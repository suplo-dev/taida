#!/usr/bin/env bash
#
# Restores a backup produced by backup.sh.
#
#   ./restore.sh /var/www/taida/backups/db-20260806-031500.sql.gz
#
# Run this at least once against a staging database before you need it — an
# untested backup is a guess, not a recovery plan.

source "$(dirname "$(readlink -f "$0")")/lib.sh"

readonly ENV_FILE="${TAIDA_ENV_FILE:-$ROOT/api/shared/.env}"
readonly DUMP="${1:-}"

[ -n "$DUMP" ]     || die "Usage: $0 <db-backup.sql.gz> [media-backup.tar.gz]"
[ -f "$DUMP" ]     || die "No such file: $DUMP"
[ -f "$ENV_FILE" ] || die "Missing $ENV_FILE"
require mysql
gzip -t "$DUMP" || die "$DUMP is not a valid gzip archive"

DB_DATABASE=$(env_value "$ENV_FILE" DB_DATABASE)
DB_USERNAME=$(env_value "$ENV_FILE" DB_USERNAME)
DB_PASSWORD=$(env_value "$ENV_FILE" DB_PASSWORD)
DB_HOST=$(env_value "$ENV_FILE" DB_HOST)
DB_PORT=$(env_value "$ENV_FILE" DB_PORT)

warn "This overwrites every table in '$DB_DATABASE' on ${DB_HOST:-127.0.0.1}."
read -r -p "Type the database name to confirm: " confirm
[ "$confirm" = "$DB_DATABASE" ] || die "Aborted."

log "Restoring $DUMP"
gzip -dc "$DUMP" | MYSQL_PWD="$DB_PASSWORD" mysql \
    --host="${DB_HOST:-127.0.0.1}" \
    --port="${DB_PORT:-3306}" \
    --user="$DB_USERNAME" \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE"

readonly MEDIA="${2:-}"
if [ -n "$MEDIA" ]; then
    [ -f "$MEDIA" ] || die "No such file: $MEDIA"
    readonly MEDIA_DEST="$ROOT/api/shared/storage/app/public"
    log "Restoring media into $MEDIA_DEST"
    mkdir -p "$MEDIA_DEST"
    tar -xzf "$MEDIA" -C "$MEDIA_DEST"
fi

# Cached payloads describe the database that was just replaced.
log "Clearing the content cache"
php "$ROOT/api/current/artisan" cache:clear

log "Restore complete. Spot-check https://www.taida.vn/ before telling anyone."
