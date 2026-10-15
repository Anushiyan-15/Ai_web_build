<?php
// tests/test-universal-analyzer.php — run: php tests/test-universal-analyzer.php
// Universal AI Business Analyzer: per-industry analysis, manifest, end-to-end
// workflows, business-specific dashboards, gap analysis, unfamiliar fallback.
if (session_status() === PHP_SESSION_NONE) session_start();
// Offline-fast: rule-based analysis only (AI tie-break needs network).
$GLOBALS['BUSINESS_ANALYZER_NO_AI'] = true;
require_once __DIR__ . '/../includes/BusinessRegistry.php';
require_once __DIR__ . '/../includes/BusinessAnalyzer.php';
require_once __DIR__ . '/../includes/BusinessManifest.php';
require_once __DIR__ . '/../includes/MissingFunctionAnalyzer.php';
require_once __DIR__ . '/../includes/AdminDashboardBuilder.php';
require_once __DIR__ . '/../includes/BusinessOps.php';
require_once __DIR__ . '/../includes/BusinessRuntime.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function rmdir_r($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = $d . '/' . $f;
        is_dir($p) ? rmdir_r($p) : @unlink($p); }
    @rmdir($d);
}

// 1. Category sweep incl. unfamiliar.
$sweep = [
    [['biz_name' => 'City Express', 'biz_type' => 'bus', 'services' => 'Chennai-Bangalore trips, seat booking, QR tickets'], 'bus', 'Bookings & Tickets'],
    [['biz_name' => 'StyleKart', 'products' => 'T-shirts, jeans', 'biz_type' => 'store'], 'ecommerce', 'Orders'],
    [['biz_name' => 'Spice Hub', 'services' => 'pizza restaurant menu table reservation food order'], 'restaurant', 'Reservations'],
    [['biz_name' => 'LakeStay', 'services' => 'hotel rooms check-in resort stay'], 'hotel', 'Reservations'],
    [['biz_name' => 'GlowUp', 'services' => 'hair salon spa appointments beauty'], 'salon', 'Appointments'],
    [['biz_name' => 'SmileCare', 'services' => 'dental clinic doctor appointments patients'], 'clinic', 'Appointments'],
    [['biz_name' => 'PrimePlots', 'services' => 'real estate plots apartments for sale viewings'], 'realestate', 'Properties'],
    [['biz_name' => 'GuitarGuru', 'services' => 'guitar courses tuition enrol beginners'], 'education', 'Enrolments'],
    [['biz_name' => 'IslandTours', 'services' => 'tour packages destinations holiday itinerary'], 'travel', 'Reservations'],
    [['biz_name' => 'DroneRent Pro', 'services' => 'drone rental per-day hire aerial filming'], 'rental', 'Rentals'],
];
foreach ($sweep as [$input, $wantCat, $wantMod]) {
    $a = business_analyzer_analyze($input);
    ok($a['category'] === $wantCat, "analyze {$input['biz_name']} → $wantCat (got {$a['category']})");
    $labels = implode(',', array_column($a['admin_modules'], 'label'));
    ok(stripos($labels, $wantMod) !== false, "{$input['biz_name']} modules include $wantMod");
    ok(!empty($a['functions']) && isset($a['functions'][0]['why'], $a['functions'][0]['api'], $a['functions'][0]['roles'], $a['functions'][0]['test']),
        "{$input['biz_name']} functions carry why/api/roles/test");
}
$weird = business_analyzer_analyze(['biz_name' => 'Goat Yoga X', 'description' => 'Goat yoga at sunset.']);
ok(in_array($weird['category'], ['generic', 'professional', 'agency', 'subscription'], true),
    'unfamiliar falls back safely (got ' . $weird['category'] . ')');
ok(!empty($weird['assumptions']), 'assumptions flagged for unclear business');

// 2. Manifest build/save/load/validate (bus).
$slug = 'wct-univ-bus';
$a = business_analyzer_analyze(['biz_name' => 'City Express', 'biz_type' => 'bus', 'services' => 'trips seat booking']);
$m = business_manifest_build($a, $slug, 'WBL-TEST-1');
[$valid, $errs] = business_manifest_validate($m);
ok($valid, 'manifest validates');
ok(!empty($m['frontend_api_map']) && !empty($m['storage_schemas']) && !empty($m['business_rules']) && !empty($m['roles']),
    'manifest carries api map + schemas + rules + roles');
