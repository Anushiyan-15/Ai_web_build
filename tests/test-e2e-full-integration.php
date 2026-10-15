<?php
// tests/test-e2e-full-integration.php — run: C:\php\php.exe tests/test-e2e-full-integration.php
// COMPLETE end-to-end integration, every relevant frontend form:
//   contact, booking, order, registration, review, quote, newsletter,
//   appointment → correct JSON file → exact record in the correct admin page.
// Then: full CRUD + refresh persistence (pre-publish), publish with isolated
// storage, production verification, republish preservation. Cleans up after.
error_reporting(E_ALL & ~E_DEPRECATED);
ob_start();
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/PreviewEnv.php';

$PASS = 0; $FAIL = 0;
function t_ok(string $name, bool $cond, string $detail = '') {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  PASS  $name" . ($detail ? " — $detail" : '') . "\n"; }
    else { $FAIL++; echo "  FAIL  $name" . ($detail ? " — $detail" : '') . "\n"; }
}
function http_post(string $url, array $fields, bool $follow = true): array {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 20,
        'follow_location' => $follow ? 1 : 0,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($fields), 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    return ['body' => $body === false ? '' : $body, 'headers' => $http_response_header ?? []];
}
function http_post_json(string $url, array $data, int $timeout = 30): array {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => $timeout,
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data), 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    return ['body' => $body === false ? '' : $body, 'headers' => $http_response_header ?? []];
}
function http_get(string $url, string $cookie = '', int $timeout = 20): array {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => $timeout,
        'header' => $cookie !== '' ? "Cookie: $cookie\r\n" : '', 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    return ['body' => $body === false ? '' : $body, 'headers' => $http_response_header ?? []];
}
function jar(array $headers): string {
    $m = [];
    foreach ($headers as $h) {
        if (preg_match('/^Set-Cookie:\s*([^;=]+)=([^;]*)/i', $h, $mm)) $m[trim($mm[1])] = trim($mm[2]);
    }
    $c = '';
    foreach ($m as $k => $v) $c .= ($c !== '' ? '; ' : '') . $k . '=' . $v;
    return $c;
}
function admin_login(string $loginUrl, string $user, string $pass, string $dashUrl, int $tries = 3): string {
    for ($i = 0; $i < $tries; $i++) {
        $login = http_post($loginUrl, ['username' => $user, 'password' => $pass], false);
        $cookie = jar($login['headers']);
        if ($cookie === '') continue;
        $dash = http_get($dashUrl, $cookie);
        if (strpos($dash['body'], 'Admin Login') === false
            && (strpos($dash['body'], 'Welcome') !== false || strpos($dash['body'], 'Dashboard') !== false)) {
            return $cookie;
        }
    }
    return '';
}
function tdel(string $d): void {
    if (!is_dir($d)) return;
    foreach (scandir($d) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $d . '/' . $f;
        is_dir($p) ? tdel($p) : @unlink($p);
    }
    @rmdir($d);
}
function find_row(array $rows, string $k, string $v): ?array {
    foreach ($rows as $r) {
        if (is_array($r) && ($r[$k] ?? null) === $v) return $r;
    }
    return null;
}

$BASE = 'http://localhost/project/webbbuilder';
@unlink($ROOT . '/storage/preview_throttle.json');
$probe = http_get($BASE . '/api/preview-env.php?action=info&draftId=' . str_repeat('0', 32));
if ($probe['body'] === '' && empty($probe['headers'])) { echo "web server unreachable — aborting\n"; exit(2); }

