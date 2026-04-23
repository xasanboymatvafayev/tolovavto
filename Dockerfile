FROM php:8.2-fpm-alpine

RUN docker-php-ext-install mysqli

RUN apk add --no-cache nginx

RUN echo '#!/bin/sh
PORT=${PORT:-8080}
cat > /etc/nginx/http.d/default.conf << NGINX
server {
    listen $PORT;
    root /var/www/html;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
NGINX
php-fpm -D
exec nginx -g "daemon off;"
' > /entrypoint.sh && chmod +x /entrypoint.sh

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
CMD ["/entrypoint.sh"]