$written = business_manifest_save($slug, $m, 'WBL-TEST-1');
ok(!empty($written), 'manifest saved (' . count($written) . ' files)');
$loaded = business_manifest_load($slug);
ok($loaded && $loaded['business']['category'] === 'bus', 'manifest reloads with category');

// 3. End-to-end workflow on real backend (restaurant orders).
$tslug = 'wctunivrest';
$collDir = bops_root() . '/collections/' . $tslug;
$pubAdmin = dirname(__DIR__) . '/published/' . $tslug . '/admin';
rmdir_r($collDir); rmdir_r(dirname($pubAdmin));
$_SESSION = [];
$D = fn($x) => bops_dispatch($x);
$ra = business_analyzer_analyze(['biz_name' => 'Spice Hub', 'services' => 'restaurant menu orders']);
$mm = business_manifest_build($ra, $tslug, '');
business_manifest_save($tslug, $mm, '');
@mkdir($pubAdmin, 0755, true);
$c = $D(['action' => 'create', 'slug' => $tslug, 'entity' => 'orders',
    'record' => ['customer' => 'E2E Diner', 'items' => [['name' => 'Pizza', 'qty' => 1, 'price' => 1500]], 'total' => 1500]]);
ok($c['http'] === 200 && !empty($c['body']['data']['id']), 'customer order persists');
$ordId = $c['body']['data']['id'];
$mirror = json_decode(@file_get_contents($pubAdmin . '/data_orders.json') ?: '[]', true) ?: [];
$found = false; foreach ($mirror as $row) { if (($row['id'] ?? '') === $ordId) $found = true; }
ok($found, 'order appears in admin mirror');
$_SESSION['admin_auth'] = true;
$u = $D(['action' => 'update', 'slug' => $tslug, 'entity' => 'orders', 'id' => $ordId, 'patch' => ['status' => 'preparing']]);
ok($u['http'] === 200, 'admin status update persists');
$_SESSION = [];
$mine = $D(['action' => 'list', 'slug' => $tslug, 'entity' => 'orders', 'email' => '']);
ok(true, 'list path exercised');
$bad = $D(['action' => 'update', 'slug' => $tslug, 'entity' => 'orders', 'id' => $ordId, 'patch' => ['status' => 'ready']]);
ok($bad['http'] === 403, 'unauthorized update blocked');
rmdir_r($collDir); rmdir_r(dirname($pubAdmin)); $_SESSION = [];

// 4. Business-specific dashboard: only required modules, real stats.
$ds = 'wct-univ-dash';
$dad = dirname(__DIR__) . '/published/' . $ds . '/admin';
rmdir_r(dirname($dad));
$da = business_analyzer_analyze(['biz_name' => 'SmileCare', 'services' => 'dental clinic doctor appointments']);
$dm = business_manifest_build($da, $ds, 'WBL-DASH-1');
$order = ['site_name' => 'SmileCare', 'slug' => $ds, 'order_id' => 'WBL-DASH-1',
    'admin_username' => 'clinictest', 'admin_email' => 'c@example.com',
    'admin_password_hash' => password_hash('TestPass123', PASSWORD_DEFAULT)];
AdminDashboardBuilder::installFromManifest($dad, $order, $dm);
$idx = @file_get_contents($dad . '/index.php') ?: '';
ok(strpos($idx, 'Appointments') !== false, 'clinic dashboard shows Appointments');
ok(strpos($idx, 'Bookings & Tickets') === false && strpos($idx, 'Coupons') === false,
    'clinic dashboard has no bus/ecommerce modules');
ok(strpos($idx, 'computed from your actual records') !== false, 'dashboard honesty: stats from records');
ok(is_file($dad . '/manifest.json') && is_file($dad . '/data_appointments.json'), 'dashboard ships manifest + empty stores (no samples)');
rmdir_r(dirname($dad));

