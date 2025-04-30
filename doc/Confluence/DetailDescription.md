# Подробное описание работы Depth-Bot

**Полезные ссылки:**

*   **Dev Среда:**
    *   Frontend Управления Ботами: http://164.68.117.90:5502/
    *   API Swagger: http://164.68.117.90:5501/
    *   Основной Frontend Биржи: dev.newexchanger.com
*   **Demo Среда:**
    *   Frontend Управления Ботами: http://164.68.117.90:6502/
    *   API Swagger: http://164.68.117.90:6501/
    *   Основной Frontend Биржи: new.newexchanger.com

## 1. Введение и Назначение

Depth-Bot (или бот глубины) — это автоматизированный программный инструмент, созданный для ключевой цели: **имитация активного и ликвидного рынка** на нашей криптовалютной бирже. Он достигает этого путем интеллектуального управления книгой ордеров (стаканом) для различных торговых пар (например, BTC_USDT, ETH_BTC).

**Основные задачи бота:**

1.  **Наполнение Книги Ордеров:** Автоматически размещает ордера на покупку (bids) и продажу (asks), чтобы стакан никогда не выглядел пустым.
2.  **Создание Видимости Активности:** Постоянное обновление ордеров (добавление новых, отмена старых) создает иллюзию оживленной торговли, что повышает доверие пользователей и стимулирует их к участию.
3.  **Обеспечение Базовой Ликвидности:** Размещенные ботом ордера становятся доступными для реальных пользователей, позволяя им совершать сделки даже при низкой активности других трейдеров.
4.  **Управление Рисками:** Использует параметры, такие как `MarketGap`, для выставления ордеров на безопасном расстоянии от рыночной цены, что потенциально делает сделки пользователей с ботом выгодными для биржи.

## 2. Как Работает Бот: Основной Цикл

Работа бота основана на непрерывном цикле анализа рынка и манипуляции ордерами:

1.  **Получение Рыночных Данных:** Бот подключается к внешней, высоколиквидной бирже (в настоящее время поддерживаются **Binance** и **Kraken**, выбор настраивается для каждой пары) и загружает актуальную информацию о книге ордеров (цены и объемы на покупку/продажу).
2.  **Анализ и Расчет:** На основе полученных данных и собственных настроек бот рассчитывает параметры для своих ордеров: цены, объемы, количество.
3.  **Размещение Ордеров:** Бот размещает **лимитные** ордера на покупку и продажу непосредственно на **нашей бирже** (через наш торговый сервер).
4.  **Имитация Динамики:** Чтобы рынок выглядел живым, бот не просто держит ордера, а постоянно их обновляет:
    *   **Отмена:** Периодически отменяет некоторые из своих ранее размещенных ордеров. Выбор ордера для отмены может быть случайным или взвешенным (бот может чаще отменять ордера, находящиеся **ближе** к текущей рыночной цене, имитируя исполнение).
    *   **Размещение Новых:** Почти сразу после отмены (или в рамках поддержки минимального количества ордеров) размещает новые лимитные ордера, часто со слегка измененными ценами или объемами.
5.  **Поддержка Количества:** Бот постоянно следит за количеством собственных ордеров в стакане и старается поддерживать его в пределах диапазона от `min_orders` до `min_orders + 1`. Например, если `min_orders` установлено на 12, бот будет поддерживать 12 или 13 ордеров в стакане.
6.  **Взаимодействие с Пользователями:** Если реальный пользователь решает совершить сделку по цене, предложенной ботом, происходит **реальная торговля**. Ордер бота исполняется.
7.  **Повторение Цикла:** Процесс повторяется с небольшой задержкой, настраиваемой (`frequency_from` / `frequency_to`). Использование диапазона задержки (напр., 1-5 секунд) вместо фиксированного значения позволяет имитировать более реалистичную, неравномерную активность рынка.

## 3. Ключевые Концепции и Механизмы

