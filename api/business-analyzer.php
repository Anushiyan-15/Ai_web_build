<?php
// api/business-analyzer.php — Universal AI Business Analyzer endpoints.
// Actions: analyze | manifest (load) | save_manifest | gapscan | autofix | verify
// JSON {success,...}, CORS *, never leaks keys.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { echo '{}'; exit; }

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BusinessRegistry.php';
require_once __DIR__ . '/../includes/BusinessAnalyzer.php';
require_once __DIR__ . '/../includes/BusinessManifest.php';
require_once __DIR__ . '/../includes/MissingFunctionAnalyzer.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw ?: '[]', true);
if (!is_array($req)) $req = [];
foreach ($_GET as $k => $v) { if (!isset($req[$k])) $req[$k] = $v; }
foreach ($_POST as $k => $v) { if (!isset($req[$k])) $req[$k] = $v; }
$action = strtolower((string)($req['action'] ?? 'analyze'));

function ba_out($body, int $http = 200) { http_response_code($http); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'analyze') {
    $a = business_analyzer_analyze($req['input'] ?? $req);
    $slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($req['slug'] ?? 'site')) ?: 'site';
    $m = business_manifest_build($a, $slug, (string)($req['order_id'] ?? ''));
    [$ok, $errs] = business_manifest_validate($m);
    ba_out(['success' => true, 'analysis' => $a, 'manifest' => $m, 'valid' => $ok, 'errors' => $errs]);
}

if ($action === 'manifest') {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($req['slug'] ?? '')) ?: 'site';
    $m = business_manifest_load($slug);
    if (!$m) ba_out(['success' => false, 'error' => 'No manifest for ' . $slug], 404);
    ba_out(['success' => true, 'manifest' => $m]);
}

if ($action === 'save_manifest') {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($req['slug'] ?? '')) ?: 'site';
    $m = $req['manifest'] ?? null;
    if (!is_array($m)) {
        $a = business_analyzer_analyze($req['input'] ?? $req);
        $m = business_manifest_build($a, $slug, (string)($req['order_id'] ?? ''));
    }
    [$ok, $errs] = business_manifest_validate($m);
    if (!$ok) ba_out(['success' => false, 'error' => 'Invalid manifest', 'errors' => $errs], 400);
    $written = business_manifest_save($slug, $m, (string)($req['order_id'] ?? ''));
    ba_out(['success' => true, 'written' => $written]);
}

if ($action === 'gapscan') {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($req['slug'] ?? '')) ?: 'site';
    $m = $req['manifest'] ?? business_manifest_load($slug);
    if (!is_array($m)) {
        $a = business_analyzer_analyze($req['input'] ?? $req);
        $m = business_manifest_build($a, $slug, (string)($req['order_id'] ?? ''));
    }
    $html = (string)($req['html'] ?? '');
    if ($html === '' && !empty($req['order_id'])) {
        $df = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/designs/' . preg_replace('/[^A-Za-z0-9\-_]/', '', $req['order_id']) . '.html';
        if (is_file($df)) $html = (string)@file_get_contents($df);
    }
    ba_out(['success' => true, 'report' => mfa_scan($m, $html, $slug)]);
}

if ($action === 'autofix') {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($req['slug'] ?? '')) ?: 'site';
    $m = $req['manifest'] ?? business_manifest_load($slug);
    if (!is_array($m)) ba_out(['success' => false, 'error' => 'No manifest; run analyze first'], 400);
    require_once __DIR__ . '/../includes/BusinessRuntime.php';
    $html = (string)($req['html'] ?? '');
    $r = mfa_autofix($slug, $m, $html);
    ba_out(['success' => true, 'applied' => $r['applied'], 'skipped' => $r['skipped'],
        'remaining' => count($r['rescan']['findings']), 'html' => $r['html']]);
}

if ($action === 'verify') {
    $slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($req['slug'] ?? '')) ?: 'site';
    $m = $req['manifest'] ?? business_manifest_load($slug);
    if (!is_array($m)) ba_out(['success' => false, 'error' => 'No manifest'], 404);
    [$ok, $errs] = business_manifest_validate($m);
    $adminDir = dirname(__DIR__) . '/published/' . $slug . '/admin';
    $checks = ['manifest_valid' => $ok, 'admin_dir' => is_dir($adminDir),
        'dashboard' => is_file($adminDir . '/index.php'), 'auth' => is_file($adminDir . '/.auth.json')];
    $entities = [];
    foreach (($m['entities'] ?? []) as $e) {
        $id = (string)($e['id'] ?? '');
        $entities[$id] = ['store' => is_file((defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . "/sites/$slug/$id.json")
            || is_file((defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . "/collections/$slug/$id.json"),
            'admin' => is_file($adminDir . "/data_$id.json")];
    }
    ba_out(['success' => true, 'valid' => $ok, 'errors' => $errs, 'checks' => $checks, 'entities' => $entities]);
}

ba_out(['success' => false, 'error' => 'Unknown action: ' . $action], 400);
