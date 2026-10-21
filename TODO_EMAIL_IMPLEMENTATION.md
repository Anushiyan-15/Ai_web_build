# Owner Email Implementation — DONE

## Phase 1: Core credential infrastructure
- [x] Created config/owner-email.php — encryption key config (env/git-ignored)
- [x] Created includes/OwnerEmail.php — AES-256-CBC encrypt/decrypt, store/load per-slug, build PHPMailer (SMTP + OAuth), test send, public-safe summary
- [x] Created api/owner-email.php — wizard endpoints: save, test, load, status, delete (CSRF + owner-only + rate limit on test)

## Phase 2: Builder wizard UI
- [x] Added ui-lang.php keys for Business Email Setup section
- [x] Modified builder.php Step 4 — Business Email Setup section (email, provider, auth method, app password, SMTP host, from name, test button, provider guides, status summary)
- [x] Wired JS save + test endpoints + localStorage persistence (password NOT persisted locally)

## Phase 3: Publish pipeline
- [x] Modified publish.php create_order — encrypts smtp_pass at rest to storage/owner-email/<slug>.json; order JSON stores smtp_user + smtp_owner flag only (no password)
- [x] Modified publish.php do_publish — generates contact.php with slug only (no embedded credentials)
- [x] Modified includes/ContactReceiver.php — contact.php loads decrypted creds from storage/owner-email/<slug>.json at runtime; sends via owner's mailer; platform queue as reported fallback
- [x] Modified includes/PreviewEnv.php — contact.php via slug
- [x] Credentials never in published/public files

## Phase 4: Connect business email functions
- [x] includes/BusinessOps.php — booking confirmation (customer) via owner's mailer
- [x] includes/BusinessOps.php — new-booking notification (owner) via owner's mailer
- [x] includes/BusinessOps.php — order confirmation (customer) via owner's mailer
- [x] includes/BusinessOps.php — enquiry/quotation notification (owner) via owner's mailer
- [x] Contact form: owner notification + customer auto-reply via owner's mailer

## Phase 5: Security
- [x] CSRF on all owner-email API endpoints
- [x] Rate limiting (5 test emails/hour/owner/slug) on test endpoint
- [x] Credentials never in logs/error messages (safe error messages only)
- [x] storage/ blocked by .htaccess; owner-email/ has its own Require all denied .htaccess
- [x] Owner-only access (authenticated session + CSRF)
- [x] Per-slug isolation (each website/owner independent)

## Phase 6: Tests
- [x] tests/test_owner_email_core.php — 23/23 PASS (encrypt/decrypt round-trip, save/load, summary masking, OAuth tokens, delete, mailer throws when unconfigured)
- [ ] Real email delivery — NOT tested (no SMTP credentials / mail-testing environment available). Cannot claim delivery works without live SMTP test.

## Files changed
- Created: config/owner-email.php, includes/OwnerEmail.php, api/owner-email.php, tests/test_owner_email_core.php
- Modified: config.php, includes/ui-lang.php, builder.php, publish.php, includes/ContactReceiver.php, includes/BusinessOps.php, includes/PreviewEnv.php, .gitignore

## Platform email
UNCHANGED — config/mail.php, includes/Mailer.php (Mailer::getInstance), includes/db.php (sendEmailOtp) all untouched. WebCraft AI platform emails keep using their existing global sender.
