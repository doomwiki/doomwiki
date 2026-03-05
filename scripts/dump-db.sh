#!/usr/bin/env bash
set -euo pipefail

# Dump MySQL database using credentials from AWS Secrets Manager.
# Usage:
#   SECRET_ID=<secret> DB_NAME=appdb [AWS_REGION=...] [BACKUP_DIR=...] [RETENTION_DAYS=7] ./dump-db.sh

SECRET_ID="${SECRET_ID:-mysql/doomwiki/prod/db-credentials}"
AWS_REGION="${AWS_REGION:-}"
BACKUP_DIR="${BACKUP_DIR:-/home/doomwiki/dbdump}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
DB_NAME="${DB_NAME:-}"
MEDIAWIKI="/mnt/efs/cache"

current_date=$(date +%Y%m%d)
backup_dbfile="${BACKUP_DIR}/backup${current_date}.sql.gz"

log() { echo "[$(date -Is)] $*"; }

require_field() {
  local field="$1"
  local val
  val=$(jq -er --arg k "$field" '.[$k]' <<<"${SECRET_PAYLOAD}") || {
    log "Missing field '$field' in secret ${SECRET_ID}"
    exit 1
  }
  echo "$val"
}

log "Fetching DB credentials from Secrets Manager (${SECRET_ID})"
if [ -n "${AWS_REGION}" ]; then
  SECRET_PAYLOAD="$(aws --region "${AWS_REGION}" secretsmanager get-secret-value --secret-id "${SECRET_ID}" --query 'SecretString' --output text)"
else
  SECRET_PAYLOAD="$(aws secretsmanager get-secret-value --secret-id "${SECRET_ID}" --query 'SecretString' --output text)"
fi

DB_USER="$(require_field app_user)"
DB_PASSWORD="$(require_field app_password)"
DB_HOST="$(require_field mysql_listen_addr)"
DB_PORT="$(require_field mysql_port)"
if [ -z "${DB_NAME}" ]; then
  DB_NAME="$(require_field db_name)"
fi

log "Ensuring backup dir exists: ${BACKUP_DIR}"
mkdir -p "${BACKUP_DIR}"

log "Pruning backups older than ${RETENTION_DAYS} days in ${BACKUP_DIR}"
find "${BACKUP_DIR}" -type f -name '*.gz' -mtime +"${RETENTION_DAYS}" -exec rm -f {} \;

log "Locking MediaWiki"
sed -i '/wgReadOnly = /s~^//~~' $MEDIAWIKI/LocalSettings.php

log "Running mysqldump for ${DB_NAME}"
mysqldump \
  --lock-tables \
  --default-character-set=BINARY \
  --set-gtid-purged=OFF \
  -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p"${DB_PASSWORD}" \
  "${DB_NAME}" | gzip -9 > "${backup_dbfile}"

log "Unlock MediaWiki"
sed -i '/wgReadOnly = /s~^~//~' $MEDIAWIKI/LocalSettings.php

log "Backup complete: ${backup_dbfile}"
