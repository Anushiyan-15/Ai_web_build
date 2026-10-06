<?php
/**
 * test-payment-flow.php  — End-to-End Payment Flow Tester
 * Access: http://localhost/project/webbbuilder/test-payment-flow.php
 * DELETE THIS FILE before going live!
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/PayPalService.php';

$mode = $_GET['mode'] ?? 'info';
$result = null;

// ── Test 1: Check PayPal credentials status
if ($mode === 'check_paypal') {
    $pp = new PayPalService();
    $configured = $pp->isConfigured();
    $token = $configured ? $pp->getAccessToken() : null;
    $result = [
        'configured'   => $configured,
        'mode'         => PAYPAL_MODE,
        'client_id'    => substr(PAYPAL_CLIENT_ID, 0, 20) . '…',
        'token_ok'     => !empty($token),
        'base_url'     => PAYPAL_BASE_URL,
    ];
}

// ── Test 2: Create a test order + checkout URL
if ($mode === 'create_test_order') {
    require_once __DIR__ . '/config.php';

    // Write a minimal test HTML design
    $oid     = 'TEST-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $slug    = 'test-site-' . strtolower(bin2hex(random_bytes(2)));
    $designDir = __DIR__ . '/storage/designs';
    if (!is_dir($designDir)) @mkdir($designDir, 0755, true);
    file_put_contents($designDir . '/' . $oid . '.html', '<html><head><title>Test Site</title></head><body style="font-family:sans-serif;background:#0a0d14;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh"><div style="text-align:center"><h1>🚀 Test Website</h1><p>Payment test successful!</p></div></body></html>');

    $order = [
        'order_id'             => $oid,
        'site_name'            => 'Test Website',
        'slug'                 => $slug,
        'concept_index'        => 0,
        'gen_mode'             => 'static',
        'package'              => 'pro',
        'package_label'        => 'Pro',
        'amount'               => 19.00,
        'currency'             => 'USD',
        'admin_username'       => '',
        'admin_email'          => 'test@example.com',
        'admin_password_hash'  => '',
        'admin_password_plain' => '',
        'admin_files'          => [],
        'admin_sql'            => '',
        'admin_entities'       => [],
        'status'               => 'payment_pending',
        'paypal_order_id'      => null,
        'paypal_capture_id'    => null,
        'live_url'             => null,
        'admin_url'            => null,
        'created_at'           => date('Y-m-d H:i:s'),
        'ip'                   => $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    $ordersDir = __DIR__ . '/storage/orders';
    if (!is_dir($ordersDir)) @mkdir($ordersDir, 0755, true);
    file_put_contents($ordersDir . '/' . $oid . '.json', json_encode($order, JSON_PRETTY_PRINT));

    $pp = new PayPalService();
    $ppOrder = $pp->createOrder(
        $oid,
        19.00,
        'WebCraft AI Test — Pro Plan',
        SITE_URL . '/publish.php?action=paypal_return&order_id=' . urlencode($oid),
        SITE_URL . '/publish.php?action=paypal_cancel&order_id=' . urlencode($oid)
    );

    if ($ppOrder['success']) {
        $order['paypal_order_id'] = $ppOrder['order_id'];
        file_put_contents($ordersDir . '/' . $oid . '.json', json_encode($order, JSON_PRETTY_PRINT));
    }

    $result = [
        'order_id'     => $oid,
        'slug'         => $slug,
        'paypal_result'=> $ppOrder,
        'checkout_url' => $ppOrder['checkout_url'] ?? null,
        'mode'         => $ppOrder['mode'] ?? 'unknown',
        'sim_pay_url'  => SITE_URL . '/publish.php?action=simulate_pay&order_id=' . urlencode($oid),
        'order_file'   => $ordersDir . '/' . $oid . '.json',
    ];
}

// ── Test 3: Direct simulate pay for existing order
if ($mode === 'sim_pay' && isset($_GET['order_id'])) {
    header('Location: ' . SITE_URL . '/publish.php?action=simulate_pay&order_id=' . urlencode($_GET['order_id']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Flow Test — WebCraft AI</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#0a0d14;color:#e2e8f0;min-height:100vh;padding:2rem}
.wrap{max-width:900px;margin:0 auto}
h1{font-size:1.9rem;font-weight:900;color:#fff;margin-bottom:.3rem}
.sub{color:#64748b;font-size:.9rem;margin-bottom:2.5rem}
.card{background:#111622;border:1px solid #1e293b;border-radius:18px;padding:1.75rem;margin-bottom:1.5rem}
.card h2{color:#a5b4fc;font-size:1rem;font-weight:800;margin-bottom:1.25rem;letter-spacing:.03em;text-transform:uppercase;font-size:.78rem}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;border-radius:10px;font-weight:700;font-size:.88rem;cursor:pointer;text-decoration:none;border:none;transition:.2s}
.btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,.4)}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.btn-success:hover{transform:translateY(-2px)}
.btn-warning{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.btn-ghost{background:#1e293b;color:#94a3b8;border:1.5px solid #334155}
.btn-ghost:hover{border-color:#6366f1;color:#fff}
.btns{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem}
pre{background:#0b0f17;border:1px solid #1e293b;border-radius:10px;padding:1.25rem;overflow-x:auto;font-size:.8rem;line-height:1.6;color:#a5b4fc;white-space:pre-wrap;word-break:break-all}
.badge{display:inline-block;padding:.25rem .75rem;border-radius:999px;font-size:.72rem;font-weight:800}
.badge-green{background:#064e3b;color:#34d399}
.badge-red{background:#450a0a;color:#fca5a5}
.badge-yellow{background:#451a03;color:#fcd34d}
.badge-blue{background:#1e1b4b;color:#a5b4fc}
.link-big{display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;background:#0b0f17;border:1.5px solid #6366f1;border-radius:12px;color:#a5b4fc;text-decoration:none;font-weight:700;font-size:.9rem;margin:.5rem 0;transition:.2s}
.link-big:hover{background:#1e1b4b;border-color:#818cf8}
.link-big .arrow{margin-left:auto;color:#6366f1}
.row{display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;border-bottom:1px dashed #1e293b;font-size:.88rem}
.row:last-child{border-bottom:none}
.row .k{color:#64748b}
.row .v{color:#fff;font-weight:700}
.warn{background:#1c1007;border:1px solid #92400e;border-radius:10px;padding:1rem;color:#fcd34d;font-size:.83rem;margin-bottom:1.25rem}
.info-box{background:#0f172a;border:1px solid #1e3a5f;border-radius:10px;padding:1rem;color:#93c5fd;font-size:.83rem;margin-bottom:1.25rem;line-height:1.7}
.step-num{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;background:#6366f1;border-radius:50%;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0}
.step-row{display:flex;align-items:flex-start;gap:.75rem;margin-bottom:.9rem}
.step-row p{color:#cbd5e1;font-size:.85rem;line-height:1.6}
code{background:#0b0f17;padding:.15rem .45rem;border-radius:5px;font-family:'Fira Code',monospace;font-size:.82rem;color:#34d399}
</style>
</head>
<body>
<div class="wrap">
  <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.75rem">
    <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#4f46e5,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.2rem">🧪</div>
    <div>
      <h1>Payment Flow Tester</h1>
      <p class="sub">WebCraft AI — End-to-End Test Suite</p>
    </div>
  </div>

  <div class="warn">⚠️ <strong>Development Only</strong> — Delete <code>test-payment-flow.php</code> before going live!</div>

  <!-- ─── Status Check ─── -->
  <div class="card">
    <h2>📊 System Status</h2>
    <div class="row"><span class="k">SITE_URL</span><span class="v"><?= SITE_URL ?></span></div>
    <div class="row"><span class="k">PayPal Mode</span><span class="v"><span class="badge <?= PAYPAL_MODE === 'live' ? 'badge-red' : 'badge-blue' ?>"><?= strtoupper(PAYPAL_MODE) ?></span></span></div>
    <?php
    $pp = new PayPalService();
    $cred = $pp->isConfigured();
    ?>
    <div class="row"><span class="k">PayPal Credentials</span><span class="v">
      <?php if ($cred): ?>
        <span class="badge badge-green">✓ Configured</span>
      <?php else: ?>
        <span class="badge badge-yellow">⚠ Placeholder (Simulator Active)</span>
      <?php endif; ?>
    </span></div>
    <div class="row"><span class="k">PayPalService.php</span><span class="v"><span class="badge badge-green">✓ Found</span></span></div>
    <div class="row"><span class="k">Storage/orders dir</span><span class="v"><span class="badge <?= is_dir(__DIR__.'/storage/orders') ? 'badge-green' : 'badge-red' ?>"><?= is_dir(__DIR__.'/storage/orders') ? '✓ OK' : '✗ Missing' ?></span></span></div>
    <div class="row"><span class="k">Published dir</span><span class="v"><span class="badge <?= is_dir(__DIR__.'/published') ? 'badge-green' : 'badge-red' ?>"><?= is_dir(__DIR__.'/published') ? '✓ OK' : '✗ Missing' ?></span></span></div>
  </div>

  <!-- ─── Test Flow Buttons ─── -->
  <div class="card">
    <h2>🚀 Test the Full Flow</h2>

    <div class="info-box">
      <div class="step-row"><span class="step-num">1</span><p><strong>Click "Create Test Order"</strong> — Creates a test order in the database and generates a PayPal checkout URL (or simulator URL if no credentials)</p></div>
      <div class="step-row"><span class="step-num">2</span><p><strong>Click "Pay with PayPal"</strong> — Opens PayPal sandbox login. Use your sandbox buyer account. After approval, you'll be redirected back here automatically.</p></div>
      <div class="step-row"><span class="step-num">3</span><p><strong>Website publishes!</strong> — After payment is captured, the site is automatically written to <code>published/</code> and you'll see the success screen.</p></div>
    </div>

    <div class="btns">
      <a href="?mode=create_test_order" class="btn btn-primary">🛒 Create Test Order</a>
      <a href="?mode=check_paypal" class="btn btn-ghost">🔍 Check PayPal Config</a>
      <a href="<?= SITE_URL ?>/platform-admin/plans.php" class="btn btn-ghost" target="_blank" style="border-color:#10b981;color:#34d399">💳 Edit SaaS Plans</a>
      <a href="<?= SITE_URL ?>/builder.php" class="btn btn-ghost" target="_blank">🏗️ Open Builder</a>
      <a href="<?= SITE_URL ?>/publish.php" class="btn btn-ghost" target="_blank">📄 Open Publish Page</a>
    </div>

    <?php if ($result): ?>
    <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>

    <?php if ($mode === 'create_test_order' && isset($result['order_id'])): ?>
    <div style="margin-top:1.25rem">
      <h2 style="color:#10b981;font-size:.85rem;font-weight:800;margin-bottom:.75rem;text-transform:uppercase">✅ Test Order Created: <?= htmlspecialchars($result['order_id']) ?></h2>

      <?php if (!empty($result['checkout_url'])): ?>
      <p style="color:#94a3b8;font-size:.83rem;margin-bottom:.75rem">
        <?php if (($result['mode'] ?? '') === 'simulator'): ?>
          <span class="badge badge-yellow">Simulator Mode</span> — No real PayPal credentials set.
          Click "Simulate Payment" to test the full publish flow instantly.
        <?php else: ?>
          <span class="badge badge-green">Real PayPal</span> — Click "Pay with PayPal" to open sandbox checkout.
        <?php endif; ?>
      </p>

      <?php if (($result['mode'] ?? '') === 'simulator'): ?>
        <a href="?mode=sim_pay&order_id=<?= urlencode($result['order_id']) ?>" class="link-big">
          <span>⚡</span><span>Simulate Payment → Publish Site</span><span class="arrow">→</span>
        </a>
      <?php else: ?>
        <a href="<?= htmlspecialchars($result['checkout_url']) ?>" class="link-big" target="_blank">
          <span>🅿</span><span>Pay with PayPal (Sandbox)</span><span class="arrow">↗</span>
        </a>
        <a href="?mode=sim_pay&order_id=<?= urlencode($result['order_id']) ?>" class="link-big" style="border-color:#f59e0b">
          <span>⚡</span><span>Or: Simulate Payment (Skip PayPal)</span><span class="arrow">→</span>
        </a>
      <?php endif; ?>
      <?php endif; ?>

      <a href="<?= SITE_URL ?>/publish.php?step=done&order_id=<?= urlencode($result['order_id']) ?>" class="link-big" style="border-color:#10b981;margin-top:.5rem">
        <span>🔍</span><span>Preview Done Page (Order ID: <?= htmlspecialchars($result['order_id']) ?>)</span><span class="arrow">→</span>
      </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- ─── How to Add Real PayPal Credentials ─── -->
  <div class="card">
    <h2>🔑 Add Real PayPal Sandbox Credentials</h2>
    <div class="info-box">
      <div class="step-row"><span class="step-num">1</span><p>Go to <strong><a href="https://developer.paypal.com/dashboard/applications/sandbox" target="_blank" style="color:#60a5fa">developer.paypal.com/dashboard/applications/sandbox</a></strong></p></div>
      <div class="step-row"><span class="step-num">2</span><p>Click <strong>"Create App"</strong> → Name: "WebCraft AI" → Type: Merchant → <strong>Create App</strong></p></div>
      <div class="step-row"><span class="step-num">3</span><p>Copy your <strong>Sandbox Client ID</strong> and <strong>Sandbox Secret</strong></p></div>
      <div class="step-row"><span class="step-num">4</span><p>Open <code>config/paypal.php</code> and paste them into:<br>
        <code>PAYPAL_CLIENT_ID</code> and <code>PAYPAL_CLIENT_SECRET</code></p></div>
      <div class="step-row"><span class="step-num">5</span><p>For the <strong>sandbox buyer account</strong>, go to <a href="https://developer.paypal.com/dashboard/accounts" target="_blank" style="color:#60a5fa">developer.paypal.com/dashboard/accounts</a> → click the "Personal" account → View credentials (email + password)</p></div>
      <div class="step-row"><span class="step-num">6</span><p>Run a test: Create order above → click "Pay with PayPal" → log in with sandbox buyer email → Approve → Site publishes! ✅</p></div>
    </div>

    <div class="row"><span class="k">Config file to edit</span><span class="v"><code>config/paypal.php</code></span></div>
    <div class="row"><span class="k">Current Client ID</span><span class="v"><?= defined('PAYPAL_CLIENT_ID') ? htmlspecialchars(substr(PAYPAL_CLIENT_ID, 0, 30)) . (strlen(PAYPAL_CLIENT_ID) > 30 ? '…' : '') : 'Not set' ?></span></div>
    <div class="row"><span class="k">Mode after credentials</span><span class="v">Test with Sandbox → then change PAYPAL_MODE to <code>live</code> for production</span></div>
  </div>

  <!-- ─── Recent Orders ─── -->
  <div class="card">
    <h2>📋 Recent Orders (Last 10)</h2>
    <?php
    $files = glob(__DIR__ . '/storage/orders/*.json');
    if ($files) {
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $files = array_slice($files, 0, 10);
        foreach ($files as $f) {
            $o = json_decode(file_get_contents($f), true);
            if (!$o || !isset($o['order_id'])) continue;
            $statusBadge = match($o['status'] ?? 'unknown') {
                'published'       => '<span class="badge badge-green">✓ Published</span>',
                'paid'            => '<span class="badge badge-blue">💳 Paid</span>',
                'payment_pending' => '<span class="badge badge-yellow">⏳ Pending</span>',
                'cancelled'       => '<span class="badge badge-red">✗ Cancelled</span>',
                default           => '<span class="badge badge-red">' . htmlspecialchars($o['status'] ?? '?') . '</span>',
            };
            echo '<div class="row">';
            echo '<span class="k">' . htmlspecialchars($o['order_id']) . ' &mdash; ' . htmlspecialchars($o['site_name'] ?? '?') . ' &mdash; $' . number_format((float)($o['amount'] ?? 0), 2) . ' </span>';
            echo '<span class="v" style="display:flex;gap:.5rem;align-items:center">' . $statusBadge;
            if ($o['status'] === 'payment_pending') {
                echo ' <a href="?mode=sim_pay&order_id=' . urlencode($o['order_id']) . '" style="font-size:.72rem;background:#1e293b;color:#94a3b8;padding:.2rem .6rem;border-radius:6px;text-decoration:none">⚡ Sim Pay</a>';
            }
            if ($o['status'] === 'paid') {
                echo ' <a href="' . SITE_URL . '/publish.php?action=do_publish&order_id=' . urlencode($o['order_id']) . '" style="font-size:.72rem;background:#064e3b;color:#34d399;padding:.2rem .6rem;border-radius:6px;text-decoration:none">🚀 Publish</a>';
            }
            if ($o['status'] === 'published' && !empty($o['live_url'])) {
                echo ' <a href="' . htmlspecialchars($o['live_url']) . '" target="_blank" style="font-size:.72rem;background:#1e1b4b;color:#a5b4fc;padding:.2rem .6rem;border-radius:6px;text-decoration:none">↗ View</a>';
            }
            echo '</span>';
            echo '</div>';
        }
    } else {
        echo '<p style="color:#64748b;font-size:.85rem">No orders yet.</p>';
    }
    ?>
  </div>

</div>
</body>
</html>
