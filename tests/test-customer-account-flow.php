<?php
// tests/test-customer-account-flow.php
// Customer account flow on a generated site (through the preview gate):
//   open portal -> register -> signed in -> own booking visible -> cancel ->
//   logout -> login again. The account lives on the SITE and the admin sees
//   the customer in its Customers module.
$base = 'http://localhost/project/webbbuilder/preview/0c5fb868c5a77b3f74205462029907d6';
$slug = '0c5fb868c5a77b3f74205462029907d6';
$pass = 0; $fail = 0;
function check($c, $m) { global $pass, $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
$jar = tempnam(sys_get_temp_dir(), 'custjar');

function req($url, $post = null, $jar = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    $b = curl_exec($ch); $c = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$c, (string)$b];
}
function csrfOf(string $h): string { return preg_match('/name="csrf"\s+value="([^"]+)"/', $h, $m) ? $m[1] : ''; }
function api(string $action, array $payload) {
    $ch = curl_init('http://localhost/project/webbbuilder/api/business-ops.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(array_merge(['action' => $action, 'slug' => '0c5fb868c5a77b3f74205462029907d6'], $payload))]);
    $r = (string)curl_exec($ch); curl_close($ch);
    return json_decode($r, true) ?: [];
}

echo "=== CUSTOMER ACCOUNT FLOW (generated site) ===\n";
$email = 'customer.probe.' . time() . '@example.com';
$password = 'CustomerPass123!';

[$code, $html] = req("$base/customer.php", null, $jar);
check($code === 200 && stripos($html, 'customer') !== false, "portal opens (HTTP $code)");
$csrf = csrfOf($html);
check($csrf !== '', "portal CSRF token present");

[$code, $html] = req("$base/customer.php", ['action' => 'register', 'csrf' => $csrf, 'name' => 'Probe Customer', 'email' => $email, 'phone' => '+94770011223', 'password' => $password], $jar);
check(stripos($html, 'sign out') !== false || stripos($html, 'logout') !== false, "registration signs the customer in");
$csrf = csrfOf($html) ?: $csrf;

$adminCustomers = 'C:/UniServerZ/www/project/webbbuilder/preview/' . $slug . '/admin/data_customers.json';
$rows = json_decode((string)@file_get_contents($adminCustomers), true) ?: [];
$inAdmin = false;
foreach ($rows as $r) { if (strtolower((string)($r['email'] ?? '')) === $email) { $inAdmin = true; break; } }
check($inAdmin, "customer appears in the admin Customers module");

// a booking on a free seat
$bookingRef = ''; $usedSeat = '';
foreach (['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D1', 'D2', 'A3', 'B3', 'C3', 'D3'] as $seat) {
    $res = api('create', ['entity' => 'bookings', 'record' => [
        'trip_id' => 'TRIP-20261021-4A3C0C', 'seats' => [$seat], 'passenger' => 'Probe Customer',
        'phone' => '+94770011223', 'email' => $email, 'status' => 'pending', 'payment_status' => 'unpaid',
    ]]);
    if (!empty($res['success'])) { $bookingRef = (string)($res['data']['ref'] ?? ''); $usedSeat = $seat; break; }
}
check($bookingRef !== '', "booking created for the customer ($bookingRef, seat $usedSeat)");

[$code, $html] = req("$base/customer.php", null, $jar);
check(stripos($html, $bookingRef) !== false || stripos($html, 'Probe Customer') !== false, "the customer's booking shows in their portal");
$csrf = csrfOf($html) ?: $csrf;

[$code, $html] = req("$base/customer.php", ['action' => 'cancel_booking', 'csrf' => $csrf, 'booking_id' => $bookingRef], $jar);
$adminBookings = 'C:/UniServerZ/www/project/webbbuilder/preview/' . $slug . '/admin/data_bookings.json';
$rows = json_decode((string)@file_get_contents($adminBookings), true) ?: [];
$status = '';
foreach ($rows as $r) { if (($r['ref'] ?? '') === $bookingRef) { $status = (string)($r['status'] ?? ''); break; } }
check($status === 'cancelled', "customer cancelled their booking (status: $status)");

$csrf = csrfOf($html) ?: $csrf;
req("$base/customer.php", ['action' => 'logout', 'csrf' => $csrf], $jar);
[$code, $html] = req("$base/customer.php", null, $jar);
check(stripos($html, 'name="password"') !== false, "customer is signed out");
$csrf = csrfOf($html);
[$code, $html] = req("$base/customer.php", ['action' => 'login', 'csrf' => $csrf, 'email' => $email, 'password' => $password], $jar);
check(stripos($html, 'sign out') !== false || stripos($html, 'logout') !== false, "customer can sign in again");

// cleanup
foreach ([$adminCustomers, $adminBookings] as $f) {
    if (!is_file($f)) continue;
    $rows = json_decode((string)@file_get_contents($f), true) ?: [];
    $rows = array_values(array_filter($rows, function ($r) use ($bookingRef, $email) {
        return (($r['ref'] ?? '') !== $bookingRef) && (strtolower((string)($r['email'] ?? '')) !== strtolower($email));
    }));
    file_put_contents($f, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
@unlink($jar);
echo "\n=== SUMMARY: pass=$pass fail=$fail ===\n";
exit($fail === 0 ? 0 : 1);
