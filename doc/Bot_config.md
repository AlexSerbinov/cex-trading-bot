# Russian Version

Давайте углубимся в раздел **Конфигурация Бота** документации вашего проекта Торгового Бота. Я проанализировал предоставленные скриншоты интерфейса фронтенда (список ботов и страница редактирования бота) и включил подробные объяснения параметров, которые вы предоставили. Я также опишу функциональность фронтенда, процесс управления конфигурацией (включая взаимодействие с API и поведение Docker-контейнера), а также требования к балансу для правильной работы бота. Кроме того, я выделю документацию Swagger, проблемы интеграции фронтенда и отсутствие авторизации согласно вашим инструкциям.

---

## Документация: Проект Торгового Бота

## 2. Конфигурация Бота

Этот раздел подробно описывает процесс конфигурации бота, включая параметры, определенные в `config/bots_config.json`, их значение и рекомендуемые настройки. Он также охватывает интерфейс фронтенда для управления ботами, как конфигурации обновляются через API, и важность поддержания достаточного баланса для операций бота.

### Обзор Файла Конфигурации
Настройки бота хранятся в `config/bots_config.json`, который определяет поведение каждого торгового бота для конкретных торговых пар (например, `BTC_USDT`, `ETH_BTC`). Этот файл загружается при запуске бота в Docker-контейнере. Если файл пуст, вы можете заполнить его через API, или вы можете предварительно определить настройки перед запуском контейнера.

**Обновления Конфигурации через API**:
- Конфигурация бота может быть изменена динамически с использованием API (например, через эндпоинты в `src/api/api.php`).
- Изменения, внесенные через API, напрямую обновляют файл `bots_config.json` внутри Docker-контейнера. Например, обновление `price_deviation` бота через API немедленно отразится в конфигурационном файле контейнера.
- **Важное Замечание**: Если Docker-контейнер остановлен и перезапущен, конфигурация возвращается к шаблону по умолчанию, определенному в `bots_config.json` на момент создания контейнера. Любые изменения, сделанные через API, будут потеряны, если обновленная конфигурация не сохранена вне контейнера.

**Документация Swagger**:
- Подробная документация API доступна через Swagger по следующим URL:
  - Разработка: `http://164.68.117.90:5501/`
  - Демо: `http://164.68.117.90:6501/`
- Интерфейс Swagger предоставляет полный список эндпоинтов для управления ботами, включая добавление, обновление и удаление конфигураций.

### Интерфейс Фронтенда
Фронтенд, доступный по адресу `http://164.68.117.90:5502/` (Разработка) или `http://164.68.117.90:6502/` (Демо), предоставляет удобный интерфейс для управления ботами. В настоящее время он разработан как демонстрационный инструмент, но предназначен для будущей интеграции в существующую админ-панель на PHP с использованием Swagger API.

**Структура Фронтенда**:
- **Страница Списка Ботов**:
  - Отображает таблицу всех активных ботов со столбцами: `ID`, `Пара`, `Биржа`, `Статус`, `Мин. ордеров`, `Мин/Макс сумма`, `Частота (сек)`, `Отклонение (%)`, `Маркет-мейкер (%)` и `Действия`.
  - **ID**: Уникальный идентификатор для каждого бота. Обратите внимание, что ID не важны для функциональности бота и используются в основном для отображения на фронтенде.
  - **Действия**:
    - **Смотреть (Синяя Иконка Глаза)**: Открывает подробный вид конфигурации бота, показывая все параметры (как видно на скриншоте "Редактирование бота").
    - **Остановить (Желтая Иконка Паузы)**: Приостанавливает операции бота без его удаления. Бот остается в списке, но прекращает размещать новые ордера. При остановке бот отменяет все свои существующие ордера в стакане ордеров.
    - **Удалить (Красная Иконка Корзины)**: Полностью удаляет бота из списка. Как и остановка, это действие также отменяет все ордера бота в стакане ордеров.
  - **Кнопка Создать Бота**: Расположена в правом верхнем углу, эта кнопка открывает форму для добавления нового бота с настраиваемыми параметрами.

- **Страница Редактирования Бота**:
  - Отображает форму для редактирования настроек существующего бота (например, `BTC_USDT` на скриншоте).
  - Поля соответствуют параметрам в `bots_config.json`, позволяя пользователям изменять настройки напрямую.
  - Кнопка "Сохранить" применяет изменения, которые затем отправляются через API для обновления конфигурации в Docker-контейнере.

