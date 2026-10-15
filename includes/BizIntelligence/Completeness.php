<?php
// includes/BizIntelligence/Completeness.php — Requirement completeness + gap
// analysis. The score is computed from ACTUAL checks below — never an
// arbitrary 100%. Generation may proceed with documented assumptions;
// critical unresolved items require confirmation (blocking).
require_once __DIR__ . '/Schema.php';

function biz_completeness_checks(): array {
    // Each: key, label, weight, critical bool, test(spec, answers) -> [pass, detail].
    return [
        ['key' => 'identity', 'label' => 'Business identity (name + what it offers)', 'w' => 12, 'critical' => true,
         'test' => fn($s, $a) => [trim((string)($s['profile']['business_name'] ?? '')) !== '' && trim((string)($s['profile']['description'] ?? '')) !== '', 'name/description present']],
        ['key' => 'audience', 'label' => 'Target audience identified', 'w' => 8, 'critical' => false,
         'test' => fn($s, $a) => [!empty($s['profile']['target_users']) || stripos(json_encode($s['profile']), 'audience') !== false, 'audience in profile']],
        ['key' => 'entities', 'label' => 'Core data entities defined', 'w' => 12, 'critical' => true,
         'test' => fn($s, $a) => [!empty($s['entities']), count($s['entities'] ?? []) . ' entities']],
        ['key' => 'workflows', 'label' => 'Main workflows mapped (happy + failure paths)', 'w' => 12, 'critical' => true,
         'test' => function ($s, $a) {
             foreach (($s['workflows'] ?? []) as $w) {
                 if (empty($w['failures'])) return [false, 'workflow ' . ($w['key'] ?? '?') . ' lacks failure handling'];
             }
             return [!empty($s['workflows']), count($s['workflows'] ?? []) . ' workflows'];
         }],
        ['key' => 'payments decided', 'label' => 'Payment method decided (when payments needed)', 'w' => 10, 'critical' => true,
         'test' => function ($s, $a) {
             if (!in_array('payments', $s['capabilities'] ?? [], true)) return [true, 'n/a — no payments'];
             return [isset($a['payments_methods']), isset($a['payments_methods']) ? 'answered' : 'UNRESOLVED — blocks safe checkout'];
         }],
        ['key' => 'refunds decided', 'label' => 'Cancellation/refund policy decided', 'w' => 8, 'critical' => false,
         'test' => function ($s, $a) {
             if (!in_array('booking', $s['capabilities'] ?? [], true) && !in_array('orders', $s['capabilities'] ?? [], true)) return [true, 'n/a'];
             return [isset($a['payments_refunds']), isset($a['payments_refunds']) ? 'answered' : 'assumed: configurable default'];
         }],
        ['key' => 'accounts decided', 'label' => 'Customer accounts vs guest flow decided', 'w' => 8, 'critical' => false,
         'test' => function ($s, $a) {
             $needs = in_array('identity', $s['capabilities'] ?? [], true);
             if (!$needs && !isset($a['accounts_needed'])) return [true, 'n/a'];
             return [isset($a['accounts_needed']), isset($a['accounts_needed']) ? 'answered' : 'assumed: guest-capable'];
         }],
        ['key' => 'admin ops', 'label' => 'Admin operations cover all entities', 'w' => 10, 'critical' => false,
         'test' => function ($s, $a) {
             $eks = array_column($s['entities'] ?? [], 'key');
             $mods = array_map('strtolower', $s['compat']['admin_modules'] ?? []);
             $missing = [];
             foreach ($eks as $k) {
                 $found = false;
                 foreach ($mods as $m) { if (stripos($m, (string)$k) !== false || stripos((string)$k, $m) !== false) { $found = true; break; } }
                 if (!$found && !in_array($k, ['inquiries', 'reviews'], true)) $missing[] = $k;
             }
             return [empty($missing), empty($missing) ? 'covered' : 'missing admin for: ' . implode(',', $missing)];
         }],
        ['key' => 'data decisions', 'label' => 'Database/integration decisions resolved', 'w' => 8, 'critical' => false,
         'test' => function ($s, $a) {
             $u = $s['uncertainty']['unresolved'] ?? [];
             $hard = array_filter($u, fn($x) => stripos((string)$x, 'credential') !== false || stripos((string)$x, 'integration') !== false);
             return [empty($hard), empty($hard) ? 'resolved or deferred' : count($hard) . ' pending'];
         }],
        ['key' => 'conflicts', 'label' => 'No contradictory requirements', 'w' => 4, 'critical' => false,
         'test' => function ($s, $a) {
             $caps = $s['capabilities'] ?? [];
             if (in_array('identity', $caps, true) && ($a['accounts_needed'] ?? '') === 'no') return [false, 'accounts capability vs guest answer'];
             return [true, 'none detected'];
         }],
    ];
}

// Returns {score 0-100, checks[{key,label,pass,detail,critical}], blocking[],
// missing[], assumptions[], can_proceed bool}.
function biz_completeness(array $spec, array $answers = []): array {
    $total = 0; $earned = 0; $checks = []; $blocking = []; $missing = [];
    foreach (biz_completeness_checks() as $c) {
        [$pass, $detail] = $c['test']($spec, $answers);
        $total += $c['w'];
        if ($pass) $earned += $c['w'];
        $checks[] = ['key' => $c['key'], 'label' => $c['label'], 'pass' => $pass,
            'detail' => (string)$detail, 'critical' => $c['critical'], 'weight' => $c['w']];
        if (!$pass && $c['critical']) $blocking[] = $c['label'] . ' — ' . $detail;
        if (!$pass) $missing[] = $c['label'];
    }
    $score = $total > 0 ? (int)round(100 * $earned / $total) : 0;
    $assumptions = $spec['uncertainty']['assumptions'] ?? [];
    return ['score' => $score, 'checks' => $checks, 'blocking' => array_values($blocking),
        'missing' => array_values($missing),
        'assumptions' => array_values($assumptions),
        'confirmed' => $spec['uncertainty']['confirmed'] ?? [],
        'can_proceed' => empty($blocking)];
}
