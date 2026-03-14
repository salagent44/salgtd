#!/bin/sh
set -e

# Set system timezone from TIMEZONE env var
if [ -n "$TIMEZONE" ]; then
    ln -sf "/usr/share/zoneinfo/$TIMEZONE" /etc/localtime
    echo "$TIMEZONE" > /etc/timezone
fi

# Clear stale update artifacts
rm -f /data/update-trigger
rm -f /data/update-status

# Ensure SQLite database exists
if [ ! -f /data/gtd.sqlite ]; then
    touch /data/gtd.sqlite
    chown www-data:www-data /data/gtd.sqlite
fi

# Generate .env if missing
if [ ! -f /var/www/.env ]; then
    cp /var/www/.env.production /var/www/.env
fi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run migrations + seed (firstOrCreate ensures idempotency)
php artisan migrate --force
php artisan db:seed --force

# Cache config/routes for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec supervisord -c /etc/supervisord.conf
