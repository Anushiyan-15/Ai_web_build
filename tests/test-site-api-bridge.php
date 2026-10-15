<?php
// tests/test-site-api-bridge.php — run: php tests/test-site-api-bridge.php
// Proves the published-site backend is REACHABLE (not "temporarily
// unavailable"): writeSiteApiBridge() → HTTP POST to
// published/<slug>/api/business-ops.php → real seeded trips come back.
// Plus: bare-# anti-jump guards in runtime + both preview bundles.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/SiteApiBridge.php';
require_once __DIR__ . '/../includes/BusinessOps.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
function rmdir_r($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = $d . '/' . $f;
        is_dir($p) ? rmdir_r($p) : @unlink($p); }
    @rmdir($d);
}

$slug = 'bridgetest';
$target = dirname(__DIR__) . '/published/' . $slug;
rmdir_r($target);
@mkdir($target, 0755, true);

// 1. Bridge writer creates the per-site endpoint.
ok(writeSiteApiBridge($target), 'bridge written');
$bf = $target . '/api/business-ops.php';
ok(is_file($bf) && strpos((string)@file_get_contents($bf), "require dirname(__DIR__, 3) . '/api/business-ops.php'") !== false,
    'bridge forwards to root endpoint');

// 2. Seed one trip via the real backend (admin path).
$_SESSION['admin_auth'] = true;
$r = bops_dispatch(['action' => 'create', 'slug' => $slug, 'entity' => 'trips',
    'record' => ['route' => 'Chennai-Bangalore', 'date' => '2026-12-25', 'departure' => '21:30', 'price' => 850, 'total_seats' => 40]]);
ok(($r['http'] ?? 0) === 200, 'trip seeded');
$_SESSION = [];

// 3. Reach it over HTTP exactly like storefront JS does (relative api/ URL).
$base = 'http://localhost/project/webbbuilder/published/' . $slug . '/api/business-ops.php';
$ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/json',
    'content' => json_encode(['action' => 'list', 'slug' => $slug, 'entity' => 'trips']), 'timeout' => 20]]);
$raw = @file_get_contents($base, false, $ctx);
$j = is_string($raw) ? json_decode($raw, true) : null;
ok(is_array($j) && ($j['success'] ?? false) && count($j['data'] ?? []) === 1
    && (string)($j['data'][0]['route'] ?? '') === 'Chennai-Bangalore',
    'bridge serves real trips over HTTP (backend reachable)');
rmdir_r($target);
rmdir_r(bops_root() . '/collections/' . $slug);
rmdir_r(bops_root() . '/sites/' . $slug);

// 4. Bare-# anti-jump guards (select no longer scrolls to top).
$rt = (string)@file_get_contents(__DIR__ . '/../includes/BusinessRuntime.php');
ok(strpos($rt, 'a[href="#"]') !== false, 'runtime swallows bare-# clicks');
$bld = (string)@file_get_contents(__DIR__ . '/../builder.php');
ok(strpos($bld, 'href0==="#"') !== false, 'workspace bundle swallows bare-# clicks');
$var = (string)@file_get_contents(__DIR__ . '/../variations.php');
ok(strpos($var, 'href0==="#"') !== false, 'variations bundle swallows bare-# clicks');
$svc = (string)@file_get_contents(__DIR__ . '/../includes/OpenCodeService.php');
ok(strpos($svc, 'never <a href=') !== false, 'contract bans bare-# controls');

echo $fail === 0 ? "\nALL SITE-API-BRIDGE TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
