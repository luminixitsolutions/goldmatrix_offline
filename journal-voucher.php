<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_voucher_runtime_settings.php';
$auragold_voucher_runtime_client = auragold_voucher_runtime_payload_only($conn, 'Journal Voucher');

// Branches: login main + its sub-branches only (not every registry branch).
$branches = [];
if (function_exists('auragold_filter_branches_for_login_scope')) {
    $branches = auragold_filter_branches_for_login_scope();
}
if (empty($branches) && function_exists('getListMaster')) {
    $tree_root = function_exists('auragold_branch_stock_transfer_tree_root_id')
        ? (int) auragold_branch_stock_transfer_tree_root_id()
        : (function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0);
    if ($tree_root > 0) {
        $branches = getListMaster(
            'SELECT id, name, code FROM tbl_branches WHERE status = 1 AND (id = ' . $tree_root
            . ' OR IFNULL(main_branch_id, 0) = ' . $tree_root . ') ORDER BY name ASC'
        );
    }
}
if (empty($branches)) {
    $branches = getListMaster("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
}
if (empty($branches)) {
    $branches = [['id' => 1, 'name' => 'Main Branch', 'code' => 'MB']];
}
if (!is_array($branches)) {
    $branches = [];
}
// Normalize shape for JS (ensure name/id keys)
$branches = array_values(array_map(static function ($b) {
    return [
        'id'   => (int) ($b['id'] ?? 0),
        'name' => (string) ($b['name'] ?? ''),
        'code' => (string) ($b['code'] ?? ''),
    ];
}, $branches));

// Account ledger: Cash, Bank, common accounts + all customer names from ledger
$account_ledger_options = [
    ['id' => 'Cash', 'name' => 'Cash'],
    ['id' => 'Bank', 'name' => 'Bank'],
    ['id' => 'CUSTOMER LEDGER', 'name' => 'CUSTOMER LEDGER'],
    ['id' => 'Sundry Debtors', 'name' => 'Sundry Debtors'],
    ['id' => 'Sundry Creditors', 'name' => 'Sundry Creditors'],
    ['id' => 'Sales', 'name' => 'Sales'],
    ['id' => 'Purchase', 'name' => 'Purchase'],
    ['id' => 'Capital', 'name' => 'Capital'],
    ['id' => 'Expenses', 'name' => 'Expenses'],
];
$ledger_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
if ($ledger_check && mysqli_num_rows($ledger_check) > 0) {
    $customer_ledgers = getList("SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1 AND TRIM(IFNULL(customer_name,'')) != '' ORDER BY customer_name ASC");
    $existing = array_map(function($o) { return $o['id']; }, $account_ledger_options);
    foreach ($customer_ledgers as $cl) {
        $name = trim($cl['customer_name'] ?? '');
        if ($name !== '' && !in_array($name, $existing)) {
            $account_ledger_options[] = ['id' => $name, 'name' => $name];
            $existing[] = $name;
        }
    }
}

// Against options: show all created RV list, SI list, PV list, etc. from DB
$against_options = [['id' => '', 'name' => '-- Select --']];
$against_seen = [''];
$add_against = function($ref, $label) use (&$against_options, &$against_seen) {
    $ref = trim((string)$ref);
    if ($ref !== '' && !in_array($ref, $against_seen)) {
        $against_options[] = ['id' => $ref, 'name' => $label ?: $ref];
        $against_seen[] = $ref;
    }
};
// RV - Receipt Vouchers
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
if ($t && mysqli_num_rows($t) > 0) {
    foreach (getList("SELECT voucher_no FROM tbl_receipt_vouchers ORDER BY id DESC LIMIT 300") as $r) {
        $add_against($r['voucher_no'] ?? '', $r['voucher_no'] ?? '');
    }
}
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_receipt_vouchers'");
if ($t && mysqli_num_rows($t) > 0) {
    mysqli_free_result($t);
    foreach (getList("SELECT voucher_no FROM tbl_sale_receipt_vouchers ORDER BY id DESC LIMIT 300") as $r) {
        $add_against($r['voucher_no'] ?? '', $r['voucher_no'] ?? '');
    }
} elseif ($t) {
    mysqli_free_result($t);
}
// PV - Payment Vouchers
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
if ($t && mysqli_num_rows($t) > 0) {
    foreach (getList("SELECT voucher_no FROM tbl_payment_vouchers ORDER BY id DESC LIMIT 300") as $r) {
        $add_against($r['voucher_no'] ?? '', $r['voucher_no'] ?? '');
    }
}
// CV - Contra Vouchers
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_contra_vouchers'");
if ($t && mysqli_num_rows($t) > 0) {
    foreach (getList("SELECT voucher_no FROM tbl_contra_vouchers ORDER BY id DESC LIMIT 300") as $r) {
        $add_against($r['voucher_no'] ?? '', $r['voucher_no'] ?? '');
    }
}
// JV - Journal Vouchers (exclude current when editing)
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_journal_vouchers'");
if ($t && mysqli_num_rows($t) > 0) {
    $exclude = $edit_voucher_id > 0 ? " AND id != " . (int)$edit_voucher_id : '';
    foreach (getList("SELECT voucher_no FROM tbl_journal_vouchers WHERE 1=1 $exclude ORDER BY id DESC LIMIT 300") as $r) {
        $add_against($r['voucher_no'] ?? '', $r['voucher_no'] ?? '');
    }
}
// SI - Sale Invoices
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_invoices'");
if ($t && mysqli_num_rows($t) > 0) {
    foreach (getList("SELECT invoice_no FROM tbl_sale_invoices ORDER BY id DESC LIMIT 300") as $r) {
        $add_against($r['invoice_no'] ?? '', $r['invoice_no'] ?? '');
    }
}
// PI - Purchase Invoices
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_invoices'");
if ($t && mysqli_num_rows($t) > 0) {
    foreach (getList("SELECT invoice_no FROM tbl_purchase_invoices ORDER BY id DESC LIMIT 300") as $r) {
        $add_against($r['invoice_no'] ?? '', $r['invoice_no'] ?? '');
    }
}
// PQ - Purchase Quotations
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_quotations'");
if ($t && mysqli_num_rows($t) > 0) {
    foreach (getList("SELECT quotation_no FROM tbl_purchase_quotations ORDER BY id DESC LIMIT 300") as $r) {
        $add_against($r['quotation_no'] ?? '', $r['quotation_no'] ?? '');
    }
}
// SO - Sale Quotations
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_quotations'");
if ($t && mysqli_num_rows($t) > 0) {
    foreach (getList("SELECT quotation_no FROM tbl_sale_quotations ORDER BY id DESC LIMIT 300") as $r) {
        $ref = $r['quotation_no'] ?? '';
        $add_against($ref, $ref);
    }
}

// Get next voucher number
$next_voucher_no = 'JV-1';
$tbl_exists = false;
$check_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_journal_vouchers'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    $tbl_exists = true;
    $last_voucher = getRecord("SELECT voucher_no FROM tbl_journal_vouchers ORDER BY id DESC LIMIT 1");
    if ($last_voucher && !empty($last_voucher['voucher_no'])) {
        $last_num = (int)preg_replace('/[^0-9]/', '', $last_voucher['voucher_no']);
        $next_voucher_no = 'JV-' . ($last_num + 1);
    }
}

