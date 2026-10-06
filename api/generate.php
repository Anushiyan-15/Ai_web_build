<?php
// ═══════════════════════════════════════════════════════════════
//  api/generate.php — Multi-Design AI Website Generator Engine
//  FIXED VERSION:
//   - Single-model policy: gemini-3.5-flash-lite ONLY (verified 2026-10-05,
//     full page ~22s, fits Apache 30s). No other Gemini models.
//   - Server-side change detection — never lies "updated"
//   - Higher maxOutputTokens for full HTML documents
//   - Better Gemini error reporting
//   - Server templates carry taste safety net + working shop cart
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/TasteSkill.php';
require_once dirname(__DIR__) . '/includes/FlowCraftService.php';

$raw  = file_get_contents('php://input');
$req  = json_decode($raw, true) ?: [];
$action = $req['action'] ?? 'generate_3';

// Resolve API key: user-supplied first, then server config
$apiKey = trim($req['api_key'] ?? '');
if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
}

$resolvedModel = (defined('GEMINI_MODEL') && GEMINI_MODEL)
    ? GEMINI_MODEL
    : 'gemini-3.5-flash-lite'; // single-model policy: lite ONLY

// ── Sanitize helper ───────────────────────────────────────────
$sanitize = function ($v) {
    return htmlspecialchars(strip_tags(trim((string)($v ?? ''))), ENT_QUOTES, 'UTF-8');
};

$d = $req['data'] ?? [];
$bizName     = $sanitize($d['biz_name'] ?? $req['biz_name'] ?? 'Apex Studio');
if (empty($bizName)) $bizName = 'Apex Studio';
$bizType     = $sanitize($d['biz_type'] ?? $req['biz_type'] ?? 'Creative Agency');
$bizTagline  = $sanitize($d['biz_tagline'] ?? $req['biz_tagline'] ?? 'Elevate your digital presence with modern web solutions.');
$bizAudience = $sanitize($d['biz_audience'] ?? $req['biz_audience'] ?? 'Modern businesses, entrepreneurs, and clients seeking premium quality.');
$bizServices = $sanitize($d['biz_services'] ?? $req['biz_services'] ?? 'Web Design, Brand Strategy, Digital Marketing, Custom Development');
$style       = $sanitize($d['design_style'] ?? $req['design_style'] ?? $req['style'] ?? 'modern');
$palette     = $sanitize($d['color_palette'] ?? $req['color_palette'] ?? $req['palette'] ?? 'purple');
$bizPhone    = $sanitize($d['biz_phone'] ?? $req['biz_phone'] ?? '+1 (555) 234-5678');
$bizEmail    = $sanitize($d['biz_email'] ?? $req['biz_email'] ?? ('contact@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $bizName)) . '.com'));
$bizAddress  = $sanitize($d['biz_address'] ?? $req['biz_address'] ?? '100 Innovation Blvd, Suite 400, Tech City');
// Pasted customer reviews (Solo-style import) — shared by every generator path
$customReviews = parseReviews($d['biz_reviews'] ?? $req['biz_reviews'] ?? '');

$colorPresets = [
    'purple' => ['primary' => '#6366f1', 'secondary' => '#a855f7', 'accent' => '#38bdf8', 'gradient' => 'linear-gradient(135deg, #6366f1 0%, #a855f7 100%)', 'light' => '#ede9fe'],
    'blue'   => ['primary' => '#2563eb', 'secondary' => '#06b6d4', 'accent' => '#38bdf8', 'gradient' => 'linear-gradient(135deg, #2563eb 0%, #06b6d4 100%)', 'light' => '#dbeafe'],
    'green'  => ['primary' => '#059669', 'secondary' => '#10b981', 'accent' => '#84cc16', 'gradient' => 'linear-gradient(135deg, #059669 0%, #10b981 100%)', 'light' => '#d1fae5'],
    'red'    => ['primary' => '#dc2626', 'secondary' => '#f97316', 'accent' => '#fbbf24', 'gradient' => 'linear-gradient(135deg, #dc2626 0%, #f97316 100%)', 'light' => '#fee2e2'],
    'gold'   => ['primary' => '#d97706', 'secondary' => '#f59e0b', 'accent' => '#ef4444', 'gradient' => 'linear-gradient(135deg, #b45309 0%, #f59e0b 100%)', 'light' => '#fef3c7'],
    'slate'  => ['primary' => '#1e293b', 'secondary' => '#475569', 'accent' => '#6366f1', 'gradient' => 'linear-gradient(135deg, #1e293b 0%, #334155 100%)', 'light' => '#f1f5f9'],
];
$cp = $colorPresets[$palette] ?? $colorPresets['purple'];

// ═══════════════════════════════════════════════════════════════
//  Helper: call Gemini API once, returns [ok, text, httpCode, errMsg]
// ═══════════════════════════════════════════════════════════════
function callGemini(string $apiKey, string $model, string $prompt, int $maxTokens = 32000): array {
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'
              . $model . ':generateContent?key=' . urlencode($apiKey);

    $payload = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => $maxTokens,
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!empty($curlErr)) {
        return [false, '', $code, 'Network error: ' . $curlErr];
    }
    if ($code !== 200) {
        $j = json_decode($res, true);
        $msg = $j['error']['message'] ?? ('HTTP ' . $code);
        error_log('[generate.php] Gemini HTTP ' . $code . ': ' . substr($res, 0, 500));
        return [false, '', $code, 'Gemini API error (' . $code . '): ' . $msg];
    }

    $parsed = json_decode($res, true);
    $text   = $parsed['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text)) {
        return [false, '', $code, 'Gemini returned empty response'];
    }
    return [true, $text, $code, null];
}

// ═══════════════════════════════════════════════════════════════
//  Shared JS bundle injected into every generated design
// ═══════════════════════════════════════════════════════════════
function getSharedJS($bizName) {
    return <<<JS
<script>
document.querySelectorAll('a').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    let targetId = this.getAttribute('href') || '';
    let targetEl = null;
    if (targetId && targetId.startsWith('#') && targetId.length > 1) {
      try { targetEl = document.querySelector(targetId); } catch(err) {}
    }
    if (!targetEl) {
      const rawText = this.innerText.trim().toLowerCase();
      const cleanSlug = rawText.replace(/[^a-z0-9]/g, '');
      if (cleanSlug.length >= 2) {
        targetEl = document.getElementById(cleanSlug) ||
                   document.getElementById(cleanSlug.replace('us', '')) ||
                   document.querySelector('section[id*="' + cleanSlug + '"]');
        if (!targetEl) {
          const headings = document.querySelectorAll('section h1, section h2, section h3, header h1, header h2');
          for (const h of headings) {
            const hText = h.innerText.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
            if (hText.includes(cleanSlug) || cleanSlug.includes(hText)) {
              targetEl = h.closest('section') || h.closest('header') || h;
              break;
            }
          }
        }
      }
    }
    if (targetEl) {
      e.preventDefault();
      targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (targetId === '#contact' || targetEl.id === 'contact') {
        const firstInput = targetEl.querySelector('input, textarea');
        if (firstInput) setTimeout(() => firstInput.focus(), 600);
      }
      const mob = document.getElementById('mobile-drawer');
      if (mob) mob.classList.remove('open');
    }
  });
});
function toggleMobileMenu() {
  const drawer = document.getElementById('mobile-drawer');
  if (drawer) drawer.classList.toggle('open');
}
const btt = document.getElementById('back-to-top');
if (btt) {
  window.addEventListener('scroll', () => {
    btt.style.display = (window.scrollY > 400) ? 'flex' : 'none';
  });
  btt.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}
function handleContactSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const name = form.querySelector('[name="name"], [type="text"]')?.value || 'Friend';
  const email = form.querySelector('[type="email"]')?.value || '';
  const btn = form.querySelector('button[type="submit"]');
  if (btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Sending Message…';
    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = origText;
      form.reset();
      let modal = document.getElementById('form-success-banner');
      if (!modal) {
        modal = document.createElement('div');
        modal.id = 'form-success-banner';
        modal.style.cssText = 'position:fixed;bottom:2rem;right:2rem;z-index:9999;background:#059669;color:#fff;padding:1.25rem 1.75rem;border-radius:14px;box-shadow:0 15px 35px rgba(0,0,0,0.3);display:flex;align-items:center;gap:1rem;font-size:0.95rem;font-weight:600;transition:all 0.3s;max-width:420px;';
        document.body.appendChild(modal);
      }
      modal.innerHTML = '<div style="font-size:1.8rem">🎉</div><div><div style="font-weight:700">Thank you, ' + name + '!</div><div style="font-size:0.85rem;opacity:0.9">Your message was delivered to {$bizName}. We will reply to ' + (email || 'your email') + ' within 24 hours.</div></div>';
      modal.style.opacity = '1';
      modal.style.transform = 'translateY(0)';
      setTimeout(() => {
        modal.style.opacity = '0';
        modal.style.transform = 'translateY(20px)';
      }, 5000);
    }, 800);
  }
}
function toggleFaq(el) {
  const body = el.nextElementSibling;
  const icon = el.querySelector('.faq-icon');
  if (body) {
    const isOpen = body.style.display === 'block';
    body.style.display = isOpen ? 'none' : 'block';
    if (icon) icon.textContent = isOpen ? '+' : '−';
  }
}
</script>
JS;
}

// ═══════════════════════════════════════════════════════════════
//  Niche Stock Assets & Google Maps Embed
// ═══════════════════════════════════════════════════════════════
function getNicheAssets($bizType, $bizName) {
    $t = strtolower($bizType . ' ' . $bizName);
    if (preg_match('/tech|software|app|saas|ai|digital|cyber|cloud|it\b|developer|data/i', $t)) {
        return [
            'hero' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1000&q=80',
            'showcase1' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=800&q=80',
            'showcase2' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80',
            'tag' => 'Next-Gen Technology'
        ];
    } elseif (preg_match('/restaurant|cafe|food|dining|bakery|bar|coffee|bistro|culinary/i', $t)) {
        return [
            'hero' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=1000&q=80',
            'showcase1' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=800&q=80',
            'showcase2' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=800&q=80',
            'tag' => 'Artisanal Dining & Hospitality'
        ];
    } elseif (preg_match('/fitness|gym|workout|trainer|crossfit|health|wellness|yoga|physio|sport/i', $t)) {
        return [
            'hero' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?auto=format&fit=crop&w=1000&q=80',
            'showcase1' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?auto=format&fit=crop&w=800&q=80',
            'showcase2' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?auto=format&fit=crop&w=800&q=80',
            'tag' => 'Elite Health & Performance'
        ];
    } elseif (preg_match('/dental|clinic|doctor|medical|hospital|therapy|care|pharma/i', $t)) {
        return [
            'hero' => 'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=1000&q=80',
            'showcase1' => 'https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=800&q=80',
            'showcase2' => 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80',
            'tag' => 'Modern Healthcare & Wellness'
        ];
    } elseif (preg_match('/law|legal|attorney|advocate|court|justice/i', $t)) {
        return [
            'hero' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1453733190071-0d931bd90b7b?auto=format&fit=crop&w=1000&q=80',
            'showcase1' => 'https://images.unsplash.com/photo-1479142506502-19b3a3b7ff33?auto=format&fit=crop&w=800&q=80',
            'showcase2' => 'https://images.unsplash.com/photo-1505664194779-8beaceb93744?auto=format&fit=crop&w=800&q=80',
            'tag' => 'Trusted Legal Counsel'
        ];
    } elseif (preg_match('/real estate|realtor|property|architecture|construction|builder|home|interior/i', $t)) {
        return [
            'hero' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1000&q=80',
            'showcase1' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=800&q=80',
            'showcase2' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=800&q=80',
            'tag' => 'Prime Architectural Spaces'
        ];
    } elseif (preg_match('/fashion|clothing|apparel|jewelry|boutique|beauty|salon|style/i', $t)) {
        return [
            'hero' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1000&q=80',
            'showcase1' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=800&q=80',
            'showcase2' => 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?auto=format&fit=crop&w=800&q=80',
            'tag' => 'Haute Couture & Elegance'
        ];
    } else {
        return [
            'hero' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1000&q=80',
            'showcase1' => 'https://images.unsplash.com/photo-1467232004584-a241de8bcf5d?auto=format&fit=crop&w=800&q=80',
            'showcase2' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800&q=80',
            'tag' => 'Strategic Growth & Craft'
        ];
    }
}

function getMapEmbed($bizAddress) {
    $bizAddress = trim($bizAddress ?? '');
    if (empty($bizAddress) || strtolower($bizAddress) === '(unspecified)') {
        return '';
    }
    $enc = urlencode($bizAddress);
    return <<<MAP
<div class="map-card" style="margin-top:1.75rem; border-radius:18px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 8px 24px rgba(0,0,0,0.06);">
  <iframe title="Business location" width="100%" height="220" style="border:0; display:block;" loading="lazy" allowfullscreen src="https://www.google.com/maps?q={$enc}&output=embed"></iframe>
  <div style="padding:0.75rem 1rem; background:#f8fafc; display:flex; justify-content:space-between; align-items:center; font-size:0.82rem; font-weight:600; color:#475569;">
    <span>📍 {$bizAddress}</span>
    <a href="https://www.google.com/maps/dir/?api=1&destination={$enc}" target="_blank" rel="noopener" style="color:var(--primary); font-weight:700;">Get Directions &rarr;</a>
  </div>
</div>
MAP;
}

// ═══════════════════════════════════════════════════════════════
//  SHOP SYSTEM — loaded from includes/ShopHelper.php
// ═══════════════════════════════════════════════════════════════
require_once dirname(__DIR__) . '/includes/ShopHelper.php';
// ═══════════════════════════════════════════════════════════════
//  SOLO-STYLE ENGINE — one-page professional sites for solo
//  consultants / coaches / therapists / tradespeople.
//  Pasted reviews render verbatim; any failure falls back to the
//  standard server template engine (buildDesign1).
// ═══════════════════════════════════════════════════════════════

function parseReviews($raw) {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)($raw ?? '')) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', explode('|', $line));
        $name = $parts[0] ?? '';
        $text = $parts[1] ?? '';
        if ($name === '' || $text === '') continue;
        $out[] = ['name' => $name, 'text' => $text, 'role' => ($parts[2] ?? 'Verified Client')];
        if (count($out) >= 6) break;
    }
    return $out;
}

function getSoloProfession($bizType) {
    $t = strtolower((string)($bizType ?? ''));
    $map = [
        'coach' => [
            'label' => 'Coach', 'emoji' => '🎯',
            'headline' => 'Unlock the next level of your life & work',
            'sub' => '1-on-1 coaching, group programs and workshops — practical change you can feel in weeks.',
            'services' => [['1-on-1 Coaching', 'Private sessions tailored to your goals, with clear action steps every week.'], ['Group Programs', 'Small cohorts, big momentum — learn alongside driven peers.'], ['Workshops', 'Half-day intensives for teams and communities.']],
            'cta' => 'Book a Free Discovery Call', 'tag' => 'Certified Professional Coach',
            'hero' => 'https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=1000&q=80'
        ],
        'therapist' => [
            'label' => 'Therapist', 'emoji' => '🌿',
            'headline' => 'A calmer mind starts with one conversation',
            'sub' => 'Confidential individual therapy, couples counseling and online sessions — at your pace.',
            'services' => [['Individual Therapy', 'A safe space to work through anxiety, stress and life transitions.'], ['Couples Counseling', 'Rebuild trust and communication, together.'], ['Online Sessions', 'Same quality of care, from the comfort of your home.']],
            'cta' => 'Book a Confidential Session', 'tag' => 'Licensed Mental Health Professional',
            'hero' => 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1573497620053-ea5300f94f21?auto=format&fit=crop&w=1000&q=80'
        ],
        'trade' => [
            'label' => 'Tradesperson', 'emoji' => '🔧',
            'headline' => 'Fixed right, the first time — guaranteed',
            'sub' => 'Repairs, installations and emergency callouts. Upfront pricing, tidy work, on time.',
            'services' => [['Repairs & Fixes', 'Fast diagnosis and durable repairs for home and office.'], ['Installations', 'Clean, code-compliant installs with full testing.'], ['Emergency Callout', 'Urgent problem? Same-day response when it matters.']],
            'cta' => 'Call Now for a Free Quote', 'tag' => 'Licensed & Insured Tradesperson',
            'hero' => 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=1000&q=80'
        ],
        'cleaning' => [
            'label' => 'Cleaning Pro', 'emoji' => '✨',
            'headline' => 'Spotless spaces, zero hassle',
            'sub' => 'Home cleaning, office contracts and deep cleans — vetted pros, eco products.',
            'services' => [['Home Cleaning', 'Recurring or one-off cleans that keep your home shining.'], ['Office Contracts', 'Reliable after-hours service for workplaces.'], ['Deep Cleaning', 'Top-to-bottom detail for move-ins, events and seasons.']],
            'cta' => 'Get an Instant Quote', 'tag' => 'Vetted 5-Star Cleaning Team',
            'hero' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1528740561666-dc2479dc08ab?auto=format&fit=crop&w=1000&q=80'
        ],
        'salon' => [
            'label' => 'Stylist', 'emoji' => '💇',
            'headline' => 'Look sharp, feel sharper',
            'sub' => 'Precision cuts, color artistry and bridal styling — book in under a minute.',
            'services' => [['Haircuts & Styling', 'Consultation-first cuts shaped to you.'], ['Color & Balayage', 'Dimensional color with healthy-shine finish.'], ['Bridal & Events', 'Trial + day-of styling for unforgettable days.']],
            'cta' => 'Book Your Chair', 'tag' => 'Top-Rated Salon Professional',
            'hero' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?auto=format&fit=crop&w=1000&q=80'
        ],
        'tutor' => [
            'label' => 'Tutor', 'emoji' => '📚',
            'headline' => 'Grades up, stress down',
            'sub' => 'Personalized 1-on-1 tutoring in maths, science and languages — online or at home.',
            'services' => [['1-on-1 Tutoring', 'Lessons built around how your child learns best.'], ['Exam Prep', 'Structured revision plans that raise scores.'], ['Homework Help', 'Daily support that builds independent study habits.']],
            'cta' => 'Book a Free Trial Lesson', 'tag' => 'Experienced Private Tutor',
            'hero' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=1000&q=80'
        ],
        'consultant' => [
            'label' => 'Consultant', 'emoji' => '💼',
            'headline' => 'Strategy that moves your numbers',
            'sub' => 'Advisory for growth, operations and digital — senior expertise, no fluff.',
            'services' => [['Business Strategy', 'Clarity on where to play and how to win.'], ['Operations Audit', 'Find the leaks, fix the bottlenecks.'], ['Growth Advisory', 'Quarterly guidance that compounds.']],
            'cta' => 'Schedule a Strategy Call', 'tag' => 'Independent Strategy Consultant',
            'hero' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1200&q=80',
            'about' => 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?auto=format&fit=crop&w=1000&q=80'
        ]
    ];
    if (preg_match('/coach/i', $t)) return $map['coach'];
    if (preg_match('/therap| counsel|psycholog/i', $t)) return $map['therapist'];
    if (preg_match('/plumb|electric|handyman|carpent|trade|technician|repair/i', $t)) return $map['trade'];
    if (preg_match('/clean|housekeep|maid/i', $t)) return $map['cleaning'];
    if (preg_match('/salon|beauty|barber|spa|styl/i', $t)) return $map['salon'];
    if (preg_match('/tutor|coach|train|mentor|teach|music teacher/i', $t) && !preg_match('/fitness|gym/i', $t)) return $map['coach'];
    if (preg_match('/tutor|tuition|teacher/i', $t)) return $map['tutor'];
    if (preg_match('/consult|advisor|agency|freelance|creator|portfolio/i', $t)) return $map['consultant'];
    return $map['consultant'];
}

