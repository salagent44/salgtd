#!/usr/bin/env bash
#
# Deploy Sal GTD to Ubuntu 24.04 VPS (run as root)
#
# How to deploy:
#   1. Point your domain's DNS (A record) to your VPS IP
#   2. SSH in as root and either:
#      a) bash <(curl -fsSL https://raw.githubusercontent.com/salagent44/salgtd/main/deploy.sh)
#      b) git clone https://github.com/salagent44/salgtd.git /opt/gtd-app && bash /opt/gtd-app/deploy.sh
#
# This script is idempotent — safe to run multiple times.
# Requires Ubuntu 24.04 LTS.
# Caddy handles HTTPS automatically via Let's Encrypt.
# SQLite data persists in a Docker volume (gtd-app_gtd-data).
#
set -euo pipefail

REPO_URL="https://github.com/salagent44/salgtd"
INSTALL_DIR="/opt/gtd-app"

# --- Verify Ubuntu 24.04 ---
if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [[ "${ID:-}" != "ubuntu" || ! "${VERSION_ID:-}" =~ ^24\. ]]; then
        echo "ERROR: This script requires Ubuntu 24.04 LTS."
        echo "       Detected: ${PRETTY_NAME:-unknown}"
        exit 1
    fi
else
    echo "ERROR: Cannot detect OS. This script requires Ubuntu 24.04 LTS."
    exit 1
fi

echo "==> Running on ${PRETTY_NAME}"

# Helper: update or add a key=value in .env
set_env() {
    local key="$1" value="$2" file="$3"
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$file"
    else
        echo "${key}=${value}" >> "$file"
    fi
}

# --- Install Docker via official apt repo (Ubuntu 24.04) ---
echo "==> Installing Docker..."
if ! command -v docker &>/dev/null; then
    apt-get update
    apt-get install -y ca-certificates curl gnupg

    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc

    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
      $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}") stable" | \
      tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
else
    echo "    Docker already installed, skipping."
fi

# --- Firewall (ufw) — allow only SSH, HTTP, HTTPS ---
echo "==> Configuring firewall..."
if command -v ufw &>/dev/null; then
    ufw allow 22/tcp
    ufw allow 25/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
    echo "    Firewall: SSH, SMTP, HTTP, HTTPS allowed."
else
    echo "    ufw not found, skipping firewall config."
fi

echo "==> Cloning repo..."
if [ -d "$INSTALL_DIR" ]; then
    echo "    $INSTALL_DIR exists, pulling latest..."
    git -C "$INSTALL_DIR" fetch origin main
    git -C "$INSTALL_DIR" reset --hard origin/main
else
    apt-get install -y git
    git clone "$REPO_URL" "$INSTALL_DIR"
fi

cd "$INSTALL_DIR"

# --- APP_KEY (generate once, never overwrite) ---
echo "==> Checking APP_KEY..."
if [ ! -f .env ]; then
    touch .env
fi

if ! grep -q "^APP_KEY=" .env || [ -z "$(grep '^APP_KEY=' .env | cut -d= -f2-)" ]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    set_env "APP_KEY" "$APP_KEY" .env
    echo "    APP_KEY generated."
else
    echo "    APP_KEY already set, keeping it."
fi

# --- Domain ---
echo ""
CURRENT_DOMAIN=$(grep '^DOMAIN=' .env 2>/dev/null | cut -d= -f2- || true)

if [ -n "$CURRENT_DOMAIN" ]; then
    echo "  Current domain: $CURRENT_DOMAIN"
    read -rp "  Update domain? [y/N]: " UPDATE_DOMAIN < /dev/tty
else
    UPDATE_DOMAIN="y"
fi

if [[ "${UPDATE_DOMAIN,,}" == "y" ]]; then
    read -rp "  Domain (e.g. tasks.example.com) [${CURRENT_DOMAIN:-}]: " DOMAIN < /dev/tty
    DOMAIN="${DOMAIN:-$CURRENT_DOMAIN}"
    if [ -z "$DOMAIN" ]; then
        echo "ERROR: Domain is required."
        exit 1
    fi
    set_env "DOMAIN" "$DOMAIN" .env
    echo "    Domain set to $DOMAIN"
else
    DOMAIN="$CURRENT_DOMAIN"
fi

# --- Timezone ---
CURRENT_TZ=$(grep '^TIMEZONE=' .env 2>/dev/null | cut -d= -f2- || true)

if [ -n "$CURRENT_TZ" ]; then
    echo "  Current timezone: $CURRENT_TZ"
    read -rp "  Update timezone? [y/N]: " UPDATE_TZ < /dev/tty
