<?php
// ═══════════════════════════════════════════════════════════════
//  api/payment.php — PayHere Payment & Instant Publishing Engine
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/payhere.php';
require_once dirname(__DIR__) . '/includes/PayHereService.php';
require_once dirname(__DIR__) . '/includes/PayPalService.php';
require_once dirname(__DIR__) . '/includes/db.php';

$raw  = file_get_contents('php://input');
$req  = json_decode($raw, true) ?: [];
$action = $req['action'] ?? ($_GET['action'] ?? '');

$payHere = new PayHereService();

switch ($action) {
    case 'create_order':
        handleCreateOrder($req, $payHere);
        break;

    case 'capture_order':
        handleCaptureOrder($req, $payHere);
        break;

    case 'notify':
        handleNotify($req);
        break;

    case 'domain_choice':
        handleDomainChoice($req);
        break;

    case 'save_design':
        handleSaveDesign($req);
        break;

    case 'status':
        handleGetStatus($req);
        break;

    default:
        echo json_encode(['success' => false, 'error' => "Unknown payment action: $action"]);
        break;
}

function orderStorageDir(): string {
    $dir = defined('STORAGE_DIR') ? STORAGE_DIR . '/orders' : dirname(__DIR__) . '/storage/orders';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function orderStorageFile(string $orderRef): string {
    return orderStorageDir() . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $orderRef) . '.json';
}

function loadOrderRecord(string $orderRef): ?array {
    $file = orderStorageFile($orderRef);
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode((string)@file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function saveOrderRecord(array $order): bool {
    $orderRef = (string)($order['order_ref'] ?? $order['order_id'] ?? '');
    if ($orderRef === '') {
        return false;
    }
    $file = orderStorageFile($orderRef);
    $payload = json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return false;
    }
    return file_put_contents($file, $payload) !== false;
}

// ═══════════════════════════════════════════════════════════════
// ACTION HANDLERS
// ═══════════════════════════════════════════════════════════════

/**
 * 1. Create PayHere Order (and legacy PayPal compatibility)
 */
function handleCreateOrder(array $req, PayHereService $payHere) {
    $planId    = preg_replace('/[^a-z0-9_-]/i', '', $req['plan'] ?? 'pro');
    $amount    = floatval($req['amount'] ?? 19.00);
    $payMethod = strtolower(trim((string)($req['method'] ?? ($req['payment_method'] ?? PAYMENT_PROVIDER ?? 'payhere'))));
    $payMethod = $payMethod === '' ? 'payhere' : $payMethod;
    $bizName   = htmlspecialchars(trim((string)($req['biz_name'] ?? 'Website')));
    $html      = $req['html'] ?? '';

    if ($amount <= 0) {
        $amount = 19.00;
    }

    $orderRef = 'WBL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $returnUrl = (defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '') . '/builder.php?payment=success';
    $cancelUrl = (defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '') . '/builder.php?payment=cancel';
    $desc = "WebCraft AI - " . ucfirst($planId) . " Plan for " . $bizName;

    $service = ($payMethod === 'payhere') ? $payHere : new PayPalService();
    $res = $service->createOrder($orderRef, $amount, $desc, $returnUrl, $cancelUrl);

    if (!$res['success']) {
        echo json_encode($res);
        return;
    }

    $payHereOrderId = (string)($res['order_id'] ?? '');
    $orderData = [
        'order_ref'        => $orderRef,
        'order_id'         => $orderRef,
        'payhere_order_id' => $payHereOrderId,
        'paypal_order_id'  => $payHereOrderId,
        'plan'             => $planId,
        'amount'           => $amount,
        'currency'         => defined('PAYHERE_CURRENCY') ? PAYHERE_CURRENCY : (defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : 'USD'),
        'payment_method'   => $payMethod,
        'pay_method'       => $payMethod,
        'biz_name'         => $bizName,
        'status'           => 'pending',
        'payment_status'   => 'pending',
        'created_at'       => date('Y-m-d H:i:s'),
    ];

    if (!is_dir(STORAGE_DIR . '/orders')) {
        @mkdir(STORAGE_DIR . '/orders', 0755, true);
    }
    saveOrderRecord($orderData);

    if (!is_dir(STORAGE_DIR . '/designs')) {
        @mkdir(STORAGE_DIR . '/designs', 0755, true);
    }
    if (!empty($html)) {
        file_put_contents(STORAGE_DIR . '/designs/' . $orderRef . '.html', $html);
    }

    $pdo = getDb();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO orders (order_id, site_name, payment_method, package, amount, status, created_at)
                VALUES (:order_id, :site_name, :payment_method, :package, :amount, 'payment_pending', NOW())
            ");
            $stmt->execute([
                ':order_id'       => $orderRef,
                ':site_name'      => $bizName,
                ':payment_method' => $payMethod,
                ':package'        => ucfirst($planId),
                ':amount'         => $amount,
            ]);
        } catch (Exception $e) {
            error_log("DB insert failed: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success'          => true,
        'order_ref'        => $orderRef,
        'order_id'         => $orderRef,
        'payhere_order_id' => $payHereOrderId,
        'paypal_order_id'  => $payHereOrderId,
        'checkout_url'     => $res['checkout_url'] ?? $returnUrl,
        'mode'             => $res['mode'] ?? ($payMethod === 'payhere' ? 'payhere' : 'paypal')
    ]);
}

