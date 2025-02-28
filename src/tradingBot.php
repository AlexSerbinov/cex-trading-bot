<?php

declare(strict_types=1);

// Підключаємо клас MockOrderBook
require_once __DIR__ . '/MockOrderBook.php';

/**
 * TradingBot - an automated bot for simulating trades on a cryptocurrency exchange.
 */
class TradingBot
{
    // public const TRADE_SERVER_URL = 'http://195.7.7.93:18080';
    public const TRADE_SERVER_URL = 'http://164.68.117.90:18080';
    public const USE_MOCK_DATA = false; // Switch to use mock data instead of Kraken API
    public const EXTERNAL_API_URL = 'https://api.kraken.com/0/public/Depth?pair=LTCUSDT&count=50'; // Додаємо URL для зовнішнього API
    public const BOT_USER_ID = 5;
    public const BOT_BALANCE_LTC = 50.0; // Bot's balance in LTC (can be extended for each pair)
    public const MIN_ORDERS = 13;
    public const MAX_ORDERS = 15;
    public const TAKER_FEE = '0.07';
    public const MAKER_FEE = '0.02';
    public const ORDER_SOURCE = 'bot order';
    public const MARKET_TRADE_SOURCE = 'bot trade';
    public const MARKET_MAKER_ORDER_PROBABILITY = 0.7; // Configurable probability for market maker orders (0.0 to 1.0)

    // Trading pairs configuration
    public const TRADING_PAIRS = ['LTC_USDT', 'ETH_USDT'];

    // Delay constants (in milliseconds)
    public const DELAY_RUN_MIN = 100; // 0.1 second
    public const DELAY_RUN_MAX = 500; // 0.5 seconds
    public const DELAY_CLEAR_MIN = 10; // 10 ms
    public const DELAY_CLEAR_MAX = 25; // 25 ms
    public const DELAY_INIT_MIN = 15; // 15 ms
    public const DELAY_INIT_MAX = 50; // 50 ms
    public const DELAY_MAINTAIN_MIN = 100; // 100 ms
    public const DELAY_MAINTAIN_MAX = 200; // 200 ms
    public const DELAY_BETWEEN_PAIRS = 50; // 50 ms delay between pair cycles

    private ApiClient $apiClient;
    private Logger $logger;
    private array $bots = []; // Store bot instances for each pair

    /**
     * Constructor for TradingBot.
     */
    public function __construct()
    {
        $this->apiClient = new ApiClient();
        $this->logger = new Logger();
    }

    /**
     * Runs the bot for all trading pairs in parallel simulation.
     */
    public function run(): void
    {
        foreach (self::TRADING_PAIRS as $pair) {
            $this->bots[$pair] = new PairBot($this->apiClient, $this->logger, $pair);
            $this->logger->log(sprintf('Initializing bot for pair %s...', $pair));
            $this->bots[$pair]->initialize();
        }

        while (true) {
            try {
                foreach (self::TRADING_PAIRS as $pair) {
                    $this->bots[$pair]->processCycle();
                    $this->randomDelay(self::DELAY_BETWEEN_PAIRS, self::DELAY_BETWEEN_PAIRS); // Small delay between pairs
                }
            } catch (Exception $e) {
                $this->logger->error(sprintf('Error for all pairs: %s', $e->getMessage()));
                $this->randomDelay(self::DELAY_RUN_MIN, self::DELAY_RUN_MAX);
            }
        }
    }

    /**
     * Random delay in milliseconds using usleep.
     *
     * @param int $min Minimum delay in milliseconds
     * @param int $max Maximum delay in milliseconds
     */
    private function randomDelay(int $min, int $max): void
    {
        $delayMs = rand($min, $max); // Delay in milliseconds
        usleep($delayMs * 1000); // Convert to microseconds for accuracy
    }
}

/**
 * Class for handling individual pair trading logic.
 */
class PairBot
{
    private const BOT_USER_ID = 5;
    private const BOT_BALANCE = 50.0; // Balance per pair (can be customized per pair if needed)
    private const MIN_ORDERS = 13;
    private const MAX_ORDERS = 15;
    private const TAKER_FEE = '0.07';
    private const MAKER_FEE = '0.02';
    private const ORDER_SOURCE = 'bot order';
    private const MARKET_TRADE_SOURCE = 'bot trade';
    private const MARKET_MAKER_ORDER_PROBABILITY = 0.7; // Configurable probability for market maker orders

