-- Optional: default Bill Series for Old Jewellery Scrap Invoice (prefix OJB-, start 1).
-- Ensure admin/sql/seed_voucher_type_old_jewelry_scrap_invoice.sql was run first, then:

INSERT INTO tbl_bill_series (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_at)
SELECT vt.id, NULL, 'OJB-', '', 1, 1, NOW()
FROM tbl_voucher_types vt
WHERE vt.status = 1 AND LOWER(TRIM(vt.name)) = 'old jewellery scrap invoice'
  AND NOT EXISTS (
    SELECT 1 FROM tbl_bill_series bs WHERE bs.status = 1 AND bs.voucher_type_id = vt.id
  )
ORDER BY vt.id ASC
LIMIT 1;
