<?php
declare(strict_types=1);
// Usage: php bin/migrate.php [--seed]
// Applies database migrations. With --seed, imports the existing 33-track Stillroom catalog (safe to run twice).
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
require dirname(__DIR__) . '/src/bootstrap.php';
$applied = Stillroom\Migrator::run();
echo $applied ? 'Applied: ' . implode(', ', $applied) . "\n" : "Database is up to date.\n";
if (in_array('--seed', $argv, true)) {
    $n = Stillroom\Seeder::run();
    echo 'Imported: ' . json_encode($n) . "\n";
}
