<?php
// submit.php — Universal PUBLIC form submission handler (WebCraft AI).
// No admin login required. Every submission is server-validated, guarded
// (honeypot + rate limit + size cap + duplicate protection), saved with
// flock to admin/data_<entity>.json, and appears in the matching admin
// page on next load. False success is never returned: storage failure
// yields HTTP 500 + success:false.
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$ENTITIES = json_decode('[{"id":"trips","name":"Trips","singular":"Trip","icon":"📁","fields":[{"name":"route","label":"Route","type":"text","required":false,"options":[]},{"name":"date","label":"Date","type":"text","required":false,"options":[]},{"name":"departure","label":"Departure","type":"text","required":false,"options":[]},{"name":"price","label":"Price","type":"text","required":false,"options":[]},{"name":"total_seats","label":"Total Seats","type":"text","required":false,"options":[]}]},{"id":"bookings","name":"Bookings","singular":"Booking","icon":"📁","fields":[{"name":"trip_id","label":"Trip Id","type":"text","required":false,"options":[]},{"name":"seats","label":"Seats","type":"text","required":false,"options":[]},{"name":"passenger","label":"Passenger","type":"text","required":false,"options":[]},{"name":"phone","label":"Phone","type":"text","required":false,"options":[]},{"name":"status","label":"Status","type":"text","required":false,"options":[]},{"name":"payment_status","label":"Payment Status","type":"text","required":false,"options":[]},{"name":"ref","label":"Ref","type":"text","required":false,"options":[]},{"name":"qr","label":"Qr","type":"text","required":false,"options":[]}]},{"id":"payments","name":"Payments","singular":"Payment","icon":"📁","fields":[{"name":"booking_ref","label":"Booking Ref","type":"text","required":false,"options":[]},{"name":"method","label":"Method","type":"text","required":false,"options":[]},{"name":"amount","label":"Amount","type":"text","required":false,"options":[]},{"name":"status","label":"Status","type":"text","required":false,"options":[]},{"name":"mode","label":"Mode","type":"text","required":false,"options":[]},{"name":"transaction_id","label":"Transaction Id","type":"text","required":false,"options":[]},{"name":"timestamp","label":"Timestamp","type":"text","required":false,"options":[]}]},{"id":"buses","name":"Bus","singular":"Bus","icon":"📁","fields":[{"name":"route","label":"Route","type":"text","required":false,"options":[]},{"name":"capacity","label":"Capacity","type":"text","required":false,"options":[]},{"name":"schedule","label":"Schedule","type":"text","required":false,"options":[]}]},{"id":"seat","name":"Seat","singular":"Seat","icon":"📁","fields":[{"name":"seat_number","label":"Seat Number","type":"text","required":false,"options":[]},{"name":"status","label":"Status","type":"text","required":false,"options":[]},{"name":"price","label":"Price","type":"text","required":false,"options":[]}]},{"id":"tickets","name":"Ticket","singular":"Ticket","icon":"📁","fields":[{"name":"qr_code","label":"Qr Code","type":"text","required":false,"options":[]},{"name":"pnr","label":"Pnr","type":"text","required":false,"options":[]},{"name":"fare","label":"Fare","type":"text","required":false,"options":[]},{"name":"payment_status","label":"Payment Status","type":"text","required":false,"options":[]}]}]', true) ?: [];
$FORM_MAP = json_decode('{"contact":"trips","booking":"bookings","order":"trips","registration":"trips","review":"trips","quote":"trips","newsletter":"trips","appointment":"trips"}', true) ?: [];
$PROTECTED = ['id'=>1,'created_at'=>1,'updated_at'=>1,'role'=>1,'is_admin'=>1,'admin'=>1,
    'paid'=>1,'approved'=>1,'permissions'=>1,'password'=>1,'password_hash'=>1,'hash'=>1,
    'secret'=>1,'status'=>0]; // status stripped below for public writes

