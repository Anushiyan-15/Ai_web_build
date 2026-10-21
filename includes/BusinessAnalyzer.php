<?php
// includes/BusinessAnalyzer.php — Universal AI business analyzer.
// Analyzes name/category/description/services/products/audience/pages/forms
// and returns the concrete admin functions THIS business needs.
// Rule-based registry first (deterministic, offline-safe); Gemini enrichment
// is best-effort only and never invents unsupported requirements.

require_once __DIR__ . '/BusinessRegistry.php';

if (!function_exists('business_analyzer_analyze')) {

function business_analyzer_input_text(array $d): string {
    return implode(' ', [
        (string)($d['biz_name'] ?? ''), (string)($d['biz_type'] ?? ''), (string)($d['category'] ?? ''),
        (string)($d['description'] ?? ''), (string)($d['biz_description'] ?? ''),
        (string)($d['services'] ?? ''), (string)($d['biz_services'] ?? ''),
        (string)($d['products'] ?? ''), (string)($d['biz_products'] ?? ''),
        (string)($d['audience'] ?? ''), (string)($d['biz_audience'] ?? ''),
        (string)($d['requirements'] ?? ''), (string)($d['tagline'] ?? ''),
        implode(' ', (array)($d['sections'] ?? [])), (string)($d['html'] ?? ''),
    ]);
}

function business_analyzer_gemini_hint(string $text, ?string &$provider = null): ?string {
    try {
        if (!empty($GLOBALS['BUSINESS_ANALYZER_NO_AI'])) return null; // offline/test fast path
        if (strlen(trim($text)) < 40) return null; // rule-based is enough for tiny inputs
        if (!function_exists('ai_chat')) {
            @require_once __DIR__ . '/AiProviderChain.php';
        }
        if (!function_exists('ai_chat')) return null;
        // Provider order: OpenRouter free model → Gemini → none (rule-based stands).
        $prompt = "Classify this business into ONE key: bus,ecommerce,restaurant,hotel,salon,clinic,realestate,education,travel,events,rental,automotive,subscription,portfolio,agency,professional,generic.\n"
            . "Reply with JSON only: {\"category\":\"...\",\"confidence\":0-1,\"reason\":\"...\"}\nBusiness:\n" . substr($text, 0, 1500);
        $r = ai_chat('You classify businesses. Reply with JSON only.',
            $prompt, ['task' => 'json', 'temperature' => 0.1, 'max_tokens' => 256, 'timeout' => 0]);
        if (empty($r['ok']) || trim((string)($r['text'] ?? '')) === '') return null;
        $provider = (string)($r['provider'] ?? '');
        $t = (string)$r['text'];
        if (preg_match('/\{.*\}/s', $t, $m)) {
            $d = json_decode($m[0], true);
            $c = strtolower((string)($d['category'] ?? ''));
            if (in_array($c, business_registry_keys(), true)) return $c;
        }
        return null;
    } catch (Throwable $e) { return null; }
}

function business_analyzer_analyze(array $d): array {
    $name = trim((string)($d['biz_name'] ?? $d['business_name'] ?? $d['site_name'] ?? 'Business'));
    if ($name === '') $name = 'Business';
    $explicit = strtolower(trim((string)($d['biz_type'] ?? $d['category'] ?? '')));
    $text = business_analyzer_input_text($d);
    $ruleCat = business_registry_detect($text);
    // Honor an explicit valid category when rule scores are weak/ambiguous.
    $explicitKey = in_array($explicit, business_registry_keys(), true) ? $explicit : '';
    $category = $ruleCat;
    $engine = 'rule-based';
    $aiProvider = '';
    $aiHint = business_analyzer_gemini_hint($text, $aiProvider);
    if ($aiHint && $aiHint !== $ruleCat) {
        // AI breaks ties only when rule detection fell back to generic/agency.
        if (in_array($ruleCat, ['generic', 'professional', 'agency'], true)) {
            $category = $aiHint; $engine = 'rule-based+' . ($aiProvider !== '' ? $aiProvider : 'ai');
        }
    }
    if ($explicitKey !== '' && in_array($ruleCat, ['generic', 'professional'], true)) {
        $category = $explicitKey; // respect customer's stated category over weak guess
    }
    $cat = business_registry_get($category);

    // Build the 8-point function list required by the spec.
    $functions = [];
    foreach (($cat['admin_modules'] ?? []) as $m) {
        $map = null;
        foreach (($cat['frontend_mappings'] ?? []) as $fm) {
            if (($fm['admin_page'] ?? '') === ($m['label'] ?? '') || ($fm['entity'] ?? '') === ($m['entity'] ?? 'x')) { $map = $fm; break; }
        }
        $entity = (string)($m['entity'] ?? '');
        $functions[] = [
            'module' => $m['id'] ?? '', 'label' => $m['label'] ?? '',
            'why' => $m['why'] ?? '',
            'frontend_feature' => $map['frontend'] ?? ($m['frontend_feature'] ?? ''),
            'admin_page' => ($m['label'] ?? '') . ($entity !== '' ? " (manage.php?entity=$entity)" : ' (index.php)'),
            'api' => $map['api'] ?? implode(', ', (array)($m['api'] ?? [])),
            'storage' => $entity !== '' ? "storage/sites/<slug>/$entity.json (+ collections mirror, admin/data_$entity.json)" : 'settings.json / content.json',
            'rules' => implode('; ', (array)($cat['business_rules'] ?? [])),
            'roles' => implode(', ', (array)($cat['roles'] ?? ['admin'])),
            'test' => $entity !== '' ? "create→list→admin mirror→update→cancel for $entity; unauthorized update 403" : 'stats reflect actual record counts',
        ];
    }

    $assumptions = [];
    if ($category === 'generic') $assumptions[] = 'Generic service model assumed from limited input; only Services + Enquiries generated. Clarify booking/payment needs to unlock more modules.';
    if (stripos($text, 'cancel') === false && preg_match('/book|reserve|appoint|order|rent/', strtolower($text))) $assumptions[] = 'Cancellation policy not stated; standard cancel→release-capacity + paid→refund_pending applied.';
    if (stripos($text, 'refund') === false && preg_match('/pay|price|fee|checkout/', strtolower($text))) $assumptions[] = 'Refund rules not stated; refunds allowed only for paid records by admin.';
    $followUp = [];
    if (preg_match('/book|reserve|appoint/', strtolower($text)) && stripos($text, 'cancel') === false) $followUp[] = 'What is the cancellation policy?';
    if (preg_match('/pay|price|fee/', strtolower($text)) && stripos($text, 'refund') === false) $followUp[] = 'Are refunds allowed and under what conditions?';

    return [
        'business_name' => $name,
        'category' => $category,
        'category_label' => $cat['label'] ?? $category,
        'engine' => $engine,
        'entities' => $cat['entities'] ?? [],
        'relationships' => $cat['relationships'] ?? [],
        'workflows' => $cat['workflows'] ?? [],
        'admin_modules' => $cat['admin_modules'] ?? [],
        'functions' => $functions,
        'frontend_mappings' => $cat['frontend_mappings'] ?? [],
        'roles' => $cat['roles'] ?? ['visitor', 'customer', 'admin'],
        'business_rules' => $cat['business_rules'] ?? [],
        'dashboard_stats' => $cat['dashboard_stats'] ?? [],
        'privacy' => $cat['privacy'] ?? null,
        'sensitive' => !empty($cat['sensitive']),
        'assumptions' => $assumptions,
        'follow_up' => $followUp,
        'analyzed_at' => date('Y-m-d H:i:s'),
    ];
}

}
