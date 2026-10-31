<?php /** @var array $categories @var array $errors @var int $editId */ use Stillroom\Admin\View; use Stillroom\Auth; $pageTitle = 'Categories'; ?>
<div class="page-head"><h1>Categories</h1></div>
<p class="muted">Categories are the tabs at the top of the listening screen and the sections of the catalog. Changes here go live immediately.</p>
<?php if ($errors): ?><div class="flash flash-error"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" data-sortable-form>
  <?= Auth::csrfField() ?><input type="hidden" name="action" value="reorder">
  <ul class="rows" data-sortable>
    <?php foreach ($categories as $c): ?>
      <li class="row-item"><input type="hidden" name="ids[]" value="<?= (int) $c['id'] ?>"><span class="handle" aria-hidden="true">⋮⋮</span>
        <span class="row-main"><b><?= h($c['title']) ?></b><span class="muted small"><?= (int) $c['track_count'] ?> tracks · /c/<?= h($c['slug']) ?><?= $c['visible'] ? '' : ' · hidden' ?></span></span>
        <span class="move"><button type="button" class="icon-btn" data-move="-1" aria-label="Move up">↑</button><button type="button" class="icon-btn" data-move="1" aria-label="Move down">↓</button></span></li>
    <?php endforeach; ?>
  </ul>
  <div class="sticky-actions" data-order-bar hidden><span>Order changed.</span><button class="btn btn-primary">Save order</button></div>
</form>
<?php foreach (array_merge($categories, [null]) as $c): $new = $c === null; ?>
  <details class="card"<?= (!$new && $editId === (int) $c['id']) || ($new && $errors && !$editId) ? ' open' : '' ?>>
    <summary><h2><?= $new ? '+ Add a category' : 'Edit · ' . h($c['title']) ?></h2></summary>
    <form method="post" class="form">
      <?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= $new ? 0 : (int) $c['id'] ?>">
      <label>Title<input name="title" value="<?= h($c['title'] ?? '') ?>" required maxlength="80"></label>
      <label>Eyebrow text <span class="hint">Small line above the title on the listening screen</span><input name="intro" value="<?= h($c['intro'] ?? '') ?>" maxlength="120"></label>
      <label>Description<textarea name="description" rows="2" maxlength="1000"><?= h($c['description'] ?? '') ?></textarea></label>
      <label>Address <span class="hint">/c/…</span><input name="slug" value="<?= h($c['slug'] ?? '') ?>" pattern="[a-z0-9][a-z0-9-]{0,79}"></label>
      <label class="row"><input type="checkbox" name="visible" value="1"<?= ($c['visible'] ?? 1) ? ' checked' : '' ?>> Visible on the site and in the app</label>
      <div class="inline-forms"><button class="btn btn-primary">Save</button>
      <?php if (!$new): ?><button class="btn btn-danger" name="action" value="delete" data-confirm="Delete this category?">Delete</button><?php endif; ?></div>
    </form>
  </details>
<?php endforeach; ?>
