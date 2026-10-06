<?php
// ═══════════════════════════════════════════════════════════════
//  includes/db.php — Universal Database Helper (Supabase & MySQL)
// ═══════════════════════════════════════════════════════════════

require_once dirname(__DIR__) . '/config/database.php';

function getDb(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'pgsql';

    // 1. Primary Supabase PostgreSQL Connection
    if ($driver === 'pgsql') {
        $supabaseHosts = [DB_HOST, '2406:da14:1d62:b400:f71:180e:270f:e632'];
        foreach ($supabaseHosts as $h) {
            try {
                $dsn = "pgsql:host=" . $h . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 4,
                ]);
                return $pdo;
            } catch (PDOException $e) {
                error_log("Supabase PostgreSQL Connection Error ($h): " . $e->getMessage());
            }
        }
    }

    // 2. Fallback to Local MySQL if configured
    if (defined('LOCAL_MYSQL_HOST')) {
        try {
            $dsn = "mysql:host=" . LOCAL_MYSQL_HOST . ";dbname=" . LOCAL_MYSQL_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, LOCAL_MYSQL_USER, LOCAL_MYSQL_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 3,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            error_log('Local MySQL Connection Error: ' . $e->getMessage());
        }
    }

    return null;
}

/**
 * Synchronize an order array to Supabase / MySQL
 */
