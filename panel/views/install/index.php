<div class="between"><div><div class="section-title" style="margin:0">Deployment</div><h1 data-testid="page-title">Install on Linux Server</h1><p class="muted">One-line install for Ubuntu 22.04+ / Debian 12+. Standalone, or co-existing with cPanel / Plesk / DirectAdmin.</p></div></div>

<div class="grid-2" style="margin-top:16px">
  <div class="card">
    <div class="section-title" style="margin-top:0">1. Choose your host layout</div>
    <div class="row" id="host-picker" data-testid="host-picker" style="flex-wrap:wrap">
      <?php $modes = [
        ['standalone',   'Standalone',  'No existing panel — bind Nginx to :80'],
        ['cpanel',       'cPanel',      'Reverse-proxied from WHM Pre-VirtualHost'],
        ['plesk',        'Plesk',       'Apache & nginx settings → proxy_pass'],
        ['directadmin',  'DirectAdmin', 'CustomBuild custom_httpd.conf snippet'],
        ['nginx',        'Nginx (any)', 'Generic reverse-proxy vhost'],
      ]; foreach ($modes as $m): ?>
        <label class="chip" data-mode="<?= $m[0] ?>" style="padding:10px 14px;cursor:pointer;flex:1;min-width:150px;text-align:center" data-testid="host-mode-<?= $m[0] ?>">
          <input type="radio" name="mode" value="<?= $m[0] ?>" <?= $m[0]==='standalone'?'checked':''?> style="margin-right:6px">
          <b><?= h($m[1]) ?></b><br>
          <span class="mono muted" style="font-size:10px"><?= h($m[2]) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="section-title">2. Pick a port</div>
    <div class="form-group">
      <label>Panel port</label>
      <input type="number" id="panel-port" value="8443" min="1024" max="65535" data-testid="input-panel-port">
      <p class="mono muted" style="margin-top:6px">Standalone mode binds public. Coexist modes bind 127.0.0.1 only — set anything free (e.g. 8443, 2087, 8880).</p>
    </div>

    <div class="section-title">3. One-liner</div>
    <pre class="mono" style="background:#05070c;border:1px solid var(--border);border-radius:8px;padding:12px;overflow:auto" data-testid="install-cmd">curl -fsSL https://<?= h($host) ?>/install.sh | sudo bash -s -- --port 8443 --coexist standalone</pre>
    <button class="btn btn-primary btn-sm" data-testid="copy-install-btn" onclick="navigator.clipboard.writeText(document.querySelector('[data-testid=install-cmd]').textContent)">⧉ Copy</button>

    <div class="section-title">4. Node daemon (game hosts)</div>
    <pre class="mono" style="background:#05070c;border:1px solid var(--border);border-radius:8px;padding:12px;overflow:auto">curl -fsSL https://<?= h($host) ?>/install-daemon.sh | sudo bash</pre>
  </div>

  <div class="card">
    <div class="section-title" style="margin-top:0">Reverse-proxy snippet</div>
    <p class="muted" data-testid="snippet-intro">The exact block to paste into your existing panel:</p>
    <pre class="mono" id="snippet" data-testid="reverse-proxy-snippet" style="background:#05070c;border:1px solid var(--border);border-radius:8px;padding:12px;overflow:auto;font-size:11px;white-space:pre-wrap"></pre>

    <div class="section-title">What gets installed</div>
    <ul class="mono muted" style="padding-left:18px;font-size:12px">
      <li>Nginx + PHP-FPM (loopback in coexist mode)</li>
      <li>MariaDB (auto-provisioned DB user & schema)</li>
      <li>Redis (session + cache)</li>
      <li>OpenJDK 17 JRE (for Paper / Purpur / Forge runtimes)</li>
      <li>Systemd units: <b>apex-daemon</b>, <b>apex-backup</b>, <b>apexnode-bot</b></li>
    </ul>

    <div class="section-title">System requirements</div>
    <table class="table">
      <tr><td class="muted">Minimum</td><td>1 vCPU · 1 GB RAM · 10 GB SSD</td></tr>
      <tr><td class="muted">Recommended</td><td>4 vCPU · 8 GB RAM · SSD</td></tr>
      <tr><td class="muted">OS</td><td>Ubuntu 22.04+, Debian 12+</td></tr>
    </table>
  </div>
</div>

<script>
(function () {
  const port = document.getElementById('panel-port');
  const cmd = document.querySelector('[data-testid=install-cmd]');
  const snippet = document.getElementById('snippet');
  const host = '<?= h($host) ?>';

  const templates = {
    standalone: () => 'Standalone mode — the installer configures Nginx as the public webserver on the port you chose. No control-panel integration needed.',
    cpanel: (p) => `# WHM → Home » Service Configuration » Apache Configuration » Include Editor » Pre VirtualHost (2.4)

<VirtualHost *:80>
    ServerName apex.yourdomain.tld
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:${p}/
    ProxyPassReverse / http://127.0.0.1:${p}/
</VirtualHost>

# Then: systemctl reload httpd`,
    plesk: (p) => `# Plesk → Domains → apex.yourdomain.tld → Apache & nginx Settings
# Paste this in the "Additional nginx directives" box (also uncheck "Proxy mode"):

location / {
    proxy_pass http://127.0.0.1:${p};
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}`,
    directadmin: (p) => `# 1. In DirectAdmin: create subdomain apex.yourdomain.tld
# 2. Edit /usr/local/directadmin/data/users/USER/domains/DOMAIN.custom_httpd.conf

|?PROXY=http://127.0.0.1:${p}|
|?VHOST=apex.yourdomain.tld|

# 3. cd /usr/local/directadmin/custombuild && ./build rewrite_confs`,
    nginx: (p) => `# /etc/nginx/conf.d/apex.conf on your existing web-panel host

server {
    listen 80;
    server_name apex.yourdomain.tld;
    location / {
        proxy_pass http://127.0.0.1:${p};
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}`,
  };

  function refresh() {
    const mode = document.querySelector('input[name=mode]:checked').value;
    const p = parseInt(port.value, 10) || 8443;
    cmd.textContent = `curl -fsSL https://${host}/install.sh | sudo bash -s -- --port ${p} --coexist ${mode}`;
    snippet.textContent = templates[mode](p);
  }
  document.querySelectorAll('input[name=mode]').forEach(r => r.addEventListener('change', refresh));
  port.addEventListener('input', refresh);
  refresh();
})();
</script>
