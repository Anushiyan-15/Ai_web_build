<?php
// ═══════════════════════════════════════════════════════════════
//  test_generated.php — Ecommerce generation E2E (server-side)
//  Usage: php test_generated.php
//  Checks the FULL chain a shop site travels:
//    1. Single-model policy (gemini-3.5-flash-lite ONLY, everywhere)
//    2. Shop training blocks present in AI prompts (Gemini + OpenCode)
//    3. Server-template shop HTML: cards + stepper + drawer + bill + JS
//    4. Cart qty delegation robust (.wc-card lookup, per-store key)
//    5. Placeholder replacement (__PRODUCTS__/__PHONE__/__CUR__/__STORE_NAME__)
//    6. Full + partial injection (AI #shop-only → engine parts added, no dup)
//    7. Taste baseline on shop output (safety net + audit)
// ═══════════════════════════════════════════════════════════════

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/TasteSkill.php';
require __DIR__ . '/includes/ShopHelper.php';
require __DIR__ . '/includes/GeminiService.php'; // pulls OpenCodeService + FlowCraft

$pass = 0; $fail = 0;
function check($name, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS: $name\n"; }
    else { $fail++; echo "FAIL: $name\n"; }
}

// ── 1. Single-model policy ──
check('config GEMINI_MODEL is lite', defined('GEMINI_MODEL') && GEMINI_MODEL === 'gemini-3.5-flash-lite');
check('config GEMINI_FAST_MODEL is lite', defined('GEMINI_FAST_MODEL') && GEMINI_FAST_MODEL === 'gemini-3.5-flash-lite');
$fm = gemini_fast_models();
check('gemini_fast_models lite-only', $fm === ['gemini-3.5-flash-lite']);
$genSrc = file_get_contents(__DIR__ . '/api/generate.php');
check('generate.php no 3.8-flash default', strpos($genSrc, "'gemini-3.8-flash'") === false);
check('generate.php resolvedModel lite', strpos($genSrc, "'gemini-3.5-flash-lite'") !== false);
$jsLane = file_get_contents(__DIR__ . '/assets/js/opencode-service.js');
check('frontend lanes no flash-lite-latest', strpos($jsLane, 'gemini-flash-lite-latest') === false);
check('frontend lanes use lite', substr_count($jsLane, 'gemini-3.5-flash-lite') >= 6);

// ── 2. Shop training in prompts ──
$shopData = [
    'biz_name' => 'Madurai Mart', 'biz_type' => 'Grocery Store',
    'biz_products' => "Idly Rice 5kg | ₹349\nToor Dal 1kg | ₹189\nCoconut Oil 1L | Ask price",
    'sections' => ['hero', 'shop', 'contact'], 'color_palette' => 'green',
];
$tb = gemini_shop_training_block($shopData);
check('gemini shop block non-empty for shop', strlen($tb) > 300);
check('gemini shop block has stepper contract', strpos($tb, 'wc-card-inc') !== false && strpos($tb, 'wc-add-btn') !== false);
check('gemini shop block has drawer ids', strpos($tb, '#wc-cart-drawer') !== false && strpos($tb, '#wc-bill-modal') !== false);
check('gemini shop block verbatim prices', strpos($tb, '₹349') !== false);
check('gemini shop block ask-price rule', stripos($tb, 'Ask price') !== false);
$tbOff = gemini_shop_training_block(['biz_type' => 'Salon', 'sections' => ['hero'], 'biz_products' => '']);
check('gemini shop block empty for non-shop', $tbOff === '');
$ob = opencode_shop_block($shopData);
check('opencode shop block non-empty for shop', strlen($ob) > 200 && strpos($ob, 'wc-card-dec') !== false);
check('opencode shop block empty for non-shop', opencode_shop_block(['biz_type' => 'Salon', 'sections' => []]) === '');

// ── 3. Server-template shop HTML ──
$products = parseShopProducts($shopData['biz_products'], []);
check('parse 3 products', count($products) === 3);
check('parse price num', $products[0]['num'] == 349);
check('parse ask-price num 0', $products[2]['num'] == 0);
$cp = ['primary' => '#059669', 'secondary' => '#10b981', 'accent' => '#84cc16',
       'gradient' => 'linear-gradient(135deg,#059669,#10b981)', 'light' => '#d1fae5'];
$shopHtml = getShopHtml($products, $cp, '+919876543210', 'light', 'Madurai Mart', 'a@b.com', 'Fresh daily', 'Madurai');
check('shop section id', stripos($shopHtml, 'id="shop"') !== false);
check('stepper inc class', strpos($shopHtml, 'wc-card-inc') !== false);
check('stepper dec class', strpos($shopHtml, 'wc-card-dec') !== false);
check('stepper qty class', strpos($shopHtml, 'wc-card-qty') !== false);
check('add btn class', strpos($shopHtml, 'wc-add-btn') !== false);
check('card root has wc-card class', strpos($shopHtml, 'class="wc-card"') !== false);
check('ask-price enquire link, no add btn for idx2', strpos($shopHtml, 'Enquire') !== false
    && strpos($shopHtml, 'wc-add-btn" data-idx="2"') === false);
