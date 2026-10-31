/* Loads the published catalog from the CMS.
   Web: the server inlines it into the page (#boot-catalog). Android app: cached copy → GET {apiBase}/catalog (ETag) → bundled fallback.
   New tracks published in the admin appear without rebuilding either client. */
import { CONFIG } from './config.js';
import { store } from './util.js';

const CACHE_KEY = 'stillroom.catalog.v1';
const ETAG_KEY = 'stillroom.catalog.etag';
const REFRESH_MS = 5 * 60 * 1000;

export const previewMode = (() => {
  const q = new URLSearchParams(location.search);
  if (q.get('preview') === '1') { try { sessionStorage.setItem('stillroom.preview', '1'); } catch (e) { /* ignore */ } return true; }
  if (q.get('preview') === '0') { try { sessionStorage.removeItem('stillroom.preview'); } catch (e) { /* ignore */ } return false; }
  try { return sessionStorage.getItem('stillroom.preview') === '1'; } catch (e) { return false; }
})();

let data = null;
let loadedAt = 0;
const listeners = new Set();

export const catalog = {
  get data() { return data; },
  onChange(fn) { listeners.add(fn); },
  track: slug => data?.tracks.find(t => t.slug === slug) || null,
  category: slug => data?.categories.find(c => c.slug === slug) || null,
  collection: slug => data?.collections.find(c => c.slug === slug) || null,
  tag: slug => data?.tags.find(t => t.slug === slug) || null,
  inCategory: slug => data?.tracks.filter(t => t.category === slug) || [],
  withTag: slug => data?.tracks.filter(t => t.tags.includes(slug)) || [],
  ofCollection(slug) { const c = this.collection(slug); return c ? c.tracks.map(s => this.track(s)).filter(Boolean) : []; },
};

function set(next, source) {
  if (!next || !Array.isArray(next.tracks)) return false;
  const changed = !data || JSON.stringify(next.tracks) !== JSON.stringify(data.tracks) || JSON.stringify(next) !== JSON.stringify(data);
  data = next;
  loadedAt = Date.now();
  if (changed) listeners.forEach(fn => fn(source));
  return changed;
}

async function fetchCatalog() {
  const url = `${CONFIG.apiBase}/catalog${previewMode ? '?preview=1' : ''}`;
  const headers = {};
  const etag = store.get(ETAG_KEY);
  if (etag && !previewMode && data) headers['If-None-Match'] = etag;
  const res = await fetch(url, { headers, credentials: previewMode ? 'same-origin' : 'omit', cache: 'no-cache' });
  if (res.status === 304) { loadedAt = Date.now(); return false; }
  if (!res.ok) throw new Error('Catalog request failed: ' + res.status);
  const json = await res.json();
  if (!previewMode) {
    store.set(CACHE_KEY, JSON.stringify(json));
    const e = res.headers.get('ETag'); if (e) store.set(ETAG_KEY, e);
  }
  return set(json, 'network');
}

/** Resolves once something is displayable; keeps refreshing in the background. */
export async function loadCatalog() {
  const boot = document.getElementById('boot-catalog');
  if (boot) {
    try { set(JSON.parse(boot.textContent), 'boot'); } catch (e) { /* fall through */ }
  }
  if (!data && !previewMode) {
    const cached = store.json(CACHE_KEY, null);
    if (cached) set(cached, 'cache');
  }
  if (data && previewMode && !data.preview) {
    await fetchCatalog().catch(() => {});   // page was served without drafts: ask for the preview catalog
  } else if (data && CONFIG.alwaysRefresh) {
    fetchCatalog().catch(() => {});          // show the cached copy now, update when the network answers
  } else if (!data) {
    try {
      await fetchCatalog();
    } catch (e) {
      // offline and nothing cached: the catalog bundled with the app build
      const res = await fetch(CONFIG.fallbackCatalog).catch(() => null);
      if (res && res.ok) set(await res.json(), 'bundled');
      else throw e;
    }
  }
  // refresh when the app comes back to the foreground
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && Date.now() - loadedAt > REFRESH_MS) fetchCatalog().catch(() => {});
  });
  setInterval(() => { if (!document.hidden) fetchCatalog().catch(() => {}); }, REFRESH_MS);
  return data;
}
