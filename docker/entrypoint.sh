#!/bin/sh
set -e
cd /var/www/html

# Зібрані public-файли (Filament, css/js адмінки) у том, який віддає nginx
if [ -d /var/www/public-shared ]; then
    cp -a public/. /var/www/public-shared/
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
    php artisan storage:link 2>/dev/null || true
    php artisan config:cache && php artisan route:cache && php artisan view:cache
    # персонал (пароль ADMIN_PASSWORD) і демо-дані, якщо база ще порожня
    SEED_DEMO="${SEED_DEMO:-1}" php artisan db:seed --force >> storage/logs/seed.log 2>&1 || true
fi
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
