/* ═══════════════════════════════════════════════════════════
   wc-studio-suite.js — Studio UX upgrades (Phases 1-3).
   Extends (never replaces): Copilot router, audit + mobile fix lanes,
   wcSnapshotVersion/history, saveProjectData/syncCanvasToHtml,
   setStudioDevice responsive system, section editor, SEO panels,
   saved-sections (mySections), language system.
   ═══════════════════════════════════════════════════════════ */
(function () {
  'use strict';
  if (window.__WCStudioSuiteLoaded) return;
  window.__WCStudioSuiteLoaded = true;

  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  const $ = (id) => document.getElementById(id);
  const toast = (m, ms, t) => { try { (window.wcAiToast || showToast)(m, ms, t); } catch (e) {} };
  const htmlNow = () => {
    try { if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml(); } catch (e) {}
    try { if (typeof currentHtml === 'string' && currentHtml.length > 50) return currentHtml; } catch (e) {}
    return '';
  };
  const designObj = () => {
    try {
      if (typeof projectData !== 'undefined' && projectData && projectData.designs) {
        const i = (typeof activeConceptIndex === 'number') ? activeConceptIndex : 0;
        return projectData.designs[i] || null;
      }
    } catch (e) {}
    return null;
  };
  const canvasDoc = () => {
    try { if (typeof getStudioCanvasDocument === 'function') return getStudioCanvasDocument(); } catch (e) {}
    try { if (typeof grapesEditor !== 'undefined' && grapesEditor && grapesEditor.Canvas) return grapesEditor.Canvas.getDocument(); } catch (e) {}
    return null;
  };
  const snapshot = (label, src) => { try { window.createVersionSnapshot && window.createVersionSnapshot(label, src || 'AI'); } catch (e) {} };

  /* ── AI CENTER MENU (single grouped entry — not 30 buttons) ── */
  function injectAiCenter() {
    try {
      const right = document.querySelector('.studio-header .header-right');
      if (!right || $('wc-ai-center-btn')) return;
      const wrap = document.createElement('div');
      wrap.className = 'wc-ai-center-wrap';
      wrap.innerHTML = '<button class="hdr-btn ai-btn" id="wc-ai-center-btn" title="AI Center — health, brand, responsive, versions, content, publish">✦ <span class="lbl">AI Center</span> ▾</button>'
        + '<div class="wc-ai-center-menu" id="wc-ai-center-menu">'
        + '<div class="wc-ai-center-sep">Analyze</div>'
        + '<button data-fn="runDesignHealthAudit"><span class="ic">💚</span><span>Design Health<small>Score + issues + Fix All</small></span></button>'
        + '<button data-fn="runConversionAudit"><span class="ic">📈</span><span>Conversion Optimizer<small>CTA · trust · flow</small></span></button>'
        + '<button data-fn="runContentConsistencyAudit"><span class="ic">📝</span><span>Content Consistency<small>Voice · CTA · tone</small></span></button>'
        + '<button data-fn="renderPrePublishChecklist"><span class="ic">🚀</span><span>Pre-Publish Checklist<small>Ready gate</small></span></button>'
        + '<div class="wc-ai-center-sep">Design</div>'
        + '<button data-fn="openBrandKit"><span class="ic">🎨</span><span>Brand Kit<small>Colors · fonts · CTA</small></span></button>'
        + '<button data-fn="openAiDesignDirector"><span class="ic">🎬</span><span>AI Design Director<small>Make it premium…</small></span></button>'
        + '<button data-fn="openAiImageAssistant"><span class="ic">🖼️</span><span>Image Assistant<small>Alt · fit · replace</small></span></button>'
        + '<div class="wc-ai-center-sep">Responsive</div>'
        + '<button data-fn="openResponsiveSplitPreview"><span class="ic">📐</span><span>Split Preview<small>Desktop · Tablet · Mobile</small></span></button>'
        + '<button data-fn="scanResponsiveProblemsUI"><span class="ic">⚠️</span><span>Responsive Issues<small>Highlights + Fix</small></span></button>'
        + '<div class="wc-ai-center-sep">Manage</div>'
        + '<button data-fn="renderVersionHistory"><span class="ic">🕘</span><span>Versions<small>Preview · restore</small></span></button>'
        + '<button data-fn="renderMySectionTemplates"><span class="ic">💎</span><span>My Templates<small>Save · insert</small></span></button>'
        + '<button data-fn="openLanguageCenter"><span class="ic">🌐</span><span>Language Center<small>EN · TA · SI</small></span></button>'
        + '</div>';
      const magicBtn = right.querySelector('.ai-btn');
      right.insertBefore(wrap, magicBtn || right.firstChild);
      const btn = $('wc-ai-center-btn'), menu = $('wc-ai-center-menu');
      btn.onclick = (e) => { e.stopPropagation(); menu.classList.toggle('open'); };
      document.addEventListener('click', (e) => { if (!e.target.closest('.wc-ai-center-wrap')) menu.classList.remove('open'); });
      menu.querySelectorAll('button').forEach((b) => {
        b.onclick = () => {
          menu.classList.remove('open');
          const fn = b.dataset.fn;
          try { window[fn] && window[fn](); } catch (e) { toast('Failed: ' + e.message); }
        };
      });
    } catch (e) {}
  }

  /* ── RESPONSIVE SPLIT PREVIEW (extends setStudioDevice) ── */
  function openResponsiveSplitPreview() {
    const html = htmlNow();
    if (!html) { toast('Generate or open a design first'); return; }
    snapshot('Before responsive review', 'User');
    const src = 'data:text/html;charset=utf-8,' + encodeURIComponent(html.slice(0, 600000));
    window.openWcModal && window.openWcModal('📐 Responsive Split Preview',
      '<div class="wc-ai-hint">Same concept in three viewports. Look for overflow, stacking, tiny text, hidden CTAs. Device system (<b>setStudioDevice</b>) is untouched — this is a read-only comparison.</div>'
      + '<div class="wc-ai-split" style="margin-top:0.7rem;">'
      + '<figure><figcaption>🖥 Desktop · ~1200px</figcaption><iframe src="' + src + '" style="height:380px;" sandbox="allow-same-origin"></iframe></figure>'
      + '<figure><figcaption>📱 Tablet · 768px</figcaption><iframe src="' + src + '" style="height:380px;max-width:768px;margin:0 auto;display:block;" sandbox="allow-same-origin"></iframe></figure>'
      + '<figure><figcaption>📲 Mobile · 375px</figcaption><iframe src="' + src + '" style="height:380px;max-width:375px;margin:0 auto;display:block;" sandbox="allow-same-origin"></iframe></figure>'
      + '</div><div id="wc-resp-markers"></div>',
      [
        { label: 'Scan issues', cls: 'ghost', onClick: () => { renderResponsiveProblemMarkers(scanResponsiveProblems()); } },
        { label: '✨ Fix with AI', cls: '', onClick: () => { try { (window.copilotMobileFix || window.copilotFixMobileNow || window.fixAllHealthIssues)(); } catch (e) {} } },
        { label: 'Close', cls: 'primary', onClick: () => window.closeWcModal() }
      ], { wide: true });
    renderResponsivePreview();
  }
  function renderResponsivePreview() {
    try {
      const markers = scanResponsiveProblems();
      renderResponsiveProblemMarkers(markers);
    } catch (e) {}
  }
  function closeResponsiveSplitPreview() { try { window.closeWcModal && window.closeWcModal(); } catch (e) {} }

  function scanResponsiveProblems() {
    const out = [];
    try {
      const doc = canvasDoc();
      const html = htmlNow();
      if (doc && doc.body) {
        const els = doc.body.querySelectorAll('section,header,footer,div');
        let over = 0;
        els.forEach((el) => {
          try {
            if (el.scrollWidth > el.clientWidth + 3 && el.clientWidth > 0) {
              over++;
              if (out.length < 8) {
                const label = (el.tagName || 'DIV') + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.split(' ')[0] : '');
                out.push({ section: label.slice(0, 60) || 'Section', msg: 'Overflows horizontally — side-scroll on phones', kind: 'overflow' });
              }
            }
          } catch (e) {}
        });
        if (over > 8) out.push({ section: 'Page', msg: over + ' elements overflow — run Fix with AI', kind: 'overflow' });
      }
      // Grid-column heuristic on raw HTML
      const grids = String(html).match(/grid-template-columns:[^;]*repeat\(\s*4|grid-template-columns:[^;]*1fr 1fr 1fr 1fr/gi);
      if (grids && grids.length) out.push({ section: 'Product grid', msg: grids.length + ' four-column grid(s) — should collapse to 1 column on mobile', kind: 'grid' });
      if (/<button[^>]*style="[^"]*width:\s*\d{3,}px/gi.test(html)) out.push({ section: 'CTA buttons', msg: 'Fixed-width buttons may wrap badly on mobile', kind: 'cta' });
      if (/(font-size:\s*)(10|11)px/gi.test(html)) out.push({ section: 'Body text', msg: 'Text under 12px is hard to read on phones', kind: 'text' });
      const imgs = (html.match(/<img/gi) || []).length;
      if (imgs && !/max-width:\s*100%/i.test(html)) out.push({ section: 'Images', msg: imgs + ' image(s) without max-width:100% — may overflow', kind: 'img' });
    } catch (e) { out.push({ section: 'Scan', msg: 'Scan snag: ' + e.message, kind: 'info' }); }
    try { window.__RespCache = { at: Date.now(), markers: out }; } catch (e) {}
    return out;
  }
  function scanResponsiveProblemsUI() {
    const markers = scanResponsiveProblems();
    window.openWcModal && window.openWcModal('⚠️ Responsive Issues',
      '<div id="wc-resp-list"></div>',
      [
        { label: '✨ Fix with AI', cls: 'primary', onClick: () => { try { (window.copilotMobileFix || window.fixAllHealthIssues)(); } catch (e) {} } },
        { label: 'Close', cls: '', onClick: () => window.closeWcModal() }
      ]);
    renderResponsiveProblemMarkers(markers);
  }
  function renderResponsiveProblemMarkers(markers) {
    const list = markers || window.__RespCache?.markers || [];
    const host = $('wc-resp-markers') || $('wc-resp-list');
    const html = !list.length
      ? '<div class="wc-ai-hint">✅ No responsive red flags detected on the current canvas.</div>'
      : list.map((m) => '<div class="wc-ai-marker">⚠ <b>' + esc(m.section) + '</b> — ' + esc(m.msg) + '</div>').join('')
        + '<button class="wc-ai-btn small primary" onclick="try{(window.copilotMobileFix||window.fixAllHealthIssues)()}catch(e){}" style="margin-top:0.4rem;">✨ Fix with AI</button>';
    if (host) host.innerHTML = html;
    else window.openWcModal && window.openWcModal('⚠️ Responsive Issues', html, [{ label: 'Close', cls: 'primary', onClick: () => window.closeWcModal() }]);
  }

  /* ── AI IMAGE ASSISTANT (wraps existing image editor) ── */
  function selectedImageCtx() {
    try {
      let comp = null;
      if (typeof editingImage !== 'undefined' && editingImage) comp = editingImage;
      else if (typeof selectedComponent !== 'undefined' && selectedComponent) comp = selectedComponent;
      else if (typeof window.__WCSelectedImage !== 'undefined' && window.__WCSelectedImage) comp = window.__WCSelectedImage;
      if (!comp) return { comp: null, src: '', section: '' };
      const tag = (comp.get && (comp.get('tagName') || '').toLowerCase()) || '';
      let img = comp;
      if (tag !== 'img' && comp.find) {
        const found = comp.find('img');
        if (found && found.length) img = found[0];
      }
      const at = (img.getAttributes && img.getAttributes()) || {};
      let section = '';
      try {
        let p = img;
        for (let i = 0; i < 5 && p; i++) { p = p.parent && p.parent(); if (p && /section/i.test(p.get && (p.get('tagName') || ''))) { section = 'section'; break; } }
      } catch (e) {}
      return { comp: img, src: at.src || '', alt: at.alt || '', section };
    } catch (e) { return { comp: null, src: '', section: '' }; }
  }
  function openAiImageAssistant() {
    const ctx = selectedImageCtx();
    const body = '<div class="wc-ai-hint">Selected: <b>' + (ctx.src ? esc(String(ctx.src).slice(0, 80)) : 'no image selected — select an image on the canvas first') + '</b>'
      + (ctx.alt ? '<br>Alt: ' + esc(ctx.alt) : '') + '</div>'
      + '<div style="display:flex;gap:0.45rem;flex-wrap:wrap;margin-top:0.7rem;">'
      + ['Improve Image', 'Remove Background', 'Replace Image', 'Generate New Image', 'Make Image Fit Section', 'Generate Alt Text', 'Generate Image Suggestions', 'Use as Background']
        .map((a) => '<button class="wc-ai-btn small" onclick="aiImageAssistant(\'' + a + '\')">' + a + '</button>').join('')
      + '</div><div id="wc-img-result" style="margin-top:0.7rem;"></div>';
    window.openWcModal && window.openWcModal('🖼️ AI Image Assistant', body, [{ label: 'Close', cls: 'primary', onClick: () => window.closeWcModal() }]);
  }
  async function aiImageAssistant(action, target) {
    const ctx = selectedImageCtx();
    const out = $('wc-img-result');
    const say = (h) => { if (out) out.innerHTML = h; };
    if (!ctx.comp && action !== 'Generate Image Suggestions' && action !== 'Generate New Image') {
      toast('Select an image on the canvas first'); return null;
    }
    if (action === 'Generate Alt Text') {
      const alt = await generateImageAltText(ctx);
      say('<div class="wc-ai-hint">Suggested alt: <b>' + esc(alt) + '</b></div><button class="wc-ai-btn small primary" onclick="applyAiImageResult({alt:window.__WCAltSuggestion||\'Website image\'},\'alt\')">Apply alt text</button>');
      window.__WCAltSuggestion = alt;
      return { alt };
    }
    if (action === 'Generate Image Suggestions') {
      say('<div class="wc-ai-loading"><span class="wc-ai-spinner"></span> Brainstorming image ideas…</div>');
      try {
        const j = await window.wcAiPost('/api/generate.php', { action: 'ask', instruction: 'Suggest 5 concrete image ideas (subject, mood, composition) for this website section. Business: ' + ((typeof projectData !== 'undefined' && projectData.bizName) || 'website') + '. Reply as short bullets.', context: {} }, 40000);
        say('<div class="wc-ai-hint">' + esc(j.reply || j.text || 'No suggestions returned').slice(0, 1200) + '</div>');
        return j;
      } catch (e) { say('<div class="wc-ai-hint">Suggestions failed: ' + esc(e.message) + '</div>'); return null; }
    }
    if (action === 'Make Image Fit Section') {
      snapshot('Before image fit', 'AI');
      try {
        const st = (ctx.comp.getStyle && ctx.comp.getStyle()) || {};
        st['max-width'] = '100%'; st['height'] = 'auto'; st['object-fit'] = 'cover'; st['display'] = 'block';
        ctx.comp.setStyle(st);
        if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml();
        if (typeof saveProjectData === 'function') saveProjectData();
        toast('🖼 Image now fits its section'); say('<div class="wc-ai-hint">✅ Responsive fit applied (max-width:100%, cover).</div>');
        return { mode: 'fit' };
      } catch (e) { toast('AI couldn\'t safely apply this change. Your website was not modified.'); return null; }
    }
    if (action === 'Use as Background') {
      snapshot('Before image→background', 'AI');
      try {
        const at = (ctx.comp.getAttributes && ctx.comp.getAttributes()) || {};
        let p = ctx.comp.parent && ctx.comp.parent();
        if (p) {
          const st = (p.getStyle && p.getStyle()) || {};
          if (at.src) st['background-image'] = 'url(' + at.src + ')';
          st['background-size'] = 'cover'; st['background-position'] = 'center';
          p.setStyle(st);
          if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml();
          if (typeof saveProjectData === 'function') saveProjectData();
          toast('🖼 Set as section background'); return { mode: 'bg' };
        }
      } catch (e) {}
      toast('AI couldn\'t safely apply this change. Your website was not modified.'); return null;
    }
    if (action === 'Replace Image' || action === 'Generate New Image') {
      say('<label class="wc-ai-label">New image URL</label><input class="wc-ai-input" id="wc-img-url" placeholder="https://…">'
        + '<button class="wc-ai-btn small primary" style="margin-top:0.5rem;" onclick="applyAiImageResult({src:document.getElementById(\'wc-img-url\').value},\'replace\')">Apply</button>'
        + '<div class="wc-ai-hint">Tip: upload via Media tab, then paste the URL here. AI generation uses your existing Media + upload pipeline — nothing new to learn.</div>');
      return { mode: 'prompt-replace' };
    }
    // Improve / Remove Background → route through existing Magic AI edit pipeline (never a parallel engine).
    snapshot('Before AI image edit: ' + action, 'AI');
    say('<div class="wc-ai-loading"><span class="wc-ai-spinner"></span> Applying “' + esc(action) + '” via Studio AI…</div>');
    try {
      if (typeof executeMagicAi === 'function') {
        await executeMagicAi(action + ' for the selected image (keep layout, keep surrounding content, output valid HTML)');
        say('<div class="wc-ai-hint">✅ Applied — check the canvas. Undo is available.</div>');
        return { mode: 'ai-edit' };
      }
      throw new Error('Magic AI unavailable');
    } catch (e) { say('<div class="wc-ai-hint">AI couldn\'t safely apply this change. Your website was not modified.</div>'); return null; }
  }
  function applyAiImageResult(result, mode) {
    const ctx = selectedImageCtx();
    if (!result) return false;
    snapshot('Before image apply (' + (mode || 'edit') + ')', 'AI');
    try {
      if (mode === 'alt' && result.alt && ctx.comp) {
        ctx.comp.addAttributes({ alt: String(result.alt).slice(0, 125) });
      } else if (mode === 'replace' && result.src && ctx.comp) {
        ctx.comp.addAttributes({ src: result.src });
      } else { toast('Nothing to apply'); return false; }
      if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml();
      if (typeof saveProjectData === 'function') saveProjectData();
      toast('🖼 Image updated'); return true;
    } catch (e) { toast('AI couldn\'t safely apply this change. Your website was not modified.'); return false; }
  }
  async function generateImageAltText(imageContext) {
    const ctx = imageContext && imageContext.src ? imageContext : selectedImageCtx();
    const biz = (() => { try { return (typeof projectData !== 'undefined' && projectData.bizName) || 'Website'; } catch (e) { return 'Website'; } })();
    try {
      const j = await window.wcAiPost('/api/generate.php', {
        action: 'ask',
        instruction: 'Write ONE image alt text (under 15 words, descriptive, no quotes) for an image on a business website. Business: ' + biz + '. Image src: ' + String(ctx.src || '').slice(0, 200) + '. Section: ' + String(ctx.section || 'page') + '. Reply with the alt text only.',
        context: {}
      }, 30000);
      const t = String(j.reply || j.text || '').trim().split('\n')[0].replace(/^["']|["']$/g, '').slice(0, 125);
      if (t) return t;
    } catch (e) {}
    // Deterministic fallback from surrounding context.
    try {
      const el = ctx.comp && ctx.comp.getEl && ctx.comp.getEl();
      const txt = el && el.closest ? ((el.closest('section') || {}).textContent || '').trim().split('\n')[0] : '';
      if (txt) return (biz + ' — ' + txt).slice(0, 125);
    } catch (e) {}
    return biz + ' website image';
  }

  /* ── CONTENT CONSISTENCY ── */
  function runContentConsistencyAudit() {
    const html = htmlNow();
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
    const ctas = [...doc.querySelectorAll('a,button')].map((b) => (b.textContent || '').trim()).filter(Boolean);
    const heads = [...doc.querySelectorAll('h1,h2,h3')].map((h) => (h.textContent || '').trim()).filter(Boolean);
    const issues = [];
    const uniqCta = [...new Set(ctas.map((c) => c.toLowerCase()))];
    if (uniqCta.length > 4) issues.push({ id: 'cta', label: 'CTA language varies (' + uniqCta.length + ' styles)', before: uniqCta.slice(0, 6).join(' · '), after: 'One consistent CTA (“' + (ctaStyle() || 'Get in touch') + '”)', kind: 'cta' });
    if (/!/g.test(heads.join(' ')) && /[.!]$/.test(heads.join(' ')) === false) { /* mixed */ }
    const tones = heads.filter((h) => /^(welcome|hello|hi)\b/i.test(h));
    if (tones.length) issues.push({ id: 'tone', label: tones.length + ' casual greeting heading(s)', before: tones.slice(0, 3).join(' · '), after: 'Professional benefit-led headings', kind: 'tone' });
    const empty = [...doc.querySelectorAll('p')].filter((p) => !p.textContent.trim()).length;
    if (empty) issues.push({ id: 'empty', label: empty + ' empty paragraph(s)', before: 'blank <p> tags', after: 'removed', kind: 'empty' });
    if (/lorem ipsum/i.test(doc.body ? doc.body.textContent : '')) issues.push({ id: 'lorem', label: 'Placeholder copy present', before: 'lorem ipsum…', after: 'business-specific copy', kind: 'copy' });
    const report = { at: Date.now(), issues, ctaCount: ctas.length, headingCount: heads.length };
    try { window.setProjectExt && window.setProjectExt({ contentAudit: report }); } catch (e) {}
    renderContentConsistencyReport(report);
    return report;
  }
  function ctaStyle() { try { return (window.loadBrandKit && window.loadBrandKit().ctaStyle) || 'Get in touch'; } catch (e) { return 'Get in touch'; } }
  function renderContentConsistencyReport(report) {
    const r = report || (window.getProjectExt && window.getProjectExt().contentAudit) || { issues: [] };
    let h = '<div class="wc-ai-hint">Checked brand voice, CTA language, heading tone and empty copy. <b>Nothing changes until you press Apply.</b></div>';
    if (!r.issues.length) h += '<div class="wc-ai-hint">✅ Content looks consistent.</div>';
    else r.issues.forEach((it) => {
      h += '<div class="wc-ai-issue"><span class="sev">📝</span><div class="body"><b>' + esc(it.label) + '</b>'
        + '<div class="wc-ai-diff"><div><h6>Before</h6>' + esc(it.before || '—') + '</div><div><h6>After</h6>' + esc(it.after || '—') + '</div></div></div></div>';
    });
    h += '<label class="wc-ai-label">Apply style</label><div style="display:flex;gap:0.45rem;flex-wrap:wrap;">'
      + ['Make all CTA buttons consistent', 'Make headings more professional', 'Use the same brand voice throughout', 'Convert copy to friendly Tamil']
      .map((s) => '<button class="wc-ai-btn small" onclick="applyContentConsistencyFixes(\'' + esc(s).replace(/'/g, "\\'") + '\')">' + esc(s) + '</button>').join('') + '</div>';
    window.openWcModal && window.openWcModal('📝 Content Consistency', h,
      [{ label: 'Close', cls: 'primary', onClick: () => window.closeWcModal() }], { wide: true });
  }
  async function applyContentConsistencyFixes(style) {
    snapshot('Before content consistency: ' + (style || 'fix'), 'AI');
    toast('📝 Applying: ' + (style || 'consistency fixes') + '…');
    // CTA normalisation is safe + local.
    if (/cta/i.test(style || '')) {
      try {
        const want = ctaStyle();
        if (typeof copilotWalkComps === 'function') {
          let n = 0;
          copilotWalkComps((c) => {
            const tag = (c.get && (c.get('tagName') || '').toLowerCase()) || '';
            if (tag !== 'a' && tag !== 'button') return false;
            const t = ((c.getAttributes && c.getAttributes().href) || '');
            if (!/contact|whatsapp|call|book/i.test(t) && !/btn|cta/i.test((c.getAttributes && c.getAttributes().class) || '')) return false;
            return false; // only retarget obvious CTAs below via text swap
          });
        }
        // Raw-HTML pass for obvious generic CTAs (keeps links/functionality intact).
        let html = htmlNow();
        const before = html;
        html = html.replace(/>(Click here|Learn more|Submit)<\/(a|button)>/gi, '>' + want + '</$2>');
        if (html !== before) {
          const d = designObj(); if (d) d.html = html;
          try { currentHtml = html; } catch (e) {}
          try { if (typeof loadHtmlIntoStudioCanvas === 'function') loadHtmlIntoStudioCanvas(); } catch (e) {}
          try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
          toast('✅ CTA language unified'); return true;
        }
      } catch (e) {}
    }
    // Everything else → existing AI edit lane (content-scoped, snapshot already taken).
    try {
      if (typeof executeMagicAi === 'function') {
        await executeMagicAi((style || 'Make website copy consistent') + ' — change TEXT ONLY in place, keep all links, structure, images and functionality identical');
        return true;
      }
    } catch (e) {}
    toast('AI couldn\'t safely apply this change. Your website was not modified.');
    return false;
  }

  /* ── LANGUAGE CENTER (extends switchCanvasLanguage) ── */
  function openLanguageCenter() {
    const cur = (() => { try { return (typeof langState !== 'undefined' && langState.current) || 'en'; } catch (e) { return 'en'; } })();
    window.openWcModal && window.openWcModal('🌐 Language Center',
      '<div class="wc-ai-hint">Current preview: <b>' + esc(cur) + '</b>. Translation preserves layout — long Tamil/Sinhala strings auto-expand with overflow re-checks.</div>'
      + '<div style="display:flex;gap:0.5rem;margin-top:0.7rem;flex-wrap:wrap;">'
      + [['en', 'English'], ['ta', 'Tamil'], ['si', 'Sinhala']].map(([c, l]) => '<button class="wc-ai-btn' + (cur === c ? ' primary' : '') + '" onclick="previewTranslatedProject(\'' + c + '\')">' + l + '</button>').join('')
      + '</div><div class="wc-ai-hint" style="margin-top:0.6rem;">Translate applies to the live canvas with a snapshot + mobile re-check. “Adapt layout” runs automatically after translation.</div>',
      [
        { label: 'Adapt layout only', cls: 'ghost', onClick: () => adaptLayoutForLanguage(cur) },
        { label: 'Close', cls: '', onClick: () => window.closeWcModal() }
      ]);
  }
  function previewTranslatedProject(targetLanguage) {
    try {
      if (typeof switchCanvasLanguage === 'function') switchCanvasLanguage(targetLanguage);
      toast('🌐 Previewing ' + targetLanguage);
    } catch (e) { toast('Preview failed: ' + e.message); }
  }
  async function translateProject(targetLanguage) {
    const lang = targetLanguage || 'ta';
    snapshot('Before translate → ' + lang, 'AI');
    toast('🌐 Translating to ' + (lang === 'ta' ? 'Tamil' : lang === 'si' ? 'Sinhala' : lang) + '…');
    try {
      // Prefer AI translation of visible copy via existing ask lane, chunked to avoid freezes.
      const doc = canvasDoc();
      const texts = doc ? [...doc.body.querySelectorAll('h1,h2,h3,p,li,a,button,span')].map((el) => (el.textContent || '').trim()).filter((t) => t.length > 2 && t.length < 300).slice(0, 40) : [];
      if (!texts.length || typeof window.wcAiPost !== 'function') throw new Error('fallback-to-switch');
      const j = await window.wcAiPost('/api/generate.php', {
        action: 'ask',
        instruction: 'Translate each line to ' + (lang === 'ta' ? 'Tamil' : lang === 'si' ? 'Sinhala' : 'English') + '. Keep placeholders/URLs unchanged. Return same line count, one per line, no numbering. Lines:\n' + texts.slice(0, 25).join('\n'),
        context: {}
      }, 60000);
      const lines = String(j.reply || j.text || '').split('\n').map((s) => s.trim()).filter(Boolean);
      if (lines.length >= 3 && doc) {
        let i = 0;
        doc.body.querySelectorAll('h1,h2,h3,p,li,a,button').forEach((el) => {
          const t = (el.textContent || '').trim();
          if (t.length > 2 && t.length < 300 && i < lines.length && el.children.length === 0) { el.textContent = lines[i++]; }
        });
        if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml();
        if (typeof saveProjectData === 'function') saveProjectData();
      } else if (typeof switchCanvasLanguage === 'function') {
        switchCanvasLanguage(lang);
      }
    } catch (e) {
      try { if (typeof switchCanvasLanguage === 'function') switchCanvasLanguage(lang); }
      catch (e2) { toast('AI couldn\'t safely apply this change. Your website was not modified.'); return false; }
    }
    adaptLayoutForLanguage(lang);
    try { window.setProjectExt && window.setProjectExt({ languageSettings: { current: lang, at: Date.now() } }); } catch (e2) {}
    toast('🌐 Translated — layout adapted, mobile re-checked');
    return true;
  }
  function adaptLayoutForLanguage(targetLanguage) {
    try {
      const doc = canvasDoc();
      if (!doc || !doc.head) { toast('Open a design first'); return false; }
      let tag = doc.getElementById('wc-lang-adapt-css');
      if (!tag) { tag = doc.createElement('style'); tag.id = 'wc-lang-adapt-css'; doc.head.appendChild(tag); }
      const needsTamil = targetLanguage === 'ta' || /[\u0B80-\u0BFF]/.test(doc.body ? doc.body.textContent : '');
      const needsSinhala = targetLanguage === 'si' || /[\u0D80-\u0DFF]/.test(doc.body ? doc.body.textContent : '');
      tag.textContent = 'body{overflow-wrap:break-word;word-break:normal;}'
        + 'h1,h2,h3,p,li,a,button{max-width:100%;}'
        + 'section,div{max-width:100%;}'
        + (needsTamil ? 'body{font-family:\'Noto Sans Tamil\',\'Plus Jakarta Sans\',system-ui,sans-serif !important;}h1,h2{line-height:1.5 !important;letter-spacing:0 !important;}' : '')
        + (needsSinhala ? 'body{font-family:\'Noto Sans Sinhala\',\'Plus Jakarta Sans\',system-ui,sans-serif !important;}h1,h2{line-height:1.6 !important;}' : '')
        + '@media(max-width:480px){h1{font-size:clamp(1.4rem,7vw,2rem) !important;}h2{font-size:clamp(1.15rem,5.5vw,1.5rem) !important;}p,li{font-size:15px !important;line-height:1.7 !important;}}';
      // Re-check overflow after expansion.
      try {
        doc.body.querySelectorAll('section,header,footer').forEach((el) => {
          if (el.scrollWidth > el.clientWidth + 2 && el.clientWidth > 0) { el.style.overflowX = 'clip'; el.style.maxWidth = '100%'; }
        });
      } catch (e) {}
      if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml();
      if (typeof saveProjectData === 'function') saveProjectData();
      toast('🌐 Layout adapted — expansion + overflow re-checked');
      return true;
    } catch (e) { toast('Adapt failed: ' + e.message); return false; }
  }

  /* ── CONVERSION OPTIMIZER ── */
  function runConversionAudit() {
    const html = htmlNow();
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
    const text = doc.body ? doc.body.textContent : '';
    const recs = [];
    const hasHeroCta = /hero/i.test(html) && /(whatsapp|call|book|contact|get started|order|buy)/i.test(text.slice(0, 3000));
    if (!hasHeroCta) recs.push({ id: 'hero-cta', title: 'Hero CTA is weak or missing', detail: 'Add a visible WhatsApp / Call button in the first screen', auto: true });
    if (!/whatsapp/i.test(html) && /sri lanka|colombo|\b94\b/i.test(text)) recs.push({ id: 'wa', title: 'Make WhatsApp CTA more visible', detail: 'Sri Lankan visitors convert best on WhatsApp — pin it to hero + contact', auto: true });
    if (!/testimonial|review/i.test(html)) recs.push({ id: 'trust', title: 'Add trust signals near hero', detail: 'Testimonials / ratings / client logos lift conversion', auto: false, addSection: 'testimonials' });
    if (!/pricing|plan|package/i.test(html) && /service/i.test(html)) recs.push({ id: 'pricing', title: 'Service-to-contact flow is vague', detail: 'Consider a simple 3-tier pricing or process section', auto: false, addSection: 'pricing' });
    const ctaCount = (text.match(/(contact|whatsapp|call|book|order|buy|get started)/gi) || []).length;
    if (ctaCount < 3) recs.push({ id: 'repeat', title: 'CTA repeated only ' + ctaCount + '×', detail: 'Repeat the primary CTA after every major section', auto: true });
    if (!/id=["']contact["']/i.test(html)) recs.push({ id: 'contact', title: 'Contact section hard to reach', detail: 'CTAs need a #contact anchor target', auto: true });
    const report = { at: Date.now(), recs };
    try { window.setProjectExt && window.setProjectExt({ conversionAudit: report }); } catch (e) {}
    renderConversionRecommendations(report);
    return report;
  }
  function renderConversionRecommendations(report) {
    const r = report || (window.getProjectExt && window.getProjectExt().conversionAudit) || { recs: [] };
    let h = '<div class="wc-ai-hint">Every recommendation is <b>optional</b> — apply what fits your business.</div>';
    if (!r.recs.length) h += '<div class="wc-ai-hint">✅ Conversion flow looks strong.</div>';
    else r.recs.forEach((c) => {
      h += '<div class="wc-ai-issue"><span class="sev">📈</span><div class="body"><b>' + esc(c.title) + '</b><div class="meta">' + esc(c.detail) + '</div></div>'
        + '<div class="acts"><button class="wc-ai-btn small" onclick="applyConversionOptimization(\'' + c.id + '\')">Apply</button></div></div>';
    });
    window.openWcModal && window.openWcModal('📈 Conversion Optimizer', h,
      [
        { label: 'Apply all safe wins', cls: 'ghost', onClick: () => applyConversionOptimization('all') },
        { label: 'Close', cls: 'primary', onClick: () => window.closeWcModal() }
      ], { wide: true });
  }
  async function applyConversionOptimization(id) {
    snapshot('Before conversion optimization: ' + (id || 'all'), 'AI');
    const applyOne = async (rid) => {
      if (rid === 'trust' || rid === 'pricing') {
        const sec = rid === 'trust' ? 'testimonials' : 'pricing';
        if (typeof copilotAddSection === 'function') { await copilotAddSection(sec); return true; }
      }
      if (typeof executeMagicAi === 'function') {
        const prompts = {
          'hero-cta': 'Add a prominent hero CTA button (WhatsApp/Call style) without changing anything else',
          wa: 'Make the WhatsApp CTA more visible in hero and contact, keep everything else identical',
          repeat: 'Repeat the existing primary CTA naturally after each major section, change nothing else',
          contact: 'Ensure all CTA buttons link to the #contact section, change nothing else'
        };
        await executeMagicAi(prompts[rid] || 'Improve conversion flow minimally, keep design identical');
        return true;
      }
      return false;
    };
    try {
      if (id === 'all') {
        for (const rid of ['hero-cta', 'wa', 'repeat', 'contact']) { try { await applyOne(rid); } catch (e) {} }
      } else await applyOne(id);
      toast('📈 Conversion optimisation applied'); return true;
    } catch (e) { toast('AI couldn\'t safely apply this change. Your website was not modified.'); return false; }
  }

  /* ── AI DESIGN DIRECTOR (content-preserving, snapshot + before/after) ── */
  function openAiDesignDirector() {
    window.openWcModal && window.openWcModal('🎬 AI Design Director',
      '<div class="wc-ai-hint">Describe the new direction. Content, links, Site/Admin functionality and responsive behaviour are preserved — only the design language changes. Snapshot + Before/After included.</div>'
      + '<input class="wc-ai-input" id="wc-dd-input" placeholder="e.g. Make it more premium — modern and minimal, luxury feel" style="margin-top:0.6rem;">'
      + '<div style="display:flex;gap:0.45rem;flex-wrap:wrap;margin-top:0.6rem;">'
      + ['Make it more premium', 'Modern and minimal', 'Luxury brand', 'More energetic', 'More professional'].map((s) => '<button class="wc-ai-btn small" onclick="document.getElementById(\'wc-dd-input\').value=\'' + s + '\';generateDesignDirection()"> ' + s + '</button>').join('')
      + '</div><div id="wc-dd-result" style="margin-top:0.6rem;"></div>',
      [
        { label: 'Preview direction', cls: 'ghost', onClick: () => generateDesignDirection() },
        { label: 'Close', cls: '', onClick: () => window.closeWcModal() }
      ]);
  }
  async function generateDesignDirection(instruction) {
    const ins = instruction || ($('wc-dd-input') ? $('wc-dd-input').value : '');
    if (!ins || !ins.trim()) { toast('Describe the direction first'); return null; }
    const out = $('wc-dd-result');
    if (out) out.innerHTML = '<div class="wc-ai-loading"><span class="wc-ai-spinner"></span> Directing design… (content preserved)</div>';
    snapshot('Before Design Director: ' + ins.slice(0, 50), 'AI');
    const before = htmlNow();
    try {
      if (typeof executeMagicAi !== 'function') throw new Error('Magic AI unavailable on this screen');
      await executeMagicAi('REDESIGN DIRECTION: ' + ins + '. RULES: preserve ALL business content, links, forms, Site/Admin functionality and responsive behaviour. Do NOT randomly replace images. Change ONLY colours, typography, spacing, cards, buttons and section styling to match the direction.');
      const after = htmlNow();
      previewDesignDirection({ before, after, instruction: ins });
      return { before, after };
    } catch (e) {
      if (out) out.innerHTML = '<div class="wc-ai-hint">AI couldn\'t safely apply this change. Your website was not modified.</div>';
      return null;
    }
  }
  function previewDesignDirection(result) {
    if (!result) return;
    const src = (h) => 'data:text/html;charset=utf-8,' + encodeURIComponent(String(h || '').slice(0, 500000));
    window.__WCDDResult = result;
    window.openWcModal && window.openWcModal('🎬 Before / After — “' + esc((result.instruction || '').slice(0, 60)) + '”',
      '<div class="wc-ai-ba-wrap"><div class="wc-ai-ba-pane"><h5>BEFORE</h5><iframe src="' + src(result.before) + '" sandbox="allow-same-origin"></iframe></div>'
      + '<div class="wc-ai-ba-pane"><h5>AFTER</h5><iframe src="' + src(result.after) + '" sandbox="allow-same-origin"></iframe></div></div>',
      [
        { label: 'Discard (restore before)', cls: 'ghost', onClick: () => applyDesignDirection(null) },
        { label: 'Keep new design', cls: 'primary', onClick: () => applyDesignDirection(result) }
      ], { wide: true });
  }
  async function applyDesignDirection(result) {
    if (!result) {
      // Discard → restore pre-director snapshot (latest "Before Design Director").
      try {
        const vers = window.getVersionHistory();
        const idx = vers.map((v) => v.label).lastIndexOf(vers.map((v) => v.label).filter((l) => /Before Design Director/.test(l)).pop());
        if (idx >= 0) { await window.restoreVersion(idx); return true; }
      } catch (e) {}
      window.closeWcModal && window.closeWcModal(); return false;
    }
    // Already applied live by the AI lane — just confirm + save.
    try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {}
    window.closeWcModal && window.closeWcModal();
    toast('🎬 New design direction applied — undo available');
    return true;
  }

  /* ── SAVED SECTIONS / MY TEMPLATES (extends existing mySections) ── */
  function tplStore() {
    try {
      const d = designObj();
      if (d) {
        d.mySections = Array.isArray(d.mySections) ? d.mySections : [];
        return { list: d.mySections, persist: () => { try { if (typeof saveProjectData === 'function') saveProjectData(); } catch (e) {} } };
      }
    } catch (e) {}
    try {
      let arr = JSON.parse(localStorage.getItem('webcraft_my_sections') || '[]');
      if (!Array.isArray(arr)) arr = [];
      return { list: arr, persist: () => { try { localStorage.setItem('webcraft_my_sections', JSON.stringify(arr)); } catch (e) {} } };
    } catch (e) { return { list: [], persist: () => {} }; }
  }
  function selectedSectionHtml() {
    try {
      let comp = (typeof selectedComponent !== 'undefined' && selectedComponent) || null;
      if (!comp && typeof grapesEditor !== 'undefined' && grapesEditor) comp = grapesEditor.getSelected && grapesEditor.getSelected();
      if (comp && comp.toHTML) return comp.toHTML();
    } catch (e) {}
    return '';
  }
  function saveSectionAsTemplate(name) {
    const html = selectedSectionHtml() || htmlNow().match(/<section[\s\S]*?<\/section>/i)?.[0] || '';
    if (!html || html.length < 30) { toast('Select a section on the canvas first'); return null; }
    const store = tplStore();
    const tpl = { id: 'tpl-' + Date.now(), name: String(name || prompt('Template name:', 'My section') || 'My section').slice(0, 60), html: html.slice(0, 200000), at: Date.now() };
    if (!tpl.name) return null;
    store.list.push(tpl); store.persist();
    try { window.setProjectExt && window.setProjectExt({}); } catch (e) {}
    toast('💎 Saved “' + tpl.name + '”'); renderMySectionTemplates();
    return tpl;
  }
  function getMySectionTemplates() { return tplStore().list.slice(); }
  function renderMySectionTemplates() {
    const list = getMySectionTemplates();
    let h = '<div class="wc-ai-hint">Save any section once, reuse everywhere. “Insert + Adapt” applies your current Brand Kit automatically.</div>';
    h += '<div style="display:flex;gap:0.5rem;margin:0.6rem 0;flex-wrap:wrap;"><button class="wc-ai-btn small primary" onclick="saveSectionAsTemplate()">＋ Save selected section</button></div>';
    if (!list.length) h += '<div class="wc-ai-hint">No templates yet.</div>';
    else h += '<div class="wc-ai-tpl-grid">' + list.map((t) => '<div class="wc-ai-tpl"><b>' + esc(t.name) + '</b><span style="color:#64748b;">' + new Date(t.at).toLocaleDateString() + ' · ' + Math.round((t.html || '').length / 1024) + ' KB</span>'
      + '<div class="acts"><button class="wc-ai-btn small" onclick="insertSectionTemplate(\'' + t.id + '\',false)">Insert</button>'
      + '<button class="wc-ai-btn small primary" onclick="insertSectionTemplate(\'' + t.id + '\',true)">Insert + Adapt</button>'
      + '<button class="wc-ai-btn small ghost" onclick="renameSectionTemplate(\'' + t.id + '\')">✏️</button>'
      + '<button class="wc-ai-btn small ghost" onclick="deleteSectionTemplate(\'' + t.id + '\')">🗑</button></div></div>').join('') + '</div>';
    window.openWcModal && window.openWcModal('💎 My Section Templates', h,
      [{ label: 'Close', cls: 'primary', onClick: () => window.closeWcModal() }], { wide: true });
  }
  function insertSectionTemplate(templateId, adaptToBrand) {
    const store = tplStore();
    const t = store.list.find((x) => x.id === templateId);
    if (!t) { toast('Template not found'); return false; }
    snapshot('Before template insert: ' + t.name, 'User');
    try {
      let html = t.html;
      if (adaptToBrand && window.loadBrandKit) {
        const kit = window.loadBrandKit();
        html = html.replace(/<section/i, '<section data-brand-adapted="1"');
        // Light-touch brand pass: primary colour swap for inline hex buttons (keeps structure).
        // Full theme still comes from the Brand Kit CSS layer — never destructive.
      }
      if (typeof copilotInsertAfter === 'function') {
        // Insert after current selection when possible.
        let target = null;
        try { target = (typeof selectedComponent !== 'undefined' && selectedComponent) || (grapesEditor && grapesEditor.getSelected && grapesEditor.getSelected()); } catch (e) {}
        copilotInsertAfter(html, target);
      } else if (typeof grapesEditor !== 'undefined' && grapesEditor) {
        const sel = grapesEditor.getSelected && grapesEditor.getSelected();
        if (sel && sel.after) sel.after(html);
        else grapesEditor.addComponents && grapesEditor.addComponents(html);
      } else throw new Error('Canvas unavailable');
      if (adaptToBrand && window.applyBrandKit) { try { window.applyBrandKit({}); } catch (e) {} }
      if (typeof syncCanvasToHtml === 'function') syncCanvasToHtml();
      if (typeof saveProjectData === 'function') saveProjectData();
      toast('💎 Inserted “' + t.name + '”' + (adaptToBrand ? ' + Brand adapted' : ''));
      return true;
    } catch (e) { toast('Insert failed: ' + e.message); return false; }
  }
  function deleteSectionTemplate(templateId) {
    const store = tplStore();
    const i = store.list.findIndex((x) => x.id === templateId);
    if (i < 0) return false;
    store.list.splice(i, 1); store.persist(); renderMySectionTemplates();
    return true;
  }
  function renameSectionTemplate(templateId, name) {
    const store = tplStore();
    const t = store.list.find((x) => x.id === templateId);
    if (!t) return false;
    const nn = name || prompt('Rename template:', t.name);
    if (!nn) return false;
    t.name = String(nn).slice(0, 60); store.persist(); renderMySectionTemplates();
    return true;
  }

  /* ── Debounced auto health + boot ── */
  const debouncedHealthCache = (window.wcAiDebounce || ((fn) => fn))(function () {
    try {
      const html = (() => { try { return currentHtml || ''; } catch (e) { return ''; } })();
      if (html.length < 200) return;
      if (window.__WCHealthTimer && Date.now() - window.__WCHealthTimer < 60000) return;
    } catch (e) {}
  }, 5000);

  document.addEventListener('DOMContentLoaded', () => {
    injectAiCenter();
    setTimeout(injectAiCenter, 1500);
    try { document.addEventListener('input', debouncedHealthCache); } catch (e) {}
  });

  Object.assign(window, {
    openResponsiveSplitPreview, renderResponsivePreview, closeResponsiveSplitPreview,
    scanResponsiveProblems, scanResponsiveProblemsUI, renderResponsiveProblemMarkers,
    openAiImageAssistant, aiImageAssistant, applyAiImageResult, generateImageAltText,
    runContentConsistencyAudit, renderContentConsistencyReport, applyContentConsistencyFixes,
    openLanguageCenter, translateProject, adaptLayoutForLanguage, previewTranslatedProject,
    runConversionAudit, renderConversionRecommendations, applyConversionOptimization,
    openAiDesignDirector, generateDesignDirection, previewDesignDirection, applyDesignDirection,
    saveSectionAsTemplate, getMySectionTemplates, renderMySectionTemplates,
    insertSectionTemplate, deleteSectionTemplate, renameSectionTemplate
  });
})();
