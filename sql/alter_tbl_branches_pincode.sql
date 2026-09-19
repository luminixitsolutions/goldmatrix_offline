-- Registry / master database: tbl_branches postal PIN for e-Way bill (seller from-address).
-- Run once. If column already exists, skip or comment out the ALTER.

ALTER TABLE `tbl_branches`
  ADD COLUMN `pincode` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Postal PIN (e-Way / GST)';

-- Optional: copy from legacy zip_code into pincode where pincode is empty (only if zip_code exists on your DB):
-- UPDATE `tbl_branches`
-- SET `pincode` = NULLIF(TRIM(`zip_code`), '')
-- WHERE (`pincode` IS NULL OR TRIM(IFNULL(`pincode`,'')) = '')
--   AND `zip_code` IS NOT NULL AND TRIM(`zip_code`) <> '';
