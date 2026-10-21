<?php
// tests/test-publish-live-e2e.php
// Real publish flow end-to-end: order -> publish -> admin login ->
// admin adds a trip -> the published public page lists it live.
// Cleans up every artefact it creates. Run: php tests/test-publish-live-e2e.php
$base = 'http://localhost/project/webbbuilder';
$root = 'C:/UniServerZ/www/project/webbbuilder';
$pass = 0; $fail = 0;
function check($c, $m) { global $pass, $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function req($url, $post = null, $opts = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => $opts['headers'] ?? ['Content-Type: application/json']]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? json_encode($post) : $post); }
    if (!empty($opts['jar'])) { curl_setopt($ch, CURLOPT_COOKIEJAR, $opts['jar']); curl_setopt($ch, CURLOPT_COOKIEFILE, $opts['jar']); }
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string)$body];
}

$adminUser = 'city_test_admin';
$adminPass = 'TestPass123!';
$previewToken = '0c5fb868c5a77b3f74205462029907d6';
echo "=== PUBLISH E2E ===\n";

// 1) design html = preview page minus the runtime (publish injects its own).
$previewHtml = (string)@file_get_contents("$root/preview/$previewToken/index.html");
$design = preg_replace('#<script[^>]*data-wc-biz-runtime[^>]*>.*?</script>#is', '', $previewHtml);
$design = preg_replace('#<script[^>]*id="wc-biz-spec"[^>]*>.*?</script>#is', '', $design);
check(strlen($design) > 10000, "design html prepared (" . strlen($design) . " bytes)");

// 2) real order
$tripFields = ['route:text', 'date:date', 'departure:text', 'price:number', 'total_seats:number'];
$bizSpec = ['schema' => 'biz-spec/1', 'gen_mode' => 'database',
    'profile' => ['business_name' => 'City Express Publish Probe', 'domain' => 'booking', 'business_model' => 'booking'],
    'entities' => [
        ['key' => 'trips', 'name' => 'trips', 'label' => 'Trips', 'fields' => $tripFields],
        ['key' => 'bookings', 'name' => 'bookings', 'label' => 'Bookings', 'fields' => ['trip_id:text', 'seats:json', 'passenger:text', 'phone:text', 'status:select:held|awaiting_payment|confirmed|cancelled|expired', 'payment_status:select:unpaid|paid|refunded', 'ref:text']],
    ]];
$order = ['action' => 'create_order', 'siteName' => 'City Express Publish Probe', 'genMode' => 'database',
    'adminUsername' => $adminUser, 'adminPassword' => $adminPass, 'adminEmail' => 'probe@example.com',
    'contactEmail' => 'probe@example.com', 'html' => $design, 'bizSpec' => $bizSpec];
[$code, $body] = req("$base/publish.php", $order);
$res = json_decode($body, true);
check($code === 200 && !empty($res['success']), "create_order succeeded (HTTP $code)");
$oid = (string)($res['order_id'] ?? '');
$slug = (string)($res['slug'] ?? '');
echo "order=$oid slug=$slug\n";

// 3) publish
[$code, $body] = req("$base/publish.php?action=do_publish&order_id=" . urlencode($oid) . "&draft_id=" . urlencode($previewToken));
check($code === 200, "do_publish HTTP $code");
$pubDir = "$root/published/$slug";
check(is_file("$pubDir/index.html"), "published index.html exists (the live page is on disk)");
check(is_file("$pubDir/admin/login.php"), "admin login page installed");
check(is_file("$pubDir/admin/trips.php"), "admin trips module installed");
check(is_file("$pubDir/admin/api.php"), "admin api installed");
check(is_file("$pubDir/api/business-ops.php"), "site api bridge written");
check(is_file("$pubDir/customer.php"), "customer portal shipped");
$auth = json_decode((string)@file_get_contents("$pubDir/admin/.auth.json"), true);
$authUser = is_array($auth) ? (string)($auth['username'] ?? '') : '';
check($authUser === $adminUser, "admin credentials installed ($authUser)");

