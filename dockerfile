FROM php:8.4-apache

# Extensions nécessaires pour SQLite
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Active mod_rewrite
RUN a2enmod rewrite

# Dossier de l'application
WORKDIR /var/www/html

# Copie le site
COPY . /var/www/html/

# Création du dossier SQLite + permissions Apache
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/data

# Render utilise PORT=10000 par défaut
ENV PORT=10000

EXPOSE 10000

# Apache doit écouter sur le port fourni par Render
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
