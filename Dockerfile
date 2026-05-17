FROM php:8.5-apache

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

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
    composer dump-autoload -d /var/www --optimize && \
    echo 'AddOutputFilterByType DEFLATE application/json' >> /etc/apache2/mods-enabled/deflate.conf && \
    echo 'opcache.jit=tracing' > /usr/local/etc/php/conf.d/opcache-jit.ini
