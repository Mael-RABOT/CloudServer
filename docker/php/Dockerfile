FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create symfony user with UID 1000 (matching typical host user)
RUN useradd -G www-data,root -u 1000 -d /home/symfony symfony
RUN mkdir -p /home/symfony/.composer && \
    chown -R symfony:symfony /home/symfony

# Set working directory
WORKDIR /var/www/html

# Copy custom PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create upload directory and set permissions
RUN mkdir -p /var/www/upload && \
    chown -R symfony:www-data /var/www/upload && \
    chmod -R 775 /var/www/upload

# Create a script to fix permissions on startup
RUN echo '#!/bin/bash\n\
if [ -d "/var/www/html" ]; then\n\
    chown -R symfony:symfony /var/www/html\n\
    chmod -R 755 /var/www/html\n\
    # Fix Git ownership issues\n\
    if [ -d "/var/www/html/.git" ]; then\n\
        gosu symfony git config --global --add safe.directory /var/www/html\n\
    fi\n\
fi\n\
# If the command is php-fpm, run it as root\n\
if [ "$1" = "php-fpm" ]; then\n\
    exec "$@"\n\
else\n\
    # For other commands, switch to symfony user\n\
    exec gosu symfony "$@"\n\
fi' > /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

# Install gosu for proper user switching
RUN apt-get update && apt-get install -y gosu && rm -rf /var/lib/apt/lists/*

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Use the entrypoint script
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]