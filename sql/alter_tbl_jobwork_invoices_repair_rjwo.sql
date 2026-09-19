-- Link jobwork invoices to Repair Job Work Orders (RJWO). Run once if not using runtime ALTER from save-jobwork-invoice.php.

ALTER TABLE tbl_jobwork_invoices
  ADD COLUMN repair_jobwork_order_id INT NULL DEFAULT NULL AFTER jobwork_order_id;

ALTER TABLE tbl_jobwork_invoices
  MODIFY jobwork_order_id INT NULL DEFAULT NULL;

ALTER TABLE tbl_jobwork_invoices
  DROP INDEX uniq_jobwork_order;

ALTER TABLE tbl_jobwork_invoices
  ADD UNIQUE KEY uniq_repair_jwo (repair_jobwork_order_id);

ALTER TABLE tbl_jobwork_invoices
  ADD UNIQUE KEY uniq_jwo_id (jobwork_order_id);
