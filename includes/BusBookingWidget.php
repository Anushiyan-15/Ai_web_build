<?php
// includes/BusBookingWidget.php — reusable live bus search, seat hold and ticket UI.
if (!function_exists('bus_booking_widget_html')) {
    function bus_booking_widget_html(string $slug = ''): string {
        $portalUrl = 'customer.php';
        $accountUrl = $portalUrl . (strpos($portalUrl, '?') === false ? '?' : '&') . 'mode=register';
        $html = <<<'HTML'
<style data-wc-bus-booking-style>
[data-wc-live-bus-booking]{--wc-bus-accent:var(--accent,var(--primary,#0f766e));margin:clamp(2rem,6vw,5rem) auto;padding:clamp(1rem,3vw,2rem);max-width:1100px;border:1px solid var(--border,#dbe3e7);border-radius:18px;background:var(--surface,#fff);color:var(--text,#17212b);font:inherit}
[data-wc-live-bus-booking] h2,[data-wc-live-bus-booking] h3{color:inherit}
[data-wc-live-bus-booking] .form-group{display:grid;gap:.45rem}
[data-wc-live-bus-booking] .form-group label{font-size:.9rem;font-weight:650}
[data-wc-live-bus-booking] .form-input{width:100%;min-height:44px;padding:.7rem .8rem;border:1px solid var(--border,#cbd5e1);border-radius:9px;background:var(--background,#fff);color:inherit;font:inherit}
[data-wc-live-bus-booking] .btn-submit{min-height:44px;margin-top:1rem;padding:.7rem 1rem;border:0;border-radius:9px;background:var(--wc-bus-accent);color:#fff;font:inherit;font-weight:700;cursor:pointer}
[data-wc-live-bus-booking] .btn-submit:disabled{opacity:.55;cursor:not-allowed}
[data-wc-live-bus-booking] .bus-trip-list{display:grid;gap:.75rem;margin-top:1rem}
[data-wc-live-bus-booking] .bus-trip-option{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:.5rem 1rem;padding:1rem 0;border-bottom:1px solid var(--border,#dbe3e7)}
[data-wc-live-bus-booking] .bus-trip-option p{grid-column:1}
[data-wc-live-bus-booking] .bus-trip-option .btn-submit{grid-column:2;grid-row:1/3;margin:0}
[data-wc-live-bus-booking] [data-wc-step][hidden]{display:none!important}
[data-wc-live-bus-booking] .bus-seat-grid{display:grid;grid-template-columns:repeat(4,minmax(44px,1fr));gap:.6rem;max-width:360px;margin:1rem 0}
[data-wc-live-bus-booking] .bus-seat{min-height:44px;border:1px solid var(--border,#b8c4ca);border-radius:8px;background:var(--background,#fff);color:inherit;font:inherit;font-weight:650;cursor:pointer}
[data-wc-live-bus-booking] .bus-seat.selected{border-color:var(--wc-bus-accent);background:var(--wc-bus-accent);color:#fff}
[data-wc-live-bus-booking] .bus-seat.held{background:#fef3c7;border-color:#d97706;color:#92400e;cursor:not-allowed;text-decoration:none}
[data-wc-live-bus-booking] .bus-seat.booked{background:#fee2e2;border-color:#b91c1c;color:#7f1d1d;cursor:not-allowed;text-decoration:line-through}
[data-wc-live-bus-booking] .bus-seat:disabled{opacity:.75;cursor:not-allowed}
[data-wc-live-bus-booking] .bus-seat:disabled:not(.held):not(.booked){opacity:.4;text-decoration:line-through}
[data-wc-live-bus-booking] .bus-legend{display:flex;gap:1rem;flex-wrap:wrap;margin:.75rem 0 0;padding:0;list-style:none;font-size:.82rem;color:var(--text-muted,#64748b)}
[data-wc-live-bus-booking] .bus-legend li{display:flex;align-items:center;gap:.4rem}
[data-wc-live-bus-booking] .bus-legend .sw{display:inline-block;width:14px;height:14px;border-radius:4px;border:1px solid #b8c4ca}
[data-wc-live-bus-booking] .bus-legend .sw-available{background:#fff}
[data-wc-live-bus-booking] .bus-legend .sw-selected{background:var(--wc-bus-accent);border-color:var(--wc-bus-accent)}
[data-wc-live-bus-booking] .bus-legend .sw-held{background:#fbbf24;border-color:#d97706}
[data-wc-live-bus-booking] .bus-legend .sw-booked{background:#f87171;border-color:#b91c1c}
[data-wc-live-bus-booking] .bus-legend .sw-unavailable{background:#e2e8f0;border-color:#94a3b8}
[data-wc-live-bus-booking] .bus-status{min-height:1.4em}
@media(max-width:600px){[data-wc-live-bus-booking] .bus-trip-option{grid-template-columns:1fr}[data-wc-live-bus-booking] .bus-trip-option .btn-submit{grid-column:1;grid-row:auto;width:100%}}
@media(prefers-reduced-motion:reduce){[data-wc-live-bus-booking], [data-wc-live-bus-booking] *{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
</style>
<div id="booking" class="widget-wrap" data-wc-live-bus-booking>
    <div style="margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:14px;">
        <h2 style="font-size:22px;font-weight:700;color:inherit;">Book your journey</h2>
        <p style="margin-top:5px;color:var(--text-muted);font-size:14px;">Live schedules are shown below. Filter by route or travel date, choose available seats, and confirm your ticket.</p>
    </div>
    <form id="busTripSearch" method="POST" onsubmit="return false" data-wc-search="trips" data-wc-target="#busTripResults">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
            <div class="form-group"><label for="busRoute">Route (optional)</label><input id="busRoute" name="route" class="form-input" placeholder="For example, Chennai"></div>
            <div class="form-group"><label for="busDate">Travel date (optional)</label><input id="busDate" name="date" type="date" class="form-input"></div>
        </div>
        <button type="submit" class="btn-submit" id="busFindButton">Find buses</button>
    </form>
    <div id="busTripResults" class="bus-trip-list" data-wc-results aria-live="polite">
        <p class="bus-status">Loading available trips…</p>
    </div>
    <section data-wc-step="seats" hidden>
        <div class="widget-wrap" style="margin:24px 0 0;padding:22px;">
            <h3 style="font-size:18px;">Choose your seats</h3>
            <p id="busTripSummary" style="color:var(--text-muted);font-size:13px;"></p>
            <ul class="bus-legend" aria-label="Seat legend">
                <li><span class="sw sw-available" aria-hidden="true"></span>Available</li>
                <li><span class="sw sw-selected" aria-hidden="true"></span>Selected</li>
                <li><span class="sw sw-held" aria-hidden="true"></span>Held (checkout)</li>
                <li><span class="sw sw-booked" aria-hidden="true"></span>Booked</li>
                <li><span class="sw sw-unavailable" aria-hidden="true"></span>Unavailable</li>
            </ul>
            <div id="busSeatMap" class="bus-seat-grid" role="group" aria-label="Available seats"></div>
            <button type="button" class="btn-submit" id="busHoldButton">Hold seats</button>
            <p id="busHoldStatus" class="bus-status" role="status" aria-live="polite"></p>
        </div>
    </section>
    <section data-wc-step="details" hidden>
        <div class="widget-wrap" style="margin:24px 0 0;padding:22px;">
            <h3 style="font-size:18px;">Passenger details</h3>
            <form id="busPassengerForm" data-wc-form="booking" data-wc-thanks="Booking request submitted. Your ticket is pending operator approval.">
                <input type="hidden" name="trip_id" id="busTripId">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
                    <div class="form-group"><label for="busPassenger">Full name</label><input id="busPassenger" name="passenger" class="form-input" autocomplete="name" required maxlength="120"></div>
                    <div class="form-group"><label for="busPhone">Phone</label><input id="busPhone" name="phone" type="tel" class="form-input" autocomplete="tel" required maxlength="30"></div>
                    <div class="form-group"><label for="busEmail">Email</label><input id="busEmail" name="email" type="email" class="form-input" autocomplete="email" required maxlength="160"></div>
                </div>
                <button type="submit" class="btn-submit">Confirm booking</button>
            </form>
        </div>
    </section>
    <section data-wc-step="ticket" hidden>
        <div class="widget-wrap" style="margin:24px 0 0;padding:22px;">
            <h3 style="font-size:18px;">Your ticket</h3>
            <div id="busTicketBox" style="margin-top:12px;"></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
                <button type="button" data-wc-pay="counter" class="btn-submit" style="width:auto;">Pay at Counter</button>
                <button type="button" data-wc-pay="online" class="btn-submit" style="width:auto;background:#334155;">Request Online Payment</button>
                <button type="button" id="busPrintButton" class="btn-submit" style="width:auto;background:#334155;">Print ticket</button>
                <button type="button" id="busCancelButton" class="btn-submit" style="width:auto;background:#b91c1c;">Cancel ticket</button>
            </div>
            <p id="busTicketStatus" class="bus-status" role="status" aria-live="polite">Your booking will be saved for the operator. Payment is not complete until the operator records it.</p>
            <p id="busPaymentStatus" class="bus-status" role="status" aria-live="polite"></p>
        </div>
    </section>
    <section style="margin-top:24px;">
        <div class="widget-wrap" style="padding:22px;">
            <h3 style="font-size:18px;">My tickets</h3>
            <p>Sign in to view your booking history, approval updates, and payment records.</p>
            <p style="margin:.75rem 0 1rem;">
                <a href="__WC_BUS_PORTAL_URL__" data-wc-booking-dashboard data-wc-account-link style="font-weight:700;">Sign in, create an account, or open your booking dashboard</a>
            </p>
            <h3 style="font-size:18px;margin-top:20px;">Find a ticket by reference</h3>
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
