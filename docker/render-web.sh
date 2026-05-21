#!/bin/sh
set -e

mkdir -p storage/logs bootstrap/cache
touch storage/logs/laravel.log

php artisan storage:link || true

exec apache2-foreground
