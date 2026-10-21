<?php
// tests/test-runtime-binding.php — Regression: generated AI pages must bind to
// the REAL backend runtime, never to an AI-authored mock.
//
// Verifies:
//   1. Bus detection reads through {canonical:{...}} blueprint payloads.
//   2. AI mock window.WCBiz (fake BK-/HOLD- refs) is neutralised.
//   3. Tail injectors (form wiring, taste safety net) can never split the
//      runtime script — it stays whole and last, so the real API wins.
//   4. A "My Account" link to customer.php is injected into the navbar.
//
// Run: C:\UniServerZ\core\php83\php.exe tests/test-runtime-binding.php

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/BusinessRuntime.php';
require_once $ROOT . '/includes/PublishedAdminKit.php';
require_once $ROOT . '/includes/TasteSkill.php';

$fails = [];
$passes = 0;
function ok(string $label, bool $cond, array &$fails, int &$passes): void {
    if ($cond) { $passes++; echo "  [PASS] $label\n"; }
    else { $fails[] = $label; echo "  [FAIL] $label\n"; }
}

$html = <<<'HTML'
<!doctype html><html><head><title>City Express Travels</title></head><body>
<header><nav class="nav-links"><a href="#home">Home</a><a href="#services">Services</a><a href="#booking">Book Ticket</a></nav></header>
<main>
<section id="booking"><h2>Book Your Journey</h2>
<form data-wc-form="booking"><input name="passenger" required><input name="phone" required><input name="email" type="email"></form>
<div data-wc-history="bookings" data-wc-id="phone"></div>
<div data-wc-ticket-lookup="bookings"></div>
</section>
</main>
<script>
// Mock WCBiz Runtime API & Frontend Controller
window.WCBiz = {
  selectedSeats: [],
  async list(entity) { return [{id:'D1',origin:'Chennai',destination:'Bangalore',price:899}]; },
  async hold(entity, data) {
    await new Promise(r => setTimeout(r, 400));
    this.heldRef = 'HOLD-' + Math.random().toString(36).substring(2, 8).toUpperCase();
    return { success: true, ref: this.heldRef };
  },
  async create(entity, data) {
    await new Promise(r => setTimeout(r, 500));
    const ref = 'BK-' + Math.random().toString(36).substring(2, 8).toUpperCase();
    return { success: true, data: { ref: ref, id: '101' } };
  },
  ticketQR(ref, size) { return '<svg></svg>'; }
};
</script>
</body></html>
HTML;

$spec = [
    'gen_mode' => 'database',
    'features' => ['trip_search', 'seat_map', 'hold_seats', 'qr_ticket'],
    'pages' => ['home', 'seat-select', 'booking', 'ticket'],
    'canonical' => [
        'profile' => [
            'business_name' => 'City Express Travels',
            'domain' => 'booking',
            'description' => 'Daily bus service Chennai to Bangalore.',
        ],
        'entities' => [
            ['key' => 'trips', 'name' => 'trips', 'fields' => ['route:text*']],
            ['key' => 'bookings', 'name' => 'bookings', 'fields' => ['passenger:text*']],
        ],
    ],
];

echo "Runtime binding regression\n";

ok('bus detection reads canonical blueprint', wc_business_requires_bus_booking($spec), $fails, $passes);

// Old order (runtime first, then tail injectors) must be resilient too.
$page = business_runtime_inject($html, $spec, 'city-express');
$page = pak_wire_frontend_forms($page, []);
$page = taste_apply_safety_net($page);

ok('mock assignment removed', stripos($page, "'BK-' + Math.random") === false, $fails, $passes);
ok('mock placeholder keeps page UI state safe', stripos($page, 'window.WCBiz=window.WCBiz||{}') !== false, $fails, $passes);
ok('real runtime injected', stripos($page, 'data-wc-biz-runtime') !== false, $fails, $passes);
ok('customer portal link injected', stripos($page, 'href="customer.php"') !== false, $fails, $passes);
ok('portal link lives inside the navbar', (bool)preg_match('/<nav\b[^>]*>.*href="customer\.php".*<\/nav>/is', $page), $fails, $passes);
ok('exactly one real </body> remains', substr_count(strtolower($page), '</body>') === 1, $fails, $passes);

// Extract the runtime script and prove it was not truncated by nested tags.
if (preg_match('/<script\b[^>]*data-wc-biz-runtime\b[^>]*>(.*?)<\/script\s*>/is', $page, $rt)) {
    $js = $rt[1];
    ok('runtime script has no nested <script>', stripos($js, '<script') === false, $fails, $passes);
    ok('runtime script assigns real WCBiz', strpos($js, 'window.WCBiz=Object.assign(') !== false, $fails, $passes);
    ok('runtime script reaches its closing IIFE', strpos(rtrim($js), '})();') !== false, $fails, $passes);
    ok('runtime script still exposes real create', strpos($js, 'create:wcCreate') !== false, $fails, $passes);
} else {
    ok('runtime script extractable', false, $fails, $passes);
}

// Runtime script must be the last executable block before </body>, and no
// tail injector may land inside it (the actual historical failure).
$runtimeOpen = strpos($page, 'data-wc-biz-runtime');
$runtimeClose = strpos($page, '</script>', $runtimeOpen);
$pakAt = strpos($page, 'data-wc-pak-submit');
ok('form wiring was not injected inside the runtime script', $pakAt === false || $pakAt > $runtimeClose, $fails, $passes);

// Idempotency.
$portalBefore = substr_count($page, 'href="customer.php"');
$again = business_runtime_inject($page, $spec, 'city-express');
ok('re-injection is idempotent', substr_count($again, 'data-wc-biz-runtime') === 1, $fails, $passes);
ok('portal link not duplicated', substr_count($again, 'href="customer.php"') === $portalBefore, $fails, $passes);

echo "\n" . ($fails ? 'FAILED: ' . count($fails) . ' check(s)' : 'OK: ' . $passes . ' checks passed') . "\n";
exit($fails ? 1 : 0);
