/* Stillroom generative sound engine — the original Web Audio synthesis from the first app, moved into a module.
   Plays one of the 33 built-in presets (assets/js/presets.js) when a track has no uploaded audio file. */
import { PRESETS } from './presets.js';

const hzOf = (m, a) => a * Math.pow(2, (m - 69) / 12);
let ac = null, master, reverb, buf = {}, scene = null, vol = 0.7;

function init() {
  if (ac) return;
  try { if (navigator.audioSession) navigator.audioSession.type = 'playback'; } catch (e) { /* iOS 17+ only */ }
  ac = new (window.AudioContext || window.webkitAudioContext)();
  master = ac.createGain(); master.gain.value = vol * vol;
  const comp = ac.createDynamicsCompressor();
  comp.threshold.value = -16; comp.ratio.value = 3; comp.attack.value = .02; comp.release.value = .4;
  master.connect(comp); comp.connect(ac.destination);
  reverb = ac.createConvolver(); reverb.buffer = impulse(4.8, 2.6);
  const ret = ac.createGain(); ret.gain.value = .85; reverb.connect(ret); ret.connect(master);
  ['white', 'pink', 'brown', 'vinyl'].forEach(t => buf[t] = noiseBuffer(t));
  setInterval(() => { if (scene && !scene.dead && scene.tick) scene.tick(scene, ac.currentTime, document.hidden ? 2.5 : .7); }, 150);
}
function impulse(sec, decay) {
  const n = Math.floor(ac.sampleRate * sec), b = ac.createBuffer(2, n, ac.sampleRate);
  for (let c = 0; c < 2; c++) { const d = b.getChannelData(c); for (let i = 0; i < n; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / n, decay); }
  return b;
}
function noiseBuffer(type) {
  const n = ac.sampleRate * 9, b = ac.createBuffer(2, n, ac.sampleRate);
  for (let c = 0; c < 2; c++) {
    const d = b.getChannelData(c); let b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0, last = 0;
    for (let i = 0; i < n; i++) {
      const w = Math.random() * 2 - 1;
      if (type === 'white') d[i] = w * .5;
      else if (type === 'pink') { b0 = .99886 * b0 + w * .0555179; b1 = .99332 * b1 + w * .0750759; b2 = .969 * b2 + w * .153852; b3 = .8665 * b3 + w * .3104856; b4 = .55 * b4 + w * .5329522; b5 = -.7616 * b5 - w * .016898; d[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + w * .5362) * .11; b6 = w * .115926; }
      else if (type === 'brown') { last = (last + .02 * w) / 1.02; d[i] = last * 3.2; }
      else d[i] = w * .012 + (Math.random() < .00022 ? (Math.random() * 2 - 1) * .7 : 0);
    }
    const f = Math.floor(ac.sampleRate * .25); for (let i = 0; i < f; i++) { const k = i / f; d[i] = d[i] * k + d[n - f + i] * (1 - k); }
  }
  return b;
}
function src(type) { const s = ac.createBufferSource(); s.buffer = buf[type]; s.loop = true; s.start(ac.currentTime, Math.random() * 8); return s; }
function lfo(freq, depth, target, type = 'sine') { const o = ac.createOscillator(); o.type = type; o.frequency.value = freq; const g = ac.createGain(); g.gain.value = depth; o.connect(g); g.connect(target); o.start(); return o; }

