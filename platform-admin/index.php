<?php
/**
 * platform-admin/index.php
 * Unified Command Dashboard — Enterprise RBAC Edition
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/platform-admin.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$all_orders  = load_all_orders();
$total       = count($all_orders);
$active_cnt  = 0;
$deact_cnt   = 0;
$overdue_cnt = 0;
$mrr         = 0.0;
$recent      = array_slice($all_orders, 0, 8);

foreach ($all_orders as $o) {
    $s = subscription_status($o);
    $amt = (float)($o['amount'] ?? 19.0);
    if ($s === 'active') {
        $active_cnt++;
        $mrr += $amt;
    } elseif ($s === 'deactivated') {
        $deact_cnt++;
    } elseif ($s === 'overdue') {
        $overdue_cnt++;
    }
}

// Module summaries
$aiStats     = get_ai_usage_stats();
$tickets     = get_support_tickets();
$openTickets = count(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'open'));
$sslRecords  = get_domain_ssl_records();
$sslWarnings = count(array_filter($sslRecords, fn($r) => in_array($r['ssl_status'] ?? '', ['expiring_soon', 'expired'], true)));
$recentAudit = get_audit_logs(6);

$adminRole   = current_admin_role();
$roleDefs    = get_role_definitions();
$curRoleDef  = $roleDefs[$adminRole] ?? $roleDefs[ROLE_SUPERADMIN];

render_head('Enterprise Command Console');
render_sidebar('dashboard');
?>

<main class="main">
  <!-- Page Header -->
  <div class="page-header">
    <div>
      <div style="display:flex;align-items:center;gap:10px;">
        <h1>Platform Command Center</h1>
        <?= role_badge($adminRole) ?>
      </div>
      <p>Logged in as <strong style="color:#fff;"><?= htmlspecialchars($_SESSION['platform_admin_name'] ?? 'Admin') ?></strong> &nbsp;·&nbsp; <?= htmlspecialchars($curRoleDef['description']) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="tenants.php" class="btn btn-ghost">🏢 Tenants Directory</a>
      <a href="notifications.php" class="btn btn-primary">📢 Broadcast Alert</a>
    </div>
  </div>

  <!-- Primary Metric Cards -->
  <div class="grid-4" style="margin-bottom:24px;">
    <!-- Active Tenants -->
    <div class="card" style="border-left:4px solid #10b981;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Live Active Tenants</div>
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;margin-top:6px;"><?= $active_cnt ?> <span style="font-size:1rem;color:var(--muted);font-weight:500;">/ <?= $total ?></span></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Websites currently serving traffic</div>
    </div>

    <!-- Monthly Recurring Revenue -->
    <div class="card" style="border-left:4px solid #6366f1;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Monthly Recurring Revenue</div>
      <div style="font-size:1.8rem;font-weight:800;color:#fff;margin-top:6px;">$<?= number_format($mrr, 2) ?></div>
      <div style="font-size:0.75rem;color:#818cf8;margin-top:4px;">ARR: $<?= number_format($mrr * 12, 2) ?> USD</div>
    </div>

    <!-- AI Compute Spend -->
    <div class="card" style="border-left:4px solid #06b6d4;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">AI Tokens Processed</div>
      <div style="font-size:1.8rem;font-weight:800;color:#22d3ee;margin-top:6px;"><?= number_format($aiStats['total_tokens']) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Est. spend: $<?= number_format($aiStats['total_cost_usd'], 4) ?></div>
    </div>

    <!-- Overdue & At Risk -->
    <div class="card" style="border-left:4px solid #f59e0b;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Overdue Subscriptions</div>
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:6px;"><?= $overdue_cnt ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">
        <a href="billing.php" style="color:#f59e0b;text-decoration:underline;">Automate enforcement →</a>
      </div>
    </div>
  </div>

  <!-- Operational Quick-Modules Grid -->
  <div class="grid-4" style="margin-bottom:28px;">
    <!-- Deployments -->
    <a href="deployments.php" class="card" style="text-decoration:none;transition:transform .2s;display:block;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <span style="font-size:1.5rem;">🚀</span>
        <span style="font-size:0.7rem;color:#818cf8;font-weight:700;background:rgba(99,102,241,0.15);padding:2px 8px;border-radius:999px;">Rollback Ready</span>
      </div>
      <div style="font-weight:800;color:#fff;font-size:0.95rem;">Deployments &amp; Builds</div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:3px;">Inspect live code &amp; rollback</div>
    </a>

    <!-- Domain & SSL -->
    <a href="domains.php" class="card" style="text-decoration:none;transition:transform .2s;display:block;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <span style="font-size:1.5rem;">🔒</span>
        <?php if ($sslWarnings > 0): ?>
          <span style="font-size:0.7rem;color:#f59e0b;font-weight:700;background:rgba(245,158,11,0.15);padding:2px 8px;border-radius:999px;"><?= $sslWarnings ?> Warnings</span>
        <?php else: ?>
          <span style="font-size:0.7rem;color:#10b981;font-weight:700;background:rgba(16,185,129,0.15);padding:2px 8px;border-radius:999px;">All Secure</span>
        <?php endif; ?>
      </div>
      <div style="font-weight:800;color:#fff;font-size:0.95rem;">Domain &amp; SSL Tracker</div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:3px;">TLS certificate lifecycles</div>
    </a>

    <!-- Support Tickets -->
    <a href="tickets.php" class="card" style="text-decoration:none;transition:transform .2s;display:block;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <span style="font-size:1.5rem;">🎧</span>
        <?php if ($openTickets > 0): ?>
          <span style="font-size:0.7rem;color:#ef4444;font-weight:700;background:rgba(239,68,68,0.15);padding:2px 8px;border-radius:999px;"><?= $openTickets ?> Pending</span>
        <?php else: ?>
          <span style="font-size:0.7rem;color:#10b981;font-weight:700;background:rgba(16,185,129,0.15);padding:2px 8px;border-radius:999px;">Inbox Clear</span>
        <?php endif; ?>
      </div>
      <div style="font-weight:800;color:#fff;font-size:0.95rem;">Support Tickets</div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:3px;">Contact inquiries &amp; help</div>
    </a>

    <!-- Audit Logs -->
    <a href="audit-logs.php" class="card" style="text-decoration:none;transition:transform .2s;display:block;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <span style="font-size:1.5rem;">📋</span>
        <span style="font-size:0.7rem;color:#22d3ee;font-weight:700;background:rgba(6,182,212,0.15);padding:2px 8px;border-radius:999px;">Immutable</span>
      </div>
      <div style="font-weight:800;color:#fff;font-size:0.95rem;">Audit Trail Logs</div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:3px;">Who changed what and when</div>
    </a>
  </div>

  <div class="grid-3" style="margin-bottom:28px;">
    
    <!-- Recent Tenants Table (2 cols) -->
    <div class="card" style="grid-column:span 2;padding:0;overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">🏢 Recent Tenant Publications</h2>
          <div style="font-size:0.75rem;color:var(--muted);">Latest customer full-stack deployments</div>
        </div>
        <a href="tenants.php" class="btn btn-ghost btn-sm">All Tenants →</a>
      </div>

      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>Tenant / Site</th>
              <th>Tier</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Published</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent)): ?>
              <tr><td colspan="6" style="text-align:center;padding:36px;color:var(--muted);">No tenants published yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recent as $o):
                $subStatus = subscription_status($o);
                $slug = $o['slug'] ?? '';
              ?>
              <tr>
                <td>
                  <div style="font-weight:700;color:#fff;"><?= htmlspecialchars($o['site_name'] ?? 'Website') ?></div>
                  <div style="font-size:0.72rem;color:var(--muted);">/published/<?= htmlspecialchars($slug) ?>/</div>
                </td>
                <td><?= package_badge($o['package'] ?? 'starter') ?></td>
                <td style="color:#10b981;font-weight:700;">$<?= number_format((float)($o['amount'] ?? 19), 2) ?></td>
                <td><?= status_badge($subStatus) ?></td>
                <td style="font-size:0.78rem;color:var(--muted);">
                  <?= htmlspecialchars(date('M d, Y', strtotime($o['published_at'] ?? $o['created_at'] ?? 'now'))) ?>
                </td>
                <td style="text-align:right;">
                  <a href="customer-view.php?order_id=<?= urlencode($o['order_id']) ?>" class="btn btn-ghost btn-sm">Manage</a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Audit Activity Feed (1 col) -->
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">🛡️ Recent Audit Trail</h2>
          <div style="font-size:0.75rem;color:var(--muted);">Operator action log</div>
        </div>
        <a href="audit-logs.php" class="btn btn-ghost btn-sm">View All →</a>
      </div>

      <div style="padding:14px 16px;">
        <?php if (empty($recentAudit)): ?>
          <div style="text-align:center;padding:30px;color:var(--muted);font-size:0.85rem;">No audit logs recorded yet.</div>
        <?php else: ?>
          <?php foreach ($recentAudit as $al): ?>
            <div style="padding:10px 0;border-bottom:1px dashed rgba(255,255,255,0.06);font-size:0.8rem;">
              <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                <strong style="color:#818cf8;font-family:'Fira Code',monospace;font-size:0.75rem;"><?= htmlspecialchars($al['action']) ?></strong>
                <span style="font-size:0.7rem;color:var(--muted);"><?= htmlspecialchars(date('H:i:s', strtotime($al['timestamp'] ?? 'now'))) ?></span>
              </div>
              <div style="color:#cbd5e1;line-height:1.4;font-size:0.78rem;">
                <?= htmlspecialchars($al['detail']) ?>
              </div>
              <div style="font-size:0.68rem;color:var(--muted);margin-top:2px;">
                By @<?= htmlspecialchars($al['admin_user']) ?> (<?= htmlspecialchars($al['admin_role']) ?>)
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</main>
</div></body></html>
