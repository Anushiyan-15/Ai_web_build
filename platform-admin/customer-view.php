<?php
/**
 * Platform Admin - Customer Detail View (customer-view.php)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/platform-admin.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// ── Load Order ────────────────────────────────
$order_id = trim($_GET['order_id'] ?? '');
if (!$order_id) {
    header('Location: customers.php');
    exit;
}

$order = load_order($order_id);
if (!$order) {
    header('Location: customers.php');
    exit;
}

$status        = subscription_status($order);
$notifications = load_notifications($order_id);
$due_date      = next_due_date($order);
$days_od       = days_overdue($order);

// ── Handle POST (send notification) ───────────
$msg_success = '';
$msg_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notif'])) {
    $notif_type    = in_array($_POST['notif_type'] ?? '', ['payment_reminder','warning','info'])
                     ? $_POST['notif_type'] : 'info';
    $notif_message = trim($_POST['notif_message'] ?? '');

    if ($notif_message === '') {
        $msg_error = 'Message cannot be empty.';
    } else {
        // Save notification
        $saved = save_notification($order_id, [
            'type'    => $notif_type,
            'message' => $notif_message,
            'sent_by' => $_SESSION['platform_admin_user'] ?? 'admin',
        ]);

        // Send via background queue (PHPMailer as WebCraft AI on flush)
        $email       = $order['admin_email'] ?? '';
        $email_sent  = false;
        $email_error = '';
        $subject = match($notif_type) {
            'payment_reminder' => 'Payment Reminder — WebCraft AI',
            'warning'          => 'Important Warning — WebCraft AI',
            default            => 'Notification — WebCraft AI',
        };
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            require_once dirname(__DIR__) . '/includes/Mailer.php';
            require_once dirname(__DIR__) . '/includes/MailQueue.php';
            $sendRes = queueNotificationEmail($order_id, $email, $subject, $notif_message, $notif_type, $order, $_SESSION['platform_admin_user'] ?? 'admin');
            $email_sent = !empty($sendRes['success']);
            $email_error = $sendRes['error'] ?? '';
        }

        if ($saved && $email_sent) {
            $msg_success = 'Message saved and email queued for background delivery to ' . $email . '.';
        } elseif ($saved) {
            $msg_success = 'Message saved to portal' . ($email_error ? ' but queue failed: ' . $email_error : ' (no valid customer email).') . '';
        } else {
            $msg_error = 'Could not save notification. Check storage/notifications/ write permissions.';
        }
        // Reload notifications
        $notifications = load_notifications($order_id);
    }
}

render_head('Customer: ' . ($order['site_name'] ?? $order_id));
render_sidebar('customers');
?>
<div class="main">

  <!-- Page Header -->
  <div style="display:flex;align-items:center;gap:16px;margin-bottom:28px;">
    <a href="customers.php" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:9px;border:1px solid var(--border);color:var(--muted);text-decoration:none;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div style="flex:1;">
      <div style="display:flex;align-items:center;gap:12px;">
        <h1 style="font-size:1.4rem;font-weight:800;color:#fff;"><?= htmlspecialchars($order['site_name'] ?? $order_id) ?></h1>
        <?= status_badge($status) ?>
        <?= package_badge($order['package'] ?? 'starter') ?>
      </div>
      <p style="color:var(--muted);font-size:.85rem;margin-top:4px;">
        Order <?= htmlspecialchars($order_id) ?> &nbsp;·&nbsp; <?= htmlspecialchars($order['admin_email'] ?? '') ?>
      </p>
    </div>
    <div id="siteActionWrap" style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="send-email.php?to=<?= urlencode($order['admin_email'] ?? '') ?>" class="btn btn-ghost" title="Send email to this customer">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Email User
      </a>
      <?php if ($status === 'deactivated'): ?>
      <button onclick="toggleSite('<?= htmlspecialchars($order_id) ?>', 'activate')" class="btn btn-success">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Activate Site
      </button>
      <?php else: ?>
      <button onclick="toggleSite('<?= htmlspecialchars($order_id) ?>', 'deactivate')" class="btn btn-danger">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        Deactivate Site
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($msg_success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($msg_success) ?></div>
  <?php endif; ?>
  <?php if ($msg_error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($msg_error) ?></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

    <!-- Customer Info -->
    <div class="card">
      <h3 style="font-size:.95rem;font-weight:700;color:#fff;margin-bottom:18px;display:flex;align-items:center;gap:8px;">
        <svg width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Customer Information
      </h3>
      <div style="display:grid;gap:14px;">
        <?php
        $acctLabel = 'Guest (no signup yet)';
        try {
            if (file_exists(dirname(__DIR__) . '/includes/db.php')) {
                require_once dirname(__DIR__) . '/includes/db.php';
                if (function_exists('findCustomerByEmail') && !empty($order['admin_email'])) {
                    $acct = findCustomerByEmail($order['admin_email']);
                    if ($acct) $acctLabel = 'Registered (' . ($acct['source'] ?? 'account') . ')'
                        . (!empty($acct['last_login_at']) ? ' · last login ' . date('M d, Y', strtotime($acct['last_login_at'])) : ' · never logged in');
                }
            }
        } catch (Throwable $e) {}
        $info_rows = [
            ['Email',       $order['admin_email']    ?? '—'],
            ['Account',     $acctLabel],
            ['Username',    $order['admin_username'] ?? '—'],
            ['Password',    $order['admin_password_plain'] ?? '—'],
            ['Site Name',   $order['site_name']      ?? '—'],
            ['Slug',        '/' . ($order['slug']    ?? '—')],
            ['Package',     ucfirst($order['package'] ?? '—')],
            ['Amount Paid', '$' . number_format((float)($order['amount'] ?? 0), 2)],
            ['Published At',$order['published_at']   ?? '—'],
            ['Created At',  $order['created_at']     ?? '—'],
        ];
        foreach ($info_rows as [$label, $val]):
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(30,41,59,.5);">
          <span style="font-size:.8rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;"><?= $label ?></span>
          <span style="font-size:.875rem;color:#e2e8f0;font-weight:500;"><?= htmlspecialchars($val) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Subscription & Site Links -->
    <div style="display:flex;flex-direction:column;gap:20px;">

      <!-- Subscription Status -->
      <div class="card">
        <h3 style="font-size:.95rem;font-weight:700;color:#fff;margin-bottom:18px;display:flex;align-items:center;gap:8px;">
          <svg width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Subscription Status
        </h3>
        <div style="display:flex;align-items:center;gap:16px;padding:16px;border-radius:12px;background:rgba(255,255,255,.03);border:1px solid var(--border);">
          <div style="flex:1;">
            <div style="font-size:.8rem;color:var(--muted);margin-bottom:4px;">Current Status</div>
            <?= status_badge($status) ?>
          </div>
          <div style="flex:1;text-align:right;">
            <div style="font-size:.8rem;color:var(--muted);margin-bottom:4px;">Next Payment Due</div>
            <div style="font-weight:700;color:<?= $status === 'overdue' ? '#ef4444' : '#e2e8f0' ?>;"><?= htmlspecialchars($due_date) ?></div>
            <?php if ($days_od > 0): ?>
            <div style="font-size:.75rem;color:#ef4444;margin-top:2px;"><?= $days_od ?> day<?= $days_od > 1 ? 's' : '' ?> overdue</div>
            <?php endif; ?>
          </div>
        </div>
        <div style="margin-top:14px;padding:12px;border-radius:10px;background:rgba(99,102,241,.05);border:1px solid rgba(99,102,241,.15);font-size:.8rem;color:var(--muted);">
          ℹ️ Subscription renews every 30 days from the published date. After deactivation, the site shows a suspension page until reactivated.
        </div>
      </div>

      <!-- Site Links -->
      <div class="card">
        <h3 style="font-size:.95rem;font-weight:700;color:#fff;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
          <svg width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          Site Links
        </h3>
        <div style="display:grid;gap:10px;">
          <div>
            <div style="font-size:.75rem;color:var(--muted);margin-bottom:5px;font-weight:600;text-transform:uppercase;">Live URL</div>
            <a href="<?= htmlspecialchars($order['live_url'] ?? '#') ?>" target="_blank"
               style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:9px;background:#0d1117;border:1px solid var(--border);color:#6366f1;text-decoration:none;font-size:.85rem;word-break:break-all;"
               onmouseover="this.style.borderColor='#6366f1'" onmouseout="this.style.borderColor='var(--border)'">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              <?= htmlspecialchars($order['live_url'] ?? 'N/A') ?>
            </a>
          </div>
          <div>
            <div style="font-size:.75rem;color:var(--muted);margin-bottom:5px;font-weight:600;text-transform:uppercase;">Admin URL (1-Click Login)</div>
            <?php 
            $adminDirect = !empty($order['admin_url']) ? ($order['admin_url'] . (strpos($order['admin_url'], '?') !== false ? '&' : '?') . 'autologin=1') : '#';
            ?>
            <a href="<?= htmlspecialchars($adminDirect) ?>" target="_blank"
               style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:9px;background:#0d1117;border:1px solid var(--border);color:#818cf8;text-decoration:none;font-size:.85rem;word-break:break-all;"
               onmouseover="this.style.borderColor='#6366f1'" onmouseout="this.style.borderColor='var(--border)'">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              <?= htmlspecialchars($order['admin_url'] ?? 'N/A') ?> <span style="font-size:.75rem;background:#1e1b4b;padding:2px 8px;border-radius:6px;color:#a5b4fc;margin-left:auto;">⚡ Auto-Login</span>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Send Notification -->
  <div class="card" style="margin-bottom:20px;">
    <h3 style="font-size:.95rem;font-weight:700;color:#fff;margin-bottom:18px;display:flex;align-items:center;gap:8px;">
      <svg width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      Send Notification to Customer
    </h3>
    <form method="POST" data-wcl="Sending notification…" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:end;">
      <div class="form-group" style="margin-bottom:0;">
        <label>Notification Type</label>
        <select name="notif_type">
          <option value="payment_reminder">💳 Payment Reminder</option>
          <option value="warning">⚠️ Warning</option>
          <option value="info">ℹ️ Info</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label>Quick Templates</label>
        <select onchange="applyTemplate(this.value)" style="cursor:pointer;">
          <option value="">— Pick a template —</option>
          <option value="Your subscription for <?= htmlspecialchars(addslashes($order['site_name'] ?? '')) ?> is due for renewal. Please make a payment to keep your site live.">Payment Due Reminder</option>
          <option value="Your website <?= htmlspecialchars(addslashes($order['site_name'] ?? '')) ?> will be suspended in 3 days due to non-payment. Please contact us immediately.">Suspension Warning</option>
          <option value="Your website <?= htmlspecialchars(addslashes($order['site_name'] ?? '')) ?> has been reactivated. Thank you for your payment!">Reactivation Confirmation</option>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1;margin-bottom:0;">
        <label>Message</label>
        <textarea name="notif_message" id="notifMsg" rows="4"
                  placeholder="Type your message to the customer…"><?= htmlspecialchars($_POST['notif_message'] ?? '') ?></textarea>
      </div>
      <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
        <button type="submit" name="send_notif" value="1" class="btn btn-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Send Notification
        </button>
      </div>
    </form>
  </div>

  <!-- Notification History -->
  <div class="card">
    <h3 style="font-size:.95rem;font-weight:700;color:#fff;margin-bottom:18px;display:flex;align-items:center;gap:8px;">
      <svg width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      Notification History (<?= count($notifications) ?>)
    </h3>

    <?php if (empty($notifications)): ?>
    <div style="text-align:center;padding:40px;color:var(--muted);">
      <div style="font-size:2.5rem;margin-bottom:10px;">📭</div>
      No notifications sent yet.
    </div>
    <?php else: ?>
    <div style="display:grid;gap:12px;">
      <?php
      $type_styles = [
          'payment_reminder' => ['icon' => '💳', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.08)', 'border' => 'rgba(245,158,11,.2)', 'label' => 'Payment Reminder'],
          'warning'          => ['icon' => '⚠️', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.08)',  'border' => 'rgba(239,68,68,.2)',  'label' => 'Warning'],
          'info'             => ['icon' => 'ℹ️', 'color' => '#6366f1', 'bg' => 'rgba(99,102,241,.08)','border' => 'rgba(99,102,241,.2)', 'label' => 'Info'],
      ];
      foreach ($notifications as $n):
          $ts = $type_styles[$n['type'] ?? 'info'] ?? $type_styles['info'];
      ?>
      <div style="padding:14px 16px;border-radius:10px;background:<?= $ts['bg'] ?>;border:1px solid <?= $ts['border'] ?>;display:flex;gap:14px;align-items:flex-start;">
        <span style="font-size:1.4rem;flex-shrink:0;"><?= $ts['icon'] ?></span>
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
            <span style="font-size:.75rem;font-weight:700;color:<?= $ts['color'] ?>;text-transform:uppercase;letter-spacing:.05em;"><?= $ts['label'] ?></span>
            <span style="font-size:.73rem;color:var(--muted);"><?= htmlspecialchars($n['sent_at'] ?? '') ?></span>
            <?php if (!empty($n['sent_by'])): ?>
            <span style="font-size:.73rem;color:#475569;">by <?= htmlspecialchars($n['sent_by']) ?></span>
            <?php endif; ?>
          </div>
          <div style="font-size:.875rem;color:#e2e8f0;line-height:1.6;"><?= nl2br(htmlspecialchars($n['message'] ?? '')) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /.main -->

<!-- Toast -->
<div id="toast" style="position:fixed;bottom:28px;right:28px;padding:14px 20px;border-radius:12px;font-size:.875rem;font-weight:600;display:none;z-index:9999;min-width:260px;box-shadow:0 10px 40px rgba(0,0,0,.5);align-items:center;gap:10px;">
  <span id="toastMsg"></span>
</div>

</div><!-- /.layout -->
</body>
<script>
function applyTemplate(val) {
  if (val) document.getElementById('notifMsg').value = val;
}

function showToast(msg, type) {
  const t  = document.getElementById('toast');
  const tm = document.getElementById('toastMsg');
  tm.textContent = msg;
  const colors = {
    success: { bg: 'rgba(16,185,129,.95)',  color: '#fff' },
    error:   { bg: 'rgba(239,68,68,.95)',   color: '#fff' },
  };
  const c = colors[type] || colors.success;
  t.style.background = c.bg;
  t.style.color      = c.color;
  t.style.display    = 'flex';
  clearTimeout(window._tt);
  window._tt = setTimeout(() => { t.style.display = 'none'; }, 4500);
}

function toggleSite(orderId, action) {
  const label = action === 'activate' ? 'activate' : 'deactivate';
  if (!confirm('Are you sure you want to ' + label + ' this site?\n\n' +
    (action === 'deactivate' ? 'The live site will show a suspension notice.' : 'The original website will be restored.')))
    return;

  const btn = document.querySelector('#siteActionWrap button');
  btn.disabled = true;
  btn.innerHTML = '<span class="wcl-bblocks"><i></i><i></i><i></i></span>Working…';
  const doneLoad = (window.paAjaxLoad ? paAjaxLoad('Updating site…', 'Syncing site status', 'db') : () => {});

  fetch('api.php?action=toggle_site&order_id=' + encodeURIComponent(orderId) + '&status=' + action)
    .then(r => r.json())
    .then(d => {
      doneLoad();
      showToast(d.message || 'Done!', d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 1400);
      else btn.disabled = false;
    })
    .catch(() => { doneLoad(); showToast('Network error.', 'error'); btn.disabled = false; });
}
</script>
</html>
