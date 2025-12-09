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
EFS_MEDIA_MOUNT="${EFS_MEDIA_MOUNT:-${EFS_MOUNT_BASE}/images}"
EFS_OPTS="${EFS_OPTS:-_netdev,noresvport,tls,nofail}"
EFS_MOUNT_TIMEOUT="${EFS_MOUNT_TIMEOUT:-20}" # seconds per mount attempt

ENABLE_FIREWALLD="${ENABLE_FIREWALLD:-true}"
RSYNC_PORT="${RSYNC_PORT:-873}"
OPEN_RSYNC_PORT="${OPEN_RSYNC_PORT:-false}" # set true only if running rsync daemon; ssh is preferred
SSH_KEY_PATH="${SSH_KEY_PATH:-/home/doomwiki/.ssh/doomwiki_backup_ed25519}"
GENERATE_SSH_KEY="${GENERATE_SSH_KEY:-true}" # set true to generate a local keypair; default expects operator provides pubkey

CRON_FILE="${CRON_FILE:-/etc/cron.d/doomwiki-backup}"

# Optional script copy support: set COPY_SCRIPTS (space-separated filenames)
# and COPY_SCRIPTS_FROM (source dir) / COPY_SCRIPTS_TO (dest dir)
COPY_SCRIPTS="${COPY_SCRIPTS:-}"
COPY_SCRIPTS_FROM="${COPY_SCRIPTS_FROM:-./}"
COPY_SCRIPTS_TO="${COPY_SCRIPTS_TO:-/home/doomwiki}"

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

if id "doomwiki" >/dev/null 2>&1; then
  log "User 'doomwiki' already exists"
else
  log "Creating user 'doomwiki'"
  useradd -m -s /bin/bash doomwiki
  read -s -p "Enter password for user 'doomwiki': " _PW && echo
  echo "doomwiki:${_PW}" | chpasswd
fi

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

mount_efs_now() {
  local fs_id="$1"
  local mount_point="$2"
  log "Mounting ${fs_id} to ${mount_point} (timeout ${EFS_MOUNT_TIMEOUT}s)"
  if timeout "${EFS_MOUNT_TIMEOUT}" mount -t efs -o "${EFS_OPTS}" "${fs_id}:/" "${mount_point}"; then
    return 0
  else
    log "WARN: Mount attempt timed out or failed for ${fs_id} at ${mount_point}"
    return 1
  fi
}

mount_efs_now "${EFS_CACHE_ID}" "${EFS_CACHE_MOUNT}" || true
mount_efs_now "${EFS_MEDIA_ID}" "${EFS_MEDIA_MOUNT}" || true

log "Ensuring symlink /home/doomwiki/images -> ${EFS_MEDIA_MOUNT}"
ln -sfn "${EFS_MEDIA_MOUNT}" /home/doomwiki/images
chown -h doomwiki:doomwiki /home/doomwiki/images

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

ensure_ssh_dir() {
  local key="${SSH_KEY_PATH}"
  local dir
  dir="$(dirname "${key}")"
  mkdir -p "${dir}"
  chown doomwiki:doomwiki "${dir}"
  chmod 700 "${dir}"
}
ensure_ssh_dir

if [ "${GENERATE_SSH_KEY}" = "true" ]; then
  if [ ! -f "${SSH_KEY_PATH}" ]; then
    log "Generating SSH keypair for backup rsync over SSH: ${SSH_KEY_PATH}"
    sudo -u doomwiki ssh-keygen -t ed25519 -N '' -f "${SSH_KEY_PATH}" -C "doomwiki-backup" >/dev/null
    chown doomwiki:doomwiki "${SSH_KEY_PATH}" "${SSH_KEY_PATH}.pub"
    chmod 600 "${SSH_KEY_PATH}"
    chmod 644 "${SSH_KEY_PATH}.pub"
  else
    log "SSH key already present at ${SSH_KEY_PATH}"
    chown doomwiki:doomwiki "${SSH_KEY_PATH}" "${SSH_KEY_PATH}.pub" >/dev/null 2>&1 || true
  fi
else
  log "Skipping SSH key generation (GENERATE_SSH_KEY=${GENERATE_SSH_KEY}). Place operator public key in $(dirname "${SSH_KEY_PATH}")/authorized_keys"
fi

log "Backup setup complete."
