<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/branch_profile_schema.php';
require_once __DIR__ . '/includes/auragold_stock_journal_delete.php';
require_once __DIR__ . '/includes/auragold_product_opening_field_helpers.php';
require_once __DIR__ . '/includes/auragold_metal_opening_fields_schema.php';

$stock_journal_effective_branch_id = function_exists('auragold_effective_branch_id') ? auragold_effective_branch_id() : 0;
if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_ensure_table_branch_id_column')) {
    auragold_ensure_table_branch_id_column($conn, 'tbl_purchase_invoices', 'id');
}
if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_ensure_opening_stock_weight_precision')) {
    auragold_ensure_opening_stock_weight_precision($conn);
}

$sj_branch_label = '';
if ($stock_journal_effective_branch_id > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
    $sjbr = getRecordMaster('SELECT name FROM tbl_branches WHERE id = ' . (int) $stock_journal_effective_branch_id . ' LIMIT 1');
    if ($sjbr && !empty($sjbr['name'])) {
        $sj_branch_label = trim((string) $sjbr['name']);
    }
}

require_once __DIR__ . '/includes/dashboard_currency_display.php';
$sj_currency_sql_fallback = 'AED';
if (isset($conn) && $conn instanceof mysqli) {
    $sj_currency_sql_fallback = mysqli_real_escape_string(
        $conn,
        auragold_branch_profile_currency_display_label($conn, (!empty($conn_master) && $conn_master instanceof mysqli) ? $conn_master : null)
    );
}

$purchase_branch_sql = '';
$purchase_branch_sql_pi = '';
if ($stock_journal_effective_branch_id > 0 && isset($conn) && $conn instanceof mysqli && function_exists('auragold_tbl_has_column')
    && auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'branch_id')) {
    $b = (int) $stock_journal_effective_branch_id;
    $purchase_branch_sql = ' AND branch_id = ' . $b;
    $purchase_branch_sql_pi = ' AND pi.branch_id = ' . $b;
}

$po_branch_sql = '';
if ($stock_journal_effective_branch_id > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
    $sb = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $stock_journal_effective_branch_id . ' LIMIT 1');
    if ($sb) {
        if ((int) ($sb['main_branch_id'] ?? 0) > 0) {
            $po_branch_sql = ' AND pc.branch_id = ' . (int) $stock_journal_effective_branch_id;
        } else {
            $bidm = (int) $stock_journal_effective_branch_id;
            $po_branch_sql = ' AND (pc.branch_id = ' . $bidm . ' OR pc.branch_id IS NULL OR pc.branch_id = 0)';
        }
    }
}

