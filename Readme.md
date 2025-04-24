# CEX Trading Bot - Portfolio Project

## Overview

### What is the Depth-Bot?

The Depth-Bot is a smart tool built to make our cryptocurrency exchange look busy and exciting by keeping the order book full. Its main job is to make the exchange seem active, even if no one is trading yet. It pretends to trade, creating the feeling of a lively market where people are buying and selling. This builds trust with users and gets them interested in joining in.

### Why Do We Need It?

When an exchange is new or quiet, an empty order book can scare traders away. The Depth-Bot fixes this by:

- **Filling the Order Book**: It adds and manages buy and sell orders for different pairs (like BTC_USDT or ETH_BTC) so the order book always has activity.
- **Making It Look Busy**: When users log in and see a full order book, they think trading is happening—someone out there is active. This is key to earning trust and bringing in real traders.
- **Helping Us Grow**: Right now, it fakes trades, but later it can help with real trading. It sets us up to increase liquidity and make the market more real.

### Main Goal

The bot's big task is to make the exchange look alive and thriving. It does this by smartly handling orders using settings like price changes, order sizes, and timing. This keeps the order book looking real and active. In the future, it could switch from pretending to actually supporting real trades, making our exchange even better.

### Future Profit Potential

Later on, we can use the Depth-Bot to make money too! Here's how it could work:
- **Copying Binance**: We take the order book from Binance as a guide. If a user places an order cheaper than Binance (like a lower sell price), our Trade Server grabs it fast and fills it.
- **Making Profit**: After filling the cheap order, we can sell it right away on Binance at a higher price. This locks in a profit for us!
- **Or Keep It Flexible**: We don't always have to cash out right away. If the bot has a pool of coins—like a balance of 10 different coins—we can just grow that pool. Instead of turning everything into one final coin, we earn by trading within the pool. Profit comes from the whole mix, not just one step.



## 1. Setup

This section outlines how to set up and run the Trading Bot project. The primary deployment method uses Docker to launch two environments—**Development** and **Demo**—in isolated containers. Alternatively, you can run the project locally for development purposes.

### Running with Docker
The project is designed to run in Docker, providing a consistent and scalable setup for both **Development** and **Demo** environments. To deploy, use the `rebuild-all.sh` script located in the `scripts` folder. This script:
1. **Stops Existing Containers**: Terminates any running Docker containers from previous runs to avoid conflicts.
2. **Builds and Starts Containers**: Rebuilds and launches containers for both environments using `docker-compose-dev.yml` and `docker-compose-demo.yml`.

Run the script:
```bash
./scripts/rebuild-all.sh
```

- **Development Environment**: 
  - Backend (API and Bot Manager) runs on port `5501`.
  - Frontend runs on port `5502`.
- **Demo Environment**: 
  - Backend (API and Bot Manager) runs on port `6501`.
  - Frontend runs on port `6502`.

To stop the Docker containers, use:
```bash
./scripts/stop-docker.sh
```
This command gracefully shuts down all containers.

### Verifying URLs and Balance
The Trading Bot relies on external trade server URLs and sufficient bot balance for correct operation. Check `URLs.txt` for environment-specific URLs:
- **Development**:
  - Frontend: `dev.newexchanger.com`
  - Trade Server: `http://164.68.117.90:18080`
  - Admin: `https://newexchanger.com/admin/payment/wallet/fill_request/list`
  - DepthBot Swagger: `http://164.68.117.90:5501/`
  - DepthBot Frontend: `http://164.68.117.90:5502/`
- **Demo**:
  - Frontend: `new.newexchanger.com`
  - Trade Server: `http://:195.7.7.93:18080`
  - Admin: `https://dev.api.newexchanger.com/admin/payment/wallet/fill_request/list`
  - DepthBot Swagger: `http://164.68.117.90:6501/`
  - DepthBot Frontend: `http://164.68.117.90:6502/`

**Balance Requirement**: Before running bots, ensure sufficient funds:
1. Visit the admin URL for your environment (e.g., `https://newexchanger.com/admin/payment/wallet/fill_request/list` for Development).
2. Verify or top up the bot's balance via the admin interface.
3. Insufficient balance will prevent bots from placing orders at the configured amounts, leading to incorrect behavior.

### Running Locally (For Development Purposes)
For development and testing, you can run the project locally without Docker using the `clean_and_run_local.sh` script in the `scripts` folder. This script:
1. **Cleans Previous Processes**: Removes stale PID files from `data/pids/` and terminates lingering bot processes.
2. **Starts New Processes**: Launches the Trading Bot Manager and services in the background.

