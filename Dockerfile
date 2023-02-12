FROM php:8.0-fpm

RUN apt-get -y update && apt-get install -y libicu-dev zlib1g-dev libpng-dev

RUN docker-php-ext-configure intl

RUN docker-php-ext-install pdo pdo_mysql intl gd

ENV XDEBUG_MODE=coverage

RUN pecl install xdebug && docker-php-ext-enable xdebug
