/* ═══════════════════════════════════════════════════════════════
   assets/js/opencode-service.js
   OpenCode Zen AI lane — generation + AI chat editing with fallback.

   CHAIN (never empty, all AI — no server templates):
     1. Variations 1-2 → Gemini gemini-3.5-flash-lite (FlowCraft-fed)
     2. Variation 3 → OpenCode Muse Spark 1.3
     3. Each slot has an AI backup lane (the other engine)

   Customer requirements are ANALYSED first (design brief), then the
   brief + FlowCraft layout direction feed every generation call so
   output looks premium even when fields are "(unspecified)".
   Tanglish / Tamil / English all understood; code stays English.
   ═══════════════════════════════════════════════════════════════ */
window.OpenCodeAI = (function () {
  'use strict';

  const KEY_MODEL = 'webcraft_opencode_model';

  const FREE_MODELS = [
    { id: 'space-bunny-free',                label: 'Space Bunny (Free · working)', free: true },
    { id: 'big-pickle',                      label: 'Big Pickle (Free stealth)', free: true },
    { id: 'muse-spark-1.3-contributor-free', label: 'Muse Spark 1.3 (Free)', free: true },
    { id: 'muse-spark-1.2-contributor-free', label: 'Muse Spark 1.2 (Free)', free: true },
    { id: 'mimo-v2.5-free',                  label: 'MiMo V2.5 (Free)', free: true },
    { id: 'mimo-v2.6-flash-free',            label: 'MiMo V2.6 Flash (Free)', free: true },
    { id: 'nemotron-3.5-lightning-free',     label: 'Nemotron 3.5 Lightning (Free)', free: true },
    { id: 'jev-1.13-free',                   label: 'Jev 1.13 (Free)', free: true },
    { id: 'longcat-2.5-preview-free',        label: 'LongCat 2.5 Preview (Free)', free: true }
  ];

  const VARIATIONS = ['classic', 'bold', 'editorial'];
  const VAR_META = {
    classic:   { name: 'Concept 1 — Modern Minimal & Crisp',       badge: 'Clean & Professional',  description: 'Light balanced aesthetic, glassmorphism sticky nav, refined cards. (OpenCode AI)' },
    bold:      { name: 'Concept 2 — Bold Dynamic & Bento Grid',    badge: 'High-Impact & Modern',  description: 'Oversized type, gradient mesh, bento cards, high-conversion flow. (OpenCode AI)' },
    editorial: { name: 'Concept 3 — Executive Luxury & Dark Mode', badge: 'Sleek Dark Glass',      description: 'Obsidian backdrop, aurora glows, frosted glass, luxury type. (OpenCode AI)' }
  };

  function base() {
    try {
      if (typeof SITE_URL !== 'undefined' && SITE_URL) return SITE_URL;
    } catch (e) {}
    return '';
  }
  function endpoint(path) {
    const b = base().replace(/\/+$/, '');
    return (b ? b : '') + path;
  }

  async function post(path, body, timeoutMs) {
    timeoutMs = timeoutMs || 95000;
    const ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    const timer = ctrl ? setTimeout(() => { try { ctrl.abort(); } catch (e) {} }, timeoutMs) : null;
    try {
      const resp = await fetch(endpoint(path), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
        signal: ctrl ? ctrl.signal : undefined
      });
      if (!resp.ok) throw new Error('HTTP ' + resp.status + ' on ' + path);
      return resp.json();
    } catch (e) {
      if (e && e.name === 'AbortError') throw new Error('AI request timed out — please retry');
      throw e;
    } finally {
      if (timer) clearTimeout(timer);
    }
  }

  function getModel() {
    try {
      return localStorage.getItem(KEY_MODEL) || FREE_MODELS[0].id;
    } catch (e) { return FREE_MODELS[0].id; }
  }
  function setModel(m) {
    if (!m || typeof m !== 'string') return;
    m = m.trim();
    if (!m) return;
    try { localStorage.setItem(KEY_MODEL, m); } catch (e) {}
    service.selectedModel = m;
    try {
      ['ai-model-select', 'wiz-model-select'].forEach(id => {
        const sel = document.getElementById(id);
        if (sel) sel.value = m;
      });
      const lbl = document.getElementById('ai-model-label');
      if (lbl) lbl.textContent = (FREE_MODELS.find(x => x.id === m) || {}).label || m;
    } catch (e) {}
  }

  /* ── Tanglish hint enrichment (mirrors builder.php) ── */
  const TANGLISH_HINTS = [
    [/\bmaathu|maathunga\b/i, 'change'],
    [/\bperiya|periyathu\b/i, 'make it bigger'],
    [/\bchinna|sinnathu\b/i, 'make it smaller'],
    [/\baakku|aakunga\b/i, 'make it'],
    [/\badd pannu|add pannunga\b/i, 'add'],
    [/\bremove pannu\b/i, 'remove'],
    [/\bkaattu|kaattunga\b/i, 'show'],
    [/\bazhaga|azhagana\b/i, 'beautiful'],
    [/\bkizha|keezha\b/i, 'at the bottom'],
    [/\bmela|mele\b/i, 'at the top']
  ];
  function enrichTanglish(text) {
    let out = String(text || '');
    TANGLISH_HINTS.forEach(([rx, en]) => { if (rx.test(out)) out += ` [hint: ${en}]`; });
    return out;
  }

  /* ── TASTE LAYER: customer vibe + dials (builder.php owns the UI) ── */
  function tastePrefs() {
    try {
      if (typeof window.collectTastePrefs === 'function') return window.collectTastePrefs() || {};
    } catch (e) {}
    return {};
  }

  /* ── Lane primitives ── */
  async function getModels() {
    return post('/api/opencode.php', { action: 'models' });
  }

  async function analyzeRequirements(data, onProgress) {
    if (onProgress) onProgress({ stage: 'analyzing', pct: 12, message: 'OpenCode AI analysing requirements…' });
    try {
      const j = await post('/api/opencode.php', { action: 'analyze', data, taste: tastePrefs(), model: getModel() }, 12000);
      if (j && j.brief) {
        // Track Gemini health: analyze rides the Gemini lite lane. If it
        // failed we know Gemini is down → route generation quick-first
        // (OpenCode) so we still get AI results instead of 0/3.
        service.geminiDown = !j.ai;
        if (onProgress) onProgress({ stage: 'analyzing', pct: 30, message: 'Design brief ready' + (j.model ? ' (' + j.model + ')' : '') });
        return j.brief;
      }
    } catch (e) { console.warn('[OpenCodeAI] analyze failed, using fallback brief:', e?.message); service.geminiDown = true; }
    const d = data || {};
    return `AUDIENCE: ${d.biz_audience || 'Modern clients'}\nSECTIONS: ${(d.sections || []).join(', ') || 'hero, services, about, metrics, contact, footer'}\nPALETTE: ${d.color_palette || 'purple'}\nTYPE: Plus Jakarta Sans + Inter\nDIFFERENTIATORS: glass nav; clay CTAs; scroll-reveal`;
  }

  /* Fixed lane split (no picker — customer never chooses):
     Variation 1 (classic) + Variation 2 (bold) → gemini-3.5-flash-lite first.
     Variation 3 (editorial) → OpenCode space-bunny-free FIRST (quick lane),
     so one AI result survives even when Gemini is fully down.
     NOTE: muse-spark-1.3 was requested but OpenCode rejects it over HTTP
     with 403 "free tier can only be used from within OpenCode" (verified
     2026-10-06 probe — same for big-pickle/mimo; only space-bunny works
     with this key). One-line swap if OpenCode allowlists it later.
     When the analyze step already proved Gemini down, ALL slots go
     quick-first (see lanesFor()). Every fallback stays inside AI —
     server templates are never used. */
  const OPENCODE_SLOT3_MODEL = 'space-bunny-free';
  const SLOT_LANES = [
    [ { kind: 'fast',  model: 'gemini-3.5-flash-lite',   temp: 0.7 },
      { kind: 'fast',  model: 'gemini-3.5-flash-lite',   temp: 0.8 },
      { kind: 'quick', model: OPENCODE_SLOT3_MODEL,     temp: 0.7 } ],
    [ { kind: 'fast',  model: 'gemini-3.5-flash-lite',   temp: 0.95 },
      { kind: 'fast',  model: 'gemini-3.5-flash-lite',   temp: 0.85 },
      { kind: 'quick', model: OPENCODE_SLOT3_MODEL,     temp: 0.95 } ],
    [ { kind: 'quick', model: OPENCODE_SLOT3_MODEL,     temp: 0.6 },
      { kind: 'fast',  model: 'gemini-3.5-flash-lite',   temp: 0.55 },
      { kind: 'fast',  model: 'gemini-3.5-flash-lite',   temp: 0.65 } ]
  ];
  /* Lane order per slot: Gemini-down (proven by analyze step) → quick
     first everywhere, so 3 parallel OpenCode gens replace 3 hung Gemini
     calls instead of burning the whole race budget on timeouts. */
  function lanesFor(i) {
    const base = SLOT_LANES[i] || SLOT_LANES[0];
    if (service.geminiDown) {
      const quick = base.filter(l => l.kind === 'quick');
      const fast = base.filter(l => l.kind !== 'quick');
      return [...quick, ...fast];
    }
    return base;
  }

  /* Shop guarantee: ecommerce must ship a WORKING cart (quantity +/-, drawer,
     totals, checkout). Backend injectShopIfMissing ensures this on every
     AI output. */
  function needsShop(data) {
    try {
      const d = data || {};
      if (d.biz_products && String(d.biz_products).trim()) return true;
      if (Array.isArray(d.sections) && d.sections.map(s => String(s).toLowerCase()).includes('shop')) return true;
      return /shop|store|product|retail|e-?commerce|boutique|mart|trading|enterprise|fashion|jewelry|grocery|bakery|furniture|electronics|pharma/i.test(d.biz_type || '');
    } catch (e) { return false; }
  }
  function hasWorkingCart(html) {
    const h = String(html || '');
    if (!/id=["']shop["']/i.test(h)) return false;
    const hasCartUi = /wc-cart-drawer/i.test(h) || /data-wc-add/i.test(h) || /wc-add-btn/i.test(h);
    const hasLogic = /localStorage/i.test(h) && /Add to Cart/i.test(h);
    return hasCartUi && hasLogic;
  }
  function assertShopOk(j, data, variation) {
    if (needsShop(data) && !hasWorkingCart(j.html || '')) {
      console.warn('[OpenCodeAI] AI shop cart missing [' + variation + '] — proceeding with generated output');
    }
    return j;
  }

  /* One fast-lane call (Gemini single-shot full page ~22s solo). */
  async function generateFast(data, variation, mode, brief, slot, model, temp) {
    const j = await post('/api/opencode.php', {
      action: 'fast_one', data, variation, mode, brief, taste: tastePrefs(),
      model: model || undefined, temperature: (typeof temp === 'number' ? temp : undefined),
      slot: (typeof slot === 'number' ? slot : -1)
    }, 75000);
    if (j && j.success && j.html && j.html.length > 500) return assertShopOk(j, data, variation);
    throw new Error(((j && (j.errors || [])[0]) || (j && j.error) || 'fast lane failed') + ' [' + variation + ']');
  }

  /* OpenCode QUICK lane — explicit model per slot (slot 3 = space-bunny). */
  async function generateQuick(data, variation, mode, brief, slot, temp, modelOverride) {
    const j = await post('/api/opencode.php', {
      action: 'quick_one', data, variation, mode, brief, taste: tastePrefs(), model: modelOverride || getModel(),
      temperature: (typeof temp === 'number' ? temp : undefined),
      slot: (typeof slot === 'number' ? slot : -1)
    }, 100000);
    if (j && j.success && j.html && j.html.length > 500) return assertShopOk(j, data, variation);
    throw new Error(((j && (j.errors || [])[0]) || (j && j.error) || 'quick lane failed') + ' [' + variation + ']');
  }

  /* Run one slot through its lane list in order — first success wins. */
  async function runSlotLanes(data, variation, mode, brief, slot, lanes) {
    let lastErr = null;
    for (const lane of lanes) {
      try {
        if (lane.kind === 'quick') {
          const j = await generateQuick(data, variation, mode, brief, slot, lane.temp, lane.model);
          return { j, engine: 'opencode-quick' };
        }
        const j = await generateFast(data, variation, mode, brief, slot, lane.model, lane.temp);
        return { j, engine: 'gemini' };
      } catch (e) { lastErr = e; }
    }
    throw lastErr || new Error('slot lanes failed [' + variation + ']');
  }
  /* ── TASTE POLISH (frontend-driven repair, optional, never fatal) ──
     Only failing pages (audit < 70) get a repair call in their own HTTP
     budget — 70+ pages ship as-is for speed. Keeps the better-scoring
     version; failures keep original. Callers batch these concurrently. */
  async function polishIfNeeded(j, onPolish) {
    try {
      if (!j || !j.taste_audit || typeof j.taste_audit.score !== 'number') return j;
      if (j.taste_audit.score >= 70) return j;
      if (onPolish) onPolish();
      const r = await post('/api/opencode.php', {
        action: 'taste_repair', html: j.html, issues: j.taste_audit.issues || []
      }, 58000);
      if (r && r.success && r.html && r.html.length > 500) {
        const ra = r.taste_audit || {};
        if (typeof ra.score !== 'number' || ra.score >= j.taste_audit.score) {
          j.html = r.html;
          j.taste_audit = ra;
          j.model = (j.model || 'AI') + '+polish';
        }
      }
    } catch (e) { /* polish is optional — keep original */ }
    return j;
  }

  /* OpenCode chunked lane (slow but sure) — final net for missing slots.
     Diversity: each missing slot tries a DIFFERENT free model. */
  async function generateOne(data, variation, mode, brief, slot, modelOverride) {
    const j = await post('/api/opencode.php', {
      action: 'generate_one', data, variation, mode, brief, taste: tastePrefs(), model: modelOverride || getModel(),
      slot: (typeof slot === 'number' ? slot : -1)
    }, 280000);
    if (j && j.success && j.html && j.html.length > 500) return j;
    throw new Error(((j && (j.errors || [])[0]) || (j && j.error) || 'OpenCode generate failed') + ' [' + variation + ']');
  }

  function shapeDesign(v, j, data, mode, brief, engine) {
    return {
      id: v, name: VAR_META[v].name, badge: VAR_META[v].badge + ' · ' + (j.model || 'AI'),
      description: VAR_META[v].description, html: j.html,
      meta: { bizName: data.biz_name, mode, engine, model: j.model, brief, taste: j.taste || null, tasteAudit: j.taste_audit || null, generatedAt: new Date().toISOString() }
    };
  }

  /* ── MAIN: 3 variations, fixed AI split, PARTIAL template fallback ──
     Slot 1-2 → Gemini lite, slot 3 → OpenCode (each with an AI backup
     lane, so a single-model outage still yields 3/3 AI).
     Diversity via temperature + layout brief + slot direction + taste dials.
     Partial fallback: 1-2 AI ready → return them, caller fills the rest
     with server templates by variation id. Throws only on 0/3.
     Bounded ≈ 100s (95s race + polish within budget). */
  async function generateConcepts(data, mode, options) {
    options = options || {};
    const onProgress = options.onProgress;
    const brief = await analyzeRequirements(data, onProgress);
    const t0 = Date.now();
    const secs = () => Math.round((Date.now() - t0) / 1000) + 's';
    if (onProgress) onProgress({
      stage: 'generating', pct: 38,
      message: 'AI crafting 3 variations in parallel…',
      completedCount: 0
    });

    const slots = [null, null, null];
    const errors = [];
    let done = 0, tok = 0;
    const tokStr = () => `· ~${(tok / 1000).toFixed(1)}k tokens`;
    const bump = (msg) => {
      done++;
      if (onProgress) onProgress({
        stage: 'generating', pct: 38 + Math.round((done / VARIATIONS.length) * 57),
        message: msg + ` (${secs()}) ${tokStr()}…`,
        completedCount: done
      });
    };

    // PARALLEL CONCURRENT slots (staggered by 250ms):
    // Runs all 3 variations concurrently so full generation completes
    // in ~22-28 seconds (guaranteed < 60 seconds).
    let laneDone = false;
    const pending = [];
    const lane1 = Promise.all(VARIATIONS.map(async (v, i) => {
      // Wider stagger when all slots share the OpenCode lane (Gemini down)
      // so the 3 parallel gens don't trip free-tier rate limits together.
      await new Promise(r => setTimeout(r, i * (service.geminiDown ? 2000 : 250)));
      try {
        const { j, engine } = await runSlotLanes(data, v, mode, brief, -1, lanesFor(i));
        if (laneDone) return;
        tok += (j.usage?.prompt || 0) + (j.usage?.completion || 0);
        pending.push({ v, j, engine, i });
        slots[i] = shapeDesign(v, j, data, mode, brief, engine);
        bump(`✓ ${done + 1}/3 variations ready (${j.model || 'AI'})`);
      } catch (e) { errors.push(v + ': ' + (e?.message || e)); }
    }));
    await Promise.race([
      lane1,
      new Promise(r => setTimeout(() => {
        if (!laneDone) errors.push('lane timeout 95s — proceeding with ready variations');
        r();
      }, 95000))
    ]);
    laneDone = true;
    // Concurrent polish batch (failing pages only, threshold 70, within budget).
    if (pending.length) {
      const needsPolish = pending.filter(p => p.j.taste_audit && typeof p.j.taste_audit.score === 'number' && p.j.taste_audit.score < 70);
      const elapsed = Date.now() - t0;
      if (needsPolish.length && elapsed < 38000) {
        if (onProgress) onProgress({ stage: 'generating', pct: 96, message: `✦ polishing ${needsPolish.length} page(s)…`, completedCount: done });
        await Promise.all(pending.map(async (p) => {
          const before = p.j.taste_audit ? p.j.taste_audit.score : '?';
          await polishIfNeeded(p.j, null);
          if (p.j.model && String(p.j.model).includes('+polish')) {
            slots[p.i] = shapeDesign(p.v, p.j, data, mode, brief, p.engine);
            bump(`✦ polished ${p.v} (taste ${before}→${p.j.taste_audit.score})`);
          }
        }));
      }
    }

    const designs = slots.filter(Boolean);
    // PARTIAL FALLBACK: 1-2 AI slots ready + rest failed/timed-out →
    // return what AI made; caller fills missing slots with server
    // templates (by variation id). Only 0/3 throws (full templates).
    if (designs.length === 0) {
      throw new Error('AI incomplete (0/3 ready: ' + errors.slice(0, 2).join(' | ') + '). Please retry — no templates used.');
    }
    const partial = designs.length !== VARIATIONS.length;
    if (onProgress) onProgress({ stage: 'done', pct: 100, completedCount: designs.length, message: partial ? `✓ ${designs.length}/3 AI ready — rest filled with templates (${secs()} ${tokStr()})` : `✓ ${designs.length}/3 AI variations ready in ${secs()} ${tokStr()}` });
    return { designs, brief, aiCount: designs.length, errors, tokens: tok, partial };
  }

  /* ── 3 AI LAYOUTS inside ONE selected variation ──
     Same fixed split as concepts (slots 1-2 Gemini, slot 3 OpenCode).
     Partial fallback: 1-2 AI layouts ready → return them (each carries
     its slot 0-2); caller fills the missing slot(s) with templates.
     Only 0/3 throws. */
  const SLOT_META = [
    { suffix: 'A', label: 'Layout A · Hero Focus' },
    { suffix: 'B', label: 'Layout B · Bento Showcase' },
    { suffix: 'C', label: 'Layout C · Authority Editorial' }
  ];
  async function generateSubDesigns(data, variation, mode, conceptNum, options) {
    options = options || {};
    const onProgress = options.onProgress;
    const brief = options.brief || await analyzeRequirements(data, onProgress);
    const t0 = Date.now();
    if (onProgress) onProgress({ stage: 'generating', pct: 40, completedCount: 0, message: `3-AI crafting 3 ${variation} layouts (each different)…` });
    const slots = [null, null, null];
    const errors = [];
    let done = 0, tok = 0;
    const tokStr = () => `· ~${(tok / 1000).toFixed(1)}k tokens`;
    // PARALLEL CONCURRENT layouts (staggered by 250ms):
    // Runs all 3 layout variants concurrently so completion stays under 30s.
    let subDone = false;
    const pendingSub = [];
    const subAll = Promise.all([0, 1, 2].map(async (s) => {
      await new Promise(r => setTimeout(r, s * (service.geminiDown ? 2000 : 250)));
      try {
        const { j, engine } = await runSlotLanes(data, variation, mode, brief, s, lanesFor(s));
        if (subDone) return;
        tok += (j.usage?.prompt || 0) + (j.usage?.completion || 0);
        pendingSub.push({ j, engine, s });
        slots[s] = {
          id: variation + '-' + SLOT_META[s].suffix,
          slot: s,
          name: `${conceptNum}${SLOT_META[s].suffix} · ${SLOT_META[s].label.replace('Layout ', '')} (${variation})`,
          badge: 'AI Layout ' + SLOT_META[s].suffix + ' · ' + (j.model || engine),
          description: `AI-generated ${SLOT_META[s].label} in ${variation} style.`,
          html: j.html,
          meta: { bizName: data.biz_name, mode, engine, model: j.model, brief, taste: j.taste || null, tasteAudit: j.taste_audit || null, generatedAt: new Date().toISOString() }
        };
      } catch (e) { errors.push('slot' + s + ': ' + (e?.message || e)); }
      if (subDone) return;
      done++;
      if (onProgress) onProgress({ stage: 'generating', pct: 40 + Math.round((done / 3) * 55), completedCount: done, message: `✓ ${done}/3 AI layouts ready (${Math.round((Date.now() - t0) / 1000)}s) ${tokStr()}…` });
    }));
    await Promise.race([
      subAll,
      new Promise(r => setTimeout(() => {
        if (!subDone) errors.push('layout lane timeout 95s — proceeding with ready layouts');
        r();
      }, 95000))
    ]);
    subDone = true;
    // Concurrent polish batch (failing pages only, within budget).
    if (pendingSub.length) {
      const elapsed = Date.now() - t0;
      if (elapsed < 38000) {
        await Promise.all(pendingSub.map(async (p) => {
          await polishIfNeeded(p.j, null);
          const cur = slots[p.s];
          if (cur && p.j.model && String(p.j.model).includes('+polish')) {
            cur.html = p.j.html;
            if (cur.meta) { cur.meta.model = p.j.model; cur.meta.tasteAudit = p.j.taste_audit || null; }
          }
        }));
      }
    }
    const designs = slots.filter(Boolean);
    // PARTIAL FALLBACK: return ready AI layouts (each has .slot 0-2);
    // caller fills missing slots with server templates. Only 0/3 throws.
    if (designs.length === 0) throw new Error('AI layouts incomplete (0/3 ready: ' + errors.slice(0, 2).join(' | ') + '). Please retry — no templates used.');
    const subPartial = designs.length !== 3;
    if (onProgress) onProgress({ stage: 'done', pct: 100, completedCount: designs.length, message: subPartial ? `✓ ${designs.length}/3 AI layouts ready — rest filled with templates ${tokStr()}` : `✓ ${designs.length}/3 AI layouts ready ${tokStr()}` });
    return { designs, brief, aiCount: designs.length, errors, tokens: tok, partial: subPartial };
  }

  /* ── Free-model dropdown (live Zen list, recommended first) ──
     OpenCode free models only — no Puter options. */
  function shortLabel(id, known, recommended) {
    const base = known ? known.label.replace(/ \(Free.*$/, '') : id;
    return (id === recommended ? '★ ' : '') + base + ' (Free)';
  }
  async function refreshModelDropdown() {
    let free = null, paid = [], recommended = null, benchNote = '';
    try {
      const j = await post('/api/opencode.php', { action: 'models' }, 20000);
      if (j && Array.isArray(j.free) && j.free.length) {
        free = j.free;
        paid = Array.isArray(j.paid) ? j.paid.filter(id => !j.free.includes(id)).slice(0, 12) : [];
        recommended = j.recommended || j.free[0];
        const bm = j.benchmark && Array.isArray(j.benchmark.models)
          ? j.benchmark.models.find(m => m.model === recommended) : null;
        if (bm && bm.ok) benchNote = `tested ✓ ${bm.secs}s`;
        else if (bm) benchNote = 'last test failed — auto fallback on';
      }
    } catch (e) { /* static fallback below */ }
    const ids = free || FREE_MODELS.map(m => m.id);
    const allIds = [...ids, ...paid.filter(id => !ids.includes(id))];
    recommended = ids.includes(recommended) ? recommended : ids[0];
    const cur = getModel();
    ['ai-model-select', 'wiz-model-select'].forEach(selId => {
      const sel = document.getElementById(selId);
      if (!sel) return;
      const keepVal = sel.value || cur;
      sel.innerHTML = '';
      const mkGroup = (label, list) => {
        if (!list.length) return null;
        const g = document.createElement('optgroup');
        g.label = label;
        list.forEach(id => {
          const known = FREE_MODELS.find(m => m.id === id);
          const o = document.createElement('option');
          o.value = id;
          const isPaid = paid.includes(id);
          o.textContent = isPaid ? `◆ ${id} (Paid · needs credits)` : shortLabel(id, known, recommended);
          g.appendChild(o);
        });
        return g;
      };
      const gFree = mkGroup('Free models', ids);
      const gPaid = mkGroup('Claude / GPT (paid · needs credits)', paid.filter(id => !ids.includes(id)));
      if (gFree) sel.appendChild(gFree);
      if (gPaid) sel.appendChild(gPaid);
      sel.value = allIds.includes(keepVal) ? keepVal : recommended;
    });
    if (!allIds.includes(cur)) setModel(recommended);
    else { service.selectedModel = cur; }
    const why = document.getElementById('ai-model-why') || document.getElementById('wiz-model-why') || document.getElementById('magic-model-why');
    if (why) {
      const rec = FREE_MODELS.find(m => m.id === recommended);
      const recName = rec ? rec.label.replace(/ \(Free.*$/, '') : recommended;
      why.textContent = `★ Recommended: ${recName}${benchNote ? ' — ' + benchNote : ''} · tap to change`;
    }
    if (typeof updateModelLabel === 'function') { try { updateModelLabel(); } catch (e) {} }
  }

  /* ── AI CHAT MODEL: gemini-3.5-flash-lite is the default ──
     Reads the AI-chat dropdown (builder #ai-model-select / studio
     #magic-model-select) or persisted `webcraft_ai_model`. */
  const AI_CHAT_DEFAULT = 'gemini-3.5-flash-lite';
  function getChatModel(explicit) {
    if (explicit && typeof explicit === 'string' && explicit.trim()) return explicit.trim();
    try {
      const b = document.getElementById('ai-model-select');
      if (b && b.value) return b.value;
      const s = document.getElementById('magic-model-select');
      if (s && s.value) return s.value;
      return localStorage.getItem('webcraft_ai_model') || AI_CHAT_DEFAULT;
    } catch (e) { return AI_CHAT_DEFAULT; }
  }

  /* ── AI CHAT EDIT (fast Gemini first, then OpenCode chunks) ── */
  async function chatAndEdit({ userPrompt, currentHtml = '', bizName = 'Website', model = null }) {
    const instruction = enrichTanglish(userPrompt);
    if (!instruction.trim()) return { conversation: 'Type or say something first.', isEdit: false, updatedHtml: '' };
    const chatModel = getChatModel(model);
    const isOpencodeOnly = (chatModel === 'opencode-fallback');
    if (!isOpencodeOnly) {
      try {
        const fj = await post('/api/opencode.php', {
          action: 'fast_edit', current_html: currentHtml, instruction, biz_name: bizName, taste: tastePrefs(), model: chatModel
        }, 35000);
        if (fj && fj.success && fj.html) {
          return {
            conversation: fj.response_msg || '✨ Applied your change.',
            isEdit: true, updatedHtml: fj.html, engine: 'gemini', model: fj.model || chatModel,
            tasteAudit: fj.taste_audit || null
          };
        }
      } catch (fe) { console.warn('[OpenCodeAI] fast edit failed, trying OpenCode:', fe?.message); }
    }
    const j = await post('/api/opencode.php', {
      action: 'edit', current_html: currentHtml, instruction, biz_name: bizName, taste: tastePrefs(), model: getModel()
    });
    if (j && j.success && j.html) {
      return {
        conversation: j.response_msg || '✨ OpenCode AI applied your change.',
        isEdit: true, updatedHtml: j.html, engine: 'opencode', model: j.model || getModel(),
        tasteAudit: j.taste_audit || null
      };
    }
    const err = new Error(((j && (j.errors || [])[0]) || (j && j.error) || 'OpenCode edit failed'));
    err.fallback = (j && j.fallback) || 'gemini-then-smart-engine';
    err.detail = j;
    throw err;
  }

  /* ── SNIPPET EDIT (selected element only — small I/O, fits free caps) ──
     First choice for selected-element micro-edits. Returns snippet HTML. */
  async function editSnippet({ userPrompt, selectedHtml = '', bizName = 'Website' }) {
    const instruction = enrichTanglish(userPrompt);
    if (!instruction.trim() || !selectedHtml.trim()) {
      throw new Error('Select an element on canvas first, then describe the change.');
    }
    const j = await post('/api/opencode.php', {
      action: 'edit_snippet', selected_html: selectedHtml.slice(0, 8000),
      instruction, biz_name: bizName, taste: tastePrefs(), model: getModel()
    }, 95000);
    if (j && j.success && j.html) {
      return {
        conversation: j.response_msg || '✨ OpenCode AI updated the selected element.',
        isEdit: true, updatedHtml: j.html, engine: 'opencode-snippet', model: j.model || getModel()
      };
    }
    const err = new Error(((j && (j.errors || [])[0]) || (j && j.error) || 'Snippet edit failed'));
    err.detail = j;
    throw err;
  }

  /* ── Combined edit chain for one-click UI use ──
     OpenCode free models → server Gemini/refine → smart template notice.
     (No Puter dependency.) */
  async function editWithFallback({ userPrompt, currentHtml = '', bizName = 'Website', model = null }) {
    const laneErrs = [];
    const chatModel = getChatModel(model);
    try {
      return await chatAndEdit({ userPrompt, currentHtml, bizName, model: chatModel });
    } catch (e1) {
      laneErrs.push(e1?.message || 'fast+opencode edit failed');
      console.warn('[OpenCodeAI] lane failed, trying Gemini refine:', e1?.message);
    }
    // Gemini / smart-engine lane (server)
    try {
      const j = await post('/api/generate.php', { action: 'refine', current_html: currentHtml, instruction: userPrompt, taste: tastePrefs(), model: chatModel });
      if (j && j.success && j.html) {
        return { conversation: j.response_msg || '✨ Applied via server AI.', isEdit: true, updatedHtml: j.html, engine: j.source || 'gemini', tasteAudit: j.taste_audit || null };
      }
      if (j && j.html && j.success === false) throw new Error(j.error || 'refine failed');
    } catch (e2) {
      laneErrs.push(e2?.message || 'refine failed');
      console.warn('[OpenCodeAI] refine lane failed:', e2?.message);
    }
    const err = new Error('All AI lanes failed (' + laneErrs.slice(0, 2).join(' | ').slice(0, 220) + '). Please retry, or try a built-in action (e.g. "Add a pricing table with 3 plans").');
    err.lanes = laneErrs;
    throw err;
  }

  const service = {
    FREE_MODELS, VARIATIONS, AI_CHAT_DEFAULT,
    selectedModel: getModel(),
    geminiDown: false,
    getModel, setModel, getModels, getChatModel,
    analyzeRequirements, generateOne, generateQuick, generateConcepts, generateSubDesigns,
    chatAndEdit, editSnippet, editWithFallback, enrichTanglish, refreshModelDropdown
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { try { refreshModelDropdown(); } catch (e) {} });
  } else {
    try { refreshModelDropdown(); } catch (e) {}
  }
  return service;
})();
