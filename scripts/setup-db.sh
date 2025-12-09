#!/usr/bin/env bash
set -euo pipefail

# === CONFIG ===
detect_region() {
  local r=""
  r="${AWS_REGION:-${AWS_DEFAULT_REGION:-}}"
  if [ -z "$r" ]; then
    local token=""
    token="$(curl -s -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600" || true)"
    if [ -n "$token" ]; then
      r="$(curl -s -H "X-aws-ec2-metadata-token: $token" http://169.254.169.254/latest/dynamic/instance-identity/document | jq -r '.region // empty' 2>/dev/null || true)"
    fi
  fi
  if [ -z "$r" ]; then
    r="$(curl -s http://169.254.169.254/latest/meta-data/placement/region || true)"
  fi
  [ -z "$r" ] && r="us-east-1"
  echo "$r"
}

DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"   # optional inline override; otherwise pull from secrets
DB_APP_USER="${DB_APP_USER:-appuser}"
DB_APP_PASSWORD="${DB_APP_PASSWORD:-}"     # optional inline override; otherwise pull from secrets
DB_NAME="${DB_NAME:-appdb}"
MYSQL_LISTEN_ADDR="${MYSQL_LISTEN_ADDR:-0.0.0.0}" # remote access default; tighten if local-only
MYSQL_PORT="${MYSQL_PORT:-5000}"
DB_ROOT_PASSWORD_OLD="${DB_ROOT_PASSWORD_OLD:-}" # optional: prior root password when rotating secrets
GRANT_APP_PROCESS="${GRANT_APP_PROCESS:-true}"   # grant PROCESS globally to app user for mysqldump without --no-tablespaces
ALLOW_CIDR="${ALLOW_CIDR:-203.0.113.0/24}" # used if you open MySQL port
CW_REGION="${CW_REGION:-$(detect_region)}"
ENABLE_FIREWALLD="${ENABLE_FIREWALLD:-true}"   # set to false if you manage security groups only
ENABLE_CLOUDWATCH="${ENABLE_CLOUDWATCH:-true}" # requires outbound internet
ENABLE_FAIL2BAN="${ENABLE_FAIL2BAN:-true}"
USE_SECRETS="${USE_SECRETS:-true}"
SECRET_ID="${SECRET_ID:-mysql/doomwiki/prod/db-credentials}"
SECRET_ROTATION_LAMBDA_ARN="${SECRET_ROTATION_LAMBDA_ARN:-}" # optional; only referenced when creating secret
AWS_REGION="${AWS_REGION:-${CW_REGION}}"

log() { echo "[$(date -Is)] $*"; }
require_root() { [ "$(id -u)" -eq 0 ] || { log "Run as root or with sudo"; exit 1; }; }

require_root

log "Updating base system"
dnf -y update

log "Installing dependencies"
# Replace curl-minimal with full curl to avoid dependency conflicts from MySQL repo
ensure_curl_full() {
  if rpm -q curl-minimal >/dev/null 2>&1; then
    log "Replacing curl-minimal with curl to satisfy MySQL repo dependencies"
    if ! dnf -y swap curl-minimal curl; then
      dnf -y remove curl-minimal || true
      dnf -y install curl
    fi
    dnf clean all
  fi
}
ensure_curl_full

