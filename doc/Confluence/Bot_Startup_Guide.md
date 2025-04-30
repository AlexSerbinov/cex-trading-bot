## Trading Bot Startup and Operation Guide

This document describes the procedures for starting, stopping, configuring, and monitoring the trading bot and related services.

### 1. Prerequisites

*   **Balances:** Before launching or actively running a bot for a trading pair, **ensure** sufficient funds are available in **both** currencies of the pair. 
    *   Check balances within the platform's **Admin Panel** (refer to specific Admin URLs if available, see section 1.1 for typical base URLs) or via the **"Bot Balances" page** in the Trading Bot Admin UI (see section 6).
    *   The balance must cover potential orders based on the `trade_amount_max` parameter ([see BotConfig_Parameters](./BotConfig_Parameters.md)). Insufficient balance can lead to smaller-than-configured emergency orders or operational failures.
    *   For detailed recommendations on required balance amounts, see [BotConfig_BestPractices](./BotConfig_BestPractices.md).
*   **Dependencies:** Ensure that all necessary PHP dependencies (via `composer install`) and Node.js dependencies (via `npm install`) are installed.

### 1.1. Environment URLs (Deployed Servers)

Below are typical URLs for accessing the deployed Dev and Demo environments:

*   **Development Environment (`dev`):**
    *   **Main Frontend:** `https://dev.newexchanger.com`
    *   **TradeServer API:** `http://164.68.117.90:18080`
    *   **Trading Bot API / Swagger UI:** `http://164.68.117.90:5501`
    *   **Trading Bot Frontend:** `http://164.68.117.90:6501`

*   **Demo Environment (`demo`):**
    *   **Main Frontend:** `https://new.newexchanger.com`
    *   **TradeServer API:** `http://195.7.7.93:18080`
    *   **Trading Bot API / Swagger UI:** `http://164.68.117.90:5502`
    *   **Trading Bot Frontend:** `http://164.68.117.90:6502`

*Note:* These URLs may change. Always confirm the current addresses with the responsible personnel.

### 2. Running via Docker (Recommended for Dev/Demo)

Docker allows running isolated environments for development (`dev`) and demonstration (`demo`) **locally** on your machine.

**2.1. Starting the System:**

*   **Main Script:** `scripts/rebuild-all.sh`
*   **What it does:**
    1.  Stops and removes previous containers (if they exist).
    2.  Prepares configuration files using `scripts/prepare-configs.sh`.
    3.  Builds (or rebuilds) Docker images.
    4.  Starts containers for `dev` and `demo` environments in the background using `docker-compose-dev.yml` and `docker-compose-demo.yml`.

*   **Command:**
    ```bash
    cd /path/to/project/trading-bot
    ./scripts/rebuild-all.sh
    ```

**2.2. Available Local URLs and Ports (after Docker start):**

After a successful start via `rebuild-all.sh`, the following services will be available **on your local machine**:

*   **Local Development Environment (`dev`):**
    *   **Trading Bot Frontend:** `http://localhost:6501`
    *   **Trading Bot API / Swagger UI:** `http://localhost:5501`
*   **Local Demo Environment (`demo`):**
    *   **Trading Bot Frontend:** `http://localhost:6502`
    *   **Trading Bot API / Swagger UI:** `http://localhost:5502`

*Important:* These `localhost` addresses are for accessing services running in Docker on your machine and differ from the deployed server URLs (see section 1.1).

**2.3. Stopping Docker Containers:**

*   **Main Script:** `scripts/stop-docker.sh`
*   **What it does:** Stops and removes containers defined in `docker-compose-dev.yml` and `docker-compose-demo.yml`.
*   **Command:**
    ```bash
    cd /path/to/project/trading-bot
    ./scripts/stop-docker.sh
    ```
    *Note:* The `scripts/stop_all.sh` script also calls `stop-docker.sh` after stopping local bots.

### 3. Local Run (For debugging a specific bot)

Local run allows starting the bot process directly on your machine, outside of Docker. **This is primarily intended for debugging.**

**3.1. Starting the System:**

*   **Main Script:** `scripts/clean_and_run_local.sh`
*   **What it does:**
    1.  Performs cleanup: removes old logs and PID files for the `local` environment.
    2.  Prepares configuration files (`prepare-configs.sh`).
    3.  Attempts to stop any previous local bot processes (`stop_bots.php`).
    4.  Starts the main bot process (`src/run.php`) in the background using `nohup`.

*   **Command:**
    ```bash
    cd /path/to/project/trading-bot
    ./scripts/clean_and_run_local.sh
    ```

**3.2. How it Works:**

*   A **background PHP process** is created during local startup.
*   The Process ID (PID) of this process is stored in a file within the `data/pids/` directory. This is necessary for correct stopping later.

**3.3. Stopping Local Processes:**

*   **Main Script:** `scripts/stop_all.sh` (recommended) or `scripts/stop_bots.php` (only for local bots).
*   **What `stop_bots.php` does:** Reads PID files from `data/pids/` and sends a termination signal (SIGTERM) to the corresponding PHP processes.
*   **What `stop_all.sh` does:** First calls `stop_bots.php` to stop local processes, then calls `stop-docker.sh` to stop Docker containers.

*   **Command (Full Stop):**
    ```bash
    cd /path/to/project/trading-bot
    ./scripts/stop_all.sh
    ```

**3.4. IMPORTANT: Correctly Stopping Local Bots**

*   **Problem:** If you start the bot locally (`clean_and_run_local.sh`) and then simply close the terminal or press `Ctrl+C` for the main script, **the background PHP bot process will continue running**. It will not stop automatically.
*   **Consequences:** The presence of "stray" background processes can lead to:
    *   Unpredictable behavior on subsequent launches.
    *   Port or resource conflicts.
    *   Debugging difficulties.
    *   Incorrect log entries.
*   **Solution:** **Always** use the `scripts/stop_all.sh` or `scripts/stop_bots.php` script to stop locally running bots. This ensures that the corresponding PIDs are found, and the processes are terminated correctly.

### 4. Configuration

System settings are divided into two main files:

*   **`config/config.php`:**
    *   The main application configuration file.
    *   Contains settings for the database, paths, external APIs (e.g., Binance), logging parameters, server settings, and other global parameters.
*   **`config/bots_config.json`:**
    *   Configuration specifically for trading bot instances.
    *   Contains an array of objects, where each object defines parameters for a specific trading pair (e.g., `BTC/USDT`).
    *   Includes parameters like `trade_amount_min`, `trade_amount_max`, `frequency_from`, `frequency_to`, `price_factor`, `market_gap`, `min_orders`, etc. See [BotConfig_Parameters](./BotConfig_Parameters.md) and [BotConfig_BestPractices](./BotConfig_BestPractices.md) for detailed descriptions.
    *   **Source of Truth and Updates:** This JSON file is the **single source of truth** for bot configurations *at startup*.
        *   If an API exists to change bot settings (e.g., via the Frontend UI), these changes directly modify the configuration **used by the currently running bot process or within the live container**. 
        *   To manually run a bot with specific parameters, you can directly edit this file *before* launching the bot process or building/restarting the container.
        *   **Important on Restart:** When you (re)start the system using scripts (`rebuild-all.sh`, `clean_and_run_local.sh`), the configuration is typically initialized based on the `config/bots_config.json` file **as it exists on the host machine at that moment**. This means that changes made via API or UI to a running instance **might be lost** if the container/process is stopped and restarted using the main scripts, unless the updated configuration was also persisted back to the host machine's `config/bots_config.json` file.

*   **Config Preparation:** The `scripts/prepare-configs.sh` script (called by other startup scripts) may perform additional actions like copying or modifying configs for different environments, if specified by its logic.

### 5. Logging

The system uses a centralized logging mechanism.

