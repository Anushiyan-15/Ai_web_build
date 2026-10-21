<?php
// includes/templates/bus-ticket-verify.php — Public QR ticket verification.
// Per-site copy lives at published/<slug>/verify.php and preview/<id>/verify.php.
// Read-only: resolves ref (+ optional token) against the canonical backend via
// bops_dispatch verify. Ref alone yields a LIMITED verdict (no PII); a matching
// verify_token (embedded in the QR URL) unlocks the full ticket view.
// Verification never changes booking status and never marks payment as paid.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config.php';
require_once $projectRoot . '/includes/BusinessOps.php';

$slug = preg_replace('/[^a-z0-9_-]/i', '', basename(__DIR__));
if ($slug === '') {
    http_response_code(400);
    exit('Invalid bus site.');
}
$esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$ref = strtoupper(trim((string)($_GET['ref'] ?? '')));
$token = trim((string)($_GET['t'] ?? $_GET['token'] ?? ''));
$verdict = '';
$ticket = null;
$limited = null;
$error = '';
if ($ref !== '') {
    $response = bops_dispatch(['action' => 'verify', 'entity' => 'bookings', 'slug' => $slug, 'ref' => $ref, 'token' => $token]);
    if (($response['http'] ?? 500) === 200 && !empty($response['body']['success'])) {
        $verdict = (string)($response['body']['verdict'] ?? '');
        if ($token !== '' && isset($response['body']['data']['passenger'])) {
            $ticket = $response['body']['data'];
        } else {
            $limited = $response['body']['data'] ?? null;
        }
    } else {
        $error = (string)($response['body']['error'] ?? 'Ticket not found.');
        http_response_code((int)($response['http'] ?? 404));
    }
}
$statusLabel = static fn($status) => ucfirst(str_replace('_', ' ', (string)$status));
$verdictLabel = $verdict !== '' ? $statusLabel($verdict) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ticket verification | <?= $esc($slug) ?></title>
  <style>
    :root{font-family:Arial,Helvetica,sans-serif;color:#17212b;background:#f3f5f3}
    *{box-sizing:border-box}body{margin:0;padding:clamp(1rem,5vw,3rem)}
    main{max-width:640px;margin:0 auto;background:#fff;border:1px solid #dbe3e7;border-radius:16px;padding:clamp(1.2rem,4vw,2rem)}
    .eyebrow{color:#64716c;font-size:.78rem;font-weight:700;letter-spacing:.13em;text-transform:uppercase;margin:0 0 .5rem}
    h1{margin:0 0 .5rem;font-size:clamp(1.5rem,4vw,2rem)}
    .verdict{display:inline-block;padding:.4rem .7rem;border-radius:6px;font-weight:800;margin:.6rem 0}
    .verdict.valid,.verdict.approved,.verdict.confirmed{background:#dcfce7;color:#166534}
    .verdict.pending,.verdict.awaiting-payment{background:#fef9c3;color:#854d0e}
    .verdict.cancelled,.verdict.rejected,.verdict.expired{background:#fee2e2;color:#991b1b}
    dl{display:grid;grid-template-columns:auto 1fr;gap:.4rem 1rem;margin:1rem 0}
    dt{color:#64716c}dd{margin:0;font-weight:600}
    form{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem}
    input{flex:1;min-width:160px;min-height:44px;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font:inherit}
    button{min-height:44px;padding:.6rem 1rem;border:0;border-radius:8px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer}
    .error{color:#991b1b;background:#fee2e2;border-left:3px solid #991b1b;padding:.7rem 1rem}
    .note{color:#64716c;font-size:.85rem;line-height:1.55}
  </style>
</head>
<body>
<main>
  <p class="eyebrow"><?= $esc($slug) ?> · Ticket verification</p>
  <h1>Verify a bus ticket</h1>
  <p class="note">Scan the ticket QR or enter the booking reference. Verification is read-only: it never confirms payment or changes the booking.</p>
  <form method="get" action="verify.php">
    <input name="ref" placeholder="Booking ref (e.g. BK-XXXXXXXX)" value="<?= $esc($ref) ?>" required maxlength="64">
    <button type="submit">Verify</button>
  </form>
  <?php if ($error !== '' && $ref !== ''): ?>
    <p class="error" role="alert"><?= $esc($error) ?></p>
  <?php elseif ($ref !== '' && ($ticket || $limited)): ?>
    <p><span class="verdict <?= $esc(strtolower(preg_replace('/[^a-z-]/', '', $verdict))) ?>"><?= $esc($verdictLabel) ?></span></p>
    <?php if (is_array($ticket)): ?>
      <?php $seats = $ticket['seats'] ?? []; if (!is_array($seats)) $seats = [$seats]; ?>
      <dl>
        <dt>Reference</dt><dd><?= $esc($ticket['ref'] ?? $ref) ?></dd>
        <dt>Passenger</dt><dd><?= $esc($ticket['passenger'] ?? '') ?></dd>
        <dt>Route</dt><dd><?= $esc($ticket['route'] ?? '') ?></dd>
        <dt>Trip</dt><dd><?= $esc($ticket['trip_id'] ?? '') ?> · <?= $esc($ticket['trip_date'] ?? '') ?> <?= $esc($ticket['departure'] ?? '') ?></dd>
        <dt>Seats</dt><dd><?= $esc(implode(', ', array_map('strval', $seats))) ?></dd>
        <dt>Booking status</dt><dd><?= $esc($statusLabel($ticket['status'] ?? '')) ?></dd>
        <dt>Payment</dt><dd><?= $esc($statusLabel($ticket['payment_status'] ?? 'unpaid')) ?></dd>
      </dl>
    <?php elseif (is_array($limited)): ?>
      <?php $seats = $limited['seats'] ?? []; if (!is_array($seats)) $seats = [$seats]; ?>
      <dl>
        <dt>Reference</dt><dd><?= $esc($limited['ref'] ?? $ref) ?></dd>
        <dt>Route</dt><dd><?= $esc($limited['route'] ?? '') ?></dd>
        <dt>Trip</dt><dd><?= $esc($limited['trip_id'] ?? '') ?> · <?= $esc($limited['trip_date'] ?? '') ?> <?= $esc($limited['departure'] ?? '') ?></dd>
        <dt>Seats</dt><dd><?= count($seats) ?> seat(s): <?= $esc(implode(', ', array_map('strval', $seats))) ?></dd>
        <dt>Booking status</dt><dd><?= $esc($statusLabel($limited['status'] ?? '')) ?></dd>
        <dt>Payment</dt><dd><?= $esc($statusLabel($limited['payment_status'] ?? 'unpaid')) ?></dd>
      </dl>
      <p class="note">Limited verification (reference only). Open the full QR link with its token to see passenger detail.</p>
    <?php endif; ?>
  <?php endif; ?>
  <p class="note"><a href="index.html">Back to booking</a> · <a href="customer.php">Customer sign in</a></p>
</main>
</body>
</html>
