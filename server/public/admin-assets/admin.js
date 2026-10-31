/* Stillroom admin — progressive enhancement. Every form works without this file. */
(() => {
  'use strict';
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // ---- mobile navigation
  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.getElementById('sidenav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = !nav.classList.contains('open');
      nav.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', e => {
      if (nav.classList.contains('open') && !nav.contains(e.target) && !toggle.contains(e.target)) {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // ---- static thumbnails of the 33 background scenes (same engine as the site)
  const thumbCache = new Map();
  function sceneThumb(id) {
    if (thumbCache.has(id)) return thumbCache.get(id);
    const host = document.createElement('div');
    host.style.cssText = 'position:fixed;left:-9999px;top:0;width:240px;height:160px';
    document.body.appendChild(host);
    let url = '';
    try {
      const bg = window.StillroomBackgrounds.mount(host, { preset: id, reducedMotion: true, maxDpr: 1 });
      url = host.querySelector('canvas').toDataURL('image/webp', .8);
      bg.destroy();
    } catch (e) { /* engine missing: keep the plain tile */ }
    host.remove();
    thumbCache.set(id, url);
    return url;
  }
  function paintScenes() {
    if (!window.StillroomBackgrounds) return;
    const io = new IntersectionObserver(entries => {
      for (const en of entries) {
        if (!en.isIntersecting) continue;
        const el = en.target;
        io.unobserve(el);
        if (el.querySelector('img')) continue;
        const url = sceneThumb(el.dataset.scene);
        if (url) el.style.background = `center/cover no-repeat url(${url})`;
      }
    }, { rootMargin: '200px' });
    $$('[data-scene]').forEach(el => io.observe(el));
  }
  window.addEventListener('load', paintScenes);

  // ---- reorder lists: ↑ ↓ buttons everywhere, drag by the handle on desktop
  function markChanged(list) {
    const form = list.closest('form');
    const bar = form && form.querySelector('[data-order-bar]');
    if (bar) bar.hidden = false;
  }
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-move]');
    if (!btn) return;
    const li = btn.closest('li');
    const dir = Number(btn.dataset.move);
    const sib = dir < 0 ? li.previousElementSibling : li.nextElementSibling;
    if (!sib || sib.hasAttribute('data-template')) return;
    dir < 0 ? li.parentNode.insertBefore(li, sib) : li.parentNode.insertBefore(sib, li);
    btn.focus();
    markChanged(li.parentNode);
  });
  $$('[data-sortable]:not([data-sort-disabled])').forEach(list => {
    let dragged = null;
    $$('.row-item', list).forEach(li => {
      const handle = li.querySelector('.handle');
      if (!handle) return;
      handle.addEventListener('mousedown', () => li.setAttribute('draggable', 'true'));
      li.addEventListener('dragstart', e => { dragged = li; li.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
      li.addEventListener('dragend', () => { li.classList.remove('dragging'); li.removeAttribute('draggable'); $$('.drop-before', list).forEach(x => x.classList.remove('drop-before')); });
      li.addEventListener('dragover', e => { if (!dragged || dragged === li) return; e.preventDefault(); $$('.drop-before', list).forEach(x => x.classList.remove('drop-before')); li.classList.add('drop-before'); });
      li.addEventListener('drop', e => { e.preventDefault(); if (dragged && dragged !== li) { list.insertBefore(dragged, li); markChanged(list); } });
    });
  });

  // ---- collection picker: ticked tracks float to the top, filter box
  $$('[data-picker]').forEach(list => {
    list.addEventListener('change', e => {
      const li = e.target.closest('li');
      if (!li || e.target.type !== 'checkbox') return;
      li.classList.toggle('is-on', e.target.checked);
      if (e.target.checked) {
        const lastOn = [...list.children].filter(x => x !== li && x.classList.contains('is-on')).pop();
        lastOn ? lastOn.after(li) : list.prepend(li);
      }
    });
  });
  $$('[data-filter-list]').forEach(input => {
    const list = input.parentNode.querySelector('[data-picker]');
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      $$('li', list).forEach(li => { li.hidden = q !== '' && !li.textContent.toLowerCase().includes(q); });
    });
  });

  // ---- home sections editor
  const sections = document.querySelector('[data-sections]');
  const addSection = document.querySelector('[data-add-section]');
  if (sections && addSection) {
    const tpl = sections.querySelector('[data-template]');
    const syncRef = row => {
      const type = row.querySelector('select[name="section_type[]"]');
      const ref = row.querySelector('select[name="section_ref[]"]');
      ref.style.visibility = type.value === 'collection' ? 'visible' : 'hidden';
    };
    $$('.section-row:not([data-template])', sections).forEach(syncRef);
    sections.addEventListener('change', e => { if (e.target.name === 'section_type[]') syncRef(e.target.closest('li')); });
    sections.addEventListener('click', e => { if (e.target.closest('[data-remove]')) e.target.closest('li').remove(); });
    addSection.addEventListener('click', () => {
      const row = tpl.cloneNode(true);
      row.removeAttribute('data-template');
      row.hidden = false;
      $$('[disabled]', row).forEach(x => x.disabled = false);
      sections.insertBefore(row, tpl);
      syncRef(row);
      row.querySelector('select').focus();
    });
  }

  // ---- slug follows the title until edited by hand
  const slugSrc = document.querySelector('[data-slug-source]');
  const slugDst = document.querySelector('[data-slug-target]');
  if (slugSrc && slugDst && slugDst.value === '') {
    let touched = false;
    slugDst.addEventListener('input', () => { touched = true; });
    const tr = { а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'e',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'h',ц:'ts',ч:'ch',ш:'sh',щ:'sch',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya' };
    slugSrc.addEventListener('input', () => {
      if (touched) return;
      slugDst.value = slugSrc.value.toLowerCase().split('').map(c => tr[c] ?? c).join('')
        .normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
    });
  }

  // ---- audio: size check, duration from metadata
  $$('[data-audio-input]').forEach(input => {
    const info = input.closest('section').querySelector('[data-audio-info]');
    const dur = input.form.querySelector('[data-duration]');
    input.addEventListener('change', () => {
      const f = input.files[0];
      if (dur) dur.value = '';
      if (!f) { info.textContent = ''; return; }
      const max = Number(input.dataset.max || 0);
      if (max && f.size > max) {
        info.textContent = `This file is ${(f.size / 1048576).toFixed(1)} MB — over the ${(max / 1048576).toFixed(0)} MB limit.`;
        input.value = '';
        return;
      }
      const a = new Audio();
      a.preload = 'metadata';
      a.src = URL.createObjectURL(f);
      a.onloadedmetadata = () => {
        if (isFinite(a.duration) && dur) dur.value = String(Math.round(a.duration * 1000));
        const m = Math.floor(a.duration / 60), s = Math.round(a.duration % 60);
        info.textContent = `${f.name} · ${(f.size / 1048576).toFixed(1)} MB · ${m}:${String(s).padStart(2, '0')}`;
        URL.revokeObjectURL(a.src);
      };
      a.onerror = () => { info.textContent = `${f.name} · ${(f.size / 1048576).toFixed(1)} MB — the browser cannot read this format; the server will check it.`; };
    });
  });

  // ---- image preview
  $$('[data-image-input]').forEach(input => {
    const img = input.closest('label').parentNode.querySelector('[data-image-preview]');
    input.addEventListener('change', () => {
      const f = input.files[0];
      if (!img) return;
      if (!f) { img.hidden = true; return; }
      img.src = URL.createObjectURL(f);
      img.hidden = false;
    });
  });

  // ---- upload with a progress bar (large audio files)
  $$('[data-upload-form]').forEach(form => {
    form.addEventListener('submit', e => {
      const hasFile = $$('input[type=file]', form).some(i => i.files && i.files.length);
      if (!hasFile || !window.FormData || !window.XMLHttpRequest) return;
      e.preventDefault();
      const fd = new FormData(form, e.submitter || undefined);
      const bar = form.querySelector('[data-progress]');
      const fill = bar && bar.querySelector('span');
      const label = bar && bar.querySelector('b');
      $$('button', form).forEach(b => b.disabled = true);
      if (bar) bar.hidden = false;
      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('action') || location.href);
      xhr.upload.onprogress = ev => {
        if (!ev.lengthComputable || !bar) return;
        const p = Math.round(ev.loaded / ev.total * 100);
        fill.style.width = p + '%';
        label.textContent = p < 100 ? `Uploading… ${p}%` : 'Processing…';
      };
      const actionUrl = new URL(form.getAttribute('action') || location.href, location.href).href;
      xhr.onload = () => {
        // the server redirects after a successful save; follow it. Otherwise show the returned page (validation errors).
        if (xhr.status < 400 && xhr.responseURL && xhr.responseURL !== actionUrl) {
          location.href = xhr.responseURL;
          return;
        }
        document.open();
        document.write(xhr.responseText);
        document.close();
      };
      xhr.onerror = () => {
        $$('button', form).forEach(b => b.disabled = false);
        if (label) label.textContent = 'Upload failed — check the connection and try again.';
      };
      xhr.send(fd);
    });
  });

  // ---- confirmations and the auto-opened preview
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-confirm]');
    if (b && !window.confirm(b.dataset.confirm)) e.preventDefault();
  });
  const auto = document.querySelector('[data-autopen]');
  if (auto) window.open(auto.href, 'stillroom-preview');
})();
