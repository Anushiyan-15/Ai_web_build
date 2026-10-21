<?php
// tests/test-ai-abort-message.php
// Regression: raw DOMException "signal is aborted without reason" must never
// reach the user as "Templates (AI failed: signal is aborted without reason)".
// Run: php tests/test-ai-abort-message.php
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

$router = (string)@file_get_contents(__DIR__ . '/../assets/js/generation-router.js');
$svc = (string)@file_get_contents(__DIR__ . '/../assets/js/opencode-service.js');
$suite = (string)@file_get_contents(__DIR__ . '/../assets/js/wc-ai-suite.js');
$builder = (string)@file_get_contents(__DIR__ . '/../builder.php');

// 1. No bare ctrl.abort() on AI timeout paths (bare abort = cryptic DOMException).
foreach (['router' => $router, 'opencode-service' => $svc, 'wc-ai-suite' => $suite] as $name => $src) {
    $bare = preg_match('/setTimeout\([^)]*ctrl\.abort\(\)/', $src);
    ok($bare === 0, "$name: no bare ctrl.abort() without reason");
}
// 2. Every AI post() normalizes AbortError/aborted signals to actionable text.
ok(strpos($router, 'AbortError') !== false && strpos($router, 'timed out after') !== false, 'router: AbortError normalized with timeout context');
ok(strpos($svc, 'AbortError') !== false && strpos($svc, 'timed out after') !== false, 'opencode-service: AbortError normalized with timeout context');
ok(strpos($suite, 'AbortError') !== false, 'wc-ai-suite: AbortError normalized');
// 3. Builder sanitizes AI errors before flashing "Templates (AI failed: …)".
ok(strpos($builder, 'function friendlyAiErr') !== false, 'builder: friendlyAiErr helper present');
ok(strpos($builder, 'aborted without reason') !== false && strpos($builder, 'AI timed out') !== false, 'builder: abort pattern mapped to friendly text');
ok(strpos($builder, 'aiErr = friendlyAiErr') !== false && strpos($builder, 'subAiErr = friendlyAiErr') !== false, 'builder: both aiErr paths sanitized');
// 4. No client-side time limit on AI routing/generation (timeoutMs = 0).
ok(strpos($router, "action: 'assess'") !== false && substr_count($router, '}, 0)') >= 2, 'router: decide/assess have no time limit');
ok(strpos($svc, 'timeoutMs = (typeof timeoutMs === \'number\' && timeoutMs > 0) ? timeoutMs : 0;') !== false, 'opencode-service: post() has no default time limit');
// 5. No lane race deadline: slots wait for the AI, never get cut at 165s.
ok(strpos($svc, '165000') === false && strpos($svc, 'Promise.race') === false, 'opencode-service: no lane race deadline');
// 6. Route endpoint has no PHP execution ceiling.
$route = (string)@file_get_contents(__DIR__ . '/../api/generate-route.php');
ok(strpos($route, 'set_time_limit(0)') !== false, 'api/generate-route.php: no execution ceiling');

echo $fail === 0 ? "\nALL AI-ABORT-MESSAGE TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
