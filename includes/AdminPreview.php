<?php
// includes/AdminPreview.php — Single-file INTERACTIVE admin preview built
// from the REAL business manifest (never generic prebuilt).
//
// The builder workspace Admin tab fetches api/admin-preview.php on click
// (delay OK): analysis → manifest → AI-lane probe → this HTML. Login works
// in-preview, EVERY entity of ANY world business gets full Add/Edit/Delete
// + search + status/payment workflow actions derived from its REAL manifest
// fields (bus trips, shop orders, hotel stays, salon visits, clinic
// appointments, courses, properties, events — one universal path, never a
// per-business special case). After Publish the same manifest installs the
// real multi-file PHP panel (AdminDashboardBuilder).

if (!function_exists('admin_preview_html')) {

function _ap_esc($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function _ap_field_input(array $f, string $val = ''): string {
    $name = preg_replace('/[^a-z0-9_]/i', '', (string)($f['name'] ?? 'field'));
    $label = (string)($f['label'] ?? $name);
    $type = strtolower((string)($f['type'] ?? 'text'));
    $req = !empty($f['required']) ? ' required' : '';
    $v = _ap_esc($val);
    $common = 'name="' . _ap_esc($name) . '" data-f="' . _ap_esc($name) . '"';
    switch ($type) {
        case 'textarea':
            return '<label>' . _ap_esc($label) . '</label><textarea ' . $common . $req . '>' . $v . '</textarea>';
        case 'select':
            $opts = is_array($f['options'] ?? null) ? $f['options'] : [];
            $h = '<label>' . _ap_esc($label) . '</label><select ' . $common . $req . '>';
            $h .= '<option value="">—</option>';
            foreach (array_slice($opts, 0, 12) as $o) {
                $o = (string)$o;
                $h .= '<option value="' . _ap_esc($o) . '"' . ($val === $o ? ' selected' : '') . '>' . _ap_esc($o) . '</option>';
            }
            return $h . '</select>';
        case 'number':
            return '<label>' . _ap_esc($label) . '</label><input type="number" ' . $common . ' value="' . $v . '"' . $req . '>';
        case 'date':
            return '<label>' . _ap_esc($label) . '</label><input type="date" ' . $common . ' value="' . $v . '"' . $req . '>';
        case 'email':
            return '<label>' . _ap_esc($label) . '</label><input type="email" ' . $common . ' value="' . $v . '"' . $req . '>';
        case 'url':
            return '<label>' . _ap_esc($label) . '</label><input type="url" ' . $common . ' value="' . $v . '"' . $req . ' placeholder="https://…">';
        default:
            return '<label>' . _ap_esc($label) . '</label><input type="text" ' . $common . ' value="' . $v . '"' . $req . '>';
    }
}

/**
 * @param array $manifest business-manifest/1 array (real analysis result)
 * @param array $aiMeta ['engine'=>..,'model'=>..,'note'=>..]
 */
function admin_preview_html(string $bizName, array $manifest, array $aiMeta = []): string {
    $bizName = trim($bizName) !== '' ? trim($bizName) : 'My Website';
    $safe = _ap_esc($bizName);
    $cat = (string)($manifest['business']['category'] ?? 'generic');
    $catLabel = (string)($manifest['business']['label'] ?? 'General business');
    $entities = is_array($manifest['entities'] ?? null) ? array_slice($manifest['entities'], 0, 8) : [];
    $workflows = is_array($manifest['core_workflows'] ?? null) ? array_slice($manifest['core_workflows'], 0, 4) : [];
    $rules = is_array($manifest['business_rules'] ?? null) ? array_slice($manifest['business_rules'], 0, 6) : [];
    $engine = (string)($aiMeta['engine'] ?? 'server');
    $model = (string)($aiMeta['model'] ?? 'manifest-live');
    $note = (string)($aiMeta['note'] ?? '');

    // Sidebar modules: manifest admin_modules order (skip pure dashboard).
    $mods = [];
    foreach (($manifest['admin_modules'] ?? []) as $m) {
        $id = (string)($m['entity'] ?? '');
        if ($id === '') continue;
        foreach ($entities as $e) {
            if ((string)($e['id'] ?? '') === $id) {
                $mods[] = ['id' => $id,
                    'label' => (string)($m['label'] ?? $e['name'] ?? $id),
                    'icon' => (string)($e['icon'] ?? '📁'),
                    'why' => (string)($m['why'] ?? '')];
                break;
            }
        }
    }
    // Any entity missing from admin_modules still gets a module.
    foreach ($entities as $e) {
        $id = (string)($e['id'] ?? '');
        if ($id === '') continue;
        $found = false;
        foreach ($mods as $mm) { if ($mm['id'] === $id) { $found = true; break; } }
        if (!$found) $mods[] = ['id' => $id, 'label' => (string)($e['name'] ?? $id),
            'icon' => (string)($e['icon'] ?? '📁'), 'why' => ''];
    }

    $wfHtml = '';
    foreach ($workflows as $w) {
        $nm = _ap_esc($w['name'] ?? $w['key'] ?? 'Workflow');
        $steps = is_array($w['steps'] ?? null) ? implode(' → ', array_map(fn($s) => _ap_esc($s), array_slice($w['steps'], 0, 8))) : '';
        $wfHtml .= '<div class="ap-wf"><b>' . $nm . '</b><span>' . $steps . '</span></div>';
    }
    if ($wfHtml === '') $wfHtml = '<div class="ap-wf"><b>Enquiry flow</b><span>browse → enquire → respond</span></div>';
    $rulesHtml = '';
    foreach ($rules as $r) $rulesHtml .= '<li>' . _ap_esc($r) . '</li>';
    if ($rulesHtml === '') $rulesHtml = '<li>Cancelled records are terminal.</li>';

    $navMods = '';
    foreach ($mods as $m) {
        $navMods .= '<button class="ap-nav" data-tab="ent-' . _ap_esc($m['id']) . '">' . _ap_esc($m['icon']) . ' ' . _ap_esc($m['label']) . '</button>';
    }

    // UNIVERSAL modules: every entity — any world business — gets the same
    // full functions (Add/Edit/Delete + search + status/payment workflow
    // actions derived from its REAL field options, never hardcoded per
    // business). Bus bookings, shop orders, hotel stays, salon visits,
    // clinic appointments, rentals, events — all run through this one path.
    $entSections = '';
    foreach ($mods as $m) {
        $ent = null;
        foreach ($entities as $e) { if ((string)($e['id'] ?? '') === $m['id']) { $ent = $e; break; } }
        $fields = is_array($ent['fields'] ?? null) ? array_slice($ent['fields'], 0, 12) : [];
        $cols = [];
        foreach ($fields as $f) { $cols[] = (string)($f['label'] ?? $f['name'] ?? ''); }
        $cols = array_values(array_filter($cols));
        $formFields = '';
        foreach ($fields as $f) { $formFields .= '<div class="ap-f">' . _ap_field_input(is_array($f) ? $f : ['name' => (string)$f]) . '</div>'; }
        if ($formFields === '') $formFields = '<div class="ap-f"><label>Title</label><input name="title" required></div>';
        // Status + payment options come from the REAL manifest fields, so
        // every business gets its own transitions (bus held→confirmed,
        // orders pending→delivered, stays pending→checked_out, ...).
        $statusOpts = [];
        $payOpts = [];
        foreach ($fields as $f) {
            if (!is_array($f)) continue;
            $fn = strtolower((string)($f['name'] ?? ''));
            $isSel = strtolower((string)($f['type'] ?? '')) === 'select' && !empty($f['options']) && is_array($f['options']);
            if ($fn === 'status' && $isSel) {
                foreach ($f['options'] as $o) { $o = trim((string)$o); if ($o !== '' && !in_array($o, $statusOpts, true)) $statusOpts[] = $o; }
            }
            if (in_array($fn, ['payment_status', 'payment', 'pay_status'], true)) {
                if ($isSel) { foreach ($f['options'] as $o) { $o = trim((string)$o); if ($o !== '' && !in_array($o, $payOpts, true)) $payOpts[] = $o; } }
                elseif (empty($payOpts)) $payOpts = ['unpaid', 'paid'];
            }
        }
        $hint = 'Row actions: ✏️ Edit · 🗑 Delete';
        if (!empty($statusOpts)) $hint .= ' · status flow: <b>' . _ap_esc(implode(' → ', $statusOpts)) . '</b> (✅ advances, ✕ cancels)';
        if (!empty($payOpts)) $hint .= ' · payment: <b>' . _ap_esc(implode(' / ', $payOpts)) . '</b> (💳 records payment)';
        $entSections .= '<section class="ap-pane" id="pane-ent-' . _ap_esc($m['id']) . '">'
            . '<div class="ap-pane-head"><div><h2>' . _ap_esc($m['icon'] . ' ' . $m['label']) . '</h2>'
            . ($m['why'] !== '' ? '<p>' . _ap_esc($m['why']) . '</p>' : '') . '</div>'
            . '<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center"><input class="ap-search" data-search="' . _ap_esc($m['id']) . '" placeholder="🔍 Search ' . _ap_esc($m['label']) . '…" style="padding:.65rem .9rem;border:1.5px solid #1e293b;border-radius:10px;background:#060a14;color:#fff;font:inherit;font-size:.82rem;min-width:200px">'
            . '<button class="ap-btn" data-add="' . _ap_esc($m['id']) . '">＋ Add</button></div></div>'
            . '<div class="ap-form" id="form-' . _ap_esc($m['id']) . '" hidden><h3 id="formtitle-' . _ap_esc($m['id']) . '">Add</h3>' . $formFields
            . '<div class="ap-row"><button class="ap-btn" data-save="' . _ap_esc($m['id']) . '">💾 Save</button> <button class="ap-ghost" data-cancel="' . _ap_esc($m['id']) . '">Cancel</button></div></div>'
            . '<div class="ap-tablewrap"><table data-table="' . _ap_esc($m['id']) . '"><thead><tr><th>' . implode('</th><th>', array_map('_ap_esc', array_slice($cols, 0, 6))) . '</th><th>Status</th><th>Actions</th></tr></thead><tbody></tbody></table></div>'
            . '<div class="ap-hint">' . $hint . '.</div>'
            . '</section>';
    }

    $dashStats = is_array($manifest['dashboard_stats'] ?? null) ? array_slice($manifest['dashboard_stats'], 0, 6) : [];
    $specJson = json_encode(['entities' => $entities, 'stats' => $dashStats, 'category' => $cat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $engBadge = _ap_esc($engine . ($model !== '' ? '/' . $model : ''));

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . $safe . ' — Admin Panel (Generated Preview)</title>'
        . '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#060911;color:#e2e8f0;min-height:100vh}'
        . '.ap-login{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;background:radial-gradient(circle at 50% 30%,#151c38,#060911 75%)}'
        . '.ap-card{width:100%;max-width:450px;background:#0c1220;border:1px solid #1e293b;border-radius:20px;padding:2.2rem;box-shadow:0 25px 60px rgba(0,0,0,.6);position:relative;overflow:hidden}'
        . '.ap-card::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#6366f1,#a855f7,#06b6d4)}'
        . '.ap-brand{display:flex;gap:.8rem;align-items:center;margin-bottom:1.2rem}.ap-logo{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.4rem}'
        . '.ap-eng{display:inline-block;font-size:.7rem;font-weight:800;color:#6ee7b7;background:rgba(6,78,59,.4);border:1px solid rgba(16,185,129,.5);border-radius:999px;padding:.2rem .7rem;margin:.5rem 0}'
        . '.ap-card h1{font-size:1.15rem}.ap-card p{font-size:.82rem;color:#94a3b8;margin:.4rem 0 1rem;line-height:1.5}'
        . '.ap-card label{display:block;font-size:.72rem;font-weight:700;color:#cbd5e1;margin:.8rem 0 .3rem;text-transform:uppercase}'
        . '.ap-card input,.ap-f input,.ap-f select,.ap-f textarea{width:100%;padding:.7rem .9rem;border:1.5px solid #1e293b;border-radius:10px;background:#060a14;color:#fff;font:inherit;font-size:.88rem}'
        . '.ap-f textarea{min-height:70px;resize:vertical}.ap-btn{display:inline-block;padding:.7rem 1.3rem;border-radius:10px;border:none;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;font-weight:800;cursor:pointer;font-size:.86rem}'
        . '.ap-btn:hover{filter:brightness(1.15)}.ap-ghost{padding:.7rem 1.1rem;border-radius:10px;background:transparent;border:1.5px solid #334155;color:#94a3b8;cursor:pointer;font-weight:700}'
        . '.ap-dash{display:none;min-height:100vh}.ap-lay{display:flex;min-height:100vh}.ap-side{width:250px;background:#0b0f19;border-right:1px solid #1e293b;padding:1.1rem .9rem;flex-shrink:0;display:flex;flex-direction:column;gap:.2rem}'
        . '.ap-nav{display:block;width:100%;text-align:left;padding:.62rem .8rem;border-radius:9px;background:none;border:none;color:#94a3b8;font-weight:600;cursor:pointer;font-size:.85rem}'
        . '.ap-nav:hover{background:#111827;color:#fff}.ap-nav.on{background:#1e1b4b;color:#a5b4fc;font-weight:800}'
        . '.ap-main{flex:1;padding:1.6rem 2rem;max-width:1200px}.ap-pane{display:none}.ap-pane.on{display:block}'
        . '.ap-pane-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem;flex-wrap:wrap}.ap-pane-head h2{font-size:1.3rem}.ap-pane-head p{font-size:.8rem;color:#94a3b8}'
        . '.ap-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin-bottom:1.2rem}@media(max-width:900px){.ap-stats{grid-template-columns:repeat(2,1fr)}.ap-lay{flex-direction:column}.ap-side{width:100%}}'
        . '.ap-stat{background:#070b14;border:1px solid #1e293b;border-radius:12px;padding:1rem}.ap-stat b{font-size:1.5rem;display:block}.ap-stat span{font-size:.7rem;color:#64748b;text-transform:uppercase;font-weight:700}'
        . '.ap-wf{background:#0f1523;border:1px solid #1e293b;border-radius:10px;padding:.6rem .9rem;margin-bottom:.5rem;font-size:.8rem}.ap-wf b{color:#fff;display:block}.ap-wf span{color:#94a3b8}'
        . '.ap-tablewrap{overflow-x:auto;background:#0f1523;border:1px solid #1e293b;border-radius:12px}.ap-tablewrap table{width:100%;border-collapse:collapse;font-size:.82rem}.ap-tablewrap th,.ap-tablewrap td{padding:.65rem .8rem;border-bottom:1px solid #1e293b;text-align:left}.ap-tablewrap th{color:#64748b;text-transform:uppercase;font-size:.68rem}'
        . '.ap-mini{background:#1e293b;border:1px solid #334155;color:#cbd5e1;border-radius:7px;padding:.3rem .6rem;font-size:.72rem;font-weight:700;cursor:pointer;margin:.1rem}.ap-mini:hover{border-color:#6366f1;color:#fff}.ap-mini.danger{color:#fca5a5;border-color:#7f1d1d}.ap-mini.danger:hover{background:#7f1d1d;color:#fff}'
        . '.ap-form{background:#0f1523;border:1px solid #6366f1;border-radius:12px;padding:1.2rem;margin-bottom:1rem}.ap-form h3{margin-bottom:.8rem}.ap-f{margin-bottom:.7rem}.ap-f label{display:block;font-size:.72rem;font-weight:700;color:#cbd5e1;margin-bottom:.25rem}.ap-row{display:flex;gap:.6rem;margin-top:.8rem}'
        . '.ap-hint{font-size:.74rem;color:#64748b;margin-top:.6rem;line-height:1.5}.ap-toast{position:fixed;bottom:1.4rem;right:1.4rem;background:#059669;color:#fff;padding:.8rem 1.3rem;border-radius:11px;font-weight:700;z-index:99;display:none;box-shadow:0 12px 30px rgba(0,0,0,.5)}'
        . '</style></head><body>'
        . '<div class="ap-login" id="ap-login"><div class="ap-card">'
        . '<div class="ap-brand"><div class="ap-logo">🔐</div><div><h1>' . $safe . '</h1><div style="font-size:.75rem;color:#64748b">Generated Admin · ' . _ap_esc($catLabel) . '</div></div></div>'
        . '<span class="ap-eng">⚡ ' . $engBadge . '</span>'
        . '<h1 style="margin-top:.4rem">Admin Panel Login</h1>'
        . '<p>Real generated preview for <b>' . $safe . '</b> — ' . count($mods) . ' modules, full functions. Demo creds prefilled; click Sign In (delay OK, everything generated).</p>'
        . ($note !== '' ? '<p style="color:#6ee7b7">' . _ap_esc($note) . '</p>' : '')
        . '<form onsubmit="return AP.login(event)"><label>Username</label><input id="ap-u" value="admin" required>'
        . '<label>Password</label><input id="ap-p" type="password" value="preview1234" required>'
        . '<div style="margin-top:1rem"><button class="ap-btn" type="submit" style="width:100%">🚀 Sign In to Dashboard</button></div></form>'
        . '<p style="margin-top:1rem;font-size:.72rem">Modules: ' . _ap_esc(implode(' · ', array_map(fn($m) => $m['icon'] . ' ' . $m['label'], $mods))) . '</p>'
        . '</div></div>'
        . '<div class="ap-dash" id="ap-dash"><div class="ap-lay"><aside class="ap-side">'
        . '<div style="font-weight:800;padding:.2rem .4rem 1rem">⚡ ' . $safe . '<div style="font-size:.68rem;color:#10b981">● Logged In (Admin) · ' . $engBadge . '</div></div>'
        . '<button class="ap-nav on" data-tab="dash">📊 Dashboard</button>' . $navMods
        . '<button class="ap-nav" data-tab="inbox">✉️ Inbox</button>'
        . '<button class="ap-nav" data-tab="content">📝 Content</button>'
        . '<button class="ap-nav" data-tab="settings">⚙️ Settings</button>'
        . '<button class="ap-nav" onclick="AP.logout()" style="color:#ef4444;margin-top:1rem">🚪 Sign Out</button>'
        . '</aside><main class="ap-main">'
        . '<section class="ap-pane on" id="pane-dash"><div class="ap-pane-head"><div><h2>Administration Dashboard 👋</h2><p>' . _ap_esc($catLabel) . ' · generated modules below are fully operable in this preview.</p></div></div>'
        . '<div class="ap-stats" id="ap-stats"></div>'
        . '<h3 style="margin:.4rem 0 .6rem;font-size:.95rem">⚙️ Operating workflows</h3>' . $wfHtml
        . '<h3 style="margin:1rem 0 .4rem;font-size:.95rem">📜 Business rules</h3><ul style="font-size:.8rem;color:#94a3b8;line-height:1.9;padding-left:1.1rem">' . $rulesHtml . '</ul></section>'
        . $entSections
        . '<section class="ap-pane" id="pane-inbox"><div class="ap-pane-head"><div><h2>✉️ Inbox</h2><p>Customer enquiries land here after publish.</p></div></div><div class="ap-tablewrap"><table><thead><tr><th>Name</th><th>Contact</th><th>Message</th><th>Actions</th></tr></thead><tbody id="ap-inbox"></tbody></table></div></section>'
        . '<section class="ap-pane" id="pane-content"><div class="ap-pane-head"><div><h2>📝 Content</h2><p>Business profile for ' . $safe . '.</p></div><button class="ap-btn" onclick="AP.saveContent()">💾 Save</button></div><div class="ap-form" style="display:block"><div class="ap-f"><label>Business name</label><input id="ap-c-name" value="' . $safe . '"></div><div class="ap-f"><label>Tagline</label><input id="ap-c-tag" value=""></div><div class="ap-f"><label>Phone</label><input id="ap-c-phone" value=""></div><div class="ap-f"><label>Email</label><input id="ap-c-email" value=""></div></div></section>'
        . '<section class="ap-pane" id="pane-settings"><div class="ap-pane-head"><div><h2>⚙️ Settings</h2><p>Preview credentials + publish note.</p></div></div><div class="ap-form" style="display:block"><div class="ap-f"><label>New password (demo)</label><input id="ap-s-pass" type="password" placeholder="min 8 chars"></div><div class="ap-row"><button class="ap-btn" onclick="AP.savePass()">Save password</button></div><p class="ap-hint">Real bcrypt password is set at publish (Step 3 credentials). Preview login accepts the demo creds above.</p></div></section>'
        . '</main></div></div><div class="ap-toast" id="ap-toast"></div>'
        . '<script>var AP_SPEC=' . ($specJson !== false ? $specJson : '{}') . ';'
        . '(function(){var SKEY="ap_prev_"+("' . _ap_esc(preg_replace('/[^a-z0-9]+/i', '-', strtolower($bizName))) . '");'
        . 'var S={};try{S=JSON.parse(localStorage.getItem(SKEY)||"{}")||{}}catch(e){S={}}'
        . 'var Q={};'
        . 'function persist(){try{localStorage.setItem(SKEY,JSON.stringify(S))}catch(e){}}'
        . 'function esc(s){return String(s==null?"":s).replace(/&/g,"&amp;").replace(/</g,"&lt;")} '
        . 'function toast(m){var t=document.getElementById("ap-toast");t.textContent=m;t.style.display="block";setTimeout(function(){t.style.display="none"},2600)}'
        . 'function rows(id){return S[id]||[]} '
        . 'function specOf(id){return (AP_SPEC.entities||[]).filter(function(e){return e.id===id})[0]||{fields:[]}} '
        . 'function statusOpts(id){var f=(specOf(id).fields||[]).filter(function(x){return String(x.name||"").toLowerCase()==="status"&&x.type==="select"&&x.options})[0];return f?(f.options||[]):[]}'
        . 'function payField(id){var fs=specOf(id).fields||[];for(var i=0;i<fs.length;i++){var n=String(fs[i].name||"").toLowerCase();if(n==="payment_status"||n==="payment"||n==="pay_status")return fs[i]}return null}'
        . 'function curStatus(o,id){if(o.status)return o.status;var pf=payField(id);if(pf&&o[pf.name])return o[pf.name];if(o._st)return o._st;var so=statusOpts(id);return so.length?so[0]:"—"}'
        . 'function isCancelOpt(s){return /cancel|expire|reject|refund/i.test(String(s||""))}'
        . 'function draw(id){var spec=specOf(id);'
        . 'var t=document.querySelector(\'table[data-table="\'+id+\'"] tbody\');if(!t)return;var r=rows(id);'
        . 'var q=String(Q[id]||"").toLowerCase();'
        . 'if(q){r=r.filter(function(o){return Object.keys(o).some(function(k){if(k.charAt(0)==="_")return false;return String(o[k]).toLowerCase().indexOf(q)!==-1})})}'
        . 'if(!r.length){t.innerHTML=\'<tr><td colspan="8" style="color:#64748b">\'+(q?"No matches for this search.":"No records yet — click ＋ Add (empty state, never fake data).")+\'</td></tr>\';return}'
        . 't.innerHTML=r.map(function(o,ri){var cells=(spec.fields||[]).slice(0,6).map(function(f){return "<td>"+esc(o[f.name])+"</td>"}).join("");'
        . 'var gi=(function(){var all=rows(id);for(var k=0;k<all.length;k++){if(all[k]===o)return k}return ri})();'
        . 'return "<tr>"+cells+\'<td><b>\'+esc(curStatus(o,id))+"</b></td><td data-i=\'"+gi+"\'>"+\'<button class="ap-mini" data-act="edit">✏️</button><button class="ap-mini" data-act="next">✅</button><button class="ap-mini" data-act="pay">💳</button><button class="ap-mini danger" data-act="cancel">✕</button><button class="ap-mini danger" data-act="del">🗑</button>\'+"</td></tr>"}).join("");'
        . 't.querySelectorAll("button").forEach(function(b){b.onclick=function(){var i=+b.parentElement.dataset.i;var a=b.dataset.act;var arr=rows(id);var o=arr[i]||{};'
        . 'var so=statusOpts(id);var pf=payField(id);'
        . 'if(a==="del"){if(confirm("Delete record?")){arr.splice(i,1);S[id]=arr;persist();draw(id);stats();toast("Deleted")}}'
        . 'else if(a==="next"){var fwd=so.filter(function(s){return !isCancelOpt(s)});var cur=curStatus(o,id);var nx=fwd[fwd.indexOf(cur)+1]||fwd[fwd.length-1]||cur;'
        . 'if(o.status!==undefined||so.length)o.status=nx;else o._st=nx;S[id]=arr;persist();draw(id);stats();toast("✅ "+nx)}}'
        . 'else if(a==="cancel"){var cx=so.filter(isCancelOpt)[0]||"cancelled";if(o.status!==undefined||so.length)o.status=cx;else o._st=cx;'
        . 'if(pf&&(String(o[pf.name]).toLowerCase()==="paid"))o[pf.name]="refund_pending";S[id]=arr;persist();draw(id);stats();toast("✕ "+cx)}}'
        . 'else if(a==="pay"){if(pf){var pv=(pf.options||[]).filter(function(s){return /paid|complet|confirm/i.test(s)})[0]||(pf.options||[])[0]||"paid";o[pf.name]=pv;'
        . 'if(o.status!==undefined&&so.length){var pv2=so.filter(function(s){return /paid|complet|confirm/i.test(s)})[0];if(pv2)o.status=pv2}}'
        . 'else{o._st="paid"}S[id]=arr;persist();draw(id);stats();toast("💳 Payment recorded")}}'
        . 'else{openForm(id,i)}}}})}'
        . 'function openForm(id,i){var f=document.getElementById("form-"+id);f.hidden=false;f.dataset.edit=(i==null?"":i);'
        . 'document.getElementById("formtitle-"+id).textContent=(i==null?"Add ":"Edit ")+id;'
        . 'if(i!=null){var o=rows(id)[i]||{};f.querySelectorAll("[data-f]").forEach(function(inp){inp.value=o[inp.dataset.f]||""})}'
        . 'else{f.querySelectorAll("[data-f]").forEach(function(inp){inp.value=""})}'
        . 'f.scrollIntoView({behavior:"smooth",block:"center"})}'
        . 'function stats(){var defs=AP_SPEC.stats||[];var h="";'
        . 'function entCount(id){return (S[id]||[]).length}'
        . 'function sumAgg(ent,field,filters){var t=0;((S[ent]||[])).forEach(function(o){'
        . 'if(filters&&filters.length){var st=String(o.status||o.payment_status||o._st||"").toLowerCase();'
        . 'var hit=false;filters.forEach(function(f){if(st===String(f).toLowerCase())hit=true});if(!hit)return}'
        . 'var v=parseFloat(String(o[field]||"").replace(/[^0-9.\-]/g,""));if(isFinite(v))t+=v});return Math.round(t*100)/100}'
        . 'function breakdown(ent){var c={};(S[ent]||[]).forEach(function(o){var k=String(o.status||o.payment_status||o._st||"new");c[k]=(c[k]||0)+1});'
        . 'var ks=Object.keys(c);if(!ks.length)return "—";ks.sort(function(a,b){return c[b]-c[a]});'
        . 'return ks.slice(0,3).map(function(k){return c[k]+" "+k}).join(" · ")}'
        . 'if(defs.length){h=defs.slice(0,6).map(function(d){var v="0";var ag=String(d.aggregate||"count");'
        . 'if(ag.indexOf("sum:")===0){var p=ag.split(":");v=sumAgg(d.entity,p[1]||"amount",(p[2]||"").split(",").filter(Boolean))}'
        . 'else if(ag==="status_breakdown"){v=breakdown(d.entity)}else{v=entCount(d.entity)}'
        . 'return \'<div class="ap-stat"><span>\'+esc(d.label||d.key)+\'</span><b style="font-size:1.05rem">\'+esc(String(v))+\'</b></div>\'}).join("")}'
        . 'else{var ents=AP_SPEC.entities||[];h=ents.slice(0,4).map(function(e){return \'<div class="ap-stat"><span>\'+esc(e.name)+\'</span><b>\'+entCount(e.id)+\'</b></div>\'}).join("")||\'<div class="ap-stat"><span>Modules</span><b>\'+(AP_SPEC.entities||[]).length+\'</b></div>\'}'
        . 'document.getElementById("ap-stats").innerHTML=h;'
        . 'var ib=document.getElementById("ap-inbox");if(ib)ib.innerHTML=\'<tr><td colspan="4" style="color:#64748b">No enquiries in preview — contact form writes here after publish.</td></tr>\'}'
        . 'window.AP={login:function(e){e.preventDefault();document.getElementById("ap-login").style.display="none";document.getElementById("ap-dash").style.display="block";'
        . '(AP_SPEC.entities||[]).forEach(function(x){draw(x.id)});stats();toast("Welcome, Admin 👋");return false},'
        . 'logout:function(){document.getElementById("ap-dash").style.display="none";document.getElementById("ap-login").style.display="flex"},'
        . 'saveContent:function(){toast("✅ Content saved (preview)")},savePass:function(){var v=document.getElementById("ap-s-pass").value;'
        . 'if(v.length<8){toast("Min 8 chars");return}toast("✅ Password updated (preview)")}};'
        . 'document.querySelectorAll(".ap-nav[data-tab]").forEach(function(b){b.onclick=function(){'
        . 'document.querySelectorAll(".ap-nav").forEach(function(x){x.classList.remove("on")});b.classList.add("on");'
        . 'document.querySelectorAll(".ap-pane").forEach(function(p){p.classList.remove("on")});'
        . 'var p=document.getElementById("pane-"+b.dataset.tab);if(p)p.classList.add("on")}});'
        . 'document.querySelectorAll("[data-add]").forEach(function(b){b.onclick=function(){openForm(b.dataset.add,null)}});'
        . 'document.querySelectorAll(".ap-search").forEach(function(inp){inp.oninput=function(){Q[inp.dataset.search]=inp.value;draw(inp.dataset.search)}});'
        . 'document.querySelectorAll("[data-cancel]").forEach(function(b){b.onclick=function(){document.getElementById("form-"+b.dataset.cancel).hidden=true}});'
        . 'document.querySelectorAll("[data-save]").forEach(function(b){b.onclick=function(){var id=b.dataset.save;var f=document.getElementById("form-"+id);'
        . 'var o={};var ok=true;f.querySelectorAll("[data-f]").forEach(function(inp){if(inp.required&&!inp.value.trim()){ok=false;inp.style.borderColor="#ef4444"}o[inp.dataset.f]=inp.value.trim()});'
        . 'if(!ok){toast("Fill required fields");return}'
        . 'var so0=statusOpts(id);o._st=o._st||o.status||(so0.length?so0[0]:"new");o.id=o.id||("r_"+Date.now().toString(36));'
        . 'var arr=rows(id);if(f.dataset.edit===""){arr.unshift(o)}else{arr[+f.dataset.edit]=Object.assign(arr[+f.dataset.edit]||{},o)}'
        . 'S[id]=arr;persist();f.hidden=true;draw(id);stats();toast("✅ Saved")}});'
        . '})();</script></body></html>';
}

}
