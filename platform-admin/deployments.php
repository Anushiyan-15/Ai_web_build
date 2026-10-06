<?php
/**
 * platform-admin/deployments.php
 * Feature 3: Deployment Logs & Instant Rollback
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('deployments');

$deployments = get_deployment_history();
$action      = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── ACTION: Rollback to Snapshot ───────────────────────────
if ($action === 'rollback' && isset($_POST['slug']) && isset($_POST['snapshot_file'])) {
    if (!has_permission('deployments_rollback') && !has_permission('*')) {
        header('Location: deployments.php?msg=Unauthorized+to+perform+rollback&type=error');
        exit;
    }

    $slug = $_POST['slug'];
    $file = $_POST['snapshot_file'];
    $res  = rollback_site_to_snapshot($slug, $file);

    header('Location: deployments.php?msg=' . urlencode($res['msg']) . '&type=' . ($res['ok'] ? 'success' : 'error'));
    exit;
}

// ─── ACTION: Re-Publish from Order JSON ─────────────────────
if ($action === 'republish' && isset($_GET['order_id'])) {
    $oid = $_GET['order_id'];
    $ord = load_order($oid);

    if ($ord && !empty($ord['slug']) && !empty($ord['html'])) {
        $pubDir = published_root_dir() . '/' . $ord['slug'];
        if (!is_dir($pubDir)) @mkdir($pubDir, 0755, true);

        // Backup existing if any
        if (file_exists($pubDir . '/index.html')) {
            @copy($pubDir . '/index.html', $pubDir . '/index.html.backup-' . date('Ymd-His'));
        }

        // Re-write HTML
        file_put_contents($pubDir . '/index.html', $ord['html']);

        // Re-write admin files if exist
        if (!empty($ord['admin_files']) && is_array($ord['admin_files'])) {
            $admDir = $pubDir . '/admin';
            if (!is_dir($admDir)) @mkdir($admDir, 0755, true);
            foreach ($ord['admin_files'] as $fpath => $fcontent) {
                $dest = $admDir . '/' . ltrim(str_replace(['..', '\\'], '', $fpath), '/');
                $d = dirname($dest);
                if (!is_dir($d)) @mkdir($d, 0755, true);
                @file_put_contents($dest, $fcontent);
            }
        }

        log_admin_action('republish_site', $oid, "Republished site '{$ord['slug']}' from base order JSON snapshot");
        header('Location: deployments.php?msg=Site+successfully+republished+from+order+snapshot&type=success');
        exit;
    } else {
        header('Location: deployments.php?msg=Could+not+find+original+HTML+for+this+order&type=error');
        exit;
    }
}

// Stats
$totalDeploys  = count($deployments);
$liveCount     = 0;
$failedCount   = 0;
$suspendedCount= 0;

foreach ($deployments as $d) {
    if ($d['status'] === 'live') $liveCount++;
    elseif ($d['status'] === 'suspended') $suspendedCount++;
    else $failedCount++;
}

render_head('Deployment Logs & Rollback');
render_sidebar('deployments');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>🚀 Deployment Logs &amp; Rollback</h1>
      <p>Monitor live builds, inspect HTML payloads, and trigger instant 1-click version rollbacks.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="domains.php" class="btn btn-ghost">🔒 Domain &amp; SSL</a>
      <a href="ai-usage.php" class="btn btn-primary">🤖 AI Cost &amp; Tokens</a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= ($_GET['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Deployments KPIs -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="card" style="border-left:4px solid #6366f1;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Total Deployments</div>
      <div style="font-size:1.8rem;font-weight:800;color:#fff;margin-top:6px;"><?= $totalDeploys ?></div>
      <div style="font-size:0.75rem;color:#818cf8;margin-top:4px;">Multi-tenant builds</div>
    </div>
    <div class="card" style="border-left:4px solid #10b981;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Live &amp; Serving</div>
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;margin-top:6px;"><?= $liveCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">200 OK Status</div>
    </div>
    <div class="card" style="border-left:4px solid #f59e0b;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Suspended Builds</div>
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:6px;"><?= $suspendedCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Disabled by policy</div>
    </div>
    <div class="card" style="border-left:4px solid #ef4444;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Failed / Incomplete</div>
      <div style="font-size:1.8rem;font-weight:800;color:#ef4444;margin-top:6px;"><?= $failedCount ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Missing index.html</div>
    </div>
  </div>

  <!-- Deployments Table -->
  <div class="card" style="padding:0;overflow:hidden;margin-bottom:28px;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">📦 Deployed Website Instances</h2>
      <span style="font-size:0.75rem;color:var(--muted);">Automated backup on rollback</span>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Site &amp; Directory</th>
            <th>Stack Architecture</th>
            <th>Payload Size</th>
            <th>Deployment Date</th>
            <th>Status</th>
            <th style="text-align:right;">Rollback / Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($deployments)): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);">No published deployments registered.</td></tr>
          <?php else: ?>
            <?php foreach ($deployments as $d): 
              $isFailed = ($d['status'] === 'failed');
              $isSuspended = ($d['status'] === 'suspended');
            ?>
            <tr>
              <td>
                <span class="code-badge"><?= htmlspecialchars($d['order_id']) ?></span>
              </td>
              <td>
                <div style="font-weight:700;color:#fff;"><?= htmlspecialchars($d['site_name']) ?></div>
                <div style="font-size:0.75rem;color:#22d3ee;font-family:'Fira Code',monospace;margin-top:2px;">
                  published/<?= htmlspecialchars($d['slug']) ?>/
                </div>
              </td>
              <td>
                <?php if ($d['gen_mode'] === 'database'): ?>
                  <span style="font-size:0.75rem;color:#22d3ee;font-weight:700;">🗄️ Full-Stack MySQL</span>
                <?php elseif ($d['gen_mode'] === 'admin'): ?>
                  <span style="font-size:0.75rem;color:#a5b4fc;font-weight:700;">🛠️ PHP Admin Panel</span>
                <?php else: ?>
                  <span style="font-size:0.75rem;color:#94a3b8;font-weight:600;">📄 Static HTML5</span>
                <?php endif; ?>
              </td>
              <td>
                <span style="font-size:0.8rem;color:#cbd5e1;font-family:'Fira Code',monospace;">
                  <?= $d['html_size'] > 0 ? number_format($d['html_size'] / 1024, 1) . ' KB' : '0 KB' ?>
                </span>
              </td>
              <td style="font-size:0.8rem;color:var(--muted);">
                <?= htmlspecialchars(date('M d, Y H:i', strtotime($d['published_at']))) ?>
              </td>
              <td>
                <?php if ($isFailed): ?>
                  <span style="font-size:0.75rem;font-weight:700;color:#ef4444;background:rgba(239,68,68,0.15);padding:3px 8px;border-radius:999px;">❌ Failed</span>
                <?php elseif ($isSuspended): ?>
                  <span style="font-size:0.75rem;font-weight:700;color:#f59e0b;background:rgba(245,158,11,0.15);padding:3px 8px;border-radius:999px;">🚫 Suspended</span>
                <?php else: ?>
                  <span style="font-size:0.75rem;font-weight:700;color:#10b981;background:rgba(16,185,129,0.15);padding:3px 8px;border-radius:999px;">● Live Active</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <div style="display:inline-flex;gap:6px;align-items:center;">
                  
                  <!-- Live Preview Link -->
                  <a href="<?= htmlspecialchars($d['live_url']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="Inspect Live Site">
                    👁️ Test
                  </a>

                  <!-- Re-Publish from Original JSON -->
                  <a href="?action=republish&order_id=<?= urlencode($d['order_id']) ?>" 
                     onclick="return confirm('Republish website <?= htmlspecialchars($d['site_name']) ?> from its stored JSON snapshot?')"
                     class="btn btn-ghost btn-sm" style="color:#818cf8;border-color:rgba(99,102,241,0.3);" title="Re-deploy from backup">
                    ↺ Rebuild
                  </a>

                  <!-- Rollback Trigger Button -->
                  <button type="button" 
                          onclick="openRollbackModal('<?= htmlspecialchars($d['slug'], ENT_QUOTES) ?>', '<?= htmlspecialchars($d['order_id'], ENT_QUOTES) ?>')"
                          class="btn btn-warning btn-sm" title="Rollback to previous snapshot">
                    ⏮️ Rollback
                  </button>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Rollback Modal -->
  <div id="rollbackModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);align-items:center;justify-content:center;z-index:99999;padding:20px;">
    <div style="background:#111622;border:1.5px solid var(--border);border-radius:20px;max-width:540px;width:100%;padding:28px;box-shadow:0 25px 60px rgba(0,0,0,0.8);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h3 style="font-size:1.15rem;font-weight:800;color:#fff;">⏮️ Instant Version Rollback</h3>
        <button onclick="closeRollbackModal()" style="background:none;border:none;color:var(--muted);font-size:1.2rem;cursor:pointer;">✕</button>
      </div>

      <p style="color:var(--muted);font-size:0.85rem;line-height:1.6;margin-bottom:18px;">
        Select an HTML snapshot version to restore to <strong id="rbSlugDisplay" style="color:#22d3ee;"></strong>. 
        Your current live <code style="color:#818cf8;">index.html</code> will be safely backed up automatically.
      </p>

      <form method="POST" action="deployments.php">
        <input type="hidden" name="action" value="rollback">
        <input type="hidden" name="slug" id="rbSlugInput" value="">

        <div class="form-group">
          <label>Select Backup Snapshot to Restore</label>
          <select name="snapshot_file" id="rbSnapshotSelect" required style="padding:10px;">
            <option value="">Scanning stored snapshots…</option>
          </select>
        </div>

        <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:10px;padding:12px;font-size:0.78rem;color:#fcd34d;margin-bottom:20px;">
          ⚠️ <strong>Safety Guard:</strong> This immediately replaces the live front-end page with the selected historic version and writes to the platform audit log.
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" onclick="closeRollbackModal()" class="btn btn-ghost">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm &amp; Rollback Now</button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
function openRollbackModal(slug, orderId) {
  document.getElementById('rbSlugDisplay').textContent = '/published/' + slug + '/';
  document.getElementById('rbSlugInput').value = slug;
  
  const sel = document.getElementById('rbSnapshotSelect');
  sel.innerHTML = '<option value="">Loading available snapshots…</option>';
  const doneLoad = (window.paAjaxLoad ? paAjaxLoad('Loading snapshots…', 'Reading build history', 'radar') : () => {});

  // Fetch snapshots for this order
  fetch('api.php?action=get_snapshots&order_id=' + encodeURIComponent(orderId))
    .then(r => r.json())
    .then(j => {
      doneLoad();
      sel.innerHTML = '';
      if (j.snapshots && j.snapshots.length > 0) {
        j.snapshots.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.filename;
          opt.textContent = s.filename + ' (' + s.mtime + ', ' + s.size_kb + ' KB)';
          sel.appendChild(opt);
        });
      } else {
        const opt = document.createElement('option');
        opt.value = 'default';
        opt.textContent = 'Original initial publication snapshot';
        sel.appendChild(opt);
      }
    })
    .catch(() => {
      doneLoad();
      sel.innerHTML = '<option value="default">Original generation build snapshot</option>';
    });

  document.getElementById('rollbackModal').style.display = 'flex';
}

function closeRollbackModal() {
  document.getElementById('rollbackModal').style.display = 'none';
}
</script>
</div></body></html>