else
    UPDATE_TZ="y"
fi

if [[ "${UPDATE_TZ,,}" == "y" ]]; then
    read -rp "  Timezone (e.g. America/New_York) [${CURRENT_TZ:-America/Chicago}]: " TIMEZONE < /dev/tty
    TIMEZONE="${TIMEZONE:-${CURRENT_TZ:-America/Chicago}}"
    set_env "TIMEZONE" "$TIMEZONE" .env
    echo "    Timezone set to $TIMEZONE"
fi

# --- IP allowlist (optional) ---
CURRENT_ALLOWED_IPS=$(grep '^ALLOWED_IPS=' .env 2>/dev/null | cut -d= -f2- || true)

if [ -n "$CURRENT_ALLOWED_IPS" ]; then
    echo "  Current IP allowlist: $CURRENT_ALLOWED_IPS"
    read -rp "  Update IP allowlist? [y/N]: " UPDATE_IPS < /dev/tty
else
    read -rp "  Restrict access to specific IPs? [y/N]: " UPDATE_IPS < /dev/tty
fi

if [[ "${UPDATE_IPS,,}" == "y" ]]; then
    echo "    Enter CIDR ranges separated by commas (e.g. 104.36.90.0/24,10.0.0.0/8)"
    echo "    Leave blank to allow all IPs."
    read -rp "  Allowed IPs [${CURRENT_ALLOWED_IPS:-any}]: " ALLOWED_IPS < /dev/tty
    ALLOWED_IPS="${ALLOWED_IPS:-$CURRENT_ALLOWED_IPS}"
    set_env "ALLOWED_IPS" "$ALLOWED_IPS" .env
    if [ -n "$ALLOWED_IPS" ]; then
        echo "    Access restricted to: $ALLOWED_IPS"
    else
        echo "    Access open to all IPs."
    fi
else
    ALLOWED_IPS="$CURRENT_ALLOWED_IPS"
fi

# --- Generate Caddyfile ---
echo "==> Generating Caddyfile..."
if [ -n "$ALLOWED_IPS" ]; then
    # Build space-separated list from comma-separated input
    IP_LIST=$(echo "$ALLOWED_IPS" | tr ',' ' ')
    cat > docker/Caddyfile <<CADDYEOF
${DOMAIN} {
    @blocked not remote_ip ${IP_LIST}
    respond @blocked 403

    reverse_proxy gtd:80
}
CADDYEOF
    echo "    Caddyfile: $DOMAIN with IP restriction"
else
    cat > docker/Caddyfile <<CADDYEOF
${DOMAIN} {
    reverse_proxy gtd:80
}
CADDYEOF
    echo "    Caddyfile: $DOMAIN open to all"
fi

# --- Admin credentials ---
echo ""
CURRENT_EMAIL=$(grep '^ADMIN_EMAIL=' .env 2>/dev/null | cut -d= -f2- || true)
CURRENT_NAME=$(grep '^ADMIN_NAME=' .env 2>/dev/null | cut -d= -f2- || true)

if [ -n "$CURRENT_EMAIL" ]; then
    echo "  Current admin: $CURRENT_NAME <$CURRENT_EMAIL>"
    read -rp "  Update admin credentials? [y/N]: " UPDATE_CREDS < /dev/tty
else
    UPDATE_CREDS="y"
fi

if [[ "${UPDATE_CREDS,,}" == "y" ]]; then
    read -rp "  Admin email [${CURRENT_EMAIL:-admin@gtd.local}]: " ADMIN_EMAIL < /dev/tty
    ADMIN_EMAIL="${ADMIN_EMAIL:-${CURRENT_EMAIL:-admin@gtd.local}}"
    read -rp "  Admin name [${CURRENT_NAME:-Admin}]: " ADMIN_NAME < /dev/tty
    ADMIN_NAME="${ADMIN_NAME:-${CURRENT_NAME:-Admin}}"
    read -rsp "  Admin password: " ADMIN_PASSWORD < /dev/tty
    echo
    if [ -z "$ADMIN_PASSWORD" ]; then
        echo "    Password unchanged (kept existing)."
    else
        set_env "ADMIN_PASSWORD" "$ADMIN_PASSWORD" .env
    fi
    set_env "ADMIN_EMAIL" "$ADMIN_EMAIL" .env
    set_env "ADMIN_NAME" "$ADMIN_NAME" .env
    echo "    Admin credentials saved."
else
    echo "    Keeping existing credentials."
fi

