<?php
// tests/test-config-boot.php — run: php tests/test-config-boot.php
// Post-cleanup verification: single config file per area (no *.example.php),
// all constants boot, real secrets intact, placeholders behave safely.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SocialAuth.php';
require_once __DIR__ . '/../includes/OpenRouterService.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

// 1. No example duplicates left.
$ex = glob(__DIR__ . '/../config/*.example.php') ?: [];
ok(empty($ex), 'config/ has no *.example.php files');

// 2. Core constants boot.
foreach (['SITE_URL', 'SITE_NAME', 'GEMINI_API_KEY', 'GEMINI_MODEL', 'GEMINI_ENDPOINT',
    'OPENCODE_API_KEY', 'OPENCODE_DEFAULT_MODEL', 'STORAGE_DIR', 'PUBLISHED_DIR', 'NODE_API_URL'] as $k) {
    ok(defined($k), "constant $k defined");
}

// 3. OpenRouter block merged (env-first + safe placeholder default).
ok(defined('OPENROUTER_API_KEY') && defined('OPENROUTER_CHAT_ENDPOINT') && defined('OPENROUTER_MODELS_ENDPOINT'),
    'OpenRouter constants defined');
ok(defined('OPENROUTER_CACHE_TTL') && defined('OPENROUTER_TEST_TIMEOUT'), 'OpenRouter tuning constants defined');
$src = (string)@file_get_contents(__DIR__ . '/../includes/OpenRouterService.php');
ok(strpos($src, "getenv('OPENROUTER_API_KEY')") !== false, 'service reads env before constant');
ok(openrouter_is_configured() === (strlen(openrouter_api_key()) > 10), 'configured flag matches key presence (no fake configured)');

// 4. Real secrets intact (never replaced by placeholders).
ok(strpos((string)GEMINI_API_KEY, 'YOUR_') === false && strlen((string)GEMINI_API_KEY) > 10, 'real Gemini key intact');
ok(strpos((string)OPENCODE_API_KEY, 'YOUR_') === false && strlen((string)OPENCODE_API_KEY) > 10, 'real OpenCode key intact');
ok(strpos((string)PLATFORM_ADMIN_PASSWORD_HASH, '$2y$') === 0, 'platform admin bcrypt hash intact (not placeholder)');
ok((string)PLATFORM_ADMIN_SECRET !== 'PASTE_RANDOM_SECRET_HERE', 'platform secret is real value');
ok(strpos((string)SMTP_USER, '@') !== false && strpos((string)SMTP_USER, 'you@') === false, 'real SMTP user intact');
ok(strpos((string)DB_PASS, 'YOUR_') === false, 'real DB password intact');
ok(strpos((string)SUPABASE_ANON_KEY, 'YOUR_') === false, 'real Supabase anon key intact');

// 5. Mail/PayPal/social shapes preserved.
foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURE', 'SMTP_FROM_EMAIL', 'SUPPORT_EMAIL',
    'PAYPAL_MODE', 'PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'PAYPAL_CURRENCY', 'PAYPAL_BASE_URL'] as $k) {
    ok(defined($k), "constant $k defined");
}
ok(!empty($GLOBALS['PAYPAL_PLANS']['pro']['price']), 'PayPal fallback plans intact');
foreach (['SOCIAL_GOOGLE_CLIENT_ID', 'SOCIAL_GOOGLE_CLIENT_SECRET', 'SOCIAL_FACEBOOK_APP_ID',
    'SOCIAL_FACEBOOK_APP_SECRET', 'SOCIAL_INSTAGRAM_APP_ID', 'SOCIAL_INSTAGRAM_APP_SECRET'] as $k) {
    ok(defined($k), "constant $k defined");
}

// 6. Placeholder social keys degrade safely (setup notice path, no crash).
ok(social_is_configured('google') === false, 'google reports unconfigured on placeholders');
ok(social_is_configured('facebook') === false, 'facebook reports unconfigured on placeholders');
ok(social_is_configured('instagram') === false, 'instagram reports unconfigured on placeholders');
ok(social_login_url('google') === null, 'login URL builder returns null (portal shows setup notice)');

// 7. Platform admin timing constants intact.
ok((int)SUBSCRIPTION_PERIOD_DAYS === 30 && (int)RENEWAL_WARNING_DAYS === 5, 'subscription timing constants intact');

echo $fail === 0 ? "\nALL CONFIG-BOOT TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