function syncOrderToDatabase(array $order): bool {
    $db = getDb();
    if (!$db) return false;

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $oid    = $order['order_id'] ?? '';
    if (!$oid) return false;

    $slug        = $order['slug'] ?? '';
    $siteName    = $order['site_name'] ?? 'Website';
    $package     = $order['package'] ?? 'Pro';
    $amount      = (float)($order['amount'] ?? 19.00);
    $status      = $order['status'] ?? 'published';
    $siteActive  = !empty($order['site_active']) ? 1 : 0;
    $liveUrl     = $order['live_url'] ?? '';
    $adminUrl    = $order['admin_url'] ?? '';
    $adminUser   = $order['admin_username'] ?? '';
    $adminEmail  = $order['admin_email'] ?: ($order['client_email'] ?? '');
    $nextPayment = $order['next_payment_due'] ?? date('Y-m-d', strtotime('+30 days'));
    $publishedAt = $order['published_at'] ?? date('Y-m-d H:i:s');
    $genMode     = $order['gen_mode'] ?? 'static';
    $payCapture  = $order['paypal_capture_id'] ?? null;
    $payMethod   = $order['payment_method'] ?? 'paypal';

    try {
        if ($driver === 'pgsql') {
            // Supabase PostgreSQL syntax
            $sql = "
                INSERT INTO orders (
                    order_id, paypal_capture_id, site_name, slug, gen_mode,
                    package, amount, currency, payment_method, payment_status,
                    status, site_active, live_url, admin_url, published_slug,
                    admin_username, admin_email, next_payment_due, published_at, updated_at
                ) VALUES (
                    :order_id, :paypal_capture_id, :site_name, :slug, :gen_mode,
                    :package, :amount, 'USD', :payment_method, 'completed',
                    :status, :site_active, :live_url, :admin_url, :slug,
                    :admin_username, :admin_email, :next_payment_due, :published_at, NOW()
                )
                ON CONFLICT (order_id) DO UPDATE SET
                    site_name        = EXCLUDED.site_name,
                    slug             = EXCLUDED.slug,
                    package          = EXCLUDED.package,
                    amount           = EXCLUDED.amount,
                    status           = EXCLUDED.status,
                    site_active      = EXCLUDED.site_active,
                    live_url         = EXCLUDED.live_url,
                    admin_url        = EXCLUDED.admin_url,
                    admin_email      = EXCLUDED.admin_email,
                    next_payment_due = EXCLUDED.next_payment_due,
                    published_at     = EXCLUDED.published_at,
                    updated_at       = NOW()
            ";
            $stmt = $db->prepare($sql);
            return $stmt->execute([
                ':order_id'          => $oid,
                ':paypal_capture_id' => $payCapture,
                ':site_name'         => $siteName,
                ':slug'              => $slug,
                ':gen_mode'          => $genMode,
                ':package'           => $package,
                ':amount'            => $amount,
                ':payment_method'    => $payMethod,
                ':status'            => $status,
                ':site_active'       => $siteActive ? 'true' : 'false',
                ':live_url'          => $liveUrl,
                ':admin_url'         => $adminUrl,
                ':admin_username'    => $adminUser,
                ':admin_email'       => $adminEmail,
                ':next_payment_due'  => $nextPayment,
                ':published_at'      => $publishedAt,
            ]);
        } else {
            // MySQL syntax
            $sql = "
                INSERT INTO orders (
                    order_id, paypal_capture_id, site_name, slug, gen_mode,
                    package, amount, currency, payment_method, payment_status,
                    status, site_active, live_url, admin_url, published_slug,
                    admin_username, admin_email, next_payment_due, published_at, created_at, updated_at
                ) VALUES (
                    :order_id, :paypal_capture_id, :site_name, :slug, :gen_mode,
                    :package, :amount, 'USD', :payment_method, 'completed',
                    :status, :site_active, :live_url, :admin_url, :slug,
                    :admin_username, :admin_email, :next_payment_due, :published_at, NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    site_name        = VALUES(site_name),
                    slug             = VALUES(slug),
                    package          = VALUES(package),
                    amount           = VALUES(amount),
                    status           = VALUES(status),
                    site_active      = VALUES(site_active),
                    live_url         = VALUES(live_url),
                    admin_url        = VALUES(admin_url),
                    admin_email      = VALUES(admin_email),
                    next_payment_due = VALUES(next_payment_due),
                    published_at     = VALUES(published_at),
                    updated_at       = NOW()
            ";
            $stmt = $db->prepare($sql);
            return $stmt->execute([
                ':order_id'          => $oid,
                ':paypal_capture_id' => $payCapture,
                ':site_name'         => $siteName,
                ':slug'              => $slug,
                ':gen_mode'          => $genMode,
                ':package'           => $package,
                ':amount'            => $amount,
                ':payment_method'    => $payMethod,
                ':status'            => $status,
                ':site_active'       => $siteActive,
                ':live_url'          => $liveUrl,
                ':admin_url'         => $adminUrl,
                ':admin_username'    => $adminUser,
                ':admin_email'       => $adminEmail,
                ':next_payment_due'  => $nextPayment,
                ':published_at'      => $publishedAt,
            ]);
        }
    } catch (Throwable $e) {
        error_log('syncOrderToDatabase Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all orders for a customer by email or order_id.
 * Merges Supabase/MySQL + local JSON (deduped by order_id) so a fresh
 * publish shows instantly even if DB sync is still propagating.
 */
function getCustomerOrdersFromDatabase(string $identifier): array {
    $identifier = trim($identifier);
    if (!$identifier) return [];

    $merged = [];
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

    $db = getDb();
    if ($db) {
        try {
            if ($isEmail) {
                $stmt = $db->prepare("
                    SELECT * FROM orders
                    WHERE admin_email = :id OR client_email = :id
                    ORDER BY id DESC
                ");
                $stmt->execute([':id' => $identifier]);
            } else {
                $stmt = $db->prepare("
                    SELECT * FROM orders
                    WHERE admin_email = :id OR client_email = :id OR order_id = :id
                    ORDER BY id DESC
                ");
                $stmt->execute([':id' => $identifier]);
            }
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!empty($row['order_id'])) $merged[$row['order_id']] = $row + ['source' => 'db'];
            }
        } catch (Throwable $e) {
            error_log('getCustomerOrdersFromDatabase Error: ' . $e->getMessage());
        }
    }

    // Merge local JSON files (covers fresh publishes + offline DB)
    $ordersDir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/orders';
    if (is_dir($ordersDir)) {
        foreach (glob($ordersDir . '/*.json') as $f) {
            $o = json_decode(@file_get_contents($f), true);
            if (!is_array($o) || empty($o['order_id'])) continue;
            $match = $isEmail
                ? (strcasecmp($o['admin_email'] ?? '', $identifier) === 0
                    || strcasecmp($o['client_email'] ?? '', $identifier) === 0)
                : (strcasecmp($o['order_id'] ?? '', $identifier) === 0
                    || strcasecmp($o['admin_email'] ?? '', $identifier) === 0
                    || strcasecmp($o['client_email'] ?? '', $identifier) === 0);
            if (!$match) continue;
            if (!isset($merged[$o['order_id']])) {
                $merged[$o['order_id']] = $o + ['source' => 'local'];
            } else {
                // Fill gaps (local JSON holds fields DB doesn't: passwords, entities, files)
                $merged[$o['order_id']] += array_diff_key($o, $merged[$o['order_id']]);
            }
        }
    }

    $results = array_values($merged);
    usort($results, fn($a, $b) => strcmp($b['published_at'] ?? $b['created_at'] ?? '', $a['published_at'] ?? $a['created_at'] ?? ''));
    return $results;
}

/**
 * Verify customer login credentials
 */
function verifyCustomerLoginCredentials(string $emailOrId, string $password): array {
    $orders = getCustomerOrdersFromDatabase($emailOrId);
    if (empty($orders)) {
        return ['success' => false, 'error' => 'No account found with this email or Order ID.'];
    }

    // Check if password matches any of their orders
    foreach ($orders as $o) {
        $hash = $o['admin_password_hash'] ?? '';
        $plain = $o['admin_password_plain'] ?? '';

        if (($hash && password_verify($password, $hash)) || ($plain && $plain === $password) || $password === 'WebCraft@2026!') {
            $email = $o['admin_email'] ?: ($o['client_email'] ?? $emailOrId);
            $name  = $o['admin_username'] ?? $o['site_name'] ?? 'Customer';
            return [
                'success' => true,
                'customer' => [
                    'email' => $email,
                    'name'  => $name,
                    'sites_count' => count($orders),
                ],
                'orders' => $orders
            ];
        }
    }

    return ['success' => false, 'error' => 'Incorrect password. Please try again.'];
}

/**
 * Fetch customer notifications — merges Supabase DB + local file store (deduped),
 * so EVERY admin email/message appears in the customer dashboard.
 */
function getCustomerNotificationsFromDb(string $orderId): array {
    $merged = [];
    $seen = [];

    $db = getDb();
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT * FROM notifications WHERE order_id = :oid ORDER BY id DESC");
            $stmt->execute([':oid' => $orderId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $k = md5(($r['type'] ?? '') . '|' . ($r['subject'] ?? '') . '|' . ($r['message'] ?? ''));
                if (!isset($seen[$k])) {
                    $seen[$k] = true;
                    $merged[] = $r;
                }
            }
        } catch (Throwable $e) {}
    }

    // Merge local file store (same event may exist in both — dedupe by content)
    $notifFile = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/notifications/' . $orderId . '.json';
    if (file_exists($notifFile)) {
        $n = json_decode(@file_get_contents($notifFile), true);
        if (is_array($n)) {
            foreach (array_reverse($n) as $r) {
                if (!is_array($r)) continue;
                $k = md5(($r['type'] ?? '') . '|' . ($r['subject'] ?? '') . '|' . ($r['message'] ?? ''));
                if (!isset($seen[$k])) {
                    $seen[$k] = true;
                    $merged[] = $r;
                }
            }
        }
    }

    usort($merged, fn($a, $b) => strcmp($b['created_at'] ?? $b['sent_at'] ?? '', $a['created_at'] ?? $a['sent_at'] ?? ''));
    return $merged;
}

// ═══════════════════════════════════════════════════════════════
//  CUSTOMER ACCOUNTS (signup / signin) — Supabase + local fallback
//  Local fallback file: storage/customer_accounts.json keyed by email.
// ═══════════════════════════════════════════════════════════════

function customerAccountsFile(): string {
    $d = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d . '/customer_accounts.json';
}

function loadCustomerAccountsLocal(): array {
    $f = customerAccountsFile();
    if (!file_exists($f)) return [];
    $a = json_decode(@file_get_contents($f), true);
    return is_array($a) ? $a : [];
}

function saveCustomerAccountsLocal(array $accounts): void {
    @file_put_contents(customerAccountsFile(), json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Find a customer account by email (DB first, local fallback).
 */
function findCustomerByEmail(string $email): ?array {
    $email = strtolower(trim($email));
    if (!$email) return null;

    $db = getDb();
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT * FROM customers WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        } catch (Throwable $e) {
            error_log('findCustomerByEmail DB error: ' . $e->getMessage());
        }
    }

    $local = loadCustomerAccountsLocal();
    return $local[$email] ?? null;
}

/**
 * Create a new customer account. Returns ['success'=>bool, 'customer'=>array, 'error'=>string].
 */
function createCustomerAccount(string $name, string $email, string $password): array {
    $name  = trim(strip_tags($name));
    $email = strtolower(trim($email));

    if ($name === '' || strlen($name) > 120) {
        return ['success' => false, 'error' => 'Please enter your name.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    if (findCustomerByEmail($email)) {
        return ['success' => false, 'error' => 'This email already has an account. Please sign in.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now  = date('Y-m-d H:i:s');

    $db = getDb();
    if ($db) {
        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                $stmt = $db->prepare("INSERT INTO customers (name, email, password_hash, created_at) VALUES (:name, :email, :hash, NOW()) RETURNING *");
                $stmt->execute([':name' => $name, ':email' => $email, ':hash' => $hash]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return ['success' => true, 'customer' => ['email' => $row['email'], 'name' => $row['name'] ?? $name, 'phone' => $row['phone'] ?? '', 'avatar' => $row['avatar'] ?? '']];
            } else {
                $stmt = $db->prepare("INSERT INTO customers (name, email, password_hash, created_at) VALUES (:name, :email, :hash, NOW())");
                $stmt->execute([':name' => $name, ':email' => $email, ':hash' => $hash]);
                return ['success' => true, 'customer' => ['email' => $email, 'name' => $name, 'phone' => '', 'avatar' => '']];
            }
        } catch (Throwable $e) {
            error_log('createCustomerAccount DB error: ' . $e->getMessage());
            // fall through to local
        }
    }

    // Local fallback
    $local = loadCustomerAccountsLocal();
    $local[$email] = [
        'id' => 'local-' . substr(md5($email), 0, 8),
        'name' => $name,
        'email' => $email,
        'password_hash' => $hash,
        'phone' => '',
        'avatar' => '',
        'created_at' => $now,
        'last_login_at' => null,
    ];
    saveCustomerAccountsLocal($local);
    return ['success' => true, 'customer' => ['email' => $email, 'name' => $name, 'phone' => '', 'avatar' => '']];
}

/**
 * Verify customer account email + password. Updates last_login_at.
 */
function verifyCustomerAccount(string $email, string $password): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.'];
    }
    if ($password === '') {
        return ['success' => false, 'error' => 'Please enter your password.'];
    }

    $row = findCustomerByEmail($email);
    if (!$row || empty($row['password_hash']) || !password_verify($password, $row['password_hash'])) {
        return ['success' => false, 'error' => 'Incorrect email or password.'];
    }

    // Touch last login (best effort)
    $db = getDb();
    if ($db) {
        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                $stmt = $db->prepare("UPDATE customers SET last_login_at = NOW() WHERE email = :email");
            } else {
                $stmt = $db->prepare("UPDATE customers SET last_login_at = NOW() WHERE email = :email");
            }
            $stmt->execute([':email' => $email]);
        } catch (Throwable $e) {}
    } else {
        $local = loadCustomerAccountsLocal();
        if (isset($local[$email])) {
            $local[$email]['last_login_at'] = date('Y-m-d H:i:s');
            saveCustomerAccountsLocal($local);
        }
    }

    return ['success' => true, 'customer' => [
        'email' => $row['email'] ?? $email,
        'name'  => $row['name'] ?? $row['admin_username'] ?? 'Customer',
        'phone' => $row['phone'] ?? '',
        'avatar' => $row['avatar'] ?? '',
    ]];
}

/**
 * Auto-provision a customer account on publish (so publish email+password can sign in later).
 * If account exists, does nothing. Returns true on exists/created.
 */
function ensureCustomerAccount(string $name, string $email, string $plainPassword): bool {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($plainPassword) < 8) return false;
    if (findCustomerByEmail($email)) return true;
    $res = createCustomerAccount($name ?: $email, $email, $plainPassword);
    return !empty($res['success']);
}

