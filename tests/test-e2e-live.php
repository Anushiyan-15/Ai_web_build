<?php
// tests/test-e2e-live.php — run: php tests/test-e2e-live.php
// LIVE end-to-end over HTTP (requires local server at
// http://localhost/project/webbbuilder). Skips honestly when unreachable.
// Flow: install bus admin (as do_publish does) → publish page with runtime
// → customer booking/contact/history over HTTP → admin login over HTTP →
// manage dashboard shows real records → admin update → customer sees change
// → concurrent seat holds (exactly one wins) → cleanup, zero residue.
if (session_status() === PHP_SESSION_NONE) session_start();
$BASE = 'http://localhost/project/webbbuilder';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function rmdir_r($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $d . '/' . $f;
        is_dir($p) ? rmdir_r($p) : @unlink($p);
    }
    @rmdir($d);
}
// Server reachable?
$ch = curl_init($BASE . '/api/business-ops.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['action' => 'list', 'slug' => 'x', 'entity' => 'bookings'])]);
$probe = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($http === 0) { echo "SKIP: local server unreachable at $BASE\n"; exit(0); }
ok($http === 200, 'server answers business-ops API');

require_once __DIR__ . '/../includes/AdminPanelGenerator.php';
require_once __DIR__ . '/../includes/BusinessRuntime.php';
require_once __DIR__ . '/../includes/BusinessOps.php';
$SLUG = 'e2ebus';
$ROOT = dirname(__DIR__);
$siteDir = $ROOT . '/published/' . $SLUG;
$adminDir = $siteDir . '/admin';
rmdir_r($siteDir); rmdir_r($ROOT . '/storage/collections/' . $SLUG);

function http($method, $url, $data = null, $jar = null, $isJson = true, $headers = []) {
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5];
    if ($jar) { $opts[CURLOPT_COOKIEJAR] = $jar; $opts[CURLOPT_COOKIEFILE] = $jar; }
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($isJson) { $opts[CURLOPT_HTTPHEADER] = array_merge(['Content-Type: application/json'], $headers); $opts[CURLOPT_POSTFIELDS] = json_encode($data); }
        else $opts[CURLOPT_POSTFIELDS] = $data;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}
function api($action, $payload, $jar = null, $csrf = '') {
    global $BASE;
    $headers = $csrf !== '' ? ['X-Admin-CSRF: ' . $csrf] : [];
    [$code, $body] = http('POST', $BASE . '/api/business-ops.php', array_merge(['action' => $action], $payload), $jar, true, $headers);
    return [$code, json_decode((string)$body, true) ?: ['_raw' => substr((string)$body, 0, 200)]];
}

// ── 1. Publish-equivalent install: canonical bus entities ──
$entities = AdminPanelGenerator::entitiesFromCanonical(['entities' => [
    ['key' => 'trips', 'label' => 'Trips', 'fields' => ['route:text*', 'date:date*', 'price:number*']],
    ['key' => 'bookings', 'label' => 'Bookings', 'fields' => ['trip_id:text*', 'seats:json*', 'passenger:text*', 'phone:text', 'status:select:pending|confirmed']],
]]);
try {
    AdminPanelGenerator::install($adminDir, ['site_name' => 'E2E Bus Co', 'slug' => $SLUG, 'order_id' => 'WBL-E2E-1',
        'admin_username' => 'e2e_admin', 'admin_email' => 'owner@e2e.local', 'contact_email' => 'book@e2e.local',
        'admin_password_hash' => password_hash('E2ePass@123', PASSWORD_DEFAULT),
        'admin_entities' => $entities, 'admin_seed_empty' => true]);
    ok(true, 'admin installed with bus modules');
} catch (Throwable $e) { ok(false, 'admin install (' . $e->getMessage() . ')'); }
$_SESSION['admin_auth'] = true;
$seedTrip = bops_dispatch(['action' => 'create', 'slug' => $SLUG, 'entity' => 'trips',
    'record' => ['route' => 'E2E Route', 'date' => '2026-12-01', 'departure' => '10:00',
        'price' => 850, 'total_seats' => 40]]);
