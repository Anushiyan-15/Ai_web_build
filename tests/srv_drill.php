<?php
// TEMP: server button drill on __bustest2 — strict assertions.
$base = 'http://localhost/project/webbbuilder/published/__bustest2/admin';
function post($url, $f, $cookie = '', $follow = true) {
    $h = "Content-Type: application/x-www-form-urlencoded\r\n";
    if ($cookie !== '') $h .= "Cookie: $cookie\r\n";
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 30,
        'follow_location' => $follow ? 1 : 0, 'header' => $h,
        'content' => http_build_query($f), 'ignore_errors' => true]]);
    $b = @file_get_contents($url, false, $ctx);
    return ['body' => $b === false ? '' : $b, 'headers' => $http_response_header ?? []];
}
function get($url, $c = '') {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 15,
        'header' => $c !== '' ? "Cookie: $c\r\n" : '', 'ignore_errors' => true]]);
    $b = @file_get_contents($url, false, $ctx);
    return $b === false ? '' : $b;
}
function jar($headers) {
    $m = [];
    foreach ($headers as $h) {
        if (preg_match('/^Set-Cookie:\s*([^;=]+)=([^;]*)/i', $h, $mm)) $m[trim($mm[1])] = trim($mm[2]);
    }
    $c = '';
    foreach ($m as $k => $v) $c .= ($c !== '' ? '; ' : '') . $k . '=' . $v;
    return $c;
}
function portOpen() {
    $h = @fsockopen('127.0.0.1', 3100, $no, $str, 2);
    if ($h) { fclose($h); return true; }
    return false;
}
function csrf($base, $cookie) {
    $p = get($base . '/server.php', $cookie);
    if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $p, $m)) return $m[1];
    return '';
}
$fail = 0;
function ok($c, $m) { global $fail; echo ($c ? 'PASS' : 'FAIL') . ": $m\n"; if (!$c) $fail++; }
$l = post($base . '/login.php', ['username' => 'busadmin', 'password' => 'BusTest123!'], '', false);
$cookie = jar($l['headers']);
ok($cookie !== '', 'login');
$p = get($base . '/server.php', $cookie);
if (strpos($p, 'Node.js API — ONLINE') === false) {
    // Cold start: establish a panel-owned baseline first (drill is
    // self-contained — it must not assume a running server).
    $tok0 = csrf($base, $cookie);
    post($base . '/server.php', ['op' => 'start', 'csrf' => $tok0], $cookie);
    sleep(22);
    $p = get($base . '/server.php', $cookie);
}
ok(strpos($p, 'Node.js API — ONLINE') !== false, 'baseline: status shows ONLINE (port open=' . (portOpen() ? 'y' : 'n') . ')');
// unauth + bad csrf
$u = post($base . '/server.php', ['op' => 'stop', 'csrf' => 'x']);
ok(strpos($u['body'], 'Backend Server') === false, 'unauthenticated POST blocked');
$b = post($base . '/server.php', ['op' => 'stop', 'csrf' => 'bad'], $cookie);
ok(strpos($b['body'], 'Invalid session token') !== false, 'bad CSRF rejected (403)');
// stop via button
$tok = csrf($base, $cookie);
$s = post($base . '/server.php', ['op' => 'stop', 'csrf' => $tok], $cookie);
ok(strpos($s['body'], 'Node backend stopped') !== false, 'stop reports stopped');
sleep(6);
ok(!portOpen(), 'port 3100 closed after stop');
// fallback booking while Node down (correct passenger field)
$fb = post('http://localhost/project/webbbuilder/published/__bustest2/submit.php',
    ['form' => 'booking', 'passenger' => 'Fallback Pat', 'phone' => '1', 'trip_id' => 'D9', 'seats' => 'F9']);
$fbj = json_decode($fb['body'], true);
ok(($fbj['success'] ?? false) === true, 'booking works on PHP fallback while stopped');
// start via button
$p2 = get($base . '/server.php', $cookie);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $p2, $m2);
$st = post($base . '/server.php', ['op' => 'start', 'csrf' => ($m2[1] ?? '')], $cookie);
sleep(16);
ok(strpos($st['body'], 'Node backend started') !== false, 'start reports started');
ok(portOpen(), 'port 3100 open after start');
$p3 = get($base . '/server.php', $cookie);
ok(strpos($p3, 'Node.js API — ONLINE') !== false, 'status shows ONLINE after start');
// second start = already-running (no duplicate server)
preg_match('/name="csrf" value="([a-f0-9]+)"/', $p3, $m3);
$st2 = post($base . '/server.php', ['op' => 'start', 'csrf' => ($m3[1] ?? '')], $cookie);
ok(strpos($st2['body'], 'already running') !== false, 'second start refuses duplicate');
echo $fail === 0 ? "SERVER BUTTON DRILL PASSED\n" : "$fail FAILED\n";
exit($fail ? 1 : 0);
