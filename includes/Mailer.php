<?php
// ═══════════════════════════════════════════════════════════════
//  includes/Mailer.php — Centralized PHPMailer Notification Service
// ═══════════════════════════════════════════════════════════════

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
if (file_exists(dirname(__DIR__) . '/config/mail.php')) {
    require_once dirname(__DIR__) . '/config/mail.php';
}
require_once __DIR__ . '/db.php';

class Mailer {

    /**
     * Get configured PHPMailer instance
     * Credentials: config constants → env vars (no hardcoded secrets).
     */
    public static function getInstance(): PHPMailer {
        $smtpHost  = defined('SMTP_HOST') ? SMTP_HOST : (getenv('SMTP_HOST') ?: 'smtp.gmail.com');
        $smtpUser  = defined('SMTP_USER') ? SMTP_USER : (getenv('SMTP_USER') ?: '');
        $smtpPass  = defined('SMTP_PASS') ? SMTP_PASS : (getenv('SMTP_PASS') ?: '');
        $smtpPort  = defined('SMTP_PORT') ? SMTP_PORT : ((int)getenv('SMTP_PORT') ?: 587);
        $smtpSec   = defined('SMTP_SECURE') ? SMTP_SECURE : (getenv('SMTP_SECURE') ?: 'tls');
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (getenv('SMTP_FROM_EMAIL') ?: $smtpUser);
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'WebCraft AI';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = ($smtpSec === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 12;

        $mail->setFrom($fromEmail, $fromName);
        if (defined('SUPPORT_EMAIL')) {
            $mail->addReplyTo(SUPPORT_EMAIL, SMTP_FROM_NAME . ' Support');
        }

        return $mail;
    }

    /**
     * Send general HTML email
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): array {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid recipient email address'];
        }

        try {
            $mail = self::getInstance();
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log('PHPMailer Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Throwable $t) {
            error_log('Mailer Fatal Error: ' . $t->getMessage());
            return ['success' => false, 'error' => $t->getMessage()];
        }
    }

    /**
     * Build publish-notification payload without sending (for queue).
     * Returns [to, subject, html, text].
     */
    public static function buildPublishPayload(array $order): array {
        $to = $order['admin_email'] ?: ($order['client_email'] ?? '');
        $siteName    = htmlspecialchars($order['site_name'] ?? 'Your Website');
        $liveUrl     = $order['live_url'] ?? '#';
        $adminUrl    = $order['admin_url'] ?? '';
        $adminUser   = htmlspecialchars($order['admin_username'] ?? 'admin');
        $adminPass   = htmlspecialchars($order['admin_password_plain'] ?? 'WebCraft@2026!');
        $orderId     = htmlspecialchars($order['order_id'] ?? '');
        $package     = htmlspecialchars(ucfirst($order['package'] ?? 'Pro'));
        $amount      = number_format((float)($order['amount'] ?? 19.00), 2);
        $portalUrl   = (defined('SITE_URL') ? SITE_URL : 'http://localhost/project/webbbuilder') . '/customer-portal.php';
        $managerUrl  = (defined('SITE_URL') ? SITE_URL : 'http://localhost/project/webbbuilder') . '/site-manager.php?order_id=' . urlencode($order['order_id']);

        $subject = "🎉 Your Website \"{$siteName}\" is Officially Live! — WebCraft AI";

        $html = "
<!DOCTYPE html>
<html>
<head>
<meta charset=\"UTF-8\">
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
<title>Website Published</title>
</head>
<body style=\"margin:0;padding:0;background:#090d16;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;\">
  <div style=\"max-width:620px;margin:30px auto;background:#0f172a;border:1px solid #1e293b;border-radius:18px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.5);\">
    
    <!-- Header -->
    <div style=\"background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:35px 30px;text-align:center;\">
      <div style=\"font-size:42px;margin-bottom:8px;\">🚀</div>
      <h1 style=\"margin:0;font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.02em;\">Your Website is Officially Live!</h1>
      <p style=\"margin:8px 0 0;font-size:14px;color:#c7d2fe;\">Congratulations, <strong>{$siteName}</strong> is online and ready for the world.</p>
    </div>

    <!-- Content -->
    <div style=\"padding:30px 28px;\">

      <!-- Action Buttons -->
      <div style=\"text-align:center;margin-bottom:28px;\">
        <a href=\"{$liveUrl}\" target=\"_blank\" style=\"display:inline-block;padding:14px 28px;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;border-radius:10px;box-shadow:0 6px 20px rgba(16,185,129,0.35);margin:0 6px 10px;\">🌐 Open Live Website</a>
        " . ($adminUrl ? "<a href=\"{$adminUrl}\" target=\"_blank\" style=\"display:inline-block;padding:14px 28px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;border-radius:10px;box-shadow:0 6px 20px rgba(99,102,241,0.35);margin:0 6px 10px;\">🔐 Admin Panel</a>" : "") . "
      </div>

      <!-- Access Details Box -->
      <div style=\"background:#060a14;border:1px solid #1e293b;border-radius:14px;padding:20px;margin-bottom:24px;\">
        <h3 style=\"margin:0 0 14px;color:#38bdf8;font-size:14px;text-transform:uppercase;letter-spacing:0.06em;\">🔑 Access Credentials &amp; Project Info</h3>
        <table style=\"width:100%;border-collapse:collapse;font-size:14px;\">
          <tr>
            <td style=\"padding:6px 0;color:#64748b;width:130px;\">Order ID:</td>
            <td style=\"padding:6px 0;color:#f8fafc;font-family:monospace;font-weight:700;\">{$orderId}</td>
          </tr>
          <tr>
            <td style=\"padding:6px 0;color:#64748b;\">Live Website:</td>
            <td style=\"padding:6px 0;\"><a href=\"{$liveUrl}\" style=\"color:#38bdf8;text-decoration:none;\">{$liveUrl}</a></td>
          </tr>
          " . ($adminUrl ? "
          <tr>
            <td style=\"padding:6px 0;color:#64748b;\">Admin URL:</td>
            <td style=\"padding:6px 0;\"><a href=\"{$adminUrl}\" style=\"color:#a78bfa;text-decoration:none;\">{$adminUrl}</a></td>
          </tr>
          <tr>
            <td style=\"padding:6px 0;color:#64748b;\">Admin Username:</td>
            <td style=\"padding:6px 0;color:#34d399;font-weight:700;\">{$adminUser}</td>
          </tr>
          <tr>
            <td style=\"padding:6px 0;color:#64748b;\">Admin Password:</td>
            <td style=\"padding:6px 0;color:#facc15;font-weight:700;\">{$adminPass}</td>
          </tr>
          <tr>
            <td style=\"padding:6px 0;color:#64748b;\">Demo Login:</td>
            <td style=\"padding:6px 0;color:#6ee7b7;font-weight:700;\">demo / Demo@1234 <span style=\"color:#64748b;font-weight:400;\">(shareable preview)</span></td>
          </tr>
          " : "") . "
          <tr>
            <td style=\"padding:6px 0;color:#64748b;\">Subscription:</td>
            <td style=\"padding:6px 0;color:#f8fafc;\">{$package} Plan (\${$amount}/mo)</td>
          </tr>
        </table>
      </div>

      <!-- Customer Portal Box -->
      <div style=\"background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.3);border-radius:14px;padding:20px;margin-bottom:24px;\">
        <h3 style=\"margin:0 0 8px;color:#c7d2fe;font-size:15px;font-weight:800;\">📱 Track &amp; Manage Your Projects Anytime</h3>
        <p style=\"margin:0 0 14px;color:#94a3b8;font-size:13px;line-height:1.5;\">
          You can track all your websites, manage subscription payments, and add new AI features anytime from your Customer Portal.
        </p>
        <a href=\"{$portalUrl}\" target=\"_blank\" style=\"display:inline-block;padding:10px 18px;background:#6366f1;color:#ffffff;text-decoration:none;font-weight:700;font-size:13px;border-radius:8px;\">⚙️ Go to Customer Portal →</a>
      </div>

      <p style=\"margin:24px 0 0;font-size:12px;color:#64748b;text-align:center;line-height:1.5;\">
        Need assistance or want to add custom features? Contact our team at <a href=\"mailto:" . (defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : (getenv('SUPPORT_EMAIL') ?: '')) . "\" style=\"color:#38bdf8;text-decoration:none;\">" . (defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : (getenv('SUPPORT_EMAIL') ?: 'support')) . "</a>.
      </p>

    </div>

    <!-- Footer -->
    <div style=\"background:#060a14;padding:16px 20px;text-align:center;border-top:1px solid #1e293b;font-size:11px;color:#475569;\">
      &copy; " . date('Y') . " " . (defined('SITE_NAME') ? SITE_NAME : 'WebCraft AI') . ". All rights reserved.
    </div>
  </div>
</body>
</html>
";

        $res = ['to' => $to, 'subject' => $subject, 'html' => $html,
                'text' => strip_tags($html), 'order' => $order];
        return $res;
    }