**Заметки по Интеграции Фронтенда**:
- Фронтенд в настоящее время является автономным демонстрационным инструментом. В идеале, он должен быть интегрирован в существующую админ-панель на PHP (например, по адресу `https://newexchanger.com/admin/payment/wallet/fill_request/list` для Разработки) с использованием Swagger API.
- **Проблема Безопасности**: Отсутствует механизм авторизации как для фронтенда, так и для API. Это представляет угрозу безопасности, так как любой, у кого есть доступ к URL, может изменять конфигурации ботов. Следует рассмотреть внедрение аутентификации (например, JWT или OAuth) для защиты доступа.

### Детали Параметров
Ниже приведено подробное описание каждого параметра в `bots_config.json`, его назначение и рекомендуемые настройки на основе фронтенда и ваших данных.

- **`trading_pair`**:
  - **Описание**: Указывает торговую пару для бота (например, `BTC_USDT`, `ETH_BTC`).
  - **Фронтенд**: Отображается как выпадающий список, который получает все активные пары с торгового сервера (например, `http://1:18080` для Разработки).
  - **Поведение API**: При добавлении пары через API система выполняет две проверки:
    1. Проверяет, существует ли пара на торговом сервере.
    2. Подтверждает, поддерживается ли пара на выбранной бирже (например, Binance или Kraken).
    - Если любая из проверок не проходит, возвращается ошибка (например, "Пара не поддерживается").
  - **Рекомендация**: Убедитесь, что пара активна как на торговом сервере, так и на бирже, чтобы избежать ошибок.

- **`exchange`**:
  - **Описание**: Определяет внешнюю биржу, используемую как источник данных стакана ордеров (например, Binance, Kraken).
  - **Фронтенд**: Отображается как выпадающий список с опциями `binance` и `kraken`.
  - **Назначение**: Бот получает стакан ордеров с выбранной биржи (например, топ 100 ордеров на покупку/продажу с Binance) и использует эти данные для размещения собственных ордеров.
  - **Примечание**: Выбор биржи оказывает минимальное влияние на поведение бота, так как другие параметры (например, `price_deviation`, `market_gap`) значительно изменяют динамику стакана ордеров.
  - **Рекомендация**: Любая биржа может быть использована по предпочтению, так как логика бота нормализует данные.

- **`trade_amount_min` / `trade_amount_max`**:
  - **Описание**: Устанавливает минимальный и максимальный размеры ордеров для бота (например, `0.01` до `0.2` для `BTC_USDT`).
  - **Поведение**: Бот случайным образом размещает ордера с суммами между этими значениями. Например, если установлено `1` и `100` для `USDT`, ордера будут варьироваться от 1 до 100 USDT.
  - **Зависимость от Баланса**:
    - Если баланс бота в админ-панели недостаточен (например, `trade_amount_min` равен 1 BTC, но баланс 0.05 BTC), бот корректирует суммы ордеров вокруг доступного баланса.
    - В таких случаях бот все равно разместит несколько ордеров в качестве экстренной меры, но это не идеально и может привести к непоследовательному поведению.
  - **Рекомендация**: Убедитесь, что баланс бота в админ-панели (например, по адресу `https://newexchanger.com/admin/payment/wallet/fill_request/list`) превышает `trade_amount_max`, чтобы избежать экстренных корректировок. Для пар с `USDT` рекомендуется баланс на 100-200 USDT выше максимального.

- **`frequency_from` / `frequency_to`** (в секундах):
  - **Описание**: Определяет минимальную и максимальную задержку между торговыми действиями для конкретной пары.
  - **Поведение**:
    - Если установлено `60` секунд, бот приостанавливается на 60 секунд (плюс ~5 секунд на операционные расходы) между действиями.
    - Если установлено `0` секунд, действия происходят без дополнительной задержки.
  - **Примечание**: Бот в настоящее время написан на синхронном PHP, поэтому существует внутренняя минимальная задержка около ~10 секунд между действиями. Эти параметры добавляют дополнительную задержку поверх этого.
  - **Будущее Улучшение**: В будущих версиях бот будет перенесен на ReactPHP для обеспечения асинхронных операций, что уменьшит внутреннюю задержку и сделает эти параметры более точными.
  - **Рекомендация**: Для активных рынков установите `0`/`1`, чтобы минимизировать задержки. Для менее активных пар диапазон `3`/`5` секунд может помочь управлять нагрузкой.

