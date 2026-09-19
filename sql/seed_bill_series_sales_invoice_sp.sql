-- Optional: Sale Invoice numbers as SP10, SP11, … (prefix SP, first number 10).
-- Requires: admin/sql/create_tbl_bill_series.sql applied.
-- Adjust voucher_type_id if your tbl_voucher_types.id for "Sales Invoice" is not 33.

-- INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
-- SELECT id, NULL, 'SP', '', 10, 1, NOW()
-- FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'sales invoice' LIMIT 1
-- ON DUPLICATE KEY UPDATE prefix = VALUES(prefix), suffix = VALUES(suffix), start_count = VALUES(start_count);

-- Safer: add only if no active series exists for Sales Invoice yet:
INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
SELECT vt.id, NULL, 'SP', '', 10, 1, NOW()
FROM tbl_voucher_types vt
WHERE vt.status = 1 AND LOWER(TRIM(vt.name)) = 'sales invoice'
  AND NOT EXISTS (
    SELECT 1 FROM tbl_bill_series bs WHERE bs.status = 1 AND bs.voucher_type_id = vt.id
  )
LIMIT 1;
