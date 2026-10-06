<?php
/**
 * platform-admin/ai-usage.php
 * Feature 5: AI Usage & Token Cost Tracking
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_permission('ai_usage');

$stats  = get_ai_usage_stats();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── ACTION: Save Global AI Configuration ───────────────────
if ($action === 'save_ai_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('*') && current_admin_role() !== ROLE_DEVELOPER) {
        header('Location: ai-usage.php?msg=Unauthorized&type=error');
        exit;
    }

    $newModel = trim($_POST['gemini_model'] ?? 'gemini-2.0-flash');
    $appConfigFile = dirname(__DIR__) . '/config/app.php';
    
    if (file_exists($appConfigFile)) {
        $content = file_get_contents($appConfigFile);
        $content = preg_replace("/define\('GEMINI_MODEL',\s*'[^']+'\);/", "define('GEMINI_MODEL',   '{$newModel}');", $content);
        file_put_contents($appConfigFile, $content);
        log_admin_action('ai_model_updated', null, "Changed global primary AI model to {$newModel}");
        header('Location: ai-usage.php?msg=Global+AI+model+updated+to+' . urlencode($newModel) . '&type=success');
        exit;
    }
}

// ─── ACTION: Reset Quota for Tenant ────────────────────────
if ($action === 'reset_quota' && isset($_GET['order_id'])) {
    $oid = $_GET['order_id'];
    $ord = load_order($oid);
    if ($ord) {
        $ord['ai_quota_reset_at'] = date('Y-m-d H:i:s');
        save_order($ord);
        log_admin_action('ai_quota_reset', $oid, "Reset AI token quota for tenant {$oid}");
        header('Location: ai-usage.php?msg=Quota+counter+reset+for+tenant&type=success');
        exit;
    }
}

$currentModel = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-2.0-flash';

render_head('AI Usage & Cost Tracking');
render_sidebar('ai_usage');
?>

<main class="main">
  <div class="page-header">
    <div>
      <h1>🤖 AI Usage &amp; Cost Tracking</h1>
      <p>Monitor token consumption, prompt volume, and compute expenditure per customer and AI model.</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="deployments.php" class="btn btn-ghost">🚀 Deployments</a>
      <a href="billing.php" class="btn btn-warning">💳 Subscriptions</a>
    </div>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= ($_GET['type'] ?? '') === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- AI KPI Cards -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="card" style="border-left:4px solid #6366f1;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Total AI Generations</div>
      <div style="font-size:1.8rem;font-weight:800;color:#fff;margin-top:6px;"><?= number_format($stats['total_generations']) ?></div>
      <div style="font-size:0.75rem;color:#818cf8;margin-top:4px;">Concepts, Admin &amp; Features</div>
    </div>
    <div class="card" style="border-left:4px solid #10b981;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Total Est. Tokens</div>
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;margin-top:6px;"><?= number_format($stats['total_tokens']) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Prompt &amp; Completion tokens</div>
    </div>
    <div class="card" style="border-left:4px solid #f59e0b;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Est. AI Infrastructure Spend</div>
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;margin-top:6px;">$<?= number_format($stats['total_cost_usd'], 4) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Total Google/Puter API cost</div>
    </div>
    <div class="card" style="border-left:4px solid #06b6d4;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;">Active Primary Model</div>
      <div style="font-size:1.3rem;font-weight:800;color:#22d3ee;margin-top:6px;word-break:break-all;"><?= htmlspecialchars($currentModel) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;">Google Gemini Flash 2.x</div>
    </div>
  </div>

  <!-- AI Model Cost Reference & Settings Grid -->
  <div class="grid-2" style="margin-bottom:28px;">
    
    <!-- Model Pricing Reference -->
    <div class="card">
      <h3 style="font-size:0.95rem;font-weight:800;color:#fff;margin-bottom:12px;">📊 AI Provider Rates Reference</h3>
      <div style="font-size:0.8rem;color:var(--muted);line-height:1.7;">
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
          <span style="color:#fff;font-weight:600;">Google Gemini 2.0 Flash</span>
          <span style="color:#10b981;font-weight:700;">$0.10 / 1,000,000 Tokens</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
          <span style="color:#fff;font-weight:600;">DeepSeek V3 (via Puter.js)</span>
          <span style="color:#22d3ee;font-weight:700;">Included / Free Developer Tier</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
          <span style="color:#fff;font-weight:600;">GPT-4o Mini (via Puter.js)</span>
          <span style="color:#a5b4fc;font-weight:700;">$0.15 / 1,000,000 Tokens</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;">
          <span style="color:#fff;font-weight:600;">Claude 3.5 Sonnet (via Puter.js)</span>
          <span style="color:#f59e0b;font-weight:700;">$3.00 / 1,000,000 Tokens</span>
        </div>
      </div>
    </div>

    <!-- Switch Global Default Model -->
    <div class="card">
      <h3 style="font-size:0.95rem;font-weight:800;color:#fff;margin-bottom:12px;">⚙️ Global Backend Engine</h3>
      <p style="font-size:0.8rem;color:var(--muted);line-height:1.6;margin-bottom:16px;">
        Set the Google Gemini model used by the backend PHP proxy (<code>api/generate.php</code>) when generating sites and admin modules.
      </p>

      <form method="POST" action="ai-usage.php">
        <input type="hidden" name="action" value="save_ai_config">
        <div class="form-group">
          <label>Backend Model Identifier</label>
          <select name="gemini_model" style="padding:10px;">
            <option value="gemini-2.0-flash" <?= $currentModel === 'gemini-2.0-flash' ? 'selected' : '' ?>>gemini-2.0-flash (Recommended · Fast &amp; Cheap)</option>
            <option value="gemini-2.5-flash" <?= $currentModel === 'gemini-2.5-flash' ? 'selected' : '' ?>>gemini-2.5-flash (Next Gen)</option>
            <option value="gemini-1.5-pro"   <?= $currentModel === 'gemini-1.5-pro'   ? 'selected' : '' ?>>gemini-1.5-pro (High Reasoning)</option>
          </select>
        </div>
        <div style="text-align:right;">
          <button type="submit" class="btn btn-primary btn-sm">Update Engine</button>
        </div>
      </form>
    </div>

  </div>

  <!-- Tenant AI Usage Breakdown -->
  <div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <h2 style="font-size:1.05rem;font-weight:800;color:#fff;">👥 AI Consumption Breakdown per Tenant</h2>
      <span style="font-size:0.75rem;color:var(--muted);"><?= count($stats['tenants']) ?> Active Tenants</span>
    </div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Tenant / Site</th>
            <th>Generations Breakdown</th>
            <th>Est. Tokens</th>
            <th>Est. Cost</th>
            <th>Tier Quota Utilization</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($stats['tenants'])): ?>
            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);">No tenant usage logged yet.</td></tr>
          <?php else: ?>
            <?php foreach ($stats['tenants'] as $t): 
              $pct = min(100, round(($t['tokens'] / $t['tier_limit']) * 100));
              $barColor = $pct > 80 ? '#ef4444' : ($pct > 50 ? '#f59e0b' : '#10b981');
            ?>
            <tr>
              <td>
                <div style="font-weight:700;color:#fff;"><?= htmlspecialchars($t['site_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($t['client_email']) ?></div>
                <span class="code-badge" style="font-size:0.68rem;"><?= htmlspecialchars($t['order_id']) ?></span>
              </td>
              <td>
                <div style="font-size:0.8rem;color:#e2e8f0;font-weight:600;">
                  <?= $t['generations'] ?> Total Generations
                </div>
                <div style="font-size:0.72rem;color:var(--muted);margin-top:2px;">
                  3 Concepts &nbsp;·&nbsp; <?= $t['admin_files'] ?> Admin Files &nbsp;·&nbsp; <?= $t['feature_addons'] ?> Feature Addons
                </div>
              </td>
              <td>
                <div style="font-size:0.85rem;font-weight:700;color:#10b981;font-family:'Fira Code',monospace;">
                  <?= number_format($t['tokens']) ?>
                </div>
                <div style="font-size:0.7rem;color:var(--muted);">Tokens processed</div>
              </td>
              <td>
                <div style="font-size:0.85rem;font-weight:700;color:#f59e0b;font-family:'Fira Code',monospace;">
                  $<?= number_format($t['cost_usd'], 4) ?>
                </div>
                <div style="font-size:0.7rem;color:var(--muted);">USD compute</div>
              </td>
              <td style="min-width:180px;">
                <div style="display:flex;justify-content:space-between;font-size:0.72rem;color:var(--muted);margin-bottom:4px;">
                  <span><?= $pct ?>% used</span>
                  <span><?= number_format($t['tokens']) ?> / <?= number_format($t['tier_limit']) ?></span>
                </div>
                <div style="width:100%;height:6px;background:#0d1117;border-radius:999px;overflow:hidden;border:1px solid #1e293b;">
                  <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>;border-radius:999px;"></div>
                </div>
              </td>
              <td style="text-align:right;">
                <a href="?action=reset_quota&order_id=<?= urlencode($t['order_id']) ?>" 
                   onclick="return confirm('Reset quota usage stats for <?= htmlspecialchars($t['site_name']) ?>?')"
                   class="btn btn-ghost btn-sm" title="Reset monthly AI quota">
                  ↺ Reset Quota
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div></body></html>
