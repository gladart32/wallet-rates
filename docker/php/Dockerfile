FROM php:8.5-fpm

# Системные пакеты
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# PHP расширения
RUN docker-php-ext-configure intl

RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    intl \
    zip

# Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Настройки PHP
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

CMD ["php-fpm"]