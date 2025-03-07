FROM php:8.1-apache

WORKDIR /var/www/html

# Встановлення залежностей
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# Увімкнення mod_rewrite
RUN a2enmod rewrite

# Додання ServerName для уникнення попередження
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Копіювання коду
COPY . .

# Встановлення прав на запис у data
RUN chmod -R 777 /var/www/html/data

# Налаштування Apache для .htaccess
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/rewrite.conf \
    && a2enconf rewrite

EXPOSE 80

CMD ["apache2-foreground"] 