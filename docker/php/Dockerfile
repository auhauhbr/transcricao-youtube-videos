FROM node:22-alpine AS frontend

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources resources
COPY public public
RUN npm run build

FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache libpq-dev libzip-dev libpng-dev \
    && docker-php-ext-install pdo_pgsql pcntl gd zip \
    && php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'

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

FROM base AS web

RUN apk add --no-cache nginx supervisor \
    && mkdir -p /run/nginx \
    && rm -f /etc/nginx/http.d/default.conf

COPY docker/nginx/northflank.conf /etc/nginx/http.d/default.conf
COPY docker/php/supervisord.conf /etc/supervisord.conf
COPY --from=frontend /var/www/html/public/build public/build

RUN printf '%s\n' \
        '[global]' \
        'error_log = /proc/self/fd/2' \
        '' \
        '[www]' \
        'access.log = /proc/self/fd/2' \
        'catch_workers_output = yes' \
        'php_admin_flag[log_errors] = on' \
        'php_admin_value[error_log] = /proc/self/fd/2' \
        > /usr/local/etc/php-fpm.d/zz-production-logging.conf

EXPOSE 8080

CMD ["supervisord", "-c", "/etc/supervisord.conf"]

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

CMD ["php", "artisan", "queue:work", "redis", "--queue=transcripts,default", "--sleep=3", "--tries=3", "--timeout=180"]
