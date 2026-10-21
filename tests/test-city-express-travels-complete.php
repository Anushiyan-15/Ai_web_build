<?php
/**
 * Comprehensive Full-Stack Verification for City Express Travels
 * Tests the preview environment: preview/d2ecd5c885191b468a23ed8f71380727
 * Validates all 12 Master AI Agent Prompt Requirements without MySQL.
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config.php';
require_once $projectRoot . '/includes/BusinessOps.php';

$slug = 'd2ecd5c885191b468a23ed8f71380727';
$previewDir = $projectRoot . '/preview/' . $slug;
$adminDir = $previewDir . '/admin';

echo "=== City Express Travels Full-Stack Verification ===\n";
echo "Slug: $slug\n";
echo "Preview Dir: $previewDir\n\n";

$passCount = 0;
$failCount = 0;

function check(bool $cond, string $msg): void {
    global $passCount, $failCount;
    if ($cond) {
        echo "PASS: $msg\n";
        $passCount++;
    } else {
        echo "FAIL: $msg\n";
        $failCount++;
    }
}

// -----------------------------------------------------------------------------
// STEP 1: Verify environment and files
// -----------------------------------------------------------------------------
check(is_dir($previewDir), "Preview directory exists");
check(is_file($previewDir . '/index.html'), "Preview index.html exists");
check(is_file($previewDir . '/customer.php'), "Preview customer.php exists");
check(is_file($previewDir . '/verify.php'), "Preview verify.php exists");
check(is_file($previewDir . '/api/business-ops.php'), "Preview api/business-ops.php bridge exists");

$html = file_get_contents($previewDir . '/index.html');
check(strpos($html, 'var IS_BUS_BOOKING=(true);') !== false, "index.html has IS_BUS_BOOKING=(true)");
check(strpos($html, 'Demo schedule') === false, "index.html does not contain hardcoded demo schedule markers");
check(strpos($html, "BK-849201") === false, "index.html does not contain fake BK-849201 mock booking");
check(strpos($html, "setupSeatMap") !== false, "index.html contains live setupSeatMap function");

// -----------------------------------------------------------------------------
// STEP 2: Admin Adds a Trip via Backend API
// -----------------------------------------------------------------------------
$newTripPayload = [
    'action' => 'create',
    'entity' => 'trips',
    'slug' => $slug,
    'record' => [
        'origin' => 'Chennai',
        'destination' => 'Bangalore',
        'route' => 'Chennai → Bangalore',
        'date' => date('Y-m-d', strtotime('+2 days')),
        'departure' => '07:45 AM',
        'bus' => 'Volvo Multi-Axle AC',
        'price' => 950,
        'total_seats' => 40
    ]
];

$res = bops_dispatch($newTripPayload);
check($res['http'] === 200 && !empty($res['body']['success']), "Admin adds new trip via backend API");
$createdTrip = $res['body']['data'] ?? [];
$tripId = $createdTrip['id'] ?? '';
check(!empty($tripId), "Backend automatically generated unique trip ID: $tripId");
check(strpos($tripId, 'TRIP-') === 0 || strpos($tripId, 'r_') === 0, "Trip ID format is valid: $tripId");

// Verify trip is saved in persistent collections storage and admin data file
$colTrips = bops_load($slug, 'trips');
$foundInCol = false;
foreach ($colTrips as $t) {
    if (($t['id'] ?? '') === $tripId) { $foundInCol = true; break; }
}
check($foundInCol, "Trip is persisted in collections storage trips.json");

$adminTripsFile = $adminDir . '/data_trips.json';
$adminTrips = json_decode(file_get_contents($adminTripsFile), true) ?? [];
$foundInAdmin = false;
foreach ($adminTrips as $t) {
    if (($t['id'] ?? '') === $tripId) { $foundInAdmin = true; break; }
}
check($foundInAdmin, "Trip is mirrored to admin data_trips.json");

// -----------------------------------------------------------------------------
// STEP 3: Customer Searches Trips - Admin Trip Must Appear
// -----------------------------------------------------------------------------
$searchRes = bops_dispatch([
    'action' => 'list',
    'entity' => 'trips',
    'slug' => $slug,
    'origin' => 'Chennai',
    'destination' => 'Bangalore'
]);

check($searchRes['http'] === 200 && !empty($searchRes['body']['success']), "Customer trip search API responds with 200");
$searchedTrips = $searchRes['body']['data'] ?? [];
$foundInSearch = null;
foreach ($searchedTrips as $t) {
    if (($t['id'] ?? '') === $tripId) {
        $foundInSearch = $t;
        break;
    }
}
check($foundInSearch !== null, "Newly added admin trip appears in customer trip search results");
check(($foundInSearch['price'] ?? 0) == 950, "Trip search returns correct fare (₹950)");
check(($foundInSearch['departure'] ?? '') === '07:45 AM', "Trip search returns correct departure time");

// -----------------------------------------------------------------------------
// STEP 4: Live Seat Availability for the Selected Trip
// -----------------------------------------------------------------------------
$availRes = bops_dispatch([
    'action' => 'availability',
    'entity' => 'bookings',
    'slug' => $slug,
    'trip_id' => $tripId
]);

check($availRes['http'] === 200 && !empty($availRes['body']['success']), "Customer requests live seat availability");
$availData = $availRes['body']['data'] ?? [];
check(($availData['total_seats'] ?? 0) == 40, "Seat capacity matches trip capacity (40 seats)");
check(empty($availData['reserved_seats']), "Initial state: no seats reserved");

// -----------------------------------------------------------------------------
// STEP 5: Customer Holds Seats (e.g. A1, A2)
// -----------------------------------------------------------------------------
$holdRes = bops_dispatch([
    'action' => 'hold',
    'entity' => 'bookings',
    'slug' => $slug,
    'trip_id' => $tripId,
    'seats' => ['A1', 'A2']
]);

check($holdRes['http'] === 200 && !empty($holdRes['body']['success']), "Customer holds seats A1 and A2");
$holdRef = $holdRes['body']['data']['ref'] ?? '';
check(!empty($holdRef) && strpos($holdRef, 'HOLD-') === 0, "Hold reference generated: $holdRef");

// Live availability should now show held seats
$availRes2 = bops_dispatch([
    'action' => 'availability',
    'entity' => 'bookings',
    'slug' => $slug,
    'trip_id' => $tripId
]);
$heldList = $availRes2['body']['data']['held_seats'] ?? [];
check(in_array('A1', $heldList, true) && in_array('A2', $heldList, true), "Live availability shows A1 and A2 in held_seats");

// -----------------------------------------------------------------------------
// STEP 6: Conflicting Hold Attempt by Another Passenger is Rejected
// -----------------------------------------------------------------------------
$conflictHoldRes = bops_dispatch([
    'action' => 'hold',
    'entity' => 'bookings',
    'slug' => $slug,
    'trip_id' => $tripId,
    'seats' => ['A1', 'A3'] // A1 already held
]);
check($conflictHoldRes['http'] >= 400 || empty($conflictHoldRes['body']['success']), "Conflicting seat hold on A1 is rejected");

// -----------------------------------------------------------------------------
// STEP 7: Customer Confirms Booking with Details
// -----------------------------------------------------------------------------
$passengerPhone = '9876543210';
$passengerEmail = 'passenger.test@example.com';
$passengerName = 'Rahul Sharma';

$bookRes = bops_dispatch([
    'action' => 'create',
    'entity' => 'bookings',
    'slug' => $slug,
    'record' => [
        'trip_id' => $tripId,
        'seats' => ['A1', 'A2'],
        'passenger' => $passengerName,
        'phone' => $passengerPhone,
        'email' => $passengerEmail,
        'hold_ref' => $holdRef
    ]
]);

check($bookRes['http'] === 200 && !empty($bookRes['body']['success']), "Customer confirms booking with details");
$bookingData = $bookRes['body']['data'] ?? [];
$bookingRef = $bookingData['ref'] ?? '';
$verifyToken = $bookingData['verify_token'] ?? '';
check(!empty($bookingRef) && strpos($bookingRef, 'BK-') === 0, "Booking reference generated: $bookingRef");
check(($bookingData['amount'] ?? 0) == (950 * 2), "Server-calculated fare is correct (₹1900)");
check(($bookingData['status'] ?? '') === 'pending' || ($bookingData['status'] ?? '') === 'confirmed', "Initial booking status is valid ({$bookingData['status']})");

// -----------------------------------------------------------------------------
// STEP 8: Seat Map Refresh Shows Seats as Booked/Unavailable
// -----------------------------------------------------------------------------
$availRes3 = bops_dispatch([
    'action' => 'availability',
    'entity' => 'bookings',
    'slug' => $slug,
    'trip_id' => $tripId
]);
$bookedList = $availRes3['body']['data']['booked_seats'] ?? [];
$reservedList = $availRes3['body']['data']['reserved_seats'] ?? [];
check(in_array('A1', $reservedList, true) && in_array('A2', $reservedList, true), "Live availability shows booked seats A1 and A2 as reserved");

// -----------------------------------------------------------------------------
// STEP 9: Customer Booking History by Phone
// -----------------------------------------------------------------------------
$historyRes = bops_dispatch([
    'action' => 'list',
    'entity' => 'bookings',
    'slug' => $slug,
    'phone' => $passengerPhone
]);
check($historyRes['http'] === 200 && !empty($historyRes['body']['success']), "Booking history query succeeds");
$customerBookings = $historyRes['body']['data'] ?? [];
$foundBookingInHistory = false;
foreach ($customerBookings as $b) {
    if (($b['ref'] ?? '') === $bookingRef) {
        $foundBookingInHistory = true;
        break;
    }
}
check($foundBookingInHistory, "Customer booking history returns the confirmed booking $bookingRef");

// Stranger phone cannot access Rahul's booking
$strangerHistory = bops_dispatch([
    'action' => 'list',
    'entity' => 'bookings',
    'slug' => $slug,
    'phone' => '1112223334'
]);
$strangerRows = $strangerHistory['body']['data'] ?? [];
$foundStranger = false;
foreach ($strangerRows as $b) {
    if (($b['ref'] ?? '') === $bookingRef) { $foundStranger = true; break; }
}
check(!$foundStranger, "Privacy enforced: stranger phone cannot see booking $bookingRef");

// -----------------------------------------------------------------------------
// STEP 10: Ticket Retrieval & Verification
// -----------------------------------------------------------------------------
$ticketRes = bops_dispatch([
    'action' => 'ticket',
    'entity' => 'bookings',
    'slug' => $slug,
    'ref' => $bookingRef,
    'phone' => $passengerPhone
]);
check($ticketRes['http'] === 200 && !empty($ticketRes['body']['success']), "Ticket retrieval by ref + phone succeeds");
$ticketData = $ticketRes['body']['data'] ?? [];
check(($ticketData['ref'] ?? '') === $bookingRef, "Retrieved ticket matches booking reference");
check(($ticketData['passenger'] ?? '') === $passengerName, "Retrieved ticket has passenger name: $passengerName");

// Public QR Verification via verify.php logic
$verifyRes = bops_dispatch([
    'action' => 'verify',
    'entity' => 'bookings',
    'slug' => $slug,
    'ref' => $bookingRef,
    'token' => $verifyToken
]);
check($verifyRes['http'] === 200 && !empty($verifyRes['body']['success']), "Ticket verification with token succeeds");
check(in_array(($verifyRes['body']['verdict'] ?? ''), ['valid', 'pending'], true), "Ticket verdict is valid/pending (verdict: " . ($verifyRes['body']['verdict'] ?? '') . ")");

// -----------------------------------------------------------------------------
// STEP 11: Admin Approves Booking & Records Payment
// -----------------------------------------------------------------------------
// Mock admin session
$_SESSION['admin_auth'] = true;
$_SESSION['admin_user'] = 'city_express_admin';
$_SESSION['admin_site'] = $slug;

// Admin reads bookings
$adminBookingsRes = bops_dispatch([
    'action' => 'list',
    'entity' => 'bookings',
    'slug' => $slug
]);
$adminList = $adminBookingsRes['body']['data'] ?? [];
$adminFound = false;
foreach ($adminList as $b) {
    if (($b['ref'] ?? '') === $bookingRef) { $adminFound = true; break; }
}
check($adminFound, "Admin bookings list includes the customer booking");

// Admin approves booking
$approveRes = bops_dispatch([
    'action' => 'update',
    'entity' => 'bookings',
    'slug' => $slug,
    'id' => $bookingData['id'],
    'status' => 'confirmed'
]);
check($approveRes['http'] === 200 && !empty($approveRes['body']['success']), "Admin approves booking (status -> confirmed)");

// Admin records payment
$payRes = bops_dispatch([
    'action' => 'pay',
    'entity' => 'bookings',
    'slug' => $slug,
    'ref' => $bookingRef,
    'method' => 'cash',
    'amount' => 1900
]);
check($payRes['http'] === 200 && !empty($payRes['body']['success']), "Admin records cash payment of ₹1900");

// Verify booking payment status is updated to 'paid'
$checkPaid = bops_dispatch([
    'action' => 'ticket',
    'entity' => 'bookings',
    'slug' => $slug,
    'ref' => $bookingRef,
    'phone' => $passengerPhone
]);
check(($checkPaid['body']['data']['payment_status'] ?? '') === 'paid', "Booking payment_status is now 'paid'");

// Reset admin session
unset($_SESSION['admin_auth'], $_SESSION['admin_user'], $_SESSION['admin_site']);

// -----------------------------------------------------------------------------
// STEP 12: Cancellation & Seat Release
// -----------------------------------------------------------------------------
$cancelRes = bops_dispatch([
    'action' => 'cancel',
    'entity' => 'bookings',
    'slug' => $slug,
    'id' => $bookingData['id'],
    'phone' => $passengerPhone,
    'email' => $passengerEmail
]);
check($cancelRes['http'] === 200 && !empty($cancelRes['body']['success']), "Customer cancels booking");

// Check seats are released
$availRes4 = bops_dispatch([
    'action' => 'availability',
    'entity' => 'bookings',
    'slug' => $slug,
    'trip_id' => $tripId
]);
$reservedAfterCancel = $availRes4['body']['data']['reserved_seats'] ?? [];
check(!in_array('A1', $reservedAfterCancel, true) && !in_array('A2', $reservedAfterCancel, true), "Cancelled booking seats A1 and A2 are released and available again");

// Clean up test trip
bops_transact($slug, 'trips', function ($rows) use ($tripId) {
    return [array_values(array_filter($rows, fn($r) => ($r['id'] ?? '') !== $tripId)), true];
});
bops_transact($slug, 'bookings', function ($rows) use ($bookingRef) {
    return [array_values(array_filter($rows, fn($r) => ($r['ref'] ?? '') !== $bookingRef)), true];
});
$adminTrips = array_values(array_filter(json_decode(file_get_contents($adminTripsFile), true) ?? [], fn($r) => ($r['id'] ?? '') !== $tripId));
file_put_contents($adminTripsFile, json_encode($adminTrips, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n========================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
if ($failCount === 0) {
    echo "SUCCESS: All City Express Travels requirements verified!\n";
} else {
    echo "FAILURE: $failCount checks failed.\n";
}
echo "========================================\n";
exit($failCount > 0 ? 1 : 0);
