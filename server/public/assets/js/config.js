/* Runtime configuration. The website uses these defaults (same origin).
   The Android build (scripts/build-app.mjs) overwrites this file with the absolute API address. */
export const CONFIG = {
  apiBase: '/api/v1',
  router: 'history',            // 'history' on the website, 'hash' inside the app
  fallbackCatalog: '/assets/data/catalog.json',
  alwaysRefresh: false,         // the app asks the API on every start
  platform: 'web',
};
