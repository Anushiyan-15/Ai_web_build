<?php
// ═══════════════════════════════════════════════════════════════
//  api/opencode.php — OpenCode Zen AI lane (generation + chat edit)
//
//  POST JSON {action, ...}:
//   - models      → live model list + free list + recommended + benchmark
//   - test        → tiny chat probe across free models
//   - analyze     → {data} → design brief (FlowCraft Skills 1-2)
//   - generate_one→ {data, variation, mode, model?, slot?} → ONE premium HTML
//   - edit        → {current_html, instruction, biz_name?, model?} → full HTML
//
//  On ANY AI failure: success=false + errors[] + fallback hint.
//  Frontend then falls back to server templates (never empty).
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__) . '/includes/OpenCodeService.php';
require_once dirname(__DIR__) . '/includes/GeminiService.php';
require_once dirname(__DIR__) . '/includes/ShopHelper.php';

// Free-lane AI generations are slow (chunked, up to ~5 min for 3 chunks).
// Lift PHP's execution cap so Apache doesn't kill the request mid-stream.
@set_time_limit(600);

$raw = file_get_contents('php://input');
$req = json_decode($raw, true) ?: [];
$action = $req['action'] ?? 'test';
$model = isset($req['model']) && is_string($req['model']) ? trim($req['model']) : null;
if ($model === '') $model = null;

// ── TASTE LAYER: normalize prefs + merge into data ──────────────
// Wizard sends taste inside data.taste; direct callers may send top-level
// `taste: {vibe, variance, motion, density}` (0 = auto).
function taste_data_with_prefs($data, $req) {
    $t = [];
    if (isset($req['taste']) && is_array($req['taste'])) $t = $req['taste'];
    elseif (isset($data['taste']) && is_array($data['taste'])) $t = $data['taste'];
    $data['taste'] = taste_normalize($t);
    return $data;
}
function taste_meta_for($data) {
    $b = taste_infer_brief($data, (array)($data['taste'] ?? []));
    return ['design_read' => $b['design_read'], 'vibe' => $b['vibe'],
            'variance' => $b['variance'], 'motion' => $b['motion'], 'density' => $b['density']];
}

