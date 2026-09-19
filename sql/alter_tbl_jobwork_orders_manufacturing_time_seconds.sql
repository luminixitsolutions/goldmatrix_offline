-- Cumulative manufacturing timer (Manufacturing Process job cards) — seconds
-- Safe to run once; ignore error if column already exists.

ALTER TABLE `tbl_jobwork_orders`
  ADD COLUMN `manufacturing_time_seconds` INT UNSIGNED NOT NULL DEFAULT 0
  COMMENT 'Cumulative manufacturing time (seconds)';
