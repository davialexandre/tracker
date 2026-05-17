FROM php:8.5-apache

ENV DB_DSN="sqlite:///var/tracker/data/db.sqlite"
EXPOSE 80

COPY src /var/www/html

RUN mkdir -p /var/tracker/data && \
    chown www-data:www-data /var/tracker/data && \
    echo 'AddOutputFilterByType DEFLATE application/json' >> /etc/apache2/mods-enabled/deflate.conf && \
    echo 'opcache.jit=tracing' > /usr/local/etc/php/conf.d/opcache-jit.ini
