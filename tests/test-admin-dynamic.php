<?php
// tests/test-admin-dynamic.php — run: php tests/test-admin-dynamic.php
// Covers: canonical-spec entities → per-business admin modules (never the
// same generic dashboard), empty seeds for canonical installs, dynamic
// owner contacts, AI-mix prompt/parse helpers (no network in tests).
require_once __DIR__ . '/../includes/AdminPanelGenerator.php';
require_once __DIR__ . '/../includes/AdminAi.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

// 1. Canonical → module mapping (bus booking business)
$spec = ['schema' => 'biz-spec/1',
    'profile' => ['business_name' => 'City Express'],
    'entities' => [
        ['key' => 'trips', 'name' => 'Trips', 'label' => 'Trips',
         'fields' => ['route:text*', 'date:date*', 'price:number*']],
        ['key' => 'bookings', 'name' => 'Bookings', 'label' => 'Bookings',
         'fields' => ['trip_id:text*', 'seats:json*', 'passenger:text*', 'phone:text', 'status:select:pending|confirmed']],
    ],
    'capabilities' => ['booking', 'payments']];
$mods = AdminPanelGenerator::entitiesFromCanonical($spec);
ok(count($mods) === 2 && $mods[0]['id'] === 'trips' && $mods[1]['id'] === 'bookings', 'canonical ids mapped');
ok($mods[0]['icon'] === '🚌' && $mods[1]['icon'] === '🎫', 'business icons assigned (' . $mods[0]['icon'] . '/' . $mods[1]['icon'] . ')');
ok(count($mods[0]['fields']) === 3 && $mods[0]['fields'][0]['name'] === 'route'
    && $mods[0]['fields'][0]['required'] === true && $mods[0]['fields'][1]['type'] === 'date', 'name:type* fields parsed (names, types, required)');
ok($mods[1]['fields'][4]['name'] === 'status' && $mods[1]['fields'][4]['type'] === 'select', 'select options parsed to select type');
ok(AdminPanelGenerator::entitiesFromCanonical(['entities' => []]) === [], 'empty spec → no modules (caller falls back)');

// 2. Legacy {name,label} lists harden to unique ids (never id-less 'items' collisions)
$norm = AdminPanelGenerator::normalizeEntities([['name' => 'trips', 'label' => 'Trips'], ['name' => 'trips', 'label' => 'Trips']]);
ok(count($norm) === 1 && $norm[0]['id'] === 'trips', 'legacy list deduped with ids');
ok(!empty($norm[0]['fields']), 'legacy modules get safe fallback fields');

// 3. Install with canonical modules → bus admin, no generic modules, empty seeds
$dir = sys_get_temp_dir() . '/wc-adm-dyn-' . substr(md5(uniqid()), 0, 8);
@mkdir($dir, 0755, true);
$order = ['site_name' => 'City Express', 'slug' => 'city-express', 'order_id' => 'WBL-DYN-1',
    'admin_username' => 'city_admin', 'admin_email' => 'owner@cityexpress.local',
    'contact_email' => 'book@cityexpress.local',
    'admin_password_hash' => password_hash('StrongPass123!', PASSWORD_DEFAULT),
    'admin_entities' => $mods, 'admin_seed_empty' => true];
try { AdminPanelGenerator::install($dir, $order); ok(true, 'canonical install succeeds'); }
catch (Throwable $e) { ok(false, 'canonical install succeeds (' . $e->getMessage() . ')'); }
$layout = @file_get_contents($dir . '/layout.php') ?: '';
$manage = @file_get_contents($dir . '/manage.php') ?: '';
ok(stripos($layout, 'Trips') !== false && stripos($layout, 'Bookings') !== false, 'sidebar has bus modules');
ok(stripos($manage, 'trip_id') !== false && stripos($manage, 'passenger') !== false, 'manager renders canonical fields');
ok(stripos($manage, 'Services / Products') === false && stripos($manage, 'Customer Inquiries') === false, 'no generic modules leak in');
$trips = json_decode(@file_get_contents($dir . '/data_trips.json') ?: 'null', true);
ok(is_array($trips) && empty($trips), 'canonical tables seed empty (no fake records)');
$content = json_decode(@file_get_contents($dir . '/content.json') ?: '{}', true);
ok(($content['email'] ?? '') === 'book@cityexpress.local', 'content inbox is the real owner email');
ok(($content['business_name'] ?? '') === 'City Express', 'content carries business name');
// Generated code parses (bridge + Node wiring included)
foreach (['features.php', 'manage.php', 'layout.php', 'index.php'] as $gf) {
    $out = []; $code = 1;
    @exec(PHP_BINARY . ' -l ' . escapeshellarg($dir . '/' . $gf) . ' 2>&1', $out, $code);
    ok($code === 0, "generated $gf parses");
}
ok(strpos(@file_get_contents($dir . '/features.php') ?: '', 'node-api') !== false, 'features tab wired to Node engine (no fake toast)');

// 4. AI-mix helpers: prompt carries entities, JSON extractor handles fences
$pr = admin_ai_prompt(['bizName' => 'City Express', 'bizType' => 'bus', 'userRequirements' => 'x',
    'genMode' => 'admin', 'entities' => [['label' => 'Trips'], ['label' => 'Bookings']]]);
ok(stripos($pr, 'Trips') !== false && stripos($pr, 'Bookings') !== false, 'AI prompt pins exact modules');
$ex = admin_ai_extract_json("```json\n" . '{"files":{"index.php":"x"},"entities":[]}' . "\n```");
ok(is_array($ex) && isset($ex['files']['index.php']), 'fenced JSON extracted');
ok(is_array($ex) && isset($ex['files']['index.php']), 'fenced JSON extracted');
ok(admin_ai_extract_json('no json here') === null, 'non-JSON honestly rejected');

// 5. Honest failure with no keys (no network touched when unconfigured)
$gemKey = (function () { try { return function_exists('gemini_api_key') ? gemini_api_key() : ''; } catch (Throwable $e) { return 'x'; } })();
$ocKey = (function () { try { return function_exists('opencode_is_configured') && opencode_is_configured(); } catch (Throwable $e) { return true; } })();
if ($gemKey === '' && !$ocKey) {
    $r = admin_ai_generate(['bizName' => 'T', 'userRequirements' => 'x']);
    ok(($r['success'] ?? true) === false && !empty($r['error']), 'unconfigured lanes fail honestly');
} else { ok(true, 'live keys present — network lane skipped (manual QA)'); }

// cleanup
foreach (glob($dir . '/*') ?: [] as $ff) @unlink($ff);
@rmdir($dir);
echo $fail === 0 ? "\nALL ADMIN-DYNAMIC TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
