#!/usr/bin/env bash
#
# Nightly backup of the database and the uploaded media, run by
# taida-backup.timer. Safe to run by hand at any time.
#
# Media lives in $TAIDA_ROOT/api/shared/storage/app/public and is NOT in the
# repository — losing it means losing every image the client uploaded, so it is
# backed up alongside the database rather than treated as rebuildable.

source "$(dirname "$(readlink -f "$0")")/lib.sh"

readonly ENV_FILE="$ROOT/api/shared/.env"
readonly DEST="${TAIDA_BACKUP_DIR:-$ROOT/backups}"
readonly KEEP_DAYS="${TAIDA_BACKUP_KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
readonly STAMP

[ -f "$ENV_FILE" ] || die "Missing $ENV_FILE"
require mysqldump
require gzip

DB_DATABASE=$(env_value "$ENV_FILE" DB_DATABASE)
DB_USERNAME=$(env_value "$ENV_FILE" DB_USERNAME)
DB_PASSWORD=$(env_value "$ENV_FILE" DB_PASSWORD)
DB_HOST=$(env_value "$ENV_FILE" DB_HOST)
DB_PORT=$(env_value "$ENV_FILE" DB_PORT)

[ -n "$DB_DATABASE" ] || die "DB_DATABASE is not set in $ENV_FILE"

mkdir -p "$DEST"
# The dump contains every password hash in the users table.
chmod 700 "$DEST"

readonly SQL="$DEST/db-$STAMP.sql.gz"

log "Dumping $DB_DATABASE"
# The credentials go in via the environment, not argv, so they never show up in
# `ps` output for the seconds the dump takes.
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="${DB_HOST:-127.0.0.1}" \
    --port="${DB_PORT:-3306}" \
    --user="$DB_USERNAME" \
    --single-transaction \
    --quick \
    --default-character-set=utf8mb4 \
    --no-tablespaces \
    "$DB_DATABASE" | gzip -9 > "$SQL"

# A dump that failed halfway still leaves a plausible-looking file behind.
gzip -t "$SQL" || die "Dump is corrupt: $SQL"
[ "$(wc -c < "$SQL")" -gt 1024 ] || die "Dump is suspiciously small: $SQL"
chmod 600 "$SQL"

readonly MEDIA_SRC="$ROOT/api/shared/storage/app/public"
if [ -d "$MEDIA_SRC" ]; then
    readonly MEDIA="$DEST/media-$STAMP.tar.gz"
    log "Archiving uploaded media"
    tar -czf "$MEDIA" -C "$MEDIA_SRC" .
    chmod 600 "$MEDIA"
else
    warn "No media directory at $MEDIA_SRC — skipping"
fi

log "Removing backups older than $KEEP_DAYS days"
find "$DEST" -maxdepth 1 -type f -name 'db-*.sql.gz'     -mtime "+$KEEP_DAYS" -delete
find "$DEST" -maxdepth 1 -type f -name 'media-*.tar.gz'  -mtime "+$KEEP_DAYS" -delete

# A backup that only ever exists on the machine it protects is not a backup.
if [ -n "${TAIDA_BACKUP_REMOTE:-}" ]; then
    require rsync
    log "Copying to $TAIDA_BACKUP_REMOTE"
    rsync -az --delete "$DEST/" "$TAIDA_BACKUP_REMOTE"
else
    warn "TAIDA_BACKUP_REMOTE is not set — backups exist only on this server"
fi

log "Done: $(du -sh "$DEST" | cut -f1) in $DEST"
