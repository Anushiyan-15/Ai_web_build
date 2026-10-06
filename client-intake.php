<?php
require_once __DIR__ . '/config.php';
// ─────────────────────────────────────────────
//  SERVER-SIDE PROCESSING
// ─────────────────────────────────────────────
$submitted   = false;
$errors      = [];
$formData    = [];
$successMsg  = '';

// Helper: sanitize a single value
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// Helper: sanitize array values
function cleanArr(array $arr): array {
    return array_map('clean', $arr);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Section 1 ─────────────────────────────
    $formData['primary_purpose']   = clean($_POST['primary_purpose']   ?? '');
    $formData['objectives']        = clean($_POST['objectives']        ?? '');
    $formData['project_type']      = clean($_POST['project_type']      ?? '');
    $formData['success_metrics']   = clean($_POST['success_metrics']   ?? '');
    $formData['budget_range']      = clean($_POST['budget_range']      ?? '');
    $formData['timeline']          = clean($_POST['timeline']          ?? '');

    // ── Section 2 ─────────────────────────────
    $formData['target_audience']   = clean($_POST['target_audience']   ?? '');
    $formData['competitors']       = clean($_POST['competitors']       ?? '');
    $formData['uvp']               = clean($_POST['uvp']               ?? '');

    // ── Section 3 ─────────────────────────────
    $formData['brand_assets']      = isset($_POST['brand_assets'])  ? cleanArr($_POST['brand_assets'])  : [];
    $formData['content_inventory'] = clean($_POST['content_inventory'] ?? '');
    $formData['content_owner']     = clean($_POST['content_owner']     ?? '');
    $formData['required_pages']    = clean($_POST['required_pages']    ?? '');
    $formData['tone_of_voice']     = clean($_POST['tone_of_voice']     ?? '');

    // ── Section 4 ─────────────────────────────
    $formData['style_refs']        = clean($_POST['style_refs']        ?? '');
    $formData['color_mood']        = clean($_POST['color_mood']        ?? '');
    $formData['layout_pref']       = clean($_POST['layout_pref']       ?? '');
    $formData['accessibility']     = clean($_POST['accessibility']     ?? '');

    // ── Section 5 ─────────────────────────────
    $formData['must_have_features']= clean($_POST['must_have_features']?? '');
    $formData['integrations']      = isset($_POST['integrations'])  ? cleanArr($_POST['integrations'])  : [];
    $formData['cms_pref']          = clean($_POST['cms_pref']          ?? '');
    $formData['responsive']        = clean($_POST['responsive']        ?? '');
    $formData['seo_req']           = clean($_POST['seo_req']           ?? '');
    $formData['hosting_status']    = clean($_POST['hosting_status']    ?? '');

    // ── Section 6 ─────────────────────────────
    $formData['decision_makers']   = clean($_POST['decision_makers']   ?? '');
    $formData['comm_pref']         = clean($_POST['comm_pref']         ?? '');
    $formData['scope_boundaries']  = clean($_POST['scope_boundaries']  ?? '');
    $formData['revision_rounds']   = clean($_POST['revision_rounds']   ?? '');
    $formData['payment_terms']     = clean($_POST['payment_terms']     ?? '');

    // ── Section 7 ─────────────────────────────
    $formData['training_needs']    = clean($_POST['training_needs']    ?? '');
    $formData['maintenance_resp']  = clean($_POST['maintenance_resp']  ?? '');
    $formData['future_roadmap']    = clean($_POST['future_roadmap']    ?? '');

    // ── Contact info (top of form) ─────────────
    $formData['client_name']       = clean($_POST['client_name']       ?? '');
    $formData['company_name']      = clean($_POST['company_name']      ?? '');
    $formData['client_email']      = clean($_POST['client_email']      ?? '');
    $formData['client_phone']      = clean($_POST['client_phone']      ?? '');

    // ── Validation ────────────────────────────
    if (empty($formData['client_name']))
        $errors['client_name'] = 'Your name is required.';
    if (empty($formData['company_name']))
        $errors['company_name'] = 'Company name is required.';
    if (empty($formData['client_email']))
        $errors['client_email'] = 'Email address is required.';
    elseif (!filter_var($_POST['client_email'], FILTER_VALIDATE_EMAIL))
        $errors['client_email'] = 'Please enter a valid email address.';
    if (empty($formData['primary_purpose']))
        $errors['primary_purpose'] = 'Please describe the primary purpose of the website.';
    if (empty($formData['project_type']))
        $errors['project_type'] = 'Please select a project type.';
    if (empty($formData['budget_range']))
        $errors['budget_range'] = 'Please select a budget range.';
    if (empty($formData['target_audience']))
        $errors['target_audience'] = 'Target audience description is required.';
    if (empty($formData['uvp']))
        $errors['uvp'] = 'Your unique value proposition is required.';
    if (empty($formData['required_pages']))
        $errors['required_pages'] = 'Please list the required pages.';
    if (empty($formData['must_have_features']))
        $errors['must_have_features'] = 'Please list must-have features.';
    if (empty($formData['decision_makers']))
        $errors['decision_makers'] = 'Please list the decision-makers.';

    // ── Process if valid ───────────────────────
    if (empty($errors)) {
        // --- Option A: Save to a JSON log file in storage/submissions/ ---
        $logDir  = defined('STORAGE_DIR') ? (STORAGE_DIR . '/submissions') : (__DIR__ . '/storage/submissions');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $filename = $logDir . '/submission_' . date('Ymd_His') . '_' . uniqid() . '.json';
        $payload  = array_merge($formData, [
            'submitted_at' => date('Y-m-d H:i:s'),
            'ip'           => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // --- Option B: Send notification email (uncomment + configure) ---
        // $to      = 'hello@youragency.com';
        // $subject = 'New Client Intake: ' . $formData['company_name'];
        // $body    = print_r($payload, true);
        // $headers = 'From: noreply@youragency.com';
        // mail($to, $subject, $body, $headers);

        $submitted  = true;
        $successMsg = "Thank you, {$formData['client_name']}! Your intake questionnaire has been received. We'll review it and be in touch within 1–2 business days.";
    }
}

// Helper to repopulate field values
function val(string $key, string $default = ''): string {
    global $formData;
    return isset($formData[$key]) && is_string($formData[$key]) ? htmlspecialchars($formData[$key]) : $default;
}
function selected(string $key, string $value): string {
    return (isset($_POST[$key]) && $_POST[$key] === $value) ? 'selected' : '';
}
function checked(string $key, string $value): string {
    global $formData;
    if (is_array($formData[$key] ?? null)) {
        return in_array($value, $formData[$key]) ? 'checked' : '';
    }
    return (($formData[$key] ?? '') === $value) ? 'checked' : '';
}
function err(string $key): string {
    global $errors;
    return isset($errors[$key]) ? '<span class="field-error" role="alert"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>' . htmlspecialchars($errors[$key]) . '</span>' : '';
}
function hasErr(string $key): string {
    global $errors;
    return isset($errors[$key]) ? ' has-error' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Intake Questionnaire | Website Project Discovery</title>
<style>
/* ──────────────────────────────────────────────
   RESET & VARIABLES
────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --primary:      #4f46e5;
  --primary-dark: #3730a3;
  --primary-light:#ede9fe;
  --accent:       #06b6d4;
  --success:      #10b981;
  --danger:       #ef4444;
  --warning:      #f59e0b;
  --neutral-50:   #f9fafb;
  --neutral-100:  #f3f4f6;
  --neutral-200:  #e5e7eb;
  --neutral-300:  #d1d5db;
  --neutral-400:  #9ca3af;
  --neutral-500:  #6b7280;
  --neutral-600:  #4b5563;
  --neutral-700:  #374151;
  --neutral-800:  #1f2937;
  --neutral-900:  #111827;
  --radius:       10px;
  --shadow-sm:    0 1px 3px rgba(0,0,0,.08);
  --shadow:       0 4px 16px rgba(0,0,0,.08);
  --shadow-lg:    0 12px 40px rgba(0,0,0,.12);
  --font:         'Inter', system-ui, -apple-system, sans-serif;
  --transition:   .18s ease;
}

html { scroll-behavior: smooth; }
body {
  font-family: var(--font);
  background: linear-gradient(135deg, #f0f4ff 0%, #faf5ff 50%, #f0fdf4 100%);
  min-height: 100vh;
  color: var(--neutral-800);
  line-height: 1.6;
}

/* ──────────────────────────────────────────────
   HEADER
────────────────────────────────────────────── */
.site-header {
  background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 60%, #a855f7 100%);
  color: #fff;
  padding: 3rem 1.5rem 5rem;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.site-header::before {
  content:'';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.site-header .logo {
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  opacity: .85;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
}
.site-header .logo svg { width:22px; height:22px; }
.site-header h1 {
  font-size: clamp(1.6rem, 4vw, 2.4rem);
  font-weight: 800;
  letter-spacing: -.02em;
  margin-bottom: .75rem;
}
.site-header p {
  font-size: 1.05rem;
  opacity: .85;
  max-width: 550px;
  margin: 0 auto;
}
.header-badge {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  background: rgba(255,255,255,.15);
  border: 1px solid rgba(255,255,255,.25);
  border-radius: 999px;
  font-size: .8rem;
  font-weight: 600;
  padding: .3rem .9rem;
  margin-bottom: 1rem;
  letter-spacing: .04em;
}

/* ──────────────────────────────────────────────
   MAIN WRAPPER
────────────────────────────────────────────── */
.wrapper {
  max-width: 820px;
  margin: -2.5rem auto 4rem;
  padding: 0 1.25rem;
  position: relative;
  z-index: 1;
}

/* ──────────────────────────────────────────────
   PROGRESS BAR
────────────────────────────────────────────── */
.progress-wrap {
  background: #fff;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}
.progress-label {
  font-size: .8rem;
  font-weight: 600;
  color: var(--neutral-500);
  white-space: nowrap;
}
.progress-bar-track {
  flex: 1;
  height: 8px;
  background: var(--neutral-200);
  border-radius: 999px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--primary), var(--accent));
  border-radius: 999px;
  transition: width .4s ease;
}
.step-dots {
  display: flex;
  gap: .35rem;
}
.step-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--neutral-200);
  cursor: pointer;
  transition: var(--transition);
}
.step-dot.active { background: var(--primary); transform: scale(1.3); }
.step-dot.done   { background: var(--success); }

/* ──────────────────────────────────────────────
   FORM CARD
────────────────────────────────────────────── */
.form-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

/* ──────────────────────────────────────────────
   CONTACT STRIP
────────────────────────────────────────────── */
.contact-strip {
  background: linear-gradient(135deg, var(--primary-light), #f0fdf4);
  padding: 2rem;
  border-bottom: 1px solid var(--neutral-200);
}
.contact-strip h2 {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--primary-dark);
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}
.contact-strip h2 svg { width:20px; height:20px; }

/* ──────────────────────────────────────────────
   SECTIONS (multi-step tabs)
────────────────────────────────────────────── */
.form-sections { padding: 0; }

.form-section {
  display: none;
  padding: 2rem;
  border-bottom: 1px solid var(--neutral-100);
}
.form-section.active { display: block; }

.section-header {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.75rem;
  padding-bottom: 1.25rem;
  border-bottom: 2px solid var(--neutral-100);
}
.section-number {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--primary), #7c3aed);
  color: #fff;
  font-size: .9rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.section-title h2 {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--neutral-900);
  margin-bottom: .2rem;
}
.section-title p {
  font-size: .85rem;
  color: var(--neutral-500);
}

/* ──────────────────────────────────────────────
   FORM FIELDS
────────────────────────────────────────────── */
.field-group { margin-bottom: 1.4rem; }
.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}
@media (max-width: 580px) { .field-row { grid-template-columns: 1fr; } }

