<?php
/**
 * Partial scrap stock-in: balance = invoice line gross_wt minus SUM(tbl_old_jewelry_stock.gross_wt).
 */
if (!function_exists('auragold_oj_scrap_stocked_gross_sum')) {
    function auragold_oj_scrap_stocked_gross_sum($conn, $item_id)
    {
        $item_id = (int) $item_id;
        if ($item_id <= 0 || !$conn) {
            return 0.0;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
        if (!$t || mysqli_num_rows($t) === 0) {
            return 0.0;
        }
        mysqli_free_result($t);
        $r = getRecord("SELECT COALESCE(SUM(gross_wt), 0) AS t FROM tbl_old_jewelry_stock WHERE source_item_id = $item_id");

        return (float) ($r['t'] ?? 0);
    }

    /**
     * Stock rows whose source_item_id no longer matches a live scrap line (e.g. after sync DELETE/re-INSERT of items)
     * still belong to source_invoice_id. For invoices with exactly one line, count that toward this line's stocked gross.
     */
    function auragold_oj_scrap_stocked_gross_sum_for_line_including_single_line_orphans($conn, $invoice_id, $item_id)
    {
        $invoice_id = (int) $invoice_id;
        $item_id = (int) $item_id;
        $base = auragold_oj_scrap_stocked_gross_sum($conn, $item_id);
        if ($invoice_id <= 0 || !$conn) {
            return $base;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
        if (!$t || mysqli_num_rows($t) === 0) {
            return $base;
        }
        mysqli_free_result($t);
        $cnt = getRecord("SELECT COUNT(*) AS c FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $invoice_id AND status = 1");
        if (!$cnt || (int) ($cnt['c'] ?? 0) !== 1) {
            return $base;
        }
        $orph = getRecord(
            "SELECT COALESCE(SUM(s.gross_wt),0) AS t FROM tbl_old_jewelry_stock s "
            . 'LEFT JOIN tbl_old_jewelry_scrap_invoice_items i ON i.id = s.source_item_id AND i.invoice_id = s.source_invoice_id AND IFNULL(i.status,1) = 1 '
            . "WHERE s.source_invoice_id = $invoice_id AND i.id IS NULL"
        );

        return $base + (float) ($orph['t'] ?? 0);
    }

    /** After replacing scrap invoice lines in-place, repoint tbl_old_jewelry_stock rows to new item ids (same order). */
    function auragold_oj_scrap_remap_stock_after_invoice_items_replaced($conn, $invoice_id, array $old_item_ids, array $new_item_ids)
    {
        $invoice_id = (int) $invoice_id;
        if ($invoice_id <= 0 || !$conn) {
            return;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
        if (!$t || mysqli_num_rows($t) === 0) {
            return;
        }
        mysqli_free_result($t);
        $n = min(count($old_item_ids), count($new_item_ids));
        for ($i = 0; $i < $n; $i++) {
            $oid = (int) $old_item_ids[$i];
            $nid = (int) $new_item_ids[$i];
            if ($oid > 0 && $nid > 0 && $oid !== $nid) {
                mysqli_query($conn, "UPDATE tbl_old_jewelry_stock SET source_item_id = $nid WHERE source_invoice_id = $invoice_id AND source_item_id = $oid");
            }
        }
    }

    function auragold_oj_scrap_remaining_gross($conn, $item_id, $original_gross, $invoice_id = 0)
    {
        $item_id = (int) $item_id;
        $invoice_id = (int) $invoice_id;
        if ($invoice_id > 0) {
            $stocked = auragold_oj_scrap_stocked_gross_sum_for_line_including_single_line_orphans($conn, $invoice_id, $item_id);
        } else {
            $stocked = auragold_oj_scrap_stocked_gross_sum($conn, $item_id);
        }

        return max(0.0, (float) $original_gross - $stocked);
    }

    function auragold_oj_scrap_sync_is_stocked_for_item($conn, $item_id)
    {
        $item_id = (int) $item_id;
        if ($item_id <= 0 || !$conn) {
            return;
        }
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items LIKE 'is_stocked'");
        if (!$c || mysqli_num_rows($c) === 0) {
            return;
        }
        mysqli_free_result($c);
        $row = getRecord("SELECT gross_wt, stocked_branch_id, invoice_id FROM tbl_old_jewelry_scrap_invoice_items WHERE id = $item_id LIMIT 1");
        if (!$row) {
            return;
        }
        $orig = (float) ($row['gross_wt'] ?? 0);
        $inv_id = (int) ($row['invoice_id'] ?? 0);
        $stocked = auragold_oj_scrap_stocked_gross_sum_for_line_including_single_line_orphans($conn, $inv_id, $item_id);
        $bid = (int) ($row['stocked_branch_id'] ?? 0);
        if (!empty($_SESSION['working_branch_id'])) {
            $bid = (int) $_SESSION['working_branch_id'];
        } elseif (!empty($_SESSION['branch_id'])) {
            $bid = (int) $_SESSION['branch_id'];
        }
        $br_sql = $bid > 0 ? (string) (int) $bid : 'NULL';
        if ($orig > 0 && $stocked + 0.00001 >= $orig) {
            mysqli_query($conn, "UPDATE tbl_old_jewelry_scrap_invoice_items SET is_stocked = 1, stocked_at = NOW(), stocked_branch_id = COALESCE(stocked_branch_id, $br_sql) WHERE id = $item_id");
        } else {
            mysqli_query($conn, "UPDATE tbl_old_jewelry_scrap_invoice_items SET is_stocked = 0, stocked_at = NULL, stocked_branch_id = NULL WHERE id = $item_id");
        }
    }
}
