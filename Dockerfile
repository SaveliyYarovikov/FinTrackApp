FROM php:8.3.6-fpm

RUN apt-get update && apt-get install -y \
    curl \
    zip \
    unzip \
    libicu-dev

RUN curl -sL https://deb.nodesource.com/setup_25.x | bash \
    && apt-get install nodejs -y

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-configure intl

RUN docker-php-ext-install pdo pdo_mysql intl

RUN pecl install xdebug-3.4.5 && docker-php-ext-enable xdebug

COPY conf/php/php.ini ${PHP_INI_DIR}

COPY conf/xdebug/xdebug.ini ${PHP_INI_DIR}/conf.d

WORKDIR /var/www