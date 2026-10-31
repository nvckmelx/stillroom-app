<?php
declare(strict_types=1);

namespace Stillroom;

/** Curated playlists ("подборки") with the same draft → publish workflow as tracks. */
final class Collections
{
    public const FIELDS = ['title', 'slug', 'description', 'cover_media_id'];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM collections WHERE id = ?', [$id]);
    }

    public static function all(): array
    {
        $rows = Db::all('SELECT c.*, (SELECT COUNT(*) FROM collection_tracks ct WHERE ct.collection_id = c.id) AS track_count FROM collections c ORDER BY c.sort, c.id');
        foreach ($rows as &$r) {
            if ($r['draft']) {
                $r['title'] = (json_decode($r['draft'], true) ?: [])['title'] ?? $r['title'];
            }
        }
        return $rows;
    }

    /** @return int[] */
    public static function trackIds(int $id): array
    {
        return array_map('intval', array_column(Db::all('SELECT track_id FROM collection_tracks WHERE collection_id = ? ORDER BY sort', [$id]), 'track_id'));
    }

    public static function editable(array $row): array
    {
        $d = [];
        foreach (self::FIELDS as $f) {
            $d[$f] = $row[$f];
        }
        $d['tracks'] = self::trackIds((int) $row['id']);
        if (!empty($row['draft'])) {
            $d = array_merge($d, array_intersect_key(json_decode($row['draft'], true) ?: [], $d));
        }
        return $d;
    }

    public static function validate(array $in, ?int $id = null): array
    {
        $e = [];
        $d = ['title' => mb_substr(trim((string) ($in['title'] ?? '')), 0, 120)];
        if ($d['title'] === '') {
            $e['title'] = 'Title is required.';
        }
        $slug = trim((string) ($in['slug'] ?? '')) ?: slugify($d['title']);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $slug)) {
            $e['slug'] = 'Use lowercase letters, numbers and hyphens.';
        } elseif (Db::val('SELECT id FROM collections WHERE slug = ? AND id <> ?', [$slug, $id ?? 0])) {
            $e['slug'] = 'Another collection uses this address.';
        }
        $d['slug'] = $slug;
        $d['description'] = mb_substr(trim((string) ($in['description'] ?? '')), 0, 2000);
        $cover = (int) ($in['cover_media_id'] ?? 0);
        $d['cover_media_id'] = $cover > 0 && Media::find($cover) ? $cover : null;
        $tracks = [];
        foreach ((array) ($in['tracks'] ?? []) as $t) {
            $t = (int) $t;
            if ($t > 0 && !in_array($t, $tracks, true) && Db::val('SELECT id FROM tracks WHERE id = ?', [$t])) {
                $tracks[] = $t;
            }
        }
        $d['tracks'] = $tracks;
        return [$d, $e];
    }

    public static function create(array $d): int
    {
        $sort = (int) Db::val('SELECT COALESCE(MAX(sort), 0) FROM collections') + 10;
        $id = Db::insert('collections', array_intersect_key($d, array_flip(self::FIELDS)) + ['status' => 'draft', 'sort' => $sort, 'created_at' => now(), 'updated_at' => now()]);
        self::setTracks($id, $d['tracks']);
        Audit::log('create', 'collection', (string) $id, $d['title']);
        return $id;
    }

    public static function save(int $id, array $d): void
    {
        $row = self::find($id);
        if (!$row) {
            throw new \RuntimeException('Collection not found');
        }
        if ($row['status'] === 'published') {
            Db::update('collections', ['draft' => json_encode($d, JSON_UNESCAPED_UNICODE), 'updated_at' => now()], 'id = ?', [$id]);
        } else {
            Db::update('collections', array_intersect_key($d, array_flip(self::FIELDS)) + ['draft' => null, 'updated_at' => now()], 'id = ?', [$id]);
            self::setTracks($id, $d['tracks']);
        }
        Audit::log('save', 'collection', (string) $id, $d['title']);
    }

    public static function publish(int $id): array
    {
        $row = self::find($id);
        if (!$row) {
            return ['Collection not found.'];
        }
        [$d, $e] = self::validate(self::editable($row), $id);
        if ($e) {
            return array_values($e);
        }
        Db::tx(static function () use ($id, $d, $row) {
            Db::update('collections', array_intersect_key($d, array_flip(self::FIELDS)) + ['status' => 'published', 'draft' => null, 'updated_at' => now(), 'published_at' => $row['published_at'] ?: now()], 'id = ?', [$id]);
            self::setTracks($id, $d['tracks']);
        });
        Audit::log('publish', 'collection', (string) $id, $d['title']);
        return [];
    }

    public static function unpublish(int $id): void
    {
        $row = self::find($id);
        if ($row && $row['draft']) {
            $d = self::editable($row);
            Db::update('collections', array_intersect_key($d, array_flip(self::FIELDS)) + ['draft' => null], 'id = ?', [$id]);
            self::setTracks($id, $d['tracks']);
        }
        Db::update('collections', ['status' => 'unpublished', 'updated_at' => now()], 'id = ?', [$id]);
        Audit::log('unpublish', 'collection', (string) $id);
    }

    public static function discardDraft(int $id): void
    {
        Db::update('collections', ['draft' => null], 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Db::exec('DELETE FROM collection_tracks WHERE collection_id = ?', [$id]);
        Db::exec('DELETE FROM collections WHERE id = ?', [$id]);
        Audit::log('delete', 'collection', (string) $id);
    }

    public static function setTracks(int $id, array $trackIds): void
    {
        Db::exec('DELETE FROM collection_tracks WHERE collection_id = ?', [$id]);
        foreach (array_values($trackIds) as $i => $t) {
            Db::insert('collection_tracks', ['collection_id' => $id, 'track_id' => (int) $t, 'sort' => $i]);
        }
    }

    /** @param int[] $ids */
    public static function reorder(array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            Db::update('collections', ['sort' => ($i + 1) * 10], 'id = ?', [(int) $id]);
        }
    }
}