function submit_out(int $http, array $body): void {
    http_response_code($http);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
function submit_entity_defs(string $id, array $entities): ?array {
    foreach ($entities as $e) {
        if (($e['id'] ?? '') === $id) return $e;
    }
    return null;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    submit_out(405, ['success' => false, 'error' => 'Method not allowed.']);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 262144) {
    submit_out(413, ['success' => false, 'error' => 'Submission too large.']);
}
// JSON or classic form posts both accepted.
$input = $_POST;
$raw = file_get_contents('php://input');
if (empty($input) && $raw !== false && $raw !== '') {
    $j = json_decode($raw, true);
    if (is_array($j)) $input = $j;
}
// Honeypot: bots fill it, humans don't. Quietly "succeed" to deter retries.
if (!empty($input['company'])) { submit_out(200, ['success' => true]); }
// Resolve entity: explicit `entity` wins; else `form` purpose maps via manifest.
$entity = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string)($input['entity'] ?? '')));
if ($entity === '') {
    $purpose = strtolower(trim((string)($input['form'] ?? 'contact')));
    $entity = (string)($FORM_MAP[$purpose] ?? $FORM_MAP['contact'] ?? 'inquiries');
}
$defs = submit_entity_defs($entity, $ENTITIES);
if ($defs === null) submit_out(400, ['success' => false, 'error' => 'Unknown form destination.']);
// Rate limit: 20 submissions/hour per IP+entity.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$hitsFile = __DIR__ . '/submit_hits.json';
$hits = is_file($hitsFile) ? (json_decode(@file_get_contents($hitsFile), true) ?: []) : [];
if (!is_array($hits)) $hits = [];
$now = time();
$hits = array_values(array_filter($hits, fn($h) => is_array($h) && ($now - ($h['t'] ?? 0)) < 3600 && ($h['ip'] ?? '') === $ip && ($h['e'] ?? '') === $entity));
if (count($hits) >= 20) submit_out(429, ['success' => false, 'error' => 'Too many submissions. Please try again later.']);
// Duplicate protection: identical email+message within 60s is deduped.
$clean = [];
foreach (($defs['fields'] ?? []) as $f) {
    $name = (string)($f['name'] ?? '');
    if ($name === '' || $name === 'id') continue;
    $v = is_scalar($input[$name] ?? null) ? strip_tags(trim((string)$input[$name])) : '';
    if (strlen($v) > 5000) $v = substr($v, 0, 5000);
    $t = strtolower((string)($f['type'] ?? 'text'));
    if (!empty($f['required']) && $v === '') submit_out(422, ['success' => false, 'error' => ($f['label'] ?? $name) . ' is required.']);
    if ($v !== '' && $t === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) submit_out(422, ['success' => false, 'error' => 'Enter a valid email.']);
    if ($v !== '' && $t === 'url' && !filter_var($v, FILTER_VALIDATE_URL)) submit_out(422, ['success' => false, 'error' => 'Enter a valid URL.']);
    if ($v !== '' && $t === 'number' && !is_numeric($v)) submit_out(422, ['success' => false, 'error' => ($f['label'] ?? $name) . ' must be a number.']);
    if ($v !== '' && $t === 'select' && !empty($f['options']) && !in_array($v, array_map('strval', $f['options']), true)) submit_out(422, ['success' => false, 'error' => 'Invalid choice.']);
    // Preserve extra submitted business fields (bounded): map unknown keys
    // under 40 safe chars so frontend additions are never silently dropped.
    $clean[$name] = $v;
}
foreach ($input as $k => $v) {
    if (!is_string($k) || array_key_exists($k, $clean)) continue;
    if (in_array($k, ['entity','form','csrf','action','id','company','idempotency_key'], true)) continue;
    if (isset($PROTECTED[$k]) && $k !== 'status') continue;
    if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/i', $k)) continue;
    if (!is_scalar($v)) continue;
    $s = substr(strip_tags(trim((string)$v)), 0, 2000);
    if ($s !== '') $clean[$k] = $s;
    if (count($clean) >= 30) break;
}
// Client-submitted privilege/status fields are NEVER authoritative.
unset($clean['status'], $clean['payment_status'], $clean['role'], $clean['paid']);
$hasEmail = false;
foreach ($clean as $v) { if (is_string($v) && strpos($v, '@') !== false) { $hasEmail = true; break; } }
// Default processing status per entity kind.
$statusDefault = in_array($entity, ['bookings','appointments','reservations','orders','viewings','rentals','enrollments','tickets'], true)
    ? 'pending' : 'unread';
$clean['status'] = $statusDefault;
if ($entity === 'inquiries') $clean['read'] = false;
// Locked read-modify-write on the site's own admin data file.
$adminDir = __DIR__ . '/admin';
if (!is_dir($adminDir)) @mkdir($adminDir, 0755, true);
$dataFile = $adminDir . '/data_' . $entity . '.json';
$h = @fopen($dataFile, 'c+');
if (!$h) submit_out(500, ['success' => false, 'error' => 'Storage unavailable. Nothing was saved.']);
$record = null;
$dup = false;
try {
    if (!flock($h, LOCK_EX)) submit_out(500, ['success' => false, 'error' => 'Storage busy. Try again.']);
    $rawRows = stream_get_contents($h);
    $rows = ($rawRows !== false && trim((string)$rawRows) !== '') ? (json_decode($rawRows, true) ?: []) : [];
    if (!is_array($rows)) $rows = [];
    $rows = array_values($rows);
    // 60-second near-duplicate guard (same email + same longest text field).
    $probe = '';
    foreach ($clean as $v) { if (is_string($v) && strlen($v) > strlen($probe)) $probe = strtolower($v); }
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $ca = strtotime($r['created_at'] ?? '');
        if (!$ca || ($now - $ca) > 60) continue;
        $hay = strtolower(json_encode($r));
        $em = '';
        foreach (['email'] as $ek) { if (!empty($clean[$ek])) $em = strtolower((string)$clean[$ek]); }
        if ($em !== '' && strpos($hay, $em) !== false && $probe !== '' && strpos($hay, substr($probe, 0, 40)) !== false) { $dup = true; break; }
    }
    if (!$dup) {
        $ts = date('c');
        $record = array_merge($clean, ['id' => 'r_' . bin2hex(random_bytes(8)),
            'created_at' => $ts, 'updated_at' => $ts]);
        array_unshift($rows, $record);
        if (count($rows) > 2000) $rows = array_slice($rows, 0, 2000);
        ftruncate($h, 0);
        rewind($h);
        if (fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            submit_out(500, ['success' => false, 'error' => 'Storage failed. Nothing was saved.']);
        }
        fflush($h);
    }
} finally {
    flock($h, LOCK_UN);
    fclose($h);
}
if ($dup) submit_out(200, ['success' => true, 'deduped' => true]);
// Record rate-limit hit only on accepted writes.
$hits[] = ['ip' => $ip, 'e' => $entity, 't' => $now];
@file_put_contents($hitsFile, json_encode(array_slice($hits, -500), JSON_UNESCAPED_UNICODE), LOCK_EX);
submit_out(200, ['success' => true, 'entity' => $entity, 'id' => (string)($record['id'] ?? '')]);