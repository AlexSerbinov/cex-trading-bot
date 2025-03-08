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

# Копіювання коду
COPY . .

# Копіювання скрипту ініціалізації
COPY backend/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Створення необхідних директорій
RUN mkdir -p /app/data/logs /app/data/pids /app/data/storage

# Встановлення прав на запис у data
RUN chmod -R 777 /app/data

# Експозиція порту
EXPOSE 8080

# Встановлення скрипту ініціалізації як ENTRYPOINT
ENTRYPOINT ["/docker-entrypoint.sh"]

# Запуск бекенду та веб-сервера для Swagger (якщо потрібно)
CMD ["php", "-S", "0.0.0.0:8080", "router.php"] 