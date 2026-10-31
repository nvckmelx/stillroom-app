<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Tracks with a draft → preview → publish workflow.
 *
 * status: draft (never published, not public) | published (public) | unpublished (hidden again).
 * Edits to a *published* track are stored in `draft` (JSON) and stay invisible until Publish;
 * edits to a draft or unpublished track write the columns directly (nothing public to protect).
 */
final class Tracks
{
    public const FIELDS = ['title', 'slug', 'subtitle', 'details', 'description', 'category_id', 'scene', 'engine',
        'audio_media_id', 'cover_media_id', 'duration', 'featured', 'seo_title', 'seo_description'];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM tracks WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Db::one('SELECT * FROM tracks WHERE slug = ?', [$slug]);
    }

    /** @return int[] */
    public static function tagIds(int $id): array
    {
        return array_map('intval', array_column(Db::all('SELECT tag_id FROM track_tags WHERE track_id = ?', [$id]), 'tag_id'));
    }

    /** Values an editor sees: live columns, overlaid with the pending draft if any. */
    public static function editable(array $row): array
    {
        $data = [];
        foreach (self::FIELDS as $f) {
            $data[$f] = $row[$f];
        }
        $data['tags'] = self::tagIds((int) $row['id']);
        if (!empty($row['draft'])) {
            $draft = json_decode((string) $row['draft'], true) ?: [];
            $data = array_merge($data, array_intersect_key($draft, $data));
        }
        return $data;
    }

    /**
     * Normalises and validates form input.
     * @return array{0:array,1:array<string,string>} [data, errors]
     */
    public static function validate(array $in, ?int $id = null): array
    {
        $e = [];
        $d = [];
        $d['title'] = mb_substr(trim((string) ($in['title'] ?? '')), 0, 160);
        if ($d['title'] === '') {
            $e['title'] = 'Title is required.';
        }
        $slug = trim((string) ($in['slug'] ?? ''));
        $d['slug'] = $slug === '' ? slugify($d['title']) : $slug;
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $d['slug'])) {
            $e['slug'] = 'Use lowercase letters, numbers and hyphens.';
        } elseif (Db::val('SELECT id FROM tracks WHERE slug = ? AND id <> ?', [$d['slug'], $id ?? 0])) {
            $e['slug'] = 'Another track already uses this address.';
        }
        $d['subtitle'] = mb_substr(trim((string) ($in['subtitle'] ?? '')), 0, 120);
        $d['details'] = mb_substr(trim((string) ($in['details'] ?? '')), 0, 180);
        $d['description'] = mb_substr(trim((string) ($in['description'] ?? '')), 0, 5000);
        $cat = (int) ($in['category_id'] ?? 0);
        $d['category_id'] = $cat > 0 && Db::val('SELECT id FROM categories WHERE id = ?', [$cat]) ? $cat : null;
        $d['scene'] = (string) ($in['scene'] ?? '');
        if (!Scenes::isScene($d['scene'])) {
            $e['scene'] = 'Choose a background scene.';
        }
        $engine = (string) ($in['engine'] ?? '');
        $d['engine'] = $engine !== '' && Scenes::isEngine($engine) ? $engine : null;
        $d['audio_media_id'] = self::mediaId($in['audio_media_id'] ?? null, 'audio');
        $d['cover_media_id'] = self::mediaId($in['cover_media_id'] ?? null, 'image');
        $dur = (int) ($in['duration'] ?? 0);
        $d['duration'] = $dur > 0 && $dur < 86400000 ? $dur : null;
        $d['featured'] = !empty($in['featured']) ? 1 : 0;
        $d['seo_title'] = mb_substr(trim((string) ($in['seo_title'] ?? '')), 0, 120);
        $d['seo_description'] = mb_substr(trim((string) ($in['seo_description'] ?? '')), 0, 300);
        $tags = [];
        foreach ((array) ($in['tags'] ?? []) as $t) {
            if ((int) $t > 0) {
                $tags[] = (int) $t;
            }
        }
        $d['tags'] = array_values(array_unique(array_filter($tags, static fn($t) => (bool) Db::val('SELECT id FROM tags WHERE id = ?', [$t]))));
        return [$d, $e];
    }

    private static function mediaId(mixed $v, string $kind): ?int
    {
        $id = (int) $v;
        if ($id <= 0) {
            return null;
        }
        $m = Media::find($id);
        return $m && ($m['kind'] === $kind || ($kind === 'image' && $m['kind'] === 'image')) ? $id : null;
    }

    /** Problems that block publishing (validation already passed). */
    public static function publishProblems(array $d): array
    {
        $p = [];
        if (!$d['audio_media_id'] && !$d['engine']) {
            $p[] = 'Add an audio file or choose a built-in sound.';
        }
        if (!$d['category_id']) {
            $p[] = 'Choose a category.';
        }
        return $p;
    }

    public static function create(array $d, ?int $userId): int
    {
        return Db::tx(static function () use ($d, $userId) {
            $sort = (int) Db::val('SELECT COALESCE(MAX(sort), 0) FROM tracks') + 10;
            $row = array_intersect_key($d, array_flip(self::FIELDS));
            $id = Db::insert('tracks', $row + [
                'status' => 'draft', 'sort' => $sort, 'created_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            self::setTags($id, $d['tags']);
            Audit::log('create', 'track', (string) $id, $d['title']);
            return $id;
        });
    }

    public static function save(int $id, array $d): void
    {
        $row = self::find($id);
        if (!$row) {
            throw new \RuntimeException('Track not found');
        }
        Db::tx(static function () use ($row, $id, $d) {
            if ($row['status'] === 'published') {
                Db::update('tracks', ['draft' => json_encode($d, JSON_UNESCAPED_UNICODE), 'updated_at' => now()], 'id = ?', [$id]);
            } else {
                Db::update('tracks', array_intersect_key($d, array_flip(self::FIELDS)) + ['draft' => null, 'updated_at' => now()], 'id = ?', [$id]);
                self::setTags($id, $d['tags']);
            }
        });
        Audit::log('save', 'track', (string) $id, $d['title']);
    }

    /** @return string[] problems; empty on success */
    public static function publish(int $id): array
    {
        $row = self::find($id);
        if (!$row) {
            return ['Track not found.'];
        }
        $d = self::editable($row);
        [$d2, $errors] = self::validate($d, $id);
        $problems = array_merge(array_values($errors), self::publishProblems($d2));
        if ($problems) {
            return $problems;
        }
        Db::tx(static function () use ($id, $d2, $row) {
            Db::update('tracks', array_intersect_key($d2, array_flip(self::FIELDS)) + [
                'status' => 'published', 'draft' => null, 'updated_at' => now(),
                'published_at' => $row['published_at'] ?: now(),
            ], 'id = ?', [$id]);
            self::setTags($id, $d2['tags']);
        });
        Audit::log('publish', 'track', (string) $id, $d2['title']);
        return [];
    }

    public static function unpublish(int $id): void
    {
        Db::update('tracks', ['status' => 'unpublished', 'updated_at' => now()], 'id = ? AND status = ?', [$id, 'published']);
        // pending edits become the working copy of a hidden track
        $row = self::find($id);
        if ($row && $row['draft']) {
            $d = self::editable($row);
            Db::update('tracks', array_intersect_key($d, array_flip(self::FIELDS)) + ['draft' => null], 'id = ?', [$id]);
            self::setTags($id, $d['tags']);
        }
        Audit::log('unpublish', 'track', (string) $id);
    }

    public static function discardDraft(int $id): void
    {
        Db::update('tracks', ['draft' => null, 'updated_at' => now()], 'id = ?', [$id]);
        Audit::log('discard', 'track', (string) $id);
    }

    public static function delete(int $id): void
    {
        Db::tx(static function () use ($id) {
            Db::exec('DELETE FROM track_tags WHERE track_id = ?', [$id]);
            Db::exec('DELETE FROM collection_tracks WHERE track_id = ?', [$id]);
            Db::exec('DELETE FROM tracks WHERE id = ?', [$id]);
        });
        Audit::log('delete', 'track', (string) $id);
    }

    /** @param int[] $ids in display order */
    public static function reorder(array $ids): void
    {
        Db::tx(static function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                Db::update('tracks', ['sort' => ($i + 1) * 10], 'id = ?', [(int) $id]);
            }
        });
        Audit::log('reorder', 'track', '', count($ids) . ' tracks');
    }

    /** @param int[] $tagIds */
    public static function setTags(int $id, array $tagIds): void
    {
        Db::exec('DELETE FROM track_tags WHERE track_id = ?', [$id]);
        foreach (array_unique($tagIds) as $t) {
            Db::insert('track_tags', ['track_id' => $id, 'tag_id' => (int) $t]);
        }
    }

    /** Admin list with draft overlay for titles. */
    public static function adminList(string $q = '', string $status = '', int $category = 0): array
    {
        $where = ['1=1'];
        $p = [];
        if ($q !== '') {
            $where[] = '(t.title LIKE ? OR t.slug LIKE ? OR t.draft LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        if (in_array($status, ['draft', 'published', 'unpublished'], true)) {
            $where[] = 't.status = ?';
            $p[] = $status;
        } elseif ($status === 'changes') {
            $where[] = 't.draft IS NOT NULL';
        }
        if ($category > 0) {
            $where[] = 't.category_id = ?';
            $p[] = $category;
        }
        $rows = Db::all(
            'SELECT t.*, c.title AS category_title, m.path AS cover_path FROM tracks t
             LEFT JOIN categories c ON c.id = t.category_id LEFT JOIN media m ON m.id = t.cover_media_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY t.sort, t.id',
            $p
        );
        foreach ($rows as &$r) {
            if ($r['draft']) {
                $d = json_decode($r['draft'], true) ?: [];
                $r['title'] = $d['title'] ?? $r['title'];
            }
        }
        return $rows;
    }
}