// Load voucher for editing if ID provided
$edit_voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_voucher = null;
$edit_items = [];

if ($edit_voucher_id > 0 && $tbl_exists) {
    $edit_voucher = getRecord("SELECT * FROM tbl_journal_vouchers WHERE id = $edit_voucher_id");
    if ($edit_voucher) {
        $edit_items = getList("SELECT * FROM tbl_journal_voucher_items WHERE voucher_id = $edit_voucher_id ORDER BY id ASC");
        $next_voucher_no = $edit_voucher['voucher_no'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Journal Voucher - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
    <link rel="stylesheet" href="assets/libs/select2/select2.css">
</head>
<style>
body { margin: 0; padding: 0; overflow-x: hidden; min-height: 100vh; background: #f4f6fb; font-family: Roboto, sans-serif; font-size: 11px; }
.layout-wrapper { min-height: 100vh; overflow: visible; }
.layout-container { margin-left: 0 !important; width: 100% !important; padding: 8px 12px; }
.layout-content { min-height: calc(100vh - 120px); overflow-y: auto; padding: 0 0 56px; margin: 0; display: flex; flex-direction: column; gap: 0; }
.layout-content > .journal-form-bar { flex-shrink: 0; }
.layout-content > .voucher-list-card { flex-shrink: 0; display: flex; flex-direction: column; }
.layout-content > .journal-footer { flex-shrink: 0; }
.layout-content > .saved-voucher-list-card { flex-shrink: 0; margin-top: 8px; margin-bottom: 16px; display: flex; flex-direction: column; }
#layout-sidenav { display: none !important; }

/* Top form bar - Journal Voucher No, Date, New+, Save */
.journal-form-bar { background: #fff; padding: 8px 12px; border-radius: 4px; border: 1px solid #e2e8f0; margin-bottom: 2px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
.journal-form-bar .form-group { margin: 0; display: flex; align-items: center; gap: 6px; }
.journal-form-bar label { margin: 0; font-size: 11px; font-weight: 600; color: #ffffff; white-space: nowrap; }
.journal-form-bar .form-control { height: 28px; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 8px; min-width: 0; max-width: 100px; }
.journal-form-bar .form-control#voucherNo { max-width: 80px; background: #f8fafc; }
.voucher-no-label { font-size: 11px; color: #64748b; white-space: nowrap; }
.btn-new { background: #ec4899; color: #fff; border: none; padding: 5px 14px; height: 28px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.btn-new:hover { background: #db2777; color: #fff; }
.btn-save { background: #2563eb; color: #fff; border: none; padding: 5px 16px; height: 28px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.btn-save:hover { background: #1d4ed8; color: #fff; }

.voucher-list-card { background: #fff; border-radius: 4px; border: 1px solid #e2e8f0; overflow: hidden; margin-top: 0; margin-bottom: 0; }
.voucher-list-header { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; min-height: 32px; }
.voucher-list-header h5 { margin: 0; font-size: 12px; font-weight: 600; color: #11294b; }
.voucher-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.voucher-table th { background: #f8fafc; padding: 4px 6px; text-align: left; font-weight: 600; color: #000; border-bottom: 1px solid #e2e8f0; white-space: nowrap; font-size: 10px; }
.voucher-table td { padding: 3px 6px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
.voucher-table tbody tr:hover { background: #f8fafc; }
.voucher-table .text-right { text-align: right; }
/* One height for Branch select, native inputs, and Select2 (Account / Against) */
.voucher-table input[type="text"],
.voucher-table input[type="number"],
.voucher-table input[type="date"],
.voucher-table select,
.voucher-table .form-control {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
    border-radius: 3px;
    font-size: 11px;
    height: 32px;
    min-height: 32px;
    max-height: 32px;
    padding: 4px 8px;
    line-height: 1.25;
}
.voucher-table select.form-control,
.voucher-table select.row-branch {
    padding-right: 24px;
    cursor: pointer;
}
.voucher-table td.action-cell { padding: 2px 4px; white-space: nowrap; width: 1%; }
.voucher-table .action-btns { display: inline-flex; align-items: center; gap: 2px; flex-wrap: nowrap; }
.voucher-table .btn-icon { background: none; border: none; padding: 1px 3px; cursor: pointer; color: #64748b; font-size: 12px; line-height: 1; display: inline-flex; align-items: center; justify-content: center; }
.voucher-table .btn-icon:hover { color: #11294b; }
.voucher-table .btn-icon.delete:hover { color: #dc2626; }
.col-settings-btn { background: none; border: none; padding: 2px 6px; cursor: pointer; color: #64748b; font-size: 12px; }
.col-settings-btn:hover { color: #11294b; }
.btn-add-row { background: #11294b; color: #fff; border: none; padding: 4px 10px; height: 26px; border-radius: 4px; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.btn-add-row:hover { background: #1e3a5f; color: #fff; }

/* Footer: Comment + Credit Wt, Debit Wt, Debit Total, Credit Total */
.journal-footer { background: #fff; padding: 8px 12px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 4px 4px; display: flex; flex-wrap: wrap; align-items: flex-start; gap: 16px 24px; }
.journal-footer .comment-section { flex: 1; min-width: 200px; }
.journal-footer .comment-section label { display: block; font-size: 11px; font-weight: 600; color: #ffffff; margin-bottom: 4px; }
.journal-footer .comment-section textarea { width: 100%; min-height: 60px; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 12px; resize: vertical; }
.journal-footer .summary-section { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: center; }
.journal-footer .summary-section .summary-item { display: flex; align-items: center; gap: 6px; }
.journal-footer .summary-section .summary-item label { margin: 0; font-size: 11px; font-weight: 600; color: #ffffff; white-space: nowrap; }
.journal-footer .summary-section .summary-item input { width: 100px; height: 28px; padding: 4px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 12px; text-align: right; }
/* Select2: same visual height as native Branch select + row inputs */
.voucher-table .select2-container { width: 100% !important; font-size: 11px; }
.voucher-table .select2-container--default .select2-selection--single {
    height: 32px;
    min-height: 32px;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
    border-radius: 3px;
    padding: 0 24px 0 6px;
}
.voucher-table .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 30px;
    padding-left: 4px;
    padding-right: 0;
    color: #334155;
}
.voucher-table .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 30px;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
}
.voucher-table .select2-container--default.select2-container--open .select2-selection--single,
.voucher-table .select2-container--default.select2-container--focus .select2-selection--single {
    border-color: #94a3b8;
}

/* Saved voucher list (like payment-voucher Payment List) */
.saved-voucher-list-card { background: #fff; border-radius: 4px; border: 1px solid #e2e8f0; overflow: hidden; }
.saved-voucher-list-header { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.saved-voucher-list-header h5 { margin: 0; font-size: 12px; font-weight: 600; color: #11294b; }
.saved-voucher-list-scroll { overflow-x: auto; }
.saved-voucher-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 0; }
.saved-voucher-table thead th { background: #11294b; color: #fff; padding: 8px 10px; font-weight: 600; white-space: nowrap; border: 1px solid #0f2444; font-size: 11px; }
.saved-voucher-table tbody td { padding: 6px 10px; border: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
.saved-voucher-table tbody tr[data-voucher-id] { cursor: pointer; }
.saved-voucher-table tbody tr[data-voucher-id]:hover { background: #f1f5f9; }
.saved-voucher-table .saved-voucher-actions { white-space: nowrap; width: 70px; }
.saved-voucher-table .saved-voucher-actions .btn { padding: 2px 6px; line-height: 1; }
.saved-voucher-table .text-right { text-align: right; }
.saved-voucher-table .svl-col-drag-h { display: inline-flex; align-items: center; margin-right: 4px; cursor: grab; opacity: 0.75; }
.saved-voucher-table .svl-col-drag-h:active { cursor: grabbing; }
.saved-voucher-list-footer { padding: 10px 12px 12px; border-top: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; background: #fafbfc; }
.saved-voucher-pagination { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 11px; color: #64748b; }
.saved-voucher-pagination .btn { font-size: 11px; padding: 3px 10px; height: 26px; line-height: 1.2; }
.saved-voucher-pagination .svl-page-size { height: 26px; font-size: 11px; padding: 2px 6px; border: 1px solid #e2e8f0; border-radius: 4px; }
.voucher-list-export-wrap { position: relative; display: inline-block; }
.voucher-list-export-menu { display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; min-width: 160px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 1000; padding: 4px 0; }
.voucher-list-export-menu.show { display: block; }
.voucher-list-export-menu button { display: block; width: 100%; text-align: left; border: none; background: none; padding: 8px 12px; font-size: 0.75rem; color: #334155; cursor: pointer; }
.voucher-list-export-menu button:hover { background: #f1f5f9; }
</style>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div class="layout-container">
            <?php include 'sidebar.php';?>
            <div class="layout-content">
                <!-- Top bar: Journal Voucher No, Date, New +, Save -->
                <div class="journal-form-bar">
                    <div class="form-group">
                        <span class="voucher-no-label">Journal Voucher No:</span>
                        <input type="text" class="form-control" id="voucherNo" value="<?= htmlspecialchars($next_voucher_no) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" class="form-control" id="voucherDate" value="<?= $edit_voucher ? date('Y-m-d', strtotime($edit_voucher['voucher_date'])) : date('Y-m-d') ?>">
                    </div>
                    <div class="form-group" style="margin-left: auto;">
                        <a href="journal-voucher.php" class="btn btn-new" id="btnNew"><i class="feather icon-plus"></i> New</a>
                        <button type="button" class="btn btn-save" id="btnSave"><i class="feather icon-save"></i> Save</button>
                    </div>
                </div>

                <!-- Voucher items table -->
                <div class="voucher-list-card">
                    <div class="voucher-list-header">
                        <h5>Voucher List</h5>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <button type="button" class="btn-add-row" id="btnAddRow"><i class="feather icon-plus"></i> Add Row</button>
                            <button type="button" class="col-settings-btn" id="btnColSettings" title="Columns"><i class="feather icon-settings"></i></button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="voucher-table" id="voucherTable">
                            <thead>
                                <tr>
                                    <th data-column="branch">Branch *</th>
                                    <th data-column="account_ledger">Account Ledger *</th>
                                    <th data-column="cr_dr">Cr/Dr *</th>
                                    <th data-column="against">Against</th>
                                    <th data-column="ref_no">Ref. No.</th>
                                    <th data-column="ref_date">Ref. Date</th>
                                    <th data-column="amount" class="text-right">Amount</th>
                                    <th data-column="metal">Metal</th>
                                    <th data-column="purity_wt" class="text-right">Purity Wt</th>
                                    <th class="no-sort action-cell" style="width: 52px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="voucherTableBody">
                                <?php 
                                if (!empty($edit_items)) {
                                    foreach ($edit_items as $item) {
                                        $ref_date = !empty($item['ref_date']) ? date('Y-m-d', strtotime($item['ref_date'])) : '';
                                        $is_cr = ($item['cr_dr'] ?? '') === 'Cr';
                                        echo '<tr data-id="'.(int)$item['id'].'">';
                                        echo '<td data-column="branch"><select class="form-control row-branch">';
                                        foreach ($branches as $b) {
                                            $sel = (isset($item['branch_id']) && (int)$item['branch_id'] === (int)$b['id']) || ($item['branch_name'] ?? '') === $b['name'] ? ' selected' : '';
                                            echo '<option value="'.$b['id'].'" data-name="'.htmlspecialchars($b['name']).'"'.$sel.'>'.htmlspecialchars($b['name']).'</option>';
                                        }
                                        echo '</select></td>';
                                        echo '<td data-column="account_ledger"><select class="form-control row-account-ledger">';
                                        foreach ($account_ledger_options as $opt) {
                                            $sel = ($item['account_ledger'] ?? '') === $opt['id'] ? ' selected' : '';
                                            echo '<option value="'.htmlspecialchars($opt['id']).'">'.htmlspecialchars($opt['name']).'</option>';
                                        }
                                        echo '</select></td>';
                                        echo '<td data-column="cr_dr"><label class="mb-0"><input type="radio" name="row_crdr_'.$item['id'].'" value="Cr" '.($is_cr?'checked':'').'> Cr</label> <label class="mb-0"><input type="radio" name="row_crdr_'.$item['id'].'" value="Dr" '.(!$is_cr?'checked':'').'> Dr</label></td>';
                                        $against_val = $item['against'] ?? '';
                                        echo '<td data-column="against"><select class="form-control row-against">';
                                        $against_found = false;
                                        foreach ($against_options as $ao) {
                                            $sel = $against_val === $ao['id'] ? ' selected' : '';
                                            if ($sel) $against_found = true;
                                            echo '<option value="'.htmlspecialchars($ao['id']).'"'.$sel.'>'.htmlspecialchars($ao['name']).'</option>';
                                        }
                                        if ($against_val !== '' && !$against_found) {
                                            echo '<option value="'.htmlspecialchars($against_val).'" selected>'.htmlspecialchars($against_val).'</option>';
                                        }
                                        echo '</select></td>';
                                        echo '<td data-column="ref_no"><input type="text" class="form-control row-ref-no" value="'.htmlspecialchars($item['ref_no']??'').'" placeholder="Ref. No."></td>';
                                        echo '<td data-column="ref_date"><input type="date" class="form-control row-ref-date" value="'.$ref_date.'"></td>';
                                        echo '<td data-column="amount" class="text-right"><input type="number" class="form-control row-amount text-right" value="'.htmlspecialchars($item['amount']??'0').'" step="0.01" min="0" placeholder="0.00"></td>';
                                        echo '<td data-column="metal"><input type="text" class="form-control row-metal" value="'.htmlspecialchars($item['metal']??'').'" placeholder="Metal"></td>';
                                        echo '<td data-column="purity_wt" class="text-right"><input type="number" class="form-control row-purity-wt text-right" value="'.htmlspecialchars($item['purity_wt']??'0').'" step="0.0001" min="0" placeholder="0.0000"></td>';
                                        echo '<td class="action-cell"><span class="action-btns"><button type="button" class="btn-icon edit-row" title="Edit"><i class="feather icon-edit-2"></i></button><button type="button" class="btn-icon delete delete-row" title="Delete"><i class="feather icon-trash-2"></i></button></span></td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer: Comment + Credit Wt, Debit Wt, Debit Total, Credit Total -->
                <div class="journal-footer">
                    <div class="comment-section">
                        <label>Comment</label>
                        <textarea class="form-control" id="comment" placeholder="Comment"><?= $edit_voucher ? htmlspecialchars($edit_voucher['comment'] ?? '') : '' ?></textarea>
                    </div>
                    <div class="summary-section">
                        <div class="summary-item">
                            <label>Credit Wt</label>
                            <input type="text" id="creditWt" readonly value="<?= $edit_voucher ? number_format((float)($edit_voucher['credit_wt'] ?? 0), 4) : '0.0000' ?>">
                        </div>
                        <div class="summary-item">
                            <label>Debit Wt</label>
                            <input type="text" id="debitWt" readonly value="<?= $edit_voucher ? number_format((float)($edit_voucher['debit_wt'] ?? 0), 4) : '0.0000' ?>">
                        </div>
                        <div class="summary-item">
                            <label>Debit Total</label>
                            <input type="text" id="debitTotal" readonly value="<?= $edit_voucher ? number_format((float)($edit_voucher['debit_total'] ?? 0), 2) : '0.00' ?>">
                        </div>
                        <div class="summary-item">
                            <label>Credit Total</label>
                            <input type="text" id="creditTotal" readonly value="<?= $edit_voucher ? number_format((float)($edit_voucher['credit_total'] ?? 0), 2) : '0.00' ?>">
                        </div>
                    </div>
                </div>

                <!-- Saved Journal Vouchers -->
                <div class="saved-voucher-list-card" id="journalSavedListCard">
                    <div class="saved-voucher-list-header">
                        <h5>Journal Voucher List</h5>
                        <div class="voucher-list-export-wrap">
                            <button type="button" class="btn btn-sm svl-export-btn" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.5rem; font-size: 0.7rem;">Export <i class="feather icon-chevron-down" style="font-size: 0.65rem;"></i></button>
                            <div class="voucher-list-export-menu">
                                <button type="button" class="js-svl-export-excel">Export in Excel</button>
                                <button type="button" class="js-svl-export-pdf">Export in PDF</button>
                            </div>
                        </div>
                    </div>
                    <div class="saved-voucher-list-scroll">
                        <table class="table table-bordered table-sm saved-voucher-table">
                            <thead>
                                <tr>
                                    <th data-column="sr-no"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Sr.No.</th>
                                    <th data-column="voucher_date"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Date</th>
                                    <th data-column="voucher_no"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Voucher No.</th>
                                    <th data-column="comment"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Comment</th>
                                    <th data-column="debit_total" class="text-right"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Debit Total</th>
                                    <th data-column="credit_total" class="text-right"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Credit Total</th>
                                    <th data-column="actions" style="width: 70px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="journalSavedListBody">
                                <tr class="no-rows"><td colspan="7" class="text-center text-muted py-3">No Rows To Show</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="saved-voucher-list-footer saved-voucher-pagination">
                        <span class="svl-page-info">Loading…</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="margin: 0; font-size: 11px; color: #64748b;">Rows:</label>
                            <select class="svl-page-size">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-secondary svl-page-prev">Previous</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary svl-page-next">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Column settings modal -->
<div class="modal fade" id="colSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Columns</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="colList"></div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php';?>
<?php include __DIR__ . '/includes/auragold_voucher_runtime_scripts.php'; ?>
<script src="assets/libs/select2/select2.js"></script>
<script src="assets/libs/sortablejs/sortable.js"></script>
<script src="assets/js/voucher-saved-list.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/voucher-saved-list.js'); ?>"></script>
<script>
(function() {
    var voucherId = <?= (int)$edit_voucher_id ?>;
    var nextVoucherNo = <?= json_encode($next_voucher_no) ?>;
    var branches = <?= json_encode($branches) ?>;
    var ledgerOptions = <?= json_encode($account_ledger_options) ?>;
    var againstOptions = <?= json_encode($against_options) ?>;

    function addRow() {
        var tbody = document.getElementById('voucherTableBody');
        var rowId = 'row_' + Date.now();
        var branchOpts = branches.map(function(b){ return '<option value="'+b.id+'" data-name="'+b.name.replace(/"/g,'&quot;')+'">'+b.name+'</option>'; }).join('');
        var ledgerOpts = ledgerOptions.map(function(o){ return '<option value="'+o.id.replace(/"/g,'&quot;')+'">'+o.name.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</option>'; }).join('');
        var againstOpts = againstOptions.map(function(o){ return '<option value="'+o.id+'">'+o.name+'</option>'; }).join('');
        var tr = document.createElement('tr');
        tr.setAttribute('data-row-id', rowId);
        tr.innerHTML = 
            '<td data-column="branch"><select class="form-control row-branch">'+branchOpts+'</select></td>' +
            '<td data-column="account_ledger"><select class="form-control row-account-ledger">'+ledgerOpts+'</select></td>' +
            '<td data-column="cr_dr"><label class="mb-0"><input type="radio" name="rtype_'+rowId+'" value="Cr"> Cr</label> <label class="mb-0"><input type="radio" name="rtype_'+rowId+'" value="Dr" checked> Dr</label></td>' +
            '<td data-column="against"><select class="form-control row-against">'+againstOpts+'</select></td>' +
            '<td data-column="ref_no"><input type="text" class="form-control row-ref-no" placeholder="Ref. No."></td>' +
            '<td data-column="ref_date"><input type="date" class="form-control row-ref-date" value="<?= date('Y-m-d') ?>"></td>' +
            '<td data-column="amount" class="text-right"><input type="number" class="form-control row-amount text-right" value="0" step="0.01" min="0" placeholder="0.00"></td>' +
            '<td data-column="metal"><input type="text" class="form-control row-metal" placeholder="Metal"></td>' +
            '<td data-column="purity_wt" class="text-right"><input type="number" class="form-control row-purity-wt text-right" value="0" step="0.0001" min="0" placeholder="0.0000"></td>' +
            '<td class="action-cell"><span class="action-btns"><button type="button" class="btn-icon edit-row" title="Edit"><i class="feather icon-edit-2"></i></button><button type="button" class="btn-icon delete delete-row" title="Delete"><i class="feather icon-trash-2"></i></button></span></td>';
        tbody.appendChild(tr);
        bindRowEvents(tr);
        initSelect2Row(tr);
        updateTotals();
    }

    function initSelect2Row(tr) {
        if (typeof $ === 'undefined' || !$.fn.select2) return;
        $(tr).find('.row-account-ledger').select2({ placeholder: 'Search ledger...', allowClear: true, width: '100%' });
        $(tr).find('.row-against').select2({ placeholder: 'Search RV, SI...', allowClear: true, width: '100%' });
    }

    function initAllSelect2() {
        if (typeof $ === 'undefined' || !$.fn.select2) return;
        $('#voucherTableBody .row-account-ledger').select2({ placeholder: 'Search ledger...', allowClear: true, width: '100%' });
        $('#voucherTableBody .row-against').select2({ placeholder: 'Search RV, SI...', allowClear: true, width: '100%' });
    }

    function bindRowEvents(tr) {
        var delBtn = tr.querySelector('.delete-row');
        if (delBtn) delBtn.addEventListener('click', function() { tr.remove(); updateTotals(); });
        var amtInput = tr.querySelector('.row-amount');
        if (amtInput) amtInput.addEventListener('input', updateTotals);
        var purInput = tr.querySelector('.row-purity-wt');
        if (purInput) purInput.addEventListener('input', updateTotals);
        tr.querySelectorAll('input[type="radio"]').forEach(function(r) {
            r.addEventListener('change', updateTotals);
        });
    }

    function updateTotals() {
        var debitTotal = 0, creditTotal = 0, debitWt = 0, creditWt = 0;
        document.querySelectorAll('#voucherTableBody tr').forEach(function(tr) {
            var crR = tr.querySelector('input[type=radio][value=Cr]');
            var isCr = crR && crR.checked;
            var amt = parseFloat(tr.querySelector('.row-amount').value) || 0;
            var wt = parseFloat(tr.querySelector('.row-purity-wt').value) || 0;
            if (isCr) { creditTotal += amt; creditWt += wt; } else { debitTotal += amt; debitWt += wt; }
        });
        document.getElementById('debitTotal').value = debitTotal.toFixed(2);
        document.getElementById('creditTotal').value = creditTotal.toFixed(2);
        document.getElementById('debitWt').value = debitWt.toFixed(4);
        document.getElementById('creditWt').value = creditWt.toFixed(4);
    }

    function getItems() {
        var items = [];
        document.querySelectorAll('#voucherTableBody tr').forEach(function(tr) {
            var branchSel = tr.querySelector('.row-branch');
            var ledgerSel = tr.querySelector('.row-account-ledger');
            var crRadio = tr.querySelector('input[type=radio][value=Cr]');
            var isCr = crRadio && crRadio.checked;
            items.push({
                branch_id: branchSel ? branchSel.value : '',
                branch_name: branchSel && branchSel.options[branchSel.selectedIndex] ? (branchSel.options[branchSel.selectedIndex].getAttribute('data-name') || branchSel.options[branchSel.selectedIndex].text) : '',
                account_ledger: ledgerSel ? ledgerSel.value : '',
                cr_dr: isCr ? 'Cr' : 'Dr',
                against: (tr.querySelector('.row-against') ? tr.querySelector('.row-against').value : '') || '',
                ref_no: (tr.querySelector('.row-ref-no') || {}).value || '',
                ref_date: (tr.querySelector('.row-ref-date') || {}).value || '',
                amount: parseFloat((tr.querySelector('.row-amount') || {}).value) || 0,
                metal: (tr.querySelector('.row-metal') || {}).value || '',
                purity_wt: parseFloat((tr.querySelector('.row-purity-wt') || {}).value) || 0
            });
        });
        return items;
    }

    document.getElementById('btnAddRow').addEventListener('click', addRow);
    document.querySelectorAll('#voucherTableBody tr').forEach(function(tr) { bindRowEvents(tr); });

    document.getElementById('btnSave').addEventListener('click', function() {
        updateTotals();
        var voucherDate = document.getElementById('voucherDate').value;
        var comment = document.getElementById('comment').value;
        var items = getItems();
        if (items.length === 0) { alert('Add at least one row.'); return; }
        var hasValid = items.some(function (it) {
            return it.account_ledger && ((parseFloat(it.amount) || 0) > 0 || (parseFloat(it.purity_wt) || 0) > 0);
        });
        if (!hasValid) {
            alert('Add at least one row with Account Ledger and Amount (or metal weight).');
            return;
        }
        var debitTotal = parseFloat(String(document.getElementById('debitTotal').value).replace(/,/g, '')) || 0;
        var creditTotal = parseFloat(String(document.getElementById('creditTotal').value).replace(/,/g, '')) || 0;
        var hasDr = items.some(function (it) {
            return it.cr_dr === 'Dr' && it.account_ledger && ((parseFloat(it.amount) || 0) > 0 || (parseFloat(it.purity_wt) || 0) > 0);
        });
        var hasCr = items.some(function (it) {
            return it.cr_dr === 'Cr' && it.account_ledger && ((parseFloat(it.amount) || 0) > 0 || (parseFloat(it.purity_wt) || 0) > 0);
        });
        if (!hasDr || !hasCr) {
            alert('Journal needs both sides: at least one Debit (Dr) and one Credit (Cr) account row.');
            return;
        }
        if (Math.abs(debitTotal - creditTotal) > 0.01) {
            alert('Debit Total and Credit Total must match.');
            return;
        }

        var formData = new FormData();
        formData.append('voucher_id', voucherId);
        formData.append('voucher_no', document.getElementById('voucherNo').value || nextVoucherNo);
        formData.append('voucher_date', voucherDate);
        formData.append('comment', comment);
        formData.append('credit_wt', document.getElementById('creditWt').value || '0');
        formData.append('debit_wt', document.getElementById('debitWt').value || '0');
        formData.append('debit_total', document.getElementById('debitTotal').value || '0');
        formData.append('credit_total', document.getElementById('creditTotal').value || '0');
        formData.append('items', JSON.stringify(items));

        fetch('ajax/save-journal-voucher.php', { method: 'POST', body: formData })
            .then(function(r) { return r.text().then(function(text) {
                try {
                    return { ok: r.ok, data: text ? JSON.parse(text) : {} };
                } catch (e) {
                    return { ok: false, data: { status: 'error', message: (text && text.length < 500) ? text : 'Invalid response from server.' } };
                }
            }); })
            .then(function(result) {
                var data = result.data;
                if (result.ok && data.status === 'success') {
                    alert(data.message || 'Saved successfully.');
                    // Reload blank form ready for a new journal voucher
                    window.location.href = 'journal-voucher.php';
                } else {
                    alert(data.message || 'Error saving.');
                }
            })
            .catch(function(err) { alert('Network error. ' + (err && err.message ? err.message : '')); });
    });

    updateTotals();

    // Column settings
    var colDefs = [
        { key: 'branch', label: 'Branch' },
        { key: 'account_ledger', label: 'Account Ledger' },
        { key: 'cr_dr', label: 'Cr/Dr' },
        { key: 'against', label: 'Against' },
        { key: 'ref_no', label: 'Ref. No.' },
        { key: 'ref_date', label: 'Ref. Date' },
        { key: 'amount', label: 'Amount' },
        { key: 'metal', label: 'Metal' },
        { key: 'purity_wt', label: 'Purity Wt' }
    ];
    function getColPrefs() { try { return JSON.parse(localStorage.getItem('journal_voucher_columns') || '{}'); } catch(e) { return {}; } }
    function saveColPrefs(p) { localStorage.setItem('journal_voucher_columns', JSON.stringify(p)); }
    function applyColVisibility() {
        var p = getColPrefs();
        colDefs.forEach(function(c) {
            var vis = p[c.key] !== false;
            document.querySelectorAll('[data-column="'+c.key+'"]').forEach(function(el) { el.style.display = vis ? '' : 'none'; });
        });
    }
    document.getElementById('btnColSettings').addEventListener('click', function() {
        var html = '';
        var p = getColPrefs();
        colDefs.forEach(function(c) {
            var checked = p[c.key] !== false ? 'checked' : '';
            html += '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="col_'+c.key+'" '+checked+'><label class="custom-control-label" for="col_'+c.key+'">'+c.label+'</label></div>';
        });
        document.getElementById('colList').innerHTML = html;
        document.getElementById('colList').querySelectorAll('input').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var prefs = getColPrefs();
                prefs[cb.id.replace('col_','')] = cb.checked;
                saveColPrefs(prefs);
                applyColVisibility();
            });
        });
        $('#colSettingsModal').modal('show');
    });
    applyColVisibility();

    var rowCount = document.querySelectorAll('#voucherTableBody tr').length;
    if (rowCount === 0) addRow();
    else initAllSelect2();

    if (typeof window.initVoucherSavedList === 'function') {
        window.initVoucherSavedList({
            root: '#journalSavedListCard',
            tableSelector: '#journalSavedListCard .saved-voucher-table',
            bodySelector: '#journalSavedListBody',
            paginationSelector: '.saved-voucher-pagination',
            getUrl: 'ajax/get-journal-vouchers.php',
            deleteUrl: 'ajax/delete-journal-voucher.php',
            editPage: 'journal-voucher.php',
            deleteConfirm: 'Are you sure you want to delete this journal voucher? This cannot be undone.',
            pageSize: 10,
            columnOrderKey: 'journalSavedListColumnOrder',
            exportExcelUrl: 'ajax/export-simple-voucher-list-excel.php?kind=journal',
            exportPdfUrl: 'ajax/export-simple-voucher-list-pdf.php?kind=journal',
            columns: [
                { key: 'voucher_date', field: 'voucher_date', label: 'Date', format: 'date' },
                { key: 'voucher_no', field: 'voucher_no', label: 'Voucher No.' },
                { key: 'comment', field: 'comment', label: 'Comment' },
                { key: 'debit_total', field: 'debit_total', label: 'Debit Total', format: 'amount', align: 'right' },
                { key: 'credit_total', field: 'credit_total', label: 'Credit Total', format: 'amount', align: 'right' }
            ]
        });
    }
})();
</script>
</body>
</html>
