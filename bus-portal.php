<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if (!preg_match('/^[a-z0-9_-]{1,60}$/i', $slug)) {
    http_response_code(400);
    exit('Invalid bus site identifier.');
}

$siteDir = __DIR__ . '/published/' . $slug;
$siteIndex = $siteDir . '/index.html';
if (!is_file($siteIndex)) {
    http_response_code(404);
    exit('Bus customer portal is unavailable for this site.');
}
$siteHtml = file_get_contents($siteIndex);
if ($siteHtml === false) {
    http_response_code(500);
    exit('Unable to inspect the published bus site.');
}
if (stripos($siteHtml, 'data-wc-live-bus-booking') === false) {
    http_response_code(404);
    exit('Bus customer portal is unavailable for this site.');
}

$customerPage = $siteDir . '/customer.php';
$template = __DIR__ . '/includes/templates/bus-customer-portal.php';
if (!is_file($customerPage) && !copy($template, $customerPage) && !is_file($customerPage)) {
    error_log('[bus customer portal] failed to provision portal for ' . $slug);
    http_response_code(500);
    exit('Unable to prepare the bus customer portal. Please try again later.');
}
$verifyPage = $siteDir . '/verify.php';
$verifyTemplate = __DIR__ . '/includes/templates/bus-ticket-verify.php';
if (!is_file($verifyPage) && is_file($verifyTemplate)) {
    @copy($verifyTemplate, $verifyPage);
}

$baseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
header('Location: ' . $baseUrl . '/published/' . rawurlencode($slug) . '/customer.php', true, 302);
exit;
