/* assets/js/generation-router.js — Centralized sequential generation router.
   Boundaries: analysis -> complexity -> routing -> design gen -> app gen.
   NEVER calls providers simultaneously: strict sequential fallback chain.
   FRONTEND CHAIN (all sizes, big projects with admin included):
     Space Bunny primary -> gemini-3.5-flash-lite fallback -> server templates.
   Small: 3 vars via Bunny -> Gemini -> templates.
   Large: 1 var via Space Bunny (landing) -> Gemini -> templates.
   Same validated spec across providers; frontend + backend stay connected
   via that spec. Failure preserves project data. */
window.GenerationRouter = (function () {
  'use strict';

  const VARIATIONS = ['classic', 'bold', 'editorial'];

  function withDesignSeed(data) {
    const seeded = Object.assign({}, data || {});
    if (!seeded.design_seed) {
      const bytes = new Uint8Array(12);
      if (window.crypto && window.crypto.getRandomValues) {
        window.crypto.getRandomValues(bytes);
        seeded.design_seed = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
      } else {
        seeded.design_seed = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
      }
    }
    return seeded;
  }

  function base() {
    try { if (typeof SITE_URL !== 'undefined' && SITE_URL) return SITE_URL; } catch (e) {}
    return '';
  }
  async function post(path, body, timeoutMs) {
    timeoutMs = timeoutMs || 115000;
    const ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    const timer = ctrl ? setTimeout(() => { try { ctrl.abort(); } catch (e) {} }, timeoutMs) : null;
    try {
      const r = await fetch(base() + path, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body), signal: ctrl ? ctrl.signal : undefined
      });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    } finally { if (timer) clearTimeout(timer); }
  }

  async function decide(data, orderId) {
    // Staged Gemini analysis takes ~15-25s; 60s budget so decide never
    // false-fails into templates on a merely slow analysis.
    const j = await post('/api/generate-route.php', { action: 'decide', data, order_id: orderId || '' }, 60000);
    if (!j || !j.success || !j.route) throw new Error((j && j.error) || 'routing failed');
    return j; // {spec, route, summary}
  }

  function validDesign(d) {
    const h = String((d && d.html) || '');
    if (h.length < 2000) return false;
    if (h.toLowerCase().indexOf('</html>') === -1) return false;
    const txt = h.replace(/<(script|style)[^>]*>[\s\S]*?<\/\1>/gi, '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (txt.length < 300) return false;
    if (/lorem ipsum/i.test(h)) return false;
    return true;
  }
  // Dedupe: full normalized HTML hash + visible-text signature. Two slots
  // with identical rendered text are the same variation even if markup
  // differs cosmetically — showing them twice is a FAILED set.
  function visibleSig(h) {
    try {
      return String(h || '').replace(/<(script|style)[^>]*>[\s\S]*?<\/\1>/gi, ' ')
        .replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 4000);
    } catch (e) { return ''; }
  }
  function dedupe(designs) {
    const seen = {}, seenTxt = {}, out = [];
    designs.forEach(d => {
      const norm = String(d.html || '').replace(/\s+/g, '');
      const h = md5short(norm);
      const t = md5short(visibleSig(d.html || ''));
      if (seen[h] || (t && seenTxt[t])) return;
      seen[h] = true; if (t) seenTxt[t] = true; out.push(d);
    });
    return out;
  }
  function md5short(s) {
    let h1 = 0xdeadbeef, h2 = 0x41c6ce57;
    for (let i = 0; i < s.length; i++) {
      const ch = s.charCodeAt(i);
      h1 = Math.imul(h1 ^ ch, 2654435761); h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);
    return (h2 >>> 0).toString(16) + (h1 >>> 0).toString(16);
  }

  function shape(v, j, data, mode, brief, engine, note) {
    const names = {
      classic: ['Concept 1 — Modern Minimal', 'Clean & Professional'],
      bold: ['Concept 2 — Bold Dynamic', 'High-Impact'],
      editorial: ['Concept 3 — Executive Luxury', 'Sleek Dark Glass']
    };
    const m = names[v] || [v, v];
    return {
      id: v, name: m[0], badge: m[1] + ' · ' + (j.model || 'AI'),
      description: 'Generated via ' + engine + (note ? ' (' + note + ')' : ''),
      html: j.html,
      meta: { bizName: data.biz_name, mode, engine, model: j.model, brief,
        tasteAudit: j.taste_audit || null, generatedAt: new Date().toISOString() }
    };
  }

  /* GEMINI single variation, bounded: max 2 tries, sequential. */
  async function geminiOne(data, variation, mode, brief, slot, temp) {
    const OC = window.OpenCodeAI;
    let lastErr = null;
    for (let a = 0; a < 2; a++) {
      try {
        const r = await OC.generateFast
          ? await (function () {
              // use lane primitive via runSlotLanes-equivalent single fast call
              return OC.generateFast(data, variation, mode, brief, slot, 'gemini-3.5-flash-lite', temp);
            })()
          : await Promise.reject(new Error('gemini lane missing'));
        if (r && r.html && validDesign({ html: r.html })) return { j: r, engine: 'gemini' };
        lastErr = new Error('gemini invalid output [' + variation + ']');
      } catch (e) { lastErr = e; }
      await new Promise(r => setTimeout(r, 1500));
    }
    throw lastErr || new Error('gemini failed [' + variation + ']');
  }

  /* SPACE BUNNY single variation (fallback for small / primary landing for large). */
  async function bunnyOne(data, variation, mode, brief, slot, temp, landingOnly) {
    const OC = window.OpenCodeAI;
    let lastErr = null;
    for (let a = 0; a < 2; a++) {
      try {
        let r;
        if (landingOnly && OC.enhanceLandingWithSpaceBunny && data._landingHtml) {
          r = await OC.enhanceLandingWithSpaceBunny(data._landingHtml, data, brief);
        } else {
          r = await OC.generateQuick(data, variation, mode, brief, slot, temp, 'space-bunny-free');
        }
        const html = r.html || (r.j && r.j.html) || '';
        if (html && validDesign({ html })) return { j: { html, model: r.model || 'space-bunny-free', taste_audit: r.taste_audit }, engine: 'spacebunny' };
        lastErr = new Error('spacebunny invalid output [' + variation + ']');
      } catch (e) { lastErr = e; }
      await new Promise(r => setTimeout(r, 2000));
    }
    throw lastErr || new Error('spacebunny failed [' + variation + ']');
  }

  /* FLOW A — small/medium: exactly 3, sequential Space Bunny -> Gemini -> templates.
     Frontend chain: Bunny primary, gemini-3.5-flash-lite fallback, server templates last. */
  async function generateSmall(data, mode, route, brief, onProgress) {
    data = withDesignSeed(data);
    const history = [];
    const prog = (pct, msg, extra) => { if (onProgress) onProgress(Object.assign({ stage: 'generating', pct, message: msg }, extra || {})); };
    const slots = [null, null, null];
    const temps = [0.7, 0.95, 0.6];
    // 1) PRIMARY: Space Bunny, strictly sequential (no simultaneous provider calls)
    for (let i = 0; i < 3; i++) {
      const v = VARIATIONS[i];
      prog(38 + Math.round((i / 3) * 40), 'Space Bunny crafting ' + v + ' (' + (i + 1) + '/3)…', { completedCount: i });
      try {
        const { j } = await bunnyOne(data, v, mode, brief, i, temps[i], false);
        slots[i] = shape(v, j, data, mode, brief, 'spacebunny');
        history.push({ event: 'bunny-ok', variation: v });
        prog(38 + Math.round(((i + 1) / 3) * 40), '✓ ' + v + ' ready (' + (i + 1) + '/3)', { completedCount: i + 1, variationIndex: i });
      } catch (e) {
        history.push({ event: 'bunny-fail', variation: v, error: String((e && e.message) || e).slice(0, 160) });
      }
    }
    // 2) FIRST FALLBACK: gemini-3.5-flash-lite only for missing slots, sequential
    for (let i = 0; i < 3; i++) {
      if (slots[i]) continue;
      const v = VARIATIONS[i];
      prog(80, 'Gemini fallback for ' + v + '…', { completedCount: slots.filter(Boolean).length });
      try {
        const { j } = await geminiOne(data, v, mode, brief, i, temps[i]);
        slots[i] = shape(v, j, data, mode, brief, 'gemini', 'fallback');
        history.push({ event: 'gemini-ok', variation: v });
      } catch (e) {
        history.push({ event: 'gemini-fail', variation: v, error: String((e && e.message) || e).slice(0, 160) });
      }
    }
    // 3) FINAL FALLBACK: server templates for still-missing slots (spec-driven)
    const missing = slots.map((s, i) => (s ? -1 : i)).filter(i => i >= 0);
    let tplUsed = 0;
    if (missing.length) {
      prog(90, 'Server templates for ' + missing.length + ' slot(s)…');
      try {
        const tpl = await window.AIFlowCraft.generateConcepts(data, mode, {});
        const byId = {};
        (tpl || []).forEach(t => { byId[t.id] = t; });
        missing.forEach(i => {
          const v = VARIATIONS[i];
          const t = (tpl || [])[i] || byId[v];
          if (t && t.html && validDesign(t)) {
            slots[i] = { id: v, name: t.name || v, badge: t.badge || 'Template', description: t.description || 'Template fallback honoring the same spec.',
              html: t.html, meta: { bizName: data.biz_name, mode, engine: 'template', model: 'server-template', brief, generatedAt: new Date().toISOString() } };
            tplUsed++;
            history.push({ event: 'template-ok', variation: v });
          }
        });
      } catch (e) { history.push({ event: 'template-fail', error: String((e && e.message) || e).slice(0, 160) }); }
    }
    let designs = dedupe(slots.filter(Boolean));
    if (designs.length !== 3) {
      const err = new Error('Generation incomplete (' + designs.length + '/3 valid). Project data preserved — please retry.');
      err.history = history; err.designs = designs;
      throw err;
    }
    prog(100, '✓ 3 variations ready [Space-Bunny-first routing]', { completedCount: 3 });
    return { designs, brief, history, providers: history.map(h => h.event),
      meta: { flow: 'A-small', primary: 'spacebunny', tplUsed } };
  }

  /* FLOW B — large: exactly 1 via Space Bunny landing -> Gemini -> templates.
     Landing integrates with app pages built by the existing system (spec.pages). */
  async function generateLarge(data, mode, route, brief, onProgress) {
    data = withDesignSeed(data);
    const history = [];
    const prog = (pct, msg, extra) => { if (onProgress) onProgress(Object.assign({ stage: 'generating', pct, message: msg }, extra || {})); };
    prog(40, 'Space Bunny generating landing page (1/1)…');
    let design = null;
    try {
      const { j } = await bunnyOne(data, 'classic', mode, brief + '\n[LARGE PROJECT: single landing page; app pages per spec follow.]', 0, 0.6, false);
      design = shape('classic', j, data, mode, brief, 'spacebunny', 'landing + app pages per spec');
      design.name = 'Flagship — Complete Application';
      design.meta.appPages = (route && route.appPages) || [];
      history.push({ event: 'bunny-ok', variation: 'classic' });
      prog(85, '✓ Landing ready — integrating app pages…', { completedCount: 1, variationIndex: 0 });
    } catch (e) {
      history.push({ event: 'bunny-fail', error: String((e && e.message) || e).slice(0, 160) });
      prog(55, 'Gemini fallback for single design…');
      try {
        const { j } = await geminiOne(data, 'classic', mode, brief, 0, 0.7);
        design = shape('classic', j, data, mode, brief, 'gemini', 'fallback single');
        design.name = 'Flagship — Complete Application';
        history.push({ event: 'gemini-ok', variation: 'classic' });
      } catch (e2) {
        history.push({ event: 'gemini-fail', error: String((e2 && e2.message) || e2).slice(0, 160) });
        prog(75, 'Server template fallback…');
        const tpl = await window.AIFlowCraft.generateConcepts(data, mode, {});
        const t = (tpl || [])[0];
        if (t && t.html && validDesign(t)) {
          design = { id: 'classic', name: 'Flagship — Complete Application', badge: t.badge || 'Template',
            description: 'Template starting design; full business logic per spec included.',
            html: t.html, meta: { bizName: data.biz_name, mode, engine: 'template', model: 'server-template', brief, generatedAt: new Date().toISOString() } };
          history.push({ event: 'template-ok', variation: 'classic' });
        } else {
          history.push({ event: 'template-fail' });
          const err = new Error('Large-project generation failed. Project data preserved — please retry.');
          err.history = history; throw err;
        }
      }
    }
    prog(100, '✓ 1 flagship design ready [Space-Bunny-first routing]', { completedCount: 1 });
    return { designs: [design], brief, history, meta: { flow: 'B-large', primary: 'spacebunny' } };
  }

  /* SPEC COMPLIANCE NOTE — appended to every provider brief so models emit
     the structural contract Verify checks: required section ids, wired forms,
     resolving anchors, viewport/title/lang. Deterministic server patch
     (comply action) backstops anything the model still misses. */
  function complianceNote(spec) {
    try {
      if (!spec || !spec.pages || !spec.pages[0]) return '';
      const need = ['hero', 'contact'];
      (spec.pages[0].sections || []).forEach(s => {
        const k = String(s || '').toLowerCase();
        if (k && need.indexOf(k) === -1) need.push(k);
      });
      const caps = spec.capabilities || [];
      const biz = ((spec.profile || {}).business_name || 'the business');
      let n = '\n[STRUCTURAL CONTRACT — must follow exactly]\n'
        + 'Required landing sections with EXACT ids: ' + need.slice(0, 8).map(k => 'id="' + k + '"').join(', ') + '.\n'
        + 'Every <form> must carry data-wc-form="booking|contact|newsletter" (pick by purpose), required attributes on mandatory inputs, and data-wc-thanks confirmation text.\n'
        + 'Every href="#..." must match an existing element id (no dead anchors).\n'
        + 'Include <html lang="en">, viewport meta, and a descriptive <title>.\n';
      if (caps.indexOf('booking') !== -1) n += 'Booking UI for ' + biz + ': search form data-wc-search="trips" + #trip-results target → steps as <div data-wc-step="seats|details|ticket" hidden>, every advance calls WCBiz.gotoStep(name) (show only the active stage and smooth-scroll below sticky navigation; NEVER location.hash/scrollTo(0,0)/<a href="#">) → seat grid #seat-map with WCBiz.hold → details form data-wc-form="booking" (held trip+seats auto-attached) → ticket #ticket-box with WCBiz.ticketQR(ref,180) (offline fallback built in) → pay buttons WCBiz.pay (counter|online) → history data-wc-history="bookings" + phone+ref lookup <div data-wc-ticket-lookup="bookings"></div>. NEVER hardcode fake trip rows like "Showing flexible options"; empty DB = "No matches found" state. WCBiz.list/hold/create/pay are ASYNC (Promise) — always await/.then, never const x=WCBiz.list() then x.map (throws, blank page); never invent booking refs (no GT-+random); ticket renders from the wc:booking-confirmed event detail (real BK- ref); NEVER overwrite [data-wc-history]/[data-wc-ticket-lookup] containers.\n';
      if (caps.indexOf('payments') !== -1) n += 'Payments run in configurable/offline-safe mode; never hardcode provider keys.\n';
      return n;
    } catch (e) { return ''; }
  }

  async function generateRouted(data, mode, options) {
    data = withDesignSeed(data);
    options = options || {};
    const onProgress = options.onProgress;
    let spec, route;
    if (options.spec && options.spec.schema === 'biz-spec/1') {
      // Approved blueprint is the single source of truth: assess only,
      // never rebuild (prevents requirement drift between steps).
      const j = await post('/api/generate-route.php', { action: 'assess',
        spec: options.spec, order_id: options.orderId || '' }, 30000);
      if (!j || !j.success || !j.route) throw new Error((j && j.error) || 'routing assess failed');
      spec = options.spec; route = j.route;
    } else {
      const decided = await decide(data, options.orderId || '');
      spec = decided.spec; route = decided.route;
    }
    const brief = ((spec.meta && spec.meta.ai_brief) || options.brief || '') + complianceNote(spec);
    if (onProgress) onProgress({ stage: 'analyzing', pct: 30,
      message: 'Spec validated · ' + route.complexity + ' (' + route.score + ') → ' + route.variations + ' variation(s) via ' + route.primary });
    let out;
    if (route.variations === 1) out = await generateLarge(data, mode, route, brief, onProgress);
    else out = await generateSmall(data, mode, route, brief, onProgress);
    out.spec = spec; out.route = route;
    return out;
  }

  return { decide, generateSmall, generateLarge, generateRouted, validDesign, complianceNote };
})();
