FROM php:8.2-apache

# Встановлення системних залежностей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Встановлення розширень PHP для роботи з базами даних
RUN docker-php-ext-install pdo pdo_mysql pcntl bcmath

# Встановлення Composer з офіційного образу
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Налаштування Apache
RUN a2enmod rewrite

# Встановлення розширення redis для PHP
RUN pecl install redis \
 && docker-php-ext-enable redis

# Xdebug для покриття коду (PHPUnit --coverage-*)
RUN pecl install xdebug \
 && docker-php-ext-enable xdebug

# Робоча директорія
WORKDIR /var/www/html

# Встановлення прав доступу
RUN chown -R www-data:www-data /var/www/html
