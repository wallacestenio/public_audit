FROM php:8.2-apache

# Instala extensões comuns
RUN docker-php-ext-install pdo pdo_mysql

# Copia código para o servidor
COPY . /var/www/html/

# Configura o DocumentRoot para /public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/apache2.conf

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
CMD ["apache2-foreground"]
