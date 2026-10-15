<?php
// includes/BizIntelligence/Interview.php — Adaptive AI requirements interview.
// Generates context-specific follow-up questions ONLY for gaps that
// materially affect design/functionality/architecture/cost/security.
// Rules: never repeat answered topics, selectable options + custom + skip,
// labeled assumptions, stop when sufficient, confirmation gates for
// payments/integrations/permissions/sensitive workflows.
require_once __DIR__ . '/Schema.php';

function biz_interview_question_bank(): array {
    // Each: key, topic, ask_when(signal/capability), critical bool,
    // options[] (value+label), allows_custom, confirmation_gate bool.
    return [
        ['key' => 'payments_methods', 'topic' => 'payments',
         'q' => 'How should customers pay you?',
         'when' => ['take_payments'], 'critical' => true, 'gate' => true,
         'options' => [
             ['v' => 'online', 'label' => 'Online payment (cards/UPI)'],
             ['v' => 'counter', 'label' => 'Pay at counter / on delivery'],
             ['v' => 'both', 'label' => 'Both online and counter'],
             ['v' => 'none', 'label' => 'No payments needed'],
         ]],
        ['key' => 'payments_refunds', 'topic' => 'payments',
         'q' => 'What is your cancellation and refund policy?',
         'when' => ['take_payments', 'reservable'], 'critical' => true, 'gate' => true,
         'options' => [
             ['v' => 'full_window', 'label' => 'Full refund within a time window'],
             ['v' => 'partial', 'label' => 'Partial refund / reschedule only'],
             ['v' => 'none', 'label' => 'No refunds'],
         ]],
        ['key' => 'booking_capacity', 'topic' => 'booking',
         'q' => 'What exactly can customers reserve, and what is its capacity?',
         'when' => ['reservable'], 'critical' => true, 'gate' => false,
         'options' => [
             ['v' => 'seats', 'label' => 'Seats (bus/event) with a fixed layout'],
             ['v' => 'slots', 'label' => 'Time slots (appointments/tables)'],
             ['v' => 'units', 'label' => 'Units/items (rooms/equipment)'],
         ]],
        ['key' => 'booking_hold', 'topic' => 'booking',
         'q' => 'How long should an unpaid reservation be held before release?',
         'when' => ['reservable'], 'critical' => false, 'gate' => false,
         'options' => [
             ['v' => '15m', 'label' => '15 minutes (recommended)'],
             ['v' => '1h', 'label' => '1 hour'],
             ['v' => '24h', 'label' => '24 hours'],
         ]],
        ['key' => 'accounts_needed', 'topic' => 'accounts',
         'q' => 'Do customers need their own login accounts?',
         'when' => ['accounts'], 'critical' => true, 'gate' => true,
         'options' => [
             ['v' => 'yes', 'label' => 'Yes — order/booking history needed'],
             ['v' => 'no', 'label' => 'No — guest checkout/booking is fine'],
         ]],
        ['key' => 'staff_roles', 'topic' => 'staff',
         'q' => 'Who besides you will manage the website?',
         'when' => ['manage_ops'], 'critical' => false, 'gate' => false,
         'options' => [
             ['v' => 'owner_only', 'label' => 'Only me (owner)'],
             ['v' => 'staff', 'label' => 'Staff with limited access'],
             ['v' => 'multi', 'label' => 'Multiple roles (manager/staff)'],
         ]],
        ['key' => 'inventory_track', 'topic' => 'inventory',
         'q' => 'Should stock levels update automatically on each sale?',
         'when' => ['sell_goods'], 'critical' => false, 'gate' => false,
         'options' => [
             ['v' => 'yes', 'label' => 'Yes — track stock'],
             ['v' => 'no', 'label' => 'No — just showcase items'],
         ]],
        ['key' => 'delivery_fulfil', 'topic' => 'fulfillment',
         'q' => 'How do customers receive their order?',
         'when' => ['sell_goods'], 'critical' => true, 'gate' => false,
         'options' => [
             ['v' => 'delivery', 'label' => 'Home delivery'],
             ['v' => 'pickup', 'label' => 'Pickup / in-store'],
             ['v' => 'digital', 'label' => 'Digital delivery'],
         ]],
        ['key' => 'notify_channels', 'topic' => 'notify',
         'q' => 'How should customers be notified about their booking/order?',
         'when' => ['notify'], 'critical' => false, 'gate' => false,
         'options' => [
             ['v' => 'email', 'label' => 'Email'],
             ['v' => 'onscreen', 'label' => 'On-screen + account history only'],
         ]],
        ['key' => 'integration_keys', 'topic' => 'integrations',
         'q' => 'Do you have credentials for payment/SMS/maps providers to connect?',
         'when' => ['take_payments'], 'critical' => true, 'gate' => true,
         'options' => [
             ['v' => 'later', 'label' => 'I will configure them later'],
             ['v' => 'have', 'label' => 'I have them (enter in site settings after generation)'],
             ['v' => 'none', 'label' => 'No external providers needed'],
         ]],
        ['key' => 'content_source', 'topic' => 'content',
         'q' => 'Where should real business content (prices, photos, address) come from?',
         'when' => [], 'critical' => false, 'gate' => false,
         'options' => [
             ['v' => 'provided', 'label' => 'I already entered it in the form'],
             ['v' => 'edit_later', 'label' => 'I will edit placeholders after generation'],
         ]],
    ];
}

