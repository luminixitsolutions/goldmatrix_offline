<?php 
session_start();
require_once 'config.php';

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'scrap';

// Align string column collations in UNION ALL (sale vs OJB tables) — avoids MySQL "Illegal mix of collations"
$oj_uc = 'utf8mb4_unicode_ci';

// Purchase invoice line items: optional columns (older DBs may lack location_id or active)
$pii_has_active = false;
$pii_has_location_id = false;
$_pac = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'active'");
if ($_pac && mysqli_num_rows($_pac) > 0) {
    $pii_has_active = true;
}
$_plc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'location_id'");
if ($_plc && mysqli_num_rows($_plc) > 0) {
    $pii_has_location_id = true;
}
$pii_active_sql = $pii_has_active ? 'IFNULL(pii.active, 1) = 1' : 'IFNULL(pii.status, 1) = 1';
$pii_location_join = $pii_has_location_id ? 'LEFT JOIN tbl_location l ON pii.location_id = l.id' : '';
$pii_location_id_sel = $pii_has_location_id ? 'pii.location_id' : '0';
$pii_location_name_sel = $pii_has_location_id ? "CONVERT(COALESCE(l.name, '') USING utf8mb4) COLLATE $oj_uc" : "CONVERT('' USING utf8mb4) COLLATE $oj_uc";

// Get filters
$date_range = isset($_GET['date_range']) ? esc($_GET['date_range']) : '';
$customer_name_parts = [];
if (isset($_GET['customer_name'])) {
    if (is_array($_GET['customer_name'])) {
        foreach ($_GET['customer_name'] as $_cn) {
            $t = trim((string) $_cn);
            if ($t !== '') {
                $customer_name_parts[] = $t;
            }
        }
    } else {
        $t = trim((string) $_GET['customer_name']);
        if ($t !== '') {
            $customer_name_parts[] = $t;
        }
    }
}
$invoice_no = isset($_GET['invoice_no']) ? esc($_GET['invoice_no']) : '';
$due_from_date = isset($_GET['due_from_date']) ? esc($_GET['due_from_date']) : '';
$due_to_date = isset($_GET['due_to_date']) ? esc($_GET['due_to_date']) : '';
$assign_to_ids = [];
if (isset($_GET['assign_to']) && is_array($_GET['assign_to'])) {
    foreach ($_GET['assign_to'] as $_aid) {
        $aid = (int) $_aid;
        if ($aid > 0) {
            $assign_to_ids[$aid] = $aid;
        }
    }
}
$assign_to_ids = array_values($assign_to_ids);
$jwo_status_vals = [];
if (isset($_GET['jwo_status']) && is_array($_GET['jwo_status'])) {
    foreach ($_GET['jwo_status'] as $_st) {
        $st = trim((string) $_st);
        if ($st !== '') {
            $jwo_status_vals[] = $st;
        }
    }
}
$jwo_priority_vals = [];
if (isset($_GET['jwo_priority']) && is_array($_GET['jwo_priority'])) {
    foreach ($_GET['jwo_priority'] as $_pr) {
        $pr = trim((string) $_pr);
        if ($pr !== '') {
            $jwo_priority_vals[] = $pr;
        }
    }
}
$adv_group_name = isset($_GET['adv_group_name']) ? trim((string) $_GET['adv_group_name']) : '';
$adv_jobwork_no = isset($_GET['adv_jobwork_no']) ? trim((string) $_GET['adv_jobwork_no']) : '';
$adv_tag_no = isset($_GET['adv_tag_no']) ? trim((string) $_GET['adv_tag_no']) : '';
$adv_gross_wt = isset($_GET['adv_gross_wt']) ? trim((string) $_GET['adv_gross_wt']) : '';
$adv_rfid = isset($_GET['adv_rfid']) ? trim((string) $_GET['adv_rfid']) : '';
$adv_against_ref = isset($_GET['adv_against_ref']) ? trim((string) $_GET['adv_against_ref']) : '';
$adv_short_desc = isset($_GET['adv_short_desc']) ? trim((string) $_GET['adv_short_desc']) : '';
$metal_id = isset($_GET['metal_id']) ? (int)$_GET['metal_id'] : 0;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$location_id = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;
$sales_person = isset($_GET['sales_person']) ? esc($_GET['sales_person']) : '';
$source = isset($_GET['source']) ? esc($_GET['source']) : '';
$against_invoice_no = isset($_GET['against_invoice_no']) ? esc($_GET['against_invoice_no']) : '';

// Parse date range if provided
$from_date = '';
$to_date = '';
if (!empty($date_range)) {
    $dates = explode(' - ', $date_range);
    if (count($dates) == 2) {
        $from_date = trim($dates[0]);
        $to_date = trim($dates[1]);
    }
} else {
    $from_date = isset($_GET['from_date']) ? esc($_GET['from_date']) : date('Y-m-01');
    $to_date = isset($_GET['to_date']) ? esc($_GET['to_date']) : date('Y-m-t');
}

// Branch filter: multiple values via branch_id[]=1&branch_id[]=2 or comma-separated branch_id=1,2
$branch_ids = [];
if (isset($_GET['branch_id'])) {
    $raw_b = $_GET['branch_id'];
    if (is_array($raw_b)) {
        foreach ($raw_b as $bid) {
            $bid = (int) $bid;
            if ($bid > 0) {
                $branch_ids[$bid] = $bid;
            }
        }
    } else {
        $raw_b = trim((string) $raw_b);
        if ($raw_b !== '') {
            foreach (preg_split('/\s*,\s*/', $raw_b) as $part) {
                $bid = (int) $part;
                if ($bid > 0) {
                    $branch_ids[$bid] = $bid;
                }
            }
        }
    }
}
$branch_ids = array_values($branch_ids);
$branch_id_in_sql = '';
if (!empty($branch_ids)) {
    $branch_id_in_sql = implode(',', array_map('intval', $branch_ids));
}

// Preserve filters in tab links
$oj_tab_extra = '';
if (!empty($from_date)) {
    $oj_tab_extra .= '&from_date=' . rawurlencode($from_date);
}
if (!empty($to_date)) {
    $oj_tab_extra .= '&to_date=' . rawurlencode($to_date);
}
foreach ($branch_ids as $bid) {
    $oj_tab_extra .= '&branch_id[]=' . (int) $bid;
}
foreach ($customer_name_parts as $cn) {
    $oj_tab_extra .= '&customer_name[]=' . rawurlencode($cn);
}
if ($invoice_no !== '') {
    $oj_tab_extra .= '&invoice_no=' . rawurlencode($invoice_no);
}
if ($due_from_date !== '' && $due_to_date !== '') {
    $oj_tab_extra .= '&due_from_date=' . rawurlencode($due_from_date) . '&due_to_date=' . rawurlencode($due_to_date);
}
foreach ($assign_to_ids as $aid) {
    $oj_tab_extra .= '&assign_to[]=' . (int) $aid;
}
foreach ($jwo_status_vals as $stv) {
    $oj_tab_extra .= '&jwo_status[]=' . rawurlencode($stv);
}
foreach ($jwo_priority_vals as $prv) {
    $oj_tab_extra .= '&jwo_priority[]=' . rawurlencode($prv);
}
if ($adv_group_name !== '') {
    $oj_tab_extra .= '&adv_group_name=' . rawurlencode($adv_group_name);
}
if ($adv_jobwork_no !== '') {
    $oj_tab_extra .= '&adv_jobwork_no=' . rawurlencode($adv_jobwork_no);
}
if ($adv_tag_no !== '') {
    $oj_tab_extra .= '&adv_tag_no=' . rawurlencode($adv_tag_no);
}
if ($adv_gross_wt !== '') {
    $oj_tab_extra .= '&adv_gross_wt=' . rawurlencode($adv_gross_wt);
}
if ($adv_rfid !== '') {
    $oj_tab_extra .= '&adv_rfid=' . rawurlencode($adv_rfid);
}
if ($adv_against_ref !== '') {
    $oj_tab_extra .= '&adv_against_ref=' . rawurlencode($adv_against_ref);
}
if ($adv_short_desc !== '') {
    $oj_tab_extra .= '&adv_short_desc=' . rawurlencode($adv_short_desc);
}

// Refine → Jobwork Invoice: after save, return to Old Jewellery Received tab
$oj_jobwork_invoice_return_qs = '&from_oj_refine=1';
if ($from_date !== '' && $to_date !== '') {
    $oj_jobwork_invoice_return_qs .= '&oj_from_date=' . rawurlencode($from_date) . '&oj_to_date=' . rawurlencode($to_date);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// Get master data for filters
$branches = getListMaster("SELECT * FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
$customers = getList("SELECT DISTINCT customer_name FROM tbl_sale_invoices WHERE customer_name != '' ORDER BY customer_name ASC");
$sales_persons = getList("SELECT DISTINCT sales_person FROM tbl_sale_invoices WHERE sales_person IS NOT NULL AND sales_person != '' ORDER BY sales_person ASC");
$products = getList("SELECT * FROM tbl_products WHERE status = 1 ORDER BY name ASC LIMIT 100");
$metals = getList("SELECT * FROM tbl_metal WHERE status = 1 ORDER BY display_name ASC");
$locations = getList("SELECT * FROM tbl_location WHERE status = 1 ORDER BY name ASC");

// Build where clause based on active tab and filters
$where_clause = "si.status != 'cancelled'";

// Filter by scrap/old jewellery payments
if ($active_tab == 'scrap' || $active_tab == 'summary' || $active_tab == 'refine' || $active_tab == 'received' || $active_tab == 'stocked') {
    $where_clause .= " AND EXISTS (
        SELECT 1 FROM tbl_sale_invoice_payments sip 
        WHERE sip.invoice_id = si.id 
        AND sip.payment_type = 'scrap' 
        AND sip.status = 1
    )";
}

if (!empty($from_date) && !empty($to_date)) {
    $where_clause .= " AND DATE(si.invoice_date) BETWEEN '$from_date' AND '$to_date'";
}

if ($branch_id_in_sql !== '') {
    // Branch filtering via product characteristics
    $where_clause .= " AND EXISTS (
        SELECT 1 FROM tbl_product_characteristics pc 
        WHERE pc.product_id = sii.product_id 
        AND pc.branch_id IN ($branch_id_in_sql) 
        AND pc.status = 1
    )";
}

if (!empty($customer_name_parts)) {
    $cn_quoted = [];
    foreach ($customer_name_parts as $p) {
        $cn_quoted[] = "'" . mysqli_real_escape_string($conn, $p) . "'";
    }
    $where_clause .= ' AND si.customer_name IN (' . implode(',', $cn_quoted) . ')';
}

if (!empty($invoice_no)) {
    $where_clause .= " AND si.invoice_no LIKE '%$invoice_no%'";
}

if ($metal_id > 0) {
    $where_clause .= " AND pc.metal_id = $metal_id";
}

if ($product_id > 0) {
    $where_clause .= " AND sii.product_id = $product_id";
}

if ($location_id > 0) {
    $where_clause .= " AND sii.location_id = $location_id";
}

if (!empty($sales_person)) {
    $where_clause .= " AND si.sales_person LIKE '%$sales_person%'";
}

// Scrap invoice (OJB) where clause - same filters where applicable
$where_scrap = "oinv.status != 'cancelled'";
if (!empty($from_date) && !empty($to_date)) {
    $where_scrap .= " AND DATE(oinv.invoice_date) BETWEEN '$from_date' AND '$to_date'";
}
if (!empty($customer_name_parts)) {
    $cn_quoted_s = [];
    foreach ($customer_name_parts as $p) {
        $cn_quoted_s[] = "'" . mysqli_real_escape_string($conn, $p) . "'";
    }
    $where_scrap .= ' AND oinv.customer_name IN (' . implode(',', $cn_quoted_s) . ')';
}
if (!empty($invoice_no)) {
    $where_scrap .= " AND (oinv.invoice_no LIKE '%$invoice_no%' OR IFNULL(oinv.against_of,'') LIKE '%$invoice_no%' OR IFNULL(oinv.ref_no,'') LIKE '%$invoice_no%' OR EXISTS (SELECT 1 FROM tbl_purchase_invoices pix WHERE oinv.ref_no = CONCAT('PI:', pix.id) AND pix.invoice_no LIKE '%$invoice_no%') OR EXISTS (SELECT 1 FROM tbl_sale_invoices six WHERE oinv.ref_no = CONCAT('SI:', six.id) AND six.invoice_no LIKE '%$invoice_no%'))";
}
if (!empty($sales_person)) {
    $where_scrap .= " AND oinv.sales_person LIKE '%$sales_person%'";
}

$scrap_tables_exist = false;
$t_scrap = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
if ($t_scrap && mysqli_num_rows($t_scrap) > 0) {
    $t_items = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_items'");
    $scrap_tables_exist = ($t_items && mysqli_num_rows($t_items) > 0);
}
$has_stocked_col = false;
$scrap_has_metal_id = false;
$scrap_has_less_wt = false;
if ($scrap_tables_exist) {
    $col_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items LIKE 'is_stocked'");
    $has_stocked_col = ($col_check && mysqli_num_rows($col_check) > 0);
    $mc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items LIKE 'metal_id'");
    $scrap_has_metal_id = ($mc && mysqli_num_rows($mc) > 0);
    $lc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items LIKE 'less_wt'");
    $scrap_has_less_wt = ($lc && mysqli_num_rows($lc) > 0);
    $_pd_pay = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_payments LIKE 'payment_details'");
    $oj_pay_has_details_col = ($_pd_pay && mysqli_num_rows($_pd_pay) > 0);
    if ($_pd_pay) {
        mysqli_free_result($_pd_pay);
    }
} else {
    $oj_pay_has_details_col = false;
}
$stock_table_exists = false;
$t_stock = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
$stock_table_exists = ($t_stock && mysqli_num_rows($t_stock) > 0);
$oj_stock_balance_joins = '';
if ($stock_table_exists) {
    $oj_stock_balance_joins = "
    LEFT JOIN (SELECT invoice_id, COUNT(*) AS cnt FROM tbl_old_jewelry_scrap_invoice_items WHERE status = 1 GROUP BY invoice_id) oj_item_cnt ON oj_item_cnt.invoice_id = oinv.id
    LEFT JOIN (
        SELECT s.source_invoice_id AS inv_id, COALESCE(SUM(s.gross_wt),0) AS orphan_gross
        FROM tbl_old_jewelry_stock s
        LEFT JOIN tbl_old_jewelry_scrap_invoice_items i ON i.id = s.source_item_id AND i.invoice_id = s.source_invoice_id AND IFNULL(i.status,1) = 1
        WHERE i.id IS NULL
        GROUP BY s.source_invoice_id
    ) oj_orphan ON oj_orphan.inv_id = oinv.id";
}
// Scrap payment weights (payment_details JSON) — need item count per invoice when stock table is absent
if ($scrap_tables_exist && !empty($oj_pay_has_details_col) && !$stock_table_exists) {
    $oj_stock_balance_joins .= "
    LEFT JOIN (SELECT invoice_id, COUNT(*) AS cnt FROM tbl_old_jewelry_scrap_invoice_items WHERE status = 1 GROUP BY invoice_id) oj_item_cnt ON oj_item_cnt.invoice_id = oinv.id";
}

// Exclude lines with no remaining gross (partial stock-in supported via tbl_old_jewelry_stock)
if ($active_tab != 'stocked' && $has_stocked_col) {
    if ($stock_table_exists) {
        $where_scrap .= " AND (GREATEST(0, COALESCE(oji.gross_wt,0) - (
            COALESCE((SELECT COALESCE(SUM(sj.gross_wt),0) FROM tbl_old_jewelry_stock sj WHERE sj.source_item_id = oji.id), 0)
            + CASE WHEN (SELECT COUNT(*) FROM tbl_old_jewelry_scrap_invoice_items oc WHERE oc.invoice_id = oinv.id AND oc.status = 1) = 1
 THEN COALESCE((SELECT COALESCE(SUM(s.gross_wt),0) FROM tbl_old_jewelry_stock s LEFT JOIN tbl_old_jewelry_scrap_invoice_items ix ON ix.id = s.source_item_id AND ix.invoice_id = s.source_invoice_id AND IFNULL(ix.status,1) = 1 WHERE s.source_invoice_id = oinv.id AND ix.id IS NULL), 0)
 ELSE 0 END
        )) > 0.0001)";
    } else {
        $where_scrap .= " AND (oji.is_stocked = 0 OR oji.is_stocked IS NULL)";
    }
}

// Scrap tab: list only dedicated old-jewelry scrap invoices (OJB-*), not sale invoices with scrap payment
if ($active_tab == 'scrap') {
    $where_scrap .= " AND oinv.invoice_no LIKE 'OJB%'";
}

// Ensure OJB documents exist for purchase invoices with scrap payment (Invoice No. = OJB-*, not PI)
if ($scrap_tables_exist && ($active_tab == 'scrap' || $active_tab == 'summary' || $active_tab == 'refine' || $active_tab == 'received' || $active_tab == 'stocked')) {
    $sync_path = __DIR__ . '/includes/sync_purchase_scrap_to_ojb.php';
    if (is_file($sync_path)) {
        require_once $sync_path;
        if (function_exists('syncPurchaseScrapToOjbForDateRange')) {
            syncPurchaseScrapToOjbForDateRange($conn, $from_date, $to_date);
        }
    }
    $sync_si_path = __DIR__ . '/includes/sync_sale_scrap_to_ojb.php';
    if (is_file($sync_si_path)) {
        require_once $sync_si_path;
        if (function_exists('syncSaleScrapToOjbForDateRange')) {
            syncSaleScrapToOjbForDateRange($conn, $from_date, $to_date);
        }
    }
}

// Sale-invoice rows: "Against Invoice No" = linked Old Jewellery Scrap (OJB-*) when sync created ref_no SI:{sale_id}; else si.against_of
$si_against_invoice_expr = "COALESCE(NULLIF(TRIM(si.against_of), ''), '')";
if ($scrap_tables_exist) {
    $si_against_invoice_expr = "COALESCE((SELECT TRIM(ox.invoice_no) FROM tbl_old_jewelry_scrap_invoices ox WHERE ox.ref_no = CONCAT('SI:', si.id) AND IFNULL(ox.status,'') != 'cancelled' ORDER BY ox.id DESC LIMIT 1), NULLIF(TRIM(si.against_of), ''), '')";
}

if ($adv_short_desc !== '') {
    $ad = mysqli_real_escape_string($conn, $adv_short_desc);
    $where_clause .= " AND (sii.product_name LIKE '%{$ad}%' OR sii.description LIKE '%{$ad}%')";
}
if ($adv_against_ref !== '') {
    $ad = mysqli_real_escape_string($conn, $adv_against_ref);
    $where_clause .= " AND (({$si_against_invoice_expr}) LIKE '%{$ad}%' OR si.against_of LIKE '%{$ad}%')";
}
if ($adv_gross_wt !== '' && is_numeric($adv_gross_wt)) {
    $gw = (float) $adv_gross_wt;
    $where_clause .= " AND ABS(COALESCE(sii.gross_weight,0) - $gw) < 0.000001";
}

if ($adv_short_desc !== '') {
    $ad = mysqli_real_escape_string($conn, $adv_short_desc);
    $where_scrap .= " AND oji.description LIKE '%{$ad}%'";
}
if ($adv_against_ref !== '') {
    $ad = mysqli_real_escape_string($conn, $adv_against_ref);
    $where_scrap .= " AND (oinv.invoice_no LIKE '%{$ad}%' OR IFNULL(oinv.against_of,'') LIKE '%{$ad}%' OR IFNULL(oinv.ref_no,'') LIKE '%{$ad}%')";
}
if ($adv_tag_no !== '') {
    $ad = mysqli_real_escape_string($conn, $adv_tag_no);
    $where_scrap .= " AND TRIM(IFNULL(oji.barcode,'')) LIKE '%{$ad}%'";
}
if ($adv_gross_wt !== '' && is_numeric($adv_gross_wt)) {
    $gw = (float) $adv_gross_wt;
    $where_scrap .= " AND ABS(COALESCE(oji.gross_wt,0) - $gw) < 0.000001";
}

// Build query based on active tab (sale invoice scrap items)
 $query_sale = "
    SELECT DISTINCT
        si.id,
        CONVERT(si.invoice_no USING utf8mb4) COLLATE $oj_uc AS invoice_no,
        si.invoice_date as date,
        CONVERT(si.customer_name USING utf8mb4) COLLATE $oj_uc AS customer_name,
        CONVERT(si.sales_person USING utf8mb4) COLLATE $oj_uc AS sales_person,
        COALESCE(pc.branch_id, 0) as branch_id,
        CONVERT(COALESCE(b.name, '') USING utf8mb4) COLLATE $oj_uc as branch_name,
        sii.id as item_id,
        sii.product_id,
        CONVERT(p.name USING utf8mb4) COLLATE $oj_uc as product_name,
        COALESCE(pc.metal_id, 0) as metal_id,
        CONVERT(COALESCE(m.display_name, '') USING utf8mb4) COLLATE $oj_uc as metal,
        sii.location_id,
        CONVERT(l.name USING utf8mb4) COLLATE $oj_uc as location,
        sii.gross_weight as gross_wt,
        sii.less_weight as less_wt,
        0.000 as stone_wt,
        sii.net_weight as net_wt,
        sii.purity,
        sii.final_weight as final_wt,
        sii.rate,
        sii.amount,
        sii.quantity,
        sii.pure_weight as pure_wt,
        si.grand_total as amount_paid,
        CONVERT(sii.product_name USING utf8mb4) COLLATE $oj_uc as description,
        CONVERT('Exchange' USING utf8mb4) COLLATE $oj_uc as source,
        CONVERT($si_against_invoice_expr USING utf8mb4) COLLATE $oj_uc as against_invoice_no,
        0.00 as current_gold_rate,
        CONVERT(si.status USING utf8mb4) COLLATE $oj_uc as active,
        CONVERT('' USING utf8mb4) COLLATE $oj_uc as barcode,
        0 as pi_id_for_scrap
    FROM tbl_sale_invoices si
    INNER JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id AND sii.status = 1
    LEFT JOIN tbl_products p ON sii.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON sii.product_characteristic_id = pc.id
    LEFT JOIN tbl_branches b ON pc.branch_id = b.id
    LEFT JOIN tbl_metal m ON pc.metal_id = m.id
    LEFT JOIN tbl_location l ON sii.location_id = l.id
    WHERE $where_clause
";

// Purchase scrap lines are represented via auto-created OJB invoices (ref_no PI:{id}) — see sync_purchase_scrap_to_ojb.php
$query_purchase = "";

