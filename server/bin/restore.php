<?php
declare(strict_types=1);
// Usage: php bin/restore.php storage/backups/stillroom-YYYYMMDD-HHMMSS.zip --yes
// Replaces ALL content (tracks, users, settings, uploads) with the backup. Make a fresh backup first.
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
require dirname(__DIR__) . '/src/bootstrap.php';
$file = $argv[1] ?? '';
if (!is_file($file) || !in_array('--yes', $argv, true)) {
    fwrite(STDERR, "Usage: php bin/restore.php <backup.zip> --yes\n");
    exit(1);
}
$safety = Stillroom\Backup::create();
echo "Safety backup of the current state: $safety\n";
echo 'Restored: ' . json_encode(Stillroom\Backup::restore($file)) . "\n";