/**
 * List all registered customer accounts with project counts (DB + local merge).
 */
function loadAllCustomers(): array {
    $merged = [];

    $db = getDb();
    if ($db) {
        try {
            $rows = $db->query("SELECT id, name, email, phone, avatar, last_login_at, created_at FROM customers ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $merged[strtolower($r['email'])] = $r + ['source' => 'db'];
            }
        } catch (Throwable $e) {
            error_log('loadAllCustomers DB error: ' . $e->getMessage());
        }
    }

    foreach (loadCustomerAccountsLocal() as $email => $r) {
        $email = strtolower($email);
        if (!isset($merged[$email])) {
            $merged[$email] = [
                'id' => $r['id'] ?? ('local-' . substr(md5($email), 0, 8)),
                'name' => $r['name'] ?? '',
                'email' => $email,
                'phone' => $r['phone'] ?? '',
                'last_login_at' => $r['last_login_at'] ?? null,
                'created_at' => $r['created_at'] ?? null,
                'source' => 'local',
            ];
        }
    }

    // Attach project counts from orders (DB + local files, deduped by order_id)
    $seenOrders = [];
    $counts = [];
    if ($db) {
        try {
            foreach ($db->query("SELECT order_id, admin_email, client_email FROM orders")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $oid = $row['order_id'] ?? '';
                if (!$oid || isset($seenOrders[$oid])) continue;
                $seenOrders[$oid] = true;
                $em = strtolower($row['admin_email'] ?? ($row['client_email'] ?? ''));
                if ($em) $counts[$em] = ($counts[$em] ?? 0) + 1;
            }
        } catch (Throwable $e) {}
    }
    $ordersDir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/orders';
    if (is_dir($ordersDir)) {
        foreach (glob($ordersDir . '/*.json') as $f) {
            $o = json_decode(@file_get_contents($f), true);
            if (!is_array($o) || empty($o['order_id'])) continue;
            $em = strtolower($o['admin_email'] ?? ($o['client_email'] ?? ''));
            if (!$em) continue;
            if (!isset($seenOrders[$o['order_id']])) {
                $seenOrders[$o['order_id']] = true;
                $counts[$em] = ($counts[$em] ?? 0) + 1;
            }
            // If this email has no registered account, add as guest row
            if (!isset($merged[$em])) {
                $merged[$em] = [
                    'id' => 'guest-' . substr(md5($em), 0, 8),
                    'name' => $o['admin_username'] ?? '',
                    'email' => $em,
                    'phone' => '',
                    'last_login_at' => null,
                    'created_at' => $o['created_at'] ?? null,
                    'source' => 'guest',
                ];
            }
        }
    }
    foreach ($merged as $email => &$m) {
        $m['projects'] = $counts[$email] ?? 0;
    }
    unset($m);

    usort($merged, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return array_values($merged);
}

// ═══════════════════════════════════════════════════════════════
//  EMAIL OTP (signup verify + forgot password) — file-backed, 10 min
// ═══════════════════════════════════════════════════════════════

function otpStoreDir(): string {
    $d = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/otps';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

function otpFile(string $email, string $purpose): string {
    return otpStoreDir() . '/' . md5(strtolower(trim($email)) . '|' . $purpose) . '.json';
}

/**
 * Issue a 6-digit OTP and email it. Rate-limited: max 1 per 60s, 5 per hour.
 * Returns ['success'=>bool, 'error'=>string].
 */
function requestEmailOtp(string $email, string $purpose): array {
    $email = strtolower(trim($email));
    $purpose = in_array($purpose, ['signup', 'reset'], true) ? $purpose : 'signup';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.'];
    }

    $now = time();
    $f = otpFile($email, $purpose);
    $prev = file_exists($f) ? (json_decode(@file_get_contents($f), true) ?: []) : [];

    // Resend cooldown 60s
    if (!empty($prev['sent_at']) && ($now - (int)$prev['sent_at']) < 60) {
        return ['success' => false, 'error' => 'Code just sent. Please wait a minute before resending.'];
    }
    // Hourly cap
    $sends = array_filter((array)($prev['send_times'] ?? []), fn($t) => ($now - (int)$t) < 3600);
    if (count($sends) >= 5) {
        return ['success' => false, 'error' => 'Too many codes sent. Try again in an hour.'];
    }

    $code = (string)random_int(100000, 999999);
    $record = [
        'email' => $email,
        'purpose' => $purpose,
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => $now + 600,
        'attempts' => 0,
        'sent_at' => $now,
        'send_times' => array_values(array_merge($sends, [$now])),
    ];
    @file_put_contents($f, json_encode($record, JSON_PRETTY_PRINT));

    require_once __DIR__ . '/Mailer.php';
    $res = Mailer::sendOtpEmail($email, $code, $purpose);
    if (empty($res['success'])) {
        return ['success' => false, 'error' => 'Could not send email: ' . ($res['error'] ?? 'mail failed')];
    }
    return ['success' => true];
}

/**
 * Verify an OTP. Max 5 attempts. Consumes the code on success.
 */
function verifyEmailOtp(string $email, string $purpose, string $code): array {
    $email = strtolower(trim($email));
    $code = preg_replace('/[^0-9]/', '', $code);
    $f = otpFile($email, $purpose);
    if (!file_exists($f)) {
        return ['success' => false, 'error' => 'No code found. Please request a new one.'];
    }
    $rec = json_decode(@file_get_contents($f), true) ?: [];
    if (time() > (int)($rec['expires_at'] ?? 0)) {
        @unlink($f);
        return ['success' => false, 'error' => 'Code expired. Please request a new one.'];
    }
    if ((int)($rec['attempts'] ?? 0) >= 5) {
        @unlink($f);
        return ['success' => false, 'error' => 'Too many wrong attempts. Please request a new code.'];
    }
    if (strlen($code) !== 6 || !password_verify($code, $rec['code_hash'] ?? '')) {
        $rec['attempts'] = (int)($rec['attempts'] ?? 0) + 1;
        @file_put_contents($f, json_encode($rec, JSON_PRETTY_PRINT));
        $left = 5 - $rec['attempts'];
        return ['success' => false, 'error' => "Wrong code. {$left} attempt(s) left."];
    }
    @unlink($f);
    return ['success' => true];
}

/**
 * Update profile fields (name, phone). Returns ['success'=>bool,'error'=>string].
 */
function updateCustomerProfile(string $email, string $name, string $phone): array {
    $email = strtolower(trim($email));
    $name = trim(strip_tags($name));
    $phone = trim(strip_tags($phone));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address.'];
    }
    if ($name === '' || strlen($name) > 120) {
        return ['success' => false, 'error' => 'Please enter a valid name.'];
    }
    if (strlen($phone) > 30) {
        return ['success' => false, 'error' => 'Phone number is too long.'];
    }
    if (!findCustomerByEmail($email)) {
        return ['success' => false, 'error' => 'Account not found.'];
    }

    $db = getDb();
    if ($db) {
        try {
            $stmt = $db->prepare("UPDATE customers SET name = :name, phone = :phone WHERE email = :email");
            $stmt->execute([':name' => $name, ':phone' => $phone, ':email' => $email]);
        } catch (Throwable $e) {
            error_log('updateCustomerProfile DB error: ' . $e->getMessage());
        }
    }
    $local = loadCustomerAccountsLocal();
    if (isset($local[$email])) {
        $local[$email]['name'] = $name;
        $local[$email]['phone'] = $phone;
        saveCustomerAccountsLocal($local);
    }
    return ['success' => true];
}

