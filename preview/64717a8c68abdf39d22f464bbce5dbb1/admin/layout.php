<?php
require_once __DIR__ . '/auth.php';
requireAdmin();

// Business-specific modules (from the validated business spec — every
// business gets its own nav, never a fixed one-size-fits-all set).
$navEntities = json_decode('[{"id":"trips","name":"Trips","singular":"Trip","icon":"\\ud83d\\udcc1","fields":[{"name":"route","label":"Route","type":"text","required":false,"options":[]},{"name":"date","label":"Date","type":"text","required":false,"options":[]},{"name":"departure","label":"Departure","type":"text","required":false,"options":[]},{"name":"price","label":"Price","type":"text","required":false,"options":[]},{"name":"total_seats","label":"Total Seats","type":"text","required":false,"options":[]}]},{"id":"bookings","name":"Bookings","singular":"Booking","icon":"\\ud83d\\udcc1","fields":[{"name":"trip_id","label":"Trip Id","type":"text","required":false,"options":[]},{"name":"seats","label":"Seats","type":"text","required":false,"options":[]},{"name":"passenger","label":"Passenger","type":"text","required":false},{"name":"phone","label":"Phone","type":"tel","required":false},{"name":"status","label":"Status","type":"select","required":false,"options":["held","confirmed","pending","approved","rejected","cancelled"]},{"name":"payment_status","label":"Payment status","type":"select","required":false,"options":["unpaid","paid","refund_pending","refunded"]},{"name":"ref","label":"Ref","type":"text","required":false,"options":[]},{"name":"qr","label":"Qr","type":"text","required":false,"options":[]},{"name":"email","label":"Email","type":"email","required":false},{"name":"amount","label":"Fare total","type":"number","required":false}]},{"id":"payments","name":"Payments","singular":"Payment","icon":"\\ud83d\\udcc1","fields":[{"name":"booking_ref","label":"Booking Ref","type":"text","required":false,"options":[]},{"name":"method","label":"Method","type":"text","required":false,"options":[]},{"name":"amount","label":"Amount","type":"text","required":false,"options":[]},{"name":"status","label":"Status","type":"text","required":false,"options":[]},{"name":"payment_mode","label":"Payment Mode","type":"text","required":false,"options":[]},{"name":"transaction_id","label":"Transaction Id","type":"text","required":false,"options":[]}]},{"id":"buses","name":"Bus","singular":"Bus","icon":"\\ud83d\\udcc1","fields":[{"name":"route","label":"Route","type":"text","required":false,"options":[]},{"name":"capacity","label":"Capacity","type":"text","required":false,"options":[]},{"name":"schedule","label":"Schedule","type":"text","required":false,"options":[]}]},{"id":"seat","name":"Seat","singular":"Seat","icon":"\\ud83d\\udcc1","fields":[{"name":"seat_number","label":"Seat Number","type":"text","required":false,"options":[]},{"name":"status","label":"Status","type":"text","required":false,"options":[]},{"name":"price","label":"Price","type":"text","required":false,"options":[]}]},{"id":"tickets","name":"Ticket","singular":"Ticket","icon":"\\ud83d\\udcc1","fields":[{"name":"ticket_id","label":"Ticket Id","type":"text","required":false,"options":[]},{"name":"qr_code","label":"Qr Code","type":"text","required":false,"options":[]},{"name":"passenger_details","label":"Passenger Details","type":"text","required":false,"options":[]},{"name":"payment_status","label":"Payment Status","type":"text","required":false,"options":[]}]}]', true) ?: [];

