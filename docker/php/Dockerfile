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

# Set working directory
WORKDIR /var/www/html

# Copy custom PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create symfony user
RUN useradd -G www-data,root -u 1000 -d /home/symfony symfony
RUN mkdir -p /home/symfony/.composer && \
    chown -R symfony:symfony /home/symfony

# Create upload directory and set permissions
RUN mkdir -p /var/www/upload && \
    chown -R symfony:www-data /var/www/upload && \
    chmod -R 775 /var/www/upload

# Switch to symfony user
USER symfony

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]