function buildSoloDesign($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $reviews = [], $style = 'light') {
    $prof = getSoloProfession($bizType);
    $primary = $cp['primary'] ?? '#059669';
    $grad    = $cp['gradient'] ?? 'linear-gradient(135deg,#059669,#10b981)';
    $light   = $cp['light'] ?? '#d1fae5';
    $digits  = preg_replace('/[^0-9]/', '', (string)($bizPhone ?? ''));
    $callHref = (strlen($digits) >= 7) ? ('tel:+' . $digits) : '#contact';
    $esc = function ($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); };
    $fb1 = 'https://picsum.photos/seed/solohero/1200/800';
    $fb2 = 'https://picsum.photos/seed/soloabout/1000/800';
    $heroImg  = $esc($prof['hero']);
    $aboutImg = $esc($prof['about']);
    // ★ Style themes: light (clean) / bold (neo-brutal) / dark (luxury)
    $isDark = ($style === 'dark');
    $isBold = ($style === 'bold');
    $cardBg = $isDark ? 'rgba(255,255,255,0.04)' : '#fff';
    $cardBd = $isDark ? '1px solid rgba(255,255,255,0.1)' : ($isBold ? '2.5px solid #0f172a' : '1px solid #e2e8f0');
    $cardSh = $isDark ? 'none' : ($isBold ? '5px 5px 0px #0f172a' : '0 6px 22px rgba(0,0,0,0.04)');
    $hCol  = $isDark ? '#ffffff' : '#0f172a';
    $tCol  = $isDark ? '#94a3b8' : '#64748b';
    $bandBg = $isDark ? '#0f172a' : '#f8fafc';
    $bandBd = $isDark ? '#1e293b' : '#e2e8f0';
    $testiStyle = $isDark ? 'dark' : ($isBold ? 'bold' : 'light');
    $themeCss = '';
    if ($isDark) {
        $themeCss = 'body{background:#0a0f1c!important;color:#e2e8f0}.nav{background:rgba(10,15,28,.92)!important;border-color:#1e293b}.brand{color:#fff!important}.nav-links a{color:#cbd5e1!important}.hero h1{color:#fff!important}.hero p{color:#94a3b8!important}.trust{background:#0f172a!important;border-color:#1e293b;color:#94a3b8!important}.sec-title{color:#fff!important}#mobile-drawer{background:#0f172a!important}.hamburger{color:#fff!important}';
    } elseif ($isBold) {
        $themeCss = '.sec-title,.hero h1{letter-spacing:-.03em}';
    }

    $svcList = array_filter(array_map('trim', explode(',', (string)$bizServices)));
    if (empty($svcList)) {
        foreach ($prof['services'] as $s) $svcList[] = $s[0];
    }
    $svcCards = '';
    $i = 0;
    foreach (array_slice($svcList, 0, 6) as $s) {
        $desc = $prof['services'][$i % count($prof['services'])][1] ?? 'Professional, reliable service with transparent pricing.';
        $i++;
        $svcCards .= "<div style=\"background:{$cardBg};border:{$cardBd};border-radius:18px;padding:2rem;box-shadow:{$cardSh};\">"
            . "<div style=\"width:46px;height:46px;border-radius:13px;background:{$light};color:{$primary};display:flex;align-items:center;justify-content:center;font-size:1.35rem;font-weight:800;margin-bottom:1rem;\">{$prof['emoji']}</div>"
            . "<h3 style=\"font-size:1.15rem;color:{$hCol};font-weight:800;margin-bottom:0.5rem;\">" . $esc($s) . "</h3>"
            . "<p style=\"font-size:0.92rem;color:{$tCol};line-height:1.65;\">" . $esc($desc) . "</p></div>";
    }

    if (!empty($reviews)) {
        $revCards = '';
        foreach (array_slice($reviews, 0, 6) as $r) {
            $w0 = preg_split('/\s+/', trim((string)$r['name']));
            $ch0 = preg_split('//u', (string)($w0[0] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $ch1 = preg_split('//u', (string)($w0[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $initials = strtoupper((($ch0[0] ?? '') . ($ch1[0] ?? '')));
            if ($initials === '') $initials = '★';
            $revCards .= "<div style=\"background:{$cardBg};border:{$cardBd};border-radius:18px;padding:1.75rem;box-shadow:{$cardSh};\">"
                . "<div style=\"color:#f59e0b;letter-spacing:2px;margin-bottom:0.8rem;\">★★★★★</div>"
                . "<p style=\"font-size:0.95rem;color:{$tCol};line-height:1.7;font-style:italic;margin-bottom:1.25rem;\">" . $esc('"' . $r['text'] . '"') . "</p>"
                . "<div style=\"display:flex;align-items:center;gap:0.8rem;\">"
                . "<div style=\"width:44px;height:44px;border-radius:50%;background:{$grad};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;\">" . $esc($initials) . "</div>"
                . "<div><strong style=\"display:block;font-size:0.92rem;color:{$hCol};\">" . $esc($r['name']) . "</strong>"
                . "<span style=\"font-size:0.78rem;color:#94a3b8;\">" . $esc($r['role'] ?? 'Verified Client') . "</span></div></div></div>";
        }
        $reviewsHtml = <<<REV
<section id="reviews" style="padding:5rem 1.5rem;background:{$bandBg};border-top:1px solid {$bandBd};border-bottom:1px solid {$bandBd};">
  <div style="max-width:1100px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:2.75rem;">
      <div style="font-size:0.78rem;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;color:{$primary};margin-bottom:0.5rem;">⭐ Client Reviews</div>
      <h2 style="font-size:2.1rem;font-weight:800;color:{$hCol};">Loved by Clients</h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;">{$revCards}</div>
  </div>
</section>
REV;
    } else {
        $reviewsHtml = getTestimonialsHtml($bizName, $bizType, $testiStyle, $cp);
    }

    $mapEmbed = getMapEmbed($bizAddress);
    $sharedJs = getSharedJS($bizName);
    $year = date('Y');
    $tagline = $bizTagline ?: $prof['headline'];

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$esc($bizName)} — {$esc($prof['label'])}</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Plus Jakarta Sans',sans-serif;color:#1e293b;background:#fff;line-height:1.6}
:root{--primary:{$primary};--primary-light:{$light}}
.nav{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border-bottom:1px solid #e2e8f0}
.nav-in{max-width:1150px;margin:0 auto;padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between}
.brand{font-weight:800;font-size:1.25rem;color:#0f172a}
.nav-links{display:flex;gap:1.5rem;align-items:center}
.nav-links a{font-size:.9rem;font-weight:600;color:#475569}
.btn{display:inline-block;padding:.85rem 2rem;border-radius:999px;background:{$grad};color:#fff;font-weight:700;text-decoration:none;box-shadow:0 10px 25px -5px {$primary}}
.hero{max-width:1150px;margin:0 auto;padding:5rem 1.5rem 4rem;display:grid;grid-template-columns:1.05fr .95fr;gap:3rem;align-items:center}
.hero-badge{display:inline-block;padding:.35rem 1rem;background:{$light};color:{$primary};border-radius:999px;font-weight:700;font-size:.8rem;margin-bottom:1.25rem}
.hero h1{font-size:clamp(2.2rem,4.6vw,3.4rem);font-weight:800;color:#0f172a;line-height:1.15;margin-bottom:1.1rem;letter-spacing:-.02em}
.hero p{font-size:1.08rem;color:#64748b;margin-bottom:2rem}
.hero-img{border-radius:24px;overflow:hidden;box-shadow:0 20px 45px rgba(0,0,0,.1)}
.hero-img img{width:100%;height:380px;object-fit:cover;display:block}
.trust{display:flex;gap:2rem;flex-wrap:wrap;justify-content:center;padding:1.25rem;background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;font-size:.85rem;font-weight:700;color:#475569}
.section{max-width:1150px;margin:0 auto;padding:5rem 1.5rem}
.sec-head{text-align:center;margin-bottom:2.75rem}
.sec-tag{font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:{$primary};margin-bottom:.5rem}
.sec-title{font-size:2.1rem;font-weight:800;color:#0f172a}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem}
.about{display:grid;grid-template-columns:1fr 1fr;gap:3.5rem;align-items:center;background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0}
.about img{border-radius:24px;width:100%;height:420px;object-fit:cover}
.contact{background:#0f172a;color:#e2e8f0}
.contact-grid{max-width:1150px;margin:0 auto;padding:5rem 1.5rem;display:grid;grid-template-columns:1fr 1.2fr;gap:3rem}
.c-form input,.c-form textarea{width:100%;padding:.85rem 1rem;border:1.5px solid #334155;border-radius:12px;margin-bottom:1rem;font-family:inherit;background:#0b1220;color:#fff}
.fab-call{position:fixed;bottom:5.5rem;right:1.5rem;z-index:999;width:56px;height:56px;border-radius:50%;background:#25D366;color:#fff;font-size:1.5rem;display:flex;align-items:center;justify-content:center;text-decoration:none;box-shadow:0 12px 30px rgba(0,0,0,.3)}
#back-to-top{display:none;position:fixed;bottom:2rem;right:2rem;z-index:90;width:44px;height:44px;border-radius:50%;background:#0f172a;color:#fff;border:none;cursor:pointer;align-items:center;justify-content:center;font-size:1.2rem}
#mobile-drawer{display:none;flex-direction:column;background:#fff;padding:1.5rem;gap:1rem;font-weight:600;border-bottom:1px solid #e2e8f0}
#mobile-drawer.open{display:flex}
.hamburger{display:none;background:none;border:none;font-size:1.5rem;cursor:pointer}
footer{background:#0f172a;color:#94a3b8;padding:2.5rem 1.5rem;text-align:center;border-top:1px solid #1e293b}
{$themeCss}
@media(max-width:900px){.hero,.about,.contact-grid{grid-template-columns:1fr}.nav-links{display:none}.hamburger{display:block}}
</style>
</head>
<body>
<nav class="nav"><div class="nav-in">
  <div class="brand">{$esc($bizName)}</div>
  <div class="nav-links"><a href="#services">Services</a><a href="#reviews">Reviews</a><a href="#about">About</a><a href="#contact">Contact</a><a href="{$callHref}" class="btn" style="padding:.55rem 1.3rem;font-size:.85rem;">{$esc($prof['cta'])}</a></div>
  <button class="hamburger" onclick="toggleMobileMenu()">☰</button>
</div><div id="mobile-drawer"><a href="#services">Services</a><a href="#reviews">Reviews</a><a href="#about">About</a><a href="#contact">Contact</a></div></nav>

<header class="hero">
  <div>
    <span class="hero-badge">{$prof['emoji']} {$esc($prof['tag'])}</span>
    <h1>{$esc($tagline)}</h1>
    <p>{$esc($prof['sub'])}</p>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;">
      <a href="{$callHref}" class="btn">{$esc($prof['cta'])} →</a>
      <a href="#services" class="btn" style="background:#fff;color:#0f172a;border:1.5px solid #cbd5e1;box-shadow:none;">Explore Services</a>
    </div>
  </div>
  <div class="hero-img"><img src="{$heroImg}" alt="{$esc($bizName)}" onerror="this.onerror=null;this.src='{$fb1}'"></div>
</header>

<div class="trust"><span>★ 5-Star Rated</span><span>✓ Verified Professional</span><span>⚡ Fast Response</span><span>🛡️ Satisfaction Guaranteed</span></div>

<section class="section" id="services">
  <div class="sec-head"><div class="sec-tag">What I Do</div><h2 class="sec-title">Services</h2></div>
  <div class="grid">{$svcCards}</div>
</section>

<section id="about" style="background:{$bandBg};border-top:1px solid {$bandBd};border-bottom:1px solid {$bandBd};">
  <div class="section about" style="border:none;background:none;">
    <div><img src="{$aboutImg}" alt="About {$esc($bizName)}" onerror="this.onerror=null;this.src='{$fb2}'"></div>
    <div>
      <div class="sec-tag">About</div>
      <h2 class="sec-title" style="margin-bottom:1rem;">Hi, I'm {$esc($bizName)}</h2>
      <p style="color:{$tCol};font-size:1.05rem;line-height:1.75;margin-bottom:1.5rem;">{$esc($bizAudience)} trust me for {$esc($prof['label'])} work done with care, honesty and skill. Every client gets personal attention — no hand-offs, no surprises.</p>
      <a href="#contact" class="btn">Work With Me →</a>
    </div>
  </div>
</section>

{$reviewsHtml}

<section class="contact" id="contact">
  <div class="contact-grid">
    <div>
      <div class="sec-tag">Get In Touch</div>
      <h2 style="font-size:2.1rem;font-weight:800;color:#fff;margin-bottom:1rem;">Let's talk</h2>
      <p style="color:#94a3b8;margin-bottom:1.5rem;">Call, message or send the form — I reply within 24 hours.</p>
      <div style="margin-bottom:.8rem;">📞 <strong>{$esc($bizPhone)}</strong></div>
      <div style="margin-bottom:.8rem;">✉️ {$esc($bizEmail)}</div>
      <div>📍 {$esc($bizAddress)}</div>
      <div style="margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="{$callHref}" class="btn">📞 Call Now</a>
      </div>
    </div>
    <form class="c-form" id="contact-form" method="POST" onsubmit="handleContactSubmit(event)">
      <input name="name" placeholder="Your name" required>
      <input type="email" name="email" placeholder="Email address" required>
      <input name="phone" placeholder="Phone (optional)">
      <textarea name="message" placeholder="How can I help?" required></textarea>
      <button type="submit" class="btn" style="border:none;cursor:pointer;width:100%;">Send Message →</button>
    </form>
  </div>
  {$mapEmbed}
</section>

<footer>© {$year} {$esc($bizName)} · {$esc($prof['label'])} · All rights reserved.</footer>
<a class="fab-call" href="{$callHref}">📞</a>
<button id="back-to-top">↑</button>
{$sharedJs}
</body>
</html>
HTML;
}

// ═══════════════════════════════════════════════════════════════
//  Premium Helpers: Showcase, Testimonials, FAQ Accordion
// ═══════════════════════════════════════════════════════════════

function getShowcaseHtml($bizName, $bizType, $style, $cp, $assets) {
    $img1 = $assets['showcase1'] ?? 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80';
    $img2 = $assets['showcase2'] ?? 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=800&q=80';
    $tag = $assets['tag'] ?? 'Featured Work';

    if ($style === 'light') {
        return <<<SHOW
<section class="section" id="showcase">
  <div class="section-header">
    <div class="section-tag">Recent Work</div>
    <h2 class="section-title">Curated Excellence & Impact</h2>
    <p class="section-sub">A glimpse into signature projects delivered with measurable ROI.</p>
  </div>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:20px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.04); transition:transform 0.25s;">
      <div style="height:240px; overflow:hidden;">
        <img src="{$img1}" alt="{$bizName} flagship project" style="width:100%; height:100%; object-fit:cover;">
      </div>
      <div style="padding:1.75rem;">
        <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--primary); letter-spacing:0.08em; background:var(--primary-light); padding:0.25rem 0.65rem; border-radius:999px;">{$tag}</span>
        <h3 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0.8rem 0 0.4rem;">Enterprise Transformation</h3>
        <p style="font-size:0.9rem; color:#64748b; line-height:1.6;">Re-engineered acquisition pipeline resulting in +210% inbound pipeline growth and user retention.</p>
      </div>
    </div>
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:20px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.04); transition:transform 0.25s;">
      <div style="height:240px; overflow:hidden;">
        <img src="{$img2}" alt="{$bizName} client project" style="width:100%; height:100%; object-fit:cover;">
      </div>
      <div style="padding:1.75rem;">
        <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--primary); letter-spacing:0.08em; background:var(--primary-light); padding:0.25rem 0.65rem; border-radius:999px;">High Velocity</span>
        <h3 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0.8rem 0 0.4rem;">Modern Brand Deployment</h3>
        <p style="font-size:0.9rem; color:#64748b; line-height:1.6;">Delivered bespoke web application with sub-second page loads, intuitive UI, and seamless UX.</p>
      </div>
    </div>
  </div>
</section>
SHOW;
    } elseif ($style === 'bold') {
        return <<<SHOW
<section class="section" id="showcase">
  <h2 class="section-title">Proven Track Record</h2>
  <p class="section-subtitle">Real outcomes engineered for ambitious organizations that refuse to blend in.</p>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
    <div style="background:#fff; border:2.5px solid #0f172a; border-radius:20px; overflow:hidden; box-shadow:6px 6px 0px #0f172a;">
      <img src="{$img1}" alt="{$bizName} case study 1" style="width:100%; height:240px; object-fit:cover; border-bottom:2.5px solid #0f172a;">
      <div style="padding:1.75rem;">
        <div style="display:inline-block; background:var(--primary-light); color:var(--primary); font-weight:800; font-size:0.78rem; padding:0.2rem 0.7rem; border-radius:999px; border:1px solid var(--primary); margin-bottom:0.6rem;">{$tag}</div>
        <h3 style="font-size:1.4rem; font-weight:800; font-family:'Space Grotesk'; margin-bottom:0.5rem;">Next-Gen Market Expansion</h3>
        <p style="font-size:0.95rem; color:#475569; line-height:1.6;">Scaled digital infrastructure to handle 50,000+ daily interactions with zero latency.</p>
      </div>
    </div>
    <div style="background:#fff; border:2.5px solid #0f172a; border-radius:20px; overflow:hidden; box-shadow:6px 6px 0px #0f172a;">
      <img src="{$img2}" alt="{$bizName} case study 2" style="width:100%; height:240px; object-fit:cover; border-bottom:2.5px solid #0f172a;">
      <div style="padding:1.75rem;">
        <div style="display:inline-block; background:var(--primary-light); color:var(--primary); font-weight:800; font-size:0.78rem; padding:0.2rem 0.7rem; border-radius:999px; border:1px solid var(--primary); margin-bottom:0.6rem;">Rapid Scalability</div>
        <h3 style="font-size:1.4rem; font-weight:800; font-family:'Space Grotesk'; margin-bottom:0.5rem;">Conversion Architecture</h3>
        <p style="font-size:0.95rem; color:#475569; line-height:1.6;">Re-engineered customer onboarding funnel delivering 3.4x higher activation.</p>
      </div>
    </div>
  </div>
</section>
SHOW;
    } else {
        return <<<SHOW
<section class="section" id="showcase">
  <div class="section-tag">Portfolio of Distinction</div>
  <h2 class="section-head">Bespoke Case Studies</h2>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2.5rem;">
    <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08); border-radius:16px; overflow:hidden; backdrop-filter:blur(14px);">
      <img src="{$img1}" alt="{$bizName} showcase" style="width:100%; height:250px; object-fit:cover; filter:brightness(0.85);">
      <div style="padding:2rem;">
        <span style="font-size:0.72rem; letter-spacing:0.18em; text-transform:uppercase; color:var(--primary); font-weight:700;">{$tag}</span>
        <h3 style="font-size:1.3rem; color:#fff; font-family:'Cinzel',serif; margin:0.8rem 0 0.5rem;">The Sovereign Platform</h3>
        <p style="font-size:0.9rem; color:#94a3b8; line-height:1.7;">A flagship digital identity system architected for tier-one global clientele.</p>
      </div>
    </div>
    <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.08); border-radius:16px; overflow:hidden; backdrop-filter:blur(14px);">
      <img src="{$img2}" alt="{$bizName} showcase" style="width:100%; height:250px; object-fit:cover; filter:brightness(0.85);">
      <div style="padding:2rem;">
        <span style="font-size:0.72rem; letter-spacing:0.18em; text-transform:uppercase; color:var(--primary); font-weight:700;">Private Advisory</span>
        <h3 style="font-size:1.3rem; color:#fff; font-family:'Cinzel',serif; margin:0.8rem 0 0.5rem;">Autonomous Digital Assets</h3>
        <p style="font-size:0.9rem; color:#94a3b8; line-height:1.7;">End-to-end bespoke implementation with bank-grade security and uncompromising aesthetics.</p>
      </div>
    </div>
  </div>
</section>
SHOW;
    }
}

function testimonialAvatarHtml($r, $roundPx, $border, $size = 46) {
    $nm = htmlspecialchars($r['author'] ?? '', ENT_QUOTES, 'UTF-8');
    if (!empty($r['avatar'])) {
        $av = htmlspecialchars($r['avatar'], ENT_QUOTES, 'UTF-8');
        return "<img src=\"{$av}\" alt=\"{$nm}\" loading=\"lazy\" style=\"width:{$size}px;height:{$size}px;border-radius:{$roundPx};object-fit:cover;{$border}\">";
    }
    $ini = htmlspecialchars($r['initials'] ?? '★', ENT_QUOTES, 'UTF-8');
    return "<div style=\"width:{$size}px;height:{$size}px;border-radius:{$roundPx};background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.95rem;flex-shrink:0;{$border}\">{$ini}</div>";
}

function getTestimonialsHtml($bizName, $bizType, $style, $cp, $customReviews = null) {
    $reviews = [
        [
            'quote' => "Partnering with {$bizName} was a game-changer. Their strategic mastery in {$bizType} and obsessive attention to detail doubled our conversion rates within weeks.",
            'author' => 'Elena Rostova',
            'role' => 'VP of Operations, NovaCorp',
            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=120&q=80',
            'rating' => '★★★★★'
        ],
        [
            'quote' => "The velocity, elegance, and precision delivered by {$bizName} surpassed every benchmark. Our clients constantly compliment our new presence.",
            'author' => 'Marcus Sterling',
            'role' => 'Managing Director, Sterling Group',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=120&q=80',
            'rating' => '★★★★★'
        ],
        [
            'quote' => "Simply the highest standard of execution in {$bizType}. Responsive, visionary, and thoroughly dependable from kickoff to launch.",
            'author' => 'Sophia Vance',
            'role' => 'Chief Strategy Officer, Aurelia',
            'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=120&q=80',
            'rating' => '★★★★★'
        ]
    ];

    // ★ Pasted customer reviews (Solo-style import) override defaults
    if (is_array($customReviews) && !empty($customReviews)) {
        $mapped = [];
        foreach (array_slice($customReviews, 0, 6) as $cr) {
            if (empty($cr['name']) || empty($cr['text'])) continue;
            $w0 = preg_split('/\s+/', trim((string)$cr['name']));
            $c0 = preg_split('//u', (string)($w0[0] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $c1 = preg_split('//u', (string)($w0[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $ini = strtoupper((($c0[0] ?? '') . ($c1[0] ?? '')));
            if ($ini === '') $ini = '★';
            $mapped[] = [
                'quote' => $cr['text'],
                'author' => $cr['name'],
                'role' => ($cr['role'] ?? 'Verified Client'),
                'rating' => '★★★★★',
                'avatar' => '',
                'initials' => $ini
            ];
        }
        if (!empty($mapped)) $reviews = $mapped;
    }

    if ($style === 'light') {
        $cards = '';
        foreach ($reviews as $r) {
            $av = testimonialAvatarHtml($r, '50%', 'border:2px solid var(--primary);', 46);
            $cards .= <<<CARD
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; box-shadow:0 6px 24px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
              <div>
                <div style="color:#f59e0b; font-size:1.1rem; margin-bottom:1rem; letter-spacing:2px;">{$r['rating']}</div>
                <p style="font-size:0.95rem; color:#475569; line-height:1.7; font-style:italic; margin-bottom:1.5rem;">"{$r['quote']}"</p>
              </div>
              <div style="display:flex; align-items:center; gap:0.9rem; border-top:1px solid #f1f5f9; padding-top:1rem;">
                {$av}
                <div>
                  <strong style="display:block; font-size:0.92rem; color:#0f172a;">{$r['author']}</strong>
                  <span style="font-size:0.8rem; color:#94a3b8;">{$r['role']}</span>
                </div>
              </div>
            </div>
CARD;
        }
        return <<<SEC
<section class="section" id="testimonials" style="background:#f8fafc; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; padding:5.5rem 1.5rem;">
  <div style="max-width:1200px; margin:0 auto;">
    <div style="text-align:center; max-width:650px; margin:0 auto 3.5rem;">
      <div class="section-tag">Client Endorsements</div>
      <h2 class="section-title">Trusted by Industry Leaders</h2>
      <p class="section-sub">Read what partners say about our measurable results and commitment to craft.</p>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:2rem;">
      {$cards}
    </div>
  </div>
</section>
SEC;
    } elseif ($style === 'bold') {
        $cards = '';
        foreach ($reviews as $r) {
            $av = testimonialAvatarHtml($r, '12px', 'border:2px solid #0f172a;', 48);
            $cards .= <<<CARD
            <div style="background:#ffffff; border:2.5px solid #0f172a; border-radius:18px; padding:2.2rem; box-shadow:6px 6px 0px #0f172a; display:flex; flex-direction:column; justify-content:space-between;">
              <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
                  <span style="color:#f59e0b; font-size:1.2rem;">{$r['rating']}</span>
                  <span style="background:var(--primary-light); color:var(--primary); font-size:0.75rem; font-weight:800; padding:0.25rem 0.6rem; border-radius:999px; border:1px solid var(--primary);">VERIFIED</span>
                </div>
                <p style="font-size:1rem; color:#0f172a; font-weight:500; line-height:1.6; margin-bottom:1.5rem;">"{$r['quote']}"</p>
              </div>
              <div style="display:flex; align-items:center; gap:0.9rem; border-top:2px solid #e2e8f0; padding-top:1rem;">
                {$av}
                <div>
                  <strong style="display:block; font-size:1rem; font-family:'Space Grotesk';">{$r['author']}</strong>
                  <span style="font-size:0.82rem; color:#64748b;">{$r['role']}</span>
                </div>
              </div>
            </div>
CARD;
        }
        return <<<SEC
<section class="section" id="testimonials">
  <h2 class="section-title">Proof That Speaks Volumes</h2>
  <p class="section-subtitle">Real experiences and verified feedback from partners achieving record growth.</p>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:2rem;">
    {$cards}
  </div>
</section>
SEC;
    } else {
        $cards = '';
        foreach ($reviews as $r) {
            $av = testimonialAvatarHtml($r, '50%', 'border:1px solid rgba(255,255,255,0.25);', 46);
            $cards .= <<<CARD
            <div style="background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.09); border-radius:14px; padding:2.2rem; backdrop-filter:blur(14px); display:flex; flex-direction:column; justify-content:space-between;">
              <div>
                <div style="color:#f59e0b; font-size:1rem; margin-bottom:1.2rem; letter-spacing:3px;">{$r['rating']}</div>
                <p style="font-size:0.95rem; color:#cbd5e1; font-weight:300; line-height:1.8; margin-bottom:1.8rem; font-style:italic;">"{$r['quote']}"</p>
              </div>
              <div style="display:flex; align-items:center; gap:1rem; border-top:1px solid rgba(255,255,255,0.08); padding-top:1.2rem;">
                {$av}
                <div>
                  <strong style="display:block; font-size:0.92rem; color:#fff; font-family:'Cinzel',serif; letter-spacing:0.04em;">{$r['author']}</strong>
                  <span style="font-size:0.8rem; color:#94a3b8;">{$r['role']}</span>
                </div>
              </div>
            </div>
CARD;
        }
        return <<<SEC
<section class="section" id="testimonials">
  <div class="section-tag">Executive Testimonials</div>
  <h2 class="section-head">Endorsements of Distinction</h2>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:2rem;">
    {$cards}
  </div>
</section>
SEC;
    }
}

function getFaqHtml($bizName, $bizType, $style, $cp) {
    $faqs = [
        [
            'q' => "What sets {$bizName}'s approach to {$bizType} apart?",
            'a' => "We combine meticulous user-centric design with conversion architecture. Every decision is driven by real market data, ensuring your business stands out while converting visitors into dedicated clients."
        ],
        [
            'q' => "What is the typical timeframe to deliver a full project?",
            'a' => "Our focused sprint model allows us to deploy fully functional, high-performance solutions within 7 to 14 days, maintaining exceptional polish and comprehensive testing throughout."
        ],
        [
            'q' => "Do you provide ongoing support and proactive maintenance?",
            'a' => "Yes. We offer continuous care tiers including automated backups, speed tuning, security patches, and priority technical guidance to guarantee flawless operation 24/7."
        ],
        [
            'q' => "How can we initiate our project with {$bizName}?",
            'a' => "Simply submit the inquiry form below or give us a direct call. Our leadership team will review your objectives and host an onboarding session within 24 hours."
        ]
    ];

    if ($style === 'light') {
        $items = '';
        foreach ($faqs as $idx => $f) {
            $items .= <<<FAQ
            <div style="border:1px solid #e2e8f0; border-radius:14px; margin-bottom:1rem; overflow:hidden; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.02);">
              <button onclick="toggleFaq(this)" type="button" style="width:100%; text-align:left; background:none; border:none; padding:1.25rem 1.5rem; font-size:1.02rem; font-weight:700; color:#0f172a; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-family:inherit;">
                <span>{$f['q']}</span>
                <span class="faq-icon" style="font-size:1.4rem; color:var(--primary); font-weight:400;">+</span>
              </button>
              <div style="display:none; padding:0 1.5rem 1.25rem; color:#64748b; font-size:0.92rem; line-height:1.7; border-top:1px solid #f1f5f9; padding-top:0.75rem;">
                {$f['a']}
              </div>
            </div>
FAQ;
        }
        return <<<SEC
<section class="section" id="faq" style="max-width:880px; margin:0 auto; padding:5.5rem 1.5rem;">
  <div style="text-align:center; margin-bottom:3.5rem;">
    <div class="section-tag">Got Questions?</div>
    <h2 class="section-title">Frequently Asked Questions</h2>
    <p class="section-sub">Everything you need to know about our services, process, and deliverables.</p>
  </div>
  <div>
    {$items}
  </div>
</section>
SEC;
    } elseif ($style === 'bold') {
        $items = '';
        foreach ($faqs as $idx => $f) {
            $items .= <<<FAQ
            <div style="border:2.5px solid #0f172a; border-radius:14px; margin-bottom:1.2rem; overflow:hidden; background:#fff; box-shadow:4px 4px 0px #0f172a;">
              <button onclick="toggleFaq(this)" type="button" style="width:100%; text-align:left; background:none; border:none; padding:1.25rem 1.5rem; font-size:1.05rem; font-weight:800; color:#0f172a; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-family:'Space Grotesk';">
                <span>{$f['q']}</span>
                <span class="faq-icon" style="font-size:1.4rem; font-weight:800; color:#0f172a;">+</span>
              </button>
              <div style="display:none; padding:0 1.5rem 1.25rem; color:#475569; font-size:0.95rem; line-height:1.7; border-top:2px solid #0f172a; padding-top:1rem; background:#f8fafc;">
                {$f['a']}
              </div>
            </div>
FAQ;
        }
        return <<<SEC
<section class="section" id="faq" style="max-width:900px; margin:0 auto;">
  <h2 class="section-title">Clear Answers, No Confusion</h2>
  <p class="section-subtitle">Common questions answered directly by the {$bizName} team.</p>
  <div>
    {$items}
  </div>
</section>
SEC;
    } else {
        $items = '';
        foreach ($faqs as $idx => $f) {
            $items .= <<<FAQ
            <div style="border:1px solid rgba(255,255,255,0.08); border-radius:10px; margin-bottom:1rem; overflow:hidden; background:rgba(255,255,255,0.02); backdrop-filter:blur(10px);">
              <button onclick="toggleFaq(this)" type="button" style="width:100%; text-align:left; background:none; border:none; padding:1.3rem 1.6rem; font-size:1rem; font-weight:600; color:#fff; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-family:'Cinzel',serif; letter-spacing:0.03em;">
                <span>{$f['q']}</span>
                <span class="faq-icon" style="font-size:1.4rem; color:var(--primary); font-family:sans-serif;">+</span>
              </button>
              <div style="display:none; padding:0 1.6rem 1.4rem; color:#94a3b8; font-size:0.92rem; line-height:1.8; border-top:1px solid rgba(255,255,255,0.06); padding-top:1rem;">
                {$f['a']}
              </div>
            </div>
FAQ;
        }
        return <<<SEC
<section class="section" id="faq" style="max-width:880px; margin:0 auto;">
  <div class="section-tag">Direct Inquiries</div>
  <h2 class="section-head">Common Inquiries</h2>
  <div>
    {$items}
  </div>
</section>
SEC;
    }
}

// ═══════════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════════
//  Design 1: Modern Minimal & Crisp (Light Theme)
//  Supports Sub-Variants: A (Conversion), B (Bento Grid), C (Editorial Authority)
// ═══════════════════════════════════════════════════════════════
function buildDesign1($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets = null, $mapEmbed = null, $subVariant = 'A', $customReviews = null) {
    if (!$assets) $assets = getNicheAssets($bizType, $bizName);
    if ($mapEmbed === null) $mapEmbed = getMapEmbed($bizAddress);
    $servicesList = array_filter(array_map('trim', explode(',', $bizServices)));
    if (empty($servicesList)) $servicesList = ['Strategic Planning', 'Digital Excellence', 'Creative Design', 'Ongoing Support'];

    $cardsHtml = '';
    $icons = ['✦', '⚡', '❖', '◈', '★', '◉'];
    foreach ($servicesList as $idx => $s) {
        $ic = $icons[$idx % count($icons)];
        $isFeatured = ($subVariant === 'B' && $idx === 0) ? 'style="grid-column: span 2; background: linear-gradient(135deg, var(--primary-light), #ffffff); border-color: var(--primary);"' : '';
        $cardsHtml .= "<article class='service-card' {$isFeatured}><div class='service-icon'>{$ic}</div><h3>" . htmlspecialchars($s) . "</h3><p>Bespoke execution and strategic delivery crafted specifically for " . htmlspecialchars($bizAudience) . ".</p><a href='#contact' class='card-link'>Inquire Now &rarr;</a></article>";
    }

    $showcaseHtml = getShowcaseHtml($bizName, $bizType, 'light', $cp, $assets);
    $testimonialsHtml = getTestimonialsHtml($bizName, $bizType, 'light', $cp, $customReviews);
    $faqHtml = getFaqHtml($bizName, $bizType, 'light', $cp);
    $sharedJs = getSharedJS($bizName);
    $year = date('Y');
    $aboutImg = $assets['about'] ?? 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1000&q=80';
    $heroImg = $assets['hero'] ?? 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=1200&q=80';

    if ($subVariant === 'B') {
        $pageTitle = "{$bizName} — Bento Architecture";
        $heroHtml = <<<HERO
<header class="hero" style="padding:5rem 1.5rem 4rem;">
  <div style="display:grid; grid-template-columns:1.2fr 0.8fr; gap:3.5rem; align-items:center; text-align:left; max-width:1200px; margin:0 auto;">
    <div>
      <div class="hero-badge">✦ Bento Architecture · {$bizAudience}</div>
      <h1 style="text-align:left; margin:0 0 1.25rem; font-size:clamp(2.4rem, 5vw, 3.8rem);">{$bizTagline}</h1>
      <p style="text-align:left; margin:0 0 2rem; color:#64748b; font-size:1.1rem; line-height:1.7;">Engineered for ambitious organizations requiring unmatched velocity, conversion clarity, and high-impact digital presence.</p>
      <div class="hero-actions" style="justify-content:flex-start; margin-bottom:0;">
        <a href="#contact" class="btn-primary">Start Your Build &rarr;</a>
        <a href="#services" class="btn-secondary">Explore Capabilities</a>
      </div>
    </div>
    <div style="position:relative; border-radius:24px; overflow:hidden; box-shadow:0 20px 45px rgba(0,0,0,0.1); border:1px solid #e2e8f0;">
      <img src="{$heroImg}" alt="{$bizName} flagship" style="width:100%; height:340px; object-fit:cover; display:block;">
      <div style="position:absolute; bottom:1.25rem; left:1.25rem; right:1.25rem; background:rgba(255,255,255,0.94); backdrop-filter:blur(14px); border-radius:14px; padding:1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; border:1px solid rgba(255,255,255,0.7); box-shadow:0 10px 25px rgba(0,0,0,0.06);">
        <div><strong style="color:#0f172a; font-size:0.95rem; display:block;">Verified Standard</strong><span style="font-size:0.8rem; color:#64748b;">99.8% Client Retention</span></div>
        <span style="font-size:0.75rem; font-weight:800; background:var(--primary-light); color:var(--primary); padding:0.25rem 0.65rem; border-radius:999px;">FEATURED</span>
      </div>
    </div>
  </div>
</header>
HERO;
    } elseif ($subVariant === 'C') {
        $pageTitle = "{$bizName} — Editorial Standard";
        $heroHtml = <<<HERO
<header class="hero" style="padding:6rem 1.5rem 4rem;">
  <div style="max-width:920px; margin:0 auto; text-align:center;">
    <div class="hero-badge">✦ Signature Strategic Practice</div>
    <h1 style="font-size:clamp(2.6rem, 5.5vw, 4.4rem); letter-spacing:-0.035em; margin-bottom:1.5rem;">{$bizTagline}</h1>
    <p style="font-size:1.2rem; max-width:680px; margin:0 auto 2.5rem; color:#475569; line-height:1.8;">Pioneering tailored digital systems for {$bizAudience} with an uncompromising dedication to craft, clarity, and return on investment.</p>
    <div class="hero-actions" style="margin-bottom:3rem;">
      <a href="#contact" class="btn-primary">Schedule Discovery Session &rarr;</a>
      <a href="#showcase" class="btn-secondary">View Signature Works</a>
    </div>
    <div style="border-top:1px solid #e2e8f0; padding-top:2rem; display:flex; justify-content:center; gap:2.5rem; flex-wrap:wrap; font-size:0.88rem; font-weight:700; color:#64748b;">
      <span>★ 150+ Milestone Launches</span>
      <span>★ SOC-2 / SSL Hardened</span>
      <span>★ 24/7 Dedicated Concierge</span>
    </div>
  </div>
</header>
HERO;
    } else {
        $pageTitle = "{$bizName} — {$bizTagline}";
        $heroHtml = <<<HERO
<header class="hero">
  <div class="hero-badge">✦ Tailored for {$bizAudience}</div>
  <h1>{$bizTagline}</h1>
  <p>Partnering with visionary clients to design, build, and accelerate high-performing digital solutions that produce tangible results.</p>
  <div class="hero-actions">
    <a href="#contact" class="btn-primary">Start a Project &rarr;</a>
    <a href="#services" class="btn-secondary">Explore Services</a>
  </div>
  <aside class="metrics-wrap">
    <div class="metric-item"><h4>99.8%</h4><p>Client Satisfaction</p></div>
    <div class="metric-item"><h4>150+</h4><p>Successful Deliveries</p></div>
    <div class="metric-item"><h4>24/7</h4><p>Dedicated Support</p></div>
  </aside>
</header>
HERO;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$pageTitle}</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Plus Jakarta Sans', -apple-system, sans-serif; color: #1e293b; background: #fafbfe; line-height: 1.6; }
:root { --primary: {$cp['primary']}; --primary-gradient: {$cp['gradient']}; --primary-light: {$cp['light']}; --dark: #0f172a; }
a { text-decoration: none; color: inherit; }
.nav-wrap { position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(12px); border-bottom: 1px solid #e2e8f0; }
.nav-container { max-width: 1200px; margin: 0 auto; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.brand-logo { font-size: 1.3rem; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 0.5rem; }
.brand-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--primary); }
.nav-links { display: flex; align-items: center; gap: 1.75rem; }
.nav-links a { font-size: 0.9rem; font-weight: 600; color: #475569; transition: color 0.2s; }
.nav-links a:hover { color: var(--primary); }
.btn-nav { padding: 0.55rem 1.25rem; border-radius: 999px; background: var(--dark); color: #fff !important; font-size: 0.85rem; font-weight: 600; transition: transform 0.2s, background 0.2s; }
.btn-nav:hover { transform: translateY(-1px); background: var(--primary); }
.hamburger { display: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--dark); }
#mobile-drawer { display: none; flex-direction: column; background: #fff; padding: 1.5rem; border-bottom: 1px solid #e2e8f0; gap: 1rem; font-weight: 600; }
#mobile-drawer.open { display: flex; }
.hero { padding: 6rem 1.5rem 5rem; max-width: 1200px; margin: 0 auto; text-align: center; }
.hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 1rem; border-radius: 999px; background: var(--primary-light); color: var(--primary); font-size: 0.82rem; font-weight: 700; margin-bottom: 1.5rem; }
.hero h1 { font-size: clamp(2.4rem, 5vw, 4rem); font-weight: 800; color: var(--dark); line-height: 1.15; max-width: 900px; margin: 0 auto 1.5rem; letter-spacing: -0.03em; }
.hero p { font-size: 1.15rem; color: #64748b; max-width: 650px; margin: 0 auto 2.5rem; line-height: 1.7; }
.hero-actions { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 3.5rem; }
.btn-primary { padding: 0.85rem 2rem; border-radius: 999px; background: var(--primary-gradient); color: #fff; font-weight: 700; font-size: 0.95rem; box-shadow: 0 10px 25px -5px rgba(99,102,241,0.4); transition: transform 0.2s, box-shadow 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 30px -5px rgba(99,102,241,0.5); }
.btn-secondary { padding: 0.85rem 2rem; border-radius: 999px; background: #fff; color: var(--dark); font-weight: 700; font-size: 0.95rem; border: 1.5px solid #cbd5e1; transition: all 0.2s; }
.btn-secondary:hover { border-color: var(--primary); background: #f8fafc; }
.metrics-wrap { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; max-width: 850px; margin: 0 auto; padding: 2rem; background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.metric-item h4 { font-size: 2rem; font-weight: 800; color: var(--dark); margin-bottom: 0.2rem; }
.metric-item p { font-size: 0.85rem; color: #64748b; font-weight: 500; }
.section { padding: 5.5rem 1.5rem; max-width: 1200px; margin: 0 auto; }
.section-header { text-align: center; max-width: 650px; margin: 0 auto 3.5rem; }
.section-tag { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--primary); letter-spacing: 0.08em; margin-bottom: 0.5rem; }
.section-title { font-size: 2.2rem; font-weight: 800; color: var(--dark); letter-spacing: -0.02em; }
.section-sub { color: #64748b; font-size: 1rem; margin-top: 0.6rem; }
.services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; }
.service-card { background: #fff; padding: 2.2rem; border-radius: 16px; border: 1px solid #e2e8f0; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
.service-card:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: 0 12px 30px rgba(0,0,0,0.06); }
.service-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; margin-bottom: 1.25rem; }
.service-card h3 { font-size: 1.15rem; font-weight: 700; color: var(--dark); margin-bottom: 0.6rem; }
.service-card p { font-size: 0.9rem; color: #64748b; margin-bottom: 1.25rem; line-height: 1.6; }
.card-link { font-size: 0.85rem; font-weight: 700; color: var(--primary); }
.about-section { background: #fff; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 5.5rem 1.5rem; }
.about-grid { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
.about-img-box { border-radius: 24px; padding: 3rem; color: #fff; min-height: 380px; display: flex; flex-direction: column; justify-content: flex-end; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
.about-img-box h3 { font-size: 1.8rem; font-weight: 800; line-height: 1.3; margin-bottom: 0.75rem; }
.about-img-box p { opacity: 0.9; font-size: 0.95rem; }
.about-content h2 { font-size: 2.2rem; font-weight: 800; color: var(--dark); margin-bottom: 1.25rem; }
.about-content p { color: #64748b; font-size: 1.05rem; line-height: 1.7; margin-bottom: 1.5rem; }
.contact-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 3rem; background: #fff; border-radius: 24px; padding: 3.5rem; border: 1px solid #e2e8f0; box-shadow: 0 10px 35px rgba(0,0,0,0.04); }
.c-info h3 { font-size: 1.6rem; font-weight: 800; color: var(--dark); margin-bottom: 1rem; }
.c-item { margin-top: 1.25rem; }
.c-item span { font-size: 0.8rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 0.2rem; }
.c-item strong { font-size: 1rem; color: var(--dark); }
.c-form input, .c-form textarea { width: 100%; padding: 0.85rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 12px; margin-bottom: 1rem; font-family: inherit; font-size: 0.95rem; transition: border 0.2s; }
.c-form input:focus, .c-form textarea:focus { outline: none; border-color: var(--primary); }
.c-form textarea { min-height: 110px; resize: vertical; }
#back-to-top { display: none; position: fixed; bottom: 2rem; right: 2rem; z-index: 90; width: 44px; height: 44px; border-radius: 50%; background: var(--dark); color: #fff; border: none; cursor: pointer; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-size: 1.2rem; }
footer { background: var(--dark); color: #94a3b8; padding: 3.5rem 1.5rem 2rem; border-top: 1px solid #1e293b; }
.footer-container { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }
.footer-links { display: flex; gap: 1.75rem; font-size: 0.9rem; }
.footer-links a:hover { color: #fff; }
@media (max-width: 768px) { .nav-links { display: none; } .hamburger { display: block; } .about-grid, .contact-grid { grid-template-columns: 1fr; padding: 2rem; } .metrics-wrap { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<nav class="nav-wrap">
  <div class="nav-container">
    <a href="#" class="brand-logo"><div class="brand-dot"></div>{$bizName}</a>
    <div class="nav-links">
      <a href="#services">Services</a>
      <a href="#about">About</a>
      <a href="#showcase">Showcase</a>
      <a href="#testimonials">Reviews</a>
      <a href="#faq">FAQ</a>
      <a href="#contact" class="btn-nav">Get in Touch</a>
    </div>
    <button class="hamburger" onclick="toggleMobileMenu()" aria-label="Toggle navigation">☰</button>
  </div>
  <div id="mobile-drawer">
    <a href="#services">Services</a>
    <a href="#about">About</a>
    <a href="#showcase">Showcase</a>
    <a href="#testimonials">Reviews</a>
    <a href="#faq">FAQ</a>
    <a href="#contact">Contact</a>
  </div>
</nav>

<main>
{$heroHtml}

<section class="section" id="services">
  <div class="section-header">
    <div class="section-tag">What We Do</div>
    <h2 class="section-title">Tailored Solutions for Your Growth</h2>
    <p class="section-sub">Comprehensive offerings crafted to elevate, convert, and scale your brand.</p>
  </div>
  <div class="services-grid">{$cardsHtml}</div>
</section>

{$showcaseHtml}

<section class="about-section" id="about">
  <div class="about-grid">
    <div class="about-img-box" style="background: linear-gradient(135deg, rgba(15,23,42,0.88) 0%, rgba(15,23,42,0.4) 60%), url('{$aboutImg}') center/cover no-repeat;">
      <h3>Built with precision.<br>Engineered for growth.</h3>
      <p>{$bizName} combines strategy, modern design, and technology to deliver outstanding digital experiences.</p>
    </div>
    <div class="about-content">
      <div class="section-tag">About {$bizName}</div>
      <h2>Dedicated to craft and outcome</h2>
      <p>We are a specialized {$bizType} team committed to empowering our clients with high-performing web platforms that drive real, measurable impact.</p>
      <p>From initial discovery to launch and ongoing evolution, we stay laser-focused on speed, quality, and conversion.</p>
      <a href="#contact" class="btn-primary">Work With Us</a>
    </div>
  </div>
</section>

{$testimonialsHtml}

{$faqHtml}

<section class="section" id="contact">
  <div class="contact-grid">
    <div class="c-info">
      <div class="section-tag">Get In Touch</div>
      <h3>Let's build something remarkable together.</h3>
      <p style="color:#64748b;line-height:1.6">Have a question or ready to begin? Send us a note and our team will get back to you within 24 hours.</p>
      <div class="c-item"><span>Phone</span><strong>{$bizPhone}</strong></div>
      <div class="c-item"><span>Email</span><strong>{$bizEmail}</strong></div>
      <div class="c-item"><span>Office</span><strong>{$bizAddress}</strong></div>
      {$mapEmbed}
    </div>
    <form class="c-form" onsubmit="handleContactSubmit(event)">
      <input type="text" name="name" placeholder="Your Full Name" required>
      <input type="email" name="email" placeholder="Your Email Address" required>
      <textarea name="message" placeholder="Tell us about your project..." required></textarea>
      <button type="submit" class="btn-primary" style="width:100%; border:none; cursor:pointer;">Send Message &rarr;</button>
    </form>
  </div>
</section>
</main>

<button id="back-to-top" title="Back to top">↑</button>

<footer>
  <div class="footer-container">
    <div><strong style="color:#fff;font-size:1.1rem">{$bizName}</strong><p style="font-size:0.85rem;margin-top:0.3rem">{$bizTagline}</p></div>
    <div class="footer-links"><a href="#services">Services</a><a href="#about">About</a><a href="#showcase">Showcase</a><a href="#testimonials">Reviews</a><a href="#faq">FAQ</a><a href="#contact">Contact</a></div>
    <p style="font-size:0.8rem">&copy; {$year} {$bizName}. All rights reserved.</p>
  </div>
</footer>

{$sharedJs}
</body>
</html>
HTML;
}

// ═══════════════════════════════════════════════════════════════
//  Design 2: Bold Dynamic & High-Converting
//  Supports Sub-Variants: A (Neo-Brutalist), B (Cyber Aurora), C (Velocity Growth)
// ═══════════════════════════════════════════════════════════════
function buildDesign2($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets = null, $mapEmbed = null, $subVariant = 'A', $customReviews = null) {
    if (!$assets) $assets = getNicheAssets($bizType, $bizName);
    if ($mapEmbed === null) $mapEmbed = getMapEmbed($bizAddress);
    $servicesList = array_filter(array_map('trim', explode(',', $bizServices)));
    if (empty($servicesList)) $servicesList = ['Rapid Launch', 'High Conversion Strategy', 'Omnichannel Growth', 'Full Support'];

    $featCards = '';
    $emojis = ['🚀', '🔥', '💎', '📈', '🎯', '✨'];
    foreach ($servicesList as $idx => $s) {
        $em = $emojis[$idx % count($emojis)];
        $featCards .= "<article class='bold-card'><div class='bold-badge'>Feature " . ($idx + 1) . "</div><div class='bold-icon'>{$em}</div><h3>" . htmlspecialchars($s) . "</h3><p>Engineered for maximum velocity, user retention, and peak performance for " . htmlspecialchars($bizAudience) . ".</p></article>";
    }

    $showcaseHtml = getShowcaseHtml($bizName, $bizType, 'bold', $cp, $assets);
    $testimonialsHtml = getTestimonialsHtml($bizName, $bizType, 'bold', $cp, $customReviews);
    $faqHtml = getFaqHtml($bizName, $bizType, 'bold', $cp);
    $sharedJs = getSharedJS($bizName);
    $year = date('Y');
    $heroImg = $assets['hero'] ?? 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=1200&q=80';

    $pageTitle = ($subVariant === 'B') ? "{$bizName} — Cyber Aurora" : (($subVariant === 'C') ? "{$bizName} — Velocity Funnel" : "{$bizName} | Accelerate Your Vision");

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$pageTitle}</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; color: #0f172a; background: #ffffff; overflow-x: hidden; }
:root { --primary: {$cp['primary']}; --primary-gradient: {$cp['gradient']}; --primary-light: {$cp['light']}; --dark: #0f172a; }
h1, h2, h3, h4, .brand-font { font-family: 'Space Grotesk', sans-serif; }
a { text-decoration: none; color: inherit; }
.top-bar { background: var(--dark); color: #fff; font-size: 0.8rem; font-weight: 600; text-align: center; padding: 0.5rem 1rem; }
.top-bar span { color: #38bdf8; }
.nav { position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-bottom: 2px solid #0f172a; padding: 1.1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
.logo { font-size: 1.4rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.4rem; }
.logo-badge { background: var(--primary-gradient); color: #fff; border-radius: 6px; padding: 0.15rem 0.5rem; font-size: 0.85rem; }
.nav-links { display: flex; align-items: center; gap: 1.75rem; font-weight: 600; font-size: 0.95rem; }
.nav-btn { background: var(--primary-gradient); color: #fff !important; padding: 0.6rem 1.4rem; border-radius: 12px; font-weight: 700; box-shadow: 4px 4px 0px #0f172a; border: 2px solid #0f172a; transition: transform 0.15s, box-shadow 0.15s; }
.nav-btn:hover { transform: translate(-2px, -2px); box-shadow: 6px 6px 0px #0f172a; }
.hamburger { display: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #0f172a; }
#mobile-drawer { display: none; flex-direction: column; background: #fff; padding: 1.5rem; border-bottom: 2px solid #0f172a; gap: 1rem; font-weight: 700; }
#mobile-drawer.open { display: flex; }
.hero { padding: 5rem 2rem; background: radial-gradient(circle at 80% 20%, {$cp['light']} 0%, #ffffff 60%); }
.hero-container { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 3.5rem; align-items: center; }
.pill-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: #f1f5f9; border: 2px solid #0f172a; padding: 0.35rem 0.9rem; border-radius: 999px; font-size: 0.82rem; font-weight: 700; box-shadow: 3px 3px 0px #0f172a; margin-bottom: 1.5rem; }
.hero h1 { font-size: clamp(2.5rem, 5vw, 4.2rem); font-weight: 800; line-height: 1.08; color: #0f172a; margin-bottom: 1.5rem; letter-spacing: -0.03em; }
.hero h1 span { background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.hero p { font-size: 1.15rem; color: #475569; line-height: 1.7; margin-bottom: 2.2rem; max-width: 580px; }
.hero-cta-group { display: flex; gap: 1.2rem; flex-wrap: wrap; }
.btn-bold { padding: 1rem 2.2rem; border-radius: 12px; background: var(--primary-gradient); color: #fff; font-size: 1rem; font-weight: 700; border: 2px solid #0f172a; box-shadow: 5px 5px 0px #0f172a; transition: transform 0.15s, box-shadow 0.15s; display: inline-flex; align-items: center; gap: 0.5rem; }
.btn-bold:hover { transform: translate(-2px, -2px); box-shadow: 7px 7px 0px #0f172a; }
.btn-outline-bold { padding: 1rem 2.2rem; border-radius: 12px; background: #fff; color: #0f172a; font-size: 1rem; font-weight: 700; border: 2px solid #0f172a; box-shadow: 5px 5px 0px #0f172a; transition: transform 0.15s, box-shadow 0.15s; }
.btn-outline-bold:hover { transform: translate(-2px, -2px); box-shadow: 7px 7px 0px #0f172a; background: #f8fafc; }
.hero-mockup { background: #0f172a; border: 3px solid #0f172a; border-radius: 20px; padding: 2rem; color: #fff; box-shadow: 12px 12px 0px var(--primary); }
.mockup-header { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
.mockup-dot { width: 12px; height: 12px; border-radius: 50%; background: #ef4444; }
.mockup-dot:nth-child(2) { background: #f59e0b; }
.mockup-dot:nth-child(3) { background: #10b981; }
.mockup-box { background: rgba(255,255,255,0.08); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
.mockup-stat { font-size: 2.2rem; font-weight: 800; color: #38bdf8; font-family: 'Space Grotesk'; }
.section { padding: 6rem 2rem; max-width: 1200px; margin: 0 auto; }
.section-title { font-size: 2.6rem; font-weight: 800; text-align: center; margin-bottom: 0.5rem; letter-spacing: -0.02em; }
.section-subtitle { text-align: center; color: #64748b; font-size: 1.1rem; margin-bottom: 3.5rem; }
.bold-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
.bold-card { background: #ffffff; border: 2.5px solid #0f172a; border-radius: 18px; padding: 2.2rem; box-shadow: 6px 6px 0px #0f172a; transition: transform 0.2s, box-shadow 0.2s; position: relative; }
.bold-card:hover { transform: translate(-3px, -3px); box-shadow: 9px 9px 0px #0f172a; }
.bold-badge { position: absolute; top: 1.2rem; right: 1.2rem; background: var(--primary-light); color: var(--primary); font-size: 0.72rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 999px; border: 1px solid var(--primary); }
.bold-icon { font-size: 2.5rem; margin-bottom: 1.25rem; }
.bold-card h3 { font-size: 1.35rem; font-weight: 700; margin-bottom: 0.6rem; }
.bold-card p { color: #64748b; font-size: 0.95rem; line-height: 1.6; }
.cta-banner { background: var(--primary-gradient); border: 3px solid #0f172a; border-radius: 24px; padding: 4.5rem 2rem; text-align: center; color: #fff; box-shadow: 10px 10px 0px #0f172a; margin: 4rem 2rem; }
.cta-banner h2 { font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 800; margin-bottom: 1rem; }
.cta-banner p { font-size: 1.15rem; max-width: 600px; margin: 0 auto 2rem; opacity: 0.95; }
.btn-white { background: #ffffff; color: #0f172a; padding: 1rem 2.5rem; border-radius: 12px; font-weight: 800; font-size: 1rem; border: 2px solid #0f172a; box-shadow: 5px 5px 0px #0f172a; display: inline-block; cursor: pointer; transition: transform 0.15s; }
.btn-white:hover { transform: translate(-2px, -2px); box-shadow: 7px 7px 0px #0f172a; }
.contact-section { background: #f8fafc; border-top: 2px solid #0f172a; padding: 5rem 2rem; }
.contact-wrap { max-width: 1000px; margin: 0 auto; background: #fff; border: 3px solid #0f172a; border-radius: 20px; padding: 3rem; box-shadow: 8px 8px 0px #0f172a; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; }
.contact-form input, .contact-form textarea { width: 100%; padding: 0.85rem; border: 2px solid #0f172a; border-radius: 10px; margin-bottom: 1rem; font-family: inherit; font-size: 0.95rem; }
.contact-form textarea { min-height: 120px; }
#back-to-top { display: none; position: fixed; bottom: 2rem; right: 2rem; z-index: 90; width: 44px; height: 44px; border-radius: 10px; background: #0f172a; color: #fff; border: 2px solid #0f172a; box-shadow: 3px 3px 0px #fff; cursor: pointer; align-items: center; justify-content: center; font-size: 1.2rem; }
footer { background: #0f172a; color: #fff; padding: 3rem 2rem; border-top: 2px solid #0f172a; text-align: center; }
@media (max-width: 850px) { .hero-container, .contact-wrap { grid-template-columns: 1fr; } .nav-links { display: none; } .hamburger { display: block; } }
</style>
</head>
<body>

<div class="top-bar">⚡ Special Launch: Discover modern capabilities built for <span>{$bizAudience}</span></div>

<nav class="nav">
  <a href="#" class="logo"><span class="logo-badge">✦</span>{$bizName}</a>
  <div class="nav-links">
    <a href="#services">Features</a>
    <a href="#showcase">Results</a>
    <a href="#testimonials">Reviews</a>
    <a href="#faq">FAQ</a>
    <a href="#contact" class="nav-btn">Get Started &rarr;</a>
  </div>
  <button class="hamburger" onclick="toggleMobileMenu()">☰</button>
</nav>

<div id="mobile-drawer">
  <a href="#services">Features</a>
  <a href="#showcase">Results</a>
  <a href="#testimonials">Reviews</a>
  <a href="#faq">FAQ</a>
  <a href="#contact">Contact</a>
</div>

<main>
<header class="hero">
  <div class="hero-container">
    <div>
      <div class="pill-badge">🚀 High-Performance {$bizType}</div>
      <h1>Accelerate your growth with <span>{$bizName}</span>.</h1>
      <p>{$bizTagline}</p>
      <div class="hero-cta-group">
        <a href="#contact" class="btn-bold">Claim Free Consultation &rarr;</a>
        <a href="#services" class="btn-outline-bold">View Capabilities</a>
      </div>
    </div>
    <div class="hero-mockup" style="background: linear-gradient(135deg, rgba(15,23,42,0.92), rgba(15,23,42,0.78)), url('{$heroImg}') center/cover no-repeat;">
      <div class="mockup-header"><div class="mockup-dot"></div><div class="mockup-dot"></div><div class="mockup-dot"></div></div>
      <div class="mockup-box"><div style="font-size:0.85rem;color:#94a3b8">Growth Velocity</div><div class="mockup-stat">+340%</div></div>
      <div class="mockup-box"><div style="font-size:0.85rem;color:#94a3b8">Customer Conversion Rate</div><div class="mockup-stat" style="color:#10b981">4.8x</div></div>
      <p style="font-size:0.85rem;opacity:0.8">Empowering {$bizAudience} with outcomes that matter.</p>
    </div>
  </div>
</header>

<section class="section" id="services">
  <h2 class="section-title">High-Impact Capabilities</h2>
  <p class="section-subtitle">Everything you need to outpace competitors and turn visitors into loyal advocates.</p>
  <div class="bold-grid">{$featCards}</div>
</section>

{$showcaseHtml}

{$testimonialsHtml}

{$faqHtml}

<div class="cta-banner">
  <h2>Ready to transform your results?</h2>
  <p>Join forward-thinking partners who rely on {$bizName} for top-tier execution.</p>
  <a href="#contact" class="btn-white">Book Your Discovery Call &rarr;</a>
</div>

<div class="contact-section" id="contact">
  <div class="contact-wrap">
    <div>
      <h2 style="font-size:2rem;margin-bottom:1rem">Let's Connect</h2>
      <p style="color:#64748b;margin-bottom:2rem">Reach out directly and our specialists will formulate a personalized growth plan for your team.</p>
      <p style="margin-bottom:0.75rem"><strong>📞 Phone:</strong> {$bizPhone}</p>
      <p style="margin-bottom:0.75rem"><strong>📧 Email:</strong> {$bizEmail}</p>
      <p><strong>📍 Address:</strong> {$bizAddress}</p>
      {$mapEmbed}
    </div>
    <form class="contact-form" onsubmit="handleContactSubmit(event)">
      <input type="text" name="name" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email Address" required>
      <textarea name="message" placeholder="How can we help you?" required></textarea>
      <button type="submit" class="btn-bold" style="width:100%; cursor:pointer;">Send Message Now &rarr;</button>
    </form>
  </div>
</div>
</main>

<button id="back-to-top" title="Back to top">↑</button>

<footer>
  <p style="font-weight:700;font-size:1.1rem;margin-bottom:0.5rem">{$bizName}</p>
  <p style="font-size:0.85rem;color:#94a3b8">&copy; {$year} {$bizName}. Designed to convert.</p>
</footer>

{$sharedJs}
</body>
</html>
HTML;
}

// ═══════════════════════════════════════════════════════════════
//  Design 3: Executive Luxury & Dark Mode
//  Supports Sub-Variants: A (Obsidian Gold), B (Frosted Aurora), C (Private Office)
// ═══════════════════════════════════════════════════════════════
function buildDesign3($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets = null, $mapEmbed = null, $subVariant = 'A', $customReviews = null) {
    if (!$assets) $assets = getNicheAssets($bizType, $bizName);
    if ($mapEmbed === null) $mapEmbed = getMapEmbed($bizAddress);
    $servicesList = array_filter(array_map('trim', explode(',', $bizServices)));
    if (empty($servicesList)) $servicesList = ['Bespoke Architecture', 'Private Advisory', 'Autonomous Systems', 'Elite Support'];

    $luxCards = '';
    $symbols = ['◈', '✦', '❖', '★', '◉', '▲'];
    foreach ($servicesList as $idx => $s) {
        $sy = $symbols[$idx % count($symbols)];
        $luxCards .= "<article class='glass-card'><div class='glass-icon'>{$sy}</div><h3>" . htmlspecialchars($s) . "</h3><p>Uncompromising craftsmanship and tailored precision tailored specifically for " . htmlspecialchars($bizAudience) . ".</p></article>";
    }

    $showcaseHtml = getShowcaseHtml($bizName, $bizType, 'dark', $cp, $assets);
    $testimonialsHtml = getTestimonialsHtml($bizName, $bizType, 'dark', $cp, $customReviews);
    $faqHtml = getFaqHtml($bizName, $bizType, 'dark', $cp);
    $sharedJs = getSharedJS($bizName);
    $year = date('Y');
    $heroImg = $assets['hero'] ?? 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80';

    $pageTitle = ($subVariant === 'B') ? "{$bizName} — Sovereign Glassmorphism" : (($subVariant === 'C') ? "{$bizName} — Private Office Suite" : "{$bizName} — Executive Suite");

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$pageTitle}</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', -apple-system, sans-serif; color: #f1f5f9; background: #090d16; line-height: 1.7; overflow-x: hidden; }
:root { --primary: {$cp['primary']}; --primary-gradient: {$cp['gradient']}; --primary-accent: {$cp['accent']}; --gold: #f59e0b; }
h1, h2, .brand-font { font-family: 'Cinzel', serif; letter-spacing: 0.04em; }
a { text-decoration: none; color: inherit; }
.nav-bar { position: sticky; top: 0; z-index: 100; background: rgba(9, 13, 22, 0.88); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; }
.brand-title { font-size: 1.35rem; font-weight: 700; color: #fff; letter-spacing: 0.12em; text-transform: uppercase; display: flex; align-items: center; gap: 0.6rem; }
.brand-gem { width: 8px; height: 8px; transform: rotate(45deg); background: var(--primary); box-shadow: 0 0 10px var(--primary); }
.nav-menu { display: flex; gap: 2rem; align-items: center; font-size: 0.85rem; letter-spacing: 0.05em; text-transform: uppercase; color: #94a3b8; }
.nav-menu a:hover { color: #fff; }
.btn-lux { padding: 0.65rem 1.6rem; border-radius: 4px; background: rgba(255, 255, 255, 0.06); color: #fff; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.8rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; transition: all 0.3s; }
.btn-lux:hover { background: #fff; color: #090d16; box-shadow: 0 0 20px rgba(255,255,255,0.4); }
.hamburger { display: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #fff; }
#mobile-drawer { display: none; flex-direction: column; background: #090d16; padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); gap: 1rem; font-size: 0.9rem; text-transform: uppercase; }
#mobile-drawer.open { display: flex; }
.hero { min-height: 88vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 6rem 2rem; position: relative; }
.hero::before { content: ''; position: absolute; top: 20%; left: 50%; transform: translate(-50%, -20%); width: 600px; height: 600px; border-radius: 50%; background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%); pointer-events: none; filter: blur(50px); }
.hero-content { max-width: 880px; position: relative; z-index: 2; }
.hero-pre { font-size: 0.8rem; font-weight: 700; letter-spacing: 0.25em; text-transform: uppercase; color: var(--primary); margin-bottom: 1.5rem; }
.hero h1 { font-size: clamp(2.4rem, 5.5vw, 4.4rem); font-weight: 700; color: #fff; line-height: 1.15; margin-bottom: 1.5rem; }
.hero p { font-size: 1.15rem; color: #94a3b8; max-width: 650px; margin: 0 auto 2.5rem; font-weight: 300; line-height: 1.8; }
.btn-gold { display: inline-block; padding: 0.95rem 2.5rem; border-radius: 4px; background: var(--primary-gradient); color: #fff; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; box-shadow: 0 0 25px rgba(99,102,241,0.35); transition: all 0.3s; }
.btn-gold:hover { transform: translateY(-2px); box-shadow: 0 0 35px rgba(99,102,241,0.6); }
.section { padding: 6rem 2rem; max-width: 1200px; margin: 0 auto; position: relative; }
.section-tag { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: var(--primary); text-align: center; margin-bottom: 0.6rem; }
.section-head { font-size: 2.2rem; text-align: center; color: #fff; margin-bottom: 3.5rem; }
.glass-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 1.8rem; }
.glass-card { background: rgba(255, 255, 255, 0.025); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 2.5rem; backdrop-filter: blur(12px); transition: all 0.3s; }
.glass-card:hover { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.25); transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
.glass-icon { font-size: 1.8rem; color: var(--primary); margin-bottom: 1.25rem; }
.glass-card h3 { font-size: 1.15rem; font-weight: 600; color: #fff; margin-bottom: 0.75rem; letter-spacing: 0.02em; }
.glass-card p { font-size: 0.9rem; color: #94a3b8; line-height: 1.7; }
.contact-glass { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.09); border-radius: 16px; padding: 4rem 3rem; max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1.2fr; gap: 3rem; }
.contact-glass input, .contact-glass textarea { width: 100%; padding: 0.9rem 1.1rem; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 6px; color: #fff; margin-bottom: 1rem; font-family: inherit; font-size: 0.9rem; }
.contact-glass input:focus, .contact-glass textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 10px rgba(99,102,241,0.3); }
#back-to-top { display: none; position: fixed; bottom: 2rem; right: 2rem; z-index: 90; width: 44px; height: 44px; border-radius: 4px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); cursor: pointer; align-items: center; justify-content: center; font-size: 1.2rem; }
footer { border-top: 1px solid rgba(255, 255, 255, 0.06); padding: 3.5rem 2rem; text-align: center; font-size: 0.85rem; color: #64748b; }
@media (max-width: 768px) { .nav-menu { display: none; } .hamburger { display: block; } .contact-glass { grid-template-columns: 1fr; padding: 2rem; } }
</style>
</head>
<body>

<nav class="nav-bar">
  <div class="brand-title"><div class="brand-gem"></div>{$bizName}</div>
  <div class="nav-menu">
    <a href="#services">Offerings</a>
    <a href="#showcase">Portfolio</a>
    <a href="#testimonials">Accolades</a>
    <a href="#faq">Inquiries</a>
    <a href="#contact" class="btn-lux">Consultation</a>
  </div>
  <button class="hamburger" onclick="toggleMobileMenu()">☰</button>
</nav>

<div id="mobile-drawer">
  <a href="#services">Offerings</a>
  <a href="#showcase">Portfolio</a>
  <a href="#testimonials">Accolades</a>
  <a href="#faq">Inquiries</a>
  <a href="#contact">Contact</a>
</div>

<main>
<header class="hero">
  <div class="hero-content">
    <div class="hero-pre">Bespoke {$bizType} Solutions</div>
    <h1>{$bizTagline}</h1>
    <p>Distinguished digital craftsmanship tailored for {$bizAudience} who demand unmatched sophistication and execution.</p>
    <a href="#contact" class="btn-gold">Request Private Briefing &rarr;</a>
  </div>
</header>

<div class="showcase-strip" style="max-width:1200px; margin:0 auto 3rem; padding:0 2rem;">
  <div style="height:320px; border-radius:18px; overflow:hidden; border:1px solid rgba(255,255,255,0.12); position:relative; box-shadow:0 20px 50px rgba(0,0,0,0.5);">
    <img src="{$heroImg}" alt="{$bizName} flagship" style="width:100%; height:100%; object-fit:cover; filter:brightness(0.75);">
    <div style="position:absolute; bottom:2rem; left:2rem; background:rgba(9,13,22,0.85); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.15); padding:1rem 1.5rem; border-radius:12px; max-width:420px;">
      <div style="font-size:0.75rem; color:var(--primary); font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Flagship Experience</div>
      <div style="font-size:1.1rem; color:#fff; font-weight:700; margin-top:0.25rem;">{$bizName} Signature Standard</div>
    </div>
  </div>
</div>

<section class="section" id="services">
  <div class="section-tag">Distinctive Capabilities</div>
  <h2 class="section-head">Crafted for Discerning Standards</h2>
  <div class="glass-grid">{$luxCards}</div>
</section>

{$showcaseHtml}

{$testimonialsHtml}

{$faqHtml}

<section class="section" id="contact">
  <div class="contact-glass">
    <div>
      <div class="section-tag" style="text-align:left">Inquiries</div>
      <h2 style="font-size:1.8rem;color:#fff;margin:0.5rem 0 1rem">Connect Privately</h2>
      <p style="color:#94a3b8;font-size:0.95rem;margin-bottom:1.5rem">Direct correspondence with our lead partners.</p>
      <p style="margin-bottom:0.75rem"><strong>Direct:</strong> {$bizPhone}</p>
      <p style="margin-bottom:0.75rem"><strong>Confidential:</strong> {$bizEmail}</p>
      <p><strong>Headquarters:</strong> {$bizAddress}</p>
      {$mapEmbed}
    </div>
    <form onsubmit="handleContactSubmit(event)">
      <input type="text" name="name" placeholder="Your Distinguished Name" required>
      <input type="email" name="email" placeholder="Professional Email" required>
      <textarea name="message" rows="4" placeholder="Brief statement of requirements..." required></textarea>
      <button type="submit" class="btn-gold" style="width:100%; border:none; cursor:pointer;">Submit Inquiry</button>
    </form>
  </div>
</section>
</main>

<button id="back-to-top" title="Back to top">↑</button>

<footer>
  <p style="letter-spacing:0.1em;text-transform:uppercase">&copy; {$year} {$bizName}. All rights reserved.</p>
</footer>

{$sharedJs}
</body>
</html>
HTML;
}

// ═══════════════════════════════════════════════════════════════
//  Helper: getSubDesigns (3 specialized sub-designs for chosen concept)
// ═══════════════════════════════════════════════════════════════
function getSubDesigns($conceptIndex, $bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, $customReviews = null) {
    if ($conceptIndex === 0 || $conceptIndex === 'classic' || $conceptIndex === '1') {
        return [
            [
                'id' => '1A',
                'name' => '1A · Conversion & Split Hero',
                'badge' => 'High Conversion',
                'description' => 'Direct value proposition, floating metrics bar, split-screen storytelling, and high-converting contact flow.',
                'html' => buildDesign1($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'A', $customReviews)
            ],
            [
                'id' => '1B',
                'name' => '1B · Bento Grid & Media Showcase',
                'badge' => 'Media Rich',
                'description' => 'Modern asymmetric bento layout, featured showcase cards, interactive hover states, and dynamic visual rhythm.',
                'html' => buildDesign1($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'B', $customReviews)
            ],
            [
                'id' => '1C',
                'name' => '1C · Editorial Authority & Trust',
                'badge' => 'Brand Authority',
                'description' => 'Magazine-style typography, trust proof credentials, narrative feature rows, and prestigious client endorsements.',
                'html' => buildDesign1($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'C', $customReviews)
            ]
        ];
    } elseif ($conceptIndex === 1 || $conceptIndex === 'bold' || $conceptIndex === '2') {
        return [
            [
                'id' => '2A',
                'name' => '2A · Neo-Brutalist High Impact',
                'badge' => 'High Impact',
                'description' => 'Solid dark borders, vibrant drop-shadow badges, energetic neo-cards, and high-visibility CTAs.',
                'html' => buildDesign2($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'A', $customReviews)
            ],
            [
                'id' => '2B',
                'name' => '2B · Gradient Aurora & Cyber Fluid',
                'badge' => 'Cyber Fluid',
                'description' => 'Vibrant gradient mesh backdrop, glowing bento panels, 3D hover scale, and tech-forward atmosphere.',
                'html' => buildDesign2($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'B', $customReviews)
            ],
            [
                'id' => '2C',
                'name' => '2C · High-Velocity Growth Funnel',
                'badge' => 'Growth Funnel',
                'description' => 'Sticky top announcement, conversion mockup card, metric badges, and fast-action lead capture.',
                'html' => buildDesign2($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'C', $customReviews)
            ]
        ];
    } else {
        return [
            [
                'id' => '3A',
                'name' => '3A · Midnight Obsidian & Gold',
                'badge' => 'Prestige Gold',
                'description' => 'Deep obsidian backdrop, gold accents, Cinzel typography, and signature flagship showcase.',
                'html' => buildDesign3($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'A', $customReviews)
            ],
            [
                'id' => '3B',
                'name' => '3B · Ambient Frosted Glass & Aurora',
                'badge' => 'Frosted Glass',
                'description' => 'Subtle ambient glows, frosted glass cards with 1px border highlights, and luxury accolades.',
                'html' => buildDesign3($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'B', $customReviews)
            ],
            [
                'id' => '3C',
                'name' => '3C · Bespoke Private Office',
                'badge' => 'Private Concierge',
                'description' => 'Split executive layout, portfolio case studies, white-glove direct inquiry form, and headquarters map.',
                'html' => buildDesign3($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'C', $customReviews)
            ]
        ];
    }
}

// ═══════════════════════════════════════════════════════════════
//  ACTION: save_design
// ═══════════════════════════════════════════════════════════════
if ($action === 'save_design') {
    $bizName  = htmlspecialchars(trim($req['biz_name'] ?? 'Website'));
    $html     = $req['html'] ?? '';
    $designId = 'DSN-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));

    $designsDir = defined('STORAGE_DIR') ? (STORAGE_DIR . '/designs') : (dirname(__DIR__) . '/storage/designs');
    if (!is_dir($designsDir)) mkdir($designsDir, 0755, true);
    file_put_contents($designsDir . '/' . $designId . '.html', $html);

    echo json_encode(['success' => true, 'design_id' => $designId, 'saved_at' => date('Y-m-d H:i:s')]);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  ACTION: flowcraft_generate (Autonomous AI-FlowCraft Engine)
// ═══════════════════════════════════════════════════════════════
if ($action === 'flowcraft_generate') {
    $mode = $req['mode'] ?? 'static';
    $assets = getNicheAssets($bizType, $bizName);
    $mapEmbed = getMapEmbed($bizAddress);

    // ★ Shop detection: products textarea / shop checkbox / product-type business
    $sectionsReq = $d['sections'] ?? [];
    $productsRaw = $d['biz_products'] ?? '';
    $shopOn = isShopSite($bizType, $sectionsReq, $productsRaw);
    $shopProducts = $shopOn ? parseShopProducts($productsRaw, $assets) : [];
    if ($shopOn && empty($shopProducts)) {
        $svcLines = array_filter(array_map('trim', explode(',', $bizServices)));
        $svcLines = array_slice(array_values($svcLines), 0, 6);
        $fallbackLines = [];
        foreach ($svcLines as $s) $fallbackLines[] = $s . ' | Ask price';
        $shopProducts = parseShopProducts(implode("\n", $fallbackLines), $assets);
    }
    $shopHtmlByStyle = [];
    if (!empty($shopProducts)) {
        $shopHtmlByStyle = [
            'light'   => getShopHtml($shopProducts, $cp, $bizPhone, 'light', $bizName, $bizEmail, $bizTagline, $bizAddress),
            'vibrant' => getShopHtml($shopProducts, $cp, $bizPhone, 'bold', $bizName, $bizEmail, $bizTagline, $bizAddress),
            'dark'    => getShopHtml($shopProducts, $cp, $bizPhone, 'dark', $bizName, $bizEmail, $bizTagline, $bizAddress)
        ];
    }
    $injectShop = function ($html, $style) use ($shopHtmlByStyle) {
        $sh = $shopHtmlByStyle[$style] ?? '';
        return injectShopIntoHtml($html, $sh);
    };

    $subdesigns1 = $subdesigns2 = $subdesigns3 = [];
    $flowcraftFallback = false;
    try {
        $subdesigns1 = getSubDesigns(0, $bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, $customReviews);
        $subdesigns2 = getSubDesigns(1, $bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, $customReviews);
        $subdesigns3 = getSubDesigns(2, $bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, $customReviews);
        if (empty($subdesigns1) || empty($subdesigns2) || empty($subdesigns3)) throw new Exception('Empty subdesign set');
    } catch (Throwable $e) {
        // ★ Fallback: server template engine takes over (plain trio, no sub-variants)
        error_log('[generate.php] flowcraft subdesigns failed, template fallback: ' . $e->getMessage());
        $flowcraftFallback = true;
        $mkFallback = function ($n, $fn) use ($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, $customReviews) {
            return [[
                'id' => $n . 'A',
                'name' => 'Concept ' . $n . ' (Template Fallback)',
                'badge' => 'Server Template',
                'description' => 'Generated by the server template engine.',
                'html' => $fn($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'A', $customReviews)
            ]];
        };
        $subdesigns1 = $mkFallback(1, 'buildDesign1');
        $subdesigns2 = $mkFallback(2, 'buildDesign2');
        $subdesigns3 = $mkFallback(3, 'buildDesign3');
    }
    // Taste safety net on every server-template doc (smooth scroll +
    // reduced-motion fallback, idempotent) so templates carry the same
    // taste baseline as AI output.
    $tasteSafe = function ($html) {
        return function_exists('taste_apply_safety_net') ? taste_apply_safety_net($html) : $html;
    };
    foreach ($subdesigns1 as &$sd) { $sd['html'] = $tasteSafe($injectShop($sd['html'], 'light')); }
    unset($sd);
    foreach ($subdesigns2 as &$sd) { $sd['html'] = $tasteSafe($injectShop($sd['html'], 'vibrant')); }
    unset($sd);
    foreach ($subdesigns3 as &$sd) { $sd['html'] = $tasteSafe($injectShop($sd['html'], 'dark')); }
    unset($sd);

    $design1 = $subdesigns1[0]['html'];
    $design2 = $subdesigns2[0]['html'];
    $design3 = $subdesigns3[0]['html'];

    // ── TASTE LAYER meta (design read + dials drive the AI lanes; the
    // template trio carries the same read so the UI stays consistent) ──
    if (!function_exists('taste_infer_brief')) {
        $tsvc = dirname(__DIR__) . '/includes/TasteSkill.php';
        if (file_exists($tsvc)) require_once $tsvc;
    }
    $tasteMeta = null;
    if (function_exists('taste_infer_brief')) {
        $tb = taste_infer_brief(
            ['biz_type' => $bizType, 'biz_audience' => $bizAudience,
             'design_style' => $style, 'design_direction' => ($d['design_direction'] ?? ''),
             'taste' => ($d['taste'] ?? [])],
            (array)($d['taste'] ?? [])
        );
        $tasteMeta = ['design_read' => $tb['design_read'], 'vibe' => $tb['vibe'],
                      'variance' => $tb['variance'], 'motion' => $tb['motion'], 'density' => $tb['density']];
    }

    echo json_encode([
        'success' => true,
        'fallback' => $flowcraftFallback,
        'skills_pipeline' => [
            'phase1' => 'Skills 1-2: Requirements & PRD Discussion',
            'phase2' => 'Skills 3-9: System Architecture, DB/API & Visual Tokens (HTML5/CSS3)',
            'phase3' => 'Skills 10-18: Standards, Glassmorphism, Maps & FAQ Engines',
            'phase4' => 'Skills 19-21: Feature Development & Component Assembly',
            'phase5' => 'Skills 22-26: Five-Layer Testing & Polish'
        ],
        'analysis' => [
            'biz_name' => $bizName,
            'biz_type' => $bizType,
            'audience' => $bizAudience,
            'palette'  => $palette,
            'mode'     => $mode,
            'tag'      => $assets['tag'],
            'shop'     => !empty($shopProducts),
            'products' => count($shopProducts),
            'taste'    => $tasteMeta,
            'brief'    => "AI-FlowCraft 28-Skill Engine analyzed {$bizName} ({$bizType}) for target audience: {$bizAudience}. Synthesized 3 master concepts, each equipped with 3 bespoke HTML5/CSS3 sub-designs." . (!empty($shopProducts) ? " Shop mode ON (" . count($shopProducts) . " products) with working add-to-cart, drawer, quantities and WhatsApp/contact checkout." : "")
        ],
        'designs' => [
            [
                'id' => 'classic',
                'name' => 'Concept 1 — Modern Minimal & Crisp',
                'badge' => 'Clean & Professional',
                'description' => 'Light balanced aesthetic, Plus Jakarta Sans typography, glassmorphism sticky navigation, and refined subtle cards.',
                'style' => 'light',
                'html' => $design1,
                'subdesigns' => $subdesigns1
            ],
            [
                'id' => 'bold',
                'name' => 'Concept 2 — Bold Dynamic & Bento Grid',
                'badge' => 'High-Impact & Modern',
                'description' => 'Space Grotesk typography, vibrant gradient mesh, asymmetrical bento cards, and high-conversion CTA flow.',
                'style' => 'vibrant',
                'html' => $design2,
                'subdesigns' => $subdesigns2
            ],
            [
                'id' => 'editorial',
                'name' => 'Concept 3 — Executive Luxury & Dark Mode',
                'badge' => 'Sleek Dark Glassmorphism',
                'description' => 'Deep obsidian backdrop, glowing ambient auroras, frosted glass panels, and luxury typography for high-end clientele.',
                'style' => 'dark',
                'html' => $design3,
                'subdesigns' => $subdesigns3
            ]
        ],
        'default_html' => $design1
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  ACTION: solo_generate (Solo-style one-page professional site)
//  Falls back to the server template engine on ANY failure.
// ═══════════════════════════════════════════════════════════════
if ($action === 'solo_generate') {
    $assets = getNicheAssets($bizType, $bizName);
    $mapEmbed = getMapEmbed($bizAddress);
    $reviews = parseReviews($d['biz_reviews'] ?? '');
    // ★ 3 Solo variations (light / bold / dark) — EACH with its own
    // fallback: if Solo fails for a style, the server template engine
    // stands in for that slot. Nothing ever comes back empty.
    $soloStyles = [
        ['key' => 'light', 'name' => 'Solo Light — Clean Professional',  'badge' => 'Solo · Light',  'desc' => 'Airy one-pager: services, real reviews, booking contact.', 'fn' => 'buildDesign1'],
        ['key' => 'bold',  'name' => 'Solo Bold — High Impact',          'badge' => 'Solo · Bold',    'desc' => 'Neo-brutal cards, vibrant energy, call-first flow.',                 'fn' => 'buildDesign2'],
        ['key' => 'dark',  'name' => 'Solo Dark — Luxury Night',         'badge' => 'Solo · Dark',    'desc' => 'Dark glassmorphism, glowing CTA, premium night feel.',                'fn' => 'buildDesign3']
    ];
    $designs = [];
    $anyFallback = false;
    foreach ($soloStyles as $sv) {
        $html = '';
        $fb = false;
        try {
            $html = buildSoloDesign($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $reviews, $sv['key']);
            if (strpos($html, '</html>') === false) throw new Exception('Solo build incomplete (' . $sv['key'] . ')');
        } catch (Throwable $e) {
            error_log('[generate.php] solo_generate style ' . $sv['key'] . ' failed, template fallback: ' . $e->getMessage());
            $fb = true;
            $anyFallback = true;
            $fn = $sv['fn'];
            $html = $fn($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'A', $reviews);
        }
        $designs[] = [
            'id' => 'solo-' . $sv['key'],
            'name' => $bizName . ' — ' . $sv['name'],
            'badge' => $fb ? 'Server Template' : $sv['badge'],
            'description' => $sv['desc'] . ($fb ? ' (Template fallback stood in.)' : ''),
            'style' => $sv['key'],
            'html' => function_exists('taste_apply_safety_net') ? taste_apply_safety_net($html) : $html
        ];
    }
    echo json_encode([
        'success' => true,
        'fallback' => $anyFallback,
        'analysis' => [
            'biz_name' => $bizName,
            'biz_type' => $bizType,
            'audience' => $bizAudience,
            'palette'  => $palette,
            'mode'     => 'solo',
            'tag'      => $assets['tag'],
            'reviews'  => count($reviews),
            'brief'    => "Solo-style professional trio for {$bizName} ({$bizType}): light, bold & dark one-pagers with real reviews." . ($anyFallback ? ' Server template engine stood in for at least one slot.' : '')
        ],
        'designs' => $designs
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  ACTION: flowcraft_subdesigns (3 Layout Sub-Designs for Chosen Concept)
// ═══════════════════════════════════════════════════════════════
if ($action === 'flowcraft_subdesigns') {
    $conceptIndex = $req['concept_index'] ?? $req['concept_id'] ?? 0;
    $assets = getNicheAssets($bizType, $bizName);
    $mapEmbed = getMapEmbed($bizAddress);
    $subdesigns = getSubDesigns($conceptIndex, $bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, $customReviews);
    // ★ Shop injection for on-demand sub-design loads
    $sectionsReq = $d['sections'] ?? [];
    $productsRaw = $d['biz_products'] ?? '';
    if (isShopSite($bizType, $sectionsReq, $productsRaw)) {
        $sp = parseShopProducts($productsRaw, $assets);
        if (empty($sp)) {
            $svcLines = array_filter(array_map('trim', explode(',', $bizServices)));
            $fl = [];
            foreach (array_slice(array_values($svcLines), 0, 6) as $s) $fl[] = $s . ' | Ask price';
            $sp = parseShopProducts(implode("\n", $fl), $assets);
        }
        if (!empty($sp)) {
            $ci = (is_string($conceptIndex) && !is_numeric($conceptIndex))
                ? strtolower($conceptIndex)
                : (int)$conceptIndex;
            $sty = ($ci === 1 || $ci === 'bold' || $ci === '2') ? 'bold' : (($ci === 2 || $ci === 'editorial' || $ci === '3') ? 'dark' : 'light');
            $sh = getShopHtml($sp, $cp, $bizPhone, $sty, $bizName, $bizEmail, $bizTagline, $bizAddress);
            foreach ($subdesigns as &$sd) {
                $sd['html'] = injectShopIntoHtml($sd['html'], $sh);
                if (function_exists('taste_apply_safety_net')) $sd['html'] = taste_apply_safety_net($sd['html']);
            }
            unset($sd);
        }
    }

    echo json_encode([
        'success' => true,
        'concept_index' => $conceptIndex,
        'subdesigns' => $subdesigns
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  ACTION: generate_3
// ═══════════════════════════════════════════════════════════════
if ($action === 'generate_3' || $action === 'generate') {
    $assets = getNicheAssets($bizType, $bizName);
    $mapEmbed = getMapEmbed($bizAddress);
    $design1 = buildDesign1($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'A', $customReviews);
    $design2 = buildDesign2($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'A', $customReviews);
    $design3 = buildDesign3($bizName, $bizType, $bizTagline, $bizAudience, $bizServices, $bizPhone, $bizEmail, $bizAddress, $cp, $assets, $mapEmbed, 'A', $customReviews);
    // ★ Shop injection (legacy generate path)
    $sectionsReq = $d['sections'] ?? [];
    $productsRaw = $d['biz_products'] ?? '';
    if (isShopSite($bizType, $sectionsReq, $productsRaw)) {
        $sp = parseShopProducts($productsRaw, $assets);
        if (empty($sp)) {
            $svcLines = array_filter(array_map('trim', explode(',', $bizServices)));
            $fl = [];
            foreach (array_slice(array_values($svcLines), 0, 6) as $s) $fl[] = $s . ' | Ask price';
            $sp = parseShopProducts(implode("\n", $fl), $assets);
        }
        if (!empty($sp)) {
            $design1 = injectShopIntoHtml($design1, getShopHtml($sp, $cp, $bizPhone, 'light', $bizName, $bizEmail, $bizTagline, $bizAddress));
            $design2 = injectShopIntoHtml($design2, getShopHtml($sp, $cp, $bizPhone, 'bold', $bizName, $bizEmail, $bizTagline, $bizAddress));
            $design3 = injectShopIntoHtml($design3, getShopHtml($sp, $cp, $bizPhone, 'dark', $bizName, $bizEmail, $bizTagline, $bizAddress));
        }
        if (function_exists('taste_apply_safety_net')) {
            $design1 = taste_apply_safety_net($design1);
            $design2 = taste_apply_safety_net($design2);
            $design3 = taste_apply_safety_net($design3);
        }
    }

    echo json_encode([
        'success' => true,
        'designs' => [
            ['id' => 1, 'name' => 'Concept 1 — Modern Minimal & Crisp', 'badge' => 'Clean & Professional',
             'description' => 'Light aesthetic with refined typography, balanced whitespace, interactive smooth navigation, and crisp subtle cards.',
             'style' => 'light', 'html' => $design1],
            ['id' => 2, 'name' => 'Concept 2 — Bold Dynamic & High-Converting', 'badge' => 'High-Impact & Conversion-Focused',
             'description' => 'Vibrant gradient accents, striking metrics badges, neo-brutalist buttons, dynamic contact flow, and energetic layout.',
             'style' => 'vibrant', 'html' => $design2],
            ['id' => 3, 'name' => 'Concept 3 — Executive Luxury & Dark Mode', 'badge' => 'Sleek Dark Glassmorphism',
             'description' => 'Deep dark aesthetics, glowing ambient highlights, frosted glass panels, and luxury typography for high-end brands.',
             'style' => 'dark', 'html' => $design3]
        ],
        'default_html' => $design1
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  ACTION: update_details  (with change detection)
// ═══════════════════════════════════════════════════════════════
if ($action === 'update_details') {
    $currentHTML = $req['current_html'] ?? '';
    $details = $req['details'] ?? [];
    if (empty($currentHTML)) {
        echo json_encode(['success' => false, 'error' => 'Missing HTML']);
        exit;
    }

    $modified = $currentHTML;
    $changeCount = 0;

    if (!empty($details['phone'])) {
        $newPhone = $sanitize($details['phone']);
        $after = preg_replace('/(\+?[\d\s\-\(\)]{9,20})/', $newPhone, $modified, 4);
        if ($after !== $modified) { $modified = $after; $changeCount++; }
    }

    if (!empty($details['email'])) {
        $newEmail = $sanitize($details['email']);
        $after = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $newEmail, $modified, 4);
        if ($after !== $modified) { $modified = $after; $changeCount++; }
    }

    if (!empty($details['address'])) {
        $newAddr = $sanitize($details['address']);
        $after = preg_replace('/(<strong>Headquarters:<\/strong>\s*|<span>Office<\/span>\s*<strong>|<strong>📍 Address:<\/strong>\s*)[^<]+/i', '$1' . $newAddr, $modified, 2);
        if ($after !== $modified) { $modified = $after; $changeCount++; }
    }

    if (!empty($details['tagline'])) {
        $newTag = $sanitize($details['tagline']);
        $after = preg_replace('/(<header[^>]*>[\s\S]*?<h1[^>]*>)([\s\S]*?)(<\/h1>)/i', '$1' . $newTag . '$3', $modified, 1);
        if ($after !== $modified) { $modified = $after; $changeCount++; }
    }

    if (!empty($details['cta_text'])) {
        $newCta = $sanitize($details['cta_text']);
        $after = preg_replace('/(<a[^>]*class=["\'][^"\']*(?:btn-primary|btn-bold|btn-gold)[^"\']*["\'][^>]*>)([\s\S]*?)(<\/a>)/i', '$1' . $newCta . ' &rarr;$3', $modified, 1);
        if ($after !== $modified) { $modified = $after; $changeCount++; }
    }

    if (!empty($details['new_service'])) {
        $newSvc = $sanitize($details['new_service']);
        $newCard = "<div class='service-card'><div class='service-icon'>✦</div><h3>{$newSvc}</h3><p>Tailored high-impact solutions built to deliver measurable excellence and sustainable outcomes.</p><a href='#contact' class='card-link'>Inquire Now &rarr;</a></div>";
        $after = preg_replace('/(<\/div>\s*<\/section>)/i', $newCard . '$1', $modified, 1);
        if ($after !== $modified) { $modified = $after; $changeCount++; }
    }

    if ($changeCount === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'None of the provided details could be applied — the fields may already contain those values.',
            'html' => $currentHTML
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => true, 'html' => $modified, 'source' => 'batch_updater', 'changes' => $changeCount]);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  ACTION: rewrite_text  (with honest fallback)
// ═══════════════════════════════════════════════════════════════
if ($action === 'rewrite_text') {
    $text = trim($req['text'] ?? '');
    $tone = trim($req['tone'] ?? 'punchy');
    $instruction = trim($req['instruction'] ?? '');

    if (empty($text)) {
        echo json_encode(['success' => false, 'error' => 'No text provided to rewrite']);
        exit;
    }

    if (!empty($apiKey) && strlen($apiKey) > 10) {
        $prompt = "You are an elite conversion copywriter for websites. Rewrite the following text to make it more engaging.\n\n"
                . "ORIGINAL TEXT: \"{$text}\"\n"
                . "DESIRED TONE: {$tone}\n"
                . (!empty($instruction) ? "SPECIFIC INSTRUCTION: {$instruction}\n" : "")
                . "REQUIREMENTS:\n"
                . "1. Return ONLY the rewritten text string.\n"
                . "2. Do NOT output quotation marks around the text, markdown formatting, or commentary.\n"
                . "3. Keep roughly similar length unless the user specifically requested otherwise.";

        list($ok, $aiText, $code, $errMsg) = callGemini($apiKey, $resolvedModel, $prompt, 1024);
        if ($ok && !empty($aiText)) {
            $rewritten = trim(trim($aiText, "\"'` \n\r\t"));
            if (!empty($rewritten)) {
                echo json_encode(['success' => true, 'rewritten_text' => $rewritten, 'source' => 'gemini'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    // Smart offline rewrite
    $rewritten = $text;
    if ($tone === 'punchy') {
        $rewritten = "Engineered for Growth: " . rtrim($text, '.') . " — Built to Outperform.";
    } elseif ($tone === 'professional') {
        $rewritten = "Delivering strategic " . strtolower(rtrim($text, '.')) . " with proven industry excellence.";
    } elseif ($tone === 'luxury') {
        $rewritten = "The pinnacle of bespoke distinction: " . rtrim($text, '.') . ".";
    } elseif ($tone === 'friendly') {
        $rewritten = "We make " . strtolower(rtrim($text, '.')) . " simple, seamless, and rewarding.";
    } elseif ($tone === 'concise') {
        $words = explode(' ', $text);
        $rewritten = implode(' ', array_slice($words, 0, min(count($words), 6)));
    } else {
        $rewritten = "Transforming " . strtolower(rtrim($text, '.')) . " into measurable success.";
    }

    echo json_encode(['success' => true, 'rewritten_text' => $rewritten, 'source' => 'smart_engine'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  ACTION: refine  — main AI-chat handler WITH CHANGE DETECTION
// ═══════════════════════════════════════════════════════════════
if ($action === 'refine') {
    $currentHTML = $req['current_html'] ?? '';
    $instruction = strip_tags(trim($req['instruction'] ?? ''));

    if (empty($currentHTML) || empty($instruction)) {
        echo json_encode(['success' => false, 'error' => 'Missing HTML or instruction']);
        exit;
    }

    // ── TASTE LAYER: audit-first edit rules + post-edit pre-flight ──
    if (!function_exists('taste_audit_html')) {
        $tsvc = dirname(__DIR__) . '/includes/TasteSkill.php';
        if (file_exists($tsvc)) require_once $tsvc;
    }

    $geminiError = null;
    $opencodeError = null;

    // ── 0. Try OpenCode Zen first (all free models + FlowCraft edit skills) ──
    try {
        if (!function_exists('opencode_edit_html')) {
            $svc = dirname(__DIR__) . '/includes/OpenCodeService.php';
            if (file_exists($svc)) require_once $svc;
        }
        if (function_exists('opencode_edit_html')) {
            $ocModel = isset($req['model']) && is_string($req['model']) ? trim($req['model']) : null;
            [$ocOk, $ocHtml, $ocUsed, $ocErrors] = opencode_edit_html($currentHTML, $instruction, 'Website', $ocModel ?: null);
            if ($ocOk && trim($ocHtml) !== trim($currentHTML)) {
                echo json_encode([
                    'success' => true,
                    'html' => $ocHtml,
                    'source' => 'opencode',
                    'model' => $ocUsed,
                    'taste_audit' => function_exists('taste_audit_html') ? taste_audit_html($ocHtml) : null,
                    'response_msg' => "✨ OpenCode AI applied your refinement: \"{$instruction}\"."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $opencodeError = implode(' | ', array_slice((array)$ocErrors, 0, 2));
            if ($opencodeError !== '') error_log('[generate.php refine] OpenCode failed: ' . substr($opencodeError, 0, 300));
        }
    } catch (Throwable $e) {
        $opencodeError = $e->getMessage();
        error_log('[generate.php refine] OpenCode exception: ' . $opencodeError);
    }

    // ── 1. Try Gemini next ──
    if (!empty($apiKey) && strlen($apiKey) > 10) {
        $prompt = "You are Google Gemini, an elite full-stack web developer and UI/UX designer. "
                . "The user wants to refine their single-file HTML website with the following instruction.\n\n"
                . "USER INSTRUCTION: {$instruction}\n\n"
                . "CRITICAL REQUIREMENTS:\n"
                . "1. Output the COMPLETE valid single-file HTML document (start with <!DOCTYPE html> and end with </html>).\n"
                . "2. Preserve all existing CSS styling, responsive layout (mobile/tablet/desktop), and colors unless explicitly asked to change.\n"
                . "3. Preserve all working JavaScript event handlers (smooth scroll, contact form submission banner, mobile menu drawer, FAQ accordion, back-to-top button).\n"
                . "4. Seamlessly incorporate the requested updates or sections with modern typography, smooth spacing, and accessible semantic HTML.\n"
                . "5. Return ONLY the raw HTML. Do NOT include markdown fences (no ```html). Output only HTML.\n"
                . "6. TASTE EDIT RULES: audit the document first, change ONLY what was asked, match the existing design system (radius scale, single accent color, type pairing), keep button contrast AA, add no second accent color and no em-dash decoration.\n\n"
                . "CURRENT HTML:\n" . substr($currentHTML, 0, 45000);

        list($ok, $aiText, $code, $errMsg) = callGemini($apiKey, $resolvedModel, $prompt, 32000);

        if (!$ok) {
            $geminiError = $errMsg;
            error_log('[generate.php refine] Gemini failed: ' . $errMsg);
        } else {
            // Strip markdown fences if Gemini added them anyway
            $aiText = preg_replace('/^```(?:html)?\s*/i', '', trim($aiText));
            $aiText = preg_replace('/\s*```$/', '', $aiText);
            $aiText = trim($aiText);

            // Must be valid HTML
            $validHtml = (stripos($aiText, '<!DOCTYPE') !== false || stripos($aiText, '<html') !== false)
                      && stripos($aiText, '</html>') !== false;

            if ($validHtml) {
                // ⚠️ CRITICAL: verify Gemini actually changed something
                if (trim($aiText) === trim($currentHTML)) {
                    // Gemini returned the same HTML — treat as failure so user knows
                    echo json_encode([
                        'success' => false,
                        'error' => 'Gemini returned the same HTML — no changes were applied. Try rephrasing your request more specifically (e.g. "Add a pricing section with 3 plans").',
                        'html' => $currentHTML,
                        'source' => 'gemini_no_change'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'html' => $aiText,
                    'source' => 'gemini',
                    'taste_audit' => function_exists('taste_audit_html') ? taste_audit_html($aiText) : null,
                    'response_msg' => "✨ Gemini applied your refinement: \"{$instruction}\"."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $geminiError = 'Gemini returned malformed HTML (missing closing tags or invalid structure).';
            error_log('[generate.php refine] Malformed HTML from Gemini. First 200 chars: ' . substr($aiText, 0, 200));
        }
    } else {
        $geminiError = 'No Gemini API key configured.';
    }

    // ── 2. Smart engine fallback WITH change tracking ──
    $modified = $currentHTML;
    $lower = strtolower($instruction);
    $changed = false;
    $responseMsg = '';

    // 1. Phone
    if (str_contains($lower, 'phone') || str_contains($lower, 'call') || str_contains($lower, 'number') || str_contains($lower, 'tel')) {
        if (preg_match('/(\+?[\d\s\-\(\)]{8,22})/', $instruction, $m) && !empty($m[1])) {
            $newPhone = trim($m[1]);
            $after = preg_replace('/(\+?[\d\s\-\(\)]{9,22})/', $newPhone, $modified, 4);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated phone number to {$newPhone}."; }
        }
    }

    // 2. Email
    if (str_contains($lower, 'email') || str_contains($lower, 'mail')) {
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $instruction, $m) && !empty($m[1])) {
            $newEmail = trim($m[1]);
            $after = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $newEmail, $modified, 4);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated contact email to {$newEmail}."; }
        }
    }

    // 3. Address
    if (str_contains($lower, 'address') || str_contains($lower, 'location') || str_contains($lower, 'headquarters') || str_contains($lower, 'city')) {
        if (preg_match('/(?:to|in|at)\s+([A-Za-z0-9\s,.-]{4,40})/i', $instruction, $m) && !empty($m[1])) {
            $newAddr = trim($m[1]);
            $after = preg_replace('/(<strong>Headquarters:<\/strong>\s*|<span>Office<\/span>\s*<strong>|<strong>📍 Address:<\/strong>\s*)[^<]+/i', '$1' . $newAddr, $modified, 2);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated address to \"{$newAddr}\"."; }
        }
    }

    // 4. Headline / tagline
    if (str_contains($lower, 'headline') || str_contains($lower, 'tagline') || str_contains($lower, 'title') || str_contains($lower, 'heading')) {
        $newTitle = '';
        if (preg_match('/["“\']([^"”\']+)["”\']/u', $instruction, $m)) $newTitle = trim($m[1]);
        elseif (preg_match('/(?:to|as)\s+(.+)$/i', $instruction, $m)) $newTitle = trim($m[1]);
        if (!empty($newTitle)) {
            $after = preg_replace('/(<header[^>]*>[\s\S]*?<h1[^>]*>)([\s\S]*?)(<\/h1>)/i', '$1' . htmlspecialchars($newTitle) . '$3', $modified, 1);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated hero headline to: \"{$newTitle}\"."; }
        }
    }

    // 5. CTA button text
    if (str_contains($lower, 'button') || str_contains($lower, 'cta')) {
        $newBtn = '';
        if (preg_match('/["“\']([^"”\']+)["”\']/u', $instruction, $m)) $newBtn = trim($m[1]);
        elseif (preg_match('/(?:to|as|saying)\s+(.+)$/i', $instruction, $m)) $newBtn = trim($m[1]);
        if (!empty($newBtn)) {
            $cleanBtn = htmlspecialchars($newBtn);
            $after = preg_replace('/(<a[^>]*class=["\'][^"\']*(?:btn-primary|btn-bold|btn-gold)[^"\']*["\'][^>]*>)([\s\S]*?)(<\/a>)/i', '$1' . $cleanBtn . ' &rarr;$3', $modified, 1);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated primary button text to: \"{$cleanBtn}\"."; }
        }
    }

    // 6. Testimonials / reviews
    if (str_contains($lower, 'testimonial') || str_contains($lower, 'review') || str_contains($lower, 'feedback') || str_contains($lower, 'social proof') || str_contains($lower, 'rating')) {
        if (!str_contains($modified, 'id="testimonials"')) {
            $testiHtml = '
            <section class="section" id="testimonials" style="padding:5rem 1.5rem;background:rgba(99,102,241,0.03);border-top:1px solid rgba(226,232,240,0.4);border-bottom:1px solid rgba(226,232,240,0.4)">
              <div style="text-align:center;max-width:700px;margin:0 auto 3rem">
                <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;color:var(--primary);letter-spacing:0.1em;margin-bottom:0.4rem">Client Feedback</div>
                <h2 style="font-size:2.4rem;font-weight:800;letter-spacing:-0.02em">Trusted by Visionary Leaders</h2>
                <p style="color:#64748b;margin-top:0.5rem">See how our solutions deliver measurable excellence and outstanding growth for our partners.</p>
              </div>
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;max-width:1150px;margin:0 auto">
                <div style="background:rgba(255,255,255,0.85);backdrop-filter:blur(10px);border:1.5px solid rgba(226,232,240,0.8);border-radius:18px;padding:2rem;box-shadow:0 10px 30px rgba(0,0,0,0.04);display:flex;flex-direction:column;justify-content:space-between">
                  <div><div style="color:#f59e0b;font-size:1.1rem;margin-bottom:1rem">★★★★★</div>
                  <p style="color:#334155;line-height:1.6;font-size:0.95rem;font-style:italic">"The velocity and design quality exceeded our highest expectations. Our conversions jumped 48% within the first month of launching."</p></div>
                  <div style="display:flex;align-items:center;gap:0.75rem;margin-top:1.5rem;padding-top:1rem;border-top:1px solid #e2e8f0">
                    <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem">SJ</div>
                    <div><div style="font-weight:700;color:#0f172a;font-size:0.92rem">Sarah Jenkins</div><div style="color:#64748b;font-size:0.8rem">VP of Marketing, NexaCorp</div></div>
                  </div>
                </div>
                <div style="background:rgba(255,255,255,0.85);backdrop-filter:blur(10px);border:1.5px solid var(--primary);border-radius:18px;padding:2rem;box-shadow:0 15px 35px rgba(99,102,241,0.12);display:flex;flex-direction:column;justify-content:space-between;position:relative">
                  <span style="position:absolute;top:-12px;right:20px;background:var(--primary);color:#fff;font-size:0.72rem;font-weight:700;padding:0.25rem 0.75rem;border-radius:999px">FEATURED REVIEW</span>
                  <div><div style="color:#f59e0b;font-size:1.1rem;margin-bottom:1rem">★★★★★</div>
                  <p style="color:#334155;line-height:1.6;font-size:0.95rem;font-style:italic">"Working together was seamless. They understood our brand immediately and engineered a platform that sets us years ahead of competitors."</p></div>
                  <div style="display:flex;align-items:center;gap:0.75rem;margin-top:1.5rem;padding-top:1rem;border-top:1px solid #e2e8f0">
                    <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#059669,#10b981);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem">DC</div>
                    <div><div style="font-weight:700;color:#0f172a;font-size:0.92rem">David Chen</div><div style="color:#64748b;font-size:0.8rem">Co-Founder, Strata Global</div></div>
                  </div>
                </div>
                <div style="background:rgba(255,255,255,0.85);backdrop-filter:blur(10px);border:1.5px solid rgba(226,232,240,0.8);border-radius:18px;padding:2rem;box-shadow:0 10px 30px rgba(0,0,0,0.04);display:flex;flex-direction:column;justify-content:space-between">
                  <div><div style="color:#f59e0b;font-size:1.1rem;margin-bottom:1rem">★★★★★</div>
                  <p style="color:#334155;line-height:1.6;font-size:0.95rem;font-style:italic">"The attention to mobile responsiveness and speed optimization is unmatched. Truly world-class digital craftsmanship."</p></div>
                  <div style="display:flex;align-items:center;gap:0.75rem;margin-top:1.5rem;padding-top:1rem;border-top:1px solid #e2e8f0">
                    <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#06b6d4);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem">ER</div>
                    <div><div style="font-weight:700;color:#0f172a;font-size:0.92rem">Elena Rostova</div><div style="color:#64748b;font-size:0.8rem">Managing Director, Lumina Labs</div></div>
                  </div>
                </div>
              </div>
            </section>';
            $after = str_replace('<section class="section" id="contact">', $testiHtml . "\n" . '<section class="section" id="contact">', $modified);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Added a stunning 5-star Customer Testimonials & Reviews section!"; }
        }
    }

    // 7. Team / leadership
    if (str_contains($lower, 'team') || str_contains($lower, 'leadership') || str_contains($lower, 'founder') || str_contains($lower, 'staff')) {
        if (!str_contains($modified, 'id="team"')) {
            $teamHtml = '
            <section class="section" id="team" style="padding:5rem 1.5rem;text-align:center">
              <div style="max-width:700px;margin:0 auto 3rem">
                <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;color:var(--primary);letter-spacing:0.1em;margin-bottom:0.4rem">The Experts</div>
                <h2 style="font-size:2.4rem;font-weight:800;letter-spacing:-0.02em">Meet Our Leadership Team</h2>
                <p style="color:#64748b;margin-top:0.5rem">Passionate industry specialists committed to delivering exceptional digital results.</p>
              </div>
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem;max-width:1050px;margin:0 auto">
                <div style="background:rgba(255,255,255,0.7);border:1px solid #e2e8f0;border-radius:18px;padding:2rem 1.5rem;text-align:center">
                  <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800">AT</div>
                  <h3 style="font-size:1.15rem;font-weight:700;color:#0f172a">Alexander Thorne</h3>
                  <div style="color:var(--primary);font-size:0.85rem;font-weight:600;margin-top:0.2rem">CEO &amp; Head of Strategy</div>
                  <p style="font-size:0.85rem;color:#64748b;margin-top:0.75rem;line-height:1.5">12+ years directing high-impact initiatives for global enterprise clients.</p>
                </div>
                <div style="background:rgba(255,255,255,0.7);border:1px solid #e2e8f0;border-radius:18px;padding:2rem 1.5rem;text-align:center">
                  <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#059669,#10b981);color:#fff;font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800">MV</div>
                  <h3 style="font-size:1.15rem;font-weight:700;color:#0f172a">Maya Vance</h3>
                  <div style="color:var(--primary);font-size:0.85rem;font-weight:600;margin-top:0.2rem">Creative Director</div>
                  <p style="font-size:0.85rem;color:#64748b;margin-top:0.75rem;line-height:1.5">Award-winning designer crafting intuitive, human-centered experiences.</p>
                </div>
                <div style="background:rgba(255,255,255,0.7);border:1px solid #e2e8f0;border-radius:18px;padding:2rem 1.5rem;text-align:center">
                  <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#06b6d4);color:#fff;font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800">LK</div>
                  <h3 style="font-size:1.15rem;font-weight:700;color:#0f172a">Liam Kendrick</h3>
                  <div style="color:var(--primary);font-size:0.85rem;font-weight:600;margin-top:0.2rem">Lead Solutions Architect</div>
                  <p style="font-size:0.85rem;color:#64748b;margin-top:0.75rem;line-height:1.5">Full-stack systems specialist ensuring military-grade stability and speed.</p>
                </div>
              </div>
            </section>';
            $after = str_replace('<section class="section" id="contact">', $teamHtml . "\n" . '<section class="section" id="contact">', $modified);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Added Meet Our Leadership Team section!"; }
        }
    }

    // 8. WhatsApp
    if (str_contains($lower, 'whatsapp') || str_contains($lower, 'chat button')) {
        if (!str_contains($modified, 'whatsapp-floating-btn')) {
            $waHtml = '<a href="https://wa.me/15551234567" target="_blank" id="whatsapp-floating-btn" style="position:fixed;bottom:2rem;left:2rem;z-index:99;background:#25D366;color:#fff;padding:0.75rem 1.25rem;border-radius:999px;font-weight:700;font-size:0.9rem;box-shadow:0 10px 25px rgba(37,211,102,0.4);display:flex;align-items:center;gap:0.5rem;text-decoration:none"><span style="font-size:1.2rem">💬</span> Chat on WhatsApp</a>';
            $after = str_replace('</body>', $waHtml . "\n</body>", $modified);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Added floating WhatsApp chat button!"; }
        }
    }

    // 9. FAQ
    if (str_contains($lower, 'faq') || str_contains($lower, 'questions') || str_contains($lower, 'accordion')) {
        if (!str_contains($modified, 'id="faq"')) {
            $faqHtml = '
            <section class="section" id="faq" style="padding:5.5rem 1.5rem;max-width:950px;margin:0 auto">
              <div style="text-align:center;margin-bottom:3rem">
                <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;color:var(--primary);letter-spacing:0.1em;margin-bottom:0.4rem">Common Inquiries</div>
                <h2 style="font-size:2.4rem;font-weight:800;letter-spacing:-0.02em">Frequently Asked Questions</h2>
              </div>
              <div style="display:flex;flex-direction:column;gap:1rem">
                <div style="background:rgba(255,255,255,0.04);border:1.5px solid #cbd5e1;border-radius:14px;padding:1.25rem 1.75rem;cursor:pointer" onclick="toggleFaq(this)">
                  <div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:1.05rem"><span>What is the typical turnaround timeline for a website project?</span><span class="faq-icon" style="font-size:1.4rem;color:var(--primary)">+</span></div>
                  <div style="display:none;margin-top:0.85rem;color:#64748b;font-size:0.95rem;line-height:1.6">Our average turnaround is between 2 to 4 weeks, with expedited 7-day launch schedules available for fast-track initiatives.</div>
                </div>
                <div style="background:rgba(255,255,255,0.04);border:1.5px solid #cbd5e1;border-radius:14px;padding:1.25rem 1.75rem;cursor:pointer" onclick="toggleFaq(this)">
                  <div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:1.05rem"><span>Is full responsive mobile optimization included?</span><span class="faq-icon" style="font-size:1.4rem;color:var(--primary)">+</span></div>
                  <div style="display:none;margin-top:0.85rem;color:#64748b;font-size:0.95rem;line-height:1.6">Yes, 100% of our builds are mobile-first, ensuring high speeds, crisp accessibility, and seamless journeys on phones, tablets, and desktops.</div>
                </div>
                <div style="background:rgba(255,255,255,0.04);border:1.5px solid #cbd5e1;border-radius:14px;padding:1.25rem 1.75rem;cursor:pointer" onclick="toggleFaq(this)">
                  <div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:1.05rem"><span>How does payment and milestone scheduling work?</span><span class="faq-icon" style="font-size:1.4rem;color:var(--primary)">+</span></div>
                  <div style="display:none;margin-top:0.85rem;color:#64748b;font-size:0.95rem;line-height:1.6">We provide flexible terms, including 50% down / 50% on completion, or monthly subscription plans tailored to your budget.</div>
                </div>
              </div>
            </section>';
            $after = str_replace('<section class="section" id="contact">', $faqHtml . "\n" . '<section class="section" id="contact">', $modified);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Added interactive FAQ accordion!"; }
        }
    }

    // 10. Client logos
    if (str_contains($lower, 'logo') || str_contains($lower, 'partner') || str_contains($lower, 'trusted by')) {
        if (!str_contains($modified, 'id="partners-strip"')) {
            $partnersHtml = '<div id="partners-strip" style="padding:2.5rem 1.5rem;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;text-align:center"><div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;color:#94a3b8;letter-spacing:0.1em;margin-bottom:1.25rem">Trusted by industry leaders worldwide</div><div style="display:flex;justify-content:center;gap:3rem;flex-wrap:wrap;align-items:center;opacity:0.75;font-weight:800;font-size:1.15rem;color:#475569"><span>✦ ACME CORP</span><span>⚡ VELOCITY AI</span><span>◈ NEXUS LABS</span><span>❖ ORBITAL MEDIA</span><span>★ STRATA GLOBAL</span></div></div>';
            $after = str_replace('</header>', '</header>' . "\n" . $partnersHtml, $modified);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Added client brand logos strip!"; }
        }
    }

    // 11. Pricing
    if (str_contains($lower, 'pricing') || str_contains($lower, 'plans') || str_contains($lower, 'tier') || str_contains($lower, 'packages')) {
        if (!str_contains(strtolower($modified), 'id="pricing"')) {
            $pricingHtml = '
            <section class="section" id="pricing" style="padding:5.5rem 1.5rem;text-align:center">
              <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;color:var(--primary);letter-spacing:0.1em;margin-bottom:0.4rem">Transparent Investment</div>
              <h2 style="font-size:2.4rem;font-weight:800;margin-bottom:0.6rem;letter-spacing:-0.02em">Flexible Pricing Plans</h2>
              <p style="color:#64748b;margin-bottom:3.5rem;max-width:550px;margin-left:auto;margin-right:auto">Choose the tier that fits your growth ambitions.</p>
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;max-width:1050px;margin:0 auto">
                <div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2.5rem 2rem;color:#0f172a">
                  <h3 style="font-size:1.2rem;font-weight:700">Starter</h3>
                  <div style="font-size:2.6rem;font-weight:800;margin:1rem 0;color:var(--dark)">$499</div>
                  <p style="color:#64748b;font-size:0.9rem;margin-bottom:1.5rem">Essential digital presence</p>
                  <a href="#contact" class="btn-secondary" style="display:block;text-align:center;padding:0.75rem;border-radius:999px">Choose Starter</a>
                </div>
                <div style="background:#fff;border:2.5px solid var(--primary);border-radius:18px;padding:2.5rem 2rem;box-shadow:0 15px 40px rgba(99,102,241,0.18);color:#0f172a;position:relative">
                  <span style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--primary);color:#fff;font-size:0.75rem;font-weight:700;padding:0.25rem 0.85rem;border-radius:999px">MOST POPULAR</span>
                  <h3 style="font-size:1.2rem;font-weight:700">Growth Scale</h3>
                  <div style="font-size:2.6rem;font-weight:800;margin:1rem 0;color:var(--primary)">$999</div>
                  <p style="color:#64748b;font-size:0.9rem;margin-bottom:1.5rem">Complete custom experience</p>
                  <a href="#contact" class="btn-primary" style="display:block;text-align:center;padding:0.75rem;border-radius:999px">Choose Growth &rarr;</a>
                </div>
                <div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;padding:2.5rem 2rem;color:#0f172a">
                  <h3 style="font-size:1.2rem;font-weight:700">Enterprise</h3>
                  <div style="font-size:2.6rem;font-weight:800;margin:1rem 0;color:var(--dark)">$1,899</div>
                  <p style="color:#64748b;font-size:0.9rem;margin-bottom:1.5rem">Full custom engineering</p>
                  <a href="#contact" class="btn-secondary" style="display:block;text-align:center;padding:0.75rem;border-radius:999px">Choose Enterprise</a>
                </div>
              </div>
            </section>';
            $after = str_replace('<section class="section" id="contact">', $pricingHtml . "\n" . '<section class="section" id="contact">', $modified);
            if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Added 3-tier pricing table!"; }
        }
    }

    // 12. Dark / light mode
    if (str_contains($lower, 'dark') || str_contains($lower, 'night') || str_contains($lower, 'black')) {
        $after = str_replace(['#ffffff', '#fafbfe', '#f8fafc', 'background: #ffffff;', 'background: #fff;'], ['#090d16', '#090d16', '#0f172a', 'background: #090d16;', 'background: #090d16;'], $modified);
        $after = str_replace(['color: #1e293b;', 'color: #0f172a;'], 'color: #f1f5f9;', $after);
        if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Switched entire website to executive dark mode!"; }
    } elseif (str_contains($lower, 'light') || str_contains($lower, 'clean mode') || str_contains($lower, 'white')) {
        $after = str_replace(['#090d16', '#0f172a', '#111622'], ['#ffffff', '#f8fafc', '#ffffff'], $modified);
        $after = str_replace(['color: #f1f5f9;', 'color: #f8fafc;'], 'color: #0f172a;', $after);
        if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Switched theme to clean light mode!"; }
    }

    // 13. Color palette changes
    if (str_contains($lower, 'blue') || str_contains($lower, 'cyan') || str_contains($lower, 'ocean')) {
        $after = str_replace(['#6366f1', '#a855f7'], ['#2563eb', '#06b6d4'], $modified);
        $after = preg_replace('/--primary:\s*[^;]+;/', '--primary: #2563eb;', $after);
        if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated color palette to Ocean Blue!"; }
    } elseif (str_contains($lower, 'green') || str_contains($lower, 'emerald')) {
        $after = str_replace(['#6366f1', '#a855f7', '#2563eb'], ['#059669', '#10b981', '#10b981'], $modified);
        $after = preg_replace('/--primary:\s*[^;]+;/', '--primary: #059669;', $after);
        if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated color palette to Emerald Green!"; }
    } elseif (str_contains($lower, 'red') || str_contains($lower, 'crimson') || str_contains($lower, 'orange')) {
        $after = str_replace(['#6366f1', '#a855f7'], ['#dc2626', '#f97316'], $modified);
        $after = preg_replace('/--primary:\s*[^;]+;/', '--primary: #dc2626;', $after);
        if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated color palette to Crimson Red!"; }
    } elseif (str_contains($lower, 'gold') || str_contains($lower, 'amber') || str_contains($lower, 'luxury')) {
        $after = str_replace(['#6366f1', '#a855f7'], ['#d97706', '#f59e0b'], $modified);
        $after = preg_replace('/--primary:\s*[^;]+;/', '--primary: #d97706;', $after);
        if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated color palette to Luxury Gold!"; }
    } elseif (str_contains($lower, 'purple') || str_contains($lower, 'violet') || str_contains($lower, 'indigo')) {
        $after = str_replace(['#2563eb', '#06b6d4', '#059669'], ['#6366f1', '#a855f7', '#6366f1'], $modified);
        $after = preg_replace('/--primary:\s*[^;]+;/', '--primary: #6366f1;', $after);
        if ($after !== $modified) { $modified = $after; $changed = true; $responseMsg = "Updated color palette to Royal Indigo!"; }
    }

    // ═══ CRITICAL FIX: If nothing changed, tell the truth ═══
    if (!$changed) {
        $hint = '';
        if ($opencodeError) {
            $hint .= "OpenCode AI: {$opencodeError}. ";
        }
        if ($geminiError) {
            $hint .= "Gemini failed: {$geminiError}. ";
        }
        echo json_encode([
            'success' => false,
            'error' => $hint . "I couldn't figure out how to apply \"{$instruction}\" to this website. Try a specific instruction like:\n" .
                "• \"Add a pricing table with 3 tiers\"\n" .
                "• \"Add 5-star customer reviews\"\n" .
                "• \"Add an FAQ section\"\n" .
                "• \"Change phone to +1 555 123 4567\"\n" .
                "• \"Switch to dark mode\" or \"Change color to blue\"",
            'html' => $currentHTML,
            'source' => 'no_change'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'html' => $modified,
        'source' => 'smart_engine',
        'response_msg' => $responseMsg
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);