<?php
// includes/BusinessRuntime.php — injects real working business logic
// into generated/published HTML using the BusinessSpec.
// Shop cart already handled by ShopHelper; this adds generic
// booking/search/auth/validation runtime + API wiring.

require_once __DIR__ . '/BusinessSpec.php';
require_once __DIR__ . '/BusBookingWidget.php';
require_once __DIR__ . '/HtmlUtil.php';

/**
 * AI/blueprint payloads arrive as {compat:{...}, canonical:{...}}. Detection
 * MUST read through both layers, otherwise a genuine bus site is treated as
 * generic: no seat runtime routing, no customer.php, no portal link.
 */
function wc_business_spec_flat(array $spec): array {
    if (!isset($spec['canonical']) || !is_array($spec['canonical'])) return $spec;
    $canonical = $spec['canonical'];
    $flat = array_merge($canonical, $spec);
    if (empty($flat['entities']) && !empty($canonical['entities'])) {
        $flat['entities'] = $canonical['entities'];
    }
    if (empty($flat['profile']) && !empty($canonical['profile'])) {
        $flat['profile'] = $canonical['profile'];
    }
    return $flat;
}

function wc_business_requires_bus_booking(array $spec): bool {
    $spec = wc_business_spec_flat($spec);
    $profile = is_array($spec['profile'] ?? null) ? $spec['profile'] : [];
    $business = is_array($spec['business'] ?? null) ? $spec['business'] : [];
    $explicit = strtolower(trim((string)($spec['category'] ?? $business['category']
        ?? $spec['business_type'] ?? $spec['biz_type'] ?? '')));
    if (in_array($explicit, ['bus', 'coach', 'bus_booking', 'transport', 'travel', 'bus_ticketing'], true)) return true;

    $domain = strtolower(trim((string)($profile['domain'] ?? '')));
    if (in_array($domain, ['bus', 'coach', 'bus_booking', 'transport', 'travel'], true)) return true;

    // Capability/page fingerprints unique to seat-based transport. A generic
    // booking business (salon/clinic) never carries trip/seat primitives.
    $markerParts = array_merge(
        (array)($spec['features'] ?? []),
        (array)($spec['pages'] ?? []),
        (array)($spec['admin_modules'] ?? []),
        (array)($spec['capabilities'] ?? [])
    );
    $markerParts = array_filter($markerParts, 'is_scalar');
    $markers = strtolower(implode(' ', array_map('strval', $markerParts)));
    if (preg_match('/\b(trip_search|seat_map|hold_seats|seat-select|trip-detail|qr_ticket)\b/', $markers)) return true;

    $identity = implode(' ', [
        (string)($profile['business_name'] ?? ''),
        (string)($profile['description'] ?? ''),
        (string)($spec['biz_type'] ?? ''),
        (string)($spec['business_type'] ?? ''),
        (string)($business['name'] ?? ''),
    ]);
    if (preg_match('/\b(bus|coach)\b/i', $identity)) return true;

    $entities = (array)($spec['entities'] ?? []);
    $hasTrips = false;
    $hasBookings = false;
    foreach ($entities as $entity) {
        if (!is_array($entity)) continue;
        $key = strtolower((string)($entity['key'] ?? $entity['id'] ?? ''));
        $name = strtolower((string)($entity['name'] ?? ''));
        if ($key === 'trips' || $name === 'trips') $hasTrips = true;
        if ($key === 'bookings' || $name === 'bookings') $hasBookings = true;
    }
    return $hasTrips && $hasBookings;
}

/**
 * Remove AI-authored fake backend objects (window.WCBiz = { ...create with
 * Math.random BK-... }). The real runtime is injected after page scripts and
 * is authoritative, but stripping the mock prevents any cached reference or
 * fallback path from ever producing a fake confirmation.
 * A tiny placeholder keeps page UI assignments (WCBiz.selectedSeats = …) safe.
 */
function wc_neutralize_mock_wcbiz(string $html): string {
    if (stripos($html, 'window.WCBiz') === false) return $html;
    return preg_replace_callback('/<script\b([^>]*)>(.*?)<\/script\s*>/is', function ($m) {
        $attrs = strtolower($m[1]);
        if (strpos($attrs, 'data-wc-biz-runtime') !== false
            || strpos($attrs, 'application/json') !== false
            || strpos($attrs, 'data-wc-pak-submit') !== false) {
            return $m[0];
        }
        $js = $m[2];
        if (stripos($js, 'window.WCBiz') === false) return $m[0];
        $offset = 0;
        while (preg_match('/window\.WCBiz\s*=\s*\{/', $js, $hit, PREG_OFFSET_CAPTURE, $offset)) {
            $start = (int)$hit[0][1];
            $brace = strpos($js, '{', $start);
            if ($brace === false) break;
            $end = wc_find_balanced_brace($js, $brace);
            if ($end === null) break;
            $block = substr($js, $start, $end - $start + 1);
            $isMock = (bool)preg_match('/Mock\s+WCBiz/i', $block)
                || ((bool)preg_match('/Math\.random\(\)/', $block)
                    && (bool)preg_match('/[\'"]BK-|[\'"]HOLD-/', $block))
                || ((bool)preg_match('/\basync\s+create\s*\(/', $block)
                    && (bool)preg_match('/success\s*:\s*true/', $block)
                    && (bool)preg_match('/Math\.random|setTimeout/', $block));
            if ($isMock) {
                $replacement = 'window.WCBiz=window.WCBiz||{};/* WebCraft: AI mock WCBiz removed — real backend runtime is authoritative */';
                $js = substr($js, 0, $start) . $replacement . substr($js, $end + 1);
                $offset = $start + strlen($replacement);
            } else {
                $offset = $end + 1;
            }
        }
        return '<script' . $m[1] . '>' . $js . '</script>';
    }, $html) ?? $html;
}

/** Index of the closing brace matching $open, string/escape aware. */
function wc_find_balanced_brace(string $src, int $open): ?int {
    $depth = 0;
    $quote = null;
    $escaped = false;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        $ch = $src[$i];
        if ($quote !== null) {
            if ($escaped) { $escaped = false; continue; }
            if ($ch === '\\') { $escaped = true; continue; }
            if ($ch === $quote) { $quote = null; }
            continue;
        }
        if ($ch === '"' || $ch === "'" || $ch === '`') { $quote = $ch; continue; }
        if ($ch === '{') { $depth++; continue; }
        if ($ch === '}') {
            $depth--;
            if ($depth === 0) return $i;
        }
    }
    return null;
}

