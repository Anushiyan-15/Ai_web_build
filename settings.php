<?php
// settings.php — App Settings (customer-friendly)
// Sections: 01 Appearance · 02 Custom colors · 03 Account & more.
// Theme persists client-side via wc-theme.js (no backend change).
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ui-lang.php';
$UI_LANG = ui_resolve_lang();
$page_title = 'Settings';
$page_desc = 'Change how WebCraft AI looks, make your own colors, and find your account options.';
require_once __DIR__ . '/includes/nav.php';
$customerUser = $_SESSION['customer_user'] ?? null;
?>
<div class="set-wrap">
  <!-- ── Hero: what is on this page ── -->
  <div class="set-hero">
    <div>
      <span class="badge">⚙️ Settings</span>
      <h1 class="heading-lg">Make it yours</h1>
      <p class="lead" style="margin-top:.5rem">Change how WebCraft AI looks. Everything saves automatically on this device — your websites are never affected.</p>
    </div>
    <div class="set-status" id="set-status">
      <span class="set-status-dot" id="set-status-dot"></span>
      <span>Using now: <strong id="set-current-name">…</strong></span>
    </div>
  </div>
  <nav class="set-toc" aria-label="What's on this page">
    <a href="#set-appearance"><span>🎨</span><span><strong>1 · Appearance</strong><small>Dark, Light or Custom look</small></span></a>
    <a href="#set-colors"><span>🖌️</span><span><strong>2 · My colors</strong><small>Your own color scheme</small></span></a>
    <a href="#set-account"><span>👤</span><span><strong>3 · Account &amp; more</strong><small>Login, language, websites</small></span></a>
  </nav>

  <!-- ── 01 Appearance ── -->
  <section class="set-card set-section" id="set-appearance">
    <div class="set-sec-head">
      <span class="set-num">01</span>
      <div><h2>Appearance</h2><p class="sub">Pick how the whole app looks — Builder, Studio, dashboard and the AI screen all change together, instantly.</p></div>
    </div>
    <div class="theme-cards" role="radiogroup" aria-label="Appearance mode">
      <div class="mode-opt tcard" data-mode="dark" onclick="WCTheme.setMode('dark')" role="radio" tabindex="0">
        <div class="tprev tprev-dark"><span class="tp-nav"></span><span class="tp-hero"></span><span class="tp-btn"></span></div>
        <div class="mo-t">🌙 Dark</div><div class="mo-d">Easy on the eyes, best at night</div>
        <div class="tactive">✓ Active</div>
      </div>
      <div class="mode-opt tcard" data-mode="light" onclick="WCTheme.setMode('light')" role="radio" tabindex="0">
        <div class="tprev tprev-light"><span class="tp-nav"></span><span class="tp-hero"></span><span class="tp-btn"></span></div>
        <div class="mo-t">☀️ Light</div><div class="mo-d">Bright and clean for daytime</div>
        <div class="tactive">✓ Active</div>
      </div>
      <div class="mode-opt tcard" data-mode="custom" onclick="WCTheme.setMode('custom')" role="radio" tabindex="0">
        <div class="tprev tprev-custom"><span class="tp-nav"></span><span class="tp-hero"></span><span class="tp-btn"></span></div>
        <div class="mo-t">🎨 Custom</div><div class="mo-d">Your own colors from section 2</div>
        <div class="tactive">✓ Active</div>
      </div>
    </div>
    <input type="hidden" id="settingsThemeMode" value="dark">
    <p class="set-note">💡 Tip: this changes only how <strong>WebCraft AI itself</strong> looks. The websites you build keep their own designs.</p>
  </section>

  <!-- ── 02 Custom colors ── -->
  <section class="set-card set-section" id="set-colors">
    <div class="set-sec-head">
      <span class="set-num">02</span>
      <div><h2>My colors</h2><p class="sub">Build your own color scheme. Try colors below — the whole app previews live. Press <strong>Apply</strong> to keep them.</p></div>
    </div>
    <h3 class="set-group-t">🖼️ Backgrounds</h3>
    <div class="swatch-row" id="custom-fields-bg"></div>
    <h3 class="set-group-t">✨ Accent colors <small>(buttons &amp; highlights)</small></h3>
    <div class="swatch-row" id="custom-fields-accent"></div>
    <h3 class="set-group-t">🔤 Text <small>(must stay readable)</small></h3>
    <div class="swatch-row" id="custom-fields-text"></div>
    <h3 class="set-group-t">🔲 Finishing touches</h3>
    <div class="swatch-row" id="custom-fields-border"></div>
    <div class="cf-radius-row">
      <label for="cf-radius">Corner roundness: <span id="cf-radius-val">12</span>px <small>(square ↔ round)</small></label>
      <input type="range" id="cf-radius" min="0" max="24" value="12" style="width:100%;accent-color:#6366f1">
    </div>
    <h3 class="set-group-t">🎁 Start from a ready-made style</h3>
    <div class="preset-row" id="preset-row"></div>
    <div class="contrast-warn" id="contrast-warn"></div>
    <div class="set-actions">
      <button class="btn btn-primary" id="btn-apply-custom">✓ Apply my colors</button>
      <button class="btn btn-ghost" id="btn-reset-custom">↺ Back to default colors</button>
    </div>
    <h3 class="set-group-t">👀 Live preview</h3>
    <div class="preview-box" id="live-preview">
      <div class="preview-nav" id="pv-nav"><span>✦</span> WebCraft AI <span style="margin-left:auto;font-weight:600;opacity:.7">Preview</span></div>
      <div class="preview-hero" id="pv-hero">
        <div class="ph-t" id="pv-title">Your business, beautifully online</div>
        <div class="ph-s" id="pv-sub">Buttons, cards and inputs follow your colors in real time.</div>
        <div class="preview-btns"><span id="pv-btn1">✦ Generate Website</span><span id="pv-btn2" style="border:1.5px solid">Learn more</span></div>
      </div>
      <div style="padding:.8rem 1rem;display:flex;gap:.5rem" id="pv-cards">
        <div style="flex:1;border-radius:10px;padding:.7rem;border:1px solid" id="pv-card1"><strong style="font-size:.78rem">Card</strong><div style="font-size:.72rem;opacity:.75">Secondary text stays readable.</div></div>
        <div style="flex:1;border-radius:10px;padding:.7rem;border:1px solid" id="pv-card2"><strong style="font-size:.78rem">Input box</strong><div style="font-size:.72rem;opacity:.75;border-radius:6px;padding:.3rem .5rem;margin-top:.3rem;border:1px solid" id="pv-input">Type here…</div></div>
      </div>
    </div>
    <!-- Back-compat mount point for existing script -->
    <div id="custom-fields" style="display:none"></div>
  </section>

  <!-- ── 03 Account & more ── -->
  <section class="set-card set-section" id="set-account">
    <div class="set-sec-head">
      <span class="set-num">03</span>
      <div><h2>Account &amp; more</h2><p class="sub">Everything else lives where you already use it — one tap away.</p></div>
    </div>
    <?php if ($customerUser): ?>
    <div class="set-me">👤 Signed in as <strong><?= htmlspecialchars($customerUser['email']) ?></strong></div>
    <?php else: ?>
    <div class="set-me">👋 You are browsing as a <strong>guest</strong> — <a href="<?= SITE_URL ?>/customer-portal.php" style="color:var(--p);font-weight:700">log in</a> to sync your websites on any device.</div>
    <?php endif; ?>
    <div class="set-links">
      <a class="btn btn-ghost" href="<?= SITE_URL ?>/customer-portal.php">📁 My websites <small>open, manage &amp; track</small></a>
      <a class="btn btn-ghost" href="<?= SITE_URL ?>/builder.php">✦ AI Builder <small>create a new site</small></a>
      <a class="btn btn-ghost" href="<?= SITE_URL ?>/customer-portal.php?tab=language">🌐 Language <small>change app language</small></a>
      <a class="btn btn-ghost" href="<?= SITE_URL ?>/site-manager.php">🗂️ Website manager <small>published site controls</small></a>
    </div>
  </section>
