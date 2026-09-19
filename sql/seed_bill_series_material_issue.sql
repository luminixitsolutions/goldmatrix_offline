-- Optional: Material Issue numbers from Bill Series. Add voucher type "Material Issue" first, then run this.

INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
SELECT vt.id, NULL, 'MI-', '', 1, 1, NOW()
FROM tbl_voucher_types vt
WHERE vt.status = 1 AND LOWER(TRIM(vt.name)) = 'material issue'
  AND NOT EXISTS (
    SELECT 1 FROM tbl_bill_series bs WHERE bs.status = 1 AND bs.voucher_type_id = vt.id
  )
ORDER BY vt.id ASC
LIMIT 1;
