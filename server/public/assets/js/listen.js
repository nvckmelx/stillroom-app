/* The listening room: the original seamless Stillroom screen, now fed by the CMS.
   Categories on top (sliding glass pill, swipe the bar to change category), swipe the content to change track,
   dots + counter, tap anywhere on the content to play/pause. No separate play button. */
import { catalog } from './catalog.js';
import { player } from './player.js';
import { world } from './world.js';
import { esc, icon, fmtTime, reduceMotion } from './util.js';

let root = null;
let lastInCat = {};
let currentSlug = null;
let off = [];

const $ = id => root && root.querySelector('#' + id);
const cats = () => catalog.data.categories.filter(c => catalog.inCategory(c.slug).length);

function trackOf(slug) { return catalog.track(slug); }

export const Listen = {
  mount(container, slug, hooks) {
    this.unmount();
    root = container;
    root.innerHTML = markup();
    const start = (slug && trackOf(slug)) || player.current || catalog.data.tracks[0];
    if (!start) { root.innerHTML = '<p class="empty-state">No sounds are published yet.</p>'; return; }
    currentSlug = null;
    if (!player.current || player.current.slug !== start.slug) {
      player.select(start, catalog.inCategory(start.category), catalog.category(start.category)?.title);
    }
    buildTabs();
    show(start, 0);
    bindGestures(hooks);
    const onTrack = () => { const t = player.current; if (t && t.slug !== currentSlug) show(t, 1); };
    const onState = () => status();
    const onTime = () => progress();
    const onTimer = () => timerLabel();
    const onSettings = () => controls();
    player.addEventListener('track', onTrack); player.addEventListener('state', onState); player.addEventListener('time', onTime);
    player.addEventListener('timer', onTimer); player.addEventListener('settings', onSettings);
    off = [() => player.removeEventListener('track', onTrack), () => player.removeEventListener('state', onState), () => player.removeEventListener('time', onTime),
      () => player.removeEventListener('timer', onTimer), () => player.removeEventListener('settings', onSettings)];
    const onResize = () => placeGlider();
    addEventListener('resize', onResize); off.push(() => removeEventListener('resize', onResize));
    status(); controls(); timerLabel(); progress();
    requestAnimationFrame(() => placeGlider(0, true));
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => root && placeGlider());
  },
  unmount() { off.forEach(f => f()); off = []; root = null; },
  refresh() { if (root) { const t = player.current || trackOf(currentSlug); buildTabs(); if (t) show(t, 0); } },
  step(d) { step(d); },
  stepGroup(d) { stepGroup(d); },
};

function markup() {
  return `
  <section class="listen" aria-label="Listening room">
    <nav class="tabs ui" id="tabs" role="tablist" aria-label="Categories"><span class="glider" id="glider" aria-hidden="true"></span></nav>
    <div class="hero ui" id="hero" role="button" tabindex="0" aria-label="Play" aria-describedby="l-title">
      <div class="slide" id="slide">
        <p class="eyebrow" id="l-eyebrow"></p>
        <h1 id="l-title"></h1>
        <p class="description" id="l-desc"></p>
        <p class="meta" id="l-meta"></p>
      </div>
      <p class="hint" id="hint"><span class="bars" aria-hidden="true"><i></i><i></i><i></i></span><span id="hintText">Tap to listen</span></p>
      <div class="dots" id="dots" aria-hidden="true"></div>
    </div>
    <div class="listen-progress ui" id="l-progress" hidden>
      <span id="l-pos">0:00</span><input type="range" id="l-seek" min="0" max="1000" value="0" aria-label="Seek"><span id="l-dur">0:00</span>
    </div>
    <div class="listen-controls ui" role="toolbar" aria-label="Playback">
      <button class="glass-btn" data-fav-current aria-label="Save">${icon('heart')}</button>
      <button class="glass-btn" id="l-repeat" aria-label="Repeat">${icon('repeat')}</button>
      <button class="glass-btn" id="l-prev" aria-label="Previous">${icon('prev')}</button>
      <button class="glass-btn" id="l-next" aria-label="Next">${icon('next')}</button>
      <button class="glass-btn" data-open-queue aria-label="Queue">${icon('queue')}</button>
      <button class="glass-btn timer-btn" data-open-timer aria-label="Sleep timer">${icon('moon')}<span id="l-timer"></span></button>
      <label class="glass-btn vol-btn" aria-label="Volume">${icon('volume')}<input type="range" id="l-vol" min="0" max="100" aria-label="Volume"></label>
      <button class="glass-btn" data-hide-ui aria-label="Hide interface">${icon('expand')}</button>
    </div>
    <div class="listen-foot ui"><span id="l-counter">01 / 01</span><a id="l-about" data-link href="#">About this sound</a></div>
  </section>`;
}

