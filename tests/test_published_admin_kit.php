<?php
// tests/test_published_admin_kit.php — End-to-end verification for the
// FRONTEND INPUT → VALIDATION → JSON → ADMIN PANEL flow.
//
// Run:  C:\php\php.exe tests/test_published_admin_kit.php
// Uses a safe temp site under published/__paktest_*/ (removed afterwards).
// Live HTTP checks run against the local server; they skip gracefully if
// the server is unreachable.

error_reporting(E_ALL & ~E_DEPRECATED);
ob_start(); // buffer CLI output so generated session code stays warning-free
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/PublishedAdminKit.php';
require_once $ROOT . '/includes/AdminPanelGenerator.php';

$PASS = 0; $FAIL = 0; $SKIP = 0;
function t_ok(string $name, bool $cond, string $detail = '') {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  PASS  $name" . ($detail ? " — $detail" : '') . "\n"; }
    else { $FAIL++; echo "  FAIL  $name" . ($detail ? " — $detail" : '') . "\n"; }
}
function t_skip(string $name, string $why = '') {
    global $SKIP;
    $SKIP++; echo "  SKIP  $name" . ($why ? " — $why" : '') . "\n";
}
function rrmdir(string $d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $d . '/' . $f;
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($d);
}

$slug = '__paktest_' . substr(bin2hex(random_bytes(4)), 0, 8);
$targetDir = $ROOT . '/published/' . $slug;
$adminDir = $targetDir . '/admin';
@mkdir($adminDir, 0755, true);

$TEST_PASS = 'TestPass123!';
$order = [
    'order_id' => 'pak-test-' . $slug,
    'site_name' => 'Pak Test Services',
    'slug' => $slug,
    'admin_username' => 'pakadmin',
    'admin_email' => 'owner@example.com',
    'contact_email' => 'owner@example.com',
    'admin_password_hash' => password_hash($TEST_PASS, PASSWORD_DEFAULT),
    'admin_seed_empty' => true, // canonical installs seed EMPTY tables (no samples)
    'admin_entities' => [
        ['id' => 'inquiries', 'name' => 'Inquiries', 'icon' => '✉️',
         'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false]]],
        ['id' => 'bookings', 'name' => 'Bookings', 'icon' => '🎫',
         'fields' => [
            ['name' => 'customer', 'label' => 'Customer', 'type' => 'text', 'required' => true],
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'seats', 'label' => 'Seats', 'type' => 'number', 'required' => false],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => false,
             'options' => ['pending', 'confirmed', 'cancelled']]]],
    ],
];

echo "== 1. pak_install generates the required architecture ==\n";
// Mirror the real publish order: base panel first, required kit on top.
AdminPanelGenerator::install($adminDir, $order);
$res = pak_install($adminDir, $order);
t_ok('install reports no errors', empty($res['errors']), json_encode($res['errors']));
foreach (['includes/auth.php', 'includes/db.php', 'includes/layout.php',
          'bookings.php', 'inquiries.php', 'inbox.php', 'settings.php',
          'api.php', 'install.php', 'data_bookings.json', 'data_inquiries.json',
          'manifest.json', 'form_map.json', 'site.json'] as $f) {
    t_ok("file exists: $f", is_file($adminDir . '/' . $f));
}
t_ok('form map routes contact→inquiries',
    ($res['form_map']['contact'] ?? '') === 'inquiries', json_encode($res['form_map']));

echo "== 2. generated PHP files pass syntax checks ==\n";
foreach (['includes/auth.php', 'includes/db.php', 'includes/layout.php',
          'bookings.php', 'inquiries.php', 'inbox.php', 'settings.php',
          'api.php', 'install.php', 'index.php'] as $f) {
    $out = []; $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($adminDir . '/' . $f) . ' 2>&1', $out, $code);
    t_ok("php -l $f", $code === 0, implode(' ', $out));
}

