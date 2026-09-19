-- Extra field visibility flags for voucher-type.php (match reference UI)
-- Run once. Ignore errors if a column already exists.

ALTER TABLE `tbl_voucher_field_visibility`
  ADD COLUMN `show_billing_type` tinyint(1) NOT NULL DEFAULT 0 AFTER `fixing_type`,
  ADD COLUMN `show_metal_unfix` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_billing_type`,
  ADD COLUMN `show_payment_term` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_metal_unfix`,
  ADD COLUMN `show_unfix` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_payment_term`,
  ADD COLUMN `show_shipping_method` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_unfix`,
  ADD COLUMN `show_barcode_no` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_shipping_method`,
  ADD COLUMN `show_ounce_rate` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_barcode_no`,
  ADD COLUMN `show_lead_source` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_ounce_rate`,
  ADD COLUMN `show_design_no` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_lead_source`,
  ADD COLUMN `show_product_code` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_design_no`,
  ADD COLUMN `show_dmd_or_nam_unfix` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_product_code`,
  ADD COLUMN `show_update_tax_dropdown` tinyint(1) NOT NULL DEFAULT 0 AFTER `show_dmd_or_nam_unfix`;
