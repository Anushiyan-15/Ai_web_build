<?php
// ═══════════════════════════════════════════════════════════════
//  includes/GeminiService.php — Google Gemini FAST lane
//
//  gemini-3.5-flash-lite: verified 1.4s tiny / full pages ~10-20s.
//  (gemini-2.x retired 404; gemini-3.8-flash overloaded ~32s.)
//  Single-shot full pages fit Apache's 30s guillotine — no chunking.
//  Prompts + validators reused from OpenCodeService (compact system).
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/OpenCodeService.php';
require_once __DIR__ . '/TasteSkill.php';
require_once __DIR__ . '/FlowCraftService.php';

function gemini_fast_models(): array {
    // Single-model policy (user request): ONLY gemini-3.5-flash-lite.
    // Verified working 2026-10-05 probe (full page ~22s, fits Apache 30s).
    // No fallback to other Gemini models — keeps output consistent and
    // lets the prompt training target exactly one model's behaviour.
    return ['gemini-3.5-flash-lite'];
}

function gemini_api_key(): string {
    $k = defined('GEMINI_API_KEY') ? (string)GEMINI_API_KEY : '';
    if ($k === '' || stripos($k, 'YOUR_') !== false) return '';
    return trim($k);
}

// Single Gemini call. Returns [ok, text, err|null, usage[]]
function gemini_chat_once(string $model, string $system, string $user, float $temperature = 0.7, int $maxTokens = 8000, int $timeout = 28): array {
    $key = gemini_api_key();
    if ($key === '') return [false, '', 'Gemini API key not configured', ['prompt' => 0, 'completion' => 0]];
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($key);
    $payload = [
        'system_instruction' => ['parts' => [['text' => $system]]],
        'contents' => [['parts' => [['text' => $user]]]],
        'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens],
    ];
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    // Log transport failures (timeouts/dns) — otherwise invisible in logs
    // and indistinguishable from model errors during diagnosis.
    if ($curlErr !== '') {
        error_log('[GeminiService] model=' . $model . ' TRANSPORT err=' . substr($curlErr, 0, 160));
        return [false, '', 'Network error: ' . $curlErr, ['prompt' => 0, 'completion' => 0]];
    }
    $j = json_decode((string)$res, true);
    $usage = ['prompt' => 0, 'completion' => 0];
    if (isset($j['usageMetadata'])) {
        $usage = ['prompt' => (int)($j['usageMetadata']['promptTokenCount'] ?? 0),
                  'completion' => (int)($j['usageMetadata']['candidatesTokenCount'] ?? 0)];
    }
    $text = $j['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($code === 200 && trim((string)$text) !== '') return [true, (string)$text, null, $usage];
    $err = '';
    if (is_array($j) && isset($j['error'])) $err = (string)(($j['error']['message'] ?? '') ?: json_encode($j['error']));
    if ($err === '') $err = 'HTTP ' . $code;
    error_log('[GeminiService] model=' . $model . ' code=' . $code . ' err=' . substr($err, 0, 200));
    return [false, '', $model . ': ' . $err, $usage];
}

// ── AI-FlowCraft Skill Context (Claude-style distilled version) ─────
// High-signal only: what actually improves single-file HTML output.
// (The full 28-skill planning backend stays server-side; the model only
// needs the frontend execution contract — long planning lists burn prompt
// tokens and shrink the output budget, lowering quality.)
function gemini_flowcraft_skill_context(): string {
    return "## BUILD CONTRACT (senior dev + QA in one pass)\n"
        . "- Think like Claude: plan the sections first (nav → hero → trust → services → showcase → metrics → testimonials → faq → contact → footer → shop-if-needed), then write final code once, cleanly.\n"
        . "- Design tokens in :root before any component. Shared classes (.btn .card .section .grid) — never repeat the same 10-line style block.\n"
        . "- States: nav scrolled / drawer open / reveal visible / FAQ open / form sending+sent / cart open+empty+filled. Every state must look intentional.\n"
        . "- QA before output: valid HTML nesting, all ids unique, all hrefs resolve, all images have alt, contrast AA, keyboard reachable, no JS null-errors, responsive <768px single column, reduced-motion fallback present.\n"
        . "- Copy: use requirement words verbatim for names/prices/contacts; invent the rest sharply (no lorem, no clichés, no fake stats).\n\n";
}

// ── ECOMMERCE TRAINING for gemini-3.5-flash-lite (single-model policy) ──
// Lite models under-generate shop logic unless taught explicitly: exact
// section ids, exact button classes, quantity rules, cart math, currency.
// SPEED + RELIABILITY: the model ships ONLY the #shop grid + stepper
// contract (items 1-3). The backend ALWAYS injects the guaranteed working
// cart drawer + bill modal + JS (injectShopIfMissing) when Shop Mode is ON
// — so the model must NOT waste output budget on drawer/bill/cart-JS.
// That keeps shop pages inside the token budget (~10k) and under the
// Apache/JS timeouts instead of truncating to "AI generation failed".
function gemini_shop_training_block(array $data): string {
    $raw = trim((string)($data['biz_products'] ?? ''));
    if ($raw === '' && stripos((string)($data['biz_type'] ?? ''), 'shop') === false
        && !in_array('shop', array_map('strtolower', (array)($data['sections'] ?? [])), true)
        && !preg_match('/shop|store|product|retail|e-?commerce|boutique|mart|fashion|jewelry|grocery|bakery|furniture|electronics/i', (string)($data['biz_type'] ?? ''))) {
        return '';
    }
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));
    $prodList = $lines ? implode("\n", array_slice($lines, 0, 12)) : '(none listed — use 6 plausible products for this business with real prices)';
    return "## ECOMMERCE CONTRACT — SHOP MODE ON (gemini-3.5-flash-lite training)\n"
        . "This is a SHOPPING site. A broken/absent product grid = FAILED output.\n"
        . "PRODUCTS (name + price verbatim, never invent prices when given):\n{$prodList}\n"
        . "Build ONLY the product grid in THIS html file (backend injects the cart automatically — do NOT build it yourself):\n"
        . "1) <section id=\"shop\"> AFTER about/services, BEFORE contact/footer: heading 'Shop Our Products', responsive grid (auto-fill minmax 260px).\n"
        . "2) ONE product card per PRODUCT line: <img> (real Unsplash, alt=name), <h3>name</h3>, price div (exact label e.g. Rs.499 — unpriced 'Ask price' items get an 'Enquire →' link to #contact instead of cart buttons, never a Rs.0 button).\n"
        . "3) QUANTITY STEPPER on every priced card (exact classes — the injected cart engine binds to these): "
        . "<button type=\"button\" class=\"wc-card-dec\" data-idx=\"N\">−</button> "
        . "<span class=\"wc-card-qty\" data-idx=\"N\">1</span> "
        . "<button type=\"button\" class=\"wc-card-inc\" data-idx=\"N\">+</button> "
        . "<button type=\"button\" class=\"wc-add-btn\" data-idx=\"N\">Add to Cart</button> "
        . "where N = 0-based product index. Minus never goes below 1 on the card.\n"
        . "4) DO NOT BUILD: cart drawer (#wc-cart-drawer), bill/invoice modal (#wc-bill-modal), floating cart button (#wc-fab), header cart button, or cart <script> — the backend injects all of these with working localStorage + totals + WhatsApp checkout. Building your own duplicate cart wastes output budget and causes truncation = FAILED output.\n"
        . "5) RULES: prices verbatim (no currency swaps); nav link <a href=\"#shop\">Shop</a>; stepper/add buttons type=\"button\" (never submit); mobile single-column <768px; 44px+ tap targets; tight CSS (shared classes).\n\n";
}

