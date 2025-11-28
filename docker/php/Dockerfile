FROM php:8.3-fpm-alpine AS base

# Dependencias del sistema y extensiones
RUN apk add --no-cache \
    bash git curl unzip libpng-dev libjpeg-turbo-dev libwebp-dev libzip-dev oniguruma-dev \
    icu-dev freetype-dev sqlite-dev postgresql-dev supervisor shadow

# Extensiones PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) gd pdo_pgsql pgsql pdo_sqlite mbstring zip intl

# Opcional: pcntl para Horizon (se recomienda)
RUN docker-php-ext-install pcntl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Config
WORKDIR /var/www/html
COPY docker/php/php.ini /usr/local/etc/php/conf.d/php.ini

# Permisos recomendados para storage/bootstrap
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data \
 && mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
 && chown -R www-data:www-data /var/www/html

USER www-data

# En dev montamos volumen; en prod podrías hacer build de vendor con multi-stage
CMD ["php-fpm"]