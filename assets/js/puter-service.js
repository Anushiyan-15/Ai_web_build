/* ═══════════════════════════════════════════════════════════════
   assets/js/puter-service.js
   Puter.js AI service — chat, edit, Tanglish, voice-aware prompts
   ═══════════════════════════════════════════════════════════════ */
window.PuterService = (function () {
  'use strict';

  const KEY_MODEL = 'webcraft_puter_model';

  const MODELS = {
    'gpt-4o-mini':            { label: 'GPT-4o Mini (⚡ Ultra-Fast & Free)', free: true  },
    'deepseek/deepseek-chat': { label: 'DeepSeek V3 (Free)',              free: true  },
    'gemini-2.0-flash':       { label: 'Gemini 2.0 Flash (Free)',         free: true  },
    'claude-3-5-sonnet':      { label: 'Claude 3.5 Sonnet (Paid Credits)', free: false },
    'gpt-4o':                 { label: 'GPT-4o (Paid Credits)',           free: false }
  };

  /* ═══════════ TANGLISH SYSTEM ADDENDUM ═══════════ */
  const TANGLISH = `
## LANGUAGE SUPPORT
You understand Tanglish (Tamil written in English letters), English, and Tamil script.
When the user writes in Tanglish, reply in the SAME Tanglish style, but keep all
code, class names, IDs, and HTML attributes in English.

Mandatory examples:
- "hero section color maathu"        → change hero section color
- "button periya aakku"              → make button bigger
- "footer la WhatsApp add pannu"     → add WhatsApp to footer
- "pricing table remove pannu"       → remove the pricing table
- "site azhaga dark mode la kaattu"  → render site beautifully in dark mode
- "kizha oru newsletter section add pannu" → add a newsletter section at the bottom
- "menu bar fix pannu top la"        → fix the menu bar to the top
- "hero image replace pannu"         → replace the hero image

Never refuse a Tanglish request. If unsure, ask ONE clarifying question in Tanglish.
`.trim();

  /* ═══════════ BASE SYSTEM PROMPT ═══════════ */
  const SYSTEM = `You are WebCraft AI — an expert full-stack web developer inside a live editor.

## YOUR JOB
Two modes:
A) **CHAT** — user asks a question / wants advice → reply conversationally. No HTML edit.
B) **EDIT** — user requests a change → return the updated HTML snippet.

## EDIT RULES
1. Return ONLY the modified HTML when editing. No markdown fences, no commentary around the code.
2. Change ONLY what the user asked. Never touch unrelated sections.
3. Preserve existing class names, IDs, colors, fonts unless asked to change them.
4. If asked to add a section, return ONLY the new section as clean HTML.
5. Keep the site responsive — mobile-first.
6. Never invent placeholder text — reuse real content from context.

## WHEN TO EDIT vs CHAT
- Edit if: action verbs (add / change / remove / make / replace / move / rewrite / style / color / bigger / smaller)
- Chat if: question words (what / how / why / which / when / can you explain)
- If genuinely ambiguous, ask ONE short clarifying question.

${TANGLISH}

## STUDIO CONTEXT
The user is editing a live website in a Canva-style studio. Some elements may
have data-anim, data-mobile-id, data-section-name attributes — preserve them.
`.trim();

  /* ═══════════ INTERNAL STATE ═══════════ */
  let _user = null;
  let _signedIn = false;
  let _listenersWired = false;

  function puterReady() {
    if (!window.puter || !window.puter.ai) {
      throw new Error('Puter.js not loaded — include https://js.puter.com/v2/ before this script.');
    }
  }

  /* ═══════════ AUTH ═══════════ */
  async function isSignedIn() {
    try {
      puterReady();
      if (window.puter.auth?.isSignedIn) return !!(await puter.auth.isSignedIn());
      const u = await puter.auth.getUser();
      return !!u;
    } catch { return false; }
  }

  async function getUser() {
    try {
      puterReady();
      if (window.puter.auth?.getUser) return await puter.auth.getUser();
      return null;
    } catch { return null; }
  }

  async function signIn() {
    puterReady();
    if (window.puter.auth?.signIn) {
      await puter.auth.signIn();
    }
    _signedIn = await isSignedIn();
    _user = await getUser();
    dispatchAuthChange();
  }

  async function signOut() {
    try {
      puterReady();
      if (window.puter.auth?.signOut) await puter.auth.signOut();
    } catch (e) { console.warn('[PuterService] signOut', e); }
    _signedIn = false;
    _user = null;
    dispatchAuthChange();
  }

  async function switchAccount() {
    try {
      puterReady();
      if (window.puter.auth?.signOut) await puter.auth.signOut();
    } catch (e) { /* ignore */ }
    _signedIn = false;
    _user = null;
    dispatchAuthChange();
    setTimeout(() => signIn().catch(() => {}), 100);
  }

  function openSignUp() {
    try {
      puterReady();
      if (window.puter.auth?.signUp) puter.auth.signUp();
      else if (window.puter.auth?.signIn) puter.auth.signIn();
    } catch (e) { console.warn(e); }
  }

  function dispatchAuthChange() {
    window.dispatchEvent(new CustomEvent('puter-auth-changed', {
      detail: { isSignedIn: _signedIn, user: _user }
    }));
  }

  function wireAuthListeners() {
    if (_listenersWired) return;
    _listenersWired = true;
    if (window.puter?.auth?.onAuthStateChanged) {
      try {
        puter.auth.onAuthStateChanged(async () => {
          _signedIn = await isSignedIn();
          _user = await getUser();
          dispatchAuthChange();
        });
      } catch (e) { /* older Puter builds */ }
    }
    setTimeout(async () => {
      _signedIn = await isSignedIn();
      _user = await getUser();
      dispatchAuthChange();
    }, 200);
  }

  /* ═══════════ ERROR CLASSIFIER ═══════════ */
  function isQuotaOrCreditError(err) {
    const m = String(err?.message || err?.error?.message || err || '').toLowerCase();
    return /credit|quota|limit|usage|402|429|payment/.test(m);
  }

  /* ═══════════ MODEL ═══════════ */
  function setModel(m) {
    if (!MODELS[m]) return;
    service.selectedModel = m;
    try { localStorage.setItem(KEY_MODEL, m); } catch (e) {}
  }

  function getModel() {
    return service.selectedModel || localStorage.getItem(KEY_MODEL) || 'deepseek/deepseek-chat';
  }

  /* ═══════════ CORE: chatAndEdit ═══════════ */
  async function chatAndEdit({ userPrompt, selectedElement = null, currentHtml = '', context = {} }) {
    puterReady();
    if (!userPrompt || !userPrompt.trim()) {
      return { conversation: 'Type or say something first.', isEdit: false, updatedHtml: '' };
    }

    /* ── Build the context block ── */
    const lines = [];
    lines.push(`### CONTEXT`);
    lines.push(`Business: ${context.bizName || 'Website'}`);
    if (context.summary) {
      lines.push(`Section list:`);
      try {
        const nodes = context.summary.nodes || [];
        const topSections = nodes.filter(n => n.tag === 'section').slice(0, 12);
        topSections.forEach((s, i) => {
          lines.push(`  ${i + 1}. ${s.section_name || s.id || 'section'} (${s.tag}${s.classes ? '.' + s.classes.split(' ').slice(0, 2).join('.') : ''})`);
        });
      } catch (e) {}
    }
    lines.push(`### END CONTEXT`);

    /* ── Build user message ── */
    let userMessage;
    if (selectedElement && selectedElement.html) {
      userMessage = `
${lines.join('\n')}

### SELECTED ELEMENT TO EDIT
\`\`\`
${selectedElement.html.slice(0, 6000)}
\`\`\`

### USER REQUEST
${userPrompt}

If this is an edit request, return ONLY the updated HTML for the selected element.
If this is a question, return conversational text only.
      `.trim();
    } else {
      const snippet = (currentHtml || '').slice(0, 8000);
      userMessage = `
${lines.join('\n')}

### CURRENT PAGE (truncated)
\`\`\`html
${snippet}
\`\`\`

### USER REQUEST
${userPrompt}

If this is an edit request, return ONLY the new HTML to add/change (a full section
or a full-page replacement). If it's a question, answer conversationally.
      `.trim();
    }

    /* ── Call Puter AI ── */
    const messages = [
      { role: 'system', content: SYSTEM },
      { role: 'user',   content: userMessage }
    ];

    let raw = '';
    try {
      const model = getModel();
      const res = await puter.ai.chat(messages, { model, temperature: 0.7 });
      raw = extractText(res);
    } catch (err) {
      const msg = String(err?.message || '').toLowerCase();
      if (/model|not found|unavailable/.test(msg) && getModel() !== 'deepseek/deepseek-chat') {
        setModel('deepseek/deepseek-chat');
        const retry = await puter.ai.chat(messages, { model: getModel(), temperature: 0.7 });
        raw = extractText(retry);
      } else {
        throw err;
      }
    }

    return classifyResponse(raw);
  }

  function extractText(res) {
    if (res == null) return '';
    if (typeof res === 'string') return res;
    if (res?.message?.content) return String(res.message.content);
    if (res?.text) return String(res.text);
    if (res?.response) return String(res.response);
    if (res?.result) return String(res.result);
    return String(res);
  }

  /* ═══════════ RESPONSE PARSER ═══════════ */
  function classifyResponse(raw) {
    const txt = String(raw || '').trim();
    if (!txt) return { conversation: '(empty response)', isEdit: false, updatedHtml: '' };

    const fence = txt.match(/```(?:html)?\s*([\s\S]*?)```/i);
    if (fence && looksLikeHtml(fence[1])) {
      return { conversation: stripCode(txt), isEdit: true, updatedHtml: fence[1].trim() };
    }

    if (looksLikeHtml(txt)) {
      return { conversation: '✨ Updated the website with your changes.', isEdit: true, updatedHtml: txt };
    }

    return { conversation: txt, isEdit: false, updatedHtml: '' };
  }

  function looksLikeHtml(s) {
    const t = String(s || '').trim();
    if (!t || t.length < 20) return false;
    if (/^<(section|div|header|footer|main|nav|article|aside|h[1-6]|p|button|a|img|form|ul|ol|table|span|html|!DOCTYPE)/i.test(t)) return true;
    const tagCount = (t.match(/<[a-z][^>]*>/gi) || []).length;
    return tagCount > 8;
  }

  function stripCode(s) {
    return String(s).replace(/```[\s\S]*?```/g, '').trim() || '✨ Done.';
  }

  /* ═══════════ PUBLIC API ═══════════ */
  const service = {
    MODELS,
    selectedModel: localStorage.getItem(KEY_MODEL) || 'gpt-4o-mini',
    isSignedIn,
    getUser,
    signIn,
    signOut,
    switchAccount,
    openSignUp,
    chatAndEdit,
    isQuotaOrCreditError,
    setModel,
    getModel
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireAuthListeners);
  } else {
    wireAuthListeners();
  }

  return service;
})();