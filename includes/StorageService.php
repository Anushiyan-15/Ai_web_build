<?php
// ═══════════════════════════════════════════════════════════════
//  includes/StorageService.php — Central customer storage quota,
//  usage tracking, file registry, and per-GB billing service.
//
//  QUOTA SCOPE (what consumes quota):
//  - Files uploaded through an authenticated customer session that are
//    persisted under storage/customer_files/<md5(email)>/ and registered
//    in the `files` table. Each physical file is counted exactly once
//    (file_size_bytes summed from `files`).
//  - Published projects owned by the customer email in `orders`; their
//    published output and per-project bundles/manifests are counted from disk.
//  - NOT counted: external image URLs (never persisted as customer
//    assets), temporary/preview files, profile avatars
//    (storage/avatars/, 2MB account asset), transient OTP/mail-queue files.
//
//  UNITS: bytes internally (BIGINT). MB displayed = bytes / 1048576
//  rounded to 2 decimals. 1 GB = 1073741824 bytes.
//
//  EFFECTIVE ALLOWANCE precedence:
//    manual_storage_limit_mb (admin override, when NOT NULL) wins;
//    otherwise plan_base_limit_mb + (extra_gb_purchased × 1024).
//  Individual purchases increment customer_usage.extra_gb_purchased and
//  NEVER mutate the shared plans row.
//
//  CONCURRENCY: quota reservation uses an atomic conditional UPDATE
//  (storage_used_bytes + size <= limit) inside a DB transaction, so
//  concurrent uploads cannot oversell the allowance on either driver.
//  Row locking (SELECT ... FOR UPDATE) is used for purchase/adjust
//  paths where supported.
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/db.php';

class StorageService {
    const BYTES_PER_MB = 1048576;
    const BYTES_PER_GB = 1073741824;

    /** @var PDO|null injected handle (tests); null = use getDb() */
    private static $testDb = null;

    public static function setTestDb(?PDO $pdo): void { self::$testDb = $pdo; }

    public static function db(): ?PDO {
        if (self::$testDb) return self::$testDb;
        try { return getDb(); } catch (Throwable $e) { return null; }
    }

    public static function driver(?PDO $db = null): string {
        $db = $db ?: self::db();
        if (!$db) return 'none';
        try { return $db->getAttribute(PDO::ATTR_DRIVER_NAME); } catch (Throwable $e) { return 'none'; }
    }

    // ── Units ────────────────────────────────────────────────
    public static function bytesToMb($bytes): float {
        return round(((float)$bytes) / self::BYTES_PER_MB, 2);
    }
    public static function mbToBytes($mb): int {
        return (int)round(((float)$mb) * self::BYTES_PER_MB);
    }

