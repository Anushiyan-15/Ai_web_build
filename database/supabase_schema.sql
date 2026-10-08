-- ═══════════════════════════════════════════════════════════════
-- WebCraft AI – Supabase (PostgreSQL 17) Production Schema
-- Designed for Multi-Tenant Wildcard Domains & AI Website Builder
-- ═══════════════════════════════════════════════════════════════

-- ─── 1. Orders & Published Websites ───────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id BIGSERIAL PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL UNIQUE,
    paypal_order_id VARCHAR(100),
    paypal_capture_id VARCHAR(100),
    site_name VARCHAR(255) DEFAULT '',
    slug VARCHAR(100) DEFAULT '',
    subdomain VARCHAR(100) DEFAULT '',
    custom_domain VARCHAR(255),
    design_id VARCHAR(50) DEFAULT '1',
    gen_mode VARCHAR(30) DEFAULT 'static', -- 'static' | 'admin' | 'database'
    package VARCHAR(50) DEFAULT 'Pro',
    amount NUMERIC(10,2) DEFAULT 19.00,
    currency VARCHAR(10) DEFAULT 'USD',
    payment_method VARCHAR(50) DEFAULT 'paypal',
    payment_status VARCHAR(50) DEFAULT 'pending', -- 'pending' | 'completed' | 'refunded'
    status VARCHAR(50) DEFAULT 'payment_pending', -- 'received' | 'payment_pending' | 'published' | 'cancelled'
    site_active BOOLEAN DEFAULT TRUE,
    live_url TEXT,
    admin_url TEXT,
    published_slug VARCHAR(100),
    admin_username VARCHAR(100) DEFAULT '',
    admin_email VARCHAR(255) DEFAULT '',
    admin_password_hash VARCHAR(255) DEFAULT '',
    client_email VARCHAR(255) DEFAULT '',
    client_phone VARCHAR(50) DEFAULT '',
    next_payment_due DATE,
    last_reminder_sent TIMESTAMP WITH TIME ZONE,
    published_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_orders_order_id ON orders(order_id);
CREATE INDEX IF NOT EXISTS idx_orders_slug ON orders(slug);
CREATE INDEX IF NOT EXISTS idx_orders_subdomain ON orders(subdomain);
CREATE INDEX IF NOT EXISTS idx_orders_custom_domain ON orders(custom_domain);
CREATE INDEX IF NOT EXISTS idx_orders_site_active ON orders(site_active);
CREATE INDEX IF NOT EXISTS idx_orders_next_payment ON orders(next_payment_due);

-- ─── 2. Contact Messages & Support Inquiries ──────────────────
CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT '',
    phone VARCHAR(50) DEFAULT '',
    subject VARCHAR(255) DEFAULT 'Website Inquiry',
    message TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'open', -- 'open' | 'in_progress' | 'resolved'
    replied BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ─── 3. Customer Notifications ─────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id BIGSERIAL PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    type VARCHAR(50) DEFAULT 'info', -- 'payment_reminder' | 'warning' | 'info' | 'activation' | 'deactivation'
    subject VARCHAR(255) DEFAULT '',
    message TEXT NOT NULL,
    sent_by VARCHAR(100) DEFAULT 'platform_admin',
    email_sent BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notif_order ON notifications(order_id);

-- ─── 4. Subscription Payment Ledger ───────────────────────────
CREATE TABLE IF NOT EXISTS subscription_payments (
    id BIGSERIAL PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    payment_ref VARCHAR(100),
    amount NUMERIC(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    status VARCHAR(30) DEFAULT 'paid', -- 'paid' | 'failed' | 'refunded'
    gateway VARCHAR(50) DEFAULT 'paypal',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_sub_pay_order ON subscription_payments(order_id);

-- ─── 5. Platform Audit Activity Log ───────────────────────────
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id BIGSERIAL PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    order_id VARCHAR(50),
    detail TEXT,
    admin_user VARCHAR(100) DEFAULT 'superadmin',
    admin_role VARCHAR(50) DEFAULT 'superadmin',
    ip_address VARCHAR(45) DEFAULT '127.0.0.1',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_action ON admin_activity_log(action);
CREATE INDEX IF NOT EXISTS idx_audit_user ON admin_activity_log(admin_user);

-- ─── 6. Feature Additions (AI History) ─────────────────────────
CREATE TABLE IF NOT EXISTS feature_additions (
    id BIGSERIAL PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    feature_name VARCHAR(255) NOT NULL,
    feature_description TEXT NOT NULL,
    files_added JSONB DEFAULT '[]'::jsonb,
    status VARCHAR(30) DEFAULT 'active',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_features_order ON feature_additions(order_id);

-- ─── 7. Customer Accounts (Signup / Signin) ────────────────────
-- One row per customer email. Orders link via admin_email / client_email.
-- Password is bcrypt hash. Auto-provisioned on first publish if missing.
CREATE TABLE IF NOT EXISTS customers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) DEFAULT '',
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT '',
    avatar TEXT DEFAULT '',
    last_login_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_customers_email ON customers(email);

-- ─── 8. Customer Reviews & Ratings ───────────────────────────
-- One row per submitted review. Ownership keyed on customer_email
-- (canonical identity app-wide). Only status='approved' is public.
CREATE TABLE IF NOT EXISTS reviews (
    id BIGSERIAL PRIMARY KEY,
    customer_id VARCHAR(64) NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255) NOT NULL DEFAULT '',
    customer_role VARCHAR(120) NOT NULL DEFAULT '',
    rating SMALLINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_title VARCHAR(255) NOT NULL DEFAULT '',
    review_message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_reviews_email ON reviews(customer_email);
CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(status);
