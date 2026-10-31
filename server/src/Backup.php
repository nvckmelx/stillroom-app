<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Backups: one zip with the database (portable JSON dump of every table; plus the raw SQLite file when on SQLite)
 * and every uploaded file. Stored in storage/backups (outside the web root). Restore with bin/restore.php.
 */
final class Backup
{
    public const TABLES = ['users', 'categories', 'tags', 'media', 'tracks', 'track_tags', 'collections', 'collection_tracks', 'settings', 'audit_log', 'schema_migrations'];

    public static function dir(): string
    {
        $d = APP_ROOT . '/storage/backups';
        if (!is_dir($d)) {
            mkdir($d, 0700, true);
        }
        return $d;
    }

    public static function create(bool $withMedia = true): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is required for backups.');
        }
        $file = self::dir() . '/stillroom-' . gmdate('Ymd-His') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new \RuntimeException('Cannot create ' . $file);
        }
        $dump = ['format' => 'stillroom-backup', 'version' => 1, 'created_at' => gmdate('c'), 'driver' => Db::driver(), 'tables' => []];
        foreach (self::TABLES as $t) {
            $dump['tables'][$t] = Db::tableExists($t) ? Db::all('SELECT * FROM ' . $t) : [];
        }
        $zip->addFromString('database.json', json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (Db::driver() === 'sqlite') {
            $copy = self::dir() . '/.snapshot.sqlite';
            @unlink($copy);
            Db::pdo()->exec('VACUUM INTO ' . Db::pdo()->quote($copy));
            $zip->addFile($copy, 'database.sqlite');
        }
        $uploads = Media::uploadDir();
        if ($withMedia && is_dir($uploads)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                /** @var \SplFileInfo $f */
                if ($f->isFile() && $f->getFilename() !== '.htaccess') {
                    $zip->addFile($f->getPathname(), 'uploads/' . substr($f->getPathname(), strlen($uploads) + 1));
                }
            }
        }
        $zip->close();
        @unlink(self::dir() . '/.snapshot.sqlite');
        @chmod($file, 0600);
        self::prune(Env::int('BACKUP_KEEP', 14));
        return $file;
    }

    public static function list(): array
    {
        $files = glob(self::dir() . '/stillroom-*.zip') ?: [];
        rsort($files);
        return array_map(static fn($f) => ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)], $files);
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^stillroom-\d{8}-\d{6}\.zip$/', $name)) {
            return null;
        }
        $p = self::dir() . '/' . $name;
        return is_file($p) ? $p : null;
    }

    public static function prune(int $keep): void
    {
        $files = glob(self::dir() . '/stillroom-*.zip') ?: [];
        rsort($files);
        foreach (array_slice($files, max(1, $keep)) as $old) {
            @unlink($old);
        }
    }

    /** Replaces all content with the backup. Used by bin/restore.php (CLI only). */
    public static function restore(string $zipFile): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new \RuntimeException('Cannot open ' . $zipFile);
        }
        $dump = json_decode((string) $zip->getFromName('database.json'), true);
        if (($dump['format'] ?? '') !== 'stillroom-backup') {
            throw new \RuntimeException('Not a Stillroom backup.');
        }
        Migrator::run();
        $counts = [];
        Db::tx(static function () use ($dump, &$counts) {
            if (Db::driver() === 'sqlite') {
                Db::pdo()->exec('PRAGMA foreign_keys = OFF');
            }
            foreach (array_reverse(self::TABLES) as $t) {
                if ($t !== 'schema_migrations') {
                    Db::exec('DELETE FROM ' . $t);
                }
            }
            foreach (self::TABLES as $t) {
                if ($t === 'schema_migrations') {
                    continue;
                }
                foreach ($dump['tables'][$t] ?? [] as $row) {
                    Db::insert($t, $row);
                }
                $counts[$t] = count($dump['tables'][$t] ?? []);
            }
        });
        $uploads = Media::uploadDir();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!str_starts_with($name, 'uploads/') || str_contains($name, '..') || str_ends_with($name, '/')) {
                continue;
            }
            $target = $uploads . '/' . substr($name, 8);
            @mkdir(dirname($target), 0755, true);
            file_put_contents($target, $zip->getFromIndex($i));
            $counts['files'] = ($counts['files'] ?? 0) + 1;
        }
        $zip->close();
        return $counts;
    }
}
