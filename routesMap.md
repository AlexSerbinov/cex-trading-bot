Я розумію ваше завдання. Створю для вас детальну карту маршрутизації портів та монтування папок для обох конфігурацій: dev і demo. Спочатку проаналізую всі необхідні файли.

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
На основі проаналізованих файлів, я створю детальну карту маршрутизації портів та монтування папок для обох конфігурацій: dev і demo.

## Карта маршрутизації для DEV середовища

### 1. Маршрутизація портів (DEV)

| Сервіс | Порт хоста | Порт контейнера | Опис |
|--------|------------|-----------------|------|
| backend-dev | 5501 | 8080 | PHP-сервер для бекенду, запускається через `php -S 0.0.0.0:8080 router.php` |
| frontend-dev | 5502 | 80 | Nginx-сервер для фронтенду |

### 2. Маршрутизація HTTP-запитів (DEV)

| URL запиту | Перенаправлення | Опис |
|------------|-----------------|------|
| http://localhost:5502/api/* | http://backend-dev:8080/* | Запити до API перенаправляються на бекенд з видаленням префіксу `/api` |
| http://localhost:5502/swagger | http://backend-dev:8080/swagger-ui | Перенаправлення на Swagger UI |
| http://localhost:5502/swagger-ui | http://backend-dev:8080/swagger-ui | Перенаправлення на Swagger UI |
| http://localhost:5502/swagger.json | http://backend-dev:8080/swagger.json | Отримання Swagger-специфікації |
| http://localhost:5502/* | Статичні файли з `/usr/share/nginx/html` | Обслуговування статичних файлів фронтенду |

### 3. Монтування папок (DEV)

#### Backend-dev:
| Шлях хоста | Шлях контейнера | Опис |
|------------|-----------------|------|
| ./config | /app/config | Конфігураційні файли |
| ./data | /app/data | Дані, логи, PID-файли |
| ./src | /app/src | Вихідний код |
| ./router.php | /app/router.php | Файл маршрутизації для PHP-сервера |
| ./index.php | /app/index.php | Головний файл PHP |
| ./public | /app/public | Публічні файли (Swagger UI) |

#### Frontend-dev:
| Шлях хоста | Шлях контейнера | Опис |
|------------|-----------------|------|
| ./frontend | /usr/share/nginx/html | Файли фронтенду |
| ./frontend/nginx-dev.conf | /etc/nginx/conf.d/default.conf | Конфігурація Nginx для dev середовища |

### 4. Структура даних (DEV)

| Шлях | Опис |
|------|------|
| ./config/bots_config.json | Конфігурація ботів |
| ./data/logs/bot.log | Логи ботів |
| ./data/logs/router.log | Логи маршрутизатора |
| ./data/pids/*.pid | PID-файли для процесів ботів |

## Карта маршрутизації для DEMO середовища

### 1. Маршрутизація портів (DEMO)

| Сервіс | Порт хоста | Порт контейнера | Опис |
|--------|------------|-----------------|------|
| backend-demo | 6501 | 8080 | PHP-сервер для бекенду, запускається через `php -S 0.0.0.0:8080 router.php` |
| frontend-demo | 6502 | 80 | Nginx-сервер для фронтенду |

### 2. Маршрутизація HTTP-запитів (DEMO)

| URL запиту | Перенаправлення | Опис |
|------------|-----------------|------|
| http://localhost:6502/api/* | http://backend-demo:8080/* | Запити до API перенаправляються на бекенд з видаленням префіксу `/api` |
| http://localhost:6502/swagger | http://backend-demo:8080/swagger-ui | Перенаправлення на Swagger UI |
| http://localhost:6502/swagger-ui | http://backend-demo:8080/swagger-ui | Перенаправлення на Swagger UI |
| http://localhost:6502/swagger.json | http://backend-demo:8080/swagger.json | Отримання Swagger-специфікації |
| http://localhost:6502/* | Статичні файли з `/usr/share/nginx/html` | Обслуговування статичних файлів фронтенду |

### 3. Монтування папок (DEMO)

#### Backend-demo:
| Шлях хоста | Шлях контейнера | Опис |
|------------|-----------------|------|
| ./config | /app/config | Конфігураційні файли |
| ./data | /app/data | Дані, логи, PID-файли |
| ./src | /app/src | Вихідний код |
| ./router.php | /app/router.php | Файл маршрутизації для PHP-сервера |
| ./index.php | /app/index.php | Головний файл PHP |
| ./public | /app/public | Публічні файли (Swagger UI) |

#### Frontend-demo:
| Шлях хоста | Шлях контейнера | Опис |
|------------|-----------------|------|
| ./frontend | /usr/share/nginx/html | Файли фронтенду |
| ./frontend/nginx-demo.conf | /etc/nginx/conf.d/default.conf | Конфігурація Nginx для demo середовища |

### 4. Структура даних (DEMO)

| Шлях | Опис |
|------|------|
| ./config/bots_config.json | Конфігурація ботів |
| ./data/logs/bot.log | Логи ботів |
| ./data/logs/router.log | Логи маршрутизатора |
| ./data/pids/*.pid | PID-файли для процесів ботів |

## Детальна карта API-ендпоінтів

| Метод | Ендпоінт | Опис |
|-------|----------|------|
| GET | /api/bots | Отримання списку всіх ботів |
| GET | /api/bots/{id} | Отримання бота за ID |
| POST | /api/bots | Створення нового бота |
| PUT | /api/bots/{id} | Оновлення бота |
| DELETE | /api/bots/{id} | Видалення бота |
| PUT | /api/bots/{id}/enable | Активація бота |
| PUT | /api/bots/{id}/disable | Деактивація бота |
| GET | /api/exchanges | Отримання списку доступних бірж |
| GET | /api/pairs | Отримання списку доступних пар |

## Схема роботи системи

1. **Фронтенд** (Nginx) отримує запити від користувача:
   - Статичні файли обслуговуються напряму з `/usr/share/nginx/html`
   - Запити до API перенаправляються на бекенд через внутрішню мережу Docker

2. **Бекенд** (PHP-сервер) обробляє запити:
   - API-запити обробляються через `index.php`
   - Статичні файли для Swagger UI обслуговуються з `/app/public/docs`
   - Конфігурація ботів зберігається в `/app/config/bots_config.json`

3. **Процеси ботів**:
   - Запускаються через `TradingBotManager.php`
   - Кожен бот працює в окремому процесі
   - PID-файли зберігаються в `/app/data/pids`
   - Логи записуються в `/app/data/logs/bot.log`

4. **Конфігурація**:
   - Основні налаштування в `config/config.php`
   - Конфігурація ботів в `config/bots_config.json`
   - Конфігурація Nginx в `frontend/nginx-dev.conf` або `frontend/nginx-demo.conf`

5. **Мережі Docker**:
   - DEV: `app-dev-net` - внутрішня мережа для комунікації між контейнерами
   - DEMO: `app-demo-net` - внутрішня мережа для комунікації між контейнерами

## Особливості монтування

Важливо відзначити, що в обох конфігураціях (DEV і DEMO) директорія `./data` монтується як volume, що означає:
1. Зміни в файлах на хості відображаються в контейнері
2. Зміни в файлах в контейнері відображаються на хості
3. Видалення файлів на хості призводить до їх зникнення в контейнері

Це пояснює проблему, яку ви описали, коли видалення файлу `config/bots_config.json` на локальній машині призводить до його зникнення в контейнері.

Ця детальна карта маршрутизації повинна допомогти вам краще зрозуміти, як налаштована ваша система і як взаємодіють різні компоненти.
