<?php
session_start();
require_once 'config.php';

$departments = [];
$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_departments'");
if ($tbl && mysqli_num_rows($tbl) > 0) {
    mysqli_free_result($tbl);
    $departments = getList("SELECT id, dept_name FROM tbl_departments WHERE status = 1 ORDER BY dept_name ASC");
}

$job_worker_type_id = 0;
$jw_result = @mysqli_query($conn, "SELECT id FROM tbl_customer_types WHERE LOWER(name) = 'job worker' AND status = 1 LIMIT 1");
if ($jw_result && mysqli_num_rows($jw_result) > 0) {
    $jw_row = mysqli_fetch_assoc($jw_result);
    $job_worker_type_id = (int)$jw_row['id'];
}

$department_users = [];
foreach ($departments as $dept) {
    $dept_id = (int)$dept['id'];
    $department_users[$dept_id] = [];
    $map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
    if ($map_tbl && mysqli_num_rows($map_tbl) > 0) {
        mysqli_free_result($map_tbl);
        $users_query = "
            SELECT c.id, c.name 
            FROM tbl_customers c
            INNER JOIN tbl_department_user_map dum ON c.id = dum.user_id AND dum.status = 1
            WHERE dum.department_id = $dept_id 
            AND c.status = 1
            " . ($job_worker_type_id > 0 ? "AND c.customer_type_id = $job_worker_type_id" : "") . "
            ORDER BY c.name ASC
        ";
        $users_result = @mysqli_query($conn, $users_query);
        if ($users_result) {
            while ($user_row = mysqli_fetch_assoc($users_result)) {
                $department_users[$dept_id][] = $user_row;
            }
            mysqli_free_result($users_result);
        }
    }
}

$mp_jobwork_orders = [];

$chk_jwo = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if ($chk_jwo && mysqli_num_rows($chk_jwo) > 0) {
    mysqli_free_result($chk_jwo);
    $jwo_cols = [];
    $colq = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders");
    if ($colq) {
        while ($cr = mysqli_fetch_assoc($colq)) {
            $jwo_cols[$cr['Field']] = true;
        }
        mysqli_free_result($colq);
    }
    if (empty($jwo_cols['manufacturing_time_seconds'])) {
        $al = @mysqli_query($conn, "ALTER TABLE tbl_jobwork_orders ADD COLUMN manufacturing_time_seconds INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cumulative manufacturing time (seconds)'");
        if ($al) {
            $jwo_cols['manufacturing_time_seconds'] = true;
        } elseif (mysqli_errno($conn) === 1060) {
            $jwo_cols['manufacturing_time_seconds'] = true;
        }
    }
    if (empty($jwo_cols['jobwork_queue_no'])) {
        $alj = @mysqli_query($conn, "ALTER TABLE tbl_jobwork_orders ADD COLUMN jobwork_queue_no VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Jobwork Queue No from bill series (Jobwork Queue voucher)' AFTER jobwork_no");
        if ($alj) {
            $jwo_cols['jobwork_queue_no'] = true;
        } elseif (mysqli_errno($conn) === 1060) {
            $jwo_cols['jobwork_queue_no'] = true;
        }
    }
} elseif ($chk_jwo) {
    mysqli_free_result($chk_jwo);
}

