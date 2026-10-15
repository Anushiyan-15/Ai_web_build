<?php
// tests/test-business-manifest.php — run: php tests/test-business-manifest.php
// Covers: per-category manifests (relevant modules only), unfamiliar fallback,
// AI prompt carries workflows + JSON-store rules (no template-only output).
require_once __DIR__ . '/../includes/BizIntelligence/Capabilities.php';
require_once __DIR__ . '/../includes/BizIntelligence/Schema.php';
require_once __DIR__ . '/../includes/BizIntelligence/Pipeline.php';
require_once __DIR__ . '/../includes/AdminAi.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

$bus = ['biz_name' => 'City Express', 'biz_type' => 'Bus booking',
    'biz_services' => 'Chennai-Bangalore trips', 'sections' => ['hero', 'contact']];
$spec = biz_repair_spec(biz_build_spec($bus, 'Bus booking with seats, QR tickets, counter pay.'));
$m = biz_business_manifest($spec, 'city-express', 'City Express');
ok($m['category'] === 'bus', 'bus category detected (got ' . $m['category'] . ')');
$mods = implode(',', $m['adminModules']);
ok(stripos($mods, 'Bookings') !== false, 'bus modules include bookings ops');
ok(!empty($m['entities']) && !empty($m['workflows']), 'manifest carries entities + workflows');
ok(!empty($m['capabilities']), 'manifest carries capabilities');

// Unfamiliar business → generic, never a fixed bus list
$weird = biz_repair_spec(biz_build_spec(['biz_name' => 'X'], 'Goat yoga at sunset.'));
$mw = biz_business_manifest($weird, 'x', 'X');
ok($mw['category'] === 'generic', 'unfamiliar falls back to generic (got ' . $mw['category'] . ')');
ok(stripos(implode(',', $mw['adminModules']), 'Bookings') === false, 'generic has no bus modules');

// Category sweep: relevant-only modules (capabilities drive modules)
$sweep = [
    'Fashion store summer sale' => ['ecommerce', 'Orders', ['catalog', 'orders']],
    'Pizza restaurant menu order' => ['restaurant', 'Reservations', ['catalog', 'orders']],
    'Hair salon appointments' => ['salon', 'Appointments', ['scheduling', 'booking']],
    'Downtown dental clinic' => ['clinic', 'Appointments', ['scheduling']],
    'Lakeview apartments for sale' => ['realestate', 'Properties', ['catalog']],
    'Guitar courses for beginners' => ['education', 'Courses', ['courses']],
];
foreach ($sweep as $text => [$cat, $mustHave, $caps]) {
    $mm = biz_business_manifest(['profile' => ['business_name' => 'T', 'description' => $text],
        'entities' => [], 'workflows' => [], 'capabilities' => $caps], 't', 'T');
    ok($mm['category'] === $cat && stripos(implode(',', $mm['adminModules']), $mustHave) !== false,
        "category $cat carries $mustHave");
}

// AI prompt: workflows + store-layer rules fed (not templates-only)
$pr = admin_ai_prompt(['bizName' => 'City Express', 'bizType' => 'bus',
    'userRequirements' => 'x', 'genMode' => 'admin',
    'entities' => [['label' => 'Trips'], ['label' => 'Bookings']],
    'workflows' => [['name' => 'Reservation lifecycle', 'steps' => ['hold', 'confirm', 'pay']]]]);
ok(stripos($pr, 'hold') !== false && stripos($pr, 'confirm') !== false, 'prompt carries operating workflows');
ok(stripos($pr, 'store.php') !== false && stripos($pr, 'flock') !== false, 'prompt requires JSON store layer');
ok(stripos($pr, 'JSON FILES ARE THE DATABASE') !== false, 'prompt pins JSON-as-database');

echo $fail === 0 ? "\nALL MANIFEST TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
