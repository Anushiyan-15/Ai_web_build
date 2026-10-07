<?php
// preview.php — Real-URL website previews (no srcdoc/blob quirks).
//
// WHY: about:blank/srcdoc/blob preview documents inherit the APP url as their
// base, so an empty/relative header link (href="" or "?x") resolves to
// studio.php/builder.php and the preview tab "loops back into Studio".
// A real http(s) URL behaves exactly like the published site: #anchors
// scroll, relative page links 404 honestly, empty links harmlessly reload.
//
// POST JSON {html} → {success, url}   (stored 3h, token URL, max ~7MB)
// GET  ?t=<32hex> → the stored HTML   (no-cache, nosniff)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';
$dir = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/storage') . '/preview';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

// Opportunistic expiry (files older than 3h).
foreach (@glob($dir . '/*.html') ?: [] as $f) {
    if (is_file($f) && (time() - filemtime($f) > 10800)) @unlink($f);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $req = json_decode($raw, true) ?: [];
    $html = (string)($req['html'] ?? '');
    if (strlen($html) < 100) { echo json_encode(['success' => false, 'error' => 'Empty HTML']); exit; }
    if (strlen($html) > 7 * 1024 * 1024) { echo json_encode(['success' => false, 'error' => 'HTML too large for URL preview']); exit; }
    // Defense in depth: never store server-executable code.
    $html = preg_replace('/<\?php[\s\S]*?(?:\?>|$)/i', '', $html);
    $tok = bin2hex(random_bytes(16));
    if (@file_put_contents($dir . '/' . $tok . '.html', $html) === false) {
        echo json_encode(['success' => false, 'error' => 'Could not store preview']); exit;
    }
    $base = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '');
    $url = ($base !== '' ? $base : '') . '/preview.php?t=' . $tok;
    echo json_encode(['success' => true, 'url' => $url]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tok = strtolower($_GET['t'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $tok)) { http_response_code(404); exit; }
    $f = $dir . '/' . $tok . '.html';
    if (!is_file($f)) { http_response_code(404); exit; }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, max-age=0');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

http_response_code(405);
