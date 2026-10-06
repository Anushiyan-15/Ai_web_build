<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Manual — WebCraft AI Website Builder</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;background:#060911;color:#e2e8f0;line-height:1.65;min-height:100vh;}
a{color:#38bdf8;}
.uman-top{position:sticky;top:0;z-index:50;background:rgba(12,18,32,.92);backdrop-filter:blur(10px);border-bottom:1px solid #1e293b;padding:.8rem 1.5rem;display:flex;align-items:center;gap:.75rem;}
.uman-logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.uman-top h1{font-size:1.05rem;font-weight:900;color:#fff;}
.uman-top p{font-size:.72rem;color:#94a3b8;}
.uman-layout{display:flex;max-width:1200px;margin:0 auto;align-items:flex-start;}
.uman-toc{position:sticky;top:73px;width:260px;flex-shrink:0;max-height:calc(100vh - 90px);overflow-y:auto;padding:1.5rem 0.5rem 2rem 1.5rem;}
.uman-toc strong{display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin:.9rem 0 .4rem;}
.uman-toc strong:first-child{margin-top:0;}
.uman-toc a{display:block;font-size:.8rem;font-weight:600;color:#94a3b8;padding:.32rem .6rem;border-radius:8px;text-decoration:none;border-left:2px solid transparent;}
.uman-toc a:hover{color:#fff;background:#111726;}
.uman-toc a.active{color:#c7d2fe;background:#1e1b4b;border-left-color:#6366f1;}
.uman-main{flex:1;min-width:0;padding:2rem 2rem 4rem;}
.uman-hero{background:linear-gradient(135deg,#1e1b4b,#0c1220);border:1px solid #28334d;border-radius:18px;padding:1.75rem;margin-bottom:1.5rem;}
.uman-hero h2{font-size:1.5rem;font-weight:900;color:#fff;margin-bottom:.4rem;}
.uman-hero p{font-size:.9rem;color:#94a3b8;}
.uman-part{display:flex;align-items:center;gap:.7rem;margin:2.2rem 0 1rem;padding:.7rem 1.1rem;background:linear-gradient(135deg,rgba(99,102,241,.16),rgba(16,185,129,.1));border:1px solid rgba(99,102,241,.45);border-radius:14px;}
.uman-part b{font-size:1.02rem;color:#fff;}
.uman-part span{font-size:.75rem;color:#a5b4fc;}
.uman-sec{margin-bottom:2rem;scroll-margin-top:90px;}
.uman-sec h2{font-size:1.2rem;font-weight:900;color:#fff;margin-bottom:.7rem;display:flex;align-items:center;gap:.5rem;}
.uman-sec h3{font-size:.98rem;font-weight:800;color:#c7d2fe;margin:1rem 0 .4rem;}
.uman-sec p,.uman-sec li{font-size:.87rem;color:#cbd5e1;}
.uman-sec ul,.uman-sec ol{padding-left:1.35rem;margin:.4rem 0 .8rem;display:flex;flex-direction:column;gap:.32rem;}
.uman-sec b,.uman-sec strong{color:#fff;}
.uman-card{background:#0c1220;border:1px solid #1e293b;border-radius:14px;padding:1rem 1.2rem;margin:.7rem 0;}
.uman-where{display:flex;gap:.5rem;align-items:flex-start;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.35);border-radius:10px;padding:.6rem .85rem;margin:.6rem 0;font-size:.8rem;color:#c7d2fe;}
.uman-where::before{content:'📍';flex-shrink:0;}
.uman-tip{display:flex;gap:.5rem;align-items:flex-start;background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.35);border-radius:10px;padding:.6rem .85rem;margin:.6rem 0;font-size:.8rem;color:#a7f3d0;}
.uman-tip::before{content:'💡';flex-shrink:0;}
.uman-warn{display:flex;gap:.5rem;align-items:flex-start;background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.4);border-radius:10px;padding:.6rem .85rem;margin:.6rem 0;font-size:.8rem;color:#fde68a;}
.uman-warn::before{content:'⚠️';flex-shrink:0;}
kbd{background:#1e293b;border:1px solid #334155;border-radius:5px;padding:.05rem .4rem;font-family:inherit;font-size:.76rem;color:#fff;}
code.inline{background:#111726;border:1px solid #1e293b;border-radius:6px;padding:.05rem .4rem;font-size:.78rem;color:#67e8f9;}
table{width:100%;border-collapse:collapse;margin:.6rem 0;font-size:.8rem;}
th,td{text-align:left;padding:.5rem .7rem;border-bottom:1px solid #1e293b;vertical-align:top;}
th{color:#818cf8;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;}
td{color:#cbd5e1;}
.uman-foot{text-align:center;color:#475569;font-size:.75rem;padding:2rem 1rem;}
@media(max-width:860px){
  .uman-layout{flex-direction:column;}
  .uman-toc{position:static;width:100%;max-height:none;padding:1rem 1.25rem 0;display:flex;flex-wrap:wrap;gap:.35rem;}
  .uman-toc strong{width:100%;margin:.4rem 0 .2rem;}
  .uman-toc a{border:1px solid #1e293b;padding:.3rem .6rem;}
  .uman-main{padding:1.25rem 1.25rem 3rem;}
}
@media print{.uman-toc,.uman-top{display:none;}.uman-main{padding:0;}body{background:#fff;color:#000;}.uman-sec p,.uman-sec li,.uman-sec h2,.uman-sec h3,.uman-part b{color:#000;}}
</style>
</head>
<body>
<header class="uman-top">
  <div class="uman-logo">📘</div>
  <div><h1>WebCraft AI — User Manual</h1><p>Two guides: ① Create your website · ② Edit your website — then publish</p></div>
</header>
<div class="uman-layout">
  <nav class="uman-toc" id="uman-toc">
    <strong>Manual 1 · Create</strong>
    <a href="#uman-account">1 · Account: sign up &amp; log in</a>
    <a href="#uman-wizard">2 · Describe your business (Steps 1–3)</a>
    <a href="#uman-generate">3 · Mode &amp; Generate (Step 4)</a>
    <a href="#uman-pick">4 · Pick a variation</a>
    <strong>Manual 2 · Edit</strong>
    <a href="#uman-builder">5 · Builder: type-to-edit &amp; toolbar</a>
    <a href="#uman-ai">6 · AI Co-Pilot chat</a>
    <a href="#uman-studio">7 · Studio visual editor</a>
    <a href="#uman-devices">8 · Phone / tablet / PC check</a>
    <strong>Publish &amp; manage</strong>
    <a href="#uman-publish">9 · Publish &amp; go live</a>
    <a href="#uman-portal">10 · Your dashboard &amp; admin panel</a>
    <a href="#uman-faq">11 · FAQ &amp; fixes</a>
  </nav>
  <main class="uman-main">
    <div class="uman-hero">
      <h2>👋 What can you do here?</h2>
      <p><b>Manual 1</b> takes you from zero to 3 AI-designed websites. <b>Manual 2</b> shows how to edit every word, picture and section yourself — by typing, by chatting with AI, or in the drag-and-drop Studio. Finish with Publish and share your live link. Each section tells you <b>exactly where each button lives</b>.</p>
    </div>

    <div class="uman-part" id="uman-part1"><b>📗 Manual 1 · Create your website</b><span>account → describe → generate → pick — about 5 minutes</span></div>

    <section class="uman-sec" id="uman-account">
      <h2>1 · Account: sign up &amp; log in</h2>
      <p>You need an account <b>before generating</b> — the Generate button asks you to sign in first. Open the <b>Customer Portal</b> (login page). It has two tabs at the top of the card:</p>
      <table>
        <tr><th>Tab</th><th>What to do</th></tr>
        <tr><td><b>Create Account</b></td><td>3 quick steps — ① type <b>Name + Email</b> and press <b>Send Verification Code</b> → ② type the <b>6-digit code</b> from your email and press <b>Verify Email</b> → ③ set a <b>password (8+ characters)</b> and press <b>Create My Account</b>. You are signed in automatically.</td></tr>
        <tr><td><b>Sign In</b></td><td>Enter <b>Customer Email + Password</b> (or just an <b>Order ID</b> for quick access) and press <b>🚀 Access My Websites</b>.</td></tr>
      </table>
      <h3>One-click social login</h3>
      <p>Under each form: <b>“or continue with” — Google · Facebook · Instagram</b>. Click an icon and approve, like any normal app. First time, an account is created for you automatically. <b>Instagram</b> asks for your <b>email once</b> afterwards (Instagram never shares emails).</p>
      <h3>Forgot password &amp; logout</h3>
      <ul>
        <li><b>Forgot password?</b> under Sign In → email → 6-digit code (valid 10 min) → <b>Verify</b> → new password → back to login to <b>sign in manually</b>. A ← Back button returns to login anytime.</li>
        <li><b>🚪 Sign Out</b> (dashboard top-right). After logout the browser back-button cannot reopen your account.</li>
      </ul>
      <div class="uman-where">Sign In / Create Account tabs are at the top of the login card; social buttons sit right below each form.</div>
    </section>

    <section class="uman-sec" id="uman-wizard">
      <h2>2 · Describe your business (Steps 1–3 of 4)</h2>
      <p>Open the <b>Builder</b> and walk the 4-dot wizard (<b>Next Step →</b> / <b>← Back</b> at the bottom). The more you write, the better the AI result. Red <b>*</b> = required.</p>
      <h3>Step 1 · Business Profile (required: name + tagline)</h3>
      <ul>
        <li><b>Business name *</b>, <b>Business type *</b> (Agency, Restaurant, SaaS, E-Commerce, Clinic, Gym, Portfolio…), <b>Tagline *</b> (one line, e.g. “Fresh bread, baked daily”).</li>
        <li><b>Audience, Services</b> — who it is for, what you offer.</li>
        <li><b>Products</b> (only for shops) — one per line as <code class="inline">Name | Price | ImageURL</code>, e.g. <code class="inline">Sourdough Loaf | Rs.850</code>. Adding products switches <b>Shop mode ON</b> (product grid + working cart).</li>
        <li><b>Reviews</b> — one per line as <code class="inline">Name | Review | Role</code>.</li>
        <li>Short on time? Press <b>🛍️ E-Commerce Demo</b>, <b>🎯 Agency Demo</b>, or a preset (café, SaaS, gym) to prefill everything.</li>
      </ul>
      <h3>Step 2 · Design Direction</h3>
      <ul>
        <li><b>Style:</b> Modern (clean), Bold (big &amp; colourful), Dark (luxury dark).</li>
        <li><b>Brand colour:</b> purple, blue, green, red, gold or slate — the whole site follows it.</li>
        <li><b>Vibe + dials (optional):</b> minimalist, premium, playful, editorial, brutalist or trust — leave on <b>Auto</b> if unsure. Extra notes go in the <b>design direction</b> box (“calm clinic”, “wild agency”…).</li>
      </ul>
      <h3>Step 3 · Sections</h3>
      <p>Tick what your site needs: hero, services, about, metrics, <b>shop</b>, contact, footer (most are pre-ticked; shop only for stores).</p>
      <div class="uman-tip">Write real names, prices and phone numbers — the AI uses your words verbatim. Leave the rest; never type “lorem ipsum”.</div>
    </section>

    <section class="uman-sec" id="uman-generate">
      <h2>3 · Mode &amp; Generate (Step 4 of 4)</h2>
      <p>Step 4 collects <b>phone, email, address, logo URL</b> and social links (all optional — address adds a map, socials add footer icons). Then press <b>✦ Generate 3 Style Variations</b> (it stays greyed out until <b>name + tagline</b> are filled). A mode popup appears first:</p>
      <table>
        <tr><th>Mode</th><th>You get</th></tr>
        <tr><td><b>📄 Static</b></td><td>Simplest — just the website. No login system, no admin panel.</td></tr>
        <tr><td><b>🛠️ Admin</b></td><td>Website + login-protected admin panel to manage content (no database).</td></tr>
        <tr><td><b>🗄️ Admin + Database</b></td><td>Full stack — admin panel + MySQL database.</td></tr>
      </table>
      <ul>
        <li>Admin modes ask for <b>what the admin should manage</b> (min 20 letters — presets like shop, clinic, school fill it in one tap) plus an <b>admin username + password</b> (or <b>🎭 Use Demo Credentials</b>).</li>
        <li><b>⚡ Solo Quick Site</b> skips the trio and builds one fast site instead.</li>
      </ul>
      <div class="uman-warn">Not signed in? Generate stops with “Please sign in / create account first” and sends you to sign-up. Sign in, come back — your form is still there.</div>
      <h3>While it builds</h3>
      <p>A full-screen loader shows <b>percentage, live AI activity, a skill checklist</b> and a mini wireframe that lights up per finished variation. 3 AI designs take ~1–2 minutes. If one AI slot is slow, that slot is auto-filled with a premium template — check the badge on each card:</p>
      <ul>
        <li><b>✦ AI-GENERATED · model-name</b> — made fresh by AI.</li>
        <li><b>📄 TEMPLATE · server engine</b> — premium template fallback for a failed AI slot.</li>
        <li><b>✦ TASTE 82</b> — design-quality score (70+ ships as-is).</li>
      </ul>
    </section>

    <section class="uman-sec" id="uman-pick">
      <h2>4 · Pick a variation</h2>
      <p>You land on <b>✦ 3 Style Variations</b>. Each card has a live mini-preview and 3 buttons:</p>
      <ul>
        <li><b>👁️ Full Preview</b> — open that design big (try the PC/Laptop/Tablet/Phone buttons inside too).</li>
        <li><b>✦ Layouts</b> — 3 extra layout variants (A/B/C) of the same style; each opens with <b>Full Preview</b> and <b>Select &amp; Edit →</b>.</li>
        <li><b>Select &amp; Edit →</b> — loads it into the Builder workspace (Manual 2 starts here).</li>
      </ul>
      <div class="uman-tip">Compare all 3 in Full Preview on phone view first — most visitors come from phones. Then Select &amp; Edit your favourite.</div>
    </section>

    <div class="uman-part" id="uman-part2"><b>📘 Manual 2 · Edit your website</b><span>three ways: type directly · ask AI · Studio drag-and-drop</span></div>

    <section class="uman-sec" id="uman-builder">
      <h2>5 · Builder: type-to-edit &amp; toolbar</h2>
      <p>After <b>Select &amp; Edit</b> you see your site live in the middle, tools around it.</p>
      <h3>✏️ Edit toggle (fastest way to change words)</h3>
      <div class="uman-card">
        <p><b>OFF (default):</b> the preview is a <b>normal web page</b> — click links, scroll, test forms. Nothing can change by accident.</p>
        <p><b>ON:</b> click <b>any text</b> and <b>type</b> (dashed outline = editing). Toggle <b>OFF</b> to save — words are stored into that variation (with Undo support).</p>
      </div>
      <div class="uman-where">The ✏️ Edit toggle is in the preview bar, between the view label and ↺ Reload. It works on the 🌐 Site view only.</div>
      <h3>Top toolbar — left to right</h3>
      <table>
        <tr><th>Button</th><th>What it does</th></tr>
        <tr><td>← Back</td><td>Back to your dashboard (or home for guests).</td></tr>
        <tr><td>Variation 1 · 2 · 3</td><td>Jump between your 3 designs; edits stay per variation.</td></tr>
        <tr><td>🌐 Site / 🔐 Admin Panel</td><td>Flip between website and its admin backend (Admin/Database modes only).</td></tr>
        <tr><td>👁 Preview</td><td>Open the current site in a new tab.</td></tr>
        <tr><td>🎨 Edit in Studio ↗</td><td>Open the drag-and-drop Studio (section 7).</td></tr>
        <tr><td>＋ Add Function</td><td>Visible in Admin view after publishing — adds features to the live site.</td></tr>
        <tr><td>← Back to Variations</td><td>Return to the 3-style picker.</td></tr>
        <tr><td>📖 Admin &amp; Features Guide / 📘 Manual</td><td>Guides (this manual).</td></tr>
        <tr><td>↶ Undo AI</td><td>Revert the last AI change.</td></tr>
        <tr><td>🚀 Save &amp; Publish</td><td>Checkout → your site goes live (section 9).</td></tr>
      </table>
    </section>

    <section class="uman-sec" id="uman-ai">
      <h2>6 · AI Co-Pilot chat (✦ button, bottom-right)</h2>
      <p>Press <b>✦ WebCraft AI</b>. Write in plain English or Tanglish — “Add a pricing table with 3 plans”, “hero-oda colour maathu”, “footer la WhatsApp add pannu”. Or tap a chip: <b>⭐ Reviews · 💰 Pricing · ❓ FAQ · 💬 WhatsApp · 👥 Team · 🌙 Dark Mode</b>.</p>
      <ul>
        <li><b>🎯 Editing target</b> (top of the panel) — check <b>which variation</b> the AI will change before sending.</li>
        <li><b>🎤 Mic</b> — dictate (Tamil / English / Hindi); <b>➤</b> sends (Enter works too).</li>
        <li>Wrong result? <b>↶ Undo AI</b> in the toolbar reverts it.</li>
      </ul>
      <div class="uman-tip">One change per message works best (“make buttons bigger” beats a 5-item list). Wait for the reply before the next edit.</div>
    </section>

    <section class="uman-sec" id="uman-studio">
      <h2>7 · Studio visual editor (🎨 Edit in Studio)</h2>
      <p>Drag-and-drop editing on a live canvas. <b>← Back</b> saves and returns to the Builder; <b>✓ Save</b> saves here. Concept tabs <b>C1/C2/C3</b> switch designs.</p>
      <h3>How to edit anything (4 gestures)</h3>
      <ul>
        <li><b>Click</b> an element → it selects; the floating <b>✍️ Edit Section Content</b> button edits its text.</li>
        <li><b>Double-click an image</b> → image editor (upload/replace). <b>Click a button</b> → button editor (text, link, colours).</li>
        <li><b>Right-click</b> → menu: edit, animate, duplicate, bring forward / send backward, delete…</li>
        <li><b>Drag the ↕ handle</b> on a hovered section to move it up/down. <kbd>Delete</kbd> removes, <kbd>Ctrl</kbd>+<kbd>Z</kbd> / <kbd>Ctrl</kbd>+<kbd>Y</kbd> undo/redo.</li>
      </ul>
      <h3>Panels around the canvas</h3>
      <ul>
        <li><b>Left icon rail (13):</b> 🧱 Elements · 🃏 Cards · ⭐ Shapes · 📁 Media · ✍️ Text · 🎬 Anim · 🌐 Lang · 🎨 Styles · ⚙️ Settings · 📑 Layers · 📝 Forms · 🛍️ Shop · 📄 Pages. Click one, pick a block, it drops on the canvas. <b>🔍 Search blocks</b> finds things fast.</li>
        <li><b>Right ⚡ Quick Actions:</b> contextual buttons for the selection (Edit, Style, Clone, Delete, Image, Animate, AI Edit…). Nothing selected? It shows tips instead.</li>
        <li><b>Header:</b> devices 🖥/📱/📲 + ribbon (adds 💻 1200px laptop) · ↶ ↷ undo/redo · ⬚ outlines · ⛶ fullscreen · ❓ Help (all shortcuts) · ✦ Magic AI · 👁️ Preview (new tab at current width) · <b>✏️ Edit ON/OFF</b> (OFF = clean normal page, nothing selectable).</li>
      </ul>
      <div class="uman-tip">Everything auto-saves to the browser — press <b>✓ Save</b> (or ← Back) and it syncs back to the Builder automatically.</div>
    </section>

    <section class="uman-sec" id="uman-devices">
      <h2>8 · Phone / tablet / PC check</h2>
      <ul>
        <li><b>Builder preview bar:</b> 🖥 PC (full) · 💻 Laptop (1280px) · 📱 Tablet (768px) · 📲 Phone (390px).</li>
        <li><b>Fullscreen modal:</b> same 4 buttons inside the big preview.</li>
        <li><b>Studio 👁️ Preview:</b> opens a new tab framed at the selected width with a “Customer preview” label.</li>
      </ul>
      <div class="uman-tip">Scroll the stats section on phone view too — numbers (50k+, 99.8%…) count up when they scroll into view. Header links smooth-scroll everywhere automatically.</div>
    </section>

    <div class="uman-part" id="uman-part3"><b>🚀 Publish &amp; manage</b><span>go live, then run your site</span></div>

    <section class="uman-sec" id="uman-publish">
      <h2>9 · Publish &amp; go live</h2>
      <ol>
        <li>Click <b>🚀 Save &amp; Publish</b> → a 5-step wizard: <b>1 Review → 2 Contact Inbox → 3 Admin Credentials → 4 Choose Plan &amp; Pay → 5 Published</b> (<b>Continue →</b>, finally <b>🔒 Pay &amp; Publish Now</b>).</li>
        <li>Pay with <b>PayPal</b> (or the demo pay option). You get a <b>live link</b> like <code class="inline">…/published/your-site/</code> — share it anywhere.</li>
        <li>Admin/Database modes also get an <b>admin login</b> at <code class="inline">…/published/your-site/admin/login.php</code> — log in and manage content; press <b>＋ Add Function</b> anytime to add features with AI.</li>
      </ol>
      <div class="uman-where">After publishing: ⚙️ Website Manager (settings &amp; features) and Open My Projects (dashboard) links are on the success screen.</div>
    </section>

    <section class="uman-sec" id="uman-portal">
      <h2>10 · Your dashboard &amp; admin panel</h2>
      <h3>Customer dashboard (after login)</h3>
      <ul>
        <li><b>🌐 Published Websites</b> — every site with <b>Open Live</b>, <b>Admin</b>, <b>Add AI Functions</b> and <b>🎨 Edit Design in Builder</b> shortcuts. Empty? <b>➕ Create New Website</b> / <b>Build with AI</b> starts over.</li>
        <li><b>Profile card</b> — click the photo to change it, <b>✎ Edit Profile</b> for name/phone (email never changes).</li>
        <li><b>Stats + 🔔 Notifications</b> — total sites, active online, next renewal, payment/update alerts.</li>
      </ul>
      <h3>Your site's admin panel (Admin/Database modes)</h3>
      <p>Open it from the dashboard (<b>🔐 Open Admin Panel</b>) or the Builder's <b>🔐 Admin Panel</b> tab. Log in with the admin username/password you set at publish time, then manage whatever you configured (products, orders, bookings…) from the sidebar.</p>
    </section>

    <section class="uman-sec" id="uman-faq">
      <h2>11 · FAQ &amp; fixes</h2>
      <table>
        <tr><th>Problem</th><th>Fix</th></tr>
        <tr><td>Manual popup is empty / blank</td><td>Press <b>↗ Open full page</b> in the popup header — the full manual opens in a new tab. (The popup needs network access to this site; the full page always works.)</td></tr>
        <tr><td>Generate button is greyed out</td><td>Fill <b>Business name + Tagline</b> (Step 1) — it enables automatically.</td></tr>
        <tr><td>“Please sign in first” on Generate</td><td>Sign up / sign in (section 1), then return — your form is kept.</td></tr>
        <tr><td>A card says 📄 TEMPLATE</td><td>That AI slot failed (e.g. timed out) and a premium template filled in — the other cards are still AI. Retry Generate for a full-AI trio.</td></tr>
        <tr><td>My edit is not showing</td><td>Builder: <b>↺ Reload</b> re-renders. Edit-mode typing: toggle <b>✏️ Edit OFF</b> to save. Studio: press <b>✓ Save</b>.</td></tr>
        <tr><td>AI changed the wrong design</td><td>Check the <b>🎯 Editing</b> dropdown first, then <b>↶ Undo AI</b> and retry.</td></tr>
        <tr><td>Stats show 0 and never count</td><td>Scroll them into view — counting triggers on scroll. Reduced-motion shows final values instantly.</td></tr>
        <tr><td>Mobile layout looks off</td><td>Switch to 📲 Phone preview and fix stacking, font sizes, section padding there.</td></tr>
        <tr><td>Social login says “not set up”</td><td>The site owner must add provider keys (config/social.php). Email + code login always works.</td></tr>
      </table>
    </section>
    <div class="uman-foot">WebCraft AI · User Manual · Manual 1 = Create · Manual 2 = Edit · Press the 📘 Manual button in Builder or Studio anytime.</div>
  </main>
</div>
<script>
// Active TOC highlighting (progressive enhancement only)
(function(){
  try{
    var links=[].slice.call(document.querySelectorAll('#uman-toc a'));
    var map={}; links.forEach(function(a){ map[a.getAttribute('href').slice(1)]=a; });
    var obs=new IntersectionObserver(function(es){
      es.forEach(function(e){
        if(e.isIntersecting){
          links.forEach(function(a){a.classList.remove('active');});
          var a=map[e.target.id]; if(a) a.classList.add('active');
        }
      });
    },{rootMargin:'-40% 0px -55% 0px'});
    document.querySelectorAll('.uman-sec').forEach(function(s){obs.observe(s);});
  }catch(e){}
})();
</script>
</body>
</html>
