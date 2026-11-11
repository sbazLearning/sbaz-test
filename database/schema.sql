-- ============================================================
--  National Cement Sanitary & Hardware - Billing System
--  Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS hardware_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hardware_shop;

-- ---------------------------------------------------------
-- Users (login)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default login -> username: admin | password: admin123
-- (Please change the password after first login from Dashboard > Change Password)
INSERT INTO users (username, password, full_name)
VALUES ('admin', '$2y$10$7/GXlN2dzqfMKMK9iO3E3eRH00wPLqRkvDnLms0eYn5BO0qhfxfLS', 'Shop Owner')
ON DUPLICATE KEY UPDATE username = username;

-- ---------------------------------------------------------
-- Discount Groups (set one discount % that applies to every
-- item placed in that group - change the group and all its
-- items update automatically)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS discount_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) UNIQUE NOT NULL,
    discount DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Discount percentage applied to every item in this group',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Items (inventory)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    short_name VARCHAR(50) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Own discount percentage - only used when discount_group_id is NULL',
    discount_group_id INT DEFAULT NULL COMMENT 'If set, the group discount overrides the item own discount',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_short_name (short_name),
    CONSTRAINT fk_items_group FOREIGN KEY (discount_group_id) REFERENCES discount_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Customers (for repeat/regular customer ledger tracking)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'Unique friendly ID, e.g. CUST00001',
    name VARCHAR(100) NOT NULL,
    mobile_number VARCHAR(15) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mobile (mobile_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Bills (bill header)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_number VARCHAR(30) UNIQUE NOT NULL,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(100) DEFAULT NULL,
    mobile_number VARCHAR(15) DEFAULT NULL,
    bill_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bills_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Customer payments (money received from a customer - either
-- against the account in general, or a specific bill)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    bill_id INT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_bill FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Bill items (bill line items)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    item_id INT DEFAULT NULL,
    item_name VARCHAR(150) NOT NULL,
    short_name VARCHAR(50) DEFAULT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Discount percentage, e.g. 5.00 = 5%',
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_bill_items_bill FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Bill edit history (keeps a snapshot every time a saved
-- bill is modified later, so nothing is ever silently lost)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS bill_edit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    edited_by VARCHAR(50) DEFAULT NULL,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    old_snapshot LONGTEXT NOT NULL COMMENT 'JSON snapshot of the bill + items BEFORE this edit',
    CONSTRAINT fk_bill_history_bill FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Settings (bill numbering counter, shop notes etc.)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(500) DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('last_bill_number', '0'),
    ('bill_prefix', 'NCSH'),
    ('bill_notes', 'Thank you for your business!'),
    ('last_customer_number', '0'),
    ('customer_prefix', 'CUST')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
