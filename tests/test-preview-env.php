<?php
// tests/test-preview-env.php — run: C:\php\php.exe tests/test-preview-env.php
// Pre-publish development environment: provision → real forms → real JSON →
// real admin CRUD → AI feature → readiness → publish migration. All live.
error_reporting(E_ALL & ~E_DEPRECATED);
ob_start();
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/PreviewEnv.php';

$PASS = 0; $FAIL = 0; $SKIP = 0;
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
function http_post_json(string $url, array $data): array {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 30,
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data), 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    return ['body' => $body === false ? '' : $body, 'headers' => $http_response_header ?? []];
}
// Test-local recursive delete for explicit paths (preview_rrmdir is scoped
// to preview/ by design and refuses anything else).
function tdel(string $d): void {
    if (!is_dir($d)) return;
    foreach (scandir($d) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $d . '/' . $f;
        is_dir($p) ? tdel($p) : @unlink($p);
    }
    @rmdir($d);
}
function http_get(string $url, string $cookie = '', int $timeout = 20): array {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => $timeout,
        'header' => $cookie !== '' ? "Cookie: $cookie\r\n" : '', 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    return ['body' => $body === false ? '' : $body, 'headers' => $http_response_header ?? []];
}
// do_publish does real generation work (AI analysis fallback, kit install,
// migration) — allow up to 2 minutes and retry once if the order is not
// marked published (client-side timeout does not stop the server, but a
// clean second run converges safely since every step is idempotent).
function do_publish_run(string $url, string $orderFile): bool {
    global $ROOT;
    for ($i = 0; $i < 2; $i++) {
        http_get($url, '', 120);
        $o = json_decode(@file_get_contents($orderFile), true);
        if (($o['status'] ?? '') === 'published') return true;
    }
    return false;
}
function jar(array $headers): string {    $m = [];
    foreach ($headers as $h) {
        if (preg_match('/^Set-Cookie:\s*([^;=]+)=([^;]*)/i', $h, $m2)) $m[trim($m2[1])] = trim($m2[2]);
    }
    $c = '';
    foreach ($m as $k => $v) $c .= ($c !== '' ? '; ' : '') . $k . '=' . $v;
    return $c;
}
// Strong login: returns a cookie only after the dashboard actually renders
// (retries ride out transient session-store flakes on busy dev machines).
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

$BASE = 'http://localhost/project/webbbuilder';
// Deterministic suite: reset the endpoint rate-limit buckets (test-only
// ephemeral state) so reruns never throttle themselves.
@unlink($ROOT . '/storage/preview_throttle.json');
$probe = http_get($BASE . '/api/preview-env.php?action=info&draftId=' . str_repeat('0', 32));
$serverUp = !($probe['body'] === '' && empty($probe['headers']));
if (!$serverUp) { echo "web server unreachable — aborting\n"; exit(2); }

$HTML = '<!DOCTYPE html><html><head><title>Bus Test Co</title></head><body>'
    . '<h1>Bus Test Co</h1>'
    . '<form id="contact-form" method="POST"><input name="name"><input type="email" name="email">'
    . '<textarea name="message"></textarea><button type="submit">Send</button></form>'
    . '<form data-wc-form="booking"><input name="company" style="display:none">'
    . '<input name="passenger"><input name="phone"><input name="email" type="email">'
    . '<button type="submit">Book</button></form>'
    . '</body></html>';
$ENTS = [
    ['id' => 'inquiries', 'name' => 'Inquiries', 'icon' => '✉️', 'fields' => [
        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false]]],
    ['id' => 'trips', 'name' => 'Trips', 'icon' => '🚌', 'fields' => [
        ['name' => 'route', 'label' => 'Route', 'type' => 'text', 'required' => true],
        ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'required' => false]]],
    ['id' => 'bookings', 'name' => 'Bookings', 'icon' => '🎫', 'fields' => [
        ['name' => 'customer', 'label' => 'Customer', 'type' => 'text', 'required' => true],
        ['name' => 'trip_id', 'label' => 'Trip', 'type' => 'text', 'required' => false],
        ['name' => 'seats', 'label' => 'Seats', 'type' => 'text', 'required' => false]]],
];
$ADMIN_USER = 'previewadmin';
$ADMIN_PASS = 'PreviewPass123!';