check('drawer present', strpos($shopHtml, 'wc-cart-drawer') !== false);
check('bill modal present', strpos($shopHtml, 'wc-bill-modal') !== false);
check('fab present', strpos($shopHtml, 'id="wc-fab"') !== false);
check('header cart btn stored', strpos(($GLOBALS['__wc_header_btn__'] ?? ''), 'wc-hdr-cart-btn') !== false);

// ── 4. Cart JS robustness ──
check('JS binds .wc-card lookup', strpos($shopHtml, "closest('.wc-card')") !== false);
check('JS no fragile closest-div-only', strpos($shopHtml, "btn.closest('div')") === false
    || strpos($shopHtml, "closest('.wc-card')") !== false);
check('JS per-store storage key', strpos($shopHtml, 'wc_v3_') !== false);
check('JS drawer inc/dec/remove handlers', strpos($shopHtml, "closest('.wc-ii')") !== false
    && strpos($shopHtml, "closest('.wc-di')") !== false && strpos($shopHtml, "closest('.wc-ri')") !== false);
check('JS add resets qty to 1', strpos($shopHtml, "qEl.textContent = '1'") !== false);
check('JS money+total math', strpos($shopHtml, 'function total(c)') !== false && strpos($shopHtml, 'function money(n)') !== false);

// ── 5. Placeholder replacement ──
check('no __PRODUCTS__ leftover', strpos($shopHtml, '__PRODUCTS__') === false);
check('no __PHONE__ leftover', strpos($shopHtml, '__PHONE__') === false);
check('no __CUR__ leftover', strpos($shopHtml, '__CUR__') === false);
check('no __STORE_NAME__ leftover', strpos($shopHtml, '__STORE_NAME__') === false);
check('products JSON embedded', strpos($shopHtml, 'Idly Rice 5kg') !== false);
check('currency embedded (₹)', strpos($shopHtml, '₹') !== false);

// ── 6. Injection: full + partial + idempotent ──
$plain = '<!DOCTYPE html><html><head><title>T</title></head><body><nav><a href="#x">X</a></nav><footer>F</footer></body></html>';
$full = injectShopIntoHtml($plain, $shopHtml);
check('full inject adds shop', stripos($full, 'id="shop"') !== false);
check('full inject adds drawer', strpos($full, 'wc-cart-drawer') !== false);
check('full inject adds shop nav link', strpos($full, '#shop') !== false);
$again = injectShopIntoHtml($full, $shopHtml);
check('injection idempotent (no dup shop)', preg_match_all('/id="shop"/i', $again) === 1);
check('injection idempotent (no dup drawer)', preg_match_all('/wc-cart-drawer/', $again) === preg_match_all('/wc-cart-drawer/', $full));
// AI partial: model shipped #shop grid with stepper but no drawer/engine
$aiPartial = '<!DOCTYPE html><html><head><title>S</title></head><body><nav></nav>'
    . '<section id="shop"><div class="wc-card" data-idx="0"><button type="button" class="wc-card-inc" data-idx="0">+</button>'
    . '<span class="wc-card-qty" data-idx="0">1</span><button type="button" class="wc-add-btn" data-idx="0">Add</button></div></section>'
    . '<footer>F</footer></body></html>';
$fixed = injectShopIntoHtml($aiPartial, $shopHtml);
check('partial inject keeps single #shop', preg_match_all('/id="shop"/i', $fixed) === 1);
check('partial inject adds missing drawer', strpos($fixed, 'wc-cart-drawer') !== false);
check('partial inject adds cart JS', strpos($fixed, '__WC__') !== false || strpos($fixed, 'wc-cart-total') !== false);
// injectShopIfMissing end-to-end (data-driven)
$wired = injectShopIfMissing($plain, $shopData, 'classic');
check('injectShopIfMissing adds shop for shop data', stripos($wired, 'id="shop"') !== false);
$nonShop = injectShopIfMissing($plain, ['biz_type' => 'Salon', 'sections' => ['hero'], 'biz_products' => ''], 'classic');
check('injectShopIfMissing skips non-shop', stripos($nonShop, 'id="shop"') === false);

// ── 7. Taste baseline on shop output ──
$safe = taste_apply_safety_net($shopHtml);
check('safety net present on shop html', stripos($safe, 'prefers-reduced-motion') !== false);
check('safety net idempotent on shop', taste_apply_safety_net($safe) === $safe);
$shopDoc = '<!DOCTYPE html><html lang="en"><head><title>Madurai Mart — Fresh daily</title>'
    . '<style>@media(max-width:900px){.wc-card{grid-column:span 1}}</style></head><body>'
    . '<nav><a href="#shop">Shop</a><a href="#contact">Contact</a></nav>'
    . '<section><h1>Fresh groceries delivered fast</h1><p>Good.</p><img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1200&q=70" alt="Fresh groceries"></section>'
    . $shopHtml . '<section id="contact"><h2>Contact</h2></section>'
    . '<style>@media (prefers-reduced-motion: reduce){*{animation:none}}</style></body></html>';
