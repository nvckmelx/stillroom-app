<?php
/** @var array|null $row @var array $data @var array $errors @var array $problems @var array $categories @var array $tags @var array|null $audio @var array|null $cover @var bool $openPreview */
use Stillroom\Admin\View; use Stillroom\Auth; use Stillroom\Media; use Stillroom\Scenes;
$isNew = !$row;
$pageTitle = $isNew ? 'New track' : 'Edit · ' . $data['title'];
$status = $row['status'] ?? 'draft';
$previewUrl = $row ? '/track/' . $row['slug'] . '?preview=1' : '';
$maxAudio = Stillroom\Env::int('MAX_AUDIO_MB', 60);
?>
<div class="page-head">
  <div>
    <p class="crumbs"><a href="/admin/tracks">Tracks</a> /</p>
    <h1><?= $isNew ? 'New track' : h($data['title']) ?></h1>
    <?php if ($row): ?><p><?= View::statusBadge($status, (bool) $row['draft']) ?>
      <?php if ($status === 'published' && $row['draft']): ?><span class="muted small">Visitors see the live version until you publish.</span><?php endif; ?></p><?php endif; ?>
  </div>
  <?php if ($row): ?><a class="btn btn-ghost" href="<?= h($previewUrl) ?>" target="stillroom-preview" rel="noopener" data-preview-link>Open preview</a><?php endif; ?>
