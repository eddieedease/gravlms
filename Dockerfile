FROM php:8.3-apache
RUN a2enmod rewrite
RUN docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy startup script
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set working directory
WORKDIR /var/www/html

CMD ["/usr/local/bin/start.sh"]