# Install MySQL server, preferring native repo; fall back to Oracle MySQL community repo on failure
install_mysql() {
  if rpm -q mysql-server >/dev/null 2>&1 || rpm -q mysql-community-server >/dev/null 2>&1; then
    log "MySQL server already installed; skipping package install"
    return
  fi
  if dnf -y install mysql-server; then
    return
  fi
  log "Native mysql-server package not found; installing MySQL Community repo and server"
  local repo_pkg="/tmp/mysql-community-release.rpm"
  curl -sS -o "${repo_pkg}" https://repo.mysql.com/mysql80-community-release-el9-1.noarch.rpm
  rpm -Uvh --force "${repo_pkg}"
  # Import multiple potential signing keys; ignore 404s
  rpmkeys --import /etc/pki/rpm-gpg/RPM-GPG-KEY-mysql-2022 || true
  rpmkeys --import https://repo.mysql.com/RPM-GPG-KEY-mysql-2023 || true
  rpmkeys --import https://repo.mysql.com/RPM-GPG-KEY-mysql-2022 || true
  dnf config-manager --disable mysql80-community-source >/dev/null 2>&1 || true
  dnf clean all
  if dnf -y install --enablerepo=mysql80-community --allowerasing mysql-community-server; then
    return
  fi
  log "MySQL Community install hit GPG/metadata issues; retrying with refreshed keys"
  rpmkeys --import /etc/pki/rpm-gpg/RPM-GPG-KEY-mysql-2022 || true
  rpmkeys --import https://repo.mysql.com/RPM-GPG-KEY-mysql-2023 || true
  rpmkeys --import https://repo.mysql.com/RPM-GPG-KEY-mysql-2022 || true
  dnf clean all
  if dnf -y install --enablerepo=mysql80-community --allowerasing mysql-community-server; then
    return
  fi
  # Address potential curl/curl-minimal conflicts by removing curl-minimal variants if present
  if rpm -q curl-minimal >/dev/null 2>&1; then
    log "Removing curl-minimal to resolve dependency conflicts"
    dnf -y remove curl-minimal || true
  fi
  dnf clean all
  log "Last resort: installing MySQL Community server with --nogpgcheck and --allowerasing (ensure repo trust)"
  dnf -y install --enablerepo=mysql80-community --nogpgcheck --allowerasing mysql-community-server
}
install_mysql

dnf -y install firewalld jq curl tar awscli openssl
if [ "${ENABLE_FAIL2BAN}" = "true" ]; then dnf -y install fail2ban; fi

SECRET_PAYLOAD=""
secret_field() {
  local key="$1"
  if [ -n "${SECRET_PAYLOAD}" ]; then
    jq -er --arg k "$key" '.[$k]' <<<"${SECRET_PAYLOAD}" 2>/dev/null || true
  fi
}

gen_password() {
  # Generate URL-safe random password
  openssl rand -base64 32 | tr -d '\n' | tr '/+' '_-'
}

build_secret_payload() {
  # Prefer provided env values; otherwise generate fresh ones
  local root_pw="${DB_ROOT_PASSWORD:-$(gen_password)}"
  local app_pw="${DB_APP_PASSWORD:-$(gen_password)}"
  # Bubble generated values up so the rest of the script uses them
  DB_ROOT_PASSWORD="$root_pw"
  DB_APP_PASSWORD="$app_pw"
  jq -n \
    --arg root_password "$root_pw" \
    --arg app_password "$app_pw" \
    --arg app_user "$DB_APP_USER" \
    --arg db_name "$DB_NAME" \
    --arg listen_addr "$MYSQL_LISTEN_ADDR" \
    --argjson port "$MYSQL_PORT" \
    '{
      root_password: $root_password,
      app_password: $app_password,
      app_user: $app_user,
      db_name: $db_name,
      mysql_listen_addr: $listen_addr,
      mysql_port: $port
    }'
}

ensure_secret_exists() {
  if [ "${USE_SECRETS}" != "true" ]; then
    log "Skipping Secrets Manager integration (USE_SECRETS=${USE_SECRETS})"
    return
  fi

  if aws --region "${AWS_REGION}" secretsmanager describe-secret --secret-id "${SECRET_ID}" >/dev/null 2>&1; then
    log "Found existing secret ${SECRET_ID}"
    return
  fi

  log "Secret ${SECRET_ID} not found; creating with generated credentials"
  local secret_json
  secret_json="$(build_secret_payload)"

  create_args=(--region "${AWS_REGION}" --name "${SECRET_ID}" --secret-string "${secret_json}")
  if [ -n "${SECRET_ROTATION_LAMBDA_ARN}" ]; then
    create_args+=(--rotation-lambda-arn "${SECRET_ROTATION_LAMBDA_ARN}" --rotation-rules AutomaticallyAfterDays=30)
  fi

  aws secretsmanager create-secret "${create_args[@]}"
  log "Created secret ${SECRET_ID}"
}

load_secret_payload() {
  if [ "${USE_SECRETS}" != "true" ]; then return; fi
  SECRET_PAYLOAD="$(aws --region "${AWS_REGION}" secretsmanager get-secret-value --secret-id "${SECRET_ID}" --query 'SecretString' --output text 2>/tmp/secret.err || true)"
  if [ -z "${SECRET_PAYLOAD}" ] || [ "${SECRET_PAYLOAD}" = "null" ]; then
    log "Secret ${SECRET_ID} is empty; populating with generated/override values"
    local secret_json
    secret_json="$(build_secret_payload)"
    aws --region "${AWS_REGION}" secretsmanager put-secret-value --secret-id "${SECRET_ID}" --secret-string "${secret_json}" >/dev/null
    SECRET_PAYLOAD="${secret_json}"
  fi
}

