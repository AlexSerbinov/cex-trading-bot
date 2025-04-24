## Параметри Конфігурації Бота (Українська версія)

Конфігурація кожного екземпляра бота керує його поведінкою на конкретній торговій парі. Ці налаштування зберігаються централізовано і завантажуються ботом при старті. Ось короткий опис ключових параметрів:

##### `trade_amount_min` / `trade_amount_max` (Мінімальний / Максимальний обсяг ордера)

*   **Призначення:** Визначають діапазон обсягу (від мінімального до максимального) для **нових** ордерів, які створює бот.
*   **Механізм:** Обсяг кожного нового ордера обирається випадковим чином у межах [`trade_amount_min`, `trade_amount_max`].

##### `frequency_from` / `frequency_to` (Діапазон частоти дій, сек)

*   **Призначення:** Визначають діапазон (в секундах) для **додаткової випадкової затримки**, яку бот витримує між активними циклами дій.
*   **Механізм:** Перед кожним циклом дій (перевірка ринку, оновлення ордерів) бот обирає випадкову тривалість паузи в межах [`frequency_from`, `frequency_to`]. Фактичний час між циклами також включає час на виконання мережевих запитів та обчислень.

##### `price_factor` (Фактор Цінового Діапазону / "Ширина Сітки", %)

*   **Призначення:** Визначає, наскільки "широко" (у відсотках) будуть розміщені ордери бота відносно базової ціни (скоригованої на `market_gap`). Контролює щільність ордерів у стакані.
*   **Механізм:** Задає діапазон цін для ордерів: від `Базова_Ціна` до `Базова_Ціна * (1 ± price_factor / 100)`. Ордери розміщуються всередині цього діапазону.

##### `market_gap` (Ринковий Зазор / "Ширина Спреду", %)

*   **Призначення:** Створює гарантований мінімальний відсотковий розрив (спред) між **найкращими ордерами бота** та **реальними ринковими цінами** на зовнішній біржі. Ключовий параметр безпеки.
*   **Механізм:** Розраховує стартові ціни для бота: Ask бота ≥ Ask ринку * (1 + gap/100), Bid бота ≤ Bid ринку * (1 - gap/100).

##### `min_orders` (Мінімальна кількість ордерів)

*   **Призначення:** Визначає цільову кількість ордерів, яку бот **намагається** підтримувати на кожній стороні стакану (купівля та продаж).
*   **Механізм:** Бот додає ордери, якщо їх кількість менша за `min_orders`, і видаляє зайві (найменш вигідні), якщо кількість перевищує `min_orders + 1`. Цільовий діапазон: [`min_orders`, `min_orders + 1`].

---
## Параметры Конфигурации Бота (Русская версия)

Конфигурация каждого экземпляра бота управляет его поведением на конкретной торговой паре. Эти настройки хранятся централизованно и загружаются ботом при старте. Вот краткое описание ключевых параметров:

##### `trade_amount_min` / `trade_amount_max` (Минимальный / Максимальный объем ордера)

*   **Назначение:** Определяют диапазон объема (от минимального до максимального) для **новых** ордеров, которые создает бот.
*   **Механизм:** Объем каждого нового ордера выбирается случайным образом в пределах [`trade_amount_min`, `trade_amount_max`].

##### `frequency_from` / `frequency_to` (Диапазон частоты действий, сек)

*   **Назначение:** Определяют диапазон (в секундах) для **дополнительной случайной задержки**, которую бот выдерживает между активными циклами действий.
*   **Механизм:** Перед каждым циклом действий (проверка рынка, обновление ордеров) бот выбирает случайную продолжительность паузы в пределах [`frequency_from`, `frequency_to`]. Фактическое время между циклами также включает время на выполнение сетевых запросов и вычислений.

##### `price_factor` (Фактор Ценового Диапазона / "Ширина Сетки", %)

*   **Назначение:** Определяет, насколько "широко" (в процентах) будут размещены ордера бота относительно базовой цены (скорректированной на `market_gap`). Контролирует плотность ордеров в стакане.
*   **Механизм:** Задает диапазон цен для ордеров: от `Базовая_Цена` до `Базовая_Цена * (1 ± price_factor / 100)`. Ордера размещаются внутри этого диапазона.

##### `market_gap` (Рыночный Зазор / "Ширина Спреда", %)

*   **Назначение:** Создает гарантированный минимальный процентный разрыв (спред) между **лучшими ордерами бота** и **реальными рыночными ценами** на внешней бирже. Ключевой параметр безопасности.
*   **Механизм:** Рассчитывает стартовые цены для бота: Ask бота ≥ Market Ask * (1 + gap/100), Bot Bid ≤ Market Bid * (1 - gap/100).

##### `min_orders` (Минимальное количество ордеров)

*   **Назначение:** Определяет целевое количество ордеров, которое бот **пытается** поддерживать на каждой стороне стакана (покупка и продажа).
*   **Механизм:** Бот добавляет ордера, если их количество меньше `min_orders`, и удаляет лишние (наименее выгодные), если количество превышает `min_orders + 1`. Целевой диапазон: [`min_orders`, `min_orders + 1`].

---
## Bot Configuration Parameters (English Version)

The configuration of each bot instance controls its behavior on a specific trading pair. These settings are stored centrally and loaded by the bot upon startup. Here is a brief description of the key parameters:

##### `trade_amount_min` / `trade_amount_max` (Minimum / Maximum Order Amount)

*   **Purpose:** Define the volume range (from minimum to maximum) for **new** orders created by the bot.
*   **Mechanism:** The volume of each new order is chosen randomly within the [`trade_amount_min`, `trade_amount_max`] range.

##### `frequency_from` / `frequency_to` (Action Frequency Range, sec)

*   **Purpose:** Define the range (in seconds) for the **additional random delay** that the bot maintains between active action cycles.
*   **Mechanism:** Before each action cycle (market check, order updates), the bot selects a random pause duration within the [`frequency_from`, `frequency_to`] range. The actual time between cycles also includes the time for network requests and calculations.

##### `price_factor` (Price Range Factor / "Grid Width", %)

*   **Purpose:** Determines how "wide" (in percentage) the bot's orders will be placed relative to the base price (adjusted for `market_gap`). Controls the density of orders in the order book.
*   **Mechanism:** Sets the price range for orders: from `Base_Price` to `Base_Price * (1 ± price_factor / 100)`. Orders are placed within this range.

##### `market_gap` (Market Gap / "Spread Width", %)

*   **Purpose:** Creates a guaranteed minimum percentage gap (spread) between the **bot's best orders** and the **real market prices** on the external exchange. A key security parameter.
*   **Mechanism:** Calculates the starting prices for the bot: Bot Ask ≥ Market Ask * (1 + gap/100), Bot Bid ≤ Market Bid * (1 - gap/100).

##### `min_orders` (Minimum Number of Orders)

*   **Purpose:** Defines the target number of orders that the bot **tries** to maintain on each side of the order book (buy and sell).
*   **Mechanism:** The bot adds orders if their count is less than `min_orders` and removes excess orders (the least profitable ones) if the count exceeds `min_orders + 1`. The target range is [`min_orders`, `min_orders + 1`].


*Попередній вміст (російська та англійська версії) було видалено і замінено на оновлену українську версію, орієнтовану на бізнес-користувачів.*