/**
 * 2. Capture Payment Order & Publish Website
 */
function handleCaptureOrder(array $req, PayHereService $payHere) {
    $orderId = trim((string)($req['order_ref'] ?? $req['order_id'] ?? ''));
    $payHereOrderId = trim((string)($req['payhere_order_id'] ?? $req['paypal_order_id'] ?? ''));

    $orderData = null;
    if ($orderId !== '') {
        $orderData = loadOrderRecord($orderId);
    }
    if (!$orderData && $payHereOrderId !== '') {
        if (isset($GLOBALS['STORAGE_DIR'])) {
            $ordersDir = orderStorageDir();
            $files = scandir($ordersDir);
            rsort($files);
            foreach ($files as $file) {
                if (!str_ends_with($file, '.json')) {
                    continue;
                }
                $content = json_decode((string)@file_get_contents($ordersDir . '/' . $file), true);
                if (is_array($content) && (($content['payhere_order_id'] ?? $content['paypal_order_id'] ?? '') === $payHereOrderId)) {
                    $orderData = $content;
                    $orderId = (string)($orderData['order_ref'] ?? $orderData['order_id'] ?? $orderId);
                    break;
                }
            }
        }
    }

    if (!$orderData) {
        echo json_encode(['success' => false, 'error' => 'Payment order not found']);
        return;
    }

    $payMethod = strtolower((string)($orderData['payment_method'] ?? $orderData['pay_method'] ?? PAYMENT_PROVIDER ?? 'payhere'));
    $service = ($payMethod === 'payhere') ? $payHere : new PayPalService();
    $paymentId = $payHereOrderId !== '' ? $payHereOrderId : ((string)($orderData['payhere_order_id'] ?? $orderData['paypal_order_id'] ?? ''));

    if ($paymentId === '') {
        echo json_encode(['success' => false, 'error' => 'Missing payment token']);
        return;
    }

    $captureRes = $service->captureOrder($paymentId);
    if (!$captureRes['success'] || ($captureRes['status'] ?? '') !== 'COMPLETED') {
        echo json_encode([
            'success' => false,
            'error' => $captureRes['error'] ?? 'Payment could not be captured',
        ]);
        return;
    }

    $bizName = $orderData['biz_name'] ?? 'Website';
    $orderRef = $orderId !== '' ? $orderId : ('WBL-' . date('Ymd-His'));
    $html = '';
    $designFile = STORAGE_DIR . '/designs/' . $orderRef . '.html';
    if (file_exists($designFile)) {
        $html = (string)@file_get_contents($designFile);
    }
    if (empty($html) && !empty($req['html'])) {
        $html = (string)$req['html'];
    }

    $rawSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $bizName), '-'));
    if ($rawSlug === '') {
        $rawSlug = 'site';
    }
    $slug = $rawSlug . '-' . substr(md5($orderRef), 0, 6);
    $siteDir = PUBLISHED_DIR . '/' . $slug;
    if (!is_dir($siteDir)) {
        @mkdir($siteDir, 0755, true);
    }
    file_put_contents($siteDir . '/index.html', $html);
    file_put_contents($siteDir . '/meta.json', json_encode([
        'biz_name'        => $bizName,
        'order_ref'       => $orderRef,
        'paypal_order_id' => $paymentId,
        'published_at'    => date('Y-m-d H:i:s'),
        'plan'            => $orderData['plan'] ?? 'pro',
    ], JSON_PRETTY_PRINT));

    $liveUrl = (defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '') . '/published/' . $slug . '/';
    if ($orderData) {
        $orderData['status'] = 'published';
        $orderData['payment_status'] = 'paid';
        $orderData['live_url'] = $liveUrl;
        $orderData['published_slug'] = $slug;
        $orderData['captured_at'] = date('Y-m-d H:i:s');
        $orderData['capture_id'] = $captureRes['capture_id'] ?? $paymentId;
        saveOrderRecord($orderData);
    }

    $pdo = getDb();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'published', updated_at = NOW() WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $orderRef]);
        } catch (Exception $e) {
            error_log('DB update failed: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'live_url' => $liveUrl,
        'order_ref' => $orderRef,
        'slug' => $slug,
        'message' => 'Payment captured and website published live!'
    ]);
}

