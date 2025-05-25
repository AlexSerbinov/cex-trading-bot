# User Guide: Managing the Depth-Bot

## 1. Introduction

Welcome to the Depth-Bot user guide!

**Depth-Bot** is an automated tool designed to simulate an active and liquid market on our cryptocurrency exchange. It populates the order book for selected trading pairs, creates the appearance of trading activity, and provides basic liquidity for users.

This guide is intended for administrators and those responsible for managing the bots. It focuses on using the **web interface for bot management** to configure, start, stop, and monitor them. The bot installation process is **not** covered here.

## 2. Accessing the Management Interface

The web interface for managing bots is available at different addresses for test and demo environments:

*   **Dev Environment (Development):**
    *   Bot Management Frontend: [`http://164.68.117.90:5502/`](http://164.68.117.90:5502/)
    *   *Additional:* API Swagger (API documentation): [`http://164.68.117.90:5501/`](http://164.68.117.90:5501/)
    *   *Additional:* Main Exchange Frontend: `dev.newexchanger.com`
*   **Demo Environment (Demonstration):**
    *   Bot Management Frontend: [`http://164.68.117.90:6502/`](http://164.68.117.90:6502/)
    *   *Additional:* API Swagger: [`http://164.68.117.90:6501/`](http://164.68.117.90:6501/)
    *   *Additional:* Main Exchange Frontend: `new.newexchanger.com`

Screenshots will be used later in this guide to illustrate the interface.

## 3. How the Bot Works (Briefly)

The bot operates in a continuous cycle for each active trading pair:

1.  **Receives Data:** Requests current prices and volumes from the order book of an external exchange (`binance` or `kraken`).
2.  **Calculates Prices:** Determines the price range for its orders based on external exchange data and the `market_gap` and `price_factor` parameters.
3.  **Calculates Volumes:** Selects a random volume for new orders within `trade_amount_min` and `trade_amount_max`.
4.  **Manages Orders:**
    *   Checks the current number of its orders on the exchange.
    *   If there are too few orders (less than `min_orders`), it adds new limit buy and sell orders.
    *   If there are too many orders (more than `min_orders + 1`), it cancels the "excess" ones (usually the least favorable).
    *   Periodically cancels and immediately places new orders, even if the quantity is within the norm, to simulate market dynamics.
5.  **Interacts with Users:** If a real exchange user executes an order placed by the bot, a regular trade occurs.
6.  **Pause:** Takes a random pause (according to `frequency_from`/`to`) before the next cycle.

`[SCREENSHOT: Example of an order book on the exchange showing orders placed by the bot]`

**Safety (`DeadWatcher`):** There is a separate `DeadWatcher` service that monitors bot activity. If a bot doesn't send a "I'm alive" signal for a certain period, `DeadWatcher` automatically cancels all orders of that bot on the exchange to prevent "stuck" orders in case of a failure.

## 4. Overview of the Main Screen (`Bots list`)

After logging into the interface, you will see the main screen with a list of all configured bots.

`[SCREENSHOT: Main screen with the list of bots (`Bots list`)]`

The table contains the following columns:

*   `Pair`: The trading pair managed by the bot (e.g., `BTC_USDC`).
*   `Exchange`: The external exchange from which the bot gets market data (`binance` or `kraken`).
*   `Status`: The current state of the bot:
    *   `ACTIVE`: The bot is running and placing orders.
    *   `INACTIVE`: The bot is stopped and inactive.
*   `Orders`: Minimum target number of orders (`min_orders`).
*   `Min/Max amount`: The range of the volume of a single order (`trade_amount_min`/`max`).
*   `Frequency (sec)`: The range of delay between cycles (`frequency_from`/`to`).
*   `Deviation (%)`: Pricing parameters (`price_factor`, `market_gap`).
*   `Market maker (%)`: Probability of a market order (`market_maker_order_probability`). **Recommended 0%.**
*   `Actions`: Available actions for each bot:
    *   **View (Blue eye icon):** Opens the detailed configuration and balance page for this bot (`Bot details`).
    *   **Stop/Start (Yellow pause icon / Green play icon):** Changes the bot's status between `ACTIVE` and `INACTIVE`. **Important:** When stopping (`INACTIVE`), the bot automatically **cancels all its active orders** on the exchange.
    *   **Delete (Red trash bin icon):** Completely removes the bot's configuration from the system. **Important:** When deleting, the bot also **cancels all its active orders**.

At the top of the screen, there is a `Create bot` button used to add a new bot.

## 5. Creating a New Bot

To add a new bot to manage a specific trading pair:

1.  Click the `Create bot` button on the main screen.
2.  Fill out the configuration form for the new bot.

`[SCREENSHOT: New bot creation form (`Create bot`)]`

Key parameters for configuration (for detailed description, see `BotConfig_Parameters.md`):

*   `Pair Name`: Enter the name of the trading pair exactly as it exists on the exchange (e.g., `BTC_USDT`, `ETH_BTC`).
*   `Exchange`: Select the external exchange (`binance` or `kraken`) from which the bot will get price data.
*   `Min Orders`: The target minimum number of orders on each side of the order book (buy/sell) that the bot will maintain.
*   `Min Amount` (`trade_amount_min`): Minimum volume of a single order.
*   `Max Amount` (`trade_amount_max`): Maximum volume of a single order. **Very important:** Make sure the bot's account in the exchange admin panel has enough funds to cover this maximum volume!
*   `Frequency From` (`frequency_from`): Minimum random delay (sec) between bot action cycles.
*   `Frequency To` (`frequency_to`): Maximum random delay (sec) between bot action cycles.
    *   **Why a range?** Using a frequency range (e.g., from 0 to 5 seconds) instead of a fixed value makes the bot's actions less predictable and robotic. This better simulates the natural, uneven activity of a real market where trades and order updates occur at different intervals.
*   `Price Factor` (`price_factor`): Determines the "width" of the price range (in %) within which the bot places orders relative to the base price. This parameter visually affects the density of orders in the order book: **a smaller value (e.g., 1-2%)** means orders will be concentrated closer to the current market price, creating a "denser" order book appearance. **A larger value (e.g., 10%)** spreads orders wider, making the order book visually "thinner".
*   `Market Gap` (`market_gap`): Guaranteed minimum percentage offset of the bot's orders from the real market prices of the external exchange. This is a **key safety parameter** as it guarantees that:
    *   **Arbitrage Protection:** The bot's orders will never be more favorable than prices on the external exchange, making instant arbitrage against the bot impossible.
    *   **Potential Benefit for the Exchange:** When a user trades with a bot's order, the exchange (through the bot) buys slightly cheaper or sells slightly more expensive than the current price on the external market.
*   `Market Maker Order Probability`: The probability (in %) that the bot will execute a market order instead of a limit order. **It is recommended to set this to 0% due to function instability.**

After filling in all the fields, click the button to save the configuration. The new bot will appear in the list with the status `INACTIVE`.

### 5.1. Recommended Starting Settings

The parameters listed below are **recommended starting values** for a new bot using **Binance** as a data source. These may need to be adjusted depending on the specific trading pair, its volatility, and the desired order book density:

*   `Exchange`: `binance`
*   `Min Orders`: `12`
*   `Min Amount`: `0.1` (or the minimum allowable volume for the pair)
*   `Max Amount`: `1` (or a volume that matches the pair's average activity and available balances)
*   `Frequency From`: `0`
*   `Frequency To`: `5`
*   `Price Factor`: `2` (for a relatively dense order book)
*   `Market Gap`: `1` (standard safe offset)
*   `Market Maker Order Probability`: `0`

**Always check for sufficient balances before launching the bot!**

## 6. Viewing and Managing an Existing Bot (`Bot details`)

To view the detailed configuration, balances, and manage an existing bot, click the **eye** icon in the list of bots. The `Bot details` page will open.

`[SCREENSHOT: Bot details page (`Bot details`)]`

On this page, you will see:

*   **Full configuration parameters** of the bot that you specified during creation or editing.
*   **Management buttons:**
    *   `Edit`: Opens the form for changing the parameters of this bot (similar to the creation form).
    *   `Enable`/`Disable`: Starts (`ACTIVE`) or stops (`INACTIVE`) the bot. Remember that stopping cancels orders.
    *   `Delete`: Removes the bot's configuration and cancels its orders.
*   **Bot Balances for This Pair (`Bot Balances`):** This section shows the **current real balances** in the bot's account **specifically for the two currencies that make up this trading pair**. The data is updated in real time.
    *   `Available`: Available for trading.
    *   `Frozen`: Locked in open orders (usually 0 if only the bot places orders).
    *   `Total`: Total currency balance.
    *   **Note:** Balances may change even if this specific bot is stopped, if other active bots are trading the same currencies.

`[SCREENSHOT: Bot editing form (after clicking `Edit`)]`

## 7. Monitoring Balances

In addition to the balances for a specific pair on the `Bot details` page, there is a separate tab/section `Bot Balances` (the name may differ slightly in the interface) that displays the **total current balances of all currencies** in the account used by all bots.

`[SCREENSHOT: Bot Balances tab/page (`Bot Balances`)]`

*   Use the `Refresh Balances` button to update the data.
*   The `Top Up Balance` form is intended **only for simulating** top-up during testing and **does not perform actual crediting of funds**.

**Critically Important: Maintaining Real Balances!**

For all bots to function correctly, it is **necessary to constantly maintain sufficient real balance** of all required currencies in the dedicated bot account. This is done through the **administrative panel of the exchange itself**, not through the bot management interface.

*   Ensure that the balance of each currency is sufficient to cover the `trade_amount_max` set for bots trading that currency.
*   Regularly check and top up balances through the exchange admin panel (development URL: `https://newexchanger.com/admin/payment/wallet/fill_request/list`, clarify demo URL). Insufficient balance will lead to bot failures and inability to place orders of the required volume.

## 8. Important Aspects

*   **Balance Requirements:** To reiterate: **always maintain sufficient real balance** in the exchange admin panel. This is the most important condition for stable operation.
*   **Configuration Saving:** Bot settings are saved in the `bots_config.json` file **inside the Docker container**. If the container is restarted without saving this file externally (via Docker Volumes), all changes made through the interface **will be lost**. Contact DevOps to configure configuration saving.
*   **Interface Security:** Currently, there is **no authentication** for accessing the bot management interface and its API. This poses a risk of unauthorized access. Authentication/authorization mechanisms need to be implemented.
*   **Profit Cycle:** The bot currently does not implement a full profit realization cycle (e.g., through hedging on an external exchange). Balance changes only occur locally as a result of trades with users.

We hope this guide helps you effectively manage the Depth-Bot. 