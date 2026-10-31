/* Stillroom web client: catalog pages + shared player + the listening room. Same code runs on the website and in the Android app. */
import { CONFIG } from './config.js';
import { catalog, loadCatalog, previewMode } from './catalog.js';
import { player } from './player.js';
import { world } from './world.js';
import { Listen } from './listen.js';
import { paintScenes } from './thumbs.js';
import { esc, icon, fmtTime, fold, store } from './util.js';

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const hashMode = CONFIG.router === 'hash' || location.protocol === 'file:';
const href = p => (hashMode ? '#' + p : p);
window.__href = href;

// ------------------------------------------------------------------ favourites (per device)
const favs = new Set(store.json('stillroom.favs', []));
const isFav = slug => favs.has(slug);
function toggleFav(slug) {
  favs.has(slug) ? favs.delete(slug) : favs.add(slug);
  store.set('stillroom.favs', JSON.stringify([...favs]));
  syncFavButtons();
  toast(favs.has(slug) ? 'Saved' : 'Removed from saved');
  if (route.name === 'saved') render();
}
function syncFavButtons() {
  $$('[data-fav]').forEach(b => { const on = isFav(b.dataset.fav); b.setAttribute('aria-pressed', String(on)); b.innerHTML = icon(on ? 'heartFill' : 'heart'); });
  const cur = player.current;
  $$('[data-fav-current]').forEach(b => { const on = !!cur && isFav(cur.slug); b.setAttribute('aria-pressed', String(on)); b.innerHTML = icon(on ? 'heartFill' : 'heart'); b.classList.toggle('on', on); });
}

// ------------------------------------------------------------------ lists registered by the current view (play context)
const lists = new Map();
function list(key, tracks, label) { lists.set(key, { tracks, label }); return key; }

// ------------------------------------------------------------------ components
const art = (t, cls = '') => t.cover
  ? `<span class="art ${cls}"><img src="${esc(t.cover.thumb || t.cover.url)}" alt="" loading="lazy" decoding="async"></span>`
  : `<span class="art ${cls}" data-scene="${esc(t.scene)}"></span>`;
const dur = t => (t.audio ? (t.duration ? fmtTime(t.duration / 1000) : '') : 'Live');
const catTitle = slug => catalog.category(slug)?.title || '';

function row(t, key) {
  const active = player.current && player.current.slug === t.slug;
  return `<li class="trow${active ? ' is-current' : ''}" data-slug="${esc(t.slug)}">
    <button class="trow-play" data-play="${esc(t.slug)}" data-list="${key}" aria-label="Play ${esc(t.title)}">${art(t, 'sm')}<span class="play-ov">${icon(active && player.playing ? 'pause' : 'play')}</span></button>
    <a class="trow-main" data-link href="${href('/track/' + t.slug)}"><b>${esc(t.title)}</b><span>${esc(t.subtitle || catTitle(t.category))}</span></a>
    <span class="trow-dur">${esc(dur(t))}</span>
    <button class="icon-btn" data-fav="${esc(t.slug)}" aria-pressed="${isFav(t.slug)}" aria-label="Save ${esc(t.title)}">${icon(isFav(t.slug) ? 'heartFill' : 'heart')}</button>
    <button class="icon-btn" data-more="${esc(t.slug)}" data-list="${key}" aria-label="More actions for ${esc(t.title)}">${icon('more')}</button>
  </li>`;
}
const rows = (tracks, key) => `<ul class="trows">${tracks.map(t => row(t, key)).join('')}</ul>`;

function card(t, key) {
  return `<article class="tcard">
    <button class="tcard-art" data-play="${esc(t.slug)}" data-list="${key}" aria-label="Play ${esc(t.title)}">${art(t)}<span class="play-ov">${icon('play')}</span></button>
    <a data-link href="${href('/track/' + t.slug)}" class="tcard-text"><b>${esc(t.title)}</b><span>${esc(t.subtitle || catTitle(t.category))}</span></a>
  </article>`;
}
function shelf(title, link, tracks, key) {
  return `<section class="shelf">
    <div class="shelf-head"><h2>${esc(title)}</h2>${link ? `<a data-link href="${href(link)}" class="see-all">See all</a>` : ''}</div>
    <div class="shelf-row" role="list">${tracks.map(t => `<div role="listitem">${card(t, key)}</div>`).join('')}</div>
  </section>`;
}
function categoryTiles() {
  return `<div class="tiles">${catalog.data.categories.map(c => {
    const tracks = catalog.inCategory(c.slug); const first = tracks[0];
    return `<a class="tile" data-link href="${href('/c/' + c.slug)}">${first ? art(first, 'tile-art') : ''}<span class="tile-text"><b>${esc(c.title)}</b><span>${tracks.length} sounds</span></span></a>`;
  }).join('')}</div>`;
}
const tagChips = (tags, active = '') => `<div class="chips">${tags.map(t => `<a data-link class="chip${t.slug === active ? ' on' : ''}" href="${href('/tag/' + t.slug)}">${esc(t.title)}</a>`).join('')}</div>`;

