<?php
/**
 * Platform Admin - Review Management (reviews.php)
 *
 * View / search / filter all customer reviews; approve, reject,
 * edit and delete. Only approved reviews appear on the landing page.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/platform-admin.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/includes/reviews.php';

require_permission('reviews_manage');

// ── Mutations (POST → flash → redirect) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = trim($_POST['op'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $back = 'reviews.php?' . http_build_query(array_filter(['q' => trim($_GET['q'] ?? ''), 'status' => trim($_GET['status'] ?? ''), 'rating' => trim($_GET['rating'] ?? '')]));
    if ($op === 'approve' || $op === 'reject') {
        $res = review_admin_set_status($id, $op === 'approve' ? 'approved' : 'rejected');
        $_SESSION['flash_type'] = $res['success'] ? 'success' : 'error';
        $_SESSION['flash_msg']  = $res['success'] ? ('Review #' . $id . ' ' . ($op === 'approve' ? 'approved — now live on the landing page.' : 'rejected — hidden from the landing page.')) : ($res['error'] ?? 'Action failed.');
    } elseif ($op === 'edit') {
        $res = review_admin_update($id, $_POST['rating'] ?? 0, $_POST['title'] ?? '', $_POST['message'] ?? '', $_POST['role'] ?? '');
        $_SESSION['flash_type'] = $res['success'] ? 'success' : 'error';
        $_SESSION['flash_msg']  = $res['success'] ? ('Review #' . $id . ' updated.') : ($res['error'] ?? 'Update failed.');
    } elseif ($op === 'delete') {
        $res = review_admin_delete($id);
        $_SESSION['flash_type'] = $res['success'] ? 'success' : 'error';
        $_SESSION['flash_msg']  = $res['success'] ? ('Review #' . $id . ' deleted.') : ($res['error'] ?? 'Delete failed.');
    }
    header('Location: ' . $back);
    exit;
}

// ── Filters ─────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
if (!in_array($status, ['', 'pending', 'approved', 'rejected'], true)) $status = '';
$rating = (int)($_GET['rating'] ?? 0);
if ($rating < 0 || $rating > 5) $rating = 0;

$flash_type = $_SESSION['flash_type'] ?? '';
$flash_msg  = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

$counts  = reviews_status_counts();
$summary = reviews_rating_summary();
$list    = reviews_admin_list($search, $status, $rating, 200, 0);

$keep = array_filter(['q' => $search, 'status' => $status, 'rating' => $rating ?: '']);
$qs = http_build_query($keep);
$qs = $qs !== '' ? '&' . $qs : '';

render_head('Reviews & Ratings');
render_sidebar('reviews');
?>
<div class="main">

  <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div>
      <h1>Reviews &amp; Ratings</h1>
      <p>Approve customer reviews to publish them on the landing page · average <?= htmlspecialchars(number_format($summary['average'], 1)) ?>/5 from <?= (int)$summary['count'] ?> approved</p>
    </div>
  </div>

  <?php if ($flash_msg): ?>
  <div class="alert alert-<?= $flash_type === 'success' ? 'success' : 'error' ?>" style="margin-bottom:20px;">
    <?= htmlspecialchars($flash_msg) ?>
  </div>
  <?php endif; ?>

  <!-- Status counts -->
  <div class="card" style="margin-bottom:20px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a href="reviews.php" class="btn <?= $status === '' ? '' : 'btn-ghost' ?> btn-sm">All (<?= (int)$counts['total'] ?>)</a>
      <a href="reviews.php?status=pending<?= htmlspecialchars($qs) ?>" class="btn <?= $status === 'pending' ? '' : 'btn-ghost' ?> btn-sm">⏳ Pending (<?= (int)$counts['pending'] ?>)</a>
      <a href="reviews.php?status=approved" class="btn <?= $status === 'approved' ? '' : 'btn-ghost' ?> btn-sm">✅ Approved (<?= (int)$counts['approved'] ?>)</a>
      <a href="reviews.php?status=rejected" class="btn <?= $status === 'rejected' ? '' : 'btn-ghost' ?> btn-sm">🚫 Rejected (<?= (int)$counts['rejected'] ?>)</a>
    </div>
  </div>

  <!-- Search + rating filter -->
  <div class="card" style="margin-bottom:20px;">
    <form method="GET" action="reviews.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:2;min-width:200px;">
        <label style="display:block;font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:4px;">SEARCH</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email, title or message…" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid #1e293b;background:#0b0f17;color:#fff;font-family:inherit;font-size:.85rem;">
      </div>
      <div>
        <label style="display:block;font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:4px;">RATING</label>
        <select name="rating" style="padding:9px 12px;border-radius:8px;border:1px solid #1e293b;background:#0b0f17;color:#fff;font-family:inherit;font-size:.85rem;">
          <option value="0">All ratings</option>
          <?php for ($s = 5; $s >= 1; $s--): ?>
            <option value="<?= $s ?>"<?= $rating === $s ? ' selected' : '' ?>><?= $s ?> star<?= $s > 1 ? 's' : '' ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
      <button type="submit" class="btn btn-sm">🔍 Filter</button>
      <?php if ($search !== '' || $rating): ?><a href="reviews.php<?= $status !== '' ? '?status=' . urlencode($status) : '' ?>" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
    </form>
  </div>

  <!-- Table -->
  <div class="card">
    <?php if (empty($list)): ?>
      <div style="text-align:center;padding:30px;color:var(--muted);font-size:.875rem;">No reviews match these filters.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Customer</th>
            <th>Rating</th>
            <th>Review</th>
            <th>Status</th>
            <th>Date</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $r): ?>
          <?php $rid = (int)($r['id'] ?? 0); $rst = $r['status'] ?? 'pending'; ?>
          <tr>
            <td>
              <div style="font-weight:600;color:#fff;"><?= htmlspecialchars($r['customer_name'] ?? 'Customer') ?></div>
              <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($r['customer_email'] ?? '') ?></div>
              <?php if (!empty($r['customer_role'])): ?><div style="font-size:.72rem;color:#818cf8;"><?= htmlspecialchars($r['customer_role']) ?></div><?php endif; ?>
            </td>
            <td style="color:#f59e0b;font-weight:800;white-space:nowrap;"><?= str_repeat('★', max(0, min(5, (int)($r['rating'] ?? 0)))) ?><span style="color:#475569;"><?= str_repeat('★', 5 - max(0, min(5, (int)($r['rating'] ?? 0)))) ?></span></td>
            <td style="max-width:340px;">
              <?php if (!empty($r['review_title'])): ?><div style="font-weight:700;color:#fff;font-size:.84rem;"><?= htmlspecialchars($r['review_title']) ?></div><?php endif; ?>
              <div style="font-size:.8rem;color:var(--muted);line-height:1.5;"><?= htmlspecialchars(mb_substr($r['review_message'] ?? '', 0, 220)) ?><?= mb_strlen($r['review_message'] ?? '') > 220 ? '…' : '' ?></div>
            </td>
            <td>
              <?php if ($rst === 'approved'): ?><span style="font-size:.72rem;font-weight:800;color:#6ee7b7;background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.4);padding:.2rem .6rem;border-radius:999px;">APPROVED</span>
              <?php elseif ($rst === 'rejected'): ?><span style="font-size:.72rem;font-weight:800;color:#fca5a5;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.4);padding:.2rem .6rem;border-radius:999px;">REJECTED</span>
              <?php else: ?><span style="font-size:.72rem;font-weight:800;color:#fcd34d;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4);padding:.2rem .6rem;border-radius:999px;">PENDING</span><?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:.78rem;white-space:nowrap;"><?= !empty($r['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($r['created_at']))) : '—' ?></td>
            <td>
              <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;">
                <?php if ($rst !== 'approved'): ?>
                <form method="POST" action="reviews.php<?= htmlspecialchars($qs) ?>" style="margin:0;">
                  <input type="hidden" name="op" value="approve"><input type="hidden" name="id" value="<?= $rid ?>">
                  <button type="submit" class="btn btn-sm" title="Approve — shows on landing page">✅ Approve</button>
                </form>
                <?php endif; ?>
                <?php if ($rst !== 'rejected'): ?>
                <form method="POST" action="reviews.php<?= htmlspecialchars($qs) ?>" style="margin:0;">
                  <input type="hidden" name="op" value="reject"><input type="hidden" name="id" value="<?= $rid ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" title="Reject — hides from landing page">🚫 Reject</button>
                </form>
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" onclick='rvAdminEdit(<?= $rid ?>, <?= (int)($r['rating'] ?? 5) ?>, <?= json_encode($r['review_title'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($r['review_message'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($r['customer_role'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✎ Edit</button>
                <form method="POST" action="reviews.php<?= htmlspecialchars($qs) ?>" style="margin:0;" onsubmit="return confirm('Delete review #<?= $rid ?>? This cannot be undone.')">
                  <input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= $rid ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:#fca5a5;" title="Delete permanently">🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Edit modal -->
<div id="rv-admin-edit" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.7);align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#111622;border:1px solid #28334d;border-radius:16px;width:100%;max-width:480px;margin:1rem;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid #1e293b;">
      <strong style="color:#fff;">✎ Edit Review <span id="rv-admin-edit-id-lbl" style="color:#64748b;"></span></strong>
      <button onclick="document.getElementById('rv-admin-edit').style.display='none'" style="background:none;border:none;color:#64748b;font-size:1.3rem;cursor:pointer;">✕</button>
    </div>
    <form method="POST" action="reviews.php<?= htmlspecialchars($qs) ?>" style="padding:1.25rem;">
      <input type="hidden" name="op" value="edit">
      <input type="hidden" name="id" id="rv-admin-edit-id" value="">
      <label style="display:block;font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:4px;">RATING (1–5)</label>
      <select name="rating" id="rv-admin-edit-rating" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid #1e293b;background:#0b0f17;color:#fff;font-family:inherit;margin-bottom:12px;">
        <?php for ($s = 1; $s <= 5; $s++): ?><option value="<?= $s ?>"><?= $s ?> star<?= $s > 1 ? 's' : '' ?></option><?php endfor; ?>
      </select>
      <label style="display:block;font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:4px;">TITLE</label>
      <input type="text" name="title" id="rv-admin-edit-title" maxlength="120" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid #1e293b;background:#0b0f17;color:#fff;font-family:inherit;margin-bottom:12px;">
      <label style="display:block;font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:4px;">MESSAGE</label>
      <textarea name="message" id="rv-admin-edit-message" rows="4" required minlength="10" maxlength="2000" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid #1e293b;background:#0b0f17;color:#fff;font-family:inherit;margin-bottom:12px;"></textarea>
      <label style="display:block;font-size:.72rem;font-weight:700;color:var(--muted);margin-bottom:4px;">ROLE / BUSINESS TYPE</label>
      <input type="text" name="role" id="rv-admin-edit-role" maxlength="120" style="width:100%;padding:9px 12px;border-radius:8px;border:1px solid #1e293b;background:#0b0f17;color:#fff;font-family:inherit;margin-bottom:16px;">
      <button type="submit" class="btn" style="width:100%;">Save Changes</button>
    </form>
  </div>
</div>

<script>
function rvAdminEdit(id, rating, title, message, role) {
  document.getElementById('rv-admin-edit-id').value = id;
  document.getElementById('rv-admin-edit-id-lbl').textContent = '#' + id;
  document.getElementById('rv-admin-edit-rating').value = rating || 5;
  document.getElementById('rv-admin-edit-title').value = title || '';
  document.getElementById('rv-admin-edit-message').value = message || '';
  document.getElementById('rv-admin-edit-role').value = role || '';
  document.getElementById('rv-admin-edit').style.display = 'flex';
}
</script>
</div>
</body>
</html>
