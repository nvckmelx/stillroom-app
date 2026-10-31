/* Stillroom service worker (website only, not used inside the Android app).
   App shell: cache-first. Catalog API: network-first with offline fallback. Pages: network-first.
   Audio, admin and uploads are never cached here (range requests, private pages). */
const VERSION = 'stillroom-v1';
const SHELL = ['/assets/css/app.css', '/assets/js/app.js', '/assets/js/config.js', '/assets/js/catalog.js', '/assets/js/player.js',
  '/assets/js/sound.js', '/assets/js/presets.js', '/assets/js/listen.js', '/assets/js/world.js', '/assets/js/thumbs.js', '/assets/js/util.js',
  '/assets/js/backgrounds.js', '/assets/img/icon-192.png', '/assets/img/favicon.png', '/assets/data/catalog.json'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSION).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (/^\/(admin|install|uploads)/.test(url.pathname) || url.searchParams.has('preview') || req.headers.has('range')) return;
  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(caches.match(req).then(hit => hit || fetch(req).then(res => { const copy = res.clone(); caches.open(VERSION).then(c => c.put(req, copy)); return res; })));
    return;
  }
  if (url.pathname.startsWith('/api/') || req.mode === 'navigate') {
    e.respondWith(fetch(req).then(res => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then(c => c.put(req, copy)); }
      return res;
    }).catch(() => caches.match(req).then(hit => hit || (req.mode === 'navigate' ? caches.match('/') : Response.error()))));
  }
});
