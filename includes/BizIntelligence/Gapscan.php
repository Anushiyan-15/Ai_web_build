<?php
// includes/BizIntelligence/Gapscan.php — AI-spec-driven missing-function analysis.
// Compares the APPROVED spec against ACTUAL generated HTML and reports every
// frontend control without a working backend handler, every form that does
// not save, and every customer action invisible to the admin. Deterministic
// fixes reuse Comply + ShopHelper + BusinessRuntime (never decorative).
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Comply.php';

/**
 * @return array{findings: array, auto_fixable: int}
 * finding: {area, severity: high|medium|info, detail, fix: wire_forms|runtime|shop|sections|manual}
 */
function biz_gapscan(array $spec, string $html): array {
    $findings = [];
    $caps = $spec['capabilities'] ?? [];
    $prime = $html;
    // Scan markup only: <form> strings inside <script> blocks (e.g. the
    // runtime's own lookup-form template) are not real forms.
    $markup = preg_replace('/<script\b[\s\S]*?<\/script\s*>/i', '', $prime);
    $markup = preg_replace('/<style\b[\s\S]*?<\/style\s*>/i', '', $markup);
    // 1. Forms without any backend handler
    if ($markup !== '' && preg_match_all('/<form\b[^>]*>/i', $markup, $fm)) {
        $bare = 0;
        foreach ($fm[0] as $tag) {
            if (stripos($tag, 'data-wc-form') === false && stripos($tag, 'data-wc-search') === false && stripos($tag, 'business-ops') === false && stripos($tag, 'contact.php') === false) $bare++;
        }
        if ($bare > 0) $findings[] = ['area' => 'forms', 'severity' => 'high',
            'detail' => $bare . ' form(s) submit nowhere (no data-wc-form/backend wiring)', 'fix' => 'wire_forms'];
    }
    // 2. Required sections missing
    $missing = [];
    foreach (biz_comply_required_sections($spec) as $s) {
        if (biz_comply_section_missing($prime, $s)) $missing[] = $s;
    }
    if (!empty($missing)) $findings[] = ['area' => 'sections', 'severity' => 'medium',
        'detail' => 'missing sections: ' . implode(',', $missing), 'fix' => 'sections'];
    // 3. Booking capability without booking UI markers
    if (in_array('booking', $caps, true)) {
        $hit = false;
        foreach (['book', 'seat', 'reserve'] as $mk) { if ($prime !== '' && stripos($prime, $mk) !== false) { $hit = true; break; } }
        if (!$hit) $findings[] = ['area' => 'booking', 'severity' => 'high',
            'detail' => 'booking required but no booking UI (search/hold/confirm) found', 'fix' => 'manual'];
    }
    // 4. Orders capability without cart markers
    if (in_array('orders', $caps, true)) {
        $hit = false;
        foreach (['cart', 'checkout', 'order'] as $mk) { if ($prime !== '' && stripos($prime, $mk) !== false) { $hit = true; break; } }
        if (!$hit) $findings[] = ['area' => 'orders', 'severity' => 'high',
            'detail' => 'orders required but no cart/checkout UI found', 'fix' => 'manual'];
    }
    // 5. No runtime wiring at all
    if ($prime !== '' && stripos($prime, 'wc-biz-runtime') === false && stripos($prime, 'data-wc-form') === false && stripos($prime, 'data-wc-search') === false && stripos($prime, 'data-wc-catalog') === false
        && stripos($prime, 'contact.php') === false && stripos($prime, 'business-ops') === false) {
        $findings[] = ['area' => 'runtime', 'severity' => 'high',
            'detail' => 'no frontend→backend wiring detected (no runtime, no handlers)', 'fix' => 'runtime'];
    }
    // 6. Dead # anchors
    if ($prime !== '') {
        preg_match_all('/href="#([A-Za-z0-9_\-]+)"/', $prime, $m);
        $dead = [];
        foreach (array_unique($m[1] ?? []) as $id) {
            if (stripos($prime, 'id="' . $id . '"') === false && stripos($prime, "id='" . $id . "'") === false) $dead[] = $id;
        }
        if (!empty($dead)) $findings[] = ['area' => 'anchors', 'severity' => 'medium',
            'detail' => 'dead anchors: ' . implode(',', array_slice($dead, 0, 6)), 'fix' => 'manual'];
    }
    // 7. Admin coverage for spec entities
    if (!empty($spec['entities'])) {
        $findings[] = ['area' => 'admin', 'severity' => 'info',
            'detail' => count($spec['entities']) . ' spec entities → admin modules + mirrored live data', 'fix' => 'manual'];
    }
    $auto = 0;
    foreach ($findings as $f) { if (in_array($f['fix'], ['wire_forms', 'sections', 'runtime'], true)) $auto++; }
    return ['findings' => $findings, 'auto_fixable' => $auto];
}

/**
 * Applies ONLY deterministic fixes (forms/sections/runtime). Content-level
 * gaps (booking UI, shop catalog) are reported, never faked.
 * @return array{html: string, applied: string[], remaining: array}
 */
function biz_gapfix(array $spec, string $html, string $slug = 'site'): array {
    $applied = [];
    $r = biz_ensure_html_compliance($html, $spec);
    if (!empty($r['fixes'])) { $html = $r['html']; $applied = array_merge($applied, $r['fixes']); }
    if (stripos($html, 'wc-biz-runtime') === false && strlen($html) > 500) {
        require_once dirname(__DIR__) . '/BusinessRuntime.php';
        $before = $html;
        $html = business_runtime_inject($html, $spec, $slug);
        if ($html !== $before) $applied[] = 'Injected frontend→backend runtime (purpose-aware forms + history + WCBiz)';
    }
    $scan = biz_gapscan($spec, $html);
    return ['html' => $html, 'applied' => array_values($applied), 'remaining' => $scan['findings']];
}
