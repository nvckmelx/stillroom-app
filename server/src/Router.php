<?php
declare(strict_types=1);

namespace Stillroom;

final class Router
{
    public static function dispatch(): void
    {
        $path = Http::path();
        if ($path === '/api' || str_starts_with($path, '/api/')) {
            Api::handle($path);
        }
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            Http::securityHeaders(true);
            Admin\Controller::handle($path);
            return;
        }
        if ($path === '/install') {
            Http::securityHeaders(true);
            Installer::handle();
            return;
        }
        if ($path === '/robots.txt') {
            Site::robots();
        }
        if ($path === '/sitemap.xml') {
            Site::sitemap();
        }
        if ($path === '/manifest.webmanifest') {
            Site::manifest();
        }
        if (preg_match('#^/(uploads|assets|admin-assets)/#', $path) || preg_match('#\.(js|css|png|jpe?g|webp|svg|ico|mp3|m4a|ogg|wav|json|map|txt|woff2?)$#i', $path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Not found';
            return;
        }
        Http::securityHeaders(false);
        Site::page($path);
    }
}
