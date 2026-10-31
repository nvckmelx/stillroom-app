<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Serves the web client (public/app.html) for every page URL, with server-rendered SEO:
 * <title>, description, canonical, Open Graph, JSON-LD and a crawlable text version of the page.
 * The catalog is inlined so the first screen needs no extra request.
 */
final class Site
{
    public static function page(string $path): void
    {
        $tpl = @file_get_contents(Media::publicDir() . '/app.html');
        if ($tpl === false) {
            http_response_code(500);
            echo 'app.html is missing from the public folder.';
            return;
        }
        if (!Migrator::installed()) {
            http_response_code(503);
            echo '<!doctype html><meta charset="utf-8"><title>Setup</title><body style="font-family:system-ui;background:#060c11;color:#edf0ea;padding:40px">'
                . '<h1 style="font-weight:300">Stillroom is not set up yet</h1><p>Open <a style="color:#aec7b6" href="/install">/install</a> to create the database and the first admin.</p></body>';
            return;
        }
        $preview = Http::query('preview') === '1' && Auth::user() !== null;
        $c = Catalog::build($preview);
        $site = $c['site'];
        $base = Env::appUrl();
        $name = $site['name'] ?: 'Stillroom';
        $title = $site['seo']['title'] ?: $name;
        $desc = $site['seo']['description'] ?: $site['tagline'];
        $image = $site['seo']['image'];
        $status = 200;
        $ld = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $name, 'url' => $base . '/'];
        $ssr = '';
        $bySlug = static fn(array $list, string $slug) => array_values(array_filter($list, static fn($x) => $x['slug'] === $slug))[0] ?? null;
        $trackLinks = static function (array $tracks) {
            $o = '<ul>';
            foreach ($tracks as $t) {
                $o .= '<li><a href="/track/' . h($t['slug']) . '">' . h($t['title']) . '</a> — ' . h($t['subtitle']) . '</li>';
            }
            return $o . '</ul>';
        };

        if (preg_match('#^/track/([a-z0-9-]+)$#', $path, $m)) {
            $t = $bySlug($c['tracks'], $m[1]);
            if ($t) {
                $title = ($t['seo']['title'] ?: $t['title']) . ' — ' . $name;
                $desc = $t['seo']['description'] ?: ($t['description'] ?: $t['subtitle']);
                $image = $t['cover']['url'] ?? $image;
                $ld = ['@context' => 'https://schema.org', '@type' => 'MusicRecording', 'name' => $t['title'], 'description' => $desc,
                    'url' => $base . '/track/' . $t['slug'], 'genre' => $t['category'], 'keywords' => implode(', ', $t['tags'])]
                    + ($t['duration'] ? ['duration' => 'PT' . (int) round($t['duration'] / 1000) . 'S'] : [])
                    + ($image ? ['image' => $image] : []);
                $ssr = '<h1>' . h($t['title']) . '</h1><p>' . h($t['subtitle']) . '</p><p>' . h($t['description']) . '</p>';
            } else {
                $status = 404;
                $title = 'Not found — ' . $name;
            }
        } elseif (preg_match('#^/collection/([a-z0-9-]+)$#', $path, $m)) {
            $col = $bySlug($c['collections'], $m[1]);
            if ($col) {
                $title = $col['title'] . ' — ' . $name;
                $desc = $col['description'] ?: $desc;
                $image = $col['cover']['url'] ?? $image;
                $tracks = array_values(array_filter(array_map(static fn($s) => $bySlug($c['tracks'], $s), $col['tracks'])));
                $ssr = '<h1>' . h($col['title']) . '</h1><p>' . h($col['description']) . '</p>' . $trackLinks($tracks);
                $ld = ['@context' => 'https://schema.org', '@type' => 'MusicPlaylist', 'name' => $col['title'], 'numTracks' => count($tracks), 'url' => $base . $path];
            } else {
                $status = 404;
            }
        } elseif (preg_match('#^/c/([a-z0-9-]+)$#', $path, $m)) {
            $cat = $bySlug($c['categories'], $m[1]);
            if ($cat) {
                $title = $cat['title'] . ' — ' . $name;
                $desc = $cat['description'] ?: $desc;
                $ssr = '<h1>' . h($cat['title']) . '</h1><p>' . h($cat['description']) . '</p>' . $trackLinks(array_filter($c['tracks'], static fn($t) => $t['category'] === $cat['slug']));
            } else {
                $status = 404;
            }
        } elseif (preg_match('#^/tag/([a-z0-9-]+)$#', $path, $m)) {
            $tag = $bySlug($c['tags'], $m[1]);
            if ($tag) {
                $title = $tag['title'] . ' — ' . $name;
                $ssr = '<h1>' . h($tag['title']) . '</h1>' . $trackLinks(array_filter($c['tracks'], static fn($t) => in_array($tag['slug'], $t['tags'], true)));
            } else {
                $status = 404;
            }
        } elseif ($path === '/' || in_array($path, ['/browse', '/search', '/saved', '/listen'], true) || str_starts_with($path, '/listen/')) {
            $ssr = '<h1>' . h($c['home']['hero_title'] ?? $name) . '</h1><p>' . h($c['home']['hero_subtitle'] ?? '') . '</p>' . $trackLinks($c['tracks']);
            if ($path === '/browse') {
                $title = 'Browse — ' . $name;
            } elseif ($path === '/search') {
                $title = 'Search — ' . $name;
            }
        } else {
            $status = 404;
            $title = 'Not found — ' . $name;
        }