// Get filters
$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? esc($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? esc($_GET['to_date']) : '';
$customer_filter = isset($_GET['customer']) ? esc($_GET['customer']) : '';
$date_type = isset($_GET['date_type']) ? esc($_GET['date_type']) : 'source';
if (!in_array($date_type, ['source', 'sj'], true)) {
    $date_type = 'source';
}
$gross_wt_filter = isset($_GET['gross_wt']) ? trim((string) $_GET['gross_wt']) : '';
$metal_filter = isset($_GET['metal_id']) ? (int) $_GET['metal_id'] : 0;
$product_filter = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
$source_filter = isset($_GET['source']) ? esc($_GET['source']) : '';
$sj_invoice_no_filter = isset($_GET['sj_invoice_no']) ? esc($_GET['sj_invoice_no']) : '';
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$account_no_filter = isset($_GET['account_no']) ? esc($_GET['account_no']) : '';
// Voucher type: purchase_invoice (default), product_opening, jobwork_invoice, purchase_quotation, broken_entry
$voucher = isset($_GET['voucher']) ? esc($_GET['voucher']) : 'purchase_invoice';
if (!in_array($voucher, ['purchase_invoice', 'product_opening', 'jobwork_invoice', 'purchase_quotation', 'broken_entry'])) {
    $voucher = 'purchase_invoice';
}

$sj_metals = getList("SELECT id, COALESCE(NULLIF(TRIM(display_name), ''), system_name, '') AS name FROM tbl_metal WHERE status = 1 ORDER BY display_name ASC");
require_once __DIR__ . '/includes/auragold_product_metal_tab_match.php';
$sj_products = [];
if ($metal_filter > 0) {
    $sj_product_where = 'p.status = 1 AND pc.status = 1' . auragold_sql_pc_metal_for_product_list($metal_filter);
    $sj_products = getList("
        SELECT DISTINCT p.id, p.name
        FROM tbl_products p
        INNER JOIN tbl_product_characteristics pc ON p.id = pc.product_id
        WHERE $sj_product_where
        ORDER BY p.name ASC
        LIMIT 1000
    ");
}
if (!is_array($sj_products)) {
    $sj_products = [];
}
$sj_branch_groups = [];
$sj_branches = [];
$sj_branch_allowed_ids = [];
require_once __DIR__ . '/includes/user_management_schema.php';
if (function_exists('auragold_um_branch_picker_groups')) {
    $sj_branch_groups = auragold_um_branch_picker_groups(
        (isset($conn) && $conn instanceof mysqli) ? $conn : null,
        (!empty($conn_master) && $conn_master instanceof mysqli) ? $conn_master : null
    );
}
if (!is_array($sj_branch_groups)) {
    $sj_branch_groups = [];
}
foreach ($sj_branch_groups as $sj_grp) {
    $sj_main = is_array($sj_grp['main'] ?? null) ? $sj_grp['main'] : null;
    if ($sj_main && !empty($sj_main['id'])) {
        $sj_mid = (int) $sj_main['id'];
        $sj_mname = trim((string) ($sj_main['name'] ?? ''));
        if ($sj_mid > 0 && $sj_mname !== '') {
            $sj_branches[] = ['id' => $sj_mid, 'name' => $sj_mname, 'is_main' => true];
            $sj_branch_allowed_ids[$sj_mid] = $sj_mid;
        }
    }
    foreach (is_array($sj_grp['subs'] ?? null) ? $sj_grp['subs'] : [] as $sj_sub) {
        if (!is_array($sj_sub) || empty($sj_sub['id'])) {
            continue;
        }
        $sj_sid = (int) $sj_sub['id'];
        $sj_sname = trim((string) ($sj_sub['name'] ?? ''));
        if ($sj_sid > 0 && $sj_sname !== '') {
            $sj_branches[] = ['id' => $sj_sid, 'name' => $sj_sname, 'is_main' => false];
            $sj_branch_allowed_ids[$sj_sid] = $sj_sid;
        }
    }
}
if ($branch_filter > 0 && !isset($sj_branch_allowed_ids[$branch_filter])) {
    $branch_filter = 0;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// Initialize data array
$data = [];
$total_records = 0;
$total_pages = 1;
$customers = getList("SELECT DISTINCT supplier_name FROM tbl_purchase_invoices WHERE supplier_name IS NOT NULL AND supplier_name != ''" . $purchase_branch_sql . " ORDER BY supplier_name ASC");
if (!is_array($customers)) {
    $customers = [];
}

// Build WHERE clause for invoices table
$where_clause = "1=1";
if (!empty($search)) {
    $where_clause .= " AND (invoice_no LIKE '%" . esc($search) . "%' OR supplier_name LIKE '%" . esc($search) . "%')";
}
if (!empty($from_date)) {
    $where_clause .= " AND invoice_date >= '" . esc($from_date) . "'";
}
if (!empty($to_date)) {
    $where_clause .= " AND invoice_date <= '" . esc($to_date) . "'";
}
if (!empty($customer_filter)) {
    $where_clause .= " AND supplier_name = '" . esc($customer_filter) . "'";
}
if (!empty($source_filter)) {
    $where_clause .= " AND invoice_no LIKE '%" . esc($source_filter) . "%'";
}
if ($branch_filter > 0 && isset($conn) && $conn instanceof mysqli && function_exists('auragold_tbl_has_column')
    && auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'branch_id')) {
    $purchase_branch_sql = ' AND branch_id = ' . (int) $branch_filter;
    $purchase_branch_sql_pi = ' AND pi.branch_id = ' . (int) $branch_filter;
}
$pi_item_extra_sql = '';
if ($metal_filter > 0) {
    $pi_item_extra_sql .= ' AND pc.metal_id = ' . (int) $metal_filter;
}
if ($product_filter > 0) {
    $pi_item_extra_sql .= ' AND pii.product_id = ' . (int) $product_filter;
}
if ($gross_wt_filter !== '' && is_numeric($gross_wt_filter)) {
    $pi_item_extra_sql .= ' AND COALESCE(pii.gross_weight, 0) >= ' . (float) $gross_wt_filter;
}
if (!empty($account_no_filter)) {
    $pi_item_extra_sql .= " AND pi.invoice_no LIKE '%" . esc($account_no_filter) . "%'";
}

$pi_show_in_stock_journal_sql = '';
if ($voucher === 'purchase_invoice'
    && isset($conn) && $conn instanceof mysqli
    && function_exists('auragold_tbl_has_column')
    && auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'show_in_stock_journal')) {
    $pi_show_in_stock_journal_sql = ' AND pi.show_in_stock_journal = 1';
}

try {
    if ($voucher === 'product_opening') {
        // Product Opening: rows from tbl_product_characteristics with opening data (branch-scoped)
        // Show opening rows when they have weight, value, OR quantity (qty-only lines e.g. gross wt 0, qty 5).
        $po_where = "pc.status = 1 AND p.status = 1 AND (COALESCE(pc.opening_weight, 0) > 0 OR COALESCE(pc.final_weight, 0) > 0 OR COALESCE(pc.value, 0) > 0 OR COALESCE(pc.opening_qty, 0) > 0)";
        if (!empty($search)) {
            $po_where .= " AND (p.name LIKE '%" . esc($search) . "%' OR p.alternate_name LIKE '%" . esc($search) . "%' OR pc.barcode LIKE '%" . esc($search) . "%')";
        }
        if ($metal_filter > 0) {
            $po_where .= ' AND pc.metal_id = ' . (int) $metal_filter;
        }
        if ($product_filter > 0) {
            $po_where .= ' AND p.id = ' . (int) $product_filter;
        }
        if ($gross_wt_filter !== '' && is_numeric($gross_wt_filter)) {
            $po_where .= ' AND COALESCE(pc.opening_weight, 0) >= ' . (float) $gross_wt_filter;
        }
        if ($branch_filter > 0) {
            $po_where .= ' AND pc.branch_id = ' . (int) $branch_filter;
        } elseif ($po_branch_sql !== '') {
            $po_where .= $po_branch_sql;
        }
        $po_count_sql = "SELECT COUNT(*) as total FROM tbl_product_characteristics pc INNER JOIN tbl_products p ON pc.product_id = p.id WHERE $po_where";
        $po_total = getRecord($po_count_sql);
        $total_records = $po_total ? (int)$po_total['total'] : 0;
        $total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
        $data = [];
        if ($total_records > 0) {
            // Match SJ lines by characteristic id, or by product+metal when opening save recreated pc rows (legacy bug).
            $po_sj_po_link = "(sj_po.product_characteristic_id = pc.id OR (sj_po.product_id = p.id AND sj_po.metal_id = pc.metal_id))";
            $po_sj_link = "(sj.product_characteristic_id = pc.id OR (sj.product_id = p.id AND sj.metal_id = pc.metal_id))";
            $po_query = "
                SELECT 
                    0 as invoice_id,
                    '' as invoice_no,
                    '' as supplier_name,
                    NULL as invoice_date,
                    1 as status,
                    '$sj_currency_sql_fallback' as currency,
                    pc.id as item_id,
                    p.id as product_id,
                    p.name as product_name,
                    pc.barcode,
                    COALESCE(pc.opening_qty, 0) + (
                        SELECT COALESCE(SUM(sj_po.quantity), 0)
                        FROM tbl_stock_journal sj_po
                        WHERE $po_sj_po_link
                          AND sj_po.status = 'active'
                          AND (sj_po.item_id IS NULL OR sj_po.item_id = 0)
                          AND (sj_po.comment IS NULL OR sj_po.comment NOT LIKE 'auragold_doc|src=pi|%')
                    ) as quantity,
                    p.name as full_product_name,
                    COALESCE(pc.metal_id, 0) as metal_id,
                    COALESCE(m.display_name, 'N/A') as metal_name,
                    'N/A' as location_name,
                    COALESCE(pc.branch_id, 0) as branch_id,
                    '' as branch_name,
                    COALESCE(pc.opening_weight, 0) as gross_weight,
                    COALESCE(pc.final_weight, 0) as net_weight,
                    COALESCE(pc.opening_purity, 0) as purity,
                    COALESCE(pc.rate, 0) as rate,
                    COALESCE(pc.value, 0) as net_amount,
                    COALESCE(pc.value, 0) as purchase_amount,
                    0.00 as stockjournal_amount,
                    (
                        SELECT COALESCE(SUM(sj_po.quantity), 0)
                        FROM tbl_stock_journal sj_po
                        WHERE $po_sj_po_link
                          AND sj_po.status = 'active'
                          AND (sj_po.item_id IS NULL OR sj_po.item_id = 0)
                          AND (sj_po.comment IS NULL OR sj_po.comment NOT LIKE 'auragold_doc|src=pi|%')
                    ) as production_qty,
                    (
                        SELECT COALESCE(SUM(COALESCE(sj_po.gross_weight, sj_po.net_weight, 0)), 0)
                        FROM tbl_stock_journal sj_po
                        WHERE $po_sj_po_link
                          AND sj_po.status = 'active'
                          AND (sj_po.item_id IS NULL OR sj_po.item_id = 0)
                          AND (sj_po.comment IS NULL OR sj_po.comment NOT LIKE 'auragold_doc|src=pi|%')
                    ) as production_wt,
                    COALESCE(pc.opening_qty, 0) as available_qty,
                    GREATEST(
                        COALESCE(pc.opening_weight, 0) - (
                            SELECT COALESCE(SUM(COALESCE(sj_po.gross_weight, sj_po.net_weight, 0)), 0)
                            FROM tbl_stock_journal sj_po
                            WHERE $po_sj_po_link
                              AND sj_po.status = 'active'
                              AND (sj_po.item_id IS NULL OR sj_po.item_id = 0)
                              AND (sj_po.comment IS NULL OR sj_po.comment NOT LIKE 'auragold_doc|src=pi|%')
                        ),
                        0
                    ) as available_wt,
                    COALESCE(pc.opening_qty, 0) + (
                        SELECT COALESCE(SUM(sj_po.quantity), 0)
                        FROM tbl_stock_journal sj_po
                        WHERE $po_sj_po_link
                          AND sj_po.status = 'active'
                          AND (sj_po.item_id IS NULL OR sj_po.item_id = 0)
                          AND (sj_po.comment IS NULL OR sj_po.comment NOT LIKE 'auragold_doc|src=pi|%')
                    ) as total_qty,
                    0.00 as stone_wt,
                    (CASE WHEN EXISTS (SELECT 1 FROM tbl_stock_journal sj WHERE $po_sj_link AND (sj.item_id IS NULL OR sj.item_id = 0) AND sj.status = 'active' AND (sj.comment IS NULL OR sj.comment NOT LIKE 'auragold_doc|src=pi|%')) THEN 1 ELSE 0 END) as has_stock_journal,
                    'product_opening' as voucher_type,
                    pc.id as characteristic_id
                FROM tbl_product_characteristics pc
                INNER JOIN tbl_products p ON pc.product_id = p.id
                LEFT JOIN tbl_metal m ON pc.metal_id = m.id
                WHERE $po_where
                ORDER BY p.name ASC, pc.id ASC
                LIMIT $per_page OFFSET $offset
            ";
            $data = getList($po_query);
            if ($total_records > 0 && empty($data) && isset($conn) && $conn instanceof mysqli && mysqli_error($conn)) {
                error_log('Stock Journal product_opening list query failed: ' . mysqli_error($conn));
            }
            if (!empty($data) && function_exists('auragold_product_opening_has_orphan_stock')) {
                foreach ($data as &$po_row) {
                    if ((int) ($po_row['has_stock_journal'] ?? 0) > 0) {
                        continue;
                    }
                    $po_cid = (int) ($po_row['characteristic_id'] ?? $po_row['item_id'] ?? 0);
                    if ($po_cid > 0 && auragold_product_opening_has_orphan_stock($conn, $po_cid)) {
                        $po_row['has_stock_journal'] = 1;
                    }
                }
                unset($po_row);
            }
        }
        $customers = [];
    } elseif ($voucher === 'jobwork_invoice' || $voucher === 'purchase_quotation' || $voucher === 'broken_entry') {
        // Placeholder: no tables yet, show empty
        $data = [];
        $total_records = 0;
        $total_pages = 1;
        $customers = [];
    } else {
        // purchase_invoice (default)
        // Line visible when it has qty, weight, amount, barcode, or product (do not hide zero-total imitation lines).
        // Use OR on metal_qty/quantity (not COALESCE) so metal_qty=0 does not hide quantity>0 rows.
        $pii_visible_sql = " AND (pii.id IS NULL"
            . " OR COALESCE(pii.metal_qty, 0) > 0"
            . " OR COALESCE(pii.quantity, 0) > 0"
            . " OR COALESCE(pii.gross_weight, 0) > 0"
            . " OR COALESCE(pii.net_weight, 0) > 0"
            . " OR COALESCE(pii.final_weight, 0) > 0"
            . " OR COALESCE(pii.net_amount, 0) > 0"
            . " OR COALESCE(pii.amount, 0) > 0"
            . " OR COALESCE(pii.purchase_amount, 0) > 0"
            . " OR TRIM(IFNULL(pii.barcode, '')) <> ''"
            . " OR COALESCE(pii.product_id, 0) > 0)";

        // Get unique customers for filter (already loaded above)

        // Simplified query - get invoices first, join items if they exist
    // This ensures invoices always show even if they have no items
     $query = "
        SELECT 
            pi.id as invoice_id,
            pi.invoice_no,
            pi.supplier_name,
            pi.invoice_date,
            pi.status,
            CASE
                WHEN pi.currency IS NOT NULL AND TRIM(pi.currency) <> '' THEN TRIM(pi.currency)
                ELSE '$sj_currency_sql_fallback'
            END as currency,
            pii.id as item_id,
            pii.product_id,
            pii.product_name,
            pii.barcode,
            COALESCE(NULLIF(pii.metal_qty, 0), pii.quantity, 0) as quantity,
            COALESCE(p.name, pii.product_name, 'N/A') as full_product_name,
            COALESCE(pc.metal_id, 0) as metal_id,
            COALESCE(m.display_name, 'N/A') as metal_name,
            'N/A' as location_name,
            COALESCE(pi.branch_id, 0) as branch_id,
            '' as branch_name,
            COALESCE(pii.gross_weight, 0) as gross_weight,
            COALESCE(pii.net_weight, 0) as net_weight,
            COALESCE(pii.purity, 0) as purity,
            COALESCE(pii.rate, 0) as rate,
            COALESCE(pii.net_amount, 0) as net_amount,
            COALESCE(NULLIF(pii.purchase_amount, 0), NULLIF(pii.net_amount, 0), pii.amount, 0) as purchase_amount,
            0.00 as stockjournal_amount,
            COALESCE(SUM(sj.quantity), 0) as production_qty,
            COALESCE(SUM(COALESCE(sj.gross_weight, sj.net_weight, 0)), 0) as production_wt,
            GREATEST(COALESCE(NULLIF(pii.metal_qty, 0), pii.quantity, 0) - COALESCE(SUM(sj.quantity), 0), 0) as available_qty,
            GREATEST(COALESCE(pii.gross_weight, 0) - COALESCE(SUM(COALESCE(sj.gross_weight, sj.net_weight, 0)), 0), 0) as available_wt,
            COALESCE(NULLIF(pii.metal_qty, 0), pii.quantity, 0) as total_qty,
            0.00 as stone_wt,
            CASE WHEN COUNT(DISTINCT sj.id) > 0 THEN 1 ELSE 0 END as has_stock_journal,
            'purchase_invoice' as voucher_type,
            COALESCE(pii.product_characteristic_id, 0) as characteristic_id
        FROM tbl_purchase_invoices pi
        LEFT JOIN tbl_purchase_invoice_items pii ON pi.id = pii.invoice_id
        LEFT JOIN tbl_products p ON pii.product_id = p.id
        LEFT JOIN tbl_product_characteristics pc ON pii.product_characteristic_id = pc.id
        LEFT JOIN tbl_metal m ON pc.metal_id = m.id
        LEFT JOIN tbl_stock_journal sj ON sj.item_id = pii.id AND sj.status = 'active'
            AND (sj.comment IS NULL OR sj.comment NOT LIKE 'auragold_doc|src=pi|%')
        WHERE $where_clause$purchase_branch_sql_pi$pi_show_in_stock_journal_sql$pii_visible_sql$pi_item_extra_sql
        GROUP BY pi.id, pii.id
        ORDER BY pi.id DESC, pii.id ASC
    ";

    // Count invoice line rows (not invoices only) so qty-only lines paginate correctly.
    $count_query = "
        SELECT COUNT(*) as total
        FROM tbl_purchase_invoices pi
        LEFT JOIN tbl_purchase_invoice_items pii ON pi.id = pii.invoice_id
        WHERE $where_clause$purchase_branch_sql_pi$pi_show_in_stock_journal_sql$pii_visible_sql$pi_item_extra_sql
    ";
    
    $total_record = getRecord($count_query);
    $total_records = $total_record ? (int)$total_record['total'] : 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;

    // Get paginated data
    if ($total_records > 0) {
        $data = getList($query . " LIMIT $per_page OFFSET $offset");
        // If getList returns false or empty, try simple invoice query
        if ($data === false || empty($data)) {
            $simple_query = "
                SELECT 
                    pi.id as invoice_id,
                    pi.invoice_no,
                    pi.supplier_name,
                    pi.invoice_date,
                    pi.status,
                    CASE
                        WHEN pi.currency IS NOT NULL AND TRIM(pi.currency) <> '' THEN TRIM(pi.currency)
                        ELSE '$sj_currency_sql_fallback'
                    END as currency,
                    NULL as item_id,
                    NULL as product_id,
                    NULL as product_name,
                    NULL as barcode,
                    NULL as quantity,
                    'N/A' as full_product_name,
                    0 as metal_id,
                    'N/A' as metal_name,
                    'N/A' as location_name,
                    COALESCE(pi.branch_id, 0) as branch_id,
                    '' as branch_name,
                    0 as gross_weight,
                    0 as net_weight,
                    0 as purity,
                    0 as rate,
                    0 as net_amount,
                    0 as purchase_amount,
                    0.00 as stockjournal_amount,
                    0.00 as production_qty,
                    0.00 as production_wt,
                    0.00 as available_qty,
                    0.00 as available_wt,
                    0.00 as total_qty,
                    0.00 as stone_wt,
                    0 as has_stock_journal,
                    'purchase_invoice' as voucher_type,
                    0 as characteristic_id
                FROM tbl_purchase_invoices pi
                WHERE $where_clause$purchase_branch_sql$pi_show_in_stock_journal_sql
                ORDER BY pi.invoice_date DESC, pi.id DESC
                LIMIT $per_page OFFSET $offset
            ";
            $data = getList($simple_query);
        }
    } else {
        $data = [];
    }
    } // end else purchase_invoice
    if (!empty($data) && function_exists('auragold_enrich_rows_branch_name_from_registry')) {
        auragold_enrich_rows_branch_name_from_registry($data);
    }
} catch (Exception $e) {
    // Log error and show empty data
    error_log("Stock Journal Error: " . $e->getMessage());
    $data = [];
    $total_records = 0;
}

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Stock Journal - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
</head>

<style>
html, body {
    overflow-x: hidden !important;
    height: 100vh;
    background: #f4f6fb;
    /* font-family: 'Segoe UI', Arial, sans-serif; */
}

.layout-content {
    height: calc(100vh - 60px);
    overflow: hidden;
}

.container-fluid {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 0;
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
    position: relative;
    z-index: 40;
    overflow: visible;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    position: relative;
    z-index: 50;
    overflow: visible;
}

.page-header-actions .dropdown {
    position: relative;
}

.page-header-actions .dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    display: none;
    min-width: 170px;
    margin-top: 6px;
    z-index: 1200;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
    padding: 6px 0;
}

