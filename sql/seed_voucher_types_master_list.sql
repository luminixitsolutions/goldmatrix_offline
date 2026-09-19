-- Ensures master voucher types exist (name match is case-insensitive).
-- Safe to run multiple times. Adjusts only missing rows.

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Catalogue Quotation', NULL, 'Catalogue Quotation', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'catalogue quotation' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Consignment In', NULL, 'Consignment In', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'consignment in' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Consignment Out', NULL, 'Consignment Out', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'consignment out' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Contra Voucher', NULL, 'Contra Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'contra voucher' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Credit Note', NULL, 'Credit Note', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'credit note' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Customer Advance', NULL, 'Customer Advance', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'customer advance' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Daily Salary Voucher', NULL, 'Daily Salary Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'daily salary voucher' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Debit Note', NULL, 'Debit Note', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'debit note' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Delivery Note', NULL, 'Delivery Note', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'delivery note' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Material In', NULL, 'Material In', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'material in' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Material Issue', NULL, 'Material Issue', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'material issue' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Material Out', NULL, 'Material Out', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'material out' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Material Receipt', NULL, 'Material Receipt', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'material receipt' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Material Receive', NULL, 'Material Receive', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'material receive' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Monthly Salary Voucher', NULL, 'Monthly Salary Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'monthly salary voucher' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Old Jewelry - Scrap Invoice', NULL, 'Old Jewelry - Scrap Invoice', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'old jewelry - scrap invoice' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Opening Balance', NULL, 'Opening Balance', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'opening balance' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Opening Stock', NULL, 'Opening Stock', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'opening stock' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Payment Voucher', NULL, 'Payment Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'payment voucher' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Purchase Payment Voucher', NULL, 'Purchase Payment Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'purchase payment voucher' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'PDC Clearance', NULL, 'PDC Clearance', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'pdc clearance' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'PDC Payable', NULL, 'PDC Payable', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'pdc payable' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'PDC Receivable', NULL, 'PDC Receivable', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'pdc receivable' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Physical Stock', NULL, 'Physical Stock', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'physical stock' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'POS', NULL, 'POS', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'pos' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Sale Receipt Voucher', NULL, 'Sale Receipt Voucher', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'sale receipt voucher' LIMIT 1);

INSERT INTO `tbl_voucher_types` (`name`, `method_of_numbering`, `type_of_voucher`, `calculate_amount_by`, `calculate_wastage_by`, `fixing_type`, `calculate_loss_by`, `status`, `created_at`)
SELECT 'Task / Event', NULL, 'Task / Event', 'Rate X Gross Wt', 'Net Wt', 'Standard', 'Net Wt', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `tbl_voucher_types` WHERE LOWER(TRIM(`name`)) = 'task / event' LIMIT 1);
