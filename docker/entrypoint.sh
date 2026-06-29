#!/bin/sh
set -e

cd /var/www/html

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

php artisan storage:link --force 2>/dev/null || true

if [ "${APP_ENV}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