// ── GEMINI-FIRST brief: the analyze step used to ride the slow OpenCode
// free-model lane (28s timeout, sequential fallback). Lite writes the same
// brief shape in ~2-3s. Returns [ok, brief, modelUsed|null, errors[], usage].
// On failure returns an instant template brief (ok=false, never fatal).
function gemini_template_brief(array $data): string {
    $biz = trim((string)($data['biz_name'] ?? 'Apex Studio'));
    $type = trim((string)($data['biz_type'] ?? 'Business'));
    $aud = trim((string)($data['biz_audience'] ?? 'Modern clients'));
    $secs = (!empty($data['sections']) && is_array($data['sections'])) ? implode(', ', $data['sections']) : 'hero, services, about, metrics, contact, footer';
    return "AUDIENCE: {$aud} seeking premium {$type} services\nSECTIONS: {$secs}\nPALETTE: brand-mapped primary/secondary/accent\nTYPE: Plus Jakarta Sans display + Inter body\nDIFFERENTIATORS: glassmorphism nav; clay CTAs; scroll-reveal + counters\nPRODUCTS: " . (trim((string)($data['biz_products'] ?? '')) !== '' ? 'see Shop Products in requirements' : 'NONE') . "\nADMIN_ENTITIES: NONE\nRISKS: avoid generic stock look for {$biz}";
}

