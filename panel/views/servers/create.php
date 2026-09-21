<div class="between">
  <div><div class="section-title" style="margin:0">Deploy</div><h1>New Server</h1></div>
  <a href="/servers" class="btn">← Back</a>
</div>

<form method="post" action="/servers" class="card" style="max-width:820px;margin-top:16px" data-testid="deploy-form">
  <?= csrf_field() ?>
  <div class="section-title" style="margin-top:0">1. Identity</div>
  <div class="form-group"><label>Server Name</label><input type="text" name="name" required placeholder="Survival SMP" data-testid="input-name"></div>

  <div class="section-title">2. Game & Node</div>
  <div class="grid-2">
    <div class="form-group">
      <label>Game</label>
      <select name="game" required data-testid="input-game">
        <option value="minecraft-java">Minecraft: Java Edition</option>
        <option value="minecraft-bedrock">Minecraft: Bedrock Edition</option>
        <option value="cs2">Counter-Strike 2</option>
        <option value="rust">Rust</option>
      </select>
    </div>
    <div class="form-group">
      <label>Node</label>
      <select name="node_id" required data-testid="input-node">
        <?php foreach ($nodes as $n): ?>
          <option value="<?= (int)$n['id'] ?>"><?= h($n['name']) ?> — <?= h($n['ip']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="section-title">3. Resources</div>
  <div class="grid-3">
    <div class="form-group"><label>Port</label><input type="number" name="port" value="25565" data-testid="input-port"></div>
    <div class="form-group"><label>CPU cores</label><input type="number" name="cpu_limit" value="2" min="1" max="32" data-testid="input-cpu"></div>
    <div class="form-group"><label>RAM (MB)</label><input type="number" name="ram_mb" value="2048" step="256" data-testid="input-ram"></div>
    <div class="form-group"><label>Disk (GB)</label><input type="number" name="disk_gb" value="10" min="1" data-testid="input-disk"></div>
  </div>

  <div class="between" style="margin-top:8px">
    <p class="mono muted">// resources will be reserved on the selected node.</p>
    <button class="btn btn-primary" data-testid="submit-deploy">▶ Deploy Server</button>
  </div>
</form>
