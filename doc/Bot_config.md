Let’s dive into the **Bot Configuration** section for your Trading Bot project documentation. I’ve analyzed the provided screenshots of the frontend interface (the bot list and the bot editing page) and incorporated the detailed parameter explanations you provided. I’ll also describe the frontend functionality, the configuration management process (including API interactions and Docker container behavior), and the balance requirement for proper bot operation. Additionally, I’ll highlight the Swagger documentation, frontend integration challenges, and the lack of authorization as per your instructions.

---

## Documentation: Trading Bot Project

## 2. Bot Configuration

This section details the bot configuration process, including the parameters defined in `config/bots_config.json`, their meanings, and recommended settings. It also covers the frontend interface for managing bots, how configurations are updated via API, and the importance of maintaining sufficient balance for bot operations.

### Configuration File Overview
Bot settings are stored in `config/bots_config.json`, which defines the behavior of each trading bot for specific trading pairs (e.g., `BTC_USDT`, `ETH_BTC`). This file is loaded when the bot starts in a Docker container. If the file is empty, you can populate it via the API, or you can predefine settings before launching the container.

**Configuration Updates via API**:
- The bot’s configuration can be modified dynamically using the API (e.g., via endpoints in `src/api/api.php`).
- Changes made through the API directly update the `bots_config.json` file inside the Docker container. For example, updating a bot’s `price_deviation` via the API will immediately reflect in the container’s configuration file.
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
    - **Watch (Blue Eye Icon)**: Opens a detailed view of the bot’s configuration, showing all parameters (as seen in the "Editing bot" screenshot).
    - **Stop (Yellow Pause Icon)**: Pauses the bot’s operations without deleting it. The bot remains in the list but stops placing new orders. Upon stopping, the bot cancels all its existing orders in the order book.
    - **Delete (Red Trash Icon)**: Removes the bot from the list entirely. Like stopping, this action also cancels all the bot’s orders in the order book.
  - **Create Bot Button**: Located at the top-right, this button opens a form to add a new bot with configurable parameters.

- **Editing Bot Page**:
  - Displays a form for editing an existing bot’s settings (e.g., `BTC_USDT` in the screenshot).
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
  - **Recommendation**: Either exchange can be used based on preference, as the bot’s logic normalizes the data.

- **`trade_amount_min` / `trade_amount_max`**:
  - **Description**: Sets the minimum and maximum order sizes for the bot (e.g., `0.01` to `0.2` for `BTC_USDT`).
  - **Behavior**: The bot randomly places orders with amounts between these values. For example, if set to `1` and `100` for `USDT`, orders will range from 1 to 100 USDT.
  - **Balance Dependency**:
    - If the bot’s balance in the admin panel is insufficient (e.g., `trade_amount_min` is 1 BTC, but the balance is 0.05 BTC), the bot adjusts the order amounts around the available balance.
    - In such cases, the bot will still place a few orders as an emergency measure, but this is not ideal and may lead to inconsistent behavior.
  - **Recommendation**: Ensure the bot’s balance in the admin panel (e.g., at `https://newexchanger.com/admin/payment/wallet/fill_request/list`) exceeds the `trade_amount_max` to avoid emergency adjustments. For `USDT` pairs, a balance of 100-200 USDT above the max is recommended.

- **`frequency_from` / `frequency_to`** (in seconds):
  - **Description**: Defines the minimum and maximum delay between trading actions for a specific pair.
  - **Behavior**:
    - If set to `60` seconds, the bot pauses for 60 seconds (plus ~5 seconds for operational overhead) between actions.
    - If set to `0` seconds, actions occur without additional delay.
  - **Note**: The bot is currently written in synchronous PHP, so there’s an inherent minimum delay of ~10 seconds between actions. These parameters add an additional delay on top of that.
  - **Future Improvement**: In future versions, the bot will be migrated to ReactPHP to enable asynchronous operations, reducing the inherent delay and making these parameters more precise.
  - **Recommendation**: For active markets, set to `0`/`1` to minimize delays. For less active pairs, a range of `3`/`5` seconds can help manage load.

- **`price_deviation`** (in percentage):
  - **Description**: Determines the price range for the bot’s order grid relative to the market price.
  - **Behavior**: If set to `0.09%` (0.0009) and the market price of BTC is $100,000, the bot places orders in a grid from $99,910 to $100,090 (for 10 orders, each step is ~$9). The grid extends both below and above the market price.
  - **Recommendation**: A value of `0.09%` to `0.1%` works well for most pairs to create a tight grid. Higher values (e.g., `0.5%`) may spread orders too far, reducing visibility in the order book.

- **`market_gap`** (in percentage):
  - **Description**: Increases the spread width to avoid overly tight spreads, which can be risky.
  - **Behavior**: If the exchange spread is narrow (e.g., BTC at $99,999 bid and $100,000 ask), a `market_gap` of `0.09%` adds a buffer, placing the bot’s orders slightly wider (e.g., $99,990 and $100,010).
  - **Recommendation**: Set to `0.03%` to `0.1%` depending on the pair’s volatility. Higher values are safer for volatile markets but may reduce order matching.

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
    - If a user sees the bot’s orders (e.g., $20 orders) and places a poorly priced order, the bot may match it, potentially leading to losses.
    - A malicious user could exploit this by buying all bot orders, placing a bad order, and forcing the bot to match it.
  - **Recommendation**: Use cautiously with low values (e.g., 0% to 30%). Avoid high values until the feature is fully tested and stabilized.

### Balance Requirement
For the bot to function correctly, sufficient balance must be maintained in the admin panel:
- **Admin URLs**:
  - Development: `https://newexchanger.com/admin/payment/wallet/fill_request/list`
  - Demo: `https://dev.api.newexchanger.com/admin/payment/wallet/fill_request/list`
- **Process**:
  1. Access the admin URL for your environment.
  2. Verify or top up the bot’s balance.
  3. Ensure the balance exceeds the `trade_amount_max` for each pair to avoid emergency order adjustments.
- **Impact**: Insufficient balance leads to the bot placing smaller orders than configured, which can disrupt trading behavior and order book dynamics.

---

## Placement of Screenshots
To enhance the documentation, the provided screenshots can be inserted as follows:
- **Bot List Screenshot** (first image):
  - Insert under the **Frontend Interface** > **Bot List Page** subsection to visually represent the table of bots, action buttons, and the "Create bot" button.
  - Caption: "Bot List page showing all active bots with their configurations and action buttons."
- **Editing Bot Screenshot** (second image):
  - Insert under the **Frontend Interface** > **Editing Bot Page** subsection to illustrate the form for editing bot parameters.
  - Caption: "Editing Bot page displaying the configuration form for the BTC_USDT bot."

---

This section provides a comprehensive overview of bot configuration, frontend functionality, and parameter details, while addressing the balance requirement, API updates, and Docker container behavior. Let me know if you’d like to adjust any part or proceed to the next section!