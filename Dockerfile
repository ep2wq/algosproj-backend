# Build stage: install PHP dependencies with Composer
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --no-progress

# Final stage: PHP + Apache (8.4, because composer.lock pins Symfony 8 which needs PHP 8.4)
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql zip bcmath \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
