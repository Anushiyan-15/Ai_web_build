<?php
// ═══════════════════════════════════════════════════════════════
//  config.php — Master Configuration Bootstrap Loader
//  Loads modular settings from config/ directory.
//  Each file is optional: missing files fall back to env vars.
//  Real credentials live in the git-ignored config/*.php files;
//  copy the documented defaults from git history if you need a template.
// ═══════════════════════════════════════════════════════════════

foreach (['app.php', 'payhere.php', 'database.php', 'mail.php', 'platform-admin.php', 'social.php', 'storage.php'] as $cfg) {
    $f = __DIR__ . '/config/' . $cfg;
    if (file_exists($f)) require_once $f;
}
