<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/ExchangeManager.php';
require_once __DIR__ . '/../core/BotProcess.php';
require_once __DIR__ . '/BotStorage.php';

/**
 * Class for managing bots through the API
 */
class BotManager
{
    private Logger $logger;
    private ExchangeManager $exchangeManager;
    private BotProcess $botProcess;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->exchangeManager = ExchangeManager::getInstance();
        $this->botProcess = new BotProcess();
    }

    /**
        * Getting a list of all bots
     */
    public function getAllBots(): array
    {
        // Get bots from storage
        $storage = BotStorage::getInstance();
        $bots = $storage->getAllBots();
        
        // Format data for API response
        $formattedBots = [];
        foreach ($bots as $bot) {
            $formattedBots[] = $this->formatBotForResponse($bot);
        }
        
        return $formattedBots;
    }

    /**
     * Getting a bot by ID
     */
    public function getBotById(int $id): ?array
    {
        // Get bot from storage
        $storage = BotStorage::getInstance();
        $bot = $storage->getBotById($id);
        
        if (!$bot) {
            return null;
        }
        
        return $this->formatBotForResponse($bot);
    }

    /**
     * Adding a new bot
     */
    public function addBot(array $data): ?array
    {
        // Check for required fields
        if (!isset($data['market']) || !isset($data['exchange'])) {
            throw new InvalidArgumentException('Missing required fields: market, exchange');
        }
        
        // Check if the exchange is supported
        if (!in_array($data['exchange'], Config::SUPPORTED_EXCHANGES)) {
            throw new InvalidArgumentException('Unsupported exchange: ' . $data['exchange']);
        }
        
        // Check if the pair is available on the trade server
        try {
            $isPairAvailable = $this->exchangeManager->isPairAvailableOnTradeServer($data['market']);
            if (!$isPairAvailable) {
                throw new InvalidArgumentException('Pair ' . $data['market'] . ' is not available on the trade server');
            }
        } catch (Exception $e) {
            throw new InvalidArgumentException('Error checking pair availability: ' . $e->getMessage());
        }
        
        // Check if the pair is available on the exchange
        if (!$this->exchangeManager->isPairAvailable($data['exchange'], $data['market'])) {
            throw new InvalidArgumentException("Pair {$data['market']} is not available on the exchange {$data['exchange']}");
        }
        
        // Check for duplicates
        $storage = BotStorage::getInstance();
        $allBots = $storage->getAllBots();
        foreach ($allBots as $existingBot) {
            if ($existingBot['market'] === $data['market']) {
                $this->logger->log("Attempt to create a duplicate bot for pair {$data['market']}");
                throw new InvalidArgumentException("Bot for this pair already exists");
            }
        }
        
        // Check if the pair is available on the trade server
        if (!$this->exchangeManager->isPairAvailableOnTradeServer($data['market'])) {
            throw new InvalidArgumentException("Pair {$data['market']} is not available on the trade server");
        }
        
        // Check if the pair is available on the exchange
        if (!$this->exchangeManager->isPairAvailable($data['exchange'], $data['market'])) {
            throw new InvalidArgumentException("Pair {$data['market']} is not available on the exchange {$data['exchange']}");
        }
        
        // Prepare data for saving
        $botData = [
            'market' => $data['market'],
            'exchange' => $data['exchange'],
            'isActive' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'settings' => [
                'min_orders' => $data['settings']['min_orders'],
                'max_orders' => $data['settings']['max_orders'],
                'trade_amount_min' => $data['settings']['trade_amount_min'],
                'trade_amount_max' => $data['settings']['trade_amount_max'],
                'frequency_from' => $data['settings']['frequency_from'],
                'frequency_to' => $data['settings']['frequency_to'],
                'price_factor' => $data['settings']['price_factor'],
                'market_gap' => $data['settings']['market_gap'],
                'market_maker_order_probability' => $data['settings']['market_maker_order_probability']
            ]
        ];
        
        // Check the bot balance before creating
        $tradeAmountMax = $botData['settings']['trade_amount_max'];
        $pair = $data['market'];
        $currencies = explode('_', $pair);
        $baseCurrency = $currencies[0]; // First currency in the pair (e.g., ETH in ETH_USDT)
        
        // Get the balance of the base currency (Bot ID on the trade server = 5)
        $botBalance = $this->getBotBalanceFromTradeServer(Config::BOT_ID, $baseCurrency);
        $this->logger->log("Base currency balance: {$botBalance} {$baseCurrency}");
        
        if ($botBalance < $tradeAmountMax) {
            $this->logger->error("Insufficient balance for creating a bot: required {$tradeAmountMax} {$baseCurrency}, available {$botBalance} {$baseCurrency}");
            throw new InvalidArgumentException("Balance not enough, actual balance = {$botBalance} {$baseCurrency}, required = {$tradeAmountMax} {$baseCurrency}");
        }
        
        // Adding a bot to storage
        $storage = BotStorage::getInstance();
        $bot = $storage->addBot($botData);
        
        if (!$bot) {
            $this->logger->error("Failed to add a bot for pair {$botData['market']}");
            return null;
        }
        
        // Add a bot to the configuration for process start
        Config::addBot([
            'market' => $bot['market'],
            'isActive' => true,
            'settings' => $bot['settings']
        ]);
        
        // Start the process for the new pair
        $this->botProcess->startProcess($bot['market']);
        
        $this->logger->log("Added a new bot: ID={$bot['id']}, Pair={$bot['market']}");
        return $this->formatBotForResponse($bot);
    }

    /**
     * Updating a bot
     */
    public function updateBot(int $id, array $data): ?array
    {
        $this->logger->log("Updating bot with ID {$id}: " . json_encode($data));
        
        // Get the bot from storage
        $storage = BotStorage::getInstance();
        $bot = $storage->getBotById($id);
        
        if (!$bot) {
            $this->logger->error("Bot with ID {$id} not found");
            return null;
        }
        
        // Перевіряємо наявність налаштувань в даних запиту
        if (!isset($data['settings'])) {
            // Якщо settings не передано, але є інші параметри налаштувань, 
            // створюємо об'єкт settings з цих параметрів
            $settingsFields = [
                'min_orders', 'max_orders', 'trade_amount_min', 'trade_amount_max',
                'frequency_from', 'frequency_to', 'price_factor', 'market_gap',
                'market_maker_order_probability'
            ];
            
            $settings = [];
            foreach ($settingsFields as $field) {
                if (isset($data[$field])) {
                    $settings[$field] = $data[$field];
                    // Видаляємо поле з кореневого об'єкта, щоб уникнути дублювання
                    unset($data[$field]);
                }
            }
            
            if (!empty($settings)) {
                $data['settings'] = $settings;
            }
        }
        
        // Оновлюємо налаштування
        if (isset($data['settings']) && is_array($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                if ($value !== null) {
                    $bot['settings'][$key] = $value;
                }
            }
        }
        
        // Оновлюємо інші поля (крім settings, які вже оброблені)
        foreach ($data as $key => $value) {
            if ($key !== 'settings' && $key !== 'id' && $key !== 'market' && $value !== null) {
                $bot[$key] = $value;
            }
        }
        
        // Оновлюємо дату оновлення
        $bot['updated_at'] = date('Y-m-d H:i:s');
        
        // Зберігаємо оновленого бота
        $updatedBot = $storage->updateBot($id, $bot);
        
        if (!$updatedBot) {
            $this->logger->error("Failed to update bot with ID {$id}");
            return null;
        }
        
        // Якщо статус бота змінився, оновлюємо процес
        if (isset($data['isActive'])) {
            if ($data['isActive']) {
                $this->enableBot($id);
            } else {
                $this->disableBot($id);
            }
        }
        
        $this->logger->log("Bot with ID {$id} updated successfully");
        
        return $this->formatBotForResponse($updatedBot);
    }

    /**
     * Deleting a bot
     */
    public function deleteBot(int $id): bool
    {
        $this->logger->log("Deleting bot with ID {$id}");
        
        // Get the bot from storage
        $storage = BotStorage::getInstance();
        $bot = $storage->getBotById($id);
        
        if (!$bot) {
            $this->logger->error("Bot with ID {$id} not found for deletion");
            return false;
        }
        
        // Stop the process for the pair
        $this->botProcess->stopProcess($bot['market']);
        
        // Delete the bot from configuration
        Config::deleteBot($id);
        
        // Delete the bot from storage
        $result = $storage->deleteBot($id);
        
        if ($result) {
            $this->logger->log("Deleted bot: ID={$id}, Pair={$bot['market']}");
        } else {
            $this->logger->error("Failed to delete bot with ID {$id}");
        }
        
        return $result;
    }

    /**
     * Disabling a bot
     */
    public function disableBot(int $id): ?array
    {
        // Get the bot from storage
        $storage = BotStorage::getInstance();
        $bot = $storage->getBotById($id);
        
        if (!$bot) {
            return null;
        }
        
        // Disable the bot
        $bot['isActive'] = false;
        $bot['updated_at'] = date('Y-m-d H:i:s');
        
        // Update the bot in storage
        $updatedBot = $storage->updateBot($id, $bot);
        
        if (!$updatedBot) {
            return null;
        }
        
        // Disable the bot in configuration
        Config::disableBot($bot['market']);
        
        // Make sure the status in the response is correct
        $updatedBot['isActive'] = false;
        
        $this->logger->log("Disabled bot: ID={$id}, Pair={$bot['market']}");
        return $this->formatBotForResponse($updatedBot);
    }

    /**
     * Enabling a bot
     */
    public function enableBot(int $id): ?array
    {
        // Get the bot from storage
        $storage = BotStorage::getInstance();
        $bot = $storage->getBotById($id);
        
        if (!$bot) {
            return null;
        }
        
        // Enable the bot
        $bot['isActive'] = true;
        $bot['updated_at'] = date('Y-m-d H:i:s');
        
        // Update the bot in storage
        $updatedBot = $storage->updateBot($id, $bot);
        
        if (!$updatedBot) {
            return null;
        }
        
        // Enable the bot in configuration
        Config::enableBot($bot['market']);
        
        // Make sure the status in the response is correct
        $updatedBot['isActive'] = true;
        
        // Start the bot process
        $this->botProcess->startProcess($bot['market']);
        
        $this->logger->log("Enabled bot: ID={$id}, Pair={$bot['market']}");
        return $this->formatBotForResponse($updatedBot);
    }

    /**
     * Validation of bot data
     */
    private function validateBotData(array $botData, bool $requireAllFields = true): void
    {
        $requiredFields = [
            'market', 'trade_amount_min', 'trade_amount_max', 
            'frequency_from', 'frequency_to', 'price_factor', 'exchange',
            'min_orders', 'max_orders'
        ];
        
        if ($requireAllFields) {
        foreach ($requiredFields as $field) {
            if (!isset($botData[$field])) {
                    throw new InvalidArgumentException("Missing required field: {$field}");
                }
            }
        }
        
        // Check the correctness of the values
        if (isset($botData['trade_amount_min']) && isset($botData['trade_amount_max'])) {
        if ($botData['trade_amount_min'] <= 0 || $botData['trade_amount_max'] <= 0) {
                throw new InvalidArgumentException("Trade amount must be greater than zero");
        }
        
        if ($botData['trade_amount_min'] > $botData['trade_amount_max']) {
                throw new InvalidArgumentException("Minimum trade amount cannot be greater than maximum trade amount");
            }
        }
        
        if (isset($botData['frequency_from']) && isset($botData['frequency_to'])) {
        if ($botData['frequency_from'] <= 0 || $botData['frequency_to'] <= 0) {
                throw new InvalidArgumentException("Frequency must be greater than zero");
            }
            
            if ($botData['frequency_from'] > $botData['frequency_to']) {
                throw new InvalidArgumentException("Minimum frequency cannot be greater than maximum frequency");
            }
        }
        
        if (isset($botData['settings']['market_gap'])) {
            if ($botData['settings']['market_gap'] < 0) {
                throw new InvalidArgumentException("Market gap cannot be negative");
            }
            
            if ($botData['settings']['market_gap'] > 25) {
                throw new InvalidArgumentException("Market gap should not exceed 25% for realistic trading");
            }
        }

        if (isset($botData['settings']['min_orders']) && isset($botData['settings']['max_orders'])) {
            if ($botData['settings']['min_orders'] <= 0 || $botData['settings']['max_orders'] <= 0) {
                throw new InvalidArgumentException("Minimum orders must be greater than zero");
            }

            if ($botData['settings']['min_orders'] > $botData['settings']['max_orders']) {
                throw new InvalidArgumentException("Minimum orders cannot be greater than maximum orders");
            }
        }   

        if (isset($botData['settings']['price_factor'])) {
            if ($botData['settings']['price_factor'] <= 0) {
                throw new InvalidArgumentException("Price factor must be greater than zero");
            }
        }   
        
        if (isset($botData['settings']['market_gap'])) {
            if ($botData['settings']['market_gap'] <= 0) {
                throw new InvalidArgumentException("Market gap must be greater than zero");
            }
        }       
        
        if (isset($botData['settings']['market_maker_order_probability'])) {
            if ($botData['settings']['market_maker_order_probability'] < 0 || $botData['settings']['market_maker_order_probability'] > 100) {
                throw new InvalidArgumentException("Market maker order probability must be between 0 and 100");
            }
        }
    }

    /**
     * Formatting bot data for API response
     */
    private function formatBotForResponse(?array $bot): ?array
    {
        if (!$bot) {
            return null;
        }
        
        
        // Додаємо значення за замовчуванням для відсутніх полів
        $isActive = isset($bot['isActive']) ? $bot['isActive'] : true;
        $createdAt = isset($bot['created_at']) ? $bot['created_at'] : date('Y-m-d H:i:s');
        $updatedAt = isset($bot['updated_at']) ? $bot['updated_at'] : date('Y-m-d H:i:s');
        
        // Отримуємо налаштування з вкладеного масиву settings або з кореневих полів
        $settings = $bot['settings'] ?? [];
        
        return [
            'id' => $bot['id'],
            'market' => $bot['market'],
            'exchange' => $bot['exchange'],
            'isActive' => $isActive,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'settings' => [
                'min_orders' => $settings['min_orders'],
                'max_orders' => $settings['max_orders'],
                'trade_amount_min' => $settings['trade_amount_min'],
                'trade_amount_max' => $settings['trade_amount_max'],
                'frequency_from' => $settings['frequency_from'],
                'frequency_to' => $settings['frequency_to'],
                'price_factor' => $settings['price_factor'],
                'market_gap' => $settings['market_gap'],
                'market_maker_order_probability' => $settings['market_maker_order_probability']
            ]
        ];
    }

    /**
     * Getting the balance of a bot from the trade server
     */
    public function getBotBalanceFromTradeServer(int $botId, string $currency = ''): float
    {
        try {
            $url = Config::getTradeServerUrl();
            
            $requestBody = json_encode([
                'method' => 'balance.query',
                'params' => [$botId],
                'id' => 1,
            ]);
    
            $apiClient = new ApiClient();
            $response = $apiClient->post($url, $requestBody);
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
    
            $totalBalance = 0.0;
            foreach ($result['result'] as $currencyData) {
                if (isset($currencyData['available'])) {
                    $totalBalance += (float)$currencyData['available'];
                }
            }
    
            $this->logger->log("Received the total balance of bot {$botId} from the trade server: {$totalBalance}");
            return $totalBalance;
        } catch (Exception $e) {
            $this->logger->error("Error getting the bot balance from the trade server: " . $e->getMessage());
            return 0.0;
        }
    }
    /**
     * Updating the maximum trade amount for a bot based on the current balance
     */
    public function updateBotTradeAmountMax(int $id, float $manualBalance = 0): ?array
    {
        // Get the bot
        $storage = BotStorage::getInstance();
        $bot = $storage->getBotById($id);
        
        if (!$bot) {
            return null;
        }
        
        // Use the passed value or get the balance from the trade server
        $botBalance = $manualBalance > 0 ? $manualBalance : $this->getBotBalanceFromTradeServer(Config::BOT_ID);
        
        if ($botBalance <= 0) {
            $this->logger->error("Failed to get a valid balance for bot {$id}");
            return $bot;
        }
        
        // Update the maximum trade amount
        $bot['settings']['trade_amount_max'] = $botBalance;
        $bot['updated_at'] = date('Y-m-d H:i:s');
        
        // Update the bot in storage
        $updatedBot = $storage->updateBot($id, $bot);
        
        if ($updatedBot !== null) {
            $this->logger->log("Updated the maximum trade amount for bot: ID={$id}, Pair={$bot['market']}, New value={$botBalance}");
        }
        
        return $this->formatBotForResponse($updatedBot);
    }

    public function cancelAllOrders(int $botId): bool
    {
        // Get the bot
        $bot = $this->getBotById($botId);
        
        if (!$bot) {
            return false;
        }
        
        // Get all open orders for this bot
        $openOrders = $this->getOpenOrders($botId);
        
        if (empty($openOrders)) {
            return true;
        }
        
        // Cancel each order
        $exchangeManager = ExchangeManager::getInstance();
        foreach ($openOrders as $order) {
            $result = $exchangeManager->cancelOrder($order['id'], $bot['market']);
            
            if (!isset($result['result']) || $result['result'] === null) {
                $this->logger->error("Failed to cancel order {$order['id']} for bot {$botId}");
            }
        }
        
        return true;
    }
    
    /**
     * Проксі-метод для сумісності з фронтендом
     * Просто викликає addBot
     */
    public function createBot(array $data): ?array
    {
        return $this->addBot($data);
    }
} 