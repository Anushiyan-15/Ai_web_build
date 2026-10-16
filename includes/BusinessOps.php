<?php
// includes/BusinessOps.php — Reusable persistent JSON storage layer for
// per-business customer operations (bookings, orders, inquiries, ...).
// One source of truth: storage/collections/<slug>/<entity>.json with
// flock-guarded atomic read-modify-write + idempotency + row caps.
// Admin-visible mirror: operational records are upserted into the site's
// published admin data files (published/<slug>/admin/data_<entity>.json)
// so the admin dashboard manages REAL customer activity, never samples.
// Storage stays outside the public web root (storage/); the admin mirror
// lives with the published site and is reachable only via admin auth.

function bops_slug(array $r): string {
    $s = preg_replace('/[^a-z0-9_-]/i', '', (string)($r['slug'] ?? 'site'));
    return substr($s !== '' ? $s : 'site', 0, 60);
}
function bops_entity(array $r): string {
    $e = preg_replace('/[^a-z0-9_]/i', '', (string)($r['entity'] ?? ''));
    $e = substr($e, 0, 40);
    // ★ Singular AI ids (trip/bus/booking/…) resolve to the canonical plural
    // store (trips/buses/bookings/…) so reads/writes never split across two
    // files — admin-added rows are the same rows the frontend lists.
    if ($e !== '') {
        if (!function_exists('business_registry_canonical_entity')) {
            @require_once __DIR__ . '/BusinessRegistry.php';
        }
        if (function_exists('business_registry_canonical_entity')) {
            $e = business_registry_canonical_entity($e);
        }
    }
    return $e;
}
function bops_root(): string {
    return defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
}
function bops_dir(string $slug): string {
    $base = bops_root() . '/collections/' . $slug;
    if (!is_dir($base)) @mkdir($base, 0755, true);
    return $base;
}
function bops_file(string $slug, string $entity): string {
    return bops_dir($slug) . '/' . $entity . '.json';
}
/** Sites-store twin (Node writer). PHP reads it as union. */
function bops_sites_file(string $slug, string $entity): string {
    $slug = bops_slug(['slug' => $slug]);
    $entity = bops_entity(['entity' => $entity]);
    return bops_root() . '/sites/' . $slug . '/' . $entity . '.json';
}
/**
 * Convergent union of both stores (collections ← PHP writer, sites/ ← Node
 * writer), deduped by id with newer updated_at winning (tie → collections).
 * Either backend's rows stay visible to both backends' reads.
 */
function bops_all_rows(string $slug, string $entity): array {
    $byId = [];
    $order = [];
    foreach ([bops_file($slug, $entity), bops_sites_file($slug, $entity)] as $fi => $f) {
        if (!is_file($f)) continue;
        $j = json_decode(@file_get_contents($f), true);
        if (!is_array($j)) continue;
        foreach ($j as $r) {
            if (!is_array($r) || !isset($r['id'])) continue;
            $id = (string)$r['id'];
            if (!isset($byId[$id])) { $order[] = $id; $byId[$id] = $r; continue; }
            $oldTs = strtotime((string)($byId[$id]['updated_at'] ?? $byId[$id]['created_at'] ?? '')) ?: 0;
            $newTs = strtotime((string)($r['updated_at'] ?? $r['created_at'] ?? '')) ?: 0;
            // fi 0 = collections wins ties (system of record for PHP writes).
            if ($newTs > $oldTs || ($newTs === $oldTs && $fi === 0)) $byId[$id] = $r;
        }
    }
    $out = [];
    foreach ($order as $id) $out[] = $byId[$id];
    return $out;
}
function bops_load(string $slug, string $entity): array {
    $slug = bops_slug(['slug' => $slug]);
    $entity = bops_entity(['entity' => $entity]);
    $rows = bops_all_rows($slug, $entity);
    if ($entity !== 'trips' || $rows) return $rows;

    $adminFile = dirname(__DIR__) . '/published/' . $slug . '/admin/data_trips.json';
    $adminRows = json_decode((string)@file_get_contents($adminFile), true);
    if (!is_array($adminRows)) return [];
    $adminRows = array_values(array_filter($adminRows, function ($row) {
        return is_array($row) && !empty($row['id']);
    }));
    if (!$adminRows) return [];

    bops_transact($slug, $entity, function ($stored) use ($adminRows) {
        if ($stored) return [false, 'Trip records already exist'];
        return [$adminRows, $adminRows];
    });
    return bops_all_rows($slug, $entity);
}
/**
 * Merge sites-store rows into a local row set for CHECKS and adoption
 * (dedupe by id, newer updated_at wins, tie → local). The authoritative
 * write-back still targets the local store; cross-store propagation uses
 * bops_sync_sites().
 */
function bops_union_rows(array $local, string $slug, string $entity): array {
    $f = bops_sites_file($slug, $entity);
    if (!is_file($f)) return array_values($local);
    $j = json_decode(@file_get_contents($f), true);
    if (!is_array($j)) return array_values($local);
    $byId = [];
    foreach ($local as $r) {
        if (is_array($r) && isset($r['id'])) $byId[(string)$r['id']] = $r;
    }
    foreach ($j as $r) {
        if (!is_array($r) || !isset($r['id'])) continue;
        $id = (string)$r['id'];
        if (!isset($byId[$id])) { $byId[$id] = $r; continue; }
        $oldTs = strtotime((string)($byId[$id]['updated_at'] ?? $byId[$id]['created_at'] ?? '')) ?: 0;
        $newTs = strtotime((string)($r['updated_at'] ?? $r['created_at'] ?? '')) ?: 0;
        if ($newTs > $oldTs) $byId[$id] = $r;
    }
    return array_values($byId);
}
function bops_sync_sites(string $slug, string $entity, string $id, ?array $patch): void {
    try {
        if (is_array($patch)) { unset($patch['id'], $patch['created_at']); }
        $f = bops_sites_file($slug, $entity);
        if ($id === '' || !is_file($f)) return;
        $h = @fopen($f, 'c+');
        if (!$h) return;
        try {
            if (!flock($h, LOCK_EX)) return;
            $raw = stream_get_contents($h);
            $rows = ($raw !== false && trim((string)$raw) !== '') ? (json_decode($raw, true) ?: []) : [];
            if (!is_array($rows)) $rows = [];
            $rows = array_values($rows);
            $changed = false;
            if ($patch === null) {
                $before = count($rows);
                $rows = array_values(array_filter($rows,
                    fn($r) => (string)(is_array($r) ? ($r['id'] ?? '') : '') !== $id));
                $changed = count($rows) !== $before;
            } else {
                foreach ($rows as &$r) {
                    if (is_array($r) && (string)($r['id'] ?? '') === $id) {
                        $r = array_merge($r, $patch, ['updated_at' => date('c')]);
                        $changed = true;
                        break;
                    }
                }
                unset($r);
            }
            if ($changed) {
                ftruncate($h, 0);
                rewind($h);
                fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($h);
            }
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }
    } catch (Throwable $e) { /* best-effort only */ }
}
/**
 * Atomic read-modify-write under an exclusive lock.
 * $fn receives rows, returns [rows, result]. Lock held throughout,
 * so concurrent holds/creates cannot double-book the same seat.
 */
