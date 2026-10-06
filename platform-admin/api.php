<?php
/**
 * Platform Admin - AJAX API Endpoint (api.php)
 * Handles: toggle_site, get_stats, send_reminder
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/platform-admin.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Always return JSON
header('Content-Type: application/json; charset=utf-8');
// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

/**
 * Send a JSON response and exit.
 */
function api_respond(bool $success, string $message, array $data = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

$action = trim($_GET['action'] ?? '');

// ══════════════════════════════════════════
// ACTION: toggle_site
// ══════════════════════════════════════════
if ($action === 'toggle_site') {

    $order_id = trim($_GET['order_id'] ?? '');
    $status   = trim($_GET['status']   ?? ''); // 'activate' | 'deactivate'

    if (!$order_id)                                    api_respond(false, 'Missing order_id.');
    if (!in_array($status, ['activate','deactivate'])) api_respond(false, 'Invalid status. Use activate or deactivate.');

    $order = load_order($order_id);
    if (!$order) api_respond(false, 'Order not found.');

    $slug = $order['slug'] ?? '';
    if (!$slug) api_respond(false, 'Order has no slug.');

    if ($status === 'deactivate') {
        $result = deactivate_site($slug);
        if (!$result['ok']) api_respond(false, $result['msg']);

        $order['site_active'] = false;
        save_order($order);

        // Log the event as a notification
        save_notification($order_id, [
            'type'    => 'warning',
            'message' => 'Site deactivated by platform admin: ' . ($_SESSION['platform_admin_user'] ?? 'admin'),
            'sent_by' => 'system',
        ]);

        // Email the customer via background queue (never blocks toggle)
        $emailMsg = 'Your website "' . ($order['site_name'] ?? '') . '" has been temporarily deactivated (subscription overdue). Renew to restore access.';
        $emailSent = false;
        $emailTo = filter_var($order['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);
        if ($emailTo) {
            require_once dirname(__DIR__) . '/includes/Mailer.php';
            require_once dirname(__DIR__) . '/includes/MailQueue.php';
            $sr = queueNotificationEmail($order_id, $emailTo, 'Website Deactivated — WebCraft AI', $emailMsg, 'warning', $order, 'system');
            $emailSent = !empty($sr['success']);
        }

        api_respond(true, 'Site deactivated successfully. Suspension page is now live.' . ($emailSent ? ' Customer email queued.' : ''), [
            'order_id'    => $order_id,
            'site_active' => false,
        ]);

    } else { // activate
        $result = activate_site($slug);
        if (!$result['ok']) api_respond(false, $result['msg']);

        $order['site_active'] = true;
        save_order($order);

        // Log the activation
        save_notification($order_id, [
            'type'    => 'info',
            'message' => 'Site reactivated by platform admin: ' . ($_SESSION['platform_admin_user'] ?? 'admin'),
            'sent_by' => 'system',
        ]);

        // Email the customer via background queue (never blocks toggle)
        $emailMsg = 'Good news! Your website "' . ($order['site_name'] ?? '') . '" has been reactivated and is live again.';
        $emailSent = false;
        $emailTo = filter_var($order['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);
        if ($emailTo) {
            require_once dirname(__DIR__) . '/includes/Mailer.php';
            require_once dirname(__DIR__) . '/includes/MailQueue.php';
            $sr = queueNotificationEmail($order_id, $emailTo, 'Website Reactivated — WebCraft AI', $emailMsg, 'info', $order, 'system');
            $emailSent = !empty($sr['success']);
        }

        api_respond(true, 'Site activated successfully. Original website is restored.' . ($emailSent ? ' Customer email queued.' : ''), [
            'order_id'    => $order_id,
            'site_active' => true,
        ]);
    }
}

// ══════════════════════════════════════════
// ACTION: get_stats
// ══════════════════════════════════════════
if ($action === 'get_stats') {

    $all      = load_all_orders();
    $total    = count($all);
    $active   = 0; $overdue = 0; $deact = 0;

    foreach ($all as $o) {
        $s = subscription_status($o);
        if ($s === 'active')      $active++;
        elseif ($s === 'overdue')     $overdue++;
        elseif ($s === 'deactivated') $deact++;
    }

    api_respond(true, 'Stats loaded.', [
        'total'       => $total,
        'active'      => $active,
        'overdue'     => $overdue,
        'deactivated' => $deact,
        'generated_at'=> date('Y-m-d H:i:s'),
    ]);
}

// ══════════════════════════════════════════
// ACTION: send_reminder
// ══════════════════════════════════════════
if ($action === 'send_reminder') {

    $order_id = trim($_GET['order_id'] ?? '');
    if (!$order_id) api_respond(false, 'Missing order_id.');

    $order = load_order($order_id);
    if (!$order) api_respond(false, 'Order not found.');

    $status   = subscription_status($order);
    $due_date = next_due_date($order);
    $days_od  = days_overdue($order);

    $site_name = $order['site_name'] ?? 'your website';
    $slug      = $order['slug']      ?? '';

    if ($days_od > 0) {
        $message = "Dear customer,\n\nYour website \"{$site_name}\" subscription payment is {$days_od} day(s) overdue. "
                 . "Next payment was due on {$due_date}. "
                 . "Please make a payment as soon as possible to avoid site suspension.\n\n"
                 . "If payment is not received within 3 days, your site will be temporarily suspended.\n\n"
                 . "Thank you for your continued trust in WebCraft AI Builder.";
    } else {
        $days_left = abs($days_od);
        $message   = "Dear customer,\n\nThis is a friendly reminder that your \"{$site_name}\" subscription "
                   . "renewal is due on {$due_date} ({$days_left} day(s) from today). "
                   . "Please ensure your payment method is up to date to avoid any interruption to your service.\n\n"
                   . "Thank you for using WebCraft AI Builder!";
    }

    // Save the notification
    $notif = [
        'type'    => 'payment_reminder',
        'message' => $message,
        'sent_by' => 'platform_admin',
    ];
    save_notification($order_id, $notif);

    // Send email via background queue (never blocks the response)
    $email_sent = false;
    $email_addr = filter_var($order['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);
    if ($email_addr) {
        $subject = 'Payment Reminder — WebCraft AI';
        require_once dirname(__DIR__) . '/includes/Mailer.php';
        require_once dirname(__DIR__) . '/includes/MailQueue.php';
        $sendRes = queueNotificationEmail($order_id, $email_addr, $subject, $message, 'payment_reminder', $order, 'platform_admin');
        $email_sent = !empty($sendRes['success']);
    }

    api_respond(true,
        'Payment reminder saved' . ($email_sent ? ' and email queued for background delivery' : ' (portal note only)') . '.',
        [
            'order_id'   => $order_id,
            'email_sent' => $email_sent,
            'status'     => $status,
            'days_overdue' => $days_od,
        ]
    );
}

// ══════════════════════════════════════════
// ACTION: bulk_send_reminders
// ══════════════════════════════════════════
if ($action === 'bulk_send_reminders') {
    $all     = load_all_orders();
    $sent    = 0;
    $errors  = [];
    require_once dirname(__DIR__) . '/includes/Mailer.php';
    require_once dirname(__DIR__) . '/includes/MailQueue.php';

    foreach ($all as $o) {
        $s = subscription_status($o);
        if ($s !== 'overdue') continue;

        $oid      = $o['order_id'] ?? '';
        $due_date = next_due_date($o);
        $days_od  = days_overdue($o);
        $sname    = $o['site_name'] ?? 'your website';
        $message  = "Your website \"{$sname}\" subscription payment is {$days_od} day(s) overdue "
                  . "(was due {$due_date}). Please pay immediately to avoid suspension.";

        $saved = save_notification($oid, [
            'type'    => 'payment_reminder',
            'message' => $message,
            'sent_by' => 'bulk_system',
        ]);

        $email_addr = filter_var($o['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);
        if ($email_addr) {
            queueNotificationEmail($oid, $email_addr, 'Payment Reminder — WebCraft AI', $message, 'payment_reminder', $o, 'bulk_system');
        }

        if ($saved) $sent++;
        else $errors[] = $oid;
    }

    api_respond(true, "Bulk reminders saved; {$sent} email(s) queued for background delivery.", [
        'sent'   => $sent,
        'errors' => $errors,
    ]);
}

// ══════════════════════════════════════════
// ACTION: get_snapshots (for Rollback)
// ══════════════════════════════════════════
if ($action === 'get_snapshots') {
    $order_id = preg_replace('/[^A-Za-z0-9\-]/', '', trim($_GET['order_id'] ?? ''));
    $order    = load_order($order_id);
    $slug     = $order['slug'] ?? '';

    $snapshots = [];
    $dir = designs_dir();
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.html') as $file) {
            $fname = basename($file);
            if ($order_id && strpos($fname, $order_id) !== false) {
                $snapshots[] = [
                    'filename' => $fname,
                    'size_kb'  => round(filesize($file) / 1024, 1),
                    'mtime'    => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }
    }

    // Also check published backup files
    if ($slug) {
        $pubDir = published_root_dir() . '/' . $slug;
        foreach (glob($pubDir . '/index.html.backup*') as $bf) {
            $snapshots[] = [
                'filename' => '../published/' . $slug . '/' . basename($bf),
                'size_kb'  => round(filesize($bf) / 1024, 1),
                'mtime'    => date('Y-m-d H:i:s', filemtime($bf)),
            ];
        }
    }

    api_respond(true, 'Snapshots loaded', ['snapshots' => $snapshots]);
}

// Unknown action
api_respond(false, 'Unknown action: ' . htmlspecialchars($action));
