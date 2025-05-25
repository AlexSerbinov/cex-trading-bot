I understand your task. I will create a detailed map of port routing and folder mounting for both configurations: dev and demo. First, I will analyze all necessary files.

Read file: docker-compose-dev.yml

Read file: docker-compose-demo.yml

Read file: frontend/nginx-dev.conf

Read file: frontend/nginx-demo.conf

Read file: backend/Dockerfile

Read file: frontend/Dockerfile

Read file: router.php

Read file: index.php

Ran tool

Read file: src/api/BotManager.php

Read file: src/core/TradingBotManager.php

Read file: src/core/BotProcess.php

Read file: config/config.php

Read file: src/api/BotStorage.php

Based on the analyzed files, I will create a detailed map of port routing and folder mounting for both configurations: dev and demo.

## Routing map for DEV environment

### 1. Port Routing (DEV)

| Service | Host Port | Container Port | Description |
|--------|------------|-----------------|------|
| backend-dev | 5501 | 8080 | PHP server for backend, started via `php -S 0.0.0.0:8080 router.php` |
| frontend-dev | 5502 | 80 | Nginx server for frontend |

### 2. HTTP Request Routing (DEV)

| Request URL | Redirect | Description |
|------------|-----------------|------|
| http://localhost:5502/api/* | http://backend-dev:8080/* | API requests are redirected to the backend with the `/api` prefix removed |
| http://localhost:5502/swagger | http://backend-dev:8080/swagger-ui | Redirect to Swagger UI |
| http://localhost:5502/swagger-ui | http://backend-dev:8080/swagger-ui | Redirect to Swagger UI |
| http://localhost:5502/swagger.json | http://backend-dev:8080/swagger.json | Get Swagger specification |
| http://localhost:5502/* | Static files from `/usr/share/nginx/html` | Serving frontend static files |

### 3. Folder Mounting (DEV)

#### Backend-dev:
| Host Path | Container Path | Description |
|------------|-----------------|------|
| ./config | /app/config | Configuration files |
| ./data | /app/data | Data, logs, PID files |
| ./src | /app/src | Source code |
| ./router.php | /app/router.php | Routing file for PHP server |
| ./index.php | /app/index.php | Main PHP file |
| ./public | /app/public | Public files (Swagger UI) |

#### Frontend-dev:
| Host Path | Container Path | Description |
|------------|-----------------|------|
| ./frontend | /usr/share/nginx/html | Frontend files |
| ./frontend/nginx-dev.conf | /etc/nginx/conf.d/default.conf | Nginx configuration for dev environment |

### 4. Data Structure (DEV)

| Path | Description |
|------|------|
| ./config/bots_config.json | Bot configuration |
| ./data/logs/bot.log | Bot logs |
| ./data/logs/router.log | Router logs |
| ./data/pids/*.pid | PID files for bot processes |

## Routing map for DEMO environment

### 1. Port Routing (DEMO)

| Service | Host Port | Container Port | Description |
|--------|------------|-----------------|------|
| backend-demo | 6501 | 8080 | PHP server for backend, started via `php -S 0.0.0.0:8080 router.php` |
| frontend-demo | 6502 | 80 | Nginx server for frontend |

### 2. HTTP Request Routing (DEMO)

| Request URL | Redirect | Description |
|------------|-----------------|------|
| http://localhost:6502/api/* | http://backend-demo:8080/* | API requests are redirected to the backend with the `/api` prefix removed |
| http://localhost:6502/swagger | http://backend-demo:8080/swagger-ui | Redirect to Swagger UI |
| http://localhost:6502/swagger-ui | http://backend-demo:8080/swagger-ui | Redirect to Swagger UI |
| http://localhost:6502/swagger.json | http://backend-demo:8080/swagger.json | Get Swagger specification |
| http://localhost:6502/* | Static files from `/usr/share/nginx/html` | Serving frontend static files |

### 3. Folder Mounting (DEMO)

#### Backend-demo:
| Host Path | Container Path | Description |
|------------|-----------------|------|
| ./config | /app/config | Configuration files |
| ./data | /app/data | Data, logs, PID files |
| ./src | /app/src | Source code |
| ./router.php | /app/router.php | Routing file for PHP server |
| ./index.php | /app/index.php | Main PHP file |
| ./public | /app/public | Public files (Swagger UI) |

#### Frontend-demo:
| Host Path | Container Path | Description |
|------------|-----------------|------|
| ./frontend | /usr/share/nginx/html | Frontend files |
| ./frontend/nginx-demo.conf | /etc/nginx/conf.d/default.conf | Nginx configuration for demo environment |

### 4. Data Structure (DEMO)

| Path | Description |
|------|------|
| ./config/bots_config.json | Bot configuration |
| ./data/logs/bot.log | Bot logs |
| ./data/logs/router.log | Router logs |
| ./data/pids/*.pid | PID files for bot processes |

## Detailed API Endpoints Map

| Method | Endpoint | Description |
|-------|----------|------|
| GET | /api/bots | Get a list of all bots |
| GET | /api/bots/{id} | Get bot by ID |
| POST | /api/bots | Create a new bot |
| PUT | /api/bots/{id} | Update bot |
| DELETE | /api/bots/{id} | Delete bot |
| PUT | /api/bots/{id}/enable | Activate bot |
| PUT | /api/bots/{id}/disable | Deactivate bot |
| GET | /api/exchanges | Get a list of available exchanges |
| GET | /api/pairs | Get a list of available pairs |

## System Operation Scheme

1. **Frontend** (Nginx) receives user requests:
   - Static files are served directly from `/usr/share/nginx/html`
   - API requests are forwarded to the backend via the internal Docker network

2. **Backend** (PHP server) processes requests:
   - API requests are handled via `index.php`
   - Static files for Swagger UI are served from `/app/public/docs`
   - Bot configuration is stored in `/app/config/bots_config.json`

3. **Bot Processes**:
   - Started via `TradingBotManager.php`
   - Each bot runs in a separate process
   - PID files are stored in `/app/data/pids`
   - Logs are written to `/app/data/logs/bot.log`

4. **Configuration**:
   - Main settings in `config/config.php`
   - Bot configuration in `config/bots_config.json`
   - Nginx configuration in `frontend/nginx-dev.conf` or `frontend/nginx-demo.conf`

5. **Docker Networks**:
   - DEV: `app-dev-net` - internal network for communication between containers
   - DEMO: `app-demo-net` - internal network for communication between containers

## Mounting Features

It is important to note that in both configurations (DEV and DEMO), the `./data` directory is mounted as a volume, which means:
1. Changes in files on the host are reflected in the container
2. Changes in files in the container are reflected on the host
3. Deleting files on the host leads to their disappearance in the container

This explains the problem you described when deleting the `config/bots_config.json` file on the local machine leads to its disappearance in the container.

This detailed routing map should help you better understand how your system is set up and how different components interact.
