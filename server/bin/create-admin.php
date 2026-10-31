<?php
declare(strict_types=1);
// Usage: php bin/create-admin.php you@example.com [admin|editor]
// Asks for the password interactively (or reads ADMIN_PASSWORD from the environment for automation).
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
require dirname(__DIR__) . '/src/bootstrap.php';
$email = $argv[1] ?? '';
$role = $argv[2] ?? 'admin';
if ($email === '') { fwrite(STDERR, "Usage: php bin/create-admin.php email [admin|editor]\n"); exit(1); }
$pass = getenv('ADMIN_PASSWORD') ?: '';
if ($pass === '') {
    echo 'Password (min 10 characters): ';
    if (DIRECTORY_SEPARATOR === '/') { shell_exec('stty -echo'); }
    $pass = trim((string) fgets(STDIN));
    if (DIRECTORY_SEPARATOR === '/') { shell_exec('stty echo'); }
    echo "\n";
}
Stillroom\Migrator::run();
try {
    $id = Stillroom\Auth::createUser($email, $pass, $role);
    echo "Created $role #$id <$email>\n";
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
