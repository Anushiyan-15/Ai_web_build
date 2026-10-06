<?php
// ═══════════════════════════════════════════════════════════════
//  includes/ShopHelper.php — E-Commerce Shop Engine & Cart Injector
//  Provides shopping cart, quantity controls, cart drawer, invoice modal,
//  and seamless shop injection for both Server Templates & AI Generations.
// ═══════════════════════════════════════════════════════════════

if (!function_exists('isShopSite')) {
    function isShopSite($bizType, $sections, $productsRaw) {
        if (!empty(trim((string)($productsRaw ?? '')))) return true;
        if (is_array($sections) && in_array('shop', array_map('strtolower', $sections), true)) return true;
        $t = strtolower((string)($bizType ?? ''));
        return (bool) preg_match('/shop|store|product|retail|e-?commerce|boutique|mart|trading|enterprise|fashion|jewelry|grocery|bakery|furniture|electronics|pharma/i', $t);
    }
}

if (!function_exists('parseShopProducts')) {
    function parseShopProducts($raw, $assets = []) {
        $fallbacks = array_values(array_filter([
            $assets['showcase1'] ?? null, $assets['showcase2'] ?? null,
            $assets['about'] ?? null, $assets['hero'] ?? null
        ]));
        if (empty($fallbacks)) {
            $fallbacks = ['https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=800&q=80'];
        }
        $out = [];
        $i = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string)($raw ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = array_map('trim', explode('|', $line));
            $name = $parts[0] ?? '';
            if ($name === '') continue;
            $priceLabel = $parts[1] ?? '';
            $img = $parts[2] ?? '';
            if (!preg_match('~^https?://~i', $img)) {
                $img = $fallbacks[$i % count($fallbacks)];
            }
            $num = 0;
            if (preg_match('/\d[\d,]*(\.\d{1,2})?/', (string)$priceLabel, $m)) {
                $num = (float) str_replace(',', '', $m[0]);
            }
            $out[] = [
                'name'  => $name,
                'price' => $priceLabel,
                'num'   => $num,
                'img'   => $img
            ];
            $i++;
        }
        return $out;
    }
}

if (!function_exists('getShopCurrency')) {
    function getShopCurrency($products) {
        foreach ($products as $p) {
            $label = (string)($p['price'] ?? '');
            if ($label === '' || ($p['num'] ?? 0) <= 0) continue;
            if (strpos($label, '₹') !== false) return '₹';
            if (stripos($label, 'rs') !== false) return 'Rs. ';
            if (strpos($label, '$') !== false) return '$';
            if (strpos($label, '€') !== false) return '€';
            if (strpos($label, '£') !== false) return '£';
            $sym = preg_replace('/[\d\s.,]/', '', $label);
            if ($sym !== '') {
                $chars = preg_split('//u', $sym, -1, PREG_SPLIT_NO_EMPTY);
                return implode('', array_slice($chars ?: [$sym], 0, 3));
            }
        }
        return '';
    }
}