function createScene(item) {
  const now = ac.currentTime, S = { item, srcs: [], dead: false };
  S.out = ac.createGain(); S.out.gain.setValueAtTime(0, now); S.out.gain.linearRampToValueAtTime(1, now + 2.5); S.out.connect(master);
  const wet = ac.createGain(); wet.gain.value = item.kind === 'binaural' ? .12 : (item.p?.wet ?? .4); S.out.connect(wet); wet.connect(reverb);
  S.filter = ac.createBiquadFilter(); S.filter.type = 'lowpass'; S.filter.Q.value = .6; S.filter.frequency.value = item.p?.cut ?? 800; S.filter.connect(S.out);
  S.srcs.push(lfo(.031, item.p?.lfo ?? 200, S.filter.frequency));
  S.pf = ac.createBiquadFilter(); S.pf.type = 'lowpass'; S.pf.frequency.value = 3600; S.pf.connect(S.out);
  buildGenerator(S, now);
  return S;
}
function buildGenerator(S, now) {
  const it = S.item;
  if (it.kind === 'mood') {
    const p = it.p; S.chords = p.chords.map(c => c.map(n => hzOf(p.root + n, p.tune))); S.dur = p.dur; S.wave = p.wave; S.padLv = p.pad;
    S.nextChord = now + .05; S.ci = 0; S.nextPl = now + (p.pl?.type === 'bowl' ? 1.5 : 2); S.nextArp = now + 1; S.ai = 0; S.nextDrum = now + .2; S.si = 0; S.nextBird = now + 3;
    (p.noise || []).forEach(([t, l]) => addNoise(S, t, l));
    if (p.beat) { S.dr = ac.createGain(); S.dr.gain.value = .55; const f = ac.createBiquadFilter(); f.type = 'lowpass'; f.frequency.value = 2200; S.dr.connect(f); f.connect(S.out); }
    S.tick = moodTick;
  } else if (it.kind === 'binaural') {
    const m = ac.createChannelMerger(2), g = ac.createGain(); g.gain.value = .2;
    const L = ac.createOscillator(), R = ac.createOscillator(); L.frequency.value = it.carrier; R.frequency.value = it.carrier + it.beat;
    L.connect(m, 0, 0); R.connect(m, 0, 1); m.connect(g); g.connect(S.out); L.start(); R.start(); S.srcs.push(L, R);
    S.filter.frequency.value = 600; S.chords = [[it.carrier / 2, it.carrier * .75]]; S.dur = 14; S.wave = 'sine'; S.padLv = .16; S.nextChord = now + .05; S.ci = 0;
    addNoise(S, 'pink', .035); S.tick = padOnlyTick;
  } else if (it.kind === 'tone') {
    const f = it.f, lv = .15 * Math.sqrt(300 / Math.max(f, 300)), g = ac.createGain(); g.gain.value = lv;
    const o = ac.createOscillator(); o.frequency.value = f; o.connect(g); g.connect(S.out); o.start(); S.srcs.push(o, lfo(.1, lv * .35, g.gain));
    const sg = ac.createGain(); sg.gain.value = .05; const o2 = ac.createOscillator(); o2.frequency.value = f / 2; o2.connect(sg); sg.connect(S.out); o2.start(); S.srcs.push(o2);
    let b = f; while (b > 180) b /= 2; while (b < 90) b *= 2;
    S.filter.frequency.value = 900; S.chords = [[b, b * 1.5, b * 2], [b, b * 1.5, b * 3]]; S.dur = 14; S.wave = 'triangle'; S.padLv = .26; S.nextChord = now + .05; S.ci = 0; S.tick = padOnlyTick;
  } else {
    it.layers.forEach(([t, l]) => addNoise(S, t, l));
  }
}
function addNoise(S, type, lv) {
  const g = ac.createGain(); g.gain.value = lv; g.connect(S.out);
  const chain = (s, ...nodes) => { let n = s; nodes.forEach(x => { n.connect(x); n = x; }); n.connect(g); S.srcs.push(s); };
  const bq = (t, f, q = .7) => { const b = ac.createBiquadFilter(); b.type = t; b.frequency.value = f; b.Q.value = q; return b; };
  if (type === 'white' || type === 'pink' || type === 'brown') chain(src(type), bq('lowpass', type === 'white' ? 12000 : 9000));
  else if (type === 'rain') { chain(src('white'), bq('highpass', 900), bq('lowpass', 6500), bq('peaking', 3200)); S.srcs.push(lfo(.17, lv * .12, g.gain)); }
  else if (type === 'ocean') { g.gain.value = lv * .55; chain(src('brown'), bq('lowpass', 850)); const foam = ac.createGain(); foam.gain.value = .08; const fs = src('pink'); const hp = bq('highpass', 1400); fs.connect(hp); hp.connect(foam); foam.connect(g); S.srcs.push(fs, lfo(1 / 14, lv * .5, g.gain)); }
  else if (type === 'stream') { chain(src('pink'), bq('bandpass', 1900, .6), bq('highshelf', 4000)); S.srcs.push(lfo(.37, lv * .18, g.gain)); }
  else if (type === 'wind') { const f = bq('bandpass', 520, 1.4); chain(src('pink'), f); S.srcs.push(lfo(.05, 320, f.frequency), lfo(.08, lv * .5, g.gain)); }
  else if (type === 'vinyl') chain(src('vinyl'), bq('highpass', 1800));
  else if (type === 'crickets') {
    const o = ac.createOscillator(); o.frequency.value = 4650; const a = ac.createGain(); a.gain.value = .5; const b = ac.createGain(); b.gain.value = .5;
    o.connect(a); a.connect(b); b.connect(g); o.start(); S.srcs.push(o, lfo(17, .5, a.gain, 'square'), lfo(.55, .5, b.gain, 'square'));
  }
}
function padChord(S, freqs, t, dur) {
  const att = dur * .45, rel = dur * .65, lv = S.padLv / freqs.length / 2;
  freqs.forEach(f => [-6, 6].forEach(det => {
    const o = ac.createOscillator(), g = ac.createGain(); o.type = S.wave; o.frequency.value = f; o.detune.value = det;
    g.gain.setValueAtTime(0, t); g.gain.linearRampToValueAtTime(lv, t + att); g.gain.setValueAtTime(lv, t + dur); g.gain.linearRampToValueAtTime(0, t + dur + rel);
    o.connect(g); g.connect(S.filter); o.start(t); o.stop(t + dur + rel + .05);
  }));
}
const PLUCK = { soft: { p: [[1, 1], [2, .15]], a: .03, d: 3.5 }, bell: { p: [[1, 1], [2.76, .32], [5.4, .1]], a: .004, d: 4.2 },
  bowl: { p: [[1, 1], [1.004, .8], [2.71, .45], [5.15, .2], [8.6, .07]], a: .02, d: 13 }, piano: { p: [[1, 1], [2, .45], [3, .18], [4, .08]], a: .006, d: 2.8 } };
