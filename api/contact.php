<?php
// ═══════════════════════════════════════════════════════════════
//  api/contact.php — Contact Form & Inquiries API
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';
require_once dirname(__DIR__) . '/includes/MailQueue.php';

$raw  = file_get_contents('php://input');
$req  = json_decode($raw, true) ?: $_POST;

$name    = htmlspecialchars(strip_tags(trim($req['name'] ?? '')));
$email   = htmlspecialchars(strip_tags(trim($req['email'] ?? '')));
$subject = htmlspecialchars(strip_tags(trim($req['subject'] ?? 'Website Inquiry')));
$message = htmlspecialchars(strip_tags(trim($req['message'] ?? '')));

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Name is required']);
    exit;
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Valid email address is required']);
    exit;
}
if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// 1. Save to storage/submissions/
$subDir = STORAGE_DIR . '/submissions';
if (!is_dir($subDir)) {
    mkdir($subDir, 0755, true);
}

$submissionId = 'SUB-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
$entry = [
    'id'         => $submissionId,
    'name'       => $name,
    'email'      => $email,
    'subject'    => $subject,
    'message'    => $message,
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
    'created_at' => date('Y-m-d H:i:s'),
];

file_put_contents($subDir . '/' . $submissionId . '.json', json_encode($entry, JSON_PRETTY_PRINT));

// 2. Save to MySQL database if available
$pdo = getDb();
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO contact_messages (name, email, message, created_at)
            VALUES (:name, :email, :message, NOW())
        ");
        $stmt->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':message' => "Subject: $subject\n\n$message"
        ]);
    } catch (Exception $e) {
        error_log("DB contact insert failed: " . $e->getMessage());
    }
}

// 3. Email via PHPMailer queue (owner notification + visitor auto-reply)
// Raw mail() is unreliable on shared hosting — the queue delivers over
// SMTP with retries, so the business can actually contact the visitor.
$mailStatus = ['owner' => false, 'autoreply' => false];
try {
    $siteLabel = defined('SITE_NAME') ? SITE_NAME : 'Website';
    $payload = Mailer::buildContactPayload($siteLabel, $name, $email, '', $subject, $message);
    $ownerTo = (defined('CONTACT_EMAIL') && filter_var(CONTACT_EMAIL, FILTER_VALIDATE_EMAIL)) ? CONTACT_EMAIL : '';
    if ($ownerTo) {
        $r = queueMail([
            'to' => $ownerTo, 'subject' => $payload['owner']['subject'],
            'html' => $payload['owner']['html'], 'text' => $payload['owner']['text'],
            'kind' => 'contact',
        ]);
        $mailStatus['owner'] = !empty($r['success']);
    }
    $r2 = queueMail([
        'to' => $email, 'subject' => $payload['autoreply']['subject'],
        'html' => $payload['autoreply']['html'], 'text' => $payload['autoreply']['text'],
        'kind' => 'contact_autoreply',
    ]);
    $mailStatus['autoreply'] = !empty($r2['success']);
} catch (Throwable $e) {
    error_log('contact queueMail failed: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'message' => 'Thank you! Your message has been sent successfully.',
    'id'      => $submissionId,
    'mailed'  => $mailStatus,
]);
