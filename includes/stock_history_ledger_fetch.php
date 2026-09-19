<?php

/**
 * Stock movement ledger query (tbl_stock_journal + optional MI union).
 * Shared by stock-history-ledger.php and export endpoints.
 *
 * @param array<string,mixed> $get Typically $_GET
 * @return array{
 *   rows: list<array<string,mixed>>,
 *   err: string,
 *   tot_qty: float,
 *   tot_gross: float,
 *   tot_pure: float,
 *   filter_count: int,
 *   adv_branch: int,
 *   adv_category: int,
 *   adv_barcode: string,
 *   adv_rfid: string,
 *   adv_date_from: string,
 *   adv_date_to: string,
 *   adv_metal: int,
 *   adv_product: list<int>,
 *   adv_article: string,
 *   adv_voucher_type: string,
 *   adv_against_voucher: string,
 *   adv_invoice_no: string,
 *   adv_gross_wt: string,
 *   adv_against_invoice_no: string
 * }
 */
function auragold_stock_history_ledger_fetch(mysqli $conn, array $get): array {
    $adv_branch = isset($get['adv_branch']) ? (int) $get['adv_branch'] : 0;
    if (function_exists('auragold_sanitize_adv_branch_for_login_scope')) {
        $adv_branch = auragold_sanitize_adv_branch_for_login_scope($adv_branch);
    }
    $adv_category = isset($get['adv_category']) ? (int) $get['adv_category'] : 0;
    $adv_barcode = isset($get['adv_barcode']) ? trim((string) $get['adv_barcode']) : '';
    $adv_rfid = isset($get['adv_rfid']) ? trim((string) $get['adv_rfid']) : '';
    $adv_date_from = isset($get['adv_date_from']) ? trim((string) $get['adv_date_from']) : '';
    $adv_date_to = isset($get['adv_date_to']) ? trim((string) $get['adv_date_to']) : '';
    $adv_metal = isset($get['adv_metal']) ? (int) $get['adv_metal'] : 0;
    $adv_product = isset($get['adv_product']) && is_array($get['adv_product']) ? array_filter(array_map('intval', $get['adv_product'])) : [];
    $adv_article = isset($get['adv_article']) ? trim((string) $get['adv_article']) : '';
    $adv_voucher_type = isset($get['adv_voucher_type']) ? trim((string) $get['adv_voucher_type']) : '';
    $adv_against_voucher = isset($get['adv_against_voucher']) ? trim((string) $get['adv_against_voucher']) : '';
    $adv_invoice_no = isset($get['adv_invoice_no']) ? trim((string) $get['adv_invoice_no']) : '';
    $adv_gross_wt = isset($get['adv_gross_wt']) ? trim((string) $get['adv_gross_wt']) : '';
    $adv_against_invoice_no = isset($get['adv_against_invoice_no']) ? trim((string) $get['adv_against_invoice_no']) : '';

    $filter_count = 0;
    if ($adv_branch > 0) {
        $filter_count++;
    }
    if ($adv_category > 0) {
        $filter_count++;
    }
    if ($adv_barcode !== '') {
        $filter_count++;
    }
    if ($adv_rfid !== '') {
        $filter_count++;
    }
    if ($adv_date_from !== '') {
        $filter_count++;
    }
    if ($adv_date_to !== '') {
        $filter_count++;
    }
    if ($adv_metal > 0) {
        $filter_count++;
    }
    if (!empty($adv_product)) {
        $filter_count++;
    }
    if ($adv_article !== '') {
        $filter_count++;
    }
    if ($adv_voucher_type !== '') {
        $filter_count++;
    }
    if ($adv_against_voucher !== '') {
        $filter_count++;
    }
    if ($adv_invoice_no !== '') {
        $filter_count++;
    }
    if ($adv_gross_wt !== '' && is_numeric(str_replace(',', '', $adv_gross_wt))) {
        $filter_count++;
    }
    if ($adv_against_invoice_no !== '') {
        $filter_count++;
    }

    $extra_where = '';
    if ($adv_branch > 0) {
        $bid = (int) $adv_branch;
        $extra_where .= " AND (
        COALESCE(b.id, 0) = $bid
        OR (
            COALESCE(b.id, 0) <> $bid
            AND EXISTS (
                SELECT 1 FROM tbl_stock s_f
                WHERE s_f.status = 1
                AND s_f.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return')
                AND s_f.branch_id = $bid
                AND sj.barcode IS NOT NULL AND TRIM(sj.barcode) <> ''
                AND s_f.barcode COLLATE utf8mb4_unicode_ci = sj.barcode COLLATE utf8mb4_unicode_ci
                LIMIT 1
            )
        )
        OR (
            sj.comment LIKE '%|src=mi|%'
            AND EXISTS (
                SELECT 1 FROM tbl_material_issues mi_f
                WHERE mi_f.branch_id = $bid
                AND sj.comment LIKE CONCAT('%|hid=', mi_f.id, '|%')
                LIMIT 1
            )
        )
    )";
    }
    if ($adv_category > 0) {
        $extra_where .= ' AND cat.id = ' . $adv_category;
    }
    if ($adv_barcode !== '') {
        $bc_like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_barcode) . '%';
        $bc_esc = mysqli_real_escape_string($conn, $bc_like);
        $extra_where .= " AND sj.barcode LIKE '$bc_esc' ";
    }
    if ($adv_rfid !== '') {
        $rf_like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_rfid) . '%';
        $rf_esc = mysqli_real_escape_string($conn, $rf_like);
        $extra_where .= " AND IFNULL(sj.rfid_code,'') LIKE '$rf_esc' ";
    }
    if ($adv_date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $adv_date_from)) {
        $df = mysqli_real_escape_string($conn, $adv_date_from);
        $extra_where .= " AND sj.sj_date >= '$df' ";
    }
    if ($adv_date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $adv_date_to)) {
        $dt = mysqli_real_escape_string($conn, $adv_date_to);
        $extra_where .= " AND sj.sj_date <= '$dt' ";
    }
    if ($adv_metal > 0) {
        $extra_where .= ' AND sj.metal_id = ' . $adv_metal;
    }
    if (!empty($adv_product)) {
        $ids = implode(',', array_map('intval', $adv_product));
        if ($ids !== '') {
            $extra_where .= " AND sj.product_id IN ($ids) ";
        }
    }
    if ($adv_article !== '') {
        $al = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_article) . '%';
        $al_esc = mysqli_real_escape_string($conn, $al);
        $extra_where .= " AND IFNULL(p.article,'') LIKE '$al_esc' ";
    }
    if ($adv_voucher_type !== '') {
        $vt = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_voucher_type) . '%';
        $vt_esc = mysqli_real_escape_string($conn, $vt);
        $extra_where .= " AND IFNULL(sj.voucher_type,'') LIKE '$vt_esc' ";
    }
    if ($adv_against_voucher !== '') {
        $av = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_against_voucher) . '%';
        $av_esc = mysqli_real_escape_string($conn, $av);
        $extra_where .= " AND IFNULL(jwo.jobwork_no,'') LIKE '$av_esc' ";
    }
    if ($adv_invoice_no !== '') {
        $inv = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_invoice_no) . '%';
        $inv_esc = mysqli_real_escape_string($conn, $inv);
        $extra_where .= " AND (IFNULL(sj.invoice_no,'') LIKE '$inv_esc' OR IFNULL(sj.sj_invoice_no,'') LIKE '$inv_esc') ";
    }
    if ($adv_gross_wt !== '') {
        $gw = str_replace(',', '', $adv_gross_wt);
        if (is_numeric($gw)) {
            $gwn = (float) $gw;
            $extra_where .= ' AND ABS(sj.gross_weight - ' . $gwn . ') < 0.0005 ';
        }
    }
    if ($adv_against_invoice_no !== '') {
        $ain = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_against_invoice_no) . '%';
        $ain_esc = mysqli_real_escape_string($conn, $ain);
        $extra_where .= " AND IFNULL(jwo.sale_order_no,'') LIKE '$ain_esc' ";
    }

    $shl_journal_inner = "
