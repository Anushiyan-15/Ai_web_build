<?php
// ═══════════════════════════════════════════════════════════════
//  database/migrations/migrate.php — CLI migration runner
//  Usage (from project root):
//    php database/migrations/migrate.php            (apply pending)
//    php database/migrations/migrate.php --status   (show applied)
//  Web access is refused. Uses includes/db.php driver detection:
//  applies the pgsql file on PostgreSQL, mysql file on MySQL.
//  Every step is idempotent; re-running is safe.
// ═══════════════════════════════════════════════════════════════

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}

$root = dirname(dirname(__DIR__));
require_once $root . '/config.php';
require_once $root . '/includes/db.php';

$onlyStatus = in_array('--status', $argv ?? [], true);

function mig_split_statements(string $sql): array {
    // Order-preserving split. Compound blocks (pgsql DO $$..END $$; and
    // MySQL CREATE PROCEDURE..END$$) are lifted out via placeholders
    // first, the remainder splits on ";", then blocks are restored.
    $lines = preg_split('/\r\n|\r|\n/', $sql);
    $code = [];
    foreach ($lines as $ln) {
        $t = trim($ln);
        if (stripos($t, 'DELIMITER') === 0) continue;
        if (strpos(ltrim($ln), '--') === 0) continue; // full-line comment only
        $code[] = $ln;
    }
    $text = implode("\n", $code);
    $blocks = [];
    $text = preg_replace_callback(
        '/\bDO\s*\$\$.*?\bEND\s*\$\$\s*;/s',
        function ($m) use (&$blocks) { $blocks[] = $m[0]; return "\n__MIG_BLOCK_" . (count($blocks) - 1) . "__;\n"; },
        $text
    );
    $text = preg_replace_callback(
        '/\bCREATE\s+PROCEDURE\b.*?\bEND\s*\$\$/s',
        function ($m) use (&$blocks) { $blocks[] = rtrim($m[0]); return "\n__MIG_BLOCK_" . (count($blocks) - 1) . "__;\n"; },
        $text
    );
    $out = [];
    foreach (explode(';', $text) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $part = preg_replace_callback(
            '/__MIG_BLOCK_(\d+)__/',
            function ($m) use (&$blocks) { return $blocks[(int)$m[1]]; },
            $part
        );
        // MySQL procedure terminator "$$" becomes end-of-statement.
        $part = preg_replace('/\$\$\s*$/', '', $part);
        $part = trim($part);
        if ($part !== '') $out[] = $part;
    }
    return $out;
}

try {
    $db = getDb();
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}
if (!$db) { fwrite(STDERR, "No database reachable (Supabase + local MySQL both failed).\n"); exit(1); }

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Driver: $driver\n";

// Ensure ledger exists even on drivers where migration SQL hasn't run.
try {
    if ($driver === 'pgsql') {
        $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(50) PRIMARY KEY, applied_at TIMESTAMPTZ DEFAULT NOW())");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (`version` VARCHAR(50) NOT NULL PRIMARY KEY, `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Ledger init failed: " . $e->getMessage() . "\n");
    exit(1);
}

$applied = [];
try {
    $applied = $db->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {}

$migrations = [
    '001_storage_quota' => $driver === 'pgsql' ? '001_storage_quota_pgsql.sql' : '001_storage_quota_mysql.sql',
];

if ($onlyStatus) {
    foreach ($migrations as $ver => $file) {
        echo str_pad($ver, 22) . (in_array($ver, $applied, true) ? "APPLIED  ($file)" : "PENDING  ($file)") . "\n";
    }
    exit(0);
}

foreach ($migrations as $ver => $file) {
    if (in_array($ver, $applied, true)) { echo "[$ver] already applied — skipping.\n"; continue; }
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) { fwrite(STDERR, "Missing migration file: $file\n"); exit(1); }
    echo "[$ver] applying $file ...\n";
    $stmts = mig_split_statements(file_get_contents($path));
    // NOTE: MySQL auto-commits DDL, so a wrapping transaction is useless
    // there (and rollBack() would throw). Use transactions only on pgsql.
    $useTxn = ($driver === 'pgsql');
    if ($useTxn) $db->beginTransaction();
    try {
        foreach ($stmts as $sql) {
            $db->exec($sql);
        }
        if ($useTxn) $db->commit();
        echo "[$ver] schema applied.\n";
    } catch (Throwable $e) {
        if ($useTxn && $db->inTransaction()) $db->rollBack();
        fwrite(STDERR, "[$ver] FAILED: " . $e->getMessage() . "\n");
        $bad = substr(preg_replace('/\s+/', ' ', $sql ?? ''), 0, 300);
        fwrite(STDERR, "Statement: $bad\n");
        exit(1);
    }

    // ── Data backfill (idempotent) ──────────────────────────
    try {
        mig_backfill($db, $driver);
        echo "[$ver] backfill complete.\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "[$ver] backfill warning: " . $e->getMessage() . "\n");
    }

    try {
        if ($driver === 'pgsql') {
            $db->exec("INSERT INTO schema_migrations (version) VALUES ('$ver') ON CONFLICT (version) DO NOTHING");
        } else {
            $db->exec("INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('$ver')");
        }
    } catch (Throwable $e) {}
    echo "[$ver] done.\n";
}
echo "All migrations applied.\n";

