FROM php:8.3-fpm

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
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Match the host user so bind-mounted storage/ and bootstrap/cache are writable
ARG UID=1000
ARG GID=1000
RUN usermod -u ${UID} www-data && groupmod -g ${GID} www-data

WORKDIR /var/www