$jwo_url_id = isset($_GET['jwo_id']) ? (int)$_GET['jwo_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$jwq_page_bootstrap = null;
if ($jwo_url_id > 0) {
    $jwq_page_bootstrap = function_exists('getRecord') ? getRecord(
        'SELECT j.id, j.jobwork_no, j.sale_order_no, j.customer_name, j.department_id, d.dept_name, j.department_user_id, c.name AS worker_name, '
        . 'COALESCE(j.manufacturing_time_seconds,0) AS manufacturing_time_seconds, j.jobwork_queue_no, '
        . '(SELECT ji.product_name FROM tbl_jobwork_order_items ji WHERE ji.jobwork_order_id = j.id ORDER BY ji.id ASC LIMIT 1) AS first_product '
        . 'FROM tbl_jobwork_orders j '
        . 'LEFT JOIN tbl_departments d ON j.department_id = d.id '
        . 'LEFT JOIN tbl_customers c ON j.department_user_id = c.id '
        . 'WHERE j.id = ' . (int)$jwo_url_id . ' LIMIT 1'
    ) : null;
    if ($jwq_page_bootstrap && function_exists('ensureJobworkQueueNoForOrder')) {
        $qn = ensureJobworkQueueNoForOrder($conn, $jwo_url_id);
        if ($qn !== null && $qn !== '') {
            $jwq_page_bootstrap['jobwork_queue_no'] = $qn;
        }
    }
}

$jwq_initial_search_label = '';
if ($jwq_page_bootstrap && !empty($jwq_page_bootstrap['id'])) {
    $bjw = trim((string)($jwq_page_bootstrap['jobwork_no'] ?? ''));
    $bjq = trim((string)($jwq_page_bootstrap['jobwork_queue_no'] ?? ''));
    $bcust = trim((string)($jwq_page_bootstrap['customer_name'] ?? ''));
    $jwq_initial_search_label = ($bjq !== '' ? $bjq : ($bjw !== '' ? $bjw : ('#' . (int)$jwq_page_bootstrap['id'])))
        . ($bcust !== '' ? (' · ' . $bcust) : '');
}

/** Next Jobwork Queue No. from bill series — shown when no order is loaded yet (preview until save links a row). */
$jwq_preview_queue_no = '';
if (!$jwq_page_bootstrap && function_exists('getNextJobworkQueueNo')) {
    $jwq_preview_queue_no = getNextJobworkQueueNo($conn);
}

$metals = function_exists('getList') ? @getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ORDER BY id ASC") : [];
if (!is_array($metals)) {
    $metals = [];
}
require_once __DIR__ . '/includes/auragold_voucher_runtime_settings.php';
$auragold_voucher_runtime_client = auragold_voucher_runtime_bootstrap($conn, $metals, 'Jobwork Queue');

$bank_accounts_raw = function_exists('getList') ? @getList("SELECT id, name FROM tbl_customers WHERE sundry_debtors_id = 29 AND status = 1 AND TRIM(IFNULL(name,'')) != '' ORDER BY name ASC") : [];
$bank_accounts = [];
$exclude_bank_names = ['phonepe', 'phonepay', 'gpay', 'google pay', 'paytm', 'upi', '0.00', '0'];
if (is_array($bank_accounts_raw)) {
    foreach ($bank_accounts_raw as $b) {
        $n = trim(strtolower($b['name'] ?? ''));
        if ($n === '' || in_array($n, $exclude_bank_names) || preg_match('/^[0-9.]+$/', $n)) {
            continue;
        }
        $bank_accounts[] = $b;
    }
}

$stock_columns = [
    ['key' => 'queue_no', 'label' => 'Queue No'],
    ['key' => 'comment', 'label' => 'Comment'],
    ['key' => 'product_name', 'label' => 'Product Name'],
    ['key' => 'active', 'label' => 'active'],
    ['key' => 'image_urls', 'label' => 'imageUrls'],
    ['key' => 'against_queue', 'label' => 'Against Queue'],
    ['key' => 'against_invoice', 'label' => 'Against Invoice'],
    ['key' => 'metal', 'label' => 'Metal'],
    ['key' => 'description', 'label' => 'Description'],
    ['key' => 'loss_wt', 'label' => 'Loss Wt'],
    ['key' => 'handloss_wt', 'label' => 'HandLoss Wt'],
    ['key' => 'profit_wt', 'label' => 'Profit Wt'],
    ['key' => 'tag_no', 'label' => 'Tag No.'],
    ['key' => 'total_wt', 'label' => 'Total Wt'],
    ['key' => 'metal_wt', 'label' => 'Metal Wt'],
    ['key' => 'diamond_wt', 'label' => 'Diamond Wt'],
    ['key' => 'purity_wt', 'label' => 'Purity Wt'],
    ['key' => 'carat_name', 'label' => 'Carat Name'],
    ['key' => 'total_quantity', 'label' => 'Total Quantity'],
    ['key' => 'date_time', 'label' => 'Date & Time'],
    ['key' => 'branch_name', 'label' => 'Branch Name'],
    ['key' => 'design_no', 'label' => 'DesignNo'],
    ['key' => 'department_name', 'label' => 'Department Name'],
    ['key' => 'user_name', 'label' => 'User Name'],
    ['key' => 'action', 'label' => 'action'],
];

$jwq_inward_stock_modal_columns = [
    ['key' => 'queue_no', 'label' => 'Queue No'],
    ['key' => 'comment', 'label' => 'Comment'],
    ['key' => 'product_name', 'label' => 'Product Name'],
    ['key' => 'active', 'label' => 'active'],
    ['key' => 'image_urls', 'label' => 'imageUrls'],
    ['key' => 'against_queue', 'label' => 'Against Queue'],
    ['key' => 'against_invoice', 'label' => 'Against Invoice'],
    ['key' => 'metal', 'label' => 'Metal'],
    ['key' => 'description', 'label' => 'Description'],
    ['key' => 'dust_wastage_wt', 'label' => 'Dust / Wastage Wt'],
    ['key' => 'loss_wt', 'label' => 'Loss Wt'],
    ['key' => 'total_wt', 'label' => 'Total Wt'],
    ['key' => 'metal_wt', 'label' => 'Metal Wt'],
    ['key' => 'diamond_wt', 'label' => 'Diamond Wt'],
    ['key' => 'purity_wt', 'label' => 'Purity Wt'],
    ['key' => 'carat_name', 'label' => 'Carat Name'],
    ['key' => 'profit_wt', 'label' => 'Profit Wt'],
    ['key' => 'tag_no', 'label' => 'Tag No.'],
    ['key' => 'total_quantity', 'label' => 'Total Quantity'],
    ['key' => 'date_time', 'label' => 'Date & Time'],
];

$jwq_order_line_columns = [
    ['key' => 'design_no', 'label' => 'Design No'],
    ['key' => 'tag_no', 'label' => 'Tag No'],
    ['key' => 'description', 'label' => 'Description'],
    ['key' => 'order_no', 'label' => 'Order No'],
    ['key' => 'total_wt', 'label' => 'Total Wt'],
    ['key' => 'metal_wt', 'label' => 'Metal Wt'],
    ['key' => 'diamond_wt', 'label' => 'Diamond Wt'],
    ['key' => 'total_purity', 'label' => 'Total Purity'],
    ['key' => 'karat', 'label' => 'Karat'],
    ['key' => 'total_qty', 'label' => 'Total Qty'],
    ['key' => 'price', 'label' => 'Price'],
    ['key' => 'dust_wastage_wt', 'label' => 'Dust / Wastage Wt'],
    ['key' => 'loss', 'label' => 'Loss'],
    ['key' => 'profit', 'label' => 'Profit'],
    ['key' => 'expected_wt', 'label' => 'Expected Wt'],
    ['key' => 'product', 'label' => 'Product'],
    ['key' => 'requested_wt', 'label' => 'Requested Wt.'],
    ['key' => 'requested_purity', 'label' => 'Requested Purity'],
    ['key' => 'alloy_wt', 'label' => 'Alloy Wt.'],
    ['key' => 'damage_qty', 'label' => 'Damage Quantity'],
    ['key' => 'damage_wt', 'label' => 'Damage Weight'],
];

$mp_json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$mp_departments_json = json_encode($departments, $mp_json_flags);
if ($mp_departments_json === false) {
    $mp_departments_json = '[]';
}
$mp_department_users_json = json_encode($department_users, $mp_json_flags);
if ($mp_department_users_json === false) {
    $mp_department_users_json = '{}';
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Jobwork Queue — Gold Matrix</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="assets/css/mfg-pages-mobile.css">
</head>
<style>
:root {
    --gm-navy: #11294b;
    --gm-navy-mid: #1a3a5c;
    --gm-gold-light: #e8d48a;
    --gm-gold-pale: #faf6eb;
    --gm-gold-deep: #8b6914;
}

.jobwork-queue-page .layout-content {
    height: calc(100vh - 60px);
    overflow: auto;
    background: #f4f6f9;
}

.jwq-page-shell {
    padding: 12px 14px 24px;
    max-width: 1320px;
    margin: 0 auto;
}

.jwq-modal-overlay.jwq-page-embed {
    position: static;
    display: block;
    background: transparent;
    z-index: auto;
    padding: 0;
    inset: auto;
}

.jwq-modal-overlay.jwq-page-embed .jwq-modal {
    width: 100%;
    max-width: 100%;
    max-height: none;
    min-height: calc(100vh - 88px);
    display: flex;
    flex-direction: column;
    background: #f4f6fb;
    border: 1px solid #c5ccdb;
    border-radius: 10px;
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
    overflow: hidden;
}

.jwq-modal-overlay.jwq-page-embed .jwq-modal-body {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.jwq-picker-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.jwq-picker-row label {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}
.jwq-search-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
    max-width: 480px;
}
.jwq-search-wrap .form-control {
    width: 100%;
}
.jwq-search-suggestions {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 2100;
    max-height: min(360px, 50vh);
    overflow: auto;
    background: #fff;
    border: 1px solid #c8d0e2;
    border-radius: 8px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
}
.jwq-search-suggestions.show {
    display: block;
}
.jwq-suggestion-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}
.jwq-suggestion-item:last-child {
    border-bottom: 0;
}
.jwq-suggestion-item:hover,
.jwq-suggestion-item.active {
    background: #f8fafc;
}
.jwq-suggestion-primary {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
}
.jwq-suggestion-meta {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 4px;
}

/* Jobwork Queue modal (same as manufacturing-process) */
.jwq-modal-head {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    background: linear-gradient(180deg, #fff 0%, #f0f3fa 100%);
    border-bottom: 1px solid #d6dbea;
}
.jwq-modal-title-wrap {
    font-size: 15px;
    font-weight: 700;
    color: #1e2b4a;
}
.jwq-modal-title-wrap strong {
    color: #11294b;
}
.jwq-modal-head-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.jwq-btn-text {
    border: 0;
    background: transparent;
    color: var(--gm-navy-mid);
    font-size: 13px;
    font-weight: 600;
    padding: 6px 8px;
    cursor: pointer;
}
.jwq-btn-text:hover {
    text-decoration: underline;
    color: var(--gm-gold-deep);
}
.jwq-btn-save {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--gm-navy);
    background: var(--gm-navy);
    color: var(--gm-gold-light);
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
}
.jwq-btn-save:hover { opacity: 0.94; }
.jwq-modal-close {
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 22px;
    line-height: 1;
    padding: 4px 8px;
    cursor: pointer;
    border-radius: 6px;
}
.jwq-modal-close:hover {
    background: #e8ecf4;
    color: #0f172a;
}
.jwq-btn-print,
.jwq-btn-new {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(17, 41, 75, 0.35);
    background: #fff;
    color: #11294b;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
}
.jwq-btn-print:hover,
.jwq-btn-new:hover {
    background: var(--gm-gold-pale);
}
.jwq-modal-body {
    padding: 12px 14px 14px;
    overflow: auto;
}
.jwq-transfer-row {
    display: grid;
    grid-template-columns: 1fr auto 1fr auto;
    gap: 10px 12px;
    align-items: end;
    margin-bottom: 12px;
}
@media (max-width: 992px) {
    .jwq-transfer-row { grid-template-columns: 1fr; }
}
.jwq-from-block,
.jwq-to-block {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 10px;
    background: #fff;
    border: 1px solid #d8deeb;
    border-radius: 8px;
    padding: 10px;
}
.jwq-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.jwq-field label {
    margin: 0;
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.jwq-field select,
.jwq-field input {
    height: 32px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    padding: 0 8px;
    font-size: 13px;
    color: #1e293b;
    background: #fff;
}
.jwq-user-with-icons {
    display: flex;
    align-items: center;
    gap: 4px;
}
.jwq-user-with-icons select { flex: 1; min-width: 0; }
.jwq-icon-btn {
    width: 30px;
    height: 30px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    background: #f8fafc;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    cursor: pointer;
}
.jwq-icon-btn:hover {
    background: #eef2f8;
    color: #11294b;
}
.jwq-arrows {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    color: #94a3b8;
    padding: 0 4px;
}
.jwq-arrows i { font-size: 22px; }
.jwq-datetime-block {
    background: #fff;
    border: 1px solid #d8deeb;
    border-radius: 8px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 160px;
}
.jwq-datetime-block .jwq-field {
    flex-direction: row;
    align-items: center;
    gap: 8px;
}
.jwq-datetime-block .jwq-field label { min-width: 38px; }
.jwq-time-spent {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    padding: 6px 8px;
    background: #f1f5f9;
    border-radius: 6px;
    border: 1px dashed #cbd5e1;
}
.jwq-tag-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.jwq-tag-row input[type="text"] {
    flex: 1;
    min-width: 160px;
    height: 34px;
    border: 1px solid #c8d0e2;
    border-radius: 8px;
    padding: 0 10px 0 34px;
    font-size: 13px;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M3 7V5a2 2 0 012-2h2'/%3E%3Cpath d='M17 3h2a2 2 0 012 2v2'/%3E%3Cpath d='M21 17v2a2 2 0 01-2 2h-2'/%3E%3Cpath d='M7 21H5a2 2 0 01-2-2v-2'/%3E%3C/svg%3E") no-repeat 10px center;
}
.jwq-tag-row .jwq-pill-btn {
    height: 32px;
    padding: 0 14px;
    border-radius: 6px;
    border: 1px solid #11294b;
    background: #fff;
    color: #11294b;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
.jwq-tag-row .jwq-pill-btn:hover {
    background: #11294b;
    color: #fff;
}
.jwq-weight-adjust-strip {
    margin: 12px 0 0;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.jwq-weight-adjust-title {
    font-weight: 700;
    font-size: 14px;
    color: #0f172a;
    margin-bottom: 10px;
}
.jwq-weight-adjust-inner {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px 14px;
}
.jwq-weight-adjust-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
}
.jwq-weight-adjust-field-grow {
    flex: 1 1 200px;
    min-width: 160px;
}
.jwq-weight-adjust-input {
    width: 100%;
    max-width: 200px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 14px;
    background: #fff;
}
.jwq-weight-adjust-field-grow .jwq-weight-adjust-input {
    max-width: none;
}
.jwq-weight-adjust-save {
    border: 1px solid #11294b;
    background: #11294b;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 18px;
    border-radius: 6px;
    cursor: pointer;
    align-self: flex-end;
}
.jwq-weight-adjust-save:hover {
    opacity: 0.94;
}
.jwq-weight-adjust-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.jwq-table-wrap {
    border: 1px solid #d8deeb;
    border-radius: 8px;
    overflow: auto;
    max-height: min(50vh, 420px);
    background: #fff;
    margin-bottom: 10px;
    -webkit-overflow-scrolling: touch;
    position: relative;
    z-index: 1;
}
.jwq-table {
    width: 100%;
    min-width: max-content;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: auto;
}
.jwq-table th {
    position: sticky;
    top: 0;
    background: #e8ecf4;
    color: #334155;
    font-weight: 700;
    text-align: left;
    padding: 8px 10px;
    padding-right: 18px;
    border-bottom: 1px solid #d8deeb;
    border-right: 1px solid #c8d4e8;
    white-space: nowrap;
}
#jwqOrderLinesTable.acr-col-table thead th .acr-th-drag {
    color: #64748b;
}
#jwqOrderLinesTable.acr-col-table thead th .acr-th-drag:hover {
    color: #c9a962;
}
.jwq-table th:last-child { border-right: 0; }
.jwq-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #eef1f7;
    border-right: 1px solid #e8ecf4;
    color: #1e293b;
    white-space: nowrap;
    vertical-align: top;
}
.jwq-table td:last-child { border-right: 0; }
.jwq-table tr:last-child td { border-bottom: 0; }
.jwq-table th.col-hidden,
.jwq-table td.col-hidden { display: none !important; }
.jwq-table td .jwq-cell-input {
    width: 100%;
    min-width: 88px;
    max-width: 140px;
    border: 1px solid #cfd8e3;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 12px;
    line-height: 1.25;
    background: #fff;
    color: #0f172a;
    box-sizing: border-box;
}
.jwq-table td .jwq-cell-input:focus {
    outline: none;
    border-color: #7aa2ff;
    box-shadow: 0 0 0 2px rgba(122, 162, 255, 0.18);
}
.jwq-table td .jwq-cell-input--decimal {
    min-width: 96px;
    max-width: 150px;
}
.jwq-table td[data-col="total_wt"],
.jwq-table td[data-col="metal_wt"],
.jwq-table td[data-col="diamond_wt"],
.jwq-table td[data-col="total_purity"],
.jwq-table td[data-col="total_qty"],
.jwq-table td[data-col="loss"],
.jwq-table td[data-col="price"] {
    min-width: 108px;
}
.jwq-table td .jwq-cell-input--readonly,
.jwq-table td .jwq-cell-input[readonly] {
    background: #f1f5f9;
    color: #334155;
    cursor: default;
    border-color: #e2e8f0;
}
.jwq-lines-toolbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
    position: relative;
    z-index: 12;
}
.jwq-lines-toolbar .jwq-btn-add-line {
    margin-right: auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(17, 41, 75, 0.35);
    background: #fff;
    color: #11294b;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 6px;
    cursor: pointer;
}
.jwq-lines-toolbar .jwq-btn-add-line:hover {
    background: var(--gm-gold-pale);
}

