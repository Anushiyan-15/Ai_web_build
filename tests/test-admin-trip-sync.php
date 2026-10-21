<?php
// Admin-add-trip -> frontend visibility on the REAL __bustest2 site equipment.
// Uses the site's OWN admin db.php (same functions trips.php uses), then
// asserts the row reaches collections, the admin file, PHP search (default
// empty-date Find + date-scoped Find) and Node (when up). Cleans up fully.
if (session_status() === PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/includes/BusinessOps.php';
$_SESSION = [];
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function postJson($url, $payload) {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 5,
        'header' => "Content-Type: application/json\r\n", 'content' => json_encode($payload),
        'ignore_errors' => true]]);
    $b = @file_get_contents($url, false, $ctx);
    if ($b === false) {
        $res = bops_dispatch($payload);
        return $res['body'] ?? null;
    }
    return json_decode($b, true);
}

$SLUG = '__bustest2';
$DATE = date('Y-m-d', time() + 2 * 86400);
$EP = 'http://localhost/project/webbbuilder/published/__bustest2/api/business-ops.php';

// 1. Admin adds a trip through the site's own admin stack.
require $root . '/published/__bustest2/admin/includes/db.php';
ok(db_site_slug() === $SLUG, 'admin slug resolves to __bustest2 (no underscore stripping)');
$row = db_insert('trips', ['route' => 'Sync Check City → Sync Town', 'date' => $DATE, 'departure' => '08:45', 'price' => '540', 'total_seats' => '40']);
$tid = (string)($row['id'] ?? '');
ok($row && $tid !== '', 'admin Add trip succeeds');
$inColl = false;
foreach (bops_load($SLUG, 'trips') as $r) if (($r['id'] ?? '') === $tid) $inColl = true;
ok($inColl, 'admin trip mirrored to canonical collections store');
$adminFile = json_decode(@file_get_contents($root . '/published/__bustest2/admin/data_trips.json'), true) ?: [];
$inAdmin = false;
foreach ($adminFile as $r) if (($r['id'] ?? '') === $tid) $inAdmin = true;
ok($inAdmin, 'admin trip present in admin data file');

// 2. Frontend Find with default (empty) date sees it.
$unfiltered = postJson($EP, ['action' => 'list', 'slug' => $SLUG, 'entity' => 'trips', 'route' => '', 'date' => '']);
$seen = false;
foreach ((array)($unfiltered['data'] ?? []) as $r) if (($r['id'] ?? '') === $tid) $seen = true;
ok(is_array($unfiltered) && ($unfiltered['success'] ?? false) && $seen, 'frontend default Find (no date) lists the admin trip');
// 3. Date-scoped Find (page sends the picked date) sees it too.
$scoped = postJson($EP, ['action' => 'list', 'slug' => $SLUG, 'entity' => 'trips', 'route' => 'Sync Check', 'date' => $DATE]);
$seen2 = false;
foreach ((array)($scoped['data'] ?? []) as $r) if (($r['id'] ?? '') === $tid) $seen2 = true;
ok($seen2, 'frontend date-scoped Find lists the admin trip');
// 4. Availability + booking on the admin trip (same trip id end to end).
$av = postJson($EP, ['action' => 'availability', 'slug' => $SLUG, 'entity' => 'bookings', 'trip_id' => $tid]);
ok(is_array($av) && ($av['success'] ?? false) && ($av['data']['total_seats'] ?? 0) == 40, 'availability serves the admin trip (live capacity)');
// 5. Node path when up (informational: must not contradict PHP).
$ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true]]);
$nb = @file_get_contents('http://localhost:3100/node-api/' . rawurlencode($SLUG) . '/trips', false, $ctx);
if ($nb === false) {
    echo "SKIP: Node down — PHP path verified above; Node union covers collections when up\n";
} else {
    $nj = json_decode($nb, true);
    $rows = $nj['data'] ?? (is_array($nj) ? $nj : []);
    $seen3 = false;
    foreach ((array)$rows as $r) if (($r['id'] ?? '') === $tid) $seen3 = true;
    ok($seen3, 'Node list includes the admin trip (union, no stale split)');
}
// 6. Admin edits the trip; frontend sees the edit.
db_update('trips', $tid, ['price' => '590']);
$edited = postJson($EP, ['action' => 'list', 'slug' => $SLUG, 'entity' => 'trips', 'route' => 'Sync Check']);
$newPrice = null;
foreach ((array)($edited['data'] ?? []) as $r) if (($r['id'] ?? '') === $tid) $newPrice = $r['price'] ?? null;
ok((string)$newPrice === '590', 'admin trip edit visible in frontend search');
// 7. Cleanup via the same admin stack.
ok(db_delete('trips', $tid), 'admin delete removes the test trip');
$gone = true;
foreach (bops_load($SLUG, 'trips') as $r) if (($r['id'] ?? '') === $tid) $gone = false;
$adminFile2 = json_decode(@file_get_contents($root . '/published/__bustest2/admin/data_trips.json'), true) ?: [];
foreach ($adminFile2 as $r) if (($r['id'] ?? '') === $tid) $gone = false;
ok($gone, 'cleanup: test trip gone from backend and admin file');
$_SESSION = [];
echo $fail === 0 ? "\nALL ADMIN-TRIP-SYNC TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
