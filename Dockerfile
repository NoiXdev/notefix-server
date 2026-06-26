# syntax=docker/dockerfile:1
#
# Production image for the Notefix server (Laravel 13 + Filament + Horizon).
# The same image runs the web app, the Horizon worker and the scheduler — the
# command is overridden per service in docker-compose.yml.
#
# Build & push to Harbor, then deploy the compose stack via Portainer:
#   docker build -t harbor.cloud.noidee.dev/notefix/server:latest .
#   docker push   harbor.cloud.noidee.dev/notefix/server:latest

# ---- 1. Front-end assets (Vite + Tailwind v4) -------------------------------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
# Sources Tailwind scans + the Vite entry points.
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
COPY config ./config
COPY public ./public
RUN npm run build

# ---- 2. PHP dependencies ----------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --no-scripts --no-interaction --no-progress \
        --prefer-dist --optimize-autoloader

# ---- 3. Runtime -------------------------------------------------------------
FROM serversideup/php:8.3-fpm-nginx AS app

# Horizon needs pcntl + posix; the rest cover the app + Filament.
USER root
RUN install-php-extensions pcntl posix redis intl bcmath gd zip exif
USER www-data

WORKDIR /var/www/html

# App code, then the prepared vendor + built assets.
COPY --chown=www-data:www-data . .
COPY --from=vendor   --chown=www-data:www-data /app/vendor       ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Publish Filament's bundled CSS/JS (composer scripts were skipped above).
# No database is touched by this command.
RUN php artisan filament:assets

# Config/route/view caching and migrations run at container start via the
# serversideup autorun (configured in docker-compose.yml), because they depend
# on the runtime environment.
