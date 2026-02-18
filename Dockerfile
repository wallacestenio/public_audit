# Usa a imagem oficial do PHP com Apache
FROM php:8.2-apache

# Copia os arquivos do projeto para dentro do container
COPY . /var/www/html/

# Define permissões adequadas
RUN chown -R www-data:www-data /var/www/html

# Instala extensões necessárias (exemplo: SQLite)
RUN docker-php-ext-install pdo pdo_sqlite

# Expõe a porta padrão do Apache
EXPOSE 80
