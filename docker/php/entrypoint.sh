#!/usr/bin/env bash
set -euo pipefail

mkdir -p var/cache var/log
# Volume mounts often keep host UID ownership — make writable for php-fpm
chmod -R ugo+rwX var || true

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  echo "Waiting for database..."
  until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
    sleep 2
  done
  echo "Running migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
fi

exec docker-php-entrypoint php-fpm
