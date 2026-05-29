FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    zip \
    gd \
    intl \
    bcmath \
    pcntl \
    opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/toweros-api-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/toweros-api-entrypoint \
    && chmod +x /usr/local/bin/toweros-api-entrypoint

EXPOSE 8000

ENTRYPOINT ["/bin/sh", "/usr/local/bin/toweros-api-entrypoint"]
CMD []
