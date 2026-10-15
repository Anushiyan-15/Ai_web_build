<?php
// tests/test-business-spec.php — run: php tests/test-business-spec.php
require_once __DIR__ . '/../includes/BusinessSpec.php';
$fail = 0;
function ok($cond, $msg) { global $fail; echo ($cond ? "PASS" : "FAIL") . ": $msg\n"; if (!$cond) $fail++; }

// 1. No mode selection: spec auto-decides gen_mode
$bus = business_spec_analyze(['biz_name'=>'City Express Travels','biz_type'=>'Bus booking','biz_tagline'=>'Daily buses','biz_services'=>'Chennai to Bangalore daily trips','sections'=>['hero','contact']]);
ok($bus['business_type']==='booking', 'bus business detected as booking');
ok(!empty($bus['gen_mode']), 'gen_mode auto-decided');
ok(in_array('bookings', array_column($bus['entities'],'name')), 'bookings entity present');
ok(in_array('trips', array_column($bus['entities'],'name')), 'trips entity present');

// 2. Different descriptions -> different feature sets
$shop = business_spec_analyze(['biz_name'=>'Aura Fashion','biz_type'=>'Ecommerce store','biz_tagline'=>'Clothes','biz_products'=>"Tee | Rs.500",'sections'=>['shop']]);
ok($shop['business_type']==='ecommerce', 'shop detected as ecommerce');
ok($shop['features'] !== $bus['features'], 'bus vs shop features differ');
ok(in_array('cart', $shop['features']), 'shop has cart');

// 3. Unknown business -> generic, still useful
$weird = business_spec_analyze(['biz_name'=>'Pet Yoga','biz_type'=>'Goat yoga studio','biz_tagline'=>'Yoga with goats','biz_services'=>'Weekend sessions, Kids classes']);
ok(!empty($weird['entities']), 'unknown business still yields entities');
ok(!empty($weird['pages']), 'unknown business still yields pages');

// 4. Exactly 3 Gemini variations policy (config check)
$svc = file_get_contents(__DIR__ . '/../includes/GeminiService.php');
ok(strpos($svc, 'gemini-3.5-flash-lite') !== false, 'gemini-3.5-flash-lite integration present');
$js = file_get_contents(__DIR__ . '/../assets/js/opencode-service.js');
ok(substr_count($js, 'gemini-3.5-flash-lite') >= 3, 'frontend uses gemini-3.5-flash-lite lanes');
ok(strpos($js, 'enhanceLandingWithSpaceBunny') !== false, 'Space Bunny landing-only function exists');
// Slot lanes must be Gemini-only (no quick lane inside SLOT_LANES)
if (preg_match('/const SLOT_LANES = \[(.*?)\];/s', $js, $m)) {
    ok(strpos($m[1], "'quick'") === false && strpos($m[1], '"quick"') === false, '3 variation lanes are Gemini-only');
} else { ok(false, 'SLOT_LANES found'); }

// 5. No mode picker in builder
$b = file_get_contents(__DIR__ . '/../builder.php');
ok(strpos($b, "pickGenerateMode('static'") === false, 'static/admin/database mode picker removed');
ok(strpos($b, 'refreshBizSpec') !== false, 'auto business analysis wired');

// 6. Booking ops backend exists
ok(file_exists(__DIR__ . '/../api/business-ops.php'), 'business-ops backend exists');
ok(file_exists(__DIR__ . '/../includes/BusinessRuntime.php'), 'business runtime exists');

echo $fail === 0 ? "\nALL TESTS PASSED\n" : "\n$fail TEST(S) FAILED\n";
exit($fail === 0 ? 0 : 1);
