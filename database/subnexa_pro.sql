-- ==========================================================
-- SubNexa Pro - Enterprise Subscription & Recurring Billing
-- Database Architecture & Indian Seed Data (Odoo Hackathon)
-- Target DB: subnexa_pro (Indian Rupee ₹ & Indian Records)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `subnexa_pro` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `subnexa_pro`;

-- Disable foreign key checks for clean recreation
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `invoice_lines`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `subscription_lines`;
DROP TABLE IF EXISTS `subscriptions`;
DROP TABLE IF EXISTS `quotation_template_lines`;
DROP TABLE IF EXISTS `quotation_templates`;
DROP TABLE IF EXISTS `discount_rules`;
DROP TABLE IF EXISTS `tax_rules`;
DROP TABLE IF EXISTS `plans`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `users`;

-- ----------------------------------------------------------
-- 1. USERS TABLE (Role-Based: admin, staff, customer)
-- ----------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
  `phone` VARCHAR(30) NULL,
  `company_name` VARCHAR(150) NULL,
  `address` TEXT NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. PASSWORD RESETS TABLE (Token-based recovery)
-- ----------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reset_token` (`token`),
  INDEX `idx_reset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. PRODUCTS TABLE (Software, Service, Add-On)
-- ----------------------------------------------------------
CREATE TABLE `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `type` ENUM('software', 'service', 'add_on') NOT NULL DEFAULT 'software',
  `sales_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_product_type` (`type`),
  INDEX `idx_product_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. PRODUCT VARIANTS TABLE (e.g. Odoo Brand adds 560 to price)
-- ----------------------------------------------------------
CREATE TABLE `product_variants` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `attribute_name` VARCHAR(100) NOT NULL,
  `variant_value` VARCHAR(100) NOT NULL,
  `price_adjustment` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. PLANS TABLE (Daily, Weekly, Monthly, Yearly)
-- ----------------------------------------------------------
CREATE TABLE `plans` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `billing_frequency` ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL DEFAULT 'monthly',
  `cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `min_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `auto_close_on_end` TINYINT(1) NOT NULL DEFAULT 0,
  `can_pause` TINYINT(1) NOT NULL DEFAULT 1,
  `can_renew` TINYINT(1) NOT NULL DEFAULT 1,
  `available_from` DATE NULL,
  `available_to` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_plan_frequency` (`billing_frequency`),
  INDEX `idx_plan_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. TAX RULES TABLE (GST in India)
-- ----------------------------------------------------------
CREATE TABLE `tax_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `rate_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. DISCOUNT RULES TABLE (Fixed / Percentage, Caps, Limits)
-- ----------------------------------------------------------
CREATE TABLE `discount_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `discount_type` ENUM('fixed', 'percentage') NOT NULL DEFAULT 'percentage',
  `discount_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `min_purchase_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `min_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `max_uses` INT UNSIGNED NOT NULL DEFAULT 0,
  `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `applies_to` ENUM('entire_subscription', 'specific_product') NOT NULL DEFAULT 'entire_subscription',
  `product_id` INT UNSIGNED NULL,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_discount_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  INDEX `idx_discount_code` (`code`),
  INDEX `idx_discount_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. QUOTATION TEMPLATES & LINES
-- ----------------------------------------------------------
CREATE TABLE `quotation_templates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `validity_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_template_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quotation_template_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `default_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT `fk_tplline_template` FOREIGN KEY (`template_id`) REFERENCES `quotation_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tplline_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 9. SUBSCRIPTIONS TABLE (Full Lifecycle: Draft -> Closed)
-- ----------------------------------------------------------
CREATE TABLE `subscriptions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `subscription_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED NOT NULL,
  `created_by_staff_id` INT UNSIGNED NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `discount_id` INT UNSIGNED NULL,
  `status` ENUM('draft', 'quotation', 'confirmed', 'active', 'paused', 'closed') NOT NULL DEFAULT 'draft',
  `start_date` DATE NOT NULL,
  `expiry_date` DATE NULL,
  `next_billing_date` DATE NULL,
  `payment_terms` VARCHAR(100) NOT NULL DEFAULT 'Immediate Payment',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sub_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sub_staff` FOREIGN KEY (`created_by_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sub_discount` FOREIGN KEY (`discount_id`) REFERENCES `discount_rules` (`id`) ON DELETE SET NULL,
  INDEX `idx_sub_status` (`status`),
  INDEX `idx_sub_billing_date` (`next_billing_date`),
  INDEX `idx_sub_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 10. SUBSCRIPTION LINES (Products & Add-Ons)
