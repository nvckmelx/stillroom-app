/* Static thumbnails of the 33 background scenes, rendered once by the same engine and cached as images.
   Used on cards and rows when a track has no uploaded cover. */
const cache = new Map();
let queue = [];
let busy = false;

function render(id) {
  if (cache.has(id)) return cache.get(id);
  const host = document.createElement('div');
  host.style.cssText = 'position:fixed;left:-10000px;top:0;width:360px;height:240px;pointer-events:none';
  document.body.appendChild(host);
  let url = '';
  try {
    const bg = window.StillroomBackgrounds.mount(host, { preset: id, reducedMotion: true, maxDpr: 1 });
    url = host.querySelector('canvas').toDataURL('image/webp', .82);
    bg.destroy();
  } catch (e) { url = ''; }
  host.remove();
  cache.set(id, url);
  return url;
}

function pump() {
  if (busy) return;
  busy = true;
  const run = deadline => {
    while (queue.length && (!deadline || deadline.timeRemaining() > 6)) {
      const el = queue.shift();
      if (!el.isConnected) continue;
      const url = render(el.dataset.scene);
      if (url) { el.style.backgroundImage = `url(${url})`; el.classList.add('has-scene'); }
    }
    if (queue.length) schedule(run); else busy = false;
  };
  schedule(run);
}
const schedule = fn => (window.requestIdleCallback ? requestIdleCallback(fn, { timeout: 120 }) : setTimeout(() => fn(null), 16));

const io = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
  for (const e of entries) {
    if (!e.isIntersecting) continue;
    io.unobserve(e.target);
    const id = e.target.dataset.scene;
    if (cache.has(id)) { const u = cache.get(id); if (u) { e.target.style.backgroundImage = `url(${u})`; e.target.classList.add('has-scene'); } }
    else { queue.push(e.target); pump(); }
  }
}, { rootMargin: '300px' }) : null;

/** Paint every [data-scene] element inside root that does not have a cover image. */
export function paintScenes(root = document) {
  if (!window.StillroomBackgrounds) return;
  root.querySelectorAll('[data-scene]:not(.has-scene)').forEach(el => {
    if (io) io.observe(el); else { queue.push(el); pump(); }
  });
}
