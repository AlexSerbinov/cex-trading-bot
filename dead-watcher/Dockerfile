FROM php:8.1-cli

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Create logs directory
RUN mkdir -p logs && chmod 777 logs

CMD ["php", "src/DeadWatcher.php"]