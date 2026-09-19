-- Optional: Sales Quotation numbers from Bill Series (prefix/start_count per tbl_bill_series).
-- Requires: admin/sql/create_tbl_bill_series.sql and a voucher type named "Sales Quotation" in tbl_voucher_types.
-- Example: prefix SQ, start 1 → SQ1, SQ2, … (or use your own prefix in Bill Series UI).

INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
SELECT vt.id, NULL, 'SQ', '', 1, 1, NOW()
FROM tbl_voucher_types vt
WHERE vt.status = 1 AND LOWER(TRIM(vt.name)) = 'sales quotation'
  AND NOT EXISTS (
    SELECT 1 FROM tbl_bill_series bs WHERE bs.status = 1 AND bs.voucher_type_id = vt.id
  )
LIMIT 1;
