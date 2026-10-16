<?php
// ═══════════════════════════════════════════════════════════════
//  config/storage.php — Customer Storage Quota & Billing Settings
//  No secrets in this file. Override via environment variables or
//  Apache SetEnv where indicated. Currency must match PAYHERE_CURRENCY.
// ═══════════════════════════════════════════════════════════════

// Maximum single file accepted for quota-tracked customer uploads.
// Must stay <= PHP upload_max_filesize / post_max_size.
if (!defined('STORAGE_MAX_FILE_BYTES')) {
    $mb = (int)(getenv('STORAGE_MAX_FILE_MB') ?: 25);
    define('STORAGE_MAX_FILE_BYTES', max(1, $mb) * 1048576);
}

// Allowed MIME types for quota-tracked customer file uploads.
if (!defined('STORAGE_ALLOWED_MIMES')) {
    define('STORAGE_ALLOWED_MIMES', json_encode([
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf', 'text/plain', 'text/csv',
        'application/zip', 'application/x-zip-compressed',
        'application/octet-stream',
        'video/mp4', 'audio/mpeg',
    ]));
}

// Default billing period length (days) for new customer_usage rows.
if (!defined('STORAGE_BILLING_PERIOD_DAYS')) {
    define('STORAGE_BILLING_PERIOD_DAYS', (int)(getenv('STORAGE_BILLING_PERIOD_DAYS') ?: 30));
}

// ── Automatic (off-session) billing capability ─────────────────
// PayPal Checkout orders require interactive buyer approval for every
// payment, so true off-session auto-charge is NOT supported by the
// configured gateway. Keep this false unless a gateway with stored
// payment-method / subscription support is integrated and the
// customer has given explicit consent with a stored authorization.
// When false the UI exposes an honest "manual checkout" state and no
// automatic charge is ever attempted.
if (!defined('STORAGE_AUTOBILL_SUPPORTED')) {
    define('STORAGE_AUTOBILL_SUPPORTED', filter_var(getenv('STORAGE_AUTOBILL_SUPPORTED') ?: false, FILTER_VALIDATE_BOOLEAN));
}

// Secret authorizing cron/CLI-adjacent HTTP triggers (empty = HTTP disabled).
if (!defined('STORAGE_CRON_SECRET')) {
    define('STORAGE_CRON_SECRET', getenv('STORAGE_CRON_SECRET') ?: '');
}
