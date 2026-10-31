<?php /** @var array $users @var string $error */ use Stillroom\Auth; $pageTitle = 'Users'; $me = (int) Auth::user()['id']; ?>
<div class="page-head"><h1>Users</h1></div>
<p class="muted"><b>Admin</b>: everything. <b>Editor</b>: tracks, collections, categories, tags and the home page — no users, site settings or backups.</p>
<?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
<ul class="rows">
  <?php foreach ($users as $u): ?>
    <li class="row-item user-row">
      <span class="row-main"><b><?= h($u['name'] ?: $u['email']) ?></b><span class="muted small"><?= h($u['email']) ?> · <?= h($u['role']) ?><?= $u['active'] ? '' : ' · disabled' ?> · last sign-in <?= h($u['last_login_at'] ? substr($u['last_login_at'], 0, 16) : 'never') ?></span></span>
      <?php if ((int) $u['id'] !== $me): ?>
        <form method="post" class="inline"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="role">
          <select name="role" aria-label="Role"><?php foreach (Auth::ROLES as $r): ?><option<?= $u['role'] === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select><button class="btn btn-sm">Set role</button></form>
        <form method="post" class="inline"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-sm" name="action" value="toggle"><?= $u['active'] ? 'Disable' : 'Enable' ?></button></form>
      <?php endif; ?>
      <details class="inline"><summary class="btn btn-sm">Set password</summary>
        <form method="post" class="inline"><?= Auth::csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="password" name="password" minlength="10" placeholder="New password" autocomplete="new-password" required><button class="btn btn-sm" name="action" value="password">Save</button></form></details>
    </li>
  <?php endforeach; ?>
</ul>
<form method="post" class="card form"><?= Auth::csrfField() ?><input type="hidden" name="action" value="add">
  <h2>Add a user</h2>
  <div class="grid-2 tight"><label>Email<input type="email" name="email" required autocomplete="off"></label><label>Name<input name="name"></label></div>
  <div class="grid-2 tight"><label>Temporary password<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
  <label>Role<select name="role"><option value="editor">editor</option><option value="admin">admin</option></select></label></div>
  <button class="btn btn-primary">Add user</button>
</form>
