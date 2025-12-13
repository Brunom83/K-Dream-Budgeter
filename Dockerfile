# Dockerfile
# Usamos uma imagem oficial do PHP com Apache
FROM php:8.2-apache

# Instalar extensões necessárias para ligar ao MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Ativar o mod_rewrite do Apache (para os URLs bonitos funcionarem)
RUN a2enmod rewrite

# Copiar o ficheiro .htaccess e o código para o sítio certo
COPY . /var/www/html/

# Dar permissões à pasta de uploads para poderes subir fotos
RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads