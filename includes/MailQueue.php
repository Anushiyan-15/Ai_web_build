<?php
// ═══════════════════════════════════════════════════════════════
//  includes/MailQueue.php — File-backed mail queue (fast enqueue,
//  background flush). Synchronous SMTP takes ~7s/mail and causes
//  500s under load — so ONLY OTP codes send inline. Everything
//  else (notifications, welcome, alerts, bulk) goes via queue.
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/Mailer.php';

function mailQueueFile(): string {
    $d = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d . '/mail_queue.json';
}

function mailQueueLockFile(): string {
    $d = defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage';
    return $d . '/mail_queue.lock';
}

function loadMailQueue(): array {
    $f = mailQueueFile();
    if (!file_exists($f)) return [];
    $a = json_decode(@file_get_contents($f), true);
    return is_array($a) ? $a : [];
}

function saveMailQueue(array $q): void {
    @file_put_contents(mailQueueFile(), json_encode($q, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Enqueue a mail for background delivery. Returns instantly (<5ms).
 * $job: to, subject, html, text, kind, order_id?, ntype?, notif_subject?,
 *       notif_message?, sent_by?
 */
function queueMail(array $job): array {
    $to = trim($job['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid recipient email'];
    }
    if (trim($job['subject'] ?? '') === '' || trim($job['html'] ?? $job['text'] ?? '') === '') {
        return ['success' => false, 'error' => 'Empty subject or body'];
    }
    $q = loadMailQueue();
    // Prune old sent/failed (keep file small)
    $cutoff = time() - 7 * 86400;
    $q = array_values(array_filter($q, fn($j) => ($j['status'] ?? 'pending') === 'pending'
        || strtotime($j['created_at'] ?? 'now') > $cutoff));
    // Cap pending backlog (protect disk + runaway loops)
    $pending = 0;
    foreach ($q as $j) if (($j['status'] ?? '') === 'pending') $pending++;
    if ($pending >= 500) {
        return ['success' => false, 'error' => 'Mail queue full. Try again later.'];
    }
    $id = 'mq_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $q[] = [
        'id' => $id,
        'to' => $to,
        'subject' => $job['subject'],
        'html' => $job['html'],
        'text' => $job['text'] ?? strip_tags($job['html'] ?? ''),
        'kind' => $job['kind'] ?? 'generic',
        'order_id' => $job['order_id'] ?? '',
        'ntype' => $job['ntype'] ?? 'info',
        'notif_subject' => $job['notif_subject'] ?? $job['subject'],
        'notif_message' => $job['notif_message'] ?? strip_tags($job['text'] ?? $job['html'] ?? ''),
        'sent_by' => $job['sent_by'] ?? 'system',
        'attempts' => 0,
        'status' => 'pending',
        'last_error' => '',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    saveMailQueue($q);
    triggerMailFlushAsync();
    return ['success' => true, 'id' => $id, 'queued' => true];
}

/**
 * Fire-and-forget background flush trigger (never blocks, never throws).
 */
function triggerMailFlushAsync(): void {
    try {
        $base = defined('SITE_URL') ? SITE_URL : '';
        if ($base === '') return;
        $parts = parse_url($base . '/api/flush-mail.php');
        if (empty($parts['host'])) return;
        $host = $parts['host'];
        $port = $parts['port'] ?? (strtolower($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $path = ($parts['path'] ?? '/api/flush-mail.php');
        $fp = @fsockopen($host, $port, $errno, $errstr, 1);
        if (!$fp) return;
        stream_set_timeout($fp, 1);
        @fwrite($fp, "GET {$path}?async=1 HTTP/1.0\r\nHost: {$host}\r\nConnection: close\r\n\r\n");
        @fclose($fp); // do NOT wait for response — server continues alone
    } catch (Throwable $t) {}
}

/**
 * Process up to $max pending mails. Single SMTP connection (fast bulk).
 * Safe for concurrent calls (non-blocking flock — busy runners skip).
 */
function flushMailQueue(int $max = 8): array {
    $lockFp = @fopen(mailQueueLockFile(), 'c');
    if (!$lockFp) return ['success' => false, 'error' => 'Cannot open lock'];
    if (!@flock($lockFp, LOCK_EX | LOCK_NB)) {
        @fclose($lockFp);
        return ['success' => true, 'skipped' => true, 'reason' => 'another flush running'];
    }

    $sent = 0; $failed = 0; $errors = [];
    try {
        $q = loadMailQueue();
        $todo = [];
        foreach ($q as $idx => $j) {
            if (($j['status'] ?? '') === 'pending' && (int)($j['attempts'] ?? 0) < 3) {
                $todo[$idx] = $j;
                if (count($todo) >= $max) break;
            }
        }
        if (!empty($todo)) {
            $batch = [];
            foreach ($todo as $idx => $j) {
                $batch[$idx] = ['to' => $j['to'], 'subject' => $j['subject'], 'html' => $j['html'], 'text' => $j['text']];
            }
            $results = Mailer::sendBatch($batch);
            foreach ($todo as $idx => $j) {
                $r = $results[$idx] ?? ['success' => false, 'error' => 'no result'];
                if (!empty($r['success'])) {
                    $q[$idx]['status'] = 'sent';
                    $q[$idx]['sent_at'] = date('Y-m-d H:i:s');
                    $sent++;
                    // Record portal notification AFTER actual delivery
                    if (!empty($j['order_id'])) {
                        try {
                            Mailer::recordNotificationInDb(
                                $j['order_id'], $j['ntype'] ?? 'info',
                                $j['notif_subject'] ?? $j['subject'],
                                $j['notif_message'] ?? '', $j['sent_by'] ?? 'system', true
                            );
                        } catch (Throwable $t) {}
                    }
                } else {
                    $q[$idx]['attempts'] = (int)($j['attempts'] ?? 0) + 1;
                    $q[$idx]['last_error'] = substr($r['error'] ?? 'send failed', 0, 300);
                    $q[$idx]['last_try'] = date('Y-m-d H:i:s');
                    if ($q[$idx]['attempts'] >= 3) {
                        $q[$idx]['status'] = 'failed';
                        $failed++;
                    }
                }
            }
            saveMailQueue($q);
        }
    } catch (Throwable $t) {
        $errors[] = $t->getMessage();
    }
    @flock($lockFp, LOCK_UN);
    @fclose($lockFp);

    $pending = 0;
    foreach (loadMailQueue() as $j) if (($j['status'] ?? '') === 'pending') $pending++;
    return ['success' => true, 'sent' => $sent, 'failed' => $failed, 'pending' => $pending, 'errors' => $errors];
}

function mailQueuePending(): int {
    $n = 0;
    foreach (loadMailQueue() as $j) if (($j['status'] ?? '') === 'pending') $n++;
    return $n;
}

/**
 * Shortcut: queue an order-linked notification email (template prebuilt).
 * Saves the portal file-note instantly; delivery happens in background.
 */
function queueNotificationEmail(string $orderId, string $to, string $subject, string $message, string $type = 'info', ?array $order = null, string $sentBy = 'system'): array {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email'];
    }
    $html = Mailer::buildNotificationHtml($orderId, $subject, $message, $type, $order);
    return queueMail([
        'to' => $to, 'subject' => $subject, 'html' => $html, 'text' => $message,
        'kind' => 'notification', 'order_id' => $orderId, 'ntype' => $type,
        'notif_subject' => $subject, 'notif_message' => $message, 'sent_by' => $sentBy,
    ]);
}