Run the script:
```bash
./scripts/clean_and_run_local.sh
```

**Note**: If you interrupt the script with `Ctrl+C`, processes will continue running in the background. To stop them completely, use:
```bash
./scripts/stop_all.sh
```
This ensures all background processes and PID files are cleaned up.

### Additional Notes
- **PID and Lock Files**: The `data/pids/` and `data/locks/` directories manage process IDs and locks to prevent duplicate instances. These are handled automatically but may need manual cleanup after crashes.
- **Configuration**: Bot settings are stored in `config/bots_config.json`. Modify trading parameters (e.g., `trade_amount_min`, `price_factor`) before starting, if needed.

---

## 2. Environments

The project supports two distinct environments when deployed via Docker:
- **Development**: Used for testing and debugging.
  - **Ports**: Backend on `5501`, Frontend on `5502`.
  - **Trade Server URL**: `http://195.7.7.93:18080`.
  - **Nginx Config**: Uses `frontend/nginx-dev.conf` for routing API calls to `/api` and Swagger to `/swagger-ui`.
- **Demo**: Mimics a production-like setup for demonstration purposes.
  - **Ports**: Backend on `6501`, Frontend on `6502`.
  - **Trade Server URL**: `http://164.68.117.90:18080`.
  - **Nginx Config**: Uses `frontend/nginx-demo.conf` with similar routing.

The backend in both environments includes the API (powered by Apache with `api/.htaccess`) and the Bot Manager, while the frontend is served via Nginx. The differences in trade server URLs and ports allow for isolated testing and deployment scenarios.

---


## 3. Scripts and Tools

The `scripts` folder contains utilities for managing the project:
- **`rebuild-all.sh`**: Stops, rebuilds, and starts Docker containers for both Development and Demo environments.
- **`clean_and_run_local.sh`**: Cleans up local PIDs and launches bots for development.
- **`stop-docker.sh`**: Stops all Docker containers.
- **`stop_all.sh`**: Terminates local background processes and removes PID files.

**Monitoring Tool**:
- **`utils/OrderBookConsoleTool.php`**: A PHP script to display real-time order books in the console. Run it with:
  ```bash
  php utils/OrderBookConsoleTool.php -<pair>
  ```
  Example: `php utils/OrderBookConsoleTool.php -ETH_USDC`. It fetches data from the trade server every 500ms, showing bids (green) and asks (red).

A TypeScript version (`OrderBookConsoleTool.ts`) is also available for Node.js environments.


## Architecture

This section provides an in-depth look at the architecture of the Trading Bot project, detailing the technologies, file structure, Docker packaging, and how services are deployed and accessed. The system is designed to simulate trading activity on a cryptocurrency exchange, with a modular structure supporting both Development and Demo environments.

### Technologies Used

The project leverages a modern tech stack for robustness and scalability:
- **PHP 8.1**: Powers the backend logic, including the Trading Bot Manager (`src/core/TradingBot.php`), API (`src/api/api.php`), and configuration management (`config/config.php`).
- **Apache**: Serves the PHP-based API with URL rewriting via `.htaccess`.
- **Nginx**: Acts as a reverse proxy and static file server for the frontend, configured via `nginx-dev.conf` and `nginx-demo.conf`.
- **Docker**: Containers encapsulate the application, ensuring consistency across environments. Managed with `docker-compose-dev.yml` and `docker-compose-demo.yml`.
- **Composer**: Manages PHP dependencies (e.g., `guzzlehttp/guzzle` for HTTP requests, `symfony/console` for CLI tools, `react/event-loop` for asynchronous operations).
- **TypeScript/Node.js**: Optional, used in `utils/OrderBookConsoleTool.ts` for an alternative order book monitoring tool.
- **JSON**: Configuration files (e.g., `bots_config.json`) store bot settings.
- **Bash**: Shell scripts in the `scripts` folder automate setup, deployment, and cleanup.

### Docker Packaging

The project is packaged into Docker containers for both Development and Demo environments, each with distinct configurations:
- **Base Images**:
  - **PHP-FPM**: `api/Dockerfile` uses `php:8.1-fpm` with extensions (`pdo`, `mbstring`, etc.) and Composer-installed dependencies.
  - **Nginx**: `frontend/Dockerfile` uses `nginx:alpine` for lightweight frontend serving.
