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

# Встановлення прав на запис у data
RUN chmod -R 777 /app/data

# Експозиція порту
EXPOSE 8080

# Запуск бекенду та веб-сервера для Swagger (якщо потрібно)
CMD ["php", "src/core/TradingBotManager.php"] 