</div>
<?php if ($openPreview): ?><p class="note">Draft saved. <a href="<?= h($previewUrl) ?>" target="stillroom-preview" rel="noopener" data-autopen>Open the preview</a> — only you see it while signed in.</p><?php endif; ?>
<?php if ($problems): ?><div class="flash flash-error" role="alert"><b>Not published yet:</b><ul><?php foreach ($problems as $p): ?><li><?= h($p) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($errors): ?><div class="flash flash-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form" data-upload-form>
  <?= Auth::csrfField() ?>
  <input type="hidden" name="duration_ms" value="" data-duration>
  <div class="grid-2">
    <section class="card">
      <h2>Basics</h2>
      <label>Title<input name="title" value="<?= h($data['title']) ?>" required maxlength="160" data-slug-source></label>
      <?= View::fieldError($errors, 'title') ?>
      <label>Short line <span class="hint">Shown on cards, e.g. “432 Hz · 6 breaths/min”</span><input name="subtitle" value="<?= h($data['subtitle']) ?>" maxlength="120"></label>
      <label>Details <span class="hint">Small technical line under the title</span><input name="details" value="<?= h($data['details']) ?>" maxlength="180"></label>
      <label>Description<textarea name="description" rows="5" maxlength="5000"><?= h($data['description']) ?></textarea></label>
      <label>Category
        <select name="category_id" required>
          <option value="">Choose…</option>
          <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $data['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= h($c['title']) ?><?= $c['visible'] ? '' : ' (hidden)' ?></option><?php endforeach; ?>
        </select>
      </label>
      <fieldset class="tags">
        <legend>Tags</legend>
        <div class="chips">
          <?php foreach ($tags as $t): ?>
            <label class="chip"><input type="checkbox" name="tags[]" value="<?= (int) $t['id'] ?>"<?= in_array((int) $t['id'], $data['tags'], true) ? ' checked' : '' ?>><span><?= h($t['title']) ?></span></label>
          <?php endforeach; ?>
        </div>
        <label class="small">New tags, comma separated<input name="new_tags" placeholder="e.g. Rainy night, Lullaby"></label>
      </fieldset>
      <label class="row"><input type="checkbox" name="featured" value="1"<?= $data['featured'] ? ' checked' : '' ?>> Featured</label>
    </section>

    <section class="card">
      <h2>Sound</h2>
      <?php if ($audio): ?>
        <div class="media-current">
          <audio controls preload="none" src="<?= h(Media::url($audio['path'])) ?>"></audio>
          <p class="small muted"><?= h($audio['original_name']) ?> · <?= View::bytes((int) $audio['size']) ?><?= $data['duration'] ? ' · ' . gmdate($data['duration'] >= 3600000 ? 'H:i:s' : 'i:s', (int) round($data['duration'] / 1000)) : '' ?></p>
          <label class="row small"><input type="checkbox" name="remove_audio" value="1"> Remove this audio</label>
        </div>
      <?php endif; ?>
      <label><?= $audio ? 'Replace audio' : 'Upload audio' ?> <span class="hint">MP3, M4A, AAC, OGG, WAV, FLAC · up to <?= $maxAudio ?> MB</span>
        <input type="file" name="audio" accept=".mp3,.m4a,.aac,.ogg,.opus,.wav,.flac,.webm,audio/*" data-audio-input data-max="<?= $maxAudio * 1048576 ?>"></label>
      <?= View::fieldError($errors, 'audio') ?>
      <p class="small muted" data-audio-info></p>
      <label>Built-in sound <span class="hint">Plays when there is no audio file</span>
        <select name="engine">
          <option value="">None</option>
          <?php foreach (Scenes::ENGINES as $id => [$name]): ?><option value="<?= h($id) ?>"<?= $data['engine'] === $id ? ' selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?>
        </select>
      </label>

      <h2>Cover</h2>
      <?php if ($cover): ?>
        <div class="media-current"><img class="cover-preview" src="<?= h(Media::url($cover['path'])) ?>" alt="">
          <label class="row small"><input type="checkbox" name="remove_cover" value="1"> Remove cover</label></div>
      <?php endif; ?>
      <label><?= $cover ? 'Replace cover' : 'Upload cover' ?> <span class="hint">JPG, PNG, WebP · up to <?= Stillroom\Env::int('MAX_IMAGE_MB', 8) ?> MB. Optional: without a cover the scene is used.</span>
        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" data-image-input></label>
      <?= View::fieldError($errors, 'cover') ?>
      <img class="cover-preview" alt="" hidden data-image-preview>
    </section>
  </div>

  <section class="card">
    <h2>Background scene</h2>
    <p class="muted small">The animated world behind this track on the site and in the app.</p>
    <?= View::fieldError($errors, 'scene') ?>
    <div class="scenes" role="radiogroup" aria-label="Background scene">
      <?php $group = ''; foreach (Scenes::SCENES as $id => [$name, $g]): if ($g !== $group): $group = $g; ?><p class="scene-group"><?= h($g) ?></p><?php endif; ?>
        <label class="scene"><input type="radio" name="scene" value="<?= h($id) ?>"<?= $data['scene'] === $id ? ' checked' : '' ?>>
          <span class="scene-thumb" data-scene="<?= h($id) ?>"></span><span class="scene-name"><?= h($name) ?></span></label>
      <?php endforeach; ?>
    </div>
  </section>

  <details class="card" <?= isset($errors['slug']) ? 'open' : '' ?>>
    <summary><h2>Address &amp; SEO</h2></summary>
    <label>Address <span class="hint">/track/…  — letters, numbers, hyphens</span><input name="slug" value="<?= h($data['slug']) ?>" pattern="[a-z0-9][a-z0-9-]{0,79}" maxlength="80" data-slug-target></label>
    <?= View::fieldError($errors, 'slug') ?>
    <label>SEO title <span class="hint">Defaults to the track title</span><input name="seo_title" value="<?= h($data['seo_title']) ?>" maxlength="120"></label>
    <label>SEO description <span class="hint">Defaults to the description</span><textarea name="seo_description" rows="2" maxlength="300"><?= h($data['seo_description']) ?></textarea></label>
  </details>

  <div class="upload-progress" hidden data-progress><span></span><b>Uploading… 0%</b></div>
  <div class="sticky-actions">
    <button class="btn" name="action" value="save">Save draft</button>
    <button class="btn" name="action" value="preview">Preview</button>
    <button class="btn btn-primary" name="action" value="publish"><?= $status === 'published' ? 'Publish changes' : 'Publish' ?></button>
  </div>
</form>

<?php if ($row): ?>
  <section class="card danger">
    <h2>More actions</h2>
    <div class="inline-forms">
      <?php if ($status === 'published'): ?>
        <form method="post"><?= Auth::csrfField() ?><button class="btn" name="action" value="unpublish">Unpublish (hide)</button></form>
      <?php endif; ?>
      <?php if ($row['draft']): ?>
        <form method="post"><?= Auth::csrfField() ?><button class="btn" name="action" value="discard">Discard unpublished changes</button></form>
      <?php endif; ?>
      <form method="post"><?= Auth::csrfField() ?>
        <label class="row small"><input type="checkbox" name="confirm_delete" value="yes"> Yes, delete this track</label>
        <button class="btn btn-danger" name="action" value="delete">Delete</button>
      </form>
    </div>
  </section>
<?php endif; ?>
