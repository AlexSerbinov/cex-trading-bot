FROM php:8.1-cli

# Встановлення залежностей
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# Встановлення Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Створення робочої директорії
WORKDIR /app

# Копіювання файлів проекту
COPY . /app/

# Встановлення прав на запис для директорії з логами
RUN mkdir -p /app/src/data && chmod -R 777 /app/src/data

# Відкриття порту для API
EXPOSE 8080

# Запуск бота за замовчуванням
CMD ["php", "src/TradingBotManager.php"] 