$query_scrap = "";
if ($scrap_tables_exist && ($active_tab == 'scrap' || $active_tab == 'summary' || $active_tab == 'refine' || $active_tab == 'received' || $active_tab == 'stocked')) {
    $metal_sel = $scrap_has_metal_id ? "CONVERT(COALESCE(m.display_name, '') USING utf8mb4) COLLATE $oj_uc as metal" : "CONVERT('' USING utf8mb4) COLLATE $oj_uc as metal";
    $metal_join = $scrap_has_metal_id ? " LEFT JOIN tbl_metal m ON oji.metal_id = m.id" : "";
    if ($stock_table_exists) {
        $oj_line_stock = "(SELECT COALESCE(SUM(sj.gross_wt),0) FROM tbl_old_jewelry_stock sj WHERE sj.source_item_id = oji.id)";
        $oj_orphan_part = "(CASE WHEN COALESCE(oj_item_cnt.cnt, 0) = 1 THEN COALESCE(oj_orphan.orphan_gross, 0) ELSE 0 END)";
        $oj_eff_stock = "($oj_line_stock + $oj_orphan_part)";
        $oj_rem_gross = "GREATEST(0, COALESCE(oji.gross_wt,0) - COALESCE($oj_eff_stock,0))";
    } else {
        $oj_rem_gross = "COALESCE(oji.gross_wt,0)";
    }
    $oj_bal_ratio = $stock_table_exists
        ? "(CASE WHEN COALESCE(oji.gross_wt,0) > 0.00001 THEN ($oj_rem_gross / oji.gross_wt) ELSE 0 END)"
        : '1';
    $less_wt_sel = $scrap_has_less_wt
        ? ($stock_table_exists ? "COALESCE(oji.less_wt, 0) * $oj_bal_ratio as less_wt" : "COALESCE(oji.less_wt, 0) as less_wt")
        : "0.000 as less_wt";
    $net_wt_sel = $stock_table_exists ? "COALESCE(oji.net_wt, 0) * $oj_bal_ratio as net_wt" : "oji.net_wt as net_wt";
    $final_wt_sel = $stock_table_exists ? "COALESCE(oji.final_wt, 0) * $oj_bal_ratio as final_wt" : "oji.final_wt as final_wt";
    $pure_wt_sel = $stock_table_exists ? "COALESCE(oji.pure_wt, 0) * $oj_bal_ratio as pure_wt" : "oji.pure_wt as pure_wt";
    $amount_line_sel = $stock_table_exists ? "COALESCE(oji.amount, 0) * $oj_bal_ratio as amount" : "oji.amount as amount";
    $stone_wt_sel = $stock_table_exists
        ? "COALESCE(oji.gemstone_wt, oji.diamond_wt, 0) * $oj_bal_ratio as stone_wt"
        : "COALESCE(oji.gemstone_wt, oji.diamond_wt, 0) as stone_wt";
    $oj_gross_remaining = $stock_table_exists
        ? $oj_rem_gross
        : "COALESCE(oji.gross_wt,0)";

    $gross_wt_sel = "$oj_gross_remaining AS gross_wt";
    $purity_sel = "COALESCE(oji.purity, 0) AS purity";
    $oj_pay_wt_join = '';
    if (!empty($oj_pay_has_details_col)) {
        $oj_pay_wt_join = "
    LEFT JOIN (
        SELECT invoice_id,
            SUM(GREATEST(0, COALESCE(
                CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.scrap_gross_wt'))), '') AS DECIMAL(18,4)),
                CAST(JSON_EXTRACT(payment_details, '$.scrap_gross_wt') AS DECIMAL(18,4)),
                0
            ))) AS pay_gross,
            SUM(GREATEST(0, COALESCE(
                CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.scrap_net_wt'))), '') AS DECIMAL(18,4)),
                CAST(JSON_EXTRACT(payment_details, '$.scrap_net_wt') AS DECIMAL(18,4)),
                0
            ))) AS pay_net,
            SUM(GREATEST(0, COALESCE(
                CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.scrap_less_wt'))), '') AS DECIMAL(18,4)),
                CAST(JSON_EXTRACT(payment_details, '$.scrap_less_wt') AS DECIMAL(18,4)),
                0
            ))) AS pay_less,
            SUM(GREATEST(0, COALESCE(
                CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.scrap_stone_wt'))), '') AS DECIMAL(18,4)),
                CAST(JSON_EXTRACT(payment_details, '$.scrap_stone_wt') AS DECIMAL(18,4)),
                0
            ))) AS pay_stone,
            SUM(GREATEST(0, COALESCE(
                CAST(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.scrap_purity_wt'))), '') AS DECIMAL(18,4)),
                CAST(JSON_EXTRACT(payment_details, '$.scrap_purity_wt') AS DECIMAL(18,4)),
                0
            ))) AS pay_pure
        FROM tbl_old_jewelry_scrap_invoice_payments
        WHERE IFNULL(status,1) = 1
          AND (LOWER(IFNULL(payment_type,'')) LIKE '%scrap%' OR IFNULL(deposit_into,'') = 'Scrap')
        GROUP BY invoice_id
    ) oj_pay_wt ON oj_pay_wt.invoice_id = oinv.id
";
        $net_wt_core = $stock_table_exists ? "COALESCE(oji.net_wt, 0) * $oj_bal_ratio" : "COALESCE(oji.net_wt, 0)";
        $less_wt_core = $scrap_has_less_wt
            ? ($stock_table_exists ? "COALESCE(oji.less_wt, 0) * $oj_bal_ratio" : "COALESCE(oji.less_wt, 0)")
            : "0.000";
        $stone_wt_core = $stock_table_exists
            ? "COALESCE(oji.gemstone_wt, oji.diamond_wt, 0) * $oj_bal_ratio"
            : "COALESCE(oji.gemstone_wt, oji.diamond_wt, 0)";
        $final_wt_core = $stock_table_exists ? "COALESCE(oji.final_wt, 0) * $oj_bal_ratio" : "COALESCE(oji.final_wt, 0)";
        $pure_wt_core = $stock_table_exists ? "COALESCE(oji.pure_wt, 0) * $oj_bal_ratio" : "COALESCE(oji.pure_wt, 0)";
        $single_ln_pay = "COALESCE(oj_pay_wt.pay_net, 0) > 0.00001 AND COALESCE(oj_item_cnt.cnt, 0) = 1";
        $gross_wt_sel = "CASE WHEN ($single_ln_pay) THEN CASE WHEN COALESCE(oj_pay_wt.pay_gross, 0) > 0.00001 THEN oj_pay_wt.pay_gross ELSE oj_pay_wt.pay_net END ELSE ($oj_gross_remaining) END AS gross_wt";
        $less_wt_sel = "CASE WHEN ($single_ln_pay) THEN oj_pay_wt.pay_less ELSE ($less_wt_core) END AS less_wt";
        $stone_wt_sel = "CASE WHEN ($single_ln_pay) THEN oj_pay_wt.pay_stone ELSE ($stone_wt_core) END AS stone_wt";
        $net_wt_sel = "CASE WHEN ($single_ln_pay) THEN oj_pay_wt.pay_net ELSE ($net_wt_core) END AS net_wt";
        $final_wt_sel = "CASE WHEN ($single_ln_pay) THEN CASE WHEN COALESCE(oj_pay_wt.pay_gross, 0) > 0.00001 THEN oj_pay_wt.pay_gross ELSE oj_pay_wt.pay_net END ELSE ($final_wt_core) END AS final_wt";
        $pure_wt_sel = "CASE WHEN ($single_ln_pay) THEN CASE WHEN COALESCE(oj_pay_wt.pay_pure, 0) > 0.00001 THEN oj_pay_wt.pay_pure ELSE ($pure_wt_core) END ELSE ($pure_wt_core) END AS pure_wt";
        $purity_sel = "CASE WHEN ($single_ln_pay) AND COALESCE(oj_pay_wt.pay_net, 0) > 0.00001 AND COALESCE(oj_pay_wt.pay_pure, 0) > 0.00001 THEN (oj_pay_wt.pay_pure / NULLIF(oj_pay_wt.pay_net, 0)) ELSE COALESCE(oji.purity, 0) END AS purity";
    }

    $query_scrap = "
    UNION ALL
    SELECT
        oinv.id,
        CONVERT(oinv.invoice_no USING utf8mb4) COLLATE $oj_uc AS invoice_no,
        oinv.invoice_date as date,
        CONVERT(oinv.customer_name USING utf8mb4) COLLATE $oj_uc AS customer_name,
        CONVERT(oinv.sales_person USING utf8mb4) COLLATE $oj_uc AS sales_person,
        0 as branch_id,
        CONVERT('' USING utf8mb4) COLLATE $oj_uc as branch_name,
        oji.id as item_id,
        0 as product_id,
        CONVERT(COALESCE(oji.description, '') USING utf8mb4) COLLATE $oj_uc as product_name,
        " . ($scrap_has_metal_id ? "COALESCE(oji.metal_id, 0) as metal_id" : "0 as metal_id") . ",
        $metal_sel,
        0 as location_id,
        CONVERT('' USING utf8mb4) COLLATE $oj_uc as location,
        $gross_wt_sel,
        $less_wt_sel,
        $stone_wt_sel,
        $net_wt_sel,
        $purity_sel,
        $final_wt_sel,
        oji.rate,
        $amount_line_sel,
        oji.quantity,
        $pure_wt_sel,
        oinv.grand_total as amount_paid,
        CONVERT(COALESCE(oji.description, '') USING utf8mb4) COLLATE $oj_uc as description,
        CONVERT(CASE WHEN IFNULL(oinv.ref_no,'') LIKE 'PI:%' THEN 'Purchase Invoice' ELSE 'Scrap Invoice' END USING utf8mb4) COLLATE $oj_uc as source,
        CONVERT(COALESCE(NULLIF(TRIM(oinv.against_of), ''), pi_ojb.invoice_no, si_ojb.invoice_no, (SELECT TRIM(six.invoice_no) FROM tbl_sale_invoices six INNER JOIN tbl_sale_invoice_payments sip ON sip.invoice_id = six.id AND LOWER(TRIM(sip.payment_type)) = 'scrap' AND sip.status = 1 WHERE IFNULL(six.status,'') != 'cancelled' AND TRIM(IFNULL(six.against_of,'')) <> '' AND TRIM(six.against_of) = TRIM(oinv.invoice_no) ORDER BY six.id DESC LIMIT 1), TRIM(oinv.invoice_no)) USING utf8mb4) COLLATE $oj_uc as against_invoice_no,
        0.00 as current_gold_rate,
        CONVERT(oinv.status USING utf8mb4) COLLATE $oj_uc as active,
        CONVERT(COALESCE(oji.barcode, '') USING utf8mb4) COLLATE $oj_uc as barcode,
        COALESCE(pi_ojb.id, 0) as pi_id_for_scrap
    FROM tbl_old_jewelry_scrap_invoices oinv
    INNER JOIN tbl_old_jewelry_scrap_invoice_items oji ON oinv.id = oji.invoice_id AND oji.status = 1
    LEFT JOIN tbl_purchase_invoices pi_ojb ON TRIM(IFNULL(oinv.ref_no,'')) <> '' AND TRIM(oinv.ref_no) = CONCAT('PI:', pi_ojb.id)
    LEFT JOIN tbl_sale_invoices si_ojb ON TRIM(IFNULL(oinv.ref_no,'')) <> '' AND LOWER(TRIM(oinv.ref_no)) = LOWER(CONCAT('SI:', si_ojb.id))
    $metal_join
    $oj_stock_balance_joins
    $oj_pay_wt_join
    WHERE $where_scrap
    ";
}

$scrap_tab_ojb_only = ($active_tab == 'scrap' && $scrap_tables_exist && $query_scrap !== '');
if ($scrap_tab_ojb_only) {
    $query_scrap_body = preg_replace('/^\s*UNION ALL\s+/i', '', trim($query_scrap));
    $query = "($query_scrap_body) ORDER BY date DESC, id DESC";
} else {
    $query = "($query_sale) $query_purchase $query_scrap ORDER BY date DESC, id DESC";
}

if ($scrap_tab_ojb_only) {
    $count_query = "
        SELECT COUNT(*) as total
        FROM tbl_old_jewelry_scrap_invoices oinv
        INNER JOIN tbl_old_jewelry_scrap_invoice_items oji ON oinv.id = oji.invoice_id AND oji.status = 1
        WHERE $where_scrap
    ";
} else {
    $count_query = "
        SELECT SUM(c) as total FROM (
            SELECT COUNT(DISTINCT CONCAT(si.id, '-', sii.id)) as c
            FROM tbl_sale_invoices si
            INNER JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id AND sii.status = 1
            LEFT JOIN tbl_products p ON sii.product_id = p.id
            LEFT JOIN tbl_product_characteristics pc ON sii.product_characteristic_id = pc.id
            WHERE $where_clause
            " . ($scrap_tables_exist && ($active_tab == 'scrap' || $active_tab == 'summary' || $active_tab == 'refine' || $active_tab == 'received' || $active_tab == 'stocked') ? "
            UNION ALL
            SELECT COUNT(*) as c
            FROM tbl_old_jewelry_scrap_invoices oinv
            INNER JOIN tbl_old_jewelry_scrap_invoice_items oji ON oinv.id = oji.invoice_id AND oji.status = 1
            WHERE $where_scrap" : "") . "
        ) t
    ";
}

$total_records_result = getRecord($count_query);
$total_records = $total_records_result ? (int)($total_records_result['total'] ?? 0) : 0;

$old_jewellery_data = getList($query . " LIMIT $per_page OFFSET $offset");

if (!empty($old_jewellery_data) && is_array($old_jewellery_data)) {
    $sync_helpers_path = __DIR__ . '/includes/sync_purchase_scrap_to_ojb.php';
    if (is_file($sync_helpers_path)) {
        require_once $sync_helpers_path;
    }
    $oj_balance_helper = __DIR__ . '/includes/old_jewelry_scrap_stock_balance.php';
    if ($stock_table_exists && is_file($oj_balance_helper)) {
        require_once $oj_balance_helper;
    }
    if (function_exists('ojbBuildScrapModalItemValuesFromPaymentDetails')) {
        foreach ($old_jewellery_data as &$oj_merge_row) {
            $pi_merge = (int) ($oj_merge_row['pi_id_for_scrap'] ?? 0);
            if ($pi_merge <= 0) {
                continue;
            }
            if (!function_exists('pipTableHasPaymentDetailsColumn') || !pipTableHasPaymentDetailsColumn($conn)) {
                continue;
            }
            $pd_merge = getRecord("SELECT payment_details FROM tbl_purchase_invoice_payments WHERE invoice_id = $pi_merge AND LOWER(TRIM(payment_type)) = 'scrap' AND status = 1 ORDER BY id DESC LIMIT 1");
            $pd_arr = [];
            if ($pd_merge && !empty($pd_merge['payment_details'])) {
                $pd_arr = json_decode($pd_merge['payment_details'], true);
            }
            $mv = ojbBuildScrapModalItemValuesFromPaymentDetails(is_array($pd_arr) ? $pd_arr : []);
            if ($mv === null) {
                continue;
            }
            $oj_merge_row['gross_wt'] = $mv['gross_wt'];
            $oj_merge_row['less_wt'] = $mv['less_wt'];
            $oj_merge_row['final_wt'] = $mv['final_wt'];
            $oj_merge_row['net_wt'] = $mv['net_wt'];
            $oj_merge_row['pure_wt'] = $mv['pure_wt'];
            $oj_merge_row['quantity'] = $mv['quantity'];
            $oj_merge_row['purity'] = $mv['purity'];
            $oj_merge_row['rate'] = $mv['rate'];
            $oj_merge_row['amount'] = $mv['amount'];
            if ($mv['description'] !== '') {
                $oj_merge_row['description'] = $mv['description'];
                $oj_merge_row['product_name'] = $mv['description'];
            }
            if ($mv['barcode'] !== '') {
                $oj_merge_row['barcode'] = $mv['barcode'];
            }
            if ($scrap_has_metal_id && $mv['metal_id'] > 0) {
                $oj_merge_row['metal_id'] = $mv['metal_id'];
                $mn = getRecord("SELECT display_name FROM tbl_metal WHERE id = " . (int) $mv['metal_id'] . " LIMIT 1");
                if ($mn && trim((string) ($mn['display_name'] ?? '')) !== '') {
                    $oj_merge_row['metal'] = $mn['display_name'];
                }
            }
            if ($stock_table_exists && function_exists('auragold_oj_scrap_stocked_gross_sum_for_line_including_single_line_orphans')) {
                $iid_m = (int) ($oj_merge_row['item_id'] ?? 0);
                if ($iid_m > 0) {
                    $orig_g_m = (float) ($oj_merge_row['gross_wt'] ?? 0);
                    $inv_m = (int) ($oj_merge_row['id'] ?? 0);
                    $st_m = auragold_oj_scrap_stocked_gross_sum_for_line_including_single_line_orphans($conn, $inv_m, $iid_m);
                    $rem_m = max(0, $orig_g_m - $st_m);
                    $ratio_m = $orig_g_m > 0.00001 ? ($rem_m / $orig_g_m) : 0;
                    $oj_merge_row['gross_wt'] = $rem_m;
                    $oj_merge_row['net_wt'] = (float) ($oj_merge_row['net_wt'] ?? 0) * $ratio_m;
                    $oj_merge_row['final_wt'] = (float) ($oj_merge_row['final_wt'] ?? 0) * $ratio_m;
                    $oj_merge_row['less_wt'] = (float) ($oj_merge_row['less_wt'] ?? 0) * $ratio_m;
                    $oj_merge_row['pure_wt'] = (float) ($oj_merge_row['pure_wt'] ?? 0) * $ratio_m;
                    $oj_merge_row['amount'] = (float) ($oj_merge_row['amount'] ?? 0) * $ratio_m;
                }
            }
        }
        unset($oj_merge_row);
    }
}

// Stocked tab: data from separate stock table (does not change invoice saved data)
$stocked_data = [];
$stocked_total_records = 0;
$stocked_totals = ['total_final_wt' => 0, 'total_gross_wt' => 0, 'total_less_wt' => 0, 'total_net_wt' => 0, 'total_amount' => 0];
$stocked_total_pages = 0;
if ($active_tab == 'stocked' && $stock_table_exists) {
    $where_stock = "1=1";
    if (!empty($from_date) && !empty($to_date)) {
        $where_stock .= " AND DATE(s.created_at) BETWEEN '$from_date' AND '$to_date'";
    }
    if ($branch_id_in_sql !== '') {
        $where_stock .= " AND s.branch_id IN ($branch_id_in_sql)";
    }
    if (!empty($invoice_no)) {
        $where_stock .= " AND s.invoice_no LIKE '%$invoice_no%'";
    }
    $stocked_count = getRecord("SELECT COUNT(*) as c FROM tbl_old_jewelry_stock s WHERE $where_stock");
    $stocked_total_records = $stocked_count ? (int)$stocked_count['c'] : 0;
    $stocked_total_pages = $stocked_total_records > 0 ? ceil($stocked_total_records / $per_page) : 0;
    $stocked_data = getList("
        SELECT s.*, COALESCE(b.name, '') as branch_name
        FROM tbl_old_jewelry_stock s
        LEFT JOIN tbl_branches b ON s.branch_id = b.id
        WHERE $where_stock
        ORDER BY s.created_at DESC, s.id DESC
        LIMIT $per_page OFFSET $offset
    ");
    $st = getRecord("SELECT SUM(final_wt) as fw, SUM(gross_wt) as gw, SUM(less_wt) as lw, SUM(net_wt) as nw, SUM(amount) as am FROM tbl_old_jewelry_stock s WHERE $where_stock");
    if ($st) {
        $stocked_totals = [
            'total_final_wt' => (float)($st['fw'] ?? 0),
            'total_gross_wt' => (float)($st['gw'] ?? 0),
            'total_less_wt' => (float)($st['lw'] ?? 0),
            'total_net_wt' => (float)($st['nw'] ?? 0),
            'total_amount' => (float)($st['am'] ?? 0)
        ];
    }
}

// Refine tab: job work order lines linked to sale orders created from Old Jewellery scrap → refinery
$refine_data = [];
$refine_total_records = 0;
$refine_total_pages = 1;
$refine_totals = ['sum_gross_wt' => 0, 'sum_final_wt' => 0];
$received_data = [];
$received_total_records = 0;
$received_total_pages = 1;
$received_totals = ['sum_gross_wt' => 0, 'sum_final_wt' => 0];
$refine_comment_needle = 'Job work / refinery from Old Jewellery scrap';
$has_refine_jwo = false;
$tjwo_ref = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if ($tjwo_ref && mysqli_num_rows($tjwo_ref) > 0) {
    $has_refine_jwo = true;
}
if ($tjwo_ref) {
    mysqli_free_result($tjwo_ref);
}
$assign_to_options = [];
if ($has_refine_jwo) {
    $needle_as = mysqli_real_escape_string($conn, $refine_comment_needle);
    $assign_to_options = getList(
        "SELECT DISTINCT cu.id, cu.name FROM tbl_jobwork_orders j "
        . "INNER JOIN tbl_sale_orders so ON so.id = j.sale_order_id "
        . "LEFT JOIN tbl_customers cu ON cu.id = j.department_user_id "
        . "WHERE so.comment LIKE '%{$needle_as}%' AND cu.id IS NOT NULL "
        . "ORDER BY cu.name ASC"
    );
}
if (!is_array($assign_to_options)) {
    $assign_to_options = [];
}
$jwo_status_options = ['Completed', 'Hold', 'Invoice Created', 'Not Initiate', 'Processing', 'Rejected', 'Transfered'];
$jwo_priority_options = ['Low', 'Medium', 'High'];

if (($active_tab === 'refine' || $active_tab === 'received') && $has_refine_jwo) {
    $needle_esc = mysqli_real_escape_string($conn, $refine_comment_needle);
    $t_inv_ref = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_invoices'");
    $has_jwi_tbl = ($t_inv_ref && mysqli_num_rows($t_inv_ref) > 0);
    if ($t_inv_ref) {
        mysqli_free_result($t_inv_ref);
    }
    $oj_line_base_where = " WHERE so.comment LIKE '%{$needle_esc}%' "
        . " AND LOWER(TRIM(COALESCE(j.status,''))) NOT IN ('cancelled') "
        . " AND LOWER(TRIM(COALESCE(so.status,''))) NOT IN ('cancelled') ";
    if (!empty($from_date) && !empty($to_date)) {
        $oj_line_base_where .= " AND DATE(j.order_date) BETWEEN '" . esc($from_date) . "' AND '" . esc($to_date) . "' ";
    }
    $oj_line_tab_suffix = '';
    if ($active_tab === 'refine') {
        if ($has_jwi_tbl) {
            $oj_line_tab_suffix = ' AND NOT EXISTS (SELECT 1 FROM tbl_jobwork_invoices jwi WHERE jwi.jobwork_order_id = j.id) ';
        }
    } else {
        if (!$has_jwi_tbl) {
            $oj_line_tab_suffix = ' AND 1=0 ';
        } else {
            // Received = invoiced JWO lines. Stock-in state is shown in the Stock In column (button vs Completed).
            $oj_line_tab_suffix = ' AND EXISTS (SELECT 1 FROM tbl_jobwork_invoices jwi WHERE jwi.jobwork_order_id = j.id) ';
        }
    }
    $refine_where = $oj_line_base_where . $oj_line_tab_suffix;

    $tji_ref = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
    $refine_has_ji = ($tji_ref && mysqli_num_rows($tji_ref) > 0);
    if ($tji_ref) {
        mysqli_free_result($tji_ref);
    }

    if ($refine_has_ji) {
        $ji_cols_r = [];
        $cq = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
        if ($cq) {
            while ($r = mysqli_fetch_assoc($cq)) {
                $ji_cols_r[$r['Field']] = true;
            }
            mysqli_free_result($cq);
        }
        $so_has_branch_r = false;
        $so_has_ref_r = false;
        $so_has_against_r = false;
        foreach (['branch_id' => &$so_has_branch_r, 'ref_no' => &$so_has_ref_r, 'against_of' => &$so_has_against_r] as $f => &$v) {
            $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE '$f'");
            $v = ($c && mysqli_num_rows($c) > 0);
            if ($c) {
                mysqli_free_result($c);
            }
        }
        unset($v);

        $so_has_group_r = false;
        $_gc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'group_name'");
        if ($_gc && mysqli_num_rows($_gc) > 0) {
            $so_has_group_r = true;
        }
        if ($_gc) {
            mysqli_free_result($_gc);
        }

        $tag_expr = "COALESCE(NULLIF(TRIM(ji.barcode),''), '')";
        if (!empty($ji_cols_r['barcode_no'])) {
            $tag_expr = "COALESCE(NULLIF(TRIM(ji.barcode_no),''), NULLIF(TRIM(ji.barcode),''), '')";
        }
        $rfid_sel = "COALESCE(NULLIF(TRIM(pc.sku_code),''), '')";
        if (!empty($ji_cols_r['rfid_code'])) {
            $rfid_sel = "COALESCE(NULLIF(TRIM(ji.rfid_code),''), NULLIF(TRIM(pc.sku_code),''), '')";
        }

        $against_expr = "'' AS against_ref";
        if ($so_has_ref_r || $so_has_against_r) {
            $parts = [];
            if ($so_has_ref_r) {
                $parts[] = "NULLIF(TRIM(so.ref_no),'')";
            }
            if ($so_has_against_r) {
                $parts[] = "NULLIF(TRIM(so.against_of),'')";
            }
            if (!empty($parts)) {
                $against_expr = 'COALESCE(' . implode(', ', $parts) . ", '') AS against_ref";
            }
        }

        $branch_join_r = '';
        $branch_sel_r = "'' AS branch_name";
        if ($so_has_branch_r) {
            $branch_join_r = ' LEFT JOIN tbl_branches br ON br.id = so.branch_id ';
            $branch_sel_r = "IFNULL(br.name, '') AS branch_name";
        }

        $prio_sel = "'Medium'";
        $jwo_prio = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'priority'");
        if ($jwo_prio && mysqli_num_rows($jwo_prio) > 0) {
            $prio_sel = 'j.priority';
        }
        if ($jwo_prio) {
            mysqli_free_result($jwo_prio);
        }

        $assign_join_r = '';
        $assign_sel_r = "'' AS assign_name";
        $jwo_du = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_user_id'");
        if ($jwo_du && mysqli_num_rows($jwo_du) > 0) {
            $assign_join_r = ' LEFT JOIN tbl_customers cu ON cu.id = j.department_user_id ';
            $assign_sel_r = "COALESCE(cu.name, '') AS assign_name";
        }
        if ($jwo_du) {
            mysqli_free_result($jwo_du);
        }

        $jwi_inv_sel = $has_jwi_tbl
            ? '(SELECT jwx.invoice_no FROM tbl_jobwork_invoices jwx WHERE jwx.jobwork_order_id = j.id ORDER BY jwx.id DESC LIMIT 1) AS jwi_invoice_no'
            : "'' AS jwi_invoice_no";

        $oj_refine_stocked_sel = ', 0 AS oj_refine_stocked';
        if ($stock_table_exists) {
            $vt_refine_esc = mysqli_real_escape_string($conn, 'Refinery - Jobwork');
            $oj_refine_stocked_sel = ", EXISTS (SELECT 1 FROM tbl_old_jewelry_stock s WHERE s.source_item_id = ji.id AND (TRIM(IFNULL(s.voucher_type,'')) = '{$vt_refine_esc}' OR (LOCATE('auragold_oj_refine', IFNULL(s.comment,'')) > 0 AND LOCATE(CONCAT('joi_id=', ji.id, '|'), IFNULL(s.comment,'')) > 0)) LIMIT 1) AS oj_refine_stocked";
        }

        if ($due_from_date !== '' && $due_to_date !== '') {
            $refine_where .= " AND DATE(j.due_date) BETWEEN '" . esc($due_from_date) . "' AND '" . esc($due_to_date) . "' ";
        }
        if ($so_has_branch_r && $branch_id_in_sql !== '') {
            $refine_where .= " AND so.branch_id IN ($branch_id_in_sql) ";
        }
        if (!empty($customer_name_parts)) {
            $cnq = [];
            foreach ($customer_name_parts as $p) {
                $cnq[] = "'" . mysqli_real_escape_string($conn, $p) . "'";
            }
            $refine_where .= ' AND j.customer_name IN (' . implode(',', $cnq) . ') ';
        }
        if (!empty($assign_to_ids) && $assign_join_r !== '') {
            $refine_where .= ' AND j.department_user_id IN (' . implode(',', array_map('intval', $assign_to_ids)) . ') ';
        }
        if (!empty($jwo_status_vals)) {
            $sts = [];
            foreach ($jwo_status_vals as $st) {
                $sts[] = "'" . mysqli_real_escape_string($conn, $st) . "'";
            }
            $refine_where .= ' AND j.status IN (' . implode(',', $sts) . ') ';
        }
        if (!empty($jwo_priority_vals) && $prio_sel === 'j.priority') {
            $prs = [];
            foreach ($jwo_priority_vals as $pr) {
                $prs[] = "'" . mysqli_real_escape_string($conn, $pr) . "'";
            }
            $refine_where .= ' AND j.priority IN (' . implode(',', $prs) . ') ';
        }
        if ($adv_group_name !== '' && $so_has_group_r) {
            $g = mysqli_real_escape_string($conn, $adv_group_name);
            $refine_where .= " AND IFNULL(so.group_name,'') LIKE '%{$g}%' ";
        }
        if ($adv_jobwork_no !== '') {
            $jn = mysqli_real_escape_string($conn, $adv_jobwork_no);
            $refine_where .= " AND j.jobwork_no LIKE '%{$jn}%' ";
        }
        if ($adv_tag_no !== '') {
            $tn = mysqli_real_escape_string($conn, $adv_tag_no);
            if (!empty($ji_cols_r['barcode_no'])) {
                $refine_where .= " AND (TRIM(IFNULL(ji.barcode,'')) LIKE '%{$tn}%' OR TRIM(IFNULL(ji.barcode_no,'')) LIKE '%{$tn}%') ";
            } else {
                $refine_where .= " AND TRIM(IFNULL(ji.barcode,'')) LIKE '%{$tn}%' ";
            }
        }
        if ($adv_gross_wt !== '') {
            if (is_numeric($adv_gross_wt)) {
                $gw = (float) $adv_gross_wt;
                $refine_where .= " AND ABS(COALESCE(ji.gross_weight,0) - $gw) < 0.000001 ";
            }
        }
        if ($adv_rfid !== '') {
            $rf = mysqli_real_escape_string($conn, $adv_rfid);
            if (!empty($ji_cols_r['rfid_code'])) {
                $refine_where .= " AND (TRIM(IFNULL(ji.rfid_code,'')) LIKE '%{$rf}%' OR TRIM(IFNULL(pc.sku_code,'')) LIKE '%{$rf}%') ";
            } else {
                $refine_where .= " AND TRIM(IFNULL(pc.sku_code,'')) LIKE '%{$rf}%' ";
            }
        }
        if ($adv_against_ref !== '') {
            $ar = mysqli_real_escape_string($conn, $adv_against_ref);
            $ar_parts = [];
            if ($so_has_ref_r) {
                $ar_parts[] = "TRIM(IFNULL(so.ref_no,'')) LIKE '%{$ar}%'";
            }
            if ($so_has_against_r) {
                $ar_parts[] = "TRIM(IFNULL(so.against_of,'')) LIKE '%{$ar}%'";
            }
            if (!empty($ar_parts)) {
                $refine_where .= ' AND (' . implode(' OR ', $ar_parts) . ') ';
            }
        }
        if ($adv_short_desc !== '') {
            $sd = mysqli_real_escape_string($conn, $adv_short_desc);
            $refine_where .= " AND ji.product_name LIKE '%{$sd}%' ";
        }

        $refine_count_sql = 'SELECT COUNT(*) AS total FROM tbl_jobwork_order_items ji '
            . 'INNER JOIN tbl_jobwork_orders j ON ji.jobwork_order_id = j.id '
            . 'INNER JOIN tbl_sale_orders so ON so.id = j.sale_order_id '
            . $refine_where;
        $refine_count = getRecord($refine_count_sql);
        $line_total = $refine_count ? (int) ($refine_count['total'] ?? 0) : 0;
        if ($active_tab === 'refine') {
            $refine_total_records = $line_total;
            $refine_total_pages = $line_total > 0 ? (int) ceil($line_total / $per_page) : 1;
        } else {
            $received_total_records = $line_total;
            $received_total_pages = $line_total > 0 ? (int) ceil($line_total / $per_page) : 1;
        }

        $refine_sql = 'SELECT ji.id AS line_id, j.id AS jwo_id, j.sale_order_id, j.jobwork_no, j.customer_name, '
            . 'j.order_date, j.due_date, j.status AS jwo_status, '
            . $prio_sel . ' AS jwo_priority, '
            . 'ji.product_name, ji.purity, ji.gross_weight, ji.final_weight, '
            . $tag_expr . ' AS tag_no, '
            . $rfid_sel . ' AS rfid_code, '
            . $against_expr . ', '
            . $assign_sel_r . ', '
            . $branch_sel_r . ', '
            . $jwi_inv_sel
            . $oj_refine_stocked_sel . ' '
            . 'FROM tbl_jobwork_order_items ji '
            . 'INNER JOIN tbl_jobwork_orders j ON ji.jobwork_order_id = j.id '
            . 'INNER JOIN tbl_sale_orders so ON so.id = j.sale_order_id '
            . $branch_join_r
            . $assign_join_r
            . 'LEFT JOIN tbl_products p ON p.id = ji.product_id '
            . 'LEFT JOIN tbl_product_characteristics pc ON pc.id = ji.product_characteristic_id '
            . $refine_where
            . " ORDER BY j.id DESC, ji.id ASC LIMIT $per_page OFFSET $offset";

        $line_rows = getList($refine_sql);
        if (!is_array($line_rows)) {
            $line_rows = [];
        }
        if ($active_tab === 'refine') {
            $refine_data = $line_rows;
            foreach ($refine_data as $rd) {
                $refine_totals['sum_gross_wt'] += (float) ($rd['gross_weight'] ?? 0);
                $refine_totals['sum_final_wt'] += (float) ($rd['final_weight'] ?? 0);
            }
        } else {
            $received_data = $line_rows;
            foreach ($received_data as $rd) {
                $received_totals['sum_gross_wt'] += (float) ($rd['gross_weight'] ?? 0);
                $received_totals['sum_final_wt'] += (float) ($rd['final_weight'] ?? 0);
            }
        }
    }
}

// Scrap tab: invoice numbers that already have a refinery job work order (disable duplicate JWO from scrap list)
$oj_refinery_locked_invoice_nos = [];
if ($active_tab === 'scrap' && $has_refine_jwo) {
    $needle_lock = mysqli_real_escape_string($conn, $refine_comment_needle);
    $lock_rows = getList(
        'SELECT DISTINCT TRIM(so.against_of) AS inv_no FROM tbl_jobwork_orders j '
        . 'INNER JOIN tbl_sale_orders so ON so.id = j.sale_order_id '
        . "WHERE so.comment LIKE '%{$needle_lock}%' AND TRIM(IFNULL(so.against_of,'')) <> ''"
    );
    if (is_array($lock_rows)) {
        foreach ($lock_rows as $lr) {
            $ik = trim((string) ($lr['inv_no'] ?? ''));
            if ($ik !== '') {
                $oj_refinery_locked_invoice_nos[$ik] = true;
            }
        }
    }
}

// Summary tab: grouped by product/description + metal + purity (scrap list + stock-in records)
// Use two separate queries and merge in PHP to avoid complex nested SQL that can cause MySQL 500
$summary_data = [];
$summary_total_records = 0;
$summary_total_pages = 0;
$summary_totals = ['total_gross_wt' => 0, 'total_net_wt' => 0, 'total_less_wt' => 0, 'total_stone_wt' => 0, 'total_pure_wt' => 0, 'total_amount' => 0];
if ($active_tab == 'summary') {
    $where_stock_summary = "1=1";
    if (!empty($from_date) && !empty($to_date)) {
        $where_stock_summary .= " AND DATE(s.created_at) BETWEEN '$from_date' AND '$to_date'";
    }
    if ($branch_id_in_sql !== '') {
        $where_stock_summary .= " AND s.branch_id IN ($branch_id_in_sql)";
    }
    if (!empty($invoice_no)) {
        $where_stock_summary .= " AND s.invoice_no LIKE '%$invoice_no%'";
    }

    // 1) Scrap summary: grouped from sale + OJB (no nested ORDER BY)
    $scrap_summary_sql = "SELECT
        COALESCE(q.description, q.product_name, '') as product_name,
        COALESCE(q.metal, '') as metal,
        q.purity,
        SUM(q.gross_wt) as gross_wt,
        SUM(q.net_wt) as net_wt,
        SUM(q.less_wt) as less_wt,
        SUM(q.stone_wt) as stone_wt,
        SUM(q.pure_wt) as pure_wt,
        SUM(q.amount) as amount
    FROM (" . $query_sale . " " . $query_purchase . " " . $query_scrap . ") q
    GROUP BY COALESCE(q.description, q.product_name, ''), COALESCE(q.metal, ''), q.purity
    ORDER BY product_name, metal, purity";
    $scrap_rows = getList($scrap_summary_sql);
    if (!is_array($scrap_rows)) {
        $scrap_rows = [];
    }

    // 2) Stock summary: grouped from tbl_old_jewelry_stock
    $stock_rows = [];
    if ($stock_table_exists) {
        $stock_summary_sql = "SELECT
            COALESCE(s.product,'') as product_name,
            COALESCE(s.metal,'') as metal,
            COALESCE(s.purity,0) as purity,
            SUM(s.gross_wt) as gross_wt,
            SUM(s.net_wt) as net_wt,
            SUM(s.less_wt) as less_wt,
            0.000 as stone_wt,
            SUM(s.net_wt) as pure_wt,
            SUM(s.amount) as amount
        FROM tbl_old_jewelry_stock s
        WHERE $where_stock_summary
        GROUP BY COALESCE(s.product,''), COALESCE(s.metal,''), COALESCE(s.purity,0)
        ORDER BY product_name, metal, purity";
        $stock_rows = getList($stock_summary_sql);
        if (!is_array($stock_rows)) {
            $stock_rows = [];
        }
    }

    // Merge by (product_name, metal, purity) and sum
    $merged = [];
    foreach (array_merge($scrap_rows, $stock_rows) as $r) {
        $key = (trim($r['product_name'] ?? '') . '|' . trim($r['metal'] ?? '') . '|' . ($r['purity'] ?? ''));
        if (!isset($merged[$key])) {
            $merged[$key] = [
                'product_name' => trim($r['product_name'] ?? ''),
                'metal' => trim($r['metal'] ?? ''),
                'purity' => (float)($r['purity'] ?? 0),
                'gross_wt' => 0,
                'net_wt' => 0,
                'less_wt' => 0,
                'stone_wt' => 0,
                'pure_wt' => 0,
                'amount' => 0
            ];
        }
        $merged[$key]['gross_wt'] += (float)($r['gross_wt'] ?? 0);
        $merged[$key]['net_wt'] += (float)($r['net_wt'] ?? 0);
        $merged[$key]['less_wt'] += (float)($r['less_wt'] ?? 0);
        $merged[$key]['stone_wt'] += (float)($r['stone_wt'] ?? 0);
        $merged[$key]['pure_wt'] += (float)($r['pure_wt'] ?? 0);
        $merged[$key]['amount'] += (float)($r['amount'] ?? 0);
    }
    usort($merged, function ($a, $b) {
        $c = strcmp($a['product_name'], $b['product_name']);
        if ($c !== 0) return $c;
        $c = strcmp($a['metal'], $b['metal']);
        if ($c !== 0) return $c;
        return ($a['purity'] <=> $b['purity']);
    });

    $summary_total_records = count($merged);
    $summary_total_pages = $summary_total_records > 0 ? ceil($summary_total_records / $per_page) : 0;
    $summary_data = array_slice($merged, $offset, $per_page);

    // Totals: stock part
    $summary_totals = ['total_gross_wt' => 0, 'total_net_wt' => 0, 'total_less_wt' => 0, 'total_stone_wt' => 0, 'total_pure_wt' => 0, 'total_amount' => 0];
    if ($stock_table_exists) {
        $st = getRecord("SELECT SUM(gross_wt) as gw, SUM(net_wt) as nw, SUM(less_wt) as lw, SUM(amount) as am FROM tbl_old_jewelry_stock s WHERE $where_stock_summary");
        if ($st) {
            $summary_totals['total_gross_wt'] = (float)($st['gw'] ?? 0);
            $summary_totals['total_net_wt'] = (float)($st['nw'] ?? 0);
            $summary_totals['total_less_wt'] = (float)($st['lw'] ?? 0);
            $summary_totals['total_stone_wt'] = 0;
            $summary_totals['total_pure_wt'] = (float)($st['nw'] ?? 0);
            $summary_totals['total_amount'] = (float)($st['am'] ?? 0);
        }
    }
    // Scrap totals added later when $totals is set
}

// Calculate totals (sale + scrap; scrap tab = OJB rows only)
$totals_query = "
    SELECT 
        COALESCE(SUM(sii.gross_weight), 0) as total_gross_wt,
        COALESCE(SUM(sii.less_weight), 0) as total_less_wt,
        0.000 as total_stone_wt,
        COALESCE(SUM(sii.net_weight), 0) as total_net_wt,
        COALESCE(SUM(sii.final_weight), 0) as total_final_wt,
        COALESCE(SUM(sii.pure_weight), 0) as total_pure_wt,
        COALESCE(SUM(sii.amount), 0) as total_amount,
        COALESCE(SUM(sii.quantity), 0) as total_quantity,
        COALESCE(SUM(si.grand_total), 0) as total_amount_paid
    FROM tbl_sale_invoices si
    INNER JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id AND sii.status = 1
    LEFT JOIN tbl_products p ON sii.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON sii.product_characteristic_id = pc.id
    WHERE $where_clause
";
if ($scrap_tab_ojb_only) {
    if ($stock_table_exists) {
        $oj_tot_line = "(SELECT COALESCE(SUM(sj.gross_wt),0) FROM tbl_old_jewelry_stock sj WHERE sj.source_item_id = oji.id)";
        $oj_tot_orphan = "(CASE WHEN COALESCE(oj_item_cnt.cnt, 0) = 1 THEN COALESCE(oj_orphan.orphan_gross, 0) ELSE 0 END)";
        $oj_tot_eff = "($oj_tot_line + $oj_tot_orphan)";
        $oj_tot_rem = "GREATEST(0, COALESCE(oji.gross_wt,0) - COALESCE($oj_tot_eff,0))";
    } else {
        $oj_tot_rem = "COALESCE(oji.gross_wt,0)";
    }
    $oj_tot_ratio = $stock_table_exists
        ? "(CASE WHEN COALESCE(oji.gross_wt,0) > 0.00001 THEN ($oj_tot_rem / oji.gross_wt) ELSE 0 END)"
        : '1';
    $oj_tot_gross_rem = $stock_table_exists ? $oj_tot_rem : "COALESCE(oji.gross_wt,0)";
    $oj_tot_less = ($scrap_has_less_wt && $stock_table_exists) ? "COALESCE(SUM(COALESCE(oji.less_wt,0) * $oj_tot_ratio), 0)" : "0.000";
    $oj_tot_stone = $stock_table_exists ? "COALESCE(SUM(COALESCE(oji.gemstone_wt, oji.diamond_wt, 0) * $oj_tot_ratio), 0)" : "COALESCE(SUM(COALESCE(oji.gemstone_wt, oji.diamond_wt, 0)), 0)";
    $totals_query = "
    SELECT
        COALESCE(SUM($oj_tot_gross_rem), 0) as total_gross_wt,
        $oj_tot_less as total_less_wt,
        $oj_tot_stone as total_stone_wt,
        COALESCE(SUM(COALESCE(oji.net_wt,0) * $oj_tot_ratio), 0) as total_net_wt,
        COALESCE(SUM(COALESCE(oji.final_wt,0) * $oj_tot_ratio), 0) as total_final_wt,
        COALESCE(SUM(COALESCE(oji.pure_wt,0) * $oj_tot_ratio), 0) as total_pure_wt,
        COALESCE(SUM(COALESCE(oji.amount,0) * $oj_tot_ratio), 0) as total_amount,
        COALESCE(SUM(oji.quantity), 0) as total_quantity,
        COALESCE(SUM(oinv.grand_total), 0) as total_amount_paid
    FROM tbl_old_jewelry_scrap_invoices oinv
    INNER JOIN tbl_old_jewelry_scrap_invoice_items oji ON oinv.id = oji.invoice_id AND oji.status = 1
    $oj_stock_balance_joins
    WHERE $where_scrap ";
} elseif ($scrap_tables_exist && ($active_tab == 'scrap' || $active_tab == 'summary' || $active_tab == 'refine' || $active_tab == 'received' || $active_tab == 'stocked')) {
    if ($stock_table_exists) {
        $oj_tot_line_u = "(SELECT COALESCE(SUM(sj.gross_wt),0) FROM tbl_old_jewelry_stock sj WHERE sj.source_item_id = oji.id)";
        $oj_tot_orphan_u = "(CASE WHEN COALESCE(oj_item_cnt.cnt, 0) = 1 THEN COALESCE(oj_orphan.orphan_gross, 0) ELSE 0 END)";
        $oj_tot_eff_u = "($oj_tot_line_u + $oj_tot_orphan_u)";
        $oj_tot_gross_rem_u = "GREATEST(0, COALESCE(oji.gross_wt,0) - COALESCE($oj_tot_eff_u,0))";
        $oj_tot_rem_u = $oj_tot_gross_rem_u;
    } else {
        $oj_tot_gross_rem_u = "COALESCE(oji.gross_wt,0)";
        $oj_tot_rem_u = $oj_tot_gross_rem_u;
    }
    $oj_tot_ratio_u = $stock_table_exists
        ? "(CASE WHEN COALESCE(oji.gross_wt,0) > 0.00001 THEN ($oj_tot_rem_u / oji.gross_wt) ELSE 0 END)"
        : '1';
    $oj_tot_less_u = ($scrap_has_less_wt && $stock_table_exists) ? "COALESCE(SUM(COALESCE(oji.less_wt,0) * $oj_tot_ratio_u), 0)" : "0.000";
    $oj_tot_stone_u = $stock_table_exists ? "COALESCE(SUM(COALESCE(oji.gemstone_wt, oji.diamond_wt, 0) * $oj_tot_ratio_u), 0)" : "COALESCE(SUM(COALESCE(oji.gemstone_wt, oji.diamond_wt, 0)), 0)";
    $totals_query .= "
    UNION ALL
    SELECT
        COALESCE(SUM($oj_tot_gross_rem_u), 0),
        $oj_tot_less_u,
        $oj_tot_stone_u,
        COALESCE(SUM(COALESCE(oji.net_wt,0) * $oj_tot_ratio_u), 0),
        COALESCE(SUM(COALESCE(oji.final_wt,0) * $oj_tot_ratio_u), 0),
        COALESCE(SUM(COALESCE(oji.pure_wt,0) * $oj_tot_ratio_u), 0),
        COALESCE(SUM(COALESCE(oji.amount,0) * $oj_tot_ratio_u), 0),
        COALESCE(SUM(oji.quantity), 0),
        COALESCE(SUM(oinv.grand_total), 0)
    FROM tbl_old_jewelry_scrap_invoices oinv
    INNER JOIN tbl_old_jewelry_scrap_invoice_items oji ON oinv.id = oji.invoice_id AND oji.status = 1
    $oj_stock_balance_joins
    WHERE $where_scrap
    ";
}
$totals_query = "SELECT SUM(total_gross_wt) as total_gross_wt, SUM(total_less_wt) as total_less_wt, SUM(total_stone_wt) as total_stone_wt, SUM(total_net_wt) as total_net_wt, SUM(total_final_wt) as total_final_wt, SUM(total_pure_wt) as total_pure_wt, SUM(total_amount) as total_amount, SUM(total_quantity) as total_quantity, SUM(total_amount_paid) as total_amount_paid FROM ($totals_query) u";

$totals_result = getRecord($totals_query);
$totals = [
    'total_gross_wt' => (float)($totals_result['total_gross_wt'] ?? 0),
    'total_less_wt' => (float)($totals_result['total_less_wt'] ?? 0),
    'total_stone_wt' => (float)($totals_result['total_stone_wt'] ?? 0),
    'total_net_wt' => (float)($totals_result['total_net_wt'] ?? 0),
    'total_final_wt' => (float)($totals_result['total_final_wt'] ?? 0),
    'total_pure_wt' => (float)($totals_result['total_pure_wt'] ?? 0),
    'total_amount' => (float)($totals_result['total_amount'] ?? 0),
    'total_quantity' => (float)($totals_result['total_quantity'] ?? 0),
    'total_amount_paid' => (float)($totals_result['total_amount_paid'] ?? 0)
];

// Summary tab: add scrap totals to summary_totals (stock totals already added in summary block)
if ($active_tab == 'summary' && isset($summary_totals)) {
    $summary_totals['total_gross_wt'] += $totals['total_gross_wt'];
    $summary_totals['total_net_wt'] += $totals['total_net_wt'];
    $summary_totals['total_less_wt'] += $totals['total_less_wt'];
    $summary_totals['total_stone_wt'] += $totals['total_stone_wt'];
    $summary_totals['total_pure_wt'] += $totals['total_pure_wt'];
    $summary_totals['total_amount'] += $totals['total_amount'];
}

$total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Old Jewellery - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="Old Jewellery Management - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software" />
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
</head>

<style>
html, body {
    overflow-x: hidden !important;
    height: 100vh;
}

/* Hide sidebar and default template navbar (same pattern as purchase-invoice) */
#layout-sidenav {
    display: none !important;
}

#layout-navbar {
    display: none !important;
}

