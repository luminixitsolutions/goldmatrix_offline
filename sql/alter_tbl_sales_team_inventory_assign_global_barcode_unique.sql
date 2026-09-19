-- One barcode may be assigned to only one sale person globally.
-- Run once if the table already exists with UNIQUE (sales_person, barcode_no).
-- If this fails due to duplicate barcodes across sale persons, delete or merge those rows first.

ALTER TABLE `tbl_sales_team_inventory_assign` DROP INDEX `uk_sp_barcode`;
ALTER TABLE `tbl_sales_team_inventory_assign` ADD UNIQUE KEY `uk_barcode_global` (`barcode_no`(100));
