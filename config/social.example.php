<?php
// ═══════════════════════════════════════════════════════════════
//  config/social.example.php — Social Login (Google / Facebook / Instagram) TEMPLATE
//  Copy to config/social.php and fill in your own credentials.
//  config/social.php is git-ignored and never pushed.
//
//  HOW TO ENABLE REAL SOCIAL LOGIN:
//  ─────────────────────────────────────────────────────────────
//  GOOGLE:
//    1. Go to https://console.cloud.google.com/apis/credentials
//    2. Create Project → OAuth consent screen (External, add your email)
//    3. Create Credentials → OAuth client ID → Web application
//    4. Authorized redirect URI = YOUR_SITE/customer-portal.php?action=social_callback&provider=google
//       (local dev example: http://localhost/project/webbbuilder/customer-portal.php?action=social_callback&provider=google)
//    5. Paste the Client ID + Client secret below.
//
//  FACEBOOK:
//    1. Go to https://developers.facebook.com/apps → Create App (Authenticate and request data)
//    2. Add "Facebook Login" product → Valid OAuth Redirect URI =
//       YOUR_SITE/customer-portal.php?action=social_callback&provider=facebook
//    3. Paste App ID + App Secret below. (Go Live in App Review for real users.)
//
//  INSTAGRAM (via Instagram Login):
//    1. In the same Meta app, add "Instagram" product (API setup with Instagram login)
//    2. Valid OAuth Redirect URI =
//       YOUR_SITE/customer-portal.php?action=social_callback&provider=instagram
//    3. Paste the Instagram App ID + App Secret below.
//    NOTE: Instagram only shares id + username (no email), so first-time
//    Instagram users complete one extra step: entering their email.
//
//  LEAVE AS-IS (placeholders) → social buttons show a setup notice instead
//  of calling the provider. Nothing else breaks.
// ═══════════════════════════════════════════════════════════════

// ── Google ─────────────────────────────────────────────────────
if (!defined('SOCIAL_GOOGLE_CLIENT_ID')) {
    define('SOCIAL_GOOGLE_CLIENT_ID', getenv('SOCIAL_GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID');
}
if (!defined('SOCIAL_GOOGLE_CLIENT_SECRET')) {
    define('SOCIAL_GOOGLE_CLIENT_SECRET', getenv('SOCIAL_GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET');
}

// ── Facebook ───────────────────────────────────────────────────
if (!defined('SOCIAL_FACEBOOK_APP_ID')) {
    define('SOCIAL_FACEBOOK_APP_ID', getenv('SOCIAL_FACEBOOK_APP_ID') ?: 'YOUR_FACEBOOK_APP_ID');
}
if (!defined('SOCIAL_FACEBOOK_APP_SECRET')) {
    define('SOCIAL_FACEBOOK_APP_SECRET', getenv('SOCIAL_FACEBOOK_APP_SECRET') ?: 'YOUR_FACEBOOK_APP_SECRET');
}

// ── Instagram ──────────────────────────────────────────────────
if (!defined('SOCIAL_INSTAGRAM_APP_ID')) {
    define('SOCIAL_INSTAGRAM_APP_ID', getenv('SOCIAL_INSTAGRAM_APP_ID') ?: 'YOUR_INSTAGRAM_APP_ID');
}
if (!defined('SOCIAL_INSTAGRAM_APP_SECRET')) {
    define('SOCIAL_INSTAGRAM_APP_SECRET', getenv('SOCIAL_INSTAGRAM_APP_SECRET') ?: 'YOUR_INSTAGRAM_APP_SECRET');
}
