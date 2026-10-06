<?php
/**
 * Platform Admin - Customer List (customers.php)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/platform-admin.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// ── Load & filter ─────────────────────────────
$all_orders  = load_all_orders();
$all_customers = load_all_customers();
$filter      = $_GET['filter'] ?? 'all';
$search      = trim($_GET['q'] ?? '');

// Flash message from redirect
$flash_type = $_SESSION['flash_type'] ?? '';
$flash_msg  = $_SESSION['flash_msg']  ?? '';
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

$filtered = [];
foreach ($all_orders as $o) {
    $status = subscription_status($o);

    // Filter tab
    if ($filter !== 'all' && $status !== $filter) continue;

    // Search
    if ($search !== '') {
        $haystack = strtolower(
            ($o['admin_email']  ?? '') . ' ' .
            ($o['site_name']    ?? '') . ' ' .
            ($o['slug']         ?? '') . ' ' .
            ($o['order_id']     ?? '') . ' ' .
            ($o['package']      ?? '')
        );
        if (strpos($haystack, strtolower($search)) === false) continue;
    }

    $o['_status'] = $status;
    $filtered[]   = $o;
}

// Tab counts
$tab_counts = ['all' => count($all_orders), 'active' => 0, 'overdue' => 0, 'deactivated' => 0];
foreach ($all_orders as $o) {
    $s = subscription_status($o);
    $tab_counts[$s]++;
}

render_head('Customers');
render_sidebar('customers');
?>
<div class="main">

  <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <div>
      <h1>Customer Management</h1>
      <p>All published sites, subscription statuses, and quick actions</p>
    </div>
    <a href="customers.php?filter=overdue" class="btn btn-warning">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      Bulk Reminder (<?= $tab_counts['overdue'] ?> Overdue)
    </a>
  </div>

  <?php if ($flash_msg): ?>
  <div class="alert alert-<?= $flash_type === 'success' ? 'success' : ($flash_type === 'warning' ? 'warning' : 'error') ?>" style="margin-bottom:20px;">
    <?= htmlspecialchars($flash_msg) ?>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
      <div>
        <h2 style="font-size:1.05rem;color:#fff;">Registered Accounts (<?= count($all_customers) ?>)</h2>
        <p style="color:var(--muted);font-size:.82rem;">Signup users + guest publishers. Source: db = Supabase/MySQL, local = file fallback, guest = published without signup.</p>
      </div>
    </div>

    <?php if (empty($all_customers)): ?>
    <div style="text-align:center;padding:30px;color:var(--muted);font-size:.875rem;">No customer accounts yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Account</th>
            <th>Source</th>
            <th>Projects</th>
            <th>Signed Up</th>
            <th>Last Login</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all_customers as $c): ?>
          <tr>
            <td>
              <div style="font-weight:600;color:#fff;"><?= htmlspecialchars($c['email'] ?? '—') ?></div>
              <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($c['name'] ?? '') ?><?= !empty($c['phone']) ? ' · ' . htmlspecialchars($c['phone']) : '' ?></div>
            </td>
            <td style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($c['source'] ?? '—') ?></td>
            <td style="font-weight:700;color:#818cf8;"><?= (int)($c['projects'] ?? 0) ?></td>
            <td style="color:var(--muted);font-size:.82rem;"><?= !empty($c['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($c['created_at']))) : '—' ?></td>
            <td style="color:var(--muted);font-size:.82rem;"><?= !empty($c['last_login_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($c['last_login_at']))) : '—' ?></td>
            <td>
              <div style="display:flex;gap:6px;justify-content:flex-end;">
                <a href="send-email.php?to=<?= urlencode($c['email'] ?? '') ?>" class="btn btn-ghost btn-sm" title="Send email to this user">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                  Email
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">

    <!-- Tabs + Search -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:20px;">
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php
        $tabs = [
            'all'         => ['label' => 'All',         'color' => '#6366f1'],
            'active'      => ['label' => 'Active',      'color' => '#10b981'],
            'overdue'     => ['label' => 'Overdue',     'color' => '#f59e0b'],
            'deactivated' => ['label' => 'Deactivated', 'color' => '#ef4444'],
        ];
        foreach ($tabs as $key => $tab):
            $isActive = ($filter === $key);
            $cnt      = $tab_counts[$key];
            $style    = $isActive
                ? "display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;font-family:inherit;font-size:.83rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;background:rgba(99,102,241,.2);color:#818cf8;"
                : "display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;font-family:inherit;font-size:.83rem;font-weight:600;cursor:pointer;border:1px solid var(--border);text-decoration:none;background:transparent;color:var(--muted);";
            $href = '?filter=' . $key . ($search ? '&q=' . urlencode($search) : '');
        ?>
        <a href="<?= $href ?>" style="<?= $style ?>">
          <?= $tab['label'] ?>
          <span style="padding:1px 7px;border-radius:99px;font-size:.72rem;background:rgba(255,255,255,.08);color:<?= $isActive ? '#818cf8' : 'var(--muted)' ?>;"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Search -->
      <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div style="position:relative;">
          <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none;" width="16" height="16" fill="none" stroke="#64748b" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                 placeholder="Search email, site, slug…"
                 style="padding-left:36px;width:260px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search): ?>
        <a href="?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-ghost btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <?php if (empty($filtered)): ?>
    <div style="text-align:center;padding:60px 20px;">
      <div style="font-size:3rem;margin-bottom:12px;">🔍</div>
      <div style="font-weight:600;color:#fff;margin-bottom:6px;">No customers found</div>
      <div style="color:var(--muted);font-size:.875rem;">Try adjusting your filter or search term.</div>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Customer</th>
            <th>Site</th>
            <th>Package</th>
            <th>Amount</th>
            <th>Published</th>
            <th>Next Due</th>
            <th>Status</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filtered as $o): ?>
          <?php
            $status   = $o['_status'];
            $due_date = next_due_date($o);
            $overdue  = days_overdue($o);
            $oid      = urlencode($o['order_id'] ?? '');
          ?>
          <tr>
            <td>
              <div style="font-weight:600;color:#fff;margin-bottom:2px;"><?= htmlspecialchars($o['admin_email'] ?? '—') ?></div>
              <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($o['admin_username'] ?? '') ?></div>
            </td>
            <td>
              <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($o['site_name'] ?? '—') ?></div>
              <div style="font-size:.76rem;color:#6366f1;font-family:monospace;">/<?= htmlspecialchars($o['slug'] ?? '') ?></div>
            </td>
            <td><?= package_badge($o['package'] ?? 'starter') ?></td>
            <td style="color:#10b981;font-weight:700;">$<?= number_format((float)($o['amount'] ?? 0), 2) ?></td>
            <td style="color:var(--muted);font-size:.82rem;">
              <?= htmlspecialchars($o['published_at'] ? date('M d, Y', strtotime($o['published_at'])) : '—') ?>
            </td>
            <td>
              <div style="font-size:.83rem;color:<?= $status === 'overdue' ? '#f59e0b' : 'var(--muted)' ?>;">
                <?= htmlspecialchars($due_date) ?>
              </div>
              <?php if ($status === 'overdue'): ?>
              <div style="font-size:.73rem;color:#ef4444;"><?= $overdue ?>d overdue</div>
              <?php endif; ?>
            </td>
            <td><?= status_badge($status) ?></td>
            <td>
              <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                <a href="customer-view.php?order_id=<?= $oid ?>" class="btn btn-ghost btn-sm" title="View Details">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  View
                </a>

                <?php if ($status === 'overdue' || $status === 'active'): ?>
                <button onclick="sendReminder('<?= htmlspecialchars($o['order_id'] ?? '') ?>', this)" class="btn btn-warning btn-sm" title="Send Payment Reminder">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                  Remind
                </button>
                <?php endif; ?>

                <?php if ($status === 'deactivated'): ?>
                <button onclick="toggleSite('<?= htmlspecialchars($o['order_id'] ?? '') ?>', 'activate', this)" class="btn btn-success btn-sm">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                  Activate
                </button>
                <?php else: ?>
                <button onclick="toggleSite('<?= htmlspecialchars($o['order_id'] ?? '') ?>', 'deactivate', this)" class="btn btn-danger btn-sm">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                  Deactivate
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;color:var(--muted);font-size:.8rem;text-align:right;">
      Showing <?= count($filtered) ?> of <?= count($all_orders) ?> customer<?= count($all_orders) !== 1 ? 's' : '' ?>
    </div>
    <?php endif; ?>

  </div><!-- /.card -->
</div><!-- /.main -->

<!-- Toast notification -->
<div id="toast" style="position:fixed;bottom:28px;right:28px;padding:14px 20px;border-radius:12px;font-size:.875rem;font-weight:600;display:none;z-index:9999;min-width:260px;box-shadow:0 10px 40px rgba(0,0,0,.5);align-items:center;gap:10px;">
  <span id="toastMsg"></span>
</div>

</div><!-- /.layout -->
</body>

<script>
function showToast(msg, type) {
  const t  = document.getElementById('toast');
  const tm = document.getElementById('toastMsg');
  tm.textContent = msg;
  const colors = {
    success: { bg: 'rgba(16,185,129,.9)',  color: '#fff' },
    error:   { bg: 'rgba(239,68,68,.9)',   color: '#fff' },
    warning: { bg: 'rgba(245,158,11,.9)',  color: '#fff' },
  };
  const c = colors[type] || colors.success;
  t.style.background = c.bg;
  t.style.color      = c.color;
  t.style.display    = 'flex';
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => { t.style.display = 'none'; }, 4000);
}

function sendReminder(orderId, btn) {
  btn.disabled    = true;
  btn.innerHTML = '<span class="wcl-bblocks"><i></i><i></i><i></i></span>Sending…';
  const doneLoad = (window.paAjaxLoad ? paAjaxLoad('Sending reminder…', 'Mailing the customer', 'mail') : () => {});
  fetch('api.php?action=send_reminder&order_id=' + encodeURIComponent(orderId))
    .then(r => r.json())
    .then(d => {
      doneLoad();
      showToast(d.message || 'Reminder sent!', d.success ? 'success' : 'error');
      btn.disabled    = false;
      btn.innerHTML   = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Remind';
    })
    .catch(() => { doneLoad(); showToast('Network error.', 'error'); btn.disabled = false; });
}

function toggleSite(orderId, action, btn) {
  const label = action === 'activate' ? 'activate' : 'deactivate';
  if (!confirm('Are you sure you want to ' + label + ' this site?')) return;
  btn.disabled    = true;
  btn.innerHTML = '<span class="wcl-bblocks"><i></i><i></i><i></i></span>Working…';
  const doneLoad = (window.paAjaxLoad ? paAjaxLoad('Updating site…', 'Syncing site status', 'db') : () => {});
  fetch('api.php?action=toggle_site&order_id=' + encodeURIComponent(orderId) + '&status=' + action)
    .then(r => r.json())
    .then(d => {
      doneLoad();
      showToast(d.message || 'Done!', d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 1200);
      else { btn.disabled = false; }
    })
    .catch(() => { doneLoad(); showToast('Network error.', 'error'); btn.disabled = false; });
}
</script>
</html>
