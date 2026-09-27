#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

sha=${1:-}
release=$(release_path "$sha")
read_release "$release"
[[ -f "$ESIGN_SHARED_DIR/backup-uri" ]] || { echo 'Missing off-host backup URI.' >&2; exit 2; }
backup_base=$(cat "$ESIGN_SHARED_DIR/backup-uri")
[[ $backup_base =~ ^s3://[a-zA-Z0-9._/-]+$ ]] || { echo 'Invalid backup URI.' >&2; exit 2; }
command -v aws >/dev/null || { echo 'AWS CLI is required for off-host backups.' >&2; exit 2; }

mkdir -p "$ESIGN_ROOT/backups"
umask 077
backup_file=$(mktemp "$ESIGN_ROOT/backups/backup-XXXXXXXX.dump")
trap 'rm -f "$backup_file"' EXIT

compose "$release" exec -T postgres sh -c \
    'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump -Fc -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > "$backup_file"
[[ -s $backup_file ]] || { echo 'Database backup is empty.' >&2; exit 1; }
backup_uri="${backup_base%/}/esign-${sha}-$(date -u +%Y%m%dT%H%M%SZ).dump"
aws s3 cp "$backup_file" "$backup_uri" --sse AES256 --only-show-errors
aws s3 ls "$backup_uri" >/dev/null
echo "Verified off-host database backup: $backup_uri"
