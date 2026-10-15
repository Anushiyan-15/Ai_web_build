<?php
// tests/test-admin-login-crud.php — run: php tests/test-admin-login-crud.php
// REAL admin proof: install a bus admin exactly like publish does, then log
// in and exercise every inner function — dashboard modules, entity tables,
// Add/Edit/Delete with validation, select dropdowns, inbox/content/settings.
require_once __DIR__ . '/../includes/BusinessAnalyzer.php';
require_once __DIR__ . '/../includes/BusinessManifest.php';
require_once __DIR__ . '/../includes/AdminDashboardBuilder.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function rmdir_r($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = $d . '/' . $f;
        is_dir($p) ? rmdir_r($p) : @unlink($p); }
    @rmdir($d);
}

$tmp = sys_get_temp_dir() . '/wc-admin-e2e';
rmdir_r($tmp);
$adminDir = $tmp . '/busbooking/admin';
@mkdir($adminDir, 0755, true);
// Fresh session store for the shared harness login (stale files would leak auth).
foreach (glob(sys_get_temp_dir() . '/wc-harness-sess/*') ?: [] as $sf) @unlink($sf);
$GLOBALS['BUSINESS_ANALYZER_NO_AI'] = true;

// 1. Analyze City Express → manifest → publish-path install.
$a = business_analyzer_analyze(['biz_name' => 'City Express',
    'description' => 'daily bus service Chennai-Bangalore, seat booking, QR tickets, counter payment',
    'services' => 'bus service seat booking']);
ok(($a['category'] ?? '') === 'bus', 'bus analyzed');
$m = business_manifest_build($a, 'busbooking', 'ord-e2e');
$order = ['site_name' => 'City Express', 'slug' => 'busbooking', 'order_id' => 'ord-e2e',
    'admin_username' => 'busadmin', 'admin_email' => 'owner@cityexpress.local',
    'contact_email' => 'book@cityexpress.local',
    'admin_password_hash' => password_hash('StrongPass123!', PASSWORD_DEFAULT)];
try {
    AdminDashboardBuilder::installFromManifest($adminDir, $order, $m);
    ok(true, 'publish-path install succeeds');
} catch (Throwable $e) { ok(false, 'install succeeds (' . $e->getMessage() . ')'); }
foreach (['trips', 'bookings', 'payments', 'routes', 'buses'] as $ent) {
    ok(is_file($adminDir . '/data_' . $ent . '.json'), "data store created: $ent");
}
foreach (['login.php', 'index.php', 'manage.php', 'messages.php', 'content.php', 'settings.php', 'layout.php', 'auth.php', 'manifest.json'] as $f) {
    ok(is_file($adminDir . '/' . $f), "admin file created: $f");
}

// Subprocess caller (shared session file = one login). Args go through a
// temp JSON file — Windows argv quoting mangles inline nested quotes.
$run = function (string $action, array $arg = []) use ($adminDir) {
    $af = tempnam(sys_get_temp_dir(), 'wcarg');
    file_put_contents($af, json_encode($arg));
    $cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/admin_harness.php')
        . ' ' . escapeshellarg($adminDir) . ' ' . escapeshellarg($action)
        . ' ' . escapeshellarg($af);
    $out = [];
    @exec($cmd . ' 2>&1', $out, $code);
    @unlink($af);
    $j = json_decode(implode("\n", $out), true);
    return is_array($j) ? $j : ['ok' => false, 'error' => 'bad harness output: ' . implode('|', array_slice($out, 0, 3))];
};

// 2. Login rejects wrong password, accepts the real one.
$r = $run('login', ['user' => 'busadmin', 'pass' => 'nope12345']);
ok(($r['ok'] ?? false) && empty($r['auth']), 'wrong password rejected');
$r = $run('login', ['user' => 'busadmin', 'pass' => 'StrongPass123!']);
ok(($r['ok'] ?? false) && !empty($r['auth']), 'correct login sets admin session');

// 3. Dashboard shows THIS business: modules, live stats, rules.
$r = $run('get', ['page' => 'index', 'needles' => ['City Express', 'Trips &amp; Schedules', 'Bookings &amp; Tickets', 'Payments', 'Buses &amp; Seats', 'Routes', 'Revenue', 'holds expire after 15 min']]);
ok(($r['ok'] ?? false) && ($r['len'] ?? 0) > 2000, 'dashboard renders');
$allFound = true;
foreach (($r['found'] ?? []) as $n => $f) { if (!$f) { $allFound = false; echo "  missing: $n\n"; } }
ok($allFound, 'dashboard has bus modules + stats + rules');

