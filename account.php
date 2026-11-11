-- 9 tables required:
users                    -- login credentials
discount_groups          -- group-based discounts
items                    -- inventory items with discount_group_id FK
customers                -- tracked customers with UNIQUE mobile
bills                    -- bill headers with customer_id FK
bill_items               -- line items with bill_id FK
customer_payments        -- payment records with customer_id + bill_id FK
bill_edit_history        -- edit snapshots with bill_id FK
settings                 -- configuration (last_bill_number, bill_prefix, etc.)