# --- S3 Backup Configuration ---
echo ""
CURRENT_S3_BUCKET=$(grep '^S3_BUCKET=' .env 2>/dev/null | cut -d= -f2- || true)

if [ -n "$CURRENT_S3_BUCKET" ]; then
    echo "  Current S3 bucket: $CURRENT_S3_BUCKET"
    read -rp "  Update S3 backup config? [y/N]: " UPDATE_S3 < /dev/tty
else
    read -rp "  Configure S3 backups? [y/N]: " UPDATE_S3 < /dev/tty
fi

if [[ "${UPDATE_S3,,}" == "y" ]]; then
    CURRENT_KEY_ID=$(grep '^AWS_ACCESS_KEY_ID=' .env 2>/dev/null | cut -d= -f2- || true)
    CURRENT_REGION=$(grep '^S3_REGION=' .env 2>/dev/null | cut -d= -f2- || true)

    read -rp "  AWS Access Key ID [${CURRENT_KEY_ID:-}]: " AWS_ACCESS_KEY_ID < /dev/tty
    AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID:-$CURRENT_KEY_ID}"

    read -rsp "  AWS Secret Access Key [keep existing]: " AWS_SECRET_ACCESS_KEY < /dev/tty
    echo
    if [ -n "$AWS_SECRET_ACCESS_KEY" ]; then
        set_env "AWS_SECRET_ACCESS_KEY" "$AWS_SECRET_ACCESS_KEY" .env
    fi

    read -rp "  S3 Bucket name [${CURRENT_S3_BUCKET:-}]: " S3_BUCKET < /dev/tty
    S3_BUCKET="${S3_BUCKET:-$CURRENT_S3_BUCKET}"

    read -rp "  S3 Region [${CURRENT_REGION:-us-east-1}]: " S3_REGION < /dev/tty
    S3_REGION="${S3_REGION:-${CURRENT_REGION:-us-east-1}}"

    set_env "AWS_ACCESS_KEY_ID" "$AWS_ACCESS_KEY_ID" .env
    set_env "S3_BUCKET" "$S3_BUCKET" .env
    set_env "S3_REGION" "$S3_REGION" .env
    echo "    S3 backup config saved."
else
    echo "    Skipping S3 configuration."
fi

# --- Set commit hash for build ---
COMMIT_HASH=$(git -C "$INSTALL_DIR" rev-parse --short HEAD 2>/dev/null || echo "unknown")
set_env "COMMIT_HASH" "$COMMIT_HASH" .env

echo ""
echo "==> Building and starting containers..."
docker compose up -d --build

# --- Install update watcher cron ---
echo "==> Installing update watcher..."
mkdir -p /opt/gtd-app/scripts
cat > /opt/gtd-app/scripts/auto-update.sh <<'UPDATEEOF'
#!/bin/bash
# Triggered by in-app update button — checks for trigger file on the data volume
VOLUME_PATH=$(docker volume inspect gtd-app_gtd-data --format '{{ .Mountpoint }}' 2>/dev/null)
[ -z "$VOLUME_PATH" ] && exit 0

TRIGGER="$VOLUME_PATH/update-trigger"
STATUS="$VOLUME_PATH/update-status"

[ ! -f "$TRIGGER" ] && exit 0

rm -f "$TRIGGER"
echo "updating:$(date -Iseconds)" > "$STATUS"

cd /opt/gtd-app || exit 1
git fetch origin main
git reset --hard origin/main

COMMIT_HASH=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
sed -i "s|^COMMIT_HASH=.*|COMMIT_HASH=${COMMIT_HASH}|" .env 2>/dev/null || echo "COMMIT_HASH=${COMMIT_HASH}" >> .env

docker compose up -d --build

echo "done:$(date -Iseconds)" > "$STATUS"
UPDATEEOF
chmod +x /opt/gtd-app/scripts/auto-update.sh

# Cron: check every minute for update trigger
echo "* * * * * root /opt/gtd-app/scripts/auto-update.sh >> /var/log/gtd-update.log 2>&1" > /etc/cron.d/gtd-update
chmod 644 /etc/cron.d/gtd-update

echo ""
echo "============================================"
echo "  Sal GTD is deploying!"
echo ""
echo "  URL:  https://${DOMAIN}"
echo ""
echo "  Caddy will auto-provision the TLS cert."
echo "  First request may take a few seconds."
echo ""
echo "  Useful commands:"
echo "    docker compose logs -f        # view logs"
echo "    docker compose down            # stop"
echo "    docker compose up -d --build   # rebuild & restart"
echo ""
echo "  Data is stored in docker volume 'gtd-app_gtd-data'"
echo "============================================"
