-- ═══════════════════════════════════════════════════════════════
-- WebCraft AI – Storage Quota Migration 001 (MySQL 8 / local fallback)
-- Idempotent: safe to run multiple times (conditional ADD COLUMN via
-- helper procedure; CREATE TABLE IF NOT EXISTS throughout).
-- Run via: database/migrations/migrate.php  (recommended) or mysql CLI.
-- Rollback notes: see database/migrations/README.md.
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `version` VARCHAR(50) NOT NULL PRIMARY KEY,
    `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1. Relational plans table (source of truth for quotas / per-GB rates)
CREATE TABLE IF NOT EXISTS `plans` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(64) NOT NULL UNIQUE,
    `name` VARCHAR(128) NOT NULL,
    `storage_limit_mb` INT NOT NULL DEFAULT 250 CHECK (`storage_limit_mb` >= 0),
    `bandwidth_limit_mb` INT NOT NULL DEFAULT 5120 CHECK (`bandwidth_limit_mb` >= 0),
    `max_sites` INT NOT NULL DEFAULT 1 CHECK (`max_sites` >= 0),
    `price_per_extra_gb` DECIMAL(10,2) NOT NULL DEFAULT 2.00,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed defaults (stable slugs; starter/pro/business preserved for BC).
-- INSERT IGNORE keeps existing admin-edited rows untouched on re-run.
INSERT IGNORE INTO `plans`
    (`slug`, `name`, `storage_limit_mb`, `bandwidth_limit_mb`, `max_sites`, `price_per_extra_gb`, `currency`, `is_active`)
VALUES
    ('free',     'Free',     100,    512,     1,    2.00, 'USD', 1),
    ('starter',  'Starter',  250,    5120,    1,    2.00, 'USD', 1),
    ('basic',    'Basic',    500,    10240,   2,    2.00, 'USD', 1),
    ('pro',      'Pro',      2048,   51200,   3,    2.00, 'USD', 1),
    ('business', 'Business', 5120,   512000,  9999, 1.50, 'USD', 1),
    ('agency',   'Agency',   20480,  1048576, 9999, 1.00, 'USD', 1);

-- 1b. Ensure base customers table exists (no-op where it already exists).
-- Shape mirrors database/schema.sql plus the new storage columns.
-- (Placed after plans so the FK reference resolves on fresh installs.)
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT '',
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) DEFAULT '',
    `avatar` VARCHAR(500) DEFAULT '',
    `plan_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `suspended_at` DATETIME NULL DEFAULT NULL,
    `suspended_reason` TEXT NULL DEFAULT NULL,
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cust_email` (`email`),
    INDEX `idx_customers_plan_id` (`plan_id`),
    INDEX `idx_customers_status` (`status`),
    CONSTRAINT `fk_customers_plan_id` FOREIGN KEY (`plan_id`)
        REFERENCES `plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Per-customer usage counters (one row per customer email)
CREATE TABLE IF NOT EXISTS `customer_usage` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `storage_used_bytes` BIGINT NOT NULL DEFAULT 0,
    `bandwidth_used_bytes` BIGINT NOT NULL DEFAULT 0,
    `extra_gb_purchased` INT NOT NULL DEFAULT 0,
    `manual_storage_limit_mb` INT NULL DEFAULT NULL,
    `billing_period_start` DATE NULL DEFAULT NULL,
    `billing_period_end` DATE NULL DEFAULT NULL,
    `last_calculated_at` DATETIME NULL DEFAULT NULL,
    `auto_bill_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_usage_email` (`email`),
    CONSTRAINT `fk_usage_customer` FOREIGN KEY (`email`)
        REFERENCES `customers` (`email`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tracked customer files (quota-consuming assets)
CREATE TABLE IF NOT EXISTS `files` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` TEXT NOT NULL,
    `file_size_bytes` BIGINT NOT NULL DEFAULT 0,
    `mime_type` VARCHAR(128) DEFAULT '',
    `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_files_email` (`email`),
    INDEX `idx_files_uploaded` (`uploaded_at`),
    CONSTRAINT `fk_files_customer` FOREIGN KEY (`email`)
        REFERENCES `customers` (`email`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- MySQL cannot create a UNIQUE key on TEXT; enforce uniqueness on a
-- generated hash column instead (duplicate filenames stay allowed).
DROP PROCEDURE IF EXISTS `stg_add_unique_path_hash`;
DELIMITER $$
CREATE PROCEDURE `stg_add_unique_path_hash`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'file_path_hash'
    ) THEN
        ALTER TABLE `files`
            ADD COLUMN `file_path_hash` CHAR(64) NULL AFTER `file_path`,
            ADD UNIQUE KEY `uq_files_path_hash` (`file_path_hash`);
    END IF;
END$$
DELIMITER ;
CALL `stg_add_unique_path_hash`;
DROP PROCEDURE IF EXISTS `stg_add_unique_path_hash`;

-- 4. Append-only storage audit ledger
CREATE TABLE IF NOT EXISTS `storage_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `action` VARCHAR(20) NOT NULL,
    `size_bytes` BIGINT NOT NULL DEFAULT 0,
    `amount_charged` DECIMAL(10,2) NULL DEFAULT NULL,
    `currency` VARCHAR(10) NULL DEFAULT NULL,
    `payment_reference` VARCHAR(128) NULL DEFAULT NULL UNIQUE,
    `note` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_stx_email` (`email`),
    INDEX `idx_stx_action` (`action`),
    INDEX `idx_stx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Extend existing customers table (guarded; never recreated/dropped).
-- MySQL 8 has no ADD COLUMN IF NOT EXISTS, so use a helper procedure.
DROP PROCEDURE IF EXISTS `stg_add_customer_cols`;
DELIMITER $$
CREATE PROCEDURE `stg_add_customer_cols`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'plan_id'
    ) THEN
        ALTER TABLE `customers` ADD COLUMN `plan_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `password_hash`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'status'
    ) THEN
        ALTER TABLE `customers` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `plan_id`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'suspended_at'
    ) THEN
        ALTER TABLE `customers` ADD COLUMN `suspended_at` DATETIME NULL DEFAULT NULL AFTER `status`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'suspended_reason'
    ) THEN
        ALTER TABLE `customers` ADD COLUMN `suspended_reason` TEXT NULL DEFAULT NULL AFTER `suspended_at`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND INDEX_NAME = 'idx_customers_plan_id'
    ) THEN
        ALTER TABLE `customers` ADD INDEX `idx_customers_plan_id` (`plan_id`);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND INDEX_NAME = 'idx_customers_status'
    ) THEN
        ALTER TABLE `customers` ADD INDEX `idx_customers_status` (`status`);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND CONSTRAINT_NAME = 'fk_customers_plan_id'
    ) THEN
        ALTER TABLE `customers`
            ADD CONSTRAINT `fk_customers_plan_id` FOREIGN KEY (`plan_id`)
            REFERENCES `plans` (`id`) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;
CALL `stg_add_customer_cols`;
DROP PROCEDURE IF EXISTS `stg_add_customer_cols`;

-- 6. Record migration
INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('001_storage_quota');
