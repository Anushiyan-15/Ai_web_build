/* ═══════════════════════════════════════════════════════════════
   //WEBbuilders.lk — Fixed Green Appearance Manager
   - The platform appearance is fixed (no Dark / Light / Custom UI).
   - Public + dashboard pages use the light green system.
   - Builder + studio keep their dark editor chrome with green accents
     via data-wc-surface="editor".
   - Legacy theme preferences (wc_theme_mode / wc_theme_custom / wc_theme)
     are cleared on every load so they can never override the brand.
   - Keeps a small back-compatible WCTheme API surface so existing
     callers do not throw.
   ═══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var LEGACY_KEYS = ['wc_theme_mode', 'wc_theme_custom', 'wc_theme', 'wc-theme-mode', 'wc-theme'];

  function clearLegacyPrefs() {
    try {
      LEGACY_KEYS.forEach(function (k) { localStorage.removeItem(k); });
    } catch (e) {}
  }

  function surface() {
    var h = document.documentElement;
    var s = h.getAttribute('data-wc-surface');
    if (!s) {
      // Editor surfaces are the only dark chrome we intentionally keep.
      s = (h.getAttribute('data-theme') === 'dark') ? 'editor' : 'light';
    }
    return (s === 'editor') ? 'editor' : 'light';
  }

  function applyFixed() {
    var h = document.documentElement;
    var s = surface();
    var mode = (s === 'editor') ? 'dark' : 'light';
    h.setAttribute('data-wc-surface', s);
    h.setAttribute('data-theme', mode);
    h.setAttribute('data-appearance', 'green');
    // Remove any custom-theme variables that old code may have injected.
    ['--wc-c-bg','--wc-c-bg2','--wc-c-surface','--wc-c-accent','--wc-c-accent2',
     '--wc-c-text','--wc-c-text2','--wc-c-border','--wc-c-radius','--wc-c-overlay'].forEach(function (p) {
      try { h.style.removeProperty(p); } catch (e) {}
    });
    clearLegacyPrefs();
    ensureStylesheetLast();
    try {
      document.dispatchEvent(new CustomEvent('wc:theme-change', { detail: { mode: mode, fixed: true, appearance: 'green' } }));
    } catch (e) {}
    return mode;
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
        var el = links[links.length - 1];
        el.parentNode.appendChild(el);
      }
      var brand = Array.prototype.slice.call(document.querySelectorAll('link[href*="wc-brand.css"]'));
      if (brand.length) {
        var bel = brand[brand.length - 1];
        bel.parentNode.appendChild(bel);
      }
      var premium = Array.prototype.slice.call(document.querySelectorAll('link[href*="wc-premium-3d.css"]'));
      if (premium.length) {
        var pel = premium[premium.length - 1];
        pel.parentNode.appendChild(pel);
      }
    } catch (e) {}
  }

  // Back-compatible no-op API. Any attempt to change the mode is ignored
  // and the fixed brand appearance is re-applied.
  window.WCTheme = {
    getMode: function () { return surface() === 'editor' ? 'dark' : 'light'; },
    getAppearance: function () { return 'green'; },
    getCustom: function () { return {}; },
    getPresets: function () { return {}; },
    getDefaults: function () { return {}; },
    getLight: function () { return {}; },
    setMode: function () { return applyFixed(); },
    applyCustom: function () { return applyFixed(); },
    previewCustom: function () { applyFixed(); },
    resetCustom: function () { return applyFixed(); },
    contrast: function () { return 21; },
    contrastWarnings: function () { return []; },
    refresh: applyFixed
  };

  // Neutralise any legacy theme controls that may still exist in old markup.
  function bindLegacyControls() {
    ['themeToggleBtn', 'mobileThemeToggleBtn'].forEach(function (id) {
      var b = document.getElementById(id);
      if (b && !b.getAttribute('data-wc-fixed')) {
        b.setAttribute('data-wc-fixed', '1');
        b.addEventListener('click', function (e) { e.preventDefault(); applyFixed(); });
      }
    });
    ['themeModeSelect', 'mobileThemeModeSelect', 'settingsThemeMode'].forEach(function (id) {
      var s = document.getElementById(id);
      if (s && !s.getAttribute('data-wc-fixed')) {
        s.setAttribute('data-wc-fixed', '1');
        s.addEventListener('change', function (e) { e.preventDefault(); applyFixed(); });
      }
    });
  }

  clearLegacyPrefs();
  try { applyFixed(); } catch (e) {}
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { applyFixed(); bindLegacyControls(); });
  } else {
    bindLegacyControls();
  }
})();
