<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin(): void {
    if (empty($_SESSION['admin_auth'])) {
        $redirect = 'login.php';
        header("Location: $redirect");
        exit;
    }
}

/** Login rate limiter: max 5 attempts per 5 minutes per IP. */
function admin_login_allowed(): bool {
    $file = __DIR__ . '/.login_attempts.json';
    $now = time();
    $rows = file_exists($file) ? (json_decode(@file_get_contents($file), true) ?: []) : [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $rows = array_values(array_filter($rows, fn($r) => ($r['at'] ?? 0) > $now - 300));
    $n = 0;
    foreach ($rows as $r) { if (($r['ip'] ?? '') === $ip) $n++; }
    @file_put_contents($file, json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $n < 5;
}

function admin_login_failed(): void {
    $file = __DIR__ . '/.login_attempts.json';
    $rows = file_exists($file) ? (json_decode(@file_get_contents($file), true) ?: []) : [];
    $rows[] = ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli', 'at' => time()];
    @file_put_contents($file, json_encode(array_slice($rows, -50), JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function getAuthData(): array {
    $file = __DIR__ . '/.auth.json';
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

/** CSRF protection for session-authenticated admin POST forms. */
function admin_csrf_token(): string {
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['admin_csrf'];
}

function admin_csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(admin_csrf_token(), ENT_QUOTES) . '">';
}

function admin_require_csrf(): void {
    $t = (string)($_POST['csrf'] ?? '');
    $s = (string)($_SESSION['admin_csrf'] ?? '');
    if ($t === '' || $s === '' || !hash_equals($s, $t)) {
        http_response_code(403);
        exit('Invalid session token. Please reload the page and try again.');
    }
}

/**
 * SECTION 18.5 — VISIBILITY & DISPLAY RULE GUARD
 * Evaluates whether Requirement Builder features may be rendered.
 */
function canShowRequirementBuilder(string $role, string $mode, $recordId): bool {
    return ($role === 'admin' && $mode === 'edit' && !empty($recordId));
}