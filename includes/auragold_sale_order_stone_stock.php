<?php

/**
 * Sale Order: allocate gemstone/coloured-stone serial stock — deduct tbl_stock, outward row, issue log, Stock History journal.
 */

if (!function_exists('auragold_sale_order_ensure_stone_issue_table')) {
    function auragold_sale_order_ensure_stone_issue_table(mysqli $conn): void
    {
        $tbl = 'tbl_sale_order_stone_stock_issue';
        @mysqli_query(
            $conn,
            'CREATE TABLE IF NOT EXISTS `' . $tbl . '` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `order_id` int(11) NOT NULL,
              `stock_id` int(11) NOT NULL,
              `barcode` varchar(100) DEFAULT NULL,
              `product_name` varchar(255) DEFAULT NULL,
              `stone_category` varchar(100) DEFAULT NULL,
              `weight` decimal(14,4) NOT NULL DEFAULT 0,
              `qty` decimal(14,4) NOT NULL DEFAULT 0,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_so_order_stone` (`order_id`),
              KEY `idx_so_stock_stone` (`stock_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

if (!function_exists('auragold_sale_order_apply_stone_allocations')) {
    /**
     * @param array<int, array<string, mixed>> $rows Each: stock_id, barcode (opt), qty (alloc pcs), weight (opt alloc wt), stone_category (opt)
     * @return array{saved:int}
     */
    function auragold_sale_order_apply_stone_allocations(
        mysqli $conn,
        int $order_id,
        array $rows,
        string $order_no,
        string $order_date_ymd,
        bool &$tx_ok,
        string &$tx_err
    ): array {
        require_once __DIR__ . '/auragold_voucher_stone_stock.php';

        return auragold_voucher_apply_stone_allocations(
            $conn,
            'sale_order',
            $order_id,
            $rows,
            $order_no,
            $order_date_ymd,
            $tx_ok,
            $tx_err
        );
    }
}