### 3.1. Имитация Книги Ордеров

*   **Источник данных:** Бот использует стакан внешней биржи как "ориентир", но не копирует его напрямую.
*   **Собственная логика:** Цены и объемы ордеров бота генерируются на основе данных источника, но корректируются в соответствии с параметрами `Price Deviation`, `MarketGap`, `trade_amount_min/max`.
*   **Количество ордеров (`min_orders`):** Бот поддерживает *минимальное* заданное количество собственных ордеров в стакане (и может временно иметь `min_orders + 1`). Если в стакане уже есть ордера реальных пользователей, ордера бота добавляются к ним.

### 3.2. Контроль Цен и Спреда

*   **Отклонение Цены (`Price Deviation`, %):** Этот параметр определяет, насколько "широко" бот расставляет свои ордера относительно текущей рыночной цены (рассчитанной как середина между лучшей покупкой и продажей на внешней бирже). Например, 0.1% означает, что ордера будут размещены в диапазоне +/- 0.1% от рыночной цены. Меньшее значение создает более плотный стакан, большее - разреженный.
*   **Рыночный Зазор (`MarketGap`, %):** Это **ключевой параметр безопасности**. Он создает дополнительный отступ от рыночной цены *перед* применением `Price Deviation`. Например, если рыночная цена BTC $100,000 и `MarketGap` = 0.5%, бот будет рассчитывать свою сетку ордеров не от $100,000, а от $99,500 для покупки и $100,500 для продажи. Это гарантирует, что ордера бота всегда немного хуже рыночных, что:
    *   Защищает против мгновенного арбитража против бота.
    *   Делает сделку пользователя с ботом потенциально выгодной для биржи (покупка дешевле / продажа дороже "рынка").

### 3.3. Управление Объемами и Частотой

*   **Объем Ордеров (`trade_amount_min` / `trade_amount_max`):** Каждый ордер, размещаемый ботом, имеет случайный объем в пределах этого диапазона. **Очень важно:** Бот должен иметь достаточный баланс соответствующих валют на своем счету (в админ-панели биржи), чтобы покрыть максимальный объем (`trade_amount_max`). Если баланса недостаточно, бот попытается разместить меньшие ордера, в соответствии с имеющимся балансом, но это может привести к некорректной работе.
*   **Частота Действий (`frequency_from` / `frequency_to`, сек):** Определяет **случайную** паузу (в секундах) между полными циклами действий бота для конкретной пары. Использование диапазона (напр., `frequency_from`=1, `frequency_to`=5) приводит к тому, что задержки будут неравномерными (напр., 3с, затем 1.5с, затем 4.8с), что лучше имитирует реальную рыночную активность по сравнению с фиксированным интервалом (напр., `frequency_from`=5, `frequency_to`=5).

### 3.4. Опциональный Маркет-Мейкинг (`market_maker_order_probability`, %)

*   **Назначение:** Этот **экспериментальный** параметр добавляет вероятность того, что бот, вместо размещения очередного лимитного ордера, будет **выполнять рыночный ордер**, то есть "ударять" по лучшей противоположной цене в стакане. Теоретически, это может исполнять ордера пользователей.
*   **Текущее состояние:** Эта функция считается **нестабильной и рискованной**. Из-за особенностей работы торгового сервера (где ордера бота могут исполняться сами с собой), этот механизм может не работать должным образом и приводить к неожиданным результатам.
*   **Рекомендация:** **Устанавливать значение 0%**. Не использовать этот параметр до дальнейшего исследования, тестирования и возможного пересмотра логики.

### 3.5. Мониторинг и Безопасность (`DeadWatcher`)

Хотя это отдельный сервис, `DeadWatcher` тесно связан с ботом. Бот отправляет сигнал "я жив" (`heartbeat`) в `DeadWatcher` **после каждой успешной установки ордера**. Если `DeadWatcher` не получает сигналов от бота для определенной пары в течение заданного времени (например, 60 секунд), он автоматически отменяет **все** ордера этого бота для этой пары на торговом сервере. Это защитный механизм от "зависших" ордеров в случае сбоя бота или потери связи.

