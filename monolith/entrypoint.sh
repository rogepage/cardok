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

# Ensure composer dependencies are installed if vendor directory or autoload.php is missing
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "vendor/autoload.php not found, installing composer dependencies..."
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

# Run database migrations to ensure all tables exist on startup
echo "Running database migrations..."
php artisan migrate --force

# Ensure storage log file exists and stream to container stdout for docker compose logs
mkdir -p /var/www/html/storage/logs
touch /var/www/html/storage/logs/laravel.log
tail -n 0 -F /var/www/html/storage/logs/laravel.log &

exec "$@"
