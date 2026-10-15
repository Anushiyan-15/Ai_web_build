<?php
// api/admin-preview.php — Single-file manifest-driven admin MOCK (legacy).
//
// NOTE: the builder workspace no longer routes users through this endpoint:
// the Admin tab now provisions a REAL pre-publish env via api/preview-env.php
// (preview/<draft>/ with real PHP handlers + real JSON storage), because a
// browser-localStorage simulation cannot prove PHP backend functionality.
// Kept for backward compatibility only.
//
// POST {bizName, bizType, tagline, services, products, audience, brief,
//       entities?, workflows?, genMode?, slug?}
// Flow: real business_analyzer_analyze → manifest_build → AI-lane probe
// (OpenRouter free → OpenCode SpaceBunny → Gemini lite) for the engine
// badge → single-file interactive HTML from includes/AdminPreview.php
// built from the REAL manifest (never generic prebuilt).
// Publishes the same manifest's multi-file PHP panel (AdminDashboardBuilder).
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
@set_time_limit(300);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BusinessRegistry.php';
require_once __DIR__ . '/../includes/BusinessAnalyzer.php';
require_once __DIR__ . '/../includes/BusinessManifest.php';
require_once __DIR__ . '/../includes/AdminPreview.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!is_array($req)) $req = $_POST;
$d = is_array($req['data'] ?? null) ? $req['data'] : $req;

$bizName = trim((string)($d['biz_name'] ?? $d['bizName'] ?? 'My Website')) ?: 'My Website';
$slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($req['slug'] ?? $d['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $bizName)))) ?: 'site';

$input = [
    'biz_name' => $bizName,
    'biz_type' => (string)($d['biz_type'] ?? $d['bizType'] ?? ''),
    'description' => (string)($d['brief'] ?? $d['description'] ?? $d['biz_tagline'] ?? $d['tagline'] ?? ''),
    'services' => (string)($d['biz_services'] ?? $d['services'] ?? ''),
    'products' => (string)($d['biz_products'] ?? $d['products'] ?? ''),
    'audience' => (string)($d['biz_audience'] ?? $d['audience'] ?? ''),
    'tagline' => (string)($d['biz_tagline'] ?? $d['tagline'] ?? ''),
];
// Caller-approved entities/workflows win (same spec as generation).
if (!empty($d['entities']) && is_array($d['entities'])) $input['_entities'] = array_slice($d['entities'], 0, 8);
if (!empty($d['workflows']) && is_array($d['workflows'])) $input['_workflows'] = array_slice($d['workflows'], 0, 8);

try {
    $a = business_analyzer_analyze($input);
    // Honor caller-approved entities when present (prevents drift).
    if (!empty($input['_entities'])) {
        $norm = [];
        foreach ($input['_entities'] as $e) {
            if (!is_array($e)) continue;
            $id = preg_replace('/[^a-z0-9_]/i', '', strtolower((string)($e['key'] ?? $e['id'] ?? $e['name'] ?? '')));
            if ($id === '') continue;
            $norm[] = ['id' => $id, 'name' => (string)($e['label'] ?? $e['name'] ?? $id),
                'singular' => (string)($e['label'] ?? $e['name'] ?? $id),
                'icon' => (string)($e['icon'] ?? '📁'), 'fields' => $e['fields'] ?? [['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true]]];
        }
        if (!empty($norm)) {
            $a['entities'] = $norm;
            $a['admin_modules'] = array_map(fn($e) => ['id' => $e['id'], 'label' => $e['name'], 'entity' => $e['id'], 'actions' => ['create', 'read', 'update', 'delete'], 'why' => 'Manage ' . $e['name'] . '.'], $norm);
        }
    }
    $m = business_manifest_build($a, $slug, (string)($req['order_id'] ?? ''));

    // AI-lane probe for the engine badge (fast: selection test only, the
    // full file generation still runs at publish via api/admin-generate.php).
    $engine = 'server';
    $model = 'manifest-live';
    $note = 'Built from your real business analysis — same modules publish installs.';
    try {
        if (function_exists('openrouter_is_configured') || file_exists(__DIR__ . '/../includes/OpenRouterService.php')) {
            @require_once __DIR__ . '/../includes/OpenRouterService.php';
            if (function_exists('openrouter_is_configured') && openrouter_is_configured()) {
                [$sel] = openrouter_select('code', false);
                if ($sel !== '') { $engine = 'openrouter'; $model = $sel; $note = 'AI lane verified: ' . $sel . ' — full PHP files generate at publish.'; }
            }
        }
    } catch (Throwable $e) {}
    if ($engine === 'server') {
        try {
            @require_once __DIR__ . '/../includes/OpenCodeService.php';
            if (function_exists('opencode_is_configured') && opencode_is_configured()) {
                $engine = 'opencode'; $model = 'space-bunny-free';
                $note = 'AI lane: Space Bunny → full PHP files generate at publish.';
            } elseif (defined('GEMINI_API_KEY') && stripos((string)GEMINI_API_KEY, 'YOUR_') === false) {
                $engine = 'gemini'; $model = 'gemini-3.5-flash-lite';
                $note = 'AI lane: Gemini lite → full PHP files generate at publish.';
            }
        } catch (Throwable $e) {}
    }

    $html = admin_preview_html($bizName, $m, ['engine' => $engine, 'model' => $model, 'note' => $note]);
    echo json_encode(['success' => true, 'engine' => $engine, 'model' => $model,
        'category' => $m['business']['category'] ?? 'generic',
        'entities' => $m['entities'], 'workflows' => $m['core_workflows'],
        'modules' => array_column($m['admin_modules'], 'label'),
        'html' => $html], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
