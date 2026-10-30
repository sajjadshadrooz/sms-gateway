ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions rdkafka pdo_pgsql pcntl intl bcmath zip

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction

COPY . .
RUN composer dump-autoload

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
