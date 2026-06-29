# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Stage 1: Composer dependencies
# -----------------------------------------------------------------------------
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    && composer clear-cache

# -----------------------------------------------------------------------------
# Stage 2: Frontend assets (Vite 8 + Wayfinder precisa do PHP 8.4)
# -----------------------------------------------------------------------------
FROM php:8.4-cli-bookworm AS assets

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd zip intl xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY --from=vendor /app /app

RUN npm run build \
    && rm -rf node_modules

# -----------------------------------------------------------------------------
# Stage 3: Production image (Nginx + PHP-FPM)
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS production

LABEL maintainer="Clínica Estética"

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/log/supervisor

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-laravel.ini
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak

COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY --from=assets --chown=www-data:www-data /app /var/www/html

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -f http://127.0.0.1/up || exit 1

ENV TZ="America/Sao_Paulo"

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]