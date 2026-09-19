-- Credit card master (Set Software)
CREATE TABLE IF NOT EXISTS `tbl_credit_card` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int DEFAULT NULL COMMENT 'FK tbl_branches.id',
    `name` varchar(255) NOT NULL DEFAULT '',
    `account_group` varchar(255) NOT NULL DEFAULT '' COMMENT 'Account ledger name',
    `commission_account` varchar(255) NOT NULL DEFAULT '' COMMENT 'Commission ledger name',
    `commission_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
    `status` tinyint(1) NOT NULL DEFAULT 1,
    `is_default` tinyint(1) NOT NULL DEFAULT 0,
    `sort_order` int NOT NULL DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_credit_card_branch` (`branch_id`),
    KEY `idx_credit_card_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