// ---------- categories bar
function buildTabs() {
  const tabs = $('tabs');
  if (!tabs) return;
  tabs.querySelectorAll('.tab').forEach(b => b.remove());
  for (const c of cats()) {
    const b = document.createElement('button');
    b.className = 'tab'; b.type = 'button'; b.dataset.cat = c.slug; b.setAttribute('role', 'tab'); b.textContent = c.title;
    b.addEventListener('click', () => { if (tabDrag.suppress) { tabDrag.suppress = false; return; } goCategory(c.slug); });
    tabs.appendChild(b);
  }
}
function placeGlider(offset = 0, instant = false) {
  const tabs = $('tabs'); const glider = $('glider'); const t = trackOf(currentSlug);
  if (!tabs || !t) return;
  const on = tabs.querySelector(`.tab[data-cat="${t.category}"]`);
  if (!on) return;
  if (instant) glider.style.transition = 'none';
  glider.style.width = on.offsetWidth + 'px';
  glider.style.transform = `translateX(${on.offsetLeft + offset}px)`;
  if (instant) { void glider.offsetWidth; glider.style.transition = ''; }
  tabs.querySelectorAll('.tab').forEach(b => b.setAttribute('aria-selected', String(b === on)));
}
function goCategory(slug, dir) {
  const t = trackOf(currentSlug);
  if (!t || slug === t.category) return;
  const list = catalog.inCategory(slug);
  if (!list.length) return;
  const order = cats().map(c => c.slug);
  const target = lastInCat[slug] && list.find(x => x.slug === lastInCat[slug]) || list[0];
  choose(target, dir || (order.indexOf(slug) > order.indexOf(t.category) ? 1 : -1));
}
function stepGroup(d) {
  const t = trackOf(currentSlug); if (!t) return;
  const order = cats().map(c => c.slug);
  goCategory(order[(order.indexOf(t.category) + d + order.length) % order.length], d);
}
const tabDrag = { x: 0, y: 0, on: false, moved: false, suppress: false };

// ---------- content
function fill(t) {
  const cat = catalog.category(t.category);
  $('l-eyebrow').textContent = cat ? (cat.intro || cat.title) : '';
  $('l-title').textContent = t.title;
  $('l-desc').textContent = world.description(t.scene) || t.subtitle;
  $('l-meta').textContent = t.details || t.subtitle || '';
  const list = catalog.inCategory(t.category), idx = list.findIndex(x => x.slug === t.slug);
  $('dots').replaceChildren(...list.map((_, k) => { const d = document.createElement('i'); if (k === idx) d.className = 'on'; return d; }));
  $('l-counter').textContent = String(idx + 1).padStart(2, '0') + ' / ' + String(list.length).padStart(2, '0');
  const about = $('l-about');
  about.setAttribute('href', (window.__href || (p => p))('/track/' + t.slug));
  controls();
}
let slideTimer = 0;
function show(t, dir) {
  const el = $('slide');
  if (!el) return;
  currentSlug = t.slug;
  lastInCat[t.category] = t.slug;
  world.setScene(t.scene);
  placeGlider();
  clearTimeout(slideTimer);
  if (reduceMotion() || !dir) { el.className = 'slide'; el.style.transform = ''; el.style.opacity = ''; fill(t); return; }
  el.className = 'slide anim'; el.style.transform = `translateX(${-dir * 70}px)`; el.style.opacity = '0';
  slideTimer = setTimeout(() => {
    if (!root) return;
    fill(t); el.className = 'slide'; el.style.transform = `translateX(${dir * 70}px)`; el.style.opacity = '0';
    void el.offsetWidth; el.className = 'slide anim'; el.style.transform = 'translateX(0)'; el.style.opacity = '1';
  }, 300);
}
/** Swipe / category change: move the player to this track; keep playing if it was playing. */
function choose(t, dir) {
  const list = catalog.inCategory(t.category);
  const label = catalog.category(t.category)?.title;
  show(t, dir);
  if (player.playing) player.playTrack(t, list, { context: label });
  else player.select(t, list, label);
}
function step(d) {
  const t = trackOf(currentSlug); if (!t) return;
  const list = catalog.inCategory(t.category), i = list.findIndex(x => x.slug === t.slug);
  choose(list[(i + d + list.length) % list.length], d);
}

