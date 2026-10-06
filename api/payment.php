<?php
// ═══════════════════════════════════════════════════════════════
//  api/payment.php — PayPal Payment & Instant Publishing Engine
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
require_once dirname(__DIR__) . '/config/paypal.php';
require_once dirname(__DIR__) . '/includes/PayPalService.php';
require_once dirname(__DIR__) . '/includes/db.php';

$raw  = file_get_contents('php://input');
$req  = json_decode($raw, true) ?: [];
$action = $req['action'] ?? ($_GET['action'] ?? '');

$payPal = new PayPalService();

switch ($action) {
    case 'create_order':
        handleCreateOrder($req, $payPal);
        break;

    case 'capture_order':
        handleCaptureOrder($req, $payPal);
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

// ═══════════════════════════════════════════════════════════════
// ACTION HANDLERS
// ═══════════════════════════════════════════════════════════════

/**
 * 1. Create PayPal Order
 */
function handleCreateOrder(array $req, PayPalService $payPal) {
    $planId    = preg_replace('/[^a-z0-9_-]/i', '', $req['plan'] ?? 'pro');
    $amount    = floatval($req['amount'] ?? 19.00);
    $payMethod = htmlspecialchars(trim($req['method'] ?? 'paypal'));
    $bizName   = htmlspecialchars(trim($req['biz_name'] ?? 'Website'));
    $html      = $req['html'] ?? '';

    if ($amount <= 0) {
        $amount = 19.00;
    }

    $orderRef = 'WBL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    // Return & cancel URLs for PayPal checkout
    $returnUrl = SITE_URL . '/builder.php?payment=success';
    $cancelUrl = SITE_URL . '/builder.php?payment=cancel';

    $desc = "WebCraft AI - " . ucfirst($planId) . " Plan for " . $bizName;
    $res = $payPal->createOrder($orderRef, $amount, $desc, $returnUrl, $cancelUrl);

    if (!$res['success']) {
        echo json_encode($res);
        return;
    }

    $paypalOrderId = $res['order_id'] ?? '';

    // Save snapshot of order to storage/orders/
    $orderData = [
        'order_ref'       => $orderRef,
        'paypal_order_id' => $paypalOrderId,
        'plan'            => $planId,
        'amount'          => $amount,
        'currency'        => defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : 'USD',
        'pay_method'      => $payMethod,
        'biz_name'        => $bizName,
        'status'          => 'pending',
        'created_at'      => date('Y-m-d H:i:s'),
    ];

    if (!is_dir(STORAGE_DIR . '/orders')) {
        mkdir(STORAGE_DIR . '/orders', 0755, true);
    }
    file_put_contents(STORAGE_DIR . '/orders/' . $orderRef . '.json', json_encode($orderData, JSON_PRETTY_PRINT));

    // Save HTML snapshot
    if (!is_dir(STORAGE_DIR . '/designs')) {
        mkdir(STORAGE_DIR . '/designs', 0755, true);
    }
    if (!empty($html)) {
        file_put_contents(STORAGE_DIR . '/designs/' . $orderRef . '.html', $html);
    }

    // Attempt DB record
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
                ':amount'         => $amount
            ]);
        } catch (Exception $e) {
            error_log("DB insert failed: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success'         => true,
        'order_ref'       => $orderRef,
        'paypal_order_id' => $paypalOrderId,
        'checkout_url'    => $res['checkout_url'],
        'mode'            => $res['mode'] ?? 'paypal'
    ]);
}

/**
 * 2. Capture PayPal Order & Auto-Publish Website
 */
function handleCaptureOrder(array $req, PayPalService $payPal) {
    $paypalOrderId = trim($req['paypal_order_id'] ?? '');

    if (empty($paypalOrderId)) {
        echo json_encode(['success' => false, 'error' => 'Missing PayPal Order Token']);
        return;
    }

    $captureRes = $payPal->captureOrder($paypalOrderId);

    if (!$captureRes['success'] || ($captureRes['status'] ?? '') !== 'COMPLETED') {
        echo json_encode([
            'success' => false,
            'error'   => $captureRes['error'] ?? 'PayPal payment could not be captured'
        ]);
        return;
    }

    // Locate the matching order record in storage/orders/
    $matchedOrderRef = null;
    $orderData = null;

    if (is_dir(STORAGE_DIR . '/orders')) {
        $files = scandir(STORAGE_DIR . '/orders');
        // Search newest first
        rsort($files);
        foreach ($files as $file) {
            if (str_ends_with($file, '.json')) {
                $content = json_decode(file_get_contents(STORAGE_DIR . '/orders/' . $file), true);
                if (($content['paypal_order_id'] ?? '') === $paypalOrderId) {
                    $matchedOrderRef = $content['order_ref'];
                    $orderData = $content;
                    break;
                }
            }
        }

        // If not matched by ID (e.g. simulation or direct callback), pick latest pending order
        if (!$orderData) {
            foreach ($files as $file) {
                if (str_ends_with($file, '.json')) {
                    $content = json_decode(file_get_contents(STORAGE_DIR . '/orders/' . $file), true);
                    if (($content['status'] ?? '') === 'pending') {
                        $matchedOrderRef = $content['order_ref'];
                        $orderData = $content;
                        break;
                    }
                }
            }
        }
    }

    $bizName = $orderData['biz_name'] ?? 'Website';
    $orderRef = $matchedOrderRef ?? ('WBL-' . date('Ymd-His'));

    // Retrieve the saved HTML snapshot
    $html = '';
    $designFile = STORAGE_DIR . '/designs/' . $orderRef . '.html';
    if (file_exists($designFile)) {
        $html = file_get_contents($designFile);
    }
    if (empty($html) && !empty($req['html'])) {
        $html = $req['html'];
    }

    // Generate safe directory slug for published site
    $rawSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $bizName), '-'));
    if (empty($rawSlug)) {
        $rawSlug = 'site';
    }
    $slug = $rawSlug . '-' . substr(md5($orderRef), 0, 6);

    $siteDir = PUBLISHED_DIR . '/' . $slug;
    if (!is_dir($siteDir)) {
        mkdir($siteDir, 0755, true);
    }

    // Write index.html to published site directory
    file_put_contents($siteDir . '/index.html', $html);

    // Save metadata
    file_put_contents($siteDir . '/meta.json', json_encode([
        'biz_name'        => $bizName,
        'order_ref'       => $orderRef,
        'paypal_order_id' => $paypalOrderId,
        'published_at'    => date('Y-m-d H:i:s'),
        'plan'            => $orderData['plan'] ?? 'pro',
    ], JSON_PRETTY_PRINT));

    $liveUrl = SITE_URL . '/published/' . $slug . '/';

    // Update order status
    if ($orderData) {
        $orderData['status']          = 'published';
        $orderData['live_url']        = $liveUrl;
        $orderData['published_slug']  = $slug;
        $orderData['captured_at']     = date('Y-m-d H:i:s');
        $orderData['capture_id']      = $captureRes['capture_id'] ?? '';
        file_put_contents(STORAGE_DIR . '/orders/' . $orderRef . '.json', json_encode($orderData, JSON_PRETTY_PRINT));
    }

    // Update MySQL DB if online
    $pdo = getDb();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'published', updated_at = NOW() 
                WHERE order_id = :order_id
            ");
            $stmt->execute([':order_id' => $orderRef]);
        } catch (Exception $e) {
            error_log("DB update failed: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success'   => true,
        'live_url'  => $liveUrl,
        'order_ref' => $orderRef,
        'slug'      => $slug,
        'message'   => 'Payment captured and website published live!'
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
