<?php /** @var array $site @var bool $hasDraft @var array $errors @var array|null $logo @var array|null $og @var string $openPreview */
use Stillroom\Admin\View; use Stillroom\Auth; use Stillroom\Media; $pageTitle = 'Site & SEO';
$links = $site['links'] ?? []; while (count($links) < 6) { $links[] = ['label' => '', 'url' => '']; } ?>
<div class="page-head"><div><h1>Site &amp; SEO</h1><?php if ($hasDraft): ?><p><span class="badge badge-changes">Unpublished changes</span></p><?php endif; ?></div>
  <a class="btn btn-ghost" href="/?preview=1" target="stillroom-preview" rel="noopener">Open preview</a></div>
<?php if ($openPreview): ?><p class="note">Draft saved. <a href="<?= h($openPreview) ?>" target="stillroom-preview" rel="noopener" data-autopen>Open the preview</a></p><?php endif; ?>
<?php if ($errors): ?><div class="flash flash-error"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form" data-upload-form>
  <?= Auth::csrfField() ?>
  <div class="grid-2">
    <section class="card">
      <h2>Identity</h2>
      <label>Site name <span class="hint">Working name; change it here when the brand is final</span><input name="name" value="<?= h($site['name'] ?? '') ?>" required maxlength="60"></label>
      <label>Tagline<input name="tagline" value="<?= h($site['tagline'] ?? '') ?>" maxlength="140"></label>
      <h2>Logo</h2>
      <?php if ($logo): ?><img class="logo-preview" src="<?= h(Media::url($logo['path'])) ?>" alt=""><label class="row small"><input type="checkbox" name="remove_logo" value="1"> Remove logo (use the built-in mark)</label><?php endif; ?>
      <label><?= $logo ? 'Replace' : 'Upload' ?> logo <span class="hint">PNG or WebP with transparency, shown about 28 px high</span><input type="file" name="logo" accept="image/png,image/webp,image/jpeg" data-image-input></label>
      <?= View::fieldError($errors, 'logo') ?><img class="logo-preview" alt="" hidden data-image-preview>
    </section>
    <section class="card">
      <h2>Texts</h2>
      <label>Footer note<input name="footer" value="<?= h($site['footer'] ?? '') ?>" maxlength="300"></label>
      <label>About <span class="hint">Shown in the site footer and the app's About sheet</span><textarea name="about" rows="5" maxlength="3000"><?= h($site['about'] ?? '') ?></textarea></label>
      <h2>Links</h2>
      <?php foreach ($links as $l): ?>
        <div class="grid-2 tight"><input name="link_label[]" value="<?= h($l['label']) ?>" placeholder="Label" aria-label="Link label" maxlength="40"><input name="link_url[]" value="<?= h($l['url']) ?>" placeholder="https://…" aria-label="Link URL" maxlength="300"></div>
      <?php endforeach; ?>
    </section>
  </div>
  <section class="card">
    <h2>Search engines &amp; sharing</h2>
    <label>Default title<input name="seo_title" value="<?= h($site['seo']['title'] ?? '') ?>" maxlength="120"></label>
    <label>Default description<textarea name="seo_description" rows="2" maxlength="300"><?= h($site['seo']['description'] ?? '') ?></textarea></label>
    <?php if ($og): ?><img class="cover-preview" src="<?= h(Media::url($og['path'])) ?>" alt=""><label class="row small"><input type="checkbox" name="remove_og" value="1"> Remove sharing image</label><?php endif; ?>
    <label>Sharing image <span class="hint">1200 × 630 recommended</span><input type="file" name="og_image" accept="image/jpeg,image/png,image/webp" data-image-input></label>
    <?= View::fieldError($errors, 'og_image') ?><img class="cover-preview" alt="" hidden data-image-preview>
  </section>
  <div class="upload-progress" hidden data-progress><span></span><b>Uploading… 0%</b></div>
  <div class="sticky-actions">
    <button class="btn" name="action" value="save">Save draft</button>
    <button class="btn" name="action" value="preview">Preview</button>
    <button class="btn btn-primary" name="action" value="publish">Publish</button>
    <?php if ($hasDraft): ?><button class="btn btn-ghost" name="action" value="discard" formnovalidate>Discard draft</button><?php endif; ?>
  </div>
</form>
