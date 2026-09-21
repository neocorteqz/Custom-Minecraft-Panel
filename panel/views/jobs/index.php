<div class="between">
  <div>
    <div class="section-title" style="margin:0">System</div>
    <h1 data-testid="page-title">Background Jobs</h1>
    <p class="muted">Long-running work — modpack installs, backups, restores. Watch progress live.</p>
  </div>
</div>

<div class="card" style="margin-top:14px">
  <table class="table" data-testid="jobs-table">
    <thead><tr><th>#</th><th>Kind</th><th>Server</th><th>Status</th><th>Progress</th><th>Message</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($jobs as $j):
      $pct = $j['total'] > 0 ? min(100, (int)round(100 * $j['progress'] / $j['total'])) : 0;
      $cls = match($j['status']) { 'completed'=>'online','failed'=>'offline','running'=>'starting','cancelled'=>'offline', default=>'installing' };
    ?>
      <tr data-testid="job-row-<?= (int)$j['id'] ?>">
        <td class="mono">#<?= (int)$j['id'] ?></td>
        <td><span class="chip"><?= h($j['kind']) ?></span></td>
        <td><?= $j['server_name'] ? '<a href="/servers/'.(int)$j['target_id'].'">'.h($j['server_name']).'</a>' : '—' ?></td>
        <td><span class="status status-<?= $cls ?>" data-testid="job-status-<?= (int)$j['id'] ?>"><?= strtoupper($j['status']) ?></span></td>
        <td style="min-width:180px">
          <div class="meter"><span style="width:<?= $pct ?>%"></span></div>
          <div class="mono muted" style="font-size:10px;margin-top:4px" data-testid="job-progress-<?= (int)$j['id'] ?>"><?= (int)$j['progress'] ?>/<?= (int)$j['total'] ?> · <?= $pct ?>%</div>
        </td>
        <td class="mono muted" style="font-size:11px"><?= h($j['message'] ?: ($j['error'] ?: '')) ?></td>
        <td class="mono muted" style="font-size:11px"><?= h($j['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($jobs)): ?>
      <tr><td colspan="7" class="muted" style="text-align:center;padding:30px" data-testid="empty-jobs">No jobs yet. Trigger a modpack install to see one appear.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
// Live refresh every 2s
setInterval(async () => {
  document.querySelectorAll('tr[data-testid^=job-row-]').forEach(async (row) => {
    const id = row.dataset.testid.replace('job-row-', '');
    try {
      const r = await fetch('/json/jobs/' + id);
      if (!r.ok) return;
      const j = await r.json();
      const pill = row.querySelector('[data-testid=job-status-' + id + ']');
      if (pill) {
        pill.textContent = j.status.toUpperCase();
        pill.className = 'status status-' + (j.status === 'completed' ? 'online' : j.status === 'failed' ? 'offline' : j.status === 'running' ? 'starting' : 'installing');
      }
      const meter = row.querySelector('.meter > span');
      if (meter) meter.style.width = j.pct + '%';
      const prog = row.querySelector('[data-testid=job-progress-' + id + ']');
      if (prog) prog.textContent = j.progress + '/' + j.total + ' · ' + j.pct + '%';
    } catch (_) {}
  });
}, 2000);
</script>
