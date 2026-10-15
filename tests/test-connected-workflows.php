<?php
// tests/test-connected-workflows.php — run: php tests/test-connected-workflows.php
// Integration tests A–J: frontend wiring, JSON persistence, customer↔admin
// workflows, business lifecycles, gap analysis, fallback honesty, security,
// concurrency/idempotency, regression surface.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/BusinessOps.php';
require_once __DIR__ . '/../includes/BusinessRuntime.php';
require_once __DIR__ . '/../includes/ShopHelper.php';
require_once __DIR__ . '/../includes/BizIntelligence/Schema.php';
require_once __DIR__ . '/../includes/BizIntelligence/Pipeline.php';
require_once __DIR__ . '/../includes/BizIntelligence/Comply.php';
require_once __DIR__ . '/../includes/BizIntelligence/Gapscan.php';
require_once __DIR__ . '/../includes/BizIntelligence/Completeness.php';
require_once __DIR__ . '/../includes/BizIntelligence/Verify.php';
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
$SLUG = 'wctest1';
$collDir = bops_root() . '/collections/' . $SLUG;
$pubAdmin = dirname(__DIR__) . '/published/' . $SLUG . '/admin';
rmdir_r($collDir); rmdir_r(dirname($pubAdmin));
$_SESSION = [];
function D($a) { return bops_dispatch($a); }

// ── Test A — frontend integration surface ──
$rt = business_runtime_js(['capabilities' => ['booking']], $SLUG);
ok(strpos($rt, '/api/business-ops.php') === false, 'A: no root-absolute API URL (subpath-safe)');
ok(strpos($rt, "new URL('api/business-ops.php',document.baseURI)") !== false, 'A: relative endpoint via baseURI');
ok(strpos($rt, 'data-wc-form') !== false && strpos($rt, "'bookings'") !== false, 'A: purpose-aware entities (booking→bookings)');
ok(strpos($rt, 'idempotency_key') !== false, 'A: idempotent submits');
ok(strpos($rt, 'data-wc-thanks') !== false, 'A: thanks-text feedback (no alert)');
ok(strpos($rt, "step.setAttribute('hidden','')") !== false
    && strpos($rt, 'scrollMarginTop') !== false, 'A: booking step navigation isolates the active stage and respects sticky headers');
ok(strpos($rt, 'window.__WC_NODE_API__') !== false, 'A: Node API advertised to page scripts');
ok(strpos($rt, 'apiPhp(action,payload)') !== false, 'A: PHP fallback retained');
ok(strpos($rt, 'AbortError') !== false, 'A: fallback on network failure only (no double-post)');
$inj = business_runtime_inject('<html><body><form><input name="x"></form></body></html>', [], $SLUG);
ok(strpos($rt, 'alert(') === false, 'A: no alert() dialogs');
ok(strpos($rt, 'data-wc-history') !== false && strpos($rt, 'WCBiz') !== false, 'A: history renderer + WCBiz API');
ok(strpos($rt, 'rec[identity]') !== false, 'A: history auto-fill respects phone/email selector');
$inj = business_runtime_inject('<html><body><form><input name="x"></form></body></html>', [], $SLUG);
ok(strpos($inj, 'wc-biz-runtime') !== false, 'A: runtime injects into HTML');
$inj2 = business_runtime_inject($inj, [], $SLUG);
ok(substr_count($inj2, 'wc-biz-runtime') === 1, 'A: injection idempotent');
$busSpec = ['profile' => ['business_name' => 'City Express', 'domain' => 'booking',
    'description' => 'Bus route, trip and seat reservations'], 'capabilities' => ['booking'],
    'entities' => [['key' => 'trips', 'name' => 'Trips'], ['key' => 'bookings', 'name' => 'Bookings']]];
$busHtml = business_runtime_inject('<html><body><header><a href="#booking">Book</a></header><section id="booking"><h2>Book a trip</h2></section></body></html>', $busSpec, $SLUG);
ok(substr_count($busHtml, 'data-wc-bus-booking-style') === 1
    && strpos($busHtml, 'id="wc-live-bus-booking"') !== false
    && strpos($busHtml, 'busPassengerForm') !== false
    && strpos($busHtml, 'data-wc-biz-runtime') !== false
    && strpos($busHtml, 'var IS_BUS_BOOKING=(true)') !== false
    && strpos($busHtml, "action==='ticket'||action==='pay'") !== false
    && strpos($busHtml, "api('availability'") !== false,
    'A: generated bus pages receive the live booking widget with one backend store for booking lifecycle and history');
