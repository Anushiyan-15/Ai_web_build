<?php
// tests/test-bus-booking-e2e.php — run: php tests/test-bus-booking-e2e.php
// Master-prompt §11 bus flow, all 12 steps against the real backend:
// admin seeds route/bus/schedule → customer searches DB → hold → book (ref)
// → retrieve → admin manages → seats protected → rejections → admin change
// reflected → persistence. Plus runtime search/catalog wiring checks.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/BusinessOps.php';
require_once __DIR__ . '/../includes/BusinessRuntime.php';
require_once __DIR__ . '/../includes/BusinessAnalyzer.php';
require_once __DIR__ . '/../includes/BusinessManifest.php';
require_once __DIR__ . '/../includes/MissingFunctionAnalyzer.php';
require_once __DIR__ . '/../includes/AdminDashboardBuilder.php';
require_once __DIR__ . '/../includes/BizIntelligence/Comply.php';
require_once __DIR__ . '/../includes/AutoSiteGenerator.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function rmdir_r($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = $d . '/' . $f;
        is_dir($p) ? rmdir_r($p) : @unlink($p); }
    @rmdir($d);
}
$SLUG = 'wcbuse2e';
$LEGACY_SLUG = '__wcbuse2e_legacy';
$DATE = '2026-11-20';
rmdir_r(bops_root() . '/collections/' . $SLUG);
rmdir_r(bops_root() . '/sites/' . $SLUG);
rmdir_r(dirname(__DIR__) . '/published/' . $SLUG);
rmdir_r(bops_root() . '/collections/' . $LEGACY_SLUG);
rmdir_r(bops_root() . '/sites/' . $LEGACY_SLUG);
rmdir_r(dirname(__DIR__) . '/published/' . $LEGACY_SLUG);
$_SESSION = [];
$D = fn($a) => bops_dispatch($a);

// A legacy published admin may have manually maintained trips before the
// operational store exists. Import those schedules once so customer search
// and seat availability use the same persistent records.
$legacyAdmin = dirname(__DIR__) . '/published/' . $LEGACY_SLUG . '/admin';
@mkdir($legacyAdmin, 0755, true);
$legacyTrip = ['id' => 'legacy_trip_1', 'route' => 'Legacy City-Next City',
    'date' => $DATE, 'departure' => '09:00', 'price' => 500, 'total_seats' => 20];
file_put_contents($legacyAdmin . '/data_trips.json', json_encode([$legacyTrip]));
$importedTrips = bops_load($LEGACY_SLUG, 'trips');
ok(count($importedTrips) === 1 && ($importedTrips[0]['id'] ?? '') === 'legacy_trip_1',
    '0: published admin schedules bootstrap the operational trip store');

// 1. Admin creates route + bus + schedule.
$_SESSION['admin_auth'] = true;
$r = $D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'routes',
    'record' => ['origin' => 'Chennai', 'destination' => 'Bangalore', 'fare' => 850]]);
ok($r['http'] === 200, '1: route created');
$b = $D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'buses',
    'record' => ['plate' => 'TN01AB1234', 'capacity' => 40, 'status' => 'active']]);
ok($b['http'] === 200, '1: bus registered');
$t = $D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'trips',
    'record' => ['route' => 'Chennai-Bangalore', 'date' => $DATE, 'departure' => '21:30', 'price' => 850, 'total_seats' => 40]]);
ok($t['http'] === 200, '1: schedule created');
$tripId = $t['body']['data']['id'];
$_SESSION = [];

// Public availability exposes only capacity and reserved seat labels.
$av0 = $D(['action' => 'availability', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId]);
ok($av0['http'] === 200 && ($av0['body']['data']['total_seats'] ?? 0) === 40
    && empty($av0['body']['data']['reserved_seats']), '1b: public seat availability returns capacity without customer data');

// 2+3. Anonymous customer searches route+date → real schedule from DB.
$s = $D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'trips', 'route' => 'chennai', 'date' => $DATE]);
ok($s['http'] === 200 && count($s['body']['data']) >= 1, '2: public search finds schedules');
$found = null;
foreach ($s['body']['data'] as $row) { if (($row['id'] ?? '') === $tripId) $found = $row; }
ok($found && (float)($found['price'] ?? 0) == 850, '3: retrieved schedule is the real DB row (price 850)');
// Private entities stay hidden from anonymous search.
$pb = $D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings']);
ok($pb['http'] === 200 && empty($pb['body']['data']), '2b: bookings not publicly listable');

