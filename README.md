# WebCraft AI — Website Builder with PayPal Payment & Instant Publishing

An enterprise-ready AI Website Builder powered by **Google Gemini 2.0/3.6 Flash** and **PayPal REST API v2**. It enables users to describe their business, generate multi-concept responsive websites, edit them live with CodeMirror or a visual drag-and-drop studio, and instantly publish them live upon PayPal payment.

---

## 📁 Professional Project Architecture

```
webbbuilder/
├── api/                              # Backend JSON REST API Endpoints
│   ├── generate.php                  # Gemini AI website generation & interactive refinement
│   ├── payment.php                   # PayPal create_order, capture_order & auto-publish
│   └── contact.php                   # Contact form inquiry handler
│
├── assets/                           # Static Web Assets
│   ├── css/
│   │   └── loader-3d.css             # Shared creative loaders (all pages)
│   ├── js/
│   │   ├── ai-flowcraft.js           # Core AI generation engine (used by builder.php)
│   │   ├── opencode-service.js       # OpenCode AI lane (builder.php, studio.php)
│   │   ├── ai-admin-generator.js     # Gemini-backed admin-panel generator (publish.php, site-manager.php)
│   │   └── loader-3d.js              # Loader helpers (all pages)
│   └── images/                       # Graphics, icons, logos
│
├── config/                           # Modular Configurations
│   ├── app.php                       # Application parameters, Gemini API key, site URL
│   ├── paypal.php                    # PayPal REST API credentials, currency, plan prices
│   └── database.php                  # MySQL PDO credentials (UniServerZ)
│
├── database/                         # Database Schemas & Migrations
│   └── schema.sql                    # Production schema with PayPal orders & publish tracking
│
├── includes/                         # Reusable Services & Layouts
│   ├── db.php                        # Safe PDO connection with file-storage fallback
│   ├── PayPalService.php             # Full PayPal REST API v2 Client (OAuth, Orders, Captures)
│   ├── nav.php                       # Global sticky header & navigation
│   └── footer.php                    # Global footer
│
├── published/                        # Auto-published Live Client Websites
│   ├── index.php                     # Showcase directory of all published client websites
│   └── <site-slug>/                  # Individual published site directory
│       ├── index.html                # Frozen production-ready HTML/CSS/JS website
│       └── meta.json                 # Site publication metadata
│
├── storage/                          # Server-side Persistent Storage
│   ├── .htaccess                     # Security restriction protecting private records
│   ├── designs/                      # Draft HTML snapshots & backups
│   ├── orders/                       # JSON transaction records & order receipts
│   └── submissions/                  # Contact & intake questionnaire submissions
│
├── index.php                         # Public Landing Page & Features Showcase
├── builder.php                       # AI-FlowCraft Multi-Concept Generator + Live Split Editor + PayPal Modal
├── studio.php                        # Visual Drag & Drop Editor (user-friendly customize: drag-drop, photo uploads, shapes, map)
├── client-intake.php                 # Comprehensive Client Requirements / Quote Questionnaire
├── contact.php                       # Contact Page
└── config.php                        # Root configuration bootstrap loader
```

---

## 💳 PayPal Payment & Publishing Flow

1. **User designs a website** in `builder.php` (AI-FlowCraft generation + AI Co-Pilot prompt editing) or customizes visually in `studio.php` (drag-drop, photos, shapes, map), then clicks **"Next → Save & Publish"**.
2. **Subscription Plan Selection**:
   - **Starter**: \$9.00/mo
   - **Pro**: \$19.00/mo (Recommended)
   - **Business**: \$49.00/mo
3. **Order Creation (`POST /api/payment.php?action=create_order`)**:
   - Backend calls PayPal REST API v2 (`POST /v2/checkout/orders`).
   - HTML snapshot is securely saved in `storage/designs/`.
   - Returns PayPal approval URL (`checkout_url`).
4. **PayPal Checkout**:
   - User is redirected to PayPal to approve payment via PayPal Balance or Debit/Credit Card.
   - Upon completion, PayPal redirects to `SITE_URL/builder.php?payment=success&token=<paypal_order_id>`.
