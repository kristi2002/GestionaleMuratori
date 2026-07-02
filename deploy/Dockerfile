# Application image: PHP-FPM 8.2 with the extensions the app needs
# (pdo_mysql, gd for thumbnails/signatures, zip for PhpSpreadsheet, opcache).
# Build context is the repository root:  docker compose up -d --build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# ext-gd/ext-pdo live in the runtime image below, not in the composer image:
# skip the platform check here (dependencies are pure PHP + polyfills).
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
        --ignore-platform-reqs

FROM php:8.2-fpm-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip opcache \
 && rm -rf /var/lib/apt/lists/*

COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/app
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Uploads live on a named volume mounted here; make sure PHP-FPM can write.
RUN mkdir -p storage/uploads \
 && chown -R www-data:www-data storage

USER www-data
