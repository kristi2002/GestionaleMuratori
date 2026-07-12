#!/bin/sh
# Container entrypoint: apply any pending DB migrations, then serve.
# migrate.php is idempotent (it records applied migrations), so retrying while
# the database is still starting up is safe. Without this, schema changes had to
# be applied by hand after every deploy.
set -e

echo "[entrypoint] applying database migrations…"
n=0
until php database/migrate.php; do
    n=$((n + 1))
    if [ "$n" -ge 10 ]; then
        echo "[entrypoint] WARNING: migrations still failing after $n attempts; starting anyway"
        break
    fi
    echo "[entrypoint] migrate attempt $n failed (database not ready?); retrying in 3s…"
    sleep 3
done

echo "[entrypoint] starting: $*"
exec "$@"