.layout-container {
    margin-left: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}

.layout-sidenav-toggle {
    display: none !important;
}

.layout-content {
    height: calc(100vh - 140px);
    overflow: hidden;
    margin: 0 !important;
    padding: 0 !important;
    background: #f4f6fb;
}

/* Top bar: logo + utilities (light) — match GoldMatrix / purchase-invoice */
/* Main nav: navy bar, white UPPERCASE labels, gold icons */
.top-navbar {
    background: #11294b;
    padding: 8px 8px 0 8px;
    margin: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    border-radius: 0;
}

.top-navbar .nav {
    padding-left: 12px;
    padding-right: 12px;
    flex-wrap: wrap;
}

.top-navbar .nav-item {
    position: relative !important;
}

.top-navbar .nav-link {
    color: #ffffff;
    padding: 12px 14px 14px;
    font-weight: 700;
    font-size: 11px;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    border-bottom: 3px solid transparent;
    border-radius: 6px 6px 0 0;
    transition: all 0.2s ease;
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-right: 0.15rem;
}

.top-navbar .nav-link i {
    font-size: 15px;
    color: #c5a864;
    font-weight: normal;
}

.top-navbar .nav-link:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
}

.top-navbar .nav-link:hover i {
    color: #e8d5a3;
}

.top-navbar .nav-link.active {
    color: #11294b;
    background: #c5a864;
    border-bottom-color: #a68a4a;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.top-navbar .nav-link.active i {
    color: #11294b;
}

.top-navbar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-right: 15px;
}

.top-navbar-right .btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #fff;
    font-size: 1.1rem;
}

.top-navbar-right .btn-icon:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #c5a864;
}

.badge-notification {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #f44336;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Company header: desktop only — mobile uses grid from newcss.css (sidebar) */
@media (min-width: 992px) {
.company-header {
    background: linear-gradient(to bottom, #ffffff 0%, #f5f7fa 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #11294b;
}
}

.company-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.company-info img {
    max-height: 48px;
    width: auto;
}

.company-logo img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

.company-info h4 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #11294b;
}

.company-info small {
    font-size: 0.75rem;
    color: #64748b;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.company-header .user-info .btn-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #11294b;
    box-shadow: 0 1px 3px rgba(17, 41, 75, 0.08);
}

.company-header .user-info .btn-icon:hover {
    background: #f8fafc;
    color: #c5a864;
    border-color: #c5a864;
}

.pos-btn {
    background: linear-gradient(180deg, #c5a864 0%, #a68a4a 100%);
    color: #fff;
    border: none;
    padding: 8px 22px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(166, 138, 74, 0.35);
}

.pos-btn:hover {
    background: linear-gradient(180deg, #d4b87a 0%, #b8995c 100%);
    color: #fff;
}

.user-dropdown {
    position: relative;
}

.user-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 200px;
    padding: 0.5rem 0;
    margin-top: 5px;
    display: none;
    z-index: 1000;
}

.user-dropdown:hover .user-dropdown-menu {
    display: block;
}

.user-dropdown-menu .dropdown-item {
    padding: 8px 15px;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s;
}

.user-dropdown-menu .dropdown-item:hover {
    background: #f8fafc;
    color: #11294b;
}

.user-dropdown-menu .dropdown-divider {
    height: 1px;
    background: #e0e0e0;
    margin: 0.5rem 0;
}

.container-fluid {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Page Header */
.page-header-bar {
    background: #11294b;
    color: #fff;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    font-size: 12px;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.page-header-actions .btn-icon {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.page-header-actions .btn-icon:hover {
    background: rgba(255,255,255,0.3);
}

/* Tabs */
.tabs-container {
    background: #fff;
    border-bottom: 2px solid #e2e8f0;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tabs-list {
    display: flex;
    gap: 0;
    margin: 0;
    padding: 0;
    list-style: none;
}

.tab-link {
    display: block;
    padding: 12px 20px;
    color: #64748b;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
    background: transparent;
    border: none;
}

.tab-link:hover {
    color: #11294b;
    background: #f8fafc;
}

.tab-link.active {
    color: #11294b;
    border-bottom-color: #11294b;
    font-weight: 600;
    background: #f8fafc;
}

.tab-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.tab-actions .btn-icon {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #64748b;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.tab-actions .btn-icon:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

/* Mobile: tabs in one horizontal row + horizontal scroll; actions below */
@media (max-width: 991.98px) {
    .layout-content {
        height: auto !important;
        min-height: calc(100dvh - 120px);
        overflow-x: hidden;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 72px !important;
    }
    .tabs-container {
        flex-direction: column !important;
        align-items: stretch !important;
        justify-content: flex-start !important;
        gap: 10px;
        padding: 10px 12px !important;
    }
    .tabs-list {
        display: flex !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        -webkit-overflow-scrolling: touch;
        width: 100%;
        max-width: 100%;
        gap: 0;
        margin: 0 !important;
        padding: 0 0 6px 0 !important;
        border-bottom: 1px solid #e2e8f0;
        scrollbar-width: thin;
    }
    .tabs-list::-webkit-scrollbar {
        height: 6px;
    }
    .tabs-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .tabs-list li {
        display: flex !important;
        flex-shrink: 0;
    }
    .tabs-list .tab-link {
        white-space: nowrap;
        padding: 10px 14px;
        font-size: 0.78rem;
    }
    .tab-actions {
        width: 100%;
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 8px;
    }
}

/* Column picker: popover under gear (sale-invoice style; fixed position avoids card overflow clip) */
.oj-columns-wrap {
    position: relative;
    display: inline-flex;
    vertical-align: middle;
    align-items: center;
}
.oj-columns-dropdown {
    display: none;
    position: fixed;
    flex-direction: column;
    z-index: 4000;
    width: min(320px, calc(100vw - 16px));
    min-width: 260px;
    max-height: min(70vh, 480px);
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
    overflow: hidden;
    box-sizing: border-box;
}
.oj-columns-dropdown.show {
    display: flex;
}
.oj-columns-dropdown-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #334155;
    text-transform: uppercase;
    flex-shrink: 0;
}
.oj-columns-dropdown-header-actions {
    display: flex;
    align-items: center;
    gap: 2px;
}
.oj-columns-icon-btn {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 4px 6px;
    line-height: 1;
    border-radius: 4px;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.oj-columns-icon-btn:hover {
    background: #e2e8f0;
    color: #11294b;
}
.oj-columns-search {
    padding: 8px 10px;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.oj-columns-search input {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    box-sizing: border-box;
}
.oj-columns-search input:focus {
    outline: none;
    border-color: #11294b;
}
.oj-columns-list {
    overflow-y: auto;
    padding: 6px 0;
    flex: 1;
    min-height: 0;
}

.tab-actions .export-btn {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 6px 16px;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

.tab-actions .export-btn:hover {
    background: #4a2f70;
}

/* Table Container */
.table-container {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #fff;
}

.table-wrapper {
    flex: 1;
    overflow: auto;
    /* No left padding: avoids a wide empty strip before the first column (checkbox). Top/right/bottom keep spacing for scroll and footer. */
    padding: 16px 20px 20px 0;
}

/* Table Styling */
.old-jewellery-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.old-jewellery-table thead {
    background: #f8fafc;
    position: sticky;
    top: 0;
    z-index: 10;
}

.old-jewellery-table th {
    padding: 12px 10px;
    text-align: left;
    font-weight: 600;
    color: #11294b;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    font-size: 0.8rem;
}

.old-jewellery-table th.sortable {
    cursor: pointer;
    user-select: none;
}

.old-jewellery-table th.sortable:hover {
    background: #f1f5f9;
}

.old-jewellery-table th .sort-arrows {
    display: inline-flex;
    flex-direction: column;
    margin-left: 4px;
    vertical-align: middle;
    font-size: 0.7rem;
    opacity: 0.5;
}

.old-jewellery-table th.sortable:hover .sort-arrows {
    opacity: 1;
}

/* Column reorder (Old Jewellery-Scrap main table) */
#oldJewelleryTable thead th.oj-col-drop-target {
    box-shadow: inset 0 0 0 2px #b8922e;
    background: #fffbeb;
}
#oldJewelleryTable thead th.oj-col-drag-source {
    background: #fff !important;
    box-shadow: inset 0 0 0 2px #11294b, 0 2px 8px rgba(17, 41, 75, 0.12);
    opacity: 0.92;
}
#oldJewelleryTable.oj-col-dragging thead th .oj-col-drag-handle {
    cursor: grabbing;
}
#oldJewelleryTable.oj-col-dragging {
    cursor: grabbing;
}
/* Old Jewellery-Scrap main table — vertical/horizontal lines between columns */
#oldJewelleryTable tbody tr {
    border-bottom: none;
}
#oldJewelleryTable th,
#oldJewelleryTable td {
    border: 1px solid #cbd5e1;
    vertical-align: middle;
}
#oldJewelleryTable thead th {
    border-color: #cbd5e1;
    border-bottom: 1px solid #94a3b8;
    background: #f8fafc;
}
#oldJewelleryTable thead th.oj-col-drop-target {
    border-color: #b8922e;
}
#oldJewelleryTable thead th.oj-col-drag-source {
    border-color: #11294b;
}
/* Floating label follows pointer (like Product Selection column drag) */
.oj-col-drag-ghost {
    position: fixed;
    z-index: 10050;
    left: 0;
    top: 0;
    display: none;
    padding: 8px 16px;
    background: #11294b;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 999px;
    box-shadow: 0 6px 20px rgba(17, 41, 75, 0.35);
    pointer-events: none;
    white-space: nowrap;
    max-width: min(280px, 90vw);
    overflow: hidden;
    text-overflow: ellipsis;
}
.oj-col-drag-handle {
    display: inline-block;
    cursor: grab;
    margin-right: 6px;
    margin-left: -2px;
    vertical-align: middle;
    color: #94a3b8;
    line-height: 1;
    user-select: none;
    -webkit-user-select: none;
    touch-action: none;
    flex-shrink: 0;
}
.oj-col-drag-handle:hover {
    color: #b8922e;
}
.oj-col-drag-handle:active {
    cursor: grabbing;
}
/* Let the handle receive all pointer events (Feather may replace <i> with SVG) */
.oj-col-drag-handle .feather,
.oj-col-drag-handle svg {
    width: 15px;
    height: 15px;
    pointer-events: none;
    vertical-align: middle;
}

.old-jewellery-table tbody tr {
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.2s;
}

.old-jewellery-table th.oj-scrap-select-col,
.old-jewellery-table td[data-column="active"] {
    text-align: center;
    vertical-align: middle;
}
.old-jewellery-table .oj-scrap-select-all,
.old-jewellery-table .oj-scrap-row-cb {
    width: 1rem;
    height: 1rem;
    cursor: pointer;
    vertical-align: middle;
}
.old-jewellery-table tbody tr:hover {
    background: #f8fafc;
}

.old-jewellery-table td {
    padding: 12px 10px;
    color: #1e293b;
    vertical-align: middle;
}

.old-jewellery-table td.text-right {
    text-align: right;
}

/* Summary tab table */
#summaryTable { min-width: 100%; }
#summaryTable th { background: #f8fafc; padding: 12px 10px; font-weight: 600; }
#summaryTable th.text-right { text-align: right; }
#summaryTable .summary-total-row th {
    background: #eef2ff;
    border-top: 2px solid #c7d2fe;
    padding: 12px 10px;
    font-weight: 600;
    color: #3730a3;
}
#summaryTable .summary-total-row th.text-right { text-align: right; }

/* Refine tab: stretch table to full card width + column drag targets */
#refineReceivedWrapper {
    width: 100%;
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
}
#refineReceivedWrapper > .table-wrapper {
    flex: 1 1 auto;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 12px 0 16px 0;
    overflow-x: auto;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
#refineTable {
    table-layout: auto;
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
}
#refineTable tbody tr {
    border-bottom: none;
}
#refineTable th,
#refineTable td {
    border: 1px solid #cbd5e1;
    word-wrap: break-word;
    overflow-wrap: break-word;
    vertical-align: middle;
    padding: 10px 12px;
}
#refineTable thead th {
    white-space: nowrap;
    background: #11294b;
    color: #fff;
    border-color: #1e3a5c;
    border-bottom: 1px solid #0a1f36;
    font-weight: 600;
}
#refineTable thead th .oj-col-drag-handle {
    color: rgba(255, 255, 255, 0.72);
}
#refineTable thead th .oj-col-drag-handle:hover {
    color: #e8d48a;
}
#receivedRefineTable {
    table-layout: auto;
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
}
#receivedRefineTable tbody tr {
    border-bottom: none;
}
#receivedRefineTable th,
#receivedRefineTable td {
    border: 1px solid #cbd5e1;
    word-wrap: break-word;
    overflow-wrap: break-word;
    vertical-align: middle;
    padding: 10px 12px;
}
#receivedRefineTable thead th {
    white-space: nowrap;
    background: #11294b;
    color: #fff;
    border-color: #1e3a5c;
    border-bottom: 1px solid #0a1f36;
    font-weight: 600;
}
#receivedRefineTable thead th .oj-col-drag-handle {
    color: rgba(255, 255, 255, 0.72);
}
#receivedRefineTable thead th .oj-col-drag-handle:hover {
    color: #e8d48a;
}
#receivedRefineTable thead th.oj-col-drop-target {
    box-shadow: inset 0 0 0 2px #b8922e;
    background: #fffbeb;
    color: #11294b;
}
#receivedRefineTable thead th.oj-col-drop-target .oj-col-drag-handle {
    color: #64748b;
}
#receivedRefineTable thead th.oj-col-drag-source {
    background: #fff !important;
    color: #11294b;
    box-shadow: inset 0 0 0 2px #11294b, 0 2px 8px rgba(17, 41, 75, 0.12);
    opacity: 0.92;
}
#receivedRefineTable thead th.oj-col-drag-source .oj-col-drag-handle {
    color: #64748b;
}
#receivedRefineTable.oj-col-dragging thead th .oj-col-drag-handle {
    cursor: grabbing;
}
#receivedRefineTable.oj-col-dragging {
    cursor: grabbing;
}
#receivedRefineTable th[data-column="rfid"],
#receivedRefineTable td[data-column="rfid"] { min-width: 104px; }
#receivedRefineTable th[data-column="job-order"],
#receivedRefineTable td[data-column="job-order"] { min-width: 108px; }
#receivedRefineTable th[data-column="against-ref"],
#receivedRefineTable td[data-column="against-ref"] { min-width: 112px; }
#receivedRefineTable th[data-column="customer"],
#receivedRefineTable td[data-column="customer"] { min-width: 150px; }
#receivedRefineTable th[data-column="short-desc"],
#receivedRefineTable td[data-column="short-desc"] { min-width: 140px; }
#receivedRefineTable th[data-column="purity"],
#receivedRefineTable td[data-column="purity"] { min-width: 76px; }
#receivedRefineTable th[data-column="gross-wt"],
#receivedRefineTable td[data-column="gross-wt"],
#receivedRefineTable th[data-column="final-wt"],
#receivedRefineTable td[data-column="final-wt"] { min-width: 92px; }
#receivedRefineTable th[data-column="assign"],
#receivedRefineTable td[data-column="assign"] { min-width: 110px; }
#receivedRefineTable th[data-column="due-date"],
#receivedRefineTable td[data-column="due-date"],
#receivedRefineTable th[data-column="order-dt"],
#receivedRefineTable td[data-column="order-dt"] { min-width: 102px; }
#receivedRefineTable th[data-column="tag-no"],
#receivedRefineTable td[data-column="tag-no"] { min-width: 108px; }
#receivedRefineTable th[data-column="status"],
#receivedRefineTable td[data-column="status"] { min-width: 108px; }
#receivedRefineTable th[data-column="priority"],
#receivedRefineTable td[data-column="priority"] { min-width: 96px; }
#receivedRefineTable th[data-column="jw-inv"],
#receivedRefineTable td[data-column="jw-inv"] { min-width: 112px; }
#receivedRefineTable th[data-column="stock-in"],
#receivedRefineTable td[data-column="stock-in"] { min-width: 128px; white-space: nowrap; }
#receivedRefineTable th[data-column="print"],
#receivedRefineTable td[data-column="print"],
#receivedRefineTable th[data-column="track"],
#receivedRefineTable td[data-column="track"] { min-width: 64px; }
#receivedRefineTable th[data-column="branch"],
#receivedRefineTable td[data-column="branch"] { min-width: 132px; }
/* Readable column widths — table scrolls horizontally when wider than the card */
#refineTable th[data-column="rfid"],
#refineTable td[data-column="rfid"] { min-width: 104px; }
#refineTable th[data-column="job-order"],
#refineTable td[data-column="job-order"] { min-width: 108px; }
#refineTable th[data-column="against-ref"],
#refineTable td[data-column="against-ref"] { min-width: 112px; }
#refineTable th[data-column="customer"],
#refineTable td[data-column="customer"] { min-width: 150px; }
#refineTable th[data-column="short-desc"],
#refineTable td[data-column="short-desc"] { min-width: 140px; }
#refineTable th[data-column="purity"],
#refineTable td[data-column="purity"] { min-width: 76px; }
#refineTable th[data-column="gross-wt"],
#refineTable td[data-column="gross-wt"],
#refineTable th[data-column="final-wt"],
#refineTable td[data-column="final-wt"] { min-width: 92px; }
#refineTable th[data-column="assign"],
#refineTable td[data-column="assign"] { min-width: 110px; }
#refineTable th[data-column="due-date"],
#refineTable td[data-column="due-date"],
#refineTable th[data-column="order-dt"],
#refineTable td[data-column="order-dt"] { min-width: 102px; }
#refineTable th[data-column="tag-no"],
#refineTable td[data-column="tag-no"] { min-width: 108px; }
#refineTable th[data-column="status"],
#refineTable td[data-column="status"] { min-width: 108px; }
#refineTable th[data-column="priority"],
#refineTable td[data-column="priority"] { min-width: 96px; }
#refineTable th[data-column="invoice"],
#refineTable td[data-column="invoice"] { min-width: 128px; }
#refineTable th[data-column="print"],
#refineTable td[data-column="print"],
#refineTable th[data-column="track"],
#refineTable td[data-column="track"] { min-width: 64px; }
#refineTable th[data-column="branch"],
#refineTable td[data-column="branch"] { min-width: 132px; }
#refineTable thead th.oj-col-drop-target {
    box-shadow: inset 0 0 0 2px #b8922e;
    background: #fffbeb;
    color: #11294b;
}
#refineTable thead th.oj-col-drop-target .oj-col-drag-handle {
    color: #64748b;
}
#refineTable thead th.oj-col-drag-source {
    background: #fff !important;
    color: #11294b;
    box-shadow: inset 0 0 0 2px #11294b, 0 2px 8px rgba(17, 41, 75, 0.12);
    opacity: 0.92;
}
#refineTable thead th.oj-col-drag-source .oj-col-drag-handle {
    color: #64748b;
}
#refineTable.oj-col-dragging thead th .oj-col-drag-handle {
    cursor: grabbing;
}
#refineTable.oj-col-dragging {
    cursor: grabbing;
}

