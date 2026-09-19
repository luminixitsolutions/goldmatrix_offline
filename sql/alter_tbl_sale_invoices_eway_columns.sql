-- Optional: run once if you prefer manual migration (save-sale-invoice.php also auto-adds these).
ALTER TABLE `tbl_sale_invoices`
  ADD COLUMN `customer_gstin` VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN `eway_vehicle_no` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `eway_distance_km` DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN `eway_bill_no` VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN `eway_bill_date` DATETIME NULL DEFAULT NULL,
  ADD COLUMN `eway_status` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `eway_response` TEXT NULL DEFAULT NULL;
