-- GST breakdown on sale invoice header (optional; save-sale-invoice.php also auto-adds these columns if missing).
ALTER TABLE `tbl_sale_invoices`
  ADD COLUMN `gst_supply_mode` VARCHAR(24) NULL DEFAULT NULL COMMENT 'intrastate|interstate',
  ADD COLUMN `gst_cgst_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `gst_sgst_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `gst_igst_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00;
