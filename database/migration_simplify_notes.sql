-- ============================================================
--  MIGRATION: Simplify default bill notes
--  Run this if you already set up the database and want to
--  change the default "Notes" message shown on new bills.
--
--  (Tip: you can also just edit the Notes box on the New Bill
--  page itself each time you make a bill — this only changes
--  the default text that appears automatically.)
-- ============================================================

USE hardware_shop;

UPDATE settings
SET setting_value = 'Thank you for your business!'
WHERE setting_key = 'bill_notes';
