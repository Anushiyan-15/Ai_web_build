/* ═══════════════════════════════════════════════════════════
   wc-builder-suite.js — Builder UX upgrades (Phase 1 + 4).
   Reuses: wizNav/validateStep/updateWizDisplay/updateGenerateButton/
   saveSessionNow/tryRestoreSession/restoreIntoWorkspace/refreshAccountUI/
   loadBestResumeSession/fillDemoData/clearWizardForm/toggleDemoPresetsMenu
   + OpenCodeAI / AIFlowCraft generation + Supabase/publish flow.
   ═══════════════════════════════════════════════════════════ */
(function () {
  'use strict';
  if (window.__WCBuilderSuiteLoaded) return;
  window.__WCBuilderSuiteLoaded = true;

  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  const $ = (id) => { try { return document.getElementById(id); } catch (e) { return null; } };
  const toast = (m, ms, t) => { try { (window.wcAiToast || showToast)(m, ms, t); } catch (e) {} };
  const siteBase = () => {
    try {
      if (typeof SITE_URL_JS === 'string' && SITE_URL_JS) return SITE_URL_JS.replace(/\/+$/, '');
      if (typeof SITE_URL === 'string' && SITE_URL) return SITE_URL.replace(/\/+$/, '');
    } catch (e) {}
    return '';
  };

  /* ── 1. AI BUSINESS BRIEF ── */
  function heuristicBrief(text) {
    const t = String(text || '').trim();
    const lower = t.toLowerCase();
    let biz_name = '';
    let m = t.match(/(?:business is|called|named)\s+[“"']?([A-Z][\w&.'\- ]{1,48}?)(?:["'”]| in |\.|,| —| -| we | our |$)/);
    if (m) biz_name = m[1].trim();
    if (!biz_name) {
      m = t.match(/^([\w&.'\- ]{2,40}?)\s+(?:is a|is an|offers|provides)/i);
      if (m) biz_name = m[1].trim();
    }
    // Never fill a generic placeholder as the business name — leave empty for user/AI.
    if (/^(my business|my company|our business|a small|small|the business|business)$/i.test(biz_name)) biz_name = '';
    const typeMap = [
      [/fitness|gym|yoga|personal train|workout|nutrition/i, 'Fitness Center & Personal Trainer'],
      [/restaurant|cafe|caf[eé]|bar|food|bakery|pizza|coffee/i, 'Restaurant, Cafe & Bar'],
      [/saas|software|startup|tech |app |ai /i, 'Tech Startup & SaaS'],
      [/clinic|dental|doctor|health|wellness|hospital|physio/i, 'Healthcare & Wellness Clinic'],
      [/real estate|property|architect/i, 'Real Estate & Architecture'],
      [/shop|e-?commerce|store|retail|boutique|fashion|clothing/i, 'E-Commerce & Retail'],
      [/school|college|tutor|train|coach|education|course|academy/i, 'School / College / Education'],
      [/agency|design|studio|marketing|brand/i, 'Creative & Digital Agency'],
      [/legal|law|account|consult/i, 'Professional Services & Legal'],
      [/coach\s*\(|life coach|business coach/i, 'Coach (Life / Business)'],
      [/clean/i, 'Cleaning Service'],
      [/portfolio|creator|photograph|freelanc/i, 'Personal Portfolio & Creator']
    ];
    let biz_type = 'Other';
    for (const [re, label] of typeMap) { if (re.test(t)) { biz_type = label; break; } }
    let tagline = '';
    m = t.match(/(?:mission is|we want|goal is|aim is)\s+([^.]{8,120})/i);
    if (m) tagline = m[1].trim();
    if (!tagline) {
      const vibe = /energetic|dynamic|bold/i.test(t) ? 'Energetic' : /luxur|premium/i.test(t) ? 'Premium' : /modern/i.test(t) ? 'Modern' : 'Trusted';
      const place = /sri lanka|colombo|kandy|galle|jaffna/i.test(t) ? ' in Sri Lanka' : '';
      tagline = vibe + ' ' + biz_type.split(' &')[0].toLowerCase() + ' services' + place + ' — built around you.';
      if (biz_name) tagline = tagline.charAt(0).toUpperCase() + tagline.slice(1);
    }
    let audience = '';
    m = t.match(/(?:target customers are|customers are|audience is|for)\s+([^.]{4,120})/i);
    if (m) audience = m[1].trim();
    let services = '';
    m = t.match(/(?:we offer|offer|services (?:include|are))\s+([^.]{4,220})/i);
    if (m) services = m[1].trim().replace(/\s+and\s+/gi, ', ');
    // ── Products: "we sell X", "products: ...", or item + price mentions.
    // Backend format per line: Name | Price | ImageURL(optional).
    let products = '';
    try {
      // Protect "Rs.1500" dots so sentence-stop patterns ([^.]) don't cut
      // product lists mid-price. "Rs 1500" still matches the price patterns.
      const tw = t.replace(/\b(Rs|LKR)\.\s*(?=\d)/gi, '$1 ');
      const prodLines = [];
      const seen = new Set();
      const pushProd = (name, price) => {
        name = String(name || '').trim().replace(/\s+/g, ' ').replace(/[,.;:]+$/, '');
        price = String(price || '').trim().replace(/[,.;:]+$/, '');
        // "Chocolate Cake Rs" (currency glued to name) → "Chocolate Cake".
        name = name.replace(/\s+(Rs\.?|LKR|₹|\$)$/i, '').trim();
        // "and Chicken Bun" (list joiner) → "Chicken Bun".
        name = name.replace(/^(and|or|with)\s+/i, '').trim();
        // Drop sentence fragments (periods, too long, generic lead-ins).
        if (name.length < 2 || name.length > 40) return;
        if (/[.]/.test(name)) return;
        if (name.split(' ').length > 5) return;
        if (/^(we|our|bakery in|products?|items?|call us)\b/i.test(name)) return;
        const key = name.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        prodLines.push(price ? (name + ' | ' + price) : (name + ' | Ask price'));
      };
      // Explicit "we sell / products / items:" list
      const listM = tw.match(/(?:we sell|we offer for sale|products?(?:\s+include|:)|items?:)\s+([^.]{4,240})/i);
      if (listM) {
        listM[1].split(/,|\band\b|;/i).forEach((chunk) => {
          const pm = chunk.match(/(.+?)\s*((?:Rs\.?|LKR|₹|\$)\s?[\d,]+(?:\.\d{1,2})?)/i);
          if (pm) pushProd(pm[1], pm[2]);
          else if (chunk.trim().length > 2 && chunk.trim().length < 50) pushProd(chunk, '');
        });
      }
      // Any "Item name Rs.499" mentions anywhere in the text.
      // Name class excludes periods so it can never span sentences.
      const priceRe = /([A-Za-z][\w&'\- ]{1,30}?)\s*((?:Rs\.?|LKR|₹|\$)\s?[\d,]+(?:\.\d{1,2})?)/gi;
      let pm2;
      while ((pm2 = priceRe.exec(tw)) && prodLines.length < 12) pushProd(pm2[1], pm2[2]);
      if (prodLines.length) products = prodLines.slice(0, 12).join('\n');
    } catch (e) {}
    // ── Reviews: "review", quoted praise, "customers say ...", "rated ...".
    // Backend format per line: Name | Review text | Role(optional). Max 6.
    let reviews = '';
    try {
      const revLines = [];
      // "Name | review text" explicit pastes
      t.split('\n').forEach((line) => {
        if (/\|/.test(line) && /review|great|excellent|recommend|love|best|amazing|fantast|good service/i.test(line)) {
          const parts = line.split('|').map((s) => s.trim()).filter(Boolean);
          if (parts.length >= 2 && revLines.length < 6) revLines.push(parts.slice(0, 3).join(' | '));
        }
      });
      // Quoted praise with a nearby name:  "quote" — Name  /  Name: "quote"
      const qRe = /["“”']([^"“”']{10,160})["“”']\s*(?:—|–|-|by|from)?\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/g;
      let qm;
      while ((qm = qRe.exec(t)) && revLines.length < 6) revLines.push(qm[2].trim() + ' | ' + qm[1].trim() + ' | Happy Customer');
      // "Priya says great service" style
      const sRe = /([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s+(?:says|said|told us|reviewed|rated us|gave us)\s+([^.]{8,140})/g;
      let sm;
      while ((sm = sRe.exec(t)) && revLines.length < 6) revLines.push(sm[1].trim() + ' | ' + sm[2].trim() + ' | Happy Customer');
      if (revLines.length) reviews = revLines.slice(0, 6).join('\n');
    } catch (e) {}
    let contact_method = 'Contact form';
    if (/whatsapp/i.test(t)) contact_method = 'WhatsApp';
    else if (/call|phone/i.test(t)) contact_method = 'Phone call';
    else if (/instagram/i.test(t)) contact_method = 'Instagram DM';
    let language = 'English';
    if (/tamil/i.test(t)) language = 'Tamil';
    else if (/sinhala|sinhalese/i.test(t)) language = 'Sinhala';
    const style = /luxur|premium|dark|elegant/i.test(t) ? 'dark' : (/energetic|bold|dynamic|vibrant|playful/i.test(t) ? 'bold' : 'modern');
    const palette = /energetic|bold|red|dynamic/i.test(t) ? 'red' : (/trust|blue|professional/i.test(t) ? 'blue' : (/eco|green|health|fitness/i.test(t) ? 'green' : (/luxur|gold|premium/i.test(t) ? 'gold' : (/minimal|slate|mono/i.test(t) ? 'slate' : 'purple'))));
    const sections = ['hero', 'services', 'about', 'contact', 'footer'];
    if (/metric|stat|result|proof|review/i.test(t)) sections.splice(3, 0, 'metrics');
    if (/product|shop|price|buy|store|sell/i.test(t) && !sections.includes('shop')) sections.splice(3, 0, 'shop');
    return {
      biz_name, biz_type, tagline,
      audience: audience || 'Local customers looking for quality service',
      services: services || t.split(/[.]\s*/)[1]?.slice(0, 160) || 'Core services described on the website',
      products, reviews,
      recommended_sections: sections,
      recommended_cta: contact_method === 'WhatsApp' ? 'Chat on WhatsApp' : (contact_method === 'Phone call' ? 'Call now' : 'Get in touch'),
      design_style: style, color_palette: palette,
      typography_direction: style === 'dark' ? 'Elegant serif headings, generous spacing' : 'Clean geometric sans, strong hierarchy',
      contact_method, language,
      suggested_features: contact_method === 'WhatsApp'
        ? ['WhatsApp CTA on hero + contact', 'Click-to-call backup', 'Testimonials near CTA']
        : ['Sticky contact CTA', 'Testimonials near CTA', 'Simple contact form']
    };
  }

  async function aiBusinessBriefFromNaturalLanguage(text) {
    const input = String(text || ($('wc-brief-input') ? $('wc-brief-input').value : '') || '').trim();
    if (input.length < 10) { toast('Describe your business in a sentence or two first'); return null; }
    const box = $('wc-ai-brief-result');
    if (box) box.innerHTML = '<div class="wc-ai-loading"><span class="wc-ai-spinner"></span> AI is understanding your business…</div>';
    // Try AI parse via existing ask lane (never a new engine).
    // Formats must match the Builder contract exactly, otherwise the
    // generator cannot use them: products "Name | Price | ImageURL?",
    // reviews "Name | Review text | Role?" (one per line, backend caps at 6).
    let data = null;
    try {
      const j = await (window.wcAiPost
        ? window.wcAiPost('/api/generate.php', {
          action: 'ask',
          instruction: 'Parse the business description into STRICT JSON only (no markdown, no commentary) with keys: biz_name,biz_type,tagline,audience,services,products,reviews,recommended_sections(array of hero/services/about/metrics/shop/contact/footer),recommended_cta,design_style(one of modern/bold/dark),color_palette(one of purple/blue/green/red/gold/slate),typography_direction,contact_method,language,suggested_features(array).'
            + ' RULES: (1) products: ONLY if the description sells items with names/prices — one per line as "Name | Price | ImageURL(optional)", else empty string. Never invent products. (2) reviews: ONLY if it mentions real customer feedback with names — one per line as "Name | Review text | Role(optional)", max 6 lines, else empty string. Never invent reviews. (3) If products is non-empty, recommended_sections MUST include "shop". (4) If reviews is non-empty, mention testimonials in suggested_features. Description: ' + input.slice(0, 1500),
          context: { brand: '', page: '', target: input.slice(0, 1500) }
        }, 45000)
        : Promise.reject(new Error('no-post')));
      const reply = j && (j.reply || j.text || j.brief || '');
      if (reply) {
        const m = String(reply).match(/\{[\s\S]*\}/);
        if (m) {
          const parsed = JSON.parse(m[0]);
          if (parsed && typeof parsed === 'object') data = parsed;
        }
      }
    } catch (e) { data = null; }
    if (!data || !data.tagline) {
      // Deterministic fallback — always works offline.
      data = heuristicBrief(input);
      data._fallback = true;
    }
    // Normalise
    data.biz_name = String(data.biz_name || '').trim();
    data.biz_type = String(data.biz_type || 'Other').trim();
    data.tagline = String(data.tagline || '').trim() || heuristicBrief(input).tagline;
    data.audience = String(data.audience || '');
    data.services = String(data.services || '');
    data.products = String(data.products || '').trim().split('\n').map((s) => s.trim()).filter(Boolean).slice(0, 12).join('\n');
    data.reviews = String(data.reviews || '').trim().split('\n').map((s) => s.trim()).filter(Boolean).slice(0, 6).join('\n');
    if (!Array.isArray(data.recommended_sections) || !data.recommended_sections.length) data.recommended_sections = ['hero', 'services', 'about', 'contact', 'footer'];
    // Products found → shop section MUST be on, else generation skips the cart.
    if (data.products && !data.recommended_sections.includes('shop')) {
      const at = data.recommended_sections.indexOf('contact');
      data.recommended_sections.splice(at === -1 ? data.recommended_sections.length : at, 0, 'shop');
    }
    if (!['modern', 'bold', 'dark'].includes(data.design_style)) data.design_style = 'modern';
    if (!['purple', 'blue', 'green', 'red', 'gold', 'slate'].includes(data.color_palette)) data.color_palette = 'purple';
    window.__AiBriefData = data;
    try { window.setProjectExt && window.setProjectExt({ aiBrief: data }); } catch (e) {}
    renderAiBriefSummary(data);
    try { generateBuilderRecommendations(data); } catch (e) {}
    return data;
  }

  function renderAiBriefSummary(data) {
    const d = data || window.__AiBriefData;
    if (!d) return;
    const box = $('wc-ai-brief-result');
    if (!box) return;
    box.innerHTML = '<div class="wc-ai-brief-card"><h4>✨ AI understood your business' + (d._fallback ? ' <span class="wc-ai-pill info">quick parse</span>' : '') + '</h4>'
      + '<dl class="wc-ai-kv">'
      + '<dt>Business</dt><dd>' + esc(d.biz_name || '—') + '</dd>'
      + '<dt>Industry</dt><dd>' + esc(d.biz_type || '—') + '</dd>'
      + '<dt>Audience</dt><dd>' + esc(d.audience || '—') + '</dd>'
      + '<dt>Main services</dt><dd>' + esc(String(d.services || '').slice(0, 220) || '—') + '</dd>'
      + (d.products
        ? '<dt>Products (' + d.products.split('\n').length + ')</dt><dd>' + esc(d.products.split('\n').slice(0, 4).join(' · ').slice(0, 220)) + (d.products.split('\n').length > 4 ? ' …' : '') + ' <span class="wc-ai-chip">🛒 shop section ON</span></dd>'
        : '')
      + (d.reviews
        ? '<dt>Reviews (' + d.reviews.split('\n').length + ')</dt><dd>' + esc(d.reviews.split('\n').slice(0, 2).join(' · ').slice(0, 220)) + (d.reviews.split('\n').length > 2 ? ' …' : '') + ' <span class="wc-ai-chip">⭐ testimonials</span></dd>'
        : '<dt>Reviews</dt><dd style="color:#64748b;">None mentioned — testimonials skipped at generation</dd>')
      + '<dt>Style</dt><dd>' + esc(d.design_style) + ' · ' + esc(d.color_palette) + '</dd>'
      + '<dt>Sections</dt><dd>' + (d.recommended_sections || []).map((s) => '<span class="wc-ai-chip">' + esc(s) + '</span>').join('') + '</dd>'
      + '<dt>Primary CTA</dt><dd>' + esc(d.recommended_cta || 'Get in touch') + '</dd>'
      + '</dl>'
      + '<div style="display:flex;gap:0.5rem;margin-top:0.8rem;flex-wrap:wrap;">'
      + '<button class="wc-ai-btn primary" onclick="applyAiBriefToWizard()">Apply to Builder ✓</button>'
      + '<button class="wc-ai-btn ghost" onclick="document.getElementById(\'biz_name\')?.focus()">Edit Details</button>'
      + '</div></div>';
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function setRadio(name, value) {
    try {
      const els = document.querySelectorAll('input[name="' + name + '"]');
      els.forEach((el) => { el.checked = (el.value === value); });
    } catch (e) {}
  }
  function applyAiBriefToWizard(data) {
    const d = data || window.__AiBriefData;
    if (!d) { toast('Generate the AI brief first'); return false; }
    try { window.createVersionSnapshot && window.createVersionSnapshot('Before AI brief apply', 'AI'); } catch (e) {}
    const set = (id, v) => { const el = $(id); if (el && v !== undefined) el.value = v; };
    if (d.biz_name) set('biz_name', d.biz_name);
    if (d.biz_type) {
      const sel = $('biz_type');
      if (sel) {
        // Match option VALUE (always English) — display text may be translated.
        const vals = [...sel.options].map((o) => o.value || o.text);
        const want = String(d.biz_type).trim().toLowerCase();
        const hit = vals.find((v) => v.toLowerCase() === want)
          || vals.find((v) => v.toLowerCase().includes(want.split(' ')[0]));
        if (hit) sel.value = hit;
      }
    }
    if (d.tagline) set('biz_tagline', d.tagline);
    if (d.audience) set('biz_audience', d.audience);
    if (d.services) set('biz_services', d.services);
    if (d.products) set('biz_products', d.products);
    if (d.reviews) set('biz_reviews', d.reviews);
    if (d.design_style) setRadio('design_style', d.design_style);
    if (d.color_palette) setRadio('color_palette', d.color_palette);
    try {
      const secs = new Set(d.recommended_sections || []);
      // Safety: products extracted but stale brief lacks shop → force it on,
      // otherwise Generate builds no shop/cart despite having products.
      if (d.products && String(d.products).trim()) secs.add('shop');
      document.querySelectorAll('input[name="sections[]"]').forEach((c) => { c.checked = secs.size ? secs.has(c.value) : c.checked; });
    } catch (e) {}
    if (d.contact_method === 'WhatsApp' && !$('biz_phone')?.value) set('biz_phone', '+94 77 123 4567');
    try {
      if (typeof updateWizDisplay === 'function') updateWizDisplay();
      if (typeof updateGenerateButton === 'function') updateGenerateButton();
      if (typeof validateStep === 'function') validateStep(1);
      if (typeof saveSessionNow === 'function') saveSessionNow();
    } catch (e) {}
    toast('✅ Business brief applied — review Step 1, then continue');
    try {
      const nP = (d.products ? String(d.products).split('\n').filter(Boolean).length : 0);
      const nR = (d.reviews ? String(d.reviews).split('\n').filter(Boolean).length : 0);
      if (nP || nR) setTimeout(() => toast('🛒 ' + nP + ' product(s)' + (nR ? ' · ⭐ ' + nR + ' review(s)' : '') + ' will generate with the site'), 1200);
    } catch (e) {}
    return true;
  }

  /* ── 2. RECOMMENDATIONS ── */
  function generateBuilderRecommendations(data) {
    const d = data || window.__AiBriefData || heuristicBrief(
      ($('biz_services') ? $('biz_services').value : '') + ' ' + ($('biz_type') ? $('biz_type').value : '')
    );
    const sections = d.recommended_sections && d.recommended_sections.length ? d.recommended_sections : ['hero', 'services', 'about', 'contact', 'footer'];
    const rec = {
      sections,
      cta: d.recommended_cta || 'Get in touch',
      conversionPath: 'Hero → Services → Trust (reviews/metrics) → Contact (' + (d.contact_method || 'form') + ')',
      numSections: sections.length,
      visualStyle: d.design_style || 'modern',
      colorDirection: d.color_palette || 'purple',
      useAdmin: /shop|product|order|book|member|student|patient/i.test(JSON.stringify(d)),
      reasons: [
        'Sections match a high-converting local-business flow',
        (d.contact_method === 'WhatsApp' ? 'WhatsApp CTA kept visible (hero + contact)' : 'Contact CTA repeated after trust signals'),
        ((d.design_style || '') === 'bold' ? 'Bold style fits an energetic brand' : (d.design_style === 'dark' ? 'Dark luxury fits a premium brand' : 'Modern clean fits broad trust')),
        ...(d.products && String(d.products).trim() ? ['🛒 Products detected → shop section + working cart will generate'] : []),
        ...(d.reviews && String(d.reviews).trim() ? ['⭐ Reviews detected → testimonials render under trust signals'] : [])
      ]
    };
    try { window.setProjectExt && window.setProjectExt({ aiRecommendations: rec }); } catch (e) {}
    renderBuilderRecommendations(rec);
    return rec;
  }
  function renderBuilderRecommendations(rec) {
    const r = rec || (window.getProjectExt && window.getProjectExt().aiRecommendations);
    const box = $('wc-ai-rec-result');
    if (!box || !r) return;
    box.innerHTML = '<div class="wc-ai-rec-box"><h4>🤖 AI Recommendations <span style="font-weight:400;color:#94a3b8;">— optional, tap to accept</span></h4>'
      + '<ul><li><b>Sections (' + r.numSections + '):</b> ' + esc(r.sections.join(' → ')) + '</li>'
      + '<li><b>Primary CTA:</b> ' + esc(r.cta) + '</li>'
      + '<li><b>Path:</b> ' + esc(r.conversionPath) + '</li>'
      + '<li><b>Style:</b> ' + esc(r.visualStyle) + ' · ' + esc(r.colorDirection) + '</li>'
      + '<li><b>Site/Admin mode:</b> ' + (r.useAdmin ? 'useful (bookings/orders/members detected)' : 'probably unnecessary — static site is faster') + '</li></ul>'
      + '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;">'
      + '<button class="wc-ai-btn small good" onclick="applyAiBriefToWizard()">Accept & Apply ✓</button>'
      + '<button class="wc-ai-btn small ghost" onclick="document.getElementById(\'step3\')?.scrollIntoView({behavior:\'smooth\'})">Modify sections</button>'
      + '</div></div>';
  }

  /* ── 3. Inject Brief UI (grouped, not 30 buttons) ── */
  function injectBriefUI() {
    try {
      const step1 = $('step1');
      if (!step1 || $('wc-brief-launcher')) return;
      const T = (window.WC_UI && window.WC_UI.t) || {};
      const tx = (k, fb) => T[k] || fb;
      const div = document.createElement('div');
      div.className = 'wc-ai-brief-launcher';
      div.id = 'wc-brief-launcher';
      div.innerHTML = '<h3>' + esc(tx('brief_t', '✨ AI Business Brief')) + ' <span style="font-weight:400;font-size:0.74rem;color:#a5b4fc;">— optional shortcut</span></h3>'
        + '<p>' + esc(tx('brief_s', 'Type one natural sentence about your business. AI fills Steps 1–3 for you — you stay in control.')) + '</p>'
        + '<textarea class="wc-ai-textarea" id="wc-brief-input" rows="3" placeholder="e.g. My business is a small fitness studio in Sri Lanka. We offer personal training, group workouts and nutrition coaching. Target: beginners and young professionals. Modern energetic site with WhatsApp contact."></textarea>'
        + '<div style="display:flex;gap:0.5rem;margin-top:0.6rem;flex-wrap:wrap;">'
        + '<button class="wc-ai-btn primary" id="wc-brief-go" type="button">' + esc(tx('brief_btn', '✨ Understand my business')) + '</button>'
        + '<button class="wc-ai-btn ghost" id="wc-brandkit-open" type="button">' + esc(tx('brandkit', '🎨 Brand Kit')) + '</button>'
        + '</div>'
        + '<div id="wc-ai-brief-result"></div><div id="wc-ai-rec-result"></div>';
      step1.insertBefore(div, step1.firstChild);
      $('wc-brief-go').onclick = () => aiBusinessBriefFromNaturalLanguage();
      $('wc-brandkit-open').onclick = () => { try { window.openBrandKit && window.openBrandKit(); } catch (e) {} };
      // Restore previous brief silently
      try {
        const ext = window.getProjectExt && window.getProjectExt();
        if (ext && ext.aiBrief && ext.aiBrief.tagline) {
          window.__AiBriefData = ext.aiBrief;
          renderAiBriefSummary(ext.aiBrief);
          if (ext.aiRecommendations) renderBuilderRecommendations(ext.aiRecommendations);
        }
      } catch (e) {}
    } catch (e) {}
  }

  /* ── 4. Project dashboard search/sort/duplicate/rename/archive ── */
  function enhanceProjectMenu() {
    try {
      const menu = $('load-project-dropdown');
      if (!menu || menu.__wcEnhanced) return;
      menu.__wcEnhanced = true;
      const bar = document.createElement('div');
      bar.style.cssText = 'padding:0.5rem;border-bottom:1px solid #1e293b;display:flex;gap:0.4rem;';
      bar.innerHTML = '<input id="wc-proj-q" class="wc-ai-input" placeholder="🔍 Search projects…" style="font-size:0.76rem;padding:0.4rem 0.6rem;">'
        + '<select id="wc-proj-sort" class="wc-ai-select" style="max-width:110px;font-size:0.76rem;padding:0.4rem;"><option value="recent">Recent</option><option value="name">Name</option><option value="oldest">Oldest</option></select>';
      menu.insertBefore(bar, menu.firstChild);
      const render = () => {
        const q = $('wc-proj-q') ? $('wc-proj-q').value : '';
        const mode = $('wc-proj-sort') ? $('wc-proj-sort').value : 'recent';
        let list = window.searchProjects ? window.searchProjects(q) : [];
        if (window.sortProjects) {
          const sorted = window.sortProjects(mode).map((o) => o.key);
          list = list.sort((a, b) => sorted.indexOf(a.key) - sorted.indexOf(b.key));
        }
        let extra = $('wc-proj-extra');
        if (!extra) { extra = document.createElement('div'); extra.id = 'wc-proj-extra'; menu.appendChild(extra); }
        if (!list.length) { extra.innerHTML = '<div class="wc-ai-hint" style="padding:0.6rem;">No matching projects for this account.</div>'; return; }
        extra.innerHTML = list.slice(0, 12).map((o) => {
          const s = o.session || {};
          const nm = esc(s.bizName || 'Website');
          const dt = s.savedAt ? new Date(s.savedAt).toLocaleDateString() : '';
          return '<div style="display:flex;gap:0.35rem;align-items:center;padding:0.35rem 0.5rem;border-top:1px solid #141d33;font-size:0.76rem;">'
            + '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#e2e8f0;" title="' + nm + '">' + nm + ' <span style="color:#64748b;">· ' + esc(dt) + '</span></span>'
            + '<button class="wc-ai-btn small ghost" data-act="preview" data-k="' + esc(o.key) + '">👁</button>'
            + '<button class="wc-ai-btn small ghost" data-act="dup" data-k="' + esc(o.key) + '">📋</button>'
            + '<button class="wc-ai-btn small ghost" data-act="ren" data-k="' + esc(o.key) + '">✏️</button>'
            + '</div>';
        }).join('');
        extra.querySelectorAll('button').forEach((b) => {
          b.onclick = (e) => {
            e.stopPropagation();
            const k = b.dataset.k, act = b.dataset.act;
            if (act === 'preview') window.previewProject(k);
            else if (act === 'dup') { window.duplicateProject(k); render(); }
            else if (act === 'ren') {
              const nm = prompt('Rename project:', '');
              if (nm) { window.renameProject(k, nm); render(); }
            }
          };
        });
      };
      $('wc-proj-q').addEventListener('input', render);
      $('wc-proj-sort').addEventListener('change', render);
      new MutationObserver(() => { if (menu.style.display === 'block') render(); })
        .observe(menu, { attributes: true, attributeFilter: ['style'] });
    } catch (e) {}
  }

  /* ── 5. Smart publish gate (extends, never replaces wizard) ── */
  function armPublishGate() {
    try {
      if (typeof saveAndProceedToPayment !== 'function' || window.__WCPublishGateArmed) return;
      window.__WCPublishGateArmed = true;
      const orig = window.saveAndProceedToPayment;
      window.__origSaveAndProceed = orig;
      window.saveAndProceedToPayment = async function (...args) {
        let r = null;
        try { r = window.runPrePublishAudit && window.runPrePublishAudit(); } catch (e) { r = null; }
        if (r && r.critical && r.critical.length) {
          window.renderPrePublishChecklist && window.renderPrePublishChecklist();
          toast('⚠️ ' + r.critical.length + ' critical issue(s) — fix or confirm to publish', 4500);
          return;
        }
        if (r && r.failed && r.failed.length) {
          const ok = await window.confirmWcModal('Publish with warnings?',
            'There are <b>' + r.failed.length + '</b> warning(s). You can publish anyway — quality may suffer. Continue?'.replace(/</g, '&lt;').replace(/&lt;b&gt;/g, '<b>').replace(/&lt;\/b&gt;/g, '</b>'),
            'Publish anyway');
          if (!ok) return;
        }
        return orig.apply(this, args);
      };
    } catch (e) {}
  }

  document.addEventListener('DOMContentLoaded', () => {
    injectBriefUI();
    enhanceProjectMenu();
    armPublishGate();
    // Re-arm lazily (functions defined later in page).
    setTimeout(() => { enhanceProjectMenu(); armPublishGate(); }, 1500);
  });

  Object.assign(window, {
    aiBusinessBriefFromNaturalLanguage, renderAiBriefSummary, applyAiBriefToWizard,
    generateBuilderRecommendations, renderBuilderRecommendations
  });
})();