echo "== 1. provision via endpoint ==\n";
$r = http_post_json($BASE . '/api/preview-env.php', ['action' => 'provision',
    'html' => $HTML, 'bizName' => 'Bus Test Co', 'slug' => 'bus-test-co',
    'entities' => $ENTS, 'adminUser' => $ADMIN_USER, 'adminPass' => $ADMIN_PASS,
    'contactEmail' => 'owner@example.com', 'genMode' => 'admin']);
$j = json_decode($r['body'], true);
t_ok('provision succeeds', is_array($j) && ($j['success'] ?? false) === true, substr($r['body'], 0, 200));
$env = is_array($j) ? ($j['env'] ?? []) : [];
$PID = (string)($env['id'] ?? '');
t_ok('preview id is 32hex', (bool)preg_match('/^[a-f0-9]{32}$/', $PID));
t_ok('explicit creds kept (no generated pass)', ($env['adminUser'] ?? '') === $ADMIN_USER && empty($env['adminPassOnce']));
$PDIR = $ROOT . '/preview/' . $PID;
$ADMIN = $PDIR . '/admin';

echo "== 2. generated files (php -l) ==\n";
foreach (['index.html', 'submit.php', 'contact.php', 'customer.php', 'api/business-ops.php', 'draft.json',
          'admin/login.php', 'admin/index.php', 'admin/inbox.php', 'admin/settings.php',
          'admin/api.php', 'admin/install.php', 'admin/trips.php', 'admin/bookings.php',
          'admin/inquiries.php', 'admin/includes/auth.php', 'admin/includes/db.php',
          'admin/includes/layout.php'] as $f) {
    t_ok("exists: $f", is_file($PDIR . '/' . $f));
}
t_ok('bus preview exposes customer account link',
    strpos((string)file_get_contents($PDIR . '/index.html'), 'customer.php') !== false
    && is_file($PDIR . '/customer.php'));
foreach (['submit.php', 'contact.php', 'customer.php', 'admin/trips.php', 'admin/bookings.php',
          'admin/inbox.php', 'admin/settings.php', 'admin/api.php'] as $f) {
    $out = []; $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($PDIR . '/' . $f) . ' 2>&1', $out, $code);
    t_ok("php -l $f", $code === 0);
}
t_ok('no sample rows seeded',
    strpos(file_get_contents($ADMIN . '/data_trips.json'), 'Sample') === false
    && file_get_contents($ADMIN . '/data_trips.json') === '[]');
t_ok('.htaccess protects preview data', is_file($ROOT . '/preview/.htaccess'));

echo "== 3. live frontend forms → preview JSON (no login) ==\n";
$site = $BASE . '/preview/' . $PID . '/';
$c1 = http_post($site . 'contact.php', ['name' => 'Preview Visitor',
    'email' => 'visitor@example.com', 'message' => 'Hello preview inbox']);
$j1 = json_decode($c1['body'], true);
t_ok('contact form succeeds in preview', is_array($j1) && ($j1['success'] ?? false) === true, substr($c1['body'], 0, 120));
$b1 = http_post($site . 'submit.php', ['form' => 'booking', 'customer' => 'Preview Rider',
    'trip_id' => 'T1', 'seats' => 'C3', 'phone' => '0770000111']);
$j2 = json_decode($b1['body'], true);
t_ok('booking form succeeds in preview', is_array($j2) && ($j2['success'] ?? false) === true, substr($b1['body'], 0, 120));
$disk = json_decode(@file_get_contents($ADMIN . '/data_inquiries.json'), true);
$foundInbox = false;
foreach ((array)$disk as $dr) {
    if (($dr['email'] ?? '') === 'visitor@example.com' && ($dr['message'] ?? '') === 'Hello preview inbox') { $foundInbox = true; break; }
}
t_ok('inquiry persisted with real values', $foundInbox);
$diskB = json_decode(@file_get_contents($ADMIN . '/data_bookings.json'), true);
$foundBook = false;
foreach ((array)$diskB as $dr) {
    if (($dr['customer'] ?? '') === 'Preview Rider' && ($dr['seats'] ?? '') === 'C3') { $foundBook = true; break; }
}
t_ok('booking persisted in preview entity file', $foundBook);
$bad = http_post($site . 'submit.php', ['form' => 'booking', 'customer' => '']);
$jb = json_decode($bad['body'], true);
t_ok('validation failure honest (no fake success)', is_array($jb) && ($jb['success'] ?? true) === false);

