<?php
require_once __DIR__ . '/layout.php';

$entities = json_decode('[{"id":"inquiries","name":"Inquiries","singular":"Inquirie","icon":"\\u2709\\ufe0f","fields":[{"name":"name","label":"Name","type":"text","required":true,"options":[]},{"name":"email","label":"Email","type":"email","required":true,"options":[]},{"name":"message","label":"Message","type":"text","required":false,"options":[]}]},{"id":"bookings","name":"Bookings","singular":"Booking","icon":"\\ud83c\\udfab","fields":[{"name":"customer","label":"Customer","type":"text","required":true,"options":[]},{"name":"trip_id","label":"Trip","type":"text","required":false,"options":[]},{"name":"seats","label":"Seats","type":"text","required":false,"options":[]},{"name":"phone","label":"Phone","type":"text","required":false,"options":[]},{"name":"passenger","label":"Passenger","type":"text","required":true},{"name":"email","label":"Email","type":"email","required":true},{"name":"status","label":"Approval status","type":"select","required":false,"options":["pending","approved","rejected","cancelled"]},{"name":"payment_status","label":"Payment status","type":"text","required":false},{"name":"amount","label":"Fare total","type":"number","required":false}]},{"id":"orders","name":"Orders","singular":"Order","icon":"\\ud83e\\uddfe","fields":[{"name":"customer","label":"Customer","type":"text","required":true,"options":[]},{"name":"total","label":"Total","type":"number","required":false,"options":[]}]},{"id":"customers","name":"Customers","singular":"Customer","icon":"\\ud83d\\udc65","fields":[{"name":"name","label":"Name","type":"text","required":true,"options":[]},{"name":"email","label":"Email","type":"email","required":false,"options":[]}]},{"id":"reviews","name":"Reviews","singular":"Review","icon":"\\u2b50","fields":[{"name":"reviewer","label":"Reviewer","type":"text","required":true,"options":[]},{"name":"stars","label":"Stars","type":"number","required":false,"options":[]}]},{"id":"appointments","name":"Appointments","singular":"Appointment","icon":"\\ud83d\\udcc5","fields":[{"name":"customer","label":"Customer","type":"text","required":true,"options":[]},{"name":"date","label":"Date","type":"date","required":false,"options":[]}]},{"id":"subscribers","name":"Subscribers","singular":"Subscriber","icon":"\\ud83d\\udc8c","fields":[{"name":"email","label":"Email","type":"email","required":true,"options":[]}]},{"id":"trips","name":"Trips","singular":"Trip","icon":"\\ud83d\\ude8c","fields":[{"name":"route","label":"Route","type":"text","required":true,"options":[]},{"name":"date","label":"Date","type":"date","required":false,"options":[]}]}]', true) ?: [];
// Live data files created since publish (e.g. storefront-submitted records
// mirrored from the API) appear as extra modules. Columns derive
// from their own records — nothing hidden, nothing hardcoded.
$__knownIds = [];
foreach ($entities as $ent) { $__knownIds[(string)($ent['id'] ?? '')] = true; }
foreach (glob(__DIR__ . '/data_*.json') ?: [] as $df) {
    $did = preg_replace('/^data_|\.json$/', '', basename($df));
    if ($did === '' || isset($__knownIds[$did]) || $did === 'inquiries') continue;
    $__sample = [];
    $__jr = json_decode(@file_get_contents($df), true);
    if (is_array($__jr)) { foreach ($__jr as $__rr) { if (is_array($__rr)) { $__sample = $__rr; break; } } }
    $__dfields = [];
    foreach (array_keys($__sample) as $__k) {
        if ($__k === 'id') continue;
        $__dfields[] = ['name' => $__k, 'label' => ucwords(str_replace('_', ' ', $__k)), 'type' => 'text', 'required' => false];
        if (count($__dfields) >= 8) break;
    }
    $entities[] = ['id' => $did, 'name' => ucwords(str_replace('_', ' ', $did)),
        'singular' => ucwords(str_replace('_', ' ', $did)), 'icon' => '📁', 'fields' => $__dfields];
    $__knownIds[$did] = true;
}
$activeEntityId = $_GET['entity'] ?? ($entities[0]['id'] ?? 'services');