5. **Capture & Instant Publishing (`POST /api/payment.php?action=capture_order`)**:
   - Backend captures the authorized payment via `POST /v2/checkout/orders/{id}/capture`.
   - Verifies `status === 'COMPLETED'`.
   - Generates a safe slug (e.g., `apex-studio-a1b2c3`) and creates `published/<slug>/index.html`.
   - Stores transaction record in MySQL and `storage/orders/<order_ref>.json`.
   - Returns live URL: `http://localhost/project/webbbuilder/published/<slug>/`.
6. **Live Deployment**:
   - Website is immediately live on your server.
   - The user receives direct links to view and copy their live website address.

---

## ⚙️ How to Configure PayPal

Open `config/paypal.php`:

```php
// 1. Set mode to 'sandbox' (for testing) or 'live' (for production):
define('PAYPAL_MODE', 'sandbox');

// 2. Put your PayPal REST App Credentials (from developer.paypal.com):
define('PAYPAL_CLIENT_ID', 'YOUR_PAYPAL_SANDBOX_CLIENT_ID');
define('PAYPAL_CLIENT_SECRET', 'YOUR_PAYPAL_SANDBOX_CLIENT_SECRET');

// 3. Set your currency:
define('PAYPAL_CURRENCY', 'USD');
```

> **Testing without PayPal credentials**:
> If credentials are not yet configured or left as placeholder, the system automatically uses an intelligent **Sandbox Simulator** mode. You can test the entire generation, payment flow, and instant publishing without needing a live PayPal account right away!

---

## 🤖 Gemini AI Configuration

Open `config/app.php`:

```php
// Free API key from https://aistudio.google.com/app/apikey
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY');
define('GEMINI_MODEL',   'gemini-3.6-flash'); // or gemini-2.0-flash
```

---

## 🚀 Published Sites Portal

You can view all published websites anytime by visiting:
`http://localhost/project/webbbuilder/published/`

---

## ✦ Taste Skill — Design-Intelligence Layer

Adapted from the official approach at https://github.com/Leonxlnx/taste-skill
(`design-taste-frontend` v2 for generation, `redesign-existing-projects` for edits).
Project skill file: `skills/taste/SKILL.md`. Executable adapter: `includes/TasteSkill.php`.

- **Wizard (Step 2)**: customer picks a vibe (Auto/Minimal/Premium/Playful/Editorial/Brutalist/Trust-first)
  plus 3 dials — Layout variance / Motion intensity / Visual density (`0` = Auto, baseline `8/6/4`).
  Collected by `collectTastePrefs()` in `builder.php`, embedded in every generation payload.
- **Generation**: `taste_prompt_block()` (design read + dials + anti-slop hard bans) is injected into
  every OpenCode free-lane and Gemini fast-lane prompt; `taste_system_addendum()` hardens the system prompts.
- **AI editing**: `taste_edit_addendum()` enforces the audit-first protocol (scan → diagnose → surgical fix);
  snippet micro-edits carry `taste_snippet_addendum()`.
- **Pre-flight audit**: `taste_audit_html()` scores every AI-produced page (hero discipline, eyebrow restraint,
  single CTA intent, no AI-purple/slop copy/dead links, alt text, reduced-motion, responsive, real photography).
  Responses carry `taste` (design read + dials) and `taste_audit` (score 0–100 + issues); concept cards show a
  `✦ TASTE <score>` badge.
- **Close-the-loop polish**: `taste_apply_safety_net()` (free deterministic CSS patch, idempotent) runs on every
  generation; weak pages (score < 85) get one AI polish pass via `POST /api/opencode.php {"action":"taste_repair",…}`
  — frontend auto-fires it per slot in its own request budget and keeps the better-scoring version.
- **Standalone audit**: `POST /api/opencode.php {"action":"taste_audit","html":"..."}`.

---

## 🔗 Frontend → JSON → Admin: Connected Website + Admin Panel System

> Core rule: **FRONTEND INPUT → SERVER-SIDE VALIDATION → JSON DATABASE → REAL ADMIN PANEL.**
> Every customer-facing form persists to JSON and appears in the admin panel.
> Generator: `includes/PublishedAdminKit.php`. Verified by `tests/test_published_admin_kit.php`
> (run: `C:\php\php.exe tests/test_published_admin_kit.php` — 82 checks, all live).

