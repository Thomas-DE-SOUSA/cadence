#!/bin/sh
set -e
cd /app

# Ensure runtime dirs + the SQLite file on the persistent volume exist.
mkdir -p /data storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch /data/database.sqlite
chown -R www-data:www-data /data storage bootstrap/cache 2>/dev/null || true

# Laravel boot: discover packages, migrate, warm the view cache.
php artisan package:discover --ansi || true
php artisan migrate --force --no-interaction
php artisan view:cache || true

exec frankenphp run --config /etc/caddy/Caddyfile
