<?php /** @var array $backups @var string $root @var array $pending */ use Stillroom\Admin\View; use Stillroom\Auth; $pageTitle = 'Backups'; ?>
<div class="page-head"><h1>Backups</h1></div>
<p class="muted">A backup is one zip with the whole database (tracks, collections, settings, users) and every uploaded file. They are kept outside the website folder; the newest <?= Stillroom\Env::int('BACKUP_KEEP', 14) ?> are kept.</p>
<form method="post" class="card form"><?= Auth::csrfField() ?><input type="hidden" name="action" value="create">
  <label class="row"><input type="radio" name="media" value="yes" checked> Database + uploaded files</label>
  <label class="row"><input type="radio" name="media" value="no"> Database only (small)</label>
  <button class="btn btn-primary">Create backup now</button></form>
<ul class="rows">
  <?php if (!$backups): ?><li class="empty">No backups yet.</li><?php endif; ?>
  <?php foreach ($backups as $b): ?>
    <li class="row-item"><span class="row-main"><b><?= h($b['name']) ?></b><span class="muted small"><?= gmdate('Y-m-d H:i', $b['time']) ?> UTC · <?= View::bytes((int) $b['size']) ?></span></span>
      <a class="btn btn-sm" href="/admin/backups/download/<?= h($b['name']) ?>">Download</a></li>
  <?php endforeach; ?>
</ul>
<section class="card"><h2>Database</h2>
  <?php if ($pending): ?>
    <p>After a code update the database needs <?= count($pending) ?> update(s): <code><?= h(implode(', ', $pending)) ?></code>. A database backup is made first.</p>
    <form method="post"><?= Auth::csrfField() ?><button class="btn btn-primary" name="action" value="migrate">Apply database updates</button></form>
  <?php else: ?><p class="muted">Up to date.</p><?php endif; ?>
</section>
<section class="card"><h2>Automatic daily backup</h2>
  <p>In hPanel → Advanced → Cron Jobs add a daily job:</p>
  <pre>/usr/bin/php <?= h($root) ?>/bin/backup.php</pre>
  <p class="muted small">Restore (SSH): <code>php bin/restore.php storage/backups/&lt;file&gt;.zip --yes</code> — it first saves a safety backup of the current state.</p></section>
