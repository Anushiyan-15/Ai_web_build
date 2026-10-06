<?php
// ═══════════════════════════════════════════════════════════════
//  variations.php — Standalone 3-Variations picker (separate URL)
//  Flow: builder.php (wizard → generate) → variations.php (pick)
//        → builder.php (workspace edit) / studio.
//  Reads the SAME localStorage session as builder.php
//  (key: webcraft_saved_project::<email|guest>), so no server
//  round-trip is needed. Same origin = same storage.
// ═══════════════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) session_start();
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once __DIR__ . '/config.php';

$customerUser  = $_SESSION['customer_user'] ?? null;
$siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Choose Your Variation — WebCraft AI</title>
<link rel="stylesheet" href="<?= htmlspecialchars($siteUrl) ?>/assets/css/loader-3d.css">
<script src="<?= htmlspecialchars($siteUrl) ?>/assets/js/loader-3d.js"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0a0d14; color: #e2e8f0; font-family: 'Inter', system-ui, sans-serif; min-height: 100vh; }
  .var-topbar { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; gap: 0.8rem; padding: 0.85rem 1.5rem; background: rgba(10,13,20,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid #1e293b; }
  .var-brand { font-weight: 800; font-size: 1.05rem; }
  .var-brand b { background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
  .var-back { margin-left: auto; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.1rem; border-radius: 10px; border: 1.5px solid #334155; background: #0b0f17; color: #cbd5e1; font-family: inherit; font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: all 0.15s; text-decoration: none; }
  .var-back:hover { border-color: #6366f1; color: #fff; }
  .var-head { text-align: center; padding: 2.5rem 1.5rem 0.5rem; }
  .var-badge { display: inline-block; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.08em; color: #a5b4fc; background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.5); padding: 0.35rem 0.9rem; border-radius: 999px; margin-bottom: 0.9rem; }
  .var-head h1 { font-size: clamp(1.6rem, 3.5vw, 2.4rem); font-weight: 800; letter-spacing: -0.02em; }
  .var-head p { color: #94a3b8; margin-top: 0.5rem; font-size: 0.95rem; }
  .var-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
  .var-card { background: #111622; border: 1px solid #1e293b; border-radius: 18px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s; position: relative; }
  .var-card:hover { transform: translateY(-4px); border-color: rgba(99,102,241,0.6); box-shadow: 0 18px 45px rgba(0,0,0,0.5), 0 0 0 1px rgba(99,102,241,0.25); }
  .var-num { position: absolute; top: 12px; right: 12px; z-index: 2; width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(99,102,241,0.5); }
  .var-frame-wrap { position: relative; height: 340px; background: #0b0f17; border-bottom: 1px solid #1e293b; }
  .var-frame-wrap iframe { width: 100%; height: 100%; border: none; background: #fff; }
  .var-frame-spin { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: #0b0f17; }
  .var-body { padding: 1.25rem 1.25rem 1.35rem; display: flex; flex-direction: column; gap: 0.6rem; flex: 1; }
  .var-body h3 { font-size: 1.05rem; }
  .var-pills { display: flex; gap: 0.35rem; flex-wrap: wrap; }
  .pill { font-size: 0.66rem; font-weight: 800; letter-spacing: 0.04em; padding: 0.22rem 0.6rem; border-radius: 999px; }
  .pill.ai { color: #6ee7b7; background: rgba(6,78,59,0.35); border: 1px solid rgba(16,185,129,0.5); }
  .pill.tpl { color: #fcd34d; background: rgba(120,53,15,0.35); border: 1px solid rgba(245,158,11,0.5); }
  .pill.taste { color: #c4b5fd; background: rgba(76,29,149,0.35); border: 1px solid rgba(139,92,246,0.5); }
  .var-body p { font-size: 0.85rem; color: #94a3b8; line-height: 1.55; }
  .var-sub { font-size: 0.72rem; color: #818cf8; font-weight: 700; letter-spacing: 0.05em; }
  .var-actions { display: flex; gap: 0.6rem; margin-top: auto; padding-top: 0.5rem; flex-wrap: wrap; }
  .var-btn { flex: 1; min-width: 110px; padding: 0.65rem 0.5rem; border-radius: 10px; font-family: inherit; font-size: 0.82rem; font-weight: 800; cursor: pointer; border: 1.5px solid #334155; background: #0b0f17; color: #cbd5e1; transition: all 0.15s; }
  .var-btn:hover { border-color: #6366f1; color: #fff; }
  .var-btn.go { background: linear-gradient(135deg, #10b981, #059669); border-color: #34d399; color: #fff; }
  .var-btn.go:hover { filter: brightness(1.1); }
  /* ── 3D splash loader (inline, creative, matches Loader3D theme) ── */
  #var-loader { position: fixed; inset: 0; z-index: 100; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; background: radial-gradient(circle at 50% 38%, #0d1330 0%, #05070f 62%, #010204 100%); transition: opacity 0.4s; }
  #var-loader.hide { opacity: 0; pointer-events: none; }
  #var-loader::before { content: ''; position: absolute; inset: 0; pointer-events: none; background-image: linear-gradient(rgba(99,102,241,.10) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,.10) 1px, transparent 1px); background-size: 44px 44px; transform: perspective(520px) rotateX(64deg) scale(3) translateY(22%); transform-origin: center bottom; animation: varGrid 3s linear infinite; }
  @keyframes varGrid { from { background-position: 0 0; } to { background-position: 0 44px; } }
  .var-stage { width: 300px; height: 220px; perspective: 800px; position: relative; }
  .var-cube { position: absolute; inset: 0; transform-style: preserve-3d; animation: varSpin 7s linear infinite; }
  @keyframes varSpin { from { transform: rotateX(-12deg) rotateY(0deg); } to { transform: rotateX(-12deg) rotateY(360deg); } }
  .var-face { position: absolute; top: 34px; width: 120px; height: 150px; border-radius: 12px; background: linear-gradient(160deg, rgba(30,41,59,.96), rgba(15,23,42,.96)); border: 1.5px solid rgba(129,140,248,.7); box-shadow: 0 0 24px rgba(99,102,241,.45); padding: 10px; backface-visibility: hidden; }
  .var-face i { display: block; border-radius: 4px; margin-bottom: 7px; }
  .var-face i:nth-child(1) { height: 34px; background: linear-gradient(135deg, #6366f1, #a855f7); box-shadow: 0 0 12px rgba(139,92,246,.8); animation: varGlow 1.6s ease-in-out infinite alternate; }
  .var-face.f2 i:nth-child(1) { background: linear-gradient(135deg, #10b981, #06b6d4); box-shadow: 0 0 12px rgba(16,185,129,.8); }
  .var-face.f3 i:nth-child(1) { background: linear-gradient(135deg, #e879f9, #6366f1); box-shadow: 0 0 12px rgba(232,121,249,.8); }
  .var-face i:nth-child(2) { height: 9px; width: 85%; background: rgba(226,232,240,.55); }
  .var-face i:nth-child(3) { height: 9px; width: 62%; background: rgba(148,163,184,.4); }
  .var-face i:nth-child(4) { height: 20px; width: 70%; background: rgba(16,185,129,.5); border-radius: 99px; margin: 9px auto 0; }
  @keyframes varGlow { from { filter: brightness(0.85); } to { filter: brightness(1.25); } }
  .var-face.f1 { left: 90px; transform: rotateY(0deg) translateZ(110px); }
  .var-face.f2 { left: 90px; transform: rotateY(120deg) translateZ(110px); }
  .var-face.f3 { left: 90px; transform: rotateY(240deg) translateZ(110px); }
  .var-load-msg { font-size: 1.05rem; font-weight: 800; }
  .var-load-msg b { background: linear-gradient(135deg, #38bdf8, #818cf8, #c084fc); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
  .var-load-sub { font-size: 0.8rem; color: #94a3b8; }
  .var-bar { width: min(320px, 70vw); height: 6px; border-radius: 99px; background: rgba(255,255,255,0.07); overflow: hidden; }
  .var-bar i { display: block; height: 100%; width: 30%; border-radius: 99px; background: linear-gradient(90deg, #4f46e5, #8b5cf6, #06b6d4); background-size: 200% 100%; animation: varBar 1.2s linear infinite; }
  @keyframes varBar { 0% { margin-left: -30%; } 100% { margin-left: 100%; } }
  #var-empty { display: none; text-align: center; padding: 4rem 1.5rem; }
  /* ── Back-to-Builder transition loader (creative 3D rewind portal) ── */
  #var-back-loader { position: fixed; inset: 0; z-index: 99999; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; background: radial-gradient(circle at 50% 42%, #131a36 0%, #05070f 62%, #010204 100%); opacity: 0; pointer-events: none; transition: opacity 0.3s ease; overflow: hidden; }
  #var-back-loader.on { opacity: 1; pointer-events: all; }
  #var-back-loader::before { content: ''; position: absolute; inset: 0; pointer-events: none; background-image: linear-gradient(rgba(99,102,241,.12) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,.12) 1px, transparent 1px); background-size: 44px 44px; transform: perspective(520px) rotateX(64deg) scale(3) translateY(22%); transform-origin: center bottom; animation: varGrid 3s linear infinite; }
  #var-back-loader::after { content: ''; position: absolute; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent 0%, rgba(99,102,241,0.8) 50%, rgba(52,211,153,0.8) 70%, transparent 100%); box-shadow: 0 0 20px rgba(99,102,241,0.9); animation: varBackLaser 3.5s ease-in-out infinite; pointer-events: none; }
  @keyframes varBackLaser { 0% { top: 6%; opacity: 0; } 12% { opacity: 0.8; } 88% { opacity: 0.8; } 100% { top: 94%; opacity: 0; } }
  .var-back-scene { position: relative; z-index: 2; transform: scale(1.05); }
  .var-back-title { position: relative; z-index: 2; font-size: clamp(1.2rem, 3vw, 1.7rem); font-weight: 900; letter-spacing: -0.02em; }
  .var-back-title b { background: linear-gradient(135deg, #38bdf8, #818cf8, #c084fc); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
  .var-back-sub { position: relative; z-index: 2; font-size: 0.82rem; color: #94a3b8; font-weight: 600; min-height: 1.3em; }
  .var-back-sub::after { content: ''; animation: wclDots 1.2s steps(4) infinite; }
  .var-back-bar { position: relative; z-index: 2; width: min(320px, 70vw); height: 6px; border-radius: 99px; background: rgba(255,255,255,0.07); overflow: hidden; box-shadow: 0 0 0 1px rgba(99,102,241,0.25); }
  .var-back-bar i { display: block; height: 100%; width: 40%; border-radius: 99px; background: linear-gradient(90deg, #4f46e5, #8b5cf6, #06b6d4); box-shadow: 0 0 12px rgba(99,102,241,.9); animation: wclSlide 1.1s ease-in-out infinite; }
  .var-back-chips { position: relative; z-index: 2; display: flex; gap: 0.4rem; flex-wrap: wrap; justify-content: center; }
  .var-back-chip { font-size: 0.68rem; font-weight: 800; color: #c7d2fe; background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.4); padding: 0.25rem 0.7rem; border-radius: 999px; animation: varBackChip 1.6s ease-in-out infinite; }
  .var-back-chip:nth-child(2) { animation-delay: 0.25s; }
  .var-back-chip:nth-child(3) { animation-delay: 0.5s; }
  @keyframes varBackChip { 0%, 100% { opacity: 0.45; transform: translateY(0); } 50% { opacity: 1; transform: translateY(-3px); } }
  @keyframes wclDots { 0% { content: ''; } 25% { content: '.'; } 50% { content: '..'; } 75% { content: '...'; } }
  @keyframes wclSlide { 0% { margin-left: -40%; } 100% { margin-left: 100%; } }
  #var-empty .big { font-size: 3rem; margin-bottom: 1rem; }
  #var-flash { display: none; max-width: 1200px; margin: 1.25rem auto 0; padding: 0.8rem 1.2rem; background: rgba(6,78,59,0.3); border: 1px solid rgba(16,185,129,0.5); color: #a7f3d0; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
  @media (max-width: 640px) { .var-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<!-- 3D splash: visible instantly, hidden after session renders -->
<div id="var-loader">
  <div class="var-stage">
    <div class="var-cube">
      <div class="var-face f1"><i></i><i></i><i></i><i></i></div>
      <div class="var-face f2"><i></i><i></i><i></i><i></i></div>
      <div class="var-face f3"><i></i><i></i><i></i><i></i></div>
    </div>
  </div>
  <div class="var-load-msg">Loading your <b>3 variations</b></div>
  <div class="var-bar"><i></i></div>
  <div class="var-load-sub" id="var-load-sub">Reading saved session…</div>
</div>

<div class="var-topbar">
  <div class="var-brand">✦ WebCraft <b>Variations</b></div>
  <a class="var-back" href="<?= htmlspecialchars($siteUrl) ?>/builder.php?wizard=1" onclick="return (window.varBackToBuilder ? varBackToBuilder(this) : true)">← Back to Builder</a>
</div>

<div class="var-head">
  <div class="var-badge">✦ 3 Style Variations</div>
  <h1 id="var-title">Choose Your Style Variation</h1>
  <p id="var-sub">All 3 use your exact content and design direction — only the layout differs.</p>
</div>

<div id="var-flash"></div>
<div class="var-grid" id="var-grid"></div>

<div id="var-empty">
  <div class="big">🎨</div>
  <h2>No variations yet</h2>
  <p style="color:#94a3b8;margin:0.5rem 0 1.5rem;">Generate your website first — your 3 style variations will appear here.</p>
  <a class="var-back" style="margin:0;" href="<?= htmlspecialchars($siteUrl) ?>/builder.php?wizard=1" onclick="return (window.varBackToBuilder ? varBackToBuilder(this) : true)">Open Builder →</a>
</div>

<!-- Back-to-Builder 3D transition loader (shows instantly on Back click) -->
<div id="var-back-loader" aria-hidden="true">
  <div class="var-back-scene">
    <div class="wcl-back">
      <div class="ring r1"></div><div class="ring r2"></div><div class="ring r3"></div>
      <div class="core"></div>
      <div class="arrow">←</div>
      <div class="orbit"><i></i><i></i></div>
      <div class="trail"><i></i><i></i><i></i></div>
    </div>
  </div>
  <div class="var-back-title">← Back to <b>Builder</b></div>
  <div class="var-back-bar"><i></i></div>
  <div class="var-back-sub" id="var-back-sub">Packing your variations</div>
  <div class="var-back-chips">
    <span class="var-back-chip">✦ Setup Wizard</span>
    <span class="var-back-chip">🎨 Variations Saved</span>
    <span class="var-back-chip">← Rewind Portal</span>
  </div>
</div>

<script>
window.__CUSTOMER__ = <?= json_encode($customerUser ?: null) ?>;
window.__SITE_URL__ = <?= json_encode($siteUrl) ?>;
(function () {
  'use strict';
  var SESSION_KEY = 'webcraft_saved_project::' + ((window.__CUSTOMER__ && window.__CUSTOMER__.email) || 'guest').toLowerCase();
  var BASE = window.__SITE_URL__ || '';

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  // Same sanitize as builder.php (strip leaks after </html>, PHP tags, fences)
  function sanitize(raw) {
    if (!raw) return '';
    var s = String(raw).trim().replace(/^```(?:html)?\s*/i, '').replace(/```\s*$/i, '');
    var dt = s.search(/<!DOCTYPE|<html/i);
    if (dt > 0) s = s.slice(dt);
    var ci = s.toLowerCase().lastIndexOf('</html>');
    if (ci !== -1) s = s.slice(0, ci + 7);
    return s.replace(/<\?php[\s\S]*?(?:\?>|$)/gi, '').replace(/\?>/g, '');
  }
  function hydrate(s) {
    try {
      if (!s || !Array.isArray(s.designs)) return s;
      if (Array.isArray(s.concepts)) {
        s.concepts.forEach(function (c, i) {
          if (c && !c.html && s.designs[i] && s.designs[i].html) c.html = s.designs[i].html;
        });
        if (!s.concepts.length && s.designs.length) s.concepts = s.designs;
      } else { s.concepts = s.designs; }
    } catch (e) {}
    return s;
  }
  function flash() {
    try {
      var m = sessionStorage.getItem('wc_flash');
      if (m) {
        sessionStorage.removeItem('wc_flash');
        var f = document.getElementById('var-flash');
        f.textContent = m; f.style.display = 'block';
      }
    } catch (e) {}
  }
  function hideLoader() {
    setTimeout(function () { document.getElementById('var-loader').classList.add('hide'); }, 500);
  }

  function selectAndEdit(i) {
    try {
      var raw = localStorage.getItem(SESSION_KEY);
      if (!raw) return;
      var s = JSON.parse(raw);
      s.activeDesignIndex = i;
      s.stage = 'builder';
      s.currentViewMode = 'site';
      s.savedAt = Date.now();
      localStorage.setItem(SESSION_KEY, JSON.stringify(s));
    } catch (e) {}
    try { if (window.Loader3D) Loader3D.show('Opening workspace…', 'Loading variation ' + (i + 1), 'home'); } catch (e) {}
    setTimeout(function () { window.location.href = BASE + '/builder.php'; }, 350);
  }
  window.varSelectAndEdit = selectAndEdit;

  function openLayouts(i) {
    try {
      var raw = localStorage.getItem(SESSION_KEY);
      if (raw) {
        var s = JSON.parse(raw);
        s.activeDesignIndex = i;
        s.savedAt = Date.now();
        localStorage.setItem(SESSION_KEY, JSON.stringify(s));
      }
    } catch (e) {}
    try { if (window.Loader3D) Loader3D.show('Opening layouts…', 'Loading 3 layout variants', 'back'); } catch (e) {}
    setTimeout(function () { window.location.href = BASE + '/builder.php?layouts=' + i; }, 450);
  }
  window.varOpenLayouts = openLayouts;

  // Back-to-Builder links: creative 3D rewind-portal loader, then navigate.
  // Uses dedicated #var-back-loader overlay (instant, no dependency) so the
  // animation always plays even if Loader3D fails to load.
  var __varBackPending = false;
  function backToBuilder(a) {
    if (!a || !a.href) return true;
    if (__varBackPending) return false;
    __varBackPending = true;
    var url = a.href;
    try {
      if (a && a.textContent) a.style.opacity = '0.6';
      var ov = document.getElementById('var-back-loader');
      if (ov) { ov.classList.add('on'); ov.setAttribute('aria-hidden', 'false'); }
      var sub = document.getElementById('var-back-sub');
      if (sub) {
        sub.textContent = 'Packing your variations';
        setTimeout(function () { try { sub.textContent = 'Opening setup wizard'; } catch (e) {} }, 400);
      }
    } catch (e) {}
    setTimeout(function () { window.location.href = url; }, 950);
    setTimeout(function () { __varBackPending = false; }, 4000);
    return false;
  }
  window.varBackToBuilder = backToBuilder;

  function fullPreview(i, list) {
    var c = list[i];
    if (!c || !c.html) return;
    var w = window.open('', '_blank');
    if (w) { w.document.open(); w.document.write(c.html); w.document.close(); }
  }
  window.varFullPreview = function (i) { fullPreview(i, window.__VAR_LIST__ || []); };

  function render(list, bizName) {
    document.getElementById('var-title').textContent = '3 Style Concepts for ' + (bizName || 'Your Website');
    var grid = document.getElementById('var-grid');
    grid.innerHTML = '';
    window.__VAR_LIST__ = list;
    list.forEach(function (c, i) {
      var isAI = c.meta && (c.meta.engine === 'opencode' || c.meta.engine === 'gemini' || c.meta.engine === 'opencode-quick');
      var modelShort = isAI ? esc(String((c.meta && c.meta.model) || 'AI').split('/').pop()) : '';
      var enginePill = isAI
        ? '<span class="pill ai">✦ AI · ' + modelShort + '</span>'
        : '<span class="pill tpl">TEMPLATE · server engine</span>';
      var tastePill = (isAI && c.meta && c.meta.tasteAudit && typeof c.meta.tasteAudit.score === 'number')
        ? '<span class="pill taste">✦ TASTE ' + c.meta.tasteAudit.score + '</span>' : '';
      var hasSubs = (c.subdesigns && c.subdesigns.length > 0) || (c.meta && c.meta.hasLayouts);
      var card = document.createElement('div');
      card.className = 'var-card';
      card.innerHTML =
        '<div class="var-num">' + (i + 1) + '</div>' +
        '<div class="var-frame-wrap"><div class="var-frame-spin"><div class="wcl-mini-house" style="transform:scale(1.4);"><div class="walls"></div><div class="roof"></div><div class="door"></div></div></div>' +
        '<iframe id="var-if-' + i + '" title="Variation ' + (i + 1) + ' preview" sandbox="allow-scripts allow-same-origin"></iframe></div>' +
        '<div class="var-body"><h3>' + esc(c.name || ('Variation ' + (i + 1))) + '</h3>' +
        '<div class="var-pills">' + enginePill + tastePill + '</div>' +
        '<p>' + esc(c.description || 'A premium style variation for your business.') + '</p>' +
        (hasSubs ? '<div class="var-sub">✦ 3 LAYOUT VARIANTS INSIDE</div>' : '') +
        '<div class="var-actions">' +
        '<button class="var-btn" onclick="varFullPreview(' + i + ')">👁 Preview</button>' +
        '<button class="var-btn" onclick="varOpenLayouts(' + i + ')">✦ Layouts</button>' +
        '<button class="var-btn go" onclick="varSelectAndEdit(' + i + ')">Select &amp; Edit →</button>' +
        '</div></div>';
      grid.appendChild(card);
      (function (idx, html) {
        setTimeout(function () {
          try {
            var f = document.getElementById('var-if-' + idx);
            if (f && html) {
              f.srcdoc = html;
              f.onload = function () {
                try { var sp = f.previousElementSibling; if (sp && sp.classList.contains('var-frame-spin')) sp.remove(); } catch (e) {}
              };
              setTimeout(function () {
                try { var sp2 = f.previousElementSibling; if (sp2 && sp2.classList.contains('var-frame-spin')) sp2.remove(); } catch (e) {}
              }, 6000);
            }
          } catch (e) {}
        }, 60 + idx * 40);
      })(i, c.html);
    });
  }

  function boot() {
    flash();
    var sub = document.getElementById('var-load-sub');
    var list = [], biz = 'Your Website';
    try {
      var raw = localStorage.getItem(SESSION_KEY);
      if (raw) {
        var s = hydrate(JSON.parse(raw));
        if (s && Array.isArray(s.concepts) && s.concepts.length) {
          list = s.concepts.map(function (c) {
            var slim = Object.assign({}, c);
            if (!slim.html) {
              var d = (s.designs || []).find(function (x, xi) { return xi === s.concepts.indexOf(c) && x && x.html; });
              if (d) slim.html = d.html;
            }
            slim.html = sanitize(slim.html || '');
            return slim;
          }).filter(function (c) { return c.html && c.html.length > 200; });
          biz = s.bizName || (s.wizard && s.wizard.biz_name) || biz;
        }
      }
    } catch (e) { if (sub) sub.textContent = 'Session read failed — opening builder…'; }
    if (!list.length) {
      document.getElementById('var-empty').style.display = 'block';
      document.getElementById('var-grid').style.display = 'none';
      hideLoader();
      return;
    }
    if (sub) sub.textContent = list.length + ' variations found — rendering previews…';
    render(list, biz);
    hideLoader();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>
</body>
</html>
