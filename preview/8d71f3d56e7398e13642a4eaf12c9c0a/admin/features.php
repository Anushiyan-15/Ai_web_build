<?php
require_once __DIR__ . '/layout.php';
renderAdminHeader('🤖 Add Functions (AI Feature Builder)', 'features', '$safeSite');
// SSO bridge: this page already verified the admin session (requireAdmin).
// Mint a 5-minute HMAC handoff so the JS can trade it for a Node Bearer
// token — no password handling, no secrets in markup beyond the window.
$__bridgeTs = (string)time();
$__bridgeSig = '';
$__bridgeSlug = basename(dirname(__FILE__));
try {
    $__secFile = dirname(__FILE__, 3) . '/storage/sites/' . preg_replace('/[^a-z0-9\-]/i', '', $__bridgeSlug) . '/node_secret';
    if (is_file($__secFile)) {
        $__secret = trim((string)@file_get_contents($__secFile));
        if (strlen($__secret) >= 32) $__bridgeSig = hash_hmac('sha256', $__bridgeTs . ':' . $__bridgeSlug, $__secret);
    }
} catch (Throwable $__e) {}
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
  <div>
    <div style="display:inline-block;padding:0.25rem 0.75rem;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.4);border-radius:999px;font-size:0.72rem;font-weight:800;color:#c7d2fe;margin-bottom:0.4rem;">
      ✦ AI-FLOWCRAFT 28-SKILL FULLSTACK ENGINE &amp; TASTESKILL UI
    </div>
    <h1 class="page-title">Add Functions &amp; Full-Stack Module Builder 🤖</h1>
    <p style="color:#94a3b8;font-size:.88rem">Powered by AI-FlowCraft (Skills 1-28: Data Model, MySQL DDL, REST API &amp; TDD) and TasteSkill UI Design for CsrfCo.</p>
  </div>
  <?php if ('PREVIEW-8d71f3d5'): ?>
  <a href="../../site-manager.php?order_id=PREVIEW-8d71f3d5#ai-adder" target="_blank" class="btn btn-primary">
    <span>✨ Open Advanced AI Studio &rarr;</span>
  </a>
  <?php endif; ?>
</div>

<div class="card" style="border-color:rgba(99,102,241,0.35);background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(15,23,42,0.8));">
  <div style="font-size:.82rem;font-weight:700;color:#cbd5e1;margin-bottom:.5rem">Quick Presets (Click to Load):</div>
  <div style="margin-bottom:1rem">
    <button type="button" class="btn btn-ghost" style="padding:.4rem .8rem;font-size:.78rem;margin:0 .3rem .3rem 0" onclick="usePreset('Add an Appointment Booking system with Customer Name, Phone, Email, Service Type, Booking Date, Time Slot, and Status.')">📅 Appointment Booking</button>
    <button type="button" class="btn btn-ghost" style="padding:.4rem .8rem;font-size:.78rem;margin:0 .3rem .3rem 0" onclick="usePreset('Add an Employee / Team Directory with Full Name, Role / Position, Phone, Bio, and Photo.')">👥 Staff / Team Management</button>
    <button type="button" class="btn btn-ghost" style="padding:.4rem .8rem;font-size:.78rem;margin:0 .3rem .3rem 0" onclick="usePreset('Add a Customer Reviews &amp; Testimonials module with Client Name, Company, Rating (1-5 stars), Comment, and Status.')">⭐ Reviews &amp; Ratings</button>
    <button type="button" class="btn btn-ghost" style="padding:.4rem .8rem;font-size:.78rem;margin:0 .3rem .3rem 0" onclick="usePreset('Add a Blog &amp; News Publishing section with Article Title, Featured Image, Category, Content Body, and Publish Date.')">📰 Blog / News Articles</button>
  </div>

  <div class="field">
    <label class="lbl">Describe The Function / Business Process to Add</label>
    <textarea id="feature-req-input" rows="4" class="inp" placeholder="e.g. Add an Appointment Booking system with Customer Name, Phone, Email, Date, Time, and Status..."></textarea>
  </div>

  <div class="field">
    <label class="lbl">Record fields (comma-separated, optional — e.g. holder, tier, expires)</label>
    <input id="feature-fields-input" class="inp" placeholder="title, details">
  </div>

  <div id="feat-result" style="display:none;border:1.5px solid #334155;border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.84rem;color:#e2e8f0"></div>

  <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
    <button type="button" class="btn btn-ghost" id="feat-analyze-btn" onclick="analyzeFeatures()">
      <span>🔍 Analyze Missing Functions</span>
    </button>
    <button type="button" class="btn btn-success" onclick="installFeature()">
      <span>✨ Generate &amp; Install Module</span>
    </button>
    <?php if ('PREVIEW-8d71f3d5'): ?>
    <a href="../../site-manager.php?order_id=PREVIEW-8d71f3d5#ai-adder" target="_blank" style="color:#a5b4fc;font-size:.82rem;text-decoration:none">
      Or use your Website Manager AI Feature Adder &rarr;
    </a>
    <?php endif; ?>
  </div>