// 4. Seat hold during checkout.
$h = $D(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId, 'seats' => ['A1', 'A2']]);
ok($h['http'] === 200 && ($h['body']['data']['status'] ?? '') === 'held', '4: seats held (15-min)');
$av1 = $D(['action' => 'availability', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId]);
ok(in_array('A1', $av1['body']['data']['reserved_seats'] ?? [], true)
    && in_array('A2', $av1['body']['data']['reserved_seats'] ?? [], true), '4b: held seats immediately show unavailable');
$badSeat = $D(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId, 'seats' => ['Z1']]);
ok($badSeat['http'] === 400, '4c: seat outside trip layout rejected');
$release = $D(['action' => 'release_hold', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $h['body']['data']['ref']]);
ok($release['http'] === 200, '4d: customer can release their hold');
$h = $D(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId, 'seats' => ['A1', 'A2']]);
ok($h['http'] === 200, '4e: released seats can be held again');

// 5+6. Confirm booking → unique ref persisted.
$c = $D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => $tripId, 'seats' => ['A3'], 'passenger' => 'E2E Rider',
        'phone' => '0771112222', 'email' => 'e2erider@example.com']]);
ok($c['http'] === 200 && strpos($c['body']['data']['ref'] ?? '', 'BK-') === 0, '5: booking confirmed with BK- ref');
$ref = $c['body']['data']['ref'];
$bid = $c['body']['data']['id'];
$all = bops_load($SLUG, 'bookings');
$real = false; foreach ($all as $row) { if (($row['ref'] ?? '') === $ref) $real = true; }
ok($real, '6: ticket ref corresponds to a real stored booking');

// 6b. Hold → confirm on the SAME seats (the real customer flow): the own
// live hold converts instead of blocking its own confirmation.
$hOwn = $D(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId, 'seats' => ['B1', 'B2']]);
ok($hOwn['http'] === 200, '6b: own seats held first');
$holdRef = $hOwn['body']['data']['ref'] ?? '';
$cOwn = $D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => $tripId, 'seats' => ['B1', 'B2'], 'passenger' => 'Hold Converter',
        'phone' => '0772223333', 'hold_ref' => $holdRef]]);
ok($cOwn['http'] === 200 && strpos($cOwn['body']['data']['ref'] ?? '', 'BK-') === 0, '6b: own hold converts to ticket (no self-conflict)');
$leftover = false;
foreach (bops_load($SLUG, 'bookings') as $row) { if (($row['ref'] ?? '') === $holdRef) $leftover = true; }
ok(!$leftover, '6b: consumed hold row removed (no stray held rows)');

// 7. Customer retrieves own booking (and only own).
$mine = $D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings', 'email' => 'e2erider@example.com']);
ok(count($mine['body']['data']) === 1 && ($mine['body']['data'][0]['ref'] ?? '') === $ref, '7: customer ticket lookup works');
// Payment pending (no gateway configured) — never fake-paid.
ok(($mine['body']['data'][0]['payment_status'] ?? '') === 'unpaid', '7b: booking honestly unpaid until verified pay');
$p = $D(['action' => 'pay', 'slug' => $SLUG, 'ref' => $ref, 'method' => 'counter', 'amount' => 850]);
ok($p['http'] === 200 && ($p['body']['data']['payment_status'] ?? '') === 'paid', '7c: verified counter pay recorded');

// 8. Admin finds + manages the same booking.
$_SESSION['admin_auth'] = true;
$al = $D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings']);
$seen = false; foreach ($al['body']['data'] as $row) { if (($row['ref'] ?? '') === $ref) $seen = true; }
ok($seen, '8: booking immediately visible to admin');
$u = $D(['action' => 'update', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'patch' => ['status' => 'confirmed']]);
ok($u['http'] === 200, '8b: admin confirm applies');
$_SESSION = [];

// 9. Booked seats protected from other customers.
$h2 = $D(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId, 'seats' => ['A3']]);
ok($h2['http'] === 400, '9: held/booked seat rejected for others');
$c2 = $D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => $tripId, 'seats' => ['A3'], 'passenger' => 'Other', 'phone' => '0770000000']]);
ok($c2['http'] === 400, '9b: double-book rejected');

// 10. Invalid / duplicate / unauthorized rejected.
ok($D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => $tripId, 'email' => 'x@y.zz']])['http'] === 400, '10: seatless rejected');
ok($D(['action' => 'pay', 'slug' => $SLUG, 'ref' => $ref, 'method' => 'counter', 'amount' => 850])['http'] === 400, '10b: duplicate pay rejected');
ok($D(['action' => 'update', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'patch' => ['status' => 'confirmed']])['http'] === 403, '10c: non-admin update rejected');
ok($D(['action' => 'cancel', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'email' => 'stranger@example.com'])['http'] === 403, '10d: stranger cancel rejected');

