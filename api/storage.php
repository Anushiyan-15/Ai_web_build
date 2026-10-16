<?php
// ═══════════════════════════════════════════════════════════════
//  api/storage.php — Customer storage quota + per-GB billing API
//
//  REST mapping (see .htaccess; direct PHP fallback uses ?resource=):
//    GET    /api/customer/:email/storage     → usage summary
//    GET    /api/customer/:email/files       → file list
//    POST   /api/customer/:email/files       → quota-checked upload
//    DELETE /api/customer/:email/files/:id   → delete file
//    POST   /api/customer/:email/buy-storage → create PayHere order
//    GET    /api/customer/:email/invoices    → invoices (both ledgers)
//    GET    /api/admin/plans                 → list plans
//    PUT    /api/admin/plans/:id             → update plan
//    GET    /api/admin/customers?q=email     → search customers
//    POST   /api/admin/customers/:email/adjust|override → admin adjust
//
//  Auth: customer endpoints require $_SESSION['customer_user'] and the
//  route email must equal the session email. Admin endpoints require the
//  platform-admin session + RBAC permission. Mutations require CSRF.
//  All SQL is prepared; errors are generic (details go to error_log).
// ═══════════════════════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/config/storage.php';
require_once dirname(__DIR__) . '/includes/StorageService.php';
require_once dirname(__DIR__) . '/includes/Csrf.php';

function stg_json(int $http, array $payload): void {
    http_response_code($http);
    echo json_encode($payload);
    exit;
}

function stg_customer_email(): ?string {
    $e = $_SESSION['customer_user']['email'] ?? '';
    $e = strtolower(trim((string)$e));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
}

function stg_require_customer_match(string $routeEmail): string {
    $sess = stg_customer_email();
    if (!$sess) stg_json(401, ['success' => false, 'error' => 'Not signed in.']);
    $route = strtolower(trim($routeEmail));
    if (!filter_var($route, FILTER_VALIDATE_EMAIL) || strcasecmp($route, $sess) !== 0) {
        stg_json(403, ['success' => false, 'error' => 'Forbidden: email mismatch.']);
    }
    return $sess;
}

function stg_require_admin(string $perm): string {
    require_once dirname(__DIR__) . '/platform-admin/roles.php';
    if (empty($_SESSION['platform_admin_logged_in']) || $_SESSION['platform_admin_logged_in'] !== true) {
        stg_json(401, ['success' => false, 'error' => 'Admin sign-in required.']);
    }
    if (!function_exists('has_permission') || !has_permission($perm)) {
        stg_json(403, ['success' => false, 'error' => 'Forbidden: missing permission ' . $perm . '.']);
    }
    return (string)($_SESSION['platform_admin_user'] ?? 'admin');
}

function stg_require_csrf(array $json): void {
    if (!csrf_validate(csrf_token_from_request($json))) {
        stg_json(403, ['success' => false, 'error' => 'Invalid CSRF token.']);
    }
}

