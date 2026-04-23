FROM php:8.2-apache

RUN docker-php-ext-install mysqli

RUN a2enmod rewrite

# PORT ni runtime da o'qish uchun entrypoint script
RUN echo '#!/bin/bash\nPORT=${PORT:-8080}\nsed -i "s/APACHE_PORT/$PORT/g" /etc/apache2/ports.conf\nsed -i "s/APACHE_PORT/$PORT/g" /etc/apache2/sites-enabled/000-default.conf\nexec apache2-foreground' > /entrypoint.sh \
    && chmod +x /entrypoint.sh

# Placeholder bilan yozish (build vaqtida)
RUN sed -i 's/Listen 80/Listen APACHE_PORT/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:APACHE_PORT>/' /etc/apache2/sites-enabled/000-default.conf \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
CMD ["/entrypoint.sh"]