function pageHead({ eyebrow = '', title, text = '', key = '', scene = '', cover = null }) {
  return `<header class="page-head">
    ${cover ? `<span class="art head-art"><img src="${esc(cover.url)}" alt=""></span>` : scene ? `<span class="art head-art" data-scene="${esc(scene)}"></span>` : ''}
    <div class="page-head-text">
      ${eyebrow ? `<p class="eyebrow">${esc(eyebrow)}</p>` : ''}
      <h1>${esc(title)}</h1>
      ${text ? `<p class="lede">${esc(text)}</p>` : ''}
      ${key ? `<div class="head-actions"><button class="btn btn-primary" data-play-all="${key}">${icon('play')} Play all</button></div>` : ''}
    </div>
  </header>`;
}

// ------------------------------------------------------------------ views
const views = {
  home() {
    const d = catalog.data, h = d.home || {};
    let out = `<section class="hero-home">
      ${h.hero_eyebrow ? `<p class="eyebrow">${esc(h.hero_eyebrow)}</p>` : ''}
      <h1>${esc(h.hero_title || d.site.name)}</h1>
      ${h.hero_subtitle ? `<p class="lede">${esc(h.hero_subtitle)}</p>` : ''}
      <div class="head-actions"><button class="btn btn-primary btn-lg" data-cta>${icon('play')} ${esc(h.cta_label || 'Start listening')}</button>
      <a class="btn btn-glass btn-lg" data-link href="${href('/browse')}">Browse sounds</a></div>
    </section>`;
    const saved = d.tracks.filter(t => isFav(t.slug));
    if (saved.length) out += shelf('Your saved sounds', '/saved', saved, list('saved', saved, 'Saved'));
    for (const s of (h.sections || [])) {
      if (s.type === 'collection') {
        const c = catalog.collection(s.slug); if (!c) continue;
        const tr = catalog.ofCollection(c.slug);
        out += shelf(s.title || c.title, '/collection/' + c.slug, tr, list('col-' + c.slug, tr, c.title));
      } else if (s.type === 'categories') {
        out += `<section class="shelf"><div class="shelf-head"><h2>${esc(s.title || 'Categories')}</h2><a data-link class="see-all" href="${href('/browse')}">Browse</a></div>${categoryTiles()}</section>`;
      } else if (s.type === 'featured') {
        const tr = d.tracks.filter(t => t.featured);
        if (tr.length) out += shelf(s.title || 'Featured', '', tr, list('featured', tr, s.title || 'Featured'));
      } else if (s.type === 'new') {
        const tr = [...d.tracks].sort((a, b) => String(b.published_at || '').localeCompare(String(a.published_at || '')));
        out += `<section class="shelf"><div class="shelf-head"><h2>${esc(s.title || 'New')}</h2></div>${rows(tr, list('all', tr, s.title || 'All sounds'))}</section>`;
      }
    }
    return { title: '', html: out };
  },
  browse() {
    const d = catalog.data;
    let out = pageHead({ title: 'Browse', text: d.site.tagline });
    out += `<section class="shelf"><div class="shelf-head"><h2>Categories</h2></div>${categoryTiles()}</section>`;
    if (d.collections.length) {
      out += `<section class="shelf"><div class="shelf-head"><h2>Collections</h2></div><div class="tiles">${d.collections.map(c => {
        const first = catalog.ofCollection(c.slug)[0];
        return `<a class="tile" data-link href="${href('/collection/' + c.slug)}">${c.cover ? `<span class="art tile-art"><img src="${esc(c.cover.thumb)}" alt="" loading="lazy"></span>` : first ? art(first, 'tile-art') : ''}<span class="tile-text"><b>${esc(c.title)}</b><span>${c.tracks.length} sounds</span></span></a>`;
      }).join('')}</div></section>`;
    }
    if (d.tags.length) out += `<section class="shelf"><div class="shelf-head"><h2>Tags</h2></div>${tagChips(d.tags)}</section>`;
    return { title: 'Browse', html: out };
  },
  category([slug], q) {
    const c = catalog.category(slug); if (!c) return views.notFound();
    const all = catalog.inCategory(slug);
    const tag = q.get('tag') || '';
    const shown = tag ? all.filter(t => t.tags.includes(tag)) : all;
    const tagsHere = catalog.data.tags.filter(t => all.some(x => x.tags.includes(t.slug)));
    const key = list('cat-' + slug + tag, shown, c.title);
    return { title: c.title, scene: all[0]?.scene, html: pageHead({ eyebrow: c.intro, title: c.title, text: c.description, key, scene: all[0]?.scene })
      + (tagsHere.length > 1 ? `<div class="chips">${[{ slug: '', title: 'All' }, ...tagsHere].map(t => `<a data-link class="chip${t.slug === tag ? ' on' : ''}" href="${href('/c/' + slug + (t.slug ? '?tag=' + t.slug : ''))}">${esc(t.title)}</a>`).join('')}</div>` : '')
      + rows(shown, key) };
  },
  tag([slug]) {
    const t = catalog.tag(slug); if (!t) return views.notFound();
    const tr = catalog.withTag(slug);
    const key = list('tag-' + slug, tr, t.title);
    return { title: t.title, html: pageHead({ eyebrow: 'Tag', title: t.title, key }) + rows(tr, key) };
  },
  collection([slug]) {
    const c = catalog.collection(slug); if (!c) return views.notFound();
    const tr = catalog.ofCollection(slug);
    const key = list('col-' + slug, tr, c.title);
    return { title: c.title, scene: tr[0]?.scene, html: pageHead({ eyebrow: 'Collection', title: c.title, text: c.description, key, cover: c.cover, scene: tr[0]?.scene }) + rows(tr, key) };
  },
  track([slug]) {
    const t = catalog.track(slug); if (!t) return views.notFound();
    const cat = catalog.category(t.category);
    const related = catalog.inCategory(t.category).filter(x => x.slug !== t.slug).slice(0, 8);
    const key = list('cat-' + t.category, catalog.inCategory(t.category), cat?.title);
    const playingThis = player.current && player.current.slug === t.slug && player.playing;
    const tags = t.tags.map(s => catalog.tag(s)).filter(Boolean);
    return { title: t.seo?.title || t.title, scene: t.scene, html: `
      <article class="track-page">
        <div class="track-art">${t.cover ? `<img src="${esc(t.cover.url)}" alt="">` : `<span class="art" data-scene="${esc(t.scene)}"></span>`}</div>
        <div class="track-info">
          ${cat ? `<a data-link class="eyebrow" href="${href('/c/' + cat.slug)}">${esc(cat.title)}</a>` : ''}
          <h1>${esc(t.title)}</h1>
          ${t.subtitle ? `<p class="lede">${esc(t.subtitle)}</p>` : ''}
          ${t.details ? `<p class="muted">${esc(t.details)}</p>` : ''}
          <div class="head-actions">
            <button class="btn btn-primary btn-lg" data-play="${esc(t.slug)}" data-list="${key}">${icon(playingThis ? 'pause' : 'play')} ${playingThis ? 'Pause' : 'Play'}</button>
            <a class="btn btn-glass btn-lg" data-link data-listen-track="${esc(t.slug)}" href="${href('/listen/' + t.slug)}">${icon('listen')} Listening room</a>
            <button class="icon-btn lg" data-fav="${esc(t.slug)}" aria-pressed="${isFav(t.slug)}" aria-label="Save">${icon(isFav(t.slug) ? 'heartFill' : 'heart')}</button>
            <button class="icon-btn lg" data-more="${esc(t.slug)}" data-list="${key}" aria-label="More actions">${icon('more')}</button>
          </div>
          ${t.description ? `<p class="track-desc">${esc(t.description)}</p>` : ''}
          ${tags.length ? tagChips(tags) : ''}
          <p class="muted small">${t.audio ? (t.duration ? 'Length ' + fmtTime(t.duration / 1000) : 'Audio file') : 'Live generative sound — plays as long as you like'}${t.tags.includes('headphones') || t.category === 'binaural' ? ' · Best with headphones' : ''}</p>
        </div>
      </article>
      ${related.length ? `<section class="shelf"><div class="shelf-head"><h2>More in ${esc(cat?.title || '')}</h2></div>${rows(related, key)}</section>` : ''}` };
  },
  search(_, q) {
    const term = q.get('q') || '';
    const words = fold(term).split(/\s+/).filter(Boolean);
    const tagTitle = Object.fromEntries(catalog.data.tags.map(t => [t.slug, t.title]));
    const hits = words.length ? catalog.data.tracks.filter(t => {
      const hay = fold([t.title, t.subtitle, t.details, t.description, catTitle(t.category), ...t.tags.map(s => tagTitle[s] || s)].join(' '));
      return words.every(w => hay.includes(w));
    }) : [];
    const key = list('search', hits, 'Search: ' + term);
    return { title: 'Search', html: `
      <header class="page-head"><div class="page-head-text"><h1>Search</h1></div></header>
      <form class="search-form" role="search" data-search-form>
        ${icon('search')}<input type="search" name="q" value="${esc(term)}" placeholder="Sleep, rain, 432 Hz, focus…" aria-label="Search sounds" autocomplete="off" enterkeyhint="search" data-search-input>
      </form>
      ${words.length ? (hits.length ? `<p class="muted">${hits.length} ${hits.length === 1 ? 'sound' : 'sounds'}</p>${rows(hits, key)}` : `<p class="empty-state">Nothing matches “${esc(term)}”. Try one of these:</p>${tagChips(catalog.data.tags)}`)
        : `<p class="muted">Popular tags</p>${tagChips(catalog.data.tags)}`}` };
  },
  saved() {
    const tr = catalog.data.tracks.filter(t => isFav(t.slug));
    const key = list('saved', tr, 'Saved');
    return { title: 'Saved', html: pageHead({ title: 'Saved', text: tr.length ? 'Saved on this device.' : '', key: tr.length ? key : '' })
      + (tr.length ? rows(tr, key) : `<p class="empty-state">Tap ${icon('heart', 'inline')} on any sound to keep it here.</p>`) };
  },
  notFound() { return { title: 'Not found', status: 404, html: `<section class="empty-state big"><h1>Not found</h1><p>This page or sound does not exist any more.</p><a class="btn btn-primary" data-link href="${href('/')}">Go home</a></section>` }; },
};