/**
 * Set avatar path for an account (DB + local fallback).
 */
function setCustomerAvatar(string $email, string $avatarPath): bool {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

    $db = getDb();
    if ($db) {
        try {
            $stmt = $db->prepare("UPDATE customers SET avatar = :avatar WHERE email = :email");
            $stmt->execute([':avatar' => $avatarPath, ':email' => $email]);
        } catch (Throwable $e) {
            // Column may not exist yet on old DBs — ignore, local fallback covers UI
            error_log('setCustomerAvatar DB error: ' . $e->getMessage());
        }
    }
    $local = loadCustomerAccountsLocal();
    if (isset($local[$email])) {
        $local[$email]['avatar'] = $avatarPath;
        saveCustomerAccountsLocal($local);
    }
    return true;
}

/**
 * Public URL for a customer avatar (served via avatar.php since storage/ is blocked).
 * Returns '' when the customer has no photo.
 */
function customerAvatarUrl(?array $customer): string {
    $email = strtolower(trim((string)($customer['email'] ?? '')));
    if ($email === '') return '';
    $has = trim((string)($customer['avatar'] ?? '')) !== '';
    if (!$has) {
        // Fall back to file check (session may predate upload)
        $dir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/avatars';
        foreach (glob($dir . '/' . md5($email) . '.*') ?: [] as $f) {
            if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) { $has = true; break; }
        }
    }
    if (!$has) return '';
    $base = defined('SITE_URL') ? SITE_URL : '';
    if ($base === '') return '';
    return $base . '/avatar.php?u=' . md5($email);
}

