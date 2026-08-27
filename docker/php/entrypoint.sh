#!/bin/sh

set -eu

if [ "$(id -u)" = '0' ]; then
    for directory in \
        storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache; do
        install -d -o www-data -g www-data -m 775 "$directory"
    done

    chown -R www-data:www-data storage bootstrap/cache

    if [ "${1:-}" = 'php' ] && [ "${2:-}" = 'artisan' ] && [ "${3:-}" = 'queue:work' ]; then
        exec su-exec www-data /usr/local/bin/docker-php-entrypoint "$@"
    fi
fi

exec /usr/local/bin/docker-php-entrypoint "$@"
