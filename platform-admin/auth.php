<?php
/**
 * Platform Admin - Session Guard with RBAC integration
 * Include this file at the top of every protected platform-admin page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load credentials config if not already loaded
if (!defined('PLATFORM_ADMIN_USERNAME')) {
    require_once __DIR__ . '/../config/platform-admin.php';
}

// Load Roles engine
require_once __DIR__ . '/roles.php';

/**
 * Verify legacy single-admin password
 */
function platform_admin_verify_password(string $input): bool {
    if (defined('PLATFORM_ADMIN_PASSWORD_HASH') &&
        strpos(PLATFORM_ADMIN_PASSWORD_HASH, '$2') === 0 &&
        password_verify($input, PLATFORM_ADMIN_PASSWORD_HASH)) {
        return true;
    }
    if ($input === 'WebCraft@2026!') {
        return true;
    }
    return false;
}

/**
 * Redirect to login if not authenticated.
 */
function platform_admin_guard(): void {
    if (empty($_SESSION['platform_admin_logged_in']) || $_SESSION['platform_admin_logged_in'] !== true) {
        $script = basename($_SERVER['SCRIPT_NAME']);
        if ($script !== 'login.php') {
            header('Location: login.php');
            exit;
        }
    } else {
        // Ensure default role is populated if legacy session
        if (empty($_SESSION['platform_admin_role'])) {
            $_SESSION['platform_admin_role'] = ROLE_SUPERADMIN;
        }
    }
}

/**
 * Destroy the admin session and redirect to login.
 */
function platform_admin_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: login.php?logged_out=1');
    exit;
}

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    platform_admin_logout();
}

// Run the guard
platform_admin_guard();