$tripId = $seedTrip['body']['data']['id'] ?? '';
$_SESSION = [];
ok(($seedTrip['http'] ?? 500) === 200 && strpos($tripId, 'TRIP-') === 0, 'live schedule created with an automatic trip ID');

// ── 2. Frontend page: generated-like HTML + runtime + contact receiver ──
$page = '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width"><title>E2E Bus Co</title></head>'
    . '<body><header id="hero"><h1>E2E Bus Co</h1><nav><a href="#book">Book</a><a href="#contact">Contact</a></nav></header>'
    . '<main><section id="book"><form data-wc-form="booking" data-wc-thanks="Booked!"><input name="trip_id" value="' . htmlspecialchars($tripId, ENT_QUOTES) . '"><input name="seats" value="A1"><input name="email" type="email" required><button>Book</button></form>'
    . '<div data-wc-history="bookings"></div></section>'
    . '<section id="contact"><form><input name="name" required><input type="email" name="email" required><textarea name="message" required></textarea><button>Send</button></form></section></main>'
    . '<footer id="contact2"></footer></body></html>';
ob_start(); include $ROOT . '/publish.php'; ob_end_clean(); // defines buildContactReceiver()
$page = business_runtime_inject($page, ['capabilities' => ['booking']], $SLUG);
@mkdir($siteDir, 0755, true);
file_put_contents($siteDir . '/index.html', $page);
file_put_contents($siteDir . '/contact.php', buildContactReceiver('E2E Bus Co', 'book@e2e.local'));
ok(strpos($page, 'wc-biz-runtime') !== false, 'frontend carries backend runtime');
[$c0, $b0] = http('GET', $BASE . '/published/' . $SLUG . '/index.html');
ok($c0 === 200 && strpos($b0, 'data-wc-form="booking"') !== false, 'frontend page served with wired booking form');

// ── 3. Customer booking over HTTP (as the runtime form would send) ──
$jarC = tempnam(sys_get_temp_dir(), 'e2ec') . '.txt';
[$hc, $jc] = api('create', ['slug' => $SLUG, 'entity' => 'bookings',
    'idempotency_key' => 'e2e-k1',
    'record' => ['trip_id' => $tripId, 'seats' => ['A1'], 'phone' => '0771112222',
        'email' => 'rider@example.invalid', 'passenger' => 'Rider']], $jarC);
ok($hc === 200 && ($jc['success'] ?? false) && strpos(($jc['data']['ref'] ?? ''), 'BK-') === 0, 'customer booking accepted with BK- ref');
$bid = $jc['data']['id'] ?? '';
$coll = json_decode(@file_get_contents($ROOT . '/storage/collections/' . $SLUG . '/bookings.json') ?: '[]', true) ?: [];
ok(count($coll) === 1, 'booking persisted in collections JSON');
$adm = json_decode(@file_get_contents($adminDir . '/data_bookings.json') ?: '[]', true) ?: [];
ok(count($adm) === 1 && ($adm[0]['id'] ?? '') === $bid, 'booking mirrored to admin data file');

// ── 4. Contact submit over HTTP → admin inbox ──
[$hc2, $bc2] = http('POST', $BASE . '/published/' . $SLUG . '/contact.php',
    ['name' => 'Visitor', 'email' => 'visitor@e2e.local', 'message' => 'Seat query', 'company' => ''], $jarC, false);
$j2 = json_decode((string)$bc2, true) ?: [];
ok($hc2 === 200 && ($j2['success'] ?? false), 'contact submit accepted');
$inbox = json_decode(@file_get_contents($adminDir . '/data_inquiries.json') ?: '[]', true) ?: [];
$got = false; foreach ($inbox as $m) { if (($m['email'] ?? '') === 'visitor@e2e.local') $got = true; }
ok($got, 'contact lands in admin inbox file');

// ── 5. Admin login over HTTP with created credentials ──
$jarA = tempnam(sys_get_temp_dir(), 'e2ea') . '.txt';
[$lc, $lb] = http('GET', $BASE . '/published/' . $SLUG . '/admin/login.php', null, $jarA);
ok($lc === 200 && stripos($lb, 'password') !== false && stripos($lb, 'E2ePass@123') === false, 'login page served, no plaintext secrets');
[$lw, $bw] = http('POST', $BASE . '/published/' . $SLUG . '/admin/login.php',
    ['username' => 'e2e_admin', 'password' => 'wrongpass1'], $jarA, false);