function bops_transact(string $slug, string $entity, callable $fn) {
    $f = bops_file($slug, $entity);
    $h = @fopen($f, 'c+');
    if (!$h) return [false, 'Storage unavailable'];
    try {
        if (!flock($h, LOCK_EX)) return [false, 'Storage busy — retry'];
        $raw = stream_get_contents($h);
        $rows = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        if (!is_array($rows)) $rows = [];
        $rows = array_values($rows);
        [$rows, $result] = $fn($rows);
        if ($rows === false) return [false, $result];
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($h);
        return [true, $result];
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}
function bops_is_admin(string $slug): bool {
    // Published admin session. Platform customer login alone is NOT enough
    // to mutate records (per-record ownership applies to customers instead).
    if (!empty($_SESSION['admin_auth'])) return true;
    return false;
}
function bops_customer_id(array $req): string {
    return strtolower((string)($_SESSION['customer_user']['email'] ?? $req['email'] ?? $req['phone'] ?? ''));
}
function bops_record_owner(array $r): string {
    return strtolower((string)($r['email'] ?? $r['phone'] ?? $r['customer'] ?? ''));
}
function bops_customer_ids(array $req): array {
    $sessionEmail = strtolower(trim((string)($_SESSION['customer_user']['email'] ?? '')));
    if ($sessionEmail !== '') return [$sessionEmail];

    $ids = [];
    foreach ([($req['email'] ?? ''), ($req['phone'] ?? '')] as $value) {
        $value = strtolower(trim((string)$value));
        if ($value !== '' && !in_array($value, $ids, true)) $ids[] = $value;
    }
    return $ids;
}
/**
 * Phone-number match tolerant of formatting/country codes:
 * compare digit strings; match when equal or one ends with the other
 * (min 7 digits). '+94 77 111 2222' matches '0771112222'.
 */
function bops_phone_match(string $a, string $b): bool {
    $da = preg_replace('/[^0-9]/', '', $a);
    $db = preg_replace('/[^0-9]/', '', $b);
    if (strlen($da) < 7 || strlen($db) < 7) return false;
    // Same subscriber, different format: country codes usually REPLACE the
    // leading trunk 0 ('+94 77…' ≡ '077…'), so compare the last 9 digits
    // when both sides are long enough.
    if (strlen($da) >= 9 && strlen($db) >= 9) {
        return substr($da, -9) === substr($db, -9);
    }
    return $da === $db || str_ends_with($da, $db) || str_ends_with($db, $da);
}
function bops_is_public_entity(string $entity): bool {
    if (!function_exists('business_registry_is_public')) {
        @require_once __DIR__ . '/BusinessRegistry.php';
    }
    if (function_exists('business_registry_is_public')) return business_registry_is_public($entity);
    return in_array($entity, ['trips', 'products'], true);
}
function bops_strip_secrets(array $r): array {
    // Strip credential/token keys only — never real business fields.
    // ('passenger'/'passport' are customer data and stay visible; 'pass',
    // 'password', 'pass_hash', tokens and hashes are removed.)
    foreach ($r as $k => $v) {
        $kl = strtolower((string)$k);
        if ($kl === 'pass' || str_ends_with($kl, '_pass') || str_starts_with($kl, 'pass_')
            || strpos($kl, 'password') !== false || strpos($kl, 'passwd') !== false
            || strpos($kl, 'pwd') !== false || strpos($kl, 'hash') !== false
            || strpos($kl, 'secret') !== false || strpos($kl, 'token') !== false
            || strpos($kl, 'otp') !== false) unset($r[$k]);
    }
    return $r;
}
// Public search filter: scalar query params (origin, date, …) match
// case-insensitive substrings; unknown/system keys ignored.
// Row-field matching is alias- AND substring-tolerant: admin-created fields
// like departurelocation/destinationlocation/departuredate still match the
// frontend's origin/destination/date filters (exact-name match would hide
// admin-added trips from search). Date-ish values compare by date parts so
// 14.10.2026 matches a 2026-10-14 filter.
function bops_date_parts(string $v): ?array {
    // Date-ish values compare by their numeric parts, format-free, so
    // an admin-typed 14.10.2026 still matches a 2026-10-14 search filter.
    if (!preg_match_all('/\d+/', $v, $m) || count($m[0]) < 3) return null;
    $nums = array_map('intval', array_slice($m[0], 0, 3));
    $hasYear = false;
    foreach ($nums as $n) { if ($n > 31 && $n < 2100) { $hasYear = true; break; } }
    if (!$hasYear) return null;
    sort($nums);
    return $nums;
}
function bops_values_match(string $rv, string $fv): bool {
    if (strpos($rv, $fv) !== false) return true;
    $rp = bops_date_parts($rv);
    $fp = bops_date_parts($fv);
    if ($rp !== null && $fp !== null && $rp === $fp) return true;
    return false;
}
function bops_public_filter(array $rows, array $req, int $cap = 100): array {
    $skip = ['action' => 1, 'slug' => 1, 'entity' => 1, 'email' => 1, 'phone' => 1,
        'limit' => 1, 'company' => 1, 'idempotency_key' => 1];
    // Domain synonyms: frontend search forms (origin/destination) must match
    // admin-managed schedule fields (route/departure/...) and vice versa —
    // otherwise admin-added trips never appear in frontend search results.
    $aliases = [
        'origin' => ['origin', 'route', 'departure', 'from', 'source'],
        'destination' => ['destination', 'route', 'arrival', 'to'],
        'route' => ['route', 'origin', 'destination', 'departure'],
    ];
    $filters = [];
    foreach ($req as $k => $v) {
        if (isset($skip[$k]) || !is_scalar($v)) continue;
        $v = trim((string)$v);
        if ($v !== '') $filters[strtolower((string)$k)] = strtolower($v);
    }
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $ok = true;
        foreach ($filters as $fk => $fv) {
            $keys = $aliases[$fk] ?? [$fk];
            $rv = null;
            foreach ($r as $rk => $val) {
                if (!is_scalar($val)) continue;
                $rkl = strtolower((string)$rk);
                $hit = in_array($rkl, $keys, true);
                if (!$hit) {
                    // Substring-tolerant: departurelocation≈departure,
                    // destinationlocation≈destination, departuredate≈date.
                    // Short tokens (to/from) stay exact-only to avoid
                    // over-matching (e.g. 'to' inside 'photo').
                    foreach ($keys as $kk) {
                        if (strlen($kk) < 3) continue;
                        $a = preg_replace('/[^a-z]/', '', $kk);
                        $b = preg_replace('/[^a-z]/', '', $rkl);
                        if ($a !== '' && $b !== '' && (strpos($b, $a) !== false || strpos($a, $b) !== false)) { $hit = true; break; }
                    }
                }
                if ($hit) $rv = ($rv ?? '') . ' ' . strtolower((string)$val);
            }
            if ($rv === null || !bops_values_match($rv, $fv)) { $ok = false; break; }
        }
        if ($ok) $out[] = bops_strip_secrets($r);
        if (count($out) >= $cap) break;
    }
    return array_values($out);
}
function bops_sanitize_record(array $rec): array {
    foreach ($rec as $k => $v) { $rec[$k] = is_string($v) ? trim(strip_tags($v)) : $v; }
    return $rec;
}
function bops_active_status(string $st): bool {
    return in_array($st, ['held', 'awaiting_payment', 'confirmed', 'pending', 'approved'], true);
}
function bops_hold_live(array $r): bool {
    if (($r['status'] ?? '') !== 'held') return true;
    $ca = strtotime($r['created_at'] ?? '');
    return !($ca && (time() - $ca > 900)); // expired holds release capacity
}
function bops_norm_seats($seats): array {
    if (is_string($seats)) {
        $t = trim($seats);
        // JSON array ("[\"1A\",\"2B\"]") preferred; plain "1A, 2B" / "1A 2B"
        // lists split too — a comma-joined string must never become ONE seat
        // (that would break conflict checks and ticket display).
        if ($t !== '' && ($t[0] === '[' || $t[0] === '{')) {
            $j = json_decode($t, true);
            if (is_array($j)) $seats = $j;
        }
        if (is_string($seats)) $seats = preg_split('/[\s,;|]+/', $t, -1, PREG_SPLIT_NO_EMPTY);
    }
    return array_values(array_unique(array_filter(array_map(
        fn($seat) => strtoupper(trim((string)$seat)),
        (array)$seats
    ))));
}
function bops_trip_capacity(string $slug, string $tripId): ?int {
    foreach (bops_load($slug, 'trips') as $trip) {
        if (!is_array($trip) || (string)($trip['id'] ?? '') !== $tripId) continue;
        foreach (['total_seats', 'capacity', 'seat_capacity'] as $key) {
            if (isset($trip[$key]) && is_numeric($trip[$key])) return max(0, (int)$trip[$key]);
        }
        return 40;
    }
    return null;
}
function bops_trip_by_id(string $slug, string $tripId): ?array {
    foreach (bops_load($slug, 'trips') as $trip) {
        if (is_array($trip) && (string)($trip['id'] ?? '') === $tripId) return $trip;
    }
    return null;
}
function bops_ensure_trip_for_booking(string $slug, array $booking): ?array {
    $tripId = trim((string)($booking['trip_id'] ?? ''));
    if ($tripId === '') return null;
    $existing = bops_trip_by_id($slug, $tripId);
    if ($existing) return $existing;

    $route = trim((string)($booking['route'] ?? ''));
    $origin = trim((string)($booking['origin'] ?? ''));
    $destination = trim((string)($booking['destination'] ?? ''));
    $price = is_numeric($booking['price'] ?? $booking['fare'] ?? null)
        ? (float)($booking['price'] ?? $booking['fare']) : 850.0;
    $capacity = is_numeric($booking['capacity'] ?? $booking['total_seats'] ?? $booking['seat_capacity'] ?? null)
        ? (int)($booking['capacity'] ?? $booking['total_seats'] ?? $booking['seat_capacity']) : 40;
    $trip = [
        'id' => $tripId,
        'route' => $route !== '' ? $route : ($origin !== '' || $destination !== '' ? trim($origin . ' → ' . $destination) : 'Demo Route'),
        'origin' => $origin !== '' ? $origin : 'Demo Origin',
        'destination' => $destination !== '' ? $destination : 'Demo Destination',
        'date' => trim((string)($booking['trip_date'] ?? $booking['date'] ?? date('Y-m-d'))),
        'departure' => trim((string)($booking['departure'] ?? '08:00')),
        'price' => round($price, 2),
        'total_seats' => max(1, $capacity),
        'seat_capacity' => max(1, $capacity),
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'demo' => true,
    ];

    [$ok, $stored] = bops_transact($slug, 'trips', function ($rows) use ($trip) {
        foreach ($rows as $r) {
            if (is_array($r) && (string)($r['id'] ?? '') === (string)$trip['id']) return [$rows, $r];
        }
        $rows[] = $trip;
        return [$rows, $trip];
    });
    if ($ok) {
        bops_mirror_admin($slug, 'trips', $stored);
        bops_sync_sites($slug, 'trips', (string)$trip['id'], $stored);
        return $stored;
    }
    return bops_trip_by_id($slug, $tripId);
}
function bops_booking_amount(string $slug, array $booking): float {
    if (isset($booking['amount']) && is_numeric($booking['amount'])) return round((float)$booking['amount'], 2);
    $trip = bops_trip_by_id($slug, (string)($booking['trip_id'] ?? ''));
    $fare = is_array($trip) && is_numeric($trip['price'] ?? $trip['fare'] ?? null)
        ? (float)($trip['price'] ?? $trip['fare']) : 0.0;
    return round($fare * max(1, count(bops_norm_seats($booking['seats'] ?? []))), 2);
}
function bops_queue_booking_status_email(string $slug, array $booking): array {
    $email = strtolower(trim((string)($booking['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'status' => 'not_sent', 'error' => 'Booking has no valid customer email'];
    }
    require_once __DIR__ . '/MailQueue.php';
    $status = strtolower((string)($booking['status'] ?? ''));
    $approved = $status === 'approved';
    $subject = $approved ? 'Your bus booking is approved' : 'Update on your bus booking';
    $message = $approved
        ? 'Your booking ' . (string)($booking['ref'] ?? '') . ' has been approved.'
        : 'Your booking ' . (string)($booking['ref'] ?? '') . ' was rejected. Please contact the bus operator if you need help.';
    $route = trim((string)($booking['route'] ?? ''));
    if ($route !== '') $message .= ' Route: ' . $route . '.';
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#17212b">'
        . '<h2>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2><p>' . $safeMessage . '</p>'
        . '<p>Booking reference: <strong>' . htmlspecialchars((string)($booking['ref'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '</body></html>';
    $queued = queueMail([
        'to' => $email,
        'subject' => $subject,
        'html' => $html,
        'text' => $message,
        'kind' => 'bus_booking_status',
    ]);
    return !empty($queued['success'])
        ? ['success' => true, 'status' => 'queued', 'queue_id' => $queued['id'] ?? '']
        : ['success' => false, 'status' => 'failed', 'error' => (string)($queued['error'] ?? 'Mail queue failed')];
}
function bops_seat_fits_capacity(string $seat, int $capacity): bool {
    $seat = strtoupper(trim($seat));
    if (!preg_match('/^([A-Z])([1-4])$/', $seat, $m)) return false;
    // Standard fleet is A–J × 1–4 (40 seats). Larger buses extend the row
    // range so every configured seat stays addressable: rows needed =
    // ceil(capacity / 4), capped at A–Z. Small-bus behaviour is unchanged
    // (Z1 on a 40-seat bus is still invalid).
    $rowsNeeded = (int)ceil(max(1, $capacity) / 4);
    if ($rowsNeeded < 10) $rowsNeeded = 10;
    if ($rowsNeeded > 26) $rowsNeeded = 26;
    $maxRow = chr(ord('A') + $rowsNeeded - 1);
    if ($m[1] < 'A' || $m[1] > $maxRow) return false;
    $index = (ord($m[1]) - ord('A')) * 4 + (int)$m[2];
    return $index <= $capacity;
}
function bops_booking_trip_id(array $rec): string {
    foreach (['trip_id', 'tripId', 'tripID', 'trip', 'booking_trip', 'trip_ref'] as $key) {
        if (isset($rec[$key]) && trim((string)$rec[$key]) !== '') return trim((string)$rec[$key]);
    }
    return '';
}
function bops_booking_seats($rec): array {
    if (!is_array($rec)) $rec = ['seats' => $rec];
    foreach (['seats', 'seat_numbers', 'seatNumbers', 'seat', 'seats_string', 'selected_seats'] as $key) {
        if (array_key_exists($key, $rec) && $rec[$key] !== '' && $rec[$key] !== null) {
            $norm = bops_norm_seats($rec[$key]);
            if (!empty($norm)) return $norm;
        }
    }
    return [];
}
function bops_booking_ref(array $rec): string {
    foreach (['ref', 'booking_ref', 'ticket_ref', 'ticketRef', 'bookingRef', 'confirmation_code'] as $key) {
        if (isset($rec[$key]) && trim((string)$rec[$key]) !== '') return strtoupper(trim((string)$rec[$key]));
    }
    return strtoupper(trim((string)($rec['id'] ?? '')));
}
function bops_normalize_booking_aliases(array $rec): array {
    $tripId = bops_booking_trip_id($rec);
    if ($tripId !== '') $rec['trip_id'] = $tripId;
    $seats = bops_booking_seats($rec);
    if (!empty($seats)) $rec['seats'] = $seats;
    foreach (['passenger' => ['passenger', 'passenger_name', 'passengerName', 'customer', 'name', 'full_name'],
              'phone' => ['phone', 'phone_number', 'phoneNumber', 'mobile', 'tel'],
              'email' => ['email', 'email_address', 'emailAddress', 'customer_email']] as $canonical => $aliases) {
        if (isset($rec[$canonical]) && trim((string)$rec[$canonical]) !== '') continue;
        foreach ($aliases as $alias) {
            if (isset($rec[$alias]) && trim((string)$rec[$alias]) !== '') { $rec[$canonical] = $rec[$alias]; break; }
        }
    }
    if (empty($rec['ref'])) {
        foreach (['booking_ref', 'ticket_ref', 'ticketRef', 'bookingRef'] as $alias) {
            if (!empty($rec[$alias])) { $rec['ref'] = strtoupper((string)$rec[$alias]); break; }
        }
    }
    return $rec;
}
// Backward-compatible display view: legacy records stored under camelCase or
// alternate keys (tripId, seat_numbers, booking_ref, …) resolve to the same
// canonical fields so admin tables and dashboards never show "—" for data
// that is actually present. Never invents values; truly missing stays ''.
function bops_booking_view(array $rec): array {
    $rec = bops_normalize_booking_aliases($rec);
    if (!isset($rec['seats']) || !is_array($rec['seats'])) $rec['seats'] = bops_booking_seats($rec);
    if (empty($rec['trip_id'])) $rec['trip_id'] = bops_booking_trip_id($rec);
    if (empty($rec['ref'])) $rec['ref'] = bops_booking_ref($rec);
    return $rec;
}
const BOPS_MIRROR_ENTITIES = ['bookings', 'orders', 'appointments', 'inquiries', 'subscribers', 'payments',
    'products', 'customers', 'coupons', 'menu_items', 'reservations', 'rooms', 'services', 'staff',
    'doctors', 'patients', 'properties', 'viewings', 'courses', 'enrollments', 'tutors',
    'packages', 'departures', 'events', 'tickets', 'units', 'rentals', 'vehicles', 'service_jobs',
    'plans', 'projects', 'buses', 'routes', 'trips'];

// Registry-driven validation (lenient v1): status allowlists + conflict guards
// across ALL business categories. Required-field enforcement stays soft for
// backward compatibility with pre-registry records.
function bops_registry_check(string $entity, array $rec, array $rows) {
    if (!function_exists('business_registry_find_entity')) {
        @require_once __DIR__ . '/BusinessRegistry.php';
    }
    if (!function_exists('business_registry_find_entity')) return '';
    $def = business_registry_find_entity($entity);
    if (!$def) return '';
    $allowedStatus = [];
    foreach (($def['fields'] ?? []) as $f) {
        if (($f['name'] ?? '') === 'status' && !empty($f['options'])) $allowedStatus = $f['options'];
    }
    if ($allowedStatus && isset($rec['status']) && $rec['status'] !== '' && !in_array($rec['status'], $allowedStatus, true)) {
        return 'Invalid status for ' . $entity;
    }
    // Conflict guards per entity shape (only when identifying fields present).
    $same = function ($a, $b, $keys) {
        foreach ($keys as $k) { if (strtolower(trim((string)($a[$k] ?? ''))) !== strtolower(trim((string)($b[$k] ?? '')))) return false; }
        return true;
    };
    $active = fn($r) => !in_array(($r['status'] ?? ''), ['cancelled', 'expired', 'returned', 'checked_out'], true);
    if ($entity === 'appointments' && isset($rec['date'], $rec['time'])) {
        $keys = isset($rec['doctor']) ? ['doctor', 'date', 'time'] : (isset($rec['service']) ? ['service', 'date', 'time'] : ['date', 'time']);
        foreach ($rows as $r) {
            if (!is_array($r) || !$active($r)) continue;
            if ($same($r, $rec, $keys)) return 'Slot already booked';
        }
    }
    if (in_array($entity, ['reservations', 'viewings'], true) && isset($rec['date'])) {
        $keys = isset($rec['room']) ? ['room', 'date'] : (isset($rec['property']) ? ['property', 'date', 'time'] : null);
        if ($keys) foreach ($rows as $r) {
            if (!is_array($r) || !$active($r)) continue;
            if ($same($r, $rec, $keys)) {
                if (isset($rec['time'], $r['time']) && strtolower((string)$r['time']) !== strtolower((string)$rec['time'])) continue;
                return 'Slot already reserved';
            }
        }
    }
    if ($entity === 'rentals' && isset($rec['unit'], $rec['from'], $rec['to'])) {
        foreach ($rows as $r) {
            if (!is_array($r) || ($r['unit'] ?? '') !== $rec['unit'] || !$active($r)) continue;
            if (($rec['from'] ?? '') <= ($r['to'] ?? '') && ($r['from'] ?? '') <= ($rec['to'] ?? '')) return 'Unit unavailable for these dates';
        }
    }
    if ($entity === 'enrollments' && isset($rec['student'], $rec['course'])) {
        foreach ($rows as $r) {
            if (!is_array($r) || !$active($r)) continue;
            if ($same($r, $rec, ['student', 'course'])) return 'Already enrolled';
        }
    }
    if ($entity === 'subscribers' && isset($rec['email'], $rec['plan'])) {
        foreach ($rows as $r) {
            if (!is_array($r) || ($r['status'] ?? '') !== 'active') continue;
            if ($same($r, $rec, ['email', 'plan'])) return 'Subscription already active';
        }
    }
    if ($entity === 'products' && isset($rec['price']) && (float)$rec['price'] < 0) return 'Invalid price';
    return '';
}

/**
 * Full action dispatcher — the SAME code path as the HTTP endpoint.
 * Returns ['http' => int, 'body' => array]. No exit(), fully testable.
 */
function bops_dispatch(array $req): array {
    $action = strtolower((string)($req['action'] ?? ''));
    $slug = bops_slug($req);
    $entity = bops_entity($req);
    if ($action === '') return ['http' => 400, 'body' => ['success' => false, 'error' => 'Missing action']];
    if ($entity === '' && !in_array($action, ['pay'], true)) {
        return ['http' => 400, 'body' => ['success' => false, 'error' => 'Missing entity']];
    }

    if ($action === 'list') {
        $rows = bops_load($slug, $entity);
        // Public catalogs (trips, products, rooms…): anyone may search them
        // with optional field filters — this is how customer-facing search
        // reads real schedules/inventory. Secrets are stripped regardless.
        if (!bops_is_admin($slug) && bops_is_public_entity($entity)) {
            $rows = bops_public_filter($rows, $req);
            return ['http' => 200, 'body' => ['success' => true, 'data' => array_values($rows)]];
        }
        // Privacy: non-admins see only own records (owner must match).
        // Ownerless rows are admin-visible only — never leaked across customers.
        if (!bops_is_admin($slug)) {
            $sessionEmail = strtolower(trim((string)($_SESSION['customer_user']['email'] ?? '')));
            $identities = ($entity === 'bookings' && $sessionEmail === '')
                ? [] : bops_customer_ids($req);
            if ($identities) {
                $rows = array_values(array_filter($rows, function ($r) use ($identities) {
                    if (!is_array($r)) return false;
                    foreach (['email', 'phone', 'customer'] as $key) {
                        $owner = strtolower(trim((string)($r[$key] ?? '')));
                        if ($owner === '') continue;
                        foreach ($identities as $identity) {
                            if ($owner === $identity) return true;
                            if (strpos($owner, '@') === false && strpos($identity, '@') === false
                                && bops_phone_match($owner, $identity)) return true;
                        }
                    }
                    return false;
                }));
            } else { $rows = []; }
        } else {
            $rows = bops_public_filter($rows, $req, 500);
        }
        return ['http' => 200, 'body' => ['success' => true, 'data' => array_values($rows)]];
    }

    if ($action === 'availability') {
        $tripId = trim((string)($req['trip_id'] ?? $req['tripId'] ?? ''));
        if ($tripId === '') return ['http' => 400, 'body' => ['success' => false, 'error' => 'Missing trip_id']];
        $trip = null;
        foreach (bops_load($slug, 'trips') as $row) {
            if (is_array($row) && (string)($row['id'] ?? '') === $tripId) { $trip = $row; break; }
        }
        if (!$trip) return ['http' => 404, 'body' => ['success' => false, 'error' => 'Trip not found']];
        $capacity = bops_trip_capacity($slug, $tripId) ?? 0;
        $reserved = [];
        $heldSeats = [];
        $bookedSeats = [];
        foreach (bops_load($slug, 'bookings') as $booking) {
            if (!is_array($booking)) continue;
            $booking = bops_booking_view($booking);
            if ((string)($booking['trip_id'] ?? '') !== $tripId
                || !bops_active_status((string)($booking['status'] ?? '')) || !bops_hold_live($booking)) continue;
            $seats = bops_norm_seats($booking['seats'] ?? []);
            $reserved = array_merge($reserved, $seats);
            if (($booking['status'] ?? '') === 'held') $heldSeats = array_merge($heldSeats, $seats);
            else $bookedSeats = array_merge($bookedSeats, $seats);
        }
        return ['http' => 200, 'body' => ['success' => true, 'data' => [
            'trip_id' => $tripId,
            'total_seats' => $capacity,
            'reserved_seats' => array_values(array_unique($reserved)),
            'held_seats' => array_values(array_unique($heldSeats)),
            'booked_seats' => array_values(array_unique($bookedSeats)),
        ]]];
    }

    if ($action === 'release_hold') {
        $ref = strtoupper(trim((string)($req['ref'] ?? '')));
        if ($ref === '') return ['http' => 400, 'body' => ['success' => false, 'error' => 'Missing hold ref']];
        [$ok, $out] = bops_transact($slug, 'bookings', function ($rows) use ($ref) {
            foreach ($rows as $i => $row) {
                if (!is_array($row) || strtoupper((string)($row['ref'] ?? '')) !== $ref) continue;
                if (($row['status'] ?? '') !== 'held') return [false, 'Hold is no longer active'];
                array_splice($rows, $i, 1);
                return [$rows, $row];
            }
            return [false, 'Hold not found'];
        });
        if (!$ok) return ['http' => 400, 'body' => ['success' => false, 'error' => (string)$out]];
        $holdId = (string)($out['id'] ?? '');
        if ($holdId !== '') {
            bops_sync_sites($slug, 'bookings', $holdId, null);
            bops_mirror_admin($slug, 'bookings', ['id' => $holdId], true);
        }
        return ['http' => 200, 'body' => ['success' => true, 'data' => ['released' => true]]];
    }

    if ($action === 'create') {
        $rec = $req['record'] ?? [];
        if (!is_array($rec) || empty($rec)) return ['http' => 400, 'body' => ['success' => false, 'error' => 'Empty record']];
        $rec = bops_sanitize_record($rec);
        if ($entity === 'bookings') $rec = bops_normalize_booking_aliases($rec);
        if (isset($rec['email']) && $rec['email'] !== '' && !filter_var($rec['email'], FILTER_VALIDATE_EMAIL)) {
            return ['http' => 400, 'body' => ['success' => false, 'error' => 'Invalid email']];
        }
        if ($entity === 'bookings' && !filter_var((string)($rec['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return ['http' => 400, 'body' => ['success' => false, 'error' => 'A valid customer email is required for bus bookings']];
        }
        if (!empty($req['company'])) return ['http' => 403, 'body' => ['success' => false, 'error' => 'Spam detected']];
        unset($rec['company']); // honeypot residue is never business data
        $idem = (string)($req['idempotency_key'] ?? $rec['idempotency_key'] ?? '');
        $tripRow = $entity === 'bookings' ? bops_ensure_trip_for_booking($slug, $rec) : null;
        if ($entity === 'bookings' && !$tripRow) {
            return ['http' => 400, 'body' => ['success' => false, 'error' => 'Trip not found. Search and select a live trip first.']];
        }
        $capacity = $entity === 'bookings' ? bops_trip_capacity($slug, (string)($rec['trip_id'] ?? '')) : null;
        if ($entity === 'bookings' && $capacity !== null) {
            if ($capacity < 1) return ['http' => 400, 'body' => ['success' => false, 'error' => 'Trip has no seat capacity']];
            foreach (bops_norm_seats($rec['seats'] ?? []) as $seat) {
                if (!bops_seat_fits_capacity($seat, $capacity)) {
                    return ['http' => 400, 'body' => ['success' => false, 'error' => 'Invalid seat for this trip: ' . $seat]];
                }
            }
        }
        [$ok, $out] = bops_transact($slug, $entity, function ($rows) use ($rec, $entity, $idem, $slug, $tripRow) {
            $check = bops_union_rows($rows, $slug, $entity);
            if ($idem !== '') {
                foreach ($check as $r) {
                    if (is_array($r) && ($r['idempotency_key'] ?? '') === $idem) return [$rows, ['rec' => $r, 'deduped' => true]];
                }
            }
            $rec['id'] = $rec['id'] ?? ($entity === 'trips'
                ? 'TRIP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))
                : ($entity === 'bookings' ? 'BKG-' : 'r_') . strtoupper(bin2hex(random_bytes(6))));
            $rec['created_at'] = date('c');
            $rec['updated_at'] = date('c');
            if ($idem !== '') $rec['idempotency_key'] = $idem;
            // Universal registry guard (all industries): status allowlist +
            // slot/date conflicts. Runs before entity-specific rules.
            $regErr = bops_registry_check($entity, $rec, $check);
            if ($regErr !== '') return [false, $regErr];
            if ($entity === 'bookings') {
                $trip = (string)($rec['trip_id'] ?? '');
                $seats = bops_norm_seats($rec['seats'] ?? []);
                if (empty($seats)) return [false, 'Select at least one seat'];
                if ($trip === '') return [false, 'Missing trip'];
                // Hold → confirm conversion: the customer's own live hold
                // (same trip, covers every booked seat, proven by hold_ref)
                // must NOT block its own confirmation. It is consumed below.
                $holdRef = (string)($rec['hold_ref'] ?? '');
                $consumeHoldId = '';
                foreach ($check as $r) {
                    if (!is_array($r) || ($r['trip_id'] ?? '') !== $trip) continue;
                    if (!bops_active_status($r['status'] ?? '') || !bops_hold_live($r)) continue;
                    if (($r['status'] ?? '') === 'held' && $holdRef !== '' && ($r['ref'] ?? '') === $holdRef) {
                        $covered = true;
                        $heldSeats = bops_norm_seats($r['seats'] ?? []);
                        foreach ($seats as $s) { if (!in_array($s, $heldSeats, true)) { $covered = false; break; } }
                        if ($covered) { $consumeHoldId = (string)($r['id'] ?? ''); continue; }
                    }
                    foreach ($seats as $s) { if (in_array($s, bops_norm_seats($r['seats'] ?? []), true)) return [false, 'Seat ' . $s . ' already reserved']; }
                }
                if ($consumeHoldId !== '') {
                    $rows = array_values(array_filter($rows,
                        fn($r) => (string)(is_array($r) ? ($r['id'] ?? '') : '') !== $consumeHoldId));
                    // Purge the twin copy too (same lock scope, different file).
                    bops_sync_sites($slug, $entity, $consumeHoldId, null);
                }
                $rec['seats'] = $seats;
                if (empty($rec['ref'])) $rec['ref'] = 'BK-' . strtoupper(substr(md5($rec['id']), 0, 8));
                if (empty($rec['verify_token'])) $rec['verify_token'] = bin2hex(random_bytes(16));
                $rec['route'] = (string)($tripRow['route'] ?? trim((string)($tripRow['origin'] ?? '') . ' → ' . (string)($tripRow['destination'] ?? '')));
                $rec['trip_date'] = (string)($tripRow['date'] ?? $tripRow['departure_date'] ?? '');
                $rec['departure'] = (string)($tripRow['departure'] ?? $tripRow['departure_time'] ?? '');
                $fare = is_numeric($tripRow['price'] ?? $tripRow['fare'] ?? null)
                    ? (float)($tripRow['price'] ?? $tripRow['fare']) : 0.0;
                $rec['price_per_seat'] = round($fare, 2);
                $rec['amount'] = round($fare * count($seats), 2);
                $rec['currency'] = (string)($tripRow['currency'] ?? 'LKR');
                $rec['status'] = 'pending';
                if (empty($rec['payment_status'])) $rec['payment_status'] = 'unpaid';
            }
            if ($entity === 'orders') {
                $items = $rec['items'] ?? [];
                if (is_string($items)) $items = json_decode($items, true) ?: [];
                if (!is_array($items) || empty($items)) return [false, 'Order has no items'];
                $rec['items'] = array_values($items);
                $rec['total'] = round((float)($rec['total'] ?? 0), 2);
                if (empty($rec['ref'])) $rec['ref'] = 'ORD-' . strtoupper(substr(md5($rec['id']), 0, 8));
                if (empty($rec['status'])) $rec['status'] = 'pending';
                if (empty($rec['payment_status'])) $rec['payment_status'] = 'unpaid';
            }
            $rows[] = $rec;
            if (count($rows) > 2000) $rows = array_slice($rows, -2000);
            return [$rows, ['rec' => $rec, 'deduped' => false, 'consumed_hold_id' => $consumeHoldId ?? '']];
        });
        if (!$ok) return ['http' => 400, 'body' => ['success' => false, 'error' => (string)$out]];
        if (!empty($out['consumed_hold_id'])) {
            $holdId = (string)$out['consumed_hold_id'];
            bops_sync_sites($slug, $entity, $holdId, null);
            bops_mirror_admin($slug, $entity, ['id' => $holdId], true);
        }
        if (in_array($entity, BOPS_MIRROR_ENTITIES, true)) bops_mirror_admin($slug, $entity, $out['rec']);
        $body = ['success' => true, 'data' => $out['rec']];
        if ($out['deduped']) $body['deduped'] = true;
        return ['http' => 200, 'body' => $body];
    }

    if ($action === 'hold') {
        $trip = (string)($req['trip_id'] ?? '');
        $seats = bops_norm_seats($req['seats'] ?? []);
        if ($trip === '' || empty($seats)) return ['http' => 400, 'body' => ['success' => false, 'error' => 'trip_id + seats required']];
        $capacity = bops_trip_capacity($slug, $trip);
        if ($capacity !== null) {
            if ($capacity < 1) return ['http' => 400, 'body' => ['success' => false, 'error' => 'Trip has no seat capacity']];
            foreach ($seats as $seat) {
                if (!bops_seat_fits_capacity($seat, $capacity)) {
                    return ['http' => 400, 'body' => ['success' => false, 'error' => 'Invalid seat for this trip: ' . $seat]];
                }
            }
        }
        [$ok, $out] = bops_transact($slug, $entity, function ($rows) use ($trip, $seats, $slug, $entity) {
            foreach (bops_union_rows($rows, $slug, $entity) as $r) {
                if (!is_array($r) || ($r['trip_id'] ?? '') !== $trip) continue;
                if (!bops_active_status($r['status'] ?? '') || !bops_hold_live($r)) continue;
                foreach ($seats as $s) { if (in_array($s, bops_norm_seats($r['seats'] ?? []), true)) return [false, 'Seat ' . $s . ' unavailable']; }
            }
            $rec = ['id' => 'h_' . bin2hex(random_bytes(8)), 'trip_id' => $trip, 'seats' => $seats,
                'status' => 'held', 'payment_status' => 'unpaid', 'ref' => 'HOLD-' . strtoupper(bin2hex(random_bytes(16))),
                'created_at' => date('c'), 'updated_at' => date('c'), 'expires_at' => date('c', time() + 900)];
            $rows[] = $rec;
            return [$rows, $rec];
        });
        if (!$ok) return ['http' => 400, 'body' => ['success' => false, 'error' => (string)$out]];
        bops_mirror_admin($slug, $entity, $out);
        return ['http' => 200, 'body' => ['success' => true, 'data' => $out]];
    }

    if ($action === 'update') {
        if (!bops_is_admin($slug)) return ['http' => 403, 'body' => ['success' => false, 'error' => 'Admin only']];
        $id = (string)($req['id'] ?? '');
        if ($id === '') return ['http' => 400, 'body' => ['success' => false, 'error' => 'Missing id']];
        $patch = is_array($req['patch'] ?? null) ? $req['patch'] : (is_array($req['record'] ?? null) ? $req['record'] : []);
        $patch = bops_sanitize_record($patch);
        unset($patch['id']);
        if ($entity === 'bookings' && isset($patch['status'])
            && !in_array(strtolower((string)$patch['status']), [
                'pending', 'approved', 'rejected', 'cancelled',
                'held', 'awaiting_payment', 'confirmed', 'expired',
            ], true)) {
            return ['http' => 400, 'body' => ['success' => false, 'error' => 'Booking status must be pending, approved, rejected, or cancelled']];
        }
        $previousStatus = '';
        [$ok, $out] = bops_transact($slug, $entity, function ($rows) use ($id, $patch, $slug, $entity, &$previousStatus) {
            $foundIdx = -1;
            foreach ($rows as $i => $r) {
                if (is_array($r) && (string)($r['id'] ?? '') === $id) { $foundIdx = $i; break; }
            }
            if ($foundIdx < 0) {
                // Adopt a twin-store row so admin updates reach Node rows.
                foreach (bops_union_rows($rows, $slug, $entity) as $u) {
                    if (is_array($u) && (string)($u['id'] ?? '') === $id) {
                        $rows[] = $u;
                        $foundIdx = count($rows) - 1;
                        break;
                    }
                }
                if ($foundIdx < 0) return [false, 'Not found'];
            }
            $r = $rows[$foundIdx];
            if (($r['status'] ?? '') === 'cancelled' && ($patch['status'] ?? 'cancelled') !== 'cancelled') {
                return [false, 'Cancelled records cannot be reactivated'];
            }
            $previousStatus = strtolower((string)($r['status'] ?? 'pending'));
            $nextStatus = strtolower((string)($patch['status'] ?? $previousStatus));
            if ($entity === 'bookings' && $nextStatus !== $previousStatus) {
                $history = is_array($r['status_history'] ?? null) ? $r['status_history'] : [];
                $history[] = ['from' => $previousStatus, 'to' => $nextStatus, 'at' => date('c')];
                $patch['status_history'] = $history;
                if ($nextStatus === 'approved') $patch['approved_at'] = date('c');
                if ($nextStatus === 'rejected') $patch['rejected_at'] = date('c');
            }
            $rows[$foundIdx] = array_merge($r, $patch, ['updated_at' => date('c')]);
            return [$rows, $rows[$foundIdx]];
        });
        if (!$ok) return ['http' => ($out === 'Not found' ? 404 : 400), 'body' => ['success' => false, 'error' => (string)$out]];
        $notification = null;
        if ($entity === 'bookings' && in_array((string)($out['status'] ?? ''), ['approved', 'rejected'], true)
            && $previousStatus !== (string)$out['status']) {
            $notification = bops_queue_booking_status_email($slug, $out);
            $out['notification_status'] = $notification['status'];
            $out['notification_error'] = $notification['error'] ?? '';
            $out['notification_queue_id'] = $notification['queue_id'] ?? '';
            [$persisted, $saved] = bops_transact($slug, $entity, function ($rows) use ($out) {
                foreach ($rows as $i => $row) {
                    if (is_array($row) && (string)($row['id'] ?? '') === (string)$out['id']) {
                        $rows[$i] = array_merge($row, [
                            'notification_status' => $out['notification_status'],
                            'notification_error' => $out['notification_error'],
                            'notification_queue_id' => $out['notification_queue_id'],
                        ]);
                        return [$rows, $rows[$i]];
                    }
                }
                return [false, 'Booking disappeared while saving notification status'];
            });
            if (!$persisted) {
                return ['http' => 500, 'body' => ['success' => false, 'error' => (string)$saved]];
            }
            $out = $saved;
        }
        bops_mirror_admin($slug, $entity, $out);
        bops_sync_sites($slug, $entity, (string)($out['id'] ?? ''), is_array($out) ? $out : []);
        return ['http' => 200, 'body' => ['success' => true, 'data' => $out, 'notification' => $notification]];
    }

    if ($action === 'cancel') {
        $id = (string)($req['id'] ?? '');
        if ($id === '' || $entity === '') return ['http' => 400, 'body' => ['success' => false, 'error' => 'entity + id required']];
        $isAdmin = bops_is_admin($slug);
        $me = bops_customer_id($req);
        [$ok, $out] = bops_transact($slug, $entity, function ($rows) use ($id, $isAdmin, $me, $slug, $entity, $req) {
            $matchIdx = -1;
            foreach ($rows as $i => $r) {
                if (!is_array($r)) continue;
                // Accept the internal id OR the customer-facing ref (tickets,
                // QR codes and lookup flows only carry the ref).
                if ((string)($r['id'] ?? '') !== $id && strtoupper((string)($r['ref'] ?? '')) !== strtoupper($id)) continue;
                $matchIdx = $i;
                break;
            }
            if ($matchIdx < 0) {
                // Adopt a twin-store row so customer cancels reach Node rows.
                foreach (bops_union_rows($rows, $slug, $entity) as $u) {
                    if (!is_array($u)) continue;
                    if ((string)($u['id'] ?? '') !== $id && strtoupper((string)($u['ref'] ?? '')) !== strtoupper($id)) continue;
                    $rows[] = $u;
                    $matchIdx = count($rows) - 1;
                    break;
                }
                if ($matchIdx < 0) return [false, 'Not found'];
            }
            $r = $rows[$matchIdx];
            if (!$isAdmin) {
                // Ticket flows prove ownership with phone AND/OR email (the
                // lookup already required ref + contact), so accept any of the
                // visitor's proven identities — email alone must not block a
                // phone-verified customer from cancelling their own ticket.
                $owner = bops_record_owner($r);
                $candidates = [];
                foreach ([($_SESSION['customer_user']['email'] ?? ''), ($req['email'] ?? ''), ($req['phone'] ?? '')] as $c) {
                    $c = strtolower(trim((string)$c));
                    if ($c !== '') $candidates[] = $c;
                }
                $okOwner = false;
                foreach ($candidates as $c) {
                    if ($owner === '' || $c === $owner || bops_phone_match($c, $owner)) { $okOwner = true; break; }
                }
                if (!$okOwner) {
                    // Ticket-context proof: ref + matching contact channel (the
                    // same proof the ticket lookup requires). A phone-verified
                    // visitor may cancel even when the record owner field
                    // holds their email instead of the phone.
                    $rp = strtolower(trim((string)($req['phone'] ?? '')));
                    $re = strtolower(trim((string)($req['email'] ?? '')));
                    if ($rp !== '' && isset($r['phone']) && bops_phone_match((string)$r['phone'], $rp)) $okOwner = true;
                    if (!$okOwner && $re !== '' && strtolower((string)($r['email'] ?? '')) === $re) $okOwner = true;
                }
                if (!$okOwner) return [false, 'Not your record'];
            }
            if (in_array(($r['status'] ?? ''), ['cancelled', 'expired'], true)) return [false, 'Already ' . $r['status']];
            $r['status'] = 'cancelled';
            $r['cancelled_at'] = date('c');
            $r['updated_at'] = date('c');
            if (($r['payment_status'] ?? '') === 'paid') $r['payment_status'] = 'refund_pending';
            $rows[$matchIdx] = $r;
            return [$rows, $r];
        });
        if (!$ok) {
            $code = ($out === 'Not your record') ? 403 : (($out === 'Not found') ? 404 : 400);
            return ['http' => $code, 'body' => ['success' => false, 'error' => (string)$out]];
        }
        bops_mirror_admin($slug, $entity, $out);
        bops_sync_sites($slug, $entity, (string)($out['id'] ?? ''), is_array($out) ? $out : []);
        return ['http' => 200, 'body' => ['success' => true, 'data' => $out, 'message' => 'Cancelled; capacity released']];
    }

    if ($action === 'payment_quote') {
        if (!bops_is_admin($slug)) return ['http' => 403, 'body' => ['success' => false, 'error' => 'Admin only']];
        $ref = strtoupper(trim((string)($req['ref'] ?? '')));
        if ($ref === '') return ['http' => 400, 'body' => ['success' => false, 'error' => 'Ticket reference is required']];
        foreach (bops_load($slug, 'bookings') as $booking) {
            if (!is_array($booking) || strtoupper((string)($booking['ref'] ?? '')) !== $ref) continue;
            if (in_array((string)($booking['status'] ?? ''), ['rejected', 'cancelled'], true)) {
                return ['http' => 400, 'body' => ['success' => false, 'error' => 'Rejected or cancelled bookings cannot be paid']];
            }
            if (($booking['payment_status'] ?? '') === 'paid') {
                return ['http' => 400, 'body' => ['success' => false, 'error' => 'This booking is already paid', 'data' => [
                    'ref' => $ref, 'payment_id' => $booking['payment_id'] ?? '',
                ]]];
            }
            return ['http' => 200, 'body' => ['success' => true, 'data' => [
                'ref' => $ref,
                'passenger' => $booking['passenger'] ?? $booking['customer'] ?? '',
                'amount' => bops_booking_amount($slug, $booking),
                'currency' => $booking['currency'] ?? 'LKR',
                'status' => $booking['status'] ?? 'pending',
                'payment_status' => $booking['payment_status'] ?? 'unpaid',
            ]]];
        }
        return ['http' => 404, 'body' => ['success' => false, 'error' => 'Ticket reference not found']];
    }

    if ($action === 'payment_intent') {
        $ref = strtoupper(trim((string)($req['ref'] ?? '')));
        $method = strtolower(trim((string)($req['method'] ?? '')));
        $email = strtolower(trim((string)($req['email'] ?? '')));
        $phone = trim((string)($req['phone'] ?? ''));
        if ($ref === '' || !in_array($method, ['counter', 'online'], true)) {
            return ['http' => 400, 'body' => ['success' => false, 'error' => 'Ticket reference and a valid payment method are required']];
        }
        if ($email === '' && $phone === '') {
            return ['http' => 400, 'body' => ['success' => false, 'error' => 'Enter the email or phone number used for this booking']];
        }

        $sessionEmail = strtolower(trim((string)($_SESSION['customer_user']['email'] ?? '')));
        [$ok, $out] = bops_transact($slug, 'bookings', function ($rows) use ($ref, $method, $email, $phone, $sessionEmail, $slug) {
            $idx = -1;
            foreach ($rows as $i => $booking) {
                if (!is_array($booking) || strtoupper((string)($booking['ref'] ?? '')) !== $ref) continue;
                $idx = $i;
                break;
            }
            if ($idx < 0) {
                foreach (bops_union_rows($rows, $slug, 'bookings') as $booking) {
                    if (!is_array($booking) || strtoupper((string)($booking['ref'] ?? '')) !== $ref) continue;
                    $rows[] = $booking;
                    $idx = count($rows) - 1;
                    break;
                }
            }
            if ($idx < 0) return [false, 'Ticket reference not found'];

            $booking = $rows[$idx];
            $bookingEmail = strtolower(trim((string)($booking['email'] ?? '')));
            $bookingPhone = trim((string)($booking['phone'] ?? ''));
            $emailMatch = $email !== '' && $bookingEmail !== '' && hash_equals($bookingEmail, $email);
            $phoneMatch = $phone !== '' && $bookingPhone !== '' && bops_phone_match($bookingPhone, $phone);
            $sessionMatch = $sessionEmail !== '' && $bookingEmail !== '' && hash_equals($bookingEmail, $sessionEmail);
            if (!$emailMatch && !$phoneMatch && !$sessionMatch) return [false, 'Booking contact details do not match'];

            if (in_array((string)($booking['status'] ?? ''), ['cancelled', 'rejected', 'expired'], true)) {
                return [false, 'Cancelled, rejected, or expired bookings cannot request payment'];
            }
            if (($booking['payment_status'] ?? '') === 'paid') return [false, 'This booking has already been paid'];

            $booking['payment_method'] = $method;
            $booking['payment_request_status'] = 'pending';
            $booking['payment_requested_at'] = date('c');
            $booking['updated_at'] = date('c');
            $rows[$idx] = $booking;
            return [$rows, $booking];
        });
        if (!$ok) {
            $status = $out === 'Ticket reference not found' ? 404
                : ($out === 'Booking contact details do not match' ? 403 : 400);
            return ['http' => $status, 'body' => ['success' => false, 'error' => (string)$out]];
        }
        bops_sync_sites($slug, 'bookings', (string)($out['id'] ?? ''), $out);
        bops_mirror_admin($slug, 'bookings', $out);
        $message = $method === 'counter'
            ? 'Counter payment selected. The booking remains unpaid until staff records the payment.'
            : 'Online payment requested. The operator will follow up; no online charge was made.';
        return ['http' => 200, 'body' => ['success' => true, 'data' => $out, 'message' => $message]];
    }

    if ($action === 'pay') {
        if (!bops_is_admin($slug)) return ['http' => 403, 'body' => ['success' => false, 'error' => 'Admin only']];
        $ref = (string)($req['ref'] ?? '');
        $method = strtolower((string)($req['method'] ?? ''));
        $ref = strtoupper(trim($ref));
        if (!in_array($method, ['cash', 'card'], true)) {
            return ['http' => 400, 'body' => ['success' => false, 'error' => 'Payment method must be cash or card']];
        }
        if ($ref === '') return ['http' => 400, 'body' => ['success' => false, 'error' => 'Missing ref']];
        foreach (bops_load($slug, 'payments') as $existingPayment) {
            if (is_array($existingPayment) && strtoupper((string)($existingPayment['booking_ref'] ?? '')) === $ref) {
                return ['http' => 400, 'body' => ['success' => false, 'error' => 'A payment record already exists for this ticket']];
            }
        }
        $paymentId = 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        [$ok, $out] = bops_transact($slug, 'bookings', function ($rows) use ($ref, $method, $slug, $paymentId) {
            $payIdx = -1;
            foreach ($rows as $i => $b) {
                if (!is_array($b) || (($b['ref'] ?? '') !== $ref && ($b['id'] ?? '') !== $ref)) continue;
                $payIdx = $i;
                break;
            }
            if ($payIdx < 0) {
                // Adopt a twin-store row so payments reach Node rows.
                foreach (bops_union_rows($rows, $slug, 'bookings') as $u) {
                    if (!is_array($u) || (($u['ref'] ?? '') !== $ref && ($u['id'] ?? '') !== $ref)) continue;
                    $rows[] = $u;
                    $payIdx = count($rows) - 1;
                    break;
                }
                if ($payIdx < 0) return [false, 'Booking not found'];
            }
            $b = $rows[$payIdx];
            if (in_array(($b['status'] ?? ''), ['cancelled', 'expired'], true)) return [false, 'Booking ' . $b['status'] . ' — payment rejected'];
            if (($b['status'] ?? '') !== 'approved') return [false, 'Booking must be approved before payment can be recorded'];
            if (($b['payment_status'] ?? '') === 'paid') return [false, 'Already paid — duplicate rejected'];
            $amount = bops_booking_amount($slug, $b);
            if ($amount <= 0) return [false, 'The booking has no valid fare; update the trip fare before recording payment'];
            $b['payment_status'] = 'paid';
            $b['paid_at'] = date('c');
            $b['pay_method'] = $method;
            $b['pay_amount'] = $amount;
            $b['payment_id'] = $paymentId;
            if (($b['payment_request_status'] ?? '') === 'pending') {
                $b['payment_request_status'] = 'completed';
                $b['payment_received_at'] = date('c');
            }
            $b['updated_at'] = date('c');
            $rows[$payIdx] = $b;
            return [$rows, ['booking' => $b, 'payment' => [
                'id' => $paymentId,
                'payment_id' => $paymentId,
                'booking_ref' => $ref,
                'method' => $method,
                'amount' => $amount,
                'currency' => $b['currency'] ?? 'LKR',
                'status' => 'completed',
                'created_at' => date('c'),
                'received_at' => date('c'),
            ]]];
        });
        if (!$ok) return ['http' => ($out === 'Booking not found' ? 404 : 400), 'body' => ['success' => false, 'error' => (string)$out]];
        $booking = $out['booking'];
        $payment = $out['payment'];
        bops_sync_sites($slug, 'bookings', (string)($booking['id'] ?? ''), $booking);
        [$paymentSaved, $paymentResult] = bops_transact($slug, 'payments', function ($rows) use ($payment) {
            foreach ($rows as $row) {
                if (is_array($row) && (($row['booking_ref'] ?? '') === $payment['booking_ref']
                    || ($row['payment_id'] ?? $row['id'] ?? '') === $payment['payment_id'])) {
                    return [false, 'Payment already exists for this booking'];
                }
            }
            $rows[] = $payment;
            return [$rows, $payment];
        });
        if (!$paymentSaved) {
            [$rollbackOk] = bops_transact($slug, 'bookings', function ($rows) use ($booking) {
                foreach ($rows as $i => $row) {
                    if (!is_array($row) || (string)($row['id'] ?? '') !== (string)$booking['id']) continue;
                    if (($row['payment_id'] ?? '') !== ($booking['payment_id'] ?? '')) return [$rows, false];
                    unset($row['payment_id'], $row['paid_at'], $row['pay_method'], $row['pay_amount']);
                    $row['payment_status'] = 'unpaid';
                    if (($row['payment_request_status'] ?? '') === 'completed') {
                        $row['payment_request_status'] = 'pending';
                        unset($row['payment_received_at']);
                    }
                    $row['updated_at'] = date('c');
                    $rows[$i] = $row;
                    return [$rows, $row];
                }
                return [$rows, false];
            });
            if ($rollbackOk) {
                $restored = null;
                foreach (bops_load($slug, 'bookings') as $row) {
                    if (is_array($row) && (string)($row['id'] ?? '') === (string)$booking['id']) { $restored = $row; break; }
                }
                if ($restored) {
                    bops_sync_sites($slug, 'bookings', (string)$booking['id'], $restored);
                    bops_mirror_admin($slug, 'bookings', $restored);
                }
            }
            return ['http' => 500, 'body' => ['success' => false, 'error' => (string)$paymentResult]];
        }
        bops_mirror_admin($slug, 'bookings', $booking);
        bops_mirror_admin($slug, 'payments', $payment);
        return ['http' => 200, 'body' => ['success' => true, 'data' => $booking, 'payment' => $payment]];
    }

    // Ticket retrieval by phone + booking ref (no login needed).
    // BOTH must match the same record — a ref alone or a phone alone
    // returns nothing, so nobody can enumerate another person's tickets.
    // Returns one stripped record (never a list).
    if ($action === 'ticket') {
        $ref = strtoupper(trim((string)($req['ref'] ?? '')));
        $phone = trim((string)($req['phone'] ?? ''));
        $email = strtolower(trim((string)($req['email'] ?? '')));
        if ($ref === '' || ($phone === '' && $email === '')) {
            return ['http' => 400, 'body' => ['success' => false, 'error' => 'Booking ref + phone (or email) required']];
        }
        if (!empty($req['company'])) return ['http' => 403, 'body' => ['success' => false, 'error' => 'Spam detected']];
        $rows = bops_load($slug, $entity !== '' ? $entity : 'bookings');
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $rref = strtoupper((string)($r['ref'] ?? $r['id'] ?? ''));
            if ($rref === '' || ($rref !== $ref && strtoupper((string)($r['id'] ?? '')) !== $ref)) continue;
            $contactOk = false;
            if ($phone !== '' && isset($r['phone']) && bops_phone_match((string)$r['phone'], $phone)) $contactOk = true;
            if (!$contactOk && $email !== '' && strtolower((string)($r['email'] ?? '')) === $email && $email !== '') $contactOk = true;
            if (!$contactOk) continue;
            return ['http' => 200, 'body' => ['success' => true, 'data' => bops_strip_secrets($r)]];
        }
        return ['http' => 404, 'body' => ['success' => false, 'error' => 'No ticket found for that ref + contact. Check both and try again.']];
    }

    // Ticket verification for QR scans and operator checks (read-only).
    // Ref alone returns a LIMITED public verdict (no PII, no contact data)
    // so boarding staff can confirm valid/pending/cancelled/rejected without
    // leaking customer details. A matching verify_token (embedded in the QR
    // URL) unlocks the full ticket view. Never changes booking status.
    if ($action === 'verify') {
        $ref = strtoupper(trim((string)($req['ref'] ?? '')));
        $token = trim((string)($req['token'] ?? $req['t'] ?? ''));
        if ($ref === '') return ['http' => 400, 'body' => ['success' => false, 'error' => 'Booking ref required']];
        if (!empty($req['company'])) return ['http' => 403, 'body' => ['success' => false, 'error' => 'Spam detected']];
        $rows = bops_load($slug, $entity !== '' ? $entity : 'bookings');
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $r = bops_booking_view($r);
            $rref = strtoupper((string)($r['ref'] ?? $r['id'] ?? ''));
            if ($rref === '' || ($rref !== $ref && strtoupper((string)($r['id'] ?? '')) !== $ref)) continue;
            $status = strtolower((string)($r['status'] ?? 'pending'));
            $verdict = in_array($status, ['cancelled', 'rejected', 'expired'], true) ? $status : (in_array($status, ['approved', 'confirmed'], true) ? 'valid' : $status);
            $tokenOk = ($token !== '' && !empty($r['verify_token']) && hash_equals((string)$r['verify_token'], $token));
            if ($tokenOk) {
                return ['http' => 200, 'body' => ['success' => true, 'data' => bops_strip_secrets($r), 'verdict' => $verdict]];
            }
            $seats = bops_norm_seats($r['seats'] ?? []);
            return ['http' => 200, 'body' => ['success' => true, 'verdict' => $verdict, 'data' => [
                'ref' => (string)($r['ref'] ?? ''),
                'trip_id' => (string)($r['trip_id'] ?? ''),
                'route' => (string)($r['route'] ?? ''),
                'trip_date' => (string)($r['trip_date'] ?? ''),
                'departure' => (string)($r['departure'] ?? ''),
                'seats' => $seats,
                'seat_count' => count($seats),
                'status' => (string)($r['status'] ?? ''),
                'payment_status' => (string)($r['payment_status'] ?? 'unpaid'),
            ]]];
        }
        return ['http' => 404, 'body' => ['success' => false, 'error' => 'No ticket found for that reference.']];
    }

    return ['http' => 400, 'body' => ['success' => false, 'error' => 'Unknown action: ' . $action]];
}

/**
 * Seed demo schedules into the backend (collections + admin data file) when a
 * bus/trips concept exists but the store is empty. Demo trips then come from
 * the SAME backend as admin-added trips — the storefront lists them via the
 * normal list API, bookings attach to real trip ids, and the admin sees
 * everything. Never overwrites existing rows. Dates roll forward (+1/+2/+3
 * days) so seeded schedules are always bookable.
 * Returns the seeded rows (empty when skipped).
 */
function bops_seed_demo_trips(string $slug, string $adminDir = '', array $entityIds = []): array {
    try {
        $slug = bops_slug(['slug' => $slug]);
        $hasTrips = in_array('trips', $entityIds, true)
            || ($adminDir !== '' && is_file($adminDir . '/trips.php'));
        if (!$hasTrips && $adminDir !== '' && is_file($adminDir . '/manifest.json')) {
            $m = json_decode(@file_get_contents($adminDir . '/manifest.json'), true);
            foreach ((array)($m['entities'] ?? []) as $e) {
                if (!is_array($e)) continue;
                $eid = strtolower((string)($e['id'] ?? ''));
                if ($eid === 'trips' || (function_exists('business_registry_canonical_entity')
                    && business_registry_canonical_entity($eid) === 'trips')) { $hasTrips = true; break; }
            }
        }
        if (!$hasTrips) return [];
        if (!empty(bops_load($slug, 'trips'))) return [];
        $d1 = date('Y-m-d', time() + 86400);
        $d2 = date('Y-m-d', time() + 2 * 86400);
        $d3 = date('Y-m-d', time() + 3 * 86400);
        $now = date('c');
        $demo = [
            ['route' => 'Chennai → Bangalore', 'origin' => 'Chennai', 'destination' => 'Bangalore',
             'date' => $d1, 'departure' => '06:30', 'price' => 899, 'total_seats' => 40, 'bus' => 'AC Sleeper'],
            ['route' => 'Chennai → Bangalore', 'origin' => 'Chennai', 'destination' => 'Bangalore',
             'date' => $d1, 'departure' => '22:15', 'price' => 549, 'total_seats' => 40, 'bus' => 'Non-AC Seater'],
            ['route' => 'Bangalore → Chennai', 'origin' => 'Bangalore', 'destination' => 'Chennai',
             'date' => $d2, 'departure' => '21:00', 'price' => 899, 'total_seats' => 32, 'bus' => 'AC Sleeper'],
        ];
        foreach ($demo as &$t) {
            $t['id'] = 'r_' . bin2hex(random_bytes(8));
            $t['created_at'] = $now;
            $t['updated_at'] = $now;
            $t['demo'] = true;
        }
        unset($t);
        [$ok, $out] = bops_transact($slug, 'trips', function ($rows) use ($demo) {
            if (!empty($rows)) return [false, 'not-empty'];
            return [array_values($demo), $demo];
        });
        if (!$ok) return [];
        // Same rows into the admin data file so the dashboard manages them.
        if ($adminDir !== '' && is_dir($adminDir)) {
            $f = $adminDir . '/data_trips.json';
            $h = @fopen($f, 'c+');
            if ($h) {
                try {
                    if (flock($h, LOCK_EX)) {
                        $raw = stream_get_contents($h);
                        $rows = ($raw !== false && trim((string)$raw) !== '') ? (json_decode($raw, true) ?: []) : [];
                        if (!is_array($rows)) $rows = [];
                        if (empty($rows)) {
                            ftruncate($h, 0);
                            rewind($h);
                            fwrite($h, json_encode(array_values($demo), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            fflush($h);
                        }
                    }
                } finally { flock($h, LOCK_UN); fclose($h); }
            }
        }
        return $demo;
    } catch (Throwable $e) { return []; }
}

/**
 * Mirror an operational record into an admin's data files so the
 * admin dashboard shows real customer activity. Upsert by id; delete flag
 * removes.
 * Also mirrors into a live PREVIEW admin (preview/<slug>/admin) when one
 * exists, so instant bookings made while testing appear in the preview
 * admin immediately — not only after publish.
 * Returns true when mirrored, false when skipped.
 */
function bops_mirror_to_dir(string $adminDir, string $entity, array $rec, bool $delete): bool {
    if (!is_dir($adminDir)) return false;
    $map = ['inquiries' => 'data_inquiries.json'];
    $file = $adminDir . '/' . ($map[$entity] ?? ('data_' . $entity . '.json'));
    $h = @fopen($file, 'c+');
    if (!$h) return false;
    try {
        if (!flock($h, LOCK_EX)) return false;
        $raw = stream_get_contents($h);
        $rows = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        if (!is_array($rows)) $rows = [];
        $rows = array_values($rows);
        $id = (string)($rec['id'] ?? '');
        if ($delete) {
            $rows = array_values(array_filter($rows, fn($r) => (string)(is_array($r) ? ($r['id'] ?? '') : '') !== $id));
        } elseif ($id !== '') {
            $row = $rec;
            if ($entity === 'inquiries' && !array_key_exists('read', $row)) $row['read'] = false;
            $row['updated_at'] = date('c');
            $done = false;
            foreach ($rows as &$r) {
                if (is_array($r) && (string)($r['id'] ?? '') === $id) { $r = array_merge($r, $row); $done = true; break; }
            }
            if (!$done) array_unshift($rows, $row);
            if (count($rows) > 2000) $rows = array_slice($rows, 0, 2000);
        }
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($h);
        return true;
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}
function bops_mirror_admin(string $slug, string $entity, array $rec, bool $delete = false): bool {
    $base = dirname(__DIR__);
    $didPub = bops_mirror_to_dir($base . '/published/' . $slug . '/admin', $entity, $rec, $delete);
    $didPrev = bops_mirror_to_dir($base . '/preview/' . $slug . '/admin', $entity, $rec, $delete);
    return $didPub || $didPrev;
}