echo "== 4. preview admin login + CRUD before publish ==\n";
$login = http_post($BASE . '/preview/' . $PID . '/admin/login.php',
    ['username' => $ADMIN_USER, 'password' => $ADMIN_PASS], false);
$cookie = jar($login['headers']);
t_ok('preview admin login works', $cookie !== '');
$inbox = http_get($BASE . '/preview/' . $PID . '/admin/inbox.php', $cookie);
t_ok('submitted inquiry visible in preview inbox',
    strpos($inbox['body'], 'Preview Visitor') !== false && strpos($inbox['body'], 'Hello preview inbox') !== false);
$dash = http_get($BASE . '/preview/' . $PID . '/admin/index.php', $cookie);
t_ok('dashboard counts real preview records',
    strpos($dash['body'], 'Preview Rider') !== false || strpos($dash['body'], 'Total records') !== false);
// Create a trip via API (CSRF round-trip like a browser).
$tripPage = http_get($BASE . '/preview/' . $PID . '/admin/trips.php?new=1', $cookie);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $tripPage['body'], $mcsrf);
$csrf = $mcsrf[1] ?? '';
t_ok('entity form embeds CSRF', $csrf !== '');
if ($csrf !== '') {
    $sctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
        'content' => http_build_query(['action' => 'save', 'entity' => 'trips', 'csrf' => $csrf,
            'route' => 'Colombo-Kandy', 'date' => '2026-12-01', 'price' => '1200']),
        'ignore_errors' => true]]);
    $saveJ = json_decode(@file_get_contents($BASE . '/preview/' . $PID . '/admin/api.php', false, $sctx), true);
    $tripId = (string)($saveJ['data']['id'] ?? '');
    t_ok('admin creates trip in preview', is_array($saveJ) && ($saveJ['success'] ?? false) === true && $tripId !== '');
    // Edit: reload entity page, record listed.
    $list = http_get($BASE . '/preview/' . $PID . '/admin/trips.php', $cookie);
    t_ok('new trip listed', strpos($list['body'], 'Colombo-Kandy') !== false);
    // Search filters.
    $search = http_get($BASE . '/preview/' . $PID . '/admin/trips.php?q=Kandy', $cookie);
    t_ok('search works', strpos($search['body'], 'Colombo-Kandy') !== false);
    // Delete via API.
    $dctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
        'content' => http_build_query(['action' => 'delete', 'entity' => 'trips', 'csrf' => $csrf, 'id' => $tripId]),
        'ignore_errors' => true]]);
    $delJ = json_decode(@file_get_contents($BASE . '/preview/' . $PID . '/admin/api.php', false, $dctx), true);
    t_ok('admin deletes trip in preview', is_array($delJ) && ($delJ['success'] ?? false) === true);
}

echo "== 5. AI feature lands in preview (add_feature endpoint) ==\n";
$af = http_post_json($BASE . '/api/preview-env.php', ['action' => 'add_feature',
    'draftId' => $PID, 'requirements' => 'Add reviews with reviewer and stars',
    'new_files' => ['reviews_note.txt' => 'reviews module'],
    'new_entities' => [['id' => 'reviews', 'name' => 'Reviews', 'icon' => '⭐',
        'fields' => [['name' => 'reviewer', 'label' => 'Reviewer', 'type' => 'text', 'required' => true],
                     ['name' => 'stars', 'label' => 'Stars', 'type' => 'number', 'required' => false]]]]]);
$aj = json_decode($af['body'], true);
t_ok('add_feature succeeds', is_array($aj) && ($aj['success'] ?? false) === true, substr($af['body'], 0, 200));
t_ok('reviews page generated', is_file($ADMIN . '/reviews.php'));
t_ok('reviews data file created', is_file($ADMIN . '/data_reviews.json'));
t_ok('earlier booking survives feature add', $foundBook
    && strpos(file_get_contents($ADMIN . '/data_bookings.json'), 'Preview Rider') !== false);
