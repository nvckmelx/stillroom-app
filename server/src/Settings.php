<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Site-wide documents with draft/publish:
 *  - site: name, tagline, logo, links, footer text, default SEO
 *  - home: hero texts, call to action and the ordered list of home page sections
 */
final class Settings
{
    public const NAMES = ['site', 'home'];

    public static function defaults(string $name): array
    {
        static $seed = null;
        $seed ??= json_decode((string) file_get_contents(APP_ROOT . '/seed/catalog.json'), true)['settings'] ?? [];
        return $seed[$name] ?? [];
    }

    public static function row(string $name): ?array
    {
        return Db::one('SELECT * FROM settings WHERE name = ?', [$name]);
    }

    /** Live values, or the draft when $preview is true. */
    public static function get(string $name, bool $preview = false): array
    {
        $row = self::row($name);
        $live = $row && $row['published'] ? (json_decode($row['published'], true) ?: []) : [];
        $data = array_replace(self::defaults($name), $live);
        if ($preview && $row && $row['draft']) {
            $data = array_replace($data, json_decode($row['draft'], true) ?: []);
        }
        return $data;
    }

    public static function hasDraft(string $name): bool
    {
        $row = self::row($name);
        return (bool) ($row['draft'] ?? null);
    }

    public static function saveDraft(string $name, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (self::row($name)) {
            Db::update('settings', ['draft' => $json, 'updated_at' => now()], 'name = ?', [$name]);
        } else {
            Db::insert('settings', ['name' => $name, 'published' => null, 'draft' => $json, 'updated_at' => now()]);
        }
        Audit::log('save', 'settings', $name);
    }

    public static function publish(string $name): void
    {
        $data = self::get($name, true);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (self::row($name)) {
            Db::update('settings', ['published' => $json, 'draft' => null, 'updated_at' => now(), 'published_at' => now()], 'name = ?', [$name]);
        } else {
            Db::insert('settings', ['name' => $name, 'published' => $json, 'draft' => null, 'updated_at' => now(), 'published_at' => now()]);
        }
        Audit::log('publish', 'settings', $name);
    }

    public static function discard(string $name): void
    {
        Db::update('settings', ['draft' => null], 'name = ?', [$name]);
    }
}
