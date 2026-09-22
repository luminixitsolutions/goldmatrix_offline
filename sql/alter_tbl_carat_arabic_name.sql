-- Optional Arabic name on Carat master (auto-applied via auragold_ensure_tbl_carat_arabic_name).

ALTER TABLE `tbl_carat`
  ADD COLUMN `arabic_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Arabic display name' AFTER `name`;

-- If column already exists with latin1 (Arabic save fails), run:
-- ALTER TABLE `tbl_carat` MODIFY COLUMN `arabic_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Arabic display name';
