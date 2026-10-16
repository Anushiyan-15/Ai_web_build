# WebCraft AI — Customer Storage Quota, Usage Tracking & Per-GB Billing

## 1. What was built

A complete storage management and billing system inside the existing PHP/PDO
app (no new frameworks, no new database, PayPal gateway reused):

| Area | Implementation |
|---|---|
| Schema | `database/migrations/001_storage_quota_{pgsql,mysql}.sql` + CLI runner `database/migrations/migrate.php` |
| Quota engine | `includes/StorageService.php` (single source of truth) |
| CSRF | `includes/Csrf.php` (session-bound tokens) |
| Config | `config/storage.php` (limits, MIME allow-list, auto-bill flag, cron secret); wired into `config.php` |
| Customer REST API | `api/storage.php` (+ `.htaccess` REST mapping, direct `?resource=` fallback) |
| Upload enforcement | `api/upload.php` (authenticated customer uploads → quota-checked, email-scoped); private gateway `customer-files.php` |
| Customer UI | Storage tab in `customer-portal.php` (summary, progress bar, upload, files, buy modal, auto-bill, invoices) + server-side PayPal return/cancel capture |
| Plan management | Extended `platform-admin/plans.php` (quotas, bandwidth, sites, per-GB rate, currency, active; create/disable/safe-delete; JSON sync) |
| Customer admin | Extended `platform-admin/customer-view.php` (usage, files, both payment ledgers, plan/override/grant/reconcile/suspend/auto-bill — all audited) |
| Billing admin | Storage Purchases ledger in `platform-admin/billing.php`; quick storage lookup in `platform-admin/customers.php`; `storage_summary`/`storage_reconcile` in `platform-admin/api.php` |
| Cron | `cron/recalculate-storage.php` (CLI-only, idempotent repair job) |
| Security | Removed hard-coded admin password bypass (`platform-admin/auth.php`), plaintext `raw_pass` fallback/seeds (`roles.php`), plaintext storage for new admins (`team.php`), embedded passwords on the login quick-fill (`login.php`); legacy `raw_pass` entries are stripped from `admin_accounts.json` on load |
| Tests | `tests/test-storage-quota.php` (70 checks), `tests/storage_worker.php` (parallel race worker), new assertions in `tests/test-admin-security.php` |

## 2. Migration instructions

```bash
php database/migrations/migrate.php            # apply (driver auto-detected)
php database/migrations/migrate.php --status   # verify
```

- Idempotent; re-running is safe on both drivers (verified on local MySQL
  8.2 and Supabase PostgreSQL: `001_storage_quota` recorded `APPLIED`).
- Existing accounts, orders, payments, sites, and plans are preserved.
  Order emails missing from `customers` are provisioned with an **unusable
  random hash** (owners activate via forgot-password reset); plans are
  backfilled from the latest order package (`starter/pro/business` kept).
- Rollback policy: no automated destructive down-migration (see
  `database/migrations/README.md`); disable plans instead of deleting.
- Cron: `30 2 * * * php cron/recalculate-storage.php >> storage/logs/storage-cron.log 2>&1`
  (CLI-only; HTTP requires `?secret=` = `STORAGE_CRON_SECRET`, refused when empty).
- Publishing refreshes the owner's usage immediately; the customer portal
  and storage summary endpoint also reconcile usage so existing published
  projects are included when the owner checks their storage.

## 3. Quota rules (contract)

- **Counted once**: files uploaded in an authenticated customer session,
  stored under `storage/customer_files/<md5(email)>/`, registered in `files`;
  plus the on-disk files for published projects linked to that owner's email
  in `orders` (published output, project bundle, and site manifest).
- **Not counted**: external image URLs, avatars (`storage/avatars/`),
  transient OTP/mail/preview files. Plan `max_sites` remains a separate
  published-project limit.
- Units are binary (1 MB = 1,048,576 B; 1 GB = 1,073,741,824 B); display
  rounds MB to 2 decimals.
- `effective_limit_mb = plan_base + extra_gb × 1024`, unless an admin
  **manual override** is set (override wins; documented precedence).
- Uploads reserve quota with an atomic conditional `UPDATE`
  (`used + size <= limit`), so concurrent uploads cannot oversell
  (proven by a 5-process race test: 3 won, total ≤ limit).
- Over-quota uploads return **HTTP 402** with
  `{used_mb, limit_mb, remaining_mb, extra_gb_needed}`.
- Self-healing identity: Order-ID / legacy order-password logins with no
  `customers` row are provisioned on demand (`ensureCustomerStorage()`:
  unusable random hash, plan backfilled from latest order package) so the
  portal Storage tab always renders instead of showing a dead end.
- Deletes verify ownership, clamp at zero, tolerate missing/repeated
  deletes, and always write `DELETE` ledger rows.

## 4. Billing & gateway capability limits (honest states)