### 3.6. Унифицированное Применение Параметров

Важно отметить, что все параметры конфигурации бота (`Price Deviation`, `MarketGap`, `trade_amount_min`/`max`, `frequency_from`/`to`, `min_orders` и т.д.) применяются **одинаково к обеим сторонам книги ордеров** – как к ордерам на покупку (bids), так и к ордерам на продажу (asks). Нет возможности настроить, например, разное отклонение цены или разное количество ордеров отдельно для покупки и продажи в рамках одного экземпляра бота.

## 4. Управление Ботами: Интерфейс Фронтенда

Для удобного управления ботами существует веб-интерфейс (доступный по отдельным URL для сред разработки и демо).

**(См. скриншоты, предоставленные пользователем)**

### 4.1. Список Ботов (`Bots list`)

*   **Отображение:** Таблица со всеми настроенными ботами.
*   **Колонки:**
    *   `Pair`: Торговая пара (напр., BTC_USDC).
    *   `Exchange`: Внешняя биржа-источник данных (binance/kraken).
    *   `Status`: `ACTIVE` (работает) или `INACTIVE` (остановлен).
    *   `Orders`: Минимальное поддерживаемое количество ордеров (`min_orders`).
    *   `Min/Max amount`: Минимальный/максимальный объем одного ордера (`trade_amount_min`/`max`).
    *   `Frequency (sec)`: Минимальная/максимальная задержка между циклами (`frequency_from`/`to`).
    *   `Deviation (%)`: Отклонение цены / Рыночный зазор (`price_deviation`/`market_gap`).
    *   `Market maker (%)`: Вероятность рыночного ордера (`market_maker_order_probability`).
    *   `Actions`:
        *   **Просмотр (Синяя иконка глаза):** Открывает страницу `Bot details` с полной конфигурацией и балансами.
        *   **Остановить/Запустить (Желтая иконка паузы / Зеленая иконка play):** Временно останавливает (`INACTIVE`) или возобновляет (`ACTIVE`) работу бота. **При остановке бот отменяет все свои активные ордера.**
        *   **Удалить (Красная иконка корзины):** Полностью удаляет конфигурацию бота. **Также отменяет все его активные ордера.**
*   **Кнопка `Create bot`:** Открывает форму для добавления и настройки нового бота.

### 4.2. Детали Бота (`Bot details`)

*   **Отображение:** Показывает все параметры конфигурации для выбранного бота.
*   **Управление:**
    *   `Edit`: Открывает форму для изменения параметров.
    *   `Enable`/`Disable`: Запускает или останавливает бота (аналогично иконкам Play/Pause в списке).
    *   `Delete`: Удаляет бота (аналогично иконке корзины).
*   **Балансы Бота (`Bot Balances`):** Показывает **текущие реальные балансы** для валют, используемых в **этой конкретной паре**. Обновляется в реальном времени.
    *   `Available`: Доступно для торговли.
    *   `Frozen`: Заблокировано в открытых ордерах (обычно 0, так как ордера размещает админ).
    *   `Total`: Общий баланс.
    *   **Важно:** Балансы могут изменяться, даже если этот бот остановлен, если другие активные боты торгуют теми же валютами.

### 4.3. Балансы Ботов (`Bot Balances`)

*   **Отображение:** Отдельная вкладка, показывающая **общие текущие балансы** всех валют на счету, который используется ботами.
*   **Кнопка `Refresh Balances`:** Обновляет данные о балансах.
*   **Форма `Top Up Balance`:** Позволяет **имитировать** пополнение баланса для тестирования (не выполняет реального пополнения).

## 5. Важные Аспекты и Соображения

