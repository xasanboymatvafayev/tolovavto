FROM php:8.2-fpm-alpine

# mysqli + opcache (tezlik uchun eng muhim)
RUN docker-php-ext-install mysqli opcache

RUN apk add --no-cache nginx

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
CMD ["/entrypoint.sh"]
