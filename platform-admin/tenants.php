<?php
/**
 * platform-admin/tenants.php
 * Feature 1: User & Tenant Management
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('tenants_view');

$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['search'] ?? '');
$orders  = load_all_orders();

// Handle quick toggle action if authorized
if (isset($_GET['toggle_slug']) && isset($_GET['set_active'])) {
    if (has_permission('site_toggle') || has_permission('*')) {
        $slug = $_GET['toggle_slug'];
        $setActive = ($_GET['set_active'] === '1');
        
        $res = $setActive ? activate_site($slug) : deactivate_site($slug);
        
        // Update order record
        foreach ($orders as &$ord) {
            if (($ord['slug'] ?? '') === $slug) {
                $ord['site_active'] = $setActive;
                save_order($ord);
                break;
            }
        }
        
        log_admin_action(
            $setActive ? 'site_activated' : 'site_deactivated',
            $slug,
            "Tenant '{$slug}' status changed to " . ($setActive ? 'ACTIVE' : 'DEACTIVATED')
        );

        header('Location: tenants.php?msg=' . urlencode($res['msg']) . '&type=' . ($res['ok'] ? 'success' : 'error'));
        exit;
    } else {
        header('Location: tenants.php?msg=' . urlencode('Unauthorized to toggle site status') . '&type=error');
        exit;
    }
}

// Compute tenant statistics
$totalTenants     = count($orders);
$activeTenants    = 0;
$overdueTenants   = 0;
$suspendedTenants = 0;
$dbTenants        = 0;

foreach ($orders as $o) {
    $st = subscription_status($o);
    if ($st === 'active') $activeTenants++;
    elseif ($st === 'overdue') $overdueTenants++;
    elseif ($st === 'deactivated') $suspendedTenants++;

    if (($o['gen_mode'] ?? '') === 'database') $dbTenants++;
}

// Filter tenants
$filtered = array_filter($orders, function($o) use ($filter, $search) {
    $st = subscription_status($o);
    
    // Status filter
    if ($filter === 'active' && $st !== 'active') return false;
    if ($filter === 'overdue' && $st !== 'overdue') return false;
    if ($filter === 'suspended' && $st !== 'deactivated') return false;
    if ($filter === 'database' && ($o['gen_mode'] ?? '') !== 'database') return false;
    if ($filter === 'static' && ($o['gen_mode'] ?? '') !== 'static') return false;

    // Search query
    if ($search !== '') {
        $needle = strtolower($search);
        $haystack = strtolower(
            ($o['site_name'] ?? '') . ' ' .
            ($o['slug'] ?? '') . ' ' .
            ($o['order_id'] ?? '') . ' ' .
            ($o['admin_email'] ?? '') . ' ' .
            ($o['client_email'] ?? '') . ' ' .
            ($o['admin_username'] ?? '')
        );
        if (strpos($haystack, $needle) === false) return false;
    }

    return true;
});

render_head('User & Tenant Management');
render_sidebar('tenants');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>🏢 User &amp; Tenant Management</h1>
      <p>Real-time directory of every customer, deployed subdomain, package tier, and site status.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="billing.php" class="btn btn-warning">💳 Subscriptions</a>
      <a href="notifications.php" class="btn btn-primary">📢 Broadcast Notice</a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= ($_GET['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- KPI Cards -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="card" style="border-left:4px solid #6366f1;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Total Tenants</div>
      <div style="font-size:1.8rem;font-weight:800;color:#fff;margin-top:6px;"><?= $totalTenants ?></div>
      <div style="font-size:0.75rem;color:#818cf8;margin-top:4px;">Across all tiers</div>
    </div>
    <div class="card" style="border-left:4px solid #10b981;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Active &amp; Live</div>
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;margin-top:6px;"><?= $activeTenants ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Serving live traffic</div>
    </div>
    <div class="card" style="border-left:4px solid #f59e0b;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Overdue Subscriptions</div>
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:6px;"><?= $overdueTenants ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Payment pending &gt; 30d</div>
    </div>
    <div class="card" style="border-left:4px solid #06b6d4;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">MySQL Full-Stack</div>
      <div style="font-size:1.8rem;font-weight:800;color:#22d3ee;margin-top:6px;"><?= $dbTenants ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">With database engine</div>
    </div>
  </div>

  <!-- Filters & Search -->
  <div class="card" style="margin-bottom:20px;padding:16px 20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      
      <!-- Filter Tabs -->
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php
        $tabs = [
            'all'       => 'All Tenants (' . $totalTenants . ')',
            'active'    => 'Active (' . $activeTenants . ')',
            'overdue'   => 'Overdue (' . $overdueTenants . ')',
            'suspended' => 'Suspended (' . $suspendedTenants . ')',
            'database'  => 'MySQL DB (' . $dbTenants . ')',
            'static'    => 'Static Only',
        ];
        foreach ($tabs as $key => $lbl) {
            $isCur = ($filter === $key);
            $bg = $isCur ? 'background:#6366f1;color:#fff;' : 'background:#0d1117;color:var(--muted);border:1px solid var(--border);';
            echo '<a href="?filter=' . $key . '&search=' . urlencode($search) . '" style="padding:6px 12px;border-radius:8px;font-size:0.78rem;font-weight:700;text-decoration:none;' . $bg . '">' . $lbl . '</a>';
        }
        ?>
      </div>

      <!-- Search Box -->
      <form method="GET" style="display:flex;gap:8px;max-width:320px;width:100%;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="search" placeholder="Search tenant, slug, email..." value="<?= htmlspecialchars($search) ?>" style="padding:7px 12px;font-size:0.82rem;">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search): ?>
          <a href="?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-ghost btn-sm">Clear</a>
        <?php endif; ?>
      </form>

    </div>
  </div>

  <!-- Tenants Table -->
  <div class="card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Tenant / Customer</th>
            <th>Site &amp; Subdomain</th>
            <th>Tier &amp; Mode</th>
            <th>Billing Status</th>
            <th>Next Due Date</th>
            <th>Health</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filtered)): ?>
            <tr>
              <td colspan="7" style="text-align:center;padding:48px;color:var(--muted);">
                <div style="font-size:2.5rem;margin-bottom:8px;">🔍</div>
                No tenants matching the current filter criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($filtered as $t): 
              $subStatus = subscription_status($t);
              $isActive  = ($t['site_active'] ?? true);
              $slug      = $t['slug'] ?? '';
              $oid       = $t['order_id'] ?? '';
              $liveUrl   = $t['live_url'] ?? ('../published/' . $slug . '/');
              $adminUrl  = $t['admin_url'] ?? '';
              $genMode   = $t['gen_mode'] ?? 'static';
            ?>
            <tr>
              <td>
                <div style="font-weight:700;color:#fff;"><?= htmlspecialchars($t['admin_username'] ?: ($t['site_name'] ?? 'Tenant')) ?></div>
                <div style="font-size:0.75rem;color:var(--muted);margin-top:2px;">
                  <?= htmlspecialchars($t['admin_email'] ?: ($t['client_email'] ?? 'No email')) ?>
                </div>
                <div style="font-size:0.7rem;color:#818cf8;font-family:'Fira Code',monospace;margin-top:2px;">
                  <?= htmlspecialchars($oid) ?>
                </div>
              </td>
              <td>
                <div style="font-weight:700;color:#e2e8f0;"><?= htmlspecialchars($t['site_name'] ?? 'Untitled') ?></div>
                <a href="<?= htmlspecialchars($liveUrl) ?>" target="_blank" style="font-size:0.75rem;color:#22d3ee;text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-top:2px;" title="Visit Live Site">
                  <span>/published/<?= htmlspecialchars($slug) ?>/</span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
                </a>
              </td>
              <td>
                <div style="margin-bottom:4px;"><?= package_badge($t['package'] ?? 'pro') ?></div>
                <div style="font-size:0.72rem;color:var(--muted);">
                  <?php if ($genMode === 'database'): ?>
                    <span style="color:#22d3ee;font-weight:600;">🗄️ MySQL Full-Stack</span>
                  <?php elseif ($genMode === 'admin'): ?>
                    <span style="color:#a5b4fc;font-weight:600;">🛠️ Admin + JSON</span>
                  <?php else: ?>
                    <span style="color:#94a3b8;">📄 Static HTML</span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <?= status_badge($subStatus) ?>
                <div style="font-size:0.72rem;color:var(--muted);margin-top:3px;">
                  $<?= number_format((float)($t['amount'] ?? 19.0), 2) ?> USD / mo
                </div>
              </td>
              <td>
                <div style="font-size:0.82rem;font-weight:600;color:#e2e8f0;"><?= next_due_date($t) ?></div>
                <?php $days = days_overdue($t); ?>
                <?php if ($days > 0): ?>
                  <div style="font-size:0.7rem;color:#ef4444;font-weight:700;"><?= $days ?> days overdue!</div>
                <?php else: ?>
                  <div style="font-size:0.7rem;color:#10b981;">Up to date</div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isActive): ?>
                  <span style="display:inline-flex;align-items:center;gap:4px;color:#10b981;font-size:0.78rem;font-weight:700;">
                    <span style="width:7px;height:7px;border-radius:50%;background:#10b981;"></span> Live
                  </span>
                <?php else: ?>
                  <span style="display:inline-flex;align-items:center;gap:4px;color:#ef4444;font-size:0.78rem;font-weight:700;">
                    <span style="width:7px;height:7px;border-radius:50%;background:#ef4444;"></span> Suspended
                  </span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <div style="display:inline-flex;gap:4px;align-items:center;">
                  
                  <!-- Customer Details Link -->
                  <a href="customer-view.php?order_id=<?= urlencode($oid) ?>" class="btn btn-ghost btn-sm" title="View Full Tenant Profile">
                    👤 Profile
                  </a>

                  <!-- Admin Login Link -->
                  <?php if (!empty($adminUrl)): 
                    $directAdmin = $adminUrl . (strpos($adminUrl, '?') !== false ? '&' : '?') . 'autologin=1';
                  ?>
                    <a href="<?= htmlspecialchars($directAdmin) ?>" target="_blank" class="btn btn-ghost btn-sm" style="color:#a5b4fc;border-color:rgba(99,102,241,0.3);" title="Open Customer Admin Panel (1-Click Login)">
                      🔐 Admin
                    </a>
                  <?php endif; ?>

                  <!-- Suspend / Activate Toggle -->
                  <?php if (has_permission('site_toggle') || has_permission('*')): ?>
                    <?php if ($isActive): ?>
                      <a href="?toggle_slug=<?= urlencode($slug) ?>&set_active=0&filter=<?= urlencode($filter) ?>" 
                         onclick="return confirm('Suspend website for <?= htmlspecialchars($t['site_name']) ?>? Visitors will see the suspension notice.')" 
                         class="btn btn-danger btn-sm" title="Suspend website">
                        🚫 Suspend
                      </a>
                    <?php else: ?>
                      <a href="?toggle_slug=<?= urlencode($slug) ?>&set_active=1&filter=<?= urlencode($filter) ?>" 
                         onclick="return confirm('Restore website for <?= htmlspecialchars($t['site_name']) ?>?')" 
                         class="btn btn-success btn-sm" title="Activate website">
                        ✓ Activate
                      </a>
                    <?php endif; ?>
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
</main>
</div></body></html>
