<?php
// customer-files.php — Private gateway for quota-tracked customer files.
// storage/ is blocked by .htaccess, so tracked files in
// storage/customer_files/<md5(email)>/ are served only to their owner
// (customer session email match) or a signed-in platform admin.
// Usage: /customer-files.php?id=<files.id>
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit; }

$db = null;
try { $db = getDb(); } catch (Throwable $e) { $db = null; }
if (!$db) { http_response_code(503); exit; }

try {
    $st = $db->prepare("SELECT * FROM files WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $file = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $file = null; }
if (!$file) { http_response_code(404); exit; }

$owner = strtolower(trim((string)($file['email'] ?? '')));
$sessEmail = strtolower(trim((string)($_SESSION['customer_user']['email'] ?? '')));
$isOwner = $owner !== '' && $sessEmail !== '' && hash_equals($owner, $sessEmail);
$isAdmin = !empty($_SESSION['platform_admin_logged_in']) && $_SESSION['platform_admin_logged_in'] === true;
if (!$isOwner && !$isAdmin) { http_response_code(404); exit; }

$base = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/storage');
$rel = preg_replace('#^storage/#', '', (string)($file['file_path'] ?? ''));
$abs = $base . '/' . $rel;
$allowedRoot = $base . '/customer_files/';
if (strpos($abs, $allowedRoot) !== 0 || !is_file($abs)) { http_response_code(404); exit; }

$mime = (string)($file['mime_type'] ?? '');
if ($mime === '' || preg_match('#[^a-zA-Z0-9.+\-/]#', $mime)) $mime = 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=3600');
header('Content-Disposition: inline; filename="' . addslashes(basename((string)($file['file_name'] ?? 'file'))) . '"');
readfile($abs);
exit;
