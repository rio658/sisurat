#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY belum diset. Tambahkan APP_KEY pada environment hosting."
    exit 1
fi

if [ -z "$DB_HOST" ] && [ -n "$MYSQLHOST" ] && [ "$DB_CONNECTION" = "mysql" ]; then
    export DB_CONNECTION=mysql
    export DB_HOST="$MYSQLHOST"
    export DB_PORT="${MYSQLPORT:-3306}"
    export DB_DATABASE="$MYSQLDATABASE"
    export DB_USERNAME="$MYSQLUSER"
    export DB_PASSWORD="$MYSQLPASSWORD"
fi

if [ "$DB_CONNECTION" != "mysql" ]; then
    export DB_CONNECTION=sqlite
    export DB_DATABASE="${DB_DATABASE:-/var/www/html/storage/app/database.sqlite}"
    mkdir -p "$(dirname "$DB_DATABASE")"
    [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi

if [ -z "$APP_URL" ] && [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    export APP_URL="https://$RAILWAY_PUBLIC_DOMAIN"
fi
export ASSET_URL="${ASSET_URL:-$APP_URL}"

php artisan config:clear
php artisan migrate --force
php artisan db:seed --class=AkunSeeder --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