// ── Forms covering every purpose ─────────────────────────────
$HTML = '<!DOCTYPE html><html><head><title>Full E2E Co</title></head><body><h1>Full E2E Co</h1>'
    . '<form id="contact-form" method="POST"><input name="name"><input type="email" name="email">'
    . '<textarea name="message"></textarea><button type="submit">Send</button></form>'
    . '<form data-wc-form="booking"><input name="company" style="display:none">'
    . '<input name="customer"><input name="trip_id"><input name="seats"><input name="phone"><button>Book</button></form>'
    . '<form data-wc-form="order"><input name="company" style="display:none">'
    . '<input name="customer"><input name="total"><button>Order</button></form>'
    . '<form data-wc-form="registration"><input name="company" style="display:none">'
    . '<input name="name"><input name="email" type="email"><button>Join</button></form>'
    . '<form data-wc-form="review"><input name="company" style="display:none">'
    . '<input name="reviewer"><input name="stars"><button>Review</button></form>'
    . '<form data-wc-form="quote"><input name="company" style="display:none">'
    . '<input name="name"><input name="email" type="email"><input name="message"><button>Quote</button></form>'
    . '<form data-wc-form="newsletter"><input name="company" style="display:none">'
    . '<input name="email" type="email"><button>Subscribe</button></form>'
    . '<form data-wc-form="appointment"><input name="company" style="display:none">'
    . '<input name="customer"><input name="date"><button>Schedule</button></form>'
    . '</body></html>';
$F = fn($n, $l, $t = 'text', $r = false) => ['name' => $n, 'label' => $l, 'type' => $t, 'required' => $r];
$ENTS = [
    ['id' => 'inquiries', 'name' => 'Inquiries', 'icon' => '✉️',
     'fields' => [$F('name', 'Name', 'text', true), $F('email', 'Email', 'email', true), $F('message', 'Message')]],
    ['id' => 'bookings', 'name' => 'Bookings', 'icon' => '🎫',
     'fields' => [$F('customer', 'Customer', 'text', true), $F('trip_id', 'Trip'), $F('seats', 'Seats'), $F('phone', 'Phone')]],
    ['id' => 'orders', 'name' => 'Orders', 'icon' => '🧾',
     'fields' => [$F('customer', 'Customer', 'text', true), $F('total', 'Total', 'number')]],
    ['id' => 'customers', 'name' => 'Customers', 'icon' => '👥',
     'fields' => [$F('name', 'Name', 'text', true), $F('email', 'Email', 'email')]],
    ['id' => 'reviews', 'name' => 'Reviews', 'icon' => '⭐',
     'fields' => [$F('reviewer', 'Reviewer', 'text', true), $F('stars', 'Stars', 'number')]],
    ['id' => 'appointments', 'name' => 'Appointments', 'icon' => '📅',
     'fields' => [$F('customer', 'Customer', 'text', true), $F('date', 'Date', 'date')]],
    ['id' => 'subscribers', 'name' => 'Subscribers', 'icon' => '💌',
     'fields' => [$F('email', 'Email', 'email', true)]],
    ['id' => 'trips', 'name' => 'Trips', 'icon' => '🚌',
     'fields' => [$F('route', 'Route', 'text', true), $F('date', 'Date', 'date')]],
];
$AU = 'e2eadmin'; $AP = 'E2ePass123!';

echo "== A. provision full-coverage env ==\n";
$r = http_post_json($BASE . '/api/preview-env.php', ['action' => 'provision',
    'html' => $HTML, 'bizName' => 'Full E2E Co', 'slug' => 'full-e2e-co',
    'entities' => $ENTS, 'adminUser' => $AU, 'adminPass' => $AP,
    'contactEmail' => 'owner@example.com', 'genMode' => 'admin']);
$j = json_decode($r['body'], true);
$PID = is_array($j) ? (string)($j['env']['id'] ?? '') : '';
t_ok('provisioned', is_array($j) && ($j['success'] ?? false) === true && $PID !== '');
$PDIR = $ROOT . '/preview/' . $PID;
$ADMIN = $PDIR . '/admin';
$site = $BASE . '/preview/' . $PID . '/';