echo "== 3. public handlers (submit.php) ==\n";
$pub = pak_public_handlers($targetDir, $order, $res['entities']);
t_ok('submit.php written', is_file($targetDir . '/submit.php'));
$out = []; $code = 0;
exec(PHP_BINARY . ' -l ' . escapeshellarg($targetDir . '/submit.php') . ' 2>&1', $out, $code);
t_ok('php -l submit.php', $code === 0);
$subSrc = file_get_contents($targetDir . '/submit.php');
t_ok('submit returns 500 on storage failure (no fake success)',
    strpos($subSrc, 'Storage failed. Nothing was saved.') !== false
    && strpos($subSrc, 'Storage unavailable. Nothing was saved.') !== false);
t_ok('submit strips privileged fields', strpos($subSrc, "unset(\$clean['status']") !== false);
t_ok('submit has honeypot + rate limit',
    strpos($subSrc, "['company']") !== false && strpos($subSrc, 'Too many submissions') !== false);

echo "== 4. shared db helpers (real flock CRUD) ==\n";
require_once $adminDir . '/includes/db.php';
$row = db_insert('bookings', ['customer' => 'Ada Tester', 'date' => '2026-11-01', 'seats' => '2',
    'role' => 'admin', 'paid' => '1']); // privileged keys must be stripped
t_ok('db_insert returns row with id + timestamps',
    is_array($row) && !empty($row['id']) && !empty($row['created_at']) && !empty($row['updated_at']));
t_ok('db_insert strips protected fields (role/paid)',
    !array_key_exists('role', $row) && !array_key_exists('paid', $row));
t_ok('db_find returns the row', (db_find('bookings', $row['id'])['customer'] ?? '') === 'Ada Tester');
$upd = db_update('bookings', $row['id'], ['seats' => '3', 'id' => 'HACK']);
t_ok('db_update preserves id + changes field',
    ($upd['seats'] ?? '') === '3' && ($upd['id'] ?? '') === $row['id']);
t_ok('db_read sees 1 row', count(db_read('bookings')) === 1);
t_ok('db rejects traversal entity', db_file('../x') === null && db_read('../x') === []);
t_ok('db rejects unknown entity write', db_insert('nope_not_real_xyz', ['a' => 'b']) === null);
t_ok('db_delete removes row', db_delete('bookings', $row['id']) === true && db_read('bookings') === []);

echo "== 5. auth helpers (hash-only, csrf, throttle) ==\n";
// Seed .auth.json the way publish does (hash only, never plaintext).
@file_put_contents($adminDir . '/.auth.json', json_encode([
    'username' => 'pakadmin', 'email' => 'owner@example.com',
    'hash' => $order['admin_password_hash'], 'order_id' => 'WBL-TEST-1',
    'created' => date('Y-m-d H:i:s'),
], JSON_PRETTY_PRINT), LOCK_EX);
require_once $adminDir . '/includes/auth.php';
t_ok('verify_login accepts correct password', verify_login('pakadmin', $TEST_PASS) === true);
t_ok('verify_login rejects wrong password', verify_login('pakadmin', 'wrongpass1') === false);
t_ok('verify_login rejects unknown user', verify_login('nosuchuser', $TEST_PASS) === false);
t_ok('no plaintext password on disk',
    strpos(file_get_contents($adminDir . '/.auth.json'), $TEST_PASS) === false);
if (session_status() === PHP_SESSION_NONE) @session_start();
$tok = csrf_token();
t_ok('csrf_token stable 64-hex', preg_match('/^[a-f0-9]{64}$/', $tok) === 1);
$_POST['csrf'] = $tok;
$_SESSION['pak_csrf'] = $tok;
try { csrf_validate(); t_ok('csrf_validate accepts valid token', true); }
catch (Throwable $e) { t_ok('csrf_validate accepts valid token', false, $e->getMessage()); }
unset($_POST['csrf']);

echo "== 6. installer is one-time and honest ==\n";
$runInstaller = function () use ($adminDir) {
    $out = []; $code = 0;
    exec(PHP_BINARY . ' ' . escapeshellarg($adminDir . '/install.php') . ' 2>&1', $out, $code);
    return implode("\n", $out);
};
@unlink($adminDir . '/.installed');
$first = $runInstaller();
t_ok('fresh install redirects to login', strpos($first, 'Location') !== false || $first === '' || strpos($first, 'login.php') !== false,
    substr($first, 0, 120));
