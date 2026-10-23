<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// No-cache: logout ku pirahu Back press panna stale builder vara kudathu
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/ui-lang.php';
$UI_LANG = ui_resolve_lang();

$customerUser  = $_SESSION['customer_user'] ?? null;
$customerEmail = $customerUser['email'] ?? null;

// ─── AJAX: Save design draft before publish ─────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'save_design' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $html       = (string)($data['html'] ?? '');
        $bizName    = preg_replace('/[^a-zA-Z0-9 _\-]/', '', (string)($data['biz_name'] ?? 'Site'));
        $genMode    = in_array($data['gen_mode'] ?? '', ['static','admin','database']) ? $data['gen_mode'] : 'static';
        $designIdx  = max(0, (int)($data['design_index'] ?? 0));

        if (empty($html)) {
            echo json_encode(['success' => false, 'error' => 'Empty HTML']);
            exit;
        }

        $draftDir = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/storage') . '/drafts';
        if (!is_dir($draftDir)) @mkdir($draftDir, 0755, true);

        // Unique draft token per session
        $token = bin2hex(random_bytes(16));
        $meta  = [
            'token'       => $token,
            'biz_name'    => $bizName,
            'gen_mode'    => $genMode,
            'design_index'=> $designIdx,
            'saved_at'    => date('Y-m-d H:i:s'),
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        file_put_contents($draftDir . '/' . $token . '.html', $html);
        file_put_contents($draftDir . '/' . $token . '.json', json_encode($meta, JSON_PRETTY_PRINT));

        // Clean old drafts (keep only last 20)
        $allDrafts = glob($draftDir . '/*.json');
        if (count($allDrafts) > 20) {
            usort($allDrafts, fn($a,$b) => filemtime($a) - filemtime($b));
            foreach (array_slice($allDrafts, 0, count($allDrafts) - 20) as $old) {
                $oldHtml = str_replace('.json', '.html', $old);
                @unlink($old);
                @unlink($oldHtml);
            }
        }

        echo json_encode(['success' => true, 'token' => $token]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Check for existing project to load via GET param (?order_id=XXX or ?slug=XXX)
// HTML stays local (storage/designs + published), meta may come from Supabase.
// Ownership: if ?order_id has an owner email, require customer login match.
$loadOrderId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['order_id'] ?? '');
$loadSlug    = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['slug'] ?? '');
$preloadedProject = null;
$loadError = '';

if ($loadOrderId) {
    // 1. Try local order JSON first, then Supabase fallback
    $orderFile = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/storage') . '/orders/' . $loadOrderId . '.json';
    $orderData = file_exists($orderFile) ? json_decode(file_get_contents($orderFile), true) : null;
    if (!$orderData) {
        $dbRows = function_exists('getCustomerOrdersFromDatabase') ? getCustomerOrdersFromDatabase($loadOrderId) : [];
        if (!empty($dbRows[0])) {
            $orderData = $dbRows[0];
            // Normalize Supabase row to order-array shape
            $orderData['order_id'] = $orderData['order_id'] ?? $loadOrderId;
            $orderData['site_name'] = $orderData['site_name'] ?? 'Loaded Website';
            $orderData['created_at'] = $orderData['created_at'] ?? ($orderData['published_at'] ?? '');
        }
    }
    if ($orderData) {
        // Ownership check — only owner email may open it
        $ownerEmail = $orderData['admin_email'] ?? ($orderData['client_email'] ?? '');
        if ($ownerEmail && (!$customerEmail || strcasecmp($ownerEmail, $customerEmail) !== 0)) {
            $loadError = 'Please log in with ' . $ownerEmail . ' to edit this project.';
            $preloadedProject = null;
        } else {
            $designFile = (defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/storage') . '/designs/' . $loadOrderId . '.html';
            $htmlContent = file_exists($designFile) ? file_get_contents($designFile) : '';
            if (!$htmlContent && !empty($orderData['slug'])) {
                $pubFile = __DIR__ . '/published/' . $orderData['slug'] . '/index.html';
                if (file_exists($pubFile)) $htmlContent = file_get_contents($pubFile);
            }
            if ($htmlContent) {
                $preloadedProject = [
                    'order_id'    => $loadOrderId,
                    'biz_name'    => $orderData['site_name'] ?? 'Loaded Website',
                    'slug'        => $orderData['slug'] ?? '',
                    'gen_mode'    => $orderData['gen_mode'] ?? 'admin',
                    'html'        => $htmlContent,
                    'admin_url'   => $orderData['admin_url'] ?? '',
                    'live_url'    => $orderData['live_url'] ?? '',
                ];
            }
        }
    }
} elseif ($loadSlug) {
    $pubFile = __DIR__ . '/published/' . $loadSlug . '/index.html';
    if (file_exists($pubFile)) {
        $metaFile = __DIR__ . '/published/' . $loadSlug . '/meta.json';
        $meta = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : [];
        $preloadedProject = [
            'order_id'    => $meta['order_id'] ?? '',
            'biz_name'    => $meta['site_name'] ?? ucwords(str_replace('-', ' ', $loadSlug)),
            'slug'        => $loadSlug,
            'gen_mode'    => 'admin',
            'html'        => file_get_contents($pubFile),
            'admin_url'   => SITE_URL . '/published/' . $loadSlug . '/admin/login.php',
            'live_url'    => SITE_URL . '/published/' . $loadSlug . '/',
        ];
    }
}

// "Open Project" menu — ONLY this customer's email projects (Supabase + local fallback).
// Guest (no login): empty list, must log in. Generate-new still works locally.
$existingProjects = [];
if ($customerEmail && function_exists('getCustomerOrdersFromDatabase')) {
    $rows = getCustomerOrdersFromDatabase($customerEmail);
    foreach ($rows as $o) {
        if (!empty($o['site_name']) && !empty($o['order_id'])) {
            $existingProjects[] = [
                'order_id'  => $o['order_id'],
                'site_name' => $o['site_name'],
                'slug'      => $o['slug'] ?? '',
                'date'      => $o['published_at'] ?? $o['created_at'] ?? '',
            ];
        }
    }
}

$page_title = 'AI Website Builder — 3 Style Variations & AI Co-Pilot';
$page_desc  = 'Describe your business, choose a mode and a design direction, and get 3 AI-generated style variations.';
$wc_theme_surface = 'editor'; // keep the dark editor chrome (green accents)
require_once __DIR__ . '/includes/nav.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/wc-robot-3d.css?v=<?= @filemtime(__DIR__ . '/assets/css/wc-robot-3d.css') ?: 1 ?>">
<style>
/* ══ BASE ══ */
body { background:#0a0d14; color:#e2e8f0; font-family:'Inter',system-ui,sans-serif; }

/* ══ RESUME BANNER ══ */
.resume-banner {
  display: none; position: fixed; top: 80px; left: 50%; transform: translateX(-50%);
  z-index: 9998; max-width: min(680px, calc(100vw - 2rem));
  padding: 0.85rem 1rem 0.85rem 1.1rem;
  background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(99,102,241,0.12));
  border: 1.5px solid rgba(16,185,129,0.5);
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05) inset;
  align-items: center; gap: 0.85rem;
  animation: bannerIn 0.4s cubic-bezier(0.22,1,0.36,1);
}
.resume-banner.show { display: flex; }
@keyframes bannerIn { from { opacity: 0; transform: translate(-50%, -12px); } to { opacity: 1; transform: translate(-50%, 0); } }
.resume-banner .rb-icon { font-size: 1.4rem; flex-shrink: 0; }
.resume-banner .rb-text { flex: 1; min-width: 0; font-size: 0.84rem; color: #e2e8f0; line-height: 1.4; }
.resume-banner .rb-text strong { color: #34d399; font-weight: 800; }
.resume-banner .rb-text small { display: block; color: #94a3b8; font-size: 0.72rem; margin-top: 0.15rem; }
.resume-banner .rb-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }
.resume-banner .rb-btn {
  padding: 0.45rem 0.85rem; border-radius: 8px; border: 1.5px solid #334155;
  background: #0b0f17; color: #cbd5e1; font-family: inherit;
  font-size: 0.74rem; font-weight: 700; cursor: pointer; transition: all 0.15s;
}
.resume-banner .rb-btn:hover { border-color: #6366f1; color: #fff; }
.resume-banner .rb-btn.primary {
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  border-color: #818cf8; color: #fff;
}
.resume-banner .rb-btn.danger {
  border-color: #7f1d1d; color: #fca5a5;
}
.resume-banner .rb-btn.danger:hover { background: #7f1d1d; color: #fff; }

/* ══ WIZARD SCREEN ══ */
#wizard-screen { min-height: calc(100vh - 64px); background: radial-gradient(circle at 50% 20%, #1e1b4b 0%, #0a0d14 70%); display: flex; align-items: center; justify-content: center; padding: 3rem 1.25rem; }
.wizard-card { background: #111622; border: 1px solid #1e293b; border-radius: 24px; width: 100%; max-width: 880px; box-shadow: 0 25px 60px -15px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.05); overflow: hidden; }
.wizard-header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 2.25rem 2.5rem; color: #fff; }
.wizard-header h1 { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.4rem; letter-spacing: -0.02em; }
.wizard-header p  { color: rgba(255,255,255,0.85); font-size: 0.95rem; }
.wizard-progress { display: flex; gap: 0.5rem; padding: 1.25rem 2.5rem; background: #0d121c; border-bottom: 1px solid #1e293b; }
.wp-dot { flex: 1; height: 5px; border-radius: 999px; background: #1e293b; transition: all 0.3s; }
.wp-dot.done { background: #10b981; }
.wp-dot.active { background: linear-gradient(90deg, #6366f1, #a855f7); box-shadow: 0 0 10px rgba(99,102,241,0.5); }
.wizard-body { padding: 2.25rem 2.5rem; }
.wizard-step { display: none; }
.wizard-step.active { display: block; animation: fadeIn 0.25s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
.wiz-section-label { font-size: 0.78rem; font-weight: 700; color: #818cf8; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
.w-group { margin-bottom: 1.4rem; }
.w-label { display: block; font-size: 0.88rem; font-weight: 600; color: #f1f5f9; margin-bottom: 0.45rem; }
.w-label .req { color: #f43f5e; margin-left: 0.2rem; }
.w-hint { font-size: 0.8rem; color: #94a3b8; margin-top: 0.3rem; line-height: 1.4; }
.w-input, .w-select, .w-textarea { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #283347; border-radius: 10px; font-family: inherit; font-size: 0.92rem; color: #f8fafc; background: #0b0f17; transition: border-color 0.2s, box-shadow 0.2s; -webkit-appearance: none; }
.w-textarea { resize: vertical; min-height: 90px; }
.w-input:focus, .w-select:focus, .w-textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.25); background: #111726; }
.w-select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1.1rem; padding-right: 2.5rem; }
.w-select option { background: #111622; color: #f8fafc; }
.w-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 520px) { .w-row { grid-template-columns: 1fr; } }
.style-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
@media (max-width: 480px) { .style-grid { grid-template-columns: repeat(2, 1fr); } }
.style-card { position: relative; }
.style-card input { position: absolute; opacity: 0; width: 0; height: 0; }
.style-card label { display: flex; flex-direction: column; align-items: center; gap: 0.35rem; padding: 0.85rem 0.5rem; border: 1.5px solid #283347; border-radius: 12px; background: #0b0f17; cursor: pointer; text-align: center; font-size: 0.8rem; font-weight: 600; color: #94a3b8; transition: all 0.2s; }
.style-card input:checked + label { border-color: #6366f1; background: #1e1b4b; color: #a5b4fc; box-shadow: 0 0 15px rgba(99,102,241,0.25); }
.style-card label:hover { border-color: #6366f1; }
.color-grid { display: flex; flex-wrap: wrap; gap: 0.65rem; }
.color-swatch { position: relative; }
.color-swatch input { position: absolute; opacity: 0; }
.color-swatch label { display: block; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; border: 3px solid transparent; transition: transform 0.2s, border-color 0.2s; }
.color-swatch input:checked + label { border-color: #fff; transform: scale(1.15); box-shadow: 0 0 12px rgba(255,255,255,0.4); }
/* ══ TASTE SKILL — design intelligence block ══ */
.taste-block { margin-top: 1.5rem; padding: 1.25rem; background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(16,185,129,0.06)); border: 1.5px solid rgba(99,102,241,0.35); border-radius: 14px; }
.taste-vibes { display: flex; flex-wrap: wrap; gap: 0.45rem; margin: 0.75rem 0 1rem; }
.taste-vibe input { position: absolute; opacity: 0; width: 0; height: 0; }
.taste-vibe span { display: inline-block; padding: 0.42rem 0.8rem; border: 1.5px solid #283347; border-radius: 999px; background: #0b0f17; cursor: pointer; font-size: 0.76rem; font-weight: 700; color: #94a3b8; transition: all 0.2s; }
.taste-vibe span:hover { border-color: #6366f1; color: #fff; }
.taste-vibe input:checked + span { border-color: #818cf8; background: #1e1b4b; color: #c7d2fe; box-shadow: 0 0 12px rgba(99,102,241,0.3); }
.taste-dials { display: flex; flex-direction: column; gap: 0.55rem; margin-bottom: 0.75rem; }
.taste-dial { display: grid; grid-template-columns: 130px 1fr 52px; align-items: center; gap: 0.7rem; }
.td-name { font-size: 0.76rem; font-weight: 600; color: #cbd5e1; }
.taste-dial input[type="range"] { width: 100%; accent-color: #6366f1; cursor: pointer; }
.td-val { font-size: 0.72rem; font-weight: 800; color: #a5b4fc; text-align: right; }
@media (max-width: 520px) { .taste-dial { grid-template-columns: 1fr 52px; } .td-name { grid-column: 1 / -1; } }
.check-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.6rem; }
@media (max-width: 480px) { .check-grid { grid-template-columns: 1fr; } }
.wcheck { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.85rem; border: 1.5px solid #283347; border-radius: 10px; background: #0b0f17; cursor: pointer; transition: all 0.2s; }
.wcheck:hover { border-color: #6366f1; }
.wcheck input { accent-color: #6366f1; width: 17px; height: 17px; }
.wcheck span { font-size: 0.85rem; color: #cbd5e1; }
.wcheck:has(input:checked) { border-color: #6366f1; background: #181938; }
.wizard-footer { padding: 1.5rem 2.5rem; background: #0d121c; border-top: 1px solid #1e293b; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.wbtn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 10px; font-family: inherit; font-size: 0.9rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
.wbtn-ghost { background: transparent; color: #94a3b8; border: 1.5px solid #283347; }
.wbtn-ghost:hover { color: #fff; border-color: #64748b; }
.wbtn-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 4px 15px rgba(79,70,229,0.4); }
.wbtn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,70,229,0.5); }
.wbtn-generate { background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-size: 0.95rem; font-weight: 700; padding: 0.85rem 1.75rem; box-shadow: 0 4px 20px rgba(16,185,129,0.4); }
.wbtn-generate:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(16,185,129,0.55); }
.wbtn-generate:disabled { opacity: 0.55; cursor: not-allowed; }
.wbtn-clear { background: transparent; color: #fca5a5; border: 1.5px solid #7f1d1d; padding: 0.6rem 1rem; font-size: 0.82rem; }
.wbtn-clear:hover { background: #7f1d1d; color: #fff; }
.account-bar { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.85rem 1rem; background: #0a0f1c; border: 1.5px solid #1e293b; border-radius: 12px; margin-bottom: 1.25rem; flex-wrap: wrap; }
.account-info { display: flex; align-items: center; gap: 0.6rem; min-width: 0; }
.account-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 0.9rem; flex-shrink: 0; }
.account-name { font-size: 0.85rem; font-weight: 700; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.account-status { font-size: 0.7rem; color: #10b981; font-weight: 600; margin-top: 0.1rem; }
.account-status.offline { color: #94a3b8; }
.account-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.account-btn { padding: 0.4rem 0.85rem; border-radius: 8px; border: 1.5px solid #334155; background: #0b0f17; color: #cbd5e1; font-family: inherit; font-size: 0.74rem; font-weight: 700; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 0.3rem; }
.account-btn:hover { border-color: #6366f1; color: #fff; }
.account-btn.danger { border-color: #7f1d1d; color: #fca5a5; }
.account-btn.danger:hover { background: #7f1d1d; color: #fff; }
.account-btn.primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); border-color: #818cf8; color: #fff; }
.gen-block { margin-top: 1.75rem; padding: 1.75rem; background: linear-gradient(135deg, rgba(16,185,129,0.06), rgba(99,102,241,0.06)); border: 1.5px solid rgba(16,185,129,0.35); border-radius: 16px; text-align: center; }
.gen-block h3 { font-size: 1.05rem; font-weight: 800; color: #fff; margin-bottom: 0.4rem; }
.gen-block p  { font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.25rem; line-height: 1.5; }

/* Wizard visual refresh — presentation only; existing markup and handlers stay intact. */
#wizard-screen {
  --wizard-accent: #48b89b;
  min-height: calc(100vh - 64px);
  min-height: max(calc(100vh - 64px), calc(100dvh - 64px));
  align-items: flex-start;
  padding: clamp(1.5rem, 4vw, 3.5rem) 1rem;
  background:
    radial-gradient(ellipse at 12% 6%, rgba(72,184,155,0.11), transparent 32rem),
    radial-gradient(ellipse at 92% 82%, rgba(148,163,184,0.07), transparent 30rem),
    #0d1115;
}
#wizard-screen > div:first-child { max-width: 960px !important; margin-bottom: .85rem !important; }
#wizard-screen > div:first-child > a {
  border-color: #35423f !important;
  background: rgba(20,28,29,.82) !important;
  color: #d9e5e1 !important;
  border-radius: 999px !important;
  padding: .58rem 1rem !important;
  transition: border-color .22s ease, background .22s ease, transform .22s ease;
}
#wizard-screen > div:first-child > a:hover { border-color: var(--wizard-accent) !important; background: #182321 !important; transform: translateY(-1px); }
#wizard-screen .wizard-card {
  max-width: 960px;
  background: #151b1d;
  border: 1px solid #2a3535;
  border-radius: 22px;
  box-shadow: 0 28px 80px -38px rgba(0,0,0,.82), inset 0 1px rgba(255,255,255,.035);
}
#wizard-screen .wizard-header {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  padding: clamp(1.5rem, 4vw, 2.6rem);
  background:
    radial-gradient(ellipse at 86% 0%, rgba(72,184,155,.17), transparent 25rem),
    linear-gradient(118deg, #1b2525, #18201f 58%, #172120);
  border-bottom: 1px solid #2c3937;
}
#wizard-screen .wizard-header::after {
  position: absolute;
  z-index: -1;
  right: -4rem;
  bottom: -8rem;
  width: 20rem;
  height: 12rem;
  border: 1px solid rgba(72,184,155,.14);
  border-radius: 50%;
  box-shadow: 0 0 0 1.8rem rgba(72,184,155,.025), 0 0 0 3.6rem rgba(72,184,155,.02);
  content: "";
  pointer-events: none;
}
#wizard-screen .wizard-header h1 { color: #f1f5f2; font-size: clamp(1.45rem, 3vw, 1.9rem); letter-spacing: -.045em; }
#wizard-screen .wizard-header p { max-width: 50ch; color: #aebbb7; line-height: 1.65; }
#wizard-screen .wizard-header strong { color: #8bd3bd; }
#wizard-screen .wizard-progress { gap: .65rem; padding: 1.1rem clamp(1.25rem, 4vw, 2.6rem); background: #121819; border-bottom-color: #273231; }
#wizard-screen .wp-dot { height: 4px; background: #303a39; }
#wizard-screen .wp-dot.done { background: var(--wizard-accent); }
#wizard-screen .wp-dot.active { background: linear-gradient(90deg, #48b89b, #8bd3bd); box-shadow: 0 0 12px rgba(72,184,155,.3); }
#wizard-screen .wizard-body { padding: clamp(1.25rem, 4vw, 2.75rem); }
#wizard-screen .wiz-section-label { color: #82cdb5; letter-spacing: .13em; }
#wizard-screen .w-label { color: #e4ece8; font-size: .9rem; }
#wizard-screen .w-hint { color: #96a6a1; line-height: 1.55; }
#wizard-screen .w-input,
#wizard-screen .w-select,
#wizard-screen .w-textarea {
  min-height: 46px;
  border: 1px solid #35413f;
  border-radius: 11px;
  background-color: #101617;
  color: #e9f0ed;
  transition: border-color .22s ease, background-color .22s ease, box-shadow .22s ease;
}
#wizard-screen .w-input:focus,
#wizard-screen .w-select:focus,
#wizard-screen .w-textarea:focus {
  border-color: #56b99d;
  background-color: #141d1c;
  box-shadow: 0 0 0 3px rgba(72,184,155,.13);
}
#wizard-screen .w-select option { background: #151b1d; }
#wizard-screen .wizard-step#step1 > .w-group:has(#biz_brief) {
  border: 1px solid #35413f !important;
  border-radius: 15px !important;
  background: linear-gradient(135deg, rgba(72,184,155,.07), rgba(16,22,23,.95)) !important;
  padding: clamp(1rem, 3vw, 1.35rem) !important;
}
#wizard-screen .style-grid { gap: .85rem; }
#wizard-screen .style-card label {
  min-height: 92px;
  justify-content: center;
  border: 1px solid #35413f;
  border-radius: 13px;
  background: #111819;
  color: #a6b4af;
  transition: transform .22s ease, border-color .22s ease, background-color .22s ease, color .22s ease;
}
#wizard-screen .style-card label:hover { transform: translateY(-2px); border-color: #5a7770; color: #e8f1ed; }
#wizard-screen .style-card input:checked + label {
  border-color: #56b99d;
  background: linear-gradient(145deg, rgba(72,184,155,.16), #17201f);
  color: #a5e2cf;
  box-shadow: inset 0 1px rgba(255,255,255,.06), 0 8px 22px rgba(0,0,0,.17);
}
#wizard-screen .color-swatch label { box-shadow: 0 0 0 1px rgba(255,255,255,.12) inset; }
#wizard-screen .color-swatch input:checked + label { border-color: #d9ece5; box-shadow: 0 0 0 3px #151b1d, 0 0 0 4px #80cdb5; }
#wizard-screen .taste-block {
  border-color: #34433f;
  background: linear-gradient(135deg, rgba(72,184,155,.07), rgba(20,27,28,.94));
  border-radius: 15px;
}
#wizard-screen .taste-vibe span { border-color: #35413f; background: #111819; color: #a6b4af; }
#wizard-screen .taste-vibe span:hover { border-color: #64857a; color: #edf4f1; }
#wizard-screen .taste-vibe input:checked + span { border-color: #56b99d; background: rgba(72,184,155,.14); color: #a5e2cf; box-shadow: inset 0 1px rgba(255,255,255,.05); }
#wizard-screen .taste-dial input[type="range"] { accent-color: #56b99d; }
#wizard-screen .td-val { color: #8bd3bd; }
#wizard-screen .wcheck { border-color: #35413f; background: #111819; }
#wizard-screen .wcheck:hover { border-color: #64857a; }
#wizard-screen .wcheck input { accent-color: #56b99d; }
#wizard-screen .wcheck:has(input:checked) { border-color: #56b99d; background: rgba(72,184,155,.1); }
#wizard-screen .account-bar { border-color: #34433f !important; background: linear-gradient(110deg, rgba(72,184,155,.08), #12191a) !important; border-radius: 14px; }
#wizard-screen .gen-block { border-color: #3b655a; background: linear-gradient(130deg, rgba(72,184,155,.1), #131a1a 65%); border-radius: 16px; }
#wizard-screen .gen-block p strong { color: #9cddc9 !important; }
#wizard-screen .wbtn { border-radius: 10px; transition: transform .22s ease, border-color .22s ease, background-color .22s ease, box-shadow .22s ease; }
#wizard-screen .wbtn:active { transform: scale(.98); }
#wizard-screen .wbtn:focus-visible,
#wizard-screen .style-card input:focus-visible + label,
#wizard-screen .taste-vibe input:focus-visible + span,
#wizard-screen .color-swatch input:focus-visible + label {
  outline: 2px solid #8bd3bd;
  outline-offset: 3px;
}
#wizard-screen .wbtn-ghost { border-color: #35413f; color: #aebbb7; }
#wizard-screen .wbtn-ghost:hover { border-color: #64857a; color: #f1f5f2; }
#wizard-screen .wbtn-primary,
#wizard-screen .wizard-header .wbtn-primary {
  border: 1px solid #5bc2a4;
  background: #348e75;
  color: #f5fffb;
  box-shadow: 0 5px 16px rgba(31,112,89,.22);
}
#wizard-screen .wbtn-primary:hover:not(:disabled) { background: #3c9d81; box-shadow: 0 8px 20px rgba(31,112,89,.28); }
#wizard-screen .wbtn-generate { background: #348e75; box-shadow: 0 8px 24px rgba(31,112,89,.23); }
#wizard-screen .wbtn-generate:hover:not(:disabled) { background: #3c9d81; box-shadow: 0 10px 28px rgba(31,112,89,.3); }
#wizard-screen .wbtn-generate:disabled { box-shadow: none; }
#wizard-screen .wizard-footer { padding: 1.15rem clamp(1.25rem, 4vw, 2.6rem); background: #121819; border-top-color: #273231; }
#wizard-screen #wiz-step-counter { color: #91a09b !important; font-variant-numeric: tabular-nums; }
@media (max-width: 640px) {
  #wizard-screen { padding: 1.1rem .7rem 2rem; }
  #wizard-screen .wizard-card { border-radius: 17px; }
  #wizard-screen .wizard-header { gap: 1rem !important; }
  #wizard-screen .wizard-header > div:first-child { min-width: 0 !important; }
  #wizard-screen .wizard-header > div:last-child { width: 100%; }
  #wizard-screen .wizard-header > div:last-child > * { max-width: 100%; }
  #wizard-screen .wizard-footer { gap: .55rem; }
  #wizard-screen .wizard-footer > * { flex: 1 1 auto; }
  #wizard-screen #wiz-step-counter { order: -1; flex-basis: 100%; text-align: center; }
  #wizard-screen .wizard-footer .wbtn { justify-content: center; }
}
@media (prefers-reduced-motion: reduce) {
  #wizard-screen *,
  #wizard-screen *::before,
  #wizard-screen *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: .01ms !important; }
}
.gen-progress { display: none; }
.gen-progress.active { display: none; }

/* ═══════════════════════════════════════════════════════════════
   PREMIUM 3D NEURAL GENERATION OVERLAY (WebCraft AI Engine)
   Colors: Navy #050B1D | Dark Blue #0A1630 | Cyan #20DFFF | Violet #8B5CFF
   ═══════════════════════════════════════════════════════════════ */
#gen-overlay {
  display: none;
  position: fixed; inset: 0; z-index: 99999;
  height: 100vh; height: 100dvh; max-height: 100vh; max-height: 100dvh;
  background: radial-gradient(ellipse 90% 80% at 50% 25%, #0c1838 0%, #050b1d 60%, #02050e 100%);
  align-items: center; justify-content: center;
  flex-direction: column;
  overflow: hidden;
  padding: 58px 16px 16px; box-sizing: border-box;
  perspective: 1200px;
}
#gen-overlay.active { display: flex; animation: genOverlayFadeIn 0.45s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes genOverlayFadeIn { from { opacity: 0; transform: scale(0.985); } to { opacity: 1; transform: scale(1); } }

/* Perspective Cyber Grid Floor */
#gen-overlay::before {
  content: '';
  position: absolute; bottom: 0; left: -30%; right: -30%; height: 50%;
  background-image:
    linear-gradient(to right, rgba(32, 223, 255, 0.10) 1px, transparent 1px),
    linear-gradient(to top, rgba(139, 92, 255, 0.10) 1px, transparent 1px);
  background-size: 48px 48px;
  transform: perspective(450px) rotateX(68deg);
  transform-origin: bottom center;
  mask-image: radial-gradient(ellipse 70% 60% at 50% 90%, black 25%, transparent 80%);
  -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 90%, black 25%, transparent 80%);
  pointer-events: none; z-index: 0;
  animation: cyberGridScroll 12s linear infinite;
}
@keyframes cyberGridScroll { from { background-position: 0 0; } to { background-position: 0 48px; } }

/* Horizontal Laser Scanner Beam */
#gen-overlay::after {
  content: '';
  position: absolute; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent 0%, rgba(32, 223, 255, 0.8) 50%, rgba(139, 92, 255, 0.8) 75%, transparent 100%);
  box-shadow: 0 0 24px rgba(32, 223, 255, 0.9), 0 0 48px rgba(139, 92, 255, 0.6);
  animation: laserScanSweep 5s ease-in-out infinite;
  pointer-events: none; z-index: 1;
}
@keyframes laserScanSweep {
  0%   { top: 6%; opacity: 0; }
  12%  { opacity: 0.9; }
  88%  { opacity: 0.9; }
  100% { top: 94%; opacity: 0; }
}

/* ── A. Cybernetic Top Status Bar ── */
.gen-hud-bar {
  position: absolute; top: 0; left: 0; right: 0; height: 50px;
  background: rgba(5, 11, 29, 0.88); backdrop-filter: blur(14px);
  border-bottom: 1px solid rgba(32, 223, 255, 0.22);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 1.8rem; z-index: 15;
}
.gen-hud-left, .gen-hud-right { display: flex; align-items: center; gap: 0.75rem; }
.gen-hud-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #10b981;
  box-shadow: 0 0 10px #10b981, 0 0 20px rgba(16, 185, 129, 0.6);
  animation: hudDotPulse 1.4s ease-in-out infinite alternate;
}
@keyframes hudDotPulse { 0% { opacity: 0.45; transform: scale(0.9); } 100% { opacity: 1; transform: scale(1.15); } }
.gen-hud-mono {
  font-family: 'Fira Code', monospace; font-size: 0.72rem; color: #94a3b8; font-weight: 600; letter-spacing: 0.06em;
}
.gen-hud-badge {
  background: linear-gradient(135deg, rgba(32, 223, 255, 0.16), rgba(139, 92, 255, 0.16));
  border: 1px solid rgba(32, 223, 255, 0.4); border-radius: 999px;
  color: #20dfff; font-size: 0.68rem; font-weight: 800; padding: 0.22rem 0.7rem;
  letter-spacing: 0.04em; box-shadow: 0 0 12px rgba(32, 223, 255, 0.2);
}
.hud-eq { display: inline-flex; align-items: flex-end; gap: 2px; height: 14px; }
.hud-eq i {
  width: 3px; height: 14px; border-radius: 2px;
  background: linear-gradient(180deg, #20dfff, #8b5cff);
  box-shadow: 0 0 8px rgba(32, 223, 255, 0.8);
  transform-origin: bottom; animation: eqB 0.9s ease-in-out infinite;
}
.hud-eq i:nth-child(2) { animation-delay: 0.15s; }
.hud-eq i:nth-child(3) { animation-delay: 0.3s; }
.hud-eq i:nth-child(4) { animation-delay: 0.45s; }
@keyframes eqB { 0%,100% { transform: scaleY(0.3); } 50% { transform: scaleY(1); } }

.gen-clock {
  --p: 0;
  width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
  background: conic-gradient(from -90deg, #20dfff calc(var(--p) * 1%), rgba(255,255,255,0.08) 0);
  box-shadow: 0 0 14px rgba(32, 223, 255, 0.45);
}
.gen-clock .gc-face {
  position: relative; width: 30px; height: 30px; border-radius: 50%;
  background: radial-gradient(circle at 50% 35%, #0a1630, #050b1d 80%);
  border: 1px solid rgba(32, 223, 255, 0.45);
}
.gen-clock .gc-face i { position: absolute; left: 50%; bottom: 50%; transform-origin: 50% 100%; border-radius: 2px; }
.gen-clock .gc-h { width: 3px; height: 7px; margin-left: -1.5px; background: #e2e8f0; animation: gcSpin 120s linear infinite; }
.gen-clock .gc-m { width: 2px; height: 10px; margin-left: -1px; background: #20dfff; animation: gcSpin 60s linear infinite; }
.gen-clock .gc-s { width: 1.5px; height: 12px; margin-left: -0.75px; background: #8b5cff; box-shadow: 0 0 6px #8b5cff; animation: gcSpin 12s linear infinite; }
@keyframes gcSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.gen-clock .gc-pin { position: absolute; left: 50%; top: 50%; width: 4px; height: 4px; margin: -2px 0 0 -2px; border-radius: 50%; background: #fff; box-shadow: 0 0 6px #fff; }

/* ── Ambient FX Aurora Blobs ── */
.gen-aurora { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
.gen-aurora span { position: absolute; width: 42vmax; height: 42vmax; border-radius: 50%; filter: blur(95px); opacity: 0.28; }
.gen-aurora .ab1 { background: #20dfff; top: -10%; left: -6%; animation: auroraDrift 12s ease-in-out infinite alternate; }
.gen-aurora .ab2 { background: #8b5cff; bottom: -15%; right: -8%; animation: auroraDrift 14s ease-in-out infinite alternate-reverse; }
.gen-aurora .ab3 { background: #06b6d4; top: 38%; left: 52%; width: 28vmax; height: 28vmax; opacity: 0.18; animation: auroraDrift 10s ease-in-out infinite alternate; }
@keyframes auroraDrift { from { transform: translate(0,0) scale(1); } to { transform: translate(5vmax,4vmax) scale(1.12); } }

/* ── G. Floating Analysis Panels (Desktop Flanks) ── */
.gen-floating-flank {
  position: absolute; top: 50%; transform: translateY(-50%);
  display: flex; flex-direction: column; gap: 1.1rem;
  z-index: 2; pointer-events: none;
}
.gen-flank-left { left: max(22px, 3.5vw); }
.gen-flank-right { right: max(22px, 3.5vw); }

.gen-float-card {
  width: 232px; background: rgba(10, 22, 48, 0.92);
  border: 1px solid rgba(32, 223, 255, 0.22); border-radius: 14px;
  padding: 0.85rem 1rem; backdrop-filter: blur(14px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.55), 0 0 20px rgba(32, 223, 255, 0.08);
  animation: floatCardDrift 6s ease-in-out infinite alternate;
}
.gen-flank-right .gen-float-card { animation-duration: 7s; animation-direction: alternate-reverse; }
@keyframes floatCardDrift {
  0% { transform: translateY(0); }
  100% { transform: translateY(-6px); }
}
.gfc-header { display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.35rem; }
.gfc-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.gfc-dot.cyan { background: #20dfff; box-shadow: 0 0 8px #20dfff; }
.gfc-dot.violet { background: #8b5cff; box-shadow: 0 0 8px #8b5cff; }
.gfc-dot.green { background: #10b981; box-shadow: 0 0 8px #10b981; }
.gfc-title { font-size: 0.78rem; font-weight: 700; color: #f8fafc; }
.gfc-tag {
  margin-left: auto; font-family: 'Fira Code', monospace; font-size: 0.58rem; font-weight: 800;
  color: #20dfff; background: rgba(32, 223, 255, 0.14); padding: 0.1rem 0.42rem; border-radius: 4px;
}
.gfc-desc { font-size: 0.7rem; color: #94a3b8; line-height: 1.4; margin-bottom: 0.45rem; }
.gfc-bar { height: 4px; border-radius: 2px; background: rgba(255, 255, 255, 0.08); overflow: hidden; }
.gfc-bar i { display: block; height: 100%; border-radius: 2px; background: linear-gradient(90deg, #20dfff, #8b5cff); }

/* ── Central Main Content Dashboard (88–95% Opaque Dark Blue Surface) ── */
.gen-overlay-content {
  position: relative; z-index: 2;
  width: min(670px, 94vw); max-width: 670px;
  max-height: calc(100vh - 72px); max-height: calc(100dvh - 72px);
  background: rgba(10, 22, 48, 0.93);
  border: 1px solid rgba(32, 223, 255, 0.28);
  border-radius: 22px;
  padding: 1.35rem 1.65rem;
  backdrop-filter: blur(18px);
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.75), 0 0 35px rgba(32, 223, 255, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.12);
  display: flex; flex-direction: column; align-items: center;
  box-sizing: border-box;
  overflow-y: auto; overflow-x: hidden; scrollbar-width: none;
}
.gen-overlay-content::-webkit-scrollbar { display: none; }

/* B. Main Header & Live Status */
.gen-main-header { display: flex; flex-direction: column; align-items: center; margin-bottom: 0.2rem; }
.gen-overlay-title {
  font-size: clamp(1.2rem, 2.2vw, 1.55rem); font-weight: 800; letter-spacing: -0.025em; margin: 0 0 0.35rem;
  background: linear-gradient(135deg, #ffffff 20%, #e0f2fe 65%, #c7d2fe 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  text-shadow: 0 0 35px rgba(32, 223, 255, 0.25); text-align: center;
}
.gen-live-status-badge {
  display: inline-flex; align-items: center; gap: 0.45rem;
  padding: 0.24rem 0.75rem; border-radius: 999px;
  background: rgba(32, 223, 255, 0.10); border: 1px solid rgba(32, 223, 255, 0.35);
  font-size: 0.72rem; font-weight: 700; color: #20dfff; margin-bottom: 0.95rem;
}
.gls-dot {
  width: 7px; height: 7px; border-radius: 50%; background: #10b981;
  box-shadow: 0 0 8px #10b981; animation: hudDotPulse 1.4s ease-in-out infinite;
}

/* C. Six-Stage Generation Progress (Row 1: 3 steps, Row 2: 3 steps) */
.wc-stages {
  width: 100%; display: flex; flex-direction: column; gap: 0.42rem; margin-bottom: 0.95rem;
}
.wc-stage-row { display: grid; gap: 0.42rem; width: 100%; }
.wc-stage-row.row-1 { grid-template-columns: repeat(3, 1fr); }
.wc-stage-row.row-2 { grid-template-columns: repeat(3, 1fr); }
.wc-stage {
  display: flex; align-items: center; gap: 0.45rem;
  padding: 0.36rem 0.55rem; border-radius: 9px;
  font-size: 0.69rem; font-weight: 600; text-align: left;
  background: rgba(5, 11, 29, 0.85); border: 1px solid rgba(148, 163, 184, 0.16);
  color: #64748b; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  white-space: nowrap; overflow: hidden;
}
.wc-stage .n {
  width: 18px; height: 18px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 0.62rem; font-weight: 800; font-family: 'Fira Code', monospace;
  background: rgba(255, 255, 255, 0.08); color: #94a3b8; flex-shrink: 0;
}
.wc-stage .stage-label { overflow: hidden; text-overflow: ellipsis; }

/* Stage Completed */
.wc-stage.done {
  border-color: rgba(16, 185, 129, 0.45); color: #34d399; background: rgba(16, 185, 129, 0.08);
}
.wc-stage.done .n {
  background: #10b981; color: #ffffff; box-shadow: 0 0 8px #10b981;
}

/* Stage Active (Refined Cyan-to-Violet Glow Pulse) */
.wc-stage.active {
  border-color: #20dfff; color: #ffffff;
  background: linear-gradient(135deg, rgba(32, 223, 255, 0.22), rgba(139, 92, 255, 0.22));
  box-shadow: 0 0 18px rgba(32, 223, 255, 0.42);
  animation: activeStageGlow 2.2s ease-in-out infinite;
}
.wc-stage.active .n {
  background: #20dfff; color: #020617; box-shadow: 0 0 10px #20dfff;
}
@keyframes activeStageGlow {
  0%, 100% { box-shadow: 0 0 14px rgba(32, 223, 255, 0.35); border-color: rgba(32, 223, 255, 0.8); }
  50% { box-shadow: 0 0 24px rgba(139, 92, 255, 0.65); border-color: #8b5cff; }
}

/* D. Current Analysis Message Panel (Dedicated, readable dark card) */
.gen-analysis-card {
  width: 100%; background: rgba(5, 11, 29, 0.88);
  border: 1px solid rgba(139, 92, 255, 0.28); border-radius: 14px;
  padding: 0.8rem 1.1rem; margin-bottom: 0.95rem; box-sizing: border-box; text-align: center;
}
.gen-overlay-stage {
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  font-size: 0.82rem; font-weight: 700; color: #f1f5f9; min-height: 1.3em; margin-bottom: 0.4rem;
}
.gen-overlay-stage .stage-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #20dfff;
  box-shadow: 0 0 10px #20dfff; animation: stageDotPulse 1.2s ease-in-out infinite; flex-shrink: 0;
}
@keyframes stageDotPulse { 0%,100% { transform: scale(0.9); opacity: 0.7; } 50% { transform: scale(1.3); opacity: 1; } }
.gen-overlay-stage .stage-think { display: inline-flex; gap: 3px; margin-left: 2px; }
.gen-overlay-stage .stage-think i { width: 5px; height: 5px; border-radius: 50%; background: #8b5cff; animation: thinkDot 1.1s ease-in-out infinite; }
.gen-overlay-stage .stage-think i:nth-child(2) { animation-delay: 0.2s; }
.gen-overlay-stage .stage-think i:nth-child(3) { animation-delay: 0.4s; }
@keyframes thinkDot { 0%,100% { opacity: 0.3; transform: scale(0.8); } 50% { opacity: 1; transform: scale(1.2); } }

.gen-brief-summary {
  font-size: 0.76rem; color: #c7d2fe; font-weight: 600; margin-bottom: 0.45rem; text-align: center;
}
.gen-analysis-brief {
  display: flex; flex-wrap: wrap; justify-content: center; gap: 0.4rem; margin: 0;
}
.gen-pill {
  background: rgba(10, 22, 48, 0.90); border: 1px solid rgba(32, 223, 255, 0.32);
  padding: 0.22rem 0.68rem; border-radius: 999px; font-size: 0.7rem; color: #94a3b8;
  display: inline-flex; align-items: center; gap: 0.35rem; transition: all 0.3s;
}
.gen-pill.scanning { color: #e0e7ff !important; border-color: #8b5cff !important; box-shadow: 0 0 12px rgba(139, 92, 255, 0.4); }
.gen-pill.locked { color: #34d399 !important; border-color: rgba(16, 185, 129, 0.55) !important; }

/* E. Progress Bar & Percentage Section */
.gen-progress-section { width: 100%; margin-bottom: 0.85rem; }
.gen-pct-row {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.45rem;
}
.gen-pct-wrap {
  display: flex; align-items: baseline; gap: 0.12rem;
  font-family: 'Fira Code', monospace; font-size: 1.75rem; font-weight: 900;
  background: linear-gradient(135deg, #ffffff 40%, #20dfff 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  filter: drop-shadow(0 0 16px rgba(32, 223, 255, 0.5));
}
.gen-pct-wrap .pct-symbol { font-size: 1.15rem; color: #20dfff; -webkit-text-fill-color: #20dfff; }
.gen-ai-ticker {
  flex: 1; max-width: 440px; display: flex; align-items: center; gap: 0.5rem;
  padding: 0.32rem 0.8rem; border-radius: 999px;
  background: rgba(5, 11, 29, 0.88); border: 1px solid rgba(32, 223, 255, 0.32);
  box-shadow: 0 0 16px rgba(32, 223, 255, 0.12);
  font-family: 'Fira Code', monospace; font-size: 0.67rem; font-weight: 600; color: #a5f3fc;
  overflow: hidden;
}
.gen-ai-ticker .tk-orb {
  width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
  background: #20dfff; box-shadow: 0 0 8px #20dfff; animation: tkOrbPulse 1.4s ease-in-out infinite;
}
@keyframes tkOrbPulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: 0.6; } }
.gen-ai-ticker .tk-tx { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gen-ai-ticker .tk-conf { flex-shrink: 0; font-weight: 800; color: #34d399; }

/* Wide Neon Progress Track with Smooth Cyan-to-Violet Animated Gradient */
.gen-overlay-track {
  width: 100%; height: 8px; background: rgba(255, 255, 255, 0.08);
  border-radius: 999px; overflow: visible; position: relative; margin-bottom: 0.5rem;
  box-shadow: 0 0 0 1px rgba(32, 223, 255, 0.25), 0 0 16px rgba(32, 223, 255, 0.14);
}
.gen-overlay-fill {
  height: 100%; width: 5%; border-radius: 999px;
  background: linear-gradient(90deg, #20dfff 0%, #8b5cff 100%);
  background-size: 200% 100%;
  animation: progressGradientShimmer 2.4s linear infinite;
  box-shadow: 0 0 16px rgba(32, 223, 255, 0.85), 0 0 32px rgba(139, 92, 255, 0.4);
  transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
}
@keyframes progressGradientShimmer { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }
.gen-overlay-fill::after {
  content: ''; position: absolute; right: -3px; top: 50%; width: 14px; height: 14px; margin-top: -7px;
  border-radius: 50%; background: radial-gradient(circle, #fff 0%, #20dfff 45%, transparent 75%);
  filter: blur(1px); box-shadow: 0 0 14px #20dfff, 0 0 28px rgba(139, 92, 255, 0.9);
  animation: fillCometPulse 1.6s ease-in-out infinite;
}
@keyframes fillCometPulse { 0%,100% { transform: scale(0.85); opacity: 0.8; } 50% { transform: scale(1.3); opacity: 1; } }

#gen-eta-line {
  font-family: 'Fira Code', monospace; font-size: 0.71rem; font-weight: 700; color: #20dfff; text-align: center;
}
.wc-err { display: none; margin-top: 0.5rem; font-size: 0.76rem; color: #fca5a5; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); padding: 0.4rem 0.8rem; border-radius: 8px; text-align: center; }
.wc-err.show { display: block; }
.wc-err button { background: #20dfff; color: #020617; border: none; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 6px; cursor: pointer; margin-left: 0.5rem; }

/* F. Skill & Processing Indicators */
.gen-steps {
  display: flex; gap: 0.38rem; width: 100%; justify-content: center; flex-wrap: wrap; margin-bottom: 0;
}
.gen-step {
  display: inline-flex; align-items: center; gap: 0.35rem;
  font-size: 0.66rem; font-weight: 700; color: #64748b;
  background: rgba(5, 11, 29, 0.80); border: 1px solid rgba(148, 163, 184, 0.16);
  border-radius: 999px; padding: 0.24rem 0.62rem; transition: all 0.3s ease;
}
.gen-step .gs-dot { width: 6px; height: 6px; border-radius: 50%; background: #334155; flex-shrink: 0; }
.gen-step.active {
  color: #20dfff; border-color: rgba(32, 223, 255, 0.55); background: rgba(32, 223, 255, 0.12);
  box-shadow: 0 0 12px rgba(32, 223, 255, 0.32);
}
.gen-step.active .gs-dot { background: #20dfff; box-shadow: 0 0 8px #20dfff; animation: stageDotPulse 0.9s ease-in-out infinite; }
.gen-step.done {
  color: #34d399; border-color: rgba(16, 185, 129, 0.45); background: rgba(16, 185, 129, 0.08);
}
.gen-step.done .gs-dot { background: #10b981; box-shadow: 0 0 6px #10b981; }

/* ── Responsive Viewport Rules ── */
@media (max-width: 1140px) {
  .gen-floating-flank { display: none !important; }
}
@media (max-width: 680px) {
  #gen-overlay { padding: 52px 10px 10px; }
  .gen-overlay-content { width: 96vw; padding: 1.15rem 1rem; border-radius: 16px; }
  .gen-hud-bar { padding: 0 1rem; }
  .gen-hud-badge { display: none; }
  .wc-stage-row.row-1, .wc-stage-row.row-2 { grid-template-columns: repeat(2, 1fr); max-width: 100%; }
  .wc-stage-row.row-2 .wc-stage:last-child { grid-column: span 2; }
  .gen-pct-row { flex-direction: column; align-items: center; gap: 0.35rem; }
  .gen-ai-ticker { width: 100%; max-width: 100%; justify-content: center; }
}
@media (max-height: 720px) {
  .gen-overlay-content { padding: 0.95rem 1.2rem; }
  .gen-overlay-title { font-size: 1.15rem; margin-bottom: 0.2rem; }
  .gen-live-status-badge { margin-bottom: 0.6rem; padding: 0.18rem 0.6rem; font-size: 0.68rem; }
  .wc-stages { margin-bottom: 0.6rem; gap: 0.3rem; }
  .wc-stage { padding: 0.28rem 0.45rem; font-size: 0.65rem; }
  .gen-analysis-card { padding: 0.6rem 0.8rem; margin-bottom: 0.6rem; }
  .gen-progress-section { margin-bottom: 0.55rem; }
}
@media (prefers-reduced-motion: reduce) {
  #gen-overlay::before, #gen-overlay::after, .gen-aurora span, .gen-clock .gc-face i, .hud-eq i, .gen-overlay-fill, .gen-float-card {
    animation: none !important;
  }
}


/* ── Premium Neural Upgrade: particles (JS spawns .gen-particle), pct pop,
   mode badge, central CSS-3D robot stage, readability + responsive polish ── */
.gen-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 1; }
.gen-particle {
  position: absolute; bottom: -12px; border-radius: 50%;
  box-shadow: 0 0 8px currentColor;
  animation: genParticleRise linear infinite;
  will-change: transform, opacity;
}
@keyframes genParticleRise {
  0% { transform: translateY(0) translateX(0); opacity: 0; }
  12% { opacity: 0.75; }
  100% { transform: translateY(-108vh) translateX(24px); opacity: 0; }
}
.gen-pct-wrap { transition: transform 0.25s ease; }
.gen-pct-wrap.pop { animation: genPctPop 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
@keyframes genPctPop { 0% { transform: scale(1); } 40% { transform: scale(1.12); } 100% { transform: scale(1); } }
.gen-hud-badge.mode {
  background: linear-gradient(135deg, rgba(139, 92, 255, 0.18), rgba(32, 223, 255, 0.18));
  border-color: rgba(139, 92, 255, 0.5); color: #c4b5fd;
}
.gen-hud-mono { font-variant-numeric: tabular-nums; white-space: nowrap; }

/* Central robot stage: compact focal scene above the heading.
   Layered CSS 3D only — never rotates a flat image; the real 360° model
   (WCRobot3D / Three.js) continues as the ambient background layer. */
.gen-robot-stage {
  position: relative; width: 100%; height: 148px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 0.15rem; perspective: 700px; overflow: visible;
}
.gr-glow {
  position: absolute; width: 300px; height: 150px; border-radius: 50%;
  background: radial-gradient(ellipse at center, rgba(32, 223, 255, 0.22) 0%, rgba(139, 92, 255, 0.14) 45%, transparent 70%);
  filter: blur(6px); pointer-events: none;
}
.gr-orbit {
  position: absolute; left: 50%; top: 50%; border-radius: 50%;
  border: 1px solid rgba(32, 223, 255, 0.35); pointer-events: none;
  transform-style: preserve-3d;
}
.gr-orbit-1 { width: 168px; height: 52px; margin: -26px 0 0 -84px; transform: rotateX(72deg); animation: grOrbitSpin 14s linear infinite; box-shadow: 0 0 14px rgba(32, 223, 255, 0.18); }
.gr-orbit-2 { width: 208px; height: 64px; margin: -32px 0 0 -104px; transform: rotateX(72deg); animation: grOrbitSpin 20s linear infinite reverse; border-color: rgba(139, 92, 255, 0.4); box-shadow: 0 0 14px rgba(139, 92, 255, 0.2); }
.gr-orbit-3 { width: 248px; height: 40px; margin: -20px 0 0 -124px; transform: rotateX(75deg); opacity: 0.45; border-style: dashed; animation: grOrbitSpin 26s linear infinite; }
@keyframes grOrbitSpin { from { rotate: 0deg; } to { rotate: 360deg; } }
.gr-sat { position: absolute; top: -4px; left: 50%; width: 8px; height: 8px; margin-left: -4px; border-radius: 50%; background: #20dfff; box-shadow: 0 0 10px #20dfff, 0 0 20px rgba(32, 223, 255, 0.7); }
.gr-sat-2 { background: #a78bfa; box-shadow: 0 0 10px #8b5cff, 0 0 20px rgba(139, 92, 255, 0.7); }
.gr-bot {
  position: relative; width: 74px; height: 74px; border-radius: 24px; z-index: 2;
  background: linear-gradient(160deg, #f1f5f9 0%, #cbd5e1 38%, #0f172a 39%, #1e293b 100%);
  border: 1px solid rgba(32, 223, 255, 0.55);
  box-shadow: 0 14px 34px rgba(0, 0, 0, 0.6), 0 0 26px rgba(32, 223, 255, 0.35), 0 0 44px rgba(139, 92, 255, 0.2), inset 0 2px 0 rgba(255, 255, 255, 0.65);
  display: flex; align-items: center; justify-content: center;
  animation: grFloat 3.4s ease-in-out infinite;
  transform-style: preserve-3d;
}
@keyframes grFloat { 0%, 100% { transform: translateY(0) rotate(-1.6deg); } 50% { transform: translateY(-9px) rotate(1.6deg); } }
.gr-halo {
  position: absolute; top: -14px; left: 50%; width: 44px; height: 10px; margin-left: -22px; border-radius: 50%;
  border: 2px solid rgba(32, 223, 255, 0.85); box-shadow: 0 0 12px rgba(32, 223, 255, 0.8);
  animation: grHaloBob 3.4s ease-in-out infinite;
}
@keyframes grHaloBob { 0%, 100% { transform: translateY(0); opacity: 0.9; } 50% { transform: translateY(-3px); opacity: 1; } }
.gr-head {
  position: absolute; top: 14px; left: 50%; width: 46px; height: 26px; margin-left: -23px; border-radius: 13px;
  background: linear-gradient(180deg, #020617, #0f172a);
  border: 1px solid rgba(32, 223, 255, 0.4);
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.gr-eye { width: 9px; height: 11px; border-radius: 5px; background: #a5f3fc; box-shadow: 0 0 10px #22d3ee, 0 0 18px rgba(34, 211, 238, 0.8); animation: grBlink 4.2s infinite; }
@keyframes grBlink { 0%, 93%, 100% { transform: scaleY(1); } 95.5% { transform: scaleY(0.08); } }
.gr-visor-line { position: absolute; bottom: 3px; left: 8px; right: 8px; height: 2px; border-radius: 2px; background: linear-gradient(90deg, transparent, #20dfff, transparent); opacity: 0.8; }
.gr-core {
  position: absolute; bottom: 10px; left: 50%; width: 22px; height: 22px; margin-left: -11px; border-radius: 50%;
  background: radial-gradient(circle at 35% 35%, #fff 0%, #22d3ee 40%, #0e7490 100%);
  border: 2px solid rgba(255, 255, 255, 0.7);
  box-shadow: 0 0 14px #22d3ee, 0 0 30px rgba(34, 211, 238, 0.55);
  animation: grCorePulse 2.2s ease-in-out infinite;
}
@keyframes grCorePulse { 0%, 100% { transform: scale(1); filter: brightness(1); } 50% { transform: scale(1.12); filter: brightness(1.25); } }
.gr-holo {
  position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
  width: 190px; padding: 0.4rem 0.6rem 0.5rem; border-radius: 10px; z-index: 1;
  background: rgba(5, 11, 29, 0.9); border: 1px solid rgba(32, 223, 255, 0.3);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5), 0 0 16px rgba(32, 223, 255, 0.12);
}
.gr-holo-bar { display: block; height: 7px; border-radius: 4px; margin-bottom: 5px; background: linear-gradient(90deg, #20dfff, #8b5cff); opacity: 0.85; animation: grHoloShimmer 2.6s ease-in-out infinite; }
.gr-holo-bar.short { width: 62%; opacity: 0.55; }
@keyframes grHoloShimmer { 0%, 100% { opacity: 0.55; } 50% { opacity: 0.95; } }
.gr-holo-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; }
.gr-holo-grid i { height: 14px; border-radius: 3px; background: rgba(32, 223, 255, 0.14); border: 1px solid rgba(32, 223, 255, 0.25); }
.gr-holo-grid i:nth-child(2n) { background: rgba(139, 92, 255, 0.16); border-color: rgba(139, 92, 255, 0.3); }
.gr-sparks i {
  position: absolute; width: 4px; height: 4px; border-radius: 50%;
  background: #67e8f9; box-shadow: 0 0 8px #22d3ee; opacity: 0;
  animation: grSparkFloat 3.8s ease-in-out infinite;
}
.gr-sparks i:nth-child(1) { left: 30%; bottom: 26px; animation-delay: 0s; }
.gr-sparks i:nth-child(2) { left: 42%; bottom: 18px; animation-delay: 0.7s; background: #c4b5fd; box-shadow: 0 0 8px #8b5cff; }
.gr-sparks i:nth-child(3) { left: 58%; bottom: 22px; animation-delay: 1.3s; }
.gr-sparks i:nth-child(4) { left: 68%; bottom: 28px; animation-delay: 1.9s; background: #6ee7b7; box-shadow: 0 0 8px #10b981; }
.gr-sparks i:nth-child(5) { left: 50%; bottom: 14px; animation-delay: 2.5s; }
.gr-sparks i:nth-child(6) { left: 36%; bottom: 12px; animation-delay: 3s; background: #c4b5fd; box-shadow: 0 0 8px #8b5cff; }
@keyframes grSparkFloat { 0% { transform: translateY(0) scale(0.6); opacity: 0; } 30% { opacity: 0.95; } 100% { transform: translateY(-46px) scale(1.15); opacity: 0; } }

/* Readability: keep decorative layers behind text, strengthen panel contrast */
#gen-overlay .wc-robot-3d-wrap { opacity: 0.55; }
#gen-overlay.active .wc-robot-3d-wrap { opacity: 0.55; }
.gen-overlay-content { isolation: isolate; }
.gen-analysis-card, .gen-ai-ticker, .wc-stage, .gen-step { text-shadow: none; }

/* Responsive polish: HUD wraps without collision; stage scales down */
@media (max-width: 760px) {
  .gen-hud-bar { height: auto; min-height: 50px; padding: 0.4rem 0.9rem; flex-wrap: wrap; row-gap: 0.25rem; }
  .gen-hud-mono { font-size: 0.62rem; }
  .gen-hud-badge.mode { display: none; }
  .gen-robot-stage { height: 126px; }
  .gr-orbit-3 { display: none; }
  .gr-holo { width: 160px; }
}
@media (max-width: 420px) {
  .gen-robot-stage { height: 112px; }
  .gr-bot { width: 62px; height: 62px; border-radius: 20px; }
  .gr-head { width: 40px; height: 23px; margin-left: -20px; top: 12px; }
  .gr-orbit-1 { width: 142px; height: 44px; margin: -22px 0 0 -71px; }
  .gr-orbit-2 { width: 176px; height: 54px; margin: -27px 0 0 -88px; }
}
@media (max-height: 720px) {
  .gen-robot-stage { height: 104px; }
  .gr-holo { height: 54px; }
}
@media (prefers-reduced-motion: reduce) {
  .gen-particle, .gr-bot, .gr-orbit, .gr-halo, .gr-core, .gr-eye,
  .gr-holo-bar, .gr-sparks i, .gen-pct-wrap.pop {
    animation: none !important;
  }
  .gen-robot-stage { perspective: none; }
}


/* ── REFERENCE-MATCH PASS: open full-screen neural scene (no central card) ──
   Mirrors the approved loading-screen reference: hero robot + holo platform,
   connected 4+3 stage pills, icon skill badges, bar + % progress row. */
#gen-overlay { padding: 56px 20px 18px; }
.gen-overlay-content {
  width: min(1060px, 96vw); max-width: 1060px;
  background: transparent; border: none; border-radius: 0;
  padding: 0.4rem 0.6rem 0.6rem; backdrop-filter: none;
  box-shadow: none; gap: 0.85rem;
}
#gen-overlay .wc-robot-3d-wrap { opacity: 0.8; }
#gen-overlay.active .wc-robot-3d-wrap { opacity: 0.8; }

/* Hero robot scene */
.gen-robot-stage { height: 268px; margin-bottom: 0; }
.gr-glow { width: 520px; height: 240px; }
.gr-bot { width: 122px; height: 122px; border-radius: 38px; }
.gr-halo { top: -20px; width: 72px; height: 15px; margin-left: -36px; }
.gr-head { top: 24px; width: 74px; height: 42px; margin-left: -37px; border-radius: 21px; gap: 12px; }
.gr-eye { width: 14px; height: 18px; border-radius: 8px; }
.gr-visor-line { left: 13px; right: 13px; bottom: 5px; }
.gr-core { bottom: 16px; width: 34px; height: 34px; margin-left: -17px; }
.gr-orbit-1 { width: 300px; height: 92px; margin: -46px 0 0 -150px; }
.gr-orbit-2 { width: 372px; height: 112px; margin: -56px 0 0 -186px; }
.gr-orbit-3 { width: 440px; height: 70px; margin: -35px 0 0 -220px; }
.gr-sat { width: 10px; height: 10px; margin-left: -5px; }
/* Holographic platform table under the bot */
.gr-holo {
  bottom: 2px; width: 360px; height: 92px; padding: 0; border-radius: 12px;
  background:
    linear-gradient(rgba(32, 223, 255, 0.16) 1px, transparent 1px),
    linear-gradient(90deg, rgba(32, 223, 255, 0.16) 1px, transparent 1px),
    linear-gradient(180deg, rgba(32, 223, 255, 0.10), rgba(139, 92, 255, 0.14));
  background-size: 26px 26px, 26px 26px, 100% 100%;
  border: 1px solid rgba(32, 223, 255, 0.5);
  box-shadow: 0 0 26px rgba(32, 223, 255, 0.35), 0 0 60px rgba(139, 92, 255, 0.22), inset 0 0 30px rgba(32, 223, 255, 0.12);
  transform: translateX(-50%) perspective(520px) rotateX(58deg);
  transform-origin: center bottom;
}
.gr-holo-bar, .gr-holo-grid { display: none; }
.gr-sparks i { animation-duration: 4.4s; }
.gr-sparks i:nth-child(1) { left: 38%; bottom: 60px; }
.gr-sparks i:nth-child(2) { left: 46%; bottom: 48px; }
.gr-sparks i:nth-child(3) { left: 55%; bottom: 56px; }
.gr-sparks i:nth-child(4) { left: 62%; bottom: 62px; }
.gr-sparks i:nth-child(5) { left: 50%; bottom: 40px; }
.gr-sparks i:nth-child(6) { left: 42%; bottom: 36px; }

/* Heading */
.gen-main-header { margin-bottom: 0; }
.gen-overlay-title { font-size: clamp(1.6rem, 3.4vw, 2.4rem); margin-bottom: 0.55rem; }
.gen-live-status-badge { font-size: 0.8rem; padding: 0.34rem 1rem; margin-bottom: 0.2rem; }

/* Connected stage pills (4 + 3) */
.wc-stages { width: min(980px, 94vw); gap: 0.85rem; margin-bottom: 0.2rem; }
.wc-stage-row { gap: 0.9rem; position: relative; }
.wc-stage-row.row-1 { grid-template-columns: repeat(4, 1fr); }
.wc-stage-row.row-2 { grid-template-columns: repeat(3, 1fr); max-width: 76%; }
/* connector dash line behind pills */
.wc-stage-row::before {
  content: ''; position: absolute; left: 5%; right: 5%; top: 50%;
  border-top: 2px dashed rgba(56, 189, 248, 0.35); z-index: 0; pointer-events: none;
}
.wc-stage {
  position: relative; z-index: 1;
  gap: 0.6rem; padding: 0.65rem 0.9rem; border-radius: 18px;
  font-size: 0.8rem; font-weight: 700; line-height: 1.25; text-align: left;
  background: rgba(8, 17, 38, 0.82); border: 1.5px solid rgba(56, 189, 248, 0.28);
  color: #e2e8f0; white-space: normal; overflow: visible;
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(10px);
}
.wc-stage .n {
  width: 34px; height: 34px; font-size: 1rem;
  background: linear-gradient(135deg, #0ea5e9, #8b5cff); color: #fff;
  box-shadow: 0 0 14px rgba(56, 189, 248, 0.55); flex-shrink: 0;
}
.wc-stage .st-ic { font-size: 1.15rem; flex-shrink: 0; filter: drop-shadow(0 0 6px rgba(56, 189, 248, 0.6)); }
.wc-stage .stage-label { overflow: visible; text-overflow: clip; }
.wc-stage.done { border-color: rgba(16, 185, 129, 0.55); background: rgba(6, 40, 30, 0.82); color: #a7f3d0; }
.wc-stage.done .n { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 0 14px rgba(16, 185, 129, 0.6); }
.wc-stage.active {
  border-color: #22d3ee; color: #fff;
  background: linear-gradient(135deg, rgba(34, 211, 238, 0.20), rgba(139, 92, 255, 0.20)), rgba(8, 17, 38, 0.88);
  box-shadow: 0 0 22px rgba(34, 211, 238, 0.5), 0 10px 28px rgba(0, 0, 0, 0.45);
}
.wc-stage.active .n { background: linear-gradient(135deg, #22d3ee, #38bdf8); color: #020617; }

/* Message bar */
.gen-analysis-card {
  width: min(880px, 100%); background: rgba(8, 17, 38, 0.85);
  border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 18px;
  padding: 0.95rem 1.3rem; margin-bottom: 0; backdrop-filter: blur(12px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
}
.gen-overlay-stage { font-size: 0.95rem; gap: 0.65rem; margin-bottom: 0.55rem; }
.gen-overlay-stage .stage-dot { display: none; }
.msg-ic {
  width: 38px; height: 38px; border-radius: 12px; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;
  background: linear-gradient(135deg, rgba(34, 211, 238, 0.25), rgba(139, 92, 255, 0.25));
  border: 1px solid rgba(56, 189, 248, 0.4); box-shadow: 0 0 14px rgba(56, 189, 248, 0.3);
}
#gen-overlay-text { text-align: left; }
.gen-brief-summary { text-align: left; }
.gen-analysis-brief { justify-content: flex-start; }

/* Progress: bar + % at right, footer line, compact ticker */
.gen-progress-section { width: min(880px, 100%); margin-bottom: 0; }
.gen-track-row { display: flex; align-items: center; gap: 0.9rem; margin-bottom: 0.55rem; }
.gen-track-row .gen-overlay-track { flex: 1; margin-bottom: 0; height: 10px; }
.gen-pct-wrap { font-size: 1.05rem; flex-shrink: 0; min-width: 3.2rem; text-align: right; }
.gen-pct-wrap .pct-symbol { font-size: 0.85rem; }
#gen-eta-line { font-size: 0.8rem; margin-bottom: 0.5rem; }
.gen-ai-ticker { max-width: 100%; margin: 0 auto; font-size: 0.7rem; padding: 0.35rem 0.9rem; }

/* Skill badges with icons + skill-range labels */
.gen-steps { gap: 0.55rem; }
.gen-step {
  font-size: 0.74rem; padding: 0.42rem 0.9rem;
  background: rgba(8, 17, 38, 0.82); border: 1px solid rgba(56, 189, 248, 0.25);
  backdrop-filter: blur(10px); box-shadow: 0 8px 22px rgba(0, 0, 0, 0.4);
}
.gen-step .gs-ic { font-size: 0.9rem; line-height: 1; }
.gen-step .gs-dot { display: none; }
.gen-step.active .gs-ic { filter: drop-shadow(0 0 6px rgba(32, 223, 255, 0.9)); }
.gen-step.done .gs-ic { filter: none; }

/* Flank cards: icon box, tilt, aligned to hero height */
.gen-floating-flank { top: 30%; gap: 1.4rem; perspective: 900px; }
.gen-flank-left { left: max(26px, 4vw); }
.gen-flank-right { right: max(26px, 4vw); }
.gen-float-card {
  width: 252px; background: rgba(8, 17, 38, 0.86);
  border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 16px;
  padding: 0.95rem 1.1rem;
}
.gen-flank-left .gen-float-card { transform: perspective(900px) rotateY(9deg); }
.gen-flank-right .gen-float-card { transform: perspective(900px) rotateY(-9deg); }
.gen-flank-left .gen-float-card:last-child { transform: perspective(900px) rotateY(6deg) translateY(8px); }
.gen-flank-right .gen-float-card:last-child { transform: perspective(900px) rotateY(-6deg) translateY(8px); }
.gen-float-card { animation: floatCardDrift 6s ease-in-out infinite alternate; }
.gfc-header { gap: 0.6rem; margin-bottom: 0.45rem; }
.gfc-ic {
  width: 38px; height: 38px; border-radius: 12px; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem;
  background: linear-gradient(135deg, rgba(34, 211, 238, 0.22), rgba(139, 92, 255, 0.22));
  border: 1px solid rgba(56, 189, 248, 0.4); box-shadow: 0 0 12px rgba(56, 189, 248, 0.25);
}
.gfc-dot { display: none; }
.gfc-title { font-size: 0.84rem; }
.gfc-desc { font-size: 0.74rem; margin-bottom: 0.55rem; }
.gfc-bar { height: 5px; }

/* HUD: violet analysis badge + clock icon */
.gen-hud-badge { background: linear-gradient(135deg, rgba(139, 92, 255, 0.25), rgba(99, 102, 241, 0.25)); border-color: rgba(139, 92, 255, 0.55); color: #ddd6fe; }
.gen-hud-badge.mode { background: linear-gradient(135deg, rgba(139, 92, 255, 0.18), rgba(32, 223, 255, 0.18)); border-color: rgba(139, 92, 255, 0.5); color: #c4b5fd; }
#gen-hud-time::before { content: '◷ '; color: #7dd3fc; }

/* Reference-match responsive */
@media (max-width: 1240px) {
  .gen-floating-flank { display: none !important; }
}
@media (max-width: 760px) {
  #gen-overlay { padding: 54px 10px 12px; }
  .gen-robot-stage { height: 200px; }
  .gr-bot { width: 92px; height: 92px; border-radius: 30px; }
  .gr-head { top: 18px; width: 58px; height: 34px; margin-left: -29px; }
  .gr-core { bottom: 13px; width: 26px; height: 26px; margin-left: -13px; }
  .gr-orbit-1 { width: 230px; height: 70px; margin: -35px 0 0 -115px; }
  .gr-orbit-2 { width: 286px; height: 86px; margin: -43px 0 0 -143px; }
  .gr-orbit-3 { display: none; }
  .gr-holo { width: 280px; height: 72px; }
  .gen-overlay-title { font-size: clamp(1.3rem, 6vw, 1.7rem); }
  .wc-stage-row.row-1, .wc-stage-row.row-2 { grid-template-columns: repeat(2, 1fr); max-width: 100%; }
  .wc-stage-row.row-2 .wc-stage:last-child { grid-column: span 2; }
  .wc-stage-row::before { display: none; }
  .wc-stage { font-size: 0.74rem; padding: 0.55rem 0.7rem; }
  .wc-stage .n { width: 28px; height: 28px; font-size: 0.85rem; }
  .gen-overlay-stage { font-size: 0.84rem; }
  #gen-overlay-text { text-align: center; }
  .gen-brief-summary { text-align: center; }
  .gen-analysis-brief { justify-content: center; }
}
@media (max-width: 420px) {
  .gen-robot-stage { height: 172px; }
  .gr-bot { width: 76px; height: 76px; border-radius: 24px; }
  .gr-head { top: 15px; width: 48px; height: 28px; margin-left: -24px; gap: 8px; }
  .gr-eye { width: 10px; height: 13px; }
  .gr-holo { width: 230px; height: 60px; }
  .gen-step { font-size: 0.68rem; padding: 0.36rem 0.7rem; }
}
@media (max-height: 760px) {
  .gen-robot-stage { height: 190px; }
  .gr-holo { display: block; height: 66px; }
  .gen-overlay-title { font-size: 1.35rem; margin-bottom: 0.3rem; }
  .wc-stages { gap: 0.55rem; }
  .wc-stage { padding: 0.5rem 0.7rem; font-size: 0.74rem; }
  .gen-analysis-card { padding: 0.7rem 1rem; }
}
@media (prefers-reduced-motion: reduce) {
  .wc-stage.active, .gen-float-card, .gr-orbit { animation: none !important; }
}
/* Overlay box-model + tilt-preserving drift (individual `translate` composes
   with the cards' `transform` tilt instead of overriding it) */
#gen-overlay *, #gen-overlay *::before, #gen-overlay *::after { box-sizing: border-box; }
@keyframes floatCardDrift { 0% { translate: 0 0; } 100% { translate: 0 -6px; } }


/* DESIGNS SCREEN */
#designs-screen { display: none; min-height: calc(100vh - 64px); padding: 3rem 1.5rem 5rem; background: radial-gradient(circle at 50% 10%, #1e1b4b 0%, #0a0d14 60%); }
.designs-header { text-align: center; max-width: 780px; margin: 0 auto 3rem; }
.designs-badge { display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3); color: #a5b4fc; padding: 0.35rem 0.9rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; margin-bottom: 1rem; }
.designs-header h1 { font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; color: #fff; letter-spacing: -0.02em; margin-bottom: 0.75rem; }
.designs-header p { font-size: 1.05rem; color: #94a3b8; line-height: 1.6; }
.designs-grid { max-width: 1300px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
@media (max-width: 990px) { .designs-grid { grid-template-columns: 1fr; max-width: 650px; } }
.design-card { background: #111622; border: 1.5px solid #283347; border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 15px 40px rgba(0,0,0,0.4); transition: transform 0.25s, border-color 0.25s, box-shadow 0.25s; position: relative; }
.design-card:hover { transform: translateY(-6px); border-color: #6366f1; box-shadow: 0 20px 50px rgba(99,102,241,0.25); }
.design-badge-top { position: absolute; top: 1rem; left: 1rem; z-index: 10; background: rgba(15,23,42,0.85); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15); color: #38bdf8; font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.75rem; border-radius: 999px; }
.design-number-badge { position: absolute; top: 1rem; right: 1rem; z-index: 10; width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; font-size: 0.82rem; font-weight: 900; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(79,70,229,0.5); }
.design-preview-box { height: 300px; background: #0b0f17; position: relative; overflow: hidden; border-bottom: 1px solid #1e293b; }
.pv-spin { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: #0b0f1a; z-index: 2; pointer-events: none; }
.design-preview-iframe { width: 1400px; height: 900px; border: none; transform: scale(0.33); transform-origin: top left; pointer-events: none; }
.design-preview-box::after { content: '👁 Click "Full Preview" to see it live'; position: absolute; bottom: 0; left: 0; right: 0; padding: 1.2rem 1rem 0.6rem; background: linear-gradient(to top, rgba(11,15,23,0.95), transparent); color: #94a3b8; font-size: 0.75rem; font-weight: 600; text-align: center; pointer-events: none; }
.design-content { padding: 1.75rem; display: flex; flex-direction: column; flex: 1; }
.design-content h3 { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
.design-content p { font-size: 0.88rem; color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem; flex: 1; }
.design-actions { display: grid; grid-template-columns: 1fr 1.4fr; gap: 0.75rem; }
.btn-preview-modal, .btn-choose-design { padding: 0.75rem; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-align: center; transition: all 0.2s; }
.btn-preview-modal { border: 1.5px solid #283347; background: #0b0f17; color: #cbd5e1; }
.btn-preview-modal:hover { border-color: #6366f1; color: #fff; }
.btn-choose-design { border: none; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; font-weight: 700; box-shadow: 0 4px 15px rgba(79,70,229,0.35); }
.btn-choose-design:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,70,229,0.5); }
.btn-admin-preview { margin-top: 0.65rem; padding: 0.7rem; border-radius: 10px; border: 1.5px solid #0891b2; background: rgba(8,145,178,0.12); color: #67e8f9; font-weight: 700; font-size: 0.82rem; cursor: pointer; width: 100%; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; }
.btn-admin-preview:hover { background: rgba(8,145,178,0.25); border-color: #06b6d4; color: #fff; }

/* ══ BUILDER SCREEN ══ */
#builder-screen { display: none; height: calc(100vh - 64px); flex-direction: column; background: #090d14; }
.builder-toolbar { min-height: 56px; background: #101522; border-bottom: 1px solid #1e293b; display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 1rem; flex-shrink: 0; flex-wrap: wrap; }
.tb-title { font-weight: 800; font-size: 0.9rem; color: #818cf8; display: flex; align-items: center; gap: 0.4rem; white-space: nowrap; }
.tb-site-badge { background: #1e1b4b; border: 1px solid rgba(99,102,241,0.3); color: #c7d2fe; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 6px; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tb-divider { width: 1px; height: 24px; background: #1e293b; }
.design-tabs { display: flex; gap: 0.35rem; }
.d-tab { padding: 0.35rem 0.75rem; border-radius: 7px; border: 1px solid #283347; background: #0b0f17; color: #94a3b8; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.15s; font-family: inherit; }
.d-tab.active { background: #4f46e5; border-color: #6366f1; color: #fff; box-shadow: 0 0 10px rgba(99,102,241,0.4); }
.view-tabs { display: none; gap: 0.3rem; padding: 3px; background: #080c14; border-radius: 9px; border: 1px solid #283347; }
.view-tabs.show { display: flex; }
.view-tab { padding: 0.32rem 0.85rem; border-radius: 7px; border: none; background: transparent; color: #94a3b8; font-size: 0.75rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; white-space: nowrap; }
.view-tab.active { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; }
.view-tab.admin-view.active { background: linear-gradient(135deg, #0891b2, #0369a1); }
.tb-btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.42rem 0.8rem; border-radius: 7px; font-family: inherit; font-size: 0.78rem; font-weight: 600; border: 1px solid #283347; background: #0b0f17; color: #cbd5e1; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.tb-btn:hover { background: #1e293b; border-color: #64748b; }
.tb-btn.visual-btn { background: linear-gradient(135deg, #4f46e5, #7c3aed); border: 1px solid #818cf8; color: #fff; font-weight: 700; }
.tb-btn.undo-btn { background: #2d1b4e; color: #e9d5ff; border-color: #6b21a8; }
.tb-btn.next-btn { background: linear-gradient(135deg, #10b981, #059669); border: 1px solid #34d399; color: #fff; font-weight: 800; }
.tb-btn.publish-btn { background: linear-gradient(135deg, #f59e0b, #d97706); border: 1px solid #fbbf24; color: #fff; font-weight: 800; box-shadow: 0 4px 14px rgba(245,158,11,0.4); }
.tb-btn.publish-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(245,158,11,0.55); }
.tb-back-btn { background: #0b0f17; border: 1.5px solid #334155; color: #fff; font-weight: 800; text-decoration: none; }
.tb-back-btn:hover { border-color: #6366f1; background: #1e1b4b; color: #fff; }
/* ★ Edit workspace focus mode: company navbar + footer hidden inside edit view.
   No outer scroll — only the preview iframe scrolls. Confusion remove. */
body.builder-focus { overflow: hidden; height: 100vh; }
body.builder-focus .navbar, body.builder-focus .mobile-menu { display: none !important; }
body.builder-focus footer { display: none !important; }
body.builder-focus #builder-screen { height: 100vh; min-height: 100vh; }
body.builder-focus .resume-banner { top: 12px; }
.builder-main { display: flex; flex: 1; overflow: hidden; }
.preview-pane { flex: 1; display: flex; flex-direction: column; background: #0d121c; width: 100%; height: 100%; }
.preview-bar { background: #0d121c; border-bottom: 1px solid #1e293b; padding: 0 1rem; height: 42px; display: flex; align-items: center; justify-content: space-between; }
.device-toggles { display: flex; gap: 0.3rem; }
.device-btn { padding: 0.28rem 0.6rem; border: 1px solid #283347; border-radius: 6px; background: transparent; color: #94a3b8; cursor: pointer; font-size: 0.75rem; font-family: inherit; }
.device-btn.active { color: #fff; border-color: #6366f1; background: #1e1b4b; }
.tb-btn.edit-on { border-color: #6366f1 !important; background: #1e1b4b !important; color: #fff !important; }
.preview-container { flex: 1; display: flex; justify-content: center; background: #06090e; overflow: hidden; position: relative; }
/* ★ Tab-load detector overlay: shown only if the frame takes >350ms to render */
.pv-frame-spin { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(6,9,14,.6); z-index: 5; pointer-events: none; }
#live-iframe { width: 100%; height: 100%; border: none; background: #fff; transition: width 0.3s ease; }
.preview-container.laptop #live-iframe { width: 1280px; max-width: 100%; }
.preview-container.tablet #live-iframe { width: 768px; max-width: 100%; }
.preview-container.mobile #live-iframe { width: 390px; max-width: 100%; }
/* Fullscreen modal device preview: same widths as the workspace preview,
   centered with dark gutters so tablet/phone views read clearly. */
.modal-iframe { transition: width 0.3s ease, max-width 0.3s ease; }
#modal-iframe.m-desktop { width: 100%; }
#modal-iframe.m-laptop { width: 1280px; max-width: 100%; margin: 0 auto; flex: none; height: 100%; }
#modal-iframe.m-tablet { width: 768px; max-width: 100%; margin: 0 auto; flex: none; height: 100%; }
#modal-iframe.m-mobile { width: 390px; max-width: 100%; margin: 0 auto; flex: none; height: 100%; border-left: 1px solid #283347; border-right: 1px solid #283347; }

/* ══ FLOATING AI BUTTON ══ */
.floating-gemini-btn { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 1000; display: inline-flex; align-items: center; gap: 0.55rem; padding: 0.68rem 1.25rem 0.68rem 0.9rem; border-radius: 999px; border: 1px solid rgba(129,140,248,0.35); background: linear-gradient(135deg, #4f46e5 0%, #6d5ce8 55%, #8b5cf6 100%); color: #fff; font-family: inherit; font-size: 0.85rem; font-weight: 700; letter-spacing: -0.005em; box-shadow: 0 10px 28px rgba(79,70,229,0.45), 0 0 0 1px rgba(255,255,255,0.06) inset; cursor: pointer; transition: transform 0.22s cubic-bezier(0.22,1,0.36,1), box-shadow 0.22s; }
.floating-gemini-btn::before { content: ''; position: absolute; inset: 0; border-radius: inherit; background: linear-gradient(135deg, rgba(255,255,255,0.18), transparent 55%); pointer-events: none; }
.floating-gemini-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 36px rgba(79,70,229,0.6), 0 0 0 1px rgba(255,255,255,0.1) inset; }
.floating-gemini-btn:active { transform: translateY(0) scale(0.98); }
.floating-gemini-btn .fg-orb { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: rgba(255,255,255,0.18); font-size: 0.82rem; line-height: 1; position: relative; }
.floating-gemini-btn .fg-orb::after { content: ''; position: absolute; inset: -3px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.35); animation: orbPulse 2s ease-in-out infinite; }
@keyframes orbPulse { 0%,100% { transform: scale(1); opacity: 0.6; } 50% { transform: scale(1.15); opacity: 0; } }

/* ══ AI CHAT PANEL ══ */
.magic-ai-panel { position: fixed; bottom: 5.5rem; right: 1.5rem; width: min(420px, calc(100vw - 2rem)); height: min(620px, calc(100vh - 8rem)); background: #0c1220; border: 1px solid #1e293b; border-radius: 20px; box-shadow: 0 24px 70px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.03) inset; z-index: 1001; display: none; flex-direction: column; overflow: hidden; font-family: 'Inter', system-ui, sans-serif; }
.magic-ai-panel.active { display: flex; animation: magicPanelIn 0.28s cubic-bezier(0.22,1,0.36,1); }
@keyframes magicPanelIn { from { opacity: 0; transform: translateY(14px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
.magic-header { padding: 0.85rem 0.9rem 0.85rem 1rem; background: linear-gradient(180deg, #121a2e 0%, #0d1424 100%); border-bottom: 1px solid #1c2740; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-shrink: 0; position: relative; }
.magic-header::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 1px; background: linear-gradient(90deg, transparent, rgba(129,140,248,0.45), transparent); }
.magic-brand { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
.magic-avatar { position: relative; width: 34px; height: 34px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #4f46e5, #7c3aed 55%, #a855f7); box-shadow: 0 4px 14px rgba(124,58,237,0.45); font-size: 1rem; color: #fff; }
.magic-avatar::after { content: ''; position: absolute; right: -2px; bottom: -2px; width: 10px; height: 10px; border-radius: 50%; background: #10b981; border: 2px solid #0d1424; box-shadow: 0 0 8px rgba(16,185,129,0.8); }
.magic-brand-text { display: flex; flex-direction: column; min-width: 0; }
.magic-brand-name { font-size: 0.86rem; font-weight: 800; color: #fff; letter-spacing: -0.01em; line-height: 1.15; }
.magic-brand-sub { font-size: 0.68rem; color: #94a3b8; font-weight: 600; display: flex; align-items: center; gap: 0.3rem; margin-top: 1px; }
.magic-brand-sub .dot { width: 5px; height: 5px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981; }
.magic-header-actions { display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0; }
.magic-icon-btn { width: 28px; height: 28px; border-radius: 8px; border: none; background: transparent; color: #64748b; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; cursor: pointer; transition: all 0.15s; font-family: inherit; line-height: 1; }
.magic-icon-btn:hover { background: #1a2338; color: #fff; }
.ai-model-row { display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0.85rem; background: #080c14; border-bottom: 1px solid #162032; font-size: 0.7rem; gap: 0.5rem; flex-shrink: 0; }
.ai-model-dropdown { background: #111726; border: 1px solid #283347; color: #e2e8f0; border-radius: 6px; font-size: 0.7rem; padding: 0.2rem 0.45rem; font-family: inherit; outline: none; }
.magic-chat-log { flex: 1; overflow-y: auto; padding: 1rem 0.9rem 1.1rem; display: flex; flex-direction: column; gap: 0.9rem; scroll-behavior: smooth; background: radial-gradient(120% 60% at 50% 0%, rgba(79,70,229,0.06), transparent 60%); }
.magic-chat-log::-webkit-scrollbar { width: 6px; }
.magic-chat-log::-webkit-scrollbar-thumb { background: #243049; border-radius: 999px; }
.msg { display: flex; gap: 0.55rem; align-items: flex-end; max-width: 100%; animation: msgIn 0.3s cubic-bezier(0.22,1,0.36,1); }
@keyframes msgIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.msg.user { flex-direction: row-reverse; }
.msg-avatar { width: 26px; height: 26px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.68rem; font-weight: 800; line-height: 1; }
.msg.ai .msg-avatar { background: linear-gradient(135deg, #4f46e5, #a855f7); color: #fff; box-shadow: 0 3px 10px rgba(124,58,237,0.35); }
.msg.user .msg-avatar { background: #1e293b; color: #c7d2fe; border: 1px solid #334155; }
.msg-body { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; max-width: calc(100% - 42px); }
.msg.user .msg-body { align-items: flex-end; }
.msg-bubble { position: relative; padding: 0.6rem 0.85rem; border-radius: 14px; font-size: 0.82rem; line-height: 1.55; color: #e2e8f0; word-wrap: break-word; overflow-wrap: anywhere; }
.msg.ai .msg-bubble { background: #141c2f; border: 1px solid #1e293b; border-bottom-left-radius: 5px; }
.msg.user .msg-bubble { background: linear-gradient(135deg, #4f46e5, #6d5ce8); color: #fff; border-bottom-right-radius: 5px; box-shadow: 0 4px 14px rgba(79,70,229,0.32); }
.msg-bubble strong { color: #fff; font-weight: 800; }
.msg-bubble a { color: #a5b4fc; }
.msg-bubble code { background: #1e293b; padding: 0.1rem 0.35rem; border-radius: 4px; color: #a5b4fc; font-size: 0.75rem; }
.msg-meta { display: flex; align-items: center; gap: 0.35rem; font-size: 0.62rem; color: #64748b; font-weight: 600; padding: 0 0.25rem; }
.msg-copy { opacity: 0; background: transparent; border: none; color: #64748b; font-size: 0.62rem; font-weight: 700; cursor: pointer; padding: 0.1rem 0.35rem; border-radius: 4px; transition: all 0.15s; font-family: inherit; }
.msg:hover .msg-copy { opacity: 1; }
.msg-copy:hover { background: #1e293b; color: #c7d2fe; }
.typing-dots { display: inline-flex; gap: 0.28rem; align-items: center; padding: 0.1rem 0; }
.typing-dots i { width: 6px; height: 6px; border-radius: 50%; background: #818cf8; display: block; animation: tdWave 1.2s infinite ease-in-out; }
.typing-dots i:nth-child(2) { animation-delay: 0.15s; }
.typing-dots i:nth-child(3) { animation-delay: 0.3s; }
@keyframes tdWave { 0%,60%,100% { transform: translateY(0); opacity: 0.4; } 30% { transform: translateY(-5px); opacity: 1; } }
.m-chip-row { display: flex; gap: 0.4rem; overflow-x: auto; padding: 0.65rem 0.9rem; border-top: 1px solid #1c2740; background: #0a0f1c; flex-shrink: 0; scrollbar-width: none; }
.m-chip-row::-webkit-scrollbar { display: none; }
.m-chip { padding: 0.36rem 0.75rem; border-radius: 999px; border: 1px solid #243049; background: #0f1729; color: #cbd5e1; font-size: 0.72rem; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.16s; display: inline-flex; align-items: center; gap: 0.3rem; font-family: inherit; }
.m-chip:hover { border-color: #6366f1; color: #fff; background: #1a2140; transform: translateY(-1px); }
.magic-input-row { display: flex; gap: 0.5rem; padding: 0.7rem 0.9rem 0.5rem; background: #0a0f1c; flex-shrink: 0; align-items: flex-end; flex-wrap: wrap; }
.magic-input-wrap { flex: 1 1 200px; min-width: 0; display: flex; align-items: center; gap: 0.5rem; background: #080c14; border: 1.5px solid #243049; border-radius: 12px; padding: 0.05rem 0.2rem 0.05rem 0.75rem; transition: border-color 0.18s, box-shadow 0.18s; position: relative; }
.magic-input-wrap:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.16); }
.magic-input-icon { color: #64748b; font-size: 0.85rem; flex-shrink: 0; line-height: 1; }
.magic-input { flex: 1; min-width: 0; padding: 0.62rem 0; border: none; background: transparent; color: #fff; font-family: inherit; font-size: 0.84rem; outline: none; }
.magic-input::placeholder { color: #475569; }
.magic-btn { width: 38px; height: 38px; border-radius: 11px; border: none; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; cursor: pointer; flex-shrink: 0; box-shadow: 0 4px 14px rgba(79,70,229,0.4); transition: all 0.18s; line-height: 1; }
.magic-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,70,229,0.6); }
.magic-btn:disabled { opacity: 0.55; cursor: wait; transform: none; }
.magic-mic-btn { width: 38px; height: 38px; border-radius: 11px; border: 1.5px solid #243049; background: #080c14; color: #cbd5e1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; transition: all 0.15s; line-height: 1; }
.magic-mic-btn:hover { border-color: #6366f1; color: #fff; }
.magic-mic-btn.recording { background: linear-gradient(135deg, #dc2626, #ef4444); border-color: #f87171; color: #fff; animation: micPulse 1.2s ease-in-out infinite; }
@keyframes micPulse { 0%,100% { box-shadow: 0 0 0 4px rgba(239,68,68,0.25); } 50% { box-shadow: 0 0 0 8px rgba(239,68,68,0.05); } }
.magic-lang-select { height: 38px; padding: 0 0.5rem; border-radius: 11px; border: 1.5px solid #243049; background: #080c14; color: #cbd5e1; font-family: inherit; font-size: 0.7rem; font-weight: 600; cursor: pointer; flex-shrink: 0; }
.magic-hint { font-size: 0.65rem; color: #475569; text-align: center; padding: 0 0.9rem 0.7rem; background: #0a0f1c; font-weight: 600; }
.magic-hint kbd { background: #1e293b; border: 1px solid #334155; border-radius: 4px; padding: 0.05rem 0.32rem; font-family: inherit; font-size: 0.62rem; color: #cbd5e1; }
.voice-listening-toast { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 0.6rem; padding: 0.5rem 1rem; background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; border-radius: 999px; font-size: 0.72rem; font-weight: 700; box-shadow: 0 8px 24px rgba(239,68,68,0.4); display: none; white-space: nowrap; z-index: 100; align-items: center; gap: 0.5rem; }
.voice-listening-toast.show { display: flex; }
.voice-listening-toast::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #fff; animation: blink 0.9s infinite; }
@keyframes blink { 50% { opacity: 0.2; } }
@media (max-width: 480px) {
  .magic-ai-panel { right: 0.75rem; left: 0.75rem; width: auto; bottom: 5rem; }
  .floating-gemini-btn span:not(.fg-orb) { display: none; }
  .floating-gemini-btn { padding: 0.7rem; }
}

/* ══ MODALS ══ */
.modal-overlay { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); align-items: center; justify-content: center; padding: 2rem; }
.modal-overlay.active { display: flex; }
.modal-box { background: #111622; border: 1.5px solid #283347; border-radius: 20px; width: 90%; max-width: 1200px; height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.8); position: relative; }
.modal-bar { padding: 1rem 1.5rem; background: #0d121c; border-bottom: 1px solid #1e293b; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.modal-iframe { flex: 1; border: none; background: #fff; }
.mode-grid { padding: 1.5rem; display: grid; gap: 1rem; grid-template-columns: repeat(3, 1fr); }
@media (max-width: 720px) { .mode-grid { grid-template-columns: 1fr; } }
.gen-mode-card { border: 2px solid #283347; border-radius: 16px; padding: 1.5rem 1.25rem; background: #0b0f17; cursor: pointer; transition: all 0.25s; text-align: left; font-family: inherit; color: inherit; position: relative; }
.gen-mode-card:hover { border-color: #6366f1; transform: translateY(-3px); box-shadow: 0 12px 32px rgba(99,102,241,0.25); }
.gen-mode-card.selected { border-color: #10b981; background: linear-gradient(180deg,#062b22,#0b0f17); }
.gen-mode-card[data-mode="admin"].selected { border-color: #6366f1; background: linear-gradient(180deg,#1e1b4b,#0b0f17); }
.gen-mode-card[data-mode="database"].selected { border-color: #0891b2; background: linear-gradient(180deg,#082f49,#0b0f17); }
.gen-mode-card .gm-icon { font-size: 1.9rem; display: block; margin-bottom: 0.5rem; }
.gen-mode-card h3 { font-size: 1.05rem; font-weight: 800; color: #fff; margin-bottom: 0.4rem; }
.gen-mode-card p { font-size: 0.8rem; color: #94a3b8; line-height: 1.5; margin-bottom: 0.6rem; }
.gen-mode-card .gm-foot { font-size: 0.7rem; color: #818cf8; font-weight: 700; }
.gen-mode-card .gm-badge { position: absolute; top: 0.7rem; right: 0.7rem; background: linear-gradient(135deg,#4f46e5,#7c3aed); color: #fff; font-size: 0.58rem; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 999px; letter-spacing: 0.05em; text-transform: uppercase; }
.gen-mode-card[data-mode="static"] .gm-badge { background: linear-gradient(135deg,#10b981,#059669); }
.gen-mode-card[data-mode="database"] .gm-badge { background: linear-gradient(135deg,#0891b2,#0369a1); }
.toast { position: fixed; bottom: 1.5rem; left: 1.5rem; z-index: 10000; background: #111622; border: 1.5px solid #283347; border-radius: 12px; padding: 0.85rem 1.4rem 1rem; color: #fff; font-size: 0.88rem; font-weight: 600; box-shadow: 0 10px 30px rgba(0,0,0,0.6); transform: translateY(100px); opacity: 0; transition: transform 0.3s ease, opacity 0.3s ease; max-width: 90vw; overflow: hidden; }
.toast.show { transform: translateY(0); opacity: 1; }
.toast.success { border-color: #10b981; box-shadow: 0 10px 30px rgba(0,0,0,0.6), 0 0 18px rgba(16,185,129,0.25); }
.toast.error { border-color: #ef4444; box-shadow: 0 10px 30px rgba(0,0,0,0.6), 0 0 18px rgba(239,68,68,0.25); }
.toast.info { border-color: #6366f1; box-shadow: 0 10px 30px rgba(0,0,0,0.6), 0 0 18px rgba(99,102,241,0.25); }
#toast-bar { position: absolute; bottom: 0; left: 0; height: 3px; width: 100%; background: linear-gradient(90deg, #6366f1, #a855f7); }
.toast.success #toast-bar { background: linear-gradient(90deg, #059669, #34d399); }
.toast.error #toast-bar { background: linear-gradient(90deg, #dc2626, #f87171); }

/* ══ GUIDE MODAL ══ */
.generate-modal-backdrop {
  display: none;
  position: fixed; inset: 0; z-index: 100000;
  background: rgba(0,0,0,0.88);
  backdrop-filter: blur(12px);
  align-items: center; justify-content: center;
  padding: 1.5rem;
}
.generate-modal-backdrop.open {
  display: flex !important;
  animation: guideIn 0.3s cubic-bezier(0.22,1,0.36,1);
}
@keyframes guideIn { from { opacity:0; transform:scale(0.96) translateY(16px); } to { opacity:1; transform:scale(1) translateY(0); } }
.generate-modal-card {
  background: #111622;
  border: 1.5px solid #283347;
  border-radius: 22px;
  width: 100%;
  overflow: hidden;
  box-shadow: 0 30px 80px rgba(0,0,0,0.8), 0 0 0 1px rgba(255,255,255,0.04) inset;
  cursor: default;
}

/* ══ ADMIN REQUIREMENTS (in modal) ══ */
.admin-req-section { display: none; padding: 1.5rem; border-top: 1px solid #1e293b; background: linear-gradient(180deg, #0a0f1c, #0d121c); }
.admin-req-section.active { display: block; animation: fadeIn 0.3s ease; }
.admin-req-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
.admin-req-header .ar-icon { font-size: 1.6rem; }
.admin-req-header h3 { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 0.15rem; }
.admin-req-header p { font-size: 0.78rem; color: #94a3b8; }
.admin-req-textarea { width: 100%; padding: 0.85rem 1rem; border: 1.5px solid #283347; border-radius: 12px; background: #080c14; color: #fff; font-family: inherit; font-size: 0.85rem; line-height: 1.6; resize: vertical; min-height: 140px; }
.admin-req-textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
.admin-preset-row { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.75rem; }
.admin-preset-btn { padding: 0.42rem 0.85rem; border-radius: 9px; border: 1.5px solid #334155; background: #0b0f17; color: #cbd5e1; font-family: inherit; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 0.3rem; }
.admin-preset-btn:hover { border-color: #6366f1; color: #fff; background: #1a1f36; transform: translateY(-1px); }
.admin-ai-note { display: flex; gap: 0.6rem; align-items: flex-start; padding: 0.85rem 1rem; background: rgba(99,102,241,0.08); border: 1.5px solid rgba(99,102,241,0.3); border-radius: 10px; margin-top: 1rem; font-size: 0.78rem; color: #c7d2fe; line-height: 1.5; }
.admin-ai-note .note-icon { font-size: 1.1rem; flex-shrink: 0; }
</style>

<!-- ═══ RESUME BANNER ═══ -->
<div class="resume-banner" id="resume-banner">
  <div class="rb-icon">✨</div>
  <div class="rb-text">
    <strong>Welcome back!</strong> Resumed your last session.
    <small id="resume-details">—</small>
  </div>
  <div class="rb-actions">
    <button class="rb-btn" onclick="dismissResumeBanner()">Dismiss</button>
    <button class="rb-btn danger" onclick="startFreshFromBanner()">Start Fresh</button>
  </div>
</div>

<!-- ═══ 3D GENERATION OVERLAY ═══ -->
<div id="gen-overlay">
  <!-- Top Cyber Status Bar (A) -->
  <div class="gen-hud-bar">
    <div class="gen-hud-left">
      <span class="gen-hud-dot"></span>
      <span class="hud-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
      <span class="gen-hud-mono">AI-FLOWCRAFT: AUTONOMOUS NEURAL SYNTHESIS</span>
    </div>
    <div class="gen-hud-right">
      <span class="gen-hud-badge mode" id="gen-hud-mode" title="Generation mode">⚡ STATIC</span>
      <span class="gen-hud-badge">✦ DEEP ANALYSIS · 3 VARIATIONS</span>
      <span class="gen-clock" id="gen-clock" title="Elapsed generation time"><span class="gc-face"><i class="gc-h"></i><i class="gc-m"></i><i class="gc-s"></i><b class="gc-pin"></b></span></span>
      <span class="gen-hud-mono" id="gen-hud-time">0.0s</span>
      <span class="gen-hud-mono" id="gen-hud-eta" title="Estimated time remaining" style="color:#20DFFF;">~90s left</span>
    </div>
  </div>

  <!-- Ambient FX & 3D Robot Background Layer -->
  <div class="gen-aurora"><span class="ab1"></span><span class="ab2"></span><span class="ab3"></span></div>
  <div id="wc-robot-3d-canvas-wrap" class="wc-robot-3d-wrap" aria-hidden="true">
    <canvas id="wc-robot-3d-canvas"></canvas>
    <div class="wc-robot-3d-vignette"></div>
  </div>
  <div class="gen-particles" id="gen-particles"></div>

  <!-- G. Floating Analysis Panels (Desktop Flanks) -->
  <div class="gen-floating-flank gen-flank-left" aria-hidden="true">
    <div class="gen-float-card card-ai-analysis">
      <div class="gfc-header">
        <span class="gfc-ic">🧠</span>
        <span class="gfc-dot cyan"></span>
        <span class="gfc-title">AI Analysis</span>
        <span class="gfc-tag">ACTIVE</span>
      </div>
      <div class="gfc-body">
        <div class="gfc-desc">Understanding your business idea…</div>
        <div class="gfc-bar"><i style="width: 82%;"></i></div>
      </div>
    </div>
    <div class="gen-float-card card-neural-proc">
      <div class="gfc-header">
        <span class="gfc-ic">⚡</span>
        <span class="gfc-dot violet"></span>
        <span class="gfc-title">Neural Processing</span>
        <span class="gfc-tag">ACTIVE</span>
      </div>
      <div class="gfc-body">
        <div class="gfc-desc">Building your perfect website…</div>
        <div class="gfc-bar"><i style="width: 68%;"></i></div>
      </div>
    </div>
  </div>

  <div class="gen-floating-flank gen-flank-right" aria-hidden="true">
    <div class="gen-float-card card-design-gen">
      <div class="gfc-header">
        <span class="gfc-ic">🖼️</span>
        <span class="gfc-dot cyan"></span>
        <span class="gfc-title">Design Generation</span>
        <span class="gfc-tag">ACTIVE</span>
      </div>
      <div class="gfc-body">
        <div class="gfc-desc">Creating beautiful layouts…</div>
        <div class="gfc-bar"><i style="width: 88%;"></i></div>
      </div>
    </div>
    <div class="gen-float-card card-web-opt">
      <div class="gfc-header">
        <span class="gfc-ic">🚀</span>
        <span class="gfc-dot green"></span>
        <span class="gfc-title">Deployment Ready</span>
        <span class="gfc-tag">CONNECTED</span>
      </div>
      <div class="gfc-body">
        <div class="gfc-desc">Optimizing for performance…</div>
        <div class="gfc-bar"><i style="width: 95%;"></i></div>
      </div>
    </div>
  </div>

  <!-- Main Content & Progress (High contrast, 92-95% opaque dark blue panel) -->
  <div class="gen-overlay-content">
    <!-- B. Central AI Assistant Scene — layered CSS-3D focal robot (ambient Three.js runs behind overlay) -->
    <div class="gen-robot-stage" aria-hidden="true">
      <div class="gr-glow"></div>
      <div class="gr-orbit gr-orbit-1"><span class="gr-sat gr-sat-1"></span></div>
      <div class="gr-orbit gr-orbit-2"><span class="gr-sat gr-sat-2"></span></div>
      <div class="gr-orbit gr-orbit-3"></div>
      <div class="gr-bot" id="gen-robot-bot">
        <div class="gr-halo"></div>
        <div class="gr-head"><span class="gr-eye"></span><span class="gr-eye"></span><span class="gr-visor-line"></span></div>
        <div class="gr-core"></div>
      </div>
      <div class="gr-holo">
        <span class="gr-holo-bar"></span><span class="gr-holo-bar short"></span>
        <span class="gr-holo-grid"><i></i><i></i><i></i><i></i><i></i><i></i></span>
      </div>
      <div class="gr-sparks"><i></i><i></i><i></i><i></i><i></i><i></i></div>
    </div>
    <!-- B. Central AI Assistant Scene Header -->
    <div class="gen-main-header">
      <h2 class="gen-overlay-title">AI-FlowCraft Neural Design Engine</h2>
      <div class="gen-live-status-badge">
        <span class="gls-dot"></span>
        <span class="gls-text">LIVE · Analyzing your brief &amp; building</span>
      </div>
    </div>

    <!-- C. Six-Stage Generation Progress (Row 1: 3 steps, Row 2: 3 steps) -->
    <div class="wc-stages" id="wc-stages" aria-label="Generation stages">
      <div class="wc-stage-row row-1">
        <span class="wc-stage active" data-wc="0"><span class="n">1</span><span class="st-ic">📋</span><span class="stage-label">Analysing requirements</span></span>
        <span class="wc-stage" data-wc="1"><span class="n">2</span><span class="st-ic">🗺️</span><span class="stage-label">Planning structure</span></span>
        <span class="wc-stage" data-wc="2"><span class="n">3</span><span class="st-ic">🎨</span><span class="stage-label">Generating design</span></span>
      </div>
      <div class="wc-stage-row row-2">
        <span class="wc-stage" data-wc="3"><span class="n">4</span><span class="st-ic">🔌</span><span class="stage-label">Connecting functionality</span></span>
        <span class="wc-stage" data-wc="4"><span class="n">5</span><span class="st-ic">🛡️</span><span class="stage-label">Checking permissions</span></span>
        <span class="wc-stage" data-wc="5"><span class="n">6</span><span class="st-ic">🚀</span><span class="stage-label">Preparing preview</span></span>
      </div>
    </div>

    <!-- D. Current Analysis Message Panel (Dedicated, readable dark card) -->
    <div class="gen-analysis-card">
      <div class="gen-overlay-stage">
        <span class="msg-ic" aria-hidden="true">🧠</span>
        <span class="stage-dot"></span>
        <span id="gen-overlay-text">AI-FlowCraft is analyzing your requirements and preparing your website structure…</span>
        <span class="stage-think" aria-hidden="true"><i></i><i></i><i></i></span>
      </div>

      <!-- Live Requirements Analysis Pill Bar & Summary -->
      <div id="gen-brief-line" class="gen-brief-summary"></div>
      <div class="gen-analysis-brief" id="gen-analysis-brief">
        <span class="gen-pill" id="gpill-biz">🏢 <strong id="gpill-biz-val">Analyzing Business</strong></span>
        <span class="gen-pill" id="gpill-aud">🎯 <strong id="gpill-aud-val">Target Audience</strong></span>
        <span class="gen-pill" id="gpill-style">🎨 <strong id="gpill-style-val">Design Direction</strong></span>
        <span class="gen-pill" id="gpill-mode">⚡ <strong id="gpill-mode-val">Mode: Static</strong></span>
      </div>
    </div>

    <!-- E. Progress Bar and Percentage Section -->
    <div class="gen-progress-section">
      <div class="gen-track-row">
        <!-- Glowing Progress Track -->
        <div class="gen-overlay-track">
          <div class="gen-overlay-fill" id="gen-overlay-fill"></div>
        </div>
        <div class="gen-pct-wrap">
          <span id="gen-pct-num">0</span><span class="pct-symbol">%</span>
        </div>
      </div>
      <div id="gen-eta-line">⏳ Estimated ~90s · MAX tokens · building full project…</div>
      <div class="gen-ai-ticker" id="gen-ai-ticker" aria-live="polite">
        <span class="tk-orb"></span>
        <span class="tk-tx" id="gen-ai-ticker-tx">AI analyzing business DNA…</span>
        <span class="tk-conf" id="gen-ai-ticker-conf">…</span>
      </div>
      <div class="wc-err" id="wc-gen-err" role="alert"></div>
    </div>

    <!-- F. Skill & Processing Indicators -->
    <div class="gen-steps" id="gen-steps" aria-label="Pipeline skills">
      <div class="gen-step active" data-s="0"><span class="gs-ic">📋</span>Skills 1–2: Requirements</div>
      <div class="gen-step" data-s="1"><span class="gs-ic">🧩</span>Skills 3–9: Architecture</div>
      <div class="gen-step" data-s="2"><span class="gs-ic">🛡️</span>Skills 10–18: Standards</div>
      <div class="gen-step" data-s="3"><span class="gs-ic">⚙️</span>Skills 19–21: Features</div>
      <div class="gen-step" data-s="4"><span class="gs-ic">🚀</span>Skills 22–26: QA &amp; Launch</div>
    </div>

    <!-- Legacy elements kept in DOM for 100% JS backward compatibility (zero errors) -->
    <div class="gen-compat-stubs" aria-hidden="true" style="display:none !important;">
      <div class="wc-robot"><div class="wc-robot-bot"><span class="wc-robot-eyes"><i></i><i></i></span></div><div class="wc-robot-meta"><div class="wc-robot-name"></div><div class="wc-robot-status"></div></div></div>
      <div class="gen-stage-wrap"><div class="wcl-hb"></div></div>
      <div class="gen-wire" id="gen-wire"><span class="gw-url" id="gw-url"></span><div class="gw-nav"><span class="gw-logo"></span><span class="gw-links"><i></i></span><span class="gw-cta"></span></div><div class="gw-hero"><span class="gw-h1"></span><span class="gw-sub"></span><div class="gw-btns"><i></i></div></div><div class="gw-cards"><span class="gw-card"><i></i></span></div><div class="gw-foot"><i></i></div><div id="gen-wire-cap"></div></div>
      <div class="gen-insights" id="gen-insights"></div>
      <div class="gen-now" id="gen-now"><span id="gen-now-ic"></span><span id="gen-now-text"></span></div>
      <div class="gen-live-log" id="gen-live-log"><div id="gen-live-log-body"></div></div>
      <div class="gen-terminal-box"><span id="gen-terminal-line"></span></div>
      <div id="gen-overlay-tip" class="gen-overlay-tip"></div>
    </div>
  </div>
</div>

<!-- ═══ WIZARD SCREEN ═══ -->
<div id="wizard-screen" style="flex-direction:column;align-items:center;">
<div style="width:100%;max-width:880px;margin:0 auto 0.9rem;display:flex;justify-content:flex-start;">
  <a href="<?= $customerUser ? SITE_URL . '/customer-portal.php' : SITE_URL . '/index.php' ?>" onclick="return navHomeWithLoader(this)" style="display:inline-flex;align-items:center;gap:0.4rem;background:#0b0f17;border:1.5px solid #334155;color:#fff;font-size:0.82rem;font-weight:800;padding:0.5rem 1.1rem;border-radius:10px;text-decoration:none;">← Back to Home</a>
</div>
<?php if (!empty($loadError)): ?>
  <div style="max-width:900px;margin:0 auto 1rem;background:rgba(239,68,68,.12);border:1.5px solid rgba(239,68,68,.5);color:#fca5a5;padding:.9rem 1.2rem;border-radius:12px;font-size:.88rem;font-weight:600;"><?= htmlspecialchars($loadError) ?> <a href="<?= SITE_URL ?>/customer-portal.php" style="color:#fff;font-weight:800;">Login here</a></div>
<?php endif; ?>
  <div class="wizard-card">
    <div class="wizard-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
      <div style="flex:1; min-width:260px;">
        <h1>✦ AI Website Generator</h1>
        <p>Tell us about your business and design direction. We'll generate <strong>3 style variations</strong> — you pick your favorite.</p>
      </div>
      <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
        <?php if ($customerEmail && !empty($existingProjects)): ?>
        <div style="position:relative; display:inline-block;">
          <button type="button" class="wbtn" onclick="toggleLoadProjectMenu()" style="background:#1e1b4b; border:1.5px solid #6366f1; color:#c7d2fe; font-size:0.82rem; font-weight:800; padding:0.55rem 1.15rem; border-radius:10px; cursor:pointer; display:inline-flex; align-items:center; gap:0.45rem; white-space:nowrap; transition:all 0.15s; flex-shrink:0;">
            <span>📂</span><span>Open Project (<?= count($existingProjects) ?>)</span><span>▾</span>
          </button>
          <div id="load-project-dropdown" style="display:none; position:absolute; right:0; top:calc(100% + 6px); background:#0c1220; border:1px solid #1e293b; border-radius:12px; box-shadow:0 15px 35px rgba(0,0,0,0.7); min-width:280px; z-index:9999; padding:0.5rem; max-height:300px; overflow-y:auto;">
            <div style="font-size:0.7rem; font-weight:700; color:#64748b; padding:0.4rem 0.6rem; text-transform:uppercase;"><?= htmlspecialchars($customerEmail) ?> projects</div>
            <?php foreach ($existingProjects as $ep): ?>
              <a href="builder.php?order_id=<?= urlencode($ep['order_id']) ?>" style="display:block; padding:0.6rem 0.75rem; border-radius:8px; text-decoration:none; color:#e2e8f0; font-size:0.82rem; transition:background 0.15s;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                <div style="font-weight:700; color:#fff;"><?= htmlspecialchars($ep['site_name']) ?></div>
                <div style="font-size:0.7rem; color:#818cf8;"><?= htmlspecialchars($ep['order_id']) ?> &bull; <?= htmlspecialchars($ep['slug']) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php elseif ($customerEmail): ?>
        <span style="font-size:.75rem;color:#34d399;font-weight:700;">Logged in: <?= htmlspecialchars($customerEmail) ?></span>
        <?php else: ?>
        <a href="<?= SITE_URL ?>/customer-portal.php" class="wbtn" style="background:#1e1b4b; border:1.5px solid #6366f1; color:#c7d2fe; font-size:0.82rem; font-weight:800; padding:0.55rem 1.15rem; border-radius:10px; display:inline-flex; align-items:center; gap:0.45rem; text-decoration:none;">Login to open your projects</a>
        <?php endif; ?>

        <div class="db-status-badge" style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.4rem 0.8rem; border-radius:999px; font-size:0.75rem; font-weight:800; background:rgba(16,185,129,0.15); border:1.5px solid #10b981; color:#34d399;" title="Supabase PostgreSQL Cloud Database">
          <span style="width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
          <span>⚡ Supabase Connected</span>
        </div>

        <button type="button" class="wbtn" onclick="openStepByStepGuide()" style="background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.35); color:#fff; font-size:0.82rem; font-weight:800; padding:0.55rem 1.15rem; border-radius:10px; cursor:pointer; display:inline-flex; align-items:center; gap:0.45rem; white-space:nowrap; transition:all 0.15s; flex-shrink:0;" onmouseover="this.style.background='rgba(255,255,255,0.28)'" onmouseout="this.style.background='rgba(255,255,255,0.18)'" title="Click to view full-stack development guide">
          <span>📖</span><span>Admin &amp; Features Guide</span>
        </button>
      </div>
    </div>

    <div class="wizard-progress">
      <div class="wp-dot active" id="wp1"></div>
      <div class="wp-dot" id="wp2"></div>
      <div class="wp-dot" id="wp3"></div>
      <div class="wp-dot" id="wp4"></div>
    </div>

    <div class="wizard-body">
      <!-- STEP 1 -->
      <div class="wizard-step active" id="step1">
        <!-- ★ AI Business Brief: natural-language description → Understand my
             business → Steps 1–3 auto-configured (industry, design hints,
             sections). Everything stays editable; nothing invented. -->
        <div class="w-group" style="background:#0d1424;border:1.5px solid rgba(99,102,241,0.4);border-radius:12px;padding:1rem 1.1rem;margin-bottom:1.1rem;">
          <label class="w-label" for="biz_brief">🧠 AI Business Brief <span style="font-weight:400;color:#94a3b8;">(describe your business in your own words)</span></label>
          <textarea class="w-textarea" id="biz_brief" rows="3" placeholder="e.g. City Express — daily bus service from Chennai to Bangalore with online seat booking, QR tickets and counter payment"></textarea>
          <div style="display:flex;gap:.6rem;align-items:center;margin-top:.6rem;flex-wrap:wrap;">
            <button type="button" class="wbtn wbtn-primary" id="btn-understand-biz" onclick="understandMyBusiness()" style="font-size:.82rem;padding:.55rem 1.1rem;">✨ Understand my business</button>
            <button type="button" class="wbtn wbtn-ghost" id="btn-prompt-helper" onclick="openPromptGenerator()" title="Unga vaarthaila sollunga — atha sariyaana prompt ah maathi tharum" style="font-size:.82rem;padding:.55rem 1.1rem;">💡 Prompt Helper</button>
            <button type="button" class="wbtn wbtn-ghost" id="btn-brandkit-open" onclick="try{window.openBrandKit&&window.openBrandKit()}catch(e){}" style="font-size:.82rem;padding:.55rem 1.1rem;">🎨 Brand Kit</button>
            <span id="biz-brief-result" style="font-size:.78rem;color:#94a3b8;"></span>
          </div>
        </div>
        <script>
        /* Single-brief guard (inline, cache-proof): the canonical #biz_brief
           box above is the ONLY brief UI. If a stale cached script injects a
           second launcher, remove it — on load and on late DOM inserts. */
        (function () {
          function killDup() {
            try {
              if (document.getElementById('biz_brief')) {
                document.querySelectorAll('#wc-brief-launcher').forEach(function (n) { n.remove(); });
              }
            } catch (e) {}
          }
          if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', killDup);
          else killDup();
          setTimeout(killDup, 1500);
          try {
            new MutationObserver(killDup).observe(document.documentElement, { childList: true, subtree: true });
          } catch (e) {}
        })();
        </script>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.1rem; flex-wrap:wrap; gap:0.6rem; background:#0e1320; padding:0.65rem 0.9rem; border-radius:10px; border:1px solid #1e293b;">
          <div>
            <div class="wiz-section-label" style="margin-bottom:0; font-size:0.78rem;"><?= ui_t('b_step1') ?></div>
            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">Enter your business details below, or click Demo to test.</div>
          </div>
          <div style="display:flex; gap:0.45rem; align-items:center;">
            <button type="button" class="btn btn-ghost btn-sm" onclick="fillDemoData('ecommerce')" style="background:#2e1065; border:1px solid #a855f7; color:#f3e8ff; font-size:0.75rem; font-weight:700; padding:0.4rem 0.8rem; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:0.35rem;" title="Fill with complete sample E-Commerce shop & products">
              🛍️ E-Commerce Demo
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="fillDemoData('agency')" style="background:#1e1b4b; border:1px solid #6366f1; color:#c7d2fe; font-size:0.75rem; font-weight:700; padding:0.4rem 0.8rem; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:0.35rem;" title="Fill with sample agency business data">
              🎯 Agency Demo
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="fillDemoData('bus')" style="background:#082f49; border:1px solid #0284c7; color:#e0f2fe; font-size:0.75rem; font-weight:700; padding:0.4rem 0.8rem; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:0.35rem;" title="Fill with bus booking demo — seat booking + QR ticket + backend connected">
              🚌 Bus Demo
            </button>
            <div class="dropdown" style="position:relative; display:inline-block;">
              <button type="button" class="btn btn-ghost btn-sm" onclick="toggleDemoPresetsMenu(event)" style="background:#111827; border:1px solid #374151; color:#cbd5e1; font-size:0.75rem; font-weight:700; padding:0.4rem 0.65rem; border-radius:8px; cursor:pointer;" title="More demo presets">
                ▼
              </button>
              <div id="demo-presets-menu" style="display:none; position:absolute; right:0; top:calc(100% + 5px); background:#0f172a; border:1px solid #334155; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.5); z-index:100; min-width:180px; overflow:hidden;">
                <button type="button" onclick="fillDemoData('ecommerce')" style="width:100%; text-align:left; background:transparent; border:none; padding:0.5rem 0.85rem; color:#e2e8f0; font-size:0.75rem; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:0.4rem;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                  🛍️ E-Commerce Store
                </button>
                <button type="button" onclick="fillDemoData('agency')" style="width:100%; text-align:left; background:transparent; border:none; padding:0.5rem 0.85rem; color:#e2e8f0; font-size:0.75rem; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:0.4rem;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                  💼 Digital Agency
                </button>
                <button type="button" onclick="fillDemoData('cafe')" style="width:100%; text-align:left; background:transparent; border:none; padding:0.5rem 0.85rem; color:#e2e8f0; font-size:0.75rem; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:0.4rem;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                  ☕ Cafe &amp; Bakery
                </button>
                <button type="button" onclick="fillDemoData('saas')" style="width:100%; text-align:left; background:transparent; border:none; padding:0.5rem 0.85rem; color:#e2e8f0; font-size:0.75rem; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:0.4rem;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                  🚀 Tech Startup
                </button>
                <button type="button" onclick="fillDemoData('gym')" style="width:100%; text-align:left; background:transparent; border:none; padding:0.5rem 0.85rem; color:#e2e8f0; font-size:0.75rem; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:0.4rem;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                  🏋️ Fitness Gym
                </button>
                <button type="button" onclick="fillDemoData('bus')" style="width:100%; text-align:left; background:transparent; border:none; padding:0.5rem 0.85rem; color:#e2e8f0; font-size:0.75rem; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:0.4rem;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">
                  🚌 Bus Booking
                </button>
              </div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="clearWizardForm()" style="background:#111827; border:1px solid #374151; color:#9ca3af; font-size:0.75rem; font-weight:600; padding:0.4rem 0.65rem; border-radius:8px; cursor:pointer;" title="Clear all fields">
              🧹 Clear
            </button>
          </div>
        </div>
        <div class="w-row">
          <div class="w-group">
            <label class="w-label" for="biz_name"><?= ui_t('b_biz_name') ?> <span class="req">*</span></label>
            <input class="w-input" type="text" id="biz_name" placeholder="e.g. Apex Studio, Zen Cafe, Dr. Smith Clinic" value="">
          </div>
          <div class="w-group">
            <label class="w-label" for="biz_type"><?= ui_t('b_biz_type') ?> <span class="req">*</span></label>
            <select class="w-select" id="biz_type">
              <?php $inds = ['Creative & Digital Agency','Restaurant, Cafe & Bar','Tech Startup & SaaS','Professional Services & Legal','Healthcare & Wellness Clinic','Real Estate & Architecture','Fitness Center & Personal Trainer','E-Commerce & Retail','Consultant / Advisor','Coach (Life / Business)','Therapist / Counselor','Tradesperson (Plumber / Electrician / Handyman)','Cleaning Service','Tutor / Trainer','Personal Portfolio & Creator','School / College / Education','Bus, Transport & Travel','Salon & Beauty','Hotel & Hospitality','Travel & Tours','Events & Entertainment','Rental & Hire','Automotive & Repairs','Other']; ?>
              <?php foreach ($inds as $ii => $iv): ?>
                <option value="<?= htmlspecialchars($iv) ?>"><?= ui_t('b_ind' . $ii) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_tagline"><?= ui_t('b_biz_tagline') ?> <span class="req">*</span></label>
          <input class="w-input" type="text" id="biz_tagline" placeholder="e.g. Crafting exceptional experiences that inspire and scale." value="">
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_audience"><?= ui_t('b_biz_audience') ?></label>
          <input class="w-input" type="text" id="biz_audience" placeholder="e.g. High-growth brands, modern businesses, local community..." value="">
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_services"><?= ui_t('b_biz_services') ?></label>
          <input class="w-input" type="text" id="biz_services" placeholder="e.g. UI/UX Design, Web Development, Brand Identity, Consulting..." value="">
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_products"><?= ui_t('b_biz_products') ?> <span style="font-weight:400;color:#94a3b8;">(for product companies — one per line)</span></label>
          <textarea class="w-textarea" id="biz_products" rows="4" placeholder="Example:&#10;Cotton T-Shirt | ₹499 | https://example.com/tshirt.jpg&#10;Denim Jacket | ₹1499&#10;Leather Wallet | Ask price"></textarea>
          <div class="w-hint">Format per line: <strong style="color:#a5b4fc">Name | Price | ImageURL (optional)</strong> — no price = Enquire button. Empty = no shop section (unless business type is a shop).</div>
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_reviews"><?= ui_t('b_biz_reviews') ?> <span style="font-weight:400;color:#94a3b8;">(paste from Google/Yelp/Facebook — one per line)</span></label>
          <textarea class="w-textarea" id="biz_reviews" rows="3" placeholder="Example:&#10;Priya Sharma | Fantastic service, highly recommended! | Happy Customer&#10;Rahul Verma | Professional work, on time every time."></textarea>
          <div class="w-hint">Format per line: <strong style="color:#a5b4fc">Name | Review text | Role (optional)</strong> — rendered verbatim in testimonials.</div>
        </div>
      </div>

      <!-- STEP 2 -->
      <div class="wizard-step" id="step2">
        <div class="wiz-section-label"><?= ui_t('b_step2') ?></div>
        <p class="w-hint" style="margin-bottom:1.5rem;">
          Tell us the aesthetic you want. <strong style="color:#a5b4fc">All 3 style variations we generate will follow this direction</strong> — only the layout approach will differ.
        </p>
        <div class="w-group">
          <label class="w-label"><?= ui_t('b_design_style') ?> <span class="req">*</span></label>
          <div class="style-grid">
            <div class="style-card"><input type="radio" name="design_style" id="st_modern" value="modern" checked><label for="st_modern"><span style="font-size:1.4rem">🎯</span><?= ui_t('b_style_modern') ?></label></div>
            <div class="style-card"><input type="radio" name="design_style" id="st_bold" value="bold"><label for="st_bold"><span style="font-size:1.4rem">⚡</span><?= ui_t('b_style_bold') ?></label></div>
            <div class="style-card"><input type="radio" name="design_style" id="st_dark" value="dark"><label for="st_dark"><span style="font-size:1.4rem">🌙</span><?= ui_t('b_style_dark') ?></label></div>
          </div>
        </div>
        <div class="w-group">
          <label class="w-label" for="design_direction"><?= ui_t('b_design_dir') ?></label>
          <textarea class="w-textarea" id="design_direction" rows="3" placeholder="e.g. Minimal Scandinavian with warm neutrals, generous whitespace, editorial typography…"></textarea>
          <div class="w-hint">Free-text adds extra guidance on top of the aesthetic above.</div>
        </div>
        <div class="w-group">
          <label class="w-label"><?= ui_t('b_color') ?></label>
          <div class="color-grid">
            <div class="color-swatch"><input type="radio" name="color_palette" id="cp_purple" value="purple" checked><label for="cp_purple" style="background:linear-gradient(135deg,#6366f1,#a855f7)"></label></div>
            <div class="color-swatch"><input type="radio" name="color_palette" id="cp_blue" value="blue"><label for="cp_blue" style="background:linear-gradient(135deg,#2563eb,#06b6d4)"></label></div>
            <div class="color-swatch"><input type="radio" name="color_palette" id="cp_green" value="green"><label for="cp_green" style="background:linear-gradient(135deg,#059669,#10b981)"></label></div>
            <div class="color-swatch"><input type="radio" name="color_palette" id="cp_red" value="red"><label for="cp_red" style="background:linear-gradient(135deg,#dc2626,#f97316)"></label></div>
            <div class="color-swatch"><input type="radio" name="color_palette" id="cp_gold" value="gold"><label for="cp_gold" style="background:linear-gradient(135deg,#d97706,#f59e0b)"></label></div>
            <div class="color-swatch"><input type="radio" name="color_palette" id="cp_slate" value="slate"><label for="cp_slate" style="background:linear-gradient(135deg,#1e293b,#475569)"></label></div>
          </div>
        </div>
        <div class="w-group taste-block">
          <label class="w-label"><?= ui_t('b_taste') ?> <span style="font-weight:400;color:#94a3b8;">(Taste Skill — reads your brief, sets the taste)</span></label>
          <div class="taste-vibes">
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="auto" checked><span><?= ui_t('b_vibe_auto') ?></span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="minimalist"><span><?= ui_t('b_vibe_min') ?></span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="premium"><span><?= ui_t('b_vibe_prem') ?></span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="playful"><span><?= ui_t('b_vibe_play') ?></span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="editorial"><span><?= ui_t('b_vibe_edit') ?></span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="brutalist"><span><?= ui_t('b_vibe_brut') ?></span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="trust"><span><?= ui_t('b_vibe_trust') ?></span></label>
          </div>
          <div class="taste-dials">
            <div class="taste-dial"><span class="td-name"><?= ui_t('b_dial_var') ?></span><input type="range" id="taste_variance" min="0" max="10" value="0" oninput="updateTasteRead()"><span class="td-val" id="taste_variance_val">Auto</span></div>
            <div class="taste-dial"><span class="td-name"><?= ui_t('b_dial_mot') ?></span><input type="range" id="taste_motion" min="0" max="10" value="0" oninput="updateTasteRead()"><span class="td-val" id="taste_motion_val">Auto</span></div>
            <div class="taste-dial"><span class="td-name"><?= ui_t('b_dial_den') ?></span><input type="range" id="taste_density" min="0" max="10" value="0" oninput="updateTasteRead()"><span class="td-val" id="taste_density_val">Auto</span></div>
          </div>
          <div class="w-hint" id="taste-read">✦ Auto: the AI reads your business + direction and picks the design language + dials (baseline 8/6/4). Move a slider to override.</div>
        </div>
      </div>

      <!-- STEP 3 -->
      <div class="wizard-step" id="step3">
        <div class="wiz-section-label"><?= ui_t('b_step3') ?></div>
        <div class="w-group">
          <label class="w-label"><?= ui_t('b_sections') ?></label>
          <div class="check-grid">
            <label class="wcheck"><input type="checkbox" name="sections[]" value="hero" checked><span><?= ui_t('b_sec_hero') ?></span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="services" checked><span><?= ui_t('b_sec_services') ?></span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="about" checked><span><?= ui_t('b_sec_about') ?></span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="metrics" checked><span><?= ui_t('b_sec_metrics') ?></span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="shop"><span><?= ui_t('b_sec_shop') ?></span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="contact" checked><span><?= ui_t('b_sec_contact') ?></span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="footer" checked><span><?= ui_t('b_sec_footer') ?></span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="gallery" id="sec-gallery" onchange="toggleGallerySubject()"><span><?= ui_t('b_sec_gallery') ?></span></label>
          </div>
          <div id="gallery-subject-row" style="display:none;margin-top:.6rem;background:#0d1424;border:1.5px dashed rgba(99,102,241,0.45);border-radius:10px;padding:.7rem .85rem;">
            <label class="w-label" for="gallery_subject" style="font-size:.78rem;">What should the gallery show? <span class="req">*</span></label>
            <input class="w-input" type="text" id="gallery_subject" placeholder="e.g. photos of our buses and fleet" style="margin-top:.35rem;">
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.5rem;">
              <button type="button" class="admin-preset-btn" onclick="setGallerySubject('photos of our buses and fleet')">🚌 Buses &amp; fleet</button>
              <button type="button" class="admin-preset-btn" onclick="setGallerySubject('photos of our food dishes and restaurant')">🍽️ Food &amp; dishes</button>
              <button type="button" class="admin-preset-btn" onclick="setGallerySubject('photos of our hotel rooms and property')">🏨 Rooms &amp; property</button>
              <button type="button" class="admin-preset-btn" onclick="setGallerySubject('photos of our products')">🛍️ Products</button>
              <button type="button" class="admin-preset-btn" onclick="setGallerySubject('photos of our events and recent work')">🎉 Events &amp; work</button>
              <button type="button" class="admin-preset-btn" onclick="setGallerySubject('photos of our team at work')">👥 Team</button>
            </div>
          </div>
        </div>
      </div>

      <!-- STEP 4 -->
      <div class="wizard-step" id="step4">
        <div class="wiz-section-label"><?= ui_t('b_step4') ?></div>
        <div class="account-bar" id="account-bar" style="background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(16,185,129,0.1)); border: 1.5px solid rgba(99,102,241,0.35);">
          <div class="account-info">
            <div class="account-avatar" id="account-avatar" style="background: linear-gradient(135deg, #10b981, #059669); color:#fff; font-weight:800;">✦</div>
            <div>
              <div class="account-name" id="account-name" style="color:#fff; font-weight:700;">AI Model Ready</div>
              <div class="account-status" id="account-status" style="color:#34d399;">Free AI Models · No sign-in needed</div>
            </div>
          </div>
          <div class="account-actions" id="account-actions">
            <span style="font-size:0.75rem; color:#c7d2fe; background:rgba(99,102,241,0.2); padding:0.35rem 0.8rem; border-radius:999px; border:1px solid rgba(99,102,241,0.35); font-weight:700;">⚡ Ready to Generate</span>
          </div>
        </div>

        <div class="w-row">
          <div class="w-group"><label class="w-label" for="biz_phone"><?= ui_t('b_phone') ?></label><input class="w-input" type="text" id="biz_phone" value="+1 (555) 890-1234"></div>
          <div class="w-group"><label class="w-label" for="biz_email"><?= ui_t('b_email') ?></label><input class="w-input" type="email" id="biz_email" value="hello@zenithstudio.com"></div>
        </div>
        <div class="w-group"><label class="w-label" for="biz_address"><?= ui_t('b_address') ?></label><input class="w-input" type="text" id="biz_address" value="450 Innovation Parkway, San Francisco, CA"></div>

        <div class="w-group">
          <label class="w-label" for="biz_logo"><?= ui_t('b_logo') ?> <span style="font-weight:400;color:#94a3b8;">(optional — empty = text brand name)</span></label>
          <input class="w-input" type="url" id="biz_logo" placeholder="https://example.com/logo.png">
          <div class="w-hint">Customer logo iruntha link kodunga — navbar + footer la <strong style="color:#a5b4fc">img</strong> aaga varum. Vendam-na empty-a vidunga.</div>
        </div>

        <div class="w-group">
          <label class="w-label"><?= ui_t('b_social') ?> <span style="font-weight:400;color:#94a3b8;">(optional — kodutha link mattum footer la varum)</span></label>
          <div class="w-row">
            <div class="w-group"><label class="w-label" for="biz_instagram" style="font-size:.72rem;">Instagram URL</label><input class="w-input" type="url" id="biz_instagram" placeholder="https://instagram.com/yourpage"></div>
            <div class="w-group"><label class="w-label" for="biz_facebook" style="font-size:.72rem;">Facebook URL</label><input class="w-input" type="url" id="biz_facebook" placeholder="https://facebook.com/yourpage"></div>
          </div>
          <div class="w-row">
            <div class="w-group"><label class="w-label" for="biz_x" style="font-size:.72rem;">X (Twitter) URL</label><input class="w-input" type="url" id="biz_x" placeholder="https://x.com/yourhandle"></div>
            <div class="w-group"><label class="w-label" for="biz_youtube" style="font-size:.72rem;">YouTube URL</label><input class="w-input" type="url" id="biz_youtube" placeholder="https://youtube.com/@yourchannel"></div>
          </div>
          <div class="w-hint">Venum-na mattum fill pannunga — empty fields footer la kaattaathu, fake icon varathu.</div>
        </div>

        <div class="gen-block">
          <h3><?= ui_t('b_gen_title') ?></h3>
          <p>We'll create <strong style="color:#a5b4fc">3 distinct style variations</strong> based on your design direction.<br>Each variation uses your exact content — only the layout differs.</p>
          <button class="wbtn wbtn-generate" id="wiz-generate-btn" onclick="openGenerateModal()" disabled>
            <span>✦</span><span><?= ui_t('b_gen_btn') ?></span>
          </button>
        </div>

        <div class="gen-progress" id="gen-progress">
          <div class="gen-progress-bar"><div class="gen-progress-fill" id="gen-progress-fill"></div></div>
          <div class="gen-progress-msg"><span class="spinner"></span><span id="gen-progress-text">Ready</span></div>
        </div>
      </div>
    </div>

    <div class="wizard-footer">
      <button class="wbtn wbtn-ghost" id="wiz-prev" onclick="wizNav(-1)" disabled>&larr; <?= ui_t('b_back') ?></button>
      <div id="wiz-step-counter" style="font-size:0.85rem; color:#64748b; font-weight:600">Step 1 of 4</div>
      <button class="wbtn wbtn-primary" id="wiz-next" onclick="wizNav(1)"><?= ui_t('b_next') ?></button>
      <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
        <button class="wbtn wbtn-clear" id="wiz-clear" onclick="clearAndSwitchAccount()" style="display:none;">✕ Clear</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ PROMPT HELPER MODAL — unga vaarthaila sollunga, sariyaana prompt ah maathi tharum.
     Tamil / Tanglish / English OK. Offline-safe: ellaam browser la nadakkum. -->
<div class="modal-overlay" id="promptgen-modal">
  <div class="modal-box" style="max-width:620px;height:auto;max-height:90vh;overflow-y:auto;">
    <div class="modal-bar">
      <div style="font-weight:800;color:#fff;font-size:1rem;">💡 Prompt Helper</div>
      <button type="button" class="wbtn wbtn-ghost" onclick="closePromptGenerator()">✕ Close</button>
    </div>
    <div style="padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:.8rem;">
      <p style="font-size:.84rem;color:#94a3b8;line-height:1.55;">Unga business pathi <strong style="color:#c7d2fe">unga vaarthaila</strong> ezhuthunga (Tamil, Tanglish, English — ethuvaanalum seri). AI atha sariyaana business prompt ah maathi, <strong style="color:#c7d2fe">Understand box la podum</strong> — aprom Understand click panna AI wizard ah fill pannum.</p>
      <textarea class="w-textarea" id="promptgen-input" rows="4" placeholder="e.g. enakk oru bus service iruku, Chennai to Bangalore daily trips, seat booking um QR ticket um venum"></textarea>
      <div style="display:flex;gap:.45rem;flex-wrap:wrap;">
        <button type="button" class="admin-preset-btn" onclick="document.getElementById('promptgen-input').value='enakk oru bus service iruku Chennai to Bangalore daily, seat booking QR ticket counter payment venum';">🚌 Bus example</button>
        <button type="button" class="admin-preset-btn" onclick="document.getElementById('promptgen-input').value='My business is a small bakery in Jaffna. We sell cakes and buns. Target: families and students. Modern warm site with WhatsApp contact.';">🍰 Shop example</button>
        <button type="button" class="admin-preset-btn" onclick="document.getElementById('promptgen-input').value='enakku oru beauty salon iruku, haircut facial bridal service, appointment booking venum, premium look';">💅 Salon example</button>
      </div>
      <button type="button" class="wbtn wbtn-primary" onclick="makePromptFromWords()" style="align-self:flex-start;">✨ Make my prompt</button>
      <label class="w-label" for="promptgen-output">Ready prompt (check panni Use pannunga)</label>
      <textarea class="w-textarea" id="promptgen-output" rows="4" readonly placeholder="Inga ready prompt varum…"></textarea>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <button type="button" class="wbtn wbtn-ghost" onclick="useGeneratedPrompt(false)">📋 Use this prompt</button>
        <button type="button" class="wbtn wbtn-generate" onclick="useGeneratedPrompt(true)" style="font-size:.85rem;padding:.65rem 1.2rem;">🚀 Use &amp; Understand</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ DESIGNS SCREEN ═══ -->
<div id="designs-screen" style="display:none !important;" aria-hidden="true">
  <!-- DEPRECATED: concepts picker lives ONLY on variations.php now.
       Direct flow: wizard → generate → variations.php (no in-builder step).
       Kept as hidden stub so legacy getElementById('designs-screen') calls stay null-safe. -->
  <div class="designs-grid" id="designs-grid" style="display:none;"></div>
  <div id="designs-title" style="display:none;"></div>
  <div id="designs-sub" style="display:none;"></div>
</div>

<!-- ═══ SUB-DESIGN PICKER SCREEN ═══ -->
<div id="subdesign-screen" style="display:none;">
  <div class="designs-header">
    <div class="designs-badge" id="subdesign-concept-badge">✦ 3 Layout Variants</div>
    <h1 id="subdesign-title">Choose Your Layout Variant</h1>
    <p id="subdesign-sub">Each variant uses the same design direction — only the layout and structure differ. Pick the one that fits your vision.</p>
    <button class="wbtn wbtn-ghost" onclick="backToDesignsFromSub()" style="margin-top:0.9rem;font-size:0.82rem;padding:0.5rem 1.1rem;border-radius:10px;"><span>←</span> Back to Concepts</button>
  </div>
  <div class="designs-grid" id="subdesign-grid"></div>
</div>

<!-- ═══ LIVE WORKSPACE ═══ -->
<div id="builder-screen">
  <div class="builder-toolbar">
    <a class="tb-btn tb-back-btn" href="<?= $customerUser ? SITE_URL . '/customer-portal.php' : SITE_URL . '/index.php' ?>" onclick="return navHomeWithLoader(this)" title="Back to Home">← Back</a>
    <div class="tb-title">✦ WebCraft AI</div>
    <div class="tb-site-badge" id="tb-biz-badge">Zenith Studio</div>
    <div class="db-status-badge" style="display:inline-flex; align-items:center; gap:0.35rem; padding:0.25rem 0.65rem; border-radius:999px; font-size:0.72rem; font-weight:800; background:rgba(16,185,129,0.15); border:1px solid #10b981; color:#34d399;" title="Supabase PostgreSQL Active">
      <span style="width:6px; height:6px; border-radius:50%; background:#10b981; box-shadow:0 0 6px #10b981;"></span>
      <span>Supabase Connected</span>
    </div>
    <div class="tb-divider"></div>
    <div class="design-tabs">
      <button class="d-tab active" id="tab-d0" onclick="switchActiveDesign(0)">Variation 1</button>
      <button class="d-tab" id="tab-d1" onclick="switchActiveDesign(1)">Variation 2</button>
      <button class="d-tab" id="tab-d2" onclick="switchActiveDesign(2)">Variation 3</button>
    </div>
    <div class="view-tabs" id="view-tabs">
      <button class="view-tab active" id="vtab-site" onclick="switchWorkspaceView('site')">🌐 Site</button>
      <button class="view-tab admin-view" id="vtab-admin" onclick="switchWorkspaceView('admin')">🔐 Admin Panel</button>
    </div>
    <div class="tb-divider"></div>
    <button class="tb-btn" onclick="openInNewTab()">👁 Preview</button>
    <button class="tb-btn visual-btn" onclick="openStudioInNewTab()">🎨 Edit in Studio ↗</button>
    <button class="tb-btn" id="btn-add-function" onclick="openAddFunctionManager()" style="display:none;background:linear-gradient(135deg,#10b981,#059669);border-color:#34d399;color:#fff;font-weight:800;" title="Add new features to your admin panel using AI">&#xFF0B; Add Function</button>
    <button class="tb-btn guide-btn" onclick="openStepByStepGuide()" style="background:rgba(99,102,241,0.18); border:1.5px solid #6366f1; color:#c7d2fe; font-weight:800;" title="How to use admin panel & add functions">📖 Admin &amp; Features Guide</button>
    <button class="tb-btn" onclick="openUserManual()" title="User manual: how to use, edit & publish (popup)">📘 Manual</button>
    <button class="tb-btn" onclick="goToVariations()">← Back to Variations</button>
    <div style="flex:1"></div>
    <button class="tb-btn undo-btn" id="btn-undo-ai" onclick="undoLastAiChange()" style="display:none">↶ Undo AI</button>
    <button class="tb-btn publish-btn" id="btn-publish" onclick="saveAndProceedToPayment()">🚀 Save &amp; Publish</button>
  </div>

  <div class="builder-main">
    <div class="preview-pane">
      <div class="preview-bar">
        <div class="device-toggles">
          <button class="device-btn active" id="d-desktop" onclick="setDevice('desktop')">🖥 PC</button>
          <button class="device-btn" id="d-laptop" onclick="setDevice('laptop')">💻 Laptop</button>
          <button class="device-btn" id="d-tablet" onclick="setDevice('tablet')">📱 Tablet</button>
          <button class="device-btn" id="d-mobile" onclick="setDevice('mobile')">📲 Phone</button>
        </div>
        <div style="display:flex; align-items:center; gap:0.6rem;">
          <span id="current-view-label" style="font-size:0.72rem;color:#94a3b8;font-weight:700;">🌐 Frontend Site</span>
          <button class="tb-btn" id="btn-edit-mode" onclick="toggleEditMode()" style="padding:0.28rem 0.7rem; font-size:0.75rem;" title="Edit mode ON = click any text in the preview and type. OFF = normal preview page.">✏️ Edit: OFF</button>
          <button class="tb-btn" onclick="refreshLivePreview()" style="padding:0.28rem 0.7rem; font-size:0.75rem;">↺ Reload</button>
        </div>
      </div>
      <!-- Admin URL Info Bar (shown when admin tab active) -->
      <div id="admin-url-bar" style="display:none;background:#0a0f1c;border-bottom:1px solid #1e3a5f;padding:.5rem 1rem;align-items:center;gap:.65rem;flex-wrap:wrap;">
        <span style="font-size:.72rem;font-weight:700;color:#38bdf8;white-space:nowrap;">&#x1F510; Admin URL:</span>
        <input id="admin-url-display" type="text" readonly onclick="this.select()" value="Publish your site first to see admin URL"
          style="flex:1;min-width:180px;max-width:520px;background:#050810;border:1px solid #1e293b;border-radius:7px;padding:.3rem .75rem;color:#67e8f9;font-family:monospace;font-size:.73rem;outline:none;cursor:text;">
        <button onclick="copyAdminUrlBar()" id="copy-admin-url-btn" style="padding:.3rem .75rem;background:#1e3a5f;border:1px solid #1e4a6f;border-radius:7px;color:#93c5fd;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">&#x1F4CB; Copy URL</button>
        <a id="admin-url-open-btn" href="#" target="_blank" style="padding:.3rem .75rem;background:linear-gradient(135deg,#6366f1,#4f46e5);border:none;border-radius:7px;color:#fff;font-size:.72rem;font-weight:700;text-decoration:none;white-space:nowrap;">&#x2197; Open Admin Login</a>
        <button onclick="previewOpenSite()" id="btn-preview-site" style="display:none;padding:.3rem .75rem;background:#065f46;border:1px solid #10b981;border-radius:7px;color:#a7f3d0;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">&#x1F9EA; Live test site</button>
        <button onclick="previewOpenTab()" id="btn-preview-open" style="display:none;padding:.3rem .75rem;background:#065f46;border:1px solid #10b981;border-radius:7px;color:#a7f3d0;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">&#x2197; Preview Website</button>
        <button onclick="openPreviewChecklist()" id="btn-preview-checklist" style="display:none;padding:.3rem .75rem;background:#1e1b4b;border:1px solid #6366f1;border-radius:7px;color:#c7d2fe;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">✅ Checklist</button>
        <button onclick="previewRebuild()" id="btn-preview-rebuild" style="display:none;padding:.3rem .75rem;background:#1e3a5f;border:1px solid #1e4a6f;border-radius:7px;color:#93c5fd;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">&#x21BB; Rebuild</button>
        <button onclick="previewResetPass()" id="btn-preview-pass" style="display:none;padding:.3rem .75rem;background:#1e3a5f;border:1px solid #1e4a6f;border-radius:7px;color:#93c5fd;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">&#x1F511; Password</button>
        <button onclick="openCredsModal()" id="btn-creds" title="View / generate the admin username & password used at publish" style="padding:.3rem .75rem;background:#1e1b4b;border:1px solid #6366f1;border-radius:7px;color:#c7d2fe;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">&#x1F511; Credentials</button>
        <span id="admin-creds-hint" style="display:none;font-size:.7rem;color:#6ee7b7;font-weight:600;"></span>
        <span id="preview-readiness" style="display:none;font-size:.7rem;font-weight:800;"></span>
        <span style="font-size:.7rem;color:#475569;font-weight:600;">Login, then edit your site freely</span>
      </div>
<!-- ═══ WORKSPACE CREDENTIALS MODAL — admin username/password used at publish.
     Always visible here (unlike the one-time publish display). Changing it
     later is possible anytime in Website Manager. -->
<div id="creds-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)closeCredsModal()">
  <div style="background:#111622;border:1.5px solid #283347;border-radius:18px;width:100%;max-width:440px;box-shadow:0 24px 64px rgba(0,0,0,.6);overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;background:#0d121c;border-bottom:1px solid #1e293b;">
      <strong style="color:#fff;font-size:.95rem;">🔑 Website Admin Login</strong>
      <button onclick="closeCredsModal()" style="background:none;border:none;color:#64748b;font-size:1.3rem;cursor:pointer;line-height:1;">✕</button>
    </div>
    <div style="padding:1.25rem;display:flex;flex-direction:column;gap:.75rem;">
      <p style="font-size:.78rem;color:#94a3b8;line-height:1.55;margin:0;">This login is created at <strong style="color:#c7d2fe">publish</strong>. You can change it later anytime in <strong style="color:#c7d2fe">Website Manager</strong>.</p>
      <div>
        <label style="display:block;font-size:.72rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Admin Username</label>
        <div style="display:flex;gap:.45rem;">
          <input id="creds-user" type="text" autocomplete="off" placeholder="e.g. site_admin"
            style="flex:1;min-width:0;padding:.55rem .8rem;border:1.5px solid #283347;border-radius:9px;background:#0b0f17;color:#fff;font-family:inherit;font-size:.85rem;outline:none;">
          <button onclick="copyCredField('creds-user',this)" style="padding:.55rem .8rem;border-radius:9px;border:1.5px solid #334155;background:#0b0f17;color:#cbd5e1;font-weight:700;cursor:pointer;font-size:.78rem;">Copy</button>
        </div>
      </div>
      <div>
        <label style="display:block;font-size:.72rem;font-weight:700;color:#cbd5e1;margin-bottom:.3rem;">Admin Password</label>
        <div style="display:flex;gap:.45rem;">
          <input id="creds-pass" type="password" autocomplete="new-password" placeholder="Min 8 characters"
            style="flex:1;min-width:0;padding:.55rem .8rem;border:1.5px solid #283347;border-radius:9px;background:#0b0f17;color:#fff;font-family:inherit;font-size:.85rem;outline:none;">
          <button onclick="toggleCredPassField(this)" title="Show/Hide" style="padding:.55rem .7rem;border-radius:9px;border:1.5px solid #334155;background:#0b0f17;color:#cbd5e1;font-weight:700;cursor:pointer;font-size:.78rem;">👁️</button>
          <button onclick="copyCredField('creds-pass',this)" style="padding:.55rem .8rem;border-radius:9px;border:1.5px solid #334155;background:#0b0f17;color:#cbd5e1;font-weight:700;cursor:pointer;font-size:.78rem;">Copy</button>
        </div>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <button onclick="wcGenCredPass()" style="padding:.55rem 1rem;border-radius:9px;border:1.5px solid #6366f1;background:#1e1b4b;color:#c7d2fe;font-weight:700;cursor:pointer;font-size:.8rem;font-family:inherit;">🎲 Generate strong password</button>
        <button onclick="saveCredsModal()" style="padding:.55rem 1.2rem;border-radius:9px;border:none;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-weight:800;cursor:pointer;font-size:.82rem;font-family:inherit;">✓ Save</button>
      </div>
      <div id="creds-msg" style="font-size:.78rem;font-weight:600;min-height:1.2em;"></div>
    </div>
  </div>
</div>
      <div class="preview-container" id="preview-container">
        <iframe id="live-iframe" sandbox="allow-scripts allow-same-origin allow-forms allow-top-navigation-by-user-activation allow-modals allow-popups allow-downloads"></iframe>
        <div class="pv-frame-spin" id="live-frame-spin" style="display:none;"><div class="wcl-mini-house"><div class="walls"></div><div class="roof"></div><div class="door"></div></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ FLOATING AI BUTTON ═══ -->
<button class="floating-gemini-btn" id="floating-ai-btn" onclick="toggleMagicAi()">
  <span class="fg-orb">✦</span>
  <span>WebCraft AI</span>
</button>

<div class="magic-ai-panel" id="magic-ai-panel">
  <div class="magic-header">
    <div class="magic-brand">
      <div class="magic-avatar">✦</div>
      <div class="magic-brand-text">
        <div class="magic-brand-name">WebCraft AI Co-Pilot</div>
        <div class="magic-brand-sub"><span class="dot"></span> Online</div>
      </div>
    </div>
    <div class="magic-header-actions">
      <button class="magic-icon-btn" onclick="clearChatLog()" title="Clear chat">🗑</button>
      <button class="magic-icon-btn" onclick="toggleMagicAi()" title="Close">✕</button>
    </div>
  </div>

  <div class="ai-model-row">
    <div style="display:flex; align-items:center; gap:0.4rem; width:100%;">
      <span style="color:#818cf8; font-weight:700; flex-shrink:0;" title="AI model for chat edits">✦ Model:</span>
      <select class="ai-model-dropdown" id="ai-model-select" onchange="changeAiModel(this.value)" style="flex:1; min-width:0;">
        <option value="gemini-3.5-flash-lite" selected>✦ Gemini Flash Lite (Fast · Default)</option>
        <option value="gemini-2.5-flash">✦ Gemini 2.5 Flash</option>
        <option value="gemini-2.5-pro">✦ Gemini 2.5 Pro (Deep Reasoning)</option>
        <option value="gemini-1.5-flash">✦ Gemini 1.5 Flash</option>
        <option value="opencode-fallback">⚡ OpenCode AI (Fallback)</option>
      </select>
    </div>
  </div>

  <div class="ai-model-row">
    <div style="display:flex; align-items:center; gap:0.4rem; width:100%;">
      <span style="color:#10b981; font-weight:700;" title="AI edits apply to this website">🎯 Editing:</span>
      <select class="ai-model-dropdown" id="ai-target-select" onchange="setAiTargetVariation(this.value)" style="flex:1; min-width:0;">
        <option value="0">Variation 1</option>
      </select>
    </div>
  </div>

  <div class="magic-chat-log" id="ai-chat-log">
    <div class="msg ai">
      <div class="msg-avatar">✦</div>
      <div class="msg-body">
        <div class="msg-bubble">
          👋 <strong>Hello! I'm your AI Co-Pilot</strong><br>
          Talk to me in <strong>English</strong>, <strong>Tanglish</strong>, or Tamil.<br>
          Use the <strong>🎤</strong> button to speak instead of typing.
        </div>
        <div class="msg-meta">AI · just now</div>
      </div>
    </div>
  </div>

  <div class="m-chip-row" id="magic-chips-container">
    <span class="m-chip" onclick="quickRefine('Add a modern 5-star testimonials section')">⭐ Reviews</span>
    <span class="m-chip" onclick="quickRefine('Add a modern pricing table with 3 plans')">💰 Pricing</span>
    <span class="m-chip" onclick="quickRefine('Add an interactive FAQ accordion')">❓ FAQ</span>
    <span class="m-chip" onclick="quickRefine('Add a floating WhatsApp chat button')">💬 WhatsApp</span>
    <span class="m-chip" onclick="quickRefine('Add meet our leadership team section')">👥 Team</span>
    <span class="m-chip" onclick="quickRefine('Switch theme to sleek dark mode')">🌙 Dark Mode</span>
  </div>

  <div class="magic-input-row">
    <div class="magic-input-wrap">
      <span class="magic-input-icon">✦</span>
      <input type="text" class="magic-input" id="refine-query" placeholder="Ask AI anything — English or Tanglish…">
    </div>
    <button class="magic-mic-btn" id="btn-voice" onclick="toggleVoiceInput()" title="Voice chat"><span id="mic-icon">🎤</span></button>
    <select class="magic-lang-select" id="voice-lang" title="Voice language">
      <option value="ta-IN">🇮🇳 Tamil</option>
      <option value="si-LK">🇱🇰 Sinhala</option>
      <option value="en-IN" selected>🇮🇳 EN-IN</option>
      <option value="en-US">🇺🇸 EN-US</option>
      <option value="hi-IN">🇮🇳 Hindi</option>
    </select>
    <button class="magic-btn" id="btn-refine" onclick="executeRefine()" title="Send">➤</button>
  </div>

  <div class="magic-hint">Press <kbd>Enter</kbd> to send · Tanglish OK · 🎤 voice input</div>
</div>

<!-- ═══ FULLSCREEN PREVIEW MODAL ═══ -->
<div class="modal-overlay" id="fullscreen-modal">
  <div class="modal-box">
    <div class="modal-bar">
      <div style="font-weight:700; color:#fff" id="modal-title">Fullscreen Preview</div>
      <div style="display:flex; gap:0.6rem; flex-wrap:wrap; align-items:center;">
        <div class="device-toggles" title="Preview on different devices">
          <button class="device-btn active" id="md-desktop" onclick="setModalDevice('desktop')">🖥 PC</button>
          <button class="device-btn" id="md-laptop" onclick="setModalDevice('laptop')">💻 Laptop</button>
          <button class="device-btn" id="md-tablet" onclick="setModalDevice('tablet')">📱 Tablet</button>
          <button class="device-btn" id="md-mobile" onclick="setModalDevice('mobile')">📲 Phone</button>
        </div>
        <div class="view-tabs show" id="modal-view-tabs" style="display:none;">
          <button class="view-tab active" id="m-vtab-site" onclick="switchModalView('site')">🌐 Site</button>
          <button class="view-tab admin-view" id="m-vtab-admin" onclick="switchModalView('admin')">🔐 Admin</button>
        </div>
        <button class="wbtn wbtn-primary" onclick="selectFromModal()">🚀 Select &amp; Edit</button>
        <button class="wbtn wbtn-ghost" onclick="closeFullscreenModal()">✕ Close</button>
      </div>
    </div>
    <iframe class="modal-iframe" id="modal-iframe" sandbox="allow-scripts allow-same-origin allow-forms allow-top-navigation-by-user-activation allow-modals allow-popups allow-downloads"></iframe>
    <div class="pv-frame-spin" id="modal-frame-spin" style="display:none;"><div class="wcl-mini-house"><div class="walls"></div><div class="roof"></div><div class="door"></div></div></div>
  </div>
</div>

<?php include __DIR__ . '/includes/manual-modal.php'; ?>
<!-- ═══ GENERATE MODE MODAL ═══ -->
<div class="modal-overlay" id="generate-modal">
  <div class="modal-box" style="max-width:960px; height:auto; max-height:90vh; overflow-y:auto;">
    <div class="modal-bar">
      <div>
        <div style="font-weight:800; color:#fff; font-size:1.05rem;">✦ Choose Generation Mode</div>
        <div style="font-size:0.78rem; color:#94a3b8; margin-top:0.15rem;">
          Each mode produces <strong style="color:#a5b4fc">3 AI-generated style variations</strong> — all following your design direction.
        </div>
      </div>
      <div style="display:flex; gap:0.5rem; align-items:center;">
        <button type="button" class="wbtn" onclick="openStepByStepGuide()" style="background:rgba(99,102,241,0.18); border:1.5px solid #6366f1; color:#c7d2fe; font-size:0.78rem; font-weight:700; padding:0.45rem 0.9rem; border-radius:8px; cursor:pointer;" title="How full stack and admin work">📖 View Guide</button>
        <button type="button" class="wbtn wbtn-ghost" onclick="closeGenerateModal()">✕ Close</button>
      </div>
    </div>
    <div class="mode-grid" id="biz-auto-panel">
      <div class="gen-mode-card selected" data-mode="auto" style="grid-column:1/-1;cursor:default;text-align:left;">
        <span class="gm-badge">Auto · Business-aware</span>
        <span class="gm-icon">🧠</span>
        <h3 id="biz-auto-title">Analyzing your business…</h3>
        <p id="biz-auto-desc">We detect pages, features, admin needs and database automatically. No technical choice needed.</p>
        <div class="gm-foot" id="biz-auto-foot">▸ 3 design variations · Gemini gemini-3.5-flash-lite</div>
      </div>
    </div>
    <div id="biz-spec-box" style="margin:0 1.5rem 1rem;padding:0.9rem 1rem;background:#0b0f17;border:1px solid #1e293b;border-radius:10px;font-size:0.8rem;color:#94a3b8;line-height:1.6;">Analyzing…</div>

    <!-- ═══ BLUEPRINT: interview + review + approve (additive) ═══ -->
    <div id="bp-section" style="margin:0 1.5rem 1rem;padding:1rem 1.1rem;background:#0d1424;border:1.5px solid rgba(99,102,241,0.4);border-radius:12px;">
      <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.7rem;">
        <div style="font-weight:800;color:#fff;font-size:0.92rem;">📋 Business Blueprint</div>
        <div style="font-size:0.72rem;color:#94a3b8;">Review before generation — the approved spec drives every step.</div>
        <div style="flex:1"></div>
        <div style="display:flex;gap:0.3rem;">
          <button type="button" id="bp-mode-quick" class="admin-preset-btn" onclick="bpSetMode('quick')">Quick</button>
          <button type="button" id="bp-mode-full" class="admin-preset-btn" onclick="bpSetMode('full')">Detailed</button>
        </div>
      </div>
      <div id="bp-interview" style="margin-bottom:0.8rem;"></div>
      <div id="bp-complete" style="margin-bottom:0.8rem;"></div>
      <div id="bp-review" style="font-size:0.8rem;color:#cbd5e1;line-height:1.6;"></div>
      <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.8rem;flex-wrap:wrap;">
        <button type="button" class="wbtn wbtn-primary" id="bp-approve-btn" onclick="bpApprove()" style="font-size:0.82rem;">✓ Approve blueprint</button>
        <span id="bp-status" style="font-size:0.76rem;color:#94a3b8;"></span>
      </div>
    </div>

    <!-- ═══ ADMIN REQUIREMENTS (only shown when admin/database picked) ═══ -->
    <div class="admin-req-section" id="admin-req-section">
      <div class="admin-req-header">
        <div class="ar-icon">🧠</div>
        <div>
          <h3>AI Will Write Your Admin Panel</h3>
          <p>No templates. Just describe what you need to manage — the AI will write every PHP file from scratch.</p>
        </div>
      </div>

      <div class="w-group" style="margin-bottom:1rem;">
        <label class="w-label" for="admin_requirements">What should your admin panel manage? <span class="req">*</span></label>
        <textarea class="admin-req-textarea" id="admin_requirements" rows="6"
          placeholder="Examples for a school:
- Manage students (name, roll no, class, section, parent name, phone, address, photo)
- Manage teachers (name, subject, email, phone)
- Manage classes
- Track fees paid by each student
- Record daily attendance

Be specific about fields you need!"></textarea>
      </div>

      <div class="w-group" style="margin-bottom:0;">
        <label class="w-label">⚡ Quick presets (click to auto-fill)</label>
        <div class="admin-preset-row">
          <button type="button" class="admin-preset-btn" onclick="fillAdminPreset('school')">🎓 School</button>
          <button type="button" class="admin-preset-btn" onclick="fillAdminPreset('restaurant')">🍽️ Restaurant</button>
          <button type="button" class="admin-preset-btn" onclick="fillAdminPreset('clinic')">🏥 Clinic</button>
          <button type="button" class="admin-preset-btn" onclick="fillAdminPreset('gym')">💪 Gym</button>
          <button type="button" class="admin-preset-btn" onclick="fillAdminPreset('shop')">🛒 Shop</button>
          <button type="button" class="admin-preset-btn" onclick="fillAdminPreset('realestate')">🏠 Real Estate</button>
          <button type="button" class="admin-preset-btn" onclick="fillAdminPreset('bus')">🚌 Bus Booking</button>
          <button type="button" class="admin-preset-btn" id="btn-admin-autofill" onclick="autoFillAdminFromBusiness()" title="Detect your business type and fill this automatically">✨ Auto-fill from my business</button>
        </div>
      </div>

      <!-- Customize Admin Credentials -->
      <div class="w-group" style="margin-top:1.25rem; padding-top:1.25rem; border-top:1px dashed #283347;">
        <label class="w-label" style="display:flex; justify-content:space-between; align-items:center;">
          <span>🔐 Customize Admin Login Credentials</span>
          <span style="font-size:0.75rem; color:#10b981; font-weight:700;">✓ Fully Customizable</span>
        </label>
        <p style="font-size:0.76rem; color:#94a3b8; margin-bottom:0.75rem;">Set the username and password you want to use for logging into your admin panel:</p>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.75rem;">
          <button type="button" class="admin-preset-btn" onclick="fillWizardDemoCreds()">🎭 Use Demo Credentials</button>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div>
            <label class="w-label" style="font-size:0.75rem;" for="admin_custom_user">Admin Username</label>
            <input type="text" class="w-input" id="admin_custom_user" placeholder="e.g. admin_apex" style="font-size:0.85rem;" autocomplete="off">
            <div style="font-size:0.7rem; color:#64748b; margin-top:0.25rem;">Letters, numbers, underscore (min 4 chars)</div>
          </div>
          <div>
            <label class="w-label" style="font-size:0.75rem;" for="admin_custom_pass">Admin Password</label>
            <div style="position:relative;">
              <input type="password" class="w-input" id="admin_custom_pass" placeholder="Min 8 chars" style="font-size:0.85rem; padding-right:2.2rem;" autocomplete="new-password">
              <button type="button" onclick="togglePassVisibility('admin_custom_pass', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:#94a3b8; cursor:pointer; font-size:0.9rem;" title="Show/Hide">👁️</button>
            </div>
            <div style="font-size:0.7rem; color:#64748b; margin-top:0.25rem;">Min 8 characters</div>
          </div>
        </div>
      </div>

      <div class="admin-ai-note">
        <span class="note-icon">💡</span>
        <div>
          <strong>How it works:</strong> After you publish, the AI generates your complete admin panel (login, dashboard, forms, tables).
          You'll see the full file tree, log in with your customized credentials above, and can even <em>fix any file with AI</em> before paying.
        </div>
      </div>
    </div>

    <div class="modal-bar" style="border-top:1px solid #1e293b; border-bottom:none; justify-content:space-between;">
      <div style="font-size:0.76rem; color:#94a3b8;">
        Generation Mode: <strong id="gen-modal-mode-label" style="color:#10b981;">Static</strong>
        <span style="margin-left:0.6rem; color:#38bdf8; font-weight:600;">✦ AI-FlowCraft Engine</span>
      </div>
      <div style="display:flex; gap:0.5rem;">
        <button class="wbtn wbtn-ghost" onclick="closeGenerateModal()">✕ Cancel</button>
        <button class="wbtn" onclick="confirmSoloGenerate()" title="One-page Solo-style professional site (consultant/coach/therapist/trades). Server template stands in if it fails." style="background:linear-gradient(135deg,#059669,#10b981); color:#fff; font-weight:800; padding:0.6rem 1.1rem; border-radius:10px; border:none; cursor:pointer; font-family:inherit; font-size:0.85rem;"><span>⚡</span><span>Solo Quick Site</span></button>
        <button class="wbtn wbtn-primary" onclick="confirmGenerate()"><span>✦</span><span>Synthesize 3 Variations</span></button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ FULL-STACK DEVELOPMENT GUIDE MODAL ═══ -->
<div class="generate-modal-backdrop" id="step-guide-modal">
  <div class="generate-modal-card" style="max-width:800px; max-height:90vh; display:flex; flex-direction:column;">

    <!-- Header -->
    <div style="padding:1.4rem 1.75rem; background:linear-gradient(135deg,#1e1b4b,#0d121c); border-bottom:1px solid #283347; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-shrink:0;">
      <div style="display:flex; align-items:center; gap:0.85rem;">
        <div style="width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg,#4f46e5,#a855f7); display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; box-shadow:0 6px 18px rgba(99,102,241,0.4);">📖</div>
        <div>
          <div style="font-size:1.05rem; font-weight:900; color:#fff; letter-spacing:-0.02em;">Full-Stack Development Guide</div>
          <div style="font-size:0.75rem; color:#818cf8; font-weight:700; margin-top:0.1rem;">How to use your AI-built website &amp; admin panel</div>
        </div>
      </div>
      <button type="button" onclick="closeStepByStepGuide()" title="Close guide"
        style="width:36px;height:36px;border-radius:10px;border:1.5px solid #334155;background:#0b0f17;color:#cbd5e1;font-size:1.15rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.15s;flex-shrink:0;"
        onmouseover="this.style.borderColor='#ef4444';this.style.color='#fff';this.style.background='#7f1d1d'" onmouseout="this.style.borderColor='#334155';this.style.color='#cbd5e1';this.style.background='#0b0f17'">✕</button>
    </div>

    <!-- Scrollable Content -->
    <div style="overflow-y:auto; flex:1; padding:1.5rem 1.75rem;">

      <p style="color:#94a3b8; font-size:0.87rem; line-height:1.65; margin-bottom:1.5rem; padding:0.9rem 1rem; background:rgba(99,102,241,0.07); border:1px solid rgba(99,102,241,0.25); border-radius:10px;">
        💡 Your website was <strong style="color:#a5b4fc">AI-generated as a full-stack application</strong> — a public-facing website <em>and</em> a real PHP admin backend. This guide explains everything you need to know step by step.
      </p>

      <div style="display:flex; flex-direction:column; gap:1.1rem;">

        <!-- Step 1 -->
        <div style="background:#0b0f17; border:1px solid #1e293b; border-left:4px solid #6366f1; border-radius:14px; padding:1.2rem;">
          <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.5rem;">
            <span style="background:#6366f1; color:#fff; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:800; flex-shrink:0;">1</span>
            <h3 style="color:#fff; font-size:0.95rem; font-weight:800;">🌐 Switch Between Website &amp; Admin Panel</h3>
          </div>
          <p style="color:#94a3b8; font-size:0.83rem; line-height:1.6; margin-left:2.3rem;">
            In the toolbar at the top, you'll see two tabs: <strong style="color:#67e8f9">🌐 Site</strong> (your public website) and <strong style="color:#67e8f9">🔐 Admin Panel</strong> (your backend). Click either tab to instantly switch the preview between them — both are fully live.
          </p>
        </div>

        <!-- Step 2 -->
        <div style="background:#0b0f17; border:1px solid #1e293b; border-left:4px solid #10b981; border-radius:14px; padding:1.2rem;">
          <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.5rem;">
            <span style="background:#10b981; color:#fff; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:800; flex-shrink:0;">2</span>
            <h3 style="color:#fff; font-size:0.95rem; font-weight:800;">🎨 Visually Edit in Studio (Drag &amp; Drop)</h3>
          </div>
          <p style="color:#94a3b8; font-size:0.83rem; line-height:1.6; margin-left:2.3rem;">
            Click <strong style="color:#10b981">🎨 Edit in Studio ↗</strong> in the toolbar. Studio is a Canva-style drag-and-drop editor. Inside it, you can switch between <strong style="color:#fff">🌐 Site</strong> and <strong style="color:#fff">🔐 Admin Panel</strong> views — drag, resize, edit text and images on <em>both</em> without writing any code.
          </p>
        </div>

        <!-- Step 3 -->
        <div style="background:#0b0f17; border:1px solid #1e293b; border-left:4px solid #a855f7; border-radius:14px; padding:1.2rem;">
          <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.5rem;">
            <span style="background:#a855f7; color:#fff; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:800; flex-shrink:0;">3</span>
            <h3 style="color:#fff; font-size:0.95rem; font-weight:800;">🤖 Add New Functions Anytime with AI Co-Pilot</h3>
          </div>
          <p style="color:#94a3b8; font-size:0.83rem; line-height:1.6; margin-left:2.3rem;">
            Click the <strong style="color:#c084fc">✦ WebCraft AI</strong> floating button (bottom-right). Tell the AI what you want to add — in plain English or Tanglish:
          </p>
          <div style="margin:0.6rem 0 0.6rem 2.3rem; background:#111622; border:1px solid #283347; border-radius:10px; padding:0.75rem; font-family:'Fira Code',monospace; font-size:0.76rem; color:#a5b4fc; line-height:1.8;">
            💬 "Add employee management with name, position, photo — show team on website"<br>
            💬 "Add a products section with name, price, image, category"<br>
            💬 "Add a testimonials section with star rating"
          </div>
          <p style="color:#94a3b8; font-size:0.83rem; line-height:1.6; margin-left:2.3rem;">
            ⚡ AI automatically writes the code, updates your <strong>public website</strong> section, and adds the full <strong>admin management module</strong> — all in one go.
          </p>
        </div>

        <!-- Step 4 -->
        <div style="background:#0b0f17; border:1px solid #1e293b; border-left:4px solid #f59e0b; border-radius:14px; padding:1.2rem;">
          <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.5rem;">
            <span style="background:#f59e0b; color:#fff; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:800; flex-shrink:0;">4</span>
            <h3 style="color:#fff; font-size:0.95rem; font-weight:800;">🔐 Set Your Own Admin Username &amp; Password</h3>
          </div>
          <p style="color:#94a3b8; font-size:0.83rem; line-height:1.6; margin-left:2.3rem;">
            Your admin credentials are 100% yours to choose. Set them inside the <strong style="color:#fff">Generate Mode modal</strong> (when selecting Admin/Database mode) or in <strong style="color:#fff">Step 3 of the Publish wizard</strong>. Username: letters &amp; numbers, min 4 chars. Password: min 8 chars.
          </p>
        </div>

        <!-- Step 5 -->
        <div style="background:#0b0f17; border:1px solid #1e293b; border-left:4px solid #38bdf8; border-radius:14px; padding:1.2rem;">
          <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.5rem;">
            <span style="background:#38bdf8; color:#fff; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:800; flex-shrink:0;">5</span>
            <h3 style="color:#fff; font-size:0.95rem; font-weight:800;">🚀 Publish &amp; Log In to Your Live Admin Panel</h3>
          </div>
          <p style="color:#94a3b8; font-size:0.83rem; line-height:1.6; margin-left:2.3rem;">
            Click <strong style="color:#f59e0b">🚀 Save &amp; Publish</strong>. After payment, your site goes live at:
          </p>
          <code style="display:block; margin:0.5rem 0 0.5rem 2.3rem; color:#38bdf8; font-size:0.76rem; background:#111622; border:1px solid #1e293b; border-radius:8px; padding:0.55rem 0.85rem; font-family:'Fira Code',monospace;">
            /published/&lt;your-site&gt;/admin/login.php
          </code>
          <p style="color:#94a3b8; font-size:0.83rem; line-height:1.6; margin-left:2.3rem;">
            Log in with your custom credentials to add records, manage content, and run your site. Changes appear on your public website instantly.
          </p>
        </div>

        <!-- Step 6 -->
        <div style="background:#0b0f17; border:1px solid #1e293b; border-left:4px solid #10b981; border-radius:14px; padding:1.2rem;">
          <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.5rem;">
            <span style="background:#10b981; color:#fff; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:800; flex-shrink:0;">6</span>
            <h3 style="color:#fff; font-size:0.95rem; font-weight:800;">⚙️ Manage Your Site After Publishing</h3>
          </div>
          <p style="color:#94a3b8; font-size:0.83rem; line-height:1.6; margin-left:2.3rem;">
            After publishing, use your <strong style="color:#a5b4fc">⚙️ Website Manager</strong> (link shown on the publish confirmation page) to:
          </p>
          <ul style="color:#94a3b8; font-size:0.82rem; line-height:2; margin-left:2.9rem; margin-top:0.35rem;">
            <li>➕ Add new features with AI (e.g. "Add a Gallery section")</li>
            <li>🔔 View notifications &amp; payment reminders from support</li>
            <li>📋 See step-by-step guide for your specific admin sections</li>
            <li>🌐 Quick-access to your live site &amp; admin panel</li>
          </ul>
        </div>

        <!-- Tip box -->
        <div style="background:linear-gradient(135deg,rgba(16,185,129,0.08),rgba(5,150,105,0.04)); border:1.5px solid rgba(16,185,129,0.3); border-radius:12px; padding:1rem 1.1rem; display:flex; gap:0.85rem; align-items:flex-start;">
          <span style="font-size:1.4rem; flex-shrink:0;">💡</span>
          <div style="font-size:0.82rem; color:#a7f3d0; line-height:1.6;">
            <strong style="color:#34d399; display:block; margin-bottom:0.2rem;">Pro Tip — Use Tanglish!</strong>
            You can talk to the AI Co-Pilot in <strong>Tanglish</strong> or use the <strong>🎤 voice button</strong> to speak instead of type. For example: <em style="color:#6ee7b7">"Bro, admin la employee section add pannu, name phone photo ellam vendum"</em> — it works perfectly!
          </div>
        </div>

      </div>
    </div>

    <!-- Footer -->
    <div style="padding:1.1rem 1.75rem; background:#0d121c; border-top:1px solid #1e293b; display:flex; justify-content:space-between; align-items:center; flex-shrink:0; flex-wrap:wrap; gap:0.75rem;">
      <span style="font-size:0.75rem; color:#64748b; font-weight:700;">6 steps · Full-Stack guide</span>
      <button type="button" class="wbtn wbtn-primary" onclick="closeStepByStepGuide()" style="padding:0.65rem 1.4rem; font-size:0.88rem; font-weight:800; cursor:pointer;">
        <span>✕</span><span>Close Guide</span>
      </button>
    </div>

  </div>
</div>

<div class="toast" id="toast"><span id="toast-text"></span></div>

<script src="<?= SITE_URL ?>/assets/js/ai-flowcraft.js?v=20261021a"></script>
<script src="<?= SITE_URL ?>/assets/js/opencode-service.js?v=20261021a"></script>
<script src="<?= SITE_URL ?>/assets/js/biz-intelligence.js?v=20261021a"></script>
<script src="<?= SITE_URL ?>/assets/js/generation-router.js?v=20261021a"></script>
<!-- Loader3D JS comes from includes/nav.php (guarded against double-include) -->
<script>
window.__CUSTOMER__ = <?= json_encode($customerUser ?: null) ?>;
/* ══════════════════════════════════════════════════
   STATE
═════════════════════════════════════════════════ */
let generatedDesigns = [];
const SITE_URL_JS = <?= json_encode(SITE_URL) ?>;
window.WC_UI = <?= json_encode(['lang' => $UI_LANG, 't' => [
  'brief_t' => ui_t('b_brief_t'), 'brief_s' => ui_t('b_brief_s'),
  'brief_btn' => ui_t('b_brief_btn'), 'brandkit' => ui_t('b_brandkit'),
]], JSON_UNESCAPED_UNICODE) ?>;
if (typeof SITE_URL === 'undefined') { var SITE_URL = SITE_URL_JS; }
let activeDesignIndex = 0;
let currentHtml = '';
let currentViewMode = 'site';
let modalViewingIndex = 0;
let modalViewMode = 'site';
let selectedGenMode = 'static';
let generatedConcepts = [];
const HISTORY_LIMIT = 5;
// Per-customer scope: one email sees ONLY its own generated projects.
// `webcraft_saved_project` (no suffix) is the Studio bridge — it is written
// by openStudioInNewTab() with ownerEmail, NOT deleted blindly.
const SESSION_KEY = 'webcraft_saved_project::' + ((window.__CUSTOMER__ && window.__CUSTOMER__.email) || 'guest').toLowerCase();
try {
  const _br = localStorage.getItem('webcraft_saved_project');
  if (_br) {
    const _o = JSON.parse(_br);
    const _me = ((window.__CUSTOMER__ && window.__CUSTOMER__.email) || 'guest').toLowerCase();
    // Drop bridge only if it belongs to a different user (or has no owner = stale legacy)
    if (!_o.ownerEmail || String(_o.ownerEmail).toLowerCase() !== _me) {
      localStorage.removeItem('webcraft_saved_project');
    }
  }
} catch (e) {}

const ADMIN_PRESETS = {
  school: `Manage students (name, roll number, class, section, parent name, phone, address, photo, admission date)
Manage teachers (name, subject, email, phone, joined date)
Manage classes (class name, class teacher, room, no. of students)
Track fees paid by each student (student name, amount, paid date, method, notes)
Record daily attendance (student, date, status Present/Absent/Late, remarks)`,
  restaurant: `Manage menu items (name, category, price, description, photo, available yes/no)
Manage table reservations (guest name, phone, date, time, no. of guests, notes)
Manage incoming orders (customer name, items, total, status Pending/Preparing/Ready/Completed)`,
  clinic: `Manage patients (name, age, gender, phone, address, diagnosis)
Manage appointments (patient, doctor, date, time, status Scheduled/Completed/Cancelled)
Manage prescriptions (patient, doctor, medicines, date)
Manage doctors (name, specialization, email, phone)`,
  gym: `Manage members (name, phone, plan Monthly/Quarterly/Yearly, joined date, expires on)
Manage classes (class name, trainer, schedule, capacity)
Manage trainers (name, specialization, phone, email)`,
  shop: `Manage products (name, category, price, stock, description, photo)
Manage orders (customer name, items, total, status Pending/Shipped/Delivered)
Manage customers (name, phone, email, address)
Manage categories (name, description)`,
  realestate: `Manage properties (title, type Apartment/Villa/Plot/Commercial, price, location, area sqft, description, photo)
Manage inquiries (name, phone, property, message)
Manage agents (name, phone, email, specialization)`,
  bus: `Manage buses (plate no, capacity, seat layout, status active/maintenance)
Manage routes (origin, destination, distance km, base fare)
Manage trips (route, date, departure, fare, total seats)
Manage bookings (trip, seats, passenger, phone, status held/awaiting_payment/confirmed/cancelled)
Manage payments (booking ref, method counter/online, amount, status pending/completed/refunded)
Collect customer enquiries (name, phone, message)`
};

window.__PRELOADED_PROJECT__ = <?= json_encode($preloadedProject) ?>;
window.__LOAD_ERROR__ = <?= json_encode($loadError ?? '') ?>;

function toggleLoadProjectMenu() {
  const d = document.getElementById('load-project-dropdown');
  if (d) d.style.display = (d.style.display === 'none' || !d.style.display) ? 'block' : 'none';
}
document.addEventListener('click', (e) => {
  const d = document.getElementById('load-project-dropdown');
  if (d && !e.target.closest('#load-project-dropdown') && !e.target.closest('button[onclick*="toggleLoadProjectMenu"]')) {
    d.style.display = 'none';
  }
});

/* ══════════════════════════════════════════════════
   INIT
═════════════════════════════════════════════════ */
/* ★ Studio handoff (?resume=1): Studio may have written a different scoped
   key than this tab's SESSION_KEY — adopt the NEWEST session with real
   designs across ALL keys, so saved images never "go missing". */
function loadBestResumeSession() {
  let best = null, bestAt = -1, bestAny = null, bestAnyAt = -1;
  const consider = (raw) => {
    try {
      const s = JSON.parse(raw);
      if (!s || !Array.isArray(s.designs) || !s.designs.length) return;
      const hasHtml = s.designs.some(d => d && typeof d.html === 'string' && d.html.trim().length > 50);
      if (!hasHtml) return;
      const at = s.savedAt || 0;
      if (at >= bestAnyAt) { bestAny = s; bestAnyAt = at; }
      if ((s.version === 2 || s.version === 3) && at >= bestAt) { best = s; bestAt = at; }
    } catch (e) {}
  };
  try {
    for (let i = 0; i < localStorage.length; i++) {
      const k = localStorage.key(i);
      if (k && k.indexOf('webcraft_saved_project::') === 0) consider(localStorage.getItem(k));
    }
    consider(localStorage.getItem('webcraft_saved_project'));
  } catch (e) {}
  if (best) return hydrateSessionDesigns(best);
  if (bestAny) {
    // Versionless bridge (studio shape) → normalize to builder shape
    try {
      const designs = bestAny.designs.map((d, i) => ({
        name: d.name || ('Concept ' + (i + 1)),
        description: d.description || '',
        badge: d.badge || '',
        html: d.html || '',
        adminHtml: d.adminHtml || null,
        history: []
      }));
      return {
        version: 3, savedAt: bestAnyAt || Date.now(), stage: 'builder',
        wizard: null, selectedGenMode: 'static', currentWizStep: 4,
        bizName: bestAny.bizName || bestAny.biz_name || 'Website',
        concepts: designs, designs,
        activeDesignIndex: bestAny.activeDesignIndex || 0,
        currentViewMode: 'site'
      };
    } catch (e) { return null; }
  }
  return null;
}
window.addEventListener('DOMContentLoaded', async () => {
  // ─── SUPABASE CONNECTION ANNOUNCEMENT ───
  console.log('%c🟢 Supabase PostgreSQL: Connected & Synced! (scuzaitwwbnsllvyqced.supabase.co)', 'background: #062b22; color: #34d399; font-weight: bold; font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 1.5px solid #10b981;');

  // ★ Heal guest→login split: guest work must follow the user after login.
  // If the guest session is NEWER than this login's session, adopt it —
  // else resume would show stale files while Studio shows fresh ones.
  try {
    const me = ((window.__CUSTOMER__ && window.__CUSTOMER__.email) || 'guest').toLowerCase();
    if (me !== 'guest') {
      const graw = localStorage.getItem('webcraft_saved_project::guest');
      if (graw) {
        const g = JSON.parse(graw);
        const gHas = g && Array.isArray(g.designs) && g.designs.length
          && g.designs.some(dd => dd && typeof dd.html === 'string' && dd.html.trim().length > 50);
        const sraw = localStorage.getItem(SESSION_KEY);
        const s = sraw ? JSON.parse(sraw) : null;
        if (gHas && (!s || (g.savedAt || 0) > (s.savedAt || 0))) {
          localStorage.setItem(SESSION_KEY, graw);
          __lastSessionSeen = g.savedAt || Date.now();
          setTimeout(() => showToast('🔄 Picked up your guest work — saved to your account'), 1500);
        }
      }
    }
  } catch (e) {}

  // ★ Studio handoff params (?resume=1&concept=N&view=site|admin) — honor them,
  // and show a 3D loading screen while the workspace restores.
  let resumeQs = null;
  try {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get('resume') === '1') {
      const qc = parseInt(qs.get('concept') || '-1', 10);
      const qv = qs.get('view');
      resumeQs = { concept: isNaN(qc) ? -1 : qc, view: (qv === 'admin' || qv === 'site') ? qv : '' };
    }
  } catch (e) {}
  // ★ variations.php handoff params (?wizard=1 → wizard screen only;
  // ?layouts=N → restore session then auto-open the 3 layouts of concept N;
  // ?step=1 → force back to FIRST stage, never stuck mid-wizard)
  let wizardOnly = false;
  let layoutsQs = -1;
  let forceStep = 0;
  try {
    const qs2 = new URLSearchParams(window.location.search);
    if (qs2.get('wizard') === '1') wizardOnly = true;
    const ql = parseInt(qs2.get('layouts') || '-1', 10);
    if (!isNaN(ql) && ql >= 0 && ql <= 2) layoutsQs = ql;
    const st = parseInt(qs2.get('step') || '0', 10);
    if (!isNaN(st) && st >= 1 && st <= 4) forceStep = st;
  } catch (e) {}
  if (wizardOnly) {
    setTimeout(hideResumeLoader, 400);
    await refreshAccountUI();
    if (forceStep >= 1) { try { currentWizStep = forceStep; } catch (e) {} }
    updateWizDisplay();
    try { updateGenerateButton(); } catch (e) {}
    try { if (forceStep >= 1) saveSessionNow(); } catch (e) {}
    return;
  }
  if (resumeQs) {
    // ★ Adopt the newest session across ALL keys BEFORE restore reads SESSION_KEY
    try {
      const best = loadBestResumeSession();
      if (best) {
        localStorage.setItem(SESSION_KEY, JSON.stringify(best));
        __lastSessionSeen = best.savedAt || Date.now();
      }
    } catch (e) {}
  }
  if (resumeQs && window.Loader3D) {
    try { Loader3D.show('Opening your workspace…', 'Loading your saved designs', 'home'); } catch (e) {}
  } else if (window.Loader3D) {
    // ★ Situation-based boot loader: database connect wait (short, creative 3D)
    try { Loader3D.show('Connecting to secure database…', 'Verifying session · syncing workspace', 'db'); } catch (e) {}
  }
  const hideResumeLoader = () => { try { if (window.Loader3D) Loader3D.hide(); } catch (e) {} };

  setTimeout(() => {
    showToast('🟢 Supabase Database Connected & Synced');
  }, 750);

  await refreshAccountUI();
  if (window.__LOAD_ERROR__) setTimeout(() => showToast(window.__LOAD_ERROR__), 600);

  // ★ If an existing project was requested via ?order_id or ?slug, boot directly into workspace
  if (window.__PRELOADED_PROJECT__ && window.__PRELOADED_PROJECT__.html) {
    const p = window.__PRELOADED_PROJECT__;
    const preWantsAdmin = (p.gen_mode || 'static') !== 'static';
    if (p.gen_mode) selectedGenMode = p.gen_mode;
    generatedDesigns = [{
      name: p.biz_name,
      description: 'Loaded project ' + (p.order_id || p.slug),
      badge: 'Active Project',
      html: p.html,
      adminHtml: null,
      history: []
    }];
    generatedConcepts = generatedDesigns;
    activeDesignIndex = 0;
    currentHtml = p.html;
    currentViewMode = 'site';
    const bizInp = document.getElementById('biz_name');
    if (bizInp) bizInp.value = p.biz_name;
    restoreIntoWorkspace();
    saveSessionNow();
    setTimeout(hideResumeLoader, 700);
    showToast('📂 Loaded ' + p.biz_name);
    return;
  }

  const restored = tryRestoreSession();

  // ★ Apply Studio handoff overrides (the exact concept + view that was saved)
  if (resumeQs && restored && document.getElementById('builder-screen')?.style.display === 'flex') {
    let needReRender = false;
    if (resumeQs.concept >= 0 && resumeQs.concept < generatedDesigns.length && resumeQs.concept !== activeDesignIndex) {
      activeDesignIndex = resumeQs.concept;
      needReRender = true;
    }
    if (resumeQs.view && resumeQs.view !== currentViewMode) {
      currentViewMode = resumeQs.view;
      needReRender = true;
    }
    if (needReRender) {
      restoreIntoWorkspace();
      saveSessionNow();
    }
  }

  if (!restored) {
    updateWizDisplay();
    hideResumeLoader();
  } else {
    updateWizDisplay();
    updateGenerateButton();
    // ★ variations.php "Layouts" deep-link (?layouts=N): jump straight
    // INSIDE the variation's 3 layout variants.
    if (layoutsQs >= 0 && generatedConcepts[layoutsQs]) {
      setTimeout(() => { try { showSubDesigns(layoutsQs); } catch (e) {} }, 700);
    }
    // Boot db loader already visible → give it a beat, then reveal
    setTimeout(hideResumeLoader, resumeQs ? 900 : 600);
  }

  wireAutoSave();

  // ★ Frame load detection: hide tab-load spinners the moment content renders.
  // If edit mode is ON (e.g. an AI update reloaded the frame), re-enable
  // inline editing on the fresh document so the mode persists.
  try {
    document.getElementById('live-iframe')?.addEventListener('load', () => {
      __hideSpin('live-frame-spin', 'live');
      if (editMode) { try { setIframeEditable(true); } catch (e) {} }
    });
    document.getElementById('modal-iframe')?.addEventListener('load', () => __hideSpin('modal-frame-spin', 'modal'));
  } catch (e) {}

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeFullscreenModal();
      closeGenerateModal();
      closeStepByStepGuide();
      stopVoiceInput();
    }
  });

  // Close guide modal on backdrop click
  document.getElementById('step-guide-modal')?.addEventListener('click', e => {
    if (e.target.id === 'step-guide-modal') {
      closeStepByStepGuide();
    }
  });

  window.addEventListener('beforeunload', () => {
    saveSessionNow();
  });
});

/* ══════════════════════════════════════════════════
   SESSION PERSISTENCE
═════════════════════════════════════════════════ */
// TASTE SKILL - customer vibe + dial prefs (design intelligence).
// Read by assets/js/opencode-service.js via window.collectTastePrefs and
// embedded in every generation payload via collectWizardSnapshot.
// 0 on a dial = Auto (server infers from brief, baseline 8/6/4).
function collectTastePrefs() {
  var vibeEl = document.querySelector('input[name="taste_vibe"]:checked');
  var num = function (id) {
    var el = document.getElementById(id);
    var v = el ? parseInt(el.value || '0', 10) : 0;
    if (isNaN(v) || v < 0) return 0;
    return Math.min(10, v);
  };
  return {
    vibe: (vibeEl && vibeEl.value) || 'auto',
    variance: num('taste_variance'),
    motion: num('taste_motion'),
    density: num('taste_density')
  };
}
window.collectTastePrefs = collectTastePrefs;
document.addEventListener('change', function (e) {
  if (e && e.target && e.target.name === 'taste_vibe') updateTasteRead();
});

function updateTasteRead() {
  try {
    var t = collectTastePrefs();
    const show = function (id, v) {
      var el = document.getElementById(id);
      if (el) el.textContent = v > 0 ? String(v) : 'Auto';
    };
    show('taste_variance_val', t.variance);
    show('taste_motion_val', t.motion);
    show('taste_density_val', t.density);
    var vibeLabels = { auto: 'Auto', minimalist: 'Minimal', premium: 'Premium', playful: 'Playful', editorial: 'Editorial', brutalist: 'Brutalist', trust: 'Trust-first' };
    var vibeLabel = vibeLabels[t.vibe] || 'Auto';
    var box = document.getElementById('taste-read');
    if (box) {
      box.innerHTML = (t.vibe === 'auto' && !t.variance && !t.motion && !t.density)
        ? 'Auto: the AI reads your business + direction and picks the design language + dials (baseline 8/6/4). Move a slider to override.'
        : 'Taste: <strong style="color:#a5b4fc">' + vibeLabel + '</strong> - variance ' + (t.variance || 'auto') + ' - motion ' + (t.motion || 'auto') + ' - density ' + (t.density || 'auto');
    }
  } catch (e) {}
}
function collectWizardSnapshot() {
  const sections = Array.from(document.querySelectorAll('input[name="sections[]"]:checked')).map(c => c.value);
  return {
    biz_name:         document.getElementById('biz_name')?.value.trim() || '',
    biz_type:         document.getElementById('biz_type')?.value || '',
    biz_tagline:      document.getElementById('biz_tagline')?.value.trim() || '',
    biz_audience:     document.getElementById('biz_audience')?.value.trim() || '',
    biz_services:     document.getElementById('biz_services')?.value.trim() || '',
    biz_products:     document.getElementById('biz_products')?.value.trim() || '',
    biz_reviews:      document.getElementById('biz_reviews')?.value.trim() || '',
    design_style:     document.querySelector('input[name="design_style"]:checked')?.value || 'modern',
    design_direction: document.getElementById('design_direction')?.value.trim() || '',
    color_palette:    document.querySelector('input[name="color_palette"]:checked')?.value || 'purple',
    biz_phone:        document.getElementById('biz_phone')?.value.trim() || '',
    biz_email:        document.getElementById('biz_email')?.value.trim() || '',
    biz_address:      document.getElementById('biz_address')?.value.trim() || '',
    biz_logo:         document.getElementById('biz_logo')?.value.trim() || '',
    biz_instagram:    document.getElementById('biz_instagram')?.value.trim() || '',
    biz_facebook:     document.getElementById('biz_facebook')?.value.trim() || '',
    biz_x:            document.getElementById('biz_x')?.value.trim() || '',
    biz_youtube:      document.getElementById('biz_youtube')?.value.trim() || '',
    admin_requirements: document.getElementById('admin_requirements')?.value.trim() || '',
    biz_brief: document.getElementById('biz_brief')?.value.trim() || '',
    gallery_subject: (() => {
      const on = document.getElementById('sec-gallery')?.checked;
      const v = document.getElementById('gallery_subject')?.value.trim() || '';
      return on ? v : '';
    })(),
    taste: (typeof collectTastePrefs === 'function' ? collectTastePrefs() : { vibe: 'auto', variance: 0, motion: 0, density: 0 }),
    sections
  };
}

/* ★ Payload slimming: concepts[] and designs[] usually carry IDENTICAL html
   (2x bytes → localStorage quota fail → "saved but old photo returns").
   Drop concepts[].html when it duplicates designs[i].html; hydrateSessionDesigns()
   restores it on every read. */
function slimConceptsForSave(concepts, designs) {
  try {
    return (concepts || []).map((c, i) => {
      const d = (designs || [])[i];
      if (c && d && c.html && c.html === d.html) {
        const slim = Object.assign({}, c);
        slim.html = '';
        return slim;
      }
      return c;
    });
  } catch (e) { return concepts || []; }
}
function hydrateSessionDesigns(s) {
  try {
    if (!s || !Array.isArray(s.designs)) return s;
    if (Array.isArray(s.concepts)) {
      s.concepts.forEach((c, i) => {
        if (c && !c.html && s.designs[i] && s.designs[i].html) c.html = s.designs[i].html;
      });
      if (!s.concepts.length && s.designs.length) s.concepts = s.designs;
    } else {
      s.concepts = s.designs;
    }
  } catch (e) {}
  return s;
}

function saveSessionNow() {
  try {
    // ★ Never clobber newer data saved by Studio in another tab:
    // adopt it first, then write back merged.
    try {
      const raw = localStorage.getItem(SESSION_KEY);
      if (raw) {
        const s = hydrateSessionDesigns(JSON.parse(raw));
        if (s && (s.savedAt || 0) > (__lastSessionSeen || 0) && Array.isArray(s.designs) && s.designs.length) {
          generatedDesigns = s.designs;
          generatedConcepts = s.concepts || s.designs;
          if (typeof s.activeDesignIndex === 'number') activeDesignIndex = s.activeDesignIndex;
          if (s.currentViewMode) currentViewMode = s.currentViewMode;
          if (s.selectedGenMode) selectedGenMode = s.selectedGenMode;
          __lastSessionSeen = s.savedAt;
        }
      }
    } catch (e) {}
    let stage = 'wizard';
    if (document.getElementById('builder-screen')?.style.display === 'flex') stage = 'builder';
    else if (document.getElementById('designs-screen')?.style.display === 'block') stage = 'designs';

    // ★ Preserve publish linkage: once publish.php records slug/adminUrl for
    // this project, autosaves must carry it forward (Studio resolves the
    // REAL per-project admin from it instead of a mock preview).
    let keepPublished = null;
    let keepPreview = null;
    try {
      const prev = JSON.parse(localStorage.getItem(SESSION_KEY) || 'null');
      if (prev && prev.published && prev.published.adminUrl) keepPublished = prev.published;
      if (prev && prev.preview && prev.preview.id && prev.preview.adminUrl) keepPreview = prev.preview;
    } catch (e) {}

    const payload = {
      version: 3,
      savedAt: Date.now(),
      stage,
      wizard: collectWizardSnapshot(),
      selectedGenMode,
      bizSpec: (typeof window.__bizSpec !== 'undefined' ? window.__bizSpec : (__bizSpec || null)),
      routeMeta: (typeof window.__routeMeta !== 'undefined' ? window.__routeMeta : null),
      // Blueprint interview progress + approved canonical spec (additive;
      // never stores credentials — interview never asks for passwords).
      blueprint: (typeof window.__blueprint !== 'undefined' && window.__blueprint.spec) ? {
        mode: window.__blueprint.mode, answers: window.__blueprint.answers,
        skipped: window.__blueprint.skipped, approved: !!window.__blueprint.approved,
        spec: window.__blueprint.spec,
        architecture: window.__blueprint.architecture || null,
        completeness: window.__blueprint.completeness || null,
      } : null,
      // Post-generation verification report (additive).
      verifyReport: (typeof window.__verifyReport !== 'undefined' ? window.__verifyReport : null),
      // ★ Admin credentials typed in the generate modal travel to publish,
      // which pre-fills them (same convention as the stored SMTP password).
      // Publish still validates + allows editing before creating the login.
      adminUsername: ((document.getElementById('admin_custom_user')?.value || '').trim().toLowerCase()
        || (window.__pendingAdminCreds?.user || '')),
      adminPassword: (document.getElementById('admin_custom_pass')?.value || (window.__pendingAdminCreds?.pass || '')),
      currentWizStep,
      bizName: document.getElementById('biz_name')?.value.trim() || 'Website',
      concepts: slimConceptsForSave(generatedConcepts, generatedDesigns),
      designs: generatedDesigns,
      activeDesignIndex,
      currentViewMode,
      published: keepPublished,
      preview: keepPreview
    };
    localStorage.setItem(SESSION_KEY, JSON.stringify(payload));
    __lastSessionSeen = payload.savedAt;
  } catch (e) { console.warn('[session save]', e); }
}
// Tracks newest session write we know about (ours or adopted) — guards cross-tab clobber.
let __lastSessionSeen = 0;
try {
  const _r = localStorage.getItem(SESSION_KEY);
  if (_r) __lastSessionSeen = JSON.parse(_r).savedAt || 0;
} catch (e) {}
// ★ Live-sync: Studio saved in another tab → adopt instantly (no stale overwrite on unload).
window.addEventListener('storage', (e) => {
  if (!e || e.key !== SESSION_KEY || !e.newValue) return;
  try {
    const p = hydrateSessionDesigns(JSON.parse(e.newValue));
    if (!p || !Array.isArray(p.designs) || !p.designs.length) return;
    if ((p.savedAt || 0) <= (__lastSessionSeen || 0)) return;
    __lastSessionSeen = p.savedAt;
    generatedDesigns = p.designs;
    generatedConcepts = p.concepts || p.designs;
    if (typeof p.activeDesignIndex === 'number') activeDesignIndex = p.activeDesignIndex;
    if (p.currentViewMode) currentViewMode = p.currentViewMode;
    if (document.getElementById('builder-screen')?.style.display === 'flex' && currentViewMode === 'site') {
      const c = generatedDesigns[activeDesignIndex] || generatedDesigns[0];
      if (c && c.html && c.html !== currentHtml) {
        try { pushHistory(activeDesignIndex, currentHtml); } catch (err) {}
        currentHtml = c.html;
        updateLiveIframe(currentHtml);
        try { updateUndoBtn(); } catch (err) {}
        showToast('🔄 Synced with Studio!');
      }
    } else {
      showToast('🔄 Synced with Studio — refresh view to see latest');
    }
  } catch (err) {}
});

function loadSession() {
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    if (!raw) return null;
    const data = hydrateSessionDesigns(JSON.parse(raw));
    if (!data || (data.version !== 2 && data.version !== 3)) return null;
    return data;
  } catch (e) { return null; }
}

function tryRestoreSession() {
  const saved = loadSession();
  if (!saved) return false;

  const hasConcepts = Array.isArray(saved.concepts) && saved.concepts.length > 0;
  const hasDesigns  = Array.isArray(saved.designs)  && saved.designs.length  > 0;

  if (saved.wizard) {
    const w = saved.wizard;
    const set = (id, val) => { const el = document.getElementById(id); if (el && val != null) el.value = val; };
    set('biz_name', w.biz_name);
    set('biz_products', w.biz_products);
    set('biz_reviews', w.biz_reviews);
    set('biz_type', w.biz_type);
    set('biz_tagline', w.biz_tagline);
    set('biz_audience', w.biz_audience);
    set('biz_services', w.biz_services);
    set('design_direction', w.design_direction);
    set('biz_phone', w.biz_phone);
    set('biz_email', w.biz_email);
    set('biz_address', w.biz_address);
    set('biz_logo', w.biz_logo);
    set('biz_instagram', w.biz_instagram);
    set('biz_facebook', w.biz_facebook);
    set('biz_x', w.biz_x);
    set('biz_youtube', w.biz_youtube);
    set('admin_requirements', w.admin_requirements);
    set('gallery_subject', w.gallery_subject);
    try {
      const gcb = document.getElementById('sec-gallery');
      const grow = document.getElementById('gallery-subject-row');
      const on = Array.isArray(w.sections) && w.sections.includes('gallery');
      if (gcb) gcb.checked = on;
      if (grow) grow.style.display = on ? '' : 'none';
    } catch (e) {}

    if (w.design_style) {
      const r = document.querySelector(`input[name="design_style"][value="${w.design_style}"]`);
      if (r) r.checked = true;
    }
    if (w.color_palette) {
      const r = document.querySelector(`input[name="color_palette"][value="${w.color_palette}"]`);
      if (r) r.checked = true;
    }
    if (w.taste) {
      if (w.taste.vibe) {
        const tr = document.querySelector(`input[name="taste_vibe"][value="${w.taste.vibe}"]`);
        if (tr) tr.checked = true;
      }
      ['variance', 'motion', 'density'].forEach(k => {
        const el = document.getElementById('taste_' + k);
        if (el && typeof w.taste[k] === 'number') el.value = Math.max(0, Math.min(10, w.taste[k]));
      });
      try { updateTasteRead(); } catch (e) {}
    }
    if (Array.isArray(w.sections)) {
      document.querySelectorAll('input[name="sections[]"]').forEach(cb => {
        cb.checked = w.sections.includes(cb.value);
      });
    }
  }

  if (saved.selectedGenMode) selectedGenMode = saved.selectedGenMode;
  if (saved.currentWizStep)  currentWizStep  = saved.currentWizStep;
  // Restore blueprint interview progress + approved spec (additive).
  try {
    if (saved.blueprint && saved.blueprint.spec) {
      window.__blueprint = {
        mode: saved.blueprint.mode || 'quick',
        answers: saved.blueprint.answers || {},
        skipped: saved.blueprint.skipped || {},
        spec: saved.blueprint.spec,
        questions: [], completeness: saved.blueprint.completeness || null,
        architecture: saved.blueprint.architecture || null,
        approved: !!saved.blueprint.approved,
      };
      bpNormalize(saved.blueprint.spec);
    }
    if (saved.verifyReport) window.__verifyReport = saved.verifyReport;
    if (saved.routeMeta) window.__routeMeta = saved.routeMeta;
  } catch (e) {}

  if (!hasConcepts && !hasDesigns) {
    updateWizDisplay();
    return false;
  }

  generatedConcepts = saved.concepts || [];
  generatedDesigns  = saved.designs  || [];
  activeDesignIndex = saved.activeDesignIndex || 0;
  currentViewMode   = saved.currentViewMode || 'site';
  currentWizStep    = 4;

  if (hasDesigns && saved.stage === 'builder') {
    restoreIntoWorkspace();
    showResumeBanner('Resumed your editing session · ' + generatedDesigns.length + ' variations');
    return true;
  }

  if (hasConcepts) {
    // ★ Direct flow: concepts picker lives ONLY on variations.php.
    // No in-builder intermediate screen — go straight there.
    try { saveSessionNow(); } catch (e) {}
    window.location.href = ((typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '') + '/variations.php';
    return true;
  }

  return false;
}

function restoreIntoWorkspace() {
  const c = generatedDesigns[activeDesignIndex];
  if (!c) return;

  document.getElementById('wizard-screen').style.display = 'none';
  document.getElementById('designs-screen').style.display = 'none';
  document.getElementById('builder-screen').style.display = 'flex';
  document.getElementById('tb-biz-badge').textContent = document.getElementById('biz_name').value || 'Website';

  [0, 1, 2].forEach(i => document.getElementById(`tab-d${i}`)?.classList.toggle('active', i === activeDesignIndex));
  updateViewTabsVisibility();

  document.getElementById('vtab-site').classList.toggle('active', currentViewMode === 'site');
  document.getElementById('vtab-admin').classList.toggle('active', currentViewMode === 'admin');
  document.getElementById('current-view-label').textContent = currentViewMode === 'admin' ? '🔐 Admin Panel' : '🌐 Frontend Site';

  if (currentViewMode === 'admin') {
    const pub = getPublishedSession();
    const bizName = document.getElementById('biz_name')?.value || 'My Website';
    const slug = bizName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'site';
    const targetUrl = (pub && pub.adminUrl) ? pub.adminUrl : `${SITE_URL}/published/${slug}/admin/login.php`;

    const adminBar   = document.getElementById('admin-url-bar');
    const urlDisplay = document.getElementById('admin-url-display');
    const openBtn    = document.getElementById('admin-url-open-btn');
    const addFnBtn   = document.getElementById('btn-add-function');

    if (adminBar)   { adminBar.style.display = 'flex'; }
    if (urlDisplay) { urlDisplay.value = targetUrl; }
    if (openBtn)    { openBtn.href = targetUrl; openBtn.style.opacity = ''; openBtn.style.pointerEvents = ''; }
    if (addFnBtn)   { addFnBtn.style.display = ''; }

    loadWorkspaceAdmin();
  } else {
    currentHtml = c.html;
    updateLiveIframe(currentHtml);
  }
  updateUndoBtn();
}

function wireAutoSave() {
  let timer = null;
  const scheduleSave = () => {
    clearTimeout(timer);
    timer = setTimeout(saveSessionNow, 400);
  };
  document.querySelectorAll(
    '.wizard-body input, .wizard-body select, .wizard-body textarea, .admin-req-textarea'
  ).forEach(el => {
    el.addEventListener('input', scheduleSave);
    el.addEventListener('change', scheduleSave);
  });
}

function showResumeBanner(details) {
  const banner = document.getElementById('resume-banner');
  if (!banner) return;
  const det = document.getElementById('resume-details');
  if (det) det.textContent = details + ' · Auto-saved';
  banner.classList.add('show');
  setTimeout(() => banner.classList.remove('show'), 8000);
}
function dismissResumeBanner() {
  document.getElementById('resume-banner')?.classList.remove('show');
}
async function startFreshFromBanner() {
  if (!confirm('⚠️ Start fresh? This will delete your saved work.')) return;
  dismissResumeBanner();
  try { localStorage.removeItem(SESSION_KEY); } catch (e) {}
  try { localStorage.removeItem('webcraft_saved_project::guest'); } catch (e) {}
  generatedConcepts = [];
  generatedDesigns = [];
  activeDesignIndex = 0;
  currentHtml = '';
  currentWizStep = 1;
  selectedGenMode = 'static';
  document.getElementById('designs-grid').innerHTML = '';
  document.getElementById('gen-progress').classList.remove('active');
  document.getElementById('wizard-screen').style.display = '';
  document.getElementById('designs-screen').style.display = 'none';
  document.getElementById('builder-screen').style.display = 'none';
  updateWizDisplay();
  showToast('🧹 Fresh start — fill the wizard again');
}

/* ══════════════════════════════════════════════════
   DEMO PRESETS & QUICK FILL
═════════════════════════════════════════════════ */
const DEMO_PRESETS = {
  ecommerce: {
    name: 'Aura Luxe Fashion & Lifestyle',
    type: 'E-Commerce & Retail',
    tagline: 'Curated premium fashion, designer accessories, and modern lifestyle essentials.',
    audience: 'Discerning online shoppers, fashion lovers, and lifestyle trendsetters.',
    services: 'Express Worldwide Shipping, Premium Organic Materials, 30-Day Easy Returns, 24/7 Priority Support',
    products: "Minimalist Linen Overshirt | $58.00 | https://images.unsplash.com/photo-1596755094514-f87e34085b2c?auto=format&fit=crop&w=600&q=80\n" +
              "Chronograph Minimalist Watch | $145.00 | https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=600&q=80\n" +
              "Handcrafted Leather Backpack | $110.00 | https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=600&q=80\n" +
              "Matte Black Polarized Sunglasses | $42.00 | https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=600&q=80\n" +
              "Italian Leather Bifold Wallet | $36.00 | https://images.unsplash.com/photo-1627123424574-724758594e93?auto=format&fit=crop&w=600&q=80\n" +
              "ANC Wireless Earbuds Pro | $85.00 | https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=600&q=80",
    reviews: "Sophia Martinez | The linen overshirt quality is unbelievable. Breathable, premium finish, and true to size! | Verified Buyer\n" +
             "David Chen | The chronograph watch looks even better than the photos. Packaged securely with fast 2-day delivery! | Verified Customer\n" +
             "Emma Watson | Exchanged sizes seamlessly through their customer support. Truly a 5-star shopping experience! | Verified Buyer",
    style: 'modern',
    color: 'purple'
  },
  agency: {
    name: 'Zenith Studio',
    type: 'Creative & Digital Agency',
    tagline: 'Crafting next-generation digital experiences that inspire and scale.',
    audience: 'High-growth brands and modern businesses seeking top-tier digital quality.',
    services: 'UI/UX Design, Full-Stack Web Development, Brand Identity, Growth Optimization',
    style: 'modern',
    color: 'purple'
  },
  cafe: {
    name: 'Zen Cafe & Bakery',
    type: 'Restaurant, Cafe & Bar',
    tagline: 'Artisan specialty coffee and handcrafted pastries made fresh daily.',
    audience: 'Coffee enthusiasts, local community, breakfast lovers, and remote workers.',
    services: 'Specialty Espresso, Cold Brew, Sourdough Pastries, Artisan Brunch, Whole Beans',
    style: 'bold',
    color: 'gold'
  },
  saas: {
    name: 'Apex AI Cloud',
    type: 'Tech Startup & SaaS',
    tagline: 'Intelligent workflow automation and real-time analytics powered by AI.',
    audience: 'Software engineers, fast-scaling startups, and modern enterprise teams.',
    services: 'Automated Pipelines, Real-Time Dashboards, API Webhooks, 99.9% Uptime Cloud',
    style: 'modern',
    color: 'blue'
  },
  gym: {
    name: 'Pulse Fitness Club',
    type: 'Fitness Center & Personal Trainer',
    tagline: 'Transform your physical performance with expert personal training and modern equipment.',
    audience: 'Fitness lovers, athletes, and beginners ready to build strength and endurance.',
    services: '1-on-1 Personal Training, HIIT Sessions, Custom Nutrition Coaching, Strength Zone',
    style: 'bold',
    color: 'green'
  },
  bus: {
    name: 'City Express Travels',
    type: 'Bus, Transport & Travel',
    tagline: 'Daily bus service from Chennai to Bangalore with online seat booking, QR tickets and counter payment.',
    audience: 'Daily commuters, office goers, students and weekend travellers on the Chennai-Bangalore route.',
    services: 'Daily Chennai-Bangalore trips, Online seat booking with 15-min hold, QR ticket at boarding, Counter + online payment, Easy cancellation',
    style: 'modern',
    color: 'blue'
  }
};

function fillDemoData(presetKey = 'agency') {
  const p = DEMO_PRESETS[presetKey] || DEMO_PRESETS.agency;
  const set = (id, val) => { const el = document.getElementById(id); if (el && val != null) el.value = val; };
  set('biz_name', p.name);
  set('biz_type', p.type);
  set('biz_tagline', p.tagline);
  set('biz_audience', p.audience);
  set('biz_services', p.services);
  set('biz_products', p.products || '');
  set('biz_reviews', p.reviews || '');

  if (p.style) {
    const r = document.querySelector(`input[name="design_style"][value="${p.style}"]`);
    if (r) r.checked = true;
  }
  if (p.color) {
    const c = document.querySelector(`input[name="color_palette"][value="${p.color}"]`);
    if (c) c.checked = true;
  }

  // ★ Bus demo = booking business: frontend booking form + backend admin stay
  // connected. Shop section OFF (trips are catalog, not cart), contact ON,
  // and bus admin preset pre-filled so generate builds bookings + QR ticket.
  if (presetKey === 'bus') {
    try {
      const shopCb = document.querySelector('input[name="sections[]"][value="shop"]');
      if (shopCb) shopCb.checked = false;
      const contactCb = document.querySelector('input[name="sections[]"][value="contact"]');
      if (contactCb) contactCb.checked = true;
      const ar = document.getElementById('admin_requirements');
      if (ar && !String(ar.value || '').trim() && typeof ADMIN_PRESETS !== 'undefined' && ADMIN_PRESETS.bus) {
        ar.value = ADMIN_PRESETS.bus;
      }
    } catch (e) {}
  }

  const menu = document.getElementById('demo-presets-menu');
  if (menu) menu.style.display = 'none';

  saveSessionNow();
  if (typeof updateGenerateButton === 'function') updateGenerateButton();
  showToast(`✨ Demo data filled (${p.name})! Click Next or customize.`);
}

function clearWizardForm() {
  const set = (id) => { const el = document.getElementById(id); if (el) el.value = ''; };
  set('biz_name');
  set('biz_tagline');
  set('biz_audience');
  set('biz_services');
  set('biz_products');
  set('biz_reviews');
  set('biz_brief');
  set('gallery_subject');
  try {
    const gcb = document.getElementById('sec-gallery');
    if (gcb) gcb.checked = false;
    const grow = document.getElementById('gallery-subject-row');
    if (grow) grow.style.display = 'none';
  } catch (e) {}
  const out = document.getElementById('biz-brief-result');
  if (out) out.textContent = '';
  const bizInp = document.getElementById('biz_name');
  if (bizInp) bizInp.focus();
  const menu = document.getElementById('demo-presets-menu');
  if (menu) menu.style.display = 'none';
  saveSessionNow();
  if (typeof updateGenerateButton === 'function') updateGenerateButton();
  showToast('🧹 Form cleared!');
}

function toggleDemoPresetsMenu(e) {
  if (e) e.stopPropagation();
  const menu = document.getElementById('demo-presets-menu');
  if (menu) {
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
  }
}

document.addEventListener('click', () => {
  const menu = document.getElementById('demo-presets-menu');
  if (menu && menu.style.display === 'block') menu.style.display = 'none';
});

/* ══════════════════════════════════════════════════
   WIZARD
═════════════════════════════════════════════════ */
let currentWizStep = 1;
function wizNav(dir) {
  if (dir === 1 && !validateStep(currentWizStep)) return;
  currentWizStep = Math.max(1, Math.min(4, currentWizStep + dir));
  updateWizDisplay();
  saveSessionNow();
}
function validateStep(step) {
  if (step === 1) {
    const n = document.getElementById('biz_name').value.trim();
    const t = document.getElementById('biz_tagline').value.trim();
    if (!n) { showToast('⚠️ Enter Business Name'); document.getElementById('biz_name').focus(); return false; }
    if (!t) { showToast('⚠️ Enter Core Mission / Tagline'); document.getElementById('biz_tagline').focus(); return false; }
  }
  return true;
}
function updateWizDisplay() {
  document.querySelectorAll('.wizard-step').forEach((s, i) => s.classList.toggle('active', i + 1 === currentWizStep));
  ['wp1','wp2','wp3','wp4'].forEach((id, i) => {
    const d = document.getElementById(id);
    if (!d) return;
    d.classList.toggle('active', i + 1 === currentWizStep);
    d.classList.toggle('done', i + 1 < currentWizStep);
  });
  document.getElementById('wiz-prev').disabled = (currentWizStep === 1);
  document.getElementById('wiz-step-counter').textContent = `Step ${currentWizStep} of 4`;
  document.getElementById('wiz-next').style.display = (currentWizStep === 4) ? 'none' : 'inline-flex';
  const cb = document.getElementById('wiz-clear');
  if (cb) cb.style.display = (currentWizStep === 4) ? 'inline-flex' : 'none';
  updateGenerateButton();
}
function updateGenerateButton() {
  const b = document.getElementById('wiz-generate-btn');
  if (!b) return;
  const data = collectWizardSnapshot();
  const ready = !!(data.biz_name && data.biz_tagline);
  b.disabled = !ready;
  b.style.opacity = ready ? '1' : '0.55';
}

/* ══════════════════════════════════════════════════
   ACCOUNT UI (local session — no third-party auth)
═════════════════════════════════════════════════ */
async function refreshAccountUI() {
  const av = document.getElementById('account-avatar');
  const nm = document.getElementById('account-name');
  const st = document.getElementById('account-status');
  const ac = document.getElementById('account-actions');
  const cb = document.getElementById('wiz-clear');
  if (av) av.textContent = '✦';
  if (nm) nm.textContent = 'WebCraft AI';
  if (st) { st.textContent = 'AI Active · No sign-in needed'; st.className = 'account-status'; }
  if (ac) ac.innerHTML = '';
  if (cb) cb.style.display = (currentWizStep === 4) ? 'inline-flex' : 'none';
  updateGenerateButton();
}
async function clearAndSwitchAccount() {
  if (!confirm('Clear session and start fresh?\n\nThis wipes drafts too.')) return;
  try { window.AIFlowCraft?.clearLocalState?.(); window.AIFlowCraft?.clearGeneratedProjects?.(); } catch (e) {}
  try { localStorage.removeItem(SESSION_KEY); } catch (e) {}
  try { localStorage.removeItem('webcraft_saved_project::guest'); } catch (e) {}
  generatedConcepts = []; generatedDesigns = [];
  currentWizStep = 1; activeDesignIndex = 0; currentHtml = ''; selectedGenMode = 'static';
  document.getElementById('designs-grid').innerHTML = '';
  document.getElementById('gen-progress').classList.remove('active');
  document.getElementById('wizard-screen').style.display = '';
  document.getElementById('designs-screen').style.display = 'none';
  document.getElementById('builder-screen').style.display = 'none';
  document.getElementById('refine-query').value = '';
  document.getElementById('magic-ai-panel').classList.remove('active');
  dismissResumeBanner();
  updateWizDisplay();
  await refreshAccountUI();
  showToast('Fresh start ready.');
}

/* ══════════════════════════════════════════════════
   STEP-BY-STEP FULL STACK GUIDE MODAL
═════════════════════════════════════════════════ */
function openStepByStepGuide() {
  const m = document.getElementById('step-guide-modal');
  if (m) {
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
}

function closeStepByStepGuide() {
  const m = document.getElementById('step-guide-modal');
  if (m) {
    m.classList.remove('open');
    document.body.style.overflow = '';
  }
}

/* ══════════════════════════════════════════════════
   GENERATE MODAL
═════════════════════════════════════════════════ */
let __bizSpec = null;
async function refreshBizSpec() {
  const box = document.getElementById('biz-spec-box');
  const title = document.getElementById('biz-auto-title');
  const desc = document.getElementById('biz-auto-desc');
  try {
    if (box) box.textContent = 'Analyzing business…';
    const data = collectWizardSnapshot();
    // Universal Intelligence Engine (canonical spec) with legacy fallback
    let j = null;
    try {
      const r = await fetch((typeof SITE_URL !== 'undefined' && SITE_URL ? SITE_URL : '') + '/api/biz-intelligence.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'analyze', data })
      });
      j = await r.json();
    } catch (e) { j = null; }
    if (!j || !j.success) {
      const r2 = await fetch((typeof SITE_URL !== 'undefined' && SITE_URL ? SITE_URL : '') + '/api/business.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'analyze', data })
      });
      const j2 = await r2.json();
      if (j2 && j2.success) {
        // Wrap legacy shape into compat spec
        j = { success: true, spec: { profile: { business_name: data.biz_name, domain: j2.spec.business_type }, capabilities: [], compat: j2.spec, uncertainty: { unresolved: j2.spec.follow_up || [] } }, summary: j2.summary };
      }
    }
    if (j && j.success && j.spec) {
      __bizSpec = j.spec;
      const compat = j.spec.compat || j.spec;
      selectedGenMode = compat.gen_mode || 'static';
      window.__bizSpec = __bizSpec;
      window.__bizCanonical = j.spec;
      // Complexity + routing preview (same decide path as generation)
      try {
        const rr = await fetch((typeof SITE_URL !== 'undefined' && SITE_URL ? SITE_URL : '') + '/api/generate-route.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'assess', spec: j.spec })
        });
        const rj = await rr.json();
        if (rj && rj.success && rj.route) {
          window.__routePreview = rj.route;
          const foot = document.getElementById('biz-auto-foot');
          if (foot) foot.textContent = '▸ ' + rj.route.complexity + ' project · ' + rj.route.variations + ' variation(s) · ' + rj.route.primary + '-first';
        }
      } catch (e) {}
      try { saveSessionNow(); } catch (e) {}
      const dom = (j.spec.profile || {}).domain || compat.business_type || 'business';
      if (title) title.textContent = 'Detected: ' + dom;
      if (desc) desc.textContent = 'Capabilities: ' + ((j.spec.capabilities || []).join(', ') || 'landing + contact');
      if (box) {
        const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;');
        const unres = ((j.spec.uncertainty || {}).unresolved || []).join(' ');
        box.innerHTML = '<strong style="color:#a5b4fc;">' + esc(j.summary || '') + '</strong>'
          + (unres ? '<br>Needs decision: ' + esc(unres) : '');
      }
      updateAdminReqSection();
      return __bizSpec;
    }
  } catch (e) { console.warn('[biz] analyze failed', e?.message); }
  if (box) box.textContent = 'Auto plan unavailable — will generate a complete site with safe defaults.';
  return null;
}
/* ══════════════════════════════════════════════════
   BLUEPRINT: adaptive interview + review + approve (additive).
   Approved canonical spec is the single source of truth; legacy
   __bizSpec is normalized from it so all existing consumers work.
   ═════════════════════════════════════════════════ */
window.__blueprint = window.__blueprint || { mode: 'quick', answers: {}, skipped: {}, spec: null, questions: [], completeness: null, architecture: null, approved: false };
function bpEsc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
// ★ Single-quote JS string for inline onclick="..." attributes.
// JSON.stringify emits double quotes which would terminate the HTML attribute
// early (onclick="bpAnswer("key","v")" → dead buttons). bpQ('a\'b') → 'a\'b'.
// Double quotes become \x22 so even hostile ids can't break the attribute.
function bpQ(s) { return "'" + String(s ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\x22') + "'"; }
function bpSetMode(m) {
  window.__blueprint.mode = (m === 'full') ? 'full' : 'quick';
  try { saveSessionNow(); } catch (e) {}
  bpRender();
}
function bpNormalize(spec) {
  // Legacy consumers read top-level gen_mode/needs_admin/etc.
  const compat = (spec && spec.compat) || {};
  const flat = Object.assign({}, compat, { canonical: spec });
  __bizSpec = flat;
  window.__bizSpec = flat;
  window.__bizCanonical = spec;
  return flat;
}
async function bpPost(action, extra) {
  const data = collectWizardSnapshot();
  const bp = window.__blueprint;
  const r = await fetch((typeof SITE_URL !== 'undefined' && SITE_URL ? SITE_URL : '') + '/api/biz-intelligence.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({ action, data, description: '', answers: bp.answers, skipped: bp.skipped }, extra || {}))
  });
  return r.json();
}
async function refreshBlueprint() {
  const bp = window.__blueprint;
  const box = document.getElementById('bp-review');
  if (box) box.innerHTML = '<span style="color:#94a3b8;">Building blueprint…</span>';
  try {
    const j = await bpPost('blueprint', {});
    if (j && j.success && j.spec) {
      bp.spec = j.spec;
      bp.questions = j.questions || [];
      bp.completeness = j.completeness || null;
      bp.architecture = j.architecture || null;
      bp.approved = false;
      bpNormalize(j.spec);
      try { saveSessionNow(); } catch (e) {}
      bpRender();
      return true;
    }
  } catch (e) { console.warn('[blueprint]', e?.message); }
  if (box) box.innerHTML = '<span style="color:#94a3b8;">Blueprint unavailable — legacy auto analysis still applies.</span>';
  return false;
}
async function bpAnswer(key, val, btn) {
  window.__blueprint.answers[key] = val;
  // ★ Instant select feedback — no typing needed; option click IS the answer.
  try {
    if (btn && btn.parentElement) {
      btn.parentElement.querySelectorAll('button').forEach(b => { b.style.borderColor = ''; b.style.background = ''; });
      btn.style.borderColor = '#10b981'; btn.style.background = 'rgba(16,185,129,0.15)';
    }
  } catch (e) {}
  await bpRebuild('Answer saved.');
}
async function bpCustom(key) {
  const el = document.getElementById('bp-custom-' + key);
  const v = (el?.value || '').trim();
  if (!v) { showToast('Type a custom answer first (or Skip)'); return; }
  await bpAnswer(key, v);
}
async function bpSkip(key) {
  window.__blueprint.skipped[key] = true;
  await bpRebuild('Skipped — assumption labeled.');
}
async function bpRebuild(note) {
  try {
    // ★ Single round-trip: the 'answer' action already returns the rebuilt
    // spec + questions + completeness + architecture. A second 'blueprint'
    // call was redundant and doubled the failure surface (stale UI on error).
    const j = await bpPost('answer', {});
    if (j && j.success && j.spec) {
      const bp = window.__blueprint;
      bp.spec = j.spec; bp.questions = j.questions || [];
      bp.completeness = j.completeness || null; bp.architecture = j.architecture || null;
      bp.approved = false;
      bpNormalize(j.spec);
      try { saveSessionNow(); } catch (e) {}
      bpRender();
      if (note) showToast(note);
    } else showToast('Could not save answer: ' + (((j && j.error) || (j && (j.issues || []).join('; ')) || 'unknown')));
  } catch (e) { showToast('Could not update blueprint — retry (' + (e && e.message ? e.message : 'network') + ')'); }
}
async function bpVerdict(id, verdict, btn) {
  let text = null, order = null;
  if (verdict === 'edited') {
    // ★ Prefill with the current description so Edit is one tap, not retyping.
    let cur = '';
    try {
      const r = (window.__blueprint.spec.requirements || []).find(x => x.id === id);
      if (r) cur = r.description || '';
    } catch (e) {}
    text = prompt('Edit this requirement:', cur);
    if (text === null) return;
    if (!text.trim()) { showToast('Empty edit ignored'); return; }
  }
  if (btn) { try { btn.disabled = true; } catch (e) {} }
  try {
    const r = await fetch((typeof SITE_URL !== 'undefined' && SITE_URL ? SITE_URL : '') + '/api/biz-intelligence.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'verdict', spec: window.__blueprint.spec, id, verdict, text, order })
    });
    const j = await r.json();
    if (j && j.success && j.spec) {
      window.__blueprint.spec = j.spec;
      window.__blueprint.approved = false;
      bpNormalize(j.spec);
      try { saveSessionNow(); } catch (e) {}
      bpRender();
      showToast('Requirement ' + verdict);
    } else showToast('Verdict rejected: ' + ((j && j.error) || 'unknown'));
  } catch (e) { showToast('Verdict failed — retry (' + (e && e.message ? e.message : 'network') + ')'); }
  finally { if (btn) { try { btn.disabled = false; } catch (e) {} } }
}
// ★ Explicit global exposure so dynamically-rendered onclick handlers always resolve.
try { window.bpAnswer = bpAnswer; window.bpCustom = bpCustom; window.bpSkip = bpSkip; window.bpVerdict = bpVerdict; window.bpRebuild = bpRebuild; window.bpApprove = bpApprove; window.bpSetMode = bpSetMode; } catch (e) {}
function bpApprove() {
  const bp = window.__blueprint;
  if (!bp.spec) { showToast('Blueprint still building — wait a moment'); return; }
  const blocking = (bp.completeness && bp.completeness.blocking) || [];
  if (blocking.length) { showToast('⚠️ Resolve first: ' + blocking[0]); return; }
  bp.approved = true;
  try { saveSessionNow(); } catch (e) {}
  bpRender();
  showToast('✓ Blueprint approved — generation will follow it exactly');
}
function bpClassBadge(c) {
  const m = { confirmed: ['✓ confirmed', '#10b981'], recommended: ['☆ recommended', '#f59e0b'], inferred: ['◌ inferred', '#818cf8'], needs_confirmation: ['? needs confirmation', '#f43f5e'], future: ['○ future', '#64748b'] };
  const x = m[c] || m.inferred;
  return '<span style="font-size:0.66rem;font-weight:800;color:' + x[1] + ';border:1px solid ' + x[1] + ';border-radius:999px;padding:0.08rem 0.45rem;white-space:nowrap;">' + x[0] + '</span>';
}
function bpRender() {
  const bp = window.__blueprint;
  const qBox = document.getElementById('bp-interview');
  const cBox = document.getElementById('bp-complete');
  const rBox = document.getElementById('bp-review');
  const st = document.getElementById('bp-status');
  if (!qBox || !rBox) return;
  // Interview questions
  if (bp.questions && bp.questions.length) {
    qBox.innerHTML = '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin-bottom:0.4rem;">❓ Quick questions (' + bp.questions.length + ')</div>' + bp.questions.map(q => {
      const opts = (q.options || []).map(o => '<button type="button" class="admin-preset-btn" onclick="bpAnswer(' + bpQ(q.key) + ',' + bpQ(o.v) + ',this)">' + bpEsc(o.label) + '</button>').join('');
      return '<div style="background:#0b0f17;border:1px solid #1e293b;border-radius:10px;padding:0.6rem 0.75rem;margin-bottom:0.45rem;">'
        + '<div style="font-size:0.8rem;color:#fff;font-weight:700;">' + bpEsc(q.q) + (q.critical ? ' <span style="color:#f43f5e;font-size:0.68rem;">REQUIRED</span>' : '') + (q.gate ? ' <span style="color:#f59e0b;font-size:0.68rem;">CONFIRM</span>' : '') + '</div>'
        + '<div style="display:flex;gap:0.35rem;flex-wrap:wrap;margin-top:0.45rem;">' + opts + '</div>'
        + '<div style="display:flex;gap:0.35rem;margin-top:0.4rem;"><input id="bp-custom-' + bpEsc(q.key) + '" class="w-input" style="font-size:0.78rem;padding:0.4rem 0.6rem;" placeholder="Custom answer… (optional — tapping an option above is enough)">'
        + '<button type="button" class="admin-preset-btn" onclick="bpCustom(' + bpQ(q.key) + ')">Send</button>'
        + (q.skippable === false ? '' : '<button type="button" class="admin-preset-btn" onclick="bpSkip(' + bpQ(q.key) + ')">Skip</button>') + '</div></div>';
    }).join('');
  } else {
    qBox.innerHTML = '<div style="font-size:0.78rem;color:#34d399;">✓ No open questions — interview complete.</div>';
  }
  // Completeness (real score from actual checks)
  if (bp.completeness) {
    const c = bp.completeness;
    const bar = '<div style="height:7px;background:#1e293b;border-radius:99px;overflow:hidden;margin:0.3rem 0 0.45rem;"><div style="height:100%;width:' + c.score + '%;background:linear-gradient(90deg,#6366f1,#10b981);"></div></div>';
    const rows = (c.checks || []).map(k => '<div style="font-size:0.74rem;color:' + (k.pass ? '#6ee7b7' : (k.critical ? '#fca5a5' : '#fcd34d')) + ';">' + (k.pass ? '✓' : '○') + ' ' + bpEsc(k.label) + ' <span style="color:#64748b;">— ' + bpEsc(k.detail) + '</span></div>').join('');
    cBox.innerHTML = '<div style="font-weight:800;color:#fff;font-size:0.82rem;">Completeness: ' + c.score + '% (from ' + (c.checks || []).length + ' checks)</div>' + bar
      + (c.blocking && c.blocking.length ? '<div style="font-size:0.76rem;color:#fca5a5;font-weight:700;">⛔ ' + bpEsc(c.blocking.join(' · ')) + '</div>' : '<div style="font-size:0.76rem;color:#6ee7b7;">✓ Safe to generate (assumptions documented).</div>')
      + '<details style="margin-top:0.3rem;"><summary style="cursor:pointer;font-size:0.74rem;color:#94a3b8;">Check details</summary>' + rows + '</details>';
  } else cBox.innerHTML = '';
  // Review
  const s = bp.spec;
  if (!s) { rBox.innerHTML = ''; }
  else {
    const full = bp.mode === 'full';
    const reqs = [...(s.requirements || [])].sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    const must = reqs.filter(r => r.priority === 'must' && r.approval !== 'rejected');
    const rest = reqs.filter(r => r.priority !== 'must' && r.approval !== 'rejected');
    const rej = reqs.filter(r => r.approval === 'rejected');
    const reqRow = (r) => '<div style="background:#0b0f17;border:1px solid #1e293b;border-radius:8px;padding:0.45rem 0.6rem;margin-bottom:0.35rem;">'
      + '<div style="font-size:0.78rem;color:#e2e8f0;">' + bpEsc(r.description) + '</div>'
      + '<div style="display:flex;gap:0.35rem;align-items:center;margin-top:0.3rem;flex-wrap:wrap;">' + bpClassBadge(r.class)
      + '<span style="font-size:0.66rem;color:#64748b;">conf ' + Number(r.confidence ?? 0).toFixed(2) + (r.reason ? ' · ' + bpEsc(r.reason).slice(0, 90) : '') + '</span>'
      + '<span style="flex:1"></span>'
      + '<button type="button" class="admin-preset-btn" style="font-size:0.66rem;padding:0.15rem 0.5rem;" onclick="bpVerdict(' + bpQ(r.id) + ',' + bpQ('accepted') + ',this)">Accept</button>'
      + '<button type="button" class="admin-preset-btn" style="font-size:0.66rem;padding:0.15rem 0.5rem;" onclick="bpVerdict(' + bpQ(r.id) + ',' + bpQ('edited') + ',this)">Edit</button>'
      + '<button type="button" class="admin-preset-btn" style="font-size:0.66rem;padding:0.15rem 0.5rem;" onclick="bpVerdict(' + bpQ(r.id) + ',' + bpQ('rejected') + ',this)">Reject</button></div></div>';
    const p = s.profile || {};
    let h = '<div style="font-weight:800;color:#fff;">' + bpEsc(p.business_name || 'Business') + ' <span style="color:#818cf8;font-weight:600;">· ' + bpEsc(p.domain || '') + '</span></div>';
    h += '<div style="font-size:0.76rem;color:#94a3b8;margin-bottom:0.5rem;">' + bpEsc(p.description || '').slice(0, 220) + '</div>';
    h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.5rem 0 0.3rem;">Must-have (' + must.length + ')</div>' + (must.map(reqRow).join('') || '<div style="font-size:0.76rem;color:#64748b;">None</div>');
    if (full || rest.length) h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.5rem 0 0.3rem;">Recommended &amp; optional (' + rest.length + ')</div>' + (rest.map(reqRow).join('') || '');
    if (rej.length) h += '<details style="margin-top:0.3rem;"><summary style="cursor:pointer;font-size:0.74rem;color:#64748b;">Rejected (' + rej.length + ') — kept for audit</summary>' + rej.map(r => '<div style="font-size:0.74rem;color:#64748b;">✕ ' + bpEsc(r.description) + '</div>').join('') + '</details>';
    const wfs = (s.workflows || []).map(w => '<div style="font-size:0.76rem;color:#cbd5e1;">▸ <strong>' + bpEsc(w.name || w.key) + '</strong>: ' + bpEsc((w.steps || []).join(' → ')).slice(0, 160) + '</div>').join('');
    h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.6rem 0 0.3rem;">Workflows</div>' + (wfs || '<div style="font-size:0.76rem;color:#64748b;">—</div>');
    const mods = ((s.compat || {}).admin_modules || []).map(m => '<span class="admin-preset-btn" style="cursor:default;">' + bpEsc(m) + '</span>').join('');
    h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.6rem 0 0.3rem;">Admin modules</div><div style="display:flex;gap:0.35rem;flex-wrap:wrap;">' + (mods || '<span style="font-size:0.76rem;color:#64748b;">None needed</span>') + '</div>';
    h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.6rem 0 0.3rem;">Data model</div><div style="font-size:0.76rem;color:#94a3b8;">' + (s.entities || []).map(e => bpEsc((e.label || e.name) + ' (' + (e.fields || []).length + ' fields)')).join(' · ') + '</div>';
    const wz = collectWizardSnapshot();
    h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.6rem 0 0.3rem;">Design</div><div style="font-size:0.76rem;color:#94a3b8;">' + bpEsc((wz.design_style || 'modern') + ' · ' + (wz.color_palette || 'purple') + (wz.biz_logo ? ' · logo supplied ✓' : ' · no logo (text brand, placeholders marked)')) + '</div>';
    const un = s.uncertainty || {};
    const asm = (un.assumptions || []).concat((un.unresolved || []).map(u => 'Unresolved: ' + u));
    if (asm.length) h += '<div style="font-weight:800;color:#fcd34d;font-size:0.82rem;margin:0.6rem 0 0.3rem;">Assumptions &amp; open items</div>' + asm.map(a => '<div style="font-size:0.74rem;color:#fcd34d;">• ' + bpEsc(a) + '</div>').join('');
    if (full) {
      h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.6rem 0 0.3rem;">Actors &amp; access</div><div style="font-size:0.74rem;color:#94a3b8;">' + (s.actors || []).map(a => { const o = (a && typeof a === 'object') ? a : null; const acts = (o && Array.isArray(o.actions)) ? o.actions.join(', ') : ''; return bpEsc((o ? (o.role || '') : String(a ?? '')) + (acts ? ': ' + acts : '')); }).join('<br>') + '</div>';
      h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.6rem 0 0.3rem;">Backend</div><div style="font-size:0.74rem;color:#94a3b8;">APIs: ' + ((s.backend || {}).apis || []).length + ' · tables: ' + ((s.backend || {}).tables || []).length + ' · transactions: ' + (((s.backend || {}).transactions || []).join('; ') || '—') + '</div>';
      h += '<div style="font-weight:800;color:#fff;font-size:0.82rem;margin:0.6rem 0 0.3rem;">Capabilities</div><div style="font-size:0.74rem;color:#94a3b8;">' + bpEsc((s.capabilities || []).join(', ')) + '</div>';
    }
    if (bp.architecture) h += '<div style="margin-top:0.6rem;padding:0.6rem 0.75rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.35);border-radius:10px;font-size:0.78rem;color:#a7f3d0;"><strong>Recommended: ' + bpEsc(bp.architecture.mode) + '</strong> — ' + bpEsc(bp.architecture.why) + '</div>';
    rBox.innerHTML = h;
  }
  if (st) st.textContent = bp.approved ? '✓ Approved — generation follows this spec' : (bp.spec ? 'Review, answer questions, then approve' : '');
  const ab = document.getElementById('bp-approve-btn');
  if (ab) ab.style.opacity = bp.approved ? '0.6' : '1';
  const qm = document.getElementById('bp-mode-quick'), fm = document.getElementById('bp-mode-full');
  if (qm) qm.style.borderColor = bp.mode === 'quick' ? '#10b981' : '';
  if (fm) fm.style.borderColor = bp.mode === 'full' ? '#10b981' : '';
}
function openGenerateModal() {
  if (!window.__CUSTOMER__ || !window.__CUSTOMER__.email) {
    showToast('Please sign in / create account first');
    setTimeout(() => { window.location.href = SITE_URL + '/customer-portal.php?view=signup'; }, 900);
    return;
  }
  const acc = document.getElementById('gen-modal-account');
  if (acc) acc.textContent = '';
  document.getElementById('generate-modal').classList.add('active');
  refreshBizSpec();
  refreshBlueprint();
}
function closeGenerateModal() { document.getElementById('generate-modal').classList.remove('active'); }
function pickGenerateMode(mode, btn) {
  selectedGenMode = mode;
  document.querySelectorAll('#generate-modal .gen-mode-card').forEach(c => c.classList.toggle('selected', c === btn));
  document.getElementById('gen-modal-mode-label').textContent = labelFor(mode);
  updateAdminReqSection();
}
function updateAdminReqSection() {
  const s = document.getElementById('admin-req-section');
  if (!s) return;
  // Auto mode: admin block only when spec says backend needed; credentials auto-suggested.
  const needsAdmin = __bizSpec ? !!__bizSpec.needs_admin : (selectedGenMode !== 'static');
  s.classList.toggle('active', needsAdmin);
  if (needsAdmin) suggestWizardCreds();
}
/* Pre-suggest admin credentials once (used for the admin page;
   publish reuses these — no re-entry needed there). */
function suggestWizardCreds() {
  const u = document.getElementById('admin_custom_user');
  const p = document.getElementById('admin_custom_pass');
  if (u && !u.value.trim()) {
    const biz = (document.getElementById('biz_name')?.value || 'site').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 12) || 'site';
    u.value = (biz + '_admin').slice(0, 20);
  }
  if (p && !p.value) {
    const words = ['Tiger', 'Ocean', 'Eagle', 'Maple', 'Comet', 'Lotus'];
    p.value = words[Math.floor(Math.random() * words.length)] + (1000 + Math.floor(Math.random() * 9000)) + '@';
  }
}
function fillWizardDemoCreds() {
  const u = document.getElementById('admin_custom_user');
  const p = document.getElementById('admin_custom_pass');
  if (u) u.value = 'demoadmin';
  if (p) p.value = 'Demo@1234';
  showToast('Demo credentials filled — change them if you like.');
}
/* ★ Admin password show/hide (eye toggle wired in the credentials block). */
function togglePassVisibility(inputId, btn) {
  const el = document.getElementById(inputId);
  if (!el) return;
  const show = el.type === 'password';
  el.type = show ? 'text' : 'password';
  if (btn) btn.textContent = show ? '🙈' : '👁️';
  try { el.focus({ preventScroll: true }); } catch (e) { try { el.focus(); } catch (e2) {} }
}
try { window.togglePassVisibility = togglePassVisibility; } catch (e) {}
function labelFor(mode) { return { static: 'Static', admin: 'Admin Panel + PHP', database: 'Admin + PHP + MySQL' }[mode] || mode; }
function confirmGenerate() {
  // Blueprint gate: an existing unapproved blueprint must be approved first;
  // blocking gaps always stop generation. Legacy path (no blueprint) proceeds.
  const bp = window.__blueprint;
  if (bp && bp.spec) {
    const blocking = (bp.completeness && bp.completeness.blocking) || [];
    if (blocking.length) { showToast('⛔ ' + blocking[0]); return; }
    if (!bp.approved) { showToast('📋 Approve the blueprint first (review above)'); return; }
  }
  const bpspec = (bp && bp.approved && bp.spec) ? bp.spec : null;
  const mode = bpspec ? (((bpspec.compat || {}).gen_mode) || selectedGenMode)
    : ((__bizSpec && __bizSpec.gen_mode) ? __bizSpec.gen_mode : selectedGenMode);
  selectedGenMode = mode;
  let adminUser = '', adminPass = '';
  if (mode !== 'static') {
    suggestWizardCreds();
    adminUser = (document.getElementById('admin_custom_user')?.value || '').trim().toLowerCase();
    adminPass = document.getElementById('admin_custom_pass')?.value || '';
    if (!/^[a-z][a-z0-9_]{3,19}$/.test(adminUser)) {
      showToast('⚠️ Admin username: 4–20 chars, start with a letter');
      document.getElementById('admin_custom_user')?.focus();
      return;
    }
    if (adminPass.length < 8) {
      showToast('⚠️ Admin password must be 8+ characters');
      document.getElementById('admin_custom_pass')?.focus();
      return;
    }
  }
  window.__pendingAdminCreds = { user: adminUser, pass: adminPass };
  saveSessionNow();
  closeGenerateModal();
  generateWithMode(selectedGenMode);
}

/* ★ AI Business Brief → Understand my business: analyzes the natural-language
   brief (+ Step-1 fields) via the universal analyzer and auto-configures
   Steps 1–3. Only fills what the customer hasn't set; never invents names,
   prices or credentials. Everything stays editable. */
const BIZ_BRIEF_INDUSTRY = { bus: 'Bus, Transport & Travel', ecommerce: 'E-Commerce & Retail', restaurant: 'Restaurant, Cafe & Bar',
  clinic: 'Healthcare & Wellness Clinic', realestate: 'Real Estate & Architecture', salon: 'Salon & Beauty',
  hotel: 'Hotel & Hospitality', travel: 'Travel & Tours', events: 'Events & Entertainment',
  rental: 'Rental & Hire', automotive: 'Automotive & Repairs',
  education: 'School / College / Education', portfolio: 'Personal Portfolio & Creator',
  agency: 'Creative & Digital Agency', professional: 'Professional Services & Legal' };
const BIZ_BRIEF_STYLE = { bus: ['trust', 'blue'], clinic: ['trust', 'blue'], restaurant: ['playful', 'red'],
  salon: ['premium', 'purple'], education: ['editorial', 'green'], ecommerce: ['premium', 'purple'],
  realestate: ['editorial', 'slate'], travel: ['editorial', 'blue'], events: ['bold', 'purple'],
  hotel: ['premium', 'slate'], rental: ['trust', 'blue'], automotive: ['bold', 'slate'] };
/* ★ Pending store: Understand prepares, Apply writes.
   Wizard is NEVER auto-touched — only applyUnderstoodBusiness() writes. */
window.__PendingBizApply = null;
function renderPendingApplyUI(html) {
  const out = document.getElementById('biz-brief-result');
  if (!out) return;
  out.innerHTML = html;
}
function escBizHtml(s) {
  return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
async function understandMyBusiness() {
  const briefEl = document.getElementById('biz_brief');
  const out = document.getElementById('biz-brief-result');
  const btn = document.getElementById('btn-understand-biz');
  const brief = (briefEl?.value || '').trim();
  if (brief.length < 10) { showToast('⚠️ Describe your business in a line or two first'); briefEl?.focus(); return; }
  const say = (m) => { if (out) out.textContent = m; };
  const orig = btn ? btn.textContent : '';
  try {
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Understanding…'; }
    // ★ Step 0 — parse only (no wizard write yet): customer's own words
    // staged as pending, so the form never auto-changes.
    const parts = parseBriefToFields(brief);
    say('Analyzing business type… wizard not changed yet.');
    let snap = {};
    try { snap = (typeof collectWizardSnapshot === 'function') ? collectWizardSnapshot() : {}; } catch (e) {}
    const res = await fetch(SITE_URL + '/api/business-analyzer.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'analyze', slug: 'preview', input: {
        biz_name: snap.biz_name || '', biz_type: snap.biz_type || '', description: brief,
        services: snap.biz_services || '', products: snap.biz_products || '',
        audience: snap.biz_audience || '' } })
    });
    const j = await res.json();
    if (!j || !j.success || !j.manifest) throw new Error('empty');
    const m = j.manifest, cat = (m.business || {}).category || 'generic';
    // Stage everything — wizard writes happen ONLY in applyUnderstoodBusiness().
    window.__PendingBizApply = { parts, brief, m, cat };
    const mods = (m.admin_modules || []).map(x => x.label || x).filter(Boolean);
    const transactional = ['bus', 'ecommerce', 'restaurant', 'hotel', 'salon', 'clinic', 'realestate', 'education', 'travel', 'events', 'rental', 'automotive', 'subscription'].indexOf(cat) >= 0;
    const adminVerdict = (transactional && Array.isArray(m.entities) && m.entities.length)
      ? 'Admin: YES (' + mods.slice(0, 6).join(', ') + ')'
      : 'Admin: not needed — static site is enough';
    const would = [];
    if (parts.biz_name) would.push('name');
    if (parts.tagline) would.push('tagline');
    if (parts.audience) would.push('audience');
    if (parts.services) would.push('services');
    if (BIZ_BRIEF_INDUSTRY[cat]) would.push('industry');
    if (BIZ_BRIEF_STYLE[cat]) would.push('style');
    if (transactional) would.push('contact section');
    if ((cat === 'ecommerce') || (cat === 'generic' && /shop|store|product|sell|buy|retail/i.test(brief))) would.push('shop section');
    if (transactional && Array.isArray(m.entities) && m.entities.length) would.push('admin requirements');
    const label = ((m.business || {}).label || cat);
    const follow = (m.follow_up || [])[0] || '';
    renderPendingApplyUI('<span style="color:#c7d2fe;">Detected: ' + escBizHtml(label)
      + ' · ready to apply: ' + escBizHtml(would.join(', ') || 'review')
      + ' · ' + escBizHtml(adminVerdict)
      + (follow ? ' · Q: ' + escBizHtml(follow) : '') + '</span> '
      + '<button type="button" class="wbtn wbtn-primary" id="btn-apply-biz" onclick="applyUnderstoodBusiness()" style="font-size:.8rem;padding:.45rem 1rem;margin-left:.5rem;">Apply to Builder ✓</button>');
    showToast('✨ Business understood — click Apply to Builder ✓ to update wizard');
  } catch (e) {
    const why = (e && e.message) ? String(e.message).slice(0, 90) : 'network';
    // Offline fallback: stage Step 0 parse so Apply still fills from words.
    try { window.__PendingBizApply = { parts: parseBriefToFields(brief), brief, m: null, cat: 'generic', offlineWhy: why }; } catch (e2) {}
    renderPendingApplyUI('<span style="color:#fbbf24;">Wizard filled from your words (ready) — analysis retry (' + escBizHtml(why) + '). Click to apply Step 0 fields:</span> '
      + '<button type="button" class="wbtn wbtn-primary" id="btn-apply-biz" onclick="applyUnderstoodBusiness()" style="font-size:.8rem;padding:.45rem 1rem;margin-left:.5rem;">Apply to Builder ✓</button>');
    showToast('⚠️ Analysis retry pannunga (' + why + ') — Apply click panna parsed fields update aagum');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = orig; }
  }
}
/* ★ Apply step: ONLY place that writes Understand results into the wizard.
   Fill ONLY empty fields — customer text always wins, nothing invented
   beyond what the brief itself contains. */
function applyUnderstoodBusiness() {
  const p = window.__PendingBizApply;
  if (!p) { showToast('⚠️ Muthalla Understand click pannunga'); return; }
  const out = document.getElementById('biz-brief-result');
  const say = (m) => { if (out) out.textContent = m; };
  let touched = [];
  const fillEmpty = (id, v, tag) => {
    const el = document.getElementById(id);
    const val = String(v || '').trim();
    if (el && val && !String(el.value || '').trim()) { el.value = val; touched.push(tag || id); }
  };
  const parts = p.parts || {};
  const brief = p.brief || '';
  // ★ Step 0 — ALWAYS fills (no network needed): parse the customer's own
  // words straight into the wizard, so the form never "stays as is".
  if (parts.biz_name) fillEmpty('biz_name', parts.biz_name, 'name');
  if (parts.tagline) fillEmpty('biz_tagline', parts.tagline, 'tagline');
  if (parts.audience) fillEmpty('biz_audience', parts.audience, 'audience');
  if (parts.services) fillEmpty('biz_services', parts.services, 'services');
  const m = p.m;
  if (!m) {
    try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e) {}
    say('Wizard filled from your words' + (touched.length ? ': ' + touched.join(', ') : '')
      + '. Type analysis failed (' + (p.offlineWhy || 'network') + ') — industry/style check panni continue pannunga.');
    showToast('⚠️ Wizard filled' + (touched.length ? ': ' + touched.join(', ') : '') + ' · analysis retry pannunga (' + (p.offlineWhy || 'network') + ')');
    window.__PendingBizApply = null;
    return;
  }
  const cat = p.cat || 'generic';
  // Step 1 — industry: ALWAYS set from analysis (Apply click =
  // explicit consent). A stale/manual value (e.g. Coach for a bus
  // business) would otherwise poison generation.
  const typeSel = document.getElementById('biz_type');
  const wantInd = BIZ_BRIEF_INDUSTRY[cat] || '';
  if (typeSel && wantInd) {
    const curInd = (typeSel.options[typeSel.selectedIndex]?.value || '');
    if (curInd !== wantInd) {
      for (let i = 0; i < typeSel.options.length; i++) {
        if (typeSel.options[i].value === wantInd) { typeSel.selectedIndex = i; touched.push('industry'); break; }
      }
    }
  }
  // Step 2 — suggested vibe + palette (editable afterwards).
  const st = BIZ_BRIEF_STYLE[cat];
  if (st) {
    const curVibe = document.querySelector('input[name="taste_vibe"]:checked')?.value || 'auto';
    const vibe = document.querySelector('input[name="taste_vibe"][value="' + st[0] + '"]');
    if (vibe && curVibe === 'auto') { vibe.checked = true; touched.push('style'); }
    const pal = document.querySelector('input[name="color_palette"][value="' + st[1] + '"]');
    if (pal) { pal.checked = true; if (touched.indexOf('style') < 0) touched.push('style'); }
    try { if (typeof updateTasteRead === 'function') updateTasteRead(); } catch (e) {}
  }
  // Step 3 — sections: shop ONLY for real goods sellers (ecommerce winner
  // or an uncertain/generic brief with explicit shop cues). NEVER for
  // booking-style businesses: their briefs always mention fares/prices,
  // and their "products" are trips/routes (catalog, not cart).
  // Never unchecks the customer's own boxes.
  const needShop = (cat === 'ecommerce')
    || (cat === 'generic' && /shop|store|product|sell|buy|retail/i.test(brief));
  const transactional = ['bus', 'ecommerce', 'restaurant', 'hotel', 'salon', 'clinic', 'realestate', 'education', 'travel', 'events', 'rental', 'automotive', 'subscription'].indexOf(cat) >= 0;
  if (needShop) {
    const cb = document.querySelector('input[name="sections[]"][value="shop"]');
    if (cb && !cb.checked) { cb.checked = true; touched.push('shop section'); }
  }
  if (transactional) {
    const cb = document.querySelector('input[name="sections[]"][value="contact"]');
    if (cb && !cb.checked) { cb.checked = true; touched.push('contact section'); }
  }
  try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e) {}
  // ★ Admin analysis: does THIS business need an admin page? If yes, which
  // functions? Pre-fill the Generate-modal admin requirements (only when
  // empty) from the manifest's real entities — customer edits afterwards.
  const mods = (m.admin_modules || []).map(x => x.label || x).filter(Boolean);
  const adminBox = document.getElementById('admin_requirements');
  let adminVerdict = '';
  if (transactional && Array.isArray(m.entities) && m.entities.length) {
    adminVerdict = 'Admin: YES (' + mods.slice(0, 6).join(', ') + ')';
    if (adminBox && !String(adminBox.value || '').trim()) {
      const lines = m.entities
        .filter(e => e && e.id !== 'inquiries')
        .map(e => '- Manage ' + String(e.name || e.id).toLowerCase() +
          ' (' + (e.fields || []).map(f => (f && f.label) || f.name || '').filter(Boolean).join(', ') + ')');
      lines.push('- Collect customer enquiries (name, phone, message)');
      adminBox.value = lines.join('\n');
      touched.push('admin requirements');
    }
  } else {
    adminVerdict = 'Admin: not needed — static site is enough';
  }
  const follow = (m.follow_up || [])[0] || '';
  // ★ Still-missing guidance: tell exactly what the brief lacked so the
  // wizard never looks "untouched" without explanation.
  const stillMissing = [];
  if (!document.getElementById('biz_name')?.value.trim()) stillMissing.push('Business Name (brief la peyar illa — type pannunga)');
  if (!document.getElementById('biz_services')?.value.trim()) stillMissing.push('Services (offerings ah oru line ezhuthunga)');
  if (!document.getElementById('biz_audience')?.value.trim()) stillMissing.push('Audience');
  say('Detected: ' + ((m.business || {}).label || cat)
    + (touched.length ? ' · wizard filled: ' + touched.join(', ') : '')
    + ' · ' + adminVerdict
    + (stillMissing.length ? ' · Still needed: ' + stillMissing.join(' | ') : ' · Wizard full ✓')
    + (follow ? ' · Q: ' + follow : ''));
  showToast('✨ Business understood' + (touched.length ? ' — set: ' + touched.join(', ') : ' — review Steps 1–3') + ' · ' + adminVerdict);
  try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e2) {}
  window.__PendingBizApply = null;
}

/* ★ Brief field parser: pulls name / tagline / audience / services out of the
   customer's own words (Tamil / Tanglish / English). Deterministic,
   offline-safe — only fills EMPTY wizard fields, never overwrites. */
function parseBriefToFields(brief) {
  const t = String(brief || '').trim();
  const out = { biz_name: '', tagline: '', audience: '', services: '' };
  if (!t) return out;
  let m = t.match(/^([^—–\n.]{2,50}?)\s*[—–-]\s+([^.]{4,160})/);
  if (m) {
    out.biz_name = m[1].trim();
    out.tagline = m[2].trim().replace(/\s+/g, ' ');
  } else if ((m = t.match(/(?:my business is|business is|enakku|enakku oru|enakk oru)\s+(?:a|an|oru)?\s*([^.,]{2,60})/i))) {
    const guess = m[1].trim();
    // Reject verb-phrases the Tanglish patterns catch ("... iruku/venum"):
    // a real name is short and never contains those verbs.
    if (!/^(small|a small|my|our|oru)\b/i.test(guess) && guess.length <= 50
        && !/\b(iruku|irukku|venum|irukkirathu)\b/i.test(guess) && guess.split(/\s+/).length <= 4) {
      out.biz_name = guess.charAt(0).toUpperCase() + guess.slice(1);
    }
  }
  if (/^(my business|my company|our business|a small|small|oru business)$/i.test(out.biz_name)) out.biz_name = '';
  if ((m = t.match(/target\s*:\s*([^.]{3,120})/i))) out.audience = m[1].trim();
  else if ((m = t.match(/for\s+([a-z][^.]{3,80})\s*$/i))) out.audience = m[1].trim();
  if ((m = t.match(/(?:we offer|offer|we provide|provide|we sell|sell|services?(?:\s*(?:include|are|:))?|venum|iruku|allow(?:\s+\w+){1,4}\s+to|includ(?:e|ing))\s*[:,]?\s+([^.]{4,220})/i))) {
    out.services = m[1].trim()
      .replace(/^(iruku|irukku|venum|irukkirathu)[,\s]+/i, '')
      .replace(/\s+and\s+/gi, ', ').replace(/,+/g, ',').replace(/\s+/g, ' ');
  }
  if (!out.tagline) {
    const first = t.split(/[.]\s*/)[0] || '';
    if (first.length > 20 && first.length <= 160 && !/^my business is\s*$/i.test(first)) out.tagline = first.trim();
  }
  // Strip markdown the customer pasted (**bold**, __ul__) — wizard fields
  // must hold clean text, not markup.
  ['biz_name', 'tagline', 'audience', 'services'].forEach(k => {
    if (out[k]) out[k] = out[k].replace(/(\*\*|__)/g, '').replace(/\s+/g, ' ').trim();
  });
  return out;
}

/* ★ Prompt Helper: unga vaarthaila irunthu sariyaana business prompt.
   Opens popup → customer talks freely → "Make my prompt" structures it →
   "Use" copies to Understand box → AI understands + fills wizard. */
function openPromptGenerator() {
  const mo = document.getElementById('promptgen-modal');
  if (mo) mo.classList.add('active');
  setTimeout(() => document.getElementById('promptgen-input')?.focus(), 100);
}
function closePromptGenerator() {
  const mo = document.getElementById('promptgen-modal');
  if (mo) mo.classList.remove('active');
}
function promptTypeHint(t) {
  const s = String(t || '').toLowerCase();
  const map = [
    [/bus|seat booking|ticket|trip|transport|travels/i, 'bus service'],
    [/shop|sell|cake|bakery|store|product/i, 'shop'],
    [/food|restaurant|biriyani|meals|hotel.*food|catering/i, 'restaurant'],
    [/hotel|room|stay|lodge/i, 'hotel'],
    [/salon|haircut|facial|bridal|beauty/i, 'salon'],
    [/clinic|doctor|dental|patient|hospital/i, 'clinic'],
    [/school|course|tuition|class|training|academy/i, 'school'],
    [/tuition|tutor/i, 'tuition centre'],
    [/gym|fitness|yoga|workout/i, 'fitness studio'],
    [/real estate|property|plot|house sale/i, 'real estate business'],
    [/event|wedding|photograph/i, 'event service'],
    [/repair|plumb|electric/i, 'service business'],
  ];
  for (const [re, label] of map) { if (re.test(s)) return label; }
  return 'local business';
}
function buildPromptFromWords(raw) {
  const t = String(raw || '').trim().replace(/\s+/g, ' ');
  if (t.length < 5) return '';
  const parts = parseBriefToFields(t);
  const sens = [];
  const typeHint = promptTypeHint(t);
  if (parts.biz_name) sens.push(parts.biz_name + ' — ' + typeHint + '.');
  else {
    const m = t.match(/^(.{10,120}?)(?:\.|$)/);
    sens.push((m ? m[1].trim() : t.slice(0, 120)) + (/[.]$/.test(m ? m[1] : '') ? '' : '.'));
  }
  if (parts.services) {
    const offerTxt = parts.services.replace(/[.]+$/, '');
    // Skip when sentence-1 already says it (Tanglish one-liners) — no repeat.
    if (sens[0].toLowerCase().indexOf(offerTxt.slice(0, 28).toLowerCase()) === -1) sens.push('We offer ' + offerTxt + '.');
  }
  else {
    const m = t.match(/(?:offer|provide|sell|services?|venum|iruku)\s*[:,]?\s+([^.]{4,160})/i);
    if (m) sens.push('We offer ' + m[1].trim().replace(/^(iruku|irukku|venum|irukkirathu)[,\s]+/i, '').replace(/[.]+$/, '') + '.');
  }
  if (parts.audience) sens.push('Target: ' + parts.audience.replace(/[.]+$/, '') + '.');
  let style = '';
  if (/premium|luxur|elegant/i.test(t)) style = 'Premium elegant site';
  else if (/modern/i.test(t)) style = 'Modern clean site';
  else if (/energetic|bold|vibrant/i.test(t)) style = 'Modern energetic site';
  else style = 'Modern trusted site';
  let contact = '';
  if (/whatsapp/i.test(t)) contact = 'with WhatsApp contact';
  else if (/phone|call|mobile/i.test(t)) contact = 'with phone contact';
  else contact = 'with contact form';
  sens.push(style + ' ' + contact + '.');
  return sens.join(' ').replace(/\s+/g, ' ').trim();
}
function makePromptFromWords() {
  const inp = document.getElementById('promptgen-input');
  const out = document.getElementById('promptgen-output');
  const raw = (inp?.value || '').trim();
  if (raw.length < 5) { showToast('⚠️ Konjam detail ah ezhuthunga (oru 1-2 line)'); inp?.focus(); return; }
  const made = buildPromptFromWords(raw);
  if (out) out.value = made;
  showToast(made ? '✨ Prompt ready — check panni Use pannunga' : '⚠️ Konjam kooda ezhuthunga');
}
function useGeneratedPrompt(andUnderstand) {
  const out = document.getElementById('promptgen-output');
  const made = (out?.value || '').trim();
  if (!made) { showToast('⚠️ Muthalla "Make my prompt" click pannunga'); return; }
  const brief = document.getElementById('biz_brief');
  if (brief) brief.value = made;
  try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e) {}
  closePromptGenerator();
  if (andUnderstand) understandMyBusiness();
  else { showToast('📋 Prompt copied — ippa Understand click pannunga'); document.getElementById('biz_brief')?.focus(); }
}

/* ★ Gallery opt-in: landing page-ku gallery section venuma? Tick panna
    enna gallery-nu kekum — answer generation prompt-ku pogum. */
function toggleGallerySubject() {
  const cb = document.getElementById('sec-gallery');
  const row = document.getElementById('gallery-subject-row');
  if (row) row.style.display = (cb && cb.checked) ? '' : 'none';
  if (cb && cb.checked) {
    const inp = document.getElementById('gallery_subject');
    setTimeout(() => { try { inp && inp.focus({ preventScroll: true }); } catch (e) {} }, 50);
  }
  try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e) {}
}
function setGallerySubject(t) {
  const inp = document.getElementById('gallery_subject');
  if (inp) inp.value = t;
  try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e) {}
}
try { window.toggleGallerySubject = toggleGallerySubject; window.setGallerySubject = setGallerySubject; } catch (e) {}

function fillAdminPreset(key) {
  const t = document.getElementById('admin_requirements');
  if (t && ADMIN_PRESETS[key]) {
    t.value = ADMIN_PRESETS[key];
    t.focus();
    saveSessionNow();
    showToast('✓ Preset loaded — edit as needed');
  }
}

// ★ Auto-fill admin requirements from the ACTUAL business (universal
// analyzer). One click before publish: detects category from Step-1 inputs
// and writes one "- Manage X (fields…)" line per needed module.
async function autoFillAdminFromBusiness() {
  const t = document.getElementById('admin_requirements');
  const btn = document.getElementById('btn-admin-autofill');
  if (!t) return;
  let snap = {};
  try { snap = (typeof collectWizardSnapshot === 'function') ? collectWizardSnapshot() : {}; } catch (e) {}
  const orig = btn ? btn.textContent : '';
  try {
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Detecting…'; }
    const res = await fetch(SITE_URL + '/api/business-analyzer.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'analyze', slug: 'preview', input: {
        biz_name: snap.biz_name || snap.business_name || '',
        biz_type: snap.biz_type || '',
        description: snap.biz_tagline || snap.tagline || '',
        services: snap.biz_services || snap.services || '',
        products: snap.biz_products || snap.products || '',
        audience: snap.biz_audience || snap.audience || ''
      }})
    });
    const j = await res.json();
    const m = j && j.manifest;
    if (!m || !Array.isArray(m.entities) || !m.entities.length) throw new Error('empty');
    const lines = m.entities
      .filter(e => e && e.id !== 'inquiries')
      .map(e => '- Manage ' + String(e.name || e.id).toLowerCase() +
        ' (' + (e.fields || []).map(f => (f && f.label) || f.name || '').filter(Boolean).join(', ') + ')');
    lines.push('- Collect customer enquiries (name, phone, message)');
    t.value = lines.join('\n');
    try { saveSessionNow(); } catch (e) {}
    showToast('✨ Detected: ' + (m.business ? m.business.label : 'business') + ' — edit as needed');
    t.focus();
  } catch (e) {
    showToast('⚠️ Auto-detect failed — pick a preset below');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = orig; }
  }
}

/* ★ Solo Quick Site: 3 Solo-style variations (light/bold/dark) for
   consultant/coach/therapist/trades. Fallback flags surface honestly. */
async function confirmSoloGenerate() {
  if (!window.__CUSTOMER__ || !window.__CUSTOMER__.email) {
    showToast('Please sign in / create account first');
    setTimeout(() => { window.location.href = SITE_URL + '/customer-portal.php?view=signup'; }, 900);
    return;
  }
  const data = collectWizardSnapshot();
  if (!data.biz_name || !data.biz_tagline) { showToast('⚠️ Complete Step 1 first'); return; }
  saveSessionNow();
  closeGenerateModal();

  // ★ Creative Solo loader with live percentage (gen-overlay, Solo skinned)
  showGenOverlay(data);
  try {
    document.querySelector('.gen-overlay-title').textContent = '⚡ Solo Quick Sites';
    const tt = document.getElementById('gen-overlay-text');
    if (tt) tt.textContent = 'Solo engine crafting 3 one-page professional sites…';
  } catch (e) {}
  let soloPct = 8;
  const fill = document.getElementById('gen-overlay-fill');
  const pctNum = document.getElementById('gen-pct-num');
  const soloTick = setInterval(() => {
    soloPct = Math.min(soloPct + 6, 90);
    if (fill) fill.style.width = soloPct + '%';
    if (pctNum) pctNum.textContent = String(soloPct);
    try { popPct(); } catch (e) {}
  }, 450);

  try {
    const res = await window.AIFlowCraft.generateSolo(data, {
      model: (window.OpenCodeAI?.getModel?.() || 'space-bunny-free')
    });
    clearInterval(soloTick);
    const list = (res.designs || []).map(c => ({
      name: c.name, description: c.description, badge: c.badge, html: c.html,
      adminHtml: null, phpBackend: null, sqlSchema: null, history: [],
      fallback: !!c.fallback
    }));
    if (!list.length || !list[0].html) throw new Error('Empty Solo designs');
    list.forEach((c, i) => {
      updateGenOverlay('done', `✓ Solo ${i + 1}/3 "${c.name}" ready!`, 92 + i * 2, { variationIndex: i });
    });
    generatedConcepts = list;
    generatedDesigns = list.map(c => ({ ...c }));
    activeDesignIndex = 0;
    currentHtml = generatedDesigns[0]?.html || '';
    currentViewMode = 'site';
    saveSessionNow();
    hideGenOverlay(true);
    // Solo variations also live on variations.php — redirect there.
    try { sessionStorage.setItem('wc_flash', (res.fallback ? '⚡ Solo done — template stood in for a slot ✓' : '⚡ 3 Solo sites ready — pick your favorite!')); } catch (e) {}
    window.location.href = ((typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '') + '/variations.php';
  } catch (err) {
    clearInterval(soloTick);
    console.error(err);
    hideGenOverlay(false);
    showToast('Solo failed: ' + err.message);
  }
}

/* ══════════════════════════════════════════════════
   GENERATE
═════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════
   HTML SANITIZATION — PREVENTS CODE LEAKS & MARKDOWN AFTER FOOTER
═════════════════════════════════════════════════ */
function sanitizeHtmlOutput(raw) {
  if (!raw) return '';
  let s = String(raw).trim();
  // Strip markdown code fences if any
  s = s.replace(/^```(?:html)?\s*/i, '').replace(/```\s*$/i, '');
  // Cut off any leading text before <!DOCTYPE or <html
  const dt = s.search(/<!DOCTYPE|<html/i);
  if (dt > 0) s = s.slice(dt);
  // CRITICAL: Cut off any trailing markdown, commentary or code blocks after the final </html>
  const closeIdx = s.toLowerCase().lastIndexOf('</html>');
  if (closeIdx !== -1) {
    s = s.slice(0, closeIdx + 7);
  }
  // Strip any leaked PHP tags or server code
  s = s.replace(/<\?php[\s\S]*?(?:\?>|$)/gi, '');
  s = s.replace(/\?>/g, '');
  s = s.replace(/TORAGE,\s*0755[\s\S]*?;\s*}/gi, '');
  // Strip any PHP detection warnings, offline banners, or alerts
  s = s.replace(/<[^>]*>[^<]*php\s*not\s*detect[^<]*<\/[^>]*>/gi, '');
  s = s.replace(/alert\s*\(\s*['"][^'"]*php\s*not\s*detect[^'"]*['"]\s*\);?/gi, '');
  return s.trim();
}

/* ══════════════════════════════════════════════════
   GENERATE
═════════════════════════════════════════════════ */
/* ── Overlay helpers ── */
const GEN_TIPS = [
  '⚡ Parallel multi-threading generates all 3 variations simultaneously',
  '🎨 Synthesizing bespoke typography, color palettes, and responsive grids…',
  '⚡ Validating clean, semantic HTML5, CSS3, and interactive components…',
  '🔐 Preparing client-side interactive preview and CMS structures…',
  '🌐 Calibrating viewports for ultra-fast mobile, tablet, and desktop rendering…',
  '🚀 Finalizing assembly — almost ready for instant live preview…',
];
let _tipInterval = null;
let _hudTimerInterval = null;
let _logInterval = null;
let _typeTimer = null;
let _logIdx = 0;
let _lastLogMsg = '';
let _startTime = 0;
let _estTotal = 90; // typical MAX-budget run (s); refined live from progress
let _currentPct = 0;
/* ETA: blend baseline 90s with progress-projected total so the
   countdown stays honest on fast (cached) and slow (MAX tokens) runs. */
function updateEtaLine() {
  const etaEl = document.getElementById('gen-hud-eta');
  const etaLine = document.getElementById('gen-eta-line');
  if (!etaEl && !etaLine) return;
  const elapsed = (Date.now() - _startTime) / 1000;
  const p = Math.max(0, Math.min(99, _currentPct || 0));
  let remain = Math.max(0, _estTotal - elapsed);
  if (p > 8) {
    const projected = elapsed / (p / 100);
    if (isFinite(projected) && projected > 0 && projected < 600) {
      remain = Math.max(0, Math.min(projected - elapsed, 300));
      _estTotal = Math.min(300, Math.max(30, projected * 0.3 + _estTotal * 0.7));
    }
  }
  const r = Math.ceil(remain);
  const txt = r <= 2 ? 'almost done…' : ('~' + r + 's left');
  if (etaEl) etaEl.textContent = txt;
  if (etaLine) etaLine.textContent = '⏳ ' + elapsed.toFixed(0) + 's elapsed · ' + txt + ' · MAX tokens · building full project…';
}
/* Live analysis feed — AI narrates its work while generating (built per-business) */
let GEN_ANALYZE_STEPS = [
  { ic: '🔍', tx: 'Reading business DNA' },
  { ic: '🎯', tx: 'Profiling target audience' },
  { ic: '🎨', tx: 'Mixing brand palette' },
  { ic: '✒️', tx: 'Pairing display + body type' },
  { ic: '🧱', tx: 'Sketching hero layout' },
  { ic: '🗂️', tx: 'Structuring services grid' },
  { ic: '🖼️', tx: 'Placing showcase + photos' },
  { ic: '🛒', tx: 'Wiring shop products + cart' },
  { ic: '⭐', tx: 'Arranging client reviews' },
  { ic: '❓', tx: 'Building FAQ accordion' },
  { ic: '📍', tx: 'Pinning map + contact block' },
  { ic: '✨', tx: 'Weaving scroll-reveal motion' },
  { ic: '📱', tx: 'Collapsing grids for mobile' },
  { ic: '🧪', tx: 'Running taste pre-flight' },
  { ic: '💅', tx: 'Polishing pixels' },
];
/* ★ AI-analyze planner: turns the customer's real brief into AI reasoning steps + insights */
function buildAiAnalysisPlan(data) {
  data = data || {};
  const short = (s, n) => String(s || '').slice(0, n || 34);
  const bName = short(data.biz_name, 26) || 'Your business';
  const bType = short(data.biz_type, 26) || 'brand';
  const aud = short(data.biz_audience, 30) || 'modern clients';
  const secs = Array.isArray(data.sections) ? data.sections : [];
  const pal = String(data.color_palette || 'purple').toUpperCase();
  const style = String(data.design_style || 'modern').toUpperCase();
  const conf = () => 88 + Math.floor(Math.random() * 11);
  const steps = [
    { ic: '🧬', tx: 'Reading "' + bName + '" DNA (' + bType + ')…', pill: 'gpill-biz', insight: { ic: '🧬', tx: 'Business intent <b>' + escapeHtml(bType) + '</b> detected', conf: conf() } },
    { ic: '🎯', tx: 'Profiling "' + aud + '" psychology…', pill: 'gpill-aud', insight: { ic: '🎯', tx: 'Audience <b>' + escapeHtml(aud) + '</b> matched', conf: conf() } },
    { ic: '🎨', tx: 'Mixing ' + pal + ' × ' + style + ' direction…', pill: 'gpill-style', insight: { ic: '🎨', tx: '<b>' + escapeHtml(pal) + '</b> theme + <b>' + escapeHtml(style) + '</b> style locked', conf: conf() } },
    { ic: '🗺️', tx: 'Mapping ' + (secs.length || 6) + ' sections (' + short(secs.slice(0, 3).join(' · ') || 'hero · services · contact', 34) + ')…', wire: 'Analyzing ' + (secs.length || 6) + ' sections…' },
    { ic: '✒️', tx: 'Pairing display + body type for "' + short(data.biz_tagline, 30) + '"…', insight: { ic: '✒️', tx: 'Tagline tone <b>analyzed</b> → typography paired', conf: conf() } },
    { ic: '🧱', tx: 'Sketching hero for "' + bName + '"…', wire: 'Sketching hero layout…' },
    { ic: '🗂️', tx: 'Structuring ' + (data.biz_services ? 'your services' : 'services grid') + '…', wire: 'Structuring content blocks…' },
    { ic: '🖼️', tx: 'Placing showcase + photos…', wire: 'Composing visual hierarchy…' },
    { ic: '🛒', tx: (secs.includes('shop') || (data.biz_products && data.biz_products.trim()) ? 'Wiring ' + short(data.biz_products, 26) + ' + cart…' : 'Checking shop intent… no cart needed…'), wire: 'Wiring interactive sections…' },
    { ic: '⭐', tx: 'Arranging client reviews…', wire: 'Assembling sections… 1/3 live' },
    { ic: '❓', tx: 'Building FAQ + trust blocks…', wire: 'Assembling sections… 2/3 live' },
    { ic: '📍', tx: 'Pinning map + contact block…', wire: 'Assembling sections… 3/3 live' },
    { ic: '✨', tx: 'Weaving scroll-reveal motion…', pill: 'gpill-mode' },
    { ic: '📱', tx: 'Collapsing grids for mobile…', insight: { ic: '📱', tx: 'Responsive breakpoints <b>calculated</b>', conf: conf() } },
    { ic: '🧪', tx: 'Running taste pre-flight audit…', insight: { ic: '🧪', tx: 'Taste audit <b>passed</b> → polishing', conf: 96 } },
    { ic: '💅', tx: 'Polishing pixels for launch…', insight: { ic: '💅', tx: 'Final polish <b>applied</b>', conf: 99 } },
  ];
  return steps;
}
/* AI insight cards — what the AI just deduced, with confidence bar */
function addGenInsight(ic, html, conf) {
  try {
    const box = document.getElementById('gen-insights');
    if (!box) return;
    while (box.children.length >= 2) box.removeChild(box.firstChild);
    const el = document.createElement('div');
    el.className = 'gen-insight';
    const c = Math.max(80, Math.min(99, conf || 92));
    el.innerHTML = '<span class="gi-ic">' + ic + '</span><span class="gi-tx">' + html + '</span><span class="gi-conf"><span class="gi-bar"><i style="width:' + c + '%"></i></span>' + c + '%</span>';
    box.appendChild(el);
    setTimeout(() => { try { el.classList.add('out'); setTimeout(() => { try { el.remove(); } catch (e) {} }, 380); } catch (e) {} }, 3600);
  } catch (e) {}
}
function setPillState(id, mode) {
  try {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('scanning', 'locked');
    if (mode) el.classList.add(mode);
  } catch (e) {}
}
/* Percentage pop — number jumps playfully on change */
let _lastPop = 0;
function popPct() {
  try {
    const w = document.querySelector('.gen-pct-wrap');
    if (!w) return;
    w.classList.remove('pop');
    void w.offsetWidth;
    w.classList.add('pop');
  } catch (e) {}
}
function setAiTicker(html, conf) {
  try {
    const tx = document.getElementById('gen-ai-ticker-tx');
    const cf = document.getElementById('gen-ai-ticker-conf');
    if (!tx) return;
    tx.style.opacity = '0';
    setTimeout(() => {
      tx.innerHTML = html;
      if (cf) cf.textContent = conf || '';
      tx.style.opacity = '1';
    }, 220);
  } catch (e) {}
}
function setNowStep(ic, text) {
  const nic = document.getElementById('gen-now-ic');
  const ntx = document.getElementById('gen-now-text');
  if (nic) {
    nic.textContent = ic;
    try { nic.style.animation = 'none'; void nic.offsetWidth; nic.style.animation = ''; } catch (e) {}
  }
  if (ntx) ntx.textContent = text;
}
function addGenLog(ic, text) {
  const box = document.getElementById('gen-live-log-body');
  if (!box || !text) return;
  const key = ic + '|' + text;
  if (key === _lastLogMsg) return;
  _lastLogMsg = key;
  clearInterval(_typeTimer);
  // Finish the previous running row instantly (full text, green check)
  box.querySelectorAll('.glog-row.run').forEach(el => {
    el.classList.remove('run');
    el.classList.add('done');
    const c = el.querySelector('.glog-caret');
    if (c) c.remove();
    const t = el.querySelector('.glog-tx');
    if (t && el.dataset.full) t.textContent = el.dataset.full;
  });
  while (box.children.length >= 4) box.removeChild(box.firstChild);
  const row = document.createElement('div');
  row.className = 'glog-row run';
  row.dataset.full = text;
  const icEl = document.createElement('span');
  icEl.className = 'glog-ic';
  icEl.textContent = ic;
  const tx = document.createElement('span');
  tx.className = 'glog-tx';
  const caret = document.createElement('span');
  caret.className = 'glog-caret';
  row.appendChild(icEl);
  row.appendChild(tx);
  row.appendChild(caret);
  box.appendChild(row);
  // Typewriter effect for the running line
  let i = 0;
  _typeTimer = setInterval(() => {
    i += 2;
    if (!document.body.contains(tx)) { clearInterval(_typeTimer); return; }
    tx.textContent = text.slice(0, i);
    if (i >= text.length) clearInterval(_typeTimer);
  }, 24);
  setNowStep(ic, text);
}

function showGenOverlay(dataOrName) {
  const overlay = document.getElementById('gen-overlay');
  const fill    = document.getElementById('gen-overlay-fill');
  const text    = document.getElementById('gen-overlay-text');
  const tip     = document.getElementById('gen-overlay-tip');
  const pctNum  = document.getElementById('gen-pct-num');
  const timeEl  = document.getElementById('gen-hud-time');

  let data = (typeof dataOrName === 'object' && dataOrName !== null) ? dataOrName : collectWizardSnapshot();
  const bName = data.biz_name || (typeof dataOrName === 'string' ? dataOrName : 'Your Business');

  // Start 360° 3D AI Robot background animation
  try { if (window.WCRobot3D) WCRobot3D.start(data); } catch (e) {}

  const pBiz = document.getElementById('gpill-biz-val');
  const pAud = document.getElementById('gpill-aud-val');
  const pStyle = document.getElementById('gpill-style-val');
  const pMode = document.getElementById('gpill-mode-val');

  if (pBiz) pBiz.textContent = bName + (data.biz_type ? ' (' + data.biz_type + ')' : '');
  if (pAud) pAud.textContent = (data.biz_audience || 'Modern clients').slice(0, 32);
  // Style pill with a REAL color swatch so the customer SEES their brand color
  const PAL_HEX = { purple: '#6366f1', blue: '#2563eb', green: '#059669', red: '#dc2626', gold: '#d97706', slate: '#334155' };
  const palKey = (data.color_palette || 'purple').toLowerCase();
  if (pStyle) pStyle.innerHTML = (data.design_style || 'Modern').toUpperCase() + ' · <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + (PAL_HEX[palKey] || '#6366f1') + ';box-shadow:0 0 8px ' + (PAL_HEX[palKey] || '#6366f1') + ';margin:0 2px;"></span> ' + palKey.toUpperCase();
  if (pMode) pMode.textContent = (selectedGenMode || 'static').toUpperCase();
  try { var hudMode = document.getElementById('gen-hud-mode'); if (hudMode) hudMode.textContent = '⚡ ' + String(selectedGenMode || 'static').toUpperCase(); } catch (e) {}
  // Simple one-line analysis summary for the customer (creative + plain)
  const briefLine = document.getElementById('gen-brief-line');
  if (briefLine) {
    const secs = Array.isArray(data.sections) ? data.sections.length : 6;
    briefLine.innerHTML = 'For <strong>' + escapeHtml((data.biz_audience || 'modern clients').slice(0, 40)) + '</strong> · ' + secs + ' sections · <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + (PAL_HEX[palKey] || '#6366f1') + ';margin:0 2px;"></span> <strong>' + escapeHtml(palKey.toUpperCase()) + ' theme</strong>';
  }

  // Reset state
  _currentPct = 5;
  _lastPop = 5;
  if (fill) fill.style.width = '5%';
  if (pctNum) pctNum.textContent = '5';
  if (text) text.textContent = 'AI-FlowCraft initializing neural requirements analysis…';
  popPct();
  setAiTicker('🧬 AI reading business DNA…', '5%');

  // Reset wireframe (self-assembling preview)
  paintWire(0);
  const gwUrl = document.getElementById('gw-url');
  if (gwUrl) gwUrl.textContent = (bName || 'your-site').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.ai';
  // Reset step checklist
  document.querySelectorAll('#gen-steps .gen-step').forEach((el, i) => {
    el.classList.toggle('active', i === 0);
    el.classList.remove('done');
  });

  // Start HUD stopwatch + creative clock ring (fills over ~150s)
  _startTime = Date.now();
  _estTotal = 90; // typical MAX-budget 3-variation run (s)
  const clockEl = document.getElementById('gen-clock');
  if (timeEl) timeEl.textContent = '0.0s';
  const etaEl0 = document.getElementById('gen-hud-eta');
  if (etaEl0) etaEl0.textContent = '~90s left';
  const etaLine0 = document.getElementById('gen-eta-line');
  if (etaLine0) etaLine0.textContent = '⏳ Estimated ~90s · MAX tokens · building full project…';
  if (clockEl) clockEl.style.setProperty('--p', '0');
  clearInterval(_hudTimerInterval);
  _hudTimerInterval = setInterval(() => {
    const elapsed = (Date.now() - _startTime) / 1000;
    if (timeEl) timeEl.textContent = elapsed.toFixed(1) + 's';
    if (clockEl) clockEl.style.setProperty('--p', String(Math.min(100, (elapsed / 150) * 100)));
    try { updateEtaLine(); } catch (e) {}
  }, 100);

  // Start live analysis feed — contextual AI plan built from the real brief
  // (scripted steps + real engine messages via updateGenOverlay)
  _logIdx = 0;
  _lastLogMsg = '';
  clearInterval(_typeTimer);
  const logBox = document.getElementById('gen-live-log-body');
  if (logBox) logBox.innerHTML = '';
  try { document.getElementById('gen-insights').innerHTML = ''; } catch (e) {}
  ['gpill-biz', 'gpill-aud', 'gpill-style', 'gpill-mode'].forEach(id => setPillState(id, null));
  try { GEN_ANALYZE_STEPS = buildAiAnalysisPlan(data); } catch (e) {}
  const feedStep = (s) => {
    if (!s) return;
    addGenLog(s.ic, s.tx);
    if (s.pill) {
      setPillState(s.pill, 'scanning');
      setTimeout(() => setPillState(s.pill, 'locked'), 2600);
    }
    if (s.wire) { const cap = document.getElementById('gen-wire-cap'); if (cap) cap.textContent = '◉ ' + s.wire; }
    if (s.insight) {
      addGenInsight(s.insight.ic, s.insight.tx, s.insight.conf);
      // Mirror the same deduction into the always-visible ticker (plain text)
      const plain = String(s.insight.tx).replace(/<[^>]*>/g, '');
      setAiTicker(s.ic + ' AI detected: ' + plain, (s.insight.conf || 92) + '%');
    } else {
      setAiTicker('🧠 ' + s.tx, '…');
    }
  };
  feedStep(GEN_ANALYZE_STEPS[0]);
  clearInterval(_logInterval);
  _logInterval = setInterval(() => {
    _logIdx = (_logIdx + 1) % GEN_ANALYZE_STEPS.length;
    feedStep(GEN_ANALYZE_STEPS[_logIdx]);
  }, 3400);

  // Spawn starfield particles
  const pc = document.getElementById('gen-particles');
  if (pc) {
    pc.innerHTML = '';
    const colors = ['#6366f1','#a855f7','#10b981','#38bdf8','#f59e0b','#ec4899'];
    for (let i = 0; i < 35; i++) {
      const p = document.createElement('div');
      p.className = 'gen-particle';
      const size = Math.random() * 4 + 2;
      const dur  = Math.random() * 6 + 4;
      const del  = Math.random() * 5;
      const left = Math.random() * 100;
      p.style.cssText = `width:${size}px;height:${size}px;left:${left}%;background:${colors[Math.floor(Math.random()*colors.length)]};animation-duration:${dur}s;animation-delay:${del}s;opacity:0.6;`;
      pc.appendChild(p);
    }
  }

  // Rotate tips
  let tipIdx = 0;
  if (tip) {
    tip.textContent = GEN_TIPS[0];
    clearInterval(_tipInterval);
    _tipInterval = setInterval(() => {
      tipIdx = (tipIdx + 1) % GEN_TIPS.length;
      tip.style.opacity = '0';
      setTimeout(() => { tip.textContent = GEN_TIPS[tipIdx]; tip.style.opacity = '1'; }, 250);
    }, 3200);
  }

  // Stone-sculpt loop removed — the home-build scene is pure CSS now.

  // Honest-progress reset: 7-stage rail + creep timer + error cleared.
  _wcRealDone = false;
  try { wcPaintStages(5); } catch (e) {}
  try { var eb = document.getElementById('wc-gen-err'); if (eb) eb.classList.remove('show'); } catch (e2) {}
  try { var fl = document.getElementById('gen-overlay-fill'); if (fl) fl.style.background = ''; } catch (e3) {}
  try { wcStartHonestCreep(); } catch (e4) {}
  overlay.classList.add('active');
  document.body.style.overflow = 'hidden';
}

/* Paint the self-assembling wireframe: n = variations live (0-3) */
function paintWire(n) {
  const wire = document.getElementById('gen-wire');
  if (wire) {
    const nav = wire.querySelector('.gw-nav');
    const hero = wire.querySelector('.gw-hero');
    const cards = wire.querySelector('.gw-cards');
    const foot = wire.querySelector('.gw-foot');
    if (nav) nav.classList.toggle('lit', n >= 1);
    if (hero) hero.classList.toggle('lit', n >= 1);
    if (cards) cards.classList.toggle('lit', n >= 2);
    if (foot) foot.classList.toggle('lit', n >= 3);
  }
  const cap = document.getElementById('gen-wire-cap');
  if (cap) cap.textContent = n >= 3 ? '✓ Website assembled!' : (n > 0 ? `Assembling sections… ${Math.min(n, 3)}/3 live` : 'Assembling sections…');
}

/* ── Honest 6-stage rail: maps real pct → 6 required stages ── */
var WC_STAGES = [
  'Analysing business requirements',
  'Planning website structure',
  'Generating design and pages',
  'Connecting backend functionality',
  'Checking forms, data flow and permissions',
  'Preparing preview'
];
function wcPaintStages(pct) {
  var idx = pct < 18 ? 0 : pct < 36 ? 1 : pct < 56 ? 2 : pct < 76 ? 3 : pct < 94 ? 4 : 5;
  // Illustrative creep never claims completion: cap at stage 5 until success.
  if (pct < 100 && idx > 4 && !_wcRealDone) idx = 4;
  document.querySelectorAll('#wc-stages .wc-stage').forEach(function (el) {
    var s = parseInt(el.getAttribute('data-wc'), 10);
    el.classList.toggle('done', s < idx);
    el.classList.toggle('active', s === idx);
  });
  var nowT = document.getElementById('gen-now-text');
  var nowI = document.getElementById('gen-now-ic');
  if (nowT) nowT.textContent = WC_STAGES[idx];
  if (nowI) nowI.textContent = ['📋', '🗺️', '🎨', '🔌', '🛡️', '🚀'][idx] || '✨';
}
var _wcRealDone = false;
var _wcCreepTimer = null;
function wcStartHonestCreep() {
  // Illustrative only: creeps toward 85% max, never 100% before real success.
  clearInterval(_wcCreepTimer);
  _wcCreepTimer = setInterval(function () {
    try {
      if (_wcRealDone) { clearInterval(_wcCreepTimer); return; }
      if (typeof _currentPct !== 'number') return;
      if (_currentPct < 85) {
        _currentPct = Math.min(85, _currentPct + 1);
        var fill = document.getElementById('gen-overlay-fill');
        var pctNum = document.getElementById('gen-pct-num');
        if (fill) fill.style.width = _currentPct + '%';
        if (pctNum) pctNum.textContent = String(_currentPct);
        wcPaintStages(_currentPct);
      }
    } catch (e) {}
  }, 2500);
}

function updateGenOverlay(stage, message, pct, meta) {
  const fill = document.getElementById('gen-overlay-fill');
  const text = document.getElementById('gen-overlay-text');
  const pctNum = document.getElementById('gen-pct-num');
  const termLine = document.getElementById('gen-terminal-line');

  // Synchronize 360° 3D AI Robot animation stage
  try { if (window.WCRobot3D) WCRobot3D.setStage(stage, message, pct, meta); } catch (e) {}

  if (text) text.textContent = message;
  // AI-analyze voice for the telemetry line: prefix as AI reasoning
  if (termLine) termLine.textContent = 'AI ▸ analyzing: ' + message;
  // Mirror real engine progress into the live analysis feed too
  if (message) addGenLog('⚡', String(message).slice(0, 90));

  if (typeof pct === 'number') {
    // Honest cap: real events drive progress; illustrative creep capped at 85%.
    // 100% is only set on actual success in hideGenOverlay(true).
    if (!_wcRealDone && pct >= 100) pct = 90;
    _currentPct = Math.max(_currentPct, pct);
    if (fill) fill.style.width = _currentPct + '%';
    if (pctNum) pctNum.textContent = String(_currentPct);
    if (_currentPct >= _lastPop + 5) { _lastPop = _currentPct; popPct(); }
    try { wcPaintStages(_currentPct); } catch (e) {}
    // Build step checklist: 0 analyze · 1-3 variations · 4 finalize
    const stageIdx = _currentPct < 15 ? 0 : _currentPct < 40 ? 1 : _currentPct < 62 ? 2 : _currentPct < 84 ? 3 : 4;
    document.querySelectorAll('#gen-steps .gen-step').forEach(el => {
      const s = parseInt(el.dataset.s, 10);
      el.classList.toggle('done', s < stageIdx);
      el.classList.toggle('active', s === stageIdx);
    });
    // AI-analyze feel: lock brief pills as real progress crosses thresholds
    try {
      if (_currentPct >= 12) setPillState('gpill-biz', 'locked');
      if (_currentPct >= 22) setPillState('gpill-aud', 'locked');
      if (_currentPct >= 32) setPillState('gpill-style', 'locked');
      if (_currentPct >= 80) setPillState('gpill-mode', 'locked');
    } catch (e) {}
    try { updateEtaLine(); } catch (e) {}
  }

  // Light up wireframe sections as variations go live
  if (meta && (typeof meta.completedCount === 'number' || typeof meta.variationIndex === 'number')) {
    let n = meta.completedCount || 0;
    if (!n && typeof meta.variationIndex === 'number') n = meta.variationIndex + 1;
    n = Math.min(n, 3);
    paintWire(n);
    // Celebrate each live variation as an AI insight
    try { if (n > 0) { addGenInsight('✅', 'Variation <b>' + n + '/3</b> synthesized & verified', 94 + n); setAiTicker('✅ Variation <b>' + n + '/3</b> synthesized & verified', (94 + n) + '%'); } } catch (e) {}
  } else if (message) {
    // Real engine message → keep ticker in AI-analyze voice (plain text)
    try { setAiTicker('🧠 ' + escapeHtml(String(message).slice(0, 80)), _currentPct + '%'); } catch (e) {}
  }
}

function hideGenOverlay(success, errMsg) {
  clearInterval(_tipInterval);
  clearInterval(_hudTimerInterval);
  clearInterval(_logInterval);
  clearInterval(_typeTimer);
  try { clearInterval(_wcCreepTimer); } catch (e) {}
  window.__generating = false;
  const fill = document.getElementById('gen-overlay-fill');
  const pctNum = document.getElementById('gen-pct-num');
  const overlay = document.getElementById('gen-overlay');
  const text = document.getElementById('gen-overlay-text');
  const errBox = document.getElementById('wc-gen-err');

  if (success) {
    _wcRealDone = true;
    try { wcPaintStages(100); } catch (e) {}
    document.querySelectorAll('#wc-stages .wc-stage').forEach(function (el) { el.classList.add('done'); el.classList.remove('active'); });
    if (errBox) errBox.classList.remove('show');
    paintWire(3);
    try { ['gpill-biz', 'gpill-aud', 'gpill-style', 'gpill-mode'].forEach(id => setPillState(id, 'locked')); } catch (e) {}
    try { addGenInsight('🎉', 'Analysis complete — <b>3 bespoke sites</b> ready', 99); } catch (e) {}
    try { setAiTicker('🎉 Analysis complete — <b>3 bespoke sites</b> ready', '100%'); } catch (e) {}
    document.querySelectorAll('#gen-live-log-body .glog-row.run').forEach(el => {
      el.classList.remove('run');
      el.classList.add('done');
      const c = el.querySelector('.glog-caret');
      if (c) c.remove();
      const t = el.querySelector('.glog-tx');
      if (t && el.dataset.full) t.textContent = el.dataset.full;
    });
    addGenLog('🎉', '3 variations ready — pick your favorite…');
    document.querySelectorAll('#gen-steps .gen-step').forEach(el => {
      el.classList.add('done');
      el.classList.remove('active');
    });
    if (fill) fill.style.width = '100%';
    if (pctNum) pctNum.textContent = '100';
    popPct();
    if (text) text.textContent = '✓ 3 variations successfully synthesized!';
    try {
      const doneEta = document.getElementById('gen-hud-eta');
      if (doneEta) doneEta.textContent = 'done ✓';
      const doneLine = document.getElementById('gen-eta-line');
      if (doneLine) {
        const el = ((Date.now() - _startTime) / 1000).toFixed(0);
        doneLine.textContent = '✓ Done in ' + el + 's · MAX tokens · 3 full projects ready…';
      }
    } catch (e) {}
    try { if (window.WCRobot3D) WCRobot3D.hide(true); } catch (e) {}
    setTimeout(() => { overlay.classList.remove('active'); document.body.style.overflow = ''; }, 650);
  } else {
    try { if (window.WCRobot3D) WCRobot3D.hide(false); } catch (e) {}
    if (fill) { fill.style.background = 'linear-gradient(90deg,#ef4444,#dc2626)'; fill.style.width = '100%'; }
    if (text) text.textContent = '⚠️ Generation failed — nothing was lost. Retry when ready.';
    if (errBox) {
      errBox.innerHTML = '⚠️ <span></span> <button type="button" onclick="hideGenOverlayRetry()">Retry generation</button> <button type="button" onclick="hideGenOverlayDismiss()" style="background:transparent;color:var(--wc-overlay-text);border-color:var(--wc-border)">Close</button>';
      errBox.querySelector('span').textContent = (errMsg || 'The AI service returned an error.') + ' Your inputs are preserved.';
      errBox.classList.add('show');
    }
    // Keep modal visible with theme-consistent error state (existing retry = button above + wizard button).
    document.body.style.overflow = '';
    try { clearInterval(_wcCreepTimer); } catch (e) {}
    return;
  }
}
function hideGenOverlayRetry() {
  try { if (window.WCRobot3D) WCRobot3D.stop(); } catch (e) {}
  var b = document.getElementById('wc-gen-err');
  if (b) b.classList.remove('show');
  var o = document.getElementById('gen-overlay');
  if (o) o.classList.remove('active');
  document.body.style.overflow = '';
  try { generateWithMode(selectedGenMode || 'static'); } catch (e) {}
}
function hideGenOverlayDismiss() {
  try { if (window.WCRobot3D) WCRobot3D.stop(); } catch (e) {}
  var b = document.getElementById('wc-gen-err');
  if (b) b.classList.remove('show');
  var o = document.getElementById('gen-overlay');
  if (o) o.classList.remove('active');
  document.body.style.overflow = '';
}
try { window.hideGenOverlayRetry = hideGenOverlayRetry; window.hideGenOverlayDismiss = hideGenOverlayDismiss; } catch (e) {}

// Raw browser/provider abort text is not useful to the user. Map it to one
// short, actionable line (long generations need time, not a template fallback).
function friendlyAiErr(raw) {
  let s = String(raw == null ? '' : raw).replace(/\s+/g, ' ').trim();
  if (/signal is aborted without reason|AbortError|signal is aborted/i.test(s)) {
    return 'AI request was cancelled (page reload or network drop) — retry.';
  }
  if (/timed out after/i.test(s)) {
    // Timers are removed everywhere; if this still appears, keep the detail
    // (endpoint + seconds) so the cause is visible instead of hidden.
    return 'AI timed out — ' + s.slice(0, 180) + ' — retry';
  }
  return s.slice(0, 220);
}

async function generateWithMode(mode) {
  // Prevent accidental duplicate requests; preserve cancel via overlay close.
  if (window.__generating) { showToast('⏳ Generation already running…'); return; }
  window.__generating = true;
  if (!window.__CUSTOMER__ || !window.__CUSTOMER__.email) {
    window.__generating = false;
    showToast('Please sign in / create account first');
    setTimeout(() => { window.location.href = SITE_URL + '/customer-portal.php?view=signup'; }, 900);
    return;
  }
  if (!window.AIFlowCraft) { window.__generating = false; showToast('⚠️ Generator not loaded'); return; }
  selectedGenMode = mode;
  const data = collectWizardSnapshot();
  if (!data.biz_name || !data.biz_tagline) { showToast('⚠️ Complete Step 1 first'); return; }
  // Universal builder: attach auto business spec (source of truth for generation).
  // Approved blueprint (canonical) wins; legacy __bizSpec otherwise.
  try {
    const approved = (window.__blueprint && window.__blueprint.approved && window.__blueprint.spec) ? window.__blueprint.spec : null;
    if (approved) {
      data.blueprint_spec = approved;
      data.gen_mode = ((approved.compat || {}).gen_mode) || mode;
      selectedGenMode = data.gen_mode;
      bpNormalize(approved);
    } else if (typeof __bizSpec !== 'undefined' && __bizSpec) {
      data.biz_spec = __bizSpec;
      data.gen_mode = __bizSpec.gen_mode || mode;
      selectedGenMode = data.gen_mode;
    } else if (window.__bizSpec) {
      data.biz_spec = window.__bizSpec;
      data.gen_mode = window.__bizSpec.gen_mode || mode;
      selectedGenMode = data.gen_mode;
    }
  } catch (e) {}

  // ★ Shop auto-detect: explicit shop section, shop-type industry, or product
  // list on a NON-booking business. Booking-style industries (bus, hotel,
  // salon, …) keep products as catalog (trips/rooms/menu) — never a cart.
  try {
    const bType = data.biz_type || '';
    const bookingType = /bus|transport|coach|fleet|travel|tour|hotel|restaurant|salon|clinic|school|rental|booking|appointment/i.test(bType);
    const explicitShop = (data.sections || []).includes('shop');
    const shopType = /shop|store|product|retail|e-?commerce|boutique|mart|trading|enterprise|fashion|jewelry|grocery|bakery|furniture|electronics|pharma/i.test(bType);
    const isShop = explicitShop || shopType || ((data.biz_products && data.biz_products.trim()) && !bookingType);
    // ★ Booking-type fallback: bus/transport gets the BUS admin preset
    // (buses + routes + trips + bookings + payments) so frontend booking
    // connects to backend admin. Never the shop preset for bus.
    try {
      const isBusType = /bus|transport|coach|fleet/i.test(bType);
      if (isBusType && mode !== 'static') {
        const arB = document.getElementById('admin_requirements');
        if (arB && !arB.value.trim() && typeof ADMIN_PRESETS !== 'undefined' && ADMIN_PRESETS.bus) {
          arB.value = ADMIN_PRESETS.bus;
          data.admin_requirements = ADMIN_PRESETS.bus;
          showToast('🚌 Bus booking detected — admin preset applied (trips + bookings + QR tickets)');
        }
      }
    } catch (e) {}
    if (isShop && mode !== 'static') {
      const ar = document.getElementById('admin_requirements');
      if (ar && !ar.value.trim() && typeof ADMIN_PRESETS !== 'undefined' && ADMIN_PRESETS.shop) {
        ar.value = ADMIN_PRESETS.shop;
        data.admin_requirements = ADMIN_PRESETS.shop;
        showToast('🛒 Shop detected — admin preset applied (products + orders)');
      }
    }
    if (isShop) showToast('🛒 Shop mode ON — products + working cart will be generated');
  } catch (e) {}

  // Quick beat before generation starts (kept short for speed)
  try { if (window.Loader3D) Loader3D.show('Connecting to secure database…', 'Verifying session · syncing workspace', 'db'); } catch (e) {}
  try { saveSessionNow(); } catch (e) {}
  await new Promise(r => setTimeout(r, 400));
  try { if (window.Loader3D) Loader3D.hide(); } catch (e) {}

  showGenOverlay(data);
  document.getElementById('wiz-generate-btn').disabled = true;
  document.getElementById('wiz-next').disabled = true;
  document.getElementById('wiz-prev').disabled = true;

  try {
    // ★ Routed generation: complexity decides flow + provider order.
    // Small/medium → 3 via Gemini → Space Bunny → templates.
    // Large → 1 via Space Bunny → Gemini → templates.
    // Sequential only (never simultaneous); same validated spec throughout.
    const prog = (p) => {
      const pct = p.pct || (p.stage === 'connecting' ? 15 : (p.stage === 'done' ? 100 : 50));
      updateGenOverlay(p.stage, p.message, pct, p);
    };
    let concepts = null;
    let engineNote = '';
    let aiErr = '';
    let ocTokens = 0;
    let routeMeta = null;
    const tokStr = () => ocTokens > 0 ? ` · ~${(ocTokens / 1000).toFixed(1)}k tokens` : '';
    try {
      if (window.GenerationRouter?.generateRouted) {
        const approved = (window.__blueprint && window.__blueprint.approved && window.__blueprint.spec) ? window.__blueprint.spec : null;
        const routed = await window.GenerationRouter.generateRouted(data, mode, Object.assign({ onProgress: prog }, approved ? { spec: approved } : {}));
        ocTokens = routed.designs.reduce((a, d) => a, 0) || 0;
        concepts = routed.designs;
        routeMeta = { route: routed.route, history: routed.history, flow: (routed.meta || {}).flow || '' };
        try {
          window.__bizSpec = routed.spec || window.__bizSpec || null;
          __bizSpec = window.__bizSpec;
          if (routed.spec && routed.spec.compat && routed.spec.compat.gen_mode) {
            selectedGenMode = routed.spec.compat.gen_mode;
            data.gen_mode = selectedGenMode;
          }
        } catch (e) {}
        const provs = (routed.history || []).map(h => h.event).join(',');
        if ((routed.route || {}).variations === 1) {
          engineNote = provs.includes('bunny-ok') ? 'Space Bunny flagship (1/1)' : (provs.includes('gemini-ok') ? 'Gemini flagship (1/1)' : 'Template flagship (1/1)');
        } else {
          engineNote = 'Routed 3 [' + ((routed.history || []).filter(h => /-ok$/.test(h.event)).map(h => h.event.replace('-ok', '')).join('+') || 'ai') + ']';
        }
      } else if (window.OpenCodeAI?.generateConcepts) {
        const oc = await window.OpenCodeAI.generateConcepts(data, mode, { onProgress: prog });
        ocTokens = oc.tokens || 0;
        if (oc.designs.length === 3) {
          concepts = oc.designs;
          const hasOpenCode = concepts.some(c => ((c.meta && c.meta.engine) || '').includes('opencode'));
          engineNote = hasOpenCode ? 'Gemini ×2 + Space Bunny' : '3× Gemini AI (Fast)';
        } else if (oc.designs.length > 0) {
          // PARTIAL: 1-2 AI ready (3rd timed out/failed) → keep the AI
          // ones, fill ONLY missing variations with server templates.
          const tpl = await window.AIFlowCraft.generateConcepts(data, mode, {});
          const byId = {};
          oc.designs.forEach(d => { byId[d.id] = d; });
          concepts = tpl.map(t => byId[t.id] || t);
          if (!concepts.length) concepts = null;
          else {
            engineNote = 'AI (' + oc.designs.length + '/3) + template (' + (3 - oc.designs.length) + '/3)';
            showToast('⚠️ Partial AI — ' + oc.designs.length + '/3 AI ready, templates filled ' + (3 - oc.designs.length) + ' slot(s)');
            if (oc.errors && oc.errors.length) aiErr = friendlyAiErr(oc.errors[0]);
          }
        }
      }
    } catch (e) {
      aiErr = friendlyAiErr(e?.message || e);
      console.warn('[generate] Routed lane failed, using templates:', e?.message);
    }
    if (!concepts) {
      concepts = await window.AIFlowCraft.generateConcepts(data, mode, { onProgress: prog });
      engineNote = 'templates';
    }
    try { window.__routeMeta = routeMeta; } catch (e) {}

    // Sanitize each generated design to strip any PHP leaks or markdown after </html>
    // Admin preview ONLY for admin/database modes — static gets NO admin panel at all.
    const wantAdmin = mode !== 'static';
    generatedConcepts = concepts.map(c => ({
      ...c,
      html: sanitizeHtmlOutput(c.html),
      adminHtml: null
    }));

    generatedDesigns = generatedConcepts.map(c => ({
      name: c.name, description: c.description, badge: c.badge, html: c.html,
      engine: (c.meta && c.meta.engine) || 'template',
      model: (c.meta && c.meta.model) || '',
      adminHtml: null,
      phpBackend: c.phpBackend || null,
      sqlSchema: c.sqlSchema || null, history: []
    }));

    activeDesignIndex = 0;
    currentHtml = generatedDesigns[0]?.html || '';

    // Structural compliance vs the approved spec (additive, deterministic):
    // missing required sections injected, bare forms wired to data-wc-form.
    // Existing markup never rewritten; every fix listed honestly.
    let complyFixes = [];
    try {
      const approved0 = (window.__blueprint && window.__blueprint.approved && window.__blueprint.spec) ? window.__blueprint.spec : null;
      const vspec0 = approved0 || ((window.__bizCanonical && window.__bizCanonical.schema === 'biz-spec/1') ? window.__bizCanonical : null);
      if (vspec0) {
        updateGenOverlay('verifying', '✓ Ensuring spec compliance (sections + forms)…', 95, {});
        const cr = await fetch((typeof SITE_URL !== 'undefined' && SITE_URL ? SITE_URL : '') + '/api/biz-intelligence.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'comply', spec: vspec0,
            htmls: generatedDesigns.map(d => (d.html || '').slice(0, 200000)) })
        });
        const cj = await cr.json().catch(() => null);
        if (cj && cj.success && Array.isArray(cj.htmls) && cj.htmls.length === generatedDesigns.length) {
          generatedDesigns.forEach((d, i) => { if (cj.htmls[i]) d.html = cj.htmls[i]; });
          generatedConcepts.forEach((c, i) => { if (cj.htmls[i]) c.html = cj.htmls[i]; });
          currentHtml = generatedDesigns[0]?.html || currentHtml;
          complyFixes = cj.fixes || [];
          if (complyFixes.length) showToast('✓ Auto-fixed ' + complyFixes.length + ' compliance gap(s): ' + complyFixes.slice(0, 2).join('; '));
        }
      }
    } catch (e) { console.warn('[comply]', e?.message); }

    // Post-generation verification vs the approved spec (bounded, read-only).
    try {
      const approved = (window.__blueprint && window.__blueprint.approved && window.__blueprint.spec) ? window.__blueprint.spec : null;
      const vspec = approved || ((window.__bizCanonical && window.__bizCanonical.schema === 'biz-spec/1') ? window.__bizCanonical : null);
      if (vspec) {
        updateGenOverlay('verifying', '✓ Verifying build against approved blueprint…', 97, {});
        const vr = await fetch((typeof SITE_URL !== 'undefined' && SITE_URL ? SITE_URL : '') + '/api/biz-intelligence.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'verify', spec: vspec,
            htmls: generatedDesigns.map(d => (d.html || '').slice(0, 200000)) })
        });
        const vj = await vr.json().catch(() => null);
        if (vj && vj.success && vj.report) {
          window.__verifyReport = vj.report;
          const fs = (vj.report.summary.failed || 0);
          if (fs > 0) showToast('⚠️ Verification: ' + fs + ' check(s) need attention — see Variations page');
        }
      }
    } catch (e) { console.warn('[verify]', e?.message); }

    saveSessionNow();
    hideGenOverlay(true);
    // Variations live on their own URL now (variations.php) — redirect there.
    // Large projects carry 1 flagship; small/medium carry 3.
    const nVars = (concepts || []).length;
    const fixNote = (typeof complyFixes !== 'undefined' && complyFixes.length) ? ' · auto-fixed ' + complyFixes.length + ' gap(s)' : '';
    try { sessionStorage.setItem('wc_flash', (engineNote === 'templates' && aiErr
      ? 'Templates (AI failed: ' + aiErr + ')' + tokStr()
      : nVars === 1
        ? 'Flagship design ready! [' + engineNote + ']' + tokStr() + fixNote
        : nVars + ' style variations ready! [' + engineNote + ']' + tokStr() + fixNote)); } catch (e) {}
    window.location.href = ((typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '') + '/variations.php';
  } catch (err) {
    console.error(err);
    hideGenOverlay(false, err && err.message ? err.message : '');
    showToast('Failed: ' + err.message);
  } finally {
    window.__generating = false;
    document.getElementById('wiz-generate-btn').disabled = false;
    document.getElementById('wiz-next').disabled = false;
    document.getElementById('wiz-prev').disabled = (currentWizStep === 1);
    updateGenerateButton();
  }
}


/* ══════════════════════════════════════════════════
   DISPLAY 3 DESIGNS — DEPRECATED shim (direct flow)
   Concepts picker lives ONLY on variations.php now.
   After generate we go straight there — no in-builder
   intermediate screen. Kept as a redirect for old callers.
   ═════════════════════════════════════════════════ */
function display3Designs(concepts, bizName) {
  try { saveSessionNow(); } catch (e) {}
  window.location.href = ((typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '') + '/variations.php';
}

/* ══════════════════════════════════════════════════
   ADMIN PANEL STABILIZER & INTERACTIVE PREVIEW
═════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════
   PRE-PUBLISH DEV ENVIRONMENT (api/preview-env.php)
   BEFORE PUBLISH = fully functional preview: real PHP admin + real JSON.
   There is no mock admin anywhere: failures show an honest error + retry,
   never fake records or disabled buttons.
   ══════════════════════════════════════════════════ */
function getPreviewSession() {
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    const sess = raw ? JSON.parse(raw) : null;
    return (sess && sess.preview) || null;
  } catch (e) { return null; }
}
function setPreviewSession(patch) {
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    const sess = raw ? JSON.parse(raw) : {};
    sess.preview = Object.assign({}, sess.preview || {}, patch || {});
    localStorage.setItem(SESSION_KEY, JSON.stringify(sess));
  } catch (e) {}
}
function getSessionCreds() {
  try {
    const s = JSON.parse(localStorage.getItem(SESSION_KEY) || '{}');
    return { user: s.adminUsername || '', pass: s.adminPassword || '' };
  } catch (e) { return { user: '', pass: '' }; }
}
function previewHash(html) {
  let h = 5381;
  const s = String(html || '');
  for (let i = 0; i < s.length; i++) h = ((h << 5) + h + s.charCodeAt(i)) >>> 0;
  return h.toString(16);
}
let __previewBusy = false;
async function ensurePreviewEnv(opts) {
  opts = opts || {};
  const quiet = !!opts.quiet;
  const c = (typeof generatedDesigns !== 'undefined') ? generatedDesigns[activeDesignIndex] : null;
  const html = (c && c.html) || (typeof currentHtml !== 'undefined' ? currentHtml : '') || '';
  if (!html || html.length < 100) { if (!quiet) showToast('Generate a design first'); return null; }
  let snap = {};
  try { snap = (typeof collectWizardSnapshot === 'function') ? collectWizardSnapshot() : {}; } catch (e) {}
  const canon = (typeof window.__bizCanonical !== 'undefined' && window.__bizCanonical) || {};
  const hash = previewHash(html);
  const prev = getPreviewSession();
  if (!opts.force && prev && prev.id && prev.htmlHash === hash && prev.adminUrl) return prev;
  if (__previewBusy) return (prev && prev.id) ? prev : null;
  __previewBusy = true;
  try {
    if (!quiet) showToast('⚙️ Building your live preview backend…');
    const sess = getPreviewSession() || {};
    const creds = getSessionCreds();
    const bizName = (document.getElementById('biz_name') && document.getElementById('biz_name').value) || snap.biz_name || 'My Website';
    const body = {
      action: 'provision',
      draftToken: sess.id || undefined,
      html: html,
      bizName: bizName,
      slug: String(bizName).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'site',
      entities: Array.isArray(canon.entities) && canon.entities.length ? canon.entities : undefined,
      bizSpec: (typeof window.__bizSpec !== 'undefined' ? window.__bizSpec : null),
      adminUser: ((document.getElementById('admin_custom_user') && document.getElementById('admin_custom_user').value) || '').trim().toLowerCase() || creds.user || '',
      adminPass: (document.getElementById('admin_custom_pass') && document.getElementById('admin_custom_pass').value) || creds.pass || '',
      contactEmail: snap.biz_email || '',
      genMode: (typeof selectedGenMode !== 'undefined' ? selectedGenMode : 'admin')
    };
    const res = await fetch(SITE_URL + '/api/preview-env.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const j = await res.json();
    if (!(j && j.success && j.env)) throw new Error((j && j.error) || 'provision failed');
    const env = Object.assign({}, j.env, { htmlHash: hash });
    setPreviewSession(env);
    try { if (typeof saveSessionNow === 'function') saveSessionNow(); } catch (e) {}
    if (j.env.adminPassOnce) {
      showToast('🔑 Preview admin password (shown once — copied): ' + j.env.adminPassOnce, 9000);
      try { if (navigator.clipboard) navigator.clipboard.writeText(j.env.adminPassOnce); } catch (e) {}
    } else if (!quiet) {
      showToast('✅ Live preview backend ready — forms + admin fully functional');
    }
    return env;
  } catch (e) {
    console.warn('[preview-env]', e);
    if (!quiet) showToast('⚠️ Preview backend unavailable — ' + (e.message || 'network error') + '. Retry.', 6000);
    return null;
  } finally { __previewBusy = false; }
}
function paintPreviewBar(env, isPreview) {
  const adminBar   = document.getElementById('admin-url-bar');
  const urlDisplay = document.getElementById('admin-url-display');
  const openBtn    = document.getElementById('admin-url-open-btn');
  const addFnBtn   = document.getElementById('btn-add-function');
  const rebuildBtn = document.getElementById('btn-preview-rebuild');
  const liveBtn    = document.getElementById('btn-preview-site');
  const openBtn2   = document.getElementById('btn-preview-open');
  const listBtn    = document.getElementById('btn-preview-checklist');
  const passBtn    = document.getElementById('btn-preview-pass');
  const credHint   = document.getElementById('admin-creds-hint');
  const readyBadge = document.getElementById('preview-readiness');
  if (adminBar) adminBar.style.display = 'flex';
  if (urlDisplay) urlDisplay.value = env.adminUrl;
  if (openBtn) { openBtn.href = env.adminUrl; openBtn.style.opacity = ''; openBtn.style.pointerEvents = ''; }
  if (addFnBtn) addFnBtn.style.display = '';
  const showPrevTools = !!isPreview;
  if (rebuildBtn) rebuildBtn.style.display = showPrevTools ? '' : 'none';
  if (liveBtn) liveBtn.style.display = showPrevTools ? '' : 'none';
  if (openBtn2) openBtn2.style.display = showPrevTools ? '' : 'none';
  if (listBtn) listBtn.style.display = showPrevTools ? '' : 'none';
  if (passBtn) passBtn.style.display = showPrevTools ? '' : 'none';
  if (credHint) {
    credHint.style.display = showPrevTools ? '' : 'none';
    if (showPrevTools) credHint.textContent = '🧪 Preview login: ' + (env.adminUser || 'admin') + ' · data is test-only (production stays separate)';
  }
  if (readyBadge && showPrevTools) {
    readyBadge.style.display = '';
    readyBadge.textContent = '⏳ Checking…';
    refreshPreviewReadiness();
  } else if (readyBadge) { readyBadge.style.display = 'none'; }
}
function paintAdminBarProvisioning() {
  const adminBar   = document.getElementById('admin-url-bar');
  const urlDisplay = document.getElementById('admin-url-display');
  if (adminBar) adminBar.style.display = 'flex';
  if (urlDisplay) urlDisplay.value = '⚙️ Provisioning live preview admin…';
}
function previewBarFailed() {
  const urlDisplay = document.getElementById('admin-url-display');
  if (urlDisplay) urlDisplay.value = '⚠️ Preview backend unavailable — click the 🔐 Admin Panel tab to retry';
}
/* Shared workspace admin loader: published login when available,
   otherwise the real pre-publish preview admin. Never a mock. */
function loadWorkspaceAdmin() {
  const pub = getPublishedSession();
  if (pub && pub.adminUrl) {
    paintPreviewBar({ adminUrl: pub.adminUrl }, false);
    const f = document.getElementById('live-iframe');
    if (f) { f.src = pub.adminUrl; f.removeAttribute('srcdoc'); }
    return;
  }
  paintAdminBarProvisioning();
  ensurePreviewEnv().then(env => {
    if (!env) { previewBarFailed(); return; }
    paintPreviewBar(env, true);
    if (currentViewMode !== 'admin') return;
    const f = document.getElementById('live-iframe');
    if (f) { f.removeAttribute('srcdoc'); f.src = env.adminUrl; }
  });
}
function previewOpenSite() {
  const f = document.getElementById('live-iframe');
  ensurePreviewEnv().then(env => {
    if (!env) return;
    if (currentViewMode !== 'site' && currentViewMode !== 'admin') return;
    if (f) { f.removeAttribute('srcdoc'); f.src = env.siteUrl; }
    showToast('🧪 Live preview site loaded — forms hit the real backend');
  });
}
/* Customer preview in a separate tab: the full site on a real URL. */
function previewOpenTab() {
  ensurePreviewEnv().then(env => {
    if (!env) return;
    window.open(env.siteUrl, '_blank');
  });
}
function previewRebuild() {
  ensurePreviewEnv({ force: true }).then(env => {
    if (!env) return;
    paintPreviewBar(env, true);
    const f = document.getElementById('live-iframe');
    if (f) {
      f.removeAttribute('srcdoc');
      f.src = (currentViewMode === 'admin') ? env.adminUrl : env.siteUrl;
    }
    refreshPreviewReadiness();
  });
}
/* ═══ WORKSPACE CREDENTIALS — view/generate the admin login used at publish.
   Always available here (unlike the one-time publish display). Post-publish
   changes happen in Website Manager. */
function workspaceCreds() {
  let u = '', p = '';
  try {
    u = (document.getElementById('admin_custom_user')?.value || '').trim().toLowerCase();
    p = document.getElementById('admin_custom_pass')?.value || '';
    if (!u && window.__pendingAdminCreds?.user) u = window.__pendingAdminCreds.user;
    if (!p && window.__pendingAdminCreds?.pass) p = window.__pendingAdminCreds.pass;
    if (!u || !p) {
      const s = JSON.parse(localStorage.getItem(SESSION_KEY) || '{}');
      if (!u && s.adminUsername) u = s.adminUsername;
      if (!p && s.adminPassword) p = s.adminPassword;
    }
    if (!u) {
      const biz = (document.getElementById('biz_name')?.value || 'site').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 12) || 'site';
      u = (biz + '_admin').slice(0, 20);
    }
  } catch (e) {}
  return { user: u, pass: p };
}
function openCredsModal() {
  const c = workspaceCreds();
  const u = document.getElementById('creds-user'), p = document.getElementById('creds-pass');
  if (u) u.value = c.user || '';
  if (p) { p.value = c.pass || ''; p.type = 'password'; }
  const m = document.getElementById('creds-msg'); if (m) m.textContent = '';
  const mo = document.getElementById('creds-modal'); if (mo) mo.style.display = 'flex';
}
function closeCredsModal() { const mo = document.getElementById('creds-modal'); if (mo) mo.style.display = 'none'; }
function toggleCredPassField(btn) {
  const p = document.getElementById('creds-pass'); if (!p) return;
  const show = p.type === 'password'; p.type = show ? 'text' : 'password';
  if (btn) btn.textContent = show ? '🙈' : '👁️';
}
function copyCredField(id) {
  const el = document.getElementById(id); if (!el || !el.value) { showToast('Nothing to copy yet'); return; }
  try { if (navigator.clipboard) navigator.clipboard.writeText(el.value); } catch (e) {}
  showToast('Copied ✓');
}
function wcGenCredPass() {
  const words = ['Tiger', 'Ocean', 'Eagle', 'Maple', 'Comet', 'Lotus', 'River', 'Falcon'];
  const pw = words[Math.floor(Math.random() * words.length)] + (1000 + Math.floor(Math.random() * 9000)) + '@';
  const p = document.getElementById('creds-pass'); if (p) { p.type = 'text'; p.value = pw; }
  const m = document.getElementById('creds-msg'); if (m) { m.style.color = '#6ee7b7'; m.textContent = 'Strong password generated — press Save.'; }
}
function saveCredsModal() {
  const uEl = document.getElementById('creds-user'), pEl = document.getElementById('creds-pass');
  const u = (uEl?.value || '').trim().toLowerCase();
  const p = pEl?.value || '';
  const m = document.getElementById('creds-msg');
  const say = (t, ok) => { if (m) { m.style.color = ok ? '#6ee7b7' : '#fca5a5'; m.textContent = t; } };
  if (!/^[a-z][a-z0-9_]{3,19}$/.test(u)) { say('Username: 4–20 characters, must start with a letter.'); return; }
  if (p.length < 8) { say('Password must be at least 8 characters.'); return; }
  try {
    const wu = document.getElementById('admin_custom_user'); if (wu) wu.value = u;
    const wp = document.getElementById('admin_custom_pass'); if (wp) wp.value = p;
    window.__pendingAdminCreds = { user: u, pass: p };
    if (typeof saveSessionNow === 'function') saveSessionNow();
  } catch (e) {}
  say('✓ Saved — this login will be created at publish.', true);
  showToast('✓ Admin login saved');
  setTimeout(closeCredsModal, 700);
}
async function previewResetPass() {  const prev = getPreviewSession();
  if (!prev || !prev.id) { showToast('Build the preview first'); return; }
  try {
    const res = await fetch(SITE_URL + '/api/preview-env.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reset_creds', draftId: prev.id })
    });
    const j = await res.json();
    if (!(j && j.success)) throw new Error((j && j.error) || 'reset failed');
    showToast('🔑 New preview password (shown once — copied): ' + j.adminPassOnce, 9000);
    try { if (navigator.clipboard) navigator.clipboard.writeText(j.adminPassOnce); } catch (e) {}
  } catch (e) { showToast('⚠️ ' + (e.message || 'reset failed'), 5000); }
}
async function refreshPreviewReadiness() {
  const badge = document.getElementById('preview-readiness');
  const prev = getPreviewSession();
  if (!prev || !prev.id) return;
  try {
    const res = await fetch(SITE_URL + '/api/preview-env.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'readiness', draftId: prev.id })
    });
    const j = await res.json();
    if (badge) {
      if (j && j.success && j.ready) {
        const n = (j.checks || []).length;
        badge.textContent = '✅ Ready to publish (' + n + '/' + n + ' checks)';
        badge.style.color = '#34d399';
      } else if (j && j.success) {
        const bad = (j.checks || []).filter(c => c.critical && !c.pass);
        badge.textContent = '⚠️ ' + bad.length + ' check(s) need attention — see console';
        badge.style.color = '#fbbf24';
        console.warn('[preview readiness]', j.checks);
      } else if (badge) { badge.textContent = ''; }
    }
  } catch (e) { if (badge) badge.textContent = ''; }
}
/* Pre-publish checklist modal: share URL + controls + real check results.
   Fix → Retest → Publish. Nothing here auto-passes. */
function openPreviewChecklist() {
  let m = document.getElementById('preview-checklist-modal');
  if (!m) {
    m = document.createElement('div');
    m.id = 'preview-checklist-modal';
    m.style.cssText = 'position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.7);padding:1rem;';
    m.innerHTML = '<div style="background:#111622;border:1px solid #283347;border-radius:18px;max-width:640px;width:100%;max-height:88vh;overflow-y:auto;padding:1.5rem;">'
      + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">'
      + '<h2 style="color:#fff;font-size:1.1rem;margin:0;">Pre-publish checklist</h2>'
      + '<button onclick="closePreviewChecklist()" style="background:none;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer;">x</button></div>'
      + '<div id="pcl-share" style="margin-bottom:1rem;"></div>'
      + '<div id="pcl-checks"><p style="color:#94a3b8;font-size:.85rem;">Loading checks…</p></div>'
      + '<div style="display:flex;gap:.6rem;margin-top:1rem;flex-wrap:wrap;">'
      + '<button onclick="refreshChecklistModal()" style="padding:.6rem 1.1rem;border-radius:9px;border:1.5px solid #334155;background:#0b0f17;color:#cbd5e1;font-weight:700;cursor:pointer;">Re-run tests</button>'
      + '<button onclick="closePreviewChecklist()" style="padding:.6rem 1.1rem;border-radius:9px;border:none;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-weight:700;cursor:pointer;">Done</button>'
      + '</div>'
      + '<p style="color:#64748b;font-size:.75rem;margin-top:.8rem;line-height:1.5;">Fix any failing item in the preview, then Re-run tests. Publishing with failing critical checks shows a warning first — it never reports ready falsely.</p>'
      + '</div>';
    document.body.appendChild(m);
    m.addEventListener('click', (e) => { if (e.target === m) closePreviewChecklist(); });
  }
  m.style.display = 'flex';
  refreshChecklistModal();
}
function closePreviewChecklist() {
  const m = document.getElementById('preview-checklist-modal');
  if (m) m.style.display = 'none';
}
function pclEsc(s) {
  return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
async function refreshChecklistModal() {
  const box = document.getElementById('pcl-checks');
  const shareBox = document.getElementById('pcl-share');
  const prev = (typeof getPreviewSession === 'function') ? getPreviewSession() : null;
  if (!prev || !prev.id) {
    if (box) box.innerHTML = '<p style="color:#fbbf24;font-size:.85rem;">No preview built yet — open the Admin Panel or Frontend Site tab first.</p>';
    if (shareBox) shareBox.innerHTML = '';
    return;
  }
  if (box) box.innerHTML = '<p style="color:#94a3b8;font-size:.85rem;">Running checks…</p>';
  try {
    const ri = await fetch(SITE_URL + '/api/preview-env.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'readiness', draftId: prev.id }) }).then(r => r.json());
    if (shareBox) {
      const siteUrl = (prev.siteUrl || '');
      shareBox.innerHTML = '<div style="background:#0b0f17;border:1px solid #1e293b;border-radius:12px;padding:.9rem 1rem;margin-bottom:1rem;">'
        + '<div style="font-size:.75rem;font-weight:800;color:#a5b4fc;margin-bottom:.4rem;">CUSTOMER SHARE LINK (no builder login needed)</div>'
        + '<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">'
        + '<input readonly value="' + pclEsc(siteUrl) + '" onclick="this.select()" style="flex:1;min-width:200px;background:#050810;border:1px solid #1e293b;border-radius:8px;padding:.45rem .7rem;color:#67e8f9;font-family:monospace;font-size:.75rem;">'
        + '<button onclick="pclCopyShare()" style="padding:.45rem .9rem;border-radius:8px;border:1.5px solid #334155;background:#0b0f17;color:#cbd5e1;font-weight:700;cursor:pointer;font-size:.78rem;">Copy</button>'
        + '<button onclick="pclShare(14)" style="padding:.45rem .9rem;border-radius:8px;border:none;background:#065f46;color:#a7f3d0;font-weight:700;cursor:pointer;font-size:.78rem;">Extend 14d</button>'
        + '<button onclick="pclRevoke()" style="padding:.45rem .9rem;border-radius:8px;border:1px solid #7f1d1d;background:transparent;color:#fca5a5;font-weight:700;cursor:pointer;font-size:.78rem;">Revoke</button>'
        + '</div>'
        + '<div style="font-size:.72rem;color:#64748b;margin-top:.35rem;">Anyone with the link can browse + test the preview site. Admin still needs its password. Revoking shows visitors a “revoked” page; data is kept.</div>'
        + '</div>';
    }
    if (!ri || !ri.success) { if (box) box.innerHTML = '<p style="color:#f87171;">Readiness service error.</p>'; return; }
    const rows = (ri.checks || []).map(c => {
      const ok = !!c.pass;
      const dot = ok ? '#10b981' : ((c.critical) ? '#ef4444' : '#f59e0b');
      const tag = ok ? 'PASS' : (c.critical ? 'FAIL' : 'WARN');
      return '<div style="display:flex;gap:.6rem;align-items:flex-start;padding:.5rem 0;border-bottom:1px dashed #1e293b;font-size:.83rem;">'
        + '<span style="color:' + dot + ';font-weight:800;min-width:44px;">' + tag + '</span>'
        + '<div><strong style="color:#fff;">' + pclEsc(c.label || c.key) + '</strong>'
        + (c.detail ? '<div style="color:#94a3b8;font-size:.76rem;">' + pclEsc(c.detail) + '</div>' : '')
        + '</div></div>';
    }).join('');
    const untested = '<div style="font-size:.76rem;color:#64748b;margin-top:.6rem;">Untested by this checklist: AI provider lanes, real email delivery, payment gateways (counter-payment flow is testable; online gateways need production keys).</div>';
    const head = ri.ready
      ? '<div style="background:rgba(16,185,129,.12);border:1px solid #10b981;color:#34d399;padding:.7rem 1rem;border-radius:10px;font-weight:800;margin-bottom:.8rem;">Ready to publish — all critical checks pass</div>'
      : '<div style="background:rgba(239,68,68,.1);border:1px solid #ef4444;color:#fca5a5;padding:.7rem 1rem;border-radius:10px;font-weight:800;margin-bottom:.8rem;">Not ready — fix failing items, then Re-run tests</div>';
    if (box) box.innerHTML = head + rows + untested;
    const badge = document.getElementById('preview-readiness');
    if (badge) {
      const n = (ri.checks || []).length;
      const bad = (ri.checks || []).filter(c => c.critical && !c.pass).length;
      badge.textContent = bad ? ('⚠ ' + bad + ' check(s) failing') : ('Ready to publish (' + n + '/' + n + ')');
      badge.style.color = bad ? '#fbbf24' : '#34d399';
    }
  } catch (e) { if (box) box.innerHTML = '<p style="color:#f87171;">Network error loading checks.</p>'; }
}
function pclCopyShare() {
  const prev = (typeof getPreviewSession === 'function') ? getPreviewSession() : null;
  if (prev && prev.siteUrl) {
    try { navigator.clipboard.writeText(prev.siteUrl).catch(() => {}); } catch (e) {}
    showToast('Share link copied — expires automatically, revocable anytime');
  }
}
async function pclShare(days) {
  const prev = (typeof getPreviewSession === 'function') ? getPreviewSession() : null;
  if (!prev || !prev.id) return;
  try {
    const res = await fetch(SITE_URL + '/api/preview-env.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'share', draftId: prev.id, days: days || 14 }) });
    const j = await res.json();
    if (j && j.success) { showToast('Share link live until ' + (j.expiresAt || '').slice(0, 10)); refreshChecklistModal(); }
    else showToast('⚠ ' + ((j && j.error) || 'share failed'));
  } catch (e) { showToast('⚠ Network error'); }
}
async function pclRevoke() {
  const prev = (typeof getPreviewSession === 'function') ? getPreviewSession() : null;
  if (!prev || !prev.id) return;
  if (!confirm('Revoke the customer share link? Visitors will see “revoked”. Your data stays intact and you can re-share anytime.')) return;
  try {
    const res = await fetch(SITE_URL + '/api/preview-env.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'revoke', draftId: prev.id }) });
    const j = await res.json();
    if (j && j.success) { showToast('Share link revoked'); refreshChecklistModal(); try { await refreshPreviewReadiness(); } catch (e) {} }
    else showToast('⚠ ' + ((j && j.error) || 'revoke failed'));
  } catch (e) { showToast('⚠ Network error'); }
}
/* Pre-publish AI feature flow: generate with the AI mix, install into the
   PREVIEW env, test immediately — publishing later carries it over. */
async function previewAddFeatureFlow(presetReq) {
  if (!window.AIAdminGenerator || typeof window.AIAdminGenerator.generate !== 'function') {
    showToast('AI engine not loaded yet — try again in a moment', 4000);
    return;
  }
  const req = (typeof presetReq === 'string' && presetReq.trim().length >= 10)
    ? presetReq.trim()
    : prompt('Describe the feature to add and test BEFORE publishing (e.g. "Add a Reviews section with reviewer name, star rating and comment"):');
  if (req === null || req === undefined) return;
  if (!req || req.trim().length < 10) { showToast('Please describe the feature in more detail'); return; }
  const env = await ensurePreviewEnv();
  if (!env) return;
  let snap = {};
  try { snap = (typeof collectWizardSnapshot === 'function') ? collectWizardSnapshot() : {}; } catch (e) {}
  showToast('🤖 AI is building your feature… (30–60s)');
  try {
    const result = await window.AIAdminGenerator.generate({
      bizName: (document.getElementById('biz_name') && document.getElementById('biz_name').value) || snap.biz_name || 'Website',
      bizType: snap.biz_type || 'business',
      userRequirements: req.trim(),
      genMode: 'admin'
    }, (p) => { if (p && p.message) showToast('🤖 ' + p.message); });
    if (!result || !result.files) throw new Error('AI did not return valid file structure');
    const res = await fetch(SITE_URL + '/api/preview-env.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add_feature', draftId: env.id,
        requirements: req.trim(), new_files: result.files, new_entities: result.entities || [] })
    });
    const j = await res.json();
    if (!j.success) throw new Error(j.error || 'Installation failed');
    const added = (j.entities_added && j.entities_added.length) ? ' + ' + j.entities_added.join(', ') : '';
    showToast('✅ Installed in PREVIEW (' + j.files_added + ' files' + added + ') — test it now, then publish', 7000);
    const f = document.getElementById('live-iframe');
    if (f && currentViewMode === 'admin') { f.removeAttribute('srcdoc'); f.src = env.adminUrl; }
    try { await refreshPreviewReadiness(); } catch (e) {}
  } catch (err) {
    console.warn(err);
    showToast('❌ ' + (err.message || err), 6000);
  }
}

function openFullscreenModal(index) {
  modalViewingIndex = index; modalViewMode = 'site';
  const c = generatedConcepts[index];
  if (!c) return;
  document.getElementById('modal-title').textContent = c.name;
  const tabs = document.getElementById('modal-view-tabs');
  const hasAdmin = selectedGenMode !== 'static';
  tabs.style.display = hasAdmin ? 'flex' : 'none';
  document.getElementById('m-vtab-site').classList.add('active');
  document.getElementById('m-vtab-admin').classList.remove('active');
  __armSpin('modal-frame-spin', 'modal');
  document.getElementById('modal-iframe').srcdoc = withWorkspaceNavFix(c.html);
  setModalDevice('desktop');
  document.getElementById('fullscreen-modal').classList.add('active');
}
function switchModalView(view) {
  modalViewMode = view;
  const c = generatedConcepts[modalViewingIndex];
  if (!c) return;
  document.getElementById('m-vtab-site').classList.toggle('active', view === 'site');
  document.getElementById('m-vtab-admin').classList.toggle('active', view === 'admin');
  __armSpin('modal-frame-spin', 'modal');
  const mf = document.getElementById('modal-iframe');
  if (view === 'admin') {
    const pub = getPublishedSession();
    if (pub && pub.adminUrl) { mf.removeAttribute('srcdoc'); mf.src = pub.adminUrl; }
    else {
      mf.srcdoc = '<p style="padding:2rem;font-family:sans-serif;">Provisioning live preview admin…</p>';
      ensurePreviewEnv().then(env => {
        if (!env) { mf.srcdoc = '<p style="padding:2rem;font-family:sans-serif;">Preview backend unavailable — close and retry.</p>'; return; }
        if (modalViewMode !== 'admin') return;
        mf.removeAttribute('srcdoc'); mf.src = env.adminUrl;
      });
    }
  } else {
    mf.srcdoc = withWorkspaceNavFix(c.html);
  }
}
function closeFullscreenModal() { document.getElementById('fullscreen-modal').classList.remove('active'); }
function selectFromModal() { closeFullscreenModal(); selectDesignAndEdit(modalViewingIndex); }
function openAdminPreview(index) {
  const c = generatedConcepts[index];
  if (!c) return;
  if (selectedGenMode === 'static') { showToast('Static websites have no admin panel'); return; }
  modalViewingIndex = index; modalViewMode = 'admin';
  document.getElementById('modal-title').textContent = `${c.name} — Admin Panel`;
  document.getElementById('modal-view-tabs').style.display = 'flex';
  document.getElementById('m-vtab-site').classList.remove('active');
  document.getElementById('m-vtab-admin').classList.add('active');
  __armSpin('modal-frame-spin', 'modal');
  const mf2 = document.getElementById('modal-iframe');
  mf2.srcdoc = '<p style="padding:2rem;font-family:sans-serif;">Provisioning live preview admin…</p>';
  ensurePreviewEnv().then(env => {
    if (!env) { mf2.srcdoc = '<p style="padding:2rem;font-family:sans-serif;">Preview backend unavailable — close and retry.</p>'; return; }
    if (modalViewMode !== 'admin') return;
    mf2.removeAttribute('srcdoc'); mf2.src = env.adminUrl;
  });
  setModalDevice('desktop');
  document.getElementById('fullscreen-modal').classList.add('active');
}

/* ══════════════════════════════════════════════════
   SELECT DESIGN → WORKSPACE
═════════════════════════════════════════════════ */
function selectDesignAndEdit(index) {
  const c = generatedConcepts[index] || generatedDesigns[index];
  if (!c) return;

  // ★ Situation loader: workspace boot (creative 3D, quick beat)
  try { if (window.Loader3D) Loader3D.show('Opening workspace…', 'Loading "' + (c.name || 'design') + '"', 'home'); } catch (e) {}

  if (!generatedDesigns.length) {
    generatedDesigns = generatedConcepts.map(x => ({
      name: x.name, description: x.description, badge: x.badge, html: x.html,
      adminHtml: x.adminHtml || null, phpBackend: x.phpBackend || null,
      sqlSchema: x.sqlSchema || null, history: []
    }));
  }

  activeDesignIndex = index;
  currentHtml = generatedDesigns[index].html;
  currentViewMode = 'site';
  document.getElementById('wizard-screen').style.display = 'none';
  document.getElementById('designs-screen').style.display = 'none';
  document.getElementById('builder-screen').style.display = 'flex';
  document.getElementById('tb-biz-badge').textContent = document.getElementById('biz_name').value || 'Website';
  [0, 1, 2].forEach(i => document.getElementById(`tab-d${i}`)?.classList.toggle('active', i === index));
  updateViewTabsVisibility();
  document.getElementById('vtab-site').classList.add('active');
  document.getElementById('vtab-admin').classList.remove('active');
  document.getElementById('current-view-label').textContent = '🌐 Frontend Site';
  updateLiveIframe(currentHtml);
  updateUndoBtn();
  saveSessionNow();
  try { if (window.Loader3D) setTimeout(() => Loader3D.hide(), 650); } catch (e) {}
  showToast(`✏️ Loaded "${c.name}"`);
}
/* Variations live on variations.php (separate URL) — every "back to
   variations" routes there so the customer always lands INSIDE the
   variations list, never stuck in the workspace.
   All back-navigation shows the 3D rewind-portal loader first. */
let __backNavPending = false;
function backLoaderGo(url, msg, sub) {
  if (__backNavPending) return;
  __backNavPending = true;
  try { if (window.Loader3D) Loader3D.show(msg || 'Going back…', sub || 'Loading previous screen', 'back'); } catch (e) {}
  setTimeout(() => { window.location.href = url; }, 520);
}
function navHomeWithLoader(a) {
  if (!a || !a.href || __backNavPending) return false;
  __backNavPending = true;
  try { if (window.Loader3D) Loader3D.show('Going home…', 'Back to WebCraft home', 'back'); } catch (e) {}
  const url = a.href;
  setTimeout(() => { window.location.href = url; }, 450);
  return false;
}
function goToVariations() {
  try { saveSessionNow(); } catch (e) {}
  backLoaderGo(((typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '') + '/variations.php', 'Opening variations…', 'Loading your 3 style concepts');
}
function backToDesigns() {
  goToVariations();
}
/* Wizard screen (step 1) — used by the Back buttons on picker screens.
   Shows the rewind-portal loader so the transition feels animated. */
function backToWizard() {
  try { if (window.Loader3D) Loader3D.show('Going back…', 'Back to setup wizard', 'back'); } catch (e) {}
  setTimeout(() => {
    document.getElementById('builder-screen').style.display = 'none';
    document.getElementById('designs-screen').style.display = 'none';
    document.getElementById('subdesign-screen').style.display = 'none';
    document.getElementById('wizard-screen').style.display = 'flex';
    try { updateWizDisplay(); } catch (e) {}
    window.scrollTo({ top: 0 });
    setTimeout(() => { try { if (window.Loader3D) Loader3D.hide(); } catch (e) {} }, 450);
  }, 500);
}

/* ══════════════════════════════════════════════════
   SUB-DESIGN PICKER — Show 3 layout variants for a concept
═════════════════════════════════════════════════ */
function variationOfConcept(c, idx) {
  if (c && ['classic', 'bold', 'editorial'].includes(c.id)) return c.id;
  const m = /solo-(light|bold|dark)/.exec((c && c.id) || '');
  if (m) return m[1] === 'light' ? 'classic' : (m[1] === 'dark' ? 'editorial' : 'bold');
  return ['classic', 'bold', 'editorial'][(idx || 0) % 3];
}

async function fetchTemplateSubdesigns(variation, data) {
  try {
    const base = (typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '';
    const resp = await fetch((base ? base : '') + '/api/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'flowcraft_subdesigns', concept_id: variation, data: data || {} })
    });
    const j = await resp.json();
    if (j && j.success && Array.isArray(j.subdesigns)) return j.subdesigns;
  } catch (e) { console.warn('[subdesigns] template fallback failed:', e?.message); }
  return [];
}

async function showSubDesigns(conceptIndex) {
  const concept = generatedConcepts[conceptIndex];
  if (!concept) return;
  if (concept.subdesigns && concept.subdesigns.length) {
    renderSubDesignGrid(conceptIndex, '');
    return;
  }

  // No subdesigns yet — generate 3 AI layouts in THIS style (same fixed
  // split: slots 1-2 Gemini, slot 3 OpenCode). Failed slots → templates.
  const variation = variationOfConcept(concept, conceptIndex);
  const data = collectWizardSnapshot();
  data.biz_name = data.biz_name || (concept.meta && concept.meta.bizName) || 'Website';
  showGenOverlay(data);
  try {
    updateGenOverlay('generating', `Crafting 3 AI layouts in "${variation}" style…`, 28, {});
    let subs = null;
    let engineNote = '';
    let subAiErr = '';
    let subTokens = 0;
    const subTokStr = () => subTokens > 0 ? ` · ~${(subTokens / 1000).toFixed(1)}k tokens` : '';
    try {
      if (!window.OpenCodeAI?.generateSubDesigns) throw new Error('AI engine not loaded.');
      const r = await window.OpenCodeAI.generateSubDesigns(data, variation, selectedGenMode, conceptIndex + 1, {
        brief: (concept.meta && concept.meta.brief) || '',
        onProgress: (p) => updateGenOverlay(p.stage || 'generating', p.message, p.pct || 50, p)
      });
      subTokens = r.tokens || 0;
      if (r.designs.length === 3) {
        subs = r.designs;
        const hasOpenCode = subs.some(c => ((c.meta && c.meta.engine) || '').includes('opencode'));
        engineNote = hasOpenCode ? 'Gemini ×2 + Space Bunny' : '3× Gemini AI (Fast)';
      } else if (r.designs.length > 0) {
        // PARTIAL: keep ready AI layouts in their own slots, fill ONLY
        // missing slots with templates (index-merge would misplace a
        // layout when the middle slot failed — map by slot instead).
        const tpl = await fetchTemplateSubdesigns(variation, data);
        const aiBySlot = {};
        r.designs.forEach(d => {
          let s = (d && typeof d.slot === 'number') ? d.slot : -1;
          if (s < 0 || s > 2) {
            const m = /-([ABC])$/.exec(d.id || '');
            s = m ? ({ A: 0, B: 1, C: 2 })[m[1]] : -1;
          }
          if (s >= 0) aiBySlot[s] = d;
        });
        subs = [0, 1, 2].map(i => aiBySlot[i] || tpl[i]).filter(Boolean);
        if (!subs.length) subs = null;
        else {
          engineNote = 'AI (' + r.designs.length + '/3) + template (' + (3 - r.designs.length) + '/3)';
          showToast('Partial AI — ' + r.designs.length + '/3 AI layouts ready, templates filled ' + (3 - r.designs.length) + ' slot(s)');
        }
      }
      if (r.errors && r.errors.length) subAiErr = friendlyAiErr(r.errors[0]);
    } catch (e) {
      subAiErr = friendlyAiErr(e?.message || e);
      console.warn('[subdesigns] AI lane failed, using templates:', e?.message);
    }
    if (!subs || !subs.length) {
      subs = await fetchTemplateSubdesigns(variation, data);
      engineNote = 'templates';
    }
    if (!subs.length) throw new Error('No layouts available');
    concept.subdesigns = subs;
    concept._subEngine = engineNote;
    saveSessionNow();
    hideGenOverlay(true);
    renderSubDesignGrid(conceptIndex, engineNote);
    showToast(engineNote === 'templates' && subAiErr
      ? 'Templates (AI failed: ' + subAiErr + ')' + subTokStr()
      : '3 layouts ready! [' + engineNote + ']' + subTokStr());
  } catch (err) {
    console.error(err);
    hideGenOverlay(false);
    showToast('Failed: ' + err.message);
  }
}

function renderSubDesignGrid(conceptIndex, engineNote) {
  const concept = generatedConcepts[conceptIndex];
  if (!concept) return;
  const subdesigns = concept.subdesigns || [];

  // Hide other screens, show subdesign screen
  document.getElementById('wizard-screen').style.display = 'none';
  document.getElementById('designs-screen').style.display = 'none';
  document.getElementById('builder-screen').style.display = 'none';
  document.getElementById('subdesign-screen').style.display = 'block';

  // Update header
  const conceptNum = conceptIndex + 1;
  document.getElementById('subdesign-concept-badge').textContent = `Concept ${conceptNum} · 3 Layout Variants`;
  document.getElementById('subdesign-title').textContent = `${concept.name} — Choose Your Layout`;
  document.getElementById('subdesign-sub').textContent =
    `All 3 layouts use the same ${concept.name} design language. Pick the structure that best fits your brand.` +
    (engineNote ? ` [${engineNote}]` : '');

  // Build sub-design cards
  const grid = document.getElementById('subdesign-grid');
  grid.innerHTML = '';
  subdesigns.forEach((sd, si) => {
    const card = document.createElement('div');
    card.className = 'design-card';
    const variantLabel = ['A','B','C'][si] || (si+1);
    const sdIsAI = sd.meta && (sd.meta.engine === 'opencode' || sd.meta.engine === 'gemini');
    const sdBadge = sdIsAI
      ? `<div style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.68rem;font-weight:800;color:#6ee7b7;background:rgba(6,78,59,0.35);border:1px solid rgba(16,185,129,0.5);padding:0.22rem 0.6rem;border-radius:999px;margin-bottom:0.6rem;">AI · ${escapeHtml(String((sd.meta && sd.meta.model) || 'AI').split('/').pop())}</div>`
      : `<div style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.68rem;font-weight:800;color:#fcd34d;background:rgba(120,53,15,0.35);border:1px solid rgba(245,158,11,0.5);padding:0.22rem 0.6rem;border-radius:999px;margin-bottom:0.6rem;">TEMPLATE</div>`;
    card.innerHTML = `
      <div class="design-badge-top" style="background:linear-gradient(135deg,#1e1b4b,#312e81);border-color:rgba(99,102,241,0.5);">${escapeHtml(sd.badge || `Variant ${variantLabel}`)}</div>
      <div class="design-number-badge" style="background:linear-gradient(135deg,#6366f1,#a855f7);">${conceptNum}${variantLabel}</div>
      <div class="design-preview-box"><div class="pv-spin"><div class="wcl-mini-house"><div class="walls"></div><div class="roof"></div><div class="door"></div></div></div><iframe class="design-preview-iframe" id="sd${conceptIndex}-${si}-iframe" onload="try{this.previousElementSibling.remove();}catch(e){}"></iframe></div>
      <div class="design-content">
        <h3>${escapeHtml(sd.name || `Layout ${variantLabel}`)}</h3>
        ${sdBadge}
        <p>${escapeHtml(sd.description || 'A premium layout variant for your selected concept.')}</p>
        <div class="design-actions">
          <button class="btn-preview-modal" onclick="openSubdesignFullPreview(${conceptIndex},${si})">Full Preview</button>
          <button class="btn-choose-design" onclick="selectSubDesign(${conceptIndex},${si})" style="background:linear-gradient(135deg,#10b981,#059669);border-color:#34d399;">Select &amp; Edit →</button>
        </div>
      </div>`;
    grid.appendChild(card);
    // Load iframe after short delay
    setTimeout(() => {
      const f = document.getElementById(`sd${conceptIndex}-${si}-iframe`);
      if (f && sd.html) f.srcdoc = sanitizeHtmlOutput(withWorkspaceNavFix(sd.html));
    }, 50 + si * 30);
  });

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function selectSubDesign(conceptIndex, subIndex) {
  const concept = generatedConcepts[conceptIndex];
  if (!concept) return;
  const sd = concept.subdesigns && concept.subdesigns[subIndex];
  if (!sd || !sd.html) {
    // Fallback to main concept HTML
    selectDesignAndEdit(conceptIndex);
    return;
  }
  // Initialize generatedDesigns if needed
  if (!generatedDesigns.length) {
    generatedDesigns = generatedConcepts.map(x => ({
      name: x.name, description: x.description, badge: x.badge, html: x.html,
      engine: (x.meta && x.meta.engine) || 'template', model: (x.meta && x.meta.model) || '',
      adminHtml: x.adminHtml || null, phpBackend: x.phpBackend || null,
      sqlSchema: x.sqlSchema || null, history: []
    }));
  }
  // Inject the selected sub-design HTML as the active design for this concept
  generatedDesigns[conceptIndex].html = sd.html;
  generatedDesigns[conceptIndex].name = sd.name || generatedDesigns[conceptIndex].name;
  generatedDesigns[conceptIndex].engine = (sd.meta && sd.meta.engine) || 'template';
  generatedDesigns[conceptIndex].model = (sd.meta && sd.meta.model) || '';
  // Now enter the workspace as normal
  selectDesignAndEdit(conceptIndex);
  showToast(`Layout "${sd.name || 'Variant'}" loaded [${generatedDesigns[conceptIndex].engine}] — ready to edit!`);
}

function openSubdesignFullPreview(conceptIndex, subIndex) {
  const concept = generatedConcepts[conceptIndex];
  if (!concept) return;
  const sd = concept.subdesigns && concept.subdesigns[subIndex];
  if (!sd || !sd.html) return;
  const w = window.open('about:blank', '_blank');
  if (w) { w.document.open(); w.document.write(withWorkspaceNavFix(sd.html)); w.document.close(); }
}

function backToDesignsFromSub() {
  goToVariations();
}
function updateViewTabsVisibility() {
  // Admin tab is always available in admin/database mode: the real
  // generated preview is fetched on click (delay OK), published URL after.
  const show = selectedGenMode !== 'static';
  document.getElementById('view-tabs').classList.toggle('show', !!show);
  try { syncDesignTabs(); } catch (e) {}
}
/* Variation tabs mirror the ACTUAL designs array (flagship = 1 design):
   unused tabs hide so dead/duplicated variation buttons never show. */
function syncDesignTabs() {
  const n = (typeof generatedDesigns !== 'undefined' && Array.isArray(generatedDesigns)) ? generatedDesigns.length : 0;
  for (let i = 0; i < 3; i++) {
    const b = document.getElementById('tab-d' + i);
    if (!b) continue;
    b.style.display = (n === 0 || i < n) ? '' : 'none';
  }
  if (typeof activeDesignIndex === 'number' && activeDesignIndex >= n && n > 0) {
    try { switchActiveDesign(0); } catch (e) {}
  }
}
function getPublishedSession() {
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    const sess = raw ? JSON.parse(raw) : null;
    return sess?.published || null;
  } catch (e) { return null; }
}

/* ══════════════════════════════════════════════════
   PRE-PUBLISH DEV ADMIN (api/preview-env.php)
   Click Admin Panel → provision/refresh the real preview workspace
   (real PHP admin + real JSON) → load its login page in the frame.
   No mock admin anywhere.
   ══════════════════════════════════════════════════ */
/* Admin-tab entry provisions the real pre-publish preview env (module above). */
async function loadRealAdminPreview(bizName) { loadWorkspaceAdmin(); }

function switchWorkspaceView(view) {
  const c = generatedDesigns[activeDesignIndex];
  if (!c) return;
  // Static mode has no admin panel — block it even if forced.
  if (view === 'admin' && selectedGenMode === 'static') {
    showToast('Static websites have no admin panel — pick Admin or Database mode to get one.');
    return;
  }
  currentViewMode = view;
  document.getElementById('vtab-site').classList.toggle('active', view === 'site');
  document.getElementById('vtab-admin').classList.toggle('active', view === 'admin');
  document.getElementById('current-view-label').textContent = view === 'admin' ? '🔐 Admin Panel' : '🌐 Frontend Site';

  if (view === 'admin') {
    // Published → production login. Draft → REAL pre-publish preview admin.
    // There is no mock admin: failures show an honest error, never fake UI.
    const pub = getPublishedSession();
    if (pub && pub.adminUrl) {
      const bizName = document.getElementById('biz_name')?.value || 'My Website';
      const slug = bizName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'site';
      let targetUrl = `${SITE_URL}/published/${slug}/admin/login.php`;
      // Link the REAL login only when it belongs to this site — never an
      // arbitrary stored URL.
      if (typeof pub.adminUrl === 'string') {
        const expectedBase = `${SITE_URL}/published/${slug}/`;
        if (pub.adminUrl === targetUrl || pub.adminUrl.indexOf(expectedBase) === 0) {
          targetUrl = pub.adminUrl;
        }
      }
      paintPreviewBar({ adminUrl: targetUrl }, false);
      const f = document.getElementById('live-iframe');
      if (f) { f.src = targetUrl; f.removeAttribute('srcdoc'); }
    } else {
      loadWorkspaceAdmin();
    }
  } else {
    // Switched back to Site view — hide admin bar + Add Function btn
    const adminBar = document.getElementById('admin-url-bar');
    const addFnBtn = document.getElementById('btn-add-function');
    if (adminBar) adminBar.style.display = 'none';
    if (addFnBtn) addFnBtn.style.display = 'none';
    const prev = getPreviewSession();
    const h = previewHash((c && c.html) || currentHtml);
    const f = document.getElementById('live-iframe');
    if (prev && prev.id && prev.siteUrl && prev.htmlHash === h) {
      // Live preview backend is in sync — real URL, real PHP forms.
      if (f) { f.removeAttribute('srcdoc'); f.src = prev.siteUrl; }
    } else {
      if (f) { f.removeAttribute('src'); }
      updateLiveIframe(c.html);
      // Background sync so forms become live without another click.
      setTimeout(() => {
        try {
          if (currentViewMode !== 'site') return;
          ensurePreviewEnv().then(env => {
            if (!env || currentViewMode !== 'site') return;
            const cc = generatedDesigns[activeDesignIndex];
            if (previewHash((cc && cc.html) || currentHtml) !== env.htmlHash) return;
            const ff = document.getElementById('live-iframe');
            if (ff) { ff.removeAttribute('srcdoc'); ff.src = env.siteUrl; showToast('🧪 Live preview backend ready — forms fully functional'); }
          });
        } catch (e) {}
      }, 800);
    }
  }
  saveSessionNow();
}

function copyAdminUrlBar() {
  const inp = document.getElementById('admin-url-display');
  const btn = document.getElementById('copy-admin-url-btn');
  if (!inp || !inp.value || inp.value.includes('Publish')) return;
  inp.select();
  try {
    navigator.clipboard.writeText(inp.value).catch(() => document.execCommand('copy'));
  } catch(e) { document.execCommand('copy'); }
  if (btn) { btn.textContent = '✓ Copied!'; btn.style.background = '#065f46'; setTimeout(() => { btn.textContent = '📋 Copy URL'; btn.style.background = ''; }, 2000); }
}

function openAddFunctionManager() {
  const pub = getPublishedSession();
  if (pub && pub.orderId) {
    window.open(SITE_URL + '/site-manager.php?order_id=' + encodeURIComponent(pub.orderId) + '#ai-adder', '_blank');
  } else {
    if (selectedGenMode === 'static') { showToast('Static websites have no admin panel — pick Admin or Database mode to get one.'); return; }
    if (currentViewMode !== 'admin') switchWorkspaceView('admin');
    previewAddFeatureFlow();
  }
}

function switchActiveDesign(index) {
  if (!generatedDesigns[index]) return;
  activeDesignIndex = index;
  currentHtml = generatedDesigns[index].html;
  currentViewMode = 'site';
  [0, 1, 2].forEach(i => document.getElementById(`tab-d${i}`)?.classList.toggle('active', i === index));
  updateViewTabsVisibility();
  document.getElementById('vtab-site').classList.add('active');
  document.getElementById('vtab-admin').classList.remove('active');
  document.getElementById('current-view-label').textContent = '🌐 Frontend Site';
  // Hide admin bar when switching variation
  const adminBar = document.getElementById('admin-url-bar');
  const addFnBtn = document.getElementById('btn-add-function');
  if (adminBar) adminBar.style.display = 'none';
  if (addFnBtn) addFnBtn.style.display = 'none';
  const f = document.getElementById('live-iframe');
  if (f) f.removeAttribute('src');
  updateLiveIframe(currentHtml);
  updateUndoBtn();
  saveSessionNow();
}
/* ★ Tab/frame load detectors: overlay appears ONLY if a frame takes >350ms
   to render — instant tab switches stay clean, slow loads show mini 3D. */
let __liveSpinTimer = null, __modalSpinTimer = null;
function __armSpin(id, timerSlot) {
  const s = document.getElementById(id);
  if (!s) return;
  if (timerSlot === 'live') { clearTimeout(__liveSpinTimer); __liveSpinTimer = setTimeout(() => { s.style.display = 'flex'; }, 350); }
  else { clearTimeout(__modalSpinTimer); __modalSpinTimer = setTimeout(() => { s.style.display = 'flex'; }, 350); }
}
function __hideSpin(id, timerSlot) {
  if (timerSlot === 'live') clearTimeout(__liveSpinTimer);
  else clearTimeout(__modalSpinTimer);
  const s = document.getElementById(id);
  if (s) s.style.display = 'none';
}
/* Scroll-triggered count-up for stat numbers. Shared by every preview
   surface via withWorkspaceNavFix. Auto-detects single-token numbers,
   skips prices/years/phones/slashes (24/7), honors reduced-motion,
   and no-ops when the page has no stats. */
const WC_COUNT_FIX_JS = '<script data-wc-count-fix>(function(){if(window.__wcCountUp)return;window.__wcCountUp=true;function parseStat(raw){var t=String(raw==null?"":raw).trim();if(!t||t.length>16)return null;if(t.indexOf("/")!==-1)return null;if(/\\s/.test(t))return null;var m=t.match(/^([^0-9.,]*)([0-9][0-9.,]*)([A-Za-z%+]*)$/);if(!m)return null;var pre=m[1]||"",num=m[2],suf=m[3]||"";if(/[$\\u20AC\\u00A3\\u20B9]/.test(pre))return null;if(!/^[kKmMbB]?[%+]{0,2}$/.test(suf))return null;var pure=num.replace(/,/g,"");if((pure.match(/\\./g)||[]).length>1)return null;var target=parseFloat(pure);if(!isFinite(target)||target<=0)return null;var dec=0,di=pure.indexOf(".");if(di!==-1)dec=pure.length-di-1;if(dec>2)return null;if(!pre&&!suf&&dec===0&&target>=1900&&target<=2100)return null;return{pre:pre,target:target,dec:dec,suf:suf,final:t};}function collect(){var out=[];function push(el,p){if(el.__wcCounted)return;el.__wcCounted=true;if(out.length<60)out.push({el:el,p:p});}var tagged=null;try{tagged=document.querySelectorAll("[data-count],[data-target]");}catch(e){}if(tagged){for(var k=0;k<tagged.length;k++){var el2=tagged[k],p2=parseStat(el2.textContent);if(!p2){var av=parseFloat(String(el2.getAttribute("data-count")||el2.getAttribute("data-target")||"").replace(/,/g,""));if(isFinite(av)&&av>0)p2={pre:"",target:av,dec:0,suf:"",final:el2.textContent};}if(p2)push(el2,p2);}}var els=null;try{els=document.querySelectorAll("h1,h2,h3,h4,div,span,p,strong");}catch(e){}if(els){for(var i=0;i<els.length;i++){var el=els[i];if(el.children&&el.children.length>0)continue;if(el.closest&&el.closest("a,button,nav,form,select,textarea,input,script,style"))continue;var p=parseStat(el.textContent);if(p)push(el,p);}}return out;}function render(el,p,v){var s;if(p.dec>0)s=v.toFixed(p.dec);else{try{s=Math.round(v).toLocaleString("en-US");}catch(e){s=String(Math.round(v));}}el.textContent=p.pre+s+p.suf;}function animate(el,p,delay){var reduce=false;try{reduce=!!(window.matchMedia&&window.matchMedia("(prefers-reduced-motion: reduce)").matches);}catch(e){}if(reduce){el.textContent=p.final;return;}var dur=1600,t0=null;function frame(ts){if(t0===null)t0=ts;var t=Math.min((ts-t0)/dur,1);var ez=t>=1?1:1-Math.pow(2,-10*t);render(el,p,p.target*ez);if(t<1)requestAnimationFrame(frame);else render(el,p,p.target);}setTimeout(function(){try{requestAnimationFrame(frame);}catch(e){el.textContent=p.final;}},delay||0);}function init(){var items=collect();if(!items.length)return;for(var i=0;i<items.length;i++)render(items[i].el,items[i].p,0);if("IntersectionObserver" in window){try{var io=new IntersectionObserver(function(es){for(var j=0;j<es.length;j++){var en=es[j];if(en.isIntersecting){try{io.unobserve(en.target);}catch(e){}for(var q=0;q<items.length;q++){if(items[q].el===en.target){animate(en.target,items[q].p,(q%4)*120);break;}}}}},{threshold:0.35});for(var m2=0;m2<items.length;m2++)io.observe(items[m2].el);return;}catch(e){}}for(var n=0;n<items.length;n++)animate(items[n].el,items[n].p,n*120);}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init);else init();})();<\/script>';
/* Preview reliability bundle — injected at END of <head> so (1) the CSS
   lands after the page's own styles and uses !important, and (2) listeners
   register BEFORE body scripts, pre-empting the page's own instant-jump
   handlers. Two independent parts, each added only when its marker is
   missing, so live iframe, cards, modal, new-tab preview and the published
   page all behave the same:
   (a) wc-nav-fix: header anchor links smooth-scroll (+scroll-margin).
   (b) wc-count-fix: stat numbers (50k+, 99.8%, 30+) count up from 0 when
       scrolled into view (IntersectionObserver, once, easeOutExpo).
   (c) wcl-reveal-guard: Space Bunny hides sections by default
       (.reveal/.will-reveal/.fade-up… + opacity:0) with one inline script
       to unhide — if that script throws, sections show ~1s then blank.
       Guard re-adds the visible class after 2.2s (skips fixed/nav). */
const WC_REVEAL_GUARD_JS = '<script data-wcl-reveal-guard>setTimeout(function(){try{var P=[["reveal","in"],["will-reveal","visible"],["fade-up","visible"],["fade-in-up","visible"],["reveal-up","visible"],["scroll-reveal","visible"],["js-reveal","visible"],["anim-up","visible"],["reveal-item","visible"],["fade-in","visible"]];var css="";try{var ss=document.querySelectorAll("style");for(var k=0;k<ss.length;k++){css+=" "+(ss[k].textContent||"")}}catch(e){}P.forEach(function(p){var H=p[0],V=p[1];var rx=new RegExp("\\."+H+"\\b[^{]*\\{[^}]*opacity\\s*:\\s*0","i");if(!rx.test(css))return;try{document.querySelectorAll("."+H+":not(."+V+")").forEach(function(el){try{if(el.closest&&el.closest("nav"))return;var cs=null;try{cs=window.getComputedStyle(el)}catch(e){}if(cs&&cs.position==="fixed")return;el.classList.add(V)}catch(e){}})}catch(e){}})}catch(e){}},2200);<\/script>';
const WC_BLOB_GUARD_JS = '<script data-wc-blob-guard>document.addEventListener("submit",function(e){var f=e.target&&e.target.closest?e.target.closest("form"):null;if(!f)return;var a="";try{a=String(f.action||"")}catch(_){}var t="";try{t=String(f.getAttribute("target")||"").toLowerCase()}catch(_){}var s="";try{s=String((e.submitter&&e.submitter.getAttribute("formtarget"))||"").toLowerCase()}catch(_){}if(t==="_blank"){try{f.removeAttribute("target")}catch(_){}}if(s==="_blank"){try{e.submitter.removeAttribute("formtarget")}catch(_){}}if(a.slice(0,5).toLowerCase()==="blob:"||t==="_blank"||s==="_blank"){e.preventDefault()}},true);<\/script>';
function withWorkspaceNavFix(html) {
  if (!html) return html;
  const haveNav = html.indexOf('wc-nav-fix') !== -1;
  const haveCount = html.indexOf('wc-count-fix') !== -1;
  const haveBlob = html.indexOf('wc-blob-guard') !== -1;
  const needGuard = html.indexOf('wcl-reveal-guard') === -1
    && /\.(reveal|will-reveal|fade-up|fade-in-up|reveal-up|scroll-reveal|js-reveal|anim-up|reveal-item|fade-in)\b[^{]*\{[^}]*opacity\s*:\s*0/i.test(html);
  if (haveNav && haveCount && haveBlob && !needGuard) return html;
  let bundle = '';
  if (!haveNav) {
    bundle += '<style data-wc-nav-fix-css>html{scroll-behavior:smooth!important}section[id],div[id],footer[id],header[id],main[id],article[id]{scroll-margin-top:88px!important}</style>';
    bundle += '<script data-wc-nav-fix>(function(){document.addEventListener("click",function(e){var a=e.target&&e.target.closest?e.target.closest(\'a[href^="#"]\'):null;if(!a)return;var href0=a.getAttribute("href")||"";if(href0==="#"||href0===""){e.preventDefault();try{e.stopImmediatePropagation()}catch(_){}e.stopPropagation();return;}var href=href0;if(href.length<2)return;var id=href.slice(1).split("?")[0].split("#")[0];try{id=decodeURIComponent(id)}catch(_){}id=(id||"").trim();if(!id)return;var t=document.getElementById(id);if(!t)return;e.preventDefault();try{e.stopImmediatePropagation()}catch(_){}e.stopPropagation();try{t.scrollIntoView({behavior:"smooth",block:"start"})}catch(_){try{t.scrollIntoView()}catch(__){}}try{history.replaceState(null,"","#"+id)}catch(__){}var d=document.getElementById("mobile-drawer");if(d)d.classList.remove("open")},true)})();<\/script>';
  }
  if (!haveCount) bundle += WC_COUNT_FIX_JS;
  if (!haveBlob) bundle += WC_BLOB_GUARD_JS;
  if (needGuard) bundle += WC_REVEAL_GUARD_JS;
  // NOTE: function replacers — the bundle contains '$' (currency guard),
  // which a string replacer would misread as a $pattern. Function returns
  // are inserted literally.
  let s = String(html);
  if (/<\/head\s*>/i.test(s)) return s.replace(/<\/head\s*>/i, () => bundle + '</head>');
  if (/<head[^>]*>/i.test(s)) return s.replace(/<head([^>]*)>/i, (m, g1) => '<head' + g1 + '>' + bundle);
  return bundle + s;
}
function updateLiveIframe(html) { const f = document.getElementById('live-iframe'); if (!f) return; __armSpin('live-frame-spin', 'live'); f.srcdoc = sanitizeHtmlOutput(withWorkspaceNavFix(html)); }
function refreshLivePreview() {
  const c = generatedDesigns[activeDesignIndex];
  if (!c) return;
  if (currentViewMode === 'admin') { loadWorkspaceAdmin(); }
  else {
    const prev = getPreviewSession();
    const cc = generatedDesigns[activeDesignIndex];
    const h = previewHash((cc && cc.html) || currentHtml);
    if (prev && prev.id && prev.siteUrl && prev.htmlHash === h) {
      const f = document.getElementById('live-iframe');
      if (f) { f.removeAttribute('srcdoc'); f.src = prev.siteUrl; }
    } else updateLiveIframe(currentHtml);
  }
  showToast('Preview refreshed');
}
function setDevice(device) {
  const c = document.getElementById('preview-container');
  c.className = 'preview-container' + (device !== 'desktop' ? ' ' + device : '');
  ['desktop', 'laptop', 'tablet', 'mobile'].forEach(d => document.getElementById(`d-${d}`)?.classList.toggle('active', d === device));
}
/* Fullscreen modal device preview — same 4 widths as the workspace preview,
   so PC / laptop / tablet / phone views (incl. stat count-up on scroll)
   can be checked in the big preview too. */
function setModalDevice(device) {
  const f = document.getElementById('modal-iframe');
  if (f) f.className = 'modal-iframe m-' + device;
  ['desktop', 'laptop', 'tablet', 'mobile'].forEach(d => document.getElementById(`md-${d}`)?.classList.toggle('active', d === device));
}
/* ══ EDIT MODE — ON: click any text inside the live preview and type
   (iframe designMode + hover outlines). OFF: pure normal preview page,
   edits saved back into the variation. Default OFF. ══ */
let editMode = false;
function setEditModeUI() {
  const b = document.getElementById('btn-edit-mode');
  if (!b) return;
  b.textContent = editMode ? '✏️ Edit: ON' : '✏️ Edit: OFF';
  b.classList.toggle('edit-on', editMode);
}
function setIframeEditable(on) {
  const f = document.getElementById('live-iframe');
  const doc = f && (f.contentDocument || (f.contentWindow && f.contentWindow.document));
  if (!doc || !doc.body) return false;
  doc.designMode = on ? 'on' : 'off';
  let st = doc.getElementById('wc-edit-css');
  if (on) {
    if (!st) {
      st = doc.createElement('style');
      st.id = 'wc-edit-css';
      st.textContent = 'body{outline:2px dashed rgba(99,102,241,.65)!important;outline-offset:-2px!important;}body :hover{outline:1px dashed rgba(99,102,241,.7)!important;}';
      (doc.head || doc.documentElement).appendChild(st);
    }
  } else if (st) st.remove();
  return true;
}
function toggleEditMode() {
  if (!editMode) {
    // Turning ON — only the site preview can be edited inline.
    if (currentViewMode !== 'site' || !generatedDesigns[activeDesignIndex]) {
      showToast('⚠️ Edit mode works on the 🌐 Site preview');
      return;
    }
    let ok = false;
    try { ok = setIframeEditable(true); } catch (e) { ok = false; }
    if (!ok) { showToast('⚠️ Could not edit this preview (external page?)'); return; }
    editMode = true;
    setEditModeUI();
    showToast('✏️ Edit mode ON — click any text and type. Toggle OFF to save + preview normally.');
  } else {
    // Turning OFF — serialize edits back, then show a clean normal preview.
    // Only the site preview is serializable; an admin view is never written
    // back into the variation.
    let html = null;
    if (currentViewMode === 'site') {
      try {
        const f = document.getElementById('live-iframe');
        const doc = f && (f.contentDocument || (f.contentWindow && f.contentWindow.document));
        if (doc && doc.body) {
          try { doc.designMode = 'off'; } catch (e) {}
          doc.getElementById('wc-edit-css')?.remove();
          html = '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
        }
      } catch (e) { html = null; }
    }
    editMode = false;
    setEditModeUI();
    if (html && generatedDesigns[activeDesignIndex]) {
      try { pushHistory(activeDesignIndex, currentHtml); } catch (e) {}
      currentHtml = sanitizeHtmlOutput(html);
      generatedDesigns[activeDesignIndex].html = currentHtml;
      try { updateUndoBtn(); } catch (e) {}
      try { saveSessionNow(); } catch (e) {}
      showToast('✅ Edits saved — normal preview');
    }
    if (currentViewMode === 'site') updateLiveIframe(currentHtml);
  }
}
function openInNewTab() {
  if (currentViewMode === 'admin') {
    // Real admin URL only — never a mock/blob admin page.
    const pub = getPublishedSession();
    if (pub && pub.adminUrl) { window.open(pub.adminUrl, '_blank'); return; }
    const prevA = (typeof getPreviewSession === 'function') ? getPreviewSession() : null;
    if (prevA && prevA.adminUrl) { window.open(prevA.adminUrl, '_blank'); return; }
    showToast('Open the Admin Panel tab first — it builds your live preview admin');
    return;
  }
  // Prefer the real preview URL (separate tab, real backend) when in sync.
  try {
    const prev = (typeof getPreviewSession === 'function') ? getPreviewSession() : null;
    const h = (typeof previewHash === 'function') ? previewHash(currentHtml) : '';
    if (prev && prev.id && prev.siteUrl && prev.htmlHash === h) {
      window.open(prev.siteUrl, '_blank');
      return;
    }
  } catch (e) {}
  const html = withWorkspaceNavFix(currentHtml);
  const previewWindow = window.open('about:blank', '_blank');
  if (previewWindow) {
    previewWindow.document.open();
    previewWindow.document.write(html);
    previewWindow.document.close();
  }
}

/* ══════════════════════════════════════════════════
   UNDO
═════════════════════════════════════════════════ */
function pushHistory(i, html) {
  const d = generatedDesigns[i];
  if (!d) return;
  if (!Array.isArray(d.history)) d.history = [];
  if (d.history[d.history.length - 1] === html) return;
  d.history.push(html);
  if (d.history.length > HISTORY_LIMIT) d.history.shift();
}
function undoLastAiChange() {
  const d = generatedDesigns[activeDesignIndex];
  if (!d || !d.history || d.history.length === 0) { showToast('Nothing to undo'); return; }
  currentHtml = d.history.pop();
  d.html = currentHtml;
  if (currentViewMode === 'site') updateLiveIframe(currentHtml);
  updateUndoBtn();
  saveSessionNow();
  showToast('↶ Reverted');
  appendGeminiChatMessage('↶ Reverted the last AI edit.');
}
function updateUndoBtn() {
  const d = generatedDesigns[activeDesignIndex];
  const b = document.getElementById('btn-undo-ai');
  if (b) b.style.display = (d && d.history && d.history.length > 0) ? 'inline-flex' : 'none';
}

/* ══════════════════════════════════════════════════
   AI CHAT PANEL
═════════════════════════════════════════════════ */
function toggleMagicAi(forceOpen) {
  const panel = document.getElementById('magic-ai-panel');
  if (!panel) return;
  if (forceOpen === true) panel.classList.add('active');
  else panel.classList.toggle('active');
  if (panel.classList.contains('active')) {
    refreshAiTargetSelect();
    setTimeout(() => document.getElementById('refine-query')?.focus(), 200);
  }
}
/* ★ AI chat target: chat edits ALWAYS apply to the selected website */
function refreshAiTargetSelect() {
  const sel = document.getElementById('ai-target-select');
  if (!sel) return;
  const list = (generatedDesigns && generatedDesigns.length ? generatedDesigns : generatedConcepts) || [];
  sel.innerHTML = '';
  if (!list.length) {
    const o = document.createElement('option');
    o.value = '0'; o.textContent = 'No website yet — generate first';
    sel.appendChild(o);
    return;
  }
  list.forEach((c, i) => {
    const o = document.createElement('option');
    o.value = String(i);
    o.textContent = `${i + 1}. ${(c && c.name) || ('Variation ' + (i + 1))}`;
    if (i === activeDesignIndex) o.selected = true;
    sel.appendChild(o);
  });
}
function setAiTargetVariation(v) {
  const i = parseInt(v, 10);
  const list = (generatedDesigns && generatedDesigns.length ? generatedDesigns : generatedConcepts) || [];
  if (isNaN(i) || !list[i]) return;
  activeDesignIndex = i;
  [0, 1, 2].forEach(k => document.getElementById(`tab-d${k}`)?.classList.toggle('active', k === i));
  try { saveSessionNow(); } catch (e) {}
  const nm = list[i].name || ('Variation ' + (i + 1));
  showToast(`🎯 AI will edit "${nm}"`);
  if (document.getElementById('builder-screen')?.style.display === 'flex') {
    currentHtml = (generatedDesigns[i] && generatedDesigns[i].html) || currentHtml;
    currentViewMode = 'site';
    restoreIntoWorkspace();
  }
}
/* ★ AI chat model: gemini-3.5-flash-lite is the default (persisted) */
const AI_CHAT_DEFAULT_MODEL = 'gemini-3.5-flash-lite';
function getAiChatModel() {
  try {
    const sel = document.getElementById('ai-model-select');
    if (sel && sel.value) return sel.value;
    return localStorage.getItem('webcraft_ai_model') || AI_CHAT_DEFAULT_MODEL;
  } catch (e) { return AI_CHAT_DEFAULT_MODEL; }
}
function changeAiModel(model) {
  try { localStorage.setItem('webcraft_ai_model', model); } catch (e) {}
  if (String(model || '').includes('gemini')) {
    showToast(`AI Model set to ${model} (Google Gemini)`);
  } else {
    showToast(`AI Model set to ${model} (OpenCode Fallback)`);
  }
}
(function initAiChatModel() {
  const apply = () => {
    try {
      const saved = localStorage.getItem('webcraft_ai_model') || AI_CHAT_DEFAULT_MODEL;
      const sel = document.getElementById('ai-model-select');
      if (sel) {
        const has = Array.from(sel.options).some(o => o.value === saved);
        sel.value = has ? saved : AI_CHAT_DEFAULT_MODEL;
        if (!has) localStorage.setItem('webcraft_ai_model', AI_CHAT_DEFAULT_MODEL);
      }
    } catch (e) {}
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
  else apply();
})();
function getTimeStr() { return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
function appendUserChatMessage(msg) {
  const log = document.getElementById('ai-chat-log');
  if (!log) return;
  const row = document.createElement('div');
  row.className = 'msg user';
  row.innerHTML = `<div class="msg-avatar">You</div><div class="msg-body"><div class="msg-bubble">${escapeHtml(msg)}</div><div class="msg-meta">${getTimeStr()}</div></div>`;
  log.appendChild(row);
  log.scrollTop = log.scrollHeight;
}
function appendGeminiChatMessage(msg, buttonsHtml) {
  const log = document.getElementById('ai-chat-log');
  if (!log) return;
  const row = document.createElement('div');
  row.className = 'msg ai';
  row.innerHTML = `<div class="msg-avatar">✦</div><div class="msg-body"><div class="msg-bubble">${msg}${buttonsHtml || ''}</div><div class="msg-meta">AI · ${getTimeStr()}<button class="msg-copy" type="button" onclick="copyMsg(this)">Copy</button></div></div>`;
  log.appendChild(row);
  log.scrollTop = log.scrollHeight;
}
/* ★ Chat preview button: opens a FRESH preview of the just-edited result
   (reads currentHtml at click time, so it never shows stale content). */
function aiChatPreviewBtn() {
  return `<div style="margin-top:.55rem;"><button type="button" onclick="openAiChatPreview()" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;color:#fff;font-size:.72rem;font-weight:800;border-radius:999px;padding:.4rem 1rem;cursor:pointer;font-family:inherit;">👀 Preview this change</button></div>`;
}
/* ★ Real-URL chat preview (preview.php): behaves like the published site —
   header clicks scroll / 404 honestly instead of looping back into Builder.
   Falls back to the classic blob preview when the page is huge or offline. */
async function openAiChatPreview() {
  const html = String(currentHtml || '');
  if (html.length < 100) { showToast('⚠️ Nothing to preview yet'); return; }
  try {
    const ctrl = new AbortController();
    const timer = setTimeout(() => { try { ctrl.abort(); } catch (e) {} }, 15000);
    const res = await fetch(SITE_URL + '/preview.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ html: html.slice(0, 7 * 1024 * 1024) }),
      signal: ctrl.signal
    });
    clearTimeout(timer);
    const j = await res.json();
    if (j && j.success && j.url) { window.open(j.url, '_blank'); return; }
  } catch (e) { console.warn('[preview] URL preview failed, using blob fallback:', e?.message); }
  openInNewTab();
}
function copyMsg(btn) {
  const bubble = btn.closest('.msg-body')?.querySelector('.msg-bubble');
  if (!bubble) return;
  const tmp = document.createElement('textarea');
  tmp.value = bubble.innerText;
  document.body.appendChild(tmp); tmp.select();
  try { document.execCommand('copy'); showToast('📋 Copied'); } catch (e) {}
  tmp.remove();
}
function clearChatLog() {
  const log = document.getElementById('ai-chat-log');
  if (!log || !confirm('Clear conversation?')) return;
  log.innerHTML = `<div class="msg ai"><div class="msg-avatar">✦</div><div class="msg-body"><div class="msg-bubble">👋 Chat cleared. What should I build next?</div><div class="msg-meta">AI · just now</div></div></div>`;
}
function escapeHtml(s) { return s == null ? '' : String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function formatMarkdown(t) {
  if (!t) return '';
  let s = escapeHtml(t);
  s = s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  s = s.replace(/\*(.*?)\*/g, '<em>$1</em>');
  s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
  s = s.replace(/\n\n/g, '<br><br>').replace(/\n/g, '<br>');
  return s;
}
function quickRefine(t) {
  toggleMagicAi(true);
  document.getElementById('refine-query').value = t;
  setTimeout(executeRefine, 150);
}

/* ══════════════════════════════════════════════════
   VOICE
═════════════════════════════════════════════════ */
let voiceRecognition = null;
let isRecording = false;
function initVoiceRecognition() {
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) return null;
  const r = new SR();
  r.continuous = false; r.interimResults = true; r.maxAlternatives = 1;
  r.onstart = () => {
    isRecording = true;
    document.getElementById('btn-voice')?.classList.add('recording');
    document.getElementById('mic-icon').textContent = '⏹';
    showListeningToast(true);
  };
  r.onresult = (e) => {
    let interim = '', final = '';
    for (let i = e.resultIndex; i < e.results.length; i++) {
      const t = e.results[i][0].transcript;
      if (e.results[i].isFinal) final += t; else interim += t;
    }
    const input = document.getElementById('refine-query');
    if (input) input.value = (final || interim).trim();
    if (final) setTimeout(() => { stopVoiceInput(); executeRefine(); }, 350);
  };
  r.onerror = (e) => {
    if (e.error === 'not-allowed') showToast('🎤 Mic permission denied');
    else if (e.error === 'no-speech') showToast('🎤 No speech detected');
    else showToast('🎤 Voice error: ' + e.error);
    stopVoiceInput();
  };
  r.onend = () => stopVoiceInput();
  return r;
}
function toggleVoiceInput() {
  if (isRecording) return stopVoiceInput();
  if (!voiceRecognition) voiceRecognition = initVoiceRecognition();
  if (!voiceRecognition) { showToast('⚠️ Voice not supported. Use Chrome/Edge.'); return; }
  voiceRecognition.lang = document.getElementById('voice-lang').value || 'en-IN';
  try { voiceRecognition.start(); } catch (e) {}
}
function stopVoiceInput() {
  isRecording = false;
  document.getElementById('btn-voice')?.classList.remove('recording');
  const i = document.getElementById('mic-icon'); if (i) i.textContent = '🎤';
  showListeningToast(false);
  try { voiceRecognition?.stop(); } catch (e) {}
}
function showListeningToast(show) {
  let t = document.getElementById('voice-listening-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'voice-listening-toast';
    t.className = 'voice-listening-toast';
    const w = document.querySelector('.magic-input-wrap');
    if (w) w.appendChild(t);
  }
  t.innerHTML = '<span>Listening… speak now</span>';
  t.classList.toggle('show', show);
}

/* ══════════════════════════════════════════════════
   TANGLISH
═════════════════════════════════════════════════ */
const TANGLISH_HINTS = [
  [/\bmaathu|maathunga\b/i, 'change'],
  [/\bperiya|periyathu\b/i, 'make it bigger'],
  [/\bchinna|sinnathu\b/i, 'make it smaller'],
  [/\baakku|aakunga\b/i, 'make it'],
  [/\badd pannu|add pannunga\b/i, 'add'],
  [/\bremove pannu\b/i, 'remove'],
  [/\bkaattu|kaattunga\b/i, 'show'],
  [/\bazhaga|azhagana\b/i, 'beautiful'],
  [/\bkizha|keezha\b/i, 'at the bottom'],
  [/\bmela|mele\b/i, 'at the top'],
  [/\bvalathu\b/i, 'on the right'],
  [/\bidathu\b/i, 'on the left']
];
function enrichTanglishForAI(text) {
  let out = text;
  TANGLISH_HINTS.forEach(([rx, en]) => { if (rx.test(text)) out += ` [hint: ${en}]`; });
  return out;
}

/* ══════════════════════════════════════════════════
   REFINE
═════════════════════════════════════════════════ */
async function executeRefine() {
  const input = document.getElementById('refine-query');
  const query = input.value.trim();
  if (!query) return;
  refreshAiTargetSelect();
  const c = generatedDesigns[activeDesignIndex];
  if (!c) return;
  const isAdmin = currentViewMode === 'admin';
  const snapHtml = isAdmin ? c.adminHtml : currentHtml;
  const cName = c.name || `Variation ${activeDesignIndex + 1}`;

  appendUserChatMessage(query);
  const enriched = enrichTanglishForAI(query);
  input.value = '';
  const btn = document.getElementById('btn-refine');
  btn.disabled = true; btn.innerHTML = '⏳';
  // Admin refinements build REAL backend features in the preview env —
  // there is no mock admin HTML to patch anymore.
  if (isAdmin) {
    btn.disabled = false; btn.innerHTML = '➤';
    document.getElementById('ai-typing-indicator')?.remove();
    previewAddFeatureFlow(query);
    return;
  }

  const log = document.getElementById('ai-chat-log');
  let typing = document.createElement('div');
  typing.className = 'msg ai';
  typing.id = 'ai-typing-indicator';
  typing.innerHTML = '<div class="msg-avatar">✦</div><div class="msg-body"><div class="msg-bubble" style="display:flex;align-items:center;gap:.7rem;"><span class="wcl-mini-house" style="margin:0;"><i class="walls"></i><i class="roof"></i><i class="door"></i></span><span style="font-size:.82rem;color:#94a3b8;font-weight:600;">Crafting<span class="wcl-sub" style="display:inline;"></span></span></div></div>';
  log.appendChild(typing); log.scrollTop = log.scrollHeight;

  try {
    // AI EDIT CHAIN: OpenCode Zen free models, then server Gemini/smart-engine
    const bizName = document.getElementById('biz_name')?.value || 'Website';
    if (!window.OpenCodeAI?.editWithFallback) throw new Error('AI service not available');
    const res = await window.OpenCodeAI.editWithFallback({
      userPrompt: enriched, currentHtml: snapHtml, bizName, model: getAiChatModel()
    });
    document.getElementById('ai-typing-indicator')?.remove();
    appendGeminiChatMessage(formatMarkdown(res.conversation) + (res.isEdit && res.updatedHtml ? aiChatPreviewBtn() : ''));
    if (res.isEdit && res.updatedHtml) {
      pushHistory(activeDesignIndex, snapHtml);
      let n = res.updatedHtml;
      if (n.includes('<html') || n.includes('<!DOCTYPE')) currentHtml = n;
      else currentHtml = currentHtml.replace('</body>', `${n}\n</body>`);
      c.html = currentHtml;
      updateLiveIframe(currentHtml);
      updateUndoBtn();
      showToast('✨ Site updated! [' + (res.engine || 'AI') + (res.tasteAudit && typeof res.tasteAudit.score === 'number' ? ' · Taste ' + res.tasteAudit.score : '') + ']');
      saveSessionNow();
      const pb = document.getElementById('preview-container');
      if (pb) { pb.style.transition = 'box-shadow .3s ease'; pb.style.boxShadow = '0 0 35px rgba(99,102,241,.65)'; setTimeout(() => pb.style.boxShadow = '', 1200); }
    }
  } catch (err) {
    document.getElementById('ai-typing-indicator')?.remove();
    console.warn('[Refine error]:', err);
    appendGeminiChatMessage(`AI request failed: ${escapeHtml(err.message)} — try again or pick another free model.`);
  } finally {
    btn.disabled = false; btn.innerHTML = '➤';
  }
}
document.getElementById('refine-query')?.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); executeRefine(); }
});

/* ══════════════════════════════════════════════════
   STUDIO HANDOFF
═════════════════════════════════════════════════ */
/* STUDIO HANDOFF — removed: editing happens here in Builder + publish flow. */
function openStudioInNewTab() {
  const c = generatedDesigns[activeDesignIndex];
  if (!c) { showToast('⚠️ Select a variation first'); return; }
  saveSessionNow();
  // ★ Bridge for studio.php: it reads `webcraft_saved_project` in studio shape.
  // Scoped SESSION_KEY alone is invisible to Studio, so mirror the data.
  try {
    const bizName = document.getElementById('biz_name')?.value?.trim() || 'Website';
    const pub = getPublishedSession();
    const bridge = {
      bizName,
      activeDesignIndex,
      ownerEmail: ((window.__CUSTOMER__ && window.__CUSTOMER__.email) || 'guest').toLowerCase(),
      // ★ Carry publish linkage so Studio opens the REAL per-project admin.
      published: (pub && pub.adminUrl) ? pub : null,
      designs: (generatedDesigns || []).map(d => ({
        name: d.name || 'Concept',
        description: d.description || '',
        badge: d.badge || '',
        html: d.html || '',
        adminHtml: d.adminHtml || null
      }))
    };
    localStorage.setItem('webcraft_saved_project', JSON.stringify(bridge));
    // Also keep per-concept quick key (studio falls back to it)
    const pubBridge = getPublishedSession();
    localStorage.setItem('webcraft_studio_bridge', JSON.stringify({
      savedAt: Date.now(), bizName, activeDesignIndex,
      html: c.html || '', adminHtml: c.adminHtml || null,
      published: (pubBridge && pubBridge.adminUrl) ? pubBridge : null
    }));
  } catch (e) { console.warn('[studio bridge]', e); }
  window.open(SITE_URL + '/studio.php?concept=' + activeDesignIndex, '_blank');
  showToast('🎨 Studio opened');
}
/* Studio focus-sync: pick up edits saved by Studio in another tab. */
window.addEventListener('focus', () => {
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    if (!raw) return;
    const p = JSON.parse(raw);
    if (p?.designs?.[activeDesignIndex]) {
      const saved = p.designs[activeDesignIndex].html;
      if (saved && saved !== currentHtml && currentViewMode === 'site') {
        pushHistory(activeDesignIndex, currentHtml);
        currentHtml = saved;
        generatedDesigns = p.designs;
        updateLiveIframe(currentHtml);
        updateUndoBtn();
        showToast('🔄 Synced with Studio!');
      }
    }
  } catch (e) {}
});

/* ══════════════════════════════════════════════════
   ★ PUBLISH — REDIRECT TO publish.php
═════════════════════════════════════════════════ */
async function saveAndProceedToPayment() {
  // Save everything to localStorage first
  saveSessionNow();

  const c = generatedDesigns[activeDesignIndex];
  if (!c || !c.html) {
    showToast('⚠️ No design found. Please generate first.');
    return;
  }

  const bizName = document.getElementById('biz_name')?.value || 'My Website';

  // Extra safety — persist mode + requirements
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    if (raw) {
      const p = JSON.parse(raw);
      p.selectedGenMode = selectedGenMode;
      p.activeDesignIndex = activeDesignIndex;
      p.bizName = bizName;
      p.wizard = p.wizard || {};
      p.wizard.admin_requirements = document.getElementById('admin_requirements')?.value.trim() || p.wizard.admin_requirements || '';
      localStorage.setItem(SESSION_KEY, JSON.stringify(p));
    }
  } catch (e) {}

  // ★ Upload design HTML to server so publish.php can read it even if localStorage clears
  const btn = document.getElementById('btn-publish');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Saving…'; }
  try { if (window.Loader3D) Loader3D.show('Uploading your design…', 'Saving to server · preparing publish', 'rocket'); } catch (e) {}
  showToast('⬆️ Uploading design to server…');

  try {
    const resp = await fetch('<?= SITE_URL ?>/builder.php?action=save_design', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        html: c.html,
        biz_name: bizName,
        gen_mode: selectedGenMode,
        design_index: activeDesignIndex
      })
    });
    const j = await resp.json();

    if (j.success && j.token) {
      // Store draft token so publish.php can load the HTML from server
      try {
        const raw = localStorage.getItem(SESSION_KEY);
        if (raw) {
          const p = JSON.parse(raw);
          p.draftToken = j.token;
          localStorage.setItem(SESSION_KEY, JSON.stringify(p));
        }
      } catch (e) {}
      showToast('✓ Design saved! Opening publish wizard…');
    } else {
      // Even if upload fails, still proceed — publish.php falls back to localStorage
      console.warn('Draft upload failed:', j.error);
      showToast('✓ Proceeding to publish…');
    }
  } catch (err) {
    console.warn('Draft upload network error:', err);
    showToast('✓ Proceeding to publish…');
  }

  if (btn) { btn.disabled = false; btn.textContent = '🚀 Save & Publish'; }
  // Pre-publish gate: warn on failed critical checks (never auto-pass).
  // The owner can fix + retest in the preview, or publish anyway.
  try {
    const prevGate = (typeof getPreviewSession === 'function') ? getPreviewSession() : null;
    if (prevGate && prevGate.id) {
      try { if (window.Loader3D) Loader3D.text('Checking preview readiness…'); } catch (e) {}
      const rr = await fetch(SITE_URL + '/api/preview-env.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'readiness', draftId: prevGate.id })
      });
      const rj = await rr.json();
      if (rj && rj.success && !rj.ready) {
        const bad = (rj.checks || []).filter(c => c.critical && !c.pass)
          .map(c => '• ' + (c.label || c.key) + (c.detail ? ' — ' + c.detail : '')).join('\n');
        try { if (window.Loader3D) Loader3D.hide(); } catch (e) {}
        if (!confirm('Pre-publish checks are FAILING:\n' + bad + '\n\nPublish anyway? (Tip: fix + re-test in the preview first.)')) {
          showToast('Publish paused — fix the checks, then retry');
          return;
        }
      }
    }
  } catch (e) { console.warn('[readiness gate]', e); }
  try { if (window.Loader3D) Loader3D.text('✓ Ready! Opening publish…'); } catch (e) {}

  setTimeout(() => {
    window.location.href = '<?= SITE_URL ?>/publish.php';
  }, 800);
}

/* ══════════════════════════════════════════════════
   TOAST
═════════════════════════════════════════════════ */
function updateBuilderChrome() {
  try {
    const bs = document.getElementById('builder-screen');
    const isBuilder = !!(bs && (bs.style.display === 'flex'));
    document.body.classList.toggle('builder-focus', isBuilder);
    if (isBuilder) window.scrollTo(0, 0);
  } catch (e) {}
}
try {
  const _bsObs = document.getElementById('builder-screen');
  if (_bsObs && window.MutationObserver) {
    new MutationObserver(updateBuilderChrome).observe(_bsObs, { attributes: true, attributeFilter: ['style'] });
  }
  document.addEventListener('DOMContentLoaded', updateBuilderChrome);
  setTimeout(updateBuilderChrome, 800);
} catch (e) {}
let toastTimeout;
// showToast(text, ms, type) — ms defaults to 3500, auto-hides after time.
// type: 'info' | 'success' | 'error'. Old single-arg calls keep working.
function showToast(text, ms, type) {
  const t = document.getElementById('toast');
  const tt = document.getElementById('toast-text');
  if (!t || !tt) return;
  ms = (typeof ms === 'number' && ms > 0) ? ms : 3500;
  type = (type === 'success' || type === 'error') ? type : 'info';
  let bar = document.getElementById('toast-bar');
  if (!bar) { bar = document.createElement('div'); bar.id = 'toast-bar'; t.appendChild(bar); }
  t.className = 'toast show ' + type;
  tt.textContent = text;
  clearTimeout(toastTimeout);
  bar.style.transition = 'none';
  bar.style.width = '100%';
  void bar.offsetWidth; // restart countdown animation
  bar.style.transition = 'width ' + ms + 'ms linear';
  bar.style.width = '0%';
  toastTimeout = setTimeout(() => t.classList.remove('show'), ms);
}
</script>
<script>
/* Cache-heal guard (own block, survives main-script parse failure):
   if the main builder script didn't evaluate (stale cached copy),
   force one fresh reload so buttons work. Loop-guarded. */
(function () {
  try {
    if (typeof understandMyBusiness === 'undefined' || typeof showGenOverlay === 'undefined') {
      var k = 'wc_cache_healed_v2';
      try {
        if (!sessionStorage.getItem(k)) { sessionStorage.setItem(k, '1'); window.location.reload(); }
      } catch (e) { window.location.reload(); }
    } else {
      try { sessionStorage.removeItem('wc_cache_healed_v2'); } catch (e) {}
    }
  } catch (e) {}
})();
</script>

<!-- ★ WebCraft AI Suite — additive UX upgrades (brief, brand kit, health, versions, dashboard, publish gate). Reuses existing systems. -->
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/wc-ai-suite.css?v=<?= @filemtime(__DIR__ . '/assets/css/wc-ai-suite.css') ?: 1 ?>">
<script src="<?= SITE_URL ?>/assets/js/wc-ai-suite.js?v=<?= @filemtime(__DIR__ . '/assets/js/wc-ai-suite.js') ?: 1 ?>"></script>
<script src="<?= SITE_URL ?>/assets/js/wc-builder-suite.js?v=<?= @filemtime(__DIR__ . '/assets/js/wc-builder-suite.js') ?: 1 ?>"></script>

<!-- ★ 360° 3D AI Robot Background Scene Layer -->
<script src="<?= SITE_URL ?>/assets/js/three.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/wc-robot-3d.js?v=<?= @filemtime(__DIR__ . '/assets/js/wc-robot-3d.js') ?: 1 ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
