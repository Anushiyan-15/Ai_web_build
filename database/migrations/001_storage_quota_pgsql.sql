-- ═══════════════════════════════════════════════════════════════
-- WebCraft AI – Storage Quota Migration 001 (PostgreSQL / Supabase)
-- Idempotent: safe to run multiple times.
-- Run via: database/migrations/migrate.php  (recommended) or psql.
-- Rollback notes: see database/migrations/README.md (no destructive
-- rollback is performed automatically; see "down" guidance there).
-- ═══════════════════════════════════════════════════════════════

-- 0. Migration ledger (shared by all migrations)
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(50) PRIMARY KEY,
    applied_at TIMESTAMPTZ DEFAULT NOW()
);

-- 0b. Ensure base customers table exists (Supabase already has it; this
-- is a no-op there). Shape mirrors database/supabase_schema.sql plus the
-- new storage columns so fresh installs also work.
CREATE TABLE IF NOT EXISTS customers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) DEFAULT '',
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT '',
    avatar TEXT DEFAULT '',
    plan_id BIGINT NULL,
    status VARCHAR(20) DEFAULT 'active',
    suspended_at TIMESTAMPTZ NULL,
    suspended_reason TEXT NULL,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_customers_email ON customers(email);

-- 1. Extend existing customers table (never recreated, never dropped)
ALTER TABLE customers ADD COLUMN IF NOT EXISTS plan_id BIGINT NULL;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMPTZ NULL;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS suspended_reason TEXT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_customers_plan_id'
    ) THEN
        ALTER TABLE customers
            ADD CONSTRAINT fk_customers_plan_id
            FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL DEFERRABLE INITIALLY DEFERRED;
    END IF;
EXCEPTION WHEN undefined_table THEN
    -- plans table is created below; constraint added at the end instead
    NULL;
END $$;

CREATE INDEX IF NOT EXISTS idx_customers_plan_id ON customers(plan_id);
CREATE INDEX IF NOT EXISTS idx_customers_status ON customers(status);

-- 2. Relational plans table (source of truth for quotas / per-GB rates)
CREATE TABLE IF NOT EXISTS plans (
    id BIGSERIAL PRIMARY KEY,
    slug VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    storage_limit_mb INTEGER NOT NULL DEFAULT 250 CHECK (storage_limit_mb >= 0),
    bandwidth_limit_mb INTEGER NOT NULL DEFAULT 5120 CHECK (bandwidth_limit_mb >= 0),
    max_sites INTEGER NOT NULL DEFAULT 1 CHECK (max_sites >= 0),
    price_per_extra_gb NUMERIC(10,2) NOT NULL DEFAULT 2.00 CHECK (price_per_extra_gb >= 0),
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Attach FK now that plans exists (guarded, idempotent)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_customers_plan_id'
    ) THEN
        ALTER TABLE customers
            ADD CONSTRAINT fk_customers_plan_id
            FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL;
    END IF;
END $$;

-- Seed defaults (stable slugs; starter/pro/business preserved for BC).
-- Quotas / prices remain editable by platform admins afterwards.
INSERT INTO plans (slug, name, storage_limit_mb, bandwidth_limit_mb, max_sites, price_per_extra_gb, currency, is_active)
VALUES
    ('free',     'Free',     100,    512,     1,  2.00, 'USD', TRUE),
    ('starter',  'Starter',  250,    5120,    1,  2.00, 'USD', TRUE),
    ('basic',    'Basic',    500,    10240,   2,  2.00, 'USD', TRUE),
    ('pro',      'Pro',      2048,   51200,   3,  2.00, 'USD', TRUE),
    ('business', 'Business', 5120,   512000,  9999, 1.50, 'USD', TRUE),
    ('agency',   'Agency',   20480,  1048576, 9999, 1.00, 'USD', TRUE)
ON CONFLICT (slug) DO NOTHING;

-- 3. Per-customer usage counters (one row per customer email)
CREATE TABLE IF NOT EXISTS customer_usage (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    storage_used_bytes BIGINT NOT NULL DEFAULT 0 CHECK (storage_used_bytes >= 0),
    bandwidth_used_bytes BIGINT NOT NULL DEFAULT 0 CHECK (bandwidth_used_bytes >= 0),
    extra_gb_purchased INTEGER NOT NULL DEFAULT 0 CHECK (extra_gb_purchased >= 0),
    manual_storage_limit_mb INTEGER NULL CHECK (manual_storage_limit_mb IS NULL OR manual_storage_limit_mb >= 0),
    billing_period_start DATE NULL,
    billing_period_end DATE NULL,
    last_calculated_at TIMESTAMPTZ NULL,
    auto_bill_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_usage_customer FOREIGN KEY (email)
        REFERENCES customers(email) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_usage_email ON customer_usage(email);

-- 4. Tracked customer files (quota-consuming assets)
CREATE TABLE IF NOT EXISTS files (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path TEXT NOT NULL UNIQUE,
    file_path_hash CHAR(64) NULL UNIQUE,
    file_size_bytes BIGINT NOT NULL CHECK (file_size_bytes >= 0),
    mime_type VARCHAR(128) DEFAULT '',
    uploaded_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT fk_files_customer FOREIGN KEY (email)
        REFERENCES customers(email) ON UPDATE CASCADE ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_files_email ON files(email);
CREATE INDEX IF NOT EXISTS idx_files_uploaded ON files(uploaded_at);
ALTER TABLE files ADD COLUMN IF NOT EXISTS file_path_hash CHAR(64) NULL;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'uq_files_path_hash') THEN
        ALTER TABLE files ADD CONSTRAINT uq_files_path_hash UNIQUE (file_path_hash);
    END IF;
END $$;

-- 5. Append-only storage audit ledger
CREATE TABLE IF NOT EXISTS storage_transactions (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    action VARCHAR(20) NOT NULL CHECK (action IN ('UPLOAD','DELETE','PURCHASE','ADMIN_ADJUST')),
    size_bytes BIGINT NOT NULL DEFAULT 0,
    amount_charged NUMERIC(10,2) NULL,
    currency VARCHAR(10) NULL,
    payment_reference VARCHAR(128) NULL UNIQUE,
    note TEXT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_stx_email ON storage_transactions(email);
CREATE INDEX IF NOT EXISTS idx_stx_action ON storage_transactions(action);
CREATE INDEX IF NOT EXISTS idx_stx_created ON storage_transactions(created_at);

-- 6. Record migration
INSERT INTO schema_migrations (version) VALUES ('001_storage_quota')
ON CONFLICT (version) DO NOTHING;
