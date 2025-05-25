FROM php:8.1-apache

WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# Enable mod_rewrite
RUN a2enmod rewrite

# Add ServerName to avoid warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy code
COPY . .

# Set write permissions for data directory
RUN chmod -R 777 /var/www/html/data

# Configure Apache for .htaccess
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/rewrite.conf \
    && a2enconf rewrite

# Ensure access to Swagger UI
RUN mkdir -p /var/www/html/swagger-ui
COPY ./docs/swagger/swagger-ui /var/www/html/swagger-ui

EXPOSE 80

CMD ["apache2-foreground"] 