apply_secret_overrides() {
  # Prefer secret fields when present
  local s
  s="$(secret_field root_password)"; [ -n "$s" ] && DB_ROOT_PASSWORD="$s"
  s="$(secret_field app_password)";  [ -n "$s" ] && DB_APP_PASSWORD="$s"
  s="$(secret_field app_user)";      [ -n "$s" ] && DB_APP_USER="$s"
  s="$(secret_field db_name)";       [ -n "$s" ] && DB_NAME="$s"
  s="$(secret_field mysql_listen_addr)"; [ -n "$s" ] && MYSQL_LISTEN_ADDR="$s"
  s="$(secret_field mysql_port)";        [ -n "$s" ] && MYSQL_PORT="$s"
}

ensure_secret_exists
load_secret_payload
apply_secret_overrides

: "${DB_ROOT_PASSWORD:?DB_ROOT_PASSWORD must be set (inline or in secret ${SECRET_ID})}"
: "${DB_APP_PASSWORD:?DB_APP_PASSWORD must be set (inline or in secret ${SECRET_ID})}"

log "Enabling and starting MySQL"
systemctl enable mysqld
systemctl start mysqld

log "Securing MySQL (no interactive prompts)"
# Ensure root can auth via password; handle first-run temporary password and reruns
reset_root_pw_if_needed() {
  if mysql -u root -p"${DB_ROOT_PASSWORD}" --connect-expired-password -e "SELECT 1" >/dev/null 2>&1; then
    log "Root password already set and working"
    return
  fi
  if [ -n "${DB_ROOT_PASSWORD_OLD}" ] && mysql -u root -p"${DB_ROOT_PASSWORD_OLD}" --connect-expired-password -e "SELECT 1" >/dev/null 2>&1; then
    log "Old root password works; rotating to configured password"
    mysql -u root -p"${DB_ROOT_PASSWORD_OLD}" --connect-expired-password <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PASSWORD}';
FLUSH PRIVILEGES;
SQL
    return
  fi
  local tmp_pw=""
  tmp_pw="$(grep -m1 -oE 'temporary password[^:]*: [^ ]+$' /var/log/mysqld.log | awk '{print $NF}' | tail -1 || true)"
  if [ -n "${tmp_pw}" ]; then
    log "Found temporary MySQL root password; resetting to configured password"
    mysql -u root -p"${tmp_pw}" --connect-expired-password <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PASSWORD}';
FLUSH PRIVILEGES;
SQL
    return
  fi
  log "Temporary password not found; attempting socket authentication to reset root password"
  mysql --protocol=socket -u root <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PASSWORD}';
FLUSH PRIVILEGES;
SQL
  if ! mysql -u root -p"${DB_ROOT_PASSWORD}" --connect-expired-password -e "SELECT 1" >/dev/null 2>&1; then
    log "ERROR: Unable to authenticate as root to set password. Provide DB_ROOT_PASSWORD_OLD (prior root password) and rerun."
    exit 1
  fi
}
reset_root_pw_if_needed

mysql -u root -p"${DB_ROOT_PASSWORD}" --connect-expired-password <<SQL
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
SQL

log "Creating application DB/user"
mysql -u root -p"${DB_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${DB_APP_PASSWORD}';
ALTER USER '${DB_APP_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${DB_APP_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_APP_USER}'@'%';
FLUSH PRIVILEGES;
SQL
if [ "${GRANT_APP_PROCESS}" = "true" ]; then
  mysql -u root -p"${DB_ROOT_PASSWORD}" -e "GRANT PROCESS ON *.* TO '${DB_APP_USER}'@'%'; FLUSH PRIVILEGES;"
fi

log "Configuring MySQL bind-address, port, and basic hardening"
ensure_includedir() {
  if ! grep -q '!includedir /etc/my.cnf.d' /etc/my.cnf 2>/dev/null; then
    log "Ensuring /etc/my.cnf includes /etc/my.cnf.d"
    cat >>/etc/my.cnf <<'EOF'
!includedir /etc/my.cnf.d
EOF
  fi
}
ensure_includedir

