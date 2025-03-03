# Multi-Pair Trading Bot for Cryptocurrency Exchange

This trading bot simulates trading activity on a cryptocurrency exchange by creating and managing orders for multiple trading pairs simultaneously. It's designed to create market liquidity by maintaining a specified number of buy and sell orders and periodically executing trades across various trading pairs.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [File Structure](#file-structure)
- [Configuration](#configuration)
- [Installation](#installation)
- [Usage](#usage)
- [Managing Multiple Pairs](#managing-multiple-pairs)
- [Monitoring](#monitoring)
- [Troubleshooting](#troubleshooting)
- [Future Improvements](#future-improvements)

## Overview

The trading bot connects to your exchange API and simulates trading activity by:

- Maintaining a specified number of buy and sell orders for each pair
- Periodically placing and canceling orders
- Executing market trades to simulate real trading activity
- Supporting multiple trading pairs simultaneously with independent processes

## Features

- **Multi-pair support**: Run dozens of trading pairs simultaneously
- **Independent processes**: Each pair runs in its own process for stability
- **Configurable parameters per pair**: Customize settings for each trading pair
- **Automatic order book maintenance**: Keeps order books within specified limits
- **Random trading patterns**: Simulates real market activity with varied behaviors
- **Graceful error handling**: Automatic retries and error recovery
- **Flexible deployment**: Run all pairs or select specific pairs as needed

## File Structure

- **config.php**: Configuration settings for the bot and trading pairs
- **tradingBotMain.php**: Main bot logic for order management and trading
- **ApiClient.php**: Handles API communication with the exchange
- **Logger.php**: Logging functionality
- **MockOrderBook.php**: Generates mock order book data for testing
- **TradingBotManager.php**: Manages multiple bot instances (one per pair)
- **bot.php**: Command-line script to run a single bot for a specific pair
- **manager.php**: Script to launch multiple bots for all enabled pairs
- **stop.php**: Script to stop all running bots

## Configuration

The bot is configured through the `config.php` file. Key configuration options include:

### Trading Pair Configuration

Each trading pair has its own configuration:

```php
public const TRADING_PAIRS = [
    'LTC_USDT' => [
        'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=LTCUSDT',
        'bot_balance' => 50.0,
        'min_orders' => 15,
        'max_orders' => 17,
        'price_deviation_percent' => 5.0,
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
        'enabled' => false,  // Disabled pair
    ],
    // Add more pairs as needed
];
```

Parameters for each pair:

- **external_api_url**: URL to fetch external order book data
- **bot_balance**: Available balance for the bot to use
- **min_orders**: Minimum number of orders to maintain
- **max_orders**: Maximum number of orders to maintain
- **price_deviation_percent**: Maximum price deviation from market price
- **enabled**: Whether this pair is active (true/false)

## Installation

### Prerequisites

- PHP 7.4 or higher
- PHP cURL extension
- Access to your exchange API

### Setup

1. Clone the repository:

    ```bash
    git clone https://github.com/yourusername/trading-bot.git
    cd trading-bot
    ```

2. Configure your trading pairs in `config.php`

3. Create a logs directory:
    ```bash
    mkdir -p logs
    ```

## Usage

### Running a Single Pair

To run the bot for a single trading pair:

```bash
php bot.php LTC_USDT
```

Replace `LTC_USDT` with the desired trading pair.

### Running All Enabled Pairs

To run the bot for all enabled pairs:

```bash
php manager.php
```

This will start a separate process for each enabled pair defined in the configuration.

### Stopping All Bots

To stop all running bots:

```bash
php stop.php
```

## Managing Multiple Pairs

The bot is designed to handle multiple trading pairs simultaneously, with each pair running in its own process.

### How Multi-Pair Support Works

1. **Independent Configuration**: Each pair has its own configuration in `config.php`
2. **Process Isolation**: Each pair runs in a separate PHP process
3. **Resource Management**: Each bot instance manages its own resources
4. **Selective Enabling**: Pairs can be enabled/disabled via configuration

### Adding a New Pair

To add a new trading pair:

1. Add the pair configuration to `config.php`:

    ```php
    'NEW_PAIR' => [
        'external_api_url' => 'https://api.kraken.com/0/public/Depth?pair=NEWPAIR',
        'bot_balance' => 10.0,
        'min_orders' => 15,
        'max_orders' => 17,
        'price_deviation_percent' => 5.0,
        'enabled' => true,
    ],
    ```

2. Run the manager to start all enabled pairs including the new one:
    ```bash
    php manager.php
    ```

### Managing Specific Pairs

You can selectively run specific pairs:

```bash
# Run just two specific pairs
php bot.php LTC_USDT > logs/ltc_usdt.log 2>&1 &
php bot.php ETH_USDT > logs/eth_usdt.log 2>&1 &
```

### Disabling a Pair

To disable a pair without removing it from configuration:

1. Set `'enabled' => false` in the pair's configuration
2. Restart the manager or stop the specific pair process

## Monitoring

The bot logs all activities to the console. For production use, consider redirecting output to log files:

```bash
php bot.php LTC_USDT > logs/ltc_usdt.log 2>&1 &
```

You can monitor the logs in real-time using:

```bash
tail -f logs/ltc_usdt.log
```

For multiple pairs, you can use:

```bash
tail -f logs/*.log
```

## Troubleshooting

### Common Issues

1. **API Connection Errors**

    - Check your network connection
    - Verify the API URL in the configuration
    - Ensure your API credentials are correct

2. **Insufficient Balance**

    - Adjust the `bot_balance` parameter in the configuration
    - Check your actual balance on the exchange

3. **High CPU Usage**

    - Increase the delay parameters in the configuration
    - Reduce the number of active pairs
    - Consider distributing pairs across multiple servers

4. **Process Management**
    - If processes don't stop properly, use `ps aux | grep php` to find them
    - Kill manually with `kill -9 PID`

### Debugging

For debugging, you can enable mock data mode by defining the constant:

```php
define('USE_MOCK_DATA', true);
```

This will use the MockOrderBook class instead of making actual API calls.

## Future Improvements

### API Management Interface

A planned enhancement is to add a REST API for managing the trading bots:

- **Features**:

    - Start/stop individual pairs via API
    - Modify pair configurations without restarting
    - Get real-time status of all running bots
    - View performance metrics and logs
    - Adjust trading parameters on-the-fly

- **Implementation Plan**:
    - Create a lightweight API server using PHP or Node.js
    - Implement authentication for secure access
    - Develop endpoints for all management functions
    - Add a web dashboard for visual management

### Multi-Exchange Support

Another major planned improvement is supporting multiple exchanges:

- **Features**:

    - Connect to multiple exchanges simultaneously
    - Configure different strategies per exchange
    - Cross-exchange arbitrage capabilities
    - Unified configuration interface for all exchanges

- **Implementation Plan**:
    - Create abstraction layer for exchange APIs
    - Implement adapters for major exchanges (Binance, Coinbase, etc.)
    - Develop exchange-specific configuration options
    - Add support for exchange-specific features

### Additional Planned Improvements

- **Advanced Trading Strategies**: Implement more sophisticated trading algorithms
- **Performance Optimization**: Reduce resource usage for high-volume pairs
- **Distributed Architecture**: Support for running bots across multiple servers
- **Data Analysis Tools**: Add tools for analyzing bot performance and market data
- **Alerting System**: Implement notifications for critical events and anomalies
