-- ============================================================
--  MIGRATION: Add Discount Groups + Customer Ledger features
--  Run this ONLY IF you already set up this database before
--  (i.e. you are upgrading from an earlier copy of this app).
--
--  This does NOT delete or change any of your existing items,
--  bills, or login data. It only adds new tables/columns needed
--  for: Discount Groups, Customer Dashboard/Ledger with payment
--  tracking.
-- ============================================================

USE hardware_shop;

-- 1) Discount Groups table
CREATE TABLE IF NOT EXISTS discount_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) UNIQUE NOT NULL,
    discount DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Discount percentage applied to every item in this group',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2) Link items to a discount group (nullable - existing items keep
--    working exactly as before, using their own individual discount)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'hardware_shop' AND TABLE_NAME = 'items' AND COLUMN_NAME = 'discount_group_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE items ADD COLUMN discount_group_id INT DEFAULT NULL COMMENT "If set, the group discount overrides the item own discount", ADD CONSTRAINT fk_items_group FOREIGN KEY (discount_group_id) REFERENCES discount_groups(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) UNIQUE NOT NULL COMMENT 'Unique friendly ID, e.g. CUST00001',
    name VARCHAR(100) NOT NULL,
    mobile_number VARCHAR(15) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mobile (mobile_number)
) ENGINE=InnoDB;

-- 4) Link bills to a customer (nullable)
SET @col_exists2 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'hardware_shop' AND TABLE_NAME = 'bills' AND COLUMN_NAME = 'customer_id'
);
SET @sql2 = IF(@col_exists2 = 0,
    'ALTER TABLE bills ADD COLUMN customer_id INT DEFAULT NULL, ADD CONSTRAINT fk_bills_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 5) Customer payments table
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

-- 6) Settings needed for customer ID numbering
INSERT INTO settings (setting_key, setting_value) VALUES
    ('last_customer_number', '0'),
    ('customer_prefix', 'CUST')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- 7) (Optional but recommended) Link your EXISTING bills to customer
--    records, based on the customer_name/mobile_number already saved
--    on each bill, so your old bills show up in the new Customer
--    Ledger too. Safe to run multiple times.
INSERT INTO customers (customer_code, name, mobile_number)
SELECT
    CONCAT('CUST', LPAD(
        (SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code, 5) AS UNSIGNED)), 0) FROM customers) + ROW_NUMBER() OVER (ORDER BY b.mobile_number),
    5, '0')),
    MAX(b.customer_name),
    b.mobile_number
FROM bills b
WHERE b.mobile_number IS NOT NULL AND b.mobile_number != ''
  AND b.mobile_number NOT IN (SELECT mobile_number FROM customers WHERE mobile_number IS NOT NULL)
GROUP BY b.mobile_number;

UPDATE bills b
JOIN customers c ON c.mobile_number = b.mobile_number
SET b.customer_id = c.id
WHERE b.customer_id IS NULL AND b.mobile_number IS NOT NULL AND b.mobile_number != '';

UPDATE settings
SET setting_value = (SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code, 5) AS UNSIGNED)), 0) FROM customers)
WHERE setting_key = 'last_customer_number';
