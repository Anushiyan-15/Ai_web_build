<?php
// tests/test-ai-providers.php — run: php tests/test-ai-providers.php
// OpenRouter discovery/selection + required fallback order
// (OpenRouter free → Gemini → templates), status hygiene (no secrets),
// and Studio/builder publish-linkage wiring. No network required except
// the honest live-probe section, which reports SKIP when unreachable.
require_once __DIR__ . '/../includes/OpenRouterService.php';
require_once __DIR__ . '/../includes/AiProviderChain.php';
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

// ── Fixture catalogue (mirrors OpenRouter /models shape) ──
$models = [
    ['id' => 'big-corp/gpt-paid', 'pricing' => ['prompt' => '0.001', 'completion' => '0.002'], 'context_length' => 128000],
    ['id' => 'free-lab/small-chat:free', 'pricing' => ['prompt' => '0', 'completion' => '0'], 'context_length' => 32000],
    ['id' => 'free-lab/json-pro:free', 'pricing' => ['prompt' => '0', 'completion' => '0'],
     'context_length' => 64000, 'supported_parameters' => ['temperature', 'response_format']],
    ['id' => 'free-lab/code-max:free', 'pricing' => ['prompt' => '0', 'completion' => '0'], 'context_length' => 128000],
    ['id' => 'mystery/no-pricing', 'context_length' => 8000],
    ['id' => 'free-lab/old-beta:free', 'pricing' => ['prompt' => '0', 'completion' => '0'], 'context_length' => 16000],
];

// 1. Eligibility: only catalogue-verified free models, never paid/unknown.
$chat = openrouter_pick_free($models, 'chat', 10);
ok(!in_array('big-corp/gpt-paid', $chat, true), 'paid model excluded');
ok(!in_array('mystery/no-pricing', $chat, true), 'missing-pricing model excluded (not assumed free)');
ok(count($chat) === 4 && in_array('free-lab/small-chat:free', $chat, true), 'all free models eligible');

// 2. Task capability ranking.
$json = openrouter_pick_free($models, 'json', 10);
ok(($json[0] ?? '') === 'free-lab/json-pro:free', 'json task prefers response_format-capable model');
$code = openrouter_pick_free($models, 'code', 10);
ok(($code[0] ?? '') === 'free-lab/code-max:free', 'code task prefers coder + large-context model');

// 3. Empty catalogue → no candidates (never invent one).
ok(openrouter_pick_free([], 'chat') === [], 'empty catalogue yields no candidates');
ok(openrouter_pick_free([['id' => 'x/paid', 'pricing' => ['prompt' => '1', 'completion' => '1']]], 'chat') === [], 'all-paid catalogue yields none');

// 4. Test-response validation.
ok(openrouter_validate_test('OK') && openrouter_validate_test("  {\"a\":1}  "), 'non-empty replies validate');
ok(!openrouter_validate_test('') && !openrouter_validate_test("   \n "), 'empty replies never count as usable');

// 5. Chain order with stubbed deps (no network).
$stubGeminiOk = ['gemini' => fn($s, $u, $t, $m, $to) => [true, 'GEMINI-ANS', null, []]];
$stubGeminiFail = ['gemini' => fn($s, $u, $t, $m, $to) => [false, '', 'overloaded', []]];
// 5a. OpenRouter unconfigured → Gemini.
$r = ai_chat('sys', 'hi', [], ['or_configured' => fn() => false] + $stubGeminiOk);
ok($r['ok'] && $r['provider'] === 'gemini' && $r['text'] === 'GEMINI-ANS', 'OR unconfigured → Gemini secondary');
// 5b. First free candidate fails → second tried (no immediate provider switch).
$calls = [];
$r = ai_chat('sys', 'hi', ['task' => 'json'], [
    'or_configured' => fn() => true,
    'or_fetch' => fn($f) => [true, $models],
    'or_pick' => fn($m, $t) => ['free-lab/json-pro:free', 'free-lab/small-chat:free'],
    'or_chat' => function ($id, $msgs, $t, $m, $to) use (&$calls) {
        $calls[] = $id;
        return $id === 'free-lab/json-pro:free'
            ? [false, '', 'rate-limited', []]
            : [true, '{"category":"bus"}', null, []];
    }] + $stubGeminiFail);
