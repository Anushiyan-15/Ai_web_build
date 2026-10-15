<?php
// tests/test-publish-handoff.php — run: php tests/test-publish-handoff.php
// Covers: builder credentials → publish pre-fill → hashed order → working
// admin login; studio preview never opens a blank tab.
require_once __DIR__ . '/../includes/AdminPanelGenerator.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function f($p) { return file_get_contents(__DIR__ . '/../' . $p); }
$b = f('builder.php'); $p = f('publish.php'); $s = f('studio.php');

// 1. Builder carries credentials into the saved session
ok(strpos($b, 'adminUsername:') !== false && strpos($b, 'admin_custom_user') !== false, 'builder saves admin username to session');
ok(strpos($b, 'adminPassword:') !== false && strpos($b, 'admin_custom_pass') !== false, 'builder saves admin password to session');

// 2. Publish pre-fills them (username/email/password inputs)
ok(strpos($p, 'p.adminUsername') !== false && strpos($p, 'p.adminPassword') !== false, 'publish reads carried credentials');
ok(strpos($p, 'pInp.value = state.adminPassword') !== false, 'publish pre-fills password inputs');

// 3. Order path hashes (never plaintext) and install consumes the hash
ok(strpos($p, "'admin_password_hash'") !== false, 'order stores hash-only credential');
ok(strpos($p, 'adminPassword: state.adminPassword') !== false, 'create_order sends password for hashing');

// 4. Realistic username from the report (double underscore, 17 chars) works end-to-end
$u = 'bus_booking__admin';
ok(preg_match('/^[a-z][a-z0-9_]{3,19}$/', $u) === 1, 'reported username passes validation');
$dir = sys_get_temp_dir() . '/wc-handoff-' . substr(md5(uniqid()), 0, 8);
@mkdir($dir, 0755, true);
try {
    AdminPanelGenerator::install($dir, ['site_name' => 'Bus Booking Service', 'slug' => 'bus-booking-service',
        'order_id' => 'WBL-HO-1', 'admin_username' => $u, 'admin_email' => 'owner@bus.local',
        'admin_password_hash' => password_hash('BusPass@123', PASSWORD_DEFAULT),
        'admin_entities' => [['id' => 'bookings', 'name' => 'Bookings']]]);
    $auth = json_decode(@file_get_contents($dir . '/.auth.json'), true) ?: [];
    ok(($auth['username'] ?? '') === $u && password_verify('BusPass@123', $auth['hash'] ?? ''), 'installed login verifies carried credentials');
    ok(!isset($auth['password_plain']), 'no plaintext at rest');
} catch (Throwable $e) { ok(false, 'install with carried credentials (' . $e->getMessage() . ')'); }
foreach (glob($dir . '/*') ?: [] as $ff) @unlink($ff);
@rmdir($dir);

// 5. Studio preview: instant paint + guards (never a blank tab)
ok(strpos($s, 'frame.srcdoc = html') !== false, 'preview paints frame immediately');
ok(strpos($s, 'Nothing to preview yet') !== false, 'preview refuses empty canvas (no blank tab)');
ok(strpos($s, 'try { syncCanvasToHtml(); } catch') !== false, 'preview survives sync failure');
ok(strpos($s, 'else frame.srcdoc = currentHtml') === false, 'old deferred-only paint gone');

// 6. Builder admin preview is REAL (no mock, no publish-to-activate).
// The Admin tab provisions a working preview env (real PHP + real JSON)
// instead of embedding a fake interface.
ok(strpos($b, 'Live after publish') === false, 'no publish-gated mock rows');
ok(strpos($b, 'Admin panel is generated on Publish') === false, 'no generated-on-publish gate');
ok(strpos($b, 'api/preview-env.php') !== false, 'workspace provisions real preview env');
ok(strpos($b, 'loadWorkspaceAdmin') !== false, 'admin tab loads working preview admin');
ok(strpos($b, 'previewAddFeatureFlow') !== false, 'AI features install into preview before publish');
ok(strpos($b, "✨ New feature module built & installed") === false, 'fake install message gone');

echo $fail === 0 ? "\nALL PUBLISH-HANDOFF TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
