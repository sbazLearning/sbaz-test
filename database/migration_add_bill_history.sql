-- ============================================================
--  MIGRATION: Add bill_edit_history table
--  Run this ONLY IF you already set up the database before and
--  are seeing: "Table 'hardware_shop.bill_edit_history' doesn't exist"
--
--  This does NOT touch/delete any of your existing items, bills,
--  or login data. It only adds the new table needed for the
--  "Modify Bill" / "Edit History" feature.
-- ============================================================

USE hardware_shop;

CREATE TABLE IF NOT EXISTS bill_edit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    edited_by VARCHAR(50) DEFAULT NULL,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    old_snapshot LONGTEXT NOT NULL COMMENT 'JSON snapshot of the bill + items BEFORE this edit',
    CONSTRAINT fk_bill_history_bill FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB;
