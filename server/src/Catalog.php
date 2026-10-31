<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Builds the public catalog that both clients (web and Android) load at runtime from GET /api/v1/catalog.
 * Published content only; in preview mode (admin session + ?preview=1) drafts and unpublished items are merged in.
 */
final class Catalog
{
    public static function build(bool $preview = false): array
    {
        $cats = Db::all('SELECT * FROM categories WHERE visible = 1 ORDER BY sort, id');
        $catById = [];
        foreach ($cats as $c) {
            $catById[(int) $c['id']] = $c['slug'];
        }
        $tagRows = Db::all('SELECT * FROM tags ORDER BY title');
        $tagById = array_column($tagRows, 'slug', 'id');
        $media = [];
        foreach (Db::all('SELECT id, path, mime, duration FROM media') as $m) {
            $media[(int) $m['id']] = $m;
        }

        $rows = Db::all(
            $preview
                ? "SELECT * FROM tracks ORDER BY sort, id"
                : "SELECT * FROM tracks WHERE status = 'published' ORDER BY sort, id"
        );
        $tracks = [];
        $trackSlugById = [];
        $usedTags = [];
        foreach ($rows as $r) {
            $d = $preview ? Tracks::editable($r) : $r + ['tags' => Tracks::tagIds((int) $r['id'])];
            $catSlug = $catById[(int) ($d['category_id'] ?? 0)] ?? null;
            if (!$catSlug) {
                continue; // hidden or missing category: not shown
            }
            $audio = $d['audio_media_id'] ? ($media[(int) $d['audio_media_id']] ?? null) : null;
            $cover = $d['cover_media_id'] ? ($media[(int) $d['cover_media_id']] ?? null) : null;
            if (!$audio && !$d['engine']) {
                continue; // nothing to play
            }
            $tags = [];
            foreach ($d['tags'] as $tid) {
                if (isset($tagById[$tid])) {
                    $tags[] = $tagById[$tid];
                    $usedTags[$tagById[$tid]] = true;
                }
            }
            $t = [
                'id' => (int) $r['id'],
                'slug' => $d['slug'],
                'title' => $d['title'],
                'subtitle' => (string) $d['subtitle'],
                'details' => (string) $d['details'],
                'description' => (string) $d['description'],
                'category' => $catSlug,
                'tags' => $tags,
                'scene' => $d['scene'],
                'engine' => $d['engine'],
                'audio' => $audio ? ['url' => Media::url($audio['path']), 'mime' => $audio['mime']] : null,
                'duration' => $d['duration'] ? (int) $d['duration'] : ($audio['duration'] ?? null),
                'cover' => $cover ? ['url' => Media::url($cover['path']), 'thumb' => Media::thumbUrl($cover['path'])] : null,
                'featured' => (bool) $d['featured'],
                'published_at' => $r['published_at'],
                'seo' => ['title' => (string) $d['seo_title'], 'description' => (string) $d['seo_description']],
            ];
            if ($preview) {
                $t['status'] = $r['status'];
                $t['has_changes'] = (bool) $r['draft'];
            }
            $tracks[] = $t;
            $trackSlugById[(int) $r['id']] = $d['slug'];
        }

        $collections = [];
        foreach (Db::all($preview ? 'SELECT * FROM collections ORDER BY sort, id' : "SELECT * FROM collections WHERE status = 'published' ORDER BY sort, id") as $c) {
            $d = $preview ? Collections::editable($c) : $c + ['tracks' => Collections::trackIds((int) $c['id'])];
            $slugs = array_values(array_filter(array_map(static fn($id) => $trackSlugById[$id] ?? null, $d['tracks'])));
            if (!$slugs) {
                continue;
            }
            $cover = $d['cover_media_id'] ? ($media[(int) $d['cover_media_id']] ?? null) : null;
            $collections[] = [
                'slug' => $d['slug'], 'title' => $d['title'], 'description' => (string) $d['description'],
                'cover' => $cover ? ['url' => Media::url($cover['path']), 'thumb' => Media::thumbUrl($cover['path'])] : null,
                'tracks' => $slugs,
            ] + ($preview ? ['status' => $c['status']] : []);
        }

        $site = Settings::get('site', $preview);
        $logo = !empty($site['logo_media_id']) ? ($media[(int) $site['logo_media_id']] ?? null) : null;
        $seoImg = !empty($site['seo']['image_media_id']) ? ($media[(int) $site['seo']['image_media_id']] ?? null) : null;
        $siteOut = [
            'name' => $site['name'] ?? 'Stillroom',
            'tagline' => $site['tagline'] ?? '',
            'logo' => $logo ? Media::url($logo['path']) : null,
            'links' => array_values(array_filter($site['links'] ?? [], static fn($l) => !empty($l['url']) && !empty($l['label']))),
            'footer' => $site['footer'] ?? '',
            'about' => $site['about'] ?? '',
            'seo' => [
                'title' => $site['seo']['title'] ?? '',
                'description' => $site['seo']['description'] ?? '',
                'image' => $seoImg ? Media::url($seoImg['path']) : null,
            ],
        ];

        $usedCats = array_flip(array_column($tracks, 'category'));
        return [
            'version' => 1,
            'generated_at' => gmdate('c'),
            'preview' => $preview,
            'site' => $siteOut,
            'home' => Settings::get('home', $preview),
            'categories' => array_values(array_map(static fn($c) => [
                'slug' => $c['slug'], 'title' => $c['title'], 'intro' => $c['intro'], 'description' => (string) $c['description'],
            ], array_filter($cats, static fn($c) => isset($usedCats[$c['slug']])))),
            'tags' => array_values(array_map(static fn($t) => ['slug' => $t['slug'], 'title' => $t['title']], array_filter($tagRows, static fn($t) => isset($usedTags[$t['slug']])))),
            'collections' => $collections,
            'tracks' => $tracks,
        ];
    }

    /** Case- and accent-insensitive search over title, subtitle, description, tags and category. */
    public static function search(array $catalog, string $q, string $category = '', string $tag = ''): array
    {
        $needle = self::fold($q);
        $tagTitles = array_column($catalog['tags'], 'title', 'slug');
        $catTitles = array_column($catalog['categories'], 'title', 'slug');
        return array_values(array_filter($catalog['tracks'], static function ($t) use ($needle, $category, $tag, $tagTitles, $catTitles) {
            if ($category !== '' && $t['category'] !== $category) {
                return false;
            }
            if ($tag !== '' && !in_array($tag, $t['tags'], true)) {
                return false;
            }
            if ($needle === '') {
                return true;
            }
            $hay = self::fold(implode(' ', [$t['title'], $t['subtitle'], $t['details'], $t['description'], $catTitles[$t['category']] ?? '',
                implode(' ', array_map(static fn($s) => $tagTitles[$s] ?? $s, $t['tags']))]));
            foreach (preg_split('/\s+/', $needle) ?: [] as $word) {
                if ($word !== '' && !str_contains($hay, $word)) {
                    return false;
                }
            }
            return true;
        }));
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $s = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s) ?? $s;
        }
        return trim($s);
    }
}