*   **Требования к Балансу:** Это критично. Необходимо постоянно поддерживать достаточный баланс на счету бота в админ-панели биржи (URL для разработки: `https://newexchanger.com/admin/payment/wallet/fill_request/list`, для демо: `https://dev.api.newexchanger.com/admin/payment/wallet/fill_request/list` **(уточнить URL для демо)**), чтобы покрывать `trade_amount_max` для всех активных пар. Недостаток баланса ведет к непредсказуемому поведению.
*   **Сохранение Конфигурации:** Изменения, внесенные через API/Фронтенд, сохраняются в файле `bots_config.json` **внутри Docker-контейнера**. Если контейнер будет перезапущен без сохранения этого файла снаружи (например, через Docker Volumes), все изменения будут потеряны, и загрузится первоначальная версия файла.
*   **Безопасность:** В настоящее время **отсутствует авторизация** для как API, так и для фронтенд-интерфейса управления ботами (это в процессе). Это представляет значительный риск. Необходимо внедрить механизмы аутентификации/авторизации.
*   **Полный Цикл Прибыли:** В настоящее время бот не реализует полный цикл фиксации прибыли от сделок с пользователями (например, путем хеджирования на внешней бирже). Локально выгодные сделки просто изменяют баланс бота. Разработка полного цикла является следующим шагом.

---

# Detailed Description of Depth-Bot Operation (English Version)

**Useful Links:**

*   **Dev Environment:**
    *   Bot Management Frontend: http://164.68.117.90:5502/
    *   API Swagger: http://164.68.117.90:5501/
    *   Main Exchange Frontend: dev.newexchanger.com
*   **Demo Environment:**
    *   Bot Management Frontend: http://164.68.117.90:6502/
    *   API Swagger: http://164.68.117.90:6501/
    *   Main Exchange Frontend: new.newexchanger.com

## 1. Introduction and Purpose

The Depth-Bot is an automated software tool created with the key objective: **to simulate an active and liquid market** on our cryptocurrency exchange. It achieves this by intelligently managing the order book (depth) for various trading pairs (e.g., BTC_USDT, ETH_BTC).

**Main tasks of the bot:**

1.  **Filling the Order Book:** Automatically places buy (bids) and sell (asks) orders so the order book never looks empty.
2.  **Creating the Appearance of Activity:** Constant updating of orders (adding new ones, canceling old ones) creates the illusion of lively trading, which increases user trust and encourages participation.
3.  **Providing Basic Liquidity:** Orders placed by the bot become available to real users, allowing them to make trades even with low activity from other traders.
4.  **Risk Management:** Uses parameters like `MarketGap` to place orders at a safe distance from the market price, potentially making user trades with the bot profitable for the exchange.

## 2. How the Bot Works: Main Cycle

The bot's operation is based on a continuous cycle of market analysis and order manipulation:

1.  **Fetching Market Data:** The bot connects to an external, high-liquidity exchange (currently **Binance** and **Kraken** are supported, configurable per pair) and downloads current order book information (prices and volumes for buy/sell).
2.  **Analysis and Calculation:** Based on the obtained data and its own settings, the bot calculates parameters for its orders: prices, volumes, quantity.
3.  **Placing Orders:** The bot places **limit** buy and sell orders directly on **our exchange** (via our trading server).
4.  **Simulating Dynamics:** To make the market look alive, the bot doesn't just hold orders but constantly updates them:
    *   **Cancellation:** Periodically cancels some of its previously placed orders. The choice of order to cancel can be random or weighted (the bot might more often cancel orders **closer** to the current market price, simulating execution).
    *   **Placing New Orders:** Almost immediately after cancellation (or as part of maintaining the minimum number of orders), it places new limit orders, often with slightly changed prices or volumes.
