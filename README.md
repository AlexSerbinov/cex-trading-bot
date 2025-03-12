# Cryptocurrency Market Making Bot

A sophisticated market making bot designed to create and maintain liquidity on cryptocurrency exchanges by simulating realistic trading activity. This bot copies order books from external exchanges, applies customizable parameters, and places orders on your exchange to create natural market depth and trading patterns.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Architecture](#architecture)
- [Docker Infrastructure](#docker-infrastructure)
- [Installation](#installation)
- [Configuration](#configuration)
- [Bot Parameters](#bot-parameters)
- [API Reference](#api-reference)
- [Web Interface](#web-interface)
- [Monitoring](#monitoring)
- [Troubleshooting](#troubleshooting)

## Overview

This market making bot is designed to:

1. Copy order books from external exchanges (like Binance or Kraken)
2. Apply customizable parameters to adjust order placement
3. Create realistic market depth by maintaining buy and sell orders
4. Execute trades periodically to simulate natural market activity
5. Support multiple trading pairs simultaneously
6. Provide a user-friendly web interface for configuration and monitoring

The bot runs as a background process for each trading pair, continuously maintaining orders and executing trades based on the configured parameters.

## Features

- **Multi-pair support**: Run independent bots for multiple trading pairs
- **External order book mirroring**: Copy and adjust real market data
- **Customizable trading parameters**: Fine-tune each bot's behavior
- **Process management**: Start, stop, and monitor bots through API or web interface
- **Realistic trading simulation**: Create natural-looking market activity
- **Persistent configuration**: Store settings in JSON configuration files
- **RESTful API**: Control bots programmatically
- **Web dashboard**: User-friendly interface for management

## Architecture

The system consists of several components:

1. **Core Trading Logic** (`src/core/`):

    - `TradingBot.php`: Main bot logic for order management and trading
    - `BotProcess.php`: Process management for running bots in the background
    - `ApiClient.php`: Communication with the trading server
    - `ExchangeManager.php`: Fetching data from external exchanges
    - `Logger.php`: Logging functionality

2. **API Layer** (`src/api/`):

    - `BotManager.php`: High-level bot management
    - `BotStorage.php`: Configuration storage and retrieval
    - `index.php`: RESTful API endpoints

3. **Web Interface** (`frontend/`):

    - HTML/CSS/JavaScript dashboard for bot management
    - Real-time status monitoring
    - Configuration interface

4. **Configuration** (`config/`, `data/`):
    - `config.php`: System-wide configuration
    - `bots_config.json`: Individual bot configurations

## Docker Infrastructure

The system can be deployed using Docker containers, with two separate environments available: Development (Dev) and Demonstration (Demo).

### Docker Environments

1. **Development Environment (Dev)**:

    - For development and testing purposes
    - Uses the production trading server URL (http://195.7.7.93:18080)
    - Configured in `docker-compose-dev.yml`

2. **Demonstration Environment (Demo)**:
    - For showcasing the system to potential clients
    - Uses a demo trading server URL (http://164.68.117.90:18080)
    - Configured in `docker-compose-demo.yml`

### Services

Each environment contains the following services:

#### Backend Service

- **Function**: Core trading logic, bot process management
- **Container Name**: `trading-bot-backend-dev` or `trading-bot-backend-demo`
- **Technology**: PHP CLI service running `TradingBotManager.php`
- **Volumes**: Mounts configuration files and data directories
- **Paths**: Manages files in `data/pids/` for process control

#### API Service

- **Function**: RESTful API for bot management
- **Container Name**: `trading-bot-api-dev` or `trading-bot-api-demo`
- **Technology**: PHP Apache server
- **Dev Port**: http://localhost:8081/
- **Demo Port**: http://localhost:8082/
- **Volumes**: Mounts configuration, data, and source code

#### Frontend Service

- **Function**: User interface for system management
- **Container Name**: `trading-bot-frontend-dev` or `trading-bot-frontend-demo`
- **Technology**: Nginx web server hosting the frontend
- **Dev Port**: http://localhost:8001/
- **Demo Port**: http://localhost:8002/
- **Proxies to API**: Routes `/api` requests to the appropriate API service

### Network Architecture

- Each environment uses its own isolated Docker network
- Frontend communicates with API through internal Docker network
- API and Backend services share volume mounts for configuration and data

### Running Docker Environments

To run both environments simultaneously:

1. Give execution permissions to the setup script:

    ```bash
    chmod +x scripts/run_clean.sh
    ```

2. Start both environments with:

    ```bash
    # Stop any existing Docker containers
    docker-compose down && docker-compose -f docker-compose-dev.yml down && docker-compose -f docker-compose-demo.yml down

    # Start the development environment
    docker-compose -f docker-compose-dev.yml build --no-cache && docker-compose -f docker-compose-dev.yml up -d

    # Start the demonstration environment
    docker-compose -f docker-compose-demo.yml build --no-cache && docker-compose -f docker-compose-demo.yml up -d
    ```

3. Access the environments:

    - Development UI: http://localhost:8001/
    - Development API: http://localhost:8081/
    - Demonstration UI: http://localhost:8002/
    - Demonstration API: http://localhost:8082/

4. View container logs with:
    ```bash
    docker logs trading-bot-backend-dev
    docker logs trading-bot-api-dev
    docker logs trading-bot-frontend-dev
    ```
    (Replace `-dev` with `-demo` for the demonstration environment.)

    Або використовуйте спеціальний скрипт для моніторингу логів:
    ```bash
    # Надайте права на виконання
    chmod +x ./scripts/docker_logs.sh
    
    # Моніторинг логів усіх сервісів у Dev середовищі
    ./scripts/docker_logs.sh
    
    # Моніторинг логів Backend у Demo середовищі
    ./scripts/docker_logs.sh demo backend
    
    # Моніторинг логів Frontend у Dev середовищі
    ./scripts/docker_logs.sh dev frontend
    ```

## Installation

### Prerequisites

- PHP 7.4 or higher
- Web server (Apache or Nginx)
- Access to a trading server API

### Setup

1. Clone the repository:

    ```bash
    git clone https://github.com/yourusername/trading-bot.git
    cd trading-bot
    ```

2. Configure your web server to point to the project directory

3. Set up the data directory:

    ```bash
    mkdir -p data/pids
    chmod -R 755 data
    ```

4. Update the configuration in `config/config.php`:

    - Set `TRADE_SERVER_URL` to your trading server endpoint
    - Configure other system parameters as needed

5. Access the web interface at `http://your-server/`

## Configuration

The bot can be configured in two ways:

1. **Via Web Interface**: The easiest method for most users
2. **Via API**: For programmatic control
3. **Direct Configuration File**: For advanced users

### Configuration File

The main configuration file is located at `config/bots_config.json`. This file stores settings for all bots in the following format:

```json
{
    "BTC_USDT": {
        "id": 1,
        "exchange": "binance",
        "min_orders": 2,
        "max_orders": 4,
        "price_deviation_percent": 0.5,
        "market_gap": 0.05,
        "frequency_from": 30,
        "frequency_to": 60,
        "bot_balance": 10,
        "isActive": true,
        "created_at": "2023-05-15 10:00:00",
        "updated_at": "2023-05-15 10:00:00",
        "trade_amount_min": 0.1,
        "trade_amount_max": 1.0
    }
}
```

**Note**: When you create or update a bot through the API or web interface, the configuration file is automatically updated. Manual changes to this file may be overwritten.

## Bot Parameters

Each bot has the following configurable parameters:

| Parameter            | Description                                                 | Default | Impact                                                                     |
| -------------------- | ----------------------------------------------------------- | ------- | -------------------------------------------------------------------------- |
| **market**           | Trading pair in format BASE_QUOTE (e.g., BTC_USDT)          | -       | Defines which cryptocurrencies the bot will trade                          |
| **exchange**         | External exchange to copy order book from (binance, kraken) | binance | Determines the source of market data                                       |
| **trade_amount_min** | Minimum amount of base currency for each trade              | 0.1     | Lower values create more frequent but smaller trades                       |
| **trade_amount_max** | Maximum amount of base currency for each trade              | 1.0     | Higher values allow for larger trades and more significant price movements |
| **frequency_from**   | Minimum time interval between bot actions (seconds)         | 30      | Lower values increase trading frequency and liquidity                      |
| **frequency_to**     | Maximum time interval between bot actions (seconds)         | 60      | Higher values make trading patterns more unpredictable                     |
| **price_factor**     | Maximum percentage deviation from market price              | 0.5     | Higher values create wider price ranges for orders                         |
| **market_gap**       | Percentage step from best price on external exchange        | 0.05    | Controls the spread between buy and sell orders                            |

### Parameter Details

#### Market Gap

The Market Gap parameter is particularly important as it controls the gap between the best buy and sell orders, thereby regulating the spread. A higher Market Gap creates a larger spread between buy and sell prices, which acts as a protective mechanism to control liquidity and reduce risks.

For example, with a 1% Market Gap:

- If BTC is trading at $100,000 on the external exchange
- With best buy at $100,002 and best sell at $99,999
- Your bot will place orders at $99,002 for buying and $100,999 for selling
- This creates a wider spread, reducing the risk of immediate fills

#### Price Factor

The Price Factor determines how far from the current market price the bot will place its orders. Higher values create a wider price range for orders, simulating more volatile market conditions. Lower values keep orders closer to the current market price, creating tighter spreads.

## How the Bot Works

1. **Initialization**:

    - The bot loads its configuration from the storage
    - It connects to the trading server and external exchange

2. **Order Book Copying**:

    - The bot retrieves the order book from the selected external exchange
    - It takes a configurable number of the closest buy and sell orders

3. **Order Adjustment**:

    - The bot applies the Market Gap parameter to create a spread
    - It applies the Price Factor to determine the range of order placement
    - It randomly varies order sizes within the configured min/max range

4. **Order Placement**:

    - The bot places buy and sell orders on your exchange
    - Orders are distributed to create natural-looking market depth

5. **Maintenance Loop**:

    - The bot periodically checks the status of its orders
    - It cancels and replaces orders as needed
    - It executes trades at random intervals to simulate activity
    - It waits for a random time period (between frequency_from and frequency_to)
    - The loop continues until the bot is stopped

6. **Process Management**:
    - Each bot runs as a separate background process
    - The BotProcess class manages starting, stopping, and monitoring these processes

## API Reference

The bot provides a RESTful API for management:

### Bot Management

- `GET /api/bots` - List all bots
- `GET /api/bots/{id}` - Get bot details
- `POST /api/bots` - Create a new bot
- `PUT /api/bots/{id}` - Update bot configuration
- `DELETE /api/bots/{id}` - Delete a bot

### Bot Control

- `PUT /api/bots/{id}/enable` - Start a bot
- `PUT /api/bots/{id}/disable` - Stop a bot
- `PUT /api/bots/{id}/update-balance` - Update bot's balance

### System Information

- `GET /api/exchanges` - List supported exchanges
- `GET /api/pairs` - List available trading pairs
- `GET /api/logs` - Get system logs

## Web Interface

The web interface provides a user-friendly way to manage bots:

### Main Dashboard

The main dashboard displays a list of all bots with key information:

- ID and trading pair
- Exchange
- Status (active/inactive)
- Min/Max trade amounts
- Frequency range
- Price deviation and market gap settings

### Bot Creation

To create a new bot:

1. Click the "Create Bot" button
2. Fill in the required parameters
3. Click "Save"

### Bot Details

Click on a bot in the list to view detailed information:

- All configuration parameters
- Creation and update timestamps
- Controls for enabling/disabling the bot
- Edit and delete options

## Monitoring

The bot provides several ways to monitor its activity:

1. **Web Interface**: View bot status and configuration
2. **Logs**: Access detailed logs via API or file system
3. **Process Status**: Check running processes with system tools

### Перегляд логів

Система логування налаштована таким чином, що логи одночасно записуються у файл і виводяться в консоль:

#### Локальний запуск
При локальному запуску через скрипти `run_clean.sh` або `run_local.sh` логи автоматично виводяться в консоль.

#### Docker-контейнери
Для перегляду логів у Docker-контейнерах використовуйте:

```bash
# Стандартна команда Docker
docker logs -f trading-bot-backend-dev

# Або спеціальний скрипт
./scripts/docker_logs.sh dev backend
```

#### Файли логів
Логи також зберігаються у файлах:
- Основний лог: `data/logs/bot.log`
- Лог маршрутизатора: `data/logs/router.log`

## Troubleshooting

### Common Issues

1. **Bot not starting**:

    - Check if the process is already running
    - Verify the configuration parameters
    - Check logs for errors

2. **Orders not appearing**:

    - Verify connection to the trading server
    - Check if the bot has sufficient balance
    - Ensure the trading pair exists on your exchange

3. **Unexpected behavior**:
    - Review the configuration parameters
    - Check external exchange connectivity
    - Verify system resources (memory, CPU)

### Log Analysis

Logs are stored in the `data/logs` directory and can be accessed via:

- API: `GET /api/logs`
- File system: Check the log files directly

## Advanced Usage

### Multiple Exchange Support

The bot can copy order books from different exchanges for different pairs. For example:

- BTC_USDT from Binance
- ETH_USDT from Kraken

This allows you to create diverse market conditions based on different external sources.

### Custom Trading Strategies

While the bot is primarily designed for market making, you can adjust parameters to implement different strategies:

- Tight spreads with small orders for high-liquidity pairs
- Wide spreads with larger orders for low-liquidity pairs
- Varying frequencies to match typical trading patterns for specific assets