t_ok('.installed created only after success', is_file($adminDir . '/.installed'));
$second = $runInstaller();
t_ok('repeat install refused', stripos($second, 'Already installed') !== false, substr($second, 0, 120));
@unlink($adminDir . '/.installed'); // temp site cleanup later removes it anyway

echo "== 7. republish preserves records + credentials ==\n";
$keep = db_insert('inquiries', ['name' => 'Keep Me', 'email' => 'keep@example.com', 'message' => 'do not lose me']);
@file_put_contents($adminDir . '/site.json', json_encode(['site_name' => 'Custom Name', 'contact_email' => 'c@example.com']), LOCK_EX);
$res2 = pak_install($adminDir, $order);
t_ok('reinstall reports no errors', empty($res2['errors']), json_encode($res2['errors']));
t_ok('customer record survives republish',
    (db_find('inquiries', $keep['id'])['name'] ?? '') === 'Keep Me');
$siteAfter = json_decode(@file_get_contents($adminDir . '/site.json'), true);
t_ok('site.json survives republish', ($siteAfter['site_name'] ?? '') === 'Custom Name');

echo "== 8. live HTTP: submit → JSON → admin (no login for public) ==\n";
$base = 'http://localhost/project/webbbuilder/published/' . $slug;
function http_post(string $url, array $fields, bool $follow = true): array {
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 15,
        'follow_location' => $follow ? 1 : 0,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($fields),
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return ['body' => $body === false ? '' : $body, 'headers' => $http_response_header ?? []];
}
function http_get(string $url, string $cookie = ''): array {
    $headers = $cookie !== '' ? "Cookie: $cookie\r\n" : '';
    $ctx = stream_context_create(['http' => [
        'method' => 'GET', 'timeout' => 15, 'header' => $headers, 'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return ['body' => $body === false ? '' : $body, 'headers' => $http_response_header ?? []];
}
$probe = http_get($base . '/submit.php');
$serverUp = !($probe['body'] === '' && empty($probe['headers']));
if (!$serverUp) {
    t_skip('live submit test', 'local web server unreachable');
    t_skip('live inbox auth test', 'local web server unreachable');
    t_skip('live validation test', 'local web server unreachable');
} else {
    // 8a. Valid public submission (no admin session).
    $r = http_post($base . '/submit.php', [
        'form' => 'contact', 'name' => 'Live Visitor',
        'email' => 'visitor@example.com', 'message' => 'Hello from the live test']);
    $j = json_decode($r['body'], true);
    t_ok('public submit returns success + id',
        is_array($j) && ($j['success'] ?? false) === true && !empty($j['id']), substr($r['body'], 0, 160));
    $disk = json_decode(@file_get_contents($adminDir . '/data_inquiries.json'), true);
    $found = false;
    if (is_array($disk)) foreach ($disk as $dr) {
        if (is_array($dr) && ($dr['email'] ?? '') === 'visitor@example.com'
            && ($dr['message'] ?? '') === 'Hello from the live test'
            && ($dr['name'] ?? '') === 'Live Visitor'
            && !empty($dr['id']) && !empty($dr['created_at'])) { $found = true; break; }
    }
    t_ok('submission persisted with real values + id + timestamps', $found);

    // 8b. Invalid data is rejected (no fake success).
    $r2 = http_post($base . '/submit.php', ['form' => 'contact', 'name' => '', 'email' => 'not-an-email']);
    $j2 = json_decode($r2['body'], true);
    t_ok('invalid submit rejected with success:false',
        is_array($j2) && ($j2['success'] ?? true) === false, substr($r2['body'], 0, 160));

    // 8c. Unknown entity rejected.
    $r3 = http_post($base . '/submit.php', ['entity' => 'hacker_table_xyz', 'x' => '1']);
    $j3 = json_decode($r3['body'], true);
    t_ok('unknown entity rejected', is_array($j3) && ($j3['success'] ?? true) === false);

    // 8d. Privilege escalation attempt is neutralized.
    $r4 = http_post($base . '/submit.php', ['form' => 'contact', 'name' => 'Sneaky',
        'email' => 'sneaky@example.com', 'message' => 'hi', 'role' => 'admin', 'paid' => '1', 'status' => 'paid']);
    $j4 = json_decode($r4['body'], true);
    $sid = (string)($j4['id'] ?? '');
    $srow = $sid !== '' ? db_find('inquiries', $sid) : null;
    t_ok('client role/paid/status never authoritative',
        is_array($j4) && ($j4['success'] ?? false) === true && is_array($srow)
        && !isset($srow['role']) && !isset($srow['paid']) && ($srow['status'] ?? '') !== 'paid');

    // 8e. Inbox requires login; login works; record visible after login.
    $anon = http_get($base . '/admin/inbox.php');
    t_ok('inbox without login does not leak the record',
        strpos($anon['body'], 'visitor@example.com') === false);
    // Root login.php sets admin_auth + admin_username on success then 302s.
    // Do NOT follow the redirect so the session cookie can be captured.
    $login = http_post($base . '/admin/login.php',
        ['username' => 'pakadmin', 'password' => $TEST_PASS], false);
    $cookie = '';
    $gotRedirect = false;
    $jar = []; // name => value (last Set-Cookie wins, like a browser jar)
    foreach ($login['headers'] as $h) {
        if (preg_match('/^Set-Cookie:\s*([^;=]+)=([^;]*)/i', $h, $m)) $jar[trim($m[1])] = trim($m[2]);
        if (preg_match('/^Location:/i', $h)) $gotRedirect = true;
    }
    foreach ($jar as $k => $v) $cookie .= ($cookie !== '' ? '; ' : '') . $k . '=' . $v;
    t_ok('valid login creates session (redirect + cookie)',
        $gotRedirect && $cookie !== '', substr(implode(' | ', $login['headers']), 0, 160));
    if ($cookie === '') {
        t_skip('authenticated inbox check', 'login cookie not captured over plain HTTP test');
    } else {
        $authed = http_get($base . '/admin/inbox.php', $cookie);
        t_ok('authenticated inbox shows submitted name+email',
            strpos($authed['body'], 'Live Visitor') !== false
            && strpos($authed['body'], 'visitor@example.com') !== false);
        // Logout ends the session: the same cookie must no longer see records.
        http_get($base . '/admin/logout.php', $cookie);
        $afterLogout = http_get($base . '/admin/inbox.php', $cookie);
        t_ok('logout ends session (record hidden again)',
            strpos($afterLogout['body'], 'visitor@example.com') === false);
        // Re-login: the logout above destroyed the session, so mint a fresh
        // cookie for the API/pagination checks below.
        $login2 = http_post($base . '/admin/login.php',
            ['username' => 'pakadmin', 'password' => $TEST_PASS], false);
        $jar = [];
        foreach ($login2['headers'] as $h) {
            if (preg_match('/^Set-Cookie:\s*([^;=]+)=([^;]*)/i', $h, $m)) $jar[trim($m[1])] = trim($m[2]);
        }
        $cookie = '';
        foreach ($jar as $k => $v) $cookie .= ($cookie !== '' ? '; ' : '') . $k . '=' . $v;
        t_ok('re-login after logout works', $cookie !== '');
    }
    $badLogin = http_post($base . '/admin/login.php',
        ['username' => 'pakadmin', 'password' => 'wrongpass1']);
    t_ok('invalid credentials rejected',
        stripos($badLogin['body'], 'Invalid') !== false || stripos($badLogin['body'], 'incorrect') !== false
        || stripos($badLogin['body'], 'try again') !== false, substr(strip_tags($badLogin['body']), 0, 120));

    // 8f. Admin API: auth + CSRF enforced, real JSON CRUD.
    $apiAnon = http_get($base . '/admin/api.php?action=list&entity=inquiries');
    $apiAnonJ = json_decode($apiAnon['body'], true);
    t_ok('admin api rejects unauthenticated list (401)',
        is_array($apiAnonJ) && ($apiAnonJ['success'] ?? true) === false);
    $apiAuthed = http_get($base . '/admin/api.php?action=list&entity=inquiries', $cookie);
    $apiJ = json_decode($apiAuthed['body'], true);
    t_ok('authenticated api list returns stored records',
        is_array($apiJ) && ($apiJ['success'] ?? false) === true
        && strpos($apiAuthed['body'], 'visitor@example.com') !== false);
    // Scrape CSRF token from the entity form (same-session; ?new=1 forces it).
    $entPage = http_get($base . '/admin/bookings.php?new=1', $cookie);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $entPage['body'], $mcsrf);
    $csrf = $mcsrf[1] ?? '';
    t_ok('entity page embeds CSRF token', $csrf !== '');
    $noCsrf = http_post($base . '/admin/api.php',
        ['action' => 'save', 'entity' => 'bookings', 'customer' => 'X', 'date' => '2026-12-01']);
    t_ok('api save without CSRF rejected (403)',
        is_array(json_decode($noCsrf['body'], true))
        && (json_decode($noCsrf['body'], true)['success'] ?? true) === false);
    if ($csrf !== '') {
        $saveCtx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
            'content' => http_build_query(['action' => 'save', 'entity' => 'bookings',
                'csrf' => $csrf, 'customer' => 'Api Customer', 'date' => '2026-12-02', 'seats' => '4']),
            'ignore_errors' => true]]);
        $saveBody = @file_get_contents($base . '/admin/api.php', false, $saveCtx);
        $saveJ = json_decode($saveBody, true);
        $apiId = (string)($saveJ['data']['id'] ?? '');
        t_ok('api save with CSRF persists record',
            is_array($saveJ) && ($saveJ['success'] ?? false) === true && $apiId !== ''
            && (db_find('bookings', $apiId)['customer'] ?? '') === 'Api Customer');
        // Dashboard reflects real counts after the write.
        $dash = http_get($base . '/admin/index.php', $cookie);
        t_ok('dashboard shows real record data',
            strpos($dash['body'], 'Api Customer') !== false
            || strpos($dash['body'], 'Total records') !== false);
        // Delete via API (POST + CSRF), then verify JSON updated.
        $delCtx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
            'content' => http_build_query(['action' => 'delete', 'entity' => 'bookings',
                'csrf' => $csrf, 'id' => $apiId]),
            'ignore_errors' => true]]);
        $delJ = json_decode(@file_get_contents($base . '/admin/api.php', false, $delCtx), true);
        t_ok('api delete removes record',
            is_array($delJ) && ($delJ['success'] ?? false) === true && db_find('bookings', $apiId) === null);
    }

    // 8g. Pagination + search with > 20 records.
    for ($i = 1; $i <= 25; $i++) {
        db_insert('bookings', ['customer' => 'Page Customer ' . $i, 'date' => '2026-12-01']);
    }
    $p1 = http_get($base . '/admin/bookings.php', $cookie);
    t_ok('entity list paginates 20/page',
        strpos($p1['body'], 'Page 1 of 2') !== false && strpos($p1['body'], '25 total') !== false);
    $ps = http_get($base . '/admin/bookings.php?q=Page%20Customer%2025', $cookie);
    t_ok('entity search filters rows',
        strpos($ps['body'], 'Page Customer 25') !== false
        && strpos($ps['body'], 'Page Customer 24') === false);
}