// 4. Manage pages per entity with real fields; buses status = real dropdown.
$r = $run('get', ['page' => 'manage', 'entity' => 'trips', 'needles' => ['Trips &amp; Schedules', 'Add New Trip', 'Route', 'Departure', 'Fare']]);
ok(($r['ok'] ?? false) && !in_array(false, ($r['found'] ?? [false]), true), 'trips table renders with fields');
$r = $run('get', ['page' => 'manage', 'entity' => 'buses', 'needles' => ['<select', 'active', 'maintenance', 'Plate No', 'Capacity']]);
ok(($r['ok'] ?? false) && !in_array(false, ($r['found'] ?? [false]), true), 'buses status is a real dropdown (active/maintenance)');
$r = $run('get', ['page' => 'manage', 'entity' => 'bookings', 'needles' => ['<select', 'held', 'confirmed', 'unpaid', 'paid', 'Passenger']]);
ok(($r['ok'] ?? false) && !in_array(false, ($r['found'] ?? [false]), true), 'bookings status/payment dropdowns real');
$r = $run('get', ['page' => 'manage', 'entity' => 'payments', 'needles' => ['<select', 'counter', 'online', 'Booking Ref', 'Amount']]);
ok(($r['ok'] ?? false) && !in_array(false, ($r['found'] ?? [false]), true), 'payments method dropdown real');

// 5. Add trip → stored; missing required rejected; edit applies; delete clears.
$r = $run('save', ['entity' => 'trips', 'record' => ['route' => 'Chennai-Bangalore', 'date' => '2026-12-01', 'departure' => '21:30', 'price' => '850', 'total_seats' => '40']]);
ok(($r['ok'] ?? false) && ($r['count'] ?? 0) === 1 && !empty($r['msg_ok']), 'trip added + stored');
$tripId = (string)(($r['rows'][0]['id'] ?? ''));
$r = $run('save', ['entity' => 'trips', 'record' => ['date' => '2026-12-02', 'departure' => '06:00', 'price' => '900', 'total_seats' => '32']]);
ok(($r['ok'] ?? false) && ($r['count'] ?? 99) === 1 && !empty($r['msg_warn']), 'missing required route rejected');
$r = $run('save', ['entity' => 'trips', 'item_id' => $tripId, 'record' => ['route' => 'Chennai-Bangalore', 'date' => '2026-12-01', 'departure' => '21:30', 'price' => '900', 'total_seats' => '40']]);
ok(($r['ok'] ?? false) && (string)(($r['rows'][0]['price'] ?? '')) === '900', 'trip edit applies');
$r = $run('save', ['entity' => 'bookings', 'record' => ['trip_id' => $tripId, 'seats' => 'A1,A2', 'passenger' => 'E2E Rider', 'phone' => '0771112222', 'status' => 'held', 'payment_status' => 'unpaid']]);
ok(($r['ok'] ?? false) && ($r['count'] ?? 0) === 1, 'booking held with real statuses');
$bid = (string)(($r['rows'][0]['id'] ?? ''));
$r = $run('save', ['entity' => 'bookings', 'item_id' => $bid, 'record' => ['trip_id' => $tripId, 'seats' => 'A1,A2', 'passenger' => 'E2E Rider', 'phone' => '0771112222', 'status' => 'bogus', 'payment_status' => 'unpaid']]);
ok(($r['ok'] ?? false) && !empty($r['msg_warn']), 'invalid status option rejected');
$r = $run('delete', ['entity' => 'bookings', 'item_id' => $bid]);
ok(($r['ok'] ?? false) && ($r['count'] ?? 99) === 0, 'booking deleted');
$r = $run('delete', ['entity' => 'trips', 'item_id' => $tripId]);
ok(($r['ok'] ?? false) && ($r['count'] ?? 99) === 0, 'trip deleted');

// 6. Inbox / content / settings render (no fatals).
foreach (['messages', 'content', 'settings'] as $pg) {
    $r = $run('get', ['page' => $pg]);
    ok(($r['ok'] ?? false) && ($r['len'] ?? 0) > 500, "$pg renders");
}

// 7. Password changes persist in the exact auth file used by login.
$r = $run('change_password', ['current' => 'StrongPass123!', 'new' => 'UpdatedPass456!']);
ok(($r['ok'] ?? false) && !empty($r['changed']), 'settings accepts valid password change');
$auth = json_decode((string)@file_get_contents($adminDir . '/.auth.json'), true);
ok(is_array($auth) && password_verify('UpdatedPass456!', (string)($auth['hash'] ?? ''))
    && !password_verify('StrongPass123!', (string)($auth['hash'] ?? '')), 'new password saved to login auth file; old password rejected');
$r = $run('login', ['user' => 'busadmin', 'pass' => 'StrongPass123!']);
ok(($r['ok'] ?? false) && empty($r['auth']), 'generated login rejects old password');
$r = $run('login', ['user' => 'busadmin', 'pass' => 'UpdatedPass456!']);
ok(($r['ok'] ?? false) && !empty($r['auth']), 'generated login accepts changed password');

rmdir_r($tmp);
echo $fail === 0 ? "\nALL ADMIN-LOGIN-CRUD TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