</div>

<div id="feat-findings" style="margin-top:1rem"></div>

<div class="card">
  <h3 style="color:#fff;font-size:1.05rem;margin-bottom:1rem">📦 Installed Business Modules</h3>
  <table>
    <thead><tr><th>Module Name</th><th>Type</th><th>Storage</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <tr><td><strong>Content Management</strong></td><td>Core CMS</td><td>JSON Storage</td><td><span class="badge badge-green">Active</span></td><td><a href="content.php" style="color:#818cf8;font-weight:700;text-decoration:none">Edit</a></td></tr>
      <tr><td><strong>Dynamic Records Manager</strong></td><td>Entities CRUD</td><td>JSON Storage</td><td><span class="badge badge-green">Active</span></td><td><a href="manage.php" style="color:#818cf8;font-weight:700;text-decoration:none">Edit</a></td></tr>
      <tr><td><strong>Admin Authentication</strong></td><td>Security &amp; Passwords</td><td>Bcrypt Hash</td><td><span class="badge badge-green">Active</span></td><td><a href="settings.php" style="color:#818cf8;font-weight:700;text-decoration:none">Edit</a></td></tr>
    </tbody>
  </table>
</div>

<script>
var WC_NODE_API = 'http://localhost:3100';
var WC_SLUG = 'csrfco';
var WC_BRIDGE = { ts: '<?= $__bridgeTs ?>', sig: '<?= $__bridgeSig ?>' };
// Trade the PHP-minted HMAC handoff for a Node Bearer token (5-min window).
// Falls back to manual Node login only if the bridge is unavailable.
async function wcNodeToken() {
  let t = null;
  try { t = sessionStorage.getItem('wc_node_token'); } catch (e) {}
  if (t) return t;
  try {
    const r = await fetch(WC_NODE_API + '/node-api/' + encodeURIComponent(WC_SLUG) + '/auth/bridge', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ts: WC_BRIDGE.ts, sig: WC_BRIDGE.sig })
    });
    const j = await r.json();
    if (j && j.success && j.data && j.data.token) {
      try { sessionStorage.setItem('wc_node_token', j.data.token); } catch (e) {}
      return j.data.token;
    }
  } catch (e) {}
  return null;
}
function usePreset(t) {
  const el = document.getElementById('feature-req-input');
  if (el) { el.value = t; el.focus(); }
}
function featMsg(html, ok) {
  let box = document.getElementById('feat-result');
  if (!box) return;
  box.style.display = 'block';
  box.style.borderColor = ok ? '#10b981' : '#ef4444';
  box.innerHTML = html;
}
function escH(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

// Step 1: Analyze — the Node engine inspects THIS business (manifest +
// live site HTML) and reports genuinely missing functions with safe plans.
async function analyzeFeatures() {
  const out = document.getElementById('feat-findings');
  const btn = document.getElementById('feat-analyze-btn');
  out.innerHTML = '<p style="color:#94a3b8">🔍 Analyzing your website vs business requirements…</p>';
  if (btn) btn.disabled = true;
  try {
    let siteHtml = '';
    try {
      const r = await fetch('../index.html');
      if (r.ok) siteHtml = (await r.text()).slice(0, 120000);
    } catch (e) {}
    const res = await fetch(WC_NODE_API + '/node-api/' + encodeURIComponent(WC_SLUG) + '/analyze', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ html: siteHtml, business: 'CsrfCo' })
    });
    const j = await res.json();
    if (!j || !j.success) throw new Error((j && j.error) || 'analyze failed');
    const f = j.data.findings || [];
    if (!f.length) {
      out.innerHTML = '<div class="card" style="border-color:#10b981">✅ No missing functions found — frontend, backend and admin are in sync.</div>';
    } else {
      out.innerHTML = '<div class="card"><h3 style="color:#fff;margin-bottom:.75rem">🔍 Missing / incomplete functions (' + f.length + ')</h3>'
        + f.map(function (x, i) {
          return '<div style="padding:.6rem 0;border-bottom:1px solid #1e293b;font-size:.84rem">'
            + '<strong style="color:#fff">' + escH(x.area) + '</strong> '
            + '<span class="badge" style="background:' + (x.severity === 'high' ? '#7f1d1d' : '#1e1b4b') + ';color:' + (x.severity === 'high' ? '#fca5a5' : '#a5b4fc') + '">' + escH(x.severity) + '</span><br>'
            + '<span style="color:#94a3b8">' + escH(x.detail) + '</span>'
            + (x.plan && x.plan !== 'manual'
              ? '<br><button type="button" class="btn btn-ghost" style="margin-top:.4rem;padding:.35rem .8rem;font-size:.76rem" onclick="applyPlan(' + i + ')">⚙️ Auto-fix: ' + escH(x.plan) + '</button>'
              : '<br><span style="color:#64748b;font-size:.76rem">Needs content decisions — describe it below and install as a module.</span>')
            + '</div>';
        }).join('') + '</div>';
      window.__featFindings = f;
    }
    featMsg('Analysis complete: ' + f.length + ' finding(s).', true);
  } catch (e) {
    out.innerHTML = '<div class="card" style="border-color:#ef4444">⚠️ Analyzer unreachable — is the Node backend running? '
      + '(<code>node server/server.js</code>). Your existing admin functions keep working; nothing was faked.</div>';
    featMsg('Node backend offline — start it to use AI analysis.', false);
  } finally {
    if (btn) btn.disabled = false;
  }
}