/* Table Footer */
.table-footer {
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.table-footer-info {
    color: #64748b;
    font-size: 0.875rem;
}

.table-footer-totals {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.table-footer-totals .total-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: #11294b;
    min-width: 80px;
    text-align: right;
}

.pagination-controls {
    display: flex;
    gap: 5px;
    align-items: center;
}

.pagination-controls .page-btn {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.pagination-controls .page-btn:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination-controls .page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-controls .page-btn.active {
    background: #11294b;
    color: #fff;
    border-color: #11294b;
}

.pagination-controls .show-all-dropdown {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 0.875rem;
    color: #64748b;
    background: #fff;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
    font-size: 0.875rem;
}
</style>

<body>
<!-- [ Preloader ] Start -->
<div class="page-loader">
    <div class="bg-primary"></div>
</div>
<!-- [ Preloader ] End -->

<!-- [ Layout wrapper ] Start -->
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <!-- [ Layout container ] Start -->
        <div class="layout-container">
            <!-- Top Navigation Header -->
            <?php include 'sidebar.php';?>

            <!-- [ Layout content ] Start -->
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card mb-4" style="height: calc(100vh - 120px); display: flex; flex-direction: column; overflow: hidden;">
                                <div class="card-body" style="padding: 0; display: flex; flex-direction: column; overflow: hidden;">

                                    <!-- Page Header -->
                                   

                                    <!-- 5 Tabs: Old Jewellery-Scrap, Summary, Refine, Received, Stocked -->
                                    <div class="tabs-container" role="navigation" style="display: flex !important; visibility: visible !important;">
                                        <ul class="tabs-list" style="display: flex !important; flex-wrap: nowrap; list-style: none; margin: 0; padding: 0;">
                                            <li style="display: flex; flex-shrink: 0;"><a href="old-jewellery.php?tab=scrap<?= $oj_tab_extra ?>" class="tab-link <?= $active_tab == 'scrap' ? 'active' : '' ?>">Old Jewellery-Scrap</a></li>
                                            <li style="display: flex; flex-shrink: 0;"><a href="old-jewellery.php?tab=summary<?= $oj_tab_extra ?>" class="tab-link <?= $active_tab == 'summary' ? 'active' : '' ?>">Summary</a></li>
                                            <li style="display: flex; flex-shrink: 0;"><a href="old-jewellery.php?tab=refine<?= $oj_tab_extra ?>" class="tab-link <?= $active_tab == 'refine' ? 'active' : '' ?>">Refine</a></li>
                                            <li style="display: flex; flex-shrink: 0;"><a href="old-jewellery.php?tab=received<?= $oj_tab_extra ?>" class="tab-link <?= $active_tab == 'received' ? 'active' : '' ?>">Received</a></li>
                                            <li style="display: flex; flex-shrink: 0;"><a href="old-jewellery.php?tab=stocked<?= $oj_tab_extra ?>" class="tab-link <?= $active_tab == 'stocked' ? 'active' : '' ?>">Stocked</a></li>
                                        </ul>
                                        <div class="tab-actions">
                                            <button type="button" id="ojBtnJobworkFromScrap" class="btn btn-sm btn-primary" style="margin-right:8px; background:#11294b; border:none;" disabled title="Select one or more scrap lines"><i class="feather icon-plus"></i> Job work order/Refinery</button>
                                            <button class="btn-icon" title="Filter" onclick="openFilterModal()">
                                                <i class="feather icon-filter"></i>
                                            </button>
                                            <div class="oj-columns-wrap">
                                                <button type="button" class="btn-icon oj-columns-trigger" id="oldJwlColumnsToggle" title="Show / hide columns" aria-expanded="false" aria-haspopup="true">
                                                    <i class="feather icon-settings"></i>
                                                </button>
                                                <div class="oj-columns-dropdown" id="oldJwlColumnsDropdown" role="dialog" aria-label="Show or hide columns">
                                                    <div class="oj-columns-dropdown-header">
                                                        <span>Show / hide columns</span>
                                                        <div class="oj-columns-dropdown-header-actions">
                                                            <button type="button" class="oj-columns-icon-btn" onclick="refreshColumns()" title="Reset columns"><i class="feather icon-refresh-cw"></i></button>
                                                            <button type="button" class="oj-columns-icon-btn" onclick="closeColumnsModal()" title="Close">&times;</button>
                                                        </div>
                                                    </div>
                                                    <div class="oj-columns-hint" style="font-size:11px;color:#64748b;padding:0 12px 8px;line-height:1.35;">Drag the <i class="feather icon-move" style="width:12px;height:12px;vertical-align:middle;"></i> move icon on a column header to reorder. Saved in this browser.</div>
                                                    <div class="oj-columns-search">
                                                        <input type="text" id="columnSearch" placeholder="Search columns..." autocomplete="off" onkeyup="filterColumns()">
                                                    </div>
                                                    <div id="columnsList" class="oj-columns-list"></div>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="export-btn" data-toggle="dropdown">
                                                    Export <i class="feather icon-chevron-down"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="#" onclick="exportToExcel()">
                                                        <i class="feather icon-file-text"></i> Excel
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="exportToPDF()">
                                                        <i class="feather icon-file"></i> PDF
                                                    </a>
                                                </div>
                                            </div>
                                            <button class="export-btn" onclick="printTable()" style="margin-left: 5px;">
                                                <i class="feather icon-printer"></i> Print
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Table Container -->
                                        <div class="table-container">
                                        <div class="table-wrapper">
                                            <!-- Main table: visible only for Old Jewellery-Scrap tab -->
                                            <div id="mainTableWrapper" style="<?= ($active_tab == 'scrap') ? '' : 'display:none' ?>">
                                            <table class="table old-jewellery-table" id="oldJewelleryTable">
                                                <thead>
                                                    <tr>
                                                        <?php if ($active_tab != 'stocked' || !$stock_table_exists) { ?>
                                                        <th data-column="active" class="text-center oj-scrap-select-col" style="min-width: 48px; vertical-align: middle;">
                                                            <input type="checkbox" id="ojScrapSelectAll" class="oj-scrap-select-all" title="Select all" aria-label="Select all rows">
                                                        </th>
                                                        <th data-column="amount-paid" class="sortable text-right" style="min-width: 100px;">
                                                            Amount Paid
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="description" class="sortable" style="min-width: 150px;">
                                                            Description
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="invoice-no" class="sortable" style="min-width: 120px;">
                                                            Invoice No.
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="customer-name" class="sortable" style="min-width: 150px;">
                                                            Customer Name
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="metal" class="sortable" style="min-width: 100px;">
                                                            Metal
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="product-name" class="sortable" style="min-width: 150px;">
                                                            Product Name
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="location" class="sortable" style="min-width: 100px;">
                                                            Location
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="gross-wt" class="sortable text-right" style="min-width: 100px;">
                                                            Gross Wt
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="less-wt" class="sortable text-right" style="min-width: 100px;">
                                                            Less Wt
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="stone-wt" class="sortable text-right" style="min-width: 100px;">
                                                            Stone Wt
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="net-wt" class="sortable text-right" style="min-width: 100px;">
                                                            Net Wt
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="purity" class="sortable text-right" style="min-width: 80px;">
                                                            Purity
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="date" class="sortable" style="min-width: 100px;">
                                                            Date
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="final-wt" class="sortable text-right" style="min-width: 100px;">
                                                            Final Wt.
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="rate" class="sortable text-right" style="min-width: 100px;">
                                                            Rate
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="amount" class="sortable text-right" style="min-width: 100px;">
                                                            Amount
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="quantity" class="sortable text-right" style="min-width: 80px;">
                                                            Quantity
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="branch-name" class="sortable" style="min-width: 120px;">
                                                            Branch Name
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="sales-person" class="sortable" style="min-width: 120px;">
                                                            Sales Person
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="pure-wt" class="sortable text-right" style="min-width: 100px;">
                                                            Pure Wt
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="current-gold-rate" class="sortable text-right" style="min-width: 120px;">
                                                            Current Gold Rate
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="source" class="sortable" style="min-width: 100px;">
                                                            Source
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="against-invoice-no" class="sortable" style="min-width: 120px;">
                                                            Against Invoice No
                                                            <span class="sort-arrows">
                                                                <i class="feather icon-chevron-up"></i>
                                                                <i class="feather icon-chevron-down"></i>
                                                            </span>
                                                        </th>
                                                        <th data-column="stock-in" style="min-width: 120px; text-align: center;">Stock In</th>
                                                        <th data-column="print" style="min-width: 72px; text-align: center;">Print</th>
                                                        <?php } ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    if (!empty($old_jewellery_data)) {
                                                        $sr_no = $offset + 1;
                                                        foreach($old_jewellery_data as $row) {
                                                            $date = $row['date'] ? date('d-m-Y', strtotime($row['date'])) : '';
                                                            $row_id = $row['id'] ?? 0;
                                                            $item_id = $row['item_id'] ?? 0;
                                                            $inv_no_plain = trim((string) ($row['invoice_no'] ?? ''));
                                                            $inv_no = htmlspecialchars($row['invoice_no'] ?? '');
                                                            $is_ojb = (isset($row['source']) && ($row['source'] === 'Scrap Invoice' || $row['source'] === 'Purchase Invoice'));
                                                            $is_exchange = (isset($row['source']) && $row['source'] === 'Exchange');
                                                            $jw_refinery_locked = $is_ojb && $inv_no_plain !== '' && !empty($oj_refinery_locked_invoice_nos[$inv_no_plain]);
                                                            $allow_jw = ($is_ojb && !$jw_refinery_locked) ? '1' : '0';
                                                            $tr_lock_attr = $jw_refinery_locked ? ' data-jw-refinery-locked="1"' : '';
                                                            $cb_disabled = $jw_refinery_locked ? ' disabled' : '';
                                                            $cb_title = $jw_refinery_locked ? ' title="Refinery job work order already exists for this invoice"' : '';
                                                            echo '<tr data-row-id="'.(int)$row_id.'" data-item-id="'.(int)$item_id.'" data-invoice-no="'.$inv_no.'" data-allow-jobwork="'.$allow_jw.'"'.$tr_lock_attr.'>';
                                                            echo '<td data-column="active" class="text-center"><input type="checkbox" class="oj-scrap-row-cb" name="oj_scrap_row[]" value="'.(int)$item_id.'" data-invoice-id="'.(int)$row_id.'" data-item-id="'.(int)$item_id.'" data-allow-jobwork="'.$allow_jw.'" aria-label="Select row"'.$cb_disabled.$cb_title.'></td>';
                                                            echo '<td data-column="amount-paid" class="text-right">'.number_format($row['amount_paid'] ?? 0, 2).'</td>';
                                                            echo '<td data-column="description">'.htmlspecialchars($row['description'] ?? '').'</td>';
                                                            echo '<td data-column="invoice-no">'.htmlspecialchars($row['invoice_no'] ?? '').'</td>';
                                                            echo '<td data-column="customer-name">'.htmlspecialchars($row['customer_name'] ?? '').'</td>';
                                                            echo '<td data-column="metal">'.htmlspecialchars($row['metal'] ?? '').'</td>';
                                                            echo '<td data-column="product-name">'.htmlspecialchars($row['product_name'] ?? '').'</td>';
                                                            echo '<td data-column="location">'.htmlspecialchars($row['location'] ?? '').'</td>';
                                                            echo '<td data-column="gross-wt" class="text-right">'.number_format($row['gross_wt'] ?? 0, 6).'</td>';
                                                            echo '<td data-column="less-wt" class="text-right">'.number_format($row['less_wt'] ?? 0, 6).'</td>';
                                                            echo '<td data-column="stone-wt" class="text-right">'.number_format($row['stone_wt'] ?? 0, 6).'</td>';
                                                            echo '<td data-column="net-wt" class="text-right">'.number_format($row['net_wt'] ?? 0, 6).'</td>';
                                                            echo '<td data-column="purity" class="text-right">'.number_format($row['purity'] ?? 0, 2).'</td>';
                                                            echo '<td data-column="date">'.htmlspecialchars($date).'</td>';
                                                            echo '<td data-column="final-wt" class="text-right">'.number_format($row['final_wt'] ?? 0, 6).'</td>';
                                                            echo '<td data-column="rate" class="text-right">'.number_format($row['rate'] ?? 0, 2).'</td>';
                                                            echo '<td data-column="amount" class="text-right">'.number_format($row['amount'] ?? 0, 2).'</td>';
                                                            echo '<td data-column="quantity" class="text-right">'.number_format($row['quantity'] ?? 0, 2).'</td>';
                                                            echo '<td data-column="branch-name">'.htmlspecialchars($row['branch_name'] ?? '').'</td>';
                                                            echo '<td data-column="sales-person">'.htmlspecialchars($row['sales_person'] ?? '').'</td>';
                                                            echo '<td data-column="pure-wt" class="text-right">'.number_format($row['pure_wt'] ?? 0, 6).'</td>';
                                                            echo '<td data-column="current-gold-rate" class="text-right">'.number_format($row['current_gold_rate'] ?? 0, 2).'</td>';
                                                            echo '<td data-column="source">'.htmlspecialchars($row['source'] ?? '').'</td>';
                                                            echo '<td data-column="against-invoice-no">'.htmlspecialchars($row['against_invoice_no'] ?? '').'</td>';
                                                            echo '<td data-column="stock-in" class="text-center" style="white-space: nowrap;">';
                                                            if ($is_ojb) {
                                                                $stock_in_href = 'old-jewellery-scrap-stock-in.php?id='.(int)$row_id.'&item_id='.(int)$item_id;
                                                                echo '<a href="'.htmlspecialchars($stock_in_href).'" class="btn btn-sm btn-primary stock-in-btn" style="background:#11294b;border:none;" title="Open scrap invoice for stock in"><i class="feather icon-package"></i> Stock In</a>';
                                                            } elseif ($is_exchange) {
                                                                echo '<a href="sale-invoice.php?id='.(int)$row_id.'" class="btn btn-sm btn-primary" style="background:#11294b;border:none;" title="Open sale invoice"><i class="feather icon-edit"></i> Edit</a>';
                                                            } else {
                                                                echo '<a href="old-jewelry-scrap-invoice.php?id='.(int)$row_id.'" class="btn btn-sm btn-primary" style="background:#11294b;border:none;" title="Edit Invoice"><i class="feather icon-edit"></i> Edit</a>';
                                                            }
                                                            echo '</td>';
                                                            echo '<td data-column="print" class="text-center"><button type="button" class="btn btn-sm btn-outline-secondary" title="Print this row" onclick="printOldJewelleryRow(this)"><i class="feather icon-printer"></i></button></td>';
                                                            echo '</tr>';
                                                            $sr_no++;
                                                        }
                                                    } else {
                                                        echo '<tr><td colspan="26" class="empty-state">No Rows To Show</td></tr>';
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                            </div>
                                            <!-- end mainTableWrapper -->

                                            <!-- Summary tab table: Product Code, Purity, Gross Wt, Net Wt, Less Wt, Stone Wt, Pure Wt, Amount -->
                                            <div id="summaryTableWrapper" class="table-wrapper" style="<?= ($active_tab == 'summary') ? '' : 'display:none' ?>">
                                                <table class="table old-jewellery-table" id="summaryTable">
                                                    <thead>
                                                        <tr>
                                                            <th>Product Code</th>
                                                            <th class="text-right">Purity</th>
                                                            <th class="text-right">Gross Wt</th>
                                                            <th class="text-right">Net Wt</th>
                                                            <th class="text-right">Less Wt</th>
                                                            <th class="text-right">Stone Wt</th>
                                                            <th class="text-right">Pure Wt</th>
                                                            <th class="text-right">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        if ($active_tab == 'summary' && !empty($summary_data)) {
                                                            foreach ($summary_data as $s) {
                                                                $pname = trim($s['product_name'] ?? '');
                                                                $metal = trim($s['metal'] ?? '');
                                                                $product_code = $metal !== '' ? ($pname !== '' ? $pname . ' - ' . $metal : $metal) : $pname;
                                                                $purity_val = isset($s['purity']) && $s['purity'] !== '' && $s['purity'] !== null ? (float)$s['purity'] : null;
                                                                $purity_display = ($purity_val !== null && (float)$purity_val != 0) ? number_format($purity_val, 2) : '';
                                                                echo '<tr>';
                                                                echo '<td>'.htmlspecialchars($product_code).'</td>';
                                                                echo '<td class="text-right">'.$purity_display.'</td>';
                                                                echo '<td class="text-right">'.number_format($s['gross_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['net_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['less_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['stone_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['pure_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['amount'] ?? 0, 2).'</td>';
                                                                echo '</tr>';
                                                            }
                                                        } elseif ($active_tab == 'summary') {
                                                            echo '<tr><td colspan="8" class="empty-state">No summary data.</td></tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                    <?php if ($active_tab == 'summary' && !empty($summary_data)) { ?>
                                                    <tfoot>
                                                        <tr class="summary-total-row">
                                                            <th>Total</th>
                                                            <th class="text-right">&nbsp;</th>
                                                            <th class="text-right"><?= number_format($summary_totals['total_gross_wt'], 3) ?></th>
                                                            <th class="text-right"><?= number_format($summary_totals['total_net_wt'], 3) ?></th>
                                                            <th class="text-right"><?= number_format($summary_totals['total_less_wt'], 3) ?></th>
                                                            <th class="text-right"><?= number_format($summary_totals['total_stone_wt'], 3) ?></th>
                                                            <th class="text-right"><?= number_format($summary_totals['total_pure_wt'], 3) ?></th>
                                                            <th class="text-right"><?= number_format($summary_totals['total_amount'], 2) ?></th>
                                                        </tr>
                                                    </tfoot>
                                                    <?php } ?>
                                                </table>
                                            </div>
                                            <!-- end summaryTableWrapper -->

                                            <!-- Refine / Received: blank -->
                                            <div id="refineReceivedWrapper" style="<?= ($active_tab == 'refine' || $active_tab == 'received') ? '' : 'display:none' ?>">
                                                <?php if ($active_tab == 'refine') { ?>
                                                <p class="oj-refine-dnd-hint" style="margin:0 0 8px 2px;font-size:11px;color:#64748b;line-height:1.4;">Drag the move icon (<i class="feather icon-move" style="width:12px;height:12px;vertical-align:middle;"></i>) on a column header to reorder. Saved in this browser.</p>
                                                <div class="table-wrapper" style="overflow:auto; max-height: calc(100vh - 280px);">
                                                    <table class="table old-jewellery-table" id="refineTable">
                                                        <thead>
                                                            <tr>
                                                                <th data-column="rfid">RFID Code</th>
                                                                <th data-column="job-order">Job Order No</th>
                                                                <th data-column="against-ref">Against Ref. No.</th>
                                                                <th data-column="customer">Customer Name</th>
                                                                <th data-column="short-desc">Short Description</th>
                                                                <th data-column="purity" class="text-right">Purity</th>
                                                                <th data-column="gross-wt" class="text-right">Gross Wt</th>
                                                                <th data-column="final-wt" class="text-right">Final Wt.</th>
                                                                <th data-column="assign">Assign To</th>
                                                                <th data-column="due-date">Due Date</th>
                                                                <th data-column="order-dt">Order Dt.</th>
                                                                <th data-column="tag-no">Tag No.</th>
                                                                <th data-column="status">Status</th>
                                                                <th data-column="priority">Priority</th>
                                                                <th data-column="invoice" class="text-center">Invoice</th>
                                                                <th data-column="print" class="text-center">Print</th>
                                                                <th data-column="track" class="text-center">TrackOrder</th>
                                                                <th data-column="branch">Branch Name</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            if (!empty($refine_data)) {
                                                                foreach ($refine_data as $rr) {
                                                                    $jid = (int) ($rr['jwo_id'] ?? 0);
                                                                    $due_s = !empty($rr['due_date']) ? date('d-m-Y', strtotime($rr['due_date'])) : '';
                                                                    $ord_s = !empty($rr['order_date']) ? date('d-m-Y', strtotime($rr['order_date'])) : '';
                                                                    $st = strtolower(trim((string) ($rr['jwo_status'] ?? '')));
                                                                    $status_label = $st !== '' ? ucfirst($st) : 'Draft';
                                                                    $st_bg = '#64748b';
                                                                    if ($st === 'completed') {
                                                                        $st_bg = '#28a745';
                                                                    } elseif ($st === 'processing') {
                                                                        $st_bg = '#38bdf8';
                                                                    } elseif ($st === 'draft' || $st === '') {
                                                                        $status_label = 'Draft';
                                                                        $st_bg = '#94a3b8';
                                                                    }
                                                                    $prio = trim((string) ($rr['jwo_priority'] ?? 'Medium'));
                                                                    if ($prio === '') {
                                                                        $prio = 'Medium';
                                                                    }
                                                                    echo '<tr>';
                                                                    echo '<td data-column="rfid">'.htmlspecialchars((string) ($rr['rfid_code'] ?? '')).'</td>';
                                                                    echo '<td data-column="job-order">';
                                                                    if ($jid > 0) {
                                                                        echo '<a href="jobwork-order.php?id='.$jid.'">'.htmlspecialchars((string) ($rr['jobwork_no'] ?? '')).'</a>';
                                                                    } else {
                                                                        echo htmlspecialchars((string) ($rr['jobwork_no'] ?? ''));
                                                                    }
                                                                    echo '</td>';
                                                                    echo '<td data-column="against-ref">'.htmlspecialchars((string) ($rr['against_ref'] ?? '')).'</td>';
                                                                    echo '<td data-column="customer">'.htmlspecialchars((string) ($rr['customer_name'] ?? '')).'</td>';
                                                                    echo '<td data-column="short-desc">'.htmlspecialchars((string) ($rr['product_name'] ?? '')).'</td>';
                                                                    echo '<td data-column="purity" class="text-right">'.number_format((float) ($rr['purity'] ?? 0), 2).'</td>';
                                                                    echo '<td data-column="gross-wt" class="text-right">'.number_format((float) ($rr['gross_weight'] ?? 0), 3).'</td>';
                                                                    echo '<td data-column="final-wt" class="text-right">'.number_format((float) ($rr['final_weight'] ?? 0), 3).'</td>';
                                                                    echo '<td data-column="assign">'.htmlspecialchars((string) ($rr['assign_name'] ?? '')).'</td>';
                                                                    echo '<td data-column="due-date">'.htmlspecialchars($due_s).'</td>';
                                                                    echo '<td data-column="order-dt">'.htmlspecialchars($ord_s).'</td>';
                                                                    echo '<td data-column="tag-no">'.htmlspecialchars((string) ($rr['tag_no'] ?? '')).'</td>';
                                                                    echo '<td data-column="status"><span style="display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:600;background:'.$st_bg.';color:#fff;">'.htmlspecialchars($status_label).'</span></td>';
                                                                    echo '<td data-column="priority"><span style="display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:600;background:#38bdf8;color:#fff;">'.htmlspecialchars($prio).'</span></td>';
                                                                    echo '<td data-column="invoice" class="text-center">';
                                                                    if ($jid > 0) {
                                                                        echo '<a href="jobwork-invoice.php?Jobwork_order_id='.$jid.$oj_jobwork_invoice_return_qs.'" class="btn btn-sm" style="background:#5b4bce;border:none;color:#fff;" title="Invoice"><i class="feather icon-file-text" style="vertical-align:middle;"></i> Invoice</a>';
                                                                    } else {
                                                                        echo '—';
                                                                    }
                                                                    echo '</td>';
                                                                    echo '<td data-column="print" class="text-center">';
                                                                    if ($jid > 0) {
                                                                        echo '<a href="manufacturing-jobwork-slip-print.php?id='.$jid.'" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Print"><i class="feather icon-printer"></i></a>';
                                                                    } else {
                                                                        echo '—';
                                                                    }
                                                                    echo '</td>';
                                                                    echo '<td data-column="track" class="text-center">';
                                                                    if ($jid > 0) {
                                                                        echo '<button type="button" class="btn btn-sm btn-outline-secondary mp-order-tracking-btn" data-jwo-id="'.$jid.'" title="Track order"><i class="feather icon-file-text"></i></button>';
                                                                    } else {
                                                                        echo '—';
                                                                    }
                                                                    echo '</td>';
                                                                    echo '<td data-column="branch">'.htmlspecialchars((string) ($rr['branch_name'] ?? '')).'</td>';
                                                                    echo '</tr>';
                                                                }
                                                            } else {
                                                                echo '<tr><td colspan="18" class="empty-state">No refinery job work lines in this period. Create one from Old Jewellery-Scrap, save the job work order, then lines appear here.</td></tr>';
                                                            }
                                                            ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <?php } elseif ($active_tab == 'received') { ?>
                                                <p class="oj-refine-dnd-hint" style="margin:0 0 8px 2px;font-size:11px;color:#64748b;line-height:1.4;">Lines appear here after you save the jobwork invoice from Refine. Use <strong>Stock In</strong> to add each line to Old Jewellery stock with a <strong>unique barcode</strong>; the <strong>Stock In</strong> column then shows <strong>Completed</strong>, and the line is listed under <strong>Stocked</strong>. Drag the <strong>move icon</strong> on column headers to reorder columns (same as Refine).</p>
                                                <div class="table-wrapper" style="overflow:auto; max-height: calc(100vh - 280px);">
                                                    <table class="table old-jewellery-table" id="receivedRefineTable">
                                                        <thead>
                                                            <tr>
                                                                <th data-column="rfid">RFID Code</th>
                                                                <th data-column="job-order">Job Order No</th>
                                                                <th data-column="against-ref">Against Ref. No.</th>
                                                                <th data-column="customer">Customer Name</th>
                                                                <th data-column="short-desc">Short Description</th>
                                                                <th data-column="purity" class="text-right">Purity</th>
                                                                <th data-column="gross-wt" class="text-right">Gross Wt</th>
                                                                <th data-column="final-wt" class="text-right">Final Wt.</th>
                                                                <th data-column="assign">Assign To</th>
                                                                <th data-column="due-date">Due Date</th>
                                                                <th data-column="order-dt">Order Dt.</th>
                                                                <th data-column="tag-no">Tag No.</th>
                                                                <th data-column="status">Status</th>
                                                                <th data-column="priority">Priority</th>
                                                                <th data-column="jw-inv">Jobwork Inv.</th>
                                                                <th data-column="stock-in" class="text-center">Stock In</th>
                                                                <th data-column="print" class="text-center">Print</th>
                                                                <th data-column="track" class="text-center">TrackOrder</th>
                                                                <th data-column="branch">Branch Name</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            if (!empty($received_data)) {
                                                                foreach ($received_data as $rr) {
                                                                    $jid = (int) ($rr['jwo_id'] ?? 0);
                                                                    $line_id = (int) ($rr['line_id'] ?? 0);
                                                                    $due_s = !empty($rr['due_date']) ? date('d-m-Y', strtotime($rr['due_date'])) : '';
                                                                    $ord_s = !empty($rr['order_date']) ? date('d-m-Y', strtotime($rr['order_date'])) : '';
                                                                    $st = strtolower(trim((string) ($rr['jwo_status'] ?? '')));
                                                                    $status_label = $st !== '' ? ucfirst($st) : 'Draft';
                                                                    $st_bg = '#64748b';
                                                                    if ($st === 'completed') {
                                                                        $st_bg = '#28a745';
                                                                    } elseif ($st === 'processing') {
                                                                        $st_bg = '#38bdf8';
                                                                    } elseif ($st === 'draft' || $st === '') {
                                                                        $status_label = 'Draft';
                                                                        $st_bg = '#94a3b8';
                                                                    }
                                                                    $prio = trim((string) ($rr['jwo_priority'] ?? 'Medium'));
                                                                    if ($prio === '') {
                                                                        $prio = 'Medium';
                                                                    }
                                                                    echo '<tr>';
                                                                    echo '<td data-column="rfid">'.htmlspecialchars((string) ($rr['rfid_code'] ?? '')).'</td>';
                                                                    echo '<td data-column="job-order">';
                                                                    if ($jid > 0) {
                                                                        echo '<a href="jobwork-order.php?id='.$jid.'">'.htmlspecialchars((string) ($rr['jobwork_no'] ?? '')).'</a>';
                                                                    } else {
                                                                        echo htmlspecialchars((string) ($rr['jobwork_no'] ?? ''));
                                                                    }
                                                                    echo '</td>';
                                                                    echo '<td data-column="against-ref">'.htmlspecialchars((string) ($rr['against_ref'] ?? '')).'</td>';
                                                                    echo '<td data-column="customer">'.htmlspecialchars((string) ($rr['customer_name'] ?? '')).'</td>';
                                                                    echo '<td data-column="short-desc">'.htmlspecialchars((string) ($rr['product_name'] ?? '')).'</td>';
                                                                    echo '<td data-column="purity" class="text-right">'.number_format((float) ($rr['purity'] ?? 0), 2).'</td>';
                                                                    echo '<td data-column="gross-wt" class="text-right">'.number_format((float) ($rr['gross_weight'] ?? 0), 3).'</td>';
                                                                    echo '<td data-column="final-wt" class="text-right">'.number_format((float) ($rr['final_weight'] ?? 0), 3).'</td>';
                                                                    echo '<td data-column="assign">'.htmlspecialchars((string) ($rr['assign_name'] ?? '')).'</td>';
                                                                    echo '<td data-column="due-date">'.htmlspecialchars($due_s).'</td>';
                                                                    echo '<td data-column="order-dt">'.htmlspecialchars($ord_s).'</td>';
                                                                    echo '<td data-column="tag-no">'.htmlspecialchars((string) ($rr['tag_no'] ?? '')).'</td>';
                                                                    echo '<td data-column="status"><span style="display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:600;background:'.$st_bg.';color:#fff;">'.htmlspecialchars($status_label).'</span></td>';
                                                                    echo '<td data-column="priority"><span style="display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:600;background:#38bdf8;color:#fff;">'.htmlspecialchars($prio).'</span></td>';
                                                                    echo '<td data-column="jw-inv">'.htmlspecialchars((string) ($rr['jwi_invoice_no'] ?? '')).'</td>';
                                                                    echo '<td data-column="stock-in" class="text-center">';
                                                                    $oj_refine_done = $stock_table_exists && (int) ($rr['oj_refine_stocked'] ?? 0) === 1;
                                                                    if ($oj_refine_done) {
                                                                        echo '<span style="display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:600;background:#28a745;color:#fff;">Completed</span>';
                                                                    } elseif ($stock_table_exists && $line_id > 0 && $jid > 0) {
                                                                        echo '<button type="button" class="btn btn-sm btn-primary oj-refinery-receive-btn" data-joi-id="'.$line_id.'" data-jwo-id="'.$jid.'" data-tag-no="'.htmlspecialchars((string) ($rr['tag_no'] ?? ''), ENT_QUOTES, 'UTF-8').'" style="background:#11294b;border:none;" title="Receive into Old Jewellery stock"><i class="feather icon-package"></i> Stock In</button>';
                                                                    } elseif (!$stock_table_exists) {
                                                                        echo '<span class="text-muted" style="font-size:11px;">Stock table missing</span>';
                                                                    } else {
                                                                        echo '—';
                                                                    }
                                                                    echo '</td>';
                                                                    echo '<td data-column="print" class="text-center">';
                                                                    if ($jid > 0) {
                                                                        echo '<a href="manufacturing-jobwork-slip-print.php?id='.$jid.'" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Print"><i class="feather icon-printer"></i></a>';
                                                                    } else {
                                                                        echo '—';
                                                                    }
                                                                    echo '</td>';
                                                                    echo '<td data-column="track" class="text-center">';
                                                                    if ($jid > 0) {
                                                                        echo '<button type="button" class="btn btn-sm btn-outline-secondary mp-order-tracking-btn" data-jwo-id="'.$jid.'" title="Track order"><i class="feather icon-file-text"></i></button>';
                                                                    } else {
                                                                        echo '—';
                                                                    }
                                                                    echo '</td>';
                                                                    echo '<td data-column="branch">'.htmlspecialchars((string) ($rr['branch_name'] ?? '')).'</td>';
                                                                    echo '</tr>';
                                                                }
                                                            } else {
                                                                echo '<tr><td colspan="19" class="empty-state">No received refinery lines in this period (save a jobwork invoice from Refine first).</td></tr>';
                                                            }
                                                            ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <?php } else { ?>
                                                <div class="empty-state" style="padding: 40px; text-align: center; color: #64748b;">No data to display.</div>
                                                <?php } ?>
                                            </div>

                                            <!-- Stocked tab table: records from tbl_old_jewelry_stock -->
                                            <?php if ($stock_table_exists) { ?>
                                            <div id="stockedTableWrapper" class="table-wrapper" style="<?= ($active_tab == 'stocked') ? '' : 'display:none' ?>">
                                                <table class="table old-jewellery-table" id="stockedTable">
                                                    <thead>
                                                        <tr>
                                                            <th>Barcode</th>
                                                            <th>Invoice No.</th>
                                                            <th>Voucher</th>
                                                            <th>Metal</th>
                                                            <th>Product</th>
                                                            <th>Location</th>
                                                            <th class="text-right">Final Wt.</th>
                                                            <th class="text-right">Gross Wt</th>
                                                            <th class="text-right">Purity</th>
                                                            <th>Branch Name</th>
                                                            <th class="text-right">Less Wt</th>
                                                            <th class="text-right">Net Wt</th>
                                                            <th class="text-right">Amount</th>
                                                            <th>Category</th>
                                                            <th>Against Invoice No</th>
                                                            <th>Against Voucher</th>
                                                            <th class="text-center">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        if ($active_tab == 'stocked' && !empty($stocked_data)) {
                                                            foreach ($stocked_data as $s) {
                                                                $stock_id = (int)($s['id'] ?? 0);
                                                                $src_item_id = (int)($s['source_item_id'] ?? 0);
                                                                echo '<tr data-stock-id="'.$stock_id.'" data-source-item-id="'.$src_item_id.'">';
                                                                echo '<td>'.htmlspecialchars($s['barcode'] ?? '').'</td>';
                                                                echo '<td>'.htmlspecialchars($s['invoice_no'] ?? '').'</td>';
                                                                echo '<td>'.htmlspecialchars($s['voucher_type'] ?? '').'</td>';
                                                                echo '<td>'.htmlspecialchars($s['metal'] ?? '').'</td>';
                                                                echo '<td>'.htmlspecialchars($s['product'] ?? '').'</td>';
                                                                echo '<td>'.htmlspecialchars($s['location'] ?? '').'</td>';
                                                                echo '<td class="text-right">'.number_format($s['final_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['gross_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['purity'] ?? 0, 2).'</td>';
                                                                echo '<td>'.htmlspecialchars($s['branch_name'] ?? '').'</td>';
                                                                echo '<td class="text-right">'.number_format($s['less_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['net_wt'] ?? 0, 3).'</td>';
                                                                echo '<td class="text-right">'.number_format($s['amount'] ?? 0, 2).'</td>';
                                                                echo '<td>'.htmlspecialchars($s['category'] ?? '').'</td>';
                                                                echo '<td>'.htmlspecialchars($s['against_invoice_no'] ?? '').'</td>';
                                                                echo '<td>'.htmlspecialchars($s['against_voucher'] ?? '').'</td>';
                                                                echo '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-secondary" title="Revert Stock In" onclick="revertStockIn('.$stock_id.','.$src_item_id.')"><i class="feather icon-corner-up-left"></i> Revert Stock In</button></td>';
                                                                echo '</tr>';
                                                            }
                                                        } elseif ($active_tab == 'stocked') {
                                                            echo '<tr><td colspan="17" class="empty-state">No stocked records. Use Stock In from Old Jewellery-Scrap tab to add.</td></tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php } ?>
                                        </div>

                                        <!-- Table Footer -->
                                        <div class="table-footer" id="tableFooter">
                                            <div class="table-footer-info">
                                                <?php if ($active_tab == 'stocked' && $stock_table_exists) { ?>
                                                Showing <?= $stocked_total_records > 0 ? ($offset + 1) : 0 ?> to <?= min($offset + $per_page, $stocked_total_records) ?> of <?= $stocked_total_records ?> entries
                                                <?php } elseif ($active_tab == 'summary') { ?>
                                                Showing <?= $summary_total_records > 0 ? ($offset + 1) : 0 ?> to <?= min($offset + $per_page, $summary_total_records) ?> of <?= $summary_total_records ?> entries
                                                <?php } elseif ($active_tab == 'scrap') { ?>
                                                Showing <?= $total_records > 0 ? ($offset + 1) : 0 ?> to <?= min($offset + $per_page, $total_records) ?> of <?= $total_records ?> entries
                                                <?php } elseif ($active_tab == 'refine') { ?>
                                                Showing <?= $refine_total_records > 0 ? ($offset + 1) : 0 ?> to <?= min($offset + $per_page, $refine_total_records) ?> of <?= $refine_total_records ?> entries
                                                <?php } elseif ($active_tab == 'received') { ?>
                                                Showing <?= $received_total_records > 0 ? ($offset + 1) : 0 ?> to <?= min($offset + $per_page, $received_total_records) ?> of <?= $received_total_records ?> entries
                                                <?php } else { ?>
                                                Showing 0 to 0 of 0 entries
                                                <?php } ?>
                                            </div>
                                            
                                            <div class="table-footer-totals">
                                                <?php if ($active_tab == 'stocked' && $stock_table_exists) { ?>
                                                <div class="total-value"><?= number_format($stocked_totals['total_final_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($stocked_totals['total_gross_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($stocked_totals['total_less_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($stocked_totals['total_net_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($stocked_totals['total_amount'], 2) ?></div>
                                                <?php } elseif ($active_tab == 'summary') { ?>
                                                <div class="total-value"><?= number_format($summary_totals['total_gross_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($summary_totals['total_net_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($summary_totals['total_less_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($summary_totals['total_stone_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($summary_totals['total_pure_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($summary_totals['total_amount'], 2) ?></div>
                                                <?php } elseif ($active_tab == 'scrap') { ?>
                                                <div class="total-value"><?= number_format($totals['total_gross_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($totals['total_less_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($totals['total_stone_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($totals['total_net_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($totals['total_final_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($totals['total_pure_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($totals['total_amount'], 2) ?></div>
                                                <div class="total-value"><?= number_format($totals['total_quantity'], 2) ?></div>
                                                <div class="total-value"><?= number_format($totals['total_amount_paid'], 2) ?></div>
                                                <?php } elseif ($active_tab == 'refine') { ?>
                                                <div class="total-value"><?= number_format($refine_totals['sum_gross_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($refine_totals['sum_final_wt'], 3) ?></div>
                                                <?php } elseif ($active_tab == 'received') { ?>
                                                <div class="total-value"><?= number_format($received_totals['sum_gross_wt'], 3) ?></div>
                                                <div class="total-value"><?= number_format($received_totals['sum_final_wt'], 3) ?></div>
                                                <?php } ?>
                                            </div>

                                            <div class="pagination-controls">
                                                <?php
                                                if ($active_tab == 'stocked' && $stock_table_exists) {
                                                    $disp_total = $stocked_total_pages;
                                                } elseif ($active_tab == 'summary') {
                                                    $disp_total = $summary_total_pages;
                                                } elseif ($active_tab == 'refine') {
                                                    $disp_total = max(1, $refine_total_pages);
                                                } elseif ($active_tab == 'received') {
                                                    $disp_total = max(1, $received_total_pages);
                                                } else {
                                                    $disp_total = $total_pages;
                                                }
                                                $disp_page = $page;
                                                if ($active_tab == 'stocked' && $stock_table_exists) {
                                                    $disp_records = $stocked_total_records;
                                                } elseif ($active_tab == 'summary') {
                                                    $disp_records = $summary_total_records;
                                                } elseif ($active_tab == 'refine') {
                                                    $disp_records = $refine_total_records;
                                                } elseif ($active_tab == 'received') {
                                                    $disp_records = $received_total_records;
                                                } else {
                                                    $disp_records = $total_records;
                                                }
                                                ?>
                                                <select class="show-all-dropdown" id="perPageSelect" onchange="changePerPage()">
                                                    <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                                                    <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                                                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                                                    <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                                                </select>
                                                <button type="button" class="page-btn" onclick="goToPage(1)" <?= $disp_page == 1 ? 'disabled' : '' ?>>
                                                    <i class="feather icon-chevrons-left"></i>
                                                </button>
                                                <button type="button" class="page-btn" onclick="goToPage(<?= max(1, $disp_page - 1) ?>)" <?= $disp_page == 1 ? 'disabled' : '' ?>>
                                                    <i class="feather icon-chevron-left"></i>
                                                </button>
                                                <?php
                                                $start_page = max(1, $disp_page - 2);
                                                $end_page = min($disp_total, $disp_page + 2);
                                                for ($i = $start_page; $i <= $end_page; $i++) {
                                                    $active = ($i == $disp_page) ? 'active' : '';
                                                    echo '<button class="page-btn '.$active.'" onclick="goToPage('.$i.')">'.$i.'</button>';
                                                }
                                                ?>
                                                <button type="button" class="page-btn" onclick="goToPage(<?= min($disp_total, $disp_page + 1) ?>)" <?= $disp_page >= $disp_total ? 'disabled' : '' ?>>
                                                    <i class="feather icon-chevron-right"></i>
                                                </button>
                                                <button type="button" class="page-btn" onclick="goToPage(<?= $disp_total ?>)" <?= $disp_page >= $disp_total ? 'disabled' : '' ?>>
                                                    <i class="feather icon-chevrons-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ Layout content ] End -->
        </div>
        <!-- [ Layout container ] End -->
    </div>
</div>
<!-- [ Layout wrapper ] End -->

<!-- Advance Filter Modal -->
<div id="filterModal" class="filter-modal">
    <div class="adv-filter-shell">
        <div class="adv-filter-head">
            <h5 class="adv-filter-title">Advance Filter</h5>
            <button type="button" class="adv-filter-close" onclick="closeFilterModal()" aria-label="Close">&times;</button>
        </div>
        <div class="adv-filter-body">
            <div class="adv-filter-grid">
                <label class="adv-filter-label">Order Date</label>
                <div class="adv-date-row-inner">
                    <input type="date" class="adv-input" id="filterFromDate" value="<?= htmlspecialchars($from_date) ?>">
                    <span class="adv-date-emdash">–</span>
                    <input type="date" class="adv-input" id="filterToDate" value="<?= htmlspecialchars($to_date) ?>">
                    <button type="button" class="adv-icon-btn" onclick="document.getElementById('filterFromDate').focus();" title="Calendar"><i class="feather icon-calendar"></i></button>
                    <button type="button" class="adv-icon-btn" onclick="resetFilterOrderDates()" title="Reset order dates"><i class="feather icon-refresh-cw"></i></button>
                </div>

                <label class="adv-filter-label">Due Date</label>
                <div class="adv-date-row-inner">
                    <input type="date" class="adv-input" id="filterDueFromDate" value="<?= htmlspecialchars($due_from_date) ?>">
                    <span class="adv-date-emdash">–</span>
                    <input type="date" class="adv-input" id="filterDueToDate" value="<?= htmlspecialchars($due_to_date) ?>">
                    <button type="button" class="adv-icon-btn" onclick="document.getElementById('filterDueFromDate').focus();" title="Calendar"><i class="feather icon-calendar"></i></button>
                    <button type="button" class="adv-icon-btn" onclick="resetFilterDueDates()" title="Clear due dates"><i class="feather icon-refresh-cw"></i></button>
                </div>

                <label class="adv-filter-label">Branch</label>
                <div class="oj-msdd" id="filterBranchMsdd" data-placeholder="Select Branch">
                    <button type="button" class="oj-msdd-toggle" aria-expanded="false" aria-haspopup="listbox">
                        <span class="oj-msdd-toggle-text">Select Branch</span>
                        <i class="feather icon-chevron-down oj-msdd-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="oj-msdd-panel" role="listbox" hidden>
                        <div class="oj-msdd-search-wrap">
                            <input type="text" class="oj-msdd-search" placeholder="Search" autocomplete="off" aria-label="Search branches">
                        </div>
                        <label class="oj-msdd-select-all">
                            <input type="checkbox" aria-label="Select all branches">
                            <span>Select all</span>
                        </label>
                        <div class="oj-msdd-list" id="filterBranchList">
                            <?php foreach ($branches as $branch):
                                $bid = (int) ($branch['id'] ?? 0);
                                $sel = in_array($bid, $branch_ids, true);
                            ?>
                            <label class="oj-msdd-item" data-label="<?= htmlspecialchars(strtolower($branch['name'] ?? '')) ?>">
                                <input type="checkbox" class="oj-msdd-cb" value="<?= $bid ?>" <?= $sel ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($branch['name'] ?? '') ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <label class="adv-filter-label">Customer</label>
                <div class="oj-msdd" id="filterCustomerMsdd" data-placeholder="Select Customer">
                    <button type="button" class="oj-msdd-toggle" aria-expanded="false" aria-haspopup="listbox">
                        <span class="oj-msdd-toggle-text">Select Customer</span>
                        <i class="feather icon-chevron-down oj-msdd-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="oj-msdd-panel" role="listbox" hidden>
                        <div class="oj-msdd-search-wrap">
                            <input type="text" class="oj-msdd-search" placeholder="Search" autocomplete="off" aria-label="Search customers">
                        </div>
                        <label class="oj-msdd-select-all">
                            <input type="checkbox" aria-label="Select all customers">
                            <span>Select all</span>
                        </label>
                        <div class="oj-msdd-list" id="filterCustomerList">
                            <?php foreach ($customers as $crow):
                                $cname = trim((string) ($crow['customer_name'] ?? ''));
                                if ($cname === '') {
                                    continue;
                                }
                                $selc = in_array($cname, $customer_name_parts, true);
                            ?>
                            <label class="oj-msdd-item" data-label="<?= htmlspecialchars(strtolower($cname)) ?>">
                                <input type="checkbox" class="oj-msdd-cb" value="<?= htmlspecialchars($cname) ?>" <?= $selc ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($cname) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <label class="adv-filter-label">Assign To</label>
                <div class="oj-msdd" id="filterAssignMsdd" data-placeholder="Select Assign To">
                    <button type="button" class="oj-msdd-toggle" aria-expanded="false" aria-haspopup="listbox">
                        <span class="oj-msdd-toggle-text">Select Assign To</span>
                        <i class="feather icon-chevron-down oj-msdd-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="oj-msdd-panel" role="listbox" hidden>
                        <div class="oj-msdd-search-wrap">
                            <input type="text" class="oj-msdd-search" placeholder="Search" autocomplete="off" aria-label="Search assign to">
                        </div>
                        <label class="oj-msdd-select-all">
                            <input type="checkbox" aria-label="Select all assign to">
                            <span>Select all</span>
                        </label>
                        <div class="oj-msdd-list" id="filterAssignList">
                            <?php foreach ($assign_to_options as $arow):
                                $aid = (int) ($arow['id'] ?? 0);
                                $aname = trim((string) ($arow['name'] ?? ''));
                                if ($aid <= 0) {
                                    continue;
                                }
                                $sela = in_array($aid, $assign_to_ids, true);
                            ?>
                            <label class="oj-msdd-item" data-label="<?= htmlspecialchars(strtolower($aname)) ?>">
                                <input type="checkbox" class="oj-msdd-cb" value="<?= $aid ?>" <?= $sela ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($aname) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <label class="adv-filter-label">Status</label>
                <div class="oj-msdd" id="filterStatusMsdd" data-placeholder="Select Status">
                    <button type="button" class="oj-msdd-toggle" aria-expanded="false" aria-haspopup="listbox">
                        <span class="oj-msdd-toggle-text">Select Status</span>
                        <i class="feather icon-chevron-down oj-msdd-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="oj-msdd-panel" role="listbox" hidden>
                        <div class="oj-msdd-search-wrap">
                            <input type="text" class="oj-msdd-search" placeholder="Search" autocomplete="off" aria-label="Search status">
                        </div>
                        <label class="oj-msdd-select-all">
                            <input type="checkbox" aria-label="Select all status">
                            <span>Select all</span>
                        </label>
                        <div class="oj-msdd-list" id="filterStatusList">
                            <?php foreach ($jwo_status_options as $stopt):
                                $sels = in_array($stopt, $jwo_status_vals, true);
                            ?>
                            <label class="oj-msdd-item" data-label="<?= htmlspecialchars(strtolower($stopt)) ?>">
                                <input type="checkbox" class="oj-msdd-cb" value="<?= htmlspecialchars($stopt) ?>" <?= $sels ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($stopt) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <label class="adv-filter-label">Priority</label>
                <div class="oj-msdd" id="filterPriorityMsdd" data-placeholder="Select Priority">
                    <button type="button" class="oj-msdd-toggle" aria-expanded="false" aria-haspopup="listbox">
                        <span class="oj-msdd-toggle-text">Select Priority</span>
                        <i class="feather icon-chevron-down oj-msdd-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="oj-msdd-panel" role="listbox" hidden>
                        <div class="oj-msdd-search-wrap">
                            <input type="text" class="oj-msdd-search" placeholder="Search" autocomplete="off" aria-label="Search priority">
                        </div>
                        <label class="oj-msdd-select-all">
                            <input type="checkbox" aria-label="Select all priority">
                            <span>Select all</span>
                        </label>
                        <div class="oj-msdd-list" id="filterPriorityList">
                            <?php foreach ($jwo_priority_options as $prow):
                                $selp = in_array($prow, $jwo_priority_vals, true);
                            ?>
                            <label class="oj-msdd-item" data-label="<?= htmlspecialchars(strtolower($prow)) ?>">
                                <input type="checkbox" class="oj-msdd-cb" value="<?= htmlspecialchars($prow) ?>" <?= $selp ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($prow) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="adv-filter-cols-2">
                    <div class="adv-filter-col">
                        <label class="adv-filter-label adv-filter-label-stack" for="advGroupName">Group Name</label>
                        <input type="text" class="adv-input adv-input-full" id="advGroupName" value="<?= htmlspecialchars($adv_group_name) ?>" placeholder="" autocomplete="off">

                        <label class="adv-filter-label adv-filter-label-stack" for="advJobworkNo">Job Order No</label>
                        <input type="text" class="adv-input adv-input-full" id="advJobworkNo" value="<?= htmlspecialchars($adv_jobwork_no) ?>" placeholder="" autocomplete="off">

                        <label class="adv-filter-label adv-filter-label-stack" for="advTagNo">Tag No.</label>
                        <input type="text" class="adv-input adv-input-full" id="advTagNo" value="<?= htmlspecialchars($adv_tag_no) ?>" placeholder="" autocomplete="off">

                        <label class="adv-filter-label adv-filter-label-stack" for="advGrossWt">Gross Wt</label>
                        <input type="text" class="adv-input adv-input-full" id="advGrossWt" value="<?= htmlspecialchars($adv_gross_wt) ?>" placeholder="" autocomplete="off">
                    </div>
                    <div class="adv-filter-col">
                        <label class="adv-filter-label adv-filter-label-stack" for="advRfid">RFID Code</label>
                        <input type="text" class="adv-input adv-input-full" id="advRfid" value="<?= htmlspecialchars($adv_rfid) ?>" placeholder="" autocomplete="off">

                        <label class="adv-filter-label adv-filter-label-stack" for="advAgainstRef">Against Ref. No.</label>
                        <input type="text" class="adv-input adv-input-full" id="advAgainstRef" value="<?= htmlspecialchars($adv_against_ref) ?>" placeholder="" autocomplete="off">

                        <label class="adv-filter-label adv-filter-label-stack" for="advShortDesc">Short Desc.</label>
                        <input type="text" class="adv-input adv-input-full" id="advShortDesc" value="<?= htmlspecialchars($adv_short_desc) ?>" placeholder="" autocomplete="off">
                    </div>
                </div>

                <div class="adv-filter-footer-btns">
                    <button type="button" class="btn-adv-apply" onclick="applyFiltersFromModal()">Apply Filter</button>
                    <button type="button" class="btn-adv-clear" onclick="clearFiltersFromModal()">Clear Filter</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Refinery Received → Old Jewellery stock (unique barcode) -->
<div id="refineryReceiveModal" class="filter-modal oj-refinery-receive-overlay" role="dialog" aria-modal="true" aria-labelledby="refineryReceiveModalTitle">
    <div class="filter-modal-content oj-refinery-receive-panel">
        <div class="filter-modal-header oj-refinery-receive-header">
            <h5 id="refineryReceiveModalTitle">Receive to Old Jewellery stock</h5>
            <button type="button" class="filter-modal-close" onclick="closeRefineryReceiveModal()" aria-label="Close">&times;</button>
        </div>
        <div class="filter-modal-body oj-refinery-receive-body">
            <p class="oj-refinery-receive-intro">The barcode defaults to this line&rsquo;s <strong>Tag No.</strong> It must be <strong>unique</strong> in Old Jewellery stock. If that tag is already used, click <strong>Suggest unique</strong> or type another number. After save, <strong>Stock In</strong> shows <strong>Completed</strong> here, and the item appears under <strong>Stocked</strong>.</p>
            <label class="filter-field-label" for="refineryReceiveBarcode">Barcode <span class="oj-refinery-receive-locked-hint">(from tag)</span></label>
            <div class="oj-refinery-barcode-row">
                <input type="text" class="oj-refinery-receive-input" id="refineryReceiveBarcode" name="refinery_receive_barcode" readonly autocomplete="off" maxlength="64" aria-readonly="true" tabindex="-1">
                <button type="button" class="btn btn-outline-secondary btn-sm oj-refinery-suggest-bc-btn" id="refinerySuggestBarcodeBtn" onclick="suggestRefineryReceiveBarcode()">Suggest unique</button>
            </div>
            <div id="refineryReceiveErr" class="text-danger oj-refinery-receive-err" style="display:none;"></div>
            <input type="hidden" id="refineryReceiveBarcodeTagDefault" value="">
            <input type="hidden" id="refineryReceiveJoiId" value="">
            <input type="hidden" id="refineryReceiveJwoId" value="">
            <div class="filter-form-actions-row oj-refinery-receive-actions">
                <button type="button" class="btn btn-filter-apply" id="refineryReceiveSubmitBtn" onclick="submitRefineryReceiveStock()">Save to stock</button>
                <button type="button" class="btn btn-filter-clear" onclick="closeRefineryReceiveModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Stock In — Product Selection style (same field IDs; saves to stock table only) -->
<div id="stockInModal" class="filter-modal stock-in-modal-wrap">
    <div class="stock-in-modal-shell">
        <div class="stock-in-modal-header-light">
            <div class="stock-in-modal-header-row">
                <h5 class="stock-in-modal-title"><i class="feather icon-package"></i> Stock In — Edit &amp; Add to Stock</h5>
                <button type="button" class="stock-in-modal-close-x" onclick="closeStockInModal()" aria-label="Close">&times;</button>
            </div>
            <p class="stock-in-modal-note">Changes here are saved to the stock table only. Original invoice data is not modified.</p>
        </div>
        <div class="stock-in-modal-body">
            <input type="hidden" id="stockInInvoiceId" value="">
            <input type="hidden" id="stockInItemId" value="">
            <input type="hidden" id="stockInSource" value="scrap">
            <input type="hidden" id="stockInOriginalBarcode" value="">
            <input type="hidden" id="stockInProductId" value="">
            <input type="hidden" id="stockInProductCharacteristicId" value="">
            <input type="hidden" id="stockInMetalId" value="">
            <input type="hidden" id="stockInBarcode" value="">

            <div class="sim-tabs" role="tablist" aria-label="Category">
                <button type="button" class="sim-tab sim-tab-active">Gold</button>
                <button type="button" class="sim-tab sim-tab-muted" disabled>Silver</button>
                <button type="button" class="sim-tab sim-tab-muted" disabled>Platinum</button>
                <button type="button" class="sim-tab sim-tab-muted" disabled>Diamond &amp; Stones</button>
                <button type="button" class="sim-tab sim-tab-muted" disabled>Imitation Or Watches</button>
                <button type="button" class="sim-tab sim-tab-muted" disabled>Other Or Services</button>
            </div>

            <div class="sim-toolbar">
                <div class="sim-tf sim-tf-barcode">
                    <label>Barcode</label>
                    <div class="sim-input-with-icon">
                        <input type="text" class="sim-input-underline" id="stockInBarcode" placeholder="Scan or enter" autocomplete="off">
                        <i class="feather icon-maximize-2" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="sim-tf">
                    <label>Code</label>
                    <input type="text" class="sim-input-underline" id="stockInAuxCode" placeholder="" autocomplete="off" tabindex="-1">
                </div>
                <div class="sim-tf">
                    <label>Des. No.</label>
                    <input type="text" class="sim-input-underline" id="stockInDesNo" placeholder="" autocomplete="off" tabindex="-1">
                </div>
                <div class="sim-tf sim-tf-checks">
                    <label class="sim-check"><input type="checkbox" disabled tabindex="-1"> Metal Unfix</label>
                    <label class="sim-check"><input type="checkbox" disabled tabindex="-1"> UnFix</label>
                </div>
                <div class="sim-toolbar-actions">
                    <button type="button" class="btn-sim-toolbar btn-sim-toolbar-gold stock-in-add-product-toolbar" id="stockInToolbarAddProduct" title="Add line">Add Product</button>
                    <button type="button" class="btn-sim-toolbar btn-sim-toolbar-navy" disabled title="Optional"><i class="feather icon-settings"></i> Columns</button>
                </div>
            </div>

            <p class="stock-in-product-selection-hint">Product selection — add multiple lines like sale invoice</p>
            <div class="sim-table-scroll">
                <table class="sim-table">
                    <thead>
                        <tr class="sim-group-row">
                            <th colspan="16" class="sim-group-th">
                                <span class="sim-group-title"><i class="feather icon-layers" style="opacity:.9"></i> Basic Information</span>
                                <button type="button" class="sim-group-add-line" id="stockInAddLineBtn" title="Add row" aria-label="Add row">+</button>
                            </th>
                        </tr>
                        <tr>
                            <th>Product</th>
                            <th>Gross Wt</th>
                            <th>Final Wt</th>
                            <th>Net Wt</th>
                            <th>Less Wt</th>
                            <th>Purity</th>
                            <th>Rate</th>
                            <th>Amount</th>
                            <th>Qty</th>
                            <th>Branch</th>
                            <th>Metal</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Against Inv.</th>
                            <th>Against Voucher</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="stockInLinesBody">
                    </tbody>
                </table>
            </div>

            <div class="stock-in-modal-footer">
                <div class="sim-footer-fields">
                    <div class="sim-footer-field">
                        <label>Group Name</label>
                        <input type="text" class="sim-footer-input" id="stockInGroupName" placeholder="">
                    </div>
                    <div class="sim-footer-field">
                        <label>Comment</label>
                        <input type="text" class="sim-footer-input" id="stockInComment" placeholder="">
                    </div>
                </div>
                <div class="stock-in-modal-actions">
                    <button type="button" class="btn-sim-add" id="stockInSaveBtn" onclick="beginStockInSave()">ADD (Shift + A)</button>
                    <button type="button" class="btn-sim-cancel" onclick="closeStockInModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Before Stock In: next barcode preview (Product Opening) + optional print -->
<div id="stockInBarcodeChoiceModal" class="filter-modal" style="z-index: 10050;">
    <div class="filter-modal-content" style="max-width: 440px;">
        <div class="filter-modal-header">
            <h5>Barcode for stock</h5>
            <button type="button" class="filter-modal-close" onclick="closeStockInBarcodeChoiceModal()" aria-label="Close">&times;</button>
        </div>
        <div class="filter-modal-body" style="padding-top: 0;">
            <p style="margin: 0 0 12px; font-size: 13px; color: #475569;">A new sequential barcode is taken from Product Opening (prefix and digits for this product and metal). The scrap line barcode is not reused.</p>
            <div style="margin-bottom: 16px;">
                <strong>Barcode No</strong>
                <span id="stockInBcNextText" style="font-size: 12px; color: #64748b; display: block; margin-top: 4px;">Loading…</span>
            </div>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                <input type="checkbox" id="stockInBcPrintAfter">
                Print barcode after save
            </label>
        </div>
        <div style="padding: 12px 16px 16px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0;">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeStockInBarcodeChoiceModal()">Cancel</button>
            <button type="button" class="btn btn-sm" style="background: #c5a864; border-color: #c5a864; color: #fff;" onclick="confirmStockInBarcodeChoice()">Add to stock</button>
        </div>
    </div>
</div>

<!-- [ Layout wrapper ] End -->

<?php
if ($active_tab === 'refine') {
    include __DIR__ . '/includes/mp-order-tracking-modal.php';
}
?>
<?php include 'footer-script.php';?>
<?php if ($active_tab === 'refine') { ?>
<script>
(function () {
    var wrap = document.getElementById('refineReceivedWrapper');
    if (!wrap) return;
    wrap.addEventListener('click', function (e) {
        var b = e.target.closest('.mp-order-tracking-btn');
        if (!b || !wrap.contains(b)) return;
        e.preventDefault();
        if (typeof window.mpOrderTrackingOpen === 'function') {
            window.mpOrderTrackingOpen(b.getAttribute('data-jwo-id'));
        }
    });
})();
</script>
<?php } ?>

<style>
.filter-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.filter-modal.active {
    display: flex;
}

/* Refinery → stock modal: full-screen dim backdrop, centered card (avoids clipped / off-center overlay) */
#refineryReceiveModal.oj-refinery-receive-overlay {
    z-index: 10040;
    position: fixed;
    inset: 0;
    width: 100%;
    min-width: 100%;
    min-height: 100vh;
    min-height: 100dvh;
    padding: clamp(16px, 3vmin, 28px);
    box-sizing: border-box;
    background: rgba(15, 23, 42, 0.62);
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
    flex-direction: row;
    overflow: auto;
}
#refineryReceiveModal.oj-refinery-receive-overlay.active {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}
#refineryReceiveModal .oj-refinery-receive-panel {
    position: relative;
    width: 100%;
    max-width: 440px;
    margin: auto;
    flex-shrink: 0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow:
        0 4px 6px -1px rgba(17, 41, 75, 0.06),
        0 22px 48px -12px rgba(17, 41, 75, 0.32);
    border: 1px solid rgba(226, 232, 240, 0.98);
    background: #fff;
}
#refineryReceiveModal .oj-refinery-receive-header {
    padding: 16px 18px;
    border-radius: 0;
    border-bottom: 3px solid #c5a864;
}
#refineryReceiveModal .oj-refinery-receive-header h5 {
    font-size: 1.02rem;
    letter-spacing: 0.01em;
}
#refineryReceiveModal .oj-refinery-receive-body {
    padding: 20px 22px 22px;
    -webkit-user-select: text;
    user-select: text;
}
#refineryReceiveModal .oj-refinery-receive-intro {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 16px;
    line-height: 1.5;
}
#refineryReceiveModal .filter-field-label {
    font-weight: 600;
    color: #334155;
    margin-bottom: 8px;
}
#refineryReceiveModal .oj-refinery-receive-locked-hint {
    font-weight: 500;
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
#refineryReceiveModal .oj-refinery-barcode-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
#refineryReceiveModal .oj-refinery-barcode-row .oj-refinery-receive-input {
    flex: 1 1 200px;
    min-width: 0;
}
#refineryReceiveModal .oj-refinery-suggest-bc-btn {
    flex: 0 0 auto;
    white-space: nowrap;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #1e3a5f;
    border-color: #cbd5e1;
}
#refineryReceiveModal .oj-refinery-suggest-bc-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}
#refineryReceiveModal .oj-refinery-receive-input.oj-refinery-barcode-editable {
    background: #fff !important;
    cursor: text;
    pointer-events: auto;
    -webkit-user-select: text;
    user-select: text;
}
#refineryReceiveModal .oj-refinery-receive-input {
    display: block;
    width: 100%;
    box-sizing: border-box;
    height: 44px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 0.9375rem;
    padding: 10px 14px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    background: #f1f5f9 !important;
    color: #334155 !important;
    cursor: not-allowed;
}
#refineryReceiveModal .oj-refinery-receive-input[readonly] {
    pointer-events: none;
    -webkit-user-select: none;
    user-select: none;
}
#refineryReceiveModal .oj-refinery-receive-input:focus {
    outline: none;
    border-color: #cbd5e1;
    box-shadow: none;
}
#refineryReceiveModal .oj-refinery-receive-err {
    margin-top: 12px;
    font-size: 13px;
}
#refineryReceiveModal .oj-refinery-receive-actions {
    margin-top: 22px !important;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
    align-items: center;
}
#refineryReceiveModal .oj-refinery-receive-actions .btn-filter-apply {
    border-radius: 8px;
    padding: 10px 22px;
}
#refineryReceiveModal .oj-refinery-receive-actions .btn-filter-clear {
    border-radius: 8px;
    padding: 10px 22px;
}
#refineryReceiveModal .filter-modal-close {
    border-radius: 8px;
    transition: background 0.15s ease;
}
#refineryReceiveModal .filter-modal-close:hover {
    background: rgba(255, 255, 255, 0.14);
}

.filter-modal-content {
    background: #fff;
    border-radius: 6px;
    padding: 0;
    width: 90%;
    max-width: 600px;
    max-height: 85vh;
    overflow: auto;
}

.filter-modal-content.filter-modal-content-wide {
    max-width: 560px;
}

/* Advance Filter (purple) */
.adv-filter-shell {
    background: #fff;
    border-radius: 10px;
    width: 92%;
    max-width: 720px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 16px 48px rgba(91, 33, 182, 0.12);
    border: 1px solid #c4b5fd;
}
.adv-filter-head {
    position: relative;
    text-align: center;
    padding: 14px 40px 14px 16px;
    border-bottom: 1px solid #ddd6fe;
    background: #faf5ff;
    border-radius: 10px 10px 0 0;
}
.adv-filter-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #6d28d9;
    letter-spacing: 0.02em;
}
.adv-filter-close {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 22px;
    line-height: 1;
    color: #6d28d9;
    cursor: pointer;
    padding: 4px 8px;
}
.adv-filter-body {
    padding: 18px 20px 22px;
}
.adv-filter-grid {
    display: grid;
    grid-template-columns: 128px 1fr;
    gap: 14px 18px;
    align-items: center;
}
.adv-filter-label {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 700;
    color: #1e293b;
}
.adv-filter-label-stack {
    margin-bottom: 4px;
}
.adv-date-row-inner {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.adv-date-emdash {
    color: #64748b;
    font-size: 0.9rem;
}
.adv-input {
    height: 38px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 0.875rem;
    color: #0f172a;
}
.adv-input:focus {
    outline: none;
    border-color: #a78bfa;
    box-shadow: 0 0 0 2px rgba(167, 139, 250, 0.25);
}
.adv-input-full {
    width: 100%;
}
.adv-icon-btn {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    padding: 0;
}
.adv-icon-btn:hover {
    border-color: #c4b5fd;
    color: #6d28d9;
}
.adv-filter-cols-2 {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 28px;
    margin-top: 4px;
}
.adv-filter-col {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.adv-filter-footer-btns {
    grid-column: 1 / -1;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    padding-top: 20px;
    margin-top: 10px;
    border-top: 1px solid #ede9fe;
}
.btn-adv-apply {
    min-width: 140px;
    padding: 9px 22px;
    border-radius: 8px;
    border: 2px solid #7c3aed;
    background: #fff;
    color: #6d28d9;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
}
.btn-adv-apply:hover {
    background: #f5f3ff;
}
.btn-adv-clear {
    min-width: 140px;
    padding: 9px 22px;
    border-radius: 8px;
    border: 2px solid #e879a9;
    background: #fff;
    color: #db2777;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
}
.btn-adv-clear:hover {
    background: #fdf2f8;
}
#filterModal .oj-msdd-toggle {
    height: 38px;
    border-radius: 8px;
}
#filterModal .oj-msdd-panel {
    z-index: 60;
}

.filter-modal-header {
    background: #11294b;
    color: #fff;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 6px 6px 0 0;
}

.filter-modal-header h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
}

.filter-modal-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-modal-body {
    padding: 16px;
}

/* Filter form in modal - same layout as previous filter block */
.filter-form-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-form-inline .form-group {
    margin-bottom: 0;
}

.filter-form-inline .form-group label {
    display: block;
    font-size: 0.875rem;
    color: #334155;
    font-weight: 500;
    margin-bottom: 6px;
}

.filter-form-inline .form-control {
    height: 36px;
    font-size: 0.875rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 6px 12px;
    min-width: 140px;
}

.filter-date-range {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-date-range input {
    flex: 1;
    min-width: 128px;
    max-width: 220px;
    height: 36px;
    font-size: 0.875rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 6px 10px;
}

.filter-date-range .filter-date-to {
    color: #64748b;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.filter-form-grid {
    display: grid;
    grid-template-columns: 108px 1fr;
    gap: 14px 16px;
    align-items: center;
}

.filter-form-grid .filter-field-label {
    margin: 0;
    font-size: 0.875rem;
    color: #334155;
    font-weight: 600;
}

.filter-form-grid .form-control {
    height: 36px;
    font-size: 0.875rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 6px 12px;
    width: 100%;
}

.filter-form-actions-row {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    margin-top: 6px;
    padding-top: 18px;
    border-top: 1px solid #e8edf3;
}

.btn-filter-apply {
    background: #11294b !important;
    color: #fff !important;
    border: none !important;
    padding: 8px 20px;
    border-radius: 4px;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-filter-apply:hover {
    filter: brightness(1.08);
    color: #fff !important;
}

.btn-filter-clear {
    background: #fff !important;
    color: #475569 !important;
    border: 1px solid #cbd5e1 !important;
    padding: 8px 20px;
    border-radius: 4px;
    font-size: 0.8125rem;
    font-weight: 600;
}

.btn-filter-clear:hover {
    background: #f8fafc !important;
    color: #334155 !important;
}

/* Multi-select dropdown (checkbox list) */
.oj-msdd {
    position: relative;
    width: 100%;
}

.oj-msdd-toggle {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    height: 36px;
    padding: 6px 12px;
    font-size: 0.875rem;
    text-align: left;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    background: #fff;
    color: #1e293b;
    cursor: pointer;
}

.oj-msdd-toggle:hover {
    border-color: #cbd5e1;
}

.oj-msdd-toggle-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.oj-msdd-chevron {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    opacity: 0.55;
    transition: transform 0.15s ease;
}

.oj-msdd.open .oj-msdd-chevron {
    transform: rotate(180deg);
}

.oj-msdd-panel {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 50;
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
    max-height: 280px;
    overflow: hidden;
}

.oj-msdd-panel[hidden] {
    display: none !important;
}

.oj-msdd.open .oj-msdd-panel:not([hidden]) {
    display: flex !important;
}

.oj-msdd-search-wrap {
    padding: 8px;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}

.oj-msdd-search {
    width: 100%;
    height: 32px;
    padding: 4px 10px;
    font-size: 0.8125rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
}

.oj-msdd-select-all {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    padding: 8px 12px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    flex-shrink: 0;
}

.oj-msdd-select-all input {
    margin: 0;
}

.oj-msdd-list {
    overflow-y: auto;
    max-height: 200px;
    padding: 4px 0;
}

.oj-msdd-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    padding: 6px 12px;
    font-size: 0.8125rem;
    color: #334155;
    cursor: pointer;
}

.oj-msdd-item:hover {
    background: #f8fafc;
}

.oj-msdd-item input {
    margin: 0;
    flex-shrink: 0;
}

.oj-msdd-item.oj-msdd-hidden {
    display: none;
}

.filter-form-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-form-actions .btn-apply {
    background: #11294b !important;
    color: #fff;
    padding: 8px 16px;
    border-radius: 4px;
}

.filter-form-actions .btn-clear {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 8px 16px;
    border-radius: 4px;
}

#columnsList .column-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    margin-bottom: 4px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
}

#columnsList .column-item:hover {
    background: #f8fafc;
}

#columnsList .column-item input[type="checkbox"] {
    margin-right: 10px;
    cursor: pointer;
    width: 16px;
    height: 16px;
}

#columnsList .column-item label {
    margin: 0;
    cursor: pointer;
    font-size: 0.85rem;
    color: #334155;
    flex: 1;
}

/* Stock In modal — Product Selection pattern (tabs, navy table, gold actions) */
.stock-in-modal-wrap .stock-in-modal-shell {
    background: #fff;
    border-radius: 8px;
    width: 94%;
    max-width: 1180px;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.22);
}
.stock-in-modal-header-light {
    flex-shrink: 0;
    padding: 12px 18px 10px;
    border-bottom: 1px solid #e8e4dc;
    background: #fff;
}
.stock-in-modal-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.stock-in-modal-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 600;
    color: #11294b;
}
.stock-in-modal-title i {
    color: #b8922e;
    margin-right: 6px;
}
.stock-in-modal-close-x {
    background: none;
    border: none;
    color: #64748b;
    font-size: 26px;
    line-height: 1;
    cursor: pointer;
    padding: 0 4px;
}
.stock-in-modal-note {
    margin: 8px 0 0;
    font-size: 0.8rem;
    color: #64748b;
    font-style: italic;
}
.stock-in-modal-body {
    padding: 0 16px 16px;
    overflow-y: auto;
    flex: 1;
}
.sim-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 10px 0 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid #e8e4dc;
}
.sim-tab {
    border: 1px solid #d4c4a8;
    background: #faf8f6;
    color: #475569;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: default;
}
.sim-tab-active {
    background: linear-gradient(180deg, #e8d5a8 0%, #d4b978 100%);
    color: #1e293b;
    font-weight: 600;
    border-color: #c9a962;
}
.sim-tab-muted {
    opacity: 0.65;
}
.sim-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px 16px;
    margin-bottom: 12px;
}
.sim-tf label {
    display: block;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #64748b;
    margin-bottom: 4px;
}
.sim-tf-barcode {
    min-width: 200px;
    flex: 1;
}
.sim-input-with-icon {
    position: relative;
}
.sim-input-with-icon i {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #b8922e;
    font-size: 14px;
    pointer-events: none;
}
.sim-input-underline {
    width: 100%;
    min-width: 100px;
    border: none;
    border-bottom: 1px solid #cbd5e1;
    border-radius: 0;
    padding: 6px 28px 6px 4px;
    font-size: 0.875rem;
    background: transparent;
}
.sim-input-underline:focus {
    outline: none;
    border-bottom-color: #11294b;
}
.sim-tf-checks {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 4px;
}
.sim-check {
    font-size: 0.8rem;
    color: #475569;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}
.sim-toolbar-actions {
    margin-left: auto;
    display: flex;
    gap: 8px;
}
.btn-sim-toolbar {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    border: none;
    cursor: not-allowed;
    opacity: 0.65;
}
.stock-in-add-product-toolbar {
    cursor: pointer !important;
    opacity: 1 !important;
}
.btn-sim-toolbar-gold {
    background: linear-gradient(180deg, #e8d5a8 0%, #d4b978 100%);
    color: #1e293b;
}
.btn-sim-toolbar-navy {
    background: #11294b;
    color: #fff;
}
.sim-table-scroll {
    overflow-x: auto;
    margin-bottom: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}
.sim-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    min-width: 1420px;
}
.sim-group-row .sim-group-th {
    background: #11294b;
    color: #fff;
    text-align: left;
    padding: 0;
    border-bottom: 1px solid #0f2447;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.sim-group-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    font-weight: 600;
    font-size: 0.8rem;
}
.sim-group-add-line {
    margin: 4px 10px 4px 0;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    line-height: 1;
    flex-shrink: 0;
}
.sim-group-add-line:hover {
    background: rgba(255, 255, 255, 0.22);
}
.stock-in-line-remove {
    width: 28px;
    height: 28px;
    padding: 0;
    border: none;
    background: #fee2e2;
    color: #b91c1c;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
}
.stock-in-line-remove:hover {
    background: #fecaca;
}
.stock-in-modal-wrap.filter-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.stock-in-product-selection-hint {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin: 0 0 8px;
    font-weight: 600;
}
.sim-table thead tr:last-child th {
    background: #11294b;
    color: #fff;
    font-weight: 600;
    padding: 8px 10px;
    white-space: nowrap;
    border-right: 1px solid rgba(255, 255, 255, 0.12);
}
.sim-table thead tr:last-child th:last-child {
    border-right: none;
}
.sim-table tbody td {
    padding: 4px 6px;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
    background: #fff;
}
.sim-cell-input,
.sim-cell-select {
    width: 100%;
    min-width: 64px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 5px 6px;
    font-size: 0.8rem;
    background: #fff;
}
.sim-cell-input:focus,
.sim-cell-select:focus {
    outline: none;
    border-color: #11294b;
}
.sim-cell-select {
    height: 30px;
}
.stock-in-modal-footer {
    border-top: 1px solid #e8e4dc;
    padding-top: 12px;
    margin-top: 4px;
}
.sim-footer-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
}
@media (max-width: 768px) {
    .sim-footer-fields {
        grid-template-columns: 1fr;
    }
}
.sim-footer-field label {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 4px;
}
.sim-footer-input {
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 8px 10px;
    font-size: 0.875rem;
}
.stock-in-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.btn-sim-add {
    background: linear-gradient(180deg, #e8d5a8 0%, #c9a962 100%);
    color: #1e293b;
    border: 1px solid #b8922e;
    padding: 10px 22px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
}
.btn-sim-add:hover:not(:disabled) {
    filter: brightness(1.02);
}
.btn-sim-add:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
.btn-sim-cancel {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 10px 18px;
    border-radius: 6px;
    cursor: pointer;
}
</style>

<script>
window.STOCK_IN_BRANCH_OPTIONS = <?= json_encode(array_map(function ($b) {
    return ['id' => (int)($b['id'] ?? 0), 'name' => (string)($b['name'] ?? '')];
}, $branches), JSON_UNESCAPED_UNICODE); ?>;

// Column Definitions
const columnDefinitions = [
    { key: 'active', label: 'Select' },
    { key: 'amount-paid', label: 'Amount Paid' },
    { key: 'description', label: 'Description' },
    { key: 'invoice-no', label: 'Invoice No.' },
    { key: 'customer-name', label: 'Customer Name' },
    { key: 'metal', label: 'Metal' },
    { key: 'product-name', label: 'Product Name' },
    { key: 'location', label: 'Location' },
    { key: 'gross-wt', label: 'Gross Wt' },
    { key: 'less-wt', label: 'Less Wt' },
    { key: 'stone-wt', label: 'Stone Wt' },
    { key: 'net-wt', label: 'Net Wt' },
    { key: 'purity', label: 'Purity' },
    { key: 'date', label: 'Date' },
    { key: 'final-wt', label: 'Final Wt.' },
    { key: 'rate', label: 'Rate' },
    { key: 'amount', label: 'Amount' },
    { key: 'quantity', label: 'Quantity' },
    { key: 'branch-name', label: 'Branch Name' },
    { key: 'sales-person', label: 'Sales Person' },
    { key: 'pure-wt', label: 'Pure Wt' },
    { key: 'current-gold-rate', label: 'Current Gold Rate' },
    { key: 'source', label: 'Source' },
    { key: 'against-invoice-no', label: 'Against Invoice No' },
    { key: 'stock-in', label: 'Stock In' },
    { key: 'print', label: 'Print' }
];

const OLD_JEWELLERY_COL_PREFS_KEY = 'oldJewelleryColumnPreferences_v3';
const OLD_JEWELLERY_COL_ORDER_KEY = 'oldJewelleryColumnOrder_v1';
const OJ_COL_PINNED_FIRST = ['active'];
const OJ_COL_PINNED_LAST = ['stock-in', 'print'];

function getDefaultMiddleColumnKeys() {
    return columnDefinitions.map(function (c) { return c.key; }).filter(function (k) {
        return OJ_COL_PINNED_FIRST.indexOf(k) === -1 && OJ_COL_PINNED_LAST.indexOf(k) === -1;
    });
}

function mergeOrderWithSavedMiddle(savedMiddle) {
    var defMid = getDefaultMiddleColumnKeys();
    var mid = [];
    var seen = {};
    if (Array.isArray(savedMiddle)) {
        savedMiddle.forEach(function (k) {
            if (defMid.indexOf(k) >= 0 && !seen[k]) {
                mid.push(k);
                seen[k] = 1;
            }
        });
    }
    defMid.forEach(function (k) {
        if (!seen[k]) {
            mid.push(k);
            seen[k] = 1;
        }
    });
    return OJ_COL_PINNED_FIRST.concat(mid, OJ_COL_PINNED_LAST);
}

function getSavedColumnOrderMiddle() {
    try {
        var raw = localStorage.getItem(OLD_JEWELLERY_COL_ORDER_KEY);
        if (!raw) return null;
        var a = JSON.parse(raw);
        return Array.isArray(a) ? a : null;
    } catch (e) {
        return null;
    }
}

function saveColumnOrderMiddle(midKeys) {
    localStorage.setItem(OLD_JEWELLERY_COL_ORDER_KEY, JSON.stringify(midKeys));
}

function applyColumnOrder(fullOrder) {
    var table = document.getElementById('oldJewelleryTable');
    if (!table) return;
    var hr = table.querySelector('thead tr');
    if (!hr) return;
    var thMap = {};
    hr.querySelectorAll('th[data-column]').forEach(function (th) {
        thMap[th.getAttribute('data-column')] = th;
    });
    fullOrder.forEach(function (key) {
        var n = thMap[key];
        if (n) hr.appendChild(n);
    });
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.querySelector('td[colspan]')) return;
        var tdMap = {};
        tr.querySelectorAll('td[data-column]').forEach(function (td) {
            tdMap[td.getAttribute('data-column')] = td;
        });
        fullOrder.forEach(function (key) {
            var d = tdMap[key];
            if (d) tr.appendChild(d);
        });
    });
}

function applyColumnOrderFromStorage() {
    applyColumnOrder(mergeOrderWithSavedMiddle(getSavedColumnOrderMiddle()));
}

function getOjColumnLabelByKey(key) {
    for (var i = 0; i < columnDefinitions.length; i++) {
        if (columnDefinitions[i].key === key) {
            return columnDefinitions[i].label;
        }
    }
    return key || '';
}

function ensureOjColDragGhostEl() {
    var g = document.getElementById('ojColDragGhost');
    if (g) {
        return g;
    }
    g = document.createElement('div');
    g.id = 'ojColDragGhost';
    g.className = 'oj-col-drag-ghost';
    g.setAttribute('aria-hidden', 'true');
    document.body.appendChild(g);
    return g;
}

function showOjColDragGhost(text, clientX, clientY) {
    var g = ensureOjColDragGhostEl();
    g.textContent = text;
    g.style.display = 'block';
    g.style.left = Math.round(clientX + 14) + 'px';
    g.style.top = Math.round(clientY + 16) + 'px';
}

function moveOjColDragGhost(clientX, clientY) {
    var g = document.getElementById('ojColDragGhost');
    if (!g || g.style.display === 'none') {
        return;
    }
    g.style.left = Math.round(clientX + 14) + 'px';
    g.style.top = Math.round(clientY + 16) + 'px';
}

function hideOjColDragGhost() {
    var g = document.getElementById('ojColDragGhost');
    if (g) {
        g.style.display = 'none';
    }
}

function attachOjColumnDragHandles() {
    var table = document.getElementById('oldJewelleryTable');
    if (!table) return;
    var skip = { active: 1, 'stock-in': 1, print: 1 };
    table.querySelectorAll('thead th[data-column]').forEach(function (th) {
        var key = th.getAttribute('data-column');
        if (skip[key]) return;
        if (th.querySelector('.oj-col-drag-handle')) return;
        var sp = document.createElement('span');
        sp.className = 'oj-col-drag-handle';
        sp.setAttribute('data-oj-drag-key', key);
        sp.setAttribute('title', 'Drag to reorder column');
        sp.setAttribute('aria-grabbed', 'false');
        sp.innerHTML = '<i class="feather icon-move" aria-hidden="true"></i>';
        sp.addEventListener('click', function (ev) { ev.stopPropagation(); });
        th.insertBefore(sp, th.firstChild);
    });
}

function initOjColumnDragDrop() {
    var table = document.getElementById('oldJewelleryTable');
    if (!table) return;
    if (table.getAttribute('data-oj-col-dnd-init') === '1') {
        return;
    }
    table.setAttribute('data-oj-col-dnd-init', '1');
    var skipDrop = { active: 1, 'stock-in': 1, print: 1 };
    var state = { active: false, fromKey: null, sourceTh: null };

    function clearDropHighlights() {
        table.querySelectorAll('thead th.oj-col-drop-target').forEach(function (x) {
            x.classList.remove('oj-col-drop-target');
        });
    }

    function clearDragVisuals() {
        clearDropHighlights();
        table.querySelectorAll('thead th.oj-col-drag-source').forEach(function (x) {
            x.classList.remove('oj-col-drag-source');
        });
        hideOjColDragGhost();
    }

    function applyReorder(fromKey, dropKey) {
        if (!fromKey || !dropKey || fromKey === dropKey) {
            return;
        }
        if (skipDrop[fromKey] || skipDrop[dropKey]) {
            return;
        }
        var defMid = getDefaultMiddleColumnKeys();
        if (defMid.indexOf(fromKey) < 0 || defMid.indexOf(dropKey) < 0) {
            return;
        }
        var hr = table.querySelector('thead tr');
        if (!hr) {
            return;
        }
        var mid = [];
        hr.querySelectorAll('th[data-column]').forEach(function (hth) {
            var kk = hth.getAttribute('data-column');
            if (skipDrop[kk]) {
                return;
            }
            mid.push(kk);
        });
        mid = mid.filter(function (k) { return k !== fromKey; });
        var insertAt = mid.indexOf(dropKey);
        if (insertAt < 0) {
            insertAt = mid.length;
        }
        mid.splice(insertAt, 0, fromKey);

        saveColumnOrderMiddle(mid);
        applyColumnOrder(OJ_COL_PINNED_FIRST.concat(mid, OJ_COL_PINNED_LAST));
        attachOjColumnDragHandles();
        if (typeof window.feather !== 'undefined' && typeof feather.replace === 'function') {
            try {
                feather.replace();
            } catch (fe) {}
        }
        applyColumnVisibility();
    }

    function thFromPoint(clientX, clientY) {
        var el = document.elementFromPoint(clientX, clientY);
        if (!el || !el.closest) {
            return null;
        }
        var th = el.closest('thead th[data-column]');
        if (!th || !table.contains(th)) {
            return null;
        }
        return th;
    }

    function onPointerMove(e) {
        if (!state.active) {
            return;
        }
        moveOjColDragGhost(e.clientX, e.clientY);
        var th = thFromPoint(e.clientX, e.clientY);
        clearDropHighlights();
        if (!th) {
            return;
        }
        var k = th.getAttribute('data-column');
        if (skipDrop[k]) {
            return;
        }
        if (k === state.fromKey) {
            return;
        }
        th.classList.add('oj-col-drop-target');
    }

    function endPointerDrag(e) {
        document.removeEventListener('pointermove', onPointerMove, true);
        document.removeEventListener('pointerup', endPointerDrag, true);
        document.removeEventListener('pointercancel', endPointerDrag, true);
        if (!state.active) {
            return;
        }
        var fromKey = state.fromKey;
        var th = null;
        if (e && typeof e.clientX === 'number' && typeof e.clientY === 'number') {
            th = thFromPoint(e.clientX, e.clientY);
        }
        state.active = false;
        state.fromKey = null;
        state.sourceTh = null;
        table.classList.remove('oj-col-dragging');
        document.body.style.userSelect = '';
        clearDragVisuals();
        table.querySelectorAll('.oj-col-drag-handle[aria-grabbed="true"]').forEach(function (x) {
            x.setAttribute('aria-grabbed', 'false');
        });

        if (!th || !fromKey) {
            return;
        }
        var dropKey = th.getAttribute('data-column');
        applyReorder(fromKey, dropKey);
    }

    /* Pointer-based reorder: HTML5 DnD is unreliable on <th> in several browsers */
    table.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' && e.button !== 0) {
            return;
        }
        var h = e.target.closest('.oj-col-drag-handle');
        if (!h || !table.contains(h)) {
            return;
        }
        var k = h.getAttribute('data-oj-drag-key');
        if (!k) {
            return;
        }
        if (state.active) {
            document.removeEventListener('pointermove', onPointerMove, true);
            document.removeEventListener('pointerup', endPointerDrag, true);
            document.removeEventListener('pointercancel', endPointerDrag, true);
            state.active = false;
            state.fromKey = null;
            state.sourceTh = null;
            table.classList.remove('oj-col-dragging');
            document.body.style.userSelect = '';
            clearDragVisuals();
        }
        e.preventDefault();
        e.stopPropagation();
        var sourceTh = h.closest('thead th[data-column]');
        state.active = true;
        state.fromKey = k;
        state.sourceTh = sourceTh;
        if (sourceTh) {
            sourceTh.classList.add('oj-col-drag-source');
        }
        showOjColDragGhost(getOjColumnLabelByKey(k), e.clientX, e.clientY);
        h.setAttribute('aria-grabbed', 'true');
        table.classList.add('oj-col-dragging');
        document.body.style.userSelect = 'none';
        document.addEventListener('pointermove', onPointerMove, true);
        document.addEventListener('pointerup', endPointerDrag, true);
        document.addEventListener('pointercancel', endPointerDrag, true);
    }, true);
}