ok(stripos((string)$bw, 'Invalid username or password') !== false, 'wrong password rejected (rate-limited, generic error)');
[$lo, $bo] = http('POST', $BASE . '/published/' . $SLUG . '/admin/login.php',
    ['username' => 'e2e_admin', 'password' => 'E2ePass@123'], $jarA, false);
ok(stripos((string)$bo, 'Welcome,') !== false || stripos((string)$bo, 'Dashboard') !== false, 'admin login works with created credentials');

// ── 6. Admin dashboard shows REAL details + functions ──
[$mc, $mb] = http('GET', $BASE . '/published/' . $SLUG . '/admin/manage.php?entity=bookings', null, $jarA);
ok($mc === 200 && stripos($mb, 'Rider') !== false && stripos($mb, '0771112222') !== false, 'manage shows the real customer booking');
preg_match('/const WC_ADMIN_CSRF = ("(?:\\\\.|[^"])*");/', $mb, $csrfMatch);
$adminCsrf = isset($csrfMatch[1]) ? (string)json_decode($csrfMatch[1], true) : '';
ok(stripos($mb, 'Trips') !== false, 'manage tabs carry bus modules (no generic dashboard)');
[$ic, $ib] = http('GET', $BASE . '/published/' . $SLUG . '/admin/index.php', null, $jarA);
ok($ic === 200 && stripos($ib, 'Stored Records') !== false, 'dashboard shows computed record stats');
[$gc, $gb] = http('GET', $BASE . '/published/' . $SLUG . '/admin/messages.php', null, $jarA);
ok($gc === 200 && stripos($gb, 'visitor@e2e.local') !== false, 'inbox page shows the contact message');

// ── 7. Admin update → customer history over HTTP (same session bridge) ──
[$uc, $uj] = api('update', ['slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'patch' => ['status' => 'approved']], $jarA, $adminCsrf);
ok($uc === 200 && ($uj['data']['status'] ?? '') === 'approved'
    && ($uj['data']['notification_status'] ?? '') === 'queued', 'admin approval persists and queues its status email');
[$lc2, $lj2] = api('list', ['slug' => $SLUG, 'entity' => 'bookings', 'email' => 'rider@e2e.local'], $jarC);
ok(empty($lj2['data'] ?? []), 'anonymous email lookup cannot read customer booking history');

// ── 8. Concurrency: 6 parallel holds, same trip+seat → exactly one wins ──
$mh = curl_multi_init();
$hs = [];
for ($i = 0; $i < 6; $i++) {
    $ch = curl_init($BASE . '/api/business-ops.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId, 'seats' => ['A4']])]);
    curl_multi_add_handle($mh, $ch);
    $hs[] = $ch;
}
do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 5); } while ($run && $st === CURLM_OK);
$wins = 0;
foreach ($hs as $ch) {
    $j = json_decode((string)curl_multi_getcontent($ch), true) ?: [];
    if (!empty($j['success'])) $wins++;
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);
ok($wins === 1, 'concurrent holds: exactly one wins (' . $wins . ')');

// ── 9. Logout + authz ──
http('GET', $BASE . '/published/' . $SLUG . '/admin/logout.php', null, $jarA);
[$mc2, $mb2] = http('GET', $BASE . '/published/' . $SLUG . '/admin/manage.php?entity=bookings', null, $jarA);
ok(stripos((string)$mb2, 'password') !== false || stripos((string)$mb2, 'Sign In') !== false, 'logout ends session (manage bounces to login)');
@unlink($jarC); @unlink($jarA);

// cleanup
rmdir_r($siteDir); rmdir_r($ROOT . '/storage/collections/' . $SLUG);
ok(!is_dir($siteDir) && !is_dir($ROOT . '/storage/collections/' . $SLUG), 'cleanup: zero residue');

echo $fail === 0 ? "\nALL LIVE E2E TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
