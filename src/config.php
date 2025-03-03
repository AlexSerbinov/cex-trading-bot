<?php

declare(strict_types=1);

/**
 * Configuration constants for the TradingBot.
 */
class Config
{
    // public const TRADE_SERVER_URL = 'http://195.7.7.93:18080'; // - 93 dev
    public const TRADE_SERVER_URL = 'http://164.68.117.90:18080'; // - 90 demo
    public const BOT_USER_ID = 5;
    public const TAKER_FEE = '0.07';
    public const MAKER_FEE = '0.02';
    public const ORDER_SOURCE = 'bot order';
    public const MARKET_TRADE_SOURCE = 'bot trade';
    public const MARKET_MAKER_ORDER_PROBABILITY = 0.99; // Configurable probability for market maker orders (0.0 to 1.0)

    // Delay constants (in milliseconds)
    public const DELAY_RUN_MIN = 1000000; // 1 секунда
    public const DELAY_RUN_MAX = 5000000; // 5 секунд
    public const DELAY_ORDER_MIN = 500000; // 0.5 секунди
    public const DELAY_ORDER_MAX = 2000000; // 2 секунди
    public const DELAY_CLEAR_MIN = 10; // 10 ms
    public const DELAY_CLEAR_MAX = 25; // 25 ms
    public const DELAY_INIT_MIN = 15; // 15 ms
    public const DELAY_INIT_MAX = 50; // 50 ms
    public const DELAY_MAINTAIN_MIN = 100; // 100 ms
    public const DELAY_MAINTAIN_MAX = 200; // 200 ms

    // Шлях до файлу динамічної конфігурації
    private static string $dynamicConfigFile = __DIR__ . '/data/dynamic_config.json';
    
    // Кеш динамічної конфігурації
    private static ?array $dynamicConfig = null;
    
    /**
     * Завантаження динамічної конфігурації
     */
    private static function loadDynamicConfig(): void
    {
        if (self::$dynamicConfig === null) {
            if (file_exists(self::$dynamicConfigFile)) {
                self::$dynamicConfig = json_decode(file_get_contents(self::$dynamicConfigFile), true) ?: [];
            } else {
                self::$dynamicConfig = [];
            }
        }
    }
    
    /**
     * Отримання списку активних пар
     */
    public static function getEnabledPairs(): array
    {
        self::loadDynamicConfig();
        
        $enabledPairs = [];
        foreach (self::$dynamicConfig as $pair => $config) {
            if ($config['enabled']) {
                $enabledPairs[] = $pair;
            }
        }
        
        // Якщо немає активних пар у динамічній конфігурації, використовуємо статичні
        if (empty($enabledPairs)) {
            $enabledPairs = ['ETH_BTC', 'LTC_USDT', 'ETH_USDT', 'LTC_ETH'];
        }
        
        return $enabledPairs;
    }
    
    /**
     * Отримання конфігурації для пари
     */
    public static function getPairConfig(string $pair): array
    {
        self::loadDynamicConfig();
        
        // Якщо є динамічна конфігурація для пари, використовуємо її
        if (isset(self::$dynamicConfig[$pair])) {
            return self::$dynamicConfig[$pair];
        }
        
        // Інакше повертаємо статичну конфігурацію
        switch ($pair) {
            case 'ETH_BTC':
                return [
                    'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=ETHBTC',
                    'bot_balance' => 10,
                    'min_orders' => 15,
                    'max_orders' => 17,
                    'price_deviation_percent' => 5,
                    'enabled' => true,
                ];
            case 'LTC_USDT':
                return [
                    'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=LTCUSDT',
                    'bot_balance' => 10,
                    'min_orders' => 15,
                    'max_orders' => 17,
                    'price_deviation_percent' => 5,
                    'enabled' => true,
                ];
            case 'ETH_USDT':
                return [
                    'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=ETHUSDT',
                    'bot_balance' => 10,
                    'min_orders' => 15,
                    'max_orders' => 17,
                    'price_deviation_percent' => 5,
                    'enabled' => true,
                ];
            case 'LTC_ETH':
                return [
                    'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=LTCETH',
                    'bot_balance' => 10,
                    'min_orders' => 15,
                    'max_orders' => 17,
                    'price_deviation_percent' => 5,
                    'enabled' => true,
                ];
            default:
                return [
                    'external_api_url' => "https://api.kraken.com/0/public/Depth?pair=" . str_replace('_', '', $pair),
                    'bot_balance' => 10,
                    'min_orders' => 15,
                    'max_orders' => 17,
                    'price_deviation_percent' => 5,
                    'enabled' => true,
                ];
        }
    }
    
    /**
     * Оновлення динамічної конфігурації
     */
    public static function updateDynamicConfig(string $pair, array $config): void
    {
        self::loadDynamicConfig();
        
        // Оновлюємо конфігурацію для пари
        self::$dynamicConfig[$pair] = $config;
        
        // Зберігаємо оновлену конфігурацію
        $dir = dirname(self::$dynamicConfigFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(self::$dynamicConfigFile, json_encode(self::$dynamicConfig, JSON_PRETTY_PRINT));
    }
    
    /**
     * Вимкнення пари в конфігурації
     */
    public static function disablePair(string $pair): void
    {
        self::loadDynamicConfig();
        
        if (isset(self::$dynamicConfig[$pair])) {
            self::$dynamicConfig[$pair]['enabled'] = false;
            
            // Зберігаємо оновлену конфігурацію
            file_put_contents(self::$dynamicConfigFile, json_encode(self::$dynamicConfig, JSON_PRETTY_PRINT));
        }
    }
}
