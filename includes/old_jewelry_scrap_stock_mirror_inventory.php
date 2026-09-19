<?php
/**
 * Post Old Jewelry Scrap stock-in to main inventory (tbl_stock) and stock history (tbl_stock_journal).
 * Shared by ajax/save-old-jewelry-scrap-invoice-internal.php and ajax/stock-in-old-jewelry-scrap-item.php.
 */

if (!function_exists('auragold_oj_scrap_resolve_line_product_ids')) {
    /**
     * @return array{0:int,1:int,2:int} product_id, product_characteristic_id, metal_id
     */
    function auragold_oj_scrap_resolve_line_product_ids(mysqli $conn, array $it, int $mid_it, int $branch_id): array
    {
        $pid = (int) ($it['product_id'] ?? 0);
        $pcid = (int) ($it['characteristic_id'] ?? $it['product_characteristic_id'] ?? 0);
        $mid = $mid_it > 0 ? $mid_it : (int) ($it['metal_id'] ?? 0);
        $desc_for_match = trim((string) ($it['description'] ?? $it['product_name'] ?? ''));
        if ($pid <= 0 && $desc_for_match !== '') {
            $d_esc = mysqli_real_escape_string($conn, $desc_for_match);
            $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND (name = '$d_esc' OR alternate_name = '$d_esc') LIMIT 1");
            if (!$pr) {
                $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND name LIKE '%" . mysqli_real_escape_string($conn, $desc_for_match) . "%' ORDER BY id ASC LIMIT 1");
            }
            if ($pr) {
                $pid = (int) $pr['id'];
            }
        }
        $bid = $branch_id > 0 ? $branch_id : 1;
        if ($pcid <= 0 && $pid > 0 && $mid > 0) {
            $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $pid AND metal_id = $mid AND branch_id = $bid AND status = 1 ORDER BY id DESC LIMIT 1");
            if (!$pcr) {
                $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $pid AND metal_id = $mid AND status = 1 ORDER BY id DESC LIMIT 1");
            }
            if ($pcr) {
                $pcid = (int) $pcr['id'];
            }
        }
        if ($mid <= 0) {
            $mid = 1;
        }
        return [$pid, $pcid, $mid];
    }
}

if (!function_exists('auragold_oj_scrap_mirror_tbl_stock_line')) {
    /**
     * Insert one row into tbl_stock (same rules as stock-in-old-jewelry-scrap-item.php).
     *
     * @return bool true if tbl_stock row was inserted, or tbl_stock table is absent (no-op); false if insert skipped or failed
     */
    function auragold_oj_scrap_mirror_tbl_stock_line(
        mysqli $conn,
        int $resolved_product_id,
        int $resolved_pc_id,
        int $resolved_metal_id,
        int $branch_id,
        string $product_raw_line,
        array $item_fallback,
        string $barcode,
        float $gross_wt,
        float $net_wt,
        float $final_wt,
        float $purity,
        float $quantity,
        float $rate,
        float $amount
    ): bool {
        $t_stock = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if (!$t_stock || mysqli_num_rows($t_stock) === 0) {
            return true;
        }
        mysqli_free_result($t_stock);
        $pid = (int) $resolved_product_id;
        $pcid = (int) $resolved_pc_id;
        $mid = $resolved_metal_id > 0 ? $resolved_metal_id : 1;
        $bid = $branch_id > 0 ? $branch_id : 1;
        $desc_for_match = trim((string) ($product_raw_line !== '' ? $product_raw_line : ($item_fallback['description'] ?? '')));
        if ($pid <= 0 && $desc_for_match !== '') {
            $d_esc = mysqli_real_escape_string($conn, $desc_for_match);
            $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND (name = '$d_esc' OR alternate_name = '$d_esc') LIMIT 1");
            if (!$pr) {
                $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND name LIKE '%" . mysqli_real_escape_string($conn, $desc_for_match) . "%' ORDER BY id ASC LIMIT 1");
            }
            if ($pr) {
                $pid = (int) $pr['id'];
            }
        }
        if ($pcid <= 0 && $pid > 0 && $mid > 0) {
            $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $pid AND metal_id = $mid AND branch_id = $bid AND status = 1 ORDER BY id DESC LIMIT 1");
            if (!$pcr) {
                $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $pid AND metal_id = $mid AND status = 1 ORDER BY id DESC LIMIT 1");
            }
            if ($pcr) {
                $pcid = (int) $pcr['id'];
            }
        }
        $barcode_trim = trim((string) $barcode);
        if ($barcode_trim === '') {
            return false;
        }
        if ($pid <= 0) {
            return false;
        }
        $stock_weight = $gross_wt > 0 ? $gross_wt : ($net_wt > 0 ? $net_wt : $final_wt);
        $stock_purity = (float) $purity;
        if ($stock_purity <= 0) {
            $stock_purity = 0.95;
        }
        $stock_value = (float) $amount;
        $fw = $final_wt > 0 ? $final_wt : $stock_weight;
        $bc_sql = "'" . mysqli_real_escape_string($conn, $barcode_trim) . "'";
        $txn_date = date('Y-m-d');
        $has_status = false;
        $stc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'status'");
        if ($stc && mysqli_num_rows($stc) > 0) {
            $has_status = true;
            mysqli_free_result($stc);
        }
        if ($has_status) {
            $stock_ins = "
                INSERT INTO tbl_stock (
                    product_id, product_characteristic_id, barcode, branch_id, metal_id,
                    opening_weight, opening_purity, opening_qty, final_weight, rate, value,
                    current_weight, current_qty, stock_type, transaction_date, created_at, status
                ) VALUES (
                    $pid, " . ($pcid > 0 ? $pcid : 'NULL') . ", $bc_sql, $bid, $mid,
                    $stock_weight, $stock_purity, $quantity, $fw, $rate, $stock_value,
                    $stock_weight, $quantity, 'purchase', '$txn_date', NOW(), 1
                )
            ";
        } else {
            $stock_ins = "
                INSERT INTO tbl_stock (
                    product_id, product_characteristic_id, barcode, branch_id, metal_id,
                    opening_weight, opening_purity, opening_qty, final_weight, rate, value,
                    current_weight, current_qty, stock_type, transaction_date, created_at
                ) VALUES (
                    $pid, " . ($pcid > 0 ? $pcid : 'NULL') . ", $bc_sql, $bid, $mid,
                    $stock_weight, $stock_purity, $quantity, $fw, $rate, $stock_value,
                    $stock_weight, $quantity, 'purchase', '$txn_date', NOW()
                )
            ";
        }
        if (!mysqli_query($conn, $stock_ins)) {
            error_log('Old Jewellery Scrap: tbl_stock insert failed: ' . mysqli_error($conn));
            return false;
        }
        return true;
    }
}

