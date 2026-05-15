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

# Expose port (Render uses PORT env var, default 10000)
EXPOSE 10000

# Set PHP settings for production
RUN echo "display_errors = Off" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "date.timezone = Asia/Manila" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.cookie_secure = 1" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/custom.ini

# Create startup script that sets Apache port from PORT env var at runtime
RUN echo '#!/bin/bash\n\
PORT="${PORT:-10000}"\n\
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf\n\
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf\n\
exec apache2-foreground' > /usr/local/bin/start.sh && \
    chmod +x /usr/local/bin/start.sh

# Start Apache via startup script
CMD ["/usr/local/bin/start.sh"]
