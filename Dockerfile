FROM php:8.2-apache

RUN a2enmod rewrite
RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install zip

# ★★★ EXTENSION PDO MYSQL (OBLIGATOIRE) ★★★
RUN docker-php-ext-install pdo pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

# Docroot -> public/
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
    && printf "<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n" >> /etc/apache2/apache2.conf

RUN composer install --no-dev --prefer-dist --optimize-autoloader

EXPOSE 8080
ENV APACHE_LISTEN_PORT=8080
RUN sed -i 's/80/${APACHE_LISTEN_PORT}/' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

CMD ["apache2-foreground"]
