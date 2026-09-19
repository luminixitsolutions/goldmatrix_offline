-- Add diamond_category and calculation_type to tbl_sale_return_items for Sale Return product rows
-- diamond_category: e.g. Diamonds, GemStones, Jewellery (Diamond & Stones tab)
-- calculation_type: e.g. Rate X Gross Wt, Carat X Rate, Fix, etc.
-- save-sale-return.php will auto-add these columns if missing; run this manually if preferred.

ALTER TABLE `tbl_sale_return_items`
ADD COLUMN `diamond_category` VARCHAR(100) DEFAULT NULL COMMENT 'Diamonds, GemStones, Jewellery' AFTER `description`;
ALTER TABLE `tbl_sale_return_items`
ADD COLUMN `calculation_type` VARCHAR(100) DEFAULT NULL COMMENT 'Rate X Gross Wt, Carat X Rate, Fix, etc.' AFTER `diamond_amount`;