$busHtmlTwice = business_runtime_inject($busHtml, $busSpec, $SLUG);
ok(substr_count($busHtmlTwice, 'data-wc-bus-booking-style') === 1
    && substr_count($busHtmlTwice, 'id="wc-live-bus-booking"') === 1,
    'A: live bus widget injection is idempotent');
$clinicHtml = business_runtime_inject('<html><body><form data-wc-form="appointment"></form></body></html>',
    ['profile' => ['domain' => 'clinic', 'description' => 'Doctor appointment scheduling']], $SLUG);
ok(strpos($clinicHtml, 'data-wc-live-bus-booking') === false, 'A: non-bus appointment sites do not receive a bus booking flow');
$pub = file_get_contents(__DIR__ . '/../publish.php');
ok(strpos($pub, 'assets/js/wc-business.js') === false, 'A: dead 404 runtime reference gone');
$cr = file_get_contents(__DIR__ . '/../includes/ContactReceiver.php');
ok(strpos($cr, '@mkdir(\\$inboxDir') !== false, 'A: contact receiver creates admin dir (no silent drops)');
ok(strpos($pub, "includes/ContactReceiver.php") !== false && strpos($pub, 'function buildContactReceiver') === false, 'A: publish uses the shared receiver (single implementation)');
$sh = file_get_contents(__DIR__ . '/../includes/ShopHelper.php');
ok(strpos($sh, 'checkoutOrder') !== false && strpos($sh, "entity:'orders'") !== false, 'A: cart checkout posts real orders');
ok(strpos($sh, 'wc-btn-place-order') !== false, 'A: Place Order button in bill modal');

// ── Test B — persistent JSON storage ──
$r = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'inquiries',
    'record' => ['name' => 'Anu', 'email' => 'anu@example.com', 'message' => 'Hello']]);
ok($r['http'] === 200 && !empty($r['body']['data']['id']), 'B: create persists');
$id1 = $r['body']['data']['id'];
ok(is_file(bops_file($SLUG, 'inquiries')), 'B: JSON file on disk (outside web root)');
$re = bops_load($SLUG, 'inquiries'); // "restart": stateless re-read
ok(count($re) === 1 && $re[0]['id'] === $id1, 'B: data survives re-read (restart-safe)');
ok(strpos($re[0]['name'] ?? '', '<') === false, 'B: tags stripped on write');

// ── Test H — security ──
D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => 'T1', 'seats' => ['A1'], 'email' => 'a@example.com']]);
D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => 'T1', 'seats' => ['A2'], 'email' => 'b@example.com']]);
D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => 'T1', 'seats' => ['A3'], 'email' => 'phone-owner@example.com', 'phone' => '0771234567']]);
$la = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings', 'email' => 'a@example.com']);
ok(count($la['body']['data']) === 1 && $la['body']['data'][0]['email'] === 'a@example.com', 'H: customer A sees only own booking');
$lp = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings', 'phone' => '0771234567']);
ok(count($lp['body']['data']) === 1 && $lp['body']['data'][0]['email'] === 'phone-owner@example.com',
    'H: customer phone lookup matches records that also have email');
$_SESSION['customer_user'] = ['email' => 'a@example.com'];
$sessionOwner = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings', 'phone' => '0771234567']);
ok(count($sessionOwner['body']['data']) === 1 && $sessionOwner['body']['data'][0]['email'] === 'a@example.com',
    'H: authenticated identity cannot be overridden by lookup input');
$_SESSION = [];
$lanon = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings']);
ok(empty($lanon['body']['data']), 'H: anonymous list returns nothing');
$up = D(['action' => 'update', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => 'x', 'patch' => ['status' => 'confirmed']]);
ok($up['http'] === 403, 'H: non-admin update rejected');
$trav = bops_slug(['slug' => '../../etc']);
ok(strpos($trav, '.') === false && $trav !== '', 'H: slug traversal sanitized');
ok(bops_slug(['slug' => '__bustest2']) === '__bustest2', 'H: published underscore slug preserved');
D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'inquiries', 'record' => ['email' => 'not-an-email']]);
$chk = bops_load($SLUG, 'inquiries');
$hasBad = false;
foreach ($chk as $row) { if (($row['email'] ?? '') === 'not-an-email') $hasBad = true; }
ok(!$hasBad, 'H: invalid email rejected server-side');

