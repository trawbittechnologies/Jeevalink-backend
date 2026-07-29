#!/bin/sh

set -e

echo "=== Jeevalink API Starting ==="
echo "PORT=$PORT"
echo "APP_ENV=$APP_ENV"

echo "--- Configuring Apache PORT ---"
sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf || true
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT:-80}>/g" /etc/apache2/sites-available/000-default.conf || true

echo "--- Fixing Apache MPM ---"
a2dismod mpm_event mpm_worker || true
a2enmod mpm_prefork || true

echo "--- Ensuring Storage Directories & Permissions ---"
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/testing \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

echo "--- Clearing Configuration & Route Caches ---"
php artisan optimize:clear || true
php artisan config:clear || true
php artisan route:clear || true
php artisan cache:clear || true

echo "--- Running migrations ---"
php artisan migrate --force || true

echo "--- Creating Storage Link ---"
php artisan storage:link || true

echo "--- Fixing Runtime Ownership ---"
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "--- Starting Apache ---"

exec "$@"

