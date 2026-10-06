<?php
/**
 * platform-admin/billing.php
 * Feature 2: Subscription & Billing Admin
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('billing');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$orders = load_all_orders();

// Plans file
$plansFile = storage_dir() . '/plans_config.json';
$plans = [
    'starter'  => ['label' => 'Starter Plan',  'price' => 9.00,  'currency' => 'USD', 'period' => 'month', 'sites' => 1, 'bandwidth' => '5 GB',   'support' => 'Standard', 'active' => true],
    'pro'      => ['label' => 'Pro Plan',      'price' => 19.00, 'currency' => 'USD', 'period' => 'month', 'sites' => 3, 'bandwidth' => '50 GB',  'support' => 'Priority', 'active' => true],
    'business' => ['label' => 'Business Plan', 'price' => 49.00, 'currency' => 'USD', 'period' => 'month', 'sites' => 'Unlimited', 'bandwidth' => '500 GB', 'support' => '24/7 Dedicated', 'active' => true],
];
if (file_exists($plansFile)) {
    $customPlans = json_decode(@file_get_contents($plansFile), true);
    if (is_array($customPlans)) $plans = array_merge($plans, $customPlans);
}

// ─── ACTION: Save Plan Changes ─────────────────────────────
if ($action === 'save_plans' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('plans_edit') && !has_permission('*')) {
        header('Location: billing.php?msg=Unauthorized+to+edit+plans&type=error');
        exit;
    }
    foreach (['starter', 'pro', 'business'] as $pk) {
        if (isset($_POST['price_' . $pk])) {
            $plans[$pk]['price'] = (float)$_POST['price_' . $pk];
            $plans[$pk]['label'] = trim($_POST['label_' . $pk] ?? $plans[$pk]['label']);
        }
    }
    file_put_contents($plansFile, json_encode($plans, JSON_PRETTY_PRINT));
    log_admin_action('plan_updated', null, 'Subscription plan rates & labels updated');
    header('Location: billing.php?msg=Plans+updated+successfully&type=success');
    exit;
}

// ─── ACTION: Mark Paid / Renew (+30 days) ──────────────────
if ($action === 'mark_paid' && isset($_GET['order_id'])) {
    if (!has_permission('billing_manage') && !has_permission('*')) {
        header('Location: billing.php?msg=Unauthorized&type=error');
        exit;
    }
    $oid = $_GET['order_id'];
    $ord = load_order($oid);
    if ($ord) {
        $ord['status'] = 'published';
        $ord['payment_status'] = 'completed';
        $ord['site_active'] = true;
        // Extend 30 days from now
        $ord['published_at'] = date('Y-m-d H:i:s');
        $ord['next_payment_due'] = date('Y-m-d', strtotime('+30 days'));
        save_order($ord);

        // If site was suspended on disk, reactivate
        if (!empty($ord['slug'])) {
            activate_site($ord['slug']);
        }

        save_notification($oid, [
            'type'    => 'activation',
            'subject' => 'Payment Confirmed & Subscription Renewed',
            'message' => 'Thank you! Your monthly subscription payment has been confirmed. Your website is 100% active until ' . $ord['next_payment_due'] . '.',
        ]);

        log_admin_action('payment_received', $oid, "Marked payment completed for order {$oid} ($" . ($ord['amount'] ?? 19) . ")");
        header('Location: billing.php?msg=Subscription+renewed+and+payment+marked+completed&type=success');
        exit;
    }
}

// ─── ACTION: Issue Refund ──────────────────────────────────
if ($action === 'refund' && isset($_GET['order_id'])) {
    if (!has_permission('refunds') && !has_permission('*')) {
        header('Location: billing.php?msg=Unauthorized+to+issue+refunds&type=error');
        exit;
    }
    $oid = $_GET['order_id'];
    $ord = load_order($oid);
    if ($ord) {
        $ord['payment_status'] = 'refunded';
        $ord['status'] = 'cancelled';
        $ord['site_active'] = false;
        save_order($ord);

        if (!empty($ord['slug'])) {
            deactivate_site($ord['slug']);
        }

        save_notification($oid, [
            'type'    => 'warning',
            'subject' => 'Subscription Refunded & Account Cancelled',
            'message' => 'Your subscription has been refunded. Your live website has been deactivated. Please contact billing support if you have questions.',
        ]);

        log_admin_action('refund_issued', $oid, "Issued full refund for {$oid} and suspended site");
        header('Location: billing.php?msg=Refund+recorded+and+site+suspended&type=success');
        exit;
    }
}

// ─── ACTION: Run Overdue Automation ────────────────────────
if ($action === 'run_overdue_automation') {
    $scanned   = 0;
    $warned    = 0;
    $suspended = 0;

    foreach ($orders as $o) {
        $scanned++;
        $st = subscription_status($o);
        $slug = $o['slug'] ?? '';
        $oid  = $o['order_id'] ?? '';

        if ($st === 'overdue') {
            $days = days_overdue($o);
            // Send warning if overdue
            save_notification($oid, [
                'type'    => 'payment_reminder',
                'subject' => '⚠️ Action Required: Subscription Overdue (' . $days . ' days)',
                'message' => 'Your subscription for ' . ($o['site_name'] ?? 'website') . ' is currently ' . $days . ' days overdue. Please renew immediately to avoid automatic suspension of your website.',
            ]);
            $warned++;

            // Auto-suspend if > 7 days overdue
            if ($days >= 7 && ($o['site_active'] ?? true)) {
                deactivate_site($slug);
                $o['site_active'] = false;
                save_order($o);
                $suspended++;
            }
        }
    }

    log_admin_action('overdue_automation_run', null, "Automation scanned {$scanned} tenants: {$warned} notices sent, {$suspended} sites auto-suspended");
    header("Location: billing.php?msg=Overdue+automation+completed.+Scanned:+{$scanned},+Notices:+{$warned},+Auto-suspended:+{$suspended}&type=success");
    exit;
}

// Compute Financial KPIs
$mrr = 0.0;
$totalCollected = 0.0;
$overdueAmount  = 0.0;
$activeCount    = 0;

foreach ($orders as $o) {
    $amt = (float)($o['amount'] ?? 19.0);
    $st  = subscription_status($o);

    if ($st === 'active') {
        $mrr += $amt;
        $activeCount++;
    } elseif ($st === 'overdue') {
        $overdueAmount += $amt;
    }

    // Historical collected
    if (($o['payment_status'] ?? '') !== 'refunded') {
        $totalCollected += $amt;
    }
}
$arr = $mrr * 12;

// Printable Invoice Modal view
$viewInvoice = null;
if ($action === 'view_invoice' && !empty($_GET['order_id'])) {
    $viewInvoice = load_order($_GET['order_id']);
}

render_head('Subscription & Billing Admin');
render_sidebar('billing');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>💳 Subscription &amp; Billing Admin</h1>
      <p>Manage revenue, SaaS subscription tiers, invoices, refunds, and automated overdue enforcement.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="?action=run_overdue_automation" 
         onclick="return confirm('Execute automated overdue enforcement now? It will notify overdue tenants and suspend sites overdue by >7 days.')"
         class="btn btn-warning">
        ⚡ Run Overdue Automation
      </a>
      <a href="#plans-section" class="btn btn-ghost">⚙️ Manage Plans</a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= ($_GET['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Financial KPI Metrics -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="card" style="border-left:4px solid #10b981;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">MRR (Monthly Run Rate)</div>
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;margin-top:6px;">$<?= number_format($mrr, 2) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">ARR: $<?= number_format($arr, 2) ?> USD</div>
    </div>
    <div class="card" style="border-left:4px solid #6366f1;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Total Revenue Collected</div>
      <div style="font-size:1.8rem;font-weight:800;color:#fff;margin-top:6px;">$<?= number_format($totalCollected, 2) ?></div>
      <div style="font-size:0.75rem;color:#818cf8;margin-top:4px;">Across all subscriptions</div>
    </div>
    <div class="card" style="border-left:4px solid #f59e0b;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Overdue / At Risk</div>
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:6px;">$<?= number_format($overdueAmount, 2) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Pending renewal collection</div>
    </div>
    <div class="card" style="border-left:4px solid #06b6d4;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Active Paid Tenants</div>
      <div style="font-size:1.8rem;font-weight:800;color:#22d3ee;margin-top:6px;"><?= $activeCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Healthy subscriptions</div>
    </div>
  </div>

  <!-- Invoices & Transaction Log -->
  <div class="card" style="margin-bottom:28px;padding:0;overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">🧾 Invoices &amp; Subscription Ledger</h2>
        <div style="font-size:0.78rem;color:var(--muted);">All customer checkout receipts, renewal statuses, and transaction records.</div>
      </div>
      <span style="font-size:0.78rem;color:#818cf8;font-weight:700;"><?= count($orders) ?> Total Records</span>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Invoice / Order</th>
            <th>Customer &amp; Site</th>
            <th>Plan Tier</th>
            <th>Amount</th>
            <th>Gateway</th>
            <th>Billing Status</th>
            <th>Date</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No invoices found.</td></tr>
          <?php else: ?>
            <?php foreach ($orders as $o):
              $oid    = $o['order_id'] ?? '';
              $amt    = (float)($o['amount'] ?? 19.0);
              $pstat  = $o['payment_status'] ?? 'pending';
              $subst  = subscription_status($o);
              $payMethod = $o['payment_method'] ?? ($o['paypal_capture_id'] ? 'PayPal' : 'Sandbox Simulator');
            ?>
            <tr>
              <td>
                <span class="code-badge"><?= htmlspecialchars($oid) ?></span>
                <?php if (!empty($o['paypal_capture_id'])): ?>
                  <div style="font-size:0.68rem;color:var(--muted);margin-top:2px;">Ref: <?= htmlspecialchars(substr($o['paypal_capture_id'], 0, 14)) ?>…</div>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:700;color:#fff;"><?= htmlspecialchars($o['site_name'] ?? 'Website') ?></div>
                <div style="font-size:0.74rem;color:var(--muted);"><?= htmlspecialchars($o['admin_email'] ?: ($o['client_email'] ?? '—')) ?></div>
              </td>
              <td><?= package_badge($o['package'] ?? 'pro') ?></td>
              <td style="font-weight:700;color:#fff;">$<?= number_format($amt, 2) ?> USD</td>
              <td>
                <span style="font-size:0.75rem;padding:3px 8px;border-radius:6px;background:rgba(255,255,255,0.06);border:1px solid #1e293b;color:#cbd5e1;">
                  <?= htmlspecialchars($payMethod) ?>
                </span>
              </td>
              <td>
                <?php if ($pstat === 'refunded'): ?>
                  <span style="font-size:0.75rem;padding:3px 8px;border-radius:999px;background:rgba(239,68,68,0.15);color:#ef4444;font-weight:700;">Refunded</span>
                <?php else: ?>
                  <?= status_badge($subst) ?>
                <?php endif; ?>
              </td>
              <td style="font-size:0.8rem;color:var(--muted);">
                <?= htmlspecialchars(date('M d, Y', strtotime($o['published_at'] ?? $o['created_at'] ?? 'now'))) ?>
              </td>
              <td style="text-align:right;">
                <div style="display:inline-flex;gap:4px;">
                  
                  <!-- View Receipt -->
                  <a href="?action=view_invoice&order_id=<?= urlencode($oid) ?>" class="btn btn-ghost btn-sm" title="Print/View Invoice">
                    📄 Invoice
                  </a>

                  <!-- Mark Paid / Renew -->
                  <?php if ($subst === 'overdue' && (has_permission('billing_manage') || has_permission('*'))): ?>
                    <a href="?action=mark_paid&order_id=<?= urlencode($oid) ?>" 
                       onclick="return confirm('Confirm payment received of $<?= $amt ?> and renew for 30 days?')"
                       class="btn btn-success btn-sm" title="Mark Paid & Renew">
                      ✓ Mark Paid
                    </a>
                  <?php endif; ?>

                  <!-- Refund -->
                  <?php if ($pstat !== 'refunded' && (has_permission('refunds') || has_permission('*'))): ?>
                    <a href="?action=refund&order_id=<?= urlencode($oid) ?>" 
                       onclick="return confirm('Issue refund for <?= htmlspecialchars($o['site_name']) ?>? Site will be immediately suspended.')"
                       class="btn btn-danger btn-sm" title="Refund and Cancel">
                      ↩ Refund
                    </a>
                  <?php endif; ?>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Plans Management -->
  <div class="card" id="plans-section" style="margin-bottom:28px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
      <div>
        <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">⚙️ SaaS Subscription Plans &amp; Pricing</h2>
        <div style="font-size:0.78rem;color:var(--muted);">Configure client subscription tiers, monthly rates, and entitlements.</div>
      </div>
      <?php if (!has_permission('plans_edit') && !has_permission('*')): ?>
        <span style="font-size:0.75rem;color:#f59e0b;">🔒 Read-only (Super Admin &amp; Billing Admin can modify)</span>
      <?php endif; ?>
    </div>

    <form method="POST" action="billing.php">
      <input type="hidden" name="action" value="save_plans">
      
      <div class="grid-3" style="margin-bottom:20px;">
        <?php foreach (['starter', 'pro', 'business'] as $pk): 
          $p = $plans[$pk];
          $isEditable = has_permission('plans_edit') || has_permission('*');
        ?>
        <div style="background:#0d1117;border:1.5px solid var(--border);border-radius:14px;padding:20px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="font-size:0.78rem;font-weight:800;color:#818cf8;text-transform:uppercase;"><?= strtoupper($pk) ?> TIER</div>
            <?= package_badge($pk) ?>
          </div>

          <div class="form-group">
            <label>Plan Display Name</label>
            <input type="text" name="label_<?= $pk ?>" value="<?= htmlspecialchars($p['label']) ?>" <?= $isEditable ? '' : 'readonly' ?>>
          </div>

          <div class="form-group">
            <label>Monthly Price ($ USD)</label>
            <input type="number" step="0.5" name="price_<?= $pk ?>" value="<?= htmlspecialchars($p['price']) ?>" <?= $isEditable ? '' : 'readonly' ?>>
          </div>

          <div style="font-size:0.75rem;color:var(--muted);line-height:1.7;margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);">
            <div>✓ Sites: <strong style="color:#fff;"><?= htmlspecialchars($p['sites']) ?></strong></div>
            <div>✓ Bandwidth: <strong style="color:#fff;"><?= htmlspecialchars($p['bandwidth']) ?></strong></div>
            <div>✓ Support: <strong style="color:#fff;"><?= htmlspecialchars($p['support']) ?></strong></div>
            <div>✓ SSL Certificate Included</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (has_permission('plans_edit') || has_permission('*')): ?>
        <div style="text-align:right;">
          <button type="submit" class="btn btn-primary">💾 Save Subscription Plan Rates</button>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- Printable Invoice Modal (if requested) -->
  <?php if ($viewInvoice): ?>
  <div style="position:fixed;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;z-index:99999;padding:20px;">
    <div style="background:#111622;border:1.5px solid var(--border);border-radius:20px;max-width:620px;width:100%;max-height:90vh;overflow-y:auto;padding:32px;box-shadow:0 25px 60px rgba(0,0,0,0.7);">
      
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;border-bottom:1px solid var(--border);padding-bottom:16px;">
        <div>
          <div style="font-weight:900;font-size:1.25rem;color:#fff;">INVOICE RECEIPT</div>
          <div style="font-size:0.75rem;color:#818cf8;font-family:'Fira Code',monospace;">ID: <?= htmlspecialchars($viewInvoice['order_id']) ?></div>
        </div>
        <a href="billing.php" style="background:#0d1117;border:1px solid var(--border);color:var(--muted);width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;text-decoration:none;font-weight:700;">✕</a>
      </div>

      <div class="grid-2" style="margin-bottom:20px;font-size:0.85rem;">
        <div>
          <div style="color:var(--muted);font-size:0.75rem;text-transform:uppercase;">Billed To:</div>
          <div style="font-weight:700;color:#fff;margin-top:2px;"><?= htmlspecialchars($viewInvoice['site_name']) ?></div>
          <div style="color:var(--muted);"><?= htmlspecialchars($viewInvoice['admin_email'] ?: ($viewInvoice['client_email'] ?? '—')) ?></div>
          <div style="color:var(--muted);font-size:0.75rem;">Slug: /published/<?= htmlspecialchars($viewInvoice['slug']) ?>/</div>
        </div>
        <div style="text-align:right;">
          <div style="color:var(--muted);font-size:0.75rem;text-transform:uppercase;">Invoice Date:</div>
          <div style="font-weight:700;color:#fff;margin-top:2px;"><?= date('M d, Y', strtotime($viewInvoice['published_at'] ?? 'now')) ?></div>
          <div style="color:var(--muted);font-size:0.75rem;margin-top:4px;">Status: <?= htmlspecialchars(strtoupper($viewInvoice['payment_status'] ?? 'COMPLETED')) ?></div>
        </div>
      </div>

      <div style="background:#0d1117;border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:24px;">
        <div style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--border);padding-bottom:10px;margin-bottom:10px;font-size:0.85rem;">
          <span style="color:#fff;font-weight:600;">Website Publishing &amp; Hosting (<?= htmlspecialchars($viewInvoice['package'] ?? 'Pro') ?> Plan)</span>
          <span style="color:#fff;font-weight:700;">$<?= number_format((float)($viewInvoice['amount'] ?? 19.0), 2) ?> USD</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.95rem;font-weight:800;color:#10b981;">
          <span>Total Paid:</span>
          <span>$<?= number_format((float)($viewInvoice['amount'] ?? 19.0), 2) ?> USD</span>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Invoice</button>
        <a href="billing.php" class="btn btn-ghost">Close</a>
      </div>

    </div>
  </div>
  <?php endif; ?>

</main>
</div></body></html>
