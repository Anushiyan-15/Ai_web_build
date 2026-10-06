<?php
/**
 * WEBbuilder.lk – Backend API Handler
 * Handles form submissions, order saving, and email notifications
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ─── Config ──────────────────────────────────────────────
require_once __DIR__ . '/config.php';
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'info@webbuilder.lk');
if (!defined('SMTP_FROM'))   define('SMTP_FROM', 'noreply@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'));


// ─── Router ──────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'save_requirement':
        handleSaveRequirement();
        break;
    case 'save_order':
        handleSaveOrder();
        break;
    case 'get_order_status':
        handleGetOrderStatus();
        break;
    case 'send_feedback':
        handleSendFeedback();
        break;
    case 'contact':
        handleContact();
        break;
    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}

// ═══════════════════════════════════════════════════════════
// HANDLERS
// ═══════════════════════════════════════════════════════════

/**
 * Save client requirement to DB
 */
function handleSaveRequirement() {
    $data = getJsonInput();

    $requirement = sanitize($data['requirement'] ?? '');
    $businessType = sanitize($data['businessType'] ?? '');
    $colorTheme = sanitize($data['colorTheme'] ?? 'blue');
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

    if (empty($requirement) || strlen($requirement) < 10) {
        jsonResponse(['error' => 'Requirement text is too short'], 400);
        return;
    }

    // Save to DB (optional, gracefully skip if DB not configured)
    $orderId = 'WBL-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

    $saved = saveToDb([
        'table' => 'requirements',
        'data' => [
            'order_id' => $orderId,
            'requirement' => $requirement,
            'business_type' => $businessType,
            'color_theme' => $colorTheme,
            'ip_address' => $clientIp,
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ]
    ]);

    jsonResponse([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Requirement saved successfully',
        'db_saved' => $saved
    ]);
}

/**
 * Save order to DB and send notification email
 */
