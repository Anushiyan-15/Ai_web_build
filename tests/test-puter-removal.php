<?php
// tests/test-puter-removal.php — run: php tests/test-puter-removal.php
// Fails if any ACTIVE website-generation flow still calls Puter.
$fail = 0;
function ok($c,$m){global $fail;echo ($c?"PASS":"FAIL").": $m\n";if(!$c)$fail++;}
function f($p){return file_get_contents(__DIR__ . '/../' . $p);}

// 1. Dead Puter runtime files deleted
ok(!file_exists(__DIR__ . '/../assets/js/puter-service.js'), 'puter-service.js deleted');
ok(!file_exists(__DIR__ . '/../assets/js/puter-website-generator.js'), 'puter-website-generator.js deleted');
ok(!file_exists(__DIR__ . '/../test-loader.html'), 'puter-branded demo page deleted');

// 1b. No *puter* files anywhere in the shipped tree (excluding tests/.git)
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..', FilesystemIterator::SKIP_DOTS));
$puterFiles = [];
foreach ($rii as $fi) {
    $pn = $fi->getPathname();
    if (strpos($pn, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($pn, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($pn, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($pn, DIRECTORY_SEPARATOR . 'published' . DIRECTORY_SEPARATOR) !== false) continue;
    if (stripos($fi->getFilename(), 'puter') !== false) $puterFiles[] = $fi->getFilename();
}
ok(empty($puterFiles), 'no *puter* files in tree' . ($puterFiles ? ' (' . implode(',', $puterFiles) . ')' : ''));

// 2. No Puter SDK loads in active pages
foreach (['publish.php','site-manager.php','builder.php','studio.php','customer-portal.php','index.php'] as $pg) {
    $c = f($pg);
    ok(stripos($c, 'js.puter.com') === false, "$pg: no puter SDK");
    ok(stripos($c, 'puter-service.js') === false && stripos($c, 'puter-website-generator') === false, "$pg: no puter script includes");
}

// 3. No live Puter API calls in generation JS
foreach (['assets/js/ai-admin-generator.js','assets/js/ai-flowcraft.js','assets/js/opencode-service.js','assets/js/generation-router.js','assets/js/biz-intelligence.js'] as $js) {
    $c = f($js);
    ok(strpos($c, 'window.puter') === false, "$js: no window.puter calls");
    ok(strpos($c, 'puter.ai') === false, "$js: no puter.ai calls");
    ok(strpos($c, 'window.PuterService') === false, "$js: no PuterService calls");
}

// 4. Admin generation is AI-mix backed (Gemini primary, OpenCode fallback)
$adm = f('api/admin-generate.php');
$mix = f('includes/AdminAi.php');
ok(strpos($adm, 'gemini-3.5-flash-lite') !== false || strpos($mix, 'gemini-3.5-flash-lite') !== false, 'admin lane pins gemini-3.5-flash-lite');
ok(strpos($mix, 'opencode_chat_fallback') !== false && strpos($adm, 'admin_ai_generate') !== false, 'admin lane has OpenCode mix fallback');
ok(strpos($adm, 'window.puter') === false && strpos($adm, 'puter.ai') === false && strpos($adm, 'PuterService') === false, 'admin endpoint has no puter calls');
$gen = f('assets/js/ai-admin-generator.js');
ok(strpos($gen, 'api/admin-generate.php') !== false, 'admin generator calls Gemini backend');

// 5. No Puter provider routing left
$oc = f('api/opencode.php');
ok(stripos($oc, 'puter-then') === false, 'opencode fallback chain has no puter');
ok(stripos($oc, 'PuterService') === false, 'opencode.php has no PuterService');

// 6. Generation router policy intact (Gemini-first small, Bunny-first large, templates final)
$r = f('assets/js/generation-router.js');
ok(strpos($r, 'gemini-3.5-flash-lite') !== false, 'router still Gemini-pinned');
ok(strpos($r, 'space-bunny-free') !== false, 'router still Bunny-capable');

echo $fail===0 ? "\nALL PUTER-REMOVAL TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail===0?0:1);
