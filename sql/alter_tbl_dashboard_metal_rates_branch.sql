-- Branch-scoped dashboard metal rates (run once; PHP also auto-applies via auragold_ensure_dashboard_metal_rates_branch_columns).
-- After migration: rows with branch_id = 0 are shared defaults; each branch can have its own sheet.

ALTER TABLE tbl_dashboard_metal_rates ADD COLUMN branch_id INT NOT NULL DEFAULT 0 AFTER id;
ALTER TABLE tbl_dashboard_metal_rates DROP INDEX uk_metal_carat;
ALTER TABLE tbl_dashboard_metal_rates ADD UNIQUE KEY uk_branch_metal_carat (branch_id, metal, carat_label);

ALTER TABLE tbl_dashboard_metal_meta ADD COLUMN branch_id INT NOT NULL DEFAULT 0 AFTER metal;
ALTER TABLE tbl_dashboard_metal_meta DROP PRIMARY KEY;
ALTER TABLE tbl_dashboard_metal_meta ADD PRIMARY KEY (metal, branch_id);

ALTER TABLE tbl_dashboard_metal_rate_history ADD COLUMN branch_id INT NOT NULL DEFAULT 0 AFTER id;
ALTER TABLE tbl_dashboard_metal_rate_history ADD KEY idx_branch_metal_time (branch_id, metal, recorded_at);
