/* Stillroom player: one queue for uploaded audio files (HTMLAudioElement) and built-in generative sounds (sound.js).
   Queue, repeat (all / one / off), volume, sleep timer with fade-out, Media Session (lock screen / headphones). */
import { Sound } from './sound.js';
import { store } from './util.js';

const REPEAT = ['all', 'one', 'off'];
const ease = t => t * t * (3 - 2 * t);

class Player extends EventTarget {
  constructor() {
    super();
    this.queue = [];          // track objects from the catalog
    this.index = -1;
    this.context = '';        // label of where the queue came from
    this.playing = false;
    this.repeat = REPEAT.includes(store.get('stillroom.repeat')) ? store.get('stillroom.repeat') : 'all';
    const v = parseFloat(store.get('stillroom.volume'));
    this.volume = isNaN(v) ? 0.8 : Math.min(1, Math.max(0, v));
    this.timerEnd = 0; this.timerMin = 0; this.fading = false;
    this.audio = new Audio();
    this.audio.preload = 'auto';
    this.audio.addEventListener('ended', () => this._ended());
    this.audio.addEventListener('timeupdate', () => this._emit('time'));
    this.audio.addEventListener('loadedmetadata', () => this._emit('time'));
    this.audio.addEventListener('error', () => { if (this.current && this.current.audio && this.playing) this._fallback(); });
    this._fadeRaf = 0;
    Sound.setVolume(this.volume);
    setInterval(() => this._tickTimer(), 1000);
    this._mediaSession();
  }

  get current() { return this.queue[this.index] || null; }
  get isFile() { return !!(this.current && this.current.audio && !this._usingFallback); }
  get position() { return this.isFile ? this.audio.currentTime : 0; }
  get duration() { return this.isFile && isFinite(this.audio.duration) ? this.audio.duration : 0; }

  /** Replace the queue and select one track. */
  load(tracks, startIndex = 0, { context = '', autoplay = false } = {}) {
    const wasPlaying = this.playing;
    this.queue = tracks.slice();
    this.context = context;
    this._select(Math.max(0, Math.min(startIndex, this.queue.length - 1)), autoplay || wasPlaying);
    this._emit('queue');
  }

  /** Play a track; `list` becomes the queue (defaults to the current queue or just this track). */
  playTrack(track, list = null, opts = {}) {
    const q = list && list.length ? list : (this.queue.some(t => t.slug === track.slug) ? this.queue : [track]);
    const i = Math.max(0, q.findIndex(t => t.slug === track.slug));
    if (q === this.queue && i === this.index && !opts.restart) {
      if (!this.playing && opts.autoplay !== false) this.play();
      return;
    }
    this.load(q, i, { context: opts.context ?? this.context, autoplay: opts.autoplay !== false });
  }

  /** Select without changing play state (used by swipes while paused). */
  select(track, list, context) {
    const q = list && list.length ? list : this.queue;
    const i = q.findIndex(t => t.slug === track.slug);
    if (q !== this.queue) { this.queue = q.slice(); this.context = context ?? this.context; this._emit('queue'); }
    this._select(i < 0 ? 0 : i, this.playing);
  }

  _select(i, autoplay) {
    const prev = this.current;
    this.index = i;
    const t = this.current;
    this._usingFallback = false;
    if (!t) return;
    if (prev && prev.slug === t.slug && this.playing) { this._emit('track'); return; }
    this._stopOutputs(prev && prev.audio ? 0.35 : 0);
    if (t.audio) {
      this.audio.src = t.audio.url;
      this.audio.loop = this.repeat === 'one';
    } else {
      this.audio.removeAttribute('src'); this.audio.load();
    }
    store.set('stillroom.last', t.slug);
    this._emit('track');
    this._updateMeta();
    if (autoplay) this.play(); else this._setPlaying(false);
  }

  play() {
    const t = this.current;
    if (!t) return;
    if (this.timerEnd && this.fading) { this.cancelTimer(); }
    if (t.audio && !this._usingFallback) {
      this.audio.loop = this.repeat === 'one';
      this.audio.volume = 0;
      const p = this.audio.play();
      if (p && p.catch) p.catch(err => { if (err && err.name === 'NotAllowedError') { this._setPlaying(false); } else this._fallback(); });
      this._fadeAudio(this.volume, 1200);
    } else if (!Sound.start(t.engine)) {
      this._emit('error', { message: 'This track has no playable sound.' });
      return;
    }
    this._setPlaying(true);
  }

  pause() {
    this._stopOutputs(0.6);
    this._setPlaying(false);
  }

  toggle() { this.playing ? this.pause() : this.play(); }

  next(auto = false) {
    if (!this.queue.length) return;
    if (this.index < this.queue.length - 1) this._select(this.index + 1, this.playing || auto);
    else if (this.repeat !== 'off' || !auto) this._select(0, this.playing || auto);
    else { this.pause(); }
  }

  prev() {
    if (!this.queue.length) return;
    if (this.isFile && this.audio.currentTime > 3) { this.audio.currentTime = 0; return; }
    this._select(this.index > 0 ? this.index - 1 : this.queue.length - 1, this.playing);
  }

  seek(sec) { if (this.isFile && isFinite(sec)) { this.audio.currentTime = Math.max(0, Math.min(sec, this.duration || sec)); this._emit('time'); } }

