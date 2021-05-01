FROM php:7.2-apache

RUN docker-php-ext-install pdo_mysql mysqli mbstring
COPY php.ini /usr/local/etc/php/conf.d/php.ini

WORKDIR /var/www/html

EXPOSE 80
