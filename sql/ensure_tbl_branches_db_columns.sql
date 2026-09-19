-- Optional: per-branch database (used by switch_branch.php). Safe to run if columns already exist (may error on duplicate — ignore).
ALTER TABLE `tbl_branches`
  ADD COLUMN `db_name` varchar(100) NULL DEFAULT NULL AFTER `code`,
  ADD COLUMN `db_users` varchar(100) NULL DEFAULT NULL AFTER `db_name`,
  ADD COLUMN `db_password` varchar(100) NULL DEFAULT NULL AFTER `db_users`;
