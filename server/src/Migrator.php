<?php
declare(strict_types=1);

namespace Stillroom;

/** Applies migrations/NNN_*.sql once each, in order, recording them in schema_migrations. */
final class Migrator
{
    private const DIALECT = [
        'mysql' => [
            '{PK}' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            '{INT}' => 'INT',
            '{STR}' => 'VARCHAR(190)',
            '{TEXT}' => 'MEDIUMTEXT',
            '{OPTS}' => ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ],
        'sqlite' => [
            '{PK}' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '{INT}' => 'INTEGER',
            '{STR}' => 'TEXT',
            '{TEXT}' => 'TEXT',
            '{OPTS}' => '',
        ],
    ];

    /** @return string[] applied versions */
    public static function run(): array
    {
        $pdo = Db::pdo();
        $d = self::DIALECT[Db::driver()];
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version ' . $d['{STR}'] . ' NOT NULL PRIMARY KEY, applied_at ' . $d['{STR}'] . ' NOT NULL)' . $d['{OPTS}']);
        $done = array_column(Db::all('SELECT version FROM schema_migrations'), 'version');
        $applied = [];
        $files = glob(APP_ROOT . '/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $done, true)) {
                continue;
            }
            $sql = strtr((string) file_get_contents($file), $d);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
            // MySQL DDL auto-commits, so statements run one by one; a failure stops before recording the version.
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                $pdo->exec($stmt);
            }
            Db::insert('schema_migrations', ['version' => $version, 'applied_at' => now()]);
            $applied[] = $version;
        }
        return $applied;
    }

    /** @return string[] migration versions not yet applied */
    public static function pending(): array
    {
        $done = Db::tableExists('schema_migrations') ? array_column(Db::all('SELECT version FROM schema_migrations'), 'version') : [];
        $all = array_map(static fn($f) => basename($f, '.sql'), glob(APP_ROOT . '/migrations/*.sql') ?: []);
        sort($all);
        return array_values(array_diff($all, $done));
    }

    public static function installed(): bool
    {
        return Db::tableExists('schema_migrations') && Db::tableExists('users') && (int) Db::val('SELECT COUNT(*) FROM users') > 0;
    }
}
