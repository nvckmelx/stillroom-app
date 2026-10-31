<?php
declare(strict_types=1);
// Usage: php bin/export-catalog.php [output.json]
// Writes the published catalog as the offline fallback bundled with the Android app (public/assets/data/catalog.json).
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
require dirname(__DIR__) . '/src/bootstrap.php';
$out = $argv[1] ?? Stillroom\Media::publicDir() . '/assets/data/catalog.json';
$c = Stillroom\Catalog::build(false);
$c['offline'] = true;
@mkdir(dirname($out), 0755, true);
file_put_contents($out, json_encode($c, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo 'Wrote ' . count($c['tracks']) . " tracks to $out\n";
