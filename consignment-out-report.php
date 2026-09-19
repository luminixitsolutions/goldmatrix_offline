<?php 
session_start();
require_once 'config.php';

/** @var mysqli $conn */
$filter_type_raw = isset($_GET['type']) ? trim((string)$_GET['type']) : 'All';
$allowed_types = ['All', 'Consignment In', 'Consignment Out'];
$filter_type = in_array($filter_type_raw, $allowed_types, true) ? $filter_type_raw : 'All';

$date_from = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
$return_from = isset($_GET['return_from']) ? trim((string)$_GET['return_from']) : '';
$return_to = isset($_GET['return_to']) ? trim((string)$_GET['return_to']) : '';
$invoice_no_f = isset($_GET['invoice_no']) ? trim((string)$_GET['invoice_no']) : '';
$barcode_f = isset($_GET['barcode']) ? trim((string)$_GET['barcode']) : '';
$account_no_f = isset($_GET['account_no']) ? trim((string)$_GET['account_no']) : '';

$has_filter = isset($_GET['customer']) || isset($_GET['customer_id']);
$selected_customer = isset($_GET['customer']) ? esc($_GET['customer']) : '';
$selected_customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$show_all = !$has_filter || ($selected_customer === '' && $selected_customer_id === 0);

$customer_info = null;
$totals = [
    'qty' => 0,
    'purity_wt' => 0,
    'gross_wt' => 0,
    'final_wt' => 0,
    'amount' => 0,
    'net_amount' => 0,
];

