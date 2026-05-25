# AttendX For TAASCOR — Render.com Deployment
# PHP 8.2 + Apache + Embedded MariaDB (no external DB needed)

FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

# Install MariaDB server + PHP MySQL extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set ServerName to suppress warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy application to /var/www/html/ATTENDANCE/
COPY . /var/www/html/ATTENDANCE/

# Create a root index.php that redirects to /ATTENDANCE/
RUN echo '<?php header("Location: /ATTENDANCE/index.php"); exit; ?>' > /var/www/html/index.php

# Copy startup script and fix line endings (Windows CRLF → Unix LF)
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh && \
    sed -i 's/\r$//' /usr/local/bin/start.sh

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

# Start MariaDB + Apache via startup script
CMD ["/usr/local/bin/start.sh"]
