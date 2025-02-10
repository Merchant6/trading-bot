# Use official PHP 8.2 CLI image
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Install required system dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    nano

# Copy project files to container
COPY . /app

# Ensure logs directory has the correct permissions
RUN mkdir -p storage/logs && chmod -R 777 storage/logs

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install project dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set the default command to execute your bot script
#CMD ["php", "/app/bot.php"]
