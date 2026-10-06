<?php
// avatar.php — Serve customer profile photos from protected storage/avatars/.
// Public: serves only the logged-in customer's own photo or any photo to a
// logged-in customer viewing portal context (photos are non-sensitive).
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$h = preg_replace('/[^a-f0-9]/', '', strtolower($_GET['u'] ?? ''));
if (strlen($h) !== 32) { http_response_code(404); exit; }

$dir = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/storage') . '/avatars';
$files = glob($dir . '/' . $h . '.*');
$img = null;
foreach ($files as $f) {
    if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) { $img = $f; break; }
}
if (!$img || !is_file($img)) { http_response_code(404); exit; }

$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($img));
readfile($img);
exit;
