#!/usr/bin/env bash
# ApexNode Daemon — installs a lightweight game runner on a Linux host
set -euo pipefail
PANEL_URL="${PANEL_URL:-http://apexnode.local}"
NODE_TOKEN="${NODE_TOKEN:-changeme}"

banner() { printf "\n\033[1;36m▸ %s\033[0m\n" "$1"; }
if [[ "$(id -u)" -ne 0 ]]; then echo "Run as root."; exit 1; fi

banner "Installing daemon prerequisites"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq curl python3 python3-pip docker.io ufw
systemctl enable --now docker

banner "Deploying apexnode-daemon"
mkdir -p /opt/apexnode-daemon
cat > /opt/apexnode-daemon/daemon.py <<'PY'
import os, time, socket, urllib.request, json
PANEL = os.environ.get("PANEL_URL", "http://localhost")
TOKEN = os.environ.get("NODE_TOKEN", "")
def report():
    payload = {"host": socket.gethostname(), "token": TOKEN, "ts": int(time.time())}
    req = urllib.request.Request(f"{PANEL}/api/nodes/heartbeat", data=json.dumps(payload).encode(),
                                 headers={"Content-Type": "application/json"})
    try: urllib.request.urlopen(req, timeout=5)
    except Exception as e: print("heartbeat error:", e)
while True:
    report(); time.sleep(15)
PY

cat > /etc/systemd/system/apexnode-daemon.service <<UNIT
[Unit]
Description=ApexNode Node Daemon
After=network.target docker.service

[Service]
Environment=PANEL_URL=${PANEL_URL}
Environment=NODE_TOKEN=${NODE_TOKEN}
ExecStart=/usr/bin/python3 /opt/apexnode-daemon/daemon.py
Restart=always

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now apexnode-daemon
banner "Daemon online → reporting to ${PANEL_URL}"
