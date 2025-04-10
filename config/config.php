<?php

declare(strict_types=1);

/**
 * Configuration constants for TradingBot.
 */
class Config
{
    // Замінено динамічний вираз на статичний метод
    // public const TRADE_SERVER_URL_DEFAULT = 'http://195.7.7.93:18080'; // demo server
    public const TRADE_SERVER_URL_DEFAULT = 'http://164.68.117.90:18080';   // dev server
    
    /**
     * Отримання URL торгового сервера
     *
     * @return string URL торгового сервера
     */
    public static function getTradeServerUrl(): string 
    {
        return getenv('TRADE_SERVER_URL') ?: self::TRADE_SERVER_URL_DEFAULT;
    }
    
    public const BOT_USER_ID = 5;
    public const TAKER_FEE = '0';
    public const MAKER_FEE = '0';
    public const ORDER_SOURCE = 'bot order';
    public const MARKET_TRADE_SOURCE = 'bot trade';

    // Delay constants (in milliseconds)
    public const DELAY_RUN_MIN = 100; // 0.1 second
    public const DELAY_RUN_MAX = 500; // 0.5 seconds
    public const DELAY_ORDER_MIN = 200; // 0.025 seconds
    public const DELAY_ORDER_MAX = 500; // 0.05 seconds
    public const DELAY_CLEAR_MIN = 10; // 10 ms
    public const DELAY_CLEAR_MAX = 25; // 25 ms
    public const DELAY_INIT_MIN = 15; // 15 ms
    public const DELAY_INIT_MAX = 50; // 50 ms
    public const DELAY_MAINTAIN_MIN = 100; // 100 ms
    public const DELAY_MAINTAIN_MAX = 200; // 200 ms

    // Supported exchanges
    public const SUPPORTED_EXCHANGES = ['binance', 'kraken'];
    
    // Path to the configuration file
    private static string $configFile = __DIR__ . '/../config/bots_config.json';
    
    // Cache configuration
    private static ?array $config = null;
    private static int $lastLoadTime = 0;
    private static int $configFileModTime = 0;
    
    // Cache update interval (in seconds)
    private const CACHE_TTL = 5;
    
    // Bot ID on the trade server
    public const BOT_ID = 5;

    public const DEAD_WATCHER_ENABLED = true; // Default true
    public const DEAD_WATCHER_CHECK_INTERVAL = 10; // Default 10 seconds, configurable
    public const DEAD_WATCHER_BOT_ID = 5; // ID бота для видалення ордерів
    public const DEAD_WATCHER_URLS = ['http://localhost:5503/dead-watcher/heartbeat']; // якщо в докері не прописано.

    /**
     * Get Dead Watcher URLs from environment or default
     */
    public static function getDeadWatcherUrls(): array
    {
        $urls = getenv('DEAD_WATCHER_URLS') ? explode(',', getenv('DEAD_WATCHER_URLS')) : self::DEAD_WATCHER_URLS;
        return array_filter($urls, fn($url) => !empty(trim($url)));
    }

    /**
     * Check if Dead Watcher is enabled
     */
    public static function isDeadWatcherEnabled(): bool
    {
        return (bool)getenv('DEAD_WATCHER_ENABLED') ?: self::DEAD_WATCHER_ENABLED;
    }

    /**
     * Get Dead Watcher check interval
     */
    public static function getDeadWatcherCheckInterval(): int
    {
        return (int)getenv('DEAD_WATCHER_CHECK_INTERVAL') ?: self::DEAD_WATCHER_CHECK_INTERVAL;
    }
    
    /**
     * Force configuration reload
     */
    public static function reloadConfig(): void
    {
        // Completely clear the cache
        self::$config = null;
        self::$lastLoadTime = 0;
        self::$configFileModTime = 0;
        self::loadConfig();   
    }
    
    /**
     * Loading configuration
     */
    private static function loadConfig(): void
    {
        // Always check the last modification time of the file
        $currentFileModTime = file_exists(self::$configFile) ? filemtime(self::$configFile) : 0;
        
        // Load data from file if:
        // 1. Cache is empty OR
        // 2. More than 1 second has passed since the last load OR
        // 3. The file has been modified since the last load
        $currentTime = time();
        if (self::$config === null || 
            ($currentTime - self::$lastLoadTime) >= 1 || 
            $currentFileModTime > self::$configFileModTime) {
            
            if (!file_exists(self::$configFile)) {
                self::$config = [];
                self::$lastLoadTime = $currentTime;
                self::$configFileModTime = 0;
                return;
            }
            
            $content = file_get_contents(self::$configFile);
            $loadedConfig = json_decode($content, true);
            
            if ($loadedConfig === null) {
                $logger = Logger::getInstance();
                $logger->error("Error decoding JSON in the configuration file: " . json_last_error_msg());
                // Save the old cache if it exists
                if (self::$config === null) {
                    self::$config = [];
                }
            } else {
                self::$config = $loadedConfig;
            }
            
            self::$lastLoadTime = $currentTime;
            self::$configFileModTime = $currentFileModTime;
        }
    }
    
