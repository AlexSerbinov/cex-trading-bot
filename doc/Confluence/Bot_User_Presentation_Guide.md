# Bot Guide

---

## Русская версия

### 1. Что такое Depth-Bot?

**Depth-Bot** — это автоматизированный бот для имитации активного и ликвидного рынка на криптобирже. Он наполняет стакан (order book) выбранных торговых пар, создает видимость торгов и обеспечивает базовую ликвидность для пользователей.

**Главная цель и польза:** Depth-Bot помогает создать видимость активного рынка, повышает доверие пользователей, привлекает новых трейдеров и обеспечивает возможность торговли даже на новых или малоликвидных парах. Это ключевой инструмент для развития биржи и повышения её привлекательности.

Этот гайд предназначен для администраторов и тех, кто презентует или управляет ботом через веб-интерфейс. Инсталляция здесь не рассматривается.

---

### 2. Доступ к интерфейсу

Веб-интерфейс (frontend) для управления ботами доступен по адресу:

- **Demo:** [http://164.68.117.90:6502/](http://164.68.117.90:6502/) — это основной frontend для администрирования и мониторинга ботов.

Здесь вы сможете видеть список ботов, их статусы, балансы, создавать/редактировать/удалять ботов.

---

### 3. Как работает бот (кратко)

- Бот получает рыночные данные с внешней биржи (например, Binance).
- Размещает лимитные ордера на покупку/продажу в заданном ценовом диапазоне.
- Количество, объемы и цены ордеров определяются параметрами конфигурации.
- Если реальный пользователь исполняет ордер бота — происходит обычная сделка.
- Отдельный сервис DeadWatcher следит, чтобы в случае сбоя все ордера бота были отменены.

`[СКРИНШОТ: Пример стакана с ордерами бота]`

---

### 4. Главный экран: Список ботов

После входа вы увидите таблицу со всеми ботами:

- **Pair** — торговая пара (BTC_USDT и т.д.)
- **Exchange** — источник рыночных данных (binance/kraken)
- **Status** — ACTIVE/INACTIVE
- **Orders** — целевое количество ордеров
- **Min/Max amount** — диапазон объема ордеров
- **Frequency (sec)** — задержка между циклами
- **Deviation (%)** — параметры ценообразования
- **Actions** — просмотр, запуск/остановка, удаление

`[СКРИНШОТ: Главный экран со списком ботов]`

---

### 5. Создание нового бота

1. Нажмите `Create bot`.
2. Заполните форму настроек:

    - **Pair Name** — название торговой пары (BTC_USDT)
    - **Exchange** — binance/kraken
    - **Min Orders** — минимальное количество ордеров
    - **Min/Max Amount** — диапазон объема одного ордера
    - **Frequency From/To** — диапазон задержки между циклами
    - **Price Factor** — price range width (%)
    - **Market Gap** — minimum gap from the market (%)

`[СКРИНШОТ: Форма создания нового бота]`

**Подробные рекомендации по выбору параметров смотрите в [BotConfig_BestPractices.md](BotConfig_BestPractices.md)**

**Рекомендуемые стартовые настройки для Binance:**
- Min Orders: 12
- Min Amount: 0.1
- Max Amount: 1
- Frequency: 0–5 сек
- Price Factor: 2%
- Market Gap: 1%

#### Рекомендации по настройке

- Выбирайте min/max amount в зависимости от ликвидности пары и доступного баланса.
- Для волатильных пар увеличивайте market_gap и price_factor.
- Подробнее — в [BotConfig_BestPractices.md](BotConfig_BestPractices.md)

---

### 6. Просмотр и управление ботом

Нажмите иконку глаза для просмотра деталей:

- Полная конфигурация бота
- Балансы по валюте пары (Available, Frozen, Total)
- Кнопки: Edit, Enable/Disable, Delete

`[СКРИНШОТ: Страница деталей бота]`
`[СКРИНШОТ: Форма редактирования бота]`

---

### 7. Мониторинг балансов

Отдельная вкладка/страница показывает все балансы бота по всем валютам.

- Кнопка `Refresh Balances` — обновить данные
- Форма `Top Up Balance` — только для имитации пополнения в тестах

**Важно:** Реальные балансы пополняются через админ-панель биржи!

`[СКРИНШОТ: Вкладка общих балансов]`

---

### 8. Важные аспекты

- **Баланс:** Всегда поддерживайте достаточный реальный баланс для всех валют, которыми торгует бот.
- **Сохранение конфигурации:** Если Docker-контейнер перезапустить без сохранения файла bots_config.json — все изменения через интерфейс будут утеряны.
- **Безопасность:** Сейчас интерфейс не имеет авторизации — ограничьте к нему доступ!
- **Цикл прибыли:** The bot does not hedge positions on the external exchange, balance changes occur only locally.

---

### 9. Типовые ошибки и как их избежать

- **Недостаточный баланс:** The bot will not be able to place orders of the required size or will not work at all. Always check balances before starting.
- **Too narrow market_gap on volatile pairs:** This creates an arbitrage risk — users can buy from the bot below market or sell higher. For volatile pairs, increase market_gap.
- **Too small price_factor:** If price_factor is too small, orders are concentrated too closely, and an order from the other side of the book can "eat" several of your orders at once. For liquid pairs, you can set less, for illiquid pairs — more.
- **Too long delays (frequency):** If frequency_from/frequency_to are large, orders may become "stale" and not match the market. This is especially critical on volatile pairs.

---

### 10. Симулированные vs реальные депозиты

- **Simulated deposits (via Frontend):** Add virtual balance for testing and demonstration only. They are not backed by real assets on the exchange.
- **Real deposits (via Fireblocks):** For real trading, funds must be on real exchange accounts connected via Fireblocks. The bot uses only these balances to place orders.
- **Important:** For real trading, top up the balance via the exchange admin panel. Simulated deposits do not allow real operations!

---

### 11. Часто задаваемые вопросы (FAQ)

- **What happens if you stop the bot?** — All its orders are automatically canceled. If you delete the bot, orders will be deleted after 30 seconds. If the bot hangs, DeadWatcher will delete orders after 50 seconds.
- **Can I change settings on the fly?** — Yes, via the interface. But after restarting the container, changes may be lost if not saved in bots_config.json.
- **What to do if there is not enough balance?** — Top up via the exchange admin panel. Check if the balance is sufficient for trade_amount_max.
- **Why doesn't the bot place orders?** — Check if the bot is running, if the pair is active, if there are no extreme settings (e.g., too large/small parameter values).
- **How to check if the bot is working correctly?** — Check the status in the web interface, logs, presence of orders in the book, balance updates.
- **What frequency to set?** — It is not recommended to set frequency above 60 seconds, as this may cause a conflict with DeadWatcher. Also remember: if you have 10 orders on each side and frequency is 30 seconds, a full book update will take 20*30 = 600 seconds (10 minutes). On a volatile market, this can be critical.
- **Does it matter which external exchange (binance/kraken) to choose?** — For the user, this almost does not affect the bot's operation. The external exchange is used only as a source of market data for book simulation, and all orders are placed on your exchange.

---

**Insert screenshots in the appropriate sections for better clarity!**

---

## English version

### 1. What is Depth-Bot?

**Depth-Bot** is an automated bot for simulating an active and liquid market on a crypto exchange. It fills the order book of selected trading pairs, creates the appearance of trading, and provides basic liquidity for users.

**Main goal and benefit:** Depth-Bot helps create the appearance of an active market, increases user trust, attracts new traders, and ensures the possibility of trading even on new or low-liquidity pairs. This is a key tool for exchange growth and attractiveness.

This guide is intended for administrators and those who present or manage the bot via the web interface. Installation is not covered here.

---

### 2. Access to the interface

The web interface (frontend) for managing bots is available at:

- **Demo:** [http://164.68.117.90:6502/](http://164.68.117.90:6502/) — this is the main frontend for bot administration and monitoring.

Here you can see the list of bots, their statuses, balances, create/edit/delete bots.

---

### 3. How the bot works (briefly)

- The bot receives market data from an external exchange (e.g., Binance).
- Places limit buy/sell orders within a specified price range.
- The number, volumes, and prices of orders are determined by configuration parameters.
- If a real user executes a bot order — a regular trade occurs.
- A separate DeadWatcher service ensures that in case of a failure, all bot orders are canceled.

`[SCREENSHOT: Example order book with bot orders]`

---

### 4. Main screen: Bots list

After logging in, you will see a table with all bots:

- **Pair** — trading pair (BTC_USDT, etc.)
- **Exchange** — source of market data (binance/kraken)
- **Status** — ACTIVE/INACTIVE
- **Orders** — target number of orders
- **Min/Max amount** — order volume range
- **Frequency (sec)** — delay between cycles
- **Deviation (%)** — pricing parameters
- **Actions** — view, start/stop, delete

`[SCREENSHOT: Main screen with bots list]`

---

### 5. Creating a new bot

1. Click `Create bot`.
2. Fill in the settings form:

    - **Pair Name** — trading pair name (BTC_USDT)
    - **Exchange** — binance/kraken
    - **Min Orders** — minimum number of orders
    - **Min/Max Amount** — order volume range
    - **Frequency From/To** — delay range between cycles
    - **Price Factor** — price range width (%)
    - **Market Gap** — minimum gap from the market (%)

`[SCREENSHOT: Create bot form]`

**See detailed parameter selection recommendations in [BotConfig_BestPractices.md](BotConfig_BestPractices.md)**

**Recommended starting settings for Binance:**
- Min Orders: 12
- Min Amount: 0.1
- Max Amount: 1
- Frequency: 0–5 sec
- Price Factor: 2%
- Market Gap: 1%

#### Setup recommendations

- Choose min/max amount according to the pair's liquidity and available balance.
- For volatile pairs, increase market_gap and price_factor.
- More details — in [BotConfig_BestPractices.md](BotConfig_BestPractices.md)

---

### 6. Viewing and managing a bot

Click the eye icon to view details:

- Full bot configuration
- Pair currency balances (Available, Frozen, Total)
- Buttons: Edit, Enable/Disable, Delete

`[SCREENSHOT: Bot details page]`
`[SCREENSHOT: Edit bot form]`

---

### 7. Balance monitoring

A separate tab/page shows all bot balances for all currencies.

- `Refresh Balances` button — update data
- `Top Up Balance` form — for simulation only in tests

**Important:** Real balances are topped up via the exchange admin panel!

`[SCREENSHOT: General balances tab]`

---

### 8. Important aspects

- **Balance:** Always maintain a sufficient real balance for all currencies traded by the bot.
- **Configuration saving:** If the Docker container is restarted without saving bots_config.json — all changes via the interface will be lost.
- **Security:** The interface currently has no authorization — restrict access!
- **Profit cycle:** The bot does not hedge positions on the external exchange, balance changes occur only locally.

---

### 9. Typical mistakes and how to avoid them

- **Insufficient balance:** The bot will not be able to place orders of the required size or will not work at all. Always check balances before starting.
- **Too narrow market_gap on volatile pairs:** This creates an arbitrage risk — users can buy from the bot below market or sell higher. For volatile pairs, increase market_gap.
- **Too small price_factor:** If price_factor is too small, orders are concentrated too closely, and an order from the other side of the book can "eat" several of your orders at once. For liquid pairs, you can set less, for illiquid pairs — more.
- **Too long delays (frequency):** If frequency_from/frequency_to are large, orders may become "stale" and not match the market. This is especially critical on volatile pairs.

---

### 10. Simulated vs real deposits

- **Simulated deposits (via Frontend):** Add virtual balance for testing and demonstration only. They are not backed by real assets on the exchange.
- **Real deposits (via Fireblocks):** For real trading, funds must be on real exchange accounts connected via Fireblocks. The bot uses only these balances to place orders.
- **Important:** For real trading, top up the balance via the exchange admin panel. Simulated deposits do not allow real operations!

---

### 11. Frequently Asked Questions (FAQ)

- **What happens if you stop the bot?** — All its orders are automatically canceled. If you delete the bot, orders will be deleted after 30 seconds. If the bot hangs, DeadWatcher will delete orders after 50 seconds.
- **Can I change settings on the fly?** — Yes, via the interface. But after restarting the container, changes may be lost if not saved in bots_config.json.
- **What to do if there is not enough balance?** — Top up via the exchange admin panel. Check if the balance is sufficient for trade_amount_max.
- **Why doesn't the bot place orders?** — Check if the bot is running, if the pair is active, if there are no extreme settings (e.g., too large/small parameter values).
- **How to check if the bot is working correctly?** — Check the status in the web interface, logs, presence of orders in the book, balance updates.
- **What frequency to set?** — It is not recommended to set frequency above 60 seconds, as this may cause a conflict with DeadWatcher. Also remember: if you have 10 orders on each side and frequency is 30 seconds, a full book update will take 20*30 = 600 seconds (10 minutes). On a volatile market, this can be critical.
- **Does it matter which external exchange (binance/kraken) to choose?** — For the user, this almost does not affect the bot's operation. The external exchange is used only as a source of market data for book simulation, and all orders are placed on your exchange.

---

**Insert screenshots in the appropriate sections for better clarity!**

