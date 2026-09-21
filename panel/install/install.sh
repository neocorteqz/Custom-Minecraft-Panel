#!/usr/bin/env bash
# ApexNode Panel — Linux installer (Ubuntu 22.04+ / Debian 12+)
set -euo pipefail

APEX_DIR="/opt/apexnode"
DB_NAME="apexnode"
DB_USER="apexnode"
DB_PASS="$(openssl rand -hex 16)"

banner() { printf "\n\033[1;36m▸ %s\033[0m\n" "$1"; }

require_root() {
  if [[ "$(id -u)" -ne 0 ]]; then echo "Please run as root." >&2; exit 1; fi
}

require_root
banner "ApexNode installer — locking on to your Linux host"

banner "Installing dependencies"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
  nginx php-cli php-fpm php-mysql php-mbstring php-curl php-xml php-zip \
  mariadb-server redis-server unzip curl git python3 python3-pip

banner "Provisioning MariaDB"
systemctl enable --now mariadb
mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

banner "Deploying ApexNode files to ${APEX_DIR}"
mkdir -p "${APEX_DIR}"
cp -r ./* "${APEX_DIR}/"
mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${APEX_DIR}/db/schema.sql"

cat > "${APEX_DIR}/config/.env" <<ENV
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
ENV

banner "Seeding initial data"
DB_HOST=127.0.0.1 DB_USER="${DB_USER}" DB_PASS="${DB_PASS}" DB_NAME="${DB_NAME}" \
  php "${APEX_DIR}/db/seed.php"

banner "Configuring Nginx"
PHP_SOCK=$(ls /run/php/php*-fpm.sock | head -1)
cat > /etc/nginx/sites-available/apexnode <<NGX
server {
    listen 80 default_server;
    server_name _;
    root ${APEX_DIR}/public;
    index router.php;

    location / { try_files \$uri \$uri/ /router.php\$is_args\$args; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
    }
    location ~ /\. { deny all; }
}
NGX
ln -sf /etc/nginx/sites-available/apexnode /etc/nginx/sites-enabled/apexnode
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

banner "Registering systemd services"
cat > /etc/systemd/system/apexnode-bot.service <<UNIT
[Unit]
Description=ApexNode Discord Bot
After=network.target mariadb.service

[Service]
Type=simple
WorkingDirectory=${APEX_DIR}/discord-bot
EnvironmentFile=-${APEX_DIR}/config/.env
ExecStart=/usr/bin/python3 ${APEX_DIR}/discord-bot/bot.py
Restart=always
User=root

[Install]
WantedBy=multi-user.target
UNIT
pip3 install --quiet -r "${APEX_DIR}/discord-bot/requirements.txt" || true
systemctl daemon-reload
systemctl enable apexnode-bot >/dev/null 2>&1 || true

banner "Enabling Redis"
systemctl enable --now redis-server

banner "Done!"
IP=$(hostname -I | awk '{print $1}')
echo
echo "  🚀  ApexNode Panel is up on:  http://${IP}/"
echo "      Login:   admin / admin123   (change immediately)"
echo "      DB user: ${DB_USER}"
echo "      DB pass: ${DB_PASS}"
echo
echo "  Next: sign in → open Discord tab → paste bot token → \`systemctl start apexnode-bot\`"
