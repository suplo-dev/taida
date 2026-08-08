#!/usr/bin/env bash
#
# Activates a Nuxt build that has already been uploaded to
#   $TAIDA_ROOT/web/releases/<name>/.output
# by CI (or by hand with rsync). Run on the server.
#
#   ./deploy-web.sh 20260806-101500
#
# The Nuxt build happens in CI, not here: `nuxt build` needs the dev
# dependencies and several hundred MB of node_modules, and it bakes
# NUXT_PUBLIC_* values into the client bundle, so the build environment has to
# match production anyway.

source "$(dirname "$(readlink -f "$0")")/lib.sh"

readonly APP="$ROOT/web"
readonly RELEASE_NAME="${1:-}"
readonly RELEASE="$APP/releases/$RELEASE_NAME"
readonly PORT="${NITRO_PORT:-3000}"

[ -n "$RELEASE_NAME" ] || die "Usage: $0 <release-name>"
[ -f "$RELEASE/.output/server/index.mjs" ] \
    || die "$RELEASE has no .output/server/index.mjs — the build did not upload correctly"
[ -f "$APP/shared/.env" ] \
    || die "Missing $APP/shared/.env — copy deploy/env/web.env.production.example first"

require node
require curl

previous=$(readlink -f "$APP/current" 2>/dev/null || true)

log "Activating release $RELEASE_NAME"
activate "$APP" "$RELEASE"

log "Restarting taida-web"
# WorkingDirectory resolves `current` at start time, so the restart — not the
# symlink swap — is what puts the new build in front of users.
sudo systemctl restart taida-web

log "Waiting for the server to answer on :$PORT"
for attempt in $(seq 1 30); do
    if curl -fsS -o /dev/null "http://127.0.0.1:$PORT/"; then
        log "Up after ${attempt}s"
        prune_releases "$APP"
        log "Site is live on release $RELEASE_NAME"
        exit 0
    fi
    sleep 1
done

warn "New release never became healthy — rolling back"
if [ -n "$previous" ] && [ -d "$previous" ]; then
    activate "$APP" "$previous"
    sudo systemctl restart taida-web
    die "Rolled back to $(basename "$previous"). Check: journalctl -u taida-web -n 100"
fi
die "No previous release to roll back to. Check: journalctl -u taida-web -n 100"
