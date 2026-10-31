<?php /** @var string $error @var string $next */ use Stillroom\Auth; ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Sign in · Stillroom admin</title>
<link rel="stylesheet" href="/admin-assets/admin.css"></head>
<body class="auth">
<main class="auth-card">
  <div class="brand"><span class="mark"></span>stillroom <small>admin</small></div>
  <h1>Sign in</h1>
  <?php if ($error): ?><p class="err" role="alert"><?= h($error) ?></p><?php endif; ?>
  <form method="post" action="/admin/login?next=<?= h(rawurlencode($next)) ?>">
    <?= Auth::csrfField() ?>
    <label>Email<input type="email" name="email" required autocomplete="username" autofocus></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary">Sign in</button>
  </form>
</main>
</body></html>
