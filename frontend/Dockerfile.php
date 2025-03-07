FROM php:8.1-cli

WORKDIR /app

# Встановлення залежностей
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# Копіювання фронтенду
COPY frontend/ /app/frontend/

# Експозиція порту
EXPOSE 80

# Запуск PHP-сервера для фронтенду
CMD ["php", "-S", "0.0.0.0:80", "-t", "frontend"] 