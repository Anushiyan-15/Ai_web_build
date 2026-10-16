<?php
// ═══════════════════════════════════════════════════════════════
//  config/payhere.php — PayHere checkout configuration
//  Supports both modern PayHere env vars and legacy PayPal aliases.
// ═══════════════════════════════════════════════════════════════

if (!defined('PAYMENT_PROVIDER')) {
    define('PAYMENT_PROVIDER', getenv('PAYMENT_PROVIDER') ?: 'payhere');
}

if (!defined('PAYHERE_MODE')) {
    define('PAYHERE_MODE', getenv('PAYHERE_MODE') ?: 'sandbox');
}

if (!defined('PAYHERE_MERCHANT_ID')) {
    define('PAYHERE_MERCHANT_ID', getenv('PAYHERE_MERCHANT_ID') ?: 'YOUR_MERCHANT_ID');
}

if (!defined('PAYHERE_MERCHANT_SECRET')) {
    define('PAYHERE_MERCHANT_SECRET', getenv('PAYHERE_MERCHANT_SECRET') ?: 'YOUR_MERCHANT_SECRET');
}

if (!defined('PAYHERE_CURRENCY')) {
    define('PAYHERE_CURRENCY', getenv('PAYHERE_CURRENCY') ?: 'LKR');
}

if (!defined('PAYHERE_RETURN_URL')) {
    $returnUrl = getenv('PAYHERE_RETURN_URL');
    if ($returnUrl === false || trim((string)$returnUrl) === '') {
        $base = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
        $returnUrl = $base !== '' ? $base . '/builder.php?payment=success' : '/builder.php?payment=success';
    }
    define('PAYHERE_RETURN_URL', $returnUrl);
}

if (!defined('PAYHERE_CANCEL_URL')) {
    $cancelUrl = getenv('PAYHERE_CANCEL_URL');
    if ($cancelUrl === false || trim((string)$cancelUrl) === '') {
        $base = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
        $cancelUrl = $base !== '' ? $base . '/builder.php?payment=cancel' : '/builder.php?payment=cancel';
    }
    define('PAYHERE_CANCEL_URL', $cancelUrl);
}

if (!defined('PAYHERE_NOTIFY_URL')) {
    $notifyUrl = getenv('PAYHERE_NOTIFY_URL');
    if ($notifyUrl === false || trim((string)$notifyUrl) === '') {
        $base = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
        $notifyUrl = $base !== '' ? $base . '/api/payment.php?action=notify' : '/api/payment.php?action=notify';
    }
    define('PAYHERE_NOTIFY_URL', $notifyUrl);
}

if (!defined('PAYHERE_BASE_URL')) {
    define('PAYHERE_BASE_URL', PAYHERE_MODE === 'live'
        ? 'https://www.payhere.lk/pay/checkout'
        : 'https://sandbox.payhere.lk/pay/checkout');
}

if (!defined('PAYPAL_MODE')) {
    define('PAYPAL_MODE', PAYHERE_MODE);
}
if (!defined('PAYPAL_CLIENT_ID')) {
    define('PAYPAL_CLIENT_ID', PAYHERE_MERCHANT_ID);
}
if (!defined('PAYPAL_CLIENT_SECRET')) {
    define('PAYPAL_CLIENT_SECRET', PAYHERE_MERCHANT_SECRET);
}
if (!defined('PAYPAL_CURRENCY')) {
    define('PAYPAL_CURRENCY', PAYHERE_CURRENCY);
}
if (!defined('PAYPAL_BASE_URL')) {
    define('PAYPAL_BASE_URL', PAYHERE_BASE_URL);
}

$GLOBALS['PAYHERE_PLANS'] = [
    'starter'  => ['name' => 'Starter Plan', 'price' => 9.00, 'period' => 'month'],
    'pro'      => ['name' => 'Pro Plan', 'price' => 19.00, 'period' => 'month'],
    'business' => ['name' => 'Business Plan', 'price' => 49.00, 'period' => 'month'],
];

if (!isset($GLOBALS['PAYPAL_PLANS']) || empty($GLOBALS['PAYPAL_PLANS'])) {
    $GLOBALS['PAYPAL_PLANS'] = $GLOBALS['PAYHERE_PLANS'];
}
