<?php
// includes/BusBookingWidget.php — reusable live bus search, seat hold and ticket UI.
if (!function_exists('bus_booking_widget_html')) {
    function bus_booking_widget_html(string $slug = ''): string {
        $portalUrl = 'customer.php';
        $accountUrl = $portalUrl . (strpos($portalUrl, '?') === false ? '?' : '&') . 'mode=register';
        $html = <<<'HTML'
<style data-wc-bus-booking-style>
[data-wc-live-bus-booking]{--wc-bus-accent:var(--accent,var(--primary,#2563eb));margin:clamp(2rem,6vw,5rem) auto;padding:clamp(1.5rem,3.5vw,2.5rem);max-width:1150px;border:1px solid var(--border,#dbe3e7);border-radius:20px;background:var(--surface,#fff);color:var(--text,#0f172a);font:inherit;box-shadow:0 10px 30px -5px rgba(0,0,0,0.05)}
[data-wc-live-bus-booking] h2,[data-wc-live-bus-booking] h3{color:inherit;font-weight:800}
[data-wc-live-bus-booking] .booking-steps-indicator{display:flex;justify-content:space-between;position:relative;margin:0 auto 2.2rem;max-width:580px;padding:0 1rem}
[data-wc-live-bus-booking] .booking-steps-indicator::before{content:"";position:absolute;top:16px;left:40px;right:40px;height:2px;background:var(--border,#e2e8f0);z-index:1}
[data-wc-live-bus-booking] .step-indicator-item{display:flex;flex-direction:column;align-items:center;gap:6px;font-size:.82rem;font-weight:700;color:var(--text-muted,#64748b);position:relative;z-index:2;background:var(--surface,#fff);padding:0 8px;cursor:pointer}
[data-wc-live-bus-booking] .step-indicator-item.active{color:var(--wc-bus-accent)}
[data-wc-live-bus-booking] .step-indicator-dot{width:32px;height:32px;border-radius:50%;background:#f1f5f9;border:1.5px solid var(--border,#cbd5e1);color:#64748b;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:800;transition:all .2s ease}
[data-wc-live-bus-booking] .step-indicator-item.active .step-indicator-dot{background:var(--wc-bus-accent);border-color:var(--wc-bus-accent);color:#fff;box-shadow:0 0 0 4px rgba(37,99,235,0.18)}
[data-wc-live-bus-booking] .form-group{display:grid;gap:.45rem}
[data-wc-live-bus-booking] .form-group label{font-size:.86rem;font-weight:700}
[data-wc-live-bus-booking] .form-input{width:100%;min-height:46px;padding:.75rem .95rem;border:1px solid var(--border,#cbd5e1);border-radius:10px;background:var(--background,#fff);color:inherit;font:inherit;font-size:.95rem;transition:border-color .2s,box-shadow .2s}
[data-wc-live-bus-booking] .form-input:focus{outline:none;border-color:var(--wc-bus-accent);box-shadow:0 0 0 3px rgba(37,99,235,0.15)}
[data-wc-live-bus-booking] .btn-submit{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:.75rem 1.4rem;border:0;border-radius:10px;background:var(--wc-bus-accent);color:#fff;font:inherit;font-weight:750;cursor:pointer;transition:all .2s ease;text-decoration:none}
[data-wc-live-bus-booking] .btn-submit:hover:not(:disabled){filter:brightness(1.08);transform:translateY(-1px)}
[data-wc-live-bus-booking] .btn-submit:disabled{opacity:.55;cursor:not-allowed}
[data-wc-live-bus-booking] .btn-outline{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:.5rem 1rem;border:1.5px solid var(--border,#cbd5e1);border-radius:9px;background:transparent;color:inherit;font:inherit;font-weight:650;font-size:.85rem;cursor:pointer;transition:all .2s}
[data-wc-live-bus-booking] .btn-outline:hover{border-color:var(--wc-bus-accent);color:var(--wc-bus-accent)}
[data-wc-live-bus-booking] .bus-trip-list{display:grid;gap:1rem;margin-top:1.5rem}
[data-wc-live-bus-booking] .bus-trip-option{display:flex;justify-content:space-between;align-items:center;gap:1.25rem;padding:1.4rem;border:1px solid var(--border,#e2e8f0);border-radius:14px;background:var(--surface,#fff);box-shadow:0 2px 6px rgba(0,0,0,0.03);transition:all .2s ease}
[data-wc-live-bus-booking] .bus-trip-option:hover{border-color:var(--wc-bus-accent);box-shadow:0 6px 18px rgba(37,99,235,0.08);transform:translateY(-1px)}
[data-wc-live-bus-booking] [data-wc-step][hidden]{display:none!important}
[data-wc-live-bus-booking] .bus-seat-grid{display:grid;grid-template-columns:repeat(2,46px) 24px repeat(2,46px);gap:10px;max-width:360px;margin:1.5rem auto;justify-content:center}
[data-wc-live-bus-booking] .aisle-gap{width:24px;display:flex;align-items:center;justify-content:center}
[data-wc-live-bus-booking] .bus-seat{width:46px;height:46px;border:1.5px solid var(--border,#cbd5e1);border-radius:8px;background:var(--background,#fff);color:inherit;font:inherit;font-size:.82rem;font-weight:750;cursor:pointer;transition:all .2s}
[data-wc-live-bus-booking] .bus-seat:hover:not(:disabled){border-color:var(--wc-bus-accent);background:rgba(37,99,235,0.08)}
[data-wc-live-bus-booking] .bus-seat.selected{border-color:var(--wc-bus-accent);background:var(--wc-bus-accent);color:#fff;box-shadow:0 2px 8px rgba(37,99,235,0.35)}
[data-wc-live-bus-booking] .bus-seat.held{background:#fef3c7;border-color:#d97706;color:#92400e;cursor:not-allowed;text-decoration:none}
[data-wc-live-bus-booking] .bus-seat.booked{background:#fee2e2;border-color:#b91c1c;color:#7f1d1d;cursor:not-allowed;text-decoration:line-through}
[data-wc-live-bus-booking] .bus-seat:disabled{opacity:.75;cursor:not-allowed}
[data-wc-live-bus-booking] .bus-seat:disabled:not(.held):not(.booked){opacity:.4;text-decoration:line-through}
[data-wc-live-bus-booking] .bus-legend{display:flex;gap:1.25rem;justify-content:center;flex-wrap:wrap;margin:1.2rem 0;padding:0;list-style:none;font-size:.84rem;color:var(--text-muted,#64748b)}
[data-wc-live-bus-booking] .bus-legend li{display:flex;align-items:center;gap:.45rem}
[data-wc-live-bus-booking] .bus-legend .sw{display:inline-block;width:15px;height:15px;border-radius:4px;border:1px solid #b8c4ca}
[data-wc-live-bus-booking] .bus-legend .sw-available{background:#fff}
[data-wc-live-bus-booking] .bus-legend .sw-selected{background:var(--wc-bus-accent);border-color:var(--wc-bus-accent)}
[data-wc-live-bus-booking] .bus-legend .sw-held{background:#fbbf24;border-color:#d97706}
[data-wc-live-bus-booking] .bus-legend .sw-booked{background:#f87171;border-color:#b91c1c}
[data-wc-live-bus-booking] .bus-legend .sw-unavailable{background:#e2e8f0;border-color:#94a3b8}
[data-wc-live-bus-booking] .bus-status{min-height:1.4em;margin-top:.75rem;font-size:.9rem}
@media(max-width:680px){[data-wc-live-bus-booking] .bus-trip-option{flex-direction:column;align-items:flex-start}[data-wc-live-bus-booking] .bus-trip-option .btn-submit{width:100%}}
@media(prefers-reduced-motion:reduce){[data-wc-live-bus-booking], [data-wc-live-bus-booking] *{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
</style>
<div id="booking" class="widget-wrap" data-wc-live-bus-booking>
    <div style="margin-bottom:24px;border-bottom:1px solid var(--border);padding-bottom:18px;text-align:center;">
        <span style="font-size:.85rem;font-weight:750;letter-spacing:.05em;text-transform:uppercase;color:var(--wc-bus-accent);">Instant Reservation</span>
        <h2 style="font-size:26px;font-weight:800;color:inherit;margin-top:4px;">Book Your Journey</h2>
        <p style="margin-top:6px;color:var(--text-muted);font-size:15px;">Search daily routes, choose your preferred seats, and confirm your boarding ticket in minutes.</p>
    </div>

    <!-- 4-Step Interactive Wizard Progress Indicator -->
    <div class="booking-steps-indicator" aria-label="Booking steps">
        <div class="step-indicator-item active" data-indicator="search" onclick="if(window.WCBiz)WCBiz.gotoStep('search')">
            <div class="step-indicator-dot">1</div>
            <span>Search</span>
        </div>
        <div class="step-indicator-item" data-indicator="seats">
            <div class="step-indicator-dot">2</div>
            <span>Seats</span>
        </div>
        <div class="step-indicator-item" data-indicator="details">
            <div class="step-indicator-dot">3</div>
            <span>Details</span>
        </div>
        <div class="step-indicator-item" data-indicator="ticket">
            <div class="step-indicator-dot">4</div>
            <span>Ticket &amp; Pay</span>
        </div>
    </div>

    <!-- STEP 1: Search Form & Trip Results -->
    <div data-wc-step="search">
        <form id="busTripSearch" method="POST" onsubmit="return false" data-wc-search="trips" data-wc-target="#busTripResults">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;background:var(--background,#f8fafc);padding:20px;border-radius:14px;border:1px solid var(--border,#e2e8f0);align-items:end;">
                <div class="form-group"><label for="busRoute">From / To Route</label><input id="busRoute" name="route" class="form-input" placeholder="Origin or Destination (e.g. Chennai)"></div>
                <div class="form-group"><label for="busDate">Travel Date</label><input id="busDate" name="date" type="date" class="form-input"></div>
                <div><button type="submit" class="btn-submit" id="busFindButton" style="width:100%;">Find Buses</button></div>
            </div>
        </form>
        <div id="busTripResults" class="bus-trip-list" data-wc-results aria-live="polite">
            <p class="bus-status">Loading available trips…</p>
        </div>
    </div>

    <!-- STEP 2: Interactive Seat Map (4-column with Center Aisle) -->
    <section data-wc-step="seats" hidden>
        <div style="margin:16px 0 0;padding:22px;border:1px solid var(--border,#e2e8f0);border-radius:16px;background:var(--surface,#fff);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                <div>
                    <h3 style="font-size:20px;">Select Your Seats (Max 6)</h3>
                    <p id="busTripSummary" style="color:var(--text-muted);font-size:14px;margin-top:2px;"></p>
                </div>
                <button type="button" class="btn-outline" onclick="window.WCBiz.gotoStep('search')">← Back to Search</button>
            </div>
            <ul class="bus-legend" aria-label="Seat legend">
                <li><span class="sw sw-available" aria-hidden="true"></span>Available</li>
                <li><span class="sw sw-selected" aria-hidden="true"></span>Selected</li>
                <li><span class="sw sw-held" aria-hidden="true"></span>Held (15 min)</li>
                <li><span class="sw sw-booked" aria-hidden="true"></span>Booked</li>
                <li><span class="sw sw-unavailable" aria-hidden="true"></span>Unavailable</li>
            </ul>
            <div id="busSeatMap" class="bus-seat-grid" role="group" aria-label="Available seats"></div>
            <div style="text-align:center;margin-top:20px;">
                <button type="button" class="btn-submit" id="busHoldButton" style="min-width:220px;">Hold Seats (15 Min)</button>
                <p id="busHoldStatus" class="bus-status" role="status" aria-live="polite"></p>
            </div>
        </div>
    </section>

    <!-- STEP 3: Passenger Details & Hold Timer -->
    <section data-wc-step="details" hidden>
        <div style="margin:16px 0 0;padding:24px;border:1px solid var(--border,#e2e8f0);border-radius:16px;background:var(--surface,#fff);max-width:720px;margin-left:auto;margin-right:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                <h3 style="font-size:20px;">Passenger Details</h3>
                <button type="button" class="btn-outline" onclick="window.WCBiz.gotoStep('seats')">← Back to Seats</button>
            </div>
            <div id="hold-summary-box" style="background:#dbeafe;border:1px solid #bfdbfe;color:#1e40af;padding:12px 16px;border-radius:10px;font-size:.9rem;font-weight:650;margin-bottom:18px;">
                ⏱️ Hold active for 15 minutes. Complete details to confirm your ticket.
            </div>
            <form id="busPassengerForm" data-wc-form="booking" data-wc-thanks="Booking request submitted! Your ticket reference and QR code are ready." style="display:grid;gap:16px;">
                <input type="hidden" name="trip_id" id="busTripId">
                <div class="form-group"><label for="busPassenger">Full Name</label><input id="busPassenger" name="passenger" class="form-input" autocomplete="name" required maxlength="120" placeholder="e.g. Rahul Sharma"></div>
                <div class="form-group"><label for="busPhone">Mobile Phone</label><input id="busPhone" name="phone" type="tel" class="form-input" autocomplete="tel" required maxlength="30" placeholder="+91 98765 43210"></div>
                <div class="form-group"><label for="busEmail">Email Address</label><input id="busEmail" name="email" type="email" class="form-input" autocomplete="email" required maxlength="160" placeholder="name@example.com"></div>
                <button type="submit" class="btn-submit" style="margin-top:8px;">Confirm &amp; Proceed to Ticket</button>
            </form>
        </div>
    </section>

    <!-- STEP 4: Boarding Ticket & Payment Options -->
    <section data-wc-step="ticket" hidden>
        <div style="margin:16px 0 0;padding:26px;border:1px solid var(--border,#e2e8f0);border-radius:18px;background:var(--surface,#fff);max-width:680px;margin-left:auto;margin-right:auto;text-align:center;">
            <span style="font-size:.85rem;font-weight:750;letter-spacing:.05em;text-transform:uppercase;color:#16a34a;">Booking Confirmed</span>
            <h3 style="font-size:22px;margin:4px 0 16px;">Your Boarding Ticket</h3>
            <div id="busTicketBox" style="margin:16px auto;padding:20px;border:1.5px dashed var(--border,#cbd5e1);border-radius:14px;background:#f8fafc;"></div>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:20px;">
                <button type="button" data-wc-pay="counter" class="btn-submit" style="width:auto;">Pay at Counter</button>
                <button type="button" data-wc-pay="online" class="btn-submit" style="width:auto;background:#334155;">Pay Online Now</button>
                <button type="button" id="busPrintButton" class="btn-outline" style="width:auto;">🖨️ Print Ticket</button>
                <button type="button" id="busCancelButton" class="btn-outline" style="width:auto;color:#b91c1c;border-color:#fca5a5;">Cancel Booking</button>
            </div>
            <p id="busTicketStatus" class="bus-status" role="status" aria-live="polite" style="color:var(--text-muted);font-size:.88rem;margin-top:14px;">Your booking is recorded in our system. Payment can be completed at boarding or online.</p>
            <p id="busPaymentStatus" class="bus-status" role="status" aria-live="polite"></p>
        </div>
    </section>

    <!-- Customer Portal & Booking History Section -->
    <section style="margin-top:36px;border-top:1px solid var(--border,#e2e8f0);padding-top:28px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;background:var(--background,#f8fafc);padding:24px;border-radius:16px;border:1px solid var(--border,#e2e8f0);">
            <div style="max-width:580px;">
                <h3 style="font-size:18px;margin-bottom:4px;">Customer Portal &amp; Booking History</h3>
                <p style="color:var(--text-muted);font-size:14px;margin:0;">Sign in or create an account to view previous trips, verify boarding passes, check approval updates, or manage bookings.</p>
            </div>
            <a href="__WC_BUS_PORTAL_URL__" data-wc-booking-dashboard data-wc-account-link class="btn-submit" style="background:#1e293b;">
                <span>👤</span> Open My Account / Dashboard
            </a>
        </div>
        <div style="margin-top:24px;padding:20px;background:var(--surface,#fff);border:1px solid var(--border,#e2e8f0);border-radius:14px;">
            <h4 style="font-size:15px;font-weight:750;margin-bottom:8px;">Find a ticket by reference</h4>
            <div data-wc-ticket-lookup="bookings"></div>
        </div>
    </section>
</div>
<script>
(function(){
    var search=document.getElementById('busTripSearch'),results=document.getElementById('busTripResults');
    var seatMap=document.getElementById('busSeatMap'),holdButton=document.getElementById('busHoldButton');
    var holdStatus=document.getElementById('busHoldStatus'),ticketStatus=document.getElementById('busTicketStatus');
    var paymentStatus=document.getElementById('busPaymentStatus');
    var pickedTrip=null,pickedSeats=[],lastBooking=null,currentHold=null;
    function setStatus(el,text,state){el.textContent=text;el.setAttribute('data-state',state||'');}
    function api(action,payload){
        if(!window.WCBiz||typeof window.WCBiz.api!=='function')return Promise.reject(new Error('Booking service is not ready. Refresh and try again.'));
        return window.WCBiz.api(action,payload);
    }
    function renderTrip(trip){
        var card=document.createElement('article'),title=document.createElement('h3'),meta=document.createElement('p');
        var route=trip.route||((trip.origin||'')+' to '+(trip.destination||''));
        card.className='bus-trip-option';title.textContent=route;
        meta.textContent=[trip.date,trip.departure||trip.departure_time,trip.bus||trip.bus_type,
            trip.price==null?'':'Fare: '+trip.price].filter(Boolean).join(' · ');
        var choose=document.createElement('button');choose.type='button';choose.className='btn-submit';
        choose.textContent='Select this bus';choose.disabled=!(parseInt(trip.total_seats||trip.capacity||'40',10)>0);
        choose.addEventListener('click',function(){selectTrip(trip);});
        card.appendChild(title);card.appendChild(meta);card.appendChild(choose);return card;
    }
    function tripDate(trip){
        return String(trip.date||trip.departure_date||trip.trip_date||'').slice(0,10);
    }
    function loadTrips(route,date){
        var button=document.getElementById('busFindButton');
        button.disabled=true;button.textContent='Loading trips…';results.textContent='Loading available trips…';
        return releaseCurrentHold().then(function(){
            return api('list',{entity:'trips',route:route||'',date:date||''});
        }).then(function(response){
            if(!response||!response.success||!Array.isArray(response.data)){
                throw new Error((response&&response.error)||'Could not load live trips.');
            }
            var today=document.getElementById('busDate').min;
            var trips=response.data.filter(function(trip){
                var dateValue=tripDate(trip);
                return !dateValue||!today||dateValue>=today;
            });
            trips.sort(function(a,b){
                var dateOrder=tripDate(a).localeCompare(tripDate(b));
                if(dateOrder)return dateOrder;
                return String(a.departure||a.departure_time||'').localeCompare(String(b.departure||b.departure_time||''));
            });
            results.textContent='';
            if(!trips.length){
                results.textContent=route||date
                    ?'No upcoming trips match those filters. Try a different route or date.'
                    :'No upcoming trips are available yet. Please check back later.';
                return;
            }
            trips.forEach(function(trip){results.appendChild(renderTrip(trip));});
        }).catch(function(error){
            results.textContent='';
            setStatus(results,error.message||'Could not load trips. Please try again.','error');
        }).finally(function(){
            button.disabled=false;button.textContent='Find buses';
        });
    }
    function releaseCurrentHold(){
        if(!currentHold)return Promise.resolve();
        var hold=currentHold;currentHold=null;
        if(window.__WC_HOLD__&&window.__WC_HOLD__.ref===hold.ref)window.__WC_HOLD__=null;
        return api('release_hold',{entity:'bookings',ref:hold.ref}).then(function(response){
            if(!response||!response.success){currentHold=hold;throw new Error((response&&response.error)||'Could not release the previous seat hold.');}
        });
    }
    function selectTrip(trip){
        releaseCurrentHold().then(function(){
        pickedTrip=trip;pickedSeats=[];seatMap.textContent='Loading seat availability…';
        document.getElementById('busTripId').value=trip.id;
        document.getElementById('busTripSummary').textContent=
            [trip.route||((trip.origin||'')+' to '+(trip.destination||'')),trip.date,trip.departure||trip.departure_time,
                trip.price==null?'':'Fare: '+trip.price].filter(Boolean).join(' · ');
        window.WCBiz.activeTrip=trip;window.WCBiz.selectedSeats=pickedSeats;
        api('availability',{entity:'bookings',trip_id:trip.id}).then(function(response){
            if(!response||!response.success||!response.data)throw new Error((response&&response.error)||'Could not load seat availability.');
            var capacity=parseInt(response.data.total_seats||trip.total_seats||trip.capacity||'0',10);
            if(!Number.isFinite(capacity)||capacity<1)throw new Error('This trip does not have a seat layout yet.');
            var reserved=response.data.reserved_seats||[];
            var held=response.data.held_seats||[];
            var booked=response.data.booked_seats||[];
            // Dynamic rows from live capacity (4 seats per row, A–Z): small
            // buses render A–J, larger fleets extend rows — never hardcoded.
            var rowCount=Math.min(26,Math.max(10,Math.ceil(capacity/4)));
            var rows=[];for(var ri=0;ri<rowCount;ri++)rows.push(String.fromCharCode(65+ri));
            seatMap.textContent='';
            for(let row=0;row<rows.length;row++)for(let col=1;col<=4;col++){
                if(col===3){
                    var aisle=document.createElement('div');
                    aisle.className='aisle-gap';
                    seatMap.appendChild(aisle);
                }
                let seatName=rows[row]+col,seatIndex=row*4+col;
                if(seatIndex>capacity)break;
                let seat=document.createElement('button');seat.type='button';seat.className='bus-seat';
                seat.textContent=seatName;seat.setAttribute('data-seat',seatName);seat.setAttribute('aria-pressed','false');
                let isHeld=held.indexOf(seatName)!==-1,isBooked=booked.indexOf(seatName)!==-1;
                let isTaken=isHeld||isBooked||reserved.indexOf(seatName)!==-1;
                if(isHeld)seat.classList.add('held');
                if(isBooked)seat.classList.add('booked');
                seat.disabled=isTaken;
                seat.setAttribute('aria-label','Seat '+seatName+(isHeld?', held during checkout':(isBooked?', booked':(isTaken?', unavailable':', available'))));
                if(isTaken&&!isHeld&&!isBooked)seat.classList.add('booked');
                seat.addEventListener('click',function(){
                    var name=seat.getAttribute('data-seat'),idx=pickedSeats.indexOf(name);
                    if(idx===-1){if(pickedSeats.length>=6){setStatus(holdStatus,'You can select up to six seats per booking.','error');return;}pickedSeats.push(name);}
                    else pickedSeats.splice(idx,1);
                    seat.classList.toggle('selected',idx===-1);seat.setAttribute('aria-pressed',idx===-1?'true':'false');
                    window.WCBiz.selectedSeats=pickedSeats.slice();
                });
                seatMap.appendChild(seat);
            }
            window.WCBiz.gotoStep('seats');
        }).catch(function(error){seatMap.textContent='';setStatus(holdStatus,error.message,'error');window.WCBiz.gotoStep('seats');});
        }).catch(function(error){setStatus(holdStatus,error.message,'error');});
    }
    search.addEventListener('submit',function(event){
        event.preventDefault();
        loadTrips(document.getElementById('busRoute').value.trim(),document.getElementById('busDate').value);
    });
    holdButton.addEventListener('click',function(){
        if(!pickedTrip||!pickedSeats.length){setStatus(holdStatus,'Select at least one available seat.','error');return;}
        holdButton.disabled=true;holdButton.textContent='Holding seats…';
        window.WCBiz.hold('bookings',{trip_id:pickedTrip.id,seats:pickedSeats}).then(function(response){
            if(!response||!response.success||!response.data)throw new Error((response&&response.error)||'Could not hold seats.');
            currentHold=response.data;
            setStatus(holdStatus,'Seats held for 15 minutes. Hold reference: '+response.data.ref,'success');
            window.WCBiz.gotoStep('details');
        }).catch(function(error){setStatus(holdStatus,error.message||'Seat hold failed. Refresh availability and try again.','error');})
        .finally(function(){holdButton.disabled=false;holdButton.textContent='Hold seats';});
    });
    document.getElementById('busPassengerForm').addEventListener('wc:booking-confirmed',function(event){
        var booking=(event.detail&&event.detail.data)||null;
        if(!booking||!booking.ref){setStatus(ticketStatus,'Booking was saved, but its ticket reference was not returned. Check My tickets.','error');return;}
        currentHold=null;
        lastBooking=booking;
        var box=document.getElementById('busTicketBox'),ref=document.createElement('div'),details=document.createElement('p');
        ref.className='ref';ref.textContent=booking.ref;
        box.textContent='';box.appendChild(ref);
        box.insertAdjacentHTML('beforeend',window.WCBiz.ticketQR(booking.ref,180,booking.verify_token||''));
        var verifyLink=document.createElement('p');
        var verifyUrl=(window.WCBiz.ticketVerifyUrl?window.WCBiz.ticketVerifyUrl(booking.ref,booking.verify_token||''):('verify.php?ref='+encodeURIComponent(booking.ref)));
        verifyLink.innerHTML='Verify this ticket: <a href="'+verifyUrl.replace(/"/g,'')+'" target="_blank" rel="noopener">open verification</a>';
        details.textContent=(booking.passenger||'')+' · Trip '+(booking.trip_id||'')+' · Seats '+(Array.isArray(booking.seats)?booking.seats.join(', '):booking.seats||'')+
            ' · '+(booking.status||'pending')+' · '+(booking.payment_status||'unpaid');
        box.appendChild(details);
        box.appendChild(verifyLink);
        setStatus(ticketStatus,'Booking request saved. Payment remains unpaid until the operator confirms receipt.','success');
        setStatus(paymentStatus,'','');
        window.WCBiz.gotoStep('ticket');
    });
    document.querySelectorAll('[data-wc-pay]').forEach(function(button){
        button.addEventListener('click',function(){
            if(!lastBooking||!lastBooking.ref){setStatus(paymentStatus,'Confirm your booking before choosing a payment method.','error');return;}
            button.disabled=true;
            var method=button.getAttribute('data-wc-pay')||'counter';
            setStatus(paymentStatus,method==='counter'?'Sending counter-payment request…':'Sending online-payment request…','');
            window.WCBiz.pay(lastBooking.ref,method,lastBooking.amount,'bookings',{
                email:lastBooking.email||'',
                phone:lastBooking.phone||''
            }).then(function(response){
                if(!response||!response.success)throw new Error((response&&response.error)||'Payment request could not be saved.');
                lastBooking=response.data||lastBooking;
                var msg=response.message||(method==='counter'
                    ?'Counter payment selected. Your booking remains unpaid until staff records the payment.'
                    :'Online payment requested. The operator will follow up; no online charge was made.');
                setStatus(paymentStatus,msg,'success');
            }).catch(function(error){
                setStatus(paymentStatus,error.message||'Network error. Please try again.','error');
            }).finally(function(){button.disabled=false;});
        });
    });
    document.getElementById('busPrintButton').addEventListener('click',function(){
        if(!lastBooking||!window.WCBiz.printTicket(lastBooking))setStatus(ticketStatus,'Allow pop-ups to print your ticket.','error');
    });
    document.getElementById('busCancelButton').addEventListener('click',function(){
        if(!lastBooking)return;
        if(!window.confirm('Cancel this booking?'))return;
        api('cancel',{entity:'bookings',id:lastBooking.id,phone:lastBooking.phone,email:lastBooking.email||''}).then(function(response){
            if(!response||!response.success)throw new Error((response&&response.error)||'Cancellation failed.');
            lastBooking=response.data;setStatus(ticketStatus,'Ticket cancelled. Seat capacity is released.','success');
        }).catch(function(error){setStatus(ticketStatus,error.message,'error');});
    });
    var dateField=document.getElementById('busDate'),today=new Date();
    today.setMinutes(today.getMinutes()-today.getTimezoneOffset());dateField.min=today.toISOString().slice(0,10);
    document.addEventListener('DOMContentLoaded',function(){loadTrips('','');},{once:true});
})();
</script>
HTML;
        return str_replace(
            ['__WC_BUS_PORTAL_URL__', '__WC_CUSTOMER_ACCOUNT_URL__'],
            [htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8'), htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8')],
            $html
        );
    }
}
