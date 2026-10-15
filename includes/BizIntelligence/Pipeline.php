<?php
// includes/BizIntelligence/Pipeline.php — Business Understanding Pipeline (A–G)
// General reasoning -> domain understanding -> capability composition ->
// custom reqs -> validation -> canonical spec. Gemini staged prompts when
// key configured; deterministic heuristic fallback otherwise (never fake success).
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Capabilities.php';
require_once __DIR__ . '/../BusinessSpec.php';

function biz_collect_text(array $d): string {
    $parts = [
        $d['description'] ?? '', $d['biz_name'] ?? '', $d['biz_type'] ?? '',
        $d['biz_tagline'] ?? '', $d['biz_audience'] ?? '', $d['biz_services'] ?? '',
        $d['biz_products'] ?? '', $d['biz_reviews'] ?? '',
    ];
    return trim(implode("\n", array_filter(array_map('trim', $parts))));
}

// Stage A signals: operational inference from meaning, not just keywords.
// Uses verb/object patterns (sell, reserve, schedule, manage, enroll...) plus
// entity hints, so bus seats vs doctor slots vs equipment rental diverge later.
function biz_infer_signals(string $text): array {
    $t = strtolower($text);
    $has = function($words) use ($t) {
        foreach ((array)$words as $w) if (strpos($t, strtolower($w)) !== false) return true;
        return false;
    };
    return [
        'sell_goods' => $has(['sell','store','shop','product','order online','checkout','cart','inventory','fashion','grocery','electronics']),
        'sell_services' => $has(['service','repair','salon','consult','course','enroll','project','quotation','client']),
        'reservable' => $has(['book','reserve','appointment','schedule','seat','ticket','slot','rent','table','room','trip','tour']),
        'take_payments' => $has(['pay','price','fee','checkout','order','subscription','rent','ticket']),
        'accounts' => $has(['account','login','register','dashboard','my ','enroll','member','student','customer portal']),
        'recurring' => $has(['subscription','membership','monthly','plan']),
        'manage_ops' => $has(['manage','admin','staff','dashboard','report','track']) || true, // owner ops default
        'notify' => $has(['notif','email','remind','confirm','alert']) || true,
        'reviews' => $has(['review','rating','testimonial','feedback']),
        'approvals' => $has(['approv','quotat','verif','moderation']),
        'documents' => $has(['document','quotat','ticket','qr','invoice','contract','certificate']),
    ];
}

// Gemini staged analysis: 3 short structured calls (understand -> entities/workflows -> spec).
// Returns [ok, partial[], modelUsed|null, errors[]]. Never throws.
function biz_gemini_staged(string $text, array $wizard): array {
    if (!file_exists(__DIR__ . '/../GeminiService.php')) return [false, [], null, ['no gemini service']];
    require_once __DIR__ . '/../GeminiService.php';
    if (!function_exists('gemini_chat_once') || gemini_api_key() === '') return [false, [], null, ['no api key']];
    $model = 'gemini-3.5-flash-lite'; // verified supported; never silently switch
    $out = []; $errs = [];
    $stages = [
        'understand' => "Analyze this business description. Return JSON {purpose, offerings[], stakeholders[], revenue, journeys[], constraints[], explicit[], implied[]} only.\n\nDESC:\n" . substr($text, 0, 3000),
        'model' => "From the same business, return JSON {entities[{key,name,fields[],relations[],lifecycle[]}], actors[{role,actions[]}], workflows[{key,steps[],states[],failures[]}]} only.\n\nDESC:\n" . substr($text, 0, 3000),
    ];
    foreach ($stages as $k => $prompt) {
        [$ok, $txt, $err] = gemini_chat_once($model, 'You are a business analyst. Output valid JSON only. No markdown.', $prompt, 0.4, 1500, 20);
        if (!$ok) { $errs[] = $k . ': ' . $err; continue; }
        $j = json_decode(preg_replace('/^```json|^```|```$/m', '', trim($txt)), true);
        if (!is_array($j)) { $errs[] = $k . ': bad json'; continue; }
        $out[$k] = $j;
    }
    return [!empty($out), $out, $model, $errs];
}

