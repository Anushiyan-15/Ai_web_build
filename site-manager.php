<?php
/**
 * site-manager.php — Customer Website Manager
 *
 * Access: /site-manager.php?order_id=WBL-2026-XXXX
 * Features:
 *   Tab 1: Overview (site status, links, subscription)
 *   Tab 2: AI Feature Adder (add new sections via AI)
 *   Tab 3: Step-by-Step Guide (dynamic per entities)
 *   Tab 4: Notifications (from platform admin)
 */
require_once __DIR__ . '/config.php';

// ─── Customer ownership gate ───────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
// No-cache: logout ku pirahu Back press panna stale manager vara kudathu
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
$smCustomerEmail = strtolower(trim($_SESSION['customer_user']['email'] ?? ''));
function smOwnerEmail(?array $o): string {
    if (!$o) return '';
    return strtolower(trim($o['admin_email'] ?? ($o['client_email'] ?? '')));
}
function smDeny(string $msg): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $msg . ' Please log in with the owner email.']);
    exit;
}

// ─── Helpers ───────────────────────────────────────────────
function smClean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}
function ordersDir(): string {
    $d = defined('STORAGE_DIR') ? STORAGE_DIR . '/orders' : __DIR__ . '/storage/orders';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}
function notificationsDir(): string {
    $d = defined('STORAGE_DIR') ? STORAGE_DIR . '/notifications' : __DIR__ . '/storage/notifications';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}
function publishedDir(): string { return __DIR__ . '/published'; }
function loadOrder(string $id): ?array {
    $f = ordersDir() . '/' . $id . '.json';
    return file_exists($f) ? json_decode(file_get_contents($f), true) : null;
}
function saveOrder(array $o): void {
    file_put_contents(ordersDir() . '/' . $o['order_id'] . '.json', json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ─── AJAX / Action Handlers ────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_order') {
    header('Content-Type: application/json');
    $oid = smClean($_GET['order_id'] ?? '');
    $order = loadOrder($oid);
    if (!$order) { echo json_encode(['success' => false, 'error' => 'Order not found']); exit; }
    $owner = smOwnerEmail($order);
    if ($owner && $owner !== $smCustomerEmail) smDeny('Access denied for this project.');
    echo json_encode([
        'success'          => true,
        'order_id'         => $order['order_id'],
        'site_name'        => $order['site_name'],
        'slug'             => $order['slug'] ?? '',
        'gen_mode'         => $order['gen_mode'] ?? 'static',
        'package'          => $order['package'] ?? 'pro',
        'package_label'    => $order['package_label'] ?? 'Pro',
        'amount'           => $order['amount'] ?? 0,
        'live_url'         => $order['live_url'],
        'admin_url'        => $order['admin_url'],
        'admin_username'   => $order['admin_username'] ?? '',
        'site_active'      => $order['site_active'] ?? true,
        'next_payment_due' => $order['next_payment_due'] ?? null,
        'published_at'     => $order['published_at'] ?? ($order['created_at'] ?? ''),
        'admin_entities'   => $order['admin_entities'] ?? [],
        'biz_type'         => $order['biz_type'] ?? 'business',
    ]);
    exit;
}

if ($action === 'get_notifications') {
    header('Content-Type: application/json');
    $oid = smClean($_GET['order_id'] ?? '');
    $file = notificationsDir() . '/' . $oid . '.json';
    $notifs = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($notifs)) $notifs = [];
    echo json_encode(['success' => true, 'notifications' => array_reverse($notifs)]);
    exit;
}

if ($action === 'add_feature' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $oid  = smClean($data['order_id'] ?? '');
    $req  = strip_tags(trim($data['feature_requirements'] ?? ''));
    $newFiles    = $data['new_files'] ?? [];
    $newEntities = $data['new_entities'] ?? [];

    if (!$oid || empty($newFiles)) {
        echo json_encode(['success' => false, 'error' => 'Missing order_id or files']); exit;
    }

    $order = loadOrder($oid);
    if (!$order) { echo json_encode(['success' => false, 'error' => 'Order not found']); exit; }
    $owner = smOwnerEmail($order);
    if ($owner && $owner !== $smCustomerEmail) smDeny('Access denied for this project.');
    if (!($order['site_active'] ?? true)) {
        echo json_encode(['success' => false, 'error' => 'Site is currently suspended. Please renew your subscription first.']); exit;
    }

    $slug      = $order['slug'] ?? '';
    $adminDir  = publishedDir() . '/' . $slug . '/admin';
    $filesAdded = 0;
    $errors     = [];

    if (!is_dir($adminDir)) {
        echo json_encode(['success' => false, 'error' => 'Admin directory not found for this site.']); exit;
    }

    foreach ($newFiles as $relPath => $content) {
        $relPath = str_replace(['..', '\\'], '', $relPath);
        $relPath = ltrim($relPath, '/');
        if ($relPath === '') continue;
        $fullPath = $adminDir . '/' . $relPath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (file_put_contents($fullPath, $content) !== false) {
            $filesAdded++;
        } else {
            $errors[] = $relPath;
        }
    }

    // Merge into order admin_files
    if (!isset($order['admin_files']) || !is_array($order['admin_files'])) $order['admin_files'] = [];
    foreach ($newFiles as $k => $v) $order['admin_files'][$k] = $v;

    // Merge new entities
    if (!isset($order['admin_entities']) || !is_array($order['admin_entities'])) $order['admin_entities'] = [];
    foreach ($newEntities as $ent) {
        // Only add if not already there
        $exists = false;
        foreach ($order['admin_entities'] as $existing) {
            if (($existing['id'] ?? '') === ($ent['id'] ?? '')) { $exists = true; break; }
        }
        if (!$exists) $order['admin_entities'][] = $ent;
    }

    // Log the feature addition
    if (!isset($order['feature_additions'])) $order['feature_additions'] = [];
    $order['feature_additions'][] = [
        'requirements' => $req,
        'files_added'  => $filesAdded,
        'added_at'     => date('Y-m-d H:i:s'),
    ];

    saveOrder($order);

    echo json_encode([
        'success'     => true,
        'files_added' => $filesAdded,
        'errors'      => $errors,
        'note'        => "✅ $filesAdded new file(s) installed in your admin panel! Go to your admin panel to see the new section.",
        'admin_url'   => $order['admin_url'],
    ]);
    exit;
}

