# Stage 1: Explicitly pull composer from the official registry
FROM docker.io/library/composer:latest AS composer_stage

# Stage 2: Use the official PHP Apache image
FROM docker.io/library/php:8.2-apache

# 1. Install git and unzip (Required for Composer to work properly)[cite: 3]
# We remove libsqlite3-dev and the PHP install command because they are built-in.
RUN apt-get update && apt-get install -y git unzip

# 2. Enable mod_rewrite[cite: 3]
RUN a2enmod rewrite

# 3. Allow .htaccess to override settings[cite: 3]
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 4. Permissions for SQLite database directory[cite: 3]
RUN mkdir -p /var/www/db && chown -R www-data:www-data /var/www/db

# 5. Copy Composer from the first stage[cite: 3]
COPY --from=composer_stage /usr/bin/composer /usr/bin/composer