    // ── Plans ────────────────────────────────────────────────
    public static function getPlan($idOrSlug): ?array {
        $db = self::db();
        if (!$db) return null;
        try {
            if (is_numeric($idOrSlug)) {
                $st = $db->prepare("SELECT * FROM plans WHERE id = :v LIMIT 1");
            } else {
                $st = $db->prepare("SELECT * FROM plans WHERE slug = :v LIMIT 1");
            }
            $st->execute([':v' => $idOrSlug]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) { return null; }
    }

    public static function getAllPlans(bool $activeOnly = false): array {
        $db = self::db();
        if (!$db) return [];
        try {
            $sql = "SELECT * FROM plans" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY storage_limit_mb ASC, id ASC";
            return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { return []; }
    }

    /** Server-side per-GB total. Never trust client-supplied totals. */
    public static function quoteExtraStorage($planIdOrSlug, int $gb): ?array {
        $plan = self::getPlan($planIdOrSlug);
        if (!$plan) return null;
        $gb = max(1, $gb);
        $rate = (float)($plan['price_per_extra_gb'] ?? 0);
        $currency = strtoupper(trim((string)($plan['currency'] ?? 'USD'))) ?: 'USD';
        if (defined('PAYPAL_CURRENCY')) $currency = strtoupper((string)PAYPAL_CURRENCY);
        return [
            'gb' => $gb,
            'rate' => round($rate, 2),
            'total' => round(round($rate, 2) * $gb, 2),
            'currency' => $currency,
            'plan_slug' => $plan['slug'],
            'plan_name' => $plan['name'],
        ];
    }

    // ── Customer / usage rows ────────────────────────────────
    public static function getCustomer(string $email): ?array {
        $db = self::db();
        if (!$db || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        try {
            $st = $db->prepare("SELECT * FROM customers WHERE email = :e LIMIT 1");
            $st->execute([':e' => strtolower(trim($email))]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { return null; }
    }

    public static function isSuspended(?array $customer): bool {
        if (!$customer) return false;
        return strtolower(trim((string)($customer['status'] ?? 'active'))) === 'suspended';
    }

    /**
     * Provision the storage identity for an authenticated email:
     * creates the missing `customers` row (unusable random hash — the
     * owner activates password login via the normal reset flow),
     * backfills the plan from their latest order package, and ensures
     * the `customer_usage` row. Idempotent and race-safe
     * (INSERT IGNORE / ON CONFLICT DO NOTHING).
     */
    public static function ensureCustomerStorage(string $email): bool {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $db = self::db();
        if (!$db) return false;
        $driver = self::driver($db);
        try {
            $chk = $db->prepare("SELECT email, plan_id FROM customers WHERE email = :e LIMIT 1");
            $chk->execute([':e' => $email]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $name = $email;
                $pkg = '';
                try {
                    if (function_exists('getCustomerOrdersFromDatabase')) {
                        $orders = getCustomerOrdersFromDatabase($email);
                        if (!empty($orders)) {
                            $first = $orders[0];
                            $nm = trim((string)($first['admin_username'] ?? ($first['site_name'] ?? '')));
                            if ($nm !== '') $name = $nm;
                            $pkg = strtolower(trim((string)($first['package'] ?? '')));
                        }
                    }
                } catch (Throwable $ignored) {}
                $plan = ($pkg !== '') ? self::getPlan($pkg) : null;
                if (!$plan) $plan = self::getPlan('free');
                $planId = $plan ? (int)$plan['id'] : null;
                $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
                if ($driver === 'pgsql') {
                    $db->prepare("INSERT INTO customers (name, email, password_hash, plan_id, created_at) VALUES (:n, :e, :h, :p, NOW()) ON CONFLICT (email) DO NOTHING")
                       ->execute([':n' => substr($name, 0, 255), ':e' => $email, ':h' => $hash, ':p' => $planId]);
                } else {
                    $db->prepare("INSERT IGNORE INTO customers (name, email, password_hash, plan_id) VALUES (:n, :e, :h, :p)")
                       ->execute([':n' => substr($name, 0, 255), ':e' => $email, ':h' => $hash, ':p' => $planId]);
                }
            } elseif (empty($row['plan_id'])) {
                // Existing row without a plan: backfill from latest order.
                try {
                    if (function_exists('getCustomerOrdersFromDatabase')) {
                        $orders = getCustomerOrdersFromDatabase($email);
                        $pkg = strtolower(trim((string)($orders[0]['package'] ?? '')));
                        $plan = ($pkg !== '') ? self::getPlan($pkg) : self::getPlan('free');
                        if ($plan) {
                            $db->prepare("UPDATE customers SET plan_id = :p WHERE email = :e AND plan_id IS NULL")
                               ->execute([':p' => (int)$plan['id'], ':e' => $email]);
                        }
                    }
                } catch (Throwable $ignored) {}
            }
            return self::ensureUsageRow($email) !== null;
        } catch (Throwable $e) {
            error_log('ensureCustomerStorage failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Idempotent: INSERT ... ON CONFLICT/IGNORE then SELECT. */
    public static function ensureUsageRow(string $email): ?array {        $db = self::db();
        if (!$db) return null;
        $email = strtolower(trim($email));
        $driver = self::driver($db);
        try {
            if ($driver === 'pgsql') {
                $db->prepare("INSERT INTO customer_usage (email, billing_period_start, billing_period_end) VALUES (:e, CURRENT_DATE, CURRENT_DATE + 30) ON CONFLICT (email) DO NOTHING")
                   ->execute([':e' => $email]);
            } else {
                $start = date('Y-m-d');
                $days = defined('STORAGE_BILLING_PERIOD_DAYS') ? (int)STORAGE_BILLING_PERIOD_DAYS : 30;
                $end = date('Y-m-d', strtotime('+' . $days . ' days'));
                $db->prepare("INSERT IGNORE INTO customer_usage (email, billing_period_start, billing_period_end) VALUES (:e, :s, :n)")
                   ->execute([':e' => $email, ':s' => $start, ':n' => $end]);
            }
            $st = $db->prepare("SELECT * FROM customer_usage WHERE email = :e LIMIT 1");
            $st->execute([':e' => $email]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { return null; }
    }

    /**
     * Full storage summary for one customer (real backend data).
     * Self-healing: a logged-in identity that owns orders but has no
     * `customers` row yet (Order-ID / legacy order-password login) is
     * provisioned on demand so the Storage tab never shows a dead end.
     * Returns null only when the account truly cannot be resolved
     * (e.g. database unreachable).
     */
    public static function getSummary(string $email): ?array {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $customer = self::getCustomer($email);
        if (!$customer) {
            if (!self::ensureCustomerStorage($email)) return null;
            $customer = self::getCustomer($email);
            if (!$customer) return null;
        }
        $usage = self::ensureUsageRow($email);
        if (!$usage) return null;

        $plan = null;
        if (!empty($customer['plan_id'])) $plan = self::getPlan((int)$customer['plan_id']);
        if (!$plan && !empty($customer['plan_slug_fallback'])) $plan = self::getPlan($customer['plan_slug_fallback']);
        if (!$plan) $plan = self::getPlan('free');
        if (!$plan) {
            $plan = ['slug' => 'free', 'name' => 'Free', 'storage_limit_mb' => 100, 'bandwidth_limit_mb' => 512, 'max_sites' => 1, 'price_per_extra_gb' => 2.00, 'currency' => 'USD', 'is_active' => 1];
        }

        $baseMb = (int)($plan['storage_limit_mb'] ?? 0);
        $extraGb = (int)($usage['extra_gb_purchased'] ?? 0);
        $manual = $usage['manual_storage_limit_mb'];
        $manualMb = ($manual === null || $manual === '') ? null : (int)$manual;
        $effectiveMb = ($manualMb !== null) ? $manualMb : ($baseMb + $extraGb * 1024);
        $limitBytes = $effectiveMb * self::BYTES_PER_MB;

        $usedBytes = (int)($usage['storage_used_bytes'] ?? 0);
        $usedMb = self::bytesToMb($usedBytes);
        $limitMb = self::bytesToMb($limitBytes);
        $remainingBytes = max(0, $limitBytes - $usedBytes);
        $remainingMb = self::bytesToMb($remainingBytes);
        $pct = $limitBytes > 0 ? round($usedBytes / $limitBytes * 100, 2) : 0;

        return [
            'email' => $email,
            'plan' => $plan,
            'plan_base_limit_mb' => $baseMb,
            'extra_gb_purchased' => $extraGb,
            'extra_allowance_mb' => $extraGb * 1024,
            'manual_storage_limit_mb' => $manualMb,
            'effective_limit_mb' => $effectiveMb,
            'used_bytes' => $usedBytes,
            'used_mb' => $usedMb,
            'limit_mb' => $limitMb,
            'remaining_mb' => $remainingMb,
            'percent' => $pct,
            'is_full' => $usedBytes >= $limitBytes,
            'bandwidth_used_bytes' => (int)($usage['bandwidth_used_bytes'] ?? 0),
            'bandwidth_used_mb' => self::bytesToMb((int)($usage['bandwidth_used_bytes'] ?? 0)),
            'bandwidth_limit_mb' => (int)($plan['bandwidth_limit_mb'] ?? 0),
            'billing_period_start' => $usage['billing_period_start'] ?? null,
            'billing_period_end' => $usage['billing_period_end'] ?? null,
            'last_calculated_at' => $usage['last_calculated_at'] ?? null,
            'auto_bill_enabled' => !empty($usage['auto_bill_enabled']),
            'auto_bill_supported' => defined('STORAGE_AUTOBILL_SUPPORTED') ? (bool)STORAGE_AUTOBILL_SUPPORTED : false,
            'status' => $customer['status'] ?? 'active',
            'suspended' => self::isSuspended($customer),
        ];
    }

    /** How many extra GB are needed to fit $bytes alongside current usage. */
    public static function extraGbNeeded(array $summary, int $bytes): int {
        $limitBytes = ((int)$summary['effective_limit_mb']) * self::BYTES_PER_MB;
        $short = ($summary['used_bytes'] + $bytes) - $limitBytes;
        if ($short <= 0) return 0;
        return (int)ceil($short / self::BYTES_PER_GB);
    }

    // ── Upload (quota-enforced) ──────────────────────────────
    public static function customerDir(string $email): string {
        $base = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
        $dir = $base . '/customer_files/' . md5(strtolower(trim($email)));
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    public static function sanitizeFileName(string $name): string {
        $base = basename($name);
        $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $base);
        $base = trim($base, '._');
        if ($base === '') $base = 'file';
        return substr($base, 0, 150);
    }

    public static function allowedMime(string $mime): bool {
        $list = defined('STORAGE_ALLOWED_MIMES') ? (json_decode(STORAGE_ALLOWED_MIMES, true) ?: []) : [];
        if (empty($list)) return true;
        return in_array(strtolower($mime), array_map('strtolower', $list), true);
    }

    /**
     * Store an uploaded file for a customer with atomic quota reservation.
     * $srcPath = validated local temp file. Returns result array with
     * updated usage on success, quota-shaped error (HTTP 402) when full.
     */
    public static function storeUpload(string $email, string $srcPath, string $origName, string $mime, ?int $maxBytes = null): array {
        $email = strtolower(trim($email));
        $db = self::db();
        if (!$db) return ['success' => false, 'error' => 'Storage service unavailable (database offline).', 'http' => 503];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'error' => 'Invalid customer email.', 'http' => 400];
        if (!is_file($srcPath)) return ['success' => false, 'error' => 'Uploaded file missing.', 'http' => 400];

        $customer = self::getCustomer($email);
        if (!$customer) {
            // Order-ID / legacy logins may not have a customers row yet.
            if (!self::ensureCustomerStorage($email)) {
                return ['success' => false, 'error' => 'Customer account not found.', 'http' => 404];
            }
            $customer = self::getCustomer($email);
            if (!$customer) return ['success' => false, 'error' => 'Customer account not found.', 'http' => 404];
        }
        if (self::isSuspended($customer)) return ['success' => false, 'error' => 'Account suspended. Uploads are disabled.', 'http' => 403];

        // Actual size wins over any client-supplied value.
        $size = filesize($srcPath);
        if ($size === false || $size < 0) return ['success' => false, 'error' => 'Could not determine file size.', 'http' => 400];
        $cap = $maxBytes ?: (defined('STORAGE_MAX_FILE_BYTES') ? (int)STORAGE_MAX_FILE_BYTES : 25 * 1048576);
        if ($size > $cap) return ['success' => false, 'error' => 'File exceeds the ' . round($cap / 1048576, 1) . 'MB per-file limit.', 'http' => 413];
        if (!self::allowedMime($mime)) return ['success' => false, 'error' => 'File type not allowed.', 'http' => 415];

        $usage = self::ensureUsageRow($email);
        if (!$usage) return ['success' => false, 'error' => 'Could not load usage record.', 'http' => 500];
        $summary = self::getSummary($email);
        if (!$summary) return ['success' => false, 'error' => 'Could not resolve storage plan.', 'http' => 500];
        $limitBytes = ((int)$summary['effective_limit_mb']) * self::BYTES_PER_MB;

        $safe = self::sanitizeFileName($origName);
        $stored = bin2hex(random_bytes(12)) . '_' . $safe;
        $dir = self::customerDir($email);
        $dest = $dir . '/' . $stored;
        $rel = 'storage/customer_files/' . md5($email) . '/' . $stored;

        $useTxn = self::driver($db) === 'pgsql';
        if ($useTxn) $db->beginTransaction();
        try {
            // Atomic reservation: only one concurrent uploader can win.
            // (Distinct placeholder names: native prepares on both
            // drivers reject a reused named parameter.)
            $res = $db->prepare("UPDATE customer_usage SET storage_used_bytes = storage_used_bytes + :sz WHERE email = :e AND storage_used_bytes + :sz2 <= :lim");
            $res->execute([':sz' => $size, ':e' => $email, ':sz2' => $size, ':lim' => $limitBytes]);
            if ($res->rowCount() < 1) {
                if ($useTxn) $db->rollBack();
                $fresh = self::getSummary($email);
                return [
                    'success' => false, 'error' => 'Storage quota exceeded',
                    'used_mb' => $fresh['used_mb'], 'limit_mb' => $fresh['limit_mb'],
                    'remaining_mb' => $fresh['remaining_mb'],
                    'extra_gb_needed' => max(1, self::extraGbNeeded($fresh, $size)),
                    'http' => 402,
                ];
            }
            if (!@copy($srcPath, $dest)) {
                throw new RuntimeException('Could not persist file.');
            }
            // pgsql requires RETURNING (lastInsertId needs a sequence name).
            if (self::driver($db) === 'pgsql') {
                $ins = $db->prepare("INSERT INTO files (email, file_name, file_path, file_path_hash, file_size_bytes, mime_type) VALUES (:e, :n, :p, :h, :s, :m) RETURNING id");
                $ins->execute([':e' => $email, ':n' => $safe, ':p' => $rel, ':h' => hash('sha256', $rel), ':s' => $size, ':m' => substr($mime, 0, 128)]);
                $fileId = (int)$ins->fetchColumn();
            } else {
                $ins = $db->prepare("INSERT INTO files (email, file_name, file_path, file_path_hash, file_size_bytes, mime_type) VALUES (:e, :n, :p, :h, :s, :m)");
                $ins->execute([':e' => $email, ':n' => $safe, ':p' => $rel, ':h' => hash('sha256', $rel), ':s' => $size, ':m' => substr($mime, 0, 128)]);
                $fileId = (int)$db->lastInsertId();
            }
            self::logTxn($db, $email, 'UPLOAD', $size, null, null, null, 'Upload: ' . $safe . ' (' . $size . ' bytes)');
            if ($useTxn) $db->commit();
            $fresh = self::getSummary($email);
            return ['success' => true, 'http' => 200, 'file_id' => $fileId, 'file_name' => $safe, 'size_bytes' => $size, 'mime' => $mime, 'summary' => $fresh,
                'used_mb' => $fresh['used_mb'], 'limit_mb' => $fresh['limit_mb'], 'remaining_mb' => $fresh['remaining_mb']];
        } catch (Throwable $e) {
            if ($useTxn && $db->inTransaction()) $db->rollBack();
            else {
                // MySQL: DDL-free path, manually release a reservation if held.
                try { $db->prepare("UPDATE customer_usage SET storage_used_bytes = GREATEST(storage_used_bytes - :sz, 0) WHERE email = :e")->execute([':sz' => $size, ':e' => $email]); } catch (Throwable $ignored) {}
            }
            @unlink($dest);
            // Remove orphan file record if the insert succeeded but later failed.
            try { $db->prepare("DELETE FROM files WHERE file_path_hash = :h")->execute([':h' => hash('sha256', $rel)]); } catch (Throwable $ignored) {}
            return ['success' => false, 'error' => 'Upload failed. Please try again.', 'http' => 500];
        }
    }

    // ── Delete ───────────────────────────────────────────────
    public static function deleteFile(string $email, int $fileId): array {
        $email = strtolower(trim($email));
        $db = self::db();
        if (!$db) return ['success' => false, 'error' => 'Storage service unavailable.', 'http' => 503];
        try {
            $st = $db->prepare("SELECT * FROM files WHERE id = :id LIMIT 1");
            $st->execute([':id' => $fileId]);
            $file = $st->fetch(PDO::FETCH_ASSOC);
            if (!$file) return ['success' => true, 'idempotent' => true, 'message' => 'File already removed.', 'http' => 200, 'summary' => self::getSummary($email)];
            if (strcasecmp((string)($file['email'] ?? ''), $email) !== 0) {
                return ['success' => false, 'error' => 'Not found.', 'http' => 404];
            }
            $size = (int)($file['file_size_bytes'] ?? 0);
            $base = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
            $abs = $base . '/' . preg_replace('#^storage/#', '', (string)($file['file_path'] ?? ''));
            // Never follow paths outside the customer_files tree.
            $realBase = realpath($base . '/customer_files') ?: ($base . '/customer_files');
            $real = realpath($abs);
            if ($real && strpos($real, rtrim($realBase, '/\\')) === 0) {
                @unlink($real);
            } elseif ($abs && strpos($abs, $base . '/customer_files/') === 0 && is_file($abs)) {
                @unlink($abs); // missing-file tolerance: unlink best effort
            }
            $db->prepare("DELETE FROM files WHERE id = :id")->execute([':id' => $fileId]);
            $db->prepare("UPDATE customer_usage SET storage_used_bytes = GREATEST(storage_used_bytes - :sz, 0) WHERE email = :e")
               ->execute([':sz' => $size, ':e' => $email]);
            self::logTxn($db, $email, 'DELETE', -$size, null, null, null, 'Deleted file ID ' . $fileId . ' (' . ($file['file_name'] ?? '') . ')');
            return ['success' => true, 'http' => 200, 'freed_bytes' => $size, 'summary' => self::getSummary($email)];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Delete failed.', 'http' => 500];
        }
    }

    public static function listFiles(string $email, int $limit = 100, int $offset = 0): array {
        $db = self::db();
        if (!$db) return [];
        try {
            $limit = max(1, min(500, $limit));
            $offset = max(0, $offset);
            $st = $db->prepare("SELECT id, email, file_name, file_path, file_size_bytes, mime_type, uploaded_at FROM files WHERE email = :e ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $st->execute([':e' => strtolower(trim($email))]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { return []; }
    }

    // ── Ledger ───────────────────────────────────────────────
    public static function logTxn(PDO $db, string $email, string $action, int $sizeBytes, $amount, ?string $currency, ?string $ref, ?string $note): void {
        try {
            $db->prepare("INSERT INTO storage_transactions (email, action, size_bytes, amount_charged, currency, payment_reference, note) VALUES (:e, :a, :s, :amt, :c, :r, :n)")
               ->execute([':e' => $email, ':a' => $action, ':s' => $sizeBytes, ':amt' => $amount, ':c' => $currency, ':r' => $ref, ':n' => $note]);
        } catch (Throwable $e) { error_log('storage logTxn failed: ' . $e->getMessage()); }
    }

    public static function listTransactions(string $email, int $limit = 50): array {
        $db = self::db();
        if (!$db) return [];
        try {
            $limit = max(1, min(200, $limit));
            $st = $db->prepare("SELECT id, email, action, size_bytes, amount_charged, currency, payment_reference, note, created_at FROM storage_transactions WHERE email = :e ORDER BY id DESC LIMIT $limit");
            $st->execute([':e' => strtolower(trim($email))]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { return []; }
    }

    // ── Verified purchase (idempotent) ───────────────────────
    /**
     * Grant extra GB after server-side payment verification.
     * Idempotent on payment_reference: replays return the original grant
     * without adding storage twice.
     */
    public static function recordPurchase(string $email, int $gb, float $amount, string $currency, string $ref, ?string $note = null): array {
        $email = strtolower(trim($email));
        $gb = max(1, $gb);
        $db = self::db();
        if (!$db) return ['success' => false, 'error' => 'Storage service unavailable.', 'http' => 503];
        if ($ref === '') return ['success' => false, 'error' => 'Missing payment reference.', 'http' => 400];
        $useTxn = self::driver($db) === 'pgsql';
        try {
            // Replay guard: reference already granted?
            $chk = $db->prepare("SELECT id FROM storage_transactions WHERE payment_reference = :r LIMIT 1");
            $chk->execute([':r' => $ref]);
            if ($chk->fetch()) {
                return ['success' => true, 'duplicate' => true, 'message' => 'Payment already credited.', 'http' => 200, 'summary' => self::getSummary($email)];
            }
            if ($useTxn) $db->beginTransaction();
            // Lock the usage row where supported.
            try {
                $lock = $db->prepare("SELECT email FROM customer_usage WHERE email = :e FOR UPDATE");
                $lock->execute([':e' => $email]);
            } catch (Throwable $ignored) {}
            self::ensureUsageRow($email);
            // Ledger first: the UNIQUE payment_reference makes concurrent
            // replays fail here, before any quota is granted. Insert
            // directly (not via logTxn) so duplicate-key errors propagate.
            $db->prepare("INSERT INTO storage_transactions (email, action, size_bytes, amount_charged, currency, payment_reference, note) VALUES (:e, 'PURCHASE', :s, :amt, :c, :r, :n)")
               ->execute([':e' => $email, ':s' => $gb * self::BYTES_PER_GB, ':amt' => round($amount, 2), ':c' => strtoupper($currency), ':r' => $ref, ':n' => $note ?: ('Purchased ' . $gb . ' GB extra storage')]);
            $db->prepare("UPDATE customer_usage SET extra_gb_purchased = extra_gb_purchased + :g WHERE email = :e")
               ->execute([':g' => $gb, ':e' => $email]);
            if ($useTxn) $db->commit();
            return ['success' => true, 'http' => 200, 'gb' => $gb, 'summary' => self::getSummary($email)];
        } catch (Throwable $e) {
            if ($useTxn && $db->inTransaction()) $db->rollBack();
            // Unique-violation race on payment_reference → treat as replay.
            $code = (string)$e->getCode();
            if (strpos($e->getMessage(), 'uplicate') !== false || $code === '23000' || $code === '23505') {
                return ['success' => true, 'duplicate' => true, 'message' => 'Payment already credited.', 'http' => 200, 'summary' => self::getSummary($email)];
            }
            return ['success' => false, 'error' => 'Could not credit storage.', 'http' => 500];
        }
    }

    // ── Recalculation (nightly cron + admin reconcile) ───────
    public static function recalculateUsage(string $email): array {
        $email = strtolower(trim($email));
        $db = self::db();
        if (!$db) return ['success' => false, 'error' => 'DB unavailable'];
        try {
            $st = $db->prepare("SELECT COALESCE(SUM(file_size_bytes),0) AS total FROM files WHERE email = :e");
            $st->execute([':e' => $email]);
            $actual = (int)($st->fetchColumn() ?: 0) + self::publishedProjectBytes($email, $db);
            $usage = self::ensureUsageRow($email);
            $cached = (int)($usage['storage_used_bytes'] ?? 0);
            if ($actual !== $cached) {
                $db->prepare("UPDATE customer_usage SET storage_used_bytes = :t, last_calculated_at = NOW() WHERE email = :e")
                   ->execute([':t' => $actual, ':e' => $email]);
                return ['success' => true, 'repaired' => true, 'before' => $cached, 'after' => $actual];
            }
            $db->prepare("UPDATE customer_usage SET last_calculated_at = NOW() WHERE email = :e")->execute([':e' => $email]);
            return ['success' => true, 'repaired' => false, 'after' => $actual];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sum the on-disk footprint of published projects associated with an
     * owner email. DB orders and local snapshots are merged so fresh and
     * legacy publishes both resolve to the same account.
     */
    private static function publishedProjectBytes(string $email, PDO $db): int {
        $projects = [];
        $st = $db->prepare(
            "SELECT order_id, slug, status FROM orders
             WHERE LOWER(COALESCE(admin_email, '')) = :e
                OR LOWER(COALESCE(client_email, '')) = :e2"
        );
        $st->execute([':e' => $email, ':e2' => $email]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            self::addPublishedProject($projects, $row);
        }

        $ordersDir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/orders';
        foreach (is_dir($ordersDir) ? (glob($ordersDir . '/*.json') ?: []) : [] as $file) {
            $order = json_decode((string)@file_get_contents($file), true);
            if (!is_array($order)) continue;
            $adminEmail = strtolower(trim((string)($order['admin_email'] ?? '')));
            $clientEmail = strtolower(trim((string)($order['client_email'] ?? '')));
            if ($adminEmail !== $email && $clientEmail !== $email) continue;
            self::addPublishedProject($projects, $order);
        }

        $publishedDir = defined('PUBLISHED_DIR') ? PUBLISHED_DIR : dirname(__DIR__) . '/published';
        $storageDir = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
        $total = 0;
        $countedPaths = [];
        foreach ($projects as $project) {
            $slug = $project['slug'];
            $orderId = $project['order_id'];
            $paths = [$publishedDir . '/' . $slug];
            if ($orderId !== '') $paths[] = $storageDir . '/projects/' . $orderId;
            if ($slug !== '') $paths[] = $storageDir . '/sites/' . $slug;
            foreach ($paths as $path) {
                if (is_link($path)) continue;
                $realPath = realpath($path);
                if ($realPath === false || !is_dir($realPath)) continue;
                $pathKey = strtolower(str_replace('\\', '/', $realPath));
                if (isset($countedPaths[$pathKey])) continue;
                $countedPaths[$pathKey] = true;
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($files as $file) {
                    if ($file->isFile() && !$file->isLink()) {
                        $size = $file->getSize();
                        if ($size < 0) throw new RuntimeException('Could not read published project file size.');
                        $total += $size;
                    }
                }
            }
        }
        return $total;
    }

    private static function addPublishedProject(array &$projects, array $order): void {
        if (strtolower(trim((string)($order['status'] ?? ''))) !== 'published') return;
        $slug = trim((string)($order['slug'] ?? ($order['published_slug'] ?? '')));
        if ($slug === '' || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/i', $slug)) return;
        $orderId = trim((string)($order['order_id'] ?? ''));
        if ($orderId !== '' && !preg_match('/\A[a-z0-9_-]+\z/i', $orderId)) $orderId = '';
        $projects[strtolower($slug)] = ['slug' => $slug, 'order_id' => $orderId];
    }

    // ── Admin operations (each writes ADMIN_ADJUST + audit log) ──
    public static function adminAdjust(string $email, array $ops, string $adminUser, string $note = ''): array {
        $email = strtolower(trim($email));
        $db = self::db();
        if (!$db) return ['success' => false, 'error' => 'DB unavailable.', 'http' => 500];
        if ($note === '' && !empty($ops['require_note'])) return ['success' => false, 'error' => 'An explanatory note is required.', 'http' => 422];
        $applied = [];
        try {
            if (array_key_exists('plan_slug', $ops) && $ops['plan_slug'] !== null && $ops['plan_slug'] !== '') {
                $plan = self::getPlan((string)$ops['plan_slug']);
                if (!$plan) return ['success' => false, 'error' => 'Unknown plan.', 'http' => 422];
                $db->prepare("UPDATE customers SET plan_id = :p WHERE email = :e")->execute([':p' => $plan['id'], ':e' => $email]);
                $applied[] = 'plan=' . $plan['slug'];
                self::logTxn($db, $email, 'ADMIN_ADJUST', 0, null, null, null, "[$adminUser] Plan changed to {$plan['slug']}. $note");
            }
            if (array_key_exists('manual_limit_mb', $ops)) {
                $v = $ops['manual_limit_mb'];
                if ($v === '' || $v === null) {
                    $db->prepare("UPDATE customer_usage SET manual_storage_limit_mb = NULL WHERE email = :e")->execute([':e' => $email]);
                    $applied[] = 'override cleared';
                } else {
                    $v = max(0, (int)$v);
                    self::ensureUsageRow($email);
                    $db->prepare("UPDATE customer_usage SET manual_storage_limit_mb = :v WHERE email = :e")->execute([':v' => $v, ':e' => $email]);
                    $applied[] = 'override=' . $v . 'MB';
                }
                self::logTxn($db, $email, 'ADMIN_ADJUST', 0, null, null, null, "[$adminUser] Manual storage limit: " . end($applied) . ". $note");
            }
            if (!empty($ops['grant_gb'])) {
                $g = max(1, (int)$ops['grant_gb']);
                self::ensureUsageRow($email);
                $db->prepare("UPDATE customer_usage SET extra_gb_purchased = extra_gb_purchased + :g WHERE email = :e")->execute([':g' => $g, ':e' => $email]);
                $applied[] = "granted {$g}GB free";
                self::logTxn($db, $email, 'ADMIN_ADJUST', $g * self::BYTES_PER_GB, 0, strtoupper(defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : 'USD'), null, "[$adminUser] Granted {$g} GB free. $note");
            }
            if (!empty($ops['reconcile'])) {
                $r = self::recalculateUsage($email);
                $applied[] = 'reconciled usage ' . ($r['repaired'] ? "({$r['before']} → {$r['after']} bytes)" : '(no drift)');
                self::logTxn($db, $email, 'ADMIN_ADJUST', 0, null, null, null, "[$adminUser] Usage reconciled from file and published project inventory. $note");
            }
            if (array_key_exists('suspend', $ops)) {
                if (!empty($ops['suspend'])) {
                    $db->prepare("UPDATE customers SET status = 'suspended', suspended_at = NOW(), suspended_reason = :r WHERE email = :e")->execute([':r' => substr($note, 0, 500), ':e' => $email]);
                    $applied[] = 'suspended';
                } else {
                    $db->prepare("UPDATE customers SET status = 'active', suspended_at = NULL, suspended_reason = NULL WHERE email = :e")->execute([':e' => $email]);
                    $applied[] = 'reactivated';
                }
                self::logTxn($db, $email, 'ADMIN_ADJUST', 0, null, null, null, "[$adminUser] Account " . end($applied) . ". $note");
            }
            if (array_key_exists('auto_bill_enabled', $ops)) {
                self::ensureUsageRow($email);
                $db->prepare("UPDATE customer_usage SET auto_bill_enabled = :v WHERE email = :e")->execute([':v' => !empty($ops['auto_bill_enabled']) ? 1 : 0, ':e' => $email]);
                $applied[] = 'auto_bill=' . (!empty($ops['auto_bill_enabled']) ? 'on' : 'off');
                self::logTxn($db, $email, 'ADMIN_ADJUST', 0, null, null, null, "[$adminUser] Auto-billing eligibility " . end($applied) . ". $note");
            }
            if (empty($applied)) return ['success' => false, 'error' => 'No operation specified.', 'http' => 422];
            self::auditLog($adminUser, 'storage_adjust:' . $email, implode('; ', $applied) . ' | note: ' . $note);
            return ['success' => true, 'http' => 200, 'applied' => $applied, 'summary' => self::getSummary($email)];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Adjustment failed.', 'http' => 500];
        }
    }

    public static function auditLog(string $adminUser, string $action, string $detail): void {
        try {
            if (file_exists(dirname(__DIR__) . '/platform-admin/helpers.php')) {
                require_once dirname(__DIR__) . '/platform-admin/helpers.php';
                if (function_exists('log_admin_action')) log_admin_action($action, null, "[$adminUser] $detail");
            }
        } catch (Throwable $e) {}
    }

    // ── Invoices: subscription history + storage purchases ────
    public static function invoices(string $email): array {
        $email = strtolower(trim($email));
        $out = ['subscription' => [], 'storage' => []];
        $db = self::db();
        if ($db) {
            try {
                $st = $db->prepare("SELECT order_id, site_name, package, amount, currency, payment_status, status, published_at, created_at FROM orders WHERE admin_email = :e OR client_email = :e2 ORDER BY id DESC");
                $st->execute([':e' => $email, ':e2' => $email]);
                $out['subscription'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {}
            try {
                $st = $db->prepare("SELECT id, action, size_bytes, amount_charged, currency, payment_reference, note, created_at FROM storage_transactions WHERE email = :e AND action = 'PURCHASE' ORDER BY id DESC");
                $st->execute([':e' => $email]);
                $out['storage'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {}
        }
        // Merge local JSON order snapshots (fresh publishes before DB sync).
        try {
            $dir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/orders';
            if (is_dir($dir)) {
                $seen = [];
                foreach ($out['subscription'] as $r) { if (!empty($r['order_id'])) $seen[$r['order_id']] = true; }
                foreach (glob($dir . '/*.json') ?: [] as $f) {
                    $o = json_decode(@file_get_contents($f), true);
                    if (!is_array($o)) continue;
                    if (strcasecmp($o['admin_email'] ?? '', $email) !== 0 && strcasecmp($o['client_email'] ?? '', $email) !== 0) continue;
                    if (!empty($o['order_id']) && isset($seen[$o['order_id']])) continue;
                    $out['subscription'][] = [
                        'order_id' => $o['order_id'] ?? basename($f, '.json'),
                        'site_name' => $o['site_name'] ?? 'Website',
                        'package' => $o['package'] ?? '',
                        'amount' => $o['amount'] ?? 0,
                        'currency' => $o['currency'] ?? 'USD',
                        'payment_status' => $o['payment_status'] ?? ($o['status'] ?? ''),
                        'status' => $o['status'] ?? '',
                        'published_at' => $o['published_at'] ?? null,
                        'created_at' => $o['created_at'] ?? null,
                        'source' => 'local',
                    ];
                }
            }
        } catch (Throwable $e) {}
        return $out;
    }

    // ── Pending storage orders (kept OUTSIDE storage/orders/ so the
    //    website-publishing payment flow never picks them up) ──
    public static function pendingDir(): string {
        $d = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/storage_orders';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }
    public static function savePendingOrder(array $rec): void {
        @file_put_contents(self::pendingDir() . '/' . preg_replace('/[^A-Za-z0-9-]/', '', $rec['ref']) . '.json', json_encode($rec, JSON_PRETTY_PRINT));
    }
    public static function loadPendingOrder(string $ref): ?array {
        $f = self::pendingDir() . '/' . preg_replace('/[^A-Za-z0-9-]/', '', $ref) . '.json';
        if (!file_exists($f)) return null;
        $d = json_decode(@file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }
    public static function markPendingCaptured(string $ref, string $captureId): void {
        $r = self::loadPendingOrder($ref);
        if (!$r) return;
        $r['status'] = 'captured';
        $r['capture_id'] = $captureId;
        $r['captured_at'] = date('Y-m-d H:i:s');
        self::savePendingOrder($r);
    }
}
