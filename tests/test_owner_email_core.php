<?php
// Test core OwnerEmail encryption/save/load (no real SMTP needed).
// Usage: set OWNER_EMAIL_ENC_KEY env var, then run:
//   C:\UniServerZ\core\php83\php.exe tests/test_owner_email_core.php

putenv('OWNER_EMAIL_ENC_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/OwnerEmail.php';

$slug = 'test-site-' . bin2hex(random_bytes(4));
$pass = 0;
$fail = 0;

function check($cond, $msg) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS: $msg\n"; }
    else { $fail++; echo "FAIL: $msg\n"; }
}

// 1. Not configured initially
$d = OwnerEmail::load($slug, false);
check(empty($d['email']), 'fresh slug returns empty config');

// 2. Save config (encrypts smtp_pass at rest)
$ok = OwnerEmail::save($slug, [
    'email' => 'owner@example.com',
    'name' => 'Owner Biz',
    'provider' => 'gmail',
    'auth_method' => 'smtp_app_password',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_user' => 'owner@example.com',
    'smtp_pass' => 'abcd1234efgh5678', // fake app password
    'oauth_access_token' => '',
    'oauth_refresh_token' => '',
    'updated_by' => 'owner@example.com',
]);
check($ok, 'save() returns true');

// 3. Load WITHOUT secrets — smtp_pass must NOT be present
$d = OwnerEmail::load($slug, false);
check(!empty($d['email']), 'load() returns email');
check($d['email'] === 'owner@example.com', 'load() email matches');
check(empty($d['smtp_pass']), 'load() without secrets does NOT expose smtp_pass');
check($d['provider'] === 'gmail', 'load() provider matches');

// 4. Raw file on disk — smtp_pass must be ciphertext, not plaintext
$f = OWNER_EMAIL_STORAGE_DIR . '/' . $slug . '.json';
$raw = json_decode(file_get_contents($f), true);
check($raw['smtp_pass'] !== 'abcd1234efgh5678', 'stored smtp_pass is NOT plaintext on disk');
check($raw['smtp_pass'] !== '', 'stored smtp_pass is encrypted (non-empty)');
check($raw['email'] === 'owner@example.com', 'non-secret email stored plaintext (ok)');

// 5. Load WITH secrets — decrypts smtp_pass
$d2 = OwnerEmail::credentials($slug);
check($d2['smtp_pass'] === 'abcd1234efgh5678', 'credentials() decrypts smtp_pass');
check($d2['email'] === 'owner@example.com', 'credentials() email matches');

// 6. summary() — public-safe, masked email, no secrets
$s = OwnerEmail::summary($slug);
check($s['configured'] === true, 'summary configured=true');
check($s['email'] === 'ow***@example.com', 'summary email masked: ' . $s['email']);
check(empty($s['smtp_pass']), 'summary has no smtp_pass');
check(empty($s['oauth_refresh_token']), 'summary has no oauth_refresh_token');

// 7. isConfigured
check(OwnerEmail::isConfigured($slug) === true, 'isConfigured() true');
check(OwnerEmail::isConfigured('nonexistent-slug') === false, 'isConfigured() false for missing');

// 8. mailer() throws when not configured
try {
    OwnerEmail::mailer('no-such-slug');
    check(false, 'mailer() throws for unconfigured slug');
} catch (Throwable $e) {
    check(true, 'mailer() throws for unconfigured slug: ' . $e->getMessage());
}

// 9. Delete
check(OwnerEmail::delete($slug) === true, 'delete() returns true');
check(!is_file(OWNER_EMAIL_STORAGE_DIR . '/' . $slug . '.json'), 'file removed after delete');

// 10. OAuth config saved & decrypted
$ok2 = OwnerEmail::save($slug, [
    'email' => 'owner@example.com',
    'provider' => 'gmail',
    'auth_method' => 'oauth2',
    'smtp_user' => 'owner@example.com',
    'smtp_pass' => '',
    'oauth_access_token' => 'ya29.ABC123',
    'oauth_refresh_token' => '1//04REFRESH_TOKEN',
    'oauth_expires_at' => date('c', time() + 3600),
    'updated_by' => 'owner@example.com',
]);
check($ok2, 'oauth save() returns true');
$d3 = OwnerEmail::credentials($slug);
check($d3['oauth_refresh_token'] === '1//04REFRESH_TOKEN', 'oauth refresh token decrypts');
check($d3['oauth_access_token'] === 'ya29.ABC123', 'oauth access token decrypts');

echo "\n=== $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
