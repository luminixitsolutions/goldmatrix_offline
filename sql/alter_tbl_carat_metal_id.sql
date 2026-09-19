-- Link each Carat (karat/purity) row to a metal (Gold, Silver, Platinum, Diamond, etc.).
-- Run once. Used by Masters → Carat and dashboard metal/carat rate rows.

ALTER TABLE `tbl_carat`
  ADD COLUMN `metal_id` int unsigned DEFAULT NULL COMMENT 'tbl_metal.id' AFTER `name`;

ALTER TABLE `tbl_carat`
  ADD KEY `idx_carat_metal` (`metal_id`);

-- Optional: default existing rows to Gold (id 1) so legacy data shows under Gold until edited.
-- UPDATE `tbl_carat` SET `metal_id` = 1 WHERE `metal_id` IS NULL;
