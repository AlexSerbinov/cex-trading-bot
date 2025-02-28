
# TradingBot README

## Overview
This is the initial version (v1.0) of the TradingBot, an automated bot written in PHP designed to simulate trades on a cryptocurrency exchange. Currently, this version operates solely with the Litecoin-USDT (LTC_USDT) trading pair, leveraging real-time data from the Kraken exchange API to manage limit and market orders, maintaining an order book of 15-17 orders, and performing random trading actions.

## Features
- **Single Pair Support**: Works with the LTC_USDT trading pair, fetching order book data from Kraken and interacting with a custom trade server.
- **Order Management**: Maintains 15-17 limit orders (bids and asks) and executes market maker orders with a configurable probability (default 70%).
- **Logging**: Provides detailed logging of all actions, including order placements, cancellations, and errors, with timestamps in the console.
- **Error Handling**: Implements retry logic for API requests to Kraken with up to 3 retries in case of failures.
- **Configurable Delays**: Uses configurable delays (in milliseconds) for various operations to simulate realistic trading behavior.

## Requirements
- PHP 8.0 or higher
- PHP CURL extension (`php-curl` module) for API requests

## Installation
1. Clone or download this repository to your local machine.
2. Ensure PHP 8+ and the CURL extension are installed:
   ```bash
   sudo apt-get install php-curl  # On Ubuntu/Debian
   sudo yum install php-curl      # On CentOS/RHEL
   ```
3. Place all PHP files (`TradingBot.php`, `ApiClient.php`, `Logger.php`, and any other required files) in the same directory.
4. Run the bot from the command line:
   ```bash
   php TradingBot.php
   ```

## Usage
The bot runs continuously, simulating trading for the LTC_USDT pair. It logs all actions to the console, including:
- Initialization of the order book
- Placement and cancellation of limit orders
- Execution of market orders (simulated)
- Errors or retries for API calls

To adjust the bot's behavior, modify the constants in the code (or future configuration files if implemented). For example, change `MARKET_MAKER_ORDER_PROBABILITY` to adjust the frequency of market orders or `DELAY_*` constants to modify operation delays.

## Current Limitations
- **Single Pair Support**: This version only supports the LTC_USDT trading pair. It does not handle multiple pairs simultaneously.
- **Single Exchange**: The bot currently works only with the Kraken exchange API and a specific trade server (`http://164.68.117.90:18080`).

## Future Enhancements
To expand the TradingBot into a more robust trading system, the following features and improvements are planned for future versions:

1. **Multiple Pair Support**: Extend the bot to handle multiple trading pairs (e.g., ETH_USDT, BTC_USDT) simultaneously, allowing parallel operation for each pair.
2. **API Development**: Develop a RESTful or WebSocket API to allow external control, monitoring, and configuration of the bot, enabling integration with other systems or user interfaces.
3. **Multi-Exchange Support**: Add support for multiple cryptocurrency exchanges (e.g., Binance, Coinbase, Bitfinex) to fetch order book data and execute trades across different platforms.
4. **Exchange Integration**: Research and integrate additional exchanges as data sources, ensuring compatibility with their APIs, authentication methods, and order types.

## Contributing
Contributions are welcome! If you'd like to contribute to this project, please:
- Fork the repository
- Create a new branch for your feature or bug fix
- Submit a pull request with your changes

## License
This project is currently unlicensed. Please contact the maintainers for licensing information or usage permissions.

## Contact
For questions or support, reach out to the project maintainers via [your contact information here, if applicable].

---

### Notes
- This README assumes the bot is run in a development or testing environment. For production use, consider adding error logging to files, implementing configuration management (e.g., via JSON or environment variables), and enhancing security.
- The bot uses simulated market orders (`placeMarketOrder` returns `true` for simulation purposes). For real trading, update this method to interact with the actual exchange API.
