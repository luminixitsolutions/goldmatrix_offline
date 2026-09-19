-- Add against_type and against_id to tbl_sale_quotations for Sale Quotation form
-- against_type: 'Direct' | 'Sale Order' | 'Repair Order' | 'Delivery Note'
-- against_id: ID of the selected order (when against_type is Sale Order, Repair Order, or Delivery Note)

-- Run once. If columns already exist, ignore the error.
ALTER TABLE `tbl_sale_quotations` ADD COLUMN `against_type` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `tbl_sale_quotations` ADD COLUMN `against_id` INT(11) DEFAULT NULL;
