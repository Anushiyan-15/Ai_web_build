<?php
// tests/test-admin-security.php — run: php tests/test-admin-security.php
require_once __DIR__ . '/../includes/AdminPanelGenerator.php';
if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();
$fail = 0;
function ok($c,$m){global $fail;echo ($c?"PASS":"FAIL").": $m\n";if(!$c)$fail++;}

$dir = sys_get_temp_dir() . '/wc-adm-test-' . substr(md5(uniqid()), 0, 8);
@mkdir($dir, 0755, true);
$hash = password_hash('SecurePass123!', PASSWORD_DEFAULT);
$order = ['site_name' => 'Test Bus Co', 'slug' => 'test-bus', 'order_id' => 'WBL-TEST-1',
    'admin_username' => 'bus_admin', 'admin_email' => 'owner@testbus.local',
    'admin_password_hash' => $hash,
    'admin_entities' => [
        ['id' => 'trips', 'name' => 'Trips', 'singular' => 'Trip', 'icon' => '🚌'],
        ['id' => 'bookings', 'name' => 'Bookings', 'singular' => 'Booking', 'icon' => '🎫'],
    ]];

// 1. Install works with hash-only order (no plaintext anywhere)
try { $r = AdminPanelGenerator::install($dir, $order); ok($r === true, 'install succeeds hash-only'); }
catch (Throwable $e) { ok(false, 'install succeeds hash-only (' . $e->getMessage() . ')'); }

// 2. .auth.json: hash only, no plaintext, no demo, no defaults
$auth = json_decode(@file_get_contents($dir . '/.auth.json'), true) ?: [];
ok(!isset($auth['password_plain']), 'no plaintext password stored');
ok(!isset($auth['demo']), 'no demo account');
ok(!empty($auth['hash']) && password_verify('SecurePass123!', $auth['hash']), 'stored hash verifies');
ok(($auth['username'] ?? '') === 'bus_admin', 'owner username stored');

// 3. login.php: no embedded creds, no autologin, no quick-fill/demo, hash-only
$login = file_get_contents($dir . '/login.php');
ok(strpos($login, 'SecurePass123!') === false, 'login.php embeds no password');
ok(stripos($login, 'autologin') === false, 'no autologin bypass');
ok(stripos($login, 'quickFill') === false && stripos($login, 'demoFill') === false, 'no quick-fill/demo helpers');
ok(strpos($login, 'password_verify') !== false, 'hash verification present');
ok(strpos($login, 'admin_login_allowed') !== false, 'rate limiting present');
ok(strpos($login, 'session_regenerate_id') !== false, 'session fixation guard present');
ok(strpos($login, 'demo / Demo@1234') === false && strpos($login, 'Demo@1234') === false, 'no default demo password advertised');

// 4. settings.php: no plaintext writes (unset() cleanup calls are fine)
$settings = file_get_contents($dir . '/settings.php');
ok(strpos($settings, "password_plain'] =") === false && strpos($settings, '"password_plain"') === false, 'settings never writes plaintext');

// 5. Custom modules per business (bus entities via manage.php + sidebar nav)
$manage = file_get_contents($dir . '/manage.php');
$layout = file_get_contents($dir . '/layout.php');
ok(stripos($manage, 'trips') !== false && stripos($manage, 'bookings') !== false, 'bus modules in entity manager');
ok(stripos($layout, 'navEntities') !== false, 'sidebar renders business-specific nav');
$dash = file_get_contents($dir . '/index.php');
ok(strpos($dash, 'manage.php') !== false, 'dashboard links entity manager');

// 5b. Unrelated business gets no irrelevant modules
$dir2 = $dir . '-shop';
@mkdir($dir2, 0755, true);
AdminPanelGenerator::install($dir2, array_merge($order, ['site_name' => 'Shop', 'slug' => 'shop',
    'admin_entities' => [['id' => 'products', 'name' => 'Products', 'singular' => 'Product', 'icon' => '🛍️']]]));
$manage2 = file_get_contents($dir2 . '/manage.php');
ok(stripos($manage2, 'products') !== false, 'shop manager has products');
ok(stripos($manage2, 'trips') === false && stripos($manage2, 'bookings') === false, 'shop manager has no bus modules');
foreach (glob($dir2 . '/*') ?: [] as $ff) @unlink($ff);
@rmdir($dir2);

// 6. Missing/invalid credentials rejected at install
try { AdminPanelGenerator::install($dir . '-x', array_merge($order, ['admin_password_hash' => ''])); ok(false, 'empty hash rejected'); }
catch (InvalidArgumentException $e) { ok(true, 'empty hash rejected'); }
try { AdminPanelGenerator::install($dir . '-y', array_merge($order, ['admin_username' => 'ab'])); ok(false, 'bad username rejected'); }
catch (InvalidArgumentException $e) { ok(true, 'bad username rejected'); }

// 7. Platform: no plaintext in order detail contract, no master password
$pub = file_get_contents(__DIR__ . '/../publish.php');
ok(strpos($pub, "'admin_password_plain' => \$needsAdmin") === false, 'create_order stores no plaintext');
ok(strpos($pub, 'autologin=1') === false, 'no autologin bypass links');
$db = file_get_contents(__DIR__ . '/../includes/db.php');
ok(strpos($db, "=== 'WebCraft@2026!'") === false, 'no master-password bypass in customer auth');
$cv = file_get_contents(__DIR__ . '/../platform-admin/customer-view.php');
ok(strpos($cv, "admin_password_plain'] ?? '—'") === false, 'platform view shows no stored password');

// 8. Platform admin: no hard-coded fallback credential may bypass the
// configured bcrypt hash (auth.php, roles.php), no plaintext quick-fill
// on the login page, and no plaintext admin password storage in team.php.
$paAuth = file_get_contents(__DIR__ . '/../platform-admin/auth.php');
ok(strpos($paAuth, "=== 'WebCraft@2026!'") === false && strpos($paAuth, 'WebCraft@2026') === false, 'auth.php has no hard-coded password bypass');
ok(strpos($paAuth, 'password_verify') !== false, 'auth.php still verifies bcrypt hash');
$roles = file_get_contents(__DIR__ . '/../platform-admin/roles.php');
ok(strpos($roles, "'raw_pass'   =>") === false, 'roles.php seeds no plaintext raw_pass');
ok(strpos($roles, "=== \$password") === false && strpos($roles, "\$password ===") === false, 'roles.php has no plaintext password comparison');
if (!function_exists('auth_test_authenticate')) {
    require_once __DIR__ . '/../platform-admin/roles.php';
    function auth_test_authenticate($u, $p) { return authenticate_admin_account($u, $p); }
}
ok(auth_test_authenticate('superadmin', 'definitely-wrong-password-123') === null, 'wrong admin password rejected');
ok(auth_test_authenticate('superadmin', 'WebCraft@2026!') !== null, 'correct admin password still verifies via hash');
$loginPage = file_get_contents(__DIR__ . '/../platform-admin/login.php');
ok(strpos($loginPage, 'WebCraft@2026!') === false && strpos($loginPage, 'Support@2026!') === false
    && strpos($loginPage, 'Billing@2026!') === false && strpos($loginPage, 'Developer@2026!') === false,
    'login page exposes no embedded passwords');
$team = file_get_contents(__DIR__ . '/../platform-admin/team.php');
ok(strpos($team, "'raw_pass'") === false, 'team.php stores hash only for new admins');

// cleanup
foreach (glob($dir . '/*') ?: [] as $ff) @unlink($ff);
@rmdir($dir);
echo $fail===0 ? "\nALL ADMIN-SECURITY TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail===0?0:1);
