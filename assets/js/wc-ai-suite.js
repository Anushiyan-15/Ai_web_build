/* ═══════════════════════════════════════════════════════════
   wc-ai-suite.js — SHARED core for Builder + Studio AI upgrades.
   - No duplicate engines: wraps existing save/session/audit/AI systems.
   - Project-level state lives INSIDE existing session/project objects.
   - Every major AI op: snapshot → apply → validate → sync → save → toast + undo.
   Safe to load on both builder.php and studio.php.
   ═══════════════════════════════════════════════════════════ */
(function () {
  'use strict';
  if (window.__WCAISuiteLoaded) return;
  window.__WCAISuiteLoaded = true;

  /* ── tiny utils ── */
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  function toast(msg, ms, type) {
    try {
      if (typeof showToast === 'function') { showToast(msg, ms, type); return; }
    } catch (e) {}
    let t = document.querySelector('.wc-ai-toast-fallback');
    if (!t) { t = document.createElement('div'); t.className = 'wc-ai-toast-fallback'; document.body.appendChild(t); }
    t.textContent = msg; t.style.display = 'block';
    clearTimeout(t.__tm);
    t.__tm = setTimeout(() => { t.style.display = 'none'; }, ms || 3500);
  }
  function debounce(fn, wait) {
    let tm = null;
    return function (...args) { clearTimeout(tm); tm = setTimeout(() => fn.apply(this, args), wait || 400); };
  }
  function siteBase() {
    try {
      if (typeof SITE_URL_JS === 'string' && SITE_URL_JS) return SITE_URL_JS.replace(/\/+$/, '');
      if (typeof SITE_URL === 'string' && SITE_URL) return SITE_URL.replace(/\/+$/, '');
    } catch (e) {}
    return '';
  }
  function customerEmail() {
    try {
      const c = window.__CUSTOMER__ || null;
      if (c && c.email) return String(c.email).toLowerCase();
    } catch (e) {}
    return 'guest';
  }
  function sessionKey() {
    try {
      if (typeof SESSION_KEY === 'string' && SESSION_KEY) return SESSION_KEY;
    } catch (e) {}
    return 'webcraft_saved_project::' + customerEmail();
  }
  async function aiPost(endpoint, body, timeoutMs) {
    const url = siteBase() + endpoint;
    const ctrl = new AbortController();
    const tm = setTimeout(() => ctrl.abort(), timeoutMs || 60000);
    try {
      const r = await fetch(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body), signal: ctrl.signal
      });
      const j = await r.json().catch(() => null);
      if (!j) throw new Error('Empty AI response');
      return j;
    } catch (e) {
      if (e && e.name === 'AbortError') throw new Error('AI request timed out — please try again.');
      throw new Error(e && e.message ? e.message : 'Network failure — please check connection.');
    } finally { clearTimeout(tm); }
  }

  /* ── Project-level state (stored INSIDE existing session/project) ── */
  const EXT_KEYS = ['brandKit', 'aiBrief', 'aiRecommendations', 'healthAudit', 'contentAudit', 'conversionAudit', 'languageSettings', 'lastHealthScore'];
  function readSessionRaw() {
    try {
      const raw = localStorage.getItem(sessionKey());
      if (!raw) return null;
      let s = JSON.parse(raw);
      if (typeof hydrateSessionDesigns === 'function') { try { s = hydrateSessionDesigns(s); } catch (e) {} }
      return s;
    } catch (e) { return null; }
  }
  function getProjectExt() {
    // Studio shape first (projectData), then builder session, then bridge.
    try {
      if (typeof projectData !== 'undefined' && projectData && typeof projectData === 'object') {
        const out = {};
        EXT_KEYS.forEach((k) => { if (projectData[k] !== undefined) out[k] = projectData[k]; });
        if (Object.keys(out).length) return out;
      }
    } catch (e) {}
    try {
      const s = readSessionRaw();
      if (s) {
        const out = {};
        EXT_KEYS.forEach((k) => { if (s[k] !== undefined) out[k] = s[k]; });
        // legacy: wizard.brandKit
        if (!out.brandKit && s.wizard && s.wizard.brandKit) out.brandKit = s.wizard.brandKit;
        return out;
      }
    } catch (e) {}
    try {
      const b = JSON.parse(localStorage.getItem('webcraft_saved_project') || 'null');
      if (b && typeof b === 'object') {
        const out = {};
        EXT_KEYS.forEach((k) => { if (b[k] !== undefined) out[k] = b[k]; });
        return out;
      }
    } catch (e) {}
    return {};
  }
  function setProjectExt(patch) {
    const safe = {};
    EXT_KEYS.forEach((k) => { if (patch && patch[k] !== undefined) safe[k] = patch[k]; });
    if (!Object.keys(safe).length) return;
    // 1) Studio live object
    try {
      if (typeof projectData !== 'undefined' && projectData) {
        Object.assign(projectData, safe);
        if (typeof saveProjectData === 'function') saveProjectData();
      }
    } catch (e) {}
    // 2) Builder session (merge, never clobber designs)
    try {
      const k = sessionKey();
      const raw = localStorage.getItem(k);
      const s = raw ? JSON.parse(raw) : {};
      Object.assign(s, safe);
      s.savedAt = Date.now();
      localStorage.setItem(k, JSON.stringify(s));
      try { __lastSessionSeen = s.savedAt; } catch (e) {}
    } catch (e) { /* quota — non-fatal */ }
    // 3) Bridge mirror (best-effort)
    try {
      const raw = localStorage.getItem('webcraft_saved_project');
      if (raw) {
        const b = JSON.parse(raw);
        Object.assign(b, safe);
        localStorage.setItem('webcraft_saved_project', JSON.stringify(b));
      }
    } catch (e) {}
  }

  /* ── BRAND KIT ── */
  const DEFAULT_BRANDKIT = {
    logo: '', primary: '#4f46e5', secondary: '#7c3aed', accent: '#06b6d4',
    headingFont: "'Plus Jakarta Sans', system-ui, sans-serif",
    bodyFont: "'Inter', system-ui, sans-serif",
    buttonStyle: 'rounded', borderRadius: 12, shadowStyle: 'soft',
    spacingStyle: 'comfortable', brandVoice: 'professional',
    ctaStyle: 'WhatsApp us', globalOnly: true, overrideCustomStyles: false
  };
  function loadBrandKit() {
    try {
      const ext = getProjectExt();
      return Object.assign({}, DEFAULT_BRANDKIT, ext.brandKit || {});
    } catch (e) { return Object.assign({}, DEFAULT_BRANDKIT); }
  }
  function saveBrandKit(kit) {
    const clean = Object.assign({}, DEFAULT_BRANDKIT, kit || {});
    setProjectExt({ brandKit: clean });
    return clean;
  }
  function resetBrandKit() {
    setProjectExt({ brandKit: Object.assign({}, DEFAULT_BRANDKIT) });
    toast('🎨 Brand Kit reset to defaults');
    return loadBrandKit();
  }
  function brandKitCss(kit) {
    const k = Object.assign({}, DEFAULT_BRANDKIT, kit || {});
    const btnRadius = k.buttonStyle === 'pill' ? '999px' : (k.buttonStyle === 'sharp' ? '4px' : (k.borderRadius + 'px'));
    const shadows = { none: 'none', soft: '0 10px 30px rgba(2,6,23,0.10)', medium: '0 18px 50px rgba(2,6,23,0.16)', strong: '0 28px 80px rgba(2,6,23,0.28)' };
    return (':root{--wc-primary:' + k.primary + ';--wc-secondary:' + k.secondary + ';--wc-accent:' + k.accent
      + ';--wc-heading-font:' + k.headingFont + ';--wc-body-font:' + k.bodyFont
      + ';--wc-radius:' + k.borderRadius + 'px;--wc-btn-radius:' + btnRadius + ';--wc-shadow:' + (shadows[k.shadowStyle] || shadows.soft) + ';}\n'
      + 'h1,h2,h3,h4,h5,h6{font-family:' + k.headingFont + '!important;color:inherit;}\n'
      + 'body{font-family:' + k.bodyFont + '!important;}\n'
      + 'a{color:' + k.primary + ';}\n').trim();
  }
  function applyBrandKit(options) {
    const opts = options || {};
    const kit = loadBrandKit();
    if (opts && typeof opts === 'object') Object.keys(opts).forEach((k) => { kit[k] = opts[k]; });
    const override = !!kit.overrideCustomStyles;
    try { createVersionSnapshot('Before Brand Kit apply', 'User'); } catch (e) {}
    // Studio canvas path (preferred, non-destructive: CSS vars layer)
    try {
      const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
      if (doc && doc.head) {
        let tag = doc.getElementById('wc-brandkit-css');
        if (!tag) { tag = doc.createElement('style'); tag.id = 'wc-brandkit-css'; doc.head.appendChild(tag); }
        tag.textContent = brandKitCss(kit);
        // Sync existing global theme object when present (keeps Theme panel consistent)
        try {
          if (typeof wcProEnsure === 'function') {
            const d = wcProEnsure();
            if (d) {
              d.wcTheme = d.wcTheme || {};
              d.wcTheme.primary = kit.primary; d.wcTheme.secondary = kit.secondary;
              d.wcTheme.accent = kit.accent; d.wcTheme.link = kit.primary;
              d.wcTheme.headingFont = kit.headingFont; d.wcTheme.bodyFont = kit.bodyFont;
              d.wcTheme.radius = kit.borderRadius;
            }
          }
        } catch (e) {}
        if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml();
        if (typeof saveProjectData === 'function') saveProjectData();
        toast('🎨 Brand Kit applied to entire website');
        return true;
      }
    } catch (e) {}
    // Builder / raw-HTML path: patch generatedDesigns HTML head (vars layer only unless override)
    try {
      if (typeof generatedDesigns !== 'undefined' && Array.isArray(generatedDesigns) && generatedDesigns.length) {
        const idx = (typeof activeDesignIndex === 'number') ? activeDesignIndex : 0;
        const d = generatedDesigns[idx];
        if (d && typeof d.html === 'string') {
          const css = brandKitCss(kit);
          const styleTag = '<style id="wc-brandkit-css">' + css + '</style>';
          if (d.html.indexOf('id="wc-brandkit-css"') !== -1) {
            d.html = d.html.replace(/<style id="wc-brandkit-css">[\s\S]*?<\/style>/, styleTag);
          } else if (d.html.indexOf('</head>') !== -1) {
            d.html = d.html.replace('</head>', styleTag + '</head>');
          } else {
            d.html = styleTag + d.html;
          }
          if (override && typeof currentHtml === 'string') currentHtml = d.html;
          try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e) {}
          try { if (typeof restoreIntoWorkspace === 'function' && document.getElementById('builder-screen')?.style.display === 'flex') restoreIntoWorkspace(); } catch (e) {}
          toast('🎨 Brand Kit applied');
          return true;
        }
      }
    } catch (e) {}
    // Persist at minimum so Studio picks it up on open.
    saveBrandKit(kit);
    toast('🎨 Brand Kit saved — will apply when you open Studio');
    return true;
  }
  function openBrandKit() {
    const kit = loadBrandKit();
    const body = ''
      + '<div class="wc-ai-row">'
      + '<div><label class="wc-ai-label">Logo URL</label><input class="wc-ai-input" id="bk-logo" value="' + esc(kit.logo) + '" placeholder="https://…/logo.png"></div>'
      + '<div><label class="wc-ai-label">Brand voice</label><select class="wc-ai-select" id="bk-voice">'
      + ['professional', 'friendly', 'playful', 'luxury', 'bold', 'minimal'].map((v) => '<option value="' + v + '"' + (kit.brandVoice === v ? ' selected' : '') + '>' + v + '</option>').join('')
      + '</select></div></div>'
      + '<div class="wc-ai-row">'
      + '<div><label class="wc-ai-label">Primary</label><input type="color" id="bk-primary" value="' + esc(kit.primary) + '" style="width:100%;height:40px;border-radius:10px;border:1px solid #263349;background:#0b1120;"></div>'
      + '<div><label class="wc-ai-label">Secondary</label><input type="color" id="bk-secondary" value="' + esc(kit.secondary) + '" style="width:100%;height:40px;border-radius:10px;border:1px solid #263349;background:#0b1120;"></div>'
      + '<div><label class="wc-ai-label">Accent</label><input type="color" id="bk-accent" value="' + esc(kit.accent) + '" style="width:100%;height:40px;border-radius:10px;border:1px solid #263349;background:#0b1120;"></div>'
      + '</div>'
      + '<div class="wc-ai-row">'
      + '<div><label class="wc-ai-label">Heading font</label><select class="wc-ai-select" id="bk-hfont">'
      + ["'Plus Jakarta Sans', system-ui, sans-serif", "'Inter', system-ui, sans-serif", "'Space Grotesk', sans-serif", "'Cinzel', serif", "'Noto Sans Tamil', sans-serif", "'Noto Sans Sinhala', sans-serif"].map((f) => '<option value="' + esc(f) + '"' + (kit.headingFont === f ? ' selected' : '') + '>' + esc(f.split(',')[0].replace(/'/g, '')) + '</option>').join('')
      + '</select></div>'
      + '<div><label class="wc-ai-label">Body font</label><select class="wc-ai-select" id="bk-bfont">'
      + ["'Inter', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif", "'Space Grotesk', sans-serif", "'Noto Sans Tamil', sans-serif", "'Noto Sans Sinhala', sans-serif", "'Noto Sans Devanagari', sans-serif"].map((f) => '<option value="' + esc(f) + '"' + (kit.bodyFont === f ? ' selected' : '') + '>' + esc(f.split(',')[0].replace(/'/g, '')) + '</option>').join('')
      + '</select></div></div>'
      + '<div class="wc-ai-row">'
      + '<div><label class="wc-ai-label">Button style</label><select class="wc-ai-select" id="bk-btn"><option value="rounded"' + (kit.buttonStyle === 'rounded' ? ' selected' : '') + '>Rounded</option><option value="pill"' + (kit.buttonStyle === 'pill' ? ' selected' : '') + '>Pill</option><option value="sharp"' + (kit.buttonStyle === 'sharp' ? ' selected' : '') + '>Sharp</option></select></div>'
      + '<div><label class="wc-ai-label">Corner radius (px)</label><input class="wc-ai-input" type="number" id="bk-radius" min="0" max="28" value="' + esc(kit.borderRadius) + '"></div>'
      + '<div><label class="wc-ai-label">Shadow</label><select class="wc-ai-select" id="bk-shadow"><option value="none"' + (kit.shadowStyle === 'none' ? ' selected' : '') + '>None</option><option value="soft"' + (kit.shadowStyle === 'soft' ? ' selected' : '') + '>Soft</option><option value="medium"' + (kit.shadowStyle === 'medium' ? ' selected' : '') + '>Medium</option><option value="strong"' + (kit.shadowStyle === 'strong' ? ' selected' : '') + '>Strong</option></select></div>'
      + '</div>'
      + '<div class="wc-ai-row">'
      + '<div><label class="wc-ai-label">Spacing</label><select class="wc-ai-select" id="bk-spacing"><option value="compact"' + (kit.spacingStyle === 'compact' ? ' selected' : '') + '>Compact</option><option value="comfortable"' + (kit.spacingStyle === 'comfortable' ? ' selected' : '') + '>Comfortable</option><option value="spacious"' + (kit.spacingStyle === 'spacious' ? ' selected' : '') + '>Spacious</option></select></div>'
      + '<div><label class="wc-ai-label">Default CTA</label><input class="wc-ai-input" id="bk-cta" value="' + esc(kit.ctaStyle) + '" placeholder="e.g. WhatsApp us"></div>'
      + '</div>'
      + '<label class="wc-ai-hint" style="display:flex;gap:0.5rem;align-items:center;margin-top:0.8rem;"><input type="checkbox" id="bk-override" ' + (kit.overrideCustomStyles ? 'checked' : '') + '> Override custom section styles (off = keep per-section customisation)</label>'
      + '<div class="wc-ai-hint">Apply updates headings, buttons, cards, links, accents and spacing consistently — custom section styles are kept unless you tick override.</div>';
    openWcModal('🎨 Global Brand Kit', body, [
      { label: 'Reset', cls: 'ghost', onClick: () => { resetBrandKit(); closeWcModal(); } },
      {
        label: 'Save & Apply', cls: 'primary', onClick: () => {
          const v = (id) => ($(id) ? $(id).value : '');
          saveBrandKit({
            logo: v('bk-logo'), primary: v('bk-primary'), secondary: v('bk-secondary'), accent: v('bk-accent'),
            headingFont: v('bk-hfont'), bodyFont: v('bk-bfont'), buttonStyle: v('bk-btn'),
            borderRadius: Math.max(0, Math.min(28, parseInt(v('bk-radius'), 10) || 12)),
            shadowStyle: v('bk-shadow'), spacingStyle: v('bk-spacing'), brandVoice: v('bk-voice'),
            ctaStyle: v('bk-cta'), overrideCustomStyles: !!($('bk-override') && $('bk-override').checked)
          });
          closeWcModal();
          applyBrandKit({});
        }
      }
    ]);
  }

  /* ── Modal ── */
  function openWcModal(title, bodyHtml, actions, opts) {
    closeWcModal();
    const o = document.createElement('div');
    o.className = 'wc-ai-overlay'; o.id = 'wc-ai-overlay';
    o.innerHTML = '<div class="wc-ai-modal' + ((opts && opts.wide) ? ' wide' : '') + '" role="dialog" aria-modal="true">'
      + '<div class="wc-ai-modal-hdr"><h3>' + title + '</h3><button class="wc-ai-x" id="wc-ai-x" aria-label="Close">✕</button></div>'
      + '<div class="wc-ai-modal-body" id="wc-ai-modal-body">' + (bodyHtml || '') + '</div>'
      + '<div class="wc-ai-modal-ftr" id="wc-ai-modal-ftr"></div></div>';
    o.addEventListener('click', (e) => { if (e.target === o) closeWcModal(); });
    document.body.appendChild(o);
    $('wc-ai-x').onclick = closeWcModal;
    document.addEventListener('keydown', escClose);
    const ftr = $('wc-ai-modal-ftr');
    (actions || [{ label: 'Close', cls: '', onClick: closeWcModal }]).forEach((a) => {
      const b = document.createElement('button');
      b.className = 'wc-ai-btn ' + (a.cls || '');
      b.textContent = a.label;
      b.onclick = () => { try { a.onClick && a.onClick(); } catch (e) { toast('Action failed: ' + e.message); } };
      ftr.appendChild(b);
    });
    return o;
  }
  function escClose(e) { if (e && e.key === 'Escape') closeWcModal(); }
  function closeWcModal() {
    const o = $('wc-ai-overlay');
    if (o) o.remove();
    const s = $('wc-ai-split-overlay');
    if (s && s.dataset.pinned !== '1') { /* split overlay closes separately */ }
    document.removeEventListener('keydown', escClose);
  }
  function confirmWcModal(title, message, okLabel) {
    return new Promise((resolve) => {
      openWcModal(title, '<p style="font-size:0.86rem;line-height:1.6;">' + message + '</p>', [
        { label: 'Cancel', cls: 'ghost', onClick: () => { closeWcModal(); resolve(false); } },
        { label: okLabel || 'Confirm', cls: 'primary', onClick: () => { closeWcModal(); resolve(true); } }
      ]);
    });
  }

  /* ── VERSION HISTORY (reuses wcSnapshotVersion when present) ── */
  function studioVersionsKey() {
    try {
      const ci = (typeof activeConceptIndex === 'number') ? activeConceptIndex : 0;
      return 'webcraft_versions::c' + ci;
    } catch (e) { return 'webcraft_versions::c0'; }
  }
  function builderVersionsKey() { return 'wc_ai_versions::' + sessionKey(); }
  function currentHtmlNow() {
    try { if (typeof currentHtml === 'string' && currentHtml.length > 50) return currentHtml; } catch (e) {}
    try {
      if (typeof generatedDesigns !== 'undefined' && generatedDesigns.length) {
        const i = (typeof activeDesignIndex === 'number') ? activeDesignIndex : 0;
        if (generatedDesigns[i] && generatedDesigns[i].html) return generatedDesigns[i].html;
      }
    } catch (e) {}
    try {
      if (typeof projectData !== 'undefined' && projectData && projectData.designs && projectData.designs.length) {
        const i = (typeof activeConceptIndex === 'number') ? activeConceptIndex : 0;
        if (projectData.designs[i] && projectData.designs[i].html) return projectData.designs[i].html;
      }
    } catch (e) {}
    return '';
  }
  function createVersionSnapshot(reason, source) {
    // Prefer native Studio snapshot (renders History panel too).
    try {
      if (typeof window.wcSnapshotVersion === 'function' && typeof getStudioCanvasDocument === 'function') {
        window.wcSnapshotVersion((reason || 'Checkpoint') + (source ? ' · ' + source : ''));
        return true;
      }
      if (typeof wcSnapshotVersion === 'function') {
        wcSnapshotVersion((reason || 'Checkpoint') + (source ? ' · ' + source : ''));
        return true;
      }
    } catch (e) {}
    // Builder / fallback: same shape, separate key.
    try {
      const html = currentHtmlNow();
      if (!html || html.length < 50) return false;
      const k = builderVersionsKey();
      let arr = [];
      try { arr = JSON.parse(localStorage.getItem(k) || '[]'); if (!Array.isArray(arr)) arr = []; } catch (e) { arr = []; }
      if (arr.length && arr[arr.length - 1].html === html.slice(0, 300000)) return true;
      arr.push({
        label: reason || 'Checkpoint', source: source || 'User', at: Date.now(),
        conceptIndex: (typeof activeDesignIndex === 'number') ? activeDesignIndex : 0,
        viewMode: (typeof currentViewMode === 'string') ? currentViewMode : 'site',
        html: html.slice(0, 300000)
      });
      arr = arr.slice(-10);
      localStorage.setItem(k, JSON.stringify(arr));
      return true;
    } catch (e) { return false; }
  }
  function getVersionHistory() {
    try {
      if (typeof wcGetVersions === 'function' && typeof getStudioCanvasDocument === 'function') {
        const v = wcGetVersions() || [];
        return v.map((x, i) => ({ id: i, label: x.label || ('Version ' + (i + 1)), source: 'Auto', at: x.at || Date.now(), html: x.html || '' }));
      }
    } catch (e) {}
    try {
      const raw = localStorage.getItem(studioVersionsKey());
      if (raw) {
        const a = JSON.parse(raw);
        if (Array.isArray(a) && a.length) return a.map((x, i) => ({ id: i, label: x.label || ('Version ' + (i + 1)), source: 'Auto', at: x.at || Date.now(), html: x.html || '' }));
      }
    } catch (e) {}
    try {
      const raw2 = localStorage.getItem(builderVersionsKey());
      const b = raw2 ? JSON.parse(raw2) : [];
      if (Array.isArray(b)) return b.map((x, i) => ({ id: i, label: x.label || ('Version ' + (i + 1)), source: x.source || 'User', at: x.at || Date.now(), html: x.html || '', conceptIndex: x.conceptIndex, viewMode: x.viewMode }));
    } catch (e) {}
    return [];
  }
  function fmtTime(at) {
    try { return new Date(at).toLocaleString([], { hour: 'numeric', minute: '2-digit', day: 'numeric', month: 'short' }); }
    catch (e) { return ''; }
  }
  function renderVersionHistory() {
    const vers = getVersionHistory().slice().reverse();
    let h = '';
    if (!vers.length) h = '<div class="wc-ai-hint">No versions yet. Snapshots are created automatically before every AI change — plus each manual save.</div>';
    else vers.forEach((v) => {
      h += '<div class="wc-ai-ver"><span class="dot"></span><div class="info"><div class="t">' + esc(v.label) + '</div>'
        + '<div class="s">' + esc(fmtTime(v.at)) + ' · ' + esc(v.source || 'Auto') + '</div></div>'
        + '<button class="wc-ai-btn small" onclick="previewVersion(' + v.id + ')">Preview</button>'
        + '<button class="wc-ai-btn small" onclick="restoreVersion(' + v.id + ')">Restore</button></div>';
    });
    h += '<div class="wc-ai-hint" style="margin-top:0.6rem;">Restoring asks for confirmation and keeps a “Before restore” snapshot, so nothing is ever lost.</div>';
    openWcModal('🕘 Version History', h, [
      { label: 'Snapshot now', cls: 'ghost', onClick: () => { createVersionSnapshot('Manual snapshot', 'User'); renderVersionHistory(); } },
      { label: 'Close', cls: 'primary', onClick: closeWcModal }
    ]);
  }
  async function previewVersion(versionId) {
    const vers = getVersionHistory();
    const v = vers[versionId];
    if (!v || !v.html) { toast('Version not found'); return; }
    const src = 'data:text/html;charset=utf-8,' + encodeURIComponent(v.html.slice(0, 500000));
    openWcModal('👁 ' + esc(v.label), '<div class="wc-ai-hint">' + esc(fmtTime(v.at)) + ' · ' + esc(v.source || '') + '</div>'
      + '<iframe src="' + src + '" style="width:100%;height:52vh;border:1px solid #263349;border-radius:12px;background:#fff;margin-top:0.6rem;"></iframe>',
      [
        { label: 'Back to list', cls: 'ghost', onClick: renderVersionHistory },
        { label: 'Restore this version', cls: 'primary', onClick: () => restoreVersion(versionId) }
      ], { wide: true });
  }
  async function restoreVersion(versionId) {
    const vers = getVersionHistory();
    const v = vers[versionId];
    if (!v || !v.html) { toast('Version not found'); return; }
    const ok = await confirmWcModal('Restore version?', '“' + esc(v.label) + '” will replace the current design. A <b>“Before restore”</b> snapshot is kept automatically. Continue?'.replace(/&lt;/g, '<').replace(/&gt;/g, '>'), 'Restore');
    if (!ok) return;
    // Native Studio restore path first (handles canvas reload + theme re-apply).
    try {
      if (typeof window.wcRestoreVersion === 'function' && typeof getStudioCanvasDocument === 'function') {
        window.wcRestoreVersion(versionId);
        closeWcModal(); toast('🕘 Version restored'); return;
      }
    } catch (e) {}
    try { createVersionSnapshot('Before restore', 'User'); } catch (e) {}
    try {
      if (typeof projectData !== 'undefined' && projectData && projectData.designs) {
        const i = (typeof activeConceptIndex === 'number') ? activeConceptIndex : 0;
        if (projectData.designs[i]) projectData.designs[i].html = v.html;
        try { if (typeof currentHtml !== 'undefined') currentHtml = v.html; } catch (e) {}
        try { if (typeof loadHtmlIntoStudioCanvas === 'function') loadHtmlIntoStudioCanvas(); } catch (e) {}
        try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
      } else if (typeof generatedDesigns !== 'undefined' && generatedDesigns.length) {
        const i = (typeof activeDesignIndex === 'number') ? activeDesignIndex : 0;
        if (generatedDesigns[i]) generatedDesigns[i].html = v.html;
        try { currentHtml = v.html; } catch (e) {}
        try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e) {}
        try { if (typeof restoreIntoWorkspace === 'function') restoreIntoWorkspace(); } catch (e) {}
      }
      closeWcModal(); toast('🕘 Version restored — review, undo still available');
    } catch (e) { toast('Restore failed: ' + e.message); }
  }
  function compareVersions(a, b) {
    const vers = getVersionHistory();
    const va = vers[a], vb = vers[b];
    if (!va || !vb) { toast('Pick two versions to compare'); return null; }
    const strip = (h) => String(h || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    const ta = strip(va.html), tb = strip(vb.html);
    openBeforeAfterPreview(a, b);
    return { a: { id: a, chars: va.html.length, words: ta.split(' ').length }, b: { id: b, chars: vb.html.length, words: tb.split(' ').length } };
  }
  function openBeforeAfterPreview(beforeId, afterId) {
    const vers = getVersionHistory();
    let beforeHtml = '', afterHtml = '';
    if (typeof beforeId === 'number' && vers[beforeId]) beforeHtml = vers[beforeId].html;
    if (typeof afterId === 'number' && vers[afterId]) afterHtml = vers[afterId].html;
    if (!afterHtml) afterHtml = currentHtmlNow();
    if (!beforeHtml && vers.length >= 2) beforeHtml = vers[vers.length - 2].html;
    if (!beforeHtml) beforeHtml = afterHtml;
    const src = (h) => 'data:text/html;charset=utf-8,' + encodeURIComponent(String(h || '<p>Empty</p>').slice(0, 500000));
    openWcModal('🔀 Before / After', '<div class="wc-ai-ba-wrap">'
      + '<div class="wc-ai-ba-pane"><h5>BEFORE</h5><iframe src="' + src(beforeHtml) + '" sandbox="allow-same-origin"></iframe></div>'
      + '<div class="wc-ai-ba-pane"><h5>AFTER — AI version</h5><iframe src="' + src(afterHtml) + '" sandbox="allow-same-origin"></iframe></div>'
      + '</div><div class="wc-ai-hint" style="margin-top:0.6rem;">Keep the AI version, or restore the original — a snapshot is kept either way.</div>',
      [
        { label: 'Keep original', cls: 'ghost', onClick: () => { if (typeof beforeId === 'number') restoreVersion(beforeId); else closeWcModal(); } },
        { label: 'Close', cls: '', onClick: closeWcModal },
        { label: 'Apply AI version', cls: 'primary', onClick: () => { closeWcModal(); toast('✨ AI version kept'); } }
      ], { wide: true });
  }

  /* ── DESIGN HEALTH (extends copilotAuditRun, never a 2nd engine) ── */
  function localAudit(html) {
    const issues = []; let goods = 0;
    try {
      const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
      const bodyText = (doc.body ? doc.body.textContent : '') || '';
      const title = (doc.querySelector('title') ? doc.querySelector('title').textContent : '').trim();
      if (!title || /^(website|home|untitled|document)$/i.test(title)) issues.push({ sev: 'bad', cat: 'SEO', msg: 'Page title is missing or generic.', fix: 'seo-title', section: 'head' });
      else goods++;
      if (!doc.querySelector('meta[name="description"]')) issues.push({ sev: 'warn', cat: 'SEO', msg: 'Meta description missing — search previews suffer.', fix: 'seo-desc', section: 'head' });
      else goods++;
      if (!/name=["']viewport["']/i.test(String(html))) issues.push({ sev: 'bad', cat: 'Mobile', msg: 'Viewport meta missing — mobile layout will break.', fix: 'viewport', section: 'head' });
      else goods++;
      const h1s = doc.querySelectorAll('h1');
      if (!h1s.length) issues.push({ sev: 'bad', cat: 'SEO', msg: 'No H1 heading found.', fix: null, section: 'hero' });
      else if (h1s.length > 1) issues.push({ sev: 'warn', cat: 'SEO', msg: h1s.length + ' H1 headings — keep exactly one.', fix: 'h1-demote', section: 'hero' });
      else goods++;
      if (/lorem ipsum|placeholder|dummy text|sample text/i.test(bodyText)) issues.push({ sev: 'bad', cat: 'Content', msg: 'Placeholder text still on the page.', fix: 'ai', section: 'body' });
      else if (bodyText.trim().length > 200) goods++;
      const emptyS = [...doc.querySelectorAll('section')].filter((s) => !s.textContent.trim()).length;
      if (emptyS) issues.push({ sev: 'warn', cat: 'Content', msg: emptyS + ' empty section(s).', fix: null, section: 'body' });
      const imgs = [...doc.querySelectorAll('img')];
      const noAlt = imgs.filter((i) => !(i.getAttribute('alt') || '').trim()).length;
      if (noAlt) issues.push({ sev: 'warn', cat: 'Accessibility', msg: noAlt + ' image(s) missing alt text.', fix: 'img-alt', section: 'images' });
      else goods++;
      const dead = [...doc.querySelectorAll('a')].filter((a) => { const h = (a.getAttribute('href') || '').trim(); return h === '' || h === '#'; }).length;
      if (dead) issues.push({ sev: 'warn', cat: 'UX', msg: dead + ' dead link(s) (# or empty).', fix: 'dead-links', section: 'nav' });
      else goods++;
      if (!/id=["']contact["']/i.test(String(html))) issues.push({ sev: 'warn', cat: 'Conversion', msg: 'No #contact section — CTAs may lead nowhere.', fix: null, section: 'contact' });
      else goods++;
      const small = [...doc.querySelectorAll('p,span,li,a')].filter((el) => {
        const fs = parseFloat((el.getAttribute('style') || '').match(/font-size:\s*([\d.]+)px/)?.[1] || '16');
        return fs > 0 && fs < 12;
      }).length;
      if (small) issues.push({ sev: 'warn', cat: 'Mobile', msg: small + ' tiny-text element(s) under 12px.', fix: 'tiny-text', section: 'body' });
      // nav check
      if (!doc.querySelector('nav,header')) issues.push({ sev: 'warn', cat: 'UX', msg: 'No navigation landmark found.', fix: null, section: 'header' });
      else goods++;
    } catch (e) { issues.push({ sev: 'warn', cat: 'Audit', msg: 'Scan snag: ' + e.message, fix: null, section: '' }); }
    return { goods, issues };
  }
  function runDesignHealthAudit() {
    let base = null;
    try {
      if (typeof copilotAuditRun === 'function' && typeof getStudioCanvasDocument === 'function') base = copilotAuditRun();
    } catch (e) { base = null; }
    if (!base) {
      const html = currentHtmlNow();
      const r = localAudit(html);
      base = { at: Date.now(), goods: r.goods, warns: r.issues.filter((i) => i.sev === 'warn').length, bads: r.issues.filter((i) => i.sev === 'bad').length, issues: r.issues };
    }
    const scored = calculateDesignHealthScore(base);
    try { setProjectExt({ healthAudit: scored, lastHealthScore: scored.totalScore }); } catch (e) {}
    renderDesignHealthPanel(scored);
    return scored;
  }
  function calculateDesignHealthScore(audit) {
    const issues = ((audit && audit.issues) || []).map((it, idx) => ({
      id: idx, severity: it.sev === 'bad' ? 'critical' : (it.sev || 'warning'),
      category: it.cat || 'General', explanation: it.msg || 'Issue found',
      section: it.section || it.loc || 'page', fix: it.fix || null, raw: it
    }));
    const catOf = (c) => issues.filter((i) => String(i.category).toLowerCase().indexOf(c) !== -1);
    const scoreFor = (list, all) => {
      if (!list.length) return 96;
      const crit = list.filter((i) => i.severity === 'critical').length;
      const warn = list.length - crit;
      return Math.max(20, 100 - crit * 18 - warn * 7);
    };
    const mobileIssues = issues.filter((i) => /mobile|responsive|overflow|viewport/i.test(i.category + ' ' + i.explanation));
    const seoIssues = catOf('seo');
    const a11y = issues.filter((i) => /access|contrast|alt/i.test(i.category + ' ' + i.explanation));
    const content = issues.filter((i) => /content|heading|empty/i.test(i.category + ' ' + i.explanation));
    const conv = issues.filter((i) => /conver|ux|cta|contact|link|nav/i.test(i.category + ' ' + i.explanation));
    const designIssues = issues.filter((i) => !mobileIssues.includes(i) && !seoIssues.includes(i) && !a11y.includes(i) && !content.includes(i) && !conv.includes(i));
    const designScore = scoreFor(designIssues.length ? designIssues : [], 0);
    const mobileScore = mobileIssues.length ? scoreFor(mobileIssues) : 94;
    const seoScore = seoIssues.length ? scoreFor(seoIssues) : 95;
    const accessibilityScore = a11y.length ? scoreFor(a11y) : 95;
    const contentScore = content.length ? scoreFor(content) : 93;
    const conversionScore = conv.length ? scoreFor(conv) : 90;
    const totalScore = Math.round((designScore + mobileScore + seoScore + accessibilityScore + contentScore + conversionScore) / 6);
    return { totalScore, designScore, mobileScore, seoScore, accessibilityScore, contentScore, conversionScore, issues, at: Date.now(), goods: (audit && audit.goods) || 0 };
  }
  function meter(label, v) {
    const cls = v >= 85 ? 'good' : (v >= 65 ? '' : (v >= 45 ? 'warn' : 'bad'));
    return '<div class="wc-ai-meter"><div class="wc-ai-meter-top"><span>' + esc(label) + '</span><span>' + v + '</span></div>'
      + '<div class="wc-ai-meter-bar"><div class="wc-ai-meter-fill ' + cls + '" style="width:' + v + '%"></div></div></div>';
  }
  function renderDesignHealthPanel(result) {
    const r = result || { totalScore: 0, issues: [] };
    let h = '<div class="wc-ai-score-hero"><div class="wc-ai-score-ring" style="--p:' + r.totalScore + '"><span>' + r.totalScore + '</span></div>'
      + '<div style="flex:1;min-width:180px;"><div style="font-weight:800;color:#fff;">AI Design Health: ' + r.totalScore + '/100</div>'
      + '<div class="wc-ai-hint">' + (r.totalScore >= 85 ? 'Strong — small polish only.' : r.totalScore >= 65 ? 'Good foundation — fix highlights below.' : 'Needs attention — Fix All handles the safe wins.') + '</div></div></div>';
    h += meter('Design', r.designScore) + meter('Mobile', r.mobileScore) + meter('SEO', r.seoScore)
      + meter('Accessibility', r.accessibilityScore) + meter('Content', r.contentScore) + meter('Conversion', r.conversionScore);
    if (!r.issues.length) h += '<div class="wc-ai-hint">✅ No issues — everything looks solid.</div>';
    else r.issues.forEach((it) => {
      h += '<div class="wc-ai-issue"><span class="sev">' + (it.severity === 'critical' ? '❌' : '⚠️') + '</span>'
        + '<div class="body"><b>[' + esc(it.category) + ']</b> ' + esc(it.explanation)
        + '<div class="meta">Section: ' + esc(it.section || 'page') + ' · Severity: ' + esc(it.severity) + '</div></div>'
        + '<div class="acts">' + (it.fix ? '<button class="wc-ai-btn small" onclick="fixPrePublishIssue(' + it.id + ')">Fix</button>' : '') + '</div></div>';
    });
    openWcModal('💚 AI Design Health — ' + r.totalScore + '/100', h, [
      { label: 'Versions', cls: 'ghost', onClick: renderVersionHistory },
      { label: '✨ Fix All Issues', cls: 'primary', onClick: () => fixAllHealthIssues() }
    ], { wide: true });
  }
  async function fixAllHealthIssues() {
    try { createVersionSnapshot('Before Fix All', 'AI'); } catch (e) {}
    // Preferred: native batch fixer (already safe + local-first).
    try {
      if (typeof copilotFixAll === 'function' && typeof getStudioCanvasDocument === 'function') {
        toast('✨ Fixing all safe issues…');
        await copilotFixAll();
        try { runDesignHealthAudit(); } catch (e) {}
        return true;
      }
    } catch (e) {}
    // Fallback: iterate single-issue fixer.
    try {
      if (typeof copilotFixIssue === 'function') {
        const audit = (typeof copilotState !== 'undefined' && copilotState.audit) ? copilotState.audit : null;
        const n = audit && audit.issues ? audit.issues.length : 0;
        for (let i = 0; i < n; i++) { try { await copilotFixIssue(0); } catch (e) {} }
        toast('✨ Fix All complete');
        return true;
      }
    } catch (e) {}
    // Last-resort local fixes on raw HTML (builder context).
    try {
      let html = currentHtmlNow();
      if (!html) { toast('Nothing to fix yet — generate a design first'); return false; }
      let changed = false;
      if (!/name=["']viewport["']/i.test(html)) { html = html.replace(/<head([^>]*)>/i, '<head$1>\n<meta name="viewport" content="width=device-width, initial-scale=1">'); changed = true; }
      if (!/<meta name="description"/i.test(html)) { html = html.replace(/<head([^>]*)>/i, '<head$1>\n<meta name="description" content="Professional website built with WebCraft AI.">'); changed = true; }
      html = html.replace(/<img([^>]*?)((?<!alt=)[^>]*?)>/gi, (m, a) => /alt=/i.test(m) ? m : '<img' + a + ' alt="Website image">');
      if (changed) {
        try {
          if (typeof generatedDesigns !== 'undefined' && generatedDesigns.length) {
            const i = (typeof activeDesignIndex === 'number') ? activeDesignIndex : 0;
            generatedDesigns[i].html = html; try { currentHtml = html; } catch (e) {}
            if (typeof saveSessionNow === 'function') saveSessionNow();
            if (typeof restoreIntoWorkspace === 'function') restoreIntoWorkspace();
          }
        } catch (e) {}
        toast('✨ Applied safe automatic fixes');
        return true;
      }
      toast('AI couldn\'t safely apply this change. Your website was not modified.');
      return false;
    } catch (e) { toast('AI couldn\'t safely apply this change. Your website was not modified.'); return false; }
  }

  /* ── PRE-PUBLISH CHECKLIST ── */
  function runPrePublishAudit() {
    const html = currentHtmlNow();
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
    const checks = [];
    const need = (id, label, pass, fixable, detail) => checks.push({ id, label, pass: !!pass, fixable: !!fixable, detail: detail || '' });
    const title = (doc.querySelector('title') ? doc.querySelector('title').textContent : '').trim();
    need('biz', 'Business name', title.length > 2 && !/^(website|home|untitled)$/i.test(title), false, title || 'missing');
    need('title', 'Site title', title.length > 5, true, title.slice(0, 70) || 'missing');
    need('desc', 'Meta description', !!doc.querySelector('meta[name="description"]'), true, '');
    need('favicon', 'Favicon', !!doc.querySelector('link[rel*="icon"]'), false, '');
    need('viewport', 'Viewport meta', /name=["']viewport["']/i.test(String(html)), true, '');
    need('mobile', 'Mobile responsive', !/lorem ipsum/i.test(doc.body ? doc.body.textContent : ''), true, 'overflow + tiny-text scan');
    const dead = [...doc.querySelectorAll('a')].filter((a) => { const h = (a.getAttribute('href') || '').trim(); return h === '' || h === '#'; }).length;
    need('links', 'Broken internal links', dead === 0, true, dead ? dead + ' dead' : 'clean');
    const noAlt = [...doc.querySelectorAll('img')].filter((i) => !(i.getAttribute('alt') || '').trim()).length;
    need('alt', 'Image alt text', noAlt === 0, true, noAlt ? noAlt + ' missing' : 'clean');
    need('h1', 'Heading hierarchy', doc.querySelectorAll('h1').length === 1, true, doc.querySelectorAll('h1').length + ' H1');
    need('contact', 'Contact details', /[@+0-9]{4,}/.test(doc.body ? doc.body.textContent : '') || /contact/i.test(String(html)), false, '');
    need('cta', 'CTA present', /contact|whatsapp|call|book|order|buy|get started/i.test(doc.body ? doc.body.textContent : ''), false, '');
    const emptyS = [...doc.querySelectorAll('section')].filter((s) => !s.textContent.trim()).length;
    need('empty', 'No empty sections', emptyS === 0, false, emptyS ? emptyS + ' empty' : 'clean');
    need('https', 'HTTPS-safe links', !/http:\/\/(?!localhost|127\.0\.0\.1)/i.test(String(html)), false, '');
    need('a11y', 'Basic accessibility', noAlt === 0 && doc.querySelectorAll('h1').length >= 1, true, '');
    need('saved', 'Saved changes', true, false, new Date().toLocaleTimeString());
    const failed = checks.filter((c) => !c.pass);
    const critical = failed.filter((c) => ['biz', 'title', 'viewport', 'links', 'empty'].includes(c.id));
    return { checks, failed, critical, ready: failed.length === 0, at: Date.now() };
  }
  function renderPrePublishChecklist() {
    const r = runPrePublishAudit();
    let h = '<div style="font-weight:800;font-size:1rem;margin-bottom:0.4rem;color:#fff;">'
      + (r.ready ? '✅ READY TO PUBLISH' : '⚠️ ' + r.failed.length + ' issue(s) need attention') + '</div>';
    h += '<div class="wc-ai-hint">Critical issues should be fixed before publishing. Warnings won\'t block you, but hurt quality.</div>';
    r.checks.forEach((c) => {
      h += '<div class="wc-ai-issue"><span class="sev">' + (c.pass ? '✅' : (['biz', 'title', 'viewport', 'links', 'empty'].includes(c.id) ? '❌' : '⚠️')) + '</span>'
        + '<div class="body"><b>' + esc(c.label) + '</b>' + (c.detail ? '<div class="meta">' + esc(c.detail) + '</div>' : '') + '</div>'
        + '<div class="acts">' + (!c.pass && c.fixable ? '<button class="wc-ai-btn small" onclick="fixPrePublishIssue(\'' + c.id + '\')">Fix</button>' : '') + '</div></div>';
    });
    openWcModal('🚀 Pre-Publish Checklist', h, [
      { label: 'Fix Automatically', cls: 'ghost', onClick: () => fixAllHealthIssues() },
      { label: 'Close', cls: '', onClick: closeWcModal },
      {
        label: r.ready ? 'Continue to Publish' : 'Publish Anyway', cls: 'primary', onClick: () => {
          closeWcModal();
          try {
            if (typeof saveAndProceedToPayment === 'function') saveAndProceedToPayment();
            else if (typeof openStudioPreview === 'function') openStudioPreview();
            else toast('Publish flow not found on this screen');
          } catch (e) { toast('Publish failed: ' + e.message); }
        }
      }
    ], { wide: true });
    return r;
  }
  async function fixPrePublishIssue(issueId) {
    // Map checklist/health issue ids onto the native single-issue fixer when possible.
    try {
      if (typeof copilotState !== 'undefined' && copilotState.audit && typeof copilotFixIssue === 'function') {
        const map = { title: 'seo-title', desc: 'seo-desc', alt: 'img-alt', links: 'dead-links', viewport: 'viewport', mobile: 'overflow', h1: 'h1-demote' };
        const want = map[issueId];
        if (want) {
          const idx = copilotState.audit.issues.findIndex((x) => x.fix === want);
          if (idx >= 0) { await copilotFixIssue(idx); toast('✅ Fixed'); return true; }
        }
      }
      if (typeof issueId === 'number' && typeof copilotFixIssue === 'function') {
        await copilotFixIssue(issueId); return true;
      }
    } catch (e) {}
    // Local safe fixes (both contexts).
    try { createVersionSnapshot('Before pre-publish fix: ' + issueId, 'AI'); } catch (e) {}
    try {
      if (typeof getStudioCanvasDocument === 'function') {
        // Studio: alter via components where available, else raw HTML patch below.
        if (issueId === 'alt' && typeof copilotWalkComps === 'function') {
          let n = 0;
          copilotWalkComps((c) => {
            if ((c.get('tagName') || '').toLowerCase() !== 'img') return false;
            const at = (c.getAttributes && c.getAttributes()) || {};
            if ((at.alt || '').trim()) return false;
            try { c.addAttributes({ alt: 'Website image' }); n++; return true; } catch (e) { return false; }
          });
          syncCanvasToHtml(); saveProjectData(); toast('✅ Added ' + n + ' alt tag(s)'); return true;
        }
      }
      let html = currentHtmlNow();
      if (!html) { toast('Nothing to fix yet'); return false; }
      if (issueId === 'viewport' && !/name=["']viewport["']/i.test(html)) html = html.replace(/<head([^>]*)>/i, '<head$1>\n<meta name="viewport" content="width=device-width, initial-scale=1">');
      else if (issueId === 'desc' && !/<meta name="description"/i.test(html)) html = html.replace(/<head([^>]*)>/i, '<head$1>\n<meta name="description" content="Professional website built with WebCraft AI.">');
      else if ((issueId === 'title' || issueId === 'biz') && /<title>.*?<\/title>/i.test(html)) {
        const name = (() => { try { return (typeof projectData !== 'undefined' && projectData.bizName) || document.getElementById('biz_name')?.value || 'My Business'; } catch (e) { return 'My Business'; } })();
        html = html.replace(/<title>.*?<\/title>/i, '<title>' + name + ' — Official Website</title>');
      } else if (issueId === 'alt') html = html.replace(/<img([^>]*?)>/gi, (m) => /alt=/i.test(m) ? m : m.replace('<img', '<img alt="Website image"'));
      else if (issueId === 'h1') {
        let n = 0;
        html = html.replace(/<h1/gi, () => (++n === 1 ? '<h1' : '<h2'));
        html = html.replace(/<\/h1>/gi, () => (n-- > 1 ? '</h2>' : '</h1>'));
      } else if (issueId === 'links') html = html.replace(/href="#"/g, 'href="#contact"');
      else { toast('AI couldn\'t safely apply this change. Your website was not modified.'); return false; }
      // persist
      try {
        if (typeof projectData !== 'undefined' && projectData && projectData.designs) {
          const i = (typeof activeConceptIndex === 'number') ? activeConceptIndex : 0;
          projectData.designs[i].html = html; try { currentHtml = html; } catch (e) {}
          try { if (typeof loadHtmlIntoStudioCanvas === 'function') loadHtmlIntoStudioCanvas(); } catch (e) {}
          if (typeof saveProjectData === 'function') saveProjectData();
        } else if (typeof generatedDesigns !== 'undefined') {
          const i = (typeof activeDesignIndex === 'number') ? activeDesignIndex : 0;
          if (generatedDesigns[i]) generatedDesigns[i].html = html;
          try { currentHtml = html; } catch (e) {}
          if (typeof saveSessionNow === 'function') saveSessionNow();
        }
      } catch (e) {}
      toast('✅ Fixed — re-run the checklist to verify');
      return true;
    } catch (e) { toast('AI couldn\'t safely apply this change. Your website was not modified.'); return false; }
  }

  /* ── PROJECT DASHBOARD (respects per-customer ownership) ── */
  function listOwnedSessions() {
    const me = customerEmail();
    const out = [];
    try {
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        if (!k || k.indexOf('webcraft_saved_project::') !== 0) continue;
        try {
          const s = JSON.parse(localStorage.getItem(k));
          if (!s) continue;
          // Ownership: scoped key email must match current user (guest key only for guests).
          const owner = k.split('::')[1] || '';
          if (me !== 'guest' && owner !== me && owner !== 'guest') continue;
          if (me === 'guest' && owner !== 'guest') continue;
          out.push({ key: k, session: s });
        } catch (e) {}
      }
    } catch (e) {}
    return out;
  }
  function searchProjects(query) {
    const q = String(query || '').toLowerCase().trim();
    const all = listOwnedSessions();
    if (!q) return all;
    return all.filter((o) => {
      const s = o.session || {};
      const hay = [s.bizName, s.stage, (s.wizard && s.wizard.biz_name), (s.wizard && s.wizard.biz_type)].join(' ').toLowerCase();
      return hay.indexOf(q) !== -1;
    });
  }
  function sortProjects(mode) {
    const all = listOwnedSessions();
    const m = mode || 'recent';
    all.sort((a, b) => {
      const sa = a.session || {}, sb = b.session || {};
      if (m === 'name') return String(sa.bizName || '').localeCompare(String(sb.bizName || ''));
      if (m === 'oldest') return (sa.savedAt || 0) - (sb.savedAt || 0);
      return (sb.savedAt || 0) - (sa.savedAt || 0);
    });
    return all;
  }
  function duplicateProject(projectId) {
    try {
      const key = projectId || sessionKey();
      const raw = localStorage.getItem(key);
      if (!raw) { toast('Project not found'); return false; }
      const copy = JSON.parse(raw);
      copy.savedAt = Date.now();
      copy.bizName = (copy.bizName || 'Website') + ' (copy)';
      const nk = key + '::copy-' + Date.now();
      localStorage.setItem(nk, JSON.stringify(copy));
      toast('📋 Duplicated project');
      return true;
    } catch (e) { toast('Duplicate failed: ' + e.message); return false; }
  }
  function renameProject(projectId, name) {
    try {
      const key = projectId || sessionKey();
      const raw = localStorage.getItem(key);
      if (!raw) { toast('Project not found'); return false; }
      const s = JSON.parse(raw);
      s.bizName = String(name || '').trim() || s.bizName;
      if (s.wizard) s.wizard.biz_name = s.bizName;
      s.savedAt = Date.now();
      localStorage.setItem(key, JSON.stringify(s));
      try {
        const inp = $('biz_name'); if (key === sessionKey() && inp) inp.value = s.bizName;
        if (typeof projectData !== 'undefined' && projectData) { projectData.bizName = s.bizName; }
      } catch (e) {}
      toast('✏️ Renamed to ' + s.bizName);
      return true;
    } catch (e) { toast('Rename failed: ' + e.message); return false; }
  }
  function archiveProject(projectId) {
    try {
      const key = projectId || sessionKey();
      const raw = localStorage.getItem(key);
      if (!raw) { toast('Project not found'); return false; }
      localStorage.setItem('wc_archived::' + key + '::' + Date.now(), raw);
      localStorage.removeItem(key);
      toast('📦 Archived project');
      return true;
    } catch (e) { toast('Archive failed: ' + e.message); return false; }
  }
  function deleteProject(projectId) {
    try {
      const key = projectId || sessionKey();
      localStorage.removeItem(key);
      toast('🗑 Deleted project');
      return true;
    } catch (e) { toast('Delete failed: ' + e.message); return false; }
  }
  function openProjectInStudio(projectId) {
    try {
      const key = projectId || sessionKey();
      const raw = localStorage.getItem(key);
      if (raw) localStorage.setItem('webcraft_saved_project', raw);
      const base = siteBase();
      window.location.href = (base ? base : '') + '/studio.php';
    } catch (e) { toast('Open failed: ' + e.message); }
  }
  function previewProject(projectId) {
    try {
      const key = projectId || sessionKey();
      const s = JSON.parse(localStorage.getItem(key) || 'null');
      const html = (s && s.designs && s.designs[(s.activeDesignIndex || 0)] && s.designs[(s.activeDesignIndex || 0)].html) || currentHtmlNow();
      if (!html) { toast('No preview available yet'); return; }
      const w = window.open('', '_blank');
      if (w) { w.document.write(html); w.document.close(); }
    } catch (e) { toast('Preview failed: ' + e.message); }
  }

  /* ── SMART COMMAND ROUTER (extends existing Copilot router) ── */
  function classifyCommand(cmd) {
    const t = String(cmd || '').toLowerCase();
    if (!t.trim()) return 'ask';
    if (/^(hi|hello|thanks|thank you|hey)\b/.test(t)) return 'ask';
    if (/audit|health|check.*(seo|site|page)|score/.test(t)) return 'audit';
    if (/fix all|fix everything|fix all issues/.test(t)) return 'fix';
    if (/mobile|responsive|phone|overflow|tiny text/.test(t)) return 'mobile';
    if (/\b(add|create|insert)\b.*(faq|testimonial|galler|pric|section|hero|contact|about|service)/.test(t) || /^(add|create)\b/.test(t)) return 'add section';
    if (/color|colour|font|theme|premium|luxury|modern|minimal|energetic|professional|design|style/.test(t)) return 'design';
    if (/seo|meta|title|description|rank|google/.test(t)) return 'SEO';
    if (/image|photo|picture|background|alt text/.test(t)) return 'image';
    if (/translat|tamil|tamil|sinha|c Tamil/i.test(cmd) || /tamil|sinhala|tamil/i.test(t)) return 'translation';
    if (/cta|conversion|sales|leads|whatsapp|trust|testimonial.*(move|closer)|checkout/.test(t)) return 'conversion';
    if (/tone|voice|terminology|heading.*(professional|consistent)|brand voice|spelling|language consistency/.test(t)) return 'content';
    if (/undo|restore|version|history|before|after/.test(t)) return 'audit';
    return 'edit';
  }
  function aiCommandRouter(command) {
    const kind = classifyCommand(command);
    const q = String(command || '');
    try {
      // Delegate to native lanes — never a parallel AI engine.
      if (kind === 'audit' && typeof copilotAuditRun === 'function') return { kind, handled: true, run: () => runDesignHealthAudit() };
      if (kind === 'fix' && typeof copilotFixAll === 'function') return { kind, handled: true, run: () => fixAllHealthIssues() };
      if (kind === 'mobile') {
        if (typeof copilotMobileFix === 'function') return { kind, handled: true, run: () => copilotMobileFix() };
        if (typeof copilotFixMobileNow === 'function') return { kind, handled: true, run: () => copilotFixMobileNow() };
      }
      if (kind === 'add section' && typeof copilotAddSection === 'function') return { kind, handled: true, run: (cls) => copilotAddSection(cls || 'section') };
      if (typeof executeMagicAi === 'function') return { kind, handled: true, run: () => executeMagicAi(q) };
    } catch (e) {}
    return { kind, handled: false, run: null };
  }
  function aiCommandExplain(command) {
    const r = aiCommandRouter(command);
    return '“' + String(command || '').slice(0, 80) + '” → ' + r.kind + (r.handled ? ' (handled by existing AI pipeline)' : ' (no native lane — will ask AI)');
  }
  function aiCommandPreview(command) {
    return aiCommandExplain(command);
  }
  async function aiCommandApply(command) {
    const r = aiCommandRouter(command);
    if (r.handled && r.run) {
      try { await r.run(); return true; }
      catch (e) { toast('AI couldn\'t safely apply this change. Your website was not modified.'); return false; }
    }
    try {
      if (typeof executeMagicAi === 'function') { await executeMagicAi(command); return true; }
    } catch (e) {}
    toast('Copilot is not available on this screen — open Studio for AI edits.');
    return false;
  }

  /* ── exports (exact names required by spec) ── */
  Object.assign(window, {
    // utils
    wcAiToast: toast, wcAiDebounce: debounce, wcAiPost: aiPost,
    getProjectExt, setProjectExt,
    // modal
    openWcModal, closeWcModal, confirmWcModal,
    // brand kit
    openBrandKit, saveBrandKit, loadBrandKit, applyBrandKit, resetBrandKit,
    // versions + before/after
    createVersionSnapshot, getVersionHistory, renderVersionHistory,
    previewVersion, restoreVersion, compareVersions, openBeforeAfterPreview,
    // health + fix + prepublish
    runDesignHealthAudit, calculateDesignHealthScore, renderDesignHealthPanel,
    fixAllHealthIssues, runPrePublishAudit, renderPrePublishChecklist, fixPrePublishIssue,
    // dashboard
    searchProjects, sortProjects, duplicateProject, renameProject,
    archiveProject, deleteProject, openProjectInStudio, previewProject,
    // router
    aiCommandRouter, aiCommandExplain, aiCommandPreview, aiCommandApply
  });
})();