if ($action === 'models') {
    $list = opencode_list_models();
    $benchFile = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/opencode_benchmark.json';
    $bench = file_exists($benchFile) ? (json_decode(@file_get_contents($benchFile), true) ?: null) : null;
    $free = opencode_free_models();
    $paid = function_exists('opencode_paid_models') ? opencode_paid_models() : [];
    $recommended = ($bench['recommended'] ?? null);
    if (!in_array($recommended, $free, true)) $recommended = $free[0] ?? null;
    echo json_encode([
        'success' => true,
        'configured' => opencode_is_configured(),
        'default_model' => defined('OPENCODE_DEFAULT_MODEL') ? OPENCODE_DEFAULT_MODEL : 'space-bunny-free',
        'free' => $free,
        'paid' => $paid,
        'recommended' => $recommended,
        'benchmark' => $bench,
        'models' => $list['data'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'test') {
    if (!opencode_is_configured()) {
        echo json_encode(['success' => false, 'error' => 'OpenCode API key not configured (config/app.php → OPENCODE_API_KEY).', 'fallback' => 'templates']);
        exit;
    }
    [$ok, $text, $used, $errors] = opencode_chat_fallback($model, [
        ['role' => 'system', 'content' => 'Reply with exactly: OpenCode lane OK'],
        ['role' => 'user', 'content' => 'Probe: reply with exactly: OpenCode lane OK'],
    ], 0.2, 50);
    if ($ok) {
        echo json_encode(['success' => true, 'model' => $used, 'reply' => substr(trim($text), 0, 200)]);
    } else {
        echo json_encode(['success' => false, 'error' => 'All OpenCode models failed.', 'errors' => array_slice($errors, 0, 6), 'fallback' => 'templates']);
    }
    exit;
}

if ($action === 'analyze') {
    $data = is_array($req['data'] ?? null) ? $req['data'] : $req;
    $data = taste_data_with_prefs($data, $req);
    // Speed: Gemini lite brief (~3s) only. The slow OpenCode lane (28s+ sleeps)
    // used to run as backup here and pushed every generation past the HTTP
    // budget into template fallback — now we return the instant template brief
    // on lite failure instead (non-fatal, generation proceeds immediately).
    [$ok, $brief, $used, $errors] = gemini_analyze_requirements($data);
    echo json_encode([
        'success' => $ok,
        'brief' => $brief,
        'model' => $used,
        'ai' => $ok,
        'taste' => taste_meta_for($data),
        'errors' => $ok ? [] : array_slice($errors, 0, 6),
        'fallback' => $ok ? null : 'template-brief',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── TASTE AUDIT: mechanical pre-flight check on any HTML ──
if ($action === 'taste_audit') {
    $html = (string)($req['html'] ?? $req['current_html'] ?? '');
    if ($html === '') {
        echo json_encode(['success' => false, 'error' => 'Missing html']);
        exit;
    }
    echo json_encode(['success' => true, 'audit' => taste_audit_html($html)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── TASTE REPAIR: AI polish pass for weak pages (frontend-driven) ──
// Body: {html, issues?}. Fixes ONLY the listed audit issues, keeps the
// better-scoring version. Separate HTTP call so gen+polish never share
// one request budget (each stays <58s; Apache Timeout is 60s).
if ($action === 'taste_repair') {
    $html = (string)($req['html'] ?? '');
    $issues = is_array($req['issues'] ?? null) ? array_values($req['issues']) : [];
    if (strlen($html) < 200) {
        echo json_encode(['success' => false, 'error' => 'Missing html']);
        exit;
    }
    if (empty($issues)) $issues = taste_audit_html($html)['issues'];
    if (empty($issues)) {
        echo json_encode(['success' => true, 'html' => $html,
            'taste_audit' => taste_audit_html($html), 'note' => 'already clean']);
        exit;
    }
    [$ok, $out, $used, $errors, $usage] = gemini_repair_html($html, $issues);
    if ($ok) {
        $before = taste_audit_html($html);
        $after = taste_audit_html($out);
        if ($after['score'] >= $before['score']) {
            echo json_encode(['success' => true, 'html' => $out, 'model' => $used,
                'taste_audit' => $after, 'usage' => $usage,
                'note' => 'polished ' . $before['score'] . ' → ' . $after['score']]);
        } else {
            echo json_encode(['success' => true, 'html' => $html, 'model' => $used,
                'taste_audit' => $before, 'note' => 'repair scored lower — kept original']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Repair failed.',
            'errors' => array_slice($errors, 0, 4),
            'html' => $html, 'taste_audit' => taste_audit_html($html)]);
    }
    exit;
}

// ── FAST LANE (Gemini flash-lite: full page ~28s, Apache-safe) ──
if ($action === 'fast_one') {
    $data = is_array($req['data'] ?? null) ? $req['data'] : [];
    $data = taste_data_with_prefs($data, $req);
    $variation = preg_replace('/[^a-z]/', '', strtolower((string)($req['variation'] ?? 'classic')));
    if (!in_array($variation, ['classic', 'bold', 'editorial'], true)) $variation = 'classic';
    $mode = in_array(($req['mode'] ?? 'static'), ['static', 'admin', 'database'], true) ? $req['mode'] : 'static';
    $slot = isset($req['slot']) ? max(-1, min(2, (int)$req['slot'])) : -1;
    $brief = trim((string)($req['brief'] ?? ''));
    if ($brief === '') {
        // Speed: lite brief (~3s), NOT the slow OpenCode lane.
        [$bok, $brief, $bused] = gemini_analyze_requirements($data);
    }
    // Repeat-request shortcut: identical inputs served from cache instantly.
    $ckey = gen_cache_key($data, $variation, $mode, $slot, $brief);
    $hit = gen_cache_get($ckey);
    if (is_array($hit)) {
        $hit['cached'] = true;
        echo json_encode($hit, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $fastTemp = isset($req['temperature']) ? max(0.1, min(1.2, (float)$req['temperature'])) : null;
    [$ok, $html, $used, $errors, $usage] = gemini_generate_one($data, $variation, $mode, $brief, $slot, $model, $fastTemp);
    if ($ok) {
        $html = taste_apply_safety_net($html); // idempotent: no-op if already patched
        if (function_exists('injectShopIfMissing')) {
            $html = injectShopIfMissing($html, $data, $variation);
        }
        $resp = [
            'success' => true, 'engine' => 'gemini', 'model' => $used,
            'variation' => $variation, 'slot' => $slot, 'html' => $html, 'brief' => $brief,
            'usage' => $usage,
            'taste' => taste_meta_for($data),
            'taste_audit' => taste_audit_html($html),
        ];
        gen_cache_put($ckey, $resp);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false, 'engine' => 'gemini-failed',
            'variation' => $variation, 'brief' => $brief,
            'error' => 'Gemini fast lane failed.',
            'errors' => array_slice($errors, 0, 6),
            'fallback' => 'opencode-then-templates',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'fast_edit') {
    $html = (string)($req['current_html'] ?? '');
    $instruction = trim((string)($req['instruction'] ?? ''));
    $biz = trim((string)($req['biz_name'] ?? 'Website')) ?: 'Website';
    if ($html === '' || $instruction === '') {
        echo json_encode(['success' => false, 'error' => 'Missing current_html or instruction']);
        exit;
    }
    // AI-chat model: gemini-* honored as preferred (default lite);
    // 'opencode-fallback' means skip Gemini → let the OpenCode lane handle it.
    $fastModel = ($model && stripos($model, 'gemini') === 0) ? $model : null;
    [$ok, $out, $used, $errors, $usage] = gemini_edit_html($html, $instruction, $biz, $fastModel);
    if ($ok) {
        echo json_encode([
            'success' => true, 'engine' => 'gemini', 'model' => $used,
            'html' => $out, 'changed' => (trim($out) !== trim($html)),
            'usage' => $usage,
            'taste_audit' => taste_audit_html($out),
            'response_msg' => 'Gemini applied your change' . ($used ? " ({$used})" : '') . '.',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false, 'engine' => 'gemini-failed',
            'error' => 'Gemini fast edit failed.',
            'errors' => array_slice($errors, 0, 6),
            'fallback' => 'opencode-then-refine',
            'html' => $html,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'generate_one') {
    $data = is_array($req['data'] ?? null) ? $req['data'] : [];
    $data = taste_data_with_prefs($data, $req);
    $variation = preg_replace('/[^a-z]/', '', strtolower((string)($req['variation'] ?? 'classic')));
    if (!in_array($variation, ['classic', 'bold', 'editorial'], true)) $variation = 'classic';
    $mode = in_array(($req['mode'] ?? 'static'), ['static', 'admin', 'database'], true) ? $req['mode'] : 'static';
    $slot = isset($req['slot']) ? max(-1, min(2, (int)$req['slot'])) : -1;
    $brief = trim((string)($req['brief'] ?? ''));
    if ($brief === '') {
        [$bok, $brief, $bused] = gemini_analyze_requirements($data);
    }
    [$ok, $html, $used, $errors, $usage] = opencode_generate_variation($data, $variation, $mode, $brief, $model, $slot);
    if ($ok) {
        if (function_exists('injectShopIfMissing')) {
            $html = injectShopIfMissing($html, $data, $variation);
        }
        echo json_encode([
            'success' => true, 'engine' => 'opencode', 'model' => $used,
            'variation' => $variation, 'slot' => $slot, 'html' => $html, 'brief' => $brief,
            'usage' => $usage,
            'taste' => taste_meta_for($data),
            'taste_audit' => taste_audit_html($html),
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false, 'engine' => 'opencode-failed',
            'variation' => $variation, 'brief' => $brief,
            'error' => 'OpenCode AI could not generate this variation.',
            'errors' => array_slice($errors, 0, 6),
            'fallback' => 'templates',
            'hint' => 'Call api/generate.php action=flowcraft_generate for the premium template trio.',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── QUICK LANE (OpenCode single-shot: 3rd AI leg, ~40s) ──
if ($action === 'quick_one') {
    $data = is_array($req['data'] ?? null) ? $req['data'] : [];
    $data = taste_data_with_prefs($data, $req);
    $variation = preg_replace('/[^a-z]/', '', strtolower((string)($req['variation'] ?? 'classic')));
    if (!in_array($variation, ['classic', 'bold', 'editorial'], true)) $variation = 'classic';
    $mode = in_array(($req['mode'] ?? 'static'), ['static', 'admin', 'database'], true) ? $req['mode'] : 'static';
    $slot = isset($req['slot']) ? max(-1, min(2, (int)$req['slot'])) : -1;
    $brief = trim((string)($req['brief'] ?? ''));
    if ($brief === '') {
        [$bok, $brief, $bused] = gemini_analyze_requirements($data);
    }
    $qTemp = isset($req['temperature']) ? max(0.1, min(1.2, (float)$req['temperature'])) : null;
    [$ok, $html, $used, $errors, $usage] = opencode_generate_quick($data, $variation, $mode, $brief, $model, $slot, $qTemp);
    if ($ok) {
        $html = taste_apply_safety_net($html); // idempotent: reveal watchdog + smooth scroll + reduced-motion
        if (function_exists('injectShopIfMissing')) {
            $html = injectShopIfMissing($html, $data, $variation);
        }
        echo json_encode([
            'success' => true, 'engine' => 'opencode-quick', 'model' => $used,
            'variation' => $variation, 'slot' => $slot, 'html' => $html, 'brief' => $brief,
            'usage' => $usage,
            'taste' => taste_meta_for($data),
            'taste_audit' => taste_audit_html($html),
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false, 'engine' => 'opencode-quick-failed',
            'variation' => $variation, 'brief' => $brief,
            'error' => 'OpenCode quick lane failed.',
            'errors' => array_slice($errors, 0, 6),
            'fallback' => 'gemini-then-templates',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'edit') {
    $html = (string)($req['current_html'] ?? '');
    $instruction = trim((string)($req['instruction'] ?? ''));
    $biz = trim((string)($req['biz_name'] ?? 'Website')) ?: 'Website';
    if ($html === '' || $instruction === '') {
        echo json_encode(['success' => false, 'error' => 'Missing current_html or instruction']);
        exit;
    }
    [$ok, $out, $used, $errors, $usage] = opencode_edit_html($html, $instruction, $biz, $model);
    if ($ok) {
        $changed = trim($out) !== trim($html);
        echo json_encode([
            'success' => true, 'engine' => 'opencode', 'model' => $used,
            'html' => $out, 'changed' => $changed,
            'usage' => $usage,
            'taste_audit' => taste_audit_html($out),
            'response_msg' => '✨ OpenCode AI applied your change' . ($used ? " ({$used})" : '') . '.',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false, 'engine' => 'opencode-failed',
            'error' => 'OpenCode AI edit failed.',
            'errors' => array_slice($errors, 0, 6),
            'fallback' => 'gemini-then-smart-engine',
            'hint' => 'Retry via api/generate.php action=refine (Gemini → smart engine).',
            'html' => $html,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'edit_snippet') {
    $sel = (string)($req['selected_html'] ?? $req['current_html'] ?? '');
    $instruction = trim((string)($req['instruction'] ?? ''));
    $biz = trim((string)($req['biz_name'] ?? 'Website')) ?: 'Website';
    if ($sel === '' || $instruction === '') {
        echo json_encode(['success' => false, 'error' => 'Missing selected_html or instruction']);
        exit;
    }
    [$ok, $out, $used, $errors, $usage] = opencode_edit_snippet($sel, $instruction, $biz, $model);
    if ($ok) {
        echo json_encode([
            'success' => true, 'engine' => 'opencode-snippet', 'model' => $used,
            'html' => $out, 'changed' => (trim($out) !== trim($sel)),
            'usage' => $usage,
            'response_msg' => '✨ OpenCode AI updated the selected element' . ($used ? " ({$used})" : '') . '.',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false, 'engine' => 'opencode-snippet-failed',
            'error' => 'OpenCode AI snippet edit failed.',
            'errors' => array_slice($errors, 0, 6),
            'fallback' => 'puter-then-smart-style',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
