<?php
// tests/test-bus-fullstack-regression.php
// Regression coverage for full-stack bus fixes:
// trips mirror, alias normalization, availability breakdown, verify endpoint,
// dynamic seat validation, site isolation, portal dashboard, admin display helper.
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('STORAGE_DIR')) define('STORAGE_DIR', sys_get_temp_dir() . '/webbbuilder-bus-regression-' . getmypid());
$_SESSION = [];

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BusinessOps.php';
require_once __DIR__ . '/../includes/BusinessRuntime.php';
require_once __DIR__ . '/../includes/BusBookingWidget.php';

$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function rmdir_r($d) { if (!is_dir($d)) return; foreach (scandir($d) as $f) { if ($f==='.'||$f==='..') continue; $p=$d.'/'.$f; is_dir($p)?rmdir_r($p):@unlink($p);} @rmdir($d); }
function D($req) { return bops_dispatch($req); }

$SLUG = 'wcreg' . substr(bin2hex(random_bytes(3)), 0, 6);
$OTHER = 'wcregother' . substr(bin2hex(random_bytes(3)), 0, 6);
$DATE = date('Y-m-d', time() + 86400);
@mkdir(dirname(__DIR__).'/published/'.$SLUG.'/admin', 0755, true);

// Test 1 — New trip propagation (+ trips mirror).
$r = D(['action'=>'create','slug'=>$SLUG,'entity'=>'trips','record'=>['route'=>'RegCity → TestTown','origin'=>'RegCity','destination'=>'TestTown','date'=>$DATE,'departure'=>'09:00','price'=>500,'total_seats'=>40]]);
ok($r['http']===200 && str_starts_with((string)($r['body']['data']['id']??''),'TRIP-'), '1: operator trip gets automatic TRIP- id');
$tripId = (string)($r['body']['data']['id'] ?? '');
$mirror = dirname(__DIR__).'/published/'.$SLUG.'/admin/data_trips.json';
ok(is_file($mirror), '1: trip mirrored to published admin data file');
$found = D(['action'=>'list','slug'=>$SLUG,'entity'=>'trips','route'=>'RegCity']);
ok($found['http']===200 && count($found['body']['data']??[])>=1, '1: same trip appears in public search');

// Test 2 — Live seat map breakdown.
$h = D(['action'=>'hold','slug'=>$SLUG,'entity'=>'bookings','trip_id'=>$tripId,'seats'=>['A1']]);
ok($h['http']===200, '2: hold A1 works');
$av = D(['action'=>'availability','slug'=>$SLUG,'entity'=>'bookings','trip_id'=>$tripId]);
$avd = $av['body']['data'] ?? [];
ok($av['http']===200 && in_array('A1',$avd['reserved_seats']??[]) && in_array('A1',$avd['held_seats']??[]), '2: availability exposes held_seats breakdown');
D(['action'=>'release_hold','slug'=>$SLUG,'entity'=>'bookings','ref'=>(string)($h['body']['data']['ref']??'')]);

// Test 3 — Booking persistence with aliases (tripId + seat_numbers).
$b = D(['action'=>'create','slug'=>$SLUG,'entity'=>'bookings','record'=>['tripId'=>$tripId,'seat_numbers'=>['A1','A2'],'passenger'=>'Reg Rider','phone'=>'0771112222','email'=>'regrider@example.com'],'idempotency_key'=>'reg-'.bin2hex(random_bytes(4))]);
$bd = $b['body']['data'] ?? [];
ok($b['http']===200 && str_starts_with((string)($bd['ref']??''),'BK-') && ($bd['trip_id']??'')===$tripId && count($bd['seats']??[])===2, '3: alias payload normalizes to trip_id + seats');
ok(isset($bd['verify_token']) && strlen((string)$bd['verify_token'])>=16, '3: booking carries verify_token');
ok(($bd['amount']??0)==1000.0, '3: server-derived fare 2x500');
$ref = (string)($bd['ref'] ?? '');
$token = (string)($bd['verify_token'] ?? '');
$bid = (string)($bd['id'] ?? '');