-- ----------------------------------------------------------
CREATE TABLE `subscription_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `subscription_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `is_addon` TINYINT(1) NOT NULL DEFAULT 0,
  `is_addon_active` TINYINT(1) NOT NULL DEFAULT 1,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_id` INT UNSIGNED NULL,
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_subline_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subline_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_subline_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_subline_tax` FOREIGN KEY (`tax_id`) REFERENCES `tax_rules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 11. INVOICES TABLE (Billing Day Automated Generation)
-- ----------------------------------------------------------
CREATE TABLE `invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `subscription_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `billing_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `status` ENUM('draft', 'confirmed', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_inv_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_inv_cust` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_inv_status` (`status`),
  INDEX `idx_inv_due_date` (`due_date`),
  INDEX `idx_inv_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 12. INVOICE LINES TABLE
-- ----------------------------------------------------------
CREATE TABLE `invoice_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT UNSIGNED NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `variant_info` VARCHAR(150) NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_invline_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 13. PAYMENTS TABLE (Recording receipts against invoices)
-- ----------------------------------------------------------
CREATE TABLE `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_number` VARCHAR(50) NOT NULL UNIQUE,
  `invoice_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `recorded_by_id` INT UNSIGNED NULL,
  `payment_method` ENUM('credit_card', 'bank_transfer', 'upi', 'cash', 'cheque') NOT NULL DEFAULT 'upi',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE NOT NULL,
  `transaction_ref` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pay_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pay_staff` FOREIGN KEY (`recorded_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_pay_date` (`payment_date`),
  INDEX `idx_pay_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 14. AUDIT LOGS TABLE
-- ----------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================
-- SEED SAMPLE DATA (Realistic Indian Names, Rupees ₹ & GST)
-- Demo Password for all accounts: Demo@12345
-- Hash: $2y$10$KV4SaSy9g8usR3B/onawjewkvaSTwf05dTgox9EfkhQJ7PzEG9/JK
-- ==========================================================

-- 1. USERS (Indian Executives, Staff, and Enterprise Clients)
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `phone`, `company_name`, `address`, `status`) VALUES
(1, 'Aarav Sharma', 'admin@brightloop.in', '$2y$10$KV4SaSy9g8usR3B/onawjewkvaSTwf05dTgox9EfkhQJ7PzEG9/JK', 'admin', '+91-98200-11223', 'Brightloop Technologies India Pvt Ltd', 'Level 8, Cyber Park, Electronic City, Bengaluru, Karnataka 560100', 'active'),
(2, 'Priya Patel', 'staff@brightloop.in', '$2y$10$KV4SaSy9g8usR3B/onawjewkvaSTwf05dTgox9EfkhQJ7PzEG9/JK', 'staff', '+91-98201-44556', 'Brightloop Operations India', 'Floor 4, Nesco IT Park, Goregaon East, Mumbai, Maharashtra 400063', 'active'),
(3, 'Rohan Verma', 'rohan.sales@brightloop.in', '$2y$10$KV4SaSy9g8usR3B/onawjewkvaSTwf05dTgox9EfkhQJ7PzEG9/JK', 'staff', '+91-98202-77889', 'Brightloop Sales India', 'Cyber City, Tower B, DLF Phase 2, Gurugram, Haryana 122002', 'active'),
(4, 'Vikram Malhotra', 'client@apex.in', '$2y$10$KV4SaSy9g8usR3B/onawjewkvaSTwf05dTgox9EfkhQJ7PzEG9/JK', 'customer', '+91-98111-22334', 'Apex Dynamic Solutions Pvt Ltd', '702, One BKC, Bandra Kurla Complex, Bandra East, Mumbai, Maharashtra 400051', 'active'),
(5, 'Ananya Deshmukh', 'info@infotechcorp.in', '$2y$10$KV4SaSy9g8usR3B/onawjewkvaSTwf05dTgox9EfkhQJ7PzEG9/JK', 'customer', '+91-98222-33445', 'InfoTechCorp India Ltd', 'Plot 24, Rajiv Gandhi Infotech Park, Hinjewadi Phase 2, Pune, Maharashtra 411057', 'active'),
(6, 'Rajesh Gupta', 'contact@freshbites.in', '$2y$10$KV4SaSy9g8usR3B/onawjewkvaSTwf05dTgox9EfkhQJ7PzEG9/JK', 'customer', '+91-98333-44556', 'FreshBites Retail Chains India', '12, Barakhamba Road, Connaught Place, New Delhi 110001', 'active');

-- 2. PRODUCTS (Indian Software Suite & Add-Ons in Rupees ₹)
INSERT INTO `products` (`id`, `name`, `code`, `type`, `sales_price`, `cost_price`, `notes`, `is_active`) VALUES
(1, 'SubNexa ERP Enterprise (India Edition)', 'IND-ERP-PRO', 'software', 24999.00, 6500.00, 'Comprehensive Indian business ERP with GST, E-Way bills, multi-branch accounting, and inventory', 1),
(2, 'SubNexa CRM & GST Invoicing Suite', 'IND-CRM-GST', 'software', 8499.00, 2100.00, 'Lead lifecycle tracking, sales pipelines, WhatsApp business integration, and instant GST invoices', 1),
(3, 'Omnichannel E-Commerce & Retail POS', 'IND-POS-RETAIL', 'software', 12999.00, 3200.00, 'Integrated barcode scanning, UPI QR payments at counter, and unified marketplace inventory synchronizer', 1),
(4, 'Dedicated Technical Account Manager (India Support)', 'ADDON-TAM', 'add_on', 15999.00, 8000.00, 'Assigned senior software architect based in Bengaluru for custom integrations, audits, and SLA reviews', 1),
(5, '24/7 Priority SLA & Toll-Free Helpline Support', 'ADDON-SLA', 'add_on', 5999.00, 1500.00, 'Guaranteed 15-minute response SLA with dedicated Hindi and English toll-free phone escalation line', 1),
(6, 'Advanced GST E-Way & Real-Time BI Analytics', 'ADDON-BI', 'add_on', 3999.00, 800.00, 'Automated GST reconciliation, GSTR-1/GSTR-3B audit reports, and cohort financial health matrix', 1);

-- 3. PRODUCT VARIANTS (PDF Page 3: "Odoo brand adds 560 to the price" -> +₹560.00)
INSERT INTO `product_variants` (`id`, `product_id`, `attribute_name`, `variant_value`, `price_adjustment`) VALUES
(1, 1, 'Brand Edition', 'Standard Bharat Edition', 0.00),
(2, 1, 'Brand Edition', 'Odoo Brand Edition', 560.00),
(3, 1, 'Brand Edition', 'Tally-Prime Enterprise Integrated', 1200.00),
(4, 2, 'Cloud Hosting', 'Multi-Tenant Mumbai AWS Cloud', 0.00),
(5, 2, 'Cloud Hosting', 'Dedicated Private Instance (Mumbai Data Center)', 4500.00);

-- 4. PLANS (Indian Rupees ₹ - Daily, Weekly, Monthly, Yearly from Page 3 of PDF)
INSERT INTO `plans` (`id`, `name`, `code`, `billing_frequency`, `cost`, `min_quantity`, `auto_close_on_end`, `can_pause`, `can_renew`, `available_from`, `available_to`, `is_active`, `description`) VALUES
(1, 'Monthly Business Growth Plan', 'PLAN-M-GROWTH', 'monthly', 14999.00, 1, 0, 1, 1, '2026-01-01', '2027-12-31', 1, 'Billed every month with flexible pause support and GST compliance.'),
(2, 'Annual Enterprise Contract', 'PLAN-Y-ENTERPRISE', 'yearly', 149990.00, 1, 0, 1, 1, '2026-01-01', '2028-12-31', 1, 'Billed annually with two months complimentary, dedicated support and custom corporate branding.'),
(3, 'Weekly Startup Sprint Trial', 'PLAN-W-TRIAL', 'weekly', 4499.00, 1, 1, 0, 1, '2026-01-01', '2026-12-31', 1, '7-day sprint testing cycle for agile teams. Automatically closes on completion unless renewed.'),
(4, 'Daily High-Compute Cloud Burst', 'PLAN-D-COMPUTE', 'daily', 999.00, 1, 1, 0, 0, '2026-01-01', '2026-12-31', 1, 'On-demand compute burst billed daily for high-traffic sale days.');

-- 5. TAX RULES (Indian GST Framework)
INSERT INTO `tax_rules` (`id`, `name`, `rate_percentage`, `is_active`) VALUES
(1, 'Standard GST 18% (CGST 9% + SGST 9% / IGST)', 18.00, 1),
(2, 'IT Software TDS / Cess (10%)', 10.00, 1),
(3, 'SEZ Export Zero-Rated GST (0%)', 0.00, 1);

-- 6. DISCOUNT RULES (In Rupees ₹, Page 4 of PDF)
INSERT INTO `discount_rules` (`id`, `code`, `name`, `discount_type`, `discount_value`, `min_purchase_amount`, `min_quantity`, `max_uses`, `used_count`, `applies_to`, `product_id`, `start_date`, `end_date`, `is_active`) VALUES
(1, 'FESTIVE20', 'Diwali & Festive Seats Incentive (20% Off)', 'percentage', 20.00, 25000.00, 5, 100, 4, 'entire_subscription', NULL, '2026-01-01', '2026-12-31', 1),
(2, 'STARTUP5000', 'Startup India Kickstart (₹5,000 Flat Off)', 'fixed', 5000.00, 15000.00, 1, 50, 12, 'entire_subscription', NULL, '2026-01-01', '2026-12-31', 1),
(3, 'ERPBOOST', 'Make In India ERP Special (15% Off)', 'percentage', 15.00, 20000.00, 1, 30, 2, 'specific_product', 1, '2026-01-01', '2026-11-30', 1);

-- 7. QUOTATION TEMPLATES & LINES
INSERT INTO `quotation_templates` (`id`, `name`, `plan_id`, `validity_days`, `notes`) VALUES
(1, 'Bharat Enterprise Digital Suite', 2, 30, 'Pre-configured standard Indian enterprise ERP bundle with 24/7 priority SLA support.'),
(2, 'MSME Fast-Track Growth Bundle', 1, 14, 'Designed for fast closing for Indian small-to-mid businesses with CRM and Analytics.');

INSERT INTO `quotation_template_lines` (`id`, `template_id`, `product_id`, `default_quantity`) VALUES
(1, 1, 1, 1),
(2, 1, 5, 1),
(3, 2, 2, 1),
(4, 2, 6, 1);

-- 8. SUBSCRIPTIONS (In Rupees ₹)
INSERT INTO `subscriptions` (`id`, `subscription_number`, `customer_id`, `created_by_staff_id`, `plan_id`, `discount_id`, `status`, `start_date`, `expiry_date`, `next_billing_date`, `payment_terms`, `subtotal`, `tax_total`, `discount_total`, `total_amount`, `notes`) VALUES
(1, 'SUB-2026-0001', 4, 2, 1, 2, 'active', '2026-01-15', '2027-01-15', '2026-11-15', 'Net 15', 29558.00, 5320.44, 5000.00, 29878.44, 'Apex Dynamics flagship contract with Odoo Brand Edition (+₹560) and 24/7 Priority SLA.'),
(2, 'SUB-2026-0002', 5, 2, 2, 1, 'active', '2026-02-01', '2027-02-01', '2026-11-01', 'Net 30', 165989.00, 23902.02, 33197.80, 156693.22, 'InfoTechCorp India Annual Enterprise Contract with dedicated Bengaluru TAM.'),
(3, 'SUB-2026-0003', 6, 3, 1, NULL, 'quotation', '2026-10-25', '2026-11-08', NULL, 'Immediate Payment', 12498.00, 2249.64, 0.00, 14747.64, 'FreshBites Retail Chains quotation pending customer review and acceptance.'),
(4, 'SUB-2026-0004', 4, 3, 3, NULL, 'draft', '2026-10-30', '2026-11-06', NULL, 'Immediate Payment', 4499.00, 809.82, 0.00, 5308.82, 'Sales draft quote for Weekly Sprint testing of POS module.'),
(5, 'SUB-2026-0005', 5, 2, 1, NULL, 'closed', '2025-01-01', '2026-01-01', NULL, 'Net 15', 14999.00, 2699.82, 0.00, 17698.82, 'Completed previous contract. Successfully migrated to Annual Enterprise SUB-2026-0002.');

-- 9. SUBSCRIPTION LINES (In Rupees ₹, with Odoo Brand Edition +₹560)
INSERT INTO `subscription_lines` (`id`, `subscription_id`, `product_id`, `variant_id`, `is_addon`, `is_addon_active`, `quantity`, `unit_price`, `tax_id`, `tax_amount`, `discount_amount`, `subtotal`) VALUES
-- For SUB-2026-0001 (Apex Dynamics):
(1, 1, 1, 2, 0, 1, 1, 25559.00, 1, 4600.62, 5000.00, 25559.00), -- 24999 + 560 (Odoo brand)
(2, 1, 6, NULL, 1, 1, 1, 3999.00, 1, 719.82, 0.00, 3999.00), -- BI Add-on Active
-- For SUB-2026-0002 (InfoTechCorp India):
(3, 2, 1, 1, 0, 1, 1, 149990.00, 1, 21598.20, 29998.00, 149990.00),
(4, 2, 4, NULL, 1, 1, 1, 15999.00, 1, 2303.82, 3199.80, 15999.00),
-- For SUB-2026-0003 (FreshBites):
(5, 3, 2, 4, 0, 1, 1, 8499.00, 1, 1529.82, 0.00, 8499.00),
(6, 3, 6, NULL, 1, 1, 1, 3999.00, 1, 719.82, 0.00, 3999.00),
-- For SUB-2026-0004:
(7, 4, 3, NULL, 0, 1, 1, 4499.00, 1, 809.82, 0.00, 4499.00),
-- For SUB-2026-0005:
(8, 5, 2, 4, 0, 1, 1, 14999.00, 1, 2699.82, 0.00, 14999.00);

-- 10. INVOICES (In Rupees ₹, with GST and Overdue Receivables)
INSERT INTO `invoices` (`id`, `invoice_number`, `subscription_id`, `customer_id`, `billing_date`, `due_date`, `status`, `subtotal`, `tax_total`, `discount_total`, `grand_total`, `amount_paid`, `amount_due`, `notes`) VALUES
(1, 'INV-2026-0001', 1, 4, '2026-09-15', '2026-09-30', 'paid', 29558.00, 5320.44, 5000.00, 29878.44, 29878.44, 0.00, 'September billing cycle settled in full via UPI / HDFC NetBanking.'),
(2, 'INV-2026-0002', 1, 4, '2026-10-15', '2026-10-30', 'confirmed', 29558.00, 5320.44, 5000.00, 29878.44, 0.00, 29878.44, 'October billing cycle invoice. Due date passed (OVERDUE follow-up active).'),
(3, 'INV-2026-0003', 2, 5, '2026-10-01', '2026-10-31', 'paid', 165989.00, 23902.02, 33197.80, 156693.22, 156693.22, 0.00, 'Annual Enterprise Contract settled via RTGS Bank Transfer.'),
(4, 'INV-2026-0004', 1, 4, '2026-10-28', '2026-11-12', 'draft', 29558.00, 5320.44, 5000.00, 29878.44, 0.00, 29878.44, 'Draft invoice prepared by Automated Billing Day run for review.');

-- 11. INVOICE LINES
INSERT INTO `invoice_lines` (`id`, `invoice_id`, `product_name`, `variant_info`, `quantity`, `unit_price`, `tax_rate`, `tax_amount`, `discount_amount`, `total_amount`) VALUES
(1, 1, 'SubNexa ERP Enterprise (India Edition)', 'Odoo Brand Edition (+560)', 1, 25559.00, 18.00, 4600.62, 5000.00, 25159.62),
(2, 1, 'Advanced GST E-Way & Real-Time BI Analytics', 'Active Add-on', 1, 3999.00, 18.00, 719.82, 0.00, 4718.82),
(3, 2, 'SubNexa ERP Enterprise (India Edition)', 'Odoo Brand Edition (+560)', 1, 25559.00, 18.00, 4600.62, 5000.00, 25159.62),
(4, 2, 'Advanced GST E-Way & Real-Time BI Analytics', 'Active Add-on', 1, 3999.00, 18.00, 719.82, 0.00, 4718.82),
(5, 3, 'Annual Enterprise Contract (ERP Suite)', 'Standard Bharat Edition', 1, 149990.00, 18.00, 21598.20, 29998.00, 141590.20),
(6, 3, 'Dedicated Technical Account Manager (India Support)', 'Add-on', 1, 15999.00, 18.00, 2303.82, 3199.80, 15103.02),
(7, 4, 'SubNexa ERP Enterprise (India Edition)', 'Odoo Brand Edition (+560)', 1, 25559.00, 18.00, 4600.62, 5000.00, 25159.62),
(8, 4, 'Advanced GST E-Way & Real-Time BI Analytics', 'Active Add-on', 1, 3999.00, 18.00, 719.82, 0.00, 4718.82);

-- 12. PAYMENTS (Receipts in Rupees ₹ via UPI & RTGS)
INSERT INTO `payments` (`id`, `payment_number`, `invoice_id`, `customer_id`, `recorded_by_id`, `payment_method`, `amount`, `payment_date`, `transaction_ref`, `notes`) VALUES
(1, 'PAY-2026-0001', 1, 4, 2, 'upi', 29878.44, '2026-09-18', 'UPI/BHIM-982491024810', 'Payment authorized via BHIM UPI linked to HDFC Bank.'),
(2, 'PAY-2026-0002', 3, 5, 2, 'bank_transfer', 156693.22, '2026-10-05', 'RTGS-ICICI-00891294', 'Corporate RTGS settlement received and verified in current account statement.');

-- 13. AUDIT LOGS
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `ip_address`) VALUES
(1, 1, 'SYSTEM_INIT', 'SubNexa Pro Indian localization database initialized with GST rules and INR catalog', '127.0.0.1'),
(2, 2, 'QUOTE_CREATED', 'Created quotation SUB-2026-0003 for FreshBites Retail Chains India', '127.0.0.1'),
(3, 2, 'PAYMENT_RECORDED', 'Recorded payment PAY-2026-0002 of ₹ 1,56,693.22 for InfoTechCorp India Ltd', '127.0.0.1');