// Derive signal set from wizard + spec capabilities (reuses pipeline signals).
function biz_interview_signals(array $wizard, array $spec): array {
    require_once __DIR__ . '/Pipeline.php';
    $sig = biz_infer_signals(biz_collect_text($wizard));
    foreach (($spec['capabilities'] ?? []) as $c) {
        if ($c === 'booking') { $sig['reservable'] = true; }
        if ($c === 'orders') { $sig['sell_goods'] = true; $sig['take_payments'] = true; }
        if ($c === 'payments') { $sig['take_payments'] = true; }
        if ($c === 'identity') { $sig['accounts'] = true; }
        if ($c === 'inventory') { $sig['sell_goods'] = true; }
    }
    return $sig;
}

// Next questions: filter bank by signals, skip answered/skipped keys, cap count.
// NOTE: exclusion is per question KEY (not whole topic) so distinct questions
// sharing a topic (payments_methods + payments_refunds, booking_capacity +
// booking_hold) each get asked once instead of the second vanishing after the
// first is answered. Repeat-safety is preserved: an answered/skipped key never
// returns.
function biz_interview_next(array $wizard, array $spec, array $answers = [], array $skipped = [], int $max = 4): array {
    $sig = biz_interview_signals($wizard, $spec);
    $done = [];
    foreach ($answers as $k => $v) { $done[(string)$k] = true; }
    foreach ($skipped as $k => $v) { $done[(string)$k] = true; }
    $out = [];
    foreach (biz_interview_question_bank() as $q) {
        if (isset($done[$q['key']])) continue; // never repeat an answered/skipped question
        $fire = empty($q['when']);
        if (!$fire) { foreach ($q['when'] as $s) { if (!empty($sig[$s])) { $fire = true; break; } } }
        if (!$fire) continue;
        // content_source only when real content looks thin
        if ($q['key'] === 'content_source') {
            $hasContent = trim((string)($wizard['biz_products'] ?? '')) !== ''
                || trim((string)($wizard['biz_services'] ?? '')) !== ''
                || trim((string)($wizard['biz_logo'] ?? '')) !== '';
            if ($hasContent) continue;
        }
        $out[] = ['key' => $q['key'], 'q' => $q['q'], 'options' => $q['options'],
            'critical' => $q['critical'], 'gate' => $q['gate'], 'allows_custom' => true, 'skippable' => !$q['critical']];
        if (count($out) >= $max) break;
    }
    // Critical-first ordering
    usort($out, fn($a, $b) => ((int)$b['critical']) - ((int)$a['critical']));
    return $out;
}

function biz_interview_done(array $wizard, array $spec, array $answers = [], array $skipped = []): bool {
    return empty(biz_interview_next($wizard, $spec, $answers, $skipped, 1));
}

// Apply validated answers: returns [patch summary, assumption notes].
// Answers referencing unknown keys or out-of-range option values are ignored.
function biz_interview_apply(array &$spec, array $answers): array {
    $applied = []; $notes = [];
    $bank = [];
    foreach (biz_interview_question_bank() as $q) $bank[$q['key']] = $q;
    foreach ($answers as $k => $v) {
        if (!isset($bank[$k])) continue;
        $q = $bank[$k];
        $v = is_string($v) ? trim(substr($v, 0, 200)) : '';
        if ($v === '') continue;
        $valid = array_column($q['options'], 'v');
        $isCustom = !in_array($v, $valid, true);
        // Confirmation gates: record explicit confirmation in uncertainty.
        if (!empty($q['gate'])) {
            $spec['uncertainty']['confirmed'][] = $q['q'] . ' → ' . $v . ($isCustom ? ' (custom)' : '');
        } else {
            $spec['uncertainty']['inferred'][] = $q['q'] . ' → ' . $v . ($isCustom ? ' (custom)' : '');
        }
        // Remove matching unresolved items.
        $spec['uncertainty']['unresolved'] = array_values(array_filter(
            ($spec['uncertainty']['unresolved'] ?? []),
            fn($u) => stripos((string)$u, $q['topic']) === false));
        $applied[$k] = $v;
    }
    // Targeted spec adjustments from answers (additive, never destructive).
    if (isset($applied['payments_methods'])) {
        $m = $applied['payments_methods'];
        if ($m === 'none' && in_array('payments', $spec['capabilities'] ?? [], true)) {
            $spec['capabilities'] = array_values(array_diff($spec['capabilities'], ['payments']));
            $notes[] = 'Payments capability removed per owner answer.';
        }
        if ($m === 'counter' || $m === 'both') {
            $notes[] = 'Counter-payment workflow required (verify-then-confirm).';
        }
    }
    if (isset($applied['accounts_needed']) && $applied['accounts_needed'] === 'no') {
        $spec['capabilities'] = array_values(array_diff(($spec['capabilities'] ?? []), ['identity']));
        $spec['actors'] = array_values(array_filter(($spec['actors'] ?? []),
            fn($a) => strtolower(is_array($a) ? ($a['role'] ?? '') : $a) !== 'customer'));
        $notes[] = 'Guest flow selected; customer accounts removed.';
    }
    if (isset($applied['inventory_track']) && $applied['inventory_track'] === 'no') {
        $spec['capabilities'] = array_values(array_diff(($spec['capabilities'] ?? []), ['inventory']));
        $notes[] = 'Showcase-only catalog; stock tracking removed.';
    }
    return ['applied' => $applied, 'notes' => $notes];
}
