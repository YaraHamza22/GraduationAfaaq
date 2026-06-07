FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build


FROM php:8.3-cli AS vendor

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpq-dev \
    libpng-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    bcmath \
    exif \
    gd \
    intl \
    mbstring \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
COPY Modules ./Modules

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts


FROM php:8.3-apache

WORKDIR /var/www/html

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpq-dev \
    libpng-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    bcmath \
    exif \
    gd \
    intl \
    mbstring \
    opcache \
    pcntl \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    zip \
    && a2enmod rewrite proxy proxy_http proxy_wstunnel \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/render-web.sh /usr/local/bin/render-web
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN chmod +x /usr/local/bin/render-web \
    && mkdir -p storage/logs bootstrap/cache \
    && touch storage/logs/laravel.log \
    && chown -R www-data:www-data storage bootstrap/cache public

EXPOSE 80

CMD ["render-web"]
