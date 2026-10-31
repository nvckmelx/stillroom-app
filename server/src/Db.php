<?php
declare(strict_types=1);

namespace Stillroom;

use PDO;

/** PDO wrapper. MySQL/MariaDB in production (Hostinger), SQLite for local development and tests. */
final class Db
{
    private static ?PDO $pdo = null;

    public static function driver(): string
    {
        return Env::get('DB_DRIVER', 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (self::driver() === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', 'localhost'),
                Env::int('DB_PORT', 3306),
                Env::get('DB_NAME', '')
            );
            $pdo = new PDO($dsn, Env::get('DB_USER', ''), Env::get('DB_PASS', ''), $opts);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'");
        } else {
            $path = Env::get('DB_PATH') ?: APP_ROOT . '/storage/database.sqlite';
            if ($path[0] !== '/') {
                $path = APP_ROOT . '/' . $path;
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $opts);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        }
        return self::$pdo = $pdo;
    }

    public static function sqlitePath(): string
    {
        $path = Env::get('DB_PATH') ?: APP_ROOT . '/storage/database.sqlite';
        return $path[0] === '/' ? $path : APP_ROOT . '/' . $path;
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        self::exec($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(', ', array_map(static fn($c) => $c . ' = ?', array_keys($data)));
        return self::exec('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where, [...array_values($data), ...$params]);
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function tableExists(string $table): bool
    {
        try {
            self::pdo()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            return true;
        } catch (\PDOException) {
            return false;
        }
    }
}