const ROUTES = [
  [/^\/$/, 'home'], [/^\/browse$/, 'browse'], [/^\/c\/([a-z0-9-]+)$/, 'category'], [/^\/tag\/([a-z0-9-]+)$/, 'tag'],
  [/^\/collection\/([a-z0-9-]+)$/, 'collection'], [/^\/track\/([a-z0-9-]+)$/, 'track'], [/^\/search$/, 'search'],
  [/^\/saved$/, 'saved'], [/^\/listen(?:\/([a-z0-9-]+))?$/, 'listen'],
];
let route = { name: '', params: [], query: new URLSearchParams() };

function parse() {
  let raw = hashMode ? (location.hash.slice(1) || '/') : location.pathname + location.search;
  const [p, qs = ''] = raw.split('?');
  const path = p.replace(/\/+$/, '') || '/';
  for (const [re, name] of ROUTES) {
    const m = path.match(re);
    if (m) return { name, params: m.slice(1), query: new URLSearchParams(qs), path };
  }
  return { name: 'notFound', params: [], query: new URLSearchParams(qs), path };
}

export function navigate(path, { replace = false } = {}) {
  if (hashMode) {
    if (replace) location.replace('#' + path); else location.hash = path;
    return; // hashchange renders
  }
  history[replace ? 'replaceState' : 'pushState'](null, '', path);
  render(true);
}

