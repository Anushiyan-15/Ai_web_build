<?php
// tests/test-live-list-runtime.php
// Regression: generated sites must show admin-managed rows (backend data),
// never hardcoded demo schedules. Run: php tests/test-live-list-runtime.php
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

$root = dirname(__DIR__);
$runtime = (string)@file_get_contents($root . '/includes/BusinessRuntime.php');
$prompt  = (string)@file_get_contents($root . '/includes/OpenCodeService.php');
$ops     = (string)@file_get_contents($root . '/includes/BusinessOps.php');

// 1) The runtime renders a live list from the backend.
ok(strpos($runtime, 'function renderLiveList') !== false, 'runtime: live list renderer present');
ok(strpos($runtime, "data-wc-list") !== false, 'runtime: data-wc-list container contract');
ok(strpos($runtime, 'function liveSelect') !== false, 'runtime: live row selection present');
ok(strpos($runtime, "wc:list-select") !== false, 'runtime: wc:list-select event for page-owned flow');
ok(strpos($runtime, 'data-wc-list-cta') !== false, 'runtime: select button on live cards');
ok(strpos($runtime, "catalogQuery(entity") !== false, 'runtime: live list reads the backend (catalogQuery)');

// 2) Hardcoded demo rows are removed from the page, not rendered.
ok(strpos($runtime, "data-demo-trip") !== false, 'runtime: fake demo rows are stripped');
ok(strpos($runtime, "Live schedule - updated from the admin panel") !== false, 'runtime: demo note reworded to live');

// 3) The generator prompt asks for a live container, not a demo schedule.
ok(stripos($prompt, '7) DEMO SCHEDULE') === false, 'prompt: no DEMO SCHEDULE contract');
ok(stripos($prompt, '7) LIVE SCHEDULE') !== false, 'prompt: LIVE SCHEDULE contract');
ok(strpos($prompt, 'data-wc-list=\\"trips\\"') !== false || strpos($prompt, 'data-wc-list=\"trips\"') !== false,
   'prompt: tells the model to emit a data-wc-list container');
ok(stripos($prompt, 'wc:list-select') !== false, 'prompt: model listens for wc:list-select');

// 4) The backend does not seed fake schedules any more (opt-in only).
ok(strpos($ops, 'WB_SEED_DEMO_TRIPS') !== false, 'backend: demo trip seeding is opt-in (WB_SEED_DEMO_TRIPS)');

// 5) The rendered runtime JS is syntactically valid (brace/paren balance).
require_once $root . '/config.php';
require_once $root . '/includes/BusinessRuntime.php';
$js = business_runtime_js(['category' => 'bus', 'entities' => [['key' => 'trips', 'name' => 'Trips']]], 'test-slug');
$js = preg_replace('#^<script[^>]*>#', '', (string)$js);
$js = preg_replace('#</script>\s*$#', '', (string)$js);
$opens = 0; $closes = 0;
for ($i = 0; $i < strlen($js); $i++) {
    $c = $js[$i];
    if ($c === '{') $opens++;
    if ($c === '}') $closes++;
}
ok($opens > 0 && $opens === $closes, 'runtime JS: braces balanced (' . $opens . '/' . $closes . ')');
ok(strpos($js, 'renderLiveList') !== false, 'runtime JS: live list code shipped to the page');

// 5b. A mobile menu must never render next to the desktop nav (the model
//     often styles .mobile-menu only inside a max-width media query, which
//     produced a duplicated nav row on desktop).
ok(strpos($runtime, 'function wcFixMobileMenus') !== false, 'runtime: mobile menu guard present');
ok(strpos($js, 'wcFixMobileMenus') !== false, 'runtime JS: mobile menu guard shipped to the page');
ok(strpos($js, "data-wc-mobile-menu-hidden") !== false, 'runtime JS: mobile menu is marked as hidden');

echo $fail === 0 ? "\nALL LIVE-LIST TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
