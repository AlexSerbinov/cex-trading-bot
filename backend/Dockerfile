FROM php:8.1-cli

WORKDIR /app

# Встановлення залежностей та розширення PCNTL
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pcntl

# Копіювання коду
COPY . .

# Встановлення прав на запис у data
RUN chmod -R 777 /app/data

# Команда для запуску бекенду (виправлено з src/backend/server.php на src/core/TradingBotManager.php)
CMD ["php", "src/core/TradingBotManager.php"] 