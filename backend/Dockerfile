FROM php:8.3-cli-bookworm

# Some networks block plain HTTP to Debian mirrors; HTTPS works (see LAN deploy).
RUN set -eux; \
    if [ -f /etc/apt/sources.list.d/debian.sources ]; then \
      sed -i 's|http://|https://|g' /etc/apt/sources.list.d/debian.sources; \
    fi; \
    if [ -f /etc/apt/sources.list ]; then \
      sed -i 's|http://deb.debian.org|https://deb.debian.org|g; s|http://security.debian.org|https://security.debian.org|g' /etc/apt/sources.list; \
    fi; \
    apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    nginx \
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

# OPcache + JIT tuning (see docker/entrypoint.sh for the runtime bind-mount copy).
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-toweros-opcache.ini
COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/zz-toweros-uploads.ini

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx-api-dev.conf /etc/nginx/conf.d/toweros-api.conf
COPY docker/entrypoint.sh /usr/local/bin/toweros-api-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/toweros-api-entrypoint \
    && chmod +x /usr/local/bin/toweros-api-entrypoint

EXPOSE 8000

ENTRYPOINT ["/bin/sh", "/usr/local/bin/toweros-api-entrypoint"]
CMD []