// Test 4 — Admin record correctness via view helper.
$view = bops_booking_view(['tripId'=>$tripId,'seat_numbers'=>'A1, A2','passenger'=>'X']);
ok(($view['trip_id']??'')===$tripId && $view['seats']==['A1','A2'], '4: bops_booking_view resolves aliases (no dash)');
$legacyView = bops_booking_view(['trip_id'=>'','seats'=>'','passenger'=>'Anu']);
ok(($legacyView['trip_id']??'')==='' , '4: truly empty stays empty (no invented values)');
// Generator helper present for admin tables.
$pak = (string)file_get_contents(__DIR__.'/../includes/PublishedAdminKit.php');
ok(strpos($pak,'function bus_booking_cell')!==false, '4: admin generator uses alias-aware booking cells');

// Test 5 — Double-booking prevention.
$h2 = D(['action'=>'hold','slug'=>$SLUG,'entity'=>'bookings','trip_id'=>$tripId,'seats'=>['A1']]);
ok($h2['http']===400, '5: booked seat cannot be held again');

// Test 6 — Availability updates for others.
$av2 = D(['action'=>'availability','slug'=>$SLUG,'entity'=>'bookings','trip_id'=>$tripId]);
ok(in_array('A1',$av2['body']['data']['booked_seats']??[]), '6: booked seat listed as booked');

// Test 9 — QR verification (limited + token).
$v1 = D(['action'=>'verify','slug'=>$SLUG,'entity'=>'bookings','ref'=>$ref]);
ok($v1['http']===200 && ($v1['body']['verdict']??'')==='pending' && empty($v1['body']['data']['email']??''), '9: ref-only verify returns verdict without PII');
$v2 = D(['action'=>'verify','slug'=>$SLUG,'entity'=>'bookings','ref'=>$ref,'token'=>$token]);
ok($v2['http']===200 && ($v2['body']['data']['passenger']??'')==='Reg Rider', '9: token verify unlocks full ticket');
$v3 = D(['action'=>'verify','slug'=>$SLUG,'entity'=>'bookings','ref'=>'BK-NOPE1234']);
ok($v3['http']===404, '9: invalid ref not presented as valid');
// Verification is read-only.
$after = D(['action'=>'ticket','slug'=>$SLUG,'entity'=>'bookings','ref'=>$ref,'phone'=>'0771112222']);
ok(($after['body']['data']['status']??'')==='pending' && ($after['body']['data']['payment_status']??'')==='unpaid', '9: verification did not change status/payment');

// Test 10 — Ticket lookup privacy.
$tBad = D(['action'=>'ticket','slug'=>$SLUG,'entity'=>'bookings','ref'=>$ref,'phone'=>'0700000000']);
ok($tBad['http']===404, '10: wrong phone finds nothing');
$listAnon = D(['action'=>'list','slug'=>$SLUG,'entity'=>'bookings','email'=>'regrider@example.com']);
ok($listAnon['http']===200 && count($listAnon['body']['data']??[])===0, '10: anonymous email cannot enumerate history');

// Test 11 — Cancel releases seat.
$_SESSION['customer_user'] = ['email'=>'regrider@example.com'];
$c = D(['action'=>'cancel','slug'=>$SLUG,'entity'=>'bookings','id'=>$bid,'email'=>'regrider@example.com']);
unset($_SESSION['customer_user']);
ok($c['http']===200 && ($c['body']['data']['status']??'')==='cancelled', '11: customer cancels own booking');
$av3 = D(['action'=>'availability','slug'=>$SLUG,'entity'=>'bookings','trip_id'=>$tripId]);
ok(!in_array('A1',$av3['body']['data']['reserved_seats']??[]), '11: cancelled seats released');

// Test 12 — Payments stay unpaid until admin records.
$b2 = D(['action'=>'create','slug'=>$SLUG,'entity'=>'bookings','record'=>['trip_id'=>$tripId,'seats'=>['B1'],'passenger'=>'Pay Tester','phone'=>'0771113333','email'=>'paytester@example.com'],'idempotency_key'=>'regpay-'.bin2hex(random_bytes(4))]);
$ref2 = (string)($b2['body']['data']['ref'] ?? '');
$pi = D(['action'=>'payment_intent','slug'=>$SLUG,'entity'=>'bookings','ref'=>$ref2,'method'=>'counter','email'=>'paytester@example.com']);
ok($pi['http']===200 && ($pi['body']['data']['payment_status']??'')==='unpaid', '12: counter intent stays unpaid');
$_SESSION['admin_auth'] = true;
$payEarly = D(['action'=>'pay','slug'=>$SLUG,'entity'=>'bookings','ref'=>$ref2,'method'=>'cash']);
ok($payEarly['http']===400, '12: pay before approval rejected');
D(['action'=>'update','slug'=>$SLUG,'entity'=>'bookings','id'=>(string)($b2['body']['data']['id']??''),'patch'=>['status'=>'approved']]);
$pay = D(['action'=>'pay','slug'=>$SLUG,'entity'=>'bookings','ref'=>$ref2,'method'=>'cash']);
unset($_SESSION['admin_auth']);
ok($pay['http']===200 && ($pay['body']['data']['payment_status']??'')==='paid', '12: approved booking records payment');

