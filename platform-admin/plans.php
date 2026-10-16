<?php
/**
 * platform-admin/plans.php — SaaS Subscription Plans & Pricing Manager
 * 
 * Allows the website owner (platform admin) to:
 * - Edit plan names, prices, billing periods
 * - Add, edit, remove, and toggle checklist features
 * - Mark a plan as "Popular" (with glowing badge)
 * - Save directly to storage/plans.json
 * - Changes are reflected dynamically on the publish.php payment screen!
 */

require_once '../config.php';
require_once '../config/storage.php';
require_once '../includes/StorageService.php';
require_once '../includes/Csrf.php';
require_once 'auth.php';
require_once 'helpers.php';

$plansFile = dirname(__DIR__) . '/storage/plans.json';

/** Keep storage/plans.json quota fields in sync with the DB source of truth. */
function sync_plan_quotas_to_json(array $dbPlans, string $plansFile): void {
    if (!file_exists($plansFile)) return;
    $j = json_decode(@file_get_contents($plansFile), true);
    if (!is_array($j)) return;
    $bySlug = [];
    foreach ($dbPlans as $p) $bySlug[strtolower($p['slug'])] = $p;
    $changed = false;
    foreach ($j as $slug => $entry) {
        $k = strtolower((string)$slug);
        if (!isset($bySlug[$k]) || !is_array($entry)) continue;
        $p = $bySlug[$k];
        foreach (['storage_limit_mb', 'bandwidth_limit_mb', 'max_sites', 'price_per_extra_gb', 'currency', 'is_active'] as $f) {
            $nv = $f === 'is_active' ? (bool)$p[$f] : $p[$f];
            if (($entry[$f] ?? null) !== $nv) { $j[$slug][$f] = $nv; $changed = true; }
        }
    }
    if ($changed) @file_put_contents($plansFile, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/** Ensure every JSON plan slug has a DB row (name sync only; quotas untouched). */
function ensure_db_plans_for_json(array $jsonPlans): void {
    $db = StorageService::db();
    if (!$db) return;
    $driver = StorageService::driver($db);
    foreach ($jsonPlans as $slug => $p) {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string)$slug));
        if ($slug === '' || !is_array($p)) continue;
        try {
            if ($driver === 'pgsql') {
                $db->prepare("INSERT INTO plans (slug, name) VALUES (:s, :n) ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name")
                   ->execute([':s' => $slug, ':n' => substr(trim(strip_tags($p['label'] ?? $slug)), 0, 128) ?: $slug]);
            } else {
                $db->prepare("INSERT IGNORE INTO plans (slug, name) VALUES (:s, :n)")
                   ->execute([':s' => $slug, ':n' => substr(trim(strip_tags($p['label'] ?? $slug)), 0, 128) ?: $slug]);
                $db->prepare("UPDATE plans SET name = :n WHERE slug = :s")
                   ->execute([':n' => substr(trim(strip_tags($p['label'] ?? $slug)), 0, 128) ?: $slug, ':s' => $slug]);
            }
        } catch (Throwable $e) {}
    }
}

$quotaMsg = '';
$quotaErr = '';

// Handle POST: Save storage quotas (DB source of truth + JSON sync)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_storage_quotas') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $quotaErr = 'Invalid CSRF token. Please reload and try again.';
    } elseif (!has_permission('plans_edit') && !has_permission('*')) {
        $quotaErr = 'Unauthorized: plans_edit permission required.';
    } else {
        $db = StorageService::db();
        if (!$db) { $quotaErr = 'Database unavailable.'; }
        else {
            $okCount = 0;
            foreach (StorageService::getAllPlans(false) as $p) {
                $id = (int)$p['id'];
                $sMb = max(0, (int)($_POST['q_storage_' . $id] ?? $p['storage_limit_mb']));
                $bMb = max(0, (int)($_POST['q_bandwidth_' . $id] ?? $p['bandwidth_limit_mb']));
                $sites = max(0, (int)($_POST['q_sites_' . $id] ?? $p['max_sites']));
                $rate = max(0, round((float)($_POST['q_rate_' . $id] ?? $p['price_per_extra_gb']), 2));
                $cur = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['q_currency_' . $id] ?? $p['currency']))) ?: 'USD';
                $active = !empty($_POST['q_active_' . $id]) ? 1 : 0;
                try {
                    $db->prepare("UPDATE plans SET storage_limit_mb=:s, bandwidth_limit_mb=:b, max_sites=:m, price_per_extra_gb=:r, currency=:c, is_active=:a, updated_at=NOW() WHERE id=:id")
                       ->execute([':s' => $sMb, ':b' => $bMb, ':m' => $sites, ':r' => $rate, ':c' => $cur, ':a' => $active, ':id' => $id]);
                    $okCount++;
                } catch (Throwable $e) { $quotaErr = 'Some rows failed to save.'; }
            }
            // Push quotas into plans.json so checkout/portal readers never
            // silently disagree with the database.
            sync_plan_quotas_to_json(StorageService::getAllPlans(false), $plansFile);
            log_admin_action('update_storage_quotas', 'pricing', "Updated storage quotas for $okCount plans");
            if ($quotaErr === '') $quotaMsg = "Storage quotas saved ($okCount plans) and synced to plans.json.";
        }
    }
}

