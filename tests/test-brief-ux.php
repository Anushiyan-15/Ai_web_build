<?php
// tests/test-brief-ux.php — run: php tests/test-brief-ux.php
// Brief UX contract: ONE canonical brief box, Prompt Helper popup,
// full-wizard fill + admin analysis in Understand, cache-proof scripts.
$b = (string)@file_get_contents(__DIR__ . '/../builder.php');
$suite = (string)@file_get_contents(__DIR__ . '/../assets/js/wc-builder-suite.js');
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

// 1. Single canonical brief box.
ok(substr_count($b, 'id="biz_brief"') === 1, 'exactly one #biz_brief box');
ok(substr_count($b, 'id="btn-understand-biz"') === 1, 'exactly one Understand button');
ok(strpos($b, 'id="btn-brandkit-open"') !== false, 'Brand Kit button on native box');
// 2. Inline duplicate killer (works even with stale cached JS).
ok(strpos($b, "querySelectorAll('#wc-brief-launcher')") !== false, 'inline launcher killer present');
ok(strpos($b, 'MutationObserver') !== false, 'late-inject observer present');
// 3. Cache-busted suite scripts so guards actually load.
ok(strpos($b, 'wc-builder-suite.js?v=') !== false, 'cache-busted builder suite');
ok(strpos($suite, "if ($('biz_brief'))") !== false, 'inject guard on native box');
// 4. Prompt Helper popup.
foreach (['btn-prompt-helper', 'promptgen-modal', 'promptgen-input', 'promptgen-output',
    'openPromptGenerator', 'closePromptGenerator', 'buildPromptFromWords',
    'makePromptFromWords', 'useGeneratedPrompt'] as $k) {
    ok(strpos($b, $k) !== false, "prompt helper has $k");
}
// 5. Full wizard fill + admin analysis in Understand.
foreach (['parseBriefToFields', "fillEmpty('biz_name'", "fillEmpty('biz_tagline'",
    "fillEmpty('biz_audience'", "fillEmpty('biz_services'", 'admin_requirements',
    'Admin: YES (', 'Admin: not needed'] as $k) {
    ok(strpos($b, $k) !== false, "understand covers $k");
}
// 6. Always-fill first + honest failure (never "stays as is").
foreach (['Wizard filled from your words', 'analysis retry', 'Step 0'] as $k) {
    ok(strpos($b, $k) !== false, "understand resilient: $k");
}
// 7. No duplicated declarations (a dup `let bundle` killed the whole
// script block once → every onclick went "not defined").
ok(substr_count($b, "let bundle = '';") === 1, 'single bundle declaration (script parses)');
// 8. Industry always updates on Understand (no stale-select guard).
ok(strpos($b, 'selectedIndex === 0') === false, 'industry always updates from analysis');
ok(strpos($b, 'Still needed:') !== false, 'missing-field guidance shown');
foreach (['Salon & Beauty', 'Hotel & Hospitality', 'Travel & Tours',
    'Events & Entertainment', 'Rental & Hire', 'Automotive & Repairs'] as $ind) {
    ok(strpos($b, $ind) !== false, "industry option: $ind");
}

echo $fail === 0 ? "\nALL BRIEF-UX TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