</div>

<script>
(function () {
  var GROUPS = [
    ['custom-fields-bg', [['bg', 'Page background', 'Main screen color'], ['bg2', 'Secondary background', 'Cards row, inputs'], ['surface', 'Card color', 'Boxes & panels']]],
    ['custom-fields-accent', [['accent', 'Main accent', 'Buttons & links'], ['accent2', 'Second accent', 'Gradients & glow']]],
    ['custom-fields-text', [['text', 'Main text', 'Headings & body'], ['text2', 'Soft text', 'Hints & captions']]],
    ['custom-fields-border', [['border', 'Border color', 'Lines & outlines']]]
  ];
  var hidden = document.getElementById('custom-fields');
  var draft = (window.WCTheme ? WCTheme.getCustom() : {});
  try { if (WCTheme.getMode() === 'custom') draft = WCTheme.getCustom(); } catch (e) {}
  var rEl = document.getElementById('cf-radius');
  if (rEl && draft.radius != null) { rEl.value = draft.radius; document.getElementById('cf-radius-val').textContent = draft.radius; }

  // Build grouped pickers; mirror values into hidden legacy inputs for compat.
  GROUPS.forEach(function (g) {
    var box = document.getElementById(g[0]);
    box.innerHTML = g[1].map(function (f) {
      var k = f[0], lbl = f[1], hint = f[2], v = draft[k] || '#000000';
      return '<div class="cf"><input type="color" data-k="' + k + '" value="' + v + '" aria-label="' + lbl + '">' +
        '<div class="cf-t"><label>' + lbl + '</label><small>' + hint + '</small></div>' +
        '<input type="text" data-hex="' + k + '" value="' + v + '" maxlength="7" spellcheck="false" aria-label="' + lbl + ' hex code"></div>';
    }).join('');
  });
  hidden.innerHTML = ['bg', 'bg2', 'surface', 'accent', 'accent2', 'text', 'text2', 'border'].map(function (k) {
    return '<input type="color" data-k="' + k + '" value="' + (draft[k] || '#000000') + '">'
      + '<input type="text" data-hex="' + k + '" value="' + (draft[k] || '#000000') + '">';
  }).join('');

  function allPickers() {
    return document.querySelectorAll('#set-colors input[data-k], #set-colors input[data-hex]');
  }
  function readDraft() {
    var d = {};
    document.querySelectorAll('#set-colors input[data-k]').forEach(function (c) {
      if (!hidden.contains(c)) d[c.getAttribute('data-k')] = c.value;
    });
    // hidden duplicates mirror the same keys; prefer visible ones (already collected)
    d.radius = parseInt(rEl.value, 10) || 0;
    return d;
  }
  function syncHidden(d) {
    hidden.querySelectorAll('input[data-k]').forEach(function (c) { if (d[c.getAttribute('data-k')]) c.value = d[c.getAttribute('data-k')]; });
    hidden.querySelectorAll('input[data-hex]').forEach(function (h) { if (d[h.getAttribute('data-hex')]) h.value = d[h.getAttribute('data-hex')]; });
  }
  function paintPreview(d) {
    var pv = document.getElementById('live-preview');
    pv.style.background = d.bg; pv.style.color = d.text; pv.style.borderColor = d.border;
    var nav = document.getElementById('pv-nav'); nav.style.background = d.surface; nav.style.color = d.text; nav.style.borderBottom = '1px solid ' + d.border;
    var hero = document.getElementById('pv-hero'); hero.style.background = 'linear-gradient(135deg,' + d.bg2 + ',' + d.bg + ')';
    document.getElementById('pv-title').style.color = d.text;
    document.getElementById('pv-sub').style.color = d.text2;
    var b1 = document.getElementById('pv-btn1'); b1.style.background = 'linear-gradient(135deg,' + d.accent + ',' + d.accent2 + ')'; b1.style.color = '#fff';
    var b2 = document.getElementById('pv-btn2'); b2.style.color = d.accent; b2.style.borderColor = d.accent;
    ['pv-card1', 'pv-card2'].forEach(function (id) { var e = document.getElementById(id); e.style.background = d.surface; e.style.borderColor = d.border; e.style.color = d.text; });
    var inp = document.getElementById('pv-input'); inp.style.background = d.bg2; inp.style.borderColor = d.border; inp.style.color = d.text2;
    pv.style.borderRadius = (d.radius || 12) + 'px';
  }
  function warn(d) {
    var w = document.getElementById('contrast-warn');
    try {
      var msgs = WCTheme.contrastWarnings(d);
      if (msgs.length) { w.innerHTML = '⚠️ ' + msgs.join('<br>⚠️ '); w.classList.add('show'); }
      else w.classList.remove('show');
    } catch (e) {}
  }
  function live() { var d = readDraft(); syncHidden(d); try { WCTheme.previewCustom(d); } catch (e) {} paintPreview(d); warn(d); }

  document.getElementById('set-colors').addEventListener('input', function (e) {
    var t = e.target;
    if (!t.hasAttribute || (!t.hasAttribute('data-k') && !t.hasAttribute('data-hex'))) return;
    if (t.hasAttribute('data-k')) {
      document.querySelectorAll('input[data-hex="' + t.getAttribute('data-k') + '"]').forEach(function (hex) { hex.value = t.value; });
    } else {
      var v = t.value.trim();
      if (/^#[0-9a-fA-F]{6}$/.test(v)) {
        document.querySelectorAll('input[data-k="' + t.getAttribute('data-hex') + '"]').forEach(function (col) { col.value = v.toLowerCase(); });
      } else return;
    }
    live();
  });
  rEl.addEventListener('input', function () { document.getElementById('cf-radius-val').textContent = rEl.value; live(); });

  var presets = WCTheme.getPresets();
  var pr = document.getElementById('preset-row');
  var PRETTY = { ocean: '🌊 Ocean', sunset: '🌅 Sunset', forest: '🌲 Forest', lavender: '💜 Lavender', midnight: '🌙 Midnight' };
  pr.innerHTML = Object.keys(presets).map(function (k) {
    return '<button class="preset-chip" data-p="' + k + '"><i style="background:linear-gradient(135deg,' + presets[k].accent + ',' + presets[k].accent2 + ')"></i>' + (PRETTY[k] || k) + '</button>';
  }).join('');
  pr.addEventListener('click', function (e) {
    var b = e.target.closest('[data-p]'); if (!b) return;
    var p = presets[b.getAttribute('data-p')];
    allPickers().forEach(function (c) {
      var key = c.getAttribute('data-k') || c.getAttribute('data-hex');
      if (p[key]) c.value = p[key];
    });
    rEl.value = p.radius || 12; document.getElementById('cf-radius-val').textContent = rEl.value;
    live();
  });

  document.getElementById('btn-apply-custom').addEventListener('click', function () {
    WCTheme.applyCustom(readDraft());
    try { WCTheme.setMode('custom'); } catch (e) {}
    sync();
  });
  document.getElementById('btn-reset-custom').addEventListener('click', function () {
    var d = WCTheme.getDefaults();
    allPickers().forEach(function (c) {
      var key = c.getAttribute('data-k') || c.getAttribute('data-hex');
      if (d[key]) c.value = d[key];
    });
    rEl.value = d.radius; document.getElementById('cf-radius-val').textContent = d.radius;
    WCTheme.applyCustom(d);
    sync();
  });
  document.querySelectorAll('.mode-opt').forEach(function (o) {
    o.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); o.click(); } });
  });
  var NAMES = { dark: '🌙 Dark', light: '☀️ Light', custom: '🎨 Custom' };
  function sync() {
    var m = 'dark';
    try { m = WCTheme.getMode(); } catch (e) {}
    document.querySelectorAll('.mode-opt').forEach(function (o) { o.classList.toggle('sel', o.getAttribute('data-mode') === m); });
    document.getElementById('set-current-name').textContent = NAMES[m] || m;
    document.getElementById('set-status-dot').style.background = m === 'light' ? '#d97706' : (m === 'custom' ? '#a855f7' : '#34d399');
    try { document.getElementById('settingsThemeMode').value = m; } catch (e) {}
    live();
  }
  document.addEventListener('wc:theme-change', sync);
  sync();
})();
</script>
</body></html>
