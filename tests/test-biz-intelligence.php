<?php
// tests/test-biz-intelligence.php — run: php tests/test-biz-intelligence.php
require_once __DIR__ . '/../includes/BizIntelligence/Schema.php';
require_once __DIR__ . '/../includes/BizIntelligence/Pipeline.php';
require_once __DIR__ . '/../includes/BizIntelligence/Diff.php';
$fail = 0;
function ok($c,$m){global $fail;echo ($c?"PASS":"FAIL").": $m\n";if(!$c)$fail++;}

$cases = [
    'bus' => ['biz_name'=>'City Express','description'=>'I want to build a bus booking website where passengers can select seats, receive a QR ticket, pay at the bus stand, and cancel reservations.'],
    'fashion' => ['biz_name'=>'Aura','description'=>'Create an online fashion store with customer accounts and order tracking.'],
    'restaurant' => ['biz_name'=>'Spice Route','description'=>'I run a restaurant that accepts table reservations and takeaway orders.'],
    'construction' => ['biz_name'=>'BuildRight','description'=>'I need a website for a construction company that manages projects, workers, clients, and quotations.'],
    'tutor' => ['biz_name'=>'LearnLoop','description'=>'Build a platform where tutors can offer courses and students can enroll.'],
    'weird' => ['biz_name'=>'GoatYoga','description'=>'Sunset goat yoga in lavender fields with photo packages and farmstay booking.'],
];
$specs = [];
foreach ($cases as $k => $w) {
    $s = biz_repair_spec(biz_build_spec($w, $w['description']));
    [$valid, $errs] = biz_validate_spec($s);
    ok($valid, "$k spec validates" . ($valid ? '' : ' (' . implode(';', $errs) . ')'));
    ok(!empty($s['entities']), "$k has entities");
    ok(!empty($s['workflows']), "$k has workflows");
    ok(!empty($s['tests']), "$k has tests");
    // explicit vs inferred separation
    $srcs = array_unique(array_column($s['requirements'], 'source'));
    ok(in_array('explicit', $srcs) || in_array('inferred', $srcs), "$k classifies requirement sources");
    $specs[$k] = $s;
}
// Bus acceptance: seats/QR/counter-pay/cancel present, policies unresolved not invented
$bus = $specs['bus'];
$blob = json_encode($bus);
ok(stripos($blob,'QR')!==false || stripos($blob,'qr')!==false, 'bus spec covers QR verification');
ok(stripos($blob,'cancel')!==false, 'bus spec covers cancellation');
ok(!empty($bus['uncertainty']['unresolved']), 'bus missing policies recorded as unresolved');
ok(stripos($blob,'double')!==false || stripos($blob,'conflict')!==false, 'bus spec covers concurrency guard');
// Different businesses -> different capabilities (not one template)
ok($specs['bus']['capabilities'] !== $specs['fashion']['capabilities'] || $specs['bus']['entities'] !== $specs['fashion']['entities'], 'bus vs fashion specs differ');
ok($specs['construction']['capabilities'] !== $specs['restaurant']['capabilities'] || json_encode($specs['construction']['entities']) !== json_encode($specs['restaurant']['entities']), 'construction vs restaurant differ');
// Diff support
$d = biz_diff_specs($specs['bus'], $specs['fashion']);
ok(isset($d['added']) && isset($d['removed']), 'diff returns change summary');
// Gemini model identifier check (no silent switch)
$gs = file_get_contents(__DIR__ . '/../includes/BizIntelligence/Pipeline.php');
ok(strpos($gs, 'gemini-3.5-flash-lite') !== false, 'intelligence uses verified gemini-3.5-flash-lite');

echo $fail===0 ? "\nALL INTELLIGENCE TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail===0?0:1);
