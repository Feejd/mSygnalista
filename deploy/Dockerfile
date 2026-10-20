# Obraz do Cloudflare Containers: PHP + Apache + MariaDB w jednym kontenerze.
# Budowany z katalogu głównego repo (image_build_context w wrangler.jsonc).
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends mariadb-server \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && a2enmod remoteip headers

# katalog public jako główny katalog strony
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

COPY deploy/apache-msygnalista.conf /etc/apache2/conf-enabled/msygnalista.conf
COPY deploy/start.sh /usr/local/bin/start.sh

COPY config/config.example.php /var/www/html/config/config.example.php
COPY includes /var/www/html/includes
COPY public /var/www/html/public
COPY database /var/www/html/database
COPY storage /var/www/html/storage

RUN chmod +x /usr/local/bin/start.sh \
    && chown -R www-data:www-data /var/www/html/storage

EXPOSE 80
CMD ["/usr/local/bin/start.sh"]