// Handle POST: create / disable-toggle / safe-delete a plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['storage_plan_create', 'storage_plan_toggle', 'storage_plan_delete'], true)) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $quotaErr = 'Invalid CSRF token.';
    } elseif (!has_permission('plans_edit') && !has_permission('*')) {
        $quotaErr = 'Unauthorized: plans_edit permission required.';
    } else {
        $db = StorageService::db();
        $a = $_POST['action'];
        try {
            if ($a === 'storage_plan_create') {
                $slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string)($_POST['np_slug'] ?? '')));
                $name = substr(trim(strip_tags((string)($_POST['np_name'] ?? ''))), 0, 128);
                if ($slug === '' || $name === '') { $quotaErr = 'Slug and name are required.'; }
                else {
                    $db->prepare("INSERT INTO plans (slug, name, storage_limit_mb, bandwidth_limit_mb, max_sites, price_per_extra_gb, currency, is_active) VALUES (:s,:n,:st,:b,:m,:r,:c,1)")
                       ->execute([':s' => $slug, ':n' => $name, ':st' => max(0, (int)($_POST['np_storage'] ?? 250)), ':b' => max(0, (int)($_POST['np_bandwidth'] ?? 5120)), ':m' => max(0, (int)($_POST['np_sites'] ?? 1)), ':r' => max(0, round((float)($_POST['np_rate'] ?? 2), 2)), ':c' => 'USD']);
                    log_admin_action('plan_created', 'pricing', "Created storage plan $slug");
                    $quotaMsg = "Plan '$slug' created.";
                }
            } elseif ($a === 'storage_plan_toggle') {
                $id = (int)($_POST['plan_id'] ?? 0);
                $db->prepare("UPDATE plans SET is_active = 1 - is_active, updated_at = NOW() WHERE id = :id")->execute([':id' => $id]);
                sync_plan_quotas_to_json(StorageService::getAllPlans(false), $plansFile);
                log_admin_action('plan_toggled', 'pricing', "Toggled plan id $id active flag");
                $quotaMsg = 'Plan status updated.';
            } else {
                // Safe delete: refuse when referenced by customers or legacy orders.
                $id = (int)($_POST['plan_id'] ?? 0);
                $p = StorageService::getPlan($id);
                if (!$p) { $quotaErr = 'Plan not found.'; }
                else {
                    $refs = 0;
                    try {
                        $refs += (int)$db->query("SELECT COUNT(*) FROM customers WHERE plan_id = " . (int)$id)->fetchColumn();
                        $st = $db->prepare("SELECT COUNT(*) FROM orders WHERE LOWER(package) = :s");
                        $st->execute([':s' => strtolower($p['slug'])]);
                        $refs += (int)$st->fetchColumn();
                        $st = $db->prepare("SELECT COUNT(*) FROM customer_usage cu JOIN customers c ON c.email = cu.email WHERE c.plan_id = :id");
                        $st->execute([':id' => $id]);
                    } catch (Throwable $e) {}
                    if ($refs > 0) { $quotaErr = "Cannot delete '{$p['slug']}': referenced $refs time(s). Disable it instead."; }
                    else {
                        $db->prepare("DELETE FROM plans WHERE id = :id")->execute([':id' => $id]);
                        log_admin_action('plan_deleted', 'pricing', "Deleted unreferenced plan {$p['slug']}");
                        $quotaMsg = "Plan '{$p['slug']}' deleted.";
                    }
                }
            }
        } catch (Throwable $e) { $quotaErr = 'Operation failed (slug may already exist).'; }
    }
}