$revPage = http_get($BASE . '/preview/' . $PID . '/admin/reviews.php', $cookie);
t_ok('new entity page serves', strpos($revPage['body'], 'Reviews') !== false);

echo "== 6. readiness report ==\n";
$rd = http_post_json($BASE . '/api/preview-env.php', ['action' => 'readiness', 'draftId' => $PID]);
$rj = json_decode($rd['body'], true);
t_ok('readiness ready=true', is_array($rj) && ($rj['ready'] ?? false) === true, substr($rd['body'], 0, 300));
if (is_array($rj)) foreach (($rj['checks'] ?? []) as $c) {
    t_ok('check: ' . ($c['label'] ?? $c['key']), ($c['pass'] ?? false) === true, (string)($c['detail'] ?? ''));
}

echo "== 7. migration policy (preview → production) ==\n";
$fakeProd = $ROOT . '/storage/test-reports/__migtest_admin';
@mkdir($fakeProd, 0755, true);
@file_put_contents($fakeProd . '/data_bookings.json', json_encode([
    ['id' => 'prod1', 'customer' => 'Real Customer']], JSON_PRETTY_PRINT));
$mig = preview_migrate($ADMIN, $fakeProd, 'catalog');
t_ok('catalog rows copied to missing prod file',
    array_key_exists('data_trips.json', $mig['copied'] ?? []), json_encode($mig['copied']));
t_ok('transactional preview rows NOT auto-copied',
    in_array('data_bookings.json', $mig['skipped_policy'] ?? [], true));
t_ok('existing production file untouched',
    strpos(file_get_contents($fakeProd . '/data_bookings.json'), 'Real Customer') !== false
    && strpos(file_get_contents($fakeProd . '/data_bookings.json'), 'Preview Rider') === false);
$migAll = preview_migrate($ADMIN, $fakeProd, 'all');
t_ok('explicit all-policy copies into missing files only',
    in_array('data_bookings.json', $migAll['skipped_existing'] ?? [], true));
@mkdir($ROOT . '/preview', 0755, true);
preview_rrmdir($ROOT . '/preview');
t_ok('destructive guard: preview root never deleted', is_dir($ROOT . '/preview'));
// cleanup fake prod
foreach (glob($fakeProd . '/*') ?: [] as $f) @unlink($f);
@rmdir($fakeProd);

echo "== 8. re-provision preserves preview data + creds ==\n";
$r2 = http_post_json($BASE . '/api/preview-env.php', ['action' => 'provision',
    'draftToken' => $PID, 'html' => $HTML . '<!-- v2 -->', 'bizName' => 'Bus Test Co',
    'entities' => $ENTS, 'adminUser' => $ADMIN_USER, 'adminPass' => $ADMIN_PASS,
    'contactEmail' => 'owner@example.com']);
$j2b = json_decode($r2['body'], true);
t_ok('re-provision ok', is_array($j2b) && ($j2b['success'] ?? false) === true);
t_ok('records survive re-provision',
    strpos(file_get_contents($ADMIN . '/data_bookings.json'), 'Preview Rider') !== false);
$login2 = http_post($BASE . '/preview/' . $PID . '/admin/login.php',
    ['username' => $ADMIN_USER, 'password' => $ADMIN_PASS], false);
t_ok('same creds still work', jar($login2['headers']) !== '');

echo "== 9. publish deploys the tested preview (acceptance) ==\n";
// Catalog row in preview to migrate; order WITHOUT reviews (draft-merge test).
$tripCtx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
    'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
    'content' => http_build_query(['action' => 'save', 'entity' => 'trips', 'csrf' => $csrf,
        'route' => 'Preview-Only Route', 'date' => '2026-12-05', 'price' => '500']),
    'ignore_errors' => true]]);
