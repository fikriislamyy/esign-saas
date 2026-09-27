#!/usr/bin/env bash
set -Eeuo pipefail

test_root=$(mktemp -d "${TMPDIR:-/tmp}/esign-manifest-test-XXXXXXXX")
trap 'rm -rf -- "$test_root"' EXIT
sha=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
release="$test_root/releases/$sha"
mkdir -p "$release"
touch "$release/docker-compose.prod.yml"
common=$(realpath scripts/deploy/common.sh)

cat > "$release/release.env" <<EOF
APP_IMAGE=ghcr.io/fikriislamyy/esign-saas@sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
RELEASE_SHA=$sha
RELEASE_SEQUENCE=42
ROLLBACK_COMPATIBLE=false
EOF
ESIGN_ROOT="$test_root" bash -c 'source "$1"; read_release "$2"' _ "$common" "$release"

sed -i 's/@sha256:/\:latest#sha256:/' "$release/release.env"
if ESIGN_ROOT="$test_root" bash -c 'source "$1"; read_release "$2"' _ "$common" "$release" >/dev/null 2>&1; then
    echo 'Mutable image tag unexpectedly accepted.' >&2
    exit 1
fi

echo 'Release manifest validation passed.'
