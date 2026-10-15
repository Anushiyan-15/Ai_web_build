<?php
// TEMP repro/verify: bus business must NEVER get shop cart; shop still works.
error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(__DIR__);
$GLOBALS['BUSINESS_ANALYZER_NO_AI'] = true;
require $ROOT . '/includes/BusinessRegistry.php';
require $ROOT . '/includes/BusinessAnalyzer.php';
require $ROOT . '/includes/BusinessSpec.php';
require $ROOT . '/includes/ShopHelper.php';
require $ROOT . '/includes/OpenCodeService.php';
require $ROOT . '/includes/BizIntelligence/Capabilities.php';
require $ROOT . '/includes/BizIntelligence/Pipeline.php';

$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }

$bus = [
    'biz_name' => 'City Express Travels',
    'biz_type' => 'Bus, Transport & Travel',
    'biz_tagline' => 'Daily bus service Chennai to Madurai',
    'biz_audience' => 'Daily commuters and travellers',
    'biz_services' => 'Daily bus trips, seat booking, online tickets',
    'biz_products' => "Chennai-Madurai | Rs.450\nChennai-Trichy | Rs.320",
    'sections' => ['hero', 'services', 'about', 'contact'],
];

// 1. legacy spec: booking winner, NO shop
$spec = business_spec_analyze($bus);
ok($spec['business_type'] === 'booking', 'bus+trips still booking (got ' . $spec['business_type'] . ')');
ok(empty($spec['needs_shop']), 'bus+trips needs_shop=false');
ok(!in_array('cart', $spec['features']), 'bus features have no cart');
ok(!in_array('shop', $spec['pages']), 'bus pages have no shop');
ok(in_array('bookings', array_column($spec['entities'], 'name')), 'bookings entity kept');

// 2. explicit shop opt-in still works for bus
$busShop = $bus; $busShop['sections'][] = 'shop';
$spec2 = business_spec_analyze($busShop);
ok(!empty($spec2['needs_shop']), 'bus + explicit shop section => shop on');
ok($spec2['business_type'] === 'booking', 'category stays booking with shop opt-in');

// 3. server injector
ok(!isShopSite($bus['biz_type'], $bus['sections'], $bus['biz_products']), 'isShopSite=false for bus+trips');
ok(isShopSite($bus['biz_type'], array_merge($bus['sections'], ['shop']), $bus['biz_products']), 'isShopSite=true with explicit shop section');
ok(isShopSite('Grocery Store', ['hero'], "Rice | Rs.100"), 'isShopSite=true for real shop');

// 4. prompt blocks
ok(function_exists('opencode_shop_block') && opencode_shop_block($bus) === '', 'opencode shop block empty for bus');
ok(opencode_shop_block(['biz_type' => 'Grocery Store', 'sections' => ['hero'], 'biz_products' => "Rice | Rs.100"]) !== '', 'opencode shop block on for real shop');

// 5. signals: coach no longer sells services
$sig = biz_infer_signals('city bus with luxury coaches, book seats, pay online');
$caps = biz_select_capabilities($sig);
ok(empty($sig['sell_goods']), 'no sell_goods for bus');
ok(!in_array('orders', $caps), 'no orders cap for bus (got [' . implode(',', $caps) . '])');
ok(in_array('booking', $caps), 'booking cap kept');

// 6. intent: booking wins over payment/order words
$formHtml = '<form id="passenger-form"><input name="passenger"><input name="phone">'
    . '<label><input type="radio" name="pay_method" value="online">Pay Online</label>'
    . '<p>Complete your payment to confirm</p><button>Confirm Booking</button></form>';
$h = strtolower($formHtml);
$intent = preg_match('/book|reserv|appoint|seat|trip|schedule|slot/', $h) ? 'booking'
    : (preg_match('/checkout|cart|order|payment|buy|add-to-cart/', $h) ? 'order' : '');
ok($intent === 'booking', 'passenger form routes to booking');
$shopHtml = '<form id="checkout-form"><div class="cart">2 items</div><button>Buy now</button></form>';
$hs = strtolower($shopHtml);
$intent2 = preg_match('/book|reserv|appoint|seat|trip|schedule|slot/', $hs) ? 'booking'
    : (preg_match('/checkout|cart|order|payment|buy|add-to-cart/', $hs) ? 'order' : '');
ok($intent2 === 'order', 'real checkout still routes to order');

// 7. existing behavior kept
$shop = business_spec_analyze(['biz_name' => 'Aura Fashion', 'biz_type' => 'Ecommerce store', 'biz_tagline' => 'Clothes', 'biz_products' => "Tee | Rs.500", 'sections' => ['shop']]);
ok($shop['business_type'] === 'ecommerce' && !empty($shop['needs_shop']) && in_array('cart', $shop['features']), 'ecommerce shop intact');
$coach = business_spec_analyze(['biz_name' => 'Luxury Coach Hire', 'biz_type' => 'Coach service', 'biz_services' => 'coach hire for tours']);
ok($coach['business_type'] === 'booking', 'coach hire detected as booking (got ' . $coach['business_type'] . ')');

echo $fail === 0 ? "BUS-SHOP SEPARATION VERIFIED\n" : "$fail FAILED\n";
exit($fail ? 1 : 0);
