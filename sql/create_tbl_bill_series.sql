-- Bill Series: numbering config per voucher type (prefix, suffix, start count).
-- Once any bill/invoice is generated for a voucher_type, that series is locked (no edit/delete).
-- Run once to create the table.

CREATE TABLE IF NOT EXISTS `tbl_bill_series` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_type_id` int(11) NOT NULL COMMENT 'FK to tbl_voucher_types.id',
  `branch_id` int(11) DEFAULT NULL COMMENT 'Optional branch',
  `prefix` varchar(50) NOT NULL DEFAULT '',
  `suffix` varchar(50) NOT NULL DEFAULT '',
  `start_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Bill series count from',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_voucher_type_id` (`voucher_type_id`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lock logic: is_locked = true when any bill exists for this voucher_type_id.
-- Bills are detected from: tbl_purchase_invoice_items.voucher_type,
-- tbl_stock_journal.voucher_type, and other transaction tables that store voucher_type (id as string).
-- Backend uses countBillsForVoucherType($conn, $voucher_type_id) to set is_locked.
