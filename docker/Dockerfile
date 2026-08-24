# syntax=docker/dockerfile:1

# SRS §21 — production image: multi-stage build that produces one small
# runtime image containing the Laravel API (php-fpm) fronted by nginx,
# which also serves the built React SPA as static files. The same image is
# reused for the Horizon worker and scheduler deployments (see deploy.yml)
# by overriding the entrypoint — see docker/entrypoint-*.sh.

# ---- Stage 1: shared PHP base ---------------------------------------------
# Same extension set as the final runtime image, built once so the vendor
# stage's `composer install` (which boots the framework via
# post-autoload-dump -> package:discover) runs against the real extensions
# the app needs (pcntl for Horizon, pdo_pgsql, etc.) instead of composer:2's
# minimal bundled PHP.
FROM php:8.3-fpm-alpine AS php-base

RUN apk add --no-cache \
        libpq \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        bcmath \
        zip \
        gd \
        mbstring \
        pcntl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-cache .build-deps

# ---- Stage 2: PHP dependencies ---------------------------------------------
FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY api/composer.json api/composer.lock ./

# --no-scripts: artisan isn't present yet (only composer.json/.lock are
# copied at this stage, for Docker layer caching), and composer's default
# post-install scripts (package:discover etc.) need it.
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

COPY api/ ./

RUN composer dump-autoload --no-dev --optimize \
    && composer run-script post-autoload-dump

# ---- Stage 3: React SPA build ---------------------------------------------
FROM node:20-alpine AS frontend

WORKDIR /app

COPY web/package.json web/package-lock.json ./
RUN npm ci

COPY web/ ./
RUN npm run build

# ---- Stage 4: runtime -------------------------------------------------
FROM php-base AS runtime

RUN apk add --no-cache nginx supervisor

WORKDIR /var/www/api

COPY --from=vendor /app ./
COPY --from=frontend /app/dist /var/www/web

# www-data is the built-in php-fpm pool user (usr/local/etc/php-fpm.d/www.conf)
# for the official php-fpm images — storage/bootstrap/cache must be owned by
# it, not a custom user, or php-fpm can't write logs/cache and every request
# 500s.
RUN chown -R www-data:www-data /var/www/api/storage /var/www/api/bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint-web.sh docker/entrypoint-worker.sh docker/entrypoint-scheduler.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint-*.sh

EXPOSE 80

ENTRYPOINT ["entrypoint-web.sh"]