$activeEntity = null;
foreach ($entities as $ent) {
    if ($ent['id'] === $activeEntityId) {
        $activeEntity = $ent;
        break;
    }
}
if (!$activeEntity && !empty($entities)) {
    $activeEntity = $entities[0];
    $activeEntityId = $activeEntity['id'];
}

$dataFile = __DIR__ . '/data_' . $activeEntityId . '.json';
$records = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
if (!is_array($records)) $records = [];
// Read-repair: union customer-submitted rows from the JSON stores
// (storage/collections + storage/sites) so frontend activity is NEVER
// invisible here — e.g. submitted before this admin dir existed, via the
// Node API (sites/), or for entities added after publish. Upsert by id.
$__entSafe = preg_replace('/[^a-z0-9_]/i', '', $activeEntityId);
$__byId = [];
foreach ($records as $__rr) { if (is_array($__rr) && isset($__rr['id'])) $__byId[(string)$__rr['id']] = $__rr; }
$__extra = 0;
foreach ([dirname(__FILE__, 3) . '/storage/collections/' . basename(dirname(__DIR__)) . '/' . $__entSafe . '.json',
          dirname(__FILE__, 3) . '/storage/sites/' . basename(dirname(__DIR__)) . '/' . $__entSafe . '.json'] as $__sf) {
    if (!is_file($__sf)) continue;
    $__sj = json_decode(@file_get_contents($__sf), true);
    if (!is_array($__sj)) continue;
    foreach ($__sj as $__sr) {
        if (is_array($__sr) && isset($__sr['id']) && !isset($__byId[(string)$__sr['id']])) {
            $__byId[(string)$__sr['id']] = $__sr; $__extra++;
        }
    }
}
if ($__extra > 0) {
    $records = array_values($__byId);
    if (count($records) > 2000) $records = array_slice($records, -2000);
    @file_put_contents($dataFile, json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
// Extra columns: submitted keys beyond the spec fields (ref, status,
// payment, seats, ...) render too — the admin sees ALL customer data.
// System keys stay hidden.
$__sysKeys = ['id' => true, 'created_at' => true, 'updated_at' => true, 'idempotency_key' => true];
$__fieldNames = [];
foreach ($activeEntity['fields'] ?? [] as $__ff) { $__fieldNames[(string)($__ff['name'] ?? '')] = true; }
$__isBusBookingManager = isset($__fieldNames['trip_id']) && isset($__fieldNames['seats']);
$__isTripEntity = isset($__fieldNames['route']) && isset($__fieldNames['total_seats']);
$__isBusPaymentLedger = $activeEntityId === 'payments'
    && isset($__fieldNames['payment_id']) && isset($__fieldNames['booking_ref']);
$__isBusLifecycleEntity = false; // Full CRUD enabled for all entities without lifecycle locks
$__extraCols = [];
foreach ($records as $__rr) {
    if (!is_array($__rr)) continue;
    foreach ($__rr as $__k => $__v) {
        if (isset($__sysKeys[$__k]) || isset($__fieldNames[$__k])) continue;
        if (!in_array($__k, $__extraCols, true)) $__extraCols[] = (string)$__k;
        if (count($__extraCols) >= 6) break 2;
    }
}
$__cell = function ($v) {
    if (is_array($v)) $v = count($v) > 0 && array_keys($v) === range(0, count($v) - 1)
        ? implode(', ', array_map('strval', array_slice($v, 0, 6)))
        : json_encode($v, JSON_UNESCAPED_UNICODE);
    $s = (string)($v ?? '—');
    if ($s === '') $s = '—';
    if (strlen($s) > 120) $s = substr($s, 0, 120) . '…';
    return htmlspecialchars($s);
};

// Sync helper: admin edits propagate to the platform collections store so
// customer-facing history (business-ops list) reflects permitted changes.
// Convergent by id — the same upsert rule the API mirror uses.
$__slug = basename(dirname(dirname(__FILE__)));
$__collFile = dirname(__FILE__, 3) . '/storage/collections/' . preg_replace('/[^a-z0-9\-]/i', '', $__slug) . '/' . preg_replace('/[^a-z0-9_]/i', '', $activeEntityId) . '.json';
$__syncCollections = function ($rows) use ($__collFile) {
    $d = dirname($__collFile);
    if (!is_dir($d)) @mkdir($d, 0755, true);
    $h = @fopen($__collFile, 'c+');
    if (!$h) return;
    if (!flock($h, LOCK_EX)) { fclose($h); return; }
    $raw = stream_get_contents($h);
    $coll = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?: []) : [];
    if (!is_array($coll)) $coll = [];
    $byId = [];
    foreach ($coll as $c) { if (is_array($c) && isset($c['id'])) $byId[(string)$c['id']] = $c; }
    foreach ((array)$rows as $r) { if (is_array($r) && isset($r['id'])) $byId[(string)$r['id']] = $r; }
    $ids = [];
    foreach ((array)$rows as $r) { if (is_array($r) && isset($r['id'])) $ids[(string)$r['id']] = true; }
    // Deletions propagate: drop collection rows absent from admin data.
    foreach (array_keys($byId) as $k) { if (!isset($ids[$k])) unset($byId[$k]); }
    ftruncate($h, 0); rewind($h);
    fwrite($h, json_encode(array_values($byId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($h); flock($h, LOCK_UN); fclose($h);
};

$msg = '';
// Handle Add / Edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_item') {
        $id = trim($_POST['item_id'] ?? '');
        $isEdit = !empty($id);
        // Server-side validation mirrors the form: required fields + select
        // options. Admin Add/Edit is a real function, never a silent write.
        $__errs = [];
        foreach ($activeEntity['fields'] ?? [] as $__vf) {
            $__vn = (string)($__vf['name'] ?? '');
            if ($__vn === '') continue;
            $__vv = trim((string)($_POST[$__vn] ?? ''));
            if (!empty($__vf['required']) && $__vv === '') {
                $__errs[] = '“' . ($__vf['label'] ?? $__vn) . '” is required.';
                continue;
            }
            if ($__vv !== '' && ($__vf['type'] ?? '') === 'select'
                && !empty($__vf['options']) && is_array($__vf['options'])
                && !in_array($__vv, array_map('strval', $__vf['options']), true)) {
                $__errs[] = '“' . ($__vf['label'] ?? $__vn) . '” has an invalid option.';
            }
            if ($__vv !== '' && ($__vf['type'] ?? '') === 'email'
                && !filter_var($__vv, FILTER_VALIDATE_EMAIL)) {
                $__errs[] = '“' . ($__vf['label'] ?? $__vn) . '” must be a valid email.';
            }
        }
        if (!empty($__errs)) {
            $msg = '⚠️ ' . implode(' ', array_slice($__errs, 0, 3));
        } else {

        // Edit preserves keys the form does not render (ref, payment, seats,
        // ...); only known fields are overwritten. Nothing is ever dropped.
        $newId = ($__isTripEntity)
            ? 'TRIP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))
            : ($__isBusBookingManager
                ? 'BK-' . strtoupper(bin2hex(random_bytes(4)))
                : (string)time());
        $base = ['id' => $isEdit ? $id : $newId];
        if ($isEdit) {
            foreach ($records as $__er) {
                if (is_array($__er) && (string)($__er['id'] ?? '') === $id) { $base = $__er; break; }
            }
            $base['id'] = $id;
        }
        $item = $base;
        foreach ($activeEntity['fields'] ?? [] as $f) {
            $fname = $f['name'];
            if (isset($_POST[$fname])) {
                $item[$fname] = trim((string)$_POST[$fname]);
            } else {
                $item[$fname] = $base[$fname] ?? '';
            }
        }
        if ($__isBusBookingManager && empty($item['ref'])) {
            $item['ref'] = 'BK-' . strtoupper(bin2hex(random_bytes(4)));
        }

        if ($isEdit) {
            foreach ($records as &$r) {
                if (($r['id'] ?? '') === $id) {
                    $r = $item;
                    break;
                }
            }
        } else {
            array_unshift($records, $item);
        }
        file_put_contents($dataFile, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $__syncCollections($records);
        $msg = $isEdit ? 'Record updated successfully!' : 'New record added successfully!';
        }
    }

    elseif ($action === 'delete_item') {
        $delId = trim($_POST['item_id'] ?? '');
        $records = array_values(array_filter($records, fn($r) => ($r['id'] ?? '') !== $delId));
        file_put_contents($dataFile, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $__syncCollections($records);
        $msg = 'Record deleted.';
    }
}

renderAdminHeader('manage', 'Manage Data');
?>
<div class="top-actions">
  <div>
    <h1 class="page-title">📋 Data &amp; Record Manager</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem">Add, edit, or remove live data items for your website.</p>
  </div>
  <?php if (!$__isBusLifecycleEntity): ?><button class="btn btn-primary" onclick="openAddModal()">
    <span>➕</span> Add New <?= htmlspecialchars($activeEntity['singular'] ?? 'Item') ?>
  </button><?php endif; ?>
</div>

<?php if ($msg): ?>
  <div style="background:rgba(16,185,129,.15);border:1px solid #10b981;color:#34d399;padding:.85rem 1.25rem;border-radius:12px;font-weight:700;margin-bottom:1.5rem">
    ✅ <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<!-- Tabs for entities -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap">
  <?php foreach ($entities as $ent): ?>
    <a href="?entity=<?= urlencode($ent['id']) ?>" 
       class="btn <?= $ent['id'] === $activeEntityId ? 'btn-primary' : 'btn-ghost' ?>">
       <span><?= htmlspecialchars($ent['icon'] ?? '📁') ?></span>
       <span><?= htmlspecialchars($ent['name'] ?? $ent['id']) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2 style="font-size:1.1rem;font-weight:800;color:#fff"><?= htmlspecialchars($activeEntity['name'] ?? 'Records') ?> (<?= count($records) ?>)</h2>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;justify-content:flex-end">
      <input type="text" id="filterInp" placeholder="Search records..." class="inp" style="max-width:260px;padding:.5rem .85rem;font-size:.82rem" oninput="filterTable()">
      <button type="button" class="btn btn-ghost" onclick="downloadDataReport()">Download report</button>
    </div>
  </div>

  <?php if ($__isBusBookingManager): ?>
    <section style="border:1px solid #263244;border-radius:12px;padding:1rem;margin:0 0 1.25rem;background:#0b111d">
      <h3 style="margin:0 0 .35rem;color:#fff;font-size:1rem">Record a received payment</h3>
      <p style="margin:0 0 .85rem;color:#94a3b8;font-size:.82rem">Enter the ticket reference to load its fare. Choose the method only after receiving the payment.</p>
      <form id="wc-payment-form" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
        <label style="display:grid;gap:.35rem;min-width:210px;flex:1;color:#cbd5e1;font-size:.78rem;font-weight:700">
          Ticket reference
          <input class="inp" id="wc-payment-ref" required maxlength="50" placeholder="BK-XXXXXXXX">
        </label>
        <button class="btn btn-ghost" id="wc-payment-lookup" type="button">Load fare</button>
        <label style="display:grid;gap:.35rem;min-width:150px;color:#cbd5e1;font-size:.78rem;font-weight:700">
          Method
          <select class="inp" id="wc-payment-method" required>
            <option value="">Select method</option><option value="card">Card received</option><option value="cash">Cash received</option>
          </select>
        </label>
        <button class="btn btn-primary" id="wc-payment-submit" type="submit" disabled>Save payment</button>
      </form>
      <p id="wc-payment-quote" aria-live="polite" style="margin:.75rem 0 0;color:#94a3b8;font-size:.85rem">No ticket loaded.</p>
    </section>
  <?php endif; ?>

  <?php if (empty($records)): ?>
    <div style="text-align:center;padding:3rem 1rem;color:#64748b">
      <div style="font-size:2rem;margin-bottom:.5rem">📂</div>
      <p><?= $__isBusPaymentLedger ? 'No received payments have been recorded yet.' : ($__isBusBookingManager ? 'Customer requests will appear here after they submit a request.' : 'No records found yet. Click <strong>+ Add New</strong> above to create your first entry.') ?></p>
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table id="recordsTable">
        <thead>
          <tr>
            <?php foreach ($activeEntity['fields'] ?? [] as $f): ?>
              <th><?= htmlspecialchars($f['label'] ?? $f['name']) ?></th>
            <?php endforeach; ?>
            <?php foreach ($__extraCols as $__ec): ?>
              <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $__ec))) ?></th>
            <?php endforeach; ?>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $row): ?>
            <tr>
              <?php foreach ($activeEntity['fields'] ?? [] as $f): 
              ?>
                <td><?= $__cell($row[$f['name']] ?? null) ?></td>
              <?php endforeach; ?>
              <?php foreach ($__extraCols as $__ec): ?>
                <td><?= $__cell($row[$__ec] ?? null) ?></td>
              <?php endforeach; ?>
              <td style="text-align:right;white-space:nowrap">
                <?php if ($__isBusBookingManager && in_array((string)($row['status'] ?? 'pending'), ['pending', 'approved'], true)): ?>
                  <?php if (($row['status'] ?? 'pending') === 'pending'): ?>
                    <button type="button" class="btn btn-primary" style="padding:.35rem .6rem;font-size:.76rem;margin-right:.25rem" onclick='wcRecordStatus(<?= json_encode((string)($row['id'] ?? '')) ?>,"approved")'>Approve</button>
                    <button type="button" class="btn btn-danger" style="padding:.35rem .6rem;font-size:.76rem;margin-right:.25rem" onclick='wcRecordStatus(<?= json_encode((string)($row['id'] ?? '')) ?>,"rejected")'>Reject</button>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if (!$__isBusLifecycleEntity): ?><button type="button" class="btn btn-ghost" style="padding:.35rem .75rem;font-size:.78rem;margin-right:.4rem"
                        onclick='openEditModal(<?= json_encode($row) ?>)'>✏️ Edit</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record?');">
                  <?= admin_csrf_field() ?>
                  <input type="hidden" name="action" value="delete_item">
                  <input type="hidden" name="item_id" value="<?= htmlspecialchars($row['id'] ?? '') ?>">
                  <button type="submit" class="btn btn-danger" style="padding:.35rem .75rem;font-size:.78rem">🗑️</button>
                </form><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!$__isBusLifecycleEntity): ?>
