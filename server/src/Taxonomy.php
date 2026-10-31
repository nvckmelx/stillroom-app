<?php
declare(strict_types=1);

namespace Stillroom;

/** Categories (ordered, can be hidden) and tags. Changes here apply immediately. */
final class Taxonomy
{
    public static function categories(): array
    {
        return Db::all('SELECT c.*, (SELECT COUNT(*) FROM tracks t WHERE t.category_id = c.id) AS track_count FROM categories c ORDER BY c.sort, c.id');
    }

    public static function category(int $id): ?array
    {
        return Db::one('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    /** @return array{0:array,1:array} */
    public static function validateCategory(array $in, ?int $id = null): array
    {
        $e = [];
        $d = [
            'title' => mb_substr(trim((string) ($in['title'] ?? '')), 0, 80),
            'intro' => mb_substr(trim((string) ($in['intro'] ?? '')), 0, 120),
            'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 1000),
            'visible' => !empty($in['visible']) ? 1 : 0,
        ];
        if ($d['title'] === '') {
            $e['title'] = 'Title is required.';
        }
        $slug = trim((string) ($in['slug'] ?? '')) ?: slugify($d['title']);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $slug)) {
            $e['slug'] = 'Use lowercase letters, numbers and hyphens.';
        } elseif (Db::val('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $id ?? 0])) {
            $e['slug'] = 'Another category uses this address.';
        }
        $d['slug'] = $slug;
        return [$d, $e];
    }

    public static function saveCategory(?int $id, array $d): int
    {
        if ($id) {
            Db::update('categories', $d + ['updated_at' => now()], 'id = ?', [$id]);
        } else {
            $d['sort'] = (int) Db::val('SELECT COALESCE(MAX(sort), 0) FROM categories') + 10;
            $id = Db::insert('categories', $d + ['created_at' => now(), 'updated_at' => now()]);
        }
        Audit::log('save', 'category', (string) $id, $d['title']);
        return $id;
    }

    public static function deleteCategory(int $id): ?string
    {
        if ((int) Db::val('SELECT COUNT(*) FROM tracks WHERE category_id = ?', [$id]) > 0) {
            return 'Move or delete the tracks in this category first.';
        }
        Db::exec('DELETE FROM categories WHERE id = ?', [$id]);
        Audit::log('delete', 'category', (string) $id);
        return null;
    }

    /** @param int[] $ids */
    public static function reorderCategories(array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            Db::update('categories', ['sort' => ($i + 1) * 10], 'id = ?', [(int) $id]);
        }
    }

    public static function tags(): array
    {
        return Db::all('SELECT g.*, (SELECT COUNT(*) FROM track_tags tt WHERE tt.tag_id = g.id) AS track_count FROM tags g ORDER BY g.title');
    }

    /** Creates tags from a comma-separated list; returns their ids. */
    public static function ensureTags(string $csv): array
    {
        $ids = [];
        foreach (array_filter(array_map('trim', explode(',', $csv))) as $title) {
            $title = mb_substr($title, 0, 40);
            $slug = slugify($title);
            $id = Db::val('SELECT id FROM tags WHERE slug = ?', [$slug]);
            if (!$id) {
                $id = Db::insert('tags', ['slug' => $slug, 'title' => $title, 'created_at' => now()]);
                Audit::log('create', 'tag', (string) $id, $title);
            }
            $ids[] = (int) $id;
        }
        return $ids;
    }

    public static function renameTag(int $id, string $title): void
    {
        $title = mb_substr(trim($title), 0, 40);
        if ($title !== '') {
            Db::update('tags', ['title' => $title], 'id = ?', [$id]);
        }
    }

    public static function deleteTag(int $id): void
    {
        Db::exec('DELETE FROM track_tags WHERE tag_id = ?', [$id]);
        Db::exec('DELETE FROM tags WHERE id = ?', [$id]);
        Audit::log('delete', 'tag', (string) $id);
    }
}