echo "== B. submit through EVERY frontend form ==\n";
$subs = [
    ['via' => 'contact.php', 'entity' => 'inquiries',
     'fields' => ['name' => 'E2E Contact', 'email' => 'e2e-contact@example.com', 'message' => 'E2E hello inbox']],
    ['via' => 'submit.php', 'form' => 'booking', 'entity' => 'bookings',
     'fields' => ['customer' => 'E2E Booker', 'trip_id' => 'T-E2E', 'seats' => 'E1', 'phone' => '0771112233']],
    ['via' => 'submit.php', 'form' => 'order', 'entity' => 'orders',
     'fields' => ['customer' => 'E2E Buyer', 'total' => '2500']],
    ['via' => 'submit.php', 'form' => 'registration', 'entity' => 'customers',
     'fields' => ['name' => 'E2E Member', 'email' => 'e2e-member@example.com']],
    ['via' => 'submit.php', 'form' => 'review', 'entity' => 'reviews',
     'fields' => ['reviewer' => 'E2E Critic', 'stars' => '5']],
    ['via' => 'submit.php', 'form' => 'quote', 'entity' => 'inquiries',
     'fields' => ['name' => 'E2E Quoter', 'email' => 'e2e-quote@example.com', 'message' => 'E2E quote please']],
    ['via' => 'submit.php', 'form' => 'newsletter', 'entity' => 'subscribers',
     'fields' => ['email' => 'e2e-sub@example.com']],
    ['via' => 'submit.php', 'form' => 'appointment', 'entity' => 'appointments',
     'fields' => ['customer' => 'E2E Patient', 'date' => '2026-12-10']],
];
foreach ($subs as $s) {
    $payload = $s['fields'];
    if (isset($s['form'])) $payload = array_merge(['form' => $s['form']], $payload);
    $rr = http_post($site . $s['via'], $payload);
    $jj = json_decode($rr['body'], true);
    t_ok($s['via'] . ' ← ' . ($s['form'] ?? 'contact') . ' succeeds',
        is_array($jj) && ($jj['success'] ?? false) === true && !empty($jj['id'] ?? $jj['success']), substr($rr['body'], 0, 120));
}

echo "== C. exact records in the CORRECT json files ==\n";
$expect = [
    'inquiries' => [
        ['email' => 'e2e-contact@example.com', 'name' => 'E2E Contact', 'message' => 'E2E hello inbox'],
        ['email' => 'e2e-quote@example.com', 'name' => 'E2E Quoter', 'message' => 'E2E quote please'],
    ],
    'bookings' => [['customer' => 'E2E Booker', 'trip_id' => 'T-E2E', 'seats' => 'E1', 'phone' => '0771112233']],
    'orders' => [['customer' => 'E2E Buyer', 'total' => '2500']],
    'customers' => [['name' => 'E2E Member', 'email' => 'e2e-member@example.com']],
    'reviews' => [['reviewer' => 'E2E Critic', 'stars' => '5']],
    'subscribers' => [['email' => 'e2e-sub@example.com']],
    'appointments' => [['customer' => 'E2E Patient', 'date' => '2026-12-10']],
];
foreach ($expect as $ent => $rows) {
    $disk = json_decode(@file_get_contents($ADMIN . '/data_' . $ent . '.json'), true);
    t_ok("file exists: data_$ent.json", is_array($disk));
    foreach ($rows as $ri => $want) {
        $probeVal = reset($want);
        $probeKey = key($want);
        $row = find_row((array)$disk, (string)$probeKey, (string)$probeVal);
        $exact = true;
        if ($row === null) $exact = false;
        else {
            foreach ($want as $k => $v) { if ((string)($row[$k] ?? '') !== (string)$v) $exact = false; }
            if (empty($row['id']) || empty($row['created_at'])) $exact = false;
        }
        t_ok("data_$ent.json holds exact record #$ri", $exact, $probeKey . '=' . $probeVal);
    }
}

echo "== D. exact records visible in the CORRECT admin pages ==\n";
$cookie = admin_login($BASE . '/preview/' . $PID . '/admin/login.php', $AU, $AP,
    $BASE . '/preview/' . $PID . '/admin/index.php');
