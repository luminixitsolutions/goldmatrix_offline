<?php
/**
 * Insert a single tbl_stock_journal audit row for Stock History (ledger).
 * Does NOT insert tbl_stock — callers that already post stock only need this for history visibility.
 *
 * Comment tag examples:
 *   auragold_doc|src=pi|iid=12|pii=34|
 *   auragold_doc|src=si|iid=5|sii=9|
 */

if (!function_exists('auragold_stock_history_audit_user_id')) {
    function auragold_stock_history_audit_user_id(): int
    {
        if (isset($_SESSION['Admin']['id'])) {
            return (int) $_SESSION['Admin']['id'];
        }
        if (isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
        return 0;
    }

    /**
     * Insert one stock journal row. Returns new journal id or 0 on skip/failure (logs error, no throw).
     */
    function auragold_stock_history_audit_insert_row(mysqli $conn, array $p): int
    {
        $product_id = (int) ($p['product_id'] ?? 0);
        $barcode = isset($p['barcode']) ? trim((string) $p['barcode']) : '';
        if ($product_id <= 0 && $barcode === '') {
            return 0;
        }

        $sj_invoice_no = isset($p['sj_invoice_no']) ? trim((string) $p['sj_invoice_no']) : '';
        if ($sj_invoice_no === '') {
            return 0;
        }

        $item_id = isset($p['item_id']) ? (int) $p['item_id'] : 0;
        $invoice_id = isset($p['invoice_id']) ? (int) $p['invoice_id'] : 0;
        $item_sql = $item_id > 0 ? (string) $item_id : 'NULL';
        $invoice_sql = $invoice_id > 0 ? (string) $invoice_id : 'NULL';

        $invoice_no = isset($p['invoice_no']) ? trim((string) $p['invoice_no']) : '';
        $invoice_no_esc = mysqli_real_escape_string($conn, $invoice_no);
        $sj_date = isset($p['sj_date']) ? trim((string) $p['sj_date']) : date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sj_date)) {
            $sj_date = date('Y-m-d');
        }
        $sj_date_esc = mysqli_real_escape_string($conn, $sj_date);

        $barcode_esc = mysqli_real_escape_string($conn, $barcode);
        $product_name_esc = mysqli_real_escape_string($conn, (string) ($p['product_name'] ?? ''));
        $characteristic_id = isset($p['product_characteristic_id']) ? (int) $p['product_characteristic_id'] : 0;
        $metal_id = isset($p['metal_id']) ? (int) $p['metal_id'] : 0;

        $metal_type = isset($p['metal_type']) ? trim((string) $p['metal_type']) : '';
        $metal_type_esc = $metal_type !== '' ? "'" . mysqli_real_escape_string($conn, $metal_type) . "'" : 'NULL';

        $quantity = (float) ($p['quantity'] ?? 0);
        if ($quantity <= 0) {
            $quantity = 1;
        }
        $gross_weight = (float) ($p['gross_weight'] ?? 0);
        $less_weight = (float) ($p['less_weight'] ?? 0);
        $net_weight = (float) ($p['net_weight'] ?? 0);
        $purity = (float) ($p['purity'] ?? 0);
        $purity_weight = (float) ($p['purity_weight'] ?? 0);
        $pure_weight = (float) ($p['pure_weight'] ?? 0);
        $final_weight = (float) ($p['final_weight'] ?? 0);
        $rate = (float) ($p['rate'] ?? 0);
        $amount = (float) ($p['amount'] ?? 0);
        $making_amount = (float) ($p['making_amount'] ?? 0);
        $tax_amount = (float) ($p['tax_amount'] ?? 0);
        $net_amount = (float) ($p['net_amount'] ?? 0);
        $net_amt_with_tax = (float) ($p['net_amt_with_tax'] ?? 0);

        $design_no = isset($p['design_no']) ? trim((string) $p['design_no']) : '';
        $design_esc = $design_no !== '' ? "'" . mysqli_real_escape_string($conn, $design_no) . "'" : 'NULL';

        $category_esc = 'NULL';
        if (!empty($p['category'])) {
            $category_esc = "'" . mysqli_real_escape_string($conn, (string) $p['category']) . "'";
        }

        $voucher_esc = mysqli_real_escape_string($conn, (string) ($p['voucher_type'] ?? 'Document'));
        $comment_esc = mysqli_real_escape_string($conn, (string) ($p['comment'] ?? ''));
        $sj_no_esc = mysqli_real_escape_string($conn, $sj_invoice_no);

        $rfid = isset($p['rfid_code']) ? trim((string) $p['rfid_code']) : '';
        $rfid_sql = $rfid !== '' ? "'" . mysqli_real_escape_string($conn, $rfid) . "'" : 'NULL';

        $user_id = auragold_stock_history_audit_user_id();

        static $stock_journal_columns = null;
        if ($stock_journal_columns === null) {
            $stock_journal_columns = [];
            $col_rs = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_journal");
            if ($col_rs) {
                while ($col = mysqli_fetch_assoc($col_rs)) {
                    $field = strtolower((string) ($col['Field'] ?? ''));
                    if ($field !== '') {
                        $stock_journal_columns[$field] = true;
                    }
                }
                mysqli_free_result($col_rs);
            }
        }

        $group_name = isset($p['group_name']) ? trim((string) $p['group_name']) : '';
        $group_name_sql = $group_name !== '' ? "'" . mysqli_real_escape_string($conn, $group_name) . "'" : 'NULL';
        $calculation = isset($p['calculation']) ? trim((string) $p['calculation']) : '';
        $calculation_sql = $calculation !== '' ? "'" . mysqli_real_escape_string($conn, $calculation) . "'" : 'NULL';
        $location = isset($p['location']) ? trim((string) $p['location']) : '';
        $location_sql = $location !== '' ? "'" . mysqli_real_escape_string($conn, $location) . "'" : 'NULL';

        $candidate_values = [
            'sj_invoice_no' => "'$sj_no_esc'",
            'item_id' => $item_sql,
            'invoice_id' => $invoice_sql,
            'invoice_no' => "'$invoice_no_esc'",
            'sj_date' => "'$sj_date_esc'",
            'barcode' => ($barcode !== '' ? "'$barcode_esc'" : 'NULL'),
            'code' => 'NULL',
            'product_id' => ($product_id > 0 ? (string) $product_id : 'NULL'),
            'product_characteristic_id' => ($characteristic_id > 0 ? (string) $characteristic_id : 'NULL'),
            'product_name' => "'$product_name_esc'",
            'metal_id' => ($metal_id > 0 ? (string) $metal_id : 'NULL'),
            'metal_type' => $metal_type_esc,
            'quantity' => (string) $quantity,
            'karat' => 'NULL',
            'gross_weight' => (string) $gross_weight,
            'less_weight' => (string) $less_weight,
            'net_weight' => (string) $net_weight,
            'purity' => (string) $purity,
            'purity_weight' => (string) $purity_weight,
            'pure_weight' => (string) $pure_weight,
            'final_weight' => (string) $final_weight,
            'rate' => (string) $rate,
            'amount' => (string) $amount,
            'making_amount' => (string) $making_amount,
            'tax_amount' => (string) $tax_amount,
            'net_amount' => (string) $net_amount,
            'net_amt_with_tax' => (string) $net_amt_with_tax,
            'rfid_code' => $rfid_sql,
            'voucher_type' => "'$voucher_esc'",
            'design_no' => $design_esc,
            'huid_no' => 'NULL',
            'category' => $category_esc,
            'calculation' => $calculation_sql,
            'location' => $location_sql,
            'pkt_wt' => 'NULL',
            'pkt_less_wt' => 'NULL',
            'requested_purity' => 'NULL',
            'requested' => 'NULL',
            'gold_loss_1' => 'NULL',
            'gold_loss_2' => 'NULL',
            'setting_charge' => 'NULL',
            'wastage_per' => 'NULL',
            'wastage_wt' => 'NULL',
            'alloy_wt' => 'NULL',
            'metal_value' => 'NULL',
            'metal_cost' => 'NULL',
            'discount_type' => 'NULL',
            'discount_per' => 'NULL',
            'discount_amount' => 'NULL',
            'discount' => 'NULL',
            'making_type' => 'NULL',
            'making_rate' => 'NULL',
            'making_cost' => 'NULL',
            'minimum_price' => 'NULL',
            'stone_charge_type' => 'NULL',
            'stone_weight' => 'NULL',
            'stone_rate' => 'NULL',
            'stone_amount' => 'NULL',
            'stone_cost' => 'NULL',
            'diamond_amount' => 'NULL',
            'purchase_amount' => 'NULL',
            'sale_amount' => 'NULL',
            'other_charge_type' => 'NULL',
            'other_weight' => 'NULL',
            'other_rate' => 'NULL',
            'other_info' => 'NULL',
            'other_amount' => 'NULL',
            'hallmark_amount' => 'NULL',
            'hallmark_rate' => 'NULL',
            'reverse' => 'NULL',
            'group_name' => $group_name_sql,
            'comment' => "'$comment_esc'",
            'status' => "'active'",
            'created_by' => (string) $user_id,
            'created_at' => 'NOW()',
        ];

        $journal_cols = [];
        $journal_vals = [];
        foreach ($candidate_values as $col_name => $sql_value) {
            if (!empty($stock_journal_columns[$col_name])) {
                $journal_cols[] = $col_name;
                $journal_vals[] = $sql_value;
            }
        }

        if (empty($journal_cols) || count($journal_cols) !== count($journal_vals)) {
            error_log('auragold_stock_history_audit_insert_row: invalid column/value map');
            return 0;
        }

        $journal_sql = "INSERT INTO tbl_stock_journal (" . implode(', ', $journal_cols) . ") VALUES (" . implode(', ', $journal_vals) . ")";

        @mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE sj_invoice_no = '$sj_no_esc' LIMIT 1");

        if (!@mysqli_query($conn, $journal_sql)) {
            error_log('auragold_stock_history_audit_insert_row: ' . mysqli_error($conn));
            return 0;
        }
        return (int) mysqli_insert_id($conn);
    }

    function auragold_stock_history_metal_type(mysqli $conn, int $metal_id): string
    {
        if ($metal_id <= 0) {
            return '';
        }
        $row = getRecord("SELECT system_name, display_name FROM tbl_metal WHERE id = $metal_id LIMIT 1");
        if (!$row) {
            return '';
        }
        $raw = trim((string) ($row['system_name'] ?? $row['display_name'] ?? ''));
        return $raw;
    }

    /**
     * Stock History (ledger) row for a saved document line. Inserts tbl_stock_journal with voucher_type_label.
     * Resolves blank barcode from tbl_product_characteristics or tbl_stock (same branch / metal / PC when possible)
     * so Material Issue and similar lines still create ledger rows.
     * Comment: auragold_doc|src={tag}|hid={header}|lid={line}|
     *
     * @param array<string,mixed> $line
     */
    function auragold_stock_history_audit_for_document_barcode_line(
        mysqli $conn,
        string $voucher_type_label,
        string $document_no,
        string $document_date_ymd,
        string $sj_key_prefix,
        int $header_numeric_id,
        int $line_numeric_id,
        string $comment_src_tag,
        array $line
    ): int {
        $barcode = trim((string) ($line['barcode'] ?? $line['barcode_no'] ?? ''));
        $pid_resolve = (int) ($line['product_id'] ?? 0);
        $pcid_resolve = (int) ($line['product_characteristic_id'] ?? $line['characteristic_id'] ?? 0);
        if ($barcode === '' && $pcid_resolve > 0 && function_exists('getRecord')) {
            $bpc = getRecord('SELECT NULLIF(TRIM(barcode), \'\') AS bc FROM tbl_product_characteristics WHERE id = ' . $pcid_resolve . ' AND status = 1 LIMIT 1');
            if ($bpc && !empty($bpc['bc'])) {
                $barcode = trim((string) $bpc['bc']);
            }
        }
        if ($barcode === '' && $pid_resolve > 0 && function_exists('getRecord')) {
            $bst = '\'opening\',\'purchase\',\'stock_journal\',\'balance\',\'sale_return\',\'inward\'';
            $mid_ln = (int) ($line['metal_id'] ?? 0);
            $metal_sql = $mid_ln > 0 ? ' AND metal_id = ' . $mid_ln : '';
            $pc_sql = $pcid_resolve > 0 ? ' AND product_characteristic_id = ' . $pcid_resolve : '';
            $bs = getRecord('SELECT NULLIF(TRIM(barcode), \'\') AS bc FROM tbl_stock WHERE status = 1 AND product_id = ' . $pid_resolve . ' AND stock_type IN (' . $bst . ')' . $metal_sql . $pc_sql . ' AND NULLIF(TRIM(barcode), \'\') IS NOT NULL ORDER BY id DESC LIMIT 1');
            if ($bs && !empty($bs['bc'])) {
                $barcode = trim((string) $bs['bc']);
            }
            if ($barcode === '' && ($metal_sql !== '' || $pc_sql !== '')) {
                $bs2 = getRecord('SELECT NULLIF(TRIM(barcode), \'\') AS bc FROM tbl_stock WHERE status = 1 AND product_id = ' . $pid_resolve . ' AND stock_type IN (' . $bst . ') AND NULLIF(TRIM(barcode), \'\') IS NOT NULL ORDER BY id DESC LIMIT 1');
                if ($bs2 && !empty($bs2['bc'])) {
                    $barcode = trim((string) $bs2['bc']);
                }
            }
        }
        if ($barcode === '') {
            return 0;
        }
        $doc_no = trim($document_no);
        if ($doc_no === '' || $header_numeric_id <= 0 || $line_numeric_id <= 0) {
            return 0;
        }
        $d = trim($document_date_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $d = date('Y-m-d');
        }
        $sj_key = $sj_key_prefix . $header_numeric_id . 'I' . $line_numeric_id;
        if (strlen($sj_key) > 48) {
            $sj_key = substr($sj_key_prefix, 0, 1) . $header_numeric_id . 'x' . $line_numeric_id;
        }
        $pid = (int) ($line['product_id'] ?? 0);
        $pcid = (int) ($line['product_characteristic_id'] ?? $line['characteristic_id'] ?? 0);
        $pname = trim((string) ($line['product_name'] ?? ''));
        if ($pname === '' && $pid > 0 && function_exists('getRecord')) {
            $pn_row = getRecord("SELECT name FROM tbl_products WHERE id = $pid LIMIT 1");
            if ($pn_row && isset($pn_row['name'])) {
                $pname = trim((string) $pn_row['name']);
            }
        }
        $mid = (int) ($line['metal_id'] ?? 0);
        if ($mid <= 0 && $pcid > 0 && function_exists('getRecord')) {
            $ch = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE id = $pcid AND status = 1 LIMIT 1");
            if ($ch) {
                $mid = (int) ($ch['metal_id'] ?? 0);
            }
        }
        if ($mid <= 0 && $pid > 0 && function_exists('getRecord')) {
            $dm = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE product_id = $pid AND status = 1 ORDER BY id DESC LIMIT 1");
            if ($dm) {
                $mid = (int) ($dm['metal_id'] ?? 0);
            }
        }
        $qty = (float) ($line['quantity'] ?? 1);
        if ($qty <= 0) {
            $qty = 1;
        }
        $gw = (float) ($line['gross_weight'] ?? 0);
        $lw = (float) ($line['less_weight'] ?? 0);
        $nw = (float) ($line['net_weight'] ?? 0);
        $pur = (float) ($line['purity'] ?? 0);
        $pw = (float) ($line['purity_weight'] ?? 0);
        $purew = (float) ($line['pure_weight'] ?? 0);
        $fw = (float) ($line['final_weight'] ?? 0);
        $rate = (float) ($line['rate'] ?? 0);
        $amt = (float) ($line['amount'] ?? 0);
        $mk = (float) ($line['making_amount'] ?? 0);
        $tax = (float) ($line['tax_amount'] ?? $line['tax'] ?? 0);
        $net = (float) ($line['net_amount'] ?? 0);
        $nett = (float) ($line['net_amt_with_tax'] ?? 0);
        $rfid = trim((string) ($line['rfid_code'] ?? $line['rfid'] ?? ''));
        $design = trim((string) ($line['design_no'] ?? ''));
        $cat = trim((string) ($line['category'] ?? $line['diamond_category'] ?? ''));
        $calc = trim((string) ($line['calculation'] ?? $line['calculation_mode'] ?? ''));
        $loc = trim((string) ($line['location'] ?? ''));

        return auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => $sj_key,
            'item_id' => 0,
            'invoice_id' => 0,
            'invoice_no' => $doc_no,
            'sj_date' => $d,
            'barcode' => $barcode,
            'product_id' => $pid,
            'product_characteristic_id' => $pcid,
            'product_name' => $pname,
            'metal_id' => $mid,
            'metal_type' => auragold_stock_history_metal_type($conn, $mid),
            'quantity' => $qty,
            'gross_weight' => $gw,
            'less_weight' => $lw,
            'net_weight' => $nw,
            'purity' => $pur,
            'purity_weight' => $pw,
            'pure_weight' => $purew,
            'final_weight' => $fw,
            'rate' => $rate,
            'amount' => $amt,
            'making_amount' => $mk,
            'tax_amount' => $tax,
            'net_amount' => $net,
            'net_amt_with_tax' => $nett,
            'rfid_code' => $rfid,
            'voucher_type' => $voucher_type_label,
            'design_no' => $design,
            'category' => $cat,
            'calculation' => $calc,
            'location' => $loc,
            'comment' => 'auragold_doc|src=' . $comment_src_tag . '|hid=' . $header_numeric_id . '|lid=' . $line_numeric_id . '|',
        ]);
    }
}
