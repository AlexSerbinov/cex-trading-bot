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
                'trade_amount_min' => $data['trade_amount_min'] ?? 0.1,
                'trade_amount_max' => $data['trade_amount_max'] ?? 1.0,
                'frequency_from' => $data['frequency_from'] ?? 30,
                'frequency_to' => $data['frequency_to'] ?? 60,
                'price_factor' => $data['price_factor'] ?? 0.01
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
        // Get the bot from storage
        $storage = BotStorage::getInstance();
        $bot = $storage->getBotById($id);
        
        if (!$bot) {
            return null;
        }
        
        // Check if the exchange can be changed
        if (isset($data['exchange']) && $data['exchange'] !== $bot['exchange']) {
            throw new InvalidArgumentException("Changing the exchange for an existing bot is not supported");
        }
        
        // Check if the pair can be changed
        if (isset($data['market']) && $data['market'] !== $bot['market']) {
            throw new InvalidArgumentException("Changing the pair for an existing bot is not supported");
        }
        
        // Update the settings
        if (isset($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                $bot['settings'][$key] = $value;
            }
        }
        
        // Update the status if it is specified
        if (isset($data['isActive'])) {
            $bot['isActive'] = (bool)$data['isActive'];
        }
        
        // Update the bot in storage
        $updatedBot = $storage->updateBot($id, $bot);
        
        if (!$updatedBot) {
            return null;
        }
        
        // Update the bot in configuration
        Config::updateBot($bot['market'], [
            'market' => $bot['market'],
            'isActive' => $bot['isActive'],
            'settings' => $bot['settings']
        ]);
        
        return $this->formatBotForResponse($updatedBot);
    }

    /**
     * Deleting a bot
     */
    public function deleteBot(int $id): bool
    {
        // Get the bot from storage
        $storage = BotStorage::getInstance();
        $bot = $storage->getBotById($id);
        
        if (!$bot) {
            return false;
        }
        
        // Stop the process for the pair
        $this->botProcess->stopProcess($bot['market']);
        
        // Delete the bot from configuration
        Config::disableBot($bot['market']);
        
        // Delete the bot from storage
        $result = $storage->deleteBot($id);
        
        if ($result) {
            $this->logger->log("Deleted bot: ID={$id}, Pair={$bot['market']}");
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
            'frequency_from', 'frequency_to', 'price_factor', 'exchange'
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
    }
    
    /**
     * Formatting bot data for API response
     */
    private function formatBotForResponse(array $bot): array
    {
        return [
            'id' => $bot['id'],
            'market' => $bot['market'],
            'exchange' => $bot['exchange'],
            'isActive' => $bot['isActive'],
            'created_at' => $bot['created_at'],
            'updated_at' => $bot['updated_at'],
            'settings' => [
                'trade_amount_min' => $bot['settings']['trade_amount_min'],
                'trade_amount_max' => $bot['settings']['trade_amount_max'],
                'frequency_from' => $bot['settings']['frequency_from'],
                'frequency_to' => $bot['settings']['frequency_to'],
                'price_factor' => $bot['settings']['price_factor'],
                'market_gap' => $bot['settings']['market_gap'] ?? 0.05,
            ]
        ];
    }

    /**
     * Getting the balance of a bot from the trade server
     */
    public function getBotBalanceFromTradeServer(int $botId, string $currency = ''): float
    {
        try {
            $url = Config::TRADE_SERVER_URL;
            
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
} 