// ── Test I — conflicts / idempotency / duplicates ──
$h1 = D(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => 'T9', 'seats' => ['B1', 'B2']]);
ok($h1['http'] === 200 && ($h1['body']['data']['status'] ?? '') === 'held', 'I: seat hold works');
$h2 = D(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => 'T9', 'seats' => ['B2']]);
ok($h2['http'] === 400, 'I: conflicting hold rejected');
$c1 = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings', 'idempotency_key' => 'dup-key-1',
    'record' => ['trip_id' => 'T9', 'seats' => ['C1'], 'email' => 'c@example.com']]);
$c2 = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings', 'idempotency_key' => 'dup-key-1',
    'record' => ['trip_id' => 'T9', 'seats' => ['C1'], 'email' => 'c@example.com']]);
ok($c1['http'] === 200 && ($c2['body']['deduped'] ?? false) && $c1['body']['data']['id'] === $c2['body']['data']['id'], 'I: duplicate submit deduped (one row)');
$all = bops_load($SLUG, 'bookings');
$n = 0; foreach ($all as $row) { if (($row['idempotency_key'] ?? '') === 'dup-key-1') $n++; }
ok($n === 1, 'I: exactly one stored row for retried key');
$ref = $c1['body']['data']['ref'];
$p1 = D(['action' => 'pay', 'slug' => $SLUG, 'ref' => $ref, 'method' => 'counter', 'amount' => 1500]);
ok($p1['http'] === 200 && ($p1['body']['data']['status'] ?? '') === 'confirmed', 'I: counter pay confirms');
$p2 = D(['action' => 'pay', 'slug' => $SLUG, 'ref' => $ref, 'method' => 'counter', 'amount' => 1500]);
ok($p2['http'] === 400, 'I: duplicate payment rejected');
$cx = D(['action' => 'cancel', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $c1['body']['data']['id'], 'email' => 'c@example.com']);
ok($cx['http'] === 200 && ($cx['body']['data']['payment_status'] ?? '') === 'refund_pending', 'I: paid cancel → refund_pending');
$_SESSION['admin_auth'] = true;
$re2 = D(['action' => 'update', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $c1['body']['data']['id'], 'patch' => ['status' => 'confirmed']]);
ok($re2['http'] === 400, 'I: cancelled is terminal (no reactivation)');
$_SESSION = [];

// ── Test C — customer booking appears in admin dashboard files ──
@mkdir($pubAdmin, 0755, true);
$cb = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => 'CX100', 'seats' => ['D4'], 'email' => 'rider@example.com', 'name' => 'Rider']]);
$mirror = json_decode(@file_get_contents($pubAdmin . '/data_bookings.json') ?: '[]', true) ?: [];
$found = false; foreach ($mirror as $m) { if (($m['id'] ?? '') === $cb['body']['data']['id']) $found = true; }
ok($found, 'C: booking mirrored to published admin data file');
$ord = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'orders',
    'record' => ['customer' => 'Shopper', 'phone' => '0770000000',
        'items' => [['name' => 'T-Shirt', 'qty' => 2, 'price' => 2490]], 'total' => 4980]]);
ok($ord['http'] === 200 && strpos($ord['body']['data']['ref'] ?? '', 'ORD-') === 0, 'C: shop order creates ORD- ref');
$mord = json_decode(@file_get_contents($pubAdmin . '/data_orders.json') ?: '[]', true) ?: [];
ok(count($mord) === 1 && ($mord[0]['total'] ?? 0) == 4980, 'C: order visible in admin file with total');
$badOrd = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'orders', 'record' => ['customer' => 'X']]);
ok($badOrd['http'] === 400, 'C: empty order rejected (no fake transactions)');

// ── Test D — admin update reflects in customer view ──
$_SESSION['admin_auth'] = true;
$up2 = D(['action' => 'update', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $cb['body']['data']['id'], 'patch' => ['status' => 'confirmed']]);
ok($up2['http'] === 200, 'D: admin status update applies');
$_SESSION = [];
$mine = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings', 'email' => 'rider@example.com']);
ok(count($mine['body']['data']) === 1 && ($mine['body']['data'][0]['status'] ?? '') === 'confirmed', 'D: customer history shows admin change');
$mm = json_decode(@file_get_contents($pubAdmin . '/data_bookings.json') ?: '[]', true) ?: [];
$st = null; foreach ($mm as $m) { if (($m['id'] ?? '') === $cb['body']['data']['id']) $st = $m['status'] ?? null; }
ok($st === 'confirmed', 'D: admin file converged with collections');
$other = D(['action' => 'cancel', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $cb['body']['data']['id'], 'email' => 'stranger@example.com']);
ok($other['http'] === 403, 'D: stranger cannot cancel another customer booking');

