FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    git \
    unzip \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    libzip-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install \
        intl \
        mbstring \
        pdo \
        pdo_pgsql \
        zip \
        sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]
