<?php
// TEMP harness — builds published/__bustest2 + seeds trips. Deleted after drive.
require __DIR__ . '/../includes/BusinessRuntime.php';
require __DIR__ . '/../includes/PublishedAdminKit.php';
require __DIR__ . '/../includes/AdminPanelGenerator.php';
require __DIR__ . '/../includes/SiteApiBridge.php';
$slug = '__bustest2';
$root = dirname(__DIR__);
$dir = $root . '/published/' . $slug;
@mkdir($dir . '/api', 0755, true);
file_put_contents($dir . '/api/business-ops.php',
    "<?php\nrequire dirname(__DIR__, 3) . '/api/business-ops.php';\n");
$pass = 'BusTest123!';
$order = ['order_id' => 'TMP', 'site_name' => 'Bus Loop Test', 'slug' => $slug,
    'admin_username' => 'busadmin', 'admin_email' => 'o@e.com', 'contact_email' => 'o@e.com',
    'admin_password_hash' => password_hash($pass, PASSWORD_DEFAULT),
    'admin_entities' => [
        ['id' => 'trips', 'name' => 'Trips', 'icon' => '🚌', 'fields' => [
            ['name' => 'route', 'label' => 'Route', 'type' => 'text', 'required' => true],
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'departure', 'label' => 'Departure', 'type' => 'text', 'required' => true],
            ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'required' => true]]],
        ['id' => 'bookings', 'name' => 'Bookings', 'icon' => '🎫', 'fields' => [
            ['name' => 'passenger', 'label' => 'Passenger', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => true],
            ['name' => 'trip_id', 'label' => 'Trip', 'type' => 'text', 'required' => false],
            ['name' => 'seats', 'label' => 'Seats', 'type' => 'text', 'required' => false]]],
        ['id' => 'inquiries', 'name' => 'Inquiries', 'icon' => '✉️', 'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false]]],
    ],
    'admin_seed_empty' => true];
