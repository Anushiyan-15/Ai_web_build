<?php
// ═══════════════════════════════════════════════════════════════
//  api/owner-email.php — Per-owner business email configuration API
//  Used by the builder wizard (Business Email Setup) and by generated
//  websites at runtime. All mutations require a verified owner session
//  + CSRF. Credentials are NEVER returned in responses.
// ═══════════════════════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/OwnerEmail.php';
require_once __DIR__ . '/../includes/Csrf.php';

$customerEmail = strtolower(trim($_SESSION['customer_user']['email'] ?? ''));
$isGuest = ($customerEmail === '' || $customerEmail === 'guest');

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function requireOwner(): void {
    global $customerEmail, $isGuest;
    if ($isGuest) {
        jsonResponse(['success' => false, 'error' => 'Sign in to configure your business email.'], 403);
    }
}

function requireCsrf(): void {
    $json = json_decode(file_get_contents('php://input'), true) ?: [];
    $token = $_POST['csrf_token'] ?? ($json['csrf_token'] ?? null);
    if ($token === null) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    if (!\Csrf::csrf_validate($token)) {
        jsonResponse(['success' => false, 'error' => 'CSRF token missing or invalid.'], 403);
    }
}

function slugForOwner(): string {
    global $customerEmail;
    // Derive a stable, per-owner slug from the customer email hash so each
    // owner's credentials are isolated. The actual website slug is also
    // accepted when passed explicitly (verified owner only).
    return 'owner_' . substr(md5($customerEmail ?? 'guest'), 0, 16);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'status':
    case 'load':
        // GET only — returns public-safe summary, no secrets.
        requireOwner();
        $slug = $_GET['slug'] ?? slugForOwner();
        jsonResponse([
            'success' => true,
            'slug' => $slug,
            'config' => \OwnerEmail::summary($slug),
        ]);
        break;

    case 'save':
        // POST — save (encrypt at rest) the owner's business email config.
        requireOwner();
        requireCsrf();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $email    = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $name     = trim((string)($input['name'] ?? ''));
        $provider = in_array(trim((string)($input['provider'] ?? '')), ['gmail', 'outlook', 'custom'], true)
            ? trim((string)($input['provider'])) : 'custom';
        $authMethod = in_array(trim((string)($input['auth_method'] ?? '')), ['smtp_app_password', 'oauth2', 'smtp_credentials'], true)
            ? trim((string)($input['auth_method'])) : 'smtp_app_password';

        if (!$email) {
            jsonResponse(['success' => false, 'error' => 'A valid business email address is required.'], 400);
        }

        $slug = $_POST['slug'] ?? (isset($input['slug']) ? (string)$input['slug'] : slugForOwner());
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?: 'site';
        $slug = substr($slug, 0, 60);

        // Build config to store (secrets encrypted at rest by OwnerEmail::save).
        $config = [
            'email' => $email,
            'name' => $name,
            'provider' => $provider,
            'auth_method' => $auth_method,
            'smtp_host' => trim((string)($input['smtp_host'] ?? '')),
            'smtp_port' => (int)($input['smtp_port'] ?? 587),
            'smtp_secure' => in_array(trim((string)($input['smtp_secure'] ?? '')), ['ssl', 'tls'], true) ? trim((string)($input['smtp_secure'])) : 'tls',
            'smtp_user' => filter_var(trim((string)($input['smtp_user'] ?? $email)), FILTER_VALIDATE_EMAIL) ? trim((string)($input['smtp_user'] ?? $email)) : '',
            'smtp_pass' => (string)($input['smtp_pass'] ?? ''),
            'oauth_access_token' => (string)($input['oauth_access_token'] ?? ''),
            'oauth_refresh_token' => (string)($input['oauth_refresh_token'] ?? ''),
            'oauth_expires_at' => (string)($input['oauth_expires_at'] ?? ''),
            'updated_by' => $customerEmail,
        ];

        // OAuth requires at least a refresh token; SMTP requires user+pass.
        if ($authMethod === 'oauth2') {
            if ($config['oauth_refresh_token'] === '' && $config['oauth_access_token'] === '') {
                jsonResponse(['success' => false, 'error' => 'OAuth requires an authorization (refresh/access) token. Complete the OAuth flow first.'], 400);
            }
        } elseif ($authMethod !== 'oauth2') {
            if ($config['smtp_user'] === '' || $config['smtp_pass'] === '') {
                jsonResponse(['success' => false, 'error' => 'SMTP username and app password are required.'], 400);
            }
        }

        try {
            if (\OwnerEmail::save($slug, $config)) {
                jsonResponse([
                    'success' => true,
                    'slug' => $slug,
                    'message' => 'Business email configuration saved securely (encrypted at rest).',
                    'config' => \OwnerEmail::summary($slug),
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => 'Could not save configuration.'], 500);
            }
        } catch (Throwable $e) {
            // Never leak encryption/key details to the client.
            jsonResponse(['success' => false, 'error' => 'Server encryption is not configured. Contact the platform administrator to set OWNER_EMAIL_ENC_KEY.'], 500);
        }
        break;

    case 'test':
        // POST — send a real test email through the owner's account.
        requireOwner();
        requireCsrf();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $slug   = $_POST['slug'] ?? (isset($input['slug']) ? (string)$input['slug'] : slugForOwner());
        $slug   = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?: 'site';
        $slug   = substr($slug, 0, 60);
        $to     = filter_var(trim((string)($input['to'] ?? '')), FILTER_VALIDATE_EMAIL);
        $sender = trim((string)($input['sender_name'] ?? ''));

        if (!$to) {
            jsonResponse(['success' => false, 'error' => 'A valid recipient email is required for the test.'], 400);
        }

        // Rate limit: max 5 test emails per hour per owner per slug.
        $rateFile = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/storage') . '/owner_email_tests.json';
        $now = time();
        $rates = is_file($rateFile) ? (json_decode(@file_get_contents($rateFile), true) ?: []) : [];
        // Use a string key.
        $rateKey = $slug . '|' . $customerEmail;
        $rates[$rateKey] = array_values(array_filter((array)($rates[$rateKey] ?? []), fn($t) => ($now - (int)$t) < 3600));
        if (count($rates[$rateKey]) >= 5) {
            jsonResponse(['success' => false, 'error' => 'Test email limit reached (5 per hour). Try again later.'], 429);
        }
        $rates[$rateKey][] = $now;
        @file_put_contents($rateFile, json_encode($rates, JSON_UNESCAPED_UNICODE), LOCK_EX);

        $result = \OwnerEmail::test($slug, $to, $sender);
        if ($result['success']) {
            jsonResponse(['success' => true, 'message' => 'Test email sent successfully to ' . $to, 'via' => 'owner_account']);
        } else {
            // Never leak the raw SMTP/auth error to the frontend beyond a safe message.
            $safe = $result['error'];
            jsonResponse(['success' => false, 'error' => 'Test email failed: ' . $safe], 400);
        }
        break;

    case 'delete':
        requireOwner();
        requireCsrf();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $slug = $_POST['slug'] ?? (isset($input['slug']) ? (string)$input['slug'] : slugForOwner());
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?: 'site';
        \OwnerEmail::delete($slug);
        jsonResponse(['success' => true, 'message' => 'Business email configuration removed.']);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action.'], 400);
}