    private string $pair;
    private ApiClient $apiClient;
    private Logger $logger;
    private bool $lastActionWasSell = false;

    /**
     * Constructor for PairBot.
     *
     * @param ApiClient $apiClient API client instance
     * @param Logger $logger Logger instance
     * @param string $pair Trading pair (e.g., 'LTC_USDT', 'ETH_USDT')
     */
    public function __construct(ApiClient $apiClient, Logger $logger, string $pair)
    {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->pair = $pair;
    }

    /**
     * Initializes the order book for the specific pair.
     */
    public function initialize(): void
    {
        $this->clearAllOrders();
        $this->logger->log(sprintf('All existing orders cleared for pair %s.', $this->pair));
        $this->initializeOrderBook();
        $this->logger->log(sprintf('Order book initialized for pair %s.', $this->pair));
    }

    /**
     * Processes a single cycle for the pair.
     */
    public function processCycle(): void
    {
        try {
            $externalOrderBook = $this->getExternalOrderBook();
            $pendingOrders = $this->getPendingOrders();
            $currentBids = $this->getCurrentOrderBook(2); // Bids
            $currentAsks = $this->getCurrentOrderBook(1); // Asks

            $marketPrice = $this->calculateMarketPrice($externalOrderBook);
            $this->logger->log(sprintf('Market price for %s: %.6f, bestBid = %.6f, bestAsk = %.6f', $this->pair, $marketPrice, $externalOrderBook['bids'][0][0], $externalOrderBook['asks'][0][0]));

            $this->logger->log(sprintf('Current bids for %s: %d, Current asks: %d, Pending Orders: %d', $this->pair, count($currentBids), count($currentAsks), count($pendingOrders)));

            $this->maintainOrderCount($currentBids, $currentAsks, $marketPrice, $pendingOrders);

            $this->performRandomAction($currentBids, $currentAsks, $pendingOrders, $marketPrice);
        } catch (Exception $e) {
            $this->logger->error(sprintf('Error for pair %s: %s', $this->pair, $e->getMessage()));
        }
    }