- **`price_deviation`** (в процентах):
  - **Описание**: Определяет ценовой диапазон для сетки ордеров бота относительно рыночной цены. Минимально допустимое значение - `0.01%`. Если предоставлено значение меньше `0.01%` (включая `0`), оно автоматически будет установлено на `0.01%`.
  - **Поведение**: Если установлено `0.09%` (0.0009) и рыночная цена BTC составляет $100,000, бот размещает ордера в сетке от $99,910 до $100,090 (для 10 ордеров каждый шаг составляет ~$9). Сетка простирается как ниже, так и выше рыночной цены.
  - **Рекомендация**: Значение от `0.09%` до `0.1%` хорошо работает для большинства пар для создания плотной сетки. Более высокие значения (например, `0.5%`) могут слишком сильно разбросать ордера, уменьшая их видимость в стакане ордеров.

- **`market_gap`** (в процентах):
  - **Описание**: Увеличивает ширину спреда, чтобы избежать слишком узких спредов, которые могут быть рискованными.
  - **Поведение**: Если спред на бирже узкий (например, BTC по $99,999 bid и $100,000 ask), `market_gap` в `0.09%` добавляет буфер, размещая ордера бота немного шире (например, $99,990 и $100,010).
  - **Рекомендация**: Установите от `0.03%` до `0.1%` в зависимости от волатильности пары. Более высокие значения безопаснее для волатильных рынков, но могут уменьшить количество совпадений ордеров.

- **`min_orders`**:
  - **Описание**: Указывает минимальное количество ордеров, которое бот поддерживает на *каждой стороне* (покупка и продажа) стакана ордеров.
  - **Поведение**:
    - Бот гарантирует, что в стакане ордеров есть как минимум `min_orders` ордеров на покупку и как минимум `min_orders` ордеров на продажу для его админ-аккаунта. Эффективное максимальное количество ордеров на сторону автоматически устанавливается равным `min_orders + 1`.
    - Если существуют ордера пользователей (например, 5 ордеров на покупку от пользователей), и `min_orders` равно 12, общее количество ордеров на покупку в стакане будет не менее 17 (5 пользователей + 12 бота).
  - **Примечание**: Интерфейс стакана ордеров отображает до 15 ордеров. Установка `min_orders` на 12 или 13 позволяет видеть динамику ордеров (появление/исчезновение ордеров). Значение 15 может сделать изменения менее заметными.
  - **Рекомендация**: Значение 12 или 13 обычно рекомендуется для поддержания постоянного присутствия в стакане ордеров, позволяя при этом визуально наблюдать за изменениями ордеров.

- **`market_maker_order_probability`** (в процентах):
  - **Описание**: Контролирует вероятность того, что бот будет исполнять рыночные ордера вместо лимитных.
  - **Поведение**:
    - Если установлено `0%`, бот размещает только лимитные ордера, отменяя и заменяя их по мере необходимости.
    - Если установлено `100%`, бот исполняет рыночный ордер по лучшей доступной цене (например, сопоставляя с ордером по более высокой цене в стакане) перед размещением нового лимитного ордера.
  - **Применение**: Этот параметр может помочь исполнять ордера пользователей в стакане ордеров, сопоставляя их с рыночными ордерами.
  - **Предупреждение**: Это экспериментальная функция в разработке. Высокие значения (например, 100%) могут быть рискованными:
    - Если пользователь видит ордера бота (например, ордера по $20) и размещает ордер по плохой цене, бот может его исполнить, что потенциально приведет к убыткам.
    - Злоумышленник может использовать это, купив все ордера бота, разместив плохой ордер и заставив бота его исполнить.
  - **Рекомендация**: Используйте осторожно с низкими значениями (например, от 0% до 30%). Избегайте высоких значений до тех пор, пока функция не будет полностью протестирована и стабилизирована.

### Требование к Балансу
Для корректной работы бота необходимо поддерживать достаточный баланс в админ-панели:
- **URL Админ-панели**:
  - Разработка: `https://newexchanger.com/admin/payment/wallet/fill_request/list`
  - Демо: `https://dev.api.newexchanger.com/admin/payment/wallet/fill_request/list`
