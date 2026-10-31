<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Public read-only JSON API used by the web client and the Android app.
 *   GET /api/v1/catalog             everything the clients need in one request (ETag + short cache)
 *   GET /api/v1/tracks?q=&category=&tag=&collection=
 *   GET /api/v1/tracks/{slug}
 *   GET /api/v1/scenes              the 33 background scenes
 *   GET /api/v1/health
 * ?preview=1 returns drafts, but only for a logged-in admin session (same-origin cookie); otherwise it is ignored.
 */
final class Api
{
    public static function handle(string $path): never
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, If-None-Match');
        header('Access-Control-Expose-Headers: ETag');
        header('X-Content-Type-Options: nosniff');
        if (Http::method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        if (!in_array(Http::method(), ['GET', 'HEAD'], true)) {
            Http::json(['error' => 'method_not_allowed'], 405);
        }
        if ($path === '/api/v1/health') {
            $ok = Db::tableExists('tracks');
            Http::json(['ok' => $ok, 'version' => APP_VERSION, 'driver' => Db::driver()], $ok ? 200 : 503);
        }
        if (!Db::tableExists('tracks')) {
            Http::json(['error' => 'not_installed', 'message' => 'Run the installer first.'], 503);
        }
        $preview = Http::query('preview') === '1' && Auth::user() !== null;

        if ($path === '/api/v1/catalog') {
            self::cached(Catalog::build($preview), $preview);
        }
        if ($path === '/api/v1/tracks') {
            $c = Catalog::build($preview);
            $list = Catalog::search($c, Http::query('q'), Http::query('category'), Http::query('tag'));
            $col = Http::query('collection');
            if ($col !== '') {
                $found = array_values(array_filter($c['collections'], static fn($x) => $x['slug'] === $col))[0] ?? null;
                $slugs = $found['tracks'] ?? [];
                $list = array_values(array_filter($list, static fn($t) => in_array($t['slug'], $slugs, true)));
            }
            self::cached(['tracks' => $list, 'total' => count($list)], $preview);
        }
        if (preg_match('#^/api/v1/tracks/([a-z0-9-]{1,80})$#', $path, $m)) {
            $c = Catalog::build($preview);
            foreach ($c['tracks'] as $t) {
                if ($t['slug'] === $m[1]) {
                    self::cached(['track' => $t], $preview);
                }
            }
            Http::json(['error' => 'not_found'], 404);
        }
        if ($path === '/api/v1/scenes') {
            $out = [];
            foreach (Scenes::SCENES as $id => [$name, $group, $desc]) {
                $out[] = ['id' => $id, 'name' => $name, 'group' => $group, 'description' => $desc];
            }
            self::cached(['scenes' => $out], false);
        }
        Http::json(['error' => 'not_found'], 404);
    }

    private static function cached(array $data, bool $preview): never
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"' . substr(md5($body), 0, 20) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: ' . ($preview ? 'private, no-store' : 'public, max-age=60, stale-while-revalidate=300'));
        header('Vary: Cookie');
        if (!$preview && trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo $body;
        exit;
    }
}