t_ok('preview admin login (strong)', $cookie !== '');
$pages = [
    'inbox.php' => ['E2E Contact', 'E2E hello inbox', 'E2E Quoter'],
    'bookings.php' => ['E2E Booker', 'T-E2E'],
    'orders.php' => ['E2E Buyer', '2500'],
    'customers.php' => ['E2E Member', 'e2e-member@example.com'],
    'reviews.php' => ['E2E Critic'],
    'subscribers.php' => ['e2e-sub@example.com'],
    'appointments.php' => ['E2E Patient', '2026-12-10'],
];
foreach ($pages as $pg => $needles) {
    $body = http_get($BASE . '/preview/' . $PID . '/admin/' . $pg, $cookie)['body'];
    $all = true;
    foreach ($needles as $n) { if (strpos($body, $n) === false) $all = false; }
    t_ok("admin/$pg shows submitted values", $all);
}
$dash = http_get($BASE . '/preview/' . $PID . '/admin/index.php', $cookie)['body'];
t_ok('dashboard reflects submissions', strpos($dash, 'E2E Booker') !== false || strpos($dash, 'Total records') !== false);

echo "== E. full CRUD + refresh persistence (pre-publish) ==\n";
$bp = http_get($BASE . '/preview/' . $PID . '/admin/bookings.php?new=1', $cookie)['body'];
preg_match('/name="csrf" value="([a-f0-9]+)"/', $bp, $mm);
$csrf = $mm[1] ?? '';
t_ok('csrf available', $csrf !== '');
$api = $BASE . '/preview/' . $PID . '/admin/api.php';
$call = function (array $p) use ($api, $cookie) {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
        'content' => http_build_query($p), 'ignore_errors' => true]]);
    return json_decode(@file_get_contents($api, false, $ctx), true);
};
if ($csrf !== '') {
    // CREATE via admin
    $c = $call(['action' => 'save', 'entity' => 'bookings', 'csrf' => $csrf,
        'customer' => 'E2E Staff-Created', 'trip_id' => 'T-ADMIN', 'seats' => 'A9']);
    $bid = (string)($c['data']['id'] ?? '');
    t_ok('admin creates record', ($c['success'] ?? false) === true && $bid !== '');
    $row = find_row(json_decode(@file_get_contents($ADMIN . '/data_bookings.json'), true) ?: [], 'id', $bid);
    t_ok('created record persisted to json (refresh re-read)',
        $row !== null && ($row['customer'] ?? '') === 'E2E Staff-Created');
    $lp = http_get($BASE . '/preview/' . $PID . '/admin/bookings.php', $cookie)['body'];
    t_ok('created record listed in admin', strpos($lp, 'E2E Staff-Created') !== false);
    // UPDATE: change seats, unrelated fields must survive.
    $u = $call(['action' => 'save', 'entity' => 'bookings', 'csrf' => $csrf, 'id' => $bid,
        'customer' => 'E2E Staff-Created', 'trip_id' => 'T-ADMIN', 'seats' => 'B7']);
    t_ok('admin edits record', ($u['success'] ?? false) === true);
    $row2 = find_row(json_decode(@file_get_contents($ADMIN . '/data_bookings.json'), true) ?: [], 'id', $bid);
    t_ok('edit applied, unrelated fields preserved',
        $row2 !== null && ($row2['seats'] ?? '') === 'B7' && ($row2['customer'] ?? '') === 'E2E Staff-Created'
        && ($row2['trip_id'] ?? '') === 'T-ADMIN');
    $lp2 = http_get($BASE . '/preview/' . $PID . '/admin/bookings.php', $cookie)['body'];
    t_ok('edited value shown in admin', strpos($lp2, 'B7') !== false);
    // DELETE (POST + CSRF), then verify gone from file AND page.
    $d = $call(['action' => 'delete', 'entity' => 'bookings', 'csrf' => $csrf, 'id' => $bid]);
    t_ok('admin deletes record', ($d['success'] ?? false) === true);
    $row3 = find_row(json_decode(@file_get_contents($ADMIN . '/data_bookings.json'), true) ?: [], 'id', $bid);
    t_ok('deleted record gone from json', $row3 === null);
    $lp3 = http_get($BASE . '/preview/' . $PID . '/admin/bookings.php', $cookie)['body'];
    t_ok('deleted record gone from admin', strpos($lp3, 'E2E Staff-Created') === false);
    // Original frontend submission untouched by admin CRUD churn.
    $keep = find_row(json_decode(@file_get_contents($ADMIN . '/data_bookings.json'), true) ?: [], 'customer', 'E2E Booker');
    t_ok('frontend submission survives admin CRUD', $keep !== null && ($keep['seats'] ?? '') === 'E1');
    // Second entity spot-check: delete the review.
    $rev = find_row(json_decode(@file_get_contents($ADMIN . '/data_reviews.json'), true) ?: [], 'reviewer', 'E2E Critic');
    $rdel = $rev ? $call(['action' => 'delete', 'entity' => 'reviews', 'csrf' => $csrf, 'id' => $rev['id']]) : null;
    t_ok('second entity deletes cleanly', is_array($rdel) && ($rdel['success'] ?? false) === true);
}