// 11. Admin schedule change reflected in customer search.
$_SESSION['admin_auth'] = true;
$D(['action' => 'update', 'slug' => $SLUG, 'entity' => 'trips', 'id' => $tripId, 'patch' => ['price' => 900]]);
$_SESSION = [];
$s2 = $D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'trips', 'route' => 'chennai', 'date' => $DATE]);
$np = null; foreach ($s2['body']['data'] as $row) { if (($row['id'] ?? '') === $tripId) $np = $row['price'] ?? null; }
ok($np == 900, '11: admin price change live in customer search');

// 12. Data survives re-read (restart-safe).
$re = bops_load($SLUG, 'trips');
ok(count($re) === 1 && $re[0]['id'] === $tripId, '12: records persist across re-reads');

// The generated bus widget follows the same live search → seat → hold →
// passenger → ticket workflow as the bus-loop acceptance page.
$renderWidget = new ReflectionMethod('AutoSiteGenerator', 'renderDynamicWidget');
$renderWidget->setAccessible(true);
$widget = $renderWidget->invoke(null, 'bus', 'City Express', $SLUG);
ok(strpos($widget, 'data-wc-search="trips"') !== false && strpos($widget, 'id="busSeatMap"') !== false,
    'UI: generated bus page searches trips and renders a seat map');
ok(strpos($widget, 'data-wc-form="booking"') !== false
    && strpos($widget, 'wc:booking-confirmed') !== false
    && strpos($widget, 'data-wc-ticket-lookup="bookings"') !== false,
    'UI: generated flow confirms, displays, and looks up real tickets');
ok(strpos($widget, 'data-wc-live-bus-booking') !== false
    && strpos($widget, "window.WCBiz.hold('bookings'") !== false
    && strpos($widget, "api('availability'") !== false
    && strpos($widget, "api('pay',{entity:'bookings'") !== false,
    'UI: reusable bus flow uses the shared backend for live availability, seat holds and payment');
$publishSpec = business_spec_analyze(['biz_name' => 'City Express',
    'biz_type' => 'Bus Booking', 'biz_services' => 'Daily bus routes, seats and booking']);
$publishPage = business_runtime_inject('<html><body><h1>City Express</h1></body></html>', $publishSpec, $SLUG);
ok(strpos($publishPage, 'data-wc-live-bus-booking') !== false
    && strpos($publishPage, 'data-wc-biz-runtime') !== false
    && strpos($publishPage, 'api/business-ops.php') !== false
    && strpos($publishPage, 'var IS_BUS_BOOKING=(true)') !== false,
    'UI: publish-style bus specs inject the live backend-connected booking flow');
$runtimeFirstPage = business_runtime_inject('<html><body><script data-wc-biz-runtime>/* existing runtime */</script></body></html>',
    $publishSpec, $SLUG);
ok(strpos($runtimeFirstPage, 'data-wc-live-bus-booking') < strpos($runtimeFirstPage, 'data-wc-biz-runtime'),
    'UI: bus widget is inserted before an existing runtime so its forms are bound');
ok(strpos($widget, 'trip_101') === false && strpos($widget, 'value="04A"') === false,
    'UI: generated booking no longer uses hardcoded trips or preselected seats');

// Runtime search/catalog wiring present + respected by gap tools.
$rt = business_runtime_js([], $SLUG);
ok(strpos($rt, 'data-wc-search') !== false && strpos($rt, 'data-wc-catalog') !== false, 'RT: catalog + search wiring in runtime');
ok(strpos($rt, "if(f.hasAttribute('data-wc-search'))return") !== false, 'RT: search forms never create records');
$searchHtml = '<html><body><form data-wc-search="trips" data-wc-target="#r"><input name="route"><button>Go</button></form><div id="r" data-wc-results></div><div data-wc-catalog="trips"></div></body></html>';
$rep = mfa_scan(['entities' => [['id' => 'trips']], 'functions' => []], $searchHtml, $SLUG);
$hasFormMissing = false;
foreach (($rep['missing'] ?? []) as $mm) { if (strpos($mm, 'form') === 0) $hasFormMissing = true; }
ok(!$hasFormMissing, 'RT: search/catalog markup not flagged as unwired');
$cx = biz_ensure_html_compliance($searchHtml, ['profile' => ['business_name' => 'City Express']]);
ok(strpos($cx['html'], 'data-wc-search="trips"') !== false && strpos($cx['html'], 'data-wc-form="booking"') === false, 'RT: fixer never converts search into create');

