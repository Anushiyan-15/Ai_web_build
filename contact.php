<?php
require_once __DIR__ . '/config.php';
$page_title = 'Contact Us';
$page_desc  = 'Get in touch with our team. We\'d love to hear about your project.';

$sent = false;
$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name']    = htmlspecialchars(strip_tags(trim($_POST['name']    ?? '')));
    $formData['email']   = htmlspecialchars(strip_tags(trim($_POST['email']   ?? '')));
    $formData['subject'] = htmlspecialchars(strip_tags(trim($_POST['subject'] ?? '')));
    $formData['message'] = htmlspecialchars(strip_tags(trim($_POST['message'] ?? '')));

    if (empty($formData['name']))                          $errors['name']    = 'Name is required.';
    if (empty($formData['email']))                         $errors['email']   = 'Email is required.';
    elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email.';
    if (empty($formData['subject']))                       $errors['subject'] = 'Subject is required.';
    if (empty($formData['message']))                       $errors['message'] = 'Message is required.';
    elseif (strlen($formData['message']) < 20)             $errors['message'] = 'Message must be at least 20 characters.';

    if (empty($errors)) {
        // Save contact submission to storage/submissions/
        $dir = defined('STORAGE_DIR') ? (STORAGE_DIR . '/submissions') : (__DIR__ . '/storage/submissions');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/contact_' . date('Ymd_His') . '_' . uniqid() . '.json',
            json_encode(array_merge($formData, ['submitted_at' => date('Y-m-d H:i:s'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']), JSON_PRETTY_PRINT)
        );

        // Optionally insert into database if available
        require_once __DIR__ . '/includes/db.php';
        $pdo = getDb();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message, created_at) VALUES (:name, :email, :message, NOW())");
                $stmt->execute([
                    ':name'    => $formData['name'],
                    ':email'   => $formData['email'],
                    ':message' => "Subject: " . $formData['subject'] . "\n\n" . $formData['message']
                ]);
            } catch (Exception $e) {
                error_log("DB contact error: " . $e->getMessage());
            }
        }

        if (defined('CONTACT_EMAIL') && !empty(CONTACT_EMAIL)) {
            @mail(CONTACT_EMAIL, 'New Contact: ' . $formData['subject'], print_r($formData, true), 'From: ' . $formData['email']);
        }
        $sent = true;
        $formData = [];
    }
}

function cv(string $k): string { global $formData; return htmlspecialchars($formData[$k] ?? ''); }
function ce(string $k): string { global $errors; return isset($errors[$k]) ? '<span class="ferr">⚠ '.$errors[$k].'</span>' : ''; }
function ch(string $k): string { global $errors; return isset($errors[$k]) ? ' has-err' : ''; }

