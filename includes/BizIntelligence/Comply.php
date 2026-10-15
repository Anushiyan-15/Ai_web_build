<?php
// includes/BizIntelligence/Comply.php — Deterministic post-generation compliance.
// Patches ACTUAL model-generated HTML so it satisfies the APPROVED spec's
// structural contract (required sections, wired forms). Additive + honest:
// every change is recorded in $fixes, content is minimal/neutral (no fake
// business data), existing markup is never rewritten — only completed.
// Verify.php stays read-only; call comply BEFORE verify.
require_once __DIR__ . '/Schema.php';

function biz_comply_required_sections(array $spec): array {
    $need = ['hero', 'contact'];
    foreach (($spec['pages'][0]['sections'] ?? []) as $s) {
        $k = strtolower((string)$s);
        if (!in_array($k, $need, true)) $need[] = $k;
    }
    return array_slice($need, 0, 8);
}

function biz_comply_section_missing(string $html, string $key): bool {
    if ($html === '') return true;
    return stripos($html, 'id="' . $key . '"') === false
        && stripos($html, "id='" . $key . "'") === false
        && stripos($html, $key) === false;
}

function biz_comply_section_title(string $key): string {
    $map = [
        'hero' => 'Welcome', 'offerings' => 'Our Offerings', 'trust' => 'Why Choose Us',
        'contact' => 'Get in Touch', 'search' => 'Find Your Trip', 'availability' => 'Availability',
        'form' => 'Book Now', 'ticket' => 'Your Ticket', 'history' => 'Booking History',
        'grid' => 'Browse', 'filters' => 'Filter Results', 'dashboard' => 'Dashboard',
    ];
    if (isset($map[$key])) return $map[$key];
    return ucwords(str_replace(['-', '_'], ' ', $key));
}

function biz_comply_section_block(string $key, string $bizName): string {
    $title = biz_comply_section_title($key);
    $biz = trim($bizName) !== '' ? $bizName : 'us';
    $lines = [
        'offerings' => 'Explore everything ' . $biz . ' offers — pick what fits and continue to booking.',
        'trust' => 'Real trips, clear pricing and dependable support from ' . $biz . '.',
        'contact' => 'Questions or special requests? Send a message and ' . $biz . ' will reply shortly.',
    ];
    $line = $lines[$key] ?? ('Everything about ' . $biz . ' — ' . strtolower($title) . ' — in one place.');
    return '<section id="' . htmlspecialchars($key, ENT_QUOTES) . '" style="padding:4rem 1.5rem;">'
        . '<div style="max-width:1100px;margin:0 auto;text-align:center;">'
        . '<h2 style="font-size:2rem;font-weight:800;margin-bottom:0.75rem;">' . htmlspecialchars($title, ENT_QUOTES) . '</h2>'
        . '<p style="color:#475569;line-height:1.7;">' . htmlspecialchars($line, ENT_QUOTES) . '</p>'
        . '</div></section>';
}

function biz_comply_form_purpose(string $formHtml): string {
    $t = strtolower($formHtml);
    if (preg_match('/seat|trip|book|date|slot|appointment|reserve|cancel/', $t)) return 'booking';
    if (preg_match('/newsletter|subscribe/', $t)) return 'newsletter';
    return 'contact';
}

// Returns ['html' => patched, 'fixes' => [human-readable...]].
function biz_ensure_html_compliance(string $html, array $spec): array {
    $fixes = [];
    if (strlen($html) < 500 || stripos($html, '</body>') === false) {
        return ['html' => $html, 'fixes' => $fixes];
    }
    $bizName = (string)($spec['profile']['business_name'] ?? '');

    // 1. Required sections present (same contract Verify checks).
    foreach (biz_comply_required_sections($spec) as $key) {
        if (!biz_comply_section_missing($html, $key)) continue;
        $block = "\n" . biz_comply_section_block($key, $bizName) . "\n";
        if (stripos($html, '</main>') !== false) {
            $html = preg_replace('/<\/main>/i', $block . '</main>', $html, 1);
        } elseif (preg_match('/<footer\b/i', $html)) {
            $html = preg_replace('/<footer\b/i', $block . '<footer', $html, 1);
        } else {
            $html = preg_replace('/<\/body>/i', $block . '</body>', $html, 1);
        }
        $fixes[] = 'Added missing section #' . $key;
    }

    // 2. Forms wired to the platform convention (data-wc-form + thanks).
    // Existing wiring (data-wc-form / data-wc-search / business-ops.php /
    // contact.php) untouched — search forms must never become create forms.
    // Purpose is inferred from the form's own fields (booking/newsletter/contact).
    $html = preg_replace_callback('/<form\b([^>]*)>([\s\S]*?)<\/form>/i', function ($m) use (&$fixes) {
        $full = $m[0];
        if (stripos($full, 'data-wc-form') !== false || stripos($full, 'data-wc-search') !== false || stripos($full, 'business-ops') !== false || stripos($full, 'contact.php') !== false) {
            return $full;
        }
        $purpose = biz_comply_form_purpose($full);
        $fixes[] = 'Wired form as "' . $purpose . '" (validation kept, backend handler attached)';
        return preg_replace('/<form\b/i',
            '<form data-wc-form="' . $purpose . '" data-wc-thanks="&#10003; Thank you! We will be in touch shortly."',
            $full, 1);
    }, $html);

    return ['html' => $html, 'fixes' => array_values(array_unique($fixes))];
}