function pluck(S, f, t, type, lv) {
  const P = PLUCK[type], g = ac.createGain(); g.gain.setValueAtTime(0, t); g.gain.linearRampToValueAtTime(lv, t + P.a); g.gain.exponentialRampToValueAtTime(.0001, t + P.d); g.connect(S.pf);
  P.p.forEach(([r, a]) => { const o = ac.createOscillator(), pg = ac.createGain(); o.frequency.value = f * r; pg.gain.value = a; o.connect(pg); pg.connect(g); o.start(t); o.stop(t + P.d + .05); });
}
const pick = a => a[Math.floor(Math.random() * a.length)];
function padOnlyTick(S, now, la) { while (S.nextChord < now + la) { padChord(S, S.chords[S.ci % S.chords.length], S.nextChord, S.dur); S.nextChord += S.dur; S.ci++; } }
function moodTick(S, now, la) {
  const p = S.item.p;
  while (S.nextChord < now + la) { S.cur = S.chords[S.ci % S.chords.length]; padChord(S, S.cur, S.nextChord, S.dur); S.nextChord += S.dur; S.ci++; }
  const ch = S.cur || S.chords[0];
  if (p.pl) {
    if (p.pl.type === 'arp') { while (S.nextArp < now + la) { const k = S.ai % 8, f = ch[(k < 4 ? k : 7 - k) % ch.length] * (k < 4 ? 2 : 4); pluck(S, f, S.nextArp, 'soft', p.pl.lv); S.ai++; S.nextArp += p.pl.step; } }
    else if (p.pl.type === 'bowl') { while (S.nextPl < now + la) { pluck(S, ch[0] * 4, S.nextPl, 'bowl', p.pl.lv); S.nextPl += 15 + Math.random() * 15; } }
    else { while (S.nextPl < now + la) { pluck(S, pick(ch) * pick([2, 2, 4]), S.nextPl, p.pl.type, p.pl.lv * (.7 + Math.random() * .3)); S.nextPl += Math.max(.25, -Math.log(1 - Math.random()) / p.pl.rate); } }
  }
  if (p.beat) {
    const step = 60 / p.beat / 4;
    while (S.nextDrum < now + la) { const s = S.si % 16, t = S.nextDrum + (s % 2 ? step * .2 : 0); if (s === 0 || s === 10) kick(S, t); if (s === 4 || s === 12) snare(S, t); if (s % 2 === 0) hat(S, t, s % 4 ? .5 : .9); S.si++; S.nextDrum += step; }
  }
  if (p.birds) {
    while (S.nextBird < now + la) {
      const n = 2 + Math.floor(Math.random() * 4), base = 2600 + Math.random() * 1800;
      for (let k = 0; k < n; k++) {
        const t = S.nextBird + k * .12, o = ac.createOscillator(), g = ac.createGain(); o.frequency.setValueAtTime(base, t); o.frequency.exponentialRampToValueAtTime(base * 1.45, t + .07);
        g.gain.setValueAtTime(0, t); g.gain.linearRampToValueAtTime(.025, t + .01); g.gain.linearRampToValueAtTime(0, t + .08); o.connect(g); g.connect(S.out); o.start(t); o.stop(t + .1);
      }
      S.nextBird += 3 + Math.random() * 8;
    }
  }
}
function kick(S, t) { const o = ac.createOscillator(), g = ac.createGain(); o.frequency.setValueAtTime(110, t); o.frequency.exponentialRampToValueAtTime(45, t + .12); g.gain.setValueAtTime(.55, t); g.gain.exponentialRampToValueAtTime(.001, t + .38); o.connect(g); g.connect(S.dr); o.start(t); o.stop(t + .4); }
function hit(S, t, type, f, lv, d) { const s = ac.createBufferSource(); s.buffer = buf.white; const b = ac.createBiquadFilter(); b.type = type; b.frequency.value = f; const g = ac.createGain(); g.gain.setValueAtTime(lv, t); g.gain.exponentialRampToValueAtTime(.001, t + d); s.connect(b); b.connect(g); g.connect(S.dr); s.start(t, Math.random() * 5); s.stop(t + d + .02); }
const snare = (S, t) => hit(S, t, 'bandpass', 1700, .2, .16), hat = (S, t, a) => hit(S, t, 'highpass', 7500, .045 * a, .05);

