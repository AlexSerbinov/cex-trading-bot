<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../../config/config.php'; // Assuming Config is in config/ 

/**
 * Class for managing interactions with the internal trade server
 */
class TradeServer {
    private ApiClient $apiClient;
    private Logger $logger;
    private static ?TradeServer $instance = null;
    private array $cachedPairs = []; // Moved from ExchangeManager
    private string $tradeServerUrl;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->apiClient = new ApiClient();
        $this->logger = Logger::getInstance();
        
        // Використовуємо URL з конфігурації або значення за замовчуванням
        $this->logger->log('-=-=-=-=-=-=-= Trade Server URL: ' . Config::getTradeServerUrl() . ' -=-=-=-=-=-=-=');
        $this->tradeServerUrl = Config::getTradeServerUrl();
        if (empty($this->tradeServerUrl)) {
            $this->logger->error("Trade Server URL is not configured!");
            // Provide a default or throw an error if critical
            // $this->tradeServerUrl = 'http://164.68.117.90:18080'; // Example default, adjust as needed
        }
    }

    /**
     * Getting the instance of the class (Singleton)
     */
    public static function getInstance(): TradeServer {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Отримати баланси користувача
     * 
     * @param int $userId ID користувача
     * @return array Баланси користувача
     */
    public function getUserBalances(int $userId): array {
        try {
            $response = $this->sendRequest('balance.query', [$userId]);
            if (isset($response['result'])) {
                return $response['result'];
            }
            
            $this->logger->log('Error getting balances: Invalid response format');
            return [];
        } catch (Exception $e) {
            $this->logger->log('Error getting balances: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Оновити баланс користувача
     * 
     * @param int $userId ID користувача
     * @param string $currency Валюта
     * @param string $type Тип операції (deposit/withdraw)
     * @param int $operationId Унікальний ID операції (повинен бути числом!)
     * @param string $amount Сума
     * @param array $extra Додаткові параметри
     * @return array Результат операції
     */
    public function updateBalance(int $userId, string $currency, string $type, int $operationId, string $amount, array $extra = []): array {
        try {
            // Переконуємося, що amount це рядок
            if (!is_string($amount)) {
                $amount = (string)$amount;
            }
            
            // Переконуємося, що extra серіалізується як пустий об'єкт {}, а не масив []
            if (empty($extra)) {
                $extraObj = new stdClass(); // Пустий об'єкт {} в JSON
            } else {
                $extraObj = (object)$extra; // Перетворюємо масив на об'єкт
            }
            
            $this->logger->log("Sending balance update request: userId={$userId}, currency={$currency}, type={$type}, operationId={$operationId}, amount={$amount}");
            
            // ВАЖЛИВО: operationId передається як число, без приведення до рядка
            $response = $this->sendRequest('balance.update', [$userId, $currency, $type, $operationId, $amount, $extraObj]);
            
            if (isset($response['result'])) {
                $this->logger->log("Balance updated for {$currency}: {$type} {$amount}");
                return $response['result'];
            }
            
            if (isset($response['error'])) {
                $this->logger->log("Error updating balance: " . json_encode($response['error']));
                throw new Exception(json_encode($response['error']));
            }
            
            $this->logger->log('Error updating balance: Invalid response format');
            throw new Exception('Invalid response format');
        } catch (Exception $e) {
            $this->logger->log('Error updating balance: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Отримати список доступних пар на трейд-сервері
     * 
     * @return array Список пар
     */
    public function getMarketPairs(): array {
        try {
            $response = $this->sendRequest('market.list', []);
            
            if (isset($response['result'])) {
                return array_keys($response['result']);
            }
            
            $this->logger->log('Error getting market pairs: Invalid response format');
            return [];
        } catch (Exception $e) {
            $this->logger->log('Error getting market pairs: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Відправити запит на трейд-сервер
     * 
     * @param string $method Метод API
     * @param array $params Параметри
     * @return array Відповідь
     */
    private function sendRequest(string $method, array $params): array {
        $data = [
            'method' => $method,
            'params' => $params,
            'id' => time()
        ];
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($this->tradeServerUrl, false, $context);
        
        if ($result === false) {
            throw new Exception('Failed to connect to trade server');
        }
        
        $response = json_decode($result, true);
        
        if ($response === null) {
            throw new Exception('Invalid JSON response from trade server');
        }
        
        return $response;
    }

    /**
     * Checking the availability of a pair on the trade server
     *
     * @param string $pair The trading pair
     * @return bool Whether the pair is available on the trade server
     */
    public function isPairAvailableOnTradeServer(string $pair): bool {
        // Check cache first
        if (isset($this->cachedPairs[$pair])) {
            return $this->cachedPairs[$pair];
        }
        
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
                $this->cachedPairs[$pair] = false; // Cache the negative result
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
            $this->cachedPairs[$pair] = false; // Cache the error state as false
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
    public function checkPairAvailabilityWithReverse(string $pair): array {
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
     * Getting the list of available pairs for the exchange
     *
     * @return array The list of available pairs
     */
    public function getAvailablePairsOnTradeServer(): array {
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

    public function getOpenOrders(string $pair): array {
        $this->logger->log("[{$pair}] Getting open orders");
        
        $body = [
            'method' => 'order.pending',
            'params' => [Config::BOT_USER_ID, $pair, 0, 100],
            'id' => 1,
        ];
        
        try {
            // Use the getTradeServerUrl method instead of the constant
            $url = Config::getTradeServerUrl();
            // $this->logger->log("[{$pair}] Sending request to: {$url}");
            
            $json = json_encode($body);
            
            // Add a shorter timeout for faster problem detection
            $startTime = microtime(true);
            $response = $this->apiClient->post($url, $json);
            $endTime = microtime(true);
            $execTime = round(($endTime - $startTime) * 1000);
            
            $this->logger->log("[{$pair}] Request execution time: {$execTime}ms");
            
            $data = json_decode($response, true);
            
            // $this->logger->log("[{$pair}] Open orders response: " . json_encode($data));
            
            if (isset($data['result']['records']) && is_array($data['result']['records'])) {
                return $data['result']['records'];
            }
            
            $this->logger->error("[{$pair}] Failed to get open orders: " . json_encode($data));
            return [];
        } catch (Exception $e) {
            $this->logger->error("[{$pair}] Exception when getting open orders: " . $e->getMessage());
            return [];
        }
    }

    public function cancelOrder(int $orderId, string $pair): array {
        $this->logger->log("[{$pair}] Cancelling order {$orderId}");
        
        $body = [
            'method' => 'order.cancel',
            'params' => [Config::BOT_USER_ID, $pair, $orderId],
            'id' => 1,
        ];
        
        try {
            // Use the getTradeServerUrl method instead of the constant
            $url = Config::getTradeServerUrl();
            
            $json = json_encode($body);
            
            // Add tracking of execution time
            $startTime = microtime(true);
            $response = $this->apiClient->post($url, $json);
            $endTime = microtime(true);
            $execTime = round(($endTime - $startTime) * 1000);
            
            // $this->logger->log("[{$pair}] Cancel request execution time: {$execTime}ms");
            
            $data = json_decode($response, true);
            return $data;
        } catch (Exception $e) {
            $this->logger->error("[{$pair}] Exception when cancelling order: " . $e->getMessage());
            return ['error' => ['message' => $e->getMessage()]];
        }
    }

    /**
     * Getting the balance of a bot from the trade server
     */
    public function getBotBalanceFromTradeServer(int $botId, string $currency = ''): float
    {
        try {
            // $url = Config::getTradeServerUrl(); // Already have this in $this->tradeServerUrl
            
            $requestBody = json_encode([
                'method' => 'balance.query',
                'params' => [$botId],
                'id' => time(), // Use time() for unique ID like in sendRequest
            ]);
    
            // Use the class's apiClient instance
            // $apiClient = new ApiClient(); 
            $response = $this->apiClient->post($this->tradeServerUrl, $requestBody);
            $result = json_decode($response, true);
            
            $this->logger->log("Balance of bot {$botId} from the trade server: " . json_encode($result));
            
            if (!isset($result['result']) || !is_array($result['result'])) {
                $this->logger->error("Incorrect response from the trade server when getting the bot balance");
                return 0.0;
            }
    
            if (!empty($currency)) {
                return isset($result['result'][$currency]['available']) 
                    ? (float)$result['result'][$currency]['available'] 
                    : 0.0;
            }
    
            // Calculate total balance if no specific currency is requested
            // This part seems different from the original BotManager method logic
            // Let's keep the logic focused on getting a specific currency or failing
            $this->logger->error("No specific currency provided to getBotBalanceFromTradeServer");
            return 0.0; // Return 0.0 if no currency specified, as total balance logic wasn't in original
            
            /* // Original total balance logic from BotManager - removing as it wasn't used this way
            $totalBalance = 0.0;
            foreach ($result['result'] as $currencyData) {
                if (isset($currencyData['available'])) {
                    $totalBalance += (float)$currencyData['available'];
                }
            }
            $this->logger->log("Received the total balance of bot {$botId} from the trade server: {$totalBalance}");
            return $totalBalance;
            */
        } catch (Exception $e) {
            $this->logger->error("Error getting the bot balance from the trade server: " . $e->getMessage());
            return 0.0;
        }
    }
} 