FROM php:8.2-apache

# PHP extension the app needs (PDO MySQL driver)
RUN docker-php-ext-install pdo_mysql

# mod_rewrite (standard, harmless even though app uses no rewrites today)
RUN a2enmod rewrite

# App code: docroot = repo root so the app's /public/... URLs resolve
COPY . /var/www/html/

# Block direct web access to sensitive dirs/files
COPY docker/apache-security.conf /etc/apache2/conf-enabled/security-extra.conf

# Apache writes logs/ here; own it as the web user
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
