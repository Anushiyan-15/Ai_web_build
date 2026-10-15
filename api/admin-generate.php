<?php
// api/admin-generate.php — Full-AI admin panel code generation.
// BACKEND CHAIN: OpenRouter free → OpenCode SpaceBunny → gemini-3.5-flash-lite
// fallback → server-written backend (publish installs AdminPanelGenerator /
// AdminDashboardBuilder from the manifest, so failure here never blocks).
// Same JSON contract the frontend (ai-admin-generator.js) expects:
// {files{path:code}, sql, entities[], admin_title, setup_notes, engine, model}.
// Validated + repaired, never exec'd.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/AdminAi.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!is_array($req)) $req = $_POST;
$action = $req['action'] ?? 'generate';

try {
    if ($action === 'generate') {
        $out = admin_ai_generate([
            'bizName' => (string)($req['bizName'] ?? 'Business'),
            'bizType' => (string)($req['bizType'] ?? 'business'),
            'userRequirements' => (string)($req['userRequirements'] ?? ''),
            'genMode' => (string)($req['genMode'] ?? 'admin'),
            'entities' => is_array($req['entities'] ?? null) ? array_slice($req['entities'], 0, 8) : [],
            'workflows' => is_array($req['workflows'] ?? null) ? array_slice($req['workflows'], 0, 8) : [],
        ]);
        if (!($out['success'] ?? false)) { http_response_code(502); echo json_encode($out); exit; }
        echo json_encode($out);
        exit;
    }
    if ($action === 'fix_file') {
        $path = substr(preg_replace('/[^A-Za-z0-9_\-\.\/]/', '', (string)($req['filePath'] ?? 'file.php')), 0, 80);
        $issue = substr(trim((string)($req['issue'] ?? 'Fix validation and error handling')), 0, 500);
        $code = substr((string)($req['currentCode'] ?? ''), 0, 20000);
        $prompt = "Fix this single PHP file. Return ONLY the corrected file content — no markdown, no explanation.\n\nFILE: {$path}\nISSUE: {$issue}\n\nCURRENT CODE:\n{$code}";
        [$ok, $text, $err] = gemini_chat_once('gemini-3.5-flash-lite',
            'You output raw file content only. No markdown, no explanation.', $prompt, 0.2, 12000, 90);
        if (!$ok) { http_response_code(502); echo json_encode(['success' => false, 'error' => 'Fix failed: ' . $err]); exit; }
        $fixed = preg_replace('/^```(?:php|html|js)?\s*|\s*```$/i', '', trim($text));
        echo json_encode(['success' => true, 'fixed' => $fixed]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
