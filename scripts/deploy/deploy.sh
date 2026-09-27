#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sha=${1:-}
release=$(release_path "$sha")
read_release "$release"
[[ -f "$ESIGN_SHARED_DIR/.env.production" ]] || { echo 'Production environment is missing.' >&2; exit 2; }
command -v flock >/dev/null || { echo 'flock is required.' >&2; exit 2; }
exec 9> "$ESIGN_LOCK"
flock -x 9

previous=$(current_release)
if [[ $previous == "$release" ]]; then
    echo 'This release is already current.'
    exit 0
fi
if [[ -n $previous ]]; then
    old_sequence=$(sed -n 's/^RELEASE_SEQUENCE=//p' "$previous/release.env")
    [[ $old_sequence =~ ^[0-9]+$ && $RELEASE_SEQUENCE -gt $old_sequence ]] || {
        echo 'Refusing an older or reordered release; use rollback.sh explicitly.' >&2; exit 2;
    }
fi

mkdir -p "$ESIGN_SHARED_DIR/maintenance"
compose "$release" config --quiet
compose "$release" pull app
compose "$release" up -d --wait --wait-timeout 120 postgres redis
bash "$release/scripts/deploy/backup.sh" "$sha"

migration_started=0
maintenance_enabled=0
recover() {
    local status=$?
    trap - ERR
    set +e
    echo "Deployment failed (status $status)." >&2
    if [[ $maintenance_enabled -eq 1 && -n $previous ]]; then
        if [[ $migration_started -eq 0 || $RELEASE_COMPATIBLE == true ]]; then
            printf 'respond "Temporarily unavailable" 503\n' > "$ESIGN_SHARED_DIR/maintenance/active.caddy"
            compose "$previous" up -d --wait --wait-timeout 120 --no-deps app proxy
            compose "$previous" exec -T app php artisan up
            if check_app "$previous"; then
                disable_maintenance "$previous"
                check_public && echo 'Previous release restored.' >&2
            fi
        else
            echo 'Migration started and schema compatibility was not approved. Maintenance remains active.' >&2
        fi
    fi
    exit 1
}
trap recover ERR

if [[ -n $previous ]]; then
    maintenance_enabled=1
    enable_maintenance "$previous"
    compose "$previous" exec -T app php artisan down
    compose "$previous" stop app
else
    printf 'respond "Temporarily unavailable" 503\n' > "$ESIGN_SHARED_DIR/maintenance/active.caddy"
    maintenance_enabled=1
    compose "$release" up -d proxy
fi

migration_started=1
compose "$release" run --rm --no-deps --entrypoint php app artisan migrate --force
compose "$release" up -d --wait --wait-timeout 120 --no-deps app proxy
check_app "$release"
container_id=$(compose "$release" ps -q app)
expected_id=$(docker image inspect --format '{{.Id}}' "$RELEASE_IMAGE")
actual_id=$(docker inspect --format '{{.Image}}' "$container_id")
[[ $actual_id == "$expected_id" ]] || { echo 'Running image digest does not match the manifest.' >&2; exit 1; }
disable_maintenance "$release"
check_public
maintenance_enabled=0
set_current "$release"
trap - ERR
echo "Deployed $RELEASE_SHA ($RELEASE_IMAGE)."
