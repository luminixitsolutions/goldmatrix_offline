-- Purchase Return: store Against Of type (Purchase Invoice / Purchase Quotation) and linked document id
-- Run once if columns are missing.

ALTER TABLE `tbl_purchase_returns`
  ADD COLUMN `against_type` varchar(100) DEFAULT NULL AFTER `against_of`,
  ADD COLUMN `against_id` int(11) DEFAULT NULL AFTER `against_type`;