SELECT
    sj.id AS sj_id,
    sj.sj_date,
    sj.barcode,
    IFNULL(sj.rfid_code, '') AS rfid,
    sj.voucher_type,
    IFNULL(sj.location, '') AS location,
    IFNULL(NULLIF(TRIM(sj.invoice_no), ''), sj.sj_invoice_no) AS doc_invoice_no,
    sj.sj_invoice_no,
    sj.quantity AS qty,
    sj.gross_weight AS gross_wt,
    IFNULL(sj.pure_weight, 0) AS pure_wt,
    COALESCE(NULLIF(TRIM(sj.product_name), ''), p.name, '') AS product_name,
    IFNULL(m.display_name, '') AS metal_name,
    IFNULL(cat.name, '') AS category_name,
    IFNULL(p.article, '') AS article,
    IFNULL(jwo.sale_order_no, '') AS sale_order_no,
    IFNULL(jwo.sale_order_id, 0) AS sale_order_id,
    IFNULL(jwo.id, 0) AS jobwork_order_id,
    IFNULL(jwo.jobwork_no, '') AS jobwork_no,
    COALESCE(b.name, '') AS branch_name
FROM tbl_stock_journal sj
LEFT JOIN tbl_products p ON p.id = sj.product_id
LEFT JOIN tbl_metal m ON m.id = sj.metal_id
LEFT JOIN tbl_categories cat ON cat.id = p.category_id
LEFT JOIN tbl_jobwork_invoices jwi ON sj.comment LIKE CONCAT('auragold_jwi|jwi_id=', jwi.id, '|%')
LEFT JOIN tbl_jobwork_orders jwo ON jwo.id = jwi.jobwork_order_id
LEFT JOIN tbl_product_characteristics pc ON pc.id = sj.product_characteristic_id AND pc.status = 1
LEFT JOIN tbl_branches b ON b.id = pc.branch_id
WHERE sj.status = 'active'
$extra_where";

    $shl_union_mi_outward = '';
    if ($adv_barcode !== '') {
        $bc_like2 = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_barcode) . '%';
        $bc_esc2 = mysqli_real_escape_string($conn, $bc_like2);
        $union_branch_sql = '';
        if ($adv_branch > 0) {
            $bid_u = (int) $adv_branch;
            $union_branch_sql = " AND (s.branch_id = $bid_u OR s.branch_id IS NULL OR s.branch_id = 0) ";
        }
        $shl_union_mi_outward = "