ok($r['ok'] && $r['provider'] === 'openrouter' && $r['model'] === 'free-lab/small-chat:free'
    && $calls === ['free-lab/json-pro:free', 'free-lab/small-chat:free'], 'failed candidate → next free model retried');
// 5c. Auth failure stops OpenRouter retries, moves to Gemini.
$r = ai_chat('sys', 'hi', [], [
    'or_configured' => fn() => true,
    'or_fetch' => fn($f) => [true, $models],
    'or_pick' => fn($m, $t) => ['a:free', 'b:free'],
    'or_chat' => fn($id, $m, $t, $x, $to) => [false, '', 'AUTH invalid key', []]] + $stubGeminiOk);
$att = implode(',', $r['attempts']);
ok($r['ok'] && $r['provider'] === 'gemini' && substr_count($att, 'openrouter') === 1, 'auth failure → Gemini (no futile retries)');
// 5d. Everything down → honest template fallback, never fake success.
$r = ai_chat('sys', 'hi', [], [
    'or_configured' => fn() => true,
    'or_fetch' => fn($f) => [false, 'down'],
    'or_pick' => fn($m, $t) => [],
    'or_chat' => fn($id, $m, $t, $x, $to) => [false, '', 'x', []]] + $stubGeminiFail);
ok(!$r['ok'] && $r['provider'] === 'template' && $r['text'] === '' && stripos($r['error'], 'template') !== false,
    'total failure → template fallback reported, nothing faked');

// 6. Status endpoint: shape + secret hygiene (executed in-process, GET mode).
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
ob_start();
include __DIR__ . '/../api/ai-status.php';
$out = (string)ob_get_clean();
$j = json_decode($out, true);
ok(is_array($j) && ($j['success'] ?? false) && ($j['order'] ?? []) === ['openrouter', 'gemini', 'template'],
    'ai-status returns provider order');
ok(strpos($out, 'sk-or') === false && strpos($out, 'Bearer') === false && strpos($out, 'Authorization') === false,
    'ai-status leaks no key material');
ok(array_key_exists('configured', $j['openrouter'] ?? []) && array_key_exists('configured', $j['gemini'] ?? []),
    'ai-status reports configured flags');

// 7. Publish-linkage wiring present (builder → publish → Studio).
$builder = (string)@file_get_contents(__DIR__ . '/../builder.php');
$publish = (string)@file_get_contents(__DIR__ . '/../publish.php');
$studio = (string)@file_get_contents(__DIR__ . '/../studio.php');
ok(strpos($builder, 'keepPublished') !== false && strpos($builder, 'getPublishedSession') !== false,
    'builder preserves + resolves published session');
ok(strpos($publish, 'rememberPublished') !== false && strpos($publish, 'webcraft_published::') !== false,
    'publish records slug/adminUrl into sessions');
ok(strpos($studio, 'studioPublishedAdmin') !== false && strpos($studio, 'studioLiveAdminDoc') !== false
    && strpos($studio, 'wc-live-admin') !== false, 'studio resolves live per-project admin (never persisted)');
ok(strpos($studio, "currentHtml.indexOf('wc-live-admin')") !== false, 'live-admin doc guarded from save-back');

// 8. Live probe (honest: SKIP when no key/network; never asserted as working).
// Mirrors production selection (cached winner preferred, up to 3 tested).
if (openrouter_is_configured()) {
    [$selModel, $selErr] = openrouter_select('chat', false);
    if ($selModel !== '') {
        echo "PASS: live OpenRouter selection verified ($selModel)\n";
    } else {
        echo "FAIL: live OpenRouter selection failed ($selErr)\n";
        $fail++;
    }
} else echo "SKIP: no OPENROUTER_API_KEY configured — live probe not attempted\n";

echo $fail === 0 ? "\nALL AI-PROVIDER TESTS PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