/**
 * Add a visible "My Account" entry pointing at the generated customer portal
 * (customer.php). Idempotent; skips pages that already link to it.
 */
function business_customer_portal_inject(string $html): string {
    if (stripos($html, 'customer.php') !== false) return $html;
    $link = '<a href="customer.php" class="wc-portal-link" data-wc-portal="1" '
        . 'style="display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;'
        . 'border:1.5px solid currentColor;border-radius:999px;font-weight:700;'
        . 'text-decoration:none;white-space:nowrap;font:inherit;">'
        . "\xF0\x9F\x91\xA4" . ' My Account</a>';

    // Primary navigation: append inside the first real <nav>…</nav>.
    if (preg_match('/<nav\b[^>]*>/i', $html, $nav, PREG_OFFSET_CAPTURE)) {
        $navStart = (int)$nav[0][1];
        $after = $navStart + strlen($nav[0][0]);
        $close = stripos($html, '</nav>', $after);
        if ($close !== false && $navStart > (int)(stripos($html, '<body') ?: 0)) {
            return substr($html, 0, $close) . $link . substr($html, $close);
        }
    }
    // Header fallback.
    if (preg_match('/<\/header\s*>/i', $html, $hh, PREG_OFFSET_CAPTURE)) {
        $at = (int)$hh[0][1];
        return substr($html, 0, $at) . $link . substr($html, $at);
    }
    // Last resort: a floating button so the portal is never unreachable.
    $float = '<a href="customer.php" data-wc-portal="1" '
        . 'style="position:fixed;right:18px;bottom:18px;z-index:9998;display:inline-flex;'
        . 'align-items:center;gap:.4rem;padding:.7rem 1.1rem;border-radius:999px;'
        . 'background:#6366f1;color:#fff;text-decoration:none;font-weight:700;'
        . 'box-shadow:0 12px 30px rgba(15,23,42,.28);font-family:inherit;">'
        . "\xF0\x9F\x91\xA4" . ' My Account</a>';
    return wc_insert_before_body_end($html, $float);
}

function wc_node_api_url(): string {
    if (defined('NODE_API_URL')) return rtrim(NODE_API_URL, '/');
    $env = getenv('NODE_API_URL');
    return ($env !== false && trim((string)$env) !== '') ? rtrim(trim((string)$env), '/') : 'http://localhost:3100';
}

