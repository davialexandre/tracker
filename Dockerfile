FROM php:8.2-apache

COPY --from=composer:2.1 /usr/bin/composer /usr/bin/composer

ENV DB_DSN="sqlite:///var/tracker/data/db.sqlite"
EXPOSE 80

COPY src /var/www/html
COPY composer.json /var/www
COPY composer.lock /var/www

RUN apt-get update && \
    apt-get install -y --no-install-recommends zip unzip && \
    rm -rf /var/lib/apt/lists/* && \
    mkdir -p /var/tracker/data && \
    chown www-data:www-data /var/tracker/data && \
    composer install -d /var/www --no-scripts --no-dev && \
    composer dump-autoload -d /var/www --optimize
