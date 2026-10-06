/**
 * ai-admin-generator.js
 * 
 * Bolt.new / Lovable style — lets AI write COMPLETE admin panel code.
 * No templates. AI generates every PHP file, SQL schema, and asset
 * based on the user's actual requirements.
 */

window.AIAdminGenerator = (function () {

  /* ─── The Master Prompt ─────────────────────────────── */
  function buildPrompt(opts) {
    const {
      bizName = 'Business',
      bizType = 'business',
      userRequirements = '',
      siteSummary = '',
      genMode = 'admin',        // admin | database
      existingHtml = ''
    } = opts;

    const dbLine = genMode === 'database'
      ? 'Generate a MySQL schema (CREATE TABLE ...) for every entity. Use PDO with prepared statements in every PHP file.'
      : 'Use a simple JSON file storage engine (data_<entity>.json) — no MySQL required. Still write clean PHP.';

    return `You are an expert senior full-stack PHP engineer embodying the AI-FLOWCRAFT 28-SKILL PIPELINE (Skills 3-21: Architecture, Data Models, MySQL DDL, REST APIs, Backend Standards, and TDD) and TASTE SKILL UI DESIGN. Your job is to write COMPLETE, PRODUCTION-READY full-stack admin panel code from scratch.

# AI-FLOWCRAFT FULL-STACK ARCHITECTURE
- Skill 3 (System Topology): Clean MVC separation — config, auth guards, controllers, entity views, and database persistence.
- Skill 5 (Data Conventions): snake_case columns, plural table names, created_at/updated_at timestamps.
- Skill 7 (Database Architect): ${genMode === 'database' ? 'Production MySQL DDL with InnoDB, utf8mb4, proper primary keys, foreign keys, and indexed query fields.' : 'Atomic transactional JSON datastore (data_<entity>.json) with LOCK_EX file protection.'}
- Skill 8 & 14 (API & Backend Standards): PDO prepared statements ALWAYS (never string concatenation), strict input sanitization (htmlspecialchars, filter_var), uniform JSON responses ({success:bool, data?:any, error?:string}).
- Skill 9 (TasteSkill Visual Spec): Premium dark interface (:root tokens, surface cards, single brand accent, WCAG AA contrast, clean typography, zero clutter).

# CONTEXT
Business: "${bizName}" (${bizType})
User's exact requirements:
"""
${userRequirements || 'Generate a sensible admin panel for this business type.'}
"""

# YOUR TASK
Design and write a complete, secure PHP admin panel satisfying the requirements following the AI-FlowCraft fullstack protocol and TasteSkill UI design.

# OUTPUT FORMAT
Respond with ONLY a single valid JSON object (no markdown fences, no explanation, no comments outside the JSON). Exact shape:

{
  "site_name": "${bizName}",
  "admin_title": "string — displayed in login page & dashboard",
  "entities": [
    {
      "id": "students",
      "name": "Students",
      "singular": "Student",
      "icon": "👨‍🎓",
      "fields": [
        {
          "name": "student_name",
          "label": "Student Name",
          "type": "text",
          "required": true,
          "in_list": true,
          "options": []
        }
      ]
    }
  ],
  "files": {
    "config.php": "<?php /* full code */ ",
    "includes/db.php": "<?php ...",
    "includes/auth.php": "<?php ...",
    "includes/header.php": "<?php ...",
    "includes/footer.php": "<?php ...",
    "login.php": "<?php ...",
    "logout.php": "<?php ...",
    "index.php": "<?php ... dashboard with card links to each entity ...",
    "students/list.php": "<?php ...",
    "students/add.php": "<?php ...",
    "students/edit.php": "<?php ...",
    "students/delete.php": "<?php ...",
    "students/save.php": "<?php ...",
    "teachers/list.php": "<?php ...",
    "teachers/add.php": "<?php ..."
  },
  "sql": "CREATE TABLE students (...); CREATE TABLE teachers (...);",
  "setup_notes": "Plain-English note for the customer about what was built."
}

# STRICT RULES — READ CAREFULLY

1. **Every single file must be complete, working PHP code.** No placeholders, no "TODO", no "...".

2. **Login system**: \`login.php\` reads credentials from a hash file (\`.auth.json\`) that the publish system writes. It checks \`password_verify(\$pw, \$storedHash)\`. On success: \`\$_SESSION['admin'] = true; header('Location: index.php');\`.

3. **Auth guard**: \`includes/auth.php\` — call \`requireAdmin()\` at the top of every protected page. It should redirect to \`login.php\` if \`empty(\$_SESSION['admin'])\`.

4. **DB layer** (${genMode === 'database' ? 'MySQL' : 'JSON file'}):
   ${genMode === 'database'
      ? '— \`includes/db.php\` uses PDO with config from config.php.\n   — Every query uses prepared statements.\n   — Never concatenate user input into SQL.'
      : '— \`includes/db.php\` provides helpers: load_json(\$entity), save_json(\$entity, \$rows).\n   — Data stored in data_<entity>.json files next to the admin folder.'}

5. **List pages**: Show a table with: search box, all fields marked in_list=true, Edit + Delete buttons per row, and a big "+ Add X" button at the top.

6. **Add / Edit pages**: Render a form with every field. Input type must match the field type (text→text, email→email, date→date, textarea→textarea, select→select with options, file→file upload). Show validation errors inline.

7. **Save pages**: Handle POST. Validate required fields. Insert or update. Redirect to list.php on success.

8. **Delete pages**: Read \`?id=\`, delete the row, redirect to list.

9. **Dashboard** (\`index.php\`): Greeting, "View Live Site" button, and a grid of cards — one per entity, showing the entity icon, name, and a record count. Each card links to that entity's \`list.php\`. **Only render the Requirement Builder feature when the admin is in Edit mode for a specific record (see Rule 16 — Section 18.5 Visibility Rule).**

10. **TasteSkill UI Styling**: Include all CSS inline inside \`includes/header.php\` (a <style> block). Follow TasteSkill design intelligence:
    - Root Design Tokens: Define :root CSS variables (--bg: #090d16, --surface: #111827, --surface-hover: #1f2937, --border: rgba(255,255,255,0.08), --primary: #6366f1, --radius: 14px).
    - Typography & Contrast: High-contrast legible text (WCAG AA >= 4.5:1), system-ui / Inter font stack.
    - Single Accent Rule: Lock one primary brand accent page-wide. No clashing neon colors.
    - Anti-Slop: Clean tables with subtle row hover, well-padded inputs (12px 16px), styled buttons with hover lift (translateY(-1px)), clean responsive layout (<768px collapses to single column).

11. **No external CDNs** (except optionally Google Fonts). Everything must work offline.

12. **Security**: htmlspecialchars() on all output, PDO prepared statements (for MySQL mode), session regeneration on login, no SQL injection, no XSS. **Server-side enforcement of Section 18.5 (Rule 16) visibility rules is mandatory — any API endpoint for the Requirement Builder must verify authenticated admin + edit context and reject all other requests with HTTP 403.**

13. **Icons**: Use emoji in the code for entity icons. Keep code ASCII-safe except emojis.

14. **Generate ONLY what the user explicitly asked for.** Read the requirements carefully. If they say "students, teachers, fees" → generate exactly those 3 entities. Do NOT add extra tables/sections like "attendance", "reports", "announcements" unless the user mentioned them. Do NOT invent generic CRUD modules the user didn't request. Every entity in your JSON must have a clear justification from the user's requirements text. If the requirements are vague (e.g., "make an admin for my restaurant"), infer only the most obvious sections (e.g., Menu Items, Orders, Reservations) — max 3–4 entities. Never pad with extra entities to seem more complete.

14b. **Match the admin title and theme to the business.** The admin panel color scheme, titles, icons, and language must reflect the actual business type and name. A school admin should say "Students", "Classes", "Fees" — not generic "Items", "Records". A restaurant admin should say "Menu", "Orders", "Tables". Always use business-appropriate terminology.

15. **Field names must be snake_case** in JSON but the label is whatever the user-friendly name is.

16. **SECTION 18.5 — VISIBILITY & DISPLAY RULE (CRITICAL — DO NOT SKIP):**
    The "Create Business Process / Function Requirement Builder" feature must be **conditionally rendered**. Show it ONLY when ALL of the following are true:
    - User is inside the **Admin Panel** (valid authenticated admin session).
    - Admin is in **Edit mode** — editing a specific existing record (e.g., \`edit.php?id=X\`).
    - Logged-in user has the **required admin role/permission** for this feature.

    **NEVER show** the Requirement Builder button, tab, modal, route, or any DOM element when:
    - Any non-admin (customer, staff, guest) is viewing the app.
    - Admin is in **View/read-only mode** — not editing.
    - Admin is on any page outside the Admin Panel.
    - User is on public site, customer dashboard, or any generated application UI.
    - Admin is on Dashboard or list views — only show **inside** a specific record's edit page.
    - Feature flags or permissions explicitly disable it.

    **Implementation rules to apply in the generated code:**
    - In \`includes/auth.php\`, write this guard: \`function canShowRequirementBuilder(string \$role, string \$mode, \$recordId): bool { return (\$role === 'admin' && \$mode === 'edit' && !empty(\$recordId)); }\`
    - In every \`edit.php\`: call \`canShowRequirementBuilder()\` — use a PHP \`if\` block to output the UI. Do NOT use CSS hide/show. The element must not exist in the DOM at all when the condition is false.
    - Server-side: any API endpoint for the Requirement Builder must call \`canShowRequirementBuilder()\` and return HTTP 403 if not in valid edit context. Never trust the client.
    - When switching Edit → View (save/redirect), the Requirement Builder must not appear on the destination page. Any unsaved draft must be written to a temp file before redirect — no data loss, no ghost components.
    - **All existing admin pages (list, view, dashboard, delete) must work exactly as before.** The Requirement Builder is purely additive and hidden outside edit mode.

17. **Existing Code Safety — Requirement Builder (from Section 18.5):** Do not alter the logic, layout, or behavior of existing View-mode admin pages (list.php, index.php, delete.php, view.php). The Requirement Builder code is additive — it only adds a guarded block inside edit.php files. All other files remain structurally unchanged.

# IMPORTANT
- Do NOT wrap the JSON in markdown.
- Do NOT add commentary.
- Do NOT abbreviate any file.
- Total files will be large — that's fine.
- If the user gave very specific fields (like "add student with name, class, roll no, parent phone"), use exactly those fields.
- If the user was vague ("school admin"), infer ONLY the most obvious 3–4 entities for that business. Do NOT pad with extra sections.
- **Never generate entities or sections the user did not explicitly ask for.** Verify each entity against the requirements text.
- **Match admin panel title, icons, and terminology to the actual business** — a bakery admin says "Products", "Orders"; a school says "Students", "Classes".
- Rules 16 and 17 are non-negotiable: always implement \`canShowRequirementBuilder()\` in \`includes/auth.php\` and always use it as a PHP guard in every generated \`edit.php\` file.

Begin your JSON response now:`;
  }

  /* ─── The main generator call ───────────────────────── */
  async function generate(opts, onProgress) {
    const prompt = buildPrompt(opts);
    const progress = typeof onProgress === 'function' ? onProgress : () => {};

    progress({ stage: 'thinking', message: 'AI is analyzing your requirements…' });

    let raw;
    try {
      // Try Puter (primary)
      if (window.puter && window.PuterService) {
        const model = window.PuterService.selectedModel || 'deepseek/deepseek-chat';
        progress({ stage: 'generating', message: `Generating admin panel with ${model.split('/').pop()}…` });
        raw = await window.PuterService.chat(prompt, { temperature: 0.3, max_tokens: 16000 });
      } else if (window.puter) {
        raw = await window.puter.ai.chat(prompt);
      } else {
        throw new Error('Puter.js not available');
      }
    } catch (err) {
      // Fallback: try backend Gemini
      progress({ stage: 'fallback', message: 'Trying backup AI…' });
      raw = await callBackendFallback(prompt);
    }

    progress({ stage: 'parsing', message: 'Parsing generated code…' });
    const parsed = parseStructuredJson(raw);
    if (!parsed || !parsed.files) {
      throw new Error('AI did not return valid code structure. Try again or rephrase requirements.');
    }

    progress({ stage: 'done', message: `✅ Generated ${Object.keys(parsed.files).length} files` });
    return parsed;
  }

  /* ─── Very robust JSON extractor ────────────────────── */
  function parseStructuredJson(raw) {
    if (!raw) return null;
    let text = typeof raw === 'string' ? raw : (raw.message?.content || raw.text || JSON.stringify(raw));

    // Strip markdown fences
    text = text.replace(/^```(?:json)?\s*/i, '').replace(/```\s*$/i, '').trim();

    // Direct parse
    try { return JSON.parse(text); } catch (_) {}

    // Find first { ... matching }
    const start = text.indexOf('{');
    if (start === -1) return null;
    let depth = 0, inStr = false, esc = false, end = -1;
    for (let i = start; i < text.length; i++) {
      const c = text[i];
      if (inStr) {
        if (esc) { esc = false; continue; }
        if (c === '\\') { esc = true; continue; }
        if (c === '"') inStr = false;
        continue;
      }
      if (c === '"') { inStr = true; continue; }
      if (c === '{') depth++;
      else if (c === '}') {
        depth--;
        if (depth === 0) { end = i; break; }
      }
    }
    if (end === -1) return null;
    try { return JSON.parse(text.slice(start, end + 1)); } catch (e) {
      console.warn('AIAdminGenerator: JSON parse failed', e);
      return null;
    }
  }

  /* ─── Backend Gemini fallback ───────────────────────── */
  async function callBackendFallback(prompt) {
    const res = await fetch('api/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'raw_chat',
        api_key: localStorage.getItem('gemini_api_key') || '',
        prompt
      })
    });
    if (!res.ok) throw new Error('Backend AI request failed');
    const j = await res.json();
    if (!j.success) throw new Error(j.error || 'Backend error');
    return j.text || j.response || '';
  }

  /* ─── Fix a specific file with AI ───────────────────── */
  async function fixFile(filePath, currentCode, issue, fullContext) {
    const prompt = `You are fixing a single PHP file. Return ONLY the corrected file content — no markdown, no explanation.

FILE PATH: ${filePath}
ISSUE: ${issue}

CONTEXT (other files in the project, for reference):
${JSON.stringify(fullContext?.files ? Object.keys(fullContext.files) : [], null, 2)}

CURRENT CODE:
${currentCode}

Return the fixed file now:`;

    let fixed;
    if (window.puter) fixed = await window.puter.ai.chat(prompt);
    else fixed = await callBackendFallback(prompt);

    // Strip fences
    fixed = String(fixed).replace(/^```(?:php|html|js)?\s*/i, '').replace(/```\s*$/i, '').trim();
    return fixed;
  }

  /* ─── Preview helper: build file tree ───────────────── */
  function buildFileTree(files) {
    const root = { name: '', path: '', children: {}, isFile: false, content: null };
    Object.keys(files).forEach(path => {
      const parts = path.split('/');
      let node = root;
      parts.forEach((part, i) => {
        const isLast = i === parts.length - 1;
        if (!node.children[part]) {
          node.children[part] = {
            name: part,
            path: parts.slice(0, i + 1).join('/'),
            children: {},
            isFile: isLast,
            content: isLast ? files[path] : null
          };
        }
        node = node.children[part];
      });
    });
    return root;
  }

  /* ─── Public API ────────────────────────────────────── */
  return {
    generate,
    fixFile,
    buildFileTree,
    parseStructuredJson
  };
})();