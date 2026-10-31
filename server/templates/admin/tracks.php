<?php /** @var array $tracks @var array $categories @var string $q @var string $status @var int $category */
use Stillroom\Admin\View; use Stillroom\Auth; use Stillroom\Media; $pageTitle = 'Tracks';
$filtered = $q !== '' || $status !== '' || $category > 0; ?>
<div class="page-head">
  <h1>Tracks <span class="muted">(<?= count($tracks) ?>)</span></h1>
  <a class="btn btn-primary" href="/admin/tracks/new">+ New track</a>
</div>
<form class="filters" method="get" action="/admin/tracks">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search tracks" aria-label="Search tracks">
  <select name="status" aria-label="Status">
    <option value="">Any status</option>
    <?php foreach (['published' => 'Live', 'draft' => 'Draft', 'changes' => 'Unpublished changes', 'unpublished' => 'Hidden'] as $k => $v): ?>
      <option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <select name="category" aria-label="Category">
    <option value="0">All categories</option>
    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $category === (int) $c['id'] ? ' selected' : '' ?>><?= h($c['title']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn">Filter</button>
  <?php if ($filtered): ?><a class="btn btn-ghost" href="/admin/tracks">Clear</a><?php endif; ?>
</form>
<?php if (!$tracks): ?><p class="empty">No tracks match. <a href="/admin/tracks/new">Add one</a>.</p><?php endif; ?>
<form method="post" action="/admin/tracks/reorder" data-sortable-form>
  <?= Auth::csrfField() ?>
  <ul class="rows" data-sortable<?= $filtered ? ' data-sort-disabled' : '' ?>>
    <?php foreach ($tracks as $t): ?>
      <li class="row-item">
        <input type="hidden" name="ids[]" value="<?= (int) $t['id'] ?>">
        <?php if (!$filtered): ?><span class="handle" title="Drag to reorder" aria-hidden="true">⋮⋮</span><?php endif; ?>
        <span class="thumb" data-scene="<?= h($t['scene']) ?>"><?php if ($t['cover_path']): ?><img src="<?= h(Media::thumbUrl($t['cover_path'])) ?>" alt="" loading="lazy"><?php endif; ?></span>
        <a class="row-main" href="/admin/tracks/<?= (int) $t['id'] ?>">
          <b><?= h($t['title']) ?></b>
          <span class="muted small"><?= h($t['category_title'] ?? 'No category') ?> · <?= $t['audio_media_id'] ? 'Audio file' : ($t['engine'] ? 'Built-in sound' : 'No sound') ?> · /track/<?= h($t['slug']) ?></span>
        </a>
        <span class="row-status"><?= View::statusBadge($t['status'], (bool) $t['draft']) ?></span>
        <?php if (!$filtered): ?>
          <span class="move"><button type="button" class="icon-btn" data-move="-1" aria-label="Move up">↑</button><button type="button" class="icon-btn" data-move="1" aria-label="Move down">↓</button></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$filtered && $tracks): ?>
    <div class="sticky-actions" data-order-bar hidden><span>Order changed.</span><button class="btn btn-primary">Save order</button></div>
  <?php endif; ?>
</form>
