<?php
// ═══════════════════════════════════════════════════════════════
//  config/mail.example.php — SMTP & PHPMailer Configuration (TEMPLATE)
//  Copy to config/mail.php and fill in your own values.
//  config/mail.php is git-ignored and never pushed.
//  Never commit real passwords or app-passwords.
// ═══════════════════════════════════════════════════════════════

if (!defined('SMTP_HOST'))       define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
if (!defined('SMTP_USER'))       define('SMTP_USER', getenv('SMTP_USER') ?: 'you@example.com');
if (!defined('SMTP_PASS'))       define('SMTP_PASS', getenv('SMTP_PASS') ?: 'YOUR_SMTP_APP_PASSWORD');
if (!defined('SMTP_PORT'))       define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
if (!defined('SMTP_SECURE'))     define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'you@example.com');
if (!defined('SMTP_FROM_NAME'))  define('SMTP_FROM_NAME', 'WebCraft AI');
if (!defined('SUPPORT_EMAIL'))   define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'you@example.com');
