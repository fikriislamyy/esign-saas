#!/usr/bin/env bash
set -Eeuo pipefail

if [[ ! -f composer.lock || ! -f package-lock.json ]]; then
    echo 'Run from the repository root with committed lockfiles.' >&2
    exit 2
fi

build_id=${BUILD_NUMBER:-local-$$}
if [[ ! $build_id =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo 'BUILD_NUMBER contains unsupported characters.' >&2
    exit 2
fi
export COMPOSE_PROJECT_NAME="esign-ci-${build_id}"
export CI_UID="$(id -u)" CI_GID="$(id -g)"
export CI_APP_KEY="base64:$(openssl rand -base64 32)"
export CI_SOURCE_DIR="$(pwd)/.ci-source-${build_id}"
mkdir -p "$CI_SOURCE_DIR"

compose=(docker compose -f docker-compose.ci.yml)
cleanup() {
    local result=$?
    "${compose[@]}" down -v --remove-orphans || true
    mkdir -p ci-results
    if [[ -d "$CI_SOURCE_DIR/ci-results" ]]; then
        cp -a "$CI_SOURCE_DIR/ci-results/." ci-results/
    fi
    rm -rf -- "$CI_SOURCE_DIR"
    return "$result"
}
trap cleanup EXIT

tar --exclude='./.git' --exclude='./.env' --exclude='./.env.*' \
    --exclude='./vendor' --exclude='./node_modules' --exclude='./ci-results' \
    --exclude='./storage' --exclude='./bootstrap/cache' -cf - . | tar -xf - -C "$CI_SOURCE_DIR"
mkdir -p "$CI_SOURCE_DIR/storage/app/public" "$CI_SOURCE_DIR/storage/framework/cache" \
    "$CI_SOURCE_DIR/storage/framework/sessions" "$CI_SOURCE_DIR/storage/framework/testing" \
    "$CI_SOURCE_DIR/storage/framework/views" "$CI_SOURCE_DIR/storage/logs" \
    "$CI_SOURCE_DIR/bootstrap/cache"
mkdir -p "$CI_SOURCE_DIR/ci-results"
"${compose[@]}" up -d --wait --wait-timeout 120 postgres redis
"${compose[@]}" run --rm php composer install --no-interaction --prefer-dist --no-progress
"${compose[@]}" run --rm node npm ci
"${compose[@]}" run --rm php composer validate --no-check-publish
bash tests/deployment/manifest.sh

# The repository has existing Pint debt. Gate every PHP file changed by this
# branch/release, including local edits during a rehearsal.
if [[ -n ${CHANGE_TARGET:-} ]]; then
    [[ $CHANGE_TARGET =~ ^[A-Za-z0-9._/-]+$ ]] || { echo 'Invalid CHANGE_TARGET.' >&2; exit 2; }
    git fetch --no-tags origin "$CHANGE_TARGET"
    pint_base=$(git merge-base HEAD FETCH_HEAD)
else
    pint_base=$(git rev-parse HEAD^)
fi
mapfile -d '' -t changed_php < <(git diff --name-only -z "$pint_base" HEAD -- '*.php')
mapfile -d '' -t local_php < <(git diff --name-only -z HEAD -- '*.php')
changed_php+=("${local_php[@]}")
if (( ${#changed_php[@]} )); then
    "${compose[@]}" run --rm php vendor/bin/pint --test "${changed_php[@]}"
fi
"${compose[@]}" run --rm php php artisan test --log-junit=ci-results/phpunit.xml
"${compose[@]}" run --rm node npm test -- --reporter=default --reporter=junit --outputFile.junit=ci-results/vitest.xml
"${compose[@]}" run --rm node npm run build
