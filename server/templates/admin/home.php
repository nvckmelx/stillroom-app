<?php /** @var array $home @var bool $hasDraft @var array $collections @var array $tracks @var string $openPreview */
use Stillroom\Auth; $pageTitle = 'Home page';
$sections = $home['sections'] ?? [];
$types = ['collection' => 'Collection row', 'categories' => 'Categories grid', 'featured' => 'Featured tracks', 'new' => 'All tracks / newest'];
?>
<div class="page-head"><div><h1>Home page</h1><?php if ($hasDraft): ?><p><span class="badge badge-changes">Unpublished changes</span></p><?php endif; ?></div>
  <a class="btn btn-ghost" href="/?preview=1" target="stillroom-preview" rel="noopener">Open preview</a></div>
<?php if ($openPreview): ?><p class="note">Draft saved. <a href="<?= h($openPreview) ?>" target="stillroom-preview" rel="noopener" data-autopen>Open the preview</a></p><?php endif; ?>
<form method="post" class="form">
  <?= Auth::csrfField() ?>
  <section class="card">
    <h2>Hero</h2>
    <label>Small line above the title<input name="hero_eyebrow" value="<?= h($home['hero_eyebrow'] ?? '') ?>" maxlength="80"></label>
    <label>Title<input name="hero_title" value="<?= h($home['hero_title'] ?? '') ?>" maxlength="120"></label>
    <label>Text<textarea name="hero_subtitle" rows="2" maxlength="300"><?= h($home['hero_subtitle'] ?? '') ?></textarea></label>
    <div class="grid-2 tight">
      <label>Button label<input name="cta_label" value="<?= h($home['cta_label'] ?? '') ?>" maxlength="40"></label>
      <label>Button plays
        <select name="cta_track"><option value="">First track</option>
          <?php foreach ($tracks as $t): ?><option value="<?= h($t['slug']) ?>"<?= ($home['cta_track'] ?? '') === $t['slug'] ? ' selected' : '' ?>><?= h($t['title']) ?></option><?php endforeach; ?>
        </select></label>
    </div>
  </section>
  <section class="card">
    <h2>Sections <span class="hint">in display order</span></h2>
    <ul class="rows" data-sections>
      <?php foreach (array_merge($sections, [['type' => '']]) as $s): $blank = $s['type'] === ''; ?>
        <li class="row-item section-row"<?= $blank ? ' data-template hidden' : '' ?>>
          <select name="section_type[]" aria-label="Section type"<?= $blank ? ' disabled' : '' ?>>
            <?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"<?= $s['type'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
          </select>
          <select name="section_ref[]" aria-label="Collection"<?= $blank ? ' disabled' : '' ?>>
            <option value="">—</option>
            <?php foreach ($collections as $c): ?><option value="<?= h($c['slug']) ?>"<?= ($s['slug'] ?? '') === $c['slug'] ? ' selected' : '' ?>><?= h($c['title']) ?></option><?php endforeach; ?>
          </select>
          <input name="section_title[]" value="<?= h($s['title'] ?? '') ?>" placeholder="Custom heading (optional)" aria-label="Heading"<?= $blank ? ' disabled' : '' ?>>
          <span class="move"><button type="button" class="icon-btn" data-move="-1" aria-label="Move up">↑</button><button type="button" class="icon-btn" data-move="1" aria-label="Move down">↓</button><button type="button" class="icon-btn" data-remove aria-label="Remove">✕</button></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn btn-sm" data-add-section>+ Add section</button>
  </section>
  <div class="sticky-actions">
    <button class="btn" name="action" value="save">Save draft</button>
    <button class="btn" name="action" value="preview">Preview</button>
    <button class="btn btn-primary" name="action" value="publish">Publish</button>
    <?php if ($hasDraft): ?><button class="btn btn-ghost" name="action" value="discard" formnovalidate>Discard draft</button><?php endif; ?>
  </div>
</form>