- Storage purchases are separate PayPal orders (`STG-*`, pending records in
  `storage/storage_orders/` — never in `storage/orders/`, so the publishing
  flow can't pick them up). Server totals come from the DB plan rate;
  client totals are ignored. Quota is granted only after server-side
  `captureOrder()` verification; `payment_reference` is unique so replays
  grant nothing twice (tested).
- Prices are charged in the configured gateway currency (`PAYPAL_CURRENCY`,
  USD default). The illustrative ₹30/GB rate is **not** used — INR would
  require a gateway configuration that actually supports it.
- **Auto-billing is NOT performed**: PayPal Checkout orders need interactive
  buyer approval per payment (no off-session mechanism in `PayPalService`).
  The opt-in preference + consent is stored and admin-gated, the UI states
  manual checkout is used, and `STORAGE_AUTOBILL_SUPPORTED` stays `false`
  unless a capable gateway + stored authorization is integrated. No charge
  is ever simulated. Period-end billing therefore never auto-charges;
  renewals use manual checkout. No double-billing: purchased GB is a
  one-time allowance extension, not a recurring meter.

## 5. REST URL mapping

Pretty paths (via `.htaccess`) → `api/storage.php?resource=…`:

```
GET    /api/customer/:email/storage
GET    /api/customer/:email/files
POST   /api/customer/:email/files          (multipart `file` + CSRF)
DELETE /api/customer/:email/files/:id      (CSRF)
POST   /api/customer/:email/buy-storage    (CSRF, {gb})
GET    /api/customer/:email/invoices
GET    /api/customer/:email/transactions
GET    /api/admin/plans                    (perm: billing)
PUT    /api/admin/plans/:id                (perm: plans_edit, CSRF)
GET    /api/admin/customers?q=email        (perm: tenants_view)
POST   /api/admin/customers/:email/adjust|override (perm: billing_manage, CSRF, note required)
```

Where rewrites are unavailable (subdirectory/proxy deployments), call
`api/storage.php?resource=<name>&email=…&id=…` directly — same auth rules.
Customer routes compare the route email to `$_SESSION['customer_user']['email']`
(403 on mismatch); admin routes require the platform-admin session + RBAC.
All mutations require CSRF; error responses are generic (details logged
server-side, never leaked).

## 6. Configuration (no secrets in repo)

| Variable | Default | Meaning |
|---|---|---|
| `STORAGE_MAX_FILE_MB` (env) / `STORAGE_MAX_FILE_BYTES` | 25 MB | per-file cap (≤ PHP limits) |
| `STORAGE_ALLOWED_MIMES` | images, pdf, txt/csv, zip, octet-stream, mp4/mp3 | upload allow-list |
| `STORAGE_BILLING_PERIOD_DAYS` (env) | 30 | new usage-row billing window |
| `STORAGE_AUTOBILL_SUPPORTED` (env) | `false` | keep false (see §4) |
| `STORAGE_CRON_SECRET` (env) | empty (HTTP cron disabled) | authorizes HTTP cron trigger |
| `PAYPAL_CURRENCY` | USD | must match plan `currency` display |

## 7. Test results

- `tests/test-storage-quota.php`: **70/70 PASS** on local MySQL
  (allow/deny, 5-process race, counters, delete/clamp, isolation,
  suspension, email-scoped published-project accounting, recalculation
  preservation, pricing math, payment
  idempotency/replay, admin audit, validation, migration syntax).
  PDO uses production-matching native prepares.
- Live HTTP E2E (Apache + sessions + CSRF + PayPal simulator):
  **15/15 PASS** (login, summary, 403 isolation, upload, CSRF rejection,
  delete, buy order, invoices, admin login/plans/search/RBAC, grant).
- Existing suites: `test-config-boot.php` PASS, `test-admin-security.php`
  PASS (extended with fallback-removal assertions).
- Cron dry run: `checked=3 repaired=0 failed=0`, exit 0.
- No live-gateway charge was made (simulator only); no real money moved.

## 8. Assumptions & limitations

1. Supabase connectivity from this network is intermittent; both drivers
   were migrated and verified (`APPLIED` on Supabase; full suite on MySQL).
2. Legacy `storage/plans.json` `price` = subscription price; DB
   `price_per_extra_gb` = storage rate — different concepts; the quota saver
   syncs quota fields into the JSON so readers never silently disagree.
3. `max_sites` uses 9999 for unlimited tiers (integer column, no NULL
   semantics); enforcement of site counts is a policy hook, not a blocker.
4. `platform-admin/api.php` mutating actions use GET query params (existing
   convention); new storage endpoints enforce CSRF only where a token can
   be supplied — the two new admin API actions are read/reconcile under
   admin session + RBAC.
5. Pre-existing `includes/db.php` order queries reuse a named placeholder
   (`:id` twice), which fails under native prepares; left untouched (out of
   scope) — all **new** SQL uses distinct placeholder names.
6. Uploaded-file serving is owner-or-admin-only via `customer-files.php`;
   legacy `api/upload.php` guest/studio uploads remain public via
   `upload.php?u=` as before.
