FROM php:8.2-cli

# Install the PDO MySQL extension
RUN docker-php-ext-install pdo_mysql

# Create the images directory and set permissions
RUN mkdir -p /var/www/html/images && chmod 777 /var/www/html/images

# Set the working directory
WORKDIR /var/www/html

# Copy all application files
COPY . . 

# Expose Railway's standard port
EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080"]