/* Column picker (required — same as manufacturing-process; without this the panel stays in-flow and crushes the grid) */
.columns-panel {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    background: linear-gradient(180deg, #fff 0%, var(--gm-gold-pale) 100%);
    border: 1px solid rgba(17, 41, 75, 0.15);
    border-radius: 6px;
    z-index: 1200;
    display: none;
    box-shadow: 0 6px 20px rgba(31, 41, 55, 0.18);
    box-sizing: border-box;
}
.columns-panel.show {
    display: block;
}
.columns-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 8px;
    border-bottom: 1px solid #ccd4e4;
    font-size: 12px;
    font-weight: 700;
    color: #4c5a7a;
}
.columns-panel-header .icons {
    display: inline-flex;
    gap: 5px;
    align-items: center;
}
.columns-panel-header .icons .tag {
    font-size: 10px;
    border: 1px solid #c7d1e5;
    background: #fff;
    padding: 1px 4px;
    border-radius: 3px;
}
.columns-panel-close {
    border: 0;
    background: transparent;
    color: #7786a8;
    font-size: 16px;
    line-height: 1;
    padding: 0 2px;
    cursor: pointer;
}
.columns-search {
    padding: 6px 8px 4px;
}
.columns-search input {
    width: 100%;
    height: 24px;
    border: 1px solid #c8d0e2;
    border-radius: 5px;
    padding: 0 8px;
    font-size: 12px;
}
.columns-list {
    max-height: 220px;
    overflow: auto;
    padding: 2px 8px 8px;
}
.columns-list label {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0;
    padding: 3px 0;
    font-size: 13px;
    color: #2f3d5b;
    font-weight: 500;
}
.columns-list input[type="checkbox"] {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
}
/* Jobwork line columns: popover under gear (same behaviour as manufacturing-process) */
.columns-panel.jwq-columns-inline {
    position: static;
    left: auto;
    top: auto;
    width: 100%;
    max-width: none;
    z-index: auto;
    box-shadow: none;
    margin-bottom: 10px;
    box-sizing: border-box;
}
.columns-panel.jwq-columns-inline .columns-list.jwq-columns-list--table {
    max-height: min(50vh, 320px);
    overflow: auto;
    padding: 0;
}
.columns-panel.jwq-columns-inline .jwq-column-pref-table {
    font-size: 13px;
    margin: 0;
    background: #fff;
}
.columns-panel.jwq-columns-inline .jwq-column-pref-table thead th {
    background: #eef2f8;
    color: #334155;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 1;
}
.jwq-modal .columns-panel {
    z-index: 1600;
}