*   **Location:** All logs are stored in the `data/logs/` directory relative to the project root.
*   **Environment Separation:** Within `data/logs/`, there are subdirectories for each environment:
    *   `data/logs/dev/`: Logs from the `dev` environment Docker containers.
    *   `data/logs/demo/`: Logs from the `demo` environment Docker containers.
    *   `data/logs/local/`: Logs from locally run bot processes.
    *   *Important:* When Docker containers are run, the corresponding directories (`dev` or `demo`) are mounted inside the containers, so logs generated within the container are stored on your local machine.
*   **Log Rotation:**
    *   The system uses a log rotation mechanism to prevent log files from growing excessively large.
    *   The `src/helpers/LogManager.php` and `src/helpers/LogRotator.php` classes are responsible for this process.
    *   When a log file reaches a certain size (defined in `config/config.php`), it is automatically renamed (e.g., by adding a date or index), and a new empty log file is created for subsequent entries. Old files may also be archived (e.g., compressed into `.gz`).

### 6. Frontend Management Interface

The system provides a web-based frontend for managing and monitoring bots. 

*   **Access URLs:**
    *   Development (Local Docker): `http://localhost:6501`
    *   Demo (Local Docker): `http://localhost:6502`
    *   Development (Deployed): `http://164.68.117.90:6501` (Verify current URL)
    *   Demo (Deployed): `http://164.68.117.90:6502` (Verify current URL)
*   **Purpose:** Currently serves as a tool for visualizing bot status, managing configurations, and viewing balances. It interacts with the bot system via its API.
*   **Future Integration:** Intended for potential integration into a broader PHP-based admin panel.

**6.1. Bots List Page**

*   **Overview:** Displays a table summarizing all configured bots.
    *   `![Bots List Screenshot](placeholder_bots_list.png)`
*   **Columns:** Includes Pair, Exchange, Status (e.g., ACTIVE), Orders (Current/Target), Min/Max amount, Frequency, Deviation (%), Market maker (%), etc.
*   **Actions per Bot:**
    *   **Watch (Blue Eye Icon):** Opens the Bot Details page (see below).
    *   **Disable/Enable (Yellow Pause/Play Icon):** Toggles the bot's status between ACTIVE and INACTIVE. Disabling a bot stops its trading activity and cancels its open orders.
    *   **Delete (Red Trash Icon):** Permanently removes the bot configuration. This also cancels the bot's open orders.
*   **Create Bot Button:** Located at the top-right, opens the form for adding a new bot configuration.

**6.2. Bot Details / Edit Page**

*   **Overview:** Accessed via the "Watch" icon or when editing. Shows detailed information for a specific bot and allows editing its parameters.
    *   `![Bot Details Screenshot](placeholder_bot_details.png)`
*   **Information Displayed:** Shows all configured parameters (Trading Pair, Exchange, Status, Amounts, Frequency, Deviation, Gap, etc.), Created/Updated timestamps, and current Bot Balances.
*   **Editing:** The "Edit" button opens a form similar to the Create Bot page, pre-filled with the current bot's settings.
    *   `![Create/Edit Bot Screenshot](placeholder_edit_bot.png)`
*   **Saving:** The "Save" button submits changes via the API, updating the running bot's configuration (but see the note about persistence on restart in section 4).

**6.3. Bot Balances Page**

*   **Overview:** Provides a consolidated view of the balances available to the trading bot system.
    *   `![Bot Balances Screenshot](placeholder_bot_balances.png)`
*   **Functionality:**
    *   Displays current Available, Frozen, and Total balances for each currency.
    *   Allows refreshing the balance information.
    *   Includes a form to **Top Up Balance** for specific currencies (interacts with the Admin Panel backend or a similar mechanism).
*   **Importance:** Crucial for ensuring bots have sufficient funds to operate correctly (see section 1).

**6.4. Security Note**

*   **Current Status:** The frontend interface and its underlying API currently lack robust authorization mechanisms.
*   **Risk:** Access to the URLs could potentially allow unauthorized users to view bot status or modify configurations.
*   **Recommendation:** Implementing proper authentication and authorization (e.g., JWT, session-based auth linked to an admin panel) is highly recommended for production environments. 