label {
  display: block;
  font-size: .855rem;
  font-weight: 600;
  color: var(--neutral-700);
  margin-bottom: .4rem;
}
label .req {
  color: var(--danger);
  margin-left: .15rem;
}
.hint {
  font-size: .78rem;
  color: var(--neutral-400);
  font-weight: 400;
  margin-top: .15rem;
  line-height: 1.4;
}

input[type="text"],
input[type="email"],
input[type="tel"],
input[type="url"],
select,
textarea {
  width: 100%;
  padding: .7rem 1rem;
  border: 1.5px solid var(--neutral-300);
  border-radius: var(--radius);
  font-family: var(--font);
  font-size: .92rem;
  color: var(--neutral-800);
  background: var(--neutral-50);
  transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
  appearance: none;
  -webkit-appearance: none;
}
input:focus, select:focus, textarea:focus {
  outline: none;
  border-color: var(--primary);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(79,70,229,.12);
}
input:hover:not(:focus), select:hover:not(:focus), textarea:hover:not(:focus) {
  border-color: var(--neutral-400);
}
textarea { min-height: 100px; resize: vertical; }

select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right .75rem center;
  background-size: 1.2rem;
  padding-right: 2.5rem;
}

/* Error states */
.has-error input,
.has-error select,
.has-error textarea {
  border-color: var(--danger);
  background: #fff5f5;
}
.has-error input:focus,
.has-error select:focus,
.has-error textarea:focus {
  box-shadow: 0 0 0 3px rgba(239,68,68,.12);
}
.field-error {
  display: flex;
  align-items: center;
  gap: .3rem;
  color: var(--danger);
  font-size: .78rem;
  font-weight: 500;
  margin-top: .4rem;
}
.field-error svg { width:14px; height:14px; flex-shrink:0; }

