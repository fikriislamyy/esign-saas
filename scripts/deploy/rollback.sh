#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

[[ ${CONFIRM_SCHEMA_COMPATIBLE:-} == yes ]] || {
    echo 'Set CONFIRM_SCHEMA_COMPATIBLE=yes only after checking migration compatibility.' >&2
    exit 2
}
sha=${1:-}
target=$(release_path "$sha")
read_release "$target"
exec 9> "$ESIGN_LOCK"
flock -x 9
previous=$(current_release)
[[ -n $previous ]] || { echo 'No current release to roll back.' >&2; exit 2; }
[[ $previous != "$target" ]] || { echo 'Target release is already current.' >&2; exit 2; }
compose "$target" config --quiet
compose "$target" pull app
restore_current() {
    trap - ERR
    set +e
    printf 'respond "Temporarily unavailable" 503\n' > "$ESIGN_SHARED_DIR/maintenance/active.caddy"
    compose "$previous" up -d --wait --wait-timeout 120 --no-deps app proxy
    compose "$previous" exec -T app php artisan up
    if check_app "$previous"; then
        disable_maintenance "$previous"
        check_public
    fi
    echo 'Rollback failed; current release recovery attempted.' >&2
    exit 1
}
trap restore_current ERR
enable_maintenance "$previous"
compose "$previous" exec -T app php artisan down
compose "$previous" stop app
compose "$target" up -d --wait --wait-timeout 120 --no-deps app proxy
compose "$target" exec -T app php artisan up
check_app "$target"
disable_maintenance "$target"
check_public
set_current "$target"
trap - ERR
echo "Rolled back to $RELEASE_SHA ($RELEASE_IMAGE)."