/** Refine tab — column order + drag/drop (localStorage) */
const refineColumnDefinitions = [
    { key: 'rfid', label: 'RFID Code' },
    { key: 'job-order', label: 'Job Order No' },
    { key: 'against-ref', label: 'Against Ref. No.' },
    { key: 'customer', label: 'Customer Name' },
    { key: 'short-desc', label: 'Short Description' },
    { key: 'purity', label: 'Purity' },
    { key: 'gross-wt', label: 'Gross Wt' },
    { key: 'final-wt', label: 'Final Wt.' },
    { key: 'assign', label: 'Assign To' },
    { key: 'due-date', label: 'Due Date' },
    { key: 'order-dt', label: 'Order Dt.' },
    { key: 'tag-no', label: 'Tag No.' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Priority' },
    { key: 'invoice', label: 'Invoice' },
    { key: 'print', label: 'Print' },
    { key: 'track', label: 'TrackOrder' },
    { key: 'branch', label: 'Branch Name' }
];
const OLD_JEWELLERY_REFINE_COL_ORDER_KEY = 'oldJewelleryRefineColumnOrder_v1';

function getRefineDefaultOrder() {
    return refineColumnDefinitions.map(function (c) { return c.key; });
}

function mergeRefineOrderWithSaved(saved) {
    var def = getRefineDefaultOrder();
    var out = [];
    var seen = {};
    if (Array.isArray(saved)) {
        saved.forEach(function (k) {
            if (def.indexOf(k) >= 0 && !seen[k]) {
                out.push(k);
                seen[k] = 1;
            }
        });
    }
    def.forEach(function (k) {
        if (!seen[k]) {
            out.push(k);
            seen[k] = 1;
        }
    });
    return out;
}

function getSavedRefineColumnOrder() {
    try {
        var raw = localStorage.getItem(OLD_JEWELLERY_REFINE_COL_ORDER_KEY);
        if (!raw) return null;
        var a = JSON.parse(raw);
        return Array.isArray(a) ? a : null;
    } catch (e) {
        return null;
    }
}

function saveRefineColumnOrder(keys) {
    localStorage.setItem(OLD_JEWELLERY_REFINE_COL_ORDER_KEY, JSON.stringify(keys));
}

function applyRefineColumnOrder(fullOrder) {
    var table = document.getElementById('refineTable');
    if (!table) return;
    var hr = table.querySelector('thead tr');
    if (!hr) return;
    var thMap = {};
    hr.querySelectorAll('th[data-column]').forEach(function (th) {
        thMap[th.getAttribute('data-column')] = th;
    });
    fullOrder.forEach(function (key) {
        var n = thMap[key];
        if (n) hr.appendChild(n);
    });
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.querySelector('td[colspan]')) return;
        var tdMap = {};
        tr.querySelectorAll('td[data-column]').forEach(function (td) {
            tdMap[td.getAttribute('data-column')] = td;
        });
        fullOrder.forEach(function (key) {
            var d = tdMap[key];
            if (d) tr.appendChild(d);
        });
    });
}

