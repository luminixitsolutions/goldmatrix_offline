-- Bill series for Jobwork Queue (JWQ-1, …) — run after seed_voucher_type_jobwork_queue.sql
INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
SELECT vt.id, NULL, 'JWQ-', '', 1, 1, NOW()
FROM tbl_voucher_types vt
WHERE vt.status = 1 AND LOWER(TRIM(vt.name)) = 'jobwork queue'
  AND NOT EXISTS (
    SELECT 1 FROM tbl_bill_series bs WHERE bs.status = 1 AND bs.voucher_type_id = vt.id
  )
ORDER BY vt.id ASC
LIMIT 1;
