<div class="between">
  <div><div class="section-title" style="margin:0">Deploy</div><h1>New Server</h1></div>
  <a href="/servers" class="btn">← Back</a>
</div>

<form method="post" action="/servers" class="card" style="max-width:820px;margin-top:16px" data-testid="deploy-form">
  <?= csrf_field() ?>
  <div class="section-title" style="margin-top:0">1. Identity</div>
  <div class="form-group"><label>Server Name</label><input type="text" name="name" required placeholder="Survival SMP" data-testid="input-name"></div>

  <div class="section-title">2. Egg (optional template)</div>
  <div class="form-group">
    <label>Pick an egg — or leave blank for a bare server</label>
    <select name="egg_id" data-testid="input-egg" onchange="var opt=this.options[this.selectedIndex];if(opt.dataset.game){document.querySelector('[name=game]').value=opt.dataset.game;}">
      <option value="">— None (choose game below) —</option>
      <?php foreach ($eggs as $e): ?>
        <option value="<?= (int)$e['id'] ?>" data-game="<?= h($e['game']) ?>"><?= h($e['name']) ?> · <?= h($e['game']) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="mono muted" style="margin-top:6px">Browse the full <a href="/eggs">Egg Marketplace →</a></p>
  </div>

  <div class="section-title">3. Game & Node</div>
  <div class="grid-2">
    <div class="form-group">
      <label>Game</label>
      <select name="game" id="game-select" required data-testid="input-game">
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

  <div class="section-title">4. Server Installation (loader / modpack)</div>
  <p class="mono muted" style="margin-top:-6px">Choose vanilla, a loader (Paper, Forge, Fabric…), or a modpack source (CurseForge, Modrinth). Leave "None" to install the raw egg.</p>
  <div class="row" id="loader-picker" data-testid="loader-picker" style="gap:8px;margin-bottom:8px"></div>
  <input type="hidden" name="loader_id" id="loader-input" value="">
  <div class="form-group" id="modpack-ref-wrap" style="display:none">
    <label>Modpack slug or ID</label>
    <input name="modpack_ref" id="modpack-ref" placeholder="e.g. all-the-mods-9" data-testid="input-modpack-ref">
  </div>

  <div class="section-title">5. Resources</div>
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

<script>
(function () {
  const gameSel = document.getElementById('game-select');
  const picker = document.getElementById('loader-picker');
  const loaderInput = document.getElementById('loader-input');
  const packWrap = document.getElementById('modpack-ref-wrap');
  const packInput = document.getElementById('modpack-ref');

  async function refresh() {
    const game = gameSel.value;
    picker.innerHTML = '<span class="chip">Loading…</span>';
    const r = await fetch('/json/loaders?game=' + encodeURIComponent(game));
    const data = await r.json();
    picker.innerHTML = '';
    // "None" option first
    picker.appendChild(makeChip({id: '', slug: 'none', name: 'None (raw)', category: 'vanilla', logo_char: '∅', accent_color: '#64748B', requires_pack_id: 0}, true));
    data.forEach((l) => picker.appendChild(makeChip(l, false)));
    // Auto-select None
    loaderInput.value = '';
    packWrap.style.display = 'none';
    packInput.required = false;
  }
  function makeChip(l, selected) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'chip' + (selected ? ' accent' : '');
    btn.dataset.testid = 'loader-chip-' + (l.slug || 'none');
    btn.style.padding = '8px 12px';
    btn.style.cursor = 'pointer';
    btn.style.borderColor = l.accent_color;
    btn.style.color = l.accent_color;
    btn.innerHTML = `<span style="margin-right:6px">${l.logo_char}</span>${l.name}` + (l.popular ? ' ★' : '');
    btn.addEventListener('click', () => {
      loaderInput.value = l.id || '';
      picker.querySelectorAll('button').forEach((b) => b.classList.remove('accent'));
      btn.classList.add('accent');
      if (l.requires_pack_id) {
        packWrap.style.display = 'block';
        packInput.required = true;
      } else {
        packWrap.style.display = 'none';
        packInput.required = false;
      }
    });
    return btn;
  }
  gameSel.addEventListener('change', refresh);
  refresh();
})();
</script>
