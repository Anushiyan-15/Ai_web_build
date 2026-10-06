<?php
// ═══════════════════════════════════════════════════════════════
//  config/paypal.example.php — PayPal Payment Gateway Configuration (TEMPLATE)
//  Copy to config/paypal.php and fill in your own credentials.
//  config/paypal.php is git-ignored and never pushed.
//
//  HOW TO SET UP REAL PAYPAL PAYMENTS:
//  ─────────────────────────────────────────────────────────────
//  STEP 1 — Get Sandbox credentials (for testing):
//     • Go to: https://developer.paypal.com/dashboard/applications/sandbox
//     • Click "Create App" → name it "WebCraft AI" → click Create
//     • Copy "Client ID" and "Secret Key" from the sandbox section
//     • Paste below, set PAYPAL_MODE = 'sandbox'
//
//  STEP 2 — Switch to Live (for real money):
//     • Go to: https://developer.paypal.com/dashboard/applications/live
//     • Create/select your Live app → copy Live Client ID & Secret
//     • Paste below, set PAYPAL_MODE = 'live'
//
//  LEAVE AS IS → Simulator mode is used (no real PayPal, auto-success)
// ═══════════════════════════════════════════════════════════════

// ── STEP 1: Set mode ────────────────────────────────────────────
//   'sandbox' → Test with PayPal sandbox (real PayPal UI, fake money)
//   'live'    → Real PayPal payments (real money charged to buyer)
if (!defined('PAYPAL_MODE')) {
    define('PAYPAL_MODE', 'sandbox');
}

// ── STEP 2: Paste your credentials ─────────────────────────────
if (!defined('PAYPAL_CLIENT_ID')) {
    define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID') ?: 'YOUR_PAYPAL_SANDBOX_CLIENT_ID');
}

if (!defined('PAYPAL_CLIENT_SECRET')) {
    define('PAYPAL_CLIENT_SECRET', getenv('PAYPAL_CLIENT_SECRET') ?: 'YOUR_PAYPAL_SANDBOX_CLIENT_SECRET');
}

// ── Currency ───────────────────────────────────────────────────
// Common: 'USD', 'EUR', 'GBP', 'INR', 'SGD', 'AUD', 'CAD'
if (!defined('PAYPAL_CURRENCY')) {
    define('PAYPAL_CURRENCY', 'USD');
}

// ── API Endpoint (auto-set based on mode) ──────────────────────
if (!defined('PAYPAL_BASE_URL')) {
    define('PAYPAL_BASE_URL',
        PAYPAL_MODE === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com'
    );
}

// ── Fallback plan prices (overridden by storage/plans.json) ────
$GLOBALS['PAYPAL_PLANS'] = [
    'starter'  => ['name' => 'Starter Plan',  'price' => 9.00,  'period' => 'month'],
    'pro'      => ['name' => 'Pro Plan',       'price' => 19.00, 'period' => 'month'],
    'business' => ['name' => 'Business Plan',  'price' => 49.00, 'period' => 'month'],
];
