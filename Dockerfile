# syntax=docker/dockerfile:1
#
# Production image for the Notefix server (Laravel 13 + Filament + Horizon).
# The same image runs the web app, the Horizon worker and the scheduler — the
# command is overridden per service in docker-compose.yml.
#
# Released images are built and pushed to Docker Hub by the Release workflow
# (.github/workflows/release.yml). Local build:
#   docker build -t notefix-server:local .

# ---- 1. Front-end assets (Vite + Tailwind v4) -------------------------------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
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
# The composer image lacks intl/pcntl; the runtime stage installs every
# extension, and `composer check-platform-reqs` there verifies them.
RUN composer install \
        --no-dev --no-scripts --no-interaction --no-progress \
        --prefer-dist --optimize-autoloader \
        --ignore-platform-req='ext-*'

# ---- 3. Runtime -------------------------------------------------------------
FROM serversideup/php:8.5-fpm-nginx AS app

# Horizon needs pcntl + posix; the rest cover the app + Filament.
USER root
RUN install-php-extensions pcntl posix redis intl bcmath gd zip exif

# CI passes a new value once a day so the upgrade below actually re-runs instead
# of being served from the BuildKit cache, which would keep shipping Debian
# packages with published CVEs.
ARG APT_REFRESH=0
RUN echo "apt refresh: ${APT_REFRESH}" \
    && apt-get update \
    && apt-get upgrade -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
USER www-data

WORKDIR /var/www/html

# App code, then the prepared vendor + built assets.
COPY --chown=www-data:www-data . .
COPY --from=vendor   --chown=www-data:www-data /app/vendor       ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Fail the build if the runtime lacks a PHP version or extension the locked
# packages need (the vendor stage skipped the extension checks).
RUN composer check-platform-reqs --no-dev

# Publish Filament's bundled CSS/JS (composer scripts were skipped above).
# No database is touched by this command.
RUN php artisan filament:assets

# Config/route/view caching and migrations run at container start via the
# serversideup autorun (configured in docker-compose.yml), because they depend
# on the runtime environment.
