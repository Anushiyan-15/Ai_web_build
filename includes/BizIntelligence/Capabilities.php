<?php
// includes/BizIntelligence/Capabilities.php — Reusable capability modules.
// Composable, never mandatory. Each: entities, workflows, pages, apis,
// roles, tests. Intelligence selects + combines; custom layer covers the rest.

function biz_capability_catalog(): array {
    return [
        'identity' => ['label' => 'Identity & access', 'provides' => ['customer accounts','login','roles','owner-only records']],
        'catalog' => ['label' => 'Products & services catalog', 'provides' => ['listings','search/filter','detail pages']],
        'capacity' => ['label' => 'Resource & capacity', 'provides' => ['resources','availability calculation','concurrency guard']],
        'scheduling' => ['label' => 'Scheduling & availability', 'provides' => ['slots','holds with expiry','conflict prevention']],
        'booking' => ['label' => 'Bookings & reservations', 'provides' => ['reserve','confirm','cancel','release','QR reference']],
        'orders' => ['label' => 'Orders & transactions', 'provides' => ['cart','checkout','status flow','history']],
        'payments' => ['label' => 'Payments & refunds', 'provides' => ['pay record','refund workflow','no client-only success']],
        'subscriptions' => ['label' => 'Subscriptions', 'provides' => ['plans','renewal','cancellation']],
        'inventory' => ['label' => 'Inventory & fulfillment', 'provides' => ['stock','deduct on confirm','restore on cancel']],
        'notify' => ['label' => 'Notifications', 'provides' => ['email/in-app on key transitions']],
        'reviews' => ['label' => 'Reviews & feedback', 'provides' => ['ratings','moderation']],
        'reports' => ['label' => 'Reporting & analytics', 'provides' => ['dashboard metrics','activity history']],
        'approvals' => ['label' => 'Workflow & approvals', 'provides' => ['multi-step states','role-gated transitions']],
        'documents' => ['label' => 'Documents', 'provides' => ['quotations','tickets','records with audit']],
    ];
}

// ── Capability detail registry (extensible; additive to the id catalog).
// Each detail: prerequisites[], workflow, admin[], data (entities/fields),
// integrations[] (configurable adapters, never hardcoded secrets).
function biz_capability_detail(string $id): ?array {
    static $d = null;
    if ($d === null) $d = [
        'identity' => ['prerequisites' => [], 'workflow' => 'register → verify → sign in → own-records only',
            'admin' => ['customer accounts', 'role assignment'], 'data' => ['customers(name,email,phone,hash)'],
            'integrations' => ['email OTP (configurable)']],
        'catalog' => ['prerequisites' => [], 'workflow' => 'browse → search/filter → detail',
            'admin' => ['items', 'categories', 'media'], 'data' => ['items(name,price,photo,category)'],
            'integrations' => []],
        'capacity' => ['prerequisites' => ['catalog or resource list'], 'workflow' => 'availability computed from authoritative records',
            'admin' => ['resources', 'capacity rules'], 'data' => ['resources(unit,total)'], 'integrations' => []],
        'scheduling' => ['prerequisites' => ['capacity'], 'workflow' => 'slot search → hold with expiry → confirm',
            'admin' => ['schedules', 'blackout rules'], 'data' => ['slots(date,time,status)'], 'integrations' => []],
        'booking' => ['prerequisites' => ['scheduling', 'identity?'], 'workflow' => 'hold → details → confirm → QR → pay → cancel/release',
            'admin' => ['bookings', 'verification', 'refunds'], 'data' => ['bookings(ref,seats,status,payment_status)'],
            'integrations' => ['notifications']],
        'orders' => ['prerequisites' => ['catalog'], 'workflow' => 'cart → checkout → pay → fulfill → history',
            'admin' => ['orders', 'fulfillment'], 'data' => ['orders(items,total,status)'], 'integrations' => ['payments']],
        'payments' => ['prerequisites' => ['orders or booking'], 'workflow' => 'record server-side → confirm → refund per policy',
            'admin' => ['payments', 'refunds'], 'data' => ['payments(ref,method,amount,status)'],
            'integrations' => ['payment provider (keys via site settings, never invented)']],
        'subscriptions' => ['prerequisites' => ['payments', 'identity'], 'workflow' => 'subscribe → renew → cancel',
            'admin' => ['plans', 'subscribers'], 'data' => ['subscriptions(plan,status,renews)'], 'integrations' => ['payments']],
        'inventory' => ['prerequisites' => ['catalog'], 'workflow' => 'deduct on confirm → restore on cancel',
            'admin' => ['stock levels'], 'data' => ['inventory(sku,qty)'], 'integrations' => []],
        'notify' => ['prerequisites' => [], 'workflow' => 'event → queue → deliver',
            'admin' => ['templates', 'log'], 'data' => ['notifications(type,subject,status)'], 'integrations' => ['email/SMS adapters']],
        'reviews' => ['prerequisites' => [], 'workflow' => 'submit → moderate → publish',
            'admin' => ['review moderation'], 'data' => ['reviews(name,rating,text)'], 'integrations' => []],
        'reports' => ['prerequisites' => [], 'workflow' => 'aggregate → dashboard',
            'admin' => ['metrics', 'activity history'], 'data' => [], 'integrations' => []],
        'approvals' => ['prerequisites' => [], 'workflow' => 'submit → review → approve/reject',
            'admin' => ['approval queue'], 'data' => ['requests(status,reviewer)'], 'integrations' => []],
        'documents' => ['prerequisites' => [], 'workflow' => 'generate → store → retrieve (QR reference)',
            'admin' => ['documents'], 'data' => ['documents(ref,kind,payload)'], 'integrations' => []],
        // Extended industries (same engine, new detail rows):
        'hospitality' => ['prerequisites' => ['scheduling'], 'workflow' => 'search rooms → reserve → check-in/out → bill',
            'admin' => ['rooms', 'reservations', 'billing'], 'data' => ['rooms(type,rate)', 'stays(dates,status)'], 'integrations' => ['payments']],
        'courses' => ['prerequisites' => ['catalog'], 'workflow' => 'browse courses → enroll → track progress',
            'admin' => ['courses', 'enrollments', 'tutors'], 'data' => ['courses(title,fee)', 'enrollments(student,status)'], 'integrations' => ['payments?']],
    ];
    return $d[$id] ?? null;
}

