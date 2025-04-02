# Dead Watcher

Dead Watcher відстежує сигнали від торгових ботів і видаляє ордери, якщо сигнали не надходять протягом заданого часу.

## Вимоги
- PHP 8.1+
- Composer
- Docker (для запуску через Docker)

## Локальний запуск (Dev, порт 5503)
1. `cd dead-watcher`
2. `composer install`
3. `php src/DeadWatcher.php`

## Запуск через Docker
1. `cd dead-watcher`
2. `composer install`
3. Для dev (порт 5503): `docker-compose up -d bot-dead-watcher-dev`
4. Для demo (порт 6503): `docker-compose up -d bot-dead-watcher-demo`

## Ендпоінт
- **POST** `/dead-watcher/heartbeat`
  - Тіло: `{ "pair": "ETH_BTC", "bot_id": 5, "timestamp": 1743566264 }`

## Логи
- Логи виводяться в консоль і записуються в `logs/dead-watcher.log`.