FROM php:8.1-cli

WORKDIR /app

# Встановлення залежностей та розширення PCNTL
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pcntl

# Додавання підтримки веб-сервера для Swagger (якщо потрібно)
RUN apt-get install -y nginx

# Створення необхідних директорій
RUN mkdir -p /app/data/logs /app/data/pids /app/data/storage

# Копіювання конфігураційних файлів
COPY config/ /app/config/

# Копіювання коду
COPY src/ /app/src/
COPY public/ /app/public/
COPY router.php /app/router.php
COPY index.php /app/index.php

# Копіювання скрипту ініціалізації
COPY backend/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Встановлення прав на запис у data
RUN chmod -R 777 /app/data

# Експозиція порту
EXPOSE 8080

# Встановлення скрипту ініціалізації як ENTRYPOINT
ENTRYPOINT ["/docker-entrypoint.sh"]

# Запуск бекенду та веб-сервера для Swagger (якщо потрібно)
CMD ["php", "-S", "0.0.0.0:8080", "router.php"] 