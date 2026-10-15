<?php
// api/generate-route.php — complexity assessment + provider routing decisions.
// Never exposes keys. Stores routing metadata per project for audit.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BizIntelligence/Schema.php';
require_once __DIR__ . '/../includes/BizIntelligence/Pipeline.php';
require_once __DIR__ . '/../includes/BizIntelligence/Complexity.php';
require_once __DIR__ . '/../includes/BizIntelligence/Router.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!is_array($req)) $req = $_POST;
$action = $req['action'] ?? 'decide';

try {
    if ($action === 'decide') {
        $wizard = is_array($req['data'] ?? null) ? $req['data'] : $req;
        $desc = (string)($req['description'] ?? $wizard['description'] ?? '');
        // Canonical spec first (validates before routing)
        $spec = biz_repair_spec(biz_build_spec($wizard, $desc));
        [$ok, $errs] = biz_validate_spec($spec);
        if (!$ok) { http_response_code(422); echo json_encode(['success' => false, 'error' => 'spec invalid', 'issues' => $errs]); exit; }
        $route = biz_decide_route($spec, is_array($req['complexity_cfg'] ?? null) ? $req['complexity_cfg'] : null);
        // Persist routing metadata (project-scoped when order known)
        $oid = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($req['order_id'] ?? ''));
        if ($oid !== '') {
            $dir = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/../storage') . '/projects/' . $oid;
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            @file_put_contents($dir . '/route.json', json_encode([
                'route' => $route,
                'spec_summary' => ['domain' => $spec['profile']['domain'] ?? '', 'capabilities' => $spec['capabilities'] ?? []],
                'decided_at' => date('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        echo json_encode(['success' => true, 'spec' => $spec, 'route' => $route,
            'summary' => ($spec['profile']['business_name'] ?? 'Business') . ' | ' . $route['complexity'] . ' (' . $route['score'] . ') | ' . $route['variations'] . ' variation(s) via ' . $route['primary']]);
        exit;
    }
    if ($action === 'assess') {
        $spec = biz_repair_spec(is_array($req['spec'] ?? null) ? $req['spec'] : []);
        [$ok, $errs] = biz_validate_spec($spec);
        if (!$ok) { http_response_code(422); echo json_encode(['success' => false, 'error' => 'spec invalid', 'issues' => $errs]); exit; }
        $route = biz_decide_route($spec, is_array($req['complexity_cfg'] ?? null) ? $req['complexity_cfg'] : null);
        echo json_encode(['success' => true, 'route' => $route]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