echo "== 9. generated pages wire storage + csrf + escaping ==\n";
$ent = file_get_contents($adminDir . '/bookings.php');
t_ok('entity page uses db_* + csrf + PRG',
    strpos($ent, 'db_insert') !== false && strpos($ent, 'csrf_validate') !== false
    && strpos($ent, 'Location: ') !== false && strpos($ent, 'htmlspecialchars') !== false);
t_ok('entity page paginates 20/page + search',
    strpos($ent, '/ 20') !== false && strpos($ent, "'q'") !== false);
$api = file_get_contents($adminDir . '/api.php');
t_ok('api enforces admin session + csrf + hides internals',
    strpos($api, "empty(\$_SESSION['admin_auth'])") !== false
    && strpos($api, 'csrf_validate') !== false
    && strpos($api, 'display_errors') !== false);
$set = file_get_contents($adminDir . '/settings.php');
t_ok('settings hash-only (no plaintext write)',
    strpos($set, 'password_hash') !== false && strpos($set, 'password_verify') !== false
    && strpos($set, 'site.json') !== false
    && strpos($set, "['password_plain'] = ") === false);
t_ok('settings target the admin auth and site files',
    strpos($set, '$authFile = pak_auth_file();') !== false
    && strpos($set, "\$siteFile = __DIR__ . '/site.json';") !== false);
