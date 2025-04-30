Russian Version
Лучшие Практики Конфигурации Бота
Этот документ дополняет базовое описание параметров (BotConfigParameters) и предоставляет более подробные объяснения, примеры и рекомендации для эффективной настройки маркет-мейкер бота.

Объем Ордеров (trade_amount_min / trade_amount_max)
Случайность Объема: Помните, что объем каждого нового ордера выбирается случайно между min и max.
Если вы хотите, чтобы ордера имели примерно одинаковый объем, установите min и max очень близко.
Если нужна большая вариативность, установите значительную разницу (напр., max в 5-10 раз больше min).
Пример: min = 0.1 BTC, max = 1 BTC -> Новые ордера могут иметь объем 0.15 BTC, 0.8 BTC, 0.42 BTC и т.д.
Пример в стакане (Случайный Объем):
Настройки: trade_amount_min = 0.1 BTC, trade_amount_max = 0.5 BTC.
Стакан ордеров Ask может выглядеть так:
| Цена (USDT) | Объем (BTC) |
|-------------|-------------|
| 50200       | 0.35        |  <-- Случайный объем между 0.1 и 0.5
| 50150       | 0.18        |  <-- Случайный объем между 0.1 и 0.5
| 50100       | 0.49        |  <-- Случайный объем между 0.1 и 0.5
| 50050       | 0.22        |  <-- Случайный объем между 0.1 и 0.5
Важность Баланса: Убедитесь, что на балансе бота достаточно средств (как в базовой, так и в котируемой валюте) для покрытия максимального ордера (trade_amount_max). Недостаточный баланс приведет к ошибкам или выставлению ордеров меньшего объема, чем ожидалось.
Нюанс: Частичное Исполнение (“Съедание”): Существующий ордер может быть исполнен частично рынком. Это означает, что его остаточный объем может стать меньше trade_amount_min. Это нормальное поведение рынка и не является ошибкой конфигурации.
Пример в стакане (Частичное Исполнение):
trade_amount_min = 0.1 BTC. Бот выставил Ask на 0.40 BTC по цене 50100.
| Цена (USDT) | Объем (BTC) |
|-------------|-------------|
| ...         | ...         |
| 50100       | 0.40        |  <-- Исходный ордер
| ...         | ...         |
Пришел рыночный ордер на покупку 0.35 BTC.
Обновленный стакан:
| Цена (USDT) | Объем (BTC) |
|-------------|-------------|
| ...         | ...         |
| 50100       | 0.05        |  <-- Остаток < trade_amount_min!
| ...         | ...         |
Частота Действий (frequency_from / frequency_to)
Рандомизация: Использование диапазона (напр., from=1, to=20) делает поведение бота менее предсказуемым, имитируя реальный рынок. Узкий диапазон (напр., from=21, to=22) приводит к почти фиксированным интервалам.
Реальное Время Цикла: Пам’ятайте, что frequency - это дополнительная пауза. Полное время цикла = (Время на API запросы + Вычисления) + Случайная_Пауза_Frequency. Даже при frequency_from = 0, минимальное время цикла составляет ~1-2 секунды.
Взаимодействие с Deadwatcher:
Deadwatcher ожидает сигнал от бота приблизно каждые 60 секунд.
Риск: Если frequency_to близко к 60 секундам или больше, бот может не успеть отправить сигнал, что приведет к ошибочному срабатыванию deadwatcher.
Рекомендация: Устанавливайте frequency_to значительно меньше 60 секунд (напр., до 45-50 секунд максимум).
Ограничения: frequency_to должно быть ≥ frequency_from. Минимальные значения (напр., 0 и 1) возможны, но для реалистичности рекомендуется начинать хотя бы с frequency_from = 0.5.
Риск Устаревания Ордеров: Длинные задержки (высокие значения frequency) в сочетании с большим количеством ордеров (min_orders) увеличивают время полного обновления стакана.
Пример: min_orders = 15, средняя задержка frequency = 30 сек. Время обновления ≈ 15 * (30 + ~1.5) ≈ 472 секунды (~8 минут).
Опасность: На волатильных рынках цена за это время может сильно измениться. Если market_gap и price_factor малы, старые ордера станут неактуальными, создавая риск арбитража против биржи.
Рекомендации:
На высоковолатильных парах увеличивайте market_gap и/или price_factor.
При использовании длинных задержек frequency обязательно компенсируйте это увеличением market_gap и/или price_factor.
Ширина Сетки (price_factor)
Базовая Цена: Рассчитывается на основе лучших цен Ask/Bid внешней биржи, скорректированных на market_gap.
Плотность: Малый price_factor (0.1%) -> плотный стакан около спреда. Большой price_factor (5%) -> разреженный, “растянутый” стакан.
Взаимодействие с min_orders: При одинаковом price_factor, большее количество min_orders означает меньший шаг между ценами ордеров.
Примеры Расчета:
Best Ask (Binance) = 100k, market_gap = 2%, min_orders = 5. Базовая Ask = 102k.
price_factor = 1%: Диапазон Ask [102k, 103.02k]. Ордера ~102000, 102255, …, 103020.
price_factor = 10%: Диапазон Ask [102k, 112.2k]. Ордера ~102000, 104550, …, 112200.
Примеры в Стакане:
Влияние price_factor (Базовая Ask = 102k, min_orders = 4):
price_factor = 0.5%: Диапазон [102k, 102.51k], Шаг ~170 USDT.
| Цена Ask (USDT) | Примерный шаг |
|-----------------|---------------|
| 102510          |               |
| 102340          | ~170 USDT     |
| 102170          | ~170 USDT     |
| 102000          | ~170 USDT     |
price_factor = 5%: Диапазон [102k, 107.1k], Шаг ~1700 USDT.
| Цена Ask (USDT) | Примерный шаг |
|-----------------|---------------|
| 107100          |               |
| 105400          | ~1700 USDT    |
| 103700          | ~1700 USDT    |
| 102000          | ~1700 USDT    |
Влияние min_orders (Базовая Ask = 102k, price_factor = 10%):
min_orders = 10: Шаг ~1133 USDT.
min_orders = 5: Шаг ~2550 USDT.
Симуляция Рынка:
Ликвидные пары (BTC/USDT): обычно меньший price_factor.
Менее ликвидные/новые пары: обычно больший price_factor.
Примечание (Минимальное значение): Система автоматически использует минимум (напр., 0.01%), даже если указано 0, чтобы избежать схлопывания всех цен в одну точку.
Ширина Спреда / Защита (market_gap)
Безопасность: Это самый важный параметр для защиты от арбитража.
Механизм: Гарантирует, что лучшие ордера бота всегда менее выгодны, чем рыночные:
Ask Бота ≥ Ask Рынка * (1 + gap/100)
Bid Бота ≤ Bid Рынка * (1 - gap/100)
Потенциальная Прибыльность: Позволяет бирже зарабатывать, когда пользователи торгуют против ордеров бота (покупают у бота дороже рынка, продают боту дешевле рынка).
Пример: Рынок Ask=100k, Bid=99.9k. market_gap = 5%. Bid Бота ≤ 94,905. Если пользователь продает боту по 94,905, это выгодно для биржи по сравнению с рыночной ценой покупки 99.9k.
Примеры в Стакане (Влияние market_gap):
Рынок Ask=100k, Bid=99.9k. price_factor = 1%, min_orders = 4.
market_gap = 0.1%: Спред Бота ~300 USDT ([100100, 101101] vs [98802, 99800]).
| Цена Ask (USDT) | Цена Bid (USDT) |
|-----------------|-----------------|
| 101101          |                 |
| ...             |                 |
| 100100          |                 |
|-----------------|-----------------| <--- Спред ~300 USDT
|                 | 99800           |
|                 | ...             |
|                 | 98802           |
market_gap = 3%: Спред Бота ~6097 USDT ([103000, 104030] vs [95934, 96903]).
| Цена Ask (USDT) | Цена Bid (USDT) |
|-----------------|-----------------|
| 104030          |                 |
| ...             |                 |
| 103000          |                 |
|-----------------|-----------------| <--- Спред ~6097 USDT
|                 | 96903           |
|                 | ...             |
|                 | 95934           |
Взаимодействие с price_factor:
market_gap устанавливает стартовые точки сетки (отступ от рынка).
price_factor определяет ширину сетки от этих стартовых точек.
Пример в Стакане (Комбинация):
Рынок: Ask=50k, Bid=49.95k. min_orders = 5. market_gap = 0.5%, price_factor = 1%.
Базовый Ask ≥ 50250. Базовый Bid ≤ 49700.25.
Диапазон Ask: [50250, 50752.5]. Диапазон Bid: [49203.25, 49700.25].
| Цена Ask (USDT) | Цена Bid (USDT) |
|-----------------|-----------------|
| 50752.50        |                 | <-- Верхняя граница Ask
| ...             |                 |
| 50250.00        |                 | <-- Лучший Ask (База_Ask)
|-----------------|-----------------| <--- Спред (определен gap)
|                 | 49700.25        | <-- Лучший Bid (База_Bid)
|                 | ...             |
|                 | 49203.25        | <-- Нижняя граница Bid
Риск Низкого market_gap:
Основной риск возникает при малом market_gap в сочетании с длительным временем обновления стакана (большие frequency или min_orders) на волатильном рынке.
Сценарий: market_gap=0.1%, Время Обновления=7.5 мин. Рынок растет на 1% за это время. Старые ордера Ask бота становятся дешевле нового рыночного Bid -> Арбитраж!
Рекомендация: market_gap (%) должен быть больше, чем ожидаемое изменение цены (%) в течение полного цикла обновления ордеров. Эта оценка зависит от волатильности пары.
Стабильные пары: Можно меньший market_gap.
Волатильные пары: Обязательно значительно больший market_gap.
Количество Ордеров (min_orders)
Целевой Диапазон: В стабильных условиях бот поддерживает [min_orders, min_orders + 1] ордеров на каждой стороне.
Нюанс: Флуктуация при Обновлении: Фактическое количество ордеров может временно падать ниже min_orders.
Причина: На волатильных рынках, когда бот обновляет ордера под новую рыночную цену, старые ордера могут отменяться быстрее, чем добавляются новые, особенно при малых market_gap и price_factor.
Пример Механизма:
У бота 10 Ask [100.00, 100.50].
Рынок движется, новая цена -> новый диапазон Ask [100.20, 100.70].
При обновлении ордера < 100.20 отменяются. Бот начинает добавлять новые.
Временно может быть < 10 ордеров (напр., 7).
Восстановление: Бот стремится вернуться к целевому диапазону min_orders / min_orders + 1 в следующих циклах.
Рекомендация: Если стабильная глубина стакана критична, рассмотрите использование немного больших market_gap и price_factor для уменьшения этих флуктуаций.
English Version
Bot Configuration Best Practices
This document supplements the basic parameter description (BotConfigParameters) and provides more detailed explanations, examples, and recommendations for effectively configuring the market maker bot.

