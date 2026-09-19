-- Carat master: Sale / Purchase / Common purity % on one row (masters.php).
-- Run once. Existing `purity` is copied into all three when empty.

ALTER TABLE `tbl_carat`
  ADD COLUMN `purity_sales` DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'Purity % for sales' AFTER `purity`,
  ADD COLUMN `purity_purchase` DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'Purity % for purchase' AFTER `purity_sales`,
  ADD COLUMN `purity_common` DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'Purity % for common' AFTER `purity_purchase`;

UPDATE `tbl_carat`
SET
  purity_common = purity,
  purity_sales = purity,
  purity_purchase = purity
WHERE status = 1
  AND purity IS NOT NULL
  AND TRIM(CAST(purity AS CHAR)) != '';
