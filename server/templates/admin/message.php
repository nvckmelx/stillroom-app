<?php /** @var string $title @var string $text */ ?>
<h1><?= h($title) ?></h1>
<?php if ($text): ?><p><?= h($text) ?></p><?php endif; ?>
<p><a href="/admin">Back to the dashboard</a></p>
