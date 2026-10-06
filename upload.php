<?php
// upload.php — Serve studio/site uploads from storage/uploads/.
// Mirrors avatar.php: storage/ is blocked by .htaccess, so images are
// served by 32-hex hash lookup. Public, long-cacheable, immutable URLs.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$h = preg_replace('/[^a-f0-9]/', '', strtolower($_GET['u'] ?? ''));
if (strlen($h) !== 32) { http_response_code(404); exit; }

$dir = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/storage') . '/uploads';
$files = glob($dir . '/' . $h . '.*');
$img = null;
foreach ($files as $f) {
    if (preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $f)) { $img = $f; break; }
}
if (!$img || !is_file($img)) { http_response_code(404); exit; }

$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
$ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . filesize($img));
readfile($img);
exit;