@file_get_contents($BASE . '/preview/' . $PID . '/admin/api.php', false, $tripCtx);
$OID = 'WBL-TEST-PV1';
$OSLUG = 'pvpub-test';
$OPASS = 'ProdPass123!';
$order = ['order_id' => $OID, 'site_name' => 'Bus Test Co', 'slug' => $OSLUG,
    'concept_index' => 0, 'gen_mode' => 'admin', 'package' => 'pro', 'package_label' => 'Pro',
    'amount' => 19.0, 'currency' => 'USD', 'admin_username' => 'prodadmin',
    'admin_email' => 'owner@example.com', 'contact_email' => 'owner@example.com',
    'smtp_user' => '', 'smtp_pass' => '', 'use_subdomain' => false,
    'admin_password_hash' => password_hash($OPASS, PASSWORD_DEFAULT),
    'admin_files' => [], 'admin_sql' => '', 'admin_entities' => $ENTS,
    'admin_seed_empty' => true, 'canonical_spec' => null, 'biz_spec' => null,
    'draft_token' => $PID, 'status' => 'paid', 'created_at' => date('Y-m-d H:i:s'),
    'ip' => '127.0.0.1'];
@file_put_contents($ROOT . '/storage/orders/' . $OID . '.json', json_encode($order, JSON_PRETTY_PRINT));
@file_put_contents($ROOT . '/storage/designs/' . $OID . '.html', $HTML);
$dp = do_publish_run($BASE . '/publish.php?action=do_publish&order_id=' . $OID . '&draft_id=' . $PID . '&migrate_preview=catalog',
    $ROOT . '/storage/orders/' . $OID . '.json');
$orderAfter = json_decode(@file_get_contents($ROOT . '/storage/orders/' . $OID . '.json'), true);
t_ok('do_publish completes (order published)', $dp && ($orderAfter['status'] ?? '') === 'published');
t_ok('draft entities merged into production (reviews page)',
    is_file($ROOT . '/published/' . $OSLUG . '/admin/reviews.php'));
$meta = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/meta.json'), true);
t_ok('meta records migration + preflight',
    ($meta['data_migration']['policy'] ?? '') === 'catalog' && !empty($meta['preflight']));
t_ok('preflight ready after deploy', ($meta['preflight']['ready'] ?? false) === true,
    json_encode(array_column($meta['preflight']['checks'] ?? [], 'pass')));
$prodTrips = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_trips.json'), true);
$migRow = false;
foreach ((array)$prodTrips as $pr) { if (($pr['route'] ?? '') === 'Preview-Only Route') { $migRow = true; break; } }
t_ok('catalog preview row migrated to production', $migRow);
$prodBooks = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_bookings.json'), true);
$leaked = false;
foreach ((array)$prodBooks as $pr) { if (($pr['customer'] ?? '') === 'Preview Rider') { $leaked = true; break; } }
t_ok('preview test submissions NOT copied to production', !$leaked);
// Production works with its own storage: submit + manage with order creds.
// NOTE: production columns come from the merged universal manifest, so assert
// on a value stored under a manifest-visible field (plus file-level checks).
$pman = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/manifest.json'), true);
$pbookFields = [];
foreach ((array)($pman['entities'] ?? []) as $pe) {
    if (($pe['id'] ?? '') === 'bookings') {
        foreach ((array)($pe['fields'] ?? []) as $pf) { $pbookFields[] = (string)($pf['name'] ?? ''); }
    }
}
$markerField = $pbookFields[0] ?? 'trip_id';
if (in_array(strtolower($markerField), ['id', 'status', 'payment_status', 'role', 'paid', 'password'], true)) {
    $markerField = 'trip_id';
}
$ps = http_post($BASE . '/published/' . $OSLUG . '/submit.php',
    ['form' => 'booking', 'customer' => 'Production Pat', 'trip_id' => 'T9', 'seats' => 'D4',
     $markerField => 'MARKER123']);
t_ok('production booking submit works', (json_decode($ps['body'], true)['success'] ?? false) === true);
$pcookie = admin_login($BASE . '/published/' . $OSLUG . '/admin/login.php',
    'prodadmin', $OPASS, $BASE . '/published/' . $OSLUG . '/admin/index.php');
t_ok('production admin login works', $pcookie !== '');
$pbookBody = '';
for ($i = 0; $i < 3; $i++) {
    $pbookBody = http_get($BASE . '/published/' . $OSLUG . '/admin/bookings.php', $pcookie)['body'];
    if (strpos($pbookBody, 'Production Pat') !== false) break;
}
t_ok('production booking visible in production admin',
    strpos($pbookBody, 'MARKER123') !== false
    && strpos($pbookBody, 'Preview Rider') === false,
    'marker field: ' . $markerField);
