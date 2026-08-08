#!/usr/bin/env bash
# Shared helpers for the deploy and backup scripts. Sourced, never run.

set -euo pipefail

# shellcheck disable=SC2034  # consumed by the scripts that source this file
readonly ROOT="${TAIDA_ROOT:-/var/www/taida}"
readonly KEEP_RELEASES="${TAIDA_KEEP_RELEASES:-5}"

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m warn\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m fail\033[0m %s\n' "$*" >&2; exit 1; }

require() {
    command -v "$1" >/dev/null 2>&1 || die "Missing required command: $1"
}

# Reads one key out of a Laravel .env file. Values may be quoted or contain '='.
env_value() {
    local file=$1 key=$2 value
    value=$(grep -E "^${key}=" "$file" | tail -n1 | cut -d= -f2-) || true
    value=${value%$'\r'}
    value=${value#\"} ; value=${value%\"}
    value=${value#\'} ; value=${value%\'}
    printf '%s' "$value"
}

# Drops every release except the newest $KEEP_RELEASES, never the live one.
prune_releases() {
    local dir=$1 current
    current=$(readlink -f "$dir/current" 2>/dev/null || true)

    local release
    while read -r release; do
        [ -n "$release" ] || continue
        [ "$(readlink -f "$release")" != "$current" ] || continue
        log "Removing old release $(basename "$release")"
        rm -rf "$release"
    done < <(ls -1d "$dir"/releases/*/ 2>/dev/null | sort -r | tail -n +"$((KEEP_RELEASES + 1))")
}

# Points `current` at a release atomically — `ln -sfn` alone is not atomic and
# leaves a window where the symlink does not exist.
activate() {
    local dir=$1 release=$2
    ln -sfn "$release" "$dir/current.tmp"
    mv -Tf "$dir/current.tmp" "$dir/current"
}
