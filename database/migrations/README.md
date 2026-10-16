# Database Migrations — Storage Quota System (001)

## Files

| File | Driver |
|---|---|
| `001_storage_quota_pgsql.sql` | Supabase PostgreSQL (primary) |
| `001_storage_quota_mysql.sql` | Local MySQL 8 fallback |
| `migrate.php` | CLI runner (picks the file matching the live driver) |

## Run

From the project root:

```bash
php database/migrations/migrate.php            # apply pending migrations
php database/migrations/migrate.php --status   # show applied / pending
```

Or apply the SQL file directly with `psql` / `mysql` CLI. The runner is
preferred because it also performs the data backfill below.

Web access to `migrate.php` is refused (CLI-only guard).

## What 001 does (both drivers, idempotent — safe to re-run)

1. Creates `schema_migrations` ledger.
2. Creates `customers` only if missing (Supabase already has it; local
   MySQL did not). Never alters existing customer data columns.
3. Adds `plan_id` (FK → `plans.id`, `ON DELETE SET NULL`),
   `status` (`active`/`suspended`), `suspended_at`, `suspended_reason`
   to `customers` — guarded `ADD COLUMN IF NOT EXISTS` (pgsql) or
   `INFORMATION_SCHEMA`-checked helper procedures (MySQL, which has no
   `ADD COLUMN IF NOT EXISTS`).
4. Creates `plans` (relational source of truth for quotas / per-GB rates)
   and seeds `free, starter, basic, pro, business, agency`.
   `starter/pro/business` slugs are preserved for backwards compatibility
   with existing orders. Re-runs use `ON CONFLICT DO NOTHING` /
   `INSERT IGNORE`, so admin-edited rows are never overwritten.
5. Creates `customer_usage` (one row per customer email, unique),
   `files` (tracked quota-consuming assets), `storage_transactions`
   (append-only audit ledger, `payment_reference` unique for payment
   idempotency).
6. Backfill (`migrate.php` only, idempotent, never overwrites):
   - Order emails (`admin_email`/`client_email`) missing from `customers`
     are provisioned with an **unusable random bcrypt hash**. No usable
     password is invented; owners activate via the normal
     forgot-password reset flow (`setCustomerPassword()` already migrates
     legacy order emails).
   - `customers.plan_id` is set from the latest order `package`
     (`starter/pro/business` mapped to the same slugs, unknown → `free`)
     only where currently `NULL`.
   - Missing `customer_usage` rows are created with a fresh 30-day
     billing period.

## Rollback considerations

There is no automatic "down" migration (destructive rollbacks risk data
loss and are intentionally not automated):

- To revert cleanly on an empty/dev database, drop in dependency order:
  `storage_transactions`, `files`, `customer_usage`, `plans` (set
  `customers.plan_id = NULL` first), then drop the added `customers`
  columns, then `DELETE FROM schema_migrations WHERE version='001_storage_quota'`.
- On production, prefer **disabling** plans (`is_active = false`) and
  suspending enforcement over dropping tables: `storage_transactions`
  is a financial audit ledger and must be retained.

## Cron

Nightly recalculation is separate from migrations:

```bash
# crontab (server local time, 02:30 daily)
30 2 * * * C:\UniServerZ\core\php83\php.exe C:\UniServerZ\www\project\webbbuilder\cron\recalculate-storage.php >> C:\UniServerZ\www\project\webbbuilder\storage\logs\storage-cron.log 2>&1
# Linux example:
# 30 2 * * * /usr/bin/php /var/www/webbbuilder/cron/recalculate-storage.php >> /var/www/webbbuilder/storage/logs/storage-cron.log 2>&1
```

See `cron/recalculate-storage.php` for the CLI-only guard and summary output.