// ---------- gestures: drag the content to change track, tap to play/pause; drag the bar to change category
function bindGestures(hooks) {
  const hero = $('hero'), tabs = $('tabs');
  const drag = { x: 0, y: 0, on: false, moved: false };
  const snapBack = () => { const el = $('slide'); el.className = 'slide anim'; el.style.transform = 'translateX(0)'; el.style.opacity = '1'; };
  hero.addEventListener('pointerdown', e => { if (e.button > 0) return; Object.assign(drag, { x: e.clientX, y: e.clientY, on: true, moved: false }); });
  hero.addEventListener('pointermove', e => {
    if (!drag.on) return;
    const dx = e.clientX - drag.x, dy = e.clientY - drag.y;
    if (!drag.moved && Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(dy)) { drag.moved = true; try { hero.setPointerCapture(e.pointerId); } catch (_) { /* ignore */ } }
    if (drag.moved) { const el = $('slide'); el.className = 'slide'; el.style.transform = `translateX(${dx * .65}px)`; el.style.opacity = String(Math.max(.15, 1 - Math.abs(dx) / 260)); }
  });
  hero.addEventListener('pointerup', e => {
    if (!drag.on) return; drag.on = false;
    const dx = e.clientX - drag.x, dy = e.clientY - drag.y;
    if (drag.moved) { if (Math.abs(dx) > 60) step(dx < 0 ? 1 : -1); else snapBack(); return; }
    if (Math.abs(dx) < 10 && Math.abs(dy) < 10) { ripple(e); player.toggle(); }
  });
  hero.addEventListener('pointercancel', () => { drag.on = false; snapBack(); });
  hero.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); player.toggle(); } });

  tabs.addEventListener('pointerdown', e => Object.assign(tabDrag, { x: e.clientX, y: e.clientY, on: true, moved: false }));
  tabs.addEventListener('pointermove', e => {
    if (!tabDrag.on) return;
    const dx = e.clientX - tabDrag.x, dy = e.clientY - tabDrag.y;
    if (!tabDrag.moved && Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(dy)) { tabDrag.moved = true; tabs.classList.add('dragging'); try { tabs.setPointerCapture(e.pointerId); } catch (_) { /* ignore */ } }
    if (tabDrag.moved) placeGlider(Math.max(-60, Math.min(60, -dx * .35)));
  });
  const endTab = e => {
    if (!tabDrag.on) return; tabDrag.on = false; tabs.classList.remove('dragging');
    if (tabDrag.moved) { tabDrag.suppress = true; setTimeout(() => tabDrag.suppress = false, 50); const dx = e.clientX - tabDrag.x; if (Math.abs(dx) > 40) stepGroup(dx < 0 ? 1 : -1); else placeGlider(); }
  };
  tabs.addEventListener('pointerup', endTab);
  tabs.addEventListener('pointercancel', () => { tabDrag.on = false; tabs.classList.remove('dragging'); placeGlider(); });

  $('l-prev').addEventListener('click', () => player.prev());
  $('l-next').addEventListener('click', () => player.next());
  $('l-repeat').addEventListener('click', () => player.cycleRepeat());
  $('l-vol').addEventListener('input', e => player.setVolume(e.target.value / 100));
  $('l-seek').addEventListener('input', e => { if (player.duration) player.seek(e.target.value / 1000 * player.duration); });
  if (hooks && hooks.bind) hooks.bind(root);
}
function ripple(e) {
  const r = document.createElement('span'); r.className = 'tap-ring';
  r.style.left = e.clientX + 'px'; r.style.top = e.clientY + 'px';
  document.body.appendChild(r); setTimeout(() => r.remove(), 1000);
}

// ---------- status
let hintTimer = 0;
function status() {
  if (!root) return;
  const hero = $('hero'), hint = $('hint');
  hero.setAttribute('aria-label', player.playing ? 'Pause' : 'Play');
  $('hintText').textContent = player.playing ? 'Tap to pause' : 'Tap to listen';
  hint.classList.remove('fade'); clearTimeout(hintTimer);
  if (player.playing) hintTimer = setTimeout(() => hint && hint.classList.add('fade'), 3500);
  progress();
}
function controls() {
  if (!root) return;
  const rep = $('l-repeat');
  rep.innerHTML = icon(player.repeat === 'one' ? 'repeatOne' : 'repeat');
  rep.classList.toggle('on', player.repeat !== 'off');
  rep.setAttribute('aria-label', 'Repeat: ' + player.repeat);
  $('l-vol').value = String(Math.round(player.volume * 100));
}
function timerLabel() {
  if (!root) return;
  const left = player.timerLeft;
  $('l-timer').textContent = left ? fmtTime(left / 1000) : '';
}
function progress() {
  if (!root) return;
  const box = $('l-progress');
  const show = player.isFile && player.duration > 0;
  box.hidden = !show;
  if (!show) return;
  $('l-pos').textContent = fmtTime(player.position);
  $('l-dur').textContent = fmtTime(player.duration);
  if (document.activeElement !== $('l-seek')) $('l-seek').value = String(Math.round(player.position / player.duration * 1000));
}
