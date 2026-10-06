-- ═══════════════════════════════════════════════════════════════
-- WebCraft AI – Production MySQL Database Schema
-- See also: database/schema.sql
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `webbuilder_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `webbuilder_db`;

-- ─── Orders & Subscriptions ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`         VARCHAR(50)  NOT NULL UNIQUE,
  `paypal_order_id`  VARCHAR(100) DEFAULT NULL,
  `paypal_capture_id`VARCHAR(100) DEFAULT NULL,
  `site_name`        VARCHAR(255) DEFAULT '',
  `design_id`        VARCHAR(50)  DEFAULT '1',
  `package`          VARCHAR(50)  DEFAULT 'Pro',
  `amount`           DECIMAL(10,2) DEFAULT 19.00,
  `currency`         VARCHAR(10)  DEFAULT 'USD',
  `payment_method`   VARCHAR(50)  DEFAULT 'paypal',
  `payment_status`   VARCHAR(50)  DEFAULT 'pending',
  `status`           ENUM('received','payment_pending','published','cancelled') DEFAULT 'payment_pending',
  `live_url`         VARCHAR(500) DEFAULT NULL,
  `published_slug`   VARCHAR(100) DEFAULT NULL,
  `client_email`     VARCHAR(255) DEFAULT '',
  `client_phone`     VARCHAR(50)  DEFAULT '',
  `created_at`       DATETIME     NOT NULL,
  `updated_at`       DATETIME     ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_paypal_order_id` (`paypal_order_id`)
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
