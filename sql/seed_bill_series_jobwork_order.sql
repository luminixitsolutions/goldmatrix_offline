-- Optional: Job Work Order numbers from Bill Series (prefix/start_count in tbl_bill_series).
-- 1) Add voucher type "Jobwork Order" in your Voucher Types master (same name as in Bill Series UI).
-- 2) Run this after admin/sql/create_tbl_bill_series.sql exists.

INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
SELECT vt.id, NULL, 'JWO-', '', 1, 1, NOW()
FROM tbl_voucher_types vt
WHERE vt.status = 1 AND (LOWER(TRIM(vt.name)) = 'jobwork order' OR LOWER(TRIM(vt.name)) = 'job work order')
  AND NOT EXISTS (
    SELECT 1 FROM tbl_bill_series bs WHERE bs.status = 1 AND bs.voucher_type_id = vt.id
  )
ORDER BY vt.id ASC
LIMIT 1;
