-- Add making_amount_for_sale_fixing to tbl_purchase_invoices (for Fixing Type = Hedging)
-- When fixing_type = Hedging, the purchase invoice is saved without making amount on items;
-- the total making amount is stored here to be applied against the linked sale fixing.
-- Run this once to enable saving making amount against sale fixing.

ALTER TABLE `tbl_purchase_invoices`
ADD COLUMN `making_amount_for_sale_fixing` decimal(18,2) DEFAULT 0.00 COMMENT 'Total making amount to record against sale fixing when fixing_type = Hedging';