### Architecture and published directory structure

```
published/<slug>/
├── index.html              # Live website (contact wiring + business runtime injected)
├── contact.php             # Contact receiver: validates, flock-saves to admin inbox,
│                           #   mirrors to storage, then sends mail (owner SMTP → queue)
├── submit.php              # Universal PUBLIC handler (no login): validates against the
│                           #   manifest, saves to admin/data_<entity>.json, returns JSON
├── meta.json               # admin_url + admin_login + publish_status + publish_errors
├── api/business-ops.php    # Bridge to the root JSON-operations backend
└── admin/
    ├── login.php / logout.php / auth.php   # Hash-only login (bcrypt, throttled)
    ├── includes/auth.php   # csrf_token(), csrf_field(), csrf_validate(),
    │                       #   require_login(), logout(), verify_login()
    ├── includes/db.php     # db_read/write/insert/update/delete/find (+ db_field_defs)
    ├── includes/layout.php # sidebar($active), header_html(), footer_html()
    ├── index.php           # Real dashboard: counts + recent 5 + status, all from JSON
    ├── <entity>.php        # One CRUD page per manifest entity (search, 20/page PRG)
    ├── manage.php          # Multi-entity manager (kept for backward compatibility)
    ├── inbox.php           # Inquiries inbox (messages.php kept as alias page)
    ├── settings.php        # Hash-only password change + site.json settings
    ├── api.php             # Authenticated admin AJAX API (CSRF on POST)
    ├── install.php         # One-time installer (refuses repeat runs)
    ├── manifest.json       # Entity/module contract for this site
    ├── form_map.json       # Frontend-form → entity mapping used by submit.php
    ├── site.json           # Site settings (separate from .auth.json credentials)
    ├── content.json        # Website content editor data
    └── data_<entity>.json  # One JSON table per entity (customer records live here)
```

System of record mirrors: `storage/collections/<slug>/<entity>.json`
(operational writes) and `storage/sites/<slug>/business.json` (manifest).

### Administrator login and credential initialization

- Publish with an admin username (4–20 chars, starts with a letter) + password
  (min 8 chars). Only `password_hash()` output is stored — in the order file
  (`admin_password_hash`) and the site's `admin/.auth.json`. Plaintext is never
  written to disk.
- Login: `published/<slug>/admin/login.php`. Throttled (5 attempts / 5 min / IP),
  generic errors, `session_regenerate_id(true)` on success.
- The real admin URL is in `published/<slug>/meta.json` (`admin_url`,
  `admin_login`). After publishing, the builder links that exact URL (validated
  to belong to `/published/<slug>/`). Before publishing, the builder Admin tab
  provisions a REAL preview admin (see "Pre-publish development environment"
  below) — there is no mock admin anywhere.

### Safe installation process

`published/<slug>/admin/install.php`: refuses to run when `.installed` exists,
validates `manifest.json`, requires configured credentials (no default
password), creates missing data files/dirs/`site.json` without touching
existing records, and writes `.installed` only after everything succeeds.

### Where manifests and JSON records are stored

- Per-site manifest: `storage/sites/<slug>/business.json` (+ read-only admin
  copy `published/<slug>/admin/manifest.json`).
- Customer records: `published/<slug>/admin/data_<entity>.json` (admin-visible)
  mirrored to `storage/collections/<slug>/<entity>.json`.
- Every record: unique `id` (`r_` + 16 hex chars), `created_at`/`updated_at`
  (ISO-8601), plus a processing `status` (`pending` for bookings/orders,
  `unread` + `read:false` for inquiries).

### How public forms connect to the backend

- Contact-style forms (email + message) → `contact.php` (existing mailer flow,
  upgraded to flock-guarded storage with real ids/timestamps).
- Business forms (booking/order/registration/review/quote/newsletter/…) →
  `submit.php` (`form=<purpose>` or explicit `entity=`), validated against
  `admin/manifest.json` + `form_map.json`; or the `api/business-ops.php`
  JSON backend used by the storefront runtime (idempotent, same storage).
