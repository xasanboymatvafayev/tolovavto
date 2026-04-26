#!/bin/sh
PORT=${PORT:-8080}

# ============================================
# PHP-FPM — tezlik uchun optimallashtirish
# ============================================
cat > /usr/local/etc/php-fpm.d/www.conf << FPMCONF
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000

; Dynamic o'rniga ondemand — RAM tejaydi, Railway uchun yaxshi
pm = ondemand
pm.max_children = 20
pm.process_idle_timeout = 10s
pm.max_requests = 500

; Tezlik uchun
request_terminate_timeout = 30
FPMCONF

# ============================================
# PHP sozlamalari — opcache va tezlik
# ============================================
cat > /usr/local/etc/php/conf.d/zz-speed.ini << PHPINI
; OPcache — PHP fayllarni cache qiladi, har so'rovda parse qilmaydi
opcache.enable=1
opcache.memory_consumption=64
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=500
opcache.revalidate_freq=60
opcache.fast_shutdown=1
opcache.enable_cli=0

; Timeout va xotira
max_execution_time=30
memory_limit=128M

; Realpath cache — fayl yo'llarini cache qiladi
realpath_cache_size=2M
realpath_cache_ttl=600
PHPINI

# ============================================
# NGINX — buffer va keepalive
# ============================================
cat > /etc/nginx/http.d/default.conf << NGINX
server {
    listen ${PORT};
    root /var/www/html;
    index index.php index.html;

    # Keepalive — har so'rovda yangi TCP ulanish yo'q
    keepalive_timeout 65;
    keepalive_requests 100;

    # Bot webhook — kichik JSON, buffer yetarli
    client_max_body_size 5M;
    client_body_buffer_size 16k;

    # Gzip
    gzip on;
    gzip_types application/json text/plain;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;

        # FastCGI buffer — PHP javobini birdan yuboradi
        fastcgi_buffer_size 32k;
        fastcgi_buffers 8 16k;
        fastcgi_busy_buffers_size 32k;

        # Timeout
        fastcgi_read_timeout 30;
        fastcgi_connect_timeout 5;
        fastcgi_send_timeout 10;
    }

    # Static fayllar — PHP ga bormaydi
    location ~* \.(html|css|js|ico|png|jpg|svg)$ {
        expires 1d;
        add_header Cache-Control "public";
    }
}
NGINX

php-fpm -D
exec nginx -g "daemon off;"
