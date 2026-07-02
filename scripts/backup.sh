#!/usr/bin/env bash
# Nightly backup for the Docker deployment: MySQL dump + uploaded files,
# with rotation. Run from the repository root (where docker-compose.yml lives),
# typically via cron — see docs/DEPLOYMENT.md.
#
#   ./scripts/backup.sh [target_dir]        (default: /var/backups/gestionale)
#
# Restore procedure is documented in docs/DEPLOYMENT.md.

set -euo pipefail

TARGET_DIR="${1:-/var/backups/gestionale}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

# Read DB settings from the same .env docker compose uses.
if [ ! -f .env ]; then
    echo "ERROR: run from the repository root (.env not found)" >&2
    exit 1
fi
# shellcheck disable=SC1091
set -a; . ./.env; set +a

mkdir -p "$TARGET_DIR"

echo "[1/3] Dumping database ${DB_NAME:-gestionale_muratori}…"
docker compose exec -T db mysqldump \
    -u"${DB_USER:-gestionale}" -p"${DB_PASS:?DB_PASS missing in .env}" \
    --single-transaction --routines --triggers \
    "${DB_NAME:-gestionale_muratori}" | gzip > "$TARGET_DIR/db-$STAMP.sql.gz"

echo "[2/3] Archiving uploads…"
docker compose exec -T app tar -C /var/www/app/storage -czf - uploads \
    > "$TARGET_DIR/uploads-$STAMP.tar.gz"

echo "[3/3] Rotating backups older than $KEEP_DAYS days…"
find "$TARGET_DIR" -name '*.gz' -mtime "+$KEEP_DAYS" -delete

echo "Backup completed:"
ls -lh "$TARGET_DIR" | tail -n 4
