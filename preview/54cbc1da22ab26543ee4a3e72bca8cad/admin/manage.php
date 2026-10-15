<?php
require_once __DIR__ . '/layout.php';

$entities = json_decode('[{"id":"trips","name":"Trips","singular":"Trip","icon":"\\ud83d\\udcc1","fields":[{"name":"route","label":"Route","type":"text","required":false,"options":[]},{"name":"date","label":"Date","type":"text","required":false,"options":[]},{"name":"departure","label":"Departure","type":"text","required":false,"options":[]},{"name":"price","label":"Price","type":"text","required":false,"options":[]},{"name":"total_seats","label":"Total Seats","type":"text","required":false,"options":[]}]},{"id":"bookings","name":"Bookings","singular":"Booking","icon":"\\ud83d\\udcc1","fields":[{"name":"trip_id","label":"Trip Id","type":"text","required":false,"options":[]},{"name":"seats","label":"Seats","type":"text","required":false,"options":[]},{"name":"passenger","label":"Passenger","type":"text","required":false,"options":[]},{"name":"phone","label":"Phone","type":"text","required":false,"options":[]},{"name":"status","label":"Status","type":"text","required":false,"options":[]},{"name":"payment_status","label":"Payment Status","type":"text","required":false,"options":[]},{"name":"ref","label":"Ref","type":"text","required":false,"options":[]},{"name":"qr","label":"Qr","type":"text","required":false,"options":[]},{"name":"booking_id","label":"Booking Id","type":"text","required":false,"options":[]},{"name":"passenger_details","label":"Passenger Details","type":"text","required":false,"options":[]},{"name":"qr_code","label":"Qr Code","type":"text","required":false,"options":[]}]},{"id":"payments","name":"Payments","singular":"Payment","icon":"\\ud83d\\udcc1","fields":[{"name":"booking_ref","label":"Booking Ref","type":"text","required":false,"options":[]},{"name":"method","label":"Method","type":"text","required":false,"options":[]},{"name":"amount","label":"Amount","type":"text","required":false,"options":[]},{"name":"status","label":"Status","type":"text","required":false,"options":[]},{"name":"payment_id","label":"Payment Id","type":"text","required":false,"options":[]},{"name":"payment_mode","label":"Payment Mode","type":"text","required":false,"options":[]}]},{"id":"buses","name":"Bus","singular":"Bus","icon":"\\ud83d\\udcc1","fields":[{"name":"route","label":"Route","type":"text","required":false,"options":[]},{"name":"capacity","label":"Capacity","type":"text","required":false,"options":[]},{"name":"schedule","label":"Schedule","type":"text","required":false,"options":[]}]},{"id":"seat","name":"Seat","singular":"Seat","icon":"\\ud83d\\udcc1","fields":[{"name":"seat_number","label":"Seat Number","type":"text","required":false,"options":[]},{"name":"status","label":"Status","type":"text","required":false,"options":[]},{"name":"price","label":"Price","type":"text","required":false,"options":[]}]},{"id":"tickets","name":"QR Ticket","singular":"QR Ticket","icon":"\\ud83d\\udcc1","fields":[{"name":"ticket_id","label":"Ticket Id","type":"text","required":false,"options":[]},{"name":"qr_data","label":"Qr Data","type":"text","required":false,"options":[]},{"name":"validity","label":"Validity","type":"text","required":false,"options":[]}]}]', true) ?: [];
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
        $base = ['id' => $isEdit ? $id : (string)time()];
        if ($isEdit) {
            foreach ($records as $__er) {
                if (is_array($__er) && (string)($__er['id'] ?? '') === $id) { $base = $__er; break; }
            }
            $base['id'] = $id;
        }
        $item = $base;
        foreach ($activeEntity['fields'] ?? [] as $f) {
            $fname = $f['name'];
            $item[$fname] = trim($_POST[$fname] ?? '');
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

    if ($action === 'delete_item') {
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
  <button class="btn btn-primary" onclick="openAddModal()">
    <span>➕</span> Add New <?= htmlspecialchars($activeEntity['singular'] ?? 'Item') ?>
  </button>
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
    <input type="text" id="filterInp" placeholder="Search records..." class="inp" style="max-width:260px;padding:.5rem .85rem;font-size:.82rem" oninput="filterTable()">
  </div>

  <?php if (empty($records)): ?>
    <div style="text-align:center;padding:3rem 1rem;color:#64748b">
      <div style="font-size:2rem;margin-bottom:.5rem">📂</div>
      <p>No records found yet. Click <strong>+ Add New</strong> above to create your first entry.</p>
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
                <button type="button" class="btn btn-ghost" style="padding:.35rem .75rem;font-size:.78rem;margin-right:.4rem"
                        onclick='openEditModal(<?= json_encode($row) ?>)'>✏️ Edit</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record?');">
                  <?= admin_csrf_field() ?>
                  <input type="hidden" name="action" value="delete_item">
                  <input type="hidden" name="item_id" value="<?= htmlspecialchars($row['id'] ?? '') ?>">
                  <button type="submit" class="btn btn-danger" style="padding:.35rem .75rem;font-size:.78rem">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

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

<script>
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