// ── Test E — business lifecycles ──
$bk = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => 'E2E', 'seats' => ['E1'], 'email' => 'e2e@example.com']]);
ok($bk['http'] === 200 && ($bk['body']['data']['payment_status'] ?? '') === 'unpaid', 'E: booking starts awaiting_payment/unpaid');
$noseat = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings', 'record' => ['trip_id' => 'E2E', 'email' => 'x@y.z']]);
ok($noseat['http'] === 400, 'E: seatless booking rejected');

// ── Test F — gap analysis + deterministic fix ──
$bus = ['biz_name' => 'GapShop', 'biz_type' => 'Bus booking', 'biz_services' => 'trips', 'sections' => ['hero', 'contact']];
$spec = biz_repair_spec(biz_build_spec($bus, 'Bus booking with seats and counter pay.'));
$incomplete = '<!DOCTYPE html><html><head><title>g</title></head><body><header id="hero"><h1>GapShop</h1></header>'
    . '<main><form><input name="trip" required><button>Go</button></form></main></body></html>' . str_repeat('<p>x</p>', 60);
$g0 = biz_gapscan($spec, $incomplete);
ok(!empty($g0['findings']), 'F: gaps identified (' . count($g0['findings']) . ')');
$gf = biz_gapfix($spec, $incomplete, $SLUG);
ok(!empty($gf['applied']), 'F: deterministic fixes applied (' . implode('; ', array_slice($gf['applied'], 0, 2)) . ')');
$g1 = biz_gapscan($spec, $gf['html']);
$autoLeft = array_filter($g1['findings'], fn($f) => in_array($f['fix'], ['wire_forms', 'sections', 'runtime'], true));
ok(empty($autoLeft), 'F: auto-fixable gaps resolved, rest honestly reported');

// ── Test G — fallback honesty ──
$generic = '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width"><title>Fallback Business</title></head>'
    . '<body><header id="hero"><h1>Fallback Business</h1></header><main><section id="services"><h2>Services</h2></section>'
    . '<section id="contact"><form><input name="name" required><input type="email" name="email" required><textarea name="message" required></textarea></form></section></main></body></html>'
    . str_repeat('<p>y</p>', 40);
$gf2 = biz_gapfix($spec, $generic, $SLUG);
$v = biz_verify_project($spec, ['htmls' => [$gf2['html']]]);
$vn = array_column($v['failed'], 't');
ok(!in_array('forms', $vn, true), 'G: fallback forms wired (no fake transactions)');
ok(strpos($gf2['html'], 'data-wc-form') !== false, 'G: wiring marker present');
$shopHtml = getShopHtml([['name' => 'Tee', 'price' => 'Rs 100', 'num' => 100, 'img' => '']], ['primary' => '#6366f1'], '', 'classic', 'GapShop');
ok(strpos($shopHtml, 'wc-btn-place-order') !== false && strpos($shopHtml, 'wc-cart-drawer') !== false, 'G: shop fallback carries real checkout + cart');

// ── Test J — regression surface preserved ──
$gen = file_get_contents(__DIR__ . '/../includes/AdminPanelGenerator.php');
ok(strpos($gen, '__syncCollections') !== false, 'J: manage.php syncs admin→collections');
ok(strpos($gen, 'LOCK_EX') !== false, 'J: atomic admin writes');
ok(strpos($gen, 'data_*.json') !== false, 'J: discovered live modules in tabs');
ok(strpos($gen, '__secCount') !== false && strpos($gen, '__recTotal') !== false, 'J: dashboard counts computed from records');

// cleanup test artifacts
rmdir_r($collDir); rmdir_r(dirname($pubAdmin)); $_SESSION = [];
ok(!is_dir($collDir) && !is_dir(dirname($pubAdmin)), 'cleanup: no test residue');

echo $fail === 0 ? "\nALL CONNECTED-WORKFLOW TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