function stg_read_json(): array {
    $raw = @file_get_contents('php://input');
    if (!$raw) return [];
    $d = @json_decode($raw, true);
    return is_array($d) ? $d : [];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
// PUT/DELETE via method override for form clients.
if ($method === 'POST' && !empty($_POST['_method'])) {
    $method = strtoupper((string)$_POST['_method']);
}

$resource = trim($_GET['resource'] ?? ($_GET['action'] ?? ''));
$routeEmail = trim($_GET['email'] ?? '');
$routeId = trim($_GET['id'] ?? '');
$json = in_array($method, ['POST', 'PUT', 'PATCH'], true) ? stg_read_json() : [];

// ── CUSTOMER: summary ─────────────────────────────────────────
if ($resource === 'storage' || $resource === 'summary') {
    if ($method !== 'GET') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    $email = stg_require_customer_match($routeEmail ?: ($json['email'] ?? ''));
    $refresh = StorageService::recalculateUsage($email);
    if (empty($refresh['success'])) {
        error_log('storage summary recalculation failed for ' . $email . ': ' . ($refresh['error'] ?? 'unknown error'));
        stg_json(500, ['success' => false, 'error' => 'Could not calculate storage usage.']);
    }
    $s = StorageService::getSummary($email);
    if (!$s) stg_json(404, ['success' => false, 'error' => 'Account not found.']);
    stg_json(200, ['success' => true, 'summary' => $s, 'csrf_token' => csrf_token()]);
}

// ── CUSTOMER: files list / upload ─────────────────────────────
if ($resource === 'files') {
    $email = stg_require_customer_match($routeEmail);
    if ($method === 'GET') {
        $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $refresh = StorageService::recalculateUsage($email);
        if (empty($refresh['success'])) {
            error_log('storage file-list recalculation failed for ' . $email . ': ' . ($refresh['error'] ?? 'unknown error'));
            stg_json(500, ['success' => false, 'error' => 'Could not calculate storage usage.']);
        }
        stg_json(200, ['success' => true, 'files' => StorageService::listFiles($email, $limit, $offset), 'summary' => StorageService::getSummary($email)]);
    }
    if ($method === 'POST') {
        stg_require_csrf($json);
        $file = $_FILES['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            stg_json(400, ['success' => false, 'error' => 'Please choose a file to upload.']);
        }
        $mime = (string)($file['type'] ?? '');
        // Trust server-side detection over the client header when available.
        if (function_exists('finfo_open')) {
            try {
                $fi = finfo_open(FILEINFO_MIME_TYPE);
                $det = $fi ? finfo_file($fi, $file['tmp_name']) : false;
                if ($det) $mime = $det;
                if ($fi) finfo_close($fi);
            } catch (Throwable $e) {}
        }
        $r = StorageService::storeUpload($email, $file['tmp_name'], (string)($file['name'] ?? 'file'), $mime);
        stg_json($r['http'] ?? 200, $r);
    }
    stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
}

// ── CUSTOMER: delete file ─────────────────────────────────────
if ($resource === 'file') {
    if ($method !== 'DELETE' && !($method === 'POST' && ($json['action'] ?? $_POST['action'] ?? '') === 'delete')) {
        stg_json(405, ['success' => false, 'error' => 'Method not allowed. Use DELETE.']);
    }
    stg_require_csrf($json);
    $email = stg_require_customer_match($routeEmail);
    $fid = (int)($routeId ?: ($json['id'] ?? $_POST['id'] ?? 0));
    if ($fid <= 0) stg_json(400, ['success' => false, 'error' => 'Invalid file id.']);
    $r = StorageService::deleteFile($email, $fid);
    stg_json($r['http'] ?? 200, $r);
}

// ── CUSTOMER: buy extra storage (PayPal order create) ──────────
if ($resource === 'buy-storage' || $resource === 'create_storage_order') {
    if ($method !== 'POST') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    stg_require_csrf($json);
    $email = stg_require_customer_match($routeEmail ?: ($json['email'] ?? ''));
    $gb = max(1, min(100, (int)($json['gb'] ?? $_POST['gb'] ?? 1)));
    $summary = StorageService::getSummary($email);
    if (!$summary) stg_json(404, ['success' => false, 'error' => 'Account not found.']);
    if (!empty($summary['suspended'])) stg_json(403, ['success' => false, 'error' => 'Account suspended.']);
    $quote = StorageService::quoteExtraStorage($summary['plan']['slug'] ?? 'free', $gb);
    if (!$quote) stg_json(500, ['success' => false, 'error' => 'Plan pricing unavailable.']);
    require_once dirname(__DIR__) . '/includes/PayHereService.php';
    $payHere = new PayHereService();
    $ref = 'STG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    $returnUrl = $base . '/customer-portal.php?action=storage_return&ref=' . urlencode($ref);
    $cancelUrl = $base . '/customer-portal.php?action=storage_cancel&ref=' . urlencode($ref);
    $desc = 'WebCraft AI - ' . $gb . ' GB Extra Storage for ' . $email . ' (storage only; no site changes)';
    $created = $payHere->createOrder($ref, (float)$quote['total'], $desc, $returnUrl, $cancelUrl);
    if (empty($created['success'])) {
        stg_json(502, ['success' => false, 'error' => $created['error'] ?? 'Payment gateway error.']);
    }
    $payHereOrderId = $created['order_id'] ?? '';
    StorageService::savePendingOrder([
        'ref' => $ref, 'email' => $email, 'gb' => $gb,
        'amount' => $quote['total'], 'currency' => $quote['currency'],
        'plan_slug' => $quote['plan_slug'], 'status' => 'created',
        'payhere_order_id' => $payHereOrderId,
        'paypal_order_id' => $payHereOrderId,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    stg_json(200, ['success' => true, 'ref' => $ref, 'gb' => $gb,
        'amount' => $quote['total'], 'currency' => $quote['currency'],
        'rate_per_gb' => $quote['rate'], 'checkout_url' => $created['checkout_url'] ?? '',
        'mode' => $created['mode'] ?? 'payhere']);
}

// ── CUSTOMER: capture verified storage payment ────────────────
if ($resource === 'capture_storage' || $resource === 'capture-storage') {
    if ($method !== 'POST') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    stg_require_csrf($json);
    $email = stg_require_customer_match($routeEmail ?: ($json['email'] ?? ''));
    $ref = preg_replace('/[^A-Za-z0-9-]/', '', (string)($json['ref'] ?? $_POST['ref'] ?? ''));
    $token = trim((string)($json['payhere_order_id'] ?? $json['paypal_order_id'] ?? $_POST['payhere_order_id'] ?? $_POST['paypal_order_id'] ?? $_GET['token'] ?? ''));
    if ($ref === '' || $token === '') stg_json(400, ['success' => false, 'error' => 'Missing reference or payment token.']);
    $pend = StorageService::loadPendingOrder($ref);
    if (!$pend || strcasecmp((string)($pend['email'] ?? ''), $email) !== 0) {
        stg_json(404, ['success' => false, 'error' => 'Order not found.']);
    }
    if (($pend['status'] ?? '') === 'captured') {
        stg_json(200, ['success' => true, 'duplicate' => true, 'summary' => StorageService::getSummary($email)]);
    }
    require_once dirname(__DIR__) . '/includes/PayHereService.php';
    $payHere = new PayHereService();
    $cap = $payHere->captureOrder($token);
    if (empty($cap['success']) || ($cap['status'] ?? '') !== 'COMPLETED') {
        stg_json(402, ['success' => false, 'error' => $cap['error'] ?? 'Payment not completed. Quota unchanged.']);
    }
    $payRef = (string)($cap['capture_id'] ?? $token);
    $granted = StorageService::recordPurchase($email, (int)$pend['gb'], (float)$pend['amount'], (string)$pend['currency'], $payRef,
        'Storage purchase ' . $ref . ' (' . $pend['gb'] . ' GB)');
    if (empty($granted['success'])) stg_json(500, $granted);
    StorageService::markPendingCaptured($ref, $payRef);
    stg_json(200, ['success' => true, 'duplicate' => !empty($granted['duplicate']), 'summary' => $granted['summary'] ?? StorageService::getSummary($email)]);
}

// ── CUSTOMER: invoices (subscription + storage ledgers) ──────
if ($resource === 'invoices') {
    if ($method !== 'GET') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    $email = stg_require_customer_match($routeEmail);
    stg_json(200, ['success' => true, 'invoices' => StorageService::invoices($email)]);
}

// ── CUSTOMER: storage transactions ────────────────────────────
if ($resource === 'transactions') {
    if ($method !== 'GET') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    $email = stg_require_customer_match($routeEmail);
    stg_json(200, ['success' => true, 'transactions' => StorageService::listTransactions($email)]);
}

// ── CUSTOMER: auto-billing preference (opt-in, honest support flag)
if ($resource === 'auto-bill') {
    if ($method !== 'POST') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    stg_require_csrf($json);
    $email = stg_require_customer_match($routeEmail ?: ($json['email'] ?? ''));
    $enable = !empty($json['enabled'] ?? $_POST['enabled'] ?? false);
    if ($enable && empty($json['consent']) && empty($_POST['consent'])) {
        stg_json(422, ['success' => false, 'error' => 'Explicit consent is required to enable auto-billing.']);
    }
    $db = StorageService::db();
    if (!$db) stg_json(503, ['success' => false, 'error' => 'Storage service unavailable.']);
    StorageService::ensureUsageRow($email);
    $db->prepare("UPDATE customer_usage SET auto_bill_enabled = :v WHERE email = :e")->execute([':v' => $enable ? 1 : 0, ':e' => $email]);
    StorageService::logTxn($db, $email, 'ADMIN_ADJUST', 0, null, null, null,
        '[customer] Auto-billing preference ' . ($enable ? 'enabled (consented)' : 'disabled') . '. Gateway auto-charge supported: ' . (STORAGE_AUTOBILL_SUPPORTED ? 'yes' : 'no'));
    stg_json(200, ['success' => true, 'auto_bill_enabled' => $enable,
        'auto_bill_supported' => (bool)STORAGE_AUTOBILL_SUPPORTED,
        'notice' => STORAGE_AUTOBILL_SUPPORTED ? '' : 'The configured gateway (PayHere Checkout) requires manual approval per payment, so automatic charges are not performed. Enable the preference to record consent; renewals use manual checkout.']);
}

// ── ADMIN: list plans ─────────────────────────────────────────
if ($resource === 'admin-plans') {
    if ($method !== 'GET') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    stg_require_admin('billing');
    stg_json(200, ['success' => true, 'plans' => StorageService::getAllPlans(false)]);
}

// ── ADMIN: create / update plan ───────────────────────────────
if ($resource === 'admin-plan') {
    $admin = stg_require_admin('plans_edit');
    $db = StorageService::db();
    if (!$db) stg_json(503, ['success' => false, 'error' => 'DB unavailable.']);
    if ($method === 'GET') {
        $p = StorageService::getPlan($routeId !== '' ? (is_numeric($routeId) ? (int)$routeId : $routeId) : '');
        if (!$p) stg_json(404, ['success' => false, 'error' => 'Plan not found.']);
        stg_json(200, ['success' => true, 'plan' => $p]);
    }
    if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    stg_require_csrf($json);
    $in = array_merge($_POST, $json);
    $fields = [
        'slug' => strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string)($in['slug'] ?? ''))),
        'name' => substr(trim(strip_tags((string)($in['name'] ?? ''))), 0, 128),
        'storage_limit_mb' => max(0, (int)($in['storage_limit_mb'] ?? 0)),
        'bandwidth_limit_mb' => max(0, (int)($in['bandwidth_limit_mb'] ?? 0)),
        'max_sites' => max(0, (int)($in['max_sites'] ?? 0)),
        'price_per_extra_gb' => max(0, round((float)($in['price_per_extra_gb'] ?? 0), 2)),
        'currency' => strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($in['currency'] ?? 'USD'))) ?: 'USD',
        'is_active' => !empty($in['is_active']) ? 1 : 0,
    ];
    if ($fields['slug'] === '' || $fields['name'] === '') stg_json(422, ['success' => false, 'error' => 'Plan slug and name are required.']);
    try {
        if ($routeId !== '') {
            // Update existing (slug immutable to protect historical refs).
            $db->prepare("UPDATE plans SET name=:n, storage_limit_mb=:s, bandwidth_limit_mb=:b, max_sites=:m, price_per_extra_gb=:r, currency=:c, is_active=:a, updated_at=NOW() WHERE id=:id")
               ->execute([':n' => $fields['name'], ':s' => $fields['storage_limit_mb'], ':b' => $fields['bandwidth_limit_mb'], ':m' => $fields['max_sites'], ':r' => $fields['price_per_extra_gb'], ':c' => $fields['currency'], ':a' => $fields['is_active'], ':id' => (int)$routeId]);
            StorageService::auditLog($admin, 'plan_updated', "Plan id $routeId updated: " . json_encode($fields));
            stg_json(200, ['success' => true, 'plan' => StorageService::getPlan((int)$routeId)]);
        }
        $db->prepare("INSERT INTO plans (slug, name, storage_limit_mb, bandwidth_limit_mb, max_sites, price_per_extra_gb, currency, is_active) VALUES (:slug,:n,:s,:b,:m,:r,:c,:a)")
           ->execute([':slug' => $fields['slug'], ':n' => $fields['name'], ':s' => $fields['storage_limit_mb'], ':b' => $fields['bandwidth_limit_mb'], ':m' => $fields['max_sites'], ':r' => $fields['price_per_extra_gb'], ':c' => $fields['currency'], ':a' => $fields['is_active']]);
        StorageService::auditLog($admin, 'plan_created', "Plan {$fields['slug']} created.");
        stg_json(201, ['success' => true, 'plan' => StorageService::getPlan($fields['slug'])]);
    } catch (Throwable $e) {
        error_log('admin-plan save failed: ' . $e->getMessage());
        stg_json(409, ['success' => false, 'error' => 'Could not save plan (slug may already exist).']);
    }
}

