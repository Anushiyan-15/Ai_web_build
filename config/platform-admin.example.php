<?php
// ═══════════════════════════════════════════════════════════════
//  config/platform-admin.example.php — Platform Admin Credentials (TEMPLATE)
//  Copy to config/platform-admin.php, then set a strong password:
//    1. Run: php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
//    2. Paste the hash into PLATFORM_ADMIN_PASSWORD_HASH below.
//    3. Set a random PLATFORM_ADMIN_SECRET.
//  config/platform-admin.php is git-ignored and never pushed.
// ═══════════════════════════════════════════════════════════════

// Platform admin username
if (!defined('PLATFORM_ADMIN_USERNAME')) {
    define('PLATFORM_ADMIN_USERNAME', getenv('PLATFORM_ADMIN_USERNAME') ?: 'superadmin');
}

// Platform admin password hash (bcrypt of your own password — never commit plaintext)
if (!defined('PLATFORM_ADMIN_PASSWORD_HASH')) {
    define('PLATFORM_ADMIN_PASSWORD_HASH', getenv('PLATFORM_ADMIN_PASSWORD_HASH') ?: 'PASTE_BCRYPT_HASH_HERE');
}

// Secret key for session tokens (random string, keep private)
if (!defined('PLATFORM_ADMIN_SECRET')) {
    define('PLATFORM_ADMIN_SECRET', getenv('PLATFORM_ADMIN_SECRET') ?: 'PASTE_RANDOM_SECRET_HERE');
}

// How many days before payment is "overdue"
if (!defined('SUBSCRIPTION_PERIOD_DAYS')) {
    define('SUBSCRIPTION_PERIOD_DAYS', 30);
}

// Days before due date to start showing "renewal due soon" warning
if (!defined('RENEWAL_WARNING_DAYS')) {
    define('RENEWAL_WARNING_DAYS', 5);
}

// Platform admin session name
if (!defined('PLATFORM_ADMIN_SESSION')) {
    define('PLATFORM_ADMIN_SESSION', 'wc_platform_admin');
}
