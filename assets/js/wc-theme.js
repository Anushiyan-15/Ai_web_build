/* ═══════════════════════════════════════════════════════════════
   WebCraft AI — Centralized Theme Manager (Dark / Light / Custom)
   - Persists to localStorage (wc_theme_mode + wc_theme_custom).
   - Migrates legacy wc_theme key. No reload, no project reset.
   - Applies data-theme + custom CSS vars; emits wc:theme-change.
   - Re-appends theme stylesheet last to beat legacy inline styles.
   - Contrast guard (WCAG approx) for custom combos.
   ═══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';
  var MODE_KEY = 'wc_theme_mode';
  var CUSTOM_KEY = 'wc_theme_custom';
  var LEGACY_KEY = 'wc_theme';

  var DEFAULTS = {
    bg: '#0a0e17', bg2: '#0d1322', surface: '#131b2c',
    accent: '#818cf8', accent2: '#a78bfa',
    text: '#eef2ff', text2: '#aab8d4', border: '#22304a', radius: 12
  };
  var LIGHT_SNAPSHOT = {
    bg: '#f8fafc', bg2: '#eef2ff', surface: '#ffffff',
    accent: '#6366f1', accent2: '#8b5cf6',
    text: '#0f172a', text2: '#475569', border: '#e2e8f0', radius: 12
  };

  var PRESETS = {
    ocean:   { bg: '#062033', bg2: '#0a2f4a', surface: '#0e3a5c', accent: '#22d3ee', accent2: '#818cf8', text: '#ecfeff', text2: '#a5d8e8', border: '#155e75', radius: 12 },
    sunset:  { bg: '#1c0f16', bg2: '#2a1420', surface: '#331a28', accent: '#fb7185', accent2: '#f59e0b', text: '#fff1f2', text2: '#e8b8c2', border: '#5b2a3a', radius: 14 },
    forest:  { bg: '#07170f', bg2: '#0b2318', surface: '#102e20', accent: '#34d399', accent2: '#22d3ee', text: '#ecfdf5', text2: '#a8cdbb', border: '#14532d', radius: 12 },
    lavender:{ bg: '#f4f2ff', bg2: '#ede9fe', surface: '#ffffff', accent: '#7c3aed', accent2: '#db2777', text: '#1e1b4b', text2: '#5b5680', border: '#ddd6fe', radius: 14 },
    midnight:{ bg: '#05070f', bg2: '#0a0f1e', surface: '#0f1628', accent: '#818cf8', accent2: '#22d3ee', text: '#eef2ff', text2: '#94a3b8', border: '#1e293b', radius: 10 }
  };

  function isHex(v) { return typeof v === 'string' && /^#[0-9a-fA-F]{6}$/.test(v.trim()); }
  function loadMode() {
    try {
      var m = localStorage.getItem(MODE_KEY);
      if (m === 'dark' || m === 'light' || m === 'custom') return m;
      var legacy = localStorage.getItem(LEGACY_KEY);
      if (legacy === 'dark' || legacy === 'light') return legacy;
    } catch (e) {}
    try {
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) return 'light';
    } catch (e) {}
    return 'dark';
  }
  function loadCustom() {
    try {
      var raw = localStorage.getItem(CUSTOM_KEY);
      if (!raw) return Object.assign({}, DEFAULTS);
      var c = JSON.parse(raw);
      var out = Object.assign({}, DEFAULTS);
      ['bg', 'bg2', 'surface', 'accent', 'accent2', 'text', 'text2', 'border'].forEach(function (k) {
        if (isHex(c[k])) out[k] = c[k].toLowerCase();
      });
      var r = parseInt(c.radius, 10);
      if (!isNaN(r)) out.radius = Math.max(0, Math.min(24, r));
      return out;
    } catch (e) { return Object.assign({}, DEFAULTS); }
  }
  function luminance(hex) {
    var c = hex.replace('#', '');
    var r = parseInt(c.substr(0, 2), 16) / 255, g = parseInt(c.substr(2, 2), 16) / 255, b = parseInt(c.substr(4, 2), 16) / 255;
    var f = function (x) { return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  }
  function contrast(a, b) {
    var l1 = luminance(a), l2 = luminance(b);
    var hi = Math.max(l1, l2), lo = Math.min(l1, l2);
    return (hi + 0.05) / (lo + 0.05);
  }

  function applyCustomVars(c) {
    var r = document.documentElement;
    r.style.setProperty('--wc-c-bg', c.bg);
    r.style.setProperty('--wc-c-bg2', c.bg2);
    r.style.setProperty('--wc-c-surface', c.surface);
    r.style.setProperty('--wc-c-accent', c.accent);
    r.style.setProperty('--wc-c-accent2', c.accent2);
    r.style.setProperty('--wc-c-text', c.text);
    r.style.setProperty('--wc-c-text2', c.text2);
    r.style.setProperty('--wc-c-border', c.border);
    r.style.setProperty('--wc-c-radius', (c.radius || 12) + 'px');
  }

  function ensureStylesheetLast() {
    try {
      var links = Array.prototype.slice.call(document.querySelectorAll('link[href*="wc-theme.css"]'));
      if (!links.length) {
        var base = (typeof window.SITE_URL === 'string' && window.SITE_URL) ? window.SITE_URL : '';
        var l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = base + '/assets/css/wc-theme.css';
        l.setAttribute('data-wc-theme', '1');
        document.head.appendChild(l);
      } else {
        // Re-append to guarantee it wins over legacy inline <style> blocks.
        var el = links[links.length - 1];
        el.parentNode.appendChild(el);
      }
      // Presentation-only: keep premium 3D layer after theme so depth/
      // glow rules win the cascade. No behaviour or state change.
      var premium = Array.prototype.slice.call(document.querySelectorAll('link[href*="wc-premium-3d.css"]'));
      if (premium.length) {
        var pel = premium[premium.length - 1];
        pel.parentNode.appendChild(pel);
      }
    } catch (e) {}
  }

  function applyMode(mode, custom) {
    mode = (mode === 'light' || mode === 'custom') ? mode : 'dark';
    custom = custom || loadCustom();
    var html = document.documentElement;
    // Use data-theme as the single switch (nav.php light/dark logic compatible).
    html.setAttribute('data-theme', mode === 'custom' ? 'custom' : mode);
    html.setAttribute('data-appearance', mode);
    if (mode === 'custom') applyCustomVars(custom);
    try {
      localStorage.setItem(MODE_KEY, mode);
      localStorage.setItem(LEGACY_KEY, mode === 'custom' ? 'dark' : mode);
    } catch (e) {}
    ensureStylesheetLast();
    syncNavUI(mode);
    try {
      document.dispatchEvent(new CustomEvent('wc:theme-change', { detail: { mode: mode, custom: custom } }));
    } catch (e) {}
    return mode;
  }

  function syncNavUI(mode) {
    try {
      ['themeToggleIcon', 'mobileThemeToggleIcon'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.textContent = (mode === 'light') ? '🌙' : (mode === 'custom' ? '🎨' : '☀️');
      });
      ['themeModeSelect', 'mobileThemeModeSelect', 'settingsThemeMode'].forEach(function (id) {
        var s = document.getElementById(id);
        if (s && s.value !== mode) s.value = mode;
      });
      document.querySelectorAll('.mode-opt').forEach(function (o) {
        o.classList.toggle('sel', o.getAttribute('data-mode') === mode);
      });
    } catch (e) {}
  }

  // Public API — no reload, never touches project state.
  window.WCTheme = {
    getMode: loadMode,
    getCustom: loadCustom,
    getPresets: function () { return JSON.parse(JSON.stringify(PRESETS)); },
    getDefaults: function () { return Object.assign({}, DEFAULTS); },
    getLight: function () { return Object.assign({}, LIGHT_SNAPSHOT); },
    setMode: function (m) { return applyMode(m, loadCustom()); },
    applyCustom: function (c) {
      var clean = Object.assign({}, DEFAULTS);
      Object.keys(clean).forEach(function (k) {
        if (k === 'radius') {
          var r = parseInt(c && c[k], 10);
          if (!isNaN(r)) clean[k] = Math.max(0, Math.min(24, r));
        } else if (isHex(c && c[k])) clean[k] = c[k].toLowerCase();
      });
      try { localStorage.setItem(CUSTOM_KEY, JSON.stringify(clean)); } catch (e) {}
      return applyMode('custom', clean);
    },
    previewCustom: function (c) { // live preview without persisting
      try {
        var merged = Object.assign(loadCustom(), c || {});
        applyCustomVars(merged);
        document.documentElement.setAttribute('data-theme', 'custom');
        document.documentElement.setAttribute('data-appearance', 'custom');
      } catch (e) {}
    },
    resetCustom: function () {
      try { localStorage.removeItem(CUSTOM_KEY); } catch (e) {}
      return applyMode('custom', Object.assign({}, DEFAULTS));
    },
    contrast: contrast,
    contrastWarnings: function (c) {
      var w = [];
      try {
        if (contrast(c.text, c.bg) < 4.5) w.push('Main text on background contrast is low (' + contrast(c.text, c.bg).toFixed(1) + ':1 — aim for 4.5:1).');
        if (contrast(c.text2, c.bg) < 3) w.push('Secondary text on background is hard to read (' + contrast(c.text2, c.bg).toFixed(1) + ':1).');
        if (contrast(c.bg, c.surface) < 1.15) w.push('Background and surface are nearly identical — cards may blend in.');
      } catch (e) {}
      return w;
    }
  };

  // Toggle buttons (backward compat with nav.php simple toggle).
  function toggleQuick() {
    var cur = document.documentElement.getAttribute('data-theme');
    if (cur === 'light') applyMode('dark');
    else if (cur === 'custom') applyMode('light');
    else applyMode('light');
  }

  function bind() {
    ensureStylesheetLast();
    ['themeToggleBtn', 'mobileThemeToggleBtn'].forEach(function (id) {
      var b = document.getElementById(id);
      if (b && !b.getAttribute('data-wc-bound')) {
        b.setAttribute('data-wc-bound', '1');
        // If a select exists рядом, button = quick toggle; else legacy toggle.
        b.addEventListener('click', toggleQuick);
      }
    });
    ['themeModeSelect', 'mobileThemeModeSelect'].forEach(function (id) {
      var s = document.getElementById(id);
      if (s && !s.getAttribute('data-wc-bound')) {
        s.setAttribute('data-wc-bound', '1');
        s.addEventListener('change', function () { applyMode(s.value, loadCustom()); });
      }
    });
    // Keep dynamically injected dialogs/toasts themed (vars already cover;
    // observer re-syncs nav UI if navbar re-rendered).
    try {
      var obs = new MutationObserver(function () { /* vars cascade automatically */ });
      obs.observe(document.documentElement, { childList: true, subtree: true });
    } catch (e) {}
  }

  // Apply immediately (script loaded with defer=false at end of nav head).
  try { applyMode(loadMode(), loadCustom()); } catch (e) {}
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
