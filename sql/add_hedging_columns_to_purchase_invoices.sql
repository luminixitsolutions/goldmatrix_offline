-- Add hedging columns to tbl_purchase_invoices (for Fixing Type = Hedging)
-- Run this once to enable Hedge Contract Ref and Hedge Date on purchase invoices.
-- Standard purchase flow is unchanged; these columns are optional.

ALTER TABLE `tbl_purchase_invoices`
ADD COLUMN `hedge_contract_ref` varchar(255) DEFAULT NULL COMMENT 'Hedge contract reference when fixing_type = Hedging',
ADD COLUMN `hedge_date` date DEFAULT NULL COMMENT 'Hedge / locked rate date when fixing_type = Hedging';
