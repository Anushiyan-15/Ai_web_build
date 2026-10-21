<?php
// includes/BusinessRegistry.php — Extensible business-to-admin module registry.
// Single source of truth mapping a customer's actual business to the admin
// modules, entities, APIs, validations and dashboard stats it needs.
// No fixed one-size panel: callers select ONE category (or generic fallback)
// and generate ONLY its modules. New industries register at runtime via
// business_registry_register() without touching existing businesses.

if (!function_exists('business_registry_categories')) {

function _br_f(string $name, string $label, string $type = 'text', bool $req = false, array $options = []): array {
    return ['name' => $name, 'label' => $label, 'type' => $type, 'required' => $req, 'options' => $options];
}

function business_registry_categories(): array {
    static $cats = null;
    if ($cats !== null) return $cats;
    $cats = [
    'bus' => [
        'label' => 'Bus booking & transportation',
        'description' => 'Routes, trips, seat inventory, bookings, tickets, payments, cancellations.',
        'entities' => [
            ['id'=>'buses','name'=>'Buses & Vehicles','singular'=>'Bus','icon'=>'🚍','fields'=>[
                _br_f('plate','Plate No','text',true),_br_f('capacity','Capacity','number',true),
                _br_f('layout','Seat Layout','text',false),_br_f('status','Status','select',false,['active','maintenance'])]],
            ['id'=>'routes','name'=>'Routes & Destinations','singular'=>'Route','icon'=>'🗺️','fields'=>[
                _br_f('origin','Origin','text',true),_br_f('destination','Destination','text',true),
                _br_f('distance','Distance (km)','number',false),_br_f('fare','Base Fare','number',true)]],
            ['id'=>'trips','name'=>'Trips & Schedules','singular'=>'Trip','icon'=>'🚌','fields'=>[
                _br_f('route','Route','text',true),_br_f('date','Date','date',true),
                _br_f('departure','Departure','text',true),_br_f('price','Fare','number',true),
                _br_f('total_seats','Total Seats','number',true)]],
            ['id'=>'bookings','name'=>'Bookings & Tickets','singular'=>'Booking','icon'=>'🎫','fields'=>[
                _br_f('trip_id','Trip','text',true),_br_f('seats','Seats','text',true),
                _br_f('passenger','Passenger','text',true),_br_f('phone','Phone','text',true),
                _br_f('email','Email','email',false),
                _br_f('status','Status','select',false,['held','confirmed','pending','approved','rejected','cancelled']),
                _br_f('payment_status','Payment','select',false,['unpaid','paid','refund_pending','refunded']),
                _br_f('payment_id','Payment ID','text',false),_br_f('amount','Fare total','number',false)],
                'sensitive'=>false],
            ['id'=>'payments','name'=>'Payments','singular'=>'Payment','icon'=>'🧾','fields'=>[
                _br_f('payment_id','Payment ID','text',true),_br_f('booking_ref','Booking Ref','text',true),
                _br_f('method','Method','select',true,['counter','online','card','cash']),_br_f('amount','Amount','number',true),
                _br_f('status','Status','select',false,['pending','completed','refunded']),
                _br_f('received_at','Received at','text',false)]],
            ['id'=>'customers','name'=>'Customers','singular'=>'Customer','icon'=>'👥','fields'=>[
                _br_f('name','Full Name','text',true),_br_f('email','Email','email',true),
                _br_f('phone','Phone','text',false),_br_f('status','Status','select',false,['active','inactive'])]],
        ],
        'relationships' => ['trips.route→routes.id','bookings.trip_id→trips.id','payments.booking_ref→bookings.ref','bookings.email→customers.email'],
        'workflows' => [
            ['key'=>'booking','name'=>'Seat booking lifecycle','steps'=>['search trip','hold seats (15 min)','passenger details','pending review','admin approves or rejects + email notification','QR ticket ref','admin records cash/card payment','cancel per policy → release capacity']],
            ['key'=>'refund','name'=>'Cancellation & refund','steps'=>['cancel request','validate policy','cancelled','paid→refund_pending','admin refund → refunded']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Live occupancy, revenue, upcoming trips.','frontend_feature'=>'booking form','api'=>['stats']],
            ['id'=>'buses','label'=>'Buses & Seats','icon'=>'🚍','entity'=>'buses','actions'=>['create','read','update','delete'],'why'=>'Fleet + seat layouts drive availability.','frontend_feature'=>'trip search','api'=>['create','list','update','delete']],
            ['id'=>'routes','label'=>'Routes','icon'=>'🗺️','entity'=>'routes','actions'=>['create','read','update','delete'],'why'=>'Destinations + fares.','frontend_feature'=>'route search','api'=>['create','list','update','delete']],
            ['id'=>'trips','label'=>'Trips & Schedules','icon'=>'🚌','entity'=>'trips','actions'=>['create','read','update','delete'],'why'=>'Schedules create bookable inventory.','frontend_feature'=>'seat select','api'=>['create','list','update','delete']],
            ['id'=>'bookings','label'=>'Bookings & Tickets','icon'=>'🎫','entity'=>'bookings','actions'=>['create','read','update','delete','verify'],'why'=>'Confirm / cancel / verify QR tickets.','frontend_feature'=>'booking form','api'=>['create','list','update','delete','cancel','hold','pay']],
            ['id'=>'payments','label'=>'Payments','icon'=>'🧾','entity'=>'payments','actions'=>['create','read','update','delete','refund'],'why'=>'Transaction status + permitted refunds.','frontend_feature'=>'checkout','api'=>['create','list','update','delete','pay']],
            ['id'=>'customers','label'=>'Customers','icon'=>'👥','entity'=>'customers','actions'=>['create','read','update','delete'],'why'=>'Manage registered travelers and contact details.','frontend_feature'=>'customer portal','api'=>['create','list','update','delete']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'booking form','api'=>'POST business-ops?action=create entity=bookings','entity'=>'bookings','admin_page'=>'Bookings & Tickets'],
            ['frontend'=>'seat map','api'=>'POST business-ops?action=hold','entity'=>'bookings','admin_page'=>'Bookings & Tickets'],
            ['frontend'=>'admin payment recorder','api'=>'POST business-ops?action=pay (admin only, after approval)','entity'=>'payments','admin_page'=>'Payments'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['holds expire after 15 min','no double-book same seat+trip','cancelled/expired terminal','paid cancel → refund_pending','duplicate pay rejected','idempotent retries by idempotency_key'],
        'dashboard_stats' => [
            ['key'=>'trips','label'=>'Trips','entity'=>'trips','aggregate'=>'count'],
            ['key'=>'bookings','label'=>'Bookings','entity'=>'bookings','aggregate'=>'count'],
            ['key'=>'revenue','label'=>'Revenue','entity'=>'payments','aggregate'=>'sum:amount:completed'],
            ['key'=>'occupancy','label'=>'By Status','entity'=>'bookings','aggregate'=>'status_breakdown'],
        ],
    ],
    'ecommerce' => [
        'label' => 'E-commerce & online retail',
        'description' => 'Catalog, inventory, carts, orders, coupons, shipping, payments, refunds.',
        'entities' => [
            ['id'=>'products','name'=>'Products','singular'=>'Product','icon'=>'🛍️','fields'=>[
                _br_f('title','Title','text',true),_br_f('category','Category','text',false),
                _br_f('price','Price','number',true),_br_f('stock','Stock','number',true),
                _br_f('description','Description','textarea',false),_br_f('photo','Photo URL','url',false)]],
            ['id'=>'orders','name'=>'Orders','singular'=>'Order','icon'=>'🧾','fields'=>[
                _br_f('customer','Customer','text',true),_br_f('items','Items (JSON)','textarea',true),
                _br_f('total','Total','number',true),_br_f('email','Email','email',false),
                _br_f('status','Status','select',false,['pending','paid','shipped','delivered','cancelled'])]],
            ['id'=>'customers','name'=>'Customers','singular'=>'Customer','icon'=>'👥','fields'=>[
                _br_f('name','Name','text',true),_br_f('phone','Phone','text',false),_br_f('email','email',false)]],
            ['id'=>'coupons','name'=>'Discounts & Coupons','singular'=>'Coupon','icon'=>'🏷️','fields'=>[
                _br_f('code','Code','text',true),_br_f('percent','Percent %','number',true),
                _br_f('status','Status','select',false,['active','expired'])]],
        ],
        'relationships' => ['orders.items→products.id','orders.customer→customers.id'],
        'workflows' => [
            ['key'=>'order','name'=>'Order lifecycle','steps'=>['browse catalog','cart','checkout','pay','fulfill (pending→paid→shipped→delivered)','cancel/refund per policy']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Sales, orders, stock alerts.','frontend_feature'=>'product catalog','api'=>['stats']],
            ['id'=>'products','label'=>'Products','icon'=>'🛍️','entity'=>'products','actions'=>['create','read','update','delete'],'why'=>'Catalog + stock truth.','frontend_feature'=>'product grid','api'=>['create','list','update']],
            ['id'=>'orders','label'=>'Orders','icon'=>'🧾','entity'=>'orders','actions'=>['read','update','cancel','refund'],'why'=>'Fulfill + refund workflow.','frontend_feature'=>'checkout','api'=>['list','update','cancel','pay']],
            ['id'=>'customers','label'=>'Customers','icon'=>'👥','entity'=>'customers','actions'=>['read','update'],'why'=>'Order history + support.','frontend_feature'=>'accounts','api'=>['list']],
            ['id'=>'coupons','label'=>'Coupons','icon'=>'🏷️','entity'=>'coupons','actions'=>['create','read','update','delete'],'why'=>'Promotions.','frontend_feature'=>'checkout','api'=>['create','list','update']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'product catalog','api'=>'POST business-ops?action=list entity=products','entity'=>'products','admin_page'=>'Products'],
            ['frontend'=>'checkout','api'=>'POST business-ops?action=create entity=orders','entity'=>'orders','admin_page'=>'Orders'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['orders require ≥1 item','stock decremented on confirm, restored on cancel','cancelled terminal','refunds only for paid orders'],
        'dashboard_stats' => [
            ['key'=>'products','label'=>'Products','entity'=>'products','aggregate'=>'count'],
            ['key'=>'orders','label'=>'Orders','entity'=>'orders','aggregate'=>'count'],
            ['key'=>'revenue','label'=>'Revenue','entity'=>'orders','aggregate'=>'sum:total:delivered,paid,shipped'],
            ['key'=>'orders_by_status','label'=>'By Status','entity'=>'orders','aggregate'=>'status_breakdown'],
        ],
    ],
    'restaurant' => [
        'label' => 'Restaurant & food ordering',
        'description' => 'Menu, prices, availability, orders, table reservations, hours.',
        'entities' => [
            ['id'=>'menu_items','name'=>'Menu','singular'=>'Dish','icon'=>'🍽️','fields'=>[
                _br_f('name','Dish','text',true),_br_f('category','Category','text',false),
                _br_f('price','Price','number',true),_br_f('available','Available','select',false,['yes','no'])]],
            ['id'=>'orders','name'=>'Food Orders','singular'=>'Order','icon'=>'🧾','fields'=>[
                _br_f('customer','Customer','text',true),_br_f('items','Items (JSON)','textarea',true),
                _br_f('total','Total','number',true),
                _br_f('status','Status','select',false,['pending','preparing','ready','completed','cancelled'])]],
            ['id'=>'reservations','name'=>'Table Reservations','singular'=>'Reservation','icon'=>'📅','fields'=>[
                _br_f('name','Name','text',true),_br_f('phone','Phone','text',true),
                _br_f('date','Date','date',true),_br_f('time','Time','text',true),_br_f('guests','Guests','number',true),
                _br_f('status','Status','select',false,['pending','confirmed','cancelled'])]],
        ],
        'relationships' => ['orders.items→menu_items.id'],
        'workflows' => [
            ['key'=>'order','name'=>'Food order','steps'=>['browse menu','order','confirm','prepare→ready→completed']],
            ['key'=>'reserve','name'=>'Table reservation','steps'=>['pick slot','confirm','seat / cancel']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Live orders + reservations.','frontend_feature'=>'menu','api'=>['stats']],
            ['id'=>'menu_items','label'=>'Menu','icon'=>'🍽️','entity'=>'menu_items','actions'=>['create','read','update','delete'],'why'=>'Dishes, prices, availability.','frontend_feature'=>'menu list','api'=>['create','list','update']],
            ['id'=>'orders','label'=>'Orders','icon'=>'🧾','entity'=>'orders','actions'=>['read','update','cancel'],'why'=>'Kitchen status flow.','frontend_feature'=>'order form','api'=>['list','update','cancel']],
            ['id'=>'reservations','label'=>'Reservations','icon'=>'📅','entity'=>'reservations','actions'=>['read','update','cancel'],'why'=>'Table planning.','frontend_feature'=>'reservation form','api'=>['list','update','cancel']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'menu list','api'=>'POST business-ops?action=list entity=menu_items','entity'=>'menu_items','admin_page'=>'Menu'],
            ['frontend'=>'order form','api'=>'POST business-ops?action=create entity=orders','entity'=>'orders','admin_page'=>'Food Orders'],
            ['frontend'=>'reservation form','api'=>'POST business-ops?action=create entity=reservations','entity'=>'reservations','admin_page'=>'Table Reservations'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['unavailable dishes rejected','double reservation same table+slot rejected','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'dishes','label'=>'Dishes','entity'=>'menu_items','aggregate'=>'count'],
            ['key'=>'orders','label'=>'Orders','entity'=>'orders','aggregate'=>'count'],
            ['key'=>'reservations','label'=>'Reservations','entity'=>'reservations','aggregate'=>'count'],
            ['key'=>'orders_by_status','label'=>'By Status','entity'=>'orders','aggregate'=>'status_breakdown'],
        ],
    ],
    'hotel' => [
        'label' => 'Hotels & accommodation',
        'description' => 'Rooms, availability, reservations, check-in/out, billing.',
        'entities' => [
            ['id'=>'rooms','name'=>'Rooms','singular'=>'Room','icon'=>'🏠','fields'=>[
                _br_f('number','Room No','text',true),_br_f('type','Type','text',true),
                _br_f('rate','Rate','number',true),_br_f('status','Status','select',false,['available','occupied','maintenance'])]],
            ['id'=>'reservations','name'=>'Reservations','singular'=>'Reservation','icon'=>'📅','fields'=>[
                _br_f('guest','Guest','text',true),_br_f('room','Room','text',true),
                _br_f('checkin','Check-in','date',true),_br_f('checkout','Check-out','date',true),
                _br_f('status','Status','select',false,['pending','confirmed','checked_in','checked_out','cancelled'])]],
            ['id'=>'payments','name'=>'Payments','singular'=>'Payment','icon'=>'🧾','fields'=>[
                _br_f('booking_ref','Reservation Ref','text',true),_br_f('method','Method','select',true,['counter','online']),
                _br_f('amount','Amount','number',true),_br_f('status','Status','select',false,['pending','completed','refunded'])]],
        ],
        'relationships' => ['reservations.room→rooms.number','payments.booking_ref→reservations.id'],
        'workflows' => [
            ['key'=>'stay','name'=>'Guest stay','steps'=>['search rooms','reserve','confirm','check-in','check-out','bill']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Occupancy + arrivals.','frontend_feature'=>'room search','api'=>['stats']],
            ['id'=>'rooms','label'=>'Rooms','icon'=>'🏠','entity'=>'rooms','actions'=>['create','read','update','delete'],'why'=>'Inventory + rates.','frontend_feature'=>'room list','api'=>['create','list','update']],
            ['id'=>'reservations','label'=>'Reservations','icon'=>'📅','entity'=>'reservations','actions'=>['read','update','cancel'],'why'=>'Check-in/out flow.','frontend_feature'=>'booking form','api'=>['list','update','cancel']],
            ['id'=>'payments','label'=>'Billing','icon'=>'🧾','entity'=>'payments','actions'=>['read','refund'],'why'=>'Guest billing.','frontend_feature'=>'checkout','api'=>['list','pay']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'room search','api'=>'POST business-ops?action=list entity=rooms','entity'=>'rooms','admin_page'=>'Rooms'],
            ['frontend'=>'booking form','api'=>'POST business-ops?action=create entity=reservations','entity'=>'reservations','admin_page'=>'Reservations'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['overlapping reservation same room rejected','checkout must be after checkin','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'rooms','label'=>'Rooms','entity'=>'rooms','aggregate'=>'count'],
            ['key'=>'reservations','label'=>'Reservations','entity'=>'reservations','aggregate'=>'count'],
            ['key'=>'by_status','label'=>'By Status','entity'=>'reservations','aggregate'=>'status_breakdown'],
        ],
    ],
    'salon' => [
        'label' => 'Salon & appointment services',
        'description' => 'Services, staff, slots, appointments, confirm/cancel/complete.',
        'entities' => [
            ['id'=>'services','name'=>'Services','singular'=>'Service','icon'=>'💅','fields'=>[
                _br_f('name','Service','text',true),_br_f('price','Price','number',true),_br_f('duration','Duration (min)','number',false)]],
            ['id'=>'staff','name'=>'Staff','singular'=>'Staff member','icon'=>'👥','fields'=>[
                _br_f('name','Name','text',true),_br_f('role','Role','text',false),_br_f('phone','Phone','text',false)]],
            ['id'=>'appointments','name'=>'Appointments','singular'=>'Appointment','icon'=>'📅','fields'=>[
                _br_f('customer','Customer','text',true),_br_f('service','Service','text',true),
                _br_f('date','Date','date',true),_br_f('time','Time','text',true),
                _br_f('status','Status','select',false,['pending','confirmed','completed','cancelled'])]],
        ],
        'relationships' => ['appointments.service→services.id'],
        'workflows' => [
            ['key'=>'appt','name'=>'Appointment flow','steps'=>['pick service','pick slot','confirm','remind','complete / cancel']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>"Today's book + load.",'frontend_feature'=>'booking form','api'=>['stats']],
            ['id'=>'services','label'=>'Services','icon'=>'💅','entity'=>'services','actions'=>['create','read','update','delete'],'why'=>'Pricelist truth.','frontend_feature'=>'service list','api'=>['create','list','update']],
            ['id'=>'staff','label'=>'Staff','icon'=>'👥','entity'=>'staff','actions'=>['create','read','update','delete'],'why'=>'Capacity owners.','frontend_feature'=>'team list','api'=>['create','list','update']],
            ['id'=>'appointments','label'=>'Appointments','icon'=>'📅','entity'=>'appointments','actions'=>['read','update','cancel'],'why'=>'Confirm/cancel/complete.','frontend_feature'=>'appointment form','api'=>['list','update','cancel']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'appointment form','api'=>'POST business-ops?action=create entity=appointments','entity'=>'appointments','admin_page'=>'Appointments'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['double-book same staff+slot rejected','completed/cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'services','label'=>'Services','entity'=>'services','aggregate'=>'count'],
            ['key'=>'appointments','label'=>'Appointments','entity'=>'appointments','aggregate'=>'count'],
            ['key'=>'by_status','label'=>'By Status','entity'=>'appointments','aggregate'=>'status_breakdown'],
        ],
    ],
    'clinic' => [
        'label' => 'Clinics & healthcare',
        'description' => 'Departments, practitioners, appointments, privacy-guarded patient records.',
        'entities' => [
            ['id'=>'doctors','name'=>'Practitioners','singular'=>'Practitioner','icon'=>'🩺','fields'=>[
                _br_f('name','Name','text',true),_br_f('specialization','Specialization','text',false),_br_f('phone','Phone','text',false)]],
            ['id'=>'appointments','name'=>'Appointments','singular'=>'Appointment','icon'=>'📅','fields'=>[
                _br_f('patient','Patient','text',true),_br_f('doctor','Practitioner','text',true),
                _br_f('date','Date','date',true),_br_f('time','Time','text',true),
                _br_f('status','Status','select',false,['scheduled','completed','cancelled'])]],
            ['id'=>'patients','name'=>'Patients (authorized)','singular'=>'Patient','icon'=>'👥','fields'=>[
                _br_f('name','Name','text',true),_br_f('phone','Phone','text',false),_br_f('notes','Notes','textarea',false)],
                'sensitive'=>true],
        ],
        'relationships' => ['appointments.doctor→doctors.id'],
        'workflows' => [
            ['key'=>'visit','name'=>'Visit flow','steps'=>['request slot','confirm','visit','follow-up / cancel']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Daily schedule load.','frontend_feature'=>'booking form','api'=>['stats']],
            ['id'=>'doctors','label'=>'Practitioners','icon'=>'🩺','entity'=>'doctors','actions'=>['create','read','update','delete'],'why'=>'Schedules.','frontend_feature'=>'doctor list','api'=>['create','list','update']],
            ['id'=>'appointments','label'=>'Appointments','icon'=>'📅','entity'=>'appointments','actions'=>['read','update','cancel'],'why'=>'Status + reminders.','frontend_feature'=>'appointment form','api'=>['list','update','cancel']],
            ['id'=>'patients','label'=>'Patients','icon'=>'👥','entity'=>'patients','actions'=>['read','update'],'why'=>'Authorized records only.','frontend_feature'=>'accounts','api'=>['list']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'appointment form','api'=>'POST business-ops?action=create entity=appointments','entity'=>'appointments','admin_page'=>'Appointments'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'sensitive' => true,
        'privacy' => 'Patient names/notes visible to admin/staff only; never listed publicly; per-record ownership enforced.',
        'business_rules' => ['double-book same practitioner+slot rejected','patient records admin/staff only','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'practitioners','label'=>'Practitioners','entity'=>'doctors','aggregate'=>'count'],
            ['key'=>'appointments','label'=>'Appointments','entity'=>'appointments','aggregate'=>'count'],
            ['key'=>'by_status','label'=>'By Status','entity'=>'appointments','aggregate'=>'status_breakdown'],
        ],
    ],
    'realestate' => [
        'label' => 'Real estate',
        'description' => 'Listings, availability, enquiries, viewing schedules, agents.',
        'entities' => [
            ['id'=>'properties','name'=>'Properties','singular'=>'Property','icon'=>'🏠','fields'=>[
                _br_f('title','Title','text',true),_br_f('type','Type','text',false),
                _br_f('price','Price','number',true),_br_f('location','Location','text',true),
                _br_f('status','Status','select',false,['available','reserved','sold'])]],
            ['id'=>'inquiries','name'=>'Enquiries','singular'=>'Enquiry','icon'=>'✉️','fields'=>[
                _br_f('name','Name','text',true),_br_f('phone','Phone','text',true),
                _br_f('property','Property','text',false),_br_f('message','Message','textarea',false)]],
            ['id'=>'viewings','name'=>'Viewings','singular'=>'Viewing','icon'=>'📅','fields'=>[
                _br_f('name','Name','text',true),_br_f('property','Property','text',true),
                _br_f('date','Date','date',true),_br_f('time','Time','text',true),
                _br_f('status','Status','select',false,['requested','confirmed','cancelled'])]],
        ],
        'relationships' => ['inquiries.property→properties.id','viewings.property→properties.id'],
        'workflows' => [
            ['key'=>'sale','name'=>'Listing to close','steps'=>['browse listings','enquire','viewing','offer → reserved/sold']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Live listings + leads.','frontend_feature'=>'listings','api'=>['stats']],
            ['id'=>'properties','label'=>'Properties','icon'=>'🏠','entity'=>'properties','actions'=>['create','read','update','delete'],'why'=>'Listing truth + status.','frontend_feature'=>'property grid','api'=>['create','list','update']],
            ['id'=>'inquiries','label'=>'Enquiries','icon'=>'✉️','entity'=>'inquiries','actions'=>['read','update'],'why'=>'Lead follow-up.','frontend_feature'=>'enquiry form','api'=>['list']],
            ['id'=>'viewings','label'=>'Viewings','icon'=>'📅','entity'=>'viewings','actions'=>['read','update','cancel'],'why'=>'Schedule + confirm.','frontend_feature'=>'viewing form','api'=>['list','update','cancel']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'property grid','api'=>'POST business-ops?action=list entity=properties','entity'=>'properties','admin_page'=>'Properties'],
            ['frontend'=>'enquiry form','api'=>'POST business-ops?action=create entity=inquiries','entity'=>'inquiries','admin_page'=>'Enquiries'],
            ['frontend'=>'viewing form','api'=>'POST business-ops?action=create entity=viewings','entity'=>'viewings','admin_page'=>'Viewings'],
        ],
        'roles' => ['visitor','customer','agent','admin'],
        'business_rules' => ['sold properties reject new viewings','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'properties','label'=>'Properties','entity'=>'properties','aggregate'=>'count'],
            ['key'=>'enquiries','label'=>'Enquiries','entity'=>'inquiries','aggregate'=>'count'],
            ['key'=>'viewings','label'=>'Viewings','entity'=>'viewings','aggregate'=>'count'],
        ],
    ],
    'education' => [
        'label' => 'Education & courses',
        'description' => 'Courses, enrolments, instructors, progress, fees.',
        'entities' => [
            ['id'=>'courses','name'=>'Courses','singular'=>'Course','icon'=>'🎓','fields'=>[
                _br_f('title','Title','text',true),_br_f('fee','Fee','number',true),_br_f('duration','Duration','text',false)]],
            ['id'=>'enrollments','name'=>'Enrolments','singular'=>'Enrolment','icon'=>'📝','fields'=>[
                _br_f('student','Student','text',true),_br_f('course','Course','text',true),
                _br_f('status','Status','select',false,['pending','confirmed','completed','cancelled'])]],
            ['id'=>'tutors','name'=>'Instructors','singular'=>'Instructor','icon'=>'👥','fields'=>[
                _br_f('name','Name','text',true),_br_f('subject','Subject','text',false),_br_f('phone','Phone','text',false)]],
        ],
        'relationships' => ['enrollments.course→courses.id'],
        'workflows' => [
            ['key'=>'enroll','name'=>'Enrolment flow','steps'=>['browse courses','enroll','confirm','track → complete']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Enrolments + capacity.','frontend_feature'=>'course list','api'=>['stats']],
            ['id'=>'courses','label'=>'Courses','icon'=>'🎓','entity'=>'courses','actions'=>['create','read','update','delete'],'why'=>'Catalog truth.','frontend_feature'=>'course grid','api'=>['create','list','update']],
            ['id'=>'enrollments','label'=>'Enrolments','icon'=>'📝','entity'=>'enrollments','actions'=>['read','update','cancel'],'why'=>'Registration queue.','frontend_feature'=>'enrol form','api'=>['list','update','cancel']],
            ['id'=>'tutors','label'=>'Instructors','icon'=>'👥','entity'=>'tutors','actions'=>['create','read','update','delete'],'why'=>'Teaching capacity.','frontend_feature'=>'team list','api'=>['create','list','update']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'course grid','api'=>'POST business-ops?action=list entity=courses','entity'=>'courses','admin_page'=>'Courses'],
            ['frontend'=>'enrol form','api'=>'POST business-ops?action=create entity=enrollments','entity'=>'enrollments','admin_page'=>'Enrolments'],
        ],
        'roles' => ['visitor','student','instructor','admin'],
        'business_rules' => ['duplicate enrolment same student+course rejected','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'courses','label'=>'Courses','entity'=>'courses','aggregate'=>'count'],
            ['key'=>'enrolments','label'=>'Enrolments','entity'=>'enrollments','aggregate'=>'count'],
            ['key'=>'by_status','label'=>'By Status','entity'=>'enrollments','aggregate'=>'status_breakdown'],
        ],
    ],
    'travel' => [
        'label' => 'Travel & tours',
        'description' => 'Destinations, packages, schedules, reservations, capacity, cancellations.',
        'entities' => [
            ['id'=>'packages','name'=>'Tour Packages','singular'=>'Package','icon'=>'🧳','fields'=>[
                _br_f('title','Title','text',true),_br_f('destination','Destination','text',true),
                _br_f('price','Price','number',true),_br_f('capacity','Capacity','number',true)]],
            ['id'=>'departures','name'=>'Departures','singular'=>'Departure','icon'=>'📅','fields'=>[
                _br_f('package','Package','text',true),_br_f('date','Date','date',true),_br_f('seats','Seats Left','number',false)]],
            ['id'=>'bookings','name'=>'Reservations','singular'=>'Reservation','icon'=>'🎫','fields'=>[
                _br_f('package','Package','text',true),_br_f('name','Name','text',true),_br_f('phone','Phone','text',true),
                _br_f('status','Status','select',false,['pending','confirmed','cancelled'])]],
        ],
        'relationships' => ['departures.package→packages.id','bookings.package→packages.id'],
        'workflows' => [
            ['key'=>'tour','name'=>'Tour booking','steps'=>['browse packages','reserve seats','confirm','cancel per policy']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Capacity + bookings.','frontend_feature'=>'package list','api'=>['stats']],
            ['id'=>'packages','label'=>'Packages','icon'=>'🧳','entity'=>'packages','actions'=>['create','read','update','delete'],'why'=>'Tour catalog.','frontend_feature'=>'package grid','api'=>['create','list','update']],
            ['id'=>'departures','label'=>'Departures','icon'=>'📅','entity'=>'departures','actions'=>['create','read','update','delete'],'why'=>'Dated capacity.','frontend_feature'=>'schedule','api'=>['create','list','update']],
            ['id'=>'bookings','label'=>'Reservations','icon'=>'🎫','entity'=>'bookings','actions'=>['read','update','cancel'],'why'=>'Guest list.','frontend_feature'=>'booking form','api'=>['list','update','cancel']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'package grid','api'=>'POST business-ops?action=list entity=packages','entity'=>'packages','admin_page'=>'Packages'],
            ['frontend'=>'booking form','api'=>'POST business-ops?action=create entity=bookings','entity'=>'bookings','admin_page'=>'Reservations'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['over-capacity bookings rejected','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'packages','label'=>'Packages','entity'=>'packages','aggregate'=>'count'],
            ['key'=>'reservations','label'=>'Reservations','entity'=>'bookings','aggregate'=>'count'],
            ['key'=>'by_status','label'=>'By Status','entity'=>'bookings','aggregate'=>'status_breakdown'],
        ],
    ],
    'events' => [
        'label' => 'Events & ticketing',
        'description' => 'Events, sessions, tickets, capacity, check-in, cancellations.',
        'entities' => [
            ['id'=>'events','name'=>'Events','singular'=>'Event','icon'=>'🎪','fields'=>[
                _br_f('title','Title','text',true),_br_f('date','Date','date',true),
                _br_f('venue','Venue','text',true),_br_f('capacity','Capacity','number',true)]],
            ['id'=>'tickets','name'=>'Tickets','singular'=>'Ticket','icon'=>'🎫','fields'=>[
                _br_f('event','Event','text',true),_br_f('holder','Holder','text',true),
                _br_f('ref','Reference','text',false),
                _br_f('status','Status','select',false,['reserved','confirmed','checked_in','cancelled'])]],
        ],
        'relationships' => ['tickets.event→events.id'],
        'workflows' => [
            ['key'=>'ticket','name'=>'Ticketing','steps'=>['browse events','reserve','ticket ref','check-in / cancel']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Sales + check-in.','frontend_feature'=>'event list','api'=>['stats']],
            ['id'=>'events','label'=>'Events','icon'=>'🎪','entity'=>'events','actions'=>['create','read','update','delete'],'why'=>'Event truth.','frontend_feature'=>'event grid','api'=>['create','list','update']],
            ['id'=>'tickets','label'=>'Tickets','icon'=>'🎫','entity'=>'tickets','actions'=>['read','update','cancel'],'why'=>'Verify + check-in.','frontend_feature'=>'booking form','api'=>['list','update','cancel']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'event grid','api'=>'POST business-ops?action=list entity=events','entity'=>'events','admin_page'=>'Events'],
            ['frontend'=>'booking form','api'=>'POST business-ops?action=create entity=tickets','entity'=>'tickets','admin_page'=>'Tickets'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['over-capacity tickets rejected','checked_in terminal except admin','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'events','label'=>'Events','entity'=>'events','aggregate'=>'count'],
            ['key'=>'tickets','label'=>'Tickets','entity'=>'tickets','aggregate'=>'count'],
            ['key'=>'by_status','label'=>'By Status','entity'=>'tickets','aggregate'=>'status_breakdown'],
        ],
    ],
    'rental' => [
        'label' => 'Rental & hire',
        'description' => 'Units, date ranges, handovers, returns.',
        'entities' => [
            ['id'=>'units','name'=>'Units','singular'=>'Unit','icon'=>'🔑','fields'=>[
                _br_f('name','Unit','text',true),_br_f('rate','Rate','number',true),
                _br_f('status','Status','select',false,['available','rented','maintenance'])]],
            ['id'=>'rentals','name'=>'Rentals','singular'=>'Rental','icon'=>'📝','fields'=>[
                _br_f('customer','Customer','text',true),_br_f('unit','Unit','text',true),
                _br_f('from','From','date',true),_br_f('to','To','date',true),
                _br_f('status','Status','select',false,['pending','confirmed','returned','cancelled'])]],
        ],
        'relationships' => ['rentals.unit→units.id'],
        'workflows' => [
            ['key'=>'hire','name'=>'Hire flow','steps'=>['browse units','reserve dates','handover','return / cancel']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Utilization.','frontend_feature'=>'unit list','api'=>['stats']],
            ['id'=>'units','label'=>'Units','icon'=>'🔑','entity'=>'units','actions'=>['create','read','update','delete'],'why'=>'Fleet truth.','frontend_feature'=>'unit grid','api'=>['create','list','update']],
            ['id'=>'rentals','label'=>'Rentals','icon'=>'📝','entity'=>'rentals','actions'=>['read','update','cancel'],'why'=>'Date conflicts + returns.','frontend_feature'=>'booking form','api'=>['list','update','cancel']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'unit grid','api'=>'POST business-ops?action=list entity=units','entity'=>'units','admin_page'=>'Units'],
            ['frontend'=>'booking form','api'=>'POST business-ops?action=create entity=rentals','entity'=>'rentals','admin_page'=>'Rentals'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['overlapping rental same unit rejected','returned/cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'units','label'=>'Units','entity'=>'units','aggregate'=>'count'],
            ['key'=>'rentals','label'=>'Rentals','entity'=>'rentals','aggregate'=>'count'],
        ],
    ],
    'automotive' => [
        'label' => 'Automotive sales & service',
        'description' => 'Vehicle stock, enquiries, test-drives, service jobs.',
        'entities' => [
            ['id'=>'vehicles','name'=>'Vehicles','singular'=>'Vehicle','icon'=>'🚗','fields'=>[
                _br_f('make','Make','text',true),_br_f('model','Model','text',true),
                _br_f('price','Price','number',true),_br_f('status','Status','select',false,['available','reserved','sold'])]],
            ['id'=>'service_jobs','name'=>'Service Jobs','singular'=>'Job','icon'=>'🔧','fields'=>[
                _br_f('customer','Customer','text',true),_br_f('vehicle','Vehicle','text',true),
                _br_f('issue','Issue','textarea',true),
                _br_f('status','Status','select',false,['pending','in_progress','completed','cancelled'])]],
            ['id'=>'inquiries','name'=>'Enquiries','singular'=>'Enquiry','icon'=>'✉️','fields'=>[
                _br_f('name','Name','text',true),_br_f('phone','Phone','text',true),_br_f('message','Message','textarea',false)]],
        ],
        'relationships' => ['service_jobs.vehicle→vehicles.id'],
        'workflows' => [
            ['key'=>'sales','name'=>'Vehicle sale','steps'=>['browse stock','enquire/test-drive','reserve → sold']],
            ['key'=>'service','name'=>'Service','steps'=>['book service','diagnose','repair','deliver']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Stock + jobs.','frontend_feature'=>'stock list','api'=>['stats']],
            ['id'=>'vehicles','label'=>'Vehicles','icon'=>'🚗','entity'=>'vehicles','actions'=>['create','read','update','delete'],'why'=>'Stock truth.','frontend_feature'=>'stock grid','api'=>['create','list','update']],
            ['id'=>'service_jobs','label'=>'Service Jobs','icon'=>'🔧','entity'=>'service_jobs','actions'=>['read','update','cancel'],'why'=>'Job pipeline.','frontend_feature'=>'service form','api'=>['list','update','cancel']],
            ['id'=>'inquiries','label'=>'Enquiries','icon'=>'✉️','entity'=>'inquiries','actions'=>['read','update'],'why'=>'Leads.','frontend_feature'=>'enquiry form','api'=>['list']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'stock grid','api'=>'POST business-ops?action=list entity=vehicles','entity'=>'vehicles','admin_page'=>'Vehicles'],
            ['frontend'=>'service form','api'=>'POST business-ops?action=create entity=service_jobs','entity'=>'service_jobs','admin_page'=>'Service Jobs'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['sold vehicles reject new reservations','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'vehicles','label'=>'Vehicles','entity'=>'vehicles','aggregate'=>'count'],
            ['key'=>'jobs','label'=>'Jobs','entity'=>'service_jobs','aggregate'=>'count'],
        ],
    ],
    'subscription' => [
        'label' => 'Subscription & membership',
        'description' => 'Plans, subscribers, renewals, cancellations.',
        'entities' => [
            ['id'=>'plans','name'=>'Plans','singular'=>'Plan','icon'=>'💳','fields'=>[
                _br_f('name','Plan','text',true),_br_f('price','Price','number',true),_br_f('interval','Interval','select',false,['monthly','yearly'])]],
            ['id'=>'subscribers','name'=>'Subscribers','singular'=>'Subscriber','icon'=>'👥','fields'=>[
                _br_f('name','Name','text',true),_br_f('email','email',true),
                _br_f('plan','Plan','text',true),_br_f('status','Status','select',false,['active','paused','cancelled'])]],
        ],
        'relationships' => ['subscribers.plan→plans.id'],
        'workflows' => [
            ['key'=>'sub','name'=>'Membership','steps'=>['pick plan','subscribe','renew / pause / cancel']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'MRR + churn.','frontend_feature'=>'plan list','api'=>['stats']],
            ['id'=>'plans','label'=>'Plans','icon'=>'💳','entity'=>'plans','actions'=>['create','read','update','delete'],'why'=>'Pricing truth.','frontend_feature'=>'pricing','api'=>['create','list','update']],
            ['id'=>'subscribers','label'=>'Subscribers','icon'=>'👥','entity'=>'subscribers','actions'=>['read','update','cancel'],'why'=>'Lifecycle.','frontend_feature'=>'signup form','api'=>['list','update','cancel']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'signup form','api'=>'POST business-ops?action=create entity=subscribers','entity'=>'subscribers','admin_page'=>'Subscribers'],
        ],
        'roles' => ['visitor','customer','admin'],
        'business_rules' => ['duplicate active subscription same email+plan rejected','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'plans','label'=>'Plans','entity'=>'plans','aggregate'=>'count'],
            ['key'=>'subscribers','label'=>'Subscribers','entity'=>'subscribers','aggregate'=>'count'],
        ],
    ],
    'portfolio' => [
        'label' => 'Portfolio & agency',
        'description' => 'Work showcase, enquiries, quotes, delivery.',
        'entities' => [
            ['id'=>'projects','name'=>'Projects','singular'=>'Project','icon'=>'🗂️','fields'=>[
                _br_f('title','Title','text',true),_br_f('category','Category','text',false),_br_f('link','Link','url',false)]],
            ['id'=>'inquiries','name'=>'Enquiries','singular'=>'Enquiry','icon'=>'✉️','fields'=>[
                _br_f('name','Name','text',true),_br_f('email','email',true),_br_f('message','Message','textarea',false)]],
        ],
        'relationships' => [],
        'workflows' => [
            ['key'=>'lead','name'=>'Lead flow','steps'=>['browse work','enquire','quote','deliver']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Pipeline.','frontend_feature'=>'portfolio','api'=>['stats']],
            ['id'=>'projects','label'=>'Projects','icon'=>'🗂️','entity'=>'projects','actions'=>['create','read','update','delete'],'why'=>'Showcase truth.','frontend_feature'=>'work grid','api'=>['create','list','update']],
            ['id'=>'inquiries','label'=>'Enquiries','icon'=>'✉️','entity'=>'inquiries','actions'=>['read','update'],'why'=>'Leads.','frontend_feature'=>'contact form','api'=>['list']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'contact form','api'=>'POST business-ops?action=create entity=inquiries','entity'=>'inquiries','admin_page'=>'Enquiries'],
        ],
        'roles' => ['visitor','customer','admin'],
        'business_rules' => ['spam honeypot enforced'],
        'dashboard_stats' => [
            ['key'=>'projects','label'=>'Projects','entity'=>'projects','aggregate'=>'count'],
            ['key'=>'enquiries','label'=>'Enquiries','entity'=>'inquiries','aggregate'=>'count'],
        ],
    ],
    'agency' => [
        'label' => 'Agency & professional services',
        'description' => 'Services, quotes, bookings, client follow-up.',
        'entities' => [
            ['id'=>'services','name'=>'Services','singular'=>'Service','icon'=>'💼','fields'=>[
                _br_f('name','Service','text',true),_br_f('description','Description','textarea',false),_br_f('price','Price','text',false)]],
            ['id'=>'inquiries','name'=>'Enquiries','singular'=>'Enquiry','icon'=>'✉️','fields'=>[
                _br_f('name','Name','text',true),_br_f('email','email',false),_br_f('message','Message','textarea',false)]],
            ['id'=>'appointments','name'=>'Consultations','singular'=>'Consultation','icon'=>'📅','fields'=>[
                _br_f('customer','Customer','text',true),_br_f('date','Date','date',true),_br_f('time','Time','text',true),
                _br_f('status','Status','select',false,['pending','confirmed','completed','cancelled'])]],
        ],
        'relationships' => [],
        'workflows' => [
            ['key'=>'consult','name'=>'Consultation','steps'=>['browse services','request quote/slot','confirm','deliver']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Leads + bookings.','frontend_feature'=>'service list','api'=>['stats']],
            ['id'=>'services','label'=>'Services','icon'=>'💼','entity'=>'services','actions'=>['create','read','update','delete'],'why'=>'Offer truth.','frontend_feature'=>'service list','api'=>['create','list','update']],
            ['id'=>'inquiries','label'=>'Enquiries','icon'=>'✉️','entity'=>'inquiries','actions'=>['read','update'],'why'=>'Quotes.','frontend_feature'=>'quote form','api'=>['list']],
            ['id'=>'appointments','label'=>'Consultations','icon'=>'📅','entity'=>'appointments','actions'=>['read','update','cancel'],'why'=>'Scheduling.','frontend_feature'=>'booking form','api'=>['list','update','cancel']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'quote form','api'=>'POST business-ops?action=create entity=inquiries','entity'=>'inquiries','admin_page'=>'Enquiries'],
            ['frontend'=>'booking form','api'=>'POST business-ops?action=create entity=appointments','entity'=>'appointments','admin_page'=>'Consultations'],
        ],
        'roles' => ['visitor','customer','staff','admin'],
        'business_rules' => ['double-book same slot rejected','cancelled terminal'],
        'dashboard_stats' => [
            ['key'=>'services','label'=>'Services','entity'=>'services','aggregate'=>'count'],
            ['key'=>'enquiries','label'=>'Enquiries','entity'=>'inquiries','aggregate'=>'count'],
            ['key'=>'consultations','label'=>'Consultations','entity'=>'appointments','aggregate'=>'count'],
        ],
    ],
    'professional' => [
        'label' => 'Professional / other services',
        'description' => 'Generic service fallback with optional scheduling.',
        'entities' => [
            ['id'=>'services','name'=>'Services','singular'=>'Service','icon'=>'💼','fields'=>[
                _br_f('name','Service','text',true),_br_f('description','Description','textarea',false)]],
            ['id'=>'inquiries','name'=>'Enquiries','singular'=>'Enquiry','icon'=>'✉️','fields'=>[
                _br_f('name','Name','text',true),_br_f('email','email',false),_br_f('message','Message','textarea',false)]],
        ],
        'relationships' => [],
        'workflows' => [
            ['key'=>'lead','name'=>'Enquiry flow','steps'=>['browse','enquire','respond']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Leads.','frontend_feature'=>'service list','api'=>['stats']],
            ['id'=>'services','label'=>'Services','icon'=>'💼','entity'=>'services','actions'=>['create','read','update','delete'],'why'=>'Offer truth.','frontend_feature'=>'service list','api'=>['create','list','update']],
            ['id'=>'inquiries','label'=>'Enquiries','icon'=>'✉️','entity'=>'inquiries','actions'=>['read','update'],'why'=>'Follow-up.','frontend_feature'=>'contact form','api'=>['list']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'contact form','api'=>'POST business-ops?action=create entity=inquiries','entity'=>'inquiries','admin_page'=>'Enquiries'],
        ],
        'roles' => ['visitor','customer','admin'],
        'business_rules' => ['spam honeypot enforced'],
        'dashboard_stats' => [
            ['key'=>'services','label'=>'Services','entity'=>'services','aggregate'=>'count'],
            ['key'=>'enquiries','label'=>'Enquiries','entity'=>'inquiries','aggregate'=>'count'],
        ],
    ],
    'generic' => [
        'label' => 'General business',
        'description' => 'Safe fallback: services + enquiries only, never another industry modules.',
        'entities' => [
            ['id'=>'services','name'=>'Services','singular'=>'Service','icon'=>'💼','fields'=>[
                _br_f('name','Service','text',true),_br_f('description','Description','textarea',false)]],
            ['id'=>'inquiries','name'=>'Enquiries','singular'=>'Enquiry','icon'=>'✉️','fields'=>[
                _br_f('name','Name','text',true),_br_f('email','email',false),_br_f('message','Message','textarea',false)]],
        ],
        'relationships' => [],
        'workflows' => [
            ['key'=>'lead','name'=>'Enquiry flow','steps'=>['browse','enquire','respond']],
        ],
        'admin_modules' => [
            ['id'=>'dashboard','label'=>'Dashboard','icon'=>'📊','entity'=>'','actions'=>['read'],'why'=>'Leads.','frontend_feature'=>'service list','api'=>['stats']],
            ['id'=>'services','label'=>'Services','icon'=>'💼','entity'=>'services','actions'=>['create','read','update','delete'],'why'=>'Offer truth.','frontend_feature'=>'service list','api'=>['create','list','update']],
            ['id'=>'inquiries','label'=>'Enquiries','icon'=>'✉️','entity'=>'inquiries','actions'=>['read','update'],'why'=>'Follow-up.','frontend_feature'=>'contact form','api'=>['list']],
        ],
        'frontend_mappings' => [
            ['frontend'=>'contact form','api'=>'POST business-ops?action=create entity=inquiries','entity'=>'inquiries','admin_page'=>'Enquiries'],
        ],
        'roles' => ['visitor','customer','admin'],
        'business_rules' => ['spam honeypot enforced'],
        'dashboard_stats' => [
            ['key'=>'services','label'=>'Services','entity'=>'services','aggregate'=>'count'],
            ['key'=>'enquiries','label'=>'Enquiries','entity'=>'inquiries','aggregate'=>'count'],
        ],
    ],
    ];
    if (!empty($GLOBALS['_BR_EXTRA']) && is_array($GLOBALS['_BR_EXTRA'])) {
        foreach ($GLOBALS['_BR_EXTRA'] as $k => $def) { $cats[$k] = $def; }
    }
    return $cats;
}

function business_registry_register(string $key, array $def): void {
    $key = strtolower(preg_replace('/[^a-z0-9_]/i', '', $key));
    if ($key === '') return;
    if (!isset($GLOBALS['_BR_EXTRA'])) $GLOBALS['_BR_EXTRA'] = [];
    $GLOBALS['_BR_EXTRA'][$key] = $def;
}

function business_registry_get(string $key): array {
    $cats = business_registry_categories();
    return $cats[$key] ?? $cats['generic'];
}

function business_registry_keys(): array {
    return array_keys(business_registry_categories());
}

// Weighted lexicon detection. Returns category key; never throws.
// Covers world businesses: every keyword maps into one of the 17 fixed
// categories (bus/ecommerce/restaurant/hotel/salon/clinic/realestate/
// education/travel/events/rental/automotive/subscription/portfolio/
// agency/professional) or falls back to generic (services + enquiries).
function business_registry_detect(string $text): string {
    $t = strtolower($text);
    $lex = [
        'bus' => ['bus ','buses','coach','bus booking','seat','ticket','trip','route','transport','logistic','fleet','shuttle','charter bus','cargo','courier','parcel','freight','perunthu','payanam'],
        'ecommerce' => ['shop','store','product','retail','ecommerce','e-commerce','boutique','mart','fashion','jewelry','grocery','furniture','electronics','cart','checkout','inventory','supermarket','online store','wholesale','stationery','kadai','thuni','udai'],
        'restaurant' => ['restaurant','cafe','pizzeria','food order','menu','dish','dining','cuisine','pizza','burger','bakery','catering','cloud kitchen','sweet shop','juice bar','tea stall','tea kadai','biriyani','meals','tiffin','sappadu','sapadu','unavu'],
        'hotel' => ['hotel','room booking','guest house','stay','resort','accommodation','check-in','check in','villa stay','lodge','hostel','homestay','boarding house','room','rooms'],
        'salon' => ['salon','beauty','spa','barber','nail','haircut','facial','tattoo','mehndi','bridal studio','unisex salon','saloon','mudi','alagu'],
        'clinic' => ['clinic','hospital','doctor','patient','dental','physio','therapy','medical','health center','ayurveda','pharmacy','optical','diagnostic','lab test','veterinary','pet clinic','dispensary','vaithiyam','maruthuvam','marunthu kadai','vaithiyar'],
        'realestate' => ['real estate','property for sale','plot','apartment','villa sale','land sale','listing','realtor','house for sale','land for sale','rental property','veedu','nilam'],
        'education' => ['school','course','tuition','class','training','academy','tutor','coaching','college','admission','enrol','kindergarten','montessori','music class','dance class','driving school','language class','palli','padippu','kalvi'],
        'travel' => ['tour package','tour ','travel agency','destination','itinerary','sightsee','holiday package','trek','visa service','air ticket','flight booking','honeymoon package','pilgrimage'],
        'events' => ['event','concert','wedding hall','conference','exhibition','ticketed','seminar','festival','birthday party','anniversary event','dj service','sound system','kalyanam','virunthu'],
        'rental' => ['rental','hire','lease','equipment hire','car hire','rent a','on rent','camera rental','dress rental','bike rental','van rental','hall rental','vadagai'],
        'automotive' => ['car sale','auto service','garage','vehicle','service center','mechanic','spare part','bike service','car wash','bike repair','tyre shop','battery shop','vandi'],
        'subscription' => ['subscription','membership','gym','fitness','yoga plan','monthly plan','club member','newspaper subscription'],
        'portfolio' => ['portfolio','photograph','freelance','showcase','my work','gallery','videography','wedding photographer','makeup artist'],
        'agency' => ['agency','consult','marketing','software','design studio','service provider','lawyer','cleaning service','repair service','advertising','printing press','web design','it services','security service','manpower'],
        'professional' => ['service','plumber','electrician','coach','astrolog','consultant','laundry','tailor','carpenter','painter','mason','welder','appliance repair','thaiyal kadai'],
    ];
    $scores = array_fill_keys(array_keys($lex), 0);
    foreach ($lex as $k => $words) {
        foreach ($words as $w) { if (strpos($t, $w) !== false) $scores[$k] += strlen($w); }
    }
    arsort($scores);
    $top = key($scores);
    if (current($scores) <= 0) return 'generic';
    // Disambiguate travel vs bus: package/destination wins travel.
    return $top;
}

// Normalized entities for AdminPanelGenerator::normalizeEntities().
function business_registry_admin_entities(string $key): array {
    $cat = business_registry_get($key);
    $out = [];
    foreach (($cat['entities'] ?? []) as $e) {
        $out[] = ['id' => $e['id'], 'name' => $e['name'], 'singular' => $e['singular'] ?? $e['name'],
            'icon' => $e['icon'] ?? '📁', 'fields' => array_map(function ($f) {
                $t = $f['type'] ?? 'text';
                $s = ($f['name'] ?? '') . ':' . $t . (!empty($f['required']) ? '*' : '');
                if ($t === 'select' && !empty($f['options'])) $s .= ':' . implode('|', $f['options']);
                return $s;
            }, $e['fields'] ?? [])];
    }
    return $out;
}

function business_registry_find_entity(string $entityId): ?array {
    foreach (business_registry_categories() as $cat) {
        foreach (($cat['entities'] ?? []) as $e) {
            if (($e['id'] ?? '') === $entityId) return $e;
        }
    }
    return null;
}

// Public catalog entities: safe for anonymous listing (no personal data).
// Customer-facing search (trips, products, rooms…) reads these; everything
// else stays ownership-gated. Never add bookings/orders/payments/patients/
// subscribers/customers/inquiries here — those leak PII.
function business_registry_is_public(string $entityId): bool {
    static $pub = ['trips', 'routes', 'buses', 'products', 'coupons', 'menu_items',
        'rooms', 'departures', 'packages', 'events', 'tickets', 'properties',
        'viewings', 'courses', 'services', 'vehicles', 'units', 'plans',
        'doctors', 'staff', 'tutors', 'projects'];
    return in_array($entityId, $pub, true);
}

// Canonical entity id: AI-generated manifests often invent SINGULAR ids
// (bus/trip/booking/payment/route) while the storefront runtime, search and
// admin mirror all use the registry PLURAL ids (buses/trips/bookings/…).
// A singular/plural split means admin-added rows land in files the frontend
// never reads (data_trip.json vs data_trips.json) — admin adds a bus, the
// front shows nothing. Map every known singular to its canonical plural so
// installs, APIs and filters converge on ONE store per concept.
function business_registry_canonical_entity(string $id): string {
    $id = strtolower(trim(preg_replace('/[^a-z0-9_]/i', '', $id)));
    if ($id === '') return '';
    static $alias = null;
    if ($alias === null) {
        $alias = [
            'bus' => 'buses', 'route' => 'routes', 'trip' => 'trips',
            'booking' => 'bookings', 'payment' => 'payments',
            'order' => 'orders', 'product' => 'products',
            'ticket' => 'tickets', 'customer' => 'customers',
            'inquiry' => 'inquiries', 'subscriber' => 'subscribers',
        ];
        // Derive plurals from every registered category as well: any entity
        // id ending in a known singular stem maps to the registered plural.
        try {
            foreach (business_registry_categories() as $cat) {
                foreach (($cat['entities'] ?? []) as $e) {
                    $pid = strtolower(trim(preg_replace('/[^a-z0-9_]/i', '', (string)($e['id'] ?? ''))));
                    if ($pid === '' || isset($alias[$pid])) continue;
                    if (substr($pid, -3) === 'ies') $alias[substr($pid, 0, -3) . 'y'] = $pid;
                    elseif (substr($pid, -2) === 'es' && strlen($pid) > 4) $alias[substr($pid, 0, -2)] = $pid;
                    elseif (substr($pid, -1) === 's' && strlen($pid) > 3) $alias[substr($pid, 0, -1)] = $pid;
                }
            }
        } catch (Throwable $e) { /* static map above still applies */ }
    }
    return $alias[$id] ?? $id;
}

}
