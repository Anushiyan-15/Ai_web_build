<?php
// api/ai-status.php — AI provider status (OpenRouter → Gemini → templates).
// GET-friendly. NEVER exposes API keys, headers, or secrets — safe to call
// from the browser for monitoring. No network calls unless ?probe=1.
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { echo '{}'; exit; }

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/OpenRouterService.php';

$probe = (string)($_GET['probe'] ?? '') === '1';
$or = openrouter_status();

$gemini = ['configured' => false];
if (function_exists('gemini_api_key')) {
    $gemini['configured'] = gemini_api_key() !== '';
} else {
    @require_once __DIR__ . '/../includes/GeminiService.php';
    $gemini['configured'] = function_exists('gemini_api_key') ? gemini_api_key() !== '' : (defined('GEMINI_API_KEY') && stripos((string)GEMINI_API_KEY, 'YOUR_') === false && GEMINI_API_KEY !== '');
}

// Optional live probe: catalogue + ONE inference test, bounded (~25s max).
if ($probe) {
    if ($or['configured']) {
        [$fok, $models] = openrouter_fetch_models(false);
        $cands = ($fok && is_array($models)) ? openrouter_pick_free($models, 'chat', 2) : [];
        $verified = '';
        foreach (array_slice($cands, 0, 1) as $id) {
            [$tok] = openrouter_test_model($id);
            if ($tok) { $verified = $id; break; }
        }
        if ($verified !== '') {
            $or['ok'] = true; $or['model'] = $verified;
            $or['last_test'] = date('c'); $or['probe'] = 'live model verified';
        } else {
            $or['ok'] = false; $or['probe'] = 'failed';
            $or['error'] = empty($cands) ? 'No free models in catalogue' : 'Inference test failed';
        }
    } else {
        $or['probe'] = 'skipped (no key)';
    }
}

echo json_encode([
    'success' => true,
    'order' => ['openrouter', 'gemini', 'template'],
    'primary' => $or['ok'] ? 'openrouter' : ($gemini['configured'] ? 'gemini' : 'template'),
    'openrouter' => $or,
    'gemini' => $gemini,
    'template' => ['available' => true, 'note' => 'Space Bunny + server templates (offline-safe)'],
], JSON_UNESCAPED_UNICODE);