$audit = taste_audit_html($shopDoc);
check('shop doc audit runs', isset($audit['score']) && isset($audit['issues']));
echo '  (shop doc taste score: ' . $audit['score'] . ', issues: ' . count($audit['issues']) . ")\n";
foreach (array_slice($audit['issues'], 0, 5) as $i) echo "  - $i\n";

echo "\nRESULT: $pass passed, $fail failed\n";

// ── 8. SPEED: gemini-first brief + gen cache (offline-safe parts) ──
echo "\n--- speed ---\n";
$sp0 = 0; $fl0 = 0;
function check2($name, $cond) {
    global $sp0, $fl0;
    if ($cond) { $sp0++; echo "PASS: $name\n"; }
    else { $fl0++; echo "FAIL: $name\n"; }
}
$tb = gemini_template_brief($shopData);
check2('template brief instant + shaped', strpos($tb, 'AUDIENCE:') !== false && strpos($tb, 'SECTIONS:') !== false);
check2('template brief keeps sections', strpos($tb, 'shop') !== false);
$k1 = gen_cache_key($shopData, 'classic', 'static', -1, 'brief-x');
$k2 = gen_cache_key($shopData, 'classic', 'static', -1, 'brief-x');
$k3 = gen_cache_key($shopData, 'bold', 'static', -1, 'brief-x');
check2('cache key stable', $k1 === $k2);
check2('cache key varies by variation', $k1 !== $k3);
$payload = ['success' => true, 'html' => '<html>cached-doc</html>', 'variation' => 'classic'];
gen_cache_put('__speedtest__key', $payload);
$got = gen_cache_get('__speedtest__key');
check2('cache roundtrip', is_array($got) && $got['html'] === '<html>cached-doc</html>');
check2('cache miss returns null', gen_cache_get('__speedtest__nope__') === null);
@unlink(gen_cache_dir() . '/__speedtest__key.json');
check2('gemini_analyze fn exists (fast brief path)', function_exists('gemini_analyze_requirements'));
$src = file_get_contents(__DIR__ . '/api/opencode.php');
check2('fast_one serves cache', strpos($src, 'gen_cache_get') !== false && strpos($src, "'cached'") !== false);
check2('gen paths use gemini brief (not slow lane)', substr_count($src, 'gemini_analyze_requirements') >= 4);
$js = file_get_contents(__DIR__ . '/assets/js/opencode-service.js');
check2('polish threshold 70 (fewer repair calls)', strpos($js, 'score >= 70') !== false);
check2('polish batched concurrently', strpos($js, 'Promise.all(pending') !== false || strpos($js, 'Promise.all(pendingSub') !== false);
echo "\nSPEED RESULT: $sp0 passed, $fl0 failed\n";
$fail += $fl0; $pass += $sp0;

// ── 9. VARIATIONS PAGE + navigation ──
echo "\n--- variations-page ---\n";
$vp = file_get_contents(__DIR__ . '/variations.php');
check('variations.php exists + session key', strpos($vp, 'webcraft_saved_project::') !== false);
check('variations back-to-builder (?wizard=1)', strpos($vp, 'builder.php?wizard=1') !== false);
check('variations select&edit handoff', strpos($vp, "stage = 'builder'") !== false && strpos($vp, 'activeDesignIndex') !== false);
check('variations layouts deep-link', strpos($vp, 'builder.php?layouts=') !== false);
check('variations 3D splash loader', strpos($vp, 'var-cube') !== false && strpos($vp, 'var-face f1') !== false);
check('variations empty state', strpos($vp, 'var-empty') !== false);
check('variations flash message', strpos($vp, 'wc_flash') !== false);
$ld = file_get_contents(__DIR__ . '/assets/js/loader-3d.js');
check('loader gallery scene', strpos($ld, 'wcl-gallery') !== false);
$lc = file_get_contents(__DIR__ . '/assets/css/loader-3d.css');
check('loader gallery css', strpos($lc, '.wcl-gallery') !== false && strpos($lc, 'wclGFloat') !== false);
$bd = file_get_contents(__DIR__ . '/builder.php');
check('generate redirects to variations', substr_count($bd, '/variations.php') >= 3);
check('goToVariations routes backs', strpos($bd, 'function goToVariations()') !== false);
check('toolbar back uses goToVariations', strpos($bd, 'onclick="goToVariations()"') !== false);
check('wizard/layouts params handled', strpos($bd, "qs2.get('wizard')") !== false && strpos($bd, 'showSubDesigns(layoutsQs)') !== false);
check('designs-screen has back buttons', strpos($bd, 'backToWizard()') !== false);
echo "\nTOTAL RESULT: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
