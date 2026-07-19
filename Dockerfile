FROM php:8.2-apache

# PostgreSQL client lib + PDO pgsql driver (no MySQL — hard cutoff to Postgres/pgvector)
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# mod_rewrite (standard, harmless even though app uses no rewrites today)
RUN a2enmod rewrite

# App code: docroot = repo root so the app's /public/... URLs resolve
COPY . /var/www/html/

# Block direct web access to sensitive dirs/files
COPY docker/apache-security.conf /etc/apache2/conf-enabled/security-extra.conf

# Apache writes logs/ here; own it as the web user
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
