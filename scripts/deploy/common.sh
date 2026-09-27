#!/usr/bin/env bash
set -Eeuo pipefail

ESIGN_ROOT=${ESIGN_ROOT:-/opt/esign}
ESIGN_SHARED_DIR="$ESIGN_ROOT/shared"
ESIGN_LOCK="$ESIGN_ROOT/deploy.lock"
export ESIGN_ROOT ESIGN_SHARED_DIR ESIGN_LOCK

require_sha() {
    [[ ${1:-} =~ ^[0-9a-f]{40}$ ]] || { echo 'Expected a full lowercase Git SHA.' >&2; exit 2; }
}

release_path() {
    require_sha "$1"
    printf '%s/releases/%s' "$ESIGN_ROOT" "$1"
}

read_release() {
    local release=$1
    [[ -f "$release/release.env" && -f "$release/docker-compose.prod.yml" ]] || {
        echo 'Incomplete release directory.' >&2; exit 2;
    }
    mapfile -t RELEASE_LINES < "$release/release.env"
    [[ ${#RELEASE_LINES[@]} -eq 4 ]] || { echo 'Invalid release manifest.' >&2; exit 2; }
    [[ ${RELEASE_LINES[0]} == APP_IMAGE=* && ${RELEASE_LINES[1]} == RELEASE_SHA=* &&
       ${RELEASE_LINES[2]} == RELEASE_SEQUENCE=* && ${RELEASE_LINES[3]} == ROLLBACK_COMPATIBLE=* ]] || {
        echo 'Invalid release manifest keys.' >&2; exit 2;
    }
    RELEASE_IMAGE=${RELEASE_LINES[0]#APP_IMAGE=}
    RELEASE_SHA=${RELEASE_LINES[1]#RELEASE_SHA=}
    RELEASE_SEQUENCE=${RELEASE_LINES[2]#RELEASE_SEQUENCE=}
    RELEASE_COMPATIBLE=${RELEASE_LINES[3]#ROLLBACK_COMPATIBLE=}
    require_sha "$RELEASE_SHA"
    [[ $release == "$(release_path "$RELEASE_SHA")" ]] || { echo 'Release directory and SHA differ.' >&2; exit 2; }
    [[ $RELEASE_IMAGE =~ ^ghcr\.io/fikriislamyy/esign-saas@sha256:[0-9a-f]{64}$ ]] || {
        echo 'Release image must be an immutable digest from the trusted registry.' >&2; exit 2;
    }
    [[ $RELEASE_SEQUENCE =~ ^[0-9]+$ && $RELEASE_COMPATIBLE =~ ^(true|false)$ ]] || {
        echo 'Invalid release sequence or compatibility flag.' >&2; exit 2;
    }
}

compose() {
    local release=$1
    shift
    docker compose -p esign-prod \
        --env-file "$ESIGN_SHARED_DIR/.env.production" \
        --env-file "$release/release.env" \
        -f "$release/docker-compose.prod.yml" "$@"
}

current_release() {
    if [[ -L "$ESIGN_ROOT/current" ]]; then
        readlink -f "$ESIGN_ROOT/current"
    fi
}

set_current() {
    local release=$1
    ln -sfn "$release" "$ESIGN_ROOT/.current-next"
    mv -Tf "$ESIGN_ROOT/.current-next" "$ESIGN_ROOT/current"
}

reload_proxy() {
    compose "$1" exec -T proxy caddy reload --config /etc/caddy/Caddyfile
}

enable_maintenance() {
    mkdir -p "$ESIGN_SHARED_DIR/maintenance"
    printf 'respond "Temporarily unavailable" 503\n' > "$ESIGN_SHARED_DIR/maintenance/active.caddy"
    reload_proxy "$1"
}

disable_maintenance() {
    rm -f "$ESIGN_SHARED_DIR/maintenance/active.caddy"
    reload_proxy "$1"
}

check_app() {
    local release=$1
    compose "$release" exec -T app curl -fsS --max-time 5 http://127.0.0.1:10000/ready >/dev/null
    compose "$release" exec -T app test -s /var/www/public/build/manifest.json
}

public_health_url() {
    local domain
    domain=$(sed -n 's/^APP_DOMAIN=//p' "$ESIGN_SHARED_DIR/.env.production")
    [[ $domain =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$ ]] || {
        echo 'APP_DOMAIN must be a plain DNS hostname.' >&2; exit 2;
    }
    printf 'https://%s/ready' "$domain"
}

check_public() {
    local url
    url=$(public_health_url)
    for attempt in {1..12}; do
        if curl -fsS --max-time 5 "$url" >/dev/null; then return 0; fi
        sleep 5
    done
    echo 'Public HTTPS readiness check failed.' >&2
    return 1
}
