<?php /** @var array $collections */ use Stillroom\Admin\View; use Stillroom\Auth; $pageTitle = 'Collections'; ?>
<div class="page-head"><h1>Collections</h1><a class="btn btn-primary" href="/admin/collections/new">+ New collection</a></div>
<p class="muted">Curated playlists such as “Fall asleep”. Put them on the home page under <a href="/admin/home">Home page</a>.</p>
<form method="post" data-sortable-form>
  <?= Auth::csrfField() ?><input type="hidden" name="action" value="reorder">
  <ul class="rows" data-sortable>
    <?php foreach ($collections as $c): ?>
      <li class="row-item"><input type="hidden" name="ids[]" value="<?= (int) $c['id'] ?>"><span class="handle" aria-hidden="true">⋮⋮</span>
        <a class="row-main" href="/admin/collections/<?= (int) $c['id'] ?>"><b><?= h($c['title']) ?></b><span class="muted small"><?= (int) $c['track_count'] ?> tracks · /collection/<?= h($c['slug']) ?></span></a>
        <span class="row-status"><?= View::statusBadge($c['status'], (bool) $c['draft']) ?></span>
        <span class="move"><button type="button" class="icon-btn" data-move="-1" aria-label="Move up">↑</button><button type="button" class="icon-btn" data-move="1" aria-label="Move down">↓</button></span></li>
    <?php endforeach; ?>
  </ul>
  <div class="sticky-actions" data-order-bar hidden><span>Order changed.</span><button class="btn btn-primary">Save order</button></div>
</form>