5.  **Maintaining Quantity:** The bot constantly monitors the number of its own orders in the order book and tries to keep it within the range of `min_orders` to `min_orders + 1`. For example, if `min_orders` is set to 12, the bot will maintain 12 or 13 orders.
6.  **Interaction with Users:** If a real user decides to make a trade at the price offered by the bot, a **real trade** occurs. The bot's order is executed.
7.  **Repeating the Cycle:** The process repeats with a small, configurable delay (`frequency_from` / `frequency_to`). Using a delay range (e.g., 1-5 seconds) instead of a fixed value allows simulating more realistic, uneven market activity.

## 3. Key Concepts and Mechanisms

### 3.1. Order Book Simulation

*   **Data Source:** The bot uses the external exchange's order book as a "reference" but does not copy it directly.
*   **Own Logic:** The prices and volumes of the bot's orders are generated based on the source data but adjusted according to the `Price Deviation`, `MarketGap`, `trade_amount_min/max` parameters.
*   **Number of Orders (`min_orders`):** The bot maintains a *minimum* specified number of its own orders in the book (and may temporarily have `min_orders + 1`). If real user orders already exist, the bot's orders are added to them.

### 3.2. Price and Spread Control

*   **Price Deviation (%):** This parameter determines how "widely" the bot spreads its orders relative to the current market price (calculated as the midpoint between the best bid and ask on the external exchange). For example, 0.1% means orders will be placed within a +/- 0.1% range of the market price. A smaller value creates a tighter order book, a larger one creates a sparser one.
*   **Market Gap (%):** This is a **key security parameter**. It creates an additional offset from the market price *before* applying `Price Deviation`. For example, if the market price of BTC is $100,000 and `MarketGap` = 0.5%, the bot will calculate its order grid not from $100,000, but from $99,500 for buying and $100,500 for selling. This ensures the bot's orders are always slightly worse than the market, which:
    *   Protects against instant arbitrage against the bot.
    *   Makes a user's trade with the bot potentially profitable for the exchange (buying cheaper / selling dearer than the "market").

### 3.3. Volume and Frequency Management