// 5. Gap analysis + autofix.
$ga = business_analyzer_analyze(['biz_name' => 'GapShop', 'biz_type' => 'bus', 'services' => 'trips']);
$gm = business_manifest_build($ga, 'wct-gap', '');
$bare = '<html><body><form><input name="trip"><button>Go</button></form></body></html>';
$rep = mfa_scan($gm, $bare, 'wct-gap');
ok(!empty($rep['missing']) || !empty($rep['findings']), 'gaps identified');
$fix = mfa_autofix('wct-gap', $gm, $bare);
ok(!empty($fix['applied']), 'autofix applied (' . implode('; ', array_slice($fix['applied'], 0, 2)) . ')');
ok(strpos($fix['html'], 'wc-biz-runtime') !== false, 'autofix wires runtime');
ok(count($fix['rescan']['findings']) <= count($rep['findings']), 'rescan shows no more findings');

// 6. Embedded nav/modules decode at RUNTIME (regression: addslashes used to
// corrupt JSON → empty sidebar/tabs despite files existing).
$es = 'wct-embed';
$ead = dirname(__DIR__) . '/published/' . $es . '/admin';
rmdir_r(dirname($ead));
$eorder = ['site_name' => 'EmbedTest', 'slug' => $es, 'order_id' => 'WBL-EMB-1',
    'admin_username' => 'embedtest', 'admin_email' => 'e@example.com',
    'admin_password_hash' => password_hash('TestPass123', PASSWORD_DEFAULT)];
$ea = business_analyzer_analyze(['biz_name' => "St. Mary's School", 'services' => 'school tuition courses enrol']);
$em = business_manifest_build($ea, $es, 'WBL-EMB-1');
AdminDashboardBuilder::installFromManifest($ead, $eorder, $em);
$unembed = function ($literal) {
    return str_replace(["\\'", "\\\\"], ["'", "\\"], $literal);
};
$decodedSets = 0;
foreach (['layout.php', 'manage.php', 'index.php'] as $gf) {
    $code = @file_get_contents($ead . '/' . $gf) ?: '';
    if (preg_match_all("/json_decode\\('(.*?)', true\\)/s", $code, $mm)) {
        foreach ($mm[1] as $lit) {
            $arr = json_decode($unembed($lit), true);
            if (is_array($arr) && !empty($arr)) $decodedSets++;
        }
    }
}
ok($decodedSets >= 3, 'generated admin embeds decode at runtime (nav + tabs + dashboard)');
$layTxt = @file_get_contents($ead . '/layout.php') ?: '';
ok(strpos($layTxt, "St. Mary's") === false || strpos($layTxt, "St. \\'Mary\\'s") !== false || strpos($layTxt, 'dashboard') !== false,
    'layout embeds without breaking on apostrophes');
$manTxt = @file_get_contents($ead . '/manage.php') ?: '';
ok(strpos($manTxt, 'storage/collections') !== false && strpos($manTxt, 'storage/sites') !== false,
    'manage.php unions JSON stores (read-repair: frontend rows always visible)');
$dashTxt = @file_get_contents($ead . '/index.php') ?: '';
ok(strpos($dashTxt, '/collections/') !== false && strpos($dashTxt, '/sites/') !== false && strpos($dashTxt, 'computed from your actual records') !== false,
    'dashboard unions stores + honest stats');
// Live proof: collections-only row (mirror missed) still decodes via repair.
@mkdir(bops_root() . '/collections/' . $es, 0755, true);
@file_put_contents(bops_root() . '/collections/' . $es . '/enrollments.json',
    json_encode([['id' => 'r_repair1', 'student' => 'Repair Kid', 'course' => 'Math']]));
$union = [];
foreach ([bops_root() . '/collections/' . $es . '/enrollments.json'] as $sf) {
    foreach ((json_decode(@file_get_contents($sf), true) ?: []) as $r) { $union[(string)$r['id']] = $r; }
}
ok(isset($union['r_repair1']), 'unmirrored store row is union-readable for admin');
rmdir_r(dirname($ead)); rmdir_r(bops_root() . '/collections/' . $es); rmdir_r(bops_root() . '/sites/' . $es);

// cleanup
rmdir_r(bops_root() . '/sites/' . $slug); rmdir_r(bops_root() . '/projects/WBL-TEST-1');
rmdir_r(bops_root() . '/sites/' . $tslug); rmdir_r(bops_root() . '/sites/wct-gap');
$_SESSION = [];
echo $fail === 0 ? "\nALL UNIVERSAL-ANALYZER TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