require_once __DIR__ . '/includes/nav.php';
?>
<style>
.contact-hero{
  background:linear-gradient(135deg,#ede9fe 0%,#faf5ff 50%,#ecfdf5 100%);
  padding:5rem 0 3rem;text-align:center;
}
.contact-grid{display:grid;grid-template-columns:1fr 1.8fr;gap:3rem;align-items:start;padding:4rem 0}
@media(max-width:820px){.contact-grid{grid-template-columns:1fr}}
.info-card{background:#fff;border-radius:16px;border:1px solid var(--n200);padding:1.5rem;margin-bottom:1.25rem;display:flex;gap:1rem;align-items:flex-start;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.info-icon{width:46px;height:46px;border-radius:12px;background:var(--pl);color:var(--p);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
.info-title{font-size:.8rem;font-weight:700;color:var(--n500);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem}
.info-text{font-size:.95rem;font-weight:600;color:var(--n900)}
.info-sub{font-size:.8rem;color:var(--n400);margin-top:.15rem}
.form-wrap{background:#fff;border-radius:20px;border:1px solid var(--n200);padding:2.5rem;box-shadow:0 8px 32px rgba(99,102,241,.1)}
.fgroup{margin-bottom:1.35rem}
label.flbl{display:block;font-size:.855rem;font-weight:600;color:var(--n700);margin-bottom:.4rem}
label.flbl .req{color:var(--d);margin-left:.15rem}
input.fin,select.fin,textarea.fin{
  width:100%;padding:.7rem 1rem;
  border:1.5px solid var(--n300);border-radius:10px;
  font-family:'Inter',sans-serif;font-size:.92rem;color:var(--n800);
  background:var(--n50);transition:.18s ease;
}
input.fin:focus,select.fin:focus,textarea.fin:focus{
  outline:none;border-color:var(--p);background:#fff;
  box-shadow:0 0 0 3px rgba(99,102,241,.12);
}
.has-err input,.has-err select,.has-err textarea,.has-err .fin{border-color:var(--d)!important;background:#fff5f5}
.ferr{display:block;color:var(--d);font-size:.78rem;font-weight:500;margin-top:.35rem}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:500px){.frow{grid-template-columns:1fr}}
textarea.fin{min-height:130px;resize:vertical}
.success-box{
  background:#ecfdf5;border:1px solid #6ee7b7;border-radius:14px;
  padding:2rem;text-align:center;
}
.success-box .s-icon{font-size:3rem;margin-bottom:.75rem}
.success-box h3{font-size:1.3rem;font-weight:800;color:#065f46;margin-bottom:.5rem}
.success-box p{color:#047857;font-size:.95rem}
</style>

<div class="contact-hero">
  <div class="container">
    <div class="badge">💬 Get In Touch</div>
    <h1 class="heading-lg" style="margin-bottom:.75rem">We'd love to hear from you</h1>
    <p class="lead center">Have a project in mind? Questions about our AI builder? Drop us a message.</p>
  </div>
</div>

<div class="container">
  <div class="contact-grid">
    <!-- LEFT: Info cards -->
    <div>
      <div class="info-card">
        <div class="info-icon">📧</div>
        <div>
          <div class="info-title">Email Us</div>
          <div class="info-text"><?= CONTACT_EMAIL ?></div>
          <div class="info-sub">We reply within 1–2 business days</div>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon">⚡</div>
        <div>
          <div class="info-title">Fastest Path</div>
          <div class="info-text">Use the AI Builder</div>
          <div class="info-sub"><a href="<?= SITE_URL ?>/builder.php" style="color:var(--p)">Start building for free →</a></div>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon">📋</div>
        <div>
          <div class="info-title">Custom Project?</div>
          <div class="info-text">Client Intake Form</div>
          <div class="info-sub"><a href="<?= SITE_URL ?>/client-intake.php" style="color:var(--p)">Fill out the questionnaire →</a></div>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon">🕒</div>
        <div>
          <div class="info-title">Business Hours</div>
          <div class="info-text">Mon–Fri, 9am–6pm</div>
          <div class="info-sub">Indian Standard Time (IST)</div>
        </div>
      </div>
    </div>

    <!-- RIGHT: Contact form -->
    <div class="form-wrap">
      <?php if ($sent): ?>
      <div class="success-box">
        <div class="s-icon">🎉</div>
        <h3>Message Received!</h3>
        <p>Thanks for reaching out. We'll get back to you within 1–2 business days.<br>
        Meanwhile, why not try the <a href="<?= SITE_URL ?>/builder.php" style="color:var(--p);font-weight:600">AI Builder</a>?</p>
      </div>
      <?php else: ?>
      <?php if (!empty($errors)): ?>
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:1rem;margin-bottom:1.5rem;color:var(--d);font-size:.875rem">
        ⚠ Please fix <?= count($errors) ?> error(s) below.
      </div>
      <?php endif; ?>
      <h2 style="font-size:1.25rem;font-weight:800;margin-bottom:1.5rem;color:var(--n900)">Send us a message</h2>
      <form method="POST" action="" novalidate>
        <div class="frow">
          <div class="fgroup<?= ch('name') ?>">
            <label class="flbl" for="name">Your Name <span class="req">*</span></label>
            <input class="fin" type="text" id="name" name="name" value="<?= cv('name') ?>"
                   placeholder="Jane Smith" required autocomplete="name">
            <?= ce('name') ?>
          </div>
          <div class="fgroup<?= ch('email') ?>">
            <label class="flbl" for="email">Email Address <span class="req">*</span></label>
            <input class="fin" type="email" id="email" name="email" value="<?= cv('email') ?>"
                   placeholder="jane@example.com" required autocomplete="email">
            <?= ce('email') ?>
          </div>
        </div>
        <div class="fgroup<?= ch('subject') ?>">
          <label class="flbl" for="subject">Subject <span class="req">*</span></label>
          <select class="fin" id="subject" name="subject" required>
            <option value="" disabled <?= empty(cv('subject'))?'selected':'' ?>>Select a topic…</option>
            <option value="AI Builder Help" <?= cv('subject')==='AI Builder Help'?'selected':'' ?>>AI Builder Help</option>
            <option value="Custom Website Project" <?= cv('subject')==='Custom Website Project'?'selected':'' ?>>Custom Website Project</option>
            <option value="Pricing & Quote" <?= cv('subject')==='Pricing & Quote'?'selected':'' ?>>Pricing &amp; Quote</option>
            <option value="Technical Issue" <?= cv('subject')==='Technical Issue'?'selected':'' ?>>Technical Issue</option>
            <option value="Partnership" <?= cv('subject')==='Partnership'?'selected':'' ?>>Partnership</option>
            <option value="Other" <?= cv('subject')==='Other'?'selected':'' ?>>Other</option>
          </select>
          <?= ce('subject') ?>
        </div>
        <div class="fgroup<?= ch('message') ?>">
          <label class="flbl" for="message">Message <span class="req">*</span></label>
          <textarea class="fin" id="message" name="message" placeholder="Tell us about your project or question…" required maxlength="2000"><?= cv('message') ?></textarea>
          <?= ce('message') ?>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:.85rem;font-size:.95rem">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
          Send Message
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