function business_runtime_js(array $spec, string $slug): string {
    $s = json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sl = json_encode($slug);
    $nodeApi = json_encode(wc_node_api_url());
    $opsApi = json_encode((defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/api/business-ops.php');
    $isBusBooking = json_encode(wc_business_requires_bus_booking($spec));
    return <<<JS
<script data-wc-biz-runtime>(function(){
var SPEC=null;try{SPEC=JSON.parse(document.getElementById('wc-biz-spec')?.textContent||'null')}catch(e){}
var SLUG=($sl);
var IS_BUS_BOOKING=($isBusBooking);
// Dual backend: Node.js API first (JSON database), PHP fallback.
// Fallback happens ONLY on network failure (Node down) — validation
// errors (HTTP 4xx) are final and never retried elsewhere, so a submit
// can never create two records. Same idempotency key on both attempts.
var NODE_API=($nodeApi).replace(/\/$/,'');
var OPS_API=($opsApi);
try{window.__WC_NODE_API__=NODE_API;}catch(e){}
function postJson(url,body,ms){
  var ctrl=(typeof AbortController!=='undefined')?new AbortController():null;
  var timer=ctrl?setTimeout(function(){try{ctrl.abort();}catch(e){}},(ms||2500)):null;
  return fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify(body),signal:ctrl?ctrl.signal:undefined}).then(function(r){return r.json().then(function(j){return {status:r.status,body:j};});})
    .finally(function(){if(timer)clearTimeout(timer);});
}
function apiNode(action,payload){
  return postJson(NODE_API+'/node-api/'+encodeURIComponent(SLUG)+'/'+action,
    Object.assign({slug:SLUG},payload||{}),2500).then(function(r){return r.body;});
}
function apiPhp(action,payload){
  return postJson(OPS_API,Object.assign({action:action,slug:SLUG},payload||{}),12000).then(function(r){return r.body;});
}
// Node action names differ slightly from the PHP contract — translate here.
function api(action,payload){
  payload=payload||{};
  // Bus operations share one PHP transaction store across trips, holds,
  // bookings, history, tickets, payment, cancellation and the admin mirror.
  if(IS_BUS_BOOKING&&(
    (action==='list'&&['trips','bookings'].indexOf(payload.entity)>=0)
    ||action==='ticket'||action==='pay'||action==='verify'||action==='payment_intent'
    ||(action==='cancel'&&payload.entity==='bookings')
  ))return apiPhp(action,payload);
  // Booking holds/creates and seat availability use the PHP transaction layer
  // so capacity checks, hold consumption, and the admin mirror stay atomic.
  if(action==='availability'||action==='release_hold'||action==='verify'
    ||((action==='hold'||action==='create')&&payload.entity==='bookings'))
    return apiPhp(action,payload);
  var nodeCall;
  if(action==='create')nodeCall=function(){return postJson(NODE_API+'/node-api/'+encodeURIComponent(SLUG)+'/'+encodeURIComponent(payload.entity||'inquiries'),
    {record:payload.record||{},idempotency_key:payload.idempotency_key||''},2500).then(function(r){return r.body;});};
  else if(action==='list')nodeCall=function(){var qp={};Object.keys(payload||{}).forEach(function(k){if(k!=='entity'&&k!=='slug'&&k!=='action')qp[k]=payload[k];});
    var q='?'+new URLSearchParams(qp).toString();
    return fetch(NODE_API+'/node-api/'+encodeURIComponent(SLUG)+'/'+encodeURIComponent(payload.entity||'inquiries')+q,{signal:(function(){var c=new AbortController();setTimeout(function(){try{c.abort();}catch(e){}},2500);return c.signal;})()}).then(function(r){return r.json();});};
  else if(action==='hold')nodeCall=function(){return postJson(NODE_API+'/node-api/'+encodeURIComponent(SLUG)+'/'+encodeURIComponent(payload.entity||'bookings')+'/hold',
    payload,2500).then(function(r){return r.body;});};
  else if(action==='cancel')nodeCall=function(){return postJson(NODE_API+'/node-api/'+encodeURIComponent(SLUG)+'/'+encodeURIComponent(payload.entity||'inquiries')+'/'+encodeURIComponent(payload.id||'')+'/cancel',
    payload,2500).then(function(r){return r.body;});};
  else if(action==='pay')nodeCall=function(){return postJson(NODE_API+'/node-api/'+encodeURIComponent(SLUG)+'/pay',
    payload,2500).then(function(r){return r.body;});};
  else if(action==='ticket')nodeCall=function(){return apiPhp('ticket',payload);};
  else nodeCall=function(){return Promise.reject(new Error('no-node-route'));};  return nodeCall().catch(function(err){
    // Network failure only (Node down/blocked): same payload+key to PHP.
    // Anything else (validation 4xx, JSON) is final — no double submit.
    if(err&&(err.name==='AbortError'||err.name==='TypeError'))return apiPhp(action,payload);
    throw err;
  });
}
function note(msg,ok,form){
  var host=form&&form.parentNode?form.parentNode:document.body;
  var d=document.createElement('div');
  d.setAttribute('data-wc-note','1');
  d.textContent=msg;
  d.style.cssText='margin:12px 0;padding:12px 18px;border-radius:10px;font-weight:700;font-size:14px;font-family:inherit;box-shadow:0 10px 30px rgba(0,0,0,.25);background:'+(ok?'#059669':'#dc2626')+';color:#fff;';
  if(host===document.body){d.style.cssText+='position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:99999;';}
  host.appendChild(d);
  setTimeout(function(){d.remove();},5000);
}
// Blob-tab death guard: "open in new tab" previews run on a blob: URL, so a
// plain form submit (no action / GET) would navigate to blob:..?origin=..
// and die with ERR_FILE_NOT_FOUND. A form/button with target=_blank does the
// same in a new tab. Never let any form navigate to blob: or pop a new tab —
// strip the target and stay inline (wired handlers below still run).
document.addEventListener('submit',function(e){
  var f=e.target&&e.target.closest?e.target.closest('form'):null;
  if(!f)return;
  var act='';try{act=String(f.action||'');}catch(err){act='';}
  var tgt='';try{tgt=String(f.getAttribute('target')||'').toLowerCase();}catch(err){tgt='';}
  var st='';try{st=String((e.submitter&&e.submitter.getAttribute('formtarget'))||'').toLowerCase();}catch(err){st='';}
  if(tgt==='_blank'){try{f.removeAttribute('target');}catch(err){}}
  if(st==='_blank'){try{e.submitter.removeAttribute('formtarget');}catch(err){}}
  if(act.slice(0,5).toLowerCase()==='blob:'||tgt==='_blank'||st==='_blank')e.preventDefault();
},true);
// Dead "#" links jump to top (misbuilt Select controls) — swallow inline.
document.addEventListener('click',function(e){
  var a=e.target&&e.target.closest?e.target.closest('a[href="#"]'):null;
  if(!a)return;e.preventDefault();
},true);
// Purpose of a form: explicit data-wc-form wins, else infer from fields.
function formPurpose(f){
  var p=(f.getAttribute('data-wc-form')||'').toLowerCase();
  if(p==='booking'||p==='contact'||p==='newsletter'||p==='appointment'||p==='order')return p;
  var html=(f.id+' '+(f.className||'')+' '+(f.innerHTML||'')).toLowerCase();
  if(/seat|trip|book|reserve|appointment|slot|date/.test(html)&&/date|seat|slot|trip|time/.test(html))return 'booking';
  if(/subscrib|newsletter/.test(html))return 'newsletter';
  return 'contact';
}
var ENTITY={booking:'bookings',contact:'inquiries',newsletter:'subscribers',appointment:'appointments',order:'orders'};
document.querySelectorAll('form').forEach(function(f){
  if(f.dataset.wcBizBound)return;f.dataset.wcBizBound='1';
  if(f.hasAttribute('data-wc-search'))return; // search forms handled below — never create records
  if(f.hasAttribute('data-wc-lookup'))return; // runtime lookup forms list — never create
  if(/history|lookup|track|my-booking/i.test(f.id||''))return; // page lookup forms never create records
  f.addEventListener('submit',function(e){
    if(f.dataset.remote==='off')return;
    var fd=new FormData(f);
    if(String(fd.get('company')||'').trim()!=='')return; // honeypot: let server reject quietly
    var obj={};fd.forEach(function(v,k){obj[k]=String(v).trim();});
    if(obj.email&&!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(obj.email)){e.preventDefault();note('Enter a valid email.',false,f);return;}
    e.preventDefault();
    if(f.dataset.wcBusy==='1')return; // double-submit guard
    f.dataset.wcBusy='1';
    var btn=f.querySelector('[type=submit],button:not([type])');var orig=btn?btn.innerHTML:'';
    if(btn){btn.disabled=true;btn.innerHTML='Sending…';}
    var key='k_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,8);
    var purpose=formPurpose(f);
    var targetEntity=ENTITY[purpose]||'inquiries';
    // Hold-context memory: a seat/slot hold made moments ago on THIS page is
    // auto-attached to the booking create, so the ticket step can never fail
    // with "Missing trip / Select at least one seat" just because the page's
    // own JS forgot to copy trip_id+seats into the form.
    // The hold WINS over form fields for bookings: it reserves real capacity
    // and the backend consumes it by hold_ref — stale hidden inputs from an
    // earlier trip must never override (or orphan) the live hold.
    try{
      var HS=window.__WC_HOLD__||null;
      if(HS&&HS.entity===targetEntity&&HS.seats&&HS.seats.length&&HS.trip_id){
        if(targetEntity==='bookings'){
          obj.trip_id=HS.trip_id;obj.seats=HS.seats;
          if(HS.ref)obj.hold_ref=HS.ref;
        }else{
          if(!obj.trip_id)obj.trip_id=HS.trip_id;
          if(!obj.seats)obj.seats=HS.seats;
          if(!obj.hold_ref&&HS.ref)obj.hold_ref=HS.ref;
        }
      }
    }catch(e){}
    // Seat flows need a held trip first — say so plainly instead of a bare 400.
    if(targetEntity==='bookings'&&!obj.trip_id){
      e.preventDefault();
      note('Pick your bus and press “Hold Seats” first — then fill this form.',false,f);
      if(btn){btn.disabled=false;btn.innerHTML=orig;}
      delete f.dataset.wcBusy;
      return;
    }
    // Single-flight with page-level creators: WCBiz.create dedupes identical
    // payloads within 2s, so a page handler + this generic handler on one
    // submit produce exactly one record (no double booking, no stray error).
    var createP;
    try {
      createP = (window.WCBiz && typeof window.WCBiz.create === 'function')
        ? window.WCBiz.create(targetEntity, obj, {idempotency_key: key})
        : api('create', {entity: targetEntity, record: obj, idempotency_key: key});
    } catch (e) {
      createP = api('create', {entity: targetEntity, record: obj, idempotency_key: key});
    }
    createP.then(function(j){      if(btn){btn.disabled=false;btn.innerHTML=orig;}
      delete f.dataset.wcBusy;
      if(j&&j.success){
        try{window.__WC_HOLD__=null;}catch(e){} // hold consumed — never reuse stale seats
        if(j.deduped){note('Already received — thank you!',true,f);return;}
        f.reset();
        var thanks=f.getAttribute('data-wc-thanks')||'✓ Thank you! We received it and will be in touch shortly.';
        note(thanks,true,f);
        // Let page flow-JS render the ticket from the REAL record (ref, id):
        try{f.dispatchEvent(new CustomEvent('wc:booking-confirmed',{detail:j,bubbles:true}));}catch(e){}
      }
      else note('Could not submit: '+((j&&j.error)||'error'),false,f);
    }).catch(function(){if(btn){btn.disabled=false;btn.innerHTML=orig;}delete f.dataset.wcBusy;note('Network error. Try again.',false,f);});
  });
});
// Customer history: any [data-wc-history] block lists the visitor's own records.
// Auto history: a page with ticket lookup but no history block gets one, so
// every booking flow shows backend-backed "my bookings" without page edits.
try{
  document.querySelectorAll('[data-wc-ticket-lookup]').forEach(function(tl){
    try{
      if(!tl.parentNode||tl.parentNode.querySelector('[data-wc-history]'))return;
      var hh=document.createElement('h4');
      hh.textContent='My booking history';
      hh.style.cssText='font-size:1rem;margin:1.25rem 0 0.6rem;';
      var hb=document.createElement('div');
      hb.setAttribute('data-wc-history',tl.getAttribute('data-wc-ticket-lookup')||'bookings');
      tl.parentNode.insertBefore(hb,tl.nextSibling);
      hb.parentNode.insertBefore(hh,hb);
    }catch(e){}
  });
}catch(e){}
function escH(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
document.querySelectorAll('[data-wc-history]').forEach(function(box){
  var entity=(box.getAttribute('data-wc-history')||'bookings').toLowerCase();
  var idem=(box.getAttribute('data-wc-id')||'email');
  function val(){var i=document.querySelector('[data-wc-history-id]');return i?(i.value||'').trim():'';}
  function render(rows){
    if(!rows.length){box.innerHTML='<p style="color:#64748b">No records found for this lookup.</p>';return;}
    var h='<div style="display:flex;flex-direction:column;gap:0.6rem;">';
    rows.slice(0,20).forEach(function(r){
      h+='<div style="border:1px solid #e2e8f0;border-radius:10px;padding:0.7rem 1rem;font-size:0.86rem;">'
        +'<strong>'+escH(r.ref||r.id||'record')+'</strong> · '+escH(r.status||'received')
        +(r.payment_status?' · '+escH(r.payment_status):'')
        +'</div>';
    });
    box.innerHTML=h+'</div>';
  }
  box.innerHTML='<form data-remote="off" data-wc-lookup="1" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">'
    +'<input data-wc-history-id placeholder="Your '+(idem==='phone'?'phone':'email')+'" style="flex:1;min-width:180px;padding:0.6rem 0.9rem;border:1.5px solid #cbd5e1;border-radius:9px;font:inherit;">'
    +'<button type="submit" style="padding:0.6rem 1.2rem;border-radius:9px;border:none;background:#6366f1;color:#fff;font-weight:700;cursor:pointer;">View my records</button></form>'
    +'<div data-wc-history-rows></div>';
  var rowsBox=box.querySelector('[data-wc-history-rows]');
  box.querySelector('[data-wc-lookup]').addEventListener('submit',function(e){
    e.preventDefault();
    var v=val();if(!v){note('Enter your '+(idem==='phone'?'phone':'email')+' to look up.',false,box);return;}
    var payload={entity:entity};payload[idem]=v;
    api('list',payload).then(function(j){
      if(j&&j.success)render(j.data||[]);
      else note('Lookup failed: '+((j&&j.error)||'error'),false,box);
    }).catch(function(){note('Network error. Try again.',false,box);});
  });
});
// Ticket retrieval by phone + booking ref: <div data-wc-ticket-lookup="bookings"></div>.
// Both must match the same record (ref alone finds nothing) — no enumeration.
document.querySelectorAll('[data-wc-ticket-lookup]').forEach(function(box){
  var entity=(box.getAttribute('data-wc-ticket-lookup')||'bookings').toLowerCase().replace(/[^a-z0-9_]/g,'')||'bookings';
  box.innerHTML='<form data-remote="off" data-wc-ticket-form="1" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">'
    +'<input name="phone" inputmode="tel" autocomplete="tel" placeholder="Phone used at booking" required style="flex:1;min-width:160px;padding:0.6rem 0.9rem;border:1.5px solid #cbd5e1;border-radius:9px;font:inherit;">'
    +'<input name="ref" placeholder="Booking ref (e.g. BK-XXXXXXXX)" required style="flex:1;min-width:160px;padding:0.6rem 0.9rem;border:1.5px solid #cbd5e1;border-radius:9px;font:inherit;text-transform:uppercase;">'
    +'<button type="submit" style="padding:0.6rem 1.2rem;border-radius:9px;border:none;background:#059669;color:#fff;font-weight:700;cursor:pointer;">Get my ticket</button></form>'
    +'<div data-wc-ticket-out></div>';
  var out=box.querySelector('[data-wc-ticket-out]');
  box.querySelector('[data-wc-ticket-form]').addEventListener('submit',function(e){
    e.preventDefault();
    var fd=new FormData(e.target);
    var phone=String(fd.get('phone')||'').trim();
    var ref=String(fd.get('ref')||'').trim().toUpperCase();
    if(!phone||!ref){note('Enter your phone number and booking ref.',false,box);return;}
    out.innerHTML='<p style="color:#64748b">Finding your ticket…</p>';
    api('ticket',{entity:entity,phone:phone,ref:ref}).then(function(j){
      if(!(j&&j.success&&j.data)){out.innerHTML='';note('No ticket found for that ref + phone. Check both and try again.',false,box);return;}
      var r=j.data;
      var seats=r.seats;
      if(Array.isArray(seats))seats=seats.join(', ');
      out.innerHTML='<div style="border:2px solid #059669;border-radius:14px;padding:1.1rem 1.25rem;background:#f0fdf4;max-width:460px;">'
        +'<div style="font-size:.72rem;font-weight:800;color:#059669;letter-spacing:.08em;">YOUR TICKET</div>'
        +'<div style="font-size:1.6rem;font-weight:900;color:#0f172a;letter-spacing:.03em;">'+escH(r.ref||r.id||'')+'</div>'
        +'<div style="display:flex;gap:1rem;flex-wrap:wrap;margin:.6rem 0;">'
        +'<div>'+window.WCBiz.ticketQR(r.ref||r.id||'',150,r.verify_token||'')+'</div>'
        +'<div style="font-size:.86rem;color:#0f172a;line-height:1.7;">'
        +'<div><strong>'+escH(r.passenger||r.customer||r.name||'')+'</strong></div>'
        +'<div>Seats: '+escH(seats==null?'—':seats)+'</div>'
        +'<div>Status: '+escH(r.status||'received')+(r.payment_status?' · '+escH(r.payment_status):'')+'</div>'
        +'</div></div>'
        +'<div style="display:flex;gap:.5rem;flex-wrap:wrap;">'
        +'<button type="button" data-tk-print="1" style="padding:.5rem 1rem;border-radius:9px;border:none;background:#0f172a;color:#fff;font-weight:700;cursor:pointer;">Print ticket</button>'
        +'<button type="button" data-tk-cancel="1" style="padding:.5rem 1rem;border-radius:9px;border:1px solid #f87171;background:#fff;color:#b91c1c;font-weight:700;cursor:pointer;">Cancel ticket</button>'
        +'</div></div>';
      var cb=out.querySelector('[data-tk-cancel]');
      if(cb)cb.addEventListener('click',function(){
        if(!confirm('Cancel this ticket? Capacity is released immediately.'))return;
        window.WCBiz.cancel(entity,r.id||'',{phone:phone,email:(r.email||'')}).then(function(p){
          if(p&&p.success)note('Ticket cancelled.',true,out);
          else note('Cancel failed: '+((p&&p.error)||'error'),false,out);
        }).catch(function(){note('Network error. Try again.',false,out);});
      });
      var pb=out.querySelector('[data-tk-print]');
      if(pb)pb.addEventListener('click',function(){
        if(!window.WCBiz.printTicket(r))note('Popup blocked — allow popups to print the ticket.',false,out);
      });
    }).catch(function(){out.innerHTML='';note('Network error. Try again.',false,box);});
  });
});
// Public catalog: [data-wc-catalog="trips"] renders live inventory from the
// database (same rows the admin manages). Optional data-wc-filter="k:v,k:v".
// Search: <form data-wc-search="trips" data-wc-target="#results"> submits
// its inputs as filters and renders matches into the target block.
function renderCatalog(box,rows){
  if(!rows.length){box.innerHTML='<p style="color:#64748b">No matches found. Try different search details.</p>';return;}
  var cols=[];rows.slice(0,12).forEach(function(r){Object.keys(r||{}).forEach(function(k){
    if(['id','created_at','updated_at','idempotency_key'].indexOf(k)>=0)return;
    if(cols.indexOf(k)<0&&cols.length<6)cols.push(k);});});
  var h='<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.86rem;">'
    +'<thead><tr>'+cols.map(function(c){return '<th style="text-align:left;padding:.55rem .7rem;color:#64748b;font-size:.72rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">'+escH(c.replace(/_/g,' '))+'</th>';}).join('')+'</tr></thead><tbody>';
  rows.slice(0,50).forEach(function(r){
    h+='<tr>'+cols.map(function(c){var v=r[c];if(Array.isArray(v))v=v.join(', ');
      return '<td style="padding:.55rem .7rem;border-bottom:1px solid #f1f5f9;">'+escH(v==null?'—':v)+'</td>';}).join('')+'</tr>';});
  box.innerHTML=h+'</tbody></table></div>';
}
function catalogQuery(entity,filters){
  var payload=Object.assign({entity:entity},filters||{});
  var qp={};Object.keys(payload).forEach(function(k){if(k!=='entity'&&k!=='slug'&&k!=='action')qp[k]=payload[k];});
  var q='?'+new URLSearchParams(qp).toString();
  var base='';try{base=(window.__WC_NODE_API__||NODE_API).replace(/\/$/,'');}catch(e){base=NODE_API;}
  function viaPhp(){
    var url=(function(){try{return new URL('api/business-ops.php',document.baseURI).toString();}catch(e){return 'api/business-ops.php';}})();
    return postJson(url,Object.assign({action:'list',slug:SLUG},payload),12000).then(function(r){return r.body;});
  }
  return fetch(base+'/node-api/'+encodeURIComponent(SLUG)+'/'+encodeURIComponent(entity)+q).then(function(r){return r.json();}).catch(function(err){
    if(err&&(err.name==='AbortError'||err.name==='TypeError'))return viaPhp();
    throw err;});
}
function parseFilterAttr(box){
  var out={};var s=box.getAttribute('data-wc-filter')||'';
  s.split(',').forEach(function(pair){var kv=pair.split(':');if(kv.length===2&&kv[0].trim()&&kv[1].trim())out[kv[0].trim()]=kv[1].trim();});
  return out;
}
document.querySelectorAll('[data-wc-catalog]').forEach(function(box){
  var entity=(box.getAttribute('data-wc-catalog')||'').toLowerCase().replace(/[^a-z0-9_]/g,'');
  if(!entity)return;
  box.innerHTML='<p style="color:#64748b">Loading…</p>';
  catalogQuery(entity,parseFilterAttr(box)).then(function(j){
    if(j&&j.success)renderCatalog(box,j.data||[]);
    else box.innerHTML='<p style="color:#b91c1c">Could not load. Please refresh.</p>';
  }).catch(function(){box.innerHTML='<p style="color:#b91c1c">Network error. Please refresh.</p>';});
});
document.querySelectorAll('form[data-wc-search]').forEach(function(f){
  if(f.dataset.wcSearchBound)return;f.dataset.wcSearchBound='1';
  f.addEventListener('submit',function(e){
    // Page-owned search (its own handler already preventDefaulted) renders
    // custom cards — never overwrite them with the generic table.
    if(e.defaultPrevented)return;
    e.preventDefault();
    var entity=(f.getAttribute('data-wc-search')||'').toLowerCase().replace(/[^a-z0-9_]/g,'');
    var target=document.querySelector(f.getAttribute('data-wc-target')||'[data-wc-results]');
    if(!entity||!target){note('Search is not configured.',false,f);return;}
    var fd=new FormData(f);var filters={};
    fd.forEach(function(v,k){v=String(v).trim();if(v&&k!=='company')filters[k]=v;});
    // Render token: if the page renders its own custom results meanwhile
    // (replacing our placeholder), yield to it instead of overwriting.
    var tok='w'+Date.now().toString(36)+Math.random().toString(36).slice(2,7);
    target.innerHTML='<p data-wc-live="'+tok+'" style="color:#64748b">Searching…</p>';
    catalogQuery(entity,filters).then(function(j){
      try{if(!target.querySelector('[data-wc-live="'+tok+'"]'))return;}catch(err){return;}
      if(j&&j.success)renderCatalog(target,j.data||[]);
      else note('Search failed: '+((j&&j.error)||'error'),false,f);
    }).catch(function(){note('Network error. Try again.',false,f);});
  });
});
// Booking: seat hold -> confirm -> QR ticket -> cancel (bus reference workflow)
// Step navigation + QR + ticket lookup share one helper set so every
// generated page behaves the same without custom scroll/QR code.
function wcGotoStep(id){
  var el=document.querySelector('[data-wc-step="'+String(id).replace(/"/g,'')+'"]');
  if(!el)return false;
  document.querySelectorAll('[data-wc-step]').forEach(function(step){
    if(step!==el)step.setAttribute('hidden','');
  });
  el.removeAttribute('hidden');
  try{if(el.style)el.style.display='';}catch(e){}
  document.querySelectorAll('.step-indicator-item').forEach(function(item){
    var ind=item.getAttribute('data-indicator');
    if(ind===id||(id==='ticket'&&ind==='ticket')){
      item.classList.add('active');
    }else{
      item.classList.remove('active');
    }
  });
  try{
    var offset=0;
    document.querySelectorAll('header,nav,[role="banner"]').forEach(function(bar){
      var style=window.getComputedStyle(bar),rect=bar.getBoundingClientRect();
      if((style.position==='fixed'||style.position==='sticky')&&rect.top<=1&&rect.bottom>0)
        offset=Math.max(offset,rect.bottom);
    });
    el.style.scrollMarginTop=Math.ceil(offset+20)+'px';
  }catch(e){}
  try{el.scrollIntoView({behavior:'smooth',block:'start'});}catch(e){
    try{el.scrollIntoView();}catch(e2){}
  }
  try{
    if(!el.hasAttribute('tabindex'))el.setAttribute('tabindex','-1');
    el.focus({preventScroll:true});
  }catch(e){}
  return true;
}
function wcTicketQRFallback(ref,size){
  ref=String(ref==null?'':ref);size=size||180;
  var safe=ref.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  return '<div style="width:'+size+'px;max-width:100%;border:2px dashed #6366f1;border-radius:12px;padding:1rem;text-align:center;background:#f8fafc;">'
    +'<div style="font-size:.72rem;font-weight:800;color:#6366f1;letter-spacing:.08em;">SHOW THIS CODE AT BOARDING</div>'
    +'<div style="font-size:1.5rem;font-weight:900;color:#0f172a;letter-spacing:.04em;margin:.4rem 0;word-break:break-all;">'+safe+'</div>'
    +'<div style="font-size:.72rem;color:#64748b;">QR image offline — this code scans the same.</div></div>';
}
function wcTicketVerifyUrl(ref,token){
  ref=String(ref==null?'':ref);
  try{
    var base='';
    try{base=new URL('verify.php',document.baseURI).toString();}catch(e){base='verify.php';}
    var full=new URL(base,document.baseURI||window.location.href);
    full.searchParams.set('ref',ref);
    if(token)full.searchParams.set('t',String(token));
    return full.toString();
  }catch(e){
    var q='verify.php?ref='+encodeURIComponent(ref);
    if(token)q+='&t='+encodeURIComponent(String(token));
    return q;
  }
}
function wcTicketQR(ref,size,token){
  ref=String(ref==null?'':ref);size=size||180;
  var q=String(ref).split(String.fromCharCode(92)).join('').split("'").join('').slice(0,64);
  var data=wcTicketVerifyUrl(ref,token||'');
  var url='https://api.qrserver.com/v1/create-qr-code/?size='+size+'x'+size+'&data='+encodeURIComponent(data);
  return '<img src="'+url+'" width="'+size+'" height="'+size+'" alt="Ticket QR '+q+'" loading="lazy" style="border-radius:12px;background:#fff;padding:6px;max-width:100%;" onerror="this.outerHTML=window.WCBiz.ticketQRFallback(\''+q+'\','+size+')">';
}
// Printable ticket (backend record in, print window out). QR encodes the
// ticket-verification URL (ref + token when present) so a scan opens the
// authoritative record; the printout always matches the backend row.
function wcPrintTicketHTML(r){
  r=r||{};
  var seats=r.seats;
  if(Object.prototype.toString.call(seats)==='[object Array]')seats=seats.join(', ');
  var ref=String(r.ref||r.id||'');
  var qr='https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='+encodeURIComponent(wcTicketVerifyUrl(ref,r.verify_token||''));
  return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ticket '+escH(ref)+'</title>'
    +'<style>body{font-family:Arial,sans-serif;padding:32px;color:#111;}'
    +' .t{max-width:480px;margin:0 auto;border:2px solid #059669;border-radius:16px;padding:28px;}'
    +' h1{font-size:15px;color:#059669;letter-spacing:2px;margin:0;}'
    +' .ref{font-size:34px;font-weight:900;margin:6px 0 14px;word-break:break-all;}'
    +' table{width:100%;font-size:14px;border-collapse:collapse;}td{padding:5px 0;}'
    +' img.qr{display:block;margin:14px auto;}'
    +' .foot{font-size:11px;color:#666;margin-top:14px;}'
    +' @media print{.noprint{display:none;}}</style></head><body><div class="t">'
    +'<h1>BUS TICKET</h1><div class="ref">'+escH(ref)+'</div>'
    +'<table><tr><td>Passenger</td><td><strong>'+escH(r.passenger||r.customer||r.name||'')+'</strong></td></tr>'
    +'<tr><td>Seats</td><td><strong>'+escH(seats==null?'—':seats)+'</strong></td></tr>'
    +'<tr><td>Status</td><td>'+escH(r.status||'confirmed')+(r.payment_status?' · '+escH(r.payment_status):'')+'</td></tr></table>'
    +'<img class="qr" src="'+qr+'" width="180" height="180" alt="Ticket QR">'
    +'<div style="text-align:center;font-size:12px;color:#444;">Show this QR at boarding</div>'
    +'<div class="foot">Printed '+escH(new Date().toLocaleString())+'</div>'
    +'<div class="noprint" style="text-align:center;margin-top:16px;">'
    +'<button onclick="window.print()" style="padding:10px 26px;font-size:15px;font-weight:700;background:#059669;color:#fff;border:none;border-radius:9px;cursor:pointer;">Print</button></div>'
    // Escaped so later HTML tail injectors can never match this inline string.
    +'<\/div><\/body><\/html>';
}
function wcPrintTicket(r){
  try{
    var w=window.open('','_blank','width=560,height=780');
    if(!w)return false;
    w.document.write(wcPrintTicketHTML(r||{}));
    w.document.close();
    try{w.focus();}catch(e){}
    try{var t=w.setTimeout(function(){try{w.print();}catch(e){}},600);}catch(e){}
    return true;
  }catch(e){return false;}
}
// Merge with the page's own WCBiz (AI pages keep UI state: selectedSeats,
// activeTrip, gotoStep, custom helpers). Backend data methods ALWAYS win so
// holds, bookings, tickets, payments and history hit the real backend.
var __pageBiz=(window.WCBiz&&typeof window.WCBiz==='object')?window.WCBiz:{};
function __wcFn(v){return typeof v==='function';}
// Trip/seat context from live page state (never trusts stale hidden inputs
// alone): the confirm step can no longer fail with "Missing trip" while the
// visitor visibly selected seats on this same page.
function __wcTripCtx(){
  var trip='',seats=[];
  try{
    var WB=window.WCBiz||{};
    var at=WB.activeTrip||__pageBiz.activeTrip||null;
    if(at&&typeof at==='object')trip=String(at.id||at.trip_id||'');
    var ss=WB.selectedSeats||__pageBiz.selectedSeats||[];
    if(Object.prototype.toString.call(ss)==='[object Array]'&&ss.length)seats=ss.slice();
    if(!trip){var hf=document.querySelector('input[name=trip_id],select[name=trip_id],input[name=trip]');if(hf&&hf.value)trip=String(hf.value).trim();}
    if(!seats.length){
      var sel=[];
      var btns=document.querySelectorAll('.wc-seat.selected,[data-seat].selected');
      for(var i=0;i<btns.length;i++){var s=btns[i].getAttribute('data-seat');if(s)sel.push(s);}
      if(sel.length)seats=sel;
    }
  }catch(e){}
  return {trip_id:trip,seats:seats};
}
function wcHold(entity,payload){
  payload=Object.assign({},payload||{});
  var ctx=__wcTripCtx();
  if(!payload.trip_id&&ctx.trip_id)payload.trip_id=ctx.trip_id;
  if((!payload.seats||!payload.seats.length)&&ctx.seats.length)payload.seats=ctx.seats;
  return api('hold',Object.assign({entity:entity},payload)).then(function(j){
    try{
      if(j&&j.success&&j.data){
        window.__WC_HOLD__={entity:entity,trip_id:j.data.trip_id||payload.trip_id,
          seats:j.data.seats||payload.seats,ref:j.data.ref,hold_id:j.data.id,at:Date.now()};
        try{window.WCBiz.holdRef=j.data.ref;}catch(e){}
        // Page-level code (AI generations) reads res.ref directly.
        if(j.ref===undefined)j.ref=j.data.ref;
      }
    }catch(e){}
    return j;
  });
}
var __PRIVATE_STORES={bookings:1,appointments:1,orders:1,inquiries:1,subscribers:1,payments:1,customers:1,tickets:1,members:1};
function wcList(entity,query){
  var qp=Object.assign({entity:entity},query||{});
  function pageFallback(){
    if(__PRIVATE_STORES[String(entity||'').toLowerCase()])return[];
    if(__wcFn(__pageBiz.list)){try{var r=__pageBiz.list.call(__pageBiz,entity,query);return r;}catch(e){}}
    return[];
  }
  return api('list',qp).then(function(j){
    if(j&&j.success&&Object.prototype.toString.call(j.data)==='[object Array]'&&j.data.length)return j.data;
    return pageFallback();
  }).catch(function(){return pageFallback();});
}
window.WCBiz=Object.assign({},__pageBiz,{
  spec:SPEC,slug:SLUG,api:api,
  hold:wcHold,
  list:wcList,
  cancel:function(entity,id,identity){return api('cancel',Object.assign({entity:entity,id:id},identity||{}));},
  history:function(entity,identifier){return api('list',Object.assign({entity:entity},identifier||{}));},
  pay:function(ref,method,amount,entity,identity){
    identity=identity||{};
    var booking=__pageBiz.lastBooking||window.__WC_LAST_BOOKING__||{};
    var form=document.querySelector('[data-wc-form="booking"]');
    if(form){
      ['email','phone'].forEach(function(key){
        var field=form.elements.namedItem(key);
        if(!identity[key]&&field)identity[key]=field.value||'';
      });
    }
    return api('payment_intent',Object.assign({
      entity:entity||'bookings',ref:ref,method:method,
      email:identity.email||booking.email||'',
      phone:identity.phone||booking.phone||''
    }));
  },
  ticket:function(entity,phone,ref){return api('ticket',{entity:entity||'bookings',phone:phone,ref:ref});},
  verify:function(ref,token,entity){return api('verify',{entity:entity||'bookings',ref:ref,token:token||''});},
  ticketVerifyUrl:wcTicketVerifyUrl,
  gotoStep:(__wcFn(__pageBiz.gotoStep)?__pageBiz.gotoStep:wcGotoStep),
  ticketQR:wcTicketQR,
  ticketQRFallback:wcTicketQRFallback,
  printTicket:wcPrintTicket,
  create:wcCreate
});
document.addEventListener('click',function(event){
  var button=event.target&&event.target.closest
    ?event.target.closest('button[data-pay],button[data-payment-method]'):null;
  if(!button)return;
  event.stopImmediatePropagation();
  event.preventDefault();
  var method=String(button.getAttribute('data-pay')||button.getAttribute('data-payment-method')||'').toLowerCase();
  var booking=window.__WC_LAST_BOOKING__||{};
  var ref=String(booking.ref||window.__WC_LAST_REF__||window.WCBiz.bookingRef||'');
  if(!ref){note('Confirm your booking before choosing a payment method.',false,button.parentNode);return;}
  if(method!=='counter'&&method!=='online'){note('This payment method is not supported.',false,button.parentNode);return;}
  button.disabled=true;
  window.WCBiz.pay(ref,method,booking.amount||0,'bookings',{
    email:booking.email||'',phone:booking.phone||''
  }).then(function(result){
    if(!result||!result.success)throw new Error((result&&result.error)||'Payment request could not be saved.');
    note(result.message||'Payment request saved.',true,button.parentNode);
  }).catch(function(error){
    note(error.message||'Network error. Please try again.',false,button.parentNode);
  }).finally(function(){button.disabled=false;});
},true);
// Dedup core: same customer intent within 2s shares one request.
var wcCreateVOLATILE={hold_id:1,idempotency_key:1,company:1};
var wcCreateIDENTITY={passenger:1,customer:1,name:1,email:1,phone:1};
function wcNormKey(entity,rec){
  rec=rec||{};
  var idkeys=Object.keys(rec).filter(function(k){return wcCreateIDENTITY[k]&&!wcCreateVOLATILE[k];}).sort();
  if(idkeys.length)return String(entity||'')+'|'+idkeys.map(function(k){return k+'='+JSON.stringify(rec[k]);}).join('&');
  var keys=Object.keys(rec).filter(function(k){return !wcCreateVOLATILE[k];}).sort();
  return String(entity||'')+'|'+keys.map(function(k){return k+'='+JSON.stringify(rec[k]);}).join('&');
}
function wcCreate(entity,record,opts){
  record=Object.assign({},record||{});
  if(opts&&typeof opts==='object'){
    ['trip_id','seats','hold_ref','hold_id'].forEach(function(k){
      if(record[k]===undefined&&opts[k]!==undefined)record[k]=opts[k];
    });
  }
  try{
    // Direct page calls often skip the generic form handler, so they lack
    // trip context — fill from the live hold / visible page selection.
    if(entity==='bookings'||entity==='appointments'){
      var HS3=window.__WC_HOLD__||null;
      var CX3=__wcTripCtx();
      if(record.trip_id===undefined)record.trip_id=((HS3&&HS3.entity===entity&&HS3.trip_id)||CX3.trip_id||undefined);
      if(record.seats===undefined)record.seats=((HS3&&HS3.entity===entity&&HS3.seats)||CX3.seats||undefined);
      if(record.hold_ref===undefined&&HS3&&HS3.entity===entity&&HS3.ref)record.hold_ref=HS3.ref;
    }
  }catch(e){}
  var key=wcNormKey(entity,record);
  var now=Date.now();
  if(key===wcCreate._k&&(now-wcCreate._t)<2000&&wcCreate._p)return wcCreate._p;
  var payload={entity:entity,record:record};
  if(opts&&typeof opts==='object'&&opts.idempotency_key)payload.idempotency_key=opts.idempotency_key;
  var p=api('create',payload);
  wcCreate._k=key;wcCreate._t=now;wcCreate._p=p;
  p.then(function(j){
    try{
      if(j&&j.success&&j.data){
        if(entity==='bookings'&&j.data.ref){
          try{
            window.WCBiz.bookingRef=j.data.ref;
            window.__WC_LAST_BOOKING__=j.data;
            window.__WC_LAST_REF__=j.data.ref;
          }catch(e){}
        }
      }else if(j&&!j.success&&entity==='bookings'){
        var msg=String(j.error||'');
        if(/Missing trip|Select at least/i.test(msg)){
          var bf=document.querySelector('[data-wc-form="booking"]');
          note('Pick your bus and press "Hold Seats" first — then fill this form.',false,bf||undefined);
        }
      }
    }catch(e){}
  },function(){});
  return p;
}
// After a real backend confirmation: printable QR ticket and matching history lookup.
document.addEventListener('wc:booking-confirmed',function(ev){
  var rec=null;
  try{rec=(ev&&ev.detail&&((ev.detail.data)||(ev.detail.body&&ev.detail.body.data)))||null;}catch(e){}
  setTimeout(function(){
    try{
      var tb=document.getElementById('ticket-box');
      if(tb&&!tb.querySelector('[data-wc-print]')){
        var b=document.createElement('button');
        b.setAttribute('type','button');b.setAttribute('data-wc-print','1');
        b.textContent='Print ticket with QR';
        b.style.cssText='margin-top:0.9rem;padding:0.6rem 1.2rem;border-radius:10px;border:none;background:#0f172a;color:#fff;font-weight:700;cursor:pointer;font-family:inherit;';
        b.addEventListener('click',function(){if(!wcPrintTicket(rec||{}))note('Popup blocked — allow popups to print the ticket.',false,tb);});
        tb.appendChild(b);
      }
    }catch(e){}
    try{
      var hb=document.querySelector('[data-wc-history]');
      var identity=hb?String(hb.getAttribute('data-wc-id')||'email'):'email';
      var contact=rec?String(rec[identity]||''):'';
      if(hb&&contact){
        var inp=hb.querySelector('[data-wc-history-id]');
        var frm=hb.querySelector('form');
        if(inp&&frm&&!inp.value){inp.value=contact;try{frm.dispatchEvent(new Event('submit',{cancelable:true}));}catch(e2){}}
      }
    }catch(e){}
  },400);
});
})();</script>
JS;
}

function business_runtime_inject(string $html, array $spec, string $slug): string {
    // Strip AI-generated mock backends before anything else touches the HTML.
    $html = wc_neutralize_mock_wcbiz($html);

    $isBus = wc_business_requires_bus_booking($spec);
    if ($isBus) {
        // The customer portal is generated next to index.html; make it
        // reachable from the navbar even when the AI did not add a link.
        $html = business_customer_portal_inject($html);
    }
    if ($isBus && stripos($html, 'data-wc-live-bus-booking') === false) {
        $widget = bus_booking_widget_html($slug);
        if (preg_match('/\bid\s*=\s*(["\'])booking\1/i', $html)) {
            $widget = preg_replace('/\bid="booking"/i', 'id="wc-live-bus-booking"', $widget, 1);
        }
        if (preg_match('/<script\b[^>]*data-wc-biz-runtime\b[^>]*>/i', $html, $runtimeTag, PREG_OFFSET_CAPTURE)) {
            $html = substr_replace($html, $widget, $runtimeTag[0][1], 0);
        } else {
            $html = wc_insert_before_body_end($html, $widget);
        }
    }
    if (stripos($html, 'wc-biz-runtime') !== false) return $html;
    $specTag = '<script type="application/json" id="wc-biz-spec">' . htmlspecialchars(json_encode($spec, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</script>';
    $js = business_runtime_js($spec, $slug);
    $bundle = $specTag . $js;
    // Always the LAST body close: generated JS may legitimately contain the
    // string "</body>" inside templates, and matching that used to truncate
    // this runtime script (leaving the AI mock in control).
    return wc_insert_before_body_end($html, $bundle);
}
