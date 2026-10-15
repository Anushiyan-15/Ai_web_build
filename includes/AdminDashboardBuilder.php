<?php
// includes/AdminDashboardBuilder.php — Builds the BUSINESS-SPECIFIC admin
// dashboard from a business manifest. Wraps AdminPanelGenerator (auth, CRUD,
// inbox, settings preserved) then upgrades index.php to a live operating
// overview for THIS business only: real counts, status breakdowns, recent
// records, quick actions. No unrelated modules, no fake stats — every number
// is computed from persisted data_*.json files at request time.

require_once __DIR__ . '/AdminPanelGenerator.php';
require_once __DIR__ . '/BusinessRegistry.php';

if (!class_exists('AdminDashboardBuilder')) {
class AdminDashboardBuilder {

    public static function installFromManifest(string $adminDir, array $order, array $manifest): bool {
        if (!is_dir($adminDir)) @mkdir($adminDir, 0755, true);
        // Base install (login/auth/layout/manage/content/settings/messages).
        // Entities come from the manifest — one business, its own modules.
        $order2 = $order;
        $order2['admin_entities'] = self::manifestEntitiesForGenerator($manifest);
        $order2['admin_seed_empty'] = true;
        AdminPanelGenerator::install($adminDir, $order2);
        // Business manifest copy for the dashboard renderer.
        @file_put_contents($adminDir . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        // Ensure every manifest entity has a data file (empty = honest, never samples).
        foreach (($manifest['entities'] ?? []) as $e) {
            $id = preg_replace('/[^a-z0-9_]/i', '', (string)($e['id'] ?? ''));
            if ($id === '') continue;
            $f = $adminDir . '/data_' . $id . '.json';
            if (!is_file($f)) @file_put_contents($f, '[]', LOCK_EX);
        }
        // Business-specific dashboard (overwrites generic index.php).
        @file_put_contents($adminDir . '/index.php', self::dashboardCode($manifest), LOCK_EX);
        return true;
    }

    // Upgrade an already-installed admin dir without touching auth/data.
    public static function upgrade(string $adminDir, array $manifest): bool {
        if (!is_dir($adminDir)) return false;
        @file_put_contents($adminDir . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        foreach (($manifest['entities'] ?? []) as $e) {
            $id = preg_replace('/[^a-z0-9_]/i', '', (string)($e['id'] ?? ''));
            if ($id === '') continue;
            $f = $adminDir . '/data_' . $id . '.json';
            if (!is_file($f)) @file_put_contents($f, '[]', LOCK_EX);
        }
        @file_put_contents($adminDir . '/index.php', self::dashboardCode($manifest), LOCK_EX);
        return true;
    }

    public static function manifestEntitiesForGenerator(array $manifest): array {
        $out = [];
        foreach (array_slice((array)($manifest['entities'] ?? []), 0, 8) as $e) {
            if (!is_array($e)) continue;
            $fields = [];
            foreach (array_slice((array)($e['fields'] ?? []), 0, 15) as $f) {
                if (is_array($f)) {
                    // Label-preserving shape "name:Label:type*" (+ ":opt|opt" for
                    // selects) so business labels (Fare, Plate No, Booking Ref)
                    // survive into the admin forms instead of generic rewrites.
                    $fn = (string)($f['name'] ?? '');
                    $fl = trim((string)($f['label'] ?? ''));
                    $s = $fn . ':' . ($fl !== '' ? $fl : ucwords(str_replace('_', ' ', $fn)))
                        . ':' . ($f['type'] ?? 'text') . (!empty($f['required']) ? '*' : '');
                    if (($f['type'] ?? '') === 'select' && !empty($f['options'])) $s .= ':' . implode('|', $f['options']);
                    $fields[] = $s;
                } elseif (is_string($f)) $fields[] = $f;
            }
            $out[] = ['id' => $e['id'] ?? '', 'name' => $e['name'] ?? ($e['id'] ?? ''),
                'singular' => $e['singular'] ?? ($e['name'] ?? ''), 'icon' => $e['icon'] ?? '📁', 'fields' => $fields];
        }
        return $out;
    }

    private static function jsEmbed(string $json): string {
        // Same rule as AdminPanelGenerator::jsEmbed: single-quoted PHP
        // embedding must keep JSON double quotes intact.
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $json);
    }

    private static function dashboardCode(array $m): string {
        $bizLabel = addslashes($m['business']['label'] ?? 'Business');
        $bizName = addslashes($m['business']['name'] ?? 'Business');
        $modsJson = self::jsEmbed(json_encode(array_values(array_filter(($m['admin_modules'] ?? []),
            fn($x) => ($x['id'] ?? '') !== 'dashboard'))));
        $statsJson = self::jsEmbed(json_encode($m['dashboard_stats'] ?? []));
        $rulesJson = self::jsEmbed(json_encode(array_slice((array)($m['business_rules'] ?? []), 0, 6)));
        // Nowdoc: no PHP interpolation at build time; generated file is standalone.
        $code = <<<'PHP'
<?php
require_once __DIR__ . '/layout.php';
renderAdminHeader('dashboard', 'Dashboard');
$__manifest = file_exists(__DIR__ . '/manifest.json') ? (json_decode(@file_get_contents(__DIR__ . '/manifest.json'), true) ?: []) : [];
$__mods = json_decode('__MODS__', true) ?: [];
$__stats = json_decode('__STATS__', true) ?: [];
$__rules = json_decode('__RULES__', true) ?: [];
$__load = function (string $entity): array {
    $ent = preg_replace('/[^a-z0-9_]/i', '', $entity);
    $byId = [];
    // Union: admin mirror + JSON stores (collections/sites) — frontend rows
    // stay visible here even when the mirror missed them.
    $files = [__DIR__ . '/data_' . $ent . '.json'];
    $__slug = basename(dirname(__DIR__));
    $__root = dirname(__FILE__, 3) . '/storage';
    $files[] = $__root . '/collections/' . $__slug . '/' . $ent . '.json';
    $files[] = $__root . '/sites/' . $__slug . '/' . $ent . '.json';
    foreach ($files as $__f) {
        if (!is_file($__f)) continue;
        $__j = json_decode(@file_get_contents($__f), true);
        if (!is_array($__j)) continue;
        foreach ($__j as $__r) { if (is_array($__r) && isset($__r['id'])) $byId[(string)$__r['id']] = $__r; }
    }
    return array_values($byId);
};
$__cell = function ($v) {
    if (is_array($v)) $v = (array_keys($v) === range(0, count($v) - 1)) ? implode(', ', array_slice(array_map('strval', $v), 0, 6)) : json_encode($v, JSON_UNESCAPED_UNICODE);
    $s = trim((string)($v ?? ''));
    if ($s === '') $s = '—';
    if (strlen($s) > 90) $s = substr($s, 0, 90) . '…';
    return htmlspecialchars($s);
};
// Real stats from persisted records — computed live, never hardcoded.
$__cards = [];
foreach ($__stats as $st) {
    $rows = $__load((string)($st['entity'] ?? ''));
    $agg = (string)($st['aggregate'] ?? 'count');
    $val = count($rows); $sub = count($rows) . ' records';
    if (strpos($agg, 'sum:') === 0) {
        $parts = explode(':', $agg); $field = $parts[1] ?? 'total';
        $want = isset($parts[2]) ? explode(',', $parts[2]) : [];
        $sum = 0;
        foreach ($rows as $r) {
            if ($want && !in_array(strtolower((string)($r['status'] ?? '')), array_map('strtolower', $want), true)) continue;
            $sum += (float)($r[$field] ?? 0);
        }
        $val = number_format($sum, $sum == round($sum) ? 0 : 2);
        $sub = 'sum of ' . $field . (empty($want) ? '' : ' (' . implode('/', $want) . ')');
    } elseif ($agg === 'status_breakdown') {
        $bd = [];
        foreach ($rows as $r) { $k = strtolower(trim((string)($r['status'] ?? 'other'))); $bd[$k] = ($bd[$k] ?? 0) + 1; }
        arsort($bd);
        $top = array_slice($bd, 0, 3, true);
        $bits = [];
        foreach ($top as $k => $n) $bits[] = $k . ': ' . $n;
        $sub = $bits ? implode(' · ', $bits) : 'no records yet';
    }
    $__cards[] = ['label' => $st['label'] ?? $st['key'], 'value' => $val, 'sub' => $sub, 'entity' => $st['entity'] ?? ''];
}
// Inbox (contact enquiries land in data_inquiries.json).
$__inbox = $__load('inquiries');
$__unread = 0;
foreach ($__inbox as $r) { if (empty($r['read'])) $__unread++; }
// Status breakdown for the first transactional entity (for the chart row).
$__primary = $__stats[1]['entity'] ?? ($__stats[0]['entity'] ?? '');
$__prows = $__primary !== '' ? $__load($__primary) : [];
$__bd = [];
foreach ($__prows as $r) { $k = (string)($r['status'] ?? 'other'); $__bd[$k] = ($__bd[$k] ?? 0) + 1; }
arsort($__bd);
$__recent = array_slice($__prows, 0, 5);
?>
<div class="top-actions">
  <div>
    <h1 class="page-title"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?> · <?= htmlspecialchars($__manifest['business']['name'] ?? '__BIZNAME__') ?> 👋</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:.3rem"><?= htmlspecialchars($__manifest['business']['label'] ?? '__BIZLABEL__') ?> · live operating overview — numbers below are computed from your actual records.</p>
  </div>
  <a href="../index.html" target="_blank" class="btn btn-primary"><span>🌐</span> View Live Website &rarr;</a>
</div>

<div class="grid4">
  <?php foreach ($__cards as $c): ?>
  <div class="stat-box">
    <div class="stat-lbl"><?= htmlspecialchars($c['label']) ?></div>
    <div class="stat-val"><?= htmlspecialchars((string)$c['value']) ?></div>
    <div style="font-size:.75rem;color:#64748b"><?= htmlspecialchars($c['sub']) ?>
      <?php if (!empty($c['entity'])): ?> · <a href="manage.php?entity=<?= urlencode($c['entity']) ?>" style="color:#818cf8;text-decoration:none">Manage &rarr;</a><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="stat-box">
    <div class="stat-lbl">New Enquiries</div>
    <div class="stat-val" style="color:<?= $__unread > 0 ? '#f59e0b' : '#10b981' ?>"><?= (int)$__unread ?></div>
    <div style="font-size:.75rem;color:#64748b"><a href="messages.php" style="color:#818cf8;text-decoration:none">Open Inbox &rarr;</a></div>
  </div>
</div>

<div class="grid2" style="margin-bottom:1.5rem">
  <div class="card" style="margin-bottom:0">
    <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:1rem">⚡ Quick Actions — this business only</h3>
    <?php if (empty($__mods)): ?>
      <p style="color:#64748b;font-size:.86rem">No modules configured.</p>
    <?php else: ?>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
      <?php foreach ($__mods as $mod): ?>
        <?php if (!empty($mod['entity'])): ?>
        <a href="manage.php?entity=<?= urlencode($mod['entity']) ?>" class="btn btn-ghost" style="font-size:.82rem"><?= htmlspecialchars(($mod['icon'] ?? '📁') . ' ' . ($mod['label'] ?? '')) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a href="messages.php" class="btn btn-ghost" style="font-size:.82rem">✉️ Inbox<?= $__unread > 0 ? ' (' . $__unread . ')' : '' ?></a>
      <a href="content.php" class="btn btn-ghost" style="font-size:.82rem">📝 Edit Content</a>
      <a href="settings.php" class="btn btn-ghost" style="font-size:.82rem">⚙️ Settings</a>
    </div>
    <?php endif; ?>
    <?php if (!empty($__rules)): ?>
    <div style="margin-top:1.1rem;background:#0b0f17;border:1px solid #1e293b;border-radius:12px;padding:.9rem 1.1rem">
      <div style="font-size:.76rem;font-weight:800;color:#a5b4fc;margin-bottom:.4rem">BUSINESS RULES ENFORCED BY THE BACKEND</div>
      <ul style="font-size:.8rem;color:#94a3b8;line-height:1.7;margin-left:1.1rem">
        <?php foreach ($__rules as $rl): ?><li><?= htmlspecialchars($rl) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
  <div class="card" style="margin-bottom:0">
    <h3 style="font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:1rem">📈 Status · <?= htmlspecialchars($__primary !== '' ? ucwords(str_replace('_', ' ', $__primary)) : 'Records') ?></h3>
    <?php if (empty($__bd)): ?>
      <div style="text-align:center;padding:2rem 1rem;color:#64748b">
        <div style="font-size:2rem;margin-bottom:.5rem">📂</div>
        <p style="font-size:.86rem">No records yet. New customer activity will appear here automatically.</p>
      </div>
    <?php else: ?>
      <?php $mx = max(1, max($__bd)); foreach ($__bd as $k => $n): ?>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.6rem;font-size:.82rem">
        <span class="badge badge-blue" style="min-width:120px;text-align:center"><?= htmlspecialchars($k) ?></span>
        <div style="flex:1;background:#0b0f17;border-radius:999px;height:10px;overflow:hidden"><div style="height:100%;width:<?= round($n / $mx * 100) ?>%;background:linear-gradient(90deg,#6366f1,#a855f7)"></div></div>
        <strong style="color:#fff"><?= (int)$n ?></strong>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;flex-wrap:wrap;gap:.5rem">
    <h3 style="font-size:1.05rem;font-weight:800;color:#fff">🕘 Recent · <?= htmlspecialchars($__primary !== '' ? ucwords(str_replace('_', ' ', $__primary)) : 'Activity') ?></h3>
    <?php if ($__primary !== ''): ?><a href="manage.php?entity=<?= urlencode($__primary) ?>" class="btn btn-ghost" style="font-size:.8rem">Open full table &rarr;</a><?php endif; ?>
  </div>
  <?php if (empty($__recent)): ?>
    <div style="text-align:center;padding:2rem 1rem;color:#64748b;font-size:.86rem">Empty state — customer submissions will list here the moment they arrive. No sample rows are ever faked.</div>
  <?php else: ?>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>Ref / ID</th><th>Detail</th><th>Status</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($__recent as $r): ?>
      <tr>
        <td><strong style="color:#fff"><?= $__cell($r['ref'] ?? $r['id'] ?? '') ?></strong></td>
        <td><?= $__cell($r['passenger'] ?? $r['customer'] ?? $r['name'] ?? $r['guest'] ?? $r['holder'] ?? $r['title'] ?? '') ?></td>
        <td><span class="badge <?= (($r['status'] ?? '') === 'cancelled') ? 'badge-green' : 'badge-blue' ?>" style="<?= (($r['status'] ?? '') === 'cancelled') ? 'background:#3f1d1d;color:#fca5a5' : '' ?>"><?= $__cell($r['status'] ?? 'received') ?></span></td>
        <td><?= $__cell(substr((string)($r['updated_at'] ?? $r['created_at'] ?? ''), 0, 16)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php renderAdminFooter(); ?>
PHP;
        $code = str_replace(['__MODS__', '__STATS__', '__RULES__', '__BIZNAME__', '__BIZLABEL__'],
            [$modsJson, $statsJson, $rulesJson, $bizName, $bizLabel], $code);
        return $code;
    }
}
}
