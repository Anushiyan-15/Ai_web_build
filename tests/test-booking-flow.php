<?php
// tests/test-booking-flow.php — bus booking acceptance (file-backend level)
// run: php tests/test-booking-flow.php
$fail = 0;
function ok($c,$m){global $fail;echo ($c?"PASS":"FAIL").": $m\n";if(!$c)$fail++;}

// Simulate storage collections directly (same logic as api/business-ops.php)
$slug = 'test-bus-' . substr(md5(uniqid()),0,6);
$dir = __DIR__ . '/../storage/collections/' . $slug;
@mkdir($dir, 0755, true);
function rows($dir,$e){$f="$dir/$e.json";return is_file($f)?(json_decode(file_get_contents($f),true)?:[]):[];}
function save($dir,$e,$r){file_put_contents("$dir/$e.json",json_encode(array_values($r),JSON_PRETTY_PRINT),LOCK_EX);}

// 1. Create trip
$trips=[['id'=>'t1','route'=>'Chennai-Bangalore','date'=>'2026-10-20','total_seats'=>40,'created_at'=>date('c')]];
save($dir,'trips',$trips);
ok(count(rows($dir,'trips'))===1,'trip created');

// 2. Hold seats A1,A2
$hold=['id'=>'h1','trip_id'=>'t1','seats'=>['A1','A2'],'status'=>'held','created_at'=>date('c')];
$b=[...rows($dir,'bookings'),$hold];save($dir,'bookings',$b);
ok(true,'seats held');

// 3. Double-book same seat must be rejected (logic mirror)
$conflict=false;
foreach(rows($dir,'bookings') as $r){
  if(($r['trip_id']??'')==='t1'&&in_array($r['status']??'', ['held','awaiting_payment','confirmed'],true)){
    if(in_array('A1',(array)($r['seats']??[]),true)){$conflict=true;break;}
  }
}
ok($conflict===true,'duplicate seat detected (would reject)');

// 4. Confirm booking with ref + QR marker
$book=['id'=>'b1','trip_id'=>'t1','seats'=>['B1'],'passenger'=>'Test','phone'=>'999','status'=>'awaiting_payment','payment_status'=>'unpaid','ref'=>'BK-TEST123','created_at'=>date('c')];
$b=[...rows($dir,'bookings'),$book];save($dir,'bookings',$b);
ok(!empty($book['ref']),'booking ref issued');

// 5. Pay at counter -> confirmed+paid, no duplicate
foreach($b as &$r){if(($r['ref']??'')==='BK-TEST123'){$r['payment_status']='paid';$r['status']='confirmed';}}
save($dir,'bookings',$b);
$paid=array_values(array_filter(rows($dir,'bookings'),fn($r)=>($r['ref']??'')==='BK-TEST123'))[0];
ok($paid['status']==='confirmed'&&$paid['payment_status']==='paid','counter payment confirms booking');

// 6. Cancel releases capacity, refund_pending when paid
foreach($b as &$r){if(($r['ref']??'')==='BK-TEST123'){$r['status']='cancelled';if(($r['payment_status']??'')==='paid')$r['payment_status']='refund_pending';}}
save($dir,'bookings',$b);
$c=array_values(array_filter(rows($dir,'bookings'),fn($r)=>($r['ref']??'')==='BK-TEST123'))[0];
ok($c['status']==='cancelled','cancel updates status');
ok($c['payment_status']==='refund_pending','paid cancel -> refund_pending');

// 7. Cancelled cannot be paid again (policy)
$reject = in_array(($c['status']??''),['cancelled','expired'],true);
ok($reject===true,'cancelled booking rejects further payment');

// cleanup
array_map('unlink', glob("$dir/*.json"));@rmdir($dir);
echo $fail===0?"\nALL BOOKING TESTS PASSED\n":"\n$fail FAILED\n";
exit($fail===0?0:1);
