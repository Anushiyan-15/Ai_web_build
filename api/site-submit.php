<?php
// ═══════════════════════════════════════════════════════════════
//  api/site-submit.php — Studio Business-Blocks form receiver
//  Handles booking / newsletter / popup / contact-fallback submits
//  from published sites running assets/js/wc-business.js.
//  Stores into published/<slug>/admin/data_inquiries.json (same inbox
//  format the generated admin + site-manager read), emails the site
//  owner via Mailer, and optionally forwards to a Google Sheets
//  Apps-Script webhook supplied by the site (data-wc-sheet).
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';
require_once dirname(__DIR__) . '/includes/MailQueue.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true) ?: $_POST;

// Honeypot (bots fill it, humans don't)
if (!empty($req['company'])) {
    echo json_encode(['success' => true, 'id' => 'ok']);
    exit;
}

$slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($req['slug'] ?? ''));
$kind = preg_replace('/[^a-z_]/', '', (string)($req['kind'] ?? 'contact'));
if (!in_array($kind, ['contact', 'booking', 'newsletter', 'popup'], true)) $kind = 'contact';
$fields = is_array($req['fields'] ?? null) ? $req['fields'] : [];
$sheet  = trim((string)($req['sheet'] ?? ''));

$clean = [];
foreach ($fields as $k => $v) {
    $k = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$k);
    if ($k === '' || $k === 'company') continue;
    $clean[$k] = mb_substr(trim(strip_tags((string)$v)), 0, 2000);
}

$name  = $clean['name'] ?? ($clean['bk-name'] ?? ($clean['full-name'] ?? ''));
$email = $clean['email'] ?? ($clean['bk-email'] ?? '');
$phone = $clean['phone'] ?? ($clean['bk-phone'] ?? ($clean['tel'] ?? ''));

if ($kind === 'newsletter') {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Valid email address is required']);
        exit;
    }
} else {
    if ($kind === 'booking' && empty($name) && !empty($phone)) $name = $phone;
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Name is required']);
        exit;
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Valid email address is required']);
        exit;
    }
}

// Light throttle: 20 submits / hour / IP+slug
try {
    $hitDir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/submissions';
    if (!is_dir($hitDir)) @mkdir($hitDir, 0755, true);
    $hitFile = $hitDir . '/ratelimit.json';
    $hits = file_exists($hitFile) ? (json_decode(file_get_contents($hitFile), true) ?: []) : [];
    $hk = ($_SERVER['REMOTE_ADDR'] ?? 'x') . '|' . $slug;
    $now = time();
    $hits[$hk] = array_values(array_filter($hits[$hk] ?? [], fn($t) => ($now - $t) < 3600));
    if (count($hits[$hk]) >= 20) {
        echo json_encode(['success' => false, 'error' => 'Too many submissions. Try again later.']);
        exit;
    }
    $hits[$hk][] = $now;
    @file_put_contents($hitFile, json_encode($hits));
} catch (Throwable $e) {}

// Build inbox row (matches generated admin/data_inquiries.json shape + kind/fields)
$labels = ['contact' => 'Contact', 'booking' => 'Booking', 'newsletter' => 'Newsletter', 'popup' => 'Popup'];
$summaryBits = [];
foreach (['bk-date', 'date', 'bk-service', 'service', 'bk-time', 'time', 'message', 'bk-notes', 'subject'] as $k) {
    if (!empty($clean[$k])) $summaryBits[] = $k . ': ' . $clean[$k];
}
$row = [
    'id'      => time() . rand(100, 999),
    'name'    => $name !== '' ? $name : ($kind === 'newsletter' ? $email : 'Visitor'),
    'email'   => $email,
    'phone'   => $phone,
    'message' => ($labels[$kind] ?? 'Form') . ($summaryBits ? ' — ' . implode(' | ', $summaryBits) : ''),
    'date'    => date('Y-m-d H:i'),
    'read'    => false,
    'kind'    => $kind,
    'fields'  => $clean,
];

if ($slug !== '') {
    $inboxDir = dirname(__DIR__) . '/published/' . $slug . '/admin';
    if (!is_dir($inboxDir)) @mkdir($inboxDir, 0755, true);
    $inboxFile = $inboxDir . '/data_inquiries.json';
    try {
        $all = file_exists($inboxFile) ? (json_decode(file_get_contents($inboxFile), true) ?: []) : [];
        array_unshift($all, $row);
        $all = array_slice($all, 0, 500);
        @file_put_contents($inboxFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) {}
}

// Resolve owner email for this slug (order file scan)
$ownerEmail = '';
try {
    $ordersDir = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage') . '/orders';
    if ($slug !== '' && is_dir($ordersDir)) {
        foreach (glob($ordersDir . '/*.json') ?: [] as $f) {
            $o = json_decode(@file_get_contents($f), true);
            if (is_array($o) && (($o['slug'] ?? '') === $slug)) {
                $ownerEmail = $o['contact_email'] ?? ($o['admin_email'] ?? ($o['client_email'] ?? ''));
                break;
            }
        }
    }
} catch (Throwable $e) {}
if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    $ownerEmail = (defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '');
}

// Email the owner (queued, non-blocking)
$mailed = false;
if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    try {
        $subject = '📩 New ' . ($labels[$kind] ?? 'Form') . ' — ' . ($row['name'] ?: 'Website');
        $lines = [];
        foreach ($clean as $k => $v) {
            if ($v === '') continue;
            $lines[] = ucfirst(str_replace(['bk-', '-', '_'], ['', ' ', ' '], $k)) . ': ' . $v;
        }
        $text = implode("\n", $lines);
        $html = '<p>New <b>' . htmlspecialchars($labels[$kind] ?? 'form') . '</b> submission:</p><pre style="background:#f3f4f6;padding:12px;border-radius:8px;white-space:pre-wrap;">'
            . htmlspecialchars($text) . '</pre>';
        $r = queueMail(['to' => $ownerEmail, 'subject' => $subject, 'html' => $html, 'text' => $text, 'kind' => 'site_' . $kind]);
        $mailed = !empty($r['success']);
    } catch (Throwable $e) {}
}

// Optional Google Sheets webhook (site owner's own Apps Script URL, https only)
$sheetOk = null;
if ($sheet !== '' && stripos($sheet, 'https://') === 0) {
    try {
        $ch = curl_init($sheet);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['kind' => $kind, 'slug' => $slug, 'fields' => $clean, 'at' => date('c')]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $sheetOk = (curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400);
        curl_close($ch);
    } catch (Throwable $e) { $sheetOk = false; }
}

echo json_encode(['success' => true, 'id' => $row['id'], 'mailed' => $mailed, 'sheet' => $sheetOk]);