function applyRefineColumnOrderFromStorage() {
    applyRefineColumnOrder(mergeRefineOrderWithSaved(getSavedRefineColumnOrder()));
}

function getRefineColumnLabelByKey(key) {
    for (var i = 0; i < refineColumnDefinitions.length; i++) {
        if (refineColumnDefinitions[i].key === key) {
            return refineColumnDefinitions[i].label;
        }
    }
    return key || '';
}

function attachRefineColumnDragHandles() {
    var table = document.getElementById('refineTable');
    if (!table) return;
    table.querySelectorAll('thead th[data-column]').forEach(function (th) {
        if (th.querySelector('.oj-col-drag-handle')) return;
        var sp = document.createElement('span');
        sp.className = 'oj-col-drag-handle';
        sp.setAttribute('data-oj-drag-key', th.getAttribute('data-column'));
        sp.setAttribute('title', 'Drag to reorder column');
        sp.setAttribute('aria-grabbed', 'false');
        sp.innerHTML = '<i class="feather icon-move" aria-hidden="true"></i>';
        sp.addEventListener('click', function (ev) { ev.stopPropagation(); });
        th.insertBefore(sp, th.firstChild);
    });
}

function initRefineColumnDragDrop() {
    var table = document.getElementById('refineTable');
    if (!table) return;
    if (table.getAttribute('data-refine-col-dnd-init') === '1') {
        return;
    }
    table.setAttribute('data-refine-col-dnd-init', '1');
    var state = { active: false, fromKey: null, sourceTh: null };

    function clearDropHighlights() {
        table.querySelectorAll('thead th.oj-col-drop-target').forEach(function (x) {
            x.classList.remove('oj-col-drop-target');
        });
    }

    function clearDragVisuals() {
        clearDropHighlights();
        table.querySelectorAll('thead th.oj-col-drag-source').forEach(function (x) {
            x.classList.remove('oj-col-drag-source');
        });
        hideOjColDragGhost();
    }

    function applyReorder(fromKey, dropKey) {
        if (!fromKey || !dropKey || fromKey === dropKey) return;
        var def = getRefineDefaultOrder();
        if (def.indexOf(fromKey) < 0 || def.indexOf(dropKey) < 0) return;
        var hr = table.querySelector('thead tr');
        if (!hr) return;
        var mid = [];
        hr.querySelectorAll('th[data-column]').forEach(function (hth) {
            mid.push(hth.getAttribute('data-column'));
        });
        mid = mid.filter(function (k) { return k !== fromKey; });
        var insertAt = mid.indexOf(dropKey);
        if (insertAt < 0) insertAt = mid.length;
        mid.splice(insertAt, 0, fromKey);
        saveRefineColumnOrder(mid);
        applyRefineColumnOrder(mid);
        attachRefineColumnDragHandles();
        if (typeof window.feather !== 'undefined' && typeof feather.replace === 'function') {
            try { feather.replace(); } catch (fe) {}
        }
    }

    function thFromPoint(clientX, clientY) {
        var el = document.elementFromPoint(clientX, clientY);
        if (!el || !el.closest) return null;
        var th = el.closest('thead th[data-column]');
        if (!th || !table.contains(th)) return null;
        return th;
    }

    function onPointerMove(e) {
        if (!state.active) return;
        moveOjColDragGhost(e.clientX, e.clientY);
        var th = thFromPoint(e.clientX, e.clientY);
        clearDropHighlights();
        if (!th) return;
        var k = th.getAttribute('data-column');
        if (k === state.fromKey) return;
        th.classList.add('oj-col-drop-target');
    }

    function endPointerDrag(e) {
        document.removeEventListener('pointermove', onPointerMove, true);
        document.removeEventListener('pointerup', endPointerDrag, true);
        document.removeEventListener('pointercancel', endPointerDrag, true);
        if (!state.active) return;
        var fromKey = state.fromKey;
        var th = null;
        if (e && typeof e.clientX === 'number' && typeof e.clientY === 'number') {
            th = thFromPoint(e.clientX, e.clientY);
        }
        state.active = false;
        state.fromKey = null;
        state.sourceTh = null;
        table.classList.remove('oj-col-dragging');
        document.body.style.userSelect = '';
        clearDragVisuals();
        table.querySelectorAll('.oj-col-drag-handle[aria-grabbed="true"]').forEach(function (x) {
            x.setAttribute('aria-grabbed', 'false');
        });
        if (!th || !fromKey) return;
        var dropKey = th.getAttribute('data-column');
        applyReorder(fromKey, dropKey);
    }

    table.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        var h = e.target.closest('.oj-col-drag-handle');
        if (!h || !table.contains(h)) return;
        var k = h.getAttribute('data-oj-drag-key');
        if (!k) return;
        if (state.active) {
            document.removeEventListener('pointermove', onPointerMove, true);
            document.removeEventListener('pointerup', endPointerDrag, true);
            document.removeEventListener('pointercancel', endPointerDrag, true);
            state.active = false;
            state.fromKey = null;
            state.sourceTh = null;
            table.classList.remove('oj-col-dragging');
            document.body.style.userSelect = '';
            clearDragVisuals();
        }
        e.preventDefault();
        e.stopPropagation();
        var sourceTh = h.closest('thead th[data-column]');
        state.active = true;
        state.fromKey = k;
        state.sourceTh = sourceTh;
        if (sourceTh) sourceTh.classList.add('oj-col-drag-source');
        showOjColDragGhost(getRefineColumnLabelByKey(k), e.clientX, e.clientY);
        h.setAttribute('aria-grabbed', 'true');
        table.classList.add('oj-col-dragging');
        document.body.style.userSelect = 'none';
        document.addEventListener('pointermove', onPointerMove, true);
        document.addEventListener('pointerup', endPointerDrag, true);
        document.addEventListener('pointercancel', endPointerDrag, true);
    }, true);
}