- Protections on every public write: server-side type/required checks, 5000-char
  field caps, 256 KB request cap, `company` honeypot, 20/hour/IP rate limit,
  60-second near-duplicate guard, `flock()` locked read-modify-write. Failures
  return `success:false` (never a fake success).

### How submitted data appears in the admin panel

`<entity>.php` lists `data_<entity>.json` (search + 20/page); `inbox.php` lists
`data_inquiries.json` with read/unread + `mailto:` reply; the dashboard counts
records, unread inquiries, and pending statuses live, with the latest 5 records.
Reload-safe: everything is read from JSON on each request.

### How to add a new entity

1. Add it to the order's `admin_entities`
   (`[{id|name, label, icon, fields:[{name,label,type,required,options}]}]`)
   or to `admin/manifest.json` `entities`, then republish — or use
   Site Manager → **Add Features (AI)**, which validates, merges without
   duplicates, creates `data_<id>.json`, generates `<id>.php`, refreshes the
   nav/manifest, and wires storage automatically (SQL in AI output is ignored —
   this stack is PHP + JSON only).
2. Supported field types: `text, textarea, number, email, url, date, time,
   datetime, tel, select (options[]), checkbox`. Missing/invalid fields fall back
   to a safe `title` text field. Entity ids are lowercased to `[a-z0-9_]` —
   `Reviews` → `reviews` (never `eviews`).

### How to add a new frontend form and connect it to an entity

Add a `<form>` with `data-wc-form="<purpose>"` (contact|booking|order|
registration|review|quote|newsletter|appointment) — it posts to `submit.php`
automatically — or post explicitly with `entity=<id>`. Unknown `entity` values
are rejected; extra safe business fields (≤30, ≤2000 chars) are preserved, while
`role/is_admin/paid/approved/status/password*` from the client are always
stripped (status is assigned server-side).

### How CRUD and the AJAX API work

- Pages: list → create/edit forms prefilled with saved values (`db_find`) →
  `db_insert`/`db_update` → Post/Redirect/Get with flash; delete is POST-only
  with CSRF + confirm via `db_delete()`. All output uses `htmlspecialchars()`.
- API (`admin/api.php`, admin session required):
  `GET ?action=list&entity=X[&q=]`, `GET ?action=get&entity=X&id=Y`,
  `POST ?action=save` (entity + id? + fields), `POST ?action=delete`
  (CSRF required for POST). Consistent `{success, data|error}` JSON, correct
  HTTP codes, no credential/path/stack-trace leaks.

### How to change the admin password / site settings

Admin → Settings: verify current password (`password_verify`), new ≥ 8 chars,
`password_hash()` saved with file locking; the hash syncs back to
`storage/orders/<id>.json` so republishing preserves it. Site name/logo/contact
email/phone/address live in `admin/site.json` (never mixed with `.auth.json`).

### Publishing and republishing

`publish.php → do_publish` installs the base panel, then the required kit
(helpers, per-entity pages, inbox/settings/api/installer), `submit.php`, and
form wiring; preserves records/credentials/settings; records failures in
`meta.json` (`publish_status`, `publish_errors`) instead of failing silently.

### Troubleshooting JSON permissions and storage failures

- Ensure the web user can write `published/<slug>/admin/` and
  `storage/collections/`. Malformed JSON is quarantined to
  `data_<entity>.json.corrupt-<timestamp>` (never deleted) and reads as empty
  with an error-log entry. Frontend shows an error when storage fails.
- Diagnose: `tests/test_published_admin_kit.php` exercises the whole flow
  against a temp site and reports exactly what broke.

### Securing the published admin directory in production

- Serve over HTTPS. `published/.htaccess` and `preview/.htaccess` deny direct
  HTTP access to dotfiles and raw JSON tables (`.auth.json`, `data_*.json`,
  `meta.json`, hit counters) — PHP pages and the live site are unaffected.
  Delete or block `install.php` after setup, use strong unique passwords, and
  back up `published/<slug>/admin/data_*.json` regularly.

---

## 🧪 Pre-publish development environment (works BEFORE Publish)

> Rule: **BEFORE PUBLISH = fully functional development preview.
> AFTER PUBLISH = the same tested functionality, deployed.**
> Publishing deploys tested code — it never activates basic features.

### Project architecture (preview paths)

