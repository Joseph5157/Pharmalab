#!/bin/sh
set -e

# Cache config at start so Railway's runtime variables are used (not build-time env).
php artisan config:cache
php artisan migrate --force

# Demo accounts (idempotent). Enable with SEED_DEMO=true; remove the variable to stop.
if [ "$SEED_DEMO" = "true" ]; then
    php artisan db:seed --class=DemoUsersSeeder --force
fi

php-fpm -D
exec nginx -g 'daemon off;'
