FROM php:8.2-apache

RUN docker-php-ext-install mysqli

RUN a2enmod rewrite

RUN sed -i 's/Listen 80/Listen ${PORT:-8080}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT:-8080}>/' /etc/apache2/sites-enabled/000-default.conf \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
CMD ["apache2-foreground"]