function render(fromNav = false) {
  if (!catalog.data) return;
  const prev = route.name;
  route = parse();
  lists.clear();
  const view = $('#view');
  document.body.dataset.route = route.name;
  setActiveNav();
  if (route.name === 'listen') {
    document.body.classList.add('is-listen');
    Listen.mount(view, route.params[0], { bind: bindListenExtras });
    document.title = `${player.current ? player.current.title + ' — ' : ''}${catalog.data.site.name}`;
    syncFavButtons();
    return;
  }
  Listen.unmount();
  document.body.classList.remove('is-listen', 'clean');
  const out = (views[route.name] || views.notFound)(route.params, route.query);
  view.innerHTML = out.html + siteFooter();
  document.title = out.title ? `${out.title} — ${catalog.data.site.name}` : (catalog.data.site.seo.title || catalog.data.site.name);
  world.setScene(out.scene || (player.current && player.current.scene) || world.scene);
  paintScenes(view);
  if (fromNav && prev) { window.scrollTo(0, 0); view.focus({ preventScroll: true }); }
  const si = $('[data-search-input]', view);
  if (si && route.name === 'search' && !route.query.get('q')) si.focus();
}

function siteFooter() {
  const s = catalog.data.site;
  return `<footer class="site-foot">
    <div class="brandline">${brandHTML()}</div>
    ${s.about ? `<p class="about">${esc(s.about)}</p>` : ''}
    ${s.links.length ? `<ul class="foot-links">${s.links.map(l => `<li><a href="${esc(l.url)}" rel="noopener" target="_blank">${esc(l.label)}</a></li>`).join('')}</ul>` : ''}
    ${s.footer ? `<p class="muted small">${esc(s.footer)}</p>` : ''}
  </footer>`;
}
function brandHTML() {
  const s = catalog.data.site;
  return s.logo ? `<img class="logo" src="${esc(s.logo)}" alt="${esc(s.name)}">` : `<span class="mark" aria-hidden="true"></span><span>${esc(s.name.toLowerCase())}</span>`;
}

