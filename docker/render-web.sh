#!/bin/sh
set -e

APP_KEY_NORMALIZED=$(php -r '
$key = getenv("APP_KEY") ?: "";
$cipher = "AES-256-CBC";

$normalize = function (string $value): string {
    if ($value === "") {
        return "base64:" . base64_encode(random_bytes(32));
    }

    if (str_starts_with($value, "base64:")) {
        $decoded = base64_decode(substr($value, 7), true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $value;
        }
    }

    if (strlen($value) === 32) {
        return $value;
    }

    return "base64:" . base64_encode(substr(hash("sha256", $value, true), 0, 32));
};

$normalized = $normalize($key);
echo $normalized;
')

export APP_KEY="$APP_KEY_NORMALIZED"
export APP_PREVIOUS_KEYS=

mkdir -p \
  storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  bootstrap/cache
touch storage/logs/laravel.log
chown -R www-data:www-data storage bootstrap/cache public
chmod -R ug+rwx storage bootstrap/cache

php artisan storage:link || true
php artisan package:discover --ansi || true
php artisan migrate --force || true
php artisan module:migrate -a --force -n || true
php artisan db:seed --force || true
php artisan cache:clear || true
php artisan permission:cache-reset || true

exec apache2-foreground
