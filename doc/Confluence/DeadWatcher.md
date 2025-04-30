Dead Watcher is a service designed to monitor heartbeats from trading bots and cancel their orders if no signals are received within a **60-second** time frame (configurable). This ensures that orphaned orders do not remain in the order book, protecting against potential financial losses due to market fluctuations if a bot instance fails or loses connectivity.

## Description

### Purpose

In our trading system, if a bot instance crashes, stops functioning, or loses connection to the trade server, its orders remain active in the order book. This poses a significant risk: if the price of an asset moves significantly, these orphaned orders could be executed at unfavorable prices, leading to financial losses. Dead Watcher addresses this issue by actively monitoring bot activity through heartbeats and cleaning up orders when a bot becomes unresponsive or fails to confirm its activity.

### Functionality

*   **Heartbeat Monitoring**: Dead Watcher listens for heartbeat signals sent by trading bots. It expects to receive a signal for each monitored trading pair at least once every **60 seconds** (configurable via environment variables or `config/config.php`). If a signal for a pair is not received within this timeframe, Dead Watcher assumes the corresponding bot instance might be malfunctioning and proceeds to cancel its orders for that pair. Signals are sent to the `/dead-watcher/heartbeat` endpoint(s).

*   **Dynamic Pair Registration**: Dead Watcher does not rely on a predefined list of pairs. When a heartbeat signal is received for a new pair (e.g., `BTC_ETH`), Dead Watcher dynamically registers it and begins monitoring.

*   **Asynchronous Order Cancellation**: Built on ReactPHP, Dead Watcher performs order cancellations asynchronously. If no heartbeat is received for a pair within the configured 60-second window, it initiates the cancellation process, ensuring responsiveness.

*   **Redundancy**: For increased reliability, you can deploy multiple Dead Watcher instances. The URLs for these instances should be specified in the `DEAD_WATCHER_URLS` environment variable or the `DEAD_WATCHER_URLS` constant in `config/config.php`. The trading bot will send heartbeats to all configured URLs.

### Heartbeat Signals

When the `dead_watcher` feature is enabled (via `DEAD_WATCHER_ENABLED` in `config/config.php` or environment variable), the trading bot sends a heartbeat signal **after successfully placing a limit or market order** on the trade server.

This mechanism ensures that a heartbeat is sent only when the bot confirms that an order has been accepted by the trade server. If any step in placing the order fails (e.g., loss of connection to the trade server, API error from the exchange via `ExchangeManager`, internal bot error), no heartbeat will be sent for that action. Consequently, if a bot fails to place orders for a pair for more than 60 seconds, Dead Watcher will eventually cancel any existing orders for that pair, preventing orphaned orders.

The heartbeat is sent as a POST request to each URL specified in `DEAD_WATCHER_URLS` with the following JSON payload:

```json
{
  "pair": "BTC_USDT", // The specific trading pair
  "bot_id": 5,        // The BOT_ID configured in config.php (used by Dead Watcher to identify orders)
  "timestamp": <unix_timestamp> // The current Unix timestamp
}
```

### Important Note on Configuration Timing

The Dead Watcher timeout is set to **60 seconds**. This means if a bot doesn't successfully place an order for a specific pair within 60 seconds, Dead Watcher will cancel its existing orders for that pair.

It is crucial to consider this timeout when configuring the trading bot's operational frequency in `config/bots_config.json`, specifically the `frequency_from` and `frequency_to` settings for each pair. These settings determine the delay between the bot's trading cycles (`runSingleCycle`).

**Potential Issue:** If the time between consecutive *successful order placements* for a given pair exceeds 60 seconds (due to high `frequency_to` values, market conditions preventing order placement, or other delays), Dead Watcher might prematurely cancel the bot's orders before the bot intends to update them.

**Recommendation:** Ensure that the combined logic and configured frequencies (`frequency_from`, `frequency_to`) in `config/bots_config.json` allow the bot to attempt placing orders **more frequently than every 60 seconds** for each active pair. While the bot might not place an order in every cycle, the *attempt* should occur well within the 60-second window.

Setting excessively long intervals between trading cycles (e.g., approaching or exceeding 60 seconds) is strongly discouraged. A 60-second window is generally sufficient for maintaining an active order book. For instance, updating 10-15 orders might take several seconds, but a total cycle time stretching to minutes could expose the strategy to significant risk from market volatility. If you need longer intervals, consider increasing the Dead Watcher timeout accordingly, but be aware of the associated risks of orphaned orders persisting longer. Keeping the cycle frequency reasonably short (e.g., max delay of a few seconds) is generally safer.

---

## Русская версия

Dead Watcher — это сервис, предназначенный для мониторинга сигналов (heartbeats) от торговых ботов и отмены их ордеров, если сигналы не поступают в течение **60-секундного** периода времени (настраиваемо). Это гарантирует, что осиротевшие ордера не останутся в книге ордеров, защищая от потенциальных финансовых потерь из-за колебаний рынка, если экземпляр бота выйдет из строя или потеряет соединение.

### Описание

#### Назначение

