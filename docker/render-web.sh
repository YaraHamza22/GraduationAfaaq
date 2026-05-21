#!/bin/sh
set -e

mkdir -p \
  storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  bootstrap/cache
touch storage/logs/laravel.log

php artisan storage:link || true
php artisan package:discover --ansi || true
php artisan migrate --force || true
php artisan module:migrate -a --force -n || true

exec apache2-foreground