    /**
     * Saving configuration
     */
    private static function saveConfig(): void
    {
        if (self::$config === null) {
            return;
        }
        
        // Create the directory if it doesn't exist
        $dir = dirname(self::$configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Save the configuration to the file
        file_put_contents(self::$configFile, json_encode(self::$config, JSON_PRETTY_PRINT));
        
        // Update the modification time of the file to detect changes
        touch(self::$configFile);
    }
    
    /**
     * Getting configuration for a pair
     */
    public static function getPairConfig(string $pair): ?array
    {
        self::loadConfig();
        
        if (!isset(self::$config[$pair])) {
            return null;
        }
        
        return self::$config[$pair];
    }
    
    /**
     * Getting a list of all active pairs
     */
    public static function getEnabledPairs(): array
    {
        self::loadConfig();
        
        $enabledPairs = [];
        foreach (self::$config as $pair => $config) {
            if (isset($config['isActive']) && $config['isActive'] === true) {
                $enabledPairs[] = $pair;
            }
        }
        
        return $enabledPairs;
    }
    
    /**
     * Getting a list of all bots
     */
    public static function getAllBots(): array
    {
        self::loadConfig();
        return self::$config;
    }
    
    /**
     * Getting a bot by ID
     */
    public static function getBotById(int $id): ?array
    {
        self::loadConfig();
        
        foreach (self::$config as $pair => $config) {
            if (isset($config['id']) && $config['id'] === $id) {
                $bot = $config;
                $bot['market'] = $pair;
                return $bot;
            }
        }
        
        return null;
    }
    
    /**
     * Adding a new bot
     */
    public static function addBot(array $botData): ?array
    {
        self::loadConfig();
        
        // Check if a bot with this pair already exists
        $pair = $botData['market'];
        if (isset(self::$config[$pair])) {
            return null;
        }
        
        // Generate a new ID
        $maxId = 0;
        foreach (self::$config as $config) {
            if (isset($config['id']) && $config['id'] > $maxId) {
                $maxId = $config['id'];
            }
        }
        $newId = $maxId + 1;
        
        // Create a configuration for the new bot
        self::$config[$pair] = [
            'id' => $newId,
            'exchange' => $botData['exchange'] ?? 'kraken',
            // 'min_orders' => $botData['min_orders'] ?? 15,
            // 'max_orders' => $botData['max_orders'] ?? 17,
            'price_deviation_percent' => $botData['price_deviation_percent'],
            'frequency_from' => $botData['settings']['frequency_from'],
            'frequency_to' => $botData['settings']['frequency_to'],
            'bot_balance' => $botData['settings']['bot_balance'],
            'isActive' => $botData['isActive'] ?? true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'trade_amount_min' => $botData['settings']['trade_amount_min'],
            'trade_amount_max' => $botData['settings']['trade_amount_max'],
            'min_orders' => $botData['settings']['min_orders'],
            'max_orders' => $botData['settings']['max_orders'],
            'market_gap' => $botData['settings']['market_gap'],
            'price_factor' => $botData['settings']['price_factor'],
            'market_maker_order_probability' => $botData['settings']['market_maker_order_probability'],
        ];
        


        // Save the configuration
        self::saveConfig();
        
        // Return the data of the new bot
        $bot = self::$config[$pair];
        $bot['market'] = $pair;
        $bot['status'] = 'active';
        return $bot;
    }
    
    /**
     * Updating a bot
     */
    public static function updateBot(int $id, array $botData): ?array
    {
        self::loadConfig();
        
        // Search for a bot by ID
        $foundPair = null;
        foreach (self::$config as $pair => $config) {
            if (isset($config['id']) && $config['id'] === $id) {
                $foundPair = $pair;
                break;
            }
        }
        
        if ($foundPair === null) {
            return null;
        }
        
        // Check if the market has changed
        $newPair = $botData['market'] ?? $foundPair;
        
        if ($newPair !== $foundPair) {
            // If the market has changed, check if the new market already exists
            if (isset(self::$config[$newPair])) {
                return null;
            }
            
            // Copy the configuration
            $config = self::$config[$foundPair];
            
            // Update the configuration
            foreach ($botData as $key => $value) {
                if ($key !== 'id') { // Don't change the ID
                    $config[$key] = $value;
                }
            }
            
            // Make sure the frequency is specified in seconds
            if (isset($botData['frequency_from']) && isset($botData['frequency_to'])) {
                $config['frequency_from'] = (int)$botData['frequency_from'];
                $config['frequency_to'] = (int)$botData['frequency_to'];
                
                // Make sure the value is not less than 1 second
                if ($config['frequency_from'] < 1) {
                    $config['frequency_from'] = 1;
                }
                
                if ($config['frequency_to'] < 1) {
                    $config['frequency_to'] = 1;
                }
            }
            
            // Set the status
            if (isset($botData['isActive'])) {
                $config['isActive'] = filter_var($botData['isActive'], FILTER_VALIDATE_BOOLEAN);
            }
            
            // Remove the old configuration and add the new one
            unset(self::$config[$foundPair]);
            self::$config[$newPair] = $config;
            
            // Save the configuration
            self::saveConfig();
            
            // Return the full bot data
            $config['market'] = $newPair;
            $config['status'] = $config['isActive'] ? 'active' : 'disabled';
            return $config;
        } else {
            // Remove the market field if it exists
            unset($botData['market']);
            
            // Set the status
            if (isset($botData['isActive'])) {
                $botData['isActive'] = filter_var($botData['isActive'], FILTER_VALIDATE_BOOLEAN);
            }
            
            // Make sure the frequency is specified in seconds
            if (isset($botData['frequency_from']) && isset($botData['frequency_to'])) {
                $botData['frequency_from'] = (int)$botData['frequency_from'];
                $botData['frequency_to'] = (int)$botData['frequency_to'];
                
                // Make sure the value is not less than 1 second
                if ($botData['frequency_from'] < 1) {
                    $botData['frequency_from'] = 1;
                }
                
                if ($botData['frequency_to'] < 1) {
                    $botData['frequency_to'] = 1;
                }
            }
            
            // Оновлюємо налаштування, якщо вони є
            if (isset($botData['settings']) && is_array($botData['settings'])) {
                foreach ($botData['settings'] as $key => $value) {
                    self::$config[$foundPair]['settings'][$key] = $value;
                    
                    // Також оновлюємо відповідні поля в кореневому об'єкті для сумісності
                    if ($key === 'trade_amount_min' || $key === 'trade_amount_max' || 
                        $key === 'frequency_from' || $key === 'frequency_to') {
                        self::$config[$foundPair][$key] = $value;
                    } else if ($key === 'price_factor') {
                        self::$config[$foundPair]['price_deviation_percent'] = $value;
                    } else if ($key === 'market_gap') {
                        self::$config[$foundPair]['market_gap'] = $value;
                    } else if ($key === 'min_orders' || $key === 'max_orders') {
                        self::$config[$foundPair][$key] = $value;
                    } else if ($key === 'market_maker_order_probability') {
                        self::$config[$foundPair]['market_maker_order_probability'] = $value;
                    }
                }
                
                // Видаляємо налаштування з botData, оскільки вони вже оброблені
                unset($botData['settings']);
            }
            
            // Update the configuration
            foreach ($botData as $key => $value) {
                self::$config[$foundPair][$key] = $value;
            }
            
            // Save the configuration
            self::saveConfig();
            
            // Return the full bot data
            $bot = self::$config[$foundPair];
            $bot['market'] = $foundPair;
            $bot['status'] = $bot['isActive'] ? 'active' : 'disabled';
            return $bot;
        }
    }
    
    /**
     * Deleting a bot
     */
    public static function deleteBot(int $id): bool
    {
        self::loadConfig();
        
        // Search for a bot by ID
        $foundPair = null;
        foreach (self::$config as $pair => $config) {
            if (isset($config['id']) && $config['id'] === $id) {
                $foundPair = $pair;
                break;
            }
        }
        
        if ($foundPair === null) {
            return false;
        }
        
        // Delete the bot
        unset(self::$config[$foundPair]);
        
        // Save the configuration
        self::saveConfig();
        
        return true;
    }
    
    /**
     * Activation of a pair in the configuration
     */
    public static function enablePair(string $pair): void
    {
        self::loadConfig();
        
        if (isset(self::$config[$pair])) {
            self::$config[$pair]['isActive'] = true;
            
            // Save the updated configuration
            self::saveConfig();
        }
    }
    
    /**
     * Disabling a pair in the configuration
     */
    public static function disablePair(string $pair): void
    {
        self::loadConfig();
        
        if (isset(self::$config[$pair])) {
            self::$config[$pair]['isActive'] = false;
            
            // Save the updated configuration
            self::saveConfig();
        }
    }

    /**
     * Getting a list of all pairs
     */
    public static function getAllPairs(): array
    {
        self::loadConfig();
        
        // Return the keys as a list of pairs
        return array_keys(self::$config);
    }

    /**
     * Activation of a bot
     */
    public static function enableBot(string $pair): void
    {
        self::loadConfig();
        
        if (isset(self::$config[$pair])) {
            self::$config[$pair]['isActive'] = true;
            self::$config[$pair]['updated_at'] = date('Y-m-d H:i:s');
            
            // Save the updated configuration
            self::saveConfig();
        }
    }
    
    /**
     * Deactivation of a bot
     */
    public static function disableBot(string $pair): void
    {
        self::loadConfig();
        
        if (isset(self::$config[$pair])) {
            self::$config[$pair]['isActive'] = false;
            self::$config[$pair]['updated_at'] = date('Y-m-d H:i:s');
            
            // Save the updated configuration
            self::saveConfig();
        }
    }
}

// Global configuration for the TradeServer
$config = [
    'trade_server_url' => Config::getTradeServerUrl(),
    'bot_user_id' => Config::BOT_ID
]; 