Order Volume (trade_amount_min / trade_amount_max)
Volume Randomness: Remember that the volume of each new order is randomly selected between min and max.
If you want orders to have roughly the same volume, set min and max very close together.
If more variability is needed, set a significant difference (e.g., max 5-10 times larger than min).
Example: min = 0.1 BTC, max = 1 BTC -> New orders might have volumes like 0.15 BTC, 0.8 BTC, 0.42 BTC, etc.
Example in Order Book (Random Volume):
Settings: trade_amount_min = 0.1 BTC, trade_amount_max = 0.5 BTC.
The Ask order book might look like this:
| Price (USDT) | Volume (BTC) |
|--------------|--------------|
| 50200        | 0.35         |  <-- Random volume between 0.1 and 0.5
| 50150        | 0.18         |  <-- Random volume between 0.1 and 0.5
| 50100        | 0.49         |  <-- Random volume between 0.1 and 0.5
| 50050        | 0.22         |  <-- Random volume between 0.1 and 0.5
Balance Importance: Ensure the bot has sufficient funds on balance (in both base and quote currency) to cover the maximum order (trade_amount_max). Insufficient balance will lead to errors or placing orders smaller than expected.
Nuance: Partial Execution (“Eating”): An existing order can be partially filled by the market. This means its remaining volume can become less than trade_amount_min. This is normal market behavior and not a configuration error.
Example in Order Book (Partial Execution):
trade_amount_min = 0.1 BTC. The bot placed an Ask for 0.40 BTC at price 50100.
| Price (USDT) | Volume (BTC) |
|--------------|--------------|
| ...          | ...          |
| 50100        | 0.40         |  <-- Initial order
| ...          | ...          |
A market buy order for 0.35 BTC arrived.
Updated order book:
| Price (USDT) | Volume (BTC) |
|--------------|--------------|
| ...          | ...          |
| 50100        | 0.05         |  <-- Remainder < trade_amount_min!
| ...          | ...          |
Action Frequency (frequency_from / frequency_to)
Randomization: Using a range (e.g., from=1, to=20) makes the bot’s behavior less predictable, mimicking a real market. A narrow range (e.g., from=21, to=22) results in nearly fixed intervals.
Actual Cycle Time: Remember that frequency is an additional pause. Total cycle time = (Time for API requests + Calculations) + Random_Pause_Frequency. Even with frequency_from = 0, the minimum cycle time is ~1-2 seconds.
Interaction with Deadwatcher:
Deadwatcher expects a signal from the bot approximately every 60 seconds.
Risk: If frequency_to is close to 60 seconds or more, the bot might not send the signal in time, causing deadwatcher to trigger erroneously.
Recommendation: Set frequency_to significantly less than 60 seconds (e.g., up to 45-50 seconds maximum).
Constraints: frequency_to must be ≥ frequency_from. Minimum values (e.g., 0 and 1) are possible, but for realism, it’s recommended to start with at least frequency_from = 0.5.
Risk of Stale Orders: Long delays (high frequency values) combined with a large number of orders (min_orders) increase the time for a full order book refresh.
Example: min_orders = 15, average frequency delay = 30 sec. Refresh time ≈ 15 * (30 + ~1.5) ≈ 472 seconds (~8 minutes).
Danger: In volatile markets, the price can change significantly during this time. If market_gap and price_factor are small, old orders become irrelevant, creating a risk of arbitrage against the exchange.
Recommendations:
On highly volatile pairs, increase market_gap and/or price_factor.
When using long frequency delays, definitely compensate by increasing market_gap and/or price_factor.
Grid Width (price_factor)
Base Price: Calculated based on the best Ask/Bid prices of the external exchange, adjusted by market_gap.
Density: Small price_factor (0.1%) -> dense order book near the spread. Large price_factor (5%) -> sparse, “stretched” order book.
Interaction with min_orders: With the same price_factor, a larger min_orders means a smaller step between order prices.
Calculation Examples:
Best Ask (Binance) = 100k, market_gap = 2%, min_orders = 5. Base Ask = 102k.
price_factor = 1%: Ask range [102k, 103.02k]. Orders ~102000, 102255, …, 103020.
price_factor = 10%: Ask range [102k, 112.2k]. Orders ~102000, 104550, …, 112200.
Examples in Order Book:
Impact of price_factor (Base Ask = 102k, min_orders = 4):
price_factor = 0.5%: Range [102k, 102.51k], Step ~170 USDT.
| Ask Price (USDT) | Approx Step |
|------------------|-------------|
| 102510           |             |
| 102340           | ~170 USDT   |
| 102170           | ~170 USDT   |
| 102000           | ~170 USDT   |
price_factor = 5%: Range [102k, 107.1k], Step ~1700 USDT.
| Ask Price (USDT) | Approx Step |
|------------------|-------------|
| 107100           |             |
| 105400           | ~1700 USDT  |
| 103700           | ~1700 USDT  |
| 102000           | ~1700 USDT  |
Impact of min_orders (Base Ask = 102k, price_factor = 10%):
min_orders = 10: Step ~1133 USDT.
min_orders = 5: Step ~2550 USDT.
Market Simulation:
Liquid pairs (BTC/USDT): usually smaller price_factor.
Less liquid/new pairs: usually larger price_factor.
Note (Minimum Value): The system automatically uses a minimum (e.g., 0.01%), even if 0 is specified, to avoid collapsing all prices to a single point.
Spread Width / Protection (market_gap)
Safety: This is the most crucial parameter for protecting against arbitrage.
Mechanism: Ensures the bot’s best orders are always less favorable than the market:
Bot Ask ≥ Market Ask * (1 + gap/100)
Bot Bid ≤ Market Bid * (1 - gap/100)
Potential Profitability: Allows the exchange to earn when users trade against the bot’s orders (buying from the bot higher than market, selling to the bot lower than market).
Example: Market Ask=100k, Bid=99.9k. market_gap = 5%. Bot Bid ≤ 94,905. If a user sells to the bot at 94,905, it’s profitable for the exchange compared to the market buy price of 99.9k.
Examples in Order Book (Impact of market_gap):
Market Ask=100k, Bid=99.9k. price_factor = 1%, min_orders = 4.
market_gap = 0.1%: Bot Spread ~300 USDT ([100100, 101101] vs [98802, 99800]).
| Ask Price (USDT) | Bid Price (USDT) |
|------------------|------------------|
| 101101           |                  |
| ...              |                  |
| 100100           |                  |
|------------------|------------------| <--- Spread ~300 USDT
|                  | 99800            |
|                  | ...              |
|                  | 98802            |
market_gap = 3%: Bot Spread ~6097 USDT ([103000, 104030] vs [95934, 96903]).
| Ask Price (USDT) | Bid Price (USDT) |
|------------------|------------------|
| 104030           |                  |
| ...              |                  |
| 103000           |                  |
|------------------|------------------| <--- Spread ~6097 USDT
|                  | 96903            |
|                  | ...              |
|                  | 95934            |
Interaction with price_factor:
market_gap sets the starting points of the grid (offset from the market).
price_factor determines the width of the grid from these starting points.
Example in Order Book (Combination):
Market: Ask=50k, Bid=49.95k. min_orders = 5. market_gap = 0.5%, price_factor = 1%.
Base Ask ≥ 50250. Base Bid ≤ 49700.25.
Ask Range: [50250, 50752.5]. Bid Range: [49203.25, 49700.25].
| Ask Price (USDT) | Bid Price (USDT) |
|------------------|------------------|
| 50752.50         |                  | <-- Upper Ask limit
| ...              |                  |
| 50250.00         |                  | <-- Best Ask (Base_Ask)
|------------------|------------------| <--- Spread (defined by gap)
|                  | 49700.25         | <-- Best Bid (Base_Bid)
|                  | ...              |
|                  | 49203.25         | <-- Lower Bid limit
Risk of Low market_gap:
The main risk arises with a small market_gap combined with a long order book refresh time (large frequency or min_orders) in a volatile market.
Scenario: market_gap=0.1%, Refresh Time=7.5 min. Market rises 1% during this time. The bot’s old Ask orders become cheaper than the new market Bid -> Arbitrage!
Recommendation: market_gap (%) should be greater than the expected price change (%) during a full order refresh cycle. This estimation depends on the pair’s volatility.
Stable pairs: A smaller market_gap can be used.
Volatile pairs: Definitely a significantly larger market_gap.
Number of Orders (min_orders)
Target Range: In stable conditions, the bot maintains [min_orders, min_orders + 1] orders on each side.
Nuance: Fluctuation During Updates: The actual number of orders can temporarily drop below min_orders.
Reason: In volatile markets, when the bot updates orders to the new market price, old orders might be canceled faster than new ones are added, especially with small market_gap and price_factor.
Mechanism Example:
Bot has 10 Asks [100.00, 100.50].
Market moves, new price -> new Ask range [100.20, 100.70].
During update, orders < 100.20 are canceled. Bot starts adding new ones.
Temporarily, there might be < 10 orders (e.g., 7).
Recovery: The bot aims to return to the target range min_orders / min_orders + 1 in subsequent cycles.
Recommendation: If stable order book depth is critical, consider using slightly larger market_gap and price_factor to reduce these fluctuations.
Original Version (Ukrainian)
Найкращі Практики Конфігурації Бота
Цей документ доповнює базовий опис параметрів (BotConfigParameters) і надає детальніші пояснення, приклади та рекомендації для ефективного налаштування маркет-мейкер бота.

