<?php
// ═══════════════════════════════════════════════════════════════
//  includes/OpenRouterService.php — OpenRouter primary AI provider.
//
//  Dynamic free-model discovery (official catalogue
//  https://openrouter.ai/api/v1/models) → eligibility + capability
//  ranking → live inference test → cached selection.
//
//  Key hygiene: read ONLY from server env OPENROUTER_API_KEY (or the
//  git-ignored config/app.php constant). The key is NEVER returned by
//  any function here, never logged, never sent to the browser.
// ═══════════════════════════════════════════════════════════════

if (!function_exists('openrouter_api_key')) {

function openrouter_api_key(): string {
    $k = getenv('OPENROUTER_API_KEY');
    if (!is_string($k) || trim($k) === '') {
        $k = defined('OPENROUTER_API_KEY') ? (string)OPENROUTER_API_KEY : '';
    }
    $k = trim((string)$k);
    if ($k === '' || stripos($k, 'YOUR_') !== false) return '';
    return $k;
}

function openrouter_is_configured(): bool {
    return strlen(openrouter_api_key()) > 10;
}

function openrouter_cache_dir(): string {
    $d = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    return $d;
}
function openrouter_models_cache_file(): string {
    return openrouter_cache_dir() . '/openrouter_models.json';
}
function openrouter_selected_file(): string {
    return openrouter_cache_dir() . '/openrouter_selected.json';
}
function openrouter_cache_ttl(): int {
    return defined('OPENROUTER_CACHE_TTL') ? (int)OPENROUTER_CACHE_TTL : 21600;
}

// ── Catalogue fetch (server-side, Bearer — never browser-visible) ──
// Returns [ok(bool), models(array)|error(string)].
function openrouter_fetch_models(bool $force = false): array {
    if (!openrouter_is_configured()) return [false, 'OpenRouter API key not configured'];
    $cf = openrouter_models_cache_file();
    if (!$force && is_file($cf) && (time() - (int)@filemtime($cf)) < openrouter_cache_ttl()) {
        $j = json_decode((string)@file_get_contents($cf), true);
        if (is_array($j) && !empty($j['data']) && is_array($j['data'])) {
            return [true, $j['data']];
        }
    }
    $ep = defined('OPENROUTER_MODELS_ENDPOINT') ? OPENROUTER_MODELS_ENDPOINT : 'https://openrouter.ai/api/v1/models';
    $ch = curl_init($ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . openrouter_api_key()],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($curlErr !== '') {
        error_log('[OpenRouter] models TRANSPORT err=' . substr($curlErr, 0, 160));
        // Stale cache is better than nothing.
        if (is_file($cf)) {
            $j = json_decode((string)@file_get_contents($cf), true);
            if (is_array($j) && !empty($j['data'])) {
                return [true, array_merge($j['data'], [])];
            }
        }
        return [false, 'Network error: ' . $curlErr];
    }
    $j = json_decode((string)$res, true);
    if ($code === 401) return [false, 'Invalid OpenRouter API key (HTTP 401)'];
    if ($code === 429) return [false, 'OpenRouter rate-limited (HTTP 429)'];
    if ($code !== 200 || !is_array($j) || empty($j['data']) || !is_array($j['data'])) {
        error_log('[OpenRouter] models HTTP ' . $code . ' body=' . substr((string)$res, 0, 200));
        return [false, 'Model catalogue unavailable (HTTP ' . $code . ')'];
    }
    @file_put_contents($cf, json_encode(['at' => date('c'), 'data' => $j['data']], JSON_UNESCAPED_UNICODE), LOCK_EX);
    return [true, $j['data']];
}

// Price of a model entry as floats (missing pricing = NOT free).
function openrouter_price(array $m): array {
    $p = (is_array($m['pricing'] ?? null)) ? $m['pricing'] : [];
    return ['prompt' => (float)($p['prompt'] ?? 1), 'completion' => (float)($p['completion'] ?? 1)];
}
function openrouter_is_free(array $m): bool {
    $pr = openrouter_price($m);
    return $pr['prompt'] == 0.0 && $pr['completion'] == 0.0;
}

// ── Pure eligibility + capability ranking (no network — unit-testable) ──
// $task: 'chat' | 'json' | 'code'. Returns ordered candidate IDs.
function openrouter_pick_free(array $models, string $task = 'chat', int $max = 5): array {
    $task = strtolower($task);
    if (!in_array($task, ['chat', 'json', 'code'], true)) $task = 'chat';
    $cands = [];
    foreach ($models as $m) {
        if (!is_array($m)) continue;
        $id = (string)($m['id'] ?? '');
        if ($id === '') continue;
        if (!openrouter_is_free($m)) continue;
        $cands[] = ['id' => $id, 'm' => $m];
    }
    if (empty($cands)) return [];
    $score = function (array $c) use ($task): int {
        $id = strtolower($c['id']);
        $m = $c['m'];
        $s = 0;
        // Prefer instruct/chat-tuned + non-experimental builds.
        if (strpos($id, 'instruct') !== false) $s += 3;
        if (strpos($id, 'free') !== false) $s += 2;
        if (preg_match('/(experimental|beta|preview|0613|0301)/', $id)) $s -= 2;
        // Task capability hints from id / metadata (best-effort only —
        // the live inference test below is the real capability check).
        if ($task === 'json') {
            if (strpos($id, 'json') !== false) $s += 4;
            $outs = $m['supported_parameters'] ?? ($m['supported_params'] ?? []);
            if (is_array($outs) && in_array('response_format', array_map('strtolower', array_map('strval', $outs)), true)) $s += 4;
            if (strpos($id, 'llama') !== false || strpos($id, 'qwen') !== false || strpos($id, 'mistral') !== false) $s += 1;
        }
        if ($task === 'code') {
            if (preg_match('/(coder|codestral|code-|deepseek|qwen.*coder)/', $id)) $s += 4;
            $ctx = (int)($m['context_length'] ?? $m['top_provider']['context_length'] ?? 0);
            if ($ctx >= 32000) $s += 2;
        }
        // Larger context preferred for generation tasks.
        $ctx = (int)($m['context_length'] ?? 0);
        if ($ctx >= 64000) $s += 1;
        return $s;
    };
    usort($cands, fn($a, $b) => $score($b) <=> $score($a));
    $out = [];
    foreach ($cands as $c) {
        if (!in_array($c['id'], $out, true)) $out[] = $c['id'];
        if (count($out) >= $max) break;
    }
    return $out;
}

// ── Live inference test: small safe prompt, validated parse ──
// Returns [ok(bool), text|error, usage[]]. A catalogue listing alone
// NEVER counts as usable — only a validated response does.
function openrouter_test_model(string $model): array {
    // Safe minimal prompt (no user data, no secrets).
    return openrouter_chat_once($model, [
        ['role' => 'user', 'content' => 'Reply with exactly this word: OK'],
    ], 0.0, 16, defined('OPENROUTER_TEST_TIMEOUT') ? (int)OPENROUTER_TEST_TIMEOUT : 25);
}

// Validate a test reply counts as a working model (pure — testable).
function openrouter_validate_test(string $text): bool {
    return trim($text) !== '';
}

// ── Single chat/completions call (OpenAI-compatible) ──
// Returns [ok(bool), text(string), err(string|null), usage[]].
function openrouter_chat_once(string $model, array $messages, float $temperature = 0.7, int $maxTokens = 4000, ?int $timeout = null): array {
    if (!openrouter_is_configured()) return [false, '', 'OpenRouter API key not configured', ['prompt' => 0, 'completion' => 0]];
    $ep = defined('OPENROUTER_CHAT_ENDPOINT') ? OPENROUTER_CHAT_ENDPOINT : 'https://openrouter.ai/api/v1/chat/completions';
    $ch = curl_init($ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => array_values($messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . openrouter_api_key(),
            'HTTP-Referer: ' . (defined('SITE_URL') ? SITE_URL : ''),
            'X-Title: WebCraft AI',
        ],
        CURLOPT_TIMEOUT => $timeout ?? 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    $usage = ['prompt' => 0, 'completion' => 0];
    if ($curlErr !== '') {
        error_log('[OpenRouter] chat TRANSPORT model=' . $model . ' err=' . substr($curlErr, 0, 160));
        return [false, '', 'Network error: ' . $curlErr, $usage];
    }
    $j = json_decode((string)$res, true);
    if (is_array($j['usage'] ?? null)) {
        $usage = ['prompt' => (int)($j['usage']['prompt_tokens'] ?? 0),
                  'completion' => (int)($j['usage']['completion_tokens'] ?? 0)];
    }
    $text = '';
    if (is_array($j['choices'] ?? null) && isset($j['choices'][0]['message']['content'])) {
        $c = $j['choices'][0]['message']['content'];
        $text = is_string($c) ? $c : json_encode($c);
    }
    if ($code === 200 && openrouter_validate_test((string)$text)) {
        openrouter_record_success($model);
        return [true, (string)$text, null, $usage];
    }
    // HTTP 200 with an empty body is a known free-model behaviour (overloaded
    // / truncated) — report it clearly so the chain tries the next candidate.
    if ($code === 200 && trim((string)$text) === '') {
        error_log('[OpenRouter] chat model=' . $model . ' code=200 empty reply');
        return [false, '', $model . ': empty reply (HTTP 200)', $usage];
    }
    $err = '';
    if (is_array($j) && isset($j['error'])) $err = (string)(($j['error']['message'] ?? '') ?: json_encode($j['error']));
    if ($err === '') $err = 'HTTP ' . $code;
    if (in_array($code, [401, 403], true)) $err = 'AUTH: ' . $err;
    error_log('[OpenRouter] chat model=' . $model . ' code=' . $code . ' err=' . substr($err, 0, 200));
    return [false, '', $model . ': ' . $err, $usage];
}

function openrouter_record_success(string $model): void {
    @file_put_contents(openrouter_selected_file(),
        json_encode(['model' => $model, 'at' => date('c')], JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function openrouter_last_success(): ?array {
    $f = openrouter_selected_file();
    if (!is_file($f)) return null;
    $j = json_decode((string)@file_get_contents($f), true);
    return (is_array($j) && !empty($j['model'])) ? $j : null;
}

// ── Discovery → rank → test → select (cached; refreshes on failure) ──
// Returns [model(string)|'', error(string|null)]. Never throws.
function openrouter_select(string $task = 'chat', bool $refresh = false): array {
    if (!openrouter_is_configured()) return ['', 'OpenRouter API key not configured'];
    [$ok, $modelsOrErr] = openrouter_fetch_models($refresh);
    if (!$ok) return ['', (string)$modelsOrErr];
    $cands = openrouter_pick_free($modelsOrErr, $task, 4);
    if (empty($cands)) return ['', 'No free models in the current catalogue'];
    // Prefer the last proven model if still eligible (avoids re-testing).
    $last = openrouter_last_success();
    if ($last && !$refresh && in_array($last['model'], $cands, true)) {
        array_unshift($cands, $last['model']);
        $cands = array_values(array_unique($cands));
    }
    $errs = [];
    foreach (array_slice($cands, 0, 3) as $id) {
        [$tok, $txt] = openrouter_test_model($id);
        if ($tok) { openrouter_record_success($id); return [$id, null]; }
        $errs[] = $id . ' (' . substr((string)$txt, 0, 80) . ')';
        if (stripos((string)$txt, 'AUTH') !== false || stripos((string)$txt, '401') !== false) {
            break; // key problem — other candidates will fail the same way.
        }
    }
    return ['', 'Free-model tests failed: ' . implode('; ', $errs)];
}

// ── Safe status snapshot (NO keys, NO headers — frontend-safe) ──
function openrouter_status(): array {
    $out = ['configured' => openrouter_is_configured(), 'ok' => false,
        'model' => '', 'last_test' => null, 'catalogue_cached' => false, 'error' => null];
    if (!$out['configured']) { $out['error'] = 'Set the OPENROUTER_API_KEY server environment variable'; return $out; }
    $last = openrouter_last_success();
    if ($last) { $out['model'] = (string)$last['model']; $out['last_test'] = (string)($last['at'] ?? ''); }
    $cf = openrouter_models_cache_file();
    if (is_file($cf)) {
        $j = json_decode((string)@file_get_contents($cf), true);
        $n = is_array($j['data'] ?? null) ? count($j['data']) : 0;
        $out['catalogue_cached'] = $n > 0;
        if ($n > 0) $out['catalogue_models'] = $n;
    }
    $out['ok'] = $out['model'] !== '';
    return $out;
}

}
