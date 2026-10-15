<?php
// includes/AdminAi.php — Full-AI admin code generation lane.
// BACKEND CHAIN (user order):
//   1) OpenRouter free working model (discovered + inference-tested)
//   2) OpenCode free lane, SpaceBunny-first
//   3) Gemini gemini-3.5-flash-lite fallback
//   4) Server-written backend (AdminPanelGenerator/AdminDashboardBuilder
//      at publish time — this function reports fallback=>'server' so the
//      UI publishes with the server panel instead of blocking).
// Same JSON contract throughout:
// {files{path:code}, sql, entities[], admin_title, setup_notes}.
// Never execs generated code; validated + repaired only.
require_once __DIR__ . '/GeminiService.php';
require_once __DIR__ . '/OpenCodeService.php';
require_once __DIR__ . '/OpenRouterService.php';

function admin_ai_prompt(array $o): string {
    $biz = substr(trim((string)($o['bizName'] ?? 'Business')), 0, 80);
    $type = substr(trim((string)($o['bizType'] ?? 'business')), 0, 80);
    $mode = (($o['genMode'] ?? 'admin') === 'database') ? 'database' : 'admin';
    $reqs = substr(trim((string)($o['userRequirements'] ?? '')), 0, 4000);
    $entLine = '';
    if (!empty($o['entities']) && is_array($o['entities'])) {
        $names = [];
        foreach (array_slice($o['entities'], 0, 8) as $e) {
            if (!is_array($e)) continue;
            $n = trim((string)($e['label'] ?? $e['name'] ?? $e['key'] ?? ''));
            if ($n !== '') $names[] = $n;
        }
        if (!empty($names)) {
            $entLine = 'Business entities (generate EXACTLY these modules, no others): '
                . implode(', ', array_unique($names)) . "\n";
        }
    }
    // Operating workflows + field detail: the AI must implement the real
    // business process, not just CRUD shells. Take the time to write every
    // file completely — truncated/TODO output is a failure, retry instead.
    $wfLine = '';
    if (!empty($o['workflows']) && is_array($o['workflows'])) {
        $parts = [];
        foreach (array_slice($o['workflows'], 0, 8) as $w) {
            if (!is_array($w)) continue;
            $nm = trim((string)($w['name'] ?? $w['key'] ?? ''));
            $st = array_slice((array)($w['steps'] ?? []), 0, 10);
            if ($nm !== '') $parts[] = $nm . ($st ? ' (' . implode(' → ', $st) . ')' : '');
        }
        if (!empty($parts)) $wfLine = 'Operating workflows you MUST implement end-to-end: ' . implode('; ', $parts) . "\n";
    }
    $fldLine = '';
    if (!empty($o['entities']) && is_array($o['entities'])) {
        $parts = [];
        foreach (array_slice($o['entities'], 0, 8) as $e) {
            if (!is_array($e) || empty($e['fields'])) continue;
            $fl = [];
            foreach (array_slice((array)$e['fields'], 0, 12) as $f) $fl[] = is_string($f) ? $f : (string)($f['name'] ?? '');
            $fl = array_values(array_filter($fl));
            if ($fl) $parts[] = (string)(($e['label'] ?? $e['name'] ?? $e['key'] ?? 'entity')) . ': ' . implode(', ', $fl);
        }
        if (!empty($parts)) $fldLine = 'Entity fields (list/add/edit forms MUST use exactly these): ' . implode(' | ', $parts) . "\n";
    }
    $dbLine = $mode === 'database'
        ? 'Generate a MySQL schema (CREATE TABLE ...) for every entity. Use PDO with prepared statements in every PHP file.'
        : 'Use a simple JSON file storage engine (data_<entity>.json) — no MySQL required. Still write clean PHP.';
    return "You are a senior full-stack PHP engineer. Write a COMPLETE, PRODUCTION-READY admin panel.\n\n"
        . "Business: \"{$biz}\" ({$type}) | Mode: {$mode}\n" . $entLine . $wfLine . $fldLine . "Requirements:\n\"\"\"\n"
        . ($reqs !== '' ? $reqs : 'Generate a sensible admin panel for this business type.') . "\n\"\"\"\n\n"
        . "DB: {$dbLine}\n\n"
        . "JSON FILES ARE THE DATABASE: create includes/store.php with db_read(\$entity)/db_write(\$entity,\$rows) "
        . "using flock-shared reads and exclusive locked atomic writes (tmp file + rename), malformed-JSON recovery, "
        . "and row caps. EVERY data page MUST use it — never raw file_put_contents. Data files live next to the admin "
        . "as data_<entity>.json (+ data_inquiries.json for the inbox). Never invent MySQL unless Mode is database.\n\n"
        . "Respond with ONLY a single valid JSON object (no markdown, no commentary):\n"
        . "{\"site_name\":\"...\",\"admin_title\":\"...\",\"entities\":[{\"id\":\"...\",\"name\":\"...\",\"singular\":\"...\",\"icon\":\"...\",\"fields\":[{\"name\":\"...\",\"label\":\"...\",\"type\":\"text\",\"required\":true,\"in_list\":true,\"options\":[]}]}],"
        . "\"files\":{\"login.php\":\"<?php ...\",\"index.php\":\"<?php ...\"},\"sql\":\"...\",\"setup_notes\":\"...\"}\n\n"
        . "RULES: every file complete working PHP, no TODO; login.php reads credentials from .auth.json via password_verify() and session; every protected page calls requireAdmin(); list pages have search + Edit/Delete + Add; add/edit render typed inputs with validation; save handles POST + redirect; dashboard greets + links entities; inline CSS only, no external CDNs except Google Fonts.";
}