<!-- Add / Edit Modal -->
<div id="itemModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#111622;border:1.5px solid #283347;border-radius:20px;max-width:540px;width:100%;padding:2rem;box-shadow:0 25px 60px rgba(0,0,0,.6)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
      <h3 style="font-size:1.25rem;font-weight:800;color:#fff" id="modalTitle">Add Record</h3>
      <button type="button" onclick="closeModal()" style="background:none;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer">&times;</button>
    </div>

    <form method="POST" id="itemForm">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="item_id" id="modal_item_id" value="">

      <?php foreach ($activeEntity['fields'] ?? [] as $f):
        $fname = $f['name'];
        $ftype = $f['type'] ?? 'text';
        $freq = !empty($f['required']);
        $fopts = (isset($f['options']) && is_array($f['options'])) ? array_values(array_filter(array_map('strval', $f['options']))) : [];
      ?>
        <div class="field">
          <label class="lbl"><?= htmlspecialchars($f['label'] ?? $fname) ?><?= $freq ? ' *' : '' ?></label>
          <?php if ($ftype === 'textarea'): ?>
            <textarea name="<?= $fname ?>" id="inp_<?= $fname ?>" rows="3" class="inp"<?= $freq ? ' required' : '' ?>></textarea>
          <?php elseif ($ftype === 'select'): ?>
            <select name="<?= $fname ?>" id="inp_<?= $fname ?>" class="inp"<?= $freq ? ' required' : '' ?>>
              <option value="">— Select —</option>
              <?php foreach ($fopts as $__o): ?>
                <option value="<?= htmlspecialchars($__o) ?>"><?= htmlspecialchars($__o) ?></option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($ftype === 'number'): ?>
            <input type="number" step="any" name="<?= $fname ?>" id="inp_<?= $fname ?>" class="inp"<?= $freq ? ' required' : '' ?>>
          <?php else: ?>
            <input type="<?= $ftype === 'email' ? 'email' : ($ftype === 'date' ? 'date' : (($ftype === 'tel' || $ftype === 'url') ? $ftype : 'text')) ?>"
                   name="<?= $fname ?>" id="inp_<?= $fname ?>" class="inp"<?= $freq ? ' required' : '' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <!-- Section 18.5 Requirement Builder hook (only inside edit context) -->
      <div id="req-builder-hook" style="display:none;background:#1e1b4b;border:1px solid #4338ca;padding:.75rem;border-radius:10px;margin-bottom:1.25rem;font-size:.8rem;color:#c7d2fe">
        ✨ <strong>Process Builder:</strong> Edit context verified. Requirement builder features enabled.
      </div>

      <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Save Record &rarr;</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const WC_ADMIN_CSRF = <?= json_encode(admin_csrf_token()) ?>;
