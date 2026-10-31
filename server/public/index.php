<?php
declare(strict_types=1);

/*
 * Front controller. The folder above this one holds src/, templates/, migrations/, storage/ and .env.
 * Local development:  php -S localhost:8080 -t server/public server/public/index.php
 */

if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($file !== __DIR__ . '/' && is_file($file) && !preg_match('/\.(php|html?)$/i', $file)) {
        return false; // let the built-in server send static files
    }
}

require (getenv('STILLROOM_ROOT') ?: dirname(__DIR__)) . '/src/bootstrap.php';

Stillroom\Router::dispatch();
