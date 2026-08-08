#!/usr/bin/env bash
#
# Activates a Laravel release that has already been uploaded to
#   $TAIDA_ROOT/api/releases/<name>
# by CI (or by hand with rsync). Run on the server.
#
#   ./deploy-api.sh 20260806-101500
#
# Everything before the symlink swap is reversible; if migrate or config:cache
# fails the site keeps serving the previous release untouched.

source "$(dirname "$(readlink -f "$0")")/lib.sh"

readonly APP="$ROOT/api"
readonly RELEASE_NAME="${1:-}"
readonly RELEASE="$APP/releases/$RELEASE_NAME"

[ -n "$RELEASE_NAME" ] || die "Usage: $0 <release-name>"
[ -d "$RELEASE" ]      || die "No such release: $RELEASE"
[ -f "$APP/shared/.env" ] || die "Missing $APP/shared/.env — copy deploy/env/api.env.production.example first"

require php
require composer

log "Linking shared state into $RELEASE_NAME"
# The release ships without either: .env holds secrets, storage holds uploaded
# media that must survive the release it was uploaded under.
ln -sfn "$APP/shared/.env"    "$RELEASE/.env"
rm -rf "$RELEASE/storage"
ln -sfn "$APP/shared/storage" "$RELEASE/storage"

log "Installing PHP dependencies"
composer install \
    --working-dir="$RELEASE" \
    --no-dev --optimize-autoloader --classmap-authoritative \
    --no-interaction --no-progress

log "Running migrations"
# Migrations are additive, so they run against the live database before the
# swap; the old release keeps working while they apply.
php "$RELEASE/artisan" migrate --force --no-interaction

log "Warming caches"
php "$RELEASE/artisan" config:cache
php "$RELEASE/artisan" route:cache
php "$RELEASE/artisan" event:cache
# public/storage -> ../storage/app/public, so nginx can serve uploaded media.
php "$RELEASE/artisan" storage:link --quiet || true

log "Activating release"
activate "$APP" "$RELEASE"

log "Reloading PHP-FPM"
# Without this the opcache keeps serving the previous release's compiled files
# even though the symlink now points elsewhere.
sudo systemctl reload "php${PHP_FPM_VERSION:-8.5}-fpm"

log "Clearing the content cache"
# Deploys do not change content, but a schema or Resource change can make
# cached payloads stale in a way the observer never sees.
php "$APP/current/artisan" cache:clear

log "Health check"
health=$(curl -fsS -o /dev/null -w '%{http_code}' https://api.taida.vn/up) \
    || die "Health check failed — roll back with: $0 <previous-release>"
[ "$health" = "200" ] || die "Health check returned $health"

prune_releases "$APP"
log "API is live on release $RELEASE_NAME"