// Handle POST: Save Plans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_plans') {
    header('Content-Type: application/json');
    $raw = $_POST['plans_json'] ?? '';
    $data = json_decode($raw, true);

    if (!is_array($data) || empty($data)) {
        echo json_encode(['success' => false, 'error' => 'Invalid plans data.']);
        exit;
    }

    // Sanitize and validate
    $clean = [];
    foreach ($data as $key => $p) {
        $id = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $key));
        if (!$id) continue;
        $clean[$id] = [
            'id'       => $id,
            'label'    => htmlspecialchars(strip_tags(trim($p['label'] ?? ucfirst($id)))),
            'price'    => max(0, round((float)($p['price'] ?? 0), 2)),
            'period'   => in_array($p['period'] ?? '', ['month', 'year', 'lifetime', 'week']) ? $p['period'] : 'month',
            'popular'  => !empty($p['popular']),
            'features' => []
        ];
        if (!empty($p['features']) && is_array($p['features'])) {
            foreach ($p['features'] as $f) {
                $text = htmlspecialchars(strip_tags(trim($f['text'] ?? '')));
                if ($text !== '') {
                    $clean[$id]['features'][] = [
                        'text'     => $text,
                        'included' => !empty($f['included'])
                    ];
                }
            }
        }
    }

    if (!is_dir(dirname($plansFile))) @mkdir(dirname($plansFile), 0755, true);
    $ok = file_put_contents($plansFile, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($ok) {
        log_admin_action('update_plans', 'pricing', 'Updated subscription pricing plans');
        // Keep DB plan rows in step with JSON slugs (name sync only —
        // quotas and per-GB rates stay authoritative in the database).
        try { ensure_db_plans_for_json($clean); } catch (Throwable $e) {}
        echo json_encode(['success' => true, 'saved_at' => date('Y-m-d H:i:s')]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to write plans.json']);
    }
    exit;
}

// Load current plans
$defaultPlans = [
    'starter' => [
        'id' => 'starter', 'label' => 'Starter', 'price' => 9.00,
        'period' => 'month', 'popular' => false,
        'features' => [
            ['text' => '1 Website', 'included' => true],
            ['text' => '5 GB Bandwidth', 'included' => true],
            ['text' => 'Basic Support', 'included' => true],
            ['text' => 'SSL Included', 'included' => true],
            ['text' => 'Admin Panel', 'included' => false],
            ['text' => 'Priority Support', 'included' => false],
        ]
    ],
    'pro' => [
        'id' => 'pro', 'label' => 'Pro', 'price' => 19.00,
        'period' => 'month', 'popular' => true,
        'features' => [
            ['text' => '3 Websites', 'included' => true],
            ['text' => '50 GB Bandwidth', 'included' => true],
            ['text' => 'Priority Support', 'included' => true],
            ['text' => 'SSL + Backup', 'included' => true],
            ['text' => 'Admin Panel', 'included' => true],
            ['text' => 'AI Features', 'included' => false],
        ]
    ],
    'business' => [
        'id' => 'business', 'label' => 'Business', 'price' => 49.00,
        'period' => 'month', 'popular' => false,
        'features' => [
            ['text' => 'Unlimited Websites', 'included' => true],
            ['text' => '500 GB Bandwidth', 'included' => true],
            ['text' => '24/7 Support', 'included' => true],
            ['text' => 'SSL + Daily Backup', 'included' => true],
            ['text' => 'Admin Panel', 'included' => true],
            ['text' => 'AI Features', 'included' => true],
        ]
    ],
];

$plans = file_exists($plansFile) ? (json_decode(file_get_contents($plansFile), true) ?: $defaultPlans) : $defaultPlans;
$lastSaved = file_exists($plansFile) ? date('M j, Y — g:i A', filemtime($plansFile)) : 'Never';
// DB-backed quota/rate rows (source of truth for storage enforcement).
try { ensure_db_plans_for_json($plans); } catch (Throwable $e) {}
$dbPlans = StorageService::getAllPlans(false);
$canEditPlans = has_permission('plans_edit') || has_permission('*');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SaaS Plans & Pricing — Platform Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#0a0d14;color:#e2e8f0;min-height:100vh;display:flex}
.sidebar{width:260px;background:#0d121c;border-right:1px solid #1e293b;padding:1.5rem 1rem;flex-shrink:0;min-height:100vh}
.main{flex:1;padding:2.5rem 2rem;overflow-y:auto;max-width:1400px}
.brand{display:flex;align-items:center;gap:.75rem;padding:0 .5rem 1.5rem;border-bottom:1px solid #1e293b;margin-bottom:1.25rem}
.brand .logo{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.7rem 1rem;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:.88rem;font-weight:600;margin-bottom:.35rem;transition:.2s}
.nav-item:hover{background:#111622;color:#fff}
.nav-item.active{background:#1e1b4b;color:#a5b4fc;font-weight:700}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.h1{font-size:1.85rem;font-weight:900;color:#fff;letter-spacing:-.02em}
.sub{color:#64748b;font-size:.9rem;margin-top:.25rem}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.4rem;border-radius:10px;font-weight:700;font-size:.88rem;cursor:pointer;border:none;transition:.2s;text-decoration:none}
.btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,.4)}
.btn-ghost{background:#1e293b;color:#94a3b8;border:1.5px solid #334155}
.btn-ghost:hover{border-color:#6366f1;color:#fff}
.btn-sm{padding:.4rem .8rem;font-size:.78rem}
.btn-danger{background:#dc2626;color:#fff}
.btn-danger:hover{background:#b91c1c}
.plans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:2.5rem}
@media(max-width:1050px){.plans-grid{grid-template-columns:1fr}}
.plan-card{background:#111622;border:2px solid #1e293b;border-radius:20px;padding:1.75rem;transition:.25s;position:relative}
.plan-card.popular{border-color:#10b981;box-shadow:0 0 35px rgba(16,185,129,.15)}
.popular-badge{position:absolute;top:-12px;right:24px;background:#10b981;color:#0a0d14;font-size:.7rem;font-weight:900;letter-spacing:.06em;padding:.2rem .8rem;border-radius:999px;text-transform:uppercase}
.card-header{margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid #1e293b}
.field{margin-bottom:1rem}
.lbl{display:block;font-size:.76rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem}
.inp{width:100%;padding:.7rem .9rem;border:1.5px solid #283347;border-radius:10px;background:#0b0f17;color:#fff;font-family:inherit;font-size:.92rem;transition:.2s}
.inp:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.2)}
.price-row{display:flex;align-items:center;gap:.5rem}
.price-prefix{font-size:1.4rem;font-weight:800;color:#6366f1}
.select{width:100%;padding:.7rem .9rem;border:1.5px solid #283347;border-radius:10px;background:#0b0f17;color:#fff;font-family:inherit;font-size:.88rem;cursor:pointer}
.toggle-popular{display:flex;align-items:center;gap:.6rem;cursor:pointer;font-size:.83rem;font-weight:600;color:#cbd5e1;user-select:none;margin-top:.5rem}
.toggle-popular input{accent-color:#10b981;width:17px;height:17px;cursor:pointer}
.feature-list{display:flex;flex-direction:column;gap:.65rem;margin-bottom:1rem;min-height:160px}
.feature-row{display:flex;align-items:center;gap:.6rem;background:#0b0f17;border:1px solid #1e293b;border-radius:9px;padding:.45rem .75rem;transition:.2s}
.feature-row:hover{border-color:#334155}
.feature-row.not-included{opacity:.5}
.feature-check{cursor:pointer;accent-color:#10b981;width:16px;height:16px;flex-shrink:0}
.feature-text{flex:1;background:transparent;border:none;color:#fff;font-family:inherit;font-size:.84rem;outline:none}
.feature-text:focus{color:#a5b4fc}
.feature-del{background:transparent;border:none;color:#ef4444;cursor:pointer;font-size:.9rem;padding:.2rem;border-radius:5px;opacity:.6;transition:.2s}
.feature-del:hover{opacity:1;background:rgba(239,68,68,.1)}
.add-feature-btn{width:100%;background:#0b0f17;border:1.5px dashed #334155;border-radius:10px;color:#94a3b8;padding:.6rem;font-size:.8rem;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit}
.add-feature-btn:hover{border-color:#6366f1;color:#a5b4fc;background:#1e1b4b}
.preview-section{background:#111622;border:1px solid #1e293b;border-radius:20px;padding:2rem;margin-top:2.5rem}
.preview-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem}
.preview-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem}
@media(max-width:900px){.preview-grid{grid-template-columns:1fr}}
.preview-card{background:#0b0f17;border:2px solid #283347;border-radius:16px;padding:1.5rem 1.25rem;text-align:left;position:relative}
.preview-card.pop{border-color:#10b981;background:linear-gradient(180deg,#062b22,#0b0f17)}
.preview-card h3{font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:.25rem}
.preview-card .price{font-size:1.85rem;font-weight:900;color:#a5b4fc;margin:.4rem 0 .8rem}
.preview-card .price small{font-size:.75rem;color:#94a3b8;font-weight:600}
.preview-card ul{list-style:none;padding:0;display:flex;flex-direction:column;gap:.4rem}
.preview-card li{font-size:.82rem;display:flex;align-items:center;gap:.5rem;color:#cbd5e1}
.preview-card li.off{color:#64748b;text-decoration:line-through}
.toast{position:fixed;bottom:2rem;right:2rem;background:#10b981;color:#fff;padding:.85rem 1.5rem;border-radius:12px;font-weight:700;box-shadow:0 15px 40px rgba(0,0,0,.5);transform:translateY(100px);opacity:0;transition:.3s;z-index:99999}
.toast.show{transform:translateY(0);opacity:1}
.toast.error{background:#ef4444}
.info-banner{background:#1e1b4b;border:1px solid #4338ca;border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.75rem;display:flex;align-items:center;gap:.9rem;color:#c7d2fe;font-size:.86rem}
.info-banner .icon{font-size:1.4rem;flex-shrink:0}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="brand">
    <div class="logo">⚡</div>
    <div>
      <div style="font-weight:800;font-size:.95rem;color:#fff"><?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'WebCraft AI') ?></div>
      <div style="font-size:.72rem;color:#64748b">Platform Admin</div>
    </div>
  </div>
  <nav>
    <a href="index.php" class="nav-item">📊 Dashboard</a>
    <a href="tenants.php" class="nav-item">👥 Customers &amp; Sites</a>
    <a href="plans.php" class="nav-item active">💳 Plans &amp; Pricing</a>
    <a href="billing.php" class="nav-item">💰 Subscriptions</a>
    <a href="deployments.php" class="nav-item">🚀 Deployments</a>
    <a href="domains.php" class="nav-item">🌐 Domains &amp; SSL</a>
    <a href="ai-usage.php" class="nav-item">🤖 AI Tokens &amp; Cost</a>
    <a href="tickets.php" class="nav-item">🎫 Support Tickets</a>
    <a href="audit-logs.php" class="nav-item">📜 Audit Trail</a>
    <a href="notifications.php" class="nav-item">🔔 Notifications</a>
    <a href="team.php" class="nav-item">🛡️ Admin Team</a>
  </nav>
  <div style="margin-top:auto;padding-top:2rem;border-top:1px solid #1e293b">
    <a href="<?= SITE_URL ?>/publish.php" target="_blank" class="nav-item" style="color:#10b981">↗ Test Publish Page</a>
    <a href="logout.php" class="nav-item" style="color:#ef4444">🚪 Log Out</a>
  </div>
</aside>

<!-- Main Content -->
<main class="main">
  <div class="header">
    <div>
      <h1 class="h1">💳 SaaS Subscription Plans &amp; Pricing</h1>
      <p class="sub">Changes here immediately update the plan cards on the public <strong>publish.php</strong> checkout screen.</p>
    </div>
    <div style="display:flex;align-items:center;gap:1rem">
      <div style="text-align:right">
        <div style="font-size:.75rem;color:#64748b">Last Saved</div>
        <div style="font-size:.82rem;font-weight:700;color:#cbd5e1" id="last-saved"><?= $lastSaved ?></div>
      </div>
      <button class="btn btn-primary" id="btn-save" onclick="savePlans()">
        <span>💾</span> Save All Plans
      </button>
    </div>
  </div>

  <div class="info-banner">
    <div class="icon">💡</div>
    <div>
      <strong>Live Sync:</strong> Click <strong>"Save All Plans"</strong> and any changes (names, prices, checklist features, popular badge) will appear instantly on the publish page when customers reach Step 3 (Payment). You can toggle each feature's checkbox to show it as included ✅ or crossed-out ❌.
    </div>
  </div>

  <!-- Plans Editor Cards -->
  <div class="plans-grid" id="plans-container">
    <?php foreach ($plans as $key => $p): 
      $isPopular = !empty($p['popular']);
    ?>
    <div class="plan-card <?= $isPopular ? 'popular' : '' ?>" id="card-<?= htmlspecialchars($key) ?>" data-key="<?= htmlspecialchars($key) ?>">
      <?php if ($isPopular): ?>
        <span class="popular-badge" id="badge-<?= htmlspecialchars($key) ?>">★ Most Popular</span>
      <?php endif; ?>

      <div class="card-header">
        <div class="field">
          <label class="lbl">Plan Name</label>
          <input type="text" class="inp plan-label" value="<?= htmlspecialchars($p['label']) ?>" placeholder="Plan Name" oninput="updatePreview()">
        </div>

        <div class="field">
          <label class="lbl">Price &amp; Billing Period</label>
          <div class="price-row">
            <span class="price-prefix">$</span>
            <input type="number" step="0.50" min="0" class="inp plan-price" value="<?= number_format((float)$p['price'], 2, '.', '') ?>" style="font-weight:800;font-size:1.15rem" oninput="updatePreview()">
            <select class="select plan-period" onchange="updatePreview()">
              <option value="month" <?= ($p['period'] ?? '') === 'month' ? 'selected' : '' ?>>/mo</option>
              <option value="year" <?= ($p['period'] ?? '') === 'year' ? 'selected' : '' ?>>/yr</option>
              <option value="lifetime" <?= ($p['period'] ?? '') === 'lifetime' ? 'selected' : '' ?>>lifetime</option>
            </select>
          </div>
        </div>

        <label class="toggle-popular">
          <input type="checkbox" class="plan-popular" <?= $isPopular ? 'checked' : '' ?> onchange="handlePopularChange('<?= htmlspecialchars($key) ?>', this.checked)">
          <span>Highlight as "Most Popular" Plan</span>
        </label>
      </div>

      <!-- Feature Checklist -->
      <label class="lbl" style="margin-bottom:.5rem">Checklist Features</label>
      <div class="feature-list" id="features-<?= htmlspecialchars($key) ?>">
        <?php foreach ($p['features'] ?? [] as $idx => $f): 
          $inc = !empty($f['included']);
        ?>
        <div class="feature-row <?= !$inc ? 'not-included' : '' ?>">
          <input type="checkbox" class="feature-check" title="Toggle included" <?= $inc ? 'checked' : '' ?> onchange="toggleFeatureIncluded(this); updatePreview()">
          <input type="text" class="feature-text" value="<?= htmlspecialchars($f['text']) ?>" placeholder="Feature description..." oninput="updatePreview()">
          <button type="button" class="feature-del" title="Delete feature" onclick="removeFeatureRow(this); updatePreview()">🗑️</button>
        </div>
        <?php endforeach; ?>
      </div>

      <button type="button" class="add-feature-btn" onclick="addFeatureRow('<?= htmlspecialchars($key) ?>')">
        + Add Feature
      </button>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Storage Quotas & Per-GB Rates (DB source of truth) -->
  <div class="preview-section" style="margin-top:0;margin-bottom:2.5rem;">
    <div class="preview-title">
      <div>
        <h2 style="font-size:1.1rem;font-weight:800;color:#fff">💾 Storage Quotas &amp; Per-GB Rates</h2>
        <p style="font-size:.8rem;color:#64748b;margin-top:.2rem">Enforced on every customer upload. Rates are charged in <?= htmlspecialchars(defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : 'USD') ?> via PayPal. Saving here also syncs quota fields into <code>storage/plans.json</code> so readers never silently disagree.</p>
      </div>
    </div>
    <?php if (!empty($quotaMsg)): ?><div class="toast show" style="position:static;transform:none;opacity:1;margin-bottom:1rem;" id="q-toast-ok"><?= htmlspecialchars($quotaMsg) ?></div><?php endif; ?>
    <?php if (!empty($quotaErr)): ?><div class="toast show error" style="position:static;transform:none;opacity:1;margin-bottom:1rem;"><?= htmlspecialchars($quotaErr) ?></div><?php endif; ?>
    <?php if (empty($dbPlans)): ?>
      <p style="color:#f59e0b;font-size:.85rem;">Database unavailable — quota editing is disabled until the database is reachable. (Checkout pricing above still works from plans.json.)</p>
    <?php else: ?>
    <form method="POST" action="plans.php">
      <input type="hidden" name="action" value="save_storage_quotas">
      <?= csrf_field() ?>
      <div style="overflow-x:auto;">
      <table style="min-width:760px;">
        <thead><tr><th>Plan (slug)</th><th>Storage MB</th><th>Bandwidth MB</th><th>Max sites</th><th>Extra GB rate (USD)</th><th>Currency</th><th>Active</th><th style="text-align:right;">Danger</th></tr></thead>
        <tbody>
        <?php foreach ($dbPlans as $p): $pid = (int)$p['id']; ?>
          <tr>
            <td><strong style="color:#fff;"><?= htmlspecialchars($p['name']) ?></strong><br><span style="font-size:.72rem;color:#64748b;font-family:monospace;"><?= htmlspecialchars($p['slug']) ?></span></td>
            <td><input type="number" min="0" name="q_storage_<?= $pid ?>" value="<?= (int)$p['storage_limit_mb'] ?>" <?= $canEditPlans ? '' : 'readonly' ?> style="max-width:110px;"></td>
            <td><input type="number" min="0" name="q_bandwidth_<?= $pid ?>" value="<?= (int)$p['bandwidth_limit_mb'] ?>" <?= $canEditPlans ? '' : 'readonly' ?> style="max-width:110px;"></td>
            <td><input type="number" min="0" name="q_sites_<?= $pid ?>" value="<?= (int)$p['max_sites'] ?>" <?= $canEditPlans ? '' : 'readonly' ?> style="max-width:90px;"></td>
            <td><input type="number" step="0.01" min="0" name="q_rate_<?= $pid ?>" value="<?= number_format((float)$p['price_per_extra_gb'], 2, '.', '') ?>" <?= $canEditPlans ? '' : 'readonly' ?> style="max-width:100px;"></td>
            <td><input type="text" name="q_currency_<?= $pid ?>" value="<?= htmlspecialchars($p['currency']) ?>" <?= $canEditPlans ? '' : 'readonly' ?> style="max-width:70px;"></td>
            <td><input type="checkbox" name="q_active_<?= $pid ?>" value="1" <?= !empty($p['is_active']) ? 'checked' : '' ?> <?= $canEditPlans ? '' : 'disabled' ?> style="width:18px;height:18px;accent-color:#10b981;"></td>
            <td style="text-align:right;white-space:nowrap;">
              <?php if ($canEditPlans): ?>
              <button type="submit" form="q-toggle-<?= $pid ?>" class="btn btn-ghost btn-sm" title="Toggle active"><?= !empty($p['is_active']) ? 'Disable' : 'Enable' ?></button>
              <button type="submit" form="q-del-<?= $pid ?>" class="btn btn-danger btn-sm" title="Delete if unreferenced" onclick="return confirm('Delete plan <?= htmlspecialchars($p['slug']) ?>? Only unreferenced plans can be deleted.')">Delete</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php if ($canEditPlans): ?>
      <div style="text-align:right;margin-top:1rem;"><button type="submit" class="btn btn-primary">💾 Save Quotas &amp; Rates</button></div>
      <?php else: ?>
      <p style="font-size:.78rem;color:#f59e0b;margin-top:.75rem;">🔒 Read-only (Super/Billing Admin can modify).</p>
      <?php endif; ?>
    </form>
    <?php foreach ($dbPlans as $p): $pid = (int)$p['id']; ?>
      <form method="POST" action="plans.php" id="q-toggle-<?= $pid ?>" style="display:none;"><input type="hidden" name="action" value="storage_plan_toggle"><input type="hidden" name="plan_id" value="<?= $pid ?>"><?= csrf_field() ?></form>
      <form method="POST" action="plans.php" id="q-del-<?= $pid ?>" style="display:none;"><input type="hidden" name="action" value="storage_plan_delete"><input type="hidden" name="plan_id" value="<?= $pid ?>"><?= csrf_field() ?></form>
    <?php endforeach; ?>
    <?php if ($canEditPlans): ?>
    <form method="POST" action="plans.php" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1.25rem;align-items:flex-end;">
      <input type="hidden" name="action" value="storage_plan_create"><?= csrf_field() ?>
      <div><label class="lbl">Slug</label><input type="text" name="np_slug" class="inp" placeholder="starter" style="max-width:140px;"></div>
      <div><label class="lbl">Name</label><input type="text" name="np_name" class="inp" placeholder="Starter" style="max-width:160px;"></div>
      <div><label class="lbl">Storage MB</label><input type="number" min="0" name="np_storage" value="250" class="inp" style="max-width:110px;"></div>
      <div><label class="lbl">Bandwidth MB</label><input type="number" min="0" name="np_bandwidth" value="5120" class="inp" style="max-width:110px;"></div>
      <div><label class="lbl">Sites</label><input type="number" min="0" name="np_sites" value="1" class="inp" style="max-width:80px;"></div>
      <div><label class="lbl">Rate/GB</label><input type="number" step="0.01" min="0" name="np_rate" value="2.00" class="inp" style="max-width:90px;"></div>
      <div><button type="submit" class="btn btn-ghost">＋ Create Plan</button></div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Live Customer Preview -->
  <div class="preview-section">
    <div class="preview-title">
      <div>
        <h2 style="font-size:1.1rem;font-weight:800;color:#fff">👁️ Live Customer Preview (Step 3 Checkout)</h2>
        <p style="font-size:.8rem;color:#64748b;margin-top:.2rem">This is exactly how your customers will see these plans when publishing a website.</p>
      </div>
      <a href="<?= SITE_URL ?>/publish.php" target="_blank" class="btn btn-ghost btn-sm">↗ Open Publish Page</a>
    </div>

    <div class="preview-grid" id="preview-grid">
      <!-- Dynamically filled by JS -->
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>

<script>
// Toggle popular checkbox so only one plan is popular at a time
function handlePopularChange(changedKey, isChecked) {
  document.querySelectorAll('.plan-card').forEach(card => {
    const k = card.dataset.key;
    const chk = card.querySelector('.plan-popular');
    const existingBadge = card.querySelector('.popular-badge');
    if (k === changedKey && isChecked) {
      card.classList.add('popular');
      if (!existingBadge) {
        const badge = document.createElement('span');
        badge.className = 'popular-badge';
        badge.id = 'badge-' + k;
        badge.textContent = '★ Most Popular';
        card.prepend(badge);
      }
    } else {
      if (isChecked) {
        card.classList.remove('popular');
        if (chk) chk.checked = false;
        if (existingBadge) existingBadge.remove();
      } else if (k === changedKey) {
        card.classList.remove('popular');
        if (existingBadge) existingBadge.remove();
      }
    }
  });
  updatePreview();
}

function toggleFeatureIncluded(chk) {
  const row = chk.closest('.feature-row');
  row.classList.toggle('not-included', !chk.checked);
}

function removeFeatureRow(btn) {
  btn.closest('.feature-row').remove();
}

function addFeatureRow(key) {
  const container = document.getElementById('features-' + key);
  const row = document.createElement('div');
  row.className = 'feature-row';
  row.innerHTML = `
    <input type="checkbox" class="feature-check" checked title="Toggle included" onchange="toggleFeatureIncluded(this); updatePreview()">
    <input type="text" class="feature-text" value="New Feature" placeholder="Feature description..." oninput="updatePreview()">
    <button type="button" class="feature-del" title="Delete feature" onclick="removeFeatureRow(this); updatePreview()">🗑️</button>
  `;
  container.appendChild(row);
  const input = row.querySelector('.feature-text');
  input.focus();
  input.select();
  updatePreview();
}

// Build the data object from the current DOM
function collectPlansData() {
  const data = {};
  document.querySelectorAll('.plan-card').forEach(card => {
    const key = card.dataset.key;
    const label = card.querySelector('.plan-label').value.trim() || key;
    const price = parseFloat(card.querySelector('.plan-price').value) || 0;
    const period = card.querySelector('.plan-period').value;
    const popular = card.querySelector('.plan-popular').checked;
    
    const features = [];
    card.querySelectorAll('.feature-row').forEach(row => {
      const text = row.querySelector('.feature-text').value.trim();
      const included = row.querySelector('.feature-check').checked;
      if (text) {
        features.push({ text, included });
      }
    });

    data[key] = { id: key, label, price, period, popular, features };
  });
  return data;
}

// Live update customer preview
function updatePreview() {
  const data = collectPlansData();
  const container = document.getElementById('preview-grid');
  container.innerHTML = '';

  Object.values(data).forEach(p => {
    const periodLabel = p.period === 'month' ? '/mo' : (p.period === 'year' ? '/yr' : (p.period === 'lifetime' ? 'one-time' : ''));
    const card = document.createElement('div');
    card.className = 'preview-card' + (p.popular ? ' pop' : '');
    card.innerHTML = `
      ${p.popular ? '<span style="position:absolute;top:-10px;right:16px;background:#10b981;color:#0a0d14;font-size:.65rem;font-weight:900;letter-spacing:.05em;padding:.15rem .6rem;border-radius:999px;text-transform:uppercase">POPULAR</span>' : ''}
      <h3>${escapeHtml(p.label)}</h3>
      <div class="price">$${p.price.toFixed(2)} <small>${periodLabel}</small></div>
      <ul>
        ${p.features.map(f => `
          <li class="${!f.included ? 'off' : ''}">
            <span>${f.included ? '✓' : '✗'}</span>
            <span>${escapeHtml(f.text)}</span>
          </li>
        `).join('')}
      </ul>
    `;
    container.appendChild(card);
  });
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Save Plans via AJAX POST
async function savePlans() {
  const btn = document.getElementById('btn-save');
  btn.disabled = true;
  btn.innerHTML = '⏳ Saving…';
  const doneLoad = (window.paAjaxLoad ? paAjaxLoad('Saving plans…', 'Updating pricing tables', 'save') : () => {});

  const data = collectPlansData();
  const formData = new FormData();
  formData.append('action', 'save_plans');
  formData.append('plans_json', JSON.stringify(data));

  try {
    const res = await fetch('plans.php', { method: 'POST', body: formData });
    const j = await res.json();
    doneLoad();
    if (j.success) {
      showToast('✅ Plans saved! Publish page updated.');
      document.getElementById('last-saved').textContent = j.saved_at;
    } else {
      showToast('❌ ' + (j.error || 'Failed to save'), true);
    }
  } catch (err) {
    doneLoad();
    showToast('❌ Network error', true);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<span>💾</span> Save All Plans';
  }
}

let toastTimer;
function showToast(msg, isError = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (isError ? ' error' : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.className = 'toast', 3500);
}

// Initial preview render on page load
document.addEventListener('DOMContentLoaded', updatePreview);
</script>
</body>
</html>
