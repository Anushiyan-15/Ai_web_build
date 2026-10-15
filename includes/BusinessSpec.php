<?php
// includes/BusinessSpec.php — Universal Business Understanding Engine
// Analyzes wizard snapshot, infers business model, entities, pages,
// features, admin modules, DB tables, APIs, roles, workflows.
// Rule-based + Gemini assist (optional). No hardcoded industry lock-in:
// unknown businesses fall back to generic entity inference.

function business_spec_analyze(array $d): array {
    $name = trim((string)($d['biz_name'] ?? 'Business'));
    $type = trim((string)($d['biz_type'] ?? ''));
    $tag  = trim((string)($d['biz_tagline'] ?? ''));
    $aud  = trim((string)($d['biz_audience'] ?? ''));
    $svc  = trim((string)($d['biz_services'] ?? ''));
    $prod = trim((string)($d['biz_products'] ?? ''));
    $rev  = trim((string)($d['biz_reviews'] ?? ''));
    $sections = array_map('strtolower', (array)($d['sections'] ?? []));
    $blob = strtolower($name.' '.$type.' '.$tag.' '.$aud.' '.$svc.' '.$prod);

    $has = function(array $keys) use ($blob, $sections): bool {
        foreach ($keys as $k) {
            if (strpos($blob, strtolower($k)) !== false) return true;
            if (in_array(strtolower($k), $sections, true)) return true;
        }
        return false;
    };

    // Score business archetypes
    $scores = [
        'ecommerce'  => 0, 'booking' => 0, 'restaurant' => 0,
        'clinic' => 0, 'school' => 0, 'gym' => 0,
        'realestate' => 0, 'services' => 0, 'agency' => 0,
    ];
    $lex = [
        'ecommerce'  => ['shop','store','product','retail','ecommerce','e-commerce','boutique','mart','fashion','jewelry','grocery','bakery','furniture','electronics','cart','checkout','order','inventory'],
        'booking'    => ['bus','coach','booking','book','trip','travel','seat','ticket','route','schedule','appointment','reservation','rent','taxi','tour','hotel','event'],
        'restaurant' => ['restaurant','cafe','food','menu','dish','dining','order food','table','cuisine','bakery','pizza','burger'],
        'clinic'     => ['clinic','hospital','doctor','patient','appointment','dental','physio','therapy','health','medical'],
        'school'     => ['school','student','teacher','class','course','coaching','tuition','college','academy','admission','fee'],
        'gym'        => ['gym','fitness','yoga','trainer','workout','membership','diet'],
        'realestate' => ['real estate','property','plot','villa','apartment','land','agent','rent','lease'],
        'services'   => ['service','repair','salon','spa','plumber','electrician','cleaning','consultant','coach','lawyer','agency'],
        'agency'     => ['agency','studio','software','design','marketing','portfolio','startup','saas','tech'],
    ];
    foreach ($lex as $k => $words) {
        foreach ($words as $w) { if (strpos($blob, $w) !== false) $scores[$k]++; }
    }
    // Raw winner BEFORE the products bonus: a booking-style business listing
    // trips/routes (or a clinic/school/restaurant listing offerings) in the
    // products box is a catalog, not a shop — never let that flip it to ecommerce.
    arsort($scores);
    $rawTop = key($scores);
    $bookingLike = in_array($rawTop, ['booking', 'restaurant', 'clinic', 'school', 'gym', 'realestate'], true);
    if ($prod !== '' && !$bookingLike) $scores['ecommerce'] += 3;
    if (in_array('shop', $sections, true)) $scores['ecommerce'] += 2;

    arsort($scores);
    $top = key($scores);
    if (current($scores) === 0) $top = 'services'; // generic fallback

    // Generic entity inference from services/products lines
    $entities = [];
    $pushEntity = function(string $n, array $fields, string $label = '') use (&$entities) {
        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $n));
        if (isset($entities[$key])) return;
        $entities[$key] = ['name' => $n, 'label' => $label ?: ucfirst($n), 'fields' => $fields];
    };

    $shopTypeHint = (bool)preg_match('/shop|store|product|retail|e-?commerce|boutique|mart|fashion|jewelry|grocery|bakery|furniture|electronics/i', $type);
    $isBookingTop = in_array($top, ['booking', 'restaurant', 'clinic', 'school', 'gym', 'realestate'], true);
    // Shop ONLY for real goods sellers: explicit shop section, shop-like
    // industry, ecommerce winner, or products text on a non-booking business.
    // A booking winner's products box holds trips/routes (catalog, not cart).
    $needsShop = in_array('shop', $sections, true) || $shopTypeHint || $top === 'ecommerce'
        || ($prod !== '' && !$isBookingTop);
    $needsBooking = $has(['booking','appointment','reservation','bus','trip','seat','ticket']) || $top === 'booking' || $top === 'clinic';
    $needsPayments = $needsShop || $needsBooking || $has(['payment','checkout','pay','price','fee']);
    $needsAuth = $needsShop || $needsBooking || $has(['login','account','dashboard','member']);

    // Archetype-specific entities/pages/features
    $pages = ['home']; $features = []; $adminModules = []; $tables = []; $apis = [];
    $roles = ['visitor'];
    if ($needsAuth) $roles[] = 'customer';
    $roles[] = 'staff'; $roles[] = 'admin';

    switch ($top) {
        case 'ecommerce':
            $pushEntity('products', ['name:text*','category:text','price:number*','stock:number','description:textarea','photo:url'], 'Products');
            $pushEntity('orders', ['customer:text*','items:json*','total:number*','status:select:pending|paid|shipped|delivered|cancelled','created:text'], 'Orders');
            $pushEntity('customers', ['name:text*','phone:text','email:email','address:text'], 'Customers');
            $pages = array_merge($pages, ['shop','product-detail','cart','checkout','order-history','contact']);
            $features = ['product_grid','search_filter','cart','checkout','order_tracking','contact_form'];
            $adminModules = ['dashboard','products','orders','customers','inquiries','reports','settings'];
            break;
        case 'booking': // bus booking reference workflow
            $pushEntity('trips', ['route:text*','date:date*','departure:text*','price:number*','total_seats:number*'], 'Trips');
            $pushEntity('bookings', ['trip_id:text*','seats:json*','passenger:text*','phone:text*','status:select:held|awaiting_payment|confirmed|cancelled|expired','payment_status:select:unpaid|paid|refunded','ref:text*','qr:text'], 'Bookings');
            $pushEntity('payments', ['booking_ref:text*','method:select:counter|online','amount:number*','status:select:pending|completed|refunded'], 'Payments');
            $pages = array_merge($pages, ['search','trip-detail','seat-select','booking','ticket','my-bookings','contact']);
            $features = ['trip_search','seat_map','hold_seats','passenger_form','qr_ticket','cancel_booking','pay_at_counter','contact_form'];
            $adminModules = ['dashboard','trips','bookings','payments','customers','reports','settings'];
            break;
        case 'restaurant':
            $pushEntity('menu_items', ['name:text*','category:text','price:number*','description:textarea','photo:url','available:bool'], 'Menu');
            $pushEntity('reservations', ['name:text*','phone:text*','date:date*','time:text*','guests:number*','status:select:pending|confirmed|cancelled'], 'Reservations');
            $pushEntity('orders', ['customer:text*','items:json*','total:number*','status:select:pending|preparing|ready|completed'], 'Orders');
            $pages = array_merge($pages, ['menu','reserve','order','contact']);
            $features = ['menu_list','table_reservation','food_ordering','contact_form'];
            $adminModules = ['dashboard','menu_items','reservations','orders','inquiries','settings'];
            break;
        case 'clinic':
            $pushEntity('doctors', ['name:text*','specialization:text','phone:text','email:email'], 'Doctors');
            $pushEntity('appointments', ['patient:text*','doctor:text*','date:date*','time:text*','status:select:scheduled|completed|cancelled'], 'Appointments');
            $pushEntity('patients', ['name:text*','age:number','phone:text*','notes:textarea'], 'Patients');
            $pages = array_merge($pages, ['doctors','book-appointment','my-appointments','contact']);
            $features = ['doctor_list','appointment_booking','appointment_history','contact_form'];
            $adminModules = ['dashboard','doctors','appointments','patients','inquiries','settings'];
            break;
        case 'school':
            $pushEntity('students', ['name:text*','class:text','phone:text','address:text'], 'Students');
            $pushEntity('teachers', ['name:text*','subject:text','phone:text'], 'Teachers');
            $pushEntity('fees', ['student:text*','amount:number*','status:select:paid|due'], 'Fees');
            $pages = array_merge($pages, ['courses','admissions','contact']);
            $features = ['course_list','admission_form','contact_form'];
            $adminModules = ['dashboard','students','teachers','fees','inquiries','settings'];
            break;
        case 'gym':
            $pushEntity('members', ['name:text*','phone:text*','plan:text','expires:date'], 'Members');
            $pushEntity('classes', ['name:text*','trainer:text','schedule:text','capacity:number'], 'Classes');
            $pages = array_merge($pages, ['plans','trainers','join','contact']);
            $features = ['plan_list','membership_form','contact_form'];
            $adminModules = ['dashboard','members','classes','inquiries','settings'];
            break;
        case 'realestate':
            $pushEntity('properties', ['title:text*','type:text','price:number*','location:text','area:text','photo:url'], 'Properties');
            $pushEntity('inquiries', ['name:text*','phone:text*','property:text','message:textarea'], 'Inquiries');
            $pages = array_merge($pages, ['listings','property-detail','contact']);
            $features = ['property_grid','enquiry_form','contact_form'];
            $adminModules = ['dashboard','properties','inquiries','settings'];
            break;
        default: // services / agency generic
            // Infer entities from service lines
            $lines = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $svc))));
            $lines = array_slice($lines, 0, 6);
            $pushEntity('services', ['name:text*','description:textarea','price:text'], 'Services');
            $pushEntity('inquiries', ['name:text*','phone:text*','email:email','message:textarea'], 'Inquiries');
            if (!empty($lines) && $needsBooking) {
                $pushEntity('appointments', ['name:text*','date:date*','time:text*','status:select:pending|confirmed|cancelled'], 'Appointments');
            }
            $pages = array_merge($pages, ['services','about','contact']);
            if ($needsBooking) $pages[] = 'book';
            $features = ['service_list','quote_form','contact_form'];
            if ($needsBooking) $features[] = 'appointment_booking';
            $adminModules = ['dashboard','services','inquiries','content','settings'];
            if ($needsBooking) $adminModules[] = 'appointments';
            break;
    }
    if ($rev !== '') { $pushEntity('reviews', ['name:text*','rating:number','text:textarea'], 'Reviews'); $features[] = 'reviews'; }

    foreach ($entities as $k => $e) {
        $tables[] = ['name' => $k, 'fields' => array_column($e['fields'], null)];
    }
    $apis = [
        ['method' => 'POST', 'path' => '/api/business-ops.php?action=create', 'desc' => 'Create record'],
        ['method' => 'POST', 'path' => '/api/business-ops.php?action=list', 'desc' => 'List records'],
        ['method' => 'POST', 'path' => '/api/business-ops.php?action=update', 'desc' => 'Update record'],
        ['method' => 'POST', 'path' => '/api/business-ops.php?action=cancel', 'desc' => 'Cancel/void record per policy'],
    ];
    if ($needsBooking) $apis[] = ['method' => 'POST', 'path' => '/api/business-ops.php?action=hold', 'desc' => 'Hold seats/slots'];
    if ($needsPayments) $apis[] = ['method' => 'POST', 'path' => '/api/business-ops.php?action=pay', 'desc' => 'Record payment'];

    // Auto gen mode (internal, never shown to customer)
    $genMode = 'static';
    if (!empty($entities) && ($needsShop || $needsBooking || count($entities) > 2)) $genMode = 'database';
    elseif (!empty($entities)) $genMode = 'admin';

    $assumptions = [];
    if ($top === 'services' && current($scores) === 0) $assumptions[] = 'Generic service model assumed; entities inferred from description.';
    $followUp = [];
    if ($needsBooking && stripos($blob, 'cancel') === false) $followUp[] = 'What is the cancellation policy?';
    if ($needsPayments && stripos($blob, 'refund') === false) $followUp[] = 'Are refunds allowed and under what conditions?';

    return [
        'business_name' => $name ?: 'Business',
        'business_type' => $top,
        'business_model' => $top,
        'scores' => $scores,
        'entities' => array_values($entities),
        'pages' => array_values(array_unique($pages)),
        'features' => array_values(array_unique($features)),
        'admin_modules' => array_values(array_unique($adminModules)),
        'tables' => $tables,
        'apis' => $apis,
        'roles' => $roles,
        'needs_shop' => $needsShop,
        'needs_booking' => $needsBooking,
        'needs_payments' => $needsPayments,
        'needs_auth' => $needsAuth,
        'needs_admin' => !empty($entities),
        'gen_mode' => $genMode,
        'workflows' => business_spec_workflows($top, $needsBooking, $needsShop, $needsPayments),
        'assumptions' => $assumptions,
        'follow_up' => $followUp,
        'analyzed_at' => date('Y-m-d H:i:s'),
    ];
}

function business_spec_workflows(string $top, bool $booking, bool $shop, bool $pay): array {
    $w = [];
    if ($shop) $w['order'] = ['browse→cart→checkout→pay→track→cancel/refund per policy'];
    if ($booking) {
        $w['booking'] = ['search→select→hold→passenger details→confirm→QR ref→pay (online|counter)→cancel per policy→release capacity'];
        $w['seat_safety'] = ['holds expire', 'no double-book same seat/trip', 'cancelled/expired never active', 'idempotent retries'];
        $w['counter_pay'] = ['select pay-at-counter→QR + awaiting-payment→staff verify→record method/amount/time→confirm→no duplicate confirm'];
    }
    $w['inquiry'] = ['submit→validate→store→notify owner'];
    return $w;
}

function business_spec_summary_text(array $spec): string {
    $l = [];
    $l[] = 'Type: ' . ($spec['business_type'] ?? '?') . ' (auto)';
    $l[] = 'Pages: ' . implode(', ', $spec['pages'] ?? []);
    $l[] = 'Features: ' . implode(', ', $spec['features'] ?? []);
    if (!empty($spec['admin_modules'])) $l[] = 'Manage: ' . implode(', ', $spec['admin_modules']);
    return implode(' | ', $l);
}
