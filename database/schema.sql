-- ═══════════════════════════════════════════════════════════════
-- WebCraft AI – Production MySQL Database Schema
-- Run this once in phpMyAdmin or MySQL CLI
-- Updated: Platform Admin + Subscription Tracking + Notifications
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `webbuilder_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `webbuilder_db`;

-- ─── Orders & Subscriptions ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`            VARCHAR(50)  NOT NULL UNIQUE,
  `paypal_order_id`     VARCHAR(100) DEFAULT NULL,
  `paypal_capture_id`   VARCHAR(100) DEFAULT NULL,
  `site_name`           VARCHAR(255) DEFAULT '',
  `slug`                VARCHAR(100) DEFAULT '',
  `design_id`           VARCHAR(50)  DEFAULT '1',
  `gen_mode`            VARCHAR(20)  DEFAULT 'static',
  `package`             VARCHAR(50)  DEFAULT 'Pro',
  `amount`              DECIMAL(10,2) DEFAULT 19.00,
  `currency`            VARCHAR(10)  DEFAULT 'USD',
  `payment_method`      VARCHAR(50)  DEFAULT 'paypal',
  `payment_status`      VARCHAR(50)  DEFAULT 'pending',
  `status`              ENUM('received','payment_pending','published','cancelled') DEFAULT 'payment_pending',
  `site_active`         TINYINT(1)   DEFAULT 1,
  `live_url`            VARCHAR(500) DEFAULT NULL,
  `admin_url`           VARCHAR(500) DEFAULT NULL,
  `published_slug`      VARCHAR(100) DEFAULT NULL,
  `admin_username`      VARCHAR(100) DEFAULT '',
  `admin_email`         VARCHAR(255) DEFAULT '',
  `client_email`        VARCHAR(255) DEFAULT '',
  `client_phone`        VARCHAR(50)  DEFAULT '',
  `next_payment_due`    DATE         DEFAULT NULL,
  `last_reminder_sent`  DATETIME     DEFAULT NULL,
  `published_at`        DATETIME     DEFAULT NULL,
  `created_at`          DATETIME     NOT NULL,
  `updated_at`          DATETIME     ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_order_id`        (`order_id`),
  INDEX `idx_paypal_order_id` (`paypal_order_id`),
  INDEX `idx_admin_email`     (`admin_email`),
  INDEX `idx_site_active`     (`site_active`),
  INDEX `idx_next_payment`    (`next_payment_due`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Client Requirements (Quote Intake) ─────────────────────
CREATE TABLE IF NOT EXISTS `requirements` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`      VARCHAR(50)   NOT NULL UNIQUE,
  `client_name`   VARCHAR(255)  DEFAULT '',
  `client_email`  VARCHAR(255)  DEFAULT '',
  `company_name`  VARCHAR(255)  DEFAULT '',
  `business_type` VARCHAR(100)  DEFAULT '',
  `requirement`   TEXT          NOT NULL,
  `budget`        VARCHAR(50)   DEFAULT '',
  `timeline`      VARCHAR(50)   DEFAULT '',
  `ip_address`    VARCHAR(45)   DEFAULT '',
  `status`        ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `created_at`    DATETIME      NOT NULL,
  `updated_at`    DATETIME      ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Contact Messages ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(255) NOT NULL,
  `email`      VARCHAR(255) DEFAULT '',
  `phone`      VARCHAR(50)  DEFAULT '',
  `message`    TEXT         NOT NULL,
  `replied`    TINYINT(1)   DEFAULT 0,
  `created_at` DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Customer Notifications ─────────────────────────────────
-- Sent by platform admin: payment reminders, warnings, info msgs
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`    VARCHAR(50)  NOT NULL,
  `type`        ENUM('payment_reminder','warning','info','activation','deactivation') DEFAULT 'info',
  `subject`     VARCHAR(255) DEFAULT '',
  `message`     TEXT         NOT NULL,
  `sent_by`     VARCHAR(100) DEFAULT 'platform_admin',
  `email_sent`  TINYINT(1)   DEFAULT 0,
  `read_at`     DATETIME     DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL,
  INDEX `idx_notif_order` (`order_id`),
  INDEX `idx_notif_type`  (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Subscription Payment History ───────────────────────────
-- Track each monthly renewal payment per customer
CREATE TABLE IF NOT EXISTS `subscription_payments` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`      VARCHAR(50)  NOT NULL,
  `payment_ref`   VARCHAR(100) DEFAULT NULL,
  `amount`        DECIMAL(10,2) NOT NULL,
  `currency`      VARCHAR(10)  DEFAULT 'USD',
  `period_from`   DATE         NOT NULL,
  `period_to`     DATE         NOT NULL,
  `payment_method`VARCHAR(50)  DEFAULT 'paypal',
  `status`        ENUM('pending','paid','failed','overdue') DEFAULT 'pending',
  `paid_at`       DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL,
  INDEX `idx_subpay_order`  (`order_id`),
  INDEX `idx_subpay_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Platform Admin Activity Log ────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_activity_log` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `action`      VARCHAR(100) NOT NULL,
  `order_id`    VARCHAR(50)  DEFAULT NULL,
  `detail`      TEXT         DEFAULT NULL,
  `admin_user`  VARCHAR(100) DEFAULT 'superadmin',
  `ip_address`  VARCHAR(45)  DEFAULT '',
  `created_at`  DATETIME     NOT NULL,
  INDEX `idx_log_order`  (`order_id`),
  INDEX `idx_log_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── AI Feature Additions Log ───────────────────────────────
-- Tracks what new modules were added via AI Feature Adder
CREATE TABLE IF NOT EXISTS `feature_additions` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`         VARCHAR(50)  NOT NULL,
  `requirements`     TEXT         NOT NULL,
  `files_added`      INT          DEFAULT 0,
  `entities_added`   TEXT         DEFAULT NULL,
  `status`           ENUM('success','failed') DEFAULT 'success',
  `created_at`       DATETIME     NOT NULL,
  INDEX `idx_feat_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Customer Accounts (Signup / Signin) ──────────────────────
-- One row per customer email. Orders link via admin_email / client_email.
-- Auto-provisioned on first publish if missing.
CREATE TABLE IF NOT EXISTS `customers` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(255) DEFAULT '',
  `email`         VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone`         VARCHAR(50)  DEFAULT '',
  `avatar`        VARCHAR(500) DEFAULT '',
  `last_login_at` DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL,
  `updated_at`    DATETIME     ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cust_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
