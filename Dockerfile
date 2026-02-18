FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    acl \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
    && docker-php-ext-install \
        intl \
        zip \
        opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock symfony.lock ./

RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var/ \
    && if [ -f .env ]; then chown www-data:www-data .env; fi

RUN su www-data -s /bin/sh -c "php bin/console cache:warmup --env=prod || true"

RUN chown -R www-data:www-data var/

USER www-data

CMD ["php-fpm"]