FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && a2enmod rewrite

RUN echo "max_execution_time = 15" > /usr/local/etc/php/conf.d/render.ini \
 && echo "default_socket_timeout = 5" >> /usr/local/etc/php/conf.d/render.ini \
 && echo "mysql.connect_timeout = 5" >> /usr/local/etc/php/conf.d/render.ini

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]