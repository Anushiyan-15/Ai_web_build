<?php
require_once __DIR__ . '/config.php';
$page_title = 'Home — AI Website Builder';
$page_desc  = 'Build stunning, professional websites in seconds with AI. Describe your vision, watch it come alive.';
require_once __DIR__ . '/includes/nav.php';
?>
<style>
/* ── HOME PAGE STYLES ── */

/* Hero */
.hero{
  min-height:92vh;
  background:linear-gradient(160deg,#f0f4ff 0%,#faf5ff 50%,#ecfdf5 100%);
  display:flex;align-items:center;
  position:relative;overflow:hidden;
  padding:5rem 0;
}
.hero::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 80% 60% at 60% 30%, rgba(99,102,241,.10) 0%, transparent 70%),
             radial-gradient(ellipse 60% 50% at 20% 70%, rgba(168,85,247,.08) 0%, transparent 60%);
  pointer-events:none;
}
.hero-grid{
  display:grid;grid-template-columns:1fr 1fr;
  gap:4rem;align-items:center;
}
@media(max-width:840px){.hero-grid{grid-template-columns:1fr;text-align:center}}
.hero-content{}
.hero h1{margin-bottom:1.25rem}
.hero h1 span{display:block}
.hero .lead{margin-bottom:2rem}
@media(max-width:840px){.hero .lead{margin:0 auto 2rem}}
.hero-ctas{display:flex;gap:.9rem;flex-wrap:wrap}
@media(max-width:840px){.hero-ctas{justify-content:center}}
.hero-visual{
  position:relative;
}
.browser-mock{
  background:#1e1b4b;border-radius:14px;
  box-shadow:0 32px 80px rgba(99,102,241,.3),0 0 0 1px rgba(255,255,255,.1);
  overflow:hidden;
}
.browser-bar{
  background:#2d2a5e;padding:.7rem 1rem;
  display:flex;align-items:center;gap:.75rem;
}
.browser-dots{display:flex;gap:.4rem}
.browser-dots span{width:10px;height:10px;border-radius:50%}
.browser-dots span:nth-child(1){background:#ef4444}
.browser-dots span:nth-child(2){background:#f59e0b}
.browser-dots span:nth-child(3){background:#10b981}
.browser-url{
  flex:1;background:#1e1b4b;border-radius:6px;
  padding:.3rem .75rem;font-size:.7rem;font-family:'Fira Code',monospace;
  color:var(--n400);
}
.browser-content{
  padding:1.25rem;
  min-height:340px;
  background:linear-gradient(180deg,#0f172a,#1e1b4b);
  position:relative;overflow:hidden;
}
/* animated code lines */
.code-line{
  height:8px;border-radius:4px;margin-bottom:.6rem;
  background:linear-gradient(90deg,rgba(99,102,241,.6),rgba(168,85,247,.4));
  animation:shimmer 2s ease-in-out infinite alternate;
}
.code-line:nth-child(2){width:70%;animation-delay:.1s}
.code-line:nth-child(3){width:85%;animation-delay:.2s}
.code-line:nth-child(4){width:50%;animation-delay:.3s}
.code-line:nth-child(5){width:90%;animation-delay:.4s}
.code-line:nth-child(6){width:60%;animation-delay:.5s}
@keyframes shimmer{0%{opacity:.5}100%{opacity:1}}
.preview-mini{
  margin-top:1rem;
  background:linear-gradient(135deg,var(--p),#a855f7);
  border-radius:8px;padding:1rem;
  display:flex;flex-direction:column;gap:.5rem;
}
.preview-mini .mini-bar{height:6px;background:rgba(255,255,255,.3);border-radius:3px}
.preview-mini .mini-bar:nth-child(2){width:60%}
.preview-mini .mini-bar:nth-child(3){width:40%}
.floating-badge{
  position:absolute;
  background:#fff;border-radius:10px;padding:.6rem .9rem;
  box-shadow:0 8px 24px rgba(0,0,0,.12);
  font-size:.75rem;font-weight:600;color:var(--n800);
  display:flex;align-items:center;gap:.4rem;
  animation:float 3s ease-in-out infinite;
}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.badge-1{top:-16px;right:30px;animation-delay:.5s}
.badge-2{bottom:20px;left:-20px;animation-delay:1.2s}
.badge-3{bottom:60px;right:-20px;animation-delay:.8s}

/* Typewriter */
.typewriter-wrap{display:inline-block;min-width:280px}
#typewriter{border-right:3px solid var(--p);padding-right:2px;animation:blink .75s step-end infinite}
@keyframes blink{0%,100%{border-color:var(--p)}50%{border-color:transparent}}

/* Stats strip */
.stats-strip{
  padding:2.5rem 0;
  background:#fff;border-top:1px solid var(--n200);border-bottom:1px solid var(--n200);
}
.stats-grid{
  display:flex;justify-content:center;gap:4rem;flex-wrap:wrap;
}
.stat-item{text-align:center}
.stat-num{font-size:2.2rem;font-weight:900;letter-spacing:-.03em;color:var(--p)}
.stat-lbl{font-size:.85rem;color:var(--n500);font-weight:500;margin-top:.2rem}

/* How it works */
.steps-wrap{
  display:grid;grid-template-columns:repeat(4,1fr);gap:2rem;
  position:relative;
}
@media(max-width:860px){.steps-wrap{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.steps-wrap{grid-template-columns:1fr}}
.steps-wrap::before{
  content:'';position:absolute;top:36px;left:8%;right:8%;
  height:1px;background:linear-gradient(90deg,var(--p),var(--a));
  z-index:0;
}
@media(max-width:860px){.steps-wrap::before{display:none}}
.step-item{text-align:center;position:relative;z-index:1}
.step-icon{
  width:72px;height:72px;border-radius:50%;
  background:linear-gradient(135deg,var(--p),#a855f7);
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;margin:0 auto 1rem;
  box-shadow:0 8px 24px rgba(99,102,241,.3);
  border:4px solid #fff;
}
.step-num{
  position:absolute;top:-4px;right:calc(50% - 46px);
  width:22px;height:22px;background:var(--a);
  color:#fff;font-size:.7rem;font-weight:800;
  border-radius:50%;display:flex;align-items:center;justify-content:center;
}
.step-item h3{font-size:1rem;font-weight:700;margin-bottom:.4rem;color:var(--n900)}
.step-item p{font-size:.85rem;color:var(--n500)}

/* Features */
.feature-card{
  padding:1.75rem;border-radius:16px;
  border:1px solid var(--n200);background:#fff;
  transition:transform var(--t),box-shadow var(--t);
}
.feature-card:hover{transform:translateY(-4px);box-shadow:0 16px 48px rgba(99,102,241,.14)}
.feature-icon{
  width:50px;height:50px;border-radius:12px;
  background:var(--pl);color:var(--p);
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;margin-bottom:1rem;
}
.feature-card h3{font-size:1rem;font-weight:700;margin-bottom:.5rem;color:var(--n900)}
.feature-card p{font-size:.875rem;color:var(--n500);line-height:1.65}

/* Examples */
.examples-grid{
  display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;
}
@media(max-width:760px){.examples-grid{grid-template-columns:1fr}}
.example-card{
  border-radius:14px;overflow:hidden;
  border:1px solid var(--n200);
  box-shadow:0 4px 16px rgba(0,0,0,.06);
  transition:transform var(--t),box-shadow var(--t);
}
.example-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(99,102,241,.18)}
.example-thumb{
  height:180px;
  background:linear-gradient(135deg,var(--bg1),var(--bg2));
  display:flex;align-items:center;justify-content:center;
  font-size:2rem;position:relative;overflow:hidden;
}
.example-info{padding:1rem 1.25rem;background:#fff}
.example-info h4{font-size:.95rem;font-weight:700;color:var(--n900);margin-bottom:.2rem}
.example-info p{font-size:.8rem;color:var(--n400)}

/* Testimonials */
.testi-card{
  background:#fff;border-radius:16px;border:1px solid var(--n200);
  padding:1.5rem;box-shadow:0 4px 16px rgba(0,0,0,.06);
}
.testi-stars{color:#f59e0b;font-size:.9rem;margin-bottom:.75rem}
.testi-text{font-size:.9rem;color:var(--n600);line-height:1.7;margin-bottom:1rem;font-style:italic}
.testi-author{display:flex;align-items:center;gap:.75rem}
.testi-avatar{
  width:40px;height:40px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;font-weight:700;color:#fff;
}
.testi-name{font-size:.875rem;font-weight:700;color:var(--n900)}
.testi-role{font-size:.78rem;color:var(--n400)}

/* CTA section */
.cta-section{
  background:linear-gradient(135deg,var(--p),#7c3aed,#a855f7);
  border-radius:24px;padding:5rem 3rem;text-align:center;
  position:relative;overflow:hidden;
  margin:4rem 0;
}
.cta-section::before{
  content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.cta-section h2{font-size:clamp(1.6rem,4vw,2.8rem);font-weight:900;color:#fff;margin-bottom:1rem;position:relative;z-index:1}
.cta-section p{color:rgba(255,255,255,.8);font-size:1.05rem;margin-bottom:2rem;position:relative;z-index:1}
.cta-section .btn-white{
  background:#fff;color:var(--p);font-size:1rem;padding:.9rem 2.2rem;
  box-shadow:0 8px 24px rgba(0,0,0,.15);position:relative;z-index:1;
  border-radius:12px;font-weight:700;
}
.cta-section .btn-white:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,.22)}
</style>

<!-- ══ HERO ══════════════════════════════════════════════ -->
<section class="hero">
  <div class="container">
    <div class="hero-grid">
      <div class="hero-content">
        <div class="badge">✦ Powered by Google Gemini AI — Free to use</div>
        <h1 class="heading-xl">
          <span>Build websites</span>
          <span class="gradient-text typewriter-wrap"><span id="typewriter">instantly with AI</span></span>
        </h1>
        <p class="lead">
          Describe your dream website. Our AI generates production-ready code in seconds — then customize it live, right in your browser.
        </p>
        <div class="hero-ctas">
          <a href="<?= ($customerUser ? SITE_URL . '/builder.php' : SITE_URL . '/customer-portal.php?view=signup') ?>" class="btn btn-primary btn-lg">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Start Building Free
          </a>
          <a href="<?= SITE_URL ?>/client-intake.php" class="btn btn-ghost btn-lg">
            📋 Get a Custom Quote
          </a>
        </div>
        <?php if (!empty($customerUser)): ?>
        <div class="hero-ctas" style="margin-top:.9rem">
          <a href="<?= SITE_URL ?>/builder.php" class="btn btn-ghost btn-lg">✦ Open AI Builder</a>
          <a href="<?= SITE_URL ?>/customer-portal.php" class="btn btn-ghost btn-lg">📁 My Projects</a>
        </div>
        <?php else: ?>
        <div class="hero-ctas" style="margin-top:.9rem">
          <a href="<?= SITE_URL ?>/customer-portal.php" class="btn btn-ghost btn-lg">Sign In</a>
          <a href="<?= SITE_URL ?>/customer-portal.php?view=signup" class="btn btn-ghost btn-lg">Create Free Account</a>
        </div>
        <?php endif; ?>
        <p style="margin-top:1rem;font-size:.8rem;color:var(--n400)">
          ✓ No coding needed &nbsp;·&nbsp; ✓ Free API &nbsp;·&nbsp; ✓ Export your code
        </p>
      </div>
      <div class="hero-visual">
        <div class="browser-mock">
          <div class="browser-bar">
            <div class="browser-dots">
              <span></span><span></span><span></span>
            </div>
            <div class="browser-url">https://my-ai-website.com</div>
          </div>
          <div class="browser-content">
            <div class="code-line" style="width:90%"></div>
            <div class="code-line" style="width:70%"></div>
            <div class="code-line" style="width:85%"></div>
            <div class="code-line" style="width:50%"></div>
            <div class="code-line" style="width:75%"></div>
            <div class="code-line" style="width:60%"></div>
            <div class="preview-mini">
              <div class="mini-bar" style="width:80%"></div>
              <div class="mini-bar"></div>
              <div class="mini-bar"></div>
            </div>
          </div>
        </div>
        <div class="floating-badge badge-1">✅ Website generated!</div>
        <div class="floating-badge badge-2">🎨 Editing live…</div>
        <div class="floating-badge badge-3">⚡ 8 sec</div>
      </div>
    </div>
  </div>
</section>

<!-- ══ STATS STRIP ══════════════════════════════════════ -->
<section class="stats-strip">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-item"><div class="stat-num gradient-text">1,500+</div><div class="stat-lbl">Free API calls/day</div></div>
      <div class="stat-item"><div class="stat-num gradient-text">&lt;10s</div><div class="stat-lbl">Average generation time</div></div>
      <div class="stat-item"><div class="stat-num gradient-text">100%</div><div class="stat-lbl">Export-ready HTML code</div></div>
      <div class="stat-item"><div class="stat-num gradient-text">∞</div><div class="stat-lbl">Design possibilities</div></div>
    </div>
  </div>
</section>

<!-- ══ HOW IT WORKS ══════════════════════════════════════ -->
<section class="section" style="background:#fff">
  <div class="container">
    <div class="text-center" style="margin-bottom:3.5rem">
      <div class="badge">How It Works</div>
      <h2 class="heading-lg">From idea to website in 4 steps</h2>
      <p class="lead center" style="margin-top:.75rem">No design skills. No coding. Just describe what you want.</p>
    </div>
    <div class="steps-wrap">
      <?php
      $steps = [
        ['📝','Fill the Wizard','Tell us your business name, industry, style preferences, and features you need.'],
        ['🤖','AI Generates','Gemini AI writes the complete HTML, CSS, and JavaScript for your website.'],
        ['✏️','Edit Live','See a real-time preview and edit the code in the built-in editor.'],
        ['⬇️','Export & Launch','Download the HTML file and host it anywhere — or paste it to your server.'],
      ];
      foreach($steps as $i=>[$icon,$title,$desc]): ?>
      <div class="step-item">
        <div class="step-icon" style="font-size:1.6rem">
          <?= $icon ?>
          <div class="step-num"><?= $i+1 ?></div>
        </div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ FEATURES ══════════════════════════════════════ -->
<section class="section" style="background:var(--n50)">
  <div class="container">
    <div class="text-center" style="margin-bottom:3.5rem">
      <div class="badge">Features</div>
      <h2 class="heading-lg">Everything you need to build fast</h2>
    </div>
    <div class="grid-3">
      <?php
      $features = [
        ['🤖','AI Website Generation','Describe your site in plain English. Gemini AI generates production-ready HTML, CSS & JS instantly.'],
        ['👁️','Live Preview','See your website update in real time as you edit. Split-pane editor + preview — just like Bolt.new.'],
        ['✏️','Code Editor','Full syntax-highlighted editor powered by CodeMirror. Edit any part of the generated code directly.'],
        ['💬','AI Refinement Chat','Not happy with a section? Type "make the hero taller" and AI updates just that part.'],
        ['📱','Always Responsive','Every generated website is mobile-first and works perfectly on all screen sizes.'],
        ['⬇️','One-Click Export','Download your complete website as a single HTML file — ready to deploy anywhere.'],
        ['🎨','Style Wizard','Choose from 6 design styles, color presets, and layout options before generating.'],
        ['🆓','Free Gemini API','Uses Google Gemini 2.0 Flash — 1,500 free requests per day. No credit card needed.'],
        ['📋','Client Intake Form','Collect detailed project requirements from clients before starting any design work.'],
      ];
      foreach($features as [$icon,$title,$desc]): ?>
      <div class="feature-card">
        <div class="feature-icon"><?= $icon ?></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ EXAMPLE WEBSITES ══════════════════════════════════════ -->
<section class="section" style="background:#fff">
  <div class="container">
    <div class="text-center" style="margin-bottom:3rem">
      <div class="badge">Examples</div>
      <h2 class="heading-lg">See what AI can build for you</h2>
      <p class="lead center" style="margin-top:.75rem">Real websites generated by our AI in under 10 seconds.</p>
    </div>
    <div class="examples-grid">
      <?php
      $examples = [
        ['🍕','Restaurant & Cafe','--bg1:#ef4444;--bg2:#dc2626','Modern food business site with menu, reservations & gallery'],
        ['💼','Agency Portfolio','--bg1:#6366f1;--bg2:#4338ca','Creative agency showcase with case studies and team section'],
        ['🛒','E-Commerce Store','--bg1:#10b981;--bg2:#059669','Product listing with cart, search & checkout flow'],
        ['🏥','Medical Clinic','--bg1:#06b6d4;--bg2:#0891b2','Healthcare provider with booking, services & team bios'],
        ['🏋️','Fitness Studio','--bg1:#f59e0b;--bg2:#d97706','Gym website with classes, trainers & membership pricing'],
        ['🏠','Real Estate','--bg1:#8b5cf6;--bg2:#7c3aed','Property listings with search, maps & agent profiles'],
      ];
      foreach($examples as [$emoji,$name,$style,$desc]): ?>
      <div class="example-card">
        <div class="example-thumb" style="<?= $style ?>">
          <span style="font-size:3.5rem"><?= $emoji ?></span>
        </div>
        <div class="example-info">
          <h4><?= $name ?></h4>
          <p><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:2.5rem">
      <a href="<?= ($customerUser ? SITE_URL . '/builder.php' : SITE_URL . '/customer-portal.php?view=signup') ?>" class="btn btn-primary btn-lg">
        ✦ Generate Your Website Now
      </a>
    </div>
  </div>
</section>

<!-- ══ TESTIMONIALS ══════════════════════════════════════ -->
<section class="section" style="background:var(--n50)">
  <div class="container">
    <div class="text-center" style="margin-bottom:3rem">
      <div class="badge">Testimonials</div>
      <h2 class="heading-lg">Loved by businesses everywhere</h2>
    </div>
    <div class="grid-3">
      <?php
      $testis = [
        ['⭐⭐⭐⭐⭐','"Generated my entire restaurant website in under 2 minutes. The code was clean and I only had to change the phone number!"','Sarah M.','Restaurant Owner','#ef4444'],
        ['⭐⭐⭐⭐⭐','"I used this to build a landing page for my startup. The AI even added animations I didn\'t ask for. Absolutely brilliant."','James K.','SaaS Founder','#6366f1'],
        ['⭐⭐⭐⭐⭐','"My client needed a portfolio site urgently. I used WebCraft AI to generate the base and customized it live. Saved me 8 hours."','Priya R.','Freelance Designer','#10b981'],
      ];
      foreach($testis as [$stars,$text,$name,$role,$color]): ?>
      <div class="testi-card">
        <div class="testi-stars"><?= $stars ?></div>
        <p class="testi-text"><?= $text ?></p>
        <div class="testi-author">
          <div class="testi-avatar" style="background:<?= $color ?>"><?= $name[0] ?></div>
          <div>
            <div class="testi-name"><?= $name ?></div>
            <div class="testi-role"><?= $role ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ CTA ══════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="cta-section">
      <h2>Ready to build your website?</h2>
      <p>Free, fast, and no coding required. Get started in 30 seconds.</p>
      <a href="<?= ($customerUser ? SITE_URL . '/builder.php' : SITE_URL . '/customer-portal.php?view=signup') ?>" class="btn btn-white">
        ✦ Launch AI Builder — It's Free
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Typewriter effect
const words = ['instantly with AI','in seconds','without code','like a pro','that converts'];
let wi = 0, ci = 0, del = false;
const el = document.getElementById('typewriter');
function type() {
  const w = words[wi];
  el.textContent = del ? w.slice(0, --ci) : w.slice(0, ++ci);
  if (!del && ci === w.length) { setTimeout(() => del = true, 2000); }
  if (del && ci === 0) { del = false; wi = (wi + 1) % words.length; }
  setTimeout(type, del ? 60 : 120);
}
type();
</script>
</body>
</html>