// Test 13 — Site isolation.
D(['action'=>'create','slug'=>$OTHER,'entity'=>'trips','record'=>['route'=>'Other → Place','origin'=>'Other','destination'=>'Place','date'=>$DATE,'departure'=>'10:00','price'=>100,'total_seats'=>20]]);
$iso = D(['action'=>'list','slug'=>$OTHER,'entity'=>'trips']);
$leak = false;
foreach (($iso['body']['data']??[]) as $row) { if (($row['id']??'')===$tripId) $leak = true; }
ok(!$leak, '13: sites cannot read each other trips');
$isoB = D(['action'=>'ticket','slug'=>$OTHER,'entity'=>'bookings','ref'=>$ref,'phone'=>'0771112222']);
ok($isoB['http']===404, '13: ticket not visible cross-site');

// Test 14 — Non-bus regression.
$busSpec = ['profile'=>['business_name'=>'City Express','domain'=>'booking','description'=>'Bus route, trip and seat reservations'],'entities'=>[['key'=>'trips'],['key'=>'bookings']]];
$genSpec = ['profile'=>['business_name'=>'Tasty Bites','domain'=>'restaurant','description'=>'Fresh food'],'entities'=>[['key'=>'menu_items']]];
ok(wc_business_requires_bus_booking($busSpec)===true && wc_business_requires_bus_booking($genSpec)===false, '14: bus detection stays business-specific');
$widget = bus_booking_widget_html($SLUG);
ok(strpos($widget,'bus-legend')!==false && strpos($widget,'Held (checkout)')!==false && strpos($widget,'window.WCBiz.ticketVerifyUrl')!==false, '14: widget legend + verify link present, no hardcoded trips');
ok(strpos($widget,'trip_101')===false, '14: no hardcoded demo trips in widget');

// Test 15 — Portal + verify provisioning + dashboard completeness.
$portal = (string)file_get_contents(__DIR__.'/../includes/templates/bus-customer-portal.php');
ok(strpos($portal,'Upcoming trips')!==false && strpos($portal,'Payments')!==false && strpos($portal,'Edit profile')!==false && strpos($portal,'cancel_booking')!==false && strpos($portal,'verify.php')!==false && strpos($portal,'Download ticket')!==false, '15: customer dashboard has overview/upcoming/history/tickets/payments/profile/cancel');
ok(strpos($portal,'Too many sign-in attempts')!==false, '15: portal rate-limits logins');
ok(is_file(__DIR__.'/../includes/templates/bus-ticket-verify.php'), '15: verify template exists');
ok(is_file(dirname(__DIR__).'/published/express-bus-lines/verify.php'), '15: published bus site ships verify.php');
$rt = business_runtime_js([], $SLUG);
ok(strpos($rt,'ticketVerifyUrl')!==false && strpos($rt,'ticketQRFallback')!==false, '15: runtime QR + fallback present');

// Dynamic capacity: 45-seat bus addressable, Z1 still invalid on 40-seat.
ok(bops_seat_fits_capacity('L1',45)===true, '2b: 45-seat layout addressable');
ok(bops_seat_fits_capacity('Z1',40)===false, '2b: invalid seat still rejected on 40-seat bus');

rmdir_r(bops_root().'/collections/'.$SLUG);
rmdir_r(bops_root().'/sites/'.$SLUG);
rmdir_r(dirname(__DIR__).'/published/'.$SLUG);
rmdir_r(bops_root().'/collections/'.$OTHER);
rmdir_r(bops_root().'/sites/'.$OTHER);
$_SESSION = [];
rmdir_r(STORAGE_DIR);
echo $fail===0 ? "\nALL BUS-FULLSTACK-REGRESSION TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail===0?0:1);
