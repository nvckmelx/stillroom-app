<?php
/** @var string $content @var string $title @var array|null $user @var array|null $flash */
use Stillroom\Auth;
$path = Stillroom\Http::path();
$nav = [
    ['/admin', 'Dashboard', 'content'],
    ['/admin/tracks', 'Tracks', 'content'],
    ['/admin/collections', 'Collections', 'content'],
    ['/admin/categories', 'Categories', 'content'],
    ['/admin/tags', 'Tags', 'content'],
    ['/admin/home', 'Home page', 'content'],
    ['/admin/site', 'Site & SEO', 'admin'],
    ['/admin/users', 'Users', 'admin'],
    ['/admin/backups', 'Backups', 'admin'],
];
$active = static fn(string $href) => $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0b1116">
<title><?= h($title) ?> · Stillroom admin</title>
<link rel="stylesheet" href="/admin-assets/admin.css">
<script src="/assets/js/backgrounds.js" defer></script>
<script src="/admin-assets/admin.js" defer></script>
</head>
<body>
<header class="topbar">
  <button class="nav-toggle" type="button" aria-controls="sidenav" aria-expanded="false" data-nav-toggle>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><span class="sr">Menu</span>
  </button>
  <a class="brand" href="/admin"><span class="mark"></span>stillroom <small>admin</small></a>
  <a class="btn btn-ghost btn-sm" href="/" target="_blank" rel="noopener">View site</a>
</header>
<div class="shell">
  <nav class="sidenav" id="sidenav" aria-label="Admin">
    <a class="brand side-brand" href="/admin"><span class="mark"></span>stillroom <small>admin</small></a>
    <ul>
      <?php foreach ($nav as [$href, $label, $need]): if ($need === 'admin' && !Auth::can('admin')) continue; ?>
        <li><a href="<?= $href ?>"<?= $active($href) ? ' aria-current="page"' : '' ?>><?= h($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="sidenav-foot">
      <a href="/admin/account"><?= h($user['name'] ?: $user['email']) ?> <span class="muted">· <?= h($user['role']) ?></span></a>
      <form method="post" action="/admin/logout"><?= Auth::csrfField() ?><button class="linklike">Sign out</button></form>
    </div>
  </nav>
  <main class="main" id="main">
    <?php if ($flash): ?><div class="flash flash-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
