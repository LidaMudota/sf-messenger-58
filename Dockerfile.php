FROM php:8.2-fpm-alpine

# системные зависимости
RUN apk add --no-cache bash git curl libpng-dev oniguruma-dev libzip-dev icu-dev mysql-client \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath intl gd zip

# composer
ENV COMPOSER_HOME=/composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/src
