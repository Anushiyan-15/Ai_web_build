<?php
/**
 * wildcard-router.php
 * Automated Multi-Tenant Wildcard Domain Router
 * 
 * Works with DNS: *.yourdomain.com -> Server IP
 * Example:
 *   https://zenith-studio.yourdomain.com/        -> serves published/zenith-studio/index.html
 *   https://zenith-studio.yourdomain.com/admin/  -> serves published/zenith-studio/admin/index.php
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/db.php';

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
// Strip port if present
if (strpos($host, ':') !== false) {
    $host = explode(':', $host)[0];
}

// Extract subdomain (e.g. "zenith-studio" from "zenith-studio.example.com")
$parts = explode('.', $host);
$subdomain = '';

// Check custom domain in database first
$db = getDb();
$tenantSlug = null;

if ($db) {
    try {
        $stmt = $db->prepare("SELECT slug, site_active FROM orders WHERE custom_domain = :dom LIMIT 1");
        $stmt->execute([':dom' => $host]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $tenantSlug = $row['slug'];
            $siteActive = !empty($row['site_active']);
        }
    } catch (Throwable $e) {}
}

if (!$tenantSlug && count($parts) >= 3) {
    // Standard wildcard subdomain: <slug>.domain.tld
    $subdomain = $parts[0];
    if (!in_array($subdomain, ['www', 'mail', 'cpanel', 'api', 'admin'], true)) {
        $tenantSlug = $subdomain;
    }
}

// Fallback query parameter for local development testing: ?subdomain=zenith-studio
if (!$tenantSlug && isset($_GET['subdomain'])) {
    $tenantSlug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($_GET['subdomain']));
}

if (!$tenantSlug) {
    // Not a tenant subdomain -> serve main platform landing/builder
    require __DIR__ . '/index.php';
    exit;
}

$tenantDir = __DIR__ . '/published/' . $tenantSlug;
if (!is_dir($tenantDir)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#0a0d14;color:#fff;text-align:center;padding:80px;">';
    echo '<h1>404 — Website Not Found</h1>';
    echo '<p style="color:#94a3b8;">The subdomain <strong>' . htmlspecialchars($tenantSlug) . '</strong> is not yet published or does not exist.</p>';
    echo '<p><a href="' . SITE_URL . '" style="color:#6366f1;">Create this website with WebCraft AI &rarr;</a></p>';
    echo '</body></html>';
    exit;
}

// Check suspension
if (file_exists($tenantDir . '/index.html.disabled') && !file_exists($tenantDir . '/index.html')) {
    http_response_code(402);
    require $tenantDir . '/index.html.disabled';
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Admin route
if (strpos($uri, '/admin') === 0) {
    $subPath = substr($uri, 6); // after /admin
    if ($subPath === '' || $subPath === '/') {
        $subPath = '/login.php';
    }
    $targetFile = $tenantDir . '/admin' . $subPath;
    if (file_exists($targetFile)) {
        require $targetFile;
        exit;
    }
    if (file_exists($tenantDir . '/admin/index.php')) {
        require $tenantDir . '/admin/index.php';
        exit;
    }
}

// Root page
if ($uri === '/' || $uri === '/index.html') {
    if (file_exists($tenantDir . '/index.html')) {
        readfile($tenantDir . '/index.html');
        exit;
    }
}

// Static asset request (CSS, JS, images)
$assetPath = realpath($tenantDir . $uri);
if ($assetPath && strpos($assetPath, realpath($tenantDir)) === 0 && file_exists($assetPath) && !is_dir($assetPath)) {
    $mime = mime_content_type($assetPath);
    if (str_ends_with($assetPath, '.css')) $mime = 'text/css';
    elseif (str_ends_with($assetPath, '.js')) $mime = 'application/javascript';
    header('Content-Type: ' . $mime);
    readfile($assetPath);
    exit;
}

// Default fallback
if (file_exists($tenantDir . '/index.html')) {
    readfile($tenantDir . '/index.html');
    exit;
}

http_response_code(404);
echo "File not found.";