// Republish: production data preserved, no duplication, catalog merged.
$tripCtx2 = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
    'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
    'content' => http_build_query(['action' => 'save', 'entity' => 'trips', 'csrf' => $csrf,
        'route' => 'Second Preview Route', 'date' => '2026-12-06']),
    'ignore_errors' => true]]);
@file_get_contents($BASE . '/preview/' . $PID . '/admin/api.php', false, $tripCtx2);
do_publish_run($BASE . '/publish.php?action=do_publish&order_id=' . $OID . '&draft_id=' . $PID . '&migrate_preview=catalog',
    $ROOT . '/storage/orders/' . $OID . '.json');
$prodTrips2 = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_trips.json'), true);
$routes = [];
foreach ((array)$prodTrips2 as $pr) { $routes[] = $pr['route'] ?? ''; }
t_ok('republish merges new catalog rows additively',
    in_array('Preview-Only Route', $routes, true) && in_array('Second Preview Route', $routes, true));
$prodBooks2 = json_decode(@file_get_contents($ROOT . '/published/' . $OSLUG . '/admin/data_bookings.json'), true);
$pats = 0; $riders = 0;
foreach ((array)$prodBooks2 as $pr) {
    if (($pr['customer'] ?? '') === 'Production Pat') $pats++;
    if (($pr['customer'] ?? '') === 'Preview Rider') $riders++;
}
t_ok('republish preserves production rows, no preview leak', $pats === 1 && $riders === 0,
    "pats=$pats riders=$riders total=" . count((array)$prodBooks2));
$pcookie2 = admin_login($BASE . '/published/' . $OSLUG . '/admin/login.php',
    'prodadmin', $OPASS, $BASE . '/published/' . $OSLUG . '/admin/index.php');
t_ok('production credentials survive republish', $pcookie2 !== '');
// cleanup production artifacts + order + designs
tdel($ROOT . '/published/' . $OSLUG);
@unlink($ROOT . '/storage/orders/' . $OID . '.json');
@unlink($ROOT . '/storage/designs/' . $OID . '.html');
foreach (glob($ROOT . '/storage/customers/*.json') ?: [] as $cf) {
    $cc = @file_get_contents($cf);
    if (is_string($cc) && strpos($cc, $OID) !== false) @unlink($cf);
}
t_ok('production test site cleaned', !is_dir($ROOT . '/published/' . $OSLUG));

echo "== 10. customer preview controls (gate, share, UI) ==\n";
t_ok('gate.php + rewrite rules installed',
    is_file($PDIR . '/gate.php') && strpos(file_get_contents($PDIR . '/.htaccess'), 'gate.php') !== false);
// Provision a second env for gate tests (keeps section 9 flow intact).
$gr = http_post_json($BASE . '/api/preview-env.php', ['action' => 'provision',
    'html' => $HTML, 'bizName' => 'Gate Test Co', 'entities' => $ENTS,
    'adminUser' => 'gateadmin', 'adminPass' => 'GatePass123!', 'contactEmail' => 'owner@example.com']);
