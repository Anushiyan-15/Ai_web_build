<?php
/**
 * includes/CustomerSupport.php — Customer support tickets
 *
 * Tickets are stored as storage/submissions/<TCK-ID>.json so the existing
 * platform-admin inbox (platform-admin/helpers.php::get_support_tickets())
 * reads them without any second support system or schema.
 *
 * Stored shape (compatible with update_ticket_status()):
 *   id, name, email, subject, message, status, created_at, replies[]
 *   reply: by, role, msg, time
 *
 * Ownership is always checked against the email stored inside the ticket,
 * never against a client-supplied value.
 */

function support_tickets_dir(): string {
    $base = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    $dir = rtrim($base, '/\\') . '/submissions';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function support_ticket_id_valid(string $id): bool {
    return (bool)preg_match('/^TCK-\d{8}-[A-F0-9]{6}$/', $id);
}

/** Absolute path for a valid ticket id, or null for anything else. */
function support_ticket_path(string $id): ?string {
    if (!support_ticket_id_valid($id)) return null;
    return support_tickets_dir() . '/' . $id . '.json';
}

/** Normalise a decoded ticket into the shared shape. */
function support_normalize_ticket(array $data, string $fallbackId = ''): array {
    $replies = [];
    foreach ((array)($data['replies'] ?? []) as $r) {
        if (!is_array($r)) continue;
        $replies[] = [
            'by'   => (string)($r['by'] ?? 'Support'),
            'role' => (string)($r['role'] ?? ''),
            'msg'  => (string)($r['msg'] ?? ''),
            'time' => (string)($r['time'] ?? ''),
        ];
    }
    $createdAt = (string)($data['created_at'] ?? '');
    $updatedAt = (string)($data['updated_at'] ?? $createdAt);
    foreach ($replies as $r) {
        if (($r['time'] ?? '') !== '' && strtotime($r['time']) > strtotime($updatedAt ?: 'now')) {
            $updatedAt = $r['time'];
        }
    }
    return [
        'id'         => (string)($data['id'] ?? $fallbackId),
        'name'       => (string)($data['name'] ?? 'Customer'),
        'email'      => strtolower(trim((string)($data['email'] ?? ''))),
        'subject'    => (string)($data['subject'] ?? 'Support request'),
        'message'    => (string)($data['message'] ?? ''),
        'status'     => (string)($data['status'] ?? 'open'),
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
        'order_id'   => (string)($data['order_id'] ?? ''),
        'source'     => (string)($data['source'] ?? ''),
        'replies'    => $replies,
    ];
}

/**
 * Read one ticket with a shared lock. Returns null if missing/invalid.
 * Extra fields written by other code are preserved by support_update_ticket().
 */
function support_read_ticket(string $id): ?array {
    $path = support_ticket_path($id);
    if ($path === null || !is_file($path)) return null;
    $fh = @fopen($path, 'rb');
    if (!$fh) return null;
    @flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    @flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) return null;
    return support_normalize_ticket($data, $id);
}

/** All tickets whose stored email matches $email (case-insensitive). */
function support_tickets_for_email(string $email): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [];
    $out = [];
    foreach (glob(support_tickets_dir() . '/*.json') ?: [] as $f) {
        $data = json_decode((string)@file_get_contents($f), true);
        if (!is_array($data)) continue;
        if (strcasecmp(trim((string)($data['email'] ?? '')), $email) !== 0) continue;
        $out[] = support_normalize_ticket($data, basename($f, '.json'));
    }
    usort($out, function (array $a, array $b): int {
        return strcmp($b['updated_at'] ?: $b['created_at'], $a['updated_at'] ?: $a['created_at']);
    });
    return $out;
}

function support_ticket_status_label(string $status): string {
    $map = [
        'open'     => 'Open',
        'pending'  => 'Awaiting support',
        'resolved' => 'Resolved',
        'closed'   => 'Closed',
    ];
    return $map[strtolower($status)] ?? ucfirst($status ?: 'open');
}

/** Apply a mutation under an exclusive lock and persist atomically. */
function support_update_ticket(string $id, callable $mutator): array {
    $path = support_ticket_path($id);
    if ($path === null || !is_file($path)) {
        return ['success' => false, 'error' => 'Ticket not found.'];
    }
    $fh = @fopen($path, 'c+b');
    if (!$fh) return ['success' => false, 'error' => 'Could not open the ticket for update.'];
    if (!@flock($fh, LOCK_EX)) {
        fclose($fh);
        return ['success' => false, 'error' => 'Could not lock the ticket for update.'];
    }
    $raw = stream_get_contents($fh);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        @flock($fh, LOCK_UN);
        fclose($fh);
        return ['success' => false, 'error' => 'Ticket data is unreadable.'];
    }
    $result = $mutator($data);
    if (is_array($result) && array_key_exists('data', $result)) {
        $data = $result['data'];
        unset($result['data']);
    }
    if (!empty($result['success'])) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        rewind($fh);
        ftruncate($fh, 0);
        $ok = fwrite($fh, (string)$json) !== false;
        fflush($fh);
        if (!$ok) $result = ['success' => false, 'error' => 'Could not save the ticket.'];
    }
    @flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

