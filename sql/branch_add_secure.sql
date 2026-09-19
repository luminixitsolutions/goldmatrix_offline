-- =============================================================================
-- Secure "Add Branch" feature — registry DB (same server as tbl_branches).
-- Run once. Ignore "Duplicate column name" if a line was already applied.
--
-- If tbl_settings is missing entirely, this creates it (same as create_tbl_settings_barcode.sql).
-- APIs also auto-create tbl_settings via includes/ensure_tbl_settings.php on first use.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tbl_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barcode_prefix` varchar(50) DEFAULT 'RG' COMMENT 'Prefix for generated barcodes (e.g. RG00012)',
  `barcode_digit_length` int(11) DEFAULT 5 COMMENT 'Number of digits after prefix',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tbl_settings` (`id`, `barcode_prefix`, `barcode_digit_length`) VALUES (1, 'RG', 5);

-- Add Branch gate (ignore "Duplicate column" if already present, e.g. from ensure_tbl_settings.php)
-- =============================================================================
-- Notes:
-- * The application stores branches in `tbl_branches` (this ERP uses that name;
--   requirements referring to "tbl_branch" mean this table).
-- * New sub-branches use the shared application database: leave db_name NULL so
--   working context stays on DB_NAME (no new MySQL database per branch).
-- * Master password for "+ Branch" is stored on the single `tbl_settings` row
--   as `branch_password_hash` (bcrypt). Default after this script: Admin@123
--   — change it immediately in production (update hash or use UI flow).
-- =============================================================================

ALTER TABLE `tbl_settings`
  ADD COLUMN `branch_password_hash` VARCHAR(255) NULL DEFAULT NULL
  COMMENT 'bcrypt hash for Add Branch modal; use password_hash() in PHP';

-- Optional: set / rotate hash — generate with: php -r "echo password_hash('YourPassword', PASSWORD_DEFAULT);"
UPDATE `tbl_settings`
SET `branch_password_hash` = '$2y$10$7Ib8O3CuNPbi.5DSslAwL.4jEau/91VEAazYAisqFltOEhHcSyztq'
WHERE (`branch_password_hash` IS NULL OR `branch_password_hash` = '')
LIMIT 1;

-- tbl_branches: extra profile fields for Add Branch (ignore duplicate column errors)
ALTER TABLE `tbl_branches` ADD COLUMN `phone2` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Second contact';
ALTER TABLE `tbl_branches` ADD COLUMN `country` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `state` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `zip_code` VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE `tbl_branches` ADD COLUMN `barcode_num_digits` INT NULL DEFAULT NULL COMMENT 'Barcode numeric length for this branch';
ALTER TABLE `tbl_branches` ADD COLUMN `branch_barcode_prefix` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Prefix for barcodes';
