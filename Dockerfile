FROM php:8.1.0-cli

# Installing dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pcntl pdo pdo_mysql

# Installing Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Creating working directory
WORKDIR /app

# Copying project files
COPY . /app/

# Copy swagger.json to API directory
RUN cp /app/swagger.json /app/src/api/

# Setting write permissions for data directories
RUN mkdir -p /app/data/logs /app/data/pids && chmod -R 777 /app/data

# Create symbolic link for swagger.json
RUN ln -sf /app/swagger.json /app/src/api/swagger.json

# Exposing ports for API and frontend
EXPOSE 8080
EXPOSE 80

# Default command (will be overridden by docker-compose)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "src/api"] 