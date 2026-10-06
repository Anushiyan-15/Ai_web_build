<?php
/**
 * platform-admin/tickets.php
 * Feature 6: Support Tickets & Contact Inquiries
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('tickets');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── ACTION: Update Status / Add Staff Reply ────────────────
if ($action === 'reply_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid    = $_POST['ticket_id'] ?? '';
    $status = $_POST['status'] ?? 'open';
    $reply  = trim($_POST['reply_text'] ?? '');

    update_ticket_status($tid, $status, $reply ?: null);
    log_admin_action('ticket_reply', $tid, "Updated ticket {$tid} to {$status}" . ($reply ? ' with staff reply' : ''));
    header('Location: tickets.php?msg=Ticket+updated+successfully&type=success');
    exit;
}

$tickets = get_support_tickets();
$filter  = $_GET['filter'] ?? 'all';

$openCount     = 0;
$inProgCount   = 0;
$resolvedCount = 0;

foreach ($tickets as $t) {
    $st = strtolower($t['status'] ?? 'open');
    if ($st === 'resolved') $resolvedCount++;
    elseif ($st === 'in_progress') $inProgCount++;
    else $openCount++;
}

// Filter tickets
$filtered = array_filter($tickets, function($t) use ($filter) {
    $st = strtolower($t['status'] ?? 'open');
    if ($filter === 'open' && $st !== 'open') return false;
    if ($filter === 'in_progress' && $st !== 'in_progress') return false;
    if ($filter === 'resolved' && $st !== 'resolved') return false;
    return true;
});

render_head('Support Tickets & Inquiries');
render_sidebar('tickets');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>🎧 Support Tickets &amp; Client Inquiries</h1>
      <p>Manage customer inquiries, quote requests, and technical support messages in one place.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="notifications.php" class="btn btn-primary">📢 Broadcast Announcement</a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= ($_GET['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Ticket Metrics -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="card" style="border-left:4px solid #6366f1;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Total Inquiries</div>
      <div style="font-size:1.8rem;font-weight:800;color:#fff;margin-top:6px;"><?= count($tickets) ?></div>
      <div style="font-size:0.75rem;color:#818cf8;margin-top:4px;">Across all channels</div>
    </div>
    <div class="card" style="border-left:4px solid #ef4444;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Awaiting Response</div>
      <div style="font-size:1.8rem;font-weight:800;color:#ef4444;margin-top:6px;"><?= $openCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Unresolved issues</div>
    </div>
    <div class="card" style="border-left:4px solid #f59e0b;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">In Progress</div>
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:6px;"><?= $inProgCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Being addressed</div>
    </div>
    <div class="card" style="border-left:4px solid #10b981;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Resolved</div>
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;margin-top:6px;"><?= $resolvedCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Closed inquiries</div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="card" style="margin-bottom:20px;padding:14px 20px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-ghost' ?>">All (<?= count($tickets) ?>)</a>
      <a href="?filter=open" class="btn btn-sm <?= $filter === 'open' ? 'btn-primary' : 'btn-ghost' ?>">Open (<?= $openCount ?>)</a>
      <a href="?filter=in_progress" class="btn btn-sm <?= $filter === 'in_progress' ? 'btn-primary' : 'btn-ghost' ?>">In Progress (<?= $inProgCount ?>)</a>
      <a href="?filter=resolved" class="btn btn-sm <?= $filter === 'resolved' ? 'btn-primary' : 'btn-ghost' ?>">Resolved (<?= $resolvedCount ?>)</a>
    </div>
  </div>

  <!-- Tickets List -->
  <div style="display:flex;flex-direction:column;gap:16px;">
    <?php if (empty($filtered)): ?>
      <div class="card" style="text-align:center;padding:48px;color:var(--muted);">
        <div style="font-size:2.5rem;margin-bottom:8px;">📬</div>
        No tickets matching this filter.
      </div>
    <?php else: ?>
      <?php foreach ($filtered as $t): 
        $st = strtolower($t['status'] ?? 'open');
        $badgeClass = ($st === 'resolved') ? 'btn-success' : (($st === 'in_progress') ? 'btn-warning' : 'btn-danger');
      ?>
      <div class="card" style="border-left:4px solid <?= $st === 'resolved' ? '#10b981' : ($st === 'in_progress' ? '#f59e0b' : '#ef4444') ?>;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
          <div>
            <div style="display:flex;align-items:center;gap:10px;">
              <span class="code-badge"><?= htmlspecialchars($t['id']) ?></span>
              <h3 style="font-size:1.05rem;font-weight:800;color:#fff;"><?= htmlspecialchars($t['subject']) ?></h3>
            </div>
            <div style="font-size:0.8rem;color:var(--muted);margin-top:4px;">
              From: <strong style="color:#e2e8f0;"><?= htmlspecialchars($t['name']) ?></strong> 
              (<?= htmlspecialchars($t['email']) ?>) &nbsp;·&nbsp;
              Date: <?= htmlspecialchars($t['created_at']) ?>
            </div>
          </div>
          <div>
            <span class="btn btn-sm <?= $badgeClass ?>" style="cursor:default;">
              <?= ucfirst(str_replace('_', ' ', $st)) ?>
            </span>
          </div>
        </div>

        <!-- Ticket Message Body -->
        <div style="background:#0d1117;border:1px solid var(--border);border-radius:10px;padding:16px;font-size:0.88rem;color:#cbd5e1;line-height:1.6;margin-bottom:16px;white-space:pre-wrap;">
<?= htmlspecialchars($t['message']) ?>
        </div>

        <!-- Previous Staff Notes / Replies -->
        <?php if (!empty($t['replies'])): ?>
          <div style="margin-bottom:16px;">
            <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:8px;">Internal Staff Notes &amp; Responses</div>
            <?php foreach ($t['replies'] as $rep): ?>
              <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.25);border-radius:8px;padding:10px 14px;margin-bottom:6px;font-size:0.82rem;">
                <div style="display:flex;justify-content:space-between;color:#a5b4fc;font-size:0.72rem;font-weight:700;margin-bottom:4px;">
                  <span>👤 <?= htmlspecialchars($rep['by'] ?? 'Staff') ?></span>
                  <span><?= htmlspecialchars($rep['time'] ?? '') ?></span>
                </div>
                <div style="color:#e2e8f0;"><?= htmlspecialchars($rep['msg'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Quick Reply / Status Update Form -->
        <form method="POST" action="tickets.php" style="background:#090d14;border:1px solid var(--border);border-radius:10px;padding:14px;">
          <input type="hidden" name="action" value="reply_ticket">
          <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($t['id']) ?>">

          <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
            <div style="flex:1;min-width:260px;">
              <input type="text" name="reply_text" placeholder="Add staff note or internal reply..." style="padding:8px 12px;font-size:0.82rem;">
            </div>
            <div style="width:160px;">
              <select name="status" style="padding:8px 12px;font-size:0.82rem;">
                <option value="open" <?= $st === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="in_progress" <?= $st === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="resolved" <?= $st === 'resolved' ? 'selected' : '' ?>>Resolved</option>
              </select>
            </div>
            <div>
              <button type="submit" class="btn btn-primary btn-sm">Update Ticket</button>
              <?php if (!empty($t['email']) && $t['email'] !== '—'): ?>
                <a href="mailto:<?= htmlspecialchars($t['email']) ?>?subject=Re:%20<?= urlencode($t['subject']) ?>" class="btn btn-ghost btn-sm" title="Email customer directly">
                  📧 Email Client
                </a>
              <?php endif; ?>
            </div>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>
</div></body></html>
