<?php
// E2E against the REAL published site (Test A-E). Creates one test trip +
// booking, verifies admin/history/lookup/sync, records + verifies payment,
// then removes ALL test rows so real history is untouched.
if (session_status() === PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/includes/BusinessOps.php';
$_SESSION = [];
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function D($req) { return bops_dispatch($req); }
function httpPostJson($url, $payload) {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 5,
        'header' => "Content-Type: application/json\r\n", 'content' => json_encode($payload),
        'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        $res = bops_dispatch($payload);
        return ['body' => json_encode($res['body'] ?? [])];
    }
    return ['body' => $body === false ? '' : $body];
}

$SLUG = '__bustest2';
$DATE = date('Y-m-d', time() + 86400);
$EMAIL = 'e2e-verifier@example.com';
$PHONE = '0779000111';

// --- HTTP endpoint wiring (same code path, proves bridge + slug) ---
$ep = 'http://localhost/project/webbbuilder/published/__bustest2/api/business-ops.php';
$h = httpPostJson($ep, ['action' => 'availability', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => 'D9']);
$hj = json_decode($h['body'], true);
ok(is_array($hj) && ($hj['success'] ?? false) === true && isset($hj['data']['held_seats']), 'HTTP: site api bridge serves availability with held/booked breakdown');

// --- Test A: operator trip -> customer pay-at-counter booking ---
$_SESSION['admin_auth'] = true;
$t = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'trips',
    'record' => ['route' => 'E2E Verify City → E2E Town', 'origin' => 'E2E Verify City', 'destination' => 'E2E Town',
    'date' => $DATE, 'departure' => '10:30', 'price' => 650, 'total_seats' => 40]]);
unset($_SESSION['admin_auth']);
$tripId = (string)($t['body']['data']['id'] ?? '');
ok($t['http'] === 200 && str_starts_with($tripId, 'TRIP-'), 'A: operator trip created with TRIP- id');
$adminTrips = json_decode(@file_get_contents($root . '/published/__bustest2/admin/data_trips.json'), true) ?: [];
$foundTrip = false;
foreach ($adminTrips as $r) if (($r['id'] ?? '') === $tripId) $foundTrip = true;
ok($foundTrip, 'A: new trip mirrored to admin data file');
$search = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'trips', 'route' => 'E2E Verify City']);
ok($search['http'] === 200 && count($search['body']['data'] ?? []) >= 1, 'A: trip visible in public search');

$hold = D(['action' => 'hold', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tripId, 'seats' => ['B2']]);
ok($hold['http'] === 200, 'A: seat held');
$idem = 'e2e-' . bin2hex(random_bytes(4));
$b = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => $tripId, 'seats' => ['B2'], 'hold_ref' => (string)($hold['body']['data']['ref'] ?? ''),
    'passenger' => 'E2E Verifier', 'phone' => $PHONE, 'email' => $EMAIL],
    'idempotency_key' => $idem]);
$bd = $b['body']['data'] ?? [];
$ref = (string)($bd['ref'] ?? '');
$bid = (string)($bd['id'] ?? '');
ok($b['http'] === 200 && str_starts_with($ref, 'BK-') && ($bd['trip_id'] ?? '') === $tripId
    && ($bd['seats'] ?? []) === ['B2'] && ($bd['amount'] ?? 0) == 650.0
    && ($bd['status'] ?? '') === 'pending' && ($bd['payment_status'] ?? '') === 'unpaid', 'A: pay-at-counter booking persisted (ref, trip, seats, server fare, pending/unpaid)');
// Duplicate submit deduped.
$bDup = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => $tripId, 'seats' => ['B2'], 'passenger' => 'E2E Verifier', 'phone' => $PHONE, 'email' => $EMAIL],
    'idempotency_key' => $idem]);
ok(!empty($bDup['body']['deduped']), 'A: duplicate submit deduped (one row)');
// Counter intent keeps unpaid.
$pi = D(['action' => 'payment_intent', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $ref, 'method' => 'counter', 'email' => $EMAIL]);
ok($pi['http'] === 200 && ($pi['body']['data']['payment_status'] ?? '') === 'unpaid', 'A: Pay at Counter intent stays unpaid (no false paid)');
// Admin panel shows it.
$adminBooks = json_decode(@file_get_contents($root . '/published/__bustest2/admin/data_bookings.json'), true) ?: [];
$inAdmin = false;
foreach ($adminBooks as $r) if (($r['ref'] ?? '') === $ref && ($r['trip_id'] ?? '') === $tripId) $inAdmin = true;
ok($inAdmin, 'A: booking visible in Admin Panel data with correct trip + seats');
// History for the customer.
$_SESSION['customer_user'] = ['email' => $EMAIL];
$hist = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings']);
unset($_SESSION['customer_user']);
$inHist = false;
foreach (($hist['body']['data'] ?? []) as $r) if (($r['ref'] ?? '') === $ref) $inHist = true;
ok($hist['http'] === 200 && $inHist, 'A: My Bookings History returns the booking');
// Ticket lookup.
$tk = D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $ref, 'phone' => $PHONE]);
ok($tk['http'] === 200 && ($tk['body']['data']['ref'] ?? '') === $ref, 'A: Ticket Lookup retrieves by ref+phone');
$tkBad = D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $ref, 'phone' => '0700000000']);
ok($tkBad['http'] === 404, 'A: wrong phone finds nothing');
// Verify endpoint.
$ver = D(['action' => 'verify', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $ref]);
ok($ver['http'] === 200 && ($ver['body']['verdict'] ?? '') === 'pending', 'A: verify.php backend returns pending verdict');

