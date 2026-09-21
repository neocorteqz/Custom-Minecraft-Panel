<?php $g = game_meta($s['game']); ?>
<div class="between">
  <div>
    <div class="section-title" style="margin:0"><?= h($g['label']) ?><?php if (!empty($s['egg_name'])): ?> · <?= h($s['egg_name']) ?><?php endif; ?></div>
    <h1 data-testid="server-name"><?= h($s['name']) ?> <span class="status status-<?= h($s['status']) ?>" data-testid="server-status"><?= strtoupper($s['status']) ?></span></h1>
    <p class="mono muted">◉ <?= h($s['node_name']) ?> · :<?= (int)$s['port'] ?> · <?= (int)$s['players_online'] ?>/<?= (int)$s['players_max'] ?> players</p>
  </div>
  <div class="row">
    <a href="/servers/<?= (int)$s['id'] ?>/files" class="btn" data-testid="tab-files">≡ Files</a>
    <a href="/servers/<?= (int)$s['id'] ?>/backups" class="btn" data-testid="tab-backups">◱ Backups</a>
    <form method="post" action="/servers/action" style="margin:0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="start"><button class="btn btn-primary btn-sm" data-testid="btn-start">▶ Start</button></form>
    <form method="post" action="/servers/action" style="margin:0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="stop"><button class="btn btn-danger btn-sm" data-testid="btn-stop">■ Stop</button></form>
    <form method="post" action="/servers/action" style="margin:0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="restart"><button class="btn btn-sm" data-testid="btn-restart">↻ Restart</button></form>
    <form method="post" action="/servers/action" style="margin:0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="kill"><button class="btn btn-sm" data-testid="btn-kill">☠ Kill</button></form>
    <form method="post" action="/servers/delete" data-confirm="Delete this server?" style="margin:0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-danger" data-testid="btn-delete">✕ Delete</button></form>
  </div>
</div>

<div class="bento" style="margin-top:16px">
  <div class="card span-3" id="console-mount" data-server-id="<?= (int)$s['id'] ?>" data-testid="console-panel">
    <div class="card-title"><h3>▶ Live Console</h3><span class="chip accent blink">STREAM</span></div>
    <div class="console">
      <div class="log" data-testid="console-log"></div>
      <div class="cmd">
        <span class="prompt">$</span>
        <input type="text" placeholder="type command (say hello, list, help) and press Enter" data-testid="console-input" autocomplete="off">
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-title"><h3>Resources</h3></div>
    <div class="form-group">
      <div class="mono muted between"><span>CPU</span><span data-cpu-txt><?= round((float)$s['cpu_usage']) ?>%</span></div>
      <div class="meter"><span data-cpu-bar style="width: <?= min(100,(float)$s['cpu_usage']) ?>%"></span></div>
    </div>
    <div class="form-group">
      <div class="mono muted between"><span>RAM</span><span><?= (int)$s['ram_usage_mb'] ?> / <?= (int)$s['ram_mb'] ?> MB</span></div>
      <div class="meter"><span data-ram-bar style="width: <?= min(100,($s['ram_usage_mb']/max(1,$s['ram_mb']))*100) ?>%"></span></div>
    </div>
    <div class="form-group">
      <div class="mono muted between"><span>DISK</span><span><?= (int)$s['disk_gb'] ?> GB</span></div>
      <div class="meter warn"><span style="width:24%"></span></div>
    </div>
    <div class="section-title">Configuration</div>
    <table class="table">
      <tr><td class="muted">Version</td><td class="mono"><?= h($s['version']) ?></td></tr>
      <?php if (!empty($s['loader_name'])): ?>
        <tr><td class="muted">Loader</td>
            <td><span class="chip" style="color:<?= h($s['loader_color']) ?>;border-color:<?= h($s['loader_color']) ?>"><?= h($s['loader_icon']) ?> <?= h($s['loader_name']) ?></span></td></tr>
      <?php endif; ?>
      <?php if (!empty($s['modpack_ref'])): ?>
        <tr><td class="muted">Modpack</td><td class="mono"><?= h($s['modpack_ref']) ?></td></tr>
      <?php endif; ?>
      <tr><td class="muted">Port</td><td class="mono"><?= (int)$s['port'] ?></td></tr>
      <tr><td class="muted">Node</td><td class="mono"><?= h($s['node_name']) ?></td></tr>
      <tr><td class="muted">CPU limit</td><td class="mono"><?= (int)$s['cpu_limit'] ?> cores</td></tr>
    </table>
  </div>
</div>
