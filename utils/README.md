# Асинхронні утиліти для роботи з ордерами

## Встановлення залежностей

Для роботи скриптів потрібно встановити ReactPHP:

```bash
composer require react/http
composer require react/event-loop
composer require react/promise
```

## Використання

### Асинхронне скасування ордерів

```bash
php AsyncOrderCancellation.php LTC_USDT
```

### Асинхронне створення ордерів

```bash
php AsyncOrderCreation.php LTC_USDT 10 65.5 0.02 0.1 0.5
```

Де параметри:
- `LTC_USDT` - торгова пара
- `10` - кількість ордерів
- `65.5` - базова ціна
- `0.02` - відхилення ціни (2%)
- `0.1` - мінімальний об'єм
- `0.5` - максимальний об'єм

## Особливості

- Скрипти використовують асинхронні HTTP-запити для паралельного виконання операцій
- Всі запити виконуються одночасно, що значно пришвидшує роботу
- Підтримується обробка помилок та логування результатів
- Використовується Event Loop для керування асинхронними операціями

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