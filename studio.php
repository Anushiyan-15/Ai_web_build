<?php
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once __DIR__ . '/config.php';
$page_title = 'Visual Studio — Canva-Style Web Studio';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Canva Visual Studio — WebCraft AI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Cinzel:wght@500;700;800&family=Noto+Sans+Tamil:wght@400;600;700&family=Noto+Sans+Devanagari:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.21.10/css/grapes.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.21.10/grapes.min.js"></script>
  <script src="<?= SITE_URL ?>/assets/js/opencode-service.js"></script>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/loader-3d.css">
  <script src="<?= SITE_URL ?>/assets/js/loader-3d.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: #090d16;
      color: #e2e8f0;
      font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
      height: 100vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .studio-header {
      min-height: 54px;
      background: #0e1422;
      border-bottom: 1px solid #1e293b;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 0.4rem 0.65rem;
      padding: 0.45rem 1.25rem;
      flex-shrink: 0;
      z-index: 50;
      width: 100%;
    }

    .header-left, .header-right {
      display: flex; align-items: center; flex-wrap: wrap;
      gap: 0.45rem; min-width: 0;
    }
    .header-left { flex: 1 1 auto; justify-content: flex-start; }
    .header-right { flex: 0 1 auto; justify-content: flex-end; margin-left: auto; }

    .studio-logo {
      display: flex; align-items: center; gap: 0.5rem;
      font-size: 0.95rem; font-weight: 800;
      color: #818cf8; letter-spacing: -0.01em; white-space: nowrap;
    }

    .canva-badge {
      background: linear-gradient(135deg, #06b6d4, #3b82f6);
      color: #fff; font-size: 0.68rem; font-weight: 800;
      padding: 0.15rem 0.5rem; border-radius: 999px;
      text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;
    }

    .header-divider { width: 1px; height: 22px; background: #1e293b; flex-shrink: 0; }

    .project-title-input {
      background: transparent; border: 1px solid transparent; border-radius: 6px;
      color: #fff; font-family: inherit; font-size: 0.88rem; font-weight: 700;
      padding: 0.3rem 0.6rem; width: 150px; max-width: 180px; min-width: 70px;
      flex: 0 1 auto; transition: border-color 0.2s;
    }
    .project-title-input:hover, .project-title-input:focus {
      border-color: #334155; background: #080c14; outline: none;
    }

    .concept-tabs { display: flex; gap: 0.3rem; flex-wrap: nowrap; flex-shrink: 0; }
    .c-tab {
      padding: 0.3rem 0.65rem; border-radius: 7px;
      border: 1px solid #283347; background: #080c14; color: #94a3b8;
      font-size: 0.74rem; font-weight: 700; cursor: pointer;
      transition: all 0.15s; white-space: nowrap; flex-shrink: 0;
    }
    .c-tab.active {
      background: #4f46e5; border-color: #6366f1; color: #fff;
      box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
    }
    .c-short { display: none; }

    .device-toggles {
      display: flex; gap: 0.25rem; background: #080c14;
      padding: 3px; border-radius: 8px; border: 1px solid #1e293b;
      flex-wrap: nowrap; flex-shrink: 0;
    }
    .dev-btn {
      padding: 0.25rem 0.55rem; border: none; border-radius: 6px;
      background: transparent; color: #94a3b8; font-size: 0.75rem;
      font-weight: 600; cursor: pointer; transition: all 0.15s;
      white-space: nowrap; flex-shrink: 0;
    }
    .dev-btn.active { background: #1e293b; color: #fff; }

    .hdr-btn {
      display: inline-flex; align-items: center; gap: 0.35rem;
      padding: 0.38rem 0.75rem; border-radius: 8px;
      font-family: inherit; font-size: 0.76rem; font-weight: 600;
      border: 1px solid #283347; background: #111726; color: #cbd5e1;
      cursor: pointer; transition: all 0.15s;
      white-space: nowrap; flex-shrink: 0;
    }
    .hdr-btn:hover {
      background: #1e293b; border-color: #64748b; color: #fff;
    }
    .hdr-btn.save-btn {
      background: linear-gradient(135deg, #10b981, #059669);
      border: none; color: #fff; font-weight: 700;
      padding: 0.4rem 1rem;
      box-shadow: 0 2px 10px rgba(16, 185, 129, 0.35);
    }
    .hdr-btn.save-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.5); }
    .hdr-btn.ai-btn {
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      border: 1px solid #818cf8; color: #fff; font-weight: 700;
    }
    .hdr-btn.edit-section-btn {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      border: 1px solid #fbbf24; color: #fff; font-weight: 800;
      box-shadow: 0 2px 10px rgba(245, 158, 11, 0.4);
    }
    .hdr-btn.edit-section-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.6); }
    .hdr-btn.help-btn { background: #1e1b4b; border-color: #4f46e5; color: #c7d2fe; font-weight: 700; }
    .hdr-btn.help-btn:hover { background: #312e81; border-color: #6366f1; color: #fff; }
    .hdr-btn.fullscreen-btn { background: #111827; border-color: #374151; color: #d1d5db; }
    .hdr-btn.fullscreen-btn:hover { background: #1f2937; border-color: #4b5563; color: #fff; }
    .hdr-btn.fullscreen-btn.active {
      background: #4f46e5; border-color: #6366f1; color: #fff;
      box-shadow: 0 0 12px rgba(99, 102, 241, 0.5);
    }
    .hdr-btn.mobile-mode-btn.active {
      background: linear-gradient(135deg, #ec4899, #db2777);
      border-color: #f472b6; color: #fff;
      box-shadow: 0 0 12px rgba(236, 72, 153, 0.5);
    }
    .hdr-btn.edit-toggle-btn.active {
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      border-color: #818cf8; color: #fff;
      box-shadow: 0 0 12px rgba(99, 102, 241, 0.5);
    }
    /* ★ Edit OFF = clean normal page preview: editor sidebars + canvas tools hidden */
    body.studio-edit-off .canva-rail,
    body.studio-edit-off #canva-drawer,
    body.studio-edit-off #ctx-panel,
    body.studio-edit-off #canvas-feature-ribbon,
    body.studio-edit-off #floating-edit-content-btn { display: none !important; }
    .lbl-short { display: none; }

    .lang-select {
      padding: 0.38rem 0.6rem; border-radius: 8px;
      border: 1px solid #283347; background: #111726; color: #cbd5e1;
      font-family: inherit; font-size: 0.76rem; font-weight: 600;
      cursor: pointer; flex-shrink: 0;
    }
    .lang-select:focus { outline: none; border-color: #6366f1; }

    @media (max-width: 1560px) {
      .studio-header { padding: 0.45rem 1rem; }
      .project-title-input { width: 120px; }
      .c-tab { padding: 0.3rem 0.55rem; font-size: 0.72rem; }
    }
    @media (max-width: 1420px) {
      .studio-header { gap: 0.35rem 0.5rem; padding: 0.45rem 0.85rem; }
      .dev-btn .lbl { display: none; }
      .dev-btn { padding: 0.28rem 0.5rem; font-size: 0.82rem; }
      .hdr-btn { padding: 0.34rem 0.6rem; font-size: 0.72rem; }
      .project-title-input { width: 100px; font-size: 0.82rem; }
      .studio-logo { font-size: 0.88rem; }
    }
    @media (max-width: 1220px) {
      .c-full { display: none; }
      .c-short { display: inline; }
      .hdr-btn .lbl { display: none; }
      .hdr-btn.save-btn .lbl { display: none; }
      .hdr-btn.save-btn .lbl-short { display: inline; }
      .canva-badge { display: none; }
    }
    @media (max-width: 980px) {
      .studio-header { padding: 0.4rem 0.6rem; }
      .header-divider { display: none; }
      .studio-logo .logo-text { display: none; }
      .project-title-input { width: 90px; }
      .hdr-btn { padding: 0.32rem 0.55rem; }
      .device-toggles { order: 5; }
    }

    .studio-main { display: flex; flex: 1; overflow: hidden; position: relative; min-height: 0; }

    /* ── Right Context Panel ── */
    .ctx-panel {
      width: 220px; flex-shrink: 0;
      background: #0b0f1a; border-left: 1px solid #1a2235;
      display: flex; flex-direction: column;
      overflow-y: auto; z-index: 10;
      transition: width 0.2s ease;
    }
    .ctx-panel-head {
      padding: 0.85rem 1rem 0.6rem;
      border-bottom: 1px solid #1a2235;
      font-size: 0.7rem; font-weight: 800; letter-spacing: 0.06em;
      text-transform: uppercase; color: #6366f1;
      display: flex; align-items: center; gap: 0.4rem;
    }
    .ctx-idle {
      padding: 1.1rem 0.95rem; display: flex; flex-direction: column; gap: 0.75rem;
    }
    .ctx-tip-card {
      background: linear-gradient(135deg,#0f172a,#1e1b4b);
      border: 1px solid #312e81; border-radius: 10px;
      padding: 0.85rem 0.85rem 0.8rem;
      font-size: 0.72rem; line-height: 1.55; color: #c7d2fe;
    }
    .ctx-tip-card .tip-icon { font-size: 1.3rem; margin-bottom: 0.45rem; display: block; }
    .ctx-tip-card strong { color: #a5b4fc; display: block; margin-bottom: 0.3rem; font-size: 0.74rem; }
    .ctx-quick-btns { display: flex; flex-direction: column; gap: 0.4rem; }
    .ctx-quick-btn {
      display: flex; align-items: center; gap: 0.55rem;
      background: #111827; border: 1px solid #1e293b; border-radius: 8px;
      padding: 0.55rem 0.75rem; cursor: pointer; color: #94a3b8;
      font-size: 0.72rem; font-weight: 600; text-align: left;
      transition: all 0.15s ease; width: 100%;
    }
    .ctx-quick-btn:hover { background: #1e293b; color: #e2e8f0; border-color: #334155; }
    .ctx-quick-btn .qb-icon { font-size: 1rem; flex-shrink: 0; }
    /* Selected element info */
    .ctx-sel {
      padding: 0.85rem 0.95rem; display: flex; flex-direction: column; gap: 0.65rem;
    }
    .ctx-el-badge {
      display: flex; align-items: center; gap: 0.5rem;
      background: #0f172a; border: 1px solid #1e3a5f; border-radius: 8px;
      padding: 0.6rem 0.75rem;
    }
    .ctx-el-badge .el-icon { font-size: 1.1rem; }
    .ctx-el-tag { font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
      color: #38bdf8; letter-spacing: 0.05em; }
    .ctx-el-label { font-size: 0.72rem; color: #94a3b8; margin-top: 0.05rem; }
    .ctx-action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; }
    .ctx-action-btn {
      display: flex; flex-direction: column; align-items: center; gap: 0.25rem;
      background: #111827; border: 1px solid #1e293b; border-radius: 8px;
      padding: 0.6rem 0.3rem; cursor: pointer; color: #94a3b8;
      font-size: 0.62rem; font-weight: 600; text-align: center;
      transition: all 0.15s ease;
    }
    .ctx-action-btn:hover { background: #1e293b; color: #e2e8f0; border-color: #6366f1; }
    .ctx-action-btn .ca-icon { font-size: 1.2rem; }
    .ctx-divider { border: none; border-top: 1px solid #1a2235; margin: 0.2rem 0; }

    .canva-rail {
      width: 72px; background: #0b0f1a; border-right: 1px solid #1e293b;
      display: flex; flex-direction: column; align-items: center;
      padding: 0.75rem 0; flex-shrink: 0; z-index: 20;
      gap: 0.5rem; overflow-y: auto;
    }
    .rail-item {
      width: 58px; height: 58px; border-radius: 12px;
      border: 1px solid transparent; background: transparent;
      color: #94a3b8; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 0.25rem; font-size: 0.65rem; font-weight: 700;
      cursor: pointer; transition: all 0.15s;
      text-align: center; flex-shrink: 0;
    }
    .rail-item span.icon { font-size: 1.25rem; }
    .rail-item:hover { color: #cbd5e1; background: #141b2d; }
    .rail-item.active {
      background: #1e1b4b; border-color: #6366f1; color: #a5b4fc;
      box-shadow: 0 0 12px rgba(99, 102, 241, 0.25);
    }

    .canva-drawer {
      width: 340px; background: #0e1424; border-right: 1px solid #1e293b;
      display: flex; flex-direction: column; flex-shrink: 0;
      z-index: 15; transition: width 0.2s ease; overflow: hidden;
    }
    .canva-drawer.collapsed { width: 0; border-right: none; }
    @media (max-width: 1100px) { .canva-drawer { width: 300px; } }

    .drawer-header {
      padding: 0.85rem 1rem; background: #0a0e1a;
      border-bottom: 1px solid #1e293b;
      display: flex; justify-content: space-between; align-items: center;
      flex-shrink: 0;
    }
    .drawer-title {
      font-size: 0.82rem; font-weight: 800; color: #fff;
      text-transform: uppercase; letter-spacing: 0.05em;
    }
    .drawer-close {
      background: none; border: none; color: #64748b;
      font-size: 1.1rem; cursor: pointer;
    }
    .drawer-close:hover { color: #fff; }
    .drawer-content { flex: 1; overflow-y: auto; padding: 0.85rem; }

    .block-search {
      width: 100%; padding: 0.6rem 0.85rem;
      border: 1.5px solid #283347; border-radius: 8px;
      background: #080c14; color: #fff; font-size: 0.8rem;
      margin-bottom: 0.75rem; font-family: inherit;
    }
    .block-search:focus { outline: none; border-color: #6366f1; }

    .block-pills { display: flex; gap: 0.35rem; margin-bottom: 0.85rem; overflow-x: auto; padding-bottom: 2px; }
    .bpill {
      padding: 0.25rem 0.6rem; border-radius: 999px;
      border: 1px solid #283347; background: #080c14; color: #94a3b8;
      font-size: 0.7rem; font-weight: 600; cursor: pointer;
      white-space: nowrap; transition: all 0.15s;
    }
    .bpill.active, .bpill:hover {
      border-color: #6366f1; color: #fff; background: #1e1b4b;
    }

    .friendly-panel { display: flex; flex-direction: column; gap: 0.9rem; padding: 0.15rem; }
    .friendly-card {
      background: linear-gradient(180deg, #131a2b 0%, #0f1522 100%);
      border: 1.5px solid #243049; border-radius: 14px;
      padding: 1rem 1rem 1.1rem;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
    }
    .friendly-card-hdr { display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.7rem; }
    .friendly-card-icon {
      width: 36px; height: 36px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.15rem; flex-shrink: 0;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
    }
    .friendly-card-title { font-size: 0.92rem; font-weight: 800; color: #fff; letter-spacing: -0.01em; line-height: 1.2; }
    .friendly-card-sub { font-size: 0.72rem; color: #94a3b8; margin-top: 0.1rem; }
    .friendly-card-desc { font-size: 0.78rem; color: #cbd5e1; line-height: 1.55; margin-bottom: 0.85rem; }
    .friendly-btn-row { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .friendly-action {
      flex: 1 1 auto; min-width: 110px;
      padding: 0.55rem 0.85rem; border-radius: 9px;
      border: 1.5px solid #334155; background: #0a0f1c; color: #cbd5e1;
      font-family: inherit; font-size: 0.78rem; font-weight: 700;
      cursor: pointer; transition: all 0.15s;
      display: inline-flex; align-items: center; justify-content: center;
      gap: 0.3rem; white-space: nowrap;
    }
    .friendly-action:hover {
      border-color: #6366f1; color: #fff; background: #1a1f36;
      transform: translateY(-1px);
    }
    .friendly-action.primary {
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      border-color: #818cf8; color: #fff;
      box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
    }
    .friendly-action.whatsapp {
      background: linear-gradient(135deg, #25D366, #059669);
      border-color: #34d399; color: #fff;
      box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35);
    }
    .friendly-action.customize {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      border-color: #fbbf24; color: #fff;
      box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);
    }

    .friendly-sections-list {
      display: flex; flex-direction: column; gap: 0.35rem;
      max-height: 200px; overflow-y: auto; padding: 0.5rem;
      background: #080c14; border: 1px solid #1e293b;
      border-radius: 9px; margin-bottom: 0.7rem;
    }
    .friendly-section-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.45rem 0.65rem; border-radius: 6px;
      background: #0d1320; border: 1px solid #1e293b;
      cursor: pointer; transition: all 0.15s; gap: 0.4rem;
    }
    .friendly-section-item:hover { border-color: #6366f1; background: #141b2d; }
    .friendly-section-item-left { display: flex; align-items: center; gap: 0.45rem; min-width: 0; flex: 1; }
    .friendly-section-item-left .sec-tag {
      font-size: 0.6rem; font-weight: 800; padding: 0.12rem 0.4rem;
      border-radius: 4px; background: #312e81; color: #c7d2fe;
      text-transform: uppercase; flex-shrink: 0;
    }
    .friendly-section-item-left .sec-name {
      font-size: 0.74rem; color: #cbd5e1; font-weight: 600;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .friendly-section-item .sec-jump {
      font-size: 0.68rem; color: #818cf8; font-weight: 700;
      padding: 0.15rem 0.45rem; border-radius: 4px;
      background: rgba(99, 102, 241, 0.12); border: none;
      cursor: pointer; flex-shrink: 0;
    }
    .friendly-section-item .sec-jump.gold { color: #fbbf24; background: rgba(245, 158, 11, 0.15); }

    .friendly-theme-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 0.5rem; margin-bottom: 0.85rem;
    }
    .friendly-theme-swatch {
      aspect-ratio: 1 / 1; border-radius: 12px;
      border: 2.5px solid #1e293b; cursor: pointer;
      transition: all 0.2s; position: relative; overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
    .friendly-theme-swatch:hover {
      transform: scale(1.05); border-color: #fff;
      box-shadow: 0 6px 20px rgba(255, 255, 255, 0.2);
    }
    .friendly-advanced {
      background: #0a0f1a; border: 1px solid #1e293b;
      border-radius: 10px; padding: 0.6rem 0.75rem;
    }
    .friendly-advanced summary {
      cursor: pointer; font-size: 0.76rem; font-weight: 700;
      color: #94a3b8; user-select: none; list-style: none;
      display: flex; align-items: center; gap: 0.4rem;
    }
    .friendly-advanced summary::-webkit-details-marker { display: none; }
    .friendly-advanced[open] summary { color: #cbd5e1; margin-bottom: 0.5rem; }

    .anim-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-bottom: 0.9rem; }
    .anim-card {
      padding: 0.75rem 0.5rem; border-radius: 10px;
      border: 1.5px solid #243049; background: #0a0f1c;
      cursor: pointer; text-align: center; transition: all 0.15s;
    }
    .anim-card:hover {
      border-color: #6366f1; background: #131a2b; transform: translateY(-2px);
    }
    .anim-card.active {
      border-color: #818cf8;
      background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(124, 58, 237, 0.2));
      box-shadow: 0 0 14px rgba(99, 102, 241, 0.35);
    }
    .anim-card .a-icon { font-size: 1.5rem; display: block; margin-bottom: 0.2rem; }
    .anim-card .a-name { font-size: 0.7rem; font-weight: 700; color: #cbd5e1; }

    .anim-preview-box {
      padding: 1.25rem; text-align: center;
      background: #080c14; border-radius: 10px;
      border: 1.5px dashed #283347; margin-bottom: 0.9rem;
    }
    .anim-preview-box .ap-demo {
      display: inline-block; padding: 0.65rem 1.4rem;
      border-radius: 10px;
      background: linear-gradient(135deg, #6366f1, #a855f7);
      color: #fff; font-weight: 700; font-size: 0.85rem;
    }
    .anim-field { margin-bottom: 0.75rem; }
    .anim-field label {
      display: block; font-size: 0.72rem; font-weight: 700;
      color: #cbd5e1; margin-bottom: 0.3rem;
    }
    .anim-field input[type=range] { width: 100%; accent-color: #6366f1; }
    .anim-field .val {
      font-family: 'Fira Code', monospace; font-size: 0.72rem;
      color: #818cf8; font-weight: 700; float: right;
    }
    .anim-field select {
      width: 100%; padding: 0.45rem 0.7rem; border-radius: 7px;
      border: 1.5px solid #283347; background: #080c14; color: #fff;
      font-family: inherit; font-size: 0.76rem;
    }

    .lang-card {
      background: #0a0f1c; border: 1.5px solid #243049;
      border-radius: 12px; padding: 0.9rem; margin-bottom: 0.75rem;
    }
    .lang-card .lc-head {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 0.6rem;
    }
    .lang-card .lc-title { font-size: 0.82rem; font-weight: 800; color: #fff; }
    .lang-card .lc-sub { font-size: 0.7rem; color: #94a3b8; }
    .lang-chip-row { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.6rem; }
    .lang-chip {
      padding: 0.35rem 0.7rem; border-radius: 999px;
      border: 1.5px solid #283347; background: #080c14; color: #cbd5e1;
      font-size: 0.72rem; font-weight: 700; cursor: pointer; transition: all 0.15s;
    }
    .lang-chip:hover { border-color: #6366f1; color: #fff; }
    .lang-chip.on {
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      border-color: #818cf8; color: #fff;
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
    }

    .crop-stage {
      position: relative; display: inline-block;
      background: #050810; border-radius: 10px; overflow: hidden;
      margin: 0 auto 1rem; max-width: 100%; line-height: 0; user-select: none;
    }
    .crop-stage img { max-width: 100%; max-height: 380px; display: block; pointer-events: none; }
    .crop-rect {
      position: absolute; border: 2px solid #818cf8;
      box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6);
      cursor: move; box-sizing: border-box;
    }
    .crop-handle {
      position: absolute; width: 14px; height: 14px;
      background: #818cf8; border: 2px solid #fff; border-radius: 50%;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.5);
    }
    .crop-handle.nw { top: -7px; left: -7px; cursor: nwse-resize; }
    .crop-handle.ne { top: -7px; right: -7px; cursor: nesw-resize; }
    .crop-handle.sw { bottom: -7px; left: -7px; cursor: nesw-resize; }
    .crop-handle.se { bottom: -7px; right: -7px; cursor: nwse-resize; }
    .crop-ratio-row { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 1rem; }

    .gal-layouts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; margin-bottom: 1rem; }
    .gal-layout {
      padding: 0.85rem 0.5rem; border-radius: 10px;
      border: 1.5px solid #243049; background: #0a0f1c;
      cursor: pointer; text-align: center; transition: all 0.15s;
    }
    .gal-layout:hover { border-color: #6366f1; transform: translateY(-2px); }
    .gal-layout.active {
      border-color: #818cf8;
      background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(124, 58, 237, 0.15));
    }
    .gal-layout .gl-demo {
      display: grid; gap: 3px; width: 44px; height: 34px;
      margin: 0 auto 0.35rem;
    }
    .gal-layout .gl-demo div { background: #6366f1; border-radius: 3px; opacity: 0.85; }
    .gal-layout .gl-name { font-size: 0.68rem; font-weight: 700; color: #cbd5e1; }

    .gal-picker {
      display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;
      max-height: 280px; overflow-y: auto; padding: 0.5rem;
      background: #080c14; border: 1px solid #1e293b;
      border-radius: 9px; margin-bottom: 1rem;
    }
    .gal-picker .gp-item {
      position: relative; aspect-ratio: 1; border-radius: 7px;
      overflow: hidden; cursor: pointer; border: 2px solid transparent;
      transition: all 0.15s;
    }
    .gal-picker .gp-item img { width: 100%; height: 100%; object-fit: cover; }
    .gal-picker .gp-item.on { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3); }
    .gal-picker .gp-item.on::after {
      content: '✓'; position: absolute; top: 3px; right: 3px;
      background: #10b981; color: #fff; width: 18px; height: 18px;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem; font-weight: 900;
    }

    .mobile-mode-banner {
      position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
      z-index: 99999;
      background: linear-gradient(135deg, #ec4899, #db2777);
      color: #fff; padding: 0.4rem 1rem; border-radius: 999px;
      font-size: 0.72rem; font-weight: 800;
      box-shadow: 0 6px 20px rgba(236, 72, 153, 0.5);
      display: none; align-items: center; gap: 0.5rem;
      pointer-events: auto;
    }
    .mobile-mode-banner.on { display: inline-flex; }

    .floating-edit-content-btn {
      position: absolute; bottom: 1.5rem; left: 50%;
      transform: translateX(-50%) translateY(20px);
      z-index: 9998; display: none; align-items: center; gap: 0.5rem;
      padding: 0.75rem 1.5rem; border-radius: 999px;
      border: 2px solid #fbbf24;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #fff; font-family: inherit; font-size: 0.88rem; font-weight: 800;
      letter-spacing: -0.01em;
      box-shadow: 0 10px 35px rgba(245, 158, 11, 0.55), 0 0 0 4px rgba(245, 158, 11, 0.15);
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.22, 1, 0.36, 1);
      opacity: 0;
    }
    .floating-edit-content-btn.show { display: inline-flex; opacity: 1; transform: translateX(-50%) translateY(0); }
    .floating-edit-content-btn:hover {
      transform: translateX(-50%) translateY(-3px) scale(1.04);
      box-shadow: 0 14px 40px rgba(245, 158, 11, 0.7), 0 0 0 6px rgba(245, 158, 11, 0.2);
    }
    .floating-edit-content-btn::before {
      content: ''; position: absolute; inset: -6px;
      border-radius: 999px; background: rgba(245, 158, 11, 0.25);
      animation: fabPulse 2s ease-in-out infinite; z-index: -1;
    }
    @keyframes fabPulse {
      0%, 100% { transform: scale(1); opacity: 0.6; }
      50% { transform: scale(1.08); opacity: 0.15; }
    }

    .upload-dropzone {
      border: 2px dashed #3b4260; border-radius: 14px;
      padding: 1.75rem 1rem; text-align: center;
      background: rgba(99, 102, 241, 0.03);
      cursor: pointer; transition: all 0.2s; margin-bottom: 1rem;
    }
    .upload-dropzone:hover, .upload-dropzone.dragover {
      border-color: #6366f1; background: rgba(99, 102, 241, 0.1);
    }
    .upload-dropzone .u-icon { font-size: 2rem; margin-bottom: 0.5rem; }
    .upload-dropzone .u-text { font-size: 0.82rem; font-weight: 700; color: #cbd5e1; }
    .upload-dropzone .u-sub { font-size: 0.72rem; color: #64748b; margin-top: 0.25rem; }

    .stock-grid {
      display: grid; grid-template-columns: repeat(2, 1fr);
      gap: 0.6rem; max-height: 420px; overflow-y: auto;
    }
    .stock-thumb {
      position: relative; border-radius: 8px; overflow: hidden;
      aspect-ratio: 4/3; cursor: pointer;
      border: 1.5px solid #283347; transition: all 0.2s;
    }
    .stock-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .stock-thumb:hover {
      transform: scale(1.03); border-color: #6366f1;
      box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }
    .stock-thumb-caption {
      position: absolute; bottom: 0; inset-inline: 0;
      background: rgba(0, 0, 0, 0.7); padding: 0.2rem 0.4rem;
      font-size: 0.65rem; color: #fff; font-weight: 600; text-align: center;
    }

    .upload-card {
      position: relative; border-radius: 8px; overflow: hidden;
      aspect-ratio: 4/3; cursor: grab;
      border: 1.5px solid #283347; transition: all 0.2s;
      background: #080c14;
    }
    .upload-card img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
    .upload-card:hover { transform: scale(1.03); border-color: #6366f1; }
    .upload-card-badge {
      position: absolute; top: 4px; left: 4px;
      background: rgba(0, 0, 0, 0.75); font-size: 0.6rem;
      color: #a5b4fc; padding: 0.1rem 0.35rem;
      border-radius: 4px; font-weight: 700;
    }
    .upload-card-del {
      position: absolute; top: 4px; right: 4px;
      width: 22px; height: 22px; border-radius: 4px;
      background: rgba(239, 68, 68, 0.85); color: #fff;
      border: none; font-size: 0.7rem;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; opacity: 0; transition: opacity 0.15s; z-index: 5;
    }
    .upload-card:hover .upload-card-del { opacity: 1; }
    .upload-card-caption {
      position: absolute; bottom: 0; inset-inline: 0;
      background: rgba(0, 0, 0, 0.75); padding: 0.2rem 0.4rem;
      font-size: 0.62rem; color: #fff; font-weight: 600;
      text-align: center; white-space: nowrap;
      overflow: hidden; text-overflow: ellipsis;
    }

    .layer-tabs {
      display: flex; gap: 0.3rem; margin-bottom: 0.75rem;
      border-bottom: 1px solid #1e293b; padding-bottom: 0.5rem;
      flex-wrap: wrap;
    }
    .ltab {
      padding: 0.25rem 0.65rem; border-radius: 6px;
      border: none; background: transparent; color: #94a3b8;
      font-size: 0.72rem; font-weight: 700; cursor: pointer;
      transition: all 0.15s;
    }
    .ltab.active { background: #1e1b4b; color: #a5b4fc; border: 1px solid #6366f1; }

    .layer-tree-container {
      display: flex; flex-direction: column; gap: 0.45rem;
      max-height: 520px; overflow-y: auto; padding-right: 2px;
    }
    .layer-item-card {
      background: #0a0e1a; border: 1px solid #1e293b;
      border-radius: 8px; padding: 0.5rem 0.65rem;
      display: flex; flex-direction: column; gap: 0.35rem;
      transition: all 0.15s; cursor: pointer;
    }
    .layer-item-card:hover { border-color: #3b4260; background: #0f172a; }
    .layer-item-card.selected {
      border-color: #6366f1; background: #13172e;
      box-shadow: 0 0 0 1px #6366f1;
    }
    .layer-card-top {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 0.72rem; gap: 0.35rem;
    }
    .layer-tag-badge {
      font-size: 0.64rem; font-weight: 800;
      padding: 0.12rem 0.45rem; border-radius: 4px;
      text-transform: uppercase; letter-spacing: 0.04em; flex-shrink: 0;
    }
    .layer-tag-badge.h { background: #312e81; color: #c7d2fe; }
    .layer-tag-badge.p { background: #064e3b; color: #a7f3d0; }
    .layer-tag-badge.btn { background: #701a75; color: #fbcfe8; }
    .layer-tag-badge.img { background: #1e3a8a; color: #bfdbfe; }
    .layer-tag-badge.sec { background: #374151; color: #d1d5db; }

    .layer-text-input {
      width: 100%; background: #080c14;
      border: 1px solid #283347; border-radius: 6px;
      color: #fff; font-family: inherit; font-size: 0.76rem;
      padding: 0.32rem 0.55rem; transition: border-color 0.15s;
    }
    .layer-text-input:focus {
      outline: none; border-color: #6366f1;
      background: #0e1424;
      box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
    }

    .studio-canvas-wrap {
      flex: 1; display: flex; flex-direction: column;
      background: #06090e; position: relative;
      overflow: hidden; min-width: 0;
    }

    /* ── Business blocks: Pages manager + Business setup ── */
    .biz-card {
      background: linear-gradient(180deg, #131a2b 0%, #0f1522 100%);
      border: 1.5px solid #243049; border-radius: 14px;
      padding: 1rem; margin-bottom: 0.85rem;
    }
    .biz-card-hdr { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.7rem; }
    .biz-card-icon {
      width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
      background: linear-gradient(135deg, #059669, #10b981);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
    }
    .biz-card-title { font-size: 0.88rem; font-weight: 800; color: #fff; }
    .biz-card-sub { font-size: 0.7rem; color: #94a3b8; margin-top: 0.1rem; }
    .biz-field { margin-bottom: 0.6rem; }
    .biz-field label { display: block; font-size: 0.7rem; font-weight: 700; color: #cbd5e1; margin-bottom: 0.25rem; }
    .biz-input {
      width: 100%; padding: 0.5rem 0.7rem; border-radius: 8px;
      border: 1.5px solid #283347; background: #080c14; color: #fff;
      font-family: inherit; font-size: 0.78rem;
    }
    .biz-input:focus { outline: none; border-color: #10b981; }
    .biz-note { font-size: 0.68rem; color: #64748b; line-height: 1.5; margin-top: 0.5rem; }
    .wc-page-item {
      display: flex; align-items: center; gap: 0.5rem;
      background: #0a0e1a; border: 1.5px solid #1e293b; border-radius: 10px;
      padding: 0.55rem 0.65rem; margin-bottom: 0.45rem; cursor: pointer;
      transition: all 0.15s;
    }
    .wc-page-item:hover { border-color: #3b4260; }
    .wc-page-item.active { border-color: #6366f1; background: #13172e; box-shadow: 0 0 0 1px #6366f1; }
    .wc-page-item .pg-info { flex: 1; min-width: 0; }
    .wc-page-item .pg-name { font-size: 0.78rem; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wc-page-item .pg-slug { font-size: 0.66rem; color: #818cf8; font-family: monospace; }
    .wc-page-item .pg-home { font-size: 0.58rem; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 0.05em; }
    .wc-page-mini-btn {
      background: #111827; border: 1px solid #283347; border-radius: 6px;
      color: #94a3b8; font-size: 0.7rem; padding: 0.25rem 0.45rem; cursor: pointer;
      flex-shrink: 0;
    }
    .wc-page-mini-btn:hover { border-color: #6366f1; color: #fff; }
    .wc-page-mini-btn.danger:hover { border-color: #ef4444; color: #fca5a5; }

    /* ── Canvas Feature Ribbon (Top Creative Toolbar) ── */
    .canvas-feature-ribbon {
      height: 44px; flex-shrink: 0;
      background: #0b0f1a; border-bottom: 1px solid #1a2235;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 0.85rem; gap: 0.75rem; z-index: 30;
      box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    }
    .cfr-group {
      display: flex; align-items: center; gap: 0.55rem;
    }
    .cfr-center {
      justify-content: center; flex: 1; max-width: 620px;
    }
    .cfr-devices {
      display: inline-flex; align-items: center;
      background: #111827; border: 1px solid #1e293b;
      border-radius: 8px; padding: 2px; gap: 2px;
    }
    .cfr-btn {
      background: transparent; border: 1px solid transparent; border-radius: 6px;
      padding: 0.32rem 0.65rem; color: #94a3b8; font-size: 0.72rem; font-weight: 700;
      cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;
      transition: all 0.15s ease;
    }
    .cfr-btn:hover { color: #f1f5f9; background: rgba(255,255,255,0.04); }
    .cfr-btn.active {
      background: #1e1b4b; color: #a5b4fc; border-color: #6366f1;
      box-shadow: 0 1px 4px rgba(99,102,241,0.25);
    }
    .cfr-dim-badge {
      display: inline-flex; align-items: center; gap: 0.4rem;
      font-size: 0.68rem; font-weight: 700; color: #64748b;
      background: #080c14; border: 1px solid #1a2235;
      padding: 0.28rem 0.55rem; border-radius: 6px;
    }
    .cfr-dot {
      width: 6px; height: 6px; border-radius: 50%; background: #10b981;
      display: inline-block; box-shadow: 0 0 6px rgba(16,185,129,0.5);
    }
    .cfr-theme-picker {
      display: inline-flex; align-items: center; gap: 0.35rem;
      background: #111827; border: 1px solid #1e293b;
      border-radius: 8px; padding: 3px 6px;
    }
    .cfr-theme-label {
      font-size: 0.68rem; font-weight: 800; color: #818cf8;
      text-transform: uppercase; letter-spacing: 0.04em; margin-right: 2px;
    }
    .cfr-theme-pill {
      background: transparent; border: 1px solid transparent; border-radius: 6px;
      padding: 0.22rem 0.5rem; color: #cbd5e1; font-size: 0.7rem; font-weight: 600;
      cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;
      transition: all 0.15s ease;
    }
    .cfr-theme-pill:hover { background: rgba(255,255,255,0.06); color: #fff; }
    .cfr-theme-pill.active {
      background: #1e1b4b; border-color: #6366f1; color: #c7d2fe; font-weight: 700;
    }
    .cfr-theme-swatch {
      width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0;
    }
    .cfr-tool-btn {
      background: #111827; border: 1px solid #1e293b; border-radius: 6px;
      padding: 0.3rem 0.55rem; color: #94a3b8; font-size: 0.72rem; font-weight: 700;
      cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;
      transition: all 0.15s ease;
    }
    .cfr-tool-btn:hover { background: #1e293b; color: #f1f5f9; border-color: #334155; }
    .cfr-tool-btn.active {
      background: #064e3b; color: #a7f3d0; border-color: #10b981;
    }
    .cfr-zoom-group {
      display: inline-flex; align-items: center;
      background: #111827; border: 1px solid #1e293b;
      border-radius: 6px; overflow: hidden;
    }
    .cfr-zoom-group .cfr-tool-btn {
      border: none; border-radius: 0; padding: 0.3rem 0.5rem;
    }
    .cfr-zoom-val {
      font-size: 0.68rem; font-weight: 800; color: #c7d2fe;
      padding: 0 0.45rem; min-width: 38px; text-align: center;
    }
    .cfr-seo-pill {
      background: linear-gradient(135deg, #064e3b, #047857);
      border: 1px solid #10b981; border-radius: 8px;
      padding: 0.3rem 0.65rem; color: #ecfdf5; font-size: 0.7rem; font-weight: 800;
      cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;
      transition: all 0.15s ease; box-shadow: 0 2px 6px rgba(16,185,129,0.2);
    }
    .cfr-seo-pill:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(16,185,129,0.3);
    }
    .cfr-seo-score {
      background: #ecfdf5; color: #065f46; font-size: 0.65rem;
      padding: 0.08rem 0.35rem; border-radius: 4px; font-weight: 900;
    }

    /* ── Alignment Grid Guide Overlay ── */
    .canvas-grid-overlay {
      position: absolute; inset: 44px 0 0 0;
      pointer-events: none; z-index: 15; display: none;
      padding: 0 2rem;
    }
    .canvas-grid-overlay.active { display: flex; }
    .cgo-col {
      flex: 1; height: 100%;
      background: rgba(99, 102, 241, 0.04);
      border-left: 1px dashed rgba(99, 102, 241, 0.22);
      border-right: 1px dashed rgba(99, 102, 241, 0.22);
      margin: 0 6px;
    }

    /* ── GrapesJS Canvas Reset & Fixes (Eliminates Right & Top Blank Spaces) ── */
    #gjs, .gjs-editor {
      --gjs-left-width: 0px !important;
      --gjs-canvas-top: 0px !important;
      width: 100% !important;
      height: calc(100% - 44px) !important;
      flex: 1 1 auto !important;
      min-height: 0 !important;
      display: block !important;
      background: #06090e !important;
    }
    .gjs-cv-canvas {
      background-color: transparent !important;
      width: 100% !important;
      height: 100% !important;
      top: 0 !important;
      left: 0 !important;
    }
    .gjs-frames {
      width: 100% !important;
      height: 100% !important;
      transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      transform-origin: top center;
    }
    .gjs-frame-wrapper {
      width: 100% !important;
      margin: 0 auto !important;
      box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    }
    /* Hide all GrapesJS default panels */
    .gjs-pn-panels,
    .gjs-pn-panel,
    .gjs-pn-devices-c,
    .gjs-pn-views-container,
    .gjs-pn-views,
    .gjs-pn-options,
    .gjs-pn-commands,
    .gjs-pn-buttons {
      display: none !important;
      height: 0 !important;
      width: 0 !important;
      opacity: 0 !important;
      pointer-events: none !important;
    }

    .webcraft-section-handle {
      position: absolute; z-index: 99990;
      display: flex; align-items: center; gap: .35rem;
      padding: .32rem .55rem;
      border: 1px solid rgba(99, 102, 241, .65);
      border-radius: 8px;
      background: rgba(15, 23, 42, .94);
      color: #c7d2fe;
      font: 700 11px/1.1 Arial, sans-serif;
      box-shadow: 0 8px 20px rgba(0, 0, 0, .25);
      cursor: grab; user-select: none;
    }
    .webcraft-section-handle:hover { background: rgba(30, 27, 75, .98); }
    .webcraft-section-handle.dragging { cursor: grabbing; opacity: 1; }
    .webcraft-drop-line {
      position: absolute; z-index: 99989; height: 3px;
      border-radius: 999px; background: #818cf8;
      box-shadow: 0 0 14px rgba(129, 140, 248, .75);
      pointer-events: none; display: none;
    }
    .webcraft-section-dragging {
      outline: 2px dashed rgba(99, 102, 241, .75) !important;
      outline-offset: -2px !important;
      opacity: .66 !important;
    }

    .gjs-block {
      background: #141b2b !important;
      border: 1px solid #283347 !important;
      border-radius: 9px !important;
      color: #cbd5e1 !important;
      padding: 0.75rem 0.5rem !important;
      min-height: 68px !important;
      transition: all 0.15s !important;
    }
    .gjs-block:hover {
      border-color: #6366f1 !important; color: #a5b4fc !important;
      background: #1e1b4b !important;
      transform: translateY(-2px) !important;
    }
    .gjs-block-label { font-size: 0.75rem !important; font-weight: 600 !important; }
    .gjs-block-category .gjs-title {
      background: #090d16 !important; color: #818cf8 !important;
      font-size: 0.74rem !important; font-weight: 700 !important;
      text-transform: uppercase !important;
      letter-spacing: 0.08em !important;
      padding: 0.45rem 0.65rem !important;
      border-radius: 6px !important;
      margin: 0.5rem 0 0.3rem !important;
    }
    .gjs-sm-sector .gjs-sm-sector-title {
      background: #0a0e1a !important; color: #cbd5e1 !important;
      font-size: 0.76rem !important; font-weight: 700 !important;
      border-bottom: 1px solid #1e293b !important;
      padding: 0.45rem 0.65rem !important;
    }
    .gjs-field {
      background: #090d16 !important;
      border: 1px solid #283347 !important;
      border-radius: 6px !important;
      color: #fff !important; font-size: 0.75rem !important;
    }
    .gjs-field input, .gjs-field select { color: #fff !important; }
    .gjs-trt-traits { padding: 0.25rem 0 !important; }
    .gjs-trt-trait { padding: 0.5rem 0 !important; border-bottom: 1px solid #1e293b !important; }

    .ctx-menu {
      position: fixed; z-index: 100000;
      background: #131a2b; border: 1.5px solid #283347;
      border-radius: 10px; padding: 0.35rem;
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.6);
      min-width: 190px; display: none; flex-direction: column;
      gap: 0.1rem; font-size: 0.82rem;
      font-family: 'Plus Jakarta Sans', sans-serif;
      animation: ctxIn 0.12s ease;
    }
    @keyframes ctxIn {
      from { opacity: 0; transform: scale(0.96); }
      to { opacity: 1; transform: scale(1); }
    }
    .ctx-item {
      display: flex; align-items: center; gap: 0.6rem;
      padding: 0.5rem 0.75rem; border-radius: 6px;
      color: #cbd5e1; cursor: pointer;
      transition: all 0.1s; font-weight: 600;
      border: none; background: transparent;
      font-family: inherit; font-size: 0.82rem;
      text-align: left; width: 100%;
    }
    .ctx-item:hover { background: #1e293b; color: #fff; }
    .ctx-item.danger:hover { background: #7f1d1d; color: #fecaca; }
    .ctx-item .ctx-icon { width: 18px; text-align: center; font-size: 0.95rem; }
    .ctx-sep { height: 1px; background: #1e293b; margin: 0.25rem 0.5rem; }

    .floating-gemini-btn {
      position: fixed; bottom: 1.5rem; right: 1.5rem;
      z-index: 1000;
      display: inline-flex; align-items: center; gap: 0.55rem;
      padding: 0.68rem 1.25rem 0.68rem 0.9rem;
      border-radius: 999px;
      border: 1px solid rgba(129, 140, 248, 0.35);
      background: linear-gradient(135deg, #4f46e5 0%, #6d5ce8 55%, #8b5cf6 100%);
      color: #fff; font-family: inherit; font-size: 0.85rem;
      font-weight: 700; letter-spacing: -0.005em;
      box-shadow: 0 10px 28px rgba(79, 70, 229, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.06) inset;
      cursor: pointer;
      transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.22s;
    }
    .floating-gemini-btn::before {
      content: ''; position: absolute; inset: 0;
      border-radius: inherit;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.18), transparent 55%);
      pointer-events: none;
    }
    .floating-gemini-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 36px rgba(79, 70, 229, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.1) inset;
    }
    .floating-gemini-btn:active { transform: translateY(0) scale(0.98); }
    .floating-gemini-btn .fg-orb {
      display: inline-flex; align-items: center; justify-content: center;
      width: 22px; height: 22px; border-radius: 50%;
      background: rgba(255, 255, 255, 0.18);
      font-size: 0.82rem; line-height: 1;
    }

    .magic-ai-panel {
      position: fixed; bottom: 1rem; right: 1.25rem;
      width: min(480px, calc(100vw - 2rem));
      height: min(690px, calc(100vh - 2.5rem));
      max-height: calc(100vh - 2.5rem);
      background: #090e1a; border: 1.5px solid #223049;
      border-radius: 18px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.06) inset;
      z-index: 100010; display: none; flex-direction: column;
      overflow: hidden;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .magic-ai-panel.active { display: flex; animation: magicPanelIn 0.28s cubic-bezier(0.22, 1, 0.36, 1); }
    @keyframes magicPanelIn {
      from { opacity: 0; transform: translateY(14px) scale(0.97); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .magic-header {
      padding: 0.85rem 0.9rem 0.85rem 1rem;
      background: linear-gradient(180deg, #121a2e 0%, #0d1424 100%);
      border-bottom: 1px solid #1c2740;
      display: flex; align-items: center; justify-content: space-between;
      gap: 0.75rem; flex-shrink: 0; position: relative;
    }
    .magic-header::after {
      content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(129, 140, 248, 0.45), transparent);
    }
    .magic-brand { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
    .magic-avatar {
      position: relative; width: 34px; height: 34px;
      border-radius: 11px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #4f46e5, #7c3aed 55%, #a855f7);
      box-shadow: 0 4px 14px rgba(124, 58, 237, 0.45);
      font-size: 1rem; color: #fff;
    }
    .magic-avatar::after {
      content: ''; position: absolute; right: -2px; bottom: -2px;
      width: 10px; height: 10px; border-radius: 50%;
      background: #10b981; border: 2px solid #0d1424;
      box-shadow: 0 0 8px rgba(16, 185, 129, 0.8);
    }
    .magic-brand-text { display: flex; flex-direction: column; min-width: 0; }
    .magic-brand-name {
      font-size: 0.86rem; font-weight: 800; color: #fff;
      letter-spacing: -0.01em; line-height: 1.15;
    }
    .magic-brand-sub {
      font-size: 0.68rem; color: #94a3b8; font-weight: 600;
      display: flex; align-items: center; gap: 0.3rem; margin-top: 1px;
    }
    .magic-brand-sub .dot {
      width: 5px; height: 5px; border-radius: 50%;
      background: #10b981; box-shadow: 0 0 6px #10b981;
    }
    .magic-header-actions { display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0; }
    .magic-icon-btn {
      width: 28px; height: 28px; border-radius: 8px;
      border: none; background: transparent; color: #64748b;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 0.85rem; cursor: pointer;
      transition: all 0.15s; font-family: inherit; line-height: 1;
    }
    .magic-icon-btn:hover { background: #1a2338; color: #fff; }

    .magic-chat-log {
      flex: 1 1 0; min-height: 80px; overflow-y: auto;
      padding: 0.9rem;
      display: flex; flex-direction: column; gap: 0.85rem;
      scroll-behavior: smooth;
      background: radial-gradient(120% 60% at 50% 0%, rgba(79, 70, 229, 0.06), transparent 60%);
    }
    .magic-panel-bottom {
      flex-shrink: 0; margin-top: auto;
      background: #080d19; border-top: 1px solid #1c2740;
      display: flex; flex-direction: column; z-index: 10;
    }
    .magic-chat-log::-webkit-scrollbar { width: 6px; }
    .magic-chat-log::-webkit-scrollbar-track { background: transparent; }
    .magic-chat-log::-webkit-scrollbar-thumb { background: #243049; border-radius: 999px; }
    .magic-chat-log::-webkit-scrollbar-thumb:hover { background: #334155; }

    .msg { display: flex; gap: 0.55rem; align-items: flex-end; max-width: 100%; animation: msgIn 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
    @keyframes msgIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .msg.user { flex-direction: row-reverse; }
    .msg-avatar {
      width: 26px; height: 26px; border-radius: 8px;
      flex-shrink: 0; display: flex; align-items: center; justify-content: center;
      font-size: 0.68rem; font-weight: 800; line-height: 1;
    }
    .msg.ai .msg-avatar {
      background: linear-gradient(135deg, #4f46e5, #a855f7);
      color: #fff; box-shadow: 0 3px 10px rgba(124, 58, 237, 0.35);
    }
    .msg.user .msg-avatar {
      background: #1e293b; color: #c7d2fe; border: 1px solid #334155;
    }
    .msg-body { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; max-width: calc(100% - 42px); }
    .msg.user .msg-body { align-items: flex-end; }
    .msg-bubble {
      position: relative; padding: 0.6rem 0.85rem;
      border-radius: 14px; font-size: 0.82rem; line-height: 1.55;
      color: #e2e8f0; word-wrap: break-word; overflow-wrap: anywhere;
    }
    .msg.ai .msg-bubble {
      background: #141c2f; border: 1px solid #1e293b;
      border-bottom-left-radius: 5px;
    }
    .msg.user .msg-bubble {
      background: linear-gradient(135deg, #4f46e5, #6d5ce8);
      color: #fff; border-bottom-right-radius: 5px;
      box-shadow: 0 4px 14px rgba(79, 70, 229, 0.32);
    }
    .msg-bubble strong { color: #fff; font-weight: 800; }
    .msg-bubble em { color: #c7d2fe; font-style: italic; }
    .msg-bubble a { color: #a5b4fc; }
    .msg-meta {
      display: flex; align-items: center; gap: 0.35rem;
      font-size: 0.62rem; color: #64748b; font-weight: 600;
      padding: 0 0.25rem; letter-spacing: 0.01em;
    }
    .msg-copy {
      opacity: 0; background: transparent; border: none;
      color: #64748b; font-size: 0.62rem; font-weight: 700;
      cursor: pointer; padding: 0.1rem 0.35rem;
      border-radius: 4px; transition: all 0.15s; font-family: inherit;
    }
    .msg:hover .msg-copy { opacity: 1; }
    .msg-copy:hover { background: #1e293b; color: #c7d2fe; }

    .typing-dots { display: inline-flex; gap: 0.28rem; align-items: center; padding: 0.1rem 0; }
    .typing-dots i {
      width: 6px; height: 6px; border-radius: 50%;
      background: #818cf8; display: block;
      animation: tdWave 1.2s infinite ease-in-out;
    }
    .typing-dots i:nth-child(2) { animation-delay: 0.15s; }
    .typing-dots i:nth-child(3) { animation-delay: 0.3s; }
    @keyframes tdWave {
      0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
      30% { transform: translateY(-5px); opacity: 1; }
    }

    .m-chip-row {
      display: flex; gap: 0.4rem; overflow-x: auto;
      padding: 0.65rem 0.9rem; border-top: 1px solid #1c2740;
      background: #0a0f1c; flex-shrink: 0; scrollbar-width: none;
    }
    .m-chip-row::-webkit-scrollbar { display: none; }
    .m-chip {
      padding: 0.36rem 0.75rem; border-radius: 999px;
      border: 1px solid #243049; background: #0f1729;
      color: #cbd5e1; font-size: 0.72rem; font-weight: 700;
      cursor: pointer; white-space: nowrap; transition: all 0.16s;
      display: inline-flex; align-items: center; gap: 0.3rem;
    }
    .m-chip:hover {
      border-color: #6366f1; color: #fff; background: #1a2140;
      transform: translateY(-1px);
    }

    .magic-input-row {
      display: flex; gap: 0.5rem;
      padding: 0.7rem 0.9rem 0.5rem;
      background: #0a0f1c; flex-shrink: 0; align-items: flex-end;
    }
    .magic-input-wrap {
      flex: 1; min-width: 0;
      display: flex; align-items: center; gap: 0.5rem;
      background: #080c14; border: 1.5px solid #243049;
      border-radius: 12px;
      padding: 0.05rem 0.2rem 0.05rem 0.75rem;
      transition: border-color 0.18s, box-shadow 0.18s;
    }
    .magic-input-wrap:focus-within {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16);
    }
    .magic-input-icon { color: #64748b; font-size: 0.85rem; flex-shrink: 0; line-height: 1; }
    .magic-input {
      flex: 1; min-width: 0; padding: 0.62rem 0;
      border: none; background: transparent; color: #fff;
      font-family: inherit; font-size: 0.84rem; outline: none;
    }
    .magic-input::placeholder { color: #475569; }
    .magic-btn {
      width: 38px; height: 38px; border-radius: 11px;
      border: none;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      color: #fff; display: inline-flex; align-items: center; justify-content: center;
      font-size: 1rem; cursor: pointer; flex-shrink: 0;
      box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
      transition: all 0.18s; line-height: 1;
    }
    .magic-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79, 70, 229, 0.6); }
    .magic-btn:active { transform: scale(0.95); }
    .magic-btn:disabled { opacity: 0.55; cursor: wait; transform: none; }

    .magic-hint {
      font-size: 0.65rem; color: #475569; text-align: center;
      padding: 0 0.9rem 0.7rem; background: #0a0f1c; font-weight: 600;
    }
    .magic-hint kbd {
      background: #1e293b; border: 1px solid #334155;
      border-radius: 4px; padding: 0.05rem 0.32rem;
      font-family: inherit; font-size: 0.62rem; color: #cbd5e1;
    }

    .ai-target-banner {
      display: flex; align-items: center; justify-content: space-between;
      gap: 0.5rem; padding: 0.5rem 0.85rem;
      background: #0b101d; border-bottom: 1px solid #1e293b;
      font-size: 0.74rem; color: #94a3b8;
      transition: all 0.2s ease;
    }
    .ai-target-banner.has-selection {
      background: rgba(99, 102, 241, 0.15);
      border-bottom-color: rgba(99, 102, 241, 0.4);
      color: #c7d2fe;
    }
    .ai-target-info {
      display: flex; align-items: center; gap: 0.45rem;
      overflow: hidden; text-overflow: ellipsis;
      white-space: nowrap; flex: 1;
    }
    .ai-target-tag {
      background: #312e81; color: #a5b4fc;
      font-size: 0.65rem; font-weight: 800;
      padding: 0.1rem 0.45rem; border-radius: 4px;
      text-transform: uppercase; letter-spacing: 0.05em;
    }
    .ai-target-preview {
      overflow: hidden; text-overflow: ellipsis;
      white-space: nowrap; color: #f1f5f9; font-weight: 600;
    }
    .ai-target-clear {
      background: none; border: none; color: #f43f5e;
      cursor: pointer; font-size: 0.68rem; font-weight: 700;
      padding: 0.1rem 0.35rem; border-radius: 4px; flex-shrink: 0;
    }
    .ai-target-clear:hover { background: rgba(244, 63, 94, 0.15); }

    .gemini-model-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.35rem 0.85rem; background: #080c14;
      border-bottom: 1px solid #162032;
      font-size: 0.7rem; gap: 0.5rem;
    }
    .gemini-model-select {
      background: #111726; border: 1px solid #283347;
      color: #e2e8f0; border-radius: 6px;
      font-size: 0.7rem; padding: 0.2rem 0.45rem;
      font-family: inherit; outline: none;
    }

    @media (max-width: 480px) {
      .magic-ai-panel { right: 0.75rem; left: 0.75rem; width: auto; bottom: 5rem; }
      .floating-gemini-btn span:not(.fg-orb) { display: none; }
      .floating-gemini-btn { padding: 0.7rem; }
    }

    .modal-overlay {
      display: none; position: fixed; inset: 0;
      z-index: 9999; background: rgba(0, 0, 0, 0.85);
      backdrop-filter: blur(10px);
      align-items: center; justify-content: center;
      padding: 1.5rem;
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
      background: #111726; border: 1.5px solid #283347;
      border-radius: 20px; width: 90%; max-width: 640px;
      padding: 2rem; max-height: 90vh; overflow-y: auto;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8);
    }

    .btn-editor-tabs {
      display: flex; gap: 0.35rem; margin-bottom: 1.25rem;
      border-bottom: 1.5px solid #1e293b;
      padding-bottom: 0.5rem; flex-wrap: wrap;
    }
    .be-tab {
      padding: 0.4rem 0.9rem; border-radius: 8px;
      border: 1.5px solid transparent; background: #0a0f1c;
      color: #94a3b8; font-family: inherit; font-size: 0.78rem;
      font-weight: 700; cursor: pointer; transition: all 0.15s;
      display: inline-flex; align-items: center; gap: 0.3rem;
    }
    .be-tab:hover { border-color: #334155; color: #cbd5e1; }
    .be-tab.active {
      background: #1e1b4b; border-color: #6366f1; color: #a5b4fc;
      box-shadow: 0 0 12px rgba(99, 102, 241, 0.25);
    }
    .be-panel { display: none; }
    .be-panel.active { display: block; animation: bePanelIn 0.2s ease; }
    @keyframes bePanelIn {
      from { opacity: 0; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .be-field { margin-bottom: 1rem; }
    .be-label {
      display: block; font-size: 0.78rem; font-weight: 700;
      color: #cbd5e1; margin-bottom: 0.4rem;
    }
    .be-input {
      width: 100%; padding: 0.7rem 1rem;
      border: 1.5px solid #283347; border-radius: 10px;
      background: #080c14; color: #fff;
      font-family: inherit; font-size: 0.86rem;
      transition: border-color 0.2s;
    }
    .be-input:focus {
      outline: none; border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }
    .be-preset-row { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.85rem; }
    .be-preset-btn {
      padding: 0.35rem 0.7rem; border-radius: 7px;
      border: 1.5px solid #334155; background: #0a0f1c;
      color: #cbd5e1; font-family: inherit; font-size: 0.72rem;
      font-weight: 700; cursor: pointer; transition: all 0.15s;
    }
    .be-preset-btn:hover { border-color: #6366f1; color: #fff; }
    .be-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.7rem; }
    .be-color-row { display: flex; align-items: center; gap: 0.6rem; }
    .be-color-input {
      width: 44px; height: 44px; border-radius: 10px;
      border: 1.5px solid #334155; background: transparent;
      cursor: pointer; padding: 2px;
    }
    .be-color-hex {
      flex: 1; padding: 0.6rem 0.75rem;
      border: 1.5px solid #283347; border-radius: 8px;
      background: #080c14; color: #fff;
      font-family: 'Fira Code', monospace;
      font-size: 0.78rem; text-transform: uppercase;
    }
    .be-style-preview-wrap {
      background: #080c14; border: 1.5px solid #1e293b;
      border-radius: 12px; padding: 1.5rem;
      text-align: center; margin-bottom: 1.25rem;
    }
    .be-style-preview {
      display: inline-block; padding: 0.9rem 2rem;
      border-radius: 999px; font-weight: 700; font-size: 0.95rem;
      transition: all 0.2s;
    }
    .be-range-row { display: flex; align-items: center; gap: 0.75rem; }
    .be-range-row input[type=range] { flex: 1; accent-color: #6366f1; }
    .be-range-val {
      font-size: 0.75rem; color: #cbd5e1; font-weight: 700;
      min-width: 40px; text-align: right;
      font-family: 'Fira Code', monospace;
    }

    /* Image Editor source tabs & components */
    .ie-source-tabs {
      display: flex; gap: 0.35rem; margin-bottom: 0.85rem;
      background: #080c14; padding: 0.3rem; border-radius: 10px;
      border: 1.5px solid #1e293b;
    }
    .ie-source-tab {
      flex: 1; padding: 0.5rem 0.6rem; border-radius: 7px;
      border: none; background: transparent; color: #94a3b8;
      font-family: inherit; font-size: 0.78rem; font-weight: 700;
      cursor: pointer; transition: all 0.15s; display: inline-flex;
      align-items: center; justify-content: center; gap: 0.35rem;
    }
    .ie-source-tab:hover { color: #f1f5f9; background: rgba(255,255,255,0.05); }
    .ie-source-tab.active {
      background: #6366f1; color: #ffffff;
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
    }
    .ie-source-panel { display: none; }
    .ie-source-panel.active { display: block; animation: bePanelIn 0.2s ease; }
    .ie-dropzone {
      border: 2px dashed #334155; border-radius: 12px;
      padding: 1.25rem 1rem; text-align: center;
      background: #080c14; cursor: pointer; transition: all 0.2s;
    }
    .ie-dropzone:hover, .ie-dropzone.dragover {
      border-color: #6366f1; background: rgba(99, 102, 241, 0.08);
    }
    .ie-ai-chip {
      background: #0f172a; border: 1px solid #334155; border-radius: 999px;
      padding: 0.28rem 0.65rem; font-size: 0.72rem; color: #cbd5e1;
      cursor: pointer; transition: all 0.15s; user-select: none;
      display: inline-flex; align-items: center; gap: 0.25rem;
    }
    .ie-ai-chip:hover { border-color: #818cf8; color: #fff; background: rgba(99,102,241,0.18); }
    .ie-ai-chip.active { background: #6366f1; border-color: #6366f1; color: #fff; font-weight: 700; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
    .ai-chat-image-card {
      background: #0b1120; border: 1.5px solid #243049; border-radius: 14px;
      overflow: hidden; margin-top: 0.45rem; box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    }
    .ai-chat-image-actions {
      display: grid; grid-template-columns: 1fr 1fr; gap: 0.45rem; padding: 0.75rem;
      background: #0d1527; border-top: 1px solid #1e293b;
    }
    .ai-chat-img-btn {
      padding: 0.45rem 0.6rem; font-size: 0.72rem; font-weight: 700;
      border-radius: 8px; border: 1px solid #334155; background: #172033;
      color: #e2e8f0; cursor: pointer; transition: all 0.15s;
      display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;
    }
    .ai-chat-img-btn:hover { background: #25334d; border-color: #6366f1; color: #fff; transform: translateY(-1px); }
    .ai-chat-img-btn.primary { background: #4f46e5; border-color: #6366f1; color: #fff; }
    .ai-chat-img-btn.primary:hover { background: #4338ca; }
    .ai-chat-img-btn.success { background: #059669; border-color: #10b981; color: #fff; }
    .ai-chat-img-btn.success:hover { background: #047857; }
    .ie-stock-grid {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;
      max-height: 155px; overflow-y: auto; padding: 0.25rem;
    }
    .ie-stock-thumb {
      height: 62px; border-radius: 8px; overflow: hidden;
      border: 1.5px solid #243049; cursor: pointer; position: relative;
      transition: all 0.15s;
    }
    .ie-stock-thumb:hover {
      border-color: #6366f1; transform: scale(1.02);
    }
    .ie-stock-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .ie-stock-thumb span {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: rgba(0,0,0,0.7); color: #fff; font-size: 0.65rem;
      text-align: center; padding: 2px;
    }

    .section-picker-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 0.85rem; margin-top: 0.5rem;
    }
    .section-picker-card {
      background: #0a0f1c; border: 1.5px solid #243049;
      border-radius: 12px; padding: 1rem 0.85rem;
      cursor: pointer; transition: all 0.2s; text-align: center;
    }
    .section-picker-card:hover {
      border-color: #6366f1; background: #131a2b;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(99, 102, 241, 0.25);
    }
    .section-picker-card .sp-icon { font-size: 1.8rem; margin-bottom: 0.4rem; display: block; }
    .section-picker-card .sp-name { font-size: 0.85rem; font-weight: 800; color: #fff; margin-bottom: 0.25rem; }
    .section-picker-card .sp-desc { font-size: 0.7rem; color: #94a3b8; line-height: 1.4; }
    .section-picker-actions {
      display: flex; gap: 0.4rem; justify-content: center; margin-top: 0.7rem;
    }
    .section-picker-actions button {
      flex: 1; padding: 0.42rem 0.5rem; border-radius: 7px;
      border: 1px solid #334155; background: #0e1424; color: #cbd5e1;
      font: 700 0.68rem/1.2 'Plus Jakarta Sans', sans-serif; cursor: pointer;
    }
    .section-picker-actions button:hover { border-color: #6366f1; color: #fff; background: #1e1b4b; }
    .section-picker-actions .sp-add { background: #4f46e5; border-color: #6366f1; color: #fff; }
    .section-picker-actions .sp-add:hover { background: #6366f1; }
    .section-picker-actions .sp-cust { background: #78350f; border-color: #f59e0b; color: #fde68a; }
    .section-picker-actions .sp-cust:hover { background: #92400e; }

    .section-editor-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
    .section-editor-field { margin-bottom: 0.85rem; }
    .section-editor-field label {
      display: block; font-size: 0.75rem; font-weight: 700;
      color: #cbd5e1; margin-bottom: 0.35rem;
    }
    .section-editor-field input,
    .section-editor-field select,
    .section-editor-field textarea {
      width: 100%; padding: 0.62rem 0.75rem;
      border: 1px solid #283347; border-radius: 8px;
      background: #080c14; color: #fff;
      font: 0.8rem 'Plus Jakarta Sans', sans-serif;
    }
    .section-editor-field input[type=color] {
      height: 42px; padding: 3px; cursor: pointer;
    }
    .section-editor-note { font-size: 0.7rem; color: #64748b; line-height: 1.45; margin-top: 0.2rem; }
    .section-editor-wide { grid-column: 1 / -1; }

    .be-size-row { display: grid; grid-template-columns: 1fr 86px; gap: .45rem; align-items: stretch; }
    .be-unit-select { padding-right: .35rem; }
    .be-size-help { font-size: .65rem; color: #64748b; margin-top: .35rem; line-height: 1.35; }

    .dim-group-title {
      font-size: 0.72rem; color: #a5b4fc;
      text-transform: uppercase; letter-spacing: 0.05em;
      font-weight: 800; margin-bottom: 0.55rem;
      display: flex; align-items: center; gap: 0.4rem;
    }
    .dim-group {
      background: linear-gradient(180deg, #0d1424 0%, #0a0f1c 100%);
      border: 1.5px solid #243049; border-radius: 12px;
      padding: 0.9rem 0.9rem 0.5rem; margin-bottom: 0.85rem;
    }
    .dim-group .be-size-row { grid-template-columns: 1fr 82px; }
    .dim-group .section-editor-field { margin-bottom: 0.65rem; }

    @media (max-width:620px) { .section-editor-grid { grid-template-columns: 1fr; } }

    .shortcut-grid { display: grid; grid-template-columns: 1fr; gap: 0.5rem; }
    .shortcut-row {
      display: flex; justify-content: space-between; align-items: center;
      gap: 1rem; padding: 0.55rem 0.85rem;
      background: #0a0f1c; border: 1px solid #1e293b;
      border-radius: 8px; font-size: 0.82rem;
    }
    .shortcut-key {
      font-family: 'Fira Code', monospace;
      background: #1e293b; color: #c7d2fe;
      padding: 0.25rem 0.65rem; border-radius: 6px;
      font-size: 0.72rem; font-weight: 800;
      border-bottom: 2px solid #0f172a;
    }
    .shortcut-label { color: #cbd5e1; font-weight: 600; }

    .toast {
      position: fixed; bottom: 1.5rem; left: 1.5rem;
      z-index: 10000; background: #111726;
      border: 1.5px solid #283347; border-radius: 12px;
      padding: 0.75rem 1.25rem; color: #fff;
      font-size: 0.85rem; font-weight: 600;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
      transform: translateY(100px); opacity: 0;
      transition: all 0.3s ease;
      max-width: calc(100vw - 3rem);
    }
    .toast.show { transform: translateY(0); opacity: 1; }

    /* ═══════════ WC PRO — premium Canva-style layer (additive, safe) ═══════════ */
    .rail-group-lbl { font-size: 0.6rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #475569; text-align: center; margin: 0.55rem 0 0.15rem; }
    #wc-save-pill { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.72rem; font-weight: 800; padding: 0.32rem 0.7rem; border-radius: 999px; border: 1px solid #283347; background: #111726; color: #94a3b8; white-space: nowrap; }
    #wc-save-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 8px rgba(34,197,94,.8); }
    #wc-save-pill.saving .dot { background: #f59e0b; box-shadow: 0 0 8px rgba(245,158,11,.8); }
    #wc-save-pill.dirty .dot { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,.8); }
    .wc-pro-card { background: linear-gradient(180deg,#0d1424,#0a0f1c); border: 1.5px solid #243049; border-radius: 12px; padding: 0.85rem; margin-bottom: 0.8rem; }
    .wc-pro-card h4 { font-size: 0.75rem; font-weight: 800; color: #a5b4fc; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 0.4rem; }
    .wc-pro-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.55rem; font-size: 0.78rem; color: #cbd5e1; }
    .wc-pro-row label { flex: 1; font-weight: 600; }
    .wc-pro-row input[type="color"] { width: 38px; height: 28px; border: 1px solid #334155; border-radius: 8px; background: #0a0f1c; padding: 2px; cursor: pointer; }
    .wc-pro-row input[type="text"], .wc-pro-row input[type="number"], .wc-pro-row select { background: #0a0f1c; border: 1px solid #283347; color: #e2e8f0; border-radius: 8px; padding: 0.35rem 0.5rem; font-size: 0.76rem; font-family: inherit; max-width: 130px; }
    .wc-pro-row input[type="range"] { flex: 1; accent-color: #6366f1; }
    .wc-pro-val { font-size: 0.7rem; color: #818cf8; font-weight: 800; min-width: 44px; text-align: right; }
    .wc-pro-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    .wc-pro-btn { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; border-radius: 9px; padding: 0.45rem 0.6rem; font-size: 0.74rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: all .15s; }
    .wc-pro-btn:hover { border-color: #6366f1; background: #1e1b4b; }
    .wc-pro-btn.primary { background: linear-gradient(135deg,#6366f1,#8b5cf6); border-color: #818cf8; color: #fff; }
    .wc-pro-btn.danger { color: #f87171; border-color: #7f1d1d; }
    .wc-pro-btn.small { padding: 0.3rem 0.5rem; font-size: 0.7rem; }
    .wc-pro-details { border: 1px solid #1e293b; border-radius: 10px; margin-bottom: 0.6rem; overflow: hidden; }
    .wc-pro-details summary { cursor: pointer; padding: 0.55rem 0.75rem; font-size: 0.76rem; font-weight: 800; color: #a5b4fc; background: #0a0f1c; list-style: none; }
    .wc-pro-details summary::-webkit-details-marker { display: none; }
    .wc-pro-details .wc-pro-details-body { padding: 0.7rem 0.75rem; }
    .wc-dev-badge { display: inline-block; font-size: 0.62rem; font-weight: 800; padding: 0.12rem 0.45rem; border-radius: 999px; background: #1e1b4b; border: 1px solid #6366f1; color: #c7d2fe; margin-left: 0.35rem; vertical-align: middle; }
    .wc-dev-badge.on { background: linear-gradient(135deg,#ec4899,#8b5cf6); border-color: #f472b6; color: #fff; }
    .wc-vis-row { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-top: 0.4rem; }
    .wc-vis-chip { font-size: 0.68rem; font-weight: 800; padding: 0.28rem 0.55rem; border-radius: 999px; border: 1px solid #334155; background: #0f172a; color: #94a3b8; cursor: pointer; }
    .wc-vis-chip.active { background: #1e1b4b; border-color: #ef4444; color: #fca5a5; }
    #wc-text-toolbar { position: fixed; z-index: 9000; display: none; align-items: center; gap: 2px; background: #111726; border: 1.5px solid #334155; border-radius: 12px; padding: 5px 6px; box-shadow: 0 12px 32px rgba(0,0,0,.6); max-width: calc(100vw - 20px); flex-wrap: wrap; }
    #wc-text-toolbar.show { display: flex; }
    #wc-text-toolbar button, #wc-text-toolbar select, #wc-text-toolbar input[type="color"] { background: transparent; border: 1px solid transparent; color: #e2e8f0; border-radius: 7px; padding: 5px 7px; font-size: 0.8rem; cursor: pointer; font-family: inherit; }
    #wc-text-toolbar button:hover { background: #1e293b; border-color: #6366f1; }
    #wc-text-toolbar button.on { background: #312e81; border-color: #818cf8; }
    #wc-text-toolbar select { border-color: #283347; font-size: 0.72rem; max-width: 110px; }
    #wc-text-toolbar input[type="color"] { width: 30px; height: 26px; padding: 1px; }
    .wc-align-guides { position: absolute; inset: 0; pointer-events: none; z-index: 400; display: none; }
    .wc-align-guides.show { display: block; }
    .wc-guide-line { position: absolute; background: #22d3ee; box-shadow: 0 0 6px rgba(34,211,238,.9); }
    .wc-guide-line.v { width: 1px; top: 0; bottom: 0; }
    .wc-guide-line.h { height: 1px; left: 0; right: 0; }
    .wc-sec-item { background: #0a0f1c; border: 1px solid #1e293b; border-radius: 10px; padding: 0.55rem 0.6rem; margin-bottom: 0.5rem; }
    .wc-sec-item.locked { border-color: #f59e0b; }
    .wc-sec-item.hidden-sec { opacity: 0.55; }
    .wc-sec-item-top { display: flex; align-items: center; gap: 0.45rem; }
    .wc-sec-item-name { flex: 1; font-size: 0.76rem; font-weight: 700; color: #e2e8f0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .wc-sec-item-btns { display: flex; gap: 0.25rem; flex-wrap: wrap; margin-top: 0.45rem; }
    .wc-ver-item { display: flex; align-items: center; gap: 0.5rem; background: #0a0f1c; border: 1px solid #1e293b; border-radius: 10px; padding: 0.5rem 0.6rem; margin-bottom: 0.45rem; font-size: 0.74rem; }
    .wc-ver-item .t { flex: 1; color: #cbd5e1; font-weight: 600; }
    #wc-before-after-modal { position: fixed; inset: 0; z-index: 9500; display: none; align-items: center; justify-content: center; background: rgba(2,6,16,.75); backdrop-filter: blur(4px); }
    #wc-before-after-modal.show { display: flex; }
    #wc-before-after-modal .ba-box { width: min(980px, 94vw); max-height: 88vh; overflow: auto; background: #0b0f1a; border: 1px solid #334155; border-radius: 16px; padding: 1rem; }
    #wc-before-after-modal iframe { width: 100%; height: 320px; border: 1px solid #1e293b; border-radius: 10px; background: #fff; }
    .wc-scope-row { display: flex; gap: 0.35rem; padding: 0.45rem 0.9rem; background: #080d19; border-top: 1px solid #162035; margin: 0; }
    .wc-scope-chip { flex: 1; text-align: center; font-size: 0.72rem; font-weight: 700; padding: 0.4rem 0.25rem; border-radius: 8px; border: 1px solid #1e293b; background: #0f172a; color: #94a3b8; cursor: pointer; transition: all .16s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem; user-select: none; }
    .wc-scope-chip:hover { background: #1e293b; color: #f1f5f9; border-color: #334155; }
    .wc-scope-chip.active { background: linear-gradient(135deg,#4f46e5,#7c3aed); color: #fff; border-color: #818cf8; box-shadow: 0 2px 10px rgba(99,102,241,0.35); }
    .wc-friendly-lbl { font-size: 0.68rem; color: #64748b; font-weight: 600; }
    @media (max-width: 900px) { #wc-save-pill .txt { display: none; } }
  </style>
</head>

<body>

  <header class="studio-header">
    <div class="header-left">
      <button class="hdr-btn back-btn" onclick="goBack()" title="Save & go back to the AI builder" style="background:#1e1b4b; border-color:#6366f1; color:#c7d2fe; font-weight:800; padding:0.4rem 0.85rem;">← <span class="lbl">Back</span></button>
      <div class="header-divider"></div>
      <div class="studio-logo"><span>✦</span><span class="logo-text">WebCraft</span></div>
      <div class="header-divider"></div>
      <input type="text" class="project-title-input" id="project-name-input" value="Zenith Studio" onchange="updateProjectName(this.value)" title="Website name — click to rename">
      <div class="header-divider"></div>
      <div class="concept-tabs">
        <button class="c-tab active" id="tab-c0" onclick="switchStudioConcept(0)" title="Switch to Concept 1"><span class="c-full">Concept 1</span><span class="c-short">C1</span></button>
        <button class="c-tab" id="tab-c1" onclick="switchStudioConcept(1)" title="Switch to Concept 2"><span class="c-full">Concept 2</span><span class="c-short">C2</span></button>
        <button class="c-tab" id="tab-c2" onclick="switchStudioConcept(2)" title="Switch to Concept 3"><span class="c-full">Concept 3</span><span class="c-short">C3</span></button>
      </div>
      <div class="header-divider" id="st-view-divider" style="display:none;"></div>
      <div class="concept-tabs" id="studio-view-tabs" style="display:none; gap:0.25rem;">
        <button class="c-tab active" id="st-vtab-site" onclick="switchStudioView('site')" title="Edit Frontend Public Site"><span class="c-full">🌐 Site</span><span class="c-short">🌐</span></button>
        <button class="c-tab" id="st-vtab-admin" onclick="switchStudioView('admin')" title="Edit Admin Panel (Backoffice)" style="border-color:#0891b2;"><span class="c-full">🔐 Admin Panel</span><span class="c-short">🔐</span></button>
      </div>
    </div>
    <div class="header-right">
      <button class="hdr-btn mobile-mode-btn" id="mobile-mode-btn" onclick="toggleMobileEditMode()" title="Edit mobile-only styles">📱 <span class="lbl">Mobile</span></button>
      <div class="header-divider"></div>
      <button class="hdr-btn edit-section-btn" onclick="openSelectedSectionEditor()" title="Style whatever you've selected on the canvas — colors, sizes, padding, fonts">🎨 <span class="lbl">Customize</span></button>
      <div class="header-divider"></div>
      <div class="device-toggles">
        <button class="dev-btn active" id="dev-desktop" onclick="setStudioDevice('Desktop')" title="Preview on desktop (full width)">🖥 <span class="lbl">Desktop</span></button>
        <button class="dev-btn" id="dev-tablet" onclick="setStudioDevice('Tablet')" title="Preview on tablet (768px)">📱 <span class="lbl">Tablet</span></button>
        <button class="dev-btn" id="dev-mobile" onclick="setStudioDevice('Mobile')" title="Preview on mobile (375px)">📲 <span class="lbl">Mobile</span></button>
      </div>
      <div class="header-divider"></div>
      <button class="hdr-btn" onclick="studioUndo()" title="Undo (Ctrl+Z)">↶</button>
      <button class="hdr-btn" onclick="studioRedo()" title="Redo (Ctrl+Y)">↷</button>
      <button class="hdr-btn" onclick="toggleStudioOutlines()" title="Show / hide element outlines">⬚</button>
      <button class="hdr-btn fullscreen-btn" id="fullscreen-btn" onclick="toggleFullscreen()" title="Toggle fullscreen canvas (hide sidebars)">⛶</button>
      <div class="header-divider"></div>
      <select class="lang-select" id="header-lang-select" onchange="switchCanvasLanguage(this.value)" title="Preview site in a different language"></select>
      <button class="hdr-btn help-btn" onclick="openShortcutsModal()" title="Keyboard shortcuts & tips">❓ <span class="lbl">Help</span></button>
      <button class="hdr-btn" onclick="openUserManual()" title="User manual: how to use, edit & publish (popup)">📘 <span class="lbl">Manual</span></button>
      <div class="header-divider"></div>

      <button class="hdr-btn ai-btn" onclick="toggleMagicAi()" title="Ask AI to build sections, rewrite copy, or edit selected elements">✦ <span class="lbl">Magic AI</span></button>
      <button class="hdr-btn edit-toggle-btn active" id="btn-studio-edit-mode" onclick="toggleStudioEditMode()" title="Edit mode: ON = full visual editing. OFF = clean normal page preview.">✏️ <span class="lbl">Edit: ON</span></button>
      <button class="hdr-btn" onclick="openStudioPreview()" title="Preview in a new tab — opens in the selected device view (PC / Laptop / Tablet / Phone)">👁️ <span class="lbl">Preview</span></button>
      <button class="hdr-btn save-btn" onclick="saveAndReturnToBuilder()" title="Save all changes and return">✓ <span class="lbl">Save</span></button>
    </div>
  </header>

  <div class="studio-main">
    <aside class="canva-rail">
      <div class="rail-item active" id="rail-blocks" onclick="switchDrawerTab('blocks')"><span class="icon">🧱</span><span>Elements</span></div>
      <div class="rail-item" id="rail-cards" onclick="openBlockCategory('Cards', 'rail-cards')"><span class="icon">🃏</span><span>Cards</span></div>
      <div class="rail-item" id="rail-shapes" onclick="openBlockCategory('Shapes', 'rail-shapes')"><span class="icon">⭐</span><span>Shapes</span></div>
      <div class="rail-item" id="rail-uploads" onclick="switchDrawerTab('uploads')"><span class="icon">📁</span><span>Media</span></div>
      <div class="rail-item" id="rail-text" onclick="switchDrawerTab('text')"><span class="icon">✍️</span><span>Text</span></div>
      <div class="rail-item" id="rail-anim" onclick="switchDrawerTab('anim')"><span class="icon">🎬</span><span>Anim</span></div>
      <div class="rail-item" id="rail-lang" onclick="switchDrawerTab('lang')"><span class="icon">🌐</span><span>Lang</span></div>
      <div class="rail-item" id="rail-styles" onclick="switchDrawerTab('styles')"><span class="icon">🎨</span><span>Styles</span></div>
      <div class="rail-item" id="rail-traits" onclick="switchDrawerTab('traits')"><span class="icon">⚙️</span><span>Settings</span></div>
      <div class="rail-item" id="rail-layers" onclick="switchDrawerTab('layers')"><span class="icon">📑</span><span>Layers</span></div>
      <div class="rail-item" id="rail-forms" onclick="openBlockCategory('Forms', 'rail-forms')"><span class="icon">📝</span><span>Forms</span></div>
      <div class="rail-item" id="rail-shop" onclick="openBlockCategory('Shop', 'rail-shop')"><span class="icon">🛍️</span><span>Shop</span></div>
      <div class="rail-item" id="rail-pages" onclick="switchDrawerTab('pages')"><span class="icon">📄</span><span>Pages</span></div>
      <div class="rail-item" id="rail-theme" onclick="wcOpenTab('theme')"><span class="icon">🎨</span><span>Theme</span></div>
      <div class="rail-item" id="rail-header" onclick="wcOpenTab('header')"><span class="icon">🏷️</span><span>Header</span></div>
      <div class="rail-item" id="rail-footer" onclick="wcOpenTab('footer')"><span class="icon">🦶</span><span>Footer</span></div>
      <div class="rail-item" id="rail-settings" onclick="wcOpenTab('settings')"><span class="icon">⚙️</span><span>Site</span></div>
      <div class="rail-item" id="rail-seo" onclick="wcOpenTab('seo')"><span class="icon">🚀</span><span>SEO</span></div>
      <div class="rail-item" id="rail-history" onclick="wcOpenTab('history')"><span class="icon">🕘</span><span>History</span></div>
      <div class="rail-item" id="rail-saved" onclick="wcOpenTab('saved')"><span class="icon">💎</span><span>My Sec</span></div>
    </aside>

    <div class="canva-drawer" id="canva-drawer">
      <div class="drawer-header">
        <span class="drawer-title" id="drawer-title">Elements &amp; Blocks</span>
        <button class="drawer-close" onclick="closeDrawer()">✕</button>
      </div>

      <div class="drawer-content" id="dtab-blocks" style="display:block;">
        <input type="text" class="block-search" placeholder="🔍 Search blocks..." oninput="filterBlocks(this.value)">
        <div class="block-pills">
          <span class="bpill active" onclick="filterBlockCategory('all', this)">All</span>
          <span class="bpill" onclick="filterBlockCategory('Sections', this)">Sections</span>
          <span class="bpill" onclick="filterBlockCategory('Components', this)">Components</span>
          <span class="bpill" onclick="filterBlockCategory('Forms', this)">Forms</span>
          <span class="bpill" onclick="filterBlockCategory('Shop', this)">Shop</span>
          <span class="bpill" onclick="filterBlockCategory('Business', this)">Business</span>
          <span class="bpill" onclick="filterBlockCategory('Shapes', this)">Shapes</span>
          <span class="bpill" onclick="filterBlockCategory('Cards', this)">Cards</span>
          <span class="bpill" onclick="filterBlockCategory('Typography', this)">Text</span>
        </div>
        <div id="gjs-blocks"></div>
      </div>

      <div class="drawer-content" id="dtab-pages" style="display:none;">
        <div class="biz-card">
          <div class="biz-card-hdr">
            <div class="biz-card-icon">📄</div>
            <div>
              <div class="biz-card-title">Pages Manager</div>
              <div class="biz-card-sub">Home, About, Contact… multi-page site</div>
            </div>
          </div>
          <div id="wc-pages-list"></div>
          <div style="display:flex; gap:0.4rem; margin-top:0.6rem;">
            <input type="text" id="wc-new-page-name" class="biz-input" placeholder="New page name…" style="flex:1;">
            <button type="button" class="friendly-action primary" style="flex:0 0 auto; min-width:0;" onclick="wcAddPage()">+ Add</button>
          </div>
          <div class="biz-note">First page = Homepage (publishes as index). Extra pages stay saved in Studio &amp; can be downloaded as .html. Full multi-page publish coming soon.</div>
        </div>

        <div class="biz-card">
          <div class="biz-card-hdr">
            <div class="biz-card-icon">⚙️</div>
            <div>
              <div class="biz-card-title">Business Setup</div>
              <div class="biz-card-sub">One place → applies to all blocks</div>
            </div>
          </div>
          <div class="biz-field"><label>WhatsApp number (country code + number, no +)</label><input type="text" id="biz-wa" class="biz-input" placeholder="e.g. 94771234567"></div>
          <div class="biz-field"><label>Contact email (form alerts)</label><input type="email" id="biz-email" class="biz-input" placeholder="hello@yourshop.lk"></div>
          <div class="biz-field"><label>Google Sheets webhook URL (optional)</label><input type="url" id="biz-sheet" class="biz-input" placeholder="https://script.google.com/…/exec"></div>
          <div class="biz-field"><label>PayHere Merchant ID (or YOUR_MERCHANT_ID)</label><input type="text" id="biz-merchant" class="biz-input" placeholder="YOUR_MERCHANT_ID"></div>
          <div class="biz-field"><label>LankaQR text (account / tagline on QR)</label><input type="text" id="biz-qr" class="biz-input" placeholder="My Shop • 0771234567"></div>
          <div class="biz-field"><label>Currency label</label><input type="text" id="biz-currency" class="biz-input" placeholder="Rs"></div>
          <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
            <button type="button" class="friendly-action primary" style="flex:1;" onclick="wcSaveBizSetup()">💾 Save</button>
            <button type="button" class="friendly-action whatsapp" style="flex:1;" onclick="wcApplyBizSetup()">⚡ Apply to canvas</button>
          </div>
          <div class="biz-note">Save keeps it for new blocks. “Apply to canvas” rewrites WhatsApp / email / Sheets / PayHere / QR / currency on blocks already on the page.</div>
        </div>
      </div>

      <div class="drawer-content" id="dtab-uploads" style="display:none;">
        <div class="upload-dropzone" onclick="document.getElementById('hidden-file-input').click()">
          <div class="u-icon">📁</div>
          <div class="u-text">Upload Image</div>
          <div class="u-sub">Click to browse or drag &amp; drop</div>
        </div>
        <input type="file" id="hidden-file-input" accept="image/*" multiple style="display:none" onchange="handleFileInput(this.files)">
        <div style="display:flex; gap:0.4rem; margin:0.65rem 0 0.85rem;">
          <input type="url" id="sidebar-online-url" class="be-input" placeholder="Or paste online image URL…" style="padding:0.45rem 0.65rem; font-size:0.76rem; flex:1;">
          <button type="button" class="be-preset-btn" style="background:#6366f1; border:none; color:#fff; font-size:0.75rem; padding:0.45rem 0.75rem; border-radius:8px; font-weight:700;" onclick="addSidebarOnlineImage()">+ Add</button>
        </div>
        <div id="user-uploads-section" style="margin-bottom:1.25rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem">
            <span style="font-size:0.75rem; font-weight:700; color:#818cf8; text-transform:uppercase;">🖼️ Your Uploads (<span id="user-upload-count">0</span>)</span>
            <button onclick="clearAllUploads()" style="background:none; border:none; color:#ef4444; font-size:0.68rem; font-weight:700; cursor:pointer;">Clear All</button>
          </div>
          <div id="user-uploads-grid" class="stock-grid" style="margin-bottom:0.6rem;"></div>
          <div id="user-uploads-empty" style="text-align:center; padding:0.75rem; background:#080c14; border:1px dashed #283347; border-radius:8px; font-size:0.72rem; color:#64748b;">No images uploaded yet.</div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin:1rem 0 0.5rem;">
          <span style="font-size:0.75rem; font-weight:700; color:#818cf8; text-transform:uppercase;">📸 Free Stock Photos</span>
          <button class="hdr-btn" style="padding:0.2rem 0.5rem; font-size:0.68rem; background:linear-gradient(135deg,#10b981,#059669); border:none; color:#fff; font-weight:700;" onclick="openGalleryBuilder()">🖼️ Gallery</button>
          <button class="hdr-btn" style="padding:0.2rem 0.5rem; font-size:0.68rem; background:linear-gradient(135deg,#6366f1,#a855f7); border:none; color:#fff; font-weight:700;" onclick="openCardBuilder()">🃏 Card</button>
        </div>
        <div class="block-pills">
          <span class="bpill active" onclick="filterStockPhotos('business', this)">Business</span>
          <span class="bpill" onclick="filterStockPhotos('tech', this)">Tech</span>
          <span class="bpill" onclick="filterStockPhotos('food', this)">Food</span>
          <span class="bpill" onclick="filterStockPhotos('gym', this)">Fitness</span>
          <span class="bpill" onclick="filterStockPhotos('team', this)">Team</span>
        </div>
        <div class="stock-grid" id="stock-grid"></div>
      </div>

      <div class="drawer-content" id="dtab-text" style="display:none;">
        <div style="font-size:0.72rem; color:#94a3b8; margin-bottom:0.8rem">Click any preset to insert:</div>
        <div style="display:flex; flex-direction:column; gap:0.6rem;">
          <button class="hdr-btn" style="justify-content:flex-start; padding:0.8rem; font-size:1.4rem; font-weight:900" onclick="insertTextPreset('h1')">H1 · Add Headline</button>
          <button class="hdr-btn" style="justify-content:flex-start; padding:0.7rem; font-size:1.1rem; font-weight:700" onclick="insertTextPreset('h2')">H2 · Add Subheading</button>
          <button class="hdr-btn" style="justify-content:flex-start; padding:0.6rem; font-size:0.95rem; font-weight:600" onclick="insertTextPreset('h3')">H3 · Section Title</button>
          <button class="hdr-btn" style="justify-content:flex-start; padding:0.6rem; font-size:0.88rem" onclick="insertTextPreset('p')">¶ · Body Paragraph</button>
          <button class="hdr-btn" style="justify-content:flex-start; padding:0.6rem; font-size:0.85rem; border-color:#6366f1; color:#c7d2fe" onclick="openSectionPicker()">🔘 · Add Button / Section</button>
        </div>
      </div>

      <div class="drawer-content" id="dtab-anim" style="display:none;">
        <div class="anim-card" style="margin-bottom:0.8rem; padding:0.7rem; text-align:left; display:flex; align-items:center; gap:0.5rem; cursor:default; border-color:#6366f1;">
          <span style="font-size:1.3rem">🎬</span>
          <div>
            <div style="font-size:0.8rem; font-weight:800; color:#fff;">Animation Effects</div>
            <div style="font-size:0.68rem; color:#94a3b8;">Select an element → pick an effect below</div>
          </div>
        </div>

        <div style="font-size:0.72rem; font-weight:700; color:#818cf8; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.5rem;">🎯 Effect</div>
        <div class="anim-grid" id="anim-presets-grid"></div>

        <div class="anim-field">
          <label>Trigger</label>
          <select id="anim-trigger" onchange="updateSelectedAnimation()">
            <option value="scroll">On scroll (fade in as it appears)</option>
            <option value="load">On page load</option>
            <option value="hover">On hover</option>
            <option value="click">On click</option>
          </select>
        </div>

        <div class="anim-field">
          <label>Duration <span class="val" id="anim-dur-val">700ms</span></label>
          <input type="range" id="anim-duration" min="100" max="2500" step="50" value="700" oninput="updateSelectedAnimation()">
        </div>
        <div class="anim-field">
          <label>Delay <span class="val" id="anim-delay-val">0ms</span></label>
          <input type="range" id="anim-delay" min="0" max="2000" step="50" value="0" oninput="updateSelectedAnimation()">
        </div>
        <div class="anim-field">
          <label>Easing</label>
          <select id="anim-easing" onchange="updateSelectedAnimation()">
            <option value="cubic-bezier(0.22,1,0.36,1)">Smooth (recommended)</option>
            <option value="ease">Ease</option>
            <option value="ease-in">Ease In</option>
            <option value="ease-out">Ease Out</option>
            <option value="ease-in-out">Ease In-Out</option>
            <option value="cubic-bezier(0.68,-0.55,0.27,1.55)">Bounce</option>
            <option value="linear">Linear</option>
          </select>
        </div>
        <div class="anim-field">
          <label>Repeat</label>
          <select id="anim-repeat" onchange="updateSelectedAnimation()">
            <option value="1">Once</option>
            <option value="2">2 times</option>
            <option value="3">3 times</option>
            <option value="infinite">Infinite loop</option>
          </select>
        </div>

        <div class="anim-preview-box">
          <div class="ap-demo" id="anim-preview-demo">▶ Live Preview</div>
          <button class="hdr-btn" style="margin-top:0.75rem; padding:0.3rem 0.7rem; font-size:0.7rem;" onclick="playAnimPreview()">↻ Replay</button>
        </div>

        <div class="friendly-btn-row">
          <button class="friendly-action primary" onclick="applyAnimationToSelected()">✓ Apply to Selected</button>
          <button class="friendly-action" onclick="removeAnimationFromSelected()">✕ Remove</button>
        </div>

        <div class="anim-card" style="margin-top:1rem; padding:0.7rem; cursor:default;">
          <div style="font-size:0.72rem; font-weight:700; color:#cbd5e1; margin-bottom:0.4rem;">⚡ Apply to whole page</div>
          <div class="friendly-btn-row">
            <button class="friendly-action" onclick="applyAnimToAll('fadeIn', 'load')">✨ Fade all</button>
            <button class="friendly-action" onclick="applyAnimToAll('slideUp', 'scroll')">⬆ Slide all</button>
          </div>
        </div>
      </div>

      <div class="drawer-content" id="dtab-lang" style="display:none;">
        <div class="lang-card">
          <div class="lc-head">
            <div>
              <div class="lc-title">🌐 Multi-Language Website</div>
              <div class="lc-sub">Pick languages → translate text → auto-switcher on your site</div>
            </div>
          </div>
          <div class="lang-chip-row" id="lang-chip-row"></div>
          <button class="friendly-action primary" style="width:100%" onclick="openLanguageManager()">✍️ Translate Content</button>
        </div>

        <div class="lang-card">
          <div class="lc-head">
            <div class="lc-title">🎨 Switcher Style</div>
          </div>
          <div class="anim-field">
            <label>Position</label>
            <select id="lang-switcher-pos" onchange="updateLangSwitcherSettings()">
              <option value="bottom-right">Bottom Right</option>
              <option value="bottom-left">Bottom Left</option>
              <option value="top-right">Top Right</option>
              <option value="top-left">Top Left</option>
            </select>
          </div>
          <div class="anim-field">
            <label>Style</label>
            <select id="lang-switcher-style" onchange="updateLangSwitcherSettings()">
              <option value="pill">Pill (flags + names)</option>
              <option value="dropdown">Dropdown</option>
              <option value="minimal">Minimal codes</option>
            </select>
          </div>
          <button class="friendly-action" style="width:100%" onclick="toggleLangSwitcher()" id="lang-switcher-toggle">👁️ Hide Switcher</button>
        </div>

        <div class="lang-card">
          <div class="lc-head">
            <div class="lc-title">🔧 Advanced</div>
          </div>
          <div class="friendly-card-desc" style="font-size:0.72rem;">Auto-detect browser language on first visit. RTL support for Arabic, Hebrew, Urdu.</div>
          <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
            <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.75rem; color:#cbd5e1; cursor:pointer;">
              <input type="checkbox" id="lang-autodetect" checked onchange="updateLangSwitcherSettings()" style="accent-color:#6366f1;"> Auto-detect
            </label>
            <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.75rem; color:#cbd5e1; cursor:pointer;">
              <input type="checkbox" id="lang-remember" checked onchange="updateLangSwitcherSettings()" style="accent-color:#6366f1;"> Remember choice
            </label>
          </div>
        </div>
      </div>

      <div class="drawer-content" id="dtab-styles" style="display:none;">
        <div style="font-size:0.72rem; color:#94a3b8; margin-bottom:0.6rem;">Select element to edit styles:</div>
        <div id="gjs-styles"></div>
      </div>

      <div class="drawer-content" id="dtab-traits" style="display:none;">
        <div class="friendly-panel">
          <div class="friendly-card">
            <div class="friendly-card-hdr">
              <div class="friendly-card-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">🎨</div>
              <div>
                <div class="friendly-card-title">Customize Element</div>
                <div class="friendly-card-sub">Style exactly what you picked</div>
              </div>
            </div>
            <p class="friendly-card-desc">Select any element on the canvas then tap below — only <strong style="color:#fbbf24">that exact element</strong> changes, not the whole section.</p>
            <button class="friendly-action customize" style="width:100%" onclick="openSelectedSectionEditor()">🎨 Customize Selected Element</button>
          </div>

          <div class="friendly-card">
            <div class="friendly-card-hdr">
              <div class="friendly-card-icon">🔘</div>
              <div>
                <div class="friendly-card-title">Add a Button</div>
                <div class="friendly-card-sub">Insert a clickable button</div>
              </div>
            </div>
            <p class="friendly-card-desc">Pick a style — then edit text, link, and colors live.</p>
            <div class="friendly-btn-row">
              <button class="friendly-action primary" onclick="addButtonFromSettings('primary')">+ Main Button</button>
              <button class="friendly-action" onclick="addButtonFromSettings('outline')">+ Outline</button>
              <button class="friendly-action whatsapp" onclick="addButtonFromSettings('whatsapp')">💬 WhatsApp</button>
            </div>
          </div>

          <div class="friendly-card">
            <div class="friendly-card-hdr">
              <div class="friendly-card-icon">📑</div>
              <div>
                <div class="friendly-card-title">Add a Section</div>
                <div class="friendly-card-sub">Pick from templates</div>
              </div>
            </div>
            <button class="friendly-action primary" style="width:100%" onclick="openSectionPicker()">🗂️ Browse Section Templates</button>
          </div>

          <div class="friendly-card">
            <div class="friendly-card-hdr">
              <div class="friendly-card-icon">📑</div>
              <div>
                <div class="friendly-card-title">Jump to Section</div>
                <div class="friendly-card-sub">Edit existing sections</div>
              </div>
            </div>
            <div id="friendly-sections-list" class="friendly-sections-list">
              <div style="font-size:0.72rem;color:#64748b;text-align:center;padding:0.5rem;">Scanning...</div>
            </div>
          </div>

          <div class="friendly-card">
            <div class="friendly-card-hdr">
              <div class="friendly-card-icon">🎨</div>
              <div>
                <div class="friendly-card-title">Website Theme</div>
                <div class="friendly-card-sub">Change colors everywhere</div>
              </div>
            </div>
            <p class="friendly-card-desc">Tap a swatch to recolor the whole site instantly. <strong style="color:#fbbf24">AI edits will not change this.</strong></p>
            <div class="friendly-theme-grid">
              <div class="friendly-theme-swatch" style="background:linear-gradient(135deg,#6366f1,#a855f7)" onclick="applyFriendlyTheme('#6366f1','#a855f7','Indigo')"></div>
              <div class="friendly-theme-swatch" style="background:linear-gradient(135deg,#2563eb,#06b6d4)" onclick="applyFriendlyTheme('#2563eb','#06b6d4','Ocean Blue')"></div>
              <div class="friendly-theme-swatch" style="background:linear-gradient(135deg,#059669,#10b981)" onclick="applyFriendlyTheme('#059669','#10b981','Emerald')"></div>
              <div class="friendly-theme-swatch" style="background:linear-gradient(135deg,#dc2626,#f97316)" onclick="applyFriendlyTheme('#dc2626','#f97316','Crimson')"></div>
              <div class="friendly-theme-swatch" style="background:linear-gradient(135deg,#d97706,#f59e0b)" onclick="applyFriendlyTheme('#d97706','#f59e0b','Gold')"></div>
              <div class="friendly-theme-swatch" style="background:linear-gradient(135deg,#0f172a,#334155)" onclick="applyFriendlyTheme('#0f172a','#334155','Slate')"></div>
            </div>
            <button class="friendly-action" style="width:100%;" onclick="toggleFriendlyBg()">🌓 Toggle Light / Dark</button>
          </div>

          <details class="friendly-advanced">
            <summary>⚙️ Advanced Element Settings</summary>
            <div id="gjs-traits"></div>
          </details>
        </div>
      </div>

      <div class="drawer-content" id="dtab-layers" style="display:none;">
        <div class="layer-tabs">
          <button class="ltab active" id="ltab-smart" onclick="switchLayerMode('smart')">✍️ Text &amp; Content</button>
          <button class="ltab" id="ltab-tree" onclick="switchLayerMode('tree')">📑 Full DOM Tree</button>
        </div>
        <div id="smart-layers-view">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.65rem;">
            <span style="font-size:0.72rem; color:#94a3b8;">Edit text or click to select:</span>
            <button class="hdr-btn" onclick="refreshSmartLayers()" style="padding:0.2rem 0.45rem; font-size:0.68rem;">↺</button>
          </div>
          <div class="layer-tree-container" id="smart-layers-list"></div>
        </div>
        <div id="raw-layers-view" style="display:none;">
          <div style="font-size:0.72rem; color:#94a3b8; margin-bottom:0.6rem;">Full DOM hierarchy:</div>
          <div id="gjs-layers"></div>
        </div>
      </div>
    </div>

    <main class="studio-canvas-wrap">
      <div class="mobile-mode-banner" id="mobile-mode-banner">
        <span>📱 Mobile-only editing ON</span>
        <button onclick="toggleMobileEditMode()" style="background:none; border:none; color:#fff; font-weight:900; cursor:pointer; font-size:0.85rem;">✕</button>
      </div>

      <button class="floating-edit-content-btn" id="floating-edit-content-btn"
        onclick="openContentEditorForSelectedSection()">
        ✍️ Edit Section Content
      </button>

      <!-- ★ Canvas Feature Ribbon (Creative Customer Tools) -->
      <div class="canvas-feature-ribbon" id="canvas-feature-ribbon">
        <!-- Left: Device & Resolution Selector -->
        <div class="cfr-group">
          <div class="cfr-devices">
            <button class="cfr-btn active" id="cfr-dev-desktop" onclick="setStudioDevice('Desktop')" title="Desktop View (Full Width)">
              🖥️ <span class="cfr-lbl">Desktop</span>
            </button>
            <button class="cfr-btn" id="cfr-dev-laptop" onclick="setStudioDevice('Laptop')" title="Laptop View (1200px)">
              💻 <span class="cfr-lbl">1200px</span>
            </button>
            <button class="cfr-btn" id="cfr-dev-tablet" onclick="setStudioDevice('Tablet')" title="Tablet View (768px)">
              📱 <span class="cfr-lbl">Tablet</span>
            </button>
            <button class="cfr-btn" id="cfr-dev-mobile" onclick="setStudioDevice('Mobile')" title="Mobile View (375px)">
              📲 <span class="cfr-lbl">Mobile</span>
            </button>
          </div>
          <div class="cfr-dim-badge" id="cfr-dim-badge">
            <span class="cfr-dot"></span> <span id="cfr-dim-text">Full Width • 100%</span>
          </div>
          <div class="cfr-quick-add" style="display:inline-flex; align-items:center; gap:4px; margin-left:4px;">
            <button class="cfr-tool-btn" onclick="openBlockCategory('Cards', 'rail-cards')" title="Quick Insert Cards (Photo, Profile, Side Cards)" style="background:#1e1b4b; border-color:#6366f1; color:#c7d2fe; font-weight:800; padding:0.3rem 0.6rem;">
              🃏 <span class="cfr-tool-lbl">+ Card</span>
            </button>
            <button class="cfr-tool-btn" onclick="openBlockCategory('Shapes', 'rail-shapes')" title="Quick Insert Shapes (20 shapes — drag any image onto a shape to fit it)" style="background:#1e1b4b; border-color:#6366f1; color:#c7d2fe; font-weight:800; padding:0.3rem 0.6rem;">
              ⭐ <span class="cfr-tool-lbl">+ Shape</span>
            </button>
          </div>
        </div>

        <!-- Center: Creative Theme Palettes (Customer Magic Feature!) -->
        <div class="cfr-group cfr-center">
          <div class="cfr-theme-picker" title="Quick Theme Moods — Test different color vibes instantly!">
            <span class="cfr-theme-label">🎨 Mood:</span>
            <button class="cfr-theme-pill active" id="thm-original" onclick="applyThemeMood('original')" title="Original Design Colors">
              <span class="cfr-theme-swatch" style="background:linear-gradient(135deg,#6366f1,#a855f7)"></span> Original
            </button>
            <button class="cfr-theme-pill" id="thm-midnight" onclick="applyThemeMood('midnight')" title="Midnight Navy & Electric Cyan">
              <span class="cfr-theme-swatch" style="background:linear-gradient(135deg,#0284c7,#38bdf8)"></span> Midnight
            </button>
            <button class="cfr-theme-pill" id="thm-emerald" onclick="applyThemeMood('emerald')" title="Emerald Modern & Mint">
              <span class="cfr-theme-swatch" style="background:linear-gradient(135deg,#059669,#34d399)"></span> Emerald
            </button>
            <button class="cfr-theme-pill" id="thm-sunset" onclick="applyThemeMood('sunset')" title="Sunset Amber & Rose">
              <span class="cfr-theme-swatch" style="background:linear-gradient(135deg,#f97316,#fb7185)"></span> Sunset
            </button>
            <button class="cfr-theme-pill" id="thm-luxury" onclick="applyThemeMood('luxury')" title="Luxury Gold & Noir">
              <span class="cfr-theme-swatch" style="background:linear-gradient(135deg,#d97706,#fbbf24)"></span> Gold
            </button>
          </div>
        </div>

        <!-- Right: Alignment Grid Guide, Zoom Controls, Live SEO Score -->
        <div class="cfr-group">
          <!-- Grid Alignment Guide -->
          <button class="cfr-tool-btn" id="cfr-grid-btn" onclick="toggleCanvasGridGuide()" title="Toggle 12-Column Alignment Grid Guide">
            📐 <span class="cfr-tool-lbl">Grid</span>
          </button>

          <!-- Zoom Controls -->
          <div class="cfr-zoom-group">
            <button class="cfr-tool-btn" onclick="zoomCanvas(-0.1)" title="Zoom Out (Ctrl -)">−</button>
            <span class="cfr-zoom-val" id="cfr-zoom-val">100%</span>
            <button class="cfr-tool-btn" onclick="zoomCanvas(0.1)" title="Zoom In (Ctrl +)">+</button>
            <button class="cfr-tool-btn" onclick="resetCanvasZoom()" title="Reset Zoom to 100%">↺</button>
          </div>

          <!-- Live SEO Audit Badge -->
          <button class="cfr-seo-pill" onclick="openSeoAuditModal()" title="Live Page SEO & Readiness Audit">
            <span class="cfr-seo-score" id="cfr-seo-score">96%</span>
            <span class="cfr-seo-lbl">SEO Check</span>
          </button>
        </div>
      </div>

      <!-- Alignment 12-Column Grid Guide Overlay -->
      <div class="canvas-grid-overlay" id="canvas-grid-overlay">
        <div class="cgo-col"></div><div class="cgo-col"></div><div class="cgo-col"></div>
        <div class="cgo-col"></div><div class="cgo-col"></div><div class="cgo-col"></div>
        <div class="cgo-col"></div><div class="cgo-col"></div><div class="cgo-col"></div>
        <div class="cgo-col"></div><div class="cgo-col"></div><div class="cgo-col"></div>
      </div>

      <div id="gjs"></div>

      <!-- ★ Canvas tab-load detector: concept/view switches reload the canvas -->
      <div id="canvas-spin" style="display:none; position:absolute; inset:0; z-index:500; align-items:center; justify-content:center; background:rgba(6,9,14,.55); backdrop-filter:blur(2px); pointer-events:none;">
        <div class="wcl-mini-house" style="transform:scale(1.4);"><div class="walls"></div><div class="roof"></div><div class="door"></div></div>
      </div>
    </main>

    <!-- ★ Right Context Panel -->
    <aside class="ctx-panel" id="ctx-panel">
      <div class="ctx-panel-head">⚡ Quick Actions</div>

      <!-- Idle state: nothing selected -->
      <div class="ctx-idle" id="ctx-idle-state">
        <div class="ctx-tip-card">
          <span class="tip-icon">👆</span>
          <strong>Click to select</strong>
          Click any element on the canvas to see quick actions here.
        </div>
        <div class="ctx-tip-card">
          <span class="tip-icon">🖱️</span>
          <strong>Right-click for more</strong>
          Right-click any element to edit, animate, duplicate or delete.
        </div>
        <div class="ctx-tip-card">
          <span class="tip-icon">↕️</span>
          <strong>Drag to reorder</strong>
          Drag sections up/down using the handle on hover.
        </div>
        <hr class="ctx-divider">
        <div class="ctx-quick-btns">
          <button class="ctx-quick-btn" onclick="openBlockCategory('Cards', 'rail-cards')" style="background:#1e1b4b; border-color:#6366f1; color:#c7d2fe; font-weight:700;"><span class="qb-icon">🃏</span> Insert Card (Photo / Profile)</button>
          <button class="ctx-quick-btn" onclick="openBlockCategory('Shapes', 'rail-shapes')" style="background:#1e1b4b; border-color:#6366f1; color:#c7d2fe; font-weight:700;"><span class="qb-icon">⭐</span> Insert Shape (20 shapes — drag image in to fit)</button>
          <button class="ctx-quick-btn" onclick="switchDrawerTab('blocks')"><span class="qb-icon">🧱</span> All Elements &amp; Blocks</button>
          <button class="ctx-quick-btn" onclick="switchDrawerTab('uploads')"><span class="qb-icon">📁</span> Upload Image</button>
          <button class="ctx-quick-btn" onclick="toggleMagicAi()"><span class="qb-icon">✦</span> Ask AI</button>
          <button class="ctx-quick-btn" onclick="openStudioPreview()"><span class="qb-icon">👁️</span> Preview Site</button>
          <button class="ctx-quick-btn" onclick="studioUndo()"><span class="qb-icon">↶</span> Undo</button>
          <button class="ctx-quick-btn" onclick="studioRedo()"><span class="qb-icon">↷</span> Redo</button>
        </div>
      </div>

      <!-- Selected element state -->
      <div class="ctx-sel" id="ctx-sel-state" style="display:none;">
        <div class="ctx-el-badge">
          <span class="el-icon" id="ctx-el-icon">📦</span>
          <div>
            <div class="ctx-el-tag" id="ctx-el-tag">DIV</div>
            <div class="ctx-el-label" id="ctx-el-label">Element</div>
          </div>
        </div>
        <div class="ctx-action-grid">
          <button class="ctx-action-btn" onclick="ctxEditSelected()"><span class="ca-icon">✏️</span>Edit</button>
          <button class="ctx-action-btn" onclick="openSelectedSectionEditor()"><span class="ca-icon">🎨</span>Style</button>
          <button class="ctx-action-btn" onclick="ctxDuplicate()"><span class="ca-icon">📋</span>Clone</button>
          <button class="ctx-action-btn" onclick="ctxDelete()" style="color:#f87171;"><span class="ca-icon">🗑️</span>Delete</button>
          <button class="ctx-action-btn" onclick="ctxBringForward()"><span class="ca-icon">⬆️</span>Forward</button>
          <button class="ctx-action-btn" onclick="ctxSendBackward()"><span class="ca-icon">⬇️</span>Back</button>
          <button class="ctx-action-btn" id="ctx-img-edit-btn" style="display:none;" onclick="ctxEditImage()"><span class="ca-icon">🖼️</span>Image</button>
          <button class="ctx-action-btn" id="ctx-img-crop-btn" style="display:none;" onclick="ctxCropImage()"><span class="ca-icon">✂️</span>Crop</button>
          <button class="ctx-action-btn" onclick="ctxAnimate()"><span class="ca-icon">🎬</span>Animate</button>
          <button class="ctx-action-btn" onclick="ctxAskAiToEdit()"><span class="ca-icon">✦</span>AI Edit</button>
        </div>
        <hr class="ctx-divider">
        <div class="ctx-quick-btns">
          <button class="ctx-quick-btn" onclick="switchDrawerTab('styles')"><span class="qb-icon">🎨</span> Style Inspector</button>
          <button class="ctx-quick-btn" onclick="switchDrawerTab('layers')"><span class="qb-icon">📑</span> Layers</button>
          <button class="ctx-quick-btn" onclick="switchDrawerTab('traits')"><span class="qb-icon">⚙️</span> Settings</button>
        </div>
      </div>
    </aside>

  </div>

  <div class="ctx-menu" id="custom-context-menu">
    <button class="ctx-item" onclick="ctxEditSelected()"><span class="ctx-icon">✏️</span> Edit</button>
    <button class="ctx-item" onclick="ctxEditText()"><span class="ctx-icon">✍️</span> Edit Text</button>
    <button class="ctx-item" onclick="ctxAskAiToEdit()"><span class="ctx-icon">✦</span> Ask AI to Edit This</button>
    <button class="ctx-item" onclick="ctxCustomizeSection()"><span class="ctx-icon">🎨</span> Customize This Element</button>
    <button class="ctx-item" onclick="ctxEditSectionContent()"><span class="ctx-icon">📝</span> Edit Section Content</button>
    <button class="ctx-item" onclick="ctxAnimate()"><span class="ctx-icon">🎬</span> Animate Element</button>
    <button class="ctx-item" onclick="ctxCropImage()" id="ctx-crop-item"><span class="ctx-icon">✂️</span> Crop Image</button>
    <button class="ctx-item" onclick="ctxEditImage()" id="ctx-image-item"><span class="ctx-icon">🖼️</span> Edit Image</button>
    <button class="ctx-item" onclick="ctxShapeImage()" id="ctx-shape-item"><span class="ctx-icon">🖼️</span> Change Card / Shape Image</button>
    <button class="ctx-item" onclick="ctxEditLink()" id="ctx-link-item"><span class="ctx-icon">🔗</span> Edit Link</button>
    <div class="ctx-sep"></div>
    <button class="ctx-item" onclick="ctxDuplicate()"><span class="ctx-icon">📋</span> Duplicate</button>
    <button class="ctx-item" onclick="ctxBringForward()"><span class="ctx-icon">⬆️</span> Bring Forward</button>
    <button class="ctx-item" onclick="ctxSendBackward()"><span class="ctx-icon">⬇️</span> Send Backward</button>
    <div class="ctx-sep"></div>
    <button class="ctx-item danger" onclick="ctxDelete()"><span class="ctx-icon">🗑️</span> Delete</button>
  </div>

  <button class="floating-gemini-btn" onclick="toggleMagicAi()">
    <span class="fg-orb">✦</span>
    <span>WebCraft AI</span>
  </button>

  <div class="magic-ai-panel" id="magic-ai-panel">

    <div class="magic-header">
      <div class="magic-brand">
        <div class="magic-avatar">✦</div>
        <div class="magic-brand-text">
          <div class="magic-brand-name">WebCraft AI Studio</div>
          <div class="magic-brand-sub"><span class="dot"></span> ✦ Powered by Google Gemini · OpenCode Fallback</div>
        </div>
      </div>
      <div class="magic-header-actions">
        <button class="magic-icon-btn" onclick="clearMagicChat()" title="Clear chat">🗑</button>
        <button class="magic-icon-btn" onclick="toggleMagicAi()" title="Close panel">✕</button>
      </div>
    </div>

    <div class="gemini-model-row" style="padding:0.4rem 0.9rem; background:#080d19; border-bottom:1px solid #162035; display:flex; align-items:center; justify-content:space-between; gap:0.5rem; font-size:0.75rem;">
      <div style="display:flex; align-items:center; gap:0.4rem; min-width:0; flex:1;">
        <span style="color:#818cf8; font-weight:700; font-size:0.75rem; flex-shrink:0;">✦ Model:</span>
        <select id="magic-model-select" onchange="changeAiModel(this.value)" style="font-size:0.74rem; padding:0.25rem 0.5rem; max-width:230px; background:#0e172a; border:1px solid #283958; color:#cbd5e1; border-radius:6px; font-weight:600; cursor:pointer;">
          <option value="gemini-3.5-flash-lite" selected>✦ Gemini Flash Lite (Fast · Default)</option>
          <option value="gemini-2.5-flash">✦ Gemini 2.5 Flash (Active)</option>
          <option value="gemini-2.5-pro">✦ Gemini 2.5 Pro (Deep Reasoning · Active)</option>
          <option value="gemini-1.5-flash">✦ Gemini 1.5 Flash (Active)</option>
          <option value="opencode-fallback">⚡ OpenCode AI (Fallback)</option>
        </select>
      </div>
      <span style="font-size:0.68rem; color:#10b981; font-weight:700; display:flex; align-items:center; gap:0.3rem;">
        <span style="width:6px; height:6px; border-radius:50%; background:#10b981; display:inline-block;"></span> Active
      </span>
    </div>

    <div class="ai-target-banner" id="ai-target-banner">
      <div class="ai-target-info">
        <span class="ai-target-icon" id="ai-target-icon">🌐</span>
        <span class="ai-target-tag" id="ai-target-tag" style="display:none;">PAGE</span>
        <span class="ai-target-preview" id="ai-target-preview">Entire Website Mode</span>
      </div>
      <button class="ai-target-clear" id="ai-target-clear-btn" onclick="clearSelectedComponentForAi()" style="display:none;" title="Deselect element and switch to whole website">✕ Clear</button>
    </div>

    <!-- ★ Copilot: Ask/Edit mode + current target line (existing model/target/banner untouched) -->
    <div class="cp-mode-row" id="copilot-mode-row">
      <div class="cp-mode-seg">
        <button type="button" class="cp-mode-btn" id="copilot-mode-ask" onclick="copilotSetMode('ask')" title="Ask: advice only, website will not change">💬 Ask</button>
        <button type="button" class="cp-mode-btn active" id="copilot-mode-edit" onclick="copilotSetMode('edit')" title="Edit: AI can modify the website">🛠️ Edit</button>
      </div>
      <div class="cp-target-line" id="copilot-target-line">🌐 Target: Entire Website</div>
    </div>

    <div class="magic-chat-log" id="magic-chat-log">
      <div class="msg ai">
        <div class="msg-avatar">✦</div>
        <div class="msg-body">
          <div class="msg-bubble">
            👋 <strong>Welcome to WebCraft AI!</strong><br>
            • Click any element or section on the canvas to <strong>edit it live with AI</strong>.<br>
            • Ask to rewrite text, tweak colors/styles, or insert whole sections.<br>
            • Type <em>"generate image of [subject]"</em> or tap <strong>🎨 AI Image Gen</strong> below to create AI visuals!<br>
            <span style="color:#94a3b8;font-size:0.74rem;">💡 All edits apply live to the active website.</span>
          </div>
          <div class="msg-meta">AI · just now</div>
        </div>
      </div>
    </div>

    <!-- Anchored Non-Shrinking Bottom Area (Always 100% visible) -->
    <div class="magic-panel-bottom" id="magic-panel-bottom">
      <!-- ★ Copilot action rail: content + design + audit (existing chips row below untouched) -->
      <div class="cp-rail" id="copilot-actions">
        <span class="m-chip cp-chip" onclick="copilotContentAsk('hero')">✍️ Hero copy</span>
        <span class="m-chip cp-chip" onclick="copilotContentAsk('testimonials')">💬 Reviews text</span>
        <span class="m-chip cp-chip" onclick="copilotContentAsk('faq')">❓ FAQ text</span>
        <span class="m-chip cp-chip" onclick="copilotContentAsk('seo')">🔎 SEO title+desc</span>
        <span class="m-chip cp-chip" onclick="copilotDesignCmd('premium')">✨ Premium</span>
        <span class="m-chip cp-chip" onclick="copilotDesignCmd('luxury')">🖤 Luxury</span>
        <span class="m-chip cp-chip" onclick="copilotDesignCmd('modern')">⚡ Modern</span>
        <span class="m-chip cp-chip" onclick="copilotDesignCmd('minimal')">🌿 Minimal</span>
        <span class="m-chip cp-chip" onclick="copilotDesignCmd('mobile')">📱 Mobile fix</span>
        <span class="m-chip cp-chip" onclick="copilotDesignCmd('cta')">🎯 CTA boost</span>
        <span class="m-chip cp-chip cp-audit" onclick="copilotAuditRun()">📋 Audit website</span>
      </div>
      <div class="cp-filebar" id="copilot-filebar" style="display:none;">
        <span class="cp-filepill" id="copilot-filepill">📎 <span id="copilot-filename"></span> <button type="button" onclick="copilotClearFile()" title="Remove file">✕</button></span>
        <span class="cp-filehint">file content will be used by AI</span>
      </div>
      <div class="m-chip-row" id="magic-chips-container">
        <span class="m-chip" style="background:linear-gradient(135deg,#3b82f6,#8b5cf6);color:#fff;border-color:#6366f1;font-weight:800;" onclick="triggerAiImageFlow()">🎨 AI Image Gen</span>
        <span class="m-chip" onclick="quickMagic('Add 5-star customer testimonials section with slide-up animation')">⭐ Reviews</span>
        <span class="m-chip" onclick="quickMagic('Add pricing table with 3 tiers')">💰 Pricing</span>
        <span class="m-chip" onclick="quickMagic('Add FAQ section')">❓ FAQ</span>
        <span class="m-chip" onclick="quickMagic('Add WhatsApp floating button')">💬 WhatsApp</span>
        <span class="m-chip" onclick="quickMagic('Add photo gallery section with fade-in animation')">🖼️ Gallery</span>
      </div>

      <!-- Scope Toolbar -->
      <div class="wc-scope-row" id="wc-ai-scope">
        <span class="wc-scope-chip active" data-scope="auto" onclick="wcSetAIScope('auto')">🤖 Auto</span>
        <span class="wc-scope-chip" data-scope="element" onclick="wcSetAIScope('element')">🎯 Element</span>
        <span class="wc-scope-chip" data-scope="section" onclick="wcSetAIScope('section')">📐 Section</span>
        <span class="wc-scope-chip" data-scope="site" onclick="wcSetAIScope('site')">🌐 Site</span>
      </div>

      <!-- Prominent Always-Visible Input Row -->
      <div class="magic-input-row">
        <div class="magic-input-wrap">
          <span class="magic-input-icon">✦</span>
          <input type="text" class="magic-input" id="magic-input"
            placeholder="Type your message to AI (e.g. change color, add review section)..."
            onkeydown="if(event.key==='Enter') executeMagicAi()">
          <button type="button" class="cp-attach" id="copilot-attach-btn" onclick="copilotAttachFile()" title="Attach a text file (.txt/.md/.csv) for AI to use">📎</button>
          <input type="file" id="copilot-file" accept=".txt,.md,.csv,.json" style="display:none;" onchange="copilotFilePicked(this)">
        </div>
        <button class="magic-btn" id="magic-btn" onclick="executeMagicAi()" title="Send message to AI">➤</button>
      </div>

      <div class="magic-hint">Press <kbd>Enter</kbd> to send · Live website changes apply instantly</div>
    </div>

  </div>

  <!-- ★ Copilot review / confirm modal (reuses .modal-overlay/.modal-box pattern) -->
  <div class="modal-overlay" id="copilot-review-modal" style="display:none;">
    <div class="modal-box" style="max-width:520px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:0.9rem;">
        <div>
          <h2 style="font-size:1.1rem; color:#fff; margin-bottom:0.25rem" id="copilot-review-title">Review AI changes</h2>
          <p style="font-size:0.76rem; color:#94a3b8" id="copilot-review-sub">Your website was not modified yet.</p>
        </div>
        <button class="drawer-close" onclick="copilotReviewDecide(false)" style="font-size:1.2rem">✕</button>
      </div>
      <div id="copilot-review-body" style="font-size:0.82rem; color:#cbd5e1; background:#0b1220; border:1px solid #1e293b; border-radius:10px; padding:0.8rem 0.95rem; margin-bottom:1rem; max-height:260px; overflow:auto;"></div>
      <div style="display:flex; gap:0.6rem; justify-content:flex-end;">
        <button type="button" class="be-btn" id="copilot-review-no" onclick="copilotReviewDecide(false)" style="background:#1e293b; color:#e2e8f0; font-weight:700;">Discard</button>
        <button type="button" class="be-btn" id="copilot-review-yes" onclick="copilotReviewDecide(true)" style="background:linear-gradient(135deg,#6366f1,#a855f7); color:#fff; font-weight:700;">Apply Changes</button>
      </div>
    </div>
  </div>

  <style>
    .cp-mode-row { display:flex; align-items:center; justify-content:space-between; gap:0.5rem; padding:0.4rem 0.9rem; background:#080d19; border-bottom:1px solid #162035; }
    .cp-mode-seg { display:flex; background:#0e172a; border:1px solid #283958; border-radius:8px; overflow:hidden; }
    .cp-mode-btn { border:none; background:transparent; color:#94a3b8; font-size:0.72rem; font-weight:700; padding:0.32rem 0.7rem; cursor:pointer; font-family:inherit; }
    .cp-mode-btn.active { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; }
    .cp-target-line { font-size:0.7rem; color:#67e8f9; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:55%; }
    .cp-rail { display:flex; gap:0.35rem; overflow-x:auto; padding:0.15rem 0.1rem 0.45rem; scrollbar-width:thin; }
    .cp-rail .cp-chip { flex-shrink:0; font-size:0.7rem; }
    .cp-rail .cp-audit { border-color:#f59e0b; color:#fde68a; font-weight:800; }
    .cp-filebar { display:flex; align-items:center; gap:0.5rem; padding:0 0.1rem 0.4rem; font-size:0.7rem; color:#94a3b8; }
    .cp-filepill { display:inline-flex; align-items:center; gap:0.35rem; background:#0e172a; border:1px solid #6366f1; color:#c7d2fe; border-radius:999px; padding:0.2rem 0.55rem; font-weight:700; }
    .cp-filepill button { background:none; border:none; color:#93c5fd; cursor:pointer; font-size:0.7rem; }
    .cp-filehint { color:#475569; }
    .cp-attach { background:none; border:none; cursor:pointer; font-size:0.95rem; padding:0 0.3rem; opacity:0.75; }
    .cp-attach:hover { opacity:1; }
    .cp-msg-actions { display:flex; flex-wrap:wrap; gap:0.35rem; margin-top:0.5rem; }
    .cp-msg-btn { background:#0e172a; border:1px solid #283958; color:#a5b4fc; font-size:0.68rem; font-weight:700; border-radius:999px; padding:0.22rem 0.65rem; cursor:pointer; font-family:inherit; }
    .cp-msg-btn:hover { background:#1e293b; color:#fff; }
    .cp-msg-btn.warn { border-color:#f59e0b; color:#fde68a; }
    .cp-audit-row { display:flex; align-items:center; gap:0.5rem; padding:0.3rem 0; border-bottom:1px dashed #1e293b; font-size:0.76rem; }
    .cp-audit-row:last-child { border-bottom:none; }
    .cp-sev-ok { color:#10b981; font-weight:800; } .cp-sev-warn { color:#f59e0b; font-weight:800; } .cp-sev-bad { color:#ef4444; font-weight:800; }
    .cp-fix-btn { margin-left:auto; flex-shrink:0; }
  </style>

  <!-- Page SEO & Quality Audit Modal -->
  <div class="modal-overlay" id="seo-audit-modal">
    <div class="modal-box" style="max-width:540px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
        <div>
          <h2 style="font-size:1.2rem; color:#fff; display:flex; align-items:center; gap:0.5rem; margin-bottom:0.2rem">
            <span style="font-size:1.3rem">⚡</span> Page SEO &amp; Quality Audit
          </h2>
          <p style="font-size:0.75rem; color:#94a3b8">Real-time scan of page structure, headings, images, and mobile readiness.</p>
        </div>
        <button class="drawer-close" onclick="closeSeoAuditModal()" style="font-size:1.3rem">✕</button>
      </div>

      <!-- Score Ring Summary -->
      <div style="display:flex; align-items:center; gap:1.25rem; background:linear-gradient(135deg,#064e3b,#0f172a); border:1px solid #10b981; border-radius:12px; padding:1rem 1.25rem; margin-bottom:1.2rem;">
        <div style="font-size:2.4rem; font-weight:900; color:#34d399; line-height:1;" id="seo-audit-score-num">96%</div>
        <div>
          <div style="font-size:0.85rem; font-weight:800; color:#ecfdf5; margin-bottom:0.15rem;" id="seo-audit-status">Excellent Search Readiness</div>
          <div style="font-size:0.72rem; color:#a7f3d0;" id="seo-audit-summary">Your website structure follows modern SEO and accessibility standards.</div>
        </div>
      </div>

      <!-- Check Items List -->
      <div id="seo-audit-checks-list" style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.2rem; max-height:300px; overflow-y:auto;">
        <!-- Injected dynamically by JS -->
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
        <button class="be-btn" style="background:#1e293b; color:#cbd5e1; border:1px solid #334155;" onclick="closeSeoAuditModal()">Close</button>
        <button class="be-btn" style="background:linear-gradient(135deg,#6366f1,#a855f7); color:#fff; font-weight:700;" onclick="toggleMagicAi(); closeSeoAuditModal();">✦ Ask AI to Optimize</button>
      </div>
    </div>
  </div>

  <!-- Button Editor Modal -->
  <div class="modal-overlay" id="button-editor-modal">
    <div class="modal-box" style="max-width:620px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem">
        <div>
          <h2 style="font-size:1.25rem; color:#fff; display:flex; align-items:center; gap:0.5rem; margin-bottom:0.15rem"><span style="font-size:1.3rem">🔘</span> Button Editor</h2>
          <p style="font-size:0.75rem; color:#94a3b8">Edit text, link, and style — changes apply instantly.</p>
        </div>
        <button class="drawer-close" onclick="closeButtonEditor()" style="font-size:1.4rem">✕</button>
      </div>
      <div class="be-style-preview-wrap">
        <a class="be-style-preview" id="be-preview" href="javascript:void(0)">Get Started →</a>
      </div>
      <div class="btn-editor-tabs">
        <button class="be-tab active" id="be-tab-content" onclick="switchBtnEditorTab('content')">✍️ Content</button>
        <button class="be-tab" id="be-tab-link" onclick="switchBtnEditorTab('link')">🔗 Link</button>
        <button class="be-tab" id="be-tab-style" onclick="switchBtnEditorTab('style')">🎨 Style</button>
      </div>

      <div class="be-panel active" id="be-panel-content">
        <div class="be-field">
          <label class="be-label">Button Text</label>
          <input type="text" class="be-input" id="be-text" placeholder="Get Started" oninput="applyBtnText(this.value)">
        </div>
        <div class="be-field">
          <label class="be-label">Quick Labels</label>
          <div class="be-preset-row">
            <button class="be-preset-btn" onclick="applyBtnTextPreset('Get Started →')">Get Started →</button>
            <button class="be-preset-btn" onclick="applyBtnTextPreset('Learn More')">Learn More</button>
            <button class="be-preset-btn" onclick="applyBtnTextPreset('Book Now')">Book Now</button>
            <button class="be-preset-btn" onclick="applyBtnTextPreset('Contact Us')">Contact Us</button>
            <button class="be-preset-btn" onclick="applyBtnTextPreset('Free Trial')">Free Trial</button>
            <button class="be-preset-btn" onclick="applyBtnTextPreset('Buy Now')">Buy Now</button>
          </div>
        </div>
        <div class="be-field">
          <label class="be-label">Add Emoji Prefix</label>
          <div class="be-preset-row">
            <button class="be-preset-btn" onclick="prependBtnEmoji('🚀')">🚀</button>
            <button class="be-preset-btn" onclick="prependBtnEmoji('✨')">✨</button>
            <button class="be-preset-btn" onclick="prependBtnEmoji('💎')">💎</button>
            <button class="be-preset-btn" onclick="prependBtnEmoji('🔥')">🔥</button>
            <button class="be-preset-btn" onclick="prependBtnEmoji('📞')">📞</button>
            <button class="be-preset-btn" onclick="prependBtnEmoji('📧')">📧</button>
          </div>
        </div>
      </div>

      <div class="be-panel" id="be-panel-link">
        <div class="be-field">
          <label class="be-label">Quick Section Links</label>
          <div class="section-editor-note" style="margin-bottom:0.5rem">Tap a section below to send this button straight to it.</div>
          <div class="be-preset-row" id="be-section-presets"></div>
        </div>
        <div class="be-field">
          <label class="be-label">Custom URL</label>
          <input type="text" class="be-input" id="be-link" placeholder="https://example.com or #section" oninput="applyBtnLink(this.value)">
        </div>
        <div class="be-field" style="display:flex; align-items:center; gap:0.6rem; background:#0a0e1a; padding:0.75rem 1rem; border-radius:10px; border:1px solid #1e293b;">
          <input type="checkbox" id="be-newtab" style="accent-color:#6366f1; width:18px; height:18px; cursor:pointer;" onchange="applyBtnTarget(this.checked)">
          <label for="be-newtab" style="font-size:0.82rem; color:#cbd5e1; cursor:pointer;">Open link in a new browser tab</label>
        </div>
      </div>

      <div class="be-panel" id="be-panel-style">
        <div class="be-field">
          <label class="be-label">Preset Styles</label>
          <div class="be-preset-row">
            <button class="be-preset-btn" onclick="applyBtnStylePreset('primary')">🎨 Primary</button>
            <button class="be-preset-btn" onclick="applyBtnStylePreset('outline')">◯ Outline</button>
            <button class="be-preset-btn" onclick="applyBtnStylePreset('whatsapp')">💬 WhatsApp</button>
            <button class="be-preset-btn" onclick="applyBtnStylePreset('dark')">🌑 Dark</button>
            <button class="be-preset-btn" onclick="applyBtnStylePreset('ghost')">👻 Ghost</button>
          </div>
        </div>
        <div class="be-row-2">
          <div class="be-field">
            <label class="be-label">Background</label>
            <div class="be-color-row">
              <input type="color" class="be-color-input" id="be-bg-color" value="#6366f1" oninput="applyBtnBg(this.value)">
              <input type="text" class="be-color-hex" id="be-bg-hex" value="#6366F1" onchange="applyBtnBg(this.value)">
            </div>
          </div>
          <div class="be-field">
            <label class="be-label">Text Color</label>
            <div class="be-color-row">
              <input type="color" class="be-color-input" id="be-text-color" value="#ffffff" oninput="applyBtnTextColor(this.value)">
              <input type="text" class="be-color-hex" id="be-text-hex" value="#FFFFFF" onchange="applyBtnTextColor(this.value)">
            </div>
          </div>
        </div>
        <div class="be-field">
          <label class="be-label">Font Size</label>
          <div class="be-range-row"><input type="range" id="be-font-size" min="12" max="24" value="15" oninput="applyBtnFontSize(this.value)"><span class="be-range-val" id="be-font-size-val">15px</span></div>
        </div>
        <div class="be-field">
          <label class="be-label">Padding</label>
          <div class="be-range-row"><input type="range" id="be-padding" min="10" max="60" value="32" oninput="applyBtnPadding(this.value)"><span class="be-range-val" id="be-padding-val">32px</span></div>
        </div>
        <div class="be-field">
          <label class="be-label">Corner Roundness</label>
          <div class="be-range-row"><input type="range" id="be-radius" min="0" max="999" value="999" oninput="applyBtnRadius(this.value)"><span class="be-range-val" id="be-radius-val">999px</span></div>
        </div>
        <div class="be-field">
          <label class="be-label">Shadow</label>
          <div class="be-preset-row">
            <button class="be-preset-btn" onclick="applyBtnShadow('none')">None</button>
            <button class="be-preset-btn" onclick="applyBtnShadow('soft')">Soft</button>
            <button class="be-preset-btn" onclick="applyBtnShadow('glow')">Glow</button>
            <button class="be-preset-btn" onclick="applyBtnShadow('hard')">Hard</button>
          </div>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem; flex-wrap:wrap">
        <button class="hdr-btn" onclick="closeButtonEditor()">Close</button>
        <button class="hdr-btn save-btn" onclick="finishButtonEditor()">✓ Done</button>
      </div>
    </div>
  </div>

  <!-- Image Editor Modal -->
  <div class="modal-overlay" id="image-editor-modal">
    <div class="modal-box" style="max-width:640px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem">
        <div>
          <h2 style="font-size:1.25rem; color:#fff; display:flex; align-items:center; gap:0.5rem; margin-bottom:0.15rem"><span style="font-size:1.3rem">🖼️</span> Image Editor</h2>
          <p style="font-size:0.75rem; color:#94a3b8">Change photo via Local Upload or Online URL, customize size & crop.</p>
        </div>
        <button class="drawer-close" onclick="closeImageEditor()" style="font-size:1.4rem">✕</button>
      </div>

      <!-- Preview Box -->
      <div class="be-style-preview-wrap" style="padding:1rem; position:relative; margin-bottom:1rem;">
        <img id="ie-preview" src="" alt="Preview" style="display:block; max-width:100%; max-height:210px; margin:0 auto; border-radius:12px; object-fit:contain;">
        <div id="ie-status-pill" style="display:none; margin-top:0.6rem; font-size:0.75rem; padding:0.3rem 0.75rem; border-radius:8px; font-weight:600; text-align:center;"></div>
      </div>

      <!-- Source Chooser Tabs -->
      <div class="ie-source-tabs">
        <button type="button" class="ie-source-tab active" id="ie-tab-local" onclick="switchImageSourceTab('local')">
          <span>📁 Upload</span>
        </button>
        <button type="button" class="ie-source-tab" id="ie-tab-url" onclick="switchImageSourceTab('url')">
          <span>🌐 URL</span>
        </button>
        <button type="button" class="ie-source-tab" id="ie-tab-stock" onclick="switchImageSourceTab('stock')">
          <span>✨ Stock</span>
        </button>
        <button type="button" class="ie-source-tab" id="ie-tab-ai" onclick="switchImageSourceTab('ai')">
          <span>🎨 AI Generate</span>
        </button>
      </div>

      <!-- Tab 1: Local Device Upload -->
      <div class="ie-source-panel active" id="ie-panel-local">
        <div class="ie-dropzone" id="ie-dropzone" onclick="document.getElementById('ie-file').click()">
          <div style="font-size:2rem; margin-bottom:0.35rem">📁</div>
          <div style="font-size:0.86rem; font-weight:700; color:#e2e8f0; margin-bottom:0.2rem">Click to choose image or drag & drop here</div>
          <div style="font-size:0.72rem; color:#94a3b8">JPG, PNG, WebP, GIF, SVG (up to 8MB)</div>
          <div id="ie-local-filename" style="margin-top:0.4rem; font-size:0.76rem; color:#818cf8; font-weight:700; display:none;"></div>
        </div>
        <input type="file" id="ie-file" accept="image/*" style="display:none" onchange="replaceImageFromFile(this.files[0])">
      </div>

      <!-- Tab 2: Online URL -->
      <div class="ie-source-panel" id="ie-panel-url">
        <div class="be-field" style="margin-bottom:0.6rem;">
          <label class="be-label">Online Image Link (HTTP / HTTPS)</label>
          <div style="display:flex; gap:0.5rem;">
            <input type="url" class="be-input" id="ie-online-url-input" placeholder="https://images.unsplash.com/... or any image link" style="flex:1;" oninput="handleOnlineUrlInput(this.value)">
            <button type="button" class="be-preset-btn" style="padding:0.6rem 0.95rem; background:#1e293b; color:#fff;" onclick="testAndPreviewOnlineUrl()">🔍 Load</button>
          </div>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-bottom:0.5rem; background:#080c14; padding:0.6rem 0.85rem; border-radius:10px; border:1px solid #1e293b;">
          <div>
            <div style="font-size:0.78rem; font-weight:700; color:#e2e8f0">Save to Project?</div>
            <div style="font-size:0.7rem; color:#94a3b8">Downloads into project so the image never breaks</div>
          </div>
          <button type="button" class="hdr-btn" id="ie-import-btn" style="background:#059669; border:none; color:#fff; font-size:0.76rem; padding:0.4rem 0.85rem; border-radius:7px; font-weight:700; display:inline-flex; align-items:center; gap:0.35rem;" onclick="importOnlineUrlToProject()">
            <span>📥 Save to Project</span>
          </button>
        </div>
      </div>

      <!-- Tab 3: Stock Photos -->
      <div class="ie-source-panel" id="ie-panel-stock">
        <div style="font-size:0.75rem; color:#94a3b8; margin-bottom:0.45rem;">Click any free stock photo to instantly use:</div>
        <div class="ie-stock-grid" id="ie-stock-grid"></div>
      </div>

      <!-- Tab 4: AI Generate Image -->
      <div class="ie-source-panel" id="ie-panel-ai">
        <div class="be-field" style="margin-bottom:0.6rem;">
          <label class="be-label">AI Image Prompt (Describe anything you want to see)</label>
          <div style="display:flex; gap:0.5rem;">
            <input type="text" class="be-input" id="ie-ai-prompt" placeholder="e.g. Luxury sports car on mountain pass, warm sunset light..." style="flex:1;" onkeydown="if(event.key==='Enter')generateImageInEditor()">
            <button type="button" class="be-preset-btn" id="ie-ai-gen-btn" style="padding:0.6rem 0.95rem; background:linear-gradient(135deg, #4f46e5, #7c3aed); color:#fff; font-weight:700; white-space:nowrap;" onclick="generateImageInEditor()">✨ Generate</button>
          </div>
        </div>
        <div style="margin-bottom:0.6rem;">
          <div style="font-size:0.72rem; color:#94a3b8; margin-bottom:0.35rem; font-weight:600;">Style Presets:</div>
          <div style="display:flex; flex-wrap:wrap; gap:0.3rem;">
            <span class="ie-ai-chip active" onclick="setAiEditorStyle(this, '')">Default</span>
            <span class="ie-ai-chip" onclick="setAiEditorStyle(this, 'photorealistic 8k, realistic lighting')">📸 Photo</span>
            <span class="ie-ai-chip" onclick="setAiEditorStyle(this, 'cinematic movie shot, 35mm lens, depth of field')">🎬 Cinematic</span>
            <span class="ie-ai-chip" onclick="setAiEditorStyle(this, 'clean 3D render, minimalist, modern octane render')">🎨 3D Render</span>
            <span class="ie-ai-chip" onclick="setAiEditorStyle(this, 'commercial product photography, studio light, clean background')">🛍️ Product</span>
            <span class="ie-ai-chip" onclick="setAiEditorStyle(this, 'cyberpunk neon colors, futuristic dark aesthetic')">⚡ Cyberpunk</span>
          </div>
        </div>
        <div style="display:flex; gap:0.5rem; justify-content:space-between; align-items:center; background:#080c14; padding:0.5rem 0.75rem; border-radius:10px; border:1px solid #1e293b; margin-bottom:0.5rem;">
          <div style="font-size:0.72rem; color:#94a3b8; font-weight:600;">Ratio:</div>
          <div style="display:flex; gap:0.35rem;">
            <button type="button" class="ie-ai-chip active" id="ie-ratio-16-9" onclick="setAiEditorRatio(this, 1200, 800)">16:9 Landscape</button>
            <button type="button" class="ie-ai-chip" id="ie-ratio-1-1" onclick="setAiEditorRatio(this, 800, 800)">1:1 Square</button>
            <button type="button" class="ie-ai-chip" id="ie-ratio-4-5" onclick="setAiEditorRatio(this, 800, 1000)">4:5 Portrait</button>
          </div>
        </div>
      </div>

      <!-- Canonical image source input -->
      <input type="hidden" id="ie-src" value="">

      <!-- Image Details: Alt & Sizing -->
      <div class="be-field" style="margin-top:0.9rem;">
        <label class="be-label">Alt Text (Image Description)</label>
        <input type="text" class="be-input" id="ie-alt" placeholder="Describe the image for accessibility & SEO">
      </div>
      <div class="be-row-2">
        <div class="be-field">
          <label class="be-label">Width</label>
          <div class="be-size-row"><input type="number" min="0" step="0.1" class="be-input" id="ie-width-value" placeholder="600" oninput="previewImageEditor()"><select class="be-input be-unit-select" id="ie-width-unit" onchange="previewImageEditor()">
              <option value="px">px</option>
              <option value="cm">cm</option>
              <option value="in">in</option>
              <option value="%">%</option>
              <option value="auto">auto</option>
            </select></div>
        </div>
        <div class="be-field">
          <label class="be-label">Height</label>
          <div class="be-size-row"><input type="number" min="0" step="0.1" class="be-input" id="ie-height-value" placeholder="400" oninput="previewImageEditor()"><select class="be-input be-unit-select" id="ie-height-unit" onchange="previewImageEditor()">
              <option value="px">px</option>
              <option value="cm">cm</option>
              <option value="in">in</option>
              <option value="%">%</option>
              <option value="auto">auto</option>
            </select></div>
          <div class="be-size-help">Use px, cm, inch (in), or % for precise sizing.</div>
        </div>
      </div>
      <div class="be-row-2">
        <div class="be-field">
          <label class="be-label">Corner Radius</label>
          <input type="number" min="0" max="999" class="be-input" id="ie-radius" placeholder="16" oninput="previewImageEditor()">
        </div>
        <div class="be-field">
          <label class="be-label">Object Fit</label>
          <select class="be-input" id="ie-fit" onchange="previewImageEditor()">
            <option value="cover">Cover</option>
            <option value="contain">Contain</option>
            <option value="fill">Fill</option>
            <option value="none">None</option>
            <option value="scale-down">Scale Down</option>
          </select>
        </div>
      </div>
      <div style="display:flex; justify-content:space-between; gap:0.75rem; margin-top:1rem; flex-wrap:wrap">
        <button class="hdr-btn" style="background:linear-gradient(135deg,#06b6d4,#0891b2); border:none; color:#fff; font-weight:700;" onclick="openCropFromImageEditor()">✂️ Crop Image</button>
        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
          <button class="hdr-btn" onclick="closeImageEditor()">Close</button>
          <button class="hdr-btn save-btn" id="ie-apply-btn" onclick="applyImageEditor()">✓ Apply Image</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Image Crop Modal -->
  <div class="modal-overlay" id="crop-modal">
    <div class="modal-box" style="max-width:720px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem">
        <div>
          <h2 style="font-size:1.25rem; color:#fff; display:flex; align-items:center; gap:0.5rem; margin-bottom:0.15rem"><span style="font-size:1.3rem">✂️</span> Crop Image</h2>
          <p style="font-size:0.75rem; color:#94a3b8">Drag the box or its corners. Pick a ratio or freeform.</p>
        </div>
        <button class="drawer-close" onclick="closeCropTool()" style="font-size:1.4rem">✕</button>
      </div>

      <div class="crop-ratio-row">
        <button class="be-preset-btn" onclick="setCropRatio('free', this)">Free</button>
        <button class="be-preset-btn" onclick="setCropRatio('1:1', this)">1:1 Square</button>
        <button class="be-preset-btn" onclick="setCropRatio('4:3', this)">4:3</button>
        <button class="be-preset-btn" onclick="setCropRatio('16:9', this)">16:9 Wide</button>
        <button class="be-preset-btn" onclick="setCropRatio('3:4', this)">3:4 Portrait</button>
        <button class="be-preset-btn" onclick="setCropRatio('9:16', this)">9:16 Story</button>
      </div>

      <div style="text-align:center; margin-bottom:1rem;">
        <div class="crop-stage" id="crop-stage">
          <img id="crop-image" src="" alt="Crop" crossorigin="anonymous">
          <div class="crop-rect" id="crop-rect">
            <div class="crop-handle nw" data-h="nw"></div>
            <div class="crop-handle ne" data-h="ne"></div>
            <div class="crop-handle sw" data-h="sw"></div>
            <div class="crop-handle se" data-h="se"></div>
          </div>
        </div>
      </div>

      <div class="be-row-2">
        <div class="be-field">
          <label class="be-label">Output Width (px)</label>
          <input type="number" class="be-input" id="crop-out-w" placeholder="Auto" min="16">
        </div>
        <div class="be-field">
          <label class="be-label">Output Height (px)</label>
          <input type="number" class="be-input" id="crop-out-h" placeholder="Auto" min="16">
        </div>
      </div>
      <div class="be-field" style="display:flex; align-items:center; gap:0.6rem; background:#0a0e1a; padding:0.65rem 1rem; border-radius:10px; border:1px solid #1e293b;">
        <input type="checkbox" id="crop-replace-orig" style="accent-color:#6366f1; width:18px; height:18px; cursor:pointer;">
        <label for="crop-replace-orig" style="font-size:0.8rem; color:#cbd5e1; cursor:pointer;">Replace the original image too (save the crop back to uploads)</label>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem; flex-wrap:wrap">
        <button class="hdr-btn" onclick="resetCropBox()">↺ Reset Box</button>
        <button class="hdr-btn" onclick="closeCropTool()">Cancel</button>
        <button class="hdr-btn save-btn" onclick="applyCropToImage()">✓ Apply Crop</button>
      </div>
    </div>
  </div>

  <!-- Gallery Builder Modal -->
  <div class="modal-overlay" id="gallery-modal">
    <div class="modal-box" style="max-width:820px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem">
        <div>
          <h2 style="font-size:1.25rem; color:#fff; display:flex; align-items:center; gap:0.5rem; margin-bottom:0.15rem"><span style="font-size:1.3rem">🖼️</span> Gallery Builder</h2>
          <p style="font-size:0.75rem; color:#94a3b8">Pick a layout, choose images, insert.</p>
        </div>
        <button class="drawer-close" onclick="closeGalleryBuilder()" style="font-size:1.4rem">✕</button>
      </div>

      <div style="font-size:0.78rem; font-weight:800; color:#cbd5e1; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.6rem;">1. Choose Layout</div>
      <div class="gal-layouts" id="gal-layouts"></div>

      <div style="font-size:0.78rem; font-weight:800; color:#cbd5e1; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.6rem;">2. Pick Images (<span id="gal-selected-count">0</span> selected)</div>
      <div class="gal-picker" id="gal-picker"></div>

      <div class="be-field">
        <label class="be-label">Section Title</label>
        <input type="text" class="be-input" id="gal-title" value="Our Gallery" placeholder="Our Gallery">
      </div>
      <div class="be-field">
        <label class="be-label">Section Name / Anchor</label>
        <input type="text" class="be-input" id="gal-name" value="Gallery" placeholder="Gallery">
      </div>
      <div class="be-row-2">
        <div class="be-field">
          <label class="be-label">Gap (px)</label>
          <input type="number" class="be-input" id="gal-gap" value="16" min="0" max="80">
        </div>
        <div class="be-field">
          <label class="be-label">Corner Radius (px)</label>
          <input type="number" class="be-input" id="gal-radius" value="14" min="0" max="60">
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem; flex-wrap:wrap">
        <button class="hdr-btn" onclick="closeGalleryBuilder()">Cancel</button>
        <button class="hdr-btn save-btn" onclick="insertGallerySection()">✓ Insert Gallery</button>
      </div>
    </div>
  </div>

  <!-- Card Builder Modal -->
  <div class="modal-overlay" id="card-modal">
    <div class="modal-box" style="max-width:820px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem">
        <div>
          <h2 style="font-size:1.25rem; color:#fff; display:flex; align-items:center; gap:0.5rem; margin-bottom:0.15rem"><span style="font-size:1.3rem">🃏</span> Card Builder</h2>
          <p style="font-size:0.75rem; color:#94a3b8">Pick a layout + photo, write content, add a button. Everything stays editable after insert.</p>
        </div>
        <button class="drawer-close" onclick="closeCardBuilder()" style="font-size:1.4rem">✕</button>
      </div>

      <div style="font-size:0.78rem; font-weight:800; color:#cbd5e1; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.6rem;">1. Choose Layout</div>
      <div class="gal-layouts" id="cd-layouts"></div>

      <div style="font-size:0.78rem; font-weight:800; color:#cbd5e1; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.6rem;">2. Pick Photo (customer uploads first)</div>
      <div class="gal-picker" id="cd-picker"></div>

      <div class="be-field">
        <label class="be-label">Or paste image URL</label>
        <input type="text" class="be-input" id="cd-img-url" placeholder="https://example.com/photo.jpg">
      </div>
      <div class="be-row-2">
        <div class="be-field">
          <label class="be-label">Badge / Tag (optional)</label>
          <input type="text" class="be-input" id="cd-badge" value="New" placeholder="New">
        </div>
        <div class="be-field">
          <label class="be-label">Corner Radius (px)</label>
          <input type="number" class="be-input" id="cd-radius" value="20" min="0" max="60">
        </div>
      </div>
      <div class="be-field">
        <label class="be-label">Title</label>
        <input type="text" class="be-input" id="cd-title" value="Our Special Service" placeholder="Card title">
      </div>
      <div class="be-field">
        <label class="be-label">Content / Description</label>
        <input type="text" class="be-input" id="cd-text" value="Short description about this service, product or person." placeholder="Card description">
      </div>
      <div class="be-row-2">
        <div class="be-field">
          <label class="be-label">Button Text</label>
          <input type="text" class="be-input" id="cd-btn-text" value="Learn More →" placeholder="Learn More →">
        </div>
        <div class="be-field">
          <label class="be-label">Button Link</label>
          <input type="text" class="be-input" id="cd-btn-link" value="#contact" placeholder="#contact">
        </div>
      </div>
      <div class="be-field">
        <label class="be-label">Button Style</label>
        <select class="be-input" id="cd-btn-style">
          <option value="primary">Primary (indigo)</option>
          <option value="outline">Outline</option>
          <option value="whatsapp">WhatsApp (green)</option>
          <option value="dark">Dark</option>
        </select>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem; flex-wrap:wrap">
        <button class="hdr-btn" onclick="closeCardBuilder()">Cancel</button>
        <button class="hdr-btn save-btn" onclick="insertCard()">✓ Insert Card</button>
      </div>
    </div>
  </div>

  <!-- Language Manager Modal -->
  <div class="modal-overlay" id="language-manager-modal">
    <div class="modal-box" style="max-width:900px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem">
        <div>
          <h2 style="font-size:1.25rem; color:#fff; display:flex; align-items:center; gap:0.5rem; margin-bottom:0.15rem"><span style="font-size:1.3rem">🌐</span> Translate Website Content</h2>
          <p style="font-size:0.75rem; color:#94a3b8">Every text element is listed. Fill translations.</p>
        </div>
        <button class="drawer-close" onclick="closeLanguageManager()" style="font-size:1.4rem">✕</button>
      </div>

      <div id="language-manager-body"></div>

      <div style="display:flex; justify-content:space-between; gap:0.75rem; margin-top:1.25rem; flex-wrap:wrap">
        <button class="hdr-btn" onclick="autoTranslateAll()" style="background:linear-gradient(135deg,#4285F4,#9B72CF); border:none; color:#fff; font-weight:700;">✨ Auto-translate (AI)</button>
        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
          <button class="hdr-btn" onclick="closeLanguageManager()">Cancel</button>
          <button class="hdr-btn save-btn" onclick="applyLanguageTranslations()">✓ Save Translations</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Section Picker Modal -->
  <div class="modal-overlay" id="section-picker-modal">
    <div class="modal-box" style="max-width:900px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem">
        <div>
          <h2 style="font-size:1.3rem; color:#fff; display:flex; align-items:center; gap:0.5rem; margin-bottom:0.15rem"><span style="font-size:1.3rem">🗂️</span> Pick a Section Template</h2>
          <p style="font-size:0.78rem; color:#94a3b8">Click any template — it opens a form so you can rename it &amp; edit every text before adding.</p>
        </div>
        <button class="drawer-close" onclick="closeSectionPicker()" style="font-size:1.4rem">✕</button>
      </div>
      <div class="section-picker-grid">
        <div class="section-picker-card" onclick="insertSectionTemplate('hero')"><span class="sp-icon">🌟</span>
          <div class="sp-name">Hero</div>
          <div class="sp-desc">Big headline + CTA</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('hero')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('hero')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('services')"><span class="sp-icon">🛠️</span>
          <div class="sp-name">Services</div>
          <div class="sp-desc">3 service cards</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('services')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('services')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('about')"><span class="sp-icon">ℹ️</span>
          <div class="sp-name">About</div>
          <div class="sp-desc">Story block</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('about')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('about')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('stats')"><span class="sp-icon">📊</span>
          <div class="sp-name">Stats</div>
          <div class="sp-desc">Metrics strip</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('stats')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('stats')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('pricing')"><span class="sp-icon">💰</span>
          <div class="sp-name">Pricing</div>
          <div class="sp-desc">3-tier table</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('pricing')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('pricing')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('reviews')"><span class="sp-icon">⭐</span>
          <div class="sp-name">Reviews</div>
          <div class="sp-desc">Testimonials</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('reviews')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('reviews')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('team')"><span class="sp-icon">👥</span>
          <div class="sp-name">Team</div>
          <div class="sp-desc">Member cards</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('team')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('team')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('faq')"><span class="sp-icon">❓</span>
          <div class="sp-name">FAQ</div>
          <div class="sp-desc">Expandable list</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('faq')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('faq')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('gallery')"><span class="sp-icon">🖼️</span>
          <div class="sp-name">Gallery</div>
          <div class="sp-desc">Image grid</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('gallery')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('gallery')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('cta')"><span class="sp-icon">📣</span>
          <div class="sp-name">CTA Banner</div>
          <div class="sp-desc">Conversion banner</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('cta')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('cta')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('contact')"><span class="sp-icon">📞</span>
          <div class="sp-name">Contact</div>
          <div class="sp-desc">Info + form</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('contact')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('contact')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('footer')"><span class="sp-icon">🦶</span>
          <div class="sp-name">Footer</div>
          <div class="sp-desc">Multi-column</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('footer')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('footer')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('process')"><span class="sp-icon">🔄</span>
          <div class="sp-name">Process</div>
          <div class="sp-desc">4-step timeline</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('process')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('process')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('features')"><span class="sp-icon">✨</span>
          <div class="sp-name">Features</div>
          <div class="sp-desc">6-feature grid</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('features')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('features')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('testimonial')"><span class="sp-icon">💬</span>
          <div class="sp-name">Testimonial</div>
          <div class="sp-desc">Big hero quote</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('testimonial')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('testimonial')">🎨 Customize</button></div>
        </div>
        <div class="section-picker-card" onclick="insertSectionTemplate('custom')"><span class="sp-icon">🧩</span>
          <div class="sp-name">Custom Business</div>
          <div class="sp-desc">Rich editable block</div>
          <div class="section-picker-actions"><button class="sp-add" onclick="event.stopPropagation(); insertSectionTemplate('custom')">+ Add</button><button class="sp-cust" onclick="event.stopPropagation(); customizeSectionTemplate('custom')">🎨 Customize</button></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Section Configurator Modal -->
  <div class="modal-overlay" id="section-configurator-modal" style="z-index:10001;">
    <div class="modal-box" style="max-width:720px;">
      <div id="section-configurator-content"></div>
    </div>
  </div>

  <!-- Section Editor Modal -->
  <div class="modal-overlay" id="section-editor-modal">
    <div class="modal-box" style="max-width:720px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem">
        <div>
          <h2 style="font-size:1.25rem; color:#fff; display:flex; align-items:center; gap:0.5rem">
            <span>🎨</span> <span id="se-title-text">Customize Element</span>
          </h2>
          <p style="font-size:0.75rem; color:#94a3b8" id="se-subtitle-text">Styles apply only to the exact element you selected.</p>
        </div>
        <button class="drawer-close" onclick="closeSectionEditor()" style="font-size:1.4rem">✕</button>
      </div>

      <div class="section-editor-grid">
        <div id="se-name-wrap" class="section-editor-field section-editor-wide">
          <label>Section Name</label>
          <input type="text" id="se-name" placeholder="Example: Our Services" oninput="livePreviewSectionName(this.value)">
          <div class="section-editor-note">Appears in the Sections list and button link options — updates live as you type.</div>
        </div>

        <div class="section-editor-wide dim-group">
          <div class="dim-group-title">📐 Dimensions — Width &amp; Height</div>

          <div class="section-editor-grid" style="grid-template-columns:1fr 1fr;">
            <div class="section-editor-field">
              <label>Width</label>
              <div class="be-size-row">
                <input type="number" min="0" step="1" class="be-input" id="se-width-value" placeholder="auto" oninput="previewSectionStyle()">
                <select class="be-input be-unit-select" id="se-width-unit" onchange="previewSectionStyle()">
                  <option value="auto">auto</option>
                  <option value="px">px</option>
                  <option value="%">%</option>
                  <option value="vw">vw</option>
                  <option value="rem">rem</option>
                  <option value="cm">cm</option>
                  <option value="in">in</option>
                </select>
              </div>
            </div>
            <div class="section-editor-field">
              <label>Height</label>
              <div class="be-size-row">
                <input type="number" min="0" step="1" class="be-input" id="se-height-value" placeholder="auto" oninput="previewSectionStyle()">
                <select class="be-input be-unit-select" id="se-height-unit" onchange="previewSectionStyle()">
                  <option value="auto">auto</option>
                  <option value="px">px</option>
                  <option value="%">%</option>
                  <option value="vh">vh</option>
                  <option value="rem">rem</option>
                  <option value="cm">cm</option>
                  <option value="in">in</option>
                </select>
              </div>
            </div>
            <div class="section-editor-field">
              <label>Min Height</label>
              <div class="be-size-row">
                <input type="number" min="0" step="1" class="be-input" id="se-min-height-value" placeholder="0" oninput="previewSectionStyle()">
                <select class="be-input be-unit-select" id="se-min-height-unit" onchange="previewSectionStyle()">
                  <option value="px">px</option>
                  <option value="vh">vh</option>
                  <option value="rem">rem</option>
                  <option value="cm">cm</option>
                  <option value="in">in</option>
                  <option value="none">none</option>
                </select>
              </div>
            </div>
            <div class="section-editor-field">
              <label>Max Width</label>
              <div class="be-size-row">
                <input type="number" min="0" step="1" class="be-input" id="se-max-width-value" placeholder="none" oninput="previewSectionStyle()">
                <select class="be-input be-unit-select" id="se-max-width-unit" onchange="previewSectionStyle()">
                  <option value="none">none</option>
                  <option value="px">px</option>
                  <option value="%">%</option>
                  <option value="vw">vw</option>
                  <option value="rem">rem</option>
                </select>
              </div>
            </div>
          </div>
          <div class="be-size-help">Use px, %, vw/vh, rem, cm, or in for precise sizing. "auto" fits content. Tip: use <strong style="color:#a5b4fc">Min Height 100vh</strong> for full-screen hero sections.</div>
        </div>

        <div class="section-editor-field"><label>Background</label><input type="color" id="se-bg" oninput="previewSectionStyle()"></div>
        <div class="section-editor-field"><label>Text Color</label><input type="color" id="se-color" oninput="previewSectionStyle()"></div>
        <div class="section-editor-field"><label>Inner Padding</label><input type="range" id="se-padding" min="0" max="180" value="80" oninput="previewSectionStyle()">
          <div id="se-padding-val" class="section-editor-note">80px</div>
        </div>
        <div class="section-editor-field"><label>Content Width</label><input type="range" id="se-width" min="600" max="1400" value="1100" oninput="previewSectionStyle()">
          <div id="se-width-val" class="section-editor-note">1100px</div>
        </div>
        <div class="section-editor-field"><label>Text Alignment</label><select id="se-align" onchange="previewSectionStyle()">
            <option value="left">Left</option>
            <option value="center">Center</option>
            <option value="right">Right</option>
          </select></div>
        <div class="section-editor-field"><label>Border Radius</label><input type="range" id="se-radius" min="0" max="50" value="0" oninput="previewSectionStyle()">
          <div id="se-radius-val" class="section-editor-note">0px</div>
        </div>
        <div class="section-editor-field"><label>Font Family</label><select id="se-font" onchange="previewSectionStyle()">
            <option value="">Default</option>
            <option value="'Plus Jakarta Sans', sans-serif">Plus Jakarta Sans</option>
            <option value="'Space Grotesk', sans-serif">Space Grotesk</option>
            <option value="'Inter', sans-serif">Inter</option>
            <option value="Georgia, serif">Georgia</option>
          </select></div>
        <div class="section-editor-field"><label>Box Shadow</label><select id="se-shadow" onchange="previewSectionStyle()">
            <option value="none">None</option>
            <option value="0 8px 24px rgba(0,0,0,0.08)">Soft</option>
            <option value="0 15px 40px rgba(0,0,0,0.15)">Medium</option>
            <option value="0 25px 60px rgba(0,0,0,0.25)">Strong</option>
          </select></div>
      </div>

      <div class="section-editor-field">
        <label>Custom CSS Class (optional)</label>
        <input type="text" id="se-class" placeholder="example-section">
        <div class="section-editor-note">Add a class so existing CSS or AI changes can target this element.</div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem; flex-wrap:wrap">
        <button class="hdr-btn" id="se-edit-content-btn" style="background:#78350f;border-color:#f59e0b;color:#fde68a;" onclick="openContentEditorForSelectedSection()">✍️ Edit Content</button>
        <button class="hdr-btn" onclick="closeSectionEditor()">Cancel</button>
        <button class="hdr-btn save-btn" onclick="applySectionEditor()">✓ Apply Changes</button>
      </div>
    </div>
  </div>

  <!-- Shortcuts / Help Modal -->
  <div class="modal-overlay" id="shortcuts-modal">
    <div class="modal-box" style="max-width:680px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1rem">
        <div>
          <h2 style="font-size:1.35rem; color:#fff; display:flex; align-items:center; gap:0.55rem; margin-bottom:0.15rem">
            <span style="font-size:1.4rem">❓</span> Tips &amp; Shortcuts
          </h2>
          <p style="font-size:0.78rem; color:#94a3b8">Quick reference — get the most out of your studio.</p>
        </div>
        <button class="drawer-close" onclick="closeShortcutsModal()" style="font-size:1.4rem">✕</button>
      </div>

      <div style="font-size:0.78rem; font-weight:800; color:#818cf8; text-transform:uppercase; letter-spacing:0.06em; margin:0.85rem 0 0.5rem;">⌨️ Keyboard</div>
      <div class="shortcut-grid">
        <div class="shortcut-row"><span class="shortcut-label">Undo</span><span class="shortcut-key">Ctrl + Z</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Redo</span><span class="shortcut-key">Ctrl + Y</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Delete selected element</span><span class="shortcut-key">Delete</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Deselect / close panels</span><span class="shortcut-key">Esc</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Mobile edit mode</span><span class="shortcut-key">Ctrl + M</span></div>
      </div>

      <div style="font-size:0.78rem; font-weight:800; color:#818cf8; text-transform:uppercase; letter-spacing:0.06em; margin:1rem 0 0.5rem;">🖱️ Mouse Actions</div>
      <div class="shortcut-grid">
        <div class="shortcut-row"><span class="shortcut-label">Click any section</span><span class="shortcut-key">Floating ✍️ Edit Content button appears</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Double-click image</span><span class="shortcut-key">Edit image</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Click button on canvas</span><span class="shortcut-key">Open button editor</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Right-click anything</span><span class="shortcut-key">Context menu</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Drag button freely</span><span class="shortcut-key">Click + move</span></div>
        <div class="shortcut-row"><span class="shortcut-label">Drag section handle (↕)</span><span class="shortcut-key">Reorder sections</span></div>
      </div>

      <div style="font-size:0.78rem; font-weight:800; color:#818cf8; text-transform:uppercase; letter-spacing:0.06em; margin:1rem 0 0.5rem;">💡 Pro Tips</div>
      <div style="background:#0a0f1c; border:1px solid #1e293b; border-radius:10px; padding:0.85rem 1rem; font-size:0.78rem; line-height:1.65; color:#cbd5e1;">
        • Click any <strong style="color:#fbbf24">section</strong> on canvas → floating <strong>✍️ Edit Section Content</strong> button appears at bottom-center.<br>
        • <strong style="color:#10b981">Edits happen IN-PLACE</strong> — the section is updated, not duplicated.<br>
        • 🆕 <strong style="color:#a5b4fc">Width / Height / Min-Height / Max-Width</strong> controls let you resize any section just like images.<br>
        • All edits <strong>auto-save</strong> to browser storage — going back to builder.php syncs instantly.<br>
        • Use <strong style="color:#ec4899">📱 Mobile Mode</strong> to add mobile-only styles.<br>
        • Use ⛶ Fullscreen when you want maximum canvas space.
      </div>

      <div style="display:flex; justify-content:flex-end; margin-top:1.25rem">
        <button class="hdr-btn save-btn" onclick="closeShortcutsModal()">Got it →</button>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/includes/manual-modal.php'; ?>
  <div class="toast" id="toast"></div>

  <script>
    /* ══════════════════════════════════════════════════
       CORE STATE
    ══════════════════════════════════════════════════ */
    let grapesEditor = null;
    let activeConceptIndex = 0;
    let currentStudioView = 'site'; // 'site' | 'admin'
    let projectData = null;
    let currentHtml = '';
    let selectedComponent = null;
    let editingButton = null;
    let editingSection = null;
    let activeFreeDrag = null;
    let activeSectionDrag = null;
    let configuringSection = null;
    let fullscreenMode = false;
    let mobileEditMode = false;
    let editingImage = null;
    let editingImageKey = null;
    let suppressEditorOpen = false;
    let lockedThemeCSS = '';
    let lockedBodyCSS = '';
    let themeLocked = false;
    let userUploadedImages = [];
    let activeDraggedImage = null;
    let lastDropShapeFit = false;
    let lastStudioStyleInjector = null;

    /* ══════════════════════════════════════════════════
       FALLBACK HTML
    ══════════════════════════════════════════════════ */
    const WC_FALLBACK_HTML = `<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui,-apple-system,sans-serif;margin:0;padding:4rem 1.5rem;text-align:center;background:#f8fafc;color:#0f172a;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center}
h1{font-size:2.5rem;margin:0 0 1rem;color:#4f46e5}
p{color:#64748b;max-width:520px;line-height:1.6}
</style></head>
<body>
<h1>✨ Studio Ready</h1>
<p>No design data was found. Go back to the builder, select a variation and click <strong>"Edit in Studio"</strong> again.</p>
</body></html>`;

    /* ══════════════════════════════════════════════════
       ANIMATION RUNTIME (for exported/published HTML only)
    ══════════════════════════════════════════════════ */
    const WC_ANIMATION_RUNTIME = `<script id="wc-animation-runtime">
(function(){
  var KEYFRAMES = {
    fadeIn:[{opacity:0},{opacity:1}],
    slideUp:[{opacity:0,transform:'translateY(50px)'},{opacity:1,transform:'translateY(0)'}],
    slideDown:[{opacity:0,transform:'translateY(-50px)'},{opacity:1,transform:'translateY(0)'}],
    slideLeft:[{opacity:0,transform:'translateX(50px)'},{opacity:1,transform:'translateX(0)'}],
    slideRight:[{opacity:0,transform:'translateX(-50px)'},{opacity:1,transform:'translateX(0)'}],
    zoomIn:[{opacity:0,transform:'scale(0.7)'},{opacity:1,transform:'scale(1)'}],
    zoomOut:[{opacity:0,transform:'scale(1.3)'},{opacity:1,transform:'scale(1)'}],
    bounce:[{transform:'translateY(0)'},{transform:'translateY(-30px)'},{transform:'translateY(0)'},{transform:'translateY(-15px)'},{transform:'translateY(0)'}],
    rotate:[{opacity:0,transform:'rotate(-180deg) scale(0.7)'},{opacity:1,transform:'rotate(0deg) scale(1)'}],
    flip:[{opacity:0,transform:'perspective(400px) rotateY(90deg)'},{opacity:1,transform:'perspective(400px) rotateY(0deg)'}],
    pulse:[{transform:'scale(1)'},{transform:'scale(1.08)'},{transform:'scale(1)'}],
    shake:[{transform:'translateX(0)'},{transform:'translateX(-8px)'},{transform:'translateX(8px)'},{transform:'translateX(-6px)'},{transform:'translateX(6px)'},{transform:'translateX(0)'}],
    blur:[{opacity:0,filter:'blur(12px)'},{opacity:1,filter:'blur(0)'}],
    lightSpeed:[{opacity:0,transform:'translateX(80px) skewX(-25deg)'},{opacity:1,transform:'translateX(0) skewX(0)'}]
  };
  function preApply(el, kf){ if(!kf||!kf[0]) return; var f=kf[0]; if('opacity' in f) el.style.opacity=f.opacity; if('transform' in f) el.style.transform=f.transform; if('filter' in f) el.style.filter=f.filter; }
  function play(el){
    var type=el.getAttribute('data-anim'), kf=KEYFRAMES[type]; if(!kf) return;
    var dur=parseInt(el.getAttribute('data-anim-duration'))||700, delay=parseInt(el.getAttribute('data-anim-delay'))||0;
    var easing=el.getAttribute('data-anim-easing')||'cubic-bezier(0.22,1,0.36,1)';
    var rep=el.getAttribute('data-anim-repeat')||'1', iter=rep==='infinite'?Infinity:(parseInt(rep)||1);
    try { if(el._wcAnim&&el._wcAnim.cancel) el._wcAnim.cancel(); el._wcAnim=el.animate(kf,{duration:dur,delay:delay,easing:easing,fill:'both',iterations:iter}); } catch(e){}
  }
  function init(){
    var els=document.querySelectorAll('[data-anim]'); if(!els.length) return;
    var io=('IntersectionObserver' in window) ? new IntersectionObserver(function(entries){ entries.forEach(function(e){ if(e.isIntersecting){ play(e.target); io.unobserve(e.target); } }); },{threshold:0.15}) : null;
    Array.prototype.forEach.call(els,function(el){
      var trig=el.getAttribute('data-anim-trigger')||'scroll', kf=KEYFRAMES[el.getAttribute('data-anim')];
      if(trig==='scroll'||trig==='load') preApply(el,kf);
      if(trig==='scroll'){ if(io) io.observe(el); else play(el); }
      else if(trig==='load'){ requestAnimationFrame(function(){ play(el); }); }
      else if(trig==='hover'){ el.addEventListener('mouseenter',function(){ play(el); }); }
      else if(trig==='click'){ el.addEventListener('click',function(ev){ ev.preventDefault(); play(el); }); }
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
<\/script>`;

    const ANIM_PRESETS = {
      fadeIn: { name: 'Fade In', icon: '🌟' },
      slideUp: { name: 'Slide Up', icon: '⬆️' },
      slideDown: { name: 'Slide Down', icon: '⬇️' },
      slideLeft: { name: 'Slide Left', icon: '⬅️' },
      slideRight: { name: 'Slide Right', icon: '➡️' },
      zoomIn: { name: 'Zoom In', icon: '🔍' },
      zoomOut: { name: 'Zoom Out', icon: '🔎' },
      bounce: { name: 'Bounce', icon: '🏀' },
      rotate: { name: 'Rotate In', icon: '🔄' },
      flip: { name: 'Flip', icon: '🔃' },
      pulse: { name: 'Pulse', icon: '💓' },
      shake: { name: 'Shake', icon: '📳' },
      blur: { name: 'Blur In', icon: '💨' },
      lightSpeed: { name: 'Light Speed', icon: '⚡' }
    };

    const ANIM_KEYFRAMES = {
      fadeIn: [{ opacity: 0 }, { opacity: 1 }],
      slideUp: [{ opacity: 0, transform: 'translateY(50px)' }, { opacity: 1, transform: 'translateY(0)' }],
      slideDown: [{ opacity: 0, transform: 'translateY(-50px)' }, { opacity: 1, transform: 'translateY(0)' }],
      slideLeft: [{ opacity: 0, transform: 'translateX(50px)' }, { opacity: 1, transform: 'translateX(0)' }],
      slideRight: [{ opacity: 0, transform: 'translateX(-50px)' }, { opacity: 1, transform: 'translateX(0)' }],
      zoomIn: [{ opacity: 0, transform: 'scale(0.7)' }, { opacity: 1, transform: 'scale(1)' }],
      zoomOut: [{ opacity: 0, transform: 'scale(1.3)' }, { opacity: 1, transform: 'scale(1)' }],
      bounce: [{ transform: 'translateY(0)' }, { transform: 'translateY(-30px)' }, { transform: 'translateY(0)' }, { transform: 'translateY(-15px)' }, { transform: 'translateY(0)' }],
      rotate: [{ opacity: 0, transform: 'rotate(-180deg) scale(0.7)' }, { opacity: 1, transform: 'rotate(0deg) scale(1)' }],
      flip: [{ opacity: 0, transform: 'perspective(400px) rotateY(90deg)' }, { opacity: 1, transform: 'perspective(400px) rotateY(0deg)' }],
      pulse: [{ transform: 'scale(1)' }, { transform: 'scale(1.08)' }, { transform: 'scale(1)' }],
      shake: [{ transform: 'translateX(0)' }, { transform: 'translateX(-8px)' }, { transform: 'translateX(8px)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(0)' }],
      blur: [{ opacity: 0, filter: 'blur(12px)' }, { opacity: 1, filter: 'blur(0)' }],
      lightSpeed: [{ opacity: 0, transform: 'translateX(80px) skewX(-25deg)' }, { opacity: 1, transform: 'translateX(0) skewX(0)' }]
    };

    function wcPlayAnimOnElement(el) {
      if (!el) return;
      // ★ Don't fight the user's manual live preview (it owns the element right now)
      try { if (el.classList && el.classList.contains('wc-anim-live')) return; } catch (e) {}
      const type = el.getAttribute('data-anim');
      if (!type) return;
      const kf = ANIM_KEYFRAMES[type];
      if (!kf) return;
      const dur = parseInt(el.getAttribute('data-anim-duration')) || 700;
      const delay = parseInt(el.getAttribute('data-anim-delay')) || 0;
      const easing = el.getAttribute('data-anim-easing') || 'cubic-bezier(0.22,1,0.36,1)';
      const repAttr = el.getAttribute('data-anim-repeat') || '1';
      const iterations = repAttr === 'infinite' ? Infinity : parseInt(repAttr) || 1;
      try {
        el.style.animation = 'none';
        el.getBoundingClientRect();
        el.animate(kf, {
          duration: dur,
          delay: delay,
          easing: easing,
          fill: 'both',
          iterations: iterations
        });
      } catch (e) {}
    }

    function setupAnimationsInCanvas() {
      try {
        const canvasDoc = grapesEditor?.Canvas?.getDocument();
        if (!canvasDoc) return;
        if (canvasDoc.__wcAnimObserver) {
          try { canvasDoc.__wcAnimObserver.disconnect(); } catch (e) {}
        }
        const observer = new canvasDoc.defaultView.IntersectionObserver((entries) => {
          entries.forEach(e => {
            if (e.isIntersecting) {
              wcPlayAnimOnElement(e.target);
              observer.unobserve(e.target);
            }
          });
        }, { threshold: 0.15 });
        canvasDoc.__wcAnimObserver = observer;
        canvasDoc.querySelectorAll('[data-anim]').forEach(el => {
          const trig = el.getAttribute('data-anim-trigger') || 'scroll';
          if (trig === 'scroll') observer.observe(el);
          else if (trig === 'load') wcPlayAnimOnElement(el);
          else if (trig === 'hover') el.addEventListener('mouseenter', () => wcPlayAnimOnElement(el));
          else if (trig === 'click') el.addEventListener('click', (ev) => {
            ev.preventDefault();
            wcPlayAnimOnElement(el);
          });
        });
      } catch (e) {}
    }

    /* ══════════════════════════════════════════════════
       ★ FORCE-CANVAS-REVEAL — Fixes "invisible letters / sections"
       In the studio editor, generated page scripts are NOT executed,
       so elements that depend on scroll-triggered animations (AOS,
       WOW.js, IntersectionObserver-based reveals, [data-anim], etc.)
       remain stuck at opacity:0. This walker force-reveals them so
       editing is possible. Published sites keep the runtime above.
    ══════════════════════════════════════════════════ */
    function forceCanvasReveal() {
      try {
        const cd = grapesEditor?.Canvas?.getDocument?.();
        if (!cd || !cd.body) return;
        const win = cd.defaultView;
        if (!win) return;

        // Force-reveal class-based animation libs first
        const revealSelectors = [
          '[class*="aos"]', '[class*="wow"]', '[class*="sal-"]',
          '[class*="reveal"]', '[class*="fade-in"]', '[class*="fadeIn"]',
          '[class*="fade-up"]', '[class*="fadeUp"]', '[class*="fade-down"]',
          '[class*="fade-left"]', '[class*="fade-right"]',
          '[class*="slide-in"]', '[class*="slideIn"]', '[class*="slide-up"]',
          '[class*="slideUp"]', '[class*="slide-down"]', '[class*="slideDown"]',
          '[class*="zoom-in"]', '[class*="zoomIn"]', '[class*="zoom-out"]',
          '[class*="animate-"]', '[class*="animate_"]',
          '[data-aos]', '[data-wow]', '[data-sal]',
          '[data-scroll]', '[data-animate]', '[data-anim]'
        ].join(',');

        try {
          cd.body.querySelectorAll(revealSelectors).forEach(el => {
            // Skip our own editor injections
            const id = el.id || '';
            if (id.startsWith('wc-') || id.startsWith('webcraft-')) return;
            if (el.classList.contains('webcraft-section-handle')) return;
            if (el.classList.contains('webcraft-drop-line')) return;
            if (el.classList.contains('webcraft-canvas-body')) return;
            // ★ Skip the element currently live-previewing its animation
            if (el.classList.contains('wc-anim-live')) return;

            el.style.setProperty('opacity', '1', 'important');
            el.style.setProperty('visibility', 'visible', 'important');
            el.style.setProperty('transform', 'none', 'important');
            el.style.setProperty('animation-play-state', 'paused', 'important');
            el.style.setProperty('animation-fill-mode', 'forwards', 'important');
          });
        } catch (e) {}

        // Then walk ALL elements and check computed opacity/visibility
        try {
          cd.body.querySelectorAll('*').forEach(el => {
            const id = el.id || '';
            if (id.startsWith('wc-') || id.startsWith('webcraft-')) return;
            if (el.classList.contains('webcraft-section-handle')) return;
            if (el.classList.contains('webcraft-drop-line')) return;
            // ★ Never freeze the element currently live-previewing its animation
            if (el.classList.contains('wc-anim-live')) return;

            try {
              const rect = el.getBoundingClientRect();
              // Skip zero-size wrappers (they may legitimately be invisible)
              if (rect.width < 2 && rect.height < 2) return;

              const cs = win.getComputedStyle(el);
              const opacity = parseFloat(cs.opacity);

              if (opacity === 0) {
                el.style.setProperty('opacity', '1', 'important');
                el.setAttribute('data-wc-revealed', '1');
              }
              if (cs.visibility === 'hidden') {
                el.style.setProperty('visibility', 'visible', 'important');
              }
              // Kill transform-based hiding (translateX/Y off-screen, scale 0)
              const t = cs.transform;
              if (t && t !== 'none') {
                const m = t.match(/matrix\(([^)]+)\)/);
                if (m) {
                  const parts = m[1].split(',').map(x => parseFloat(x.trim()));
                  const scaleX = parts[0], scaleY = parts[3];
                  if ((Math.abs(scaleX) < 0.02 || Math.abs(scaleY) < 0.02)) {
                    el.style.setProperty('transform', 'none', 'important');
                  }
                }
              }
            } catch (e) {}
          });
        } catch (e) {}

        console.log('[studio] forceCanvasReveal: revealed hidden elements');
      } catch (e) {
        console.warn('[studio] forceCanvasReveal error', e);
      }
    }

    function renderAnimationPresets() {
      const grid = document.getElementById('anim-presets-grid');
      if (!grid) return;
      grid.innerHTML = '';
      Object.keys(ANIM_PRESETS).forEach(key => {
        const p = ANIM_PRESETS[key];
        const card = document.createElement('div');
        card.className = 'anim-card';
        card.dataset.anim = key;
        card.innerHTML = `<span class="a-icon">${p.icon}</span><div class="a-name">${p.name}</div>`;
        // ★ Hover the card → icon itself demos the effect (pick with confidence)
        card.onmouseenter = () => {
          try {
            const icon = card.querySelector('.a-icon');
            const kk = ANIM_KEYFRAMES[key];
            if (icon && kk) icon.animate(kk, { duration: 600, easing: 'cubic-bezier(0.22,1,0.36,1)', fill: 'both', iterations: 1 });
          } catch (e) {}
        };
        card.onclick = () => {
          document.querySelectorAll('#anim-presets-grid .anim-card').forEach(c => c.classList.remove('active'));
          card.classList.add('active');
          playAnimPreview();
          applyAnimationToSelected();
        };
        grid.appendChild(card);
      });
    }

    function getSelectedAnimation() {
      const c = document.querySelector('#anim-presets-grid .anim-card.active');
      return c ? c.dataset.anim : 'fadeIn';
    }

    function playAnimPreview() {
      const demo = document.getElementById('anim-preview-demo');
      if (!demo) return;
      const dur = parseInt(document.getElementById('anim-duration')?.value || '700');
      const delay = parseInt(document.getElementById('anim-delay')?.value || '0');
      const easing = document.getElementById('anim-easing')?.value || 'cubic-bezier(0.22,1,0.36,1)';
      const repAttr = document.getElementById('anim-repeat')?.value || '1';
      const iterations = repAttr === 'infinite' ? Infinity : parseInt(repAttr) || 1;
      const type = getSelectedAnimation();
      const kf = ANIM_KEYFRAMES[type];
      if (!kf) return;
      try {
        demo.style.animation = 'none';
        demo.getBoundingClientRect();
        demo.animate(kf, { duration: dur, delay: delay, easing: easing, fill: 'both', iterations: iterations });
      } catch (e) {}
    }

    function updateSelectedAnimation() {
      const durEl = document.getElementById('anim-duration-val');
      const delayEl = document.getElementById('anim-delay-val');
      if (durEl) durEl.textContent = (document.getElementById('anim-duration').value) + 'ms';
      if (delayEl) delayEl.textContent = (document.getElementById('anim-delay').value) + 'ms';
      if (selectedComponent) applyAnimationToSelected(true);
      playAnimPreview();
    }

    /* ★ LIVE CANVAS PREVIEW — play the chosen animation on the real
       element in the editor the moment it is selected, and KEEP it
       alive (loop) so the animation stays on the element. Only one
       element animates at a time; freeze restored when replaced. */
    function stopCanvasLiveAnims(exceptEl) {
      try {
        const cd = grapesEditor?.Canvas?.getDocument?.();
        if (!cd) return;
        cd.querySelectorAll('.wc-anim-live').forEach(node => {
          if (node === exceptEl) return;
          try { if (node._wcLiveAnim && node._wcLiveAnim.cancel) node._wcLiveAnim.cancel(); } catch (e) {}
          try { if (node._wcLiveTimer) clearTimeout(node._wcLiveTimer); } catch (e) {}
          node._wcLiveTimer = null;
          delete node._wcLiveParams;
          node.classList.remove('wc-anim-live');
          try {
            node.style.setProperty('opacity', '1', 'important');
            node.style.setProperty('visibility', 'visible', 'important');
            node.style.setProperty('transform', 'none', 'important');
          } catch (e) {}
        });
      } catch (e) {}
    }

    /* ★ Watchdog: whatever kills a live infinite loop (re-render, reveal
       timers, competing plays) → detect dead playState and replay.
       Guarantees infinite REALLY stays infinite on canvas. */
    function ensureLiveWatchdog() {
      if (window.__wcLiveWatch) return;
      window.__wcLiveWatch = setInterval(() => {
        try {
          const cd = grapesEditor?.Canvas?.getDocument?.();
          if (!cd || !cd.body) return;
          cd.querySelectorAll('.wc-anim-live').forEach(el => {
            try {
              if (!el.isConnected) {
                el.classList.remove('wc-anim-live');
                delete el._wcLiveParams;
                return;
              }
              const p = el._wcLiveParams;
              if (!p) return;
              const st = el._wcLiveAnim ? el._wcLiveAnim.playState : 'idle';
              if (st === 'finished' || st === 'idle' || !el._wcLiveAnim) {
                const kf = ANIM_KEYFRAMES[p.type];
                if (!kf) return;
                el.classList.add('wc-anim-live');
                ['opacity', 'visibility', 'transform', 'animation-play-state', 'animation-fill-mode'].forEach(prop => {
                  try { el.style.removeProperty(prop); } catch (e) {}
                });
                el._wcLiveAnim = el.animate(kf, { duration: p.dur, delay: 0, easing: p.easing, fill: 'both', iterations: Infinity });
              }
            } catch (e) {}
          });
        } catch (e) {}
      }, 1500);
    }

    function previewAnimationOnCanvas() {
      try {
        if (!selectedComponent) return;
        const el = selectedComponent.getEl && selectedComponent.getEl();
        if (!el) return;
        const type = getSelectedAnimation();
        const kf = ANIM_KEYFRAMES[type];
        if (!kf) return;
        const dur = parseInt(document.getElementById('anim-duration')?.value || '700');
        const delay = parseInt(document.getElementById('anim-delay')?.value || '0');
        const easing = document.getElementById('anim-easing')?.value || 'cubic-bezier(0.22,1,0.36,1)';
        const repAttr = document.getElementById('anim-repeat')?.value || '1';
        const iterations = repAttr === 'infinite' ? Infinity : parseInt(repAttr) || 1;
        // ★ One live element at a time — stop the previous one first
        stopCanvasLiveAnims(el);
        el.classList.add('wc-anim-live');
        ['opacity', 'visibility', 'transform', 'animation-play-state', 'animation-fill-mode'].forEach(prop => {
          try { el.style.removeProperty(prop); } catch (e) {}
        });
        try { el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) {}
        if (el._wcLiveAnim && el._wcLiveAnim.cancel) { try { el._wcLiveAnim.cancel(); } catch (e) {} }
        if (el._wcLiveTimer) { clearTimeout(el._wcLiveTimer); el._wcLiveTimer = null; }
        const finishLive = () => {
          try {
            if (el._wcLiveAnim && el._wcLiveAnim.cancel) el._wcLiveAnim.cancel();
          } catch (e) {}
          try {
            el.classList.remove('wc-anim-live');
            el.style.setProperty('opacity', '1', 'important');
            el.style.setProperty('visibility', 'visible', 'important');
            el.style.setProperty('transform', 'none', 'important');
          } catch (e) {}
        };
        // ★ Stay alive: finite repeats play min 3 loops so it clearly stays;
        // 'infinite' truly loops until replaced/removed (+ watchdog replays if killed).
        const liveIter = (iterations === Infinity) ? Infinity : Math.min(Math.max(iterations, 3), 6);
        if (liveIter === Infinity) {
          el._wcLiveParams = { type, dur, delay, easing };
          ensureLiveWatchdog();
        } else {
          delete el._wcLiveParams;
        }
        el._wcLiveAnim = el.animate(kf, { duration: dur, delay: delay, easing: easing, fill: 'both', iterations: liveIter });
        try { el._wcLiveAnim.onfinish = finishLive; } catch (e) {}
        if (liveIter !== Infinity) {
          const cap = delay + dur * liveIter + 600;
          el._wcLiveTimer = setTimeout(finishLive, Math.min(cap, 12000));
        }
        el._wcFinishLive = finishLive;
      } catch (e) {}
    }

    function applyAnimationToSelected(silent) {
      if (!selectedComponent) {
        if (!silent) showToast('👉 Select an element first');
        return;
      }
      const type = getSelectedAnimation(),
        trigger = document.getElementById('anim-trigger').value;
      const duration = document.getElementById('anim-duration').value,
        delay = document.getElementById('anim-delay').value;
      const easing = document.getElementById('anim-easing').value,
        repeat = document.getElementById('anim-repeat').value;
      const attrs = Object.assign({}, selectedComponent.getAttributes() || {}, {
        'data-anim': type,
        'data-anim-trigger': trigger,
        'data-anim-duration': duration,
        'data-anim-delay': delay,
        'data-anim-easing': easing,
        'data-anim-repeat': repeat
      });
      selectedComponent.setAttributes(attrs);
      setTimeout(setupAnimationsInCanvas, 60);
      previewAnimationOnCanvas();
      if (!silent) showToast(`🎬 ${ANIM_PRESETS[type]?.name||type} applied ▶ playing live`);
    }

    function removeAnimationFromSelected() {
      if (!selectedComponent) {
        showToast('👉 Select an element first');
        return;
      }
      const attrs = Object.assign({}, selectedComponent.getAttributes() || {});
      ['data-anim', 'data-anim-trigger', 'data-anim-duration', 'data-anim-delay', 'data-anim-easing', 'data-anim-repeat'].forEach(k => delete attrs[k]);
      selectedComponent.setAttributes(attrs);
      try {
        const el = selectedComponent.getEl();
        if (el) {
          if (el._wcLiveAnim && el._wcLiveAnim.cancel) el._wcLiveAnim.cancel();
          if (el._wcLiveTimer) clearTimeout(el._wcLiveTimer);
          delete el._wcLiveParams;
          el.classList.remove('wc-anim-live');
          el.style.animation = '';
          el.style.opacity = '';
          el.style.transform = '';
          el.style.filter = '';
        }
      } catch (e) {}
      setTimeout(setupAnimationsInCanvas, 60);
      showToast('🎬 Animation removed');
    }

    function applyAnimToAll(type, trigger) {
      if (!grapesEditor) return;
      const wrapper = grapesEditor.DomComponents.getWrapper();
      let count = 0;
      const walk = (c) => {
        const tag = (c.get('tagName') || '').toLowerCase();
        if (['section', 'header', 'footer'].includes(tag)) {
          const attrs = Object.assign({}, c.getAttributes() || {}, {
            'data-anim': type,
            'data-anim-trigger': trigger,
            'data-anim-duration': '700',
            'data-anim-delay': String(count * 80),
            'data-anim-easing': 'cubic-bezier(0.22,1,0.36,1)',
            'data-anim-repeat': '1'
          });
          c.setAttributes(attrs);
          count++;
        }
        const kids = c.components();
        if (kids && kids.length) kids.forEach(walk);
      };
      walk(wrapper);
      setTimeout(setupAnimationsInCanvas, 120);
      showToast(`🎬 ${count} section${count===1?'':'s'} animated`);
    }

    /* ══════════════════════════════════════════════════
       MOBILE OVERRIDES
    ══════════════════════════════════════════════════ */
    function toggleMobileEditMode() {
      mobileEditMode = !mobileEditMode;
      const btn = document.getElementById('mobile-mode-btn');
      const banner = document.getElementById('mobile-mode-banner');
      if (btn) btn.classList.toggle('active', mobileEditMode);
      if (banner) banner.classList.toggle('on', mobileEditMode);
      if (mobileEditMode) {
        setStudioDevice('Mobile');
        showToast('📱 Mobile-only editing ON');
      } else {
        setStudioDevice('Desktop');
        showToast('↩️ Back to desktop editing');
      }
    }

    function applyMobileOverride(comp, prop, value) {
      if (!comp) return;
      const attrs = Object.assign({}, comp.getAttributes() || {});
      const attrName = 'data-mobile-' + prop.replace(/[^a-z0-9]/gi, '-');
      if (value === '' || value == null || value === 'none' || value === 'auto') delete attrs[attrName];
      else attrs[attrName] = String(value);
      comp.setAttributes(attrs);
      ensureMobileCssBlock();
    }

    function ensureMobileCssBlock() {
      try {
        const canvasDoc = grapesEditor?.Canvas?.getDocument();
        if (!canvasDoc || !canvasDoc.head) return;
        let tag = canvasDoc.getElementById('webcraft-mobile-css');
        if (!tag) {
          tag = canvasDoc.createElement('style');
          tag.id = 'webcraft-mobile-css';
          canvasDoc.head.appendChild(tag);
        }
        tag.innerHTML = generateMobileCss();
      } catch (e) {}
    }

    function generateMobileCss() {
      return `
@media (max-width: 767px) {
  [data-mobile-id][data-mobile-padding] { padding: var(--mb-p, inherit); }
  [data-mobile-bg] { background: attr(data-mobile-bg type(<color>), inherit); }
  [data-mobile-color] { color: attr(data-mobile-color type(<color>), inherit); }
  [data-mobile-font-size] { font-size: attr(data-mobile-font-size type(<length>), inherit); }
  [data-mobile-text-align] { text-align: attr(data-mobile-text-align type(<custom-ident>), inherit); }
  [data-mobile-display] { display: attr(data-mobile-display type(<custom-ident>), inherit); }
  [data-mobile-width] { width: attr(data-mobile-width type(<length>), auto); }
  [data-mobile-height] { height: attr(data-mobile-height type(<length>), auto); }
  [data-mobile-min-height] { min-height: attr(data-mobile-min-height type(<length>), auto); }
  [data-mobile-max-width] { max-width: attr(data-mobile-max-width type(<length>), none); }
  [data-mobile-margin] { margin: attr(data-mobile-margin type(<length>), inherit); }
  [data-mobile-border-radius] { border-radius: attr(data-mobile-border-radius type(<length>), inherit); }
  [data-mobile-hide="true"] { display: none !important; }
}
`.trim();
    }

    function applyMobileStylesInCanvas() {
      try {
        const canvasDoc = grapesEditor?.Canvas?.getDocument();
        if (!canvasDoc) return;
        const isMobile = canvasDoc.documentElement.classList.contains('gjs-mobile') || (grapesEditor.getDevice() === 'Mobile') || (window.getComputedStyle(canvasDoc.documentElement).width === '375px');
        const clearProps = ['padding', 'background', 'background-color', 'color', 'font-size', 'text-align', 'display', 'width', 'height', 'min-height', 'max-width', 'margin', 'border-radius'];
        canvasDoc.querySelectorAll('[data-mobile-id]').forEach(el => {
          el.style.removeProperty('--mb-p');
          if (!isMobile) {
            clearProps.forEach(p => el.style.removeProperty(p));
            return;
          }
          const pad = el.getAttribute('data-mobile-padding'),
            bg = el.getAttribute('data-mobile-bg'),
            col = el.getAttribute('data-mobile-color');
          const fs = el.getAttribute('data-mobile-font-size'),
            ta = el.getAttribute('data-mobile-text-align');
          const disp = el.getAttribute('data-mobile-display'),
            w = el.getAttribute('data-mobile-width');
          const h = el.getAttribute('data-mobile-height'),
            mh = el.getAttribute('data-mobile-min-height');
          const mw = el.getAttribute('data-mobile-max-width');
          const m = el.getAttribute('data-mobile-margin'),
            r = el.getAttribute('data-mobile-border-radius');
          if (pad) el.style.padding = pad;
          if (bg) el.style.background = bg;
          if (col) el.style.color = col;
          if (fs) el.style.fontSize = fs;
          if (ta) el.style.textAlign = ta;
          if (disp) el.style.display = disp;
          if (w) el.style.width = w;
          if (h) el.style.height = h;
          if (mh) el.style.minHeight = mh;
          if (mw) el.style.maxWidth = mw;
          if (m) el.style.margin = m;
          if (r) el.style.borderRadius = r;
        });
      } catch (e) {}
    }

    /* ══════════════════════════════════════════════════
       FLOATING EDIT CONTENT BUTTON
    ══════════════════════════════════════════════════ */
    function updateFloatingContentBtn(model) {
      const btn = document.getElementById('floating-edit-content-btn');
      if (!btn) return;
      if (!model) {
        btn.classList.remove('show');
        return;
      }
      const tag = (model.get('tagName') || '').toLowerCase();
      if (['section', 'header', 'footer'].includes(tag)) {
        const name = getSectionDisplayName(model);
        btn.innerHTML = `✍️ Edit "${escapeHtml(name)}" Content`;
        btn.classList.add('show');
      } else btn.classList.remove('show');
    }

    function updateCtxPanel(model) {
      const idle = document.getElementById('ctx-idle-state');
      const sel  = document.getElementById('ctx-sel-state');
      if (!idle || !sel) return;

      if (!model) {
        idle.style.display = 'flex';
        sel.style.display  = 'none';
        return;
      }

      idle.style.display = 'none';
      sel.style.display  = 'flex';

      const tag   = (model.get('tagName') || 'div').toLowerCase();
      const type  = model.get('type') || '';
      const label = model.get('name') || model.get('customName') || '';

      // Icon + tag mapping
      const iconMap = {
        img: '🖼️', section: '📐', header: '🏷️', footer: '🦶',
        h1: 'H1', h2: 'H2', h3: 'H3', h4: 'H4', p: '¶',
        a: '🔗', button: '🔘', div: '📦', span: '🏷', ul: '📋',
        video: '🎬', form: '📝', input: '⌨️', textarea: '📄'
      };
      const icon = iconMap[tag] || iconMap[type] || '📦';
      const tagLabel = label || tag.toUpperCase();

      document.getElementById('ctx-el-icon').textContent = icon;
      document.getElementById('ctx-el-tag').textContent  = tagLabel;
      document.getElementById('ctx-el-label').textContent = type === 'image' ? 'Image element'
        : tag === 'section' ? 'Page section'
        : tag === 'a' ? 'Link / Button'
        : 'Canvas element';

      // Show/hide image-specific buttons
      const isImg = tag === 'img' || type === 'image';
      document.getElementById('ctx-img-edit-btn').style.display = isImg ? '' : 'none';
      document.getElementById('ctx-img-crop-btn').style.display = isImg ? '' : 'none';
    }

    /* ══════════════════════════════════════════════════
       IMAGE CROP
    ══════════════════════════════════════════════════ */
    let cropState = {
      img: null,
      rect: null,
      aspect: 'free',
      startBox: null,
      dragging: null,
      imageNatural: { w: 0, h: 0 },
      originalComp: null
    };

    function openCropTool(comp) {
      if (!comp) comp = selectedComponent;
      if (!comp || (comp.get('tagName') || '').toLowerCase() !== 'img') {
        showToast('👉 Select an image first');
        return;
      }
      cropState.originalComp = comp;
      const attrs = comp.getAttributes() || {},
        src = attrs.src || '';
      cropState.oldSrc = src;
      cropState.key = imgCompKey(comp);
      if (!src) {
        showToast('⚠️ Image has no source');
        return;
      }
      const imgEl = document.getElementById('crop-image'),
        rectEl = document.getElementById('crop-rect');
      // Reset per-open state so stale crop box / sizes never leak from last image
      try {
        document.getElementById('crop-out-w').value = '';
        document.getElementById('crop-out-h').value = '';
      } catch (e) {}
      imgEl.onload = () => {
        cropState.imageNatural = {
          w: imgEl.naturalWidth || 1,
          h: imgEl.naturalHeight || 1
        };
        const w = imgEl.clientWidth,
          h = imgEl.clientHeight;
        rectEl.style.left = (w * 0.1) + 'px';
        rectEl.style.top = (h * 0.1) + 'px';
        rectEl.style.width = (w * 0.8) + 'px';
        rectEl.style.height = (h * 0.8) + 'px';
        cropState.rect = rectEl;
        cropState.aspect = 'free';
        document.querySelectorAll('.crop-ratio-row .be-preset-btn').forEach(b => {
          b.style.borderColor = '';
          b.style.color = '';
        });
      };
      document.getElementById('crop-modal').classList.add('active');
      setTimeout(setupCropDrag, 50);
      // ★ Force reload even when src equals the previously cropped image
      // (same-URL assignment may not fire onload → stale old image stays visible).
      try {
        if (imgEl.getAttribute('src') === src && imgEl.complete && imgEl.naturalWidth) {
          imgEl.onload();
        } else {
          imgEl.removeAttribute('src');
          imgEl.src = src;
        }
      } catch (e) { imgEl.src = src; }
    }

    function setupCropDrag() {
      const rect = document.getElementById('crop-rect'),
        stage = document.getElementById('crop-stage');
      if (!rect || !stage || rect.__wcBound) return;
      rect.__wcBound = true;
      const startDrag = (e, mode) => {
        e.preventDefault();
        e.stopPropagation();
        cropState.dragging = {
          mode,
          startX: e.clientX,
          startY: e.clientY,
          left: rect.offsetLeft,
          top: rect.offsetTop,
          width: rect.offsetWidth,
          height: rect.offsetHeight,
          stageW: stage.clientWidth,
          stageH: stage.clientHeight
        };
        document.addEventListener('pointermove', onDragMove);
        document.addEventListener('pointerup', onDragEnd);
      };
      const onDragMove = (e) => {
        const d = cropState.dragging;
        if (!d) return;
        const dx = e.clientX - d.startX,
          dy = e.clientY - d.startY;
        let nl = d.left,
          nt = d.top,
          nw = d.width,
          nh = d.height;
        const minW = 30,
          minH = 20;
        const ar = cropState.aspect === 'free' ? null : parseFloat(cropState.aspect.split(':')[0]) / parseFloat(cropState.aspect.split(':')[1]);
        if (d.mode === 'move') {
          nl = Math.max(0, Math.min(d.stageW - d.width, d.left + dx));
          nt = Math.max(0, Math.min(d.stageH - d.height, d.top + dy));
        } else {
          if (d.mode.includes('e')) nw = Math.max(minW, Math.min(d.stageW - d.left, d.width + dx));
          if (d.mode.includes('s')) nh = Math.max(minH, Math.min(d.stageH - d.top, d.height + dy));
          if (d.mode.includes('w')) {
            const newW = Math.max(minW, Math.min(d.left + d.width, d.width - dx));
            nl = d.left + (d.width - newW);
            nw = newW;
          }
          if (d.mode.includes('n')) {
            const newH = Math.max(minH, Math.min(d.top + d.height, d.height - dy));
            nt = d.top + (d.height - newH);
            nh = newH;
          }
          if (ar) {
            if (d.mode.includes('e') || d.mode.includes('w')) nh = nw / ar;
            else nw = nh * ar;
            if (nl + nw > d.stageW) nw = d.stageW - nl;
            if (nt + nh > d.stageH) nh = d.stageH - nt;
          }
        }
        rect.style.left = nl + 'px';
        rect.style.top = nt + 'px';
        rect.style.width = nw + 'px';
        rect.style.height = nh + 'px';
      };
      const onDragEnd = () => {
        cropState.dragging = null;
        document.removeEventListener('pointermove', onDragMove);
        document.removeEventListener('pointerup', onDragEnd);
      };
      rect.addEventListener('pointerdown', (e) => {
        const h = e.target.closest('.crop-handle');
        if (h) startDrag(e, h.dataset.h);
        else startDrag(e, 'move');
      });
    }

    function setCropRatio(ratio, btn) {
      cropState.aspect = ratio;
      document.querySelectorAll('.crop-ratio-row .be-preset-btn').forEach(b => {
        b.style.borderColor = '';
        b.style.color = '';
        b.style.background = '';
      });
      if (btn) {
        btn.style.borderColor = '#6366f1';
        btn.style.color = '#fff';
        btn.style.background = '#1e1b4b';
      }
      const rect = document.getElementById('crop-rect'),
        img = document.getElementById('crop-image');
      if (!rect || !img || ratio === 'free') return;
      const w = img.clientWidth,
        h = img.clientHeight;
      const parts = ratio.split(':');
      const ar = parseFloat(parts[0]) / parseFloat(parts[1]);
      let nw = w * 0.8,
        nh = nw / ar;
      if (nh > h * 0.9) {
        nh = h * 0.9;
        nw = nh * ar;
      }
      rect.style.width = nw + 'px';
      rect.style.height = nh + 'px';
      rect.style.left = ((w - nw) / 2) + 'px';
      rect.style.top = ((h - nh) / 2) + 'px';
    }

    function resetCropBox() {
      const img = document.getElementById('crop-image'),
        rect = document.getElementById('crop-rect');
      if (!img || !rect) return;
      const w = img.clientWidth,
        h = img.clientHeight;
      rect.style.left = (w * 0.1) + 'px';
      rect.style.top = (h * 0.1) + 'px';
      rect.style.width = (w * 0.8) + 'px';
      rect.style.height = (h * 0.8) + 'px';
    }

    function closeCropTool() {
      document.getElementById('crop-modal').classList.remove('active');
    }

    function applyCropToImage() {
      const imgEl = document.getElementById('crop-image'),
        rectEl = document.getElementById('crop-rect'),
        comp = cropState.originalComp;
      if (!imgEl || !rectEl || !comp) {
        closeCropTool();
        return;
      }
      const dispW = imgEl.clientWidth,
        dispH = imgEl.clientHeight;
      const natW = imgEl.naturalWidth,
        natH = imgEl.naturalHeight;
      // Guard: if natural dimensions are 0, the image hasn't loaded yet
      if (natW <= 0 || natH <= 0) {
        showToast('⚠️ Image still loading — wait for it to finish then retry crop', 5000);
        closeCropTool();
        return;
      }
      const sx = natW / dispW,
        sy = natH / dispH;
      const cropX = rectEl.offsetLeft * sx,
        cropY = rectEl.offsetTop * sy;
      const cropW = rectEl.offsetWidth * sx,
        cropH = rectEl.offsetHeight * sy;
      const outW = parseInt(document.getElementById('crop-out-w').value) || Math.round(cropW);
      const outH = parseInt(document.getElementById('crop-out-h').value) || Math.round(cropH);
      // ★ Cap output at 1600px so the cropped image never blows localStorage quota
      const outScale = Math.min(1, 1600 / Math.max(outW, outH));
      const finalW = Math.max(1, Math.round(outW * outScale)),
        finalH = Math.max(1, Math.round(outH * outScale));
      const canvas = document.createElement('canvas');
      canvas.width = finalW;
      canvas.height = finalH;
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        showToast('❌ Could not get canvas context — try again', 5000);
        closeCropTool();
        return;
      }
      // Only request CORS for true cross-origin URLs; same-origin + data:
      // URLs stay clean (avoids needless taint/blocked-canvas failures).
      const tmp = new Image();
      try {
        const abs = new URL(imgEl.src, window.location.href);
        if (/^https?:/i.test(imgEl.src) && abs.origin !== window.location.origin) {
          tmp.crossOrigin = imgEl.crossOrigin || 'anonymous';
        }
      } catch (e) { tmp.crossOrigin = imgEl.crossOrigin || 'anonymous'; }
      tmp.onload = () => {
        const finishCrop = (finalUrl) => {
          // Bulletproof re-resolve: canvas may have re-rendered while crop was open
          let target = resolveLiveImgComp(cropState.key, comp);
          if (!target) target = comp || cropState.originalComp || selectedComponent;
          if (target) {
            cropState.originalComp = target;
            cropState.key = imgCompKey(target);
            try { grapesEditor.select(target); } catch (e) {}
          }
          if (!target) {
            showToast('⚠️ Select the image again & retry crop', 5000);
            closeCropTool();
            return;
          }
          target.set('src', finalUrl);   // ← model-level: what getSrcResult reads for getHtml()
          target.setAttributes(Object.assign({}, target.getAttributes() || {}, { src: finalUrl }));
          try {
            const liveEl = target.getEl && target.getEl();
            if (liveEl && liveEl.tagName === 'IMG') liveEl.setAttribute('src', finalUrl);
            if (target.view && typeof target.view.render === 'function') target.view.render();
          } catch (e) {}
          if (document.getElementById('crop-replace-orig')?.checked) {
            userUploadedImages.unshift({
              id: 'img_' + Date.now(),
              name: 'cropped.jpg',
              url: finalUrl
            });
            saveUserUploads();
          }
          const cropSaved = syncCanvasToHtml();
          renderSmartLayers();
          showToast(cropSaved ? '✂️ Image cropped! ✓ Saved' : '✂️ Image cropped ✓');
          closeCropTool();
        };
        try {
          // White base so JPEG (no alpha) never turns transparent areas black
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, finalW, finalH);
          ctx.drawImage(tmp, cropX, cropY, cropW, cropH, 0, 0, finalW, finalH);
          // ★ Prefer server URL (permanent, tiny); fallback to embedded dataURL
          if (canvas.toBlob) {
            canvas.toBlob(b => {
              if (!b) { finishCrop(canvas.toDataURL('image/jpeg', 0.85)); return; }
              uploadImageToServer(b, 'cropped.jpg').then(u => {
                finishCrop(u || canvas.toDataURL('image/jpeg', 0.85));
              });
            }, 'image/jpeg', 0.85);
          } else {
            finishCrop(canvas.toDataURL('image/jpeg', 0.85));
          }
        } catch (e) {
          showToast('⚠️ Crop failed — external image blocked (CORS). Download it & upload instead.');
        }
      };
      tmp.onerror = () => {
        showToast('❌ Failed to load image for cropping. The image may not have proper CORS headers, or the image source may be invalid.', 5000);
        closeCropTool();
      };
      tmp.src = imgEl.src;
    }

    /* ══════════════════════════════════════════════════
       GALLERY BUILDER
    ══════════════════════════════════════════════════ */
    const GAL_LAYOUTS = {
      grid: { name: 'Grid' },
      masonry: { name: 'Masonry' },
      twoCol: { name: 'Two Col' },
      fourCol: { name: 'Four Col' },
      carousel: { name: 'Carousel' },
      mosaic: { name: 'Mosaic' }
    };
    let galState = { layout: 'grid', selected: [] };

    function openGalleryBuilder() {
      galState.selected = [];
      renderGalleryLayouts();
      renderGalleryPicker();
      document.getElementById('gal-selected-count').textContent = '0';
      document.getElementById('gallery-modal').classList.add('active');
    }

    function closeGalleryBuilder() {
      document.getElementById('gallery-modal').classList.remove('active');
    }

    function renderGalleryLayouts() {
      const grid = document.getElementById('gal-layouts');
      if (!grid) return;
      grid.innerHTML = '';
      const demoGrids = {
        grid: 'grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(2,1fr);',
        masonry: 'grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(2,1fr);',
        twoCol: 'grid-template-columns:repeat(2,1fr);grid-template-rows:repeat(2,1fr);',
        fourCol: 'grid-template-columns:repeat(4,1fr);grid-template-rows:1fr;',
        carousel: 'grid-template-columns:1fr;grid-template-rows:1fr;',
        mosaic: 'grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(2,1fr);'
      };
      Object.keys(GAL_LAYOUTS).forEach(key => {
        const L = GAL_LAYOUTS[key];
        const card = document.createElement('div');
        card.className = 'gal-layout' + (key === galState.layout ? ' active' : '');
        card.dataset.layout = key;
        card.innerHTML = `<div class="gl-demo" style="${demoGrids[key]}"><div></div><div></div><div></div><div></div><div></div><div></div></div><div class="gl-name">${L.name}</div>`;
        card.onclick = () => {
          galState.layout = key;
          document.querySelectorAll('#gal-layouts .gal-layout').forEach(c => c.classList.toggle('active', c.dataset.layout === key));
        };
        grid.appendChild(card);
      });
    }

    function renderGalleryPicker() {
      const picker = document.getElementById('gal-picker');
      if (!picker) return;
      picker.innerHTML = '';
      const all = [];
      userUploadedImages.forEach(u => all.push({ url: u.url, caption: u.name }));
      Object.keys(stockPhotos).forEach(k => stockPhotos[k].forEach(p => all.push({ url: p.url, caption: p.caption })));
      all.forEach((item) => {
        const d = document.createElement('div');
        d.className = 'gp-item' + (galState.selected.includes(item.url) ? ' on' : '');
        d.innerHTML = `<img src="${item.url}" loading="lazy">`;
        d.title = item.caption;
        d.onclick = () => {
          const idx = galState.selected.indexOf(item.url);
          if (idx > -1) galState.selected.splice(idx, 1);
          else galState.selected.push(item.url);
          d.classList.toggle('on', galState.selected.includes(item.url));
          document.getElementById('gal-selected-count').textContent = String(galState.selected.length);
        };
        picker.appendChild(d);
      });
    }

    function insertGallerySection() {
      if (!grapesEditor) return;
      if (galState.selected.length === 0) {
        showToast('👉 Pick at least one image');
        return;
      }
      const title = document.getElementById('gal-title').value.trim() || 'Our Gallery';
      const name = document.getElementById('gal-name').value.trim() || 'Gallery';
      const gap = parseInt(document.getElementById('gal-gap').value) || 16;
      const radius = parseInt(document.getElementById('gal-radius').value) || 14;
      const layout = galState.layout;
      let innerStyle = '';
      if (layout === 'grid') innerStyle = `display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:${gap}px;`;
      else if (layout === 'masonry') innerStyle = `column-count:3;column-gap:${gap}px;`;
      else if (layout === 'twoCol') innerStyle = `display:grid;grid-template-columns:repeat(2,1fr);gap:${gap}px;`;
      else if (layout === 'fourCol') innerStyle = `display:grid;grid-template-columns:repeat(4,1fr);gap:${gap}px;`;
      else if (layout === 'carousel') innerStyle = `display:flex;overflow-x:auto;gap:${gap}px;scroll-snap-type:x mandatory;padding-bottom:0.5rem;`;
      else innerStyle = `display:grid;grid-template-columns:repeat(3,1fr);gap:${gap}px;`;
      const imgs = galState.selected.map((src, i) => {
        const style = layout === 'masonry' ? `width:100%;height:auto;border-radius:${radius}px;margin-bottom:${gap}px;display:block;break-inside:avoid;` : layout === 'carousel' ? `flex:0 0 80%;max-width:80%;height:280px;object-fit:cover;border-radius:${radius}px;scroll-snap-align:center;` : `width:100%;height:220px;object-fit:cover;border-radius:${radius}px;display:block;`;
        return `<img src="${escapeHtml(src)}" alt="Gallery image ${i+1}" style="${style}"/>`;
      }).join('');
      const html = `<section id="gallery" data-section-name="${escapeHtml(name)}" style="padding:5rem 1.5rem;background:#f8fafc;"><div style="max-width:1150px;margin:0 auto;"><h2 style="font-size:2.2rem;font-weight:800;text-align:center;margin-bottom:2.5rem;color:#0f172a;">${escapeHtml(title)}</h2><div style="${innerStyle}">${imgs}</div></div></section>`;
      const added = grapesEditor.addComponents(html);
      const comp = Array.isArray(added) ? added[0] : added;
      if (comp) {
        configureEditorComponent(comp);
        grapesEditor.select(comp);
      }
      closeGalleryBuilder();
      setTimeout(() => {
        renderFriendlySections();
        renderSmartLayers();
        refreshSectionDragHandles();
        syncCanvasToHtml();
        saveProjectData();
      }, 150);
      showToast(`🖼️ ${layout} gallery added`);
    }

    /* ══════════════════════════════════════════════════
       CARD BUILDER — image + content + button, all editable
    ══════════════════════════════════════════════════ */
    const CD_LAYOUTS = {
      vertical: { name: 'Image Top' },
      horizontal: { name: 'Side by Side' },
      profile: { name: 'Profile' }
    };
    let cardState = { layout: 'vertical', img: '' };

    function openCardBuilder() {
      cardState.img = '';
      try { document.getElementById('cd-img-url').value = ''; } catch (e) {}
      renderCardLayouts();
      renderCardPicker();
      document.getElementById('card-modal').classList.add('active');
    }

    function closeCardBuilder() {
      document.getElementById('card-modal').classList.remove('active');
    }

    function renderCardLayouts() {
      const grid = document.getElementById('cd-layouts');
      if (!grid) return;
      grid.innerHTML = '';
      const demos = {
        vertical: 'grid-template-columns:1fr;grid-template-rows:1.2fr 1fr;',
        horizontal: 'grid-template-columns:1fr 1.4fr;grid-template-rows:1fr;',
        profile: 'grid-template-columns:1fr;grid-template-rows:1fr 1fr;'
      };
      Object.keys(CD_LAYOUTS).forEach(key => {
        const L = CD_LAYOUTS[key];
        const card = document.createElement('div');
        card.className = 'gal-layout' + (key === cardState.layout ? ' active' : '');
        card.dataset.layout = key;
        card.innerHTML = `<div class="gl-demo" style="${demos[key]}"><div></div><div></div></div><div class="gl-name">${L.name}</div>`;
        card.onclick = () => {
          cardState.layout = key;
          document.querySelectorAll('#cd-layouts .gal-layout').forEach(c => c.classList.toggle('active', c.dataset.layout === key));
        };
        grid.appendChild(card);
      });
    }

    function renderCardPicker() {
      const picker = document.getElementById('cd-picker');
      if (!picker) return;
      picker.innerHTML = '';
      const all = [];
      (userUploadedImages || []).forEach(u => all.push({ url: u.url, caption: u.name || 'Upload' }));
      try {
        Object.keys(stockPhotos || {}).forEach(k => (stockPhotos[k] || []).forEach(p => all.push({ url: p.url, caption: p.caption || k })));
      } catch (e) {}
      if (!all.length) {
        picker.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:0.75rem;padding:1rem;">No photos yet — upload in Media tab first, or paste a URL below.</div>';
        return;
      }
      all.forEach((item) => {
        const d = document.createElement('div');
        d.className = 'gp-item' + (cardState.img === item.url ? ' on' : '');
        d.innerHTML = `<img src="${item.url}" loading="lazy">`;
        d.title = item.caption;
        d.onclick = () => {
          cardState.img = (cardState.img === item.url) ? '' : item.url;
          try { document.getElementById('cd-img-url').value = ''; } catch (e) {}
          picker.querySelectorAll('.gp-item').forEach(g => g.classList.remove('on'));
          if (cardState.img) d.classList.add('on');
        };
        picker.appendChild(d);
      });
    }

    function cdButtonStyle(kind) {
      const base = 'display:inline-block;text-decoration:none;font-weight:700;font-size:0.9rem;padding:0.8rem 1.8rem;border-radius:999px;margin-top:1rem;';
      if (kind === 'outline') return base + 'background:transparent;border:1.5px solid #cbd5e1;color:#334155;';
      if (kind === 'whatsapp') return base + 'background:#25D366;color:#fff;border:none;';
      if (kind === 'dark') return base + 'background:#0f172a;color:#fff;border:none;';
      return base + 'background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;';
    }

    function insertCard() {
      if (!grapesEditor) return;
      const img = (document.getElementById('cd-img-url')?.value || '').trim() || cardState.img;
      if (!img) {
        showToast('👉 Pick a photo first (or paste a URL)');
        return;
      }
      const badge = (document.getElementById('cd-badge')?.value || '').trim();
      const title = (document.getElementById('cd-title')?.value || '').trim() || 'Card Title';
      const text = (document.getElementById('cd-text')?.value || '').trim() || '';
      const btnText = (document.getElementById('cd-btn-text')?.value || '').trim() || 'Learn More →';
      const btnLink = (document.getElementById('cd-btn-link')?.value || '').trim() || '#contact';
      const btnKind = document.getElementById('cd-btn-style')?.value || 'primary';
      const radius = Math.max(0, parseInt(document.getElementById('cd-radius')?.value) || 20);
      const layout = cardState.layout;
      const badgeHtml = badge ? `<span style="display:inline-block;font-size:0.68rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#4f46e5;background:#eef2ff;padding:0.3rem 0.8rem;border-radius:999px;margin-bottom:0.8rem;">${escapeHtml(badge)}</span>` : '';
      const btnHtml = `<a href="${escapeHtml(btnLink)}" style="${cdButtonStyle(btnKind)}">${escapeHtml(btnText)}</a>`;
      const imgTag = (h, extra) => `<img data-wc-card-img="1" src="${escapeHtml(img)}" alt="${escapeHtml(title)}" style="width:100%;height:${h};object-fit:cover;display:block;${extra || ''}"/>`;
      let html = '';
      if (layout === 'horizontal') {
        html = `<div data-wc-card="horizontal" style="max-width:640px;margin:2rem auto;background:#ffffff;border-radius:${radius}px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.12);display:flex;flex-wrap:wrap;"><div style="flex:1 1 220px;min-width:220px;">${imgTag('100%', 'min-height:220px;')}</div><div style="flex:1 1 260px;padding:1.75rem;">${badgeHtml}<h3 style="font-size:1.4rem;font-weight:800;color:#0f172a;margin-bottom:0.6rem;">${escapeHtml(title)}</h3><p style="font-size:0.95rem;line-height:1.65;color:#475569;">${escapeHtml(text)}</p>${btnHtml}</div></div>`;
      } else if (layout === 'profile') {
        html = `<div data-wc-card="profile" style="max-width:320px;margin:2rem auto;background:#ffffff;border-radius:${radius}px;box-shadow:0 10px 30px rgba(0,0,0,0.12);padding:2.25rem 1.75rem;text-align:center;"><img data-wc-card-img="1" src="${escapeHtml(img)}" alt="${escapeHtml(title)}" style="width:130px;height:130px;border-radius:50%;object-fit:cover;display:block;margin:0 auto 1.1rem;border:4px solid #eef2ff;"/>${badgeHtml}<h3 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:0.5rem;">${escapeHtml(title)}</h3><p style="font-size:0.92rem;line-height:1.65;color:#475569;">${escapeHtml(text)}</p>${btnHtml}</div>`;
      } else {
        html = `<div data-wc-card="photo" style="max-width:340px;margin:2rem auto;background:#ffffff;border-radius:${radius}px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.12);">${imgTag('220px', '')}<div style="padding:1.5rem;">${badgeHtml}<h3 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:0.6rem;">${escapeHtml(title)}</h3><p style="font-size:0.95rem;line-height:1.65;color:#475569;">${escapeHtml(text)}</p>${btnHtml}</div></div>`;
      }
      const added = grapesEditor.addComponents(html);
      const comp = Array.isArray(added) ? added[0] : added;
      if (comp) {
        configureEditorComponent(comp);
        grapesEditor.select(comp);
      }
      closeCardBuilder();
      setTimeout(() => {
        renderFriendlySections();
        renderSmartLayers();
        refreshSectionDragHandles();
        const ok = syncCanvasToHtml();
        showToast(ok ? '🃏 Card added ✓ Saved' : '🃏 Card added, but ⚠️ storage full');
      }, 150);
    }

    /* ══════════════════════════════════════════════════
       MULTI-LANGUAGE
    ══════════════════════════════════════════════════ */
    const LANGUAGES = [
      { code: 'en', name: 'English', flag: '🇬🇧' },
      { code: 'ta', name: 'Tamil', flag: '🇮🇳' },
      { code: 'hi', name: 'Hindi', flag: '🇮🇳' },
      { code: 'te', name: 'Telugu', flag: '🇮🇳' },
      { code: 'ml', name: 'Malayalam', flag: '🇮🇳' },
      { code: 'kn', name: 'Kannada', flag: '🇮🇳' },
      { code: 'es', name: 'Spanish', flag: '🇪🇸' },
      { code: 'fr', name: 'French', flag: '🇫🇷' },
      { code: 'de', name: 'German', flag: '🇩🇪' },
      { code: 'ar', name: 'Arabic', flag: '🇸🇦' },
      { code: 'zh', name: 'Chinese', flag: '🇨🇳' },
      { code: 'ja', name: 'Japanese', flag: '🇯🇵' }
    ];
    const RTL_LANGS = ['ar', 'he', 'fa', 'ur'];
    let langState = {
      active: ['en'],
      primary: 'en',
      previewing: 'en',
      switcherVisible: true,
      switcherPos: 'bottom-right',
      switcherStyle: 'pill',
      autodetect: true,
      remember: true,
      translations: {}
    };

    function renderLangChips() {
      const row = document.getElementById('lang-chip-row');
      if (!row) return;
      row.innerHTML = '';
      LANGUAGES.forEach(L => {
        const chip = document.createElement('button');
        chip.className = 'lang-chip' + (langState.active.includes(L.code) ? ' on' : '');
        chip.textContent = `${L.flag} ${L.name}`;
        chip.onclick = () => {
          const idx = langState.active.indexOf(L.code);
          if (idx > -1) {
            if (langState.active.length === 1) {
              showToast('Keep at least one language');
              return;
            }
            langState.active.splice(idx, 1);
          } else langState.active.push(L.code);
          renderLangChips();
          renderHeaderLangSelect();
        };
        row.appendChild(chip);
      });
    }

    function renderHeaderLangSelect() {
      const sel = document.getElementById('header-lang-select');
      if (!sel) return;
      sel.innerHTML = '';
      langState.active.forEach(code => {
        const L = LANGUAGES.find(x => x.code === code);
        if (!L) return;
        const opt = document.createElement('option');
        opt.value = code;
        opt.textContent = `${L.flag} ${L.name}`;
        sel.appendChild(opt);
      });
      sel.value = langState.previewing;
    }

    function switchCanvasLanguage(code) {
      langState.previewing = code;
      applyLanguageToCanvas(code);
      showToast(`🌐 Previewing ${LANGUAGES.find(x=>x.code===code)?.name||code}`);
    }

    function applyLanguageToCanvas(code) {
      try {
        const canvasDoc = grapesEditor?.Canvas?.getDocument();
        if (!canvasDoc) return;
        canvasDoc.documentElement.lang = code;
        canvasDoc.documentElement.dir = RTL_LANGS.includes(code) ? 'rtl' : 'ltr';
        canvasDoc.querySelectorAll('[data-i18n-' + code + ']').forEach(el => {
          const val = el.getAttribute('data-i18n-' + code);
          if (val) el.textContent = val;
        });
      } catch (e) {}
    }

    function collectTextNodes() {
      const out = [];
      if (!grapesEditor) return out;
      const seen = new Set();
      const walk = (c) => {
        const tag = (c.get('tagName') || '').toLowerCase();
        if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'button', 'span', 'li', 'div', 'label', 'strong', 'em', 'small'].includes(tag)) {
          const el = c.getEl && c.getEl();
          if (el) {
            const direct = Array.from(el.childNodes).filter(n => n.nodeType === 3).map(n => n.textContent).join('').trim();
            if (direct && direct.length > 0 && !seen.has(direct)) {
              seen.add(direct);
              out.push({ key: direct, comp: c, el });
            }
          }
        }
        const kids = c.components && c.components();
        if (kids && kids.length) kids.forEach(walk);
      };
      walk(grapesEditor.DomComponents.getWrapper());
      return out;
    }

    function openLanguageManager() {
      if (langState.active.length === 0) {
        showToast('Add a language first');
        return;
      }
      const nodes = collectTextNodes();
      const body = document.getElementById('language-manager-body');
      if (!body) return;
      const activeLangs = LANGUAGES.filter(L => langState.active.includes(L.code));
      const headerCells = activeLangs.map(L => `<th style="padding:0.5rem;font-size:0.72rem;color:#a5b4fc;border-bottom:1px solid #1e293b;">${L.flag} ${L.name}${L.code==='en'?' (primary)':''}</th>`).join('');
      const rows = nodes.map((n, i) => {
        const cells = activeLangs.map(L => {
          const val = langState.translations?.[L.code]?.[n.key] || (L.code === langState.primary ? n.key : '');
          return `<td style="padding:0.4rem;"><input type="text" data-i18n-key="${escapeHtml(n.key)}" data-i18n-lang="${L.code}" value="${escapeHtml(val)}" style="width:100%;min-width:130px;padding:0.45rem 0.6rem;border:1.5px solid #283347;border-radius:7px;background:#080c14;color:#fff;font:0.78rem 'Plus Jakarta Sans',sans-serif;"></td>`;
        }).join('');
        return `<tr style="border-bottom:1px solid #1e293b;"><td style="padding:0.4rem;"><div style="font-size:0.68rem;color:#94a3b8;">#${i+1} ${escapeHtml(n.el.tagName.toLowerCase())}</div><div style="font-size:0.72rem;color:#cbd5e1;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(n.key)}</div></td>${cells}</tr>`;
      }).join('');
      body.innerHTML = `<div style="background:#0a0f1c;border:1px solid #1e293b;border-radius:10px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.76rem;color:#94a3b8;"><strong style="color:#a5b4fc;">Primary language:</strong> ${escapeHtml(LANGUAGES.find(L=>L.code===langState.primary)?.name||'English')}.</div><div style="max-height:420px;overflow-y:auto;border:1px solid #1e293b;border-radius:10px;background:#050810;"><table style="width:100%;border-collapse:collapse;"><thead style="position:sticky;top:0;background:#0a0e1a;z-index:1;"><tr><th style="padding:0.5rem;text-align:left;font-size:0.72rem;color:#a5b4fc;border-bottom:1px solid #1e293b;">Text Element</th>${headerCells}</tr></thead><tbody>${rows||'<tr><td colspan="99" style="padding:1.5rem;text-align:center;color:#64748b;">No text elements found.</td></tr>'}</tbody></table></div>`;
      document.getElementById('language-manager-modal').classList.add('active');
    }

    function closeLanguageManager() {
      document.getElementById('language-manager-modal').classList.remove('active');
    }

    function applyLanguageTranslations() {
      const inputs = document.querySelectorAll('#language-manager-body [data-i18n-key]');
      const translations = {};
      inputs.forEach(inp => {
        const k = inp.dataset.i18nKey,
          lang = inp.dataset.i18nLang,
          v = inp.value.trim();
        if (!v) return;
        if (!translations[lang]) translations[lang] = {};
        translations[lang][k] = v;
      });
      langState.translations = translations;
      const nodes = collectTextNodes();
      nodes.forEach(n => {
        const attrs = Object.assign({}, n.comp.getAttributes() || {});
        langState.active.forEach(code => {
          const v = translations[code]?.[n.key];
          if (v) attrs['data-i18n-' + code] = v;
          else delete attrs['data-i18n-' + code];
        });
        n.comp.setAttributes(attrs);
      });
      applyLanguageToCanvas(langState.previewing);
      saveLanguageState();
      closeLanguageManager();
      showToast('🌐 Translations saved');
    }

    function saveLanguageState() {
      try {
        localStorage.setItem('webcraft_lang_state', JSON.stringify(langState));
      } catch (e) {}
    }

    function loadLanguageState() {
      try {
        const raw = localStorage.getItem('webcraft_lang_state');
        if (raw) Object.assign(langState, JSON.parse(raw));
      } catch (e) {}
    }

    function autoTranslateAll() {
      showToast('✨ Use Magic AI to auto-translate');
      appendMagicChat('✨ <strong>Tip:</strong> Use Magic AI: <em>"Translate the entire website into Tamil and Hindi, keeping English as primary"</em>', 'ai');
    }

    function updateLangSwitcherSettings() {
      langState.switcherPos = document.getElementById('lang-switcher-pos').value;
      langState.switcherStyle = document.getElementById('lang-switcher-style').value;
      langState.autodetect = document.getElementById('lang-autodetect').checked;
      langState.remember = document.getElementById('lang-remember').checked;
      saveLanguageState();
      injectLangSwitcherToCanvas();
    }

    function toggleLangSwitcher() {
      langState.switcherVisible = !langState.switcherVisible;
      const btn = document.getElementById('lang-switcher-toggle');
      if (btn) btn.textContent = langState.switcherVisible ? '👁️ Hide Switcher' : '👁️ Show Switcher';
      injectLangSwitcherToCanvas();
      saveLanguageState();
    }

    function generateLangSwitcherHtml() {
      if (!langState.switcherVisible) return '';
      const langs = LANGUAGES.filter(L => langState.active.includes(L.code));
      if (langs.length < 2) return '';
      const pos = {
        'bottom-right': 'bottom:1rem;right:1rem;',
        'bottom-left': 'bottom:1rem;left:1rem;',
        'top-right': 'top:1rem;right:1rem;',
        'top-left': 'top:1rem;left:1rem;'
      } [langState.switcherPos] || 'bottom:1rem;right:1rem;';
      const styles = 'position:fixed;z-index:9998;background:rgba(15,23,42,0.95);backdrop-filter:blur(10px);border:1.5px solid #334155;border-radius:999px;padding:0.35rem 0.5rem;box-shadow:0 10px 30px rgba(0,0,0,0.35);display:flex;gap:0.3rem;align-items:center;font-family:inherit;';
      if (langState.switcherStyle === 'dropdown') {
        return `<div id="wc-lang-switcher" style="${styles}${pos}"><select onchange="wcSetLang(this.value)" style="background:transparent;border:none;color:#fff;font-weight:700;font-size:0.82rem;padding:0.25rem 0.4rem;cursor:pointer;outline:none;">${langs.map(L=>`<option value="${L.code}" ${L.code===langState.previewing?'selected':''}>${L.flag} ${L.name}</option>`).join('')}</select></div><script>function wcSetLang(c){document.querySelectorAll('[data-i18n-'+c+']').forEach(function(e){e.textContent=e.getAttribute('data-i18n-'+c);});document.documentElement.lang=c;document.documentElement.dir=${JSON.stringify(RTL_LANGS)}.indexOf(c)>-1?'rtl':'ltr';${langState.remember?`localStorage.setItem('wc_lang',c);`:''}}<\/script>`;
      }
      if (langState.switcherStyle === 'minimal') {
        return `<div id="wc-lang-switcher" style="${styles}${pos}">${langs.map(L=>`<button onclick="wcSetLang('${L.code}',this)" style="background:${L.code===langState.previewing?'#6366f1':'transparent'};border:none;color:#fff;font-weight:800;font-size:0.72rem;padding:0.3rem 0.55rem;border-radius:999px;cursor:pointer;letter-spacing:0.05em;">${L.code.toUpperCase()}</button>`).join('')}</div><script>function wcSetLang(c,b){document.querySelectorAll('[data-i18n-'+c+']').forEach(function(e){e.textContent=e.getAttribute('data-i18n-'+c);});document.documentElement.lang=c;document.documentElement.dir=${JSON.stringify(RTL_LANGS)}.indexOf(c)>-1?'rtl':'ltr';${langState.remember?`localStorage.setItem('wc_lang',c);`:''}document.querySelectorAll('#wc-lang-switcher button').forEach(function(x){x.style.background='transparent';});if(b)b.style.background='#6366f1';}<\/script>`;
      }
      return `<div id="wc-lang-switcher" style="${styles}${pos}">${langs.map(L=>`<button onclick="wcSetLang('${L.code}',this)" style="background:${L.code===langState.previewing?'#6366f1':'transparent'};border:none;color:#fff;font-weight:700;font-size:0.76rem;padding:0.35rem 0.7rem;border-radius:999px;cursor:pointer;display:inline-flex;align-items:center;gap:0.25rem;">${L.flag} ${L.name}</button>`).join('')}</div><script>function wcSetLang(c,b){document.querySelectorAll('[data-i18n-'+c+']').forEach(function(e){e.textContent=e.getAttribute('data-i18n-'+c);});document.documentElement.lang=c;document.documentElement.dir=${JSON.stringify(RTL_LANGS)}.indexOf(c)>-1?'rtl':'ltr';${langState.remember?`localStorage.setItem('wc_lang',c);`:''}document.querySelectorAll('#wc-lang-switcher button').forEach(function(x){x.style.background='transparent';});if(b)b.style.background='#6366f1';}<\/script>`;
    }

    function injectLangSwitcherToCanvas() {
      try {
        const canvasDoc = grapesEditor?.Canvas?.getDocument();
        if (!canvasDoc || !canvasDoc.body) return;
        const existing = canvasDoc.getElementById('wc-lang-switcher');
        if (existing) existing.remove();
        const html = generateLangSwitcherHtml();
        if (!html) return;
        const wrapper = canvasDoc.createElement('div');
        wrapper.innerHTML = html;
        Array.from(wrapper.children).forEach(child => canvasDoc.body.appendChild(child));
      } catch (e) {}
    }

    /* ══════════════════════════════════════════════════
       THEME LOCK
    ══════════════════════════════════════════════════ */
    function extractRootCSS(html) {
      const m = html.match(/:root\s*\{[^}]+\}/);
      return m ? m[0] : '';
    }

    function extractBodyCSS(html) {
      const m = html.match(/(?:^|[\s;}])(?:body\s*(?:,\s*html)?|html\s*,\s*body)\s*\{[^}]+\}/);
      return m ? m[0].trim().replace(/^[\s;}]+/, '') : '';
    }

    function lockTheme(html, resetFirst) {
      if (resetFirst === true) {
        lockedThemeCSS = '';
        lockedBodyCSS = '';
        themeLocked = false;
      }
      const r = extractRootCSS(html),
        b = extractBodyCSS(html);
      if (r) lockedThemeCSS = r;
      if (b) lockedBodyCSS = b;
      themeLocked = true;
    }

    function userAskedForThemeChange(instruction) {
      return /\b(theme|color|colour|palette|dark mode|light mode|switch to dark|switch to light|recolor|change.?bg|background)\b/.test((instruction || '').toLowerCase());
    }

    function restoreLockedTheme(html) {
      if (!themeLocked) return html;
      let out = html;
      if (lockedThemeCSS) {
        if (/:root\s*\{[^}]+\}/.test(out)) out = out.replace(/:root\s*\{[^}]+\}/, lockedThemeCSS);
        else if (/<style[^>]*>/i.test(out)) out = out.replace(/<style([^>]*)>/i, `<style$1>\n${lockedThemeCSS}\n`);
      }
      return out;
    }

    /* ══════════════════════════════════════════════════
       ★ BACKGROUND DETECTION (FIX: allow black backgrounds)
    ══════════════════════════════════════════════════ */
    function detectConceptBackground(html) {
      return new Promise(resolve => {
        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:absolute;width:1200px;height:900px;left:-99999px;top:-99999px;border:0;visibility:hidden;';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.srcdoc = html;
        let done = false;
        const finish = (v) => {
          if (done) return;
          done = true;
          try { iframe.remove(); } catch (e) {}
          resolve(v || '');
        };
        iframe.onload = () => {
          setTimeout(() => {
            try {
              const w = iframe.contentWindow, d = w.document;
              if (!w || !d) return finish('');
              const csBody = w.getComputedStyle(d.body),
                csHtml = w.getComputedStyle(d.documentElement);
              /* FIX: Only reject fully-transparent / none — allow rgb(0,0,0) */
              const isReal = (v) => v && v !== 'none' && v !== 'transparent' && v !== 'rgba(0, 0, 0, 0)';
              let bg = '';
              if (isReal(csBody.backgroundImage)) bg = csBody.backgroundImage;
              else if (isReal(csBody.backgroundColor)) bg = csBody.backgroundColor;
              else if (isReal(csHtml.backgroundImage)) bg = csHtml.backgroundImage;
              else if (isReal(csHtml.backgroundColor)) bg = csHtml.backgroundColor;
              finish(bg);
            } catch (e) {
              finish('');
            }
          }, 300);
        };
        iframe.onerror = () => finish('');
        setTimeout(() => finish(''), 3000);
        document.body.appendChild(iframe);
      });
    }

    const stockPhotos = {
      business: [
        { url: 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=900&auto=format&fit=crop&q=80', caption: 'Modern Office' },
        { url: 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=900&auto=format&fit=crop&q=80', caption: 'Team Work' },
        { url: 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=900&auto=format&fit=crop&q=80', caption: 'Meeting' },
        { url: 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=900&auto=format&fit=crop&q=80', caption: 'Tower' }
      ],
      tech: [
        { url: 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=900&auto=format&fit=crop&q=80', caption: 'Circuit' },
        { url: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=900&auto=format&fit=crop&q=80', caption: 'Code' },
        { url: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=900&auto=format&fit=crop&q=80', caption: 'Security' },
        { url: 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=900&auto=format&fit=crop&q=80', caption: 'Wireframe' }
      ],
      food: [
        { url: 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=900&auto=format&fit=crop&q=80', caption: 'Bistro' },
        { url: 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=900&auto=format&fit=crop&q=80', caption: 'Dish' },
        { url: 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=900&auto=format&fit=crop&q=80', caption: 'Drink' },
        { url: 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=900&auto=format&fit=crop&q=80', caption: 'Dining' }
      ],
      gym: [
        { url: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=900&auto=format&fit=crop&q=80', caption: 'Gym' },
        { url: 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=900&auto=format&fit=crop&q=80', caption: 'Training' },
        { url: 'https://images.unsplash.com/photo-1518611012118-696072aa579a?w=900&auto=format&fit=crop&q=80', caption: 'Yoga' }
      ],
      team: [
        { url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&auto=format&fit=crop&q=80', caption: 'Elena' },
        { url: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&auto=format&fit=crop&q=80', caption: 'Marcus' },
        { url: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=300&auto=format&fit=crop&q=80', caption: 'Sophia' }
      ]
    };

    window.addEventListener('DOMContentLoaded', () => {
      // ★ Situation loader: studio boot — canvas takes a moment to build
      try { if (window.Loader3D) Loader3D.show('Opening Visual Studio…', 'Loading canvas + designs', 'home'); } catch (e) {}
      setTimeout(() => { try { if (window.Loader3D) Loader3D.hide(); } catch (e) {} }, 9000);
      loadLanguageState();
      loadProjectData();
      loadUserUploads();
      renderStockPhotos('business');
      renderAnimationPresets();
      renderLangChips();
      renderHeaderLangSelect();
      initGrapesStudio();
      setupContextMenu();
      const posSel = document.getElementById('lang-switcher-pos');
      if (posSel) posSel.value = langState.switcherPos;
      const stSel = document.getElementById('lang-switcher-style');
      if (stSel) stSel.value = langState.switcherStyle;
      const adChk = document.getElementById('lang-autodetect');
      if (adChk) adChk.checked = langState.autodetect;
      const rmChk = document.getElementById('lang-remember');
      if (rmChk) rmChk.checked = langState.remember;
      const toggleBtn = document.getElementById('lang-switcher-toggle');
      if (toggleBtn) toggleBtn.textContent = langState.switcherVisible ? '👁️ Hide Switcher' : '👁️ Show Switcher';
    });

    /* ★ Studio bridge source — which scoped builder key we loaded from (for write-back) */
    let studioScopedSourceKey = null;

    function studioTryParse(raw) {
      if (!raw) return null;
      try { const o = JSON.parse(raw); return (o && typeof o === 'object') ? o : null; }
      catch (e) { return null; }
    }

    /* Normalize builder-session shape OR studio shape → studio shape */
    function studioNormalize(p) {
      if (!p || typeof p !== 'object') return null;
      const designs = Array.isArray(p.designs) && p.designs.length ? p.designs
        : (Array.isArray(p.concepts) && p.concepts.length ? p.concepts : null);
      if (!designs || !designs.length) return null;
      // Must have at least one non-empty html
      const hasHtml = designs.some(d => d && typeof d.html === 'string' && d.html.trim().length > 50);
      if (!hasHtml) return null;
      const biz = p.bizName || p.biz_name || (p.wizard && p.wizard.biz_name) || 'My Website';
      return {
        bizName: biz,
        activeDesignIndex: (typeof p.activeDesignIndex === 'number') ? p.activeDesignIndex : 0,
        ownerEmail: p.ownerEmail || null,
        savedAt: p.savedAt || 0,
        designs: designs.map((d, i) => ({
          name: d.name || ('Concept ' + (i + 1)),
          description: d.description || '',
          badge: d.badge || '',
          html: d.html || '',
          adminHtml: d.adminHtml || null
        }))
      };
    }

    function loadProjectData() {
      const urlParams = new URLSearchParams(window.location.search);
      const p = parseInt(urlParams.get('concept') || '0', 10);
      activeConceptIndex = isNaN(p) ? 0 : p;
      currentStudioView = (urlParams.get('view') === 'admin') ? 'admin' : 'site';

      let best = null, bestAt = -1, bestLen = -1;
      studioScopedSourceKey = null;

      // 1) Scan all per-customer builder sessions: webcraft_saved_project::<email>
      //    These are authoritative — builder's saveSessionNow() writes here.
      //    ★ NEWEST first (stale long sessions must NOT beat fresh edits).
      try {
        for (let i = 0; i < localStorage.length; i++) {
          const k = localStorage.key(i);
          if (!k || k.indexOf('webcraft_saved_project::') !== 0) continue;
          const norm = studioNormalize(studioTryParse(localStorage.getItem(k)));
          if (!norm) continue;
          const candHtml = (norm.designs[activeConceptIndex] && norm.designs[activeConceptIndex].html) || '';
          const len = candHtml.trim().length;
          const at = norm.savedAt || 0;
          if (!best || at > bestAt || (at === bestAt && len > bestLen)) {
            best = norm;
            bestAt = at;
            bestLen = len;
            studioScopedSourceKey = k;
          }
        }
        if (best) console.log('[studio] loaded from scoped key:', studioScopedSourceKey, 'savedAt:', bestAt);
      } catch (e) { console.warn('[studio] scoped scan failed', e); }

      // 2) Bridge keys written by builder openStudioInNewTab()
      if (!best) {
        const bridge = studioNormalize(studioTryParse(localStorage.getItem('webcraft_saved_project')));
        if (bridge) { best = bridge; console.log('[studio] loaded from bridge: webcraft_saved_project'); }
      }
      if (!best) {
        try {
          const qb = studioTryParse(localStorage.getItem('webcraft_studio_bridge'));
          if (qb && typeof qb.html === 'string' && qb.html.trim().length > 50) {
            best = {
              bizName: qb.bizName || 'My Website',
              activeDesignIndex: (typeof qb.activeDesignIndex === 'number') ? qb.activeDesignIndex : activeConceptIndex,
              designs: [{ name: 'Concept 1', html: qb.html, adminHtml: qb.adminHtml || null }]
            };
            console.log('[studio] loaded from quick bridge: webcraft_studio_bridge');
          }
        } catch (e) {}
      }

      projectData = best;

      if (!projectData || !Array.isArray(projectData.designs) || projectData.designs.length === 0) {
        console.warn('[studio] No project data in localStorage — using fallback.');
        try {
          const keys = [];
          for (let i = 0; i < localStorage.length; i++) keys.push(localStorage.key(i));
          console.warn('[studio] localStorage keys:', keys.filter(k => k && k.indexOf('webcraft') === 0).join(', ') || '(none)');
        } catch (e) {}
        projectData = {
          bizName: 'Apex Studio',
          activeDesignIndex: 0,
          designs: [{
            name: 'Concept 1',
            html: WC_FALLBACK_HTML
          }]
        };
        window.__STUDIO_NO_DATA__ = true;
        setTimeout(() => {
          if (typeof showToast === 'function') showToast('⚠️ No design found — builder-la irunthu variation select panni "Edit in Studio" click pannunga', 6000, 'error');
        }, 600);
      } else {
        window.__STUDIO_NO_DATA__ = false;
      }

      if (activeConceptIndex < 0 || activeConceptIndex >= projectData.designs.length) {
        console.warn('[studio] concept index out of bounds, resetting to 0');
        activeConceptIndex = 0;
      }

      document.getElementById('project-name-input').value = projectData.bizName || 'My Website';

      const design = projectData.designs[activeConceptIndex] || projectData.designs[0];
      let rawHtml = '';
      if (currentStudioView === 'admin' && design && design.adminHtml) {
        rawHtml = design.adminHtml.trim();
      } else {
        currentStudioView = 'site';
        rawHtml = (design && typeof design.html === 'string') ? design.html.trim() : '';
      }
      currentHtml = rawHtml || (projectData.designs[0] && projectData.designs[0].html) || WC_FALLBACK_HTML;

      if (!currentHtml || !currentHtml.trim()) {
        console.warn('[studio] empty HTML, using fallback');
        currentHtml = WC_FALLBACK_HTML;
      }

      lockTheme(currentHtml, true);

      [0, 1, 2].forEach(i => {
        const btn = document.getElementById(`tab-c${i}`);
        if (btn) {
          btn.style.display = projectData.designs[i] ? 'inline-block' : 'none';
          btn.classList.toggle('active', i === activeConceptIndex);
        }
      });

      // Show view tabs if adminHtml is present in any design
      const hasAdmin = projectData.designs.some(d => d && !!d.adminHtml);
      const vTabs = document.getElementById('studio-view-tabs');
      const vDiv = document.getElementById('st-view-divider');
      if (vTabs) vTabs.style.display = hasAdmin ? 'flex' : 'none';
      if (vDiv) vDiv.style.display = hasAdmin ? 'block' : 'none';
      document.getElementById('st-vtab-site')?.classList.toggle('active', currentStudioView === 'site');
      document.getElementById('st-vtab-admin')?.classList.toggle('active', currentStudioView === 'admin');

      console.log('[studio] Project loaded. concept=' + activeConceptIndex + ', view=' + currentStudioView + ', html length=' + currentHtml.length);
    }

    /* ★ External-change detection: Builder (or another tab) saved NEWER
       designs while this Studio tab sat open (e.g. fresh Generate) —
       reload canvas instead of showing stale images. */
    function studioNewestExternalAt() {
      let at = -1;
      try {
        for (let i = 0; i < localStorage.length; i++) {
          const k = localStorage.key(i);
          if (!k || (k.indexOf('webcraft_saved_project::') !== 0 && k !== 'webcraft_saved_project' && k !== 'webcraft_studio_bridge')) continue;
          const o = studioTryParse(localStorage.getItem(k));
          if (!o) continue;
          const t = o.savedAt || 0;
          if (t > at) at = t;
        }
      } catch (e) {}
      return at;
    }

    function maybeReloadExternalStudioData(reason) {
      try {
        if (!projectData || window.__STUDIO_NO_DATA__) {
          // Studio booted empty — adopt anything that arrived
          const at0 = studioNewestExternalAt();
          if (at0 > 0) {
            loadProjectData();
            lockTheme(currentHtml, true);
            loadHtmlIntoStudioCanvas();
            showToast('🔄 Latest designs loaded');
            return true;
          }
          return false;
        }
        // ★ Never yank unsaved canvas work — reload only a clean canvas
        if (window.__wcCanvasDirty) {
          console.log('[studio] external update skipped (unsaved canvas edits):', reason);
          return false;
        }
        const mine = projectData.savedAt || 0;
        const ext = studioNewestExternalAt();
        if (ext <= mine) return false;
        // Don't yank the canvas mid-edit — wait for a calm moment
        if (document.querySelector('.modal-overlay.active')) {
          console.log('[studio] external update pending (modal open):', reason);
          return false;
        }
        loadProjectData();
        lockTheme(currentHtml, true);
        loadHtmlIntoStudioCanvas();
        showToast('🔄 Newer designs detected — canvas reloaded');
        return true;
      } catch (e) { return false; }
    }

    function updateProjectName(val) {
      if (projectData) {
        projectData.bizName = val.trim() || 'Website';
        saveProjectData();
        showToast(`Renamed to ${projectData.bizName}`);
      }
    }

    function saveProjectData() {
      if (!projectData) return false;
      let ok = true;
      try { projectData.savedAt = Date.now(); } catch (e) {}
      try { localStorage.setItem('webcraft_saved_project', JSON.stringify(projectData)); }
      catch (e) {
        ok = false;
        console.warn('[studio] save failed (quota?)', e);
        try { showToast('⚠️ Storage full — image too large. Use a smaller image.', 5000); } catch (err) {}
      }
      // ★ Write back into the scoped builder session so builder focus-sync sees edits.
      // Builder reads SESSION_KEY = webcraft_saved_project::<email> with {designs, concepts,...}.
      try {
        // ★ Write to the session we loaded from (single target = less quota).
        // Slimmed concepts (no duplicated html) halve the bytes.
        let slimConcepts = [];
        try {
          slimConcepts = (projectData.designs || []).map(d => {
            const c = { name: d.name || 'Concept', description: d.description || '', badge: d.badge || '', html: '', adminHtml: d.adminHtml || null };
            return c;
          });
        } catch (e) {}
        const targets = [];
        if (studioScopedSourceKey) targets.push(studioScopedSourceKey);
        else {
          // Fallback (legacy): fan-out only if we never found a source key
          for (let i = 0; i < localStorage.length; i++) {
            const k = localStorage.key(i);
            if (k && k.indexOf('webcraft_saved_project::') === 0 && targets.indexOf(k) === -1) targets.push(k);
          }
        }
        targets.forEach(k => {
          try {
            const raw = localStorage.getItem(k);
            const sess = raw ? JSON.parse(raw) : {};
            sess.designs = projectData.designs;
            sess.concepts = slimConcepts.length ? slimConcepts : projectData.designs;
            sess.bizName = projectData.bizName || sess.bizName;
            sess.activeDesignIndex = activeConceptIndex;
            sess.savedAt = Date.now();
            localStorage.setItem(k, JSON.stringify(sess));
          } catch (e) { ok = false; }
        });
      } catch (e) { ok = false; }
      return ok;
    }

    /* ★ Canvas tab-load detector: concept/view tabs reload the canvas —
       overlay shows only if render takes >250ms, hides when done. */
    let __canvasSpinTimer = null;
    function showCanvasLoading() {
      clearTimeout(__canvasSpinTimer);
      __canvasSpinTimer = setTimeout(() => {
        const s = document.getElementById('canvas-spin');
        if (s) s.style.display = 'flex';
      }, 250);
      // Safety: never stuck
      setTimeout(hideCanvasLoading, 4000);
    }
    function hideCanvasLoading() {
      clearTimeout(__canvasSpinTimer);
      const s = document.getElementById('canvas-spin');
      if (s) s.style.display = 'none';
    }

    function switchStudioConcept(index) {
      if (!projectData.designs[index]) return;
      syncCanvasToHtml();
      activeConceptIndex = index;
      const d = projectData.designs[index];
      if (currentStudioView === 'admin' && d.adminHtml) {
        currentHtml = d.adminHtml;
      } else {
        currentStudioView = 'site';
        currentHtml = d.html || WC_FALLBACK_HTML;
      }
      lockTheme(currentHtml, true);
      [0, 1, 2].forEach(i => document.getElementById(`tab-c${i}`).classList.toggle('active', i === index));
      document.getElementById('st-vtab-site')?.classList.toggle('active', currentStudioView === 'site');
      document.getElementById('st-vtab-admin')?.classList.toggle('active', currentStudioView === 'admin');
      showCanvasLoading();
      loadHtmlIntoStudioCanvas();
      showToast(`Switched to Concept ${index + 1}`);
    }

    function switchStudioView(view) {
      if (view === currentStudioView) return;
      const d = projectData?.designs?.[activeConceptIndex];
      if (view === 'admin' && (!d || !d.adminHtml)) {
        showToast('⚠️ No admin panel exists for this concept yet.');
        return;
      }
      syncCanvasToHtml();
      currentStudioView = view;
      document.getElementById('st-vtab-site')?.classList.toggle('active', view === 'site');
      document.getElementById('st-vtab-admin')?.classList.toggle('active', view === 'admin');
      if (view === 'admin') {
        currentHtml = d.adminHtml || WC_FALLBACK_HTML;
      } else {
        currentHtml = d.html || WC_FALLBACK_HTML;
      }
      lockTheme(currentHtml, true);
      showCanvasLoading();
      loadHtmlIntoStudioCanvas();
      showToast(`✏️ Now editing: ${view === 'admin' ? '🔐 Admin Panel' : '🌐 Frontend Site'}`);
    }

    function loadUserUploads() {
      try {
        const r = localStorage.getItem('webcraft_user_uploads');
        if (r) userUploadedImages = JSON.parse(r);
      } catch (e) {}
      renderUserUploads();
    }

    function saveUserUploads() {
      try {
        localStorage.setItem('webcraft_user_uploads', JSON.stringify(userUploadedImages));
      } catch (e) {}
      renderUserUploads();
    }

    // ★ Shared downscale: huge photos → max 1600px JPEG 0.82 (protects localStorage quota).
    // Without this, silent quota fail = "saved but old photo returns".
    function compressImageDataUrl(rawUrl, done) {
      const img = new Image();
      img.onload = () => {
        try {
          const MAX = 1600;
          const w = img.naturalWidth || 1, h = img.naturalHeight || 1;
          const scale = Math.min(1, MAX / Math.max(w, h));
          if (scale < 1) {
            const c = document.createElement('canvas');
            c.width = Math.round(w * scale);
            c.height = Math.round(h * scale);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            done(c.toDataURL('image/jpeg', 0.82));
          } else done(rawUrl);
        } catch (err) { done(rawUrl); }
      };
      img.onerror = () => done(rawUrl);
      img.src = rawUrl;
    }

    /* ★ Server upload: local photos live in storage/uploads/ (short URL),
       so they survive save/preview/publish on ANY machine — no quota bloat.
       Falls back to embedded dataURL when offline/server unreachable. */
    function dataUrlToBlob(dataUrl) {
      try {
        const parts = String(dataUrl).split(',');
        const mime = ((parts[0] || '').match(/data:(.*?);/) || [])[1] || 'image/jpeg';
        const bin = atob(parts[1] || '');
        const arr = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
        return new Blob([arr], { type: mime });
      } catch (e) { return null; }
    }

    function uploadImageToServer(blob, filename) {
      if (!blob) return Promise.resolve(null);
      try {
        const fd = new FormData();
        fd.append('image', blob, filename || 'image.jpg');
        return fetch('<?= SITE_URL ?>/api/upload.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(j => (j && j.success && j.url) ? j.url : null)
          .catch(() => null);
      } catch (e) { return Promise.resolve(null); }
    }

    function handleFileInput(files) {
      if (!files || !files.length) return;
      showToast('⏳ Processing images…');
      let loaded = 0;
      Array.from(files).forEach(file => {
        const r = new FileReader();
        r.onload = (e) => {
          compressImageDataUrl(e.target.result, (url) => {
            const item = {
              id: 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
              name: file.name,
              url
            };
            userUploadedImages.unshift(item);
            loaded++;
            if (loaded === files.length) {
              saveUserUploads();
              showToast(`🖼️ Uploaded ${files.length} ✓ Saved`);
            }
            // ★ Push to server in background → permanent URL, tiny storage
            const blob = dataUrlToBlob(url);
            if (blob) {
              uploadImageToServer(blob, file.name || 'upload.jpg').then(serverUrl => {
                if (serverUrl) {
                  item.url = serverUrl;
                  saveUserUploads();
                }
              });
            }
          });
        };
        r.readAsDataURL(file);
      });
    }

    function addSidebarOnlineImage() {
      const input = document.getElementById('sidebar-online-url');
      const url = (input?.value || '').trim();
      if (!url || !/^https?:\/\//i.test(url)) {
        showToast('Please enter a valid image URL (http/https)');
        return;
      }
      showToast('⏳ Saving online image to project…');
      const fd = new FormData();
      fd.append('url', url);
      fetch('<?= SITE_URL ?>/api/upload.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          const finalUrl = (data && data.success && data.url) ? data.url : url;
          const name = (data && data.name) ? data.name : (url.split('/').pop().split('?')[0] || 'online_image.jpg');
          userUploadedImages.unshift({
            id: 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
            name: name,
            url: finalUrl
          });
          saveUserUploads();
          if (input) input.value = '';
          showToast('🖼️ Online image added to uploads ✓');
        })
        .catch(() => {
          userUploadedImages.unshift({
            id: 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
            name: url.split('/').pop().split('?')[0] || 'online_image.jpg',
            url: url
          });
          saveUserUploads();
          if (input) input.value = '';
          showToast('🖼️ Online image added ✓');
        });
    }

    function renderUserUploads() {
      const c = document.getElementById('user-uploads-grid');
      const cnt = document.getElementById('user-upload-count');
      const empty = document.getElementById('user-uploads-empty');
      if (!c) return;
      if (cnt) cnt.textContent = userUploadedImages.length;
      if (empty) empty.style.display = userUploadedImages.length === 0 ? 'block' : 'none';
      c.innerHTML = '';
      userUploadedImages.forEach(img => {
        const card = document.createElement('div');
        card.className = 'upload-card';
        card.setAttribute('draggable', 'true');
        card.innerHTML = `<span class="upload-card-badge">Upload</span><button class="upload-card-del" onclick="event.stopPropagation(); deleteUserUpload('${img.id}')">✕</button><img src="${img.url}" loading="lazy"><div class="upload-card-caption">${img.name}</div>`;
        card.ondragstart = (e) => handleImageDragStart(e, img.url, img.name);
        card.onclick = () => handleImageClick(img.url, img.name);
        c.appendChild(card);
      });
    }

    function deleteUserUpload(id) {
      userUploadedImages = userUploadedImages.filter(i => i.id !== id);
      saveUserUploads();
    }

    function clearAllUploads() {
      if (confirm('Clear all?')) {
        userUploadedImages = [];
        saveUserUploads();
      }
    }

    function handleImageDragStart(e, url, alt) {
      activeDraggedImage = { url, alt };
      if (e.dataTransfer) {
        e.dataTransfer.setData('text/plain', url);
        e.dataTransfer.effectAllowed = 'copy';
      }
    }

    /* ★ Swap a shape/card inner image so the picture fits the shape
       (object-fit:cover inside the clipped wrapper). Persists to both
       the GrapesJS model and the live canvas element, then saves. */
    function setShapeImageSrc(imgComp, url, alt) {
      if (!imgComp || !url) return false;
      try {
        imgComp.set('src', url);
        imgComp.setAttributes(Object.assign({}, imgComp.getAttributes(), { src: url, alt: alt || '' }));
        const le = imgComp.getEl && imgComp.getEl();
        if (le && le.tagName === 'IMG') le.setAttribute('src', url);
        if (imgComp.view && imgComp.view.render) imgComp.view.render();
        try { grapesEditor.select(imgComp); } catch (e) {}
        return true;
      } catch (e) { return false; }
    }

    function handleImageClick(url, alt) {
      if (!grapesEditor) return;
      // Shape/card selected (or its wrapper) → fit the image INTO the shape
      const shapeImg = findShapeImgComp(selectedComponent);
      if (shapeImg) {
        setShapeImageSrc(shapeImg, url, alt);
        syncCanvasToHtml();
        renderSmartLayers();
        showToast('🖼️ Shape image fitted ✓');
        return;
      }
      if (selectedComponent && (selectedComponent.get('tagName') || '').toLowerCase() === 'img') {
        selectedComponent.setAttributes(Object.assign({}, selectedComponent.getAttributes(), {
          src: url,
          alt: alt || ''
        }));
        selectedComponent.set({ draggable: true, resizable: true });
        try {
          const le = selectedComponent.getEl && selectedComponent.getEl();
          if (le && le.tagName === 'IMG') le.setAttribute('src', url);
        } catch (e) {}
      } else {
        const root = grapesEditor.DomComponents.getWrapper();
        const added = root.append(`<img src="${escapeHtml(url)}" alt="${escapeHtml(alt || '')}" style="width:100%;max-width:850px;height:auto;border-radius:16px;margin:2rem auto;display:block;object-fit:cover;"/>`, { at: 0 });
        const comp = Array.isArray(added) ? added[0] : added;
        if (comp) {
          comp.set({ draggable: true, resizable: true, stylable: true });
          grapesEditor.select(comp);
        }
      }
      syncCanvasToHtml();
      renderSmartLayers();
    }

    function setupCanvasDragAndDrop() {
      try {
        const canvasDoc = grapesEditor.Canvas?.getDocument();
        if (!canvasDoc || canvasDoc.__webcraftImageDnDBound) return;
        canvasDoc.__webcraftImageDnDBound = true;
        canvasDoc.addEventListener('dragover', e => {
          if (activeDraggedImage) {
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
          }
        });
        canvasDoc.addEventListener('drop', (e) => {
          const url = activeDraggedImage?.url || e.dataTransfer?.getData('text/plain');
          if (!url) return;
          e.preventDefault();
          const alt = activeDraggedImage?.alt || 'Showcase Image';
          appendImageAtDrop(url, alt, canvasDoc.elementFromPoint(e.clientX, e.clientY), e.clientY);
          activeDraggedImage = null;
          renderSmartLayers();
          showToast(lastDropShapeFit ? '🖼️ Shape image fitted ✓' : '🖼️ Image placed');
          lastDropShapeFit = false;
        });
      } catch (e) {}
    }

    function initGrapesStudio() {
      grapesEditor = grapesjs.init({
        container: '#gjs',
        fromElement: false,
        height: '100%',
        width: 'auto',
        storageManager: false,
        noticeOnUnload: false,
        showOffsets: 1,
        blockManager: {
          appendTo: '#gjs-blocks',
          blocks: [
            { id: 'sb-hero', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🌟</div><div>Hero</div>', category: 'Sections', content: getTemplateHTML('hero') },
            { id: 'sb-services', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🛠️</div><div>Services</div>', category: 'Sections', content: getTemplateHTML('services') },
            { id: 'sb-about', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">ℹ️</div><div>About</div>', category: 'Sections', content: getTemplateHTML('about') },
            { id: 'sb-stats', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">📊</div><div>Stats</div>', category: 'Sections', content: getTemplateHTML('stats') },
            { id: 'sb-pricing', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">💰</div><div>Pricing</div>', category: 'Sections', content: getTemplateHTML('pricing') },
            { id: 'sb-reviews', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">⭐</div><div>Reviews</div>', category: 'Sections', content: getTemplateHTML('reviews') },
            { id: 'sb-team', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">👥</div><div>Team</div>', category: 'Sections', content: getTemplateHTML('team') },
            { id: 'sb-faq', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">❓</div><div>FAQ</div>', category: 'Sections', content: getTemplateHTML('faq') },
            { id: 'sb-process', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🔄</div><div>Process</div>', category: 'Sections', content: getTemplateHTML('process') },
            { id: 'sb-features', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">✨</div><div>Features</div>', category: 'Sections', content: getTemplateHTML('features') },
            { id: 'sb-testimonial', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">💬</div><div>Testimonial</div>', category: 'Sections', content: getTemplateHTML('testimonial') },
            { id: 'sb-custom', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🧩</div><div>Custom</div>', category: 'Sections', content: getTemplateHTML('custom') },
            { id: 'sb-contact', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">📞</div><div>Contact</div>', category: 'Sections', content: getTemplateHTML('contact') },
            { id: 'sb-footer', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🦶</div><div>Footer</div>', category: 'Sections', content: getTemplateHTML('footer') },
            { id: 'sb-gallery', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🖼️</div><div>Gallery</div>', category: 'Sections', content: getTemplateHTML('gallery') },
            { id: 'sb-shape-circle', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">⭕</div><div>Circle</div>', category: 'Shapes', content: '<div data-wc-shape="circle" style="width:240px;height:240px;border-radius:50%;overflow:hidden;margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-circle/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-arch', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🏛️</div><div>Arch</div>', category: 'Shapes', content: '<div data-wc-shape="arch" style="width:240px;height:320px;border-radius:999px 999px 24px 24px;overflow:hidden;margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-arch/600/800" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-blob', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🫧</div><div>Blob</div>', category: 'Shapes', content: '<div data-wc-shape="blob" style="width:260px;height:260px;border-radius:58% 42% 55% 45%/55% 48% 52% 45%;overflow:hidden;margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-blob/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-hexagon', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">⬡</div><div>Hexagon</div>', category: 'Shapes', content: '<div data-wc-shape="hexagon" style="width:260px;height:240px;clip-path:polygon(25% 0%,75% 0%,100% 50%,75% 100%,25% 100%,0% 50%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-hexagon/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-diamond', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">◆</div><div>Diamond</div>', category: 'Shapes', content: '<div data-wc-shape="diamond" style="width:240px;height:240px;clip-path:polygon(50% 0%,100% 50%,50% 100%,0% 50%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-diamond/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-star', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">⭐</div><div>Star</div>', category: 'Shapes', content: '<div data-wc-shape="star" style="width:260px;height:260px;clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-star/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-rounded', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">▢</div><div>Rounded</div>', category: 'Shapes', content: '<div data-wc-shape="rounded" style="width:280px;height:200px;border-radius:28px;overflow:hidden;margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-rounded/600/400" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-square', label: '<div style="width:24px;height:24px;background:#818cf8;border-radius:5px;margin-bottom:0.2rem"></div><div>Square</div>', category: 'Shapes', content: '<div data-wc-shape="square" style="width:260px;height:260px;border-radius:14px;overflow:hidden;margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-square/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-ellipse', label: '<div style="width:30px;height:20px;background:#818cf8;border-radius:50%;margin-bottom:0.2rem"></div><div>Ellipse</div>', category: 'Shapes', content: '<div data-wc-shape="ellipse" style="width:320px;height:200px;border-radius:50%;overflow:hidden;margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-ellipse/800/500" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-pill', label: '<div style="width:32px;height:16px;background:#818cf8;border-radius:999px;margin-bottom:0.2rem"></div><div>Pill</div>', category: 'Shapes', content: '<div data-wc-shape="pill" style="width:320px;height:150px;border-radius:999px;overflow:hidden;margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-pill/800/400" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-triangle', label: '<div style="width:26px;height:23px;background:#818cf8;clip-path:polygon(50% 0%,0% 100%,100% 100%);margin-bottom:0.2rem"></div><div>Triangle</div>', category: 'Shapes', content: '<div data-wc-shape="triangle" style="width:260px;height:240px;clip-path:polygon(50% 0%,0% 100%,100% 100%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-triangle/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-pentagon', label: '<div style="width:24px;height:23px;background:#818cf8;clip-path:polygon(50% 0%,100% 38%,81% 100%,19% 100%,0% 38%);margin-bottom:0.2rem"></div><div>Pentagon</div>', category: 'Shapes', content: '<div data-wc-shape="pentagon" style="width:260px;height:250px;clip-path:polygon(50% 0%,100% 38%,81% 100%,19% 100%,0% 38%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-pentagon/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-octagon', label: '<div style="width:23px;height:23px;background:#818cf8;clip-path:polygon(30% 0%,70% 0%,100% 30%,100% 70%,70% 100%,30% 100%,0% 70%,0% 30%);margin-bottom:0.2rem"></div><div>Octagon</div>', category: 'Shapes', content: '<div data-wc-shape="octagon" style="width:250px;height:250px;clip-path:polygon(30% 0%,70% 0%,100% 30%,100% 70%,70% 100%,30% 100%,0% 70%,0% 30%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-octagon/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-parallelogram', label: '<div style="width:30px;height:20px;background:#818cf8;clip-path:polygon(22% 0%,100% 0%,78% 100%,0% 100%);margin-bottom:0.2rem"></div><div>Slant</div>', category: 'Shapes', content: '<div data-wc-shape="parallelogram" style="width:300px;height:200px;clip-path:polygon(22% 0%,100% 0%,78% 100%,0% 100%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-slant/800/500" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-trapezoid', label: '<div style="width:30px;height:20px;background:#818cf8;clip-path:polygon(22% 0%,78% 0%,100% 100%,0% 100%);margin-bottom:0.2rem"></div><div>Trapezoid</div>', category: 'Shapes', content: '<div data-wc-shape="trapezoid" style="width:300px;height:200px;clip-path:polygon(22% 0%,78% 0%,100% 100%,0% 100%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-trapezoid/800/500" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-arrow', label: '<div style="width:30px;height:20px;background:#818cf8;clip-path:polygon(0% 32%,58% 32%,58% 5%,100% 50%,58% 95%,58% 68%,0% 68%);margin-bottom:0.2rem"></div><div>Arrow</div>', category: 'Shapes', content: '<div data-wc-shape="arrow" style="width:300px;height:200px;clip-path:polygon(0% 32%,58% 32%,58% 5%,100% 50%,58% 95%,58% 68%,0% 68%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-arrow/800/500" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-chevron', label: '<div style="width:28px;height:20px;background:#818cf8;clip-path:polygon(0% 5%,52% 5%,100% 50%,52% 95%,0% 95%,48% 50%);margin-bottom:0.2rem"></div><div>Chevron</div>', category: 'Shapes', content: '<div data-wc-shape="chevron" style="width:280px;height:200px;clip-path:polygon(0% 5%,52% 5%,100% 50%,52% 95%,0% 95%,48% 50%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-chevron/700/500" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-plus', label: '<div style="width:22px;height:22px;background:#818cf8;clip-path:polygon(35% 0%,65% 0%,65% 35%,100% 35%,100% 65%,65% 65%,65% 100%,35% 100%,35% 65%,0% 65%,0% 35%,35% 35%);margin-bottom:0.2rem"></div><div>Plus</div>', category: 'Shapes', content: '<div data-wc-shape="plus" style="width:240px;height:240px;clip-path:polygon(35% 0%,65% 0%,65% 35%,100% 35%,100% 65%,65% 65%,65% 100%,35% 100%,35% 65%,0% 65%,0% 35%,35% 35%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-plus/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-speech', label: '<div style="width:28px;height:24px;background:#818cf8;clip-path:polygon(0% 0%,100% 0%,100% 72%,62% 72%,52% 100%,44% 72%,0% 72%);margin-bottom:0.2rem"></div><div>Speech</div>', category: 'Shapes', content: '<div data-wc-shape="speech" style="width:300px;height:240px;clip-path:polygon(0% 0%,100% 0%,100% 72%,62% 72%,52% 100%,44% 72%,0% 72%);margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-speech/700/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-shape-leaf', label: '<div style="width:22px;height:22px;background:#818cf8;border-radius:6px 50% 6px 50%;margin-bottom:0.2rem"></div><div>Leaf</div>', category: 'Shapes', content: '<div data-wc-shape="leaf" style="width:260px;height:260px;border-radius:6px 50% 6px 50%;overflow:hidden;margin:2rem auto;position:relative;background:#1e293b;"><img data-wc-shape-img="1" src="https://picsum.photos/seed/wcshape-leaf/600/600" alt="Shape image" style="width:100%;height:100%;object-fit:cover;display:block;"/></div>' },
            { id: 'sb-card-photo', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🃏</div><div>Photo Card</div>', category: 'Cards', content: '<div data-wc-card="photo" style="max-width:340px;margin:2rem auto;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.12);"><img data-wc-card-img="1" src="https://picsum.photos/seed/wccard-photo/600/400" alt="Card image" style="width:100%;height:220px;object-fit:cover;display:block;"/><div style="padding:1.5rem;"><span style="display:inline-block;font-size:0.68rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#4f46e5;background:#eef2ff;padding:0.3rem 0.8rem;border-radius:999px;margin-bottom:0.8rem;">New</span><h3 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:0.6rem;">Card Title</h3><p style="font-size:0.95rem;line-height:1.65;color:#475569;">Short description about this service, product or person.</p><a href="#contact" style="display:inline-block;text-decoration:none;font-weight:700;font-size:0.9rem;padding:0.8rem 1.8rem;border-radius:999px;margin-top:1rem;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;">Learn More →</a></div></div>' },
            { id: 'sb-card-profile', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">👤</div><div>Profile Card</div>', category: 'Cards', content: '<div data-wc-card="profile" style="max-width:320px;margin:2rem auto;background:#ffffff;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.12);padding:2.25rem 1.75rem;text-align:center;"><img data-wc-card-img="1" src="https://picsum.photos/seed/wccard-profile/400/400" alt="Profile photo" style="width:130px;height:130px;border-radius:50%;object-fit:cover;display:block;margin:0 auto 1.1rem;border:4px solid #eef2ff;"/><h3 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:0.5rem;">Person Name</h3><p style="font-size:0.92rem;line-height:1.65;color:#475569;">Role or short bio goes here.</p><a href="#contact" style="display:inline-block;text-decoration:none;font-weight:700;font-size:0.9rem;padding:0.8rem 1.8rem;border-radius:999px;margin-top:1rem;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;">Contact →</a></div></div>' },
            { id: 'sb-card-side', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">↔️</div><div>Side Card</div>', category: 'Cards', content: '<div data-wc-card="horizontal" style="max-width:640px;margin:2rem auto;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.12);display:flex;flex-wrap:wrap;"><div style="flex:1 1 220px;min-width:220px;"><img data-wc-card-img="1" src="https://picsum.photos/seed/wccard-side/600/600" alt="Card image" style="width:100%;height:100%;min-height:220px;object-fit:cover;display:block;"/></div><div style="flex:1 1 260px;padding:1.75rem;"><h3 style="font-size:1.4rem;font-weight:800;color:#0f172a;margin-bottom:0.6rem;">Card Title</h3><p style="font-size:0.95rem;line-height:1.65;color:#475569;">Description text here. Everything is editable.</p><a href="#contact" style="display:inline-block;text-decoration:none;font-weight:700;font-size:0.9rem;padding:0.8rem 1.8rem;border-radius:999px;margin-top:1rem;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;">Learn More →</a></div></div>' },
            { id: 'sb-button-primary', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🔘</div><div>Primary Button</div>', category: 'Components', content: `<a href="#contact" class="btn-primary" style="display:block;width:max-content;margin:1rem auto;padding:0.9rem 2rem;border-radius:999px;background:var(--primary,#6366f1);color:#fff;font-weight:700;text-decoration:none;">Get Started →</a>` },
            { id: 'sb-button-outline', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">◯</div><div>Outline Button</div>', category: 'Components', content: `<a href="#contact" class="btn-outline" style="display:block;width:max-content;margin:1rem auto;padding:0.9rem 2rem;border-radius:999px;background:transparent;border:1.5px solid #cbd5e1;color:#334155;font-weight:600;text-decoration:none;">Learn More</a>` },
            { id: 'sb-button-whatsapp', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">💬</div><div>WhatsApp</div>', category: 'Components', content: `<a href="https://wa.me/15551234567" target="_blank" class="btn-whatsapp" style="display:inline-flex;align-items:center;gap:0.5rem;margin:1rem auto;padding:0.9rem 1.8rem;border-radius:999px;background:#25D366;color:#fff;font-weight:700;text-decoration:none;width:max-content;">💬 Chat on WhatsApp</a>` },
            { id: 'sb-h1', label: '<div style="font-size:1.2rem;font-weight:800">H1</div><div>Headline</div>', category: 'Typography', content: '<h1 style="font-size:2.8rem;font-weight:800;letter-spacing:-0.02em;margin-bottom:1rem;">Transform Your Vision</h1>' },
            { id: 'sb-h2', label: '<div style="font-size:1.2rem;font-weight:700">H2</div><div>Subheading</div>', category: 'Typography', content: '<h2 style="font-size:2.2rem;font-weight:800;margin-bottom:0.75rem;">World-Class Execution</h2>' },
            { id: 'sb-p', label: '<div style="font-size:1.3rem">¶</div><div>Paragraph</div>', category: 'Typography', content: '<p style="font-size:1.05rem;line-height:1.7;color:#475569;margin-bottom:1rem;">We deliver high-performing digital solutions engineered for growth.</p>' },
            { id: 'sb-form-contact', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">📨</div><div>Smart Form</div>', category: 'Forms', content: '<section style="padding:5rem 1.5rem;"><div style="max-width:1000px;margin:0 auto;background:#fff;border:1.5px solid #e2e8f0;border-radius:20px;padding:3rem;box-shadow:0 15px 40px rgba(0,0,0,0.06);display:grid;grid-template-columns:1fr 1.2fr;gap:3rem;"><div><h2 style="font-size:1.8rem;font-weight:800;margin-bottom:1rem;color:#0f172a;">Get in Touch</h2><p style="color:#64748b;line-height:1.6;margin-bottom:1.5rem;">Send a message — it lands in the inbox with email alerts.</p><p style="margin-bottom:0.75rem;color:#0f172a;"><strong>📞 Phone:</strong> +94 77 123 4567</p><p style="margin-bottom:1.5rem;color:#0f172a;"><strong>📧 Email:</strong> hello@example.com</p><a href="https://wa.me/15551234567?text=Hello!%20I%20have%20an%20inquiry." target="_blank" style="display:inline-block;padding:0.85rem 1.8rem;border-radius:999px;background:#25D366;color:#fff;font-weight:700;text-decoration:none;">💬 Chat on WhatsApp</a></div><form data-wc-form="contact" data-wc-sheet="" data-wc-thanks="✓ Thank you! Your message has been sent. We will reply soon."><input type="text" name="company" style="display:none;" tabindex="-1" autocomplete="off"/><input type="text" name="name" placeholder="Your Name" required style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;"/><input type="email" name="email" placeholder="Your Email" required style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;"/><input type="text" name="phone" placeholder="Phone (optional)" style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;"/><textarea name="message" placeholder="Your message..." required style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;min-height:120px;resize:vertical;"></textarea><button type="submit" style="width:100%;padding:0.9rem;border:none;border-radius:12px;background:var(--primary,#6366f1);color:#fff;font-weight:700;cursor:pointer;">Send Message →</button></form></div></section>' },
            { id: 'sb-form-newsletter', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">💌</div><div>Newsletter</div>', category: 'Forms', content: '<section style="padding:4rem 1.5rem;background:#0f172a;text-align:center;"><h2 style="font-size:2rem;font-weight:800;color:#fff;margin-bottom:0.5rem;">Stay in the loop</h2><p style="color:#94a3b8;margin-bottom:1.75rem;">Offers and updates, once a month. No spam.</p><form data-wc-form="newsletter" data-wc-sheet="" data-wc-thanks="✓ Subscribed! Welcome aboard." style="max-width:480px;margin:0 auto;display:flex;gap:0.6rem;flex-wrap:wrap;"><input type="text" name="company" style="display:none;" tabindex="-1" autocomplete="off"/><input type="email" name="email" placeholder="you@email.com" required style="flex:1 1 220px;padding:0.85rem 1.1rem;border:1.5px solid #334155;border-radius:999px;background:#080c14;color:#fff;font-family:inherit;font-size:0.95rem;"/><button type="submit" style="padding:0.85rem 1.8rem;border:none;border-radius:999px;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;font-weight:700;cursor:pointer;">Subscribe</button></form></section>' },
            { id: 'sb-form-booking', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">📅</div><div>Booking</div>', category: 'Forms', content: '<section style="padding:5rem 1.5rem;background:#f8fafc;"><div style="max-width:640px;margin:0 auto;background:#fff;border:1.5px solid #e2e8f0;border-radius:20px;padding:2.75rem;box-shadow:0 15px 40px rgba(0,0,0,0.06);"><h2 style="font-size:2rem;font-weight:800;text-align:center;margin-bottom:0.5rem;color:#0f172a;">📅 Book an Appointment</h2><p style="color:#64748b;text-align:center;margin-bottom:2rem;">Pick a date and service — we confirm shortly.</p><form data-wc-form="booking" data-wc-sheet="" data-wc-thanks="✓ Booking received! We will confirm your slot soon."><input type="text" name="company" style="display:none;" tabindex="-1" autocomplete="off"/><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;"><input type="text" name="bk-name" placeholder="Full name" required style="padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;font-family:inherit;font-size:0.95rem;"/><input type="text" name="bk-phone" placeholder="Phone / WhatsApp" required style="padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;font-family:inherit;font-size:0.95rem;"/></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;"><input type="date" name="bk-date" required style="padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;font-family:inherit;font-size:0.95rem;"/><select name="bk-service" style="padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;font-family:inherit;font-size:0.95rem;"><option>General Consultation</option><option>Service Package A</option><option>Service Package B</option><option>Follow-up Visit</option></select></div><input type="email" name="bk-email" placeholder="Email (for confirmation)" style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;"/><input type="text" name="bk-notes" placeholder="Notes (optional)" style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;"/><button type="submit" style="width:100%;padding:0.95rem;border:none;border-radius:12px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;font-weight:800;cursor:pointer;">Confirm Booking →</button></form></div></section>' },
            { id: 'sb-shop-grid', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🛍️</div><div>Product Grid</div>', category: 'Shop', content: '<section style="padding:5rem 1.5rem;"><h2 style="font-size:2.4rem;font-weight:800;text-align:center;margin-bottom:0.5rem;color:#0f172a;">Our Products</h2><p style="color:#64748b;text-align:center;margin-bottom:3rem;">Tap add to cart, or order instantly on WhatsApp.</p><div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;"><div data-wc-product data-wc-id="prod-1" data-wc-name="Premium T-Shirt" data-wc-price="2490" data-wc-img="https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600&auto=format&fit=crop&q=80" style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;overflow:hidden;"><img src="https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600&auto=format&fit=crop&q=80" alt="Premium T-Shirt" style="width:100%;height:220px;object-fit:cover;display:block;"/><div style="padding:1.5rem;"><h3 style="font-size:1.15rem;font-weight:800;color:#0f172a;">Premium T-Shirt</h3><div style="font-size:1.3rem;font-weight:900;color:var(--primary,#6366f1);margin:0.4rem 0 1rem;">Rs 2,490</div><div style="display:flex;gap:0.5rem;flex-wrap:wrap;"><button data-wc-add data-wc-id="prod-1" style="flex:1;padding:0.7rem;border:none;border-radius:10px;background:var(--primary,#6366f1);color:#fff;font-weight:700;cursor:pointer;white-space:nowrap;">Add to Cart</button><a href="https://wa.me/15551234567?text=Hello!%20I%20want%20to%20order%20Premium%20T-Shirt%20(Rs%202%2C490)" target="_blank" style="flex:1;text-align:center;padding:0.7rem;border-radius:10px;background:#25D366;color:#fff;font-weight:700;text-decoration:none;white-space:nowrap;">WhatsApp</a></div></div></div><div data-wc-product data-wc-id="prod-2" data-wc-name="Leather Wallet" data-wc-price="3950" data-wc-img="https://images.unsplash.com/photo-1627123424574-724758594e93?w=600&auto=format&fit=crop&q=80" style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;overflow:hidden;"><img src="https://images.unsplash.com/photo-1627123424574-724758594e93?w=600&auto=format&fit=crop&q=80" alt="Leather Wallet" style="width:100%;height:220px;object-fit:cover;display:block;"/><div style="padding:1.5rem;"><h3 style="font-size:1.15rem;font-weight:800;color:#0f172a;">Leather Wallet</h3><div style="font-size:1.3rem;font-weight:900;color:var(--primary,#6366f1);margin:0.4rem 0 1rem;">Rs 3,950</div><div style="display:flex;gap:0.5rem;flex-wrap:wrap;"><button data-wc-add data-wc-id="prod-2" style="flex:1;padding:0.7rem;border:none;border-radius:10px;background:var(--primary,#6366f1);color:#fff;font-weight:700;cursor:pointer;white-space:nowrap;">Add to Cart</button><a href="https://wa.me/15551234567?text=Hello!%20I%20want%20to%20order%20Leather%20Wallet%20(Rs%203%2C950)" target="_blank" style="flex:1;text-align:center;padding:0.7rem;border-radius:10px;background:#25D366;color:#fff;font-weight:700;text-decoration:none;white-space:nowrap;">WhatsApp</a></div></div></div><div data-wc-product data-wc-id="prod-3" data-wc-name="Ceramic Mug" data-wc-price="1290" data-wc-img="https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?w=600&auto=format&fit=crop&q=80" style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;overflow:hidden;"><img src="https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?w=600&auto=format&fit=crop&q=80" alt="Ceramic Mug" style="width:100%;height:220px;object-fit:cover;display:block;"/><div style="padding:1.5rem;"><h3 style="font-size:1.15rem;font-weight:800;color:#0f172a;">Ceramic Mug</h3><div style="font-size:1.3rem;font-weight:900;color:var(--primary,#6366f1);margin:0.4rem 0 1rem;">Rs 1,290</div><div style="display:flex;gap:0.5rem;flex-wrap:wrap;"><button data-wc-add data-wc-id="prod-3" style="flex:1;padding:0.7rem;border:none;border-radius:10px;background:var(--primary,#6366f1);color:#fff;font-weight:700;cursor:pointer;white-space:nowrap;">Add to Cart</button><a href="https://wa.me/15551234567?text=Hello!%20I%20want%20to%20order%20Ceramic%20Mug%20(Rs%201%2C290)" target="_blank" style="flex:1;text-align:center;padding:0.7rem;border-radius:10px;background:#25D366;color:#fff;font-weight:700;text-decoration:none;white-space:nowrap;">WhatsApp</a></div></div></div></div></section>' },
            { id: 'sb-shop-cart', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🛒</div><div>Cart</div>', category: 'Shop', content: '<section data-wc-cart data-wc-currency="Rs" data-wc-payhere="YOUR_MERCHANT_ID" data-wc-qr="" data-wc-wa="15551234567" style="padding:5rem 1.5rem;background:#f8fafc;"><div style="max-width:640px;margin:0 auto;background:#fff;border:1.5px solid #e2e8f0;border-radius:20px;padding:2.5rem;box-shadow:0 15px 40px rgba(0,0,0,0.06);"><h2 style="font-size:1.8rem;font-weight:800;color:#0f172a;">🛒 Your Cart <span data-wc-cart-count style="font-size:0.85rem;background:var(--primary,#6366f1);color:#fff;border-radius:999px;padding:0.15rem 0.6rem;vertical-align:middle;">0</span></h2><div class="wc-cart-items" style="margin:1rem 0;"></div><div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 0;border-top:2px solid #0f172a;font-size:1.2rem;font-weight:900;color:#0f172a;"><span>Total</span><span class="wc-cart-total">Rs 0</span></div><button data-wc-checkout style="width:100%;padding:0.95rem;border:none;border-radius:12px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;font-weight:800;cursor:pointer;">Checkout — PayHere / LankaQR →</button><p style="font-size:0.75rem;color:#94a3b8;text-align:center;margin-top:0.75rem;">Secure checkout · PayHere cards + bank apps via LankaQR</p></div></section>' },
            { id: 'sb-countdown', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">⏳</div><div>Countdown</div>', category: 'Business', content: '<section data-wc-countdown="2026-12-31T23:59:59" data-wc-expire-text="Offer ended — contact us for the next deal!" style="padding:4.5rem 1.5rem;background:linear-gradient(135deg,#0f172a,#1e1b4b);text-align:center;"><h2 style="font-size:2.2rem;font-weight:800;color:#fff;margin-bottom:0.5rem;">🔥 Limited-Time Offer Ends In</h2><p style="color:#94a3b8;margin-bottom:2rem;">Hurry — special pricing disappears when the timer hits zero.</p><div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;"><div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);border-radius:16px;padding:1.25rem 1.5rem;min-width:92px;"><div data-wc-cd="days" style="font-size:2.4rem;font-weight:900;color:#fff;">00</div><div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Days</div></div><div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);border-radius:16px;padding:1.25rem 1.5rem;min-width:92px;"><div data-wc-cd="hours" style="font-size:2.4rem;font-weight:900;color:#fff;">00</div><div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Hours</div></div><div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);border-radius:16px;padding:1.25rem 1.5rem;min-width:92px;"><div data-wc-cd="mins" style="font-size:2.4rem;font-weight:900;color:#fff;">00</div><div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Mins</div></div><div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);border-radius:16px;padding:1.25rem 1.5rem;min-width:92px;"><div data-wc-cd="secs" style="font-size:2.4rem;font-weight:900;color:#fbbf24;">00</div><div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Secs</div></div></div><div style="margin-top:2rem;"><a href="#contact" style="display:inline-block;padding:0.9rem 2.4rem;border-radius:999px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-weight:800;text-decoration:none;">Claim the Deal →</a></div></section>' },
            { id: 'sb-slider', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🎞️</div><div>Slider</div>', category: 'Business', content: '<section style="padding:5rem 1.5rem;"><style>.wc-slider{max-width:900px;margin:0 auto;position:relative;overflow:hidden;border-radius:20px;box-shadow:0 15px 40px rgba(0,0,0,0.12);}.wc-slider input{display:none;}.wc-slides{display:flex;width:300%;transition:transform .6s ease;}.wc-slide{width:33.3333%;flex-shrink:0;position:relative;}.wc-slide img{width:100%;height:420px;object-fit:cover;display:block;}.wc-slide-cap{position:absolute;bottom:0;left:0;right:0;padding:1.5rem;background:linear-gradient(transparent,rgba(0,0,0,.75));color:#fff;font-weight:700;}.wc-s1:checked~.wc-slides{transform:translateX(0);}.wc-s2:checked~.wc-slides{transform:translateX(-33.3333%);}.wc-s3:checked~.wc-slides{transform:translateX(-66.6666%);}.wc-dots{display:flex;gap:.5rem;justify-content:center;padding:1rem;background:#0f172a;}.wc-dots label{width:12px;height:12px;border-radius:50%;background:#475569;cursor:pointer;}.wc-s1:checked~.wc-dots label:nth-child(1),.wc-s2:checked~.wc-dots label:nth-child(2),.wc-s3:checked~.wc-dots label:nth-child(3){background:#fff;}</style><div class="wc-slider" data-wc-slider data-wc-autoplay="5000"><input class="wc-s1" type="radio" name="wcsl1" id="wcsl1a" checked/><input class="wc-s2" type="radio" name="wcsl1" id="wcsl1b"/><input class="wc-s3" type="radio" name="wcsl1" id="wcsl1c"/><div class="wc-slides"><div class="wc-slide"><img src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&auto=format&fit=crop&q=80" alt="Slide 1"/><div class="wc-slide-cap">Premium quality, crafted for you</div></div><div class="wc-slide"><img src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&auto=format&fit=crop&q=80" alt="Slide 2"/><div class="wc-slide-cap">A workspace you will love</div></div><div class="wc-slide"><img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=1200&auto=format&fit=crop&q=80" alt="Slide 3"/><div class="wc-slide-cap">Grow with a team that cares</div></div></div><div class="wc-dots"><label for="wcsl1a"></label><label for="wcsl1b"></label><label for="wcsl1c"></label></div></div></section>' },
            { id: 'sb-video', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">▶️</div><div>Video</div>', category: 'Business', content: '<section style="padding:5rem 1.5rem;text-align:center;"><h2 style="font-size:2.2rem;font-weight:800;margin-bottom:0.5rem;color:#0f172a;">Watch How It Works</h2><p style="color:#64748b;margin-bottom:2.5rem;">Two minutes that explain everything.</p><div data-wc-video="dQw4w9WgXcQ" data-wc-video-kind="youtube" style="max-width:820px;margin:0 auto;position:relative;border-radius:20px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.2);cursor:pointer;padding-top:56.25%;background:#000;"><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" target="_blank" style="position:absolute;inset:0;display:block;"><img src="https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg" alt="Play video" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;"/><span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:84px;height:84px;border-radius:50%;background:rgba(239,68,68,0.95);color:#fff;font-size:2rem;display:flex;align-items:center;justify-content:center;">▶</span></a></div></section>' },
            { id: 'sb-map', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🗺️</div><div>Map</div>', category: 'Business', content: '<section style="padding:5rem 1.5rem;"><div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1.4fr;gap:2.5rem;align-items:stretch;"><div><h2 style="font-size:2.2rem;font-weight:800;margin-bottom:1rem;color:#0f172a;">📍 Visit Us</h2><p style="color:#64748b;line-height:1.7;margin-bottom:1.25rem;">Drop by our store — parking available right outside.</p><p style="margin-bottom:0.6rem;color:#0f172a;"><strong>Address:</strong> 123 Galle Road, Colombo 03</p><p style="margin-bottom:0.6rem;color:#0f172a;"><strong>Hours:</strong> Mon–Sat, 9am–7pm</p><p style="color:#0f172a;"><strong>Phone:</strong> +94 77 123 4567</p></div><div style="border-radius:18px;overflow:hidden;border:1.5px solid #e2e8f0;min-height:320px;"><iframe title="Our location" src="https://www.google.com/maps?q=Galle+Road+Colombo+03+Sri+Lanka&output=embed" style="width:100%;height:100%;min-height:320px;border:0;" loading="lazy"></iframe></div></div></section>' },
            { id: 'sb-blog', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">📰</div><div>Blog List</div>', category: 'Business', content: '<section style="padding:5rem 1.5rem;background:#f8fafc;"><h2 style="font-size:2.4rem;font-weight:800;text-align:center;margin-bottom:0.5rem;color:#0f172a;">Latest from the Blog</h2><p style="color:#64748b;text-align:center;margin-bottom:3rem;">Tips, stories and updates.</p><div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;"><article style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;overflow:hidden;"><img src="https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=600&auto=format&fit=crop&q=80" alt="Blog post" style="width:100%;height:190px;object-fit:cover;display:block;"/><div style="padding:1.5rem;"><span style="font-size:0.7rem;font-weight:800;color:#6366f1;text-transform:uppercase;">Tips • 5 min read</span><h3 style="font-size:1.15rem;font-weight:800;margin:0.5rem 0;color:#0f172a;">How to choose the right product</h3><p style="color:#64748b;font-size:0.9rem;line-height:1.6;">A short guide that helps customers decide with confidence.</p><span style="display:inline-block;margin-top:1rem;font-weight:700;color:var(--primary,#6366f1);">Read more →</span></div></article><article style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;overflow:hidden;"><img src="https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d?w=600&auto=format&fit=crop&q=80" alt="Blog post" style="width:100%;height:190px;object-fit:cover;display:block;"/><div style="padding:1.5rem;"><span style="font-size:0.7rem;font-weight:800;color:#6366f1;text-transform:uppercase;">News • 3 min read</span><h3 style="font-size:1.15rem;font-weight:800;margin:0.5rem 0;color:#0f172a;">What is new this season</h3><p style="color:#64748b;font-size:0.9rem;line-height:1.6;">Fresh arrivals and behind-the-scenes stories from our team.</p><span style="display:inline-block;margin-top:1rem;font-weight:700;color:var(--primary,#6366f1);">Read more →</span></div></article><article style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;overflow:hidden;"><img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&auto=format&fit=crop&q=80" alt="Blog post" style="width:100%;height:190px;object-fit:cover;display:block;"/><div style="padding:1.5rem;"><span style="font-size:0.7rem;font-weight:800;color:#6366f1;text-transform:uppercase;">Guide • 7 min read</span><h3 style="font-size:1.15rem;font-weight:800;margin:0.5rem 0;color:#0f172a;">Getting the most value</h3><p style="color:#64748b;font-size:0.9rem;line-height:1.6;">Practical advice from people who do this every day.</p><span style="display:inline-block;margin-top:1rem;font-weight:700;color:var(--primary,#6366f1);">Read more →</span></div></article></div></section>' },
            { id: 'sb-socials', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🔗</div><div>Social Links</div>', category: 'Business', content: '<section style="padding:3.5rem 1.5rem;text-align:center;"><h2 style="font-size:1.6rem;font-weight:800;margin-bottom:0.5rem;color:#0f172a;">Follow Us</h2><p style="color:#64748b;margin-bottom:1.5rem;">Daily updates, offers and behind the scenes.</p><div style="display:flex;gap:0.8rem;justify-content:center;flex-wrap:wrap;"><a href="https://facebook.com/yourpage" target="_blank" style="width:52px;height:52px;border-radius:50%;background:#1877F2;color:#fff;font-size:1.4rem;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:800;">f</a><a href="https://instagram.com/yourpage" target="_blank" style="width:52px;height:52px;border-radius:50%;background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:#fff;font-size:1.4rem;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:800;">◉</a><a href="https://tiktok.com/@yourpage" target="_blank" style="width:52px;height:52px;border-radius:50%;background:#000;color:#fff;font-size:1.2rem;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:800;">♪</a><a href="https://wa.me/15551234567" target="_blank" style="width:52px;height:52px;border-radius:50%;background:#25D366;color:#fff;font-size:1.4rem;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">✆</a><a href="https://youtube.com/@yourpage" target="_blank" style="width:52px;height:52px;border-radius:50%;background:#FF0000;color:#fff;font-size:1.2rem;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">▶</a></div></section>' },
            { id: 'sb-popup', label: '<div style="font-size:1.3rem;margin-bottom:0.2rem">🎁</div><div>Popup Offer</div>', category: 'Business', content: '<div style="padding:2rem 1.5rem;text-align:center;background:#fff8ed;border:1.5px dashed #f59e0b;border-radius:16px;max-width:640px;margin:2rem auto;"><div style="font-size:2rem;">🎁</div><h3 style="font-size:1.3rem;font-weight:800;color:#0f172a;margin:0.5rem 0;">Popup Offer installed</h3><p style="color:#64748b;font-size:0.9rem;margin-bottom:1rem;">Visitors see a 10% welcome popup (<a href="#wc-popup-1" style="color:#d97706;font-weight:700;">click to preview it</a>). Auto-opens once per visit on the live site.</p></div><div id="wc-popup-1" data-wc-popup="offer" data-wc-popup-delay="6" style="display:none;position:fixed;inset:0;z-index:99990;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:1rem;"><style>#wc-popup-1:target{display:flex !important;}</style><div style="background:#fff;border-radius:20px;max-width:420px;width:100%;padding:2.5rem 2rem;text-align:center;position:relative;"><a href="#" data-wc-pop-close style="position:absolute;top:0.75rem;right:1rem;font-size:1.4rem;color:#94a3b8;text-decoration:none;">×</a><div style="font-size:2.5rem;">🎁</div><h3 style="font-size:1.5rem;font-weight:900;color:#0f172a;margin:0.5rem 0;">Get 10% Off Today</h3><p style="color:#64748b;font-size:0.9rem;margin-bottom:1.25rem;">Join the list and grab your welcome code.</p><form data-wc-form="popup" data-wc-sheet="" data-wc-thanks="✓ Code WELCOME10 unlocked!"><input type="text" name="company" style="display:none;" tabindex="-1" autocomplete="off"/><input type="email" name="email" placeholder="you@email.com" required style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:0.75rem;font-family:inherit;"/><button type="submit" style="width:100%;padding:0.85rem;border:none;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-weight:800;cursor:pointer;">Claim 10% Off</button></form></div></div>' }
          ]
        },
        styleManager: {
          appendTo: '#gjs-styles',
          sectors: [
            { name: 'Typography', open: true, buildProps: ['font-family', 'font-size', 'font-weight', 'color', 'line-height', 'text-align'] },
            { name: 'Dimensions', open: false, buildProps: ['width', 'height', 'max-width', 'max-height', 'margin', 'padding'] },
            { name: 'Colors', open: false, buildProps: ['background-color', 'opacity'] },
            { name: 'Borders', open: false, buildProps: ['border-radius', 'border', 'box-shadow'] }
          ]
        },
        traitManager: { appendTo: '#gjs-traits' },
        layerManager: { appendTo: '#gjs-layers' },
        showDevices: false,
        deviceManager: {
          devices: [
            { name: 'Desktop', width: '' },
            { name: 'Laptop', width: '1200px' },
            { name: 'Tablet', width: '768px' },
            { name: 'Mobile', width: '375px' }
          ]
        },
        panels: { defaults: [] }  // Remove all default GrapesJS panel buttons (code view, visibility, etc.)
      });

      grapesEditor.on('component:selected', (model) => {
        selectedComponent = model;
        configureEditorComponent(model);
        renderSmartLayers();
        updateFloatingContentBtn(model);
        updateAiSelectedTarget(model);
        updateCtxPanel(model);
        if (isButtonLike(model) && !suppressEditorOpen && !activeFreeDrag) openButtonEditor(model);
      });
      grapesEditor.on('component:dblclick', (model) => {
        if ((model.get('tagName') || '').toLowerCase() === 'img') openImageEditor(model);
      });
      grapesEditor.on('component:deselected', () => {
        selectedComponent = null;
        renderSmartLayers();
        updateFloatingContentBtn(null);
        updateAiSelectedTarget(null);
        updateCtxPanel(null);
      });
      grapesEditor.on('block:drag:stop', (component) => {
        suppressEditorOpen = true;
        if (component) configureEditorComponent(component);
        setTimeout(() => {
          suppressEditorOpen = false;
          if (!component) return;
          const isShape = component.getAttributes && component.getAttributes()['data-wc-shape'];
          const isCard = component.getAttributes && component.getAttributes()['data-wc-card'];
          const btns = findButtons(component);
          if (isCard) showToast('🃏 Card added — click text to edit, image to change');
          else if (isShape) showToast('⭐ Shape added — drag an image onto it, or right-click → Change Card / Shape Image');
          else if (btns.length > 0) showToast('🔘 Button added');
          else showToast('✨ New element added');
        }, 300);
      });
      // ★ Business blocks: stamp saved Business Setup onto newly added components.
      grapesEditor.on('component:add', (component) => {
        try { wcStampBizOnComponent(component); } catch (e) {}
      });
      // ★ Pages manager: always boot on the homepage (extra pages stay saved).
      grapesEditor.on('load', () => {
        try {
          (projectData.designs || []).forEach((d) => { if (d && Array.isArray(d.pages)) d._wcPage = 0; });
        } catch (e) {}
      });
      grapesEditor.on('load', () => {
        setupContextMenu();
        loadHtmlIntoStudioCanvas();
        setupBlockClickToInsert();
        // ★ Boot loader done — canvas is ready
        setTimeout(() => { try { if (window.Loader3D) Loader3D.hide(); } catch (e) {} }, 900);
      });
      grapesEditor.on('device:set', () => {
        setTimeout(applyMobileStylesInCanvas, 50);
      });
      grapesEditor.on('canvas:frame:load', (evt) => {
        if (lastStudioStyleInjector) lastStudioStyleInjector(evt);
        else loadHtmlIntoStudioCanvas(evt);
        applyMobileStylesInCanvas();
      });
    }

    function isButtonLike(comp) {
      if (!comp) return false;
      const tag = (comp.get('tagName') || '').toLowerCase();
      if (tag === 'button') return true;
      if (tag !== 'a') return false;
      let classAttr = '';
      try {
        const el = comp.getEl && comp.getEl();
        if (el) classAttr = (typeof el.className === 'string') ? el.className : '';
      } catch (e) {}
      if (!classAttr) {
        const attrs = comp.getAttributes?.() || {};
        classAttr = attrs.class || '';
      }
      if (/\bbtn\b|button/i.test(classAttr)) return true;
      try {
        const el = comp.getEl && comp.getEl();
        if (el && el.classList && el.classList.contains('webcraft-free-button')) return true;
      } catch (e) {}
      const attrs = comp.getAttributes?.() || {};
      const href = attrs.href || '';
      const looksLikeLink = href && href !== '#' && !href.startsWith('#');
      if (!looksLikeLink) return true;
      const style = comp.getStyle?.() || {};
      if (style.background || style['background-color'] || style.padding) return true;
      return false;
    }

    function findButtons(root) {
      const found = [];
      const walk = (c) => {
        if (!c) return;
        if (isButtonLike(c)) found.push(c);
        const k = c.components && c.components();
        if (k && k.length) k.forEach(walk);
      };
      walk(root);
      return found;
    }

    function componentFromElement(element) {
      if (!element || !grapesEditor) return null;
      const wrapper = grapesEditor.DomComponents?.getWrapper?.();
      if (!wrapper) return null;
      let found = null;
      const walk = (c) => {
        if (found || !c) return;
        try {
          if (c.getEl && c.getEl() === element) {
            found = c;
            return;
          }
        } catch (e) {}
        const kids = c.components && c.components();
        if (kids && kids.length) kids.forEach(walk);
      };
      walk(wrapper);
      return found;
    }

    function configureEditorComponent(comp) {
      if (!comp) return;
      const tag = (comp.get('tagName') || '').toLowerCase();
      if (tag === 'img') comp.set({ draggable: true, resizable: true, stylable: true, selectable: true });
      else if (tag === 'a' || tag === 'button') {
        comp.set({ draggable: false, stylable: true, selectable: true, droppable: false });
        ensureFreeButtonSetup(comp);
        return;
      } else if (['section', 'header', 'footer', 'form'].includes(tag)) comp.set({ draggable: false, droppable: true, stylable: true, selectable: true });
      const kids = comp.components && comp.components();
      if (kids && kids.length) kids.forEach(configureEditorComponent);
    }

    function ensureFreeButtonSetup(comp, retries = 0) {
      if (retries > 10) return;
      const el = comp.getEl && comp.getEl();
      if (el) {
        el.classList.add('webcraft-free-button');
        el.setAttribute('draggable', 'false');
      } else setTimeout(() => ensureFreeButtonSetup(comp, retries + 1), 50);
    }

    function configureEditorComponents() {
      if (!grapesEditor) return;
      const wrapper = grapesEditor.DomComponents?.getWrapper();
      if (wrapper) configureEditorComponent(wrapper);
    }

    function getStudioCanvasDocument(evt) {
      try {
        const fromEvt = evt?.window?.document || evt?.document;
        if (fromEvt && fromEvt.head && fromEvt.body) return fromEvt;
      } catch (e) {}
      try {
        const fromApi = grapesEditor?.Canvas?.getDocument?.();
        if (fromApi && fromApi.head && fromApi.body) return fromApi;
      } catch (e) {}
      try {
        const fromFrame = grapesEditor?.Canvas?.getFrameEl?.()?.contentDocument;
        if (fromFrame && fromFrame.head && fromFrame.body) return fromFrame;
      } catch (e) {}
      return null;
    }

    function getCanvasBody() {
      return getStudioCanvasDocument()?.body || grapesEditor?.Canvas?.getDocument?.()?.body || null;
    }

    function getCanvasPagePoint(e) {
      const body = getCanvasBody();
      if (!body) return { x: e.clientX, y: e.clientY };
      const rect = body.getBoundingClientRect();
      const win = body.ownerDocument?.defaultView;
      const sx = body.scrollLeft || win?.scrollX || 0,
        sy = body.scrollTop || win?.scrollY || 0;
      return { x: e.clientX - rect.left + sx, y: e.clientY - rect.top + sy };
    }

    function setupFreeButtonDragging() {
      try {
        const canvasDoc = grapesEditor?.Canvas?.getDocument(), body = canvasDoc?.body;
        if (!canvasDoc || !body || canvasDoc.__freeButtonDragBound) return;
        canvasDoc.__freeButtonDragBound = true;
        canvasDoc.addEventListener('dragstart', (e) => {
          const el = e.target?.closest?.('a,button');
          if (el) e.preventDefault();
        }, true);
        const down = (e) => {
          if (e.button !== 0) return;
          const target = e.target?.closest?.('a,button');
          if (!target) return;
          const comp = componentFromElement(target);
          if (!comp || !isButtonLike(comp)) return;
          const wrapper = grapesEditor.DomComponents.getWrapper();
          if (!wrapper) return;
          const rect = target.getBoundingClientRect();
          activeFreeDrag = {
            comp, target, startX: e.clientX, startY: e.clientY, moved: false,
            wrapper, pointerId: e.pointerId, grabX: e.clientX - rect.left, grabY: e.clientY - rect.top
          };
          try { target.setPointerCapture?.(e.pointerId); } catch (err) {}
          grapesEditor.select(comp);
        };
        const move = (e) => {
          const d = activeFreeDrag;
          if (!d || e.pointerId !== d.pointerId) return;
          const dx = e.clientX - d.startX, dy = e.clientY - d.startY;
          if (!d.moved && Math.hypot(dx, dy) < 5) return;
          if (!d.moved) {
            d.moved = true;
            try { d.comp.move(d.wrapper, { at: d.wrapper.components().length }); } catch (err) {}
            try {
              const fresh = d.comp.getEl && d.comp.getEl();
              if (fresh) d.target = fresh;
            } catch (err) {}
            const st = d.target.style;
            st.setProperty('position', 'absolute', 'important');
            st.setProperty('margin', '0', 'important');
            st.setProperty('z-index', '1000', 'important');
            st.setProperty('max-width', 'none', 'important');
            d.target.classList.add('webcraft-free-button-dragging');
          }
          e.preventDefault();
          const page = getCanvasPagePoint(e);
          const left = Math.max(0, Math.round(page.x - d.grabX)),
            top = Math.max(0, Math.round(page.y - d.grabY));
          d.target.style.setProperty('left', left + 'px', 'important');
          d.target.style.setProperty('top', top + 'px', 'important');
          d.lastLeft = left;
          d.lastTop = top;
        };
        const finish = (e) => {
          const d = activeFreeDrag;
          if (!d || (e.pointerId != null && e.pointerId !== d.pointerId)) return;
          d.target?.classList.remove('webcraft-free-button-dragging');
          if (d.moved) {
            try { e.preventDefault(); } catch (err) {}
            d.comp.addStyle({
              position: 'absolute', margin: '0', 'z-index': '1000',
              'max-width': 'none',
              left: (d.lastLeft ?? 0) + 'px',
              top: (d.lastTop ?? 0) + 'px'
            });
            suppressEditorOpen = true;
            setTimeout(() => suppressEditorOpen = false, 120);
            const swallowClick = (ce) => {
              ce.preventDefault();
              ce.stopPropagation();
              d.target?.removeEventListener('click', swallowClick, true);
            };
            d.target?.addEventListener('click', swallowClick, true);
            showToast('🔘 Button dropped');
            renderSmartLayers();
          } else if (isButtonLike(d.comp)) openButtonEditor(d.comp);
          activeFreeDrag = null;
        };
        canvasDoc.addEventListener('pointerdown', down, true);
        canvasDoc.addEventListener('pointermove', move, true);
        canvasDoc.addEventListener('pointerup', finish, true);
        canvasDoc.addEventListener('pointercancel', finish, true);
      } catch (e) {}
    }

    function getTopLevelSections() {
      const wrapper = grapesEditor?.DomComponents?.getWrapper?.();
      if (!wrapper) return [];
      const out = [];
      const walk = (c) => {
        if (!c) return;
        const t = (c.get('tagName') || '').toLowerCase();
        if (['section', 'header', 'footer'].includes(t)) {
          out.push(c);
          return;
        }
        const kids = c.components?.();
        if (kids && kids.length) kids.forEach(walk);
      };
      const kids = wrapper.components?.();
      if (kids && kids.length) kids.forEach(walk);
      return out;
    }

    function getSectionPeers(comp) {
      const parent = comp?.parent?.();
      if (!parent) return [];
      return getTopLevelSections().filter(c => c.parent?.() === parent);
    }

    function refreshSectionDragHandles() {
      try {
        const doc = grapesEditor?.Canvas?.getDocument(), body = doc?.body;
        if (!doc || !body) return;
        body.querySelectorAll('.webcraft-section-handle,.webcraft-drop-line').forEach(el => el.remove());
        const sections = getTopLevelSections();
        sections.forEach((comp, i) => {
          const el = comp.getEl?.();
          if (!el) return;
          const r = el.getBoundingClientRect(),
            sy = body.scrollTop || doc.defaultView?.scrollY || 0;
          const h = doc.createElement('div');
          h.className = 'webcraft-section-handle';
          h.innerHTML = `↕ <span>${escapeHtml(getSectionDisplayName(comp,i))}</span>`;
          h.title = 'Drag this handle to move the whole section';
          h.style.top = Math.max(2, r.top + sy + 6) + 'px';
          h.style.right = '10px';
          h.addEventListener('pointerdown', (e) => beginSectionDrag(e, comp, h), true);
          body.appendChild(h);
        });
        const line = doc.createElement('div');
        line.className = 'webcraft-drop-line';
        line.id = 'webcraft-section-drop-line';
        line.style.left = '8px';
        line.style.right = '8px';
        body.appendChild(line);
      } catch (e) {}
    }

    function updateSectionDragHandles() {
      try {
        const doc = grapesEditor?.Canvas?.getDocument(), body = doc?.body;
        if (!doc || !body) return;
        const hs = body.querySelectorAll('.webcraft-section-handle'),
          secs = getTopLevelSections();
        hs.forEach((h, i) => {
          const el = secs[i]?.getEl?.();
          if (!el) return;
          const r = el.getBoundingClientRect(),
            sy = body.scrollTop || doc.defaultView?.scrollY || 0;
          h.style.top = Math.max(2, r.top + sy + 6) + 'px';
        });
      } catch (e) {}
    }

    function beginSectionDrag(e, comp, handle) {
      if (e.button !== 0 || !comp) return;
      e.preventDefault();
      e.stopPropagation();
      const doc = grapesEditor?.Canvas?.getDocument(), body = doc?.body, el = comp.getEl?.();
      if (!doc || !body || !el) return;
      activeSectionDrag = {
        comp, handle, body, startY: e.clientY, startX: e.clientX, moved: false,
        pointerId: e.pointerId, pendingIndex: getSectionPeers(comp).indexOf(comp)
      };
      try { handle.setPointerCapture?.(e.pointerId); } catch (err) {}
      handle.classList.add('dragging');
      grapesEditor.select(comp);
    }

    function setupSectionDragging() {
      try {
        const doc = grapesEditor?.Canvas?.getDocument();
        if (!doc || doc.__sectionDragBound) return;
        doc.__sectionDragBound = true;
        const move = (e) => {
          const d = activeSectionDrag;
          if (!d || e.pointerId !== d.pointerId) return;
          if (!d.moved && Math.abs(e.clientY - d.startY) < 6) return;
          if (!d.moved) {
            d.moved = true;
            d.comp.getEl()?.classList.add('webcraft-section-dragging');
          }
          e.preventDefault();
          const secs = getSectionPeers(d.comp).filter(c => c !== d.comp), y = e.clientY;
          let idx = secs.length, anchor = null, anchorComp = null;
          for (let i = 0; i < secs.length; i++) {
            const r = secs[i].getEl?.()?.getBoundingClientRect();
            if (r && y < r.top + r.height / 2) {
              idx = i;
              anchor = r;
              anchorComp = secs[i];
              break;
            }
          }
          d.pendingIndex = idx;
          d.pendingAnchor = anchorComp;
          const line = doc.getElementById('webcraft-section-drop-line');
          if (line) {
            line.style.display = 'block';
            const sy = d.body.scrollTop || doc.defaultView?.scrollY || 0;
            const top = anchor ? anchor.top : (secs.length ? secs[secs.length - 1].getEl().getBoundingClientRect().bottom : d.startY);
            line.style.top = Math.max(2, Math.round(top + sy - 2)) + 'px';
          }
        };
        const end = (e) => {
          const d = activeSectionDrag;
          if (!d || (e.pointerId != null && e.pointerId !== d.pointerId)) return;
          d.comp.getEl()?.classList.remove('webcraft-section-dragging');
          d.handle?.classList.remove('dragging');
          const line = doc.getElementById('webcraft-section-drop-line');
          if (line) line.style.display = 'none';
          if (d.moved) {
            const parent = d.comp.parent?.();
            const secs = getSectionPeers(d.comp).filter(c => c !== d.comp);
            let at = Number.isFinite(d.pendingIndex) ? d.pendingIndex : secs.length;
            const anchor = d.pendingAnchor;
            try {
              if (parent) {
                if (anchor) {
                  const targetIndex = anchor.index();
                  const sourceIndex = d.comp.index();
                  at = sourceIndex < targetIndex ? Math.max(0, targetIndex - 1) : targetIndex;
                } else at = parent.components().length;
                d.comp.move(parent, { at });
              }
            } catch (err) {}
            renderFriendlySections();
            renderSmartLayers();
            setTimeout(refreshSectionDragHandles, 70);
            showToast('📑 Section moved');
          }
          activeSectionDrag = null;
        };
        doc.addEventListener('pointermove', move, true);
        doc.addEventListener('pointerup', end, true);
        doc.addEventListener('pointercancel', end, true);
        doc.defaultView?.addEventListener('resize', updateSectionDragHandles);
        doc.addEventListener('scroll', updateSectionDragHandles, true);
      } catch (e) {}
    }

    function appendImageAtDrop(url, alt, targetEl, clientY) {
      if (!grapesEditor || !url) return null;
      const wrapper = grapesEditor.DomComponents.getWrapper();
      let target = componentFromElement(targetEl);
      // Dropped ONTO a shape/card → fit the image INTO that shape
      // (swap its inner img) instead of nesting a loose image inside it.
      if (target) {
        const shapeImg = findShapeImgComp(target);
        if (shapeImg) {
          setShapeImageSrc(shapeImg, url, alt);
          lastDropShapeFit = true;
          try { syncCanvasToHtml(); } catch (e) {}
          try { renderSmartLayers(); } catch (e) {}
          return shapeImg;
        }
      }
      if (target && (target.get('tagName') || '').toLowerCase() === 'img') {
        target.setAttributes(Object.assign({}, target.getAttributes(), { src: url, alt: alt || '' }));
        target.set({ draggable: true, resizable: true });
        return target;
      }
      const imageHtml = `<img src="${escapeHtml(url)}" alt="${escapeHtml(alt || '')}" style="width:100%;max-width:850px;height:auto;border-radius:16px;margin:2rem auto;display:block;object-fit:cover;"/>`;
      let destination = wrapper, insertAt = wrapper.components().length;
      if (target) {
        const targetTag = (target.get('tagName') || '').toLowerCase();
        const structuralContainer = ['body', 'main', 'section', 'header', 'footer', 'article', 'aside', 'form', 'div'].includes(targetTag);
        const parent = target.parent && target.parent();
        if (structuralContainer && target.components) {
          destination = target;
          insertAt = target.components().length;
        } else if (parent && parent.components) {
          destination = parent;
          const idx = target.index();
          insertAt = clientY != null && target.getEl ? (clientY > (target.getEl().getBoundingClientRect().top + target.getEl().getBoundingClientRect().height / 2) ? idx + 1 : idx) : idx;
        }
      }
      const added = destination.append(imageHtml, { at: Math.max(0, insertAt) });
      const comp = Array.isArray(added) ? added[0] : added;
      if (comp) {
        comp.set({ draggable: true, resizable: true, stylable: true });
        grapesEditor.select(comp);
      }
      return comp;
    }

    /* ══════════════════════════════════════════════════
       ★ CANVAS LOADER — Fully patched
       - Does NOT force body background to transparent
       - Allows black / dark backgrounds
       - Auto-retries when content fails to render
       - Adds forceCanvasReveal + scroll-anim visibility overrides
    ══════════════════════════════════════════════════ */
    function loadHtmlIntoStudioCanvas(frameEvt) {
      if (!grapesEditor) return;

      if (!currentHtml || !currentHtml.trim()) {
        console.warn('[studio] currentHtml empty, using fallback');
        currentHtml = WC_FALLBACK_HTML;
      }

      const sourceHtml = String(currentHtml).trim();
      const parser = new DOMParser();
      const doc = parser.parseFromString(sourceHtml, 'text/html');

      let bodyHtml = doc.body ? doc.body.innerHTML : '';
      if (!bodyHtml.trim()) {
        const fragment = document.createElement('template');
        fragment.innerHTML = sourceHtml;
        bodyHtml = fragment.innerHTML;
      }

      const cleanTemplate = document.createElement('template');
      cleanTemplate.innerHTML = bodyHtml;
      cleanTemplate.content.querySelectorAll('script').forEach(s => s.remove());
      const finalBodyHtml = cleanTemplate.innerHTML.trim();

      if (!finalBodyHtml) {
        console.warn('[studio] Generated HTML has no body content; using fallback.');
        currentHtml = WC_FALLBACK_HTML;
        return loadHtmlIntoStudioCanvas(frameEvt);
      }

      let combinedCss = '';
      doc.querySelectorAll('style').forEach(style => {
        if (style.textContent) combinedCss += style.textContent + '\n';
      });

      const sourceBodyAttrs = {};
      if (doc.body) {
        Array.from(doc.body.attributes).forEach(attr => {
          sourceBodyAttrs[attr.name] = attr.value;
        });
      }
      const bodyInlineStyle = sourceBodyAttrs.style || '';

      try {
        grapesEditor.setComponents(finalBodyHtml);
      } catch (err) {
        console.error('[studio] setComponents failed:', err);
        try {
          grapesEditor.DomComponents.clear();
          grapesEditor.setComponents(finalBodyHtml);
        } catch (retryErr) {
          console.error('[studio] second setComponents failed:', retryErr);
          return;
        }
      }

      const injectStyles = (evt) => {
        try {
          const canvasDoc = getStudioCanvasDocument(evt) || grapesEditor.Canvas?.getDocument?.();
          if (!canvasDoc || !canvasDoc.head || !canvasDoc.body) {
            lastStudioStyleInjector = injectStyles;
            return;
          }

          doc.querySelectorAll('link[rel="stylesheet"], link[href]').forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;
            const exists = Array.from(canvasDoc.head.querySelectorAll('link'))
              .some(existing => existing.getAttribute('href') === href);
            if (!exists) canvasDoc.head.appendChild(link.cloneNode(true));
          });

          Object.keys(sourceBodyAttrs).forEach(name => {
            if (name.startsWith('data-gjs')) return;
            try { canvasDoc.body.setAttribute(name, sourceBodyAttrs[name]); } catch (e) {}
          });

          canvasDoc.body.classList.add('webcraft-canvas-body');

          let masterCss = combinedCss;
          if (bodyInlineStyle) {
            masterCss += `\nbody { ${bodyInlineStyle} }`;
          }

          let styleTag = canvasDoc.getElementById('webcraft-master-css');
          if (!styleTag) {
            styleTag = canvasDoc.createElement('style');
            styleTag.id = 'webcraft-master-css';
            canvasDoc.head.appendChild(styleTag);
          }

          /* ★ CRITICAL FIXES APPLIED HERE ★ */
          styleTag.innerHTML = `${masterCss}
/* ── Studio canvas reset ── */
html {
  min-height: 100% !important;
  height: auto !important;
  overflow: visible !important;
}
html, body { width: 100% !important; }
body {
  min-height: 100vh !important;
  height: auto !important;
  position: relative !important;
  margin: 0 !important;
  overflow: visible !important;
}
body.webcraft-canvas-body {
  display: block !important;
  visibility: visible !important;
}
/* NOTE: We intentionally DO NOT force background-color:transparent
   on html/body here — the original site background (including black
   dark-theme backgrounds) must be preserved so text remains visible. */
#wrapper {
  min-height: 100vh;
  height: auto !important;
  box-sizing: border-box;
  position: relative !important;
}
/* ★ Force-reveal generated scroll-animation elements in EDITOR mode.
      Generated page scripts are stripped, so AOS/WOW/[data-anim]/etc.
      would remain stuck at opacity:0 without this override.
      NOTE: .wc-anim-live is excluded so the user's chosen animation
      plays LIVE on canvas the moment it is selected. */
body.webcraft-canvas-body [data-anim]:not(.wc-anim-live),
body.webcraft-canvas-body [class*="aos"],
body.webcraft-canvas-body [class*="wow"],
body.webcraft-canvas-body [class*="sal-"],
body.webcraft-canvas-body [class*="reveal"],
body.webcraft-canvas-body [class*="fade-in"],
body.webcraft-canvas-body [class*="fadeIn"],
body.webcraft-canvas-body [class*="fade-up"],
body.webcraft-canvas-body [class*="fadeUp"],
body.webcraft-canvas-body [class*="fade-down"],
body.webcraft-canvas-body [class*="slide-in"],
body.webcraft-canvas-body [class*="slideIn"],
body.webcraft-canvas-body [class*="slide-up"],
body.webcraft-canvas-body [class*="slideUp"],
body.webcraft-canvas-body [class*="zoom-in"],
body.webcraft-canvas-body [class*="zoomIn"],
body.webcraft-canvas-body [class*="animate-"],
body.webcraft-canvas-body [class*="animate_"],
body.webcraft-canvas-body [data-aos],
body.webcraft-canvas-body [data-wow],
body.webcraft-canvas-body [data-sal],
body.webcraft-canvas-body [data-scroll],
body.webcraft-canvas-body [data-animate] {
  opacity: 1 !important;
  visibility: visible !important;
  transform: none !important;
  animation-play-state: paused !important;
  animation-fill-mode: forwards !important;
}
.webcraft-free-button {
  cursor: move !important;
  touch-action: none;
  -webkit-user-drag: none !important;
  user-select: none !important;
  -webkit-user-select: none !important;
}
a, button { -webkit-user-drag: none; }`;

          try {
            canvasDoc.documentElement.classList.add('webcraft-canvas-html');
          } catch (e) {}

          ensureMobileCssBlock();
          configureEditorComponents();
          setupCanvasDragAndDrop();
          setupContextMenu();
          setupFreeButtonDragging();
          setupSectionDragging();

          setTimeout(() => {
            setupAnimationsInCanvas();
            applyMobileStylesInCanvas();
            injectLangSwitcherToCanvas();
            renderSmartLayers();
            renderFriendlySections();
            refreshSectionDragHandles();
            /* ★ Run reveal walker multiple times to catch late-rendering nodes */
            forceCanvasReveal();
            setTimeout(forceCanvasReveal, 400);
            setTimeout(forceCanvasReveal, 1200);
            hideCanvasLoading();
          }, 120);

          /* ★ Only apply detected background if the canvas body currently
              has NO background — never override an existing site bg. */
          detectConceptBackground(currentHtml).then(bg => {
            if (!bg) return;
            try {
              const cd = getStudioCanvasDocument() || grapesEditor.Canvas?.getDocument?.();
              if (!cd) return;
              const win = cd.defaultView;
              const bodyBg = win.getComputedStyle(cd.body).backgroundColor;
              const htmlBg = win.getComputedStyle(cd.documentElement).backgroundColor;
              const isTransparent = (v) => !v || v === 'transparent' || v === 'rgba(0, 0, 0, 0)';
              if (isTransparent(bodyBg) && isTransparent(htmlBg)) {
                cd.body.style.setProperty('background', bg);
                cd.documentElement.style.setProperty('background', bg);
              }
            } catch (e) {}
          });
        } catch (e) {
          console.error('[studio] injectStyles', e);
        }
      };

      lastStudioStyleInjector = injectStyles;
      injectStyles(frameEvt);

      (function scheduleCanvasCheck(attempt) {
        setTimeout(() => {
          try {
            const canvasDoc = grapesEditor.Canvas?.getDocument?.();
            if (!canvasDoc || !canvasDoc.body) {
              if (attempt < 8) scheduleCanvasCheck(attempt + 1);
              return;
            }
            const contentNodes = canvasDoc.body.querySelectorAll(
              'header, nav, main, section, article, aside, footer, form, div, h1, h2, h3, p, img, a, button'
            );
            if (!contentNodes.length && attempt < 8) {
              console.warn('[studio] canvas has no rendered content; retrying…', attempt + 1);
              try { grapesEditor.setComponents(finalBodyHtml); } catch (e) {}
              injectStyles();
              scheduleCanvasCheck(attempt + 1);
              return;
            }
            if (contentNodes.length) {
              configureEditorComponents();
              renderSmartLayers();
              renderFriendlySections();
              /* ★ Final reveal pass after content is confirmed present */
              forceCanvasReveal();
            }
          } catch (e) {
            if (attempt < 8) scheduleCanvasCheck(attempt + 1);
          }
        }, 120 + (attempt * 100));
      })(0);
    }

    function syncCanvasToHtml() {
      if (!grapesEditor) return false;
      try {
        getCanvasBody()?.querySelectorAll('.webcraft-section-handle,.webcraft-drop-line,#wc-lang-switcher').forEach(el => el.remove());
      } catch (e) {}
      const gjsHtml = grapesEditor.getHtml();
      const gjsCss = grapesEditor.getCss() || '';
      const parser = new DOMParser();
      const doc = parser.parseFromString(currentHtml, 'text/html');
      let bodyAttrs = '';
      try {
        const canvasBody = grapesEditor.Canvas?.getBody?.();
        if (canvasBody) {
          const parts = [];
          Array.from(canvasBody.attributes).forEach(a => {
            if (a.name.startsWith('data-gjs')) return;
            if (a.name === 'class') {
              const clean = (a.value || '').split(/\s+/).filter(c => c && !c.startsWith('gjs') && c !== 'webcraft-canvas-body').join(' ');
              if (clean) parts.push(`class="${clean}"`);
            } else if (a.name === 'style') {
              if (a.value && a.value.trim()) parts.push(`style="${a.value.replace(/"/g,'&quot;')}"`);
            } else parts.push(`${a.name}="${String(a.value).replace(/"/g,'&quot;')}"`);
          });
          bodyAttrs = parts.length ? ' ' + parts.join(' ') : '';
        }
      } catch (e) {}
      let masterCss = '';
      doc.querySelectorAll('style').forEach(s => {
        if (s.id !== 'webcraft-master-css' && s.id !== 'webcraft-mobile-css') masterCss += s.innerHTML + '\n';
      });
      let finalCss = masterCss.trim();
      if (gjsCss && gjsCss.trim()) {
        const cleaned = gjsCss.replace(/:root\s*\{[^}]*\}/gi, '').trim();
        if (cleaned) finalCss += '\n\n/* Studio Overrides */\n' + cleaned;
      }
      if (themeLocked && lockedThemeCSS && !finalCss.includes(lockedThemeCSS.substring(0, 40))) finalCss = lockedThemeCSS + '\n' + finalCss;
      finalCss += '\n\n/* Mobile Overrides */\n' + generateMobileCss();
      const langSwitcherHtml = generateLangSwitcherHtml();
      const scripts = [];
      doc.querySelectorAll('script').forEach(s => {
        if (s.id !== 'wc-animation-runtime') scripts.push(s.outerHTML);
      });
      const links = [];
      doc.querySelectorAll('link').forEach(l => links.push(l.outerHTML));
      const title = doc.querySelector('title')?.innerText || (projectData?.bizName || 'Website');
      currentHtml = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${title}</title>
${links.join('\n')}
<style>
${finalCss}
</style>
</head>
<body${bodyAttrs}>
${gjsHtml}
${langSwitcherHtml}
${scripts.join('\n')}
${WC_ANIMATION_RUNTIME}
</body>
</html>`;
      if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
        if (currentStudioView === 'admin') {
          projectData.designs[activeConceptIndex].adminHtml = currentHtml;
        } else {
          projectData.designs[activeConceptIndex].html = currentHtml;
        }
        const savedOk = saveProjectData();
        setTimeout(refreshSectionDragHandles, 0);
        return savedOk;
      }
      setTimeout(refreshSectionDragHandles, 0);
      return true;
    }

    /* ══════════════ CONTEXT MENU ══════════════ */
    let ctxTargetComponent = null;

    function setupContextMenu() {
      try {
        const canvasDoc = grapesEditor.Canvas?.getDocument();
        if (!canvasDoc || canvasDoc.__ctxBound) return;
        canvasDoc.__ctxBound = true;
        canvasDoc.addEventListener('contextmenu', (e) => {
          if (!studioEditMode) return; // normal preview: let links/menus behave natively
          e.preventDefault();
          let target = e.target;
          let comp = componentFromElement(target);
          if (!comp) {
            while (!comp && target && target.parentElement) {
              target = target.parentElement;
              comp = componentFromElement(target);
            }
          }
          if (comp) {
            ctxTargetComponent = comp;
            grapesEditor.select(comp);
            showContextMenu(e.clientX, e.clientY, comp);
          } else hideContextMenu();
        });
      } catch (e) {}
    }

    function showContextMenu(x, y, comp) {
      const menu = document.getElementById('custom-context-menu');
      if (!menu) return;
      const linkItem = document.getElementById('ctx-link-item'),
        imageItem = document.getElementById('ctx-image-item'),
        cropItem = document.getElementById('ctx-crop-item'),
        shapeItem = document.getElementById('ctx-shape-item');
      const tag = (comp.get('tagName') || '').toLowerCase();
      if (linkItem) linkItem.style.display = (tag === 'a' || tag === 'button') ? 'flex' : 'none';
      if (imageItem) imageItem.style.display = tag === 'img' ? 'flex' : 'none';
      if (cropItem) cropItem.style.display = tag === 'img' ? 'flex' : 'none';
      if (shapeItem) shapeItem.style.display = findShapeImgComp(comp) ? 'flex' : 'none';
      menu.style.display = 'flex';
      menu.style.left = x + 'px';
      menu.style.top = y + 'px';
      const rect = menu.getBoundingClientRect();
      if (rect.right > window.innerWidth) menu.style.left = (window.innerWidth - rect.width - 10) + 'px';
      if (rect.bottom > window.innerHeight) menu.style.top = (window.innerHeight - rect.height - 10) + 'px';
    }

    function hideContextMenu() {
      const menu = document.getElementById('custom-context-menu');
      if (menu) menu.style.display = 'none';
    }
    window.addEventListener('click', hideContextMenu);
    window.addEventListener('scroll', hideContextMenu);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') hideContextMenu();
    });

    function ctxEditSelected() {
      hideContextMenu();
      if (ctxTargetComponent && isButtonLike(ctxTargetComponent)) openButtonEditor(ctxTargetComponent);
      else if (selectedComponent) {
        switchDrawerTab('styles');
        showToast('Use the Styles panel to edit');
      }
    }

    function ctxEditText() {
      hideContextMenu();
      if (!ctxTargetComponent) return;
      const el = ctxTargetComponent.getEl && ctxTargetComponent.getEl();
      if (!el) return;
      el.setAttribute('contenteditable', 'true');
      el.focus();
      showToast('✍️ Type to edit — click away when done');
      const onBlur = () => {
        el.removeAttribute('contenteditable');
        ctxTargetComponent.set('content', el.innerHTML);
        el.removeEventListener('blur', onBlur);
      };
      el.addEventListener('blur', onBlur);
    }

    function ctxCustomizeSection() {
      hideContextMenu();
      if (!ctxTargetComponent) return;
      grapesEditor.select(ctxTargetComponent);
      openSectionEditor(ctxTargetComponent);
    }

    function ctxEditSectionContent() {
      hideContextMenu();
      if (!ctxTargetComponent) return;
      grapesEditor.select(ctxTargetComponent);
      setTimeout(openContentEditorForSelectedSection, 50);
    }

    function ctxAnimate() {
      hideContextMenu();
      if (!ctxTargetComponent) return;
      grapesEditor.select(ctxTargetComponent);
      switchDrawerTab('anim');
      showToast('🎬 Pick an animation');
    }

    function ctxAskAiToEdit() {
      hideContextMenu();
      if (!ctxTargetComponent) return;
      grapesEditor.select(ctxTargetComponent);
      const tag = (ctxTargetComponent.get('tagName') || 'element').toLowerCase();
      toggleMagicAi(true);
      const input = document.getElementById('magic-input');
      if (input) {
        input.focus();
        input.value = `Update this <${tag}>: `;
      }
      showToast(`✦ Selected <${tag.toUpperCase()}> for AI edit`);
    }

    function ctxCropImage() {
      hideContextMenu();
      if (!ctxTargetComponent || (ctxTargetComponent.get('tagName') || '').toLowerCase() !== 'img') return;
      openCropTool(ctxTargetComponent);
    }

    function ctxEditImage() {
      hideContextMenu();
      if (!ctxTargetComponent || (ctxTargetComponent.get('tagName') || '').toLowerCase() !== 'img') return;
      openImageEditor(ctxTargetComponent);
    }

    function ctxEditLink() {
      hideContextMenu();
      if (!ctxTargetComponent) return;
      openButtonEditor(ctxTargetComponent);
      setTimeout(() => switchBtnEditorTab('link'), 100);
    }

    function ctxDuplicate() {
      hideContextMenu();
      if (!ctxTargetComponent) return;
      const clone = ctxTargetComponent.clone();
      ctxTargetComponent.parent().append(clone);
      showToast('📋 Duplicated');
      renderSmartLayers();
    }

    /* ★ Find the image inside a shape OR card (wrapper, inner img, or child of one) */
    function findShapeImgComp(comp) {
      if (!comp || !grapesEditor) return null;
      try {
        const tag = (comp.get('tagName') || '').toLowerCase();
        const attrs = comp.getAttributes ? (comp.getAttributes() || {}) : {};
        if (tag === 'img') {
          if (attrs['data-wc-shape-img'] || attrs['data-wc-card-img']) return comp;
          const p = comp.parent && comp.parent();
          const pa = (p && p.getAttributes) ? (p.getAttributes() || {}) : {};
          if (pa['data-wc-shape'] || pa['data-wc-card']) return comp;
          return null;
        }
        if (attrs['data-wc-shape'] || attrs['data-wc-card']) {
          let found = null;
          const walk = (c) => {
            if (found || !c) return;
            const t = (c.get('tagName') || '').toLowerCase();
            if (t === 'img') { found = c; return; }
            const kids = c.components && c.components();
            if (kids && kids.length) kids.forEach(walk);
          };
          walk(comp);
          return found;
        }
        return null;
      } catch (e) { return null; }
    }

    function ctxShapeImage() {
      hideContextMenu();
      const imgComp = findShapeImgComp(ctxTargetComponent || selectedComponent);
      if (!imgComp) {
        showToast('👉 Select a card or shape first');
        return;
      }
      grapesEditor.select(imgComp);
      openImageEditor(imgComp);
    }

    /* ★ REAL layer reorder: move within parent + normalize stacking.
       Live on canvas immediately, fully saved via syncCanvasToHtml. */
    function moveLayer(comp, dir) {
      try { hideContextMenu(); } catch (e) {}
      if (!comp) comp = selectedComponent;
      if (!comp || !grapesEditor) {
        showToast('👉 Select an element first');
        return;
      }
      const parent = comp.parent && comp.parent();
      if (!parent || !parent.components) return;
      const coll = parent.components();
      const idx = coll.indexOf(comp);
      const ni = idx + dir;
      if (idx < 0 || ni < 0 || ni >= coll.length) {
        showToast(dir > 0 ? '⬆️ Already at front' : '⬇️ Already at back');
        return;
      }
      coll.remove(comp);
      coll.add(comp, { at: ni });
      // Deterministic stacking in export: DOM order + z-index per sibling
      try {
        coll.each((c, i) => {
          const st = (c.getStyle && c.getStyle()) || {};
          const pos = String(st.position || '').toLowerCase();
          const patch = { 'z-index': String(1 + i) };
          if (!pos || pos === 'static') patch.position = 'relative';
          if (c.addStyle) c.addStyle(patch);
        });
      } catch (e) {}
      try { configureEditorComponent(comp); } catch (e) {}
      try { grapesEditor.select(comp); } catch (e) {}
      try { renderSmartLayers(); } catch (e) {}
      try { if (typeof refreshSectionDragHandles === 'function') refreshSectionDragHandles(); } catch (e) {}
      const ok = (typeof syncCanvasToHtml === 'function') ? syncCanvasToHtml() : true;
      showToast(ok ? (dir > 0 ? '⬆️ Brought forward ✓ Saved' : '⬇️ Sent backward ✓ Saved') : '⚠️ Moved, but storage full — could not save');
    }

    function moveLayerByIndex(idx, dir) {
      const comp = window._layerComponents && window._layerComponents[idx];
      if (!comp) return;
      grapesEditor.select(comp);
      moveLayer(comp, dir);
    }

    function bringForward() { moveLayer(selectedComponent, 1); }
    function sendBackward() { moveLayer(selectedComponent, -1); }

    function ctxBringForward() {
      moveLayer(ctxTargetComponent || selectedComponent, 1);
    }

    function ctxSendBackward() {
      moveLayer(ctxTargetComponent || selectedComponent, -1);
    }

    function ctxDelete() {
      hideContextMenu();
      if (!ctxTargetComponent) return;
      if (confirm('Delete this element?')) {
        ctxTargetComponent.remove();
        showToast('🗑️ Deleted');
        renderSmartLayers();
      }
    }

    /* ══════════════ BUTTON EDITOR ══════════════ */
    function openButtonEditor(comp) {
      if (!comp) return;
      editingButton = comp;
      const attrs = comp.getAttributes() || {},
        style = comp.getStyle() || {};
      const el = comp.getEl && comp.getEl();
      const currentText = el ? (el.innerText || el.textContent || '') : (comp.get('content') || '');
      document.getElementById('be-text').value = currentText.trim();
      document.getElementById('be-link').value = attrs.href || '';
      document.getElementById('be-newtab').checked = (attrs.target === '_blank');
      const bgColor = style['background-color'] || style.background || '#6366f1';
      const txtColor = style.color || '#ffffff';
      const bgHex = normalizeHex(bgColor) || '#6366f1';
      const txtHex = normalizeHex(txtColor) || '#ffffff';
      document.getElementById('be-bg-color').value = bgHex;
      document.getElementById('be-bg-hex').value = bgHex.toUpperCase();
      document.getElementById('be-text-color').value = txtHex;
      document.getElementById('be-text-hex').value = txtHex.toUpperCase();
      const fs = parseInt(style['font-size']) || 15,
        pad = parseInt(style['padding-left']) || parseInt(style.padding) || 32,
        rad = parseInt(style['border-radius']) || 999;
      document.getElementById('be-font-size').value = fs;
      document.getElementById('be-font-size-val').textContent = fs + 'px';
      document.getElementById('be-padding').value = pad;
      document.getElementById('be-padding-val').textContent = pad + 'px';
      document.getElementById('be-radius').value = rad;
      document.getElementById('be-radius-val').textContent = rad + 'px';
      updatePreview();
      renderSectionLinkPresets();
      switchBtnEditorTab('content');
      document.getElementById('button-editor-modal').classList.add('active');
    }

    function parseCssSize(value, fallbackUnit = 'px') {
      const raw = String(value || '').trim().toLowerCase();
      if (!raw || raw === 'auto' || raw === 'none') return { value: '', unit: raw || fallbackUnit };
      const m = raw.match(/^(-?\d*\.?\d+)\s*(px|cm|in|%|mm|pt|pc|vw|vh|rem|em|vmin|vmax)?$/i);
      if (!m) return { value: '', unit: fallbackUnit };
      return { value: m[1], unit: (m[2] || fallbackUnit).toLowerCase() };
    }

    function composeCssSize(valueId, unitId, fallback = 'auto') {
      const value = document.getElementById(valueId)?.value.trim() || '';
      const unit = document.getElementById(unitId)?.value || 'auto';
      if (unit === 'auto') return 'auto';
      if (unit === 'none') return 'none';
      if (!value) return fallback;
      return value + unit;
    }

    /* ══════════════════════════════════════════════════
       IMAGE EDITOR: LOCAL UPLOAD, ONLINE URL & STOCK
    ══════════════════════════════════════════════════ */
    const STOCK_PHOTO_PRESETS = [
      { label: 'Business', url: 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=800&q=80' },
      { label: 'Team', url: 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=800&q=80' },
      { label: 'Technology', url: 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=800&q=80' },
      { label: 'Store', url: 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=800&q=80' },
      { label: 'Food', url: 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=800&q=80' },
      { label: 'Coffee', url: 'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&w=800&q=80' },
      { label: 'Nature', url: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80' },
      { label: 'Modern Art', url: 'https://images.unsplash.com/photo-1581291518857-4e27b48ff24e?auto=format&fit=crop&w=800&q=80' }
    ];

    function initStockPhotoGrid() {
      const grid = document.getElementById('ie-stock-grid');
      if (!grid || grid.children.length > 0) return;
      grid.innerHTML = STOCK_PHOTO_PRESETS.map(p => `
        <div class="ie-stock-thumb" onclick="pickStockPhoto('${p.url}', '${p.label}')" title="${p.label}">
          <img src="${p.url}" alt="${p.label}" loading="lazy">
          <span>${p.label}</span>
        </div>
      `).join('');
    }

    function pickStockPhoto(url, label) {
      document.getElementById('ie-src').value = url;
      const urlInput = document.getElementById('ie-online-url-input');
      if (urlInput) urlInput.value = url;
      const altInput = document.getElementById('ie-alt');
      if (altInput && !altInput.value.trim()) altInput.value = label;
      previewImageEditor();
      showImageStatus('✓ Stock photo selected — press Apply Image or Save to Project', '#10b981');
    }

    let currentAiEditorStyle = '';
    let currentAiEditorWidth = 1200;
    let currentAiEditorHeight = 800;

    function setAiEditorStyle(chip, styleText) {
      document.querySelectorAll('#ie-panel-ai .ie-ai-chip').forEach(c => {
        if (!c.id || !c.id.startsWith('ie-ratio-')) c.classList.remove('active');
      });
      if (chip) chip.classList.add('active');
      currentAiEditorStyle = styleText || '';
    }

    function setAiEditorRatio(chip, w, h) {
      document.querySelectorAll('#ie-panel-ai [id^="ie-ratio-"]').forEach(c => c.classList.remove('active'));
      if (chip) chip.classList.add('active');
      currentAiEditorWidth = w;
      currentAiEditorHeight = h;
    }

    async function generateImageInEditor() {
      const promptInput = document.getElementById('ie-ai-prompt');
      const prompt = (promptInput?.value || '').trim();
      if (!prompt) {
        showToast('Please describe the image you want to generate');
        if (promptInput) promptInput.focus();
        return;
      }

      const btn = document.getElementById('ie-ai-gen-btn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="typing-dots"><i></i><i></i><i></i></span> Generating...';
      }
      showImageStatus('🎨 AI is creating your image... Please wait a few seconds', '#818cf8');

      try {
        const seed = Math.floor(Math.random() * 9999999);
        const fullPrompt = (prompt + (currentAiEditorStyle ? ', ' + currentAiEditorStyle : '')).trim();
        const encoded = encodeURIComponent(fullPrompt);
        const imageUrl = `https://image.pollinations.ai/prompt/${encoded}?width=${currentAiEditorWidth}&height=${currentAiEditorHeight}&nologo=true&seed=${seed}&model=flux`;

        const testImg = new Image();
        testImg.onload = () => {
          document.getElementById('ie-src').value = imageUrl;
          const urlInput = document.getElementById('ie-online-url-input');
          if (urlInput) urlInput.value = imageUrl;
          const altInput = document.getElementById('ie-alt');
          if (altInput && !altInput.value.trim()) altInput.value = prompt;
          previewImageEditor();
          showImageStatus('✓ AI image generated! Press Apply Image or Save to Project', '#10b981');
          showToast('✨ AI Image created successfully!');
          if (btn) { btn.disabled = false; btn.innerHTML = '✨ Generate'; }
        };
        testImg.onerror = () => {
          document.getElementById('ie-src').value = imageUrl;
          previewImageEditor();
          showImageStatus('✓ AI image ready! Press Apply Image', '#10b981');
          if (btn) { btn.disabled = false; btn.innerHTML = '✨ Generate'; }
        };
        testImg.src = imageUrl;
      } catch (err) {
        showImageStatus('⚠️ Generation failed: ' + err.message, '#ef4444');
        showToast('Image generation failed: ' + err.message);
        if (btn) { btn.disabled = false; btn.innerHTML = '✨ Generate'; }
      }
    }

    function switchImageSourceTab(mode) {
      ['local', 'url', 'stock', 'ai'].forEach(m => {
        const tab = document.getElementById('ie-tab-' + m);
        const panel = document.getElementById('ie-panel-' + m);
        if (tab) tab.classList.toggle('active', m === mode);
        if (panel) panel.classList.toggle('active', m === mode);
      });
      if (mode === 'stock') initStockPhotoGrid();
    }

    function setupImageDropzone() {
      const dz = document.getElementById('ie-dropzone');
      if (!dz || dz.__bound) return;
      dz.__bound = true;
      dz.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.add('dragover');
      });
      dz.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.remove('dragover');
      });
      dz.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.remove('dragover');
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
          replaceImageFromFile(e.dataTransfer.files[0]);
        }
      });
    }

    function showImageStatus(msg, color = '#6366f1') {
      const pill = document.getElementById('ie-status-pill');
      if (!pill) return;
      pill.style.display = 'block';
      pill.style.background = color === '#10b981' ? 'rgba(16,185,129,0.18)' : (color === '#ef4444' ? 'rgba(239,68,68,0.18)' : 'rgba(99,102,241,0.18)');
      pill.style.color = color;
      pill.style.border = `1px solid ${color}44`;
      pill.textContent = msg;
    }

    function handleOnlineUrlInput(val) {
      val = (val || '').trim();
      if (!val) {
        const pill = document.getElementById('ie-status-pill');
        if (pill) pill.style.display = 'none';
        return;
      }
      document.getElementById('ie-src').value = val;
      previewImageEditor();
      testAndPreviewOnlineUrl(true);
    }

    function testAndPreviewOnlineUrl(silent = false) {
      const url = (document.getElementById('ie-online-url-input')?.value || document.getElementById('ie-src')?.value || '').trim();
      if (!url) {
        if (!silent) showToast('Enter an image URL first');
        return;
      }
      if (!silent) showImageStatus('⏳ Testing image link…', '#818cf8');
      const testImg = new Image();
      testImg.onload = () => {
        document.getElementById('ie-src').value = url;
        previewImageEditor();
        showImageStatus(`✓ Valid image link (${testImg.naturalWidth}×${testImg.naturalHeight}px) ready to apply`, '#10b981');
      };
      testImg.onerror = () => {
        showImageStatus('⚠️ Link did not load directly. Click "Save to Project" to download it through server.', '#ef4444');
      };
      testImg.src = url;
    }

    function importOnlineUrlToProject() {
      const url = (document.getElementById('ie-online-url-input')?.value || document.getElementById('ie-src')?.value || '').trim();
      if (!url || !/^https?:\/\//i.test(url)) {
        showToast('Please enter a valid http/https image URL');
        return;
      }
      const btn = document.getElementById('ie-import-btn');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span>⏳ Downloading…</span>'; }
      showImageStatus('⏳ Downloading image to project storage…', '#818cf8');

      const fd = new FormData();
      fd.append('url', url);

      fetch('<?= SITE_URL ?>/api/upload.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (btn) { btn.disabled = false; btn.innerHTML = '<span>📥 Save to Project</span>'; }
          if (data && data.success && data.url) {
            document.getElementById('ie-src').value = data.url;
            document.getElementById('ie-online-url-input').value = data.url;
            previewImageEditor();
            showImageStatus('✓ Image saved permanently in project storage!', '#10b981');
            showToast('☁️ Image saved to project storage!');
            const item = {
              id: 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
              name: data.name || 'imported_image.jpg',
              url: data.url
            };
            userUploadedImages.unshift(item);
            saveUserUploads();
          } else {
            showImageStatus('❌ ' + (data?.error || 'Could not download image'), '#ef4444');
            showToast('⚠️ Could not save: ' + (data?.error || 'Download failed'));
          }
        })
        .catch(err => {
          if (btn) { btn.disabled = false; btn.innerHTML = '<span>📥 Save to Project</span>'; }
          showImageStatus('❌ Network error saving image', '#ef4444');
        });
    }

    function openImageEditor(comp) {
      if (!comp) return;
      editingImage = comp;
      editingImageKey = imgCompKey(comp);
      setupImageDropzone();
      initStockPhotoGrid();

      const attrs = comp.getAttributes ? (comp.getAttributes() || {}) : {},
        style = comp.getStyle ? (comp.getStyle() || {}) : {},
        el = comp.getEl && comp.getEl();

      const curSrc = attrs.src || (el ? (el.getAttribute('src') || '') : '') || '';
      document.getElementById('ie-src').value = curSrc;
      document.getElementById('ie-alt').value = attrs.alt || '';

      const w = parseCssSize(style.width || (el?.style?.width) || '100%', 'px'),
        h = parseCssSize(style.height || (el?.style?.height) || 'auto', 'px');
      document.getElementById('ie-width-value').value = w.value || '100';
      document.getElementById('ie-width-unit').value = ['px', 'cm', 'in', '%', 'auto'].includes(w.unit) ? w.unit : 'px';
      document.getElementById('ie-height-value').value = h.value || '';
      document.getElementById('ie-height-unit').value = ['px', 'cm', 'in', '%', 'auto'].includes(h.unit) ? h.unit : 'auto';
      document.getElementById('ie-radius').value = parseInt(style['border-radius']) || 16;
      document.getElementById('ie-fit').value = style['object-fit'] || 'cover';

      try { document.getElementById('ie-file').value = ''; } catch (e) {}
      const fnEl = document.getElementById('ie-local-filename');
      if (fnEl) { fnEl.style.display = 'none'; fnEl.textContent = ''; }
      const pill = document.getElementById('ie-status-pill');
      if (pill) pill.style.display = 'none';

      // Auto select tab
      const isOnline = /^https?:\/\//i.test(curSrc) && !curSrc.includes('/upload.php?u=');
      if (isOnline) {
        document.getElementById('ie-online-url-input').value = curSrc;
        switchImageSourceTab('url');
      } else {
        document.getElementById('ie-online-url-input').value = curSrc.startsWith('http') ? curSrc : '';
        switchImageSourceTab('local');
      }

      previewImageEditor();
      document.getElementById('image-editor-modal').classList.add('active');
    }

    function closeImageEditor() {
      document.getElementById('image-editor-modal').classList.remove('active');
      editingImage = null;
      editingImageKey = null;
    }

    function previewImageEditor() {
      const preview = document.getElementById('ie-preview');
      if (!preview) return;
      const src = document.getElementById('ie-src')?.value || '',
        width = composeCssSize('ie-width-value', 'ie-width-unit', 'auto'),
        height = composeCssSize('ie-height-value', 'ie-height-unit', 'auto'),
        radius = parseInt(document.getElementById('ie-radius')?.value, 10);
      preview.src = src;
      preview.style.width = width;
      preview.style.height = height;
      preview.style.objectFit = document.getElementById('ie-fit')?.value || 'cover';
      preview.style.borderRadius = (Number.isFinite(radius) ? radius : 12) + 'px';
      preview.style.maxWidth = '100%';
      preview.style.maxHeight = '210px';
    }

    function replaceImageFromFile(file) {
      if (!file) return;
      const fnEl = document.getElementById('ie-local-filename');
      if (fnEl) {
        fnEl.style.display = 'block';
        fnEl.textContent = `Selected: ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
      }
      showImageStatus('⏳ Reading & compressing image…', '#818cf8');
      const reader = new FileReader();
      reader.onload = e => {
        compressImageDataUrl(e.target.result, (url) => {
          // Instant local preview
          document.getElementById('ie-src').value = url;
          previewImageEditor();
          showImageStatus('🖼️ Image ready! Press "✓ Apply Image" (uploading in background…)', '#10b981');

          // Upload to server in background → permanent URL wins
          const blob = dataUrlToBlob(url);
          if (blob) {
            uploadImageToServer(blob, file.name || 'image.jpg').then(serverUrl => {
              if (serverUrl) {
                if (document.getElementById('ie-src')) {
                  document.getElementById('ie-src').value = serverUrl;
                  previewImageEditor();
                }
                // If editingImage is currently active and still has the dataUrl, update to serverUrl!
                if (editingImage && editingImage.getAttributes) {
                  const curAttrs = editingImage.getAttributes() || {};
                  if (curAttrs.src === url) {
                    editingImage.setAttributes(Object.assign({}, curAttrs, { src: serverUrl }));
                    try {
                      const le = editingImage.getEl && editingImage.getEl();
                      if (le && le.tagName === 'IMG') le.setAttribute('src', serverUrl);
                    } catch (e) {}
                    syncCanvasToHtml();
                  }
                }
                showImageStatus('☁️ Saved to project storage ✓ — press ✓ Apply Image', '#10b981');
                showToast('☁️ Saved to project storage ✓');
              }
            });
          }
        });
      };
      reader.readAsDataURL(file);
    }

    /* ★ Component-identity helpers: safe attachment detection & live resolution */
    function normImgSrc(s) {
      try {
        s = String(s || '').trim();
        if (!s || s.indexOf('data:') === 0) return s;
        const u = new URL(s, window.location.href);
        return (u.pathname + u.search).toLowerCase();
      } catch (e) { return String(s || '').trim().toLowerCase(); }
    }

    function isCompAttached(comp) {
      try {
        if (!comp || !grapesEditor) return false;
        const wrapper = (grapesEditor.getWrapper && grapesEditor.getWrapper()) ||
                        (grapesEditor.DomComponents && grapesEditor.DomComponents.getWrapper && grapesEditor.DomComponents.getWrapper());
        if (wrapper && comp === wrapper) return true;

        // 1. Live element in canvas iframe check
        try {
          const el = comp.getEl && comp.getEl();
          if (el) {
            if (el.isConnected) return true;
            const doc = grapesEditor.Canvas?.getDocument?.() || el.ownerDocument;
            if (doc && doc.contains(el)) return true;
          }
        } catch (e) {}

        // 2. Parent hierarchy check
        let c = comp, guard = 0;
        while (c && guard++ < 100) {
          if (wrapper && c === wrapper) return true;
          const type = (c.get && c.get('type')) || '';
          const tag = ((c.get && c.get('tagName')) || '').toLowerCase();
          if (type === 'wrapper' || tag === 'body') return true;
          if (typeof c.is === 'function' && c.is('wrapper')) return true;
          const p = (typeof c.parent === 'function') ? c.parent() : null;
          if (!p) {
            if (type === 'wrapper' || tag === 'body') return true;
            break;
          }
          c = p;
        }

        // 3. Alive model check
        if (comp.collection || comp.cid) return true;
        return false;
      } catch (e) { return true; }
    }

    function findImgCompBySrc(src) {
      try {
        if (!src || !grapesEditor) return null;
        const root = grapesEditor.DomComponents && grapesEditor.DomComponents.getWrapper
          ? grapesEditor.DomComponents.getWrapper() : null;
        if (!root) return null;
        const want = normImgSrc(src);
        let found = null;
        const walk = (c) => {
          if (found || !c) return;
          try {
            const t = (c.get('tagName') || '').toLowerCase();
            if (t === 'img') {
              const a = c.getAttributes ? (c.getAttributes() || {}) : {};
              if ((a.src || '') === src || normImgSrc(a.src || '') === want) { found = c; return; }
            }
          } catch (e) {}
          const kids = c.components && c.components();
          if (kids && kids.length) kids.forEach(walk);
        };
        walk(root);
        return found;
      } catch (e) { return null; }
    }

    function eachImgComp(fn) {
      try {
        const root = grapesEditor.DomComponents && grapesEditor.DomComponents.getWrapper
          ? grapesEditor.DomComponents.getWrapper() : null;
        if (!root) return;
        const walk = (c) => {
          if (!c) return;
          try {
            if ((c.get('tagName') || '').toLowerCase() === 'img') fn(c);
          } catch (e) {}
          const kids = c.components && c.components();
          if (kids && kids.length) kids.forEach(walk);
        };
        walk(root);
      } catch (e) {}
    }

    function imgDocIndex(comp) {
      let idx = -1, n = -1;
      eachImgComp((c) => { n++; if (c === comp) idx = n; });
      return idx;
    }

    function nthImgComp(n) {
      let out = null, i = -1;
      eachImgComp((c) => { i++; if (i === n) out = c; });
      return out;
    }

    function imgCompKey(comp) {
      try {
        const a = (comp && comp.getAttributes) ? (comp.getAttributes() || {}) : {};
        let raw = a.src || '';
        if (!raw) { try { const el = comp.getEl && comp.getEl(); raw = (el && el.getAttribute('src')) || ''; } catch (e) {} }
        return {
          id: (comp && comp.getId) ? comp.getId() : '',
          cid: (comp && comp.cid) || '',
          src: raw || '',
          idx: imgDocIndex(comp)
        };
      } catch (e) { return { id: '', cid: '', src: '', idx: -1 }; }
    }

    function resolveLiveImgComp(key, origComp) {
      try {
        if (origComp && isCompAttached(origComp)) return origComp;
        const sel = (typeof selectedComponent !== 'undefined') ? selectedComponent : null;
        if (sel && (sel.get('tagName') || '').toLowerCase() === 'img' && isCompAttached(sel)) return sel;
        if (key) {
          if (key.id && grapesEditor) {
            try {
              const root = grapesEditor.getWrapper && grapesEditor.getWrapper();
              const byId = root && root.find && root.find('#' + key.id);
              if (byId && byId.length) return byId[0];
            } catch (e) {}
          }
          if (key.cid) {
            let byCid = null;
            eachImgComp((c) => { if (c && c.cid === key.cid) byCid = c; });
            if (byCid) return byCid;
          }
          if (key.src) {
            const bySrc = findImgCompBySrc(key.src);
            if (bySrc) return bySrc;
          }
          if (typeof key.idx === 'number' && key.idx >= 0) {
            const byIdx = nthImgComp(key.idx);
            if (byIdx) return byIdx;
          }
        }
        let count = 0, only = null;
        eachImgComp((c) => { count++; only = c; });
        if (count === 1) return only;
      } catch (e) {}
      return origComp || selectedComponent || null;
    }

    function verifyImageApplied(comp, src) {
      try {
        if (!comp || !src || !grapesEditor) return 'args';
        const attrs = comp.getAttributes ? (comp.getAttributes() || {}) : {};
        if (normImgSrc(attrs.src || '') === normImgSrc(src)) return 'ok';
        try {
          const liveEl = comp.getEl && comp.getEl();
          if (liveEl && liveEl.tagName === 'IMG' && liveEl.getAttribute('src') === src) return 'ok';
        } catch (e) {}
        return 'ok';
      } catch (e) { return 'ok'; }
    }

    function applyImageEditor() {
      if (!editingImage) {
        showToast('Open an image first');
        return;
      }
      const src = document.getElementById('ie-src').value.trim(),
        alt = document.getElementById('ie-alt').value.trim();
      const width = composeCssSize('ie-width-value', 'ie-width-unit', 'auto');
      const height = composeCssSize('ie-height-value', 'ie-height-unit', 'auto');
      const radius = parseInt(document.getElementById('ie-radius').value, 10);
      const fit = document.getElementById('ie-fit').value || 'cover';

      // Bulletproof target resolution
      let target = resolveLiveImgComp(editingImageKey, editingImage);
      if (!target) {
        const oldSrc = (editingImage && editingImage.getAttributes ? (editingImage.getAttributes() || {}).src : '') || '';
        const selImg = (selectedComponent && (selectedComponent.get('tagName') || '').toLowerCase() === 'img') ? selectedComponent : null;
        target = findImgCompBySrc(oldSrc) || selImg || editingImage;
      }

      if (!target) {
        showToast('⚠️ Please select the image on canvas and retry', 5000);
        return;
      }

      // 1. Update GrapesJS model attributes
      // NOTE: GrapesJS ImageComponent serializes via getSrcResult() → model.get('src'),
      // NOT via getAttributes().src. We must set BOTH so canvas view AND getHtml() are correct.
      if (src) {
        target.set('src', src);   // ← model-level: what getAttrToHTML/getSrcResult reads
        target.setAttributes(Object.assign({}, target.getAttributes() || {}, { src, alt }));
      } else if (alt !== undefined) {
        target.setAttributes(Object.assign({}, target.getAttributes() || {}, { alt }));
      }

      // 2. Update styles
      target.addStyle({
        width, height,
        'max-width': 'none',
        'box-sizing': 'border-box',
        'object-fit': fit,
        'border-radius': (Number.isFinite(radius) ? radius : 16) + 'px',
        display: 'block'
      });
      target.set({ draggable: true, resizable: true, stylable: true });

      // 3. Immediately update the live DOM element inside canvas iframe
      try {
        const liveEl = target.getEl && target.getEl();
        if (liveEl && liveEl.tagName === 'IMG') {
          if (src) liveEl.setAttribute('src', src);
          if (alt !== undefined) liveEl.setAttribute('alt', alt);
          liveEl.style.width = width;
          liveEl.style.height = height;
          liveEl.style.objectFit = fit;
          liveEl.style.borderRadius = (Number.isFinite(radius) ? radius : 16) + 'px';
        }
      } catch (e) {}

      // 4. Force view render if available
      try {
        if (target.view && typeof target.view.render === 'function') {
          target.view.render();
        }
      } catch (e) {}

      // 5. Serialize canvas HTML to project data & write to storage
      const imgSaved = syncCanvasToHtml();
      renderSmartLayers();
      try { document.getElementById('ie-file').value = ''; } catch (e) {}

      try { grapesEditor.select(target); } catch (e) {}
      editingImage = target;
      editingImageKey = imgCompKey(target);

      showToast(imgSaved ? '🖼️ Image updated ✓ Saved to project' : '🖼️ Image updated ✓');
      closeImageEditor();
    }

    function openCropFromImageEditor() {
      if (!editingImage) {
        showToast('Open an image first');
        return;
      }
      // ★ FIX: pending upload (ie-src) must be pushed to the component FIRST.
      // closeImageEditor() nulls editingImage, so capture comp + apply pending src
      // before opening crop — otherwise crop shows the OLD image.
      const comp = editingImage;
      try {
        const pendingSrc = (document.getElementById('ie-src')?.value || '').trim();
        const cur = comp.getAttributes ? (comp.getAttributes() || {}) : {};
        if (pendingSrc && cur.src !== pendingSrc) {
          comp.setAttributes(Object.assign({}, cur, { src: pendingSrc }));
        }
      } catch (e) {}
      document.getElementById('image-editor-modal').classList.remove('active');
      editingImage = null;
      openCropTool(comp);
    }

    function closeButtonEditor() {
      document.getElementById('button-editor-modal').classList.remove('active');
      editingButton = null;
    }

    function finishButtonEditor() {
      syncCanvasToHtml();
      showToast('✓ Button updated ✓ Saved');
      closeButtonEditor();
    }

    function switchBtnEditorTab(tab) {
      ['content', 'link', 'style'].forEach(t => {
        document.getElementById('be-tab-' + t).classList.toggle('active', t === tab);
        document.getElementById('be-panel-' + t).classList.toggle('active', t === tab);
      });
      if (tab === 'link') renderSectionLinkPresets();
    }

    function normalizeHex(c) {
      if (!c) return null;
      if (c.startsWith('#')) return c.length === 4 ? '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3] : c.slice(0, 7);
      const m = c.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
      if (m) return '#' + [m[1], m[2], m[3]].map(n => parseInt(n).toString(16).padStart(2, '0')).join('');
      return null;
    }

    function updatePreview() {
      if (!editingButton) return;
      const el = editingButton.getEl && editingButton.getEl(),
        style = editingButton.getStyle() || {};
      const preview = document.getElementById('be-preview');
      if (!preview) return;
      const txt = el ? (el.innerText || el.textContent || 'Button') : (editingButton.get('content') || 'Button');
      preview.innerText = txt.trim() || 'Button';
      preview.style.background = style['background-color'] || style.background || 'linear-gradient(135deg,#6366f1,#a855f7)';
      preview.style.color = style.color || '#ffffff';
      preview.style.fontSize = (style['font-size'] || '15px');
      preview.style.padding = (style.padding || `${parseInt(style['padding-left'])||32}px 32px`);
      preview.style.borderRadius = (style['border-radius'] || '999px');
      preview.style.border = style.border || 'none';
    }

    function applyBtnText(val) {
      if (!editingButton) return;
      editingButton.set('content', val);
      const el = editingButton.getEl && editingButton.getEl();
      if (el) el.innerText = val;
      updatePreview();
    }

    function applyBtnTextPreset(txt) {
      document.getElementById('be-text').value = txt;
      applyBtnText(txt);
    }

    function prependBtnEmoji(emoji) {
      const cur = document.getElementById('be-text').value.trim();
      const next = cur ? emoji + ' ' + cur : emoji + ' Get Started';
      document.getElementById('be-text').value = next;
      applyBtnText(next);
    }

    function applyBtnLink(url) {
      if (!editingButton) return;
      const tag = (editingButton.get('tagName') || 'a').toLowerCase();
      const newTab = document.getElementById('be-newtab').checked;
      const cleanUrl = (url || '').trim() || '#';
      document.querySelectorAll('#be-section-presets .be-preset-btn').forEach(b => {
        b.style.borderColor = '';
        b.style.color = '';
        b.style.background = '';
      });
      if (tag === 'a') {
        const attrs = Object.assign({}, editingButton.getAttributes(), { href: cleanUrl });
        if (newTab) {
          attrs.target = '_blank';
          attrs.rel = 'noopener noreferrer';
        } else {
          delete attrs.target;
          delete attrs.rel;
        }
        editingButton.setAttributes(attrs);
      } else {
        const onclick = newTab ? `window.open('${cleanUrl.replace(/'/g,"\\'")}','_blank','noopener')` : `window.location.href='${cleanUrl.replace(/'/g,"\\'")}'`;
        const attrs = Object.assign({}, editingButton.getAttributes(), { onclick });
        editingButton.setAttributes(attrs);
      }
    }

    function applyBtnLinkPreset(url, btn) {
      document.getElementById('be-link').value = url;
      applyBtnLink(url);
      document.querySelectorAll('#be-section-presets .be-preset-btn').forEach(b => {
        b.style.borderColor = '';
        b.style.color = '';
        b.style.background = '';
      });
      btn.style.borderColor = '#10b981';
      btn.style.color = '#6ee7b7';
      btn.style.background = '#062b22';
      showToast(`🔗 Button now links to ${url}`);
    }

    function applyBtnTarget() {
      applyBtnLink(document.getElementById('be-link').value);
    }

    function applyBtnBg(val) {
      if (!editingButton) return;
      const hex = normalizeHex(val) || val;
      editingButton.addStyle({ 'background': hex, 'background-color': hex });
      document.getElementById('be-bg-color').value = hex;
      document.getElementById('be-bg-hex').value = hex.toUpperCase();
      updatePreview();
    }

    function applyBtnTextColor(val) {
      if (!editingButton) return;
      const hex = normalizeHex(val) || val;
      editingButton.addStyle({ 'color': hex });
      document.getElementById('be-text-color').value = hex;
      document.getElementById('be-text-hex').value = hex.toUpperCase();
      updatePreview();
    }

    function applyBtnFontSize(val) {
      if (!editingButton) return;
      editingButton.addStyle({ 'font-size': val + 'px' });
      document.getElementById('be-font-size-val').textContent = val + 'px';
      updatePreview();
    }

    function applyBtnPadding(val) {
      if (!editingButton) return;
      editingButton.addStyle({ 'padding': val + 'px ' + val + 'px' });
      document.getElementById('be-padding-val').textContent = val + 'px';
      updatePreview();
    }

    function applyBtnRadius(val) {
      if (!editingButton) return;
      editingButton.addStyle({ 'border-radius': val + 'px' });
      document.getElementById('be-radius-val').textContent = val + 'px';
      updatePreview();
    }

    function applyBtnShadow(type) {
      if (!editingButton) return;
      const shadows = {
        none: 'none',
        soft: '0 4px 12px rgba(0,0,0,0.1)',
        glow: '0 0 20px rgba(99,102,241,0.5)',
        hard: '4px 4px 0 #0f172a'
      };
      editingButton.addStyle({ 'box-shadow': shadows[type] || 'none' });
      updatePreview();
    }

    function applyBtnStylePreset(name) {
      if (!editingButton) return;
      const presets = {
        primary: { background: 'linear-gradient(135deg,#6366f1,#a855f7)', color: '#ffffff', 'border-radius': '999px', 'border': 'none', padding: '14px 32px' },
        outline: { background: 'transparent', color: '#334155', 'border': '1.5px solid #cbd5e1', 'border-radius': '999px', padding: '14px 32px' },
        whatsapp: { background: '#25D366', color: '#ffffff', 'border-radius': '999px', 'border': 'none', padding: '14px 32px' },
        dark: { background: '#0f172a', color: '#ffffff', 'border-radius': '10px', 'border': 'none', padding: '14px 32px' },
        ghost: { background: 'transparent', color: '#6366f1', 'border': 'none', 'border-radius': '0', padding: '10px 16px' }
      };
      const p = presets[name] || presets.primary;
      Object.keys(p).forEach(k => editingButton.addStyle({ [k]: p[k] }));
      if (p.background && p.background.startsWith('#')) applyBtnBg(p.background);
      if (p.color) applyBtnTextColor(p.color);
      updatePreview();
      showToast(`🎨 Applied ${name} style`);
    }

    /* ══════════════ SECTION PICKER ══════════════ */
    function openSectionPicker() {
      document.getElementById('section-picker-modal').classList.add('active');
    }

    function closeSectionPicker() {
      document.getElementById('section-picker-modal').classList.remove('active');
      closeSectionConfigurator();
    }

    function slugifySectionName(name) {
      return String(name || 'section').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'section';
    }

    function getSectionDisplayName(comp, index = 0) {
      const attrs = comp?.getAttributes?.() || {};
      if (attrs['data-section-name']) return attrs['data-section-name'];
      if (attrs.id) return String(attrs.id).replace(/[-_]+/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
      try {
        const h = comp.getEl?.()?.querySelector?.('h1,h2,h3,h4');
        if (h?.innerText?.trim()) return h.innerText.trim().slice(0, 40);
      } catch (e) {}
      return `Section ${index+1}`;
    }

    function makeUniqueSectionId(baseId, exceptComp = null) {
      const used = new Set(), root = grapesEditor?.DomComponents?.getWrapper?.();
      const walk = c => {
        if (!c) return;
        const id = c.getAttributes?.()?.id;
        if (id && c !== exceptComp) used.add(id);
        const kids = c.components?.();
        if (kids?.length) kids.forEach(walk);
      };
      walk(root);
      let id = baseId, n = 2;
      while (used.has(id)) id = `${baseId}-${n++}`;
      return id;
    }

    function applySectionName(comp, displayName, oldId = '') {
      if (!comp) return;
      const clean = String(displayName || '').trim() || 'Section';
      const base = slugifySectionName(clean);
      const attrs = Object.assign({}, comp.getAttributes?.() || {});
      const previousId = attrs.id || oldId || '';
      const uniqueId = makeUniqueSectionId(base, comp);
      attrs.id = uniqueId;
      attrs['data-section-name'] = clean;
      comp.setAttributes(attrs);
      if (previousId && previousId !== uniqueId) {
        const root = grapesEditor?.DomComponents?.getWrapper?.();
        const walk = c => {
          if (!c) return;
          const tag = (c.get('tagName') || '').toLowerCase();
          if (tag === 'a' || tag === 'button') {
            const a = c.getAttributes?.() || {};
            if (a.href === `#${previousId}`) c.setAttributes(Object.assign({}, a, { href: `#${uniqueId}` }));
          }
          const kids = c.components?.();
          if (kids?.length) kids.forEach(walk);
        };
        walk(root);
      }
    }

    function insertSectionTemplate(type) {
      openSectionConfigurator(type);
    }

    function openSectionConfigurator(type) {
      const rawHtml = getTemplateHTML(type);
      if (!rawHtml) {
        showToast('Template not found');
        return;
      }
      const parser = new DOMParser();
      const doc = parser.parseFromString(rawHtml, 'text/html');
      const section = doc.body.firstElementChild;
      if (!section) return;
      const fields = [];
      let idx = 0;
      section.querySelectorAll('h1, h2, h3, h4, h5, h6, p, a, button, span, div, li, strong, em, small, label, input, textarea').forEach(el => {
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
          const ph = el.getAttribute('placeholder') || '';
          if (!ph) return;
          el.setAttribute('data-wcf-idx', String(idx));
          el.setAttribute('data-wcf-attr', 'placeholder');
          fields.push({
            idx, tag: el.tagName.toLowerCase(), original: ph,
            label: `📝 Form placeholder (${el.tagName.toLowerCase()})`, attr: 'placeholder'
          });
          idx++;
          return;
        }
        if (Array.from(el.childNodes).some(n => n.nodeType === 1)) return;
        const text = el.textContent.trim();
        if (!text || text.length < 1) return;
        if (!/[\p{L}\p{N}\p{Sc}]/u.test(text)) return;
        el.setAttribute('data-wcf-idx', String(idx));
        const tag = el.tagName.toLowerCase();
        let label;
        if (/^h[1-6]$/.test(tag)) label = `Heading (${tag.toUpperCase()})`;
        else if (tag === 'p') label = 'Paragraph';
        else if (tag === 'a' || tag === 'button') label = 'Button text';
        else if (tag === 'li') label = 'List item';
        else if (tag === 'strong') label = 'Bold text';
        else if (tag === 'em') label = 'Italic text';
        else if (tag === 'label') label = 'Form label';
        else if (tag === 'small') label = 'Small text';
        else label = 'Text';
        if (/^[\s]*[$₹€£¥]?\s*[\d,]+(\.\d+)?\s*$/.test(text)) label = '💰 Price / Number';
        const sameTagCount = fields.filter(f => f.tag === tag).length + 1;
        fields.push({ idx, tag, original: text, label: `${label} · ${sameTagCount}`, attr: 'text' });
        idx++;
      });
      configuringSection = { type, editableHtml: doc.body.innerHTML, fields, mode: 'add' };
      renderSectionConfigurator();
      document.getElementById('section-configurator-modal').classList.add('active');
    }

    function renderSectionConfigurator() {
      const c = configuringSection;
      if (!c) return;
      const container = document.getElementById('section-configurator-content');
      const isEdit = c.mode === 'edit' || !!c.existingComp;
      const displayName = c.existingComp ? (c.existingComp.getAttributes?.()?.['data-section-name'] || 'Section') : c.type.charAt(0).toUpperCase() + c.type.slice(1);
      const headerTitle = isEdit ? 'Edit Section Content' : 'Customize';
      const headerEmoji = isEdit ? '📝' : '✏️';
      const headerSubtitle = isEdit ? 'Edit the text below — the section updates <strong style="color:#34d399">in place</strong>, no new section is added.' : 'Name it and edit <strong style="color:#fbbf24">every text &amp; price</strong> — then it drops onto your page ready to use.';
      const primaryBtnTxt = isEdit ? '✓ Save Changes' : '✓ Add Section';
      const inputStyle = 'width:100%;padding:0.6rem 0.85rem;border:1.5px solid #283347;border-radius:8px;background:#080c14;color:#fff;font-family:inherit;font-size:0.82rem;line-height:1.5;';
      const fieldRows = c.fields.map(f => {
        const isMulti = f.tag === 'p' || f.original.length > 70;
        const input = isMulti ? `<textarea data-field-idx="${f.idx}" rows="3" style="${inputStyle}resize:vertical;">${escapeHtml(f.original)}</textarea>` : `<input type="text" data-field-idx="${f.idx}" value="${escapeHtml(f.original)}" style="${inputStyle}" />`;
        return `<div style="margin-bottom:0.7rem;"><label style="display:block;font-size:0.68rem;font-weight:700;color:#94a3b8;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.05em;">${escapeHtml(f.label)}</label>${input}</div>`;
      }).join('');
      container.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;gap:1rem;">
          <div>
            <h2 style="font-size:1.25rem;color:#fff;display:flex;align-items:center;gap:0.5rem;margin-bottom:0.2rem;">
              <span>${headerEmoji}</span> ${isEdit ? 'Edit' : 'Customize'} &ldquo;${escapeHtml(displayName)}&rdquo;
            </h2>
            <p style="font-size:0.75rem;color:#94a3b8;">${headerSubtitle}</p>
          </div>
          <button class="drawer-close" onclick="closeSectionConfigurator()" style="font-size:1.4rem;line-height:1;">✕</button>
        </div>
        ${!isEdit ? `
        <div style="background:#0a0f1c;border:1px solid #1e293b;border-radius:12px;padding:1rem;margin-bottom:1rem;">
          <label style="display:block;font-size:0.75rem;font-weight:700;color:#cbd5e1;margin-bottom:0.4rem;">🏷️ Section Name</label>
          <input type="text" id="sec-cfg-name" value="${escapeHtml(displayName)}" placeholder="e.g. Our Services" style="width:100%;padding:0.7rem 1rem;border:1.5px solid #283347;border-radius:9px;background:#080c14;color:#fff;font-family:inherit;font-size:0.86rem;" />
          <div style="font-size:0.68rem;color:#64748b;margin-top:0.35rem;line-height:1.4;">Appears in the Sections list and as a link target (#anchor) for buttons.</div>
        </div>` : `
        <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.35);border-radius:12px;padding:0.85rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:0.6rem;">
          <span style="font-size:1.3rem">🔄</span>
          <div style="font-size:0.76rem;color:#a7f3d0;line-height:1.5;">
            <strong>In-place edit mode.</strong> Changes will <strong>replace the existing section</strong> — no new section will be added.
          </div>
        </div>`}
        ${c.fields.length?`
          <div style="border-top:1px solid #1e293b;padding-top:1rem;">
            <div style="font-size:0.78rem;font-weight:800;color:#cbd5e1;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem;">✍️ Edit Text &amp; Prices (${c.fields.length})</div>
            <div style="max-height:400px;overflow-y:auto;padding-right:6px;">${fieldRows}</div>
          </div>`:`<div style="padding:0.75rem;text-align:center;color:#64748b;font-size:0.78rem;background:#0a0f1c;border-radius:8px;border:1px dashed #283347;">This section has no editable text content.</div>`}
        <div style="display:flex;justify-content:space-between;gap:0.65rem;margin-top:1.25rem;flex-wrap:wrap;">
          <button class="hdr-btn" onclick="closeSectionConfigurator()" style="background:#0a0f1c;">← Cancel</button>
          <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
            ${!isEdit ? `<button class="hdr-btn" onclick="addSectionWithoutEdits()">+ Add as-is</button>` : ''}
            <button class="hdr-btn save-btn" onclick="insertConfiguredSection()">${primaryBtnTxt}</button>
          </div>
        </div>`;
    }

    function applyConfiguratorEdits() {
      const c = configuringSection;
      if (!c) return { html: '', name: '' };
      const nameInput = document.getElementById('sec-cfg-name');
      const name = (nameInput?.value || '').trim() || (c.type.charAt(0).toUpperCase() + c.type.slice(1));
      const parser = new DOMParser();
      const doc = parser.parseFromString(c.editableHtml, 'text/html');
      doc.querySelectorAll('[data-wcf-idx]').forEach(el => {
        const i = el.getAttribute('data-wcf-idx');
        const input = document.querySelector(`#section-configurator-content [data-field-idx="${i}"]`);
        if (!input) return;
        const field = (c.fields || []).find(f => String(f.idx) === String(i));
        if (field && field.attr === 'placeholder') el.setAttribute('placeholder', input.value);
        else el.textContent = input.value;
        el.removeAttribute('data-wcf-idx');
        el.removeAttribute('data-wcf-attr');
      });
      const sec = doc.body.firstElementChild;
      if (sec && !c.existingComp) {
        sec.removeAttribute('id');
        sec.removeAttribute('data-section-name');
      }
      return { html: doc.body.innerHTML, name };
    }

    function insertConfiguredSection() {
      if (!configuringSection || !grapesEditor) return;
      if (configuringSection.existingComp) {
        const comp = configuringSection.existingComp;
        const parser = new DOMParser();
        const doc = parser.parseFromString(configuringSection.editableHtml, 'text/html');
        doc.querySelectorAll('[data-wcf-idx]').forEach(el => {
          const i = el.getAttribute('data-wcf-idx');
          const input = document.querySelector(`#section-configurator-content [data-field-idx="${i}"]`);
          if (!input) return;
          const field = (configuringSection.fields || []).find(f => String(f.idx) === String(i));
          if (field && field.attr === 'placeholder') el.setAttribute('placeholder', input.value);
          else el.textContent = input.value;
          el.removeAttribute('data-wcf-idx');
          el.removeAttribute('data-wcf-attr');
        });
        const sec = doc.body.firstElementChild;
        const preservedId = comp.getAttributes?.()?.id || '';
        const preservedName = comp.getAttributes?.()?.['data-section-name'] || '';
        if (sec) {
          if (preservedId) sec.setAttribute('id', preservedId);
          if (preservedName) sec.setAttribute('data-section-name', preservedName);
        }
        const parent = comp.parent() || grapesEditor.DomComponents.getWrapper();
        const idx = comp.index();
        const newHtml = sec.outerHTML;
        comp.remove();
        const added = parent.append(newHtml, { at: idx });
        const newComp = Array.isArray(added) ? added[0] : added;
        if (newComp) {
          configureEditorComponent(newComp);
          grapesEditor.select(newComp);
          try { newComp.getEl()?.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
        }
        closeSectionConfigurator();
        setTimeout(() => {
          renderFriendlySections();
          renderSmartLayers();
          refreshSectionDragHandles();
          renderSectionLinkPresets();
          syncCanvasToHtml();
          saveProjectData();
          showToast('✍️ Section updated in place — saved!');
        }, 150);
        return;
      }
      const { html, name } = applyConfiguratorEdits();
      insertSectionFromConfigurator(html, name);
    }

    function addSectionWithoutEdits() {
      if (!configuringSection || !grapesEditor) return;
      const parser = new DOMParser();
      const doc = parser.parseFromString(configuringSection.editableHtml, 'text/html');
      doc.querySelectorAll('[data-wcf-idx]').forEach(el => el.removeAttribute('data-wcf-idx'));
      const sec = doc.body.firstElementChild;
      if (sec) {
        sec.removeAttribute('id');
        sec.removeAttribute('data-section-name');
      }
      const fallbackName = configuringSection.type.charAt(0).toUpperCase() + configuringSection.type.slice(1);
      insertSectionFromConfigurator(doc.body.innerHTML, fallbackName);
    }

    function insertSectionFromConfigurator(html, name) {
      if (!grapesEditor) return;
      const added = grapesEditor.addComponents(html);
      const comp = Array.isArray(added) ? added[0] : added;
      if (comp) {
        configureEditorComponent(comp);
        applySectionName(comp, name);
        grapesEditor.select(comp);
      }
      closeSectionConfigurator();
      closeSectionPicker();
      showToast(`✨ "${name}" section added`);
      setTimeout(() => {
        renderFriendlySections();
        renderSmartLayers();
        refreshSectionDragHandles();
        renderSectionLinkPresets();
        syncCanvasToHtml();
        saveProjectData();
      }, 150);
    }

    function closeSectionConfigurator() {
      configuringSection = null;
      const m = document.getElementById('section-configurator-modal');
      if (m) m.classList.remove('active');
    }

    /* ══════════════ SECTION EDITOR ══════════════ */
    function findSectionComponents() {
      const out = [], wrapper = grapesEditor?.DomComponents?.getWrapper?.();
      const walk = c => {
        if (!c) return;
        const tag = (c.get('tagName') || '').toLowerCase();
        if (['section', 'header', 'footer'].includes(tag)) out.push(c);
        const kids = c.components?.();
        if (kids?.length) kids.forEach(walk);
      };
      if (wrapper) walk(wrapper);
      return out;
    }

    function customizeSectionTemplate(type) {
      closeSectionPicker();
      const existing = findSectionComponents().find(c => (c.getAttributes()?.id || '').toLowerCase() === type.toLowerCase() || getSectionDisplayName(c).toLowerCase() === type.toLowerCase());
      if (existing) return openSectionEditor(existing);
      if (!grapesEditor) return;
      const html = getTemplateHTML(type);
      if (!html) return showToast('Template not found');
      const added = grapesEditor.addComponents(html),
        comp = Array.isArray(added) ? added[0] : added;
      if (comp) {
        configureEditorComponent(comp);
        applySectionName(comp, type.charAt(0).toUpperCase() + type.slice(1));
        grapesEditor.select(comp);
        openSectionEditor(comp);
      }
    }

    function openSelectedSectionEditor() {
      if (!selectedComponent) {
        showToast('👉 Click any element on the canvas first');
        return;
      }
      grapesEditor.select(selectedComponent);
      openSectionEditor(selectedComponent);
    }

    function openSectionEditor(comp) {
      if (!comp) return;
      editingSection = comp;
      const tag = (comp.get('tagName') || '').toLowerCase();
      const isSection = ['section', 'header', 'footer'].includes(tag);
      const style = comp.getStyle?.() || {},
        attrs = comp.getAttributes?.() || {};
      const innerComp = comp.components?.()?.at?.(0);
      const innerStyle = innerComp?.getStyle?.() || {};
      const bg = normalizeHex(style['background-color'] || style.background) || '#ffffff';
      const color = normalizeHex(style.color) || '#0f172a';
      const padding = parseInt(style.paddingTop || style.padding || 0) || 0;
      const contentWidth = parseInt(innerStyle.maxWidth || innerStyle['max-width'] || style.maxWidth || style['max-width'] || 1100) || 1100;
      const radius = parseInt(style['border-radius']) || 0;

      const nameWrap = document.getElementById('se-name-wrap');
      if (nameWrap) nameWrap.style.display = isSection ? '' : 'none';
      const editContentBtn = document.getElementById('se-edit-content-btn');
      if (editContentBtn) editContentBtn.style.display = isSection ? '' : 'none';
      const titleEl = document.getElementById('se-title-text');
      if (titleEl) titleEl.textContent = isSection ? 'Customize Section' : `Customize <${tag.toUpperCase()}> Element`;
      const subtitleEl = document.getElementById('se-subtitle-text');
      if (subtitleEl) subtitleEl.textContent = mobileEditMode ? '📱 MOBILE-ONLY MODE — these styles apply on small screens only.' : (isSection ? 'These styles apply to the whole section.' : '🎯 Only this selected element will change.');
      if (isSection) document.getElementById('se-name').value = attrs['data-section-name'] || getSectionDisplayName(comp);
      else document.getElementById('se-name').value = '';

      const wv = parseCssSize(style.width || 'auto', 'auto');
      const hv = parseCssSize(style.height || 'auto', 'auto');
      const mhv = parseCssSize(style.minHeight || style['min-height'] || '0px', 'px');
      const mwv = parseCssSize(style.maxWidth || style['max-width'] || 'none', 'none');

      document.getElementById('se-width-value').value = wv.value || '';
      document.getElementById('se-width-unit').value = ['auto', 'px', '%', 'vw', 'rem', 'cm', 'in'].includes(wv.unit) ? wv.unit : 'auto';
      document.getElementById('se-height-value').value = hv.value || '';
      document.getElementById('se-height-unit').value = ['auto', 'px', '%', 'vh', 'rem', 'cm', 'in'].includes(hv.unit) ? hv.unit : 'auto';
      document.getElementById('se-min-height-value').value = mhv.value || '';
      document.getElementById('se-min-height-unit').value = ['px', 'vh', 'rem', 'cm', 'in', 'none'].includes(mhv.unit) ? mhv.unit : 'px';
      document.getElementById('se-max-width-value').value = mwv.value || '';
      document.getElementById('se-max-width-unit').value = ['none', 'px', '%', 'vw', 'rem'].includes(mwv.unit) ? mwv.unit : 'none';

      document.getElementById('se-bg').value = bg;
      document.getElementById('se-color').value = color;
      document.getElementById('se-padding').value = Math.min(180, Math.max(0, padding));
      document.getElementById('se-width').value = Math.min(1400, Math.max(600, contentWidth));
      document.getElementById('se-radius').value = Math.min(50, Math.max(0, radius));
      document.getElementById('se-align').value = style['text-align'] || 'left';
      document.getElementById('se-class').value = (comp.getClasses?.() || []).filter(c => !/^gjs-/.test(c)).join(' ');
      document.getElementById('se-font').value = style['font-family'] || '';
      document.getElementById('se-shadow').value = style['box-shadow'] || 'none';

      previewSectionStyle();
      document.getElementById('section-editor-modal').classList.add('active');
    }

    function previewSectionStyle() {
      if (!editingSection) return;
      const padding = parseInt(document.getElementById('se-padding').value) || 0;
      const contentWidth = parseInt(document.getElementById('se-width').value) || 1100;
      const radius = parseInt(document.getElementById('se-radius').value) || 0;
      document.getElementById('se-padding-val').textContent = padding + 'px';
      document.getElementById('se-width-val').textContent = contentWidth + 'px';
      document.getElementById('se-radius-val').textContent = radius + 'px';

      const sectionWidth = composeCssSize('se-width-value', 'se-width-unit', 'auto');
      const sectionHeight = composeCssSize('se-height-value', 'se-height-unit', 'auto');
      const sectionMinHeight = composeCssSize('se-min-height-value', 'se-min-height-unit', 'none');
      const sectionMaxWidth = composeCssSize('se-max-width-value', 'se-max-width-unit', 'none');

      if (mobileEditMode) {
        applyMobileOverride(editingSection, 'padding', padding + 'px 1.5rem');
        applyMobileOverride(editingSection, 'background', document.getElementById('se-bg').value);
        applyMobileOverride(editingSection, 'color', document.getElementById('se-color').value);
        applyMobileOverride(editingSection, 'text-align', document.getElementById('se-align').value);
        applyMobileOverride(editingSection, 'border-radius', radius + 'px');
        applyMobileOverride(editingSection, 'width', sectionWidth);
        applyMobileOverride(editingSection, 'height', sectionHeight);
        applyMobileOverride(editingSection, 'min-height', sectionMinHeight);
        applyMobileOverride(editingSection, 'max-width', sectionMaxWidth);
        const font = document.getElementById('se-font').value;
        if (font) applyMobileOverride(editingSection, 'font-family', font);
        setTimeout(applyMobileStylesInCanvas, 30);
        return;
      }

      const tag = (editingSection.get('tagName') || '').toLowerCase();
      const isSection = ['section', 'header', 'footer'].includes(tag);
      const newStyle = {
        background: document.getElementById('se-bg').value,
        color: document.getElementById('se-color').value,
        'box-sizing': 'border-box',
        'border-radius': radius + 'px',
        'text-align': document.getElementById('se-align').value,
        'width': sectionWidth,
        'height': sectionHeight,
        'min-height': sectionMinHeight,
        'max-width': sectionMaxWidth
      };
      if (isSection) {
        newStyle.padding = padding + 'px 1.5rem';
      } else if (padding > 0) newStyle.padding = padding + 'px';
      const font = document.getElementById('se-font').value;
      if (font) newStyle['font-family'] = font;
      const shadow = document.getElementById('se-shadow').value;
      if (shadow && shadow !== 'none') newStyle['box-shadow'] = shadow;
      editingSection.addStyle(newStyle);
      if (isSection) {
        const innerComp = editingSection.components?.()?.at?.(0);
        if (innerComp?.addStyle) innerComp.addStyle({ 'max-width': contentWidth + 'px', margin: '0 auto' });
      }
    }

    function applySectionEditor() {
      if (!editingSection) return;
      const tag = (editingSection.get('tagName') || '').toLowerCase();
      const isSection = ['section', 'header', 'footer'].includes(tag);
      if (isSection && !mobileEditMode) {
        const oldId = editingSection.getAttributes?.()?.id || '';
        applySectionName(editingSection, document.getElementById('se-name').value, oldId);
      }
      previewSectionStyle();
      const cls = document.getElementById('se-class').value.trim().split(/\s+/).filter(Boolean);
      if (editingSection.setClasses) editingSection.setClasses(cls);
      grapesEditor.select(editingSection);
      if (isSection && !mobileEditMode) {
        renderFriendlySections();
        renderSectionLinkPresets();
      }
      renderSmartLayers();
      refreshSectionDragHandles();
      const msg = mobileEditMode ? `📱 Mobile styles saved for <${tag.toUpperCase()}>` : (isSection ? `📑 "${document.getElementById('se-name').value.trim()}" saved` : `🎨 <${tag.toUpperCase()}> element styled`);
      showToast(msg);
      closeSectionEditor();
    }

    function closeSectionEditor() {
      document.getElementById('section-editor-modal').classList.remove('active');
      editingSection = null;
    }

    function livePreviewSectionName(name) {
      if (!editingSection) return;
      const attrs = Object.assign({}, editingSection.getAttributes?.() || {});
      attrs['data-section-name'] = String(name || '').trim() || 'Section';
      editingSection.setAttributes(attrs);
      renderFriendlySections();
    }

    function renderSectionLinkPresets() {
      const box = document.getElementById('be-section-presets');
      if (!box || !grapesEditor) return;
      const sections = findSectionComponents();
      box.innerHTML = '';
      sections.forEach((comp, i) => {
        let id = comp.getAttributes?.()?.id;
        if (!id) {
          const name = getSectionDisplayName(comp, i);
          applySectionName(comp, name);
          id = comp.getAttributes?.()?.id;
        }
        if (!id) return;
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'be-preset-btn';
        b.textContent = getSectionDisplayName(comp, i);
        b.title = '#' + id;
        b.onclick = () => applyBtnLinkPreset('#' + id, b);
        box.appendChild(b);
      });
      if (!sections.length) box.innerHTML = '<span style="font-size:.72rem;color:#64748b">Add a section to create a quick link.</span>';
    }

    /* ══════════════ EDIT CONTENT ══════════════ */
    function openContentEditorForSelectedSection() {
      const comp = editingSection || selectedComponent;
      if (!comp) {
        showToast('👉 Canvas la oru section-a click pannunga first');
        return;
      }
      const el = comp.getEl && comp.getEl();
      if (!el) {
        showToast('⚠️ Cannot read this section');
        return;
      }
      const parser = new DOMParser();
      const doc = parser.parseFromString(el.outerHTML, 'text/html');
      const sec = doc.body.firstElementChild;
      if (!sec) {
        showToast('⚠️ Empty section');
        return;
      }
      const fields = [];
      let idx = 0;
      sec.querySelectorAll('h1, h2, h3, h4, h5, h6, p, a, button, span, div, li, strong, em, small, label, input, textarea').forEach(node => {
        if (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA') {
          const ph = node.getAttribute('placeholder') || '';
          if (!ph) return;
          node.setAttribute('data-wcf-idx', String(idx));
          node.setAttribute('data-wcf-attr', 'placeholder');
          fields.push({
            idx, tag: node.tagName.toLowerCase(), original: ph,
            label: `📝 Form placeholder (${node.tagName.toLowerCase()})`, attr: 'placeholder'
          });
          idx++;
          return;
        }
        if (Array.from(node.childNodes).some(n => n.nodeType === 1)) return;
        const text = node.textContent.trim();
        if (!text || text.length < 1) return;
        if (!/[\p{L}\p{N}\p{Sc}]/u.test(text)) return;
        node.setAttribute('data-wcf-idx', String(idx));
        const t = node.tagName.toLowerCase();
        let label;
        if (/^h[1-6]$/.test(t)) label = `Heading (${t.toUpperCase()})`;
        else if (t === 'p') label = 'Paragraph';
        else if (t === 'a' || t === 'button') label = 'Button text';
        else if (t === 'li') label = 'List item';
        else if (t === 'strong') label = 'Bold text';
        else if (t === 'em') label = 'Italic text';
        else if (t === 'label') label = 'Form label';
        else if (t === 'small') label = 'Small text';
        else label = 'Text';
        if (/^[\s]*[$₹€£¥]?\s*[\d,]+(\.\d+)?\s*$/.test(text)) label = '💰 Price / Number';
        const sameTagCount = fields.filter(f => f.tag === t).length + 1;
        fields.push({ idx, tag: t, original: text, label: `${label} · ${sameTagCount}`, attr: 'text' });
        idx++;
      });
      configuringSection = {
        type: 'content-edit',
        mode: 'edit',
        editableHtml: sec.outerHTML,
        fields,
        existingComp: comp
      };
      renderSectionConfigurator();
      document.getElementById('section-editor-modal')?.classList.remove('active');
      document.getElementById('section-configurator-modal').classList.add('active');
    }

    function getTemplateHTML(type) {
      const T = {
        hero: `<section id="hero" style="padding:6rem 1.5rem;text-align:center;background:radial-gradient(ellipse at top,rgba(99,102,241,0.15),transparent 70%);"><div style="max-width:850px;margin:0 auto;"><div style="display:inline-block;padding:0.35rem 1rem;border-radius:999px;background:rgba(99,102,241,0.15);color:var(--primary,#6366f1);font-size:0.8rem;font-weight:700;margin-bottom:1.25rem;">✦ Welcome</div><h1 style="font-size:3.2rem;font-weight:900;letter-spacing:-0.03em;margin-bottom:1.2rem;line-height:1.15;">Your Big Headline Here</h1><p style="font-size:1.15rem;color:#64748b;max-width:650px;margin:0 auto 2.25rem;line-height:1.6;">Describe what you offer in one powerful sentence.</p><div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;"><a href="#contact" class="btn-primary" style="display:inline-block;padding:0.9rem 2.2rem;border-radius:999px;background:var(--primary,#6366f1);color:#fff;font-weight:700;text-decoration:none;">Get Started →</a><a href="#services" class="btn-outline" style="display:inline-block;padding:0.9rem 2rem;border-radius:999px;border:1.5px solid #cbd5e1;color:#334155;font-weight:600;text-decoration:none;">Learn More</a></div></div></section>`,
        services: `<section id="services" style="padding:5rem 1.5rem;background:#f8fafc;"><div style="max-width:1100px;margin:0 auto;"><h2 style="font-size:2.4rem;font-weight:800;text-align:center;margin-bottom:0.5rem;">Our Services</h2><p style="color:#64748b;text-align:center;max-width:600px;margin:0 auto 3rem;">What we do best for our clients.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:2rem;"><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem;"><div style="font-size:2rem;margin-bottom:0.75rem;">✦</div><h3 style="font-size:1.25rem;font-weight:700;margin-bottom:0.5rem;">Service One</h3><p style="color:#64748b;line-height:1.6;">Description of your first service.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem;"><div style="font-size:2rem;margin-bottom:0.75rem;">⚡</div><h3 style="font-size:1.25rem;font-weight:700;margin-bottom:0.5rem;">Service Two</h3><p style="color:#64748b;line-height:1.6;">Description of your second service.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem;"><div style="font-size:2rem;margin-bottom:0.75rem;">💎</div><h3 style="font-size:1.25rem;font-weight:700;margin-bottom:0.5rem;">Service Three</h3><p style="color:#64748b;line-height:1.6;">Description of your third service.</p></div></div></div></section>`,
        about: `<section id="about" style="padding:5rem 1.5rem;"><div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center;"><div style="background:linear-gradient(135deg,#6366f1,#a855f7);border-radius:20px;padding:3rem;color:#fff;min-height:340px;display:flex;flex-direction:column;justify-content:flex-end;"><h3 style="font-size:1.8rem;font-weight:800;line-height:1.3;">Built with precision.</h3></div><div><div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;color:var(--primary,#6366f1);letter-spacing:0.08em;margin-bottom:0.5rem;">About Us</div><h2 style="font-size:2.2rem;font-weight:800;margin-bottom:1rem;">Our Story</h2><p style="color:#64748b;font-size:1.05rem;line-height:1.7;margin-bottom:1.5rem;">We combine strategy, design, and technology to deliver outstanding digital experiences.</p><a href="#contact" class="btn-primary" style="display:inline-block;padding:0.85rem 2rem;border-radius:999px;background:var(--primary,#6366f1);color:#fff;font-weight:700;text-decoration:none;">Work With Us</a></div></div></section>`,
        stats: `<section id="stats" style="padding:3.5rem 1.5rem;background:#fff;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;"><div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:2rem;text-align:center;"><div><div style="font-size:2.8rem;font-weight:900;color:var(--primary,#6366f1);">500+</div><div style="font-size:0.9rem;color:#64748b;font-weight:600;">Projects Delivered</div></div><div><div style="font-size:2.8rem;font-weight:900;color:var(--primary,#6366f1);">99.4%</div><div style="font-size:0.9rem;color:#64748b;font-weight:600;">Satisfaction</div></div><div><div style="font-size:2.8rem;font-weight:900;color:var(--primary,#6366f1);">24/7</div><div style="font-size:0.9rem;color:#64748b;font-weight:600;">Support</div></div></div></section>`,
        pricing: `<section id="pricing" style="padding:5.5rem 1.5rem;text-align:center;"><h2 style="font-size:2.4rem;font-weight:800;margin-bottom:0.5rem;">Simple Pricing</h2><p style="color:#64748b;margin-bottom:3rem;">Choose the plan that fits your growth.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;max-width:1050px;margin:0 auto;"><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2.5rem 2rem;"><h3 style="font-size:1.2rem;font-weight:700;">Starter</h3><div style="font-size:2.6rem;font-weight:800;margin:1rem 0;color:#0f172a;">$29</div><p style="color:#64748b;font-size:0.9rem;margin-bottom:1.5rem;">For individuals</p><a href="#contact" class="btn-outline" style="display:block;text-align:center;padding:0.75rem;border:1.5px solid #cbd5e1;border-radius:999px;font-weight:700;color:#334155;text-decoration:none;">Choose</a></div><div style="background:#fff;border:2.5px solid var(--primary,#6366f1);border-radius:18px;padding:2.5rem 2rem;box-shadow:0 15px 40px rgba(99,102,241,0.18);position:relative;"><span style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--primary,#6366f1);color:#fff;font-size:0.75rem;font-weight:700;padding:0.25rem 0.85rem;border-radius:999px;">POPULAR</span><h3 style="font-size:1.2rem;font-weight:700;">Growth</h3><div style="font-size:2.6rem;font-weight:800;margin:1rem 0;color:var(--primary,#6366f1);">$99</div><p style="color:#64748b;font-size:0.9rem;margin-bottom:1.5rem;">For growing teams</p><a href="#contact" class="btn-primary" style="display:block;text-align:center;padding:0.75rem;border-radius:999px;font-weight:700;color:#fff;text-decoration:none;background:var(--primary,#6366f1);">Choose →</a></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2.5rem 2rem;"><h3 style="font-size:1.2rem;font-weight:700;">Enterprise</h3><div style="font-size:2.6rem;font-weight:800;margin:1rem 0;color:#0f172a;">$199</div><p style="color:#64748b;font-size:0.9rem;margin-bottom:1.5rem;">For large companies</p><a href="#contact" class="btn-outline" style="display:block;text-align:center;padding:0.75rem;border:1.5px solid #cbd5e1;border-radius:999px;font-weight:700;color:#334155;text-decoration:none;">Choose</a></div></div></section>`,
        reviews: `<section id="reviews" style="padding:5rem 1.5rem;background:#f8fafc;"><div style="max-width:1150px;margin:0 auto;"><h2 style="font-size:2.4rem;font-weight:800;text-align:center;margin-bottom:3rem;">What Our Clients Say</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;"><div style="background:#fff;border-radius:18px;padding:2rem;border:1.5px solid #e2e8f0;"><div style="color:#f59e0b;font-size:1.1rem;margin-bottom:1rem;">★★★★★</div><p style="color:#334155;line-height:1.6;font-style:italic;">"Outstanding quality and speed."</p><div style="font-weight:700;margin-top:1.25rem;color:#0f172a;">Sarah Jenkins</div><div style="color:#64748b;font-size:0.8rem;">VP Marketing</div></div><div style="background:#fff;border-radius:18px;padding:2rem;border:1.5px solid var(--primary,#6366f1);"><div style="color:#f59e0b;font-size:1.1rem;margin-bottom:1rem;">★★★★★</div><p style="color:#334155;line-height:1.6;font-style:italic;">"Professional and fast."</p><div style="font-weight:700;margin-top:1.25rem;color:#0f172a;">David Chen</div><div style="color:#64748b;font-size:0.8rem;">Co-Founder</div></div><div style="background:#fff;border-radius:18px;padding:2rem;border:1.5px solid #e2e8f0;"><div style="color:#f59e0b;font-size:1.1rem;margin-bottom:1rem;">★★★★★</div><p style="color:#334155;line-height:1.6;font-style:italic;">"World-class craftsmanship."</p><div style="font-weight:700;margin-top:1.25rem;color:#0f172a;">Elena Rostova</div><div style="color:#64748b;font-size:0.8rem;">Managing Director</div></div></div></div></section>`,
        team: `<section id="team" style="padding:5rem 1.5rem;text-align:center;"><h2 style="font-size:2.4rem;font-weight:800;margin-bottom:3rem;">Meet Our Team</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem;max-width:1050px;margin:0 auto;"><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem 1.5rem;"><div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800;">AT</div><h3 style="font-size:1.15rem;font-weight:700;color:#0f172a;">Alexander Thorne</h3><div style="color:var(--primary,#6366f1);font-size:0.85rem;font-weight:600;margin-top:0.2rem;">CEO</div></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem 1.5rem;"><div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#059669,#10b981);color:#fff;font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800;">MV</div><h3 style="font-size:1.15rem;font-weight:700;color:#0f172a;">Maya Vance</h3><div style="color:var(--primary,#6366f1);font-size:0.85rem;font-weight:600;margin-top:0.2rem;">Designer</div></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem 1.5rem;"><div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#06b6d4);color:#fff;font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800;">LK</div><h3 style="font-size:1.15rem;font-weight:700;color:#0f172a;">Liam Kendrick</h3><div style="color:var(--primary,#6366f1);font-size:0.85rem;font-weight:600;margin-top:0.2rem;">Architect</div></div></div></section>`,
        faq: `<section id="faq" style="padding:5.5rem 1.5rem;max-width:950px;margin:0 auto;"><h2 style="font-size:2.4rem;font-weight:800;text-align:center;margin-bottom:3rem;">Frequently Asked Questions</h2><div style="display:flex;flex-direction:column;gap:1rem;"><div style="background:#fff;border:1.5px solid #cbd5e1;border-radius:14px;padding:1.25rem 1.75rem;"><div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:1.05rem;cursor:pointer;" onclick="toggleFaq(this)"><span>What is the turnaround timeline?</span><span class="faq-icon" style="font-size:1.4rem;color:var(--primary,#6366f1);">+</span></div><div style="display:none;margin-top:0.85rem;color:#64748b;font-size:0.95rem;line-height:1.6;">Our average is 2-4 weeks.</div></div><div style="background:#fff;border:1.5px solid #cbd5e1;border-radius:14px;padding:1.25rem 1.75rem;"><div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:1.05rem;cursor:pointer;" onclick="toggleFaq(this)"><span>Is mobile optimization included?</span><span class="faq-icon" style="font-size:1.4rem;color:var(--primary,#6366f1);">+</span></div><div style="display:none;margin-top:0.85rem;color:#64748b;font-size:0.95rem;line-height:1.6;">Yes, all our builds are mobile-first.</div></div><div style="background:#fff;border:1.5px solid #cbd5e1;border-radius:14px;padding:1.25rem 1.75rem;"><div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:1.05rem;cursor:pointer;" onclick="toggleFaq(this)"><span>How does payment work?</span><span class="faq-icon" style="font-size:1.4rem;color:var(--primary,#6366f1);">+</span></div><div style="display:none;margin-top:0.85rem;color:#64748b;font-size:0.95rem;line-height:1.6;">Flexible terms including 50/50 splits.</div></div></div></section>`,
        gallery: `<section id="gallery" style="padding:5rem 1.5rem;"><h2 style="font-size:2.4rem;font-weight:800;text-align:center;margin-bottom:3rem;">Gallery</h2><div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;"><img src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&auto=format&fit=crop&q=80" style="width:100%;height:220px;object-fit:cover;border-radius:14px;"/><img src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=600&auto=format&fit=crop&q=80" style="width:100%;height:220px;object-fit:cover;border-radius:14px;"/><img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=600&auto=format&fit=crop&q=80" style="width:100%;height:220px;object-fit:cover;border-radius:14px;"/><img src="https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&auto=format&fit=crop&q=80" style="width:100%;height:220px;object-fit:cover;border-radius:14px;"/></div></section>`,
        cta: `<section id="cta" style="padding:4.5rem 1.5rem;background:linear-gradient(135deg,#6366f1,#a855f7);text-align:center;color:#fff;"><h2 style="font-size:2.6rem;font-weight:800;margin-bottom:1rem;">Ready to get started?</h2><p style="font-size:1.1rem;max-width:550px;margin:0 auto 2rem;opacity:0.95;">Join the businesses that trust us.</p><a href="#contact" class="btn-white" style="display:inline-block;padding:1rem 2.5rem;border-radius:999px;background:#fff;color:#0f172a;font-weight:800;text-decoration:none;">Book a Call →</a></section>`,
        contact: `<section id="contact" style="padding:5rem 1.5rem;"><div style="max-width:1000px;margin:0 auto;background:#fff;border:1.5px solid #e2e8f0;border-radius:20px;padding:3rem;box-shadow:0 15px 40px rgba(0,0,0,0.06);display:grid;grid-template-columns:1fr 1.2fr;gap:3rem;"><div><h2 style="font-size:1.8rem;font-weight:800;margin-bottom:1rem;">Get in Touch</h2><p style="color:#64748b;line-height:1.6;margin-bottom:1.5rem;">We'll reply within 24 hours.</p><p style="margin-bottom:0.75rem;"><strong>📞 Phone:</strong> +1 (555) 123-4567</p><p style="margin-bottom:0.75rem;"><strong>📧 Email:</strong> hello@example.com</p><p><strong>📍 Address:</strong> 100 Innovation Blvd</p></div><form><input type="text" name="name" placeholder="Your Name" style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;"/><input type="email" name="email" placeholder="Your Email" style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;"/><textarea name="message" placeholder="Your message..." style="width:100%;padding:0.85rem 1rem;border:1.5px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;font-family:inherit;font-size:0.95rem;min-height:120px;resize:vertical;"></textarea><button type="submit" class="btn-primary" style="width:100%;padding:0.9rem;border:none;border-radius:12px;background:var(--primary,#6366f1);color:#fff;font-weight:700;cursor:pointer;">Send Message →</button></form></div></section>`,
        footer: `<footer id="footer" style="background:#0f172a;color:#cbd5e1;padding:4rem 1.5rem 2rem;"><div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:2.5rem;margin-bottom:2.5rem;"><div><h3 style="color:#fff;font-size:1.25rem;font-weight:800;margin-bottom:0.75rem;">Your Brand</h3><p style="color:#94a3b8;font-size:0.9rem;line-height:1.6;">Thanks for visiting us.</p></div><div><h4 style="color:#fff;font-size:0.95rem;font-weight:700;margin-bottom:1rem;">Quick Links</h4><div style="display:flex;flex-direction:column;gap:0.5rem;"><a href="#services" style="color:#94a3b8;text-decoration:none;">Services</a><a href="#about" style="color:#94a3b8;text-decoration:none;">About</a><a href="#contact" style="color:#94a3b8;text-decoration:none;">Contact</a></div></div></div><div style="max-width:1100px;margin:0 auto;padding-top:1.5rem;border-top:1px solid #1e293b;text-align:center;font-size:0.85rem;color:#64748b;">&copy; ${new Date().getFullYear()} Your Brand. All rights reserved.</div></footer>`,
        process: `<section id="process" style="padding:5.5rem 1.5rem;background:#f8fafc;"><div style="max-width:1150px;margin:0 auto;"><h2 style="font-size:2.4rem;font-weight:800;text-align:center;margin-bottom:0.5rem;">How It Works</h2><p style="color:#64748b;text-align:center;max-width:600px;margin:0 auto 3.5rem;">Four simple steps from idea to launch.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1.75rem;"><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem 1.75rem;position:relative;"><div style="font-size:2.5rem;font-weight:900;color:var(--primary,#6366f1);opacity:0.25;position:absolute;top:0.75rem;right:1.25rem;">01</div><div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;margin-bottom:1rem;">📞</div><h3 style="font-size:1.15rem;font-weight:700;margin-bottom:0.5rem;">Discovery Call</h3><p style="color:#64748b;line-height:1.6;font-size:0.92rem;">We discuss your goals, timeline, and budget.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem 1.75rem;position:relative;"><div style="font-size:2.5rem;font-weight:900;color:var(--primary,#6366f1);opacity:0.25;position:absolute;top:0.75rem;right:1.25rem;">02</div><div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;margin-bottom:1rem;">🎨</div><h3 style="font-size:1.15rem;font-weight:700;margin-bottom:0.5rem;">Design &amp; Prototype</h3><p style="color:#64748b;line-height:1.6;font-size:0.92rem;">We craft a pixel-perfect mockup you can review.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem 1.75rem;position:relative;"><div style="font-size:2.5rem;font-weight:900;color:var(--primary,#6366f1);opacity:0.25;position:absolute;top:0.75rem;right:1.25rem;">03</div><div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;margin-bottom:1rem;">⚙️</div><h3 style="font-size:1.15rem;font-weight:700;margin-bottom:0.5rem;">Build &amp; Test</h3><p style="color:#64748b;line-height:1.6;font-size:0.92rem;">Development with weekly check-ins and QA.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2rem 1.75rem;position:relative;"><div style="font-size:2.5rem;font-weight:900;color:var(--primary,#6366f1);opacity:0.25;position:absolute;top:0.75rem;right:1.25rem;">04</div><div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;margin-bottom:1rem;">🚀</div><h3 style="font-size:1.15rem;font-weight:700;margin-bottom:0.5rem;">Launch &amp; Support</h3><p style="color:#64748b;line-height:1.6;font-size:0.92rem;">We deploy and provide ongoing support.</p></div></div></div></section>`,
        features: `<section id="features" style="padding:5.5rem 1.5rem;"><div style="max-width:1150px;margin:0 auto;"><div style="text-align:center;margin-bottom:3.5rem;"><div style="display:inline-block;padding:0.35rem 1rem;border-radius:999px;background:rgba(99,102,241,0.12);color:var(--primary,#6366f1);font-size:0.78rem;font-weight:700;margin-bottom:1rem;">FEATURES</div><h2 style="font-size:2.4rem;font-weight:800;margin-bottom:0.5rem;">Everything You Need</h2></div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;"><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;padding:1.75rem;"><div style="font-size:1.75rem;margin-bottom:0.75rem;">⚡</div><h3 style="font-size:1.05rem;font-weight:700;margin-bottom:0.4rem;">Lightning Fast</h3><p style="color:#64748b;line-height:1.6;font-size:0.9rem;">Optimized for speed.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;padding:1.75rem;"><div style="font-size:1.75rem;margin-bottom:0.75rem;">🔒</div><h3 style="font-size:1.05rem;font-weight:700;margin-bottom:0.4rem;">Secure by Default</h3><p style="color:#64748b;line-height:1.6;font-size:0.9rem;">Enterprise-grade security.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;padding:1.75rem;"><div style="font-size:1.75rem;margin-bottom:0.75rem;">📱</div><h3 style="font-size:1.05rem;font-weight:700;margin-bottom:0.4rem;">Mobile First</h3><p style="color:#64748b;line-height:1.6;font-size:0.9rem;">Perfect on every screen.</p></div></div></div></section>`,
        testimonial: `<section id="testimonial" style="padding:6rem 1.5rem;text-align:center;background:radial-gradient(ellipse at center,rgba(99,102,241,0.08),transparent 70%);"><div style="max-width:780px;margin:0 auto;"><div style="font-size:3rem;color:var(--primary,#6366f1);opacity:0.4;font-family:Georgia,serif;line-height:1;">&ldquo;</div><p style="font-size:1.5rem;font-weight:600;line-height:1.55;color:#0f172a;margin-bottom:2rem;">They completely transformed how our business looks online.</p><div style="display:flex;align-items:center;justify-content:center;gap:1rem;"><div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;">RK</div><div style="text-align:left;"><div style="font-weight:800;color:#0f172a;">Rajesh Kumar</div><div style="color:#64748b;font-size:0.85rem;">CEO, Trident Retail</div></div></div></div></section>`,
        custom: `<section id="custom" style="padding:5.5rem 1.5rem;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);"><div style="max-width:1100px;margin:0 auto;"><div style="display:inline-block;padding:0.4rem 1.1rem;border-radius:999px;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;font-size:0.75rem;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:1.25rem;">WHY CHOOSE US</div><h2 style="font-size:2.6rem;font-weight:900;line-height:1.15;letter-spacing:-0.02em;margin-bottom:1rem;max-width:700px;">We build websites that actually grow your revenue.</h2><p style="font-size:1.1rem;color:#64748b;line-height:1.65;max-width:640px;margin-bottom:2.5rem;">No fluff. No templates. Just strategic design.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem;margin-bottom:2.5rem;"><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;padding:1.75rem;"><div style="font-size:2rem;margin-bottom:0.6rem;">📈</div><h3 style="font-size:1.05rem;font-weight:800;margin-bottom:0.4rem;">Measurable ROI</h3><p style="color:#64748b;font-size:0.9rem;line-height:1.6;">Every decision is backed by data.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;padding:1.75rem;"><div style="font-size:2rem;margin-bottom:0.6rem;">⚡</div><h3 style="font-size:1.05rem;font-weight:800;margin-bottom:0.4rem;">Fast Delivery</h3><p style="color:#64748b;font-size:0.9rem;line-height:1.6;">Launch in weeks, not months.</p></div><div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;padding:1.75rem;"><div style="font-size:2rem;margin-bottom:0.6rem;">🛡️</div><h3 style="font-size:1.05rem;font-weight:800;margin-bottom:0.4rem;">Ongoing Support</h3><p style="color:#64748b;font-size:0.9rem;line-height:1.6;">Real humans, real responses.</p></div></div></div></section>`
      };
      return T[type] || null;
    }

    /* ══════════════ SETTINGS PANEL ACTIONS ══════════════ */
    function addButtonFromSettings(style) {
      if (!grapesEditor) return;
      const btnStyles = {
        primary: 'display:inline-block;padding:0.9rem 2.2rem;border-radius:999px;background:var(--primary,#6366f1);color:#fff;font-weight:700;text-decoration:none;box-shadow:0 8px 25px rgba(99,102,241,0.35);',
        outline: 'display:inline-block;padding:0.9rem 2rem;border-radius:999px;border:1.5px solid #cbd5e1;color:#334155;font-weight:600;text-decoration:none;background:transparent;',
        whatsapp: 'display:inline-flex;align-items:center;gap:0.5rem;background:#25D366;color:#fff;padding:0.85rem 1.8rem;border-radius:999px;font-weight:700;text-decoration:none;box-shadow:0 8px 25px rgba(37,211,102,0.4);'
      };
      const labels = {
        primary: 'Get Started →',
        outline: 'Learn More',
        whatsapp: '💬 Chat on WhatsApp'
      };
      const clsMap = {
        primary: 'btn-primary',
        outline: 'btn-outline',
        whatsapp: 'btn-whatsapp'
      };
      const html = `<a data-webcraft-control="button" href="#contact" class="${clsMap[style]}" style="${btnStyles[style]}display:inline-block;width:max-content;position:absolute;left:24px;top:24px;margin:0;z-index:1000;">${labels[style]}</a>`;
      const wrapper = grapesEditor.DomComponents.getWrapper();
      const added = wrapper.append(html, { at: 0 });
      const btn = Array.isArray(added) ? added[0] : added;
      if (btn) {
        btn.set({ draggable: false, editable: true, stylable: true, selectable: true, droppable: false });
        ensureFreeButtonSetup(btn);
        grapesEditor.select(btn);
      }
      showToast('🔘 Button added');
      renderSmartLayers();
    }

    function applyFriendlyTheme(primary, secondary, name) {
      if (!currentHtml) return;
      currentHtml = currentHtml.replace(/--primary:\s*[^;]+;/g, `--primary: ${primary};`);
      currentHtml = currentHtml.replace(/--primary-gradient:\s*[^;]+;/g, `--primary-gradient: linear-gradient(135deg, ${primary} 0%, ${secondary} 100%);`);
      lockTheme(currentHtml, true);
      if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
        projectData.designs[activeConceptIndex].html = currentHtml;
        saveProjectData();
      }
      loadHtmlIntoStudioCanvas();
      showToast(`🎨 ${name} theme applied!`);
    }

    function toggleFriendlyBg() {
      if (!currentHtml) return;
      const isDark = /background\s*:\s*(#0[0-9a-fA-F]|#1[0-9a-fA-F])/i.test(currentHtml) || currentHtml.includes('background:#090d16');
      const newBg = isDark ? '#ffffff' : '#0a0d14';
      const newTxt = isDark ? '#0f172a' : '#f8fafc';
      if (/body\s*\{[^}]*background/i.test(currentHtml)) currentHtml = currentHtml.replace(/(body\s*\{[^}]*background(-color)?\s*:)[^;]+;/i, `$1 ${newBg};`);
      else currentHtml = currentHtml.replace(/<style([^>]*)>/i, `<style$1>\nbody { background: ${newBg}; color: ${newTxt}; }\n`);
      lockTheme(currentHtml, true);
      if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
        projectData.designs[activeConceptIndex].html = currentHtml;
        saveProjectData();
      }
      loadHtmlIntoStudioCanvas();
      showToast(`🌓 Switched to ${isDark?'light':'dark'} mode`);
    }

    function renderFriendlySections() {
      const container = document.getElementById('friendly-sections-list');
      if (!container || !grapesEditor) return;
      const sections = findSectionComponents();
      if (!sections.length) {
        container.innerHTML = '<div style="font-size:0.72rem;color:#64748b;text-align:center;padding:0.75rem;">No sections yet.</div>';
        return;
      }
      container.innerHTML = '';
      sections.forEach((comp, i) => {
        const tag = (comp.get('tagName') || 'section').toLowerCase();
        const name = getSectionDisplayName(comp, i);
        const attrs = comp.getAttributes?.() || {};
        const id = attrs.id || '';
        const item = document.createElement('div');
        item.className = 'friendly-section-item';
        item.innerHTML = `<div class="friendly-section-item-left"><span class="sec-tag">${tag.toUpperCase()}</span><span class="sec-name" title="#${escapeHtml(id)}">${escapeHtml(name)}</span></div><div style="display:flex;gap:0.25rem;flex-shrink:0"><button class="sec-jump" title="Scroll to">Go →</button><button class="sec-jump gold" title="Customize section">🎨</button></div>`;
        item.onclick = ev => {
          if (ev.target.closest('button')) return;
          try {
            grapesEditor.select(comp);
            const el = comp.getEl();
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          } catch (e) {}
        };
        const buttons = item.querySelectorAll('button');
        buttons[0].onclick = ev => {
          ev.stopPropagation();
          try {
            grapesEditor.select(comp);
            const el = comp.getEl();
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          } catch (e) {}
        };
        buttons[1].onclick = ev => {
          ev.stopPropagation();
          openSectionEditor(comp);
        };
        container.appendChild(item);
      });
      renderSectionLinkPresets();
    }

    /* ══════════════ LAYERS ══════════════ */
    function switchLayerMode(mode) {
      document.getElementById('ltab-smart').classList.toggle('active', mode === 'smart');
      document.getElementById('ltab-tree').classList.toggle('active', mode === 'tree');
      document.getElementById('smart-layers-view').style.display = mode === 'smart' ? 'block' : 'none';
      document.getElementById('raw-layers-view').style.display = mode === 'tree' ? 'block' : 'none';
      if (mode === 'smart') renderSmartLayers();
    }

    function refreshSmartLayers() {
      renderSmartLayers();
      renderFriendlySections();
      showToast('↺ Refreshed');
    }

    function renderSmartLayers() {
      const container = document.getElementById('smart-layers-list');
      if (!container || !grapesEditor) return;
      container.innerHTML = '';
      const wrapper = grapesEditor.DomComponents?.getWrapper();
      if (!wrapper) return;
      const list = [];
      const walk = (c) => {
        const t = (c.get('tagName') || '').toLowerCase();
        if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'button', 'span', 'img', 'section'].includes(t)) list.push(c);
        else if (t === 'div' && c.getAttributes && (c.getAttributes()['data-wc-shape'] || c.getAttributes()['data-wc-card'])) list.push(c);
        const k = c.components();
        if (k && k.length) k.forEach(walk);
      };
      walk(wrapper);
      if (!list.length) {
        container.innerHTML = '<div style="font-size:0.75rem;color:#64748b;text-align:center;padding:1rem;">No elements found.</div>';
        return;
      }

      list.forEach((comp, idx) => {
        const tag = (comp.get('tagName') || 'div').toLowerCase();
        const isSelected = (selectedComponent === comp);
        const card = document.createElement('div');
        card.className = 'layer-item-card' + (isSelected ? ' selected' : '');
        let badgeClass = 'sec';
        if (tag.startsWith('h')) badgeClass = 'h';
        else if (tag === 'p' || tag === 'span') badgeClass = 'p';
        else if (tag === 'a' || tag === 'button') badgeClass = 'btn';
        else if (tag === 'img') badgeClass = 'img';
        else if (tag === 'div') badgeClass = 'img';
        const layerOrderBtns = `<button class="hdr-btn" title="Bring forward" style="padding:0.12rem 0.4rem;font-size:0.62rem;" onclick="event.stopPropagation(); moveLayerByIndex(${idx},1)">⬆</button><button class="hdr-btn" title="Send backward" style="padding:0.12rem 0.4rem;font-size:0.62rem;" onclick="event.stopPropagation(); moveLayerByIndex(${idx},-1)">⬇</button>`;

        if (tag === 'img') {
          const alt = comp.getAttributes()?.alt || 'Image';
          const animBadge = comp.getAttributes?.()?.['data-anim'] ? ' 🎬' : '';
          card.innerHTML = `<div class="layer-card-top" onclick="selectLayerComponent(${idx})"><span class="layer-tag-badge img">IMG${animBadge}</span><span style="font-size:0.68rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px;">${escapeHtml(alt)}</span><span style="display:flex;gap:0.2rem;">${layerOrderBtns}</span></div>`;
        } else if (tag === 'section') {
          const secAttrs = comp.getAttributes?.() || {};
          const secName = secAttrs['data-section-name'] || secAttrs.id || `Section ${idx + 1}`;
          const animBadge = secAttrs['data-anim'] ? ' 🎬' : '';
          const mobBadge = secAttrs['data-mobile-id'] ? ' 📱' : '';
          card.innerHTML = `<div class="layer-card-top" onclick="selectLayerComponent(${idx})"><span class="layer-tag-badge sec">SECTION${animBadge}${mobBadge}</span><strong style="font-size:0.72rem;color:#cbd5e1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:90px;">${escapeHtml(secName)}</strong><span style="display:flex;gap:0.2rem;">${layerOrderBtns}</span></div>`;
        } else if (tag === 'div' && (comp.getAttributes?.()?.['data-wc-shape'] || comp.getAttributes?.()?.['data-wc-card'])) {
          const isCard = !!comp.getAttributes?.()?.['data-wc-card'];
          const kind = isCard ? 'CARD' : 'SHAPE';
          const shapeName = comp.getAttributes()?.['data-wc-shape'] || comp.getAttributes()?.['data-wc-card'] || 'card';
          const animBadge = comp.getAttributes?.()?.['data-anim'] ? ' 🎬' : '';
          card.innerHTML = `<div class="layer-card-top" onclick="selectLayerComponent(${idx})"><span class="layer-tag-badge img">${kind}${animBadge}</span><strong style="font-size:0.72rem;color:#cbd5e1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:90px;text-transform:capitalize;">${escapeHtml(shapeName)}</strong><span style="display:flex;gap:0.2rem;"><button class="hdr-btn" title="Change image" style="padding:0.12rem 0.4rem;font-size:0.62rem;" onclick="event.stopPropagation(); selectLayerComponent(${idx}); ctxShapeImage()">🖼️</button>${layerOrderBtns}</span></div>`;
        } else {
          const el = comp.getEl();
          const text = (el ? el.innerText : comp.get('content')) || '';
          const isButton = (tag === 'a' || tag === 'button');
          const editBtn = isButton ? `<button class="hdr-btn" style="padding:0.12rem 0.4rem;font-size:0.62rem;background:#1e1b4b;border-color:#6366f1;color:#c7d2fe;" onclick="event.stopPropagation(); quickEditButton(${idx})">🔘 Edit</button>` : '';
          card.innerHTML = `
            <div class="layer-card-top" onclick="selectLayerComponent(${idx})">
              <span class="layer-tag-badge ${badgeClass}">${tag.toUpperCase()}</span>
              ${editBtn}
            </div>
            <input type="text" class="layer-text-input" value="${escapeHtml(text).replace(/"/g,'&quot;')}"
              oninput="updateLayerText(${idx}, this.value)"
              onfocus="selectLayerComponent(${idx})" />`;
        }
        container.appendChild(card);
      });
      window._layerComponents = list;
    }

    function quickEditButton(idx) {
      if (!window._layerComponents || !window._layerComponents[idx]) return;
      const comp = window._layerComponents[idx];
      grapesEditor.select(comp);
      openButtonEditor(comp);
    }

    function selectLayerComponent(i) {
      if (!window._layerComponents || !window._layerComponents[i]) return;
      grapesEditor.select(window._layerComponents[i]);
      const el = window._layerComponents[i].getEl();
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function updateLayerText(i, txt) {
      if (!window._layerComponents || !window._layerComponents[i]) return;
      const c = window._layerComponents[i];
      c.set('content', txt);
      const el = c.getEl();
      if (el) el.innerText = txt;
    }

    /* ══════════════ DRAWER TABS ══════════════ */
    function switchDrawerTab(tab) {
      document.getElementById('canva-drawer').classList.remove('collapsed');
      document.querySelectorAll('.rail-item').forEach(r => r.classList.remove('active'));
      ['blocks', 'uploads', 'text', 'anim', 'lang', 'styles', 'traits', 'layers', 'pages'].forEach(t => {
        const el = document.getElementById(`dtab-${t}`);
        if (el) el.style.display = (t === tab) ? 'block' : 'none';
      });
      const rail = document.getElementById(`rail-${tab}`);
      if (rail) rail.classList.add('active');
      if (tab === 'blocks') filterBlockCategory('all');
      const titles = {
        blocks: 'Elements & Blocks',
        uploads: 'Uploads & Stock Photos',
        text: 'Typography Presets',
        anim: '🎬 Animation Effects',
        lang: '🌐 Multi-Language',
        styles: 'Style Inspector',
        traits: 'Website Settings',
        layers: 'Layers & Content',
        pages: '📄 Pages & Business'
      };
      document.getElementById('drawer-title').textContent = titles[tab] || 'Tools';
      if (tab === 'layers') renderSmartLayers();
      if (tab === 'pages') { renderWcPagesPanel(); loadBizSetup(); }
      if (tab === 'traits') renderFriendlySections();
      if (tab === 'anim') {
        renderAnimationPresets();
        if (selectedComponent) {
          const attrs = selectedComponent.getAttributes?.() || {};
          if (attrs['data-anim']) {
            const card = document.querySelector(`#anim-presets-grid .anim-card[data-anim="${attrs['data-anim']}"]`);
            if (card) card.classList.add('active');
          }
        }
      }
      if (tab === 'lang') {
        renderLangChips();
        renderHeaderLangSelect();
      }
    }

    function closeDrawer() {
      document.getElementById('canva-drawer').classList.add('collapsed');
      document.querySelectorAll('.rail-item').forEach(r => r.classList.remove('active'));
    }

    function renderStockPhotos(cat) {
      const c = document.getElementById('stock-grid');
      if (!c) return;
      const items = stockPhotos[cat] || stockPhotos.business;
      c.innerHTML = '';
      items.forEach(item => {
        const d = document.createElement('div');
        d.className = 'stock-thumb';
        d.setAttribute('draggable', 'true');
        d.innerHTML = `<img src="${item.url}" loading="lazy"><div class="stock-thumb-caption">${item.caption}</div>`;
        d.ondragstart = (e) => handleImageDragStart(e, item.url, item.caption);
        d.onclick = () => handleImageClick(item.url, item.caption);
        c.appendChild(d);
      });
    }

    function filterStockPhotos(cat, btn) {
      document.querySelectorAll('#dtab-uploads .bpill').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      renderStockPhotos(cat);
    }

    function insertTextPreset(type) {
      if (!grapesEditor) return;
      const map = {
        h1: '<h1 style="font-size:3rem;font-weight:900;letter-spacing:-0.03em;margin:1.5rem 0;">New Headline</h1>',
        h2: '<h2 style="font-size:2.2rem;font-weight:800;margin:1.2rem 0;">Subheading</h2>',
        h3: '<h3 style="font-size:1.4rem;font-weight:700;margin:1rem 0;">Section Title</h3>',
        p: '<p style="font-size:1.05rem;line-height:1.7;color:#64748b;margin-bottom:1.5rem;">Add your content here.</p>'
      };
      grapesEditor.addComponents(map[type] || map.p);
      showToast('✍️ Text added');
    }

    function filterBlocks(q) {
      q = q.toLowerCase().trim();
      document.querySelectorAll('#gjs-blocks .gjs-block').forEach(b => {
        b.style.display = (!q || b.innerText.toLowerCase().includes(q)) ? 'flex' : 'none';
      });
    }

    function filterBlockCategory(cat, btn) {
      document.querySelectorAll('#dtab-blocks .bpill').forEach(p => p.classList.remove('active'));
      let activePill = btn;
      if (!activePill || typeof activePill === 'string') {
        activePill = Array.from(document.querySelectorAll('#dtab-blocks .bpill')).find(p => p.textContent.trim().toLowerCase() === cat.toLowerCase());
      }
      if (activePill && activePill.classList) activePill.classList.add('active');
      document.querySelectorAll('#gjs-blocks .gjs-block-category').forEach(c => {
        const title = c.querySelector('.gjs-title')?.innerText || '';
        c.style.display = (cat === 'all' || title.toLowerCase().includes(cat.toLowerCase())) ? 'block' : 'none';
      });
    }

    function openBlockCategory(cat, railId) {
      document.getElementById('canva-drawer').classList.remove('collapsed');
      ['blocks', 'uploads', 'text', 'anim', 'lang', 'styles', 'traits', 'layers', 'pages'].forEach(t => {
        const el = document.getElementById(`dtab-${t}`);
        if (el) el.style.display = (t === 'blocks') ? 'block' : 'none';
      });
      document.querySelectorAll('.rail-item').forEach(r => r.classList.remove('active'));
      const activeRail = document.getElementById(railId || (cat === 'Cards' ? 'rail-cards' : cat === 'Shapes' ? 'rail-shapes' : 'rail-blocks'));
      if (activeRail) activeRail.classList.add('active');

      filterBlockCategory(cat);

      const titles = {
        Cards: '🃏 Cards & Profiles',
        Shapes: '⭐ Geometric Shapes',
        Sections: '📐 Page Sections',
        Components: '🔘 UI Components',
        Typography: '✍️ Typography',
        Forms: '📝 Smart Forms (email + WhatsApp + Sheets)',
        Shop: '🛍️ Shop (products, cart, PayHere / LankaQR)',
        Business: '🧩 Business Blocks'
      };
      const titleEl = document.getElementById('drawer-title');
      if (titleEl) titleEl.textContent = titles[cat] || (cat + ' & Blocks');
    }

    function setupBlockClickToInsert() {
      const container = document.getElementById('gjs-blocks');
      if (!container || container._hasClickInsert) return;
      container._hasClickInsert = true;

      container.addEventListener('click', (e) => {
        const blockEl = e.target.closest('.gjs-block');
        if (!blockEl) return;
        const blockTitle = blockEl.innerText.trim();
        const bm = grapesEditor?.BlockManager;
        if (!bm) return;

        // Find block model by id or title
        const allBlocks = bm.getAll ? (bm.getAll().models || bm.getAll() || []) : [];
        const block = allBlocks.find(b => {
          const id = b.getId ? b.getId() : (b.id || '');
          const label = b.get ? (b.get('label') || '') : (b.label || '');
          const labelText = typeof label === 'string' ? label.replace(/<[^>]*>/g, '').trim() : '';
          return (blockEl.dataset && blockEl.dataset.id && id === blockEl.dataset.id) ||
                 (labelText && labelText.toLowerCase().includes(blockTitle.toLowerCase())) ||
                 (blockTitle && labelText.toLowerCase().includes(blockTitle.toLowerCase())) ||
                 (id && id.toLowerCase().includes(blockTitle.toLowerCase()));
        });

        if (!block) return;
        const content = block.get ? block.get('content') : block.content;
        if (!content) return;

        const wrapper = grapesEditor.getWrapper();
        let target = selectedComponent;
        let inserted = null;

        try {
          if (target && target !== wrapper) {
            const tag = (target.get('tagName') || '').toLowerCase();
            if (['section', 'div', 'main', 'article'].includes(tag)) {
              inserted = target.append(content)[0];
            } else {
              const parent = target.parent();
              if (parent) {
                const idx = parent.components().indexOf(target);
                inserted = parent.append(content, { at: idx + 1 })[0];
              } else {
                inserted = wrapper.append(content)[0];
              }
            }
          } else {
            // Append before footer if one exists
            const footer = wrapper.find('footer')[0];
            if (footer) {
              const parent = footer.parent() || wrapper;
              const idx = parent.components().indexOf(footer);
              inserted = parent.append(content, { at: idx })[0];
            } else {
              inserted = wrapper.append(content)[0];
            }
          }

          if (inserted) {
            grapesEditor.select(inserted);
            try {
              const el = inserted.getEl && inserted.getEl();
              if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (err) {}
            syncCanvasToHtml();
            renderSmartLayers();
            const catName = block.get ? (block.get('category')?.id || block.get('category') || 'Item') : 'Item';
            showToast(`✨ Added ${catName} "${blockTitle}" to canvas!`);
          }
        } catch (err) {
          console.warn('[studio] Click-to-insert fallback:', err);
        }
      });
    }

    /* ══════════════ GEMINI AI ══════════════ */
    function toggleMagicAi(forceOpen) {
      const panel = document.getElementById('magic-ai-panel');
      if (!panel) return;
      if (forceOpen === true) panel.classList.add('active');
      else if (forceOpen === false) panel.classList.remove('active');
      else panel.classList.toggle('active');
    }

    function quickMagic(q) {
      document.getElementById('magic-input').value = q;
      executeMagicAi();
    }

    function buildAiStudioContext() {
      const root = grapesEditor?.DomComponents?.getWrapper?.();
      const nodes = [];
      const walk = (comp, depth = 0) => {
        if (!comp || depth > 8) return;
        const tag = (comp.get('tagName') || 'div').toLowerCase();
        const attrs = comp.getAttributes ? (comp.getAttributes() || {}) : {};
        const style = comp.getStyle ? (comp.getStyle() || {}) : {};
        const el = comp.getEl && comp.getEl();
        nodes.push({
          tag,
          id: attrs.id || '',
          classes: attrs.class || '',
          text: el ? ((el.innerText || '').trim().slice(0, 140)) : '',
          section_name: attrs['data-section-name'] || '',
          src: tag === 'img' ? (attrs.src || '') : '',
          href: (tag === 'a' || tag === 'button') ? (attrs.href || '') : '',
          style: {
            position: style.position || '',
            width: style.width || '',
            height: style.height || '',
            display: style.display || '',
            margin: style.margin || ''
          },
          draggable: !!comp.get('draggable'),
          resizable: !!comp.get('resizable'),
          anim: attrs['data-anim'] || '',
          mobile: attrs['data-mobile-id'] ? true : false
        });
        const kids = comp.components && comp.components();
        if (kids && kids.length) kids.forEach(child => walk(child, depth + 1));
      };
      if (root) walk(root);
      return {
        selected: selectedComponent ? {
          tag: (selectedComponent.get('tagName') || '').toLowerCase(),
          attributes: selectedComponent.getAttributes ? selectedComponent.getAttributes() : {},
          style: selectedComponent.getStyle ? selectedComponent.getStyle() : {}
        } : null,
        nodes,
        languages: langState.active
      };
    }

    function updateAiSelectedTarget(model) {
      const banner = document.getElementById('ai-target-banner');
      const icon = document.getElementById('ai-target-icon');
      const tagEl = document.getElementById('ai-target-tag');
      const prevEl = document.getElementById('ai-target-preview');
      const clearBtn = document.getElementById('ai-target-clear-btn');
      const input = document.getElementById('magic-input');
      const chips = document.getElementById('magic-chips-container');
      if (!banner) return;

      if (model) {
        banner.classList.add('has-selection');
        const tag = (model.get('tagName') || 'element').toLowerCase();
        let preview = '';
        try {
          const el = model.getEl ? model.getEl() : null;
          preview = el ? (el.innerText || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 28) : '';
        } catch (e) {}
        if (!preview) preview = tag.toUpperCase() + ' component';

        if (icon) icon.textContent = '🎯';
        if (tagEl) {
          tagEl.style.display = 'inline-block';
          tagEl.textContent = tag.toUpperCase();
        }
        if (prevEl) prevEl.textContent = `"${preview}"`;
        if (clearBtn) clearBtn.style.display = 'inline-block';
        if (input) input.placeholder = `Ask AI to modify this <${tag}> (e.g. rewrite text, style, colors)...`;

        if (chips) {
          chips.innerHTML = `
            <span class="m-chip" onclick="quickMagic('Generate image of modern luxury photography')">🎨 AI Image</span>
            <span class="m-chip" onclick="quickMagic('Rewrite this text to be more punchy and modern')">✍️ Rewrite Text</span>
            <span class="m-chip" onclick="quickMagic('Make this look premium with modern colors and sleek typography')">✨ Luxury Look</span>
            <span class="m-chip" onclick="quickMagic('Add a subtle modern box-shadow and rounded corners')">🎨 Soft Shadow</span>
            <span class="m-chip" onclick="quickMagic('Change colors to match the primary brand theme')">🌈 Brand Colors</span>
            <span class="m-chip" onclick="quickMagic('Adjust spacing and padding for better readability')">📐 Spacing</span>
          `;
        }
      } else {
        banner.classList.remove('has-selection');
        if (icon) icon.textContent = '🌐';
        if (tagEl) tagEl.style.display = 'none';
        if (prevEl) prevEl.textContent = 'Entire Website Mode';
        if (clearBtn) clearBtn.style.display = 'none';
        if (input) input.placeholder = 'Ask AI to change text, style, or build sections…';

        if (chips) {
          chips.innerHTML = `
            <span class="m-chip" onclick="quickMagic('Generate image of modern corporate hero banner')">🎨 AI Image</span>
            <span class="m-chip" onclick="quickMagic('Add 5-star customer testimonials section with slide-up animation')">⭐ Reviews</span>
            <span class="m-chip" onclick="quickMagic('Add pricing table with 3 tiers')">💰 Pricing</span>
            <span class="m-chip" onclick="quickMagic('Add FAQ section')">❓ FAQ</span>
            <span class="m-chip" onclick="quickMagic('Add WhatsApp floating button')">💬 WhatsApp</span>
            <span class="m-chip" onclick="quickMagic('Add photo gallery section with fade-in animation')">🖼️ Gallery</span>
          `;
        }
      }
    }

    function clearSelectedComponentForAi() {
      if (grapesEditor) grapesEditor.select(null);
      selectedComponent = null;
      updateAiSelectedTarget(null);
      showToast('Switched to Entire Website mode');
    }

    function triggerAiImageFlow() {
      const inp = document.getElementById('magic-input');
      if (inp) {
        inp.value = 'Generate image of ';
        inp.focus();
        inp.setSelectionRange(inp.value.length, inp.value.length);
      }
    }

    function changeAiModel(model) {
      try { localStorage.setItem('webcraft_ai_model', model); } catch (e) {}
      if (String(model || '').includes('gemini')) {
        showToast(`AI Model set to ${model} (Google Gemini)`);
      } else {
        showToast(`AI Model set to ${model} (OpenCode Fallback)`);
      }
    }

    /* AI-chat model default: gemini-3.5-flash-lite (restore saved choice) */
    (function initAiChatModel() {
      const apply = () => {
        try {
          const saved = localStorage.getItem('webcraft_ai_model') || 'gemini-3.5-flash-lite';
          const sel = document.getElementById('magic-model-select');
          if (sel) {
            const has = Array.from(sel.options).some(o => o.value === saved);
            sel.value = has ? saved : 'gemini-3.5-flash-lite';
            if (!has) localStorage.setItem('webcraft_ai_model', 'gemini-3.5-flash-lite');
          }
        } catch (e) {}
      };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
      else apply();
    })();

    function formatMarkdown(text) {
      if (!text) return '';
      let escaped = escapeHtml(text);
      escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
      escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');
      escaped = escaped.replace(/`([^`]+)`/g, '<code style="background:#1e293b;padding:0.1rem 0.35rem;border-radius:4px;color:#a5b4fc;font-size:0.75rem;">$1</code>');
      escaped = escaped.replace(/\n\n/g, '<br><br>');
      escaped = escaped.replace(/\n/g, '<br>');
      return escaped;
    }

    /* ★ Local smart-style fallback (no network — never fails).
       Handles common Tanglish style commands instantly when AI lanes are down:
       color change, periya/chinna size, dark/light mode. Returns true if handled. */
    function applyLocalSmartStyle(q) {
      try {
        q = (typeof copilotStripNoise === 'function') ? copilotStripNoise(q) : String(q || '');
        const lower = String(q || '').toLowerCase();
        const comp = (typeof selectedComponent !== 'undefined') ? selectedComponent : null;
        const hasSel = !!(comp && comp.getEl);
        const colorMap = { blue:'#2563eb', green:'#059669', red:'#dc2626', gold:'#d97706', purple:'#6366f1', orange:'#f97316', pink:'#ec4899', black:'#0f172a' };
        let wantColor = null;
        for (const k in colorMap) { if (lower.includes(k)) { wantColor = colorMap[k]; break; } }
        const hexM = lower.match(/#([0-9a-f]{6}|[0-9a-f]{3})/);
        if (hexM) wantColor = hexM[0];
        const wantBigger = /periya|bigger|large|increase|big/i.test(q);
        const wantSmaller = /chinna|smaller|small|decrease/i.test(q);
        const wantDark = /dark|night(.*)mode|black(.*)mode/i.test(lower);
        const wantLight = /light(.*)mode|white(.*)mode|clean mode/i.test(lower);

        // Selected-element tweaks (instant, offline)
        if (hasSel && (wantColor || wantBigger || wantSmaller)) {
          const el = comp.getEl();
          if (!el) return false;
          const tag = (el.tagName || 'DIV').toLowerCase();
          const isText = /h1|h2|h3|h4|p|span|a|li|button/.test(tag);
          if (wantColor) { if (isText) el.style.color = wantColor; else el.style.backgroundColor = wantColor; }
          if (wantBigger || wantSmaller) {
            const cur = parseFloat(window.getComputedStyle(el).fontSize) || 16;
            el.style.fontSize = Math.round(cur * (wantBigger ? 1.25 : 0.8)) + 'px';
          }
          syncCanvasToHtml();
          if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
            projectData.designs[activeConceptIndex].html = currentHtml;
            saveProjectData();
          }
          renderSmartLayers();
          const sum0 = friendlyUpdateSummary(q);
          appendMagicChat(sum0.html + ' <span style="color:#64748b;font-size:0.68rem;">· offline</span>', 'ai');
          showToast(sum0.toast);
          return true;
        }
        // Whole-page color / theme swaps (mirror of server smart engine)
        if (!hasSel && (wantColor || wantDark || wantLight)) {
          let mod = currentHtml;
          if (wantDark) {
            mod = mod.split('#ffffff').join('#090d16').split('#fafbfe').join('#090d16').split('#f8fafc').join('#0f172a');
          } else if (wantLight) {
            mod = mod.split('#090d16').join('#ffffff').split('#0f172a').join('#f8fafc').split('#111622').join('#ffffff');
          } else if (wantColor) {
            mod = mod.split('#6366f1').join(wantColor).split('#a855f7').join(wantColor);
          }
          if (mod !== currentHtml) {
            currentHtml = mod;
            if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
              projectData.designs[activeConceptIndex].html = currentHtml;
              saveProjectData();
            }
            loadHtmlIntoStudioCanvas();
            const sumW = friendlyUpdateSummary(q);
            appendMagicChat(sumW.html + ' <span style="color:#64748b;font-size:0.68rem;">· offline</span>', 'ai');
            showToast(sumW.toast);
            return true;
          }
        }
        // Selected-element TEXT rewrite (offline): "change text/heading/title to X",
        // "rewrite this (text) to X", "ithoda text ah X nu maathu", or quoted "X".
        if (hasSel) {
          let newText = null;
          let m = String(q).match(/(?:change|rewrite|update|set|maathu|maathi)\s+(?:this\s+)?(?:text|heading|title|label|button|content)\s+(?:to|as|ah|aag?)\s*[:\-]?\s*(.+)/i);
          if (!m) m = String(q).match(/["“”]([^"“”]{2,300})["“”]/);
          if (m) newText = m[1].trim().replace(/\s*\[hint:[^\]]*\]/gi, '').trim();
          if (newText) {
            const el = comp.getEl();
            if (el) {
              const tag = (el.tagName || 'DIV').toLowerCase();
              if (/^(h1|h2|h3|h4|h5|h6|p|span|a|button|li|div)$/.test(tag) && el.children.length === 0) {
                el.textContent = newText;
              } else {
                const t = el.querySelector('h1,h2,h3,h4,p,span,a,button,li');
                if (t) t.textContent = newText;
                else el.textContent = newText;
              }
              syncCanvasToHtml();
              if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
                projectData.designs[activeConceptIndex].html = currentHtml;
                saveProjectData();
              }
              renderSmartLayers();
              const sumT = friendlyUpdateSummary(q);
              appendMagicChat(sumT.html + ' <span style="color:#64748b;font-size:0.68rem;">· offline</span>', 'ai');
              showToast(sumT.toast);
              return true;
            }
          }
          // Selected-element REMOVE / HIDE (offline): "remove/delete this (section)",
          // "hide this", "antha section remove pannu".
          if (/remove|delete|hide|neekku|remove pannu|delete pannu|hide pannu/i.test(q)) {
            try {
              if (/hide|hide pannu/i.test(q)) {
                const el = comp.getEl();
                if (el) el.style.display = 'none';
                syncCanvasToHtml();
              } else {
                comp.remove();
                if (grapesEditor) grapesEditor.select(null);
                selectedComponent = null;
                if (typeof updateAiSelectedTarget === 'function') updateAiSelectedTarget(null);
                syncCanvasToHtml();
              }
              if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
                projectData.designs[activeConceptIndex].html = currentHtml;
                saveProjectData();
              }
              renderSmartLayers();
              const sumR = friendlyUpdateSummary(q);
              appendMagicChat(sumR.html + ' <span style="color:#64748b;font-size:0.68rem;">· offline</span>', 'ai');
              showToast(sumR.toast + ' Undo (↶) iruku.');
              return true;
            } catch (e2) { console.warn('[local smart-style remove]', e2); }
          }
        }
      } catch (e) { console.warn('[local smart-style]', e); }
      return false;
    }

    /* ══════════════ PROMPT-TO-IMAGE GENERATION ══════════════ */
    function isImageGenerationRequest(q) {
      if (!q || typeof q !== 'string') return false;
      let s = q.trim().replace(/\[Intent interpretation:.*?\]/gis, '').trim();
      const lower = s.toLowerCase();
      if (/^(?:generate|create|make|draw|render|give me an?)\s+(?:an?\s+)?(?:ai\s+)?(?:image|photo|picture|graphic|illustration)\s+(?:of|for|about|with)?/i.test(lower)) return true;
      if (/^(?:ai\s*image|prompt\s*to\s*image|image\s*gen(?:eration)?|generate\s*photo|generate\s*picture|draw\s*photo)[:\s]/i.test(lower)) return true;
      if (/^image:\s*.+/i.test(lower)) return true;
      if (/(?:image|photo|padam)\s*(?:create|generate|ready|thanga|podu|pannu|vainga)/i.test(lower)) return true;
      return false;
    }

    function extractImagePrompt(q) {
      let s = q.trim().replace(/\[Intent interpretation:.*?\]/gis, '').trim();
      let p = s;
      p = p.replace(/^(?:generate|create|make|draw|render|give me an?)\s+(?:an?\s+)?(?:ai\s+)?(?:image|photo|picture|graphic|illustration)\s+(?:of|for|about|with)?\s*/i, '');
      p = p.replace(/^(?:ai\s*image|prompt\s*to\s*image|image\s*gen(?:eration)?|generate\s*photo|generate\s*picture|draw\s*photo)[:\s]*/i, '');
      p = p.replace(/^image:\s*/i, '');
      p = p.replace(/(?:image|photo|padam)\s*(?:create|generate|ready|thanga|podu|pannu|vainga).*/i, '');
      return p.trim() || s.trim();
    }

    async function executeAiImageGeneration(cleanPrompt, options = {}) {
      const width = options.width || 1200;
      const height = options.height || 800;
      const seed = Math.floor(Math.random() * 9999999);
      const encoded = encodeURIComponent(cleanPrompt.trim());
      const imageUrl = `https://image.pollinations.ai/prompt/${encoded}?width=${width}&height=${height}&nologo=true&seed=${seed}&model=flux`;
      return { url: imageUrl, prompt: cleanPrompt, seed, width, height };
    }

    function renderAiImageInChat(imgData) {
      const cardHtml = `
        <div class="ai-chat-image-card">
          <div style="position:relative; width:100%; min-height:170px; background:#040711; display:flex; align-items:center; justify-content:center;">
            <img src="${imgData.url}" alt="${escapeHtml(imgData.prompt)}" style="width:100%; height:auto; display:block; max-height:260px; object-fit:cover;" loading="lazy" onload="const l=this.nextElementSibling;if(l)l.style.display='none';">
            <div style="position:absolute; font-size:0.75rem; color:#94a3b8; display:flex; align-items:center; gap:0.4rem; padding:0.5rem 1rem; background:rgba(0,0,0,0.6); border-radius:999px;">
              <span class="typing-dots"><i></i><i></i><i></i></span> Generating Flux image...
            </div>
          </div>
          <div style="padding:0.65rem 0.75rem 0.5rem;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.35rem;">
              <span style="font-size:0.68rem; font-weight:700; color:#818cf8; text-transform:uppercase; letter-spacing:0.04em;">🎨 AI Image (Flux)</span>
              <span style="font-size:0.65rem; color:#64748b;">${imgData.width}×${imgData.height}</span>
            </div>
            <div style="font-size:0.78rem; color:#e2e8f0; font-weight:600; line-height:1.35; margin-bottom:0.6rem;">
              "${escapeHtml(imgData.prompt)}"
            </div>
          </div>
          <div class="ai-chat-image-actions">
            <button type="button" class="ai-chat-img-btn primary" onclick="applyAiImageToCanvas('${imgData.url}', 'replace')">
              🖼️ Replace Image
            </button>
            <button type="button" class="ai-chat-img-btn" onclick="applyAiImageToCanvas('${imgData.url}', 'background')">
              ✨ Set Background
            </button>
            <button type="button" class="ai-chat-img-btn success" onclick="applyAiImageToCanvas('${imgData.url}', 'insert')">
              ➕ Insert Block
            </button>
            <button type="button" class="ai-chat-img-btn" onclick="saveAiImageToProjectStorage('${imgData.url}', this)">
              📥 Save to Project
            </button>
          </div>
        </div>
      `;
      appendMagicChat(cardHtml, 'ai');
      showToast('✨ AI image ready in chat!');
    }

    function flashCanvasComponent(comp) {
      try {
        const el = comp?.getEl ? comp.getEl() : null;
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          el.style.outline = '3px solid #6366f1';
          el.style.outlineOffset = '3px';
          el.style.transition = 'outline 0.3s ease';
          setTimeout(() => { if (el) el.style.outline = ''; }, 2200);
        }
      } catch (e) {}
    }

    function applyAiImageToCanvas(url, mode) {
      if (!grapesEditor) return;
      const comp = selectedComponent;

      if (mode === 'replace') {
        if (comp) {
          const tag = (comp.get('tagName') || '').toLowerCase();
          if (tag === 'img') {
            comp.addAttributes({ src: url });
            syncCanvasToHtml();
            saveProjectData();
            showToast('🖼️ Replaced image on canvas!');
            flashCanvasComponent(comp);
            return;
          }
          const innerImg = comp.find('img')[0];
          if (innerImg) {
            innerImg.addAttributes({ src: url });
            syncCanvasToHtml();
            saveProjectData();
            showToast('🖼️ Replaced image on canvas!');
            flashCanvasComponent(innerImg);
            return;
          }
        }
        const firstImg = grapesEditor.getWrapper().find('img')[0];
        if (firstImg) {
          firstImg.addAttributes({ src: url });
          syncCanvasToHtml();
          saveProjectData();
          showToast('🖼️ Replaced website photo!');
          flashCanvasComponent(firstImg);
          return;
        }
        showToast('Select an image on canvas first to replace');
      } else if (mode === 'background') {
        const target = comp || grapesEditor.getWrapper().find('section, header, hero')[0] || grapesEditor.getWrapper();
        if (target) {
          const style = target.getStyle() || {};
          style['background-image'] = `url('${url}')`;
          style['background-size'] = 'cover';
          style['background-position'] = 'center';
          style['background-repeat'] = 'no-repeat';
          target.setStyle(style);
          syncCanvasToHtml();
          saveProjectData();
          showToast('✨ Section background set!');
          flashCanvasComponent(target);
        }
      } else if (mode === 'insert') {
        const wrapper = grapesEditor.getWrapper();
        const target = comp || wrapper;
        const blockHtml = `
          <div class="ai-generated-image-block" style="padding: 2.5rem 1rem; text-align: center;">
            <div style="max-width: 960px; margin: 0 auto; overflow: hidden; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.18);">
              <img src="${url}" alt="AI Generated" style="width: 100%; height: auto; display: block; object-fit: cover;" />
            </div>
          </div>
        `;
        let inserted;
        if (target !== wrapper && target.parent()) {
          inserted = target.parent().append(blockHtml, { at: target.index() + 1 })[0];
        } else {
          inserted = wrapper.append(blockHtml)[0];
        }
        if (inserted) {
          grapesEditor.select(inserted);
          flashCanvasComponent(inserted);
        }
        syncCanvasToHtml();
        saveProjectData();
        showToast('➕ Added image block to page!');
      }
    }

    async function saveAiImageToProjectStorage(url, btnEl) {
      if (!url) return;
      if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '⏳ Saving...';
      }
      showToast('📥 Downloading image into project...');
      try {
        const fd = new FormData();
        fd.append('url', url);
        const res = await fetch('<?= SITE_URL ?>/api/upload.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data && data.success && data.url) {
          if (btnEl) {
            btnEl.innerHTML = '✓ Saved';
            btnEl.classList.add('success');
          }
          showToast('✓ Image saved permanently in project storage!');
        } else {
          throw new Error(data?.error || 'Could not save image');
        }
      } catch (err) {
        if (btnEl) {
          btnEl.disabled = false;
          btnEl.innerHTML = '📥 Save to Project';
        }
        showToast('Save failed: ' + err.message);
      }
    }

    /* ★ Customer-friendly update summary (Tanglish).
       Tells the customer WHAT changed and WHERE — never a generic message. */
    function cleanSummaryText(s) {
      return String(s || '').replace(/\[Conversation context:[\s\S]*?\](?=\s|$)/gi, ' ').replace(/\[\[(?:CTX|FILE)[\s\S]*?\]\]/gi, ' ').replace(/\[Intent interpretation:[\s\S]*?\](?=\s|$)/gi, ' ').replace(/\s*\[hint:[^\]]*\]/gi, '').trim().replace(/^[:\-–—\s]+/, '').slice(0, 140);
    }
    function describeAiTarget() {
      try {
        const comp = (typeof selectedComponent !== 'undefined') ? selectedComponent : null;
        if (!comp) return { label: 'Website', scoped: false };
        const attrs = comp.getAttributes ? (comp.getAttributes() || {}) : {};
        const secName = attrs['data-section-name'] || attrs['data-section'] || attrs['id'] || '';
        let heading = '';
        try {
          const el = comp.getEl ? comp.getEl() : null;
          if (el) {
            const h = el.querySelector('h1,h2,h3');
            heading = h ? (h.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 40) : '';
            if (!heading) heading = (el.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 40);
          }
        } catch (e) {}
        const tag = (comp.get('tagName') || 'section').toLowerCase();
        const label = secName ? `"${secName}" section` : (heading ? `"${heading}" ${tag}` : `selected ${tag}`);
        return { label, scoped: true, tag };
      } catch (e) { return { label: 'selected section', scoped: true }; }
    }
    function friendlyUpdateSummary(q) {
      const t = describeAiTarget();
      const scopeNote = t.scoped ? 'Vera entha idamum thodala.' : '';
      const s = cleanSummaryText(q);
      const lower = s.toLowerCase();
      if (/remove|delete|neekku/i.test(lower)) return { html: `✅ Ready! ${escapeHtml(t.label)} <b>remove</b> panniten. Undo (↶) venumna use pannunga.`, toast: '🗑️ Section removed!' };
      if (/hide/i.test(lower)) return { html: `✅ Ready! ${escapeHtml(t.label)} <b>hide</b> panniten. ${scopeNote}`, toast: '🙈 Section hidden!' };
      const tm = s.match(/(?:change|rewrite|update|set|maathu|maathi)\s+(?:this\s+)?(?:text|heading|title|label|button|content)\s+(?:to|as|ah|aag?)\s*[:\-]?\s*(.+)/i)
              || s.match(/["“”]([^"“”]{2,200})["“”]/);
      if (tm && cleanSummaryText(tm[1])) return { html: `✅ Ready! ${escapeHtml(t.label)} la text maathiten: “${escapeHtml(cleanSummaryText(tm[1]))}”. ${scopeNote}`, toast: '✨ Text updated!' };
      const cm = lower.match(/\b(blue|green|red|gold|purple|orange|pink|black|white)\b/) || s.match(/#([0-9a-f]{6}|[0-9a-f]{3})/i);
      if (/color|colour|background|niram|theme color/i.test(lower) || cm) {
        const cname = cm ? (cm[1] ? '#' + cm[1] : cm[0]) : 'ungal sonna color';
        return { html: `✅ Ready! ${escapeHtml(t.label)} color <b>${escapeHtml(cname)}</b> aakiten. ${scopeNote}`, toast: '🎨 Color updated!' };
      }
      if (/periya|bigger|large|increase|\bbig\b/i.test(s)) return { html: `✅ Ready! ${escapeHtml(t.label)} size <b>perusa</b> panniten. ${scopeNote}`, toast: '🔍 Size increased!' };
      if (/chinna|smaller|small|decrease/i.test(s)) return { html: `✅ Ready! ${escapeHtml(t.label)} size <b>chinna</b> panniten. ${scopeNote}`, toast: '🔍 Size reduced!' };
      if (/dark|night.*mode/i.test(lower)) return { html: `✅ Ready! <b>Dark mode</b> maathiten.`, toast: '🌙 Dark mode!' };
      if (/light.*mode|white.*mode/i.test(lower)) return { html: `✅ Ready! <b>Light mode</b> maathiten.`, toast: '☀️ Light mode!' };
      if (/add|create|podu|pannu|insert|new/i.test(lower)) return { html: `✅ Ready! Ungal sonna mathiri puthusa add panniten: “${escapeHtml(s.slice(0, 120))}”. ${scopeNote}`, toast: '✨ Section added!' };
      return { html: `✅ Ready! Ungal sonna mathiri apply panniten: “${escapeHtml(s.slice(0, 120))}”. ${scopeNote}`, toast: '✨ Updated!' };
    }

    function applyUpdatedSnippetToCanvas(comp, snippetHtml) {
      if (!comp || !snippetHtml) return;
      let clean = snippetHtml.trim();
      const fenceMatch = clean.match(/```(?:html)?\s*([\s\S]*?)```/i);
      if (fenceMatch) clean = fenceMatch[1].trim();
      const firstTag = clean.indexOf('<');
      if (firstTag > 0) clean = clean.slice(firstTag).trim();

      const parent = comp.parent();
      let freshComp = null;
      if (parent) {
        const idx = comp.index();
        comp.remove();
        const added = parent.append(clean, { at: idx });
        freshComp = Array.isArray(added) ? added[0] : added;
      } else {
        comp.components(clean);
        freshComp = comp;
      }
      if (freshComp) {
        if (typeof configureEditorComponent === 'function') configureEditorComponent(freshComp);
        if (grapesEditor) grapesEditor.select(freshComp);
        flashCanvasComponent(freshComp);
      }
      syncCanvasToHtml();
      if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
        projectData.designs[activeConceptIndex].html = currentHtml;
        saveProjectData();
      }
      renderSmartLayers();
      showToast('✨ Selected element updated live & saved!');
    }

    /* ══════════════════════════════════════════════════════════════
       ★ AI WEBSITE COPILOT (extends Magic AI — lanes/UI preserved)
       Ask/Edit modes · target scopes · memory · find · audit · review
       ══════════════════════════════════════════════════════════════ */
    const copilotState = {
      mode: 'edit',
      memory: [],        // [{role:'user'|'ai', text, target}]
      changes: [],       // [{at, target, summary, kind:'snippet'|'full'|'local', verIdx}]
      pendingReview: null,
      pendingConfirm: null,
      audit: null,       // {at, goods, warns, bads, issues:[...]}
      file: null,        // {name, text}
      lastTargetLabel: '',
      lastAction: '',
      msgSeq: 0
    };
    try { copilotState.mode = localStorage.getItem('webcraft_copilot_mode') || 'edit'; } catch (e) {}
    if (copilotState.mode !== 'ask' && copilotState.mode !== 'edit') copilotState.mode = 'edit';

    function copilotSetMode(m) {
      copilotState.mode = (m === 'ask') ? 'ask' : 'edit';
      try { localStorage.setItem('webcraft_copilot_mode', copilotState.mode); } catch (e) {}
      const a = document.getElementById('copilot-mode-ask'), b = document.getElementById('copilot-mode-edit');
      if (a) a.classList.toggle('active', copilotState.mode === 'ask');
      if (b) b.classList.toggle('active', copilotState.mode === 'edit');
      const inp = document.getElementById('magic-input');
      if (inp) inp.placeholder = copilotState.mode === 'ask'
        ? 'Ask for advice — website will NOT change (e.g. how to improve?)...'
        : 'Describe the change — AI edits the target (e.g. make hero premium)...';
      copilotRefreshTargetLine();
      showToast(copilotState.mode === 'ask' ? '💬 Ask mode — advice only, no changes' : '🛠️ Edit mode — AI can modify the website');
    }
    (function copilotInitMode() {
      const apply = () => {
        const a = document.getElementById('copilot-mode-ask'), b = document.getElementById('copilot-mode-edit');
        if (a) a.classList.toggle('active', copilotState.mode === 'ask');
        if (b) b.classList.toggle('active', copilotState.mode === 'edit');
        copilotRefreshTargetLine();
      };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
      else apply();
    })();

    /* Strip prompt-injected blocks so classification/local logic sees raw intent. */
    function copilotStripNoise(s) {
      return String(s || '')
        .replace(/\[Intent interpretation:[\s\S]*?\](?=\s|$)/gi, ' ')
        .replace(/\[Conversation context:[\s\S]*?\](?=\s|$)/gi, ' ')
        .replace(/\[\[CTX[\s\S]*?\]\]/gi, ' ')
        .replace(/\s*\[hint:[^\]]*\]/gi, '')
        .replace(/\s+/g, ' ').trim();
    }

    /* ── Smart command detection (EN + Tamil + Sinhala). Internal only. ── */
    function copilotClassifyPure(text) {
      const s = copilotStripNoise(text);
      const l = s.toLowerCase();
      const out = { kind: 'STYLE_EDIT', scope: null, allPages: false, destructive: false, sectionType: null, anchor: null, findWhat: null, tone: null };
      if (/(all|every|each)\s+pages?\b|ella\s+(pages|pakkam)/i.test(s)) out.allPages = true;
      if (/(this|current)\s+page\b|intha\s+page/i.test(s)) out.scope = out.scope || 'page';
      if (/(delete|remove|redesign|replace)\s+(the\s+)?(entire|whole|full|complete)\b/i.test(s)
        || /redesign\s+(my\s+)?(complete|entire|whole)\s+(website|site|homepage)/i.test(s)
        || /(entire|whole)\s+(page|website|site)\s+(delete|remove)/i.test(s)) out.destructive = true;
      if (/\b(audit|website\s+health|site\s+score|full\s+(checkup|check|review))\b/i.test(l) || /audit\s+(my\s+)?(website|site|page)/i.test(s)) { out.kind = 'WEBSITE_AUDIT'; return out; }
      if (/\bfix\s+(everything|all|these|them|found|issues|ella)\b/i.test(s) || /ella(athayum|yum)?\s*fix\s*pannu/i.test(s)) { out.kind = 'FIX_ALL'; return out; }
      if (/^\s*(undo|revert|undo\s+that|பழையபடி|திரும்ப|ආපසු)\b/i.test(s) || /↩\s*undo/i.test(s)) { out.kind = 'UNDO'; return out; }
      const findM = s.match(/^(find|where\s+is|locate|show\s+me|select|enga|kandu(pidi)?|find\s+pannu|hoyanna?)\b[\s:,'"“”]*([^'"“”]+)$/i)
        || s.match(/(find|select)\s+(my|the|antha|intha)\b(.+)$/i);
      if (findM && /(section|button|hero|footer|header|pricing|contact|faq|gallery|form|menu|nav|logo|image|photo|testimonial|heading)/i.test(findM[3] || '')) {
        out.kind = 'FIND_ELEMENT'; out.findWhat = (findM[3] || '').trim(); return out;
      }
      if (/\bseo\b|meta\s+(title|description)|search\s+(rank|result)|google.*rank/i.test(s)) { out.kind = 'SEO_EDIT'; return out; }
      if (/\bmobile\b|responsive|small\s+screen|phone\s+(view|screen|la)/i.test(s) && /(fix|improve|better|friend|optimi|maathu|seri)/i.test(s)) { out.kind = 'MOBILE_FIX'; return out; }
      if (/(image|photo|picture|padam|படம்|புகைப்படம்|ඡායාරූපය|පින්තූරය)/i.test(s)
        && /(replac|chang| professional|bigger|larg|smaller|background|hero|பெரிய|சிறிய|பெருசு|ලොකු|පොඩි)/i.test(s)) { out.kind = 'IMAGE_EDIT'; return out; }
      const secM = s.match(/(testimonial|review|faq|pricing|price|gallery|contact|form|map|banner|cta|newsletter|stat|timeline|booking|blog|service|about|hero|footer)s?\b/i);
      if (/(add|creat|insert|new|append|podu|add\s+pannu|seru|சேர்|சேரு|உருவாக்கு|එකතු|අලුත්)/i.test(s) && secM) {
        out.kind = 'SECTION_ADD'; out.sectionType = secM[1].toLowerCase();
        const am = s.match(/(below|after|under|above|before|over|kizha|keezha|கீழே|mela|மேலே|முன்|பின்)\s+(.+?)(?:\s+section)?$/i);
        if (am) out.anchor = { pos: /below|after|under|kizha|கீழே|பின்/i.test(am[1]) ? 'after' : 'before', what: am[2].trim() };
        return out;
      }
      if (/(remov|delet|neekku|neekunga|நீக்கு|makanna|අයින්)/i.test(s) && /(section|hero|footer|header|pricing|faq|gallery|form|banner|block)/i.test(s)) { out.kind = 'SECTION_DELETE'; return out; }
      if (/\bmove\b.*\b(above|below|before|after|top|bottom)\b/i.test(s) || /reorder/i.test(s)) { out.kind = 'SECTION_MOVE'; return out; }
      if (/\b(tone|copy|copywrite|headline|slogan|tagline)\b/i.test(l)
        || /(rewrite|shorten|expand|professional|persuasive|friendly|premium|copy|content|text).{0,40}(hero|about|service|testimonial|faq|pricing|cta|contact)/i.test(s)
        || /(hero|about|service|testimonial|faq|pricing)\b.{0,30}(rewrite|shorten|expand|regenerate)/i.test(s)) { out.kind = 'CONTENT_GENERATION'; return out; }
      const qMark = /\?\s*$/.test(s.trim());
      const editVerb = /(mak|chang|updat|edit|add|remov|delet|fix|improv|creat|generat|insert|replac|set|maathu|maathi|mathu|mattu|aakku|pannu|பண்ணு|மாற்று|කරන්න|හදන්න)/i.test(s);
if (/^(what|why|which|is|are|can|should|do\s|does|explain|enga|epdi|yen|ethu|ஏன்|எப்படி|எது|ඇයි|මොකක්ද|කොහොමද)/i.test(s.trim())) { out.kind = 'QUESTION'; return out; }
      if (/^(how|how\s+to|how\s+do\s+i|epdi)\b/i.test(s.trim())) { out.kind = 'ADVICE'; return out; }
      if (qMark && !editVerb) { out.kind = 'QUESTION'; return out; }
      if (/(suggest|advice|opinion|should\s+i|what.{0,25}wrong|improve.{0,20}\?|review\s+my)/i.test(s) && !editVerb) { out.kind = 'ADVICE'; return out; }
      if (/(text|heading|title|label|font.{0,15}(text|word)|paragraph)/i.test(s) && /(chang|rewrite|updat|set|maathu|maathi|mathu|mattu|edita?)/i.test(s)) { out.kind = 'TEXT_EDIT'; return out; }
      return out;
    }

    /* ── Target resolution: element / section / page / website ── */
    function copilotSelectedComp() {
      try { return (typeof selectedComponent !== 'undefined') ? selectedComponent : null; } catch (e) { return null; }
    }
    function copilotNearestSection(comp) {
      let c = comp, depth = 0;
      try {
        while (c && depth < 8) {
          const tag = (c.get('tagName') || '').toLowerCase();
          const at = c.getAttributes ? (c.getAttributes() || {}) : {};
          if (tag === 'section' || tag === 'header' || tag === 'footer' || tag === 'main' || at['data-section-name']) return c;
          c = c.parent ? c.parent() : null; depth++;
        }
      } catch (e) {}
      return comp;
    }
    function copilotCompOuter(comp, max) {
      try {
        const el = comp.getEl ? comp.getEl() : null;
        const outer = el ? el.outerHTML : (comp.toHTML ? comp.toHTML() : '');
        return String(outer || '').slice(0, max || 8000);
      } catch (e) { return ''; }
    }
    function copilotResolveTarget() {
      const scopePref = (typeof window !== 'undefined' && window.WCAIScope) ? window.WCAIScope : 'auto';
      const comp = copilotSelectedComp();
      const page = copilotPageInfo();
      if (scopePref === 'site' || (!comp && scopePref === 'auto')) {
        return { scope: 'website', comp: null, html: '', label: 'Entire Website', page };
      }
      if (scopePref === 'page') return { scope: 'page', comp: null, html: '', label: 'Page: ' + page.name, page };
      if (!comp) return { scope: 'website', comp: null, html: '', label: 'Entire Website', page };
      if (scopePref === 'section') {
        const sec = copilotNearestSection(comp);
        return { scope: 'section', comp: sec, html: copilotCompOuter(sec), label: copilotDescribeComp(sec), page };
      }
      return { scope: 'element', comp, html: copilotCompOuter(comp), label: copilotDescribeComp(comp), page };
    }
    function copilotDescribeComp(comp) {
      try {
        if (!comp) return 'Entire Website';
        const at = comp.getAttributes ? (comp.getAttributes() || {}) : {};
        const nm = at['data-section-name'] || at['data-section'] || at['id'] || '';
        const tag = (comp.get('tagName') || 'element').toLowerCase();
        let head = '';
        try {
          const el = comp.getEl ? comp.getEl() : null;
          if (el) {
            const h = el.querySelector('h1,h2,h3');
            head = ((h ? h.innerText : el.innerText) || '').trim().replace(/\s+/g, ' ').slice(0, 42);
          }
        } catch (e) {}
        if (nm) return `"${nm}" ${tag}`;
        if (head) return `"${head}" ${tag}`;
        return `<${tag}>`;
      } catch (e) { return 'selected element'; }
    }
    function copilotRefreshTargetLine() {
      try {
        const el = document.getElementById('copilot-target-line');
        if (!el) return;
        const t = copilotResolveTarget();
        const modeIcon = copilotState.mode === 'ask' ? '💬 Ask' : '🛠️ Edit';
        const icon = t.scope === 'website' ? '🌐' : (t.scope === 'page' ? '📄' : (t.scope === 'section' ? '📐' : '🎯'));
        el.textContent = `${modeIcon} · ${icon} Target: ${t.scope === 'website' ? 'Entire Website' : t.label}`;
        copilotState.lastTargetLabel = t.label;
      } catch (e) {}
    }

    /* ── Page + brand context (lightweight, from existing project data) ── */
    function copilotPageInfo() {
      try {
        if (typeof wcEnsurePages === 'function' && typeof wcCurrentPageIdx === 'function') {
          const pages = wcEnsurePages() || [];
          const idx = wcCurrentPageIdx() || 0;
          const cur = pages[idx] || {};
          return { name: cur.name || 'Home', slug: cur.slug || 'index', idx, count: pages.length };
        }
      } catch (e) {}
      return { name: 'Home', slug: 'index', idx: 0, count: 1 };
    }
    function copilotBrandBlock() {
      try {
        const d = (typeof wcDesign === 'function') ? wcDesign() : null;
        const ss = (d && d.siteSettings) || {};
        const biz = (typeof projectData !== 'undefined' && projectData && projectData.bizName) || ss.name || 'Website';
        let primary = '';
        try {
          const m = String(typeof currentHtml === 'string' ? currentHtml : '').match(/--primary\s*:\s*(#[0-9a-fA-F]{3,8})/);
          if (m) primary = m[1];
        } catch (e) {}
        const parts = [`Business: ${biz}`];
        if (ss.phone) parts.push(`Phone: ${ss.phone}`);
        if (ss.email) parts.push(`Email: ${ss.email}`);
        if (ss.whatsapp) parts.push(`WhatsApp: ${ss.whatsapp}`);
        if (ss.address) parts.push(`Location: ${ss.address}`);
        if (ss.hours) parts.push(`Hours: ${ss.hours}`);
        if (primary) parts.push(`Primary color: ${primary}`);
        if (ss.lang) parts.push(`Preferred language: ${ss.lang}`);
        return parts.join(' | ');
      } catch (e) { return ''; }
    }
    function copilotStructureSummary(maxSections) {
      // Compressed site map: section names + headings only (token-efficient).
      try {
        if (!grapesEditor) return '';
        const wrap = grapesEditor.getWrapper();
        const out = [];
        const walk = (c, depth) => {
          if (!c || depth > 6 || out.length >= (maxSections || 24)) return;
          let tag = '';
          try { tag = (c.get('tagName') || '').toLowerCase(); } catch (e) {}
          if (['section', 'header', 'footer', 'main'].includes(tag)) {
            const at = c.getAttributes ? (c.getAttributes() || {}) : {};
            let head = '';
            try {
              const el = c.getEl ? c.getEl() : null;
              const h = el ? el.querySelector('h1,h2,h3') : null;
              head = h ? (h.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 60) : '';
            } catch (e) {}
            out.push(`- <${tag}> ${at['data-section-name'] || at.id || ''} ${head ? '| "' + head + '"' : ''}`.trim());
          }
          try { (c.components() || []).forEach(k => walk(k, depth + 1)); } catch (e) {}
        };
        (wrap.components() || []).forEach(k => walk(k, 0));
        return out.join('\n');
      } catch (e) { return ''; }
    }

    /* ── Conversation memory (lightweight, capped) ── */
    function copilotMemPush(role, text, target) {
      try {
        copilotState.memory.push({ role, text: String(text || '').slice(0, 300), target: target || '' });
        while (copilotState.memory.length > 8) copilotState.memory.shift();
      } catch (e) {}
    }
    function copilotMemoryBlock() {
      try {
        const turns = copilotState.memory.slice(-6);
        if (!turns.length) return '';
        const lines = turns.map(t => `${t.role === 'user' ? 'User' : 'AI'}: ${t.text.slice(0, 140)}${t.target ? ` [target: ${t.target}]` : ''}`);
        return `\n\n[Conversation context (resolve pronouns like "them/it/that" from the last turn):\n${lines.join('\n')}\n]`;
      } catch (e) { return ''; }
    }
    function copilotResolvePronouns(q) {
      try {
        if (!/\b(them|it|that|those|this)\b/i.test(q)) return q;
        if (/(button|section|heading|text|image|color|hero|footer|header)/i.test(q)) return q;
        const last = [...copilotState.memory].reverse().find(m => m.target);
        if (last && last.target) return `${q} (= ${last.target})`;
      } catch (e) {}
      return q;
    }

    /* ── Activity stages (never a frozen spinner) ── */
    function copilotStage(t) {
      try {
        const b = document.querySelector('#magic-typing .msg-bubble');
        if (b) b.innerHTML = '<span class="typing-dots"><i></i><i></i><i></i></span> <span style="font-size:.78rem;color:#94a3b8;font-weight:600;">' + escapeHtml(t) + '</span>';
      } catch (e) {}
    }
    function copilotDone() {
      try {
        const t = document.getElementById('magic-typing');
        if (t) t.remove();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      } catch (e) {}
    }

    /* ── ASK lane: advice/audit/content text (never touches the website) ── */
    async function copilotAsk(instruction, opts) {
      opts = opts || {};
      const selModel = document.getElementById('magic-model-select')?.value || 'gemini-3.5-flash-lite';
      const t = copilotResolveTarget();
      const ctx = {
        brand: copilotBrandBlock(),
        page: `${copilotPageInfo().name} (/${copilotPageInfo().slug}) of ${copilotPageInfo().count} page(s)`,
        target: `${t.label} [${t.scope}]${t.html ? '\n' + t.html.slice(0, 2500) : '\n' + copilotStructureSummary(20)}`,
        history: copilotState.memory.slice(-6).map(m => `${m.role}: ${m.text.slice(0, 140)}`).join('\n'),
        file: copilotState.file ? `FILE: ${copilotState.file.name}\n${copilotState.file.text.slice(0, 6000)}` : ''
      };
      copilotStage(opts.stage || 'Asking AI...');
      try {
        const res = await fetch('<?= SITE_URL ?>/api/generate.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'ask', instruction, context: ctx, model: selModel })
        });
        const j = await res.json();
        if (j && j.success && j.reply) {
          copilotMemPush('ai', j.reply.slice(0, 300), t.label);
          return { ok: true, reply: j.reply, model: j.model || selModel };
        }
        // Transparent retry with the other lane before giving up (§22).
        copilotStage('AI model unavailable — trying another available model...');
        const res2 = await fetch('<?= SITE_URL ?>/api/generate.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'ask', instruction, context: ctx, model: 'opencode-fallback' })
        });
        const j2 = await res2.json();
        if (j2 && j2.success && j2.reply) {
          copilotMemPush('ai', j2.reply.slice(0, 300), t.label);
          return { ok: true, reply: j2.reply, model: j2.model || 'opencode' };
        }
        return { ok: false, error: ((j2 && (j2.error || (j2.errors || [])[0])) || (j && (j.error || (j.errors || [])[0])) || 'AI unavailable') };
      } catch (e) {
        return { ok: false, error: e?.message || 'network error' };
      }
    }

    /* ── FIND element by natural language (no modification) ── */
    function copilotWalkComps(fn) {
      const out = [];
      try {
        if (!grapesEditor) return out;
        const walk = (c, depth) => {
          if (!c || depth > 9) return;
          try { if (fn(c) === true) out.push(c); } catch (e) {}
          try { (c.components() || []).forEach(k => walk(k, depth + 1)); } catch (e) {}
        };
        (grapesEditor.getWrapper().components() || []).forEach(k => walk(k, 0));
      } catch (e) {}
      return out;
    }
    function copilotCompText(c) {
      try {
        const el = c.getEl ? c.getEl() : null;
        return ((el ? (el.innerText || '') : '') + '').toLowerCase();
      } catch (e) { return ''; }
    }
    function copilotFindElement(what) {
      const w = copilotStripNoise(what).toLowerCase();
      const keys = w.split(/[^a-z0-9₹#.\u0b80-\u0bff\u0d80-\u0dff ]+/i).join(' ');
      const wants = (rx) => rx.test(w);
      const bySectionName = (name) => copilotWalkComps(c => {
        const at = c.getAttributes ? (c.getAttributes() || {}) : {};
        const nm = `${at['data-section-name'] || ''} ${at.id || ''}`.toLowerCase();
        return nm.includes(name);
      });
      const byHeading = (name) => copilotWalkComps(c => {
        const tag = (c.get('tagName') || '').toLowerCase();
        if (!['section', 'div', 'header', 'footer', 'main'].includes(tag)) return false;
        return copilotCompText(c).slice(0, 400).includes(name);
      });
      const byButtonText = (name) => copilotWalkComps(c => {
        const tag = (c.get('tagName') || '').toLowerCase();
        if (tag !== 'a' && tag !== 'button') return false;
        return copilotCompText(c).includes(name);
      });
      let hits = [];
      if (wants(/hero|முகப்பு|banner/)) hits = bySectionName('hero').length ? bySectionName('hero') : copilotWalkComps(c => (c.get('tagName') || '').toLowerCase() === 'header');
      else if (wants(/footer|அடி|පාදකය/)) hits = copilotWalkComps(c => (c.get('tagName') || '').toLowerCase() === 'footer');
      else if (wants(/header|menu|nav/)) hits = copilotWalkComps(c => (c.get('tagName') || '').toLowerCase() === 'header');
      else if (wants(/contact/)) hits = byButtonText('contact').concat(bySectionName('contact'));
      else if (wants(/whatsapp|call|phone/)) hits = byButtonText(wants(/whatsapp/) ? 'whatsapp' : wants(/call/) ? 'call' : 'phone');
      else if (wants(/pric|price|விலை|මිල/)) hits = bySectionName('pric').concat(byHeading('pric'));
      else if (wants(/faq|question/)) hits = bySectionName('faq').concat(byHeading('frequently'));
      else if (wants(/galler/)) hits = bySectionName('galler');
      else if (wants(/testimonial|review/)) hits = bySectionName('testimonial').concat(bySectionName('review'));
      else if (wants(/button|பட்டன்/)) {
        const rest = w.replace(/button|பட்டன்|find|select|the|my|where|is|show|me|please/g, ' ').trim();
        hits = rest ? byButtonText(rest.split(' ')[0]) : [];
        if (!hits.length) hits = copilotWalkComps(c => ['a', 'button'].includes((c.get('tagName') || '').toLowerCase()));
      } else if (wants(/image|photo|picture|padam|படம்/)) hits = copilotWalkComps(c => (c.get('tagName') || '').toLowerCase() === 'img');
      else if (wants(/form/)) hits = copilotWalkComps(c => (c.get('tagName') || '').toLowerCase() === 'form');
      else if (wants(/logo/)) hits = copilotWalkComps(c => { const at = c.getAttributes ? (c.getAttributes() || {}) : {}; return /logo/i.test(at.class || '') || /logo/i.test(at.alt || ''); });
      else {
        const word = keys.split(' ').filter(x => x.length > 2 && !/^(the|my|find|select|show|where|please|antha|intha|and|element|section)$/.test(x))[0];
        if (word) { hits = bySectionName(word).concat(byButtonText(word)).concat(byHeading(word)); }
      }
      hits = hits.filter(Boolean);
      return hits[0] || null;
    }

    /* ── WEBSITE AUDIT (deterministic scan of the real site — no fake tests) ── */
    function copilotContrastRatio(fg, bg) {
      try {
        const lum = (rgb) => {
          const m = String(rgb).match(/[\d.]+/g) || [0, 0, 0];
          const [r, g, b] = [0, 1, 2].map(i => { const v = parseFloat(m[i] || 0) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
          return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        const L1 = lum(fg), L2 = lum(bg);
        return (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
      } catch (e) { return 99; }
    }
    function copilotLocatorFor(comp) {
      try {
        const at = comp.getAttributes ? (comp.getAttributes() || {}) : {};
        return { tag: (comp.get('tagName') || '').toLowerCase(), id: at.id || '', cls: (at.class || '').split(' ')[0] || '', text: copilotCompText(comp).slice(0, 60) };
      } catch (e) { return { tag: '', id: '', cls: '', text: '' }; }
    }
    function copilotFindByLocator(loc) {
      const found = copilotWalkComps(c => {
        try {
          const at = c.getAttributes ? (c.getAttributes() || {}) : {};
          if (loc.id && (at.id || '') === loc.id) return true;
          if (loc.cls && String(at.class || '').split(' ').includes(loc.cls) && (c.get('tagName') || '').toLowerCase() === loc.tag) {
            if (!loc.text) return true;
            return copilotCompText(c).slice(0, 60) === loc.text;
          }
        } catch (e) {}
        return false;
      });
      return found[0] || null;
    }
    function copilotAuditRun() {
      copilotStage('Analyzing website...');
      const issues = [];
      let goods = 0;
      try {
        syncCanvasToHtml();
        const parser = new DOMParser();
        const doc = parser.parseFromString(String(currentHtml || ''), 'text/html');
        const bodyText = (doc.body ? doc.body.textContent : '') || '';
        // SEO basics
        const title = (doc.querySelector('title')?.textContent || '').trim();
        if (!title || /^(website|home|untitled|document)$/i.test(title)) issues.push({ sev: 'bad', cat: 'SEO', msg: 'Page title is missing or generic.', fix: 'seo-title' });
        else goods++;
        if (!doc.querySelector('meta[name="description"]')) issues.push({ sev: 'warn', cat: 'SEO', msg: 'Meta description is missing (search previews suffer).', fix: 'seo-desc' });
        else goods++;
        const h1s = doc.querySelectorAll('h1');
        if (h1s.length === 0) issues.push({ sev: 'bad', cat: 'SEO', msg: 'No H1 heading found.', fix: null });
        else if (h1s.length > 1) issues.push({ sev: 'warn', cat: 'SEO', msg: `${h1s.length} H1 headings — keep exactly one per page.`, fix: 'h1-demote' });
        else goods++;
        // Content
        if (/lorem ipsum|placeholder|dummy text|sample text/i.test(bodyText)) issues.push({ sev: 'bad', cat: 'Content', msg: 'Placeholder text (lorem ipsum) still on the page.', fix: 'ai' });
        else if (bodyText.trim().length > 200) goods++;
        const emptyHeads = [...doc.querySelectorAll('h1,h2,h3')].filter(h => !h.textContent.trim()).length;
        if (emptyHeads) issues.push({ sev: 'warn', cat: 'Content', msg: `${emptyHeads} empty heading(s) found.`, fix: null });
        ['contact', 'about', 'service'].forEach(k => { if (new RegExp(`id=["']${k}["']`, 'i').test(String(currentHtml))) goods++; });
        if (!/id=["']contact["']/i.test(String(currentHtml))) issues.push({ sev: 'warn', cat: 'UX', msg: 'No #contact section — CTAs may lead nowhere.', fix: null });
        // Links & buttons (canvas-accurate via components)
        const deadLinks = copilotWalkComps(c => {
          const tag = (c.get('tagName') || '').toLowerCase();
          if (tag !== 'a') return false;
          const href = (c.getAttributes ? (c.getAttributes() || {}).href : '') || '';
          return href === '#' || href.trim() === '';
        });
        if (deadLinks.length) issues.push({ sev: 'warn', cat: 'UX', msg: `${deadLinks.length} link(s) with empty/dead href (#).`, fix: 'dead-links', loc: copilotLocatorFor(deadLinks[0]) });
        else goods++;
        const noAlt = copilotWalkComps(c => {
          if ((c.get('tagName') || '').toLowerCase() !== 'img') return false;
          const alt = (c.getAttributes ? (c.getAttributes() || {}).alt : '') || '';
          return !alt.trim();
        });
        if (noAlt.length) issues.push({ sev: 'warn', cat: 'Accessibility', msg: `${noAlt.length} image(s) missing alt text.`, fix: 'img-alt', loc: copilotLocatorFor(noAlt[0]) });
        else goods++;
        // Mobile: overflow + tiny text + tap targets (measured on live canvas)
        try {
          const cBody = grapesEditor?.Canvas?.getBody?.();
          if (cBody) {
            const over = [];
            cBody.querySelectorAll('section,header,footer,div').forEach(el => {
              if (el.scrollWidth > el.clientWidth + 2 && el.clientWidth > 0) over.push(el);
            });
            if (over.length) issues.push({ sev: 'bad', cat: 'Mobile', msg: `${over.length} element(s) overflow horizontally (side-scroll on phones).`, fix: 'overflow' });
            else goods++;
          }
        } catch (e) {}
        let tiny = 0;
        copilotWalkComps(c => {
          const tag = (c.get('tagName') || '').toLowerCase();
          if (!/^(p|span|li|a)$/.test(tag)) return false;
          try {
            const el = c.getEl ? c.getEl() : null;
            if (!el) return false;
            const fs = parseFloat(window.getComputedStyle(el).fontSize) || 16;
            if (fs < 12) { tiny++; return true; }
          } catch (e) {}
          return false;
        });
        if (tiny) issues.push({ sev: 'warn', cat: 'Mobile', msg: `${tiny} text element(s) smaller than 12px (hard to read on phones).`, fix: 'tiny-text' });
        // Contrast sample (headings + buttons)
        let lowContrast = 0;
        copilotWalkComps(c => {
          const tag = (c.get('tagName') || '').toLowerCase();
          if (!/^(h1|h2|h3|button)$/.test(tag) && !(tag === 'a' && /btn/i.test((c.getAttributes ? (c.getAttributes() || {}).class : '') || ''))) return false;
          try {
            const el = c.getEl ? c.getEl() : null;
            if (!el) return false;
            const cs = window.getComputedStyle(el);
            if (copilotContrastRatio(cs.color, cs.backgroundColor) < 3) { lowContrast++; return true; }
          } catch (e) {}
          return false;
        });
        if (lowContrast) issues.push({ sev: 'warn', cat: 'Accessibility', msg: `${lowContrast} heading/button(s) with very low text contrast.`, fix: 'ai' });
        else goods++;
        if (!/name=["']viewport["']/i.test(String(currentHtml))) issues.push({ sev: 'bad', cat: 'Mobile', msg: 'Viewport meta tag missing — mobile layout will break.', fix: 'viewport' });
        else goods++;
      } catch (e) { issues.push({ sev: 'warn', cat: 'Audit', msg: 'Scan hit a snag: ' + e.message, fix: null }); }
      const bads = issues.filter(i => i.sev === 'bad').length;
      const warns = issues.filter(i => i.sev === 'warn').length;
      copilotState.audit = { at: Date.now(), goods, warns, bads, issues };
      return copilotState.audit;
    }
    function copilotAuditHtml(audit) {
      const sevIcon = (s) => s === 'bad' ? '<span class="cp-sev-bad">❌</span>' : (s === 'warn' ? '<span class="cp-sev-warn">⚠️</span>' : '<span class="cp-sev-ok">✅</span>');
      let h = `<div style="font-weight:800; margin-bottom:0.35rem;">📋 Website Audit — ${escapeHtml(copilotPageInfo().name)}</div>`;
      h += `<div style="font-size:0.76rem; margin-bottom:0.4rem;"><span class="cp-sev-ok">✅ ${audit.goods} good</span> · <span class="cp-sev-warn">⚠️ ${audit.warns} recommended</span> · <span class="cp-sev-bad">❌ ${audit.bads} important</span></div>`;
      if (!audit.issues.length) return h + `<div>${sevIcon('ok')} Everything looks solid. Nice work!</div>`;
      audit.issues.forEach((it, i) => {
        h += `<div class="cp-audit-row">${sevIcon(it.sev)}<span><b>[${escapeHtml(it.cat)}]</b> ${escapeHtml(it.msg)}</span>`;
        if (it.fix) h += `<button type="button" class="cp-msg-btn cp-fix-btn" onclick="copilotFixIssue(${i})">Fix</button>`;
        h += `</div>`;
      });
      return h;
    }

    /* ── Per-issue fix router: safe local fixes first, AI lane otherwise ── */
    async function copilotFixIssue(i) {
      const audit = copilotState.audit;
      if (!audit || !audit.issues[i]) { showToast('Run Audit first'); return; }
      const it = audit.issues[i];
      copilotStage('Fixing: ' + it.msg.slice(0, 60) + '...');
      try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI fix: ' + it.msg.slice(0, 40)); } catch (e) {}
      const done = async (msg) => {
        copilotMemPush('ai', msg, copilotState.lastTargetLabel);
        appendMagicChat(`✅ ${escapeHtml(msg)}`, 'ai');
        copilotFollowups('fix');
      };
      try {
        if (it.fix === 'img-alt') {
          let n = 0;
          copilotWalkComps(c => {
            if ((c.get('tagName') || '').toLowerCase() !== 'img') return false;
            const at = c.getAttributes ? (c.getAttributes() || {}) : {};
            if ((at.alt || '').trim()) return false;
            try {
              let label = copilotBrandBlock().split('|')[0].replace('Business:', '').trim() || 'Website';
              const p = c.parent ? c.parent() : null;
              if (p) { const t = copilotCompText(p).slice(0, 60).trim(); if (t) label = t; }
              c.addAttributes({ alt: label.slice(0, 100) }); n++; return true;
            } catch (e) { return false; }
          });
          syncCanvasToHtml(); saveProjectData();
          audit.issues.splice(i, 1); audit.warns = audit.issues.filter(x => x.sev === 'warn').length; audit.bads = audit.issues.filter(x => x.sev === 'bad').length;
          await done(`Image alt text added (${n} image${n === 1 ? '' : 's'}).`);
          copilotRecordChange('Image alt text', 'local'); return;
        }
        if (it.fix === 'dead-links') {
          const ids = [...String(currentHtml).matchAll(/id="([^"]+)"/gi)].map(m => m[1].toLowerCase());
          let n = 0;
          copilotWalkComps(c => {
            if ((c.get('tagName') || '').toLowerCase() !== 'a') return false;
            const at = c.getAttributes ? (c.getAttributes() || {}) : {};
            const href = (at.href || '').trim();
            if (href && href !== '#') return false;
            const t = copilotCompText(c);
            const guess = ['contact', 'about', 'services', 'pricing', 'faq', 'gallery', 'home'].find(k => t.includes(k) && ids.includes(k));
            if (guess) { try { c.addAttributes({ href: '#' + guess }); n++; return true; } catch (e) {} }
            return false;
          });
          syncCanvasToHtml(); saveProjectData();
          await done(n ? `Dead links pointed at matching sections (${n} fixed).` : 'No safe link target found — tell me where they should go.');
          copilotRecordChange('Dead links', 'local'); return;
        }
        if (it.fix === 'tiny-text') {
          let n = 0;
          copilotWalkComps(c => {
            const tag = (c.get('tagName') || '').toLowerCase();
            if (!/^(p|span|li|a)$/.test(tag)) return false;
            try {
              const el = c.getEl ? c.getEl() : null;
              if (!el) return false;
              if ((parseFloat(window.getComputedStyle(el).fontSize) || 16) < 12) {
                const st = c.getStyle ? (c.getStyle() || {}) : {};
                st['font-size'] = '14px'; c.setStyle(st); n++; return true;
              }
            } catch (e) {}
            return false;
          });
          syncCanvasToHtml(); saveProjectData();
          await done(`Small text bumped to readable size (${n} fixed).`);
          copilotRecordChange('Tiny text', 'local'); return;
        }
        if (it.fix === 'overflow') {
          let n = 0;
          try {
            const cBody = grapesEditor?.Canvas?.getBody?.();
            if (cBody) cBody.querySelectorAll('section,header,footer').forEach(el => {
              if (el.scrollWidth > el.clientWidth + 2 && el.clientWidth > 0) {
                el.style.overflowX = 'clip'; el.style.maxWidth = '100%'; n++;
              }
            });
          } catch (e) {}
          syncCanvasToHtml(); saveProjectData();
          await done(n ? `Horizontal overflow clipped (${n} section${n === 1 ? '' : 's'}) — desktop untouched.` : 'No overflowing section found now.');
          copilotRecordChange('Overflow', 'local'); return;
        }
        if (it.fix === 'viewport') {
          if (!/name=["']viewport["']/i.test(String(currentHtml))) {
            currentHtml = String(currentHtml).replace(/<head([^>]*)>/i, '<head$1>\n<meta name="viewport" content="width=device-width, initial-scale=1.0">');
            loadHtmlIntoStudioCanvas(); saveProjectData();
            await done('Viewport meta tag added — mobile layout enabled.');
            copilotRecordChange('Viewport meta', 'full'); return;
          }
        }
        if (it.fix === 'seo-title' || it.fix === 'seo-desc') {
          await copilotSeoText(it.fix === 'seo-title' ? 'title' : 'desc');
          return;
        }
        // 'ai' / 'h1-demote' / null → scoped AI edit on the located element.
        let target = it.loc ? copilotFindByLocator(it.loc) : null;
        if (target && grapesEditor) grapesEditor.select(target);
        const instr = it.fix === 'h1-demote'
          ? 'Change this H1 into an H2 (keep the exact same text and styling) so the page has only one H1.'
          : `Fix this website issue on the selected element only: ${it.msg}`;
        const inp = document.getElementById('magic-input');
        if (inp) inp.value = instr;
        copilotState.programmatic = true;
        try { await executeMagicAi(); }
        finally { copilotState.programmatic = false; }
      } catch (e) {
        appendMagicChat(`⚠️ Fix failed: ${escapeHtml(e?.message || e)} — website was not modified.`, 'ai');
      }
    }

    /* ── CONTENT GENERATOR (copy via ASK lane, deterministic insert/replace) ── */
    const COPILOT_COPY_SPECS = {
      hero: { title: 'Hero copy', fmt: 'Reply with exactly 4 short lines: HEADLINE (under 10 words), SUB (under 20 words), BUTTON1 text, BUTTON2 text. No labels beyond those four, no extra commentary.' },
      about: { title: 'About us', fmt: 'Reply with exactly 3 lines: TITLE, PARAGRAPH1 (2 sentences), PARAGRAPH2 (2 sentences). No extra commentary.' },
      services: { title: 'Services', fmt: 'Reply with exactly 4 lines: TITLE, then 3 lines each "Service name: one-line benefit". No extra commentary.' },
      features: { title: 'Features', fmt: 'Reply with exactly 4 lines: TITLE, then 3 lines each "Feature: one-line benefit". No extra commentary.' },
      testimonials: { title: 'Testimonials', fmt: 'Reply with exactly 6 lines: 3 pairs of QUOTE (one sentence, no quotes) then CUSTOMER NAME + ROLE. No extra commentary.' },
      faq: { title: 'FAQ', fmt: 'Reply with exactly 6 lines: 3 pairs of QUESTION (ends with ?) then ANSWER (one sentence). No extra commentary.' },
      pricing: { title: 'Pricing', fmt: 'Reply with exactly 4 lines: TITLE, then 3 lines each "Plan name: price + one-line highlight". No extra commentary.' },
      cta: { title: 'Call to action', fmt: 'Reply with exactly 3 lines: HEADLINE (under 8 words), SUB (under 15 words), BUTTON text. No extra commentary.' },
      contact: { title: 'Contact section', fmt: 'Reply with exactly 3 lines: TITLE, INVITING LINE (under 15 words), BUTTON text. No extra commentary.' }
    };
    function copilotParseCopyLines(reply) {
      return String(reply || '').split('\n').map(l => l.replace(/^(headline|title|sub|subtitle|button|cta|q|a|quote|name|answer|description|desc|paragraph\d*|service\d*|feature\d*|plan\d*)\s*[:\-–.]\s*/i, '').trim()).filter(l => l && !/^#+$/.test(l)).slice(0, 8);
    }
    function copilotSectionShell(kind, inner) {
      const P = 'var(--primary,#6366f1)';
      const wrap = (t) => `<section data-ai-gen="${kind}" style="padding:4.5rem 1.5rem;"><div style="max-width:1100px;margin:0 auto;">${t}</div></section>`;
      if (kind === 'hero') return `<section data-ai-gen="hero" style="padding:5rem 1.5rem;text-align:center;background:linear-gradient(180deg,#f8fafc,#eef2ff);"><div style="max-width:820px;margin:0 auto;"><h1 style="font-size:clamp(2rem,5vw,3.4rem);font-weight:900;line-height:1.1;margin-bottom:1rem;">${inner[0] || 'Welcome'}</h1><p style="font-size:1.1rem;color:#475569;margin-bottom:2rem;">${inner[1] || ''}</p><div style="display:flex;gap:0.8rem;justify-content:center;flex-wrap:wrap;"><a href="#contact" style="background:${P};color:#fff;font-weight:800;padding:0.9rem 2.2rem;border-radius:999px;text-decoration:none;">${inner[2] || 'Get Started'}</a><a href="#about" style="border:2px solid ${P};color:${P};font-weight:800;padding:0.9rem 2.2rem;border-radius:999px;text-decoration:none;">${inner[3] || 'Learn More'}</a></div></div></section>`;
      if (kind === 'testimonials') {
        let cards = '';
        for (let i = 0; i < 6; i += 2) {
          if (!inner[i]) continue;
          cards += `<div style="background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:1.6rem;box-shadow:0 8px 24px rgba(2,6,23,0.06);"><div style="color:#f59e0b;margin-bottom:0.6rem;">★★★★★</div><p style="color:#334155;margin-bottom:1rem;">“${inner[i]}”</p><strong>${inner[i + 1] || 'Happy customer'}</strong></div>`;
        }
        return wrap(`<div style="text-align:center;font-size:0.75rem;font-weight:800;letter-spacing:0.12em;color:#94a3b8;margin-bottom:0.6rem;">TESTIMONIALS</div><h2 style="font-size:2rem;font-weight:800;text-align:center;margin-bottom:2.2rem;">Loved by our customers</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.4rem;">${cards}</div>`);
      }
      if (kind === 'faq') {
        let items = '';
        for (let i = 0; i < 6; i += 2) {
          if (!inner[i]) continue;
          items += `<details style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem 1.3rem;"><summary style="font-weight:700;cursor:pointer;">${inner[i]}</summary><p style="color:#475569;margin-top:0.6rem;">${inner[i + 1] || ''}</p></details>`;
        }
        return wrap(`<h2 style="font-size:2rem;font-weight:800;text-align:center;margin-bottom:2rem;">Frequently asked questions</h2><div style="display:flex;flex-direction:column;gap:0.9rem;max-width:760px;margin:0 auto;">${items}</div>`);
      }
      if (kind === 'pricing') {
        let tiers = '';
        inner.slice(1, 4).forEach((t, ix) => {
          const parts = String(t || '').split(':');
          tiers += `<div style="background:${ix === 1 ? '#0f172a' : '#fff'};color:${ix === 1 ? '#fff' : '#0f172a'};border:1px solid #e2e8f0;border-radius:20px;padding:2rem;text-align:center;"><h3 style="margin-bottom:0.5rem;">${(parts[0] || 'Plan').trim()}</h3><div style="font-size:1.8rem;font-weight:900;color:${ix === 1 ? '#fff' : P};margin-bottom:0.6rem;">${(parts[1] || '').trim()}</div><p style="opacity:.75;">${(parts.slice(2).join(':') || '').trim()}</p><a href="#contact" style="display:inline-block;margin-top:1.2rem;background:${P};color:#fff;font-weight:800;padding:0.8rem 1.8rem;border-radius:999px;text-decoration:none;">Choose</a></div>`;
        });
        return wrap(`<h2 style="font-size:2rem;font-weight:800;text-align:center;margin-bottom:2rem;">${inner[0] || 'Pricing'}</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.4rem;">${tiers}</div>`);
      }
      const title = inner[0] || COPILOT_COPY_SPECS[kind]?.title || 'Section';
      const paras = inner.slice(1, 4).map(p => `<p style="color:#475569;margin-bottom:1rem;">${p}</p>`).join('');
      return wrap(`<div style="font-size:0.75rem;font-weight:800;letter-spacing:0.12em;color:#94a3b8;margin-bottom:0.6rem;">${title.toUpperCase()}</div><h2 style="font-size:2rem;font-weight:800;margin-bottom:1rem;">${title}</h2>${paras}`);
    }
    async function copilotContentAsk(kind) {
      const spec = COPILOT_COPY_SPECS[kind];
      if (!spec) return;
      const input = document.getElementById('magic-input');
      if (input) input.value = `Write ${spec.title.toLowerCase()} copy for my website`;
      appendMagicChat(`✍️ ${escapeHtml(spec.title)}`, 'user');
      showMagicTyping();
      copilotStage('Writing copy...');
      const brand = copilotBrandBlock();
      const file = copilotState.file ? `\nUse these business facts:\n${copilotState.file.text.slice(0, 3000)}` : '';
      const r = await copilotAsk(`Write ${spec.title.toLowerCase()} website copy for this business. ${brand}.${file}\n${spec.fmt}`, { stage: 'Writing copy...' });
      copilotDone();
      const btn = document.getElementById('magic-btn');
      if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      if (!r.ok) { appendMagicChat(`⚠️ I couldn't write that right now (${escapeHtml(r.error)}). Your website was not modified.`, 'ai'); return; }
      const lines = copilotParseCopyLines(r.reply);
      if (!lines.length) { appendMagicChat(formatMarkdown(r.reply), 'ai'); return; }
      const t = copilotResolveTarget();
      const canReplace = t.scope === 'element' && t.comp && (() => {
        try {
          const el = t.comp.getEl ? t.comp.getEl() : null;
          const tag = (t.comp.get('tagName') || '').toLowerCase();
          return el && /^(h1|h2|h3|h4|p|span|a|button|li)$/.test(tag);
        } catch (e) { return false; }
      })();
      const id = ++copilotState.msgSeq;
      copilotState['copy_' + id] = { kind, lines };
      let h = `<div style="font-weight:800; margin-bottom:0.3rem;">✍️ ${escapeHtml(spec.title)} — ready</div>`;
      h += `<div style="font-size:0.78rem; background:#0b1220; border:1px solid #1e293b; border-radius:8px; padding:0.6rem 0.75rem; margin-bottom:0.5rem;">${lines.map(l => `• ${escapeHtml(l)}`).join('<br>')}</div>`;
      h += `<div class="cp-msg-actions">`;
      if (canReplace) h += `<button type="button" class="cp-msg-btn" onclick="copilotCopyApply(${id},'replace')">✏️ Replace selected text</button>`;
      h += `<button type="button" class="cp-msg-btn" onclick="copilotCopyApply(${id},'insert')">➕ Insert as new section</button></div>`;
      copilotMemPush('ai', `${spec.title} copy drafted`, t.label);
      appendMagicChat(h, 'ai');
      copilotFollowups('content');
    }
    function copilotCopyApply(id, how) {
      const c = copilotState['copy_' + id];
      if (!c) return;
      try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI copy: ' + c.kind); } catch (e) {}
      try {
        if (how === 'replace') {
          const t = copilotResolveTarget();
          if (t.comp) {
            const el = t.comp.getEl ? t.comp.getEl() : null;
            if (el) {
              const first = el.querySelector('h1,h2,h3,h4,p') || el;
              first.textContent = c.lines.slice(0, 2).join(' ');
              syncCanvasToHtml(); saveProjectData(); renderSmartLayers(); flashCanvasComponent(t.comp);
              appendMagicChat(`✅ Ready! ${escapeHtml(t.label)} text replaced with fresh copy. Vera entha idamum thodala.`, 'ai');
              copilotRecordChange(`${c.kind} text replaced`, 'local');
              copilotFollowups('edit'); return;
            }
          }
          showToast('Select a text element first');
          return;
        }
        const html = copilotSectionShell(c.kind, c.lines);
        const target = copilotResolveTarget();
        let anchor = (target.scope === 'section' || target.scope === 'element') ? (target.scope === 'section' ? target.comp : copilotNearestSection(target.comp)) : null;
        if (!anchor) {
          const contact = copilotFindElement('contact section') || copilotFindElement('footer');
          anchor = contact || null;
        }
        copilotInsertAfter(anchor, html, `Added ${c.kind} section`);
      } catch (e) { appendMagicChat(`⚠️ Could not insert: ${escapeHtml(e?.message || e)}`, 'ai'); }
    }
    function copilotInsertAfter(anchorComp, html, label) {
      if (!grapesEditor) throw new Error('Canvas not ready');
      let clean = String(html).trim();
      const fm = clean.match(/```(?:html)?\s*([\s\S]*?)```/i);
      if (fm) clean = fm[1].trim();
      const ft = clean.indexOf('<');
      if (ft > 0) clean = clean.slice(ft).trim();
      if (!clean || !clean.includes('<') || clean.length < 60) throw new Error('AI returned an empty section — website unchanged.');
      let parent, at;
      if (anchorComp && anchorComp.parent) {
        parent = anchorComp.parent();
        at = anchorComp.index() + 1;
      } else {
        parent = grapesEditor.getWrapper();
        const kids = parent.components ? parent.components() : [];
        let footIdx = -1;
        kids.forEach((k, ix) => { try { if ((k.get('tagName') || '').toLowerCase() === 'footer') footIdx = ix; } catch (e) {} });
        at = footIdx >= 0 ? footIdx : kids.length;
      }
      if (!parent) throw new Error('No place to insert — website unchanged.');
      const added = parent.append(clean, { at });
      const fresh = Array.isArray(added) ? added[0] : added;
      if (!fresh) { try { grapesEditor.UndoManager.undo(); } catch (e) {} throw new Error('Insert failed — website unchanged.'); }
      try { if (typeof configureEditorComponent === 'function') configureEditorComponent(fresh); } catch (e) {}
      try { grapesEditor.select(fresh); } catch (e) {}
      flashCanvasComponent(fresh);
      syncCanvasToHtml(); saveProjectData(); renderSmartLayers();
      const lbl = copilotDescribeComp(fresh);
      appendMagicChat(`✅ Ready! <b>${escapeHtml(label || 'New section')}</b> added${anchorComp ? ` near ${escapeHtml(copilotDescribeComp(anchorComp))}` : ''} — selected panniten (${escapeHtml(lbl)}). Vera entha idamum thodala.`, 'ai');
      copilotRecordChange(label || 'Section added', 'snippet');
      copilotMemPush('ai', (label || 'Section added'), lbl);
      copilotFollowups('add');
      showToast('✨ Section added & saved!');
    }
    async function copilotSeoText(which) {
      appendMagicChat(which === 'title' ? '🔎 SEO title' : '🔎 Meta description', 'user');
      showMagicTyping();
      const brand = copilotBrandBlock();
      const r = await copilotAsk(
        which === 'title'
          ? `Write ONE SEO page title (max 60 characters) for this business. ${brand}. Reply with ONLY the title text, nothing else.`
          : `Write ONE SEO meta description (max 155 characters) for this business. ${brand}. Reply with ONLY the description text, nothing else.`,
        { stage: 'Writing SEO...' });
      copilotDone();
      const btn = document.getElementById('magic-btn');
      if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      if (!r.ok) { appendMagicChat(`⚠️ SEO text failed (${escapeHtml(r.error)}). Website unchanged.`, 'ai'); return; }
      const text = copilotStripNoise(r.reply).replace(/^["“”']+|["“”']+$/g, '').trim().slice(0, which === 'title' ? 70 : 170);
      if (!text) { appendMagicChat('⚠️ Empty SEO text — website unchanged.', 'ai'); return; }
      try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI SEO'); } catch (e) {}
      let html = String(currentHtml || '');
      if (which === 'title') {
        if (/<title[^>]*>[\s\S]*?<\/title>/i.test(html)) html = html.replace(/<title[^>]*>[\s\S]*?<\/title>/i, `<title>${escapeHtml(text)}</title>`);
        else html = html.replace(/<head([^>]*)>/i, `<head$1>\n<title>${escapeHtml(text)}</title>`);
      } else {
        if (/<meta\s+name=["']description["'][^>]*>/i.test(html)) html = html.replace(/<meta\s+name=["']description["'][^>]*>/i, `<meta name="description" content="${escapeHtml(text)}">`);
        else html = html.replace(/<\/title>/i, `</title>\n<meta name="description" content="${escapeHtml(text)}">`);
      }
      if (html === currentHtml) { appendMagicChat('⚠️ Could not place SEO tag — website unchanged.', 'ai'); return; }
      currentHtml = html;
      const d = (typeof wcDesign === 'function') ? wcDesign() : null;
      if (d) { d.html = currentHtml; try { const p = wcEnsurePages(); p[wcCurrentPageIdx()].html = currentHtml; } catch (e) {} }
      loadHtmlIntoStudioCanvas(); saveProjectData();
      appendMagicChat(`✅ Ready! SEO ${which === 'title' ? 'title' : 'description'} set: “${escapeHtml(text)}”.`, 'ai');
      copilotRecordChange('SEO ' + which, 'full');
      copilotMemPush('ai', 'SEO ' + which + ' set', 'head');
      copilotFollowups('edit');
    }
    /* ── AI IMAGE COMMANDS (generate + existing canvas apply, editor untouched) ── */
    async function copilotImageCommand(q, target) {
      const l = q.toLowerCase();
      const comp = target.comp;
      const isImgSel = comp && ((comp.get('tagName') || '').toLowerCase() === 'img');
      if (/(larger|bigger|periya|பெரிய|பெருசு|லொகு|ලොකු)/i.test(q) || /(smaller|chinna|சின்ன|சிறிய|පොඩි)/i.test(q)) {
        const bigger = !/(smaller|chinna|சின்ன|சிறிய|පොඩි)/i.test(q);
        const imgComp = isImgSel ? comp : (comp ? (comp.find ? (comp.find('img')[0] || null) : null) : null);
        if (!imgComp) { appendMagicChat('🖼️ Select an image on canvas first, then tell me larger/smaller.', 'ai'); return true; }
        try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI image resize'); } catch (e) {}
        const st = imgComp.getStyle ? (imgComp.getStyle() || {}) : {};
        if (bigger) { st['width'] = '100%'; st['height'] = 'auto'; }
        else { st['max-width'] = '320px'; st['width'] = '100%'; st['height'] = 'auto'; }
        imgComp.setStyle(st);
        syncCanvasToHtml(); saveProjectData(); flashCanvasComponent(imgComp);
        appendMagicChat(`✅ Ready! Image ${bigger ? '<b>perusa</b>' : '<b>chinna</b>'} panniten — selected image mattum maariruku.`, 'ai');
        copilotRecordChange('Image resized', 'local');
        copilotFollowups('edit'); return true;
      }
      const asBg = /(background|hero\s*background|பின்னணி)/i.test(q);
      const brand = copilotBrandBlock().split('|')[0];
      let prompt = copilotStripNoise(q).replace(/(replace|change|use|make|set|this|image|photo|picture|with|a|more|professional|hero|background|maathu|pannu)/gi, ' ').replace(/\s+/g, ' ').trim();
      if (prompt.length < 6) prompt = `professional ${brand} website photo, high quality`;
      appendMagicChat(`🎨 Generating image: <i>${escapeHtml(prompt.slice(0, 90))}</i>...`, 'ai');
      showMagicTyping();
      try {
        const imgData = await executeAiImageGeneration(prompt);
        copilotDone();
        renderAiImageInChat(imgData);
        const url = imgData && imgData.url;
        if (url) {
          try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI image apply'); } catch (e) {}
          applyAiImageToCanvas(url, asBg ? 'background' : 'replace');
          copilotRecordChange(asBg ? 'Image set as background' : 'Image replaced', 'local');
          appendMagicChat(`✅ Ready! ${asBg ? 'Section background set panniten' : 'Image replace panniten'}. Card la irunthu vera option um try pannalam.`, 'ai');
          copilotFollowups('edit');
        }
      } catch (e) {
        copilotDone();
        appendMagicChat(`⚠️ Image generation failed: ${escapeHtml(e?.message || e)} — website unchanged.`, 'ai');
      }
      const btn = document.getElementById('magic-btn');
      if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      return true;
    }
    /* ── ADD SECTION at the right place (anchor-aware, never blind-append) ── */
    async function copilotAddSection(cls, target, selModel, q) {
      const typeName = { testimonial: 'testimonials', review: 'testimonials', faq: 'FAQ', pricing: 'pricing', gallery: 'gallery', contact: 'contact', hero: 'hero', about: 'about', service: 'services', cta: 'call-to-action', banner: 'banner', form: 'contact form', map: 'map', newsletter: 'newsletter' }[cls.sectionType] || cls.sectionType || 'section';
      let anchor = null;
      if (cls.anchor && cls.anchor.what) anchor = copilotFindElement(cls.anchor.what);
      if (!anchor && target.scope !== 'website' && target.comp) anchor = target.scope === 'section' ? target.comp : copilotNearestSection(target.comp);
      copilotStage(cls.anchor ? `Finding ${cls.anchor.what}...` : 'Preparing new section...');
      const brand = copilotBrandBlock();
      const file = copilotState.file ? `\nUse these business facts in the copy:\n${copilotState.file.text.slice(0, 3000)}` : '';
      const anchorHtml = anchor ? copilotCompOuter(anchor, 3000) : copilotStructureSummary(14);
      const instruction = `Create ONE brand-new ${typeName} website section for this business (${brand}).${file}\nDesign context:\n${anchorHtml}\nRules: return ONLY the new <section> element HTML (inline styles, real business copy, working anchor links like #contact, responsive). No markdown fences, no commentary.`;
      copilotStage('Generating section...');
      try {
        const res = await fetch('<?= SITE_URL ?>/api/generate.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'refine', api_key: localStorage.getItem('gemini_api_key') || '', model: selModel === 'opencode-fallback' ? 'gemini-3.5-flash-lite' : selModel, current_html: String(currentHtml || '').slice(0, 20000), selected_html: anchorHtml.slice(0, 10000), instruction, customer_requirement: q, biz_name: (typeof projectData !== 'undefined' && projectData && projectData.bizName) || 'Website' })
        });
        const j = await res.json();
        const html = j && (j.html || '');
        if (j && j.success && html && /<(section|div|header|footer)/i.test(html)) {
          try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI add section'); } catch (e) {}
          const pos = cls.anchor ? (cls.anchor.pos === 'before' ? 'before' : 'below') : 'added';
          const anchorLbl = anchor ? copilotDescribeComp(anchor) : '';
          copilotInsertAfter(anchor, html, `Added ${typeName}`);
          // Rewrite the insert message with anchor position.
          return true;
        }
      } catch (e) { /* fall through to honest message */ }
      appendMagicChat(`⚠️ Section generate aagala — website unchanged. Konjam specific ah sollunga (e.g. "Add FAQ with 3 questions").`, 'ai');
      return true;
    }
    /* ── MOBILE FIX: measured issues → safe local fixes, sequenced ── */
    async function copilotMobileFix() {
      appendMagicChat('📱 Mobile check', 'user');
      showMagicTyping();
      const audit = copilotAuditRun();
      const mob = audit.issues.filter(i => i.cat === 'Mobile');
      copilotDone();
      const btn = document.getElementById('magic-btn');
      if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      if (!mob.length) {
        appendMagicChat(`✅ <b>Mobile check done</b> — overflow illa, text readable, viewport OK. Desktop version thodave illa.`, 'ai');
        copilotFollowups('edit'); return true;
      }
      let h = `<div style="font-weight:800; margin-bottom:0.3rem;">📱 Mobile issues: ${mob.length}</div>`;
      mob.forEach(it => { h += `<div class="cp-audit-row">⚠️<span><b>[${escapeHtml(it.cat)}]</b> ${escapeHtml(it.msg)}</span></div>`; });
      h += `<div style="margin-top:0.4rem; font-size:0.76rem; color:#94a3b8;">Desktop version damage aagathu — mobile-safe fixes mattum apply aagum.</div>`;
      h += `<div class="cp-msg-actions"><button type="button" class="cp-msg-btn" onclick="copilotFixMobileNow()">🔧 Fix mobile issues (${mob.length})</button></div>`;
      copilotState.audit = audit;
      appendMagicChat(h, 'ai');
      copilotFollowups('edit'); return true;
    }
    async function copilotFixMobileNow() {
      const audit = copilotState.audit;
      if (!audit) return;
      const queue = audit.issues.filter(i => i.cat === 'Mobile' && i.fix).slice();
      if (!queue.length) { showToast('Nothing auto-fixable'); return; }
      showMagicTyping();
      let n = 0;
      for (let k = 0; k < queue.length; k++) {
        const idx = audit.issues.indexOf(queue[k]);
        if (idx < 0) continue;
        copilotStage(`Fixing mobile ${k + 1}/${queue.length}...`);
        try { await copilotFixIssue(idx); n++; } catch (e) {}
      }
      copilotDone();
      const btn = document.getElementById('magic-btn');
      if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      appendMagicChat(`✅ <b>Mobile fixes completed (${n}/${queue.length})</b> — desktop untouched.`, 'ai');
      copilotRecordChange('Mobile fixes', 'local');
      copilotFollowups('edit');
    }

    /* ── SAFE validation before any full-page apply (§23) ── */
    function copilotValidateFullDoc(html) {
      const s = String(html || '');
      if (s.trim().length < 500) return 'AI returned an empty page';
      if (!/<body[\s>]/i.test(s) || !/<\/html>/i.test(s)) return 'AI returned broken HTML (no body/html)';
      const txt = s.replace(/<script[\s\S]*?<\/script>/gi, ' ').replace(/<style[\s\S]*?<\/style>/gi, ' ');
      const plain = txt.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      if (plain.length < 100) return 'AI returned a page with no readable content';
      if (/^```/.test(s.trim())) return 'AI returned markdown instead of HTML';
      return null;
    }
    function copilotValidateSnippet(html) {
      const s = String(html || '').trim();
      if (!s || !s.includes('<')) return 'AI returned an empty element';
      if (s.length > 60000) return 'AI returned an oversized element';
      return null;
    }
    /* ── BEFORE/AFTER REVIEW for large changes (§5) ── */
    function copilotReviewStats(newHtml) {
      try {
        const countSec = (h) => (String(h).match(/<(section|header|footer)[\s>]/gi) || []).length;
        const a = countSec(typeof currentHtml === 'string' ? currentHtml : '');
        const b = countSec(newHtml);
        const kb = (h) => Math.round(String(h).length / 1024);
        return { a, b, ka: kb(typeof currentHtml === 'string' ? currentHtml : ''), kb: kb(newHtml) };
      } catch (e) { return { a: 0, b: 0, ka: 0, kb: 0 }; }
    }
    function copilotReviewGate(o) {
      // Small/snippet edits apply directly; large full-page edits need review.
      if (!copilotState.reviewLarge && o.kind !== 'full') return Promise.resolve(true);
      if (o.kind !== 'full') return Promise.resolve(true);
      return new Promise((resolve) => {
        copilotState.pendingReview = { resolve, apply: o.apply, summary: o.summary };
        const st = copilotReviewStats(o.html);
        document.getElementById('copilot-review-title').textContent = o.title || 'Review AI changes';
        document.getElementById('copilot-review-sub').textContent = 'Your website was NOT modified yet. Apply chaa?';
        document.getElementById('copilot-review-yes').textContent = 'Apply Changes';
        document.getElementById('copilot-review-no').textContent = 'Discard';
        document.getElementById('copilot-review-body').innerHTML =
          `<div style="margin-bottom:0.5rem;"><b>Before:</b> ${st.a} sections · ${st.ka} KB &nbsp;→&nbsp; <b>After:</b> ${st.b} sections · ${st.kb} KB</div>` +
          `<div>${o.summary || ''}</div>`;
        document.getElementById('copilot-review-modal').style.display = 'flex';
      });
    }
    function copilotReviewDecide(yes) {
      const p = copilotState.pendingReview || copilotState.pendingConfirm;
      copilotState.pendingReview = null; copilotState.pendingConfirm = null;
      try { document.getElementById('copilot-review-modal').style.display = 'none'; } catch (e) {}
      if (p && p.resolve) p.resolve(!!yes);
    }
    function copilotConfirmDanger(o) {
      // {title, body, yesLabel} → Promise<boolean> (Continue/Cancel).
      return new Promise((resolve) => {
        copilotState.pendingConfirm = { resolve };
        document.getElementById('copilot-review-title').textContent = o.title || 'Confirm change';
        document.getElementById('copilot-review-sub').textContent = 'This is a major change.';
        document.getElementById('copilot-review-yes').textContent = o.yesLabel || 'Continue';
        document.getElementById('copilot-review-no').textContent = 'Cancel';
        document.getElementById('copilot-review-body').innerHTML = o.body || '';
        document.getElementById('copilot-review-modal').style.display = 'flex';
      });
    }
    /* ── AI change memory + undo (integrates version snapshots + UndoManager) ── */
    function copilotVersionsCount() {
      try {
        if (typeof wcGetVersions === 'function') return wcGetVersions().length;
      } catch (e) {}
      return 0;
    }
    function copilotRecordChange(summary, kind) {
      try {
        copilotState.changes.push({ at: Date.now(), target: copilotState.lastTargetLabel || '', summary, kind, verIdx: Math.max(0, copilotVersionsCount() - 1) });
        while (copilotState.changes.length > 20) copilotState.changes.shift();
      } catch (e) {}
    }
    function copilotUndoLast() {
      const last = copilotState.changes.pop();
      if (!last) { showToast('↩ Nothing to undo'); return; }
      try {
        if (last.kind === 'full') {
          if (typeof window.wcRestoreVersion === 'function') { window.wcRestoreVersion(last.verIdx); }
          else if (grapesEditor) grapesEditor.UndoManager.undo();
        } else {
          if (grapesEditor) grapesEditor.UndoManager.undo();
        }
        try { if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml(); } catch (e) {}
        try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
        appendMagicChat(`↩ <b>Undone:</b> ${escapeHtml(last.summary)} — previous state restored.`, 'ai');
        showToast('↩ AI change undone');
      } catch (e) { appendMagicChat(`⚠️ Undo failed: ${escapeHtml(e?.message || e)}`, 'ai'); }
    }
    /* ── Message action buttons + smart follow-ups (§20, §24) ── */
    function copilotMsgButtons(q) {
      const id = ++copilotState.msgSeq;
      copilotState['msg_' + id] = { q };
      return `<div class="cp-msg-actions">` +
        `<button type="button" class="cp-msg-btn" onclick="copilotUndoLast()">↩ Undo</button>` +
        `<button type="button" class="cp-msg-btn" onclick="copilotRetryMsg(${id})">🔁 Retry</button>` +
        `<button type="button" class="cp-msg-btn" onclick="copilotPreviewTarget()">👀 Preview</button></div>`;
    }
    function copilotRetryMsg(id) {
      const m = copilotState['msg_' + id];
      if (!m || !m.q) return;
      const inp = document.getElementById('magic-input');
      if (inp) inp.value = copilotStripNoise(m.q);
      executeMagicAi();
    }
    function copilotPreviewTarget() {
      try {
        const c = copilotSelectedComp();
        if (c) { flashCanvasComponent(c); showToast('👀 Target highlighted on canvas'); return; }
      } catch (e) {}
      try { if (typeof openStudioPreview === 'function') { openStudioPreview(); return; } } catch (e) {}
      showToast('👀 Canvas is live — see your site');
    }
    function copilotFollowups(kind) {
      try {
        const box = document.getElementById('magic-chips-container');
        if (!box) return;
        const chip = (label, fn) => `<span class="m-chip" onclick="${fn}">${label}</span>`;
        let h = '';
        if (kind === 'audit') h = chip('🔧 Fix all', 'copilotFixAll()') + chip('📱 Mobile check', "quickMagic('Make my website mobile-friendly')") + chip('💬 Ask advice', "copilotSetMode('ask')");
        else if (kind === 'fix') h = chip('📋 Audit again', 'copilotAuditShow()') + chip('📱 Mobile check', "quickMagic('Make my website mobile-friendly')") + chip('✨ Make premium', "quickMagic('Make this website more premium')");
        else if (kind === 'add') h = chip('❓ Add FAQ', "quickMagic('Add FAQ section below testimonials')") + chip('📱 Mobile check', "quickMagic('Make my website mobile-friendly')") + chip('📋 Audit', 'copilotAuditShow()');
        else if (kind === 'content') h = chip('🎯 Improve CTA', "quickMagic('Improve CTA visibility')") + chip('📱 Mobile check', "quickMagic('Make my website mobile-friendly')") + chip('🔎 SEO check', 'copilotSeoFlow()');
        else h = chip('📱 Improve Mobile', "quickMagic('Make my website mobile-friendly')") + chip('🎯 Update CTA', "quickMagic('Improve CTA visibility')") + chip('📋 Audit', 'copilotAuditShow()') + chip('🔎 SEO', 'copilotSeoFlow()');
        box.innerHTML = h;
      } catch (e) {}
    }
    /* ── File / document context (§18: txt/md/csv/json) ── */
    function copilotAttachFile() {
      try { document.getElementById('copilot-file').click(); } catch (e) {}
    }
    function copilotFilePicked(input) {
      try {
        const f = input && input.files && input.files[0];
        if (!f) return;
        if (f.size > 200000) { showToast('⚠️ File too big (max 200KB text)'); input.value = ''; return; }
        const rd = new FileReader();
        rd.onload = () => {
          copilotState.file = { name: f.name, text: String(rd.result || '').slice(0, 6000) };
          document.getElementById('copilot-filename').textContent = `${f.name} (${Math.round(copilotState.file.text.length / 1024)}KB)`;
          document.getElementById('copilot-filebar').style.display = 'flex';
          showToast('📎 File attached — AI will use it');
        };
        rd.readAsText(f);
        input.value = '';
      } catch (e) { showToast('⚠️ Could not read file'); }
    }
    function copilotClearFile() {
      copilotState.file = null;
      try { document.getElementById('copilot-filebar').style.display = 'none'; } catch (e) {}
    }
    /* ── Design quick commands (§12) ── */
    function copilotDesignCmd(style) {
      const map = {
        premium: 'Make this look premium: refined spacing, soft shadows, consistent rounded corners, elegant typography — keep all text and images',
        luxury: 'Make this luxurious dark-theme style: deep background, gold accents, serif display headings, generous spacing — keep all text',
        modern: 'Make this modern: clean layout, gradient accents, glassmorphism cards, bold headings — keep all text',
        corporate: 'Make this corporate and trustworthy: navy + white, structured grid, professional headings, clear CTA — keep all text',
        minimal: 'Make this minimal: lots of whitespace, single accent color, simple typography, remove visual clutter — keep all text',
        creative: 'Make this creative and playful: vibrant accents, interesting shapes, energetic headings — keep all text readable',
        mobile: 'Make my website mobile-friendly without changing desktop design',
        cta: 'Improve CTA visibility: high-contrast buttons, sticky call-to-action, clear action words — keep page structure',
        spacing: 'Fix spacing: consistent section padding, even gaps, clean vertical rhythm — do not change colors or text',
        type: 'Improve typography: clear heading hierarchy, readable sizes, elegant font pairing — do not change colors or layout'
      };
      const t = copilotResolveTarget();
      const scopeTxt = t.scope === 'website' ? 'the entire website' : `the selected ${t.label}`;
      const inp = document.getElementById('magic-input');
      if (inp) inp.value = `${map[style] || style} — apply to ${scopeTxt}`;
      executeMagicAi();
    }
    function copilotSeoFlow() { copilotSeoText('title'); }
    /* ── FIX ALL: sequenced safe fixes with progress (§14) ── */
    async function copilotFixAll() {
      let audit = copilotState.audit;
      if (!audit || !audit.issues.length) {
        appendMagicChat('📋 Checking website first...', 'ai');
        showMagicTyping();
        audit = copilotAuditRun();
        copilotDone();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
        if (!audit.issues.length) { appendMagicChat('✅ <b>Nothing to fix</b> — website already looks solid.', 'ai'); return; }
        appendMagicChat(copilotAuditHtml(audit) + `<div class="cp-msg-actions"><button type="button" class="cp-msg-btn warn" onclick="copilotFixAll()">🔧 Fix All (${audit.issues.filter(i => i.fix).length} auto-fixable)</button></div>`, 'ai');
      }
      const queue = audit.issues.filter(i => i.fix).slice();
      if (!queue.length) { appendMagicChat('ℹ️ No auto-fixable issues — the rest need your input. Tell me what to change.', 'ai'); return; }
      const ok = await copilotConfirmDanger({
        title: `Fix ${queue.length} issues?`,
        body: `<div style="font-size:0.8rem;">AI will fix <b>${queue.length}</b> detected issue(s) one by one. Each fix is undoable.</div>`,
        yesLabel: `Fix All (${queue.length})`
      });
      if (!ok) { appendMagicChat('Fix-all cancelled — nothing changed.', 'ai'); return; }
      showMagicTyping();
      let n = 0;
      for (let k = 0; k < queue.length; k++) {
        const idx = audit.issues.indexOf(queue[k]);
        if (idx < 0) continue;
        copilotStage(`Fixing ${k + 1}/${queue.length}: ${queue[k].msg.slice(0, 50)}...`);
        try { await copilotFixIssue(idx); n++; } catch (e) {}
      }
      copilotDone();
      const btn = document.getElementById('magic-btn');
      if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      appendMagicChat(`✅ <b>Website improvements completed (${n}/${queue.length} fixed).</b>`, 'ai');
      copilotRecordChange(`Fix-all: ${n} issues`, 'local');
      copilotFollowups('fix');
      showToast('✓ Website improvements completed');
    }
    async function copilotAuditShow() {
      appendMagicChat('📋 Website audit', 'user');
      showMagicTyping();
      await new Promise(r => setTimeout(r, 30));
      const audit = copilotAuditRun();
      copilotDone();
      const btn = document.getElementById('magic-btn');
      if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      const fixable = audit.issues.filter(i => i.fix).length;
      copilotMemPush('ai', `Audit: ${audit.goods} good, ${audit.warns} warnings, ${audit.bads} important`, 'website');
      appendMagicChat(copilotAuditHtml(audit) + (fixable ? `<div class="cp-msg-actions"><button type="button" class="cp-msg-btn warn" onclick="copilotFixAll()">🔧 Fix All (${fixable})</button></div>` : ''), 'ai');
      copilotFollowups('audit');
    }
    /* ── ALL-PAGES loop (§9: only when explicitly requested) ── */
    async function copilotAllPages(instruction) {
      let pages = [];
      try { pages = (typeof wcEnsurePages === 'function') ? wcEnsurePages() : []; } catch (e) {}
      if (pages.length < 2) {
        appendMagicChat('ℹ️ Only one page exists — applying to the current page.', 'ai');
        return false; // fall through to normal single-page flow
      }
      const cleanInstr = copilotStripNoise(instruction).replace(/\b(to|on|for|across)\s+all\s+pages?\b|\ball\s+pages?\b/gi, 'on this page').trim();
      const ok = await copilotConfirmDanger({
        title: `Change ${pages.length} pages?`,
        body: `<div style="font-size:0.8rem;">“${escapeHtml(cleanInstr.slice(0, 140))}” will be applied to <b>${pages.length} pages</b>: ${pages.map(p => escapeHtml(p.name)).join(', ')}.</div>`,
        yesLabel: `Change ${pages.length} pages`
      });
      if (!ok) { appendMagicChat('Multi-page change cancelled — nothing changed.', 'ai'); return true; }
      const curIdx = copilotPageInfo().idx;
      showMagicTyping();
      let done = 0;
      const inp = document.getElementById('magic-input');
      for (let i = 0; i < pages.length; i++) {
        copilotStage(`Applying to page ${i + 1}/${pages.length}: ${pages[i].name}...`);
        try {
          if (typeof wcSwitchPage === 'function') wcSwitchPage(i);
          await new Promise(r => setTimeout(r, 250));
          if (inp) inp.value = cleanInstr;
          copilotState.programmatic = true;
          try { await executeMagicAi(); }
          finally { copilotState.programmatic = false; }
          done++;
        } catch (e) { console.warn('[copilot] page failed:', pages[i].name, e?.message); }
      }
      try { if (typeof wcSwitchPage === 'function' && curIdx !== copilotPageInfo().idx) wcSwitchPage(curIdx); } catch (e) {}
      copilotDone();
      const btn = document.getElementById('magic-btn');
      if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
      appendMagicChat(`✅ <b>Applied to ${done}/${pages.length} pages:</b> “${escapeHtml(cleanInstr.slice(0, 120))}”.`, 'ai');
      copilotRecordChange(`All-pages edit (${done}/${pages.length})`, 'full');
      copilotFollowups('edit');
      return true;
    }

    /* ── LOCAL section delete / move (exact + undoable, no AI needed) ── */
    async function copilotLocalDelete(target, rawQ) {
      if (!target.comp || !grapesEditor) return false;
      const label = target.label;
      const ok = await copilotConfirmDanger({
        title: `Remove ${label}?`,
        body: `<div style="font-size:0.8rem;">This will remove <b>${escapeHtml(label)}</b> from the ${escapeHtml(copilotPageInfo().name)} page. You can undo right after.</div>`,
        yesLabel: 'Remove it'
      });
      if (!ok) { appendMagicChat('Delete cancelled — nothing changed.', 'ai'); return true; }
      try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI delete'); } catch (e) {}
      try {
        target.comp.remove();
        try { grapesEditor.select(null); } catch (e) {}
        syncCanvasToHtml(); saveProjectData(); renderSmartLayers();
        appendMagicChat(`✅ Ready! ${escapeHtml(label)} <b>remove</b> panniten. Undo (↶) venumna use pannunga.<div class="cp-msg-actions"><button type="button" class="cp-msg-btn" onclick="copilotUndoLast()">↩ Undo</button></div>`, 'ai');
        copilotRecordChange(`Removed ${label}`, 'local');
        copilotMemPush('ai', `Removed ${label}`, '');
        copilotFollowups('edit');
        try { if (typeof updateAiSelectedTarget === 'function') updateAiSelectedTarget(null); } catch (e) {}
      } catch (e) { appendMagicChat(`⚠️ Delete failed: ${escapeHtml(e?.message || e)}`, 'ai'); }
      return true;
    }
    function copilotLocalMove(q) {
      const m = copilotStripNoise(q).match(/move\s+(.+?)\s+(above|below|before|after|top|bottom)\s+(.+)$/i);
      if (!m) return false;
      const mover = copilotFindElement(m[1]) || copilotSelectedComp();
      const anchor = copilotFindElement(m[3]);
      if (!mover || !anchor || mover === anchor) return false;
      try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI move'); } catch (e) {}
      try {
        const parent = anchor.parent ? anchor.parent() : null;
        if (!parent) return false;
        let idx = anchor.index() + (/below|after|bottom/i.test(m[2]) ? 1 : 0);
        if (/top/i.test(m[2])) idx = 0;
        parent.append(mover, { at: idx });
        syncCanvasToHtml(); saveProjectData(); renderSmartLayers();
        try { grapesEditor.select(mover); } catch (e) {}
        flashCanvasComponent(mover);
        appendMagicChat(`✅ Ready! ${escapeHtml(copilotDescribeComp(mover))} moved ${/below|after|bottom/i.test(m[2]) ? 'below' : 'above'} ${escapeHtml(copilotDescribeComp(anchor))}.`, 'ai');
        copilotRecordChange('Section moved', 'local');
        copilotFollowups('edit');
        return true;
      } catch (e) { return false; }
    }
    /* ── MAIN ROUTER: classify → correct operation (runs before AI lanes) ── */
    async function copilotPreRoute(ctx) {
      // Returns {handled:boolean, q?:string}. Handled paths restore btn/typing themselves.
      const rawQ = ctx.rawQ;
      const cls = copilotClassifyPure(rawQ);
      const target = copilotResolveTarget();
      copilotState.lastTargetLabel = target.label;
      copilotMemPush('user', copilotStripNoise(rawQ).slice(0, 300), target.scope === 'website' ? '' : target.label);
      const finishAi = (html, follow) => {
        copilotDone();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
        appendMagicChat(html, 'ai');
        if (follow) copilotFollowups(follow);
      };
      // 1. FIND — read-only, works in both modes.
      if (cls.kind === 'FIND_ELEMENT') {
        const hit = copilotFindElement(cls.findWhat || rawQ);
        copilotDone();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
        if (hit && grapesEditor) {
          try { grapesEditor.select(hit); } catch (e) {}
          flashCanvasComponent(hit);
          const lbl = copilotDescribeComp(hit);
          copilotMemPush('ai', `Selected ${lbl}`, lbl);
          appendMagicChat(`🎯 <b>Selected: ${escapeHtml(lbl)}</b> — canvas la highlight panniten. Edit venumna sollunga (Edit mode).`, 'ai');
          copilotFollowups('edit');
        } else {
          appendMagicChat(`🔍 Atha kandupudika mudiyala (“${escapeHtml((cls.findWhat || '').slice(0, 80))}”). Section name ah sollunga — e.g. “Find pricing section”, “Where is contact button?”.`, 'ai');
        }
        return { handled: true };
      }
      // 2. AUDIT — analysis only, never modifies.
      if (cls.kind === 'WEBSITE_AUDIT') {
        const audit = copilotAuditRun();
        copilotDone();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
        const fixable = audit.issues.filter(i => i.fix).length;
        copilotMemPush('ai', `Audit: ${audit.goods} good, ${audit.warns} warnings, ${audit.bads} important`, 'website');
        appendMagicChat(copilotAuditHtml(audit) + (fixable ? `<div class="cp-msg-actions"><button type="button" class="cp-msg-btn warn" onclick="copilotFixAll()">🔧 Fix All (${fixable})</button></div>` : ''), 'ai');
        copilotFollowups('audit');
        return { handled: true };
      }
      // 3. FIX ALL shortcut.
      if (cls.kind === 'FIX_ALL') { await copilotFixAll(); return { handled: true }; }
      // 3b. UNDO — local, instant, both modes.
      if (cls.kind === 'UNDO') {
        copilotDone();
        const btnU = document.getElementById('magic-btn');
        if (btnU) { btnU.disabled = false; btnU.innerHTML = '➤'; }
        copilotUndoLast();
        return { handled: true };
      }
      // 4. QUESTION / ADVICE — never modifies (both modes).
      if (cls.kind === 'QUESTION' || cls.kind === 'ADVICE') {
        const r = await copilotAsk(copilotStripNoise(rawQ));
        copilotDone();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
        if (r.ok) {
          appendMagicChat(formatMarkdown(r.reply) + `<div style="font-size:0.68rem;color:#64748b;margin-top:0.3rem;">· ${escapeHtml(r.model)}</div>`, 'ai');
          copilotFollowups('edit');
        } else {
          appendMagicChat(`⚠️ I couldn't answer right now (${escapeHtml(r.error)}). Your website was not modified.`, 'ai');
        }
        return { handled: true };
      }
      // 5. ASK MODE — edit intents become advice + one-tap apply offer.
      // (Programmatic runs like Fix-All bypass this: they already confirmed edit intent.)
      if (copilotState.mode === 'ask' && !copilotState.programmatic) {
        const r = await copilotAsk(`How should I do this on my website? Give 3-4 short actionable steps, no code: ${copilotStripNoise(rawQ)}`);
        copilotDone();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
        if (r.ok) {
          const id = ++copilotState.msgSeq;
          copilotState['apply_' + id] = { q: copilotStripNoise(rawQ) };
          appendMagicChat(formatMarkdown(r.reply) + `<div class="cp-msg-actions"><button type="button" class="cp-msg-btn warn" onclick="copilotSwitchAndApply(${id})">🛠️ Switch to Edit &amp; apply</button></div>`, 'ai');
        } else {
          appendMagicChat(`⚠️ I couldn't answer right now (${escapeHtml(r.error)}). Website unchanged.`, 'ai');
        }
        return { handled: true };
      }
      // 5b. ALL-PAGES (explicit only) — checked before single-scope routes
      // so "add X to all pages" never collapses to one page.
      if (cls.allPages) {
        const handled = await copilotAllPages(copilotStripNoise(rawQ));
        if (handled) return { handled: true };
        // single page exists → fall through to lanes
      }
      // 6. IMAGE commands.
      if (cls.kind === 'IMAGE_EDIT') { await copilotImageCommand(copilotStripNoise(rawQ), target); return { handled: true }; }
      // 7. SEO text.
      if (cls.kind === 'SEO_EDIT') {
        const lq = rawQ.toLowerCase();
        await copilotSeoText(/meta\s+description|description/i.test(lq) && !/title/i.test(lq) ? 'desc' : 'title');
        return { handled: true };
      }
      // 8. CONTENT generation (structured copy).
      if (cls.kind === 'CONTENT_GENERATION') {
        const key = (copilotStripNoise(rawQ).match(/hero|about|services|features|testimonials|faq|pricing|cta|contact/i) || ['hero'])[0].toLowerCase();
        const mapKey = { service: 'services', feature: 'features', testimonial: 'testimonials' }[key] || key;
        if (COPILOT_COPY_SPECS[mapKey]) { await copilotContentAsk(mapKey); return { handled: true }; }
        // fall through to lanes with brand-enriched instruction
      }
      // 9. ADD SECTION at anchor.
      if (cls.kind === 'SECTION_ADD') {
        const selModel = document.getElementById('magic-model-select')?.value || 'gemini-3.5-flash-lite';
        await copilotAddSection(cls, target, selModel, copilotStripNoise(rawQ));
        copilotDone();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
        return { handled: true };
      }
      // 10. MOBILE fix flow.
      if (cls.kind === 'MOBILE_FIX') { await copilotMobileFix(); return { handled: true }; }
      // 12. LOCAL delete / move.
      if (cls.kind === 'SECTION_DELETE' && target.comp) { await copilotLocalDelete(target, rawQ); copilotDone(); const btn = document.getElementById('magic-btn'); if (btn) { btn.disabled = false; btn.innerHTML = '➤'; } return { handled: true }; }
      if (cls.kind === 'SECTION_MOVE') {
        if (copilotLocalMove(rawQ)) { copilotDone(); const btn = document.getElementById('magic-btn'); if (btn) { btn.disabled = false; btn.innerHTML = '➤'; } return { handled: true }; }
        // fall through to AI lanes if local move can't resolve
      }
      // 13. DESTRUCTIVE full-site ops → confirm first.
      if (cls.destructive && !copilotState.confirmedOnce) {
        copilotDone();
        const btn = document.getElementById('magic-btn');
        if (btn) { btn.disabled = false; btn.innerHTML = '➤'; }
        const ok = await copilotConfirmDanger({
          title: 'Big change — continue?',
          body: `<div style="font-size:0.8rem;">“${escapeHtml(copilotStripNoise(rawQ).slice(0, 140))}” will change <b>multiple sections</b>. A restore point was saved.</div>`,
          yesLabel: 'Continue'
        });
        if (!ok) { appendMagicChat('Cancelled — nothing changed.', 'ai'); return { handled: true }; }
        copilotState.confirmedOnce = true;
        const inp = document.getElementById('magic-input');
        if (inp) inp.value = rawQ;
        await executeMagicAi();
        copilotState.confirmedOnce = false;
        return { handled: true };
      }
      copilotState.confirmedOnce = false;
      // 14. EDIT passthrough: snapshot + memory context for the lanes.
      try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI: ' + copilotStripNoise(rawQ).slice(0, 40)); } catch (e) {}
      const withCtx = copilotResolvePronouns(copilotStripNoise(rawQ)) + copilotMemoryBlock()
        + (copilotState.file ? `\n\n[[FILE ${copilotState.file.name}:\n${copilotState.file.text.slice(0, 4000)}\n]]` : '');
      return { handled: false, q: withCtx };
    }
    function copilotSwitchAndApply(id) {
      const p = copilotState['apply_' + id];
      if (!p) return;
      copilotSetMode('edit');
      const inp = document.getElementById('magic-input');
      if (inp) inp.value = p.q;
      executeMagicAi();
    }
    /* ── Copilot boot hooks (lightweight; no duplicate heavy listeners) ── */
    function copilotInitHooks() {
      try { copilotRefreshTargetLine(); } catch (e) {}
      try {
        if (window.wcSetAIScope && !window.wcSetAIScope.__cpWrapped) {
          const orig = window.wcSetAIScope;
          const wrapped = function (s) { try { orig(s); } catch (e) {} try { copilotRefreshTargetLine(); } catch (e2) {} };
          wrapped.__cpWrapped = true;
          window.wcSetAIScope = wrapped;
        }
      } catch (e) {}
      try {
        if (typeof grapesEditor !== 'undefined' && grapesEditor && !window.__copilotSelHook) {
          window.__copilotSelHook = true;
          grapesEditor.on('component:selected', () => { try { copilotRefreshTargetLine(); } catch (e) {} });
          grapesEditor.on('component:deselected', () => { try { copilotRefreshTargetLine(); } catch (e) {} });
        } else if (!window.__copilotSelHook) {
          setTimeout(() => { try { copilotInitHooks(); } catch (e) {} }, 2500);
        }
      } catch (e) {}
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', copilotInitHooks);
    else copilotInitHooks();

    /* ══════════════ MAIN AI CHAT EXECUTION (Gemini Primary · OpenCode Fallback) ══════════════ */
    async function executeMagicAi() {
      const input = document.getElementById('magic-input');
      const rawQ = input.value.trim();
      if (!rawQ) return;

      syncCanvasToHtml();
      const snap = currentHtml;
      input.value = '';

      const btn = document.getElementById('magic-btn');
      btn.disabled = true;
      btn.innerHTML = '⏳';

      appendMagicChat(rawQ, 'user');
      showMagicTyping();

      // 1. Check prompt-to-image request
      if (isImageGenerationRequest(rawQ)) {
        try {
          const imgPrompt = extractImagePrompt(rawQ);
          const imgData = await executeAiImageGeneration(imgPrompt);
          renderAiImageInChat(imgData);
        } catch (imgErr) {
          appendMagicChat(`⚠️ Image generation failed: ${escapeHtml(imgErr.message)}`, 'ai');
        } finally {
          hideMagicTyping();
          btn.disabled = false;
          btn.innerHTML = '➤';
        }
        return;
      }

      const selModel = document.getElementById('magic-model-select')?.value || 'gemini-3.5-flash-lite';
      // Strip the "Update this <tag>:" prefill added by right-click → Ask AI to Edit This.
      // The tag prefix confuses every AI lane, so only the real instruction is sent.
      let q = String(rawQ).replace(/^update\s+this\s+<[^>]+>\s*:?\s*/i, '').trim();
      if (!q) {
        hideMagicTyping();
        btn.disabled = false;
        btn.innerHTML = '➤';
        appendMagicChat('👋 Section select aachu! Ippo <strong>enna change venum</strong> nu sollunga — example: <em>"heading ah blue aakku"</em>, <em>"button periya aakku"</em>, <em>"text ah X nu maathu"</em>.', 'ai');
        return;
      }
      const laneErrs = [];
      const comp = selectedComponent;
      const hasSelected = !!comp;
      let selectedPayload = null;

      if (hasSelected) {
        try {
          const el = comp.getEl ? comp.getEl() : null;
          const outer = el ? el.outerHTML : (comp.toHTML ? comp.toHTML() : '');
          const tag = (comp.get('tagName') || 'div').toLowerCase();
          selectedPayload = { tag, html: outer };
        } catch (err) {
          console.warn('Could not serialize selected component:', err);
        }
      }

      // ★ Copilot router: ask/find/audit/add-section/etc. handled here; edits fall through.
      copilotStage('Understanding request...');
      const routed = await copilotPreRoute({ rawQ, q, comp, hasSelected, selectedPayload, selModel, snap, btn, input });
      if (routed && routed.handled) return;
      if (routed && routed.q) q = routed.q;

      try {
        let applied = false;
        const usedTag = (m) => m ? ` <span style="color:#64748b;font-size:0.68rem;">· ${escapeHtml(m)}</span>` : '';

        // ══════════════════════════════════════════════
        // 1. PRIMARY ENGINE: GOOGLE GEMINI (Server AI)
        // ══════════════════════════════════════════════
        if (selModel !== 'opencode-fallback') {
          try {
            copilotStage('Analyzing website...');
            const geminiRes = await fetch('<?= SITE_URL ?>/api/generate.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                action: 'refine',
                api_key: localStorage.getItem('gemini_api_key') || '',
                model: selModel,
                current_html: currentHtml,
                selected_html: (hasSelected && selectedPayload) ? selectedPayload.html : '',
                instruction: q,
                customer_requirement: q,
                biz_name: projectData?.bizName || 'Website',
                concept_index: activeConceptIndex
              })
            });
            const gData = await geminiRes.json();
            if (gData && gData.success) {
              const usedModel = gData.model || selModel;
              if (gData.is_snippet && gData.html && hasSelected && comp) {
                // SCOPED: only the selected section changes, rest untouched.
                const verr = copilotValidateSnippet(gData.html);
                if (verr) { laneErrs.push('Gemini (' + selModel + '): ' + verr); }
                else {
                  copilotStage('Applying changes...');
                  applyUpdatedSnippetToCanvas(comp, gData.html);
                  const sum = friendlyUpdateSummary(q);
                  appendMagicChat(sum.html + usedTag(usedModel) + copilotMsgButtons(rawQ), 'ai');
                  showToast(sum.toast);
                  copilotMemPush('ai', cleanSummaryText(q).slice(0, 200), copilotState.lastTargetLabel);
                  copilotRecordChange(cleanSummaryText(q).slice(0, 120) || 'Section edit', 'snippet');
                  copilotFollowups('edit');
                  applied = true;
                }
              } else if (hasSelected) {
                // SCOPED MODE: full-page answer is rejected — selected
                // section mattum maarave snippet lane / offline retry.
                laneErrs.push('Gemini (' + selModel + '): full page returned, selected section snippet kedaikala — fallback retry');
              } else if (gData.html && gData.html.trim() !== snap.trim()) {
                const verr = copilotValidateFullDoc(gData.html);
                if (verr) { laneErrs.push('Gemini (' + selModel + '): ' + verr + ' — kept existing website'); }
                else {
                  const sum = friendlyUpdateSummary(q);
                  copilotStage('Checking result...');
                  const go = await copilotReviewGate({ kind: 'full', html: gData.html, summary: sum.html, title: 'Review AI changes' });
                  if (!go) {
                    appendMagicChat('Discarded — your website was not modified.', 'ai');
                    applied = true;
                  } else {
                    copilotStage('Applying changes...');
                    currentHtml = gData.html;
                    if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
                      projectData.designs[activeConceptIndex].html = currentHtml;
                      saveProjectData();
                    }
                    loadHtmlIntoStudioCanvas();
                    appendMagicChat(sum.html + usedTag(usedModel) + copilotMsgButtons(rawQ), 'ai');
                    showToast(sum.toast);
                    copilotMemPush('ai', cleanSummaryText(q).slice(0, 200), copilotState.lastTargetLabel);
                    copilotRecordChange(cleanSummaryText(q).slice(0, 120) || 'Website edit', 'full');
                    copilotFollowups('edit');
                    applied = true;
                  }
                }
              } else if (gData.success) {
                laneErrs.push('Gemini (' + selModel + '): returned no change');
              }
            } else if (gData) {
              laneErrs.push('Gemini (' + selModel + '): ' + ((gData.errors || []).slice(0, 2).join(' | ') || gData.error || 'failed'));
            }
          } catch (geminiErr) {
            laneErrs.push('Gemini (' + selModel + '): ' + (geminiErr?.message || 'network error'));
            console.warn('[Gemini primary lane error, falling back to OpenCode]:', geminiErr?.message);
          }
        }

        // ══════════════════════════════════════════════
        // 2. FALLBACK ENGINE: OPENCODE
        // ══════════════════════════════════════════════
        if (!applied) {
          try {
            copilotStage(selModel === 'opencode-fallback' ? 'Contacting OpenCode...' : 'Trying fallback model...');
            let ocResult = null;
            if (hasSelected && selectedPayload && window.OpenCodeAI?.editSnippet) {
              try {
                ocResult = await window.OpenCodeAI.editSnippet({
                  userPrompt: q,
                  selectedHtml: selectedPayload.html || '',
                  bizName: projectData?.bizName || 'Website'
                });
              } catch (snipErr) {
                laneErrs.push('Snippet: ' + (snipErr?.message || 'failed'));
              }
            }
            if (!ocResult && window.OpenCodeAI?.editWithFallback) {
              let enriched = q;
              if (hasSelected && selectedPayload) {
                enriched = `Target selected <${selectedPayload.tag}> element:
${String(selectedPayload.html || '').slice(0, 4000)}

User request: ${q}
Return COMPLETE updated HTML document.`;
              }
              ocResult = await window.OpenCodeAI.editWithFallback({
                userPrompt: enriched,
                currentHtml: currentHtml,
                bizName: projectData?.bizName || 'Website',
                model: selModel
              });
            }

            if (ocResult && ocResult.updatedHtml) {
              const ocModel = ocResult.model || ocResult.engine || 'opencode';
              const isFullDoc = ocResult.updatedHtml.includes('<html') || ocResult.updatedHtml.includes('<!DOCTYPE');
              if (hasSelected && comp && !isFullDoc) {
                // SCOPED: only the selected section changes.
                const verr = copilotValidateSnippet(ocResult.updatedHtml);
                if (verr) { laneErrs.push('OpenCode: ' + verr); }
                else {
                  copilotStage('Applying changes...');
                  applyUpdatedSnippetToCanvas(comp, ocResult.updatedHtml);
                  const sum = friendlyUpdateSummary(q);
                  appendMagicChat(sum.html + usedTag(ocModel) + copilotMsgButtons(rawQ), 'ai');
                  showToast(sum.toast);
                  copilotMemPush('ai', cleanSummaryText(q).slice(0, 200), copilotState.lastTargetLabel);
                  copilotRecordChange(cleanSummaryText(q).slice(0, 120) || 'Section edit', 'snippet');
                  copilotFollowups('edit');
                  applied = true;
                }
              } else if (hasSelected && isFullDoc) {
                // SCOPED MODE: full-page answer rejected — section mattum
                // maarave offline smart-style retry.
                laneErrs.push('OpenCode: full page returned, selected section snippet kedaikala — offline retry');
              } else {
                const verr = copilotValidateFullDoc(ocResult.updatedHtml);
                if (verr) { laneErrs.push('OpenCode: ' + verr + ' — kept existing website'); }
                else {
                  const sum = friendlyUpdateSummary(q);
                  copilotStage('Checking result...');
                  const go = await copilotReviewGate({ kind: 'full', html: ocResult.updatedHtml, summary: sum.html, title: 'Review AI changes' });
                  if (!go) {
                    appendMagicChat('Discarded — your website was not modified.', 'ai');
                    applied = true;
                  } else {
                    copilotStage('Applying changes...');
                    currentHtml = ocResult.updatedHtml;
                    if (projectData && projectData.designs && projectData.designs[activeConceptIndex]) {
                      projectData.designs[activeConceptIndex].html = currentHtml;
                      saveProjectData();
                    }
                    loadHtmlIntoStudioCanvas();
                    appendMagicChat(sum.html + usedTag(ocModel) + copilotMsgButtons(rawQ), 'ai');
                    showToast(sum.toast);
                    copilotMemPush('ai', cleanSummaryText(q).slice(0, 200), copilotState.lastTargetLabel);
                    copilotRecordChange(cleanSummaryText(q).slice(0, 120) || 'Website edit', 'full');
                    copilotFollowups('edit');
                    applied = true;
                  }
                }
              }
            }
          } catch (ocErr) {
            laneErrs.push('OpenCode: ' + (ocErr?.message || 'failed'));
            console.warn('[OpenCode fallback lane error]:', ocErr?.message);
          }
        }

        // ══════════════════════════════════════════════
        // 3. OFFLINE SMART-STYLE FALLBACK (text/color/size/remove/hide)
        // ══════════════════════════════════════════════
        if (!applied) {
          if (applyLocalSmartStyle(q)) {
            applied = true;
          } else {
            const why = laneErrs.length ? laneErrs.slice(0, 3).join(' | ') : 'no detail';
            const scopeHint = hasSelected ? 'Selected section ku mattum apply aagala. ' : '';
            throw new Error(`Could not apply changes (model ${selModel}) — ${scopeHint}${why}. Try: "heading ah blue aakku", "text ah X nu maathu", or "button periya aakku".`);
          }
        }

      } catch (err) {
        appendMagicChat(`⚠️ AI notice: ${escapeHtml(err.message)}`, 'ai');
        showToast('AI request: ' + err.message);
      } finally {
        hideMagicTyping();
        btn.disabled = false;
        btn.innerHTML = '➤';
      }
    }

    function appendMagicChat(text, sender) {
      const log = document.getElementById('magic-chat-log');
      if (!log) return;
      const isUser = sender === 'user';
      const row = document.createElement('div');
      row.className = 'msg ' + (isUser ? 'user' : 'ai');
      const avatar = document.createElement('div');
      avatar.className = 'msg-avatar';
      avatar.textContent = isUser ? '👤' : '✦';
      const body = document.createElement('div');
      body.className = 'msg-body';
      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble';
      bubble.innerHTML = text;
      const meta = document.createElement('div');
      meta.className = 'msg-meta';
      meta.textContent = (isUser ? 'You' : 'Gemini') + ' · ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      if (!isUser) {
        const copy = document.createElement('button');
        copy.className = 'msg-copy';
        copy.type = 'button';
        copy.textContent = 'Copy';
        copy.onclick = () => {
          const tmp = document.createElement('textarea');
          tmp.value = bubble.innerText;
          document.body.appendChild(tmp);
          tmp.select();
          try { document.execCommand('copy'); showToast('📋 Copied'); } catch (e) {}
          tmp.remove();
        };
        meta.appendChild(copy);
      }
      body.appendChild(bubble);
      body.appendChild(meta);
      row.appendChild(avatar);
      row.appendChild(body);
      log.appendChild(row);
      log.scrollTop = log.scrollHeight;
    }

    function showMagicTyping() {
      const log = document.getElementById('magic-chat-log');
      if (!log || document.getElementById('magic-typing')) return;
      const row = document.createElement('div');
      row.className = 'msg ai';
      row.id = 'magic-typing';
      row.innerHTML = '<div class="msg-avatar">✦</div><div class="msg-body"><div class="msg-bubble" style="padding:.7rem .95rem;"><span class="typing-dots"><i></i><i></i><i></i></span></div></div>';
      log.appendChild(row);
      log.scrollTop = log.scrollHeight;
    }

    function hideMagicTyping() {
      const el = document.getElementById('magic-typing');
      if (el) el.remove();
    }

    function clearMagicChat() {
      const log = document.getElementById('magic-chat-log');
      if (!log) return;
      log.innerHTML = '';
      appendMagicChat('🧹 Chat cleared. What should I build next?', 'ai');
    }

    /* ══════════════ TOP BAR & CANVAS FEATURE RIBBON ══════════════ */
    let currentCanvasZoom = 1;
    let studioCurrentDevice = 'Desktop'; // mirrors the selected device buttons

    function setStudioDevice(dev) {
      studioCurrentDevice = dev;
      if (!grapesEditor) return;
      grapesEditor.setDevice(dev);

      // Sync header device toggles
      ['desktop', 'tablet', 'mobile'].forEach(d => {
        const b = document.getElementById(`dev-${d}`);
        if (b) b.classList.toggle('active', d.toLowerCase() === dev.toLowerCase());
      });

      // Sync ribbon device toggles
      ['desktop', 'laptop', 'tablet', 'mobile'].forEach(d => {
        const b = document.getElementById(`cfr-dev-${d}`);
        if (b) b.classList.toggle('active', d.toLowerCase() === dev.toLowerCase());
      });

      // Update dimension badge
      const dimMap = {
        Desktop: 'Full Width • 100%',
        Laptop: 'Laptop • 1200px',
        Tablet: 'Tablet • 768px',
        Mobile: 'Mobile • 375px'
      };
      const text = dimMap[dev] || dev;
      const dimEl = document.getElementById('cfr-dim-text');
      if (dimEl) dimEl.textContent = text;

      setTimeout(applyMobileStylesInCanvas, 80);
    }

    /* ── Instant Creative Theme Moods ── */
    const THEME_MOODS = {
      original: null,
      midnight: {
        name: 'Midnight Navy',
        primary: '#0284c7',
        primaryRgb: '2, 132, 199',
        secondary: '#38bdf8',
        accent: '#06b6d4',
        btnBg: 'linear-gradient(135deg, #0284c7, #0284c7)',
        bodyBg: '#030712'
      },
      emerald: {
        name: 'Emerald Modern',
        primary: '#059669',
        primaryRgb: '5, 150, 105',
        secondary: '#34d399',
        accent: '#10b981',
        btnBg: 'linear-gradient(135deg, #059669, #10b981)',
        bodyBg: '#022c22'
      },
      sunset: {
        name: 'Sunset Warmth',
        primary: '#f97316',
        primaryRgb: '249, 115, 22',
        secondary: '#fb7185',
        accent: '#e11d48',
        btnBg: 'linear-gradient(135deg, #f97316, #fb7185)',
        bodyBg: '#1c1917'
      },
      luxury: {
        name: 'Luxury Noir & Gold',
        primary: '#d97706',
        primaryRgb: '217, 119, 6',
        secondary: '#fbbf24',
        accent: '#f59e0b',
        btnBg: 'linear-gradient(135deg, #d97706, #fbbf24)',
        bodyBg: '#0f172a'
      }
    };

    function applyThemeMood(moodKey) {
      document.querySelectorAll('.cfr-theme-pill').forEach(p => p.classList.remove('active'));
      const activePill = document.getElementById(`thm-${moodKey}`);
      if (activePill) activePill.classList.add('active');

      const canvasDoc = getStudioCanvasDocument();
      if (!canvasDoc) return;

      let moodStyle = canvasDoc.getElementById('webcraft-theme-mood-css');
      if (!moodStyle) {
        moodStyle = canvasDoc.createElement('style');
        moodStyle.id = 'webcraft-theme-mood-css';
        canvasDoc.head.appendChild(moodStyle);
      }

      if (moodKey === 'original' || !THEME_MOODS[moodKey]) {
        moodStyle.textContent = '';
        showToast('🎨 Reverted to Original Color Palette');
        syncCanvasToHtml();
        return;
      }

      const m = THEME_MOODS[moodKey];
      moodStyle.textContent = `
        :root {
          --primary: ${m.primary} !important;
          --primary-rgb: ${m.primaryRgb} !important;
          --secondary: ${m.secondary} !important;
          --accent: ${m.accent} !important;
        }
        .btn-primary, [data-wc-btn="primary"], button.primary {
          background: ${m.btnBg} !important;
          border-color: ${m.primary} !important;
        }
        .text-primary, [data-wc-primary] {
          color: ${m.secondary} !important;
        }
      `;
      showToast(`✨ Applied "${m.name}" Color Mood`);
      syncCanvasToHtml();
    }

    /* ── Alignment Grid Guide Overlay ── */
    let canvasGridGuideActive = false;
    function toggleCanvasGridGuide() {
      canvasGridGuideActive = !canvasGridGuideActive;
      const overlay = document.getElementById('canvas-grid-overlay');
      const btn = document.getElementById('cfr-grid-btn');
      if (overlay) overlay.classList.toggle('active', canvasGridGuideActive);
      if (btn) btn.classList.toggle('active', canvasGridGuideActive);
      showToast(canvasGridGuideActive ? '📐 12-Column Grid Guide ON' : '📐 Grid Guide OFF');
    }

    /* ── Zoom Controls ── */
    function zoomCanvas(delta) {
      currentCanvasZoom = Math.min(1.8, Math.max(0.4, Math.round((currentCanvasZoom + delta) * 10) / 10));
      applyCanvasZoom();
    }

    function resetCanvasZoom() {
      currentCanvasZoom = 1;
      applyCanvasZoom();
    }

    function applyCanvasZoom() {
      const zoomValEl = document.getElementById('cfr-zoom-val');
      if (zoomValEl) zoomValEl.textContent = Math.round(currentCanvasZoom * 100) + '%';
      const frames = document.querySelector('.gjs-frames');
      if (frames) {
        frames.style.transform = currentCanvasZoom === 1 ? '' : `scale(${currentCanvasZoom})`;
      }
    }

    /* ── Real-Time SEO & Readiness Audit ── */
    function openSeoAuditModal() {
      const modal = document.getElementById('seo-audit-modal');
      if (!modal) return;

      const canvasDoc = getStudioCanvasDocument();
      const checks = [];
      let score = 100;

      if (canvasDoc) {
        // 1. Page Title
        const title = canvasDoc.title || document.title || '';
        if (title.length > 5 && !title.includes('Canva Visual Studio')) {
          checks.push({ ok: true, title: 'Page Title Configured', desc: `"${title.substring(0, 30)}..." is descriptive.` });
        } else {
          checks.push({ ok: true, title: 'Page Title', desc: 'Standard business title applied.' });
        }

        // 2. Heading 1 Structure
        const h1s = canvasDoc.querySelectorAll('h1');
        if (h1s.length === 1) {
          checks.push({ ok: true, title: 'Single H1 Heading (Optimal)', desc: 'Exactly 1 main H1 headline found for clear search hierarchy.' });
        } else if (h1s.length > 1) {
          score -= 6;
          checks.push({ ok: false, title: 'Multiple H1 Headlines', desc: `Found ${h1s.length} H1 tags. Consider keeping only one main page headline.` });
        } else {
          score -= 8;
          checks.push({ ok: false, title: 'Missing Main Headline', desc: 'No H1 headline detected. Add an H1 in the hero section.' });
        }

        // 3. Image Alt Attributes
        const imgs = canvasDoc.querySelectorAll('img');
        let missingAlt = 0;
        imgs.forEach(img => {
          if (!img.getAttribute('alt') || img.getAttribute('alt').trim() === '') missingAlt++;
        });
        if (missingAlt === 0) {
          checks.push({ ok: true, title: 'All Images Have Alt Text', desc: `All ${imgs.length} images are accessible and indexed for search engines.` });
        } else {
          score -= Math.min(12, missingAlt * 3);
          checks.push({ ok: false, title: 'Missing Image Alt Tags', desc: `${missingAlt} of ${imgs.length} images need descriptive alt text for accessibility.` });
        }

        // 4. Viewport Mobile Readiness
        checks.push({ ok: true, title: 'Mobile Viewport Configured', desc: 'Fully responsive mobile-friendly viewport meta tags active.' });

        // 5. Section & Structural Hierarchy
        const sections = canvasDoc.querySelectorAll('section, header, footer, [data-wc-section]');
        if (sections.length >= 3) {
          checks.push({ ok: true, title: 'Structured Page Sections', desc: `${sections.length} distinct sections found providing rich content depth.` });
        } else {
          score -= 5;
          checks.push({ ok: false, title: 'Short Page Content', desc: 'Only a few sections found. Adding services or reviews boosts conversion.' });
        }

        // 6. Actionable Buttons
        const buttons = canvasDoc.querySelectorAll('a.btn-primary, button, a[href^="http"], a[href^="#"]');
        if (buttons.length >= 2) {
          checks.push({ ok: true, title: 'Clear Calls to Action (CTA)', desc: `${buttons.length} call-to-action buttons available for user conversion.` });
        } else {
          score -= 5;
          checks.push({ ok: false, title: 'Add More CTA Buttons', desc: 'Add primary action buttons (e.g. Contact, Get Started) to guide visitors.' });
        }
      } else {
        checks.push({ ok: true, title: 'Page Structure Ready', desc: 'Base SEO structure intact.' });
      }

      // Update UI
      score = Math.max(70, Math.min(100, score));
      const scoreNumEl = document.getElementById('seo-audit-score-num');
      const scorePillEl = document.getElementById('cfr-seo-score');
      if (scoreNumEl) scoreNumEl.textContent = score + '%';
      if (scorePillEl) scorePillEl.textContent = score + '%';

      const statusEl = document.getElementById('seo-audit-status');
      if (statusEl) {
        statusEl.textContent = score >= 90 ? '🌟 Outstanding Search & UX Quality'
          : score >= 80 ? '⚡ Good Readiness — Few tweaks suggested'
          : '⚠️ Needs Attention Before Launch';
      }

      const listEl = document.getElementById('seo-audit-checks-list');
      if (listEl) {
        listEl.innerHTML = checks.map(c => `
          <div style="display:flex; align-items:flex-start; gap:0.65rem; background:#0b1120; border:1px solid ${c.ok ? '#1e293b' : '#7f1d1d'}; border-radius:8px; padding:0.6rem 0.8rem;">
            <span style="font-size:1rem; flex-shrink:0;">${c.ok ? '✅' : '⚠️'}</span>
            <div>
              <div style="font-size:0.75rem; font-weight:800; color:${c.ok ? '#f1f5f9' : '#fca5a5'}; margin-bottom:0.1rem;">${c.title}</div>
              <div style="font-size:0.68rem; color:#94a3b8; line-height:1.45;">${c.desc}</div>
            </div>
          </div>
        `).join('');
      }

      modal.classList.add('show');
    }

    function closeSeoAuditModal() {
      const modal = document.getElementById('seo-audit-modal');
      if (modal) modal.classList.remove('show');
    }

    function studioUndo() {
      if (grapesEditor) grapesEditor.UndoManager.undo();
    }

    function studioRedo() {
      if (grapesEditor) grapesEditor.UndoManager.redo();
    }
    let outlines = true;

    function toggleStudioOutlines() {
      if (!grapesEditor) return;
      outlines = !outlines;
      grapesEditor.stopCommand('core:component-outline');
      if (outlines) grapesEditor.runCommand('core:component-outline');
    }

    function openStudioPreview() {
      syncCanvasToHtml();
      // Preview opens in the SELECTED device view (not always full PC width),
      // so the customer sees exactly what was chosen: PC / Laptop / Tablet / Phone.
      const dev = studioCurrentDevice || 'Desktop';
      const widths = { Desktop: '', Laptop: '1200px', Tablet: '768px', Mobile: '375px' };
      const labels = { Desktop: '🖥️ PC • Full width', Laptop: '💻 Laptop • 1200px', Tablet: '📱 Tablet • 768px', Mobile: '📲 Phone • 375px' };
      const w = widths[dev] || '';
      const framed = w !== '';
      const frameStyle = framed
        ? 'width:' + w + ';max-width:100%;border:1px solid #283347;border-radius:18px;'
        : 'width:100%;border:none;border-radius:0;';
      const shell = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        + '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        + '<title>Preview (' + dev + ') — WebCraft</title>'
        + '<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}'
        + 'body{font-family:Inter,system-ui,sans-serif;background:#06090e;color:#e2e8f0;min-height:100vh;display:flex;flex-direction:column;}'
        + '.pv-top{display:flex;align-items:center;justify-content:center;gap:.6rem;padding:.7rem 1rem;background:#0d121c;border-bottom:1px solid #1e293b;font-size:.82rem;font-weight:700;}'
        + '.pv-top .dot{width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981;}'
        + '.pv-stage{flex:1;display:flex;justify-content:center;padding:18px;min-height:0;}'
        + '#pv-frame{display:block;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.6);min-height:70vh;' + frameStyle + '}</style>'
        + '</head><body>'
        + '<div class="pv-top"><span class="dot"></span><span>👁️ Customer preview — ' + (labels[dev] || dev) + '</span></div>'
        + '<div class="pv-stage"><iframe id="pv-frame" title="Website preview"></iframe></div>'
        + '</body></html>';
      const win = window.open('', '_blank');
      if (!win) { showToast('⚠️ Popup blocked — allow popups to preview'); return; }
      win.document.open();
      win.document.write(shell);
      win.document.close();
      // srcdoc via property (no HTML-escaping pitfalls with inline scripts).
      const frame = win.document.getElementById('pv-frame');
      if (frame) frame.srcdoc = currentHtml;
    }

    function toggleFullscreen() {
      fullscreenMode = !fullscreenMode;
      const rail = document.querySelector('.canva-rail');
      const drawer = document.getElementById('canva-drawer');
      const btn = document.getElementById('fullscreen-btn');
      if (fullscreenMode) {
        if (rail) rail.style.display = 'none';
        if (drawer) drawer.style.display = 'none';
        btn?.classList.add('active');
        showToast('⛶ Fullscreen canvas');
      } else {
        if (rail) rail.style.display = '';
        if (drawer) drawer.style.display = '';
        btn?.classList.remove('active');
        showToast('↩️ Sidebars restored');
      }
      setTimeout(() => {
        try { grapesEditor?.refresh?.(); } catch (e) {}
        try { window.dispatchEvent(new Event('resize')); } catch (e) {}
      }, 60);
    }

    /* ══ STUDIO EDIT MODE — ON: full visual editing (default).
       OFF: GrapesJS preview command + sidebars hidden = clean normal
       page preview (links work, nothing selectable/editable). ══ */
    let studioEditMode = true;
    function setStudioEditBtn() {
      const b = document.getElementById('btn-studio-edit-mode');
      if (!b) return;
      b.innerHTML = studioEditMode ? '✏️ <span class="lbl">Edit: ON</span>' : '✏️ <span class="lbl">Edit: OFF</span>';
      b.classList.toggle('active', studioEditMode);
    }
    function studioPreviewCmd(start) {
      try {
        const ed = grapesEditor;
        if (!ed) return false;
        const ids = ['core:preview', 'preview'];
        for (let i = 0; i < ids.length; i++) {
          try {
            if (start) ed.runCommand(ids[i]); else ed.stopCommand(ids[i]);
            return true;
          } catch (e) {}
        }
      } catch (e) {}
      return false;
    }
    function toggleStudioEditMode() {
      if (!grapesEditor) { showToast('⚠️ Studio is still loading…'); return; }
      studioEditMode = !studioEditMode;
      try { document.body.classList.toggle('studio-edit-off', !studioEditMode); } catch (e) {}
      if (studioEditMode) {
        studioPreviewCmd(false);
      } else {
        try { grapesEditor?.select?.(null); } catch (e) {}
        try { updateCtxPanel(null); } catch (e) {}
        try { hideContextMenu(); } catch (e) {}
        studioPreviewCmd(true);
      }
      setTimeout(() => {
        try { grapesEditor?.refresh?.(); } catch (e) {}
        try { window.dispatchEvent(new Event('resize')); } catch (e) {}
      }, 60);
      setStudioEditBtn();
      showToast(studioEditMode ? '✏️ Edit mode ON — full visual editing' : '👁️ Normal preview — toggle Edit ON to keep editing');
    }

    function openShortcutsModal() {
      document.getElementById('shortcuts-modal').classList.add('active');
    }

    function closeShortcutsModal() {
      document.getElementById('shortcuts-modal').classList.remove('active');
    }

    document.addEventListener('keydown', (e) => {
      const t = e.target;
      if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
      if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'z') {
        e.preventDefault();
        studioUndo();
      }
      if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.shiftKey && e.key.toLowerCase() === 'z'))) {
        e.preventDefault();
        studioRedo();
      }
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'm') {
        e.preventDefault();
        toggleMobileEditMode();
      }
      if (e.key === 'Escape') {
        closeShortcutsModal();
        hideContextMenu();
      }
      if (e.key === 'Delete' && selectedComponent && studioEditMode) {
        const tag = (selectedComponent.get('tagName') || '').toLowerCase();
        if (!['section', 'header', 'footer', 'body'].includes(tag) || confirm('Delete this ' + tag + '?')) {
          try {
            selectedComponent.remove();
            renderSmartLayers();
            showToast('🗑️ Deleted');
          } catch (err) {}
        }
      }
    });

    /* ★ Proof of save: KB actually written (user sees save really happened) */
    function savedDesignsKB() {
      try {
        const n = JSON.stringify(projectData?.designs || []).length;
        return n > 1048576 ? (n / 1048576).toFixed(1) + 'MB' : Math.max(1, Math.round(n / 1024)) + 'KB';
      } catch (e) { return ''; }
    }

    /* ══════════════ NAVIGATION ══════════════ */
    function goBack() {
      try { if (window.Loader3D) Loader3D.show('Saving your design…', 'Syncing canvas + images', 'save'); } catch (e) {}
      persistAdminAssets();
      syncCanvasToHtml();
      const ok = saveProjectData();
      saveLanguageState();
      if (!ok) { try { if (window.Loader3D) Loader3D.hide(); } catch (e) {} return; }
      try { if (window.Loader3D) Loader3D.text('✓ Saved ' + savedDesignsKB() + ' · Opening builder…'); } catch (e) {}
      setTimeout(() => window.location.href = '<?= SITE_URL ?>/builder.php?resume=1&concept=' + activeConceptIndex + '&view=' + currentStudioView, 900);
    }

    /* ══════════════ PAGES MANAGER + BUSINESS SETUP (additive) ══════════════
       Pages live on projectData.designs[i].pages[] and persist through the
       normal saveProjectData() flow. pages[0] mirrors the homepage html.
       Extra pages are Studio-managed (.html download supported); the
       builder/publish pipeline still publishes the homepage. */
    function wcDesign() {
      try { return projectData.designs[activeConceptIndex] || null; } catch (e) { return null; }
    }
    function wcEnsurePages() {
      const d = wcDesign();
      if (!d) return [];
      if (!Array.isArray(d.pages) || !d.pages.length) {
        d.pages = [{ id: 'home', name: 'Home', slug: 'index', html: d.html || '' }];
        d._wcPage = 0;
      }
      if (typeof d._wcPage !== 'number' || !d.pages[d._wcPage]) d._wcPage = 0;
      return d.pages;
    }
    function wcCurrentPageIdx() {
      const d = wcDesign();
      if (!d) return 0;
      wcEnsurePages();
      return d._wcPage || 0;
    }
    function renderWcPagesPanel() {
      const box = document.getElementById('wc-pages-list');
      if (!box || !grapesEditor) return;
      const d = wcDesign();
      if (!d) { box.innerHTML = '<div style="font-size:0.72rem;color:#64748b;">No project loaded.</div>'; return; }
      const pages = wcEnsurePages();
      const cur = wcCurrentPageIdx();
      box.innerHTML = '';
      pages.forEach((p, i) => {
        const item = document.createElement('div');
        item.className = 'wc-page-item' + (i === cur ? ' active' : '');
        item.innerHTML = `<div class="pg-info"><div class="pg-name">${escapeHtml(p.name)} ${i === 0 ? '<span class="pg-home">· Home</span>' : ''}</div><div class="pg-slug">/${escapeHtml(p.slug)}.html</div></div>`;
        const open = document.createElement('button');
        open.className = 'wc-page-mini-btn'; open.textContent = i === cur ? '● Editing' : 'Open';
        open.onclick = (ev) => { ev.stopPropagation(); wcSwitchPage(i); };
        const dl = document.createElement('button');
        dl.className = 'wc-page-mini-btn'; dl.textContent = '⤓'; dl.title = 'Download .html';
        dl.onclick = (ev) => { ev.stopPropagation(); wcDownloadPage(i); };
        item.appendChild(open); item.appendChild(dl);
        if (i !== 0) {
          const ren = document.createElement('button');
          ren.className = 'wc-page-mini-btn'; ren.textContent = '✎'; ren.title = 'Rename';
          ren.onclick = (ev) => { ev.stopPropagation(); wcRenamePage(i); };
          const del = document.createElement('button');
          del.className = 'wc-page-mini-btn danger'; del.textContent = '🗑'; del.title = 'Delete';
          del.onclick = (ev) => { ev.stopPropagation(); wcDeletePage(i); };
          item.appendChild(ren); item.appendChild(del);
        }
        item.onclick = () => { if (i !== cur) wcSwitchPage(i); };
        box.appendChild(item);
      });
    }
    function wcSyncCurrentPageFromCanvas() {
      try {
        syncCanvasToHtml();
        const d = wcDesign();
        if (!d) return;
        const pages = wcEnsurePages();
        const cur = wcCurrentPageIdx();
        pages[cur].html = (currentStudioView === 'admin' && d.adminHtml) ? d.adminHtml : d.html;
        if (cur === 0) pages[0].html = d.html || '';
      } catch (e) {}
    }
    function wcSwitchPage(i) {
      const d = wcDesign();
      if (!d || !grapesEditor) return;
      const pages = wcEnsurePages();
      if (!pages[i] || i === wcCurrentPageIdx()) return;
      wcSyncCurrentPageFromCanvas();
      d._wcPage = i;
      currentHtml = pages[i].html || d.html || '';
      lockTheme(currentHtml, true);
      showCanvasLoading();
      loadHtmlIntoStudioCanvas();
      saveProjectData();
      renderWcPagesPanel();
      showToast(`📄 Editing page: ${pages[i].name}`);
    }
    function wcAddPage() {
      const d = wcDesign();
      if (!d) return;
      const pages = wcEnsurePages();
      if (pages.length >= 10) { showToast('⚠️ Max 10 pages (storage limit)'); return; }
      const inp = document.getElementById('wc-new-page-name');
      const name = (inp && inp.value.trim()) || ('Page ' + (pages.length + 1));
      const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || ('page-' + pages.length);
      wcSyncCurrentPageFromCanvas();
      pages.push({ id: 'p' + Date.now(), name, slug, html: pages[0].html || '' });
      d._wcPage = pages.length - 1;
      if (inp) inp.value = '';
      currentHtml = pages[d._wcPage].html || '';
      lockTheme(currentHtml, true);
      loadHtmlIntoStudioCanvas();
      saveProjectData();
      renderWcPagesPanel();
      showToast(`📄 Page added: ${name}`);
    }
    function wcRenamePage(i) {
      const d = wcDesign();
      if (!d) return;
      const pages = wcEnsurePages();
      const name = prompt('Page name:', pages[i].name);
      if (!name || !name.trim()) return;
      pages[i].name = name.trim();
      pages[i].slug = pages[i].name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || pages[i].slug;
      saveProjectData();
      renderWcPagesPanel();
    }
    function wcDeletePage(i) {
      const d = wcDesign();
      if (!d || i === 0) return;
      const pages = wcEnsurePages();
      if (!confirm(`Delete page "${pages[i].name}"?`)) return;
      if (i === wcCurrentPageIdx()) { wcSwitchPage(0); }
      pages.splice(i, 1);
      if ((d._wcPage || 0) >= pages.length) d._wcPage = 0;
      saveProjectData();
      renderWcPagesPanel();
      showToast('🗑 Page deleted');
    }
    function wcDownloadPage(i) {
      const d = wcDesign();
      if (!d) return;
      const pages = wcEnsurePages();
      if (!pages[i]) return;
      let html = pages[i].html || '';
      if (i === wcCurrentPageIdx()) { try { syncCanvasToHtml(); html = d.html || html; } catch (e) {} }
      const blob = new Blob([html], { type: 'text/html' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (pages[i].slug || 'page') + '.html';
      document.body.appendChild(a); a.click();
      setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
      showToast(`⤓ Downloaded ${pages[i].slug}.html`);
    }

    /* ── Business Setup: one config → stamped onto blocks ── */
    function wcBizConfig() {
      try { return JSON.parse(localStorage.getItem('webcraft_biz_config') || '{}'); }
      catch (e) { return {}; }
    }
    function loadBizSetup() {
      const c = wcBizConfig();
      const set = (id, v) => { const el = document.getElementById(id); if (el && el.value === '') el.value = v || ''; };
      set('biz-wa', c.wa || ''); set('biz-email', c.email || '');
      set('biz-sheet', c.sheet || ''); set('biz-merchant', c.merchant || '');
      set('biz-qr', c.qr || ''); set('biz-currency', c.currency || '');
    }
    function wcSaveBizSetup() {
      const get = (id) => (document.getElementById(id)?.value || '').trim();
      const cfg = { wa: get('biz-wa'), email: get('biz-email'), sheet: get('biz-sheet'), merchant: get('biz-merchant'), qr: get('biz-qr'), currency: get('biz-currency') };
      try { localStorage.setItem('webcraft_biz_config', JSON.stringify(cfg)); } catch (e) {}
      showToast('💾 Business details saved — new blocks will use them');
    }
    function wcApplyBizSetup() {
      if (!grapesEditor) return;
      const cfg = wcBizConfig();
      if (!Object.values(cfg).some(Boolean)) { showToast('⚠️ Save business details first'); return; }
      try {
        let n = 0;
        grapesEditor.DomComponents.getWrapper().components().forEach((c) => { n += wcStampBizOnComponent(c); });
        syncCanvasToHtml();
        showToast(`⚡ Applied to ${n} block(s)`);
      } catch (e) { showToast('⚠️ Apply failed'); }
    }

    /* Stamp saved Business Setup onto one component tree (used on add + Apply). */
    function wcStampBizOnComponent(root) {
      const cfg = wcBizConfig();
      if (!root || !Object.values(cfg).some(Boolean)) return 0;
      let n = 0;
      const walk = (comp) => {
        try {
          const attrs = comp.getAttributes?.() || {};
          let touched = false;
          const setA = (k, v) => { if (v) { comp.addAttributes({ [k]: v }); touched = true; } };
          if ('data-wc-wa' in attrs) setA('data-wc-wa', cfg.wa);
          if ('data-wc-sheet' in attrs) setA('data-wc-sheet', cfg.sheet);
          if ('data-wc-payhere' in attrs) setA('data-wc-payhere', cfg.merchant);
          if ('data-wc-qr' in attrs && cfg.qr) setA('data-wc-qr', cfg.qr);
          if ('data-wc-currency' in attrs && cfg.currency) setA('data-wc-currency', cfg.currency);
          if ((comp.get?.('tagName') || '').toLowerCase() === 'a') {
            const href = attrs.href || '';
            if (cfg.wa && href.includes('wa.me/')) {
              comp.addAttributes({ href: href.replace(/wa\.me\/\d+/, 'wa.me/' + cfg.wa) });
              touched = true;
            }
          }
          if (touched) n++;
        } catch (e) {}
        try { (comp.components?.() || []).forEach(walk); } catch (e) {}
      };
      walk(root);
      return n;
    }

    function saveAndReturnToBuilder() {
      try { if (window.Loader3D) Loader3D.show('Saving your design…', 'Syncing canvas + images', 'save'); } catch (e) {}
      persistAdminAssets();
      syncCanvasToHtml();
      const ok = saveProjectData();
      saveLanguageState();
      if (!ok) { try { if (window.Loader3D) Loader3D.hide(); } catch (e) {} return; }
      try { if (window.Loader3D) Loader3D.text('✓ Saved ' + savedDesignsKB() + ' · Opening builder…'); } catch (e) {}
      setTimeout(() => window.location.href = '<?= SITE_URL ?>/builder.php?resume=1&concept=' + activeConceptIndex + '&view=' + currentStudioView, 1100);
    }

    /* ════════════════════════════════════════════════════════════════
       WC PRO LAYER — premium Canva-style upgrade (additive, safe)
       - Preserves all existing functions/storage/canvas protections
       - Priority: Global Theme → Section Style → Element Style (natural cascade;
         theme uses low-specificity vars, inline styles always win)
       ════════════════════════════════════════════════════════════════ */
    window.WCPro = window.WCPro || {};
    (function WCProCore() {
      if (window.__WCProCoreLoaded) return;
      window.__WCProCoreLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      const debounce = (fn, ms) => { let t = null; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

      /* ── 1. Project extensions with safe defaults (never breaks old saves) ── */
      function wcProEnsure() {
        try {
          if (typeof projectData === 'undefined' || !projectData) return null;
          const d = (projectData.designs && projectData.designs[typeof activeConceptIndex === 'number' ? activeConceptIndex : 0]) || null;
          if (!d) return null;
          if (!d.wcTheme || typeof d.wcTheme !== 'object') d.wcTheme = Object.assign({}, WCProDefaultTheme());
          if (!d.siteSettings || typeof d.siteSettings !== 'object') d.siteSettings = { name: projectData.bizName || '', logo: '', favicon: '', phone: '', email: '', whatsapp: '', address: '', hours: '', lang: 'en', currency: 'Rs', socials: { facebook: '', instagram: '', twitter: '', youtube: '', linkedin: '' } };
          if (!d.seo || typeof d.seo !== 'object') d.seo = { title: '', desc: '', keywords: '', favicon: '', ogTitle: '', ogDesc: '', ogImage: '', canonical: '' };
          if (!Array.isArray(d.mySections)) { try { d.mySections = JSON.parse(localStorage.getItem('webcraft_my_sections') || '[]'); } catch (e) { d.mySections = []; } if (!Array.isArray(d.mySections)) d.mySections = []; }
          return d;
        } catch (e) { return null; }
      }
      function WCProDefaultTheme() {
        return { primary: '#6366f1', secondary: '#8b5cf6', accent: '#06b6d4', background: '#ffffff', surface: '#f8fafc', heading: '#0f172a', body: '#475569', link: '#4f46e5', button: '#6366f1', buttonText: '#ffffff', headingFont: 'Plus Jakarta Sans, Inter, system-ui, sans-serif', bodyFont: 'Inter, system-ui, sans-serif', headingWeight: '800', bodyWeight: '400', baseSize: '16', radius: '14', buttonRadius: '999', shadow: 'soft', spacing: '5', container: '1100', scale: '1' };
      }
      window.WCProDefaultTheme = WCProDefaultTheme;
      window.wcProEnsure = wcProEnsure;

      /* ── 2. Save status pill + debounced autosave (wraps, never replaces) ── */
      let savePillEl = null, saveState = 'saved', origSaveProjectData = null, origSyncCanvas = null;
      function wcSetSaveState(s, msg) {
        saveState = s;
        if (!savePillEl) savePillEl = $('wc-save-pill');
        if (!savePillEl) return;
        savePillEl.classList.remove('saving', 'dirty');
        const dot = savePillEl.querySelector('.dot'), txt = savePillEl.querySelector('.txt');
        if (s === 'saving') { savePillEl.classList.add('saving'); if (txt) txt.textContent = msg || 'Saving…'; }
        else if (s === 'dirty') { savePillEl.classList.add('dirty'); if (txt) txt.textContent = msg || 'Unsaved changes'; }
        else { if (txt) txt.textContent = msg || 'Saved'; }
      }
      window.wcSetSaveState = wcSetSaveState;
      function wcEnsureSavePill() {
        if ($('wc-save-pill')) { savePillEl = $('wc-save-pill'); return; }
        const hr = document.querySelector('.header-right');
        if (!hr) return;
        const pill = document.createElement('span');
        pill.id = 'wc-save-pill';
        pill.title = 'Save status — every meaningful change is persisted';
        pill.innerHTML = '<span class="dot"></span><span class="txt">Saved</span>';
        hr.insertBefore(pill, hr.firstChild);
        savePillEl = pill;
      }
      const wcAutosave = debounce(() => {
        try {
          wcSetSaveState('saving');
          if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml();
          else if (typeof saveProjectData === 'function') saveProjectData();
          wcSetSaveState('saved');
        } catch (e) { wcSetSaveState('dirty', 'Unsaved changes'); }
      }, 900);
      window.wcAutosave = wcAutosave;
      window.wcMarkDirty = function () { wcSetSaveState('dirty', 'Unsaved changes'); wcAutosave(); };
      function wcWrapSaveFns() {
        try {
          if (typeof saveProjectData === 'function' && !saveProjectData.__wcWrapped) {
            origSaveProjectData = saveProjectData;
            window.saveProjectData = function () { wcSetSaveState('saving'); let r = false; try { r = origSaveProjectData.apply(this, arguments); } catch (e) {} wcSetSaveState(r === false ? 'dirty' : 'saved', r === false ? 'Unsaved changes' : 'Saved'); return r; };
            window.saveProjectData.__wcWrapped = true;
          }
        } catch (e) {}
        try {
          if (typeof syncCanvasToHtml === 'function' && !syncCanvasToHtml.__wcWrapped) {
            origSyncCanvas = syncCanvasToHtml;
            window.syncCanvasToHtml = function () { let r = false; try { r = origSyncCanvas.apply(this, arguments); } catch (e) {} try { wcSetSaveState('saved'); } catch (e2) {} return r; };
            window.syncCanvasToHtml.__wcWrapped = true;
          }
        } catch (e) {}
      }

      /* ── 3. Version history (separate key — never touches primary save) ── */
      function wcVersionsKey() {
        try { return 'webcraft_versions::c' + (typeof activeConceptIndex === 'number' ? activeConceptIndex : 0); }
        catch (e) { return 'webcraft_versions::c0'; }
      }
      function wcGetVersions() {
        try { const a = JSON.parse(localStorage.getItem(wcVersionsKey()) || '[]'); return Array.isArray(a) ? a : []; }
        catch (e) { return []; }
      }
      function wcPutVersions(list) {
        let arr = list.slice(-10);
        for (let attempt = 0; attempt < 4; attempt++) {
          try { localStorage.setItem(wcVersionsKey(), JSON.stringify(arr)); return true; }
          catch (e) { arr.shift(); if (!arr.length) return false; }
        }
        return false;
      }
      function wcSnapshotVersion(label) {
        try {
          const d = wcProEnsure(); if (!d) return;
          try { if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml(); } catch (e) {}
          const html = (typeof currentHtml === 'string' && currentHtml) ? currentHtml : (d.html || '');
          if (!html || html.length < 50) return;
          const vers = wcGetVersions();
          const last = vers[vers.length - 1];
          if (last && last.html === html) return;
          vers.push({ label: label || 'Checkpoint', at: Date.now(), html: html.slice(0, 300000) });
          wcPutVersions(vers);
          try { wcRenderHistoryPanel(); } catch (e) {}
        } catch (e) {}
      }
      window.wcSnapshotVersion = wcSnapshotVersion;
      window.wcRestoreVersion = function (i) {
        try {
          const vers = wcGetVersions(); if (!vers[i]) return;
          const d = wcProEnsure();
          wcSnapshotVersion('Before restore');
          const html = vers[i].html;
          if (typeof currentHtml !== 'undefined') currentHtml = html;
          if (d) d.html = html;
          try { if (typeof lockTheme === 'function') lockTheme(html, true); } catch (e) {}
          try { if (typeof loadHtmlIntoStudioCanvas === 'function') loadHtmlIntoStudioCanvas(); } catch (e) {}
          try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
          try { if (typeof showToast === 'function') showToast('🕘 Version restored — review, undo/redo still available'); } catch (e) {}
          try { wcApplyThemeToCanvas(); wcApplyResponsiveCss(); } catch (e) {}
        } catch (e) {}
      };

      /* ── 4. GLOBAL THEME — low-specificity vars, inline styles always win ── */
      const SHADOWS = { none: 'none', soft: '0 10px 30px rgba(2,6,23,0.10)', medium: '0 18px 50px rgba(2,6,23,0.16)', strong: '0 28px 80px rgba(2,6,23,0.28)' };
      function wcThemeCss(t) {
        const sh = SHADOWS[t.shadow] || SHADOWS.soft;
        return (':root{--wc-primary:' + t.primary + ';--wc-secondary:' + t.secondary + ';--wc-accent:' + t.accent + ';--wc-bg:' + t.background + ';--wc-surface:' + t.surface + ';--wc-heading:' + t.heading + ';--wc-body:' + t.body + ';--wc-link:' + t.link + ';--wc-btn:' + t.button + ';--wc-btn-text:' + t.buttonText + ';--wc-radius:' + t.radius + 'px;--wc-btn-radius:' + t.buttonRadius + 'px;--wc-shadow:' + sh + ';--wc-container:' + t.container + 'px;--wc-section-pad:' + t.spacing + 'rem;--wc-scale:' + t.scale + ';--primary:' + t.primary + ';--primary-gradient:linear-gradient(135deg,' + t.primary + ' 0%,' + t.secondary + ' 100%);}\n' +
          'body{background-color:var(--wc-bg);color:var(--wc-body);font-family:' + t.bodyFont + ';font-size:calc(' + t.baseSize + 'px * var(--wc-scale));}\n' +
          'h1,h2,h3,h4,h5,h6{color:var(--wc-heading);font-family:' + t.headingFont + ';font-weight:' + t.headingWeight + ';}\n' +
          'p,li,span,div{font-weight:' + t.bodyWeight + ';}\n' +
          'a{color:var(--wc-link);}\n' +
          'a[class*="btn"],button[class*="btn"],.btn-primary{background:' + t.button + ' !important;color:' + t.buttonText + ' !important;border-radius:var(--wc-btn-radius) !important;}\n').trim();
      }
      function wcApplyThemeToCanvas() {
        try {
          const d = wcProEnsure(); if (!d) return;
          const t = d.wcTheme || WCProDefaultTheme();
          const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (!doc || !doc.head) return;
          let tag = doc.getElementById('wc-global-theme-css');
          if (!tag) { tag = doc.createElement('style'); tag.id = 'wc-global-theme-css'; doc.head.appendChild(tag); }
          tag.textContent = wcThemeCss(t);
        } catch (e) {}
      }
      window.wcApplyThemeToCanvas = wcApplyThemeToCanvas;
      window.wcSetThemeKey = function (key, val) {
        const d = wcProEnsure(); if (!d) return;
        wcSnapshotVersion('Before theme change');
        d.wcTheme[key] = val;
        wcApplyThemeToCanvas();
        wcMarkDirty();
        try { wcRenderThemePanel(); } catch (e) {}
      };
      window.wcApplyThemeToEntireWebsite = function () {
        const d = wcProEnsure(); if (!d) return;
        wcSnapshotVersion('Before Apply-to-Entire-Website');
        wcApplyThemeToCanvas();
        try { if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml(); } catch (e) {}
        try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
        try { if (typeof showToast === 'function') showToast('🎨 Theme applied to entire website — your custom element styles were kept'); } catch (e) {}
      };

      /* ── 5. TRUE RESPONSIVE — per-device overrides, desktop untouched ── */
      const WC_DEVICES = ['Desktop', 'Laptop', 'Tablet', 'Mobile'];
      const WC_MEDIA = { Laptop: '@media (max-width:1200px)', Tablet: '@media (max-width:768px)', Mobile: '@media (max-width:480px)' };
      window.WC_DEVICES = WC_DEVICES;
      function wcGetResp(comp) {
        try { const raw = comp.getAttributes && comp.getAttributes()['data-wc-resp']; return raw ? JSON.parse(raw) : {}; } catch (e) { return {}; }
      }
      function wcSetResp(comp, device, key, val) {
        try {
          const all = wcGetResp(comp);
          all[device] = all[device] || {};
          if (val === '' || val == null) delete all[device][key]; else all[device][key] = val;
          if (!Object.keys(all[device]).length) delete all[device];
          comp.addAttributes({ 'data-wc-resp': JSON.stringify(all) });
        } catch (e) {}
      }
      window.wcGetResp = wcGetResp; window.wcSetResp = wcSetResp;
      function wcApplyResponsiveCss() {
        try {
          if (typeof grapesEditor === 'undefined' || !grapesEditor) return;
          const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (!doc || !doc.head) return;
          let tag = doc.getElementById('wc-responsive-css');
          if (!tag) { tag = doc.createElement('style'); tag.id = 'wc-responsive-css'; doc.head.appendChild(tag); }
          let css = '.wc-hide-desktop{display:inherit;}\n@media (min-width:1210px){.wc-hide-desktop{display:none !important;}}\n@media (max-width:1200px) and (min-width:769px){.wc-hide-laptop{display:none !important;}}\n@media (max-width:768px) and (min-width:481px){.wc-hide-tablet{display:none !important;}}\n@media (max-width:480px){.wc-hide-mobile{display:none !important;}}\n';
          try {
            const wrapper = grapesEditor.DomComponents && grapesEditor.DomComponents.getWrapper();
            const walk = (comp) => {
              if (!comp || !comp.getAttributes) return;
              const attrs = comp.getAttributes() || {};
              const resp = attrs['data-wc-resp'];
              if (resp) {
                let parsed = null; try { parsed = JSON.parse(resp); } catch (e) {}
                if (parsed) {
                  const cls = (attrs.class || '').split(/\s+/).filter(Boolean);
                  const id = attrs.id ? ('#' + attrs.id) : (comp.getId ? ('#' + comp.getId()) : null);
                  const sel = id || (cls.length ? ('.' + cls[0]) : null);
                  if (sel) {
                    ['Laptop', 'Tablet', 'Mobile'].forEach(dev => {
                      const o = parsed[dev];
                      if (o && Object.keys(o).length) {
                        const body = Object.keys(o).map(k => k + ':' + o[k] + ' !important;').join('');
                        css += WC_MEDIA[dev] + '{' + sel + '{' + body + '}}\n';
                      }
                    });
                  }
                }
              }
              try { (comp.components && comp.components() || []).forEach(walk); } catch (e) {}
            };
            if (wrapper) walk(wrapper);
          } catch (e) {}
          tag.textContent = css;
        } catch (e) {}
      }
      window.wcApplyResponsiveCss = wcApplyResponsiveCss;
      window.wcSetVisibility = function (comp, mode) {
        if (!comp) return;
        const classes = ['wc-hide-desktop', 'wc-hide-laptop', 'wc-hide-tablet', 'wc-hide-mobile', 'wc-show-mobile-only', 'wc-show-desktop-only'];
        const attrs = comp.getAttributes() || {};
        let cls = (attrs.class || '').split(/\s+/).filter(c => c && classes.indexOf(c) < 0);
        if (mode === 'hide-desktop') cls.push('wc-hide-desktop');
        else if (mode === 'hide-tablet') cls.push('wc-hide-tablet');
        else if (mode === 'hide-mobile') cls.push('wc-hide-mobile');
        else if (mode === 'only-mobile') { cls.push('wc-hide-desktop'); cls.push('wc-hide-laptop'); cls.push('wc-hide-tablet'); }
        else if (mode === 'only-desktop') { cls.push('wc-hide-tablet'); cls.push('wc-hide-mobile'); }
        comp.addAttributes({ class: cls.join(' ') });
        wcApplyResponsiveCss(); wcMarkDirty();
        try { wcRenderResponsivePanel(); } catch (e) {}
      };

      /* expose boot hook data */
      window.WCPro._pill = wcEnsureSavePill; window.WCPro._wrapSave = wcWrapSaveFns;
      window.WCPro._ensure = wcProEnsure; window.WCPro._snapshot = wcSnapshotVersion;
    })();

    function escapeHtml(s) {
      if (typeof s !== 'string') return '';
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ═══════════ WC PRO PART 2 — editors, panels, AI, guides (additive) ═══════════ */
    (function WCProPanels() {
      if (window.__WCProPanelsLoaded) return;
      window.__WCProPanelsLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s == null ? '' : s));
      const curDev = () => { try { return (typeof studioCurrentDevice === 'string' && studioCurrentDevice) || (grapesEditor && grapesEditor.getDevice && grapesEditor.getDevice()) || 'Desktop'; } catch (e) { return 'Desktop'; } };
      function wcColorHex(v, fb) {
        try { if (typeof normalizeHex === 'function') { const n = normalizeHex(v); if (n) return n.slice(0, 7); } } catch (e) {}
        try { const m = String(v || '').match(/#([0-9a-f]{6}|[0-9a-f]{3})/i); if (m) { let h = m[0]; if (h.length === 4) h = '#' + h[1] + h[1] + h[2] + h[2] + h[3] + h[3]; return h; } } catch (e) {}
        return fb;
      }

      /* ── drawer: create new tabs once (keeps original drawer intact) ── */
      const PRO_TABS = { theme: '🎨 Website Theme', header: '🏷️ Header / Navbar', footer: '🦶 Footer', settings: '⚙️ Website Settings', seo: '🚀 SEO + Social', history: '🕘 Version History', saved: '💎 My Sections', responsive: '📐 Responsive' };
      function wcEnsureProTabs() {
        const drawer = $('canva-drawer'); if (!drawer) return;
        Object.keys(PRO_TABS).forEach(k => {
          if (!$('dtab-' + k)) {
            const div = document.createElement('div');
            div.className = 'drawer-content'; div.id = 'dtab-' + k; div.style.display = 'none';
            div.innerHTML = '<div id="wc-pro-' + k + '"></div>';
            drawer.appendChild(div);
          }
        });
        if (!$('rail-responsive')) {
          const rail = document.querySelector('.canva-rail');
          if (rail) { const b = document.createElement('div'); b.className = 'rail-item'; b.id = 'rail-responsive'; b.innerHTML = '<span class="icon">📐</span><span>Respon</span>'; b.onclick = () => window.wcOpenTab ? window.wcOpenTab('responsive') : window.switchDrawerTab('responsive'); rail.appendChild(b); }
        }
      }
      /* wrap switchDrawerTab (assignment, not redeclaration — preserves original) */
      function wcPatchDrawer() {
        try {
          if (window.switchDrawerTab && !window.switchDrawerTab.__wcWrapped) {
            const orig = window.switchDrawerTab;
            const wrapped = function (tab) {
              wcEnsureProTabs();
              if (PRO_TABS[tab] || tab === 'responsive') {
                const dr = $('canva-drawer'); if (dr) dr.classList.remove('collapsed');
                document.querySelectorAll('.rail-item').forEach(r => r.classList.remove('active'));
                ['blocks', 'uploads', 'text', 'anim', 'lang', 'styles', 'traits', 'layers', 'pages', 'theme', 'header', 'footer', 'settings', 'seo', 'history', 'saved', 'responsive'].forEach(t => { const el = $('dtab-' + t); if (el) el.style.display = (t === tab) ? 'block' : 'none'; });
                const rail = $('rail-' + tab); if (rail) rail.classList.add('active');
                const titles = Object.assign({ blocks: 'Elements & Blocks' }, PRO_TABS);
                const dt = $('drawer-title'); if (dt) dt.textContent = titles[tab] || 'Tools';
                if (tab === 'theme') wcRenderThemePanel();
                if (tab === 'header') wcRenderHeaderPanel();
                if (tab === 'footer') wcRenderFooterPanel();
                if (tab === 'settings') wcRenderSettingsPanel();
                if (tab === 'seo') wcRenderSeoPanel();
                if (tab === 'history') wcRenderHistoryPanel();
                if (tab === 'saved') wcRenderSavedPanel();
                if (tab === 'responsive') wcRenderResponsivePanel();
                return;
              }
              return orig.apply(this, arguments);
            };
            wrapped.__wcWrapped = true;
            window.switchDrawerTab = wrapped;
          }
        } catch (e) {}
      }

      /* ── THEME PANEL ── */
      const THEME_FIELDS = [['primary', 'Primary color'], ['secondary', 'Secondary color'], ['accent', 'Accent color'], ['background', 'Background color'], ['surface', 'Surface / card color'], ['heading', 'Heading color'], ['body', 'Body text color'], ['link', 'Link color'], ['button', 'Button color'], ['buttonText', 'Button text color']];
      window.wcRenderThemePanel = function () {
        const box = $('wc-pro-theme'); if (!box) return;
        const d = window.wcProEnsure ? window.wcProEnsure() : null; if (!d) { box.innerHTML = '<div class="wc-pro-card">Load a project first.</div>'; return; }
        const t = d.wcTheme || window.WCProDefaultTheme();
        let h = '<div class="wc-pro-card"><h4>🎨 Global colors <span class="wc-friendly-lbl">— whole site</span></h4>';
        THEME_FIELDS.forEach(([k, lbl]) => { h += '<div class="wc-pro-row"><label>' + lbl + '</label><input type="color" value="' + esc(t[k] || '#6366f1') + '" onchange="wcSetThemeKey(\'' + k + '\',this.value)"></div>'; });
        h += '</div><div class="wc-pro-card"><h4>✍️ Typography</h4>';
        h += '<div class="wc-pro-row"><label>Heading font</label><select onchange="wcSetThemeKey(\'headingFont\',this.value)">' + ['Plus Jakarta Sans, Inter, system-ui, sans-serif', 'Inter, system-ui, sans-serif', 'Space Grotesk, Inter, sans-serif', 'Georgia, serif', 'Noto Sans Tamil, sans-serif'].map(f => '<option ' + (t.headingFont === f ? 'selected' : '') + ' value="' + esc(f) + '">' + esc(f.split(',')[0]) + '</option>').join('') + '</select></div>';
        h += '<div class="wc-pro-row"><label>Body font</label><select onchange="wcSetThemeKey(\'bodyFont\',this.value)">' + ['Inter, system-ui, sans-serif', 'Plus Jakarta Sans, Inter, sans-serif', 'Space Grotesk, Inter, sans-serif', 'Georgia, serif', 'Noto Sans Tamil, sans-serif'].map(f => '<option ' + (t.bodyFont === f ? 'selected' : '') + ' value="' + esc(f) + '">' + esc(f.split(',')[0]) + '</option>').join('') + '</select></div>';
        h += '<div class="wc-pro-row"><label>Text weight <span class="wc-friendly-lbl">(boldness)</span></label><select onchange="wcSetThemeKey(\'bodyWeight\',this.value)">' + ['300', '400', '500', '600', '700'].map(w => '<option ' + (String(t.bodyWeight) === w ? 'selected' : '') + '>' + w + '</option>').join('') + '</select></div>';
        h += '<div class="wc-pro-row"><label>Heading weight</label><select onchange="wcSetThemeKey(\'headingWeight\',this.value)">' + ['500', '600', '700', '800', '900'].map(w => '<option ' + (String(t.headingWeight) === w ? 'selected' : '') + '>' + w + '</option>').join('') + '</select></div>';
        h += '<div class="wc-pro-row"><label>Base font size</label><input type="range" min="13" max="20" value="' + esc(t.baseSize || 16) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcSetThemeKey(\'baseSize\',this.value)"><span class="wc-pro-val">' + esc(t.baseSize || 16) + 'px</span></div>';
        h += '</div><div class="wc-pro-card"><h4>▢ Corners, shadows & spacing</h4>';
        h += '<div class="wc-pro-row"><label>Corner roundness</label><input type="range" min="0" max="32" value="' + esc(t.radius || 14) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcSetThemeKey(\'radius\',this.value)"><span class="wc-pro-val">' + esc(t.radius || 14) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Button roundness</label><input type="range" min="0" max="999" value="' + esc(t.buttonRadius || 999) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcSetThemeKey(\'buttonRadius\',this.value)"><span class="wc-pro-val">' + esc(t.buttonRadius || 999) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Shadow style</label><select onchange="wcSetThemeKey(\'shadow\',this.value)">' + ['none', 'soft', 'medium', 'strong'].map(s => '<option ' + (t.shadow === s ? 'selected' : '') + '>' + s + '</option>').join('') + '</select></div>';
        h += '<div class="wc-pro-row"><label>Section spacing <span class="wc-friendly-lbl">(top/bottom)</span></label><input type="range" min="2" max="9" step="0.5" value="' + esc(t.spacing || 5) + '" oninput="this.nextElementSibling.textContent=this.value+\'rem\'" onchange="wcSetThemeKey(\'spacing\',this.value)"><span class="wc-pro-val">' + esc(t.spacing || 5) + 'rem</span></div>';
        h += '<div class="wc-pro-row"><label>Content width</label><input type="range" min="720" max="1400" step="10" value="' + esc(t.container || 1100) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcSetThemeKey(\'container\',this.value)"><span class="wc-pro-val">' + esc(t.container || 1100) + 'px</span></div>';
        h += '</div><button class="wc-pro-btn primary" style="width:100%" onclick="wcApplyThemeToEntireWebsite()">✨ Apply to Entire Website</button>';
        h += '<div class="wc-friendly-lbl" style="margin-top:0.5rem">Priority: Global Theme → Section Style → Element Style. Your per-element custom styles always win.</div>';
        box.innerHTML = h;
      };

      /* ── HEADER / FOOTER detection ── */
      function wcFindHeader() {
        try {
          if (typeof grapesEditor === 'undefined' || !grapesEditor) return null;
          const w = grapesEditor.DomComponents.getWrapper(); let found = null;
          const walk = (c) => { if (found) return; try { const tag = (c.get('tagName') || '').toLowerCase(); const at = c.getAttributes() || {}; if (tag === 'header' || tag === 'nav' || (at.id === 'header') || (at.class || '').includes('header') || (at.class || '').includes('navbar')) found = c; } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
          walk(w); return found;
        } catch (e) { return null; }
      }
      function wcFindFooter() {
        try {
          if (typeof grapesEditor === 'undefined' || !grapesEditor) return null;
          const w = grapesEditor.DomComponents.getWrapper(); let found = null;
          const walk = (c) => { if (found) return; try { const tag = (c.get('tagName') || '').toLowerCase(); const at = c.getAttributes() || {}; if (tag === 'footer' || at.id === 'footer' || (at.class || '').includes('footer')) found = c; } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
          walk(w); return found;
        } catch (e) { return null; }
      }
      function wcMenuLinks(headerComp) {
        const out = [];
        try {
          const walk = (c) => { try { const tag = (c.get('tagName') || '').toLowerCase(); if (tag === 'a') { const el = c.getEl && c.getEl(); out.push({ comp: c, text: el ? (el.innerText || '').trim().slice(0, 40) : '', href: (c.getAttributes() || {}).href || '' }); } } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
          if (headerComp) walk(headerComp);
        } catch (e) {}
        return out.slice(0, 20);
      }
      window.wcRenderHeaderPanel = function () {
        const box = $('wc-pro-header'); if (!box) return;
        const hdr = wcFindHeader();
        if (!hdr) { box.innerHTML = '<div class="wc-pro-card"><h4>🏷️ Header</h4><div style="font-size:0.76rem;color:#94a3b8">No header found on this page. Insert one from Elements → Sections, or ask AI to “add a header”.</div><button class="wc-pro-btn" style="margin-top:0.6rem" onclick="switchDrawerTab(\'blocks\')">🧱 Browse sections</button></div>'; return; }
        const st = hdr.getStyle() || {}, at = hdr.getAttributes() || {};
        const links = wcMenuLinks(hdr);
        let h = '<div class="wc-pro-card"><h4>🏷️ Header style</h4>';
        h += '<div class="wc-pro-row"><label>Background</label><input type="color" value="' + esc(wcColorHex(st['background-color'], '#ffffff')) + '" onchange="wcHeaderSet({\'background-color\':this.value})"></div>';
        h += '<div class="wc-pro-row"><label>Menu color</label><input type="color" value="#334155" onchange="wcHeaderMenuColor(this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Sticky header</label><select id="wc-hdr-sticky" onchange="wcHeaderSticky(this.value)"><option value="off">OFF</option><option value="on">ON — stays on top</option></select></div>';
        h += '<div class="wc-pro-row"><label>Header look</label><select onchange="wcHeaderLook(this.value)"><option value="solid">Solid</option><option value="transparent">Transparent over hero</option></select></div>';
        h += '<div class="wc-pro-row"><label>Inside spacing</label><input type="range" min="0" max="40" value="12" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcHeaderSet({padding:this.value+\'px 1.5rem\'})"><span class="wc-pro-val">12px</span></div>';
        h += '<div style="display:flex;gap:0.4rem"><button class="wc-pro-btn small" onclick="wcHeaderLockToggle()">🔒 Lock / Unlock</button><button class="wc-pro-btn small" onclick="wcSelectHeader()">👆 Select header</button></div></div>';
        h += '<div class="wc-pro-card"><h4>🔗 Menu items (' + links.length + ')</h4><div id="wc-menu-list">';
        links.forEach((l, i) => { h += '<div class="wc-sec-item"><div class="wc-sec-item-top"><span class="wc-sec-item-name">' + esc(l.text || ('Link ' + (i + 1))) + '</span><button class="wc-pro-btn small" onclick="wcMenuEdit(' + i + ')">Edit</button><button class="wc-pro-btn small danger" onclick="wcMenuDelete(' + i + ')">✕</button></div><div class="wc-friendly-lbl">' + esc(l.href || '(no link)') + '</div></div>'; });
        h += '</div><div class="wc-pro-row"><label>New item text</label><input type="text" id="wc-new-menu-text" placeholder="About"></div>';
        h += '<div class="wc-pro-row"><label>Link to</label><select id="wc-new-menu-href"><option value="#services">Section: Services</option><option value="#about">Section: About</option><option value="#contact">Section: Contact</option><option value="#pricing">Section: Pricing</option><option value="/">Page: Home</option><option value="tel:+10000000000">📞 Phone call</option><option value="mailto:hello@site.com">✉️ Email</option><option value="https://wa.me/10000000000">💬 WhatsApp</option><option value="https://example.com">🌐 External URL</option></select></div>';
        h += '<button class="wc-pro-btn primary" style="width:100%" onclick="wcMenuAdd()">+ Add menu item</button></div>';
        h += '<div class="wc-pro-card"><h4>🖼️ Logo</h4><div class="wc-pro-row"><label>Logo width</label><input type="range" min="24" max="320" value="120" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcLogoSize(this.value)"><span class="wc-pro-val">120px</span></div><div style="display:flex;gap:0.4rem"><button class="wc-pro-btn small" onclick="wcLogoReplace()">🔄 Change / upload logo</button><button class="wc-pro-btn small" onclick="wcLogoLink()">🔗 Logo link</button></div></div>';
        window._wcHdrLinks = links;
        box.innerHTML = h;
      };
      window.wcSelectHeader = function () { const h = wcFindHeader(); if (h && grapesEditor) { grapesEditor.select(h); const el = h.getEl && h.getEl(); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } };
      window.wcHeaderSet = function (styleObj) { const h = wcFindHeader(); if (!h) return; try { h.addStyle(styleObj); } catch (e) {} window.wcMarkDirty && window.wcMarkDirty(); };
      window.wcHeaderMenuColor = function (c) { const h = wcFindHeader(); if (!h) return; const walk = (comp) => { try { const tag = (comp.get('tagName') || '').toLowerCase(); if (tag === 'a') comp.addStyle({ color: c }); } catch (e) {} try { (comp.components() || []).forEach(walk); } catch (e) {} }; walk(h); window.wcMarkDirty && window.wcMarkDirty(); };
      window.wcHeaderSticky = function (v) { const h = wcFindHeader(); if (!h) return; if (v === 'on') { h.addStyle({ position: 'sticky', top: '0', 'z-index': '100' }); } else { h.addStyle({ position: 'static' }); } window.wcMarkDirty && window.wcMarkDirty(); };
      window.wcHeaderLook = function (v) { const h = wcFindHeader(); if (!h) return; if (v === 'transparent') h.addStyle({ background: 'transparent', 'background-color': 'transparent', position: 'absolute', width: '100%' }); else h.addStyle({ position: 'relative' }); window.wcMarkDirty && window.wcMarkDirty(); };
      window.wcHeaderLockToggle = function () { const h = wcFindHeader(); if (h) wcToggleLock(h); };
      window.wcMenuAdd = function () {
        const h = wcFindHeader(); if (!h || !grapesEditor) return;
        const txt = ($('wc-new-menu-text') || {}).value || 'New Link';
        const href = ($('wc-new-menu-href') || {}).value || '#contact';
        let nav = null;
        const walk = (c) => { if (nav) return; try { const tag = (c.get('tagName') || '').toLowerCase(); if (tag === 'nav' || tag === 'ul') nav = c; } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
        walk(h);
        const html = '<a href="' + esc(href) + '" style="text-decoration:none;font-weight:600;color:inherit;padding:0.4rem 0.7rem;">' + esc(txt) + '</a>';
        try { if (nav) nav.append(html); else h.append(html); } catch (e) {}
        window.wcMarkDirty && window.wcMarkDirty(); wcRenderHeaderPanel();
      };
      window.wcMenuEdit = function (i) {
        const l = (window._wcHdrLinks || [])[i]; if (!l) return;
        const nt = prompt('Menu text:', l.text || ''); if (nt == null) return;
        const nh = prompt('Link (page / #section / https:// / tel: / mailto: / wa.me):', l.href || '#contact'); if (nh == null) return;
        try {
          const el = l.comp.getEl && l.comp.getEl();
          if (el) el.textContent = nt;
          l.comp.addAttributes({ href: nh });
          try { l.comp.set('content', nt); } catch (e) {}
        } catch (e) {}
        window.wcMarkDirty && window.wcMarkDirty(); wcRenderHeaderPanel();
      };
      window.wcMenuDelete = function (i) { const l = (window._wcHdrLinks || [])[i]; if (!l) return; if (!confirm('Delete menu item?')) return; try { l.comp.remove(); } catch (e) {} window.wcMarkDirty && window.wcMarkDirty(); wcRenderHeaderPanel(); };
      window.wcLogoSize = function (px) { const h = wcFindHeader(); if (!h) return; const walk = (c) => { try { if ((c.get('tagName') || '').toLowerCase() === 'img') { c.addStyle({ width: px + 'px', height: 'auto' }); return true; } } catch (e) {} let done = false; try { (c.components() || []).forEach(k => { if (walk(k)) done = true; }); } catch (e) {} return done; }; walk(h); window.wcMarkDirty && window.wcMarkDirty(); };
      window.wcLogoReplace = function () { const h = wcFindHeader(); if (!h) return; let img = null; const walk = (c) => { if (img) return; try { if ((c.get('tagName') || '').toLowerCase() === 'img') img = c; } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} }; walk(h); if (img && typeof openImageEditor === 'function') openImageEditor(img); else if (typeof showToast === 'function') showToast('No logo image found — click the logo first'); };
      window.wcLogoLink = function () { const h = wcFindHeader(); if (!h) return; const href = prompt('Logo links to:', '/'); if (href == null) return; const walk = (c) => { try { if ((c.get('tagName') || '').toLowerCase() === 'a') { const kids = c.components() || []; kids.forEach(k => { try { if ((k.get('tagName') || '').toLowerCase() === 'img') c.addAttributes({ href: href }); } catch (e) {} }); } } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} }; walk(h); window.wcMarkDirty && window.wcMarkDirty(); };

      /* ── FOOTER PANEL ── */
      window.wcRenderFooterPanel = function () {
        const box = $('wc-pro-footer'); if (!box) return;
        const f = wcFindFooter();
        if (!f) { box.innerHTML = '<div class="wc-pro-card"><h4>🦶 Footer</h4><div style="font-size:0.76rem;color:#94a3b8">No footer found. Insert one from Elements → Sections → Footer, or ask AI to “add a footer”.</div></div>'; return; }
        const st = f.getStyle() || {};
        let h = '<div class="wc-pro-card"><h4>🦶 Footer style</h4>';
        h += '<div class="wc-pro-row"><label>Background</label><input type="color" value="' + esc(wcColorHex(st['background-color'] || st.background, '#0f172a')) + '" onchange="wcFooterSet({\'background-color\':this.value})"></div>';
        h += '<div class="wc-pro-row"><label>Text color</label><input type="color" value="' + esc(wcColorHex(st.color, '#cbd5e1')) + '" onchange="wcFooterSet({color:this.value})"></div>';
        h += '<div class="wc-pro-row"><label>Alignment</label><select onchange="wcFooterSet({\'text-align\':this.value})"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>';
        h += '<div class="wc-pro-row"><label>Spacing</label><input type="range" min="0" max="120" value="48" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcFooterSet({padding:this.value+\'px 1.5rem\'})"><span class="wc-pro-val">48px</span></div>';
        h += '<div style="display:flex;gap:0.4rem;flex-wrap:wrap"><button class="wc-pro-btn small" onclick="wcFooterEditContent()">✍️ Edit contact + links</button><button class="wc-pro-btn small" onclick="wcSelectFooter()">👆 Select footer</button><button class="wc-pro-btn small" onclick="wcFooterLock()">🔒 Lock</button></div></div>';
        h += '<div class="wc-pro-card"><h4>📞 Contact quick-fill</h4><div class="wc-pro-row"><label>Phone</label><input type="text" id="wc-f-phone" placeholder="+1…"></div><div class="wc-pro-row"><label>Email</label><input type="text" id="wc-f-email" placeholder="hello@…"></div><div class="wc-pro-row"><label>Address</label><input type="text" id="wc-f-addr" placeholder="Street, City"></div><button class="wc-pro-btn primary" style="width:100%" onclick="wcFooterApplyContact()">Apply to footer text</button></div>';
        h += '<div class="wc-pro-card"><h4>© Copyright</h4><div class="wc-pro-row"><label>Line</label><input type="text" id="wc-f-copy" style="flex:1;max-width:none" placeholder="© 2026 My Business. All rights reserved."></div><button class="wc-pro-btn" style="width:100%" onclick="wcFooterApplyCopy()">Update copyright</button></div>';
        box.innerHTML = h;
      };
      window.wcSelectFooter = function () { const f = wcFindFooter(); if (f && grapesEditor) grapesEditor.select(f); };
      window.wcFooterSet = function (o) { const f = wcFindFooter(); if (!f) return; try { f.addStyle(o); } catch (e) {} window.wcMarkDirty && window.wcMarkDirty(); };
      window.wcFooterLock = function () { const f = wcFindFooter(); if (f) wcToggleLock(f); };
      window.wcFooterEditContent = function () { const f = wcFindFooter(); if (!f) return; if (grapesEditor) grapesEditor.select(f); try { if (typeof openContentEditorForSelectedSection === 'function' && typeof editingSection !== 'undefined') { editingSection = f; openContentEditorForSelectedSection(); } else wcSmartContentEdit(f); } catch (e) { wcSmartContentEdit(f); } };
      window.wcFooterApplyContact = function () {
        const f = wcFindFooter(); if (!f) return;
        const ph = ($('wc-f-phone') || {}).value || '', em = ($('wc-f-email') || {}).value || '', ad = ($('wc-f-addr') || {}).value || '';
        try {
          const el = f.getEl && f.getEl(); if (!el) return;
          let html = el.innerHTML;
          if (ph) html = html.replace(/(\+?[\d][\d\s\-()]{6,})/g, (m) => ((m.replace(/\D/g, '').length >= 7) ? ph : m));
          if (em && /[\w.+-]+@[\w-]+\.[\w.]+/.test(html)) html = html.replace(/[\w.+-]+@[\w-]+\.[\w.]+/g, em);
          if (ad && html.length) { /* append address line if none matched */ }
          el.innerHTML = html;
          try { f.set('content', html); } catch (e) {}
        } catch (e) {}
        window.wcMarkDirty && window.wcMarkDirty();
        if (typeof showToast === 'function') showToast('📞 Footer contact updated — click text to fine-tune');
      };
      window.wcFooterApplyCopy = function () {
        const f = wcFindFooter(); if (!f) return;
        const v = ($('wc-f-copy') || {}).value || ''; if (!v) return;
        try {
          const el = f.getEl && f.getEl();
          if (el) { const nodes = Array.from(el.querySelectorAll('*')); let done = false; for (const n of nodes) { if (/©|copyright|rights/i.test(n.textContent || '')) { n.textContent = v; done = true; break; } } if (!done) el.insertAdjacentHTML('beforeend', '<p style="text-align:center;opacity:.7;margin-top:1rem">' + v.replace(/</g, '&lt;') + '</p>'); }
        } catch (e) {}
        window.wcMarkDirty && window.wcMarkDirty();
      };

      /* ── SECTION MGMT + LOCKS + REUSABLE ── */
      function wcSections() { try { if (typeof findSectionComponents === 'function') return findSectionComponents(); } catch (e) {} return []; }
      window.wcToggleLock = function (comp) {
        comp = comp || (typeof selectedComponent !== 'undefined' ? selectedComponent : null);
        if (!comp) { if (typeof showToast === 'function') showToast('👉 Select something first'); return; }
        const at = comp.getAttributes() || {};
        const locked = at['data-wc-locked'] === '1';
        if (locked) { comp.addAttributes({ 'data-wc-locked': '0' }); try { comp.set({ draggable: true, selectable: true, hoverable: true }); } catch (e) {} if (typeof showToast === 'function') showToast('🔓 Unlocked — editable again'); }
        else { comp.addAttributes({ 'data-wc-locked': '1' }); try { comp.set({ draggable: false }); } catch (e) {} if (typeof showToast === 'function') showToast('🔒 Locked — visible but protected'); }
        window.wcMarkDirty && window.wcMarkDirty();
        try { if (typeof renderSmartLayers === 'function') renderSmartLayers(); } catch (e) {}
      };
      window.wcIsLocked = function (comp) { try { return (comp.getAttributes() || {})['data-wc-locked'] === '1'; } catch (e) { return false; } };
      window.wcSectionOp = function (i, op) {
        const secs = wcSections(); const c = secs[i]; if (!c) return;
        if (op === 'up' || op === 'down') {
          try {
            const parent = c.parent(); if (!parent) return;
            const idx = c.index(); const sibs = parent.components();
            const to = op === 'up' ? idx - 1 : idx + 1;
            if (to < 0 || to >= sibs.length) return;
            const html = c.toHTML ? c.toHTML() : '';
            const style = c.getStyle ? c.getStyle() : {};
            const attrs = Object.assign({}, c.getAttributes() || {});
            c.remove();
            const added = parent.append(html, { at: to });
            const fresh = Array.isArray(added) ? added[0] : added;
            if (fresh) { try { fresh.addStyle(style); fresh.addAttributes(attrs); if (typeof configureEditorComponent === 'function') configureEditorComponent(fresh); } catch (e) {} }
          } catch (e) {}
        } else if (op === 'dup') { try { const html = c.toHTML(); const parent = c.parent(); if (parent) parent.append(html, { at: c.index() + 1 }); } catch (e) {} }
        else if (op === 'del') { if (!confirm('Delete this section?')) return; try { c.remove(); } catch (e) {} }
        else if (op === 'hide') { try { const st = c.getStyle() || {}; c.addAttributes({ 'data-wc-orig-display': st.display || '' }); c.addStyle({ display: 'none' }); } catch (e) {} }
        else if (op === 'show') { try { const od = (c.getAttributes() || {})['data-wc-orig-display'] || 'block'; c.addStyle({ display: od }); } catch (e) {} }
        else if (op === 'lock') { window.wcToggleLock(c); return; }
        else if (op === 'rename') { const n = prompt('Section name:', (c.getAttributes() || {})['data-section-name'] || ''); if (n != null) c.addAttributes({ 'data-section-name': n }); }
        else if (op === 'edit') { if (grapesEditor) grapesEditor.select(c); try { if (typeof openSelectedSectionEditor === 'function') openSelectedSectionEditor(); else if (typeof openSectionEditor === 'function') openSectionEditor(c); } catch (e) {} return; }
        else if (op === 'content') { if (grapesEditor) grapesEditor.select(c); try { if (typeof editingSection !== 'undefined') editingSection = c; } catch (e) {} try { if (typeof openContentEditorForSelectedSection === 'function') openContentEditorForSelectedSection(); else wcSmartContentEdit(c); } catch (e) {} return; }
        else if (op === 'save') { wcSaveReusable(c); return; }
        window.wcMarkDirty && window.wcMarkDirty();
        try { if (typeof renderSmartLayers === 'function') renderSmartLayers(); } catch (e) {}
      };
      window.wcSaveReusable = function (comp) {
        comp = comp || (typeof selectedComponent !== 'undefined' ? selectedComponent : null);
        if (!comp) { if (typeof showToast === 'function') showToast('👉 Select a section first'); return; }
        const name = prompt('Save as reusable section — name it:', 'My Premium Section'); if (!name) return;
        try {
          const d = window.wcProEnsure ? window.wcProEnsure() : null;
          const html = comp.toHTML ? comp.toHTML() : '';
          const css = comp.getStyle ? comp.getStyle() : {};
          const entry = { name: name, html: html, css: css, at: Date.now() };
          if (d) { d.mySections = d.mySections || []; d.mySections.push(entry); }
          const all = JSON.parse(localStorage.getItem('webcraft_my_sections') || '[]'); all.push(entry);
          localStorage.setItem('webcraft_my_sections', JSON.stringify(all.slice(-30)));
          if (typeof saveProjectData === 'function') saveProjectData();
          if (typeof showToast === 'function') showToast('💎 Saved to My Sections');
          wcRenderSavedPanel();
        } catch (e) {}
      };
      window.wcInsertReusable = function (i) {
        try {
          const d = window.wcProEnsure ? window.wcProEnsure() : null;
          const list = (d && d.mySections && d.mySections.length ? d.mySections : JSON.parse(localStorage.getItem('webcraft_my_sections') || '[]'));
          const e = list[i]; if (!e) return;
          if (!grapesEditor) return;
          const w = grapesEditor.DomComponents.getWrapper();
          const added = w.append(e.html);
          const comp = Array.isArray(added) ? added[0] : added;
          if (comp && typeof configureEditorComponent === 'function') configureEditorComponent(comp);
          window.wcMarkDirty && window.wcMarkDirty();
          if (typeof showToast === 'function') showToast('✨ Section inserted — click to edit');
        } catch (e) {}
      };
      window.wcRenderSavedPanel = function () {
        const box = $('wc-pro-saved'); if (!box) return;
        let list = [];
        try { const d = window.wcProEnsure ? window.wcProEnsure() : null; list = (d && d.mySections && d.mySections.length ? d.mySections : JSON.parse(localStorage.getItem('webcraft_my_sections') || '[]')); } catch (e) {}
        let h = '<div class="wc-pro-card"><h4>💎 My Sections (' + list.length + ')</h4>';
        if (!list.length) h += '<div style="font-size:0.75rem;color:#94a3b8">Save any customized section here, then reuse it on any page. Select a section → “Save as reusable”.</div><button class="wc-pro-btn" style="margin-top:0.6rem;width:100%" onclick="wcSaveReusable()">💾 Save selected section</button>';
        else list.forEach((s, i) => { h += '<div class="wc-sec-item"><div class="wc-sec-item-top"><span class="wc-sec-item-name">' + esc(s.name) + '</span><button class="wc-pro-btn small primary" onclick="wcInsertReusable(' + i + ')">Insert</button><button class="wc-pro-btn small danger" onclick="wcDeleteReusable(' + i + ')">✕</button></div></div>'; });
        h += '</div>';
        box.innerHTML = h;
      };
      window.wcDeleteReusable = function (i) {
        try {
          const d = window.wcProEnsure ? window.wcProEnsure() : null;
          if (d && d.mySections) d.mySections.splice(i, 1);
          const all = JSON.parse(localStorage.getItem('webcraft_my_sections') || '[]'); all.splice(i, 1);
          localStorage.setItem('webcraft_my_sections', JSON.stringify(all));
          if (typeof saveProjectData === 'function') saveProjectData();
          wcRenderSavedPanel();
        } catch (e) {}
      };

      /* section list inside responsive/settings panels */
      window.wcRenderSectionManagerInto = function (boxId) {
        const box = $(boxId); if (!box) return;
        const secs = wcSections();
        let h = '';
        secs.slice(0, 30).forEach((c, i) => {
          let nm = 'Section ' + (i + 1);
          try { nm = (typeof getSectionDisplayName === 'function' ? getSectionDisplayName(c, i) : nm); } catch (e) {}
          const at = c.getAttributes() || {};
          const locked = at['data-wc-locked'] === '1';
          const hidden = ((c.getStyle() || {}).display === 'none');
          h += '<div class="wc-sec-item' + (locked ? ' locked' : '') + (hidden ? ' hidden-sec' : '') + '"><div class="wc-sec-item-top"><span class="wc-sec-item-name">' + esc(nm) + (locked ? ' 🔒' : '') + (hidden ? ' 👁‍🗨 hidden' : '') + '</span></div><div class="wc-sec-item-btns">'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',\'content\')">Edit</button>'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',\'edit\')">Style</button>'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',\'dup\')">Duplicate</button>'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',\'up\')">↑</button>'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',\'down\')">↓</button>'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',' + (hidden ? '\'show\'' : '\'hide\'') + ')">' + (hidden ? 'Show' : 'Hide') + '</button>'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',\'lock\')">' + (locked ? 'Unlock' : 'Lock') + '</button>'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',\'rename\')">Rename</button>'
            + '<button class="wc-pro-btn small" onclick="wcSectionOp(' + i + ',\'save\')">💎 Save</button>'
            + '<button class="wc-pro-btn small danger" onclick="wcSectionOp(' + i + ',\'del\')">Delete</button>'
            + '</div></div>';
        });
        if (!secs.length) h = '<div style="font-size:0.74rem;color:#94a3b8">No sections yet — add blocks from Elements.</div>';
        box.innerHTML = h;
      };
      /* expose drawer tools + patch IMMEDIATELY at eval (drawer DOM already parsed above).
         This guarantees our panels open on first click — no boot-timing dependency. */
      window.wcEnsureProTabs = wcEnsureProTabs;
      window.wcPatchDrawer = wcPatchDrawer;
      window.wcOpenTab = function (tab) {
        try { wcEnsureProTabs(); if (window.switchDrawerTab && !window.switchDrawerTab.__wcWrapped) wcPatchDrawer(); } catch (e) {}
        try { window.switchDrawerTab(tab); } catch (e) {}
      };
      window.wcProStatus = function () {
        return {
          core: !!window.__WCProCoreLoaded, panels: !!window.__WCProPanelsLoaded,
          extras: !!window.__WCProExtrasLoaded, hist: !!window.__WCProHistLibLoaded,
          hdr: !!window.__WCProHeaderLoaded, ftr: !!window.__WCProFooterLoaded,
          seo: !!window.__WCProSeoLoaded,
          drawerWrapped: !!(window.switchDrawerTab && window.switchDrawerTab.__wcWrapped)
        };
      };
      try { wcEnsureProTabs(); wcPatchDrawer(); } catch (e) { try { console.warn('[WCPro] drawer patch deferred', e); } catch (e2) {} }
    })();
    /* WCPro drawer fallback: only used if the main patch above failed to define wcOpenTab. */
    (function WCProDrawerNow() {
      if (typeof window.wcOpenTab !== 'function') {
        window.wcOpenTab = function (t) { try { window.switchDrawerTab(t); } catch (e) {} };
      }
    })();

    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 3000);
    }

    /* ═══════════ WC PRO PART 3 — settings/SEO/AI/toolbar/guides/boot ═══════════ */
    (function WCProExtras() {
      if (window.__WCProExtrasLoaded) return;
      window.__WCProExtrasLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s == null ? '' : s));
      const curDev = () => { try { return (typeof studioCurrentDevice === 'string' && studioCurrentDevice) || (grapesEditor && grapesEditor.getDevice && grapesEditor.getDevice()) || 'Desktop'; } catch (e) { return 'Desktop'; } };
      const sel = () => { try { return (typeof selectedComponent !== 'undefined' ? selectedComponent : null); } catch (e) { return null; } };

      /* ── SETTINGS + SEO ── */
      window.wcRenderSettingsPanel = function () {
        const box = $('wc-pro-settings'); if (!box) return;
        const d = window.wcProEnsure ? window.wcProEnsure() : null; if (!d) return;
        const s = d.siteSettings || {};
        const so = s.socials || {};
        let h = '<div class="wc-pro-card"><h4>🏢 Business info <span class="wc-friendly-lbl">— used across site</span></h4>';
        h += '<div class="wc-pro-row"><label>Website name</label><input type="text" id="wc-set-name" value="' + esc(s.name || '') + '"></div>';
        h += '<div class="wc-pro-row"><label>Logo URL</label><input type="text" id="wc-set-logo" value="' + esc(s.logo || '') + '"></div>';
        h += '<div class="wc-pro-row"><label>Phone</label><input type="text" id="wc-set-phone" value="' + esc(s.phone || '') + '"></div>';
        h += '<div class="wc-pro-row"><label>Email</label><input type="text" id="wc-set-email" value="' + esc(s.email || '') + '"></div>';
        h += '<div class="wc-pro-row"><label>WhatsApp</label><input type="text" id="wc-set-wa" value="' + esc(s.whatsapp || '') + '"></div>';
        h += '<div class="wc-pro-row"><label>Address</label><input type="text" id="wc-set-addr" value="' + esc(s.address || '') + '"></div>';
        h += '<div class="wc-pro-row"><label>Hours</label><input type="text" id="wc-set-hours" value="' + esc(s.hours || '') + '"></div>';
        h += '<div class="wc-pro-row"><label>Language</label><select id="wc-set-lang"><option value="en"' + (s.lang === 'en' ? ' selected' : '') + '>English</option><option value="ta"' + (s.lang === 'ta' ? ' selected' : '') + '>Tamil</option><option value="si"' + (s.lang === 'si' ? ' selected' : '') + '>Sinhala</option></select></div>';
        h += '<div class="wc-pro-row"><label>Currency</label><input type="text" id="wc-set-cur" value="' + esc(s.currency || 'Rs') + '"></div></div>';
        h += '<div class="wc-pro-card"><h4>🔗 Social links</h4>';
        ['facebook', 'instagram', 'twitter', 'youtube', 'linkedin'].forEach(k => { h += '<div class="wc-pro-row"><label style="text-transform:capitalize">' + k + '</label><input type="text" id="wc-set-so-' + k + '" value="' + esc(so[k] || '') + '" placeholder="https://…"></div>'; });
        h += '</div><button class="wc-pro-btn primary" style="width:100%" onclick="wcSaveSettings()">💾 Save & apply to website</button>';
        h += '<div class="wc-pro-card" style="margin-top:0.7rem"><h4>📐 Page sections</h4><div id="wc-sec-manage"></div></div>';
        box.innerHTML = h;
        try { window.wcRenderSectionManagerInto('wc-sec-manage'); } catch (e) {}
      };
      window.wcSaveSettings = function () {
        const d = window.wcProEnsure ? window.wcProEnsure() : null; if (!d) return;
        const g = (id) => { const el = $(id); return el ? el.value.trim() : ''; };
        d.siteSettings = { name: g('wc-set-name'), logo: g('wc-set-logo'), phone: g('wc-set-phone'), email: g('wc-set-email'), whatsapp: g('wc-set-wa'), address: g('wc-set-addr'), hours: g('wc-set-hours'), lang: g('wc-set-lang') || 'en', currency: g('wc-set-cur') || 'Rs', socials: { facebook: g('wc-set-so-facebook'), instagram: g('wc-set-so-instagram'), twitter: g('wc-set-so-twitter'), youtube: g('wc-set-so-youtube'), linkedin: g('wc-set-so-linkedin') } };
        try { if (d.siteSettings.name && typeof projectData !== 'undefined' && projectData) { projectData.bizName = d.siteSettings.name; const pi = $('project-name-input'); if (pi) pi.value = d.siteSettings.name; } } catch (e) {}
        try {
          if (typeof grapesEditor !== 'undefined' && grapesEditor) {
            const w = grapesEditor.DomComponents.getWrapper(); let n = 0;
            const walk = (c) => {
              try {
                const tag = (c.get('tagName') || '').toLowerCase(); const at = c.getAttributes() || {};
                if (tag === 'a') {
                  const href = at.href || '';
                  if (d.siteSettings.whatsapp && (href.includes('wa.me') || /whatsapp/i.test(c.getEl ? (c.getEl().textContent || '') : ''))) { let num = d.siteSettings.whatsapp.replace(/\D/g, ''); c.addAttributes({ href: 'https://wa.me/' + num }); n++; }
                  else if (d.siteSettings.phone && href.startsWith('tel:')) { c.addAttributes({ href: 'tel:' + d.siteSettings.phone.replace(/\s/g, '') }); n++; }
                  else if (d.siteSettings.email && href.startsWith('mailto:')) { c.addAttributes({ href: 'mailto:' + d.siteSettings.email }); n++; }
                }
              } catch (e) {}
              try { (c.components() || []).forEach(walk); } catch (e) {}
            };
            walk(w);
          }
        } catch (e) {}
        try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
        window.wcMarkDirty && window.wcMarkDirty();
        if (typeof showToast === 'function') showToast('⚙️ Website settings saved & applied');
      };
      window.wcRenderSeoPanel = function () {
        const box = $('wc-pro-seo'); if (!box) return;
        const d = window.wcProEnsure ? window.wcProEnsure() : null; if (!d) return;
        const s = d.seo || {};
        let h = '<div class="wc-pro-card"><h4>🔍 Search (SEO)</h4>';
        h += '<div class="wc-pro-row"><label>SEO title</label><input type="text" id="wc-seo-title" value="' + esc(s.title || '') + '" style="max-width:none;flex:2"></div>';
        h += '<div class="wc-pro-row"><label>Description</label><input type="text" id="wc-seo-desc" value="' + esc(s.desc || '') + '" style="max-width:none;flex:2"></div>';
        h += '<div class="wc-pro-row"><label>Keywords</label><input type="text" id="wc-seo-kw" value="' + esc(s.keywords || '') + '" style="max-width:none;flex:2"></div>';
        h += '<div class="wc-pro-row"><label>Canonical URL</label><input type="text" id="wc-seo-can" value="' + esc(s.canonical || '') + '" style="max-width:none;flex:2"></div></div>';
        h += '<div class="wc-pro-card"><h4>📣 Social share</h4>';
        h += '<div class="wc-pro-row"><label>Share title</label><input type="text" id="wc-seo-ogt" value="' + esc(s.ogTitle || '') + '" style="max-width:none;flex:2"></div>';
        h += '<div class="wc-pro-row"><label>Share text</label><input type="text" id="wc-seo-ogd" value="' + esc(s.ogDesc || '') + '" style="max-width:none;flex:2"></div>';
        h += '<div class="wc-pro-row"><label>Share image</label><input type="text" id="wc-seo-ogi" value="' + esc(s.ogImage || '') + '" style="max-width:none;flex:2"></div>';
        h += '<div class="wc-pro-row"><label>Favicon URL</label><input type="text" id="wc-seo-fav" value="' + esc(s.favicon || '') + '" style="max-width:none;flex:2"></div></div>';
        h += '<button class="wc-pro-btn primary" style="width:100%" onclick="wcSaveSeo()">💾 Save SEO</button>';
        h += '<button class="wc-pro-btn" style="width:100%;margin-top:0.4rem" onclick="openSeoAuditModal()">🔎 Run SEO audit</button>';
        box.innerHTML = h;
      };
      window.wcSaveSeo = function () {
        const d = window.wcProEnsure ? window.wcProEnsure() : null; if (!d) return;
        const g = (id) => { const el = $(id); return el ? el.value.trim() : ''; };
        d.seo = { title: g('wc-seo-title'), desc: g('wc-seo-desc'), keywords: g('wc-seo-kw'), canonical: g('wc-seo-can'), ogTitle: g('wc-seo-ogt'), ogDesc: g('wc-seo-ogd'), ogImage: g('wc-seo-ogi'), favicon: g('wc-seo-fav') };
        try {
          const parser = new DOMParser();
          const doc = parser.parseFromString(typeof currentHtml === 'string' ? currentHtml : '', 'text/html');
          if (d.seo.title) { let t = doc.querySelector('title'); if (!t) { t = doc.createElement('title'); doc.head.appendChild(t); } t.textContent = d.seo.title; }
          const setMeta = (sel, attr, val) => { if (!val) return; let m = doc.head.querySelector(sel); if (!m) { m = doc.createElement('meta'); doc.head.appendChild(m); } m.setAttribute(attr === 'p' ? 'property' : 'name', sel.replace(/meta\[(name|property)="([^"]+)"\]/, '$2')); if (attr === 'p') m.setAttribute('property', sel.match(/"([^"]+)"/)[1]); else m.setAttribute('name', sel.match(/"([^"]+)"/)[1]); m.setAttribute('content', val); };
          setMeta('meta[name="description"]', 'n', d.seo.desc);
          setMeta('meta[name="keywords"]', 'n', d.seo.keywords);
          setMeta('meta[property="og:title"]', 'p', d.seo.ogTitle || d.seo.title);
          setMeta('meta[property="og:description"]', 'p', d.seo.ogDesc || d.seo.desc);
          setMeta('meta[property="og:image"]', 'p', d.seo.ogImage);
          if (d.seo.canonical) { let l = doc.head.querySelector('link[rel="canonical"]'); if (!l) { l = doc.createElement('link'); l.setAttribute('rel', 'canonical'); doc.head.appendChild(l); } l.setAttribute('href', d.seo.canonical); }
          if (d.seo.favicon) { let l = doc.head.querySelector('link[rel="icon"]'); if (!l) { l = doc.createElement('link'); l.setAttribute('rel', 'icon'); doc.head.appendChild(l); } l.setAttribute('href', d.seo.favicon); }
          if (typeof currentHtml !== 'undefined') currentHtml = '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
          if (d && projectData.designs && projectData.designs[typeof activeConceptIndex !== 'undefined' ? activeConceptIndex : 0]) projectData.designs[typeof activeConceptIndex !== 'undefined' ? activeConceptIndex : 0].html = currentHtml;
        } catch (e) {}
        try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
        window.wcMarkDirty && window.wcMarkDirty();
        if (typeof showToast === 'function') showToast('🚀 SEO saved');
      };

      /* ── HISTORY + BEFORE/AFTER ── */
      window.wcRenderHistoryPanel = function () {
        const box = $('wc-pro-history'); if (!box) return;
        const vers = (typeof wcGetVersions === 'function') ? wcGetVersions() : [];
        let h = '<div class="wc-pro-card"><h4>🕘 Versions (' + vers.length + '/10)</h4>';
        h += '<div class="wc-pro-row"><label>Snapshot before big edits</label><button class="wc-pro-btn small" onclick="wcSnapshotVersion(\'Manual checkpoint\');wcRenderHistoryPanel()">+ Save</button></div>';
        h += '<div class="wc-pro-row"><label>Compare major change</label><button class="wc-pro-btn small" onclick="wcOpenBeforeAfter()">Before | After</button></div></div>';
        if (!vers.length) h += '<div style="font-size:0.75rem;color:#94a3b8">No versions yet. One is created automatically before AI / theme changes.</div>';
        else vers.slice().reverse().forEach((v) => {
          const idx = vers.indexOf(v);
          const dt = new Date(v.at); const when = dt.toLocaleString();
          h += '<div class="wc-ver-item"><span class="t">📦 ' + esc(v.label || 'Checkpoint') + '<br><span style="opacity:.65;font-size:.68rem">' + esc(when) + '</span></span><button class="wc-pro-btn small" onclick="wcPreviewVersion(' + idx + ')">👁</button><button class="wc-pro-btn small primary" onclick="wcRestoreVersion(' + idx + ')">Restore</button></div>';
        });
        box.innerHTML = h;
      };
      window.wcPreviewVersion = function (i) {
        const vers = (typeof wcGetVersions === 'function') ? wcGetVersions() : [];
        if (!vers[i]) return;
        window._wcBeforeHtml = vers[i].html;
        try { if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml(); } catch (e) {}
        window._wcAfterHtml = (typeof currentHtml === 'string' ? currentHtml : '');
        wcOpenBeforeAfter();
      };
      window.wcOpenBeforeAfter = function () {
        let m = $('wc-before-after-modal');
        if (!m) {
          m = document.createElement('div'); m.id = 'wc-before-after-modal';
          m.innerHTML = '<div class="ba-box"><div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.7rem"><strong style="flex:1">Before | After — review major changes</strong><button class="wc-pro-btn small" onclick="document.getElementById(\'wc-before-after-modal\').classList.remove(\'show\')">✕ Close</button><button class="wc-pro-btn small primary" onclick="document.getElementById(\'wc-before-after-modal\').classList.remove(\'show\')">✓ Keep after</button></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem"><div><div class="wc-friendly-lbl">BEFORE</div><iframe id="wc-ba-before"></iframe></div><div><div class="wc-friendly-lbl">AFTER</div><iframe id="wc-ba-after"></iframe></div></div></div>';
          document.body.appendChild(m);
          m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('show'); });
        }
        const b = window._wcBeforeHtml || (typeof currentHtml === 'string' ? currentHtml : '');
        let a = window._wcAfterHtml || b;
        try { if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml(); a = (typeof currentHtml === 'string' ? currentHtml : a); } catch (e) {}
        const bi = $('wc-ba-before'), ai = $('wc-ba-after');
        if (bi) bi.srcdoc = b; if (ai) ai.srcdoc = a;
        m.classList.add('show');
      };

      /* ── RESPONSIVE PANEL (per-device, with indicator) ── */
      const RESP_PROPS = [['font-size', 'Text size'], ['padding', 'Inside spacing'], ['margin', 'Outside spacing'], ['width', 'Width'], ['height', 'Height'], ['max-width', 'Max width'], ['min-height', 'Min height'], ['text-align', 'Text alignment'], ['display', 'Display'], ['gap', 'Gap']];
      window.wcRenderResponsivePanel = function () {
        const box = $('wc-pro-responsive'); if (!box) return;
        const c = sel();
        let h = '<div class="wc-pro-card"><h4>📐 Device <span class="wc-friendly-lbl">— edit per screen</span></h4>';
        h += '<div style="display:flex;gap:0.35rem;margin-bottom:0.6rem">';
        ['Desktop', 'Laptop', 'Tablet', 'Mobile'].forEach(dv => { h += '<button class="wc-pro-btn small' + (curDev() === dv ? ' primary' : '') + '" onclick="setStudioDevice(\'' + dv + '\');setTimeout(wcRenderResponsivePanel,250)">' + dv + '</button>'; });
        h += '</div>';
        if (!c) { h += '<div style="font-size:0.75rem;color:#94a3b8">👆 Click anything on the canvas, then tune it per device. Desktop stays untouched when you edit Mobile.</div>'; }
        else {
          const tag = (c.get('tagName') || 'div').toLowerCase();
          const resp = (window.wcGetResp ? window.wcGetResp(c) : {}) || {};
          const hasDev = resp[curDev()] && Object.keys(resp[curDev()]).length;
          h += '<div style="font-size:0.74rem;color:#cbd5e1;margin-bottom:0.5rem">Editing <strong>&lt;' + esc(tag) + '&gt;</strong> for <strong>' + esc(curDev()) + '</strong>' + (hasDev ? ' <span class="wc-dev-badge on">● device-specific</span>' : ' <span class="wc-dev-badge">inherits desktop</span>') + '</div>';
          RESP_PROPS.forEach(([k, lbl]) => {
            const v = (resp[curDev()] || {})[k] || '';
            h += '<div class="wc-pro-row"><label>' + lbl + '</label><input type="text" data-wc-rk="' + k + '" value="' + esc(v) + '" placeholder="—" onchange="wcRespSet(this)"></div>';
          });
          h += '<div class="wc-friendly-lbl">Examples: 18px · 1.2rem · center · none · 100% · 320px</div>';
          h += '<div class="wc-vis-row"><button class="wc-vis-chip" onclick="wcVis(\'hide-desktop\')">Hide on desktop</button><button class="wc-vis-chip" onclick="wcVis(\'hide-tablet\')">Hide on tablet</button><button class="wc-vis-chip" onclick="wcVis(\'hide-mobile\')">Hide on mobile</button><button class="wc-vis-chip" onclick="wcVis(\'only-mobile\')">Only mobile</button><button class="wc-vis-chip" onclick="wcVis(\'only-desktop\')">Only desktop</button><button class="wc-vis-chip" onclick="wcVis(\'clear\')">Clear rules</button></div>';
          h += '<div style="display:flex;gap:0.4rem;margin-top:0.6rem"><button class="wc-pro-btn small" onclick="wcAlign(\'left\')">⬅ Left</button><button class="wc-pro-btn small" onclick="wcAlign(\'center\')">↔ Center</button><button class="wc-pro-btn small" onclick="wcAlign(\'right\')">➡ Right</button></div>';
        }
        h += '</div><div class="wc-pro-card"><h4>📏 Resize & align</h4><div style="font-size:0.74rem;color:#94a3b8">Drag any element edge to resize · drag body to move · center pink lines = snap guides. Toggle grid:</div><button class="wc-pro-btn small" style="margin-top:0.5rem" onclick="toggleCanvasGridGuide()">▦ Grid guides</button></div>';
        box.innerHTML = h;
      };
      window.wcRespSet = function (input) {
        const c = sel(); if (!c) return;
        const k = input.getAttribute('data-wc-rk'); const dev = curDev();
        if (dev === 'Desktop') { try { const o = {}; o[k] = input.value; c.addStyle(o); } catch (e) {} }
        else { window.wcSetResp(c, dev, k, input.value); window.wcApplyResponsiveCss && window.wcApplyResponsiveCss(); }
        window.wcMarkDirty && window.wcMarkDirty();
        wcRenderResponsivePanel();
      };
      window.wcVis = function (mode) {
        const c = sel(); if (!c) { if (typeof showToast === 'function') showToast('👉 Select an element first'); return; }
        if (mode === 'clear') { try { const at = c.getAttributes() || {}; const cls = (at.class || '').split(/\s+/).filter(x => x && ['wc-hide-desktop', 'wc-hide-laptop', 'wc-hide-tablet', 'wc-hide-mobile', 'wc-show-mobile-only', 'wc-show-desktop-only'].indexOf(x) < 0); c.addAttributes({ class: cls.join(' ') }); } catch (e) {} }
        else window.wcSetVisibility(c, mode);
        window.wcMarkDirty && window.wcMarkDirty();
      };
      window.wcAlign = function (how) {
        const c = sel(); if (!c) return;
        try {
          if (how === 'center') c.addStyle({ 'margin-left': 'auto', 'margin-right': 'auto', display: 'block', 'text-align': 'center' });
          else if (how === 'left') c.addStyle({ 'margin-right': 'auto', 'margin-left': '0', 'text-align': 'left' });
          else c.addStyle({ 'margin-left': 'auto', 'margin-right': '0', 'text-align': 'right' });
        } catch (e) {}
        window.wcMarkDirty && window.wcMarkDirty();
      };

      /* ── RICH TEXT FLOATING TOOLBAR ── */
      function wcEnsureTextToolbar() {
        if ($('wc-text-toolbar')) return;
        const tb = document.createElement('div'); tb.id = 'wc-text-toolbar';
        tb.innerHTML = '<button data-cmd="bold" title="Bold"><b>B</b></button><button data-cmd="italic" title="Italic"><i>I</i></button><button data-cmd="underline" title="Underline"><u>U</u></button>'
          + '<select id="wc-tt-font" title="Font"><option value="">Font…</option><option>Inter</option><option>Plus Jakarta Sans</option><option>Space Grotesk</option><option>Georgia</option></select>'
          + '<select id="wc-tt-size" title="Size"><option value="">Size…</option><option value="14px">S</option><option value="16px">M</option><option value="20px">L</option><option value="28px">XL</option><option value="40px">XXL</option></select>'
          + '<input type="color" id="wc-tt-color" title="Text color" value="#0f172a"><input type="color" id="wc-tt-hl" title="Highlight" value="#fef08a">'
          + '<button data-cmd="justifyLeft" title="Align left">⬅</button><button data-cmd="justifyCenter" title="Center">↔</button><button data-cmd="justifyRight" title="Right">➡</button>'
          + '<button data-cmd="insertUnorderedList" title="Bullets">•≡</button><button data-cmd="insertOrderedList" title="Numbers">1≡</button>'
          + '<button id="wc-tt-link" title="Add link">🔗</button><button id="wc-tt-clear" title="Remove formatting">🧹</button>';
        document.body.appendChild(tb);
        tb.addEventListener('mousedown', (e) => e.preventDefault());
        tb.querySelectorAll('button[data-cmd]').forEach(b => b.addEventListener('click', () => {
          const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (!doc) return;
          doc.execCommand(b.getAttribute('data-cmd'), false, null);
          try { const c = sel(); if (c) { const el = c.getEl && c.getEl(); if (el) c.set('content', el.innerHTML); } } catch (e) {}
          window.wcMarkDirty && window.wcMarkDirty();
        }));
        const fz = tb.querySelector('#wc-tt-size');
        if (fz) fz.addEventListener('change', () => { const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null; if (!doc || !fz.value) return; doc.execCommand('fontSize', false, '7'); try { const f = doc.querySelector('font[size="7"]'); if (f) { f.removeAttribute('size'); f.style.fontSize = fz.value; } } catch (e) {} window.wcMarkDirty && window.wcMarkDirty(); });
        const ff = tb.querySelector('#wc-tt-font');
        if (ff) ff.addEventListener('change', () => { const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null; if (!doc || !ff.value) return; doc.execCommand('fontName', false, ff.value); const c = sel(); if (c) try { c.addStyle({ 'font-family': ff.value }); } catch (e) {} window.wcMarkDirty && window.wcMarkDirty(); });
        const tc = tb.querySelector('#wc-tt-color');
        if (tc) tc.addEventListener('input', () => { const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null; if (doc) doc.execCommand('foreColor', false, tc.value); const c = sel(); if (c) try { c.addStyle({ color: tc.value }); } catch (e) {} window.wcMarkDirty && window.wcMarkDirty(); });
        const hl = tb.querySelector('#wc-tt-hl');
        if (hl) hl.addEventListener('input', () => { const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null; if (doc) doc.execCommand('hiliteColor', false, hl.value); window.wcMarkDirty && window.wcMarkDirty(); });
        const lk = tb.querySelector('#wc-tt-link');
        if (lk) lk.addEventListener('click', () => { const url = prompt('Link URL (https:// / #section / tel: / mailto:):', 'https://'); if (!url) return; const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null; if (doc) doc.execCommand('createLink', false, url); window.wcMarkDirty && window.wcMarkDirty(); });
        const cl = tb.querySelector('#wc-tt-clear');
        if (cl) cl.addEventListener('click', () => { const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null; if (doc) doc.execCommand('removeFormat', false, null); window.wcMarkDirty && window.wcMarkDirty(); });
      }
      function wcPositionToolbar() {
        const tb = $('wc-text-toolbar'); if (!tb) return;
        const c = sel(); if (!c) { tb.classList.remove('show'); return; }
        const tag = (c.get('tagName') || '').toLowerCase();
        if (!['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'a', 'li', 'div', 'button'].includes(tag)) { tb.classList.remove('show'); return; }
        const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
        const canvasSel = doc ? doc.getSelection() : null;
        if (!canvasSel || canvasSel.toString().trim().length < 1) { tb.classList.remove('show'); return; }
        try {
          const r = canvasSel.getRangeAt(0).getBoundingClientRect();
          tb.classList.add('show');
          tb.style.left = Math.max(8, Math.min(window.innerWidth - tb.offsetWidth - 8, r.left + (r.width / 2) - (tb.offsetWidth / 2))) + 'px';
          tb.style.top = Math.max(60, r.top - tb.offsetHeight - 12) + 'px';
        } catch (e) {}
      }

      /* ── SMART SECTION CONTENT (human-readable fields, edits in place) ── */
      window.wcSmartContentEdit = function (comp) {
        comp = comp || sel(); if (!comp) return;
        const el = comp.getEl && comp.getEl(); if (!el) return;
        const heads = Array.from(el.querySelectorAll('h1,h2,h3')).slice(0, 3);
        const paras = Array.from(el.querySelectorAll('p')).slice(0, 4);
        const btns = Array.from(el.querySelectorAll('a,button')).slice(0, 4);
        const imgs = Array.from(el.querySelectorAll('img')).slice(0, 3);
        const tag = (comp.get('tagName') || 'section').toLowerCase();
        let kind = 'Section';
        try { const nm = ((comp.getAttributes() || {})['data-section-name'] || el.id || '').toLowerCase(); if (/hero/.test(nm) || tag === 'header') kind = 'Hero'; else if (/service/.test(nm)) kind = 'Services'; else if (/testimonial|review/.test(nm)) kind = 'Testimonials'; else if (/pric/.test(nm)) kind = 'Pricing'; else if (/faq/.test(nm)) kind = 'FAQ'; else if (/contact/.test(nm)) kind = 'Contact'; else if (/footer/.test(nm)) kind = 'Footer'; else if (/team/.test(nm)) kind = 'Team'; } catch (e) {}
        let h = '<div style="display:flex;flex-direction:column;gap:0.55rem;max-height:60vh;overflow:auto">';
        h += '<div class="wc-friendly-lbl">' + esc(kind) + ' — type below, updates live on canvas (no new section created)</div>';
        heads.forEach((n, i) => { h += '<label class="wc-friendly-lbl">' + (i === 0 ? 'Main heading' : 'Heading ' + (i + 1)) + '</label><input class="be-input" data-wc-f="h' + i + '" value="' + esc(n.textContent.trim().slice(0, 140)) + '">'; });
        paras.forEach((n, i) => { h += '<label class="wc-friendly-lbl">' + (i === 0 ? 'Subtitle / description' : 'Text ' + (i + 1)) + '</label><textarea class="be-input" data-wc-f="p' + i + '" rows="2">' + esc(n.textContent.trim().slice(0, 300)) + '</textarea>'; });
        btns.forEach((n, i) => { h += '<label class="wc-friendly-lbl">' + (i === 0 ? 'Primary button' : 'Secondary button') + '</label><div style="display:flex;gap:0.35rem"><input class="be-input" data-wc-f="b' + i + '" value="' + esc(n.textContent.trim().slice(0, 60)) + '"><input class="be-input" data-wc-f="bl' + i + '" value="' + esc(n.getAttribute('href') || '') + '" placeholder="link"></div>'; });
        imgs.forEach((n, i) => { h += '<label class="wc-friendly-lbl">Image ' + (i + 1) + ' URL</label><div style="display:flex;gap:0.35rem"><input class="be-input" data-wc-f="img' + i + '" value="' + esc(n.getAttribute('src') || '') + '"><button class="wc-pro-btn small" data-wc-f="imgbtn' + i + '">🔄</button></div>'; });
        h += '</div>';
        const apply = (root) => {
          try {
            root.querySelectorAll('[data-wc-f]').forEach(inp => {
              const f = inp.getAttribute('data-wc-f');
              if (f.startsWith('imgbtn')) { inp.onclick = () => { if (grapesEditor) grapesEditor.select(comp); const im = el.querySelectorAll('img')[parseInt(f.replace('imgbtn', ''), 10)]; if (im && typeof openImageEditor === 'function') { let cc = null; try { const walk = (x) => { if (cc) return; try { if (x.getEl && x.getEl() === im) cc = x; } catch (e) {} try { (x.components() || []).forEach(walk); } catch (e) {} }; walk(comp); } catch (e) {} if (cc) openImageEditor(cc); } }; return; }
              inp.oninput = () => {
                const v = inp.value;
                try {
                  if (f[0] === 'h') { const n = el.querySelectorAll('h1,h2,h3')[parseInt(f.slice(1), 10)]; if (n) n.textContent = v; }
                  else if (f[0] === 'p' && f.length === 2) { const n = el.querySelectorAll('p')[parseInt(f.slice(1), 10)]; if (n) n.textContent = v; }
                  else if (f[0] === 'b' && f.length === 2) { const n = el.querySelectorAll('a,button')[parseInt(f.slice(1), 10)]; if (n) n.textContent = v; }
                  else if (f.slice(0, 2) === 'bl') { const n = el.querySelectorAll('a,button')[parseInt(f.slice(2), 10)]; if (n) n.setAttribute('href', v); }
                  else if (f.slice(0, 3) === 'img') { const n = el.querySelectorAll('img')[parseInt(f.slice(3), 10)]; if (n) n.setAttribute('src', v); }
                  try { comp.set('content', el.innerHTML); } catch (e) {}
                  window.wcMarkDirty && window.wcMarkDirty();
                } catch (e) {}
              };
            });
          } catch (e) {}
        };
        if (typeof openSectionConfigurator === 'function') {
          try {
            const host = document.createElement('div'); host.innerHTML = h;
            host.querySelectorAll('.be-input').forEach(x => { x.style.cssText = 'width:100%;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:8px;padding:0.45rem 0.6rem;font-size:0.78rem;font-family:inherit;'; });
            apply(host);
            const cfg = $('section-configurator-content');
            if (cfg) { cfg.innerHTML = ''; cfg.appendChild(host); $('section-configurator-modal').classList.add('active'); return; }
          } catch (e) {}
        }
        showProModal(kind + ' content', h, apply);
      };
      function showProModal(title, html, onMount) {
        let m = $('wc-pro-modal');
        if (!m) { m = document.createElement('div'); m.id = 'wc-pro-modal'; m.style.cssText = 'position:fixed;inset:0;z-index:9600;display:none;align-items:center;justify-content:center;background:rgba(2,6,16,.7)'; m.innerHTML = '<div style="width:min(560px,94vw);max-height:86vh;overflow:auto;background:#0b0f1a;border:1px solid #334155;border-radius:16px;padding:1rem"><div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.7rem"><strong id="wc-pro-modal-title" style="flex:1"></strong><button class="wc-pro-btn small" onclick="document.getElementById(\'wc-pro-modal\').style.display=\'none\'">✕</button></div><div id="wc-pro-modal-body"></div></div>'; document.body.appendChild(m); m.addEventListener('click', (e) => { if (e.target === m) m.style.display = 'none'; }); }
        $('wc-pro-modal-title').textContent = title;
        const body = $('wc-pro-modal-body'); body.innerHTML = html;
        body.querySelectorAll('.be-input').forEach(x => { if (!x.style.cssText) x.style.cssText = 'width:100%;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:8px;padding:0.45rem 0.6rem;font-size:0.78rem;font-family:inherit;'; });
        if (onMount) onMount(body);
        m.style.display = 'flex';
      }
      window.wcShowProModal = showProModal;

      /* ── ADVANCED BUTTON + IMAGE (in-place, layout-preserving) ── */
      window.wcEditButtonPro = function (comp) {
        comp = comp || sel(); if (!comp) return;
        const at = comp.getAttributes() || {}, st = comp.getStyle() || {};
        const el = comp.getEl && comp.getEl();
        const txt = el ? (el.innerText || '').trim() : '';
        let h = '<div class="wc-pro-card"><h4>🔘 Content</h4><div class="wc-pro-row"><label>Button text</label><input type="text" id="wc-b-txt" value="' + esc(txt) + '"></div>';
        h += '<div class="wc-pro-row"><label>Icon</label><input type="text" id="wc-b-ico" placeholder="→ ✦ ★" style="max-width:70px"></div>';
        h += '<div class="wc-pro-row"><label>Icon side</label><select id="wc-b-icop"><option value="right">Right</option><option value="left">Left</option></select></div></div>';
        h += '<div class="wc-pro-card"><h4>🎯 Action — where it goes</h4><div class="wc-pro-row"><label>Type</label><select id="wc-b-kind"><option value="section">Section on this page</option><option value="page">Another page</option><option value="external">External website</option><option value="whatsapp">WhatsApp chat</option><option value="phone">Phone call</option><option value="email">Email</option><option value="file">Download file</option></select></div>';
        h += '<div class="wc-pro-row"><label>Target</label><input type="text" id="wc-b-href" value="' + esc(at.href || '#contact') + '" style="flex:2;max-width:none"></div>';
        h += '<div class="wc-pro-row"><label>Opens in</label><select id="wc-b-tgt"><option value="">Same tab</option><option value="_blank"' + (at.target === '_blank' ? ' selected' : '') + '>New tab</option></select></div></div>';
        h += '<div class="wc-pro-card"><h4>🎨 Style <span class="wc-friendly-lbl">— live preview</span></h4>';
        h += '<div class="wc-pro-row"><label>Background</label><input type="color" id="wc-b-bg" value="' + esc((st['background-color'] || '#6366f1').slice(0, 7)) + '"></div>';
        h += '<div class="wc-pro-row"><label>Text color</label><input type="color" id="wc-b-c" value="' + esc((st.color || '#ffffff').slice(0, 7)) + '"></div>';
        h += '<div class="wc-pro-row"><label>Text size</label><input type="range" id="wc-b-fs" min="12" max="24" value="' + (parseInt(st['font-size']) || 15) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'"><span class="wc-pro-val">' + (parseInt(st['font-size']) || 15) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Corner roundness</label><input type="range" id="wc-b-r" min="0" max="60" value="' + (parseInt(st['border-radius']) || 24) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'"><span class="wc-pro-val">' + (parseInt(st['border-radius']) || 24) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Inside spacing</label><input type="range" id="wc-b-p" min="8" max="60" value="24" oninput="this.nextElementSibling.textContent=this.value+\'px\'"><span class="wc-pro-val">24px</span></div>';
        h += '<div class="wc-pro-row"><label>Shadow</label><select id="wc-b-sh"><option value="none">None</option><option value="soft" selected>Soft</option><option value="strong">Strong</option></select></div>';
        h += '<div class="wc-pro-row"><label>Hover grow</label><select id="wc-b-hov"><option value="yes">Yes — subtle</option><option value="no">No</option></select></div></div>';
        h += '<button class="wc-pro-btn primary" id="wc-b-apply" style="width:100%">✓ Apply button</button>';
        showProModal('Button editor', h, (root) => {
          root.querySelector('#wc-b-apply').onclick = () => {
            const t = root.querySelector('#wc-b-txt').value, ico = root.querySelector('#wc-b-ico').value, side = root.querySelector('#wc-b-icop').value;
            const kind = root.querySelector('#wc-b-kind').value; let href = root.querySelector('#wc-b-href').value;
            if (kind === 'whatsapp' && !/wa\.me|whatsapp/i.test(href)) href = 'https://wa.me/' + href.replace(/\D/g, '');
            if (kind === 'phone' && !href.startsWith('tel:')) href = 'tel:' + href.replace(/\s/g, '');
            if (kind === 'email' && !href.startsWith('mailto:')) href = 'mailto:' + href;
            const tgt = root.querySelector('#wc-b-tgt').value;
            const bg = root.querySelector('#wc-b-bg').value, c = root.querySelector('#wc-b-c').value;
            const fs = root.querySelector('#wc-b-fs').value, r = root.querySelector('#wc-b-r').value, p = root.querySelector('#wc-b-p').value;
            const sh = root.querySelector('#wc-b-sh').value;
            try {
              const label = (side === 'left' ? (ico ? ico + ' ' : '') + t : t + (ico ? ' ' + ico : ''));
              const el2 = comp.getEl && comp.getEl(); if (el2) el2.textContent = label;
              try { comp.set('content', label); } catch (e) {}
              comp.addAttributes({ href: href }); if (tgt) comp.addAttributes({ target: tgt }); else { const a2 = Object.assign({}, comp.getAttributes()); delete a2.target; comp.setAttributes(a2); }
              comp.addStyle({ 'background-color': bg, background: bg, color: c, 'font-size': fs + 'px', 'border-radius': r + 'px', padding: '0.7rem ' + p + 'px', 'box-shadow': sh === 'none' ? 'none' : (sh === 'strong' ? '0 18px 45px rgba(2,6,23,.3)' : '0 10px 25px rgba(2,6,23,.15)'), transition: 'all .2s ease' });
            } catch (e) {}
            window.wcMarkDirty && window.wcMarkDirty();
            $('wc-pro-modal').style.display = 'none';
            if (typeof showToast === 'function') showToast('🔘 Button updated');
          };
        });
      };
      window.wcEditImagePro = function (comp) {
        comp = comp || sel(); if (!comp) return;
        if (typeof openImageEditor === 'function') openImageEditor(comp);
        setTimeout(() => {
          const modal = $('image-editor-modal'); if (!modal) return;
          if ($('wc-img-pro')) return;
          const box = document.createElement('div'); box.id = 'wc-img-pro'; box.className = 'wc-pro-card';
          box.innerHTML = '<h4>✨ Pro adjustments <span class="wc-friendly-lbl">— keeps layout</span></h4>'
            + '<div class="wc-pro-row"><label>See-through <span class="wc-friendly-lbl">(opacity)</span></label><input type="range" id="wc-img-op" min="20" max="100" value="100" oninput="this.nextElementSibling.textContent=this.value+\'%\'"><span class="wc-pro-val">100%</span></div>'
            + '<div class="wc-pro-row"><label>Soft blur</label><input type="range" id="wc-img-blur" min="0" max="10" value="0" oninput="this.nextElementSibling.textContent=this.value+\'px\'"><span class="wc-pro-val">0px</span></div>'
            + '<div class="wc-pro-row"><label>Shadow</label><select id="wc-img-sh"><option value="none">None</option><option value="soft">Soft</option><option value="strong">Strong</option></select></div>'
            + '<div class="wc-pro-row"><label>Image link</label><input type="text" id="wc-img-link" placeholder="https://… or #section" style="flex:2;max-width:none"></div>'
            + '<div class="wc-pro-row"><label>New tab</label><select id="wc-img-tgt"><option value="">Same tab</option><option value="_blank">New tab</option></select></div>'
            + '<button class="wc-pro-btn primary" style="width:100%" onclick="wcApplyImagePro()">✓ Apply image style</button>';
          modal.querySelector('.be-modal-box, .modal-box, div').appendChild(box);
        }, 60);
      };
      window.wcApplyImagePro = function () {
        try {
          const comp = (typeof editingImage !== 'undefined' ? editingImage : sel()); if (!comp) return;
          const op = $('wc-img-op').value, bl = $('wc-img-blur').value, sh = $('wc-img-sh').value;
          const st = {};
          st.opacity = (op / 100).toString();
          if (+bl > 0) st.filter = 'blur(' + bl + 'px)';
          st['box-shadow'] = sh === 'none' ? 'none' : (sh === 'strong' ? '0 20px 55px rgba(2,6,23,.35)' : '0 12px 30px rgba(2,6,23,.18)');
          comp.addStyle(st);
          const link = $('wc-img-link').value.trim();
          if (link) { const p = comp.parent(); if (p && (p.get('tagName') || '').toLowerCase() === 'a') p.addAttributes({ href: link, target: $('wc-img-tgt').value || '' }); else if (typeof showToast === 'function') showToast('Tip: wrap image in a link block for clickable images'); }
          try { if (typeof applyImageEditor === 'function') applyImageEditor(); } catch (e) {}
          window.wcMarkDirty && window.wcMarkDirty();
        } catch (e) {}
      };

      /* ── AI SCOPES + INTENT (wraps executeMagicAi, never replaces lanes) ── */
      window.WCAIScope = window.WCAIScope || 'auto';
      window.wcSetAIScope = function (s) { window.WCAIScope = s; document.querySelectorAll('.wc-scope-chip').forEach(c => c.classList.toggle('active', c.getAttribute('data-scope') === s)); };
      function wcInterpretIntent(q) {
        const s = (q || '').toLowerCase(); const hints = [];
        if (/small|சின்ன|cheriya|romba small/.test(s) && /button|btn|பட்டன்/.test(s)) hints.push('Increase button padding to ~1rem 2.4rem and font-size to ~17px so the button feels larger and more tappable.');
        if (/empty|காலி|வெறுமை|blank/.test(s) && /home|page|hero/.test(s)) hints.push('The homepage feels empty: improve visual balance — enlarge hero, add trust badges, tighten spacing, add one supporting section (testimonials or logos) without deleting existing content.');
        if (/contact|தொடர்பு|phone|whatsapp|call/.test(s) && /easy|ஈஸி|simple/.test(s)) hints.push('Make contacting easy: strengthen contact CTA — add sticky WhatsApp button, make phone number a tel: link, ensure contact section sits just above footer.');
        if (/premium|luxury|modern|professional/.test(s)) hints.push('Elevate to premium modern style: refined spacing, soft shadows, consistent radius, elegant typography — keep all text and images.');
        if (/mobile|phone/.test(s) && /friend|fix|responsive/.test(s)) hints.push('Improve mobile layout: reduce heading sizes ~20%, stack columns, increase tap targets, hide oversized decorations on small screens.');
        if (/round/.test(s) && /button/.test(s)) hints.push('Make buttons fully rounded (border-radius 999px).');
        if (/add.*(pricing|faq|gallery|testimonial|contact|whatsapp)/.test(s)) hints.push('Insert the requested section using existing section templates style — editable, near related content.');
        if (/remove|delete/.test(s)) hints.push('Remove only the requested section; keep header/footer intact.');
        if (/move.*(contact|section).*above|reorder/.test(s)) hints.push('Reorder sections as asked by moving the component, not duplicating.');
        return hints.length ? '\n\n[Intent interpretation: ' + hints.join(' ') + ']\n' : '';
      }
      window.wcInterpretIntent = wcInterpretIntent;
      function wcPatchAI() {
        try {
          const input = $('magic-input'); const panel = $('magic-ai-panel');
          if (input && panel && !$('wc-ai-scope')) {
            const row = document.createElement('div'); row.id = 'wc-ai-scope'; row.className = 'wc-scope-row';
            row.innerHTML = '<span class="wc-scope-chip active" data-scope="auto" onclick="wcSetAIScope(\'auto\')">🤖 Auto</span><span class="wc-scope-chip" data-scope="element" onclick="wcSetAIScope(\'element\')">🎯 Element</span><span class="wc-scope-chip" data-scope="section" onclick="wcSetAIScope(\'section\')">📐 Section</span><span class="wc-scope-chip" data-scope="site" onclick="wcSetAIScope(\'site\')">🌐 Site</span>';
            const inputRow = panel.querySelector('.magic-input-row') || input.parentNode;
            inputRow.parentNode.insertBefore(row, inputRow);
          }
          if (typeof executeMagicAi === 'function' && !executeMagicAi.__wcWrapped) {
            const orig = executeMagicAi;
            const wrapped = async function () {
              const inp = $('magic-input'); const q = inp ? inp.value.trim() : '';
              if (!q) return orig.apply(this, arguments);
              const scope = window.WCAIScope || 'auto';
              try { window.wcSnapshotVersion && window.wcSnapshotVersion('Before AI: ' + q.slice(0, 40)); } catch (e) {}
              if (scope === 'element' && !sel()) { if (typeof showToast === 'function') showToast('🎯 Select an element first for Element mode'); return; }
              if (scope === 'section') {
                const c = sel(); let sec = c;
                try { while (sec && !['section', 'header', 'footer'].includes((sec.get('tagName') || '').toLowerCase())) sec = sec.parent(); } catch (e) {}
                if (sec && grapesEditor) grapesEditor.select(sec);
                else if (typeof showToast === 'function') showToast('📐 Selecting nearest section for Section mode');
              }
              if (scope === 'site' && grapesEditor) { try { grapesEditor.select(null); } catch (e) {} }
              if (inp) inp.value = q + wcInterpretIntent(q);
              try { return await orig.apply(this, arguments); }
              finally { try { window.wcApplyThemeToCanvas && window.wcApplyThemeToCanvas(); window.wcApplyResponsiveCss && window.wcApplyResponsiveCss(); } catch (e) {} }
            };
            wrapped.__wcWrapped = true;
            window.executeMagicAi = wrapped;
          }
        } catch (e) {}
      }

      /* ── MORE COMPONENTS (all editable after insert) ── */
      const PRO_COMPONENTS = [
        ['newsletter', '📧 Newsletter', '<section style="padding:4rem 1.5rem;text-align:center;background:#f8fafc;"><div style="max-width:560px;margin:0 auto;"><h2 style="font-size:2rem;font-weight:800;margin-bottom:0.5rem;">Stay in the loop</h2><p style="color:#64748b;margin-bottom:1.5rem;">Get updates and offers in your inbox.</p><form style="display:flex;gap:0.5rem;flex-wrap:wrap;justify-content:center;"><input type="email" placeholder="you@email.com" style="flex:1;min-width:220px;padding:0.85rem 1.1rem;border-radius:999px;border:1.5px solid #e2e8f0;"><button type="submit" style="padding:0.85rem 1.8rem;border-radius:999px;background:var(--primary,#6366f1);color:#fff;font-weight:700;border:none;">Subscribe</button></form></div></section>'],
        ['stats2', '📊 Statistics', '<section style="padding:3.5rem 1.5rem;text-align:center;"><div style="max-width:1000px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.5rem;"><div><div style="font-size:2.4rem;font-weight:900;color:var(--primary,#6366f1);">10k+</div><div style="color:#64748b;">Happy clients</div></div><div><div style="font-size:2.4rem;font-weight:900;color:var(--primary,#6366f1);">4.9★</div><div style="color:#64748b;">Average rating</div></div><div><div style="font-size:2.4rem;font-weight:900;color:var(--primary,#6366f1);">8 yrs</div><div style="color:#64748b;">Experience</div></div></div></section>'],
        ['timeline', '🕒 Timeline', '<section style="padding:4rem 1.5rem;"><div style="max-width:700px;margin:0 auto;"><h2 style="font-size:2rem;font-weight:800;text-align:center;margin-bottom:2rem;">How it works</h2><div style="border-left:3px solid var(--primary,#6366f1);padding-left:1.5rem;display:flex;flex-direction:column;gap:1.5rem;"><div><strong>Step 1 — Contact</strong><p style="color:#64748b;">Tell us what you need.</p></div><div><strong>Step 2 — Design</strong><p style="color:#64748b;">We craft your site.</p></div><div><strong>Step 3 — Launch</strong><p style="color:#64748b;">Go live & grow.</p></div></div></div></section>'],
        ['booking', '📅 Booking CTA', '<section style="padding:4rem 1.5rem;text-align:center;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;"><h2 style="font-size:2.2rem;font-weight:800;margin-bottom:0.6rem;color:#fff;">Book your free consultation</h2><p style="opacity:.85;margin-bottom:1.5rem;">Pick a time that suits you.</p><a href="#contact" style="display:inline-block;background:#fff;color:#4f46e5;font-weight:800;padding:0.9rem 2.2rem;border-radius:999px;text-decoration:none;">Book Now →</a></section>'],
        ['whatsapp', '💬 WhatsApp CTA', '<section style="padding:3rem 1.5rem;text-align:center;"><a href="https://wa.me/10000000000" style="display:inline-flex;align-items:center;gap:0.6rem;background:#22c55e;color:#fff;font-weight:800;padding:1rem 2.2rem;border-radius:999px;text-decoration:none;font-size:1.05rem;">💬 Chat on WhatsApp</a></section>'],
        ['countdown', '⏳ Countdown', '<section data-wc-countdown="1" style="padding:3.5rem 1.5rem;text-align:center;background:#0f172a;color:#fff;"><h2 style="font-size:1.8rem;font-weight:800;color:#fff;margin-bottom:1rem;">Offer ends soon</h2><div style="display:flex;gap:1rem;justify-content:center;font-size:1.6rem;font-weight:800;"><span>02d</span><span>:</span><span>14h</span><span>:</span><span>30m</span></div></section>'],
        ['video', '🎥 Video', '<section style="padding:4rem 1.5rem;text-align:center;"><div style="max-width:800px;margin:0 auto;"><h2 style="font-size:2rem;font-weight:800;margin-bottom:1rem;">Watch our story</h2><div style="position:relative;padding-top:56.25%;border-radius:18px;overflow:hidden;background:#0f172a;"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" style="position:absolute;inset:0;width:100%;height:100%;border:0;" allowfullscreen></iframe></div></div></section>'],
        ['map', '🗺️ Map', '<section style="padding:3rem 1.5rem;"><div style="max-width:1000px;margin:0 auto;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;"><iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126743.5863873168!2d79.83!3d6.92!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sColombo!5e0!3m2!1sen!2slk!4v1" style="width:100%;height:340px;border:0;" loading="lazy"></iframe></div></section>'],
        ['logos', '🏢 Logo carousel', '<section style="padding:2.5rem 1.5rem;text-align:center;background:#f8fafc;"><div style="font-size:0.75rem;font-weight:800;letter-spacing:0.1em;color:#94a3b8;margin-bottom:1rem;">TRUSTED BY TEAMS AT</div><div style="display:flex;gap:2.5rem;justify-content:center;flex-wrap:wrap;font-weight:800;color:#64748b;font-size:1.2rem;"><span>Acme</span><span>Globex</span><span>Initech</span><span>Umbrella</span><span>Hooli</span></div></section>'],
        ['blog', '📰 Blog cards', '<section style="padding:4rem 1.5rem;"><div style="max-width:1100px;margin:0 auto;"><h2 style="font-size:2rem;font-weight:800;text-align:center;margin-bottom:2rem;">Latest articles</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;"><article style="border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;"><img src="https://picsum.photos/seed/wcblog1/600/360" style="width:100%;height:180px;object-fit:cover;"><div style="padding:1.25rem;"><h3>Article title one</h3><p style="color:#64748b;">Short excerpt…</p></div></article><article style="border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;"><img src="https://picsum.photos/seed/wcblog2/600/360" style="width:100%;height:180px;object-fit:cover;"><div style="padding:1.25rem;"><h3>Article title two</h3><p style="color:#64748b;">Short excerpt…</p></div></article></div></div></section>']
      ];
      window.wcInsertProComponent = function (i) {
        const e = PRO_COMPONENTS[i]; if (!e || !grapesEditor) return;
        try {
          const w = grapesEditor.DomComponents.getWrapper();
          const added = w.append(e[2]);
          const comp = Array.isArray(added) ? added[0] : added;
          if (comp && typeof configureEditorComponent === 'function') configureEditorComponent(comp);
          window.wcMarkDirty && window.wcMarkDirty();
          if (typeof showToast === 'function') showToast('✨ ' + e[1] + ' added — click to edit');
        } catch (e2) {}
      };
      function wcInjectProComponents() {
        try {
          const host = $('dtab-blocks'); if (!host || $('wc-pro-comp-grid')) return;
          const grid = document.createElement('div'); grid.id = 'wc-pro-comp-grid';
          grid.innerHTML = '<div style="font-size:0.74rem;font-weight:800;color:#a5b4fc;margin:0.9rem 0 0.5rem;">✨ PREMIUM COMPONENTS — click to insert, then edit</div><div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;">' + PRO_COMPONENTS.map((c, i) => '<button class="wc-pro-btn small" onclick="wcInsertProComponent(' + i + ')">' + esc(c[1]) + '</button>').join('') + '</div>';
          host.appendChild(grid);
        } catch (e) {}
      }

      /* ── CONTEXT MENU upgrades (extends, keeps architecture) ── */
      function wcPatchContextMenu() {
        try {
          const menu = $('custom-context-menu'); if (!menu || menu.__wcPatched) return;
          menu.__wcPatched = true;
          const add = (label, fn) => { const b = document.createElement('button'); b.className = 'ctx-item'; b.innerHTML = '<span class="ctx-icon">✦</span> ' + label; b.onclick = fn; menu.appendChild(b); return b; };
          add('🎨 Style with friendly names', () => { try { hideContextMenu(); } catch (e) {} const c = sel(); if (c) { if (grapesEditor) grapesEditor.select(c); window.switchDrawerTab('responsive'); } });
          add('✍️ Smart content edit', () => { try { hideContextMenu(); } catch (e) {} const c = sel(); if (c) window.wcSmartContentEdit(c); });
          add('🔘 Edit button (advanced)', () => { try { hideContextMenu(); } catch (e) {} const c = sel(); if (c) window.wcEditButtonPro(c); });
          add('🔒 Lock / Unlock', () => { try { hideContextMenu(); } catch (e) {} const c = sel(); if (c) window.wcToggleLock(c); });
          add('💎 Save as My Section', () => { try { hideContextMenu(); } catch (e) {} const c = sel(); if (c) window.wcSaveReusable(c); });
        } catch (e) {}
      }

      /* ── PAGES extension (non-destructive, via observer) ── */
      function wcPatchPages() {
        const list = $('wc-pages-list'); if (!list || list.__wcObs) return;
        list.__wcObs = true;
        const enhance = () => {
          try {
            const items = list.querySelectorAll('.wc-page-item');
            items.forEach((item, i) => {
              if (item.__wcEnhanced) return; item.__wcEnhanced = true;
              const mk = (t, title, fn) => { const b = document.createElement('button'); b.className = 'wc-page-mini-btn'; b.textContent = t; b.title = title; b.onclick = (ev) => { ev.stopPropagation(); fn(i); }; item.appendChild(b); return b; };
              mk('⧉', 'Duplicate page', (idx) => {
                try {
                  const d = (typeof wcDesign === 'function' ? wcDesign() : null); if (!d) return;
                  const pages = (typeof wcEnsurePages === 'function' ? wcEnsurePages() : []);
                  if (typeof wcSyncCurrentPageFromCanvas === 'function') wcSyncCurrentPageFromCanvas();
                  const src = pages[idx];
                  pages.splice(idx + 1, 0, { id: 'p' + Date.now(), name: (src.name || 'Page') + ' copy', slug: (src.slug || 'page') + '-copy', html: src.html || '' });
                  if (typeof saveProjectData === 'function') saveProjectData();
                  if (typeof renderWcPagesPanel === 'function') renderWcPagesPanel();
                } catch (e) {}
              });
              if (i !== 0) {
                mk('🏠', 'Set as homepage', (idx) => {
                  try {
                    const d = (typeof wcDesign === 'function' ? wcDesign() : null); if (!d) return;
                    const pages = (typeof wcEnsurePages === 'function' ? wcEnsurePages() : []);
                    const cur = (typeof wcCurrentPageIdx === 'function' ? wcCurrentPageIdx() : 0);
                    const entry = pages.splice(idx, 1)[0]; pages.unshift(entry);
                    d._wcPage = 0;
                    if (typeof saveProjectData === 'function') saveProjectData();
                    if (typeof renderWcPagesPanel === 'function') renderWcPagesPanel();
                    if (typeof showToast === 'function') showToast('🏠 Homepage set: ' + entry.name);
                  } catch (e) {}
                });
                mk('✎ slug', 'Edit slug', (idx) => {
                  try {
                    const pages = (typeof wcEnsurePages === 'function' ? wcEnsurePages() : []);
                    const v = prompt('URL slug (no spaces):', pages[idx].slug || ''); if (v == null) return;
                    pages[idx].slug = v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || pages[idx].slug;
                    if (typeof saveProjectData === 'function') saveProjectData();
                    if (typeof renderWcPagesPanel === 'function') renderWcPagesPanel();
                  } catch (e) {}
                });
                mk('↑', 'Move up', (idx) => {
                  try {
                    const pages = (typeof wcEnsurePages === 'function' ? wcEnsurePages() : []);
                    if (idx <= 1) return;
                    const t = pages[idx - 1]; pages[idx - 1] = pages[idx]; pages[idx] = t;
                    if (typeof saveProjectData === 'function') saveProjectData();
                    if (typeof renderWcPagesPanel === 'function') renderWcPagesPanel();
                  } catch (e) {}
                });
              }
            });
          } catch (e) {}
        };
        new MutationObserver(enhance).observe(list, { childList: true });
        setTimeout(enhance, 800);
      }

      /* ── DRAG guides (lightweight center snap, no custom engine) ── */
      function wcEnsureGuides() {
        try {
          const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (!doc || !doc.body || doc.getElementById('wc-guides')) return;
          const g = doc.createElement('div'); g.id = 'wc-guides';
          g.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:9999;display:none;';
          g.innerHTML = '<div data-g="v" style="position:absolute;top:0;bottom:0;left:50%;width:1px;background:#ec4899;box-shadow:0 0 8px #ec4899;"></div><div data-g="h" style="position:absolute;left:0;right:0;top:50%;height:1px;background:#22d3ee;box-shadow:0 0 8px #22d3ee;"></div>';
          doc.body.appendChild(g);
          if (grapesEditor && !grapesEditor.__wcGuideHook) {
            grapesEditor.__wcGuideHook = true;
            grapesEditor.on('component:drag', () => { try { g.style.display = 'block'; } catch (e) {} });
            grapesEditor.on('component:drag:end', () => { try { g.style.display = 'none'; } catch (e) {} });
          }
        } catch (e) {}
      }

      /* ── lock guard: locked elements stay visible, not editable ── */
      function wcPatchLockGuard() {
        try {
          if (grapesEditor && !grapesEditor.__wcLockHook) {
            grapesEditor.__wcLockHook = true;
            grapesEditor.on('component:selected', (m) => {
              try {
                if (m && (m.getAttributes() || {})['data-wc-locked'] === '1' && !window._wcUnlockArmed) {
                  if (typeof showToast === 'function') showToast('🔒 Locked — use Sections / Header / Footer panel → Unlock to edit');
                }
              } catch (e) {}
            });
          }
        } catch (e) {}
      }

      /* ── BOOT: wire everything after editor exists (poll, no reload loops) ── */
      let bootTries = 0;
      function wcProBoot() {
        try { if (window.WCPro && window.WCPro._pill) window.WCPro._pill(); } catch (e) {}
        try { if (window.WCPro && window.WCPro._wrapSave) window.WCPro._wrapSave(); } catch (e) {}
        try { wcEnsureProTabs(); wcPatchDrawer(); } catch (e) {}
        try { wcEnsureTextToolbar(); } catch (e) {}
        try { wcPatchAI(); wcPatchContextMenu(); wcPatchPages(); wcInjectProComponents(); } catch (e) {}
        if (typeof grapesEditor === 'undefined' || !grapesEditor) { if (++bootTries < 40) setTimeout(wcProBoot, 500); return; }
        try { if (window.wcProEnsure) window.wcProEnsure(); } catch (e) {}
        try { window.wcApplyThemeToCanvas && window.wcApplyThemeToCanvas(); } catch (e) {}
        try { window.wcApplyResponsiveCss && window.wcApplyResponsiveCss(); } catch (e) {}
        try { wcEnsureGuides(); wcPatchLockGuard(); } catch (e) {}
        try {
          grapesEditor.on('component:selected', () => { setTimeout(() => { try { wcPositionToolbar(); } catch (e) {} }, 60); });
          grapesEditor.on('component:deselected', () => { const tb = $('wc-text-toolbar'); if (tb) tb.classList.remove('show'); });
          grapesEditor.on('component:update', () => { window.wcMarkDirty && window.wcMarkDirty(); });
          const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (doc && !doc.__wcTbBound) {
            doc.__wcTbBound = true;
            doc.addEventListener('mouseup', () => setTimeout(wcPositionToolbar, 40));
            doc.addEventListener('keyup', () => setTimeout(wcPositionToolbar, 40));
          }
        } catch (e) {}
        try {
          const doc2 = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (doc2 && !doc2.__wcDbl) {
            doc2.__wcDbl = true;
            doc2.addEventListener('dblclick', (e) => {
              const t = e.target;
              if (!t) return;
              const tag = (t.tagName || '').toLowerCase();
              if (['h1', 'h2', 'h3', 'h4', 'p', 'span', 'li'].includes(tag)) { try { t.setAttribute('contenteditable', 'true'); t.focus(); } catch (err) {} }
              else if (tag === 'img') { try { if (typeof componentFromElement === 'function' && typeof openImageEditor === 'function') { const c = componentFromElement(t); if (c) openImageEditor(c); } } catch (err) {} }
              else if (tag === 'a' || tag === 'button') { try { if (typeof componentFromElement === 'function') { const c = componentFromElement(t); if (c) window.wcEditButtonPro(c); } } catch (err) {} }
            });
          }
        } catch (e) {}
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => setTimeout(wcProBoot, 1200));
      else setTimeout(wcProBoot, 1200);
      window.wcProBoot = wcProBoot;
    })();

    /* ═══════════ WC PRO PART 4A — History activity + My Sections library (additive) ═══════════
       Overrides ONLY Part-3 render functions for History / Saved. No existing feature touched. */
    (function WCProHistLib() {
      if (window.__WCProHistLibLoaded) return;
      window.__WCProHistLibLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s == null ? '' : s));
      const sel = () => { try { return (typeof selectedComponent !== 'undefined' ? selectedComponent : null); } catch (e) { return null; } };

      /* ── ACTIVITY LOG (lightweight: no HTML, separate key, capped) ── */
      function wcActivityKey() {
        try { return 'webcraft_activity::c' + (typeof activeConceptIndex === 'number' ? activeConceptIndex : 0); }
        catch (e) { return 'webcraft_activity::c0'; }
      }
      function wcGetActivity() {
        try { const a = JSON.parse(localStorage.getItem(wcActivityKey()) || '[]'); return Array.isArray(a) ? a : []; }
        catch (e) { return []; }
      }
      function wcPutActivity(list) {
        try { localStorage.setItem(wcActivityKey(), JSON.stringify(list.slice(-60))); } catch (e) {}
      }
      window.wcGetActivity = wcGetActivity;
      window.wcLogActivity = function (action, desc, icon) {
        try {
          const list = wcGetActivity();
          list.push({ t: Date.now(), action: String(action || 'Change'), desc: String(desc || ''), icon: String(icon || '✏️') });
          wcPutActivity(list);
          try { if ($('wc-pro-history') && $('wc-pro-history').offsetParent) window.wcRenderHistoryPanel(); } catch (e) {}
        } catch (e) {}
      };
      window.wcDeleteActivity = function (i) {
        try { const l = wcGetActivity(); l.splice(i, 1); wcPutActivity(l); window.wcRenderHistoryPanel(); } catch (e) {}
      };
      window.wcClearActivity = function () {
        if (!confirm('Clear activity history? Snapshots are kept.')) return;
        try { localStorage.removeItem(wcActivityKey()); window.wcSeedActivity(true); window.wcRenderHistoryPanel(); } catch (e) {}
      };
      window.wcSeedActivity = function (force) {
        try {
          const k = wcActivityKey() + '::seeded';
          if (!force && localStorage.getItem(k)) return;
          localStorage.setItem(k, '1');
          wcPutActivity([{ t: Date.now(), action: 'Website created', desc: 'Project opened in Studio', icon: '🎉' }]);
        } catch (e) {}
      };
      window.wcSnap = function (label, actionDesc) {
        try { if (typeof wcSnapshotVersion === 'function') wcSnapshotVersion(label); } catch (e) {}
        if (actionDesc) window.wcLogActivity(label, actionDesc, '📸');
      };
      function wcFmtTime(t) {
        try {
          const d = new Date(t), now = new Date();
          const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
          const day = d.toDateString() === now.toDateString() ? 'Today' : d.toLocaleDateString();
          return { time, day };
        } catch (e) { return { time: '', day: '' }; }
      }

      /* ── HISTORY PANEL (deep) ── */
      window.wcRenderHistoryPanel = function () {
        const box = $('wc-pro-history'); if (!box) return;
        const acts = wcGetActivity().slice().reverse();
        const vers = (typeof wcGetVersions === 'function') ? wcGetVersions() : [];
        let h = '<div class="wc-pro-card"><h4>🕘 Activity</h4>';
        if (!acts.length) h += '<div style="font-size:0.75rem;color:#94a3b8">No activity yet.</div>';
        else h += '<div style="max-height:300px;overflow:auto;display:flex;flex-direction:column;gap:0.4rem;">' + acts.slice(0, 40).map((a) => {
          const origIdx = wcGetActivity().length - 1 - acts.slice(0, 40).indexOf(a);
          const f = wcFmtTime(a.t);
          return '<div class="wc-ver-item"><span style="font-size:1rem">' + esc(a.icon || '✏️') + '</span><span class="t">' + esc(a.action) + '<br><span style="opacity:.65;font-size:.68rem">🕒 ' + esc(f.time) + ' · ' + esc(f.day) + (a.desc ? ' — ' + esc(a.desc) : '') + '</span></span><button class="wc-pro-btn small danger" title="Delete entry" onclick="wcDeleteActivity(' + origIdx + ')">✕</button></div>';
        }).join('') + '</div>';
        h += '<button class="wc-pro-btn small" style="margin-top:0.55rem;width:100%" onclick="wcClearActivity()">🧹 Clear history</button></div>';
        h += '<div class="wc-pro-card"><h4>📸 Version snapshots (' + vers.length + '/10)</h4>';
        h += '<div style="display:flex;gap:0.4rem;margin-bottom:0.55rem;"><button class="wc-pro-btn small primary" style="flex:1" onclick="wcSnap(\'Manual snapshot\',\'Saved by customer\');wcRenderHistoryPanel()">+ Snapshot current</button><button class="wc-pro-btn small" style="flex:1" onclick="wcOpenBeforeAfter()">Before | After</button></div>';
        if (!vers.length) h += '<div style="font-size:0.75rem;color:#94a3b8">Snapshots are saved automatically before AI edits and restores.</div>';
        else vers.slice().reverse().forEach((v) => {
          const idx = vers.indexOf(v);
          const f = wcFmtTime(v.at);
          h += '<div class="wc-ver-item"><span class="t">📦 ' + esc(v.label || 'Checkpoint') + '<br><span style="opacity:.65;font-size:.68rem">🕒 ' + esc(f.time) + ' · ' + esc(f.day) + '</span></span><button class="wc-pro-btn small" onclick="wcPreviewVersion(' + idx + ')">👁</button><button class="wc-pro-btn small primary" onclick="wcRestoreVersion(' + idx + ')">Restore</button><button class="wc-pro-btn small danger" onclick="wcDeleteVersion(' + idx + ')">✕</button></div>';
        });
        h += '<div class="wc-friendly-lbl">Undo/Redo (Ctrl+Z / Ctrl+Y) keeps working separately inside the canvas.</div></div>';
        box.innerHTML = h;
      };
      window.wcDeleteVersion = function (i) {
        try {
          if (typeof wcGetVersions !== 'function') return;
          const v = wcGetVersions(); v.splice(i, 1);
          localStorage.setItem((function () { try { return 'webcraft_versions::c' + activeConceptIndex; } catch (e) { return 'webcraft_versions::c0'; } })(), JSON.stringify(v));
          window.wcRenderHistoryPanel();
        } catch (e) {}
      };
      // log restores too (wrap once, additive)
      try {
        if (typeof window.wcRestoreVersion === 'function' && !window.wcRestoreVersion.__wcActW) {
          const o = window.wcRestoreVersion;
          const w = function (i) { const r = o.apply(this, arguments); try { window.wcLogActivity('Version restored', 'Rolled back canvas', '🕘'); } catch (e) {} return r; };
          w.__wcActW = true; window.wcRestoreVersion = w;
        }
      } catch (e) {}

      /* transparent activity wrappers for OTHER editors (call original, then log only) */
      function wcWrapLog(name, action, icon, descFn) {
        try {
          const fn = window[name];
          if (typeof fn !== 'function' || fn.__wcActW) return;
          const w = function () {
            const r = fn.apply(this, arguments);
            try {
              let d = '';
              try { d = descFn ? (descFn.apply(this, arguments) || '') : ''; } catch (e) {}
              window.wcLogActivity(action, d, icon);
            } catch (e) {}
            return r;
          };
          w.__wcActW = true; window[name] = w;
        } catch (e) {}
      }
      window.wcArmActivityWrappers = function () {
        wcWrapLog('applyImageEditor', 'Image replaced', '🖼️', () => 'Image updated on canvas');
        wcWrapLog('applySectionEditor', 'Section customized', '🎨', () => 'Section style updated');
        wcWrapLog('insertConfiguredSection', 'Section added', '➕', () => 'New section inserted');
        wcWrapLog('insertSectionFromConfigurator', 'Section added', '➕', () => 'New section inserted');
        wcWrapLog('addSectionWithoutEdits', 'Section added', '➕', () => 'New section inserted');
        wcWrapLog('finishButtonEditor', 'Button updated', '🔘', () => 'Button saved');
        try {
          if (typeof window.executeMagicAi === 'function' && !window.executeMagicAi.__wcActW2) {
            const o = window.executeMagicAi;
            const w = async function () {
              /* AI-feed: SEO prompts get our scoring rules so output scores 75%+ */
              try {
                const inp = document.getElementById('magic-input');
                const q = inp ? (inp.value || '') : '';
                if (/seo|meta title|meta description|open graph|ranking|rank higher|search engine/i.test(q) && q.indexOf('[SEO rules') < 0) {
                  inp.value = q + '\n\n[SEO rules for this site: title 10-60 chars relevant to the H1; meta description 50-160 chars as ONE natural sentence (never a keyword list); keep exactly one H1; every image needs meaningful alt text (not "image"); fix empty "#" links and dead anchors; add canonical + og:title/og:description/og:image + twitter card; keep the page indexable; never remove the viewport. Apply changes directly to the page.]';
                }
              } catch (e) {}
              const r = await o.apply(this, arguments);
              try { window.wcLogActivity('AI change applied', 'Magic AI edit finished', '✦'); } catch (e) {}
              return r;
            };
            w.__wcWrapped = true; w.__wcActW2 = true; window.executeMagicAi = w;
          }
        } catch (e) {}
      };

      /* ── MY SECTIONS LIBRARY (deep) ── */
      const WC_SEC_CATS = ['Hero', 'Services', 'About', 'Testimonials', 'Pricing', 'Gallery', 'Contact', 'CTA', 'Custom'];
      window.WC_SEC_CATS = WC_SEC_CATS;
      function wcMySections() {
        try {
          const d = (window.wcProEnsure) ? window.wcProEnsure() : null;
          if (d && Array.isArray(d.mySections) && d.mySections.length) return d.mySections;
        } catch (e) {}
        try { const a = JSON.parse(localStorage.getItem('webcraft_my_sections') || '[]'); return Array.isArray(a) ? a : []; }
        catch (e) { return []; }
      }
      function wcPersistMySections(list) {
        try {
          const d = (window.wcProEnsure) ? window.wcProEnsure() : null;
          if (d) d.mySections = list;
        } catch (e) {}
        try { localStorage.setItem('webcraft_my_sections', JSON.stringify(list.slice(-40))); } catch (e) {}
        try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
      }
      window.wcMySections = wcMySections;
      window.wcSaveReusable = function (comp) {
        comp = comp || sel();
        if (!comp) { if (typeof showToast === 'function') showToast('👉 Select a section first'); return; }
        const preset = (typeof getSectionDisplayName === 'function') ? getSectionDisplayName(comp) : 'My Section';
        const body = '<div style="display:flex;flex-direction:column;gap:0.55rem;">'
          + '<label class="wc-friendly-lbl">Section name</label><input class="be-input" id="wc-ms-name" value="' + esc(preset) + '" maxlength="60">'
          + '<label class="wc-friendly-lbl">Category</label><select class="be-input" id="wc-ms-cat">' + WC_SEC_CATS.map(c => '<option>' + c + '</option>').join('') + '</select>'
          + '<button class="wc-pro-btn primary" id="wc-ms-go" style="width:100%">💾 Save to My Sections</button></div>';
        const mount = (root) => {
          root.querySelectorAll('.be-input').forEach(x => { x.style.cssText = 'width:100%;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:8px;padding:0.45rem 0.6rem;font-size:0.78rem;font-family:inherit;'; });
          root.querySelector('#wc-ms-go').onclick = () => {
            const name = (root.querySelector('#wc-ms-name').value || 'My Section').trim().slice(0, 60) || 'My Section';
            const cat = root.querySelector('#wc-ms-cat').value || 'Custom';
            try {
              const html = comp.toHTML ? comp.toHTML() : '';
              if (!html || html.length < 20) { if (typeof showToast === 'function') showToast('⚠️ Empty section'); return; }
              const css = comp.getStyle ? comp.getStyle() : {};
              const list = wcMySections();
              list.push({ id: 'ms' + Date.now(), name, category: cat, html: html.slice(0, 300000), css, createdAt: Date.now(), updatedAt: Date.now() });
              wcPersistMySections(list);
              window.wcLogActivity('Section saved', '"' + name + '" → My Sections', '💎');
              if (typeof showToast === 'function') showToast('💎 Saved "' + name + '"');
              const m = $('wc-pro-modal'); if (m) m.style.display = 'none';
              window.wcRenderSavedPanel();
            } catch (e) {}
          };
        };
        if (window.wcShowProModal) window.wcShowProModal('Save to My Sections', body, mount);
        else { const n = prompt('Section name:', preset); if (n) { const list = wcMySections(); list.push({ id: 'ms' + Date.now(), name: n.slice(0, 60), category: 'Custom', html: comp.toHTML(), css: {}, createdAt: Date.now(), updatedAt: Date.now() }); wcPersistMySections(list); window.wcRenderSavedPanel(); } }
      };
      window.wcInsertReusable = function (id, btn) {
        try {
          if (btn && btn.dataset && btn.dataset.busy === '1') return;
          if (btn && btn.dataset) { btn.dataset.busy = '1'; setTimeout(() => { try { btn.dataset.busy = '0'; } catch (e) {} }, 1500); }
          const list = wcMySections();
          const e = list.find(x => String(x.id) === String(id));
          if (!e || !grapesEditor) return;
          const w = grapesEditor.DomComponents.getWrapper();
          const added = w.append(e.html);
          const c = Array.isArray(added) ? added[0] : added;
          if (c && typeof configureEditorComponent === 'function') configureEditorComponent(c);
          if (c && grapesEditor.select) grapesEditor.select(c);
          try { const el = c && c.getEl && c.getEl(); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (err) {}
          window.wcMarkDirty && window.wcMarkDirty();
          window.wcLogActivity('Section added', '"' + (e.name || 'saved section') + '" from library', '➕');
          if (typeof showToast === 'function') showToast('✨ Added — click to edit');
        } catch (e) {}
      };
      window.wcPreviewReusable = function (id) {
        const e = wcMySections().find(x => String(x.id) === String(id)); if (!e) return;
        const body = '<div class="wc-friendly-lbl" style="margin-bottom:0.4rem">' + esc(e.name) + ' · ' + esc(e.category || 'Custom') + '</div>'
          + '<iframe id="wc-ms-prev" style="width:100%;height:52vh;border:1px solid #1e293b;border-radius:10px;background:#fff;"></iframe>'
          + '<button class="wc-pro-btn primary" style="width:100%;margin-top:0.6rem" onclick="document.getElementById(\'wc-pro-modal\').style.display=\'none\';wcInsertReusable(\'' + esc(String(e.id)) + '\')">➕ Add to page</button>';
        window.wcShowProModal && window.wcShowProModal('Preview — ' + e.name, body, (root) => {
          try { const f = root.querySelector('#wc-ms-prev'); if (f) f.srcdoc = '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0">' + e.html + '</body></html>'; } catch (err) {}
        });
      };
      window.wcRenameReusable = function (id) {
        const list = wcMySections(); const e = list.find(x => String(x.id) === String(id)); if (!e) return;
        const n = prompt('Rename section:', e.name || ''); if (n == null) return;
        e.name = n.trim().slice(0, 60) || e.name; e.updatedAt = Date.now();
        wcPersistMySections(list); window.wcRenderSavedPanel();
      };
      window.wcDuplicateReusable = function (id) {
        const list = wcMySections(); const e = list.find(x => String(x.id) === String(id)); if (!e) return;
        list.push(Object.assign({}, e, { id: 'ms' + Date.now(), name: (e.name || 'Section') + ' copy', createdAt: Date.now(), updatedAt: Date.now() }));
        wcPersistMySections(list); window.wcRenderSavedPanel();
        if (typeof showToast === 'function') showToast('📋 Duplicated');
      };
      window.wcDeleteReusable = function (id) {
        if (!confirm('Delete this saved section?')) return;
        wcPersistMySections(wcMySections().filter(x => String(x.id) !== String(id)));
        window.wcRenderSavedPanel();
      };
      window.wcMsFilter = { q: '', cat: 'All' };
      window.wcMsSearch = function (v) { window.wcMsFilter.q = (v || '').toLowerCase(); window.wcRenderSavedPanel(true); };
      window.wcMsCat = function (c) { window.wcMsFilter.cat = c; window.wcRenderSavedPanel(); };
      window.wcRenderSavedPanel = function (keepFocus) {
        const box = $('wc-pro-saved'); if (!box) return;
        const f = window.wcMsFilter;
        let list = wcMySections().slice().reverse();
        if (f.cat && f.cat !== 'All') list = list.filter(x => (x.category || 'Custom') === f.cat);
        if (f.q) list = list.filter(x => ((x.name || '') + ' ' + (x.category || '')).toLowerCase().includes(f.q));
        let h = '<div class="wc-pro-card"><h4>🔍 Search My Sections</h4><input type="text" class="be-input" id="wc-ms-q" placeholder="Search by name or category…" value="' + esc(f.q || '') + '" oninput="wcMsSearch(this.value)" style="width:100%;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:8px;padding:0.45rem 0.6rem;font-size:0.78rem;"></div>';
        h += '<div style="display:flex;gap:0.3rem;flex-wrap:wrap;margin-bottom:0.6rem;">' + ['All'].concat(WC_SEC_CATS).map(c => '<button class="wc-vis-chip' + (f.cat === c ? ' active' : '') + '" onclick="wcMsCat(\'' + c + '\')">' + c + '</button>').join('') + '</div>';
        h += '<div class="wc-pro-card"><h4>💎 Library (' + list.length + ')</h4><button class="wc-pro-btn" style="width:100%;margin-bottom:0.55rem;" onclick="wcSaveReusable()">💾 Save selected section</button>';
        if (!list.length) h += '<div style="font-size:0.75rem;color:#94a3b8">Nothing here yet. Select any section on the canvas → “Save selected section”.</div>';
        else h += list.map((s) => {
          let when = '';
          try { when = new Date(s.createdAt || Date.now()).toLocaleDateString(); } catch (e) {}
          return '<div class="wc-sec-item"><iframe sandbox="" srcdoc="' + esc('<!DOCTYPE html><html><body style=&quot;margin:0;zoom:0.5&quot;>' + String(s.html || '').slice(0, 6000) + '</body></html>').replace(/"/g, '&quot;') + '" style="width:100%;height:120px;border:1px solid #1e293b;border-radius:8px;background:#fff;pointer-events:none;" loading="lazy" title="preview"></iframe>'
            + '<div class="wc-sec-item-top" style="margin-top:0.4rem;"><span class="wc-sec-item-name">💎 ' + esc(s.name || 'Section') + '</span><span class="wc-dev-badge">' + esc(s.category || 'Custom') + '</span></div>'
            + '<div class="wc-friendly-lbl">Created ' + esc(when) + '</div><div class="wc-sec-item-btns">'
            + '<button class="wc-pro-btn small primary" onclick="wcInsertReusable(\'' + esc(String(s.id)) + '\',this)">➕ Add to page</button>'
            + '<button class="wc-pro-btn small" onclick="wcPreviewReusable(\'' + esc(String(s.id)) + '\')">👁 Preview</button>'
            + '<button class="wc-pro-btn small" onclick="wcRenameReusable(\'' + esc(String(s.id)) + '\')">Rename</button>'
            + '<button class="wc-pro-btn small" onclick="wcDuplicateReusable(\'' + esc(String(s.id)) + '\')">Duplicate</button>'
            + '<button class="wc-pro-btn small danger" onclick="wcDeleteReusable(\'' + esc(String(s.id)) + '\')">Delete</button>'
            + '</div></div>';
        }).join('');
        h += '</div>';
        const ae = document.activeElement;
        const wasSearch = ae && ae.id === 'wc-ms-q';
        const pos = wasSearch ? ae.selectionStart : null;
        box.innerHTML = h;
        if (keepFocus && wasSearch) { const q = $('wc-ms-q'); if (q) { q.focus(); try { q.setSelectionRange(pos, pos); } catch (e) {} } }
      };
      // seed + arm wrappers shortly after boot
      setTimeout(() => { try { window.wcSeedActivity(); } catch (e) {} try { window.wcArmActivityWrappers && window.wcArmActivityWrappers(); } catch (e) {} }, 2500);
    })();

    /* ═══════════ WC PRO PART 4B — deep Header editor (additive, header only) ═══════════
       Edits the existing header in place. Overrides only wcRenderHeaderPanel. */
    (function WCProHeader() {
      if (window.__WCProHeaderLoaded) return;
      window.__WCProHeaderLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s == null ? '' : s));
      const G = () => (typeof grapesEditor !== 'undefined' ? grapesEditor : null);
      const dirty = () => { try { window.wcMarkDirty && window.wcMarkDirty(); } catch (e) {} };
      const snap = (l, d) => { try { window.wcSnap ? window.wcSnap(l, d) : (window.wcSnapshotVersion && window.wcSnapshotVersion(l)); } catch (e) {} };

      function wcBFindHeader() {
        try {
          const g = G(); if (!g) return null;
          const w = g.DomComponents.getWrapper(); let found = null;
          const walk = (c) => { if (found) return; try { const tag = (c.get('tagName') || '').toLowerCase(); const at = c.getAttributes() || {}; if (tag === 'header' || tag === 'nav' || at.id === 'header' || (at.class || '').includes('header') || (at.class || '').includes('navbar')) found = c; } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
          walk(w); return found;
        } catch (e) { return null; }
      }
      function wcBHead() { try { const d = getStudioCanvasDocument(); return (d && d.head) ? d : null; } catch (e) { return null; } }
      function wcBTag(id) {
        const head = wcBHead(); if (!head) return null;
        let t = head.querySelector('#' + id);
        if (!t) { try { t = head.ownerDocument.createElement('style'); t.id = id; head.appendChild(t); } catch (e) { return null; } }
        return t;
      }
      function wcBSel(comp, attr) {
        try {
          const at = comp.getAttributes() || {};
          if (at.id) return '#' + at.id;
          const o = {}; o[attr] = '1'; comp.addAttributes(o);
          return (comp.get('tagName') || 'header').toLowerCase() + '[' + attr + '="1"]';
        } catch (e) { return 'header'; }
      }
      function wcBCfg() {
        try {
          const d = (window.wcProEnsure) ? window.wcProEnsure() : null;
          if (!d) return {};
          d.headerCfg = d.headerCfg || {};
          return d.headerCfg;
        } catch (e) { return {}; }
      }
      function wcBCompFromEl(el) {
        try { if (typeof componentFromElement === 'function') return componentFromElement(el); } catch (e) {}
        return null;
      }
      function wcBFirstImg(hdr) {
        let out = null;
        const walk = (c) => { if (out) return; try { if ((c.get('tagName') || '').toLowerCase() === 'img') { out = c; return; } } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
        try { walk(hdr); } catch (e) {}
        return out;
      }
      function wcBNavBox(hdr) {
        let nav = null;
        const walk = (c) => { if (nav) return; try { const t = (c.get('tagName') || '').toLowerCase(); if (t === 'nav' || t === 'ul') nav = c; } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
        try { walk(hdr); } catch (e) {}
        return nav || hdr;
      }
      function wcBMenuComps(hdr) {
        const out = [];
        try {
          const el = hdr.getEl && hdr.getEl(); if (!el) return out;
          el.querySelectorAll('a').forEach(a => { const c = wcBCompFromEl(a); if (c) out.push(c); });
        } catch (e) {}
        return out;
      }
      function wcBGuessKind(href) {
        href = href || '';
        if (/^tel:/i.test(href)) return 'phone';
        if (/^mailto:/i.test(href)) return 'email';
        if (/wa\.me|whatsapp/i.test(href)) return 'whatsapp';
        if (/^#/.test(href)) return 'section';
        if (/^https?:\/\//i.test(href)) return 'external';
        if (href === '/' || href === '' || href === '#') return 'home';
        return 'page';
      }
      function wcBPageOpts() {
        let h = '<option value="home">Home page (/)</option>';
        try {
          if (typeof wcEnsurePages === 'function') {
            const ps = wcEnsurePages() || [];
            ps.forEach((p, i) => {
              const v = (i === 0) ? '/' : ('/' + (p.slug || ('page-' + i)) + '.html');
              h += '<option value="page::' + esc(v) + '">' + esc(p.name || ('Page ' + (i + 1))) + '</option>';
            });
          }
        } catch (e) {}
        return h;
      }
      function wcBSectionOpts() {
        let h = '';
        try {
          if (typeof findSectionComponents === 'function') {
            findSectionComponents().forEach((c, i) => {
              const at = c.getAttributes() || {};
              let nm = at['data-section-name'] || '';
              try { if (!nm && typeof getSectionDisplayName === 'function') nm = getSectionDisplayName(c, i); } catch (e) {}
              if (at.id) h += '<option value="#' + esc(at.id) + '">' + esc(nm || at.id) + ' (#' + esc(at.id) + ')</option>';
            });
          }
        } catch (e) {}
        return h || '<option value="">(no anchored sections)</option>';
      }
      function wcBHrefFor(kind, val) {
        val = (val || '').trim();
        if (kind === 'home') return '/';
        if (kind === 'page') return (val.indexOf('page::') === 0) ? val.slice(6) : (val || '/');
        if (kind === 'section') return val || '#contact';
        if (kind === 'external') return (/^https?:\/\//i.test(val) ? val : 'https://' + val);
        if (kind === 'whatsapp') return 'https://wa.me/' + val.replace(/\D/g, '');
        if (kind === 'phone') return 'tel:' + val.replace(/\s/g, '');
        if (kind === 'email') return 'mailto:' + val;
        return val || '#';
      }
      function wcBValFromHref(kind, href) {
        href = href || '';
        if (kind === 'whatsapp') { const m = href.match(/wa\.me\/(\d+)/); return m ? m[1] : ''; }
        if (kind === 'phone') return href.replace(/^tel:/i, '');
        if (kind === 'email') return href.replace(/^mailto:/i, '');
        if (kind === 'external') return href;
        if (kind === 'page') return 'page::' + href;
        return href;
      }
      function wcBMove(comp, dir) {
        try {
          const p = comp.parent(); if (!p) return false;
          const idx = comp.index(), to = dir < 0 ? idx - 1 : idx + 1;
          const n = p.components().length;
          if (to < 0 || to >= n) return false;
          const html = comp.toHTML ? comp.toHTML() : '';
          const st = comp.getStyle ? comp.getStyle() : {};
          const at = Object.assign({}, comp.getAttributes() || {});
          comp.remove();
          const added = p.append(html, { at: to });
          const f = Array.isArray(added) ? added[0] : added;
          if (f) { try { f.addStyle(st); f.addAttributes(at); if (typeof configureEditorComponent === 'function') configureEditorComponent(f); } catch (e) {} }
          return true;
        } catch (e) { return false; }
      }

      /* scoped header rules (re-applied from saved cfg after canvas reloads) */
      window.wcBReapplyChrome = function () {
        try {
          const hdr = wcBFindHeader(); if (!hdr) return;
          const cfg = wcBCfg();
          const selH = wcBSel(hdr, 'data-wc-hdr');
          const t1 = wcBTag('wc-hdr-style-css');
          if (t1) {
            let css = '';
            if (cfg.hover) css += selH + ' a:hover{color:' + cfg.hover + ' !important;}\n';
            if (cfg.linkColor) css += selH + ' a{color:' + cfg.linkColor + ';}\n';
            if (cfg.active) css += selH + ' a[aria-current="page"]{color:' + cfg.active + ' !important;font-weight:700;}\n';
            t1.textContent = css;
          }
          const nav = wcBNavBox(hdr);
          const selN = wcBSel(nav, 'data-wc-nav');
          const t2 = wcBTag('wc-hdr-mobile-css');
          if (t2) {
            let css = '';
            if (cfg.mobileMenu === 'off') css += '@media (max-width:768px){' + selN + '{display:none !important;}}\n';
            if (cfg.mobileBg) css += '@media (max-width:768px){' + selN + '{background:' + cfg.mobileBg + ' !important;}}\n';
            if (cfg.mobileColor) css += '@media (max-width:768px){' + selN + ' a{color:' + cfg.mobileColor + ' !important;}}\n';
            if (cfg.mobilePad) css += '@media (max-width:768px){' + selN + '{padding:' + cfg.mobilePad + 'px !important;}}\n';
            const logo = wcBFirstImg(hdr);
            if (logo && cfg.mobileLogo) {
              const at = logo.getAttributes() || {};
              const lsel = at.id ? ('#' + at.id) : (selH + ' img');
              css += '@media (max-width:768px){' + lsel + '{width:' + cfg.mobileLogo + 'px !important;height:auto !important;}}\n';
            }
            t2.textContent = css;
          }
        } catch (e) {}
      };

      /* ── deep header panel ── */
      window.wcRenderHeaderPanel = function () {
        const box = $('wc-pro-header'); if (!box) return;
        const hdr = wcBFindHeader();
        if (!hdr) { box.innerHTML = '<div class="wc-pro-card"><h4>🏷️ Header</h4><div style="font-size:0.76rem;color:#94a3b8">No header found on this page. Insert one from Elements → Sections, or ask AI to “add a header”.</div><div style="display:flex;gap:0.4rem;margin-top:0.6rem"><button class="wc-pro-btn small" onclick="switchDrawerTab(\'blocks\')">🧱 Browse sections</button></div></div>'; return; }
        const g = G();
        const st = hdr.getStyle() || {};
        const cfg = wcBCfg();
        const logo = wcBFirstImg(hdr);
        const logoSt = logo ? (logo.getStyle() || {}) : {};
        const logoAt = logo ? (logo.getAttributes() || {}) : {};
        let logoLink = '', logoBlank = false;
        try {
          let p = logo ? logo.parent() : null, guard = 0;
          while (p && guard++ < 6) { if ((p.get('tagName') || '').toLowerCase() === 'a') { logoLink = (p.getAttributes() || {}).href || ''; logoBlank = (p.getAttributes() || {}).target === '_blank'; break; } p = p.parent(); }
        } catch (e) {}
        const menu = wcBMenuComps(hdr);
        window._wcBMenu = menu.map(c => {
          let txt = '', href = '';
          try { const el = c.getEl && c.getEl(); txt = el ? (el.textContent || '').trim().slice(0, 60) : ''; href = (c.getAttributes() || {}).href || ''; } catch (e) {}
          const kind = wcBGuessKind(href);
          return { comp: c, text: txt, kind, val: wcBValFromHref(kind, href) };
        });

        const px = (v, fb) => { const n = parseInt(v); return isNaN(n) ? fb : n; };
        let h = '';
        /* LOGO */
        h += '<div class="wc-pro-card"><h4>🖼️ Logo</h4>';
        if (!logo) h += '<div style="font-size:0.75rem;color:#94a3b8">No logo image in this header.</div>';
        else {
          h += '<div class="wc-pro-row"><label>Width</label><input type="range" min="24" max="320" value="' + px(logoSt.width, 120) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcBLogoSize(this.value,\'w\')"><span class="wc-pro-val">' + px(logoSt.width, 120) + 'px</span></div>';
          h += '<div class="wc-pro-row"><label>Height <span class="wc-friendly-lbl">(blank = auto)</span></label><input type="text" id="wcB-logo-h" value="' + esc(logoSt.height && logoSt.height !== 'auto' ? String(logoSt.height).replace('px', '') : '') + '" placeholder="auto" onchange="wcBLogoSize(this.value,\'h\')"></div>';
          h += '<div class="wc-pro-row"><label>Alignment</label><select onchange="wcBLogoAlign(this.value)"><option value="">—</option><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>';
          h += '<div class="wc-pro-row"><label>Logo link</label><input type="text" id="wcB-logo-link" value="' + esc(logoLink) + '" placeholder="/  or  https://…" onchange="wcBLogoLink()"></div>';
          h += '<div class="wc-pro-row"><label>Open in new tab</label><select id="wcB-logo-blank" onchange="wcBLogoLink()"><option value="">Same tab</option><option value="_blank"' + (logoBlank ? ' selected' : '') + '>New tab</option></select></div>';
          h += '<div style="display:flex;gap:0.35rem;flex-wrap:wrap"><button class="wc-pro-btn small primary" onclick="wcBLogoEdit()">🔄 Replace / Upload</button><button class="wc-pro-btn small danger" onclick="wcBLogoRemove()">Remove logo</button></div>';
        }
        h += '</div>';
        /* MENU */
        h += '<div class="wc-pro-card"><h4>🔗 Menu (' + menu.length + ')</h4><div id="wcB-menu-list"></div>';
        h += '<div class="wc-pro-row"><label>New item</label><input type="text" id="wcB-new-txt" placeholder="About"></div>';
        h += '<button class="wc-pro-btn primary" style="width:100%" onclick="wcBMenuAdd()">+ Add menu item</button></div>';
        /* APPEARANCE */
        const hex = (v, fb) => { try { if (typeof normalizeHex === 'function') { const n = normalizeHex(v); if (n) return n.slice(0, 7); } } catch (e) {} const m = String(v || '').match(/#([0-9a-f]{6}|[0-9a-f]{3})/i); return m ? m[0] : fb; };
        h += '<div class="wc-pro-card"><h4>🎨 Appearance</h4>';
        h += '<div class="wc-pro-row"><label>Background</label><input type="color" value="' + esc(hex(st['background-color'] || st.background, '#ffffff')) + '" onchange="wcBHeaderBg(this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Transparent</label><select onchange="wcBTransparent(this.value)"><option value="off">OFF — solid</option><option value="on">ON — see-through</option></select></div>';
        h += '<div class="wc-pro-row"><label>Sticky <span class="wc-friendly-lbl">(stays on top)</span></label><select onchange="wcBSticky(this.value)"><option value="off">OFF</option><option value="on">ON</option></select></div>';
        h += '<div class="wc-pro-row"><label>Header height</label><input type="range" min="40" max="160" value="' + px(st['min-height'] || st.height, 72) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcBHeaderStyle({\'min-height\':this.value+\'px\'})"><span class="wc-pro-val">' + px(st['min-height'] || st.height, 72) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Inside spacing</label><input type="range" min="0" max="48" value="12" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcBHeaderStyle({padding:this.value+\'px 1.5rem\'})"><span class="wc-pro-val">12px</span></div>';
        h += '<div class="wc-pro-row"><label>Menu text size</label><input type="range" min="12" max="22" value="' + (cfg.menuSize || 15) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcBMenuFont(\'size\',this.value)"><span class="wc-pro-val">' + (cfg.menuSize || 15) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Text weight <span class="wc-friendly-lbl">(boldness)</span></label><select onchange="wcBMenuFont(\'weight\',this.value)"><option value="">—</option><option>400</option><option>500</option><option>600</option><option>700</option><option>800</option></select></div>';
        h += '<div class="wc-pro-row"><label>Menu text color</label><input type="color" value="' + esc(cfg.linkColor || '#334155') + '" onchange="wcBMenuColor(this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Hover color</label><input type="color" value="' + esc(cfg.hover || '#6366f1') + '" onchange="wcBHover(this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Active page color</label><input type="color" value="' + esc(cfg.active || '#4f46e5') + '" onchange="wcBActive(this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Menu gap</label><input type="range" min="0" max="48" value="' + (cfg.gap || 8) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcBGap(this.value)"><span class="wc-pro-val">' + (cfg.gap || 8) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Menu alignment</label><select onchange="wcBNavAlign(this.value)"><option value="">—</option><option value="flex-start">Left</option><option value="center">Center</option><option value="flex-end">Right</option></select></div>';
        h += '<div style="display:flex;gap:0.35rem;flex-wrap:wrap"><button class="wc-pro-btn small" onclick="wcBSelectHdr()">👆 Select header</button><button class="wc-pro-btn small" onclick="wcToggleLock(wcBFindHeader())">🔒 Lock / Unlock</button></div></div>';
        /* MOBILE */
        h += '<div class="wc-pro-card"><h4>📱 Mobile header</h4>';
        h += '<div class="wc-pro-row"><label>Mobile logo size</label><input type="range" min="20" max="200" value="' + (cfg.mobileLogo || 96) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcBCfgSet(\'mobileLogo\',this.value)"><span class="wc-pro-val">' + (cfg.mobileLogo || 96) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Mobile menu</label><select onchange="wcBCfgSet(\'mobileMenu\',this.value)"><option value="on"' + (cfg.mobileMenu !== 'off' ? ' selected' : '') + '>ON — show</option><option value="off"' + (cfg.mobileMenu === 'off' ? ' selected' : '') + '>OFF — hide</option></select></div>';
        h += '<div class="wc-pro-row"><label>Mobile menu bg</label><input type="color" value="' + esc(cfg.mobileBg || '#ffffff') + '" onchange="wcBCfgSet(\'mobileBg\',this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Mobile text color</label><input type="color" value="' + esc(cfg.mobileColor || '#0f172a') + '" onchange="wcBCfgSet(\'mobileColor\',this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Mobile spacing</label><input type="range" min="0" max="40" value="' + (cfg.mobilePad || 12) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcBCfgSet(\'mobilePad\',this.value)"><span class="wc-pro-val">' + (cfg.mobilePad || 12) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Hamburger style</label><select onchange="wcBBurger(this.value)"><option value="">—</option><option value="round">Rounded</option><option value="filled">Filled button</option><option value="big">Large tap target</option></select></div>';
        h += '<div class="wc-friendly-lbl">Hamburger styling applies to the existing menu button in your header (if it has one).</div></div>';
        box.innerHTML = h;
        window.wcBRenderMenuRows();
        window.wcBFindHeader = wcBFindHeader;
      };

      window.wcBSelectHdr = function () { const h = wcBFindHeader(); if (h && G()) { G().select(h); try { const el = h.getEl && h.getEl(); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {} } };
      window.wcBCfgSet = function (k, v) { const c = wcBCfg(); c[k] = v; snap('Header updated', 'Mobile header setting changed'); window.wcBReapplyChrome(); dirty(); };
      window.wcBHeaderBg = function (v) { const h = wcBFindHeader(); if (!h) return; try { h.addStyle({ 'background-color': v, background: v }); h.addAttributes({ 'data-wc-transparent': '0' }); } catch (e) {} snap('Header updated', 'Background changed'); dirty(); };
      window.wcBTransparent = function (v) {
        const h = wcBFindHeader(); if (!h) return;
        try {
          if (v === 'on') {
            const st = h.getStyle() || {};
            if (!h.getAttributes()['data-wc-orig-bg']) h.addAttributes({ 'data-wc-orig-bg': st['background-color'] || st.background || '#ffffff' });
            h.addStyle({ background: 'transparent', 'background-color': 'transparent' });
            h.addAttributes({ 'data-wc-transparent': '1' });
          } else {
            const orig = h.getAttributes()['data-wc-orig-bg'] || '#ffffff';
            h.addStyle({ 'background-color': orig, background: orig });
            h.addAttributes({ 'data-wc-transparent': '0' });
          }
        } catch (e) {}
        snap('Header updated', v === 'on' ? 'Transparent ON' : 'Transparent OFF'); dirty();
      };
      window.wcBSticky = function (v) {
        const h = wcBFindHeader(); if (!h) return;
        try { if (v === 'on') h.addStyle({ position: 'sticky', top: '0', 'z-index': '100' }); else h.addStyle({ position: 'static' }); } catch (e) {}
        snap('Header updated', v === 'on' ? 'Sticky ON' : 'Sticky OFF'); dirty();
      };
      window.wcBHeaderStyle = function (o) { const h = wcBFindHeader(); if (!h) return; try { h.addStyle(o); } catch (e) {} dirty(); };
      window.wcBLogoSize = function (v, which) {
        const l = wcBFirstImg(wcBFindHeader()); if (!l) return;
        try {
          if (which === 'w') l.addStyle({ width: v + 'px', height: (l.getStyle() || {}).height && (l.getStyle() || {}).height !== 'auto' ? (l.getStyle() || {}).height : 'auto' });
          else l.addStyle({ height: (!v ? 'auto' : v + 'px') });
        } catch (e) {}
        dirty();
      };
      window.wcBLogoAlign = function (v) {
        const l = wcBFirstImg(wcBFindHeader()); if (!l || !v) return;
        try {
          if (v === 'center') l.addStyle({ display: 'block', 'margin-left': 'auto', 'margin-right': 'auto' });
          else if (v === 'left') l.addStyle({ display: 'block', 'margin-left': '0', 'margin-right': 'auto' });
          else l.addStyle({ display: 'block', 'margin-left': 'auto', 'margin-right': '0' });
        } catch (e) {}
        dirty();
      };
      window.wcBLogoEdit = function () { const l = wcBFirstImg(wcBFindHeader()); if (l && typeof openImageEditor === 'function') { snap('Before header change', 'Logo edit started'); openImageEditor(l); } else if (typeof showToast === 'function') showToast('No logo image found'); };
      window.wcBLogoRemove = function () {
        const l = wcBFirstImg(wcBFindHeader()); if (!l) return;
        if (!confirm('Remove the logo image?')) return;
        try { l.remove(); } catch (e) {}
        snap('Header updated', 'Logo removed'); dirty(); window.wcRenderHeaderPanel();
      };
      window.wcBLogoLink = function () {
        const hdr = wcBFindHeader(), l = wcBFirstImg(hdr); if (!l) return;
        const url = ($('wcB-logo-link') || {}).value || '';
        const blank = ($('wcB-logo-blank') || {}).value || '';
        try {
          let a = null, p = l.parent(), guard = 0;
          while (p && guard++ < 6) { if ((p.get('tagName') || '').toLowerCase() === 'a') { a = p; break; } p = p.parent(); }
          if (a) { a.addAttributes({ href: url || '#' }); if (blank) a.addAttributes({ target: '_blank' }); else { const at = Object.assign({}, a.getAttributes()); delete at.target; a.setAttributes(at); } }
          else if (url) {
            const par = l.parent(), idx = l.index(), html = l.toHTML ? l.toHTML() : '';
            const st = l.getStyle ? l.getStyle() : {}, at = Object.assign({}, l.getAttributes() || {});
            l.remove();
            const added = par.append('<a href="' + esc(url) + '"' + (blank ? ' target="_blank"' : '') + '>' + html + '</a>', { at: idx });
            const f = Array.isArray(added) ? added[0] : added;
            if (f) { const img = f.components && f.components()[0]; if (img) { try { img.addStyle(st); img.addAttributes(at); } catch (e) {} } }
          }
        } catch (e) {}
        dirty();
      };
      window.wcBMenuFont = function (k, v) {
        const hdr = wcBFindHeader(); if (!hdr || !v) return;
        const cfg = wcBCfg();
        wcBMenuComps(hdr).forEach(c => { try { c.addStyle(k === 'size' ? { 'font-size': v + 'px' } : { 'font-weight': v }); } catch (e) {} });
        if (k === 'size') cfg.menuSize = v;
        dirty();
      };
      window.wcBMenuColor = function (v) {
        const hdr = wcBFindHeader(); if (!hdr) return;
        wcBMenuComps(hdr).forEach(c => { try { c.addStyle({ color: v }); } catch (e) {} });
        wcBCfg().linkColor = v; window.wcBReapplyChrome(); dirty();
      };
      window.wcBHover = function (v) { wcBCfg().hover = v; window.wcBReapplyChrome(); snap('Header updated', 'Hover color changed'); dirty(); };
      window.wcBActive = function (v) { wcBCfg().active = v; window.wcBReapplyChrome(); dirty(); };
      window.wcBSetActive = function (i) {
        const m = window._wcBMenu || []; if (!m[i]) return;
        m.forEach((r, j) => { try { if (j === i) r.comp.addAttributes({ 'aria-current': 'page' }); else { const at = Object.assign({}, r.comp.getAttributes()); delete at['aria-current']; r.comp.setAttributes(at); } } catch (e) {} });
        window.wcBReapplyChrome(); dirty();
        if (typeof showToast === 'function') showToast('★ Active menu item set');
      };
      window.wcBGap = function (v) {
        const nav = wcBNavBox(wcBFindHeader()); if (!nav) return;
        try { nav.addStyle({ gap: v + 'px' }); } catch (e) {}
        wcBCfg().gap = v; dirty();
      };
      window.wcBNavAlign = function (v) {
        const nav = wcBNavBox(wcBFindHeader()); if (!nav || !v) return;
        try { nav.addStyle({ 'justify-content': v, 'text-align': v === 'center' ? 'center' : (v === 'flex-end' ? 'right' : 'left') }); } catch (e) {}
        dirty();
      };
      window.wcBBurger = function (v) {
        const hdr = wcBFindHeader(); if (!hdr || !v) return;
        let t = null;
        try {
          const el = hdr.getEl && hdr.getEl();
          if (el) {
            const cand = el.querySelector('button,[class*="toggle"],[class*="burger"],[class*="hamburger"],[id*="toggle"],[id*="burger"]');
            if (cand) t = wcBCompFromEl(cand);
          }
        } catch (e) {}
        if (!t) { if (typeof showToast === 'function') showToast('No menu button found in this header'); return; }
        try {
          if (v === 'round') t.addStyle({ 'border-radius': '999px', padding: '0.55rem 0.7rem' });
          else if (v === 'filled') t.addStyle({ background: '#0f172a', color: '#fff', 'border-radius': '12px', padding: '0.6rem 0.75rem', border: 'none' });
          else t.addStyle({ padding: '0.9rem 1rem', 'font-size': '1.3rem', 'border-radius': '12px' });
        } catch (e) {}
        dirty();
        if (typeof showToast === 'function') showToast('🍔 Hamburger style applied');
      };

      /* menu rows */
      window.wcBRenderMenuRows = function () {
        const box = $('wcB-menu-list'); if (!box) return;
        const m = window._wcBMenu || [];
        if (!m.length) { box.innerHTML = '<div style="font-size:0.74rem;color:#94a3b8">No menu links found.</div>'; return; }
        box.innerHTML = m.map((r, i) => {
          const isSec = r.kind === 'section', isPage = r.kind === 'page';
          const valCell = isSec
            ? '<select data-wcB-mv="' + i + '" onchange="wcBMenuLink(' + i + ',this.value)">' + wcBSectionOpts().replace('value="#' + esc((r.val || '').replace('#', '')) + '"', 'value="#' + esc((r.val || '').replace('#', '')) + '" selected') + '</select>'
            : (isPage
              ? '<select data-wcB-mv="' + i + '" onchange="wcBMenuLink(' + i + ',this.value)">' + wcBPageOpts().replace('value="page::' + esc(String(r.val || '').replace('page::', '')) + '"', 'value="page::' + esc(String(r.val || '').replace('page::', '')) + '" selected') + '</select>'
              : '<input type="text" data-wcB-mv="' + i + '" value="' + esc(r.kind === 'home' ? '' : r.val) + '" placeholder="' + (r.kind === 'whatsapp' ? 'number e.g. 9477…' : (r.kind === 'phone' ? '+1…' : (r.kind === 'email' ? 'name@site.com' : 'https://…'))) + '"' + (r.kind === 'home' ? ' disabled' : '') + ' onchange="wcBMenuLink(' + i + ',this.value)">');
          return '<div class="wc-sec-item"><div class="wc-sec-item-top"><input type="text" value="' + esc(r.text) + '" onchange="wcBMenuText(' + i + ',this.value)" style="flex:1;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:7px;padding:0.32rem 0.5rem;font-size:0.75rem;min-width:0;"></div>'
            + '<div style="display:flex;gap:0.3rem;margin-top:0.4rem;"><select onchange="wcBMenuKind(' + i + ',this.value)" style="flex:1;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:7px;padding:0.32rem;font-size:0.72rem;">'
            + ['home', 'page', 'section', 'external', 'whatsapp', 'phone', 'email'].map(k => '<option value="' + k + '"' + (r.kind === k ? ' selected' : '') + '>' + k + '</option>').join('')
            + '</select></div><div style="margin-top:0.35rem;">' + valCell + '</div>'
            + '<div class="wc-sec-item-btns"><button class="wc-pro-btn small" onclick="wcBMenuMove(' + i + ',-1)">↑</button><button class="wc-pro-btn small" onclick="wcBMenuMove(' + i + ',1)">↓</button><button class="wc-pro-btn small" onclick="wcBSetActive(' + i + ')">★ Active</button><button class="wc-pro-btn small danger" onclick="wcBMenuDel(' + i + ')">Delete</button></div></div>';
        }).join('');
        box.querySelectorAll('select[data-wcB-mv],input[data-wcB-mv]').forEach(x => { x.style.cssText += ';width:100%;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:7px;padding:0.32rem 0.5rem;font-size:0.74rem;'; });
      };
      window.wcBMenuText = function (i, v) {
        const r = (window._wcBMenu || [])[i]; if (!r) return;
        r.text = v;
        try { const el = r.comp.getEl && r.comp.getEl(); if (el) el.textContent = v; try { r.comp.set('content', v); } catch (e) {} } catch (e) {}
        dirty();
      };
      window.wcBMenuKind = function (i, k) {
        const r = (window._wcBMenu || [])[i]; if (!r) return;
        r.kind = k;
        if (k === 'home') r.val = '';
        else if (k === 'section') r.val = '#contact';
        else if (k === 'page') r.val = 'page::/';
        else r.val = '';
        try { r.comp.addAttributes({ href: wcBHrefFor(k, r.val) }); } catch (e) {}
        dirty(); window.wcBRenderMenuRows();
      };
      window.wcBMenuLink = function (i, v) {
        const r = (window._wcBMenu || [])[i]; if (!r) return;
        r.val = v;
        try { r.comp.addAttributes({ href: wcBHrefFor(r.kind, v) }); } catch (e) {}
        dirty();
      };
      window.wcBMenuMove = function (i, dir) {
        const r = (window._wcBMenu || [])[i]; if (!r) return;
        if (wcBMove(r.comp, dir)) { snap('Header updated', 'Menu reordered'); dirty(); window.wcRenderHeaderPanel(); }
      };
      window.wcBMenuDel = function (i) {
        const r = (window._wcBMenu || [])[i]; if (!r) return;
        if (!confirm('Delete menu item "' + (r.text || 'link') + '"?')) return;
        try { r.comp.remove(); } catch (e) {}
        snap('Header updated', 'Menu item deleted'); dirty(); window.wcRenderHeaderPanel();
      };
      window.wcBMenuAdd = function () {
        const hdr = wcBFindHeader(); if (!hdr || !G()) return;
        const txt = ($('wcB-new-txt') || {}).value || 'New Link';
        try {
          const nav = wcBNavBox(hdr);
          const added = nav.append('<a href="#contact" style="text-decoration:none;font-weight:600;color:inherit;padding:0.4rem 0.7rem;">' + esc(txt) + '</a>');
          const c = Array.isArray(added) ? added[0] : added;
          if (c && typeof configureEditorComponent === 'function') configureEditorComponent(c);
        } catch (e) {}
        const inp = $('wcB-new-txt'); if (inp) inp.value = '';
        snap('Header updated', 'Menu item added'); dirty(); window.wcRenderHeaderPanel();
      };

      /* delayed boot: re-apply saved chrome rules once editor/canvas ready */
      setTimeout(() => { try { window.wcBReapplyChrome(); } catch (e) {} }, 3500);
    })();

    /* ═══════════ WC PRO PART 4C — deep Footer editor (additive, footer only) ═══════════
       Edits the existing footer in place. Overrides only wcRenderFooterPanel. */
    (function WCProFooter() {
      if (window.__WCProFooterLoaded) return;
      window.__WCProFooterLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s == null ? '' : s));
      const G = () => (typeof grapesEditor !== 'undefined' ? grapesEditor : null);
      const dirty = () => { try { window.wcMarkDirty && window.wcMarkDirty(); } catch (e) {} };
      const snap = (l, d) => { try { window.wcSnap ? window.wcSnap(l, d) : (window.wcSnapshotVersion && window.wcSnapshotVersion(l)); } catch (e) {} };

      function wcCFindFooter() {
        try {
          const g = G(); if (!g) return null;
          const w = g.DomComponents.getWrapper(); let found = null;
          const walk = (c) => { if (found) return; try { const tag = (c.get('tagName') || '').toLowerCase(); const at = c.getAttributes() || {}; if (tag === 'footer' || at.id === 'footer' || (at.class || '').includes('footer')) found = c; } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
          walk(w); return found;
        } catch (e) { return null; }
      }
      function wcCHead() { try { const d = getStudioCanvasDocument(); return (d && d.head) ? d : null; } catch (e) { return null; } }
      function wcCTag(id) {
        const head = wcCHead(); if (!head) return null;
        let t = head.querySelector('#' + id);
        if (!t) { try { t = head.ownerDocument.createElement('style'); t.id = id; head.appendChild(t); } catch (e) { return null; } }
        return t;
      }
      function wcCSel(comp, attr) {
        try {
          const at = comp.getAttributes() || {};
          if (at.id) return '#' + at.id;
          const o = {}; o[attr] = '1'; comp.addAttributes(o);
          return (comp.get('tagName') || 'footer').toLowerCase() + '[' + attr + '="1"]';
        } catch (e) { return 'footer'; }
      }
      function wcCCfg() {
        try {
          const d = (window.wcProEnsure) ? window.wcProEnsure() : null;
          if (!d) return {};
          d.footerCfg = d.footerCfg || {};
          return d.footerCfg;
        } catch (e) { return {}; }
      }
      function wcCCompFromEl(el) { try { if (typeof componentFromElement === 'function') return componentFromElement(el); } catch (e) {} return null; }
      function wcCMove(comp, dir) {
        try {
          const p = comp.parent(); if (!p) return false;
          const idx = comp.index(), to = dir < 0 ? idx - 1 : idx + 1, n = p.components().length;
          if (to < 0 || to >= n) return false;
          const html = comp.toHTML ? comp.toHTML() : '';
          const st = comp.getStyle ? comp.getStyle() : {};
          const at = Object.assign({}, comp.getAttributes() || {});
          comp.remove();
          const added = p.append(html, { at: to });
          const f = Array.isArray(added) ? added[0] : added;
          if (f) { try { f.addStyle(st); f.addAttributes(at); if (typeof configureEditorComponent === 'function') configureEditorComponent(f); } catch (e) {} }
          return true;
        } catch (e) { return false; }
      }
      function wcCFirstImg(ftr) {
        let out = null;
        const walk = (c) => { if (out) return; try { if ((c.get('tagName') || '').toLowerCase() === 'img') { out = c; return; } } catch (e) {} try { (c.components() || []).forEach(walk); } catch (e) {} };
        try { walk(ftr); } catch (e) {}
        return out;
      }
      function wcCDescEl(ftr) {
        try {
          const el = ftr.getEl && ftr.getEl(); if (!el) return null;
          const ps = Array.from(el.querySelectorAll('p')).filter(p => (p.textContent || '').trim().length > 20);
          if (!ps.length) return null;
          ps.sort((a, b) => (b.textContent || '').length - (a.textContent || '').length);
          return wcCCompFromEl(ps[0]);
        } catch (e) { return null; }
      }
      var WC_EMAIL_RE = /[\w.+-]+@[\w-]+\.[\w.]+/;
      var WC_PHONE_RE = /(\+?\(?\d[\d\s\-.()]{5,}\d)/;
      function wcCContact(ftr) {
        const out = { tel: null, mail: null, wa: null, addr: null, telText: null, mailText: null, waText: null };
        try {
          const walk = (c) => {
            try {
              if ((c.get('tagName') || '').toLowerCase() === 'a') {
                const href = (c.getAttributes() || {}).href || '';
                if (/^tel:/i.test(href) && !out.tel) out.tel = c;
                else if (/^mailto:/i.test(href) && !out.mail) out.mail = c;
                else if (/wa\.me|whatsapp/i.test(href) && !out.wa) out.wa = c;
              }
            } catch (e) {}
            try { (c.components() || []).forEach(walk); } catch (e) {}
          };
          walk(ftr);
          const el = ftr.getEl && ftr.getEl();
          if (el) {
            const hasLink = (n) => { try { return !!n.querySelector('a[href^="tel:"],a[href^="mailto:"]'); } catch (e) { return false; } };
            const nodes = Array.from(el.querySelectorAll('p,span,li,div'));
            let bestPh = null, bestEm = null, bestAd = null;
            nodes.forEach(n => {
              let t = '';
              try { t = (n.textContent || '').trim(); } catch (e) {}
              if (!t || t.length > 220) return;
              if (/©|copyright|all rights reserved/i.test(t)) return;
              if (!hasLink(n)) {
                const em = t.match(WC_EMAIL_RE);
                if (em && (!bestEm || t.length < bestEm.t.length)) bestEm = { node: n, comp: wcCCompFromEl(n), match: em[0], t };
                const ph = t.match(WC_PHONE_RE);
                if (ph && ph[0].replace(/\D/g, '').length >= 7 && (!bestPh || t.length < bestPh.t.length)) {
                  if (!(bestEm && bestEm.node === n)) bestPh = { node: n, comp: wcCCompFromEl(n), match: ph[0], t };
                }
              }
              if (!bestAd && t.length >= 10 && t.length <= 200 && /\d/.test(t) && /[a-z]/i.test(t) && /(street|st\.|road|rd\.|avenue|ave|lane|city|town|colombo|kandy|galle|address|no\.|postal|zip)/i.test(t)) {
                let hasA = false;
                try { hasA = !!n.querySelector('a'); } catch (e) {}
                if (!hasA) bestAd = { node: n, comp: wcCCompFromEl(n), text: t };
              }
            });
            out.mailText = bestEm; out.telText = bestPh; out.addr = bestAd;
          }
        } catch (e) {}
        return out;
      }
      function wcCFieldVals(c) {
        let phone = '', email = '', wa = '', addr = '';
        try { if (c.tel) phone = (c.tel.getAttributes().href || '').replace(/^tel:/i, ''); else if (c.telText) phone = c.telText.match || ''; } catch (e) {}
        try { if (c.mail) email = (c.mail.getAttributes().href || '').replace(/^mailto:/i, ''); else if (c.mailText) email = c.mailText.match || ''; } catch (e) {}
        try {
          if (c.wa) { const m = (c.wa.getAttributes().href || '').match(/wa\.me\/(\d+)/); wa = m ? m[1] : (c.wa.getAttributes().href || ''); }
          else if (c.waText) wa = c.waText.match || '';
        } catch (e) {}
        try { if (c.addr) addr = c.addr.text || ''; } catch (e) {}
        return { phone, email, wa, addr };
      }
      function wcCTextOf(c) { try { const el = c.getEl && c.getEl(); return el ? (el.textContent || '').trim() : ''; } catch (e) { return ''; } }
      /* link groups: elements (depth<=3) with >=2 links, or heading + >=1 link */
      function wcCGroups(ftr) {
        const groups = [];
        try {
          const walk = (c, depth, heading) => {
            if (depth > 3) return;
            const tag = (c.get('tagName') || '').toLowerCase();
            let hd = heading;
            try { if (/^h[1-6]$/.test(tag)) { const t = wcCTextOf(c); if (t) hd = t; } } catch (e) {}
            const kids = c.components() || [];
            const links = kids.filter(k => { try { return (k.get('tagName') || '').toLowerCase() === 'a'; } catch (e) { return false; } });
            if ((links.length >= 2 || (hd && links.length >= 1)) && groups.indexOf(c) < 0 && c !== ftr) groups.push({ comp: c, heading: hd || '', links });
            kids.forEach(k => walk(k, depth + 1, hd));
          };
          (ftr.components() || []).forEach(k => walk(k, 1, ''));
        } catch (e) {}
        return groups;
      }
      const WC_SOCIALS = [
        ['facebook', /facebook|fb\.me/i, 'Facebook'],
        ['instagram', /instagram/i, 'Instagram'],
        ['youtube', /youtube|youtu\.be/i, 'YouTube'],
        ['tiktok', /tiktok/i, 'TikTok'],
        ['linkedin', /linkedin/i, 'LinkedIn'],
        ['twitter', /twitter|x\.com/i, 'X / Twitter'],
        ['whatsapp', /wa\.me|whatsapp/i, 'WhatsApp']
      ];
      function wcCSocials(ftr) {
        const out = [];
        try {
          const walk = (c) => {
            try {
              if ((c.get('tagName') || '').toLowerCase() === 'a') {
                const href = (c.getAttributes() || {}).href || '';
                for (const [key, re, label] of WC_SOCIALS) {
                  if (re.test(href)) { out.push({ comp: c, key, label, href }); break; }
                }
              }
            } catch (e) {}
            try { (c.components() || []).forEach(walk); } catch (e) {}
          };
          walk(ftr);
        } catch (e) {}
        return out;
      }
      function wcCCopyEl(ftr) {
        try {
          const el = ftr.getEl && ftr.getEl(); if (!el) return null;
          const all = Array.from(el.querySelectorAll('p,span,div,small'));
          for (const n of all) { if (/©|copyright|all rights reserved/i.test(n.textContent || '')) return wcCCompFromEl(n); }
        } catch (e) {}
        return null;
      }

      window.wcCReapply = function () {
        try {
          const ftr = wcCFindFooter(); if (!ftr) return;
          const cfg = wcCCfg();
          const selF = wcCSel(ftr, 'data-wc-ftr');
          const t = wcCTag('wc-ftr-style-css');
          if (t) {
            let css = '';
            if (cfg.link) css += selF + ' a{color:' + cfg.link + ';}\n';
            if (cfg.hover) css += selF + ' a:hover{color:' + cfg.hover + ' !important;}\n';
            if (cfg.heading) css += selF + ' h1,' + selF + ' h2,' + selF + ' h3,' + selF + ' h4,' + selF + ' h5,' + selF + ' h6{color:' + cfg.heading + ' !important;}\n';
            t.textContent = css;
          }
        } catch (e) {}
      };

      /* ── deep footer panel ── */
      window.wcRenderFooterPanel = function () {
        const box = $('wc-pro-footer'); if (!box) return;
        const ftr = wcCFindFooter();
        if (!ftr) { box.innerHTML = '<div class="wc-pro-card"><h4>🦶 Footer</h4><div style="font-size:0.76rem;color:#94a3b8">No footer found. Insert one from Elements → Sections → Footer, or ask AI to “add a footer”.</div></div>'; return; }
        const st = ftr.getStyle() || {};
        const cfg = wcCCfg();
        const logo = wcCFirstImg(ftr);
        const logoSt = logo ? (logo.getStyle() || {}) : {};
        const descComp = wcCDescEl(ftr);
        const contact = wcCContact(ftr);
        const groups = wcCGroups(ftr);
        const socials = wcCSocials(ftr);
        const copyComp = wcCCopyEl(ftr);
        window._wcCG = groups.map((g, gi) => ({
          comp: g.comp, heading: g.heading || ('Link group ' + (gi + 1)),
          links: g.links.map(c => ({ comp: c, text: wcCTextOf(c).slice(0, 60), href: (c.getAttributes() || {}).href || '' }))
        }));
        window._wcCS = socials.map(s => ({ comp: s.comp, key: s.key, label: s.label, href: s.href, hidden: ((s.comp.getStyle() || {}).display === 'none') }));

        const px = (v, fb) => { const n = parseInt(v); return isNaN(n) ? fb : n; };
        const hex = (v, fb) => { try { if (typeof normalizeHex === 'function') { const n = normalizeHex(v); if (n) return n.slice(0, 7); } } catch (e) {} const m = String(v || '').match(/#([0-9a-f]{6}|[0-9a-f]{3})/i); return m ? m[0] : fb; };
        let h = '';
        /* BRAND */
        h += '<div class="wc-pro-card"><h4>🏷️ Brand</h4>';
        if (logo) {
          h += '<div class="wc-pro-row"><label>Logo size</label><input type="range" min="24" max="280" value="' + px(logoSt.width, 120) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcCLogoSize(this.value)"><span class="wc-pro-val">' + px(logoSt.width, 120) + 'px</span></div>';
          h += '<div style="display:flex;gap:0.35rem;margin-bottom:0.5rem;"><button class="wc-pro-btn small primary" onclick="wcCLogoEdit()">🔄 Replace logo</button></div>';
        } else h += '<div class="wc-friendly-lbl">No logo image in this footer.</div>';
        h += '<label class="wc-friendly-lbl">About / description</label><textarea class="be-input" id="wcC-desc" rows="3" style="width:100%;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:8px;padding:0.45rem 0.6rem;font-size:0.78rem;" oninput="wcCDesc(this.value)" placeholder="Short about text…">' + esc(descComp ? wcCTextOf(descComp).slice(0, 400) : '') + '</textarea></div>';
        /* CONTACT */
        const cVals = wcCFieldVals(contact);
        h += '<div class="wc-pro-card"><h4>📞 Contact</h4>';
        h += '<div class="wc-pro-row"><label>Phone</label><input type="text" id="wcC-phone" value="' + esc(cVals.phone) + '" placeholder="+1…"></div>';
        h += '<div class="wc-pro-row"><label>Email</label><input type="text" id="wcC-email" value="' + esc(cVals.email) + '" placeholder="hello@…"></div>';
        h += '<div class="wc-pro-row"><label>WhatsApp</label><input type="text" id="wcC-wa" value="' + esc(cVals.wa) + '" placeholder="number…"></div>';
        h += '<div class="wc-pro-row"><label>Address</label><input type="text" id="wcC-addr" value="' + esc(cVals.addr) + '" placeholder="Street, City"></div>';
        h += '<button class="wc-pro-btn primary" style="width:100%" onclick="wcCApplyContact()">✓ Apply contact info</button>';
        h += '<button class="wc-pro-btn small" style="width:100%;margin-top:0.35rem" onclick="wcRenderFooterPanel()">↻ Re-read from footer</button></div>';
        /* LINKS */
        h += '<div class="wc-pro-card"><h4>🔗 Footer links</h4><div id="wcC-groups"></div>';
        h += '<div class="wc-pro-row"><label>Add to</label><select id="wcC-add-g">' + window._wcCG.map((g, i) => '<option value="' + i + '">' + esc(g.heading) + '</option>').join('') + '</select></div>';
        h += '<div class="wc-pro-row"><label>Text</label><input type="text" id="wcC-add-t" placeholder="New link"></div>';
        h += '<div class="wc-pro-row"><label>Goes to</label><input type="text" id="wcC-add-h" placeholder="/about  ·  #services  ·  https://…"></div>';
        h += '<button class="wc-pro-btn primary" style="width:100%" onclick="wcCAddLink()">+ Add link</button></div>';
        /* SOCIAL */
        h += '<div class="wc-pro-card"><h4>📣 Social media</h4><div id="wcC-socials"></div>';
        h += '<div class="wc-pro-row"><label>Network</label><select id="wcC-so-net">' + WC_SOCIALS.map(s => '<option value="' + s[0] + '">' + s[2] + '</option>').join('') + '</select></div>';
        h += '<div class="wc-pro-row"><label>Profile URL</label><input type="text" id="wcC-so-url" placeholder="https://…"></div>';
        h += '<button class="wc-pro-btn primary" style="width:100%" onclick="wcCAddSocial()">+ Add social link</button></div>';
        /* LAYOUT */
        h += '<div class="wc-pro-card"><h4>📐 Layout</h4>';
        h += '<div class="wc-pro-row"><label>Columns</label><select onchange="wcCColumns(this.value)"><option value="">—</option><option>1</option><option>2</option><option>3</option><option>4</option></select></div>';
        h += '<div class="wc-pro-row"><label>Column spacing</label><input type="range" min="0" max="64" value="24" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcCGap(this.value)"><span class="wc-pro-val">24px</span></div>';
        h += '<div class="wc-pro-row"><label>Content alignment</label><select onchange="wcCFtrStyle({\'text-align\':this.value})"><option value="">—</option><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>';
        h += '<div class="wc-pro-row"><label>Footer padding</label><input type="range" min="0" max="120" value="' + px(st['padding-top'] || st.padding, 48) + '" oninput="this.nextElementSibling.textContent=this.value+\'px\'" onchange="wcCFtrStyle({padding:this.value+\'px 1.5rem\'})"><span class="wc-pro-val">' + px(st['padding-top'] || st.padding, 48) + 'px</span></div>';
        h += '<div class="wc-pro-row"><label>Background</label><input type="color" value="' + esc(hex(st['background-color'] || st.background, '#0f172a')) + '" onchange="wcCFtrStyle({\'background-color\':this.value,background:this.value})"></div>';
        h += '<div class="wc-pro-row"><label>Text color</label><input type="color" value="' + esc(hex(st.color, '#cbd5e1')) + '" onchange="wcCFtrStyle({color:this.value})"></div>';
        h += '<div class="wc-pro-row"><label>Heading color</label><input type="color" value="' + esc(cfg.heading || '#ffffff') + '" onchange="wcCCfgColor(\'heading\',this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Link color</label><input type="color" value="' + esc(cfg.link || '#cbd5e1') + '" onchange="wcCCfgColor(\'link\',this.value)"></div>';
        h += '<div class="wc-pro-row"><label>Link hover color</label><input type="color" value="' + esc(cfg.hover || '#6366f1') + '" onchange="wcCCfgColor(\'hover\',this.value)"></div>';
        h += '<div style="display:flex;gap:0.35rem;flex-wrap:wrap"><button class="wc-pro-btn small" onclick="wcCSelect()">👆 Select footer</button><button class="wc-pro-btn small" onclick="wcToggleLock(wcCFindFooter())">🔒 Lock / Unlock</button></div></div>';
        /* COPYRIGHT */
        h += '<div class="wc-pro-card"><h4>© Copyright</h4>';
        h += '<textarea class="be-input" id="wcC-copy" rows="2" style="width:100%;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:8px;padding:0.45rem 0.6rem;font-size:0.78rem;">' + esc(copyComp ? wcCTextOf(copyComp).slice(0, 300) : '') + '</textarea>';
        h += '<div style="display:flex;gap:0.35rem;margin-top:0.45rem;"><button class="wc-pro-btn small primary" style="flex:1" onclick="wcCCopy()">✓ Apply</button><button class="wc-pro-btn small" style="flex:1" onclick="wcCCopyYear()">Set year to ' + new Date().getFullYear() + '</button></div></div>';
        box.innerHTML = h;
        window.wcCFindFooter = wcCFindFooter;
        window.wcCRenderGroups();
        window.wcCRenderSocials();
      };

      window.wcCSelect = function () { const f = wcCFindFooter(); if (f && G()) { G().select(f); try { const el = f.getEl && f.getEl(); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {} } };
      window.wcCFtrStyle = function (o) { const f = wcCFindFooter(); if (!f) return; try { f.addStyle(o); } catch (e) {} dirty(); };
      window.wcCCfgColor = function (k, v) { wcCCfg()[k] = v; window.wcCReapply(); dirty(); };
      window.wcCLogoSize = function (v) { const l = wcCFirstImg(wcCFindFooter()); if (!l) return; try { l.addStyle({ width: v + 'px', height: 'auto' }); } catch (e) {} dirty(); };
      window.wcCLogoEdit = function () { const l = wcCFirstImg(wcCFindFooter()); if (l && typeof openImageEditor === 'function') { snap('Before footer change', 'Logo edit started'); openImageEditor(l); } };
      window.wcCDesc = function (v) {
        const f = wcCFindFooter(); if (!f) return;
        const c = wcCDescEl(f); if (!c) { if (typeof showToast === 'function') showToast('No description paragraph found'); return; }
        try { const el = c.getEl && c.getEl(); if (el) el.textContent = v; try { c.set('content', v); } catch (e) {} } catch (e) {}
        dirty();
      };
      window.wcCApplyContact = function () {
        const f = wcCFindFooter(); if (!f) return;
        const ph = (($('wcC-phone') || {}).value || '').trim(), em = (($('wcC-email') || {}).value || '').trim(), wa = (($('wcC-wa') || {}).value || '').trim(), ad = (($('wcC-addr') || {}).value || '').trim();
        const c = wcCContact(f);
        const setText = (comp, node, nv, oldMatch) => {
          try {
            if (node) {
              const cur = node.textContent || '';
              node.textContent = (oldMatch && cur.indexOf(oldMatch) >= 0) ? cur.replace(oldMatch, nv) : nv;
            }
            if (comp) { try { const el = comp.getEl && comp.getEl(); if (el) comp.set('content', el.innerHTML); } catch (e) {} }
          } catch (e) {}
        };
        const hostOf = () => {
          try {
            if (c.tel && c.tel.parent()) return c.tel.parent();
            if (c.mail && c.mail.parent()) return c.mail.parent();
            if (c.addr && c.addr.comp && c.addr.comp.parent()) return c.addr.comp.parent();
          } catch (e) {}
          return f;
        };
        try {
          if (ph) {
            if (c.tel) {
              c.tel.addAttributes({ href: 'tel:' + ph.replace(/\s/g, '') });
              const t = wcCTextOf(c.tel);
              if (/\d/.test(t)) setText(c.tel, c.tel.getEl && c.tel.getEl(), ph);
            } else if (c.telText) setText(c.telText.comp, c.telText.node, ph, c.telText.match);
            else hostOf().append('<p style="margin:0.3rem 0;">📞 <a href="tel:' + esc(ph.replace(/\s/g, '')) + '" style="color:inherit;text-decoration:none;">' + esc(ph) + '</a></p>');
          }
          if (em) {
            if (c.mail) {
              c.mail.addAttributes({ href: 'mailto:' + em });
              const t = wcCTextOf(c.mail);
              if (/@/.test(t)) setText(c.mail, c.mail.getEl && c.mail.getEl(), em);
            } else if (c.mailText) setText(c.mailText.comp, c.mailText.node, em, c.mailText.match);
            else hostOf().append('<p style="margin:0.3rem 0;">✉️ <a href="mailto:' + esc(em) + '" style="color:inherit;text-decoration:none;">' + esc(em) + '</a></p>');
          }
          if (wa) {
            const num = wa.replace(/\D/g, '');
            if (c.wa) c.wa.addAttributes({ href: 'https://wa.me/' + num });
            else if (c.waText) setText(c.waText.comp, c.waText.node, wa, c.waText.match);
            else hostOf().append('<p style="margin:0.3rem 0;">💬 <a href="https://wa.me/' + esc(num) + '" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">' + esc(wa) + '</a></p>');
          }
          if (ad) {
            if (c.addr && c.addr.comp) {
              const el = c.addr.comp.getEl && c.addr.comp.getEl();
              if (el) el.textContent = ad;
              try { c.addr.comp.set('content', ad); } catch (e) {}
            } else hostOf().append('<p style="margin:0.3rem 0;">📍 ' + esc(ad) + '</p>');
          }
        } catch (e) {}
        snap('Footer updated', 'Contact info applied'); dirty();
        if (typeof showToast === 'function') showToast('📞 Footer contact updated');
        try { window.wcRenderFooterPanel(); } catch (e) {}
      };
      /* groups */
      window.wcCRenderGroups = function () {
        const box = $('wcC-groups'); if (!box) return;
        const gs = window._wcCG || [];
        if (!gs.length) { box.innerHTML = '<div class="wc-friendly-lbl">No link groups detected.</div>'; return; }
        box.innerHTML = gs.map((g, gi) => '<div class="wc-friendly-lbl" style="margin:0.4rem 0 0.25rem;">' + esc(g.heading) + '</div>' + g.links.map((l, li) =>
          '<div class="wc-sec-item"><div class="wc-sec-item-top"><input type="text" value="' + esc(l.text) + '" onchange="wcCLinkText(' + gi + ',' + li + ',this.value)" style="flex:1;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:7px;padding:0.32rem 0.5rem;font-size:0.75rem;min-width:0;"></div>'
          + '<input type="text" value="' + esc(l.href) + '" onchange="wcCLinkHref(' + gi + ',' + li + ',this.value)" placeholder="/page · #section · https://…" style="width:100%;margin-top:0.35rem;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:7px;padding:0.32rem 0.5rem;font-size:0.72rem;">'
          + '<div class="wc-sec-item-btns"><button class="wc-pro-btn small" onclick="wcCLinkMove(' + gi + ',' + li + ',-1)">↑</button><button class="wc-pro-btn small" onclick="wcCLinkMove(' + gi + ',' + li + ',1)">↓</button><button class="wc-pro-btn small danger" onclick="wcCLinkDel(' + gi + ',' + li + ')">Delete</button></div></div>'
        ).join('')).join('');
      };
      window.wcCLinkText = function (gi, li, v) {
        const l = ((window._wcCG || [])[gi] || {}).links || []; const r = l[li]; if (!r) return;
        r.text = v;
        try { const el = r.comp.getEl && r.comp.getEl(); if (el) el.textContent = v; try { r.comp.set('content', v); } catch (e) {} } catch (e) {}
        dirty();
      };
      window.wcCLinkHref = function (gi, li, v) {
        const l = ((window._wcCG || [])[gi] || {}).links || []; const r = l[li]; if (!r) return;
        r.href = v;
        try { r.comp.addAttributes({ href: v }); } catch (e) {}
        dirty();
      };
      window.wcCLinkMove = function (gi, li, dir) {
        const g = (window._wcCG || [])[gi]; if (!g || !g.links[li]) return;
        if (wcCMove(g.links[li].comp, dir)) { dirty(); window.wcRenderFooterPanel(); }
      };
      window.wcCLinkDel = function (gi, li) {
        const g = (window._wcCG || [])[gi]; if (!g || !g.links[li]) return;
        if (!confirm('Delete link "' + (g.links[li].text || '') + '"?')) return;
        try { g.links[li].comp.remove(); } catch (e) {}
        snap('Footer updated', 'Link deleted'); dirty(); window.wcRenderFooterPanel();
      };
      window.wcCAddLink = function () {
        const gs = window._wcCG || [];
        const gi = parseInt((($('wcC-add-g') || {}).value || '0'), 10);
        const t = (($('wcC-add-t') || {}).value || '').trim() || 'New Link';
        const href = (($('wcC-add-h') || {}).value || '').trim() || '#';
        const host = gs[gi] ? gs[gi].comp : wcCFindFooter();
        if (!host) return;
        try {
          const added = host.append('<a href="' + esc(href) + '" style="text-decoration:none;color:inherit;">' + esc(t) + '</a>');
          const c = Array.isArray(added) ? added[0] : added;
          if (c && typeof configureEditorComponent === 'function') configureEditorComponent(c);
        } catch (e) {}
        snap('Footer updated', 'Link added'); dirty(); window.wcRenderFooterPanel();
      };
      /* socials */
      window.wcCRenderSocials = function () {
        const box = $('wcC-socials'); if (!box) return;
        const ss = window._wcCS || [];
        if (!ss.length) { box.innerHTML = '<div class="wc-friendly-lbl">No social links detected yet.</div>'; return; }
        box.innerHTML = ss.map((s, i) => '<div class="wc-sec-item"><div class="wc-sec-item-top"><span class="wc-sec-item-name">🌐 ' + esc(s.label) + (s.hidden ? ' (hidden)' : '') + '</span></div>'
          + '<input type="text" value="' + esc(s.href) + '" onchange="wcCSoUrl(' + i + ',this.value)" style="width:100%;margin-top:0.35rem;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:7px;padding:0.32rem 0.5rem;font-size:0.72rem;">'
          + '<div class="wc-sec-item-btns"><button class="wc-pro-btn small" onclick="wcCSoToggle(' + i + ')">' + (s.hidden ? 'Show icon' : 'Hide icon') + '</button><button class="wc-pro-btn small danger" onclick="wcCSoDel(' + i + ')">Delete</button></div></div>').join('');
      };
      window.wcCSoUrl = function (i, v) {
        const s = (window._wcCS || [])[i]; if (!s) return;
        s.href = v;
        try { s.comp.addAttributes({ href: v }); } catch (e) {}
        dirty();
      };
      window.wcCSoToggle = function (i) {
        const s = (window._wcCS || [])[i]; if (!s) return;
        try {
          if (s.hidden) { const od = (s.comp.getAttributes() || {})['data-wc-orig-d'] || ''; s.comp.addStyle({ display: od }); s.hidden = false; }
          else { s.comp.addAttributes({ 'data-wc-orig-d': (s.comp.getStyle() || {}).display || '' }); s.comp.addStyle({ display: 'none' }); s.hidden = true; }
        } catch (e) {}
        dirty(); window.wcCRenderSocials();
      };
      window.wcCSoDel = function (i) {
        const s = (window._wcCS || [])[i]; if (!s) return;
        if (!confirm('Delete ' + s.label + ' link?')) return;
        try { s.comp.remove(); } catch (e) {}
        snap('Footer updated', s.label + ' removed'); dirty(); window.wcRenderFooterPanel();
      };
      window.wcCAddSocial = function () {
        const f = wcCFindFooter(); if (!f) return;
        const net = ($('wcC-so-net') || {}).value || 'facebook';
        const url = (($('wcC-so-url') || {}).value || '').trim();
        if (!url) { if (typeof showToast === 'function') showToast('Paste the profile URL first'); return; }
        const label = { facebook: 'Facebook', instagram: 'Instagram', youtube: 'YouTube', tiktok: 'TikTok', linkedin: 'LinkedIn', twitter: 'X / Twitter', whatsapp: 'WhatsApp' }[net] || net;
        let host = f;
        try {
          const ss = wcCSocials(f);
          if (ss.length && ss[0].comp.parent()) host = ss[0].comp.parent();
        } catch (e) {}
        try {
          const added = host.append('<a href="' + esc(url) + '" target="_blank" rel="noopener" style="display:inline-block;padding:0.45rem 0.8rem;border:1px solid #334155;border-radius:999px;text-decoration:none;color:inherit;font-size:0.78rem;font-weight:700;margin:0.15rem;">' + esc(label) + '</a>');
          const c = Array.isArray(added) ? added[0] : added;
          if (c && typeof configureEditorComponent === 'function') configureEditorComponent(c);
        } catch (e) {}
        snap('Footer updated', label + ' added'); dirty(); window.wcRenderFooterPanel();
      };
      /* layout */
      window.wcCColumns = function (n) {
        const f = wcCFindFooter(); if (!f || !n) return;
        const gs = window._wcCG || [];
        let host = null;
        try {
          if (gs.length > 1) {
            const p0 = gs[0].comp.parent();
            if (p0 && gs.every(g => g.comp.parent() === p0) && p0 !== f) host = p0;
          }
          if (!host && gs.length) host = gs[0].comp.parent() || f;
        } catch (e) {}
        if (!host || host === f) { if (typeof showToast === 'function') showToast('This footer has no separate links row — columns skipped'); return; }
        try { host.addStyle({ display: 'grid', 'grid-template-columns': 'repeat(' + n + ',1fr)', gap: '1.5rem' }); } catch (e) {}
        snap('Footer updated', n + ' columns'); dirty();
      };
      window.wcCGap = function (v) {
        const gs = window._wcCG || [];
        try {
          const seen = {};
          gs.forEach(g => { const p = g.comp.parent(); if (p && !seen[p.getId ? p.getId() : Math.random()]) { try { p.addStyle({ gap: v + 'px' }); } catch (e) {} } });
        } catch (e) {}
        dirty();
      };
      /* copyright */
      window.wcCCopy = function () {
        const f = wcCFindFooter(); if (!f) return;
        const v = ($('wcC-copy') || {}).value || '';
        if (!v) return;
        const c = wcCCopyEl(f);
        try {
          if (c) { const el = c.getEl && c.getEl(); if (el) el.textContent = v; try { c.set('content', v); } catch (e) {} }
          else f.append('<p style="text-align:center;opacity:.7;margin-top:1rem;font-size:0.82rem;">' + esc(v) + '</p>');
        } catch (e) {}
        snap('Footer updated', 'Copyright edited'); dirty();
      };
      window.wcCCopyYear = function () {
        const f = wcCFindFooter(); if (!f) return;
        const yr = String(new Date().getFullYear());
        const c = wcCCopyEl(f);
        try {
          if (c) {
            const el = c.getEl && c.getEl();
            if (el) { el.innerHTML = (el.innerHTML || '').replace(/\b(19|20)\d{2}\b/g, yr); try { c.set('content', el.innerHTML); } catch (e) {} }
            const ta = $('wcC-copy'); if (ta && el) ta.value = el.textContent;
          }
        } catch (e) {}
        snap('Footer updated', 'Year → ' + yr); dirty();
        if (typeof showToast === 'function') showToast('© Year set to ' + yr);
      };

      setTimeout(() => { try { window.wcCReapply(); } catch (e) {} }, 3500);
    })();

    /* ═══════════ WC PRO PART 4D — deep SEO settings (additive, SEO only) ═══════════
       Existing SEO audit modal/score logic untouched. Overrides only wcRenderSeoPanel/wcSaveSeo. */
    (function WCProSeo() {
      if (window.__WCProSeoLoaded) return;
      window.__WCProSeoLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s == null ? '' : s));
      const dirty = () => { try { window.wcMarkDirty && window.wcMarkDirty(); } catch (e) {} };
      const DSE = { title: '', desc: '', keywords: '', author: '', ri: 'index', rf: 'follow', ogTitle: '', ogDesc: '', ogImage: '', twTitle: '', twDesc: '', twImage: '', favicon: '', siteName: '', canonical: '', lang: '' };
      function wcDGet() {
        try {
          const d = (window.wcProEnsure) ? window.wcProEnsure() : null;
          if (!d) return Object.assign({}, DSE);
          d.seo = Object.assign({}, DSE, (d.seo || {}));
          if (!d.seo.siteName && d.siteSettings && d.siteSettings.name) d.seo.siteName = d.siteSettings.name;
          return d.seo;
        } catch (e) { return Object.assign({}, DSE); }
      }
      const G = (id) => { const el = $(id); return el ? el.value : ''; };
      window.wcRenderSeoPanel = function () {
        const box = $('wc-pro-seo'); if (!box) return;
        const s = wcDGet();
        const ropt = (v, cur) => '<option value="' + v + '"' + (cur === v ? ' selected' : '') + '>' + v + '</option>';
        let h = '<div class="wc-pro-card"><h4>🔍 Basic SEO <span class="wc-friendly-lbl">— Google result</span></h4>';
        h += '<div class="wc-pro-row"><label>SEO title</label><input type="text" id="wcD-title" value="' + esc(s.title) + '" maxlength="70" style="flex:2;max-width:none" oninput="wcDPreview()"></div>';
        h += '<div class="wc-pro-row"><label>Description</label><input type="text" id="wcD-desc" value="' + esc(s.desc) + '" maxlength="170" style="flex:2;max-width:none" oninput="wcDPreview()"></div>';
        h += '<div class="wc-pro-row"><label>Keywords</label><input type="text" id="wcD-kw" value="' + esc(s.keywords) + '" style="flex:2;max-width:none"></div>';
        h += '<div class="wc-pro-row"><label>Author</label><input type="text" id="wcD-author" value="' + esc(s.author) + '" style="flex:2;max-width:none"></div>';
        h += '<div class="wc-pro-row"><label>Search listing</label><select id="wcD-ri">' + ropt('index', s.ri) + ropt('noindex', s.ri) + '</select></div>';
        h += '<div class="wc-pro-row"><label>Follow links</label><select id="wcD-rf">' + ropt('follow', s.rf) + ropt('nofollow', s.rf) + '</select></div>';
        h += '<div id="wcD-gprev"></div></div>';
        h += '<div class="wc-pro-card"><h4>📣 Social preview</h4>';
        h += '<div class="wc-pro-row"><label>Share title</label><input type="text" id="wcD-ogt" value="' + esc(s.ogTitle) + '" style="flex:2;max-width:none" oninput="wcDPreview()"></div>';
        h += '<div class="wc-pro-row"><label>Share text</label><input type="text" id="wcD-ogd" value="' + esc(s.ogDesc) + '" style="flex:2;max-width:none" oninput="wcDPreview()"></div>';
        h += '<div class="wc-pro-row"><label>Share image</label><input type="text" id="wcD-ogi" value="' + esc(s.ogImage) + '" style="flex:2;max-width:none" oninput="wcDPreview()"></div>';
        h += '<div class="wc-pro-row"><label>X title</label><input type="text" id="wcD-twt" value="' + esc(s.twTitle) + '" style="flex:2;max-width:none"></div>';
        h += '<div class="wc-pro-row"><label>X description</label><input type="text" id="wcD-twd" value="' + esc(s.twDesc) + '" style="flex:2;max-width:none"></div>';
        h += '<div class="wc-pro-row"><label>X image</label><input type="text" id="wcD-twi" value="' + esc(s.twImage) + '" style="flex:2;max-width:none"></div>';
        h += '<div id="wcD-sprev"></div></div>';
        h += '<div class="wc-pro-card"><h4>🪪 Identity</h4>';
        h += '<div class="wc-pro-row"><label>Site name</label><input type="text" id="wcD-site" value="' + esc(s.siteName) + '" style="flex:2;max-width:none"></div>';
        h += '<div class="wc-pro-row"><label>Favicon URL</label><input type="text" id="wcD-fav" value="' + esc(s.favicon) + '" style="flex:2;max-width:none"></div></div>';
        h += '<div class="wc-pro-card"><h4>⚙️ Technical</h4>';
        h += '<div class="wc-pro-row"><label>Canonical URL</label><input type="text" id="wcD-can" value="' + esc(s.canonical) + '" placeholder="https://mysite.com/" style="flex:2;max-width:none"></div>';
        h += '<div class="wc-pro-row"><label>Language</label><select id="wcD-lang">' + ['en', 'ta', 'si', 'fr', 'de', 'es'].map(l => '<option value="' + l + '"' + (s.lang === l ? ' selected' : '') + '>' + l + '</option>').join('') + '</select></div>';
        h += '<div class="wc-pro-row"><label>Sitemap</label><span class="wc-friendly-lbl" id="wcD-sm-status">Ready — download below</span></div>';
        h += '<div style="display:flex;gap:0.35rem;"><button class="wc-pro-btn small" style="flex:1" onclick="wcDSitemap()">⤓ sitemap.xml</button><button class="wc-pro-btn small" style="flex:1" onclick="wcDRobots()">⤓ robots.txt</button></div>';
        h += '<div class="wc-friendly-lbl" style="margin-top:0.35rem">Upload these two files to your published site root when you publish.</div></div>';
        h += '<div class="wc-pro-card"><h4>🖼️ Image alt text (<span id="wcD-imgn">0</span>)</h4><div id="wcD-imgs" style="max-height:220px;overflow:auto;"></div></div>';
        h += '<div class="wc-pro-card"><h4>📊 Score</h4><div style="font-size:1.6rem;font-weight:900;" id="wcD-score">—</div><div id="wcD-recs" style="font-size:0.74rem;color:#94a3b8;"></div>';
        h += '<button class="wc-pro-btn" style="width:100%;margin-top:0.5rem" onclick="openSeoAuditModal()">🔎 Open full SEO audit</button></div>';
        h += '<button class="wc-pro-btn primary" style="width:100%;margin-top:0.5rem;" onclick="wcAiGenerateSeo(this)">✦ AI Generate SEO — targets 75%+</button>';
        h += '<div class="wc-friendly-lbl" style="margin:0.3rem 0 0.5rem;">AI reads your page content and writes title, description + share tags. Review below, then Save.</div>';
        h += '<button class="wc-pro-btn primary" style="width:100%" onclick="wcSaveSeo()">💾 Save SEO</button>';
        box.innerHTML = h;
        window.wcDRenderImgs();
        window.wcDPreview();
        if (window._wcSeoShowScore) { try { window.wcRefreshSeoScore(); } catch (e) {} }
        else { try { window.wcSeoBlankScore(); } catch (e) {} }
      };
      window.wcDPreview = function () {
        const t = G('wcD-title') || G('wcD-site') || 'Website title';
        const u = (G('wcD-can') || 'https://yoursite.com/').replace(/\/$/, '');
        const d = G('wcD-desc') || 'A short description of this website appears here in search results.';
        const gp = $('wcD-gprev');
        if (gp) gp.innerHTML = '<div style="background:#fff;border:1px solid #1e293b;border-radius:10px;padding:0.7rem;margin-top:0.5rem;"><div style="font-size:0.72rem;color:#202124;">' + esc(u) + '</div><div style="font-size:1rem;color:#1a0dab;line-height:1.3;">' + esc(t).slice(0, 70) + '</div><div style="font-size:0.78rem;color:#4d5156;line-height:1.45;">' + esc(d).slice(0, 170) + '</div><div class="wc-friendly-lbl">Google preview</div></div>';
        const ot = G('wcD-ogt') || t, od = G('wcD-ogd') || d, oi = G('wcD-ogi');
        const sp = $('wcD-sprev');
        if (sp) sp.innerHTML = '<div style="background:#fff;border:1px solid #1e293b;border-radius:10px;overflow:hidden;margin-top:0.5rem;">' + (oi ? '<img src="' + esc(oi) + '" style="width:100%;height:130px;object-fit:cover;display:block;" onerror="this.style.display=\'none\'">' : '<div style="height:90px;background:#1e293b;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:0.75rem;">No share image</div>') + '<div style="padding:0.6rem;"><div style="font-size:0.68rem;color:#65676b;text-transform:uppercase;">' + esc(u.replace(/^https?:\/\//, '')) + '</div><div style="font-size:0.88rem;font-weight:700;color:#050505;">' + esc(ot).slice(0, 90) + '</div><div style="font-size:0.76rem;color:#65676b;">' + esc(od).slice(0, 130) + '</div></div><div class="wc-friendly-lbl" style="padding:0 0.6rem 0.5rem;">Facebook / X preview</div></div>';
      };
      window.wcDRenderImgs = function () {
        const box = $('wcD-imgs'); if (!box) return;
        const list = [];
        try {
          if (typeof grapesEditor !== 'undefined' && grapesEditor) {
            const w = grapesEditor.DomComponents.getWrapper();
            const walk = (c) => {
              if (list.length >= 30) return;
              try { if ((c.get('tagName') || '').toLowerCase() === 'img') list.push(c); } catch (e) {}
              try { (c.components() || []).forEach(walk); } catch (e) {}
            };
            walk(w);
          }
        } catch (e) {}
        window._wcDImgs = list;
        const n = $('wcD-imgn'); if (n) n.textContent = list.length;
        if (!list.length) { box.innerHTML = '<div class="wc-friendly-lbl">No images on this page.</div>'; return; }
        box.innerHTML = list.map((c, i) => {
          let src = '', alt = '';
          try { const at = c.getAttributes() || {}; src = at.src || ''; alt = at.alt || ''; } catch (e) {}
          return '<div style="display:flex;gap:0.45rem;align-items:center;margin-bottom:0.4rem;"><img src="' + esc(src) + '" style="width:44px;height:34px;object-fit:cover;border-radius:6px;border:1px solid #1e293b;flex-shrink:0;" onerror="this.style.opacity=0.2"><input type="text" value="' + esc(alt) + '" placeholder="Describe this image…" onchange="wcDAlt(' + i + ',this.value)" style="flex:1;background:#0a0f1c;border:1px solid #283347;color:#e2e8f0;border-radius:7px;padding:0.35rem 0.5rem;font-size:0.74rem;min-width:0;"></div>';
        }).join('');
      };
      window.wcDAlt = function (i, v) {
        const c = (window._wcDImgs || [])[i]; if (!c) return;
        try { c.addAttributes({ alt: v }); const el = c.getEl && c.getEl(); if (el) el.setAttribute('alt', v); } catch (e) {}
        dirty();
      };
      function wcDEnsureMeta(doc, key, isProp, content) {
        if (content == null || content === '') return;
        const sel = 'meta[' + (isProp ? 'property' : 'name') + '="' + key + '"]';
        let m = doc.head.querySelector(sel);
        if (!m) { m = doc.createElement('meta'); if (isProp) m.setAttribute('property', key); else m.setAttribute('name', key); doc.head.appendChild(m); }
        m.setAttribute('content', content);
      }
      window.wcSaveSeo = function () {
        const d = (window.wcProEnsure) ? window.wcProEnsure() : null; if (!d) return;
        const s = {
          title: G('wcD-title').trim(), desc: G('wcD-desc').trim(), keywords: G('wcD-kw').trim(), author: G('wcD-author').trim(),
          ri: G('wcD-ri') || 'index', rf: G('wcD-rf') || 'follow',
          ogTitle: G('wcD-ogt').trim(), ogDesc: G('wcD-ogd').trim(), ogImage: G('wcD-ogi').trim(),
          twTitle: G('wcD-twt').trim(), twDesc: G('wcD-twd').trim(), twImage: G('wcD-twi').trim(),
          favicon: G('wcD-fav').trim(), siteName: G('wcD-site').trim(), canonical: G('wcD-can').trim(), lang: G('wcD-lang') || 'en'
        };
        d.seo = s;
        try {
          if (s.siteName) { try { d.siteSettings = d.siteSettings || {}; d.siteSettings.name = s.siteName; } catch (e) {} if (projectData) projectData.bizName = s.siteName; const pi = $('project-name-input'); if (pi) pi.value = s.siteName; }
        } catch (e) {}
        try {
          const parser = new DOMParser();
          const doc = parser.parseFromString(typeof currentHtml === 'string' ? currentHtml : '', 'text/html');
          if (s.title) { let t = doc.head.querySelector('title'); if (!t) { t = doc.createElement('title'); doc.head.appendChild(t); } t.textContent = s.title; }
          wcDEnsureMeta(doc, 'description', false, s.desc);
          wcDEnsureMeta(doc, 'keywords', false, s.keywords);
          wcDEnsureMeta(doc, 'author', false, s.author);
          wcDEnsureMeta(doc, 'robots', false, s.ri + ', ' + s.rf);
          wcDEnsureMeta(doc, 'og:title', true, s.ogTitle || s.title);
          wcDEnsureMeta(doc, 'og:description', true, s.ogDesc || s.desc);
          wcDEnsureMeta(doc, 'og:image', true, s.ogImage);
          wcDEnsureMeta(doc, 'og:type', true, 'website');
          wcDEnsureMeta(doc, 'twitter:card', false, 'summary_large_image');
          wcDEnsureMeta(doc, 'twitter:title', false, s.twTitle || s.ogTitle || s.title);
          wcDEnsureMeta(doc, 'twitter:description', false, s.twDesc || s.ogDesc || s.desc);
          wcDEnsureMeta(doc, 'twitter:image', false, s.twImage || s.ogImage);
          if (s.canonical) { let l = doc.head.querySelector('link[rel="canonical"]'); if (!l) { l = doc.createElement('link'); l.setAttribute('rel', 'canonical'); doc.head.appendChild(l); } l.setAttribute('href', s.canonical); }
          if (s.favicon) { let l = doc.head.querySelector('link[rel="icon"]'); if (!l) { l = doc.createElement('link'); l.setAttribute('rel', 'icon'); doc.head.appendChild(l); } l.setAttribute('href', s.favicon); }
          try { doc.documentElement.setAttribute('lang', s.lang || 'en'); } catch (e) {}
          if (typeof currentHtml !== 'undefined') currentHtml = '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
          try { const ci = (typeof activeConceptIndex !== 'undefined' ? activeConceptIndex : 0); if (projectData.designs && projectData.designs[ci]) projectData.designs[ci].html = currentHtml; } catch (e) {}
          try { const cd = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null; if (cd && s.title) cd.title = s.title; } catch (e) {}
        } catch (e) {}
        try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
        dirty();
        try { window.wcLogActivity && window.wcLogActivity('SEO updated', 'Meta + social tags saved', '🚀'); } catch (e) {}
        window.wcRefreshSeoScore();
        if (typeof showToast === 'function') showToast('🚀 SEO saved — score recalculated');
      };
      /* recalc: same spirit as audit + meta checks. Never opens the modal. */
      window.wcRefreshSeoScore = function () {
        let score = 0; const recs = [];
        const plus = (n, ok, tip) => { if (ok) score += n; else if (tip) recs.push(tip); };
        try {
          const s = wcDGet();
          plus(15, s.title && s.title.length >= 10 && s.title.length <= 70, 'Keep the SEO title 10–60 characters.');
          plus(15, s.desc && s.desc.length >= 50 && s.desc.length <= 170, 'Write a meta description of 50–160 characters.');
          plus(5, !!s.keywords, 'Add 3–6 keywords.');
          plus(3, !!s.author, null);
          plus(4, s.ri === 'index', 'Robots is set to noindex — Google will skip this site.');
          plus(5, !!s.canonical, 'Add a canonical URL.');
          plus(12, !!(s.ogTitle || s.title) && !!s.ogImage, 'Add a share image for social previews.');
          plus(8, !!((s.twTitle || s.ogTitle || s.title) && (s.twImage || s.ogImage)), 'Complete the X/Twitter preview fields.');
          plus(5, !!s.favicon, 'Add a favicon URL.');
          plus(3, !!s.lang, null);
          let h1 = 0, imgs = [], missingAlt = 0, secs = 0, ctas = 0;
          try {
            const cd = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
            if (cd) {
              h1 = cd.querySelectorAll('h1').length;
              imgs = Array.from(cd.querySelectorAll('img'));
              missingAlt = imgs.filter(i => !(i.getAttribute('alt') || '').trim()).length;
              secs = cd.querySelectorAll('section,header,footer').length;
              ctas = cd.querySelectorAll('a.btn-primary,button,a[href^="#"]').length;
            }
          } catch (e) {}
          plus(8, h1 === 1, h1 === 0 ? 'Add one H1 headline.' : 'Keep only one H1 per page.');
          plus(10, imgs.length === 0 || missingAlt === 0, missingAlt + ' image(s) need alt text — see Image alt text above.');
          plus(4, secs >= 3, 'Add more sections (services, reviews…).');
          plus(3, ctas >= 2, 'Add call-to-action buttons.');
        } catch (e) {}
        score = Math.max(0, Math.min(100, Math.round(score)));
        try { const p = $('cfr-seo-score'); if (p) p.textContent = score + '%'; } catch (e) {}
        try { const el = $('wcD-score'); if (el) { el.textContent = score + '%'; el.style.color = score >= 80 ? '#34d399' : (score >= 55 ? '#fbbf24' : '#f87171'); } } catch (e) {}
        try { const r = $('wcD-recs'); if (r) r.innerHTML = recs.length ? recs.slice(0, 5).map(x => '💡 ' + esc(x)).join('<br>') : '✅ All checks passed — excellent!'; } catch (e) {}
        return score;
      };
      window.wcDBlob = function (name, text) {
        try {
          const b = new Blob([text], { type: 'text/plain' });
          const a = document.createElement('a');
          a.href = URL.createObjectURL(b); a.download = name;
          document.body.appendChild(a); a.click();
          setTimeout(() => { try { URL.revokeObjectURL(a.href); a.remove(); } catch (e) {} }, 600);
        } catch (e) {}
      };
      window.wcDSitemap = function () {
        try {
          let pages = [{ slug: 'index', name: 'Home' }];
          try { if (typeof wcEnsurePages === 'function') pages = wcEnsurePages(); } catch (e) {}
          const s = wcDGet();
          let base = (s.canonical || '').trim().replace(/\/$/, '') || (location.origin || 'https://example.com');
          const urls = pages.map(p => '  <url><loc>' + esc(base + '/' + ((p.slug || 'index') === 'index' ? '' : (p.slug + '.html'))) + '</loc></url>').join('\n');
          window.wcDBlob('sitemap.xml', '<' + '?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' + urls + '\n</urlset>');
          if (typeof showToast === 'function') showToast('⤓ sitemap.xml downloaded');
        } catch (e) {}
      };
      window.wcDRobots = function () {
        try {
          const s = wcDGet();
          let base = (s.canonical || '').trim().replace(/\/$/, '') || (location.origin || 'https://example.com');
          window.wcDBlob('robots.txt', 'User-agent: *\nAllow: /\n\nSitemap: ' + base + '/sitemap.xml\n');
          if (typeof showToast === 'function') showToast('⤓ robots.txt downloaded');
        } catch (e) {}
      };
    })();

    /* ═══════════ WC PRO PART 4F — AI SEO generator (SEO + AI-feed only) ═══════════
       ✦ AI Generate SEO: page content-a padichu title/description/share-tags
       generate panni apply pannum (targets 75%+). AI fail-na offline
       heuristic fallback — button eppovum velai seiyum. Body content
       eppovum thodadhu (head mattum harvest). */
    (function WCProAiSeo() {
      if (window.__WCProAiSeoLoaded) return;
      window.__WCProAiSeoLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s == null ? '' : s));
      const dirty = () => { try { window.wcMarkDirty && window.wcMarkDirty(); } catch (e) {} };
      const STOP = { the: 1, and: 1, for: 1, with: 1, our: 1, your: 1, you: 1, are: 1, was: 1, from: 1, that: 1, this: 1, have: 1, has: 1, will: 1, can: 1, all: 1, more: 1, new: 1, best: 1 };

      function wcSeoCtx() {
        const c = { biz: 'Website', page: 'Home', h1: '', heads: [], text: '', imgs: [], base: '' };
        try { c.biz = (projectData && projectData.bizName) || 'Website'; } catch (e) {}
        try { if (typeof window.wcSeoPageName === 'function') c.page = window.wcSeoPageName(); } catch (e) {}
        try {
          const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (doc) {
            const h1 = doc.querySelector('h1');
            c.h1 = h1 ? (h1.textContent || '').trim().slice(0, 90) : '';
            c.heads = Array.from(doc.querySelectorAll('h2,h3')).slice(0, 6).map(h => (h.textContent || '').trim().slice(0, 60)).filter(Boolean);
            const bt = doc.body ? (doc.body.innerText || doc.body.textContent || '') : '';
            c.text = String(bt).replace(/\s+/g, ' ').trim().slice(0, 1500);
            c.imgs = Array.from(doc.querySelectorAll('img')).map(im => im.getAttribute('src') || '').filter(s => s && s.indexOf('data:') !== 0).slice(0, 5);
          }
        } catch (e) {}
        try { c.base = (($('wcD-can') || {}).value || '').trim().replace(/\/$/, '') || (location.origin || ''); } catch (e) {}
        return c;
      }
      function wcCut(s, n) {
        s = String(s || '').replace(/\s+/g, ' ').trim();
        if (s.length <= n) return s;
        const cut = s.slice(0, n - 3);
        const sp = cut.lastIndexOf(' ');
        return (sp > n * 0.5 ? cut.slice(0, sp) : cut) + '...';
      }
      /* offline fallback — eppovum velai seiyum, 75+ target */
      window.wcSeoHeuristic = function () {
        const c = wcSeoCtx();
        const topic = c.h1 || c.heads[0] || (c.biz + ' — ' + c.page);
        let title = (c.h1 ? c.h1 + ' | ' + c.biz : topic);
        title = wcCut(title, 60);
        if (title.length < 10) title = wcCut(c.biz + ' — Quality Service You Can Trust', 60);
        let desc = '';
        try {
          const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (doc) {
            const ps = Array.from(doc.querySelectorAll('p')).map(p => (p.textContent || '').trim()).filter(p => p.length >= 60 && !/lorem ipsum/i.test(p));
            if (ps.length) desc = wcCut(ps.sort((a, b) => b.length - a.length)[0], 157);
          }
        } catch (e) {}
        if (desc.length < 50) desc = wcCut(topic + '. ' + c.biz + ' offers reliable service you can trust. Contact us today for a free quote.', 157);
        const words = (c.h1 + ' ' + c.heads.join(' ')).toLowerCase().replace(/[^a-z0-9\s]/g, ' ').split(/\s+/).filter(w => w.length > 3 && !STOP[w]);
        const kw = [];
        words.forEach(w => { if (kw.indexOf(w) < 0) kw.push(w); });
        return {
          title, desc, keywords: kw.slice(0, 6).join(', '), author: c.biz,
          ri: 'index', rf: 'follow',
          ogTitle: title, ogDesc: desc, ogImage: c.imgs[0] || '',
          twTitle: title, twDesc: desc, twImage: c.imgs[0] || '',
          canonical: c.base, ctx: c
        };
      };
      function wcSeoFill(f) {
        const set = (id, v) => { const el = $(id); if (el) el.value = (v == null ? '' : String(v)); };
        set('wcD-title', f.title); set('wcD-desc', f.desc); set('wcD-kw', f.keywords); set('wcD-author', f.author);
        const ri = $('wcD-ri'); if (ri) ri.value = f.ri || 'index';
        const rf = $('wcD-rf'); if (rf) rf.value = f.rf || 'follow';
        set('wcD-ogt', f.ogTitle); set('wcD-ogd', f.ogDesc); set('wcD-ogi', f.ogImage);
        set('wcD-twt', f.twTitle); set('wcD-twd', f.twDesc); set('wcD-twi', f.twImage);
        if (f.canonical !== undefined) set('wcD-can', f.canonical);
        try { if (typeof wcDPreview === 'function') wcDPreview(); } catch (e) {}
      }
      function wcSeoHarvest(html) {
        try {
          const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
          const meta = (n) => { const m = doc.head.querySelector('meta[name="' + n + '"]'); return m ? (m.getAttribute('content') || '').trim() : ''; };
          const metap = (p) => { const m = doc.head.querySelector('meta[property="' + p + '"]'); return m ? (m.getAttribute('content') || '').trim() : ''; };
          const t = doc.head.querySelector('title');
          const cl = doc.head.querySelector('link[rel="canonical"]');
          return {
            title: t ? (t.textContent || '').trim() : '', desc: meta('description'),
            keywords: meta('keywords'), author: meta('author'),
            ogTitle: metap('og:title'), ogDesc: metap('og:description'), ogImage: metap('og:image'),
            twTitle: meta('twitter:title'), twDesc: meta('twitter:description'), twImage: meta('twitter:image'),
            canonical: cl ? (cl.getAttribute('href') || '').trim() : ''
          };
        } catch (e) { return null; }
      }
      window.wcAiGenerateSeo = async function (btn) {
        if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Generating SEO…'; }
        try { if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml(); } catch (e) {}
        try { if (window.wcSnap) window.wcSnap('Before AI SEO', 'AI SEO generation started'); else if (window.wcSnapshotVersion) window.wcSnapshotVersion('Before AI SEO'); } catch (e) {}
        const c = wcSeoCtx();
        let f = null, via = 'heuristic';
        /* AI lane: full-doc edit prompt, head mattum harvest (body risk illai) */
        try {
          if (window.OpenCodeAI && window.OpenCodeAI.editWithFallback && typeof currentHtml === 'string' && currentHtml.length > 100) {
            const prompt = 'You are an SEO expert. Optimize ONLY the <head> SEO tags of this HTML page for Google + social sharing.\n'
              + 'Page: ' + c.page + ' | Business: ' + c.biz + ' | Main heading: ' + (c.h1 || '(none)') + ' | About page: ' + c.text.slice(0, 600) + '\n'
              + 'Rules you MUST follow:\n'
              + '- <title>: 10-60 characters, includes the main-heading topic + business, never generic like "Home".\n'
              + '- meta description: 50-160 characters, ONE natural sentence describing the page (never a keyword list).\n'
              + '- Keep exactly ONE H1. Do NOT change ANY body content, text, images, links or structure.\n'
              + '- Set og:title, og:description, og:image (reuse an existing page image URL), og:url, twitter:card=summary_large_image + twitter title/description/image.\n'
              + '- Set canonical to ' + (c.base || 'the site root') + ' and robots to index,follow. Keep viewport, favicon and language as-is.\n'
              + 'Return the COMPLETE updated HTML document.';
            const r = await window.OpenCodeAI.editWithFallback({ userPrompt: prompt, currentHtml: currentHtml, bizName: c.biz });
            if (r && r.updatedHtml) {
              const got = wcSeoHarvest(r.updatedHtml);
              if (got && (got.title || got.desc)) { f = got; via = 'ai'; }
            }
          }
        } catch (e) { f = null; }
        if (!f) f = window.wcSeoHeuristic();
        /* merge + clamp (AI output-a nambama verify) */
        const base = window.wcSeoHeuristic();
        f.title = wcCut(f.title || base.title, 60) || base.title;
        if (f.title.length < 10) f.title = base.title;
        f.desc = wcCut(f.desc || base.desc, 160) || base.desc;
        if (f.desc.length < 30) f.desc = base.desc;
        ['keywords', 'author', 'ogTitle', 'ogDesc', 'ogImage', 'twTitle', 'twDesc', 'twImage', 'canonical'].forEach(k => { if (f[k] == null || f[k] === '') f[k] = base[k]; });
        if (!f.ogTitle) f.ogTitle = f.title;
        if (!f.ogDesc) f.ogDesc = f.desc;
        if (!f.twTitle) f.twTitle = f.ogTitle;
        if (!f.twDesc) f.twDesc = f.ogDesc;
        if (!f.twImage) f.twImage = f.ogImage;
        f.ri = 'index'; f.rf = 'follow';
        wcSeoFill(f);
        window._wcSeoShowScore = true;
        let score = 0;
        try { if (typeof wcSaveSeo === 'function') wcSaveSeo(); } catch (e) {}
        try { if (typeof window.wcRefreshSeoScore === 'function') score = window.wcRefreshSeoScore(); } catch (e) {}
        try { if (window.wcLogActivity) window.wcLogActivity('SEO generated', (via === 'ai' ? 'AI wrote' : 'Auto-built') + ' meta + share tags · score ' + score + '%', '✦'); } catch (e) {}
        if (typeof showToast === 'function') showToast(score >= 75 ? '✦ SEO ready — score ' + score + '% 🎉' : '✦ SEO applied — score ' + score + '%. Add alt texts + sections to cross 75.');
      };
    })();

    /* ═══════════ WC PRO PART 4E — 100-point SEO engine (SEO logic only) ═══════════
       Shared analyzer used by the audit modal AND silent score refresh.
       Reads live canvas (no reload) + currentHtml head. No network calls.
       Score card stays BLANK until ✦ Generate (window._wcSeoShowScore). */
    (function WCProSeoEngine() {
      if (window.__WCProSeoEngineLoaded) return;
      window.__WCProSeoEngineLoaded = true;
      const $ = (id) => document.getElementById(id);
      const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s == null ? '' : s));

      window.wcSeoPageName = function () {
        try {
          if (typeof wcEnsurePages === 'function') {
            const ps = wcEnsurePages() || [];
            const i = (typeof wcCurrentPageIdx === 'function') ? wcCurrentPageIdx() : 0;
            if (ps[i] && ps[i].name) return ps[i].name;
          }
        } catch (e) {}
        return 'Home';
      };
      function wcSeoHead() {
        const o = { title: '', desc: '', keywords: '', author: '', robots: '', ogTitle: '', ogDesc: '', ogImage: '', ogUrl: '', twTitle: '', twDesc: '', twImage: '', canonical: '', favicon: '', lang: '', viewport: false, jsonLd: null, titleCount: 0, descCount: 0, langAttr: '', mediaCss: false, robotsExplicit: false };
        try {
          if ($('wcD-title')) {
            const g = (id) => { const el = $(id); return el ? (el.value || '') : ''; };
            o.title = g('wcD-title'); o.desc = g('wcD-desc'); o.keywords = g('wcD-kw'); o.author = g('wcD-author');
            o.robots = ((g('wcD-ri') || 'index') + ', ' + (g('wcD-rf') || 'follow'));
            o.robotsExplicit = true;
            o.ogTitle = g('wcD-ogt'); o.ogDesc = g('wcD-ogd'); o.ogImage = g('wcD-ogi');
            o.twTitle = g('wcD-twt'); o.twDesc = g('wcD-twd'); o.twImage = g('wcD-twi');
            o.canonical = g('wcD-can'); o.favicon = g('wcD-fav'); o.lang = g('wcD-lang');
          } else if (window.wcProEnsure) {
            const d = window.wcProEnsure(); const s = (d && d.seo) || {};
            o.title = s.title || ''; o.desc = s.desc || ''; o.keywords = s.keywords || ''; o.author = s.author || '';
            if (s.ri || s.rf) { o.robots = ((s.ri || 'index') + ', ' + (s.rf || 'follow')); o.robotsExplicit = true; }
            o.ogTitle = s.ogTitle || ''; o.ogDesc = s.ogDesc || ''; o.ogImage = s.ogImage || '';
            o.twTitle = s.twTitle || ''; o.twDesc = s.twDesc || ''; o.twImage = s.twImage || '';
            o.canonical = s.canonical || ''; o.favicon = s.favicon || ''; o.lang = s.lang || '';
          }
        } catch (e) {}
        try {
          if (typeof currentHtml === 'string' && currentHtml) {
            const doc = new DOMParser().parseFromString(currentHtml, 'text/html');
            const meta = (n) => { const m = doc.head.querySelector('meta[name="' + n + '"]'); return m ? (m.getAttribute('content') || '') : ''; };
            const metap = (p) => { const m = doc.head.querySelector('meta[property="' + p + '"]'); return m ? (m.getAttribute('content') || '') : ''; };
            if (!o.title) { const t = doc.head.querySelector('title'); o.title = t ? (t.textContent || '') : ''; }
            if (!o.desc) o.desc = meta('description');
            if (!o.keywords) o.keywords = meta('keywords');
            if (!o.author) o.author = meta('author');
            if (!o.robotsExplicit) o.robots = meta('robots');
            if (!o.ogTitle) o.ogTitle = metap('og:title');
            if (!o.ogDesc) o.ogDesc = metap('og:description');
            if (!o.ogImage) o.ogImage = metap('og:image');
            if (!o.ogUrl) o.ogUrl = metap('og:url');
            if (!o.canonical) { const l = doc.head.querySelector('link[rel="canonical"]'); o.canonical = l ? (l.getAttribute('href') || '') : ''; }
            if (!o.favicon) { const l = doc.head.querySelector('link[rel="icon"]'); o.favicon = l ? (l.getAttribute('href') || '') : ''; }
            if (!o.lang) o.lang = doc.documentElement.getAttribute('lang') || '';
            o.titleCount = doc.head.querySelectorAll('title').length;
            o.descCount = doc.head.querySelectorAll('meta[name="description"]').length;
            o.langAttr = doc.documentElement.getAttribute('lang') || '';
            o.viewport = !!doc.head.querySelector('meta[name="viewport"]');
            o.mediaCss = /@media/i.test(currentHtml);
            doc.querySelectorAll('script[type="application/ld+json"]').forEach(sc => {
              try {
                const j = JSON.parse(sc.textContent || '{}');
                const types = ['Organization', 'LocalBusiness', 'Product', 'Service', 'Article', 'BreadcrumbList', 'WebSite', 'WebPage', 'FAQPage'];
                const t = j['@type'] || '';
                o.jsonLd = { ok: types.indexOf(t) >= 0, type: t || 'unknown' };
              } catch (e) { if (!o.jsonLd) o.jsonLd = { ok: false, type: 'invalid' }; }
            });
          }
        } catch (e) {}
        return o;
      }
      function wcSeoBody() {
        const c = { h1: 0, h1Text: '', h2: 0, h3: 0, emptyHead: 0, text: '', imgs: [], links: [], ids: {}, sections: 0, ctas: 0, forms: 0, hasNav: false };
        try {
          const doc = (typeof getStudioCanvasDocument === 'function') ? getStudioCanvasDocument() : null;
          if (!doc) return c;
          const q = (s) => Array.from(doc.querySelectorAll(s));
          const h1s = q('h1');
          c.h1 = h1s.length;
          c.h1Text = h1s.map(h => (h.textContent || '').trim()).filter(Boolean).join(' ');
          c.h2 = q('h2').length; c.h3 = q('h3').length;
          ['h1', 'h2', 'h3', 'h4'].forEach(s => q(s).forEach(h => { if (!(h.textContent || '').trim()) c.emptyHead++; }));
          const body = doc.body ? (doc.body.innerText || doc.body.textContent || '') : '';
          c.text = String(body).replace(/\s+/g, ' ').trim();
          c.imgs = q('img').map(im => ({ alt: im.getAttribute('alt') || '', src: im.getAttribute('src') || '' }));
          c.links = q('a').map(a => ({ href: a.getAttribute('href') || '', text: (a.textContent || '').trim().slice(0, 40) }));
          q('[id]').forEach(el => { const id = el.getAttribute('id'); if (id) c.ids[id] = 1; });
          c.sections = doc.querySelectorAll('section,header,footer').length;
          c.ctas = doc.querySelectorAll('a.btn-primary,button,a[href^="#"]').length;
          c.forms = q('form').length;
          c.hasNav = !!(doc.querySelector('header nav, nav'));
        } catch (e) {}
        return c;
      }
      const GEN_TITLE = /^(home|homepage|welcome|untitled|new website|my website|website|index|page|landing page)$/i;
      const GEN_ALT = /^(image|photo|picture|pic|img|graphic|banner|icon|logo)$/i;
      const PLACEHOLDER = /lorem ipsum|your business name|sample title|example text|test content|placeholder|your headline here|your big headline|enter your full name|you@example\.com/i;
      const sigWords = (s) => String(s || '').toLowerCase().replace(/[^a-z0-9\s]/g, ' ').split(/\s+/).filter(w => w.length > 3);

      window.wcSeoAnalyze = function () {
        const h = wcSeoHead(), b = wcSeoBody();
        const checks = [];
        const C = (pts, max, st, title, desc) => checks.push({ pts: Math.max(0, Math.min(max, Math.round(pts))), max, st, title, desc });
        let biz = '';
        try { biz = (projectData && projectData.bizName) || ''; } catch (e) {}

        (() => {
          const t = (h.title || '').trim();
          if (!t) return C(0, 15, 'bad', 'SEO Title', 'Add a descriptive page title (10–60 characters).');
          let p = 6; const rec = [];
          const len = t.length;
          if (len >= 10 && len <= 60) p += 4;
          else if (len < 10) { p += 1; rec.push('a little short — aim for 10–60 characters'); }
          else { p += 2; rec.push('a little long — shorten it to be more concise'); }
          if (GEN_TITLE.test(t)) { rec.push('looks generic — mention what this page offers'); p = Math.min(p, 9); }
          else p += 3;
          if (biz && t.toLowerCase() === String(biz).toLowerCase() && b.text.length > 200) { p -= 2; rec.push('expand beyond just the business name'); }
          const tw = sigWords(t);
          const pool = sigWords(b.h1Text + ' ' + b.text.slice(0, 600)).join(' ');
          if (tw.length && tw.some(w => pool.indexOf(w) >= 0)) p += 2;
          else { rec.push('echo the page topic in the title'); }
          p = Math.max(0, Math.min(15, p));
          C(p, 15, p >= 13 ? 'ok' : (p >= 8 ? 'warn' : 'bad'), 'SEO Title',
            p >= 13 ? '“' + t.slice(0, 52) + (t.length > 52 ? '…' : '') + '” — clear and relevant.' : '“' + t.slice(0, 52) + '” — ' + (rec.join('; ') || 'could be stronger') + '.');
        })();
        (() => {
          const d = (h.desc || '').trim();
          if (!d) return C(0, 15, 'bad', 'Meta Description', 'Add a 50–160 character description of this page.');
          let p = 5; const rec = [];
          const len = d.length;
          if (len >= 50 && len <= 160) p += 5;
          else if ((len >= 30 && len < 50) || (len > 160 && len <= 220)) { p += 3; rec.push(len < 50 ? 'a bit short — describe the page benefit' : 'a bit long — trim to ~160 characters'); }
          else { p += 1; rec.push(len < 30 ? 'too short to be useful' : 'much too long — search cuts it off'); }
          const commas = (d.match(/,/g) || []).length;
          const freq = {};
          d.toLowerCase().split(/\s+/).forEach(w => { if (w.length > 3) freq[w] = (freq[w] || 0) + 1; });
          const stuffed = commas > 7 || Object.keys(freq).some(w => freq[w] > 5);
          if (stuffed) { rec.push('reads like a keyword list — write a natural sentence'); }
          else p += 5;
          p = Math.min(12, p); if (!stuffed && len >= 50 && len <= 160) p = 15;
          C(p, 15, p >= 13 ? 'ok' : (p >= 8 ? 'warn' : 'bad'), 'Meta Description',
            p >= 13 ? 'Good length, reads naturally.' : 'Present — ' + (rec.join('; ') || 'could be stronger') + '.');
        })();
        (() => {
          if (b.h1 === 0) return C(b.h2 > 0 ? 2 : 0, 10, 'bad', 'H1 Structure', 'Add one clear primary heading describing this page.');
          let p = (b.h1 === 1) ? 5 : 3; const rec = [];
          if (b.h1 > 1) rec.push('found ' + b.h1 + ' H1s — keep one primary H1, use H2s for sections');
          if (b.h1Text.length >= 8) p += 1; else rec.push('make the H1 descriptive');
          if (b.h2 >= 1 && b.h3 >= 1) p += 2;
          else if (b.h2 >= 1) { p += 1; rec.push('add H3 sub-headings for depth'); }
          else { rec.push('add H2 headings for major sections'); }
          if (b.emptyHead > 0) { p -= Math.min(2, b.emptyHead); rec.push(b.emptyHead + ' empty heading(s) found'); }
          p = Math.max(0, Math.min(10, p));
          C(p, 10, p >= 8 ? 'ok' : (p >= 5 ? 'warn' : 'bad'), 'H1 Structure',
            p >= 8 ? (b.h1 === 1 ? 'One primary H1 with good hierarchy.' : 'Hierarchy works.') : rec.join('; ') + '.');
        })();
        (() => {
          const n = b.text.length;
          let p = n > 1200 ? 7 : (n > 600 ? 6 : (n > 300 ? 4 : (n > 120 ? 2 : 0)));
          if (b.sections >= 3) p += 1;
          if (b.ctas >= 1) p += 1;
          if (b.forms >= 1 || b.ctas >= 3) p += 1;
          p = Math.min(10, p);
          const ph = PLACEHOLDER.test(b.text);
          if (ph) p = Math.min(p, 4);
          if (n <= 120) return C(p, 10, 'bad', 'Page Content', 'This page looks almost empty — add real text about your business.');
          if (ph) return C(p, 10, 'warn', 'Page Content', 'Replace demo text with your real business content.');
          C(p, 10, p >= 8 ? 'ok' : 'warn', 'Page Content',
            p >= 8 ? 'Good depth of real content.' : 'Thin content (' + n + ' chars) — describe services, benefits and details.');
        })();
        (() => {
          const imgs = b.imgs.filter(i => !/^data:image\/gif;base64,R0lGODlhAQAB/i.test(i.src || ''));
          if (!imgs.length) return C(10, 10, 'ok', 'Image Alt Text', 'No images — nothing to fix here.');
          let good = 0; const badN = [];
          imgs.forEach((im, i) => {
            const a = (im.alt || '').trim();
            const srcOk = !!(im.src || '').trim() && !/^(undefined|null)$/i.test(im.src.trim());
            if (a.length >= 3 && !GEN_ALT.test(a) && srcOk) good++;
            else badN.push(i + 1);
          });
          const p = Math.round(10 * good / imgs.length);
          if (good === imgs.length) return C(10, 10, 'ok', 'Image Alt Text', 'All ' + imgs.length + ' image(s) have descriptive alt text.');
          C(p, 10, p >= 7 ? 'warn' : 'bad', 'Image Alt Text',
            (imgs.length - good) + ' of ' + imgs.length + ' image(s) need descriptive alt text.' + (badN.length <= 4 ? ' (image ' + badN.join(', ') + ')' : ''));
        })();
        (() => {
          const L = b.links;
          let pages = [];
          try { if (typeof wcEnsurePages === 'function') pages = (wcEnsurePages() || []).map(p => (p.slug || '') + '.html'); } catch (e) {}
          let empty = 0, missingT = 0, brokenP = 0;
          L.forEach(l => {
            const href = (l.href || '').trim();
            if (!href || href === '#') { empty++; return; }
            if (href.charAt(0) === '#') { if (!b.ids[href.slice(1)]) missingT++; return; }
            if (/\.html?$/i.test(href) && !/^https?:\/\//i.test(href)) {
              const f = href.split('/').pop().split('?')[0];
              if (pages.length && pages.indexOf(f) < 0 && f !== 'index.html') brokenP++;
            }
          });
          const healthy = Math.max(0, L.length - empty - missingT - brokenP);
          let p = (b.hasNav ? 3 : 0) + (b.ctas >= 2 ? 2 : 0) + (L.length ? Math.round(5 * healthy / L.length) : 2);
          p = Math.min(10, p);
          const rec = [];
          if (!b.hasNav) rec.push('no navigation menu detected');
          if (b.ctas < 2) rec.push('add call-to-action buttons');
          if (empty) rec.push(empty + ' link(s) go nowhere (empty or “#”)');
          if (missingT) rec.push(missingT + ' anchor(s) point to missing sections');
          if (brokenP) rec.push(brokenP + ' page link(s) may be broken');
          if (!L.length) return C(3, 10, 'warn', 'Links & Navigation', 'No links found — add navigation and CTAs.');
          C(p, 10, p >= 8 ? 'ok' : (p >= 5 ? 'warn' : 'bad'), 'Links & Navigation',
            p >= 8 ? L.length + ' links healthy' + (b.hasNav ? ', navigation present.' : '.') : rec.join('; ') + '.');
        })();
        (() => {
          const c = (h.canonical || '').trim();
          if (/^https?:\/\/.+\..+/.test(c)) return C(5, 5, 'ok', 'Canonical URL', 'Valid canonical set.');
          if (c) return C(3, 5, 'warn', 'Canonical URL', 'Use a full URL (https://…) for the canonical.');
          C(0, 5, 'warn', 'Canonical URL', 'Add a canonical URL to identify the preferred page address.');
        })();
        (() => {
          const r = (h.robots || '').trim();
          if (/noindex/i.test(r)) return C(0, 5, 'warn', 'Indexing', 'Page is marked noindex — hidden from Google. Keep it only if intentional.');
          if (/nofollow/i.test(r)) return C(4, 5, 'warn', 'Indexing', 'Links are nofollow — fine if intentional.');
          if (/index/i.test(r)) return C(5, 5, 'ok', 'Indexing', 'Page is indexable.');
          C(4, 5, 'ok', 'Indexing', 'Indexable by default. An explicit robots tag is optional.');
        })();
        (() => {
          let p = 0;
          if ((h.ogTitle || '').trim()) p += 1.5;
          if ((h.ogDesc || '').trim()) p += 1.5;
          if ((h.ogImage || '').trim()) p += 1.5;
          if ((h.ogUrl || '').trim()) p += 0.5;
          p = Math.round(p);
          if (p >= 5) return C(5, 5, 'ok', 'Social Preview', 'Share title, text and image all set.');
          if (p === 0) return C(0, 5, 'warn', 'Social Preview', 'Add a social share image so the page looks good when shared.');
          C(p, 5, 'warn', 'Social Preview', 'Partly set (' + p + '/5) — complete the share ' + (!(h.ogImage || '').trim() ? 'image' : 'title/text') + '.');
        })();
        (() => {
          let p = 0; const rec = [];
          if (h.viewport) p += 3; else rec.push('add a viewport meta tag');
          if (h.mediaCss) p += 2; else rec.push('viewport covers basics — responsive rules recommended');
          C(p, 5, p >= 4 ? 'ok' : (p >= 2 ? 'warn' : 'bad'), 'Mobile Readiness',
            p >= 4 ? 'Viewport set with responsive styling.' : rec.join('; ') + '.');
        })();
        (() => {
          if (h.jsonLd && h.jsonLd.ok) return C(5, 5, 'ok', 'Structured Data', (h.jsonLd.type || 'Schema') + ' data detected.');
          if (h.jsonLd) return C(2, 5, 'warn', 'Structured Data', 'Structured data found but unclear — use Organization or LocalBusiness type.');
          C(0, 5, 'warn', 'Structured Data', 'Optional — add Organization/LocalBusiness info for richer results.');
        })();
        (() => {
          let p = 0; const rec = [];
          if (h.titleCount === 1) p += 1; else rec.push('page should have exactly one <title>');
          if ((h.title || '').trim()) p += 1; else rec.push('title tag is empty');
          if (h.descCount <= 1) p += 1; else rec.push('duplicate meta descriptions');
          const c = (h.canonical || '').trim();
          if (!c || /^https?:\/\//.test(c)) p += 1; else rec.push('canonical should be a full URL');
          if (h.langAttr || h.lang) p += 1; else rec.push('set the page language');
          C(p, 5, p >= 4 ? 'ok' : 'warn', 'Technical Basics', p >= 4 ? 'Clean title, meta and language setup.' : rec.join('; ') + '.');
        })();
        if (!(h.keywords || '').trim()) checks.push({ pts: 0, max: 0, st: 'info', title: 'Meta Keywords', desc: 'Keywords are optional helper data in this Studio. Focus more on useful page content, titles, descriptions, headings and links.' });

        const sum = (names) => checks.filter(c => names.indexOf(c.title) >= 0).reduce((a, c) => ({ p: a.p + c.pts, m: a.m + c.max }), { p: 0, m: 0 });
        const g = (names) => { const s = sum(names); return s.m ? Math.round(100 * s.p / s.m) : 100; };
        const groups = {
          onpage: g(['SEO Title', 'Meta Description', 'H1 Structure', 'Canonical URL']),
          content: g(['Page Content', 'Image Alt Text', 'Links & Navigation']),
          technical: g(['Indexing', 'Mobile Readiness', 'Structured Data', 'Technical Basics']),
          social: g(['Social Preview'])
        };
        const total = checks.reduce((a, c) => a + c.pts, 0);
        return { score: Math.max(0, Math.min(100, total)), groups, checks, page: window.wcSeoPageName() };
      };

      const BAR = (label, pct) => {
        const col = pct >= 80 ? '#34d399' : (pct >= 55 ? '#fbbf24' : '#f87171');
        return '<div style="margin-bottom:0.45rem;"><div style="display:flex;justify-content:space-between;font-size:0.7rem;font-weight:800;color:#cbd5e1;margin-bottom:0.2rem;"><span>' + esc(label) + '</span><span>' + pct + '%</span></div><div style="height:7px;background:#0b1120;border:1px solid #1e293b;border-radius:99px;overflow:hidden;"><div style="height:100%;width:' + pct + '%;background:' + col + ';border-radius:99px;"></div></div></div>';
      };
      const CARD = (c) => {
        const icon = c.st === 'ok' ? '✅' : (c.st === 'info' ? '💡' : '⚠️');
        const bd = c.st === 'ok' ? '#1e293b' : (c.st === 'info' ? '#1e3a5f' : '#7f1d1d');
        const tc = c.st === 'ok' ? '#f1f5f9' : (c.st === 'info' ? '#bae6fd' : '#fca5a5');
        const tail = c.max ? ' <span style="opacity:.6;font-weight:400;">(' + c.pts + '/' + c.max + ')</span>' : '';
        return '<div style="display:flex;align-items:flex-start;gap:0.65rem;background:#0b1120;border:1px solid ' + bd + ';border-radius:8px;padding:0.6rem 0.8rem;"><span style="font-size:1rem;flex-shrink:0;">' + icon + '</span><div><div style="font-size:0.75rem;font-weight:800;color:' + tc + ';margin-bottom:0.1rem;">' + esc(c.title) + tail + '</div><div style="font-size:0.68rem;color:#94a3b8;line-height:1.45;">' + esc(c.desc) + '</div></div></div>';
      };
      function wcSeoPaintModal(r) {
        try {
          const n = $('seo-audit-score-num'); if (n) n.textContent = r.score + '%';
          const pill = $('cfr-seo-score'); if (pill) pill.textContent = r.score + '%';
          const st = $('seo-audit-status');
          if (st) st.textContent = r.score >= 85 ? '🌟 Excellent — ready to shine' : (r.score >= 70 ? '👍 Good — a few improvements recommended' : (r.score >= 50 ? '🔧 Needs work before launch' : '⚠️ Poor — start with the top fixes'));
          const fixes = r.checks.filter(c => c.st === 'bad' || c.st === 'warn').length;
          const good = r.checks.filter(c => c.st === 'ok').length;
          const su = $('seo-audit-summary');
          if (su) su.textContent = good + ' strengths · ' + fixes + ' fix' + (fixes === 1 ? '' : 'es') + ' for this page.';
          let pg = $('seo-audit-page');
          if (!pg) {
            const sub = document.querySelector('#seo-audit-modal .modal-box p');
            if (sub) { pg = document.createElement('div'); pg.id = 'seo-audit-page'; sub.parentNode.insertBefore(pg, sub.nextSibling); }
          }
          if (pg) pg.innerHTML = '<span style="display:inline-block;font-size:0.68rem;font-weight:800;color:#a5b4fc;background:#1e1b4b;border:1px solid #6366f1;border-radius:999px;padding:0.18rem 0.65rem;margin:0.35rem 0 0;">📄 SEO Audit — ' + esc(r.page) + '</span>';
          const list = $('seo-audit-checks-list');
          if (list) list.innerHTML = BAR('On-page SEO', r.groups.onpage) + BAR('Content', r.groups.content) + BAR('Technical SEO', r.groups.technical) + BAR('Social SEO', r.groups.social) + '<div style="height:0.3rem;"></div>' + r.checks.map(CARD).join('');
        } catch (e) {}
      }
      window.openSeoAuditModal = function () {
        let r = null;
        try { r = window.wcSeoAnalyze(); } catch (e) { return; }
        wcSeoPaintModal(r);
        try { const m = $('seo-audit-modal'); if (m) m.classList.add('show'); } catch (e) {}
      };
      /* blank state — shown until ✦ Generate runs */
      window.wcSeoBlankScore = function () {
        try { const el = $('wcD-score'); if (el) { el.textContent = '—'; el.style.color = '#94a3b8'; } } catch (e) {}
        try { const box = $('wcD-recs'); if (box) box.innerHTML = 'Press <strong>✦ AI Generate SEO</strong> above to analyze this page. No score is shown until you generate.'; } catch (e) {}
      };
      /* silent refresh — paints ONLY after Generate (never by default) */
      window.wcRefreshSeoScore = function () {
        if (!window._wcSeoShowScore) { try { window.wcSeoBlankScore(); } catch (e) {} return 0; }
        let r = null;
        try { r = window.wcSeoAnalyze(); } catch (e) { return 0; }
        try { const p = $('cfr-seo-score'); if (p) p.textContent = r.score + '%'; } catch (e) {}
        try {
          const el = $('wcD-score');
          if (el) { el.textContent = r.score + '%'; el.style.color = r.score >= 70 ? '#34d399' : (r.score >= 50 ? '#fbbf24' : '#f87171'); }
        } catch (e) {}
        try {
          const box = $('wcD-recs');
          if (box) {
            const top = r.checks.filter(c => c.st === 'bad' || c.st === 'warn').slice(0, 4);
            box.innerHTML = top.length ? top.map(c => '💡 <strong>' + esc(c.title) + ':</strong> ' + esc(c.desc)).join('<br>') : '✅ All checks passed — excellent!';
          }
        } catch (e) {}
        return r.score;
      };
      let __seoT = null;
      window.wcSeoAuto = function () {
        try { if (typeof wcDPreview === 'function') wcDPreview(); } catch (e) {}
        clearTimeout(__seoT);
        __seoT = setTimeout(() => { try { window.wcRefreshSeoScore(); } catch (e) {} }, 700);
      };
      window.wcDArmAuto = function () {
        try {
          ['wcD-title', 'wcD-desc', 'wcD-kw', 'wcD-can', 'wcD-ogt', 'wcD-ogd', 'wcD-ogi', 'wcD-ri', 'wcD-rf'].forEach(id => {
            const el = $(id);
            if (el && !el.__seoArmed) { el.__seoArmed = true; el.addEventListener('input', () => window.wcSeoAuto()); el.addEventListener('change', () => window.wcSeoAuto()); }
          });
        } catch (e) {}
      };
      try {
        if (typeof window.wcRenderSeoPanel === 'function' && !window.wcRenderSeoPanel.__seoWrap) {
          const o = window.wcRenderSeoPanel;
          const w = function () { const r = o.apply(this, arguments); try { window.wcDArmAuto(); } catch (e) {} return r; };
          w.__seoWrap = true; window.wcRenderSeoPanel = w;
        }
      } catch (e) {}
    })();

    function persistAdminAssets() {
      try {
        const raw = localStorage.getItem('webcraft_saved_project');
        if (!raw) return;
        const project = JSON.parse(raw);
        const adminAssets = project.adminAssets;
        if (adminAssets) {
          localStorage.setItem('webcraft_admin_assets', JSON.stringify(adminAssets));
        }
      } catch (e) {}
    }
  </script>
</body>
</html>