// Booking contract: inline selectable demo schedule + no-navigate Find.
$svc = (string)@file_get_contents(__DIR__ . '/../includes/OpenCodeService.php');
ok(strpos($svc, 'DEMO_TRIPS') !== false && strpos($svc, 'demo-trips') !== false, 'CT: contract requires demo schedule grid');
ok(strpos($svc, 'Select This Bus') !== false && strpos($svc, 'DEMO-') !== false, 'CT: demo trips selectable into seat flow');
ok(strpos($svc, "WCBiz.gotoStep('seats')") !== false, 'CT: Select scrolls to seat step (no jump to top)');
ok(strpos($svc, 'data-wc-step=') !== false, 'CT: steps are data-wc-step blocks advanced via gotoStep');
ok(strpos($svc, 'window.WCBiz.ticketQR(ref,180)') !== false, 'CT: ticket QR via runtime helper with offline fallback');
ok(strpos($svc, 'data-wc-ticket-lookup=') !== false, 'CT: phone + ref ticket lookup block required');
ok(strpos($svc, 'onsubmit=') !== false && strpos($svc, 'method=') !== false, 'CT: search form never navigates (POST + no-submit)');
ok(strpos($svc, 'NEVER builds blob: URLs') !== false, 'CT: blob-tab navigation banned in contract');
ok(strpos($svc, 'type=\"submit\"') !== false && strpos($svc, 'formtarget') !== false && strpos($svc, 'window.open') !== false, 'CT: Find is an inline submit button (no link/target/window.open)');
ok(strpos($svc, 'never an <a> link') !== false, 'CT: Find never an anchor link');
// New-tab + duplication guards in runtime, preview bundles, router, workspace.
$rt2 = (string)@file_get_contents(__DIR__ . '/../includes/BusinessRuntime.php');
ok(strpos($rt2, 'formtarget') !== false, 'RT: runtime strips formtarget=_blank too');
ok(strpos($rt2, "step.setAttribute('hidden','')") !== false
    && strpos($rt2, 'scrollMarginTop') !== false
    && strpos($rt2, 'scrollIntoView') !== false, 'RT: gotoStep shows only the active step and scrolls below sticky navigation');
ok(strpos($rt2, '__WC_HOLD__') !== false, 'RT: hold state auto-attaches trip+seats to booking creates');
ok(strpos($rt2, 'ticketQRFallback') !== false, 'RT: QR has offline text-code fallback');
ok(strpos($rt2, 'data-wc-ticket-lookup') !== false, 'RT: phone + ref ticket lookup block present');
// Blob-death guard present in runtime + both preview bundles.
ok(strpos($rt, "toLowerCase()==='blob:'") !== false, 'RT: runtime blocks blob: form navigation');
$bld = (string)@file_get_contents(__DIR__ . '/../builder.php');
ok(strpos($bld, 'WC_BLOB_GUARD_JS') !== false && strpos($bld, 'wc-blob-guard') !== false, 'RT: workspace preview bundle carries blob guard');
$var = (string)@file_get_contents(__DIR__ . '/../variations.php');
ok(strpos($var, 'wc-blob-guard') !== false, 'RT: variations preview bundle carries blob guard');
ok(strpos($var, 'formtarget') !== false, 'RT: variations guard strips formtarget');
ok(strpos($bld, 'formtarget') !== false, 'RT: workspace guard strips formtarget');
$router = (string)@file_get_contents(__DIR__ . '/../assets/js/generation-router.js');
ok(strpos($router, 'visibleSig') !== false, 'RT: dedupe compares full HTML + visible text (no duplicate variations)');
ok(strpos($bld, 'syncDesignTabs') !== false, 'RT: workspace tabs mirror actual designs');
ok(strpos($var, '__VAR_RENDERED_KEY__') !== false, 'RT: variations render is idempotent');

// Cancellation + refund per policy.
$cx2 = $D(['action' => 'cancel', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'email' => 'e2erider@example.com']);
ok($cx2['http'] === 200 && ($cx2['body']['data']['payment_status'] ?? '') === 'refund_pending', 'CX: paid cancel → refund_pending');

rmdir_r(bops_root() . '/collections/' . $SLUG);
rmdir_r(bops_root() . '/sites/' . $SLUG);
rmdir_r(dirname(__DIR__) . '/published/' . $SLUG);
rmdir_r(bops_root() . '/collections/' . $LEGACY_SLUG);
rmdir_r(bops_root() . '/sites/' . $LEGACY_SLUG);
rmdir_r(dirname(__DIR__) . '/published/' . $LEGACY_SLUG);
$_SESSION = [];
echo $fail === 0 ? "\nALL BUS-BOOKING-E2E TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
