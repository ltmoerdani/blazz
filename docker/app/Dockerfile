# ============================================================================
# Laravel Application Dockerfile
# ============================================================================
# Base: PHP 8.3 FPM on Debian Bookworm (stable, compatible)
# Features: All required extensions, Composer, Node.js for asset building
# ============================================================================

FROM php:8.3-fpm-bookworm

LABEL maintainer="Blazz Team"
LABEL description="Laravel 12 Application Container"

# Build arguments
ARG NODE_VERSION=20
ARG COMPOSER_VERSION=2

# Environment variables
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Jakarta

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Basic utilities
    curl \
    wget \
    git \
    unzip \
    zip \
    vim \
    nano \
    htop \
    procps \
    supervisor \
    cron \
    # Image processing (for Intervention Image)
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libgif-dev \
    # PDF and document processing
    libzip-dev \
    libxml2-dev \
    libxslt1-dev \
    # FFmpeg for audio/video processing
    ffmpeg \
    # Database clients
    default-mysql-client \
    # Network utilities
    iputils-ping \
    net-tools \
    # Clean up
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    bcmath \
    opcache \
    pcntl \
    exif \
    intl \
    soap \
    xsl

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js (for Vite asset building)
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Create application user
RUN groupadd -g 1000 www \
    && useradd -u 1000 -ms /bin/bash -g www www

# Set working directory
WORKDIR /var/www/html

# Copy PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy application files
COPY --chown=www:www . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Install Node dependencies and build assets
RUN npm ci --omit=dev \
    && npm run build \
    && rm -rf node_modules

# Set permissions
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create required directories
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && chown -R www:www /var/www/html/storage

# Switch to non-root user
USER www

# Expose PHP-FPM port
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# Start PHP-FPM
CMD ["php-fpm"]