if (!function_exists('auragold_oj_scrap_insert_stock_history_journal_line')) {
    /**
     * Stock History / ledger row for this stock-in line. Returns journal id or 0.
     */
    function auragold_oj_scrap_insert_stock_history_journal_line(
        mysqli $conn,
        int $invoice_id,
        int $scrap_item_id,
        string $journal_key_suffix,
        string $invoice_no_plain,
        string $sj_date_ymd,
        string $barcode_plain,
        int $product_id,
        int $product_characteristic_id,
        int $metal_id,
        string $product_name_plain,
        float $gross_wt,
        float $less_wt,
        float $net_wt,
        float $final_wt,
        float $pure_wt,
        float $purity,
        float $quantity,
        float $rate,
        float $line_amount,
        string $voucher_type,
        string $group_name_plain,
        string $comment_plain,
        string $category_plain,
        string $location_plain
    ): int {
        require_once __DIR__ . '/stock_history_audit_journal.php';
        $jk = preg_replace('/[^A-Za-z0-9\-_:]/', '', (string) $journal_key_suffix);
        if ($jk === '') {
            $jk = (string) max(1, $scrap_item_id);
        }
        $sj_invoice_no = 'OJB-' . $invoice_id . '-' . $jk;
        $purity_pct = $purity;
        if ($purity_pct > 0 && $purity_pct <= 1.0001) {
            $purity_pct = $purity * 100;
        }
        if ($purity_pct <= 0) {
            $purity_pct = 100;
        }
        $nw = $net_wt > 0 ? $net_wt : ($gross_wt > 0 ? $gross_wt : $final_wt);
        $purity_weight = $nw * ($purity_pct / 100.0);
        $metal_type = auragold_stock_history_metal_type($conn, $metal_id);
        $net_line = $line_amount;
        // tbl_stock_journal.item_id / invoice_id FKs reference purchase invoice tables only — not scrap (OJB).
        // Use NULL via0 here; sj_invoice_no still ties the row to this scrap voucher + line.
        return auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => $sj_invoice_no,
            'item_id' => 0,
            'invoice_id' => 0,
            'invoice_no' => $invoice_no_plain,
            'sj_date' => $sj_date_ymd,
            'barcode' => $barcode_plain,
            'product_id' => $product_id,
            'product_characteristic_id' => $product_characteristic_id,
            'product_name' => $product_name_plain,
            'metal_id' => $metal_id,
            'metal_type' => $metal_type,
            'quantity' => $quantity > 0 ? $quantity : 1,
            'gross_weight' => $gross_wt,
            'less_weight' => $less_wt,
            'net_weight' => $nw,
            'purity' => $purity_pct,
            'purity_weight' => $purity_weight,
            'pure_weight' => $pure_wt > 0 ? $pure_wt : $purity_weight,
            'final_weight' => $final_wt > 0 ? $final_wt : $nw,
            'rate' => $rate,
            'amount' => $net_line,
            'making_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $net_line,
            'net_amt_with_tax' => $net_line,
            'voucher_type' => $voucher_type,
            'category' => $category_plain,
            'group_name' => $group_name_plain,
            'comment' => $comment_plain,
            'calculation' => 'Rate X Gross Wt',
            'location' => $location_plain,
        ]);
    }
}