- **Процесс**:
  1. Зайдите в админ-панель для вашей среды.
  2. Проверьте или пополните баланс бота.
  3. Убедитесь, что баланс превышает `trade_amount_max` для каждой пары, чтобы избежать экстренных корректировок ордеров.
- **Влияние**: Недостаточный баланс приводит к тому, что бот размещает ордера меньшего размера, чем настроено, что может нарушить торговое поведение и динамику стакана ордеров.

---

## Размещение Скриншотов
Для улучшения документации предоставленные скриншоты можно вставить следующим образом:
- **Скриншот Списка Ботов** (первое изображение):
  - Вставить под подраздел **Интерфейс Фронтенда** > **Страница Списка Ботов**, чтобы визуально представить таблицу ботов, кнопки действий и кнопку "Создать бота".
  - Подпись: "Страница списка ботов, показывающая всех активных ботов с их конфигурациями и кнопками действий."
- **Скриншот Редактирования Бота** (второе изображение):
  - Вставить под подраздел **Интерфейс Фронтенда** > **Страница Редактирования Бота**, чтобы проиллюстрировать форму для редактирования параметров бота.
  - Подпись: "Страница редактирования бота, отображающая форму конфигурации для бота BTC_USDT."

---

Этот раздел предоставляет всесторонний обзор конфигурации бота, функциональности фронтенда и деталей параметров, а также затрагивает требования к балансу, обновления API и поведение Docker-контейнера. Дайте знать, если вы хотите скорректировать какую-либо часть или перейти к следующему разделу!

---
# English Version (Original)

Let's dive into the **Bot Configuration** section for your Trading Bot project documentation. I've analyzed the provided screenshots of the frontend interface (the bot list and the bot editing page) and incorporated the detailed parameter explanations you provided. I'll also describe the frontend functionality, the configuration management process (including API interactions and Docker container behavior), and the balance requirement for proper bot operation. Additionally, I'll highlight the Swagger documentation, frontend integration challenges, and the lack of authorization as per your instructions.

---

## Documentation: Trading Bot Project

## 2. Bot Configuration

This section details the bot configuration process, including the parameters defined in `config/bots_config.json`, their meanings, and recommended settings. It also covers the frontend interface for managing bots, how configurations are updated via API, and the importance of maintaining sufficient balance for bot operations.

### Configuration File Overview
Bot settings are stored in `config/bots_config.json`, which defines the behavior of each trading bot for specific trading pairs (e.g., `BTC_USDT`, `ETH_BTC`). This file is loaded when the bot starts in a Docker container. If the file is empty, you can populate it via the API, or you can predefine settings before launching the container.

**Configuration Updates via API**:
- The bot's configuration can be modified dynamically using the API (e.g., via endpoints in `src/api/api.php`).
- Changes made through the API directly update the `bots_config.json` file inside the Docker container. For example, updating a bot's `price_deviation` via the API will immediately reflect in the container's configuration file.
- **Important Note**: If the Docker container is stopped and restarted, the configuration reverts to the default template defined in `bots_config.json` at the time of container creation. Any changes made via API will be lost unless the updated configuration is persisted outside the container.

**Swagger Documentation**:
- Detailed API documentation is available via Swagger at the following URLs:
  - Development: `http://164.68.117.90:5501/`
  - Demo: `http://164.68.117.90:6501/`.
- The Swagger interface provides a comprehensive list of endpoints for managing bots, including adding, updating, and deleting configurations.

### Frontend Interface
The frontend, accessible at `http://164.68.117.90:5502/` (Development) or `http://164.68.117.90:6502/` (Demo), provides a user-friendly interface for managing bots. It is currently designed as a demo tool but is intended for future integration into an existing PHP-based admin panel using the Swagger API.

**Frontend Structure**:
- **Bot List Page**:
  - Displays a table of all active bots with columns: `ID`, `Pair`, `Exchange`, `Status`, `Min orders`, `Min/Max amount`, `Frequency (sec)`, `Deviation (%)`, `Market maker (%)`, and `Actions`.
  - **ID**: A unique identifier for each bot. Note that IDs are not significant for bot functionality and are primarily for frontend display purposes.
  - **Actions**:
    - **Watch (Blue Eye Icon)**: Opens a detailed view of the bot's configuration, showing all parameters (as seen in the "Editing bot" screenshot).
    - **Stop (Yellow Pause Icon)**: Pauses the bot's operations without deleting it. The bot remains in the list but stops placing new orders. Upon stopping, the bot cancels all its existing orders in the order book.
    - **Delete (Red Trash Icon)**: Removes the bot from the list entirely. Like stopping, this action also cancels all the bot's orders in the order book.
  - **Create Bot Button**: Located at the top-right, this button opens a form to add a new bot with configurable parameters.

