<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Imports the existing Stillroom catalog (seed/catalog.json, extracted from the original app) into an empty database:
 * 4 categories, 14 tags, 33 tracks with their built-in sound and background scene, 5 collections, site and home settings.
 * Everything is imported as published so the site looks exactly like before. Idempotent: existing slugs are skipped.
 */
final class Seeder
{
    /** @return array<string,int> counts of created rows */
    public static function run(): array
    {
        $seed = json_decode((string) file_get_contents(APP_ROOT . '/seed/catalog.json'), true);
        $n = ['categories' => 0, 'tags' => 0, 'tracks' => 0, 'collections' => 0, 'settings' => 0];
        Db::tx(static function () use ($seed, &$n) {
            $catIds = [];
            foreach ($seed['categories'] as $c) {
                $id = Db::val('SELECT id FROM categories WHERE slug = ?', [$c['slug']]);
                if (!$id) {
                    $id = Db::insert('categories', $c + ['visible' => 1, 'created_at' => now(), 'updated_at' => now()]);
                    $n['categories']++;
                }
                $catIds[$c['slug']] = (int) $id;
            }
            $tagIds = [];
            foreach ($seed['tags'] as $t) {
                $id = Db::val('SELECT id FROM tags WHERE slug = ?', [$t['slug']]);
                if (!$id) {
                    $id = Db::insert('tags', $t + ['created_at' => now()]);
                    $n['tags']++;
                }
                $tagIds[$t['slug']] = (int) $id;
            }
            $trackIds = [];
            foreach ($seed['tracks'] as $t) {
                $id = Db::val('SELECT id FROM tracks WHERE slug = ?', [$t['slug']]);
                if (!$id) {
                    $id = Db::insert('tracks', [
                        'slug' => $t['slug'], 'status' => 'published', 'title' => $t['title'], 'subtitle' => $t['subtitle'],
                        'details' => $t['details'], 'description' => $t['description'], 'category_id' => $catIds[$t['category']],
                        'scene' => $t['scene'], 'engine' => $t['engine'], 'sort' => $t['sort'], 'featured' => $t['featured'],
                        'seo_title' => '', 'seo_description' => null,
                        'created_at' => now(), 'updated_at' => now(), 'published_at' => now(),
                    ]);
                    Tracks::setTags((int) $id, array_map(static fn($s) => $tagIds[$s], $t['tags']));
                    $n['tracks']++;
                }
                $trackIds[$t['slug']] = (int) $id;
            }
            foreach ($seed['collections'] as $i => $c) {
                if (Db::val('SELECT id FROM collections WHERE slug = ?', [$c['slug']])) {
                    continue;
                }
                $id = Db::insert('collections', [
                    'slug' => $c['slug'], 'status' => 'published', 'title' => $c['title'], 'description' => $c['description'],
                    'sort' => ($i + 1) * 10, 'created_at' => now(), 'updated_at' => now(), 'published_at' => now(),
                ]);
                Collections::setTracks($id, array_map(static fn($s) => $trackIds[$s], $c['tracks']));
                $n['collections']++;
            }
            foreach (Settings::NAMES as $name) {
                if (!Settings::row($name)) {
                    Db::insert('settings', ['name' => $name, 'published' => json_encode($seed['settings'][$name], JSON_UNESCAPED_UNICODE), 'draft' => null, 'updated_at' => now(), 'published_at' => now()]);
                    $n['settings']++;
                }
            }
        });
        return $n;
    }
}
