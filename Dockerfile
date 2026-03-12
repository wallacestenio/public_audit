FROM php:8.2-apache

# Instalar extensões comuns
RUN docker-php-ext-install pdo pdo_mysql

# Copiar projeto
COPY . /var/www/html/

# Definir DocumentRoot como /public
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#g' /etc/apache2/sites-available/000-default.conf

# Alterar <Directory> para permitir acesso
RUN sed -i 's#<Directory /var/www/html>#<Directory /var/www/html/public>#g' /etc/apache2/apache2.conf

# Ativar mod_rewrite (necessário para MVC)
RUN a2enmod rewrite

# Permitir .htaccess dentro de /public
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Configurar Apache para escutar porta 8080 (Cloud Run exige!)
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
RUN sed -i 's/:80/:8080/g' /etc/apache2/sites-available/000-default.conf

EXPOSE 8080
CMD ["apache2-foreground"]