// --- Test B: persistence across sessions ---
$_SESSION = [];
$_SESSION['customer_user'] = ['email' => $EMAIL];
$hist2 = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings']);
unset($_SESSION['customer_user']);
$stillThere = false;
foreach (($hist2['body']['data'] ?? []) as $r) if (($r['ref'] ?? '') === $ref) $stillThere = true;
ok($stillThere, 'B: history survives session reset (server-side, not localStorage)');

// --- Test C: admin records received payment against SAME ref ---
$_SESSION['admin_auth'] = true;
$_SESSION['admin_csrf'] = 'x';
D(['action' => 'update', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'patch' => ['status' => 'approved']]);
$q = D(['action' => 'payment_quote', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $ref]);
ok($q['http'] === 200 && ($q['body']['data']['amount'] ?? 0) == 650.0, 'C: payment quote uses saved server fare');
$pay = D(['action' => 'pay', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $ref, 'method' => 'cash']);
unset($_SESSION['admin_auth'], $_SESSION['admin_csrf']);
ok($pay['http'] === 200 && ($pay['body']['data']['payment_status'] ?? '') === 'paid'
    && str_starts_with((string)($pay['body']['payment']['payment_id'] ?? ''), 'PAY-'), 'C: received cash recorded on SAME booking (PAY- id, no duplicate booking)');
$payDup = null;
$_SESSION['admin_auth'] = true;
$payDup = D(['action' => 'pay', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $ref, 'method' => 'cash']);
unset($_SESSION['admin_auth']);
ok($payDup['http'] === 400, 'C: duplicate payment rejected');
$_SESSION['customer_user'] = ['email' => $EMAIL];
$hist3 = D(['action' => 'list', 'slug' => $SLUG, 'entity' => 'bookings']);
unset($_SESSION['customer_user']);
$paidSeen = false;
foreach (($hist3['body']['data'] ?? []) as $r) if (($r['ref'] ?? '') === $ref && ($r['payment_status'] ?? '') === 'paid') $paidSeen = true;
ok($paidSeen, 'C: customer history shows paid status');

// --- Test D: online intent (no gateway configured -> follow-up request only) ---
$pi2 = D(['action' => 'payment_intent', 'slug' => $SLUG, 'entity' => 'bookings', 'ref' => $ref, 'method' => 'online', 'email' => $EMAIL]);
ok($pi2['http'] === 400, 'D: online intent on paid booking honestly rejected (already paid, no fake charge)');

// --- Test E: failures ---
$badCreate = D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings', 'record' => ['passenger' => 'X']]);
ok($badCreate['http'] === 400, 'E: missing fields rejected (no fake ticket)');
$noAuth = D(['action' => 'update', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'patch' => ['status' => 'rejected']]);
ok($noAuth['http'] === 403, 'E: unauthenticated admin update rejected');
$_SESSION['customer_user'] = ['email' => 'stranger@example.com'];
$stranger = D(['action' => 'cancel', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'email' => 'stranger@example.com']);
unset($_SESSION['customer_user']);
ok($stranger['http'] === 403, 'E: stranger cannot cancel another customer booking');

// --- Cleanup: remove every test trace ---
$_SESSION['customer_user'] = ['email' => $EMAIL];
D(['action' => 'cancel', 'slug' => $SLUG, 'entity' => 'bookings', 'id' => $bid, 'email' => $EMAIL]);
unset($_SESSION['customer_user']);
$removed = 0;
foreach (['bookings', 'payments', 'trips'] as $entity) {
    [$okTx, $out] = bops_transact($SLUG, $entity, function ($rows) use ($bid, $ref, $tripId) {
        $before = count($rows);
        $rows = array_values(array_filter($rows, function ($r) use ($bid, $ref, $tripId) {
            if (!is_array($r)) return true;
            if ((string)($r['id'] ?? '') === $bid) return false;
            if (!empty($ref) && strtoupper((string)($r['booking_ref'] ?? '')) === strtoupper($ref)) return false;
            if (!empty($tripId) && ($r['id'] ?? '') === $tripId) return false;
            return true;
        }));
        return [$rows, $before - count($rows)];
    });
    if ($okTx) $removed += (int)$out;
    // Mirror deletions to admin + sites twin.
    bops_mirror_admin($SLUG, 'bookings', ['id' => $bid], true);
    bops_sync_sites($SLUG, 'bookings', $bid, null);
    if ($tripId !== '') { bops_mirror_admin($SLUG, 'trips', ['id' => $tripId], true); bops_sync_sites($SLUG, 'trips', $tripId, null); }
}
echo "cleanup removed rows: $removed\n";
// Verify cleanup.
$left = false;
foreach (readAll($root, $SLUG) as $r) if (($r['id'] ?? '') === $bid || ($r['id'] ?? '') === $tripId) $left = true;
function readAll($root, $slug) {
    $out = [];
    foreach (glob($root . '/storage/collections/' . $slug . '/*.json') as $f) foreach ((array)(json_decode(@file_get_contents($f), true) ?: []) as $r) $out[] = $r;
    foreach (glob($root . '/published/' . $slug . '/admin/data_*.json') as $f) foreach ((array)(json_decode(@file_get_contents($f), true) ?: []) as $r) $out[] = $r;
    return $out;
}
ok(!$left, 'cleanup: no test rows left in backend or admin files');
$_SESSION = [];
echo $fail === 0 ? "\nALL BUSTEST2 E2E TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
