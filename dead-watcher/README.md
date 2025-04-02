# Dead Watcher

Dead Watcher відстежує сигнали від торгових ботів і видаляє ордери, якщо сигнали не надходять протягом заданого часу.

## Запуск
1. `cd dead-watcher`
2. `composer install`
3. Для dev: `docker-compose up -d dead-watcher-dev`
4. Для demo: `docker-compose up -d dead-watcher-demo`

## Ендпоінт
- POST `/dead-watcher/heartbeat`
  - Тіло: `{ "pair": "ETH_BTC", "bot_id": 5, "timestamp": 1743566264 }`