function renderAdminHeader(string $activeTab = 'dashboard', string $pageTitle = ''): void {
    $auth = getAuthData();
    $siteName = 'City Express Travels';
    $user = htmlspecialchars($_SESSION['admin_username'] ?? $auth['username'] ?? 'Admin');
    // Unread inbox count (contact form writes admin/data_inquiries.json)
    $unreadInbox = 0;
    $inboxFile = __DIR__ . '/data_inquiries.json';
    if (file_exists($inboxFile)) {
        $rows = json_decode(@file_get_contents($inboxFile), true);
        if (is_array($rows)) foreach ($rows as $r) {
            if (empty($r['read'])) $unreadInbox++;
        }
    }
    $inboxBadge = $unreadInbox > 0 ? ' <span class="badge badge-green" style="margin-left:auto">' . $unreadInbox . '</span>' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ? htmlspecialchars($pageTitle) . ' &mdash; ' : '' ?><?= $siteName ?> Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#0a0d14;color:#e2e8f0;min-height:100vh;display:flex}
.sidebar{width:260px;background:#0d121c;border-right:1px solid #1e293b;padding:1.5rem 1rem;flex-shrink:0;min-height:100vh;display:flex;flex-direction:column}
.main{flex:1;padding:2rem 2.5rem;overflow-y:auto;max-width:1400px}
.brand{display:flex;align-items:center;gap:.75rem;padding:0 .5rem 1.5rem;border-bottom:1px solid #1e293b;margin-bottom:1.25rem}
.brand .logo{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.brand-name{font-weight:800;font-size:.95rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.7rem 1rem;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:.88rem;font-weight:600;margin-bottom:.35rem;transition:.2s}
.nav-item:hover{background:#111622;color:#fff}
.nav-item.active{background:#1e1b4b;color:#a5b4fc;font-weight:700}
.top-actions{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-title{font-size:1.75rem;font-weight:900;color:#fff;letter-spacing:-.02em}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.3rem;border-radius:10px;font-weight:700;font-size:.86rem;cursor:pointer;border:none;transition:.2s;text-decoration:none}
.btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,.4)}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.btn-success:hover{transform:translateY(-2px)}
.btn-ghost{background:#1e293b;color:#94a3b8;border:1.5px solid #334155}
.btn-ghost:hover{border-color:#6366f1;color:#fff}
.btn-danger{background:rgba(239,68,68,.15);border:1px solid #ef4444;color:#fca5a5}
.btn-danger:hover{background:#ef4444;color:#fff}
.card{background:#111622;border:1px solid #1e293b;border-radius:18px;padding:1.75rem;margin-bottom:1.5rem}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:2rem}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:1050px){.grid4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){body{flex-direction:column}.sidebar{width:100%;min-height:auto}.grid4,.grid2{grid-template-columns:1fr}}
.stat-box{background:#0b0f17;border:1px solid #1e293b;border-radius:14px;padding:1.25rem;position:relative}
.stat-lbl{font-size:.76rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
.stat-val{font-size:1.75rem;font-weight:900;color:#fff;margin:.35rem 0}
.field{margin-bottom:1.25rem}
.lbl{display:block;font-size:.78rem;font-weight:700;color:#cbd5e1;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.03em}
.inp,.select,textarea{width:100%;padding:.75rem 1rem;border:1.5px solid #283347;border-radius:11px;background:#0b0f17;color:#fff;font-family:inherit;font-size:.9rem;transition:.2s}
.inp:focus,.select:focus,textarea:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.2)}
table{width:100%;border-collapse:collapse;margin-top:1rem}
th{text-align:left;padding:.75rem 1rem;font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;border-bottom:1px solid #1e293b}
td{padding:.9rem 1rem;border-bottom:1px solid #1e293b;font-size:.85rem;color:#cbd5e1}
tr:hover td{background:#0b0f17}
.badge{display:inline-block;padding:.2rem .65rem;border-radius:999px;font-size:.72rem;font-weight:800}
.badge-green{background:#064e3b;color:#34d399}
.badge-blue{background:#1e1b4b;color:#a5b4fc}
.toast{position:fixed;bottom:2rem;right:2rem;background:#10b981;color:#fff;padding:.85rem 1.4rem;border-radius:12px;font-weight:700;box-shadow:0 15px 40px rgba(0,0,0,.5);z-index:99999;display:none}
.toast.show{display:block}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">
    <div class="logo">⚡</div>
    <div style="min-width:0">
      <div class="brand-name"><?= $siteName ?></div>
      <div style="font-size:.72rem;color:#64748b">Customer CMS Admin</div>
    </div>
  </div>

  <nav style="flex:1">
    <a href="index.php" class="nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
    <a href="messages.php" class="nav-item <?= $activeTab === 'messages' ? 'active' : '' ?>">✉️ Inbox<?= $inboxBadge ?></a>
    <a href="content.php" class="nav-item <?= $activeTab === 'content' ? 'active' : '' ?>">📝 Edit Content</a>
    <a href="manage.php" class="nav-item <?= $activeTab === 'manage' ? 'active' : '' ?>">📋 Manage Data</a>
    <?php foreach ($navEntities as $ne): ?>
      <a href="manage.php?entity=<?= urlencode($ne['id'] ?? '') ?>" class="nav-item" style="padding-left:2.5rem;font-size:.8rem"><?= htmlspecialchars(($ne['icon'] ?? '📁') . ' ' . ($ne['name'] ?? $ne['id'] ?? '')) ?></a>
    <?php endforeach; ?>
    <a href="features.php" class="nav-item <?= $activeTab === 'features' ? 'active' : '' ?>" style="color:#c7d2fe;background:rgba(99,102,241,0.12);border:1px dashed rgba(99,102,241,0.4)">🤖 Add Functions (AI)</a>
    <a href="settings.php" class="nav-item <?= $activeTab === 'settings' ? 'active' : '' ?>">⚙️ Settings &amp; Password</a>
  </nav>

  <div style="padding-top:1.5rem;border-top:1px solid #1e293b">
    <div style="font-size:.75rem;color:#64748b;margin-bottom:.5rem;padding:0 .5rem">Logged in as: <strong style="color:#cbd5e1"><?= $user ?></strong></div>
    <a href="../index.html" target="_blank" class="nav-item" style="color:#10b981">🌐 View Live Website &rarr;</a>
    <a href="logout.php" class="nav-item" style="color:#ef4444">🚪 Sign Out</a>
  </div>
</aside>

<main class="main">
<?php
}

function renderAdminFooter(): void {
?>
</main>
<div id="toast" class="toast"></div>
<script>
function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3500);
}
</script>
</body>
</html>
<?php
}