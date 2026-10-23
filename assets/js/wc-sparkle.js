/* ═══════════════════════════════════════════════════════════════
   //WEBbuilders.lk — Lightweight green mouse sparkle / glow layer
   - Canvas overlay, pointer-events:none, capped particle count.
   - rAF only runs while particles are alive (idle = zero CPU).
   - Disabled for touch/coarse pointers and prefers-reduced-motion.
   - Never reacts while typing, dragging, scrolling or inside editors.
   ═══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var MAX_PARTICLES = 42;      // hard cap — memory + CPU safe
  var MIN_MOVE_DIST = 7;       // px between emitted sparkles
  var MIN_MOVE_MS = 22;        // throttle
  var LAYER_ID = 'wc-sparkle-layer';

  var reduce = false, coarse = false;
  try {
    reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
  } catch (e) {}

  // Touch devices and reduced-motion users get the calm experience.
  if (reduce || coarse || !document.createElement('canvas').getContext) return;

  var canvas = null, ctx = null, dpr = 1, w = 0, h = 0;
  var particles = [];
  var rafId = 0, running = false;
  var lastX = -999, lastY = -999, lastT = 0;
  var colors = ['22,163,74', '34,197,94', '21,128,61', '134,239,172'];

  function ensureLayer() {
    if (canvas && canvas.isConnected) return true;
    if (!document.body) return false;
    canvas = document.getElementById(LAYER_ID);
    if (!canvas) {
      canvas = document.createElement('canvas');
      canvas.id = LAYER_ID;
      canvas.setAttribute('aria-hidden', 'true');
      document.body.appendChild(canvas);
    }
    ctx = canvas.getContext('2d');
    if (!ctx) return false;
    resize();
    return true;
  }

  function resize() {
    if (!canvas || !ctx) return;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    w = window.innerWidth;
    h = window.innerHeight;
    canvas.width = Math.round(w * dpr);
    canvas.height = Math.round(h * dpr);
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function emit(x, y, burst) {
    if (particles.length >= MAX_PARTICLES) {
      particles.splice(0, Math.max(1, burst ? 6 : 1));
    }
    var count = burst ? 7 : 1;
    for (var i = 0; i < count && particles.length < MAX_PARTICLES; i++) {
      var angle = Math.random() * Math.PI * 2;
      var speed = burst ? (0.7 + Math.random() * 1.7) : (0.15 + Math.random() * 0.55);
      particles.push({
        x: x + (Math.random() - 0.5) * (burst ? 14 : 4),
        y: y + (Math.random() - 0.5) * (burst ? 14 : 4),
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed - (burst ? 0.5 : 0.12),
        life: 1,
        decay: burst ? 0.016 + Math.random() * 0.012 : 0.02 + Math.random() * 0.018,
        size: (burst ? 2.2 : 1.4) + Math.random() * (burst ? 2.6 : 1.8),
        color: colors[(Math.random() * colors.length) | 0]
      });
    }
    start();
  }

  function start() {
    if (running) return;
    if (!ensureLayer()) return;
    running = true;
    rafId = requestAnimationFrame(frame);
  }

  function frame() {
    if (!ctx) { running = false; return; }
    ctx.clearRect(0, 0, w, h);
    for (var i = particles.length - 1; i >= 0; i--) {
      var p = particles[i];
      p.life -= p.decay;
      if (p.life <= 0) { particles.splice(i, 1); continue; }
      p.x += p.vx;
      p.y += p.vy;
      p.vx *= 0.975;
      p.vy = p.vy * 0.975 + 0.006;
      var a = Math.max(0, p.life);
      var r = p.size * (0.6 + a * 0.8);
      // soft glow
      var g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, r * 3.4);
      g.addColorStop(0, 'rgba(' + p.color + ',' + (0.34 * a).toFixed(3) + ')');
      g.addColorStop(0.45, 'rgba(' + p.color + ',' + (0.12 * a).toFixed(3) + ')');
      g.addColorStop(1, 'rgba(' + p.color + ',0)');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(p.x, p.y, r * 3.4, 0, Math.PI * 2);
      ctx.fill();
      // bright core
      ctx.fillStyle = 'rgba(' + p.color + ',' + (0.85 * a).toFixed(3) + ')';
      ctx.beginPath();
      ctx.arc(p.x, p.y, Math.max(0.6, r * 0.5), 0, Math.PI * 2);
      ctx.fill();
    }
    if (particles.length) {
      rafId = requestAnimationFrame(frame);
    } else {
      running = false;
      ctx.clearRect(0, 0, w, h);
    }
  }

  function isTypingTarget(t) {
    if (!t || t.nodeType !== 1) return false;
    var tag = (t.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || tag === 'option') return true;
    if (t.isContentEditable) return true;
    return !!(t.closest && t.closest('[contenteditable="true"], .gjs-editor, .gjs-cv-canvas, .studio-canvas, .code-editor, .CodeMirror, .cm-editor'));
  }

  function onMove(e) {
    if (!e || e.buttons) return;             // never while dragging/selecting
    if (document.hidden) return;
    if (isTypingTarget(e.target)) return;
    var now = Date.now();
    var dx = e.clientX - lastX, dy = e.clientY - lastY;
    if (now - lastT < MIN_MOVE_MS) return;
    if ((dx * dx + dy * dy) < (MIN_MOVE_DIST * MIN_MOVE_DIST)) return;
    lastX = e.clientX; lastY = e.clientY; lastT = now;
    emit(e.clientX, e.clientY, false);
  }

  function onClick(e) {
    if (!e || e.button !== 0) return;
    if (isTypingTarget(e.target)) return;
    emit(e.clientX, e.clientY, true);
  }

  function onVisibility() {
    if (document.hidden) {
      particles.length = 0;
      if (ctx) ctx.clearRect(0, 0, w, h);
    }
  }

  function bind() {
    if (!ensureLayer()) return;
    window.addEventListener('pointermove', onMove, { passive: true });
    window.addEventListener('pointerdown', onClick, { passive: true });
    window.addEventListener('resize', resize, { passive: true });
    document.addEventListener('visibilitychange', onVisibility);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind, { once: true });
  } else {
    bind();
  }
})();