echo "== F. readiness ==\n";
$rd = http_post_json($BASE . '/api/preview-env.php', ['action' => 'readiness', 'draftId' => $PID]);
$rj = json_decode($rd['body'], true);
t_ok('ready=true with all critical passing', is_array($rj) && ($rj['ready'] ?? false) === true);
if (is_array($rj)) foreach (($rj['checks'] ?? []) as $c) {
    t_ok('check: ' . ($c['label'] ?? ''), ($c['pass'] ?? false) === true, (string)($c['detail'] ?? ''));
}

echo "== G. publish → isolated production → preserve on republish ==\n";
// Catalog row to migrate.
$tripCtx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
    'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
    'content' => http_build_query(['action' => 'save', 'entity' => 'trips', 'csrf' => $csrf,
        'route' => 'E2E Catalog Route', 'date' => '2026-12-20']), 'ignore_errors' => true]]);
@file_get_contents($BASE . '/preview/' . $PID . '/admin/api.php', false, $tripCtx);
$OID = 'WBL-TEST-E2E1'; $OSLUG = 'e2e-pub-test'; $OPASS = 'E2eProd123!';
$order = ['order_id' => $OID, 'site_name' => 'Full E2E Co', 'slug' => $OSLUG,
    'concept_index' => 0, 'gen_mode' => 'admin', 'package' => 'pro', 'package_label' => 'Pro',
    'amount' => 19.0, 'currency' => 'USD', 'admin_username' => 'e2eprod',
    'admin_email' => 'owner@example.com', 'contact_email' => 'owner@example.com',
    'smtp_user' => '', 'smtp_pass' => '', 'use_subdomain' => false,
    'admin_password_hash' => password_hash($OPASS, PASSWORD_DEFAULT),
    'admin_files' => [], 'admin_sql' => '', 'admin_entities' => $ENTS,
    'admin_seed_empty' => true, 'canonical_spec' => null, 'biz_spec' => null,
    'draft_token' => $PID, 'status' => 'paid', 'created_at' => date('Y-m-d H:i:s'), 'ip' => '127.0.0.1'];
@file_put_contents($ROOT . '/storage/orders/' . $OID . '.json', json_encode($order, JSON_PRETTY_PRINT));
@file_put_contents($ROOT . '/storage/designs/' . $OID . '.html', $HTML);
$pubUrl = $BASE . '/publish.php?action=do_publish&order_id=' . $OID . '&draft_id=' . $PID . '&migrate_preview=catalog';
$okPub = false;
for ($i = 0; $i < 2; $i++) {
    http_get($pubUrl, '', 120);
    $o = json_decode(@file_get_contents($ROOT . '/storage/orders/' . $OID . '.json'), true);
    if (($o['status'] ?? '') === 'published') { $okPub = true; break; }
}
t_ok('published', $okPub);
// Production isolation: catalog migrated, transactional preview rows absent.
$pt = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_trips.json'), true);
t_ok('catalog row migrated', find_row((array)$pt, 'route', 'E2E Catalog Route') !== null);
$pb = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_bookings.json'), true);
t_ok('no preview bookings leaked', find_row((array)$pb, 'customer', 'E2E Booker') === null);
// Production contact + order forms work on isolated storage.
$pc = http_post($BASE . '/published/' . $OSLUG . '/contact.php',
    ['name' => 'Prod Caller', 'email' => 'prod-caller@example.com', 'message' => 'Prod hello']);
