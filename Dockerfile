FROM php:8.5-apache

ENV DB_DSN="sqlite:///var/tracker/data/db.sqlite"
EXPOSE 80

COPY web /var/www/html
COPY src /var/www/src

RUN mkdir -p /var/tracker/data && \
    chown www-data:www-data /var/tracker/data && \
    echo 'AddOutputFilterByType DEFLATE application/json' >> /etc/apache2/mods-enabled/deflate.conf && \
    echo 'opcache.jit=tracing' > /usr/local/etc/php/conf.d/opcache-jit.ini && \
    echo 'upload_max_filesize = 100M' > /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'post_max_size = 100M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'max_execution_time = 120' >> /usr/local/etc/php/conf.d/uploads.ini