// All known capability ids (catalog + extended details).
function biz_capability_ids(): array {
    $ids = array_keys(biz_capability_catalog());
    foreach (['hospitality', 'courses'] as $x) { if (!in_array($x, $ids, true)) $ids[] = $x; }
    return $ids;
}
// ── Business manifest: per-site operating context (entities, workflows,
// admin modules, capabilities) for the Node API + AI feature builder.
// Works from canonical (biz-spec/1) or legacy business specs. Unknown
// industries fall back to a generic profile — never a fixed module list.
function biz_detect_category_text(string $text): string {
    $t = strtolower($text);
    $hit = function (...$words) use ($t) {
        foreach ($words as $w) { if (strpos($t, $w) !== false) return true; }
        return false;
    };
    if ($hit('bus', 'coach', 'ticket', 'seat', 'transport', 'travel', 'tour', 'logistic')) return 'bus';
    if ($hit('shop', 'store', 'product', 'cart', 'ecommerce', 'e-commerce', 'retail', 'fashion', 'boutique', 'grocery')) return 'ecommerce';
    if ($hit('restaurant', 'food', 'menu', 'pizza', 'cafe', 'bakery', 'dining')) return 'restaurant';
    if ($hit('hotel', 'room', 'stay', 'resort', 'villa', 'accommodation')) return 'hotel';
    if ($hit('salon', 'beauty', 'spa', 'barber', 'nail')) return 'salon';
    if ($hit('clinic', 'doctor', 'dental', 'hospital', 'patient', 'physio')) return 'clinic';
    if ($hit('propert', 'real estate', 'apartment')) return 'realestate';
    if ($hit('course', 'school', 'tuition', 'training', 'academy', 'tutor')) return 'education';
    if ($hit('event', 'concert', 'wedding', 'conference', 'exhibition')) return 'events';
    if ($hit('portfolio', 'agency', 'design', 'photograph', 'freelance')) return 'portfolio';
    if ($hit('rent', 'hire', 'lease', 'equipment')) return 'rental';
    if ($hit('car', 'auto', 'garage', 'vehicle')) return 'automotive';
    if ($hit('member', 'subscription', 'gym', 'fitness', 'club')) return 'subscription';
    return 'generic';
}

