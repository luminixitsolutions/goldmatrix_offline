-- GST: whether this tax row applies for intra-state (local) or inter-state (out of state) supply.
-- Run once on existing databases that already have tbl_tax_master.

ALTER TABLE `tbl_tax_master`
  ADD COLUMN `gst_supply_scope` varchar(32) NOT NULL DEFAULT 'local_state'
  COMMENT 'local_state=intra (CGST+SGST); out_of_state=inter (IGST)'
  AFTER `default_calculation_mode`;
