<?php
// ═══════════════════════════════════════════════════════════════
//  config/app.example.php — Global Application Settings (TEMPLATE)
//  Copy to config/app.php and fill in your own values.
//  config/app.php is git-ignored and never pushed.
// ═══════════════════════════════════════════════════════════════

// Site Settings
if (!defined('SITE_NAME'))    define('SITE_NAME',    'WebCraft AI');
if (!defined('SITE_TAGLINE')) define('SITE_TAGLINE', 'Build stunning websites with AI in seconds');
if (!defined('SITE_URL'))     define('SITE_URL',     'http://localhost/project/webbbuilder');
if (!defined('CONTACT_EMAIL'))define('CONTACT_EMAIL','hello@youragency.com');

// Subdomain publishing (easy multi-tenant URLs like https://mysite.yourdomain.com)
// 1. Point DNS *.yourdomain.com to this server. 2. Set value below. 3. Done.
// Leave '' to publish under SITE_URL/published/<slug>/ (localhost default).
if (!defined('SUBDOMAIN_BASE')) define('SUBDOMAIN_BASE', '');

// Google Gemini API Configuration
// Get your free API key at: https://aistudio.google.com/app/apikey
if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'YOUR_GEMINI_API_KEY');
if (!defined('GEMINI_MODEL'))   define('GEMINI_MODEL',   'gemini-3.5-flash-lite');

if (!defined('GEMINI_ENDPOINT')) {
    define('GEMINI_ENDPOINT',
        'https://generativelanguage.googleapis.com/v1beta/models/'
        . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY
    );
}

// OpenCode Zen API Configuration (AI website generation + AI chat editing)
// Get your key at: https://opencode.ai/zen
if (!defined('OPENCODE_API_KEY')) define('OPENCODE_API_KEY', getenv('OPENCODE_API_KEY') ?: 'YOUR_OPENCODE_API_KEY');
if (!defined('OPENCODE_BASE_URL')) define('OPENCODE_BASE_URL', 'https://opencode.ai/zen/v1');
if (!defined('OPENCODE_CHAT_ENDPOINT')) define('OPENCODE_CHAT_ENDPOINT', OPENCODE_BASE_URL . '/chat/completions');
if (!defined('OPENCODE_MODELS_ENDPOINT')) define('OPENCODE_MODELS_ENDPOINT', OPENCODE_BASE_URL . '/models');
if (!defined('OPENCODE_DEFAULT_MODEL')) define('OPENCODE_DEFAULT_MODEL', 'space-bunny-free');
// All free models (auto-refreshed from /models when possible). Paid models
// are tried only if they appear here AND the account has funds.
if (!defined('OPENCODE_FREE_MODELS')) define('OPENCODE_FREE_MODELS', json_encode([
    'space-bunny-free',
    'big-pickle',
    'muse-spark-1.3-contributor-free',
    'muse-spark-1.2-contributor-free',
    'mimo-v2.5-free',
    'mimo-v2.6-flash-free',
    'nemotron-3.5-lightning-free',
    'jev-1.13-free',
    'longcat-2.5-preview-free',
]));
if (!defined('OPENCODE_TIMEOUT')) define('OPENCODE_TIMEOUT', 90);
// Long budget for full-page generation / full-doc edits on free models
// (free-lane outputs stream slowly; short budgets cause template fallback).
if (!defined('OPENCODE_GEN_TIMEOUT')) define('OPENCODE_GEN_TIMEOUT', 180);
// Gemini fast lane (verified: flash-lite full page ~28s, fits Apache 30s)
if (!defined('GEMINI_FAST_MODEL')) define('GEMINI_FAST_MODEL', 'gemini-3.5-flash-lite');

// Storage and Published Paths
if (!defined('ROOT_DIR'))      define('ROOT_DIR',      dirname(__DIR__));
if (!defined('STORAGE_DIR'))   define('STORAGE_DIR',   ROOT_DIR . '/storage');
if (!defined('PUBLISHED_DIR')) define('PUBLISHED_DIR', ROOT_DIR . '/published');
