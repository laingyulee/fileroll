# ============================================================
# FileRoll Docker Image
# PHP-FPM 8.4 + Nginx + Supervisor
# ============================================================

FROM php:8.4-fpm-alpine AS base

# Install runtime dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite-libs \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    icu-libs

# Install build dependencies, compile PHP extensions, then clean up
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        sqlite-dev \
        mariadb-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        pdo_mysql \
        gd \
        zip \
        intl \
    && docker-php-ext-enable opcache \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP
RUN echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/fileroll.ini \
    && echo "upload_max_filesize = 5G" >> /usr/local/etc/php/conf.d/fileroll.ini \
    && echo "post_max_size = 5G" >> /usr/local/etc/php/conf.d/fileroll.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/fileroll.ini \
    && echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/fileroll.ini \
    && echo "opcache.memory_consumption = 128" >> /usr/local/etc/php/conf.d/fileroll.ini \
    && echo "opcache.interned_strings_buffer = 8" >> /usr/local/etc/php/conf.d/fileroll.ini \
    && echo "opcache.max_accelerated_files = 4000" >> /usr/local/etc/php/conf.d/fileroll.ini \
    && echo "opcache.validate_timestamps = 0" >> /usr/local/etc/php/conf.d/fileroll.ini

# ── Build stage: install dependencies ──
FROM base AS builder

WORKDIR /var/www/fileroll

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-cache

COPY . .

# ── Production stage ──
FROM base AS production

WORKDIR /var/www/fileroll

# Copy application from builder
COPY --from=builder /var/www/fileroll .

# Create storage directories
RUN mkdir -p storage/content storage/temp storage/trash \
    && chown -R www-data:www-data /var/www/fileroll

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisord.conf

# Copy entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Nginx logs to stdout/stderr
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

# Create PID directories
RUN mkdir -p /run/nginx \
    && mkdir -p /var/run/php

EXPOSE 80

VOLUME ["/var/www/fileroll/storage", "/var/www/fileroll/config"]

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
