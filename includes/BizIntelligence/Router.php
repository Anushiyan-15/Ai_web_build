<?php
// includes/BizIntelligence/Router.php — Centralized generation router.
// Boundaries: analysis -> complexity -> provider routing -> design gen ->
// app gen -> backend -> validation/testing -> editor/persistence.
// Design-gen failure never erases the spec. Same spec across providers.
// Secrets never leave the server; history logged without sensitive data.
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Complexity.php';

function biz_route_log_dir(): string {
    $base = (defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__, 2) . '/storage');
    $d = rtrim($base, '/\\') . '/engine';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

function biz_route_log(array $entry): void {
    $f = biz_route_log_dir() . '/route-history.jsonl';
    $entry['at'] = date('c');
    // strip anything secret-looking
    unset($entry['api_key'], $entry['secret'], $entry['password']);
    @file_put_contents($f, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

// Validate one generated variation. Only complete+contentful passes.
function biz_validate_variation(string $html, array $spec = []): array {
    if (strlen(trim($html)) < 2000) return [false, 'too short'];
    if (stripos($html, '</html>') === false) return [false, 'missing </html>'];
    $txt = trim(strip_tags(preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', '', $html)));
    if (strlen($txt) < 300) return [false, 'no readable content'];
    if (preg_match('/lorem ipsum/i', $html)) return [false, 'lorem ipsum'];
    if (substr_count(strtolower($html), '<body') > 1) return [false, 'duplicate body'];
    // Required sections: spec pages' first route sections OR at least hero+contact-ish
    if (!preg_match('/<(header|nav|section|main|footer)/i', $html)) return [false, 'no sections'];
    return [true, 'ok'];
}

function biz_dedupe_variations(array $designs): array {
    $seen = []; $out = [];
    foreach ($designs as $d) {
        $h = md5(preg_replace('/\s+/', '', substr((string)($d['html'] ?? ''), 0, 20000)));
        if (isset($seen[$h])) continue;
        $seen[$h] = true;
        $out[] = $d;
    }
    return $out;
}

// Decide routing from a validated spec. Returns routing metadata.
function biz_decide_route(array $spec, ?array $complexityCfg = null): array {
    $cx = biz_assess_complexity($spec, $complexityCfg);
    $policy = biz_routing_policy($cx['level']);
    $route = [
        'complexity' => $cx['level'],
        'score' => $cx['score'],
        'reasons' => $cx['reasons'],
        'counts' => $cx['counts'],
        'variations' => $policy['variations'],
        'primary' => $policy['primary'],
        'fallback1' => $policy['fallback1'],
        'fallback2' => $policy['fallback2'],
        'model' => 'gemini-3.5-flash-lite', // verified; never silently switched
        'history' => [],
    ];
    biz_route_log(['event' => 'route-decided', 'complexity' => $cx['level'],
        'score' => $cx['score'], 'variations' => $policy['variations']]);
    return $route;
}

function biz_route_record(array &$route, string $event, array $extra = []): void {
    $route['history'][] = array_merge(['event' => $event, 'at' => date('c')], $extra);
    biz_route_log(array_merge(['event' => $event], $extra));
}
