FROM php:8.5-apache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends unzip git && rm -rf /var/lib/apt/lists/*

# don't send the PHP version in an X-Powered-By header
RUN echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/expose-php.ini"

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist

COPY . .

EXPOSE 80