/**
 * Backfill: provision customer rows for order emails, assign plans from
 * latest order package, create usage rows. Never overwrites existing data.
 */
function mig_backfill(PDO $db, string $driver): void {
    $q = fn($n) => $driver === 'pgsql' ? "\"$n\"" : "`$n`";

    // 1. Distinct order emails (admin_email preferred, fallback client_email)
    $emails = [];
    try {
        $rows = $db->query("SELECT admin_email, client_email, admin_username, package, published_at, created_at FROM orders")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $em = strtolower(trim($r['admin_email'] ?? ''));
            if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $em = strtolower(trim($r['client_email'] ?? ''));
            }
            if (!filter_var($em, FILTER_VALIDATE_EMAIL)) continue;
            // Keep latest order per email for plan mapping
            $ts = $r['published_at'] ?? ($r['created_at'] ?? '');
            if (!isset($emails[$em]) || strcmp((string)$ts, (string)($emails[$em]['ts'] ?? '')) > 0) {
                $emails[$em] = ['name' => $r['admin_username'] ?? '', 'package' => $r['package'] ?? '', 'ts' => $ts];
            }
        }
    } catch (Throwable $e) { return; }

    // 2. Plan slug map (preserve legacy identifiers)
    $planIds = [];
    try {
        foreach ($db->query("SELECT id, slug FROM plans")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $planIds[strtolower($p['slug'])] = $p['id'];
        }
    } catch (Throwable $e) { return; }
    $defaultPlan = $planIds['free'] ?? ($planIds['starter'] ?? reset($planIds) ?: null);
    $pkgMap = ['free' => 'free', 'starter' => 'starter', 'basic' => 'basic', 'pro' => 'pro', 'business' => 'business', 'agency' => 'agency'];

    foreach ($emails as $em => $info) {
        // 2a. Provision missing customer with an unusable random hash.
        // The owner activates it via the normal forgot-password reset flow
        // (setCustomerPassword migrates legacy order emails); no usable
        // password is invented.
        try {
            $chk = $db->prepare("SELECT email FROM customers WHERE email = :e");
            $chk->execute([':e' => $em]);
            if (!$chk->fetch()) {
                $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
                $nm = trim((string)($info['name'] ?? ''));
                if ($driver === 'pgsql') {
                    $ins = $db->prepare("INSERT INTO customers (name, email, password_hash, created_at) VALUES (:n, :e, :h, NOW())");
                } else {
                    $ins = $db->prepare("INSERT INTO customers (name, email, password_hash, created_at) VALUES (:n, :e, :h, NOW())");
                }
                $ins->execute([':n' => $nm !== '' ? $nm : $em, ':e' => $em, ':h' => $hash]);
            }
        } catch (Throwable $e) { continue; }

        // 2b. Backfill plan from latest order package (only when unset)
        $slug = $pkgMap[strtolower(trim((string)($info['package'] ?? '')))] ?? null;
        $pid = ($slug && isset($planIds[$slug])) ? $planIds[$slug] : $defaultPlan;
        if ($pid) {
            try {
                $db->prepare("UPDATE customers SET plan_id = :p WHERE email = :e AND plan_id IS NULL")
                   ->execute([':p' => $pid, ':e' => $em]);
            } catch (Throwable $e) {}
        }

        // 2c. Ensure usage row with a fresh billing period (only when missing)
        try {
            $chk = $db->prepare("SELECT email FROM customer_usage WHERE email = :e");
            $chk->execute([':e' => $em]);
            if (!$chk->fetch()) {
                $start = date('Y-m-d');
                $end = date('Y-m-d', strtotime('+' . (defined('STORAGE_BILLING_PERIOD_DAYS') ? STORAGE_BILLING_PERIOD_DAYS : 30) . ' days'));
                $db->prepare("INSERT INTO customer_usage (email, billing_period_start, billing_period_end) VALUES (:e, :s, :en)")
                   ->execute([':e' => $em, ':s' => $start, ':en' => $end]);
            }
        } catch (Throwable $e) {}
    }
}
