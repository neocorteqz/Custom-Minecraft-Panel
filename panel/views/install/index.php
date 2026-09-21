<div class="between"><div><div class="section-title" style="margin:0">Deployment</div><h1 data-testid="page-title">Install on Linux Server</h1><p class="muted">One-line install for Ubuntu 22.04+ / Debian 12+.</p></div></div>

<div class="grid-2" style="margin-top:16px">
  <div class="card">
    <div class="section-title" style="margin-top:0">1. Panel install</div>
    <p class="muted">SSH your VPS as root and run:</p>
    <pre class="mono" style="background:#05070c;border:1px solid var(--border);border-radius:8px;padding:12px;overflow:auto" data-testid="install-cmd">curl -fsSL https://<?= h($host) ?>/install.sh | sudo bash</pre>
    <p class="muted">The installer sets up Nginx, PHP 8.2, MariaDB, Redis, seeds the database, and starts the panel on port 80.</p>

    <div class="section-title">2. Node daemon install</div>
    <p class="muted">On each Linux host that will run game servers:</p>
    <pre class="mono" style="background:#05070c;border:1px solid var(--border);border-radius:8px;padding:12px;overflow:auto">curl -fsSL https://<?= h($host) ?>/install-daemon.sh | sudo bash</pre>

    <div class="section-title">3. Discord bot</div>
    <pre class="mono" style="background:#05070c;border:1px solid var(--border);border-radius:8px;padding:12px;overflow:auto">cd /app/panel/discord-bot && pip install -r requirements.txt
sudo systemctl enable --now apexnode-bot</pre>
  </div>

  <div class="card">
    <div class="section-title" style="margin-top:0">System Requirements</div>
    <table class="table">
      <tr><td class="muted">Minimum</td><td>1 vCPU · 1 GB RAM · 10 GB disk</td></tr>
      <tr><td class="muted">Recommended</td><td>4 vCPU · 8 GB RAM · SSD</td></tr>
      <tr><td class="muted">OS</td><td>Ubuntu 22.04+, Debian 12+</td></tr>
      <tr><td class="muted">Runtime</td><td>PHP 8.2, MariaDB 10.11, Redis 7</td></tr>
    </table>
    <div class="section-title">What gets installed</div>
    <ul class="mono muted" style="padding-left:18px">
      <li>Nginx + PHP-FPM on port 80/443</li>
      <li>MariaDB (auto-provisioned DB user & schema)</li>
      <li>Redis (session + cache)</li>
      <li>Systemd units: <b>apexnode</b>, <b>apexnode-bot</b>, <b>apexnode-daemon</b></li>
      <li>SSL via Certbot (optional prompt)</li>
    </ul>
  </div>
</div>
