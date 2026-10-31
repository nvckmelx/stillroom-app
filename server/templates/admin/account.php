<?php /** @var array $user @var string $error */ use Stillroom\Auth; $pageTitle = 'Account'; ?>
<h1>Your account</h1>
<p class="muted"><?= h($user['email']) ?> · <?= h($user['role']) ?></p>
<?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="card form narrow"><?= Auth::csrfField() ?>
  <h2>Change password</h2>
  <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
  <label>New password <span class="hint">at least 10 characters</span><input type="password" name="new_password" minlength="10" required autocomplete="new-password"></label>
  <button class="btn btn-primary">Change password</button>
</form>
