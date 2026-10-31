<?php /** @var array|null $row @var array $data @var array $errors @var array $problems @var array $tracks @var array|null $cover @var bool $openPreview */
use Stillroom\Admin\View; use Stillroom\Auth; use Stillroom\Media;
$pageTitle = $row ? 'Edit · ' . $data['title'] : 'New collection';
$status = $row['status'] ?? 'draft';
$previewUrl = $row ? '/collection/' . $row['slug'] . '?preview=1' : '';
$byId = []; foreach ($tracks as $t) { $byId[(int) $t['id']] = $t; }
$ordered = array_values(array_filter(array_map(static fn($id) => $byId[$id] ?? null, $data['tracks'])));
$rest = array_values(array_filter($tracks, static fn($t) => !in_array((int) $t['id'], $data['tracks'], true)));
?>
<div class="page-head"><div><p class="crumbs"><a href="/admin/collections">Collections</a> /</p><h1><?= $row ? h($data['title']) : 'New collection' ?></h1>
  <?php if ($row): ?><p><?= View::statusBadge($status, (bool) $row['draft']) ?></p><?php endif; ?></div>
  <?php if ($row): ?><a class="btn btn-ghost" href="<?= h($previewUrl) ?>" target="stillroom-preview" rel="noopener">Open preview</a><?php endif; ?></div>
<?php if ($openPreview): ?><p class="note">Draft saved. <a href="<?= h($previewUrl) ?>" target="stillroom-preview" rel="noopener" data-autopen>Open the preview</a></p><?php endif; ?>
<?php if ($problems): ?><div class="flash flash-error"><?= h(implode(' ', $problems)) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form" data-upload-form>
  <?= Auth::csrfField() ?>
  <div class="grid-2">
    <section class="card">
      <label>Title<input name="title" value="<?= h($data['title']) ?>" required maxlength="120" data-slug-source></label><?= View::fieldError($errors, 'title') ?>
      <label>Description<textarea name="description" rows="4" maxlength="2000"><?= h($data['description']) ?></textarea></label>
      <label>Address <span class="hint">/collection/…</span><input name="slug" value="<?= h($data['slug']) ?>" pattern="[a-z0-9][a-z0-9-]{0,79}" data-slug-target></label><?= View::fieldError($errors, 'slug') ?>
    </section>
    <section class="card">
      <h2>Cover <span class="hint">optional</span></h2>
      <?php if ($cover): ?><img class="cover-preview" src="<?= h(Media::url($cover['path'])) ?>" alt=""><label class="row small"><input type="checkbox" name="remove_cover" value="1"> Remove cover</label><?php endif; ?>
      <label><?= $cover ? 'Replace' : 'Upload' ?> cover<input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" data-image-input></label><?= View::fieldError($errors, 'cover') ?>
      <img class="cover-preview" alt="" hidden data-image-preview>
    </section>
  </div>
  <section class="card">
    <h2>Tracks</h2>
    <p class="muted small">Tick tracks to include them. Use ↑ ↓ to set the order. Hidden and draft tracks are skipped on the live site.</p>
    <input type="search" placeholder="Filter tracks" data-filter-list aria-label="Filter tracks">
    <ul class="rows picker" data-picker>
      <?php foreach (array_merge($ordered, $rest) as $t): $on = in_array((int) $t['id'], $data['tracks'], true); ?>
        <li class="row-item<?= $on ? ' is-on' : '' ?>"><label class="row-main row"><input type="checkbox" name="tracks[]" value="<?= (int) $t['id'] ?>"<?= $on ? ' checked' : '' ?>>
          <span><b><?= h($t['title']) ?></b> <span class="muted small"><?= h($t['category_title'] ?? '') ?> · <?= $t['status'] === 'published' ? 'live' : h($t['status']) ?></span></span></label>
          <span class="move"><button type="button" class="icon-btn" data-move="-1" aria-label="Move up">↑</button><button type="button" class="icon-btn" data-move="1" aria-label="Move down">↓</button></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <div class="upload-progress" hidden data-progress><span></span><b>Uploading… 0%</b></div>
  <div class="sticky-actions">
    <button class="btn" name="action" value="save">Save draft</button>
    <button class="btn" name="action" value="preview">Preview</button>
    <button class="btn btn-primary" name="action" value="publish"><?= $status === 'published' ? 'Publish changes' : 'Publish' ?></button>
  </div>
</form>
<?php if ($row): ?>
<section class="card danger"><h2>More actions</h2><div class="inline-forms">
  <?php if ($status === 'published'): ?><form method="post"><?= Auth::csrfField() ?><button class="btn" name="action" value="unpublish">Unpublish</button></form><?php endif; ?>
  <?php if ($row['draft']): ?><form method="post"><?= Auth::csrfField() ?><button class="btn" name="action" value="discard">Discard changes</button></form><?php endif; ?>
  <form method="post"><?= Auth::csrfField() ?><label class="row small"><input type="checkbox" name="confirm_delete" value="yes"> Yes, delete</label><button class="btn btn-danger" name="action" value="delete">Delete</button></form>
</div></section>
<?php endif; ?>
