#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force

mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf