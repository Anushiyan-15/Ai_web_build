<?php
// tests/storage_worker.php — parallel upload worker for the concurrency test.
// Usage: php storage_worker.php <email> <size_kb> <tag>
// Prints one JSON line: {tag, success, http, ...}
if (php_sapi_name() !== 'cli') exit('CLI only');
$email = $argv[1] ?? '';
$kb = max(1, (int)($argv[2] ?? 1));
$tag = $argv[3] ?? 'w';

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/config/storage.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/StorageService.php';

// Force local MySQL for deterministic tests (Supabase flaky from CI),
// with production-matching native prepares.
$pdo = new PDO(
    'mysql:host=' . LOCAL_MYSQL_HOST . ';dbname=' . LOCAL_MYSQL_NAME . ';charset=utf8mb4',
    LOCAL_MYSQL_USER, LOCAL_MYSQL_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);
StorageService::setTestDb($pdo);

$tmp = tempnam(sys_get_temp_dir(), 'stgw');
file_put_contents($tmp, random_bytes($kb * 1024));
$r = StorageService::storeUpload($email, $tmp, "worker-$tag.bin", 'application/octet-stream');
@unlink($tmp);
$r['tag'] = $tag;
echo json_encode($r) . "\n";
