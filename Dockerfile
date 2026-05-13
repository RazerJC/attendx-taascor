# AttendX For TAASCOR — Render.com Deployment
# PHP 8.2 + Apache + MySQL PDO

FROM php:8.2-apache

# Install MySQL PDO extension
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set ServerName to suppress warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy application to /var/www/html/ATTENDANCE/ so all existing paths work
COPY . /var/www/html/ATTENDANCE/

# Create a root index.php that redirects to /ATTENDANCE/
RUN echo '<?php header("Location: /ATTENDANCE/index.php"); exit; ?>' > /var/www/html/index.php

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Expose port (Render uses PORT env var)
EXPOSE 10000

# Configure Apache to listen on Render's PORT
RUN sed -i 's/Listen 80/Listen ${PORT:-10000}/' /etc/apache2/ports.conf && \
    sed -i 's/:80>/:${PORT:-10000}>/' /etc/apache2/sites-available/000-default.conf

# Set PHP settings for production
RUN echo "display_errors = Off" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "date.timezone = Asia/Manila" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.cookie_secure = 1" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/custom.ini

# Start Apache
CMD ["apache2-foreground"]
