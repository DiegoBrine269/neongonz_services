#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force

mkdir -p /var/www/html/storage/logs

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf