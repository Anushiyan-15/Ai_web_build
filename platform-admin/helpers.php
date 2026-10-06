<?php
/**
 * Platform Admin - Shared Layout, RBAC & Utility Functions
 * Enterprise Edition v2.0
 */

require_once __DIR__ . '/roles.php';

// ──────────────────────────────────────────────
// STORAGE PATH HELPERS
// ──────────────────────────────────────────────

function storage_dir(): string {
    return dirname(__DIR__) . '/storage';
}

function orders_dir(): string {
    return storage_dir() . '/orders';
}

function notifications_dir(): string {
    return storage_dir() . '/notifications';
}

function designs_dir(): string {
    return storage_dir() . '/designs';
}

function submissions_dir(): string {
    return storage_dir() . '/submissions';
}

function published_root_dir(): string {
    return dirname(__DIR__) . '/published';
}

function audit_log_file(): string {
    $dir = storage_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/audit_log.json';
}

// ──────────────────────────────────────────────
// AUDIT LOGGING
// ──────────────────────────────────────────────

/**
 * Record an action to the audit trail (JSON file + MySQL if active)
 */
function log_admin_action(string $action, ?string $order_id = null, string $detail = ''): void {
    $admin_user = current_admin_user();
    $admin_role = current_admin_role();
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $time       = date('Y-m-d H:i:s');

    $entry = [
        'id'         => 'AUD-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4),
        'timestamp'  => $time,
        'admin_user' => $admin_user,
        'admin_role' => $admin_role,
        'action'     => $action,
        'order_id'   => $order_id ?: '—',
        'detail'     => $detail,
        'ip'         => $ip,
    ];

    // File-based append
    $file = audit_log_file();
    $logs = [];
    if (file_exists($file)) {
        $logs = json_decode(@file_get_contents($file), true) ?: [];
    }
    array_unshift($logs, $entry);
    // Keep last 1000 entries
    if (count($logs) > 1000) $logs = array_slice($logs, 0, 1000);
    @file_put_contents($file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Optional MySQL insert
    if (file_exists(dirname(__DIR__) . '/includes/db.php')) {
        try {
            require_once dirname(__DIR__) . '/includes/db.php';
            $pdo = function_exists('getDb') ? getDb() : null;
            if ($pdo) {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_activity_log (action, order_id, detail, admin_user, ip_address, created_at)
                    VALUES (:action, :order_id, :detail, :admin_user, :ip, NOW())
                ");
                $stmt->execute([
                    ':action'     => $action,
                    ':order_id'   => $order_id,
                    ':detail'     => $detail,
                    ':admin_user' => $admin_user,
                    ':ip'         => $ip
                ]);
            }
        } catch (Throwable $e) {}
    }
}

/**
 * Fetch audit logs
 */
function get_audit_logs(int $limit = 200): array {
    $file = audit_log_file();
    if (!file_exists($file)) return [];
    $logs = json_decode(@file_get_contents($file), true) ?: [];
    return array_slice($logs, 0, $limit);
}

// ──────────────────────────────────────────────
// ORDER & TENANT HELPERS
// ──────────────────────────────────────────────

function load_order(string $order_id): ?array {
    $file = orders_dir() . '/' . preg_replace('/[^A-Za-z0-9\-]/', '', $order_id) . '.json';
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return null;
    $data['site_active'] = $data['site_active'] ?? true;
    return $data;
}

function save_order(array $order): bool {
    $id   = preg_replace('/[^A-Za-z0-9\-]/', '', $order['order_id'] ?? '');
    if (!$id) return false;
    $dir  = orders_dir();
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return (bool) file_put_contents($dir . '/' . $id . '.json',
        json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function load_all_orders(): array {
    $dir = orders_dir();
    if (!is_dir($dir)) return [];

    $orders = [];
    foreach (glob($dir . '/*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $data['site_active'] = $data['site_active'] ?? true;
            $orders[] = $data;
        }
    }

    usort($orders, function($a, $b) {
        return strcmp($b['published_at'] ?? $b['created_at'] ?? '', $a['published_at'] ?? $a['created_at'] ?? '');
    });

    return $orders;
}

function subscription_status(array $order): string {
    if (!($order['site_active'] ?? true)) return 'deactivated';

    $published_at = $order['published_at'] ?? $order['created_at'] ?? '';
    if (!$published_at) return 'active';

    $published   = strtotime($published_at);
    $next_due    = $published + (30 * 86400);
    $now         = time();

    return ($now > $next_due) ? 'overdue' : 'active';
}

function next_due_date(array $order): string {
    $published_at = $order['published_at'] ?? $order['created_at'] ?? '';
    if (!$published_at) return 'N/A';
    return date('M d, Y', strtotime($published_at) + 30 * 86400);
}

function days_overdue(array $order): int {
    $published_at = $order['published_at'] ?? $order['created_at'] ?? '';
    if (!$published_at) return 0;
    $next_due = strtotime($published_at) + 30 * 86400;
    return (int) floor((time() - $next_due) / 86400);
}

// ──────────────────────────────────────────────
// SITE ACTIVATION / SUSPENSION HELPERS
// ──────────────────────────────────────────────

function deactivate_site(string $slug): array {
    $slug     = preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
    $base     = published_root_dir() . '/' . $slug;
    $live     = $base . '/index.html';
    $disabled = $base . '/index.html.disabled';

    if (!is_dir($base)) return ['ok' => false, 'msg' => 'Published directory not found.'];
    if (!file_exists($live)) return ['ok' => false, 'msg' => 'Live index.html not found.'];

    if (!rename($live, $disabled)) {
        return ['ok' => false, 'msg' => 'Could not rename index.html to .disabled.'];
    }

    $placeholder = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Website Suspended — Payment Required</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #0a0d14;
    color: #e2e8f0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 24px;
  }
  .box {
    background: #111622;
    border: 1px solid #1e293b;
    border-radius: 24px;
    padding: 56px 40px;
    max-width: 540px;
    box-shadow: 0 25px 60px rgba(0,0,0,.6);
  }
  .icon { font-size: 3.8rem; margin-bottom: 20px; }
  h1 { font-size: 1.7rem; font-weight: 800; color: #ef4444; margin-bottom: 12px; }
  p  { color: #94a3b8; font-size: 1rem; line-height: 1.7; margin-bottom: 24px; }
  .contact { padding: 14px 20px; background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); border-radius: 12px; color: #fca5a5; font-size: .9rem; font-weight: 600; }
</style>
</head>
<body>
<div class="box">
  <div class="icon">🚫</div>
  <h1>Website Temporarily Suspended</h1>
  <p>This website has been temporarily deactivated due to an overdue subscription.<br>Please renew your plan to restore full public access.</p>
  <div class="contact">📧 support@webcraft.ai &nbsp;·&nbsp; Reference slug: {$slug}</div>
</div>
</body>
</html>
HTML;

    file_put_contents($live, $placeholder);
    return ['ok' => true, 'msg' => 'Site deactivated successfully.'];
}

function activate_site(string $slug): array {
    $slug     = preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
    $base     = published_root_dir() . '/' . $slug;
    $live     = $base . '/index.html';
    $disabled = $base . '/index.html.disabled';

    if (!is_dir($base)) return ['ok' => false, 'msg' => 'Published directory not found.'];
    if (!file_exists($disabled)) return ['ok' => false, 'msg' => 'No .disabled backup found. Site may already be active.'];

    if (file_exists($live)) unlink($live);

    if (!rename($disabled, $live)) {
        return ['ok' => false, 'msg' => 'Could not restore index.html.'];
    }

    return ['ok' => true, 'msg' => 'Site activated successfully.'];
}

// ──────────────────────────────────────────────
// NOTIFICATIONS
// ──────────────────────────────────────────────

function save_notification(string $order_id, array $notif): bool {
    $order_id = preg_replace('/[^A-Za-z0-9\-]/', '', $order_id);
    $dir      = notifications_dir();
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $file   = $dir . '/' . $order_id . '.json';
    $notifs = [];
    if (file_exists($file)) {
        $existing = json_decode(file_get_contents($file), true);
        if (is_array($existing)) $notifs = $existing;
    }

    $notif['sent_at'] = date('Y-m-d H:i:s');
    $notif['sent_by'] = current_admin_user() . ' (' . current_admin_role() . ')';
    $notifs[]         = $notif;

    return (bool) file_put_contents($file, json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function load_notifications(string $order_id): array {
    $order_id = preg_replace('/[^A-Za-z0-9\-]/', '', $order_id);
    $file     = notifications_dir() . '/' . $order_id . '.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? array_reverse($data) : [];
}

function get_all_notifications_sent(int $limit = 50): array {
    $dir = notifications_dir();
    if (!is_dir($dir)) return [];
    $all = [];
    foreach (glob($dir . '/*.json') as $f) {
        $oid = basename($f, '.json');
        $items = json_decode(@file_get_contents($f), true);
        if (is_array($items)) {
            foreach ($items as $item) {
                $item['order_id'] = $oid;
                $all[] = $item;
            }
        }
    }
    usort($all, fn($a, $b) => strcmp($b['sent_at'] ?? '', $a['sent_at'] ?? ''));
    return array_slice($all, 0, $limit);
}

// ──────────────────────────────────────────────
// DEPLOYMENTS & ROLLBACK
// ──────────────────────────────────────────────

function get_deployment_history(): array {
    $orders = load_all_orders();
    $deployments = [];

    foreach ($orders as $order) {
        $slug = $order['slug'] ?? '';
        $oid  = $order['order_id'] ?? '';
        $pubDir = published_root_dir() . '/' . $slug;

        $hasLiveHtml = file_exists($pubDir . '/index.html');
        $htmlSize    = $hasLiveHtml ? filesize($pubDir . '/index.html') : 0;
        $isSuspended = file_exists($pubDir . '/index.html.disabled');

        // Check storage snapshots for rollback availability
        $snapshotFiles = glob(designs_dir() . '/*' . $oid . '*.html') ?: [];
        $snapshots = [];
        foreach ($snapshotFiles as $sf) {
            $snapshots[] = [
                'file'  => basename($sf),
                'size'  => filesize($sf),
                'mtime' => date('Y-m-d H:i:s', filemtime($sf)),
            ];
        }

        $deployments[] = [
            'order_id'       => $oid,
            'site_name'      => $order['site_name'] ?? 'Website',
            'slug'           => $slug,
            'package'        => $order['package'] ?? 'pro',
            'gen_mode'       => $order['gen_mode'] ?? 'static',
            'published_at'   => $order['published_at'] ?? $order['created_at'] ?? 'Unknown',
            'live_url'       => $order['live_url'] ?? '',
            'has_admin'      => !empty($order['admin_url']),
            'admin_url'      => $order['admin_url'] ?? '',
            'status'         => $isSuspended ? 'suspended' : ($hasLiveHtml ? 'live' : 'failed'),
            'html_size'      => $htmlSize,
            'snapshots'      => $snapshots,
        ];
    }

    return $deployments;
}

function rollback_site_to_snapshot(string $slug, string $snapshotFile): array {
    $slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
    $snap = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $snapshotFile);
    $snapPath = designs_dir() . '/' . $snap;

    if (!file_exists($snapPath)) {
        return ['ok' => false, 'msg' => 'Snapshot file not found in storage/designs/'];
    }

    $pubDir = published_root_dir() . '/' . $slug;
    if (!is_dir($pubDir)) {
        return ['ok' => false, 'msg' => 'Published website directory does not exist'];
    }

    $targetHtml = $pubDir . '/index.html';
    // Create pre-rollback backup
    $preRollbackBackup = $pubDir . '/index.html.pre-rollback-' . date('Ymd-His');
    if (file_exists($targetHtml)) {
        @copy($targetHtml, $preRollbackBackup);
    }

    $content = file_get_contents($snapPath);
    if (file_put_contents($targetHtml, $content) === false) {
        return ['ok' => false, 'msg' => 'Failed to write restored HTML to published directory'];
    }

    log_admin_action('site_rollback', $slug, "Rolled back site '{$slug}' to snapshot '{$snap}'");
    return ['ok' => true, 'msg' => "Website '{$slug}' successfully rolled back to snapshot {$snap}!"];
}

// ──────────────────────────────────────────────
// DOMAIN & SSL TRACKER
// ──────────────────────────────────────────────

function get_domain_ssl_records(): array {
    $orders = load_all_orders();
    $records = [];

    foreach ($orders as $order) {
        $slug      = $order['slug'] ?? '';
        $published = strtotime($order['published_at'] ?? $order['created_at'] ?? 'now');
        $siteUrl   = defined('SITE_URL') ? SITE_URL : 'http://localhost/project/webbbuilder';
        $domain    = parse_url($siteUrl, PHP_URL_HOST) . "/published/" . $slug;

        // Calculate simulated 90-day SSL cycle
        $sslExpireTimestamp = $published + (90 * 86400);
        $daysToSslExpiry    = (int) floor(($sslExpireTimestamp - time()) / 86400);

        // Domain 365-day cycle
        $domainExpireTimestamp = $published + (365 * 86400);
        $daysToDomainExpiry    = (int) floor(($domainExpireTimestamp - time()) / 86400);

        $sslStatus = 'valid';
        if ($daysToSslExpiry < 0) $sslStatus = 'expired';
        elseif ($daysToSslExpiry <= 14) $sslStatus = 'expiring_soon';

        $records[] = [
            'order_id'       => $order['order_id'] ?? '',
            'site_name'      => $order['site_name'] ?? '',
            'slug'           => $slug,
            'client_email'   => $order['admin_email'] ?: ($order['client_email'] ?? '—'),
            'domain'         => $domain,
            'custom_domain'  => $order['custom_domain'] ?? null,
            'ssl_provider'   => 'Let\'s Encrypt / Auto-SSL',
            'ssl_status'     => $sslStatus,
            'ssl_days_left'  => $daysToSslExpiry,
            'ssl_expiry'     => date('M d, Y', $sslExpireTimestamp),
            'domain_days'    => $daysToDomainExpiry,
            'domain_expiry'  => date('M d, Y', $domainExpireTimestamp),
            'is_live'        => $order['site_active'] ?? true,
        ];
    }

    return $records;
}

// ──────────────────────────────────────────────
// CUSTOMER ACCOUNTS (registered signups + guests)
// ──────────────────────────────────────────────

/**
 * All registered customer accounts with project counts.
 * Uses includes/db.php loadAllCustomers() (Supabase + local fallback).
 */
function load_all_customers(): array {
    if (file_exists(dirname(__DIR__) . '/includes/db.php')) {
        require_once dirname(__DIR__) . '/includes/db.php';
        if (function_exists('loadAllCustomers')) {
            try {
                return loadAllCustomers();
            } catch (Throwable $e) {}
        }
    }
    // Fallback: distinct emails from local order files
    $seen = [];
    foreach (load_all_orders() as $o) {
        $em = strtolower(trim($o['admin_email'] ?? ($o['client_email'] ?? '')));
        if (!$em || isset($seen[$em])) continue;
        $seen[$em] = [
            'id' => 'guest-' . substr(md5($em), 0, 8),
            'name' => $o['admin_username'] ?? '',
            'email' => $em,
            'phone' => '',
            'projects' => 0,
            'last_login_at' => null,
            'created_at' => $o['created_at'] ?? null,
            'source' => 'guest',
        ];
    }
    foreach (load_all_orders() as $o) {
        $em = strtolower(trim($o['admin_email'] ?? ($o['client_email'] ?? '')));
        if ($em && isset($seen[$em])) $seen[$em]['projects']++;
    }
    $list = array_values($seen);
    usort($list, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $list;
}

// ──────────────────────────────────────────────
// AI USAGE & TOKEN TRACKER
// ──────────────────────────────────────────────

function get_ai_usage_stats(): array {
    $orders = load_all_orders();
    $totalGenerations = 0;
    $totalTokens      = 0;
    $totalEstimatedCost = 0.0;
    $tenantUsage = [];

    foreach ($orders as $order) {
        $oid      = $order['order_id'] ?? '';
        $siteName = $order['site_name'] ?? 'Website';
        $genMode  = $order['gen_mode'] ?? 'static';

        // Count base generation + CoPilot + feature additions
        $featureCount = count($order['feature_additions'] ?? []);
        $generations  = 3 + ($featureCount * 2); // 3 concepts initially + revisions
        $adminFileCount = count($order['admin_files'] ?? []);
        if ($adminFileCount > 0) $generations += 2;

        // Estimated tokens per site
        $estTokens = ($generations * 4200) + ($adminFileCount * 1800);
        // Cost estimation ($0.10 / 1M tokens for Gemini 2.0 Flash)
        $cost = ($estTokens / 1000000) * 0.10;

        $totalGenerations += $generations;
        $totalTokens      += $estTokens;
        $totalEstimatedCost += $cost;

        $tenantUsage[] = [
            'order_id'       => $oid,
            'site_name'      => $siteName,
            'client_email'   => $order['admin_email'] ?: ($order['client_email'] ?? '—'),
            'model'          => defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-2.0-flash',
            'generations'    => $generations,
            'admin_files'    => $adminFileCount,
            'feature_addons' => $featureCount,
            'tokens'         => $estTokens,
            'cost_usd'       => $cost,
            'tier_limit'     => ($order['package'] === 'business') ? 100000 : (($order['package'] === 'pro') ? 50000 : 20000),
        ];
    }

    return [
        'total_generations' => $totalGenerations,
        'total_tokens'      => $totalTokens,
        'total_cost_usd'    => $totalEstimatedCost,
        'tenants'           => $tenantUsage,
    ];
}

// ──────────────────────────────────────────────
// SUPPORT TICKETS / INQUIRIES
// ──────────────────────────────────────────────

function get_support_tickets(): array {
    $tickets = [];

    // Check storage/submissions/
    $subDir = submissions_dir();
    if (is_dir($subDir)) {
        foreach (glob($subDir . '/*.json') as $f) {
            $data = json_decode(@file_get_contents($f), true);
            if (is_array($data)) {
                $tickets[] = [
                    'id'         => $data['id'] ?? basename($f, '.json'),
                    'name'       => $data['name'] ?? 'Anonymous',
                    'email'      => $data['email'] ?? '—',
                    'subject'    => $data['subject'] ?? 'Website Inquiry',
                    'message'    => $data['message'] ?? '',
                    'status'     => $data['status'] ?? 'open',
                    'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s', filemtime($f)),
                    'replies'    => $data['replies'] ?? [],
                ];
            }
        }
    }

    // Check MySQL contact_messages if exists
    if (file_exists(dirname(__DIR__) . '/includes/db.php')) {
        try {
            require_once dirname(__DIR__) . '/includes/db.php';
            $pdo = function_exists('getDb') ? getDb() : null;
            if ($pdo) {
                $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY id DESC LIMIT 50");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                foreach ($rows as $row) {
                    $tickets[] = [
                        'id'         => 'MSG-' . $row['id'],
                        'name'       => $row['name'] ?? 'Contact Form User',
                        'email'      => $row['email'] ?? '—',
                        'subject'    => 'Inquiry via Form',
                        'message'    => $row['message'] ?? '',
                        'status'     => !empty($row['replied']) ? 'resolved' : 'open',
                        'created_at' => $row['created_at'] ?? 'Recently',
                        'replies'    => [],
                    ];
                }
            }
        } catch (Throwable $e) {}
    }

    usort($tickets, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $tickets;
}

function update_ticket_status(string $ticketId, string $status, ?string $reply = null): bool {
    $subDir = submissions_dir();
    $file   = $subDir . '/' . preg_replace('/[^a-zA-Z0-9\-_]/', '', $ticketId) . '.json';
    if (!file_exists($file)) return false;

    $data = json_decode(@file_get_contents($file), true) ?: [];
    $data['status'] = $status;
    if ($reply) {
        if (!isset($data['replies'])) $data['replies'] = [];
        $data['replies'][] = [
            'by'   => current_admin_user(),
            'msg'  => $reply,
            'time' => date('Y-m-d H:i:s'),
        ];
    }
    return (bool) @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// ──────────────────────────────────────────────
// VISUAL UI BADGES & HELPERS
// ──────────────────────────────────────────────

function status_badge(string $status): string {
    $map = [
        'active'      => ['label' => 'Active',      'bg' => 'rgba(16,185,129,.15)',  'color' => '#10b981', 'dot' => '#10b981'],
        'overdue'     => ['label' => 'Overdue',     'bg' => 'rgba(245,158,11,.15)',  'color' => '#f59e0b', 'dot' => '#f59e0b'],
        'deactivated' => ['label' => 'Deactivated', 'bg' => 'rgba(239,68,68,.15)',   'color' => '#ef4444', 'dot' => '#ef4444'],
    ];
    $s = $map[$status] ?? $map['active'];
    return sprintf(
        '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:600;background:%s;color:%s;">
            <span style="width:7px;height:7px;border-radius:50%%;background:%s;display:inline-block;"></span>%s
        </span>',
        $s['bg'], $s['color'], $s['dot'], $s['label']
    );
}

function package_badge(string $pkg): string {
    $map = [
        'starter'  => ['color' => '#94a3b8', 'bg' => 'rgba(100,116,139,.18)'],
        'pro'      => ['color' => '#818cf8', 'bg' => 'rgba(99,102,241,.18)'],
        'business' => ['color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.18)'],
    ];
    $p = $map[strtolower($pkg)] ?? $map['starter'];
    return sprintf(
        '<span style="padding:4px 10px;border-radius:999px;font-size:.75rem;font-weight:700;background:%s;color:%s;text-transform:capitalize;">%s</span>',
        $p['bg'], $p['color'], htmlspecialchars(ucfirst($pkg))
    );
}

// ──────────────────────────────────────────────
// SIDEBAR & NAVIGATION WITH RBAC PERMISSION GATES
// ──────────────────────────────────────────────

function render_sidebar(string $active = 'dashboard'): void {
    $admin_user = htmlspecialchars($_SESSION['platform_admin_user'] ?? 'superadmin');
    $admin_name = htmlspecialchars($_SESSION['platform_admin_name'] ?? 'Super Admin');
    $admin_role = current_admin_role();
    $role_badge = role_badge($admin_role);

    // Navigation item definitions categorized with permission gates
    $navSections = [
        'Core Management' => [
            'dashboard' => ['href' => 'index.php',    'perm' => 'dashboard',    'icon' => 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',                                         'label' => 'Dashboard'],
            'tenants'   => ['href' => 'tenants.php',  'perm' => 'tenants_view', 'icon' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'label' => 'User & Tenants'],
        ],
        'Finance & Plans' => [
            'plans'     => ['href' => 'plans.php',    'perm' => 'billing',      'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'label' => '💳 Plans & Pricing'],
            'billing'   => ['href' => 'billing.php',  'perm' => 'billing',      'icon' => 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',                             'label' => 'Subscriptions'],
        ],
        'Infrastructure' => [
            'deployments' => ['href' => 'deployments.php', 'perm' => 'deployments', 'icon' => 'M22 12h-4l-3 9L9 3l-3 9H2',                                                          'label' => 'Deployments & Rollback'],
            'domains'     => ['href' => 'domains.php',     'perm' => 'domains_ssl', 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',                                         'label' => 'Domains & SSL'],
            'ai_usage'    => ['href' => 'ai-usage.php',    'perm' => 'ai_usage',    'icon' => 'M13 2L3 14h9l-1 8 10-12h-9l1-8z',                                                      'label' => 'AI Tokens & Cost'],
        ],
        'Support & CRM' => [
            'tickets'       => ['href' => 'tickets.php',       'perm' => 'tickets',            'icon' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',                   'label' => 'Support Tickets'],
            'notifications' => ['href' => 'notifications.php', 'perm' => 'notifications_view', 'icon' => 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0',             'label' => 'Notification Hub'],
            'send_email'    => ['href' => 'send-email.php',    'perm' => 'notifications_send',  'icon' => 'M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zM22 6l-10 7L2 6', 'label' => 'Send Email to User'],
            'customers'     => ['href' => 'customers.php',     'perm' => 'notifications_view',  'icon' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'label' => 'Customers & Accounts'],
        ],
        'Security & Team' => [
            'audit' => ['href' => 'audit-logs.php', 'perm' => 'audit_logs', 'icon' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8', 'label' => 'Audit Trail'],
            'team'  => ['href' => 'team.php',       'perm' => '*',          'icon' => 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',                   'label' => 'Admin Team & Roles'],
        ],
    ];

    echo '<aside style="width:260px;min-height:100vh;background:#0d1117;border-right:1px solid #1e293b;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;">';
    
    // Header
    echo '<div style="padding:20px 18px 16px;border-bottom:1px solid #1e293b;">';
    echo '  <div style="display:flex;align-items:center;gap:10px;">';
    echo '    <div style="width:38px;height:38px;background:linear-gradient(135deg,#6366f1,#818cf8);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">';
    echo '      <svg width="20" height="20" fill="#fff" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>';
    echo '    </div>';
    echo '    <div style="min-width:0;flex:1;">';
    echo '      <div style="font-weight:800;font-size:.92rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">WebCraft Admin</div>';
    echo '      <div style="font-size:.72rem;color:#818cf8;font-weight:600;">Enterprise RBAC</div>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    // Current User Profile Chip
    echo '<div style="padding:12px 16px;background:rgba(17,22,34,0.7);border-bottom:1px solid #1e293b;">';
    echo '  <div style="font-size:.8rem;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $admin_name . '</div>';
    echo '  <div style="margin-top:4px;">' . $role_badge . '</div>';
    echo '</div>';

    // Navigation sections
    echo '<nav style="flex:1;overflow-y:auto;padding:14px 10px;">';
    foreach ($navSections as $secTitle => $items) {
        $hasVisibleItems = false;
        foreach ($items as $k => $item) {
            if (has_permission($item['perm'])) {
                $hasVisibleItems = true;
                break;
            }
        }
        if (!$hasVisibleItems) continue;

        echo '<div style="font-size:.68rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;padding:8px 8px 4px;margin-top:4px;">' . $secTitle . '</div>';
        foreach ($items as $key => $item) {
            if (!has_permission($item['perm'])) continue;

            $isActive = ($key === $active);
            $style = $isActive
                ? 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;text-decoration:none;background:rgba(99,102,241,.18);color:#818cf8;font-weight:700;font-size:.84rem;margin-bottom:2px;'
                : 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;text-decoration:none;color:#94a3b8;font-weight:500;font-size:.84rem;margin-bottom:2px;transition:all .15s;';
            
            echo '<a href="' . $item['href'] . '" style="' . $style . '" onmouseover="if(!this.style.background.includes(\'.18\')){this.style.background=\'rgba(99,102,241,.08)\';this.style.color=\'#fff\';}" onmouseout="if(!this.style.background.includes(\'.18\')){this.style.background=\'transparent\';this.style.color=\'#94a3b8\';}">';
            echo '  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' . $item['icon'] . '"/></svg>';
            echo '  <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $item['label'] . '</span>';
            if ($isActive) echo '<span style="width:6px;height:6px;border-radius:50%;background:#6366f1;flex-shrink:0;"></span>';
            echo '</a>';
        }
    }
    echo '</nav>';

    // Footer Logout
    echo '<div style="padding:14px;border-top:1px solid #1e293b;background:#090d14;">';
    echo '  <a href="login.php?action=logout" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:8px 12px;border-radius:8px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#f87171;text-decoration:none;font-size:.8rem;font-weight:700;transition:.15s;" onmouseover="this.style.background=\'rgba(239,68,68,0.2)\';" onmouseout="this.style.background=\'rgba(239,68,68,0.1)\';">';
    echo '    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sign Out (' . $admin_user . ')';
    echo '  </a>';
    echo '</div>';
    echo '</aside>';
}

function render_head(string $title = 'Platform Admin'): void {
    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title) . ' — WebCraft Enterprise Control</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">';
    echo '<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--bg:#0a0d14;--card:#111622;--border:#1e293b;--primary:#6366f1;--primary-hover:#4f46e5;--success:#10b981;--warning:#f59e0b;--danger:#ef4444;--text:#e2e8f0;--muted:#64748b;}
body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
.layout{display:flex;}
.main{margin-left:260px;flex:1;min-height:100vh;padding:28px 32px;}
.page-header{margin-bottom:24px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;}
.page-header h1{font-size:1.45rem;font-weight:800;color:#fff;letter-spacing:-.02em;}
.page-header p{color:var(--muted);font-size:.85rem;margin-top:3px;}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px 24px;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-primary:hover{background:var(--primary-hover);transform:translateY(-1px);}
.btn-success{background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);}
.btn-success:hover{background:rgba(16,185,129,.25);}
.btn-warning{background:rgba(245,158,11,.15);color:#f59e0b;border:1px solid rgba(245,158,11,.3);}
.btn-warning:hover{background:rgba(245,158,11,.25);}
.btn-danger{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3);}
.btn-danger:hover{background:rgba(239,68,68,.25);}
.btn-ghost{background:#0d1117;color:var(--text);border:1px solid var(--border);}
.btn-ghost:hover{background:#1e293b;border-color:var(--primary);}
.btn-sm{padding:5px 10px;font-size:.78rem;border-radius:7px;}
table{width:100%;border-collapse:collapse;}
thead th{padding:12px 14px;text-align:left;font-size:.74rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border);}
tbody td{padding:13px 14px;font-size:.85rem;border-bottom:1px solid rgba(30,41,59,.5);}
tbody tr:hover{background:rgba(255,255,255,.02);}
tbody tr:last-child td{border-bottom:none;}
.alert{padding:12px 16px;border-radius:10px;font-size:.85rem;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.alert-error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5;}
.alert-success{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;}
.alert-warning{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#fcd34d;}
input,select,textarea{background:#0d1117;border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:inherit;font-size:.88rem;padding:9px 12px;outline:none;transition:border-color .2s,box-shadow .2s;width:100%;}
input:focus,select:focus,textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,.2);}
label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;}
.form-group{margin-bottom:14px;}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;}
@media(max-width:1200px){.grid-4{grid-template-columns:repeat(2,1fr);}}
@media(max-width:768px){.grid-4,.grid-3,.grid-2{grid-template-columns:1fr;}}
.code-badge{font-family:"Fira Code",monospace;font-size:.78rem;background:#0d1117;padding:2px 6px;border-radius:5px;border:1px solid #1e293b;color:#a5b4fc;}
.alert{transition:opacity .5s ease;}
@keyframes paBootAutoHide{to{opacity:0;visibility:hidden;pointer-events:none;}}
</style>';
    echo '<link rel="stylesheet" href="../assets/css/loader-3d.css">';
    echo '<script src="../assets/js/loader-3d.js"></script>';
    echo '</head><body><div class="layout">';
    // Page-load overlay (every admin page) + auto-fading flashes + form loaders
    // ★ Contextual boot text: shows WHICH tab is loading
    $bootTitle = htmlspecialchars($title);
    echo '<div id="pa-boot" style="position:fixed;inset:0;z-index:99997;background:#0a0d14;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;animation:paBootAutoHide .5s ease 6s forwards;">'
       . '<div class="wcl-mini-house" style="margin:0;"><i class="walls"></i><i class="roof"></i><i class="door"></i></div>'
       . '<div style="color:#fff;font-weight:800;font-size:.95rem;">Loading ' . $bootTitle . '<span class="wcl-sub" style="display:inline;"></span></div>'
       . '</div>';
    echo '<script>'
       . 'window.addEventListener("load",function(){var b=document.getElementById("pa-boot");if(b)b.style.display="none";});'
       . 'setTimeout(function(){var b=document.getElementById("pa-boot");if(b)b.style.display="none";},5000);'
       . 'setTimeout(function(){document.querySelectorAll(".alert").forEach(function(a){a.style.opacity="0";setTimeout(function(){a.style.display="none";},500);});},5000);'
       // ★ Situation-based 3D scene per admin tab (sidebar navigation)
       . 'var PA_TAB_LOADERS={'
       . '"index.php":["Opening dashboard…","Loading command center","home"],'
       . '"tenants.php":["Opening tenants…","Loading tenant directory","home"],'
       . '"plans.php":["Opening plans…","Loading pricing plans","save"],'
       . '"billing.php":["Opening billing…","Loading subscriptions","db"],'
       . '"deployments.php":["Opening deployments…","Loading build history","rocket"],'
       . '"domains.php":["Opening domains…","Checking SSL status","lock"],'
       . '"ai-usage.php":["Opening AI usage…","Loading token stats","radar"],'
       . '"tickets.php":["Opening tickets…","Loading support inbox","mail"],'
       . '"notifications.php":["Opening notifications…","Loading broadcast hub","mail"],'
       . '"send-email.php":["Opening composer…","Preparing email editor","mail"],'
       . '"customers.php":["Opening customers…","Loading accounts","home"],'
       . '"customer-view.php":["Opening customer…","Loading account details","home"],'
       . '"audit-logs.php":["Opening audit trail…","Loading activity logs","radar"],'
       . '"team.php":["Opening team…","Loading roles","lock"]};'
       . 'document.addEventListener("click",function(e){'
       . 'var a=e.target&&e.target.closest?e.target.closest("aside a[href]"):null;'
       . 'if(!a||!a.getAttribute("href"))return;'
       . 'var href=a.getAttribute("href");'
       // logout → secure sign-out scene
       . 'if(href.indexOf("action=logout")!==-1){e.preventDefault();try{if(window.Loader3D)Loader3D.show("Signing you out…","Securing your session","lock");}catch(x){}setTimeout(function(){window.location.href=a.href;},950);return;}'
       . 'var file=href.split("/").pop().split("?")[0];'
       . 'var m=PA_TAB_LOADERS[file];'
       . 'if(!m||!window.Loader3D)return;'
       // active tab → no reload needed
       . 'if(a.style.background&&a.style.background.indexOf(".18")!==-1)return;'
       . 'e.preventDefault();'
       . 'try{Loader3D.show(m[0],m[1],m[2]);}catch(x){}'
       . 'setTimeout(function(){window.location.href=a.href;},700);'
       . '},true);'
       // ★ ALL POST forms get a contextual loader (data-wcl overrides)
       . 'document.addEventListener("submit",function(e){'
       . 'var f=e.target;if(!f||!f.dataset||!window.Loader3D)return;'
       . 'if(f.method&&f.method.toUpperCase()==="GET")return;'
       . 'if(f.dataset.wcl){Loader3D.show(f.dataset.wcl,"Please wait",f.dataset.wclType||"mail");return;}'
       . 'var act=(f.getAttribute("action")||location.pathname).split("/").pop().split("?")[0];'
       . 'var fm={"tickets.php":["Sending reply…","Delivering to customer","mail"],"deployments.php":["Starting rollback…","Restoring snapshot","rocket"],"billing.php":["Updating billing…","Syncing subscriptions","db"],"team.php":["Updating team…","Saving roles","lock"],"domains.php":["Updating domain…","Checking SSL","lock"],"plans.php":["Saving plans…","Updating pricing","save"],"ai-usage.php":["Resetting counters…","Clearing usage stats","radar"],"send-email.php":["Sending email…","Delivering message","mail"],"notifications.php":["Sending notifications…","Broadcasting alert","mail"],"customer-view.php":["Sending notification…","Notifying customer","mail"]};'
       . 'var mm=fm[act]||["Working…","Please wait","save"];'
       . 'try{Loader3D.show(mm[0],mm[1],mm[2]);}catch(x){}'
       . '},true);'
       // ★ Delayed fullscreen helper for quick AJAX calls (no flash if fast)
       . 'window.paAjaxLoad=function(msg,sub,scene){var t=setTimeout(function(){try{if(window.Loader3D)Loader3D.show(msg||"Working…",sub||"Please wait",scene||"radar");}catch(x){}},450);return function(){clearTimeout(t);try{if(window.Loader3D)Loader3D.hide();}catch(x){}};};'
       . '</script>';
}
