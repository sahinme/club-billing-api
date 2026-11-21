# syntax=docker/dockerfile:1

# ---------- base: PHP + extensions, shared by every stage ----------
FROM php:8.4-fpm-alpine AS base

# Installs PHP extensions together with the system libraries they need.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql rdkafka pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html
ENV COMPOSER_HOME=/tmp/composer

# ---------- dev: used by docker compose locally and in CI ----------
FROM base AS dev

RUN apk add --no-cache curl jq

# Dependencies first, so this layer is cached until composer.lock changes.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize

USER www-data

# ---------- prod: what we would ship (no dev tools, no tests, no seed data) ----------
FROM base AS prod

ENV APP_ENV=production
COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-autoloader --no-scripts

COPY src ./src
COPY config ./config
COPY public ./public
COPY bin ./bin
COPY database/migrations ./database/migrations
RUN composer dump-autoload --no-dev --classmap-authoritative

USER www-data