// Step 2: Install a real module (entity + data file + audit + backup).
// Modules appear in Manage Data immediately via live-module discovery.
async function installFeature() {
  const req = document.getElementById('feature-req-input').value.trim();
  const fieldsRaw = (document.getElementById('feature-fields-input') || {}).value || '';
  if (!req) { featMsg('⚠️ Describe what you want to add first (or run Analyze).', false); return; }
  if (!confirm('Install "' + req.slice(0, 60) + '" as a new admin module? A backup is taken first.')) return;
  featMsg('⏳ Installing module… (backup first, then files)', true);
  try {
    const token = await wcNodeToken();
    const res = await fetch(WC_NODE_API + '/node-api/' + encodeURIComponent(WC_SLUG) + '/features/apply', {
      method: 'POST', headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: 'Bearer ' + token } : {}) },
      body: JSON.stringify({ module: { name: req.slice(0, 60),
        fields: fieldsRaw.split(',').map(s => s.trim()).filter(Boolean).slice(0, 12) } })
    });
    const j = await res.json();
    if (!j || !j.success) throw new Error((j && j.error) || 'install failed');
    featMsg('✅ Installed <strong>' + escH(j.data.name) + '</strong> — open <a href="manage.php?entity=' + encodeURIComponent(j.data.id) + '" style="color:#818cf8">Manage Data</a> to use it.', true);
    loadInstalledFeatures();
  } catch (e) {
    featMsg('❌ Install failed: ' + escH(e.message) + (String(e.message).includes('Failed to fetch') ? ' — is the Node backend running?' : ''), false);
  }
}

async function loadInstalledFeatures() {
  try {
    const token = await wcNodeToken();
    const res = await fetch(WC_NODE_API + '/node-api/' + encodeURIComponent(WC_SLUG) + '/features', {
      headers: token ? { Authorization: 'Bearer ' + token } : {}
    });
    const j = await res.json();
    const tb = document.querySelector('#features-table tbody');
    if (tb && j && j.success) {
      (j.data || []).forEach(function (m) {
        if (document.getElementById('featmod-' + m.id)) return;
        const tr = document.createElement('tr');
        tr.id = 'featmod-' + m.id;
        tr.innerHTML = '<td><strong>' + escH(m.name) + '</strong></td><td>AI Module</td><td>JSON Storage</td>'
          + '<td><span class="badge badge-green">Active</span></td>'
          + '<td><a href="manage.php?entity=' + encodeURIComponent(m.id) + '" style="color:#818cf8;font-weight:700;text-decoration:none">Open</a></td>';
        tb.appendChild(tr);
      });
    }
  } catch (e) {}
}
loadInstalledFeatures();
</script>

<?php renderAdminFooter(); ?>