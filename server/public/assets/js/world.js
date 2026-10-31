/* One background engine for the whole app (Codex backgrounds.js). It moves only while sound is playing:
   setFrozen() eases motion out over ~2 s on pause and back in on play; scene changes always cross-fade. */
let engine = null;
let scene = 'stress-relief';

export const world = {
  mount(el) {
    if (engine || !window.StillroomBackgrounds) return;
    engine = window.StillroomBackgrounds.mount(el, { preset: scene, maxDpr: 1.5, transitionDuration: 1800, frozen: true });
  },
  setScene(id) {
    if (!id || id === scene) return;
    scene = id;
    if (engine) { try { engine.setPreset(id); } catch (e) { /* unknown scene id: keep the current one */ } }
  },
  setPlaying(on) { if (engine) engine.setFrozen(!on); },
  get scene() { return scene; },
  description(id) {
    const p = window.StillroomBackgrounds && window.StillroomBackgrounds.presets.find(x => x.id === id);
    return p ? p.description : '';
  },
};
