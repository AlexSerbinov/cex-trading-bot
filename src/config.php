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
    public const DELAY_RUN_MIN = 100; // 0.1 second
    public const DELAY_RUN_MAX = 500; // 0.5 seconds
    public const DELAY_CLEAR_MIN = 10; // 10 ms
    public const DELAY_CLEAR_MAX = 25; // 25 ms
    public const DELAY_INIT_MIN = 15; // 15 ms
    public const DELAY_INIT_MAX = 50; // 50 ms
    public const DELAY_MAINTAIN_MIN = 100; // 100 ms
    public const DELAY_MAINTAIN_MAX = 200; // 200 ms

    // Конфігурація для кожної пари
    public const TRADING_PAIRS = [
        'LTC_USDT' => [
            'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=LTCUSDT',
            'bot_balance' => 50.0,
            'min_orders' => 15,
            'max_orders' => 17,
            'price_deviation_percent' => 5.0, // Відхилення ціни в %
            'enabled' => true,
        ],
        'ETH_USDT' => [
            'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=ETHUSDT',
            'bot_balance' => 2.0,
            'min_orders' => 15,
            'max_orders' => 17,
            'price_deviation_percent' => 5.0,
            'enabled' => true,
        ],
        'BTC_USDT' => [
            'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=BTCUSDT',
            'bot_balance' => 0.5,
            'min_orders' => 15,
            'max_orders' => 17,
            'price_deviation_percent' => 5.0,
            'enabled' => false
        ],
        'LTC_ETH' => [
            'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=LTCETH',
            'bot_balance' => 0.5,
            'min_orders' => 15,
            'max_orders' => 17,
            'price_deviation_percent' => 5.0,
            'enabled' => true,
        ],
        // add other pairs if needed
    ];

    /**
     * Get the configuration for a specific pair
     *
     * @param string $pair The trading pair
     * @return array The configuration for the pair
     */
    public static function getPairConfig(string $pair): array
    {
        if (!isset(self::TRADING_PAIRS[$pair])) {
            throw new RuntimeException("Configuration for pair {$pair} not found");
        }

        return self::TRADING_PAIRS[$pair];
    }

    /**
     * Get a list of active pairs
     *
     * @return array List of active pairs
     */
    public static function getEnabledPairs(): array
    {
        return array_keys(
            array_filter(self::TRADING_PAIRS, function ($config) {
                return $config['enabled'] === true;
            }),
        );
    }
}
