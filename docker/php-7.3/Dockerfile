FROM php:7.3-cli

RUN apt update \
    && apt install --assume-yes git unzip \
    && rm -rf /var/lib/apt/lists/* \
    \
    && pecl install xdebug-2.9.2 \
    && docker-php-ext-enable xdebug \
    \
    && curl --output composer-setup.php https://getcomposer.org/installer \
    && php composer-setup.php --install-dir /usr/local/bin --filename composer \
    && rm composer-setup.php \
    \
    && curl --location --output /usr/local/bin/xdebug https://raw.githubusercontent.com/julienfalque/xdebug/v1.1.0/xdebug \
    && chmod +x /usr/local/bin/xdebug

COPY xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
