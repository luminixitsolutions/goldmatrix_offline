-- Add Against Ledger and Against Invoice No columns to tbl_customer_ledger
-- Run this SQL script to add the missing columns for the account ledger report

-- Check if columns exist before adding (MySQL doesn't support IF NOT EXISTS for ALTER TABLE ADD COLUMN)
-- Run these commands one by one, or wrap in a stored procedure

-- Add against_ledger column
SET @dbname = DATABASE();
SET @tablename = 'tbl_customer_ledger';
SET @columnname = 'against_ledger';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " varchar(255) DEFAULT NULL COMMENT 'Against Ledger name with balance (e.g., ABC(640.00Dr))' AFTER reference_no")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add against_invoice_no column
SET @columnname = 'against_invoice_no';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " varchar(100) DEFAULT NULL COMMENT 'Against Invoice/Order number' AFTER against_ledger")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for faster lookups (check if index exists first)
SET @indexname = 'idx_against_invoice_no';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  "SELECT 'Index already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD INDEX ", @indexname, " (against_invoice_no)")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

