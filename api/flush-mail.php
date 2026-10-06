<?php
// api/flush-mail.php — Background mail-queue worker.
// Triggered fire-and-forget after every enqueue + manually from admin.
// Safe under concurrency (flock). Never fatal-errors (always JSON).
if (function_exists('ignore_user_abort')) @ignore_user_abort(true);
@set_time_limit(300);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/includes/MailQueue.php';
    $max = max(1, min(20, (int)($_GET['max'] ?? 8)));
    $res = flushMailQueue($max);
    echo json_encode($res);
} catch (Throwable $t) {
    http_response_code(200); // never 500 — queue retries next run
    echo json_encode(['success' => false, 'error' => $t->getMessage()]);
}
