# PHP 8.3 + Apache, com as mesmas extensoes que o EA-PHP 8.3 do VPS oferece
# (pdo_mysql, bcmath, curl, gd, zip; mbstring ja vem na imagem) mais intl, que o Filament usa
# para formatar numeros e datas — e que precisara ser habilitado no cPanel.
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip gd opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Docroot aponta para public/, exatamente como a conta cPanel fara.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
