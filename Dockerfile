# syntax=docker/dockerfile:1

# ---- Stage 1: build the Vite/React assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.ts tsconfig*.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- Stage 2: runtime (FrankenPHP + PHP 8.4) ----
FROM dunglas/frankenphp:1-php8.4 AS runtime

# PHP extensions needed by the app (SQLite, GPX XML parsing, opcache).
RUN install-php-extensions pdo_sqlite intl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# PHP dependencies first (kept in their own layer for caching).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress --optimize-autoloader

# Application source + freshly built assets.
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-scripts --no-interaction \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /data \
 && chown -R www-data:www-data storage bootstrap/cache /data

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# SQLite lives on a persistent volume mounted at /data.
ENV DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite

EXPOSE 8000
ENTRYPOINT ["entrypoint"]
