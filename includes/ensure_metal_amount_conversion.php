<?php

/**
 * Create tbl_metal_amount_conversions and optional tbl_customer_ledger metal columns
 * (diamond) when missing.
 */
function auragold_ensure_metal_amount_conversion_table(mysqli $conn): void
{
    if (!$conn instanceof mysqli) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $sql = "
    CREATE TABLE IF NOT EXISTS `tbl_metal_amount_conversions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `branch_id` int(11) UNSIGNED NULL DEFAULT NULL,
      `customer_id` int(11) NOT NULL,
      `customer_name` varchar(255) NOT NULL,
      `direction` enum('metal_to_amount','amount_to_metal') NOT NULL,
      `metal_type` varchar(32) NOT NULL,
      `metal_weight` decimal(16,4) NOT NULL DEFAULT 0.0000,
      `rate` decimal(18,4) NOT NULL DEFAULT 0.0000,
      `amount` decimal(16,2) NOT NULL DEFAULT 0.00,
      `trans_date` datetime NOT NULL,
      `trans_no` varchar(64) DEFAULT NULL,
      `comment` text DEFAULT NULL,
      `status` tinyint(1) NOT NULL DEFAULT 1,
      `created_by` int(11) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `customer_id` (`customer_id`),
      KEY `direction` (`direction`),
      KEY `trans_date` (`trans_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    @mysqli_query($conn, $sql);
    auragold_ensure_mac_against_item_columns($conn);
}

/**
 * Optional product link when amount is converted to metal against a specific item.
 */
function auragold_ensure_mac_against_item_columns(mysqli $conn): void
{
    if (!$conn instanceof mysqli || !function_exists('auragold_tbl_has_column')) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'product_id')) {
        @mysqli_query(
            $conn,
            'ALTER TABLE tbl_metal_amount_conversions ADD COLUMN product_id INT(11) NULL DEFAULT NULL AFTER comment'
        );
    }
    if (!auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'product_characteristic_id')) {
        @mysqli_query(
            $conn,
            'ALTER TABLE tbl_metal_amount_conversions ADD COLUMN product_characteristic_id INT(11) NULL DEFAULT NULL AFTER product_id'
        );
    }
    if (!auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'against_item_label')) {
        @mysqli_query(
            $conn,
            'ALTER TABLE tbl_metal_amount_conversions ADD COLUMN against_item_label VARCHAR(255) NULL DEFAULT NULL AFTER product_characteristic_id'
        );
    }
}

function auragold_ensure_ledger_diamond_columns(mysqli $conn): void
{
    if (!$conn instanceof mysqli || !function_exists('auragold_tbl_has_column')) {
        return;
    }
    static $d = false;
    if ($d) {
        return;
    }
    $d = true;
    if (!auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_diamond')) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customer_ledger ADD COLUMN debit_diamond decimal(12,3) NOT NULL DEFAULT 0.000 AFTER credit_silver"
        );
    }
    if (!auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'credit_diamond')) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customer_ledger ADD COLUMN credit_diamond decimal(12,3) NOT NULL DEFAULT 0.000 AFTER debit_diamond"
        );
    }
    if (!auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'balance_diamond')) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customer_ledger ADD COLUMN balance_diamond decimal(12,3) NOT NULL DEFAULT 0.000 AFTER balance_silver"
        );
    }
}

/**
 * Optional platinum weight columns (same scale as gold/silver).
 */
function auragold_ensure_ledger_platinum_columns(mysqli $conn): void
{
    if (!$conn instanceof mysqli || !function_exists('auragold_tbl_has_column')) {
        return;
    }
    static $o = false;
    if ($o) {
        return;
    }
    $o = true;
    if (!auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_platinum')) {
        $after = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'credit_diamond')
            ? 'credit_diamond' : 'credit_silver';
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customer_ledger ADD COLUMN debit_platinum decimal(12,3) NOT NULL DEFAULT 0.000 AFTER `" . $after . "`"
        );
    }
    if (!auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'credit_platinum')) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customer_ledger ADD COLUMN credit_platinum decimal(12,3) NOT NULL DEFAULT 0.000 AFTER debit_platinum"
        );
    }
    if (!auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'balance_platinum')) {
        $bafter = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'balance_diamond') ? 'balance_diamond' : 'balance_silver';
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customer_ledger ADD COLUMN balance_platinum decimal(12,3) NOT NULL DEFAULT 0.000 AFTER `" . $bafter . "`"
        );
    }
}