const WC_ADMIN_SLUG = <?= json_encode($__slug) ?>;
const WC_ADMIN_RECORDS = <?= json_encode($records, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
function wcOps(payload) {
  return fetch('../../../api/business-ops.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/json', 'X-Admin-CSRF': WC_ADMIN_CSRF},
    body: JSON.stringify(Object.assign({slug: WC_ADMIN_SLUG}, payload))
  }).then(async function(response) {
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.error || 'Request failed.');
    return data;
  });
}
function wcRecordStatus(id, status) {
  if (!confirm('Set this booking to ' + status + '?')) return;
  wcOps({action:'update', entity:WC_ACTIVE_ENTITY, id:id, patch:{status:status}})
    .then(function(data) {
      const notice = data.notification || {};
      const mail = notice.status === 'queued' ? 'Customer status notification queued.'
        : (notice.error || 'No valid customer email; status was saved without an email.');
      alert('Booking ' + status + '. ' + mail);
      location.reload();
    })
    .catch(function(error) { alert('Could not update booking: ' + error.message); });
}
function downloadDataReport() {
  const rows = Array.isArray(WC_ADMIN_RECORDS) ? WC_ADMIN_RECORDS : [];
  if (!rows.length) { alert('There are no records to export.'); return; }
  const columns = Array.from(new Set(rows.reduce(function(keys, row) {
    return keys.concat(Object.keys(row || {}));
  }, [])));
  const cell = function(value) {
    if (Array.isArray(value) || (value && typeof value === 'object')) value = JSON.stringify(value);
    return '"' + String(value == null ? '' : value).replace(/"/g, '""') + '"';
  };
  const csv = [columns.map(cell).join(','), ...rows.map(function(row) {
    return columns.map(function(key) { return cell(row[key]); }).join(',');
  })].join('
');
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], {type:'text/csv;charset=utf-8'}));
  const link = document.createElement('a');
  link.href = url;
  link.download = WC_ADMIN_SLUG + '-<?= htmlspecialchars($activeEntityId, ENT_QUOTES) ?>-' + new Date().toISOString().slice(0,10) + '.csv';
  link.click();
  setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
}
<?php if ($__isBusBookingManager): ?>
let wcPaymentQuote = null;
document.getElementById('wc-payment-lookup').addEventListener('click', function() {
  const ref = document.getElementById('wc-payment-ref').value.trim().toUpperCase();
  const out = document.getElementById('wc-payment-quote');
  wcPaymentQuote = null;
  document.getElementById('wc-payment-submit').disabled = true;
  if (!ref) { out.textContent = 'Enter a ticket reference first.'; return; }
  out.textContent = 'Loading fare…';
  wcOps({action:'payment_quote', entity:WC_ACTIVE_ENTITY, ref:ref})
    .then(function(result) {
      wcPaymentQuote = result.data;
      out.textContent = result.data.ref + ' · ' + (result.data.passenger || 'Customer') + ' · '
        + result.data.currency + ' ' + Number(result.data.amount).toFixed(2)
        + ' · booking ' + result.data.status;
      document.getElementById('wc-payment-submit').disabled = result.data.status !== 'approved';
      if (result.data.status !== 'approved') out.textContent += ' · Approve the booking before recording payment.';
    })
    .catch(function(error) { out.textContent = error.message; });
});
document.getElementById('wc-payment-form').addEventListener('submit', function(event) {
  event.preventDefault();
  if (!wcPaymentQuote) { alert('Load the ticket fare before saving payment.'); return; }
  const method = document.getElementById('wc-payment-method').value;
  const submit = document.getElementById('wc-payment-submit');
  submit.disabled = true;
  wcOps({action:'pay', entity:WC_ACTIVE_ENTITY, ref:wcPaymentQuote.ref, method:method})
    .then(function(result) {
      const payment = result.payment || {};
      document.getElementById('wc-payment-quote').textContent = 'Payment saved: '
        + (payment.payment_id || payment.id) + ' · ' + payment.currency + ' '
        + Number(payment.amount).toFixed(2) + ' · ' + method + ' · ' + payment.received_at;
      wcPaymentQuote = null;
      document.getElementById('wc-payment-form').reset();
      setTimeout(function() { location.reload(); }, 1200);
    })
    .catch(function(error) {
      document.getElementById('wc-payment-quote').textContent = error.message;
      submit.disabled = false;
    });
});
<?php endif; ?>

