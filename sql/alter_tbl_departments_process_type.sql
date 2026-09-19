-- Migrate legacy department process_type values to current options.
-- Safe to run multiple times.

UPDATE `tbl_departments`
SET `process_type` = 'Manufacturing Inhouse',
    `updated_at` = NOW()
WHERE `process_type` IN ('Manufacturing', 'Melting', 'Testing');

ALTER TABLE `tbl_departments`
  MODIFY COLUMN `process_type` varchar(40) NOT NULL DEFAULT 'Manufacturing Inhouse'
    COMMENT 'Manufacturing Inhouse | Manufacturing Outsource';