function biz_business_manifest(array $spec, string $slug, string $bizName = ''): array {
    $prof = $spec['profile'] ?? [];
    $text = implode(' ', [(string)($prof['business_name'] ?? ''), (string)($prof['description'] ?? ''),
        (string)($prof['domain'] ?? ''), (string)($prof['business_model'] ?? '')]);
    $category = biz_detect_category_text($text);
    $entities = [];
    foreach (array_slice((array)($spec['entities'] ?? []), 0, 12) as $e) {
        if (!is_array($e)) continue;
        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)($e['key'] ?? $e['name'] ?? '')));
        $key = trim($key, '_');
        if ($key === '') continue;
        $fl = [];
        foreach (array_slice((array)($e['fields'] ?? []), 0, 15) as $f) {
            $fl[] = is_string($f) ? $f : (string)($f['name'] ?? '');
        }
        $entities[] = ['id' => $key, 'name' => (string)($e['label'] ?? $e['name'] ?? $key),
            'fields' => array_values(array_filter($fl))];
    }
    $workflows = [];
    foreach (array_slice((array)($spec['workflows'] ?? []), 0, 10) as $w) {
        if (!is_array($w)) continue;
        $workflows[] = ['key' => (string)($w['key'] ?? ''), 'name' => (string)($w['name'] ?? ''),
            'steps' => array_slice((array)($w['steps'] ?? []), 0, 12)];
    }
    // Admin modules: category profile first (what THIS business operates),
    // then capability registry, then entity names. Relevant-only, deduped.
    $mods = biz_category_modules($category);
    foreach ((array)($spec['capabilities'] ?? []) as $c) {
        $d = biz_capability_detail((string)$c);
        if ($d && !empty($d['admin'])) foreach ((array)$d['admin'] as $m) $mods[] = (string)$m;
    }
    foreach ($entities as $e) $mods[] = $e['name'];
    $mods = array_values(array_unique(array_filter($mods)));
    return ['slug' => $slug, 'business' => $bizName !== '' ? $bizName : ($prof['business_name'] ?? $slug),
        'category' => $category, 'entities' => $entities, 'workflows' => $workflows,
        'adminModules' => array_slice($mods, 0, 20), 'capabilities' => array_values((array)($spec['capabilities'] ?? [])),
        'updated_at' => date('c')];
}
// Per-category admin modules: what each business actually operates.
// Capability admin entries + entity names are appended after these.
function biz_category_modules(string $category): array {
    static $m = [
        'bus' => ['Trips & schedules', 'Bookings', 'Payments', 'Buses & seats', 'Tickets'],
        'ecommerce' => ['Products', 'Orders', 'Customers', 'Coupons'],
        'restaurant' => ['Menu', 'Orders', 'Reservations'],
        'hotel' => ['Rooms', 'Reservations', 'Billing'],
        'salon' => ['Services', 'Staff', 'Appointments'],
        'clinic' => ['Doctors', 'Appointments', 'Patients'],
        'realestate' => ['Properties', 'Inquiries'],
        'education' => ['Courses', 'Enrollments', 'Tutors'],
        'events' => ['Events', 'Tickets'],
        'portfolio' => ['Projects', 'Inquiries'],
        'rental' => ['Units', 'Rentals'],
        'automotive' => ['Vehicles', 'Service jobs'],
        'subscription' => ['Plans', 'Subscribers'],
        'generic' => ['Services', 'Inquiries'],
    ];
    return $m[$category] ?? $m['generic'];
}
function biz_select_capabilities(array $signals): array {    $out = [];
    if (!empty($signals['accounts'])) $out[] = 'identity';
    if (!empty($signals['sell_goods']) || !empty($signals['sell_services'])) $out[] = 'catalog';
    if (!empty($signals['reservable'])) { $out[] = 'capacity'; $out[] = 'scheduling'; $out[] = 'booking'; }
    if (!empty($signals['sell_goods'])) { $out[] = 'orders'; $out[] = 'inventory'; }
    if (!empty($signals['take_payments'])) $out[] = 'payments';
    if (!empty($signals['recurring'])) $out[] = 'subscriptions';
    if (!empty($signals['notify'])) $out[] = 'notify';
    if (!empty($signals['reviews'])) $out[] = 'reviews';
    if (!empty($signals['manage_ops'])) $out[] = 'reports';
    if (!empty($signals['approvals'])) $out[] = 'approvals';
    if (!empty($signals['documents'])) $out[] = 'documents';
    return array_values(array_unique($out));
}
