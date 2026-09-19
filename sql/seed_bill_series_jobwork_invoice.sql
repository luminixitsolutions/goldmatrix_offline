-- Optional: Jobwork Invoice numbers from Bill Series (configure in admin/bill-series.php).
-- Ensure voucher type "Jobwork Invoice" exists in tbl_voucher_types, then run:

INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
SELECT vt.id, NULL, 'JWI-', '', 1, 1, NOW()
FROM tbl_voucher_types vt
WHERE vt.status = 1 AND LOWER(TRIM(vt.name)) = 'jobwork invoice'
  AND NOT EXISTS (
    SELECT 1 FROM tbl_bill_series bs WHERE bs.status = 1 AND bs.voucher_type_id = vt.id
  )
ORDER BY vt.id ASC
LIMIT 1;
