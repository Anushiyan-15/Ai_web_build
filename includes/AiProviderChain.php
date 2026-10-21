<?php
// ═══════════════════════════════════════════════════════════════
//  includes/AiProviderChain.php — Required fallback order:
//
//    Primary:   working OpenRouter free model (discovered + tested)
//    Secondary: Gemini (existing integration)
//    Final:     existing server templates (caller-side; never faked)
//
//  ai_chat() returns ['ok','provider','model','text','error','attempts'].
//  ok=false with provider=template means: every live provider failed —
//  the caller must use its template path and say so honestly.
//  Dependencies are injectable ($deps) so tests never touch the network.
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/OpenRouterService.php';

if (!function_exists('ai_chat')) {

function ai_default_deps(): array {
    return [
        'or_configured' => fn() => openrouter_is_configured(),
        'or_fetch' => fn(bool $force) => openrouter_fetch_models($force),
        'or_pick' => fn(array $models, string $task) => openrouter_pick_free($models, $task, 4),
        'or_chat' => fn(string $model, array $msgs, float $t, int $mt, int $to) => openrouter_chat_once($model, $msgs, $t, $mt, $to),
        'gemini' => function (string $system, string $user, float $t, int $mt, int $to): array {
            if (!function_exists('gemini_chat_once')) {
                @require_once __DIR__ . '/GeminiService.php';
            }
            if (!function_exists('gemini_chat_once')) return [false, '', 'Gemini service unavailable', ['prompt' => 0, 'completion' => 0]];
            return gemini_chat_once('gemini-3.5-flash-lite', $system, $user, $t, $mt, $to);
        },
    ];
}

function ai_chat(string $system, string $user, array $opts = [], array $deps = []): array {
    $d = array_merge(ai_default_deps(), $deps);
    $task = (string)($opts['task'] ?? 'chat');
    $temp = (float)($opts['temperature'] ?? 0.7);
    $maxT = (int)($opts['max_tokens'] ?? 4000);
    $to = (int)($opts['timeout'] ?? 60);
    $attempts = [];

    // ── 1. Primary: OpenRouter free models ──
    try {
        if (($d['or_configured'])()) {
            [$fok, $modelsOrErr] = ($d['or_fetch'])(false);
            if ($fok && is_array($modelsOrErr)) {
                $cands = array_slice(($d['or_pick'])($modelsOrErr, $task), 0, 2);
                foreach ($cands as $id) {
                    $msgs = [];
                    if (trim($system) !== '') $msgs[] = ['role' => 'system', 'content' => $system];
                    $msgs[] = ['role' => 'user', 'content' => $user];
                    [$ok, $text, $err] = ($d['or_chat'])($id, $msgs, $temp, $maxT, $to);
                    $attempts[] = 'openrouter:' . $id . ':' . ($ok ? 'ok' : 'fail');
                    if ($ok) return ['ok' => true, 'provider' => 'openrouter', 'model' => $id,
                        'text' => $text, 'error' => null, 'attempts' => $attempts];
                    // Auth/key failure hits every candidate equally — stop retrying.
                    if (stripos((string)$err, 'AUTH') !== false || stripos((string)$err, '401') !== false) break;
                }
                // One catalogue refresh + single retry when the cache looks stale.
                if (!empty($cands)) {
                    [$rok, $rm] = ($d['or_fetch'])(true);
                    if ($rok && is_array($rm)) {
                        $fresh = array_slice(($d['or_pick'])($rm, $task), 0, 1);
                        if (!empty($fresh) && !in_array($fresh[0], $cands, true)) {
                            $msgs = [];
                            if (trim($system) !== '') $msgs[] = ['role' => 'system', 'content' => $system];
                            $msgs[] = ['role' => 'user', 'content' => $user];
                            [$ok, $text, $err] = ($d['or_chat'])($fresh[0], $msgs, $temp, $maxT, $to);
                            $attempts[] = 'openrouter:' . $fresh[0] . ':refresh:' . ($ok ? 'ok' : 'fail');
                            if ($ok) return ['ok' => true, 'provider' => 'openrouter', 'model' => $fresh[0],
                                'text' => $text, 'error' => null, 'attempts' => $attempts];
                        }
                    }
                }
            } else {
                $attempts[] = 'openrouter:discovery:fail';
            }
        } else {
            $attempts[] = 'openrouter:unconfigured';
        }
    } catch (Throwable $e) {
        $attempts[] = 'openrouter:exception';
    }

    // ── 2. Secondary: Gemini ──
    try {
        $g = ($d['gemini'])($system, $user, $temp, $maxT, $to);
        [$gok, $gtext, $gerr] = [$g[0] ?? false, $g[1] ?? '', $g[2] ?? 'unknown'];
        $attempts[] = 'gemini:' . ($gok ? 'ok' : 'fail');
        if ($gok) return ['ok' => true, 'provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite',
            'text' => (string)$gtext, 'error' => null, 'attempts' => $attempts];
        $gemErr = (string)$gerr;
    } catch (Throwable $e) {
        $attempts[] = 'gemini:exception';
        $gemErr = 'exception';
    }

    // ── 3. Final: existing server templates (caller-side) ──
    $err = 'All AI providers unavailable [' . implode(', ', $attempts) . ']; use server templates'
        . (isset($gemErr) && $gemErr !== '' ? ' (last: ' . substr($gemErr, 0, 120) . ')' : '');
    return ['ok' => false, 'provider' => 'template', 'model' => '', 'text' => '',
        'error' => $err, 'attempts' => $attempts];
}

}
