-- Per–voucher-type payment method visibility (voucher-type.php)
CREATE TABLE IF NOT EXISTS `tbl_voucher_payment_buttons` (
  `voucher_type_id` int(11) NOT NULL,
  `cash` tinyint(1) NOT NULL DEFAULT 1,
  `metal_exchange` tinyint(1) NOT NULL DEFAULT 1,
  `bank` tinyint(1) NOT NULL DEFAULT 1,
  `scrap` tinyint(1) NOT NULL DEFAULT 1,
  `cheque` tinyint(1) NOT NULL DEFAULT 1,
  `add_diamond` tinyint(1) NOT NULL DEFAULT 1,
  `upi` tinyint(1) NOT NULL DEFAULT 1,
  `add_stone` tinyint(1) NOT NULL DEFAULT 1,
  `card` tinyint(1) NOT NULL DEFAULT 1,
  `add_old_jewellery` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`voucher_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
