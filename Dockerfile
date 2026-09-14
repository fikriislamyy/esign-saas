# ==========================================
# Stage 1: Install PHP dependencies
# ==========================================
FROM php:8.3-cli-bookworm AS vendor

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts


# ==========================================
# Stage 2: Build frontend assets
# ==========================================
FROM node:22-bookworm AS frontend

WORKDIR /var/www

COPY package*.json ./

RUN npm ci

COPY . .

# Ziggy is imported from Laravel's vendor directory
COPY --from=vendor /var/www/vendor ./vendor

RUN npm run build


# ==========================================
# Stage 3: Laravel + PHP-FPM + Nginx
# ==========================================
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    git \
    unzip \
    zip \
    curl \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*


# Composer
COPY --from=vendor /usr/bin/composer /usr/bin/composer

WORKDIR /var/www


# Laravel application
COPY . .


# PHP dependencies
COPY --from=vendor /var/www/vendor ./vendor


# Compiled frontend assets
COPY --from=frontend /var/www/public/build ./public/build


# Laravel writable directories
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
    && chmod -R 775 \
        storage \
        bootstrap/cache


# Nginx configuration
COPY docker/render/nginx.conf /etc/nginx/conf.d/default.conf


# Supervisor configuration
COPY docker/render/supervisord.conf /etc/supervisor/conf.d/supervisord.conf


EXPOSE 10000

CMD ["/usr/bin/supervisord", "-n"]
