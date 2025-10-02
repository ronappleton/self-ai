# syntax=docker/dockerfile:1.6

FROM php:8.2-fpm-bookworm AS base

# Install system dependencies and PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        ca-certificates \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libsqlite3-dev \
        sqlite3 \
        supervisor \
        python3 \
        python3-pip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        bcmath \
        intl \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Copy Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install Python dependencies for the embedding worker
COPY worker-embed/requirements.txt /tmp/worker-embed-requirements.txt
RUN pip3 install --no-cache-dir -r /tmp/worker-embed-requirements.txt \
    && rm /tmp/worker-embed-requirements.txt

# Copy application code
COPY app/ ./

# Prepare runtime directories
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Copy the entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

ENV PATH="/var/www/html/vendor/bin:${PATH}"

EXPOSE 8000 5173

ENTRYPOINT ["app-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