/* Radio & Checkbox groups */
.radio-group, .check-group {
  display: flex;
  flex-direction: column;
  gap: .55rem;
}
.radio-group.inline, .check-group.inline {
  flex-direction: row;
  flex-wrap: wrap;
  gap: .55rem;
}
.radio-item, .check-item {
  display: flex;
  align-items: center;
  gap: .55rem;
  cursor: pointer;
}
.radio-item input[type="radio"],
.check-item input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--primary);
  cursor: pointer;
  flex-shrink: 0;
}
.radio-item span, .check-item span {
  font-size: .88rem;
  color: var(--neutral-700);
  font-weight: 400;
}
.radio-card-group {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: .7rem;
}
.radio-card {
  position: relative;
}
.radio-card input { position: absolute; opacity: 0; width:0; height:0; }
.radio-card label {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .4rem;
  padding: .9rem .75rem;
  border: 1.5px solid var(--neutral-200);
  border-radius: var(--radius);
  background: var(--neutral-50);
  cursor: pointer;
  text-align: center;
  font-size: .82rem;
  font-weight: 500;
  color: var(--neutral-600);
  transition: var(--transition);
  margin: 0;
}
.radio-card label svg { width:22px; height:22px; color: var(--neutral-400); transition: var(--transition); }
.radio-card input:checked + label {
  border-color: var(--primary);
  background: var(--primary-light);
  color: var(--primary-dark);
}
.radio-card input:checked + label svg { color: var(--primary); }
.radio-card label:hover { border-color: var(--primary); background: var(--primary-light); }

/* ──────────────────────────────────────────────
   NAVIGATION BUTTONS
────────────────────────────────────────────── */
.form-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 2rem;
  background: var(--neutral-50);
  border-top: 1px solid var(--neutral-200);
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  padding: .7rem 1.5rem;
  border-radius: 9px;
  font-family: var(--font);
  font-size: .88rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  text-decoration: none;
  line-height: 1;
}
.btn svg { width:16px; height:16px; }
.btn-outline {
  background: #fff;
  color: var(--neutral-600);
  border: 1.5px solid var(--neutral-300);
}
.btn-outline:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.btn-primary {
  background: linear-gradient(135deg, var(--primary), #7c3aed);
  color: #fff;
  box-shadow: 0 4px 14px rgba(79,70,229,.3);
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,70,229,.4); }
.btn-primary:active { transform: translateY(0); }
.btn-success {
  background: linear-gradient(135deg, var(--success), #059669);
  color: #fff;
  box-shadow: 0 4px 14px rgba(16,185,129,.3);
  font-size: .95rem;
  padding: .85rem 2rem;
}
.btn-success:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,185,129,.4); }
.btn-ghost {
  background: transparent;
  color: var(--neutral-500);
  border: 1.5px dashed var(--neutral-300);
}
.btn-ghost:hover { color: var(--neutral-700); border-color: var(--neutral-400); }
.btn:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }

/* ──────────────────────────────────────────────
   VALIDATION BANNER
────────────────────────────────────────────── */
.error-banner {
  margin: 1.5rem 2rem 0;
  padding: 1rem 1.25rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: var(--radius);
  color: var(--danger);
  font-size: .875rem;
  display: flex;
  gap: .75rem;
  align-items: flex-start;
}
.error-banner svg { width:20px; height:20px; flex-shrink:0; margin-top:.05rem; }
.error-banner strong { display: block; margin-bottom: .25rem; }

/* ──────────────────────────────────────────────
   SUCCESS SCREEN
────────────────────────────────────────────── */
.success-screen {
  padding: 4rem 2rem;
  text-align: center;
}
.success-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--success), #059669);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  box-shadow: 0 8px 30px rgba(16,185,129,.3);
}
.success-icon svg { width:40px; height:40px; color:#fff; }
.success-screen h2 {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--neutral-900);
  margin-bottom: .75rem;
}
.success-screen p {
  color: var(--neutral-500);
  max-width: 500px;
  margin: 0 auto 2rem;
  font-size: .95rem;
}
.success-steps {
  display: flex;
  justify-content: center;
  gap: 1.5rem;
  flex-wrap: wrap;
  margin-top: 2rem;
}
.success-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .5rem;
  background: var(--neutral-50);
  border: 1px solid var(--neutral-200);
  border-radius: var(--radius);
  padding: 1.25rem 1.5rem;
  width: 160px;
}
.success-step svg { width:28px; height:28px; color: var(--primary); }
.success-step span { font-size: .8rem; font-weight: 600; color: var(--neutral-600); text-align:center; }

/* ──────────────────────────────────────────────
   SECTION SUMMARY (sidebar dots)
────────────────────────────────────────────── */
.section-tabs {
  display: flex;
  overflow-x: auto;
  gap: .5rem;
  padding: 1rem 2rem;
  border-bottom: 1px solid var(--neutral-100);
  scrollbar-width: none;
}
.section-tabs::-webkit-scrollbar { display:none; }
.section-tab {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .4rem .8rem;
  border-radius: 999px;
  font-size: .75rem;
  font-weight: 600;
  color: var(--neutral-500);
  background: var(--neutral-100);
  white-space: nowrap;
  cursor: pointer;
  transition: var(--transition);
  border: none;
  font-family: var(--font);
}
.section-tab.active {
  background: var(--primary-light);
  color: var(--primary-dark);
}
.section-tab.done {
  background: #d1fae5;
  color: #065f46;
}
.section-tab .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

/* ──────────────────────────────────────────────
   CHARACTER COUNTER
────────────────────────────────────────────── */
.char-counter {
  font-size: .72rem;
  color: var(--neutral-400);
  text-align: right;
  margin-top: .2rem;
}

/* ──────────────────────────────────────────────
   DIVIDER
────────────────────────────────────────────── */
.divider { border: none; border-top: 1px solid var(--neutral-100); margin: 1.25rem 0; }

/* ──────────────────────────────────────────────
   FOOTER
────────────────────────────────────────────── */
.form-footer {
  text-align: center;
  padding: 1.5rem;
  font-size: .8rem;
  color: var(--neutral-400);
}
.form-footer a { color: var(--primary); text-decoration: none; }