t_ok('production contact works', (json_decode($pc['body'], true)['success'] ?? false) === true);
$po = http_post($BASE . '/published/' . $OSLUG . '/submit.php',
    ['form' => 'order', 'customer' => 'Prod Buyer', 'total' => '999']);
t_ok('production order works', (json_decode($po['body'], true)['success'] ?? false) === true);
$pcookie = admin_login($BASE . '/published/' . $OSLUG . '/admin/login.php', 'e2eprod', $OPASS,
    $BASE . '/published/' . $OSLUG . '/admin/index.php');
t_ok('production admin login', $pcookie !== '');
$pinbox = http_get($BASE . '/published/' . $OSLUG . '/admin/inbox.php', $pcookie)['body'];
t_ok('production inquiry in production inbox',
    strpos($pinbox, 'Prod Caller') !== false && strpos($pinbox, 'E2E Contact') === false);
$porders = http_get($BASE . '/published/' . $OSLUG . '/admin/orders.php', $pcookie)['body'];
t_ok('production order in production admin', strpos($porders, 'Prod Buyer') !== false);
// Republish: everything preserved, nothing duplicated or leaked.
http_get($pubUrl, '', 120);
$pb2 = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_bookings.json'), true);
$pi2 = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_inquiries.json'), true);
$po2 = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_orders.json'), true);
$cBuyers = 0; $cRiders = 0;
foreach ((array)$pb2 as $r) { if (($r['customer'] ?? '') === 'Prod Buyer') $cBuyers++; }
foreach ((array)$po2 as $r) { if (($r['customer'] ?? '') === 'Prod Buyer') $cBuyers++; if (($r['customer'] ?? '') === 'E2E Buyer') $cRiders++; }
$hasProdCaller = find_row((array)$pi2, 'email', 'prod-caller@example.com') !== null;
$hasPreviewMail = find_row((array)$pi2, 'email', 'e2e-contact@example.com') !== null;
t_ok('republish preserves + no dupes/leaks',
    $cBuyers === 1 && $cRiders === 0 && $hasProdCaller && !$hasPreviewMail,
    "buyers=$cBuyers riders=$cRiders");
$pcookie2 = admin_login($BASE . '/published/' . $OSLUG . '/admin/login.php', 'e2eprod', $OPASS,
    $BASE . '/published/' . $OSLUG . '/admin/index.php');
t_ok('production creds survive republish', $pcookie2 !== '');
// Cleanup.
tdel($ROOT . '/published/' . $OSLUG);
@unlink($ROOT . '/storage/orders/' . $OID . '.json');
@unlink($ROOT . '/storage/designs/' . $OID . '.html');
tdel($ROOT . '/storage/collections/' . $OSLUG); // production mirror
foreach (glob($ROOT . '/storage/customers/*.json') ?: [] as $cf) {
    $cc = @file_get_contents($cf);
    if (is_string($cc) && strpos($cc, $OID) !== false) @unlink($cf);
}
t_ok('production cleaned', !is_dir($ROOT . '/published/' . $OSLUG));
if ($PID !== '') {
    preview_rrmdir($PDIR);
    tdel($ROOT . '/storage/collections/' . $PID);
    @unlink($ROOT . '/storage/drafts/' . $PID . '.html');
    @unlink($ROOT . '/storage/drafts/' . $PID . '.json');
    t_ok('preview cleaned', !is_dir($PDIR));
} else { t_ok('preview cleaned', true); }

ob_end_flush();
echo "\nRESULT: $PASS passed, $FAIL failed\n";
exit($FAIL > 0 ? 1 : 0);