*   **Order Volume (`trade_amount_min` / `trade_amount_max`):** Each order placed by the bot has a random volume within this range. **Very important:** The bot must have sufficient balance of the relevant currencies in its account (in the exchange's admin panel) to cover the maximum volume (`trade_amount_max`). If the balance is insufficient, the bot will try to place smaller orders according to the available balance, but this can lead to incorrect operation.
*   **Action Frequency (`frequency_from` / `frequency_to`, sec):** Defines the **random** pause (in seconds) between the bot's full action cycles for a specific pair. Using a range (e.g., `frequency_from`=1, `frequency_to`=5) results in uneven delays (e.g., 3s, then 1.5s, then 4.8s), which better simulates real market activity compared to a fixed interval (e.g., `frequency_from`=5, `frequency_to`=5).

### 3.4. Optional Market Making (`market_maker_order_probability`, %)

*   **Purpose:** This **experimental** parameter adds a probability that the bot, instead of placing another limit order, will **execute a market order**, i.e., "hit" the best opposite price in the order book. Theoretically, this could execute user orders.
*   **Current Status:** This function is considered **unstable and risky**. Due to the specifics of the trading server's operation (where bot orders might execute against themselves), this mechanism may not work correctly and can lead to unexpected results.
*   **Recommendation:** **Set the value to 0%**. Do not use this parameter until further investigation, testing, and possible logic revision.

### 3.5. Monitoring and Safety (`DeadWatcher`)

Although a separate service, `DeadWatcher` is closely linked to the bot. The bot sends an "I'm alive" signal (`heartbeat`) to `DeadWatcher` **after each successful order placement**. If `DeadWatcher` does not receive signals from the bot for a specific pair within a set time (e.g., 60 seconds), it automatically cancels **all** of that bot's orders for that pair on the trading server. This is a safety mechanism against "stuck" orders in case of a bot crash or communication loss.

### 3.6. Unified Parameter Application

It is important to note that all bot configuration parameters (`Price Deviation`, `MarketGap`, `trade_amount_min`/`max`, `frequency_from`/`to`, `min_orders`, etc.) are applied **equally to both sides of the order book** – both buy orders (bids) and sell orders (asks). There is no way to configure, for example, different price deviations or different numbers of orders separately for buys and sells within a single bot instance.

## 4. Bot Management: Frontend Interface

A web interface exists for convenient bot management (available at separate URLs for dev and demo environments).

**(See screenshots provided by the user)**

### 4.1. Bots List

*   **Display:** A table with all configured bots.
*   **Columns:**
    *   `Pair`: Trading pair (e.g., BTC_USDC).
    *   `Exchange`: External data source exchange (binance/kraken).
    *   `Status`: `ACTIVE` (running) or `INACTIVE` (stopped).
    *   `Orders`: Minimum supported number of orders (`min_orders`).
    *   `Min/Max amount`: Minimum/maximum volume of a single order (`trade_amount_min`/`max`).
    *   `Frequency (sec)`: Minimum/maximum delay between cycles (`frequency_from`/`to`).
    *   `Deviation (%)`: Price deviation / Market gap (`price_deviation`/`market_gap`).
    *   `Market maker (%)`: Probability of a market order (`market_maker_order_probability`).
    *   `Actions`:
        *   **View (Blue eye icon):** Opens the `Bot details` page with full configuration and balances.
        *   **Stop/Start (Yellow pause icon / Green play icon):** Temporarily stops (`INACTIVE`) or resumes (`ACTIVE`) the bot's operation. **When stopped, the bot cancels all its active orders.**
        *   **Delete (Red trash can icon):** Completely removes the bot's configuration. **Also cancels all its active orders.**
*   **`Create bot` Button:** Opens a form to add and configure a new bot.

### 4.2. Bot Details

*   **Display:** Shows all configuration parameters for the selected bot.
*   **Controls:**
    *   `Edit`: Opens the form to change parameters.
    *   `Enable`/`Disable`: Starts or stops the bot (similar to Play/Pause icons in the list).
    *   `Delete`: Deletes the bot (similar to the trash can icon).
*   **Bot Balances:** Shows the **current real balances** for the currencies used in **this specific pair**. Updates in real-time.
    *   `Available`: Available for trading.
    *   `Frozen`: Locked in open orders (usually 0, as the admin places orders).
    *   `Total`: Total balance.
    *   **Important:** Balances can change even if this bot is stopped if other active bots are trading the same currencies.

### 4.3. Bot Balances (Overall)

*   **Display:** A separate tab showing the **overall current balances** of all currencies in the account used by the bots.
*   **`Refresh Balances` Button:** Updates the balance data.
*   **`Top Up Balance` Form:** Allows **simulating** a balance top-up for testing purposes (does not perform a real top-up).

## 5. Important Aspects and Considerations

*   **Balance Requirements:** This is critical. Sufficient balance must be constantly maintained in the bot's account in the exchange admin panel (URL for dev: `https://newexchanger.com/admin/payment/wallet/fill_request/list`, for demo: `https://dev.api.newexchanger.com/admin/payment/wallet/fill_request/list` **(clarify demo URL)**) to cover `trade_amount_max` for all active pairs. Insufficient balance leads to unpredictable behavior.
*   **Configuration Persistence:** Changes made via the API/Frontend are saved to the `bots_config.json` file **inside the Docker container**. If the container is restarted without persisting this file externally (e.g., via Docker Volumes), all changes will be lost, and the initial version of the file will be loaded.
*   **Security:** Currently, there is **no authorization** for either the API or the bot management frontend interface (this is in progress). This poses a significant risk. Authentication/authorization mechanisms must be implemented.
*   **Full Profit Cycle:** The bot currently does not implement a full profit-taking cycle from user trades (e.g., by hedging on an external exchange). Locally profitable trades simply change the bot's balance. Developing a full cycle is the next step.