function handleNotify(array $req): void {
    $payload = is_array($req) && !empty($req) ? $req : [];
    if (empty($payload)) {
        $raw = file_get_contents('php://input');
        parse_str($raw, $payload);
        if (empty($payload)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
    }

    $service = new PayHereService();
    if (!$service->verifyNotification($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid PayHere notification signature']);
        return;
    }

    $statusCode = (string)($payload['status_code'] ?? '2');
    $orderRef = (string)($payload['custom_1'] ?? $payload['order_ref'] ?? $payload['order_id'] ?? '');
    $payHereOrderId = (string)($payload['order_id'] ?? '');
    $order = $orderRef !== '' ? loadOrderRecord($orderRef) : null;

    if (!$order && $payHereOrderId !== '') {
        $ordersDir = orderStorageDir();
        $files = scandir($ordersDir);
        rsort($files);
        foreach ($files as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $content = json_decode((string)@file_get_contents($ordersDir . '/' . $file), true);
            if (is_array($content) && (($content['payhere_order_id'] ?? $content['paypal_order_id'] ?? '') === $payHereOrderId)) {
                $order = $content;
                $orderRef = (string)($order['order_ref'] ?? $order['order_id'] ?? $orderRef);
                break;
            }
        }
    }

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found for PayHere notification']);
        return;
    }

    $expectedAmount = number_format((float)($order['amount'] ?? 0), 2, '.', '');
    $actualAmount = number_format((float)($payload['amount'] ?? $payload['payhere_amount'] ?? 0), 2, '.', '');
    $expectedCurrency = strtoupper((string)($order['currency'] ?? PAYHERE_CURRENCY));
    $actualCurrency = strtoupper((string)($payload['currency'] ?? $payload['payhere_currency'] ?? ''));

    if ($expectedAmount !== $actualAmount || ($actualCurrency !== '' && $actualCurrency !== $expectedCurrency)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'PayHere amount/currency mismatch']);
        return;
    }

    if (in_array((string)($order['status'] ?? ''), ['paid', 'published'], true)) {
        echo json_encode(['success' => true, 'already_processed' => true, 'status' => $order['status']]);
        return;
    }

    $order['payhere_order_id'] = $payHereOrderId !== '' ? $payHereOrderId : ($order['payhere_order_id'] ?? '');
    $order['paypal_order_id'] = $order['payhere_order_id'] !== '' ? $order['payhere_order_id'] : ($order['paypal_order_id'] ?? '');
    $order['payment_status'] = in_array($statusCode, ['2', 'success'], true) ? 'paid' : 'pending';
    $order['status'] = in_array($statusCode, ['2', 'success'], true) ? 'paid' : (($statusCode === '0') ? 'pending' : 'cancelled');

    if ($statusCode === '2') {
        $order['paid_at'] = date('Y-m-d H:i:s');
    }

    saveOrderRecord($order);

    echo json_encode([
        'success' => true,
        'order_ref' => $orderRef,
        'status_code' => $statusCode,
        'status' => $order['status'],
    ]);
}