$pubHtml = (string)@file_get_contents("$pubDir/index.html");
check(strpos($pubHtml, 'var SLUG=("' . $slug . '")') !== false, "published runtime bound to the PUBLISHED slug");
check(strpos($pubHtml, 'data-wc-list') !== false, "published page ships the live-list runtime");
check(stripos($pubHtml, 'admin confirms live trips after publish') === false, "published page has no demo-schedule note");
check(stripos($pubHtml, 'DEMO_TRIPS') === false, "published page has no hardcoded demo trips");

// 4) the published page is actually served
[$code, $body] = req("$base/published/$slug/");
check($code === 200 && strpos($body, '</html>') !== false, "published URL serves the page (HTTP $code, " . strlen($body) . " bytes)");

// 5) admin login (session) + add a trip
$jar = tempnam(sys_get_temp_dir(), 'wbjar');
req("$base/published/$slug/admin/login.php");                       // session cookie
[$code, $body] = req("$base/published/$slug/admin/login.php", 'username=' . urlencode($adminUser) . '&password=' . urlencode($adminPass),
    ['jar' => $jar, 'headers' => ['Content-Type: application/x-www-form-urlencoded']]);
check($code === 200 && strpos($body, 'Dashboard') !== false, "admin login OK (session established)");
[$code, $adminPage] = req("$base/published/$slug/admin/trips.php", null, ['jar' => $jar, 'headers' => []]);
$csrf = '';
if (preg_match('/name="csrf"\s+value="([^"]+)"/', $adminPage, $m)) $csrf = $m[1];
check($csrf !== '', "admin CSRF token found (" . ($csrf !== '' ? 'yes' : 'no') . ")");
$trip = ['route' => 'Chennai -> Coimbatore', 'date' => date('Y-m-d', strtotime('+2 days')), 'departure' => '22:30', 'price' => '799', 'total_seats' => '41'];
$saveBody = http_build_query(['action' => 'save', 'entity' => 'trips', 'csrf' => $csrf] + $trip);
[$code, $body] = req("$base/published/$slug/admin/api.php?action=save&entity=trips", $saveBody,
    ['jar' => $jar, 'headers' => ['Content-Type: application/x-www-form-urlencoded', 'X-Admin-CSRF: ' . $csrf]]);
$saveRes = json_decode($body, true);
check($code === 200 && !empty($saveRes['success']), "admin saved a trip through admin/api.php (HTTP $code: " . substr((string)$body, 0, 100) . ")");

// 6) the published public site lists it live
[$code, $body] = req("$base/api/business-ops.php", json_encode(['action' => 'list', 'entity' => 'trips', 'slug' => $slug]));
$rows = (json_decode($body, true)['data'] ?? []);
$found = null;
foreach ($rows as $r) { if (($r['route'] ?? '') === 'Chennai -> Coimbatore') { $found = $r; break; } }
check($found !== null, "published public list shows the admin's new trip (" . count($rows) . " row(s))");
check($found !== null && (string)($found['price'] ?? '') === '799', "price from the admin row is live");
[$code, $body] = req("http://localhost:3100/node-api/$slug/trips");
$nodeFound = false;
foreach ((json_decode($body, true)['data'] ?? []) as $r) { if (($r['route'] ?? '') === 'Chennai -> Coimbatore') { $nodeFound = true; break; } }
check($nodeFound, "node public catalog shows the same trip");
check(count($rows) >= 2, "preview trips migrated into the published site (" . count($rows) . " total)");

// 7) the published page renders the live row in the browser-facing HTML
check(strpos((string)@file_get_contents("$pubDir/index.html"), 'data-wc-list="trips"') !== false || strpos($pubHtml, 'data-wc-list') !== false,
    "published page has the live list container");

// cleanup
foreach (["$root/published/$slug", "$root/storage/sites/$slug", "$root/storage/collections/$slug", "$root/storage/orders/$oid", "$root/storage/designs/$oid.html"] as $p) {
    if (is_dir($p)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); } @rmdir($p); }
    elseif (is_file($p)) @unlink($p);
}
@unlink($jar);
echo "\ncleaned up\n=== SUMMARY: pass=$pass fail=$fail ===\n";
exit($fail === 0 ? 0 : 1);