function gemini_analyze_requirements(array $data): array {
    $req = opencode_requirements_block($data);
    [$ok, $text, $err, $u] = gemini_chat_once(
        'gemini-3.5-flash-lite', opencode_analyze_system(), $req, 0.5, 800, 12);
    if ($ok && strlen(trim($text)) > 50) {
        return [true, trim($text), 'gemini-3.5-flash-lite', [], $u];
    }
    return [false, gemini_template_brief($data), null,
        [$err ?: 'brief empty'], ['prompt' => 0, 'completion' => 0]];
}

// ── FAST-LANE response cache: repeat generations return instantly ──
// Keyed on canonical data + variation + mode + slot + brief (24h TTL).
// Saves ~25-70s when the user regenerates the same site / re-clicks.
function gen_cache_dir(): string {
    $base = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/../storage');
    $dir = rtrim($base, '/\\') . '/gen_cache';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function gen_cache_key(array $data, string $variation, string $mode, int $slot, string $brief): string {
    $canon = $data;
    ksort($canon);
    return 'fast_' . md5(json_encode([$canon, $variation, $mode, $slot, $brief], JSON_UNESCAPED_UNICODE));
}

function gen_cache_get(string $key): ?array {
    $f = gen_cache_dir() . '/' . preg_replace('/[^a-z0-9_]/i', '', $key) . '.json';
    if (!is_file($f) || (time() - filemtime($f) > 86400)) return null;
    $j = json_decode(@file_get_contents($f), true);
    return (is_array($j) && isset($j['html'])) ? $j : null;
}

function gen_cache_put(string $key, array $payload): void {
    $f = gen_cache_dir() . '/' . preg_replace('/[^a-z0-9_]/i', '', $key) . '.json';
    @file_put_contents($f, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ── FAST: one premium variation, single shot (fits 30s) ────────
// 3-AI diversity: each variation/slot starts on a DIFFERENT Gemini model
// + different temperature, so 3 designs look/think differently.
// Returns [ok, html, modelUsed|null, errors[], usage]
function gemini_generate_one(array $data, string $variationId, string $mode, string $brief, int $slot = -1, ?string $preferred = null, ?float $temperature = null): array {
    $modeLabel = ['static' => 'Static Website (HTML/CSS/JS only)', 'admin' => 'Website + Admin Panel + PHP Backend (JSON storage, NO database)', 'database' => 'Website + Admin Panel + PHP Backend + MySQL Database (full stack)'][$mode] ?? $mode;
    $shopTraining = gemini_shop_training_block($data);
    $isShop = ($shopTraining !== '');
    // Shop-aware output budget: slim grid-only contract fits ~12k, but give
    // headroom so 6-product grids never truncate to "AI generation failed".
    // Non-shop pages stay lean for speed. Lite supports 32k; 16k is safe.
    $outTokens = $isShop ? 16000 : 12000;
    $user = "## TASK\nGenerate the complete, production-ready HTML file for the \"{$variationId}\" variant.\n\n"
        . "## GENERATION MODE — ALREADY DECIDED (DO NOT ASK)\nType: **{$modeLabel}**\nOutput the PUBLIC WEBSITE HTML only.\n\n"
        . "## AI DESIGN BRIEF (follow tightly)\n{$brief}\n\n"
        . "## CRITICAL OUTPUT RULES\n- Output ONLY raw HTML. No chat, no questions, no markdown.\n- First line: <!DOCTYPE html> / Last line: </html>\n\n"
        . opencode_requirements_block($data) . "\n\n"
        . "## LAYOUT DIRECTION — \"{$variationId}\"\n" . opencode_layout_brief($variationId, $slot) . "\n\n"
        . opencode_palette_block((string)($data['color_palette'] ?? 'purple')) . "\n\n"
        . taste_prompt_block($data, $variationId, $slot) . "\n\n"
        . $shopTraining
        . gemini_flowcraft_skill_context()
        . flowcraft_fullstack_prompt_block($mode, $data)
        . ($isShop
            ? "## OUTPUT BUDGET (SHOP — GRID ONLY)\n- Complete but efficient: all required sections + #shop grid + stepper, working nav/form/menu, responsive CSS.\n- DO NOT write cart drawer / bill modal / cart <script> — backend injects them. This is how you fit the budget.\n- Tight CSS (shared classes, no repetition, no filler). If the budget runs out, end cleanly after the last FULL section.\n\n## NOW OUTPUT THE HTML FILE"
            : "## OUTPUT BUDGET\n- Complete but efficient: all required sections, working nav/form/menu, responsive CSS.\n- Tight CSS (shared classes, no repetition, no filler). If the budget runs out, end cleanly after the last FULL section.\n\n## NOW OUTPUT THE HTML FILE");
    // Single-model policy: lite only. Diversity comes from temperature
    // + layout brief + slot, not from model rotation.
    // Diversity temperature: bold = wild, classic = balanced, editorial = precise
    if ($temperature === null) {
        $temps = ['classic' => 0.7, 'bold' => 0.95, 'editorial' => 0.55];
        $temperature = $temps[$variationId] ?? 0.75;
        if ($slot >= 0) $temperature = min(1.2, $temperature + $slot * 0.1);
    }
    $temperature = max(0.1, min(1.2, (float)$temperature));
    // Two attempts on the SAME model: full temp, then calmer retry.
    // (A retry with different temp often succeeds where the first cut off.)
    $queue = ['gemini-3.5-flash-lite'];
    if ($preferred && $preferred !== 'gemini-3.5-flash-lite') {
        // Honour explicit override if caller really passes one, else lite.
        array_unshift($queue, $preferred);
    }
    $errors = [];
    $usageTotal = ['prompt' => 0, 'completion' => 0];
    $attempt = 0;
    foreach ($queue as $model) {
        // 55s per attempt: full pages take ~25-45s; shop grids need the headroom.
        // Fits Apache 60s + frontend 75s budgets on the FIRST attempt so the
        // retry below only fires on fast API errors, not on healthy slowness.
        $tryTemp = $attempt === 0 ? $temperature : max(0.1, $temperature - 0.25);
        [$ok, $text, $err, $u] = gemini_chat_once($model, opencode_gen_system(), $user, $tryTemp, $outTokens, 55);
        $attempt++;
        $usageTotal = opencode_add_usage($usageTotal, $u);
        if (!$ok) {
            $errors[] = $err;
            // One retry on the SAME lite model with calmer temp before giving up.
            // Skip retry when the first call already burned ~50s+ (would exceed
            // the HTTP budget and force a template fallback anyway).
            if ($attempt === 1 && stripos((string)$err, 'timed out') === false && stripos((string)$err, 'Timeout') === false) {
                [$ok2, $text2, $err2, $u2] = gemini_chat_once($model, opencode_gen_system(), $user, max(0.1, $temperature - 0.25), $outTokens, 55);
                $usageTotal = opencode_add_usage($usageTotal, $u2);
                if ($ok2) { $ok = true; $text = $text2; }
                else { $errors[] = $err2; continue; }
            } else { continue; }
        }
        if (opencode_is_refusal($text)) { $errors[] = $model . ': conversational reply'; continue; }
        $html = opencode_clean_html($text);
        if (opencode_is_complete_html($html)) {
            if (!opencode_has_content($html)) { $errors[] = $model . ': blank page'; continue; }
            if (opencode_has_duplicate_structure($html)) { $errors[] = $model . ': duplicate structure (double body / repeated ids)'; continue; }
            // Deterministic safety net only (free, instant). The AI polish
            // pass runs as a SEPARATE taste_repair HTTP call (frontend-driven)
            // so no single request exceeds the ~58s budget.
            $html = taste_apply_safety_net($html);
            return [true, $html, $model, [], $usageTotal];
        }
        $errors[] = $model . ': incomplete HTML (' . strlen($html) . ' chars)';
        // Single-model retry: a cut-off generation often completes on a
        // calmer-temp second attempt (same lite model, no model hopping).
        // Truncation retries get the bigger shop-aware budget too.
        if ($attempt === 1) {
            [$rok, $rtext, $rerr, $ru] = gemini_chat_once($model, opencode_gen_system(), $user, max(0.1, $temperature - 0.25), $outTokens, 55);
            $usageTotal = opencode_add_usage($usageTotal, $ru);
            if ($rok && !opencode_is_refusal($rtext)) {
                $rhtml = opencode_clean_html($rtext);
                if (opencode_is_complete_html($rhtml) && opencode_has_content($rhtml) && !opencode_has_duplicate_structure($rhtml)) {
                    return [true, taste_apply_safety_net($rhtml), $model, [], $usageTotal];
                }
                $errors[] = $model . ': retry incomplete (' . strlen($rhtml) . ' chars)';
            } else { $errors[] = $rerr ?: ($model . ': retry refused'); }
        }
    }
    error_log('[GeminiService] generate_one ALL FAILED var=' . $variationId . ' slot=' . $slot . ': ' . substr(implode(' | ', array_slice($errors, 0, 4)), 0, 400));
    return [false, '', null, $errors, $usageTotal];
}

// ── TASTE repair pass: fix ONLY audit issues, quick attempt ────
// Returns [ok, html, modelUsed|null, errors[], usage]
function gemini_repair_html(string $html, array $issues): array {
    if (trim($html) === '' || empty($issues)) {
        return [false, '', null, ['Empty html or issues'], ['prompt' => 0, 'completion' => 0]];
    }
    $user = taste_repair_instruction($issues) . "\n\n### OUTPUT\nReturn the COMPLETE fixed HTML document now (<!DOCTYPE html> … </html>). No markdown fences, no commentary.\n\n### DOCUMENT\n```html\n" . substr($html, 0, 45000) . "\n```";
    $usageTotal = ['prompt' => 0, 'completion' => 0];
    // Single-model policy: lite only.
    foreach (['gemini-3.5-flash-lite'] as $m) {
        [$ok, $text, $err, $u] = gemini_chat_once($m, opencode_edit_system(), $user, 0.2, 12000, 26);
        $usageTotal = opencode_add_usage($usageTotal, $u);
        if (!$ok) continue;
        $out = opencode_clean_html($text);
        if (opencode_is_complete_html($out) && opencode_has_content($out) && !opencode_has_duplicate_structure($out)) {
            return [true, taste_apply_safety_net($out), $m, [], $usageTotal];
        }
    }
    return [false, '', null, ['repair pass failed'], $usageTotal];
}

// ── FAST: full-document edit, single shot ─────────────────────
// Returns [ok, html, modelUsed|null, errors[], usage]
function gemini_edit_html(string $currentHtml, string $instruction, string $bizName = 'Website', ?string $preferred = null): array {
    if (trim($instruction) === '' || strlen($currentHtml) < 100) {
        return [false, '', null, ['Empty instruction or HTML'], ['prompt' => 0, 'completion' => 0]];
    }
    $user = "### FULL DOCUMENT TO ANALYZE (read everything first)\n```html\n" . substr($currentHtml, 0, 40000) . "\n```\n\n"
        . "### USER INSTRUCTION (apply ONLY this)\n" . trim($instruction) . "\n\n"
        . "### TASTE SKILL UI DESIGN & EDIT PROTOCOL (Highest Authority)\n"
        . "- Aesthetic Integrity: Match existing design tokens (:root CSS variables, radius scale, shadows, font stack).\n"
        . "- Single Accent Rule: Exactly one primary brand accent page-wide. No secondary clashing colors.\n"
        . "- Anti-Slop Bans: Zero default purple glows, zero em-dash decoration, zero lorem ipsum, zero fake stats.\n"
        . "- Accessibility & Contrast: Maintain WCAG AA contrast (>= 4.5:1) for all labels, buttons, and text.\n"
        . "- Zero Dead Controls: Every new/modified button must trigger real JavaScript or scroll to a valid existing section ID.\n"
        . "- Responsive: Ensure all new components collapse cleanly on mobile (<768px) with 44px+ tap targets.\n"
        . "- Surgical Precision: Change ONLY what the instruction requests. Preserve all other classes, IDs, and structure.\n\n"
        . "### OUTPUT\nReturn the COMPLETE updated HTML document now. Business: {$bizName}.";
    $queue = [];
    if ($preferred) $queue[] = $preferred;
    foreach (gemini_fast_models() as $m) {
        if (!in_array($m, $queue, true)) $queue[] = $m;
    }
    $errors = [];
    $usageTotal = ['prompt' => 0, 'completion' => 0];
    foreach ($queue as $model) {
        [$ok, $text, $err, $u] = gemini_chat_once($model, opencode_edit_system(), $user, 0.3, 12000, 28);
        $usageTotal = opencode_add_usage($usageTotal, $u);
        if (!$ok) { $errors[] = $err; continue; }
        $html = opencode_clean_html($text);
        if (opencode_is_complete_html($html)) {
            if (!opencode_has_content($html)) { $errors[] = $model . ': blank edit'; continue; }
            if (opencode_has_duplicate_structure($html)) { $errors[] = $model . ': duplicate structure in edit'; continue; }
            return [true, $html, $model, [], $usageTotal];
        }
        $errors[] = $model . ': incomplete edit (' . strlen($html) . ' chars)';
    }
    return [false, '', null, $errors, $usageTotal];
}
