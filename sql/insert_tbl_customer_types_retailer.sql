-- Add "Retailer" to customer types (customer create modal, vouchers, etc. all read from tbl_customer_types).
-- Safe to run multiple times.

INSERT INTO tbl_customer_types (name, code, status, sort_order)
SELECT 'Retailer', 'RETAILER', 1, 8
FROM (SELECT 1 AS _) AS dummy
WHERE NOT EXISTS (
    SELECT 1 FROM tbl_customer_types t
    WHERE LOWER(TRIM(t.name)) = 'retailer'
       OR LOWER(TRIM(IFNULL(t.code, ''))) = 'retailer'
);
