<?php
/**
 * platform-admin/notifications.php
 * Feature 8: Centralized Notifications Hub & Broadcast Engine
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('notifications_view');

$orders  = load_all_orders();
$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$history = get_all_notifications_sent(60);

// ─── ACTION: Send Notification ──────────────────────────────
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('notifications_send') && !has_permission('*')) {
        header('Location: notifications.php?msg=Unauthorized+to+send+notifications&type=error');
        exit;
    }

    $targetType = $_POST['target_type'] ?? 'single';
    $singleOid  = $_POST['order_id'] ?? '';
    $type       = $_POST['type'] ?? 'info';
    $subject    = trim($_POST['subject'] ?? 'Notice from WebCraft Platform');
    $message    = trim($_POST['message'] ?? '');

    if (empty($message)) {
        header('Location: notifications.php?msg=Message+body+cannot+be+empty&type=error');
        exit;
    }

    $targetOrders = [];
    if ($targetType === 'single') {
        if ($singleOid) $targetOrders[] = $singleOid;
    } elseif ($targetType === 'overdue') {
        foreach ($orders as $o) {
            if (subscription_status($o) === 'overdue') $targetOrders[] = $o['order_id'];
        }
    } elseif ($targetType === 'active') {
        foreach ($orders as $o) {
            if (subscription_status($o) === 'active') $targetOrders[] = $o['order_id'];
        }
    } elseif ($targetType === 'all') {
        foreach ($orders as $o) {
            $targetOrders[] = $o['order_id'];
        }
    }

    $sentCount = 0;
    $queuedCount = 0;
    require_once dirname(__DIR__) . '/includes/Mailer.php';
    require_once dirname(__DIR__) . '/includes/MailQueue.php';
    foreach ($targetOrders as $toId) {
        $notifData = [
            'type'    => $type,
            'subject' => $subject,
            'message' => $message,
        ];
        if (save_notification($toId, $notifData)) {
            $sentCount++;
            // Queue email (background) instead of slow sync send
            $ordObj = load_order($toId);
            $cEmail = $ordObj ? ($ordObj['admin_email'] ?: ($ordObj['client_email'] ?? '')) : '';
            if ($cEmail && filter_var($cEmail, FILTER_VALIDATE_EMAIL)) {
                $qr = queueNotificationEmail($toId, $cEmail, $subject, $message, $type, $ordObj, 'broadcast');
                if (!empty($qr['success'])) $queuedCount++;
            }
        }
    }

    log_admin_action('notification_sent', ($targetType === 'single' ? $singleOid : 'bulk'), "Saved '{$subject}' to {$sentCount} recipient(s), {$queuedCount} email(s) queued [Target: {$targetType}]");
    header("Location: notifications.php?msg=Saved+{$sentCount}+notification(s),+{$queuedCount}+email(s)+queued&type=success");
    exit;
}

render_head('Notifications Hub & Broadcast');
render_sidebar('notifications');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>📢 Notification Hub &amp; Broadcast Center</h1>
      <p>Send direct payment reminders, SSL warnings, maintenance notices, and system-wide announcements.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="tenants.php" class="btn btn-ghost">🏢 Tenants Directory</a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= ($_GET['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <?php
  require_once dirname(__DIR__) . '/includes/MailQueue.php';
  $mqPending = mailQueuePending();
  ?>
  <!-- Mail Queue Status -->
  <div class="card" style="margin-bottom:20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <div style="font-size:1.6rem;">📬</div>
    <div style="flex:1;min-width:200px;">
      <div style="font-weight:800;color:#fff;">Background Mail Queue — <span id="mq-count"><?= (int)$mqPending ?></span> pending</div>
      <div style="font-size:.8rem;color:var(--muted);" id="mq-note">Emails send in the background so pages stay fast. OTP codes always send instantly.</div>
    </div>
    <button class="btn btn-primary btn-sm" id="mq-flush-btn" onclick="flushMailQueueNow()">Send Queued Now →</button>
  </div>
  <script>
  function flushMailQueueNow() {
    const btn = document.getElementById('mq-flush-btn');
    const note = document.getElementById('mq-note');
    btn.disabled = true; btn.textContent = 'Sending…';
    if (window.Loader3D) Loader3D.show('Sending queued emails…', 'Delivering in background', 'mail');
    fetch('api/flush-mail.php?max=20')
      .then(r => r.json())
      .then(d => {
        if (window.Loader3D) Loader3D.hide();
        document.getElementById('mq-count').textContent = d.pending ?? 0;
        note.textContent = 'Sent ' + (d.sent ?? 0) + ' just now' + ((d.failed ?? 0) ? ', ' + d.failed + ' failed (will retry)' : '') + '. ' + (d.pending ?? 0) + ' still pending.';
        btn.disabled = false; btn.textContent = 'Send Queued Now →';
      })
      .catch(() => { if (window.Loader3D) Loader3D.hide(); btn.disabled = false; btn.textContent = 'Send Queued Now →'; note.textContent = 'Flush request failed — queue keeps retrying automatically.'; });
  }
  </script>

  <div class="grid-2" style="margin-bottom:28px;">
    
    <!-- Dispatch Form -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">✉️ Compose Notification</h2>
        <span style="font-size:0.75rem;color:#818cf8;font-weight:600;">In-App + Email</span>
      </div>

      <form method="POST" action="notifications.php" data-wcl="Sending notifications…">
        <input type="hidden" name="action" value="send">

        <!-- Quick Template Selector -->
        <div class="form-group">
          <label>Pre-Built Templates (Click to fill)</label>
          <select id="tplSelect" onchange="applyTemplate(this.value)" style="padding:9px;">
            <option value="">-- Choose a pre-made template --</option>
            <option value="renewal">💳 Standard Monthly Renewal Reminder</option>
            <option value="overdue">⚠️ Urgent Overdue Notice (Suspension Warning)</option>
            <option value="ssl">🔒 Domain / SSL Expiry Notice</option>
            <option value="maint">🛠️ Scheduled Platform Maintenance</option>
            <option value="features">🚀 New AI Features &amp; Modules Available</option>
          </select>
        </div>

        <div class="form-group">
          <label>Recipients / Target Audience</label>
          <select name="target_type" id="targetTypeSelect" onchange="toggleSingleOrderField(this.value)" style="padding:9px;" required>
            <option value="single">Single Specific Tenant</option>
            <option value="overdue">All Overdue Tenants (Payment Pending)</option>
            <option value="active">All Active Paying Tenants</option>
            <option value="all">Broadcast to All Customers (System-Wide)</option>
          </select>
        </div>

        <!-- Single Tenant Selector -->
        <div class="form-group" id="singleOrderGroup">
          <label>Choose Specific Tenant</label>
          <select name="order_id" style="padding:9px;">
            <option value="">-- Select a tenant --</option>
            <?php foreach ($orders as $o): ?>
              <option value="<?= htmlspecialchars($o['order_id']) ?>">
                <?= htmlspecialchars($o['site_name']) ?> (<?= htmlspecialchars($o['order_id']) ?>) - <?= htmlspecialchars($o['admin_email'] ?: ($o['client_email'] ?? 'No email')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Notification Category</label>
          <select name="type" id="notifTypeInput" style="padding:9px;">
            <option value="payment_reminder">💳 Payment Reminder</option>
            <option value="warning">⚠️ Warning / Suspension Alert</option>
            <option value="info" selected>ℹ️ Information / Announcement</option>
            <option value="activation">✅ Account Status Update</option>
          </select>
        </div>

        <div class="form-group">
          <label>Subject Line</label>
          <input type="text" name="subject" id="subjectInput" placeholder="e.g. Action Required: Subscription Renewal" required>
        </div>

        <div class="form-group">
          <label>Message Content</label>
          <textarea name="message" id="messageInput" rows="5" placeholder="Enter message here. This will show on the customer's /site-manager.php Notifications tab and send to their email." required></textarea>
        </div>

        <div style="text-align:right;">
          <button type="submit" class="btn btn-primary">
            🚀 Dispatch Notification
          </button>
        </div>
      </form>
    </div>

    <!-- Quick Help & Channel Info -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      
      <div class="card" style="border-left:4px solid #6366f1;">
        <h3 style="font-size:0.95rem;font-weight:800;color:#fff;margin-bottom:10px;">💡 How Notifications Work</h3>
        <p style="font-size:0.83rem;color:var(--muted);line-height:1.6;margin-bottom:12px;">
          When you dispatch a notification:
        </p>
        <ul style="font-size:0.8rem;color:#cbd5e1;line-height:1.8;padding-left:18px;">
          <li>It instantly appears on the customer's <strong style="color:#a5b4fc">Site Manager</strong> (<code style="font-size:0.75rem;">/site-manager.php</code>) in their Notifications tab.</li>
          <li>A notification counter badge lights up on their portal.</li>
          <li>An email alert is dispatched via PHP mailer to their registered contact email address.</li>
          <li>Every notification dispatched is recorded in the platform <strong style="color:#22d3ee">Audit Trail</strong>.</li>
        </ul>
      </div>

      <div class="card">
        <h3 style="font-size:0.95rem;font-weight:800;color:#fff;margin-bottom:10px;">🎯 Quick Audiences</h3>
        <div style="font-size:0.8rem;color:var(--muted);line-height:1.6;">
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <span>Overdue Tenants:</span>
            <strong style="color:#f59e0b;"><?= count(array_filter($orders, fn($o) => subscription_status($o) === 'overdue')) ?> tenants</strong>
          </div>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <span>Active Tenants:</span>
            <strong style="color:#10b981;"><?= count(array_filter($orders, fn($o) => subscription_status($o) === 'active')) ?> tenants</strong>
          </div>
          <div style="display:flex;justify-content:space-between;padding:8px 0;">
            <span>Total Registered Base:</span>
            <strong style="color:#fff;"><?= count($orders) ?> tenants</strong>
          </div>
        </div>
      </div>

    </div>

  </div>

  <!-- Sent Notifications History -->
  <div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">📜 Recent Dispatched Notifications</h2>
      <span style="font-size:0.75rem;color:var(--muted);"><?= count($history) ?> Recorded Messages</span>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Sent Timestamp</th>
            <th>Target Tenant / Order</th>
            <th>Type</th>
            <th>Subject &amp; Body Preview</th>
            <th>Dispatched By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($history)): ?>
            <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted);">No notifications dispatched yet.</td></tr>
          <?php else: ?>
            <?php foreach ($history as $h): 
              $type = $h['type'] ?? 'info';
              $badgeCol = ($type === 'payment_reminder') ? '#f59e0b' : (($type === 'warning') ? '#ef4444' : '#6366f1');
            ?>
            <tr>
              <td style="font-size:0.78rem;color:var(--muted);white-space:nowrap;">
                <?= htmlspecialchars($h['sent_at'] ?? '') ?>
              </td>
              <td>
                <span class="code-badge"><?= htmlspecialchars($h['order_id'] ?? 'All') ?></span>
              </td>
              <td>
                <span style="font-size:0.72rem;font-weight:700;padding:3px 8px;border-radius:999px;background:rgba(255,255,255,0.08);color:<?= $badgeCol ?>;">
                  <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type))) ?>
                </span>
              </td>
              <td>
                <div style="font-weight:700;color:#fff;font-size:0.85rem;"><?= htmlspecialchars($h['subject'] ?? 'Notice') ?></div>
                <div style="font-size:0.75rem;color:var(--muted);margin-top:2px;max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= htmlspecialchars($h['message'] ?? '') ?>
                </div>
              </td>
              <td style="font-size:0.78rem;color:#cbd5e1;">
                <?= htmlspecialchars($h['sent_by'] ?? 'Staff') ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script>
function toggleSingleOrderField(val) {
  const grp = document.getElementById('singleOrderGroup');
  grp.style.display = (val === 'single') ? 'block' : 'none';
}

function applyTemplate(key) {
  const subInp = document.getElementById('subjectInput');
  const msgInp = document.getElementById('messageInput');
  const typeInp= document.getElementById('notifTypeInput');

  if (key === 'renewal') {
    typeInp.value = 'payment_reminder';
    subInp.value = '💳 Your Monthly Website Hosting Renewal is Due';
    msgInp.value = "Hello!\n\nThis is a friendly reminder that your monthly subscription renewal is approaching. Please ensure your payment method is up to date to maintain continuous live website hosting and admin panel access.\n\nThank you for choosing WebCraft AI!";
  } else if (key === 'overdue') {
    typeInp.value = 'warning';
    subInp.value = '⚠️ Urgent: Overdue Subscription - Website Suspension Warning';
    msgInp.value = "Dear Customer,\n\nYour website subscription is currently overdue. To prevent automatic deactivation of your live website, please settle your invoice immediately.\n\nIf you have already paid or need assistance, please contact support right away.";
  } else if (key === 'ssl') {
    typeInp.value = 'warning';
    subInp.value = '🔒 Domain SSL Security Certificate Renewal Notice';
    msgInp.value = "Hello,\n\nYour domain's HTTPS SSL certificate is scheduled for routine renewal within the next 14 days. Our system will attempt automatic re-certification. No action is required on your part unless your DNS records have changed.";
  } else if (key === 'maint') {
    typeInp.value = 'info';
    subInp.value = '🛠️ Scheduled Platform Infrastructure Maintenance';
    msgInp.value = "Dear Users,\n\nWe will be conducting scheduled system maintenance this Sunday between 02:00 AM and 03:00 AM UTC. Your live website will remain fully accessible, but administrative features in the builder may experience brief momentary pauses.";
  } else if (key === 'features') {
    typeInp.value = 'info';
    subInp.value = '🚀 New AI Feature Adder & Customizer Released!';
    msgInp.value = "Exciting news!\n\nYou can now add custom database sections (like Employees, Testimonials, and Products) to your admin panel and website using our new AI Co-Pilot in your Website Manager. Check it out today!";
  }
}
</script>
</div></body></html>