- **Editing Bot Page**:
  - Displays a form for editing an existing bot's settings (e.g., `BTC_USDT` in the screenshot).
  - Fields correspond to the parameters in `bots_config.json`, allowing users to modify settings directly.
  - A "Save" button applies changes, which are then sent via API to update the configuration in the Docker container.

**Frontend Integration Notes**:
- The frontend is currently a standalone demo tool. Ideally, it should be integrated into the existing PHP admin panel (e.g., at `https://newexchanger.com/admin/payment/wallet/fill_request/list` for Development) using the Swagger API.
- **Security Concern**: There is no authorization mechanism for either the frontend or the API. This poses a security risk, as anyone with access to the URLs can modify bot configurations. Implementing authentication (e.g., JWT or OAuth) should be considered to secure access.

### Parameter Details
Below is a detailed breakdown of each parameter in `bots_config.json`, its purpose, and recommended settings based on the frontend and your input.

- **`trading_pair`**:
  - **Description**: Specifies the trading pair for the bot (e.g., `BTC_USDT`, `ETH_BTC`).
  - **Frontend**: Displayed as a dropdown list that fetches all active pairs from the trade server (e.g., `http://1:18080` for Development).
  - **API Behavior**: When adding a pair via API, the system performs two checks:
    1. Verifies if the pair exists on the trade server.
    2. Confirms if the pair is supported on the selected exchange (e.g., Binance or Kraken).
    - If either check fails, an error is returned (e.g., "Pair not supported").
  - **Recommendation**: Ensure the pair is active on both the trade server and the exchange to avoid errors.

- **`exchange`**:
  - **Description**: Defines the external exchange used as the source for order book data (e.g., Binance, Kraken).
  - **Frontend**: Displayed as a dropdown with options `binance` and `kraken`.
  - **Purpose**: The bot fetches the order book from the selected exchange (e.g., top 100 buy/sell orders from Binance) and uses this data to place its own orders.
  - **Note**: The choice of exchange has minimal impact on bot behavior, as other parameters (e.g., `price_deviation`, `market_gap`) significantly alter the order book dynamics.
  - **Recommendation**: Either exchange can be used based on preference, as the bot's logic normalizes the data.

- **`trade_amount_min` / `trade_amount_max`**:
  - **Description**: Sets the minimum and maximum order sizes for the bot (e.g., `0.01` to `0.2` for `BTC_USDT`).
  - **Behavior**: The bot randomly places orders with amounts between these values. For example, if set to `1` and `100` for `USDT`, orders will range from 1 to 100 USDT.
  - **Balance Dependency**:
    - If the bot's balance in the admin panel is insufficient (e.g., `trade_amount_min` is 1 BTC, but the balance is 0.05 BTC), the bot adjusts the order amounts around the available balance.
    - In such cases, the bot will still place a few orders as an emergency measure, but this is not ideal and may lead to inconsistent behavior.
  - **Recommendation**: Ensure the bot's balance in the admin panel (e.g., at `https://newexchanger.com/admin/payment/wallet/fill_request/list`) exceeds the `trade_amount_max` to avoid emergency adjustments. For `USDT` pairs, a balance of 100-200 USDT above the max is recommended.

- **`frequency_from` / `frequency_to`** (in seconds):
  - **Description**: Defines the minimum and maximum delay between trading actions for a specific pair.
  - **Behavior**:
    - If set to `60` seconds, the bot pauses for 60 seconds (plus ~5 seconds for operational overhead) between actions.
    - If set to `0` seconds, actions occur without additional delay.
  - **Note**: The bot is currently written in synchronous PHP, so there's an inherent minimum delay of ~10 seconds between actions. These parameters add an additional delay on top of that.
  - **Future Improvement**: In future versions, the bot will be migrated to ReactPHP to enable asynchronous operations, reducing the inherent delay and making these parameters more precise.
  - **Recommendation**: For active markets, set to `0`/`1` to minimize delays. For less active pairs, a range of `3`/`5` seconds can help manage load.

