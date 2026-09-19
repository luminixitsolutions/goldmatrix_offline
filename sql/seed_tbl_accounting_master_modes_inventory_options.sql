-- Replaces older “Standard / Round half up / Truncate” seeds with inventory valuation modes.
-- Safe to run on existing DBs that already have tbl_accounting_master_modes.

INSERT INTO `tbl_accounting_master_modes` (`id`, `name`, `code`, `sort_order`, `status`) VALUES
(1, 'Last Purchase Rate', 'last_purchase_rate', 1, 1),
(2, 'FIFO', 'fifo', 2, 1),
(3, 'Average Cost', 'average_cost', 3, 1),
(4, 'Low Cost', 'low_cost', 4, 1),
(5, 'High Cost', 'high_cost', 5, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `code` = VALUES(`code`),
  `sort_order` = VALUES(`sort_order`),
  `status` = VALUES(`status`);
