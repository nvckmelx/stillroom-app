<?php /** @var array $counts @var int $changes @var int $collections @var int $mediaSize @var array $settingsDrafts @var array $recent @var array $limits */
use Stillroom\Admin\View; $pageTitle = 'Dashboard'; ?>
<div class="page-head">
  <h1>Dashboard</h1>
  <a class="btn btn-primary" href="/admin/tracks/new">+ New track</a>
</div>
<section class="stats">
  <a class="stat" href="/admin/tracks?status=published"><b><?= (int) ($counts['published'] ?? 0) ?></b><span>Live tracks</span></a>
  <a class="stat" href="/admin/tracks?status=draft"><b><?= (int) ($counts['draft'] ?? 0) ?></b><span>Drafts</span></a>
  <a class="stat" href="/admin/tracks?status=changes"><b><?= $changes ?></b><span>Unpublished changes</span></a>
  <a class="stat" href="/admin/tracks?status=unpublished"><b><?= (int) ($counts['unpublished'] ?? 0) ?></b><span>Hidden</span></a>
  <a class="stat" href="/admin/collections"><b><?= $collections ?></b><span>Collections</span></a>
  <div class="stat"><b><?= View::bytes($mediaSize) ?></b><span>Uploaded media</span></div>
</section>
<?php if ($settingsDrafts): ?>
  <p class="note">Unpublished drafts: <?php foreach ($settingsDrafts as $s): ?><a href="/admin/<?= $s === 'home' ? 'home' : 'site' ?>"><?= $s === 'home' ? 'Home page' : 'Site & SEO' ?></a> <?php endforeach; ?></p>
<?php endif; ?>
<section class="card">
  <h2>How to add music</h2>
  <ol class="steps">
    <li><a href="/admin/tracks/new">New track</a> → title, category, tags.</li>
    <li>Upload the audio (MP3/M4A/OGG/WAV/FLAC, up to <?= (int) $limits['audio'] ?> MB) and a cover, pick a background scene.</li>
    <li><b>Save draft</b> → <b>Preview</b> to see it on the site → <b>Publish</b>. It appears on the site and in the Android app within a minute, no rebuild.</li>
  </ol>
  <p class="muted small">Server upload limit: upload_max_filesize <?= h($limits['upload']) ?>, post_max_size <?= h($limits['post']) ?>.</p>
</section>
<section class="card">
  <h2>Recent activity</h2>
  <?php if (!$recent): ?><p class="muted">Nothing yet.</p><?php endif; ?>
  <ul class="activity">
    <?php foreach ($recent as $a): ?>
      <li><span class="muted"><?= h(substr($a['created_at'], 0, 16)) ?></span> <?= h($a['email'] ?? 'system') ?> — <?= h($a['action']) ?> <?= h($a['entity']) ?> <?= h($a['entity_id']) ?> <span class="muted"><?= h($a['detail']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
