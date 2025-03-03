# Order Book Console Tool

This is a convenient PHP-based tool for testing and monitoring order books from a trading exchange directly in the console. It allows you to observe real-time order book data for specific trading pairs, making it ideal for developers and traders who want to track market depth without relying on a frontend interface.

## Features
- **Multiple Pair Monitoring**: Run multiple instances of the script in separate terminal windows to monitor different trading pairs simultaneously (e.g., `LTC_USDT`, `BTC_USDT`).
- **Customizable Refresh Rate**: Updates the order book every 500ms by default, which is faster than the typical frontend refresh rate (until WebSocket support is implemented).
- **Colored Output**: Uses ANSI colors to distinguish bids (green), asks (red), and the last price (yellow), with bold headers for clarity.
- **Numbered Entries**: Each order book entry is numbered for easy reference.
- **Compact Layout**: Reduced column spacing for a cleaner, more compact display.

## Prerequisites
- PHP 8.0+
- Composer (for dependency management)
- Required PHP extensions: `curl`, `json`
- Required Composer packages:
  - `guzzlehttp/guzzle` (HTTP client for API requests)
  - `symfony/console` (for console output handling)

Install dependencies with:
```bash
composer require guzzlehttp/guzzle symfony/console