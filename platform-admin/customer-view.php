<?php
/**
 * Platform Admin - Customer Detail View (customer-view.php)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/platform-admin.php';
require_once __DIR__ . '/../config/storage.php';
require_once __DIR__ . '/../includes/StorageService.php';
require_once __DIR__ . '/../includes/Csrf.php';
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

// ── STORAGE ADMINISTRATION (server-side, RBAC + CSRF enforced) ──
$stgEmail = strtolower(trim((string)($order['admin_email'] ?? '')));
if ($stgEmail === '' || !filter_var($stgEmail, FILTER_VALIDATE_EMAIL)) {
    $stgEmail = strtolower(trim((string)($order['client_email'] ?? '')));
}
if ($stgEmail) {
    $storageRefresh = StorageService::recalculateUsage($stgEmail);
    if (empty($storageRefresh['success'])) {
        error_log('platform customer storage recalculation failed: ' . ($storageRefresh['error'] ?? 'unknown error'));
    }
}
$stgSummary = $stgEmail && !empty($storageRefresh['success']) ? StorageService::getSummary($stgEmail) : null;
$stgFiles = $stgEmail ? StorageService::listFiles($stgEmail, 50, 0) : [];
$stgTxns = $stgEmail ? StorageService::listTransactions($stgEmail, 50) : [];
$stgInvoices = $stgEmail ? StorageService::invoices($stgEmail) : ['subscription' => [], 'storage' => []];
$stgPlans = StorageService::getAllPlans(false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['storage_admin'])) {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $msg_error = 'Invalid CSRF token. Please reload and try again.';
    } elseif (!has_permission('billing_manage') && !has_permission('*')) {
        http_response_code(403);
        $msg_error = 'Forbidden: billing_manage permission required.';
    } elseif (!$stgEmail) {
        $msg_error = 'No customer email on this order.';
    } else {
        $adminUser = current_admin_user();
        $note = trim(strip_tags((string)($_POST['stg_note'] ?? '')));
        $ops = ['require_note' => true];
        $sub = (string)($_POST['storage_admin'] ?? '');
        if ($sub === 'plan' && isset($_POST['stg_plan'])) $ops['plan_slug'] = (string)$_POST['stg_plan'];
        elseif ($sub === 'override') $ops['manual_limit_mb'] = ($_POST['stg_override'] ?? '') === '' ? null : (int)$_POST['stg_override'];
        elseif ($sub === 'grant') { $ops['grant_gb'] = max(1, (int)($_POST['stg_grant_gb'] ?? 1)); }
        elseif ($sub === 'reconcile') { $ops['reconcile'] = true; if ($note === '') $note = 'Counter reconciled from disk inventory.'; }
        elseif ($sub === 'suspend') $ops['suspend'] = true;
        elseif ($sub === 'reactivate') $ops['suspend'] = false;
        elseif ($sub === 'autobill') $ops['auto_bill_enabled'] = !empty($_POST['stg_autobill']);
        else $msg_error = 'Unknown storage operation.';
        if ($msg_error === '') {
            $res = StorageService::adminAdjust($stgEmail, $ops, $adminUser, $note);
            if (!empty($res['success'])) {
                $msg_success = 'Storage updated: ' . implode('; ', $res['applied'] ?? []) . '. Audit record written.';
                $stgSummary = StorageService::getSummary($stgEmail);
                $stgFiles = StorageService::listFiles($stgEmail, 50, 0);
                $stgTxns = StorageService::listTransactions($stgEmail, 50);
            } else {
                $msg_error = $res['error'] ?? 'Storage update failed.';
            }
        }
    }
}

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
            ['Password',    '— (hashed; owner signs in with configured credentials)'],
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

  <!-- Storage & Quota Administration -->
  <div class="card" id="storage" style="margin-bottom:20px;">
    <h3 style="font-size:.95rem;font-weight:700;color:#fff;margin-bottom:4px;">💾 Storage &amp; Quota — <?= htmlspecialchars($stgEmail ?: 'no email on order') ?></h3>
    <p style="font-size:.78rem;color:var(--muted);margin-bottom:16px;">Every change below writes an <code>ADMIN_ADJUST</code> storage transaction plus an admin audit-log entry. Usage resets reconcile from the disk inventory — they never falsify disk consumption.</p>
    <?php if (!$stgSummary): ?>
      <div style="color:#f59e0b;font-size:.85rem;">Storage data unavailable (customer row or database unreachable).</div>
    <?php else:
      $sp = min(100, max(0, (float)$stgSummary['percent']));
      $scol = $sp < 70 ? '#10b981' : ($sp <= 90 ? '#f59e0b' : '#ef4444');
    ?>
      <div class="grid-2" style="margin-bottom:16px;">
        <div style="background:#0d1117;border:1px solid var(--border);border-radius:12px;padding:14px 16px;">
          <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;font-weight:700;">Usage</div>
          <div style="font-size:1.15rem;font-weight:800;color:#fff;margin:.3rem 0;"><?= htmlspecialchars((string)$stgSummary['used_mb']) ?> / <?= htmlspecialchars((string)$stgSummary['limit_mb']) ?> MB</div>
          <div style="height:10px;background:#1e293b;border-radius:999px;overflow:hidden;"><div style="height:100%;width:<?= $sp ?>%;background:<?= $scol ?>;"></div></div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:.4rem;">
            Plan <?= htmlspecialchars($stgSummary['plan']['name'] ?? '') ?> (base <?= (int)$stgSummary['plan_base_limit_mb'] ?> MB)
            + extra <?= (int)$stgSummary['extra_allowance_mb'] ?> MB (<?= (int)$stgSummary['extra_gb_purchased'] ?> GB purchased)
            <?= $stgSummary['manual_storage_limit_mb'] !== null ? ' · <strong style="color:#f59e0b;">override ' . (int)$stgSummary['manual_storage_limit_mb'] . ' MB</strong>' : '' ?>
            <br>Bandwidth <?= htmlspecialchars((string)$stgSummary['bandwidth_used_mb']) ?> / <?= (int)$stgSummary['bandwidth_limit_mb'] ?> MB
            · Period <?= htmlspecialchars((string)($stgSummary['billing_period_start'] ?? '—')) ?> → <?= htmlspecialchars((string)($stgSummary['billing_period_end'] ?? '—')) ?>
            · Status <strong style="color:<?= !empty($stgSummary['suspended']) ? '#ef4444' : '#10b981' ?>;"><?= htmlspecialchars($stgSummary['status']) ?></strong>
            · Auto-bill <?= !empty($stgSummary['auto_bill_enabled']) ? 'opted-in' : 'off' ?><?= empty($stgSummary['auto_bill_supported']) ? ' (gateway auto-charge unsupported)' : '' ?>
          </div>
        </div>
        <div style="background:#0d1117;border:1px solid var(--border);border-radius:12px;padding:14px 16px;">
          <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;font-weight:700;margin-bottom:8px;">Adjust (note required)</div>
          <?php if (has_permission('billing_manage') || has_permission('*')): ?>
          <form method="POST" style="display:grid;gap:8px;">
            <?= csrf_field() ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <select name="stg_plan" style="flex:1;min-width:140px;">
                <?php foreach ($stgPlans as $pl): ?>
                <option value="<?= htmlspecialchars($pl['slug']) ?>" <?= (($stgSummary['plan']['slug'] ?? '') === $pl['slug']) ? 'selected' : '' ?>><?= htmlspecialchars($pl['name']) ?> (<?= (int)$pl['storage_limit_mb'] ?> MB)</option>
                <?php endforeach; ?>
              </select>
              <button type="submit" name="storage_admin" value="plan" class="btn btn-ghost btn-sm">Change plan</button>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <input type="number" min="0" name="stg_override" placeholder="Manual limit MB (blank = clear)" value="<?= $stgSummary['manual_storage_limit_mb'] !== null ? (int)$stgSummary['manual_storage_limit_mb'] : '' ?>" style="flex:1;min-width:140px;">
              <button type="submit" name="storage_admin" value="override" class="btn btn-ghost btn-sm">Set override</button>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <input type="number" min="1" max="100" name="stg_grant_gb" value="1" style="flex:1;min-width:140px;">
              <button type="submit" name="storage_admin" value="grant" class="btn btn-ghost btn-sm" onclick="return confirm('Grant free GB? A mandatory note is required below.')">Grant free GB</button>
            </div>
            <input type="text" name="stg_note" placeholder="Explanatory note (required, recorded in ledger)" maxlength="500">
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <button type="submit" name="storage_admin" value="reconcile" class="btn btn-ghost btn-sm">↻ Reconcile counter</button>
              <?php if (!empty($stgSummary['suspended'])): ?>
              <button type="submit" name="storage_admin" value="reactivate" class="btn btn-success btn-sm">Reactivate account</button>
              <?php else: ?>
              <button type="submit" name="storage_admin" value="suspend" class="btn btn-danger btn-sm" onclick="return confirm('Suspend this customer account? Uploads and purchases will be blocked.')">Suspend account</button>
              <?php endif; ?>
              <label style="display:inline-flex;align-items:center;gap:6px;font-size:.78rem;color:var(--muted);text-transform:none;"><input type="checkbox" name="stg_autobill" value="1" <?= !empty($stgSummary['auto_bill_enabled']) ? 'checked' : '' ?> style="width:15px;height:15px;"> auto-bill eligible</label>
              <button type="submit" name="storage_admin" value="autobill" class="btn btn-ghost btn-sm">Save auto-bill</button>
            </div>
          </form>
          <?php else: ?>
          <div style="font-size:.8rem;color:#f59e0b;">🔒 billing_manage permission required.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="grid-2">
        <div>
          <h4 style="font-size:.8rem;color:var(--muted);margin-bottom:8px;">TRACKED FILES (<?= count($stgFiles) ?>)</h4>
          <?php if (empty($stgFiles)): ?><div style="font-size:.8rem;color:var(--muted);">No tracked files.</div>
          <?php else: ?><div style="display:grid;gap:6px;max-height:260px;overflow-y:auto;">
            <?php foreach ($stgFiles as $f): ?>
            <div style="font-size:.78rem;background:#0d1117;border:1px solid var(--border);border-radius:8px;padding:7px 10px;display:flex;justify-content:space-between;gap:6px;">
              <span style="color:#e2e8f0;"><?= htmlspecialchars($f['file_name'] ?? '') ?></span>
              <span style="color:var(--muted);white-space:nowrap;"><?= number_format(((int)($f['file_size_bytes'] ?? 0)) / 1048576, 2) ?> MB · <?= htmlspecialchars(substr((string)($f['uploaded_at'] ?? ''), 0, 10)) ?></span>
            </div>
            <?php endforeach; ?>
          </div><?php endif; ?>
          <h4 style="font-size:.8rem;color:var(--muted);margin:12px 0 8px;">SUBSCRIPTION PAYMENTS (existing ledger)</h4>
          <?php if (empty($stgInvoices['subscription'])): ?><div style="font-size:.8rem;color:var(--muted);">None.</div>
          <?php else: foreach (array_slice($stgInvoices['subscription'], 0, 8) as $inv): ?>
            <div style="font-size:.78rem;color:#cbd5e1;"><?= htmlspecialchars($inv['order_id'] ?? '') ?> — $<?= number_format((float)($inv['amount'] ?? 0), 2) ?> <?= htmlspecialchars($inv['currency'] ?? 'USD') ?> (<?= htmlspecialchars($inv['payment_status'] ?? $inv['status'] ?? '') ?>)</div>
          <?php endforeach; endif; ?>
          <h4 style="font-size:.8rem;color:var(--muted);margin:12px 0 8px;">STORAGE PURCHASES</h4>
          <?php $sp2 = array_values(array_filter($stgInvoices['storage'])); ?>
          <?php if (empty($sp2)): ?><div style="font-size:.8rem;color:var(--muted);">None.</div>
          <?php else: foreach (array_slice($sp2, 0, 8) as $inv): ?>
            <div style="font-size:.78rem;color:#cbd5e1;"><?= htmlspecialchars($inv['payment_reference'] ?? ('#' . $inv['id'])) ?> — <?= htmlspecialchars(($inv['amount_charged'] ?? '') . ' ' . ($inv['currency'] ?? '')) ?> <span style="color:var(--muted);"><?= htmlspecialchars($inv['created_at'] ?? '') ?></span></div>
          <?php endforeach; endif; ?>
        </div>
        <div>
          <h4 style="font-size:.8rem;color:var(--muted);margin-bottom:8px;">STORAGE TRANSACTIONS (<?= count($stgTxns) ?>)</h4>
          <?php if (empty($stgTxns)): ?><div style="font-size:.8rem;color:var(--muted);">No transactions.</div>
          <?php else: ?><div style="display:grid;gap:6px;max-height:420px;overflow-y:auto;">
            <?php foreach ($stgTxns as $t): ?>
            <div style="font-size:.75rem;background:#0d1117;border:1px solid var(--border);border-radius:8px;padding:7px 10px;">
              <strong style="color:#818cf8;"><?= htmlspecialchars($t['action'] ?? '') ?></strong>
              <span style="color:#cbd5e1;"><?= htmlspecialchars($t['note'] ?? '') ?></span>
              <?php if ($t['amount_charged'] !== null): ?><span style="color:#10b981;"><?= htmlspecialchars($t['amount_charged'] . ' ' . ($t['currency'] ?? '')) ?></span><?php endif; ?>
              <div style="color:#475569;"><?= htmlspecialchars($t['created_at'] ?? '') ?><?= !empty($t['payment_reference']) ? ' · ' . htmlspecialchars($t['payment_reference']) : '' ?></div>
            </div>
            <?php endforeach; ?>
          </div><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
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
