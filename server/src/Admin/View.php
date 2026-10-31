<?php
declare(strict_types=1);

namespace Stillroom\Admin;

use Stillroom\Auth;

final class View
{
    public static function render(string $template, array $vars = [], bool $layout = true): void
    {
        header('Content-Type: text/html; charset=utf-8');
        extract($vars, EXTR_SKIP);
        $user = Auth::user();
        $flash = self::takeFlash();
        ob_start();
        require APP_ROOT . '/templates/admin/' . $template . '.php';
        $content = (string) ob_get_clean();
        if ($layout) {
            // templates may set $pageTitle themselves
            $title = $pageTitle ?? ($vars['title'] ?? ucfirst(str_replace('_', ' ', $template)));
            require APP_ROOT . '/templates/admin/layout.php';
        } else {
            echo $content;
        }
    }

    public static function flash(string $message, string $type = 'ok'): void
    {
        Auth::startSession();
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    private static function takeFlash(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }

    public static function statusBadge(string $status, bool $changes = false): string
    {
        $label = ['draft' => 'Draft', 'published' => 'Live', 'unpublished' => 'Hidden'][$status] ?? $status;
        return '<span class="badge badge-' . h($status) . '">' . $label . '</span>' . ($changes ? ' <span class="badge badge-changes">Unpublished changes</span>' : '');
    }

    public static function fieldError(array $errors, string $key): string
    {
        return isset($errors[$key]) ? '<p class="field-error">' . h($errors[$key]) . '</p>' : '';
    }

    public static function bytes(int $b): string
    {
        return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB';
    }
}