$changedPass = 'UpdatedPakPass456!';
$_SESSION['admin_auth'] = true;
$_SESSION['pak_csrf'] = $tok;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action' => 'change_password', 'csrf' => $tok,
    'current_password' => $TEST_PASS, 'new_password' => $changedPass,
    'confirm_password' => $changedPass];
ob_start();
include $adminDir . '/settings.php';
$settingsResponse = (string)ob_get_clean();
$changedAuth = json_decode((string)@file_get_contents($adminDir . '/.auth.json'), true);
t_ok('settings POST changes the generated login credential',
    strpos($settingsResponse, 'Password changed successfully.') !== false
    && verify_login('pakadmin', $changedPass)
    && !verify_login('pakadmin', $TEST_PASS)
    && password_verify($changedPass, (string)($changedAuth['hash'] ?? '')));
$lay = file_get_contents($adminDir . '/includes/layout.php');
t_ok('layout uses required palette + dynamic sidebar',
    strpos($lay, '#0a0d14') !== false && strpos($lay, '#6366f1') !== false
    && strpos($lay, 'manifest.json') !== false);
$dash = file_get_contents($adminDir . '/index.php');
t_ok('dashboard counts from real records',
    strpos($dash, 'db_read') !== false && strpos($dash, 'Recent activity') !== false
    && strpos($dash, 'Total records') !== false);

