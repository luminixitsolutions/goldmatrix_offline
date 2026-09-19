-- Optional: Sales Return numbers from Bill Series (prefix/start_count in tbl_bill_series).
-- Requires: admin/sql/create_tbl_bill_series.sql and voucher type "Sales Return" in tbl_voucher_types.

INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
SELECT vt.id, NULL, 'SR', '', 1, 1, NOW()
FROM tbl_voucher_types vt
WHERE vt.status = 1 AND LOWER(TRIM(vt.name)) = 'sales return'
  AND NOT EXISTS (
    SELECT 1 FROM tbl_bill_series bs WHERE bs.status = 1 AND bs.voucher_type_id = vt.id
  )
LIMIT 1;
