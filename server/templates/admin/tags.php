<?php /** @var array $tags */ use Stillroom\Auth; $pageTitle = 'Tags'; ?>
<div class="page-head"><h1>Tags</h1></div>
<form method="post" class="card form"><?= Auth::csrfField() ?><input type="hidden" name="action" value="add">
  <label>Add tags, comma separated<input name="titles" placeholder="Lullaby, Thunder, Study" required></label><button class="btn btn-primary">Add</button></form>
<ul class="rows">
  <?php foreach ($tags as $t): ?>
    <li class="row-item">
      <form method="post" class="row-main tag-form"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
        <input name="title" value="<?= h($t['title']) ?>" aria-label="Tag name" maxlength="40"><span class="muted small"><?= (int) $t['track_count'] ?> tracks · /tag/<?= h($t['slug']) ?></span>
        <button class="btn btn-sm" name="action" value="rename">Rename</button>
        <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="Delete this tag? It will be removed from <?= (int) $t['track_count'] ?> tracks.">Delete</button></form>
    </li>
  <?php endforeach; ?>
</ul>
