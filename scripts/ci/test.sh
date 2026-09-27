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

compose=(docker compose -f docker-compose.ci.yml)

cleanup() {
    local result=$?
    "${compose[@]}" down -v --remove-orphans || true
    return "$result"
}
trap cleanup EXIT

# 1. Build the PHP and Node images with the source code baked in
"${compose[@]}" build

# 2. Spin up dependency services
"${compose[@]}" up -d --wait --wait-timeout 120 postgres redis

# 3. Ensure storage and test result directories exist inside container
"${compose[@]}" run --rm php mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    ci-results

# 4. Install dependencies and run validations
"${compose[@]}" run --rm php composer install --no-interaction --prefer-dist --no-progress
"${compose[@]}" run --rm node npm ci
"${compose[@]}" run --rm php composer validate --no-check-publish
bash tests/deployment/manifest.sh

# 5. Gate PHP formatting debt with Pint
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

# 6. Execute tests
"${compose[@]}" run --rm php php artisan test --log-junit=ci-results/phpunit.xml
"${compose[@]}" run --rm node npm test -- --reporter=default --reporter=junit --outputFile.junit=ci-results/vitest.xml
"${compose[@]}" run --rm node npm run build