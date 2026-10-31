<?php
declare(strict_types=1);

/*
 * Stillroom CMS bootstrap.
 * APP_ROOT holds src/, templates/, migrations/, storage/ and .env.
 * On Hostinger APP_ROOT is the folder above public_html (e.g. ~/domains/example.com),
 * so nothing private is reachable from the web.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Stillroom\\', 10) !== 0) {
        return;
    }
    $file = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 10)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/src/helpers.php';

Stillroom\Env::load(APP_ROOT . '/.env');
date_default_timezone_set('UTC');

$debug = Stillroom\Env::bool('APP_DEBUG', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isApi = str_starts_with(Stillroom\Http::path(), '/api/');
    if ($isApi) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'server_error', 'message' => $debug ? $e->getMessage() : 'Something went wrong.']);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:system-ui;background:#060c11;color:#edf0ea;padding:40px">'
            . '<h1 style="font-weight:300">Something went wrong</h1><p>The error was logged.</p>'
            . ($debug ? '<pre style="white-space:pre-wrap;color:#9ca9aa">' . h((string) $e) . '</pre>' : '') . '</body>';
    }
});
