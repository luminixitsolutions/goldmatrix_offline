<?php
/**
 * Stock transfer document header: one invoice_no per Save (N line items).
 */

if (!function_exists('auragold_ensure_stock_transfer_doc_table')) {
    function auragold_ensure_stock_transfer_doc_table(mysqli $conn): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS `tbl_stock_transfer_doc` (
          `id` bigint NOT NULL AUTO_INCREMENT,
          `invoice_no` varchar(32) NOT NULL,
          `from_branch_id` int NOT NULL,
          `to_branch_id` int NOT NULL,
          `transfer_date` date DEFAULT NULL,
          `created_by` int DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `line_count` int NOT NULL DEFAULT 0,
          `total_qty` decimal(15,4) NOT NULL DEFAULT 0,
          `total_wt` decimal(15,4) NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_st_doc_invoice` (`invoice_no`),
          KEY `idx_st_doc_date` (`transfer_date`),
          KEY `idx_st_doc_branches` (`from_branch_id`,`to_branch_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($conn, $sql)) {
            return false;
        }

        // Link columns on pending / cross-log (best-effort).
        $pend = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_transfer_pending'");
        $hasPend = ($pend && mysqli_num_rows($pend) > 0);
        if ($pend) {
            mysqli_free_result($pend);
        }
        if ($hasPend) {
            $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_transfer_pending LIKE 'transfer_doc_id'");
            $has = ($c && mysqli_num_rows($c) > 0);
            if ($c) {
                mysqli_free_result($c);
            }
            if (!$has) {
                @mysqli_query(
                    $conn,
                    "ALTER TABLE tbl_stock_transfer_pending ADD COLUMN transfer_doc_id BIGINT NULL DEFAULT NULL AFTER outward_stock_id, ADD KEY idx_transfer_doc (transfer_doc_id)"
                );
            }
        }

        $log = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_cross_transfer_log'");
        $hasLog = ($log && mysqli_num_rows($log) > 0);
        if ($log) {
            mysqli_free_result($log);
        }
        if ($hasLog) {
            $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_cross_transfer_log LIKE 'transfer_doc_id'");
            $has = ($c && mysqli_num_rows($c) > 0);
            if ($c) {
                mysqli_free_result($c);
            }
            if (!$has) {
                @mysqli_query(
                    $conn,
                    "ALTER TABLE tbl_stock_cross_transfer_log ADD COLUMN transfer_doc_id BIGINT NULL DEFAULT NULL AFTER outward_stock_id, ADD KEY idx_xfer_doc (transfer_doc_id)"
                );
            }
        }

        return true;
    }
}

if (!function_exists('auragold_stock_transfer_doc_create')) {
    /**
     * Create transfer header and allocate ST-###### invoice number.
     *
     * @return array{id:int,invoice_no:string}
     */
    function auragold_stock_transfer_doc_create(
        mysqli $conn,
        int $from_branch_id,
        int $to_branch_id,
        string $transfer_date_ymd,
        int $created_by = 0
    ): array {
        if (!auragold_ensure_stock_transfer_doc_table($conn)) {
            throw new RuntimeException('Could not ensure tbl_stock_transfer_doc: ' . mysqli_error($conn));
        }
        $td = preg_match('/^\d{4}-\d{2}-\d{2}$/', $transfer_date_ymd) ? $transfer_date_ymd : date('Y-m-d');
        $tdEsc = mysqli_real_escape_string($conn, $td);
        $cb = $created_by > 0 ? (string) (int) $created_by : 'NULL';

        // Placeholder invoice, then update from insert id (ST-000001 style).
        $tmp = 'ST-TMP-' . uniqid('', true);
        $tmpEsc = mysqli_real_escape_string($conn, $tmp);
        $sql = "INSERT INTO tbl_stock_transfer_doc
            (invoice_no, from_branch_id, to_branch_id, transfer_date, created_by, line_count, total_qty, total_wt)
            VALUES ('$tmpEsc', " . (int) $from_branch_id . ', ' . (int) $to_branch_id . ", '$tdEsc', $cb, 0, 0, 0)";
        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException('Transfer document create failed: ' . mysqli_error($conn));
        }
        $id = (int) mysqli_insert_id($conn);
        if ($id <= 0) {
            throw new RuntimeException('Transfer document create returned no id.');
        }
        $invoice_no = 'ST-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $invEsc = mysqli_real_escape_string($conn, $invoice_no);
        if (!mysqli_query($conn, "UPDATE tbl_stock_transfer_doc SET invoice_no = '$invEsc' WHERE id = $id")) {
            throw new RuntimeException('Transfer invoice number update failed: ' . mysqli_error($conn));
        }

        return ['id' => $id, 'invoice_no' => $invoice_no];
    }
}

if (!function_exists('auragold_stock_transfer_doc_bump_totals')) {
    function auragold_stock_transfer_doc_bump_totals(mysqli $conn, int $doc_id, float $qty, float $wt): void
    {
        $doc_id = (int) $doc_id;
        if ($doc_id <= 0) {
            return;
        }
        @mysqli_query(
            $conn,
            'UPDATE tbl_stock_transfer_doc SET
                line_count = IFNULL(line_count,0) + 1,
                total_qty = IFNULL(total_qty,0) + ' . (float) $qty . ',
                total_wt = IFNULL(total_wt,0) + ' . (float) $wt . '
             WHERE id = ' . $doc_id
        );
    }
}