/* ──────────────────────────────────────────────
   RESPONSIVE
────────────────────────────────────────────── */
@media (max-width: 640px) {
  .site-header { padding: 2rem 1rem 4rem; }
  .form-section { padding: 1.5rem 1.25rem; }
  .form-nav { padding: 1.25rem; flex-wrap: wrap; gap: .75rem; }
  .contact-strip { padding: 1.5rem 1.25rem; }
  .section-tabs { padding: .75rem 1rem; }
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     HEADER
════════════════════════════════════════════ -->
<header class="site-header">
  <div class="logo">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
    </svg>
    Your Agency
  </div>
  <div class="header-badge">
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    Confidential & Secure
  </div>
  <h1>Website Project Discovery</h1>
  <p>Help us understand your vision so we can build something extraordinary together.</p>
</header>

<!-- ═══════════════════════════════════════════
     MAIN
════════════════════════════════════════════ -->
<main class="wrapper">

<?php if ($submitted): ?>
<!-- ─── SUCCESS ─── -->
<div class="form-card">
  <div class="success-screen">
    <div class="success-icon">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
      </svg>
    </div>
    <h2>Questionnaire Submitted!</h2>
    <p><?= htmlspecialchars($successMsg) ?></p>
    <div class="success-steps">
      <div class="success-step">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <span>We review your answers</span>
      </div>
      <div class="success-step">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <span>Discovery call scheduled</span>
      </div>
      <div class="success-step">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Proposal delivered</span>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ─── ERROR BANNER ─── -->
<?php if (!empty($errors)): ?>
<div class="form-card" style="margin-bottom:1rem;">
  <div class="error-banner">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    <div>
      <strong>Please fix <?= count($errors) ?> error(s) below</strong>
      Scroll through the form and complete all required fields marked in red.
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ─── PROGRESS ─── -->
<div class="progress-wrap" id="progressWrap">
  <span class="progress-label" id="progressLabel">Section 1 of 7</span>
  <div class="progress-bar-track">
    <div class="progress-bar-fill" id="progressFill" style="width:14.28%"></div>
  </div>
  <div class="step-dots" id="stepDots"></div>
</div>

<!-- ─── FORM ─── -->
<div class="form-card">

  <!-- CONTACT STRIP (always visible) -->
  <div class="contact-strip">
    <h2>
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
      </svg>
      Your Contact Information
    </h2>
    <div class="field-row">
      <div class="field-group<?= hasErr('client_name') ?>">
        <label for="client_name">Full Name <span class="req">*</span></label>
        <input type="text" id="client_name" name="client_name" value="<?= val('client_name') ?>"
               placeholder="Jane Smith" required autocomplete="name"
               aria-describedby="err_client_name">
        <?= err('client_name') ?>
      </div>
      <div class="field-group<?= hasErr('company_name') ?>">
        <label for="company_name">Company / Brand Name <span class="req">*</span></label>
        <input type="text" id="company_name" name="company_name" value="<?= val('company_name') ?>"
               placeholder="Acme Corp" required autocomplete="organization"
               aria-describedby="err_company_name">
        <?= err('company_name') ?>
      </div>
      <div class="field-group<?= hasErr('client_email') ?>">
        <label for="client_email">Email Address <span class="req">*</span></label>
        <input type="email" id="client_email" name="client_email" value="<?= val('client_email') ?>"
               placeholder="jane@example.com" required autocomplete="email"
               aria-describedby="err_client_email">
        <?= err('client_email') ?>
      </div>
      <div class="field-group">
        <label for="client_phone">Phone Number</label>
        <input type="tel" id="client_phone" name="client_phone" value="<?= val('client_phone') ?>"
               placeholder="+1 (555) 000-0000" autocomplete="tel">
      </div>
    </div>
  </div>

  <!-- SECTION TABS -->
  <div class="section-tabs" id="sectionTabs" role="tablist" aria-label="Form sections">
    <?php
    $tabs = [
      '1','2','3','4','5','6','7'
    ];
    $tabNames = [
      '1'=>'Goals','2'=>'Audience','3'=>'Content','4'=>'Design',
      '5'=>'Technical','6'=>'Logistics','7'=>'Post-Launch'
    ];
    foreach($tabNames as $num => $name): ?>
    <button type="button" class="section-tab<?= $num==='1'?' active':'' ?>"
            id="tab-<?= $num ?>" data-section="<?= $num ?>"
            role="tab" aria-selected="<?= $num==='1'?'true':'false' ?>"
            aria-controls="section-<?= $num ?>">
      <span class="dot"></span>
      <?= $num ?>. <?= $name ?>
    </button>
    <?php endforeach; ?>
  </div>

  <form method="POST" action="#" id="intakeForm" novalidate>

    <!-- ══════════════════════════════════════
         SECTION 1 — Business & Project Goals
    ══════════════════════════════════════ -->
    <div class="form-section active" id="section-1" role="tabpanel" aria-labelledby="tab-1">
      <div class="section-header">
        <div class="section-number">1</div>
        <div class="section-title">
          <h2>Business &amp; Project Goals</h2>
          <p>Help us understand what you're building and why.</p>
        </div>
      </div>

      <div class="field-group<?= hasErr('primary_purpose') ?>">
        <label for="primary_purpose">What is the primary purpose of the website? <span class="req">*</span></label>
        <textarea id="primary_purpose" name="primary_purpose" rows="3"
                  placeholder="e.g. Generate qualified leads for our B2B SaaS product, showcase our portfolio, sell products online…"
                  required maxlength="600"><?= val('primary_purpose') ?></textarea>
        <div class="hint">Be as specific as possible — this shapes the entire project.</div>
        <?= err('primary_purpose') ?>
      </div>

      <div class="field-group">
        <label for="objectives">List your top 3–5 specific objectives for this project</label>
        <textarea id="objectives" name="objectives" rows="4"
                  placeholder="1. Increase organic traffic by 40%&#10;2. Improve conversion rate on the pricing page&#10;3. Reduce support tickets with a better FAQ/docs section"
                  maxlength="800"><?= val('objectives') ?></textarea>
      </div>

      <div class="field-group<?= hasErr('project_type') ?>">
        <label>Project Type <span class="req">*</span></label>
        <div class="radio-card-group">
          <div class="radio-card">
            <input type="radio" id="pt_new" name="project_type" value="New Build" <?= checked('project_type','New Build') ?> required>
            <label for="pt_new">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
              New Build
            </label>
          </div>
          <div class="radio-card">
            <input type="radio" id="pt_redesign" name="project_type" value="Full Redesign" <?= checked('project_type','Full Redesign') ?>>
            <label for="pt_redesign">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              Full Redesign
            </label>
          </div>
          <div class="radio-card">
            <input type="radio" id="pt_refresh" name="project_type" value="Partial Refresh" <?= checked('project_type','Partial Refresh') ?>>
            <label for="pt_refresh">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              Partial Refresh
            </label>
          </div>
          <div class="radio-card">
            <input type="radio" id="pt_landing" name="project_type" value="Landing Page" <?= checked('project_type','Landing Page') ?>>
            <label for="pt_landing">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Landing Page
            </label>
          </div>
          <div class="radio-card">
            <input type="radio" id="pt_ecomm" name="project_type" value="E-Commerce" <?= checked('project_type','E-Commerce') ?>>
            <label for="pt_ecomm">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              E-Commerce
            </label>
          </div>
        </div>
        <?= err('project_type') ?>
      </div>

      <div class="field-group">
        <label for="success_metrics">How will you measure the success of this project?</label>
        <textarea id="success_metrics" name="success_metrics" rows="3"
                  placeholder="e.g. 20% increase in lead form submissions, lower bounce rate below 50%, 500 monthly sales…"
                  maxlength="600"><?= val('success_metrics') ?></textarea>
      </div>

      <div class="field-row">
        <div class="field-group<?= hasErr('budget_range') ?>">
          <label for="budget_range">Budget Range <span class="req">*</span></label>
          <select id="budget_range" name="budget_range" required>
            <option value="" disabled <?= empty(val('budget_range'))?'selected':'' ?>>Select a range…</option>
            <option value="Under $2,000"        <?= selected('budget_range','Under $2,000') ?>>Under \$2,000</option>
            <option value="$2,000 – $5,000"     <?= selected('budget_range','$2,000 – $5,000') ?>>\$2,000 – \$5,000</option>
            <option value="$5,000 – $10,000"    <?= selected('budget_range','$5,000 – $10,000') ?>>\$5,000 – \$10,000</option>
            <option value="$10,000 – $25,000"   <?= selected('budget_range','$10,000 – $25,000') ?>>\$10,000 – \$25,000</option>
            <option value="$25,000 – $50,000"   <?= selected('budget_range','$25,000 – $50,000') ?>>\$25,000 – \$50,000</option>
            <option value="$50,000+"            <?= selected('budget_range','$50,000+') ?>>\$50,000+</option>
            <option value="Flexible / TBD"      <?= selected('budget_range','Flexible / TBD') ?>>Flexible / TBD</option>
          </select>
          <?= err('budget_range') ?>
        </div>
        <div class="field-group">
          <label for="timeline">Ideal Launch Timeline</label>
          <select id="timeline" name="timeline">
            <option value="" disabled <?= empty(val('timeline'))?'selected':'' ?>>Select timeline…</option>
            <option value="ASAP (1–4 weeks)"        <?= selected('timeline','ASAP (1–4 weeks)') ?>>ASAP (1–4 weeks)</option>
            <option value="1–2 months"              <?= selected('timeline','1–2 months') ?>>1–2 months</option>
            <option value="2–4 months"              <?= selected('timeline','2–4 months') ?>>2–4 months</option>
            <option value="4–6 months"              <?= selected('timeline','4–6 months') ?>>4–6 months</option>
            <option value="6+ months"               <?= selected('timeline','6+ months') ?>>6+ months</option>
            <option value="Flexible"                <?= selected('timeline','Flexible') ?>>Flexible</option>
          </select>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         SECTION 2 — Target Audience & Competitors
    ══════════════════════════════════════ -->
    <div class="form-section" id="section-2" role="tabpanel" aria-labelledby="tab-2">
      <div class="section-header">
        <div class="section-number">2</div>
        <div class="section-title">
          <h2>Target Audience &amp; Competitors</h2>
          <p>Understanding your audience and market landscape.</p>
        </div>
      </div>

      <div class="field-group<?= hasErr('target_audience') ?>">
        <label for="target_audience">Describe your target audience <span class="req">*</span></label>
        <textarea id="target_audience" name="target_audience" rows="4"
                  placeholder="Age, location, profession, interests, pain points, how they typically find you, what devices they use…"
                  required maxlength="800"><?= val('target_audience') ?></textarea>
        <div class="hint">Include demographics, psychographics, and any segments if applicable.</div>
        <?= err('target_audience') ?>
      </div>

      <div class="field-group">
        <label for="competitors">List your top 3–5 competitors (with URLs if possible)</label>
        <textarea id="competitors" name="competitors" rows="4"
                  placeholder="1. Competitor A — https://competitora.com (we like their pricing page)&#10;2. Competitor B — what they do well or poorly…"
                  maxlength="800"><?= val('competitors') ?></textarea>
      </div>

      <div class="field-group<?= hasErr('uvp') ?>">
        <label for="uvp">What is your unique value proposition? <span class="req">*</span></label>
        <textarea id="uvp" name="uvp" rows="3"
                  placeholder="What makes you different from competitors? Why should visitors choose you?"
                  required maxlength="600"><?= val('uvp') ?></textarea>
        <?= err('uvp') ?>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         SECTION 3 — Content & Branding
    ══════════════════════════════════════ -->
    <div class="form-section" id="section-3" role="tabpanel" aria-labelledby="tab-3">
      <div class="section-header">
        <div class="section-number">3</div>
        <div class="section-title">
          <h2>Content &amp; Branding</h2>
          <p>What content and brand assets exist today?</p>
        </div>
      </div>

      <div class="field-group">
        <label>Which brand assets do you already have?</label>
        <div class="check-group inline">
          <?php $ba = $formData['brand_assets'] ?? []; ?>
          <?php $assetOpts = ['Logo (vector)','Brand guidelines','Color palette','Typography guide','Photography','Illustrations','Icons','None yet']; ?>
          <?php foreach($assetOpts as $a): ?>
          <label class="check-item">
            <input type="checkbox" name="brand_assets[]" value="<?= htmlspecialchars($a) ?>"
                   <?= in_array($a,$ba)?'checked':'' ?>>
            <span><?= htmlspecialchars($a) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field-group">
        <label for="content_inventory">Existing content inventory</label>
        <textarea id="content_inventory" name="content_inventory" rows="3"
                  placeholder="What content currently exists? Text, images, videos, case studies, blog posts, testimonials…"
                  maxlength="600"><?= val('content_inventory') ?></textarea>
      </div>

      <div class="field-group">
        <label for="content_owner">Who will be responsible for creating new content?</label>
        <div class="radio-group inline">
          <?php $contentOwnerOpts = ['Client team','Your agency','Freelance copywriter','Shared responsibility']; ?>
          <?php foreach($contentOwnerOpts as $co): ?>
          <label class="radio-item">
            <input type="radio" name="content_owner" value="<?= htmlspecialchars($co) ?>"
                   <?= checked('content_owner',$co) ?>>
            <span><?= htmlspecialchars($co) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field-group<?= hasErr('required_pages') ?>">
        <label for="required_pages">List all required pages / sections <span class="req">*</span></label>
        <textarea id="required_pages" name="required_pages" rows="4"
                  placeholder="Home, About, Services (with sub-pages for each service), Case Studies, Blog, Contact, Privacy Policy…"
                  required maxlength="600"><?= val('required_pages') ?></textarea>
        <?= err('required_pages') ?>
      </div>

      <div class="field-group">
        <label for="tone_of_voice">Tone of voice &amp; brand personality</label>
        <div class="radio-card-group">
          <?php
          $tones = [
            ['Professional','🏢'],['Friendly','😊'],['Bold &amp; Edgy','⚡'],
            ['Minimal &amp; Clean','✦'],['Playful','🎉'],['Authoritative','📣']
          ];
          foreach($tones as [$t,$e]):
            $clean_t = strip_tags($t);
          ?>
          <div class="radio-card">
            <input type="radio" id="tone_<?= $clean_t ?>" name="tone_of_voice"
                   value="<?= htmlspecialchars($clean_t) ?>" <?= checked('tone_of_voice',$clean_t) ?>>
            <label for="tone_<?= $clean_t ?>"><?= $e ?><br><?= $t ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         SECTION 4 — Design Preferences
    ══════════════════════════════════════ -->
    <div class="form-section" id="section-4" role="tabpanel" aria-labelledby="tab-4">
      <div class="section-header">
        <div class="section-number">4</div>
        <div class="section-title">
          <h2>Design Preferences</h2>
          <p>Let's align on the visual direction.</p>
        </div>
      </div>

      <div class="field-group">
        <label for="style_refs">Style references — websites or designs you love</label>
        <textarea id="style_refs" name="style_refs" rows="3"
                  placeholder="https://stripe.com — love the clean design&#10;https://linear.app — great use of dark mode&#10;Please note what specifically you like about each."
                  maxlength="800"><?= val('style_refs') ?></textarea>
        <div class="hint">Share URLs and note what specifically appeals to you (layout, colors, typography, imagery, etc.).</div>
      </div>

      <div class="field-group">
        <label for="color_mood">Color palette &amp; mood preferences</label>
        <input type="text" id="color_mood" name="color_mood" value="<?= val('color_mood') ?>"
               placeholder="e.g. Deep navy blue with gold accents, clean whites — feel: corporate yet approachable">
        <div class="hint">Share hex codes, describe moods/feelings, or describe what to avoid.</div>
      </div>

      <div class="field-group">
        <label>Layout preference</label>
        <div class="radio-group inline">
          <?php $layoutOpts = ['Minimal & spacious','Content-rich & detailed','Bold hero sections','Card-based layouts','Full-width imagery','Split-screen']; ?>
          <?php foreach($layoutOpts as $l): ?>
          <label class="check-item">
            <input type="radio" name="layout_pref" value="<?= htmlspecialchars($l) ?>" <?= checked('layout_pref',$l) ?>>
            <span><?= htmlspecialchars($l) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field-group">
        <label for="accessibility">Accessibility requirements</label>
        <select id="accessibility" name="accessibility">
          <option value="" disabled <?= empty(val('accessibility'))?'selected':'' ?>>Select a level…</option>
          <option value="WCAG 2.1 AA (recommended)" <?= selected('accessibility','WCAG 2.1 AA (recommended)') ?>>WCAG 2.1 AA (Recommended)</option>
          <option value="WCAG 2.1 AAA"              <?= selected('accessibility','WCAG 2.1 AAA') ?>>WCAG 2.1 AAA (highest)</option>
          <option value="Section 508"               <?= selected('accessibility','Section 508') ?>>Section 508 (US govt)</option>
          <option value="Basic accessibility only"  <?= selected('accessibility','Basic accessibility only') ?>>Basic accessibility only</option>
          <option value="Not sure / Advise us"      <?= selected('accessibility','Not sure / Advise us') ?>>Not sure — please advise</option>
        </select>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         SECTION 5 — Technical Requirements
    ══════════════════════════════════════ -->
    <div class="form-section" id="section-5" role="tabpanel" aria-labelledby="tab-5">
      <div class="section-header">
        <div class="section-number">5</div>
        <div class="section-title">
          <h2>Technical Requirements &amp; Functionality</h2>
          <p>Define the technical scope of the project.</p>
        </div>
      </div>

      <div class="field-group<?= hasErr('must_have_features') ?>">
        <label for="must_have_features">Must-have features &amp; functionality <span class="req">*</span></label>
        <textarea id="must_have_features" name="must_have_features" rows="4"
                  placeholder="Contact forms, live chat, user login/accounts, product search, booking calendar, multi-language support…"
                  required maxlength="800"><?= val('must_have_features') ?></textarea>
        <?= err('must_have_features') ?>
      </div>

      <div class="field-group">
        <label>Required integrations</label>
        <div class="check-group inline">
          <?php
          $intOpts = $formData['integrations'] ?? [];
          $integrations = ['CRM (HubSpot, Salesforce…)','Email marketing (Mailchimp, Klaviyo…)','Payment gateway (Stripe, PayPal…)','Analytics (GA4, Mixpanel…)','Live chat (Intercom, Crisp…)','Booking / Calendar','Social media feeds','ERP / Inventory system','Custom API'];
          foreach($integrations as $i): ?>
          <label class="check-item">
            <input type="checkbox" name="integrations[]" value="<?= htmlspecialchars($i) ?>"
                   <?= in_array($i,$intOpts)?'checked':'' ?>>
            <span><?= htmlspecialchars($i) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field-group">
        <label for="cms_pref">Preferred CMS / platform</label>
        <select id="cms_pref" name="cms_pref">
          <option value="" disabled <?= empty(val('cms_pref'))?'selected':'' ?>>Select a preference…</option>
          <option value="WordPress"         <?= selected('cms_pref','WordPress') ?>>WordPress</option>
          <option value="Webflow"           <?= selected('cms_pref','Webflow') ?>>Webflow</option>
          <option value="Shopify"           <?= selected('cms_pref','Shopify') ?>>Shopify</option>
          <option value="Squarespace"       <?= selected('cms_pref','Squarespace') ?>>Squarespace</option>
          <option value="Contentful"        <?= selected('cms_pref','Contentful') ?>>Contentful (headless)</option>
          <option value="Strapi"            <?= selected('cms_pref','Strapi') ?>>Strapi (headless)</option>
          <option value="Custom / Bespoke"  <?= selected('cms_pref','Custom / Bespoke') ?>>Custom / Bespoke</option>
          <option value="No preference"     <?= selected('cms_pref','No preference') ?>>No preference — advise us</option>
        </select>
      </div>

      <div class="field-row">
        <div class="field-group">
          <label>Responsive / mobile-friendly required?</label>
          <div class="radio-group inline">
            <?php foreach(['Yes, absolutely','Mainly desktop','Mobile-first'] as $r): ?>
            <label class="radio-item">
              <input type="radio" name="responsive" value="<?= htmlspecialchars($r) ?>" <?= checked('responsive',$r) ?>>
              <span><?= htmlspecialchars($r) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field-group">
          <label>SEO requirements</label>
          <div class="radio-group inline">
            <?php foreach(['Basic on-page SEO','Full technical SEO audit','Ongoing SEO support','None needed'] as $s): ?>
            <label class="radio-item">
              <input type="radio" name="seo_req" value="<?= htmlspecialchars($s) ?>" <?= checked('seo_req',$s) ?>>
              <span><?= htmlspecialchars($s) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="field-group">
        <label for="hosting_status">Hosting &amp; domain status</label>
        <select id="hosting_status" name="hosting_status">
          <option value="" disabled <?= empty(val('hosting_status'))?'selected':'' ?>>Select a status…</option>
          <option value="Have domain + hosting" <?= selected('hosting_status','Have domain + hosting') ?>>Already have domain &amp; hosting</option>
          <option value="Have domain only"      <?= selected('hosting_status','Have domain only') ?>>Have domain, need hosting</option>
          <option value="Need both"             <?= selected('hosting_status','Need both') ?>>Need both domain &amp; hosting</option>
          <option value="Please advise"         <?= selected('hosting_status','Please advise') ?>>Please advise</option>
        </select>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         SECTION 6 — Project Logistics & Scope
    ══════════════════════════════════════ -->
    <div class="form-section" id="section-6" role="tabpanel" aria-labelledby="tab-6">
      <div class="section-header">
        <div class="section-number">6</div>
        <div class="section-title">
          <h2>Project Logistics &amp; Scope</h2>
          <p>How will we work together?</p>
        </div>
      </div>

      <div class="field-group<?= hasErr('decision_makers') ?>">
        <label for="decision_makers">Who are the key decision-makers on your side? <span class="req">*</span></label>
        <input type="text" id="decision_makers" name="decision_makers" value="<?= val('decision_makers') ?>"
               placeholder="Jane (CEO, final approvals), Mark (Marketing Director, day-to-day contact)"
               required>
        <?= err('decision_makers') ?>
      </div>

      <div class="field-group">
        <label>Preferred communication method</label>
        <div class="radio-group inline">
          <?php foreach(['Email','Slack','Teams','Weekly video calls','Asana / Project tool'] as $c): ?>
          <label class="radio-item">
            <input type="radio" name="comm_pref" value="<?= htmlspecialchars($c) ?>" <?= checked('comm_pref',$c) ?>>
            <span><?= htmlspecialchars($c) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field-group">
        <label for="scope_boundaries">What is explicitly OUT of scope for this project?</label>
        <textarea id="scope_boundaries" name="scope_boundaries" rows="3"
                  placeholder="e.g. Mobile app, social media management, content writing, ongoing PPC ads…"
                  maxlength="600"><?= val('scope_boundaries') ?></textarea>
        <div class="hint">Defining boundaries now prevents scope creep later.</div>
      </div>

      <div class="field-row">
        <div class="field-group">
          <label for="revision_rounds">How many rounds of revisions do you expect?</label>
          <select id="revision_rounds" name="revision_rounds">
            <option value="" disabled <?= empty(val('revision_rounds'))?'selected':'' ?>>Select…</option>
            <?php foreach(['1 round','2 rounds','3 rounds','4+ rounds','TBD / Discuss'] as $rr): ?>
            <option value="<?= htmlspecialchars($rr) ?>" <?= selected('revision_rounds',$rr) ?>><?= htmlspecialchars($rr) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-group">
          <label for="payment_terms">Preferred payment terms</label>
          <select id="payment_terms" name="payment_terms">
            <option value="" disabled <?= empty(val('payment_terms'))?'selected':'' ?>>Select…</option>
            <?php foreach(['50% upfront, 50% on delivery','3 milestone payments','Monthly retainer','Full payment upfront','Net 30'] as $pt): ?>
            <option value="<?= htmlspecialchars($pt) ?>" <?= selected('payment_terms',$pt) ?>><?= htmlspecialchars($pt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         SECTION 7 — Post-Launch & Maintenance
    ══════════════════════════════════════ -->
    <div class="form-section" id="section-7" role="tabpanel" aria-labelledby="tab-7">
      <div class="section-header">
        <div class="section-number">7</div>
        <div class="section-title">
          <h2>Post-Launch &amp; Maintenance</h2>
          <p>Planning for the long-term success of your site.</p>
        </div>
      </div>

      <div class="field-group">
        <label>Will your team need training to manage the site?</label>
        <div class="radio-group inline">
          <?php foreach(['Yes — video tutorials','Yes — live training session','Minimal / self-service','No training needed'] as $tn): ?>
          <label class="radio-item">
            <input type="radio" name="training_needs" value="<?= htmlspecialchars($tn) ?>" <?= checked('training_needs',$tn) ?>>
            <span><?= htmlspecialchars($tn) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field-group">
        <label>Who will handle ongoing maintenance after launch?</label>
        <div class="radio-group inline">
          <?php foreach(['Our internal team','We need a maintenance retainer from you','TBD','No ongoing maintenance planned'] as $mr): ?>
          <label class="radio-item">
            <input type="radio" name="maintenance_resp" value="<?= htmlspecialchars($mr) ?>" <?= checked('maintenance_resp',$mr) ?>>
            <span><?= htmlspecialchars($mr) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field-group">
        <label for="future_roadmap">Future roadmap — what comes after the initial launch?</label>
        <textarea id="future_roadmap" name="future_roadmap" rows="4"
                  placeholder="Phase 2 features, multilingual expansion, mobile app, AI chatbot, membership portal…"
                  maxlength="800"><?= val('future_roadmap') ?></textarea>
        <div class="hint">Knowing your roadmap helps us architect the site to support future growth.</div>
      </div>

      <!-- FINAL SUBMIT -->
      <div style="background:linear-gradient(135deg,var(--primary-light),#f0fdf4);border-radius:12px;padding:1.75rem;margin-top:1.5rem;text-align:center;">
        <p style="font-size:.9rem;color:var(--neutral-600);margin-bottom:1.25rem;">
          🎉 You're all set! Review your answers and submit when ready. We'll be in touch within 1–2 business days.
        </p>
        <button type="submit" class="btn btn-success" id="submitBtn">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
          Submit Questionnaire
        </button>
      </div>
    </div>

    <!-- NAVIGATION -->
    <div class="form-nav" id="formNav">
      <button type="button" class="btn btn-outline" id="btnPrev" onclick="navigateSection(-1)" disabled>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Previous
      </button>
      <span id="navSectionLabel" style="font-size:.82rem;color:var(--neutral-400);font-weight:600;"></span>
      <button type="button" class="btn btn-primary" id="btnNext" onclick="navigateSection(1)">
        Next
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </button>
    </div>

  </form>
</div>

<div class="form-footer">
  Your responses are handled securely and kept confidential. &nbsp;|&nbsp;
  Questions? <a href="mailto:hello@youragency.com">hello@youragency.com</a>
</div>
<?php endif; ?>

</main>

<!-- ═══════════════════════════════════════════
     JAVASCRIPT — multi-step navigation & validation
════════════════════════════════════════════ -->
<script>
(function() {
  const TOTAL = 7;
  let current = 1;

  const sections   = () => Array.from(document.querySelectorAll('.form-section'));
  const tabs       = () => Array.from(document.querySelectorAll('.section-tab'));
  const fill       = document.getElementById('progressFill');
  const label      = document.getElementById('progressLabel');
  const dotsWrap   = document.getElementById('stepDots');
  const btnPrev    = document.getElementById('btnPrev');
  const btnNext    = document.getElementById('btnNext');
  const navLbl     = document.getElementById('navSectionLabel');

  // Build step dots
  for (let i = 1; i <= TOTAL; i++) {
    const d = document.createElement('span');
    d.className = 'step-dot' + (i === 1 ? ' active' : '');
    d.dataset.step = i;
    d.title = 'Section ' + i;
    d.addEventListener('click', () => goTo(i));
    dotsWrap.appendChild(d);
  }

  // Tab click
  tabs().forEach(tab => {
    tab.addEventListener('click', () => goTo(parseInt(tab.dataset.section)));
  });

  function goTo(n) {
    if (n < 1 || n > TOTAL) return;
    sections()[current - 1].classList.remove('active');
    tabs()[current - 1].classList.remove('active');
    tabs()[current - 1].setAttribute('aria-selected', 'false');

    current = n;

    sections()[current - 1].classList.add('active');
    tabs()[current - 1].classList.add('active');
    tabs()[current - 1].setAttribute('aria-selected', 'true');
    tabs()[current - 1].scrollIntoView({ behavior:'smooth', block:'nearest', inline:'center' });

    updateUI();
    // Scroll to top of form card
    document.querySelector('.form-card').scrollIntoView({ behavior:'smooth', block:'start' });
  }

  function updateUI() {
    const pct = (current / TOTAL) * 100;
    fill.style.width = pct + '%';
    label.textContent = 'Section ' + current + ' of ' + TOTAL;
    navLbl.textContent = getSectionName(current);

    // Dots
    document.querySelectorAll('.step-dot').forEach(d => {
      const s = parseInt(d.dataset.step);
      d.className = 'step-dot' + (s === current ? ' active' : s < current ? ' done' : '');
    });

    // Tabs done state
    tabs().forEach((t, i) => {
      if (i + 1 < current) t.classList.add('done');
      else t.classList.remove('done');
    });

    btnPrev.disabled = current === 1;

    if (current === TOTAL) {
      btnNext.style.display = 'none';
    } else {
      btnNext.style.display = '';
    }
  }

  function getSectionName(n) {
    const names = ['Business & Goals','Target Audience','Content & Branding','Design','Technical','Logistics','Post-Launch'];
    return names[n - 1] || '';
  }

  window.navigateSection = function(dir) {
    if (dir === 1 && current < TOTAL) {
      goTo(current + 1);
    } else if (dir === -1 && current > 1) {
      goTo(current - 1);
    }
  };

  // Client-side validation on submit
  document.getElementById('intakeForm').addEventListener('submit', function(e) {
    let valid = true;
    const required = this.querySelectorAll('[required]');
    required.forEach(el => {
      if (!el.value.trim()) {
        valid = false;
        el.closest('.field-group')?.classList.add('has-error');
      } else {
        el.closest('.field-group')?.classList.remove('has-error');
      }
    });

    // Email validation
    const emailField = this.querySelector('[type="email"]');
    if (emailField && emailField.value) {
      const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRx.test(emailField.value)) {
        valid = false;
        emailField.closest('.field-group')?.classList.add('has-error');
      }
    }

    if (!valid) {
      e.preventDefault();
      // Jump to first error section
      const firstErr = document.querySelector('.has-error');
      if (firstErr) {
        const sec = firstErr.closest('.form-section');
        if (sec) goTo(parseInt(sec.id.split('-')[1]));
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });

  // Real-time inline validation
  document.querySelectorAll('[required]').forEach(el => {
    el.addEventListener('blur', () => {
      if (!el.value.trim()) {
        el.closest('.field-group')?.classList.add('has-error');
      } else {
        el.closest('.field-group')?.classList.remove('has-error');
      }
    });
    el.addEventListener('input', () => {
      if (el.value.trim()) {
        el.closest('.field-group')?.classList.remove('has-error');
      }
    });
  });

  // Character counters for textareas
  document.querySelectorAll('textarea[maxlength]').forEach(ta => {
    const max = parseInt(ta.getAttribute('maxlength'));
    const counter = document.createElement('div');
    counter.className = 'char-counter';
    counter.textContent = `0 / ${max}`;
    ta.parentNode.insertBefore(counter, ta.nextSibling?.nextSibling || null);
    ta.insertAdjacentElement('afterend', counter);
    ta.addEventListener('input', () => {
      counter.textContent = `${ta.value.length} / ${max}`;
      counter.style.color = ta.value.length > max * 0.9 ? 'var(--warning)' : 'var(--neutral-400)';
    });
    counter.textContent = `${ta.value.length} / ${max}`;
  });

  // If PHP returned errors, jump to the first broken section
  <?php if (!empty($errors)): ?>
  (function(){
    const errorSections = {
      'client_name': 0, 'company_name': 0, 'client_email': 0,
      'primary_purpose': 1, 'project_type': 1, 'budget_range': 1,
      'target_audience': 2, 'uvp': 2,
      'required_pages': 3,
      'must_have_features': 5,
      'decision_makers': 6,
    };
    const errKeys = <?= json_encode(array_keys($errors)) ?>;
    let minSection = 999;
    errKeys.forEach(k => {
      if (errorSections[k] !== undefined && errorSections[k] < minSection) {
        minSection = errorSections[k];
      }
    });
    if (minSection < 999 && minSection > 0) goTo(minSection + 1);
  })();
  <?php endif; ?>

  updateUI();
})();
</script>

</body>
</html>