/* Jobwork Queue line columns — same picker look as Manufacturing queue */
.jwq-modal #jwqColumnsPanel.columns-panel.jwq-columns-popover {
    position: absolute;
    left: auto;
    right: 0;
    top: 100%;
    margin-top: 6px;
    width: min(320px, calc(100vw - 48px));
    min-width: 260px;
    max-height: min(75vh, 480px);
    z-index: 40;
    box-sizing: border-box;
    margin-bottom: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(15, 23, 42, 0.14), 0 2px 8px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}
.jwq-modal #jwqColumnsPanel.columns-panel.jwq-columns-popover.show {
    display: flex;
    flex-direction: column;
}
.jwq-modal #jwqColumnsPanel .columns-panel-header {
    flex-shrink: 0;
    background: #fff;
    padding: 10px 12px;
    font-size: 13px;
    color: #334155;
    border-bottom: 1px solid #eef1f7;
}
.jwq-modal #jwqColumnsPanel .columns-panel-header .icons .feather.icon-settings {
    color: #c5a864;
    font-size: 15px;
}
.jwq-modal #jwqColumnsPanel .columns-search {
    flex-shrink: 0;
    padding: 10px 12px 8px;
    background: #fff;
}
.jwq-modal #jwqColumnsPanel .columns-search input {
    height: 34px;
    border-radius: 8px;
    border: 1px solid #d8dee9;
    font-size: 13px;
    padding: 0 12px;
}
.jwq-modal #jwqColumnsPanel .columns-list.jwq-columns-list--picker {
    flex: 1 1 auto;
    min-height: 0;
    max-height: min(58vh, 380px);
    overflow-x: hidden;
    overflow-y: auto;
    padding: 4px 8px 12px;
    margin: 0;
    -webkit-overflow-scrolling: touch;
    background: #fff;
}
.jwq-modal #jwqColumnsPanel .columns-list.jwq-columns-list--picker .jwq-column-picker-label {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    margin: 0;
    padding: 8px 8px 8px 6px;
    font-size: 13px;
    font-weight: 500;
    color: #1e293b;
    cursor: pointer;
    border-radius: 6px;
    user-select: none;
}
.jwq-modal #jwqColumnsPanel .columns-list.jwq-columns-list--picker .jwq-column-picker-label:hover {
    background: #f8fafc;
}
.jwq-modal #jwqColumnsPanel .columns-list.jwq-columns-list--picker .jwq-column-picker-label span {
    flex: 1;
    min-width: 0;
    word-break: break-word;
    line-height: 1.35;
}
.jwq-modal #jwqColumnsPanel .columns-list.jwq-columns-list--picker input[type="checkbox"] {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    margin: 0;
    cursor: pointer;
}