```
preview/<32hex-draft-token>/          # one isolated workspace per draft
├── index.html                        # draft site, REAL URL (real PHP backend)
├── contact.php                       # real receiver → preview inbox JSON
├── submit.php                        # real universal handler → preview JSON
├── api/business-ops.php              # bridge → root JSON backend (preview slug)
├── draft.json                        # draft meta + evolving entity manifest
└── admin/                            # full kit admin on PREVIEW data
    ├── login.php / includes/{auth,db,layout}.php
    ├── index.php (real counts) / <entity>.php (CRUD, 20/page) / inbox.php
    ├── settings.php (+ site.json) / api.php / install.php / manifest.json
    └── data_<entity>.json            # preview JSON tables (test records)
```

### How preview mode works

1. Generate a design in `builder.php`, open the workspace.
2. Click the **🔐 Admin Panel** tab (or 🌐 Frontend Site tab): the builder
   provisions `preview/<draft>/` in the background (`POST api/preview-env.php`
   `action=provision` with the current HTML + entities + optional admin creds)
   and loads the REAL preview admin login / live site URL in the frame.
3. First build shows a one-time generated admin password (copied to clipboard);
   use your own wizard credentials to keep a stable login. Lost it? Admin bar
   → **🔑 Password** resets it (data untouched).
4. Rebuilds (admin bar → **↻ Rebuild**, design switches, tab re-syncs) rewrite
   code only — records, credentials, and settings are preserved. Features added
   via AI are union-merged, never dropped on rebuild.
5. The admin bar shows a live **readiness badge** (`✅ Ready to publish` or
   failing checks) backed by `action=readiness`. **✅ Checklist** opens the
   full pre-publish checklist (below).

### Customer preview: shareable URL, gate, and checklist

- **Preview Website button** (admin bar → `↗ Preview Website`, or workspace
  “open in new tab”): opens the full generated site in a **separate browser
  tab** on a real URL — all pages, navigation, forms, search, and
  AI-generated functions work against the preview backend. No builder login
  is needed to view it.
- **Customer share link**: the preview URL (`/preview/<32hex>/`) is
  unguessable and shareable — customers open it in any browser/device and
  test everything without builder access or admin credentials. The preview
  admin panel always requires its own login.
- **Access controls** (`POST api/preview-env.php {action:share|revoke}`):
  links live 14 days from each build (auto-extended while you work);
  `share` sets/extends expiry (1–90 days), `revoke` immediately returns
  HTTP 410 for site + admin while keeping all data (re-share anytime).
- **Gate** (`preview/<id>/gate.php` + `.htaccess`): every request is checked
  for revocation/expiry, private files (`.auth.json`, `data_*.json`,
  `draft.json`, counters) are never served (verified live by the readiness
  `privacy` probe: any HTTP 200 on a private file is a FAIL), static assets
  are served with safe MIME + no-store, and local PHP executes in place.
  Raw `.json`/dotfiles are additionally denied at the Apache layer, and the
  same guard ships as `published/.htaccess`.
- **Known hosting limitation**: share links work wherever this server is
  reachable. On a localhost dev install they open on the same machine (other
  devices need LAN/VPN access to it); on a hosted domain they work anywhere.
  Nothing in the preview exposes builder internals, credentials, or other
  customers' data — each env only ever reads its own directory + collections.
- **Pre-publish checklist** (admin bar → **✅ Checklist**, or auto-badge):
  share link + copy/extend/revoke, then one row per readiness check with
  PASS/FAIL/WARN + detail, plus an explicit *untested* list (AI lanes, real
  email delivery, online payment gateways). **Re-run tests** after fixes.
- **Publish warning**: Save & Publish runs readiness first — failing critical
  checks pop a confirm dialog listing them (`Publish anyway?`). The publish
  done screen then shows the data-migration summary and post-deploy preflight
  result from `meta.json`, so deployment is verified, not assumed.

### How preview JSON data is stored

- Preview tables: `preview/<id>/admin/data_<entity>.json`, mirrored to
  `storage/collections/<id>/` (outside the web root). Draft `<id>` is the
  unguessable 32-hex draft token, so envs are mutually isolated and separate
  from `published/<slug>/` production data.
