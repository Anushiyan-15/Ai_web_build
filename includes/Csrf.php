<?php
// ═══════════════════════════════════════════════════════════════
//  includes/Csrf.php — Session-bound CSRF tokens for
//  cookie-authenticated state-changing requests (storage APIs,
//  admin storage forms, customer portal storage actions).
// ═══════════════════════════════════════════════════════════════

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_validate(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $sess = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sess) || $sess === '' || !is_string($token) || $token === '') return false;
    return hash_equals($sess, $token);
}

/** Extract token from POST body, JSON payload, or X-CSRF-Token header. */
function csrf_token_from_request(array $json = []): ?string {
    if (!empty($_POST['csrf_token']) && is_string($_POST['csrf_token'])) return $_POST['csrf_token'];
    if (!empty($json['csrf_token']) && is_string($json['csrf_token'])) return $json['csrf_token'];
    $h = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($h) && $h !== '' ? $h : null;
}
