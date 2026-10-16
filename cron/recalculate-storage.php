<?php
// ═══════════════════════════════════════════════════════════════
//  cron/recalculate-storage.php — Nightly storage usage repair job
//
//  For each customer: uploaded file bytes + email-owned published project
//  bytes → customer_usage.
//  Idempotent: only repairs counters that drifted from the file
//  inventory. NEVER touches bandwidth_used_bytes, extra_gb_purchased,
//  billing dates, payment history, or admin overrides.
//
//  CLI only (cron). HTTP access requires ?secret= matching
//  STORAGE_CRON_SECRET and is refused when the secret is empty.
//
//  Crontab (Windows UniServerZ example):
//    30 2 * * * C:\UniServerZ\core\php83\php.exe C:\UniServerZ\www\project\webbbuilder\cron\recalculate-storage.php >> C:\UniServerZ\www\project\webbbuilder\storage\logs\storage-cron.log 2>&1
//  Crontab (Linux example):
//    30 2 * * * /usr/bin/php /var/www/webbbuilder/cron/recalculate-storage.php >> /var/www/webbbuilder/storage/logs/storage-cron.log 2>&1
// ═══════════════════════════════════════════════════════════════

if (php_sapi_name() !== 'cli') {
    require_once dirname(__DIR__) . '/config/storage.php';
    $given = (string)($_GET['secret'] ?? '');
    $want = defined('STORAGE_CRON_SECRET') ? (string)STORAGE_CRON_SECRET : '';
    if ($want === '' || $given === '' || !hash_equals($want, $given)) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/config/storage.php';
require_once $root . '/includes/StorageService.php';

$started = microtime(true);
$checked = 0;
$repaired = 0;
$failed = [];
$repairedEmails = [];

$db = StorageService::db();
if (!$db) {
    echo "[" . date('Y-m-d H:i:s') . "] recalculate-storage: database unreachable, job aborted.\n";
    exit(1);
}

try {
    $emails = $db->query("SELECT email FROM customer_usage ORDER BY email ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] recalculate-storage: cannot list usage rows: " . $e->getMessage() . "\n";
    exit(1);
}

foreach ($emails as $email) {
    $checked++;
    try {
        $r = StorageService::recalculateUsage((string)$email);
        if (empty($r['success'])) {
            $failed[] = $email . ': ' . ($r['error'] ?? 'unknown');
            continue;
        }
        if (!empty($r['repaired'])) {
            $repaired++;
            $repairedEmails[] = $email . ' (' . ($r['before'] ?? '?') . ' → ' . ($r['after'] ?? '?') . ' bytes)';
        }
    } catch (Throwable $e) {
        $failed[] = $email . ': ' . $e->getMessage();
    }
}

$secs = round(microtime(true) - $started, 2);
echo "[" . date('Y-m-d H:i:s') . "] recalculate-storage: checked=$checked repaired=$repaired failed=" . count($failed) . " took={$secs}s\n";
foreach ($repairedEmails as $line) echo "  REPAIRED: $line\n";
foreach ($failed as $line) echo "  FAILED: $line\n";

// Append to the storage log for operators (best effort).
try {
    $logDir = (defined('STORAGE_DIR') ? STORAGE_DIR : $root . '/storage') . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/storage-cron.log',
        "[" . date('Y-m-d H:i:s') . "] checked=$checked repaired=$repaired failed=" . count($failed) . " took={$secs}s\n", FILE_APPEND);
} catch (Throwable $e) {}

exit(count($failed) > 0 ? 2 : 0);
