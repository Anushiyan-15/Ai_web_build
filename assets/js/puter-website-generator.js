/* ═══════════════════════════════════════════════════════════════
   assets/js/puter-website-generator.js
   3-variation website generator — mode is LOCKED, no asking
   ═══════════════════════════════════════════════════════════════ */
window.WebsiteGenerator = (function () {
  'use strict';

  const KEY_PROJECT  = 'webcraft_saved_project';
  const KEY_PROJECTS = 'webcraft_generated_projects';

  // Per-customer scope — one email sees ONLY its own projects
  function scopedKey(base) {
    try {
      const em = (window.__CUSTOMER__ && window.__CUSTOMER__.email) || '';
      return em ? base + '::' + String(em).toLowerCase() : base;
    } catch (e) { return base; }
  }

  /* ═══════════════════════════════════════════════════
     THREE STYLE VARIATIONS
     Content stays the SAME across all three.
     Only the LAYOUT approach differs.
     ═══════════════════════════════════════════════════ */
  const VARIATIONS = [
    {
      id: 'classic',
      name: 'Classic Corporate',
      badge: 'Traditional',
      description: 'Centered, balanced, professional. Best for established brands.',
      layoutBrief: `
- Hero: centered stack — kicker, large headline, subhead, two CTAs side-by-side.
- Services: 3-column grid with icon-on-top cards, 1px borders, subtle shadows.
- About: split-screen (text left, image right).
- Metrics: horizontal 4-column band with dividers.
- Contact: 2-column (info left, form right) inside a bordered card.
- Rounded corners: 8–10px. Shadows: soft. Whitespace: balanced.
- Section order: Hero → Services → About → Metrics → Contact → Footer`.trim()
    },
    {
      id: 'bold',
      name: 'Bold & Dynamic',
      badge: 'Contemporary',
      description: 'Oversized typography, gradient energy, high contrast.',
      layoutBrief: `
- Hero: full-bleed — oversized clamp(3rem, 8vw, 6rem) headline, gradient text on key word,
  one large CTA, subtle animated background glow.
- Services: asymmetric bento grid (1 large card + 2 smaller).
- About: full-width dark band with inline stats and pull quote.
- Metrics: gradient-filled counter cards with big numbers.
- Contact: floating glassmorphism card over a gradient backdrop.
- Rounded corners: 20–28px. Shadows: glowing. Whitespace: dense.
- Section order: Hero → Metrics → Services → About → Contact → Footer`.trim()
    },
    {
      id: 'editorial',
      name: 'Minimal Editorial',
      badge: 'Refined',
      description: 'Magazine feel, typography-first, generous whitespace.',
      layoutBrief: `
- Hero: magazine — small serif kicker line, large serif headline,
  one subtle underlined link-CTA. No button fill.
- Services: alternating 2-column rows (text ↔ image), NO cards.
- About: narrow centered column (max 720px) with pull quotes and a portrait.
- Metrics: thin inline text ribbon — numbers in bold, labels in small-caps. No boxes.
- Contact: minimal — email + phone in large type, small inline form.
- Rounded corners: 0–4px. Shadows: none. Whitespace: generous.
- Section order: Hero → About → Services → Metrics → Contact → Footer`.trim()
    }
  ];

  /* ═══════════════════════════════════════════════════
     SYSTEM PROMPT — strict, no questions allowed
     ═══════════════════════════════════════════════════ */
  const GEN_SYSTEM = `You are a RAW HTML CODE GENERATOR — nothing else.

## HARD RULES (VIOLATION = FAILURE)
1. NEVER chat. NEVER ask questions. NEVER explain.
2. NEVER ask "what type of website", "static or admin or database",
   "would you like", "do you want", "let me know", "please specify".
3. The user has ALREADY chosen the mode. It is written in the prompt under
   "GENERATION MODE". Read it. Follow it. Do not ask about it.
4. Your ONLY output is one complete HTML document.
5. Start with: <!DOCTYPE html>
6. End with: </html>
7. No markdown fences (no \`\`\`html). No prose before or after.
8. Use CLIENT REQUIREMENTS verbatim. If "(unspecified)", invent a default — do NOT ask.

## FORBIDDEN PHRASES (never appear in your response)
- "what type of website"
- "would you like"
- "do you want"
- "please specify"
- "let me know"
- "Sure,"
- "Here is"
- "I can help"
- "Which option"
- "static or admin"
- "with or without database"

## MANDATORY CHECKLIST
- [ ] Response starts with <!DOCTYPE html>
- [ ] Response ends with </html>
- [ ] Contains <head>, <style>, <body>, <script>
- [ ] Uses exact business name + tagline from requirements
- [ ] Follows the GENERATION MODE written in the prompt
- [ ] Zero forbidden phrases

## PREMIUM DESIGN MANDATE (every variant must feel expensive)
- Smooth scrolling: html{scroll-behavior:smooth} + anchor nav links + back-to-top button.
- Sticky GLASSMORPHISM navbar: rgba white/dark fill, backdrop-filter:blur(16px),
  hairline border-bottom, shrinks + gains shadow on scroll.
- GLASS CARDS for features/testimonials/pricing: rgba(255,255,255,.08) fill over
  gradient-mesh section backgrounds, blur(18px), 1px rgba(255,255,255,.25) border,
  20-24px radius, soft layered shadows.
- CLAYMORPHISM for CTAs + stat counters + icon badges: chunky rounded shapes
  (18-26px radius), pastel/brand fills, 3-4px bottom darker border, big soft outer
  shadow + white inner top highlight (inset 0 2px 0 rgba(255,255,255,.5)),
  playful press-down :active effect.
- Hero: layered gradient mesh / subtle grid pattern, oversized display typography
  (clamp), gradient accent word, dual CTA (one clay, one glass), trust badges row.
- Scroll-reveal animations: IntersectionObserver adds .revealed (fade + translateY),
  staggered delays per card. Respect prefers-reduced-motion.
- Micro-interactions: buttons lift + glow on hover, cards tilt/scale subtly,
  image zoom on hover, animated counters, marquee ribbon where fitting.
- Generous whitespace, max-width 1200px containers, 8px spacing rhythm,
  consistent radius + shadow system, section eyebrow labels + dividers.
- Fully responsive: fluid clamp() type, hamburger menu under 860px,
  grids collapse gracefully, tap targets 44px+.
- Zero emojis in UI chrome (use inline SVG icons). Every major section (hero,
  about, services) includes at least one real photo via
  https://images.unsplash.com/photo-XXXX?auto=format&fit=crop&w=1200&q=70
  (never grey placeholder boxes) with descriptive alt text.

## ZENITH STANDARD (the reference quality bar — match it or beat it)
- Design tokens first: :root with --bg, --panel (rgba white .045), --line,
  --txt, --muted, --accent + --accent-2/3, --grad (115deg accent trio),
  --radius system (20/24/28px), --glow shadows, --max:1240px.
- Typography: display font (Archivo/Space Grotesk/Sora, 900, -.035em tracking,
  .98 line-height) + body font (Inter, 17px, 1.6). Never default system look.
- Atmosphere: fixed body::before with 2-3 huge soft radial glows in brand hues.
- Glass nav: fixed, blur(18px), transparent → solid on .scrolled, pill links,
  glowing gradient CTA with lift hover.
- Buttons: gradient fill + colored glow shadow + translateY(-2px) hover.
  No flat default buttons anywhere.
- Cards/panels: rgba white .045 fills, 1px rgba white .10 borders, 24px radius.
- Every section: eyebrow kicker + huge display heading + generous padding
  (100px+ desktop), max-width container, alternating rhythm.

## CONTACT FORM CONTRACT (must be followed exactly)
- Exactly one contact form with id="contact-form", method="POST", no action.
- Fields: name (required), email type=email (required), phone (optional),
  message textarea (required), submit button.
- No fake "message sent" JavaScript — the host platform wires real sending.

## MAP + LOCATION CONTRACT (must be followed exactly)
- If a Location is given in requirements (never "(unspecified)" check — if a real
  place is named, use it; otherwise use the business city/area), include a
  "Find Us" block inside the contact section:
  - <iframe> map embed, loading="lazy", title="Map — business location",
    src="https://www.google.com/maps?q=URL_ENCODED_ADDRESS&output=embed"
    (no API key needed), wrapped in a rounded (20px) shadowed frame.
  - A "Get Directions" clay button linking to
    "https://www.google.com/maps/dir/?api=1&destination=URL_ENCODED_ADDRESS"
    target="_blank" rel="noopener".
  - Clicking the map/button opens full Google Maps with directions to the address.
- Show the raw address text + phone + email beside the map.

If ANY checkbox fails, fix it BEFORE responding. Output only the fixed HTML.`;

  /* ═══════════════════════════════════════════════════
     HELPERS
     ═══════════════════════════════════════════════════ */
  function puterReady() {
    if (!window.puter?.ai) throw new Error('Puter.js not loaded');
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

  function cleanHtml(raw) {
    let s = String(raw || '').trim();
    // Strip markdown code fences
    s = s.replace(/^```(?:html)?\s*/i, '').replace(/```\s*$/i, '');
    
    // Cut off leading text before <!DOCTYPE or <html
    const dt = s.search(/<!DOCTYPE|<html/i);
    if (dt > 0) s = s.slice(dt);

    // CRITICAL: Cut off any trailing markdown, commentary or text after the final </html>
    const closeIdx = s.toLowerCase().lastIndexOf('</html>');
    if (closeIdx !== -1) {
      s = s.slice(0, closeIdx + 7);
    }

    // CRITICAL: Strip any raw PHP code blocks or artifacts that may leak
    s = s.replace(/<\?php[\s\S]*?(?:\?>|$)/gi, '');
    s = s.replace(/\?>/g, '');
    s = s.replace(/TORAGE,\s*0755[\s\S]*?;\s*}/gi, '');

    // CRITICAL: Strip any "PHP not detected" banners, alerts or text
    s = s.replace(/<[^>]*>[^<]*php\s*not\s*detect[^<]*<\/[^>]*>/gi, '');
    s = s.replace(/alert\s*\(\s*['"][^'"]*php\s*not\s*detect[^'"]*['"]\s*\);?/gi, '');

    return s.trim();
  }

  function dispatchProgress(cb, p) {
    if (typeof cb === 'function') {
      try { cb(p); } catch (e) { console.warn(e); }
    }
  }

  /* ═══════════════════════════════════════════════════
     REFUSAL / HTML DETECTION
     ═══════════════════════════════════════════════════ */
  function looksLikeRefusal(s) {
    const t = String(s || '').toLowerCase().slice(0, 800);
    return /(what type of website|static or admin|admin or database|with admin or without|with or without database|would you like|do you want|please (specify|clarify|provide)|let me know|here is a (plan|suggestion)|sure[,!.]|of course[,!.]|which option|can you (please )?(provide|share|clarify)|i('| a)?m unable|i cannot|i can'?t)/.test(t);
  }

  function looksLikeCompleteHtml(s) {
    if (!s || s.length < 200) return false;
    const hasDoctype = /<!DOCTYPE\s+html/i.test(s) || /<html[\s>]/i.test(s);
    const hasHead    = /<head[\s>]/i.test(s);
    const hasBody    = /<body[\s>]/i.test(s);
    const hasClose   = /<\/html>/i.test(s);
    return hasDoctype && hasHead && hasBody && hasClose;
  }

  /* ═══════════════════════════════════════════════════
     CALL PUTER AI — messages form, then string fallback
     ═══════════════════════════════════════════════════ */
  async function callAI(system, user, model = 'deepseek/deepseek-chat', temperature = 0.7) {
    puterReady();

    // Attempt 1: messages-array form
    try {
      const res = await puter.ai.chat(
        [
          { role: 'system', content: system },
          { role: 'user',   content: user }
        ],
        { model, temperature }
      );
      return extractText(res);
    } catch (e) {
      console.warn('[AI] messages-array failed, falling back to combined prompt:', e?.message);
    }

    // Attempt 2: combined-string form
    const combined = `${system}\n\n---\n\n${user}`;
    const res2 = await puter.ai.chat(combined, { model, temperature });
    return extractText(res2);
  }

  /* ═══════════════════════════════════════════════════
     CALL AI FOR HTML — with retries + validation
     ═══════════════════════════════════════════════════ */
  async function callAIForHtml(system, user, model, maxRetries = 2) {
    let lastRaw = '';
    let currentUser = user;

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
      const temp = attempt === 0 ? 0.7 : 0.25;
      const raw = await callAI(system, currentUser, model, temp);
      lastRaw = raw;

      console.groupCollapsed(`🎨 [gen] attempt ${attempt + 1} — ${raw.length} chars`);
      console.log(raw.slice(0, 400) + (raw.length > 400 ? '…' : ''));
      console.groupEnd();

      // Refusal / chatty reply → retry with a stronger nudge
      if (looksLikeRefusal(raw) && !looksLikeCompleteHtml(raw)) {
        console.warn('[gen] AI responded conversationally — retrying with stricter prompt');
        currentUser = user + `\n\n⚠️ REMINDER: Your previous reply was: "${raw.slice(0, 80)}…"
That is NOT acceptable. The mode is ALREADY chosen in the prompt.
Output ONLY the raw HTML file NOW, starting with <!DOCTYPE html>.
No questions. No words before or after.`;
        continue;
      }

      const html = cleanHtml(raw);
      if (looksLikeCompleteHtml(html)) return html;

      console.warn('[gen] HTML incomplete — retrying',
        '| has <html>:', /<html[\s>]/i.test(html),
        '| has </html>:', /<\/html>/i.test(html));
      currentUser = user + `\n\n⚠️ Your previous output was incomplete.
Output the COMPLETE HTML file from <!DOCTYPE html> to </html>. Do not truncate.`;
    }

    throw new Error(
      'AI did not return a complete HTML document after 3 attempts. ' +
      'Try switching model (e.g. DeepSeek V3) or click Generate again.\n' +
      'Last response started with: "' + (lastRaw || '').slice(0, 120) + '…"'
    );
  }

  /* ═══════════════════════════════════════════════════
     REQUIREMENTS BLOCK — single source of truth
     ═══════════════════════════════════════════════════ */
  function buildRequirementsBlock(d) {
    const sections = (Array.isArray(d.sections) && d.sections.length)
      ? d.sections.join(', ')
      : 'hero, services, about, metrics, contact, footer';

    const styleCards = {
      modern: 'Modern & Clean — minimal, airy, flat, contemporary sans-serif.',
      bold:   'Bold & Dynamic — high contrast, oversized type, gradients, animated.',
      dark:   'Luxury & Dark — deep backgrounds, gold/violet accents, elegant, cinematic.'
    };
    const styleDescription = styleCards[d.design_style] || d.design_style || 'modern';
    const extraDirection = (d.design_direction || '').trim();

    return `
=== CLIENT REQUIREMENTS (MUST BE FOLLOWED EXACTLY) ===
Business Name      : ${d.biz_name}
Industry / Niche   : ${d.biz_type}
Core Mission       : ${d.biz_tagline}
Target Audience    : ${d.biz_audience || '(unspecified)'}
Services / Products: ${d.biz_services || '(unspecified)'}
Primary Design Dir : ${styleDescription}
${extraDirection ? `Additional Notes   : ${extraDirection}` : ''}
Brand Color Accent : ${d.color_palette}  (map to a real hex: purple #6366f1, blue #2563eb, green #059669, red #dc2626, gold #d97706, slate #334155)
Contact Email      : ${d.biz_email   || '(unspecified)'}
Contact Phone      : ${d.biz_phone   || '(unspecified)'}
Location           : ${d.biz_address || '(unspecified)'}
Required Sections  : ${sections}
=== END CLIENT REQUIREMENTS ===`.trim();
  }

  /* ═══════════════════════════════════════════════════
     BUILD PUBLIC-SITE PROMPT — mode is LOCKED at the top
     ═══════════════════════════════════════════════════ */
  function buildSitePrompt(requirementsBlock, variation, mode, d) {
    // ✅ Mode is fixed — AI has zero decision to make here
    const MODE_LABEL = {
      'static':   'Static Website (HTML/CSS/JS only)',
      'admin':    'Website + Admin Panel + PHP Backend (JSON storage, NO database)',
      'database': 'Website + Admin Panel + PHP Backend + MySQL Database (full stack)'
    }[mode] || mode;

    const MODE_WHAT_TO_OUTPUT = {
      'static':   'Output a single self-contained HTML file. No PHP. No admin. No database.',
      'admin':    'Output the PUBLIC WEBSITE HTML only. The admin panel is generated separately — do NOT include it here.',
      'database': 'Output the PUBLIC WEBSITE HTML only. Admin + PHP + MySQL are generated separately — do NOT include them here.'
    }[mode];

    return `
## TASK
Generate the complete, production-ready HTML file for the "${variation.name}" variant
of the website described below.

## GENERATION MODE — ALREADY DECIDED BY THE USER (DO NOT ASK)
Type: **${MODE_LABEL}**
${MODE_WHAT_TO_OUTPUT}

⚠️ The mode has ALREADY been chosen. NEVER ask "what type of website" — it is: **${MODE_LABEL}**.

## CRITICAL OUTPUT RULES
- Output ONLY raw HTML. No chat, no questions, no markdown, no explanation.
- First line: <!DOCTYPE html>
- Last line: </html>
- NEVER write: "Sure", "Here is", "What kind of", "Would you like", "Let me know",
  "Do you want static/admin/database", "Please specify".
- Use CLIENT REQUIREMENTS verbatim. If a field is "(unspecified)", invent a
  professional default — DO NOT ask the user.

## HIGH-PREMIUM REAL UI (this is what wins customers — all mandatory)
- Real 3D depth: hero has 2-3 parallax layers (data-depth + mousemove translate),
  service cards TILT in 3D toward the cursor (perspective + rotateX/rotateY JS),
  team/pricing cards flip or lift with deep shadows. No flat dead cards.
- Motion everywhere: branded preloader (fades out on load), scroll-reveal
  (IntersectionObserver, staggered), animated counters, infinite marquee ribbon,
  magnetic buttons (translate toward cursor), gradient text animation,
  testimonial auto-slider with dots, FAQ accordion, gallery lightbox,
  pricing monthly/yearly toggle, working mobile drawer menu, sticky glass
  header that condenses on scroll, back-to-top button, live form validation
  states (green/red hints) on the contact form.
- Zero dead controls: EVERY button/link must do something real (smooth-scroll
  to a section, open/close a modal or drawer, toggle content, submit the form).
  No href="#" dead ends — point them at real section ids.

${requirementsBlock}

## LAYOUT DIRECTION — "${variation.name}"
${variation.layoutBrief}

## NOW OUTPUT THE HTML FILE
Start with <!DOCTYPE html> immediately. No preamble, no questions.
`.trim();
  }

  /* ═══════════════════════════════════════════════════
     BUILD ADMIN PROMPT — mode is LOCKED
     ═══════════════════════════════════════════════════ */
  function buildAdminPrompt(requirementsBlock, variation, mode, d) {
    const MODE_LABEL = mode === 'database'
      ? 'Admin + Database (PHP + MySQL)'
      : 'Admin only (PHP + JSON storage, NO database)';

    const STORAGE = mode === 'database'
      ? 'Persist data via PHP + MySQL. Assume a `data` table with JSON columns exists.'
      : 'Persist data via JSON files on the server. Do NOT use MySQL.';

    return `
## TASK
Generate the complete ADMIN PANEL HTML file for the "${variation.name}" variant.

## MODE — ALREADY DECIDED (DO NOT ASK)
Type: **${MODE_LABEL}**
Storage: ${STORAGE}

⚠️ NEVER ask "do you want database or not". The mode is: **${MODE_LABEL}**.

## CRITICAL OUTPUT RULES
- Output ONLY raw HTML. No chat, no questions, no markdown.
- Start with <!DOCTYPE html>. End with </html>.
- NEVER write "Sure", "Here is", "Would you like", "Let me know", "Please specify".

${requirementsBlock}

## ADMIN PANEL REQUIREMENTS
- Self-contained HTML + inline CSS + inline JS.
- Left sidebar: Dashboard, Pages, Sections, Media, Settings, Logout.
- Login form (username + password).
- Dashboard: stats cards + recent activity.
- Sections editor with inline text fields.
- Media library grid.
- ${STORAGE}
- Dark modern UI using brand accent color.
- Responsive (sidebar collapses on mobile).
- Self-contained HTML + inline CSS + JS (client-side interactive preview).
- Zero PHP tags (do NOT output <?php). Use purely client-side JavaScript for preview interactivity and mock state.
- CRITICAL: NO PHP checks, NO "PHP not detected" or "offline mode" banners or alerts. The admin interface is an interactive preview driven by client-side JavaScript with realistic mock data.

## NOW OUTPUT THE ADMIN HTML FILE
Start with <!DOCTYPE html> immediately. No preamble.
`.trim();
  }

  /* ═══════════════════════════════════════════════════
     BUILD PHP BACKEND + SQL SCHEMA (database mode)
     ═══════════════════════════════════════════════════ */
  function buildPhpBackend(d, variationId) {
    return `<?php
/**
 * Auto-generated PHP backend for "${d.biz_name}" (${variationId})
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

switch ($action) {
    case 'login':
        $u = $_POST['username'] ?? '';
        $p = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
        $stmt->execute([$u]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($p, $row['password_hash'])) {
            session_start();
            $_SESSION['admin_id'] = $row['id'];
            echo json_encode(['success' => true]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
        break;

    case 'saveSection':
        $id    = $_POST['section_id'] ?? '';
        $html  = $_POST['html']       ?? '';
        $stmt  = $pdo->prepare("INSERT INTO sections (section_id, html) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE html = VALUES(html)");
        $stmt->execute([$id, $html]);
        echo json_encode(['success' => true]);
        break;

    case 'getStats':
        $stats = $pdo->query("SELECT COUNT(*) AS sections FROM sections")->fetch(PDO::FETCH_ASSOC);
        echo json_encode($stats);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
`;
  }

  function buildSqlSchema(d) {
    const slug = String(d.biz_name || 'site').toLowerCase().replace(/[^a-z0-9]+/g, '_');
    return `-- Auto-generated schema for "${d.biz_name}"
CREATE DATABASE IF NOT EXISTS \`${slug}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE \`${slug}\`;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sections (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id  VARCHAR(64) UNIQUE NOT NULL,
  html        LONGTEXT,
  updated_at  DATETIME ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS media (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename    VARCHAR(255),
  mime_type   VARCHAR(64),
  size_bytes  INT UNSIGNED,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed default admin (password = "changeme")
INSERT IGNORE INTO admins (username, password_hash)
VALUES ('admin', '$2y$10$e0NRz1Fz7Lb3FfVfLJp1ZeDLqEjZk3lqk4F6Xn6yJ6pWz8QhA3Kq2');
`;
  }

  /* ═══════════════════════════════════════════════════
     MAIN: generateConcepts(data, mode, options)
     ═══════════════════════════════════════════════════ */
  async function generateConcepts(data, mode, options = {}) {
    puterReady();
    const { onProgress, model = 'deepseek/deepseek-chat' } = options;

    const requirementsBlock = buildRequirementsBlock(data);

    console.group(`🎨 [Safe Sequential Generation] 3 variations · mode = ${mode}`);
    console.log('Business:', data.biz_name);
    console.groupEnd();

    dispatchProgress(onProgress, {
      stage: 'generating',
      message: 'Synthesizing 3 style variations…',
      pct: 10,
      completedCount: 0
    });

    const concepts = [];

    // Safe sequential loop — runs 1 request at a time, avoiding Puter concurrency 402/429 limits completely!
    for (let i = 0; i < VARIATIONS.length; i++) {
      const v = VARIATIONS[i];
      const startPct = 10 + Math.round((i / VARIATIONS.length) * 85);

      dispatchProgress(onProgress, {
        stage: 'generating',
        message: `Crafting variation ${i + 1}/3: "${v.name}"…`,
        pct: startPct,
        variationIndex: i,
        completedCount: i
      });

      /* ── Public site HTML ── */
      const sitePrompt = buildSitePrompt(requirementsBlock, v, mode, data);
      const rawSiteHtml = await callAIForHtml(GEN_SYSTEM, sitePrompt, model);
      const siteHtml = cleanHtml(rawSiteHtml);

      /* ── PHP + SQL (database only) ── */
      const phpBackend = (mode === 'database') ? buildPhpBackend(data, v.id) : null;
      const sqlSchema  = (mode === 'database') ? buildSqlSchema(data)        : null;

      concepts.push({
        id: v.id,
        name: v.name,
        badge: v.badge,
        description: v.description,
        html: siteHtml,
        adminHtml: null, // Generated client-side for zero latency and zero credit burn
        phpBackend,
        sqlSchema,
        meta: {
          bizName: data.biz_name,
          style: data.design_style,
          direction: data.design_direction || '',
          palette: data.color_palette,
          sectionsIncluded: Array.isArray(data.sections) ? data.sections.slice() : [],
          mode,
          layoutVariant: v.id,
          generatedAt: new Date().toISOString()
        }
      });

      const donePct = 10 + Math.round(((i + 1) / VARIATIONS.length) * 85);
      dispatchProgress(onProgress, {
        stage: 'generating',
        message: `✓ Variation ${i + 1}/3 "${v.name}" ready!`,
        pct: donePct,
        variationIndex: i,
        completedCount: i + 1
      });
    }

    dispatchProgress(onProgress, { stage: 'done', message: '✓ All 3 variations ready!', pct: 100 });
    return concepts;
  }

  /* ═══════════════════════════════════════════════════
     AUTH HELPERS
     ═══════════════════════════════════════════════════ */
  async function getAuthState() {
    if (window.PuterService) {
      const isSignedIn = await window.PuterService.isSignedIn();
      const user = await window.PuterService.getUser();
      return { isSignedIn, user };
    }
    try {
      const u = await puter.auth.getUser();
      return { isSignedIn: !!u, user: u };
    } catch { return { isSignedIn: false, user: null }; }
  }

  async function signIn()        { if (window.PuterService) return window.PuterService.signIn(); }
  async function switchAccount() { if (window.PuterService) return window.PuterService.switchAccount(); }
  function signOut()             { if (window.PuterService) return window.PuterService.signOut(); }

  function clearLocalState() {
    try {
      localStorage.removeItem(KEY_PROJECT);
      localStorage.removeItem(scopedKey(KEY_PROJECT));
      localStorage.removeItem('webcraft_user_uploads');
      localStorage.removeItem('webcraft_lang_state');
      localStorage.removeItem('webcraft_admin_assets');
      localStorage.removeItem('webcraft_puter_model');
      localStorage.removeItem('webcraft_puter_chat');
      localStorage.removeItem('gemini_api_key');
    } catch (e) {}
  }

  function clearGeneratedProjects() {
    try {
      localStorage.removeItem(KEY_PROJECTS);
      localStorage.removeItem(scopedKey(KEY_PROJECTS));
    } catch (e) {}
  }

  function saveGeneratedProject(project) {
    try {
      const kList = scopedKey(KEY_PROJECTS);
      const kProj = scopedKey(KEY_PROJECT);
      const list = JSON.parse(localStorage.getItem(kList) || '[]');
      list.unshift({ ...project, savedAt: new Date().toISOString() });
      localStorage.setItem(kList, JSON.stringify(list.slice(0, 20)));
      localStorage.setItem(kProj, JSON.stringify(project));
    } catch (e) { console.warn('save project failed', e); }
  }

  async function saveToPuterCloud(data) {
    try {
      if (!window.puter?.fs?.write) return null;
      const path = `~/WebCraft/projects/${Date.now()}.json`;
      await puter.fs.write(path, JSON.stringify(data));
      return path;
    } catch (e) {
      console.warn('cloud save failed', e);
      return null;
    }
  }

  function isQuotaOrCreditError(err) {
    if (window.PuterService) return window.PuterService.isQuotaOrCreditError(err);
    const m = String(err?.message || err || '').toLowerCase();
    return /credit|quota|limit|402|429|payment/.test(m);
  }

  return {
    VARIATIONS,
    generateConcepts,
    getAuthState,
    signIn,
    signOut,
    switchAccount,
    clearLocalState,
    clearGeneratedProjects,
    saveGeneratedProject,
    saveToPuterCloud,
    isQuotaOrCreditError
  };
})();