- **`price_deviation`** (in percentage):
  - **Description**: Determines the price range for the bot's order grid relative to the market price. The minimum allowed value is `0.01%`. If a value less than `0.01%` (including `0`) is provided, it will automatically be set to `0.01%`.
  - **Behavior**: If set to `0.09%` (0.0009) and the market price of BTC is $100,000, the bot places orders in a grid from $99,910 to $100,090 (for 10 orders, each step is ~$9). The grid extends both below and above the market price.
  - **Recommendation**: A value of `0.09%` to `0.1%` works well for most pairs to create a tight grid. Higher values (e.g., `0.5%`) may spread orders too far, reducing visibility in the order book.

- **`market_gap`** (in percentage):
  - **Description**: Increases the spread width to avoid overly tight spreads, which can be risky.
  - **Behavior**: If the exchange spread is narrow (e.g., BTC at $99,999 bid and $100,000 ask), a `market_gap` of `0.09%` adds a buffer, placing the bot's orders slightly wider (e.g., $99,990 and $100,010).
  - **Recommendation**: Set to `0.03%` to `0.1%` depending on the pair's volatility. Higher values are safer for volatile markets but may reduce order matching.

- **`min_orders`**:
  - **Description**: Specifies the minimum number of orders the bot maintains on *each side* (buy and sell) of the order book.
  - **Behavior**:
    - The bot ensures the order book has at least `min_orders` buy orders and at least `min_orders` sell orders for its admin account. The effective maximum number of orders per side is automatically set to `min_orders + 1`.
    - If user orders exist (e.g., 5 user buy orders), and `min_orders` is 12, the total buy orders in the book will be at least 17 (5 user + 12 bot).
  - **Note**: The order book UI displays up to 15 orders. Setting `min_orders` to 12 or 13 allows visibility of order dynamics (orders appearing/disappearing). A value of 15 may make changes less noticeable.
  - **Recommendation**: A value of 12 or 13 is generally recommended to maintain a consistent order book presence while allowing visual observation of order changes.

- **`market_maker_order_probability`** (in percentage):
  - **Description**: Controls the likelihood of the bot executing market orders instead of limit orders.
  - **Behavior**:
    - If set to `0%`, the bot only places limit orders, canceling and replacing them as needed.
    - If set to `100%`, the bot executes a market order at the best available price (e.g., matching a higher-priced order in the book) before placing a new limit order.
  - **Use Case**: This parameter can help execute user orders in the order book by matching them with market orders.
  - **Warning**: This is an experimental feature under development. High values (e.g., 100%) can be risky:
    - If a user sees the bot's orders (e.g., $20 orders) and places a poorly priced order, the bot may match it, potentially leading to losses.
    - A malicious actor could exploit this by buying all bot orders, placing a bad order, and causing the bot to match it.
  - **Recommendation**: Use with caution with low values (e.g., 0% to 30%). Avoid high values until the feature is fully tested and stabilized.

### Balance Requirement
For the bot to operate correctly, it is necessary to maintain a sufficient balance in the admin panel:
- **Admin Panel URLs**:
  - Development: `https://newexchanger.com/admin/payment/wallet/fill_request/list`
  - Demo: `https://dev.api.newexchanger.com/admin/payment/wallet/fill_request/list`
- **Process**:
  1. Log in to the admin panel for your environment.
  2. Check or top up the bot's balance.
  3. Ensure the balance exceeds `trade_amount_max` for each pair to avoid emergency order adjustments.
- **Impact**: Insufficient balance causes the bot to place orders smaller than configured, which can disrupt trading behavior and order book dynamics.

---

## Screenshot Placement
To enhance the documentation, the provided screenshots can be inserted as follows:
- **Bot List Screenshot** (first image):
  - Insert under the **Frontend Interface** > **Bot List Page** subsection to visually represent the bots table, action buttons, and "Create Bot" button.
  - Caption: "Bot list page showing all active bots with their configurations and action buttons."
- **Editing Bot Screenshot** (second image):
  - Insert under the **Frontend Interface** > **Editing Bot Page** subsection to illustrate the form for editing bot parameters.
  - Caption: "Editing bot page displaying the configuration form for the BTC_USDT bot."

---

This section provides a comprehensive overview of bot configuration, frontend functionality, and parameter details, while also addressing balance requirements, API updates, and Docker container behavior. Let me know if you'd like to refine any part or move on to the next section!