function admin_ai_extract_json(string $text): ?array {
    $t = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
    $d = json_decode($t, true);
    if (is_array($d)) return $d;
    $s = strpos($t, '{');
    if ($s === false) return null;
    $depth = 0; $inStr = false; $esc = false;
    for ($i = $s; $i < strlen($t); $i++) {
        $ch = $t[$i];
        if ($inStr) { if ($esc) $esc = false; elseif ($ch === '\\') $esc = true; elseif ($ch === '"') $inStr = false; continue; }
        if ($ch === '"') $inStr = true;
        elseif ($ch === '{') $depth++;
        elseif ($ch === '}') { $depth--; if ($depth === 0) { $d = json_decode(substr($t, $s, $i - $s + 1), true); return is_array($d) ? $d : null; } }
    }
    return null;
}

function admin_ai_shape(array $p, string $engine, string $model): array {
    return ['success' => true, 'engine' => $engine, 'model' => $model,
        'files' => $p['files'], 'sql' => (string)($p['sql'] ?? ''),
        'entities' => is_array($p['entities'] ?? null) ? $p['entities'] : [],
        'admin_title' => (string)($p['admin_title'] ?? 'Admin Panel'),
        'setup_notes' => (string)($p['setup_notes'] ?? '')];
}

// Full mix lane. Returns shaped success array or ['success'=>false,'error'=>..].
// BACKEND CHAIN: OpenRouter free → OpenCode SpaceBunny → Gemini lite → server.
// No network in tests unless keys configured: unconfigured lanes fail fast.
function admin_ai_generate(array $o): array {
    $prompt = admin_ai_prompt($o);
    $sys = 'You output valid JSON only. No markdown fences, no commentary.';
    $errors = [];
    // 1) Primary: OpenRouter free working model (whichever is free + tested).
    if (function_exists('openrouter_is_configured') && openrouter_is_configured()) {
        [$selModel, $selErr] = openrouter_select('code', false);
        if ($selModel !== '') {
            [$ok, $text, $err] = openrouter_chat_once($selModel, [
                ['role' => 'system', 'content' => $sys],
                ['role' => 'user', 'content' => $prompt],
            ], 0.3, 16000, 100);
            if ($ok) {
                $p = admin_ai_extract_json((string)$text);
                if (is_array($p) && !empty($p['files']) && is_array($p['files'])) {
                    return admin_ai_shape($p, 'openrouter', (string)$selModel);
                }
                $errors[] = 'openrouter: invalid code structure';
            } else {
                $errors[] = 'openrouter: ' . ($err ?? 'failed');
            }
        } else {
            $errors[] = 'openrouter: ' . ($selErr ?? 'no free model');
        }
    } else {
        $errors[] = 'openrouter: unconfigured';
    }
    // 2) OpenCode free lane, SpaceBunny-first.
    if (opencode_is_configured()) {
        [$ok2, $text2, $model2] = opencode_chat_fallback('space-bunny-free', [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user', 'content' => $prompt],
        ], 0.3, 16000, 100);
        if ($ok2) {
            $p2 = admin_ai_extract_json((string)$text2);
            if (is_array($p2) && !empty($p2['files']) && is_array($p2['files'])) {
                return admin_ai_shape($p2, 'opencode', (string)$model2);
            }
            $errors[] = 'opencode: invalid code structure';
        } else {
            $errors[] = 'opencode: unavailable';
        }
    } else {
        $errors[] = 'opencode: unconfigured';
    }
    // 3) Fallback: Gemini gemini-3.5-flash-lite (calmer retry on failure).
    [$ok, $text, $err] = gemini_chat_once('gemini-3.5-flash-lite', $sys, $prompt, 0.3, 16000, 110);
    if (!$ok) {
        [$ok, $text, $err] = gemini_chat_once('gemini-3.5-flash-lite', $sys, $prompt, 0.15, 16000, 110);
    }
    if ($ok) {
        $p = admin_ai_extract_json((string)$text);
        if (is_array($p) && !empty($p['files']) && is_array($p['files'])) {
            return admin_ai_shape($p, 'gemini', 'gemini-3.5-flash-lite');
        }
        $errors[] = 'gemini: invalid code structure';
    } else {
        $errors[] = 'gemini: ' . ($err ?? 'failed');
    }
    // 4) Server-written backend: publish.php installs AdminPanelGenerator /
    // AdminDashboardBuilder from the manifest — never block publishing.
    return ['success' => false, 'fallback' => 'server',
        'error' => 'Admin AI unavailable (' . implode('; ', array_slice($errors, 0, 4)) . '). Server-written backend will be used at publish.'];
}
