FROM php:8.2-apache

# Extensões comuns
RUN docker-php-ext-install pdo pdo_mysql

# Copiar projeto
COPY . /var/www/html/

# Ajustar DocumentRoot para /public
RUN sed -i 's/\/var\/www\/html/\/var\/www\/html\/public/g' /etc/apache2/sites-available/000-default.conf

# Apache precisa escutar na porta 8080 para o Cloud Run
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
RUN sed -i 's/:80/:8080/g' /etc/apache2/sites-available/000-default.conf

# Permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
CMD ["apache2-foreground"]