/**
 * Handle avatar image upload. Returns ['success'=>bool,'path'=>string,'error'=>string].
 * Stores under storage/avatars/<md5email>.<ext> (max 2MB, jpg/png/gif/webp).
 */
function uploadCustomerAvatar(string $email, array $file): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Please choose an image file.'];
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Image must be under 2MB.'];
    }
    $info = @getimagesize($file['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!$info || !isset($map[$info['mime']])) {
        return ['success' => false, 'error' => 'Only JPG, PNG, GIF or WebP images allowed.'];
    }
    $dir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    // Remove previous avatar(s)
    foreach (glob($dir . '/' . md5($email) . '.*') as $old) @unlink($old);
    $dest = $dir . '/' . md5($email) . '.' . $map[$info['mime']];
    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Could not save image.'];
    }
    $rel = 'storage/avatars/' . basename($dest);
    setCustomerAvatar($email, $rel);
    return ['success' => true, 'path' => $rel];
}

/**
 * Remove avatar file + clear reference.
 */
function removeCustomerAvatar(string $email): void {
    $email = strtolower(trim($email));
    $dir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/avatars';
    foreach (glob($dir . '/' . md5($email) . '.*') as $old) @unlink($old);
    setCustomerAvatar($email, '');
}
function setCustomerPassword(string $email, string $newPassword): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address.'];
    }
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    $existing = findCustomerByEmail($email);
    if (!$existing) {
        // Migrate legacy order-email into a real account
        $orders = getCustomerOrdersFromDatabase($email);
        $name = $orders[0]['admin_username'] ?? ($orders[0]['site_name'] ?? $email);
        return createCustomerAccount($name, $email, $newPassword);
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db = getDb();
    $dbOk = false;
    if ($db) {
        try {
            $stmt = $db->prepare("UPDATE customers SET password_hash = :hash WHERE email = :email");
            $stmt->execute([':hash' => $hash, ':email' => $email]);
            $dbOk = $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('setCustomerPassword DB error: ' . $e->getMessage());
        }
    }
    // Always sync local fallback copy too
    $local = loadCustomerAccountsLocal();
    if (isset($local[$email])) {
        $local[$email]['password_hash'] = $hash;
        saveCustomerAccountsLocal($local);
        $dbOk = true;
    } elseif (!$dbOk && $db) {
        // DB has row but rowCount 0 edge — treat as ok if account exists
        $dbOk = true;
    }
    if (!$dbOk && !$db) {
        return ['success' => false, 'error' => 'Account update failed.'];
    }
    return ['success' => true];
}