#jwqInwardStockModal .modal-content {
    overflow: visible !important;
}
#jwqInwardStockModal .modal-body {
    overflow: visible !important;
}
#jwqInwardStockModal #jwqInwardStockColumnsPanel.columns-panel {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    margin-top: 6px;
    width: min(320px, calc(100vw - 48px));
    min-width: 260px;
    max-height: min(75vh, 480px);
    z-index: 2060;
    box-sizing: border-box;
}
#jwqInwardStockModal #jwqInwardStockColumnsPanel.columns-panel.show {
    display: flex;
    flex-direction: column;
}
#jwqInwardStockModal #jwqInwardStockColumnsPanel .columns-search {
    flex-shrink: 0;
}
#jwqInwardStockModal #jwqInwardStockColumnsPanel .columns-panel-header {
    flex-shrink: 0;
}
#jwqInwardStockModal #jwqInwardStockColumnsPanel .columns-list {
    max-height: min(58vh, 380px);
    min-height: 160px;
    overflow-x: hidden;
    overflow-y: auto;
    flex: 1 1 auto;
    -webkit-overflow-scrolling: touch;
}
#jwqInwardStockModal .columns-panel {
    z-index: 2060;
}

.jwq-bottom-split {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 12px;
    align-items: start;
}
@media (max-width: 900px) {
    .jwq-bottom-split { grid-template-columns: 1fr; }
}
.jwq-material-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.jwq-payment-icons-wrap { width: 100%; }
.jwq-payment-icons-wrap .payment-icons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0;
}
.jwq-payment-icons-wrap .payment-icon {
    width: 45px;
    height: 45px;
    border: 1.5px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1rem;
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    color: #11294b;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
    margin-right: 0.35rem;
    margin-bottom: 0.25rem;
}
.jwq-payment-icons-wrap .payment-icon:hover {
    background: #11294b;
    border-color: #c5a864;
    color: white;
}
.jwq-mat-table-wrap {
    border: 1px solid #d8deeb;
    border-radius: 8px;
    overflow: auto;
    background: #fff;
    max-height: 160px;
}
.jwq-mat-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}
.jwq-mat-table th {
    background: #f1f5f9;
    padding: 6px 8px;
    text-align: left;
    font-weight: 700;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
}
.jwq-mat-table td {
    padding: 8px;
    border-bottom: 1px solid #f1f5f9;
}
.jwq-mat-empty {
    text-align: center;
    color: #94a3b8;
    padding: 20px;
    font-size: 13px;
}
.jwq-payment-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 8px 0;
}
.jwq-payment-row input {
    flex: 1;
    height: 32px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    padding: 0 8px;
    font-size: 13px;
}
.jwq-comment-row {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}
.jwq-comment-row input {
    flex: 1;
    height: 34px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    padding: 0 10px;
    font-size: 13px;
}
.jwq-comment-row button {
    width: 36px;
    height: 34px;
    border-radius: 6px;
    border: 1px solid #11294b;
    background: #11294b;
    color: #fff;
    cursor: pointer;
}
.jwq-images-box {
    border: 2px dashed #c8d0e2;
    border-radius: 10px;
    min-height: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #fafbfe;
    color: #64748b;
    cursor: pointer;
    padding: 16px;
}
.jwq-images-box:hover {
    border-color: #11294b;
    color: #11294b;
    background: #f0f4ff;
}
.jwq-images-box i { font-size: 42px; opacity: 0.7; }
.jwq-images-box span { font-size: 13px; font-weight: 600; }

.jobwork-queue-page .modal { z-index: 2000 !important; }
.jobwork-queue-page .modal-backdrop { z-index: 1990 !important; }