function handleDomainChoice(array $req): void {
    $orderRef = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($req['order_ref'] ?? $req['order_id'] ?? ''));
    if ($orderRef === '') {
        echo json_encode(['success' => false, 'error' => 'Missing order id']);
        return;
    }

    $order = loadOrderRecord($orderRef);
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    $choice = strtolower((string)($req['option'] ?? $req['hosting_option'] ?? 'subdomain'));
    $customDomain = strtolower(trim((string)($req['custom_domain'] ?? '')));
    $newDomain = strtolower(trim((string)($req['new_domain'] ?? '')));

    $order['hosting_option'] = $choice;
    $order['domain_choice'] = $choice;
    $order['subdomain'] = $order['subdomain'] ?? ($order['slug'] ?? '');

    if ($choice === 'existing' && $customDomain !== '') {
        $order['custom_domain'] = $customDomain;
        $order['domain_status'] = 'pending_dns';
        $order['live_url'] = 'https://' . $customDomain . '/';
    } elseif ($choice === 'new_domain' && $newDomain !== '') {
        $order['custom_domain'] = $newDomain;
        $order['domain_status'] = 'pending_registration';
        $order['live_url'] = 'https://' . $newDomain . '/';
    } else {
        $order['custom_domain'] = $order['custom_domain'] ?? '';
        $order['domain_status'] = 'subdomain';
        $order['live_url'] = $order['live_url'] ?? (defined('SITE_URL') ? rtrim((string)SITE_URL, '/') . '/published/' . ($order['slug'] ?? '') . '/' : '');
    }

    saveOrderRecord($order);

    echo json_encode([
        'success' => true,
        'order_ref' => $orderRef,
        'option' => $choice,
        'custom_domain' => $order['custom_domain'] ?? '',
        'subdomain' => $order['subdomain'] ?? '',
        'live_url' => $order['live_url'] ?? '',
    ]);
}

/**
 * 3. Save Editor Design Snapshot
 */
function handleSaveDesign(array $req) {
    $bizName = htmlspecialchars(trim($req['biz_name'] ?? 'Website'));
    $html    = $req['html'] ?? '';

    $designId = 'DSN-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));

    if (!is_dir(STORAGE_DIR . '/designs')) {
        mkdir(STORAGE_DIR . '/designs', 0755, true);
    }

    file_put_contents(STORAGE_DIR . '/designs/' . $designId . '.html', $html);

    echo json_encode([
        'success'   => true,
        'design_id' => $designId,
        'saved_at'  => date('Y-m-d H:i:s')
    ]);
}

/**
 * 4. Check Order Status
 */
function handleGetStatus(array $req) {
    $orderRef = preg_replace('/[^a-zA-Z0-9_-]/', '', $req['order_ref'] ?? ($_GET['order_ref'] ?? ''));
    $orderFile = STORAGE_DIR . '/orders/' . $orderRef . '.json';

    if (file_exists($orderFile)) {
        $data = json_decode(file_get_contents($orderFile), true);
        echo json_encode(['success' => true, 'order' => $data]);
        return;
    }

    echo json_encode(['success' => false, 'error' => 'Order not found']);
}