- **Compose Files**:
  - **`docker-compose-dev.yml`**: Defines services for Development:
    - `app-dev`: PHP-FPM backend on port `5501`.
    - `nginx-dev`: Nginx frontend on port `5502`.
  - **`docker-compose-demo.yml`**: Defines services for Demo:
    - `app-demo`: PHP-FPM backend on port `6501`.
    - `nginx-demo`: Nginx frontend on port `6502`.
- **Volumes**: 
  - Mounts local directories (`api/`, `frontend/`, `public/`) into containers for live code updates.
  - `data/` directory persists logs, PIDs, and locks.
- **Networking**: Each environment runs on an isolated Docker network, with ports mapped to the host (e.g., `5501:80` for Development backend).

### File Structure and Purpose

Here's a breakdown of key files and directories:

- **`api/`** (continued):
  - **`.htaccess`**: Configures Apache to rewrite URLs, directing all requests to `api.php` for routing. Example rules include rewriting `/swagger-ui` to serve Swagger documentation and `/api/*` for API endpoints.
  - **`api.php`**: The central API entry point, handling requests for bot management, order book data, and trading operations. It integrates with `TradingBot.php` for core logic.
- **`config/`**:
  - **`bots_config.json`**: Stores bot configurations (e.g., `trade_amount_min`, `price_factor`) for each trading pair. Loaded by `config.php` to initialize bots.
  - **`config.php`**: A PHP class (`Config`) with methods to manage bot settings (`addBot`, `updateBot`, `deleteBot`) and interact with `bots_config.json`.
- **`data/`**:
  - **`locks/`**: Stores lock files to prevent duplicate bot instances.
  - **`logs/`**: Contains log files generated by `Logger.php` for debugging and monitoring.
  - **`pids/`**: Holds process ID files for background bot processes, managed by scripts like `clean_and_run_local.sh`.
- **`frontend/`**:
  - **`Dockerfile`**: Builds the Nginx container, copying static files from `public/` and Nginx configs (`nginx-dev.conf` or `nginx-demo.conf`).
  - **`nginx-dev.conf`**: Nginx configuration for Development, proxying `/api` to the backend (`http://app-dev:80`) and serving static files from `/swagger-ui`.
  - **`nginx-demo.conf`**: Similar to `nginx-dev.conf`, but tailored for Demo, proxying to `http://app-demo:80`.
- **`public/`**:
  - **`docs/swagger.json`**: Defines the Swagger API specification, detailing endpoints like `/api/orderbook` and `/api/bot/status`.
  - **`index.php`**: A minimal entry point for the frontend, often redirecting to Swagger or serving static content.
- **`scripts/`**:
  - **`rebuild-all.sh`**: Stops existing containers, then builds and starts both Development and Demo environments using `docker-compose`.
  - **`clean_and_run_local.sh`**: Cleans PIDs and launches bots locally for development.
  - **`stop-docker.sh`**: Stops all Docker containers.
  - **`stop_all.sh`**: Terminates local bot processes and removes PID files.
- **`src/`**:
  - **`core/TradingBot.php`**: The heart of the bot logic, managing order books (`initializeOrderBook`), placing orders (`placeLimitOrder`, `placeMarketOrder`), and maintaining activity (`maintainOrders`).
  - **`api/api.php`**: Implements the RESTful API, exposing endpoints for external control and monitoring.
  - **`utils/Logger.php`**: A utility class for logging bot activity and errors to `data/logs/`.
- **`utils/`**:
  - **`OrderBookConsoleTool.php`**: A PHP CLI tool to monitor order books in real-time, fetching data every 500ms and displaying bids/asks in color.
  - **`OrderBookConsoleTool.ts`**: A TypeScript alternative for Node.js, offering similar functionality.

### Swagger Integration

The project includes Swagger for API documentation and testing:
- **Location**: The Swagger spec is defined in `public/docs/swagger.json`.
- **Endpoints**: Examples include:
  - `GET /api/orderbook`: Retrieves the current order book for a trading pair.
  - `POST /api/bot/start`: Starts a bot instance for a specified pair.
- **Mounting**: 
  - In the Nginx container, `/swagger-ui` is mapped to serve static Swagger UI files from `public/`.
  - Apache rewrites `/swagger-ui` requests to load `swagger.json` via `.htaccess`.
- **Access**:
  - **Development**: `http://164.68.117.90:5501/swagger-ui`.
  - **Demo**: `http://164.68.117.90:6501/swagger-ui`.
- **Purpose**: Provides a user-friendly interface to explore and test API endpoints, reflecting the bot's capabilities.