Обсяг Ордерів (trade_amount_min / trade_amount_max)
Випадковість Обсягу: Пам’ятайте, що обсяг кожного нового ордера обирається випадково між min та max.
Якщо ви хочете, щоб ордери мали приблизно однаковий обсяг, встановіть min та max дуже близькими.
Якщо потрібна більша варіативність, встановіть значну різницю (напр., max у 5-10 разів більший за min).
Приклад: min = 0.1 BTC, max = 1 BTC -> Нові ордери можуть мати обсяг 0.15 BTC, 0.8 BTC, 0.42 BTC тощо.
Приклад в стакані (Випадковий Обсяг):
Налаштування: trade_amount_min = 0.1 BTC, trade_amount_max = 0.5 BTC.
Стакан ордерів Ask може виглядати так:
| Ціна (USDT) | Обсяг (BTC) |
|-------------|-------------|
| 50200       | 0.35        |  <-- Випадковий обсяг між 0.1 та 0.5
| 50150       | 0.18        |  <-- Випадковий обсяг між 0.1 та 0.5
| 50100       | 0.49        |  <-- Випадковий обсяг між 0.1 та 0.5
| 50050       | 0.22        |  <-- Випадковий обсяг між 0.1 та 0.5
Важливість Балансу: Переконайтеся, що на балансі бота достатньо коштів (як в базовій, так і в котирувальній валюті) для покриття максимального ордера (trade_amount_max). Недостатній баланс призведе до помилок або виставлення ордерів меншого обсягу, ніж очікувалося.
Нюанс: Часткове Виконання (“З’їдання”): Існуючий ордер може бути виконаний частково ринком. Це означає, що його залишковий обсяг може стати меншим за trade_amount_min. Це нормальна поведінка ринку і не є помилкою конфігурації.
Приклад в стакані (Часткове Виконання):
trade_amount_min = 0.1 BTC. Бот виставив Ask на 0.40 BTC за ціною 50100.
| Ціна (USDT) | Обсяг (BTC) |
|-------------|-------------|
| ...         | ...         |
| 50100       | 0.40        |  <-- Початковий ордер
| ...         | ...         |
Прийшов ринковий ордер на купівлю 0.35 BTC.
Оновлений стакан:
| Ціна (USDT) | Обсяг (BTC) |
|-------------|-------------|
| ...         | ...         |
| 50100       | 0.05        |  <-- Залишок < trade_amount_min!
| ...         | ...         |
Частота Дій (frequency_from / frequency_to)
Рандомізація: Використання діапазону (напр., from=1, to=20) робить поведінку бота менш передбачуваною, імітуючи реальний ринок. Вузький діапазон (напр., from=21, to=22) призводить до майже фіксованих інтервалів.
Реальний Час Циклу: Пам’ятайте, що frequency - це додаткова пауза. Повний час циклу = (Час на API запити + Обчислення) + Випадкова_Пауза_Frequency. Навіть при frequency_from = 0, мінімальний час циклу складає ~1-2 секунди.
Взаємодія з Deadwatcher:
Deadwatcher очікує сигнал від бота приблизно кожні 60 секунд.
Ризик: Якщо frequency_to близьке до 60 секунд або більше, бот може не встигнути надіслати сигнал, що призведе до помилкового спрацювання deadwatcher.
Рекомендація: Встановлюйте frequency_to значно меншим за 60 секунд (напр., до 45-50 секунд максимум).
Обмеження: frequency_to має бути ≥ frequency_from. Мінімальні значення (напр., 0 та 1) можливі, але для реалістичності рекомендується починати хоча б з frequency_from = 0.5.
Ризик Старіння Ордерів: Довгі затримки (високі значення frequency) у поєднанні з великою кількістю ордерів (min_orders) збільшують час повного оновлення стакану.
Приклад: min_orders = 15, середня затримка frequency = 30 сек. Час оновлення ≈ 15 * (30 + ~1.5) ≈ 472 секунди (~8 хвилин).
Небезпека: На волатильних ринках ціна за цей час може сильно змінитися. Якщо market_gap та price_factor малі, старі ордери стануть неактуальними, створюючи ризик арбітражу проти біржі.
Рекомендації:
На високоволатильних парах збільшуйте market_gap та/або price_factor.
При використанні довгих затримок frequency обов’язково компенсуйте це збільшенням market_gap та/або price_factor.
Ширина Сітки (price_factor)
Базова Ціна: Розраховується на основі найкращих цін Ask/Bid зовнішньої біржі, скоригованих на market_gap.
Щільність: Малий price_factor (0.1%) -> щільний стакан біля спреду. Великий price_factor (5%) -> розріджений, “розтягнутий” стакан.
Взаємодія з min_orders: При однаковому price_factor, більша кількість min_orders означає менший крок між цінами ордерів.
Приклади Розрахунку:
Best Ask (Binance) = 100k, market_gap = 2%, min_orders = 5. Базова Ask = 102k.
price_factor = 1%: Діапазон Ask [102k, 103.02k]. Ордери ~102000, 102255, …, 103020.
price_factor = 10%: Діапазон Ask [102k, 112.2k]. Ордери ~102000, 104550, …, 112200.
Приклади в Стакані:
Вплив price_factor (Базова Ask = 102k, min_orders = 4):
price_factor = 0.5%: Діапазон [102k, 102.51k], Крок ~170 USDT.
| Ціна Ask (USDT) | Приблизний крок |
|-----------------|-----------------|
| 102510          |                 |
| 102340          | ~170 USDT       |
| 102170          | ~170 USDT       |
| 102000          | ~170 USDT       |
price_factor = 5%: Діапазон [102k, 107.1k], Крок ~1700 USDT.
| Ціна Ask (USDT) | Приблизний крок |
|-----------------|-----------------|
| 107100          |                 |
| 105400          | ~1700 USDT      |
| 103700          | ~1700 USDT      |
| 102000          | ~1700 USDT      |
Вплив min_orders (Базова Ask = 102k, price_factor = 10%):
min_orders = 10: Крок ~1133 USDT.
min_orders = 5: Крок ~2550 USDT.
Симуляція Ринку:
Ліквідні пари (BTC/USDT): зазвичай менший price_factor.
Менш ліквідні/нові пари: зазвичай більший price_factor.
Примітка (Мінімальне значення): Система автоматично використовує мінімум (напр., 0.01%), навіть якщо вказано 0, щоб уникнути схлопування всіх цін в одну точку.
Ширина Спреду / Захист (market_gap)
Безпека: Це найважливіший параметр для захисту від арбітражу.
Механізм: Гарантує, що найкращі ордери бота завжди менш вигідні за ринкові:
Ask Бота ≥ Ask Ринку * (1 + gap/100)
Bid Бота ≤ Bid Ринку * (1 - gap/100)
Потенційна Прибутковість: Дозволяє біржі заробляти, коли користувачі торгують проти ордерів бота (купують у бота дорожче ринку, продають боту дешевше ринку).
Приклад: Ринок Ask=100k, Bid=99.9k. market_gap = 5%. Bid Бота ≤ 94,905. Якщо користувач продає боту за 94,905, це вигідно для біржі порівняно з ринковою ціною купівлі 99.9k.
Приклади в Стакані (Вплив market_gap):
Рынок Ask=100k, Bid=99.9k. price_factor = 1%, min_orders = 4.
market_gap = 0.1%: Спред Бота ~300 USDT ([100100, 101101] vs [98802, 99800]).
| Ціна Ask (USDT) | Ціна Bid (USDT) |
|-----------------|-----------------|
| 101101          |                 |
| ...             |                 |
| 100100          |                 |
|-----------------|-----------------| <--- Спред ~300 USDT
|                 | 99800           |
|                 | ...             |
|                 | 98802           |
market_gap = 3%: Спред Бота ~6097 USDT ([103000, 104030] vs [95934, 96903]).
| Ціна Ask (USDT) | Ціна Bid (USDT) |
|-----------------|-----------------|
| 104030          |                 |
| ...             |                 |
| 103000          |                 |
|-----------------|-----------------| <--- Спред ~6097 USDT
|                 | 96903           |
|                 | ...             |
|                 | 95934           |
Взаємодія з price_factor:
market_gap встановлює стартові точки сітки (відступ від ринку).
price_factor визначає ширину сітки від цих стартових точок.
Приклад в Стакані (Комбінація):
Ринок: Ask=50k, Bid=49.95k. min_orders = 5. market_gap = 0.5%, price_factor = 1%.
Базовий Ask ≥ 50250. Базовий Bid ≤ 49700.25.
Діапазон Ask: [50250, 50752.5]. Діапазон Bid: [49203.25, 49700.25].
| Ціна Ask (USDT) | Ціна Bid (USDT) |
|-----------------|-----------------|
| 50752.50        |                 | <-- Верхня межа Ask
| ...             |                 |
| 50250.00        |                 | <-- Найкращий Ask (База_Ask)
|-----------------|-----------------| <--- Спред (визначений gap)
|                 | 49700.25        | <-- Найкращий Bid (База_Bid)
|                 | ...             |
|                 | 49203.25        | <-- Нижня межа Bid
Ризик Низького market_gap:
Основний ризик виникає при малому market_gap у поєднанні з довгим часом оновлення стакану (великі frequency або min_orders) на волатильному ринку.
Сценарій: market_gap=0.1%, Час Оновлення=7.5 хв. Ринок зростає на 1% за цей час. Старі ордери Ask бота стають дешевшими за новий ринковий Bid -> Арбітраж!
Рекомендація: market_gap (%) повинен бути більшим за очікувану зміну ціни (%) протягом повного циклу оновлення ордерів. Ця оцінка залежить від волатильності пари.
Стабільні пари: Можна менший market_gap.
Волатильні пари: Обов’язково значно більший market_gap.
Кількість Ордерів (min_orders)
Цільовий Діапазон: В стабільних умовах бот підтримує [min_orders, min_orders + 1] ордерів на кожній стороні.
Нюанс: Флуктуація при Оновленні: Фактична кількість ордерів може тимчасово падати нижче min_orders.
Причина: На волатильних ринках, коли бот оновлює ордери під нову ринкову ціну, старі ордери можуть скасовуватися швидше, ніж додаються нові, особливо при малих market_gap та price_factor.
Приклад Механізму:
Бот має 10 Ask [100.00, 100.50].
Ринок рухається, нова ціна -> новий діапазон Ask [100.20, 100.70].
При оновленні ордери < 100.20 скасовуються. Бот починає додавати нові.
Тимчасово може бути < 10 ордерів (напр., 7).
Відновлення: Бот прагне повернутися до цільового діапазону min_orders / min_orders + 1 в наступних циклах.
Рекомендація: Якщо стабільна глибина стакану критична, розгляньте використання трохи більших market_gap та price_factor для зменшення цих флуктуацій.