<?php
if (!function_exists('createOutwardEntry')) {

/**
 * Create one outward entry from multiple selected inward barcodes.
 * Fetches inward records by barcode_no IN (...), sums numeric fields, inserts one outward row with opening_barcode and reference_barcodes, marks inward as sold.
 *
 * @param mysqli $conn
 * @param int $product_id
 * @param string $opening_barcode  e.g. RN00001
 * @param array $selected_barcodes  e.g. ['RN00001','RN00002','RN00003']
 * @return array ['outward_id' => int]
 * @throws Exception
 */
function createOutwardEntry($conn, $product_id, $opening_barcode, $selected_barcodes) {
    $product_id = (int)$product_id;
    $opening_barcode = mysqli_real_escape_string($conn, trim($opening_barcode));
    $selected_barcodes = array_values(array_filter(array_map('trim', $selected_barcodes)));
    if (empty($selected_barcodes)) {
        throw new Exception('No barcodes selected');
    }

    $in_list = implode(', ', array_map(function ($b) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $b) . "'";
    }, $selected_barcodes));

    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_inward_stock'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        if ($chk) mysqli_free_result($chk);
        throw new Exception('tbl_inward_stock does not exist');
    }
    mysqli_free_result($chk);
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_outward_stock'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        if ($chk) mysqli_free_result($chk);
        throw new Exception('tbl_outward_stock does not exist');
    }
    mysqli_free_result($chk);

    $ref_barcodes_str = implode(',', $selected_barcodes);
    $ref_esc = mysqli_real_escape_string($conn, $ref_barcodes_str);

    $ref_col = 'reference';
    $rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_outward_stock LIKE 'reference_barcodes'");
    if ($rc && mysqli_num_rows($rc) > 0) {
        mysqli_free_result($rc);
        $ref_col = 'reference_barcodes';
    } elseif ($rc) mysqli_free_result($rc);
    $existing = @mysqli_query($conn, "SELECT id FROM tbl_outward_stock WHERE barcode_no = '$opening_barcode' AND $ref_col = '$ref_esc' LIMIT 1");
    if ($existing && mysqli_num_rows($existing) > 0) {
        mysqli_free_result($existing);
        throw new Exception('Duplicate outward entry for this barcode set');
    }
    if ($existing) mysqli_free_result($existing);

    $cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_inward_stock");
    $numeric_sum = [];
    $first_branch = null;
    $first_metal = null;
    $first_char = null;
    $first_stock_type = null;
    $first_transaction_date = null;
    if ($cols) {
        while ($c = mysqli_fetch_assoc($cols)) {
            $f = $c['Field'] ?? '';
            $t = strtoupper($c['Type'] ?? '');
            if (in_array($f, ['id', 'stock_journal_id', 'product_characteristic_id', 'branch_id', 'metal_id', 'created_at', 'updated_at', 'barcode_no', 'stock_type', 'transaction_date'])) continue;
            if (preg_match('/^(int|decimal|numeric|float|double|real)/', $t)) $numeric_sum[] = $f;
        }
        mysqli_free_result($cols);
    }
    if (!in_array('qty', $numeric_sum)) $numeric_sum[] = 'qty';
    if (!in_array('weight', $numeric_sum)) $numeric_sum[] = 'weight';
    $numeric_sum = array_unique($numeric_sum);

    $sum_parts = [];
    foreach ($numeric_sum as $col) {
        $sum_parts[] = "COALESCE(SUM(" . mysqli_real_escape_string($conn, $col) . "), 0) AS " . mysqli_real_escape_string($conn, $col);
    }
    $sum_sql = implode(', ', $sum_parts);
    $status_where = '';
    $sc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_inward_stock LIKE 'status'");
    if ($sc && mysqli_num_rows($sc) > 0) {
        mysqli_free_result($sc);
        $status_where = " AND (status = 'available' OR status IS NULL OR status = '')";
    } elseif ($sc) mysqli_free_result($sc);

    $q = "SELECT $sum_sql,
             MAX(branch_id) AS branch_id,
             MAX(metal_id) AS metal_id,
             MAX(product_characteristic_id) AS product_characteristic_id,
             MAX(stock_type) AS stock_type,
             MAX(transaction_date) AS transaction_date
          FROM tbl_inward_stock
          WHERE barcode_no IN ($in_list) AND product_id = $product_id $status_where";
    $res = mysqli_query($conn, $q);
    if (!$res || mysqli_num_rows($res) === 0) {
        throw new Exception('No matching inward records found or already sold');
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);

    $out_cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_outward_stock");
    $out_has_ref = false;
    $out_has_purity = false;
    $out_has_amount = false;
    $out_has_rate = false;
    $out_has_value = false;
    $out_has_branch = false;
    $out_has_metal = false;
    $out_has_char = false;
    $out_has_stock_journal = false;
    $out_has_stock_type = false;
    $out_has_transaction_date = false;
    if ($out_cols) {
        while ($o = mysqli_fetch_assoc($out_cols)) {
            $f = $o['Field'] ?? '';
            if ($f === 'reference' || $f === 'reference_barcodes') $out_has_ref = true;
            if ($f === 'purity') $out_has_purity = true;
            if ($f === 'amount') $out_has_amount = true;
            if ($f === 'rate') $out_has_rate = true;
            if ($f === 'value') $out_has_value = true;
            if ($f === 'branch_id') $out_has_branch = true;
            if ($f === 'metal_id') $out_has_metal = true;
            if ($f === 'product_characteristic_id') $out_has_char = true;
            if ($f === 'stock_journal_id') $out_has_stock_journal = true;
            if ($f === 'stock_type') $out_has_stock_type = true;
            if ($f === 'transaction_date') $out_has_transaction_date = true;
        }
        mysqli_free_result($out_cols);
    }

    $ref_col_name = $ref_col;

    $ins_cols = ['barcode_no', 'product_id', 'qty', 'weight', 'created_at'];
    $ins_vals = ["'$opening_barcode'", "$product_id", (float)($row['qty'] ?? 0), (float)($row['weight'] ?? 0), 'NOW()'];
    if ($out_has_ref) {
        $ins_cols[] = $ref_col_name;
        $ins_vals[] = "'$ref_esc'";
    }
    if ($out_has_purity && isset($row['purity'])) {
        $ins_cols[] = 'purity';
        $ins_vals[] = (float)$row['purity'];
    }
    if ($out_has_amount && isset($row['amount'])) {
        $ins_cols[] = 'amount';
        $ins_vals[] = (float)$row['amount'];
    }
    if ($out_has_rate && isset($row['rate'])) {
        $ins_cols[] = 'rate';
        $ins_vals[] = (float)$row['rate'];
    }
    if ($out_has_value && isset($row['value'])) {
        $ins_cols[] = 'value';
        $ins_vals[] = (float)$row['value'];
    }
    if ($out_has_char && isset($row['product_characteristic_id']) && $row['product_characteristic_id']) {
        $ins_cols[] = 'product_characteristic_id';
        $ins_vals[] = (int)$row['product_characteristic_id'];
    }
    if ($out_has_branch && isset($row['branch_id']) && $row['branch_id']) {
        $ins_cols[] = 'branch_id';
        $ins_vals[] = (int)$row['branch_id'];
    }
    if ($out_has_metal && isset($row['metal_id']) && $row['metal_id']) {
        $ins_cols[] = 'metal_id';
        $ins_vals[] = (int)$row['metal_id'];
    }
    if ($out_has_stock_type && isset($row['stock_type'])) {
        $ins_cols[] = 'stock_type';
        $st = mysqli_real_escape_string($conn, $row['stock_type']);
        $ins_vals[] = "'$st'";
    }
    if ($out_has_transaction_date && isset($row['transaction_date']) && $row['transaction_date']) {
        $ins_cols[] = 'transaction_date';
        $ins_vals[] = "'" . mysqli_real_escape_string($conn, $row['transaction_date']) . "'";
    }

    $ins_sql = "INSERT INTO tbl_outward_stock (" . implode(', ', $ins_cols) . ") VALUES (" . implode(', ', $ins_vals) . ")";
    if (!mysqli_query($conn, $ins_sql)) {
        throw new Exception('Outward insert failed: ' . mysqli_error($conn));
    }
    $outward_id = mysqli_insert_id($conn);

    $upd_sql = "UPDATE tbl_inward_stock SET status = 'sold' WHERE barcode_no IN ($in_list) AND product_id = $product_id";
    $has_status = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_inward_stock LIKE 'status'");
    if ($has_status && mysqli_num_rows($has_status) > 0) {
        mysqli_free_result($has_status);
        if (!mysqli_query($conn, $upd_sql)) {
            throw new Exception('Update inward status failed: ' . mysqli_error($conn));
        }
    } elseif ($has_status) mysqli_free_result($has_status);

    return ['outward_id' => $outward_id];
}
}