AdminPanelGenerator::install($dir . '/admin', $order);
pak_install($dir . '/admin', $order);
pak_public_handlers($dir, $order, pak_entities_for_order($order));
writeSiteApiBridge($dir);
$page = <<<'HTML'
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bus Loop Test — Book Your Journey</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f1f5f9;color:#0f172a}
.wrap{max-width:860px;margin:0 auto;padding:1.5rem}
nav{display:flex;gap:1rem;background:#0f172a;color:#fff;padding:.8rem 1.5rem}
nav a{color:#fff;font-weight:700;text-decoration:none}
.card{background:#fff;border-radius:14px;padding:1.25rem;margin:1rem 0;box-shadow:0 4px 14px rgba(0,0,0,.08)}
.trip{border:1.5px solid #cbd5e1;border-radius:12px;padding:1rem;margin:.6rem 0}
.btn{background:#6366f1;color:#fff;border:none;border-radius:9px;padding:.65rem 1.2rem;font-weight:700;font-size:.9rem;cursor:pointer}
#seat-map{display:grid;grid-template-columns:repeat(4,52px);gap:8px;margin:1rem 0}
.wc-seat{height:44px;border-radius:8px 8px 2px 2px;border:2px solid #94a3b8;background:#f8fafc;font-weight:800;cursor:pointer}
.wc-seat.selected{background:#6366f1;color:#fff;border-color:#4f46e5}
input{padding:.6rem .9rem;border:1.5px solid #cbd5e1;border-radius:9px;font:inherit;margin:.25rem 0;width:100%;box-sizing:border-box;max-width:320px}
#ticket-box{border:2px solid #059669;border-radius:14px;padding:1.2rem;background:#f0fdf4}
#ticket-box .ref{font-size:2rem;font-weight:900}
</style></head>
<body>
<nav><a href="#booking">Book</a><span>Bus Loop Test</span></nav>
<div class="wrap">
<section id="booking">
<h1>Book Your Journey</h1>
<div class="card">
<form method="POST" onsubmit="return false" data-wc-search="trips" data-wc-target="#trip-results">
<input name="route" placeholder="Route (e.g. Chennai)">
<input name="date" type="date">
<button type="submit" class="btn">Find My Bus</button>
</form>
</div>
<div id="trip-results" data-wc-results><p>Search above — live trips from the database appear here.</p></div>
<div id="demo-trips"></div>
<div data-wc-step="seats" hidden>
<div class="card"><h2>Choose your seats</h2>
<div id="seat-map"></div>
<button type="button" class="btn" id="hold-btn">Hold Seats</button>
<div id="hold-info" style="margin-top:.6rem;font-weight:700"></div>
</div>
</div>
<div data-wc-step="details" hidden>
<div class="card"><h2>Passenger details</h2>
<form data-wc-form="booking">
<input name="company" style="display:none">
<input name="passenger" required placeholder="Full name">
<input name="phone" required placeholder="Phone">
<input name="email" type="email" placeholder="Email">
<button type="submit" class="btn">Confirm Booking</button>
</form>
</div>
</div>
<div data-wc-step="ticket" hidden>
<div class="card"><h2>Your ticket</h2><div id="ticket-box"></div>
<div style="margin-top:.8rem;display:flex;gap:.5rem;flex-wrap:wrap">
<button type="button" class="btn" data-pay="counter">Pay at Counter</button>
<button type="button" class="btn" data-pay="online" style="background:#0ea5e9">Pay Online</button>
<button type="button" id="cancel-link" style="border:1px solid #f87171;background:#fff;color:#b91c1c;border-radius:9px;padding:.65rem 1.2rem;font-weight:700;cursor:pointer">Cancel</button>
</div></div>
</div>
<div class="card"><h2>My tickets by phone</h2><div data-wc-history="bookings" data-wc-id="phone"></div></div>
<div class="card"><h2>Get ticket with phone + ref</h2><div data-wc-ticket-lookup="bookings"></div></div>
</section>
</div>
<script>
var picked=null, pickedSeats=[];
var DEMO_TRIPS=[{id:'D9',origin:'Demo',destination:'Town',departure:'09:00',bus:'Demo Bus',price:100,seats:20}];
function tripCard(t, live){
  return '<div class="trip"><strong>'+t.origin+' → '+t.destination+'</strong><br>'
  +t.departure+' · '+t.bus+' · Rs '+t.price+'<br>'
  +(live?'<small>Live schedule from database</small>':'<small>Demo schedule</small>')+'<br><br>'
  +'<button type="button" class="btn" data-trip="'+t.id+'">Select This Bus</button></div>';
}
function renderDemoTrips(){
  var host=document.getElementById('demo-trips');
  var h='<h3>Available buses</h3>';
  DEMO_TRIPS.forEach(function(t){ h+=tripCard({id:t.id,origin:t.origin,destination:t.destination,departure:t.departure,bus:t.bus,price:t.price}, false); });
  host.innerHTML=h;
  host.querySelectorAll('[data-trip]').forEach(function(b){
    b.addEventListener('click',function(){selectTrip(b.getAttribute('data-trip'));});
  });
}
function renderLiveTrips(rows){
  var host=document.getElementById('trip-results');
  if(!rows.length){host.innerHTML='<p>No live trips match. Demo buses below still work.</p>';return;}
  var h='<h3>Live trips</h3>';
  rows.forEach(function(r){
    var parts=String(r.route||'').split('-');
    h+=tripCard({id:r.id,origin:(parts[0]||'A').trim(),destination:(parts[1]||'B').trim(),
      departure:r.departure||'',bus:'Live Bus',price:r.price||0}, true);
  });
  host.innerHTML=h;
  host.querySelectorAll('[data-trip]').forEach(function(b){
    b.addEventListener('click',function(){selectTrip(b.getAttribute('data-trip'));});
  });
}
document.querySelector('[data-wc-search]').addEventListener('submit',function(){
  setTimeout(function(){
    window.WCBiz.list('trips',{}).then(function(j){ renderLiveTrips((j&&j.data)||[]); });
  },600);
});
function selectTrip(id){
  picked=id;pickedSeats=[];
  var map=document.getElementById('seat-map');map.innerHTML='';
  var rows=['A','B','C','D','E','F','G','H','I','J'];
  rows.forEach(function(r){for(let c=1;c<=4;c++){
    let s=r+c,btn=document.createElement('button');
    btn.type='button';btn.className='wc-seat';btn.textContent=s;btn.setAttribute('data-seat',s);
    btn.addEventListener('click',function(){
      if(btn.classList.contains('selected')){btn.classList.remove('selected');pickedSeats=pickedSeats.filter(function(x){return x!==s;});}
      else{if(pickedSeats.length>=6)return;btn.classList.add('selected');pickedSeats.push(s);}
    });
    map.appendChild(btn);
  }});
  document.getElementById('hold-info').textContent='';
  window.WCBiz.gotoStep('seats');
}
document.getElementById('hold-btn').addEventListener('click',function(){
  if(!picked||!pickedSeats.length){alert('Pick at least one seat');return;}
  window.WCBiz.hold('bookings',{trip_id:picked,seats:pickedSeats}).then(function(j){
    if(j&&j.success){
      document.getElementById('hold-info').textContent='Held! Ref '+(j.data.ref||'');
      window.WCBiz.gotoStep('details');
    }else{alert('Hold failed: '+((j&&j.error)||'error'));}
  });
});
document.querySelector('[data-wc-form="booking"]').addEventListener('wc:booking-confirmed',function(e){
  var d=(e.detail&&e.detail.data)||{};
  var seats=d.seats?(Array.isArray(d.seats)?d.seats.join(', '):d.seats):'';
  document.getElementById('ticket-box').innerHTML=
    '<div class="ref">'+d.ref+'</div>'
    +window.WCBiz.ticketQR(d.ref,180)
    +'<p>'+d.passenger+' · Seats '+seats+' · '+(d.status||'')+'</p>';
  window.__WC_LAST_BID__=d.id;window.__WC_LAST_REF__=d.ref;
  window.WCBiz.gotoStep('ticket');
});
document.querySelectorAll('[data-pay]').forEach(function(b){
  b.addEventListener('click',function(){
    window.WCBiz.pay(window.__WC_LAST_REF__||'',b.getAttribute('data-pay'),'0').then(function(p){
      alert(p&&p.success?'Payment recorded: '+p.data.status:'Pay failed: '+((p&&p.error)||''));
    });
  });
});
document.getElementById('cancel-link').addEventListener('click',function(){
  window.WCBiz.cancel('bookings',window.__WC_LAST_BID__||'').then(function(p){
    alert(p&&p.success?'Ticket cancelled.':'Cancel failed: '+((p&&p.error)||''));
  });
});
renderDemoTrips();
</script>
HTML;
$spec = ['entities' => [['id' => 'bookings']]];
$html = str_replace('</body>', '', $page);
$html .= '<script type="application/json" id="wc-biz-spec">' . htmlspecialchars(json_encode($spec), ENT_QUOTES, 'UTF-8') . '</script>';
$html .= business_runtime_js($spec, $slug) . '</body></html>';
file_put_contents($dir . '/index.html', $html);
echo "built $slug pass=$pass\n";
