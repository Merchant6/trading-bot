# Use official PHP 8.2 CLI image
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Install required system dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    nano \
    htop \
    libgmp-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    && docker-php-ext-install \
    bcmath \
    gmp

# Copy project files to container
COPY . /app

# Ensure logs directory has the correct permissions
RUN mkdir -p storage/logs && chmod -R 777 storage/logs

# Create an empty .env file
RUN touch /app/.env

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install project dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Copy environment file and export variables
RUN export $(grep -v '^#' /app/.env | xargs) && \
    echo "Environment variables loaded"

# Keep the container alive with a shell
CMD ["tail", "-f", "/dev/null"]