.jwq-inward-stock-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    position: relative;
    z-index: 5;
}
.jwq-inward-stock-tool {
    width: 36px;
    height: 32px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.jwq-inward-stock-tool:hover { background: #f1f5f9; }

#jwqInwardStockModal .modal-dialog {
    min-width: 700px;
    max-width: min(96vw, 960px);
    width: auto;
    margin: 1.5rem auto;
    overflow: visible;
}

@media print {
    .layout-sidenav, .layout-navbar, .layout-footer, .jwq-picker-row, .no-print {
        display: none !important;
    }
    .layout-container { margin-left: 0 !important; }
    .jobwork-queue-page .layout-content { height: auto !important; overflow: visible !important; }
    .jwq-modal-overlay.jwq-page-embed .jwq-modal { box-shadow: none; border: 0; min-height: 0; }
}
</style>
<body class="mfg-page jobwork-queue-page">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
    <div class="jwq-page-shell">
        <div class="jwq-picker-row">
            <label for="jwqSearchInput">Job Work Order</label>
            <div class="jwq-search-wrap">
                <input type="text" id="jwqSearchInput" class="form-control form-control-sm" autocomplete="off"
                    placeholder="Type Jobwork Queue No., Job No., customer, sale order, or id…"
                    value="<?php echo htmlspecialchars($jwq_initial_search_label, ENT_QUOTES, 'UTF-8'); ?>">
                <div id="jwqSearchSuggestions" class="jwq-search-suggestions" role="listbox" aria-label="Job work order matches"></div>
                <button type="button" id="jwqDynamicBoot" class="d-none jwq-order-boot" aria-hidden="true" tabindex="-1"></button>
            </div>
            <a href="manufacturing-process.php" class="btn btn-sm btn-outline-secondary no-print">Manufacturing Process</a>
        </div>

        <?php
        if ($jwq_page_bootstrap) {
            $bjid = (int)($jwq_page_bootstrap['id'] ?? 0);
            $bjq = trim((string)($jwq_page_bootstrap['jobwork_queue_no'] ?? ''));
            $bdept = (int)($jwq_page_bootstrap['department_id'] ?? 0);
            $buser = (int)($jwq_page_bootstrap['department_user_id'] ?? 0);
            $bdname = trim((string)($jwq_page_bootstrap['dept_name'] ?? ''));
            $bwork = trim((string)($jwq_page_bootstrap['worker_name'] ?? ''));
            $bcust = trim((string)($jwq_page_bootstrap['customer_name'] ?? ''));
            $bfirst = trim((string)($jwq_page_bootstrap['first_product'] ?? ''));
            $bjw = (string)($jwq_page_bootstrap['jobwork_no'] ?? '');
            $bso = trim((string)($jwq_page_bootstrap['sale_order_no'] ?? ''));
            $bmfg = (int)($jwq_page_bootstrap['manufacturing_time_seconds'] ?? 0);
            if ($bmfg < 0) {
                $bmfg = 0;
            }
        ?>
        <button type="button" id="jwqPageBootstrapBtn" class="d-none"
            data-jwo-id="<?php echo $bjid; ?>"
            data-jobwork-queue-no="<?php echo htmlspecialchars($bjq, ENT_QUOTES, 'UTF-8'); ?>"
            data-jobwork-no="<?php echo htmlspecialchars($bjw, ENT_QUOTES, 'UTF-8'); ?>"
            data-sale-order-no="<?php echo htmlspecialchars($bso, ENT_QUOTES, 'UTF-8'); ?>"
            data-dept-id="<?php echo $bdept; ?>"
            data-dept-name="<?php echo htmlspecialchars($bdname, ENT_QUOTES, 'UTF-8'); ?>"
            data-user-id="<?php echo $buser; ?>"
            data-worker-name="<?php echo htmlspecialchars($bwork, ENT_QUOTES, 'UTF-8'); ?>"
            data-customer="<?php echo htmlspecialchars($bcust, ENT_QUOTES, 'UTF-8'); ?>"
            data-first-product="<?php echo htmlspecialchars($bfirst, ENT_QUOTES, 'UTF-8'); ?>"
            data-manufacturing-seconds="<?php echo $bmfg; ?>"
            aria-hidden="true" tabindex="-1"></button>
        <?php } ?>

        <div class="jwq-modal-overlay jwq-page-embed show" id="jwqModalOverlay" aria-hidden="false">
            <div class="jwq-modal" role="dialog" aria-modal="true" aria-labelledby="jwqModalTitle">
                <div class="jwq-modal-head">
                    <div class="jwq-modal-title-wrap" id="jwqModalTitle">Jobwork Queue No. : <strong id="jwqModalQueueNo">—</strong></div>
                    <div class="jwq-modal-head-actions">
                        <button type="button" class="jwq-btn-text" id="jwqBtnCatalogue">Create Catalogue</button>
                        <button type="button" class="jwq-btn-new no-print" id="jwqBtnNew" title="Start a new job queue (clear current)"><i class="feather icon-plus"></i> New</button>
                        <button type="button" class="jwq-btn-save" id="jwqBtnSave" title="Save"><i class="feather icon-check"></i> Save</button>
                        <button type="button" class="jwq-btn-print no-print" id="jwqBtnPrint" title="Print"><i class="feather icon-printer"></i> Print</button>
                    </div>
                </div>
                <div class="jwq-modal-body">
                    <input type="hidden" id="jwqCurrentJwoId" value="">
                    <input type="hidden" id="jwqJobworkQueueNo" value="">
                    <div class="jwq-transfer-row">
                        <div class="jwq-from-block">
                            <div class="jwq-field">
                                <label for="jwqFromDept">From Dept.*</label>
                                <select id="jwqFromDept"></select>
                            </div>
                            <div class="jwq-field">
                                <label for="jwqFromUser">From User</label>
                                <div class="jwq-user-with-icons">
                                    <select id="jwqFromUser"></select>
                                    <button type="button" class="jwq-icon-btn jwq-inward-folder-btn" data-which="from" title="Inward stock details" aria-label="Inward stock details"><i class="feather icon-folder" style="font-size:15px;"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="jwq-arrows" aria-hidden="true">
                            <i class="feather icon-arrow-right"></i>
                            <i class="feather icon-arrow-left"></i>
                        </div>
                        <div class="jwq-to-block">
                            <div class="jwq-field">
                                <label for="jwqToDept">To Dept.*</label>
                                <select id="jwqToDept" required></select>
                            </div>
                            <div class="jwq-field">
                                <label for="jwqToUser">To User</label>
                                <div class="jwq-user-with-icons">
                                    <select id="jwqToUser"></select>
                                    <button type="button" class="jwq-icon-btn jwq-inward-folder-btn" data-which="to" title="Inward stock details" aria-label="Inward stock details"><i class="feather icon-folder" style="font-size:15px;"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="jwq-datetime-block">
                            <div class="jwq-field">
                                <label for="jwqDate">Date</label>
                                <input type="date" id="jwqDate">
                            </div>
                            <div class="jwq-field">
                                <label for="jwqTime">Time</label>
                                <input type="time" id="jwqTime" step="1">
                            </div>
                            <div class="jwq-time-spent">
                                <i class="feather icon-clock"></i>
                                <span>Total Time Spent</span>
                                <strong id="jwqTotalTimeDisplay">00:00:00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="jwq-tag-row">
                        <input type="text" id="jwqTagNoInput" placeholder="Tag No" autocomplete="off">
                        <button type="button" class="jwq-pill-btn" id="jwqBtnBom">BOM</button>
                        <button type="button" class="jwq-pill-btn" id="jwqBtnOrder">Order</button>
                    </div>

                    <div class="jwq-weight-adjust-strip" id="jwqWeightAdjustStrip" style="display:none;" aria-hidden="true">
                        <div class="jwq-weight-adjust-title" id="jwqWeightAdjustTitle">Add Weight</div>
                        <input type="hidden" id="jwqWeightAdjustMode" value="add">
                        <div class="jwq-weight-adjust-inner">
                            <div class="jwq-weight-adjust-field">
                                <label for="jwqWeightAdjustGrams">Weight (g) <span class="text-danger">*</span></label>
                                <input type="number" id="jwqWeightAdjustGrams" class="jwq-weight-adjust-input" min="0.001" max="999999" step="0.001" placeholder="0.000">
                            </div>
                            <div class="jwq-weight-adjust-field jwq-weight-adjust-field-grow">
                                <label for="jwqWeightAdjustRemark">Remark</label>
                                <input type="text" id="jwqWeightAdjustRemark" class="jwq-weight-adjust-input" placeholder="Optional note" autocomplete="off">
                            </div>
                            <button type="button" class="jwq-weight-adjust-save" id="jwqWeightAdjustSaveBtn">Save</button>
                        </div>
                    </div>

                    <div class="jwq-lines-toolbar">
                        <button type="button" class="jwq-btn-add-line no-print" id="jwqBtnAddLine" title="Adds a line. If no job work order is open yet, a new draft order is created (sale order can be linked later in Job Work Order).">
                            <i class="feather icon-plus"></i> Add row
                        </button>
                        <button type="button" class="head-setting-btn jwq-settings-toggle" title="Columns">
                            <i class="feather icon-settings mini-gear"></i>
                        </button>
                        <div class="columns-panel jwq-columns-popover" id="jwqColumnsPanel">
                            <div class="columns-panel-header">
                                <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                                <button type="button" class="columns-panel-close" data-close-panel="jwqColumnsPanel">&times;</button>
                            </div>
                            <div class="columns-search">
                                <input type="text" id="jwqColumnsSearch" placeholder="Search" autocomplete="off">
                            </div>
                            <div class="columns-list jwq-columns-list--picker" id="jwqColumnsList">
                                <?php foreach ($jwq_order_line_columns as $col):
                                    $lk = strtolower($col['label']);
                                ?>
                                <label class="jwq-column-picker-label" data-label="<?php echo htmlspecialchars($lk); ?>">
                                    <input type="checkbox" class="jwq-line-column-checkbox" data-col="<?php echo htmlspecialchars($col['key']); ?>" checked>
                                    <span><?php echo htmlspecialchars($col['label']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="jwq-table-wrap">
                        <table class="jwq-table" id="jwqOrderLinesTable">
                            <thead>
                                <tr>
                                    <?php foreach ($jwq_order_line_columns as $col): ?>
                                    <th data-col="<?php echo htmlspecialchars($col['key']); ?>">
                                        <?php if ($col['key'] === 'diamond_wt'): ?>
                                            <span class="jwq-th-diamond-wt"><?php echo htmlspecialchars($col['label']); ?>
                                                <button type="button" class="jwq-diamond-used-info-btn" title="Diamonds used on this line" aria-label="Diamonds used"><i class="feather icon-info"></i></button>
                                            </span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($col['label']); ?>
                                        <?php endif; ?>
                                    </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody id="jwqOrderLinesBody"></tbody>
                        </table>
                    </div>

                    <div class="jwq-bottom-split">
                        <div class="jwq-bottom-left">
                            <div class="jwq-payment-row">
                                <input type="text" id="jwqPaymentScan" placeholder="Payment" autocomplete="off">
                            </div>
                            <div class="jwq-material-head">
                                <div class="jwq-payment-icons-wrap">
                                    <div class="payment-icons jwq-payment-icons" id="jwqPaymentIcons">
                                        <div class="payment-icon payment-exchange" title="Metal Exchange">
                                            <img src="icons/metal.jpeg" alt="Metal Exchange" style="width: 45px; height: 45px;">
                                        </div>
                                        <div class="payment-icon payment-jewelry" title="Scrap Payment">
                                            <img src="icons/scrap.jpeg" alt="Scrap Payment" style="width: 45px; height: 45px;">
                                        </div>
                                        <div class="payment-icon payment-diamond" title="Diamond">
                                            <img src="icons/diamond.jpeg" alt="Diamond" style="width: 45px; height: 45px;">
                                        </div>
                                        <div class="payment-icon payment-stone" title="Stone">
                                            <img src="icons/stone.jpeg" alt="Stone" style="width: 45px; height: 45px;">
                                        </div>
                                        <div class="payment-icon payment-other" title="Other">
                                            <img src="icons/old.jpeg" alt="Other" style="width: 45px; height: 45px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="jwq-mat-table-wrap" id="jwqMatTableWrap">
                                <table class="jwq-mat-table">
                                    <thead>
                                        <tr>
                                            <th>Diamond Category</th>
                                            <th>Product*</th>
                                            <th>Weight</th>
                                            <th>Metal*</th>
                                            <th>Quantity</th>
                                            <th>Purity / Carat</th>
                                            <th>Purity Wt</th>
                                            <th class="jwq-mat-action-col" style="width:44px;" aria-label="Remove"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="jwqMaterialBody">
                                        <tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="2" style="font-weight:700;">Total</td>
                                            <td id="jwqMatTotalWt">0.00</td>
                                            <td colspan="5"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="jwq-comment-row">
                                <input type="text" id="jwqCommentInput" placeholder="Enter Comment" autocomplete="off">
                                <button type="button" id="jwqCommentAdd" aria-label="Add comment"><i class="feather icon-plus"></i></button>
                            </div>
                        </div>
                        <div class="jwq-bottom-right">
                            <div class="jwq-images-box" id="jwqImagesBox" title="Upload images">
                                <i class="feather icon-upload"></i>
                                <span>Images</span>
                                <small style="font-size:11px;font-weight:500;opacity:0.85;">Click or drag files</small>
                            </div>
                            <input type="file" id="jwqImagesInput" accept="image/*" multiple hidden>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="jwqDiamondUsedModal" tabindex="-1" role="dialog" aria-labelledby="jwqDiamondUsedModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#11294b;color:#fff;border:0;">
                <h5 class="modal-title" id="jwqDiamondUsedModalTitle">Diamonds used</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 jwq-diamond-used-table">
                        <thead>
                            <tr>
                                <th>Barcode</th>
                                <th>Product</th>
                                <th class="text-right">Weight</th>
                                <th class="text-right">Qty</th>
                                <th>Added dept</th>
                                <th>Added by</th>
                                <th>Issued</th>
                                <th class="text-center" style="width:44px;" aria-label="Remove"></th>
                            </tr>
                        </thead>
                        <tbody id="jwqDiamondUsedModalBody">
                            <tr><td colspan="8" class="text-center text-muted p-3">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="jwqInwardStockModal" tabindex="-1" role="dialog" aria-labelledby="jwqInwardStockModalTitle" aria-hidden="true" data-backdrop="true">
    <div class="modal-dialog modal-dialog-scrollable jwq-inward-stock-modal-dialog" role="document">
        <div class="modal-content" style="border-radius:8px;overflow:hidden;">
            <div class="modal-header" style="background:#11294b;color:#fff;border:none;">
                <h5 class="modal-title mb-0" id="jwqInwardStockModalTitle">Inward Stock <span id="jwqInwardStockModalContext" style="font-size:0.85rem;opacity:0.85;font-weight:500;"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:1;text-shadow:none;"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="padding:12px;background:#f8fafc;">
                <div class="jwq-inward-stock-toolbar">
                    <button type="button" class="jwq-inward-stock-tool jwq-inward-stock-tool--excel" id="jwqInwardStockBtnExcel" title="Export Excel">
                        <i class="feather icon-download" style="width:18px;height:18px;"></i>
                    </button>
                    <button type="button" class="jwq-inward-stock-tool jwq-inward-stock-tool--pdf" id="jwqInwardStockBtnPdf" title="Print / PDF">
                        <i class="feather icon-file-text" style="width:18px;height:18px;"></i>
                    </button>
                    <button type="button" class="jwq-inward-stock-tool jwq-inward-stock-tool--columns head-setting-btn jwq-inward-stock-columns-toggle" id="jwqInwardStockBtnColumns" title="Columns">
                        <i class="feather icon-settings mini-gear"></i>
                    </button>
                    <div class="columns-panel" id="jwqInwardStockColumnsPanel">
                        <div class="columns-panel-header">
                            <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                            <button type="button" class="columns-panel-close" data-close-panel="jwqInwardStockColumnsPanel">&times;</button>
                        </div>
                        <div class="columns-search">
                            <input type="text" id="jwqInwardStockColumnsSearch" placeholder="Search">
                        </div>
                        <div class="columns-list" id="jwqInwardStockColumnsList">
                            <?php foreach ($jwq_inward_stock_modal_columns as $col): ?>
                            <label data-label="<?php echo htmlspecialchars(strtolower($col['label'])); ?>">
                                <input type="checkbox" class="jwq-inward-stock-column-checkbox" data-col="<?php echo htmlspecialchars($col['key']); ?>" checked>
                                <span><?php echo htmlspecialchars($col['label']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:55vh;border:1px solid #e2e8f0;border-radius:6px;background:#fff;">
                    <table class="table table-bordered table-sm mb-0" id="jwqInwardStockTable" style="font-size:12px;">
                        <thead style="background:#eef2f8;color:#334155;">
                            <tr>
                                <?php foreach ($jwq_inward_stock_modal_columns as $col): ?>
                                <th data-col="<?php echo htmlspecialchars($col['key']); ?>"><?php echo htmlspecialchars($col['label']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="jwqInwardStockBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/mp-weight-adjust-modal.php'; ?>
<?php include 'includes/jwq-payment-modals.php'; ?>
<?php include 'includes/mp-manufacturing-print-swal.php'; ?>

<script>
window.mpDepartments = <?php echo $mp_departments_json; ?>;
window.mpDepartmentUsers = <?php echo $mp_department_users_json; ?>;
window.JWQ_ORDER_LINE_COL_KEYS = <?php echo json_encode(array_column($jwq_order_line_columns, 'key')); ?>;
window.JWQ_INWARD_STOCK_MODAL_KEYS = <?php echo json_encode(array_column($jwq_inward_stock_modal_columns, 'key')); ?>;
window.JWQ_PAGE_MODE = true;
window.JWQ_LINE_COL_STORAGE = 'jobwork_queue_page_jwq_order_lines_hidden_columns';
window.JWQ_INWARD_COL_STORAGE = 'jobwork_queue_page_jwq_inward_modal_hidden_columns';
window.JWQ_PREVIEW_QUEUE_NO = <?php echo json_encode($jwq_preview_queue_no, JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php include 'footer-script.php'; ?>
<?php include __DIR__ . '/includes/auragold_voucher_runtime_scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="assets/js/auragold-col-reorder.js"></script>
<script src="includes/jobwork-queue-page.js?v=44"></script>
</body>
</html>
