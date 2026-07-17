#!/usr/bin/env bash
set -euo pipefail

export PORT="${PORT:-10000}"

mkdir -p var/cache var/log
chown -R www-data:www-data var

echo "Warming up Symfony cache (prod)..."
php bin/console cache:warmup --env=prod --no-debug

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  echo "Waiting for database..."
  attempt=0
  max_attempts=30
  until php bin/console dbal:run-sql "SELECT 1" --env=prod >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge "$max_attempts" ]; then
      echo "Database not available after ${max_attempts} attempts"
      exit 1
    fi
    sleep 2
  done

  echo "Running migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
fi

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting nginx on port ${PORT}..."
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf
exec nginx -g 'daemon off;'