    /**
     * Send onboarding / publish confirmation email to customer (direct).
     * For background delivery use buildPublishPayload() + queueMail().
     */
    public static function sendPublishNotification(array $order): array {
        $p = self::buildPublishPayload($order);
        if (!$p['to']) return ['success' => false, 'error' => 'No customer email provided'];
        $res = self::send($p['to'], $p['subject'], $p['html']);

        // Also record this welcome notification in Supabase
        if ($res['success']) {
            self::recordNotificationInDb(
                $order['order_id'],
                'welcome',
                $p['subject'],
                "Your website '{$order['site_name']}' has been successfully published to {$order['live_url']}",
                'system',
                true
            );
        }

        return $res;
    }

    /**
     * Build the notification HTML template (shared by direct send + queue).
     */
    public static function buildNotificationHtml(string $orderId, string $subject, string $message, string $type = 'info', ?array $order = null): string {
        $typeBadge = [
            'payment_reminder' => ['icon' => '💳', 'color' => '#f59e0b', 'label' => 'Payment Reminder'],
            'warning'          => ['icon' => '⚠️', 'color' => '#ef4444', 'label' => 'Important Alert'],
            'info'             => ['icon' => '🔔', 'color' => '#6366f1', 'label' => 'Project Update'],
        ][$type] ?? ['icon' => '🔔', 'color' => '#6366f1', 'label' => 'Notification'];

        $siteName  = htmlspecialchars($order['site_name'] ?? 'Your Website');
        $orderIdH  = htmlspecialchars($orderId);
        $portalUrl = (defined('SITE_URL') ? SITE_URL : 'http://localhost/project/webbbuilder') . '/customer-portal.php';
        $subjectH  = htmlspecialchars($subject);
        $messageH  = htmlspecialchars($message);

        return "
<!DOCTYPE html>
<html>
<head>
<meta charset=\"UTF-8\">
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
<title>{$subjectH}</title>
</head>
<body style=\"margin:0;padding:0;background:#090d16;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;\">
  <div style=\"max-width:580px;margin:30px auto;background:#0f172a;border:1px solid #1e293b;border-radius:16px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.5);\">
    <div style=\"background:#131c31;padding:24px 28px;border-bottom:1px solid #1e293b;display:flex;align-items:center;\">
      <span style=\"font-size:24px;margin-right:12px;\">{$typeBadge['icon']}</span>
      <div>
        <span style=\"display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;background:rgba(255,255,255,0.06);color:{$typeBadge['color']};border:1px solid {$typeBadge['color']};\">{$typeBadge['label']}</span>
        <h2 style=\"margin:6px 0 0;font-size:18px;color:#ffffff;\">{$siteName}</h2>
      </div>
    </div>
    <div style=\"padding:28px;\">
      <h3 style=\"margin:0 0 16px;font-size:16px;color:#f8fafc;\">{$subjectH}</h3>
      <div style=\"background:#060a14;border:1px solid #1e293b;border-left:4px solid {$typeBadge['color']};border-radius:10px;padding:18px;color:#cbd5e1;font-size:14px;line-height:1.6;white-space:pre-wrap;\">{$messageH}</div>
      <div style=\"margin-top:24px;text-align:center;\">
        <a href=\"{$portalUrl}\" target=\"_blank\" style=\"display:inline-block;padding:12px 24px;background:#6366f1;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;border-radius:9px;box-shadow:0 4px 15px rgba(99,102,241,0.3);\">📱 Open Customer Portal</a>
      </div>
    </div>
    <div style=\"background:#060a14;padding:14px 20px;text-align:center;border-top:1px solid #1e293b;font-size:11px;color:#475569;\">
      Order: {$orderIdH} &bull; &copy; " . date('Y') . " " . (defined('SITE_NAME') ? SITE_NAME : 'WebCraft AI') . "
    </div>
  </div>
</body>
</html>";
    }

    /**
     * Send a batch of prebuilt mails over ONE SMTP connection (fast bulk).
     * $jobs: [['to'=>..,'subject'=>..,'html'=>..,'text'=>..], ...]
     * Returns [index => ['success'=>bool,'error'=>string]].
     */
    public static function sendBatch(array $jobs): array {
        $out = [];
        if (empty($jobs)) return $out;
        try {
            $mail = self::getInstance();
            $mail->SMTPKeepAlive = true;
            $mail->isHTML(true);
            foreach ($jobs as $i => $j) {
                try {
                    $to = $j['to'] ?? '';
                    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                        $out[$i] = ['success' => false, 'error' => 'Invalid recipient'];
                        continue;
                    }
                    $mail->clearAllRecipients();
                    $mail->clearAttachments();
                    $mail->addAddress($to);
                    $mail->Subject = $j['subject'] ?? '(no subject)';
                    $mail->Body    = $j['html'] ?? '';
                    $mail->AltBody = $j['text'] ?? strip_tags($j['html'] ?? '');
                    $mail->send();
                    $out[$i] = ['success' => true];
                } catch (Exception $e) {
                    $out[$i] = ['success' => false, 'error' => $e->getMessage()];
                } catch (Throwable $t) {
                    $out[$i] = ['success' => false, 'error' => $t->getMessage()];
                }
            }
            try { $mail->smtpClose(); } catch (Throwable $t) {}
        } catch (Throwable $t) {
            foreach ($jobs as $i => $j) {
                if (!isset($out[$i])) $out[$i] = ['success' => false, 'error' => $t->getMessage()];
            }
        }
        return $out;
    }

    /**
     * Send administrative notification / payment reminder email to customer
     */
    public static function sendNotificationEmail(string $orderId, string $to, string $subject, string $message, string $type = 'info', ?array $order = null): array {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email'];
        }

        $html = self::buildNotificationHtml($orderId, $subject, $message, $type, $order);
        $res = self::send($to, $subject, $html, $message);

        // Record in Supabase
        self::recordNotificationInDb(
            $orderId,
            $type,
            $subject,
            $message,
            'admin',
            $res['success'] ?? false
        );

        return $res;
    }

    /**
     * Welcome email payload builder (for queue).
     */
    public static function buildWelcomePayload(string $to, string $name): array {
        $safeName = htmlspecialchars($name ?: 'there');
        $base = defined('SITE_URL') ? SITE_URL : 'http://localhost/project/webbbuilder';
        $html = "
<!DOCTYPE html>
<html>
<head><meta charset=\"UTF-8\"></head>
<body style=\"margin:0;padding:0;background:#090d16;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;\">
  <div style=\"max-width:520px;margin:30px auto;background:#0f172a;border:1px solid #1e293b;border-radius:16px;overflow:hidden;\">
    <div style=\"background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:30px;text-align:center;\">
      <div style=\"font-size:40px;margin-bottom:6px;\">🎉</div>
      <h1 style=\"margin:0;font-size:20px;color:#fff;\">Welcome, {$safeName}!</h1>
      <p style=\"margin:8px 0 0;font-size:13px;color:#c7d2fe;\">Your WebCraft AI account is verified and ready.</p>
    </div>
    <div style=\"padding:26px;text-align:center;\">
      <p style=\"color:#94a3b8;font-size:13px;line-height:1.6;margin:0 0 20px;\">Generate websites with AI, publish them live, and manage all your projects from one place.</p>
      <a href=\"{$base}/builder.php\" style=\"display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;text-decoration:none;font-weight:700;font-size:14px;border-radius:9px;margin:0 6px 10px;\">✦ Start Building</a>
      <a href=\"{$base}/customer-portal.php\" style=\"display:inline-block;padding:12px 24px;background:#6366f1;color:#fff;text-decoration:none;font-weight:700;font-size:14px;border-radius:9px;margin:0 6px 10px;\">📁 My Projects</a>
    </div>
    <div style=\"background:#060a14;padding:12px;text-align:center;font-size:11px;color:#475569;\">&copy; " . date('Y') . " WebCraft AI</div>
  </div>
</body>
</html>";
        return ['to' => $to, 'subject' => 'Welcome to WebCraft AI — Account Ready', 'html' => $html,
                'text' => "Welcome {$name}! Your WebCraft AI account is ready. Start building: {$base}/builder.php"];
    }

    /** Direct welcome send (prefer queueMail). */
    public static function sendWelcomeEmail(string $to, string $name): array {
        $p = self::buildWelcomePayload($to, $name);
        return self::send($p['to'], $p['subject'], $p['html'], $p['text']);
    }

    /**
     * Sign-in alert payload builder (for queue).
     */
    public static function buildSigninAlertPayload(string $to, string $name, string $ip = ''): array {
        $safeName = htmlspecialchars($name ?: 'there');
        $time = date('M j, Y g:i A');
        $ipH = htmlspecialchars($ip ?: 'unknown');
        $base = defined('SITE_URL') ? SITE_URL : 'http://localhost/project/webbbuilder';
        $html = "
<!DOCTYPE html>
<html>
<head><meta charset=\"UTF-8\"></head>
<body style=\"margin:0;padding:0;background:#090d16;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;\">
  <div style=\"max-width:520px;margin:30px auto;background:#0f172a;border:1px solid #1e293b;border-radius:16px;overflow:hidden;\">
    <div style=\"background:#131c31;padding:24px;border-bottom:1px solid #1e293b;\">
      <h2 style=\"margin:0;font-size:17px;color:#fff;\">🔔 New Sign-in to Your Account</h2>
    </div>
    <div style=\"padding:24px;\">
      <p style=\"color:#cbd5e1;font-size:13px;margin:0 0 14px;\">Hi {$safeName}, your WebCraft AI account was just signed in:</p>
      <div style=\"background:#060a14;border:1px solid #1e293b;border-radius:10px;padding:14px;font-size:13px;color:#94a3b8;\">Time: <strong style=\"color:#fff;\">{$time}</strong><br>IP: <strong style=\"color:#fff;\">{$ipH}</strong></div>
      <p style=\"color:#64748b;font-size:12px;margin:14px 0 0;\">Wasn't you? <a href=\"{$base}/customer-portal.php?view=forgot\" style=\"color:#38bdf8;\">Reset your password now</a>.</p>
    </div>
    <div style=\"background:#060a14;padding:12px;text-align:center;font-size:11px;color:#475569;\">&copy; " . date('Y') . " WebCraft AI</div>
  </div>
</body>
</html>";
        return ['to' => $to, 'subject' => 'New Sign-in — WebCraft AI', 'html' => $html,
                'text' => "Your WebCraft AI account was signed in on {$time} from IP {$ip}."];
    }

    /** Direct sign-in alert send (prefer queueMail). */
    public static function sendSigninAlert(string $to, string $name, string $ip = ''): array {
        $p = self::buildSigninAlertPayload($to, $name, $ip);
        return self::send($p['to'], $p['subject'], $p['html'], $p['text']);
    }

    /**
     * Password-changed payload builder (for queue).
     */
    public static function buildPasswordChangedPayload(string $to, string $name): array {
        $safeName = htmlspecialchars($name ?: 'there');
        $time = date('M j, Y g:i A');
        $base = defined('SITE_URL') ? SITE_URL : 'http://localhost/project/webbbuilder';
        $html = "
<!DOCTYPE html>
<html>
<head><meta charset=\"UTF-8\"></head>
<body style=\"margin:0;padding:0;background:#090d16;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;\">
  <div style=\"max-width:520px;margin:30px auto;background:#0f172a;border:1px solid #1e293b;border-radius:16px;overflow:hidden;\">
    <div style=\"background:linear-gradient(135deg,#059669,#10b981);padding:26px;text-align:center;\">
      <div style=\"font-size:34px;\">✅</div>
      <h1 style=\"margin:6px 0 0;font-size:18px;color:#fff;\">Password Updated Successfully</h1>
    </div>
    <div style=\"padding:24px;\">
      <p style=\"color:#cbd5e1;font-size:13px;margin:0 0 10px;\">Hi {$safeName}, your WebCraft AI password was changed on <strong>{$time}</strong>.</p>
      <p style=\"color:#64748b;font-size:12px;margin:0;\">Didn't do this? <a href=\"{$base}/customer-portal.php?view=forgot\" style=\"color:#38bdf8;\">Reset it immediately</a> and contact support.</p>
      <div style=\"text-align:center;margin-top:18px;\"><a href=\"{$base}/customer-portal.php\" style=\"display:inline-block;padding:11px 22px;background:#6366f1;color:#fff;text-decoration:none;font-weight:700;font-size:13px;border-radius:9px;\">Sign In →</a></div>
    </div>
    <div style=\"background:#060a14;padding:12px;text-align:center;font-size:11px;color:#475569;\">&copy; " . date('Y') . " WebCraft AI</div>
  </div>
</body>
</html>";
        return ['to' => $to, 'subject' => 'Password Updated Successfully — WebCraft AI', 'html' => $html,
                'text' => "Your WebCraft AI password was changed on {$time}."];
    }

    /** Direct password-changed send (prefer queueMail). */
    public static function sendPasswordChangedEmail(string $to, string $name): array {
        $p = self::buildPasswordChangedPayload($to, $name);
        return self::send($p['to'], $p['subject'], $p['html'], $p['text']);
    }

    /**
     * Send a 6-digit verification OTP email
     */
    public static function sendOtpEmail(string $to, string $code, string $purpose = 'verification'): array {
        $titles = [
            'signup' => 'Verify Your Email — Create Account',
            'reset'  => 'Password Reset Code',
        ];
        $title = $titles[$purpose] ?? 'Your Verification Code';
        $intro = $purpose === 'reset'
            ? 'Use this code to reset your password. It expires in 10 minutes.'
            : 'Use this code to verify your email and activate your account. It expires in 10 minutes.';

        $html = "
<!DOCTYPE html>
<html>
<head><meta charset=\"UTF-8\"></head>
<body style=\"margin:0;padding:0;background:#090d16;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;\">
  <div style=\"max-width:480px;margin:30px auto;background:#0f172a;border:1px solid #1e293b;border-radius:16px;overflow:hidden;\">
    <div style=\"background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:28px;text-align:center;\">
      <div style=\"font-size:36px;margin-bottom:6px;\">🔐</div>
      <h1 style=\"margin:0;font-size:19px;color:#fff;\">{$title}</h1>
    </div>
    <div style=\"padding:28px;text-align:center;\">
      <p style=\"color:#94a3b8;font-size:13px;margin:0 0 18px;\">{$intro}</p>
      <div style=\"font-size:38px;font-weight:900;letter-spacing:12px;color:#fff;background:#060a14;border:1.5px dashed #6366f1;border-radius:12px;padding:16px 8px 16px 20px;margin-bottom:18px;font-family:monospace;\">{$code}</div>
      <p style=\"color:#64748b;font-size:12px;margin:0;\">Didn't request this? Ignore this email — no changes will be made.</p>
    </div>
    <div style=\"background:#060a14;padding:12px;text-align:center;font-size:11px;color:#475569;\">&copy; " . date('Y') . " " . (defined('SITE_NAME') ? SITE_NAME : 'WebCraft AI') . "</div>
  </div>
</body>
</html>";

        return self::send($to, $title . ' — ' . $code, $html, "Your verification code is: {$code}. It expires in 10 minutes.");
    }

    /**
     * Contact-form payloads (platform + published sites).
     * Owner notification (so the business can contact the visitor) +
     * visitor auto-reply (confirmation). Sent via queueMail (PHPMailer SMTP).
     * Returns [owner => [...], autoreply => [...]].
     */
    public static function buildContactPayload(string $siteName, string $name, string $email, string $phone, string $subject, string $message): array {
        $safeSite = htmlspecialchars($siteName ?: 'Website');
        $safeName = htmlspecialchars($name);
        $safeEmail = htmlspecialchars($email);
        $safePhone = htmlspecialchars($phone ?: '—');
        $safeSubject = htmlspecialchars($subject ?: 'Website Inquiry');
        $safeMsg = nl2br(htmlspecialchars($message));
        $time = date('M j, Y g:i A');

        $ownerHtml = "
<!DOCTYPE html>
<html><head><meta charset=\"UTF-8\"></head>
<body style=\"margin:0;padding:0;background:#f3f4f6;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111827;\">
  <div style=\"max-width:600px;margin:24px auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;\">
    <div style=\"background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:22px 26px;\">
      <h2 style=\"margin:0;font-size:18px;color:#fff;\">New website enquiry — {$safeSite}</h2>
      <p style=\"margin:6px 0 0;font-size:12px;color:#e0e7ff;\">{$time}</p>
    </div>
    <div style=\"padding:24px 26px;\">
      <p style=\"margin:0 0 6px;font-size:14px;\"><strong>Name:</strong> {$safeName}</p>
      <p style=\"margin:0 0 6px;font-size:14px;\"><strong>Email:</strong> <a href=\"mailto:{$safeEmail}\">{$safeEmail}</a></p>
      <p style=\"margin:0 0 14px;font-size:14px;\"><strong>Phone:</strong> {$safePhone}</p>
      <p style=\"margin:0 0 6px;font-size:14px;\"><strong>Subject:</strong> {$safeSubject}</p>
      <div style=\"background:#f3f4f6;border-radius:10px;padding:14px;font-size:14px;white-space:pre-wrap;\">{$safeMsg}</div>
      <p style=\"margin:14px 0 0;\"><a href=\"mailto:{$safeEmail}?subject=Re: {$safeSubject}\" style=\"display:inline-block;padding:10px 18px;background:#10b981;color:#fff;text-decoration:none;font-weight:700;font-size:13px;border-radius:8px;\">Reply to {$safeName} →</a></p>
    </div>
  </div>
</body>
</html>";
        $ownerText = "New enquiry from {$siteName}\nTime: {$time}\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\nSubject: {$subject}\n\n{$message}";

        $replyHtml = "
<!DOCTYPE html>
<html><head><meta charset=\"UTF-8\"></head>
<body style=\"margin:0;padding:0;background:#090d16;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;\">
  <div style=\"max-width:520px;margin:30px auto;background:#0f172a;border:1px solid #1e293b;border-radius:16px;overflow:hidden;\">
    <div style=\"background:linear-gradient(135deg,#059669,#10b981);padding:26px;text-align:center;\">
      <div style=\"font-size:34px;\">✅</div>
      <h1 style=\"margin:6px 0 0;font-size:18px;color:#fff;\">Message received!</h1>
    </div>
    <div style=\"padding:24px;\">
      <p style=\"color:#cbd5e1;font-size:13px;margin:0 0 10px;\">Hi {$safeName}, thanks for contacting <strong>{$safeSite}</strong>. We received your message and will reply to {$safeEmail} shortly.</p>
      <div style=\"background:#060a14;border:1px solid #1e293b;border-radius:10px;padding:14px;font-size:12px;color:#94a3b8;\">Subject: {$safeSubject}<br>Sent: {$time}</div>
    </div>
    <div style=\"background:#060a14;padding:12px;text-align:center;font-size:11px;color:#475569;\">&copy; " . date('Y') . " {$safeSite}</div>
  </div>
</body>
</html>";
        $replyText = "Hi {$name}, thanks for contacting {$siteName}. We received your message and will reply shortly.";
        return [
            'owner' => ['subject' => "New enquiry: {$subject} — {$name} ({$siteName})", 'html' => $ownerHtml, 'text' => $ownerText],
            'autoreply' => ['subject' => "We received your message — {$siteName}", 'html' => $replyHtml, 'text' => $replyText],
        ];
    }

    /**
     * Record notification in Supabase PostgreSQL notifications table
     */
    public static function recordNotificationInDb(string $orderId, string $type, string $subject, string $message, string $sentBy = 'system', bool $emailSent = false): bool {
        try {
            $db = getDb();
            if (!$db) return false;

            $stmt = $db->prepare("
                INSERT INTO notifications (order_id, type, subject, message, sent_by, email_sent, created_at)
                VALUES (:order_id, :type, :subject, :message, :sent_by, :email_sent, NOW())
            ");
            return $stmt->execute([
                ':order_id'   => $orderId,
                ':type'       => $type,
                ':subject'    => $subject,
                ':message'    => $message,
                ':sent_by'    => $sentBy,
                ':email_sent' => $emailSent ? 'true' : 'false',
            ]);
        } catch (Throwable $e) {
            error_log('recordNotificationInDb Error: ' . $e->getMessage());
            return false;
        }
    }
}
