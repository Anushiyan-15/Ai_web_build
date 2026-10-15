<?php
// api/business-ops.php — Real business operations backend (thin endpoint).
// Storage + authz + admin mirror live in includes/BusinessOps.php so the
// same logic is reusable and unit-testable. JSON file collections with
// flock transactions, idempotency, row caps; admin-only mutation except
// own-record cancel; customers see only own records.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BusinessOps.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
if (!is_array($req)) $req = $_POST;

try {
    $res = bops_dispatch($req);
    http_response_code($res['http']);
    echo json_encode($res['body']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