        $canonical = $base . ($path === '/' ? '/' : $path);
        $meta = '<title>' . h($title) . '</title>'
            . '<meta name="description" content="' . h(str_limit((string) $desc, 300)) . '">'
            . '<link rel="canonical" href="' . h($canonical) . '">'
            . '<meta property="og:site_name" content="' . h($name) . '">'
            . '<meta property="og:title" content="' . h($title) . '">'
            . '<meta property="og:description" content="' . h(str_limit((string) $desc, 300)) . '">'
            . '<meta property="og:url" content="' . h($canonical) . '">'
            . '<meta property="og:type" content="' . (str_starts_with($path, '/track/') ? 'music.song' : 'website') . '">'
            . ($image ? '<meta property="og:image" content="' . h($image) . '"><meta name="twitter:card" content="summary_large_image">' : '<meta name="twitter:card" content="summary">')
            . ($status === 404 || $preview ? '<meta name="robots" content="noindex">' : '')
            . '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
        $boot = '<script id="boot-catalog" type="application/json">' . json_encode($c, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';

        $html = str_replace(['<!--SEO-->', '<!--BOOT-->', '<!--SSR-->'], [$meta, $boot, $ssr], $tpl);
        $html = preg_replace('#<title>[^<]*</title>(?=.*<title>)#s', '', $html, 1) ?? $html;
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: ' . ($preview ? 'private, no-store' : 'public, max-age=60'));
        echo $html;
    }

    public static function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /install\nAllow: /\n\nSitemap: " . Env::appUrl() . "/sitemap.xml\n";
        exit;
    }

    /** PWA manifest; the name follows the CMS site settings. */
    public static function manifest(): never
    {
        $site = Migrator::installed() ? Settings::get('site') : Settings::defaults('site');
        $name = $site['name'] ?? 'Stillroom';
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode([
            'name' => $name, 'short_name' => mb_substr($name, 0, 12), 'description' => $site['tagline'] ?? '',
            'start_url' => '/', 'scope' => '/', 'display' => 'standalone', 'orientation' => 'portrait',
            'background_color' => '#060c11', 'theme_color' => '#060c11',
            'icons' => [
                ['src' => '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/assets/img/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function sitemap(): never
    {
        $c = Migrator::installed() ? Catalog::build() : ['tracks' => [], 'collections' => [], 'categories' => [], 'tags' => []];
        $base = Env::appUrl();
        $urls = [$base . '/', $base . '/browse'];
        foreach ($c['categories'] as $x) {
            $urls[] = $base . '/c/' . $x['slug'];
        }
        foreach ($c['collections'] as $x) {
            $urls[] = $base . '/collection/' . $x['slug'];
        }
        foreach ($c['tracks'] as $x) {
            $urls[] = $base . '/track/' . $x['slug'];
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            echo '<url><loc>' . h($u) . '</loc></url>';
        }
        echo '</urlset>';
        exit;
    }
}
