# MinimalCMS — Development Dockerfile
#
# PHP 8.2 + Apache + Composer. Used exclusively for local development.
# Source code is volume-mounted — do NOT use this image in production.
#
# @package MinimalCMS

FROM composer:2 AS composer

FROM php:8.4-apache

# Enable Apache mod_rewrite for .htaccess URL rewriting.
RUN a2enmod rewrite

# Allow .htaccess overrides in the document root.
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy Composer from the official image.
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
