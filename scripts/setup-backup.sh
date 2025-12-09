#!/usr/bin/env bash
set -euo pipefail

# Backup/setup script for doomwiki
# - Installs cron utilities, rsync, firewalld (optional), and amazon-efs-utils
# - Mounts EFS volumes and ensures fstab entries for reboot
# - Prepares a cron.d file for backup jobs
# - Optionally copies helper scripts to a destination

# === CONFIG ===
EFS_CACHE_ID="${EFS_CACHE_ID:-fs-0bd3bcda2f2a25776}"
EFS_MEDIA_ID="${EFS_MEDIA_ID:-fs-04b6382ca7829ef79}"
EFS_MOUNT_BASE="${EFS_MOUNT_BASE:-/mnt/efs}"
EFS_CACHE_MOUNT="${EFS_CACHE_MOUNT:-${EFS_MOUNT_BASE}/cache}"
EFS_MEDIA_MOUNT="${EFS_MEDIA_MOUNT:-${EFS_MOUNT_BASE}/media}"
EFS_OPTS="${EFS_OPTS:-_netdev,noresvport,tls}"

ENABLE_FIREWALLD="${ENABLE_FIREWALLD:-true}"
RSYNC_PORT="${RSYNC_PORT:-873}"
OPEN_RSYNC_PORT="${OPEN_RSYNC_PORT:-false}" # set true only if running rsync daemon; ssh is preferred
SSH_KEY_PATH="${SSH_KEY_PATH:-/root/.ssh/doomwiki_backup_ed25519}"

CRON_FILE="${CRON_FILE:-/etc/cron.d/doomwiki-backup}"

# Optional script copy support: set COPY_SCRIPTS (space-separated filenames)
# and COPY_SCRIPTS_FROM (source dir) / COPY_SCRIPTS_TO (dest dir)
COPY_SCRIPTS="${COPY_SCRIPTS:-}"
COPY_SCRIPTS_FROM="${COPY_SCRIPTS_FROM:-}"
COPY_SCRIPTS_TO="${COPY_SCRIPTS_TO:-/usr/local/sbin}"

log() { echo "[$(date -Is)] $*"; }
require_root() { [ "$(id -u)" -eq 0 ] || { log "Run as root or with sudo"; exit 1; }; }

require_root

ensure_package() {
  local pkg="$1"
  if ! rpm -q "${pkg}" >/dev/null 2>&1; then
    dnf -y install "${pkg}"
  fi
}

log "Installing required packages"
ensure_package cronie
ensure_package rsync
ensure_package amazon-efs-utils
ensure_package firewalld
ensure_package openssh-clients

log "Enabling cron service"
systemctl enable crond
systemctl start crond

if [ "${ENABLE_FIREWALLD}" = "true" ] && [ "${OPEN_RSYNC_PORT}" = "true" ]; then
  log "Configuring firewalld (rsync port ${RSYNC_PORT})"
  systemctl enable firewalld
  systemctl start firewalld
  firewall-cmd --permanent --add-port="${RSYNC_PORT}"/tcp >/dev/null 2>&1 || true
  firewall-cmd --reload >/dev/null 2>&1 || true
fi

ensure_mount() {
  local fs_id="$1"
  local mount_point="$2"
  mkdir -p "${mount_point}"
  local fstab_entry="${fs_id}:/ ${mount_point} efs ${EFS_OPTS} 0 0"
  if ! grep -qE "^${fs_id}:/[[:space:]]+${mount_point}[[:space:]]+efs" /etc/fstab 2>/dev/null; then
    log "Adding fstab entry for ${fs_id} at ${mount_point}"
    echo "${fstab_entry}" >> /etc/fstab
  fi
}

log "Configuring EFS mounts"
ensure_mount "${EFS_CACHE_ID}" "${EFS_CACHE_MOUNT}"
ensure_mount "${EFS_MEDIA_ID}" "${EFS_MEDIA_MOUNT}"

log "Mounting EFS volumes"
mount -a

log "Preparing cron file ${CRON_FILE}"
cat > "${CRON_FILE}" <<'EOF'
# Doomwiki backup cron entries
# Example (disabled): run backup script daily at 02:15 as root
# 15 2 * * * root /usr/local/sbin/doomwiki-backup.sh >> /var/log/doomwiki-backup.log 2>&1
EOF
chmod 644 "${CRON_FILE}"

copy_scripts_if_requested() {
  if [ -z "${COPY_SCRIPTS:-}" ] || [ -z "${COPY_SCRIPTS_FROM:-}" ]; then
    return
  fi
  mkdir -p "${COPY_SCRIPTS_TO}"
  for name in ${COPY_SCRIPTS}; do
    local src="${COPY_SCRIPTS_FROM%/}/${name}"
    local dest="${COPY_SCRIPTS_TO%/}/$(basename "${name}")"
    if [ -f "${src}" ]; then
      install -m 0750 -o root -g root "${src}" "${dest}"
      log "Copied ${src} -> ${dest}"
    else
      log "WARN: Script not found: ${src}"
    fi
  done
}
copy_scripts_if_requested

ensure_ssh_key() {
  local key="${SSH_KEY_PATH}"
  local dir
  dir="$(dirname "${key}")"
  mkdir -p "${dir}"
  chmod 700 "${dir}"
  if [ ! -f "${key}" ]; then
    log "Generating SSH keypair for backup rsync over SSH: ${key}"
    ssh-keygen -t ed25519 -N '' -f "${key}" -C "doomwiki-backup" >/dev/null
    chmod 600 "${key}"
    chmod 644 "${key}.pub"
  else
    log "SSH key already present at ${key}"
  fi
}
ensure_ssh_key

log "Backup setup complete."