/** Received tab — column order + drag/drop (localStorage), same pattern as Refine */
const receivedRefineColumnDefinitions = [
    { key: 'rfid', label: 'RFID Code' },
    { key: 'job-order', label: 'Job Order No' },
    { key: 'against-ref', label: 'Against Ref. No.' },
    { key: 'customer', label: 'Customer Name' },
    { key: 'short-desc', label: 'Short Description' },
    { key: 'purity', label: 'Purity' },
    { key: 'gross-wt', label: 'Gross Wt' },
    { key: 'final-wt', label: 'Final Wt.' },
    { key: 'assign', label: 'Assign To' },
    { key: 'due-date', label: 'Due Date' },
    { key: 'order-dt', label: 'Order Dt.' },
    { key: 'tag-no', label: 'Tag No.' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Priority' },
    { key: 'jw-inv', label: 'Jobwork Inv.' },
    { key: 'stock-in', label: 'Stock In' },
    { key: 'print', label: 'Print' },
    { key: 'track', label: 'TrackOrder' },
    { key: 'branch', label: 'Branch Name' }
];
const OLD_JEWELLERY_RECEIVED_REFINE_COL_ORDER_KEY = 'oldJewelleryReceivedRefineColumnOrder_v1';

function getReceivedRefineDefaultOrder() {
    return receivedRefineColumnDefinitions.map(function (c) { return c.key; });
}

function mergeReceivedRefineOrderWithSaved(saved) {
    var def = getReceivedRefineDefaultOrder();
    var out = [];
    var seen = {};
    if (Array.isArray(saved)) {
        saved.forEach(function (k) {
            if (def.indexOf(k) >= 0 && !seen[k]) {
                out.push(k);
                seen[k] = 1;
            }
        });
    }
    def.forEach(function (k) {
        if (!seen[k]) {
            out.push(k);
            seen[k] = 1;
        }
    });
    return out;
}

function getSavedReceivedRefineColumnOrder() {
    try {
        var raw = localStorage.getItem(OLD_JEWELLERY_RECEIVED_REFINE_COL_ORDER_KEY);
        if (!raw) return null;
        var a = JSON.parse(raw);
        return Array.isArray(a) ? a : null;
    } catch (e) {
        return null;
    }
}

function saveReceivedRefineColumnOrder(keys) {
    localStorage.setItem(OLD_JEWELLERY_RECEIVED_REFINE_COL_ORDER_KEY, JSON.stringify(keys));
}

function applyReceivedRefineColumnOrder(fullOrder) {
    var table = document.getElementById('receivedRefineTable');
    if (!table) return;
    var hr = table.querySelector('thead tr');
    if (!hr) return;
    var thMap = {};
    hr.querySelectorAll('th[data-column]').forEach(function (th) {
        thMap[th.getAttribute('data-column')] = th;
    });
    fullOrder.forEach(function (key) {
        var n = thMap[key];
        if (n) hr.appendChild(n);
    });
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.querySelector('td[colspan]')) return;
        var tdMap = {};
        tr.querySelectorAll('td[data-column]').forEach(function (td) {
            tdMap[td.getAttribute('data-column')] = td;
        });
        fullOrder.forEach(function (key) {
            var d = tdMap[key];
            if (d) tr.appendChild(d);
        });
    });
}

function applyReceivedRefineColumnOrderFromStorage() {
    applyReceivedRefineColumnOrder(mergeReceivedRefineOrderWithSaved(getSavedReceivedRefineColumnOrder()));
}

function getReceivedRefineColumnLabelByKey(key) {
    for (var i = 0; i < receivedRefineColumnDefinitions.length; i++) {
        if (receivedRefineColumnDefinitions[i].key === key) {
            return receivedRefineColumnDefinitions[i].label;
        }
    }
    return key || '';
}

function attachReceivedRefineColumnDragHandles() {
    var table = document.getElementById('receivedRefineTable');
    if (!table) return;
    table.querySelectorAll('thead th[data-column]').forEach(function (th) {
        if (th.querySelector('.oj-col-drag-handle')) return;
        var sp = document.createElement('span');
        sp.className = 'oj-col-drag-handle';
        sp.setAttribute('data-oj-drag-key', th.getAttribute('data-column'));
        sp.setAttribute('title', 'Drag to reorder column');
        sp.setAttribute('aria-grabbed', 'false');
        sp.innerHTML = '<i class="feather icon-move" aria-hidden="true"></i>';
        sp.addEventListener('click', function (ev) { ev.stopPropagation(); });
        th.insertBefore(sp, th.firstChild);
    });
}

function initReceivedRefineColumnDragDrop() {
    var table = document.getElementById('receivedRefineTable');
    if (!table) return;
    if (table.getAttribute('data-received-refine-col-dnd-init') === '1') {
        return;
    }
    table.setAttribute('data-received-refine-col-dnd-init', '1');
    var state = { active: false, fromKey: null, sourceTh: null };

    function clearDropHighlights() {
        table.querySelectorAll('thead th.oj-col-drop-target').forEach(function (x) {
            x.classList.remove('oj-col-drop-target');
        });
    }

    function clearDragVisuals() {
        clearDropHighlights();
        table.querySelectorAll('thead th.oj-col-drag-source').forEach(function (x) {
            x.classList.remove('oj-col-drag-source');
        });
        hideOjColDragGhost();
    }

    function applyReorder(fromKey, dropKey) {
        if (!fromKey || !dropKey || fromKey === dropKey) return;
        var def = getReceivedRefineDefaultOrder();
        if (def.indexOf(fromKey) < 0 || def.indexOf(dropKey) < 0) return;
        var hr = table.querySelector('thead tr');
        if (!hr) return;
        var mid = [];
        hr.querySelectorAll('th[data-column]').forEach(function (hth) {
            mid.push(hth.getAttribute('data-column'));
        });
        mid = mid.filter(function (k) { return k !== fromKey; });
        var insertAt = mid.indexOf(dropKey);
        if (insertAt < 0) insertAt = mid.length;
        mid.splice(insertAt, 0, fromKey);
        saveReceivedRefineColumnOrder(mid);
        applyReceivedRefineColumnOrder(mid);
        attachReceivedRefineColumnDragHandles();
        if (typeof window.feather !== 'undefined' && typeof feather.replace === 'function') {
            try { feather.replace(); } catch (fe) {}
        }
    }

    function thFromPoint(clientX, clientY) {
        var el = document.elementFromPoint(clientX, clientY);
        if (!el || !el.closest) return null;
        var th = el.closest('thead th[data-column]');
        if (!th || !table.contains(th)) return null;
        return th;
    }

    function onPointerMove(e) {
        if (!state.active) return;
        moveOjColDragGhost(e.clientX, e.clientY);
        var th = thFromPoint(e.clientX, e.clientY);
        clearDropHighlights();
        if (!th) return;
        var k = th.getAttribute('data-column');
        if (k === state.fromKey) return;
        th.classList.add('oj-col-drop-target');
    }

    function endPointerDrag(e) {
        document.removeEventListener('pointermove', onPointerMove, true);
        document.removeEventListener('pointerup', endPointerDrag, true);
        document.removeEventListener('pointercancel', endPointerDrag, true);
        if (!state.active) return;
        var fromKey = state.fromKey;
        var th = null;
        if (e && typeof e.clientX === 'number' && typeof e.clientY === 'number') {
            th = thFromPoint(e.clientX, e.clientY);
        }
        state.active = false;
        state.fromKey = null;
        state.sourceTh = null;
        table.classList.remove('oj-col-dragging');
        document.body.style.userSelect = '';
        clearDragVisuals();
        table.querySelectorAll('.oj-col-drag-handle[aria-grabbed="true"]').forEach(function (x) {
            x.setAttribute('aria-grabbed', 'false');
        });
        if (!th || !fromKey) return;
        var dropKey = th.getAttribute('data-column');
        applyReorder(fromKey, dropKey);
    }

    table.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        var h = e.target.closest('.oj-col-drag-handle');
        if (!h || !table.contains(h)) return;
        var k = h.getAttribute('data-oj-drag-key');
        if (!k) return;
        if (state.active) {
            document.removeEventListener('pointermove', onPointerMove, true);
            document.removeEventListener('pointerup', endPointerDrag, true);
            document.removeEventListener('pointercancel', endPointerDrag, true);
            state.active = false;
            state.fromKey = null;
            state.sourceTh = null;
            table.classList.remove('oj-col-dragging');
            document.body.style.userSelect = '';
            clearDragVisuals();
        }
        e.preventDefault();
        e.stopPropagation();
        var sourceTh = h.closest('thead th[data-column]');
        state.active = true;
        state.fromKey = k;
        state.sourceTh = sourceTh;
        if (sourceTh) sourceTh.classList.add('oj-col-drag-source');
        showOjColDragGhost(getReceivedRefineColumnLabelByKey(k), e.clientX, e.clientY);
        h.setAttribute('aria-grabbed', 'true');
        table.classList.add('oj-col-dragging');
        document.body.style.userSelect = 'none';
        document.addEventListener('pointermove', onPointerMove, true);
        document.addEventListener('pointerup', endPointerDrag, true);
        document.addEventListener('pointercancel', endPointerDrag, true);
    }, true);
}

// Get column preferences from localStorage
function getColumnPreferences() {
    const saved = localStorage.getItem(OLD_JEWELLERY_COL_PREFS_KEY);
    if (saved) {
        return JSON.parse(saved);
    }
    const defaults = {};
    columnDefinitions.forEach(col => {
        defaults[col.key] = true;
    });
    return defaults;
}

// Save column preferences
function saveColumnPreferences(prefs) {
    localStorage.setItem(OLD_JEWELLERY_COL_PREFS_KEY, JSON.stringify(prefs));
}

function positionOjColumnsDropdown() {
    var btn = document.getElementById('oldJwlColumnsToggle');
    var panel = document.getElementById('oldJwlColumnsDropdown');
    if (!btn || !panel || !panel.classList.contains('show')) return;
    var r = btn.getBoundingClientRect();
    var w = Math.min(320, window.innerWidth - 16);
    var left = r.right - w;
    if (left < 8) left = 8;
    var top = r.bottom + 8;
    var maxH = Math.max(180, window.innerHeight - top - 16);
    panel.style.top = top + 'px';
    panel.style.left = left + 'px';
    panel.style.width = w + 'px';
    panel.style.maxHeight = Math.min(480, maxH) + 'px';
}

function toggleColumnsPanel(ev) {
    if (ev) {
        ev.preventDefault();
        ev.stopPropagation();
    }
    var panel = document.getElementById('oldJwlColumnsDropdown');
    var btn = document.getElementById('oldJwlColumnsToggle');
    if (!panel || !btn) return;
    if (panel.classList.contains('show')) {
        closeColumnsModal();
        return;
    }
    renderColumnsList();
    panel.classList.add('show');
    btn.setAttribute('aria-expanded', 'true');
    positionOjColumnsDropdown();
}

function openColumnsModal() {
    var panel = document.getElementById('oldJwlColumnsDropdown');
    var btn = document.getElementById('oldJwlColumnsToggle');
    if (!panel || !btn || panel.classList.contains('show')) return;
    renderColumnsList();
    panel.classList.add('show');
    btn.setAttribute('aria-expanded', 'true');
    positionOjColumnsDropdown();
}

function closeColumnsModal() {
    var panel = document.getElementById('oldJwlColumnsDropdown');
    var btn = document.getElementById('oldJwlColumnsToggle');
    if (panel) panel.classList.remove('show');
    if (btn) btn.setAttribute('aria-expanded', 'false');
}

// Refresh columns
function refreshColumns() {
    const defaults = {};
    columnDefinitions.forEach(col => {
        defaults[col.key] = true;
    });
    saveColumnPreferences(defaults);
    try {
        localStorage.removeItem(OLD_JEWELLERY_COL_ORDER_KEY);
    } catch (e) {}
    applyColumnOrder(mergeOrderWithSavedMiddle(null));
    attachOjColumnDragHandles();
    if (typeof window.feather !== 'undefined' && typeof feather.replace === 'function') {
        try {
            feather.replace();
        } catch (e) {}
    }
    applyColumnVisibility();
    renderColumnsList();
}

// Render columns list
function renderColumnsList() {
    const columnsList = document.getElementById('columnsList');
    const columnPrefs = getColumnPreferences();
    
    columnsList.innerHTML = '';
    
    columnDefinitions.forEach(col => {
        const item = document.createElement('div');
        item.className = 'column-item';
        const isChecked = columnPrefs[col.key] !== false;
        item.innerHTML = `
            <input type="checkbox" id="col_${col.key}" ${isChecked ? 'checked' : ''} onchange="toggleColumn('${col.key}', this.checked)">
            <label for="col_${col.key}">${col.label}</label>
        `;
        columnsList.appendChild(item);
    });
}

// Filter columns in modal
function filterColumns() {
    const search = document.getElementById('columnSearch').value.toLowerCase();
    const items = document.querySelectorAll('#columnsList .column-item');
    
    items.forEach(item => {
        const label = item.querySelector('label').textContent.toLowerCase();
        item.style.display = label.includes(search) ? 'flex' : 'none';
    });
}

// Toggle column visibility
function toggleColumn(key, visible) {
    const columnPrefs = getColumnPreferences();
    columnPrefs[key] = visible;
    saveColumnPreferences(columnPrefs);
    applyColumnVisibility();
}

// Apply column visibility
function applyColumnVisibility() {
    const columnPrefs = getColumnPreferences();
    
    columnDefinitions.forEach(col => {
        const isVisible = columnPrefs[col.key] !== false;
        const selector = `[data-column="${col.key}"]`;
        const headers = document.querySelectorAll(`#oldJewelleryTable th${selector}`);
        const cells = document.querySelectorAll(`#oldJewelleryTable td${selector}`);
        
        headers.forEach(header => {
            header.style.display = isVisible ? '' : 'none';
        });
        
        cells.forEach(cell => {
            cell.style.display = isVisible ? '' : 'none';
        });
    });
    
    // Update colspan for empty state
    const emptyRow = document.querySelector('#oldJewelleryTable tbody tr td[colspan]');
    if (emptyRow) {
        const visibleColumns = columnDefinitions.filter(col => columnPrefs[col.key] !== false).length;
        emptyRow.setAttribute('colspan', visibleColumns);
    }
}

// Filter modal functions
function openFilterModal() {
    document.getElementById('filterModal').classList.add('active');
    setTimeout(function() {
        if (typeof feather !== 'undefined' && feather.replace) {
            try { feather.replace(); } catch (e) {}
        }
    }, 0);
}

function closeFilterModal() {
    document.getElementById('filterModal').classList.remove('active');
    ojCloseAllAdvFilterMsdd();
}

function ojCloseAllAdvFilterMsdd() {
    var m = document.getElementById('filterModal');
    if (!m) return;
    m.querySelectorAll('.oj-msdd').forEach(function(w) {
        w.classList.remove('open');
        var p = w.querySelector('.oj-msdd-panel');
        var t = w.querySelector('.oj-msdd-toggle');
        if (p) p.setAttribute('hidden', 'hidden');
        if (t) t.setAttribute('aria-expanded', 'false');
    });
}

function ojBindOneAdvMsdd(wrap) {
    if (!wrap || wrap.getAttribute('data-oj-msdd-bound') === '1') return;
    wrap.setAttribute('data-oj-msdd-bound', '1');
    var toggle = wrap.querySelector('.oj-msdd-toggle');
    var panel = wrap.querySelector('.oj-msdd-panel');
    var search = wrap.querySelector('.oj-msdd-search');
    var selAllLab = wrap.querySelector('.oj-msdd-select-all');
    var selAll = selAllLab ? selAllLab.querySelector('input[type="checkbox"]') : null;
    if (!toggle || !panel) return;

    function getCbs() {
        return wrap.querySelectorAll('.oj-msdd-list .oj-msdd-cb');
    }
    function updateToggle() {
        var ph = wrap.getAttribute('data-placeholder') || 'Select';
        var toggleText = toggle.querySelector('.oj-msdd-toggle-text');
        if (!toggleText) return;
        var chk = [];
        getCbs().forEach(function(cb) {
            if (cb.checked) chk.push(cb);
        });
        if (chk.length === 0) {
            toggleText.textContent = ph;
            return;
        }
        if (chk.length === 1) {
            var lbl = chk[0].closest('label');
            var sp = lbl ? lbl.querySelector('span') : null;
            toggleText.textContent = sp ? sp.textContent.trim() : '1 selected';
            return;
        }
        toggleText.textContent = chk.length + ' selected';
    }
    function syncSelAll() {
        if (!selAll) return;
        var vis = [];
        getCbs().forEach(function(cb) {
            var row = cb.closest('.oj-msdd-item');
            if (row && !row.classList.contains('oj-msdd-hidden')) vis.push(cb);
        });
        if (!vis.length) {
            selAll.indeterminate = false;
            selAll.checked = false;
            return;
        }
        var n = vis.filter(function(c) { return c.checked; }).length;
        selAll.checked = n === vis.length;
        selAll.indeterminate = n > 0 && n < vis.length;
    }
    function closePn() {
        wrap.classList.remove('open');
        panel.setAttribute('hidden', 'hidden');
        toggle.setAttribute('aria-expanded', 'false');
    }
    function openPn() {
        wrap.classList.add('open');
        panel.removeAttribute('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        if (search) setTimeout(function() { try { search.focus(); } catch (e) {} }, 0);
    }
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (wrap.classList.contains('open')) closePn();
        else openPn();
    });
    if (search) {
        search.addEventListener('input', function() {
            var q = (search.value || '').toLowerCase().trim();
            wrap.querySelectorAll('.oj-msdd-item').forEach(function(row) {
                var lab = row.getAttribute('data-label') || '';
                if (!q || lab.indexOf(q) !== -1) row.classList.remove('oj-msdd-hidden');
                else row.classList.add('oj-msdd-hidden');
            });
            syncSelAll();
        });
        search.addEventListener('click', function(e) { e.stopPropagation(); });
    }
    if (selAll) {
        selAll.addEventListener('change', function() {
            var on = selAll.checked;
            if (on) {
                getCbs().forEach(function(cb) {
                    var row = cb.closest('.oj-msdd-item');
                    if (row && row.classList.contains('oj-msdd-hidden')) return;
                    cb.checked = true;
                });
            } else {
                getCbs().forEach(function(cb) { cb.checked = false; });
            }
            updateToggle();
            syncSelAll();
        });
        selAll.addEventListener('click', function(e) { e.stopPropagation(); });
    }
    getCbs().forEach(function(cb) {
        cb.addEventListener('change', function() { syncSelAll(); updateToggle(); });
        cb.addEventListener('click', function(e) { e.stopPropagation(); });
    });
    syncSelAll();
    updateToggle();
}

function initOjAdvanceFilterMsdd() {
    var modal = document.getElementById('filterModal');
    if (!modal) return;
    modal.querySelectorAll('.oj-msdd').forEach(ojBindOneAdvMsdd);
}

function resetFilterOrderDates() {
    document.getElementById('filterFromDate').value = '<?= date('Y-m-01') ?>';
    document.getElementById('filterToDate').value = '<?= date('Y-m-t') ?>';
}

function resetFilterDueDates() {
    var a = document.getElementById('filterDueFromDate');
    var b = document.getElementById('filterDueToDate');
    if (a) a.value = '';
    if (b) b.value = '';
}

function revertStockIn(stockId, sourceItemId) {
    if (!stockId) return;
    if (!confirm('Revert this Stock In? The piece will be removed from Stocked and the gross balance will be available again on the same OJB / scrap invoice line.')) return;
    var formData = new FormData();
    formData.append('stock_id', stockId);
    fetch('ajax/revert-stock-in-old-jewelry-scrap-item.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'success') {
                if (data.message) {
                    alert(data.message);
                }
                var url = 'old-jewellery.php?tab=scrap';
                var u = new URL(window.location.href);
                if (u.searchParams.get('from_date')) url += '&from_date=' + encodeURIComponent(u.searchParams.get('from_date'));
                if (u.searchParams.get('to_date')) url += '&to_date=' + encodeURIComponent(u.searchParams.get('to_date'));
                window.location.href = url;
            } else {
                alert(data.message || 'Failed to revert');
            }
        })
        .catch(function() { alert('Network error'); });
}

function stockInEscAttr(s) {
    if (s == null || s === '') return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function stockInBranchSelectHtml(selectedId) {
    var opts = window.STOCK_IN_BRANCH_OPTIONS || [];
    var html = '<option value="">-- Select --</option>';
    for (var i = 0; i < opts.length; i++) {
        var id = opts[i].id;
        var sel = selectedId != null && selectedId !== '' && String(id) === String(selectedId) ? ' selected' : '';
        html += '<option value="' + id + '"' + sel + '>' + stockInEscAttr(opts[i].name) + '</option>';
    }
    return html;
}

function stockInClearLines() {
    var tb = document.getElementById('stockInLinesBody');
    if (tb) tb.innerHTML = '';
}

function stockInRemoveLine(tr) {
    var tb = document.getElementById('stockInLinesBody');
    if (!tb || !tr || !tb.contains(tr)) return;
    if (tb.querySelectorAll('tr.stock-in-line-row').length <= 1) {
        alert('At least one line is required.');
        return;
    }
    tr.remove();
}

function stockInCreateLineRow(data) {
    data = data || {};
    var tr = document.createElement('tr');
    tr.className = 'stock-in-line-row';
    var defPid = (document.getElementById('stockInProductId') && document.getElementById('stockInProductId').value) || '';
    var defPcid = (document.getElementById('stockInProductCharacteristicId') && document.getElementById('stockInProductCharacteristicId').value) || '';
    var defMid = (document.getElementById('stockInMetalId') && document.getElementById('stockInMetalId').value) || '';
    var pid = data.product_id != null && data.product_id !== '' ? String(data.product_id) : defPid;
    var pcid = data.product_characteristic_id != null && data.product_characteristic_id !== '' ? String(data.product_characteristic_id) : defPcid;
    var mid = data.metal_id != null && data.metal_id !== '' ? String(data.metal_id) : defMid;
    tr.setAttribute('data-product-id', pid);
    tr.setAttribute('data-pc-id', pcid);
    tr.setAttribute('data-metal-id', mid);
    tr.innerHTML =
        '<td><input type="text" class="sim-cell-input si-in-product" placeholder="Product" value="' + stockInEscAttr(data.product) + '"></td>' +
        '<td><input type="number" step="0.0001" class="sim-cell-input si-in-gross" placeholder="0" value="' + stockInEscAttr(data.gross_wt) + '"></td>' +
        '<td><input type="number" step="0.0001" class="sim-cell-input si-in-final" placeholder="0" value="' + stockInEscAttr(data.final_wt) + '"></td>' +
        '<td><input type="number" step="0.0001" class="sim-cell-input si-in-net" placeholder="0" value="' + stockInEscAttr(data.net_wt) + '"></td>' +
        '<td><input type="number" step="0.0001" class="sim-cell-input si-in-less" placeholder="0" value="' + stockInEscAttr(data.less_wt != null ? data.less_wt : '0') + '"></td>' +
        '<td><input type="number" step="0.01" class="sim-cell-input si-in-purity" placeholder="0" value="' + stockInEscAttr(data.purity) + '"></td>' +
        '<td><input type="number" step="0.01" class="sim-cell-input si-in-rate" placeholder="0" value="' + stockInEscAttr(data.rate) + '"></td>' +
        '<td><input type="number" step="0.01" class="sim-cell-input si-in-amount" placeholder="0" value="' + stockInEscAttr(data.amount) + '"></td>' +
        '<td><input type="number" step="0.01" class="sim-cell-input si-in-qty" placeholder="1" value="' + stockInEscAttr(data.quantity != null ? data.quantity : '1') + '"></td>' +
        '<td><select class="sim-cell-select si-in-branch">' + stockInBranchSelectHtml(data.branch_id) + '</select></td>' +
        '<td><input type="text" class="sim-cell-input si-in-metal" value="' + stockInEscAttr(data.metal) + '"></td>' +
        '<td><input type="text" class="sim-cell-input si-in-location" value="' + stockInEscAttr(data.location) + '"></td>' +
        '<td><input type="text" class="sim-cell-input si-in-category" value="' + stockInEscAttr(data.category) + '"></td>' +
        '<td><input type="text" class="sim-cell-input si-in-against-inv" value="' + stockInEscAttr(data.against_invoice_no) + '"></td>' +
        '<td><input type="text" class="sim-cell-input si-in-against-v" value="' + stockInEscAttr(data.against_voucher) + '"></td>' +
        '<td class="text-center"><button type="button" class="stock-in-line-remove si-in-remove" title="Remove line">&times;</button></td>';
    var rm = tr.querySelector('.si-in-remove');
    if (rm) {
        rm.addEventListener('click', function() { stockInRemoveLine(tr); });
    }
    return tr;
}

function stockInAddEmptyLine() {
    var tb = document.getElementById('stockInLinesBody');
    if (!tb) return;
    var first = tb.querySelector('tr.stock-in-line-row');
    var copy = {
        product: '',
        gross_wt: '',
        final_wt: '',
        net_wt: '',
        less_wt: '0',
        purity: first ? (first.querySelector('.si-in-purity') || {}).value : '',
        rate: first ? (first.querySelector('.si-in-rate') || {}).value : '',
        amount: '',
        quantity: '1',
        branch_id: first ? (first.querySelector('.si-in-branch') || {}).value : '',
        metal: first ? (first.querySelector('.si-in-metal') || {}).value : '',
        location: first ? (first.querySelector('.si-in-location') || {}).value : '',
        category: '',
        against_invoice_no: first ? (first.querySelector('.si-in-against-inv') || {}).value : '',
        against_voucher: first ? (first.querySelector('.si-in-against-v') || {}).value : ''
    };
    tb.appendChild(stockInCreateLineRow(copy));
}

function collectStockInLinesFromDom() {
    var rows = document.querySelectorAll('#stockInLinesBody tr.stock-in-line-row');
    var lines = [];
    rows.forEach(function(tr) {
        var g = function(sel) {
            var el = tr.querySelector(sel);
            return el ? el.value : '';
        };
        lines.push({
            product: g('.si-in-product'),
            gross_wt: parseFloat(g('.si-in-gross')) || 0,
            final_wt: parseFloat(g('.si-in-final')) || 0,
            net_wt: parseFloat(g('.si-in-net')) || 0,
            less_wt: parseFloat(g('.si-in-less')) || 0,
            purity: parseFloat(g('.si-in-purity')) || 0,
            rate: parseFloat(g('.si-in-rate')) || 0,
            amount: parseFloat(g('.si-in-amount')) || 0,
            quantity: parseFloat(g('.si-in-qty')) || 1,
            branch_id: parseInt(g('.si-in-branch'), 10) || 0,
            metal: g('.si-in-metal'),
            location: g('.si-in-location'),
            category: g('.si-in-category'),
            against_invoice_no: g('.si-in-against-inv'),
            against_voucher: g('.si-in-against-v'),
            product_id: parseInt(tr.getAttribute('data-product-id'), 10) || 0,
            product_characteristic_id: parseInt(tr.getAttribute('data-pc-id'), 10) || 0,
            metal_id: parseInt(tr.getAttribute('data-metal-id'), 10) || 0
        });
    });
    return lines;
}

function stockInLineCount() {
    return document.querySelectorAll('#stockInLinesBody tr.stock-in-line-row').length;
}

// Stock In modal (scrap or purchase invoice line with scrap payment) — saves to tbl_old_jewelry_stock only
function openStockInModal(invoiceId, itemId, source) {
    if (!invoiceId || !itemId) return;
    source = source || 'scrap';
    document.getElementById('stockInInvoiceId').value = invoiceId;
    document.getElementById('stockInItemId').value = itemId;
    document.getElementById('stockInSource').value = source;
    document.getElementById('stockInComment').value = 'Old Jewellery - Scrap';
    var _pid = document.getElementById('stockInProductId');
    var _pcid = document.getElementById('stockInProductCharacteristicId');
    var _mid = document.getElementById('stockInMetalId');
    if (_pid) _pid.value = '';
    if (_pcid) _pcid.value = '';
    if (_mid) _mid.value = '';
    var auxCode = document.getElementById('stockInAuxCode');
    var desNo = document.getElementById('stockInDesNo');
    if (auxCode) auxCode.value = '';
    if (desNo) desNo.value = '';
    stockInClearLines();
    var url = (source === 'purchase')
        ? ('ajax/get-purchase-invoice-item-for-stock.php?invoice_id=' + encodeURIComponent(invoiceId) + '&item_id=' + encodeURIComponent(itemId))
        : ('ajax/get-old-jewelry-scrap-item.php?item_id=' + encodeURIComponent(itemId));
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'success' && data.item) {
                var it = data.item;
                var inv = data.invoice || {};
                document.getElementById('stockInBarcode').value = '';
                document.getElementById('stockInOriginalBarcode').value = '';
                var pidEl = document.getElementById('stockInProductId');
                var pcidEl = document.getElementById('stockInProductCharacteristicId');
                var midEl = document.getElementById('stockInMetalId');
                if (pidEl) pidEl.value = (it.product_id != null && it.product_id !== '') ? String(it.product_id) : '';
                if (pcidEl) pcidEl.value = (it.product_characteristic_id != null && it.product_characteristic_id !== '') ? String(it.product_characteristic_id) : '';
                if (midEl) midEl.value = (it.metal_id != null && it.metal_id !== '') ? String(it.metal_id) : '';
                var lineData = {
                    product: it.description || '',
                    gross_wt: it.gross_wt != null && it.gross_wt !== '' ? it.gross_wt : '',
                    final_wt: it.final_wt != null && it.final_wt !== '' ? it.final_wt : '',
                    net_wt: it.net_wt != null && it.net_wt !== '' ? it.net_wt : '',
                    less_wt: (it.less_wt != null && it.less_wt !== '') ? it.less_wt : '0',
                    purity: it.purity != null && it.purity !== '' ? it.purity : '',
                    rate: it.rate != null && it.rate !== '' ? it.rate : '',
                    amount: it.amount != null && it.amount !== '' ? it.amount : '',
                    quantity: it.quantity != null && it.quantity !== '' ? it.quantity : '1',
                    branch_id: it.branch_id || '',
                    metal: it.metal || '',
                    location: it.location || '',
                    category: '',
                    against_invoice_no: inv.against_of || '',
                    against_voucher: '',
                    product_id: it.product_id,
                    product_characteristic_id: it.product_characteristic_id,
                    metal_id: it.metal_id
                };
                var tb = document.getElementById('stockInLinesBody');
                if (tb) tb.appendChild(stockInCreateLineRow(lineData));
            }
            document.getElementById('stockInModal').classList.add('active');
        })
        .catch(function() {
            stockInClearLines();
            var tb = document.getElementById('stockInLinesBody');
            if (tb) tb.appendChild(stockInCreateLineRow({}));
            document.getElementById('stockInModal').classList.add('active');
        });
}

