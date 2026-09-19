-- Add against_type and against_id to tbl_purchase_quotations for Purchase Quotation form
-- against_type: 'Direct' | 'Purchase Order'
-- against_id: ID of the selected Purchase Order (when against_type is Purchase Order)
-- Run once; if columns already exist, ignore the duplicate column error.

ALTER TABLE `tbl_purchase_quotations` ADD COLUMN `against_type` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `tbl_purchase_quotations` ADD COLUMN `against_id` INT(11) DEFAULT NULL;
