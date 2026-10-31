<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * One-time web installer for hosting without SSH: /install
 * Requires INSTALL_TOKEN from .env, creates the tables, imports the existing catalog and the first admin.
 * Disabled for good as soon as an admin exists.
 */
final class Installer
{
    public static function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $token = (string) Env::get('INSTALL_TOKEN', '');
        try {
            $installed = Migrator::installed();
        } catch (\Throwable $e) {
            self::page('Cannot connect to the database', '<p>Check DB_* values in .env.</p><pre>' . h(Env::bool('APP_DEBUG') ? $e->getMessage() : 'See storage/logs/php-error.log') . '</pre>');
            return;
        }
        if ($installed) {
            http_response_code(404);
            self::page('Already installed', '<p>Sign in at <a href="/admin">/admin</a>.</p>');
            return;
        }
        if (strlen($token) < 16) {
            self::page('Installer locked', '<p>Set <code>INSTALL_TOKEN</code> (at least 16 random characters) in <code>.env</code>, then reload this page.</p>');
            return;
        }
        $error = '';
        if (Http::isPost()) {
            Auth::checkCsrf();
            if (!hash_equals($token, Http::post('token'))) {
                usleep(800000);
                $error = 'Wrong install token.';
            } else {
                try {
                    Migrator::run();
                    $seeded = !empty($_POST['seed']) ? Seeder::run() : [];
                    Auth::createUser(Http::post('email'), (string) ($_POST['password'] ?? ''), 'admin', Http::post('name'));
                    Audit::log('install', 'system', '', json_encode($seeded));
                    self::page('Done', '<p>The database is ready' . ($seeded ? ' and the existing catalog was imported (' . (int) ($seeded['tracks'] ?? 0) . ' tracks)' : '') . '.</p><p>Remove <code>INSTALL_TOKEN</code> from .env, then <a href="/admin">sign in</a>.</p>');
                    return;
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }
        self::page('Install Stillroom', ($error ? '<p class="err">' . h($error) . '</p>' : '') . '
          <form method="post">' . Auth::csrfField() . '
            <label>Install token<input name="token" type="password" required autocomplete="off"></label>
            <label>Your name<input name="name" autocomplete="name"></label>
            <label>Admin email<input name="email" type="email" required autocomplete="username"></label>
            <label>Password (min 10 characters)<input name="password" type="password" minlength="10" required autocomplete="new-password"></label>
            <label class="row"><input type="checkbox" name="seed" value="1" checked> Import the existing 33 Stillroom tracks</label>
            <button>Create database and admin</button>
          </form>');
    }

    private static function page(string $title, string $body): void
    {
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . h($title) . '</title><link rel="stylesheet" href="/admin-assets/admin.css"></head><body class="auth"><main class="auth-card"><div class="brand"><span class="mark"></span>stillroom</div><h1>' . h($title) . '</h1>' . $body . '</main></body></html>';
    }
}
