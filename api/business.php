<?php
// api/business.php — Business Understanding Engine endpoint
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BusinessSpec.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!is_array($req)) $req = $_POST;
$action = $req['action'] ?? 'analyze';
$data = $req['data'] ?? $req;

try {
    if ($action === 'analyze' || $action === 'spec') {
        $spec = business_spec_analyze(is_array($data) ? $data : []);
        // Optional Gemini assist: enrich differentiators when key present
        $spec['engine'] = 'rule-based';
        if (function_exists('gemini_analyze_requirements') || file_exists(__DIR__ . '/../includes/GeminiService.php')) {
            try {
                require_once __DIR__ . '/../includes/GeminiService.php';
                if (function_exists('gemini_analyze_requirements') && gemini_api_key() !== '') {
                    [$ok, $brief] = gemini_analyze_requirements(is_array($data) ? $data : []);
                    if ($ok && trim((string)$brief) !== '') {
                        $spec['ai_brief'] = substr(trim((string)$brief), 0, 2000);
                        $spec['engine'] = 'rule-based+gemini';
                    }
                }
            } catch (Throwable $e) { /* keep rule-based */ }
        }
        echo json_encode(['success' => true, 'spec' => $spec, 'summary' => business_spec_summary_text($spec)]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