// ─── Main Page ─────────────────────────────────────────────
$orderId = smClean($_GET['order_id'] ?? '');
$order   = $orderId ? loadOrder($orderId) : null;
$notFound = !$order;
$ownerDenied = false;
if ($order) {
    $owner = smOwnerEmail($order);
    if ($owner && $owner !== $smCustomerEmail) { $ownerDenied = true; $order = null; $notFound = true; }
}
if (!$smCustomerEmail && $orderId) { $ownerDenied = true; $order = null; $notFound = true; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Website Manager — <?= defined('SITE_NAME') ? SITE_NAME : 'WebCraft AI' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Fira+Code:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#0a0d14;color:#e2e8f0;min-height:100vh;line-height:1.6}
a{text-decoration:none;color:inherit}

/* Topbar */
.topbar{background:#111622;border-bottom:1px solid #1e293b;padding:0 1.5rem;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.brand{display:flex;align-items:center;gap:.6rem;font-weight:800;font-size:1rem;color:#fff}
.brand .logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1rem}
.topbar-actions{display:flex;align-items:center;gap:.6rem}
.tb-btn{padding:.38rem .85rem;border-radius:8px;border:1.5px solid #283347;background:#0b0f17;color:#cbd5e1;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:.35rem}
.tb-btn:hover{border-color:#6366f1;color:#fff}
.tb-btn.primary{background:linear-gradient(135deg,#4f46e5,#7c3aed);border-color:#818cf8;color:#fff}

/* Layout */
.wrap{max-width:1100px;margin:0 auto;padding:2rem 1.25rem}
.page-header{margin-bottom:2rem}
.page-header h1{font-size:1.6rem;font-weight:900;color:#fff;letter-spacing:-.02em;margin-bottom:.25rem}
.page-header p{color:#94a3b8;font-size:.9rem}

/* Status Badge */
.badge{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .75rem;border-radius:999px;font-size:.72rem;font-weight:800;letter-spacing:.03em}
.badge-green{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3)}
.badge-red{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.badge-yellow{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3)}
.badge-blue{background:rgba(99,102,241,.15);color:#a5b4fc;border:1px solid rgba(99,102,241,.3)}

/* Tabs */
.tabs{display:flex;gap:.3rem;background:#0d121c;border:1px solid #1e293b;border-radius:14px;padding:.35rem;margin-bottom:1.75rem;overflow-x:auto;flex-wrap:nowrap}
.tab-btn{padding:.55rem 1rem;border-radius:10px;border:none;background:transparent;color:#94a3b8;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;transition:.2s;white-space:nowrap;display:inline-flex;align-items:center;gap:.4rem}
.tab-btn:hover{color:#fff;background:#141c30}
.tab-btn.active{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;box-shadow:0 4px 14px rgba(79,70,229,.3)}
.tab-panel{display:none}
.tab-panel.active{display:block}

/* Cards */
.card{background:#111622;border:1px solid #1e293b;border-radius:18px;padding:1.5rem;margin-bottom:1.25rem}
.card-title{font-size:1rem;font-weight:800;color:#fff;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}

/* Info rows */
.info-row{display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px dashed #1e293b;font-size:.88rem;flex-wrap:wrap;gap:.5rem}
.info-row:last-child{border-bottom:none}
.info-row .k{color:#94a3b8;font-weight:600}
.info-row .v{color:#fff;font-weight:700}

/* Link Cards */
.link-card{display:flex;align-items:center;gap:.85rem;background:#0b0f17;border:1.5px solid #283347;border-radius:12px;padding:.9rem 1.1rem;color:#c7d2fe;font-weight:700;font-size:.9rem;transition:.2s;margin-bottom:.75rem;cursor:pointer}
.link-card:hover{border-color:#6366f1;transform:translateX(4px)}
.link-card .ic{font-size:1.5rem;flex-shrink:0}
.link-card .arrow{margin-left:auto;color:#6366f1;font-weight:900}
.link-card.green{border-color:rgba(16,185,129,.4)}
.link-card.green:hover{border-color:#10b981}
.link-card.purple{border-color:rgba(168,85,247,.4)}
.link-card.purple:hover{border-color:#a855f7}

/* AI Feature Adder */
.feature-textarea{width:100%;padding:.9rem 1rem;border:1.5px solid #283347;border-radius:12px;background:#080c14;color:#fff;font-family:inherit;font-size:.9rem;line-height:1.6;resize:vertical;min-height:130px;transition:.2s}
.feature-textarea:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.2)}
.preset-row{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.85rem}
.preset-btn{padding:.38rem .85rem;border-radius:9px;border:1.5px solid #334155;background:#0b0f17;color:#cbd5e1;font-family:inherit;font-size:.75rem;font-weight:700;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:.3rem}
.preset-btn:hover{border-color:#6366f1;color:#fff;background:#1a1f36;transform:translateY(-1px)}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.55rem;padding:.8rem 1.6rem;border-radius:11px;font-family:inherit;font-size:.9rem;font-weight:700;border:none;cursor:pointer;transition:.2s}
.btn-primary{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;box-shadow:0 6px 20px rgba(79,70,229,.35)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(79,70,229,.5)}
.btn-primary:disabled{opacity:.55;cursor:not-allowed;transform:none}
.btn-ghost{background:transparent;border:1.5px solid #334155;color:#cbd5e1}
.btn-ghost:hover{border-color:#6366f1;color:#fff}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff}

/* Progress */
.progress-wrap{display:none;background:#0b0f17;border:1px solid #1e293b;border-radius:12px;padding:1.25rem;margin-top:1rem}
.progress-wrap.active{display:block}
.progress-bar{height:6px;background:#1e293b;border-radius:999px;overflow:hidden;margin-bottom:.75rem}
.progress-fill{height:100%;width:0%;background:linear-gradient(90deg,#6366f1,#a855f7);transition:width .4s ease}
.progress-stage{font-size:.82rem;font-weight:700;color:#fff;margin-bottom:.2rem}
.progress-msg{font-size:.75rem;color:#94a3b8}

/* Success Box */
.success-box{display:none;background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(5,150,105,.06));border:1.5px solid rgba(16,185,129,.4);border-radius:14px;padding:1.25rem;margin-top:1rem}
.success-box.show{display:block}

/* Guide Steps */
.guide-step{background:#0b0f17;border:1px solid #1e293b;border-radius:12px;padding:1rem;margin-bottom:.85rem}
.guide-step-header{display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem}
.guide-step-num{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0}
.guide-step-title{font-weight:800;font-size:.9rem;color:#fff}
.guide-step-body{color:#94a3b8;font-size:.82rem;line-height:1.55;margin-left:2.1rem}
.guide-step-body strong{color:#c7d2fe}
.guide-step-body code{background:#1e293b;padding:.1rem .4rem;border-radius:5px;color:#a5b4fc;font-family:'Fira Code',monospace;font-size:.75rem}

/* Notification items */
.notif-item{background:#0b0f17;border:1px solid #1e293b;border-radius:12px;padding:1rem;margin-bottom:.75rem}
.notif-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;flex-wrap:wrap;gap:.5rem}
.notif-type{font-size:.7rem;font-weight:800;padding:.2rem .6rem;border-radius:999px}
.notif-type.payment_reminder{background:rgba(245,158,11,.15);color:#fbbf24}
.notif-type.warning{background:rgba(239,68,68,.15);color:#f87171}
.notif-type.info{background:rgba(99,102,241,.15);color:#a5b4fc}
.notif-type.activation{background:rgba(16,185,129,.15);color:#34d399}
.notif-type.deactivation{background:rgba(239,68,68,.15);color:#f87171}
.notif-date{font-size:.72rem;color:#64748b;font-weight:600}
.notif-msg{font-size:.85rem;color:#cbd5e1;line-height:1.55}

/* Info box */
.info-box{display:flex;gap:.85rem;background:rgba(99,102,241,.08);border:1.5px solid rgba(99,102,241,.3);border-radius:12px;padding:1rem;font-size:.83rem;color:#c7d2fe;line-height:1.55;margin-bottom:1.25rem}
.info-box .ic{font-size:1.4rem;flex-shrink:0}
.info-box.warning{background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.3);color:#fde68a}
.info-box.danger{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.3);color:#fca5a5}
.info-box.success{background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.3);color:#a7f3d0}

/* Spinner */
@keyframes spin{to{transform:rotate(360deg)}}
.spinner{display:inline-block;width:16px;height:16px;border:2px solid #334155;border-top-color:#818cf8;border-radius:50%;animation:spin .8s linear infinite;vertical-align:middle;margin-right:.4rem}

/* Toast */
.toast{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(120px);background:#111622;border:1.5px solid #10b981;border-radius:12px;padding:.85rem 1.5rem;color:#fff;font-weight:700;font-size:.88rem;box-shadow:0 10px 30px rgba(0,0,0,.6);z-index:9999;transition:.3s;max-width:calc(100vw - 2rem);text-align:center}
.toast.show{transform:translateX(-50%) translateY(0)}
.toast.error{border-color:#ef4444}

/* Suspended overlay */
.suspended-banner{background:linear-gradient(135deg,rgba(239,68,68,.12),rgba(239,68,68,.06));border:2px solid rgba(239,68,68,.4);border-radius:14px;padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;gap:1rem;align-items:flex-start}
.suspended-banner .ic{font-size:2rem;flex-shrink:0}

/* Grid */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:720px){.grid2{grid-template-columns:1fr}}

/* Not found */
.not-found{text-align:center;padding:5rem 2rem}
.not-found .icon{font-size:4rem;margin-bottom:1rem}
.not-found h2{color:#fff;font-size:1.5rem;margin-bottom:.5rem}
.not-found p{color:#94a3b8}

/* Stat cards */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:600px){.stat-row{grid-template-columns:1fr}}
.stat-card{background:#111622;border:1px solid #1e293b;border-radius:14px;padding:1.1rem;text-align:center}
.stat-card .stat-val{font-size:1.6rem;font-weight:900;color:#fff;display:block}
.stat-card .stat-lbl{font-size:.74rem;color:#64748b;font-weight:700;margin-top:.15rem}
</style>
<link rel="stylesheet" href="<?= defined('SITE_URL') ? SITE_URL : '' ?>/assets/css/loader-3d.css">
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="brand"><div class="logo">✦</div><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'WebCraft AI' ?></div>
  <div class="topbar-actions">
    <a href="<?= defined('SITE_URL') ? SITE_URL : '' ?>/builder.php" class="tb-btn">🏠 Back to Builder</a>
  </div>
</div>

<?php if ($notFound): ?>
<!-- Not Found / Access Denied -->
<div class="wrap">
  <div class="not-found">
    <?php if (!empty($ownerDenied)): ?>
      <div class="icon">🔒</div>
      <h2>Login Required</h2>
      <p>This project belongs to another account.<br>Please log in with the owner email to manage it.</p>
      <br><br>
      <a href="<?= defined('SITE_URL') ? SITE_URL : '' ?>/customer-portal.php" class="btn btn-primary">Login to My Projects →</a>
    <?php else: ?>
      <div class="icon">🔍</div>
      <h2>Website Not Found</h2>
      <p>The order ID you provided doesn't match any published website.<br>Check your email for the correct link.</p>
      <br><br>
      <a href="<?= defined('SITE_URL') ? SITE_URL : '' ?>/builder.php" class="btn btn-primary">← Build a New Website</a>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>

<div class="wrap">
  <!-- Page Header (filled by JS) -->
  <div class="page-header">
    <h1>⚙️ <span id="hdr-name">Loading…</span></h1>
    <p>Order: <code style="font-family:'Fira Code',monospace;color:#a5b4fc;font-size:.82rem"><?= htmlspecialchars($orderId) ?></code>
       &nbsp;·&nbsp; Status: <span id="hdr-status">—</span>
       &nbsp;·&nbsp; Next payment: <span id="hdr-due" style="color:#fbbf24">—</span>
    </p>
  </div>

  <!-- Suspended Banner (hidden by default) -->
  <div class="suspended-banner" id="suspended-banner" style="display:none">
    <div class="ic">🚫</div>
    <div>
      <strong style="color:#f87171;font-size:1rem;display:block;margin-bottom:.3rem">Your website is currently suspended</strong>
      <span style="color:#fca5a5;font-size:.85rem">Your site has been temporarily deactivated due to non-payment. Please contact us to renew your subscription and restore your site.</span>
      <br><a href="mailto:<?= defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'info@webbuilder.lk' ?>" style="color:#f87171;font-weight:700;font-size:.85rem;margin-top:.5rem;display:inline-block">📧 Contact Support to Renew →</a>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs" id="tabs">
    <button class="tab-btn active" data-tab="overview">🌐 Overview</button>
    <button class="tab-btn" data-tab="ai-adder" id="tab-ai-adder">🤖 Add Features (AI)</button>
    <button class="tab-btn" data-tab="guide">📋 Step-by-Step Guide</button>
    <button class="tab-btn" data-tab="notifications">🔔 Notifications <span id="notif-badge" style="display:none;background:#ef4444;color:#fff;width:18px;height:18px;border-radius:50%;font-size:.62rem;font-weight:800;align-items:center;justify-content:center">0</span></button>
  </div>

  <!-- ═══ TAB 1: OVERVIEW ═══ -->
  <div class="tab-panel active" id="tab-overview">

    <div class="stat-row" id="stat-row">
      <div class="stat-card">
        <span class="stat-val" id="stat-pkg">Pro</span>
        <div class="stat-lbl">Current Plan</div>
      </div>
      <div class="stat-card">
        <span class="stat-val" id="stat-amount">$19</span>
        <div class="stat-lbl">Monthly Subscription</div>
      </div>
      <div class="stat-card">
        <span class="stat-val" id="stat-due">—</span>
        <div class="stat-lbl">Next Renewal Date</div>
      </div>
    </div>

    <!-- Site Links -->
    <div class="card">
      <div class="card-title">🌐 Your Website</div>
      <a class="link-card green" id="link-live" href="#" target="_blank">
        <span class="ic">🌐</span>
        <div><div style="font-size:.75rem;color:#64748b;font-weight:600;margin-bottom:.1rem">Live Website</div><span id="live-url-text">Loading…</span></div>
        <span class="arrow">↗</span>
      </a>
      <a class="link-card" id="link-admin" href="#" target="_blank" style="display:none">
        <span class="ic">🔐</span>
        <div><div style="font-size:.75rem;color:#64748b;font-weight:600;margin-bottom:.1rem">Admin Panel</div><span id="admin-url-text">Loading…</span></div>
        <span class="arrow">↗</span>
      </a>
      <a class="link-card purple" href="<?= defined('SITE_URL') ? SITE_URL : '' ?>/builder.php" target="_blank">
        <span class="ic">✏️</span>
        <div><div style="font-size:.75rem;color:#64748b;font-weight:600;margin-bottom:.1rem">Edit Design</div>Return to AI Builder</div>
        <span class="arrow">↗</span>
      </a>
    </div>

    <!-- Subscription Info -->
    <div class="card">
      <div class="card-title">💳 Subscription Details</div>
      <div id="sub-details">
        <div class="info-row"><span class="k">Plan</span><span class="v" id="inf-pkg">—</span></div>
        <div class="info-row"><span class="k">Monthly Amount</span><span class="v" id="inf-amount">—</span></div>
        <div class="info-row"><span class="k">Published On</span><span class="v" id="inf-published">—</span></div>
        <div class="info-row"><span class="k">Next Payment Due</span><span class="v" id="inf-due">—</span></div>
        <div class="info-row"><span class="k">Status</span><span class="v" id="inf-status">—</span></div>
      </div>
    </div>

    <!-- Admin Credentials -->
    <div class="card" id="cred-card" style="display:none">
      <div class="card-title">🔑 Your Login Details</div>
      <div class="info-box">
        <div class="ic">💡</div>
        <div><strong>Keep these safe!</strong> Use these to log into your admin panel.</div>
      </div>
      <div class="info-row"><span class="k">Admin Username</span><span class="v" id="inf-username">—</span></div>
      <div class="info-row"><span class="k">Admin Login URL</span>
        <span class="v" style="font-size:.78rem;word-break:break-all" id="inf-admin-url">—</span>
      </div>
      <div style="margin-top:1rem">
        <a id="btn-goto-admin" href="#" target="_blank" class="btn btn-primary" style="font-size:.85rem;padding:.65rem 1.25rem">
          🔐 Open Admin Panel →
        </a>
      </div>
    </div>
  </div>

  <!-- ═══ TAB 2: AI FEATURE ADDER ═══ -->
  <div class="tab-panel" id="tab-ai-adder">
    <div class="info-box">
      <div class="ic">🤖</div>
      <div>
        <strong>AI Feature Adder</strong><br>
        Describe any new section or feature you want — AI will write all the PHP code and install it in your admin panel automatically. No coding needed!
      </div>
    </div>

    <div class="card">
      <div class="card-title">✨ What do you want to add?</div>
      <textarea class="feature-textarea" id="feature-input"
        placeholder="Examples:
• Add an Employees section with Name, Position, Phone, Photo — also show them as a Team section on the homepage
• Add a Products section with Name, Price, Description, Image, Category
• Add a Testimonials section so I can manage customer reviews
• Add an Appointments section with Date, Time, Customer Name, Service Type"></textarea>

      <div class="preset-row">
        <span style="font-size:.75rem;color:#64748b;font-weight:700;align-self:center">Quick Add:</span>
        <button class="preset-btn" onclick="usePreset('Add an Employees section with Name, Position, Department, Phone Number, and Profile Photo. Also show them as a Team section on the live website.')">👥 Employees / Team</button>
        <button class="preset-btn" onclick="usePreset('Add a Products / Services section with Name, Description, Price, Image, and Category. Display them on the live website.')">🛍️ Products / Services</button>
        <button class="preset-btn" onclick="usePreset('Add a Gallery section where I can upload photos with Title and Description. Display them as a photo grid on the website.')">🖼️ Photo Gallery</button>
        <button class="preset-btn" onclick="usePreset('Add a Testimonials section where I can add customer reviews with Name, Position, Review text, and Star Rating (1-5). Show them on the website.')">⭐ Testimonials</button>
        <button class="preset-btn" onclick="usePreset('Add an Appointments / Bookings section with Customer Name, Phone, Email, Service, Preferred Date and Time, and Status (Pending/Confirmed/Cancelled).')">📅 Appointments</button>
        <button class="preset-btn" onclick="usePreset('Add a Blog / News section with Title, Content, Featured Image, Category, and Published Date.')">📰 Blog / News</button>
        <button class="preset-btn" onclick="usePreset('Add an Events section with Event Name, Date, Time, Location, Description, and Max Attendees.')">🗓️ Events</button>
      </div>

      <div style="margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
        <button class="btn btn-primary" id="btn-add-feature" onclick="addFeature()">
          ✨ Generate & Install with AI
        </button>
        <span style="font-size:.78rem;color:#64748b">⏱️ Takes 30–60 seconds</span>
      </div>
    </div>

    <!-- Progress -->
    <div class="progress-wrap" id="feature-progress">
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
        <div class="wcl-mini-house" style="margin:0;"><i class="walls"></i><i class="roof"></i><i class="door"></i></div>
        <div>
          <div class="progress-stage" id="feat-prog-stage">AI is analyzing your request…</div>
          <div class="progress-msg" id="feat-prog-msg">This usually takes 30–60 seconds. Please wait.</div>
        </div>
      </div>
      <div class="progress-bar"><div class="progress-fill" id="feat-prog-bar"></div></div>
    </div>

    <!-- Success -->
    <div class="success-box" id="feature-success">
      <div style="font-size:1.3rem;margin-bottom:.5rem">✅</div>
      <strong style="color:#34d399;display:block;margin-bottom:.35rem">Feature installed successfully!</strong>
      <span id="success-note" style="color:#a7f3d0;font-size:.85rem;display:block;margin-bottom:1rem">—</span>
      <a id="success-admin-link" href="#" target="_blank" class="btn btn-success" style="font-size:.85rem;padding:.6rem 1.25rem">
        🔐 Open Admin Panel to See New Section →
      </a>
    </div>

    <!-- Previously Added Features -->
    <div id="prev-features-wrap" style="display:none">
      <div class="card-title" style="margin-top:1.5rem">📦 Previously Added Features</div>
      <div id="prev-features-list"></div>
    </div>
  </div>

  <!-- ═══ TAB 3: GUIDE ═══ -->
  <div class="tab-panel" id="tab-guide">
    <div id="guide-content">
      <!-- Filled by JS -->
      <div style="text-align:center;padding:3rem;color:#64748b">Loading guide…</div>
    </div>
  </div>

  <!-- ═══ TAB 4: NOTIFICATIONS ═══ -->
  <div class="tab-panel" id="tab-notifications">
    <div id="notif-list">
      <div style="text-align:center;padding:3rem;color:#64748b">
        <div style="font-size:2.5rem;margin-bottom:1rem">🔔</div>
        Loading notifications…
      </div>
    </div>
  </div>

</div><!-- /wrap -->
<?php endif; ?>

<div class="toast" id="toast"></div>

<script src="https://js.puter.com/v2/"></script>
<script src="<?= defined('SITE_URL') ? SITE_URL : '' ?>/assets/js/puter-service.js"></script>
<script src="<?= defined('SITE_URL') ? SITE_URL : '' ?>/assets/js/loader-3d.js"></script>
<script src="<?= defined('SITE_URL') ? SITE_URL : '' ?>/assets/js/ai-admin-generator.js"></script>
<script>
/* ════ CONFIG ════ */
const SITE_URL   = <?= json_encode(defined('SITE_URL') ? SITE_URL : '') ?>;
const ORDER_ID   = <?= json_encode($orderId) ?>;

/* ════ STATE ════ */
let orderData = null;

/* ════ INIT ════ */
document.addEventListener('DOMContentLoaded', async () => {
  if (!ORDER_ID) return;
  if (window.Loader3D) Loader3D.show('Loading website…', 'Fetching project data', 'radar');
  initTabs();
  try { await loadOrder(); } finally { if (window.Loader3D) Loader3D.hide(); }
  loadNotifications();
});

/* ════ TABS ════ */
function switchTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  const btn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
  const panel = document.getElementById('tab-' + tabId);
  if (btn && !btn.disabled) { btn.classList.add('active'); }
  if (panel) panel.classList.add('active');
}

function initTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      switchTab(btn.dataset.tab);
      history.replaceState(null, '', '#' + btn.dataset.tab);
    });
  });

  // Activate tab from URL hash if present
  const hash = window.location.hash.replace('#', '');
  if (hash) { setTimeout(() => switchTab(hash), 100); }

  // Support hashchange (e.g. browser back/forward)
  window.addEventListener('hashchange', () => {
    const h = window.location.hash.replace('#', '');
    if (h) switchTab(h);
  });
}

/* ════ LOAD ORDER ════ */
async function loadOrder() {
  try {
    const r = await fetch(`${SITE_URL}/site-manager.php?action=get_order&order_id=${encodeURIComponent(ORDER_ID)}`);
    const j = await r.json();
    if (!j.success) { showToast('❌ Could not load order data', true); return; }
    orderData = j;
    populateUI(j);
    buildGuide(j);
    buildPrevFeatures(j);
  } catch (e) {
    console.error(e);
    showToast('❌ Network error loading order', true);
  }
}

/* ════ POPULATE UI ════ */
function populateUI(o) {
  // Header
  document.getElementById('hdr-name').textContent = o.site_name;
  const active = o.site_active !== false;
  document.getElementById('hdr-status').innerHTML = active
    ? '<span class="badge badge-green">● Active</span>'
    : '<span class="badge badge-red">● Suspended</span>';

  const due = o.next_payment_due;
  if (due) {
    const dueDate = new Date(due);
    const today   = new Date();
    const diffDays = Math.ceil((dueDate - today) / 86400000);
    const dueStr  = dueDate.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
    const color   = diffDays <= 5 ? '#f87171' : diffDays <= 10 ? '#fbbf24' : '#34d399';
    document.getElementById('hdr-due').innerHTML = `<span style="color:${color}">${dueStr} (${diffDays > 0 ? diffDays + ' days' : 'Overdue'})</span>`;
    document.getElementById('stat-due').textContent = dueStr;
    document.getElementById('inf-due').textContent  = dueStr;
  }

  // Stats
  document.getElementById('stat-pkg').textContent    = o.package_label || o.package || '—';
  document.getElementById('stat-amount').textContent = o.amount ? `$${parseFloat(o.amount).toFixed(0)}` : '—';

  // Subscription card
  document.getElementById('inf-pkg').textContent       = o.package_label || o.package || '—';
  document.getElementById('inf-amount').textContent    = o.amount ? `$${parseFloat(o.amount).toFixed(2)} / month` : '—';
  document.getElementById('inf-published').textContent = o.published_at ? new Date(o.published_at).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'}) : '—';
  document.getElementById('inf-status').innerHTML      = active
    ? '<span class="badge badge-green">Active</span>'
    : '<span class="badge badge-red">Suspended</span>';

  // Links
  if (o.live_url) {
    const liveLink = document.getElementById('link-live');
    liveLink.href = o.live_url;
    document.getElementById('live-url-text').textContent = o.live_url;
  }
  if (o.admin_url) {
    const adminLink = document.getElementById('link-admin');
    adminLink.style.display = 'flex';
    adminLink.href = o.admin_url;
    document.getElementById('admin-url-text').textContent = o.admin_url;

    // Credentials card
    document.getElementById('cred-card').style.display = 'block';
    document.getElementById('inf-username').textContent = o.admin_username || '—';
    document.getElementById('inf-admin-url').textContent = o.admin_url;
    document.getElementById('btn-goto-admin').href = o.admin_url;
  }

  // Suspended
  if (!active) {
    document.getElementById('suspended-banner').style.display = 'flex';
    document.getElementById('tab-ai-adder').disabled = true;
    document.getElementById('tab-ai-adder').title = 'Reactivate your site to use this feature';
  }
}

/* ════ BUILD GUIDE ════ */
function buildGuide(o) {
  const entities = o.admin_entities || [];
  const genMode  = o.gen_mode || 'static';
  const colors   = ['#6366f1','#10b981','#f59e0b','#38bdf8','#a855f7','#fb7185','#a3e635'];
  let html = '';

  // Step 1: Always — Visit live site
  html += guideStep(1, colors[0], '🌐', 'Visit Your Live Website',
    `Your website is live at <a href="${esc(o.live_url)}" target="_blank" style="color:#a5b4fc;font-weight:700">${esc(o.live_url)}</a>. Click the link to see it live right now!`);

  if (genMode !== 'static') {
    // Step 2: Login to admin
    html += guideStep(2, colors[1], '🔐', 'Log In to Your Admin Panel',
      `Go to <a href="${esc(o.admin_url)}" target="_blank" style="color:#a5b4fc;font-weight:700">${esc(o.admin_url)}</a> and log in with your username: <code>${esc(o.admin_username)}</code>`);

    // Steps for each entity
    entities.forEach((ent, i) => {
      const color  = colors[(i + 2) % colors.length];
      const icon   = ent.icon || '📋';
      const name   = ent.name || ent.id || 'Section';
      const sing   = ent.singular || name;
      html += guideStep(i + 3, color, icon, `Manage Your ${name}`,
        `In the admin panel sidebar, click <strong>${name}</strong>. To add a new ${sing}, click the <strong style="color:#34d399">+ Add ${sing}</strong> button, fill in the form, and click Save. Changes appear on your live site immediately.`);
    });

    // Step: Add features
    html += guideStep(entities.length + 3, '#a855f7', '✨', 'Add New Features with AI',
      `Go to the <strong>🤖 Add Features (AI)</strong> tab above. Type what you want (e.g., "Add an Employees section with photo and position") and AI will build and install it automatically — no coding needed!`);

    // Step: Manage subscription
    html += guideStep(entities.length + 4, '#f59e0b', '💳', 'Renew Your Subscription',
      `Your subscription renews monthly. If payment is overdue, your site will be temporarily suspended. Contact us at <a href="mailto:${esc(defined('CONTACT_EMAIL') ? '<?= defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'info@webbuilder.lk' ?>' : 'info@webbuilder.lk')}" style="color:#a5b4fc;font-weight:700">📧 email us</a> to renew and restore access.`);
  } else {
    // Static site guide
    html += guideStep(2, '#a855f7', '✏️', 'Edit Your Website Design',
      `Click <strong>✏️ Edit Design</strong> in the Overview tab to return to the AI Builder and modify your website design. You can change text, colors, and layout.`);
    html += guideStep(3, '#f59e0b', '📞', 'Need More Features?',
      `Want to add an admin panel, contact form, or dynamic sections? Go back to the builder and select <strong>Admin Panel</strong> or <strong>Full Database</strong> mode to regenerate with those features.`);
  }

  document.getElementById('guide-content').innerHTML = html;
}

function guideStep(num, color, icon, title, body) {
  return `<div class="guide-step" style="border-left:4px solid ${color}">
    <div class="guide-step-header">
      <div class="guide-step-num" style="background:${color}">${num}</div>
      <div class="guide-step-title">${icon} ${title}</div>
    </div>
    <div class="guide-step-body">${body}</div>
  </div>`;
}

/* ════ BUILD PREVIOUS FEATURES ════ */
function buildPrevFeatures(o) {
  const additions = o.feature_additions || [];
  if (!additions.length) return;
  const wrap = document.getElementById('prev-features-wrap');
  const list = document.getElementById('prev-features-list');
  wrap.style.display = 'block';
  list.innerHTML = additions.map((a, i) => `
    <div style="background:#0b0f17;border:1px solid #1e293b;border-radius:11px;padding:.85rem;margin-bottom:.6rem">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.4rem;margin-bottom:.35rem">
        <strong style="color:#fff;font-size:.85rem">#${i+1} Feature Added</strong>
        <span style="font-size:.72rem;color:#64748b;font-weight:700">${a.added_at || ''}</span>
      </div>
      <div style="font-size:.8rem;color:#94a3b8;line-height:1.5">${esc(a.requirements)}</div>
      <div style="font-size:.72rem;color:#34d399;font-weight:700;margin-top:.4rem">✅ ${a.files_added} file(s) installed</div>
    </div>`).join('');
}

/* ════ LOAD NOTIFICATIONS ════ */
async function loadNotifications() {
  try {
    const r = await fetch(`${SITE_URL}/site-manager.php?action=get_notifications&order_id=${encodeURIComponent(ORDER_ID)}`);
    const j = await r.json();
    const notifs = j.notifications || [];
    renderNotifications(notifs);
  } catch (e) {}
}

function renderNotifications(notifs) {
  const list = document.getElementById('notif-list');
  const badge = document.getElementById('notif-badge');

  if (!notifs.length) {
    list.innerHTML = `<div style="text-align:center;padding:3rem;color:#64748b">
      <div style="font-size:2.5rem;margin-bottom:1rem">🔔</div>
      <div>No notifications yet</div>
    </div>`;
    return;
  }

  badge.style.display = 'inline-flex';
  badge.textContent = notifs.length;

  const typeLabels = {
    payment_reminder: '💳 Payment Reminder',
    warning: '⚠️ Warning',
    info: 'ℹ️ Info',
    activation: '✅ Site Activated',
    deactivation: '🚫 Site Suspended',
  };

  list.innerHTML = notifs.map(n => `
    <div class="notif-item">
      <div class="notif-header">
        <span class="notif-type ${n.type || 'info'}">${typeLabels[n.type] || n.type}</span>
        <span class="notif-date">${n.created_at || ''}</span>
      </div>
      ${n.subject ? `<div style="font-weight:700;color:#fff;font-size:.88rem;margin-bottom:.4rem">${esc(n.subject)}</div>` : ''}
      <div class="notif-msg">${esc(n.message).replace(/\n/g,'<br>')}</div>
    </div>`).join('');
}

/* ════ AI FEATURE ADDER ════ */
function usePreset(text) {
  document.getElementById('feature-input').value = text;
}

async function addFeature() {
  if (!orderData) { showToast('❌ Order data not loaded'); return; }
  if (orderData.site_active === false) {
    showToast('❌ Site is suspended. Please renew to add features.', true); return;
  }

  const requirements = document.getElementById('feature-input').value.trim();
  if (!requirements || requirements.length < 10) {
    showToast('⚠️ Please describe the feature you want to add', true); return;
  }

  const btn = document.getElementById('btn-add-feature');
  btn.disabled = true;
  btn.innerHTML = '<span class="wcl-bblocks"><i></i><i></i><i></i></span>Generating…';
  document.getElementById('feature-progress').classList.add('active');
  document.getElementById('feature-success').classList.remove('show');

  const stages = [
    { stage:'Analyzing your request…', msg:'Understanding what you want to build', pct:10 },
    { stage:'Designing data structure…', msg:'Planning tables and fields', pct:25 },
    { stage:'Writing PHP files…', msg:'Building list, add, edit, delete pages', pct:60 },
    { stage:'Adding styling & validation…', msg:'Making it look great and work correctly', pct:85 },
    { stage:'Finalizing…', msg:'Almost done!', pct:95 },
  ];
  let si = 0;
  const stageInt = setInterval(() => {
    if (si < stages.length) {
      document.getElementById('feat-prog-stage').textContent = stages[si].stage;
      document.getElementById('feat-prog-msg').textContent   = stages[si].msg;
      document.getElementById('feat-prog-bar').style.width   = stages[si].pct + '%';
      si++;
    }
  }, 4000);

  try {
    const result = await window.AIAdminGenerator.generate({
      bizName:          orderData.site_name,
      bizType:          orderData.biz_type || 'business',
      userRequirements: requirements,
      genMode:          'admin',
    }, (p) => {
      if (p.message) document.getElementById('feat-prog-msg').textContent = p.message;
    });

    clearInterval(stageInt);
    document.getElementById('feat-prog-bar').style.width = '100%';

    if (!result || !result.files) throw new Error('AI did not return valid file structure');

    // POST to server to install files
    const res = await fetch(`${SITE_URL}/site-manager.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'add_feature',
        order_id: ORDER_ID,
        feature_requirements: requirements,
        new_files: result.files,
        new_entities: result.entities || [],
      })
    });
    const j = await res.json();

    document.getElementById('feature-progress').classList.remove('active');

    if (!j.success) throw new Error(j.error || 'Installation failed');

    // Show success
    const successBox = document.getElementById('feature-success');
    document.getElementById('success-note').textContent = j.note;
    document.getElementById('success-admin-link').href = orderData.admin_url || j.admin_url || '#';
    successBox.classList.add('show');
    showToast(`✅ ${j.files_added} files installed!`);

    // Refresh order data (entities updated)
    await loadOrder();
    document.getElementById('feature-input').value = '';

  } catch (err) {
    clearInterval(stageInt);
    document.getElementById('feature-progress').classList.remove('active');
    showToast('❌ ' + err.message, true);
    console.error(err);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '✨ Generate & Install with AI';
  }
}

/* ════ HELPERS ════ */
function esc(s) {
  return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

let toastTimer;
function showToast(msg, isError = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast' + (isError ? ' error' : '');
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}
</script>
</body>
</html>