- Same helpers as production (`includes/db.php`: flock + atomic writes, unique
  ids, `created_at`/`updated_at`, manifest-validated entities). Tables seed
  EMPTY — sample rows are never faked; create real records instead.
- Persistence: plain files — refreshes, navigation, reopening the draft, AI
  feature adds, and rebuilds all keep the data. Max 30 envs are kept (oldest
  evicted); nothing expires by age.

### How public forms connect to PHP backend handlers (preview AND production)

- Contact-looking forms → `contact.php` (validates, rate-limits, saves to the
  env's inbox JSON, queues mail).
- Business forms (`data-wc-form`, bookings/orders/…) → `submit.php`
  (manifest-validated, honeypot + 20/hour/IP + size caps, flock writes).
- Storefront runtime (`WCBiz.*`) → `api/business-ops.php` bridge → root
  `api/business-ops.php` with the env slug (holds, creates, ticket lookup,
  pay, cancel — same backend both envs).
- Every handler returns genuine success/error JSON only after the write
  settles; failures never report fake success.

### How data appears in the admin panel

Same pages both envs: `<entity>.php` tables (search, 20/page, edit loads real
values, POST+CSRF delete), `inbox.php` (read/unread, `mailto:` reply),
dashboard counts/unread/pending computed live from JSON, latest-5 activity.

### How to add a new business entity

Edit `business.json`/order `admin_entities`
(`[{id|name, label, icon, fields:[{name,label,type,required,options}]}]`),
reprovision/republish — or ask the AI. Supported types: text, textarea,
number, email, url, date, time, datetime, tel, select, checkbox. New entities
appear in sidebar, dashboard, CRUD pages, and API without hardcoding.

### How AI feature generation updates the application

- Pre-publish: workspace **＋ Add Function** (or AI chat in admin mode) →
  `window.AIAdminGenerator.generate` → `POST api/preview-env.php`
  `action=add_feature` → validates paths, merges entities into `draft.json` +
  `admin/manifest.json` (dedupe by id), creates data files + CRUD pages,
  refreshes nav, preserves records → test immediately in the preview admin.
- Post-publish: `site-manager.php → add_feature` does the same against
  `published/<slug>/`. Returned SQL is always ignored (PHP + JSON stack).

### How to test features before publishing

- Use the preview site + admin: submit forms, check the right admin section,
  CRUD/search/pagination, settings, password change, AI-added pages.
- Admin bar readiness badge + `POST api/preview-env.php {action:readiness}`:
  env/files/manifest/auth/pages/data/forms-wiring/no-mock/storage checks —
  nothing auto-passes; each lists pass/fail + detail.

### How production publishing works

`publish.php?action=do_publish&order_id=…&draft_id=…&migrate_preview=catalog|all`
(finalize step passes the draft id automatically; `catalog` is default):
1. Merges preview-tested draft entities into the order (AI-added features ship).
2. Installs the same base panel + kit + handlers into `published/<slug>/`.
3. Migrates data under an explicit policy (`preview_migrate`): catalog rows are
   copied into missing files or merged additively (new ids only); existing
   production files are never overwritten; transactional rows
   (bookings/inquiries/orders/…) are NEVER auto-copied (`all` copies them only
   into missing files on explicit request). Report → `meta.json`.
4. Writes `meta.json` with `admin_login`, `preview_draft`, `data_migration`,
   and a post-deploy `preflight` check (warn-only, never silent).

### How production data and credentials are preserved

Republishes only refresh code; `.auth.json`, `site.json`, `content.json`, and
all `data_*.json` rows survive (verified by test). Password changes sync back
to the order hash so republishes keep them.

### How to troubleshoot failed form submissions and JSON writes

- Run `C:\php\php.exe tests/test-preview-env.php` (79 live checks: provision →
  forms → admin CRUD → AI feature → readiness → migration → full publish +
  republish) — it names the broken layer.
- Check `meta.json` `publish_errors` / `preflight` / `data_migration`.
- Ensure the web user can write `preview/<id>/admin/` and
  `storage/collections/`; malformed JSON is quarantined to
  `*.corrupt-<timestamp>` (never deleted).
- `do_publish` allows 2-minute client timeouts server-side and every step is
  idempotent — a second run converges safely.