function setActiveNav() {
  const map = { home: '/', browse: '/browse', category: '/browse', collection: '/browse', tag: '/browse', listen: '/listen', search: '/search', saved: '/saved', track: '' };
  const cur = map[route.name] ?? '';
  $$('[data-nav]').forEach(a => { if (a.dataset.nav === cur) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
}

// ------------------------------------------------------------------ player bar
function updateBar() {
  const t = player.current;
  const bar = $('#playerbar');
  bar.hidden = !t;
  if (!t) return;
  $('#pb-art').innerHTML = art(t, 'sm');
  paintScenes($('#pb-art'));
  $('#pb-title').textContent = t.title;
  $('#pb-title').setAttribute('href', href('/listen'));
  $('#pb-title').setAttribute('aria-label', `Open the listening room: ${t.title}`);
  $('#pb-sub').textContent = player.context || catTitle(t.category);
  const pp = $('#pb-play');
  pp.innerHTML = icon(player.playing ? 'pause' : 'play');
  pp.setAttribute('aria-label', player.playing ? 'Pause' : 'Play');
  $('#pb-repeat').innerHTML = icon(player.repeat === 'one' ? 'repeatOne' : 'repeat');
  $('#pb-repeat').classList.toggle('on', player.repeat !== 'off');
  $('#pb-repeat').setAttribute('aria-label', 'Repeat: ' + player.repeat);
  $('#pb-vol').value = String(Math.round(player.volume * 100));
  $('#pb-vol-ic').innerHTML = icon(player.volume === 0 ? 'mute' : 'volume');
  updateTime(); updateTimer(); syncFavButtons(); syncRowStates();
}
function updateTime() {
  const has = player.isFile && player.duration > 0;
  $('#pb-progress').hidden = !has;
  $('#pb-live').hidden = has || !player.current;
  if (!has) return;
  $('#pb-pos').textContent = fmtTime(player.position);
  $('#pb-dur').textContent = fmtTime(player.duration);
  const seek = $('#pb-seek');
  if (document.activeElement !== seek) seek.value = String(Math.round(player.position / player.duration * 1000));
}
function updateTimer() {
  const left = player.timerLeft;
  $$('[data-timer-left]').forEach(el => { el.textContent = left ? fmtTime(left / 1000) : ''; });
  $$('[data-open-timer]').forEach(b => b.classList.toggle('on', !!left));
}
function syncRowStates() {
  const cur = player.current;
  $$('.trow').forEach(r => {
    const on = cur && r.dataset.slug === cur.slug;
    r.classList.toggle('is-current', !!on);
    const ov = r.querySelector('.play-ov'); if (ov) ov.innerHTML = icon(on && player.playing ? 'pause' : 'play');
  });
  $$('.track-page [data-play]').forEach(b => {
    const on = cur && b.dataset.play === cur.slug && player.playing;
    b.innerHTML = `${icon(on ? 'pause' : 'play')} ${on ? 'Pause' : 'Play'}`;
  });
}

// ------------------------------------------------------------------ sheets (queue, timer, more, volume)
let sheetReturn = null;
let sheetRender = null;
function openSheet(title, renderFn) {
  const sheet = $('#sheet');
  sheetReturn = document.activeElement;
  sheetRender = renderFn;
  $('#sheet-title').textContent = title;
  $('#sheet-body').innerHTML = renderFn();
  paintScenes(sheet);
  sheet.hidden = false;
  requestAnimationFrame(() => sheet.classList.add('open'));
  ($('#sheet-body button, #sheet-body a') || $('#sheet-close')).focus();
}
function rerenderSheet() { if (sheetRender && !$('#sheet').hidden) { $('#sheet-body').innerHTML = sheetRender(); paintScenes($('#sheet')); } }
function closeSheet() {
  const sheet = $('#sheet');
  if (sheet.hidden) return;
  sheet.classList.remove('open');
  sheetRender = null;
  setTimeout(() => { sheet.hidden = true; }, 220);
  if (sheetReturn && sheetReturn.focus) sheetReturn.focus();
}
const queueSheet = () => {
  if (!player.queue.length) return '<p class="empty-state">The queue is empty.</p>';
  return `<p class="muted small">${esc(player.context || 'Queue')} · repeat ${esc(player.repeat)}</p><ol class="queue">${player.queue.map((t, i) => `
    <li class="${i === player.index ? 'is-current' : ''}">
      <button class="queue-main" data-jump="${i}">${art(t, 'xs')}<span><b>${esc(t.title)}</b><span>${esc(t.subtitle || catTitle(t.category))}</span></span></button>
      ${i === player.index ? `<span class="now">${player.playing ? 'Playing' : 'Current'}</span>` : `
      <button class="icon-btn" data-qmove="${i}:-1" aria-label="Move up"${i === 0 ? ' disabled' : ''}>${icon('up')}</button>
      <button class="icon-btn" data-qmove="${i}:1" aria-label="Move down"${i === player.queue.length - 1 ? ' disabled' : ''}>${icon('down')}</button>
      <button class="icon-btn" data-qremove="${i}" aria-label="Remove">${icon('close')}</button>`}
    </li>`).join('')}</ol>`;
};
const TIMER_OPTIONS = [15, 30, 45, 60, 90, 120];
const timerSheet = () => {
  const left = player.timerLeft;
  return `<p class="muted">${left ? `Stops in <b data-timer-left>${fmtTime(left / 1000)}</b>, fading out over the last 30 seconds.` : 'Sound fades out and stops when the timer ends.'}</p>
    <div class="options">${TIMER_OPTIONS.map(m => `<button class="opt${player.timerMin === m && left ? ' on' : ''}" data-timer="${m}">${m < 60 ? m + ' min' : (m / 60) + ' h'}</button>`).join('')}
    <button class="opt" data-timer="0"${left ? '' : ' disabled'}>Off</button></div>`;
};
const moreSheet = (t, key) => `<div class="more-head">${art(t, 'sm')}<span><b>${esc(t.title)}</b><span class="muted">${esc(catTitle(t.category))}</span></span></div>
  <div class="actions-list">
    <button data-play="${esc(t.slug)}" data-list="${key || ''}" data-close>${icon('play')} Play now</button>
    <button data-play-next="${esc(t.slug)}" data-close>${icon('next')} Play next</button>
    <button data-enqueue="${esc(t.slug)}" data-close>${icon('queue')} Add to queue</button>
    <a data-link href="${href('/listen/' + t.slug)}" data-listen-track="${esc(t.slug)}" data-close>${icon('listen')} Open in the listening room</a>
    <a data-link href="${href('/track/' + t.slug)}" data-close>${icon('info')} About this sound</a>
    <button data-fav="${esc(t.slug)}" aria-pressed="${isFav(t.slug)}" data-close>${icon(isFav(t.slug) ? 'heartFill' : 'heart')} ${isFav(t.slug) ? 'Remove from saved' : 'Save'}</button>
    <button data-share="${esc(t.slug)}" data-close>${icon('share')} Copy link</button>
  </div>`;

// ------------------------------------------------------------------ actions (event delegation)
function playFromList(slug, key) {
  const t = catalog.track(slug); if (!t) return;
  if (player.current && player.current.slug === slug) { player.toggle(); return; }
  const l = lists.get(key);
  player.playTrack(t, l ? l.tracks : catalog.inCategory(t.category), { context: l ? l.label : catTitle(t.category) });
}
function shareUrl(slug) {
  const base = CONFIG.platform === 'web' ? location.origin : (CONFIG.siteUrl || '');
  return base + '/track/' + slug;
}
let toastTimer = 0;
function toast(msg) {
  const el = $('#toast'); el.textContent = msg; el.classList.add('show');
  clearTimeout(toastTimer); toastTimer = setTimeout(() => el.classList.remove('show'), 2200);
}

document.addEventListener('click', e => {
  const a = e.target.closest('a[data-link]');
  if (a && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey && e.button === 0) {
    e.preventDefault();
    if (a.dataset.listenTrack) {
      const t = catalog.track(a.dataset.listenTrack);
      if (t && (!player.current || player.current.slug !== t.slug)) player.select(t, catalog.inCategory(t.category), catTitle(t.category));
    }
    if (a.hasAttribute('data-close')) closeSheet();
    const target = hashMode ? a.getAttribute('href').replace(/^#/, '') : a.getAttribute('href');
    navigate(target);
    return;
  }
  const b = e.target.closest('button');
  if (!b) {
    if (e.target.id === 'sheet') closeSheet();
    return;
  }
  const d = b.dataset;
  if (d.play) playFromList(d.play, d.list);
  else if (d.playAll) { const l = lists.get(d.playAll); if (l && l.tracks.length) player.load(l.tracks, 0, { context: l.label, autoplay: true }); }
  else if (d.cta !== undefined) {
    const h = catalog.data.home || {};
    const t = catalog.track(h.cta_track) || catalog.data.tracks[0];
    if (t) { player.playTrack(t, catalog.inCategory(t.category), { context: catTitle(t.category) }); navigate('/listen'); }
  }
  else if (d.fav) toggleFav(d.fav);
  else if (d.favCurrent !== undefined) { if (player.current) toggleFav(player.current.slug); }
  else if (d.more) { const t = catalog.track(d.more); if (t) openSheet('Sound', () => moreSheet(t, d.list)); }
  else if (d.playNext) { const t = catalog.track(d.playNext); if (t) { player.playNext(t); toast('Plays next'); } }
  else if (d.enqueue) { const t = catalog.track(d.enqueue); if (t) { player.enqueue(t); toast('Added to queue'); } }
  else if (d.share) {
    const url = shareUrl(d.share);
    (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(() => toast('Link copied'), () => toast(url));
  }
  else if (d.openQueue !== undefined) openSheet('Queue', queueSheet);
  else if (d.openTimer !== undefined) openSheet('Sleep timer', timerSheet);
  else if (d.jump) player.jump(Number(d.jump));
  else if (d.qmove) { const [i, dir] = d.qmove.split(':').map(Number); player.move(i, i + dir); }
  else if (d.qremove) player.removeAt(Number(d.qremove));
  else if (d.timer !== undefined) { player.setTimer(Number(d.timer)); toast(Number(d.timer) ? `Sleep timer: ${d.timer} min` : 'Timer off'); }
  else if (d.hideUi !== undefined) setClean(true);
  if (b.hasAttribute('data-close') || d.timer !== undefined) closeSheet();
});
$('#sheet-close').addEventListener('click', closeSheet);
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { if (!$('#sheet').hidden) closeSheet(); else if (document.body.classList.contains('clean')) setClean(false); return; }
  if (e.altKey || e.metaKey || e.ctrlKey || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || !$('#sheet').hidden) return;
  if (e.code === 'Space' && !e.target.closest('button,a')) { e.preventDefault(); player.toggle(); }
  else if (route.name === 'listen') {
    if (e.key === 'ArrowRight') Listen.step(1); else if (e.key === 'ArrowLeft') Listen.step(-1);
    else if (e.key === 'ArrowDown') Listen.stepGroup(1); else if (e.key === 'ArrowUp') Listen.stepGroup(-1);
    else if (e.key.toLowerCase() === 'h') setClean(!document.body.classList.contains('clean'));
  }
});

// search: live results while typing
document.addEventListener('input', e => {
  if (!e.target.matches('[data-search-input], [data-top-search]')) return;
  const q = e.target.value;
  clearTimeout(window.__searchT);
  window.__searchT = setTimeout(() => {
    const target = '/search' + (q.trim() ? '?q=' + encodeURIComponent(q.trim()) : '');
    if (route.name === 'search') {
      if (hashMode) history.replaceState(null, '', '#' + target); else history.replaceState(null, '', target);
      const pos = e.target.selectionStart;
      render();
      const again = $('[data-search-input]'); if (again && e.target.matches('[data-search-input]')) { again.focus(); try { again.setSelectionRange(pos, pos); } catch (_) { /* ignore */ } }
    } else navigate(target);
  }, 220);
});
document.addEventListener('submit', e => {
  if (e.target.matches('[data-search-form], [data-top-search-form]')) {
    e.preventDefault();
    const q = e.target.querySelector('input').value.trim();
    navigate('/search' + (q ? '?q=' + encodeURIComponent(q) : ''));
  }
});

// immersive mode (listening room): hide everything but the world, tap anywhere to return
function setClean(v) {
  document.body.classList.toggle('clean', v);
  $$('.ui').forEach(el => { el.inert = v; });
  $('#restore').hidden = !v;
}
$('#restore').addEventListener('click', () => setClean(false));
$('#clean-tap').addEventListener('click', () => setClean(false));

function bindListenExtras() { syncFavButtons(); updateTimer(); }

// player bar controls
$('#pb-play').addEventListener('click', () => player.toggle());
$('#pb-next').addEventListener('click', () => player.next());
$('#pb-prev').addEventListener('click', () => player.prev());
$('#pb-repeat').addEventListener('click', () => player.cycleRepeat());
$('#pb-vol').addEventListener('input', e => player.setVolume(e.target.value / 100));
$('#pb-vol-ic').addEventListener('click', () => { const v = player.volume; player.setVolume(v > 0 ? 0 : (Number(store.get('stillroom.volume.prev')) || .8)); if (v > 0) store.set('stillroom.volume.prev', String(v)); });
$('#pb-seek').addEventListener('input', e => { if (player.duration) player.seek(e.target.value / 1000 * player.duration); });
$('#pb-open').addEventListener('click', () => navigate('/listen'));

player.addEventListener('state', () => { world.setPlaying(player.playing); document.body.classList.toggle('playing', player.playing); updateBar(); });
player.addEventListener('track', () => {
  updateBar();
  if (player.current && route.name !== 'track') world.setScene(player.current.scene);
  rerenderSheet();
  if (route.name === 'listen' && player.current) document.title = `${player.current.title} — ${catalog.data.site.name}`;
});
player.addEventListener('queue', rerenderSheet);
player.addEventListener('time', updateTime);
player.addEventListener('timer', () => { updateTimer(); if ($('#sheet-title').textContent === 'Sleep timer') { /* keep the countdown live */ const el = $('#sheet [data-timer-left]'); if (!el) rerenderSheet(); } });
player.addEventListener('settings', updateBar);
player.addEventListener('error', e => toast(e.detail?.message || 'Playback problem'));

// ------------------------------------------------------------------ boot
async function boot() {
  world.mount($('#world'));
  addEventListener('scroll', () => document.body.classList.toggle('scrolled', scrollY > 8), { passive: true });
  if (previewMode) {
    document.body.classList.add('preview');
    const b = $('#preview-banner'); b.hidden = false;
    $('#preview-exit').setAttribute('href', (hashMode ? '#' : location.pathname) + '?preview=0');
  }
  try {
    await loadCatalog();
  } catch (err) {
    $('#view').innerHTML = `<section class="empty-state big"><h1>Can’t load the sounds</h1><p>Check the internet connection and try again.</p><button class="btn btn-primary" onclick="location.reload()">Try again</button></section>`;
    return;
  }
  const d = catalog.data;
  player.siteName = d.site.name;
  $$('[data-brand]').forEach(el => { el.innerHTML = brandHTML(); });
  const last = catalog.track(store.get('stillroom.last')) || catalog.track(d.home?.cta_track) || d.tracks[0];
  if (last) player.select(last, catalog.inCategory(last.category), catTitle(last.category));
  world.setScene(last ? last.scene : 'stress-relief');
  catalog.onChange(src => {
    // the CMS published something: refresh the queue objects and the current page
    const q = player.queue.map(t => catalog.track(t.slug)).filter(Boolean);
    if (q.length) { const cur = player.current && catalog.track(player.current.slug); player.queue = q; player.index = Math.max(0, cur ? q.findIndex(t => t.slug === cur.slug) : 0); }
    player.siteName = catalog.data.site.name;
    $$('[data-brand]').forEach(el => { el.innerHTML = brandHTML(); });
    if (src === 'network') { if (route.name === 'listen') Listen.refresh(); else render(); updateBar(); }
  });
  if (hashMode) addEventListener('hashchange', () => render(true));
  else addEventListener('popstate', () => render(true));
  render();
  updateBar();
  document.documentElement.classList.add('ready');
  if ('serviceWorker' in navigator && CONFIG.platform === 'web' && !previewMode && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  }
}
window.__stillroom = { player, catalog, world }; // handle for automated tests and debugging
boot();
