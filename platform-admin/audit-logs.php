<?php
/**
 * platform-admin/audit-logs.php
 * Feature 7: Enterprise Audit Trail & Activity Logging
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('audit_logs');

$logs   = get_audit_logs(500);
$action = $_GET['action'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// ─── ACTION: Export Audit Log to CSV ────────────────────────
if ($action === 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Timestamp', 'Admin User', 'Admin Role', 'Action', 'Target Order/Slug', 'Details', 'IP Address']);
    foreach ($logs as $l) {
        fputcsv($out, [
            $l['id'] ?? '',
            $l['timestamp'] ?? '',
            $l['admin_user'] ?? '',
            $l['admin_role'] ?? '',
            $l['action'] ?? '',
            $l['order_id'] ?? '',
            $l['detail'] ?? '',
            $l['ip'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Filter logs
$filtered = array_filter($logs, function($l) use ($filter, $search) {
    $act = strtolower($l['action'] ?? '');

    if ($filter === 'toggles' && !in_array($act, ['site_activated', 'site_deactivated'], true)) return false;
    if ($filter === 'billing' && !in_array($act, ['payment_received', 'refund_issued', 'plan_updated'], true)) return false;
    if ($filter === 'deploy' && !in_array($act, ['site_rollback', 'republish_site'], true)) return false;
    if ($filter === 'notif' && !in_array($act, ['notification_sent', 'ssl_notice_sent'], true)) return false;
    if ($filter === 'auth' && !in_array($act, ['admin_login', 'admin_created'], true)) return false;

    if ($search !== '') {
        $needle = strtolower($search);
        $haystack = strtolower(
            ($l['admin_user'] ?? '') . ' ' .
            ($l['admin_role'] ?? '') . ' ' .
            ($l['action'] ?? '') . ' ' .
            ($l['order_id'] ?? '') . ' ' .
            ($l['detail'] ?? '')
        );
        if (strpos($haystack, $needle) === false) return false;
    }

    return true;
});

render_head('Audit Trail & Activity Logs');
render_sidebar('audit');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>📋 Audit Trail &amp; Activity Logs</h1>
      <p>Immutable record of every administrative event, site suspension, refund, deployment, and security action.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="?action=export_csv" class="btn btn-ghost">📥 Export CSV</a>
      <a href="team.php" class="btn btn-primary">👥 Admin Team</a>
    </div>
  </div>

  <!-- KPI Counts -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="card" style="border-left:4px solid #6366f1;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Total Logged Events</div>
      <div style="font-size:1.8rem;font-weight:800;color:#fff;margin-top:6px;"><?= count($logs) ?></div>
      <div style="font-size:0.75rem;color:#818cf8;margin-top:4px;">Audit trail retention</div>
    </div>
    <div class="card" style="border-left:4px solid #ef4444;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Policy Suspensions</div>
      <div style="font-size:1.8rem;font-weight:800;color:#ef4444;margin-top:6px;">
        <?= count(array_filter($logs, fn($l) => ($l['action'] ?? '') === 'site_deactivated')) ?>
      </div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Deactivation actions</div>
    </div>
    <div class="card" style="border-left:4px solid #10b981;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Site Activations</div>
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;margin-top:6px;">
        <?= count(array_filter($logs, fn($l) => ($l['action'] ?? '') === 'site_activated')) ?>
      </div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Restorations</div>
    </div>
    <div class="card" style="border-left:4px solid #f59e0b;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Financial Actions</div>
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:6px;">
        <?= count(array_filter($logs, fn($l) => in_array($l['action'] ?? '', ['refund_issued', 'payment_received'], true))) ?>
      </div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Payments &amp; refunds</div>
    </div>
  </div>

  <!-- Filters & Search -->
  <div class="card" style="margin-bottom:20px;padding:14px 20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      
      <!-- Category Tabs -->
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-ghost' ?>">All Events</a>
        <a href="?filter=toggles&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'toggles' ? 'btn-primary' : 'btn-ghost' ?>">Site Toggles</a>
        <a href="?filter=billing&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'billing' ? 'btn-primary' : 'btn-ghost' ?>">Billing &amp; Refunds</a>
        <a href="?filter=deploy&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'deploy' ? 'btn-primary' : 'btn-ghost' ?>">Rollback &amp; Builds</a>
        <a href="?filter=notif&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'notif' ? 'btn-primary' : 'btn-ghost' ?>">Notifications</a>
        <a href="?filter=auth&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter === 'auth' ? 'btn-primary' : 'btn-ghost' ?>">Auth Logins</a>
      </div>

      <!-- Search -->
      <form method="GET" style="display:flex;gap:8px;max-width:320px;width:100%;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="search" placeholder="Filter by user, action, detail..." value="<?= htmlspecialchars($search) ?>" style="padding:7px 12px;font-size:0.82rem;">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search): ?>
          <a href="?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-ghost btn-sm">Clear</a>
        <?php endif; ?>
      </form>

    </div>
  </div>

  <!-- Audit Table -->
  <div class="card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Admin Operator</th>
            <th>Action Performed</th>
            <th>Target Entity</th>
            <th>Details &amp; Payload</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filtered)): ?>
            <tr><td colspan="6" style="text-align:center;padding:48px;color:var(--muted);">No audit log events match this filter.</td></tr>
          <?php else: ?>
            <?php foreach ($filtered as $l): 
              $act = $l['action'] ?? '';
              $badgeBg = 'rgba(255,255,255,0.06)';
              $badgeCol = '#cbd5e1';
              if (strpos($act, 'activated') !== false || strpos($act, 'received') !== false || strpos($act, 'renew') !== false) {
                  $badgeBg = 'rgba(16,185,129,0.15)'; $badgeCol = '#10b981';
              } elseif (strpos($act, 'deactivated') !== false || strpos($act, 'refund') !== false) {
                  $badgeBg = 'rgba(239,68,68,0.15)'; $badgeCol = '#ef4444';
              } elseif (strpos($act, 'rollback') !== false || strpos($act, 'warn') !== false) {
                  $badgeBg = 'rgba(245,158,11,0.15)'; $badgeCol = '#f59e0b';
              } elseif (strpos($act, 'login') !== false || strpos($act, 'admin') !== false) {
                  $badgeBg = 'rgba(99,102,241,0.15)'; $badgeCol = '#818cf8';
              }
            ?>
            <tr>
              <td style="font-size:0.78rem;color:var(--muted);white-space:nowrap;">
                <?= htmlspecialchars($l['timestamp'] ?? '') ?>
              </td>
              <td>
                <div style="font-weight:700;color:#fff;"><?= htmlspecialchars($l['admin_user'] ?? 'system') ?></div>
                <div style="margin-top:2px;"><?= role_badge($l['admin_role'] ?? ROLE_SUPERADMIN) ?></div>
              </td>
              <td>
                <span style="font-size:0.75rem;font-weight:700;padding:3px 8px;border-radius:6px;background:<?= $badgeBg ?>;color:<?= $badgeCol ?>;font-family:'Fira Code',monospace;">
                  <?= htmlspecialchars($act) ?>
                </span>
              </td>
              <td>
                <span class="code-badge"><?= htmlspecialchars($l['order_id'] ?? '—') ?></span>
              </td>
              <td style="font-size:0.84rem;color:#cbd5e1;line-height:1.5;">
                <?= htmlspecialchars($l['detail'] ?? '—') ?>
              </td>
              <td style="font-size:0.75rem;color:var(--muted);font-family:'Fira Code',monospace;">
                <?= htmlspecialchars($l['ip'] ?? '127.0.0.1') ?>
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