if (!function_exists('getShopHtml')) {
    function getShopHtml($products, $cp, $bizPhone, $style, $bizName = '', $bizEmail = '', $bizTagline = '', $bizAddress = '') {
        if (empty($products)) return '';
        $storeName    = !empty(trim((string)$bizName)) ? trim((string)$bizName) : 'Store & Retail';
        $storeEmail   = !empty(trim((string)$bizEmail)) ? trim((string)$bizEmail) : 'orders@store.com';
        $storeTagline = !empty(trim((string)$bizTagline)) ? trim((string)$bizTagline) : 'Official Store & Order Invoice';
        $storeAddress = !empty(trim((string)$bizAddress)) ? trim((string)$bizAddress) : '';
        $primary      = $cp['primary'] ?? '#6366f1';
        $grad         = $cp['gradient'] ?? 'linear-gradient(135deg,#6366f1,#a855f7)';
        $digits       = preg_replace('/[^0-9]/', '', (string)($bizPhone ?? ''));
        $hasWa        = (strlen($digits) >= 7);
        $cur          = getShopCurrency($products) ?: '$';
        $isDark       = ($style === 'dark');
        $isBold       = ($style === 'bold' || $style === 'vibrant');

        // ── Product cards ──
        $cards = '';
        foreach ($products as $idx => $p) {
            $nm  = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
            $pr  = htmlspecialchars($p['price'] !== '' ? $p['price'] : 'Ask price', ENT_QUOTES, 'UTF-8');
            $img = htmlspecialchars($p['img'], ENT_QUOTES, 'UTF-8');
            $isPriced = ((float)($p['num'] ?? 0) > 0);

            if ($isDark) {
                $stepperBg = 'background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.18);color:#fff;';
                $cardStyle = 'background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:18px;overflow:hidden;backdrop-filter:blur(12px);display:flex;flex-direction:column;transition:transform 0.2s;';
                $h3Color   = '#fff';
                $badgeBg   = 'background:rgba(99,102,241,0.2);color:#c7d2fe;border:1px solid rgba(99,102,241,0.4);';
            } elseif ($isBold) {
                $stepperBg = 'background:#f1f5f9;border:2px solid #0f172a;color:#0f172a;';
                $cardStyle = 'background:#fff;border:2.5px solid #0f172a;border-radius:18px;overflow:hidden;box-shadow:5px 5px 0px #0f172a;display:flex;flex-direction:column;transition:transform 0.2s;';
                $h3Color   = '#0f172a';
                $badgeBg   = 'background:#fef08a;color:#854d0e;border:1.5px solid #0f172a;';
            } else {
                $stepperBg = 'background:#f8fafc;border:1.5px solid #cbd5e1;color:#0f172a;';
                $cardStyle = 'background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.06);display:flex;flex-direction:column;transition:transform 0.2s;';
                $h3Color   = '#0f172a';
                $badgeBg   = 'background:#ede9fe;color:#5b21b6;';
            }

            if ($isPriced) {
                $btn = "<div style=\"display:flex;align-items:center;gap:0.45rem;margin-top:auto;padding-top:1rem;\">"
                    . "<div style=\"display:inline-flex;align-items:center;border-radius:10px;overflow:hidden;{$stepperBg}\">"
                    . "<button type=\"button\" class=\"wc-card-dec\" data-idx=\"{$idx}\" style=\"width:32px;height:40px;background:none;border:none;color:inherit;font-size:1.2rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;\">&#8722;</button>"
                    . "<span class=\"wc-card-qty\" data-idx=\"{$idx}\" style=\"min-width:30px;text-align:center;font-weight:800;font-size:0.95rem;color:inherit;\">1</span>"
                    . "<button type=\"button\" class=\"wc-card-inc\" data-idx=\"{$idx}\" style=\"width:32px;height:40px;background:none;border:none;color:inherit;font-size:1.2rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;\">+</button>"
                    . "</div>"
                    . "<button type=\"button\" class=\"wc-add-btn\" data-idx=\"{$idx}\" style=\"flex:1;height:40px;padding:0 1rem;border-radius:10px;border:none;cursor:pointer;font-weight:800;font-size:0.88rem;font-family:inherit;background:{$grad};color:#fff;display:flex;align-items:center;justify-content:center;gap:0.35rem;transition:opacity 0.15s;\">&#x1F6D2; Add to Cart</button>"
                    . "</div>";
            } else {
                $btn = "<a href=\"#contact\" style=\"display:block;text-align:center;width:100%;margin-top:auto;padding:0.75rem 1rem;border-radius:10px;font-weight:800;font-size:0.9rem;border:1.5px solid {$primary};color:{$primary};text-decoration:none;\">Enquire &#8594;</a>";
            }

            $cards .= "<div class=\"wc-card\" data-idx=\"{$idx}\" style=\"{$cardStyle}\" onmouseover=\"this.style.transform='translateY(-4px)'\" onmouseout=\"this.style.transform=''\">"
                . "<div style=\"position:relative;overflow:hidden;\">"
                . "<img src=\"{$img}\" alt=\"{$nm}\" loading=\"lazy\" style=\"width:100%;height:220px;object-fit:cover;display:block;transition:transform 0.3s;\" onmouseover=\"this.style.transform='scale(1.05)'\" onmouseout=\"this.style.transform='scale(1)'\">"
                . "<span style=\"position:absolute;top:10px;right:10px;font-size:0.72rem;font-weight:800;padding:3px 9px;border-radius:99px;{$badgeBg}\">&#x2728; In Stock</span>"
                . "</div>"
                . "<div style=\"padding:1.4rem;display:flex;flex-direction:column;flex:1;\">"
                . "<h3 style=\"font-size:1.1rem;color:{$h3Color};font-weight:800;margin:0 0 0.35rem 0;line-height:1.3;\">{$nm}</h3>"
                . "<div style=\"font-size:1.3rem;font-weight:900;color:{$primary};margin-bottom:0.5rem;\">{$pr}</div>"
                . $btn
                . "</div></div>";
        }

        $secBg  = $isDark ? 'background:#0a0f1c;' : 'background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;';
        $hColor = $isDark ? '#fff' : '#0f172a';
        $pColor = $isDark ? '#94a3b8' : '#64748b';

        $productsJson   = json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $storeNameSafe  = htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8');
        $storeTglSafe   = htmlspecialchars($storeTagline, ENT_QUOTES, 'UTF-8');
        $storePhoneDisp = !empty($bizPhone)    ? '<div>&#128222; ' . htmlspecialchars($bizPhone, ENT_QUOTES, 'UTF-8') . '</div>'   : '';
        $storeEmailDisp = !empty($bizEmail)    ? '<div>&#9993;&#65039; ' . htmlspecialchars($bizEmail, ENT_QUOTES, 'UTF-8') . '</div>' : '';
        $storeAddrDisp  = !empty($storeAddress)? '<div>&#128205; ' . htmlspecialchars($storeAddress, ENT_QUOTES, 'UTF-8') . '</div>' : '';
        $waDrawerBtn    = $hasWa
            ? "<button type=\"button\" id=\"wc-btn-checkout-wa\" style=\"width:100%;padding:0.85rem;border-radius:12px;border:none;cursor:pointer;font-weight:800;font-size:0.92rem;font-family:inherit;background:#25D366;color:#fff;margin-bottom:0.6rem;display:none;align-items:center;justify-content:center;gap:0.4rem;\">&#x1F4AC; Order on WhatsApp</button>"
            : '';

        // PHP vars needed inside heredocs
        $g = $grad; $pr2 = $primary; $c2 = $cur;

        $drawer = <<<DRAWER
<div id="wc-cart-drawer" style="display:none;position:fixed;inset:0;z-index:9990;flex-direction:row;">
  <div id="wc-drawer-overlay" style="flex:1;background:rgba(0,0,0,0.55);cursor:pointer;"></div>
  <aside style="width:min(400px,94vw);background:#0f172a;border-left:1px solid #1e293b;display:flex;flex-direction:column;box-shadow:-20px 0 50px rgba(0,0,0,0.4);">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1.1rem 1.25rem;border-bottom:1px solid #1e293b;">
      <strong style="color:#fff;font-size:1.05rem;display:flex;align-items:center;gap:0.4rem;">&#x1F6D2; Your Cart (<span id="wc-cart-count-drawer">0</span> items)</strong>
      <button type="button" id="wc-btn-close-drawer" style="background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;line-height:1;padding:0.2rem 0.5rem;">&times;</button>
    </div>
    <div id="wc-cart-items" style="flex:1;overflow-y:auto;padding:0.5rem 1.25rem;min-height:0;"></div>
    <div style="padding:1.1rem 1.25rem;border-top:1px solid #1e293b;background:#0b0f19;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
        <span style="color:#94a3b8;font-size:0.88rem;">Subtotal:</span>
        <div id="wc-cart-total" style="color:#fff;font-weight:900;font-size:1.2rem;">{$c2}0</div>
      </div>
      <div style="font-size:0.75rem;color:#10b981;font-weight:700;margin-bottom:0.9rem;">&#10003; Free Express Shipping on this order</div>
      <button type="button" id="wc-btn-open-bill" style="width:100%;padding:0.88rem;border-radius:12px;border:none;cursor:pointer;font-weight:800;font-size:0.94rem;font-family:inherit;background:{$g};color:#fff;margin-bottom:0.6rem;display:none;align-items:center;justify-content:center;gap:0.4rem;">&#x1F9FE; View Customer Bill / Invoice</button>
      {$waDrawerBtn}
      <button type="button" id="wc-btn-checkout-form" style="width:100%;padding:0.75rem;border-radius:12px;cursor:pointer;font-weight:700;font-size:0.85rem;font-family:inherit;background:transparent;border:1.5px solid #475569;color:#cbd5e1;display:none;align-items:center;justify-content:center;">&#x1F4DD; Order via Contact Form</button>
    </div>
  </aside>
</div>
<button type="button" id="wc-fab" aria-label="Open cart" style="position:fixed;bottom:5.5rem;right:1.5rem;z-index:9989;width:58px;height:58px;border-radius:50%;border:none;cursor:pointer;background:{$g};color:#fff;font-size:1.4rem;box-shadow:0 12px 30px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;">&#x1F6D2;<span id="wc-cart-count" style="position:absolute;top:-4px;right:-4px;min-width:22px;height:22px;border-radius:11px;background:#ef4444;color:#fff;font-size:0.72rem;font-weight:800;display:none;align-items:center;justify-content:center;padding:0 4px;line-height:1;">0</span></button>
DRAWER;

        $billModal = <<<MODAL
<div id="wc-bill-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.82);backdrop-filter:blur(5px);align-items:flex-start;justify-content:center;overflow-y:auto;padding:1.5rem 1rem;">
  <div style="position:relative;width:100%;max-width:760px;margin:auto;display:flex;flex-direction:column;gap:0.5rem;padding-bottom:2rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 1.25rem;background:#0f172a;border:1px solid #1e293b;border-radius:12px;color:#fff;">
      <div style="font-weight:800;font-size:0.95rem;">&#x1F9FE; Official Customer Bill &amp; Invoice</div>
      <div style="display:flex;align-items:center;gap:0.5rem;">
        <button type="button" id="wc-btn-print-pdf" style="padding:0.45rem 0.95rem;border-radius:8px;background:{$g};color:#fff;border:none;font-weight:800;font-size:0.82rem;cursor:pointer;">&#x1F4E5; Save as PDF / Print</button>
        <button type="button" id="wc-btn-download-html" style="padding:0.45rem 0.75rem;border-radius:8px;background:#1e293b;color:#cbd5e1;border:1px solid #334155;font-weight:700;font-size:0.82rem;cursor:pointer;">&#x1F4BE; Download Receipt</button>
        <button type="button" id="wc-btn-close-bill" style="background:none;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer;padding:0 0.4rem;line-height:1;">&times;</button>
      </div>
    </div>
    <div id="wc-printable-invoice" style="background:#fff;color:#0f172a;border-radius:14px;padding:2.2rem;box-shadow:0 25px 60px rgba(0,0,0,0.5);font-family:system-ui,-apple-system,sans-serif;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0f172a;padding-bottom:1.5rem;flex-wrap:wrap;gap:1.5rem;">
        <div>
          <div style="font-size:1.6rem;font-weight:900;color:#0f172a;">{$storeNameSafe}</div>
          <div style="font-size:0.86rem;color:#64748b;margin-top:2px;">{$storeTglSafe}</div>
          <div style="font-size:0.82rem;color:#475569;margin-top:0.4rem;">{$storePhoneDisp}{$storeEmailDisp}{$storeAddrDisp}</div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:1.5rem;font-weight:900;color:{$pr2};">INVOICE</div>
          <div style="font-size:0.82rem;color:#64748b;margin-top:4px;">No: <span id="wc-inv-no" style="color:#0f172a;font-family:monospace;">INV-2026-0001</span></div>
          <div style="font-size:0.82rem;color:#64748b;margin-top:2px;">Date: <span id="wc-inv-date" style="color:#0f172a;font-weight:600;"></span></div>
          <div style="margin-top:6px;"><span style="display:inline-block;padding:2px 8px;border-radius:6px;background:#dcfce7;color:#15803d;font-size:0.75rem;font-weight:800;">&#10003; ORDER READY</span></div>
        </div>
      </div>
      <div style="margin:1.4rem 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1rem 1.25rem;">
        <div style="font-size:0.75rem;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:{$pr2};margin-bottom:0.6rem;">BILL TO / CUSTOMER DETAILS</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
          <div>
            <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:2px;">Customer Name</label>
            <input type="text" id="wc-bill-name" placeholder="Enter Full Name" value="Customer" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:6px;padding:0.4rem 0.6rem;font-size:0.88rem;font-weight:600;color:#0f172a;background:#fff;outline:none;font-family:inherit;">
          </div>
          <div>
            <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:2px;">Phone / WhatsApp</label>
            <input type="text" id="wc-bill-phone" placeholder="Enter Phone Number" value="" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:6px;padding:0.4rem 0.6rem;font-size:0.88rem;font-weight:600;color:#0f172a;background:#fff;outline:none;font-family:inherit;">
          </div>
          <div style="grid-column:1 / -1;">
            <label style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:2px;">Delivery Address</label>
            <input type="text" id="wc-bill-address" placeholder="Enter Delivery Street, City" value="Local Delivery" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:6px;padding:0.4rem 0.6rem;font-size:0.88rem;font-weight:600;color:#0f172a;background:#fff;outline:none;font-family:inherit;">
          </div>
        </div>
      </div>
      <table style="width:100%;border-collapse:collapse;margin:1.25rem 0;font-size:0.88rem;">
        <thead>
          <tr style="background:#0f172a;color:#fff;">
            <th style="padding:0.65rem 0.75rem;text-align:left;width:35px;">#</th>
            <th style="padding:0.65rem 0.75rem;text-align:left;">Product Description</th>
            <th style="padding:0.65rem 0.75rem;text-align:center;width:65px;">Qty</th>
            <th style="padding:0.65rem 0.75rem;text-align:right;width:110px;">Unit Price</th>
            <th style="padding:0.65rem 0.75rem;text-align:right;width:120px;">Line Total</th>
          </tr>
        </thead>
        <tbody id="wc-bill-tbody"></tbody>
      </table>
      <div style="display:flex;justify-content:flex-end;margin-top:1.2rem;">
        <div style="width:min(320px,100%);background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1rem 1.25rem;">
          <div style="display:flex;justify-content:space-between;padding:0.3rem 0;font-size:0.86rem;color:#64748b;"><span>Subtotal:</span><span id="wc-bill-subtotal" style="font-weight:700;color:#0f172a;">{$c2}0.00</span></div>
          <div style="display:flex;justify-content:space-between;padding:0.3rem 0;font-size:0.86rem;color:#64748b;"><span>Shipping:</span><span style="font-weight:700;color:#15803d;">FREE</span></div>
          <div style="display:flex;justify-content:space-between;padding:0.3rem 0;font-size:0.86rem;color:#64748b;"><span>Tax / VAT:</span><span style="font-weight:700;color:#0f172a;">Included (0%)</span></div>
          <div style="display:flex;justify-content:space-between;border-top:2px solid #0f172a;margin-top:0.6rem;padding-top:0.6rem;font-size:1.15rem;font-weight:900;"><span>Grand Total:</span><span id="wc-bill-grand-total" style="color:{$pr2};">{$c2}0.00</span></div>
        </div>
      </div>
      <div style="margin-top:1.8rem;padding-top:1.2rem;border-top:1px dashed #cbd5e1;display:flex;justify-content:space-between;flex-wrap:wrap;gap:1rem;font-size:0.8rem;color:#64748b;">
        <div>
          <div style="font-weight:800;color:#0f172a;margin-bottom:2px;">Payment Terms:</div>
          <div>Cash on Delivery (COD) / Bank Transfer upon receiving order.</div>
          <div style="margin-top:2px;">Thank you for shopping with {$storeNameSafe}!</div>
        </div>
        <div style="text-align:right;">
          <div style="font-weight:800;color:#0f172a;">Authorized E-Commerce Store</div>
          <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">Computer-generated official receipt</div>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:0.75rem;justify-content:flex-end;flex-wrap:wrap;">
      <button type="button" id="wc-btn-print-pdf2" style="flex:1;min-width:180px;padding:0.85rem;border-radius:10px;border:none;cursor:pointer;font-weight:800;font-size:0.92rem;font-family:inherit;background:{$g};color:#fff;display:flex;align-items:center;justify-content:center;gap:0.4rem;">&#x1F4E5; Save / Download as PDF</button>
      <button type="button" id="wc-btn-bill-wa" style="flex:1;min-width:180px;padding:0.85rem;border-radius:10px;border:none;cursor:pointer;font-weight:800;font-size:0.92rem;font-family:inherit;background:#25D366;color:#fff;display:flex;align-items:center;justify-content:center;gap:0.4rem;">&#x1F4AC; Send Bill via WhatsApp</button>
      <button type="button" id="wc-btn-close-bill2" style="padding:0.85rem 1.25rem;border-radius:10px;border:1.5px solid #475569;background:#0f172a;color:#cbd5e1;cursor:pointer;font-weight:700;font-size:0.9rem;">&times; Close</button>
    </div>
  </div>
</div>
MODAL;

        $printCss = '<style id="wc-print-css">@media print{body *{visibility:hidden!important;}#wc-bill-modal,#wc-bill-modal *{visibility:visible!important;}#wc-bill-modal{position:absolute!important;inset:0!important;background:#fff!important;padding:0!important;margin:0!important;display:block!important;overflow:visible!important;z-index:999999!important;}#wc-printable-invoice{box-shadow:none!important;border:none!important;padding:10mm 12mm!important;max-width:100%!important;}@page{margin:10mm;size:auto;}}</style>';

        // ── Cart JS (NOWDOC — escaped internal closing tags) ──
        $cartJs = <<<'WCJS'
<script>
(function(){
'use strict';
var WC=window.__WC__=window.__WC__||{};
if(WC._ready){return;}
WC._ready=true;

var PRODUCTS=__PRODUCTS__;
var PHONE='__PHONE__';
var CUR='__CUR__';
var STORE=__STORE_NAME__;
var KEY='wc_v3_'+String(STORE||'store').toLowerCase().replace(/[^a-z0-9]+/g,'-').slice(0,40);
var INV='INV-'+new Date().getFullYear()+'-'+String(1000+Math.floor(Math.random()*9000));

function load(){try{return JSON.parse(localStorage.getItem(KEY))||[];}catch(e){return[];}}
function save(c){try{localStorage.setItem(KEY,JSON.stringify(c));}catch(e){}}
function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function money(n){return CUR+Number(n||0).toFixed(2).replace(/\.00$/,'');}
function total(c){var t=0;c.forEach(function(i){t+=(i.num||0)*i.qty;});return t;}
function count(c){var n=0;c.forEach(function(i){n+=i.qty;});return n;}
function gel(id){return document.getElementById(id);}
function qsa(sel){return document.querySelectorAll(sel);}

// ── Update badges ──
function badges(c){
  var n=count(c);
  [gel('wc-cart-count'),gel('wc-cart-count-drawer'),gel('wc-hdr-count')].forEach(function(el){
    if(!el)return;
    el.textContent=n;
    el.style.display=n>0?'flex':'none';
  });
}

// ── Render drawer items ──
function renderDrawer(){
  var c=load();
  badges(c);
  var box=gel('wc-cart-items');
  if(!box)return;
  var has=c.length>0;
  if(!has){
    box.innerHTML='<div style="text-align:center;color:#94a3b8;padding:3rem 1rem;font-size:0.9rem;">Your cart is empty.<br><br>&#x1F6D2; Add products below!</div>';
  } else {
    var h='';
    c.forEach(function(item,i){
      var lt=(item.num||0)*item.qty;
      h+='<div style="display:flex;gap:0.75rem;align-items:center;padding:0.75rem 0;border-bottom:1px solid rgba(255,255,255,0.08);">'
       +(item.img?'<img src="'+esc(item.img)+'" alt="" style="width:46px;height:46px;border-radius:8px;object-fit:cover;flex-shrink:0;">'
         :'<div style="width:46px;height:46px;border-radius:8px;background:#1e293b;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">&#x1F6D2;</div>')
       +'<div style="flex:1;min-width:0;">'
       +'<div style="font-weight:700;color:#fff;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+esc(item.name)+'</div>'
       +'<div style="font-size:0.78rem;color:#94a3b8;">'+esc(item.label||money(item.num))+' &times; '+item.qty+' = <strong style="color:#fff;">'+money(lt)+'</strong></div>'
       +'</div>'
       +'<div style="display:flex;align-items:center;gap:0.3rem;flex-shrink:0;">'
       +'<button type="button" class="wc-di" data-i="'+i+'" style="width:26px;height:26px;border-radius:7px;border:1px solid #334155;background:#0f172a;color:#fff;cursor:pointer;font-size:0.9rem;display:flex;align-items:center;justify-content:center;line-height:1;">&#8722;</button>'
       +'<span style="min-width:22px;text-align:center;color:#fff;font-weight:700;font-size:0.85rem;">'+item.qty+'</span>'
       +'<button type="button" class="wc-ii" data-i="'+i+'" style="width:26px;height:26px;border-radius:7px;border:1px solid #334155;background:#0f172a;color:#fff;cursor:pointer;font-size:0.9rem;display:flex;align-items:center;justify-content:center;line-height:1;">+</button>'
       +'</div>'
       +'<button type="button" class="wc-ri" data-i="'+i+'" style="background:none;border:none;color:#f87171;cursor:pointer;font-size:1rem;padding:0 4px;line-height:1;">&times;</button>'
       +'</div>';
    });
    box.innerHTML=h;
  }
  var tot=gel('wc-cart-total');
  if(tot)tot.textContent='Total: '+money(total(c));
  function sd(id,v){var e=gel(id);if(e)e.style.display=v;}
  sd('wc-btn-open-bill',has?'flex':'none');
  sd('wc-btn-checkout-form',has?'flex':'none');
  sd('wc-btn-checkout-wa',has?'flex':'none');
}

// ── Add to cart ──
WC.add=function(pi,qty){
  qty=Math.max(1,parseInt(qty,10)||1);
  var p=PRODUCTS[pi];if(!p)return;
  var c=load(),found=null;
  c.forEach(function(i){if(i.pi===pi)found=i;});
  if(found)found.qty+=qty;
  else c.push({pi:pi,name:p.name,num:p.num||0,label:p.price||'',img:p.img||'',qty:qty});
  save(c);renderDrawer();openDrawer();
};

function openDrawer(){
  var d=gel('wc-cart-drawer');if(d){d.style.display='flex';}
  renderDrawer();
}
function closeDrawer(){var d=gel('wc-cart-drawer');if(d)d.style.display='none';}

function openBill(){
  var c=load();
  if(!c.length){alert('Please add items to cart first!');return;}
  renderBill();
  var m=gel('wc-bill-modal');if(m)m.style.display='flex';
}
function closeBill(){var m=gel('wc-bill-modal');if(m)m.style.display='none';}

function renderBill(){
  var c=load();
  var el;
  el=gel('wc-inv-no');if(el)el.textContent=INV;
  el=gel('wc-inv-date');
  if(el){var n=new Date();el.textContent=n.toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'})+' '+n.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});}
  var tb=gel('wc-bill-tbody');if(!tb)return;
  var h='';
  c.forEach(function(item,idx){
    var lt=(item.num||0)*item.qty;
    h+='<tr style="border-bottom:1px solid #e2e8f0;">'
     +'<td style="padding:0.7rem 0.5rem;color:#64748b;font-weight:700;">'+(idx+1)+'</td>'
     +'<td style="padding:0.7rem 0.75rem;"><strong>'+esc(item.name)+'</strong></td>'
     +'<td style="padding:0.7rem;text-align:center;font-weight:700;">'+item.qty+'</td>'
     +'<td style="padding:0.7rem;text-align:right;">'+esc(item.label||money(item.num))+'</td>'
     +'<td style="padding:0.7rem;text-align:right;font-weight:800;">'+money(lt)+'</td>'
     +'</tr>';
  });
  tb.innerHTML=h;
  var sub=total(c);
  el=gel('wc-bill-subtotal');if(el)el.textContent=money(sub);
  el=gel('wc-bill-grand-total');if(el)el.textContent=money(sub);
}

function printPdf(){
  var inv=gel('wc-printable-invoice');if(!inv)return;
  var nm=(gel('wc-bill-name')||{}).value||'Customer';
  var ph=(gel('wc-bill-phone')||{}).value||'-';
  var ad=(gel('wc-bill-address')||{}).value||'Local Delivery';
  var no=(gel('wc-inv-no')||{}).textContent||INV;
  try{
    var w=window.open('','_blank','width=860,height=920');
    if(w){
      w.document.write('<!DOCTYPE html><html><head><title>Bill_'+no+'</title><style>body{font-family:system-ui,-apple-system,sans-serif;margin:0;padding:24px;color:#0f172a;background:#fff;max-width:800px;}table{width:100%;border-collapse:collapse;margin:1.25rem 0;}th{background:#0f172a;color:#fff;padding:8px 10px;text-align:left;}td{padding:8px 10px;border-bottom:1px solid #e2e8f0;}input{border:none!important;background:none!important;padding:0!important;font-weight:700;color:#0f172a!important;}@media print{@page{margin:10mm;}body{padding:0;}}</style></head><body>');
      w.document.write(inv.outerHTML);
      w.document.write('<\/body><\/html>');w.document.close();
      try{var wn=w.document.getElementById('wc-bill-name');if(wn)wn.value=nm;var wp=w.document.getElementById('wc-bill-phone');if(wp)wp.value=ph;var wa=w.document.getElementById('wc-bill-address');if(wa)wa.value=ad;}catch(e){}
      w.focus();setTimeout(function(){w.print();},400);return;
    }
  }catch(e){}
  window.print();
}

function downloadHtml(){
  var inv=gel('wc-printable-invoice');if(!inv)return;
  var no=(gel('wc-inv-no')||{}).textContent||INV;
  var nm=(gel('wc-bill-name')||{}).value||'Customer';
  var html='<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice '+no+'</title><style>body{font-family:system-ui,sans-serif;padding:30px;color:#0f172a;background:#fff;max-width:800px;margin:auto;}table{width:100%;border-collapse:collapse;margin:1.5rem 0;}th{background:#0f172a;color:#fff;padding:8px 10px;text-align:left;}td{padding:8px 10px;border-bottom:1px solid #e2e8f0;}input{border:none;background:none;font-weight:700;color:#0f172a;}</style></head><body>'+inv.outerHTML+'<\/body><\/html>';
  var blob=new Blob([html],{type:'text/html;charset=utf-8'});
  var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='Invoice_'+no+'_'+nm.replace(/[^a-zA-Z0-9]/g,'_')+'.html';
  document.body.appendChild(a);a.click();document.body.removeChild(a);
}

function orderText(){
  var c=load();var lines=['Hello! Order with '+STORE+':',''];
  c.forEach(function(i,n){lines.push((n+1)+'. '+i.name+' x'+i.qty+' - '+(i.label||money(i.num)));});
  lines.push('','Total: '+money(total(c)));return lines.join('\n');
}
function sendBillWa(){
  var c=load();if(!c.length)return;
  var nm=(gel('wc-bill-name')||{}).value||'Customer';
  var ph=(gel('wc-bill-phone')||{}).value||'';
  var ad=(gel('wc-bill-address')||{}).value||'Standard Delivery';
  var no=(gel('wc-inv-no')||{}).textContent||INV;
  var lines=['*ORDER: '+no+'*','*Store:* '+STORE,'','*Customer:*','Name: '+nm,(ph?'Phone: '+ph:''),'Address: '+ad,'','*Items:*'];
  c.forEach(function(i,n){lines.push((n+1)+'. '+i.name+' x'+i.qty+' = '+money((i.num||0)*i.qty));});
  lines.push('','*TOTAL: '+money(total(c))+'*');
  window.open('https://wa.me/'+PHONE+'?text='+encodeURIComponent(lines.filter(Boolean).join('\n')),'_blank');
}

// ── Unified Button Delegation (stepper + cart + drawer) ──
document.addEventListener('click',function(e){
  var t=e.target;
  if(!t)return;
  
  // Drawer increment (+)
  var ii = t.closest ? t.closest('.wc-ii') : null;
  if(ii){
    var c=load();var i=parseInt(ii.dataset.i,10);
    if(c[i]){c[i].qty++;save(c);renderDrawer();}
    return;
  }
  // Drawer decrement (-)
  var di = t.closest ? t.closest('.wc-di') : null;
  if(di){
    var c=load();var i=parseInt(di.dataset.i,10);
    if(c[i]){c[i].qty--;if(c[i].qty<=0)c.splice(i,1);save(c);renderDrawer();}
    return;
  }
  // Drawer remove (x)
  var ri = t.closest ? t.closest('.wc-ri') : null;
  if(ri){
    var c=load();var i=parseInt(ri.dataset.i,10);
    c.splice(i,1);save(c);renderDrawer();
    return;
  }

  // Product Card Buttons: inc, dec, add
  // Robust qty lookup: nearest .wc-card first (works even if the stepper
  // markup reflows), then stepper-wrapper fallback, then global fallback.
  var btn = t.closest ? t.closest('.wc-card-inc,.wc-card-dec,.wc-add-btn') : null;
  if(!btn)return;
  var idx=parseInt(btn.dataset.idx,10);
  if(isNaN(idx))return;

  var card = btn.closest ? btn.closest('.wc-card') : null;
  var qEl = null;
  if(card){
    qEl = card.querySelector('.wc-card-qty[data-idx="'+idx+'"]') || card.querySelector('.wc-card-qty');
  }
  if(!qEl){
    var stepperBox = btn.closest ? btn.closest('div') : null;
    qEl = (stepperBox ? stepperBox.querySelector('.wc-card-qty') : null)
       || document.querySelector('.wc-card-qty[data-idx="'+idx+'"]');
  }

  if(btn.classList.contains('wc-card-inc')){
    if(qEl){
      var current = parseInt(qEl.textContent||'1',10);
      qEl.textContent = Math.max(1, (isNaN(current)?1:current) + 1);
    }
    return;
  }
  if(btn.classList.contains('wc-card-dec')){
    if(qEl){
      var current = parseInt(qEl.textContent||'1',10);
      qEl.textContent = Math.max(1, (isNaN(current)?1:current) - 1);
    }
    return;
  }
  if(btn.classList.contains('wc-add-btn')){
    var qty = qEl ? Math.max(1, parseInt(qEl.textContent||'1',10)||1) : 1;
    WC.add(idx, qty);
    if(qEl) qEl.textContent = '1';
    var orig = btn.innerHTML;
    btn.innerHTML = '&#10003; Added!';
    btn.style.opacity = '0.7';
    setTimeout(function(){btn.innerHTML = orig; btn.style.opacity = '1';}, 1000);
  }
});

// ── Bind static buttons ──
function bindAll(){
  function on(id,fn){var el=gel(id);if(el)el.addEventListener('click',fn);}
  on('wc-fab',openDrawer);
  on('wc-hdr-cart-btn',openDrawer);
  on('wc-btn-close-drawer',closeDrawer);
  on('wc-drawer-overlay',closeDrawer);
  on('wc-btn-open-bill',function(){closeDrawer();openBill();});
  on('wc-btn-close-bill',closeBill);
  on('wc-btn-close-bill2',closeBill);
  on('wc-btn-print-pdf',printPdf);
  on('wc-btn-print-pdf2',printPdf);
  on('wc-btn-download-html',downloadHtml);
  on('wc-btn-bill-wa',sendBillWa);
  on('wc-btn-checkout-wa',function(){window.open('https://wa.me/'+PHONE+'?text='+encodeURIComponent(orderText()),'_blank');});
  on('wc-btn-checkout-form',function(){
    var sec=document.getElementById('contact')||document.querySelector('#contact-form');
    if(sec&&sec.scrollIntoView)sec.scrollIntoView({behavior:'smooth'});
    setTimeout(function(){var ta=document.querySelector('#contact textarea,[name="message"]');if(ta){ta.value=orderText()+'\n\nName:\nPhone:';ta.focus();}},700);
    closeDrawer();
  });
  var bm=gel('wc-bill-modal');
  if(bm)bm.addEventListener('click',function(e){if(e.target===bm)closeBill();});
}

function init(){bindAll();renderDrawer();}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
</script>
WCJS;

        // Replace placeholders in JS
        $cartJs = str_replace(
            ['__PRODUCTS__', '__PHONE__', '__CUR__', '__STORE_NAME__'],
            [$productsJson, $digits, $cur, json_encode($storeName)],
            $cartJs
        );

        // Header cart button (injected into nav)
        $GLOBALS['__wc_header_btn__'] = '<button type="button" id="wc-hdr-cart-btn" aria-label="View Cart" style="display:inline-flex;align-items:center;gap:0.4rem;position:relative;background:none;border:none;cursor:pointer;padding:0.4rem 0.7rem;border-radius:8px;font-size:0.92rem;font-weight:700;color:inherit;font-family:inherit;">&#x1F6D2; Cart<span id="wc-hdr-count" style="display:none;min-width:20px;height:20px;border-radius:10px;background:#ef4444;color:#fff;font-size:0.7rem;font-weight:800;align-items:center;justify-content:center;padding:0 4px;line-height:1;margin-left:2px;">0</span></button>';

        $section = <<<SHOP
<section id="shop" style="padding:5rem 1.5rem;{$secBg}">
  <div style="max-width:1180px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:2.75rem;">
      <div style="font-size:0.78rem;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;color:{$pr2};margin-bottom:0.5rem;">&#x1F6CD;&#xFE0F; Our Products</div>
      <h2 style="font-size:2.2rem;font-weight:800;color:{$hColor};letter-spacing:-0.02em;margin-top:0;">Shop Our Products</h2>
      <p style="color:{$pColor};margin-top:0.5rem;font-size:1rem;">Select quantity &#8594; Add to cart &#8594; Get instant downloadable invoice.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.75rem;">
      {$cards}
    </div>
  </div>
</section>
SHOP;

        return $section . "\n" . $drawer . "\n" . $billModal . "\n" . $printCss . "\n" . $cartJs;
    }
}

if (!function_exists('injectShopIntoHtml')) {
    function injectShopIntoHtml($html, $shopHtml) {
        if (empty($shopHtml) || empty($html)) return $html;
        $hasShop   = (stripos($html, 'id="shop"') !== false || stripos($html, "id='shop'") !== false);
        $hasDrawer = (stripos($html, 'wc-cart-drawer') !== false);
        $hasCartJs = (stripos($html, '__WC__') !== false || stripos($html, 'wc-card-inc') !== false);

        // Partial AI shop: section exists but cart engine missing → inject
        // ONLY the engine parts (drawer + bill modal + css + js), never a
        // second #shop section (duplicate ids kill anchor nav + audit).
        if ($hasShop && (!$hasDrawer || !$hasCartJs)) {
            $parts = preg_split('/<\/section\s*>/i', $shopHtml, 2);
            $engine = isset($parts[1]) ? $parts[1] : $shopHtml;
            // Don't double-inject the header cart button
            if (stripos($html, 'wc-hdr-cart-btn') === false) {
                $headerBtn  = $GLOBALS['__wc_header_btn__'] ?? '';
                $shopNavLink = '<a href="#shop" style="font-size:0.9rem;font-weight:600;color:inherit;text-decoration:none;">&#x1F6CD;&#xFE0F; Shop</a>';
                if (!empty($headerBtn)) {
                    $inject = "\n" . $shopNavLink . "\n" . $headerBtn . "\n";
                    if (stripos($html, '</nav>') !== false) {
                        $html = preg_replace('/<\/nav>/i', $inject . '</nav>', $html, 1);
                    } elseif (stripos($html, '</header>') !== false) {
                        $html = preg_replace('/<\/header>/i', $inject . '</header>', $html, 1);
                    }
                }
            }
            if (stripos($html, '</body>') !== false) {
                return str_ireplace('</body>', $engine . "\n</body>", $html);
            }
            return $html . "\n" . $engine;
        }
        // Full skip: shop section AND engine both present
        if ($hasShop) return $html;

        // ── 1. Inject cart button + Shop nav link before </nav> ──
        $headerBtn  = $GLOBALS['__wc_header_btn__'] ?? '';
        $shopNavLink = '<a href="#shop" style="font-size:0.9rem;font-weight:600;color:inherit;text-decoration:none;">&#x1F6CD;&#xFE0F; Shop</a>';

        if (!empty($headerBtn) && stripos($html, 'wc-hdr-cart-btn') === false) {
            $inject = "\n" . $shopNavLink . "\n" . $headerBtn . "\n";
            if (stripos($html, '</nav>') !== false) {
                $html = preg_replace('/<\/nav>/i', $inject . '</nav>', $html, 1);
            } elseif (stripos($html, '</header>') !== false) {
                $html = preg_replace('/<\/header>/i', $inject . '</header>', $html, 1);
            }
        }

        // ── 2. Inject shop section before <footer or contact section or </body> ──
        if (stripos($html, '<footer') !== false) {
            return preg_replace('/<footer/i', $shopHtml . "\n<footer", $html, 1);
        }
        if (preg_match('/<section[^>]*id=["\']contact["\'][^>]*>/i', $html)) {
            return preg_replace('/(<section[^>]*id=["\']contact["\'][^>]*>)/i', $shopHtml . "\n$1", $html, 1);
        }
        if (stripos($html, '</main>') !== false) {
            return preg_replace('/<\/main>/i', $shopHtml . "\n</main>", $html, 1);
        }
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $shopHtml . "\n</body>", $html);
        }
        return $html . "\n" . $shopHtml;
    }
}

if (!function_exists('injectShopIfMissing')) {
    function injectShopIfMissing(string $html, array $data, string $variation = 'classic'): string {
        $sections = $data['sections'] ?? [];
        $productsRaw = $data['biz_products'] ?? '';
        $bizType = $data['biz_type'] ?? '';
        
        if (!isShopSite($bizType, $sections, $productsRaw)) {
            return $html;
        }
        
        // If it already has both #shop and the full cart drawer, it's already working
        if (stripos($html, 'id="shop"') !== false && stripos($html, 'wc-cart-drawer') !== false) {
            return $html;
        }

        $bizName = $data['biz_name'] ?? 'Store';
        $assets = function_exists('getNicheAssets') ? getNicheAssets($bizType, $bizName) : [];
        $shopProducts = parseShopProducts($productsRaw, $assets);
        
        if (empty($shopProducts)) {
            $bizServices = $data['biz_services'] ?? '';
            $svcLines = array_filter(array_map('trim', explode(',', $bizServices)));
            $svcLines = array_slice(array_values($svcLines), 0, 6);
            $fallbackLines = [];
            foreach ($svcLines as $s) $fallbackLines[] = $s . ' | Ask price';
            $shopProducts = parseShopProducts(implode("\n", $fallbackLines), $assets);
        }
        
        if (empty($shopProducts)) {
            $fallbacks = ['https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=800&q=80',
                          'https://images.unsplash.com/photo-1469334031218-e382a71b716b?auto=format&fit=crop&w=800&q=80',
                          'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=800&q=80'];
            $shopProducts = [
                ['name' => 'Signature Collection 01', 'price' => '$49', 'num' => 49, 'img' => $fallbacks[0]],
                ['name' => 'Signature Collection 02', 'price' => '$89', 'num' => 89, 'img' => $fallbacks[1]],
                ['name' => 'Signature Collection 03', 'price' => '$129', 'num' => 129, 'img' => $fallbacks[2]],
            ];
        }

        $colorPresets = [
            'purple' => ['primary' => '#6366f1', 'secondary' => '#a855f7', 'accent' => '#38bdf8', 'gradient' => 'linear-gradient(135deg, #6366f1 0%, #a855f7 100%)', 'light' => '#ede9fe'],
            'blue'   => ['primary' => '#2563eb', 'secondary' => '#06b6d4', 'accent' => '#38bdf8', 'gradient' => 'linear-gradient(135deg, #2563eb 0%, #06b6d4 100%)', 'light' => '#dbeafe'],
            'green'  => ['primary' => '#059669', 'secondary' => '#10b981', 'accent' => '#84cc16', 'gradient' => 'linear-gradient(135deg, #059669 0%, #10b981 100%)', 'light' => '#d1fae5'],
            'red'    => ['primary' => '#dc2626', 'secondary' => '#f97316', 'accent' => '#fbbf24', 'gradient' => 'linear-gradient(135deg, #dc2626 0%, #f97316 100%)', 'light' => '#fee2e2'],
            'gold'   => ['primary' => '#d97706', 'secondary' => '#f59e0b', 'accent' => '#ef4444', 'gradient' => 'linear-gradient(135deg, #b45309 0%, #f59e0b 100%)', 'light' => '#fef3c7'],
            'slate'  => ['primary' => '#1e293b', 'secondary' => '#475569', 'accent' => '#6366f1', 'gradient' => 'linear-gradient(135deg, #1e293b 0%, #334155 100%)', 'light' => '#f1f5f9'],
        ];
        $palette = $data['color_palette'] ?? 'purple';
        $cp = $colorPresets[$palette] ?? $colorPresets['purple'];
        
        $bizPhone = $data['biz_phone'] ?? '';
        $bizEmail = $data['biz_email'] ?? '';
        $bizTagline = $data['biz_tagline'] ?? '';
        $bizAddress = $data['biz_address'] ?? '';
        
        $style = ($variation === 'bold' || $variation === 'vibrant') ? 'bold' : (($variation === 'editorial' || $variation === 'dark') ? 'dark' : 'light');
        $shopHtml = getShopHtml($shopProducts, $cp, $bizPhone, $style, $bizName, $bizEmail, $bizTagline, $bizAddress);
        return injectShopIntoHtml($html, $shopHtml);
    }
}