  enqueue(track) {
    if (!this.queue.length) { this.load([track], 0, { context: 'Queue' }); return; }
    this.queue.push(track); this._emit('queue');
  }
  playNext(track) {
    if (!this.queue.length) { this.load([track], 0, { context: 'Queue' }); return; }
    this.queue.splice(this.index + 1, 0, track); this._emit('queue');
  }
  removeAt(i) {
    if (i === this.index || i < 0 || i >= this.queue.length) return;
    this.queue.splice(i, 1);
    if (i < this.index) this.index--;
    this._emit('queue');
  }
  move(from, to) {
    if (from === to || from < 0 || to < 0 || from >= this.queue.length || to >= this.queue.length) return;
    const [t] = this.queue.splice(from, 1);
    this.queue.splice(to, 0, t);
    if (this.index === from) this.index = to;
    else if (from < this.index && to >= this.index) this.index--;
    else if (from > this.index && to <= this.index) this.index++;
    this._emit('queue');
  }
  jump(i) { if (i >= 0 && i < this.queue.length) this._select(i, true); }

  cycleRepeat() { this.setRepeat(REPEAT[(REPEAT.indexOf(this.repeat) + 1) % REPEAT.length]); }
  setRepeat(mode) {
    this.repeat = REPEAT.includes(mode) ? mode : 'all';
    this.audio.loop = this.repeat === 'one';
    store.set('stillroom.repeat', this.repeat);
    this._emit('settings');
  }

  setVolume(v) {
    this.volume = Math.min(1, Math.max(0, v));
    store.set('stillroom.volume', String(this.volume));
    if (!this.fading) { this.audio.volume = this.playing ? this.volume : this.audio.volume; Sound.setVolume(this.volume); }
    this._emit('settings');
  }

  /** Sleep timer in minutes (0 = off). Starts playback if needed; fades out over the last 30 s, then pauses. */
  setTimer(min) {
    this.cancelTimer(false);
    this.timerMin = min;
    this.timerEnd = min ? Date.now() + min * 60000 : 0;
    if (min && !this.playing) this.play();
    this._emit('timer');
  }
  cancelTimer(emit = true) {
    if (this.fading) { this.fading = false; Sound.restore(); this._fadeAudio(this.volume, 400); }
    this.timerEnd = 0; this.timerMin = 0;
    if (emit) this._emit('timer');
  }
  get timerLeft() { return this.timerEnd ? Math.max(0, this.timerEnd - Date.now()) : 0; }

  _tickTimer() {
    if (!this.timerEnd) return;
    const left = this.timerLeft;
    if (this.playing && left < 30000 && !this.fading) {
      this.fading = true;
      Sound.fadeOut(left / 1000);
      this._fadeAudio(0, left);
    }
    if (left === 0) {
      this.timerEnd = 0; this.timerMin = 0;
      this.pause();
      this.fading = false;
      Sound.restore();
    }
    this._emit('timer');
  }

  _ended() {
    if (this.repeat === 'one') { this.audio.currentTime = 0; this.audio.play(); return; }
    this.next(true);
  }

  /** The audio file failed to load (offline, removed): fall back to the built-in sound if the track has one. */
  _fallback() {
    const t = this.current;
    if (t && t.engine && !this._usingFallback) {
      this._usingFallback = true;
      Sound.start(t.engine);
      this._setPlaying(true);
      this._emit('track');
    } else {
      this._setPlaying(false);
      this._emit('error', { message: 'This audio could not be loaded. Check the connection.' });
    }
  }

  _stopOutputs(fadeSec) {
    Sound.stop();
    if (!this.audio.paused) {
      const a = this.audio;
      if (fadeSec > 0) this._fadeAudio(0, fadeSec * 1000, () => a.pause());
      else a.pause();
    }
  }

  _fadeAudio(target, ms, done) {
    cancelAnimationFrame(this._fadeRaf);
    const a = this.audio, from = a.volume, t0 = performance.now();
    const step = now => {
      const k = Math.min(1, (now - t0) / Math.max(1, ms));
      try { a.volume = Math.max(0, Math.min(1, from + (target - from) * ease(k))); } catch (e) { /* iOS: read-only */ }
      if (k < 1) this._fadeRaf = requestAnimationFrame(step); else if (done) done();
    };
    this._fadeRaf = requestAnimationFrame(step);
  }

  _setPlaying(v) {
    this.playing = v;
    try { navigator.mediaSession.playbackState = v ? 'playing' : 'paused'; } catch (e) { /* unsupported */ }
    this._emit('state');
  }

  _emit(type, detail) { this.dispatchEvent(new CustomEvent(type, { detail })); }

  _updateMeta() {
    const t = this.current;
    if (!t || !('mediaSession' in navigator)) return;
    try {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: t.title, artist: this.siteName || 'Stillroom', album: this.context || t.subtitle || '',
        artwork: t.cover ? [{ src: t.cover.url, sizes: '1024x1024' }] : (this.artwork || []),
      });
    } catch (e) { /* unsupported */ }
  }

  _mediaSession() {
    if (!('mediaSession' in navigator)) return;
    const ms = navigator.mediaSession;
    const set = (a, f) => { try { ms.setActionHandler(a, f); } catch (e) { /* unsupported action */ } };
    set('play', () => this.play());
    set('pause', () => this.pause());
    set('nexttrack', () => this.next());
    set('previoustrack', () => this.prev());
    set('seekto', d => this.seek(d.seekTime));
  }
}

export const player = new Player();
