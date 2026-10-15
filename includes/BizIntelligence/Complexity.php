<?php
// includes/BizIntelligence/Complexity.php — Project complexity assessment.
// Uses the COMPLETE validated spec (entities, workflows, roles, transactions,
// integrations), never page-count alone. Configurable thresholds.
// Returns {level: small|large, score, reasons[], variations, policy}.

function biz_complexity_defaults(): array {
    return [
        'large_score' => 20,      // total >= this => large
        'entity_w' => 0.8,        // per entity
        'workflow_w' => 2,        // per substantive workflow (inquiry counts 0.5)
        'workflow_step_w' => 0.4, // per workflow step beyond 4
        'role_customer_w' => 2,   // authenticated customer role
        'role_staff_w' => 1,      // operational staff role
        'role_admin_w' => 0.5,    // owner admin (near-baseline)
        'transaction_w' => 2,     // per transaction boundary beyond the first
        'integration_w' => 2,     // per external integration/signal
        'capability_w' => 0.5,    // per composed capability
        // Force-large triggers regardless of score:
        'force_large_workflows' => 4,
        'force_large_entities' => 8,
        'force_large_roles' => 4, // non-visitor roles
    ];
}

function biz_assess_complexity(array $spec, ?array $cfg = null): array {
    $c = array_merge(biz_complexity_defaults(), $cfg ?? []);
    $entities = $spec['entities'] ?? [];
    // compat shim: legacy flat spec
    if (empty($entities) && !empty($spec['compat']['pages'])) {
        $entities = array_map(fn($p) => ['key' => $p], (array)$spec['compat']['pages']);
    }
    $workflows = $spec['workflows'] ?? [];
    $actors = $spec['actors'] ?? [];
    $roles = array_values(array_filter(array_map(fn($a) => is_array($a) ? ($a['role'] ?? '') : (string)$a, $actors),
        fn($r) => $r !== '' && strtolower($r) !== 'visitor'));
    $txns = $spec['backend']['transactions'] ?? [];
    $apis = $spec['backend']['apis'] ?? [];
    $caps = $spec['capabilities'] ?? [];
    // integration signals: payments/subscriptions/inventory/approvals/documents
    $integ = 0;
    foreach (['payments','subscriptions','inventory','approvals','documents'] as $k) {
        if (in_array($k, $caps, true)) $integ++;
    }
    // custom domain components add weight
    $custom = count($spec['custom'] ?? []);

    $nw = count($workflows);
    $nr = count($roles);
    $nt = count($txns);
    $score = 0; $reasons = [];
    $ne = count($entities);
    $score += $ne * $c['entity_w'];
    if ($ne) $reasons[] = $ne . ' entities';
    // Substantive workflows count full; the trivial inquiry workflow counts half.
    $nwFull = 0;
    foreach ($workflows as $w) {
        $wk = is_array($w) ? ($w['key'] ?? '') : '';
        $nwFull += ($wk === 'inquiry') ? 0.5 : 1;
    }
    $score += $nwFull * $c['workflow_w'];
    if ($nw) $reasons[] = $nw . ' workflows';
    $extraSteps = 0;
    foreach ($workflows as $w) {
        $ns = is_array($w['steps'] ?? null) ? count($w['steps']) : 0;
        if ($ns > 4) $extraSteps += ($ns - 4);
    }
    $score += $extraSteps * $c['workflow_step_w'];
    if ($extraSteps) $reasons[] = $extraSteps . ' extra workflow steps';
    // Roles weighted by operational weight (admin is near-baseline).
    $roleScore = 0;
    foreach ($roles as $r) {
        $rl = strtolower($r);
        if ($rl === 'customer') $roleScore += $c['role_customer_w'];
        elseif ($rl === 'staff') $roleScore += $c['role_staff_w'];
        elseif ($rl === 'admin') $roleScore += $c['role_admin_w'];
        else $roleScore += 1;
    }
    $score += $roleScore;
    if ($nr) $reasons[] = $nr . ' roles (' . implode(',', $roles) . ')';
    // First transaction boundary is baseline; additional ones add complexity.
    $ntExtra = max(0, $nt - 1);
    $score += $ntExtra * $c['transaction_w'];
    if ($nt) $reasons[] = $nt . ' transaction boundaries';
    $score += $integ * $c['integration_w'];
    if ($integ) $reasons[] = $integ . ' integrations';
    $score += count($caps) * $c['capability_w'];
    $score += $custom * 1.5;
    if ($custom) $reasons[] = $custom . ' custom components';
    if (count($apis) >= 6) { $score += 2; $reasons[] = count($apis) . ' APIs'; }

    $forceLarge = ($nw >= $c['force_large_workflows']) || ($ne >= $c['force_large_entities']) || ($nr >= $c['force_large_roles']);
    if ($forceLarge) $reasons[] = 'force-large trigger hit';
    $level = ($forceLarge || $score >= $c['large_score']) ? 'large' : 'small';

    return [
        'level' => $level,
        'score' => round($score, 1),
        'threshold' => $c['large_score'],
        'reasons' => $reasons,
        'variations' => $level === 'large' ? 1 : 3,
        'counts' => ['entities' => $ne, 'workflows' => $nw, 'roles' => $nr,
            'transactions' => $nt, 'integrations' => $integ,
            'capabilities' => count($caps), 'custom' => $custom, 'apis' => count($apis)],
    ];
}

// Routing table (requirements, not suggestions)
// FRONTEND CHAIN (user order): Space Bunny primary → gemini-3.5-flash-lite
// fallback → server templates. Same for small (3 vars) and large/big
// projects with admin (1 flagship) — frontend and backend stay connected
// via the same validated spec.
function biz_routing_policy(string $level): array {
    if ($level === 'large') {
        return ['variations' => 1, 'primary' => 'spacebunny', 'fallback1' => 'gemini', 'fallback2' => 'templates'];
    }
    return ['variations' => 3, 'primary' => 'spacebunny', 'fallback1' => 'gemini', 'fallback2' => 'templates'];
}
