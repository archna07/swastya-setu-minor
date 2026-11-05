# Swastya Setu – PHP Web Application Container
FROM php:8.2-apache

# Install system dependencies and PHP PDO MySQL extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libzip-dev \
    libpng-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql curl gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure PHP production defaults
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 10M/g' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/post_max_size = 8M/post_max_size = 12M/g' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/expose_php = On/expose_php = Off/g' "$PHP_INI_DIR/php.ini"

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Secure upload directory permissions
RUN mkdir -p /var/www/html/uploads/prescriptions \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

# Expose HTTP port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]

