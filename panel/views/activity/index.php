<div class="between"><div><div class="section-title" style="margin:0">Audit</div><h1 data-testid="page-title">Activity Log</h1></div></div>
<div class="card" style="margin-top:16px">
  <table class="table" data-testid="activity-table">
    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Target</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="mono muted"><?= h($r['created_at']) ?></td>
        <td><?= h($r['username'] ?? 'system') ?></td>
        <td><span class="chip"><?= h($r['action']) ?></span></td>
        <td class="mono"><?= h($r['target']) ?></td>
        <td class="mono muted"><?= h($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="5" class="muted" style="text-align:center">No activity yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
