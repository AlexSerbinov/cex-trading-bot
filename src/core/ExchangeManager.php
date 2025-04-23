<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/Logger.php';

// Додаємо use для ReactPHP класів
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class for managing interactions with different exchanges
 */
class ExchangeManager
{
    private ApiClient $apiClient;
    private Logger $logger;
    private static ?ExchangeManager $instance = null;
    private array $cachedPairs = [];
    private Browser $browser; // Додаємо властивість для асинхронного браузера
    private string $tradeServerUrl; // Declare tradeServerUrl property

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->apiClient = new ApiClient();
        $this->logger = Logger::getInstance();
        // Ініціалізуємо Browser. Можна передати Loop, якщо потрібно
        $this->browser = new Browser(); 
        $this->tradeServerUrl = Config::getTradeServerUrl(); // Initialize tradeServerUrl
    }

    /**
     * Getting the instance of the class (Singleton)
     */
    public static function getInstance(): ExchangeManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get list of supported exchanges
     * @return array List of exchange names
     */
    public function getExchangesList(): array
    {
        // Повертаємо фіксований список підтримуваних бірж
        return ['binance', 'kraken', 'kucoin'];
    }

    /**
     * Get list of available trading pairs
     * @return array List of trading pairs with their available exchanges
     */
    public function getPairsList(): array
    {
        // Отримуємо доступні пари з торгового сервера
        $serverPairs = $this->getAvailablePairsOnTradeServer();
        
        // Формуємо результат у потрібному форматі для API
        $result = [];
        foreach ($serverPairs as $pair) {
            // Для кожної пари вказуємо, на яких біржах вона доступна
            // Тут для прикладу припускаємо, що основні пари доступні на обох біржах
            if (in_array($pair, ['BTC_USDT', 'ETH_USDT', 'ETH_BTC'])) {
                $result[] = [
                    'name' => $pair,
                    'exchanges' => ['binance', 'kraken']
                ];
            } else {
                $result[] = [
                    'name' => $pair,
                    'exchanges' => ['binance']
                ];
            }
        }
        
        return $result;
    }

    /**
     * Getting the API URL for the exchange and pair
     *
     * @param string $exchange The name of the exchange (kraken, binance, bitfinex)
     * @param string $pair The trading pair (for example, LTC_USDT)
     * @return string URL for the request to the exchange API
     */
    public function getApiUrl(string $exchange, string $pair): string
    {
        // Convert the pair to the format required for the specific exchange
        $formattedPair = $this->formatPairForExchange($exchange, $pair);
        
        switch (strtolower($exchange)) {
            case 'kraken':
                return "https://api.kraken.com/0/public/Depth?pair={$formattedPair}";
            case 'binance':
                return "https://api.binance.com/api/v3/depth?symbol={$formattedPair}";
            // case 'bitfinex':
            //     // For Bitfinex, we use P0 to get bids
            //     // echo "https://api-pub.bitfinex.com/v2/book/t{$formattedPair}/P0";
            //     return "https://api-pub.bitfinex.com/v2/book/t{$formattedPair}/P0";
            default:
                throw new InvalidArgumentException("Unsupported exchange: {$exchange}");
        }
    }

    /**
     * Checking the availability of a pair on the trade server
     *
     * @param string $pair The trading pair
     * @return bool Whether the pair is available on the trade server
     */
    public function isPairAvailableOnTradeServer(string $pair): bool
    {
        try {
            // Forming the URL for the request to the trade server
            $url = Config::getTradeServerUrl();
            
            // Forming the request body
            $requestBody = json_encode([
                'method' => 'market.list',
                'params' => [],
                'id' => 1,
            ]);
            
            // Executing the request
            $this->logger->log("Request to the trade server: " . $url);
            $this->logger->log("Request body: " . $requestBody);
            $response = $this->apiClient->post($url, $requestBody);
            $result = json_decode($response, true);
            $this->logger->log("Trade server response: " . json_encode($result));
            
            if (!isset($result['result']) || !is_array($result['result'])) {
                $this->logger->error("Incorrect response from the trade server: missing 'result' array");
                return false;
            }
            
            // Checking if the pair is in the list
            $pairExists = false;
            foreach ($result['result'] as $market) {
                if ($market['name'] === $pair) {
                    $pairExists = true;
                    break;
                }
            }
            
            if (!$pairExists) {
                $this->logger->log("Pair {$pair} not found in the list of available pairs on the trade server");
            } else {
                $this->logger->log("Pair {$pair} is available on the trade server");
            }
            
            // Saving the result in the cache
            $this->cachedPairs[$pair] = $pairExists;
            
            return $pairExists;
        } catch (Exception $e) {
            $this->logger->error("Connection error to the trade server: " . $e->getMessage());
            
            // For testing, you can return true, but in production it is better to return false
            return false;
        }
    }
    
    /**
     * Checking the availability of a pair on the trade server with a reverse pair check
     *
     * @param string $pair The trading pair
     * @return array The result of the check [available, reverse pair]
     */
    public function checkPairAvailabilityWithReverse(string $pair): array
    {
        // Checking the direct pair
        if ($this->isPairAvailableOnTradeServer($pair)) {
            return [true, null];
        }
        
        // If the direct pair is not available, check the reverse pair
        $parts = explode('_', $pair);
        if (count($parts) === 2) {
            $reversePair = $parts[1] . '_' . $parts[0];
            
            if ($this->isPairAvailableOnTradeServer($reversePair)) {
                return [false, $reversePair];
            }
        }
        
        return [false, null];
    }

    /**
     * Checking the availability of a pair on an exchange
     *
     * @param string $exchange The name of the exchange
     * @param string $pair The trading pair
     * @return bool Whether the pair is available on the exchange
     */
    public function isPairAvailable(string $exchange, string $pair): bool
    {
        // Simplified approach: try to get the order book for the pair
        try {
            // Getting the order book for the pair
            $orderbook = $this->getOrderBook($exchange, $pair);
            
            // If we got the order book and it contains bids and asks, the pair is available
            return !empty($orderbook) && isset($orderbook['bids']) && isset($orderbook['asks']);
        } catch (Exception $e) {
            // Log the error, but do not show it to the user
            $this->logger->debug("Error checking pair {$pair} on exchange {$exchange}: " . $e->getMessage());
            
            // The pair is not available
            return false;
        }
    }

    /**
     * Getting the order book from the exchange
     *
     * @param string $exchange The name of the exchange
     * @param string $pair The trading pair
     * @return array The order book in the standardized format
     */
    public function getOrderBook(string $exchange, string $pair): array
    {
        try {
            // Getting the URL for the request
            $url = $this->getApiUrl($exchange, $pair);
            
            // Executing the request
            $response = $this->apiClient->get($url);
            $data = json_decode($response, true);
            
            // Parsing the response depending on the exchange
            switch (strtolower($exchange)) {
                case 'kraken':
                    return $this->parseKrakenOrderBook($data, $pair);
                case 'binance':
                    return $this->parseBinanceOrderBook($data);
                // case 'bitfinex':
                //     return $this->parseBitfinexOrderBook($data, $pair);
                default:
                    throw new InvalidArgumentException("Unsupported exchange: {$exchange}");
            }
        } catch (Exception $e) {
            $this->logger->error("Error getting the order book for pair {$pair} on exchange {$exchange}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Formatting the pair for a specific exchange
     *
     * @param string $exchange The name of the exchange
     * @param string $pair The trading pair (for example, LTC_USDT)
     * @return string The formatted pair
     */
    private function formatPairForExchange(string $exchange, string $pair): string
    {
        // Splitting the pair into the base currency and the quote currency
        $parts = explode('_', $pair);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Invalid pair format: {$pair}");
        }
        
        $base = $parts[0];
        $quote = $parts[1];
        
        switch (strtolower($exchange)) {
            case 'kraken':
                // Kraken uses the format XBTUSDT for BTC/USDT
                if ($base === 'BTC') {
                    $base = 'XBT';
                }
                return $base . $quote;
            case 'binance':
                // Binance uses the format LTCUSDT for LTC/USDT
                return $base . $quote;
            // case 'bitfinex':
            //     return "{$base}{$quote}"; // LTCUSD
            default:
                throw new InvalidArgumentException("Unsupported exchange: {$exchange}");
        }
    }

    /**
     * Parsing the Kraken order book
     *
     * @param array $data The data from the Kraken API
     * @param string $pair The trading pair
     * @return array The order book in the standardized format
     */
    private function parseKrakenOrderBook(array $data, string $pair): array
    {
        // Checking for errors
        if (!empty($data['error'])) {
            throw new RuntimeException("Kraken API error: " . implode(', ', $data['error']));
        }
        
        // Getting the result
        $result = $data['result'];
        
        // Kraken may return different keys for pairs
        $pairKey = null;
        foreach ($result as $key => $value) {
            $pairKey = $key;
            break;
        }
        
        if ($pairKey === null || !isset($result[$pairKey]['asks']) || !isset($result[$pairKey]['bids'])) {
            throw new RuntimeException("Invalid Kraken response format for pair {$pair}");
        }
        
        // Formatting the bids and asks
        $bids = $result[$pairKey]['bids'];
        $asks = $result[$pairKey]['asks'];
        
        return [
            'bids' => $bids,
            'asks' => $asks,
        ];
    }

    /**
     * Parsing the Binance order book
     *
     * @param array $data The data from the Binance API
     * @return array The order book in the standardized format
     */
    private function parseBinanceOrderBook(array $data): array
    {
        // Checking for the presence of bids and asks
        if (!isset($data['bids']) || !isset($data['asks'])) {
            throw new RuntimeException("Invalid Binance response format: " . json_encode($data));
        }
        
        // Adding the timestamp to each order
        $timestamp = time();
        $bids = array_map(function($bid) use ($timestamp) {
            return [$bid[0], $bid[1], $timestamp];
        }, $data['bids']);
        
        $asks = array_map(function($ask) use ($timestamp) {
            return [$ask[0], $ask[1], $timestamp];
        }, $data['asks']);
        return [
            'bids' => $bids,
            'asks' => $asks,
        ];
    }

    /**
     * Parsing the Bitfinex order book
     *
     * @param array $data The data from the Bitfinex API
     * @param string $pair The trading pair (for getting asks)
     * @return array The order book in the standardized format
     */
    private function parseBitfinexOrderBook(array $data, string $pair): array
    {
        // Checking if the data is an array
        if (!is_array($data)) {
            return ['error' => 'Invalid Bitfinex data format'];
        }
        
        // Checking for errors
        if (isset($data[0]) && $data[0] === 'error') {
            return ['error' => $data[1] ?? 'Unknown Bitfinex error'];
        }
        
        // Getting the bids from the current response (P0)
        $bids = [];
        foreach ($data as $item) {
            if (count($item) >= 3 && $item[2] > 0) {
                $bids[] = [$item[0], $item[2], time()];
            }
        }
        
        // Getting the asks from a separate request (P1)
        $asks = [];
        try {
            $formattedPair = $this->formatPairForExchange('bitfinex', $pair);
            $asksUrl = "https://api-pub.bitfinex.com/v2/book/t{$formattedPair}/P1";
            $asksResponse = $this->apiClient->get($asksUrl);
            $asksData = json_decode($asksResponse, true);
            
            if (is_array($asksData)) {
                foreach ($asksData as $item) {
                    if (count($item) >= 3 && $item[2] < 0) {
                        // For Bitfinex, the quantity is negative
                        $asks[] = [$item[0], abs($item[2]), time()];
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Error getting the asks for Bitfinex: " . $e->getMessage());
                // If it is not possible to get the asks, return an empty array
        }
        
        return [
            'bids' => $bids,
            'asks' => $asks,
        ];
    }


    /**
     * Getting the list of available pairs for the exchange
     *
     * @return array The list of available pairs
     */
    public function getAvailablePairsOnTradeServer(): array
    {
        try {
            // Forming the URL for the request to the trade server
            $url = Config::getTradeServerUrl();
            
            // Forming the request body
            $requestBody = json_encode([
                'method' => 'market.list',
                'params' => [],
                'id' => 1,
            ]);
            
            // Executing the request
            $response = $this->apiClient->post($url, $requestBody);
            $result = json_decode($response, true);
            
            if (!isset($result['result']) || !is_array($result['result'])) {
                $this->logger->error("Invalid response from the trade server when getting the list of pairs");
                return [];
            }
            
            // Collecting the list of pairs
            $pairs = [];
            foreach ($result['result'] as $market) {
                if (isset($market['name'])) {
                    $pairs[] = $market['name'];
                }
            }
            
            return $pairs;
        } catch (Exception $e) {
            $this->logger->error("Error getting the list of pairs from the trade server: " . $e->getMessage());
            
            // Returning an empty list in case of an error
            return [];
        }
    }

    /**
     * Get open orders asynchronously.
     *
     * @param string $pair Trading pair
     * @return PromiseInterface<array> Promise resolving with the list of open orders or rejecting on failure.
     */
    public function getOpenOrders(string $pair): PromiseInterface
    {
        $this->logger->log("[{$pair}] ExchangeManager: Preparing async getOpenOrders request...");
        $body = [
            'method' => 'order.pending',
            'params' => [Config::BOT_USER_ID, $pair, 0, 100],
            'id' => 1,
        ];

        return $this->browser // Використовуємо асинхронний Browser
            ->post(
                $this->tradeServerUrl,
                ['Content-Type' => 'application/json'],
                json_encode($body)
            )
            ->then(
                function (ResponseInterface $response) use ($pair) {
                    $data = json_decode((string) $response->getBody(), true);
                    $this->logger->log("[{$pair}] ExchangeManager: Received API response for getOpenOrders.");
                    // Validate response structure
                    if (isset($data['result']['records']) && is_array($data['result']['records'])) {
                        $this->logger->log("[{$pair}] ExchangeManager: Successfully retrieved " . count($data['result']['records']) . " open orders.");
                        return $data['result']['records']; // Resolve with the orders array
                    } elseif (isset($data['error']) && $data['error'] !== null) {
                        $errorJson = json_encode($data['error']);
                        $this->logger->error("[{$pair}] ExchangeManager: API error getting open orders: {$errorJson}");
                        throw new \RuntimeException("API Error getting open orders: " . $errorJson);
                    } else {
                        $this->logger->error("[{$pair}] ExchangeManager: Invalid API response structure for getOpenOrders: " . (string)$response->getBody());
                        throw new \RuntimeException("Invalid API response structure received from server for getOpenOrders.");
                    }
                },
                function (\Exception $e) use ($pair) {
                    $this->logger->error("[{$pair}] ExchangeManager: HTTP Exception getting open orders: " . $e->getMessage());
                    $this->logger->logStackTrace("[{$pair}] ExchangeManager: Stack trace for getOpenOrders HTTP exception:");
                    throw $e; // Reject with the original exception
                }
            );
    }

    // Замінюємо реалізацію cancelOrder на асинхронну
    public function cancelOrder(int $orderId, string $pair): PromiseInterface
    {
        $this->logger->log("[{$pair}] Queuing async cancellation for order {$orderId}");
        
        $body = [
            'method' => 'order.cancel',
            'params' => [Config::BOT_USER_ID, $pair, $orderId],
            'id' => 1,
        ];
        
            $url = Config::getTradeServerUrl();
        $jsonBody = json_encode($body);

        return $this->browser->post(
            $url,
            ['Content-Type' => 'application/json'],
            $jsonBody
        )->then(
            function (ResponseInterface $response) use ($orderId, $pair) {
                // Успішна відповідь від сервера (не обов'язково успішне скасування)
                $data = json_decode((string) $response->getBody(), true);
                $this->logger->log("[{$pair}] Received API response for cancelling order {$orderId}");
                // Перевіряємо наявність помилки в самій відповіді API
                if (isset($data['error']) && $data['error'] !== null) {
                    $errorJson = json_encode($data['error']);
                    $this->logger->error("[{$pair}] API error cancelling order {$orderId}: {$errorJson}");
                    // Відхиляємо проміс з помилкою API
                    throw new \RuntimeException("API Error: " . $errorJson);
                }
                // Якщо помилки API немає, вважаємо операцію (на рівні HTTP) успішною
                 $this->logger->log("[{$pair}] Successfully processed cancellation request for order {$orderId} (check result field for details)");
                return $data; // Вирішуємо проміс з повними даними відповіді
            },
            function (\Exception $exception) use ($orderId, $pair) {
                // Помилка на рівні HTTP-запиту (немає з'єднання, таймаут тощо)
                $this->logger->error("[{$pair}] HTTP Exception when cancelling order {$orderId}: " . $exception->getMessage());
                // Відхиляємо проміс з винятком
                throw $exception;
            }
        );
    }

    /**
     * Asynchronously places a limit order.
     *
     * @param string $pair Trading pair
     * @param int $side Side (1 for ask, 2 for bid)
     * @param string $amount Amount
     * @param string $price Price
     * @return PromiseInterface Resolves with the API result array or rejects on failure.
     */
    public function placeLimitOrder(string $pair, int $side, string $amount, string $price): PromiseInterface
    {
        $this->logger->log("[{$pair}] ExchangeManager: Preparing async limit order placement request...");
        $body = [
            'method' => 'order.put_limit',
            'params' => [
                Config::BOT_USER_ID,
                $pair,
                $side,
                $amount,
                $price,
                Config::TAKER_FEE, // Assuming these are still relevant
                Config::MAKER_FEE,
                Config::ORDER_SOURCE,
            ],
            'id' => 1, // Consider using unique IDs if needed
        ];

        return $this->browser
            ->post(
                $this->tradeServerUrl, 
                ['Content-Type' => 'application/json'], 
                json_encode($body)
            )
            ->then(
                function (ResponseInterface $response) use ($pair, $side, $price, $amount) {
                    $result = json_decode((string) $response->getBody(), true);
                    $this->logger->log("[{$pair}] ExchangeManager: Received API response for placing limit order (side={$side}, price={$price}, amount={$amount})");
                    // Basic check for presence of result or error key
                    $this->logger->log("[{$pair}] ExchangeManager: Result: " . json_encode($result));
                    if (!isset($result['result']) && !isset($result['error'])) {
                         $this->logger->error("[{$pair}] ExchangeManager: Invalid API response structure for placeLimitOrder: " . (string)$response->getBody());
                         throw new \RuntimeException("Invalid API response structure received from server for placeLimitOrder.");
                    }
                    return $result; // Resolve with the full result array
                },
                function (\Exception $e) use ($pair, $side, $price, $amount) {
                    $this->logger->error("[{$pair}] ExchangeManager: HTTP Exception placing limit order (side={$side}, price={$price}, amount={$amount}): " . $e->getMessage());
                     $this->logger->logStackTrace("[{$pair}] ExchangeManager: Stack trace for placeLimitOrder HTTP exception:");
                    throw $e; // Reject with the original exception
                }
            );
    }
    
    // TODO: Implement placeMarketOrder asynchronously
    /*
    public function placeMarketOrder(string $pair, int $side, string $amount): PromiseInterface
    {
        $this->logger->log("[{$pair}] ExchangeManager: Preparing async market order placement request...");
        $body = [
            'method' => 'order.put_market',
            'params' => [
                Config::BOT_USER_ID,
                $pair,
                $side,
                $amount,
                Config::TAKER_FEE,
                Config::MARKET_TRADE_SOURCE,
            ],
            'id' => 1, 
        ];

        return $this->browser
            ->post(
                $this->tradeServerUrl, 
                ['Content-Type' => 'application/json'], 
                json_encode($body)
            )
            ->then(
                function (ResponseInterface $response) use ($pair, $side, $amount) {
                    $result = json_decode((string) $response->getBody(), true);
                    $this->logger->log("[{$pair}] ExchangeManager: Received API response for placing market order (side={$side}, amount={$amount})");
                    if (!isset($result['result']) && !isset($result['error'])) {
                         $this->logger->error("[{$pair}] ExchangeManager: Invalid API response structure for placeMarketOrder: " . (string)$response->getBody());
                         throw new \RuntimeException("Invalid API response structure received from server for placeMarketOrder.");
                    }
                    return $result;
                },
                function (\Exception $e) use ($pair, $side, $amount) {
                    $this->logger->error("[{$pair}] ExchangeManager: HTTP Exception placing market order (side={$side}, amount={$amount}): " . $e->getMessage());
                    $this->logger->logStackTrace("[{$pair}] ExchangeManager: Stack trace for placeMarketOrder HTTP exception:");
                    throw $e;
                }
            );
    }
    */
} 