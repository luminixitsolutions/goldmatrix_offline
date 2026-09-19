-- Jobwork Queue No. (Manufacturing) — prefix from Bill Series voucher type "Jobwork Queue" (bill-series.php)
ALTER TABLE `tbl_jobwork_orders`
  ADD COLUMN `jobwork_queue_no` varchar(50) NOT NULL DEFAULT '' COMMENT 'Jobwork Queue number from bill series' AFTER `jobwork_no`;
