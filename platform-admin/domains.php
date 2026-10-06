<?php
/**
 * platform-admin/domains.php
 * Feature 4: Domain & SSL Expiry Tracker
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('domains_ssl');

$records = get_domain_ssl_records();
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── ACTION: Renew SSL (+90 Days) ───────────────────────────
if ($action === 'renew_ssl' && isset($_GET['order_id'])) {
    $oid = $_GET['order_id'];
    $ord = load_order($oid);
    if ($ord) {
        $ord['ssl_renewed_at'] = date('Y-m-d H:i:s');
        $ord['ssl_expires_at'] = date('Y-m-d H:i:s', strtotime('+90 days'));
        save_order($ord);

        save_notification($oid, [
            'type'    => 'info',
            'subject' => '🔒 SSL Certificate Automatically Renewed',
            'message' => 'Your HTTPS SSL certificate for ' . ($ord['site_name'] ?? 'your website') . ' has been renewed successfully for another 90 days.',
        ]);

        log_admin_action('ssl_renewed', $oid, "Renewed SSL certificate (+90 days) for tenant {$ord['slug']}");
        header('Location: domains.php?msg=SSL+certificate+renewed+successfully+for+another+90+days&type=success');
        exit;
    }
}

// ─── ACTION: Send SSL Warning ───────────────────────────────
if ($action === 'send_ssl_warning' && isset($_GET['order_id'])) {
    $oid = $_GET['order_id'];
    $ord = load_order($oid);
    if ($ord) {
        save_notification($oid, [
            'type'    => 'warning',
            'subject' => '🔒 SSL Certificate Expiring Soon',
            'message' => 'Your domain SSL certificate for ' . ($ord['site_name'] ?? 'your site') . ' is scheduled to expire in fewer than 14 days. Please review your hosting plan or contact support to ensure uninterrupted HTTPS security.',
        ]);

        log_admin_action('ssl_notice_sent', $oid, "Dispatched SSL expiry notice to tenant {$oid}");
        header('Location: domains.php?msg=SSL+expiry+notification+sent+to+tenant&type=success');
        exit;
    }
}

// ─── ACTION: Set Custom Domain ──────────────────────────────
if ($action === 'set_custom_domain' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $oid = $_POST['order_id'] ?? '';
    $dom = strtolower(trim($_POST['custom_domain'] ?? ''));
    $ord = load_order($oid);

    if ($ord) {
        $ord['custom_domain'] = $dom;
        save_order($ord);
        log_admin_action('custom_domain_set', $oid, "Associated custom domain '{$dom}' to tenant {$ord['slug']}");
        header('Location: domains.php?msg=Custom+domain+configured+successfully&type=success');
        exit;
    }
}

// Metrics
$totalDomains    = count($records);
$sslValidCount   = 0;
$sslExpiringCount= 0;
$sslExpiredCount = 0;

foreach ($records as $r) {
    if ($r['ssl_status'] === 'valid') $sslValidCount++;
    elseif ($r['ssl_status'] === 'expiring_soon') $sslExpiringCount++;
    else $sslExpiredCount++;
}

render_head('Domain & SSL Expiry Tracker');
render_sidebar('domains');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>🔒 Domain &amp; SSL Expiry Tracker</h1>
      <p>Automated certificate health monitoring, expiry countdowns, and automated renewal triggers.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="deployments.php" class="btn btn-ghost">🚀 Deployments</a>
      <a href="notifications.php" class="btn btn-warning">📢 Broadcast Expiry Notice</a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= ($_GET['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Domain KPIs -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="card" style="border-left:4px solid #6366f1;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Monitored Domains</div>
      <div style="font-size:1.8rem;font-weight:800;color:#fff;margin-top:6px;"><?= $totalDomains ?></div>
      <div style="font-size:0.75rem;color:#818cf8;margin-top:4px;">Subdomains &amp; Custom URLs</div>
    </div>
    <div class="card" style="border-left:4px solid #10b981;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">SSL Secure (Valid)</div>
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;margin-top:6px;"><?= $sslValidCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">TLS 1.3 Active</div>
    </div>
    <div class="card" style="border-left:4px solid #f59e0b;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Expiring Soon (&lt;14d)</div>
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:6px;"><?= $sslExpiringCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Requires auto-renewal</div>
    </div>
    <div class="card" style="border-left:4px solid #ef4444;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Expired / Unsecured</div>
      <div style="font-size:1.8rem;font-weight:800;color:#ef4444;margin-top:6px;"><?= $sslExpiredCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Certificate expired</div>
    </div>
  </div>

  <!-- Domains Table -->
  <div class="card" style="padding:0;overflow:hidden;margin-bottom:28px;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">🌐 Monitored Tenant Domain Certificates</h2>
      <span style="font-size:0.75rem;color:var(--muted);">Automated 90-day Let's Encrypt lifecycle</span>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Site / Tenant</th>
            <th>Endpoint &amp; Domain</th>
            <th>SSL Status</th>
            <th>SSL Expiry Countdown</th>
            <th>Domain Registration</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);">No domains recorded yet.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $r): 
              $days = $r['ssl_days_left'];
              $isExpired  = ($days < 0);
              $isExpiring = ($days <= 14 && $days >= 0);
            ?>
            <tr>
              <td>
                <div style="font-weight:700;color:#fff;"><?= htmlspecialchars($r['site_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($r['client_email']) ?></div>
                <span class="code-badge" style="font-size:0.68rem;"><?= htmlspecialchars($r['order_id']) ?></span>
              </td>
              <td>
                <div style="font-family:'Fira Code',monospace;font-size:0.8rem;color:#22d3ee;">
                  <?= htmlspecialchars($r['domain']) ?>
                </div>
                <?php if (!empty($r['custom_domain'])): ?>
                  <div style="font-size:0.75rem;color:#a5b4fc;margin-top:2px;">
                    ⭐ Custom: <strong><?= htmlspecialchars($r['custom_domain']) ?></strong>
                  </div>
                <?php else: ?>
                  <button type="button" 
                          onclick="openDomainModal('<?= htmlspecialchars($r['order_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($r['site_name'], ENT_QUOTES) ?>')"
                          style="background:none;border:none;color:#6366f1;font-size:0.72rem;cursor:pointer;text-decoration:underline;padding:0;margin-top:3px;">
                    + Connect Custom Domain
                  </button>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isExpired): ?>
                  <span style="font-size:0.75rem;font-weight:700;color:#ef4444;background:rgba(239,68,68,0.15);padding:3px 8px;border-radius:999px;">
                    ❌ Expired
                  </span>
                <?php elseif ($isExpiring): ?>
                  <span style="font-size:0.75rem;font-weight:700;color:#f59e0b;background:rgba(245,158,11,0.15);padding:3px 8px;border-radius:999px;">
                    ⚠️ Expiring Soon
                  </span>
                <?php else: ?>
                  <span style="font-size:0.75rem;font-weight:700;color:#10b981;background:rgba(16,185,129,0.15);padding:3px 8px;border-radius:999px;">
                    🔒 Valid (HTTPS)
                  </span>
                <?php endif; ?>
                <div style="font-size:0.7rem;color:var(--muted);margin-top:3px;">Auto-SSL Cert</div>
              </td>
              <td>
                <?php if ($isExpired): ?>
                  <div style="font-size:0.82rem;font-weight:800;color:#ef4444;">Expired <?= abs($days) ?>d ago</div>
                <?php elseif ($isExpiring): ?>
                  <div style="font-size:0.82rem;font-weight:800;color:#f59e0b;">⏳ <?= $days ?> days left</div>
                <?php else: ?>
                  <div style="font-size:0.82rem;font-weight:700;color:#10b981;">✓ <?= $days ?> days left</div>
                <?php endif; ?>
                <div style="font-size:0.72rem;color:var(--muted);margin-top:2px;">Due: <?= htmlspecialchars($r['ssl_expiry']) ?></div>
              </td>
              <td>
                <div style="font-size:0.82rem;font-weight:600;color:#cbd5e1;"><?= $r['domain_days'] ?> days</div>
                <div style="font-size:0.72rem;color:var(--muted);margin-top:2px;">Cycle: <?= htmlspecialchars($r['domain_expiry']) ?></div>
              </td>
              <td style="text-align:right;">
                <div style="display:inline-flex;gap:6px;align-items:center;">
                  
                  <!-- Renew SSL Trigger -->
                  <a href="?action=renew_ssl&order_id=<?= urlencode($r['order_id']) ?>" 
                     onclick="return confirm('Renew SSL certificate for <?= htmlspecialchars($r['site_name']) ?> for another 90 days?')"
                     class="btn btn-success btn-sm" title="Re-issue SSL Certificate">
                    🔒 Renew SSL
                  </a>

                  <!-- Warn Tenant -->
                  <?php if ($isExpiring || $isExpired): ?>
                    <a href="?action=send_ssl_warning&order_id=<?= urlencode($r['order_id']) ?>" 
                       class="btn btn-warning btn-sm" title="Send expiry alert to tenant">
                      ⚠️ Send Warning
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

  <!-- Custom Domain Modal -->
  <div id="domainModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);align-items:center;justify-content:center;z-index:99999;padding:20px;">
    <div style="background:#111622;border:1.5px solid var(--border);border-radius:20px;max-width:480px;width:100%;padding:28px;box-shadow:0 25px 60px rgba(0,0,0,0.8);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <h3 style="font-size:1.15rem;font-weight:800;color:#fff;">🌐 Associate Custom Domain</h3>
        <button onclick="closeDomainModal()" style="background:none;border:none;color:var(--muted);font-size:1.2rem;cursor:pointer;">✕</button>
      </div>

      <p style="color:var(--muted);font-size:0.85rem;line-height:1.6;margin-bottom:16px;">
        Map a custom branded domain (e.g. <code style="color:#22d3ee;">clientbrand.com</code>) to tenant <strong id="domSiteName" style="color:#fff;"></strong>.
      </p>

      <form method="POST" action="domains.php">
        <input type="hidden" name="action" value="set_custom_domain">
        <input type="hidden" name="order_id" id="domOrderIdInput" value="">

        <div class="form-group">
          <label>Custom Fully Qualified Domain Name (FQDN)</label>
          <input type="text" name="custom_domain" placeholder="e.g. mycompany.com" required style="padding:10px;">
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
          <button type="button" onclick="closeDomainModal()" class="btn btn-ghost">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Domain Record</button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
function openDomainModal(orderId, siteName) {
  document.getElementById('domOrderIdInput').value = orderId;
  document.getElementById('domSiteName').textContent = siteName;
  document.getElementById('domainModal').style.display = 'flex';
}
function closeDomainModal() {
  document.getElementById('domainModal').style.display = 'none';
}
</script>
</div></body></html>