В нашей торговой системе, если экземпляр бота выходит из строя, перестает функционировать или теряет соединение с торговым сервером, его ордера остаются активными в книге ордеров. Это создает значительный риск: если цена актива значительно изменится, эти осиротевшие ордера могут быть исполнены по невыгодным ценам, что приведет к финансовым потерям. Dead Watcher решает эту проблему, активно отслеживая активность ботов с помощью сигналов и удаляя ордера, когда бот перестает отвечать или не подтверждает свою активность.

#### Функциональность

*   **Мониторинг сигналов (Heartbeat)**: Dead Watcher ожидает сигналы от торговых ботов. Он должен получать сигнал для каждой отслеживаемой торговой пары как минимум раз в **60 секунд** (настраивается через переменные окружения или в `config/config.php`). Если сигнал для пары не получен в течение этого времени, Dead Watcher предполагает, что соответствующий экземпляр бота может работать некорректно, и отменяет его ордера для этой пары. Сигналы отправляются на эндпоинт(ы) `/dead-watcher/heartbeat`.

*   **Динамическая регистрация пар**: Dead Watcher не полагается на предопределенный список пар. Когда поступает сигнал для новой пары (например, `BTC_ETH`), Dead Watcher динамически регистрирует ее и начинает мониторинг.

*   **Асинхронная отмена ордеров**: Построенный на ReactPHP, Dead Watcher выполняет отмену ордеров асинхронно. Если сигнал для пары не получен в течение настроенного 60-секундного окна, он инициирует процесс отмены, обеспечивая быстродействие.

*   **Резервирование**: Для повышения надежности можно развернуть несколько экземпляров Dead Watcher. URL-адреса этих экземпляров должны быть указаны в переменной окружения `DEAD_WATCHER_URLS` или в константе `DEAD_WATCHER_URLS` в `config/config.php`. Торговый бот будет отправлять сигналы на все настроенные URL-адреса.

### Сигналы (Heartbeat)

Когда функция `dead_watcher` включена (через `DEAD_WATCHER_ENABLED` в `config/config.php` или переменную окружения), торговый бот отправляет сигнал **после успешного размещения лимитного или рыночного ордера** на торговом сервере.

Этот механизм гарантирует, что сигнал отправляется только тогда, когда бот подтверждает, что ордер был принят торговым сервером. Если какой-либо шаг при размещении ордера завершается неудачей (например, потеря соединения с торговым сервером, ошибка API от биржи через `ExchangeManager`, внутренняя ошибка бота), сигнал для этого действия отправлен не будет. Следовательно, если бот не сможет разместить ордера для пары более 60 секунд, Dead Watcher в конечном итоге отменит все существующие ордера для этой пары, предотвращая появление осиротевших ордеров.

Сигнал отправляется как POST-запрос на каждый URL, указанный в `DEAD_WATCHER_URLS`, со следующей полезной нагрузкой JSON:

```json
{
  "pair": "BTC_USDT", // Конкретная торговая пара
  "bot_id": 5,        // BOT_ID, настроенный в config.php (используется Dead Watcher для идентификации ордеров)
  "timestamp": <unix_timestamp> // Текущая временная метка Unix
}
```

### Важное примечание о настройке времени

Тайм-аут Dead Watcher установлен на **60 секунд**. Это означает, что если бот не разместит успешно ордер для конкретной пары в течение 60 секунд, Dead Watcher отменит его существующие ордера для этой пары.

Крайне важно учитывать этот тайм-аут при настройке рабочей частоты торгового бота в `config/bots_config.json`, в частности, настроек `frequency_from` и `frequency_to` для каждой пары. Эти настройки определяют задержку между торговыми циклами бота (`runSingleCycle`).

**Потенциальная проблема:** Если время между последовательными *успешными размещениями ордеров* для данной пары превышает 60 секунд (из-за высоких значений `frequency_to`, рыночных условий, препятствующих размещению ордеров, или других задержек), Dead Watcher может преждевременно отменить ордера бота, прежде чем бот намеревается их обновить.

**Рекомендация:** Убедитесь, что общая логика и настроенные частоты (`frequency_from`, `frequency_to`) в `config/bots_config.json` позволяют боту пытаться размещать ордера **чаще, чем каждые 60 секунд** для каждой активной пары. Хотя бот может не размещать ордер в каждом цикле, *попытка* должна происходить в пределах 60-секундного окна.

Настоятельно не рекомендуется устанавливать чрезмерно длинные интервалы между торговыми циклами (например, приближающиеся к 60 секундам или превышающие их). 60-секундного окна обычно достаточно для поддержания активной книги ордеров. Например, обновление 10-15 ордеров может занять несколько секунд, но общее время цикла, растягивающееся до минут, может подвергнуть стратегию значительному риску из-за волатильности рынка. Если вам нужны более длинные интервалы, рассмотрите возможность соответствующего увеличения тайм-аута Dead Watcher, но помните о связанных с этим рисках сохранения осиротевших ордеров на более длительный срок. Поддержание разумно короткой частоты циклов (например, максимальная задержка в несколько секунд), как правило, безопаснее.