.page-header-actions .dropdown-menu.show {
    display: block;
}

.page-header-actions .dropdown-item {
    display: block;
    padding: 9px 14px;
    color: #334155;
    font-size: 13px;
    text-decoration: none;
}

.page-header-actions .dropdown-item:hover {
    background: #f1f5f9;
    color: #11294b;
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
    position: relative;
}

.page-header-actions .btn-icon:hover {
    background: rgba(255,255,255,0.3);
}

/* Toolbar */
.toolbar {
    background: #fff;
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.toolbar-left {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.toolbar-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-box {
    position: relative;
    min-width: 250px;
}

.search-box input {
    width: 100%;
    padding: 8px 35px 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
}

.search-box i {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
}

.filter-btn, .export-btn {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.filter-btn:hover, .export-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

/* Table Container */
.table-container {
    flex: 1;
    overflow: auto;
    background: #fff;
    margin: 4px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table {
    width: 100%;
    margin: 0;
    font-size: 12px;
    border-collapse: collapse;
}

.table thead th {
    background: #11294b !important;
    font-weight: 600;
    color: #fff;
    padding: 6px;
    border-bottom: 2px solid #e2e8f0;
    position: sticky;
    top: 0;
    z-index: 10;
    text-align: left;
}

/* Stock journal: reorder + resize — auto layout so full header labels show; horizontal scroll in .table-container */
#ledgerTable {
    table-layout: auto;
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
}
#ledgerTable thead th {
    position: relative;
    vertical-align: middle;
    user-select: none;
    box-sizing: border-box;
    overflow: visible;
    white-space: nowrap;
    text-overflow: clip;
    padding: 7px 14px 7px 8px;
    line-height: 1.3;
    font-size: 12px;
    hyphens: none;
}
#ledgerTable thead th.sj-th-reorder {
    /* room for resizer on the right */
    padding-right: 12px;
}
#ledgerTable thead th .sj-th-drag {
    display: inline-flex;
    align-items: center;
    margin-left: 6px;
    cursor: grab;
    color: rgba(255, 255, 255, 0.65);
    line-height: 0;
    vertical-align: middle;
    flex-shrink: 0;
}
#ledgerTable thead th .sj-th-drag .feather {
    width: 15px;
    height: 15px;
    stroke: currentColor;
}
#ledgerTable thead th .sj-th-drag:hover {
    color: #fff;
}
#ledgerTable thead th .sj-th-drag:active {
    cursor: grabbing;
}
#ledgerTable thead th .sj-th-label {
    display: inline;
    vertical-align: middle;
    margin-right: 2px;
}
#ledgerTable thead th .sj-col-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    cursor: col-resize;
    z-index: 3;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.12));
}
#ledgerTable thead th .sj-col-resizer:hover {
    background: rgba(255, 255, 255, 0.2);
}
#ledgerTable thead th.sortable-ghost,
#ledgerTable thead th.sj-sortable-ghost {
    opacity: 0.4;
    background: #1e3a5f !important;
}
#ledgerTable thead th.sj-sortable-chosen {
    background: #16305a !important;
}