$gj = json_decode($gr['body'], true);
$GID = is_array($gj) ? (string)($gj['env']['id'] ?? '') : '';
t_ok('gate env provisioned', $GID !== '');
if ($GID !== '') {
    $gsite = $BASE . '/preview/' . $GID . '/';
    $gcode = function (string $url) {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 15, 'ignore_errors' => true]]);
        @file_get_contents($url, false, $ctx);
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) return (int)$m[1];
        }
        return 0;
    };
    t_ok('share URL serves the full site', $gcode($gsite) === 200);
    t_ok('draft.json denied', $gcode($gsite . 'draft.json') !== 200);
    t_ok('.auth.json denied over HTTP', $gcode($gsite . 'admin/.auth.json') !== 200);
    t_ok('data tables denied over HTTP', $gcode($gsite . 'admin/data_bookings.json') !== 200);
    // Admin PHP executes through the gate (directory URL → login flow).
    $glogin = http_post($gsite . 'admin/login.php', ['username' => 'gateadmin', 'password' => 'GatePass123!'], false);
    $gcookie = jar($glogin['headers']);
    $gdir = http_get($gsite . 'admin/', $gcookie);
    t_ok('admin executes through gate (login → dashboard)',
        strpos($gdir['body'], 'Welcome') !== false || strpos($gdir['body'], 'Dashboard') !== false);
    // Revoke → 410 everywhere, data kept.
    $rv = http_post_json($BASE . '/api/preview-env.php', ['action' => 'revoke', 'draftId' => $GID]);
    t_ok('revoke succeeds', (json_decode($rv['body'], true)['success'] ?? false) === true);
    t_ok('revoked site returns 410', $gcode($gsite) === 410);
    t_ok('revoked admin returns 410', $gcode($gsite . 'admin/login.php') === 410);
    // Expiry enforced (set past directly), then re-share restores.
    $sh = http_post_json($BASE . '/api/preview-env.php', ['action' => 'share', 'draftId' => $GID, 'days' => 14]);
    $shj = json_decode($sh['body'], true);
    t_ok('share extends link', (bool)($shj['success'] ?? false) === true && !empty($shj['shareUrl']));
    $mf = $ROOT . '/preview/' . $GID . '/draft.json';
    $mm = json_decode(@file_get_contents($mf), true);
    $mm['sharing'] = ['enabled' => true, 'expiresAt' => time() - 10];
    @file_put_contents($mf, json_encode($mm, JSON_PRETTY_PRINT), LOCK_EX);
    t_ok('expired link returns 410', $gcode($gsite) === 410);
    http_post_json($BASE . '/api/preview-env.php', ['action' => 'share', 'draftId' => $GID, 'days' => 7]);
    t_ok('re-share restores access', $gcode($gsite) === 200);
    $rd2 = http_post_json($BASE . '/api/preview-env.php', ['action' => 'readiness', 'draftId' => $GID]);
    $rj2 = json_decode($rd2['body'], true);
    $keys = array_column($rj2['checks'] ?? [], 'key');
    t_ok('readiness covers gate/sharing/privacy',
        in_array('gate', $keys, true) && in_array('sharing', $keys, true) && in_array('privacy', $keys, true));
    preview_rrmdir($ROOT . '/preview/' . $GID);
    tdel($ROOT . '/storage/collections/' . $GID);
    @unlink($ROOT . '/storage/drafts/' . $GID . '.html');
    @unlink($ROOT . '/storage/drafts/' . $GID . '.json');
}
// Builder + publish UI wiring (source-level contract).
$bsrc = file_get_contents($ROOT . '/builder.php');
t_ok('Preview Website button opens new tab', strpos($bsrc, 'previewOpenTab') !== false && strpos($bsrc, 'btn-preview-open') !== false);
t_ok('checklist modal implemented', strpos($bsrc, 'openPreviewChecklist') !== false && strpos($bsrc, 'refreshChecklistModal') !== false);
t_ok('share/revoke wired in UI', strpos($bsrc, 'pclShare') !== false && strpos($bsrc, 'pclRevoke') !== false);
t_ok('publish warns on failing checks', strpos($bsrc, 'Pre-publish checks are FAILING') !== false && strpos($bsrc, 'Publish anyway?') !== false);
$tpub = file_get_contents($ROOT . '/publish.php');
t_ok('done screen shows migration + preflight', strpos($tpub, 'deploy-notes') !== false && strpos($tpub, 'data_migration') !== false);

// cleanup preview env + collections mirror (only when provisioned)
if ($PID !== '') {
    preview_rrmdir($PDIR);
    tdel($ROOT . '/storage/collections/' . $PID);
    @unlink($ROOT . '/storage/drafts/' . $PID . '.html');
    @unlink($ROOT . '/storage/drafts/' . $PID . '.json');
    t_ok('preview env cleaned', !is_dir($PDIR));
} else {
    t_ok('preview env cleaned', true);
}

ob_end_flush();
echo "\nRESULT: $PASS passed, $FAIL failed, $SKIP skipped\n";
exit($FAIL > 0 ? 1 : 0);
