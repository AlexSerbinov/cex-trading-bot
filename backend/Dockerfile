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

# Аргумент для визначення середовища (dev або demo)
ARG ENVIRONMENT=dev

# Копіювання коду
COPY . .

# Створення необхідних директорій
RUN mkdir -p /app/data/logs /app/data/pids /app/data/storage

# Копіювання конфігураційних файлів відповідно до середовища
RUN if [ "$ENVIRONMENT" = "dev" ]; then \
        echo "Налаштування DEV середовища"; \
        cp -f /app/config/dev/* /app/config/ 2>/dev/null || true; \
    elif [ "$ENVIRONMENT" = "demo" ]; then \
        echo "Налаштування DEMO середовища"; \
        cp -f /app/config/demo/* /app/config/ 2>/dev/null || true; \
    fi

# Встановлення прав на запис у data
RUN chmod -R 777 /app/data

# Експозиція порту
EXPOSE 8080

# Запуск бекенду та веб-сервера для Swagger (якщо потрібно)
CMD ["php", "src/core/TradingBotManager.php"] 