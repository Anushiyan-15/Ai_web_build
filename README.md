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
│   │   ├── main.css                  # Core design system styles
│   │   └── legacy.css                # Archived prototype stylesheet
│   ├── js/
│   │   ├── main.js                   # Application utilities
│   │   └── legacy.js                 # Archived prototype script
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
- **Regression test**: `php test-taste.php` (33 checks, must all pass).