function openAddModal() {
  document.getElementById('modalTitle').textContent = '➕ Add New ' + <?= json_encode($activeEntity['singular'] ?? 'Item') ?>;
  document.getElementById('modal_item_id').value = '';
  document.getElementById('itemForm').reset();
  document.getElementById('req-builder-hook').style.display = 'none';
  document.getElementById('itemModal').style.display = 'flex';
}

function openEditModal(row) {
  document.getElementById('modalTitle').textContent = '✏️ Edit ' + <?= json_encode($activeEntity['singular'] ?? 'Item') ?>;
  document.getElementById('modal_item_id').value = row.id || '';
  
  <?php foreach ($activeEntity['fields'] ?? [] as $f): ?>
    if (document.getElementById('inp_' + <?= json_encode($f['name']) ?>)) {
      document.getElementById('inp_' + <?= json_encode($f['name']) ?>).value = row[<?= json_encode($f['name']) ?>] || '';
    }
  <?php endforeach; ?>

  // Section 18.5: render builder hook ONLY in edit mode
  document.getElementById('req-builder-hook').style.display = 'block';
  document.getElementById('itemModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('itemModal').style.display = 'none';
}

function filterTable() {
  const query = document.getElementById('filterInp').value.toLowerCase();
  const rows = document.querySelectorAll('#recordsTable tbody tr');
  rows.forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(query) ? '' : 'none';
  });
}
</script>

<?php renderAdminFooter(); ?>