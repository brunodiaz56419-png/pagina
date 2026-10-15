# 1. Partimos de una imagen que ya trae PHP 8.3 y Apache
FROM php:8.3-apache

# 2. Instalamos las extensiones para conectarnos a MySQL
RUN docker-php-ext-install mysqli pdo_mysql

# 3. Copiamos nuestro proyecto a la carpeta pública de Apache
COPY . /var/www/html/

# 4. Hacemos que Apache escuche en el puerto que Render le indique
ENV PORT=10000
RUN sed -i 's/80/${PORT}/g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/ports.conf
