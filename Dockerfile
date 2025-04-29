FROM wordpress:php7.4-apache

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy source code to Apache web root
COPY ./test/ /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html
RUN chown -R 755 /var/www/html

EXPOSE 80