 * @return array|null  Keys: id, label_size_preset, label_width_mm, label_height_mm, font_size,
 *                     show_product_name, show_price, show_barcode_number, print_copies,
 *                     barcode_bar_width, barcode_bar_height (when columns exist), metal_type, design_layout
 */
function getBarcodeSettings() {
    global $conn;
    $table = 'tbl_barcode_settings';
    $exists = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$exists || mysqli_num_rows($exists) === 0) {
        if ($exists) mysqli_free_result($exists);
        return null;
    }
    mysqli_free_result($exists);
    auragold_ensure_branch_id_on_settings_tables($conn);
    $bid = auragold_settings_branch_id();
    $hasBranch = auragold_tbl_has_column($conn, $table, 'branch_id');
    $branchSql = ($hasBranch && $bid > 0) ? (' WHERE branch_id = ' . (int) $bid) : '';
    $colsBase = "id, label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies";
    $splitShowCols = [
        'show_product_name_barcode', 'show_product_name_qr',
        'show_price_barcode', 'show_price_qr',
        'show_barcode_number_barcode', 'show_barcode_number_qr',
    ];
    foreach ($splitShowCols as $sc) {
        if (auragold_tbl_has_column($conn, $table, $sc)) {
            $colsBase .= ', `' . $sc . '`';
        }
    }
    $chkBw = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'barcode_bar_width'");
    $hasBarcodeDims = ($chkBw && mysqli_num_rows($chkBw) > 0);
    if ($chkBw) {
        mysqli_free_result($chkBw);
    }
    if ($hasBarcodeDims) {
        $colsBase .= ", barcode_bar_width, barcode_bar_height";
    }
    $colsBase .= ", metal_type";
    $row = getRecord("SELECT $colsBase FROM $table $branchSql ORDER BY id DESC LIMIT 1");
    if (!$row && $hasBranch && $bid > 0) {
        $row = getRecord("SELECT $colsBase FROM $table WHERE (branch_id IS NULL OR branch_id = 0) ORDER BY id DESC LIMIT 1");
    }
    if (!$row && $branchSql !== '') {
        $row = getRecord("SELECT $colsBase FROM $table ORDER BY id DESC LIMIT 1");
    }
    if ($row && !empty($row['id'])) {