FROM php:8.1-apache

# Install MySQL extensions
RUN apt-get update && \
    docker-php-ext-install mysqli pdo && \
    docker-php-ext-enable mysqli pdo

# Copy application code into the Apache document root
COPY . /var/www/html/

# Expose port 80 (Apache default)
EXPOSE 80