UNION ALL
SELECT
    (2000000000 + s.id) AS sj_id,
    COALESCE(CAST(NULLIF(s.transaction_date, '0000-00-00') AS DATE), DATE(s.created_at)) AS sj_date,
    IFNULL(NULLIF(TRIM(s.barcode), ''), '') AS barcode,
    '' AS rfid,
    'Material Issue' AS voucher_type,
    '' AS location,
    IFNULL(mi.material_issue_no, '') AS doc_invoice_no,
    CONCAT('MI-STK', s.id) AS sj_invoice_no,
    IFNULL(NULLIF(s.current_qty, 0), s.opening_qty, 0) AS qty,
    IFNULL(NULLIF(s.current_weight, 0), s.opening_weight, 0) AS gross_wt,
    (IFNULL(NULLIF(s.current_weight, 0), s.opening_weight, 0) * (CASE WHEN IFNULL(s.opening_purity, 0) <= 1 THEN IFNULL(s.opening_purity, 0) ELSE IFNULL(s.opening_purity, 0) / 100 END)) AS pure_wt,
    COALESCE(p.name, '') AS product_name,
    IFNULL(m.display_name, '') AS metal_name,
    IFNULL(cat.name, '') AS category_name,
    IFNULL(p.article, '') AS article,
    IFNULL(mi.sale_order_no, '') AS sale_order_no,
    IFNULL(mi.sale_order_id, 0) AS sale_order_id,
    0 AS jobwork_order_id,
    '' AS jobwork_no,
    COALESCE(b.name, '') AS branch_name
