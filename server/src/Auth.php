<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Server-side sessions for the admin. Roles:
 *  - admin:  everything, including users, site settings and backups
 *  - editor: tracks, collections, categories, tags and the home page
 */
final class Auth
{
    public const ROLES = ['admin', 'editor'];
    private const MAX_FAILS = 5;
    private const WINDOW_MIN = 15;

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = str_starts_with(Env::appUrl(), 'https://');
        session_name('stillroom_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '28800');
        $dir = APP_ROOT . '/storage/sessions';
        if (is_dir($dir) || @mkdir($dir, 0700, true)) {
            session_save_path($dir);
        }
        session_start();
        // Idle timeout: 8 hours
        if (isset($_SESSION['seen']) && time() - (int) $_SESSION['seen'] > 28800) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['seen'] = time();
    }

    /** Only resumes a session if the browser already has one (keeps public pages cookie-free). */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['stillroom_admin'])) {
            return null;
        }
        self::startSession();
        $id = (int) ($_SESSION['uid'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $u = Db::one('SELECT id, email, name, role, active FROM users WHERE id = ?', [$id]);
        if (!$u || !(int) $u['active']) {
            return null;
        }
        return self::$user = $u;
    }

    public static function can(string $ability): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ($u['role'] === 'admin') {
            return true;
        }
        return in_array($ability, ['content'], true);
    }

    public static function tooManyAttempts(string $ip, string $email): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW_MIN * 60);
        $fails = (int) Db::val(
            'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at >= ? AND (ip = ? OR email = ?)',
            [$since, $ip, strtolower($email)]
        );
        return $fails >= self::MAX_FAILS;
    }

    /** @return string|null error message, null on success */
    public static function attempt(string $email, string $password): ?string
    {
        $email = strtolower(trim($email));
        $ip = Http::ip();
        if (self::tooManyAttempts($ip, $email)) {
            return 'Too many failed attempts. Try again in ' . self::WINDOW_MIN . ' minutes.';
        }
        $u = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        // Verify against a dummy hash when the user is missing, so timing does not reveal valid emails.
        $hash = $u['password_hash'] ?? self::hash(bin2hex(random_bytes(8)));
        $ok = password_verify($password, $hash) && $u && (int) $u['active'];
        Db::insert('login_attempts', ['ip' => $ip, 'email' => $email, 'success' => $ok ? 1 : 0, 'created_at' => now()]);
        if (!$ok) {
            return 'Wrong email or password.';
        }
        if (password_needs_rehash($u['password_hash'], self::algo())) {
            Db::update('users', ['password_hash' => self::hash($password)], 'id = ?', [$u['id']]);
        }
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        Db::update('users', ['last_login_at' => now()], 'id = ?', [$u['id']]);
        self::$loaded = false;
        Audit::log('login', 'user', (string) $u['id']);
        return null;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        self::$user = null;
        self::$loaded = true;
    }

    public static function algo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algo());
    }

    public static function validatePassword(string $p): ?string
    {
        if (mb_strlen($p) < 10) {
            return 'Use at least 10 characters.';
        }
        return null;
    }

    public static function createUser(string $email, string $password, string $role = 'admin', string $name = ''): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email.');
        }
        if ($err = self::validatePassword($password)) {
            throw new \InvalidArgumentException($err);
        }
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('Unknown role.');
        }
        if (Db::val('SELECT id FROM users WHERE email = ?', [$email])) {
            throw new \InvalidArgumentException('A user with this email already exists.');
        }
        return Db::insert('users', [
            'email' => $email, 'name' => $name, 'password_hash' => self::hash($password),
            'role' => $role, 'active' => 1, 'created_at' => now(),
        ]);
    }

    // ---- CSRF ----

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . h(self::csrfToken()) . '">';
    }

    public static function checkCsrf(): void
    {
        self::startSession();
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
            http_response_code(419);
            exit('The form expired. Go back, reload the page and try again.');
        }
    }
}
