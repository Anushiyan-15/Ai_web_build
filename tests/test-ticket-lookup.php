<?php
// tests/test-ticket-lookup.php — run: php tests/test-ticket-lookup.php
// Phone + booking-ref ticket retrieval (no login): both must match the same
// record; ref-only / phone-only / wrong values reveal nothing (no enumeration).
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/BusinessOps.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function rmdir_r($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = $d . '/' . $f;
        is_dir($p) ? rmdir_r($p) : @unlink($p); }
    @rmdir($d);
}
$SLUG = 'wcticket';
rmdir_r(bops_root() . '/collections/' . $SLUG);
$_SESSION = [];
$D = fn($a) => bops_dispatch($a);

// Seed one booking with phone + email.
$c = $D(['action' => 'create', 'slug' => $SLUG, 'entity' => 'bookings',
    'record' => ['trip_id' => 'D1', 'seats' => ['B4'], 'passenger' => 'Lookup Rider',
        'phone' => '0771112222', 'email' => 'lookup@example.com']]);
ok($c['http'] === 200, 'seed booking created');
$ref = $c['body']['data']['ref'] ?? '';
ok(strpos($ref, 'BK-') === 0, 'seed booking has BK- ref');

// 1. Phone + ref retrieve the ticket (no login).
$t = $D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'phone' => '0771112222', 'ref' => $ref]);
ok($t['http'] === 200 && ($t['body']['data']['ref'] ?? '') === $ref, 'phone + ref retrieves ticket');
ok(($t['body']['data']['passenger'] ?? '') === 'Lookup Rider', 'ticket carries real passenger + seats');

// 2. Formatted phone with country code still matches.
$t2 = $D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'phone' => '+94 77 111 2222', 'ref' => strtolower($ref)]);
ok($t2['http'] === 200, 'country-code phone + lowercase ref still match');

// 3. Email + ref also works.
$t3 = $D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'email' => 'lookup@example.com', 'ref' => $ref]);
ok($t3['http'] === 200, 'email + ref retrieves ticket');

// 4. No enumeration: ref-only, phone-only, wrong values → nothing.
ok($D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'ref' => $ref])['http'] === 400, 'ref without contact rejected (400)');
ok($D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'phone' => '0771112222'])['http'] === 400, 'phone without ref rejected (400)');
ok($D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'phone' => '0770000000', 'ref' => $ref])['http'] === 404, 'wrong phone finds nothing (404)');
ok($D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'phone' => '0771112222', 'ref' => 'BK-DEADBEEF'])['http'] === 404, 'wrong ref finds nothing (404)');

// 5. Someone else's phone + my ref finds nothing (cross-match blocked).
ok($D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'email' => 'stranger@example.com', 'ref' => $ref])['http'] === 404, 'stranger email + my ref finds nothing');

// 6. Honeypot + short numbers.
ok($D(['action' => 'ticket', 'slug' => $SLUG, 'entity' => 'bookings',
    'phone' => '0771112222', 'ref' => $ref, 'company' => 'bot'])['http'] === 403, 'honeypot bots rejected');
ok(bops_phone_match('123', '123') === false, 'short digit strings never match');
ok(bops_phone_match('0771112222', '+94-77-111-2222') === true, 'formatting-insensitive match');
ok(bops_phone_match('0771112222', '0771113333') === false, 'different numbers do not match');

// 7. No secrets leak in the ticket payload.
$payload = json_encode($t['body']['data'] ?? []);
ok(stripos($payload, 'idempotency') === false, 'no internal keys in ticket payload');

rmdir_r(bops_root() . '/collections/' . $SLUG);
$_SESSION = [];
echo $fail === 0 ? "\nALL TICKET-LOOKUP TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