function handleSaveOrder() {
    $data = getJsonInput();

    $orderId = sanitize($data['orderId'] ?? ('WBL-' . date('Y') . '-' . rand(1000,9999)));
    $siteName = sanitize($data['siteName'] ?? 'Unknown');
    $designId = (int)($data['designId'] ?? 1);
    $paymentMethod = sanitize($data['paymentMethod'] ?? 'unknown');
    $requirement = sanitize($data['requirement'] ?? '');
    $whatsapp = (bool)($data['whatsapp'] ?? false);
    $colorTheme = sanitize($data['colorTheme'] ?? 'blue');
    $clientEmail = sanitize($data['clientEmail'] ?? '');
    $clientPhone = sanitize($data['clientPhone'] ?? '');

    // Save order to DB
    $saved = saveToDb([
        'table' => 'orders',
        'data' => [
            'order_id' => $orderId,
            'site_name' => $siteName,
            'design_id' => $designId,
            'payment_method' => $paymentMethod,
            'requirement' => $requirement,
            'whatsapp_enabled' => $whatsapp ? 1 : 0,
            'color_theme' => $colorTheme,
            'client_email' => $clientEmail,
            'client_phone' => $clientPhone,
            'status' => 'received',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

    // Send admin notification email
    sendAdminNotification([
        'order_id' => $orderId,
        'site_name' => $siteName,
        'design_id' => $designId,
        'payment_method' => $paymentMethod,
        'requirement' => $requirement,
        'client_email' => $clientEmail,
        'client_phone' => $clientPhone
    ]);

    // Send client confirmation email if email provided
    if (!empty($clientEmail)) {
        sendClientConfirmation($clientEmail, $siteName, $orderId);
    }

    jsonResponse([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Order placed successfully',
        'estimated_days' => 5
    ]);
}

/**
 * Get order status
 */
function handleGetOrderStatus() {
    $orderId = sanitize($_GET['order_id'] ?? '');

    if (empty($orderId)) {
        jsonResponse(['error' => 'Order ID required'], 400);
        return;
    }

    // Try to get from DB, return mock data if DB not available
    $order = getFromDb('orders', 'order_id', $orderId);

    if (!$order) {
        // Return mock status for demo
        $order = [
            'order_id' => $orderId,
            'status' => 'in_progress',
            'progress' => [
                'requirement_received' => true,
                'designs_generated' => true,
                'client_approved' => true,
                'payment_completed' => true,
                'development_started' => true,
                'published' => false
            ],
            'estimated_completion' => date('Y-m-d', strtotime('+5 days')),
            'developer_assigned' => true
        ];
    }

    jsonResponse(['success' => true, 'order' => $order]);
}

/**
 * Handle chat feedback
 */
function handleSendFeedback() {
    $data = getJsonInput();

    $orderId = sanitize($data['orderId'] ?? '');
    $feedback = sanitize($data['feedback'] ?? '');

    if (empty($feedback)) {
        jsonResponse(['error' => 'Feedback is empty'], 400);
        return;
    }

    // Process feedback with basic AI response logic
    $response = generateAIResponse($feedback);

    // Save feedback to DB
    saveToDb([
        'table' => 'feedback',
        'data' => [
            'order_id' => $orderId,
            'feedback' => $feedback,
            'ai_response' => $response,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

    jsonResponse([
        'success' => true,
        'response' => $response
    ]);
}

/**
 * Handle contact form
 */
function handleContact() {
    $data = getJsonInput();

    $name = sanitize($data['name'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $phone = sanitize($data['phone'] ?? '');
    $message = sanitize($data['message'] ?? '');

    if (empty($name) || empty($message)) {
        jsonResponse(['error' => 'Name and message are required'], 400);
        return;
    }

    // Send email
    $subject = "Contact Form: WEBbuilder.lk – {$name}";
    $body = "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\n\nMessage:\n{$message}";
    $sent = mail(ADMIN_EMAIL, $subject, $body, "From: " . SMTP_FROM);

    jsonResponse([
        'success' => true,
        'message' => 'Message sent. We will get back to you within 24 hours.'
    ]);
}

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function getJsonInput(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $_POST;
    }
    return $data ?? [];
}

function sanitize(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

function generateAIResponse(string $feedback): string {
    $lower = strtolower($feedback);

    if (str_contains($lower, 'color') || str_contains($lower, 'blue') || str_contains($lower, 'red')) {
        return 'Color scheme updated based on your preference!';
    }
    if (str_contains($lower, 'whatsapp') || str_contains($lower, 'wp')) {
        return 'WhatsApp button has been added to the design!';
    }
    if (str_contains($lower, 'logo')) {
        return 'Logo placeholder updated. You can send us your actual logo file.';
    }
    if (str_contains($lower, 'modern') || str_contains($lower, 'clean')) {
        return 'Design refreshed with a modern, clean aesthetic!';
    }
    return 'Got it! Design updated based on your feedback. The changes will be reflected in your final website.';
}

// ─── DB Helpers ──────────────────────────────────────────

function getDbConnection(): ?PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        // DB not available – gracefully continue
        return null;
    }
}

function saveToDb(array $config): bool {
    $pdo = getDbConnection();
    if (!$pdo) return false;

    try {
        $table = $config['table'];
        $data = $config['data'];
        $cols = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$placeholders})");
        return $stmt->execute($data);
    } catch (PDOException $e) {
        return false;
    }
}

function getFromDb(string $table, string $col, string $val): ?array {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$col}` = :val LIMIT 1");
        $stmt->execute([':val' => $val]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

// ─── Email Helpers ────────────────────────────────────────

function sendAdminNotification(array $order): bool {
    $subject = "🆕 New Website Order – {$order['order_id']}";
    $body = <<<EOT
New website order received!

Order ID: {$order['order_id']}
Site Name: {$order['site_name']}
Design: Design {$order['design_id']}
Payment: {$order['payment_method']}
Client Email: {$order['client_email']}
Client Phone: {$order['client_phone']}

Requirement:
{$order['requirement']}

Please proceed with development.
EOT;
    return mail(ADMIN_EMAIL, $subject, $body, "From: " . SMTP_FROM);
}

function sendClientConfirmation(string $email, string $siteName, string $orderId): bool {
    $subject = "✅ Order Confirmed – WEBbuilder.lk ({$orderId})";
    $body = <<<EOT
Dear Client,

Your website order has been placed successfully!

Order ID: {$orderId}
Project: {$siteName}
Estimated Delivery: 5 Business Days

Our team will start working on your website immediately.
You'll receive updates via email as we progress.

Need any changes? Reply to this email or contact us:
📞 +94 741 561 234 (Tamil)
📞 +94 769 988 123 (English)

Thank you for choosing WEBbuilder.lk!
EOT;
    return mail($email, $subject, $body, "From: WEBbuilder.lk <" . SMTP_FROM . ">");
}
?>
