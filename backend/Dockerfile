FROM php:8.1-cli

WORKDIR /app

# Install dependencies and PCNTL extension
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pcntl

# Add web server support for Swagger (if needed)
RUN apt-get install -y nginx

# Create necessary directories
RUN mkdir -p /app/data/logs /app/data/pids /app/data/storage

# Copy configuration files
COPY config/ /app/config/

# Copy code
COPY src/ /app/src/
COPY public/ /app/public/
COPY router.php /app/router.php
COPY index.php /app/index.php

# Copy initialization script
COPY backend/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Set write permissions for data directory
RUN chmod -R 777 /app/data

# Expose port
EXPOSE 8080

# Set initialization script as ENTRYPOINT
ENTRYPOINT ["/docker-entrypoint.sh"]

# Start backend and web server for Swagger (if needed)
CMD ["php", "-S", "0.0.0.0:8080", "router.php"] 