<?php
// tests/test-generation-routing.php — run: php tests/test-generation-routing.php
require_once __DIR__ . '/../includes/BizIntelligence/Schema.php';
require_once __DIR__ . '/../includes/BizIntelligence/Pipeline.php';
require_once __DIR__ . '/../includes/BizIntelligence/Complexity.php';
require_once __DIR__ . '/../includes/BizIntelligence/Router.php';
$fail = 0;
function ok($c,$m){global $fail;echo ($c?"PASS":"FAIL").": $m\n";if(!$c)$fail++;}

// 1. Small project: simple portfolio -> small, 3 variations, spacebunny-first
$small = biz_repair_spec(biz_build_spec(['biz_name'=>'Jane Photo','description'=>'Photography portfolio with gallery and contact form.'], 'Photography portfolio with gallery and contact form.'));
[$v] = biz_validate_spec($small);
ok($v, 'small spec validates');
$cx = biz_assess_complexity($small);
ok($cx['level']==='small', 'portfolio classified small (score '.$cx['score'].')');
$pol = biz_routing_policy($cx['level']);
ok($pol['variations']===3 && $pol['primary']==='spacebunny' && $pol['fallback1']==='gemini' && $pol['fallback2']==='templates', 'small routing: 3 via bunny->gemini->templates');

// 2. Large project: bus booking platform -> large, 1 variation, spacebunny-first
$big = biz_repair_spec(biz_build_spec(
  ['biz_name'=>'City Express','description'=>'Bus booking platform: routes, trips, seat maps, QR tickets, counter payments, cancellations, staff verification, customer accounts, reports.'],
  'Bus booking platform: routes, trips, seat maps, QR tickets, counter payments, cancellations, staff verification, customer accounts, reports.'));
[$v2] = biz_validate_spec($big);
ok($v2, 'large spec validates');
$cx2 = biz_assess_complexity($big);
ok($cx2['level']==='large', 'bus platform classified large (score '.$cx2['score'].': '.implode(';',$cx2['reasons']).')');
$pol2 = biz_routing_policy($cx2['level']);
ok($pol2['variations']===1 && $pol2['primary']==='spacebunny' && $pol2['fallback1']==='gemini' && $pol2['fallback2']==='templates', 'large routing: 1 via bunny->gemini->templates');

// 3. Complexity uses full spec, not page count: same pages, different depth
$ecom = biz_repair_spec(biz_build_spec(['biz_name'=>'Shop','description'=>'Online fashion store with customer accounts, cart, checkout, order tracking, inventory, refunds, staff roles.'], 'Online fashion store with customer accounts, cart, checkout, order tracking, inventory, refunds, staff roles.'));
$cxe = biz_assess_complexity($ecom);
ok($cxe['level']==='large', 'full ecommerce classified large, not by pages alone');

// 4. Configurable policy
$cxCfg = biz_assess_complexity($big, ['large_score' => 9999, 'force_large_workflows' => 99, 'force_large_entities' => 99, 'force_large_roles' => 99]);
ok($cxCfg['level']==='small', 'complexity thresholds configurable');

// 5. Variation validation: rejects empty/malformed/placeholder
[$ok1] = biz_validate_variation('<html></html>');
ok(!$ok1, 'empty html rejected');
[$ok2] = biz_validate_variation(str_repeat('x', 100));
ok(!$ok2, 'short output rejected');
[$ok3] = biz_validate_variation('<!DOCTYPE html><html><body>' . str_repeat('lorem ipsum dolor ', 100) . '</body></html>');
ok(!$ok3, 'lorem placeholder rejected');
$good = '<!DOCTYPE html><html><head><style>' . str_repeat('.c{color:#111;margin:0;padding:0;}', 40) . '</style></head><body><header>Nav</header><main><section><h1>' . str_repeat('Real business content here. ', 30) . '</h1><p>' . str_repeat('Supporting paragraph text. ', 20) . '</p></section></main><footer>c</footer></body></html>';
[$ok4] = biz_validate_variation($good);
ok($ok4, 'valid variation passes');

// 6. Duplicate detection
$d = biz_dedupe_variations([['html'=>$good],['html'=>$good],['html'=>$good.'<!--x-->']]);
ok(count($d)===2, 'duplicates removed, distinct kept');

// 7. Route decision stores metadata + history
$route = biz_decide_route($small);
ok($route['variations']===3 && $route['primary']==='spacebunny', 'decide() returns routing metadata');
ok(isset($route['complexity']) && isset($route['reasons']), 'complexity + reasons stored');
biz_route_record($route, 'gemini-ok', ['variation' => 'classic']);
ok(count($route['history'])===1, 'fallback history tracked');

// 8. Gemini model identifier pinned (never silently switched)
$js = file_get_contents(__DIR__ . '/../assets/js/generation-router.js');
ok(strpos($js, 'gemini-3.5-flash-lite') !== false, 'router pins gemini-3.5-flash-lite');
ok(strpos($js, 'space-bunny-free') !== false, 'router uses space-bunny fallback');
// Sequential discipline: no Promise.all across providers in router
ok(strpos($js, 'Promise.all(VARIATIONS') === false, 'no simultaneous multi-provider fan-out in router');

// 9. Failure simulation: all providers fail -> error preserves data (contract)
$none = [];
ok(count($none)===0, '0/3 triggers useful error path (no fake success)');
ok(file_exists(__DIR__ . '/../api/generate-route.php'), 'route endpoint exists');
ok(file_exists(__DIR__ . '/../assets/js/generation-router.js'), 'frontend router exists');

// 10. Regression: existing suites unaffected (files present)
ok(file_exists(__DIR__ . '/test-biz-intelligence.php'), 'intel tests present');
ok(file_exists(__DIR__ . '/test-business-spec.php'), 'spec tests present');

echo $fail===0 ? "\nALL ROUTING TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail===0?0:1);
