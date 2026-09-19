-- Optional performance indexes for get-customer-balance.php / account ledger lookups.
-- Run once in phpMyAdmin or MySQL CLI (may take time on large tbl_customer_ledger).

ALTER TABLE tbl_customer_ledger
  ADD INDEX idx_cl_customer_status_id (customer_id, status, id);

-- Only if branch_id column exists:
-- ALTER TABLE tbl_customer_ledger
--   ADD INDEX idx_cl_customer_status_branch_id (customer_id, status, branch_id, id);
