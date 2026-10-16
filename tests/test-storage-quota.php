<?php
// tests/test-storage-quota.php — run: php tests/test-storage-quota.php
// Covers: quota upload allow/deny, atomic concurrency, counter updates,
// delete/clamp, tenant isolation, suspension, recalculation, pricing,
// payment idempotency, admin adjust audit, input validation, migration
// syntax, and existing-flow regression (config boot + admin security).
// Uses local MySQL with isolated stg_test_*@example.com accounts and
// cleans up afterwards. Never touches real customer rows.
if (php_sapi_name() !== 'cli') exit('CLI only');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/config/storage.php';
require_once $ROOT . '/includes/db.php';
require_once $ROOT . '/includes/StorageService.php';
require_once $ROOT . '/includes/Csrf.php';

$fail = 0;
$pass = 0;
function ok($c, $m) { global $fail, $pass; if ($c) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$pdo = new PDO(
    'mysql:host=' . LOCAL_MYSQL_HOST . ';dbname=' . LOCAL_MYSQL_NAME . ';charset=utf8mb4',
    LOCAL_MYSQL_USER, LOCAL_MYSQL_PASS,
    // Mirror production includes/db.php: native prepares (this rejects
    // reused named placeholders, so the suite guards against HY093).
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);
StorageService::setTestDb($pdo);
$PHP = 'C:\\UniServerZ\\core\\php83\\php.exe';

function t_email($n) { return "stg_test_$n@example.com"; }
function t_make_file($kb, $seed = 'x') {
    $f = tempnam(sys_get_temp_dir(), 'stgt');
    // Deterministic pseudo-random bytes (compressible zeros would cheat size? No — filesize counts bytes regardless).
    file_put_contents($f, str_repeat($seed, $kb * 1024));
    return $f;
}
function t_provision(PDO $pdo, $email, $planSlug = 'starter') {
    $pdo->prepare("DELETE FROM files WHERE email = :e")->execute([':e' => $email]);
    $pdo->prepare("DELETE FROM storage_transactions WHERE email = :e")->execute([':e' => $email]);
    $pdo->prepare("DELETE FROM customer_usage WHERE email = :e")->execute([':e' => $email]);
    $pdo->prepare("DELETE FROM customers WHERE email = :e")->execute([':e' => $email]);
    $plan = StorageService::getPlan($planSlug);
    $pdo->prepare("INSERT INTO customers (name, email, password_hash, plan_id, status) VALUES ('Test User', :e, :h, :p, 'active')")
        ->execute([':e' => $email, ':h' => password_hash('TestPass123!', PASSWORD_DEFAULT), ':p' => $plan ? $plan['id'] : null]);
    StorageService::ensureUsageRow($email);
}
function t_cleanup(PDO $pdo, $email) {
    foreach (StorageService::listFiles($email, 500) as $f) {
        $base = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage');
        $abs = $base . '/' . preg_replace('#^storage/#', '', $f['file_path']);
        if (strpos($abs, $base . '/customer_files/') === 0) @unlink($abs);
    }
    $pdo->prepare("DELETE FROM files WHERE email = :e")->execute([':e' => $email]);
    $pdo->prepare("DELETE FROM storage_transactions WHERE email = :e")->execute([':e' => $email]);
    $pdo->prepare("DELETE FROM customer_usage WHERE email = :e")->execute([':e' => $email]);
    $pdo->prepare("DELETE FROM customers WHERE email = :e")->execute([':e' => $email]);
}

// ── 0. Preconditions ──────────────────────────────────────────
ok(StorageService::driver() === 'mysql', 'test DB driver is mysql');
$plans = StorageService::getAllPlans(false);
$slugs = array_column($plans, 'slug');
foreach (['free', 'starter', 'basic', 'pro', 'business', 'agency'] as $s) ok(in_array($s, $slugs, true), "seeded plan present: $s");
ok(StorageService::bytesToMb(1048576) === 1.0, 'binary MB conversion (1048576 = 1.00 MB)');
ok(StorageService::bytesToMb(2621440) === 2.5, 'MB display rounded to 2 decimals');

// ── 1. Successful upload within quota ───────────────────────
$e1 = t_email('u1');
t_provision($pdo, $e1, 'starter'); // 250 MB
$tmp = t_make_file(100, 'a');
$r = StorageService::storeUpload($e1, $tmp, 'hello.txt', 'text/plain');
@unlink($tmp);
ok(!empty($r['success']) && ($r['http'] ?? 0) === 200, 'upload within quota succeeds');
ok(abs((StorageService::getSummary($e1)['used_bytes'] ?? -1) - 100 * 1024) < 1, 'usage counter equals uploaded bytes');
$fileId1 = (int)($r['file_id'] ?? 0);
ok($fileId1 > 0, 'file record id returned');

// ── 2. Upload rejected when remaining allowance insufficient ─
$e2 = t_email('u2');
t_provision($pdo, $e2, 'free');
$pdo->prepare("UPDATE customer_usage SET manual_storage_limit_mb = 1 WHERE email = :e")->execute([':e' => $e2]); // 1 MB override
$tmp = t_make_file(1500, 'b');
$r = StorageService::storeUpload($e2, $tmp, 'big.bin', 'application/octet-stream');
@unlink($tmp);
ok(empty($r['success']) && ($r['http'] ?? 0) === 402, 'over-quota upload rejected with HTTP 402');
ok(($r['error'] ?? '') === 'Storage quota exceeded', 'quota error shape');
ok(isset($r['used_mb'], $r['limit_mb'], $r['remaining_mb'], $r['extra_gb_needed']), 'quota payload has used/limit/remaining/extra_gb_needed');
ok((int)$r['extra_gb_needed'] >= 1, 'extra_gb_needed computed (>=1)');
ok((StorageService::getSummary($e2)['used_bytes'] ?? -1) === 0, 'failed upload counts zero bytes');

// ── 3. Concurrent uploads cannot exceed allowance ─────────────
$e3 = t_email('race');
t_provision($pdo, $e3, 'free');
$pdo->prepare("UPDATE customer_usage SET manual_storage_limit_mb = 1 WHERE email = :e")->execute([':e' => $e3]); // 1 MB
$procs = [];
$outs = [];
for ($i = 0; $i < 5; $i++) {
    // 300 KB each; 5 × 300 KB = 1.5 MB > 1 MB limit → at most 3 may win.
    $cmd = "$PHP " . escapeshellarg($ROOT . '/tests/storage_worker.php') . ' ' . escapeshellarg($e3) . ' 300 w' . $i;
    $outFile = sys_get_temp_dir() . "/stgw_$i.json";
    @unlink($outFile);
    pclose(popen("start /B \"\" $cmd > " . escapeshellarg($outFile) . " 2>&1", 'r'));
    $outs[] = $outFile;
}
// Wait for all workers (poll up to 60s).
$deadline = time() + 60;
do {
    $done = 0;
    foreach ($outs as $of) { if (file_exists($of) && trim((string)@file_get_contents($of)) !== '') $done++; }
    if ($done >= 5) break;
    sleep(1);
} while (time() < $deadline);
$won = 0; $wonBytes = 0;
foreach ($outs as $of) {
    $j = json_decode((string)@file_get_contents($of), true);
    if (!empty($j['success'])) { $won++; $wonBytes += (int)($j['size_bytes'] ?? 0); }
    @unlink($of);
}
$sum = StorageService::getSummary($e3);
ok($won >= 1 && $won <= 3, "race: 1–3 of 5 workers won (won=$won)");
ok(($sum['used_bytes'] ?? PHP_INT_MAX) <= 1 * 1048576, 'race: counter never exceeds 1 MB limit');
$dbTotal = (int)$pdo->query("SELECT COALESCE(SUM(file_size_bytes),0) FROM files WHERE email = " . $pdo->quote($e3))->fetchColumn();
ok($dbTotal === (int)($sum['used_bytes'] ?? -1), 'race: counter equals sum of file records');
ok($wonBytes === $dbTotal, 'race: granted bytes equal tracked bytes (no oversell, no ghost)');

// ── 4/5/6. Delete decrements; usage never negative ────────────
$e4 = t_email('del');
t_provision($pdo, $e4, 'starter');
$tmp = t_make_file(200, 'c');
$r = StorageService::storeUpload($e4, $tmp, 'todelete.txt', 'text/plain');
@unlink($tmp);
$fid = (int)$r['file_id'];
$before = StorageService::getSummary($e4)['used_bytes'];
$d = StorageService::deleteFile($e4, $fid);
ok(!empty($d['success']), 'delete succeeds');
ok(StorageService::getSummary($e4)['used_bytes'] === 0, 'usage decreases to zero after delete');
$d2 = StorageService::deleteFile($e4, $fid); // repeat
ok(!empty($d2['success']), 'repeated delete is idempotent, no corruption');
ok(StorageService::getSummary($e4)['used_bytes'] === 0, 'usage never negative after repeated delete');
$delTx = array_values(array_filter(StorageService::listTransactions($e4, 50), fn($t) => $t['action'] === 'DELETE'));
ok(count($delTx) >= 1 && (int)$delTx[0]['size_bytes'] < 0, 'DELETE transaction recorded with negative size');

// ── 7. Tenant isolation ───────────────────────────────────────
$e5a = t_email('isoA'); $e5b = t_email('isoB');
t_provision($pdo, $e5a, 'starter'); t_provision($pdo, $e5b, 'starter');
$tmp = t_make_file(50, 'd');
$r = StorageService::storeUpload($e5a, $tmp, 'private.txt', 'text/plain');
@unlink($tmp);
$fidA = (int)$r['file_id'];
$cross = StorageService::deleteFile($e5b, $fidA);
ok(empty($cross['success']) && ($cross['http'] ?? 0) === 404, 'customer B cannot delete customer A file (404, no leak)');
ok(StorageService::getSummary($e5a)['used_bytes'] === 50 * 1024, 'victim usage untouched');
ok(count(StorageService::listFiles($e5b, 100)) === 0, 'file list isolated per email');

// ── 8. Suspended customers blocked ────────────────────────────
$e6 = t_email('susp');
t_provision($pdo, $e6, 'starter');
StorageService::adminAdjust($e6, ['suspend' => true, 'require_note' => true], 'test-runner', 'test suspension');
$tmp = t_make_file(10, 'e');
$r = StorageService::storeUpload($e6, $tmp, 'blocked.txt', 'text/plain');
@unlink($tmp);
ok(empty($r['success']) && ($r['http'] ?? 0) === 403, 'suspended account cannot upload (403)');
StorageService::adminAdjust($e6, ['suspend' => false, 'require_note' => true], 'test-runner', 'test reactivate');
$tmp = t_make_file(10, 'e');
$r = StorageService::storeUpload($e6, $tmp, 'allowed.txt', 'text/plain');
@unlink($tmp);
ok(!empty($r['success']), 'reactivated account can upload again');

// ── 9. Recalculation repairs drift, preserves the rest ────────
$e7 = t_email('recalc');
t_provision($pdo, $e7, 'pro');
$tmp = t_make_file(120, 'f');
$r = StorageService::storeUpload($e7, $tmp, 'keep.bin', 'application/octet-stream');
@unlink($tmp);
$fixtureToken = strtoupper(bin2hex(random_bytes(4)));
$fixtureOrderId = 'STG-TEST-RECALC-' . $fixtureToken;
$fixtureSlug = strtolower('stg-test-recalc-' . $fixtureToken);
$fixtureStorage = defined('STORAGE_DIR') ? STORAGE_DIR : $ROOT . '/storage';
$fixturePublishedDir = (defined('PUBLISHED_DIR') ? PUBLISHED_DIR : $ROOT . '/published') . '/' . $fixtureSlug;
$fixtureProjectDir = $fixtureStorage . '/projects/' . $fixtureOrderId;
$fixtureSiteDir = $fixtureStorage . '/sites/' . $fixtureSlug;
$fixtureOrderFile = $fixtureStorage . '/orders/' . $fixtureOrderId . '.json';
foreach ([$fixturePublishedDir, $fixtureProjectDir, $fixtureSiteDir, dirname($fixtureOrderFile)] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}
file_put_contents($fixturePublishedDir . '/index.html', str_repeat('p', 64));
file_put_contents($fixtureProjectDir . '/spec.json', str_repeat('b', 32));
file_put_contents($fixtureSiteDir . '/business.json', str_repeat('m', 16));
file_put_contents($fixtureOrderFile, json_encode([
    'order_id' => $fixtureOrderId, 'slug' => $fixtureSlug,
    'status' => 'published', 'admin_email' => $e7,
]));
$pdo->prepare("UPDATE customer_usage SET storage_used_bytes = 99999999, bandwidth_used_bytes = 123456, extra_gb_purchased = 3, manual_storage_limit_mb = 500 WHERE email = :e")->execute([':e' => $e7]);
$rc = StorageService::recalculateUsage($e7);
ok(!empty($rc['success']) && !empty($rc['repaired']), 'recalculation detects and repairs drift');
$u = $pdo->query("SELECT * FROM customer_usage WHERE email = " . $pdo->quote($e7))->fetch(PDO::FETCH_ASSOC);
ok((int)$u['storage_used_bytes'] === (120 * 1024 + 64 + 32 + 16), 'counter includes uploaded files and email-owned published project files');
ok((int)$u['bandwidth_used_bytes'] === 123456 && (int)$u['extra_gb_purchased'] === 3 && (int)$u['manual_storage_limit_mb'] === 500, 'bandwidth / purchased GB / override preserved');
ok(!empty($u['last_calculated_at']), 'last_calculated_at stamped');
$unrelatedRefresh = StorageService::recalculateUsage($e1);
$unrelatedUsage = $pdo->query("SELECT storage_used_bytes FROM customer_usage WHERE email = " . $pdo->quote($e1))->fetchColumn();
ok(!empty($unrelatedRefresh['success']) && (int)$unrelatedUsage === 100 * 1024, 'published project bytes are isolated to the matching owner email');
foreach ([$fixturePublishedDir, $fixtureProjectDir, $fixtureSiteDir] as $fixtureDir) {
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixtureDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        if ($item->isDir()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($fixtureDir);
}
unlink($fixtureOrderFile);

// ── 10. Pricing math (server-side) ────────────────────────────
$q = StorageService::quoteExtraStorage('pro', 3);
ok($q && $q['total'] === round($q['rate'] * 3, 2) && $q['gb'] === 3 && $q['currency'] === 'USD', 'per-GB total = rate × gb in gateway currency');
ok(StorageService::quoteExtraStorage('no-such-plan', 2) === null, 'unknown plan quotes null');
$s = StorageService::getSummary($e7);
ok(StorageService::extraGbNeeded($s, 10 * 1073741824) === (int)ceil(((int)$s['used_bytes'] + 10 * 1073741824 - ((int)$s['effective_limit_mb']) * 1048576) / 1073741824), 'extraGbNeeded math correct');

// ── 11/12/13. Payment idempotency ─────────────────────────────
$e8 = t_email('pay');
t_provision($pdo, $e8, 'starter');
$beforeGb = (int)(StorageService::getSummary($e8)['extra_gb_purchased'] ?? 0);
$bad = StorageService::recordPurchase($e8, 2, 4.00, 'USD', '', 'empty ref');
ok(empty($bad['success']), 'purchase without reference rejected (no quota change)');
ok((int)(StorageService::getSummary($e8)['extra_gb_purchased'] ?? -1) === $beforeGb, 'failed payment leaves quota unchanged');
$p1 = StorageService::recordPurchase($e8, 2, 4.00, 'USD', 'CAP-TEST-001', 'test purchase');
ok(!empty($p1['success']) && empty($p1['duplicate']), 'verified payment grants storage');
ok((int)($p1['summary']['extra_gb_purchased'] ?? 0) === $beforeGb + 2, 'extra_gb increased by exactly 2');
$p2 = StorageService::recordPurchase($e8, 2, 4.00, 'USD', 'CAP-TEST-001', 'replay');
ok(!empty($p2['success']) && !empty($p2['duplicate']), 'replayed callback flagged duplicate');
ok((int)(StorageService::getSummary($e8)['extra_gb_purchased'] ?? 0) === $beforeGb + 2, 'replay grants nothing twice');
$purTx = array_values(array_filter(StorageService::listTransactions($e8, 50), fn($t) => $t['action'] === 'PURCHASE'));
ok(count($purTx) === 1 && (float)$purTx[0]['amount_charged'] === 4.00 && $purTx[0]['currency'] === 'USD', 'single PURCHASE ledger row with amount+currency');

// ── 14/15. Admin adjust persists + audits ─────────────────────
$e9 = t_email('adm');
t_provision($pdo, $e9, 'starter');
$adj = StorageService::adminAdjust($e9, ['plan_slug' => 'pro', 'manual_limit_mb' => 777, 'grant_gb' => 1, 'auto_bill_enabled' => true, 'require_note' => true], 'test-runner', 'test adjust all');
ok(!empty($adj['success']), 'admin adjust applies plan+override+grant+autobill');
$sa = StorageService::getSummary($e9);
ok(($sa['plan']['slug'] ?? '') === 'pro' && $sa['manual_storage_limit_mb'] === 777 && $sa['effective_limit_mb'] === 777, 'override precedence: manual wins over plan+extra');
ok((int)$sa['extra_gb_purchased'] === 1 && !empty($sa['auto_bill_enabled']), 'grant + auto-bill persisted');
$adjTx = array_values(array_filter(StorageService::listTransactions($e9, 100), fn($t) => $t['action'] === 'ADMIN_ADJUST'));
ok(count($adjTx) >= 4, 'every adjustment wrote ADMIN_ADJUST audit rows (' . count($adjTx) . ')');
$noNote = StorageService::adminAdjust($e9, ['grant_gb' => 1, 'require_note' => true], 'test-runner', '');
ok(empty($noNote['success']), 'grant without explanatory note rejected');
$badPlan = StorageService::adminAdjust($e9, ['plan_slug' => 'nope', 'require_note' => true], 'test-runner', 'x');
ok(empty($badPlan['success']), 'unknown plan rejected');

// ── 16. Input validation ──────────────────────────────────────
ok(empty(StorageService::storeUpload('not-an-email', __FILE__, 'x.txt', 'text/plain')['success']), 'invalid email rejected');
$noFile = StorageService::deleteFile($e9, 999999999);
ok(!empty($noFile['success']) && ($noFile['http'] ?? 0) === 200, 'unknown file id handled idempotently (no corruption)');
ok(StorageService::getSummary($e9)['used_bytes'] >= 0, 'usage stays valid after unknown-id delete');
$apiSrc = file_get_contents($ROOT . '/api/storage.php');
ok(strpos($apiSrc, 'strcasecmp($route') !== false, 'API binds route email to session email');
ok(strpos($apiSrc, 'stg_require_csrf') !== false && substr_count($apiSrc, 'stg_require_csrf(') >= 4, 'API enforces CSRF on mutations');
ok(strpos($apiSrc, '401') !== false && strpos($apiSrc, '403') !== false, 'API returns 401/403 auth statuses');
ok(strpos($apiSrc, 'getMessage()') === false || strpos($apiSrc, 'error_log') !== false, 'API does not leak exception text to clients');
$authSrc = file_get_contents($ROOT . '/platform-admin/auth.php');
ok(strpos($authSrc, "=== 'WebCraft@2026!'") === false, 'no hardcoded admin password bypass in auth.php');
$rolesSrc = file_get_contents($ROOT . '/platform-admin/roles.php');
ok(strpos($rolesSrc, "'raw_pass'   =>") === false && strpos($rolesSrc, '"raw_pass"') === false, 'no plaintext raw_pass seeded/stored in roles.php');
ok(strpos($rolesSrc, '=== $password') === false && strpos($rolesSrc, '$password ===') === false, 'no plaintext password comparison in roles.php');

// ── 18. Migration syntax per driver ───────────────────────────
$pg = file_get_contents($ROOT . '/database/migrations/001_storage_quota_pgsql.sql');
$my = file_get_contents($ROOT . '/database/migrations/001_storage_quota_mysql.sql');
ok(strpos($pg, '`') === false && stripos($pg, 'AUTO_INCREMENT') === false && stripos($pg, 'ENUM(') === false, 'pgsql migration has no MySQL-isms');
ok(stripos($pg, 'ON CONFLICT') !== false && stripos($pg, 'IF NOT EXISTS') !== false, 'pgsql migration uses PG idioms');
ok(stripos($my, 'ON CONFLICT') === false && stripos($my, 'ENGINE=InnoDB') !== false, 'mysql migration uses MySQL idioms');
ok($pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version = '001_storage_quota'")->fetchColumn() > 0, 'migration ledger recorded on MySQL');

// ── 19. Order-only identity self-provisions (portal Storage tab fix) ──
// A login backed only by an order (Order-ID / legacy password, no
// `customers` row) must get a working summary, not a dead end.
$eo = t_email('orderonly');
t_cleanup($pdo, $eo); // ensure no customers/usage rows exist
$orderFile = (defined('STORAGE_DIR') ? STORAGE_DIR : $ROOT . '/storage') . '/orders/TEST-ORDERONLY-FIXTURE.json';
@file_put_contents($orderFile, json_encode([
    'order_id' => 'TEST-ORDERONLY-FIXTURE', 'site_name' => 'Order Only Site',
    'slug' => 'order-only-site', 'package' => 'Pro', 'amount' => 19.00,
    'admin_email' => $eo, 'admin_username' => 'Order Only',
    'status' => 'published', 'site_active' => true,
    'published_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'),
]));
$so = StorageService::getSummary($eo);
ok(!empty($so) && ($so['plan']['slug'] ?? '') === 'pro', 'order-only identity gets summary with plan from order');
ok(StorageService::getCustomer($eo) !== null, 'customers row auto-provisioned for order identity');
ok(isset($so['used_mb'], $so['limit_mb'], $so['remaining_mb']), 'summary carries usage figures for buy/usage UI');
@unlink($orderFile);
t_cleanup($pdo, $eo);

// ── Cleanup ───────────────────────────────────────────────────
foreach (['u1','u2','race','del','isoA','isoB','susp','recalc','pay','adm'] as $n) t_cleanup($pdo, t_email($n));
$leftFiles = 0;
foreach (['u1','u2','race','del','isoA','isoB','susp','recalc','pay','adm'] as $n) { $leftFiles += count(StorageService::listFiles(t_email($n), 5)); }
ok($leftFiles === 0, 'test data cleaned up');

echo "\n" . ($fail === 0 ? "ALL STORAGE-QUOTA TESTS PASSED ($pass checks)" : "$fail FAILED ($pass passed)") . "\n";
exit($fail === 0 ? 0 : 1);