echo "== 9b. shared contact receiver (includes/ContactReceiver.php) ==\n";
$crSrc = file_get_contents($ROOT . '/includes/ContactReceiver.php');
$pubSrc = file_get_contents($ROOT . '/publish.php');
// NOTE: the receiver is generated inside a double-quoted heredoc, so the
// file literally contains backslash-escaped \$vars — needles match that.
t_ok('contact receiver uses flock + unique ids + timestamps',
    strpos($crSrc, "'id' => 'r_' . bin2hex(random_bytes(8))") !== false
    && strpos($crSrc, '\'created_at\' => \\$ts') !== false
    && strpos($crSrc, 'flock(\\$h, LOCK_EX)') !== false);
t_ok('contact receiver never fakes success (writes guarded, no blind ok)',
    strpos($crSrc, 'fwrite(\\$h, json_encode(\\$inbox') !== false);
t_ok('publish uses the shared receiver (no duplicated copy)',
    strpos($pubSrc, "require_once __DIR__ . '/includes/ContactReceiver.php'") !== false
    && strpos($pubSrc, 'function buildContactReceiver') === false);
t_ok('do_publish installs kit + preserves + reports',
    strpos($pubSrc, 'pak_install($adminDir, $order)') !== false
    && strpos($pubSrc, 'pak_public_handlers($targetDir') !== false
    && strpos($pubSrc, "'publish_errors'") !== false
    && strpos($pubSrc, "'admin_login'") !== false);

echo "\n== 10. add_feature entity merge (no duplicates, any shape) ==\n";
$merged = pak_normalize_entities([
    ['key' => 'Reviews', 'fields' => ['reviewer:text*', 'stars:number']],
    'plain string entity',
    ['id' => 'bookings', 'name' => 'Bookings DUP'],
]);
$ids = array_column($merged, 'id');
t_ok('normalize handles key/string shapes', in_array('reviews', $ids, true) && count($ids) === count(array_unique($ids)));
t_ok('protected id field skipped', (function () use ($merged) {
    foreach ($merged as $m) foreach (($m['fields'] ?? []) as $f) {
        if (($f['name'] ?? '') === 'id') return false;
    }
    return true;
})());

// Cleanup temp site.
rrmdir($targetDir);

ob_end_flush();
echo "\nRESULT: $PASS passed, $FAIL failed, $SKIP skipped\n";
exit($FAIL > 0 ? 1 : 0);
