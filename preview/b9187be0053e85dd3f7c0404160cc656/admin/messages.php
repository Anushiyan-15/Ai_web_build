<?php
require_once __DIR__ . '/layout.php';

$inboxFile = __DIR__ . '/data_inquiries.json';
$inbox = file_exists($inboxFile) ? (json_decode(@file_get_contents($inboxFile), true) ?: []) : [];
if (!is_array($inbox)) $inbox = [];

$notice = '';
// Actions: mark read / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $act = $_POST['op'] ?? '';
    $id  = (string)($_POST['id'] ?? '');
    if ($act === 'read' || $act === 'unread' || $act === 'delete') {
        foreach ($inbox as $k => $row) {
            if ((string)($row['id'] ?? '') === $id) {
                if ($act === 'delete') unset($inbox[$k]);
                else $inbox[$k]['read'] = ($act === 'read');
                break;
            }
        }
        $inbox = array_values($inbox);
        @file_put_contents($inboxFile, json_encode($inbox, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $notice = $act === 'delete' ? 'Message deleted.' : 'Message updated.';
    } elseif ($act === 'mark_all_read') {
        foreach ($inbox as $k => $row) $inbox[$k]['read'] = true;
        @file_put_contents($inboxFile, json_encode($inbox, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $notice = 'All messages marked as read.';
    }
}

$unread = 0;
foreach ($inbox as $r) { if (empty($r['read'])) $unread++; }
renderAdminHeader('messages', 'Customer Inbox');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">✉️ Customer Inbox <?= $unread > 0 ? '<span class="badge badge-green">' . $unread . ' new</span>' : '' ?></h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Every contact-form message from your live website lands here. Reply straight from your email.</p>
  </div>
  <?php if (!empty($inbox)): ?>
  <form method="POST"><input type="hidden" name="op" value="mark_all_read">
    <?= admin_csrf_field() ?>
    <button type="submit" class="btn btn-ghost">✓ Mark all read</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($notice): ?>
  <div style="background:rgba(16,185,129,.15);border:1px solid #10b981;color:#34d399;padding:.9rem 1.2rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem">✅ <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<?php if (empty($inbox)): ?>
  <div class="card" style="text-align:center;padding:3rem 1.5rem">
    <div style="font-size:2.5rem;margin-bottom:.75rem">📭</div>
    <h3 style="color:#fff;font-weight:800;margin-bottom:.4rem">No messages yet</h3>
    <p style="color:#94a3b8;font-size:.88rem">When visitors submit your website contact form, their messages appear here instantly.</p>
  </div>
<?php else: ?>
  <?php foreach ($inbox as $row):
    $rid = htmlspecialchars((string)($row['id'] ?? ''));
    $rname = htmlspecialchars($row['name'] ?? 'Visitor');
    $remail = htmlspecialchars($row['email'] ?? '');
    $rphone = htmlspecialchars($row['phone'] ?? '—');
    $rmsg = nl2br(htmlspecialchars($row['message'] ?? ''));
    $rdate = htmlspecialchars($row['date'] ?? '');
    $isNew = empty($row['read']);
  ?>
  <div class="card" style="<?= $isNew ? 'border-color:#f59e0b;background:rgba(245,158,11,.05)' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem">
      <div style="display:flex;align-items:center;gap:.7rem">
        <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff"><?= htmlspecialchars(mb_strtoupper(mb_substr($rname, 0, 1))) ?></div>
        <div>
          <strong style="color:#fff"><?= $rname ?></strong>
          <?= $isNew ? ' <span class="badge" style="background:#f59e0b;color:#000">NEW</span>' : '' ?>
          <div style="font-size:.78rem;color:#64748b"><?= $remail ?> · <?= $rphone ?> · <?= $rdate ?></div>
        </div>
      </div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <a class="btn btn-success" style="padding:.45rem .9rem;font-size:.78rem" href="mailto:<?= $remail ?>?subject=<?= rawurlencode('Re: Your enquiry') ?>">↩ Reply</a>
        <form method="POST" style="display:inline">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="id" value="<?= $rid ?>">
          <?php if ($isNew): ?>
            <button type="submit" name="op" value="read" class="btn btn-ghost" style="padding:.45rem .9rem;font-size:.78rem">✓ Read</button>
          <?php else: ?>
            <button type="submit" name="op" value="unread" class="btn btn-ghost" style="padding:.45rem .9rem;font-size:.78rem">Unread</button>
          <?php endif; ?>
          <button type="submit" name="op" value="delete" class="btn btn-danger" style="padding:.45rem .9rem;font-size:.78rem" onclick="return confirm('Delete this message?')">Delete</button>
        </form>
      </div>
    </div>
    <div style="background:#0b0f17;border:1px solid #1e293b;border-radius:10px;padding:1rem;font-size:.88rem;color:#cbd5e1;line-height:1.6;white-space:pre-wrap;"><?= $rmsg ?></div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php renderAdminFooter(); ?>