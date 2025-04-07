
## Documentation: Trading Bot Project

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
2. Verify or top up the bot’s balance via the admin interface.
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
