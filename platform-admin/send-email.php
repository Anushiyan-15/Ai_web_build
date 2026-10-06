<?php
/**
 * Platform Admin - Send Email to User (send-email.php)
 * Compose + send a direct email to any customer via PHPMailer (WebCraft AI).
 * Optionally links to their latest order and saves a portal notification.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/platform-admin.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$flash_ok = '';
$flash_err = '';
$to = trim($_GET['to'] ?? $_POST['to'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Recipient suggestions: all known customer emails
$suggestEmails = [];
foreach (load_all_orders() as $o) {
    $em = strtolower(trim($o['admin_email'] ?? ($o['client_email'] ?? '')));
    if ($em && !in_array($em, $suggestEmails, true)) $suggestEmails[] = $em;
}
sort($suggestEmails);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $flash_err = 'Please enter a valid recipient email.';
    } elseif ($subject === '') {
        $flash_err = 'Subject cannot be empty.';
    } elseif ($message === '') {
        $flash_err = 'Message cannot be empty.';
    } else {
        require_once dirname(__DIR__) . '/includes/Mailer.php';
        require_once dirname(__DIR__) . '/includes/MailQueue.php';

        // Find latest order for this email to link notification + personalize
        $linkedOrder = null;
        $linkedOid = '';
        foreach (load_all_orders() as $o) {
            $em = strtolower(trim($o['admin_email'] ?? ($o['client_email'] ?? '')));
            if ($em === strtolower($to)) { $linkedOrder = $o; $linkedOid = $o['order_id'] ?? ''; break; }
        }

        $html = "<div style=\"font-family:'Segoe UI',Roboto,Arial,sans-serif;color:#e2e8f0;background:#0f172a;padding:28px;border-radius:14px;line-height:1.7;font-size:14px;white-space:pre-wrap;\">"
              . htmlspecialchars($message) . "</div>";

        if ($linkedOrder) {
            $res = queueNotificationEmail($linkedOid, $to, $subject, $message, 'info', $linkedOrder, $_SESSION['platform_admin_user'] ?? 'admin');
        } else {
            $res = queueMail(['to' => $to, 'subject' => $subject, 'html' => $html, 'text' => $message, 'kind' => 'direct']);
        }

        if (!empty($res['success'])) {
            if ($linkedOid) {
                save_notification($linkedOid, [
                    'type'    => 'info',
                    'message' => "[Email queued] {$subject}\n\n{$message}",
                    'sent_by' => $_SESSION['platform_admin_user'] ?? 'admin',
                ]);
            }
            log_admin_action('email_queued', $linkedOid ?: null, "Email to {$to}: {$subject}");
            $flash_ok = 'Email queued for background delivery to ' . $to . '.';
            $subject = ''; $message = '';
        } else {
            $flash_err = 'Email failed: ' . ($res['error'] ?? 'mail error');
        }
    }
}

render_head('Send Email');
render_sidebar('send_email');
?>
<div class="main">
  <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <div>
      <h1>Send Email to User</h1>
      <p>Direct message via PHPMailer as WebCraft AI — saved to their portal notifications when linked to an order</p>
    </div>
  </div>

  <?php if ($flash_ok): ?>
  <div class="alert alert-success" style="margin-bottom:20px;"><?= htmlspecialchars($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
  <div class="alert alert-error" style="margin-bottom:20px;"><?= htmlspecialchars($flash_err) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:720px;">
    <form method="POST" data-wcl="Sending email…">
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:.78rem;font-weight:700;color:var(--muted);margin-bottom:6px;">TO (customer email)</label>
        <input type="email" name="to" list="email-list" value="<?= htmlspecialchars($to) ?>" placeholder="user@email.com" required
               style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,.03);color:#fff;">
        <datalist id="email-list">
          <?php foreach ($suggestEmails as $em): ?>
          <option value="<?= htmlspecialchars($em) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:.78rem;font-weight:700;color:var(--muted);margin-bottom:6px;">SUBJECT</label>
        <input type="text" name="subject" value="<?= htmlspecialchars($subject) ?>" placeholder="e.g. Payment Reminder — WebCraft AI" required
               style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,.03);color:#fff;">
      </div>
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:.78rem;font-weight:700;color:var(--muted);margin-bottom:6px;">MESSAGE</label>
        <textarea name="message" rows="8" placeholder="Write your message to the customer…" required
                  style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,.03);color:#fff;font-family:inherit;"><?= htmlspecialchars($message) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Send Email →</button>
    </form>
  </div>
</div>
</div>
</body>
</html>