// Main entry: wizard snapshot (+ optional free-text description) -> canonical spec
function biz_build_spec(array $wizard, string $freeText = ''): array {
    $text = $freeText !== '' ? $freeText : biz_collect_text($wizard);
    $name = trim((string)($wizard['biz_name'] ?? 'Business')) ?: 'Business';
    $spec = biz_blank_spec(['business_name' => $name, 'description' => substr($text, 0, 2000)]);

    // Legacy heuristic gives baseline domain/entities (kept as fallback layer)
    $legacy = business_spec_analyze($wizard);
    $signals = biz_infer_signals($text);
    $caps = biz_select_capabilities($signals);
    $spec['capabilities'] = $caps;

    // Gemini staged reasoning enriches (proposes + explains; app enforces)
    [$aiOk, $ai, $aiModel] = biz_gemini_staged($text, $wizard);
    $spec['meta']['engine'] = $aiOk ? 'biz-intel/1+gemini-3.5-flash-lite' : 'biz-intel/1+heuristic';
    if ($aiOk) $spec['meta']['ai_stages'] = array_keys($ai);

    // --- Requirements (explicit vs inferred vs recommended, with confidence) ---
    $n = 0;
    $add = function($desc, $cat, $pri, $src, $conf, $reason, $deps = [], $val = '', $unres = null) use (&$spec, &$n) {
        $n++;
        biz_add_requirement($spec, 'REQ', $desc, $cat, $pri, $src, $conf, $reason, $deps, $val, $unres);
    };
    $add('Public landing page presenting ' . $name, 'functional', 'must', 'explicit', 0.95, 'Stated business needs a web presence', [], 'Landing renders with business name + contact');
    if (in_array('catalog', $caps)) $add('Browsable offerings with search/filter and detail view', 'functional', 'must', 'inferred', 0.8, 'Sell signals detected', [], 'List + detail reachable; filters narrow results');
    if (in_array('booking', $caps)) {
        $add('Availability-checked reservation flow with hold expiry', 'functional', 'must', 'inferred', 0.85, 'Reservable resource detected', [], 'Double-reserve same unit rejected; expired holds release');
        $add('Cancellation with eligibility check and capacity release', 'functional', 'must', 'inferred', 0.8, 'Reservation lifecycle requires reversal path', [], 'Cancelled record inactive; capacity reusable');
    }
    if (in_array('orders', $caps)) $add('Order lifecycle: create→pay→fulfill→history with idempotent retry', 'functional', 'must', 'inferred', 0.8, 'Goods sales detected', [], 'Duplicate submit yields single order');
    if (in_array('payments', $caps)) $add('Payment recording verified server-side; QR/reference is identifier only', 'functional', 'must', 'inferred', 0.85, 'Payments require proof separation', [], 'Unpaid booking never shows paid; duplicate confirm rejected', 'Refund/counter-pay deadline policy');
    if (in_array('identity', $caps)) $add('Customer accounts with owner-only record access enforced in backend', 'security', 'must', 'inferred', 0.8, 'Accounts/dashboard signals', [], 'User A cannot read user B records (backend test)');
    $add('Contact/inquiry capture with validation and owner notification', 'functional', 'should', 'recommended', 0.7, 'Standard for service discovery', [], 'Invalid email rejected; valid stored');
    $add('Admin operations dashboard for owner-authorized records', 'operational', in_array('booking', $caps) || in_array('orders', $caps) ? 'must' : 'should', 'inferred', 0.75, 'Operational management signals', [], 'Non-admin blocked from admin APIs');

    // --- Entities: legacy baseline + AI-proposed domain entities (dedupe) ---
    $seen = [];
    $pushE = function($key, $nm, $label, $fields, $purpose = '', $life = [], $src = 'inferred') use (&$spec, &$seen) {
        $k = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $key));
        if (isset($seen[$k])) return;
        $seen[$k] = true;
        $spec['entities'][] = ['key' => $k, 'name' => $nm, 'label' => $label, 'purpose' => $purpose,
            'fields' => $fields, 'relations' => [], 'ownership' => 'owner-or-admin',
            'crud' => ['create' => 'customer|staff', 'read' => 'owner-or-admin', 'update' => 'staff|admin', 'delete' => 'admin'],
            'lifecycle' => $life, 'validation' => ['required fields present'], 'effects' => [], 'source' => $src];
    };
    foreach (($legacy['entities'] ?? []) as $e) {
        $pushE($e['name'] ?? 'item', $e['name'] ?? 'item', $e['label'] ?? 'Items', $e['fields'] ?? ['name:text*'], 'Baseline from business analysis', [], 'inferred');
    }
    // AI-proposed entities (validated shape only, never exec)
    if (!empty($ai['model']['entities']) && is_array($ai['model']['entities'])) {
        foreach (array_slice($ai['model']['entities'], 0, 8) as $e) {
            if (!is_array($e) || empty($e['name'])) continue;
            $f = is_array($e['fields'] ?? null) ? array_slice(array_map('strval', $e['fields']), 0, 12) : ['name:text*'];
            $pushE($e['key'] ?? $e['name'], (string)$e['name'], (string)($e['name']), $f, 'AI-proposed domain entity', is_array($e['lifecycle'] ?? null) ? $e['lifecycle'] : [], 'inferred');
        }
    }
    if (empty($spec['entities'])) $pushE('inquiries', 'inquiries', 'Inquiries', ['name:text*', 'email:email', 'message:textarea'], 'Fallback contact capture');

    // --- Actors ---
    $spec['actors'] = [
        ['role' => 'visitor', 'description' => 'Public browser', 'actions' => ['browse', 'submit inquiry'], 'resources' => ['public pages'], 'restrictions' => ['no private records']],
        ['role' => 'customer', 'description' => 'Authenticated buyer/booker', 'actions' => ['create own records', 'view own history', 'cancel own eligible'], 'resources' => ['own records'], 'restrictions' => ['no others records', 'no admin APIs']],
        ['role' => 'staff', 'description' => 'Operational staff', 'actions' => ['verify', 'confirm', 'record payment'], 'resources' => ['assigned records'], 'restrictions' => ['role-gated transitions']],
        ['role' => 'admin', 'description' => 'Owner', 'actions' => ['full CRUD', 'reports', 'settings'], 'resources' => ['all project records'], 'restrictions' => ['project-scoped only']],
    ];
    if (!in_array('identity', $caps)) { // no accounts: drop customer actor
        $spec['actors'] = array_values(array_filter($spec['actors'], fn($a) => $a['role'] !== 'customer'));
    }

    // --- Workflows (state machines with failures, idempotency) ---
    if (in_array('booking', $caps)) {
        $spec['workflows'][] = ['key' => 'reservation', 'name' => 'Reservation lifecycle',
            'trigger' => 'customer selects unit', 'steps' => ['search', 'select', 'hold (15-min expiry)', 'details', 'confirm', 'QR reference', 'pay (online|counter)', 'use|verify', 'cancel?'],
            'transitions' => ['held' => ['awaiting_payment', 'expired'], 'awaiting_payment' => ['confirmed', 'cancelled', 'expired'], 'confirmed' => ['cancelled', 'completed']],
            'rejected' => ['pay cancelled/expired', 'double-confirm paid', 'reuse cancelled as active'],
            'failures' => ['hold expires→release', 'conflict→reject with reason'], 'idempotency' => 'idempotency_key dedupe',
            'notifications' => ['confirm', 'cancel'], 'audit' => ['status + payment transitions with timestamps']];
    }
    if (in_array('orders', $caps)) {
        $spec['workflows'][] = ['key' => 'order', 'name' => 'Order lifecycle',
            'trigger' => 'cart checkout', 'steps' => ['cart', 'checkout', 'pay', 'fulfill', 'history', 'cancel/refund?'],
            'transitions' => ['pending' => ['paid', 'cancelled'], 'paid' => ['shipped', 'refunded'], 'shipped' => ['delivered']],
            'rejected' => ['fulfill unpaid', 'refund uncollected'], 'failures' => ['payment fail→stay pending'],
            'idempotency' => 'idempotency_key dedupe', 'notifications' => ['order confirm'], 'audit' => ['order events']];
    }
    $spec['workflows'][] = ['key' => 'inquiry', 'name' => 'Inquiry handling',
        'trigger' => 'form submit', 'steps' => ['validate', 'store', 'notify owner'],
        'transitions' => ['new' => ['read', 'archived']], 'rejected' => ['invalid email', 'spam honeypot'],
        'failures' => ['store fail→error message'], 'idempotency' => 'none', 'notifications' => ['owner'], 'audit' => ['inbox record']];

    // --- Pages / backend / nonfunctional / tests ---
    $spec['pages'] = [
        ['route' => '/', 'title' => $name, 'sections' => ['hero', 'offerings', 'trust', 'contact'], 'forms' => [], 'components' => []],
    ];
    if (in_array('catalog', $caps)) $spec['pages'][] = ['route' => '/shop', 'title' => 'Browse', 'sections' => ['grid', 'filters'], 'forms' => ['search'], 'components' => ['product-card']];
    if (in_array('booking', $caps)) $spec['pages'][] = ['route' => '/book', 'title' => 'Book', 'sections' => ['search', 'availability', 'form', 'ticket'], 'forms' => ['booking'], 'components' => ['availability-grid', 'qr-ticket']];
    if (in_array('identity', $caps)) $spec['pages'][] = ['route' => '/account', 'title' => 'My account', 'sections' => ['history'], 'forms' => ['login', 'register'], 'components' => []];
    $spec['pages'][] = ['route' => '/admin', 'title' => 'Manage', 'sections' => ['dashboard'], 'forms' => [], 'components' => ['entity-table']];

    $spec['backend']['tables'] = array_map(fn($e) => ['name' => $e['key'], 'fields' => $e['fields']], $spec['entities']);
    $spec['backend']['apis'] = [
        ['method' => 'POST', 'path' => '/api/business-ops.php?action=create', 'auth' => 'public-with-validation'],
        ['method' => 'POST', 'path' => '/api/business-ops.php?action=list', 'auth' => 'owner-or-admin'],
        ['method' => 'POST', 'path' => '/api/business-ops.php?action=cancel', 'auth' => 'owner-or-admin+eligibility'],
        ['method' => 'POST', 'path' => '/api/business-ops.php?action=pay', 'auth' => 'staff|admin, verified server-side'],
    ];
    $spec['backend']['transactions'] = ['reserve-then-confirm atomic', 'cancel releases capacity in same write'];
    $spec['backend']['authz'] = ['backend-enforced per-record ownership', 'project isolation by slug'];
    $spec['nonfunctional'] = ['performance' => ['list < 2s', 'no N+1 on availability'],
        'accessibility' => ['labels', 'focus states', '44px targets', 'keyboard flows'],
        'security' => ['prepared statements/file locks', 'no secrets in browser', 'no client-only success'],
        'privacy' => ['customers see only own records']];
    $t = 0;
    $addT = function($sc, $steps, $exp) use (&$spec, &$t) { $t++; $spec['tests'][] = ['id' => 'T' . str_pad((string)$t, 2, '0', STR_PAD_LEFT), 'scenario' => $sc, 'steps' => $steps, 'expect' => $exp]; };
    $addT('happy booking/order', ['create', 'confirm', 'pay'], 'confirmed + paid, visible in history');
    $addT('double-book guard', ['hold seat A1 twice'], 'second rejected');
    $addT('expiry', ['hold then wait 15min'], 'released, re-bookable');
    $addT('cancel', ['cancel confirmed'], 'inactive + capacity released');
    $addT('duplicate pay', ['pay same ref twice'], 'second rejected');
    $addT('authz', ['user A reads user B record'], '403');

    // --- Uncertainty: explicit vs inferred vs unresolved ---
    $spec['uncertainty']['confirmed'] = [$text !== '' ? 'Customer description received' : 'No description'];
    $spec['uncertainty']['inferred'] = array_map(fn($c) => 'Capability: ' . $c, $caps);
    $spec['uncertainty']['assumptions'] = $legacy['assumptions'] ?? [];
    $u = $legacy['follow_up'] ?? [];
    if (in_array('booking', $caps)) { $u[] = 'Reservation hold duration? (default 15 min)'; $u[] = 'Payment deadline / refund policy?'; }
    $spec['uncertainty']['unresolved'] = array_values(array_unique($u));

    // Domain label + compat shim for existing generator
    $spec['profile']['domain'] = $legacy['business_type'] ?? 'services';
    $spec['profile']['business_model'] = $legacy['business_model'] ?? 'services';
    $spec['compat'] = ['gen_mode' => $legacy['gen_mode'] ?? 'admin',
        'pages' => $legacy['pages'] ?? ['home'], 'features' => $legacy['features'] ?? [],
        'admin_modules' => $legacy['admin_modules'] ?? [], 'needs_admin' => !empty($spec['entities'])];

    return $spec;
}