function stopScene(S) {
  if (!S) return;
  S.dead = true; const now = ac.currentTime;
  S.out.gain.cancelScheduledValues(now); S.out.gain.setValueAtTime(S.out.gain.value, now); S.out.gain.linearRampToValueAtTime(0, now + 2.2);
  setTimeout(() => { S.srcs.forEach(s => { try { s.stop(); } catch (e) { /* already stopped */ } }); try { S.out.disconnect(); } catch (e) { /* gone */ } }, 2600);
}

export const Sound = {
  has: id => !!PRESETS[id],
  /** Start a built-in preset (fades in ~2.5 s); replaces whatever generative sound is playing. */
  start(id) {
    const preset = PRESETS[id];
    if (!preset) return false;
    init(); ac.resume();
    master.gain.cancelScheduledValues(ac.currentTime); master.gain.setTargetAtTime(vol * vol, ac.currentTime, .05);
    stopScene(scene); scene = createScene(preset);
    return true;
  },
  stop() { if (scene) { stopScene(scene); scene = null; } },
  get active() { return !!scene; },
  setVolume(v) { vol = v; if (ac) master.gain.setTargetAtTime(vol * vol, ac.currentTime, .05); },
  /** Linear fade of the master to silence over `sec` seconds (sleep timer). */
  fadeOut(sec) { if (!ac) return; master.gain.cancelScheduledValues(ac.currentTime); master.gain.setValueAtTime(master.gain.value, ac.currentTime); master.gain.linearRampToValueAtTime(0, ac.currentTime + sec); },
  restore() { if (ac) { master.gain.cancelScheduledValues(ac.currentTime); master.gain.setTargetAtTime(vol * vol, ac.currentTime, .1); } },
};
