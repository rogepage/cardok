#!/bin/sh
set -e

# Copy .env.example to .env if .env doesn't exist
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.example ]; then
        echo "Creating .env from .env.example..."
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Ensure composer dependencies are installed if vendor directory is missing
if [ ! -d /var/www/html/vendor ]; then
    echo "vendor directory not found, installing composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Ensure APP_KEY exists
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Ensure SQLite database exists if DB_CONNECTION is sqlite
if [ ! -f /var/www/html/database/database.sqlite ]; then
    mkdir -p /var/www/html/database
    touch /var/www/html/database/database.sqlite
fi

exec "$@"
