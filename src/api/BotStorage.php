<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../../config/config.php';

/**
 * Class for storing and managing bot data
 */
class BotStorage
{
    private string $storageFile;
    private array $bots = [];
    private static ?BotStorage $instance = null;
    private Logger $logger;

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->storageFile = __DIR__ . '/../../data/bots_config.json';
        $this->logger = Logger::getInstance();
        $this->loadBots();
    }

    /**
     * Getting the instance of the class (Singleton)
     */
    public static function getInstance(): BotStorage
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Loading bot data from file
     */
    private function loadBots(): void
    {
        if (!file_exists($this->storageFile)) {
            // Create directory if it doesn't exist
            $dir = dirname($this->storageFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $this->bots = [];
            $this->saveBots();
        } else {
            $content = file_get_contents($this->storageFile);
            $data = json_decode($content, true) ?: [];
            
            // Convert the format from bots_config.json to the format for BotStorage
            $this->bots = [];
            foreach ($data as $pair => $config) {
                if (isset($config['id'])) {
                    $id = $config['id'];
                    $this->bots[$id] = $this->transformBotConfig($config, $pair, $id);
                }
            }
        }
    }

    /**
     * Save bots data to file
     */
    private function saveBots(): void
    {
        // Convert the format from BotStorage to the format for bots_config.json
        $configData = [];
        foreach ($this->bots as $id => $bot) {
            $pair = $bot['market'];
            $configData[$pair] = $this->transformToConfigFormat($bot);
        }
        
        // Save in JSON format with indents
        file_put_contents($this->storageFile, json_encode($configData, JSON_PRETTY_PRINT));
        
        // Force reload the configuration in Config
        Config::reloadConfig();
    }

    /**
     * Getting all bots
     */
    public function getAllBots(): array
    {
        // Get bots from configuration
        $bots = Config::getAllBots();
        
        // Enrich bot data with additional fields
        foreach ($bots as &$bot) {
            $pair = $bot['market'];
            
            // Add bot settings
            $bot['settings'] = [
                'trade_amount_min' => $bot['trade_amount_min'] ?? 0.1,
                'trade_amount_max' => $bot['trade_amount_max'] ?? 1.0,
                'frequency_from' => $bot['frequency_from'] ?? 30,
                'frequency_to' => $bot['frequency_to'] ?? 60,
                'price_factor' => $bot['price_deviation_percent'] ?? 0.01
            ];
        }
        
        return $bots;
    }

    /**
     * Getting a bot by ID
     */
    public function getBotById(int $id): ?array
    {
        // Get bot from configuration
        $bot = Config::getBotById($id);
        
        if (!$bot) {
            return null;
        }
        
        $pair = $bot['market'];
        
        // Add bot settings
        $bot['settings'] = [
            'trade_amount_min' => $bot['trade_amount_min'] ?? 0.1,
            'trade_amount_max' => $bot['trade_amount_max'] ?? 1.0,
            'frequency_from' => $bot['frequency_from'] ?? 30,
            'frequency_to' => $bot['frequency_to'] ?? 60,
            'price_factor' => $bot['price_deviation_percent'] ?? 0.01
        ];
        
        return $bot;
    }

    /**
     * Add new bot
     */
    public function addBot(array $botData): ?array
    {
        // Reload data from file to get the latest
        $this->loadBots();
        
        // Check if a bot with the same pair already exists
        foreach ($this->bots as $bot) {
            if ($bot['market'] === $botData['market']) {
                return null;
            }
        }
        
        // Generate a new ID
        $id = $this->getNextId();
        $botData['id'] = $id;
        $botData['isActive'] = true;
        $botData['created_at'] = date('Y-m-d H:i:s');
        $botData['updated_at'] = date('Y-m-d H:i:s');
        
        $this->bots[$id] = $this->transformBotConfig($botData, $botData['market'], $id);
        $this->saveBots();
        
        $this->logger->log("Added a new bot: ID={$id}, Pair={$botData['market']}");
        return $this->bots[$id];
    }

    /**
     * Update bot data
     */
    public function updateBot(int $id, array $botData): ?array
    {
        // Reload data from file to get the latest
        $this->loadBots();
        
        if (!isset($this->bots[$id])) {
            return null;
        }
        
        $botData['id'] = $id;
        if (!isset($botData['isActive'])) {
            $botData['isActive'] = $this->bots[$id]['isActive'] ?? false;
        }
        $botData['created_at'] = $this->bots[$id]['created_at'];
        $botData['updated_at'] = date('Y-m-d H:i:s');
        
        $this->bots[$id] = $this->transformBotConfig($botData, $this->bots[$id]['market'], $id);
        $this->saveBots();
        
        $this->logger->log("Updated the bot: ID={$id}, Pair={$botData['market']}");
        return $this->bots[$id];
    }

    /**
     * Disable bot
     */
    public function disableBot(int $id): ?array
    {
        // Reload data from file to get the latest
        $this->loadBots();
        
        if (!isset($this->bots[$id])) {
            return null;
        }
        
        $this->bots[$id]['isActive'] = false;
        $this->bots[$id]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveBots();
        
        $this->logger->log("Disabled the bot: ID={$id}, Pair={$this->bots[$id]['market']}");
        return $this->bots[$id];
    }

    /**
     * Delete bot
     */
    public function deleteBot(int $id): bool
    {
        // Reload data from file to get the latest
        $this->loadBots();
        
        if (!isset($this->bots[$id])) {
            return false;
        }
        
        $pair = $this->bots[$id]['market'];
        $this->logger->log("Deleted the bot: ID={$id}, Pair={$pair}");
        
        unset($this->bots[$id]);
        
        // Reindex the bot IDs after deletion
        $this->reindexBots();
        
        $this->saveBots();
        
        return true;
    }

    /**
     * Reindexing the bot IDs
     */
    private function reindexBots(): void
    {
        if (empty($this->bots)) {
            return;
        }
        
        // Sort bots by ID
        ksort($this->bots);
        
        // Create a new array with reindexed IDs
        $newBots = [];
        $newId = 1;
        
        foreach ($this->bots as $oldId => $bot) {
            $bot['id'] = $newId;
            $newBots[$newId] = $bot;
            $newId++;
        }
        
        $this->bots = $newBots;
        $this->logger->log("Reindexed the bot IDs after deletion");
    }

    /**
     * Get next ID for new bot
     */
    private function getNextId(): int
    {
        if (empty($this->bots)) {
            return 1;
        }
        
        return max(array_keys($this->bots)) + 1;
    }

    private function transformBotConfig($config, $pair, $id)
    {
        return [
            'id' => $id,
            'market' => $pair,
            'exchange' => $config['exchange'] ?? 'binance',
            'isActive' => $config['isActive'] ?? false,
            'created_at' => $config['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $config['updated_at'] ?? date('Y-m-d H:i:s'),
            'settings' => [
                'trade_amount_min' => $config['trade_amount_min'] ?? 0.1,
                'trade_amount_max' => $config['trade_amount_max'] ?? 1,
                'frequency_from' => $config['frequency_from'] ?? 30,
                'frequency_to' => $config['frequency_to'] ?? 60,
                'price_factor' => $config['price_deviation_percent'] ?? 0.01,
                'market_gap' => $config['market_gap'] ?? 0.05,
            ]
        ];
    }

    private function transformToConfigFormat($bot)
    {
        return [
            'id' => $bot['id'],
            'exchange' => $bot['exchange'],
            'min_orders' => $bot['settings']['min_orders'] ?? 2,
            'max_orders' => $bot['settings']['max_orders'] ?? 4,
            'price_deviation_percent' => $bot['settings']['price_factor'],
            'market_gap' => $bot['settings']['market_gap'] ?? 0.05,
            'frequency_from' => $bot['settings']['frequency_from'],
            'frequency_to' => $bot['settings']['frequency_to'],
            'bot_balance' => 10,
            'isActive' => $bot['isActive'],
            'created_at' => $bot['created_at'],
            'updated_at' => $bot['updated_at'],
            'trade_amount_min' => $bot['settings']['trade_amount_min'],
            'trade_amount_max' => $bot['settings']['trade_amount_max']
        ];
    }
} 