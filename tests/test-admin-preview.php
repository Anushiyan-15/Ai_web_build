<?php
// tests/test-admin-preview.php — run: php tests/test-admin-preview.php
// REAL generated-admin preview (api/admin-preview.php + AdminPreview):
// manifest-driven modules (never generic prebuilt), working login,
// full CRUD per real entity, engine badge, single-file HTML.
require_once __DIR__ . '/../includes/BusinessRegistry.php';
require_once __DIR__ . '/../includes/BusinessAnalyzer.php';
require_once __DIR__ . '/../includes/BusinessManifest.php';
require_once __DIR__ . '/../includes/AdminPreview.php';
// Offline fast path: rule-based detection only (no AI network per case).
$GLOBALS['BUSINESS_ANALYZER_NO_AI'] = true;
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

// 1. Bus manifest → preview has bus modules, no generic samples.
$a = business_analyzer_analyze(['biz_name' => 'City Express',
    'biz_type' => 'Bus, Transport & Travel',
    'description' => 'daily bus service Chennai-Bangalore, seat booking, QR tickets, counter payment',
    'services' => 'bus service seat booking']);
$m = business_manifest_build($a, 'city-express', 'ord1');
ok(($m['business']['category'] ?? '') === 'bus', 'bus category detected');
$html = admin_preview_html('City Express', $m, ['engine' => 'openrouter', 'model' => 'x-free', 'note' => 't']);
ok(strpos($html, 'AP.login') !== false, 'login works in preview');
ok(stripos($html, 'trips') !== false && stripos($html, 'booking') !== false && stripos($html, 'payment') !== false, 'bus modules present');
ok(strpos($html, 'data-save') !== false, 'CRUD save wired');
ok(strpos($html, 'Standard Package') === false, 'no generic prebuilt rows');
ok(strpos($html, 'openrouter') !== false && stripos($html, '</html>') !== false, 'engine badge + single file');

// 2. WORLD businesses: every category analyzes to its own modules and the
// universal preview renders full functions (never bus-only, never generic).
$world = [
    ['City Express', 'daily bus service Chennai-Bangalore, seat booking, QR tickets', 'bus', ['trips', 'bookings', 'payments']],
    ['FreshMart', 'online grocery shop with cart checkout and home delivery', 'ecommerce', ['products', 'orders']],
    ['Spice Villa', 'restaurant with food menu, table reservations and takeaway orders', 'restaurant', ['menu_items', 'reservations']],
    ['Ocean View', 'hotel with room booking, check-in check-out and billing', 'hotel', ['rooms', 'reservations']],
    ['GlowUp', 'beauty salon with haircut facial services and appointment booking', 'salon', ['services', 'appointments']],
    ['SmileCare', 'dental clinic with doctor appointments and patient records', 'clinic', ['doctors', 'appointments']],
    ['DreamHomes', 'real estate agency with property listings and viewing schedules', 'realestate', ['properties', 'viewings']],
    ['Bright Academy', 'tuition school with courses and student enrolments', 'education', ['courses', 'enrollments']],
    ['WanderLanka', 'travel agency with tour packages and seat reservations', 'travel', ['packages', 'bookings']],
    ['Star Events', 'event hall with concerts and ticket reservations', 'events', ['events', 'tickets']],
    ['RentHub', 'equipment rental with units and date bookings', 'rental', ['units', 'rentals']],
    ['AutoCare', 'car service garage with vehicle jobs and enquiries', 'automotive', ['vehicles', 'service_jobs']],
];
foreach ($world as [$nm, $desc, $cat, $wantIds]) {
    $aa = business_analyzer_analyze(['biz_name' => $nm, 'description' => $desc, 'services' => $desc]);
    ok(($aa['category'] ?? '') === $cat, "$nm → $cat");
    $mm = business_manifest_build($aa, 't-' . $cat, 'ord-t');
    $ids = array_column($mm['entities'], 'id');
    $hit = true;
    foreach ($wantIds as $w) { if (!in_array($w, $ids, true)) $hit = false; }
    ok($hit, "$nm modules (" . implode(',', $ids) . ")");
    $hh = admin_preview_html($nm, $mm, ['engine' => 'server', 'model' => 'manifest-live']);
    $modsOk = true;
    foreach ($wantIds as $w) { if (stripos($hh, $w) === false) $modsOk = false; }
    ok($modsOk && strpos($hh, 'AP.login') !== false && strpos($hh, 'data-save') !== false
        && strpos($hh, 'ap-search') !== false && strpos($hh, 'Standard Package') === false, "$nm preview: real modules + login + CRUD + search, no generic rows");
}

// 3. Generic business → its own modules, still full functions.
$a2 = business_analyzer_analyze(['biz_name' => 'Apex Studio', 'biz_type' => 'Creative & Digital Agency', 'description' => 'design studio']);
$m2 = business_manifest_build($a2, 'apex', 'ord2');
$html2 = admin_preview_html('Apex Studio', $m2, ['engine' => 'server', 'model' => 'manifest-live']);
ok(strpos($html2, 'AP.login') !== false && stripos($html2, '</html>') !== false, 'generic preview complete');

// 4. Endpoint + wiring present. NOTE: the builder workspace no longer embeds
// this single-file localStorage mock — the Admin tab provisions a REAL
// pre-publish env (api/preview-env.php → preview/<draft>/ with real PHP +
// real JSON). This library/endpoint stays tested for compatibility.
ok(file_exists(__DIR__ . '/../api/admin-preview.php'), 'preview endpoint exists');
ok(file_exists(__DIR__ . '/../api/preview-env.php'), 'real preview-env endpoint exists');
$b = (string)@file_get_contents(__DIR__ . '/../builder.php');
ok(strpos($b, 'ensurePreviewEnv') !== false && strpos($b, 'api/preview-env.php') !== false, 'workspace Admin tab provisions real preview env');
ok(strpos($b, 'Live after publish') === false, 'no publish-gated mock text in builder');

echo $fail === 0 ? "\nALL ADMIN-PREVIEW TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