function closeStockInModal() {
    document.getElementById('stockInModal').classList.remove('active');
}

function closeStockInBarcodeChoiceModal() {
    var el = document.getElementById('stockInBarcodeChoiceModal');
    if (el) el.classList.remove('active');
}

function printOldJewelryStockBarcode(barcode) {
    if (!barcode) return;
    var safe = String(barcode).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    var w = window.open('', '_blank');
    if (!w) return;
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Barcode</title>');
    w.document.write('<style>body{font-family:system-ui,Segoe UI,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc}.box{text-align:center;padding:28px 36px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.06)}.lbl{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px}.code{font-size:32px;font-weight:700;letter-spacing:3px;color:#0f172a}</style></head><body>');
    w.document.write('<div class="box"><div class="lbl">Stock barcode</div><div class="code">' + safe + '</div></div>');
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    try { w.print(); } catch (e) {}
    setTimeout(function() { try { w.close(); } catch (e2) {} }, 400);
}

function stockInBarcodeQueryParams() {
    var qs = new URLSearchParams();
    var pid = document.getElementById('stockInProductId');
    var pcid = document.getElementById('stockInProductCharacteristicId');
    var mid = document.getElementById('stockInMetalId');
    var br = document.getElementById('stockInBranch');
    if (pid && pid.value) qs.set('product_id', pid.value);
    if (pcid && pcid.value) qs.set('product_characteristic_id', pcid.value);
    if (mid && mid.value) qs.set('metal_id', mid.value);
    if (br && br.value) qs.set('branch_id', br.value);
    return qs.toString();
}

/** Step 1: preview next barcode (single line) or confirm multi-line barcodes */
function beginStockInSave() {
    var invoiceId = document.getElementById('stockInInvoiceId').value;
    var itemId = document.getElementById('stockInItemId').value;
    if (!invoiceId || !itemId) return;
    if (stockInLineCount() === 0) {
        alert('Add at least one line.');
        return;
    }
    var nextLabel = document.getElementById('stockInBcNextText');
    var modal = document.getElementById('stockInBarcodeChoiceModal');
    if (stockInLineCount() > 1) {
        if (nextLabel) {
            nextLabel.textContent = stockInLineCount() + ' stock line(s) — each will get the next barcode from Product Opening (per line product / metal / branch).';
        }
        if (modal) modal.classList.add('active');
        return;
    }
    if (nextLabel) nextLabel.textContent = 'Loading next barcode…';
    if (modal) modal.classList.add('active');
    var q = stockInBarcodeQueryParams();
    fetch('ajax/get-next-old-jewelry-stock-barcode.php' + (q ? ('?' + q) : ''))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'success' && d.barcode && nextLabel) {
                nextLabel.textContent = d.barcode;
            } else if (nextLabel) {
                nextLabel.textContent = '(could not load)';
            }
        })
        .catch(function() {
            if (nextLabel) nextLabel.textContent = '(network error)';
        });
}

function confirmStockInBarcodeChoice() {
    var bcInput = document.getElementById('stockInBarcode');
    window._stockInPrintAfter = !!(document.getElementById('stockInBcPrintAfter') && document.getElementById('stockInBcPrintAfter').checked);
    closeStockInBarcodeChoiceModal();
    if (stockInLineCount() > 1) {
        saveStockInActual();
        return;
    }
    var q = stockInBarcodeQueryParams();
    fetch('ajax/get-next-old-jewelry-stock-barcode.php' + (q ? ('?' + q) : ''))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'success' && d.barcode && bcInput) {
                bcInput.value = d.barcode;
            }
            saveStockInActual();
        })
        .catch(function() {
            if (bcInput) bcInput.value = '';
            saveStockInActual();
        });
}

function saveStockInActual() {
    var invoiceId = document.getElementById('stockInInvoiceId').value;
    var itemId = document.getElementById('stockInItemId').value;
    if (!invoiceId || !itemId) return;
    var lines = collectStockInLinesFromDom();
    if (!lines.length) {
        alert('Add at least one line.');
        return;
    }
    var btn = document.getElementById('stockInSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    var formData = new FormData();
    formData.append('invoice_id', invoiceId);
    formData.append('item_id', itemId);
    formData.append('lines_json', JSON.stringify(lines));
    formData.append('group_name', document.getElementById('stockInGroupName').value);
    formData.append('comment', document.getElementById('stockInComment').value);
    formData.append('source', document.getElementById('stockInSource').value || 'scrap');
    fetch('ajax/stock-in-old-jewelry-scrap-item.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'success') {
                var doPrint = window._stockInPrintAfter;
                window._stockInPrintAfter = false;
                var barcodes = (data.barcodes && data.barcodes.length) ? data.barcodes : [];
                if (!barcodes.length && document.getElementById('stockInBarcode')) {
                    var one = document.getElementById('stockInBarcode').value.trim();
                    if (one) barcodes = [one];
                }
                closeStockInModal();
                var url = 'old-jewellery.php?tab=stocked';
                var u = new URL(window.location.href);
                if (u.searchParams.get('from_date')) url += '&from_date=' + encodeURIComponent(u.searchParams.get('from_date'));
                if (u.searchParams.get('to_date')) url += '&to_date=' + encodeURIComponent(u.searchParams.get('to_date'));
                if (doPrint && barcodes.length) {
                    var i = 0;
                    function nextPrint() {
                        if (i >= barcodes.length) {
                            window.location.href = url;
                            return;
                        }
                        printOldJewelryStockBarcode(barcodes[i]);
                        i++;
                        setTimeout(nextPrint, 550);
                    }
                    nextPrint();
                } else {
                    window.location.href = url;
                }
            } else {
                alert(data.message || 'Failed to save');
                btn.disabled = false;
                btn.textContent = 'ADD (Shift + A)';
            }
        })
        .catch(function() {
            alert('Network error');
            btn.disabled = false;
            btn.textContent = 'ADD (Shift + A)';
        });
}

function applyFiltersFromModal() {
    var fromDate = document.getElementById('filterFromDate').value;
    var toDate = document.getElementById('filterToDate').value;
    var dueA = document.getElementById('filterDueFromDate');
    var dueB = document.getElementById('filterDueToDate');
    var url = new URL(window.location.href);
    var wipe = ['from_date', 'to_date', 'due_from_date', 'due_to_date', 'branch_id', 'branch_id[]', 'customer_name', 'customer_name[]', 'assign_to', 'assign_to[]', 'jwo_status', 'jwo_status[]', 'jwo_priority', 'jwo_priority[]', 'adv_group_name', 'adv_jobwork_no', 'adv_tag_no', 'adv_gross_wt', 'adv_rfid', 'adv_against_ref', 'adv_short_desc', 'invoice_no'];
    wipe.forEach(function(k) { url.searchParams.delete(k); });
    if (fromDate) url.searchParams.set('from_date', fromDate);
    if (toDate) url.searchParams.set('to_date', toDate);
    if (dueA && dueB && dueA.value && dueB.value) {
        url.searchParams.set('due_from_date', dueA.value);
        url.searchParams.set('due_to_date', dueB.value);
    }
    document.querySelectorAll('#filterBranchList .oj-msdd-cb:checked').forEach(function(cb) {
        url.searchParams.append('branch_id[]', cb.value);
    });
    document.querySelectorAll('#filterCustomerList .oj-msdd-cb:checked').forEach(function(cb) {
        url.searchParams.append('customer_name[]', cb.value);
    });
    document.querySelectorAll('#filterAssignList .oj-msdd-cb:checked').forEach(function(cb) {
        url.searchParams.append('assign_to[]', cb.value);
    });
    document.querySelectorAll('#filterStatusList .oj-msdd-cb:checked').forEach(function(cb) {
        url.searchParams.append('jwo_status[]', cb.value);
    });
    document.querySelectorAll('#filterPriorityList .oj-msdd-cb:checked').forEach(function(cb) {
        url.searchParams.append('jwo_priority[]', cb.value);
    });
    var gn = document.getElementById('advGroupName');
    var jn = document.getElementById('advJobworkNo');
    var tn = document.getElementById('advTagNo');
    var gw = document.getElementById('advGrossWt');
    var rf = document.getElementById('advRfid');
    var ar = document.getElementById('advAgainstRef');
    var sd = document.getElementById('advShortDesc');
    if (gn && gn.value.trim()) url.searchParams.set('adv_group_name', gn.value.trim());
    if (jn && jn.value.trim()) url.searchParams.set('adv_jobwork_no', jn.value.trim());
    if (tn && tn.value.trim()) url.searchParams.set('adv_tag_no', tn.value.trim());
    if (gw && gw.value.trim()) url.searchParams.set('adv_gross_wt', gw.value.trim());
    if (rf && rf.value.trim()) url.searchParams.set('adv_rfid', rf.value.trim());
    if (ar && ar.value.trim()) url.searchParams.set('adv_against_ref', ar.value.trim());
    if (sd && sd.value.trim()) url.searchParams.set('adv_short_desc', sd.value.trim());
    url.searchParams.set('page', '1');
    ojCloseAllAdvFilterMsdd();
    closeFilterModal();
    window.location.href = url.toString();
}

function clearFiltersFromModal() {
    document.getElementById('filterFromDate').value = '<?= date('Y-m-01') ?>';
    document.getElementById('filterToDate').value = '<?= date('Y-m-t') ?>';
    resetFilterDueDates();
    var modal = document.getElementById('filterModal');
    if (modal) {
        modal.querySelectorAll('.oj-msdd .oj-msdd-cb').forEach(function(cb) { cb.checked = false; });
        modal.querySelectorAll('.oj-msdd-search').forEach(function(s) { s.value = ''; });
        modal.querySelectorAll('.oj-msdd-select-all input[type="checkbox"]').forEach(function(sa) {
            sa.checked = false;
            sa.indeterminate = false;
        });
        modal.querySelectorAll('.oj-msdd-item').forEach(function(row) { row.classList.remove('oj-msdd-hidden'); });
        modal.querySelectorAll('.oj-msdd').forEach(function(w) {
            var t = w.querySelector('.oj-msdd-toggle-text');
            if (t) t.textContent = w.getAttribute('data-placeholder') || 'Select';
        });
    }
    var gn = document.getElementById('advGroupName'); if (gn) gn.value = '';
    var jn = document.getElementById('advJobworkNo'); if (jn) jn.value = '';
    var tn = document.getElementById('advTagNo'); if (tn) tn.value = '';
    var gw = document.getElementById('advGrossWt'); if (gw) gw.value = '';
    var rf = document.getElementById('advRfid'); if (rf) rf.value = '';
    var ar = document.getElementById('advAgainstRef'); if (ar) ar.value = '';
    var sd = document.getElementById('advShortDesc'); if (sd) sd.value = '';
    ojCloseAllAdvFilterMsdd();
    closeFilterModal();
    var url = new URL(window.location.href);
    var wipe = ['from_date', 'to_date', 'due_from_date', 'due_to_date', 'branch_id', 'branch_id[]', 'customer_name', 'customer_name[]', 'assign_to', 'assign_to[]', 'jwo_status', 'jwo_status[]', 'jwo_priority', 'jwo_priority[]', 'adv_group_name', 'adv_jobwork_no', 'adv_tag_no', 'adv_gross_wt', 'adv_rfid', 'adv_against_ref', 'adv_short_desc', 'invoice_no'];
    wipe.forEach(function(k) { url.searchParams.delete(k); });
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function changePerPage() {
    const perPage = document.getElementById('perPageSelect').value;
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}

function goToPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

function setRefineryReceiveBarcodeLocked(locked) {
    var bc = document.getElementById('refineryReceiveBarcode');
    if (!bc) return;
    bc.disabled = false;
    if (locked) {
        bc.readOnly = true;
        bc.setAttribute('readonly', 'readonly');
        bc.setAttribute('aria-readonly', 'true');
        bc.classList.remove('oj-refinery-barcode-editable');
        bc.tabIndex = -1;
    } else {
        bc.readOnly = false;
        bc.removeAttribute('readonly');
        bc.setAttribute('aria-readonly', 'false');
        bc.classList.add('oj-refinery-barcode-editable');
        bc.tabIndex = 0;
    }
}

function suggestRefineryReceiveBarcode(opts) {
    opts = opts || {};
    var fromDuplicate = !!opts.fromDuplicate;
    var bc = document.getElementById('refineryReceiveBarcode');
    var err = document.getElementById('refineryReceiveErr');
    var defEl = document.getElementById('refineryReceiveBarcodeTagDefault');
    var btn = document.getElementById('refinerySuggestBarcodeBtn');
    if (!bc) return;
    var base = '';
    if (fromDuplicate) {
        base = (bc.value || '').trim();
    }
    if (!base && defEl) {
        base = (defEl.value || '').trim();
    }
    if (!base) {
        base = (bc.value || '').trim();
    }
    if (!base) {
        if (err) {
            err.textContent = 'No tag number to start from.';
            err.style.display = 'block';
            err.classList.add('text-danger');
            err.classList.remove('text-secondary');
        }
        return;
    }
    if (btn) btn.disabled = true;
    var fd = new FormData();
    fd.append('base', base);
    fetch('ajax/suggest-old-jewellery-refinery-barcode.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (btn) btn.disabled = false;
            if (!data || !data.ok) {
                if (err) {
                    err.textContent = (data && data.message) ? data.message : 'Could not suggest a barcode.';
                    err.style.display = 'block';
                    err.classList.add('text-danger');
                    err.classList.remove('text-secondary');
                }
                return;
            }
            bc.value = data.barcode;
            setRefineryReceiveBarcodeLocked(false);
            if (err) {
                err.textContent = fromDuplicate
                    ? 'That tag is already in Old Jewellery stock. A new barcode was filled in—you can edit it, then save again.'
                    : 'Unique barcode filled in. You can edit it before saving.';
                err.style.display = 'block';
                err.classList.remove('text-danger');
                err.classList.add('text-secondary');
            }
            try {
                bc.focus();
                bc.select();
            } catch (eSel) {}
        })
        .catch(function () {
            if (btn) btn.disabled = false;
            if (err) {
                err.textContent = 'Network error while suggesting barcode.';
                err.style.display = 'block';
                err.classList.add('text-danger');
                err.classList.remove('text-secondary');
            }
        });
}

function openRefineryReceiveModal(joiId, jwoId, suggestedBarcode) {
    var joiEl = document.getElementById('refineryReceiveJoiId');
    var jwoEl = document.getElementById('refineryReceiveJwoId');
    var bc = document.getElementById('refineryReceiveBarcode');
    var err = document.getElementById('refineryReceiveErr');
    var defEl = document.getElementById('refineryReceiveBarcodeTagDefault');
    var m = document.getElementById('refineryReceiveModal');
    if (!joiEl || !jwoEl || !bc || !m) return;
    joiEl.value = joiId || '';
    jwoEl.value = jwoId || '';
    var sug = (suggestedBarcode != null && suggestedBarcode !== '') ? String(suggestedBarcode).trim() : '';
    bc.value = sug;
    if (defEl) {
        defEl.value = sug;
    }
    if (err) {
        err.style.display = 'none';
        err.textContent = '';
        err.classList.remove('text-secondary');
        err.classList.add('text-danger');
    }
    try {
        document.body.style.userSelect = '';
        document.documentElement.style.userSelect = '';
    } catch (eUs) {}
    setRefineryReceiveBarcodeLocked(true);
    m.style.display = '';
    m.classList.add('active');
    setTimeout(function () {
        var submitBtn = document.getElementById('refineryReceiveSubmitBtn');
        try {
            if (submitBtn) {
                submitBtn.focus();
            }
        } catch (eF) {}
    }, 50);
}

function closeRefineryReceiveModal() {
    var m = document.getElementById('refineryReceiveModal');
    if (m) {
        m.classList.remove('active');
        m.style.display = '';
    }
}

function submitRefineryReceiveStock() {
    var err = document.getElementById('refineryReceiveErr');
    var btn = document.getElementById('refineryReceiveSubmitBtn');
    var joi = document.getElementById('refineryReceiveJoiId');
    var jwo = document.getElementById('refineryReceiveJwoId');
    var bc = document.getElementById('refineryReceiveBarcode');
    if (!joi || !jwo || !bc || !btn) return;
    if (err) {
        err.style.display = 'none';
        err.textContent = '';
    }
    var barcode = (bc.value || '').trim();
    if (!barcode) {
        if (err) {
            err.textContent = 'Barcode is required.';
            err.style.display = 'block';
        }
        return;
    }
    btn.disabled = true;
    var fd = new FormData();
    fd.append('jobwork_order_item_id', joi.value);
    fd.append('jobwork_order_id', jwo.value);
    fd.append('barcode', barcode);
    fetch('ajax/save-old-jewellery-refinery-receive-stock.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            if (!data || !data.ok) {
                if (err) {
                    err.textContent = (data && data.message) ? data.message : 'Save failed.';
                    err.style.display = 'block';
                    err.classList.add('text-danger');
                    err.classList.remove('text-secondary');
                }
                var msg = (data && data.message) ? String(data.message) : '';
                if (msg.indexOf('already exists') !== -1) {
                    suggestRefineryReceiveBarcode({ fromDuplicate: true });
                }
                return;
            }
            closeRefineryReceiveModal();
            var url = new URL(window.location.href);
            url.searchParams.set('tab', 'stocked');
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        })
        .catch(function () {
            btn.disabled = false;
            if (err) {
                err.textContent = 'Network error.';
                err.style.display = 'block';
            }
        });
}

function exportToExcel() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', 'excel');
    window.open(url.toString(), '_blank');
    alert('Excel export functionality will be implemented');
}

function exportToPDF() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', 'pdf');
    window.open(url.toString(), '_blank');
    alert('PDF export functionality will be implemented');
}

function printTable() {
    window.print();
}

/** Print a single data row with the table header (respects current column visibility styles). */
function printOldJewelleryRow(btn) {
    var tr = btn && btn.closest ? btn.closest('tr') : null;
    if (!tr) return;
    var table = document.getElementById('oldJewelleryTable');
    if (!table) return;
    var thead = table.querySelector('thead');
    if (!thead) return;
    var w = window.open('', '_blank');
    if (!w) return;
    var doc = w.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Old Jewellery</title>');
    doc.write('<style>body{font-family:Roboto,Segoe UI,sans-serif;padding:16px;color:#1e293b}table{border-collapse:collapse;width:100%;font-size:12px}th,td{border:1px solid #e2e8f0;padding:8px;text-align:left}th{background:#f8fafc;font-weight:600}.text-right{text-align:right}</style></head><body>');
    doc.write('<h3 style="margin:0 0 12px;font-size:16px">Old Jewellery - Scrap</h3>');
    doc.write('<table><thead>' + thead.innerHTML + '</thead><tbody>' + tr.outerHTML + '</tbody></table>');
    doc.write('</body></html>');
    doc.close();
    w.focus();
    try {
        w.print();
    } finally {
        w.close();
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'filterModal') closeFilterModal();
    if (!e.target.closest('.oj-columns-wrap')) closeColumnsModal();
    if (e.target.id === 'stockInModal') closeStockInModal();
    if (e.target.id === 'refineryReceiveModal') closeRefineryReceiveModal();
    if (e.target.id === 'stockInBarcodeChoiceModal') closeStockInBarcodeChoiceModal();
    if (!e.target.closest('#filterModal .oj-msdd')) {
        ojCloseAllAdvFilterMsdd();
    }
});

window.addEventListener('resize', function() {
    if (document.getElementById('oldJwlColumnsDropdown') &&
        document.getElementById('oldJwlColumnsDropdown').classList.contains('show')) {
        positionOjColumnsDropdown();
    }
});

// Stock In modal: Shift + A to submit (matches GoldMatrix-style ADD shortcut)
document.addEventListener('keydown', function(e) {
    var m = document.getElementById('stockInModal');
    if (!m || !m.classList.contains('active')) return;
    if (!e.shiftKey || (e.key !== 'a' && e.key !== 'A')) return;
    e.preventDefault();
    var btn = document.getElementById('stockInSaveBtn');
    if (btn && !btn.disabled) beginStockInSave();
});

// Apply column visibility on page load
$(document).ready(function() {
    initOjAdvanceFilterMsdd();
    applyColumnOrderFromStorage();
    attachOjColumnDragHandles();
    initOjColumnDragDrop();
    applyColumnVisibility();
    if (document.getElementById('refineTable')) {
        applyRefineColumnOrderFromStorage();
        attachRefineColumnDragHandles();
        initRefineColumnDragDrop();
    }
    if (document.getElementById('receivedRefineTable')) {
        applyReceivedRefineColumnOrderFromStorage();
        attachReceivedRefineColumnDragHandles();
        initReceivedRefineColumnDragDrop();
    }
    document.addEventListener('click', function (e) {
        var recv = e.target && e.target.closest ? e.target.closest('.oj-refinery-receive-btn') : null;
        if (!recv) return;
        e.preventDefault();
        openRefineryReceiveModal(recv.getAttribute('data-joi-id'), recv.getAttribute('data-jwo-id'), recv.getAttribute('data-tag-no'));
    });
    setTimeout(function () {
        if (typeof window.feather !== 'undefined' && typeof feather.replace === 'function') {
            try {
                feather.replace();
            } catch (e) {}
        }
    }, 0);
    var colTgl = document.getElementById('oldJwlColumnsToggle');
    if (colTgl) colTgl.addEventListener('click', toggleColumnsPanel);
    var add1 = document.getElementById('stockInAddLineBtn');
    var add2 = document.getElementById('stockInToolbarAddProduct');
    if (add1) add1.addEventListener('click', function(e) { e.preventDefault(); stockInAddEmptyLine(); });
    if (add2) add2.addEventListener('click', function(e) { e.preventDefault(); stockInAddEmptyLine(); });

    (function initOjScrapRowSelection() {
        var master = document.getElementById('ojScrapSelectAll');
        var tbl = document.getElementById('oldJewelleryTable');
        if (!master || !tbl) return;
        function rowBoxes() {
            return tbl.querySelectorAll('tbody tr td[data-column="active"] input.oj-scrap-row-cb');
        }
        function rowBoxesSelectable() {
            return tbl.querySelectorAll('tbody tr td[data-column="active"] input.oj-scrap-row-cb:not(:disabled)');
        }
        function syncJobworkRefineryButton() {
            var btn = document.getElementById('ojBtnJobworkFromScrap');
            if (!btn) return;
            var anyChecked = !!tbl.querySelector('tbody tr td[data-column="active"] input.oj-scrap-row-cb:checked:not(:disabled)');
            btn.disabled = !anyChecked;
            btn.title = anyChecked ? '' : 'Select one or more scrap lines';
        }
        function syncMaster() {
            var boxes = rowBoxes();
            var sel = rowBoxesSelectable();
            if (boxes.length === 0) {
                master.checked = false;
                master.indeterminate = false;
                master.disabled = true;
                syncJobworkRefineryButton();
                return;
            }
            if (sel.length === 0) {
                master.checked = false;
                master.indeterminate = false;
                master.disabled = true;
                syncJobworkRefineryButton();
                return;
            }
            master.disabled = false;
            var n = 0;
            sel.forEach(function(b) { if (b.checked) n++; });
            master.checked = n === sel.length && sel.length > 0;
            master.indeterminate = n > 0 && n < sel.length;
            syncJobworkRefineryButton();
        }
        master.addEventListener('change', function() {
            rowBoxesSelectable().forEach(function(cb) { cb.checked = master.checked; });
            master.indeterminate = false;
            syncJobworkRefineryButton();
        });
        tbl.addEventListener('change', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('oj-scrap-row-cb')) {
                syncMaster();
            }
        });
        syncMaster();
    })();

    var ojJwBtn = document.getElementById('ojBtnJobworkFromScrap');
    if (ojJwBtn) {
        ojJwBtn.addEventListener('click', function () {
            var tbl = document.getElementById('oldJewelleryTable');
            if (!tbl) return;
            var cbs = tbl.querySelectorAll('tbody tr td[data-column="active"] input.oj-scrap-row-cb:checked:not(:disabled)');
            if (!cbs.length) {
                alert('Select at least one scrap line.');
                return;
            }
            var invId = null;
            var itemIds = [];
            for (var i = 0; i < cbs.length; i++) {
                var cb = cbs[i];
                if (cb.disabled) {
                    alert('One or more selected rows are locked (refinery job work order already exists for that invoice).');
                    return;
                }
                if (cb.getAttribute('data-allow-jobwork') !== '1') {
                    alert('Job work / refinery is only available for Scrap Invoice and Purchase Invoice lines, and not when a refinery order already exists.');
                    return;
                }
                var iid = parseInt(cb.getAttribute('data-invoice-id') || '0', 10);
                if (!iid) {
                    alert('Invalid invoice for selected row.');
                    return;
                }
                if (invId === null) {
                    invId = iid;
                } else if (invId !== iid) {
                    alert('Select rows from the same invoice only.');
                    return;
                }
                var itemId = parseInt(cb.getAttribute('data-item-id') || cb.value || '0', 10);
                if (itemId) {
                    itemIds.push(itemId);
                }
            }
            if (!invId || !itemIds.length) {
                alert('Could not read selected lines.');
                return;
            }
            ojJwBtn.disabled = true;
            var fd = new FormData();
            fd.append('scrap_invoice_id', String(invId));
            fd.append('scrap_item_ids', itemIds.join(','));
            fetch('ajax/create-sale-order-from-old-jewelry-scrap.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    ojJwBtn.disabled = false;
                    if (data && data.status === 'success' && data.sale_order_id) {
                        var _ojQs = new URLSearchParams(window.location.search);
                        var _ojFd = _ojQs.get('from_date') || '';
                        var _ojTd = _ojQs.get('to_date') || '';
                        var _jwoHref = 'jobwork-order.php?sale_order_id=' + encodeURIComponent(data.sale_order_id) + '&from_oj_scrap=1';
                        if (_ojFd) {
                            _jwoHref += '&oj_from_date=' + encodeURIComponent(_ojFd);
                        }
                        if (_ojTd) {
                            _jwoHref += '&oj_to_date=' + encodeURIComponent(_ojTd);
                        }
                        window.location.href = _jwoHref;
                    } else {
                        alert((data && data.message) ? data.message : 'Could not create sale order.');
                    }
                })
                .catch(function () {
                    ojJwBtn.disabled = false;
                    alert('Request failed.');
                });
        });
    }
});
</script>

</body>
</html>
