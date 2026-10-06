<?php
// ═══════════════════════════════════════════════════════════════
//  config.php — Master Configuration Bootstrap Loader
//  Loads modular settings from config/ directory.
//  Each file is optional: missing files fall back to env vars
//  (see config/*.example.php — copy to config/*.php for local dev).
// ═══════════════════════════════════════════════════════════════

foreach (['app.php', 'paypal.php', 'database.php', 'mail.php', 'platform-admin.php', 'social.php'] as $cfg) {
    $f = __DIR__ . '/config/' . $cfg;
    if (file_exists($f)) require_once $f;
}