/**
 * Create a ticket for the signed-in customer.
 * Duplicate submissions of the same subject+message within 2 minutes are
 * returned as the existing ticket instead of creating a second file.
 */
function support_ticket_create(string $email, string $name, string $subject, string $message, string $orderId = ''): array {
    $email = strtolower(trim($email));
    $name = trim(strip_tags($name));
    $subject = trim(strip_tags($subject));
    $message = trim($message);
    $orderId = trim(strip_tags($orderId));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please sign in with a valid email address.'];
    }
    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
        return ['success' => false, 'error' => 'Subject must be between 3 and 150 characters.'];
    }
    if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
        return ['success' => false, 'error' => 'Message must be between 10 and 5000 characters.'];
    }
    if (preg_match('/[\r\n]/', $subject)) {
        return ['success' => false, 'error' => 'Subject must be a single line.'];
    }

    // Duplicate-submit guard (double click / refresh).
    foreach (support_tickets_for_email($email) as $existing) {
        if (strcasecmp($existing['subject'], $subject) !== 0) continue;
        if (trim($existing['message']) !== $message) continue;
        if (strtolower($existing['status']) === 'closed') continue;
        if (strtotime($existing['created_at'] ?: 'now') < time() - 120) continue;
        return ['success' => true, 'ticket' => $existing, 'duplicate' => true];
    }

    $dir = support_tickets_dir();
    $id = '';
    $path = '';
    for ($try = 0; $try < 5; $try++) {
        $candidate = 'TCK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $candidatePath = $dir . '/' . $candidate . '.json';
        if (!file_exists($candidatePath)) {
            $id = $candidate;
            $path = $candidatePath;
            break;
        }
    }
    if ($id === '' || $path === '') {
        return ['success' => false, 'error' => 'Could not allocate a ticket reference. Please try again.'];
    }

    $now = date('Y-m-d H:i:s');
    $data = [
        'id'         => $id,
        'name'       => $name !== '' ? $name : 'Customer',
        'email'      => $email,
        'subject'    => $subject,
        'message'    => $message,
        'status'     => 'open',
        'created_at' => $now,
        'updated_at' => $now,
        'order_id'   => preg_replace('/[^A-Za-z0-9-]/', '', $orderId),
        'source'     => 'customer_portal',
        'replies'    => [],
    ];

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $fh = @fopen($path, 'xb');
    if (!$fh) {
        return ['success' => false, 'error' => 'Could not create the ticket file.'];
    }
    $ok = fwrite($fh, (string)$json) !== false;
    fflush($fh);
    fclose($fh);
    if (!$ok) {
        @unlink($path);
        return ['success' => false, 'error' => 'Could not save the ticket.'];
    }

    return ['success' => true, 'ticket' => support_normalize_ticket($data, $id)];
}

/** Customer reply; only the ticket owner can post and only while not closed. */
function support_ticket_reply(string $id, string $email, string $name, string $message): array {
    $email = strtolower(trim($email));
    $message = trim($message);
    if (mb_strlen($message) < 2 || mb_strlen($message) > 3000) {
        return ['success' => false, 'error' => 'Reply must be between 2 and 3000 characters.'];
    }
    $ticket = support_read_ticket($id);
    if (!$ticket) return ['success' => false, 'error' => 'Ticket not found.'];
    if (!hash_equals($ticket['email'], $email)) {
        return ['success' => false, 'error' => 'You do not have access to this ticket.'];
    }
    if (strtolower($ticket['status']) === 'closed') {
        return ['success' => false, 'error' => 'This ticket is closed. Reopen it to reply.'];
    }
    return support_update_ticket($id, function (array $data) use ($name, $message): array {
        if (!isset($data['replies']) || !is_array($data['replies'])) $data['replies'] = [];
        $data['replies'][] = [
            'by'   => $name !== '' ? $name : 'Customer',
            'role' => 'customer',
            'msg'  => $message,
            'time' => date('Y-m-d H:i:s'),
        ];
        // A customer reply reopens a resolved ticket for support attention.
        if (in_array(strtolower((string)($data['status'] ?? '')), ['resolved', 'pending'], true)) {
            $data['status'] = 'open';
        }
        return ['success' => true, 'data' => $data];
    });
}

/** Owner-only status change (close / reopen). */
function support_ticket_set_status(string $id, string $email, string $status): array {
    $email = strtolower(trim($email));
    $status = strtolower(trim($status));
    if (!in_array($status, ['open', 'closed'], true)) {
        return ['success' => false, 'error' => 'Unsupported ticket status.'];
    }
    $ticket = support_read_ticket($id);
    if (!$ticket) return ['success' => false, 'error' => 'Ticket not found.'];
    if (!hash_equals($ticket['email'], $email)) {
        return ['success' => false, 'error' => 'You do not have access to this ticket.'];
    }
    return support_update_ticket($id, function (array $data) use ($status): array {
        $data['status'] = $status;
        return ['success' => true, 'data' => $data];
    });
}
