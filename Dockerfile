
# Build arguments for configurability
ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-apache

# Set working directory
WORKDIR /var/www

# Install system dependencies and Composer in single layer
RUN apt-get update && apt-get install -y \
        unzip \
        git \
        --no-install-recommends \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy dependency files first for better layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application files
COPY . .

# Configure Apache and set permissions
RUN a2enmod rewrite \
    && chown -R www-data:www-data /var/www

# Expose port
EXPOSE 80
