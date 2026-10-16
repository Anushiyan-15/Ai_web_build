<?php
// tests/test-booking-flow.php — bus booking acceptance against BusinessOps.
// run: php tests/test-booking-flow.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('STORAGE_DIR')) define('STORAGE_DIR', sys_get_temp_dir() . '/webbbuilder-booking-flow-' . getmypid());
if (!defined('SITE_URL')) define('SITE_URL', '');
require_once __DIR__ . '/../includes/BusinessOps.php';

$fail = 0;
function ok($condition, $message) {
    global $fail;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) $fail++;
}
function removeTree($path) {
    if (!is_dir($path)) return;
    foreach (scandir($path) as $file) {
        if ($file === '.' || $file === '..') continue;
        $child = $path . DIRECTORY_SEPARATOR . $file;
        is_dir($child) ? removeTree($child) : @unlink($child);
    }
    @rmdir($path);
}

$slug = 'test-bus-' . bin2hex(random_bytes(4));
$dispatch = static fn(array $request): array => bops_dispatch($request);
$_SESSION = ['admin_auth' => true];
$trip = $dispatch(['action' => 'create', 'slug' => $slug, 'entity' => 'trips',
    'record' => ['route' => 'Chennai-Bangalore', 'date' => '2026-10-20',
        'departure' => '09:30', 'price' => 850, 'total_seats' => 40]]);
$tripId = (string)($trip['body']['data']['id'] ?? '');
ok($trip['http'] === 200 && strpos($tripId, 'TRIP-') === 0, 'trip receives an automatic ID');
$_SESSION = [];

$hold = $dispatch(['action' => 'hold', 'slug' => $slug, 'entity' => 'bookings',
    'trip_id' => $tripId, 'seats' => ['A1', 'A2']]);
ok($hold['http'] === 200 && ($hold['body']['data']['status'] ?? '') === 'held', 'seats are held during checkout');
$conflict = $dispatch(['action' => 'hold', 'slug' => $slug, 'entity' => 'bookings',
    'trip_id' => $tripId, 'seats' => ['A1']]);
ok($conflict['http'] === 400, 'a seat held by another checkout is unavailable');

$booking = $dispatch(['action' => 'create', 'slug' => $slug, 'entity' => 'bookings',
    'record' => ['trip_id' => $tripId, 'seats' => ['A1', 'A2'], 'hold_ref' => $hold['body']['data']['ref'],
        'passenger' => 'Test Rider', 'phone' => '0771112222', 'email' => 'rider@example.invalid']]);
$ref = (string)($booking['body']['data']['ref'] ?? '');
$bookingId = (string)($booking['body']['data']['id'] ?? '');
ok($booking['http'] === 200 && strpos($bookingId, 'BKG-') === 0 && strpos($ref, 'BK-') === 0
    && ($booking['body']['data']['status'] ?? '') === 'pending'
    && (float)($booking['body']['data']['amount'] ?? 0) === 1700.0,
    'ticket request creates a pending booking with server-calculated fare');
ok(count(array_filter(bops_load($slug, 'bookings'), static fn($row) => ($row['status'] ?? '') === 'held')) === 0,
    'booking consumes its checkout hold');

$_SESSION['admin_auth'] = true;
$prematurePayment = $dispatch(['action' => 'pay', 'slug' => $slug, 'ref' => $ref, 'method' => 'cash']);
ok($prematurePayment['http'] === 400, 'operator cannot record payment before approval');
$approved = $dispatch(['action' => 'update', 'slug' => $slug, 'entity' => 'bookings',
    'id' => $bookingId, 'patch' => ['status' => 'approved']]);
ok($approved['http'] === 200 && ($approved['body']['data']['notification_status'] ?? '') === 'queued',
    'approval queues a customer email and records the new status');
$payment = $dispatch(['action' => 'pay', 'slug' => $slug, 'ref' => $ref, 'method' => 'cash', 'amount' => 1]);
ok($payment['http'] === 200 && ($payment['body']['data']['status'] ?? '') === 'approved'
    && ($payment['body']['data']['payment_status'] ?? '') === 'paid'
    && (float)($payment['body']['payment']['amount'] ?? 0) === 1700.0
    && strpos($payment['body']['payment']['payment_id'] ?? '', 'PAY-') === 0
    && !empty($payment['body']['payment']['received_at']),
    'operator records the received cash using server fare, generated payment ID and timestamp');
ok(count(bops_load($slug, 'payments')) === 1, 'payment is saved in the payments collection');
$duplicatePayment = $dispatch(['action' => 'pay', 'slug' => $slug, 'ref' => $ref, 'method' => 'cash']);
ok($duplicatePayment['http'] === 400, 'duplicate payment is rejected');

$_SESSION = [];
$cancel = $dispatch(['action' => 'cancel', 'slug' => $slug, 'entity' => 'bookings',
    'id' => $bookingId, 'email' => 'rider@example.invalid']);
ok($cancel['http'] === 200 && ($cancel['body']['data']['status'] ?? '') === 'cancelled'
    && ($cancel['body']['data']['payment_status'] ?? '') === 'refund_pending',
    'cancelling a paid booking releases capacity and flags refund review');

removeTree(bops_root() . '/collections/' . $slug);
removeTree(bops_root() . '/sites/' . $slug);
removeTree(STORAGE_DIR);
$_SESSION = [];
echo $fail === 0 ? "\nALL BOOKING TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