MYSQL_CNF="/etc/my.cnf.d/99-custom.cnf"
cat >/tmp/mysql_custom.cnf <<EOF
[mysqld]
bind-address=${MYSQL_LISTEN_ADDR}
port=${MYSQL_PORT}
skip-name-resolve
max_connections=200
sql_mode=STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
local_infile=0
secure_file_priv=/var/lib/mysql-files
EOF
install -o root -g root -m 0644 /tmp/mysql_custom.cnf "${MYSQL_CNF}"
systemctl restart mysqld

if [ "${ENABLE_FIREWALLD}" = "true" ]; then
  log "Configuring firewalld"
  systemctl enable firewalld
  systemctl start firewalld
  firewall-cmd --permanent --zone=public --add-service=ssh
  # Open MySQL only if you set a non-loopback bind-address
  if [ "${MYSQL_LISTEN_ADDR}" != "127.0.0.1" ] && [ "${MYSQL_LISTEN_ADDR}" != "::1" ]; then
    firewall-cmd --permanent --add-rich-rule="rule family='ipv4' source address='${ALLOW_CIDR}' port protocol='tcp' port='${MYSQL_PORT}' accept"
  fi
  firewall-cmd --reload
fi

if [ "${ENABLE_FAIL2BAN}" = "true" ]; then
  log "Configuring fail2ban for sshd"
  cat >/etc/fail2ban/jail.d/sshd.local <<'EOF'
[sshd]
enabled = true
port    = ssh
filter  = sshd
logpath = /var/log/secure
maxretry = 5
bantime = 3600
EOF
  systemctl enable fail2ban
  systemctl restart fail2ban
fi

if [ "${ENABLE_CLOUDWATCH}" = "true" ]; then
  log "Installing CloudWatch Agent"
  CW_PKG="amazon-cloudwatch-agent.rpm"
  ARCH="$(uname -m)"
  case "${ARCH}" in
    x86_64) CW_ARCH="amd64" ;;
    aarch64|arm64) CW_ARCH="arm64" ;;
    *) CW_ARCH="amd64" ;;
  esac
  if rpm -q amazon-cloudwatch-agent >/dev/null 2>&1; then
    log "CloudWatch Agent already installed; skipping package install"
  else
    rm -f "/tmp/${CW_PKG}" || true
    curl -s -o "/tmp/${CW_PKG}" "https://amazoncloudwatch-agent-${CW_REGION}.s3.${CW_REGION}.amazonaws.com/amazon_linux/${CW_ARCH}/latest/${CW_PKG}"
    rpm -Uvh "/tmp/${CW_PKG}"
  fi

  log "Configuring CloudWatch Agent for MySQL and system logs"
  CW_INSTANCE_ID_DIM='${!aws:InstanceId}'
  cat >/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json <<EOF
{
  "metrics": {
    "append_dimensions": {
      "InstanceId": "${CW_INSTANCE_ID_DIM}"
    },
    "metrics_collected": {
      "mysql": {
        "measurement": [
          "Threads_connected","Threads_running","Connections","Queries","Bytes_received","Bytes_sent","Slow_queries"
        ],
        "metrics_collection_interval": 60,
        "username": "root",
        "password": "${DB_ROOT_PASSWORD}"
      },
      "statsd": { "metrics_collection_interval": 10, "service_address": ":8125" }
    }
  },
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [
          { "file_path": "/var/log/mysqld.log", "log_group_name": "mysql/mysqld", "log_stream_name": "{instance_id}" },
          { "file_path": "/var/log/secure",    "log_group_name": "system/secure", "log_stream_name": "{instance_id}" }
        ]
      }
    }
  }
}
EOF
  systemctl enable amazon-cloudwatch-agent
  systemctl restart amazon-cloudwatch-agent
fi

log "Configuring logrotate for MySQL"
cat >/etc/logrotate.d/mysqld <<'EOF'
/var/log/mysqld.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 640 mysql mysql
    sharedscripts
    postrotate
        /bin/systemctl reload mysqld.service >/dev/null 2>&1 || true
    endscript
}
EOF

log "Locking down file permissions"
chmod 750 /var/lib/mysql
chown -R mysql:mysql /var/lib/mysql

log "Done. Verify MySQL status:"
systemctl --no-pager status mysqld
