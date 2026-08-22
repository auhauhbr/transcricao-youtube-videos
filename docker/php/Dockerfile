FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install pdo_pgsql pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY public public
COPY resources resources
COPY routes routes
COPY storage storage
COPY tests tests
COPY artisan ./
COPY phpunit.xml phpstan.neon ./
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

FROM base AS app

CMD ["php-fpm"]

FROM base AS worker

ARG YT_DLP_VERSION=2026.08.19
ARG YT_DLP_SHA256=1fa6733c37ea6fb51c99ad8fe785e7b7e5f3246c9b980230329d4fb72ed8d4d6

RUN apk add --no-cache nodejs python3 \
    && apk add --no-cache --virtual .yt-dlp-download curl \
    && curl --fail --location --silent --show-error \
        "https://github.com/yt-dlp/yt-dlp/releases/download/${YT_DLP_VERSION}/yt-dlp" \
        --output /usr/local/bin/yt-dlp \
    && echo "${YT_DLP_SHA256}  /usr/local/bin/yt-dlp" | sha256sum -cs \
    && chmod 0755 /usr/local/bin/yt-dlp \
    && apk del .yt-dlp-download