// ── ADMIN: search customers w/ storage overview ───────────────
if ($resource === 'admin-customers') {
    if ($method !== 'GET') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    stg_require_admin('tenants_view');
    $q = strtolower(trim($_GET['q'] ?? ''));
    $db = StorageService::db();
    if (!$db) stg_json(503, ['success' => false, 'error' => 'DB unavailable.']);
    try {
        if ($q === '') {
            $rows = $db->query("SELECT email, name, plan_id, status FROM customers ORDER BY email ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $st = $db->prepare("SELECT email, name, plan_id, status FROM customers WHERE LOWER(email) LIKE :q ORDER BY email ASC LIMIT 100");
            $st->execute([':q' => '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%']);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $out = [];
        foreach ($rows as $r) {
            $s = StorageService::getSummary($r['email']);
            $out[] = ['email' => $r['email'], 'name' => $r['name'], 'status' => $r['status'] ?? 'active', 'summary' => $s];
        }
        stg_json(200, ['success' => true, 'customers' => $out]);
    } catch (Throwable $e) {
        error_log('admin-customers search failed: ' . $e->getMessage());
        stg_json(500, ['success' => false, 'error' => 'Search failed.']);
    }
}

// ── ADMIN: adjust / override ──────────────────────────────────
if ($resource === 'admin-customer-action') {
    if ($method !== 'POST') stg_json(405, ['success' => false, 'error' => 'Method not allowed.']);
    $admin = stg_require_admin('billing_manage');
    stg_require_csrf($json);
    $in = array_merge($_POST, $json);
    $email = strtolower(trim($routeEmail ?: ($in['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) stg_json(422, ['success' => false, 'error' => 'Invalid customer email.']);
    $op = trim($_GET['op'] ?? ($in['op'] ?? 'adjust'));
    if (!in_array($op, ['adjust', 'override'], true)) stg_json(400, ['success' => false, 'error' => 'Unknown operation.']);
    $note = trim(strip_tags((string)($in['note'] ?? '')));
    if ($note === '') stg_json(422, ['success' => false, 'error' => 'An explanatory note is required.']);
    if (!StorageService::getCustomer($email)) stg_json(404, ['success' => false, 'error' => 'Customer not found.']);
    $ops = ['require_note' => true];
    if (isset($in['plan_slug'])) $ops['plan_slug'] = (string)$in['plan_slug'];
    if (array_key_exists('manual_limit_mb', $in)) $ops['manual_limit_mb'] = $in['manual_limit_mb'];
    if (!empty($in['grant_gb'])) $ops['grant_gb'] = (int)$in['grant_gb'];
    if (!empty($in['reconcile'])) $ops['reconcile'] = true;
    if (array_key_exists('suspend', $in)) $ops['suspend'] = !empty($in['suspend']);
    if (array_key_exists('auto_bill_enabled', $in)) $ops['auto_bill_enabled'] = !empty($in['auto_bill_enabled']);
    $r = StorageService::adminAdjust($email, $ops, $admin, $note);
    stg_json($r['http'] ?? 200, $r);
}

stg_json(404, ['success' => false, 'error' => 'Unknown storage resource.']);
