-- Registry / master database: tbl_branches shop landline (My Profile).
-- Run once. If column already exists, skip or comment out the ALTER.

ALTER TABLE `tbl_branches`
  ADD COLUMN `landline` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Shop landline number';
