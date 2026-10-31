<?php
declare(strict_types=1);
// Usage: php bin/backup.php [--no-media]
// Writes storage/backups/stillroom-YYYYMMDD-HHMMSS.zip and keeps the newest BACKUP_KEEP files (default 14).
// Hostinger cron (hPanel → Advanced → Cron Jobs), daily at 03:17:
//   /usr/bin/php /home/USER/domains/DOMAIN/bin/backup.php
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
require dirname(__DIR__) . '/src/bootstrap.php';
$file = Stillroom\Backup::create(!in_array('--no-media', $argv, true));
echo 'Backup written: ' . $file . ' (' . round(filesize($file) / 1048576, 2) . " MB)\n";