.table tbody td {
    padding: 4px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.btn-view {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 500;
}

.btn-view:hover {
    background: #4a2b7c;
}

.btn-create, .btn-update, .btn-delete, .btn-add-items {
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 500;
    margin: 0 2px;
}

.btn-create {
    background: #11294b;
    color: #fff;
}

.btn-create:hover {
    background: #4a2b7c;
}

.btn-add-items {
    background: #10b981;
    color: #fff;
}

.btn-add-items:hover {
    background: #059669;
}

.btn-update {
    background: #a78bfa;
    color: #fff;
}

.btn-update:hover {
    background: #8b5cf6;
}

/* Action buttons in table: ensure always clickable, no lock/overlay issues */
.table-container .btn-create,
.table-container .btn-update,
.table-container .btn-delete,
.table-container .btn-add-items {
    pointer-events: auto;
    cursor: pointer;
}

.btn-update:disabled {
    background: #cbd5e1;
    color: #94a3b8;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-delete {
    background: #ef4444;
    color: #fff;
}

.btn-delete:hover {
    background: #dc2626;
}

.btn-delete:disabled {
    background: #cbd5e1;
    color: #94a3b8;
    cursor: not-allowed;
    opacity: 0.6;
}

.invoice-link {
    color: #3b82f6;
    text-decoration: underline;
    cursor: pointer;
}

.invoice-link:hover {
    color: #2563eb;
}

/* Pagination */
.pagination-container {
    background: #fff;
    padding: 12px 20px;
    margin: 0 20px 20px 20px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.pagination-info {
    color: #64748b;
    font-size: 12px;
}

.pagination-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.per-page-dropdown select {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
}

.pagination-controls {
    display: flex;
    gap: 5px;
    align-items: center;
}

.pagination-controls button {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    min-width: 36px;
}

.pagination-controls button:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination-controls button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-controls .page-number {
    background: #11294b;
    color: #fff;
    border-color: #11294b;
}

.pagination-controls .page-number:hover {
    background: #4a2b7c;
}

.sj-adv-modal.modal { z-index: 1060; }
.sj-adv-modal .modal-backdrop { z-index: 1055; }
.sj-adv-modal .modal-content { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 12px 40px rgba(17, 41, 75, 0.2); }
.sj-adv-modal .modal-dialog { max-width: 720px; width: calc(100vw - 32px); }
.sj-adv-modal .modal-header {
    background: linear-gradient(135deg, #11294b 0%, #1e3a5f 100%);
    border: none; padding: 18px 20px 14px; position: relative;
}
.sj-adv-modal .modal-title { width: 100%; text-align: center; font-weight: 700; font-size: 1.05rem; color: #fff; margin: 0; }
.sj-adv-modal .close { position: absolute; right: 14px; top: 14px; opacity: .85; color: #fff; text-shadow: none; font-size: 1.5rem; }
.sj-adv-modal .close:hover { opacity: 1; color: #c9a962; }
.sj-adv-modal .modal-body { padding: 20px 22px 8px; background: #fafbfc; }
.sj-adv-modal .modal-footer-adv {
    display: flex; justify-content: center; gap: 12px; padding: 16px 20px 20px;
    background: #fafbfc; border-top: 1px solid #e2e8f0;
}
.sj-adv-modal .btn-adv-apply {
    border: 2px solid #7c3aed; color: #7c3aed; background: #fff; font-weight: 600;
    padding: 8px 28px; border-radius: 8px; min-width: 130px;
}
.sj-adv-modal .btn-adv-apply:hover { background: #7c3aed; color: #fff; }
.sj-adv-modal .btn-adv-clear {
    border: 2px solid #db2777; color: #db2777; background: #fff; font-weight: 600;
    padding: 8px 28px; border-radius: 8px; min-width: 130px;
}
.sj-adv-modal .btn-adv-clear:hover { background: #db2777; color: #fff; }
.sj-adv-modal .sj-filter-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px;
}
.sj-adv-modal .sj-filter-field { display: flex; flex-direction: column; gap: 4px; }
.sj-adv-modal .sj-filter-field label { font-size: 12px; font-weight: 600; color: #374151; margin: 0; }
.sj-adv-modal .sj-filter-field-full { grid-column: 1 / -1; }
.sj-adv-modal .sj-filter-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; grid-column: 1 / -1; }
.sj-adv-modal .sj-filter-grid > .row { grid-column: 1 / -1; margin-left: 0; margin-right: 0; }
.sj-adv-modal .sj-filter-grid > .row > [class*="col-"] { padding-left: 8px; padding-right: 8px; }
.sj-adv-modal .sj-filter-grid > .row > [class*="col-"]:first-child { padding-left: 0; }
.sj-adv-modal .sj-filter-grid > .row > [class*="col-"]:last-child { padding-right: 0; }
.sj-adv-modal .form-control { font-size: 13px; border-radius: 6px; border-color: #d1d5db; }

/* Column settings dropdown */
.sj-col-settings-dropdown {
    min-width: 240px; max-height: 360px; overflow-y: auto; padding: 8px 0;
}
.sj-col-settings-dropdown label {
    display: flex; align-items: center; gap: 8px; padding: 6px 14px; margin: 0;
    font-size: 13px; cursor: pointer; color: #334155;
}
.sj-col-settings-dropdown label:hover { background: #f1f5f9; }
.sj-col-settings-dropdown input { margin: 0; }
.sj-col-hidden { display: none !important; }
</style>

<body>
    <?php include 'sidebar.php';?>
    
    <div class="layout-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header-bar">
                <div>Stock Journal<?php if ($sj_branch_label !== ''): ?> <span style="opacity:0.9;font-weight:500;">— <?= htmlspecialchars($sj_branch_label, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></div>
                <div class="page-header-actions">
                    <button class="btn-icon" type="button" title="Filter" data-toggle="modal" data-target="#sjAdvFilterModal">
                        <i class="feather icon-filter"></i>
                    </button>
                    <button class="btn-icon" title="Refresh" type="button" onclick="location.reload()">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                    <div class="dropdown">
                        <button class="btn-icon sj-header-dropdown-btn" title="Export" type="button" data-sj-dropdown="sjExportMenu" aria-haspopup="true" aria-expanded="false">
                            <i class="feather icon-download"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" id="sjExportMenu">
                            <a class="dropdown-item" href="#" id="sjExportExcelMenu">Export to Excel</a>
                            <a class="dropdown-item" href="#" id="sjExportPdfMenu">Export to PDF</a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn-icon sj-header-dropdown-btn" title="Column settings" type="button" data-sj-dropdown="sjColSettingsDropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="feather icon-settings"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right sj-col-settings-dropdown" id="sjColSettingsDropdown"></div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <!-- <div class="toolbar">
                <div class="toolbar-left">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search by Invoice No or Customer..." value="<?php echo htmlspecialchars($search); ?>" onkeypress="handleSearchEnter(event)">
                        <i class="feather icon-search"></i>
                    </div>
                    <button class="filter-btn" onclick="openFilterModal()">
                        <i class="feather icon-filter"></i> Filter
                    </button>
                </div>
                <div class="toolbar-right">
                    <button class="export-btn" onclick="exportToExcel()">
                        <i class="feather icon-download"></i> Export
                    </button>
                </div>
            </div> -->

            <!-- DataTable Controls Bar -->
            <div class="datatable-controls-bar" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #e2e8f0; border-radius: 8px 8px 0 0;">
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label for="voucherType" style="margin: 0; font-weight: 600; font-size: 13px; color: #374151;">Voucher</label>
                        <select id="voucherType" class="form-control" onchange="changeVoucher(this.value)" style="min-width: 180px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                            <option value="purchase_invoice" <?php echo $voucher === 'purchase_invoice' ? 'selected' : ''; ?>>Purchase Invoice</option>
                            <option value="product_opening" <?php echo $voucher === 'product_opening' ? 'selected' : ''; ?>>Product Opening</option>
                            <option value="jobwork_invoice" <?php echo $voucher === 'jobwork_invoice' ? 'selected' : ''; ?>>Jobwork Invoice</option>
                            <option value="purchase_quotation" <?php echo $voucher === 'purchase_quotation' ? 'selected' : ''; ?>>Purchase Quotation</option>
                            <option value="broken_entry" <?php echo $voucher === 'broken_entry' ? 'selected' : ''; ?>>Broken Entry</option>
                        </select>
                    </div>
                    <div class="datatable-search" style="flex: 1;">
                        <input type="text" id="customSearch" class="form-control" placeholder="Search in table..." style="max-width: 300px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                    </div>
                </div>
                <div class="datatable-buttons" style="display: flex; gap: 10px;">
                    <button id="exportExcelBtn" class="btn" style="background: #11294b; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                        <i class="feather icon-download"></i> Export Excel
                    </button>
                    <button id="printBtn" class="btn" style="background: #11294b; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                        <i class="feather icon-printer"></i> Print
                    </button>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container" style="border-radius: 0 0 8px 8px;">
                <table class="table" id="ledgerTable" data-sj-voucher="<?php echo htmlspecialchars($voucher, ENT_QUOTES, 'UTF-8'); ?>">
                    <thead>
                        <tr>
                            <th class="sj-th-fixed" data-col="view" data-sj-title="View" data-sj-min="76" style="min-width: 76px; width: 84px;"><span class="sj-th-label">View</span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="sr_no" data-sj-title="Sr No." data-sj-min="100" style="min-width: 100px;"><span class="sj-th-label">Sr No.</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="customer" data-sj-title="Customer" data-sj-min="120" style="min-width: 120px;"><span class="sj-th-label">Customer</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="date" data-sj-title="Date" data-sj-min="96" style="min-width: 96px;"><span class="sj-th-label">Date</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="source" data-sj-title="Source" data-sj-min="100" style="min-width: 100px;"><span class="sj-th-label">Source</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="sj_invoice_no" data-sj-title="SJ Invoice No" data-sj-min="160" style="min-width: 160px;"><span class="sj-th-label">SJ Invoice No</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="metal" data-sj-title="Metal" data-sj-min="96" style="min-width: 96px;"><span class="sj-th-label">Metal</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="product" data-sj-title="Product" data-sj-min="150" style="min-width: 150px;"><span class="sj-th-label">Product</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="currency" data-sj-title="Currency" data-sj-min="100" style="min-width: 100px;"><span class="sj-th-label">Currency</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="location" data-sj-title="Location" data-sj-min="100" style="min-width: 100px;"><span class="sj-th-label">Location</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="gross_wt" data-sj-title="Gross Wt." data-sj-min="110" style="min-width: 110px;"><span class="sj-th-label">Gross Wt.</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="purchase_amount" data-sj-title="Purchase Amount." data-sj-min="180" style="min-width: 180px;"><span class="sj-th-label">Purchase Amount.</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="stockjournal_amount" data-sj-title="StockJournal Amount" data-sj-min="210" style="min-width: 210px;"><span class="sj-th-label">StockJournal Amount</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="production_qty" data-sj-title="Production Qty." data-sj-min="160" style="min-width: 160px;" title="<?php echo $voucher === 'product_opening'
                                ? 'Sum of quantities on Product Opening stock journal lines (item not linked to a purchase invoice line).'
                                : 'Sum of quantities on Stock Journal lines (one line can have qty &gt; 1).'; ?>"><span class="sj-th-label">Production Qty.</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="production_wt" data-sj-title="Total Production Wt" data-sj-min="170" style="min-width: 170px;" title="<?php echo $voucher === 'product_opening'
                                ? 'Sum of gross/net weight on Product Opening stock journal lines.'
                                : 'Sum of gross/net weight on Stock Journal lines for this purchase item.'; ?>"><span class="sj-th-label">Total Production Wt</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="available_qty" data-sj-title="Available Qty." data-sj-min="155" style="min-width: 155px;" title="<?php echo $voucher === 'product_opening'
                                ? 'Remaining opening quantity on the characteristic (after stock journal consumption).'
                                : 'Purchase line qty minus sum of SJ line quantities — not yet assigned in Stock Journal.'; ?>"><span class="sj-th-label">Available Qty.</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="available_wt" data-sj-title="Available Wt" data-sj-min="140" style="min-width: 140px;" title="<?php echo $voucher === 'product_opening'
                                ? 'Opening weight minus total production weight — remaining weight for Stock Journal.'
                                : 'Purchase line gross wt minus total production weight — not yet assigned in Stock Journal.'; ?>"><span class="sj-th-label">Available Wt</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="total_qty" data-sj-title="Total Qty." data-sj-min="110" style="min-width: 110px;" title="<?php echo $voucher === 'product_opening'
                                ? 'Original opening bucket: remaining opening qty plus quantities already moved via Product Opening stock journal.'
                                : 'Qty on the purchase invoice line (metal qty or quantity).'; ?>"><span class="sj-th-label">Total Qty.</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="branch_name" data-sj-title="Branch Name" data-sj-min="130" style="min-width: 130px;"><span class="sj-th-label">Branch Name</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sortable sj-th-reorder" data-col="stone_wt" data-sj-title="Stone Wt." data-sj-min="100" style="min-width: 100px;"><span class="sj-th-label">Stone Wt.</span><span class="sj-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                            <th class="sj-th-fixed" data-col="action" data-sj-title="Action" data-sj-min="260" style="min-width: 260px; width: 280px;"><span class="sj-th-label">Action</span><span class="sj-col-resizer" aria-hidden="true"></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data)): ?>
                            <?php 
                            $sr_no = $offset + 1;
                            $is_opening = ($voucher === 'product_opening');
                            foreach ($data as $row): 
                                $row_voucher = $row['voucher_type'] ?? '';
                                $is_row_opening = ($row_voucher === 'product_opening' || $is_opening);
                            ?>
                            <tr data-voucher="<?php echo $is_row_opening ? 'product_opening' : 'purchase_invoice'; ?>">
                                <td data-col="view">
                                    <?php if ($is_row_opening): ?>
                                        <a href="product-opening.php?id=<?php echo (int)($row['product_id'] ?? 0); ?>" class="btn-view" style="display: inline-block; padding: 4px 12px; background: #11294b; color: #fff; border-radius: 4px; text-decoration: none; font-size: 12px;">View</a>
                                    <?php else: ?>
                                        <button type="button" class="btn-view" onclick="viewInvoice(<?php echo (int)($row['invoice_id'] ?? 0); ?>)">View</button>
                                    <?php endif; ?>
                                </td>
                                <td data-col="sr_no"><?php echo $sr_no++; ?></td>
                                <td data-col="customer"><?php echo htmlspecialchars($row['supplier_name'] ?? ''); ?></td>
                                <td data-col="date"><?php echo !empty($row['invoice_date']) ? date('d/m/Y', strtotime($row['invoice_date'])) : ''; ?></td>
                                <td data-col="source">
                                    <?php if ($is_row_opening): ?>
                                        <span style="color: #11294b; font-weight: 500;">Opening</span>
                                    <?php else: ?>
                                        <a href="purchase-invoice.php?id=<?php echo (int)($row['invoice_id'] ?? 0); ?>" class="invoice-link">
                                            <?php echo htmlspecialchars($row['invoice_no'] ?? ''); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td data-col="sj_invoice_no"></td>
                                <td data-col="metal"><?php echo htmlspecialchars($row['metal_name'] ?? 'N/A'); ?></td>
                                <td data-col="product"><?php echo htmlspecialchars($row['product_name'] ?? ($row['full_product_name'] ?? 'N/A')); ?></td>
                                <td data-col="currency"><?php echo htmlspecialchars($row['currency'] ?? 'N/A'); ?></td>
                                <td data-col="location"><?php echo htmlspecialchars($row['location_name'] ?? 'N/A'); ?></td>
                                <td data-col="gross_wt" style="color: #a78bfa;"><?php echo htmlspecialchars(auragold_format_weight_list($row['gross_weight'] ?? 0)); ?></td>
                                <td data-col="purchase_amount" style="color: #a78bfa;"><?php echo number_format($row['purchase_amount'] ?? 0, 2); ?></td>
                                <td data-col="stockjournal_amount" style="color: #a78bfa;"><?php echo number_format($row['stockjournal_amount'] ?? 0, 2); ?></td>
                                <td data-col="production_qty" style="color: #a78bfa;"><?php echo number_format($row['production_qty'] ?? 0, 2); ?></td>
                                <td data-col="production_wt" style="color: #a78bfa;"><?php echo htmlspecialchars(auragold_format_weight_list($row['production_wt'] ?? 0)); ?></td>
                                <td data-col="available_qty" style="color: #a78bfa;"><?php echo number_format($row['available_qty'] ?? 0, 2); ?></td>
                                <td data-col="available_wt" style="color: #a78bfa;"><?php echo htmlspecialchars(auragold_format_weight_list($row['available_wt'] ?? 0)); ?></td>
                                <td data-col="total_qty" style="color: #a78bfa;"><?php echo number_format($row['total_qty'] ?? 0, 2); ?></td>
                                <td data-col="branch_name" style="color: #3b82f6;"><?php echo htmlspecialchars($row['branch_name'] ?? 'Main Branch'); ?></td>
                                <td data-col="stone_wt" style="color: #a78bfa;"><?php echo number_format($row['stone_wt'] ?? 0, 2); ?></td>
                                <td data-col="action">
                                    <?php 
                                    $is_row_opening = ($row_voucher === 'product_opening' || $is_opening);
                                    if ($is_row_opening): 
                                        $char_id = isset($row['characteristic_id']) ? (int)$row['characteristic_id'] : (int)($row['item_id'] ?? 0);
                                        $product_id = isset($row['product_id']) ? (int)$row['product_id'] : 0;
                                    ?>
                                        <?php $po_has_stock = (int)($row['has_stock_journal'] ?? 0) > 0; ?>
                                        <button type="button" class="btn-create" data-action="create" data-item-id="<?php echo $char_id; ?>" data-product-id="<?php echo $product_id; ?>" data-voucher-type="product_opening">Create</button>
                                        <button type="button" class="btn-update" data-action="update" data-item-id="<?php echo $char_id; ?>" data-product-id="<?php echo $product_id; ?>" data-voucher-type="product_opening" <?php echo $po_has_stock ? '' : 'disabled'; ?>>Update Items</button>
                                        <button type="button" class="btn-delete" data-action="delete" data-item-id="<?php echo $char_id; ?>" data-voucher-type="product_opening" <?php echo $po_has_stock ? '' : 'disabled'; ?>>Delete</button>
                                    <?php else: ?>
                                        <?php 
                                        $item_id = isset($row['item_id']) && $row['item_id'] ? $row['item_id'] : 0;
                                        $has_stock_journal = (int)($row['has_stock_journal'] ?? 0) > 0;
                                        $pi_product_id = isset($row['product_id']) ? (int)$row['product_id'] : 0;
                                        $pi_char_id = isset($row['characteristic_id']) ? (int)$row['characteristic_id'] : 0;
                                        ?>
                                        <?php if ($item_id > 0): ?>
                                            <?php if ($has_stock_journal): ?>
                                                <button type="button" class="btn-add-items" data-action="add" data-voucher-type="purchase_invoice" data-item-id="<?php echo (int)$item_id; ?>" data-product-id="<?php echo (int)$pi_product_id; ?>" data-characteristic-id="<?php echo (int)$pi_char_id; ?>">Add Items</button>
                                                <button type="button" class="btn-update" data-action="update" data-voucher-type="purchase_invoice" data-item-id="<?php echo (int)$item_id; ?>" data-product-id="<?php echo (int)$pi_product_id; ?>" data-characteristic-id="<?php echo (int)$pi_char_id; ?>">Update Items</button>
                                                <button type="button" class="btn-delete" data-action="delete" data-voucher-type="purchase_invoice" data-item-id="<?php echo (int)$item_id; ?>">Delete</button>
                                            <?php else: ?>
                                                <button type="button" class="btn-create" data-action="create" data-voucher-type="purchase_invoice" data-item-id="<?php echo (int)$item_id; ?>" data-product-id="<?php echo (int)$pi_product_id; ?>" data-characteristic-id="<?php echo (int)$pi_char_id; ?>">Create</button>
                                                <button type="button" class="btn-update" data-action="update" data-voucher-type="purchase_invoice" data-item-id="<?php echo (int)$item_id; ?>" data-product-id="<?php echo (int)$pi_product_id; ?>" data-characteristic-id="<?php echo (int)$pi_char_id; ?>" disabled>Update Items</button>
                                                <button type="button" class="btn-delete" data-action="delete" data-voucher-type="purchase_invoice" data-item-id="<?php echo (int)$item_id; ?>" disabled>Delete</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn-create" disabled>Create</button>
                                            <button type="button" class="btn-update" disabled>Update Items</button>
                                            <button type="button" class="btn-delete" disabled>Delete</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="21" style="text-align: center; padding: 40px; color: #64748b;">
                                    <?php
                                    if ($voucher === 'product_opening') echo 'No product opening items found.';
                                    elseif (in_array($voucher, ['jobwork_invoice', 'purchase_quotation', 'broken_entry'])) echo 'No records for this voucher type.';
                                    else echo 'No purchase invoice records found';
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                </div>
                <div class="pagination-right">
                    <div class="per-page-dropdown">
                        <select onchange="changePerPage(this.value)">
                            <option value="5" <?php echo $per_page == 5 ? 'selected' : ''; ?>>Show 5 Items</option>
                            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>Show 10 Items</option>
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>Show 25 Items</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>Show 50 Items</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>Show 100 Items</option>
                        </select>
                    </div>
                    <div class="pagination-controls">
                        <button onclick="goToPage(1)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>&lt;&lt;</button>
                        <button onclick="goToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>&lt;</button>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <button class="<?php echo $i == $page ? 'page-number' : ''; ?>" onclick="goToPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
                        <?php endfor; ?>
                        <button onclick="goToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>&gt;</button>
                        <button onclick="goToPage(<?php echo $total_pages; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>&gt;&gt;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Advance Filter Modal -->
<div class="modal fade sj-adv-modal" id="sjAdvFilterModal" tabindex="-1" role="dialog" aria-labelledby="sjAdvFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="sjAdvFilterModalLabel">Advance Filter</h5>
            </div>
            <form method="get" action="stock-journal.php" id="sjAdvFilterForm">
                <input type="hidden" name="voucher" value="<?php echo htmlspecialchars($voucher, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="per_page" value="<?php echo (int) $per_page; ?>">
                <div class="modal-body">
                    <div class="sj-filter-grid">
                        <div class="row">
                            <div class="col-6">
                                <div class="sj-filter-field">
                                    <label for="sj_date_from">Date Range (From)</label>
                                    <input type="date" class="form-control" id="sj_date_from" name="from_date" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="sj-filter-field">
                                    <label for="sj_date_to">Date Range (To)</label>
                                    <input type="date" class="form-control" id="sj_date_to" name="to_date" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="sj-filter-field">
                            <label for="sj_date_type">Date Type</label>
                            <select class="form-control" id="sj_date_type" name="date_type">
                                <option value="source" <?php echo $date_type === 'source' ? 'selected' : ''; ?>>Source</option>
                                <option value="sj" <?php echo $date_type === 'sj' ? 'selected' : ''; ?>>SJ</option>
                            </select>
                        </div>
                        <div class="sj-filter-field">
                            <label for="sj_gross_wt">Gross Wt.</label>
                            <input type="text" class="form-control" id="sj_gross_wt" name="gross_wt" value="<?php echo htmlspecialchars($gross_wt_filter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Min gross wt">
                        </div>
                        <div class="sj-filter-field">
                            <label for="sj_branch_id">Branch</label>
                            <select class="form-control" id="sj_branch_id" name="branch_id">
                                <option value="">All Branches</option>
                                <?php if (count($sj_branch_groups) > 1): ?>
                                    <?php foreach ($sj_branch_groups as $sj_grp): ?>
                                        <?php
                                        $sj_main = is_array($sj_grp['main'] ?? null) ? $sj_grp['main'] : null;
                                        if (!$sj_main || empty($sj_main['id'])) {
                                            continue;
                                        }
                                        $sj_main_id = (int) $sj_main['id'];
                                        $sj_main_name = trim((string) ($sj_main['name'] ?? ''));
                                        if ($sj_main_id <= 0 || $sj_main_name === '') {
                                            continue;
                                        }
                                        ?>
                                        <optgroup label="<?php echo htmlspecialchars($sj_main_name, ENT_QUOTES, 'UTF-8'); ?>">
                                            <option value="<?php echo $sj_main_id; ?>" <?php echo $branch_filter === $sj_main_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($sj_main_name, ENT_QUOTES, 'UTF-8'); ?> (Main)</option>
                                            <?php foreach (is_array($sj_grp['subs'] ?? null) ? $sj_grp['subs'] : [] as $sj_sub): ?>
                                                <?php
                                                $sj_sub_id = (int) ($sj_sub['id'] ?? 0);
                                                $sj_sub_name = trim((string) ($sj_sub['name'] ?? ''));
                                                if ($sj_sub_id <= 0 || $sj_sub_name === '') {
                                                    continue;
                                                }
                                                ?>
                                                <option value="<?php echo $sj_sub_id; ?>" <?php echo $branch_filter === $sj_sub_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($sj_sub_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($sj_branches as $br): ?>
                                    <option value="<?php echo (int) ($br['id'] ?? 0); ?>" <?php echo $branch_filter === (int) ($br['id'] ?? 0) ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($br['is_main']) ? (($br['name'] ?? '') . ' (Main)') : ($br['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="sj-filter-field">
                            <label for="sj_customer">Customer</label>
                            <select class="form-control" id="sj_customer" name="customer">
                                <option value="">All Customers</option>
                                <?php foreach ($customers as $cust): ?>
                                <?php $cn = trim((string) ($cust['supplier_name'] ?? '')); if ($cn === '') continue; ?>
                                <option value="<?php echo htmlspecialchars($cn, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $customer_filter === $cn ? 'selected' : ''; ?>><?php echo htmlspecialchars($cn, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sj-filter-field">
                            <label for="sj_account_no">Account No</label>
                            <input type="text" class="form-control" id="sj_account_no" name="account_no" value="<?php echo htmlspecialchars($account_no_filter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Account / invoice no">
                        </div>
                        <div class="sj-filter-field">
                            <label for="sj_adv_voucher">Voucher Type</label>
                            <select class="form-control" id="sj_adv_voucher" name="voucher">
                                <option value="purchase_invoice" <?php echo $voucher === 'purchase_invoice' ? 'selected' : ''; ?>>Purchase Invoice</option>
                                <option value="product_opening" <?php echo $voucher === 'product_opening' ? 'selected' : ''; ?>>Product Opening</option>
                                <option value="jobwork_invoice" <?php echo $voucher === 'jobwork_invoice' ? 'selected' : ''; ?>>Jobwork Invoice</option>
                                <option value="purchase_quotation" <?php echo $voucher === 'purchase_quotation' ? 'selected' : ''; ?>>Purchase Quotation</option>
                                <option value="broken_entry" <?php echo $voucher === 'broken_entry' ? 'selected' : ''; ?>>Broken Entry</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="sj-filter-field">
                                    <label for="sj_metal_id">Metal</label>
                                    <select class="form-control" id="sj_metal_id" name="metal_id">
                                        <option value="">Select Metal</option>
                                        <?php foreach ($sj_metals as $mt): ?>
                                        <option value="<?php echo (int) ($mt['id'] ?? 0); ?>" <?php echo $metal_filter === (int) ($mt['id'] ?? 0) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mt['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="sj-filter-field">
                                    <label for="sj_product_id">Product Name</label>
                                    <select class="form-control" id="sj_product_id" name="product_id" data-selected-product="<?php echo (int) $product_filter; ?>">
                                        <option value="">Select Product</option>
                                        <?php foreach ($sj_products as $pr): ?>
                                        <option value="<?php echo (int) ($pr['id'] ?? 0); ?>" <?php echo $product_filter === (int) ($pr['id'] ?? 0) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pr['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="sj-filter-row-2">
                            <div class="sj-filter-field">
                                <label for="sj_source">Source</label>
                                <input type="text" class="form-control" id="sj_source" name="source" value="<?php echo htmlspecialchars($source_filter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Invoice / source no">
                            </div>
                            <div class="sj-filter-field">
                                <label for="sj_invoice_no">SJ Invoice No.</label>
                                <input type="text" class="form-control" id="sj_invoice_no" name="sj_invoice_no" value="<?php echo htmlspecialchars($sj_invoice_no_filter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="SJ invoice no">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-adv">
                    <button type="submit" class="btn btn-adv-apply">Apply Filter</button>
                    <button type="button" class="btn btn-adv-clear" id="sjClearFilterBtn">Clear Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewInvoice(invoiceId) {
    window.location.href = 'purchase-invoice.php?id=' + invoiceId;
}

function changeVoucher(value) {
    var url = new URL(window.location.href);
    url.searchParams.set('voucher', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// Build create URL: product opening = characteristic + voucher + product; purchase invoice = item + voucher + optional product/characteristic (same shape as opening list).
function auragoldStockJournalCreateUrl(itemId, voucherType, productId, characteristicId, mode) {
    var productIdN = parseInt(productId, 10) || 0;
    var charIdN = parseInt(characteristicId, 10) || 0;
    if (voucherType === 'product_opening') {
        var q = 'stock-journal-create.php?characteristic_id=' + encodeURIComponent(itemId) + '&voucher=product_opening';
        if (productIdN > 0) q += '&product_id=' + encodeURIComponent(productIdN);
        if (mode === 'add') q += '&mode=add';
        return q;
    }
    var qp = 'stock-journal-create.php?item_id=' + encodeURIComponent(itemId) + '&voucher=purchase_invoice';
    if (productIdN > 0) qp += '&product_id=' + encodeURIComponent(productIdN);
    if (charIdN > 0) qp += '&characteristic_id=' + encodeURIComponent(charIdN);
    if (mode === 'add') qp += '&mode=add';
    return qp;
}
function auragoldStockJournalUpdateUrl(itemId, voucherType, productId, characteristicId) {
    var productIdN = parseInt(productId, 10) || 0;
    var charIdN = parseInt(characteristicId, 10) || 0;
    if (voucherType === 'product_opening') {
        var u = 'stock-journal-update.php?characteristic_id=' + encodeURIComponent(itemId) + '&voucher=product_opening';
        if (productIdN > 0) u += '&product_id=' + encodeURIComponent(productIdN);
        return u;
    }
    var up = 'stock-journal-update.php?item_id=' + encodeURIComponent(itemId) + '&voucher=purchase_invoice';
    if (productIdN > 0) up += '&product_id=' + encodeURIComponent(productIdN);
    if (charIdN > 0) up += '&characteristic_id=' + encodeURIComponent(charIdN);
    return up;
}

// Single delegated handler: Create / Add / Update / Delete for all voucher types
document.addEventListener('DOMContentLoaded', function() {
    var container = document.querySelector('.table-container');
    if (!container) return;
    container.addEventListener('click', function(e) {
        var btn = e.target && e.target.closest && e.target.closest('[data-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-action');
        var itemId = parseInt(btn.getAttribute('data-item-id'), 10);
        var voucherType = btn.getAttribute('data-voucher-type') || '';
        if (!itemId) return;
        e.preventDefault();
        e.stopPropagation();
        var productId = parseInt(btn.getAttribute('data-product-id'), 10) || 0;
        var characteristicId = parseInt(btn.getAttribute('data-characteristic-id'), 10) || 0;
        if (action === 'create') {
            window.location.href = auragoldStockJournalCreateUrl(itemId, voucherType, productId, characteristicId, '');
        } else if (action === 'add') {
            window.location.href = auragoldStockJournalCreateUrl(itemId, voucherType, productId, characteristicId, 'add');
        } else if (action === 'update') {
            window.location.href = auragoldStockJournalUpdateUrl(itemId, voucherType, productId, characteristicId);
        } else if (action === 'delete') {
            deleteItem(itemId, btn, voucherType);
        }
    });
});

function createStockJournal(itemId) {
    if (itemId && itemId > 0) {
        window.location.href = 'stock-journal-create.php?item_id=' + itemId + '&voucher=purchase_invoice';
    } else {
        alert('Invalid item ID');
    }
}

function addItems(itemId) {
    if (itemId && itemId > 0) {
        window.location.href = 'stock-journal-create.php?item_id=' + itemId + '&voucher=purchase_invoice&mode=add';
    } else {
        alert('Invalid item ID');
    }
}

function updateItems(itemId) {
    if (itemId && itemId > 0) {
        window.location.href = 'stock-journal-create.php?item_id=' + itemId + '&voucher=purchase_invoice&edit=true';
    } else {
        alert('Invalid item ID');
    }
}

function deleteItem(itemId, buttonElement, voucherType) {
    if (!itemId || itemId <= 0) {
        alert('Invalid item ID');
        return;
    }
    
    if (!confirm('Delete all stock journal entries and linked barcodes for this product opening? Any leftover barcodes from a previous delete will also be removed. This cannot be undone.')) {
        return;
    }
    
    var deleteBtn = buttonElement || (event && event.target);
    var originalText = '';
    if (deleteBtn) {
        originalText = deleteBtn.textContent;
        deleteBtn.disabled = true;
        deleteBtn.textContent = 'Deleting...';
    }
    
    var payload = voucherType === 'product_opening' ? { characteristic_id: itemId, voucher: 'product_opening' } : { item_id: itemId };
    
    fetch('ajax/delete-stock-journal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message || 'Stock journal entries deleted successfully');
            // Reload the page to refresh the table
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete stock journal entries'));
            if (deleteBtn && originalText) {
                deleteBtn.disabled = false;
                deleteBtn.textContent = originalText;
            }
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        alert('Error deleting stock journal entries: ' + error.message);
        if (deleteBtn && originalText) {
            deleteBtn.disabled = false;
            deleteBtn.textContent = originalText;
        }
    });
}

function handleSearchEnter(event) {
    if (event.key === 'Enter') {
        performSearch();
    }
}

function performSearch() {
    const search = document.getElementById('searchInput').value;
    const url = new URL(window.location.href);
    url.searchParams.set('search', search);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function openFilterModal() {
    var el = document.getElementById('sjAdvFilterModal');
    if (!el) return;
    if (typeof jQuery !== 'undefined' && typeof jQuery(el).modal === 'function') {
        jQuery(el).modal('show');
        return;
    }
    el.classList.add('show');
    el.style.display = 'block';
    el.setAttribute('aria-hidden', 'false');
}

function sjCloseHeaderDropdowns(exceptId) {
    document.querySelectorAll('.page-header-actions .dropdown-menu').forEach(function (menu) {
        if (exceptId && menu.id === exceptId) return;
        menu.classList.remove('show');
        menu.style.display = 'none';
    });
    document.querySelectorAll('.page-header-actions .sj-header-dropdown-btn').forEach(function (btn) {
        btn.setAttribute('aria-expanded', 'false');
    });
}

function sjToggleHeaderDropdown(menuId, btn) {
    var menu = document.getElementById(menuId);
    if (!menu) return;
    var willOpen = !menu.classList.contains('show');
    sjCloseHeaderDropdowns(willOpen ? menuId : null);
    menu.classList.toggle('show', willOpen);
    menu.style.display = willOpen ? 'block' : 'none';
    if (willOpen && btn) {
        var rect = btn.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.top = Math.round(rect.bottom + 6) + 'px';
        menu.style.right = Math.round(window.innerWidth - rect.right) + 'px';
        menu.style.left = 'auto';
    } else if (!willOpen) {
        menu.style.position = '';
        menu.style.top = '';
        menu.style.right = '';
    }
    if (btn) {
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }
}

function clearSjAdvanceFilter() {
    window.location.href = 'stock-journal.php?voucher=' + encodeURIComponent(document.getElementById('voucherType') ? document.getElementById('voucherType').value : 'purchase_invoice');
}

function exportToExcel() {
    if (typeof exportTableToExcel === 'function') {
        exportTableToExcel();
    }
}

function exportToPDF() {
    if (typeof printTable === 'function') {
        printTable();
    }
}

function changePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function goToPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}
</script>

<?php include 'footer-script.php'; ?>
<script src="assets/libs/sortablejs/sortable.js"></script>
<script>
var SJ_TABLE_COL_KEYS = ['view', 'sr_no', 'customer', 'date', 'source', 'sj_invoice_no', 'metal', 'product', 'currency', 'location', 'gross_wt', 'purchase_amount', 'stockjournal_amount', 'production_qty', 'production_wt', 'available_qty', 'available_wt', 'total_qty', 'branch_name', 'stone_wt', 'action'];
var sjLedgerSortableInstance = null;

function sjColVisibilityKey() {
    var table = document.getElementById('ledgerTable');
    var voucher = table ? (table.getAttribute('data-sj-voucher') || 'purchase_invoice') : 'purchase_invoice';
    return 'auragold_stock_journal_col_vis_v2_' + voucher;
}

function sjToggleColumn(col, show) {
    var table = document.getElementById('ledgerTable');
    if (!table || !col) return;
    table.querySelectorAll('[data-col="' + col + '"]').forEach(function (el) {
        if (show) el.classList.remove('sj-col-hidden');
        else el.classList.add('sj-col-hidden');
    });
}

function sjSaveColumnVisibility() {
    var vis = {};
    document.querySelectorAll('#sjColSettingsDropdown input[data-col]').forEach(function (inp) {
        vis[inp.getAttribute('data-col')] = inp.checked ? 1 : 0;
    });
    try { localStorage.setItem(sjColVisibilityKey(), JSON.stringify(vis)); } catch (e) {}
}

function sjLoadColumnVisibility() {
    var vis = null;
    try {
        var j = localStorage.getItem(sjColVisibilityKey());
        if (j) vis = JSON.parse(j);
    } catch (e) {}
    document.querySelectorAll('#sjColSettingsDropdown input[data-col]').forEach(function (inp) {
        var col = inp.getAttribute('data-col');
        var show = !vis || vis[col] === undefined || vis[col] === 1;
        inp.checked = show;
        sjToggleColumn(col, show);
    });
}

function initSjColumnSettings() {
    var dropdown = document.getElementById('sjColSettingsDropdown');
    var table = document.getElementById('ledgerTable');
    if (!dropdown || !table) return;
    dropdown.innerHTML = '';
    table.querySelectorAll('thead th[data-col]').forEach(function (th) {
        var col = th.getAttribute('data-col');
        if (!col || col === 'view' || col === 'action') return;
        var label = th.getAttribute('data-sj-title') || th.textContent.trim();
        var id = 'sj-col-vis-' + col;
        var wrap = document.createElement('label');
        wrap.innerHTML = '<input type="checkbox" id="' + id + '" data-col="' + col + '" checked> ' + label;
        dropdown.appendChild(wrap);
    });
    dropdown.querySelectorAll('input[data-col]').forEach(function (inp) {
        inp.addEventListener('change', function () {
            sjToggleColumn(inp.getAttribute('data-col'), inp.checked);
            sjSaveColumnVisibility();
        });
    });
    sjLoadColumnVisibility();
}

$(document).ready(function() {
    initStockJournalTable();
    initSjColumnSettings();

    document.querySelectorAll('.sj-header-dropdown-btn[data-sj-dropdown]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            sjToggleHeaderDropdown(btn.getAttribute('data-sj-dropdown'), btn);
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.page-header-actions .dropdown')) {
            sjCloseHeaderDropdowns();
        }
    });

    $('#sjClearFilterBtn').on('click', function () {
        clearSjAdvanceFilter();
    });

    function sjEscapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function sjPopulateProductsByMetal(metalId, keepProductId) {
        var sel = document.getElementById('sj_product_id');
        if (!sel) return;
        var mid = parseInt(metalId, 10) || 0;
        keepProductId = parseInt(keepProductId, 10) || 0;
        if (!mid) {
            sel.innerHTML = '<option value="">Select Product</option>';
            sel.value = '';
            sel.disabled = false;
            return;
        }
        sel.innerHTML = '<option value="">Loading...</option>';
        sel.disabled = true;
        fetch('ajax/get-products-by-metal.php?metal_id=' + encodeURIComponent(mid), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var html = '<option value="">Select Product</option>';
                var seen = {};
                if (data && data.success && Array.isArray(data.products)) {
                    data.products.forEach(function (p) {
                        var id = parseInt(p.id, 10) || 0;
                        if (id <= 0 || seen[id]) return;
                        seen[id] = true;
                        var name = String(p.name || p.alternate_name || '').trim();
                        if (!name) return;
                        html += '<option value="' + id + '">' + sjEscapeHtml(name) + '</option>';
                    });
                }
                sel.innerHTML = html;
                sel.disabled = false;
                if (keepProductId > 0) {
                    sel.value = String(keepProductId);
                    if (sel.value !== String(keepProductId)) sel.value = '';
                }
            })
            .catch(function () {
                sel.innerHTML = '<option value="">Select Product</option>';
                sel.disabled = false;
            });
    }

    var sjMetalEl = document.getElementById('sj_metal_id');
    var sjProductEl = document.getElementById('sj_product_id');
    if (sjMetalEl && sjProductEl) {
        sjMetalEl.addEventListener('change', function () {
            sjPopulateProductsByMetal(this.value, 0);
        });
        var sjInitialMetal = parseInt(sjMetalEl.value, 10) || 0;
        var sjInitialProduct = parseInt(sjProductEl.getAttribute('data-selected-product') || '0', 10) || 0;
        if (sjInitialMetal > 0 && sjProductEl.options.length <= 1) {
            sjPopulateProductsByMetal(sjInitialMetal, sjInitialProduct);
        }
    }

    $('#sjExportExcelMenu').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        sjCloseHeaderDropdowns();
        exportTableToExcel();
    });
    $('#sjExportPdfMenu').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        sjCloseHeaderDropdowns();
        printTable();
    });
});

function initSJTableColumnsAndResize() {
    var table = document.getElementById('ledgerTable');
    if (!table || typeof Sortable === 'undefined') return;
    var voucher = table.getAttribute('data-sj-voucher') || 'purchase_invoice';
    var orderKey = 'auragold_stock_journal_col_order_v2_' + voucher;
    var widthsKey = 'auragold_stock_journal_col_widths_v2_' + voucher;
    var theadRow = table.querySelector('thead tr');
    if (!theadRow) return;

    function getOrderFromThead() {
        return [].map.call(table.querySelectorAll('thead th[data-col]'), function (th) {
            return th.getAttribute('data-col');
        });
    }
    function syncBodyColumnOrder(order) {
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            if (tr.cells.length === 1) return;
            var byCol = {};
            tr.querySelectorAll('td[data-col]').forEach(function (td) {
                byCol[td.getAttribute('data-col')] = td;
            });
            order.forEach(function (k) {
                if (byCol[k]) tr.appendChild(byCol[k]);
            });
        });
    }
    function applyOrderArray(order) {
        if (!order || order.length !== SJ_TABLE_COL_KEYS.length) return;
        var need = {};
        SJ_TABLE_COL_KEYS.forEach(function (k) { need[k] = 0; });
        order.forEach(function (k) {
            if (Object.prototype.hasOwnProperty.call(need, k)) need[k]++;
        });
        if (!SJ_TABLE_COL_KEYS.every(function (k) { return need[k] === 1; })) return;
        if (order[0] !== 'view' || order[order.length - 1] !== 'action') return;
        var thByCol = {};
        theadRow.querySelectorAll('th[data-col]').forEach(function (th) {
            thByCol[th.getAttribute('data-col')] = th;
        });
        order.forEach(function (k) {
            if (thByCol[k]) theadRow.appendChild(thByCol[k]);
        });
        syncBodyColumnOrder(order);
    }
    function tryLoadOrder() {
        try {
            var j = localStorage.getItem(orderKey);
            if (!j) return;
            applyOrderArray(JSON.parse(j));
        } catch (e) {}
    }
    function saveOrder() {
        try {
            localStorage.setItem(orderKey, JSON.stringify(getOrderFromThead()));
        } catch (e) {}
    }
    function thMinWidthFloor(th) {
        var a = th.getAttribute('data-sj-min');
        if (a != null && a !== '') {
            var f = parseInt(a, 10);
            if (!isNaN(f) && f >= 40) return f;
        }
        var sw = th.style && th.style.minWidth;
        if (sw && /^\d+px$/.test(sw)) {
            f = parseInt(sw, 10);
            if (!isNaN(f) && f >= 40) return f;
        }
        return 40;
    }
    function applyWidths(w) {
        if (!w || typeof w !== 'object') return;
        table.querySelectorAll('thead th[data-col]').forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (k && w[k] != null) {
                var floor = thMinWidthFloor(th);
                var px = Math.max(floor, parseInt(w[k], 10) || 0);
                th.style.width = px + 'px';
                th.style.minWidth = px + 'px';
            }
        });
    }
    function tryLoadWidths() {
        try {
            var j = localStorage.getItem(widthsKey);
            if (j) applyWidths(JSON.parse(j));
        } catch (e) {}
    }
    function saveWidths() {
        var w = {};
        table.querySelectorAll('thead th[data-col]').forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (k) w[k] = Math.round(th.getBoundingClientRect().width);
        });
        try {
            localStorage.setItem(widthsKey, JSON.stringify(w));
        } catch (e) {}
    }
    if (sjLedgerSortableInstance && typeof sjLedgerSortableInstance.destroy === 'function') {
        try { sjLedgerSortableInstance.destroy(); } catch (e) {}
        sjLedgerSortableInstance = null;
    }
    tryLoadOrder();
    tryLoadWidths();
    var lastGoodOrder = getOrderFromThead().slice();
    sjLedgerSortableInstance = Sortable.create(theadRow, {
        animation: 150,
        handle: '.sj-th-drag',
        draggable: 'th.sj-th-reorder',
        filter: '.sj-th-fixed',
        preventOnFilter: true,
        ghostClass: 'sj-sortable-ghost',
        chosenClass: 'sj-sortable-chosen',
        onEnd: function () {
            var ord = getOrderFromThead();
            if (ord[0] !== 'view' || ord[ord.length - 1] !== 'action') {
                applyOrderArray(lastGoodOrder);
                return;
            }
            syncBodyColumnOrder(ord);
            saveOrder();
            lastGoodOrder = ord.slice();
        }
    });
    table.querySelectorAll('thead th .sj-col-resizer').forEach(function (handle) {
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var th = handle.closest('th');
            if (!th) return;
            var startX = e.clientX;
            var startW = th.getBoundingClientRect().width;
            var minW = thMinWidthFloor(th);
            function onMove(e2) {
                var dx = e2.clientX - startX;
                var w = Math.max(minW, Math.round(startW + dx));
                th.style.width = w + 'px';
                th.style.minWidth = w + 'px';
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.body.style.cursor = '';
                th.classList.remove('sj-col-resizing');
                saveWidths();
            }
            th.classList.add('sj-col-resizing');
            document.body.style.cursor = 'col-resize';
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    });
}

function initStockJournalTable() {
    var $table = $('#ledgerTable');
    if ($table.length === 0) {
        console.log('Table not found');
        return;
    }
    
    initSJTableColumnsAndResize();
    
    // Initialize search
    $('#customSearch').off('keyup change').on('keyup change', function() {
        var searchVal = $(this).val().toLowerCase();
        $table.find('tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            if (rowText.indexOf(searchVal) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Initialize sorting
    initTableSorting();
    
    // Connect export button
    $('#exportExcelBtn').off('click').on('click', function() {
        exportTableToExcel();
    });
    
    // Connect print button
    $('#printBtn').off('click').on('click', function() {
        printTable();
    });
    
    console.log('Stock Journal table initialized');
}

// Table sorting functionality
function initTableSorting() {
    var $table = $('#ledgerTable');
    var $headers = $table.find('thead th');
    var sortOrder = {};
    
    // Sortable column headers: pointer cursor, no ↕/↑/↓ icons
    $headers.each(function(index) {
        if (index > 0 && index < $headers.length - 1) { // Skip first (View) and last (Action) columns
            $(this).css('cursor', 'pointer');
            $(this).attr('data-sort-col', index);
        }
    });
    
    // Click handler for sorting
    $headers.on('click', function(e) {
        if ($(e.target).closest('.sj-th-drag, .sj-col-resizer').length) return;
        var colIndex = $(this).index();
        var totalCols = $headers.length;
        if (colIndex === 0 || colIndex === totalCols - 1) return; // Don't sort first and last columns
        
        var $tbody = $table.find('tbody');
        var rows = $tbody.find('tr').get();
        
        // Toggle sort order
        sortOrder[colIndex] = sortOrder[colIndex] === 'asc' ? 'desc' : 'asc';
        var isAsc = sortOrder[colIndex] === 'asc';
        
        // Sort rows
        rows.sort(function(a, b) {
            var aVal = $(a).find('td').eq(colIndex).text().trim();
            var bVal = $(b).find('td').eq(colIndex).text().trim();
            
            // Try to parse as number
            var aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
            var bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAsc ? aNum - bNum : bNum - aNum;
            }
            
            // String comparison
            return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
        
        // Reattach sorted rows
        $.each(rows, function(index, row) {
            $tbody.append(row);
        });
    });
    
    console.log('Table sorting initialized');
}

function exportTableToExcel() {
    var csv = [];
    var headers = [];
    $('#ledgerTable thead th[data-col]').each(function() {
        var col = $(this).attr('data-col');
        if (!col || col === 'view' || col === 'action' || $(this).hasClass('sj-col-hidden')) return;
        var title = $(this).attr('data-sj-title') || $(this).text();
        headers.push('"' + String(title).replace(/"/g, '""').replace(/↕|↑|↓/g, '').trim() + '"');
    });
    csv.push(headers.join(','));
    
    $('#ledgerTable tbody tr:visible').each(function() {
        if ($(this).find('td[colspan]').length) return;
        var rowData = [];
        $(this).find('td[data-col]').each(function() {
            var col = $(this).attr('data-col');
            if (!col || col === 'view' || col === 'action' || $(this).hasClass('sj-col-hidden')) return;
            rowData.push('"' + $(this).text().trim().replace(/"/g, '""') + '"');
        });
        if (rowData.length > 0) csv.push(rowData.join(','));
    });
    
    var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'Stock_Journal_' + new Date().toISOString().slice(0, 10) + '.csv';
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function printTable() {
    var printContents = '<html><head><title>Stock Journal Report</title>';
    printContents += '<style>';
    printContents += 'body { font-family: Arial, sans-serif; font-size: 11px; }';
    printContents += 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    printContents += 'th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }';
    printContents += 'th { background-color: #11294b; color: white; }';
    printContents += 'tr:nth-child(even) { background-color: #f2f2f2; }';
    printContents += 'h1 { text-align: center; color: #11294b; }';
    printContents += '@media print { th { background-color: #11294b !important; -webkit-print-color-adjust: exact; } }';
    printContents += '</style></head><body>';
    printContents += '<h1>Stock Journal Report</h1>';
    printContents += '<p>Generated on: ' + new Date().toLocaleString() + '</p>';
    printContents += '<table>';
    
    // Header (skip first View and last Action columns)
    var $headerCells = $('#ledgerTable thead th');
    var totalCols = $headerCells.length;
    printContents += '<thead><tr>';
    $('#ledgerTable thead th[data-col]').each(function() {
        var col = $(this).attr('data-col');
        if (!col || col === 'view' || col === 'action' || $(this).hasClass('sj-col-hidden')) return;
        var title = $(this).attr('data-sj-title') || $(this).text();
        printContents += '<th>' + String(title).replace(/↕|↑|↓/g, '').trim() + '</th>';
    });
    printContents += '</tr></thead>';
    
    // Body
    printContents += '<tbody>';
    $('#ledgerTable tbody tr:visible').each(function() {
        if ($(this).find('td[colspan]').length) return;
        printContents += '<tr>';
        $(this).find('td[data-col]').each(function() {
            var col = $(this).attr('data-col');
            if (!col || col === 'view' || col === 'action' || $(this).hasClass('sj-col-hidden')) return;
            printContents += '<td>' + $(this).text() + '</td>';
        });
        printContents += '</tr>';
    });
    printContents += '</tbody></table></body></html>';
    
    var printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert('Please allow pop-ups to export PDF.');
        return;
    }
    printWindow.document.write(printContents);
    printWindow.document.close();
    printWindow.focus();
    printWindow.onload = function () {
        printWindow.print();
    };
    setTimeout(function () {
        try { printWindow.print(); } catch (e) {}
    }, 400);
}
</script>
</body>
</html>

