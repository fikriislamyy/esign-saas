#!/bin/sh
set -eu

if [ "${1:-}" = "/usr/bin/supervisord" ]; then
    mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
    php artisan config:cache
    php artisan view:cache
fi

exec "$@"