    /**
     * Fetches the order book from Kraken or mock data for the specific pair.
     *
     * @return array<string, array>
     * @throws RuntimeException If the request fails
     */
    private function getExternalOrderBook(): array
    {
        if (TradingBot::USE_MOCK_DATA) {
            $mockBook = new MockOrderBook();
            return $mockBook->getMockOrderBook($this->pair);
        }

        $maxRetries = 3;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                $url = str_replace('LTCUSDT', $this->pair, TradingBot::TRADE_SERVER_URL);
                $response = $this->apiClient->get($url);
                $data = json_decode($response, true);
                if (!isset($data['result'][$this->pair])) {
                    throw new RuntimeException(sprintf('Failed to fetch order book from Kraken for pair %s: Invalid response', $this->pair));
                }
                return [
                    'bids' => $data['result'][$this->pair]['bids'],
                    'asks' => $data['result'][$this->pair]['asks'],
                ];
            } catch (RuntimeException $e) {
                $retryCount++;
                if ($retryCount === $maxRetries) {
                    throw $e;
                }
                $this->logger->error(sprintf('Retry %d/%d for Kraken API for pair %s: %s', $retryCount, $maxRetries, $this->pair, $e->getMessage()));
                $this->randomDelay(500, 1000); // Delay before retry
            }
        }
        throw new RuntimeException(sprintf('Max retries reached for Kraken API for pair %s', $this->pair));
    }

    /**
     * Fetches the current order book from your exchange for the specific pair.
     *
     * @param int $side Order type: 1 - asks, 2 - bids
     * @return array
     * @throws RuntimeException If the request fails
     */
    private function getCurrentOrderBook(int $side): array
    {
        $body = [
            'method' => 'order.book',
            'params' => [$this->pair, $side, 0, 100],
            'id' => 1,
        ];
        $response = $this->apiClient->post(TradingBot::TRADE_SERVER_URL, json_encode($body));
        $data = json_decode($response, true);

        if ($data['error'] !== null) {
            $this->logger->log(sprintf('API response for side=%d for pair %s: %s', $side, $this->pair, json_encode($data, JSON_PRETTY_PRINT)));
        }

        if (isset($data['result']['orders'])) {
            return $data['result']['orders'];
        }
        $this->logger->error(sprintf('Unexpected response structure for side=%d for pair %s: %s', $side, $this->pair, json_encode($data, JSON_PRETTY_PRINT)));
        return [];
    }

    /**
     * Fetches the list of open orders for the bot for the specific pair.
     *
     * @return Order[]
     * @throws RuntimeException If the request fails
     */
    private function getPendingOrders(): array
    {
        $body = [
            'method' => 'order.pending',
            'params' => [self::BOT_USER_ID, $this->pair, 0, 100],
            'id' => 1,
        ];
        $response = $this->apiClient->post(TradingBot::TRADE_SERVER_URL, json_encode($body));
        $data = json_decode($response, true);

        if ($data['error'] !== null) {
            $this->logger->log(sprintf('API response for pending orders for pair %s: %s', $this->pair, json_encode($data, JSON_PRETTY_PRINT)));
        }

        return $data['result']['records'] ?? [];
    }

    /**
     * Clears all bot orders for the specific pair.
     */
    private function clearAllOrders(): void
    {
        $orders = $this->getPendingOrders();
        foreach ($orders as $order) {
            $this->cancelOrder($order['id']);
            $this->logger->log(sprintf('Cancelled order: %d for pair %s', $order['id'], $this->pair));
            $this->randomDelay(TradingBot::DELAY_CLEAR_MIN, TradingBot::DELAY_CLEAR_MAX); // Delay 10-25 ms
        }
    }

    /**
     * Places a limit order for the specific pair.
     *
     * @param int $side Order type: 1 - sell, 2 - buy
     * @param string $amount Order volume
     * @param string $price Order price
     * @return mixed
     * @throws RuntimeException If the request fails
     */
    private function placeLimitOrder(int $side, string $amount, string $price)
    {
        $body = [
            'method' => 'order.put_limit',
            'params' => [self::BOT_USER_ID, $this->pair, $side, $amount, $price, self::TAKER_FEE, self::MAKER_FEE, self::ORDER_SOURCE],
            'id' => 1,
        ];
        $response = $this->apiClient->post(TradingBot::TRADE_SERVER_URL, json_encode($body));
        $data = json_decode($response, true);

        if ($data['error'] !== null) {
            $this->logger->log(sprintf('Order placement result for side=%d for pair %s: %s', $side, $this->pair, json_encode($data, JSON_PRETTY_PRINT)));
        }

        return $data;
    }

    /**
     * Cancels an order for the specific pair.
     *
     * @param int $orderId Order ID to cancel
     * @return mixed
     * @throws RuntimeException If the request fails
     */
    private function cancelOrder(int $orderId)
    {
        $body = [
            'method' => 'order.cancel',
            'params' => [self::BOT_USER_ID, $this->pair, $orderId],
            'id' => 1,
        ];
        $response = $this->apiClient->post(TradingBot::TRADE_SERVER_URL, json_encode($body));
        $data = json_decode($response, true);

        if ($data['error'] !== null) {
            $this->logger->log(sprintf('Order cancellation result for order %d for pair %s: %s', $orderId, $this->pair, json_encode($data, JSON_PRETTY_PRINT)));
        }

        return $data;
    }

    /**
     * Executes a market order (simulating trades) for the specific pair.
     *
     * @param int $side Order type: 1 - sell, 2 - buy
     * @param string $amount Order volume
     * @return bool
     */
    private function placeMarketOrder(int $side, string $amount): bool
    {
        $body = [
            'method' => 'order.put_market',
            'params' => [self::BOT_USER_ID, $this->pair, $side, $amount, self::TAKER_FEE, self::MARKET_TRADE_SOURCE],
            'id' => 1,
        ];
        $response = $this->apiClient->post(TradingBot::TRADE_SERVER_URL, json_encode($body));
        $data = json_decode($response, true);

        $this->logger->log(sprintf('Market order result for side=%d for pair %s: %s', $side, $this->pair));

        return true; // Simulation of successful execution
    }

    /**
     * Scales order volumes to fit the bot's balance for the specific pair.
     *
     * @param array $orders Orders from Kraken
     * @param float $totalAvailable Available balance
     * @param bool $isLTC Whether it's LTC (true) or USDT (false)
     * @return array Scaled orders
     */
    private function scaleVolumes(array $orders, float $totalAvailable, bool $isLTC): array
    {
        $totalVolume = array_reduce($orders, fn($sum, $order) => $sum + (float)$order[1], 0.0);
        $scaleFactor = $totalAvailable / $totalVolume;

        return array_map(function ($order) use ($scaleFactor, $isLTC) {
            return [
                'price' => $order[0],
                'amount' => number_format((float)$order[1] * $scaleFactor, 8, '.', ''),
                'side' => $isLTC ? 2 : 1, // 2 - buy for LTC, 1 - sell for USDT
            ];
        }, array_slice($orders, 0, 15)); // Limit to 15 orders
    }

    /**
     * Random delay in milliseconds using usleep.
     *
     * @param int $min Minimum delay in milliseconds
     * @param int $max Maximum delay in milliseconds
     */
    private function randomDelay(int $min, int $max): void
    {
        $delayMs = rand($min, $max); // Delay in milliseconds
        usleep($delayMs * 1000); // Convert to microseconds for accuracy
    }

    /**
     * Initializes the order book for the specific pair, limiting to 15 orders.
     */
    private function initializeOrderBook(): void
    {
        $externalOrderBook = $this->getExternalOrderBook();
        $scaledBids = $this->scaleVolumes($externalOrderBook['bids'], self::BOT_BALANCE, true); // Half balance for bids
        $scaledAsks = $this->scaleVolumes($externalOrderBook['asks'], self::BOT_BALANCE, false); // Half balance for asks

        foreach (array_slice($scaledBids, 0, 15) as $bid) {
            $this->placeLimitOrder($bid['side'], $bid['amount'], $bid['price']);
            $this->logger->log(sprintf('Initialized bid for %s: %s LTC @ %s', $this->pair, $bid['amount'], $bid['price']));
            $this->randomDelay(TradingBot::DELAY_INIT_MIN, TradingBot::DELAY_INIT_MAX); // Delay 15-50 ms
        }

        foreach (array_slice($scaledAsks, 0, 15) as $ask) {
            $this->placeLimitOrder($ask['side'], $ask['amount'], $ask['price']);
            $this->logger->log(sprintf('Initialized ask for %s: %s LTC @ %s', $this->pair, $ask['amount'], $ask['price']));
            $this->randomDelay(TradingBot::DELAY_INIT_MIN, TradingBot::DELAY_INIT_MAX); // Delay 15-50 ms
        }
    }

    /**
     * Maintains the number of orders within 13-15 for bids and asks for the specific pair.
     *
     * @param array $currentBids Current bids
     * @param array $currentAsks Current asks
     * @param float $marketPrice Market price
     * @param Order[] $pendingOrders Open orders
     */
    private function maintainOrderCount(array &$currentBids, array &$currentAsks, float $marketPrice, array $pendingOrders): void
    {
        // Add bids if there are too few
        while (count($currentBids) < self::MIN_ORDERS) {
            $bidPrice = number_format($marketPrice * (0.98 + mt_rand() / mt_getrandmax() * 0.04), 6, '.', '');
            $bidAmount = number_format(0.01 + mt_rand() / mt_getrandmax() * 0.19, 8, '.', '');
            $this->placeLimitOrder(2, $bidAmount, $bidPrice);
            $this->logger->log(sprintf('Added bid to reach 13-15 for %s: %s LTC @ %s', $this->pair, $bidAmount, $bidPrice));
            $this->randomDelay(TradingBot::DELAY_MAINTAIN_MIN, TradingBot::DELAY_MAINTAIN_MAX); // Delay 100-200 ms
            $currentBids[] = ['price' => $bidPrice, 'amount' => $bidAmount, 'side' => 2, 'type' => 2, 'id' => time()];
        }

        // Add asks if there are too few
        while (count($currentAsks) < self::MIN_ORDERS) {
            $askPrice = number_format($marketPrice * (1.02 + mt_rand() / mt_getrandmax() * 0.04), 6, '.', '');
            $askAmount = number_format(0.01 + mt_rand() / mt_getrandmax() * 0.19, 8, '.', '');
            $this->placeLimitOrder(1, $askAmount, $askPrice);
            $this->logger->log(sprintf('Added ask to reach 13-15 for %s: %s LTC @ %s', $this->pair, $askAmount, $askPrice));
            $this->randomDelay(TradingBot::DELAY_MAINTAIN_MIN, TradingBot::DELAY_MAINTAIN_MAX); // Delay 100-200 ms
            $currentAsks[] = ['price' => $askPrice, 'amount' => $askAmount, 'side' => 1, 'type' => 1, 'id' => time()];
        }

        // Cancel excess bids (more than 15)
        if (count($currentBids) > self::MAX_ORDERS) {
            $bidsToCancel = count($currentBids) - self::MAX_ORDERS;
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            usort($bids, fn($a, $b) => (float)$a['price'] - (float)$b['price']); // Sort by lowest prices
            for ($i = 0; $i < $bidsToCancel && count($bids) > 0; $i++) {
                $this->cancelOrder($bids[$i]['id']);
                $this->logger->log(sprintf('Cancelled excess bid for %s: %d @ %s', $this->pair, $bids[$i]['id'], $bids[$i]['price']));
                $this->randomDelay(TradingBot::DELAY_MAINTAIN_MIN, TradingBot::DELAY_MAINTAIN_MAX);
            }
        }

        // Cancel excess asks (more than 15)
        if (count($currentAsks) > self::MAX_ORDERS) {
            $asksToCancel = count($currentAsks) - self::MAX_ORDERS;
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
            usort($asks, fn($a, $b) => (float)$b['price'] - (float)$a['price']); // Sort by highest prices
            for ($i = 0; $i < $asksToCancel && count($asks) > 0; $i++) {
                $this->cancelOrder($asks[$i]['id']);
                $this->logger->log(sprintf('Cancelled excess ask for %s: %d @ %s', $this->pair, $asks[$i]['id'], $asks[$i]['price']));
                $this->randomDelay(TradingBot::DELAY_MAINTAIN_MIN, TradingBot::DELAY_MAINTAIN_MAX);
            }
        }
    }

    /**
     * Performs random actions (adding, canceling, simulating trades) for the specific pair.
     *
     * @param array $currentBids Current bids
     * @param array $currentAsks Current asks
     * @param Order[] $pendingOrders Open orders
     * @param float $marketPrice Market price
     */
    private function performRandomAction(array &$currentBids, array &$currentAsks, array $pendingOrders, float $marketPrice): void
    {
        $action = mt_rand() / mt_getrandmax();
        if ($action < 0.3 && count($currentBids) < self::MAX_ORDERS) {
            $bidPrice = number_format($marketPrice * (0.995 + mt_rand() / mt_getrandmax() * 0.005), 6, '.', '');
            $bidAmount = number_format(0.01 + mt_rand() / mt_getrandmax() * 0.09, 8, '.', '');
            $this->placeLimitOrder(2, $bidAmount, $bidPrice);
            $this->logger->log(sprintf('Placed bid for %s: %s LTC @ %s', $this->pair, $bidAmount, $bidPrice));
        } elseif ($action < 0.6 && count($currentAsks) < self::MAX_ORDERS) {
            $askPrice = number_format($marketPrice * (1.005 + mt_rand() / mt_getrandmax() * 0.005), 6, '.', '');
            $askAmount = number_format(0.01 + mt_rand() / mt_getrandmax() * 0.09, 8, '.', '');
            $this->placeLimitOrder(1, $askAmount, $askPrice);
            $this->logger->log(sprintf('Placed ask for %s: %s LTC @ %s', $this->pair, $askAmount, $askPrice));
        } elseif ($action < 0.8 && count($pendingOrders) > 0) {
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
            usort($bids, fn($a, $b) => (float)$b['price'] - (float)$a['price']);
            usort($asks, fn($a, $b) => (float)$a['price'] - (float)$b['price']);
            if (count($bids) > 0 && count($asks) > 0) {
                $rand = mt_rand() / mt_getrandmax();
                if ($rand < 0.5) {
                    $this->logger->log(sprintf('random= %.2f bids.length > 0 && asks.length > 0 for %s', $rand, $this->pair));
                    $this->cancelOrder(end($bids)['id']); // Cancel the lowest bid (not the top one)
                    $this->logger->log(sprintf('Cancelled bottom bid for %s: %d @ %s', $this->pair, end($bids)['id'], end($bids)['price']));
                } else {
                    $this->cancelOrder(end($asks)['id']); // Cancel the highest ask (not the top one)
                    $this->logger->log(sprintf('Cancelled bottom ask for %s: %d @ %s', $this->pair, end($asks)['id'], end($asks)['price']));
                }
            } elseif (count($bids) > 0) {
                $this->cancelOrder(end($bids)['id']); // Cancel the lowest bid
                $this->logger->log(sprintf('Cancelled bottom bid for %s: %d @ %s', $this->pair, end($bids)['id'], end($bids)['price']));
            } elseif (count($asks) > 0) {
                $this->cancelOrder(end($asks)['id']); // Cancel the highest ask
                $this->logger->log(sprintf('Cancelled bottom ask for %s: %d @ %s', $this->pair, end($asks)['id'], end($asks)['price']));
            }
        } elseif (count($pendingOrders) > 0) {
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
            usort($bids, fn($a, $b) => (float)$b['price'] - (float)$a['price']);
            usort($asks, fn($a, $b) => (float)$a['price'] - (float)$b['price']);
            if (count($bids) > 0 && count($asks) > 0) {
                if ($this->lastActionWasSell) {
                    $this->placeMarketOrder(2, $asks[0]['amount']); // Buy at the lowest ask
                    $this->logger->log(sprintf('Market trade for %s: Bought %s LTC @ %s', $this->pair, $asks[0]['amount'], $asks[0]['price']));
                    $this->lastActionWasSell = false;
                } else {
                    $this->placeMarketOrder(1, $bids[0]['amount']); // Sell at the highest bid
                    $this->logger->log(sprintf('Market trade for %s: Sold %s LTC @ %s', $this->pair, $bids[0]['amount'], $bids[0]['price']));
                    $this->lastActionWasSell = true;
                }
            } elseif (count($bids) > 0) {
                $this->placeMarketOrder(1, $bids[0]['amount']);
                $this->logger->log(sprintf('Market trade for %s: Sold %s LTC @ %s', $this->pair, $bids[0]['amount'], $bids[0]['price']));
            } elseif (count($asks) > 0) {
                $this->placeMarketOrder(2, $asks[0]['amount']);
                $this->logger->log(sprintf('Market trade for %s: Bought %s LTC @ %s', $this->pair, $asks[0]['amount'], $asks[0]['price']));
            }
        }
    }

    /**
     * Calculates the market price as the average between the best bid and ask for the specific pair.
     *
     * @param array $orderBook Order book from Kraken
     * @return float Market price
     */
    private function calculateMarketPrice(array $orderBook): float
    {
        return (floatval($orderBook['bids'][0][0]) + floatval($orderBook['asks'][0][0])) / 2;
    }
}

/**
 * Class for handling API requests via CURL.
 */
class ApiClient
{
    /**
     * Performs a GET request.
     *
     * @param string $url URL for the request
     * @return string Server response
     * @throws RuntimeException If the request fails
     */
    public function get(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('CURL error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }

    /**
     * Performs a POST request.
     *
     * @param string $url URL for the request
     * @param string $data Data to send
     * @return string Server response
     * @throws RuntimeException If the request fails
     */
    public function post(string $url, string $data): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('CURL error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }
}

/**
 * Class for logging.
 */
class Logger
{
    /**
     * Logs a message.
     *
     * @param string $message Message to log
     */
    public function log(string $message): void
    {
        echo '[' . date('Y-m-d H:i:s:ms') . '] ' . $message . PHP_EOL;
        // For real logging, you can use file_put_contents or Monolog
    }

    /**
     * Logs an error.
     *
     * @param string $message Error message
     */
    public function error(string $message): void
    {
        $this->log('ERROR: ' . $message);
    }
}

// Run the bot
$bot = new TradingBot();
$bot->run();