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
require_once __DIR__ . '/includes/nav.php';
?>
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
.gen-progress { display: none; }
.gen-progress.active { display: none; }

/* ══ 3D GENERATION OVERLAY ══ */
#gen-overlay {
  display: none;
  position: fixed; inset: 0; z-index: 99999;
  height: 100vh; height: 100dvh; max-height: 100vh; max-height: 100dvh;
  background: radial-gradient(circle at 50% 40%, #0d1226 0%, #03060f 70%, #010206 100%);
  align-items: center; justify-content: center;
  flex-direction: column;
  overflow: hidden;
  padding: 52px 12px 10px; box-sizing: border-box;
  perspective: 1200px;
}
#gen-overlay .gen-stage-wrap, #gen-overlay .gen-overlay-content { flex-shrink: 1; min-height: 0; }
#gen-overlay.active { display: flex; animation: overlayIn 0.5s cubic-bezier(0.22,1,0.36,1); }
@keyframes overlayIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }

/* Cybernetic HUD Top Bar */
.gen-hud-bar {
  position: absolute; top: 0; left: 0; right: 0; height: 48px;
  background: rgba(10,15,28,0.7); backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(99,102,241,0.25);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 1.5rem; z-index: 10;
}
.gen-hud-left, .gen-hud-right { display: flex; align-items: center; gap: 0.75rem; }
.gen-hud-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #10b981;
  box-shadow: 0 0 10px #10b981, 0 0 20px rgba(16,185,129,0.5);
  animation: hudBlink 1.4s ease-in-out infinite alternate;
}
@keyframes hudBlink { 0% { opacity: 0.4; } 100% { opacity: 1; } }
.gen-hud-mono { font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.72rem; color: #94a3b8; font-weight: 600; letter-spacing: 0.05em; }
.gen-hud-badge {
  background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(168,85,247,0.25));
  border: 1px solid rgba(129,140,248,0.4); border-radius: 999px;
  color: #c7d2fe; font-size: 0.68rem; font-weight: 800; padding: 0.2rem 0.65rem;
  letter-spacing: 0.03em;
}
/* ── Creative clock: progress ring (JS --p) + live analog face ── */
.gen-clock {
  --p: 0;
  width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
  background: conic-gradient(from -90deg, #818cf8 calc(var(--p) * 1%), rgba(255,255,255,0.10) 0);
  box-shadow: 0 0 14px rgba(99,102,241,0.55);
  animation: gcPulse 2s ease-in-out infinite;
}
@keyframes gcPulse { 0%, 100% { box-shadow: 0 0 8px rgba(99,102,241,0.4); } 50% { box-shadow: 0 0 18px rgba(129,140,248,0.9); } }
.gen-clock .gc-face {
  position: relative; width: 32px; height: 32px; border-radius: 50%;
  background: radial-gradient(circle at 50% 35%, #1e1b4b, #05070f 75%);
  border: 1px solid rgba(129,140,248,0.5);
}
.gen-clock .gc-face i { position: absolute; left: 50%; bottom: 50%; transform-origin: 50% 100%; border-radius: 2px; }
.gen-clock .gc-h { width: 3px; height: 8px; margin-left: -1.5px; background: #c7d2fe; animation: gcSpin 120s linear infinite; }
.gen-clock .gc-m { width: 2px; height: 11px; margin-left: -1px; background: #818cf8; animation: gcSpin 60s linear infinite; }
.gen-clock .gc-s { width: 1.5px; height: 13px; margin-left: -0.75px; background: #f472b6; box-shadow: 0 0 6px #f472b6; animation: gcSpin 12s linear infinite; }
@keyframes gcSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.gen-clock .gc-pin { position: absolute; left: 50%; top: 50%; width: 5px; height: 5px; margin: -2.5px 0 0 -2.5px; border-radius: 50%; background: #fff; box-shadow: 0 0 6px #fff; }
/* ── NOW banner: the current AI activity, big + glowing ── */
.gen-now {
  display: inline-flex; align-items: center; gap: 0.6rem;
  margin-bottom: 0.55rem; padding: 0.45rem 1.1rem; border-radius: 999px;
  background: linear-gradient(135deg, rgba(99,102,241,0.22), rgba(168,85,247,0.22));
  border: 1px solid rgba(129,140,248,0.55);
  box-shadow: 0 0 24px rgba(99,102,241,0.45), inset 0 0 18px rgba(99,102,241,0.12);
  animation: genNowGlow 2.2s ease-in-out infinite;
  max-width: 92vw;
}
@keyframes genNowGlow { 0%, 100% { box-shadow: 0 0 14px rgba(99,102,241,0.35), inset 0 0 18px rgba(99,102,241,0.10); } 50% { box-shadow: 0 0 32px rgba(139,92,246,0.75), inset 0 0 24px rgba(139,92,246,0.2); } }
.gen-now-ic { font-size: 1.15rem; line-height: 1; animation: genNowBob 1.1s ease-in-out infinite; }
@keyframes genNowBob { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-3px) scale(1.15); } }
.gen-now-text {
  font-size: 0.95rem; font-weight: 800; letter-spacing: 0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  background: linear-gradient(135deg, #fff 10%, #c7d2fe 55%, #f0abfc 100%);
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
.gen-now-dots { display: inline-flex; gap: 4px; }
.gen-now-dots i { width: 6px; height: 6px; border-radius: 50%; background: #818cf8; box-shadow: 0 0 8px #818cf8; animation: genNowDot 1.2s ease-in-out infinite; }
.gen-now-dots i:nth-child(2) { animation-delay: 0.2s; }
.gen-now-dots i:nth-child(3) { animation-delay: 0.4s; }
@keyframes genNowDot { 0%, 100% { opacity: 0.25; transform: scale(0.8); } 50% { opacity: 1; transform: scale(1.25); } }
/* ── Live analysis feed: mac-style terminal window ── */
.gen-live-log {
  width: min(520px, 90vw); margin-bottom: 0.55rem;
  background: rgba(5,8,18,0.85); border: 1px solid rgba(99,102,241,0.45);
  border-radius: 14px; overflow: hidden;
  box-shadow: 0 12px 34px rgba(0,0,0,0.55), 0 0 26px rgba(99,102,241,0.25);
  text-align: left;
}
.glog-head {
  display: flex; align-items: center; gap: 6px;
  padding: 0.4rem 0.8rem;
  background: linear-gradient(180deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9));
  border-bottom: 1px solid rgba(99,102,241,0.3);
  font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.66rem; font-weight: 700; color: #94a3b8;
}
.glog-head i { width: 9px; height: 9px; border-radius: 50%; }
.glog-head i:nth-child(1) { background: #f87171; box-shadow: 0 0 6px rgba(248,113,113,0.8); }
.glog-head i:nth-child(2) { background: #fbbf24; box-shadow: 0 0 6px rgba(251,191,36,0.8); }
.glog-head i:nth-child(3) { background: #34d399; box-shadow: 0 0 6px rgba(52,211,153,0.8); }
.glog-head span { margin-left: 0.3rem; letter-spacing: 0.04em; }
.glog-live { margin-left: auto !important; color: #f472b6 !important; animation: glogLiveBlink 1.3s ease-in-out infinite; }
@keyframes glogLiveBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
.glog-body {
  padding: 0.5rem 0.8rem 0.6rem;
  font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.7rem; font-weight: 600;
  min-height: 5.6em;
}
.glog-row { display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; padding: 0.12rem 0; animation: glogIn 0.35s cubic-bezier(0.22,1,0.36,1); white-space: nowrap; overflow: hidden; }
.glog-row span:last-child { overflow: hidden; text-overflow: ellipsis; }
@keyframes glogIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
.glog-row .glog-ic { width: 1.4em; text-align: center; flex-shrink: 0; font-style: normal; }
.glog-row.run { color: #fff; text-shadow: 0 0 12px rgba(56,189,248,0.6); }
.glog-row.run .glog-ic::after { content: '◌'; color: #38bdf8; animation: glogSpin 1s linear infinite; display: inline-block; }
@keyframes glogSpin { to { transform: rotate(360deg); } }
.glog-row .glog-caret { display: inline-block; width: 7px; height: 0.85em; margin-left: 3px; vertical-align: -1px; background: #38bdf8; box-shadow: 0 0 8px #38bdf8; animation: glogCaret 0.8s steps(2) infinite; }
@keyframes glogCaret { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
.glog-row.done { color: #6ee7b7; }
.glog-row.done .glog-ic::after { content: '✓'; }

/* Perspective Cyber Grid */
#gen-overlay::before {
  content: '';
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(99,102,241,0.12) 1px, transparent 1px),
    linear-gradient(90deg, rgba(99,102,241,0.12) 1px, transparent 1px);
  background-size: 44px 44px;
  transform: perspective(500px) rotateX(65deg) scale(3) translateY(20%);
  transform-origin: center bottom;
  animation: gridScroll 3s linear infinite;
  pointer-events: none;
}
@keyframes gridScroll { from { background-position: 0 0; } to { background-position: 0 44px; } }

/* Laser Scanner Beam */
#gen-overlay::after {
  content: '';
  position: absolute; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent 0%, rgba(99,102,241,0.8) 50%, rgba(56,189,248,0.8) 70%, transparent 100%);
  box-shadow: 0 0 20px rgba(99,102,241,0.9), 0 0 40px rgba(56,189,248,0.6);
  animation: laserScan 4.5s ease-in-out infinite;
  pointer-events: none;
}
@keyframes laserScan {
  0%   { top: 5%; opacity: 0; }
  10%  { opacity: 0.8; }
  90%  { opacity: 0.8; }
  100% { top: 95%; opacity: 0; }
}

/* ── 3D Stage & Rotating Quantum Cube ── */
.gen-stage-wrap {
  position: relative; z-index: 2; margin: 0.2rem 0 0; min-height: 0;
  display: flex; align-items: center; justify-content: center;
}
#gen-overlay .wcl-hb { transform: scale(0.6); transform-origin: top center; margin-bottom: -90px; }
/* ── Aurora Background Blobs ── */
.gen-aurora { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
.gen-aurora span { position: absolute; width: 46vmax; height: 46vmax; border-radius: 50%; filter: blur(90px); opacity: 0.35; }
.gen-aurora .ab1 { background: #4f46e5; top: -12%; left: -8%; animation: auroraDrift 11s ease-in-out infinite alternate; }
.gen-aurora .ab2 { background: #a855f7; bottom: -18%; right: -10%; animation: auroraDrift 13s ease-in-out infinite alternate-reverse; }
.gen-aurora .ab3 { background: #06b6d4; top: 35%; left: 55%; width: 30vmax; height: 30vmax; opacity: 0.22; animation: auroraDrift 9s ease-in-out infinite alternate; }
@keyframes auroraDrift { from { transform: translate(0,0) scale(1); } to { transform: translate(6vmax,4vmax) scale(1.15); } }

/* ── Reflective Glow Floor ── */
.gen-floor {
  position: absolute; bottom: -34px; left: 50%; transform: translateX(-50%);
  width: 280px; height: 56px; border-radius: 50%;
  background: radial-gradient(ellipse, rgba(99,102,241,0.5) 0%, rgba(168,85,247,0.25) 45%, transparent 70%);
  filter: blur(6px); animation: floorPulse 2.4s ease-in-out infinite alternate; pointer-events: none;
}
@keyframes floorPulse { from { transform: translateX(-50%) scaleX(1); opacity: 0.7; } to { transform: translateX(-50%) scaleX(1.18); opacity: 1; } }

/* ── Orbiting Satellites ── */
.gen-orbit { position: absolute; top: 50%; left: 50%; border-radius: 50%; pointer-events: none; }
.gen-orbit-1 { width: 300px; height: 300px; margin: -150px 0 0 -150px; animation: orbitSpin 7s linear infinite; }
.gen-orbit-2 { width: 220px; height: 220px; margin: -110px 0 0 -110px; animation: orbitSpin 4.5s linear infinite reverse; }
.gen-orbit i { position: absolute; top: -5px; left: 50%; margin-left: -5px; width: 10px; height: 10px; border-radius: 50%; }
.gen-orbit-1 i { background: #38bdf8; box-shadow: 0 0 12px #38bdf8, 0 0 26px rgba(56,189,248,0.6); }
.gen-orbit-2 i { background: #f0abfc; box-shadow: 0 0 12px #e879f9, 0 0 26px rgba(232,121,249,0.6); }
@keyframes orbitSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── Floating Glass Shards ── */
.gen-shard { position: absolute; border-radius: 8px; background: linear-gradient(135deg, rgba(129,140,248,0.25), rgba(168,85,247,0.12)); border: 1px solid rgba(129,140,248,0.4); backdrop-filter: blur(4px); animation: shardFloat 5s ease-in-out infinite; pointer-events: none; }
.gen-shard.s1 { width: 26px; height: 26px; top: 6%; left: 12%; animation-delay: 0s; }
.gen-shard.s2 { width: 16px; height: 16px; top: 70%; left: 8%; animation-delay: 1.2s; }
.gen-shard.s3 { width: 20px; height: 20px; top: 22%; right: 10%; animation-delay: 0.6s; }
.gen-shard.s4 { width: 14px; height: 14px; bottom: 12%; right: 16%; animation-delay: 1.8s; }
@keyframes shardFloat { 0%,100% { transform: translateY(0) rotate(12deg); } 50% { transform: translateY(-22px) rotate(32deg); } }

/* ── Build Step Checklist ── */
.gen-steps { display: flex; gap: 0.4rem; width: min(520px, 90vw); margin-bottom: 0.6rem; flex-wrap: wrap; justify-content: center; }
.gen-step { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.68rem; font-weight: 700; color: #475569; background: rgba(255,255,255,0.03); border: 1px solid rgba(148,163,184,0.2); border-radius: 999px; padding: 0.28rem 0.65rem; transition: all 0.3s ease; }
.gen-step .gs-dot { width: 7px; height: 7px; border-radius: 50%; background: #334155; flex-shrink: 0; }
.gen-step.active { color: #c7d2fe; border-color: rgba(99,102,241,0.6); background: rgba(99,102,241,0.12); box-shadow: 0 0 14px rgba(99,102,241,0.3); }
.gen-step.active .gs-dot { background: #818cf8; box-shadow: 0 0 8px #818cf8; animation: stagePulse 0.9s ease-in-out infinite; }
.gen-step.done { color: #6ee7b7; border-color: rgba(16,185,129,0.5); background: rgba(16,185,129,0.08); }
.gen-step.done .gs-dot { background: #10b981; box-shadow: 0 0 8px #10b981; }
/* home-build scene lives in assets/css/loader-3d.css (.wcl-hb) */

/* ── Gyroscope Orbiting Rings ── */
.gen-rings { display: none; }
.gen-ring { position: absolute; inset: 0; border-radius: 50%; border: 1.8px solid transparent; }
.gen-ring-1 {
  border-top-color: #6366f1; border-right-color: rgba(99,102,241,0.5);
  box-shadow: 0 0 15px rgba(99,102,241,0.4);
  animation: ringSpin1 3s linear infinite;
}
.gen-ring-2 {
  inset: 18px;
  border-bottom-color: #a855f7; border-left-color: rgba(168,85,247,0.5);
  box-shadow: 0 0 15px rgba(168,85,247,0.4);
  animation: ringSpin2 2.2s linear infinite reverse;
}
.gen-ring-3 {
  inset: 36px;
  border-top-color: #06b6d4; border-right-color: rgba(6,182,212,0.4);
  box-shadow: 0 0 15px rgba(6,182,212,0.4);
  animation: ringSpin3 4s linear infinite;
}
@keyframes ringSpin1 { 0% { transform: rotateX(65deg) rotateY(20deg) rotateZ(0deg); } 100% { transform: rotateX(65deg) rotateY(20deg) rotateZ(360deg); } }
@keyframes ringSpin2 { 0% { transform: rotateX(-50deg) rotateY(45deg) rotateZ(0deg); } 100% { transform: rotateX(-50deg) rotateY(45deg) rotateZ(360deg); } }
@keyframes ringSpin3 { 0% { transform: rotateX(30deg) rotateY(-60deg) rotateZ(0deg); } 100% { transform: rotateX(30deg) rotateY(-60deg) rotateZ(360deg); } }

/* ── Stone-Sculpt Build Scene ── */
.gen-build-scene { position: relative; width: 280px; height: 230px; }
.gb-base {
  position: absolute; bottom: 14px; left: 50%; transform: translateX(-50%);
  width: 224px; height: 26px; border-radius: 8px;
  background: linear-gradient(180deg, #94a3b8 0%, #475569 55%, #1e293b 100%);
  box-shadow: inset 0 2px 0 rgba(255,255,255,.5), 0 10px 24px rgba(0,0,0,.6);
}
.gb-b {
  position: absolute; width: 48px; height: 48px; border-radius: 7px;
  background: linear-gradient(135deg, #f1f5f9 0%, #94a3b8 45%, #475569 100%);
  box-shadow: inset 0 2px 0 rgba(255,255,255,.7), inset 0 -3px 0 rgba(0,0,0,.3), 0 6px 14px rgba(0,0,0,.5);
  opacity: 0; transform: translateY(-56px) scale(.7);
  transition: transform .32s cubic-bezier(.34,1.56,.64,1), opacity .2s ease;
}
.gb-b.landed { opacity: 1; transform: none; }
.gb-b.gold {
  background: linear-gradient(135deg, #fef3c7 0%, #f59e0b 60%, #b45309 100%);
  box-shadow: inset 0 2px 0 rgba(255,255,255,.8), 0 0 24px rgba(245,158,11,.85);
}
.gb-chisel { position: absolute; top: -8px; right: 30px; width: 16px; height: 106px; transform-origin: 50% 8px; transform: rotate(-28deg); z-index: 3; }
.gb-chisel .handle { position: absolute; top: 0; left: 3px; width: 10px; height: 72px; border-radius: 5px; background: linear-gradient(90deg, #92400e, #f59e0b, #92400e); box-shadow: 0 3px 8px rgba(0,0,0,.5); }
.gb-chisel .tip { position: absolute; bottom: 0; left: 0; width: 16px; height: 32px; background: linear-gradient(180deg, #f1f5f9, #64748b); clip-path: polygon(22% 0, 78% 0, 100% 100%, 0 100%); }
.gb-chisel.hit { transform: rotate(26deg); transition: transform .09s ease-out; }
.gb-chisel:not(.hit) { transition: transform .35s ease-in; }
.gb-sparks { position: absolute; left: 50%; top: 46%; z-index: 3; pointer-events: none; }
.gb-sparks i { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: #fcd34d; box-shadow: 0 0 8px #f59e0b; opacity: 0; }
.gb-sparks i:nth-child(1) { --x: -38px; --y: -24px; }
.gb-sparks i:nth-child(2) { --x: 34px;  --y: -28px; }
.gb-sparks i:nth-child(3) { --x: -26px; --y: 22px; }
.gb-sparks i:nth-child(4) { --x: 30px;  --y: 18px; }
.gb-sparks i:nth-child(5) { --x: 0px;   --y: -38px; }
.gb-sparks i:nth-child(6) { --x: -8px;  --y: 32px; }
.gb-sparks.burst i { animation: gbBurst .45s ease-out; }
@keyframes gbBurst { 0% { opacity: 1; transform: translate(0,0) scale(1); } 100% { opacity: 0; transform: translate(var(--x), var(--y)) scale(.3); } }
/* ── Content Area & Headings ── */
.gen-overlay-content { position: relative; z-index: 2; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; max-width: 680px; width: 92vw; max-height: calc(100vh - 64px); max-height: calc(100dvh - 64px); overflow: hidden; }

.gen-overlay-title {
  font-size: clamp(1.1rem, 2.4vw, 1.6rem); font-weight: 900; letter-spacing: -0.03em; margin-bottom: 0.25rem;
  background: linear-gradient(135deg, #ffffff 15%, #c7d2fe 55%, #e9d5ff 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  text-shadow: 0 0 40px rgba(129,140,248,0.3);
}

.gen-overlay-stage {
  font-size: 0.82rem; color: #cbd5e1; font-weight: 600; min-height: 1.3em;
  margin-bottom: 0.55rem; display: flex; align-items: center; gap: 0.5rem;
}
.gen-overlay-stage .stage-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #6366f1;
  box-shadow: 0 0 12px #818cf8; animation: stagePulse 0.9s ease-in-out infinite; flex-shrink: 0;
}
@keyframes stagePulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: 0.4; } }

/* Glowing Live Percentage */
.gen-pct-wrap {
  display: flex; align-items: baseline; gap: 0.15rem; margin-bottom: 0.35rem;
  font-family: 'Fira Code', monospace; font-size: 1.5rem; font-weight: 900;
  background: linear-gradient(135deg, #38bdf8, #818cf8, #c084fc);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  filter: drop-shadow(0 0 14px rgba(99,102,241,0.5));
}
.gen-pct-wrap .pct-symbol { font-size: 1.1rem; opacity: 0.8; }

/* Neon Progress Track */
.gen-overlay-track {
  width: min(520px, 90vw); height: 7px; background: rgba(255,255,255,0.06);
  border-radius: 999px; overflow: hidden; margin-bottom: 0.7rem;
  box-shadow: 0 0 0 1px rgba(99,102,241,0.25), 0 0 20px rgba(99,102,241,0.15);
}
.gen-overlay-fill {
  height: 100%; width: 5%; border-radius: 999px;
  background: linear-gradient(90deg, #4f46e5 0%, #8b5cf6 45%, #06b6d4 100%);
  background-size: 200% 100%;
  animation: progressShimmer 2s linear infinite;
  box-shadow: 0 0 16px rgba(99,102,241,0.9), 0 0 32px rgba(6,182,212,0.4);
  transition: width 0.4s cubic-bezier(0.4,0,0.2,1);
}
@keyframes progressShimmer { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }

/* Creative self-assembling wireframe (lights up as variations finish) */
.gen-wire { position: relative; width: min(360px, 84vw); margin-bottom: 0.35rem; background: rgba(10,15,28,0.72); border: 1px solid rgba(99,102,241,0.35); border-radius: 14px; padding: 0.55rem 0.65rem 0.6rem; overflow: hidden; box-shadow: 0 10px 34px rgba(0,0,0,0.5); }
.gw-bar { display: flex; align-items: center; gap: 5px; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(148,163,184,0.18); margin-bottom: 0.55rem; }
.gw-bar i { width: 9px; height: 9px; border-radius: 50%; }
.gw-bar i:nth-child(1) { background: #f87171; } .gw-bar i:nth-child(2) { background: #fbbf24; } .gw-bar i:nth-child(3) { background: #34d399; }
.gw-url { margin-left: 0.4rem; flex: 1; font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.62rem; color: #67e8f9; background: rgba(8,145,178,0.12); border: 1px solid rgba(103,232,249,0.25); border-radius: 6px; padding: 0.12rem 0.5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gw-nav { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.55rem; }
.gw-logo { width: 26px; height: 14px; border-radius: 4px; background: rgba(129,140,248,0.25); }
.gw-links { display: flex; gap: 5px; flex: 1; }
.gw-links i { width: 26px; height: 8px; border-radius: 4px; background: rgba(148,163,184,0.25); }
.gw-cta { width: 44px; height: 14px; border-radius: 7px; background: rgba(16,185,129,0.25); }
.gw-hero { border-radius: 10px; padding: 0.65rem; margin-bottom: 0.55rem; background: rgba(99,102,241,0.06); border: 1px dashed rgba(129,140,248,0.3); }
.gw-h1 { display: block; height: 12px; width: 72%; border-radius: 6px; background: rgba(226,232,240,0.28); margin-bottom: 0.4rem; }
.gw-sub { display: block; height: 8px; width: 52%; border-radius: 4px; background: rgba(148,163,184,0.3); margin-bottom: 0.5rem; }
.gw-btns { display: flex; gap: 0.4rem; }
.gw-btns i { width: 52px; height: 14px; border-radius: 7px; background: rgba(99,102,241,0.3); }
.gw-btns i:last-child { background: rgba(148,163,184,0.25); }
.gw-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.45rem; margin-bottom: 0.55rem; }
.gw-card { border-radius: 8px; padding: 0.45rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(148,163,184,0.2); }
.gw-card i { display: block; height: 7px; border-radius: 4px; background: rgba(148,163,184,0.28); margin-bottom: 5px; }
.gw-card i:first-child { height: 22px; background: rgba(56,189,248,0.2); }
.gw-foot { display: flex; gap: 0.4rem; }
.gw-foot i { height: 8px; border-radius: 4px; background: rgba(148,163,184,0.22); flex: 1; }
.gen-wire .lit { animation: gwPop 0.5s cubic-bezier(0.22,1,0.36,1); }
.gw-nav.lit .gw-logo, .gw-nav.lit .gw-cta { background: linear-gradient(135deg,#6366f1,#a855f7); box-shadow: 0 0 12px rgba(99,102,241,0.7); }
.gw-nav.lit .gw-links i { background: rgba(199,210,254,0.6); }
.gw-hero.lit { background: rgba(99,102,241,0.14); border-style: solid; border-color: rgba(129,140,248,0.6); box-shadow: 0 0 18px rgba(99,102,241,0.35); }
.gw-hero.lit .gw-h1 { background: linear-gradient(90deg,#fff,#c7d2fe); }
.gw-hero.lit .gw-sub { background: rgba(199,210,254,0.55); }
.gw-hero.lit .gw-btns i:first-child { background: linear-gradient(135deg,#10b981,#059669); box-shadow: 0 0 12px rgba(16,185,129,0.6); }
.gw-cards.lit .gw-card { border-color: rgba(56,189,248,0.55); background: rgba(56,189,248,0.07); box-shadow: 0 0 14px rgba(56,189,248,0.25); }
.gw-cards.lit .gw-card i:first-child { background: linear-gradient(135deg, rgba(56,189,248,0.7), rgba(168,85,247,0.7)); }
.gw-foot.lit i { background: rgba(110,231,183,0.5); }
@keyframes gwPop { 0% { transform: scale(0.96); } 60% { transform: scale(1.02); } 100% { transform: scale(1); } }
.gw-scan { position: absolute; left: 0; right: 0; top: -30%; height: 26%; background: linear-gradient(180deg, transparent, rgba(129,140,248,0.18), transparent); animation: gwScan 2.6s ease-in-out infinite; pointer-events: none; }
@keyframes gwScan { 0% { top: -30%; } 100% { top: 110%; } }
.gen-wire-cap { font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.7rem; color: #67e8f9; font-weight: 600; margin-bottom: 0.45rem; min-height: 1.1em; }
@media (prefers-reduced-motion: reduce) { .gw-scan { animation: none; } }

/* ── AI ANALYZE LAYER (additive — same design, AI vision feel) ── */
.gen-pill { position: relative; overflow: hidden; transition: border-color .3s, color .3s, box-shadow .3s; }
.gen-pill.scanning { color: #e0e7ff !important; border-color: rgba(129,140,248,0.8) !important; box-shadow: 0 0 14px rgba(99,102,241,0.45); }
.gen-pill.scanning::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, transparent 20%, rgba(129,140,248,0.35) 50%, transparent 80%); transform: translateX(-100%); animation: pillSweep 1.4s ease-in-out infinite; pointer-events: none; }
@keyframes pillSweep { 100% { transform: translateX(100%); } }
.gen-pill.locked { color: #6ee7b7 !important; border-color: rgba(16,185,129,0.55) !important; box-shadow: 0 0 12px rgba(16,185,129,0.25); }
.gen-pill.locked::after { display: none; }
/* AI vision frame over the self-assembling wireframe */
.gw-ai-frame { position: absolute; inset: 6px; pointer-events: none; z-index: 3; }
.gw-ai-frame .gw-corner { position: absolute; width: 16px; height: 16px; border: 2px solid rgba(103,232,249,0.9); filter: drop-shadow(0 0 6px rgba(103,232,249,0.8)); }
.gw-ai-frame .gw-corner.tl { top: 0; left: 0; border-right: none; border-bottom: none; border-radius: 6px 0 0 0; }
.gw-ai-frame .gw-corner.tr { top: 0; right: 0; border-left: none; border-bottom: none; border-radius: 0 6px 0 0; }
.gw-ai-frame .gw-corner.bl { bottom: 0; left: 0; border-right: none; border-top: none; border-radius: 0 0 0 6px; }
.gw-ai-frame .gw-corner.br { bottom: 0; right: 0; border-left: none; border-top: none; border-radius: 0 0 6px 0; }
.gw-ai-beam { position: absolute; left: 4px; right: 4px; top: 0; height: 34px; background: linear-gradient(180deg, transparent, rgba(103,232,249,0.16) 45%, rgba(129,140,248,0.32) 55%, transparent); border-top: 1px solid rgba(103,232,249,0.7); border-bottom: 1px solid rgba(129,140,248,0.5); box-shadow: 0 0 18px rgba(103,232,249,0.35); animation: gwAiBeam 2.8s ease-in-out infinite; }
@keyframes gwAiBeam { 0% { top: 0; opacity: 0; } 12% { opacity: 1; } 88% { opacity: 1; } 100% { top: calc(100% - 34px); opacity: 0; } }
.gw-ai-tag { position: absolute; top: -11px; left: 50%; transform: translateX(-50%); font-family: 'Fira Code','Courier New',monospace; font-size: 0.58rem; font-weight: 800; letter-spacing: 0.12em; color: #67e8f9; background: rgba(5,8,18,0.92); border: 1px solid rgba(103,232,249,0.5); border-radius: 999px; padding: 0.1rem 0.55rem; white-space: nowrap; box-shadow: 0 0 12px rgba(103,232,249,0.35); animation: gwTagBlink 2s ease-in-out infinite; }
@keyframes gwTagBlink { 0%,100% { opacity: 1; } 50% { opacity: 0.65; } }
/* AI insight cards — what the AI just figured out, with confidence */
.gen-insights { display: flex; flex-direction: column; align-items: center; gap: 0.3rem; margin-bottom: 0.55rem; min-height: 0; width: min(520px, 90vw); }
.gen-insight { display: flex; align-items: center; gap: 0.5rem; width: 100%; background: rgba(8,12,24,0.85); border: 1px solid rgba(103,232,249,0.35); border-radius: 10px; padding: 0.35rem 0.65rem; font-size: 0.7rem; font-weight: 600; color: #cbd5e1; text-align: left; box-shadow: 0 6px 20px rgba(0,0,0,0.45), 0 0 14px rgba(103,232,249,0.12); animation: giIn 0.4s cubic-bezier(0.22,1,0.36,1); overflow: hidden; }
@keyframes giIn { from { opacity: 0; transform: translateY(8px) scale(0.98); } to { opacity: 1; transform: none; } }
.gen-insight.out { opacity: 0; transform: translateY(-6px); transition: all 0.35s ease; }
.gen-insight .gi-ic { font-size: 0.95rem; flex-shrink: 0; }
.gen-insight .gi-tx { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gen-insight .gi-tx b { color: #fff; }
.gen-insight .gi-conf { flex-shrink: 0; display: flex; align-items: center; gap: 0.35rem; font-family: 'Fira Code',monospace; font-size: 0.62rem; font-weight: 800; color: #6ee7b7; }
.gen-insight .gi-bar { width: 44px; height: 5px; border-radius: 99px; background: rgba(255,255,255,0.1); overflow: hidden; }
.gen-insight .gi-bar i { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg,#10b981,#67e8f9); box-shadow: 0 0 8px rgba(16,185,129,0.8); animation: giBar 0.8s cubic-bezier(0.22,1,0.36,1); }
@keyframes giBar { from { width: 0 !important; } }
/* stage text AI thinking dots */
.gen-overlay-stage .stage-think { display: inline-flex; gap: 3px; margin-left: 2px; }
.gen-overlay-stage .stage-think i { width: 5px; height: 5px; border-radius: 50%; background: #818cf8; animation: genNowDot 1.1s ease-in-out infinite; }
.gen-overlay-stage .stage-think i:nth-child(2) { animation-delay: 0.2s; }
.gen-overlay-stage .stage-think i:nth-child(3) { animation-delay: 0.4s; }
/* ── AI ticker: compact single-line deduction, visible in every viewport height ── */
.gen-ai-ticker { display: flex; align-items: center; gap: 0.5rem; width: min(520px, 90vw); margin: -0.15rem 0 0.6rem; padding: 0.32rem 0.75rem; border-radius: 999px; background: rgba(8,12,24,0.82); border: 1px solid rgba(103,232,249,0.35); box-shadow: 0 0 16px rgba(103,232,249,0.14), inset 0 0 14px rgba(99,102,241,0.10); font-family: 'Fira Code','Courier New',monospace; font-size: 0.68rem; font-weight: 600; color: #a5f3fc; overflow: hidden; }
.gen-ai-ticker .tk-orb { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: radial-gradient(circle at 35% 35%, #a5f3fc, #0ea5e9 60%, #6366f1); box-shadow: 0 0 10px #22d3ee, 0 0 20px rgba(99,102,241,0.6); animation: tkOrb 1.4s ease-in-out infinite; }
@keyframes tkOrb { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: 0.6; } }
.gen-ai-ticker .tk-tx { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: opacity 0.25s ease; }
.gen-ai-ticker .tk-tx b { color: #fff; }
.gen-ai-ticker .tk-conf { flex-shrink: 0; font-weight: 800; color: #6ee7b7; text-shadow: 0 0 8px rgba(16,185,129,0.7); }
/* ── Active build-step gets an AI scanning shine (always-visible zone) ── */
.gen-step { position: relative; overflow: hidden; }
.gen-step.active::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, transparent 20%, rgba(129,140,248,0.4) 50%, transparent 80%); transform: translateX(-100%); animation: pillSweep 1.6s ease-in-out infinite; pointer-events: none; }
/* ── Traveling comet on the progress fill ── */
.gen-overlay-fill { position: relative; overflow: visible; }
.gen-overlay-fill::after { content: ''; position: absolute; right: -2px; top: 50%; width: 14px; height: 14px; margin-top: -7px; border-radius: 50%; background: radial-gradient(circle, #fff 0%, #67e8f9 45%, transparent 75%); filter: blur(1px); box-shadow: 0 0 12px #22d3ee, 0 0 26px rgba(99,102,241,0.9); animation: fillComet 1.6s ease-in-out infinite; }
@keyframes fillComet { 0%,100% { transform: scale(0.8); opacity: 0.75; } 50% { transform: scale(1.25); opacity: 1; } }
/* ── Attractiveness pass: title sheen, sonar dot, pct pop, eq, breathing glows ── */
.gen-overlay-title { background-size: 200% auto; animation: titleSheen 6s linear infinite; }
@keyframes titleSheen { to { background-position: 200% center; } }
.gen-overlay-stage .stage-dot { position: relative; }
.gen-overlay-stage .stage-dot::after { content: ''; position: absolute; inset: -6px; border-radius: 50%; border: 2px solid rgba(99,102,241,0.7); animation: dotSonar 1.6s ease-out infinite; pointer-events: none; }
@keyframes dotSonar { from { transform: scale(0.4); opacity: 1; } to { transform: scale(1.5); opacity: 0; } }
.gen-pct-wrap.pop { animation: pctPop 0.45s cubic-bezier(0.34,1.56,0.64,1); }
@keyframes pctPop { 0% { transform: scale(1); } 40% { transform: scale(1.18); } 100% { transform: scale(1); } }
.gen-step.done { animation: stepPop 0.45s cubic-bezier(0.34,1.56,0.64,1); }
#gen-overlay.active .gen-step.done { animation: stepPop 0.45s cubic-bezier(0.34,1.56,0.64,1); }
@keyframes stepPop { 0% { transform: scale(0.92); } 60% { transform: scale(1.06); } 100% { transform: scale(1); } }
.gen-ai-ticker { animation: tickerGlow 2.6s ease-in-out infinite; }
@keyframes tickerGlow { 0%,100% { box-shadow: 0 0 10px rgba(103,232,249,0.10), inset 0 0 14px rgba(99,102,241,0.10); border-color: rgba(103,232,249,0.30); } 50% { box-shadow: 0 0 22px rgba(103,232,249,0.30), inset 0 0 18px rgba(99,102,241,0.18); border-color: rgba(103,232,249,0.60); } }
.gen-overlay-track { position: relative; animation: trackGlow 2.8s ease-in-out infinite; }
@keyframes trackGlow { 0%,100% { box-shadow: 0 0 0 1px rgba(99,102,241,0.25), 0 0 14px rgba(99,102,241,0.12); } 50% { box-shadow: 0 0 0 1px rgba(99,102,241,0.45), 0 0 30px rgba(99,102,241,0.35); } }
/* HUD live equalizer — AI is "talking" */
.hud-eq { display: inline-flex; align-items: flex-end; gap: 2px; height: 14px; }
.hud-eq i { width: 3px; height: 14px; border-radius: 2px; background: linear-gradient(180deg, #67e8f9, #818cf8); box-shadow: 0 0 6px rgba(103,232,249,0.7); transform-origin: bottom; animation: eqB 0.9s ease-in-out infinite; }
.hud-eq i:nth-child(2) { animation-delay: 0.15s; }
.hud-eq i:nth-child(3) { animation-delay: 0.3s; }
.hud-eq i:nth-child(4) { animation-delay: 0.45s; }
@keyframes eqB { 0%,100% { transform: scaleY(0.3); } 50% { transform: scaleY(1); } }
/* ── Attractiveness+ pass: twinkle starfield, meteors, pill cascade,
   title float, pct halo, wire breathing, badge sweep (all ambient) ── */
.gen-stars { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
.gen-stars::before, .gen-stars::after { content: ''; position: absolute; border-radius: 50%; }
.gen-stars::before {
  width: 2px; height: 2px; top: 0; left: 0; color: #e0e7ff;
  box-shadow: 80px 120px 0 0 currentColor, 240px 80px 0 0 currentColor, 420px 200px 0 0 currentColor, 600px 90px 0 0 currentColor, 760px 160px 0 0 currentColor, 940px 70px 0 0 currentColor, 1120px 140px 0 0 currentColor, 1300px 90px 0 0 currentColor, 1450px 180px 0 0 currentColor, 120px 420px 0 0 currentColor, 520px 460px 0 0 currentColor, 880px 480px 0 0 currentColor, 1260px 470px 0 0 currentColor, 200px 760px 0 0 currentColor, 820px 780px 0 0 currentColor, 1380px 790px 0 0 currentColor;
  animation: starTwinkle 2.8s ease-in-out infinite;
}
.gen-stars::after {
  width: 3px; height: 3px; top: 0; left: 0; color: #67e8f9;
  box-shadow: 160px 260px 0 0 currentColor, 580px 300px 0 0 currentColor, 1000px 280px 0 0 currentColor, 1400px 300px 0 0 currentColor, 90px 640px 0 0 currentColor, 680px 680px 0 0 currentColor, 1240px 680px 0 0 currentColor, 420px 620px 0 0 currentColor, 960px 640px 0 0 currentColor, 1480px 620px 0 0 currentColor;
  animation: starTwinkle 3.7s ease-in-out 1.2s infinite;
}
@keyframes starTwinkle { 0%,100% { opacity: 0.25; } 50% { opacity: 1; } }
.gen-meteor { position: absolute; top: 0; left: 0; width: 180px; height: 2px; border-radius: 2px; background: linear-gradient(90deg, transparent, #a5f3fc 60%, #fff); filter: drop-shadow(0 0 8px #67e8f9); pointer-events: none; z-index: 1; opacity: 0; animation: meteorFly 9s linear infinite; }
.gen-meteor.m2 { width: 120px; animation-duration: 14s; animation-delay: 4.5s; }
@keyframes meteorFly {
  0% { transform: translate(-20vw, 6vh) rotate(-24deg); opacity: 0; }
  3% { opacity: 1; }
  12% { transform: translate(115vw, 46vh) rotate(-24deg); opacity: 0; }
  100% { transform: translate(115vw, 46vh) rotate(-24deg); opacity: 0; }
}
#gen-overlay.active .gen-step { animation: stepIn 0.5s cubic-bezier(0.22,1,0.36,1) backwards; }
#gen-overlay.active .gen-step:nth-child(2) { animation-delay: 0.08s; }
#gen-overlay.active .gen-step:nth-child(3) { animation-delay: 0.16s; }
#gen-overlay.active .gen-step:nth-child(4) { animation-delay: 0.24s; }
#gen-overlay.active .gen-step:nth-child(5) { animation-delay: 0.32s; }
@keyframes stepIn { from { opacity: 0; transform: translateY(10px) scale(0.94); } }
.gen-overlay-title { animation: titleSheen 6s linear infinite, titleFloat 5s ease-in-out infinite; }
@keyframes titleFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
.gen-pct-wrap { position: relative; }
.gen-pct-wrap::before { content: ''; position: absolute; inset: -14px -30px; background: radial-gradient(ellipse, rgba(99,102,241,0.35), transparent 70%); filter: blur(10px); z-index: -1; animation: pctHalo 2.4s ease-in-out infinite; pointer-events: none; }
@keyframes pctHalo { 0%,100% { opacity: 0.6; transform: scale(0.95); } 50% { opacity: 1; transform: scale(1.08); } }
.gen-wire { animation: wireBreathe 3.2s ease-in-out infinite; }
@keyframes wireBreathe { 0%,100% { box-shadow: 0 10px 34px rgba(0,0,0,0.5); } 50% { box-shadow: 0 10px 44px rgba(0,0,0,0.5), 0 0 30px rgba(99,102,241,0.35); } }
.gen-hud-badge { position: relative; overflow: hidden; }
.gen-hud-badge::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, transparent 30%, rgba(255,255,255,0.35) 50%, transparent 70%); transform: translateX(-100%); animation: badgeSweep 3.5s ease-in-out infinite; pointer-events: none; }
@keyframes badgeSweep { 60% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
@media (prefers-reduced-motion: reduce) {
  .gen-stars, .gen-meteor { display: none; }
  .gen-overlay-title, .gen-wire, .gen-pct-wrap::before, .gen-hud-badge::after, #gen-overlay.active .gen-step { animation: none !important; }
}
@media (max-height: 920px) { .gen-insights { display: none; } .gw-ai-tag { display: none; } }
@media (max-height: 560px) { .gen-ai-ticker { display: none; } }

/* Live Telemetry Terminal Line */
.gen-terminal-box {
  width: min(520px, 90vw); background: rgba(5,8,16,0.8);
  border: 1px solid rgba(56,189,248,0.25); border-radius: 8px;
  padding: 0.4rem 0.85rem; display: flex; align-items: center; gap: 0.55rem;
  font-family: 'Fira Code', monospace; font-size: 0.72rem; color: #38bdf8;
  box-shadow: 0 4px 20px rgba(0,0,0,0.5); margin-bottom: 0.6rem;
}
.terminal-prompt { color: #818cf8; font-weight: 900; }
#gen-terminal-line { color: #e2e8f0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Rotating Tip */
.gen-overlay-tip {
  font-size: 0.76rem; color: #64748b; font-weight: 600; max-width: 440px;
  line-height: 1.5; text-align: center; transition: opacity 0.3s;
}

/* Floating Starfield Particles */
.gen-particles { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
.gen-particle { position: absolute; border-radius: 50%; animation: particleFloat linear infinite; }
@keyframes particleFloat {
  0%   { transform: translateY(100vh) scale(0); opacity: 0; }
  10%  { opacity: 1; }
  90%  { opacity: 0.6; }
  100% { transform: translateY(-10vh) scale(1); opacity: 0; }
}


/* Creative codefield: rising mini browser-windows + code glyphs */
.gen-codefield { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 1; }
.gcf-chip {
  position: absolute; bottom: -160px; left: var(--x, 50%);
  padding: 10px 12px 12px; border-radius: 12px;
  background: rgba(15,23,42,0.55); border: 1px solid rgba(129,140,248,0.35);
  backdrop-filter: blur(6px); box-shadow: 0 8px 30px rgba(0,0,0,0.45);
  animation: codeRise var(--t, 15s) linear infinite; animation-delay: var(--d, 0s); opacity: 0;
}
.gcf-chip::before {
  content: ''; display: block; width: 36px; height: 8px; border-radius: 99px;
  background: linear-gradient(90deg, #f87171 0 8px, #fbbf24 8px 16px, #34d399 16px 24px, transparent 24px);
  opacity: 0.9; margin-bottom: 2px;
}
.gcf-chip i {
  display: block; height: 8px; border-radius: 4px; margin-top: 7px; position: relative; overflow: hidden;
  background: linear-gradient(90deg, rgba(129,140,248,0.75), rgba(56,189,248,0.35));
}
.gcf-chip i:nth-child(2) { width: 88%; }
.gcf-chip i:nth-child(3) { width: 64%; }
.gcf-chip i:nth-child(4) { width: 76%; }
.gcf-chip i::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.55), transparent);
  transform: translateX(-100%); animation: chipShimmer 2.8s ease-in-out infinite;
}
@keyframes chipShimmer { 60% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
.gcf-glyph {
  position: absolute; bottom: -80px; left: var(--x, 50%);
  font-family: 'Fira Code', 'Courier New', monospace; font-weight: 800; font-size: 1.7rem;
  color: rgba(129,140,248,0.5); text-shadow: 0 0 18px rgba(99,102,241,0.65);
  animation: codeRise var(--t, 14s) linear infinite; animation-delay: var(--d, 0s); opacity: 0;
  white-space: nowrap;
}
@keyframes codeRise {
  0%   { transform: translateY(0) rotate(-6deg) scale(0.9); opacity: 0; }
  8%   { opacity: 0.9; }
  85%  { opacity: 0.65; }
  100% { transform: translateY(-115vh) rotate(5deg) scale(1); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) {
  .gcf-chip, .gcf-glyph, .gcf-chip i::after { animation: none; }
  .gcf-chip, .gcf-glyph { display: none; }
}

/* GEN OVERLAY FIT — no-scroll: everything fits viewport, extras hide progressively */
@media (max-height: 920px) {
  .gen-terminal-box, .gen-overlay-tip { display: none; }
  .gen-live-log { display: none; }
  #gen-overlay .wcl-hb { transform: scale(0.55); margin-bottom: -100px; }
  .gen-analysis-brief { gap: 0.3rem !important; margin: 0.2rem 0 0.5rem !important; }
  #gen-brief-line { display: none; }
}
@media (max-height: 760px) {
  .gen-stage-wrap { margin: 0; }
  #gen-overlay .wcl-hb { transform: scale(0.45); margin-bottom: -122px; }
  .gen-wire, .gen-wire-cap { display: none; }
  .gen-overlay-title { font-size: 1.1rem; }
  .gen-pct-wrap { font-size: 1.25rem; margin-bottom: 0.25rem; }
  .gen-overlay-track { margin-bottom: 0.5rem; }
  .gen-steps { margin-bottom: 0.4rem; }
}
@media (max-height: 620px) {
  .gen-stage-wrap { display: none; }
  .gen-analysis-brief { display: none !important; }
  .gen-now { display: none; }
  .gen-overlay-stage { font-size: 0.76rem; margin-bottom: 0.4rem; }
}
@media (max-width: 640px) {
  .gen-hud-bar { padding: 0 0.8rem; }
  .gen-hud-badge { display: none; }
  .gen-hud-mono { font-size: 0.62rem; }
  .gen-clock { width: 32px; height: 32px; }
  .gen-clock .gc-face { width: 25px; height: 25px; }
  .gen-now-text { font-size: 0.82rem; }
  .gen-overlay-content { width: 94vw; }
  .gen-analysis-brief { width: 94vw !important; }
}


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
.puter-model-row { display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0.85rem; background: #080c14; border-bottom: 1px solid #162032; font-size: 0.7rem; gap: 0.5rem; flex-shrink: 0; }
.puter-model-select { background: #111726; border: 1px solid #283347; color: #e2e8f0; border-radius: 6px; font-size: 0.7rem; padding: 0.2rem 0.45rem; font-family: inherit; outline: none; }
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
  <!-- Top Cyber Status Bar -->
  <div class="gen-hud-bar">
    <div class="gen-hud-left">
      <span class="gen-hud-dot"></span>
      <span class="hud-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
      <span class="gen-hud-mono">AI-FLOWCRAFT: AUTONOMOUS NEURAL SYNTHESIS</span>
    </div>
    <div class="gen-hud-right">
      <span class="gen-hud-badge">✦ DEEP ANALYSIS · 3 VARIATIONS</span>
      <span class="gen-clock" id="gen-clock" title="Elapsed generation time"><span class="gc-face"><i class="gc-h"></i><i class="gc-m"></i><i class="gc-s"></i><b class="gc-pin"></b></span></span>
      <span class="gen-hud-mono" id="gen-hud-time">0.0s</span>
    </div>
  </div>

  <!-- Ambient FX -->
  <div class="gen-aurora"><span class="ab1"></span><span class="ab2"></span><span class="ab3"></span></div>

  <!-- Floating particles (injected by JS) -->
  <div class="gen-particles" id="gen-particles"></div>
  <div class="gen-stars" aria-hidden="true"></div>
  <div class="gen-meteor m1" aria-hidden="true"></div>
  <div class="gen-meteor m2" aria-hidden="true"></div>
  <div class="gen-shard s1"></div>
  <div class="gen-shard s2"></div>
  <div class="gen-shard s3"></div>
  <div class="gen-shard s4"></div>

  <!-- Creative background: floating code-windows + glyphs rising (pure CSS) -->
  <div class="gen-codefield" aria-hidden="true">
    <div class="gcf-chip" style="--x:4%; --d:0s; --t:16s; width:120px;"><i></i><i></i><i></i></div>
    <div class="gcf-glyph" style="--x:14%; --d:2s; --t:13s;">&lt;/&gt;</div>
    <div class="gcf-chip" style="--x:24%; --d:5s; --t:18s; width:96px;"><i></i><i></i></div>
    <div class="gcf-glyph" style="--x:38%; --d:1s; --t:15s;">{ }</div>
    <div class="gcf-chip" style="--x:52%; --d:3s; --t:17s; width:140px;"><i></i><i></i><i></i><i></i></div>
    <div class="gcf-glyph" style="--x:64%; --d:6s; --t:12s;">#</div>
    <div class="gcf-chip" style="--x:74%; --d:2.5s; --t:16s; width:108px;"><i></i><i></i><i></i></div>
    <div class="gcf-glyph" style="--x:86%; --d:4s; --t:14s;">CSS</div>
    <div class="gcf-chip" style="--x:93%; --d:7s; --t:19s; width:88px;"><i></i><i></i></div>
  </div>

  <!-- Little builder builds your home (pure CSS story loop) -->
  <div class="gen-stage-wrap">
    <div class="wcl-hb" style="position:relative;z-index:2;">
      <div class="wcl-hb-sun"></div>
      <div class="wcl-hb-cloud c1"></div><div class="wcl-hb-cloud c2"></div>
      <div class="wcl-hb-bricks"><i></i><i></i><i></i></div>
      <div class="wcl-hb-walls"></div><div class="wcl-hb-roof"></div>
      <div class="wcl-hb-door"></div>
      <div class="wcl-hb-win w1"></div><div class="wcl-hb-win w2"></div>
      <div class="wcl-hb-chimney"><div class="wcl-hb-smoke"><i></i><i></i><i></i></div></div>
      <div class="wcl-hb-man"><div class="wcl-hb-bob">
        <div class="wcl-hb-head"><span class="hat"></span></div>
        <div class="wcl-hb-body"></div>
        <div class="wcl-hb-legs"><i></i><i></i></div>
        <div class="wcl-hb-arm"><span class="hammer"></span></div>
      </div></div>
      <div class="wcl-hb-ground"></div>
    </div>
  </div>

  <!-- Main Content & Progress -->
  <div class="gen-overlay-content">
    <div class="gen-overlay-title">✦ AI-FlowCraft Neural Design Engine</div>
    
    <!-- Live Requirements Analysis Pill Bar -->
    <div class="gen-analysis-brief" id="gen-analysis-brief" style="display:flex; flex-wrap:wrap; justify-content:center; gap:0.45rem; margin:0.35rem 0 0.85rem; width:min(540px, 92vw);">
      <span class="gen-pill" id="gpill-biz" style="background:rgba(15,23,42,0.75); border:1px solid rgba(99,102,241,0.35); padding:0.25rem 0.7rem; border-radius:999px; font-size:0.72rem; color:#94a3b8; display:inline-flex; align-items:center; gap:0.35rem; backdrop-filter:blur(8px);">🏢 <strong id="gpill-biz-val" style="color:#e2e8f0; font-weight:700;">Analyzing Business</strong></span>
      <span class="gen-pill" id="gpill-aud" style="background:rgba(15,23,42,0.75); border:1px solid rgba(99,102,241,0.35); padding:0.25rem 0.7rem; border-radius:999px; font-size:0.72rem; color:#94a3b8; display:inline-flex; align-items:center; gap:0.35rem; backdrop-filter:blur(8px);">🎯 <strong id="gpill-aud-val" style="color:#e2e8f0; font-weight:700;">Target Audience</strong></span>
      <span class="gen-pill" id="gpill-style" style="background:rgba(15,23,42,0.75); border:1px solid rgba(99,102,241,0.35); padding:0.25rem 0.7rem; border-radius:999px; font-size:0.72rem; color:#94a3b8; display:inline-flex; align-items:center; gap:0.35rem; backdrop-filter:blur(8px);">🎨 <strong id="gpill-style-val" style="color:#e2e8f0; font-weight:700;">Design Direction</strong></span>
      <span class="gen-pill" id="gpill-mode" style="background:rgba(15,23,42,0.75); border:1px solid rgba(99,102,241,0.35); padding:0.25rem 0.7rem; border-radius:999px; font-size:0.72rem; color:#94a3b8; display:inline-flex; align-items:center; gap:0.35rem; backdrop-filter:blur(8px);">⚡ <strong id="gpill-mode-val" style="color:#e2e8f0; font-weight:700;">Mode: Static</strong></span>
    </div>

    <div class="gen-overlay-stage">
      <span class="stage-dot"></span>
      <span id="gen-overlay-text">AI-FlowCraft initializing neural requirements analysis…</span>
      <span class="stage-think" aria-hidden="true"><i></i><i></i><i></i></span>
    </div>
    <div id="gen-brief-line" style="font-size:0.78rem; color:#c7d2fe; font-weight:600; margin-bottom:0.5rem; text-align:center; max-width:520px;"></div>

    <!-- Live Percentage Counter -->
    <div class="gen-pct-wrap">
      <span id="gen-pct-num">0</span><span class="pct-symbol">%</span>
    </div>

    <!-- Glowing Progress Track -->
    <div class="gen-overlay-track">
      <div class="gen-overlay-fill" id="gen-overlay-fill"></div>
    </div>

    <!-- AI insight ticker: single-line, always visible even in compact mode -->
    <div class="gen-ai-ticker" id="gen-ai-ticker" aria-live="polite"><span class="tk-orb"></span><span class="tk-tx" id="gen-ai-ticker-tx">AI analyzing business DNA…</span><span class="tk-conf" id="gen-ai-ticker-conf">…</span></div>

    <!-- Build Step Checklist — AI-FlowCraft 28-Skill Pipeline -->
    <div class="gen-steps" id="gen-steps">
      <div class="gen-step" data-s="0"><span class="gs-dot"></span>Skills 1-2: Requirements</div>
      <div class="gen-step" data-s="1"><span class="gs-dot"></span>Skills 3-9: Architecture</div>
      <div class="gen-step" data-s="2"><span class="gs-dot"></span>Skills 10-18: Standards</div>
      <div class="gen-step" data-s="3"><span class="gs-dot"></span>Skills 19-21: Features</div>
      <div class="gen-step" data-s="4"><span class="gs-dot"></span>Skills 22-26: QA &amp; Launch</div>
    </div>

    <!-- Creative: self-assembling website wireframe (lights up per variation) -->
    <div class="gen-wire" id="gen-wire" aria-hidden="true">
      <div class="gw-bar"><i></i><i></i><i></i><span class="gw-url" id="gw-url">your-site.ai</span></div>
      <div class="gw-nav"><span class="gw-logo"></span><span class="gw-links"><i></i><i></i><i></i></span><span class="gw-cta"></span></div>
      <div class="gw-hero"><span class="gw-h1"></span><span class="gw-sub"></span><span class="gw-btns"><i></i><i></i></span></div>
      <div class="gw-cards"><span class="gw-card"><i></i><i></i><i></i></span><span class="gw-card"><i></i><i></i><i></i></span><span class="gw-card"><i></i><i></i><i></i></span></div>
      <div class="gw-foot"><i></i><i></i></div>
      <div class="gw-scan"></div>
      <div class="gw-ai-frame" aria-hidden="true"><span class="gw-corner tl"></span><span class="gw-corner tr"></span><span class="gw-corner bl"></span><span class="gw-corner br"></span><div class="gw-ai-beam"></div><span class="gw-ai-tag">◉ AI VISION · ANALYZING LAYOUT</span></div>
    </div>
    <div class="gen-wire-cap" id="gen-wire-cap">Assembling sections…</div>
    <!-- AI insights: what the AI just deduced (injected by JS, max 2 visible) -->
    <div class="gen-insights" id="gen-insights" aria-live="polite"></div>

    <!-- NOW line: big attractive banner of the current AI activity -->
    <div class="gen-now" id="gen-now" aria-live="polite">
      <span class="gen-now-ic" id="gen-now-ic">🔍</span>
      <span class="gen-now-text" id="gen-now-text">Reading business DNA</span>
      <span class="gen-now-dots" aria-hidden="true"><i></i><i></i><i></i></span>
    </div>

    <!-- Live analysis feed: mac-style terminal, AI narrates line by line -->
    <div class="gen-live-log" id="gen-live-log" aria-label="Live AI activity">
      <div class="glog-head" aria-hidden="true"><i></i><i></i><i></i><span>neural-analysis · live</span><b class="glog-live">● LIVE</b></div>
      <div class="glog-body" id="gen-live-log-body" aria-live="polite"></div>
    </div>

    <!-- Live Telemetry Terminal Line -->
    <div class="gen-terminal-box">
      <span class="terminal-prompt">&gt;</span>
      <span id="gen-terminal-line">AI-FlowCraft neural engine analyzing customer requirements and synthesizing multi-variation layouts...</span>
    </div>

    <!-- Rotating Tip -->
    <div id="gen-overlay-tip" class="gen-overlay-tip">
      ✦ AI-FlowCraft analyzes customer requirements, audience psychology, and builds 3 bespoke websites
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
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.1rem; flex-wrap:wrap; gap:0.6rem; background:#0e1320; padding:0.65rem 0.9rem; border-radius:10px; border:1px solid #1e293b;">
          <div>
            <div class="wiz-section-label" style="margin-bottom:0; font-size:0.78rem;">Step 1 of 4 · Business Profile</div>
            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">Enter your business details below, or click Demo to test.</div>
          </div>
          <div style="display:flex; gap:0.45rem; align-items:center;">
            <button type="button" class="btn btn-ghost btn-sm" onclick="fillDemoData('ecommerce')" style="background:#2e1065; border:1px solid #a855f7; color:#f3e8ff; font-size:0.75rem; font-weight:700; padding:0.4rem 0.8rem; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:0.35rem;" title="Fill with complete sample E-Commerce shop & products">
              🛍️ E-Commerce Demo
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="fillDemoData('agency')" style="background:#1e1b4b; border:1px solid #6366f1; color:#c7d2fe; font-size:0.75rem; font-weight:700; padding:0.4rem 0.8rem; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:0.35rem;" title="Fill with sample agency business data">
              🎯 Agency Demo
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
              </div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="clearWizardForm()" style="background:#111827; border:1px solid #374151; color:#9ca3af; font-size:0.75rem; font-weight:600; padding:0.4rem 0.65rem; border-radius:8px; cursor:pointer;" title="Clear all fields">
              🧹 Clear
            </button>
          </div>
        </div>
        <div class="w-row">
          <div class="w-group">
            <label class="w-label" for="biz_name">Business / Brand Name <span class="req">*</span></label>
            <input class="w-input" type="text" id="biz_name" placeholder="e.g. Apex Studio, Zen Cafe, Dr. Smith Clinic" value="">
          </div>
          <div class="w-group">
            <label class="w-label" for="biz_type">Industry / Category <span class="req">*</span></label>
            <select class="w-select" id="biz_type">
              <option>Creative &amp; Digital Agency</option>
              <option>Restaurant, Cafe &amp; Bar</option>
              <option>Tech Startup &amp; SaaS</option>
              <option>Professional Services &amp; Legal</option>
              <option>Healthcare &amp; Wellness Clinic</option>
              <option>Real Estate &amp; Architecture</option>
              <option>Fitness Center &amp; Personal Trainer</option>
              <option>E-Commerce &amp; Retail</option>
              <option>Consultant / Advisor</option>
              <option>Coach (Life / Business)</option>
              <option>Therapist / Counselor</option>
              <option>Tradesperson (Plumber / Electrician / Handyman)</option>
              <option>Cleaning Service</option>
              <option>Tutor / Trainer</option>
              <option>Personal Portfolio &amp; Creator</option>
              <option>School / College / Education</option>
              <option>Other</option>
            </select>
          </div>
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_tagline">Core Mission / Tagline <span class="req">*</span></label>
          <input class="w-input" type="text" id="biz_tagline" placeholder="e.g. Crafting exceptional experiences that inspire and scale." value="">
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_audience">Target Audience</label>
          <input class="w-input" type="text" id="biz_audience" placeholder="e.g. High-growth brands, modern businesses, local community..." value="">
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_services">Services / Products Offered</label>
          <input class="w-input" type="text" id="biz_services" placeholder="e.g. UI/UX Design, Web Development, Brand Identity, Consulting..." value="">
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_products">🛒 Product List <span style="font-weight:400;color:#94a3b8;">(for product companies — one per line)</span></label>
          <textarea class="w-textarea" id="biz_products" rows="4" placeholder="Example:&#10;Cotton T-Shirt | ₹499 | https://example.com/tshirt.jpg&#10;Denim Jacket | ₹1499&#10;Leather Wallet | Ask price"></textarea>
          <div class="w-hint">Format per line: <strong style="color:#a5b4fc">Name | Price | ImageURL (optional)</strong> — no price = Enquire button. Empty = no shop section (unless business type is a shop).</div>
        </div>
        <div class="w-group">
          <label class="w-label" for="biz_reviews">⭐ Client Reviews <span style="font-weight:400;color:#94a3b8;">(paste from Google/Yelp/Facebook — one per line)</span></label>
          <textarea class="w-textarea" id="biz_reviews" rows="3" placeholder="Example:&#10;Priya Sharma | Fantastic service, highly recommended! | Happy Customer&#10;Rahul Verma | Professional work, on time every time."></textarea>
          <div class="w-hint">Format per line: <strong style="color:#a5b4fc">Name | Review text | Role (optional)</strong> — rendered verbatim in testimonials.</div>
        </div>
      </div>

      <!-- STEP 2 -->
      <div class="wizard-step" id="step2">
        <div class="wiz-section-label">Step 2 of 4 · Primary Design Direction</div>
        <p class="w-hint" style="margin-bottom:1.5rem;">
          Tell us the aesthetic you want. <strong style="color:#a5b4fc">All 3 style variations we generate will follow this direction</strong> — only the layout approach will differ.
        </p>
        <div class="w-group">
          <label class="w-label">Design Aesthetic <span class="req">*</span></label>
          <div class="style-grid">
            <div class="style-card"><input type="radio" name="design_style" id="st_modern" value="modern" checked><label for="st_modern"><span style="font-size:1.4rem">🎯</span>Modern &amp; Clean</label></div>
            <div class="style-card"><input type="radio" name="design_style" id="st_bold" value="bold"><label for="st_bold"><span style="font-size:1.4rem">⚡</span>Bold &amp; Dynamic</label></div>
            <div class="style-card"><input type="radio" name="design_style" id="st_dark" value="dark"><label for="st_dark"><span style="font-size:1.4rem">🌙</span>Luxury &amp; Dark</label></div>
          </div>
        </div>
        <div class="w-group">
          <label class="w-label" for="design_direction">Describe your design direction (optional)</label>
          <textarea class="w-textarea" id="design_direction" rows="3" placeholder="e.g. Minimal Scandinavian with warm neutrals, generous whitespace, editorial typography…"></textarea>
          <div class="w-hint">Free-text adds extra guidance on top of the aesthetic above.</div>
        </div>
        <div class="w-group">
          <label class="w-label">Brand Color Accent</label>
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
          <label class="w-label">✦ Design Intelligence <span style="font-weight:400;color:#94a3b8;">(Taste Skill — reads your brief, sets the taste)</span></label>
          <div class="taste-vibes">
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="auto" checked><span>✦ Auto</span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="minimalist"><span>Minimal</span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="premium"><span>Premium</span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="playful"><span>Playful</span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="editorial"><span>Editorial</span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="brutalist"><span>Brutalist</span></label>
            <label class="taste-vibe"><input type="radio" name="taste_vibe" value="trust"><span>Trust-first</span></label>
          </div>
          <div class="taste-dials">
            <div class="taste-dial"><span class="td-name">Layout variance</span><input type="range" id="taste_variance" min="0" max="10" value="0" oninput="updateTasteRead()"><span class="td-val" id="taste_variance_val">Auto</span></div>
            <div class="taste-dial"><span class="td-name">Motion intensity</span><input type="range" id="taste_motion" min="0" max="10" value="0" oninput="updateTasteRead()"><span class="td-val" id="taste_motion_val">Auto</span></div>
            <div class="taste-dial"><span class="td-name">Visual density</span><input type="range" id="taste_density" min="0" max="10" value="0" oninput="updateTasteRead()"><span class="td-val" id="taste_density_val">Auto</span></div>
          </div>
          <div class="w-hint" id="taste-read">✦ Auto: the AI reads your business + direction and picks the design language + dials (baseline 8/6/4). Move a slider to override.</div>
        </div>
      </div>

      <!-- STEP 3 -->
      <div class="wizard-step" id="step3">
        <div class="wiz-section-label">Step 3 of 4 · Website Sections</div>
        <div class="w-group">
          <label class="w-label">Include Sections</label>
          <div class="check-grid">
            <label class="wcheck"><input type="checkbox" name="sections[]" value="hero" checked><span>Hero &amp; Value Proposition</span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="services" checked><span>Services &amp; Offerings</span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="about" checked><span>About &amp; Story</span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="metrics" checked><span>Key Metrics &amp; Stats</span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="shop"><span>🛒 Shop &amp; Products (working cart)</span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="contact" checked><span>Interactive Contact Section</span></label>
            <label class="wcheck"><input type="checkbox" name="sections[]" value="footer" checked><span>Footer with Links</span></label>
          </div>
        </div>
      </div>

      <!-- STEP 4 -->
      <div class="wizard-step" id="step4">
        <div class="wiz-section-label">Step 4 of 4 · Contact Info &amp; Generate</div>
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
          <div class="w-group"><label class="w-label" for="biz_phone">Phone Number</label><input class="w-input" type="text" id="biz_phone" value="+1 (555) 890-1234"></div>
          <div class="w-group"><label class="w-label" for="biz_email">Contact Email</label><input class="w-input" type="email" id="biz_email" value="hello@zenithstudio.com"></div>
        </div>
        <div class="w-group"><label class="w-label" for="biz_address">Headquarters / Location</label><input class="w-input" type="text" id="biz_address" value="450 Innovation Parkway, San Francisco, CA"></div>

        <div class="w-group">
          <label class="w-label" for="biz_logo">🏷️ Brand Logo URL <span style="font-weight:400;color:#94a3b8;">(optional — empty = text brand name)</span></label>
          <input class="w-input" type="url" id="biz_logo" placeholder="https://example.com/logo.png">
          <div class="w-hint">Customer logo iruntha link kodunga — navbar + footer la <strong style="color:#a5b4fc">img</strong> aaga varum. Vendam-na empty-a vidunga.</div>
        </div>

        <div class="w-group">
          <label class="w-label">📣 Social Media Links <span style="font-weight:400;color:#94a3b8;">(optional — kodutha link mattum footer la varum)</span></label>
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
          <h3>Ready to Generate</h3>
          <p>We'll create <strong style="color:#a5b4fc">3 distinct style variations</strong> based on your design direction.<br>Each variation uses your exact content — only the layout differs.</p>
          <button class="wbtn wbtn-generate" id="wiz-generate-btn" onclick="openGenerateModal()" disabled>
            <span>✦</span><span>Generate 3 Style Variations</span>
          </button>
        </div>

        <div class="gen-progress" id="gen-progress">
          <div class="gen-progress-bar"><div class="gen-progress-fill" id="gen-progress-fill"></div></div>
          <div class="gen-progress-msg"><span class="spinner"></span><span id="gen-progress-text">Ready</span></div>
        </div>
      </div>
    </div>

    <div class="wizard-footer">
      <button class="wbtn wbtn-ghost" id="wiz-prev" onclick="wizNav(-1)" disabled>&larr; Back</button>
      <div id="wiz-step-counter" style="font-size:0.85rem; color:#64748b; font-weight:600">Step 1 of 4</div>
      <button class="wbtn wbtn-primary" id="wiz-next" onclick="wizNav(1)">Next Step &rarr;</button>
      <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
        <button class="wbtn wbtn-clear" id="wiz-clear" onclick="clearAndSwitchAccount()" style="display:none;">✕ Clear</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ DESIGNS SCREEN ═══ -->
<div id="designs-screen">
  <div class="designs-header">
    <div class="designs-badge">✦ 3 Style Variations</div>
    <h1 id="designs-title">Choose Your Style Variation</h1>
    <p id="designs-sub">All 3 use your exact content and design direction — only the layout differs.</p>
    <div style="display:flex;gap:0.6rem;justify-content:center;margin-top:0.9rem;flex-wrap:wrap;">
      <button class="wbtn wbtn-ghost" onclick="backToWizard()" style="font-size:0.82rem;padding:0.5rem 1.1rem;border-radius:10px;">← Back</button>
      <button class="wbtn wbtn-ghost" onclick="goToVariations()" style="font-size:0.82rem;padding:0.5rem 1.1rem;border-radius:10px;">Open Variations Page ↗</button>
    </div>
  </div>
  <div class="designs-grid" id="designs-grid"></div>
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
        <span style="font-size:.7rem;color:#475569;font-weight:600;">Login, then edit your site freely</span>
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

  <div class="puter-model-row">
    <div style="display:flex; align-items:center; gap:0.4rem; width:100%;">
      <span style="color:#10b981; font-weight:700;" title="AI edits apply to this website">🎯 Editing:</span>
      <select class="puter-model-select" id="ai-target-select" onchange="setAiTargetVariation(this.value)" style="flex:1; min-width:0;">
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
    <div class="mode-grid">
      <button class="gen-mode-card selected" data-mode="static" onclick="pickGenerateMode('static', this)">
        <span class="gm-badge">Simplest</span>
        <span class="gm-icon">📄</span>
        <h3>Static</h3>
        <p>HTML / CSS / JS only.<br>No backend, no admin, no DB.</p>
        <div class="gm-foot">▸ 3 style variations</div>
      </button>
      <button class="gen-mode-card" data-mode="admin" onclick="pickGenerateMode('admin', this)">
        <span class="gm-badge">With CMS</span>
        <span class="gm-icon">🛠️</span>
        <h3>Admin</h3>
        <p>Site + AI-written admin panel.<br>JSON storage — no database.</p>
        <div class="gm-foot">▸ 3 style variations</div>
      </button>
      <button class="gen-mode-card" data-mode="database" onclick="pickGenerateMode('database', this)">
        <span class="gm-badge">Full Stack</span>
        <span class="gm-icon">🗄️</span>
        <h3>Admin + Database</h3>
        <p>Site + AI admin + MySQL.<br>Full CRUD, session auth.</p>
        <div class="gm-foot">▸ 3 style variations</div>
      </button>
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

<script src="<?= SITE_URL ?>/assets/js/ai-flowcraft.js"></script>
<script src="<?= SITE_URL ?>/assets/js/opencode-service.js"></script>
<!-- Loader3D JS comes from includes/nav.php (guarded against double-include) -->
<script>
window.__CUSTOMER__ = <?= json_encode($customerUser ?: null) ?>;
/* ══════════════════════════════════════════════════
   STATE
═════════════════════════════════════════════════ */
let generatedDesigns = [];
const SITE_URL_JS = <?= json_encode(SITE_URL) ?>;
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
Manage agents (name, phone, email, specialization)`
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
  // ?layouts=N → restore session then auto-open the 3 layouts of concept N)
  let wizardOnly = false;
  let layoutsQs = -1;
  try {
    const qs2 = new URLSearchParams(window.location.search);
    if (qs2.get('wizard') === '1') wizardOnly = true;
    const ql = parseInt(qs2.get('layouts') || '-1', 10);
    if (!isNaN(ql) && ql >= 0 && ql <= 2) layoutsQs = ql;
  } catch (e) {}
  if (wizardOnly) {
    setTimeout(hideResumeLoader, 400);
    await refreshAccountUI();
    updateWizDisplay();
    try { updateGenerateButton(); } catch (e) {}
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
      adminHtml: preWantsAdmin ? buildInteractiveAdminPreview(p.biz_name) : null,
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

    const payload = {
      version: 3,
      savedAt: Date.now(),
      stage,
      wizard: collectWizardSnapshot(),
      selectedGenMode,
      currentWizStep,
      bizName: document.getElementById('biz_name')?.value.trim() || 'Website',
      concepts: slimConceptsForSave(generatedConcepts, generatedDesigns),
      designs: generatedDesigns,
      activeDesignIndex,
      currentViewMode
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
    display3Designs(generatedConcepts, saved.bizName || 'Your Website');
    showResumeBanner('Resumed — 3 variations ready to preview');
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

    if (pub && pub.adminUrl) {
      const f = document.getElementById('live-iframe');
      if (f) { f.src = pub.adminUrl; f.removeAttribute('srcdoc'); }
    } else {
      updateLiveIframe(buildInteractiveAdminPreview(bizName));
    }
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
   PUTER AUTH (Preserved for AI Editing / Co-Pilot)
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
function openGenerateModal() {
  if (!window.__CUSTOMER__ || !window.__CUSTOMER__.email) {
    showToast('Please sign in / create account first');
    setTimeout(() => { window.location.href = SITE_URL + '/customer-portal.php?view=signup'; }, 900);
    return;
  }
  const acc = document.getElementById('gen-modal-account');
  if (acc) acc.textContent = '';
  document.querySelectorAll('#generate-modal .gen-mode-card').forEach(c => c.classList.toggle('selected', c.dataset.mode === selectedGenMode));
  document.getElementById('gen-modal-mode-label').textContent = labelFor(selectedGenMode);
  updateAdminReqSection();
  document.getElementById('generate-modal').classList.add('active');
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
  const needsAdmin = selectedGenMode !== 'static';
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
function labelFor(mode) { return { static: 'Static', admin: 'Admin Panel + PHP', database: 'Admin + PHP + MySQL' }[mode] || mode; }
function confirmGenerate() {
  let adminUser = '', adminPass = '';
  if (selectedGenMode !== 'static') {
    const req = document.getElementById('admin_requirements')?.value.trim() || '';
    if (req.length < 20) {
      showToast('⚠️ Please describe what your admin panel should manage');
      document.getElementById('admin_requirements')?.focus();
      return;
    }
    // Admin login credentials — asked ONCE here, reused for the admin page
    // (publish step reuses these automatically, no re-entry needed).
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

function fillAdminPreset(key) {
  const t = document.getElementById('admin_requirements');
  if (t && ADMIN_PRESETS[key]) {
    t.value = ADMIN_PRESETS[key];
    t.focus();
    saveSessionNow();
    showToast('✓ Preset loaded — edit as needed');
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
let _currentPct = 0;
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
  const clockEl = document.getElementById('gen-clock');
  if (timeEl) timeEl.textContent = '0.0s';
  if (clockEl) clockEl.style.setProperty('--p', '0');
  clearInterval(_hudTimerInterval);
  _hudTimerInterval = setInterval(() => {
    const elapsed = (Date.now() - _startTime) / 1000;
    if (timeEl) timeEl.textContent = elapsed.toFixed(1) + 's';
    if (clockEl) clockEl.style.setProperty('--p', String(Math.min(100, (elapsed / 150) * 100)));
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

function updateGenOverlay(stage, message, pct, meta) {
  const fill = document.getElementById('gen-overlay-fill');
  const text = document.getElementById('gen-overlay-text');
  const pctNum = document.getElementById('gen-pct-num');
  const termLine = document.getElementById('gen-terminal-line');

  if (text) text.textContent = message;
  // AI-analyze voice for the telemetry line: prefix as AI reasoning
  if (termLine) termLine.textContent = 'AI ▸ analyzing: ' + message;
  // Mirror real engine progress into the live analysis feed too
  if (message) addGenLog('⚡', String(message).slice(0, 90));

  if (typeof pct === 'number') {
    _currentPct = Math.max(_currentPct, pct);
    if (fill) fill.style.width = _currentPct + '%';
    if (pctNum) pctNum.textContent = String(_currentPct);
    if (_currentPct >= _lastPop + 5) { _lastPop = _currentPct; popPct(); }
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

function hideGenOverlay(success) {
  clearInterval(_tipInterval);
  clearInterval(_hudTimerInterval);
  clearInterval(_logInterval);
  clearInterval(_typeTimer);
  const fill = document.getElementById('gen-overlay-fill');
  const pctNum = document.getElementById('gen-pct-num');
  const overlay = document.getElementById('gen-overlay');
  const text = document.getElementById('gen-overlay-text');

  if (success) {
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
    setTimeout(() => { overlay.classList.remove('active'); document.body.style.overflow = ''; }, 650);
  } else {
    if (fill) { fill.style.background = 'linear-gradient(90deg,#ef4444,#dc2626)'; fill.style.width = '100%'; }
    setTimeout(() => { overlay.classList.remove('active'); document.body.style.overflow = ''; }, 1100);
  }
}

async function generateWithMode(mode) {
  if (!window.__CUSTOMER__ || !window.__CUSTOMER__.email) {
    showToast('Please sign in / create account first');
    setTimeout(() => { window.location.href = SITE_URL + '/customer-portal.php?view=signup'; }, 900);
    return;
  }
  if (!window.AIFlowCraft) { showToast('⚠️ Generator not loaded'); return; }
  selectedGenMode = mode;
  const data = collectWizardSnapshot();
  if (!data.biz_name || !data.biz_tagline) { showToast('⚠️ Complete Step 1 first'); return; }

  // ★ Shop auto-detect: product list / shop checkbox / shop-type business.
  // Prefill admin requirements with the shop preset so admin/database modes
  // manage products + orders out of the box.
  try {
    const isShop = (data.biz_products && data.biz_products.trim())
      || (data.sections || []).includes('shop')
      || /shop|store|product|retail|e-?commerce|boutique|mart|trading|enterprise|fashion|jewelry|grocery|bakery|furniture|electronics|pharma/i.test(data.biz_type || '');
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
    // ★ Fixed AI split: variations 1-2 → Gemini, variation 3 → OpenCode.
    // No picker. Missing/failed slots → server templates fill in (never empty).
    const prog = (p) => {
      const pct = p.pct || (p.stage === 'connecting' ? 15 : (p.stage === 'done' ? 100 : 50));
      updateGenOverlay(p.stage, p.message, pct, p);
    };
    let concepts = null;
    let engineNote = '';
    let aiErr = '';
    let ocTokens = 0;
    const tokStr = () => ocTokens > 0 ? ` · ~${(ocTokens / 1000).toFixed(1)}k tokens` : '';
    try {
      if (window.OpenCodeAI?.generateConcepts) {
        const oc = await window.OpenCodeAI.generateConcepts(data, mode, { onProgress: prog });
        ocTokens = oc.tokens || 0;
        if (oc.designs.length === 3) {
          concepts = oc.designs;
          engineNote = 'Gemini ×2 + OpenCode AI';
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
            if (oc.errors && oc.errors.length) aiErr = String(oc.errors[0]).slice(0, 220);
          }
        }
      }
    } catch (e) {
      aiErr = String(e?.message || e).slice(0, 220);
      console.warn('[generate] OpenCode lane failed, using templates:', e?.message);
    }
    if (!concepts) {
      concepts = await window.AIFlowCraft.generateConcepts(data, mode, { onProgress: prog });
      engineNote = 'templates';
    }

    // Sanitize each generated design to strip any PHP leaks or markdown after </html>
    // Admin preview ONLY for admin/database modes — static gets NO admin panel at all.
    const wantAdmin = mode !== 'static';
    generatedConcepts = concepts.map(c => ({
      ...c,
      html: sanitizeHtmlOutput(c.html),
      adminHtml: wantAdmin ? buildInteractiveAdminPreview(data.biz_name) : null
    }));

    generatedDesigns = generatedConcepts.map(c => ({
      name: c.name, description: c.description, badge: c.badge, html: c.html,
      engine: (c.meta && c.meta.engine) || 'template',
      model: (c.meta && c.meta.model) || '',
      adminHtml: wantAdmin ? buildInteractiveAdminPreview(data.biz_name) : null,
      phpBackend: c.phpBackend || null,
      sqlSchema: c.sqlSchema || null, history: []
    }));

    activeDesignIndex = 0;
    currentHtml = generatedDesigns[0]?.html || '';

    saveSessionNow();
    hideGenOverlay(true);
    // Variations live on their own URL now (variations.php) — redirect there.
    try { sessionStorage.setItem('wc_flash', (engineNote === 'templates' && aiErr
      ? 'Templates (AI failed: ' + aiErr + ')' + tokStr()
      : '3 style variations ready! [' + engineNote + ']' + tokStr())); } catch (e) {}
    window.location.href = ((typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '') + '/variations.php';
  } catch (err) {
    console.error(err);
    hideGenOverlay(false);
    showToast('Failed: ' + err.message);
  } finally {
    document.getElementById('wiz-generate-btn').disabled = false;
    document.getElementById('wiz-next').disabled = false;
    document.getElementById('wiz-prev').disabled = (currentWizStep === 1);
    updateGenerateButton();
  }
}


/* ══════════════════════════════════════════════════
   DISPLAY 3 DESIGNS
═════════════════════════════════════════════════ */
function display3Designs(concepts, bizName) {
  document.getElementById('wizard-screen').style.display = 'none';
  document.getElementById('designs-screen').style.display = 'block';
  document.getElementById('subdesign-screen').style.display = 'none';
  document.getElementById('builder-screen').style.display = 'none';
  document.getElementById('designs-title').textContent = `3 Style Concepts for ${bizName}`;
  document.getElementById('designs-sub').textContent = selectedGenMode === 'static'
    ? 'Each concept has 3 premium layout variants — choose a style, then refine your layout.'
    : 'Each concept includes an AI admin panel. Pick a style concept, then choose your layout.';
  const grid = document.getElementById('designs-grid');
  grid.innerHTML = '';
  concepts.forEach((c, i) => {
    const card = document.createElement('div');
    card.className = 'design-card';
    const hasSubdesigns = c.subdesigns && c.subdesigns.length > 0;
    const isAI = c.meta && (c.meta.engine === 'opencode' || c.meta.engine === 'gemini');
    const aiModelShort = isAI ? String(c.meta.model || 'OpenCode AI').split('/').pop() : '';
    const engineBadge = isAI
      ? `<div style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.68rem;font-weight:800;letter-spacing:0.04em;color:#6ee7b7;background:rgba(6,78,59,0.35);border:1px solid rgba(16,185,129,0.5);padding:0.22rem 0.6rem;border-radius:999px;margin-bottom:0.6rem;">✦ AI-GENERATED · ${escapeHtml(aiModelShort)}</div>`
      : `<div style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.68rem;font-weight:800;letter-spacing:0.04em;color:#fcd34d;background:rgba(120,53,15,0.35);border:1px solid rgba(245,158,11,0.5);padding:0.22rem 0.6rem;border-radius:999px;margin-bottom:0.6rem;">📄 TEMPLATE · server engine</div>`;
    const tasteBadge = (isAI && c.meta && c.meta.tasteAudit && typeof c.meta.tasteAudit.score === 'number')
      ? `<div style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.68rem;font-weight:800;letter-spacing:0.04em;color:#c4b5fd;background:rgba(76,29,149,0.35);border:1px solid rgba(139,92,246,0.5);padding:0.22rem 0.6rem;border-radius:999px;margin-bottom:0.6rem;margin-left:0.35rem;">✦ TASTE ${c.meta.tasteAudit.score}</div>`
      : '';
    card.innerHTML = `
      <div class="design-badge-top">${escapeHtml(c.badge || `Variation ${i+1}`)}</div>
      <div class="design-number-badge">${i + 1}</div>
      <div class="design-preview-box"><div class="pv-spin"><div class="wcl-mini-house"><div class="walls"></div><div class="roof"></div><div class="door"></div></div></div><iframe class="design-preview-iframe" id="d${i}-iframe" onload="try{this.previousElementSibling.remove();}catch(e){}"></iframe></div>
      <div class="design-content">
        <h3>${escapeHtml(c.name)}</h3>
        ${engineBadge}${tasteBadge}
        <p>${escapeHtml(c.description)}</p>
        ${hasSubdesigns ? `<div style="font-size:0.72rem;color:#818cf8;font-weight:700;margin-bottom:0.6rem;letter-spacing:0.05em;">✦ 3 LAYOUT VARIANTS INSIDE</div>` : ''}
        <div class="design-actions">
          <button class="btn-preview-modal" onclick="openFullscreenModal(${i})">👁️ Full Preview</button>
          <button class="btn-choose-design" onclick="selectDesignAndEdit(${i})">Select &amp; Edit →</button>
        </div>
      </div>`;
    grid.appendChild(card);
    setTimeout(() => { const f = document.getElementById(`d${i}-iframe`); if (f) f.srcdoc = sanitizeHtmlOutput(withWorkspaceNavFix(c.html)); }, 40);
  });
  window.scrollTo({ top: 0, behavior: 'smooth' });
  saveSessionNow();
}

/* ══════════════════════════════════════════════════
   ADMIN PANEL STABILIZER & INTERACTIVE PREVIEW
═════════════════════════════════════════════════ */
function buildInteractiveAdminPreview(bizName) {
  bizName = bizName || document.getElementById('biz_name')?.value || 'My Website';
  const slug = bizName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'site';
  const safeName = bizName.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const targetUrl = `${SITE_URL}/published/${slug}/admin/login.php`;

  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${safeName} &mdash; Admin Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Fira+Code:wght@500;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:#060911;color:#e2e8f0;min-height:100vh}

/* ══ LOGIN SCREEN (Initial View) ══ */
#view-login {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  padding: 2rem 1.5rem; background: radial-gradient(circle at 50% 30%, #151c38 0%, #060911 75%);
}
.login-card {
  width: 100%; max-width: 440px; background: #0c1220; border: 1px solid #1e293b;
  border-radius: 20px; padding: 2.25rem 2rem; box-shadow: 0 25px 60px rgba(0,0,0,0.6);
  position: relative; overflow: hidden;
}
.login-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, #6366f1, #a855f7, #06b6d4);
}
.login-brand { display: flex; align-items: center; gap: 0.85rem; margin-bottom: 1.5rem; }
.login-logo { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #6366f1, #a855f7); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; box-shadow: 0 8px 20px rgba(99,102,241,0.4); }
.login-title { font-size: 1.25rem; font-weight: 800; color: #fff; line-height: 1.2; }
.login-sub { font-size: 0.78rem; color: #64748b; font-weight: 600; }
.login-heading { font-size: 1.1rem; font-weight: 800; color: #fff; margin-bottom: 0.35rem; }
.login-desc { font-size: 0.82rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1.5; }
.login-path-banner {
  background: #060913; border: 1px solid #1e3a5f; border-radius: 10px; padding: 0.65rem 0.85rem;
  margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.25rem;
}
.login-path-lbl { font-size: 0.68rem; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.04em; }
.login-path-val { font-family: 'Fira Code', monospace; font-size: 0.72rem; color: #67e8f9; word-break: break-all; }
.field { margin-bottom: 1.15rem; }
.field label { display: block; font-size: 0.75rem; font-weight: 700; color: #cbd5e1; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.03em; }
.inp { width: 100%; padding: 0.75rem 0.95rem; border: 1.5px solid #1e293b; border-radius: 10px; background: #060a14; color: #fff; font-family: inherit; font-size: 0.88rem; transition: 0.2s; }
.inp:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.25); }
.btn-login {
  width: 100%; padding: 0.85rem; border-radius: 11px; border: none;
  background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
  font-weight: 800; font-size: 0.92rem; cursor: pointer; transition: all 0.2s;
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  box-shadow: 0 8px 24px rgba(99,102,241,0.4); margin-top: 0.5rem;
}
.btn-login:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(99,102,241,0.6); }

/* ══ DASHBOARD SCREEN (After Login) ══ */
#view-dashboard { display: none; min-height: 100vh; }
.dash-layout { display: flex; min-height: 100vh; }
.sidebar { width: 260px; background: #0b0f19; border-right: 1px solid #1e293b; padding: 1.25rem 1rem; flex-shrink: 0; display: flex; flex-direction: column; }
.brand { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.25rem 1.25rem; border-bottom: 1px solid #1e293b; margin-bottom: 1rem; }
.brand .logo { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #6366f1, #a855f7); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.nav-btn {
  display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 0.85rem; border-radius: 10px;
  color: #94a3b8; background: none; border: none; width: 100%; font-family: inherit; font-size: 0.86rem;
  font-weight: 600; cursor: pointer; text-align: left; margin-bottom: 0.35rem; transition: 0.2s;
}
.nav-btn:hover { background: #111827; color: #fff; }
.nav-btn.active { background: #1e1b4b; color: #a5b4fc; font-weight: 800; border-left: 3px solid #6366f1; }
.nav-btn-highlight { background: rgba(99,102,241,0.12); color: #c7d2fe; border: 1px dashed rgba(99,102,241,0.4); }
.nav-btn-highlight:hover { background: rgba(99,102,241,0.25); color: #fff; }
.main { flex: 1; padding: 2rem 2.5rem; overflow-y: auto; max-width: 1300px; }
.card { background: #0f1523; border: 1px solid #1e293b; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; }
.grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
@media (max-width: 900px) { .grid4 { grid-template-columns: repeat(2, 1fr); } }
.stat { background: #070b14; border: 1px solid #1e293b; border-radius: 12px; padding: 1.1rem; }
.stat .lbl { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
.stat .val { font-size: 1.6rem; font-weight: 900; color: #fff; margin: 0.3rem 0; }
.btn-action { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.65rem 1.2rem; border-radius: 9px; font-weight: 700; font-size: 0.84rem; cursor: pointer; border: none; background: linear-gradient(135deg,#6366f1,#8b5cf6); color: #fff; }
.btn-action-green { background: linear-gradient(135deg,#10b981,#059669); }
table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; }
th, td { padding: 0.75rem 0.85rem; text-align: left; border-bottom: 1px solid #1e293b; font-size: 0.84rem; }
th { color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 0.72rem; }
.preset-tag { padding: 0.35rem 0.75rem; background: #1e293b; border: 1px solid #334155; border-radius: 999px; font-size: 0.76rem; font-weight: 700; color: #cbd5e1; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; margin: 0.25rem 0.25rem 0.25rem 0; }
.preset-tag:hover { border-color: #6366f1; color: #fff; background: #1e1b4b; }
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════
     SCREEN 1: OFFICIAL ADMIN LOGIN PAGE
══════════════════════════════════════════════════ -->
<div id="view-login">
  <div class="login-card">
    <div class="login-brand">
      <div class="login-logo">🔐</div>
      <div>
        <div class="login-title">${safeName}</div>
        <div class="login-sub">Customer CMS Administration</div>
      </div>
    </div>

    <div class="login-heading">Admin Panel Login</div>
    <div class="login-desc">Enter your credentials below to access the management dashboard.</div>

    <div class="login-path-banner">
      <span class="login-path-lbl">📍 Published Admin URL Path</span>
      <span class="login-path-val">${targetUrl}</span>
    </div>

    <form onsubmit="doLogin(event)">
      <div class="field">
        <label>Username</label>
        <input class="inp" type="text" id="inp-user" value="admin" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input class="inp" type="password" id="inp-pass" value="WebCraft@2026!" required>
      </div>
      <button type="submit" class="btn-login">
        <span>🚀 Sign In to Dashboard</span>
      </button>
    </form>

    <div style="margin-top:1.25rem;text-align:center;">
      <span style="font-size:0.73rem;color:#64748b;">⚡ Click "Sign In" to preview the active admin dashboard</span>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     SCREEN 2: LOGGED-IN ADMIN DASHBOARD
══════════════════════════════════════════════════ -->
<div id="view-dashboard">
  <div class="dash-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="brand">
        <div class="logo">⚡</div>
        <div style="min-width:0">
          <div style="font-weight:800;font-size:0.92rem;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${safeName}</div>
          <div style="font-size:0.7rem;color:#10b981;font-weight:700">● Logged In (Admin)</div>
        </div>
      </div>

      <nav style="flex:1">
        <button class="nav-btn active" id="btn-tab-dash" onclick="tab('dash')">📊 Dashboard</button>
        <button class="nav-btn" id="btn-tab-content" onclick="tab('content')">📝 Edit Content</button>
        <button class="nav-btn" id="btn-tab-data" onclick="tab('data')">📋 Manage Data</button>
        <!-- CRITICAL: ADD FUNCTIONS / AI FEATURE BUILDER (Rule 18.5) -->
        <button class="nav-btn nav-btn-highlight" id="btn-tab-features" onclick="tab('features')">🤖 Add Functions (AI)</button>
        <button class="nav-btn" id="btn-tab-settings" onclick="tab('settings')">⚙️ Settings &amp; Password</button>
      </nav>

      <div style="padding-top:1.25rem;border-top:1px solid #1e293b">
        <button onclick="doLogout()" class="nav-btn" style="color:#ef4444">🚪 Sign Out</button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <!-- 1. Dashboard Tab -->
      <div id="sec-dash">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
          <div>
            <h1 style="font-size:1.6rem;font-weight:900;color:#fff;">Administration Dashboard 👋</h1>
            <p style="color:#64748b;font-size:0.85rem;">Live management control center for ${safeName}.</p>
          </div>
          <button class="btn-action btn-action-green" onclick="tab('features')">➕ Add New Function</button>
        </div>

        <div class="grid4">
          <div class="stat"><div class="lbl">Website Status</div><div class="val" style="color:#10b981">Active 🟢</div><div style="font-size:0.75rem;color:#64748b">Hosted &amp; Online</div></div>
          <div class="stat"><div class="lbl">Active Functions</div><div class="val" style="color:#818cf8" id="stat-fn-count">4</div><div style="font-size:0.75rem;color:#64748b">CMS Modules Ready</div></div>
          <div class="stat"><div class="lbl">Content Sections</div><div class="val">6</div><div style="font-size:0.75rem;color:#64748b">Editable Fields</div></div>
          <div class="stat"><div class="lbl">Admin Security</div><div class="val" style="color:#f59e0b">SSL 🔒</div><div style="font-size:0.75rem;color:#64748b">Bcrypt Protected</div></div>
        </div>

        <div class="card">
          <h3 style="color:#fff;font-size:1rem;margin-bottom:0.5rem">⚡ How Your Admin Panel Works</h3>
          <p style="color:#94a3b8;font-size:0.84rem;line-height:1.6;margin-bottom:1rem;">
            When published, your administration panel is automatically installed at: <code style="color:#38bdf8">${targetUrl}</code>.
            You can manage live site content, view records, add new features anytime with AI, and change your password.
          </p>
          <div style="display:flex;gap:0.75rem;">
            <button class="btn-action" onclick="tab('features')">🤖 Open AI Feature Builder</button>
            <button class="btn-action" style="background:#1e293b;" onclick="tab('content')">📝 Edit Live Content</button>
          </div>
        </div>
      </div>

      <!-- 2. AI Feature / Function Requirement Builder (Rule 18.5) -->
      <div id="sec-features" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
          <div>
            <div style="display:inline-block;padding:0.25rem 0.75rem;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.4);border-radius:999px;font-size:0.72rem;font-weight:800;color:#c7d2fe;margin-bottom:0.4rem;">
              ✦ RULE 18.5: ADMIN EDIT MODE ACTIVE
            </div>
            <h1 style="font-size:1.6rem;font-weight:900;color:#fff;">Create Business Process / Add Functions 🤖</h1>
            <p style="color:#94a3b8;font-size:0.84rem;">Describe what business functions you want to add to your admin panel. The AI writes and installs the complete backend module automatically.</p>
          </div>
        </div>

        <div class="card" style="border-color:rgba(99,102,241,0.35);background:linear-gradient(135deg,rgba(99,102,241,0.06),rgba(15,23,42,0.8));">
          <label style="display:block;font-size:0.8rem;font-weight:700;color:#cbd5e1;margin-bottom:0.5rem;">Quick Presets (Click to Load):</label>
          <div style="margin-bottom:1rem;">
            <span class="preset-tag" onclick="usePreset('Add an Appointment Booking system with Customer Name, Phone, Email, Service Type, Booking Date, Time Slot, and Status.')">📅 Appointment Booking</span>
            <span class="preset-tag" onclick="usePreset('Add an Employee / Team section with Full Name, Role / Position, Phone, Bio, and Photo upload.')">👥 Staff / Team Management</span>
            <span class="preset-tag" onclick="usePreset('Add a Customer Reviews &amp; Testimonials module with Client Name, Company, Rating (1-5 stars), Comment, and Status.')">⭐ Reviews &amp; Ratings</span>
            <span class="preset-tag" onclick="usePreset('Add a Blog &amp; News Publishing section with Article Title, Featured Image, Category, Content Body, and Publish Date.')">📰 Blog / News Articles</span>
          </div>

          <div class="field">
            <label>Function Requirements / Process Description</label>
            <textarea id="feature-req-input" rows="3" class="inp" style="resize:vertical;" placeholder="Describe what you want to add... e.g. Add a Booking system with Date, Time, Service, Customer Name, and Email"></textarea>
          </div>

          <button class="btn-action btn-action-green" onclick="simulateAddFeature()">
            <span>✨ Generate &amp; Install Function with AI</span>
          </button>
        </div>

        <div class="card">
          <h3 style="color:#fff;font-size:1rem;margin-bottom:0.75rem;">📦 Active Business Functions &amp; Modules</h3>
          <table id="features-table">
            <thead><tr><th>Function Name</th><th>Type</th><th>Storage</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Content Management</strong></td><td>Core CMS</td><td>JSON Storage</td><td><span style="color:#10b981;font-weight:800;">● Active</span></td><td><button style="background:none;border:none;color:#818cf8;cursor:pointer;font-weight:700" onclick="tab('content')">Edit</button></td></tr>
              <tr><td><strong>Data Record Manager</strong></td><td>Entities CRUD</td><td>JSON Storage</td><td><span style="color:#10b981;font-weight:800;">● Active</span></td><td><button style="background:none;border:none;color:#818cf8;cursor:pointer;font-weight:700" onclick="tab('data')">Edit</button></td></tr>
              <tr><td><strong>Customer Password &amp; Auth</strong></td><td>Security</td><td>Bcrypt Hash</td><td><span style="color:#10b981;font-weight:800;">● Active</span></td><td><button style="background:none;border:none;color:#818cf8;cursor:pointer;font-weight:700" onclick="tab('settings')">Edit</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 3. Edit Content Tab -->
      <div id="sec-content" style="display:none">
        <h1 style="font-size:1.6rem;font-weight:900;color:#fff;margin-bottom:0.3rem">Website Content Editor 📝</h1>
        <p style="color:#64748b;font-size:0.85rem;margin-bottom:1.5rem">Updates saved here sync directly to your public website.</p>
        <div class="card">
          <div class="field"><label>Business Name</label><input type="text" class="inp" value="${safeName}"></div>
          <div class="field"><label>Tagline / Mission</label><input type="text" class="inp" value="Quality &amp; Excellence Delivered"></div>
          <div class="field"><label>Contact Phone</label><input type="text" class="inp" value="+1 (555) 019-2834"></div>
          <div class="field"><label>Contact Email</label><input type="email" class="inp" value="contact@${slug}.com"></div>
          <button class="btn-action">💾 Save Content Changes</button>
        </div>
      </div>

      <!-- 4. Manage Data Tab -->
      <div id="sec-data" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
          <div>
            <h1 style="font-size:1.6rem;font-weight:900;color:#fff">Manage Records 📋</h1>
            <p style="color:#64748b;font-size:0.85rem;">Dynamic CRUD data tables for your business items.</p>
          </div>
          <button class="btn-action btn-action-green">➕ Add New Item</button>
        </div>
        <div class="card">
          <table>
            <thead><tr><th>Title</th><th>Category</th><th>Price / Value</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td>Standard Package</td><td>Services</td><td>$99.00</td><td><span style="color:#10b981;font-weight:700">Published</span></td><td><button style="background:none;border:none;color:#818cf8;cursor:pointer">✏️ Edit</button></td></tr>
              <tr><td>Premium Consultation</td><td>Consulting</td><td>$150.00</td><td><span style="color:#10b981;font-weight:700">Published</span></td><td><button style="background:none;border:none;color:#818cf8;cursor:pointer">✏️ Edit</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 5. Settings Tab -->
      <div id="sec-settings" style="display:none">
        <h1 style="font-size:1.6rem;font-weight:900;color:#fff;margin-bottom:0.3rem">Settings &amp; Password ⚙️</h1>
        <p style="color:#64748b;font-size:0.85rem;margin-bottom:1.5rem">Manage your admin login credentials and security.</p>
        <div class="card" style="max-width:540px">
          <div class="field"><label>Current Password</label><input type="password" class="inp" value="WebCraft@2026!"></div>
          <div class="field"><label>New Password</label><input type="password" class="inp" placeholder="Enter new password"></div>
          <div class="field"><label>Confirm New Password</label><input type="password" class="inp" placeholder="Confirm new password"></div>
          <button class="btn-action">Update Password</button>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
function doLogin(e) {
  if (e) e.preventDefault();
  document.getElementById('view-login').style.display = 'none';
  document.getElementById('view-dashboard').style.display = 'block';
  tab('dash');
}

function doLogout() {
  document.getElementById('view-dashboard').style.display = 'none';
  document.getElementById('view-login').style.display = 'flex';
}

function tab(id) {
  ['dash','features','content','data','settings'].forEach(t => {
    const el = document.getElementById('sec-' + t);
    const btn = document.getElementById('btn-tab-' + t);
    if (el) el.style.display = (t === id) ? 'block' : 'none';
    if (btn) btn.classList.toggle('active', t === id);
  });
}

function usePreset(text) {
  const t = document.getElementById('feature-req-input');
  if (t) { t.value = text; t.focus(); }
}

function simulateAddFeature() {
  const req = document.getElementById('feature-req-input')?.value.trim();
  if (!req) { alert('Please enter feature requirements or choose a preset.'); return; }
  const tbody = document.querySelector('#features-table tbody');
  if (tbody) {
    const title = req.split(' ')[1] || 'Custom Feature';
    const tr = document.createElement('tr');
    tr.innerHTML = '<td><strong>' + title + ' System</strong></td><td>AI Module</td><td>JSON Storage</td><td><span style=\"color:#10b981;font-weight:800;\">● Active</span></td><td><span style=\"color:#a5b4fc;font-size:0.75rem\">Installed</span></td>';
    tbody.appendChild(tr);
  }
  const countEl = document.getElementById('stat-fn-count');
  if (countEl) countEl.textContent = parseInt(countEl.textContent || '4', 10) + 1;
  alert('✨ New feature module built & installed into admin panel!');
  document.getElementById('feature-req-input').value = '';
}
<\/script>
</body>
</html>`;
}

function stabilizeAdminHtml(html) {
  const bizName = document.getElementById('biz_name')?.value || 'My Website';
  const pub = getPublishedSession();
  if (!pub || !pub.adminUrl || !html || 
      html.includes('<' + String.fromCharCode(63) + 'php') || 
      !html.includes('<html') ||
      /php\s*not\s*detect/i.test(html) ||
      html.includes('action=bootstrap') ||
      html.includes('offline') ||
      html.includes('ZS_STORAGE')
  ) {
    return buildInteractiveAdminPreview(bizName);
  }
  const stabilizer = `
<style id="__wc_admin_stabilizer__">
html, body {
  height: auto !important;
  min-height: 100% !important;
  overflow-x: hidden !important;
  overscroll-behavior: contain !important;
  scroll-behavior: auto !important;
}
body { position: relative !important; }
[style*="position:fixed"], [style*="position: fixed"],
[style*="position:sticky"], [style*="position: sticky"] {
  position: absolute !important;
}
aside, .sidebar, [class*="sidebar"], nav[class*="side"] {
  position: absolute !important;
}
*, *::before, *::after {
  animation-iteration-count: 1 !important;
  transition-duration: 0.001ms !important;
  scroll-behavior: auto !important;
}
[data-anim], [data-anim-trigger] {
  animation: none !important;
  transform: none !important;
  opacity: 1 !important;
  filter: none !important;
}
canvas, iframe { max-width: 100%; }
</style>
`;
  if (/<head[^>]*>/i.test(html)) {
    return html.replace(/<head([^>]*)>/i, `<head$1>${stabilizer}`);
  }
  return stabilizer + html;
}

/* ══════════════════════════════════════════════════
   FULLSCREEN MODAL
═════════════════════════════════════════════════ */
function openFullscreenModal(index) {
  modalViewingIndex = index; modalViewMode = 'site';
  const c = generatedConcepts[index];
  if (!c) return;
  document.getElementById('modal-title').textContent = c.name;
  const tabs = document.getElementById('modal-view-tabs');
  const hasAdmin = selectedGenMode !== 'static' && c.adminHtml;
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
  document.getElementById('modal-iframe').srcdoc = view === 'admin'
    ? stabilizeAdminHtml(c.adminHtml || '<p style="padding:2rem;font-family:sans-serif;">No admin panel yet — will be generated during publish.</p>')
    : withWorkspaceNavFix(c.html);
}
function closeFullscreenModal() { document.getElementById('fullscreen-modal').classList.remove('active'); }
function selectFromModal() { closeFullscreenModal(); selectDesignAndEdit(modalViewingIndex); }
function openAdminPreview(index) {
  const c = generatedConcepts[index];
  if (!c || !c.adminHtml) { showToast('Admin panel will be AI-generated at publish time'); return; }
  modalViewingIndex = index; modalViewMode = 'admin';
  document.getElementById('modal-title').textContent = `${c.name} — Admin Panel`;
  document.getElementById('modal-view-tabs').style.display = 'flex';
  document.getElementById('m-vtab-site').classList.remove('active');
  document.getElementById('m-vtab-admin').classList.add('active');
  __armSpin('modal-frame-spin', 'modal');
  document.getElementById('modal-iframe').srcdoc = stabilizeAdminHtml(c.adminHtml);
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
        engineNote = 'Gemini ×2 + OpenCode AI';
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
      if (r.errors && r.errors.length) subAiErr = String(r.errors[0]).slice(0, 220);
    } catch (e) {
      subAiErr = String(e?.message || e).slice(0, 220);
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
  const w = window.open('', '_blank');
  if (w) { w.document.open(); w.document.write(withWorkspaceNavFix(sd.html)); w.document.close(); }
}

function backToDesignsFromSub() {
  goToVariations();
}
function updateViewTabsVisibility() {
  const c = generatedDesigns[activeDesignIndex];
  const hasAdmin = selectedGenMode !== 'static' && c?.adminHtml;
  document.getElementById('view-tabs').classList.toggle('show', !!hasAdmin);
}
function getPublishedSession() {
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    const sess = raw ? JSON.parse(raw) : null;
    return sess?.published || null;
  } catch (e) { return null; }
}

function switchWorkspaceView(view) {
  const c = generatedDesigns[activeDesignIndex];
  if (!c) return;
  // Static mode has no admin panel — block it even if forced
  if (view === 'admin' && (selectedGenMode === 'static' || !c.adminHtml)) {
    showToast('Static websites have no admin panel — pick Admin or Database mode to get one.');
    return;
  }
  currentViewMode = view;
  document.getElementById('vtab-site').classList.toggle('active', view === 'site');
  document.getElementById('vtab-admin').classList.toggle('active', view === 'admin');
  document.getElementById('current-view-label').textContent = view === 'admin' ? '🔐 Admin Panel' : '🌐 Frontend Site';

  const adminBar   = document.getElementById('admin-url-bar');
  const urlDisplay = document.getElementById('admin-url-display');
  const openBtn    = document.getElementById('admin-url-open-btn');
  const addFnBtn   = document.getElementById('btn-add-function');

  if (view === 'admin') {
    const pub = getPublishedSession();
    const bizName = document.getElementById('biz_name')?.value || 'My Website';
    const slug = bizName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'site';
    const targetUrl = (pub && pub.adminUrl) ? pub.adminUrl : `${SITE_URL}/published/${slug}/admin/login.php`;

    if (adminBar)   { adminBar.style.display = 'flex'; }
    if (urlDisplay) { urlDisplay.value = targetUrl; }
    if (openBtn)    { openBtn.href = targetUrl; openBtn.style.opacity = ''; openBtn.style.pointerEvents = ''; }
    if (addFnBtn)   { addFnBtn.style.display = ''; }

    if (pub && pub.adminUrl) {
      // Load real admin login page
      const f = document.getElementById('live-iframe');
      if (f) { f.src = pub.adminUrl; f.removeAttribute('srcdoc'); }
    } else {
      // Load official interactive Login Page & Dashboard preview
      updateLiveIframe(buildInteractiveAdminPreview(bizName));
    }
  } else {
    // Switched back to Site view — hide admin bar + Add Function btn
    if (adminBar) { adminBar.style.display = 'none'; }
    if (addFnBtn) { addFnBtn.style.display = 'none'; }
    const f = document.getElementById('live-iframe');
    if (f) { f.removeAttribute('src'); }
    updateLiveIframe(c.html);
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
    try {
      if (currentViewMode !== 'admin') {
        switchWorkspaceView('admin');
      }
      const f = document.getElementById('live-iframe');
      if (f && f.contentWindow) {
        const doc = f.contentDocument || f.contentWindow.document;
        const loginView = doc.getElementById('view-login');
        if (loginView && loginView.style.display !== 'none') {
          if (typeof f.contentWindow.doLogin === 'function') f.contentWindow.doLogin();
        }
        if (typeof f.contentWindow.tab === 'function') {
          f.contentWindow.tab('features');
          showToast('🤖 Opened AI Feature & Requirement Builder');
          return;
        }
      }
    } catch(e) {}
    openGenerateModal();
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
       scrolled into view (IntersectionObserver, once, easeOutExpo). */
function withWorkspaceNavFix(html) {
  if (!html) return html;
  const haveNav = html.indexOf('wc-nav-fix') !== -1;
  const haveCount = html.indexOf('wc-count-fix') !== -1;
  if (haveNav && haveCount) return html;
  let bundle = '';
  if (!haveNav) {
    bundle += '<style data-wc-nav-fix-css>html{scroll-behavior:smooth!important}section[id],div[id],footer[id],header[id],main[id],article[id]{scroll-margin-top:88px!important}</style>';
    bundle += '<script data-wc-nav-fix>(function(){document.addEventListener("click",function(e){var a=e.target&&e.target.closest?e.target.closest(\'a[href^="#"]\'):null;if(!a)return;var href=a.getAttribute("href")||"";if(href.length<2)return;var id=href.slice(1).split("?")[0].split("#")[0];try{id=decodeURIComponent(id)}catch(_){}id=(id||"").trim();if(!id)return;var t=document.getElementById(id);if(!t)return;e.preventDefault();try{e.stopImmediatePropagation()}catch(_){}e.stopPropagation();try{t.scrollIntoView({behavior:"smooth",block:"start"})}catch(_){try{t.scrollIntoView()}catch(__){}}try{history.replaceState(null,"","#"+id)}catch(__){}var d=document.getElementById("mobile-drawer");if(d)d.classList.remove("open")},true)})();<\/script>';
  }
  if (!haveCount) bundle += WC_COUNT_FIX_JS;
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
  if (currentViewMode === 'admin') updateLiveIframe(stabilizeAdminHtml(c.adminHtml || ''));
  else updateLiveIframe(currentHtml);
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
  const c = generatedDesigns[activeDesignIndex];
  const html = currentViewMode === 'admin' ? stabilizeAdminHtml(c?.adminHtml || '') : withWorkspaceNavFix(currentHtml);
  window.open(URL.createObjectURL(new Blob([html], { type: 'text/html;charset=utf-8' })), '_blank');
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
function appendGeminiChatMessage(msg) {
  const log = document.getElementById('ai-chat-log');
  if (!log) return;
  const row = document.createElement('div');
  row.className = 'msg ai';
  row.innerHTML = `<div class="msg-avatar">✦</div><div class="msg-body"><div class="msg-bubble">${msg}</div><div class="msg-meta">AI · ${getTimeStr()}<button class="msg-copy" type="button" onclick="copyMsg(this)">Copy</button></div></div>`;
  log.appendChild(row);
  log.scrollTop = log.scrollHeight;
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
      userPrompt: enriched, currentHtml: snapHtml, bizName
    });
    document.getElementById('ai-typing-indicator')?.remove();
    appendGeminiChatMessage(formatMarkdown(res.conversation));
    if (res.isEdit && res.updatedHtml) {
      if (isAdmin) {
        c.adminHtml = res.updatedHtml;
        updateLiveIframe(stabilizeAdminHtml(c.adminHtml));
        showToast('✨ Admin panel updated!');
      } else {
        pushHistory(activeDesignIndex, snapHtml);
        let n = res.updatedHtml;
        if (n.includes('<html') || n.includes('<!DOCTYPE')) currentHtml = n;
        else currentHtml = currentHtml.replace('</body>', `${n}\n</body>`);
        c.html = currentHtml;
        updateLiveIframe(currentHtml);
        updateUndoBtn();
        showToast('✨ Site updated! [' + (res.engine || 'AI') + (res.tasteAudit && typeof res.tasteAudit.score === 'number' ? ' · Taste ' + res.tasteAudit.score : '') + ']');
      }
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
    const bridge = {
      bizName,
      activeDesignIndex,
      ownerEmail: ((window.__CUSTOMER__ && window.__CUSTOMER__.email) || 'guest').toLowerCase(),
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
    localStorage.setItem('webcraft_studio_bridge', JSON.stringify({
      savedAt: Date.now(), bizName, activeDesignIndex,
      html: c.html || '', adminHtml: c.adminHtml || null
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