FROM tbl_stock s
INNER JOIN tbl_material_issues mi ON mi.id = s.reference_id
LEFT JOIN tbl_products p ON p.id = s.product_id
LEFT JOIN tbl_metal m ON m.id = s.metal_id
LEFT JOIN tbl_categories cat ON cat.id = p.category_id
LEFT JOIN tbl_product_characteristics pc ON pc.id = s.product_characteristic_id AND pc.status = 1
LEFT JOIN tbl_branches b ON b.id = s.branch_id
WHERE s.status = 1 AND s.stock_type = 'outward' AND s.reference_type = 'material_issue'
AND NULLIF(TRIM(s.barcode), '') IS NOT NULL
AND s.barcode LIKE '$bc_esc2'
$union_branch_sql
AND NOT EXISTS (
    SELECT 1 FROM tbl_stock_journal sj_dedup
    WHERE sj_dedup.status = 'active'
    AND TRIM(IFNULL(sj_dedup.voucher_type, '')) = 'Material Issue'
    AND sj_dedup.barcode COLLATE utf8mb4_unicode_ci = s.barcode COLLATE utf8mb4_unicode_ci
    AND DATE(sj_dedup.sj_date) = DATE(COALESCE(NULLIF(s.transaction_date, '0000-00-00'), s.created_at))
    AND ABS(COALESCE(sj_dedup.gross_weight, 0) - IFNULL(NULLIF(s.current_weight, 0), s.opening_weight, 0)) < 0.02
)";
    }

    $sql = "
SELECT * FROM (
$shl_journal_inner
$shl_union_mi_outward
) AS shl_rows
ORDER BY (DATE(shl_rows.sj_date) = CURDATE()) DESC, shl_rows.sj_date DESC, shl_rows.sj_id DESC
LIMIT 5000
";

    $rows = [];
    $err = '';
    $q = @mysqli_query($conn, $sql);
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $rows[] = $r;
        }
        mysqli_free_result($q);
    } else {
        $err = mysqli_error($conn);
    }

    $tot_qty = 0.0;
    $tot_gross = 0.0;
    $tot_pure = 0.0;
    foreach ($rows as $r) {
        $tot_qty += (float) ($r['qty'] ?? 0);
        $tot_gross += (float) ($r['gross_wt'] ?? 0);
        $tot_pure += (float) ($r['pure_wt'] ?? 0);
    }

    return [
        'rows' => $rows,
        'err' => $err,
        'tot_qty' => $tot_qty,
        'tot_gross' => $tot_gross,
        'tot_pure' => $tot_pure,
        'filter_count' => $filter_count,
        'adv_branch' => $adv_branch,
        'adv_category' => $adv_category,
        'adv_barcode' => $adv_barcode,
        'adv_rfid' => $adv_rfid,
        'adv_date_from' => $adv_date_from,
        'adv_date_to' => $adv_date_to,
        'adv_metal' => $adv_metal,
        'adv_product' => $adv_product,
        'adv_article' => $adv_article,
        'adv_voucher_type' => $adv_voucher_type,
        'adv_against_voucher' => $adv_against_voucher,
        'adv_invoice_no' => $adv_invoice_no,
        'adv_gross_wt' => $adv_gross_wt,
        'adv_against_invoice_no' => $adv_against_invoice_no,
    ];
}

/**
 * Display label for voucher type column (matches stock-history-ledger.php on-screen).
 */
function auragold_stock_history_ledger_voucher_display(string $voucherType): string {
    return $voucherType === 'Stock Transfer (In)' ? 'Stock Receive' : $voucherType;
}