/** Customers with activity in memo out and/or memo in — merged counts (distinct vouchers per ledger). */
$co_cust_rows = getList("
    SELECT COALESCE(customer_id, 0) AS customer_id, TRIM(customer_name) AS customer_name, COUNT(DISTINCT co.id) AS consignment_count
    FROM tbl_consignment_out co
    WHERE co.status = 'active'
    GROUP BY COALESCE(customer_id, 0), TRIM(customer_name)
");
if (!is_array($co_cust_rows)) {
    $co_cust_rows = [];
}
$ci_cust_rows = getList("
    SELECT COALESCE(customer_id, 0) AS customer_id, TRIM(customer_name) AS customer_name, COUNT(DISTINCT ci.id) AS consignment_count
    FROM tbl_consignment_in ci
    WHERE ci.status = 'active'
    GROUP BY COALESCE(customer_id, 0), TRIM(customer_name)
");
if (!is_array($ci_cust_rows)) {
    $ci_cust_rows = [];
}
$cust_merge = [];
foreach (array_merge($co_cust_rows, $ci_cust_rows) as $row) {
    $cid = (int)($row['customer_id'] ?? 0);
    $nm = trim((string)($row['customer_name'] ?? ''));
    $key = $cid . '|' . mb_strtolower($nm);
    if (!isset($cust_merge[$key])) {
        $cust_merge[$key] = ['customer_id' => $cid, 'customer_name' => $nm, 'consignment_count' => 0];
    }
    $cust_merge[$key]['consignment_count'] += (int)($row['consignment_count'] ?? 0);
}
$customers = array_values($cust_merge);
usort($customers, function ($a, $b) {
    return strcasecmp((string)($a['customer_name'] ?? ''), (string)($b['customer_name'] ?? ''));
});

$account_customer_ids = null;
$account_nomatch = false;
if ($account_no_f !== '') {
    $a_esc = mysqli_real_escape_string($conn, $account_no_f);
    $acr = getList("
        SELECT id FROM tbl_customers
        WHERE status = 1
        AND (
            IFNULL(bank_account_no,'') LIKE '%$a_esc%'
            OR IFNULL(registration_no,'') LIKE '%$a_esc%'
        )
        LIMIT 500
    ");
    if (!is_array($acr)) {
        $acr = [];
    }
    $account_customer_ids = array_values(array_unique(array_map('intval', array_column($acr, 'id'))));
    if (empty($account_customer_ids)) {
        $account_nomatch = true;
    }
}

$consignment_items = [];

if (!function_exists('memo_report_image_url')) {
    /**
     * @param mixed $json images JSON from tbl_product_characteristics.images
     */
    function memo_report_image_url($json): string
    {
        global $SiteUrl;
        if ($json === null || $json === '') {
            return '';
        }
        $d = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($d)) {
            return '';
        }
        $rel = '';
        if (!empty($d['primary'])) {
            $rel = (string)$d['primary'];
        } elseif (!empty($d['images']) && is_array($d['images']) && !empty($d['images'][0])) {
            $rel = (string)$d['images'][0];
        }
        if ($rel === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $rel)) {
            return $rel;
        }

        return rtrim($SiteUrl ?? '', '/') . '/' . auragold_uploads_public_rel(ltrim(str_replace('\\', '/', $rel), '/'));
    }
}

if (!function_exists('memo_report_carat_wt_cell_html')) {
    function memo_report_carat_wt_cell_html(array $item): string
    {
        $c = trim((string)($item['carat'] ?? ''));
        if ($c !== '') {
            return htmlspecialchars($c);
        }
        $pw = (float)($item['purity_weight'] ?? 0);
        if ($pw > 0) {
            return htmlspecialchars(number_format($pw, 3));
        }

        return '—';
    }
}

/** Build reusable WHERE snippets (consignment OUT = co, items = coi). */
$build_where_out = function (array &$parts) use ($conn, $show_all, $selected_customer_id, $selected_customer, $date_from, $date_to, $invoice_no_f, $barcode_f, $account_customer_ids, $account_no_f, $account_nomatch) {
    $parts[] = "co.status = 'active'";
    if (!$show_all) {
        if ($selected_customer_id > 0) {
            $parts[] = '(co.customer_id = ' . (int)$selected_customer_id . ')';
        } elseif ($selected_customer !== '') {
            $nm = mysqli_real_escape_string($conn, $selected_customer);
            $parts[] = "co.customer_name = '$nm'";
        }
    }
    if ($account_no_f !== '' && !$account_nomatch && is_array($account_customer_ids) && !empty($account_customer_ids)) {
        $parts[] = 'co.customer_id IN (' . implode(',', $account_customer_ids) . ')';
    }
    if ($date_from !== '') {
        $df = mysqli_real_escape_string($conn, $date_from);
        $parts[] = "co.consignment_date >= '$df'";
    }
    if ($date_to !== '') {
        $dt = mysqli_real_escape_string($conn, $date_to);
        $parts[] = "co.consignment_date <= '$dt'";
    }
    if ($invoice_no_f !== '') {
        $inv = mysqli_real_escape_string($conn, $invoice_no_f);
        $parts[] = "co.consignment_no LIKE '%$inv%'";
    }
    if ($barcode_f !== '') {
        $bc = mysqli_real_escape_string($conn, $barcode_f);
        $parts[] = "coi.barcode LIKE '%$bc%'";
    }
};

$build_where_in = function (array &$parts) use ($conn, $show_all, $selected_customer_id, $selected_customer, $date_from, $date_to, $invoice_no_f, $barcode_f, $account_customer_ids, $account_no_f, $account_nomatch) {
    $parts[] = "ci.status = 'active'";
    if (!$show_all) {
        if ($selected_customer_id > 0) {
            $parts[] = '(ci.customer_id = ' . (int)$selected_customer_id . ')';
        } elseif ($selected_customer !== '') {
            $nm = mysqli_real_escape_string($conn, $selected_customer);
            $parts[] = "ci.customer_name = '$nm'";
        }
    }
    if ($account_no_f !== '' && !$account_nomatch && is_array($account_customer_ids) && !empty($account_customer_ids)) {
        $parts[] = 'ci.customer_id IN (' . implode(',', $account_customer_ids) . ')';
    }
    if ($date_from !== '') {
        $df = mysqli_real_escape_string($conn, $date_from);
        $parts[] = "ci.consignment_date >= '$df'";
    }
    if ($date_to !== '') {
        $dt = mysqli_real_escape_string($conn, $date_to);
        $parts[] = "ci.consignment_date <= '$dt'";
    }
    if ($invoice_no_f !== '') {
        $inv = mysqli_real_escape_string($conn, $invoice_no_f);
        $parts[] = "ci.consignment_no LIKE '%$inv%'";
    }
    if ($barcode_f !== '') {
        $bc = mysqli_real_escape_string($conn, $barcode_f);
        $parts[] = "cii.barcode LIKE '%$bc%'";
    }
};

/** Prefer pc.images when the optional column exists (avoids mysqli exception on schemas without it). */
$pc_images_expr = 'NULL';
try {
    $r_pc_img = mysqli_query(
        $conn,
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_product_characteristics' AND COLUMN_NAME = 'images' LIMIT 1"
    );
    if ($r_pc_img && mysqli_num_rows($r_pc_img) > 0) {
        $pc_images_expr = 'pc.images';
    }
    if ($r_pc_img) {
        mysqli_free_result($r_pc_img);
    }
} catch (Throwable $ePcCol) {
    $pc_images_expr = 'NULL';
}

if (!$account_nomatch) {
    $want_out = ($filter_type === 'All' || $filter_type === 'Consignment Out');
    $want_in = ($filter_type === 'All' || $filter_type === 'Consignment In');

    if ($want_out) {
        $w = [];
        $build_where_out($w);
        $where_out = implode(' AND ', $w);
        $rows_out = getList("
            SELECT 
                'Consignment Out' AS movement_type,
                co.id AS consignment_id,
                co.customer_id AS party_customer_id,
                co.consignment_no,
                co.consignment_date,
                co.customer_name,
                coi.id AS item_id,
                coi.barcode,
                coi.product_name,
                coi.design_no,
                coi.category,
                coi.carat,
                coi.quantity,
                coi.gross_weight,
                coi.net_weight,
                coi.final_weight,
                coi.purity,
                coi.purity_weight,
                coi.rate,
                coi.amount,
                coi.net_amount,
                coi.net_amt_with_tax,
                $pc_images_expr AS item_images_json,
                COALESCE(NULLIF(TRIM(IFNULL(pc.sku_code,'')),''), TRIM(IFNULL(coi.design_no,''))) AS item_code_disp,
                COALESCE(NULLIF(TRIM(IFNULL(cust.name,'')),''), co.customer_name) AS ledger_name,
                CASE 
                    WHEN IFNULL(TRIM(IFNULL(cust.bank_account_no,'')),'') != '' THEN TRIM(cust.bank_account_no)
                    WHEN IFNULL(TRIM(IFNULL(cust.registration_no,'')),'') != '' THEN TRIM(cust.registration_no)
                    ELSE ''
                END AS account_no_disp
            FROM tbl_consignment_out co
            INNER JOIN tbl_consignment_out_items coi ON co.id = coi.consignment_id
            LEFT JOIN tbl_customers cust ON cust.id = co.customer_id AND cust.status = 1
            LEFT JOIN tbl_product_characteristics pc ON pc.id = coi.product_characteristic_id
            WHERE $where_out
            ORDER BY co.consignment_date DESC, co.id DESC, coi.id ASC
        ");
        if (is_array($rows_out)) {
            foreach ($rows_out as $r) {
                $consignment_items[] = $r;
            }
        }
    }

    if ($want_in) {
        $w = [];
        $build_where_in($w);
        $where_in = implode(' AND ', $w);
        $rows_in = getList("
            SELECT 
                'Consignment In' AS movement_type,
                ci.id AS consignment_id,
                ci.customer_id AS party_customer_id,
                ci.consignment_no,
                ci.consignment_date,
                ci.customer_name,
                cii.id AS item_id,
                cii.barcode,
                cii.product_name,
                cii.design_no,
                cii.category,
                cii.carat,
                cii.quantity,
                cii.gross_weight,
                cii.net_weight,
                cii.final_weight,
                cii.purity,
                cii.purity_weight,
                cii.rate,
                cii.amount,
                cii.net_amount,
                cii.net_amt_with_tax,
                $pc_images_expr AS item_images_json,
                COALESCE(NULLIF(TRIM(IFNULL(pc.sku_code,'')),''), TRIM(IFNULL(cii.design_no,''))) AS item_code_disp,
                COALESCE(NULLIF(TRIM(IFNULL(cust.name,'')),''), ci.customer_name) AS ledger_name,
                CASE 
                    WHEN IFNULL(TRIM(IFNULL(cust.bank_account_no,'')),'') != '' THEN TRIM(cust.bank_account_no)
                    WHEN IFNULL(TRIM(IFNULL(cust.registration_no,'')),'') != '' THEN TRIM(cust.registration_no)
                    ELSE ''
                END AS account_no_disp
            FROM tbl_consignment_in ci
            INNER JOIN tbl_consignment_in_items cii ON ci.id = cii.consignment_id
            LEFT JOIN tbl_customers cust ON cust.id = ci.customer_id AND cust.status = 1
            LEFT JOIN tbl_product_characteristics pc ON pc.id = cii.product_characteristic_id
            WHERE $where_in
            ORDER BY ci.consignment_date DESC, ci.id DESC, cii.id ASC
        ");
        if (is_array($rows_in)) {
            foreach ($rows_in as $r) {
                $consignment_items[] = $r;
            }
        }
    }

    usort($consignment_items, function ($a, $b) {
        $d = strcmp((string)($b['consignment_date'] ?? ''), (string)($a['consignment_date'] ?? ''));
        if ($d !== 0) {
            return $d;
        }
        return (int)($b['consignment_id'] ?? 0) <=> (int)($a['consignment_id'] ?? 0);
    });
}

if (!$show_all) {
    if ($selected_customer_id > 0) {
        $customer_info = getRecord('SELECT * FROM tbl_customers WHERE id = ' . (int)$selected_customer_id);
    }
    if (!$customer_info && $selected_customer !== '') {
        $sn = mysqli_real_escape_string($conn, $selected_customer);
        $customer_info = getRecord("SELECT * FROM tbl_customers WHERE name = '$sn' LIMIT 1");
    }
}

foreach ($consignment_items as $item) {
    $totals['qty'] += (int)($item['quantity'] ?? 0);
    $totals['purity_wt'] += (float)($item['purity_weight'] ?? 0);
    $totals['gross_wt'] += (float)($item['gross_weight'] ?? 0);
    $totals['final_wt'] += (float)($item['final_weight'] ?? $item['net_weight'] ?? 0);
    $totals['amount'] += (float)($item['amount'] ?? 0);
    $totals['net_amount'] += (float)($item['net_amount'] ?? $item['net_amt_with_tax'] ?? 0);
}

$filter_active_count = 0;
if ($filter_type !== 'All') {
    $filter_active_count++;
}
foreach (['date_from', 'date_to', 'invoice_no', 'barcode', 'account_no'] as $fk) {
    if (!empty($_GET[$fk])) {
        $filter_active_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Memo / Consignment Items - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <?php include 'header-script.php';?>
</head>

<style>
/* Brand: navy + gold (aligned with AURA GOLD / GoldMatrix logos) */
:root {
    --brand-navy: #0c2340;
    --brand-navy-mid: #152d52;
    --brand-navy-hover: #1a3a5c;
    --brand-gold: #c9a227;
    --brand-gold-bright: #d4af37;
    --brand-gold-soft: #e6cf7a;
    --brand-gold-pale: #f5eed9;
    --brand-header-tint: #e6edf5;
}

html, body {
    overflow-x: hidden !important;
    height: 100vh;
    background: #f4f5f7;
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

/* Main Layout */
.report-layout {
    display: flex;
    flex: 1;
    overflow: hidden;
    gap: 0;
}

/* Left Panel - Customer List */
.customer-panel {
    width: 280px;
    min-width: 280px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.customer-panel-header {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}

.customer-panel-header h6 {
    margin: 0 0 10px 0;
    color: #1e293b;
    font-weight: 600;
    font-size: 12px;
}

.customer-panel-header .header-row {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #64748b;
    padding: 5px 0;
    border-bottom: 1px solid #e2e8f0;
}

.customer-search {
    padding: 10px 15px;
    border-bottom: 1px solid #e2e8f0;
}

.customer-search input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 13px;
}

.customer-search input:focus {
    outline: none;
    border-color: var(--brand-navy);
}

.customer-list {
    flex: 1;
    overflow-y: auto;
}

.customer-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: inherit;
}

.customer-item:hover {
    background: #f8fafc;
}

.customer-item.active {
    background: var(--brand-gold-pale);
    border-left: 3px solid var(--brand-navy);
}

.customer-item .customer-name {
    font-size: 13px;
    color: #1e293b;
    font-weight: 500;
}

.customer-item .customer-count {
    background: linear-gradient(135deg, var(--brand-gold-bright) 0%, var(--brand-gold) 100%);
    color: var(--brand-navy);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

.customer-panel-footer {
    padding: 10px 15px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.customer-panel-footer .total-count {
    font-size: 12px;
    color: #64748b;
}

.pagination-mini {
    display: flex;
    gap: 5px;
}

.pagination-mini button {
    width: 24px;
    height: 24px;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #64748b;
}

.pagination-mini button:hover {
    background: #f8fafc;
}

.pagination-mini button.active {
    background: var(--brand-navy);
    color: var(--brand-gold-soft);
    border-color: rgba(212, 175, 55, 0.45);
}

/* Right Panel - Items Grid */
.items-panel {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f8fafc;
}

.items-header {
    padding: 15px 20px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.items-header-left {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.items-header-left h5 {
    margin: 0;
    color: #1e293b;
    font-size: 16px;
    font-weight: 600;
}

.items-header-left .customer-details {
    font-size: 12px;
    color: #64748b;
}

.items-header-left .customer-details span {
    margin-right: 15px;
}

.items-header-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-filter, .btn-export {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-filter {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
}

.btn-filter:hover {
    border-color: var(--brand-navy);
    color: var(--brand-navy);
}

.btn-export {
    background: var(--brand-navy);
    border: 1px solid rgba(212, 175, 55, 0.35);
    color: var(--brand-gold-soft);
}

.btn-export:hover {
    background: var(--brand-navy-hover);
    color: #fff;
}

/* Items Table */
.items-table-container {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 15px 20px;
    overflow: hidden;
}

.memo-table-scroll-x {
    flex: 1;
    min-height: 0;
    overflow-x: auto;
    overflow-y: auto;
    max-width: 100%;
    -webkit-overflow-scrolling: touch;
}

.memo-table-scroll-x .memo-report-table {
    width: max-content;
    min-width: 100%;
    table-layout: auto;
}


.items-table {
    width: 100%;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border-collapse: collapse;
    font-size: 13px;
}

.items-table thead th {
    background: var(--brand-header-tint);
    color: var(--brand-navy);
    font-weight: 600;
    padding: 12px 10px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    position: sticky;
    top: 0;
    white-space: nowrap;
}

.memo-table-scroll-x .items-table thead th {
    z-index: 2;
}

.memo-report-table td[data-col-key].memo-col-hidden,
.memo-report-table th[data-col-key].memo-col-hidden {
    display: none !important;
}

.items-table thead th:first-child {
    border-radius: 8px 0 0 0;
}

.items-table thead th:last-child {
    border-radius: 0 8px 0 0;
}

.items-table tbody td {
    padding: 10px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.items-table tbody tr:hover {
    background: #fafafa;
}

.items-table tbody tr:last-child td:first-child {
    border-radius: 0 0 0 8px;
}

.items-table tbody tr:last-child td:last-child {
    border-radius: 0 0 8px 0;
}

.items-table .text-right {
    text-align: right;
}

.items-table .text-center {
    text-align: center;
}

.items-table .product-cell {
    max-width: 200px;
}

.items-table .product-name {
    font-weight: 500;
    color: #1e293b;
    display: block;
}

.items-table .product-code {
    font-size: 11px;
    color: #94a3b8;
}

.consignment-badge {
    background: var(--brand-gold-pale);
    color: var(--brand-navy);
    border: 1px solid rgba(201, 162, 39, 0.35);
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.action-icons {
    display: flex;
    gap: 5px;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
    min-height: 32px;
}

.action-icon {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
}

.action-icon:hover {
    background: var(--brand-gold-pale);
    color: var(--brand-navy);
    border-color: rgba(201, 162, 39, 0.45);
}

.action-icon.action-print:hover {
    background: #fffbeb;
    color: var(--brand-navy);
    border-color: rgba(201, 162, 39, 0.55);
}

.action-icon.delete:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}

/* Footer Totals */
.items-table tfoot td {
    background: var(--brand-header-tint);
    font-weight: 600;
    padding: 12px 10px;
    border-top: 2px solid rgba(201, 162, 39, 0.25);
    color: var(--brand-navy);
}

.memo-report-table .memo-th {
    position: relative;
    padding-right: 16px;
    vertical-align: bottom;
}

.memo-report-table .memo-col-drag {
    display: inline-block;
    margin-right: 4px;
    cursor: grab;
    color: rgba(12, 35, 64, 0.35);
    vertical-align: middle;
}

.memo-report-table .memo-col-drag:active {
    cursor: grabbing;
}

.memo-report-table .memo-col-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    cursor: col-resize;
    z-index: 2;
}

.memo-report-table .memo-col-resizer:hover {
    background: rgba(201, 162, 39, 0.35);
}

.memo-report-table.memo-resizing-table {
    cursor: col-resize;
}

.memo-report-table.memo-resizing-table * {
    user-select: none;
}

.memo-report-table .memo-img-cell {
    text-align: center;
}

.memo-line-img {
    max-height: 40px;
    max-width: 48px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
}

.memo-img-ph {
    color: #cbd5e1;
    font-size: 12px;
}

/* Items Footer */
.items-footer {
    padding: 12px 20px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.items-footer .showing-info {
    font-size: 13px;
    color: #64748b;
}

.items-footer .per-page select {
    padding: 6px 30px 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 13px;
    background: #fff;
    cursor: pointer;
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #94a3b8;
    text-align: center;
    padding: 40px;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state h5 {
    color: #64748b;
    margin-bottom: 5px;
}

.empty-state p {
    font-size: 13px;
}


/* Create Button with Dropdown */
.create-dropdown {
    position: relative;
    display: inline-block;
}

.btn-create {
    background: linear-gradient(135deg, var(--brand-gold-bright) 0%, var(--brand-gold) 100%);
    border: 1px solid rgba(12, 35, 64, 0.15);
    color: var(--brand-navy);
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-create:hover {
    background: linear-gradient(135deg, var(--brand-gold-soft) 0%, var(--brand-gold-bright) 100%);
    color: var(--brand-navy);
}

.btn-create:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    box-shadow: none;
}

.btn-create:disabled:hover {
    background: linear-gradient(135deg, var(--brand-gold-bright) 0%, var(--brand-gold) 100%);
    color: var(--brand-navy);
}

.create-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 200px;
    z-index: 1000;
    margin-top: 5px;
}

.create-dropdown-menu.show {
    display: block;
}

.create-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    color: #475569;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s;
}

.create-dropdown-item:hover {
    background: #f8fafc;
    color: var(--brand-navy);
}

.create-dropdown-item i {
    font-size: 16px;
    color: #94a3b8;
}

.create-dropdown-item:hover i {
    color: var(--brand-navy);
}

.create-dropdown-item.is-submenu-disabled,
.create-dropdown-item.is-submenu-disabled:hover {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
    color: #94a3b8;
    background: transparent !important;
}

.create-dropdown-item.is-submenu-disabled:hover i,
.create-dropdown-item.is-submenu-disabled i {
    color: #cbd5e1;
}

.btn-filter-icon, .btn-refresh-icon {
    position: relative;
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-filter-icon:hover, .btn-refresh-icon:hover {
    background: #f8fafc;
    border-color: var(--brand-navy);
    color: var(--brand-navy);
}

.filter-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: linear-gradient(135deg, var(--brand-gold-bright), var(--brand-gold));
    color: var(--brand-navy);
    font-size: 10px;
    font-weight: 600;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Export Dropdown */
.export-dropdown {
    position: relative;
    display: inline-block;
}

.export-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 160px;
    z-index: 1000;
    margin-top: 5px;
}

.export-dropdown-menu.show {
    display: block;
}

/* Columns visibility (settings gear) */
.column-settings-dropdown {
    position: relative;
    display: inline-block;
}

.column-settings-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    width: 280px;
    max-height: min(380px, 70vh);
    z-index: 1000;
    margin-top: 5px;
    padding: 0 14px 12px 14px;
    overflow-x: hidden;
}

.column-settings-menu.show {
    display: block;
}

.column-settings-menu .column-settings-heading {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #fff;
    padding-top: 12px;
    padding-bottom: 8px;
    margin: 0 -14px 4px -14px;
    padding-left: 14px;
    padding-right: 14px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.column-settings-rows {
    max-height: min(260px, 52vh);
    overflow-y: auto;
}

.column-settings-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 0;
    border-bottom: 1px solid #f8fafc;
    font-size: 13px;
    color: #334155;
}

.column-settings-row:last-child {
    border-bottom: none;
}

.column-settings-row label {
    flex: 1;
    margin: 0;
    cursor: pointer;
    font-weight: 500;
}

.column-settings-actions {
    padding-top: 10px;
    margin-top: 6px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.column-settings-actions button {
    font-size: 12px;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: var(--brand-navy);
}

.column-settings-actions button:hover {
    border-color: var(--brand-navy);
    background: var(--brand-gold-pale);
}

.export-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    color: #475569;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s;
}

.export-dropdown-item:hover {
    background: #f8fafc;
    color: var(--brand-navy);
}

/* Advanced Filter Modal */
.filter-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.4);
    z-index: 2000;
    align-items: flex-start;
    justify-content: center;
    padding-top: 100px;
}

.filter-modal.show {
    display: flex;
}

.filter-modal-content {
    background: #fff;
    border-radius: 8px;
    width: 95%;
    max-width: 900px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.filter-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.filter-modal-header h5 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}

.filter-modal-close {
    background: none;
    border: none;
    font-size: 20px;
    color: #94a3b8;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.filter-modal-close:hover {
    color: #64748b;
}

.filter-modal-body {
    padding: 20px;
}

.filter-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.filter-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-form-group label {
    font-size: 13px;
    font-weight: 500;
    color: #475569;
}

.filter-form-group input,
.filter-form-group select {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 13px;
    transition: border-color 0.2s;
}

.filter-form-group input:focus,
.filter-form-group select:focus {
    outline: none;
    border-color: var(--brand-navy);
}

.date-range-inputs {
    display: flex;
    align-items: center;
    gap: 10px;
}

.date-range-inputs .date-input-wrapper {
    position: relative;
    flex: 1;
    min-width: 140px;
}

.date-range-inputs .date-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    min-width: 130px;
}

.date-range-inputs .date-input:focus {
    outline: none;
    border-color: var(--brand-navy);
}

.date-icon-overlay {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
    font-size: 12px;
}

.date-separator {
    color: #64748b;
    font-size: 13px;
    white-space: nowrap;
}

.date-clear-btn {
    color: #94a3b8;
    cursor: pointer;
    font-size: 16px;
    padding: 4px;
    transition: color 0.2s;
}

.date-clear-btn:hover {
    color: #ef4444;
}

.filter-modal-footer {
    display: flex;
    justify-content: center;
    gap: 12px;
    padding: 16px 20px;
    border-top: 1px solid #e2e8f0;
}

.btn-apply-filter {
    background: var(--brand-navy);
    border: none;
    color: #fff;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-apply-filter:hover {
    background: var(--brand-navy-hover);
    box-shadow: 0 0 0 1px rgba(212, 175, 55, 0.35);
}

.btn-clear-filter {
    background: #fff;
    border: 1px solid rgba(201, 162, 39, 0.55);
    color: var(--brand-navy);
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-clear-filter:hover {
    background: var(--brand-gold-pale);
    border-color: var(--brand-gold);
}

/* Customer Name Column Style */
.customer-name-cell {
    color: var(--brand-navy);
    font-weight: 500;
}

/* Mobile: allow document scroll (newcss locks html/body); stack customer list + items */
@media (max-width: 991.98px) {
    html {
        height: auto !important;
        min-height: 100dvh !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    body {
        height: auto !important;
        min-height: 100dvh !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    .layout-wrapper {
        height: auto !important;
        min-height: 100dvh !important;
        overflow: visible !important;
    }
    .layout-content {
        height: auto !important;
        min-height: 0;
        max-width: 100%;
        overflow-x: hidden !important;
        overflow-y: visible !important;
        padding-bottom: 1.25rem !important;
    }
    .container-fluid {
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow: visible !important;
    }
    .report-layout {
        flex-direction: column;
        flex: 1 1 auto;
        overflow: visible;
        min-height: 0;
    }
    .customer-panel {
        width: 100% !important;
        min-width: 0 !important;
        max-height: min(42dvh, 280px);
        flex: 0 0 auto;
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
    }
    .items-panel {
        flex: 1 1 auto;
        min-height: 260px;
        overflow: visible;
    }
    .items-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding: 12px 14px;
    }
    .items-header-right {
        flex-wrap: wrap;
        width: 100%;
        justify-content: flex-start;
        gap: 8px;
    }
    .items-table-container {
        overflow: visible;
        padding: 10px 12px;
    }
    .items-footer {
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 14px;
        align-items: flex-start;
    }
}
</style>

<body>
<?php include 'sidebar.php'; ?>

<div class="layout-content">
<div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">

<!-- Main Layout -->
<div class="report-layout">
    
    <!-- Left Panel - Customer List -->
    <div class="customer-panel">
        <div class="customer-panel-header">
            <h6>Customer List</h6>
            <div class="header-row">
                <span>Customer</span>
                <span>No. of Co...</span>
            </div>
        </div>
        
        <div class="customer-search">
            <input type="text" id="customerSearch" placeholder="Search">
        </div>
        
        <div class="customer-list" id="customerList">
            <!-- Total Row - Show All -->
            <a href="?" class="customer-item <?php echo $show_all ? 'active' : ''; ?>">
                <span class="customer-name" style="font-weight: 600;">Total</span>
                <span class="customer-count"><?php echo count($customers); ?></span>
            </a>
            
            <?php foreach ($customers as $customer): ?>
            <a href="?customer=<?php echo urlencode($customer['customer_name']); ?>&customer_id=<?php echo (int)$customer['customer_id']; ?>" 
               class="customer-item <?php echo (!$show_all && ($selected_customer === $customer['customer_name'] || $selected_customer_id === (int)$customer['customer_id'])) ? 'active' : ''; ?>">
                <span class="customer-name"><?php echo htmlspecialchars($customer['customer_name']); ?></span>
                <span class="customer-count"><?php echo (int)$customer['consignment_count']; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        
        <div class="customer-panel-footer">
            <span class="total-count">Showing 1 to <?php echo count($customers); ?> entries</span>
            <div class="pagination-mini">
                <button><i class="feather icon-chevron-left"></i></button>
                <button class="active">1</button>
                <button><i class="feather icon-chevron-right"></i></button>
            </div>
        </div>
    </div>
    
    <!-- Right Panel - Items Grid -->
    <div class="items-panel">
        <!-- Header with customer info -->
        <div class="items-header">
            <div class="items-header-left">
                <?php if ($show_all): ?>
                <h5>All Consignment Items</h5>
                <div class="customer-details">
                    <span>Showing consignment <?php echo htmlspecialchars($filter_type === 'All' ? 'in and out' : $filter_type); ?> line items</span>
                </div>
                <?php else: ?>
                <h5><?php echo htmlspecialchars($selected_customer); ?></h5>
                <div class="customer-details">
                    <span>Contact: <?php echo $customer_info ? htmlspecialchars($customer_info['mobile_no'] ?? 'NA') : 'NA'; ?></span>
                    <span>Email: <?php echo $customer_info ? htmlspecialchars($customer_info['email'] ?? 'NA') : 'NA'; ?></span>
                    <span>Trade No.: <?php echo $customer_info ? htmlspecialchars($customer_info['trade_no'] ?? 'NA') : 'NA'; ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="items-header-right">
                <!-- Create Button with Dropdown -->
                <div class="create-dropdown">
                    <button type="button" class="btn-create" id="btnCreateDropdown" disabled title="Select at least one row" onclick="toggleCreateDropdown()">
                        Create <i class="feather icon-chevron-down"></i>
                    </button>
                    <div class="create-dropdown-menu" id="createDropdownMenu">
                        <a href="sale-invoice.php" class="create-dropdown-item">
                            <i class="feather icon-file-text"></i> Create Sale Invoice
                        </a>
                        <a href="javascript:void(0)" id="memoReportCreateConsInItem" onclick="createConsignmentIn()" class="create-dropdown-item" title="Starts a Consignment In from memo out lines only">
                            <i class="feather icon-package"></i> Create Memo / Cons. In
                        </a>
                        <a href="sale-quotations.php" class="create-dropdown-item">
                            <i class="feather icon-file"></i> Create Sale Quotation
                        </a>
                    </div>
                </div>
                
                <!-- Filter Button -->
                <button class="btn-filter-icon" onclick="openFilterModal()" title="Filter">
                    <i class="feather icon-filter"></i>
                    <span class="filter-badge" id="filterBadge" style="<?php echo $filter_active_count > 0 ? '' : 'display: none;'; ?>"><?php echo (int)$filter_active_count; ?></span>
                </button>
                
                <!-- Refresh Button -->
                <button class="btn-refresh-icon" onclick="location.reload()" title="Refresh">
                    <i class="feather icon-refresh-cw"></i>
                </button>

                <div class="column-settings-dropdown">
                    <button type="button" class="btn-filter-icon" onclick="toggleColumnSettingsMenu()" title="Show / hide columns">
                        <i class="feather icon-settings"></i>
                    </button>
                    <div class="column-settings-menu" id="memoColumnSettingsMenu">
                        <div class="column-settings-heading">Visible columns</div>
                        <div class="column-settings-rows" id="memoColumnSettingsRows"></div>
                        <div class="column-settings-actions">
                            <button type="button" onclick="memoReportShowAllColumns()">Show all</button>
                        </div>
                    </div>
                </div>
                
                <!-- Export Button with Dropdown -->
                <div class="export-dropdown">
                    <button class="btn-export" onclick="toggleExportDropdown()">
                        Export <i class="feather icon-chevron-down"></i>
                    </button>
                    <div class="export-dropdown-menu" id="exportDropdownMenu">
                        <a href="javascript:void(0)" onclick="exportToExcel()" class="export-dropdown-item">
                            <i class="feather icon-file"></i> Export to Excel
                        </a>
                        <a href="javascript:void(0)" onclick="exportToPDF()" class="export-dropdown-item">
                            <i class="feather icon-file-text"></i> Export to PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="items-table-container">
            <?php if (count($consignment_items) > 0): ?>
            <div class="memo-table-scroll-x">
            <table class="items-table memo-report-table" id="memoReportTable">
                <thead>
                    <tr id="memoReportTheadRow">
                        <th data-col-key="active" class="memo-th text-center" style="width:52px;min-width:52px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span><input type="checkbox" id="selectAll" title="Select all"><span class="memo-col-resizer"></span></th>
                        <th data-col-key="image" class="memo-th" style="width:56px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Image<span class="memo-col-resizer"></span></th>
                        <th data-col-key="invoice_no" class="memo-th" style="min-width:100px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Invoice No.<span class="memo-col-resizer"></span></th>
                        <th data-col-key="voucher_type" class="memo-th" style="min-width:120px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Voucher Type<span class="memo-col-resizer"></span></th>
                        <th data-col-key="description" class="memo-th" style="min-width:160px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Description<span class="memo-col-resizer"></span></th>
                        <th data-col-key="doc_date" class="memo-th" style="min-width:92px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Date<span class="memo-col-resizer"></span></th>
                        <th data-col-key="est_return" class="memo-th" style="min-width:100px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Est. Return Dt.<span class="memo-col-resizer"></span></th>
                        <th data-col-key="qty" class="memo-th text-right" style="min-width:64px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Qty<span class="memo-col-resizer"></span></th>
                        <th data-col-key="carat_wt" class="memo-th text-right" style="min-width:88px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Carat/Wt.<span class="memo-col-resizer"></span></th>
                        <th data-col-key="gross_wt" class="memo-th text-right" style="min-width:88px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Gross Wt.<span class="memo-col-resizer"></span></th>
                        <th data-col-key="final_wt" class="memo-th text-right" style="min-width:88px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Final Wt.<span class="memo-col-resizer"></span></th>
                        <th data-col-key="amount" class="memo-th text-right" style="min-width:88px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Amount<span class="memo-col-resizer"></span></th>
                        <th data-col-key="net_amount" class="memo-th text-right" style="min-width:88px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Net Amount<span class="memo-col-resizer"></span></th>
                        <th data-col-key="barcode" class="memo-th" style="min-width:100px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Barcode No.<span class="memo-col-resizer"></span></th>
                        <th data-col-key="item_code" class="memo-th" style="min-width:90px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>ItemCode<span class="memo-col-resizer"></span></th>
                        <th data-col-key="ledger_name" class="memo-th" style="min-width:130px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Ledger Name<span class="memo-col-resizer"></span></th>
                        <th data-col-key="account_no" class="memo-th" style="min-width:100px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Account No<span class="memo-col-resizer"></span></th>
                        <th data-col-key="actions" class="memo-th" style="min-width:118px;"><span class="memo-col-drag" title="Drag column"><i class="feather icon-move"></i></span>Action<span class="memo-col-resizer"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consignment_items as $item):
                        $imgHref = memo_report_image_url($item['item_images_json'] ?? '');
                        ?>
                    <tr data-consignment-id="<?php echo (int)$item['consignment_id']; ?>"
                        data-item-id="<?php echo (int)$item['item_id']; ?>"
                        data-movement="<?php echo htmlspecialchars($item['movement_type'] ?? 'Consignment Out', ENT_QUOTES, 'UTF-8'); ?>">
                        <td data-col-key="active" class="text-center"><input type="checkbox" class="item-checkbox" value="<?php echo (int)$item['item_id']; ?>"></td>
                        <td data-col-key="image" class="memo-img-cell"><?php if ($imgHref !== '') { ?><img src="<?php echo htmlspecialchars($imgHref, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="memo-line-img" loading="lazy"><?php } else { ?><span class="memo-img-ph" title="No image">—</span><?php } ?></td>
                        <td data-col-key="invoice_no"><span class="consignment-badge"><?php echo htmlspecialchars($item['consignment_no'] ?? ''); ?></span></td>
                        <td data-col-key="voucher_type"><?php echo htmlspecialchars($item['movement_type'] ?? 'Consignment Out'); ?></td>
                        <td data-col-key="description" class="product-cell">
                            <span class="product-name"><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></span>
                            <?php if (!empty($item['category'])): ?><span class="product-code"><?php echo htmlspecialchars($item['category']); ?></span><?php endif; ?>
                        </td>
                        <td data-col-key="doc_date"><?php echo date('d-m-Y', strtotime($item['consignment_date'])); ?></td>
                        <td data-col-key="est_return">—</td>
                        <td data-col-key="qty" class="text-right"><?php echo (int)$item['quantity']; ?></td>
                        <td data-col-key="carat_wt" class="text-right"><?php echo memo_report_carat_wt_cell_html($item); ?></td>
                        <td data-col-key="gross_wt" class="text-right"><?php echo number_format((float)($item['gross_weight'] ?? 0), 3); ?></td>
                        <td data-col-key="final_wt" class="text-right"><?php echo number_format((float)($item['final_weight'] ?? $item['net_weight'] ?? 0), 3); ?></td>
                        <td data-col-key="amount" class="text-right"><?php echo number_format((float)($item['amount'] ?? 0), 2); ?></td>
                        <td data-col-key="net_amount" class="text-right"><?php echo number_format((float)($item['net_amount'] ?? $item['net_amt_with_tax'] ?? 0), 2); ?></td>
                        <td data-col-key="barcode"><?php echo htmlspecialchars($item['barcode'] ?? ''); ?></td>
                        <td data-col-key="item_code"><?php echo htmlspecialchars((string)($item['item_code_disp'] ?? $item['design_no'] ?? '')); ?></td>
                        <td data-col-key="ledger_name" class="customer-name-cell"><?php echo htmlspecialchars((string)($item['ledger_name'] ?? $item['customer_name'] ?? '')); ?></td>
                        <td data-col-key="account_no"><?php echo htmlspecialchars((string)($item['account_no_disp'] ?? '')); ?></td>
                        <td data-col-key="actions">
                            <div class="action-icons">
                                <span class="action-icon" title="View" onclick="viewConsignment('<?php echo htmlspecialchars($item['movement_type'] ?? 'Consignment Out', ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$item['consignment_id']; ?>)">
                                    <i class="feather icon-eye"></i>
                                </span>
                                <span class="action-icon action-print" title="Print voucher" onclick="printMemoVoucher('<?php echo htmlspecialchars($item['movement_type'] ?? 'Consignment Out', ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$item['consignment_id']; ?>)">
                                    <i class="feather icon-printer"></i>
                                </span>
                                <span class="action-icon delete" title="Delete" onclick="deleteItem(<?php echo (int)$item['item_id']; ?>)">
                                    <i class="feather icon-trash-2"></i>
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td data-col-key="active"></td>
                        <td data-col-key="image"></td>
                        <td data-col-key="invoice_no"></td>
                        <td data-col-key="voucher_type"></td>
                        <td data-col-key="description" class="text-right" style="font-weight:600;">Total:</td>
                        <td data-col-key="doc_date"></td>
                        <td data-col-key="est_return"></td>
                        <td data-col-key="qty" class="text-right"><?php echo (int)$totals['qty']; ?></td>
                        <td data-col-key="carat_wt" class="text-right"><?php echo number_format((float)$totals['purity_wt'], 3); ?></td>
                        <td data-col-key="gross_wt" class="text-right"><?php echo number_format((float)$totals['gross_wt'], 3); ?></td>
                        <td data-col-key="final_wt" class="text-right"><?php echo number_format((float)$totals['final_wt'], 3); ?></td>
                        <td data-col-key="amount" class="text-right"><?php echo number_format((float)$totals['amount'], 2); ?></td>
                        <td data-col-key="net_amount" class="text-right"><?php echo number_format((float)$totals['net_amount'], 2); ?></td>
                        <td data-col-key="barcode"></td>
                        <td data-col-key="item_code"></td>
                        <td data-col-key="ledger_name"></td>
                        <td data-col-key="account_no"></td>
                        <td data-col-key="actions"></td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="feather icon-package"></i>
                <h5>No Items Found</h5>
                <p>No consignment items found<?php echo $show_all ? '' : ' for this customer'; ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="items-footer">
            <span class="showing-info">Showing 1 to <?php echo count($consignment_items); ?> of <?php echo count($consignment_items); ?> entries</span>
            <div class="per-page">
                <select>
                    <option>Show All Items</option>
                    <option>10 per page</option>
                    <option>25 per page</option>
                    <option>50 per page</option>
                </select>
            </div>
        </div>
    </div>
    
</div>

</div>
</div>

<!-- Advanced Filter Modal -->
<div class="filter-modal" id="filterModal">
    <div class="filter-modal-content">
        <div class="filter-modal-header">
            <h5>Advance Filter</h5>
            <button class="filter-modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="filter-modal-body">
            <form id="filterForm" method="GET">
                <?php if (!$show_all && $selected_customer !== ''): ?>
                <input type="hidden" name="customer" value="<?php echo htmlspecialchars($selected_customer, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <?php if (!$show_all && $selected_customer_id > 0): ?>
                <input type="hidden" name="customer_id" value="<?php echo (int)$selected_customer_id; ?>">
                <?php endif; ?>
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Date Range</label>
                        <div class="date-range-inputs">
                            <div class="date-input-wrapper">
                                <input type="date" name="date_from" id="dateFrom" class="date-input" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="feather icon-calendar date-icon-overlay"></i>
                            </div>
                            <span class="date-separator">to</span>
                            <div class="date-input-wrapper">
                                <input type="date" name="date_to" id="dateTo" class="date-input" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="feather icon-calendar date-icon-overlay"></i>
                            </div>
                            <i class="feather icon-x date-clear-btn" onclick="clearDateRange()" title="Clear"></i>
                        </div>
                    </div>
                    <div class="filter-form-group">
                        <label>Est. Return Date</label>
                        <div class="date-range-inputs">
                            <div class="date-input-wrapper">
                                <input type="date" name="return_from" id="returnFrom" class="date-input" value="<?php echo htmlspecialchars($return_from, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="feather icon-calendar date-icon-overlay"></i>
                            </div>
                            <span class="date-separator">to</span>
                            <div class="date-input-wrapper">
                                <input type="date" name="return_to" id="returnTo" class="date-input" value="<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="feather icon-calendar date-icon-overlay"></i>
                            </div>
                            <i class="feather icon-x date-clear-btn" onclick="clearReturnDate()" title="Clear"></i>
                        </div>
                    </div>
                </div>
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Select Type</label>
                        <select name="type" id="filterType">
                            <option value="All"<?php echo ($filter_type === 'All') ? ' selected' : ''; ?>>All</option>
                            <option value="Consignment In"<?php echo ($filter_type === 'Consignment In') ? ' selected' : ''; ?>>Consignment In</option>
                            <option value="Consignment Out"<?php echo ($filter_type === 'Consignment Out') ? ' selected' : ''; ?>>Consignment Out</option>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>Invoice No.</label>
                        <input type="text" name="invoice_no" id="filterInvoiceNo" placeholder="" value="<?php echo htmlspecialchars($invoice_no_f, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Barcode No.</label>
                        <input type="text" name="barcode" id="filterBarcode" placeholder="" value="<?php echo htmlspecialchars($barcode_f, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="filter-form-group">
                        <label>Account No.</label>
                        <input type="text" name="account_no" id="filterAccountNo" placeholder="Bank account / Registration no." value="<?php echo htmlspecialchars($account_no_f, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </form>
        </div>
        <div class="filter-modal-footer">
            <button type="button" class="btn-apply-filter" onclick="applyFilter()">Apply Filter</button>
            <button type="button" class="btn-clear-filter" onclick="clearFilter()">Clear Filter</button>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function () {
    var STORAGE_KEYS_ORDER = 'memo_report_col_keys';
    var STORAGE_KEYS_WIDTH = 'memo_report_col_widths';
    var TABLE_ID = 'memoReportTable';
    var THEAD_ROW_ID = 'memoReportTheadRow';

    var DEFAULT_COL_KEYS = [
        'active', 'image', 'invoice_no', 'voucher_type', 'description', 'doc_date', 'est_return',
        'qty', 'carat_wt', 'gross_wt', 'final_wt', 'amount', 'net_amount',
        'barcode', 'item_code', 'ledger_name', 'account_no', 'actions'
    ];

    var STORAGE_KEYS_VISIBLE = 'memo_report_col_visible';

    var COL_LABELS = {
        active: 'Checkbox',
        image: 'Image',
        invoice_no: 'Invoice No.',
        voucher_type: 'Voucher Type',
        description: 'Description',
        doc_date: 'Date',
        est_return: 'Est. Return Dt.',
        qty: 'Qty',
        carat_wt: 'Carat/Wt.',
        gross_wt: 'Gross Wt.',
        final_wt: 'Final Wt.',
        amount: 'Amount',
        net_amount: 'Net Amount',
        barcode: 'Barcode No.',
        item_code: 'ItemCode',
        ledger_name: 'Ledger Name',
        account_no: 'Account No.',
        actions: 'Action'
    };

    function sameKeySet(a, b) {
        if (!Array.isArray(a) || !Array.isArray(b) || a.length !== b.length) return false;
        var x = a.slice().sort().join('\0');
        var y = b.slice().sort().join('\0');
        return x === y;
    }

    function reorderRow(row, orderedKeys) {
        var cells = {};
        var list = row.querySelectorAll('[data-col-key]');
        for (var i = 0; i < list.length; i++) {
            cells[list[i].getAttribute('data-col-key')] = list[i];
        }
        orderedKeys.forEach(function (k) {
            if (cells[k]) row.appendChild(cells[k]);
        });
    }

    function applyColumnOrder(order) {
        var table = document.getElementById(TABLE_ID);
        if (!table) return;
        var theadRow = document.getElementById(THEAD_ROW_ID) || table.querySelector('thead tr');
        if (!theadRow) return;
        reorderRow(theadRow, order);
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            reorderRow(tr, order);
        });
        var tf = table.querySelector('tfoot tr');
        if (tf) reorderRow(tf, order);
    }

    function loadOrder() {
        try {
            var raw = localStorage.getItem(STORAGE_KEYS_ORDER);
            if (!raw) return DEFAULT_COL_KEYS.slice();
            var arr = JSON.parse(raw);
            if (!Array.isArray(arr) || !sameKeySet(arr, DEFAULT_COL_KEYS)) return DEFAULT_COL_KEYS.slice();
            return arr;
        } catch (e1) {
            return DEFAULT_COL_KEYS.slice();
        }
    }

    function readOrderFromDOM() {
        var theadRow = document.getElementById(THEAD_ROW_ID);
        if (!theadRow) return DEFAULT_COL_KEYS.slice();
        var keys = [];
        theadRow.querySelectorAll('[data-col-key]').forEach(function (th) {
            keys.push(th.getAttribute('data-col-key'));
        });
        return keys.length ? keys : DEFAULT_COL_KEYS.slice();
    }

    function applyCellsWidth(colKey, px) {
        var pxn = Math.max(36, Math.round(px));
        var table = document.getElementById(TABLE_ID);
        if (!table) return;
        table.querySelectorAll('[data-col-key="' + colKey + '"]').forEach(function (el) {
            el.style.width = pxn + 'px';
            el.style.minWidth = pxn + 'px';
        });
    }

    function loadWidthsMap() {
        try {
            var raw = localStorage.getItem(STORAGE_KEYS_WIDTH);
            if (!raw) return {};
            var o = JSON.parse(raw);
            return typeof o === 'object' && o !== null ? o : {};
        } catch (e2) {
            return {};
        }
    }

    function persistWidthsMap(obj) {
        localStorage.setItem(STORAGE_KEYS_WIDTH, JSON.stringify(obj));
    }

    function applySavedWidthsFromStorage() {
        var mw = loadWidthsMap();
        Object.keys(mw).forEach(function (k) {
            var n = parseFloat(mw[k]);
            if (!isNaN(n)) applyCellsWidth(k, n);
        });
    }

    function initResizeHandles() {
        var table = document.getElementById(TABLE_ID);
        if (!table) return;
        table.querySelectorAll('thead .memo-col-resizer').forEach(function (rz) {
            rz.addEventListener('mousedown', function (eDown) {
                eDown.preventDefault();
                eDown.stopPropagation();
                var th = rz.closest('[data-col-key]');
                if (!th) return;
                var colKey = th.getAttribute('data-col-key');
                var startX = eDown.clientX;
                var startW = th.getBoundingClientRect().width;
                document.body.style.cursor = 'col-resize';
                table.classList.add('memo-resizing-table');

                function mm(e) {
                    applyCellsWidth(colKey, startW + (e.clientX - startX));
                }

                function mu() {
                    document.removeEventListener('mousemove', mm);
                    document.removeEventListener('mouseup', mu);
                    document.body.style.cursor = '';
                    table.classList.remove('memo-resizing-table');
                    var mw = loadWidthsMap();
                    var first = table.querySelector('[data-col-key="' + colKey + '"]');
                    var wstyle = first && first.style && first.style.width ? first.style.width : '';
                    var px = parseFloat(wstyle);
                    if (!isNaN(px)) mw[colKey] = Math.round(px);
                    persistWidthsMap(mw);
                }

                document.addEventListener('mousemove', mm);
                document.addEventListener('mouseup', mu);
            });
        });
    }

    function defaultVisibility() {
        var o = {};
        DEFAULT_COL_KEYS.forEach(function (k) {
            o[k] = true;
        });
        return o;
    }

    function loadVisibility() {
        try {
            var raw = localStorage.getItem(STORAGE_KEYS_VISIBLE);
            if (!raw) return defaultVisibility();
            var parsed = JSON.parse(raw);
            if (typeof parsed !== 'object' || parsed === null) return defaultVisibility();
            var out = defaultVisibility();
            DEFAULT_COL_KEYS.forEach(function (k) {
                if (Object.prototype.hasOwnProperty.call(parsed, k)) {
                    out[k] = !!parsed[k];
                }
            });
            return out;
        } catch (eVis) {
            return defaultVisibility();
        }
    }

    function persistVisibility(obj) {
        localStorage.setItem(STORAGE_KEYS_VISIBLE, JSON.stringify(obj));
    }

    function applyColumnVisibility(vis) {
        var table = document.getElementById(TABLE_ID);
        if (!table) return;
        DEFAULT_COL_KEYS.forEach(function (k) {
            var hide = vis[k] === false;
            table.querySelectorAll('[data-col-key="' + k + '"]').forEach(function (el) {
                el.classList.toggle('memo-col-hidden', hide);
            });
        });
    }

    function populateColumnSettingsRows() {
        var host = document.getElementById('memoColumnSettingsRows');
        if (!host) return;
        var order = readOrderFromDOM();
        var vis = loadVisibility();
        host.innerHTML = '';
        order.forEach(function (key) {
            if (COL_LABELS[key] === undefined) return;
            var row = document.createElement('div');
            row.className = 'column-settings-row';
            var cid = 'memoColVis_' + key.replace(/[^a-zA-Z0-9_]/g, '_');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.id = cid;
            cb.checked = vis[key] !== false;
            cb.addEventListener('change', function () {
                var m = loadVisibility();
                if (!cb.checked) {
                    var otherOn = DEFAULT_COL_KEYS.filter(function (k2) {
                        if (k2 === key) return false;
                        return m[k2] !== false;
                    }).length;
                    if (otherOn === 0) {
                        cb.checked = true;
                        return;
                    }
                }
                m[key] = cb.checked;
                persistVisibility(m);
                applyColumnVisibility(m);
            });
            var lab = document.createElement('label');
            lab.htmlFor = cid;
            lab.textContent = COL_LABELS[key] || key;
            row.appendChild(cb);
            row.appendChild(lab);
            host.appendChild(row);
        });
    }

    window.memoReportShowAllColumns = function () {
        var m = defaultVisibility();
        persistVisibility(m);
        applyColumnVisibility(m);
        populateColumnSettingsRows();
    };

    window.toggleColumnSettingsMenu = function () {
        var menu = document.getElementById('memoColumnSettingsMenu');
        if (!menu) return;
        var willShow = !menu.classList.contains('show');
        if (willShow) {
            menu.classList.add('show');
            populateColumnSettingsRows();
            var em = document.getElementById('exportDropdownMenu');
            var cm = document.getElementById('createDropdownMenu');
            if (em) em.classList.remove('show');
            if (cm) cm.classList.remove('show');
        } else {
            menu.classList.remove('show');
        }
    };

    window.initMemoReportColumnLayout = function () {
        var table = document.getElementById(TABLE_ID);
        if (!table) return;

        applyColumnOrder(loadOrder());
        applySavedWidthsFromStorage();

        var theadRow = document.getElementById(THEAD_ROW_ID);
        if (theadRow && typeof Sortable !== 'undefined') {
            if (theadRow._memoReportSortable && typeof theadRow._memoReportSortable.destroy === 'function') {
                try {
                    theadRow._memoReportSortable.destroy();
                } catch (eDel) {}
                theadRow._memoReportSortable = null;
            }

            theadRow._memoReportSortable = new Sortable(theadRow, {
                animation: 150,
                handle: '.memo-col-drag',
                draggable: 'th.memo-th',
                onEnd: function () {
                    var ord = readOrderFromDOM();
                    localStorage.setItem(STORAGE_KEYS_ORDER, JSON.stringify(ord));
                    applyColumnOrder(ord);
                }
            });
        }

        initResizeHandles();
        populateColumnSettingsRows();
        applyColumnVisibility(loadVisibility());
    };
})();

// Customer search filter
document.getElementById('customerSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const items = document.querySelectorAll('#customerList .customer-item');
    
    items.forEach(function(item) {
        const name = item.querySelector('.customer-name').textContent.toLowerCase();
        if (name.includes(searchTerm) || name === 'total') {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
});

// Select all checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('#memoReportTable tbody .item-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateMemoReportCreateButtonState();
});

(function bindMemoReportRowSelectionSync() {
    var tbody = document.querySelector('#memoReportTable tbody');
    if (!tbody) {
        return;
    }
    function onRowSelectionChange() {
        updateMemoReportCreateButtonState();
        syncMemoReportSelectAllCheckbox();
    }
    tbody.addEventListener('change', function (e) {
        if (e.target && e.target.matches && e.target.matches('.item-checkbox')) {
            onRowSelectionChange();
        }
    });
    tbody.addEventListener('click', function (e) {
        if (e.target && e.target.matches && e.target.matches('.item-checkbox')) {
            window.setTimeout(onRowSelectionChange, 0);
        }
    });
})();

/** Movement label for a table row: data-movement, else visible Voucher Type cell (handles odd casing / missing attr). */
function memoReportRowMovementText(tr) {
    if (!tr) {
        return '';
    }
    var fromAttr = (tr.getAttribute('data-movement') || '').replace(/\s+/g, ' ').trim();
    if (fromAttr !== '') {
        return fromAttr;
    }
    var cell = tr.querySelector('[data-col-key="voucher_type"]');
    if (!cell) {
        return '';
    }
    return cell.textContent.replace(/\s+/g, ' ').trim();
}

function memoReportMovementIsConsignmentIn(text) {
    return (text || '').toLowerCase().indexOf('consignment in') !== -1;
}

/** True if any checked report row is Consignment In. */
function memoReportSelectionIncludesConsignmentIn() {
    const selected = document.querySelectorAll('#memoReportTable tbody .item-checkbox:checked');
    for (let i = 0; i < selected.length; i++) {
        const row = selected[i].closest('tr');
        if (memoReportMovementIsConsignmentIn(memoReportRowMovementText(row))) {
            return true;
        }
    }
    return false;
}

function updateMemoReportCreateConsInSubmenuItem() {
    const link = document.getElementById('memoReportCreateConsInItem');
    if (!link) {
        return;
    }
    const disabled = memoReportSelectionIncludesConsignmentIn();
    link.classList.toggle('is-submenu-disabled', disabled);
    link.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    if (disabled) {
        link.setAttribute('tabindex', '-1');
    } else {
        link.removeAttribute('tabindex');
    }
    link.title = disabled
        ? 'Not available when Consignment In lines are selected (use this only from Consignment Out lines).'
        : 'Starts a new Consignment In from selected Consignment Out lines';
}

/** Create is enabled when ≥1 table row is checked (Consignment In or Out). */
function updateMemoReportCreateButtonState() {
    const btn = document.getElementById('btnCreateDropdown');
    const menu = document.getElementById('createDropdownMenu');
    if (!btn) {
        return;
    }
    const selected = document.querySelectorAll('#memoReportTable tbody .item-checkbox:checked');
    const allowed = selected.length > 0;
    const title = allowed ? 'Create from selected lines' : 'Select at least one row';
    btn.disabled = !allowed;
    btn.title = title;
    if (!allowed && menu) {
        menu.classList.remove('show');
    }
    updateMemoReportCreateConsInSubmenuItem();
}

function syncMemoReportSelectAllCheckbox() {
    const master = document.getElementById('selectAll');
    const boxes = document.querySelectorAll('#memoReportTable tbody .item-checkbox');
    if (!master || !boxes.length) {
        return;
    }
    const n = boxes.length;
    let c = 0;
    boxes.forEach(function (cb) {
        if (cb.checked) {
            c++;
        }
    });
    master.checked = c === n && n > 0;
    master.indeterminate = c > 0 && c < n;
}

// View consignment (in or out)
function viewConsignment(movementType, id) {
    var m = (movementType || '').toString();
    if (m.indexOf('Consignment In') !== -1) {
        window.location.href = 'consignment-in.php?id=' + encodeURIComponent(id);
    } else {
        window.location.href = 'consignment-out.php?id=' + encodeURIComponent(id);
    }
}

function printMemoVoucher(movementType, consignmentId) {
    var cid = parseInt(consignmentId, 10);
    if (!cid) return;
    var m = (movementType || '').toString();
    if (m.indexOf('Consignment In') !== -1) {
        window.open('consignment-in-print.php?id=' + encodeURIComponent(cid), '_blank', 'width=1200,height=800');
    } else {
        window.open('consignment-out-print.php?id=' + encodeURIComponent(cid), '_blank', 'width=1200,height=800');
    }
}

// Delete item
function deleteItem(itemId) {
    if (confirm('Are you sure you want to delete this item?')) {
        // Implement delete functionality
        alert('Delete functionality will be implemented');
    }
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('memoReportTable') || document.querySelector('.items-table');
    if (!table) {
        alert('No data to export');
        return;
    }
    const theadRow = table.querySelector('thead tr');
    const headerKeys = theadRow ? Array.from(theadRow.querySelectorAll('th[data-col-key]')).filter(function (th) {
        return !th.classList.contains('memo-col-hidden');
    }).map(function (th) {
        return th.getAttribute('data-col-key');
    }).filter(function (k) { return k && k !== 'actions'; }) : [];

    function cellTextByKey(row, k) {
        const c = row.querySelector('[data-col-key="' + k + '"]');
        return c ? c.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    let csv = [];
    const headerLabels = headerKeys.map(function (k) {
        const th = theadRow.querySelector('[data-col-key="' + k + '"]');
        var t = th ? th.textContent.replace(/\s+/g, ' ').trim() : k;
        return '"' + t.replace(/"/g, '""') + '"';
    });
    csv.push(headerLabels.join(','));

    table.querySelectorAll('tbody tr').forEach(function (tr) {
        const rowData = headerKeys.map(function (k) {
            return '"' + cellTextByKey(tr, k).replace(/"/g, '""') + '"';
        });
        csv.push(rowData.join(','));
    });

    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'consignment_out_report_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

// Create Consignment In from selected Consignment Out lines only
function createConsignmentIn() {
    const sub = document.getElementById('memoReportCreateConsInItem');
    if (sub && sub.classList.contains('is-submenu-disabled')) {
        return;
    }

    const selectedCheckboxes = document.querySelectorAll('#memoReportTable tbody .item-checkbox:checked');

    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one item to create Consignment In');
        return;
    }

    var i, row;
    for (i = 0; i < selectedCheckboxes.length; i++) {
        row = selectedCheckboxes[i].closest('tr');
        if (memoReportMovementIsConsignmentIn(memoReportRowMovementText(row))) {
            alert('Create Memo / Consignment In only from Consignment Out lines. Deselect Consignment In rows.');
            return;
        }
    }
    
    // Collect selected item IDs and consignment IDs
    const selectedItemIds = [];
    const consignmentIds = new Set();
    
    selectedCheckboxes.forEach(function(checkbox) {
        selectedItemIds.push(checkbox.value);
        // Get consignment ID from the row
        const row = checkbox.closest('tr');
        if (row) {
            const consignmentId = row.getAttribute('data-consignment-id');
            if (consignmentId) {
                consignmentIds.add(consignmentId);
            }
        }
    });
    
    // Build URL with parameters
    let url = 'consignment-in.php?';
    
    // If all selected items are from the same consignment out
    if (consignmentIds.size === 1) {
        const consignmentOutId = Array.from(consignmentIds)[0];
        url += 'consignment_out_id=' + consignmentOutId + '&';
    }
    
    // Add selected item IDs
    url += 'items=' + encodeURIComponent(JSON.stringify(selectedItemIds));
    
    // Redirect to consignment in page
    window.location.href = url;
}

// Toggle Create Dropdown
function toggleCreateDropdown() {
    const btn = document.getElementById('btnCreateDropdown');
    if (btn && btn.disabled) {
        return;
    }
    const menu = document.getElementById('createDropdownMenu');
    const exportMenu = document.getElementById('exportDropdownMenu');
    const colMenu = document.getElementById('memoColumnSettingsMenu');
    if (exportMenu) exportMenu.classList.remove('show');
    if (colMenu) colMenu.classList.remove('show');
    menu.classList.toggle('show');
}

(function bindCreateDropdownGuard() {
    var menu = document.getElementById('createDropdownMenu');
    if (!menu) {
        return;
    }
    menu.addEventListener('click', function (e) {
        var btn = document.getElementById('btnCreateDropdown');
        if (!btn || !btn.disabled) {
            return;
        }
        var a = e.target && e.target.closest ? e.target.closest('a.create-dropdown-item') : null;
        if (a) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
})();

updateMemoReportCreateButtonState();

// Toggle Export Dropdown
function toggleExportDropdown() {
    const menu = document.getElementById('exportDropdownMenu');
    const createMenu = document.getElementById('createDropdownMenu');
    const colMenu = document.getElementById('memoColumnSettingsMenu');
    if (createMenu) createMenu.classList.remove('show');
    if (colMenu) colMenu.classList.remove('show');
    menu.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.create-dropdown')) {
        const createMenu = document.getElementById('createDropdownMenu');
        if (createMenu) createMenu.classList.remove('show');
    }
    if (!e.target.closest('.export-dropdown')) {
        const exportMenu = document.getElementById('exportDropdownMenu');
        if (exportMenu) exportMenu.classList.remove('show');
    }
    if (!e.target.closest('.column-settings-dropdown')) {
        const columnMenu = document.getElementById('memoColumnSettingsMenu');
        if (columnMenu) columnMenu.classList.remove('show');
    }
});

// Filter modal
function openFilterModal() {
    document.getElementById('filterModal').classList.add('show');
}

function closeFilterModal() {
    document.getElementById('filterModal').classList.remove('show');
}

// Close modal on backdrop click
document.getElementById('filterModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeFilterModal();
    }
});

// Apply filter
function applyFilter() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    // Keep existing customer filter if present
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('customer')) params.set('customer', urlParams.get('customer'));
    if (urlParams.has('customer_id')) params.set('customer_id', urlParams.get('customer_id'));
    
    // Add filter params
    for (let [key, value] of formData.entries()) {
        if (value) params.set(key, value);
    }
    
    window.location.href = '?' + params.toString();
}

// Clear filter
function clearFilter() {
    document.getElementById('filterForm').reset();
    // Keep only customer filter
    const urlParams = new URLSearchParams(window.location.search);
    const params = new URLSearchParams();
    if (urlParams.has('customer')) params.set('customer', urlParams.get('customer'));
    if (urlParams.has('customer_id')) params.set('customer_id', urlParams.get('customer_id'));
    
    window.location.href = params.toString() ? '?' + params.toString() : '?';
}

// Clear date range
function clearDateRange() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
}

function clearReturnDate() {
    document.getElementById('returnFrom').value = '';
    document.getElementById('returnTo').value = '';
}

// Export to PDF
function exportToPDF() {
    alert('PDF export will be implemented');
}

if (typeof initMemoReportColumnLayout === 'function') {
    initMemoReportColumnLayout();
}
</script>

</body>
</html>
