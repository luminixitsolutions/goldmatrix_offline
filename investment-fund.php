<?php
session_start();
require_once 'config.php';

// Ledger / sundry options for customer creation modal (same as sale-invoice)
$ledger_groups = [
    ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
    ['id' => 3, 'name' => 'Bank Accounts'],
    ['id' => 4, 'name' => 'Cash'],
    ['id' => 5, 'name' => 'Sales'],
    ['id' => 6, 'name' => 'Purchase'],
    ['id' => 7, 'name' => 'Expenses'],
    ['id' => 8, 'name' => 'Income'],
    ['id' => 9, 'name' => 'Capital'],
    ['id' => 10, 'name' => 'Loans & Advances'],
    ['id' => 11, 'name' => 'Fixed Assets'],
    ['id' => 12, 'name' => 'Current Assets'],
    ['id' => 13, 'name' => 'Current Liabilities'],
    ['id' => 14, 'name' => 'Investment'],
];
$sundry_options = [
    ['id' => 2, 'name' => 'Sundry Creditors'],
];
$nationalities_modal = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
if (!is_array($nationalities_modal)) {
    $nationalities_modal = [];
}

$investment_table_exists = false;
$t_if = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_investment_funds'");
if ($t_if && mysqli_num_rows($t_if) > 0) {
    $investment_table_exists = true;
}

$next_fund_no = 'IF-1';
if ($investment_table_exists) {
    $last_if = getRecord("SELECT fund_no FROM tbl_investment_funds ORDER BY id DESC LIMIT 1");
    if ($last_if && !empty($last_if['fund_no'])) {
        $last_num = (int) preg_replace('/[^0-9]/', '', $last_if['fund_no']);
        $next_fund_no = 'IF-' . ($last_num + 1);
    }
}

$sales_person_default = '';
if (!empty($_SESSION['name'])) {
    $sales_person_default = trim((string) $_SESSION['name']);
} elseif (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
    $ad = $_SESSION['Admin'];
    $fn = isset($ad['Fname']) ? trim((string) $ad['Fname']) : '';
    $ln = isset($ad['Lname']) ? trim((string) $ad['Lname']) : '';
    $sales_person_default = trim($fn . ' ' . $ln);
}
if ($sales_person_default === '') {
    $sales_person_default = 'SUPER ADMIN';
}

$sales_person_users = [];
$spu = function_exists('getList')
    ? @getList("SELECT id, Fname, Lname, Username FROM tbl_users WHERE Status = '1' ORDER BY Fname ASC, Lname ASC, Username ASC")
    : null;
if (!is_array($spu) || empty($spu)) {
    $spu = function_exists('getListMaster')
        ? @getListMaster("SELECT id, Fname, Lname, Username FROM tbl_users WHERE Status = '1' ORDER BY Fname ASC, Lname ASC, Username ASC")
        : null;
}
if (is_array($spu)) {
    foreach ($spu as $u) {
        $fn = trim((string) ($u['Fname'] ?? ''));
        $ln = trim((string) ($u['Lname'] ?? ''));
        $disp = trim($fn . ' ' . $ln);
        if ($disp === '') {
            $disp = trim((string) ($u['Username'] ?? ''));
        }
        if ($disp === '') {
            continue;
        }
        $sales_person_users[] = $disp;
    }
    $sales_person_users = array_values(array_unique($sales_person_users));
}
$sp_default_in_list = false;
foreach ($sales_person_users as $_spn) {
    if (strcasecmp($_spn, $sales_person_default) === 0) {
        $sp_default_in_list = true;
        break;
    }
}
if (!$sp_default_in_list && $sales_person_default !== '') {
    $sales_person_users[] = $sales_person_default;
}

$today_ymd = date('Y-m-d');

$carats = getList("SELECT id, name, purity FROM tbl_carat WHERE status = 1 ORDER BY id ASC");
if (!is_array($carats)) {
    $carats = [];
}

$metals = getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ORDER BY id ASC");
if (!is_array($metals)) {
    $metals = [];
}
$bank_accounts_raw = getList("SELECT id, name FROM tbl_customers WHERE sundry_debtors_id = 29 AND status = 1 AND TRIM(IFNULL(name,'')) != '' ORDER BY name ASC");
$bank_accounts = [];
$bank_exclude_names = ['phonepe', 'phonepay', 'gpay', 'google pay', 'paytm', 'upi', '0.00', '0'];
if (is_array($bank_accounts_raw)) {
    foreach ($bank_accounts_raw as $b) {
        $bn = trim(strtolower($b['name'] ?? ''));
        if ($bn === '' || in_array($bn, $bank_exclude_names, true) || preg_match('/^[0-9.]+$/', $bn)) {
            continue;
        }
        $bank_accounts[] = $b;
    }
}
if (count($carats) === 0) {
    $carats = [
        ['id' => 1, 'name' => '18k - Gold', 'purity' => ''],
        ['id' => 2, 'name' => '21k - Gold', 'purity' => ''],
        ['id' => 3, 'name' => '22k - Gold', 'purity' => ''],
        ['id' => 4, 'name' => '24k - Gold', 'purity' => ''],
    ];
}

$if_schemes_table_exists = false;
$if_schemes_initial = [];
$t_isc = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_investment_schemes'");
if ($t_isc && mysqli_num_rows($t_isc) > 0) {
    $if_schemes_table_exists = true;
    $__ifsch = getList('SELECT * FROM tbl_investment_schemes ORDER BY id DESC');
    if (is_array($__ifsch)) {
        foreach ($__ifsch as $__r) {
            $bonus = [];
            if (!empty($__r['bonus_rows'])) {
                $jd = json_decode($__r['bonus_rows'], true);
                if (is_array($jd)) {
                    $bonus = $jd;
                }
            }
            $if_schemes_initial[] = [
                'id' => (string) $__r['id'],
                'scheme_name' => $__r['scheme_name'],
                'redemption_on' => $__r['redemption_on'] ?? '',
                'carat_id' => isset($__r['carat_id']) && $__r['carat_id'] !== null && $__r['carat_id'] !== '' ? (int) $__r['carat_id'] : null,
                'carat_label' => $__r['carat_label'] ?? '',
                'duration_value' => (int) ($__r['duration_value'] ?? 12),
                'duration_unit' => $__r['duration_unit'] ?? 'Month',
                'installment_type' => $__r['installment_type'] ?? '',
                'installment_amt' => (float) ($__r['installment_amt'] ?? 0),
                'minimum_amt_enabled' => !empty($__r['minimum_amt_enabled']),
                'minimum_amt' => (float) ($__r['minimum_amt'] ?? 0),
                'active' => !isset($__r['active']) || (int) $__r['active'] === 1,
                'bonus_rows' => $bonus,
            ];
        }
    }
}

$emi_voucher_company_legal = isset($Proj_Title) ? trim((string) $Proj_Title) : 'Aura Gold';
if (defined('COMPANY_LEGAL_NAME') && is_string(COMPANY_LEGAL_NAME) && trim(COMPANY_LEGAL_NAME) !== '') {
    $emi_voucher_company_legal = trim(COMPANY_LEGAL_NAME);
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Investment / Layaways Fund - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php'; ?>
</head>
<style>
    body {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        overflow-y: hidden;
        height: 100vh;
        background: linear-gradient(135deg, #f5f7fa 0%, #eeeeee 100%);
        font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    html { height: 100vh; overflow: hidden; }
    .layout-wrapper { height: 100vh; overflow: hidden; }
    .layout-content {
        height: calc(100vh - 60px);
        overflow-y: auto;
        margin: 0 !important;
        padding: 0 !important;
    }
    .layout-container { margin-left: 260px; }
    @media (max-width: 991.98px) {
        .layout-container { margin-left: 0; }
    }
    .if-card {
        border-radius: 12px;
        border: 1px solid #e6e8f0;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: 12px;
    }
    .if-card .card-body { padding: 10px 12px; }
    .if-header {
        background: #11294b;
        color: #fff;
        border: none;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .if-header .card-body { padding: 8px 14px; }
    .billing-form label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 4px;
        display: block;
    }
    .billing-form .form-control-sm,
    .billing-form select.form-control-sm {
        height: 34px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        font-size: 13px;
    }
    .billing-form textarea.form-control-sm {
        height: auto;
        min-height: 56px;
        resize: vertical;
    }
    /* Installment entry form: label above field (stacked), full width */
    #ifInstallmentFormView .if-installment-entry-form .form-group {
        display: block;
        margin-bottom: 0.75rem;
    }
    #ifInstallmentFormView .if-installment-entry-form .form-group label {
        display: block;
        width: 100%;
        float: none;
        margin-bottom: 6px;
        padding-right: 0;
    }
    #ifInstallmentFormView .if-installment-entry-form .form-control-sm,
    #ifInstallmentFormView .if-installment-entry-form select.form-control-sm,
    #ifInstallmentFormView .if-installment-entry-form textarea.form-control-sm {
        width: 100%;
        max-width: 100%;
        display: block;
    }
    #ifInstallmentFormView .if-installment-entry-form .position-relative .form-control-sm {
        width: 100%;
    }
    #ifInstallmentFormView .if-installment-entry-form input.form-control-sm[readonly],
    #ifInstallmentFormView .if-installment-entry-form textarea.form-control-sm[readonly] {
        background-color: #f1f5f9;
        color: #334155;
        cursor: default;
    }
    #ifInstallmentFormView .if-installment-entry-form select.form-control-sm:disabled {
        background-color: #f1f5f9;
        color: #334155;
        cursor: default;
        opacity: 1;
    }
    .if-subtabs .nav-link {
        background: #f3f4f9;
        border-radius: 8px;
        margin-right: 8px;
        color: #5c5c7a !important;
        font-weight: 600;
        padding: 8px 16px;
        border: none;
    }
    .if-subtabs .nav-link.active {
        background: #7b6cff;
        color: #fff !important;
    }
    .if-subtabs-toolbar {
        border-bottom: 1px solid #dee2e6;
        gap: 8px 12px;
    }
    .if-subtabs-toolbar .if-subtabs {
        border-bottom: none !important;
        margin-bottom: 0 !important;
        flex: 1 1 auto;
        min-width: 0;
    }
    .if-subtabs-toolbar .if-subtabs .nav-item {
        margin-bottom: 0;
    }
    .if-subtabs-actions {
        gap: 8px;
    }
    .if-subtabs-action-btn {
        border-color: #11294b !important;
        color: #11294b !important;
        background: #fff !important;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .if-subtabs-action-btn:hover:not(:disabled) {
        background: #11294b !important;
        color: #fff !important;
    }
    .if-subtabs-action-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    /* Fund Withdraw modal — navy & gold (Jewelsteps-style) */
    #fundWithdrawModal .if-fw-modal-content {
        border: 2px solid #c5a864;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(15, 40, 75, 0.18);
    }
    #fundWithdrawModal .if-fw-modal-header {
        background: linear-gradient(180deg, #153a5c 0%, #0f2848 100%);
        border-bottom: 3px solid #c5a864;
        padding: 14px 18px;
        position: relative;
    }
    #fundWithdrawModal .if-fw-modal-header .modal-title {
        font-weight: 700;
        color: #fff;
        font-size: 1.05rem;
        letter-spacing: 0.03em;
    }
    #fundWithdrawModal .if-fw-modal-header .close {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.5rem;
        font-weight: 400;
        color: #f5e6c8;
        opacity: 0.95;
        text-shadow: none;
    }
    #fundWithdrawModal .if-fw-modal-header .close:hover {
        color: #fff;
    }
    #fundWithdrawModal .if-fw-modal-body {
        padding: 16px 18px 18px;
        max-height: calc(100vh - 140px);
        overflow-y: auto;
        background: #fff;
    }
    #fundWithdrawModal .if-fw-customer-line {
        font-size: 0.95rem;
        margin-bottom: 14px;
    }
    #fundWithdrawModal .if-fw-customer-line strong {
        color: #db2777;
        font-weight: 700;
    }
    #fundWithdrawModal .if-fw-grid-scroll {
        border: 1px solid #d4c4a8;
        border-radius: 8px;
        overflow: auto;
        min-height: 240px;
        max-height: 320px;
        background: #fff;
    }
    #fundWithdrawModal .if-fw-pay-table thead th {
        background: #0f2848 !important;
        color: #f5e6c8 !important;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        border-color: #1a3d5c !important;
        white-space: nowrap;
        padding: 8px 6px;
    }
    #fundWithdrawModal .if-fw-pay-table tbody td {
        padding: 4px 6px;
        vertical-align: middle;
        border-color: #e8e0d0;
        font-size: 0.78rem;
    }
    #fundWithdrawModal .if-fw-pay-table tfoot td {
        background: #f8f4eb;
        font-weight: 700;
        color: #0f2848;
        border-color: #d4c4a8;
    }
    #fundWithdrawModal .if-fw-pay-icons {
        gap: 10px;
        margin-bottom: 12px;
    }
    #fundWithdrawModal .if-fw-pay-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: 2px solid #c5a864;
        background: #11294b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        cursor: pointer;
        transition: box-shadow 0.15s ease, border-color 0.15s ease, transform 0.12s ease;
    }
    #fundWithdrawModal .if-fw-pay-icon:hover {
        border-color: #e8c76b;
        transform: translateY(-1px);
    }
    #fundWithdrawModal .if-fw-pay-icon.if-fw-pay-icon--active {
        box-shadow: 0 0 0 3px #c5a864;
        border-color: #f0d78c;
        background: #153a5c;
    }
    #fundWithdrawModal .if-fw-pay-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        pointer-events: none;
    }
    #fundWithdrawModal .if-fw-modal-footer {
        border-top: 1px solid #e8e0d0;
        background: #faf8f4;
    }
    #fundWithdrawModal .if-fw-btn-close {
        border-color: #0f2848 !important;
        color: #0f2848 !important;
        background: #fff !important;
        font-weight: 600;
    }
    #fundWithdrawModal .if-fw-btn-close:hover {
        background: #f8f4eb !important;
        border-color: #c5a864 !important;
        color: #a67c2a !important;
    }
    #fundWithdrawModal .if-fw-btn-save {
        background: #0f2848 !important;
        border-color: #0f2848 !important;
        color: #fff !important;
        font-weight: 600;
    }
    #fundWithdrawModal .if-fw-btn-save:hover {
        background: #153a5c !important;
        border-color: #c5a864 !important;
    }
    #fundWithdrawModal .if-fw-btn-add {
        border-color: #c5a864 !important;
        color: #a67c2a !important;
        background: #fff !important;
        font-weight: 700;
    }
    #fundWithdrawModal .if-fw-btn-add:hover {
        background: #fff9e6 !important;
        color: #0f2848 !important;
    }
    #fundWithdrawModal .if-fw-toolbar-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        margin-bottom: 8px;
    }
    #fundWithdrawModal .if-fw-toolbar-row .btn-link {
        color: #a67c2a !important;
    }
    #fundWithdrawModal .if-fw-readonly-field {
        background: #f8f6f2 !important;
        color: #0f2848;
        font-weight: 600;
    }
    #fundWithdrawModal #fwTotalPaidAmt {
        color: #11294b !important;
        font-weight: 700;
        background: #f1f5f9 !important;
    }
    /* Fund Transfer modal */
    #fundTransferModal .if-ft-modal-content {
        border: 2px solid #c4b5fd;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(91, 33, 182, 0.12);
    }
    #fundTransferModal .if-ft-modal-header {
        background: linear-gradient(180deg, #faf5ff 0%, #f3e8ff 100%);
        border-bottom: 2px solid #c4b5fd;
        padding: 14px 18px;
    }
    #fundTransferModal .if-ft-modal-header .modal-title {
        font-weight: 700;
        color: #5b21b6;
        font-size: 1.05rem;
    }
    #fundTransferModal .if-ft-modal-body {
        padding: 18px 20px 22px;
        background: #fff;
    }
    #fundTransferModal .if-ft-customer-line strong {
        color: #db2777;
        font-weight: 700;
    }
    #fundTransferModal .if-ft-section-bar {
        background: #ede9fe;
        color: #5b21b6;
        font-weight: 700;
        font-size: 0.82rem;
        padding: 8px 14px;
        border-radius: 6px;
        margin-bottom: 12px;
        border: 1px solid #ddd6fe;
    }
    #fundTransferModal .if-ft-section-bar.if-ft-section-bar--total {
        margin-top: 18px;
        margin-bottom: 16px;
    }
    #fundTransferModal .if-ft-formula-block {
        background: #fafaff;
        border: 1px solid #e9d5ff;
        border-radius: 10px;
        padding: 16px 14px;
    }
    #fundTransferModal .if-ft-formula-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 10px 14px;
        text-align: center;
    }
    #fundTransferModal .if-ft-formula-row + .if-ft-formula-row {
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px dashed #ddd6fe;
    }
    #fundTransferModal .if-ft-formula-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
    }
    #fundTransferModal .if-ft-big-val {
        font-size: 1.2rem;
        font-weight: 800;
        color: #4c1d95;
        min-width: 2.5em;
    }
    #fundTransferModal .if-ft-op {
        font-size: 1.1rem;
        font-weight: 700;
        color: #7c3aed;
    }
    #fundTransferModal .if-ft-modal-footer {
        border-top: 1px solid #ede9fe;
        background: #fafafa;
    }
    #fundTransferModal .if-ft-btn-close {
        border-color: #7b6cff !important;
        color: #6d28d9 !important;
        background: #fff !important;
        font-weight: 600;
    }
    #fundTransferModal .if-ft-btn-close:hover {
        background: #f5f3ff !important;
    }
    #fundTransferModal .if-ft-btn-save {
        background: #7b6cff !important;
        border-color: #7b6cff !important;
        font-weight: 600;
    }
    #fundTransferModal .if-ft-btn-save:hover {
        background: #6d5ce8 !important;
        border-color: #6d5ce8 !important;
    }
    .if-list-panel .btn {
        border-radius: 8px;
        font-weight: 600;
    }
    .if-list-panel .btn-toolbar-gap .btn { margin-right: 4px; margin-bottom: 6px; }
    .if-list-panel label.small { color: #000 !important; font-weight: 600; }
    .if-btn-scheme {
        border-color: #c5a864 !important;
        color: #c5a864 !important;
        background: #fff !important;
        font-weight: 600;
    }
    .if-btn-scheme:hover {
        background: #fff9e6 !important;
        color: #a68a4a !important;
    }
    .if-installment-table thead th {
        background: #11294b !important;
        color: #fff !important;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        padding: 4px 6px;
        line-height: 1.1;
        border: 1px solid #0d1f38 !important;
        text-align: center;
    }
    .if-installment-table tbody td {
        padding: 2px 6px;
        font-size: 11px;
        line-height: 1.2;
        vertical-align: middle;
        border-color: #e6e8f0;
        color: #4c4768;
    }
    .if-installment-table tbody tr:nth-child(even) td {
        background: #f8f9ff;
    }
    .if-installment-table .form-control-sm {
        height: 30px;
        font-size: 13px;
        padding: 0.25rem 0.45rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }
    .if-installment-summary-bar .form-control-sm[readonly] {
        background: #f9fafb;
        color: #374151;
        cursor: default;
    }
    .toggle-view .btn {
        font-size: 0.72rem;
        padding: 0.25rem 0.6rem;
    }
    .toggle-view .btn-primary {
        background: #11294b;
        border-color: #11294b;
    }
    .if-add-row-btn {
        border-color: #c5a864 !important;
        color: #c5a864 !important;
        background: #fff !important;
        font-weight: 600;
    }
    .if-add-row-btn:hover {
        background: #fff9e6 !important;
        color: #a68a4a !important;
    }
    .add-customer-icon {
        transition: all 0.2s ease;
        color: #11294b !important;
    }
    .add-customer-icon:hover {
        color: #c5a864 !important;
        transform: translateY(-50%) scale(1.1) !important;
    }
    /* Right-side customer modal (sale-invoice) */
    .modal.fade.right .modal-dialog {
        transform: translateX(100%);
        transition: transform 0.3s ease-out;
    }
    .modal.fade.right.show .modal-dialog { transform: translateX(0); }
    .modal-dialog-right {
        position: fixed;
        right: 0;
        top: 0;
        margin: 0;
        height: 100vh;
    }
    .modal.fade.right { padding-right: 0 !important; }
    .modal.fade.right .modal-backdrop { background-color: rgba(0, 0, 0, 0.5); }
    #customerSuggestions { font-family: inherit; }
    #customerCreationModal .form-group { margin-bottom: 0.75rem; }
    #customerCreationModal .form-group label {
        font-weight: 500;
        color: #334155;
        margin-bottom: 0.25rem;
        font-size: 0.8rem;
        display: block;
    }
    #customerCreationModal .form-control {
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 0.4rem 0.75rem;
        font-size: 0.85rem;
        height: 32px;
        line-height: 1.5;
    }
    #customerCreationModal .form-control:focus {
        border-color: #c5a864;
        box-shadow: 0 0 0 0.2rem rgba(17, 41, 75, 0.15);
        outline: none;
    }
    #customerCreationModal .nav-tabs .nav-link:hover {
        color: #11294b;
    }
    #customerCreationModal .item-tax-table thead {
        background: #11294b;
        color: #fff;
    }
    /* Create Scheme modal — keep header + Save visible (tall content was clipping top bar) */
    #createSchemeModal {
        padding-top: 56px;
        padding-bottom: 12px;
    }
    #createSchemeModal .modal-dialog {
        max-width: 96%;
        width: 96%;
        margin: 0.75rem auto;
        max-height: calc(100vh - 72px);
    }
    #createSchemeModal .modal-content {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        max-height: calc(100vh - 72px);
        display: flex;
        flex-direction: column;
    }
    #createSchemeModal .cs-modal-header {
        background: #11294b;
        color: #fff;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        flex-shrink: 0;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    #createSchemeModal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
    }
    #createSchemeModal .cs-scheme-form-actions {
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }
    #createSchemeModal .cs-scheme-form-actions .btn-cs-navy {
        min-width: 110px;
    }
    #createSchemeModal .cs-modal-header h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
    }
    #createSchemeModal .cs-form-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #000;
        margin-bottom: 8px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
    #createSchemeModal .cs-scheme-details-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 18px 18px 20px;
    }
    #createSchemeModal .cs-scheme-details-card .cs-section-title {
        margin-bottom: 18px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e2e8f0;
    }
    #createSchemeModal .cs-scheme-form .form-group {
        margin-bottom: 0;
    }
    #createSchemeModal .cs-scheme-form .cs-form-field-gap {
        margin-bottom: 1.35rem;
    }
    #createSchemeModal .cs-scheme-form .cs-form-field-gap:last-of-type {
        margin-bottom: 0;
    }
    #createSchemeModal .cs-scheme-form-checks {
        margin-top: 0.5rem;
        padding-top: 1.25rem;
        border-top: 1px solid #f1f5f9;
    }
    #createSchemeModal .cs-scheme-form-checks .custom-control-label {
        color: #000;
        font-size: 0.78rem;
        font-weight: 600;
    }
    #createSchemeModal .form-control,
    #createSchemeModal select.form-control {
        font-size: 0.85rem;
        color: #000;
        border-color: #e2e8f0;
        border-radius: 6px;
    }
    #createSchemeModal .cs-section-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: #11294b;
        margin-bottom: 10px;
        text-transform: uppercase;
    }
    #createSchemeModal .cs-table-list thead th {
        background: #11294b;
        color: #fff;
        font-size: 0.68rem;
        font-weight: 600;
        padding: 8px 6px;
        white-space: nowrap;
        border: 1px solid #0d1f38;
    }
    #createSchemeModal .btn-cs-new {
        border: 1px solid #ec4899 !important;
        color: #db2777 !important;
        background: #fff !important;
        font-weight: 600;
        font-size: 0.75rem;
    }
    #createSchemeModal .btn-cs-navy {
        background: #11294b !important;
        border-color: #11294b !important;
        color: #fff !important;
        font-weight: 600;
        font-size: 0.75rem;
    }
    #createSchemeModal .btn-cs-navy-outline {
        border: 1px solid #11294b !important;
        color: #11294b !important;
        background: #fff !important;
        font-weight: 600;
        font-size: 0.75rem;
    }
    #createSchemeModal .cs-pagination {
        font-size: 0.72rem;
        color: #000;
    }
    #createSchemeModal .cs-scheme-list-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px;
    }
    #createSchemeModal .cs-scheme-list-card .cs-section-title {
        margin-bottom: 14px;
        padding-bottom: 0;
        border-bottom: none;
    }
    @media (min-width: 992px) {
        #createSchemeModal .border-right-lg {
            border-right: 1px solid #e2e8f0 !important;
        }
    }
    /* Left fund record list (card rows) */
    #investmentListScroll {
        min-height: 420px;
        max-height: calc(100vh - 240px);
        overflow-y: auto;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    .if-fund-card {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
        background: #fff;
        transition: background 0.15s ease;
        font-size: 0.78rem;
    }
    .if-fund-card:hover {
        background: #f1f5f9;
    }
    .if-fund-card.if-fund-card--active {
        background: #e0f2fe;
        border-left: 3px solid #0ea5e9;
    }
    .if-fund-card__name {
        color: #2563eb;
        font-weight: 600;
        font-size: 0.82rem;
        line-height: 1.25;
    }
    .if-fund-card__no {
        color: #64748b;
        font-size: 0.72rem;
        margin-top: 2px;
    }
    .if-fund-card__right {
        text-align: right;
        min-width: 0;
    }
    .if-fund-card__scheme {
        color: #0f172a;
        font-weight: 600;
        font-size: 0.78rem;
    }
    .if-fund-card__meta {
        color: #475569;
        font-size: 0.7rem;
        margin-top: 3px;
        white-space: nowrap;
    }
    .if-list-footer {
        font-size: 0.72rem;
        color: #000;
    }
    /* Master list: Party | Scheme Name */
    .if-fund-master-table {
        font-size: 0.75rem;
        margin-bottom: 0;
        background: #fff;
    }
    .if-fund-master-table thead th {
        background: #11294b !important;
        color: #fff !important;
        font-weight: 700;
        font-size: 0.7rem;
        padding: 6px 8px;
        border: 1px solid #0d1f38 !important;
        vertical-align: middle;
    }
    .if-fund-master-table .if-fund-search-row td {
        padding: 4px 6px;
        background: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        color: #334155;
    }
    .if-fund-master-table .if-fund-search-row input {
        font-size: 0.72rem;
    }
    .if-fund-master-table tbody td {
        padding: 8px;
        vertical-align: top;
        border: 1px solid #e2e8f0;
        background: #fff;
    }
    .if-fund-master-table tbody tr.if-fund-row {
        cursor: pointer;
    }
    .if-fund-master-table tbody tr.if-fund-row:hover td {
        background: #f8fafc;
    }
    .if-fund-master-table tbody tr.if-fund-row--active td {
        background: #e0f2fe !important;
    }
    .if-fund-row .if-party-name {
        color: #2563eb;
        font-weight: 600;
        font-size: 0.8rem;
    }
    .if-fund-row .if-party-no {
        color: #64748b;
        font-size: 0.7rem;
        margin-top: 2px;
    }
    .if-fund-row .if-scheme-title {
        color: #0f172a;
        font-weight: 600;
        font-size: 0.78rem;
    }
    .if-fund-row .if-scheme-meta {
        color: #475569;
        font-size: 0.7rem;
        margin-top: 3px;
    }
    /* Read-only detail panel — profile left, details grid right (Jewelsteps-style) */
    .if-detail-card {
        padding: 0;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .if-detail-card-inner {
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
    }
    .if-detail-profile {
        padding: 16px 20px;
        border-bottom: none;
        border-right: 1px solid #e6e8f0;
        display: flex;
        align-items: center;
        gap: 14px;
        flex: 0 0 auto;
        min-width: 200px;
        max-width: 100%;
    }
    .if-detail-grid-wrap {
        flex: 1 1 280px;
        min-width: 0;
    }
    @media (max-width: 991.98px) {
        .if-detail-profile {
            border-right: none;
            border-bottom: 1px solid #e6e8f0;
            width: 100%;
        }
    }
    .if-detail-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(145deg,#3b82f6,#1d4ed8);
        display:flex;
        align-items:center;
        justify-content:center;
        color:#fff;
        font-size:22px;
    }
    .if-detail-name {
        font-size: 15px;
        font-weight: 700;
        color: #11294b;
        margin-bottom: 2px;
    }
    .if-detail-sub {
        font-size: 0.8rem;
        color: #475569;
        margin-bottom: 2px;
    }
    .if-detail-phone {
        font-size: 0.8rem;
        color: #334155;
    }
    .if-detail-phone i {
        font-size: 0.85rem;
        vertical-align: middle;
        margin-right: 6px;
        color: #22c55e;
    }
    .if-detail-ft-badge {
        display: inline-block;
        margin-top: 8px;
        padding: 3px 10px;
        font-size: 0.72rem;
        font-weight: 700;
        color: #15803d;
        border: 1px solid #22c55e;
        border-radius: 4px;
        background: #f0fdf4;
        letter-spacing: 0.02em;
    }
    .if-detail-ft-badge.d-none {
        display: none !important;
    }
    .if-detail-grid {
        padding: 10px 18px 6px;
    }
    .if-detail-grid .if-dg-item {
        margin-bottom: 6px;
    }
    .if-detail-grid .if-dg-label {
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .if-detail-grid .if-dg-value {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
    }
    .if-detail-grid .row {
        margin-left: -5px;
        margin-right: -5px;
    }
    .if-detail-grid [class*="col-"] {
        padding-left: 5px;
        padding-right: 5px;
    }
    .if-detail-grid .if-dg-value.if-dg-empty {
        color: #94a3b8;
        font-weight: 400;
    }
    .if-detail-inst-section {
        padding: 12px 18px 16px;
        border-top: 1px solid #e6e8f0;
    }
    .if-detail-inst-heading-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px 14px;
        background: #fff;
        padding: 10px 14px;
        margin-bottom: 10px;
        border-radius: 6px;
        border: 1px solid #e6e8f0;
        text-align: left;
    }
    .if-detail-inst-heading-title {
        font-weight: 700;
        font-size: 0.9rem;
        color: #3d3566 !important;
        margin: 0;
        line-height: 1.3;
        flex: 1 1 auto;
        min-width: 0;
    }
    .if-detail-inst-heading-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
        flex: 0 0 auto;
    }
    .if-detail-inst-heading-bar .if-detail-more-btn {
        color: #7b6cff !important;
        font-weight: 600;
        text-decoration: none;
    }
    .if-detail-inst-heading-bar .if-detail-more-btn:hover {
        color: #5b4cdb !important;
        text-decoration: underline;
    }
    .if-detail-inst-heading-bar .toggle-view.btn-group {
        border-radius: 999px;
        overflow: hidden;
        border: 1px solid #7b6cff;
        box-shadow: none;
    }
    .if-detail-inst-heading-bar .toggle-view .btn {
        font-size: 0.72rem;
        padding: 0.35rem 0.85rem;
        font-weight: 600;
        margin: 0 !important;
        border-radius: 0 !important;
    }
    .if-detail-inst-heading-bar .toggle-view .btn:first-child {
        border-radius: 999px 0 0 999px !important;
    }
    .if-detail-inst-heading-bar .toggle-view .btn:last-child {
        border-radius: 0 999px 999px 0 !important;
    }
    .if-detail-inst-heading-bar .toggle-view .btn-primary {
        background: #7b6cff !important;
        color: #fff !important;
        border-color: #7b6cff !important;
    }
    .if-detail-inst-heading-bar .toggle-view .btn-outline-secondary {
        background: #fff !important;
        color: #7b6cff !important;
        border-color: #7b6cff !important;
    }
    .if-detail-inst-heading-bar .toggle-view .btn-outline-secondary:hover {
        background: #f5f3ff !important;
        color: #5b4cdb !important;
        border-color: #7b6cff !important;
    }
    .if-detail-inst-heading-bar--form {
        margin-bottom: 0;
        border-radius: 6px 6px 0 0;
        border-bottom: 1px solid #e6e8f0;
    }
    .if-form-installment-card {
        overflow: hidden;
    }
    .if-detail-inst-section .table-responsive {
        margin-top: 0;
    }
    .if-detail-inst-table {
        border-color: #e6e8f0;
        font-size: 11px;
    }
    .if-detail-inst-table thead th {
        background: #f3f4f6 !important;
        color: #374151 !important;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 6px;
        line-height: 1.1;
        border: 1px solid #e6e8f0 !important;
        white-space: nowrap;
        text-align: center;
    }
    .if-detail-inst-table.if-passbook-mode thead th {
        background: #f3f4f6 !important;
        color: #374151 !important;
        border-color: #e6e8f0 !important;
        text-align: center;
    }
    .if-detail-inst-table tbody td {
        font-size: 11px;
        padding: 2px 6px;
        line-height: 1.2;
        vertical-align: middle;
        border: 1px solid #e6e8f0;
        color: #4c4768;
        font-weight: 500;
    }
    .if-detail-inst-table tbody tr {
        height: 26px;
    }
    .if-detail-inst-table tbody tr:nth-child(even) td {
        background: #f8f9ff;
    }
    .if-detail-inst-table tbody td:nth-child(5),
    .if-detail-inst-table tbody td:nth-child(6),
    .if-detail-inst-table tbody td:nth-child(7),
    .if-detail-inst-table tbody td:nth-child(9),
    .if-detail-inst-table tbody td:nth-child(10),
    .if-detail-inst-table tbody td:nth-child(11) {
        text-align: right;
    }
    .if-detail-inst-table tbody td:nth-child(1) {
        text-align: center;
    }
    .if-detail-inst-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 20px;
        padding: 10px 14px;
        margin-top: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
    }
    .if-detail-inst-footer .if-dif-item {
        font-size: 0.72rem;
    }
    .if-detail-inst-footer .if-dif-label {
        color: #000;
        font-weight: 600;
        margin-right: 6px;
    }
    .if-detail-inst-footer .if-dif-val {
        color: #11294b;
        font-weight: 700;
    }
    .if-detail-inst-footer-stats {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 20px;
        flex: 1 1 auto;
        min-width: 0;
    }
    .if-detail-inst-attach {
        flex: 0 0 auto;
        gap: 6px;
    }
    .if-detail-attach-btn {
        border-color: #11294b !important;
        color: #11294b !important;
        background: #fff !important;
        font-weight: 600;
        font-size: 0.72rem;
    }
    .if-detail-attach-btn:hover {
        background: #11294b !important;
        color: #fff !important;
    }
    .if-action-btns {
        white-space: nowrap;
    }
    /* Detail installment table: gold outline icons, no button box (Normal + Passbook) */
    .if-detail-inst-table .if-action-btns .btn-icon {
        width: auto;
        height: auto;
        min-width: 0;
        padding: 2px 5px;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        margin: 0;
        color: #b8984d !important;
        border-radius: 4px;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }
    .if-detail-inst-table .if-action-btns .btn-icon i {
        font-size: 15px !important;
        width: 15px !important;
        height: 15px !important;
        line-height: 15px !important;
        display: inline-block;
        vertical-align: middle;
    }
    .if-detail-inst-table td.if-detail-normal-actions .if-detail-action-cell .btn-icon i {
        font-size: 14px !important;
        width: 10px !important;
        height: 8px !important;
        line-height: 8px !important;
    }
    .if-detail-inst-table .if-action-btns .btn-icon:hover {
        background: rgba(197, 168, 100, 0.2) !important;
        color: #9a7b3d !important;
    }
    .if-detail-inst-table .if-action-btns .btn-icon.btn-icon-danger {
        color: #b8984d !important;
    }
    .if-detail-inst-table .if-action-btns .btn-icon.btn-icon-danger:hover {
        background: rgba(220, 38, 38, 0.12) !important;
        color: #dc2626 !important;
    }
    .if-detail-inst-table .if-action-btns .btn-icon:disabled,
    .if-detail-inst-table .if-action-btns .btn-icon[disabled] {
        opacity: 0.38 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
        color: #94a3b8 !important;
    }
    .if-detail-inst-table tbody td.if-detail-normal-actions {
        text-align: center;
    }
    .if-detail-action-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    #investmentReceiptModal .modal-header {
        border-bottom: 1px solid #e2e8f0;
        padding: 10px 16px;
    }
    #investmentReceiptModal .modal-title {
        font-weight: 700;
        color: #11294b;
        width: 100%;
        text-align: center;
    }
    #investmentReceiptModal .if-rec-label {
        font-size: 0.68rem;
        font-weight: 600;
        color: #000;
        margin-bottom: 2px;
        text-transform: uppercase;
    }
    #investmentReceiptModal .form-control-sm {
        font-size: 0.78rem;
    }
    #investmentReceiptModal .if-rec-month-multiselect .dropdown-menu {
        z-index: 1060;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    #investmentReceiptModal .if-rec-month-multiselect #ifRecInstMonthBtn {
        cursor: pointer;
        background: #fff;
        border: 1px solid #cbd5e1;
    }
    #investmentReceiptModal .if-rec-month-opt {
        cursor: pointer;
        user-select: none;
        color: #0f172a;
    }
    #investmentReceiptModal .if-rec-month-opt:hover {
        background: #f1f5f9;
    }
    #investmentReceiptModal #ifRecInstMonthEmpty {
        font-size: 0.72rem;
        color: #64748b;
    }
    #investmentReceiptModal .if-rec-pay-icons.payment-icons {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        margin: 12px 0;
    }
    #investmentReceiptModal .if-rec-pay-icons .payment-icon {
        width: 45px;
        height: 45px;
        border: 1.5px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        position: relative;
        overflow: hidden;
        padding: 0;
        flex-shrink: 0;
    }
    #investmentReceiptModal .if-rec-pay-icons .payment-icon img {
        width: 45px;
        height: 45px;
        object-fit: contain;
    }
    #investmentReceiptModal .if-rec-pay-icons .payment-cash:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-bank:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-cheque:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-mobile:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-card:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-exchange:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-jewelry:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-diamond:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-stone:hover,
    #investmentReceiptModal .if-rec-pay-icons .payment-other:hover {
        background: #11294b;
        border-color: #c5a864;
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 4px 12px rgba(197, 168, 100, 0.45);
    }
    #investmentReceiptModal .if-rec-pay-icons .payment-icon.active {
        background: #11294b !important;
        border-color: #c5a864 !important;
        border-width: 3px;
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }
    .if-rec-inner-table thead th {
        background: #11294b;
        color: #fff;
        font-size: 0.65rem;
        padding: 6px 4px;
    }
    .if-detail-more-btn {
        text-decoration: none !important;
        white-space: nowrap;
    }
    .if-detail-more-btn:hover {
        color: #0d1f38 !important;
    }
    .if-btn-more-row {
        font-size: 0.65rem;
        font-weight: 700;
        color: #11294b;
        padding: 2px 6px;
        border: none;
        background: transparent;
        text-decoration: underline;
        cursor: pointer;
    }
    .if-btn-more-row:hover {
        color: #0d1f38;
    }
    /* Installment Report tab (split layout) */
    .if-report-split {
        min-height: 360px;
    }
    .if-report-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }
    .if-report-pill-title {
        background: #7b6cff;
        color: #fff !important;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 6px;
        letter-spacing: 0.02em;
    }
    .if-report-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .if-report-icon-btn {
        padding: 4px 10px;
        line-height: 1.2;
        border-radius: 6px;
        color: #5c5c7a !important;
        border-color: #e6e8f0 !important;
    }
    .if-report-icon-btn:hover {
        background: #f3f4f9 !important;
        color: #7b6cff !important;
        border-color: #d4d2e8 !important;
    }
    .if-report-filter-btn {
        position: relative;
    }
    .if-report-filter-btn .if-report-filter-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        font-size: 0.6rem;
        padding: 2px 5px;
    }
    .if-report-party-card .table-responsive {
        max-height: 420px;
        overflow-y: auto;
    }
    .if-report-party-table thead th {
        font-size: 11px;
        font-weight: 600;
        color: #374151;
        background: #f3f4f6 !important;
        border-color: #e6e8f0 !important;
        padding: 6px 8px;
        white-space: nowrap;
    }
    .if-report-party-table td {
        font-size: 12px;
        padding: 6px 8px;
        vertical-align: middle;
        border-color: #e6e8f0;
    }
    .if-report-party-search td {
        padding: 6px 8px;
        background: #fafafa;
    }
    .if-report-party-row:hover td {
        background: #f8f9ff;
    }
    .if-report-party-row--active td {
        background: #ede9fe !important;
        font-weight: 600;
        color: #5b4cdb;
    }
    .if-report-main-scroll {
        overflow-x: auto;
        max-height: 520px;
        overflow-y: auto;
    }
    .if-report-main-table thead th {
        font-size: 11px;
        font-weight: 600;
        color: #374151;
        background: #f3f4f6 !important;
        border-color: #e6e8f0 !important;
        padding: 6px 8px;
        white-space: nowrap;
    }
    .if-report-main-table tbody td {
        font-size: 12px;
        padding: 6px 8px;
        border-color: #e6e8f0;
        color: #334155;
        vertical-align: middle;
    }
    .if-report-main-table tbody tr[data-fund-id] {
        cursor: pointer;
    }
    .if-report-main-table tbody tr[data-fund-id]:hover {
        background: #f8f9ff;
    }
    .if-report-main-table tbody tr[data-fund-id].if-report-row--selected {
        background: #eef2ff !important;
        outline: 1px solid #c7d2fe;
        outline-offset: -1px;
    }
    .if-report-main-table td.if-report-link-cell {
        color: #4b49ac;
        font-weight: 600;
    }
    .if-report-main-table td.if-report-link-cell:hover {
        text-decoration: underline;
    }
    /* Installment Report: customer + scheme header (Jewelsteps-style) */
    .if-report-detail-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }
    .if-report-detail-inner {
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
    }
    .if-report-detail-profile {
        padding: 18px 22px;
        border-right: 1px solid #e6e8f0;
        align-items: center;
        gap: 16px;
        flex: 0 0 auto;
        min-width: 220px;
        max-width: 100%;
    }
    .if-report-detail-avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: linear-gradient(145deg, #3b82f6 0%, #1d4ed8 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #fff;
        font-size: 2rem;
    }
    .if-report-detail-name {
        font-size: 1.05rem;
        font-weight: 700;
        color: #4b49ac;
        margin-bottom: 4px;
    }
    .if-report-detail-sub {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 2px;
    }
    .if-report-detail-phone {
        font-size: 0.8rem;
        color: #334155;
    }
    .if-report-detail-phone i {
        font-size: 0.85rem;
        vertical-align: middle;
        margin-right: 6px;
        color: #22c55e;
    }
    .if-report-detail-grid-wrap {
        flex: 1 1 280px;
        min-width: 0;
    }
    .if-report-detail-grid {
        padding: 14px 18px 10px;
    }
    .if-report-detail-grid .if-report-dg-item {
        margin-bottom: 10px;
    }
    .if-report-detail-grid .if-report-dg-label {
        font-size: 0.68rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin-bottom: 2px;
    }
    .if-report-detail-grid .if-report-dg-value {
        font-size: 0.82rem;
        font-weight: 700;
        color: #4b49ac;
        word-break: break-word;
    }
    .if-report-detail-grid .if-report-dg-value.if-report-dg-empty {
        color: #94a3b8;
        font-weight: 400;
    }
    .if-report-detail-card-footer {
        border-top: 1px solid #f1f5f9;
        padding-top: 10px !important;
        margin-top: 0;
    }
    @media (max-width: 991.98px) {
        .if-report-detail-profile {
            border-right: none;
            border-bottom: 1px solid #e6e8f0;
            width: 100%;
        }
    }
    .if-report-party-footer,
    .if-report-main-footer {
        font-size: 0.72rem;
        color: #64748b;
    }
    /* Installment Report tab: full-width report only (hide fund list + header + detail actions) */
    body.if-installment-report-active .if-list-panel,
    body.if-installment-report-active #ifInvestmentFundHeader {
        display: none !important;
    }
    body.if-installment-report-active #ifMainContentColumn {
        flex: 0 0 100%;
        max-width: 100%;
        padding-left: 0 !important;
    }
    body.if-installment-report-active #ifSubtabDetailActions {
        display: none !important;
    }
    body.if-installment-report-active .if-report-toolbar {
        justify-content: flex-end;
        border-bottom: none;
        margin-bottom: 8px;
    }

    /* Print bill confirmation (Jewelsteps-style) */
    .if-print-bill-modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
        position: relative;
        padding-top: 8px;
    }
    .if-print-bill-close {
        position: absolute;
        right: 10px;
        top: 6px;
        z-index: 2;
        opacity: 0.45;
        font-size: 1.4rem;
    }
    .if-print-bill-icon-wrap {
        font-size: 2.5rem;
        line-height: 1;
    }
    .if-print-bill-title {
        color: #1e6fd9;
        font-size: 1.35rem;
    }
    .if-print-bill-yes {
        color: #1e6fd9 !important;
        font-size: 1rem;
    }
    .if-print-bill-no {
        color: #c94b9d !important;
        font-size: 1rem;
    }

</style>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
            <div class="app-brand demo">
                <span class="app-brand-logo demo">
                    <img src="assets/img/logo.png" alt="Brand Logo" class="img-fluid">
                </span>
                <a href="index.php" class="app-brand-text demo sidenav-text font-weight-normal ml-2">AuraGold</a>
                <a href="javascript:" class="layout-sidenav-toggle sidenav-link text-large ml-auto">
                    <i class="ion ion-md-menu align-middle"></i>
                </a>
            </div>
            <div class="sidenav-divider mt-0"></div>
            <ul class="sidenav-inner py-1">
                <li class="sidenav-item">
                    <a href="dashboard.php" class="sidenav-link">
                        <i class="sidenav-icon feather icon-home"></i>
                        <div>Dashboard</div>
                    </a>
                </li>
            </ul>
        </div>

        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                <a href="index.php" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                    <span class="app-brand-logo demo"><img src="assets/img/logo-dark.png" alt="" class="img-fluid"></span>
                    <span class="app-brand-text demo font-weight-normal ml-2">AuraGold</span>
                </a>
                <div class="layout-sidenav-toggle navbar-nav d-lg-none align-items-lg-center mr-auto">
                    <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:"><i class="ion ion-md-menu text-large align-middle"></i></a>
                </div>
            </nav>

            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <?php include 'sidebar.php'; ?>

                    <div class="row mx-0">
                        <!-- Left: search & list (matches reference layout) -->
                        <div class="col-lg-3 pr-lg-2 if-list-panel">
                            <div class="if-card">
                                <div class="card-body">
                                    <div class="btn-toolbar btn-toolbar-gap mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary">+ Import</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary if-btn-scheme" id="btnOpenCreateScheme" data-toggle="modal" data-target="#createSchemeModal">Create Scheme</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnNewInvestmentFund">+ Add</button>
                                    </div>
                                    <input type="hidden" id="currentFundRecordId" value="">
                                    <div id="investmentListScroll">
                                        <table class="table if-fund-master-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Party</th>
                                                    <th>Scheme Name</th>
                                                </tr>
                                                <tr class="if-fund-search-row">
                                                    <td><input type="text" class="form-control form-control-sm" id="filterParty" placeholder="Search" autocomplete="off"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="filterScheme" placeholder="Search" autocomplete="off"></td>
                                                </tr>
                                            </thead>
                                            <tbody id="investmentListBody"></tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-2 if-list-footer">
                                        <span id="ifFundListInfo">Showing 0 to 0 of 0 entries</span>
                                        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                            <select class="form-control form-control-sm" id="ifFundListPageSize" style="width: auto; max-width: 130px; font-size: 0.72rem;">
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                                <option value="9999">Show All</option>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 if-fund-pg" data-delta="first" title="First">&laquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 if-fund-pg" data-delta="prev" title="Previous">&lsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 if-fund-pg" data-delta="next" title="Next">&rsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 if-fund-pg" data-delta="last" title="Last">&raquo;</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Main -->
                        <div class="col-lg-9 pl-lg-2" id="ifMainContentColumn">
                            <div class="card if-header mb-2" id="ifInvestmentFundHeader">
                                <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                                    <h5 class="mb-0" style="font-weight: 600; font-size: 0.95rem;">
                                        Investment / Layaways Fund No: <span id="fundNoDisplay"><?php echo htmlspecialchars($next_fund_no); ?></span>
                                    </h5>
                                    <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                                        <button type="button" class="btn btn-sm text-white border-0" id="btnSaveFund" style="background: rgba(255,255,255,0.25); font-weight: 600;">Save</button>
                                        <button type="button" class="btn btn-sm text-white border-0 d-none" id="btnEditFund" style="background: rgba(255,255,255,0.35); font-weight: 600;">Edit</button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-end justify-content-between if-subtabs-toolbar mb-2 px-1">
                                <ul class="nav nav-tabs if-subtabs" id="ifMainSubtabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" href="#tabInstallmentEntry" data-toggle="tab" id="ifTabInstallmentEntry">Installment Entry</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#tabInstallmentReport" data-toggle="tab" id="ifTabInstallmentReport">Installment Report</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#tabLayawaysReport" data-toggle="tab">Layaways Report</a>
                                    </li>
                                </ul>
                                <div class="if-subtabs-actions d-none align-items-center flex-wrap pb-1" id="ifSubtabDetailActions">
                                    <button type="button" class="btn btn-sm if-subtabs-action-btn" id="btnDetailPrint">Print</button>
                                    <button type="button" class="btn btn-sm if-subtabs-action-btn" id="btnFundWithdraw">Fund Withdraw</button>
                                    <button type="button" class="btn btn-sm if-subtabs-action-btn" id="btnFundTransfer">Fund Transfer</button>
                                </div>
                            </div>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="tabInstallmentEntry">
                                    <!-- Read-only detail (reference UI) -->
                                    <div id="ifInstallmentDetailView" class="d-none">
                                        <div class="if-detail-card mb-2">
                                            <div class="if-detail-card-inner">
                                                <div class="if-detail-profile">
                                                    <div class="if-detail-avatar"><i class="feather icon-user"></i></div>
                                                    <div class="if-detail-profile-text">
                                                        <div class="if-detail-name" id="dvCustomerName">—</div>
                                                        <div class="if-detail-sub" id="dvLocation">—</div>
                                                        <div class="if-detail-phone"><i class="feather icon-phone"></i><span id="dvPhone">—</span></div>
                                                    </div>
                                                </div>
                                                <div class="if-detail-grid-wrap">
                                                    <div class="row if-detail-grid mx-0">
                                                        <div class="col-lg-4 col-md-6">
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Scheme Name</div>
                                                                <div class="if-dg-value" id="dvSchemeName">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Amount</div>
                                                                <div class="if-dg-value" id="dvAmount">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Redemption On</div>
                                                                <div class="if-dg-value" id="dvRedemption">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Contact No</div>
                                                                <div class="if-dg-value if-dg-empty" id="dvContactNo">—</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-4 col-md-6">
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Joining Dt.</div>
                                                                <div class="if-dg-value" id="dvJoiningDt">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Installment Type</div>
                                                                <div class="if-dg-value" id="dvInstType">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Nominee Name</div>
                                                                <div class="if-dg-value if-dg-empty" id="dvNominee">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Relation Type</div>
                                                                <div class="if-dg-value if-dg-empty" id="dvRelationType">—</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-4 col-md-6">
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Maturity Dt.</div>
                                                                <div class="if-dg-value" id="dvMaturityDt">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Duration</div>
                                                                <div class="if-dg-value" id="dvDuration">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">Email</div>
                                                                <div class="if-dg-value if-dg-empty" id="dvEmail">—</div>
                                                            </div>
                                                            <div class="if-dg-item">
                                                                <div class="if-dg-label">National Id</div>
                                                                <div class="if-dg-value if-dg-empty" id="dvNationalId">—</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="if-detail-inst-section">
                                                <div class="if-detail-inst-heading-bar">
                                                    <h6 class="if-detail-inst-heading-title">Installment Entry</h6>
                                                    <div class="if-detail-inst-heading-actions">
                                                        <button type="button" class="btn btn-sm btn-link p-0 if-detail-more-btn d-none" id="btnDetailNormalMore">MORE &gt;&gt;</button>
                                                        <div class="btn-group toggle-view" role="group">
                                                            <button type="button" class="btn btn-sm btn-primary" id="dvViewNormal">Normal</button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="dvViewPassbook">Passbook</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="table-responsive" style="overflow-x: auto;">
                                                    <table class="table table-bordered table-sm if-detail-inst-table mb-0" id="dvInstallmentTable">
                                                        <thead id="dvInstallmentTableHead"></thead>
                                                        <tbody id="dvInstallmentTableBody"></tbody>
                                                    </table>
                                                </div>
                                                <div class="if-detail-inst-footer" id="ifDetailInstFooter" style="display: none;">
                                                    <div class="if-detail-inst-footer-stats" id="ifDetailInstFooterStats"></div>
                                                    <div class="if-detail-inst-attach d-flex flex-wrap align-items-center">
                                                        <input type="file" id="ifDetailInstAttachInput" accept="image/*" multiple class="d-none">
                                                        <button type="button" class="btn btn-sm if-detail-attach-btn" id="ifDetailInstAttachBtn" title="Attach images">
                                                            <i class="feather icon-image"></i> Attach image
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 small d-none" id="ifDetailInstAttachClear">Clear</button>
                                                        <span class="small text-muted" id="ifDetailInstAttachSummary" style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="ifInstallmentFormView">
                                    <div class="if-card">
                                        <div class="card-body billing-form if-installment-entry-form">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Customer Name <span class="text-danger">*</span></label>
                                                        <div class="position-relative">
                                                            <input type="text" class="form-control form-control-sm" id="customerName" placeholder="Customer" autocomplete="off" style="padding-right: 28px;">
                                                            <input type="hidden" id="customerId" value="">
                                                            <i class="feather icon-plus add-customer-icon" id="addCustomerBtn" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); font-size:14px; cursor:pointer; z-index:11;" title="Add New Customer"></i>
                                                            <div id="customerSuggestions" class="border rounded bg-white shadow-sm" style="display:none; position:absolute; z-index:1050; left:0; right:0; top:100%; max-height:240px; overflow-y:auto; margin-top:2px;"></div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Sales Person</label>
                                                        <select class="form-control form-control-sm" id="salesPerson" data-default="<?php echo htmlspecialchars($sales_person_default); ?>">
                                                            <option value="">Select sales person</option>
                                                            <?php foreach ($sales_person_users as $sp_name): ?>
                                                            <option value="<?php echo htmlspecialchars($sp_name); ?>"<?php echo (strcasecmp($sales_person_default, $sp_name) === 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars($sp_name); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Address</label>
                                                        <textarea class="form-control form-control-sm" id="address" rows="2" placeholder="Address" style="height: 94px;"></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Nominee Name</label>
                                                        <div class="position-relative">
                                                            <input type="text" class="form-control form-control-sm" id="nomineeName" placeholder="Nominee" autocomplete="off">
                                                            <i class="feather icon-plus" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#11294b; font-size:12px; cursor:pointer;" title="Add nominee"></i>
                                                        </div>
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label>Email</label>
                                                        <input type="email" class="form-control form-control-sm" id="email" placeholder="Email">
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-group">
                                                        <label>Scheme Name <span class="text-danger">*</span></label>
                                                        <select class="form-control form-control-sm" id="schemeName">
                                                            <option value="">Select scheme</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Joining Date <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control form-control-sm" id="joiningDate" value="<?php echo htmlspecialchars($today_ymd); ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Maturity Date</label>
                                                        <input type="date" class="form-control form-control-sm" id="maturityDate" value="">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Redemption On</label>
                                                        <input type="text" class="form-control form-control-sm" id="redemptionOn" placeholder="Redemption">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Contact No</label>
                                                        <input type="text" class="form-control form-control-sm" id="contactNo" placeholder="Contact">
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label>Relation Type</label>
                                                        <input type="text" class="form-control form-control-sm" id="relationType" placeholder="Relation">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>Duration</label>
                                                        <input type="text" class="form-control form-control-sm" id="duration" placeholder="e.g. 12 months">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Inst. Type</label>
                                                        <select class="form-control form-control-sm" id="instType">
                                                            <option value="">Select</option>
                                                            <option value="monthly">Monthly</option>
                                                            <option value="weekly">Weekly</option>
                                                            <option value="daily">Daily</option>
                                                            <option value="lump">Lump sum</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Inst. Amt.</label>
                                                        <input type="number" class="form-control form-control-sm" id="instAmt" placeholder="0.00" step="0.01">
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label>National Id</label>
                                                        <input type="text" class="form-control form-control-sm" id="nationalId" placeholder="ID number">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="if-card if-form-installment-card">
                                        <div class="card-body p-0">
                                            <div class="if-detail-inst-heading-bar if-detail-inst-heading-bar--form">
                                                <h6 class="if-detail-inst-heading-title">Installment Entry</h6>
                                                <div class="if-detail-inst-heading-actions">
                                                    <div class="btn-group toggle-view" role="group">
                                                        <button type="button" class="btn btn-sm btn-primary" id="viewNormal">Normal</button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="viewPassbook">Passbook</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="px-3 pt-2 pb-3">
                                            <div class="table-responsive" style="overflow-x: auto;">
                                                <table class="table table-bordered table-sm if-installment-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Sr.No.</th>
                                                            <th>Install. No.</th>
                                                            <th>Payment Date</th>
                                                            <th>Payment Mode</th>
                                                            <th>Amount</th>
                                                            <th>Gold Rate</th>
                                                            <th>Gold Wt.</th>
                                                            <th>Entry By</th>
                                                            <th>Tax</th>
                                                            <th>Tax %</th>
                                                            <th>Taxable Amt.</th>
                                                            <th>Entry Date</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="installmentTableBody">
                                                        <tr class="if-empty-row">
                                                            <td colspan="12" class="text-center text-muted py-4">No Rows To Show</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="row mt-3 if-installment-summary-bar billing-form align-items-end">
                                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                    <label for="ifInstSummaryPaidInst">Paid Inst.</label>
                                                    <input type="text" class="form-control form-control-sm" id="ifInstSummaryPaidInst" readonly tabindex="-1">
                                                </div>
                                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                    <label for="ifInstSummaryPaidAmt">Paid Amount</label>
                                                    <input type="text" class="form-control form-control-sm" id="ifInstSummaryPaidAmt" readonly tabindex="-1">
                                                </div>
                                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                    <label for="ifInstSummaryRemaining">Remaining Amt.</label>
                                                    <input type="text" class="form-control form-control-sm" id="ifInstSummaryRemaining" readonly tabindex="-1">
                                                </div>
                                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                    <label for="ifInstSummaryTotal">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm" id="ifInstSummaryTotal" readonly tabindex="-1">
                                                </div>
                                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                    <label for="ifInstSummaryGoldWt">Gold Wt.</label>
                                                    <input type="text" class="form-control form-control-sm" id="ifInstSummaryGoldWt" readonly tabindex="-1">
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm if-add-row-btn mt-2" id="btnAddInstallmentRow"><i class="feather icon-plus"></i> Add row</button>
                                            </div>
                                        </div>
                                    </div>
                                    </div><!-- /ifInstallmentFormView -->
                                </div>

                                <div class="tab-pane fade" id="tabInstallmentReport">
                                    <div class="if-report-toolbar">
                                        <div class="if-report-toolbar-actions">
                                            <button type="button" class="btn btn-sm btn-outline-secondary if-report-icon-btn if-report-filter-btn" id="ifReportBtnFilter" title="Filter">
                                                <i class="feather icon-filter"></i>
                                                <span class="badge badge-danger if-report-filter-badge d-none" id="ifReportFilterBadge">0</span>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary if-report-icon-btn" id="ifReportBtnRefresh" title="Refresh">
                                                <i class="feather icon-refresh-cw"></i>
                                            </button>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Export</button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="#" id="ifReportExportCsv">Export CSV</a>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary if-report-icon-btn" id="ifReportBtnColumns" title="Column settings">
                                                <i class="feather icon-settings"></i>
                                            </button>
                                            <input type="search" class="form-control form-control-sm" id="ifReportSearchMain" placeholder="Search…" autocomplete="off" style="min-width: 160px; max-width: 220px;">
                                        </div>
                                    </div>
                                    <div class="row if-report-split mx-0">
                                        <div class="col-lg-3 pr-lg-2 mb-3 mb-lg-0">
                                            <div class="if-card if-report-party-card mb-0 h-100">
                                                <div class="card-body p-0 d-flex flex-column" style="min-height: 320px;">
                                                    <div class="table-responsive flex-grow-1">
                                                        <table class="table table-sm table-bordered mb-0 if-report-party-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Party</th>
                                                                    <th class="text-right">No. Of Schemes</th>
                                                                </tr>
                                                                <tr class="if-report-party-search">
                                                                    <td><input type="text" class="form-control form-control-sm" id="ifReportPartyFilter" placeholder="Search" autocomplete="off"></td>
                                                                    <td></td>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="ifReportPartyBody"></tbody>
                                                        </table>
                                                    </div>
                                                    <div class="border-top px-2 py-2 if-report-party-footer" id="ifReportPartyFooter">Showing 0 of 0 entries</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-9 pl-lg-2">
                                            <div id="ifReportDetailCard" class="if-report-detail-card d-none mb-2">
                                                <div class="if-report-detail-inner">
                                                    <div class="d-flex if-report-detail-profile">
                                                        <div class="if-report-detail-avatar"><i class="feather icon-user"></i></div>
                                                        <div>
                                                            <div class="if-report-detail-name" id="ifReportDvCustomerName">—</div>
                                                            <div class="if-report-detail-sub" id="ifReportDvLocation">—</div>
                                                            <div class="if-report-detail-phone"><i class="feather icon-phone"></i><span id="ifReportDvPhone">—</span></div>
                                                        </div>
                                                    </div>
                                                    <div class="if-report-detail-grid-wrap">
                                                        <div class="row if-report-detail-grid mx-0">
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Scheme Name</div>
                                                                <div class="if-report-dg-value" id="ifReportDvSchemeName">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Joining Dt.</div>
                                                                <div class="if-report-dg-value" id="ifReportDvJoiningDt">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Maturity Dt.</div>
                                                                <div class="if-report-dg-value" id="ifReportDvMaturityDt">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Amount</div>
                                                                <div class="if-report-dg-value" id="ifReportDvAmount">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Installment Type</div>
                                                                <div class="if-report-dg-value" id="ifReportDvInstType">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Duration</div>
                                                                <div class="if-report-dg-value" id="ifReportDvDuration">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Redemption On</div>
                                                                <div class="if-report-dg-value" id="ifReportDvRedemption">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Advanced Payment</div>
                                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvAdvancedPayment">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Nominee Name</div>
                                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvNominee">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Email</div>
                                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvEmail">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Contact No</div>
                                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvContactNo">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">Relation Type</div>
                                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvRelationType">—</div>
                                                            </div>
                                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                                <div class="if-report-dg-label">National Id</div>
                                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvNationalId">—</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="if-report-detail-card-footer px-3 pb-3">
                                                    <button type="button" class="btn btn-sm btn-primary" id="ifReportBtnOpenEntry">Open installment entry</button>
                                                </div>
                                            </div>
                                            <div class="if-card mb-0">
                                                <div class="card-body p-2">
                                                    <div class="table-responsive if-report-main-scroll">
                                                        <table class="table table-sm table-bordered mb-0 if-report-main-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Customer</th>
                                                                    <th>Mobile</th>
                                                                    <th>Email</th>
                                                                    <th>Scheme Name</th>
                                                                    <th>Sale Person</th>
                                                                    <th>Inst. Type</th>
                                                                    <th title="Redemption">Redem…</th>
                                                                    <th title="Joining date">Join…</th>
                                                                    <th title="Maturity date">Matur…</th>
                                                                    <th>Fund No</th>
                                                                    <th title="Paid installments">Paid</th>
                                                                    <th class="text-right">Inst. Amt</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="ifReportMainBody">
                                                                <tr><td colspan="12" class="text-center text-muted py-4">No Rows To Show</td></tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="d-flex justify-content-end px-1 pt-2 if-report-main-footer" id="ifReportMainFooter"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="tabLayawaysReport">
                                    <div class="if-card"><div class="card-body text-muted small py-5 text-center">Layaways report will appear here.</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/customer-creation-modal-only.php'; ?>

<!-- Fund Withdraw -->
<div class="modal fade" id="fundWithdrawModal" tabindex="-1" role="dialog" aria-labelledby="fundWithdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 920px;">
        <div class="modal-content if-fw-modal-content">
            <div class="modal-header if-fw-modal-header border-0 rounded-0 position-relative pr-5">
                <h5 class="modal-title w-100 text-center pr-3" id="fundWithdrawModalLabel">Fund Withdraw</h5>
                <button type="button" class="close position-absolute" style="right: 14px; top: 50%; transform: translateY(-50%);" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body if-fw-modal-body">
                <div class="if-fw-customer-line">
                    Customer Name: <strong id="fwCustomerName">—</strong>
                </div>
                <div class="form-row align-items-end mb-2">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-dark mb-1" for="fwWithdrawDate">Date</label>
                        <input type="date" class="form-control form-control-sm" id="fwWithdrawDate">
                    </div>
                    <div class="col-md-8 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-dark mb-1" for="fwExtraPct">Extra Paid</label>
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control" id="fwExtraPct" min="0" max="100" step="0.01" value="0" placeholder="0">
                            <div class="input-group-append"><span class="input-group-text" style="background:#f8f4eb;border-color:#d4c4a8;">%</span></div>
                            <input type="text" class="form-control text-right if-fw-readonly-field" id="fwExtraAmt" readonly tabindex="-1" value="0.00">
                        </div>
                    </div>
                </div>
                <div class="form-row align-items-end mb-3">
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-dark mb-1" for="fwTotalPaidAmt">Total Paid Amount</label>
                        <input type="text" class="form-control form-control-sm text-right if-fw-readonly-field" id="fwTotalPaidAmt" readonly tabindex="-1" value="0.00" title="Sum of all amounts received on installments for this fund.">
                    </div>
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-dark mb-1" for="fwAmount">Withdraw amount</label>
                        <input type="text" class="form-control form-control-sm text-right" id="fwAmount" inputmode="decimal" autocomplete="off" title="How much you are withdrawing now. Defaults to total paid when the fund has receipts; you can change it.">
                    </div>
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-dark mb-1" for="fwTotalAmt">Total Amt.</label>
                        <input type="text" class="form-control form-control-sm text-right if-fw-readonly-field" id="fwTotalAmt" readonly tabindex="-1">
                    </div>
                    <div class="col-6 col-md-3 text-md-right mb-2 mb-md-0">
                        <label class="small font-weight-bold text-dark mb-1 d-block d-md-none">&nbsp;</label>
                        <button type="button" class="btn btn-sm if-fw-btn-add" id="btnFwAddLine">+ Add</button>
                    </div>
                </div>
                <div class="form-row align-items-center mb-2">
                    <div class="col-md-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-dark mb-1 d-block">Payment</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control text-right" id="fwPaymentDraft" placeholder="0.00" inputmode="decimal" autocomplete="off">
                            <div class="input-group-append">
                                <span class="input-group-text bg-white" title="Amount for grid row" style="cursor: default; border-color:#d4c4a8;"><i class="feather icon-credit-card" style="color:#a67c2a;"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="if-fw-pay-icons d-flex flex-wrap align-items-center">
                    <button type="button" class="if-fw-pay-icon if-fw-pay-icon--active" data-fw-pay="Cash" title="Cash"><img src="icons/cash.jpeg" alt="Cash"></button>
                    <button type="button" class="if-fw-pay-icon" data-fw-pay="Bank" title="Bank"><img src="icons/bank.jpeg" alt="Bank"></button>
                    <button type="button" class="if-fw-pay-icon" data-fw-pay="Cheque" title="Cheque"><img src="icons/cheque.jpeg" alt="Cheque"></button>
                    <button type="button" class="if-fw-pay-icon" data-fw-pay="UPI" title="UPI / Online"><img src="icons/upi.jpeg" alt="UPI"></button>
                    <button type="button" class="if-fw-pay-icon" data-fw-pay="Card" title="Card"><img src="icons/card.jpeg" alt="Card"></button>
                    <button type="button" class="if-fw-pay-icon" data-fw-pay="Diamond" title="Diamond"><img src="icons/diamond.jpeg" alt="Diamond"></button>
                    <button type="button" class="if-fw-pay-icon" data-fw-pay="Gold ring" title="Gold ring"><img src="icons/stone.jpeg" alt="Gold ring"></button>
                    <button type="button" class="if-fw-pay-icon" data-fw-pay="Jewellery" title="Jewellery"><img src="icons/scrap.jpeg" alt="Jewellery"></button>
                </div>
                <div class="if-fw-toolbar-row">
                    <button type="button" class="btn btn-sm btn-link p-0 font-weight-bold" id="btnFwMore">MORE &gt;&gt;</button>
                    <button type="button" class="btn btn-sm btn-light border" id="btnFwGridSettings" title="Column settings" style="border-color:#d4c4a8;"><i class="feather icon-settings" style="color:#0f2848;"></i></button>
                </div>
                <div class="if-fw-grid-scroll">
                    <table class="table table-sm table-bordered mb-0 if-fw-pay-table">
                        <thead>
                            <tr>
                                <th>Payment Type</th>
                                <th>Diamond Category</th>
                                <th>Transaction No.</th>
                                <th>Transfer From</th>
                                <th>Cheque Dt.</th>
                                <th class="text-right">Amount</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="fwPaymentGridBody"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right">Total</td>
                                <td class="text-right" id="fwPaymentGridTotal">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer if-fw-modal-footer">
                <button type="button" class="btn btn-sm if-fw-btn-close" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary if-fw-btn-save" id="btnFundWithdrawSave">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Fund Transfer -->
<div class="modal fade" id="fundTransferModal" tabindex="-1" role="dialog" aria-labelledby="fundTransferModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 640px;">
        <div class="modal-content if-ft-modal-content">
            <div class="modal-header if-ft-modal-header border-0 rounded-0 position-relative pr-5">
                <h5 class="modal-title w-100 text-center pr-3" id="fundTransferModalLabel">Fund Transfer</h5>
                <button type="button" class="close position-absolute" style="right: 14px; top: 50%; transform: translateY(-50%);" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body if-ft-modal-body">
                <div class="d-flex flex-wrap justify-content-between align-items-end mb-3">
                    <div class="if-ft-customer-line mb-2 mb-md-0">
                        Customer Name : <strong id="ftCustomerName">—</strong>
                    </div>
                    <div class="mb-2 mb-md-0">
                        <label class="small font-weight-bold text-dark mb-1 d-block" for="ftTransferDate">Date</label>
                        <input type="date" class="form-control form-control-sm" id="ftTransferDate" style="min-width: 160px;">
                    </div>
                </div>
                <div class="if-ft-section-bar">Extra Bonus :</div>
                <div class="form-row align-items-end">
                    <div class="col-md-5 mb-3">
                        <label class="small font-weight-bold text-dark mb-1" for="ftBonusBaseAmount">Amount</label>
                        <input type="text" class="form-control form-control-sm text-right" id="ftBonusBaseAmount" inputmode="decimal" autocomplete="off">
                    </div>
                    <div class="col-md-7 mb-3">
                        <label class="small font-weight-bold text-dark mb-1" for="ftExtraPct">Extra Amt.</label>
                        <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                            <div class="input-group input-group-sm" style="max-width: 120px;">
                                <input type="number" class="form-control text-right" id="ftExtraPct" min="0" max="100" step="0.01" value="0" placeholder="0">
                                <div class="input-group-append"><span class="input-group-text">%</span></div>
                            </div>
                            <input type="text" class="form-control form-control-sm text-right flex-grow-1" id="ftExtraAmt" readonly tabindex="-1" style="min-width: 100px;">
                        </div>
                    </div>
                </div>
                <div class="if-ft-section-bar if-ft-section-bar--total">Total</div>
                <div class="if-ft-formula-block">
                    <div class="if-ft-formula-row">
                        <span class="if-ft-formula-label">Total Bonus :</span>
                        <span class="if-ft-big-val" id="ftDispTotalBonus">0.00</span>
                        <span class="if-ft-op">=</span>
                        <span class="if-ft-formula-label">Extra Amount :</span>
                        <span class="if-ft-big-val" id="ftDispExtraAmount">0.00</span>
                    </div>
                    <div class="if-ft-formula-row">
                        <span class="if-ft-formula-label">Transfer Amt. :</span>
                        <span class="if-ft-big-val" id="ftDispTransferAmt">0.00</span>
                        <span class="if-ft-op">=</span>
                        <span class="if-ft-formula-label">Total Bonus :</span>
                        <span class="if-ft-big-val" id="ftDispTotalBonusRow2">0.00</span>
                        <span class="if-ft-op">+</span>
                        <span class="if-ft-formula-label">Paid Amount :</span>
                        <span class="if-ft-big-val" id="ftDispPaidAmount">0.00</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer if-ft-modal-footer">
                <button type="button" class="btn btn-sm if-ft-btn-close" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary if-ft-btn-save" id="btnFundTransferSave">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Scheme (investment / layaway schemes master) -->
<div class="modal fade" id="createSchemeModal" tabindex="-1" role="dialog" aria-labelledby="createSchemeModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="cs-modal-header">
                <h5 id="createSchemeModalLabel">Create Scheme</h5>
                <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                    <button type="button" class="btn btn-sm btn-cs-new" id="csBtnNew">New Scheme</button>
                    <button type="button" class="btn btn-sm btn-cs-navy" id="csBtnSave">Save</button>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-cs-navy-outline dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Export</button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" id="csExportCsv">Export CSV</a>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close" style="font-weight: 700;">&times;</button>
                </div>
            </div>
            <div class="modal-body" style="padding: 18px 20px;">
                <div class="row">
                    <div class="col-lg-5 border-right-lg pr-lg-3 mb-3 mb-lg-0" style="border-color: #e2e8f0;">
                        <div class="cs-scheme-details-card">
                            <div class="cs-section-title">Scheme Details</div>
                            <form id="createSchemeForm" class="cs-scheme-form" onsubmit="return false;">
                                <input type="hidden" id="csEditId" value="">
                                <div class="form-group cs-form-field-gap">
                                    <label class="cs-form-label" for="csSchemeName">Scheme Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="csSchemeName" required placeholder="">
                                </div>
                                <div class="form-group cs-form-field-gap">
                                    <label class="cs-form-label" for="csRedemption">Redemption on <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-sm" id="csRedemption" required>
                                        <option value="">Select</option>
                                        <option value="Amount">Amount</option>
                                        <option value="Gold">Gold</option>
                                    </select>
                                </div>
                                <div class="form-group cs-form-field-gap">
                                    <label class="cs-form-label" for="csKarat">Karat <span id="csKaratReqStar" class="text-danger">*</span></label>
                                    <select class="form-control form-control-sm" id="csKarat">
                                        <option value="">Select Karat</option>
                                        <?php foreach ($carats as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars(trim(($c['name'] ?? '') . (isset($c['purity']) && $c['purity'] !== '' ? ' — ' . $c['purity'] : ''))); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group cs-form-field-gap">
                                    <label class="cs-form-label">Duration <span class="text-danger">*</span></label>
                                    <div class="d-flex" style="gap: 10px;">
                                        <input type="number" class="form-control form-control-sm" id="csDurationVal" min="1" step="1" value="12" required style="max-width: 110px;">
                                        <select class="form-control form-control-sm flex-grow-1" id="csDurationUnit" required>
                                            <option value="Month">Month</option>
                                            <option value="Year">Year</option>
                                            <option value="Day">Day</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group cs-form-field-gap">
                                    <label class="cs-form-label" for="csInstType">Installment Type <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-sm" id="csInstType" required>
                                        <option value="">Select</option>
                                        <option value="Daily">Daily</option>
                                        <option value="Weekly">Weekly</option>
                                        <option value="Monthly">Monthly</option>
                                    </select>
                                </div>
                                <div class="form-group cs-form-field-gap">
                                    <label class="cs-form-label" for="csInstAmt">Installment Amt.</label>
                                    <input type="number" class="form-control form-control-sm" id="csInstAmt" value="0" min="0" step="0.01">
                                </div>
                                <div class="cs-scheme-form-checks">
                                    <div class="d-flex flex-wrap align-items-center" style="gap: 14px 20px;">
                                        <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                                            <div class="custom-control custom-checkbox mb-0">
                                                <input type="checkbox" class="custom-control-input" id="csMinAmtChk">
                                                <label class="custom-control-label mb-0" for="csMinAmtChk">Minimum Amt.</label>
                                            </div>
                                            <input type="number" class="form-control form-control-sm" id="csMinAmt" value="0" min="0" step="0.01" style="max-width: 150px;" disabled>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-0">
                                            <input type="checkbox" class="custom-control-input" id="csActive" checked>
                                            <label class="custom-control-label mb-0" for="csActive">Active</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="cs-scheme-form-actions d-flex flex-wrap align-items-center" style="gap: 8px;">
                                    <button type="button" class="btn btn-sm btn-cs-navy" id="csBtnSaveBottom">Save Scheme</button>
                                    <button type="button" class="btn btn-sm btn-cs-new" id="csBtnNewBottom">New Scheme</button>
                                    <small class="text-muted ml-1">Or use <strong>Save</strong> in the dark bar at the top of this window.</small>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-7 pl-lg-3 mt-3 mt-lg-0">
                        <div class="cs-scheme-list-card">
                            <div class="cs-section-title">Saved Schemes</div>
                            <div class="table-responsive border rounded" style="max-height: 420px; overflow: auto;">
                            <table class="table table-sm table-bordered mb-0 cs-table-list">
                                <thead>
                                    <tr>
                                        <th>Scheme</th>
                                        <th>Redemption</th>
                                        <th>Duration</th>
                                        <th>Inst. Type</th>
                                        <th>Inst. Amt.</th>
                                        <th>Active</th>
                                        <th>Min. Amt.</th>
                                        <th>Karat</th>
                                        <th style="width: 70px;"><i class="feather icon-settings"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="csListBody">
                                    <tr class="cs-list-empty">
                                        <td colspan="9" class="text-center text-muted py-4 small">No Rows To Show</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap cs-pagination">
                                <span id="csPageInfo">Showing 0 to 0 of 0 entries</span>
                                <div class="d-flex align-items-center" style="gap: 6px;">
                                    <select class="form-control form-control-sm" id="csPageSize" style="width: auto; font-size: 0.72rem;">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="csPgFirst" title="First">&laquo;</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="csPgPrev" title="Previous">&lsaquo;</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="csPgNext" title="Next">&rsaquo;</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="csPgLast" title="Last">&raquo;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment receipt (passbook row) -->
<div class="modal fade" id="investmentReceiptModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border: 1px solid #e2e8f0;">
            <div class="modal-header align-items-center position-relative">
                <h5 class="modal-title">Receipt</h5>
                <button type="button" class="close position-absolute" style="right: 12px; top: 10px;" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" style="padding: 14px 16px;">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="if-rec-label">Entry By <span class="text-danger">*</span></div>
                        <select class="form-control form-control-sm" id="ifRecEntryBy">
                            <option value="">Select</option>
                            <?php foreach ($sales_person_users as $sp_name): ?>
                            <option value="<?php echo htmlspecialchars($sp_name); ?>"<?php echo (strcasecmp($sales_person_default, $sp_name) === 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars($sp_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="if-rec-label">Payment Date</div>
                        <input type="date" class="form-control form-control-sm" id="ifRecPayDate">
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="if-rec-label">Gold Rate/Gm <span class="text-danger">*</span></div>
                        <input type="number" class="form-control form-control-sm" id="ifRecGoldRate" step="0.01" value="0">
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="if-rec-label">Gold Wt. <span class="text-danger">*</span></div>
                        <input type="number" class="form-control form-control-sm" id="ifRecGoldWt" step="0.001" value="0">
                    </div>
                    <div class="col-md-4 mb-2 if-rec-inst-date-wrap">
                        <div class="if-rec-label">Inst. Date</div>
                        <input type="date" class="form-control form-control-sm" id="ifRecInstDate" readonly>
                    </div>
                    <div class="col-md-4 mb-2 if-rec-inst-month-wrap" style="display: none;">
                        <div class="if-rec-label">Inst. Month <span class="text-danger">*</span></div>
                        <div class="dropdown if-rec-month-multiselect" id="ifRecInstMonthDrop">
                            <button type="button" class="form-control form-control-sm text-left d-flex justify-content-between align-items-center" id="ifRecInstMonthBtn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span id="ifRecInstMonthSummary" class="text-truncate flex-grow-1 mr-1">Select months...</span>
                                <i class="feather icon-chevron-down flex-shrink-0" style="font-size:14px;"></i>
                            </button>
                            <div class="dropdown-menu w-100" id="ifRecInstMonthPanel" aria-labelledby="ifRecInstMonthBtn" onclick="event.stopPropagation();">
                                <div class="px-2 pb-1 border-bottom">
                                    <label class="if-rec-month-opt d-block small mb-0 py-1 font-weight-bold">
                                        <input type="checkbox" class="mr-2 align-middle" id="ifRecInstMonthSelectAll"> Select All
                                    </label>
                                </div>
                                <div id="ifRecInstMonthChecks" class="px-2 pt-1"></div>
                                <div id="ifRecInstMonthEmpty" class="px-2 py-2 d-none">All installments already have records.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="if-rec-label">Inst. Amount</div>
                        <input type="number" class="form-control form-control-sm" id="ifRecInstAmt" step="0.01" value="0">
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="if-rec-label">Tax Amount</div>
                        <input type="number" class="form-control form-control-sm" id="ifRecTaxAmt" step="0.01" value="0">
                    </div>
                </div>
                <div class="if-rec-pay-icons payment-icons" id="ifRecPayMethodRow" title="Payment method — opens entry modal (same as Sale Invoice)">
                    <div class="payment-icon payment-cash" data-pm="Cash" title="Cash"><img src="icons/cash.jpeg" alt="Cash"></div>
                    <div class="payment-icon payment-bank" data-pm="Bank" title="Bank"><img src="icons/bank.jpeg" alt="Bank"></div>
                    <div class="payment-icon payment-cheque" data-pm="Cheque" title="Cheque"><img src="icons/cheque.jpeg" alt="Cheque"></div>
                    <div class="payment-icon payment-mobile" data-pm="UPI" title="UPI / Mobile"><img src="icons/upi.jpeg" alt="UPI"></div>
                    <div class="payment-icon payment-card" data-pm="Card" title="Card"><img src="icons/card.jpeg" alt="Card"></div>
                    <div class="payment-icon payment-exchange" data-pm="Metal Exchange" title="Metal Exchange"><img src="icons/metal.jpeg" alt="Metal Exchange"></div>
                    <div class="payment-icon payment-jewelry" data-pm="Scrap" title="Scrap"><img src="icons/scrap.jpeg" alt="Scrap"></div>
                    <div class="payment-icon payment-diamond" data-pm="Diamond" title="Diamond"><img src="icons/diamond.jpeg" alt="Diamond"></div>
                    <div class="payment-icon payment-stone" data-pm="Stone" title="Stone"><img src="icons/stone.jpeg" alt="Stone"></div>
                    <div class="payment-icon payment-other" data-pm="Other" title="Other"><img src="icons/old.jpeg" alt="Other"></div>
                </div>
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-bordered mb-0 if-rec-inner-table">
                        <thead>
                            <tr>
                                <th>Payment Type</th>
                                <th>Diamond Categ.</th>
                                <th>Transaction No.</th>
                                <th>Deposit Into</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="ifRecBreakdownBody">
                            <tr><td colspan="5" class="text-center text-muted py-3 small">No Rows To Show</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-right font-weight-bold" style="font-size: 0.75rem;">Total</td>
                                <td class="font-weight-bold" id="ifRecBreakdownTotal" style="font-size: 0.75rem;">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal" id="ifRecBtnClose">Close</button>
                <button type="button" class="btn btn-sm text-white" style="background: #11294b;" id="ifRecBtnSave">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Print bill confirmation -->
<div class="modal fade" id="ifPrintBillModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 400px;">
        <div class="modal-content if-print-bill-modal-content">
            <button type="button" class="close if-print-bill-close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <div class="modal-body text-center pt-4 pb-3 px-4">
                <div class="if-print-bill-icon-wrap mb-2" aria-hidden="true">&#128196;</div>
                <h4 class="if-print-bill-title font-weight-bold mb-2">Print bill</h4>
                <p class="text-muted mb-4" id="ifPrintBillModalText">Do you want to print?</p>
                <div class="d-flex justify-content-center align-items-center" style="gap: 3rem;">
                    <button type="button" class="btn btn-link if-print-bill-yes p-0 border-0" id="ifPrintBillYes">Yes</button>
                    <button type="button" class="btn btn-link if-print-bill-no p-0 border-0" data-dismiss="modal" id="ifPrintBillNo">No</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/payment-modals-only.php'; ?>

<?php include 'footer-script.php'; ?>
<script>
window.IF_DEFAULT_JOINING_DATE = <?php echo json_encode($today_ymd, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var nationalities = <?php echo json_encode($nationalities_modal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var CS_CARAT_OPTIONS = <?php
    $carat_opts = [];
    foreach ($carats as $c) {
        $carat_opts[] = [
            'id' => (int)$c['id'],
            'label' => trim(($c['name'] ?? '') . (isset($c['purity']) && $c['purity'] !== '' ? ' — ' . $c['purity'] : '')),
        ];
    }
    echo json_encode($carat_opts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;
window.IF_SCHEMES_USE_DB = <?php echo $if_schemes_table_exists ? 'true' : 'false'; ?>;
window.IF_SCHEMES_INITIAL = <?php echo json_encode($if_schemes_initial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.IF_COMPANY_TRN = <?php echo json_encode(defined('COMPANY_TRN') ? COMPANY_TRN : '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.IF_COMPANY_LEGAL_NAME = <?php echo json_encode($emi_voucher_company_legal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="assets/js/customer-creation-modal-common.js"></script>
<script>
(function() {
    var installmentSeq = 0;
    var IF_FUNDS_KEY = 'auragold_investment_funds_v1';
    var ifFundListPage = 1;
    var selectedFundListId = null;
    var ifDetailInstMode = 'normal';
    var ifReceiptCtx = { fundId: null, rowIndex: 0, periodCount: 12, schedYmd: '', mode: 'passbook' };
    var ifRecPaymentDraft = [];
    var ifPrintBillResolve = null;

    function loadFundRecords() {
        try {
            var raw = localStorage.getItem(IF_FUNDS_KEY);
            if (!raw) return [];
            var a = JSON.parse(raw);
            return Array.isArray(a) ? a : [];
        } catch (e) {
            return [];
        }
    }

    function saveFundRecords(arr) {
        localStorage.setItem(IF_FUNDS_KEY, JSON.stringify(arr));
    }

    function getNextFundNo() {
        var list = loadFundRecords();
        var max = 0;
        list.forEach(function (r) {
            var m = String(r.fund_no || '').match(/^IF-(\d+)$/i);
            if (m) max = Math.max(max, parseInt(m[1], 10));
        });
        return 'IF-' + (max + 1);
    }

    /** FT number mirrors IF number (e.g. IF-3 → FT-3) after a completed fund transfer. */
    function deriveFtNoFromFundNo(fundNo) {
        var m = String(fundNo || '').match(/^IF-(\d+)$/i);
        if (m) return 'FT-' + m[1];
        var m2 = String(fundNo || '').match(/(\d+)/);
        return m2 ? 'FT-' + m2[1] : 'FT-1';
    }

    function getFundSidebarDisplayNo(rec) {
        if (!rec) return '';
        if (rec.fund_transfer_done) return deriveFtNoFromFundNo(rec.fund_no);
        return rec.fund_no || '';
    }

    function applyFundTransferLockUi(rec) {
        var locked = !!(rec && rec.fund_transfer_done);
        var w = document.getElementById('btnFundWithdraw');
        var t = document.getElementById('btnFundTransfer');
        var fs = document.getElementById('btnFundTransferSave');
        if (w) w.disabled = locked;
        if (t) t.disabled = false;
        if (fs) fs.disabled = false;
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function getSchemeOptionText(value) {
        var sel = document.getElementById('schemeName');
        if (!sel || value === '' || value == null) return '';
        var v = String(value);
        for (var i = 0; i < sel.options.length; i++) {
            if (String(sel.options[i].value) === v) return sel.options[i].textContent.trim();
        }
        return '';
    }

    function mapSchemeInstallmentTypeToFormValue(t) {
        if (!t) return '';
        var s = String(t).trim().toLowerCase();
        if (s.indexOf('month') !== -1) return 'monthly';
        if (s.indexOf('week') !== -1) return 'weekly';
        if (s.indexOf('day') !== -1) return 'daily';
        if (s.indexOf('lump') !== -1) return 'lump';
        return '';
    }

    function formatSchemeDurationLabel(s) {
        if (!s) return '';
        var v = s.duration_value;
        var u = (s.duration_unit || '').trim();
        if (v == null || v === '' || parseInt(v, 10) <= 0) return u ? String(u) : '';
        var n = parseInt(v, 10);
        var unitDisp = u;
        if (u) {
            var ul = u.toLowerCase();
            if (n !== 1 && ul.slice(-1) !== 's') unitDisp = u + 's';
            else if (n === 1 && ul.slice(-1) === 's') unitDisp = u.replace(/s$/i, '');
        }
        return (String(n) + (unitDisp ? ' ' + unitDisp : '')).trim();
    }

    function computeMaturityFromJoinAndScheme(joinYmd, s) {
        if (!joinYmd || !s) return '';
        var d = new Date(joinYmd + 'T12:00:00');
        if (isNaN(d.getTime())) return '';
        var v = parseInt(s.duration_value, 10);
        if (isNaN(v) || v <= 0) return '';
        var unit = (s.duration_unit || 'Month').toLowerCase();
        if (unit === 'year') d.setFullYear(d.getFullYear() + v);
        else if (unit === 'day') d.setDate(d.getDate() + v);
        else d.setMonth(d.getMonth() + v);
        return d.toISOString().slice(0, 10);
    }

    /** Maturity is always joining date + scheme duration (Month/Year/Day). */
    function syncMaturityDateFromJoinAndScheme() {
        var finder = window.IF_findSchemeById;
        var matEl = document.getElementById('maturityDate');
        var joinEl = document.getElementById('joiningDate');
        if (!matEl || !joinEl) return;
        if (typeof finder !== 'function') {
            matEl.value = '';
            return;
        }
        var scheme = finder(document.getElementById('schemeName') && document.getElementById('schemeName').value);
        if (!scheme) {
            matEl.value = '';
            return;
        }
        matEl.value = computeMaturityFromJoinAndScheme(joinEl.value, scheme) || '';
    }

    function applySelectedSchemeToInstallmentForm() {
        var finder = window.IF_findSchemeById;
        if (typeof finder !== 'function') return;
        var sel = document.getElementById('schemeName');
        var id = sel && sel.value;
        var scheme = finder(id);
        var redEl = document.getElementById('redemptionOn');
        var durEl = document.getElementById('duration');
        var instT = document.getElementById('instType');
        var instA = document.getElementById('instAmt');
        if (!scheme) {
            if (redEl) redEl.value = '';
            if (durEl) durEl.value = '';
            if (instT) instT.value = '';
            if (instA) instA.value = '';
            syncMaturityDateFromJoinAndScheme();
            refreshInstallmentEntrySummary();
            return;
        }
        if (redEl) redEl.value = scheme.redemption_on || '';
        if (durEl) durEl.value = formatSchemeDurationLabel(scheme);
        if (instT) instT.value = mapSchemeInstallmentTypeToFormValue(scheme.installment_type);
        if (instA) {
            instA.value =
                scheme.installment_amt != null && scheme.installment_amt !== ''
                    ? Number(scheme.installment_amt)
                    : '';
        }
        syncMaturityDateFromJoinAndScheme();
        refreshInstallmentEntrySummary();
    }

    function refreshInstallmentEntrySummary() {
        var paidEl = document.getElementById('ifInstSummaryPaidInst');
        if (!paidEl) return;
        var paidAmtEl = document.getElementById('ifInstSummaryPaidAmt');
        var remEl = document.getElementById('ifInstSummaryRemaining');
        var totEl = document.getElementById('ifInstSummaryTotal');
        var gwEl = document.getElementById('ifInstSummaryGoldWt');
        var rows = collectInstallmentRowsData();
        var n = computeTotalInstallments(rows);
        var perEl = document.getElementById('instAmt');
        var per = perEl ? parseFloat(perEl.value) : NaN;
        if (isNaN(per)) per = 0;
        var rec = { installments: rows };
        var t = recalcFundPassbookTotals(rec, n);
        var totalDue = n * per;
        var rem = Math.max(0, totalDue - t.paidAmt);
        paidEl.value = t.paid + ' / ' + n;
        if (paidAmtEl) paidAmtEl.value = formatMoney(t.paidAmt);
        if (remEl) remEl.value = formatMoney(rem);
        if (totEl) totEl.value = formatMoney(totalDue);
        if (gwEl) {
            var gw = t.goldWt;
            gwEl.value = isNaN(gw) ? '0.000' : Number(gw).toFixed(3);
        }
    }

    function collectInstallmentRowsData() {
        var tbody = document.getElementById('installmentTableBody');
        if (!tbody) return [];
        var out = [];
        tbody.querySelectorAll('tr:not(.if-empty-row)').forEach(function (tr) {
            var inp = tr.querySelectorAll('input, select');
            if (inp.length < 11) return;
            out.push({
                inst_no: inp[0].value,
                pay_date: inp[1].value,
                pay_mode: inp[2].value,
                amount: inp[3].value,
                gold_rate: inp[4].value,
                gold_wt: inp[5].value,
                entry_by: inp[6].value,
                tax: inp[7].value,
                tax_pct: inp[8].value,
                taxable: inp[9].value,
                entry_date: inp[10].value
            });
        });
        return out;
    }

    function countPaidInstallments(rows) {
        if (!rows || !rows.length) return 0;
        var n = 0;
        rows.forEach(function (r) {
            var a = parseFloat(r.amount);
            if ((r.pay_date && String(r.pay_date).trim() !== '') || (a > 0)) n++;
        });
        return n;
    }

    function computeTotalInstallments(rows) {
        var c = rows && rows.length ? rows.length : 0;
        return Math.max(c, 12);
    }

    function refreshInstallmentEmptyState() {
        var tbody = document.getElementById('installmentTableBody');
        var rows = tbody.querySelectorAll('tr:not(.if-empty-row)');
        var empty = tbody.querySelector('.if-empty-row');
        if (rows.length === 0) {
            installmentSeq = 0;
            if (!empty) {
                var tr = document.createElement('tr');
                tr.className = 'if-empty-row';
                tr.innerHTML = '<td colspan="12" class="text-center text-muted py-4">No Rows To Show</td>';
                tbody.appendChild(tr);
            }
        } else if (empty) {
            empty.remove();
        }
    }

    function appendInstallmentRow(data) {
        var tbody = document.getElementById('installmentTableBody');
        var empty = tbody.querySelector('.if-empty-row');
        if (empty) empty.remove();
        installmentSeq++;
        var d = data || {};
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + installmentSeq + '</td>' +
            '<td><input type="text" class="form-control form-control-sm" name="inst_no[]" value="' + escapeHtml(d.inst_no || '') + '"></td>' +
            '<td><input type="date" class="form-control form-control-sm" name="pay_date[]" value="' + escapeHtml(d.pay_date || '') + '"></td>' +
            '<td><select class="form-control form-control-sm" name="pay_mode[]">' +
            '<option value="">—</option>' +
            ['Cash', 'Bank', 'UPI'].map(function (m) {
                return '<option' + (String(d.pay_mode) === m ? ' selected' : '') + '>' + m + '</option>';
            }).join('') +
            '</select></td>' +
            '<td><input type="number" class="form-control form-control-sm" name="amount[]" step="0.01" value="' +
            escapeHtml(d.amount != null && d.amount !== '' ? d.amount : '') +
            '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" name="gold_rate[]" step="0.01" value="' +
            escapeHtml(d.gold_rate != null && d.gold_rate !== '' ? d.gold_rate : '') +
            '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" name="gold_wt[]" step="0.001" value="' +
            escapeHtml(d.gold_wt != null && d.gold_wt !== '' ? d.gold_wt : '') +
            '"></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="entry_by[]" value="' + escapeHtml(d.entry_by || '') + '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" name="tax[]" step="0.01" value="' +
            escapeHtml(d.tax != null && d.tax !== '' ? d.tax : '') +
            '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" name="tax_pct[]" step="0.01" value="' +
            escapeHtml(d.tax_pct != null && d.tax_pct !== '' ? d.tax_pct : '') +
            '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" name="taxable[]" step="0.01" value="' +
            escapeHtml(d.taxable != null && d.taxable !== '' ? d.taxable : '') +
            '"></td>' +
            '<td><input type="date" class="form-control form-control-sm" name="entry_date[]" value="' + escapeHtml(d.entry_date || '') + '"></td>';
        tbody.appendChild(tr);
        refreshInstallmentEmptyState();
        refreshInstallmentEntrySummary();
    }

    function applyInstallmentRowsData(rows) {
        var tbody = document.getElementById('installmentTableBody');
        tbody.innerHTML = '';
        installmentSeq = 0;
        if (!rows || !rows.length) {
            refreshInstallmentEmptyState();
            refreshInstallmentEntrySummary();
            return;
        }
        rows.forEach(function (r) {
            appendInstallmentRow(r);
        });
    }

    function ensureSalesPersonSelectValue(sel, val) {
        if (!sel) return;
        var v = val != null ? String(val).trim() : '';
        if (v === '') {
            sel.value = '';
            return;
        }
        var lower = v.toLowerCase();
        var found = false;
        for (var i = 0; i < sel.options.length; i++) {
            if (String(sel.options[i].value).trim().toLowerCase() === lower) {
                sel.selectedIndex = i;
                found = true;
                break;
            }
        }
        if (!found) {
            var o = document.createElement('option');
            o.value = v;
            o.textContent = v;
            sel.appendChild(o);
            sel.value = v;
        }
    }

    function collectFormSnapshot() {
        var instAmt = document.getElementById('instAmt').value;
        return {
            customer_name: document.getElementById('customerName').value.trim(),
            customer_id: document.getElementById('customerId').value,
            sales_person: document.getElementById('salesPerson').value,
            address: document.getElementById('address').value,
            nominee_name: document.getElementById('nomineeName').value,
            email: document.getElementById('email').value,
            scheme_id: document.getElementById('schemeName').value,
            scheme_label: getSchemeOptionText(document.getElementById('schemeName').value),
            joining_date: document.getElementById('joiningDate').value,
            maturity_date: document.getElementById('maturityDate').value,
            redemption_on: document.getElementById('redemptionOn').value,
            contact_no: document.getElementById('contactNo').value,
            relation_type: document.getElementById('relationType').value,
            duration: document.getElementById('duration').value,
            inst_type: document.getElementById('instType').value,
            inst_amt: instAmt === '' ? 0 : parseFloat(instAmt) || 0,
            national_id: document.getElementById('nationalId').value,
            installments: collectInstallmentRowsData()
        };
    }

    function applyFormSnapshot(rec) {
        document.getElementById('customerName').value = rec.customer_name || '';
        document.getElementById('customerId').value = rec.customer_id || '';
        window.selectedCustomerId = rec.customer_id ? parseInt(rec.customer_id, 10) : null;
        ensureSalesPersonSelectValue(document.getElementById('salesPerson'), rec.sales_person || '');
        document.getElementById('address').value = rec.address || '';
        document.getElementById('nomineeName').value = rec.nominee_name || '';
        document.getElementById('email').value = rec.email || '';
        document.getElementById('schemeName').value = rec.scheme_id || '';
        document.getElementById('joiningDate').value = rec.joining_date || '';
        document.getElementById('redemptionOn').value = rec.redemption_on || '';
        document.getElementById('contactNo').value = rec.contact_no || '';
        document.getElementById('relationType').value = rec.relation_type || '';
        document.getElementById('duration').value = rec.duration || '';
        document.getElementById('instType').value = rec.inst_type || '';
        document.getElementById('instAmt').value = rec.inst_amt != null ? rec.inst_amt : '';
        document.getElementById('nationalId').value = rec.national_id || '';
        applyInstallmentRowsData(rec.installments || []);
        syncMaturityDateFromJoinAndScheme();
    }

    function setInvestmentMainView(mode) {
        var det = document.getElementById('ifInstallmentDetailView');
        var frm = document.getElementById('ifInstallmentFormView');
        var saveBtn = document.getElementById('btnSaveFund');
        var editBtn = document.getElementById('btnEditFund');
        var tabActs = document.getElementById('ifSubtabDetailActions');
        if (!det || !frm) return;
        if (mode === 'detail') {
            det.classList.remove('d-none');
            frm.classList.add('d-none');
            if (saveBtn) saveBtn.classList.add('d-none');
            if (editBtn) editBtn.classList.remove('d-none');
            if (tabActs) {
                tabActs.classList.remove('d-none');
                tabActs.classList.add('d-flex');
            }
            applyFundTransferLockUi(getCurrentFundRecord());
        } else {
            det.classList.add('d-none');
            frm.classList.remove('d-none');
            if (saveBtn) saveBtn.classList.remove('d-none');
            if (editBtn) editBtn.classList.add('d-none');
            if (tabActs) {
                tabActs.classList.add('d-none');
                tabActs.classList.remove('d-flex');
            }
            applyFundTransferLockUi(getCurrentFundRecord());
        }
    }

    function formatDateDisplay(ymd) {
        if (!ymd || String(ymd).trim() === '') return '—';
        var p = String(ymd).split('-');
        if (p.length !== 3) return ymd;
        return p[1] + '/' + p[2] + '/' + p[0];
    }

    function formatDateDMY(ymd) {
        if (!ymd || String(ymd).trim() === '') return '';
        var p = String(ymd).split('-');
        if (p.length !== 3) return ymd;
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function pad2Num(x) {
        return x < 10 ? '0' + x : String(x);
    }

    /** Detail installment table: dd/mm/yyyy from YYYY-MM-DD */
    function formatDateDDMMYYYY(ymd) {
        if (!ymd || String(ymd).trim() === '') return '';
        var p = String(ymd).trim().split('-');
        if (p.length !== 3) return ymd;
        var d = parseInt(p[2], 10);
        var m = parseInt(p[1], 10);
        var y = p[0];
        if (isNaN(d) || isNaN(m)) return ymd;
        return pad2Num(d) + '/' + pad2Num(m) + '/' + y;
    }

    function formatDetailMoney2(val) {
        if (val == null || String(val).trim() === '') return '0.00';
        var n = parseFloat(String(val).replace(/,/g, ''));
        return isNaN(n) ? String(val).trim() : n.toFixed(2);
    }

    function formatDetailGoldWt3(val) {
        if (val == null || String(val).trim() === '') return '0.000';
        var n = parseFloat(String(val).replace(/,/g, ''));
        return isNaN(n) ? String(val).trim() : n.toFixed(3);
    }

    function parseInstallmentCountFromRec(rec) {
        var n = rec.total_installments;
        if (n != null && String(n).trim() !== '') {
            var x = parseInt(n, 10);
            if (x > 0) return Math.min(240, x);
        }
        var d = (rec.duration || '').toString();
        var m = d.match(/(\d+)/);
        if (m) return Math.min(240, Math.max(1, parseInt(m[1], 10)));
        return 12;
    }

    function getPassbookPeriodCount(rec) {
        var t = (rec.inst_type || 'monthly').toLowerCase();
        if (t === 'lump') return 1;
        return parseInstallmentCountFromRec(rec);
    }

    function passbookScheduleDateYmd(rec, index) {
        var join = rec.joining_date;
        if (!join || String(join).trim() === '') return '';
        var t = (rec.inst_type || 'monthly').toLowerCase();
        if (t === 'weekly') {
            var d = new Date(join + 'T12:00:00');
            if (isNaN(d.getTime())) return '';
            d.setDate(d.getDate() + index * 7);
            return d.toISOString().slice(0, 10);
        }
        if (t === 'daily') {
            var d2 = new Date(join + 'T12:00:00');
            if (isNaN(d2.getTime())) return '';
            d2.setDate(d2.getDate() + index);
            return d2.toISOString().slice(0, 10);
        }
        var p = String(join).split('-');
        if (p.length !== 3) return '';
        var jy = parseInt(p[0], 10);
        var jm = parseInt(p[1], 10);
        var jd = parseInt(p[2], 10);
        if (!jy || !jm || !jd) return '';
        var total = jy * 12 + (jm - 1) + index;
        var year = Math.floor(total / 12);
        var month = total % 12;
        var lastDay = new Date(year, month + 1, 0).getDate();
        var day = Math.min(jd, lastDay);
        var mm = month + 1;
        return year + '-' + pad2Num(mm) + '-' + pad2Num(day);
    }

    function getInstallmentSlot(rec, index, n) {
        var arr = rec.installments || [];
        if (arr[index] != null && typeof arr[index] === 'object') return arr[index];
        var want = String(index + 1);
        for (var k = 0; k < arr.length; k++) {
            if (arr[k] && String(arr[k].inst_no || '') === want) return arr[k];
        }
        return {};
    }

    function ensureInstallmentsLength(rec, n) {
        var a = Array.isArray(rec.installments) ? rec.installments.slice() : [];
        while (a.length < n) a.push({});
        rec.installments = a;
    }

    function recalcFundPassbookTotals(rec, n) {
        var paid = 0;
        var paidAmt = 0;
        var gwt = 0;
        var arr = rec.installments || [];
        for (var i = 0; i < n; i++) {
            var r = arr[i] || {};
            var a = parseFloat(r.amount);
            var hasPay = (r.pay_date && String(r.pay_date).trim() !== '') || (!isNaN(a) && a > 0);
            if (hasPay) paid++;
            if (!isNaN(a)) paidAmt += a;
            var gw = parseFloat(r.gold_wt);
            if (!isNaN(gw)) gwt += gw;
        }
        rec.paid_installments = paid;
        rec.total_installments = n;
        return { paid: paid, paidAmt: paidAmt, goldWt: gwt };
    }

    function detailInstNormalThead() {
        return (
            '<tr>' +
            '<th>Sr.No.</th><th>Installment</th><th>Payment Desc.</th><th>Payment Date</th><th>Amount</th>' +
            '<th>Gold Rate</th><th>Gold Wt.</th><th>Entry By</th><th>Tax</th><th>Tax %</th><th>Taxable Amt.</th><th>Entry Date</th>' +
            '<th style="min-width: 200px;">Actions</th>' +
            '</tr>'
        );
    }

    function detailInstPassbookThead() {
        return (
            '<tr>' +
            '<th>Sr.No.</th><th>Inst. Date</th><th>Payment Date</th><th>Payment Mode</th><th>Amount</th>' +
            '<th>Gold Rate</th><th>Gold Wt.</th><th>Entry By</th><th>Tax</th><th>Tax %</th><th>Taxable Amt.</th><th>Entry Date</th><th>Actions</th>' +
            '</tr>'
        );
    }

    function refreshDetailMoreBtnVisibility() {
        var b = document.getElementById('btnDetailNormalMore');
        if (!b) return;
        b.classList.toggle('d-none', ifDetailInstMode !== 'normal');
    }

    function installmentOrdinalLabel(i) {
        var n = i + 1;
        if (n === 1) return '1st installment';
        if (n === 2) return '2nd installment';
        if (n === 3) return '3rd installment';
        return n + 'th installment';
    }

    function formatInstallmentWeekdayCell(row, rec, idx) {
        var ymd = row.inst_date || row.pay_date;
        if ((!ymd || String(ymd).trim() === '') && rec) {
            ymd = passbookScheduleDateYmd(rec, idx);
        }
        if (!ymd || String(ymd).trim() === '') return '—';
        var d = new Date(ymd + 'T12:00:00');
        if (isNaN(d.getTime())) return ymd;
        var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return d.getDate() + ' ' + days[d.getDay()] + ' ' + months[d.getMonth()];
    }

    function findFirstUnpaidIndex(rec, n) {
        for (var i = 0; i < n; i++) {
            var row = getInstallmentSlot(rec, i, n);
            var hasPaid =
                (row.pay_date && String(row.pay_date).trim() !== '') ||
                (row.amount != null && String(row.amount).trim() !== '' && parseFloat(row.amount) > 0);
            if (!hasPaid) return i;
        }
        return 0;
    }

    function isInstallmentRowMeaningful(r) {
        if (!r || typeof r !== 'object') return false;
        if (r.pay_date && String(r.pay_date).trim() !== '') return true;
        if (r.amount != null && String(r.amount).trim() !== '' && parseFloat(r.amount) !== 0) return true;
        if (r.gold_rate != null && String(r.gold_rate).trim() !== '' && parseFloat(r.gold_rate) > 0) return true;
        if (r.gold_wt != null && String(r.gold_wt).trim() !== '' && parseFloat(r.gold_wt) !== 0) return true;
        if (r.entry_by && String(r.entry_by).trim() !== '') return true;
        if (r.receipt_no && String(r.receipt_no).trim() !== '') return true;
        return false;
    }

    function getIfRecSelectedMonthIndices() {
        var out = [];
        document.querySelectorAll('#ifRecInstMonthChecks input[type=checkbox]:checked').forEach(function (cb) {
            var v = parseInt(cb.value, 10);
            if (!isNaN(v)) out.push(v);
        });
        return out.sort(function (a, b) {
            return a - b;
        });
    }

    function updateIfRecInstMonthSummary() {
        var arr = getIfRecSelectedMonthIndices();
        var span = document.getElementById('ifRecInstMonthSummary');
        if (!span) return;
        if (!arr.length) {
            span.textContent = 'Select months...';
            return;
        }
        if (arr.length === 1) {
            span.textContent = installmentOrdinalLabel(arr[0]);
            return;
        }
        span.textContent = arr.length + ' installments selected';
    }

    function syncIfRecInstMonthSelectAllState() {
        var selAll = document.getElementById('ifRecInstMonthSelectAll');
        if (!selAll) return;
        var boxes = document.querySelectorAll('#ifRecInstMonthChecks input[type=checkbox]');
        if (!boxes.length) {
            selAll.checked = false;
            selAll.indeterminate = false;
            return;
        }
        var nChecked = 0;
        boxes.forEach(function (cb) {
            if (cb.checked) nChecked++;
        });
        selAll.checked = nChecked === boxes.length;
        selAll.indeterminate = nChecked > 0 && nChecked < boxes.length;
    }

    function getIfRecPerInstallmentBaseAmount(rec, firstIx, n) {
        var per = rec.inst_amt != null ? Number(rec.inst_amt) : 0;
        if (!isNaN(per) && per > 0) return per;
        var row = getInstallmentSlot(rec, firstIx, n);
        var a = parseFloat(row.amount);
        if (!isNaN(a) && a > 0) return a;
        return 0;
    }

    function applyIfRecMultiMonthInstAmount(rec, indices) {
        var el = document.getElementById('ifRecInstAmt');
        if (!el || !indices || indices.length < 2) return;
        var n = getPassbookPeriodCount(rec);
        var base = getIfRecPerInstallmentBaseAmount(rec, indices[0], n);
        if (base <= 0) {
            var cur = parseFloat(el.value);
            if (!isNaN(cur) && cur > 0) base = cur / indices.length;
        }
        if (base <= 0) return;
        el.value = (base * indices.length).toFixed(2);
    }

    function onIfRecInstMonthSelectionChange() {
        updateIfRecInstMonthSummary();
        syncIfRecInstMonthSelectAllState();
        if (ifReceiptCtx.mode !== 'normal') return;
        var rec = getCurrentFundRecord();
        if (!rec) return;
        var indices = getIfRecSelectedMonthIndices();
        if (!indices.length) return;
        var ix = indices[0];
        var n = getPassbookPeriodCount(rec);
        var sched = passbookScheduleDateYmd(rec, ix);
        var instDateEl = document.getElementById('ifRecInstDate');
        if (instDateEl) instDateEl.value = sched || '';
        applyReceiptFormFromRow(ix, sched, rec);
        applyIfRecMultiMonthInstAmount(rec, indices);
        ifReceiptCtx.rowIndex = ix;
        ifReceiptCtx.schedYmd = sched;
    }

    /**
     * Inst. months that already have installment data are omitted (same as Sale-style "already paid" rows).
     * @param {Array<number>|number|null} preselectedIndices - indices to check initially
     * @param {boolean} skipApply - if true, do not reload receipt form (caller will applyReceiptFormFromRow)
     */
    function renderIfRecInstMonthCheckboxes(rec, preselectedIndices, skipApply) {
        var n = getPassbookPeriodCount(rec);
        var host = document.getElementById('ifRecInstMonthChecks');
        var emptyEl = document.getElementById('ifRecInstMonthEmpty');
        var selAll = document.getElementById('ifRecInstMonthSelectAll');
        if (!host) return;
        var preset = {};
        if (Array.isArray(preselectedIndices)) {
            preselectedIndices.forEach(function (x) {
                if (x != null && x !== '') preset[String(x)] = true;
            });
        } else if (preselectedIndices != null && preselectedIndices !== '') {
            preset[String(preselectedIndices)] = true;
        }
        host.innerHTML = '';
        var availableCount = 0;
        for (var i = 0; i < n; i++) {
            var slot = getInstallmentSlot(rec, i, n);
            var preselectedHere = !!preset[String(i)];
            /* Omit paid slots from *new* entry, but always show the row being edited (preset). */
            if (isInstallmentRowMeaningful(slot) && !preselectedHere) continue;
            availableCount++;
            var id = 'ifRecInstChk_' + i;
            var lab = document.createElement('label');
            lab.className = 'if-rec-month-opt d-block small mb-0 py-1';
            lab.setAttribute('for', id);
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'mr-2 align-middle';
            cb.value = String(i);
            cb.id = id;
            cb.addEventListener('change', onIfRecInstMonthSelectionChange);
            if (preset[String(i)]) cb.checked = true;
            lab.appendChild(cb);
            lab.appendChild(document.createTextNode(installmentOrdinalLabel(i)));
            host.appendChild(lab);
        }
        if (emptyEl) {
            emptyEl.classList.toggle('d-none', availableCount > 0);
        }
        if (selAll) {
            selAll.disabled = availableCount === 0;
            if (availableCount === 0) {
                selAll.checked = false;
                selAll.indeterminate = false;
            }
        }
        updateIfRecInstMonthSummary();
        syncIfRecInstMonthSelectAllState();
        if (!skipApply) {
            onIfRecInstMonthSelectionChange();
        }
    }

    function syncReceiptModalLayout(mode) {
        var isNormal = mode === 'normal';
        var wMonth = document.querySelector('.if-rec-inst-month-wrap');
        var wDate = document.querySelector('.if-rec-inst-date-wrap');
        if (wMonth) wMonth.style.display = isNormal ? '' : 'none';
        if (wDate) wDate.style.display = isNormal ? 'none' : '';
    }

    function applyReceiptFormFromRow(rowIndex, schedYmd, rec) {
        var n = getPassbookPeriodCount(rec);
        var row = getInstallmentSlot(rec, rowIndex, n);
        var per = rec.inst_amt != null ? Number(rec.inst_amt) : 0;
        document.getElementById('ifRecInstDate').value = schedYmd || '';
        document.getElementById('ifRecPayDate').value = row.pay_date || schedYmd || '';
        document.getElementById('ifRecInstAmt').value = row.amount != null && row.amount !== '' ? row.amount : per;
        document.getElementById('ifRecGoldRate').value = row.gold_rate != null && row.gold_rate !== '' ? row.gold_rate : '';
        if (document.getElementById('ifRecGoldRate').value === '') {
            document.getElementById('ifRecGoldRate').value = '540.25';
        }
        document.getElementById('ifRecGoldWt').value = row.gold_wt != null && row.gold_wt !== '' ? row.gold_wt : '0';
        document.getElementById('ifRecTaxAmt').value = row.tax != null && row.tax !== '' ? row.tax : '0';
        var eb = document.getElementById('ifRecEntryBy');
        if (eb) ensureSalesPersonSelectValue(eb, row.entry_by || '');
        ifRecPaymentDraft = [];
        if (row.payment_breakdown && Array.isArray(row.payment_breakdown)) {
            row.payment_breakdown.forEach(function (p) {
                ifRecPaymentDraft.push(Object.assign({}, p));
            });
        }
        renderIfRecBreakdownFromDraft();
        document.querySelectorAll('#ifRecPayMethodRow .payment-icon').forEach(function (b) {
            b.classList.remove('active');
        });
        if (row.pay_mode) {
            document.querySelectorAll('#ifRecPayMethodRow .payment-icon').forEach(function (b) {
                if (b.getAttribute('data-pm') === row.pay_mode) b.classList.add('active');
            });
        } else if (ifRecPaymentDraft.length) {
            syncIfRecPayIconActive(ifRecPaymentDraft[ifRecPaymentDraft.length - 1].type);
        }
    }

    function paymentTypeDisplayLabel(type) {
        var t = String(type || '').toLowerCase();
        if (t === 'cash') return 'Cash';
        if (t === 'bank') return 'Bank';
        if (t === 'cheque') return 'Cheque';
        if (t === 'upi') return 'UPI';
        if (t === 'card') return 'Card';
        if (t === 'metal-exchange') return 'M. Exch.';
        if (t === 'scrap') return 'Scrap';
        return type ? String(type) : '';
    }

    function collectDetailInstallmentPrintItems(rec) {
        if (!rec) return [];
        if (ifDetailInstMode === 'passbook') {
            var n = getPassbookPeriodCount(rec);
            var out = [];
            for (var i = 0; i < n; i++) {
                var row = getInstallmentSlot(rec, i, n);
                var hasPaid =
                    (row.pay_date && String(row.pay_date).trim() !== '') ||
                    (row.amount != null && String(row.amount).trim() !== '' && parseFloat(row.amount) > 0);
                if (hasPaid) out.push({ row: row, schedIx: i });
            }
            return out;
        }
        var raw = rec.installments || [];
        var visible = [];
        for (var ji = 0; ji < raw.length; ji++) {
            if (isInstallmentRowMeaningful(raw[ji])) {
                visible.push({ row: raw[ji], schedIx: ji });
            }
        }
        return visible.map(function (item) {
            var row = item.row;
            var schedIx = item.schedIx;
            if (row.inst_no != null && String(row.inst_no).trim() !== '') {
                var pn = parseInt(row.inst_no, 10);
                if (!isNaN(pn) && pn >= 1) schedIx = pn - 1;
            }
            return { row: row, schedIx: schedIx };
        });
    }

    var IF_PRINT_STORAGE_KEY = 'auragold_if_print_payload_v1';

    function openInvestmentFundPrintPage(rec, items) {
        if (!rec || !items || !items.length) {
            alert('No installment data to print.');
            return;
        }
        try {
            localStorage.setItem(IF_PRINT_STORAGE_KEY, JSON.stringify({
                v: 1,
                t: Date.now(),
                rec: rec,
                items: items
            }));
        } catch (e) {
            alert('Could not prepare print.');
            return;
        }
        window.open('investment-fund-print.php', '_blank', 'noopener,noreferrer');
    }

    function openPrintBillModal(onYes) {
        ifPrintBillResolve = typeof onYes === 'function' ? onYes : null;
        if (window.jQuery) {
            window.jQuery('#ifPrintBillModal').modal('show');
        } else if (window.confirm('Do you want to print?')) {
            ifPrintBillResolve = null;
            if (typeof onYes === 'function') onYes();
        } else {
            ifPrintBillResolve = null;
        }
    }

    function syncIfRecPayIconActive(type) {
        var map = {
            cash: 'payment-cash',
            bank: 'payment-bank',
            cheque: 'payment-cheque',
            upi: 'payment-mobile',
            card: 'payment-card',
            'metal-exchange': 'payment-exchange',
            scrap: 'payment-jewelry'
        };
        var cls = map[String(type || '').toLowerCase()];
        document.querySelectorAll('#ifRecPayMethodRow .payment-icon').forEach(function (el) {
            el.classList.remove('active');
        });
        if (cls) {
            var el = document.querySelector('#ifRecPayMethodRow .payment-icon.' + cls);
            if (el) el.classList.add('active');
        }
    }

    function refreshIfRecBreakdownTotal() {
        var sum = 0;
        ifRecPaymentDraft.forEach(function (p) {
            sum += parseFloat(p.amount) || 0;
        });
        var tEl = document.getElementById('ifRecBreakdownTotal');
        if (tEl) tEl.textContent = sum.toFixed(2);
    }

    function renderIfRecBreakdownFromDraft() {
        var tb = document.getElementById('ifRecBreakdownBody');
        if (!tb) return;
        tb.innerHTML = '';
        if (!ifRecPaymentDraft.length) {
            tb.innerHTML =
                '<tr><td colspan="5" class="text-center text-muted py-3 small">No Rows To Show</td></tr>';
            refreshIfRecBreakdownTotal();
            return;
        }
        ifRecPaymentDraft.forEach(function (p) {
            var tr = document.createElement('tr');
            var pt = paymentTypeDisplayLabel(p.type);
            tr.innerHTML =
                '<td class="small">' +
                escapeHtml(pt) +
                '</td><td class="small">' +
                escapeHtml(p.diamond_category || '') +
                '</td><td class="small">' +
                escapeHtml(p.transaction_no || '') +
                '</td><td class="small">' +
                escapeHtml(p.deposit_into || '') +
                '</td><td class="small text-right">' +
                (parseFloat(p.amount) || 0).toFixed(2) +
                '</td>';
            tb.appendChild(tr);
        });
        refreshIfRecBreakdownTotal();
    }

    function hideIfPaymentModalByType(type) {
        if (typeof window.jQuery === 'undefined') return;
        var map = {
            cash: '#cashPaymentModal',
            bank: '#bankPaymentModal',
            cheque: '#chequePaymentModal',
            upi: '#upiPaymentModal',
            card: '#cardPaymentModal',
            'metal-exchange': '#metalExchangeModal',
            scrap: '#scrapPaymentModal'
        };
        var id = map[type];
        if (id) window.jQuery(id).modal('hide');
    }

    function clearIfPaymentModalFields(type) {
        var todayYmd = (window.IF_DEFAULT_JOINING_DATE || '').trim() || new Date().toISOString().slice(0, 10);
        if (type === 'cash') {
            var ca = document.getElementById('cashAmount');
            if (ca) ca.value = '0.00';
        } else if (type === 'bank') {
            if (document.getElementById('bankDepositInto')) document.getElementById('bankDepositInto').value = '';
            if (document.getElementById('bankTransNo')) document.getElementById('bankTransNo').value = '';
            if (document.getElementById('bankAmount')) document.getElementById('bankAmount').value = '0.00';
        } else if (type === 'cheque') {
            if (document.getElementById('chequeDepositInto')) document.getElementById('chequeDepositInto').value = '';
            if (document.getElementById('chequeTransNo')) document.getElementById('chequeTransNo').value = '';
            if (document.getElementById('chequeAmount')) document.getElementById('chequeAmount').value = '0.00';
            if (document.getElementById('chequeDate')) document.getElementById('chequeDate').value = todayYmd;
        } else if (type === 'upi') {
            if (document.getElementById('upiDepositInto')) document.getElementById('upiDepositInto').value = '';
            if (document.getElementById('upiTransNo')) document.getElementById('upiTransNo').value = '';
            if (document.getElementById('upiAmount')) document.getElementById('upiAmount').value = '0.00';
        } else if (type === 'card') {
            if (document.getElementById('cardDepositInto')) document.getElementById('cardDepositInto').value = '';
            if (document.getElementById('cardTransNo')) document.getElementById('cardTransNo').value = '';
            if (document.getElementById('cardNumber')) document.getElementById('cardNumber').value = '';
            if (document.getElementById('cardAmount')) document.getElementById('cardAmount').value = '0.00';
        } else if (type === 'metal-exchange') {
            var meMetalEl = document.getElementById('metalExchangeMetal');
            var meProdInEl = document.getElementById('metalExchangeProductInput');
            var meProdIdEl = document.getElementById('metalExchangeProductId');
            var meProdListEl = document.getElementById('metalExchangeProductList');
            if (meMetalEl) meMetalEl.value = '';
            if (meProdInEl) meProdInEl.value = '';
            if (meProdIdEl) meProdIdEl.value = '';
            if (meProdListEl) {
                meProdListEl.style.display = 'none';
                meProdListEl.innerHTML = '';
            }
            if (document.getElementById('metalExchangeQty')) document.getElementById('metalExchangeQty').value = '1';
            if (document.getElementById('metalExchangePurity')) document.getElementById('metalExchangePurity').value = '1';
            if (document.getElementById('metalExchangeRate')) document.getElementById('metalExchangeRate').value = '0';
            if (document.getElementById('metalExchangeItemCode')) document.getElementById('metalExchangeItemCode').value = '';
            if (document.getElementById('metalExchangeGrossWt')) document.getElementById('metalExchangeGrossWt').value = '0';
            if (document.getElementById('metalExchangePurityWt')) document.getElementById('metalExchangePurityWt').value = '0';
            if (document.getElementById('metalExchangeAmount')) document.getElementById('metalExchangeAmount').value = '0.00';
        } else if (type === 'scrap') {
            var sm = document.getElementById('scrapMetal');
            var spi = document.getElementById('scrapProductInput');
            var spid = document.getElementById('scrapProductId');
            var spl = document.getElementById('scrapProductList');
            if (sm) sm.value = '';
            if (spi) spi.value = '';
            if (spid) spid.value = '';
            if (spl) {
                spl.style.display = 'none';
                spl.innerHTML = '';
            }
            if (document.getElementById('scrapQty')) document.getElementById('scrapQty').value = '1';
            if (document.getElementById('scrapLessWt')) document.getElementById('scrapLessWt').value = '0';
            if (document.getElementById('scrapStoneWt')) document.getElementById('scrapStoneWt').value = '0';
            if (document.getElementById('scrapPurity')) document.getElementById('scrapPurity').value = '1';
            if (document.getElementById('scrapRate')) document.getElementById('scrapRate').value = '0';
            if (document.getElementById('scrapItemCode')) document.getElementById('scrapItemCode').value = '';
            if (document.getElementById('scrapGrossWt')) document.getElementById('scrapGrossWt').value = '0';
            if (document.getElementById('scrapNetWt')) document.getElementById('scrapNetWt').value = '0';
            if (document.getElementById('scrapPurityWt')) document.getElementById('scrapPurityWt').value = '0';
            if (document.getElementById('scrapAmount')) document.getElementById('scrapAmount').value = '0.00';
        }
    }

    function openInvestmentPaymentModal(type) {
        if (typeof window.jQuery === 'undefined') return;
        var modalMap = {
            cash: '#cashPaymentModal',
            bank: '#bankPaymentModal',
            cheque: '#chequePaymentModal',
            upi: '#upiPaymentModal',
            card: '#cardPaymentModal',
            'metal-exchange': '#metalExchangeModal',
            scrap: '#scrapPaymentModal'
        };
        var modalId = modalMap[type];
        if (!modalId) {
            alert('This payment type has no entry form here. Use Cash, Bank, UPI, Card, Cheque, Metal Exchange, or Scrap.');
            return;
        }
        var instEl = document.getElementById('ifRecInstAmt');
        var amtPref = instEl ? parseFloat(instEl.value) || 0 : 0;
        var prefill = amtPref > 0 ? amtPref.toFixed(2) : '0.00';
        if (type === 'cash') {
            var cashAmountEl = document.getElementById('cashAmount');
            if (cashAmountEl) cashAmountEl.value = prefill;
        } else if (type === 'bank') {
            var bankAmountEl = document.getElementById('bankAmount');
            if (bankAmountEl) bankAmountEl.value = prefill;
        } else if (type === 'cheque') {
            var chequeAmountEl = document.getElementById('chequeAmount');
            if (chequeAmountEl) chequeAmountEl.value = prefill;
        } else if (type === 'upi') {
            var upiAmountEl = document.getElementById('upiAmount');
            if (upiAmountEl) upiAmountEl.value = prefill;
        } else if (type === 'card') {
            var cardAmountEl = document.getElementById('cardAmount');
            if (cardAmountEl) cardAmountEl.value = prefill;
        } else if (type === 'metal-exchange') {
            var metalExchangeAmountEl = document.getElementById('metalExchangeAmount');
            if (metalExchangeAmountEl) metalExchangeAmountEl.value = prefill;
        } else if (type === 'scrap') {
            var scrapAmountEl = document.getElementById('scrapAmount');
            if (scrapAmountEl) scrapAmountEl.value = prefill;
        }
        window.jQuery(modalId).modal('show');
    }

    function mapFwDisplayPayToModalType(displayLabel) {
        var x = {
            Cash: 'cash',
            Bank: 'bank',
            Cheque: 'cheque',
            UPI: 'upi',
            Card: 'card',
            Diamond: 'metal-exchange',
            'Gold ring': 'scrap',
            Jewellery: 'scrap'
        };
        return x[displayLabel] || 'cash';
    }

    function getFundWithdrawPrefillAmount() {
        var draft = document.getElementById('fwPaymentDraft');
        if (draft && draft.value.trim() !== '') {
            var d = parseFloat(String(draft.value).replace(/,/g, '')) || 0;
            if (d > 0) return d.toFixed(2);
        }
        var t = document.getElementById('fwTotalAmt');
        if (t) {
            var tv = parseFloat(String(t.value).replace(/,/g, '')) || 0;
            if (tv > 0) return tv.toFixed(2);
        }
        var a = document.getElementById('fwAmount');
        if (a) {
            var av = parseFloat(String(a.value).replace(/,/g, '')) || 0;
            if (av > 0) return av.toFixed(2);
        }
        return '0.00';
    }

    function openFundWithdrawPaymentModal(displayLabel) {
        if (typeof window.jQuery === 'undefined') return;
        var type = mapFwDisplayPayToModalType(displayLabel);
        var modalMap = {
            cash: '#cashPaymentModal',
            bank: '#bankPaymentModal',
            cheque: '#chequePaymentModal',
            upi: '#upiPaymentModal',
            card: '#cardPaymentModal',
            'metal-exchange': '#metalExchangeModal',
            scrap: '#scrapPaymentModal'
        };
        var modalId = modalMap[type];
        if (!modalId) {
            alert('This payment type has no entry form here.');
            return;
        }
        clearIfPaymentModalFields(type);
        var prefill = getFundWithdrawPrefillAmount();
        if (type === 'cash') {
            var cashAmountEl = document.getElementById('cashAmount');
            if (cashAmountEl) cashAmountEl.value = prefill;
        } else if (type === 'bank') {
            var bankAmountEl = document.getElementById('bankAmount');
            if (bankAmountEl) bankAmountEl.value = prefill;
        } else if (type === 'cheque') {
            var chequeAmountEl = document.getElementById('chequeAmount');
            if (chequeAmountEl) chequeAmountEl.value = prefill;
        } else if (type === 'upi') {
            var upiAmountEl = document.getElementById('upiAmount');
            if (upiAmountEl) upiAmountEl.value = prefill;
        } else if (type === 'card') {
            var cardAmountEl = document.getElementById('cardAmount');
            if (cardAmountEl) cardAmountEl.value = prefill;
        } else if (type === 'metal-exchange') {
            var metalExchangeAmountEl = document.getElementById('metalExchangeAmount');
            if (metalExchangeAmountEl) metalExchangeAmountEl.value = prefill;
        } else if (type === 'scrap') {
            var scrapAmountEl = document.getElementById('scrapAmount');
            if (scrapAmountEl) scrapAmountEl.value = prefill;
        }
        window.jQuery(modalId).modal('show');
    }

    function initIfRecPaymentIcons() {
        var row = document.getElementById('ifRecPayMethodRow');
        if (!row || row._ifPaymentIconsBound) return;
        row._ifPaymentIconsBound = true;
        row.addEventListener('click', function (e) {
            var icon = e.target.closest('.payment-icon');
            if (!icon || !row.contains(icon)) return;
            var paymentType = icon.classList.contains('payment-cash')
                ? 'cash'
                : icon.classList.contains('payment-bank')
                  ? 'bank'
                  : icon.classList.contains('payment-cheque')
                    ? 'cheque'
                    : icon.classList.contains('payment-mobile')
                      ? 'upi'
                      : icon.classList.contains('payment-card')
                        ? 'card'
                        : icon.classList.contains('payment-exchange')
                          ? 'metal-exchange'
                          : icon.classList.contains('payment-jewelry')
                            ? 'scrap'
                            : icon.classList.contains('payment-diamond') || icon.classList.contains('payment-stone')
                              ? 'card'
                              : 'other';
            if (paymentType === 'other') {
                openInvestmentPaymentModal('cash');
                return;
            }
            openInvestmentPaymentModal(paymentType);
        });
    }

    function addFundWithdrawGridRowDetailed(opts) {
        var tb = document.getElementById('fwPaymentGridBody');
        if (!tb) return;
        var empty = tb.querySelector('.if-fw-empty-row');
        if (empty) empty.parentNode.removeChild(empty);
        var pt = opts.payType != null ? String(opts.payType) : 'Cash';
        var amtRaw = opts.amount;
        var amtVal =
            amtRaw == null || amtRaw === ''
                ? 0
                : typeof amtRaw === 'number'
                  ? (isNaN(amtRaw) ? 0 : amtRaw)
                  : parseFloat(String(amtRaw).replace(/,/g, '')) || 0;
        var amtStr = amtVal.toFixed(2);
        var diamond = opts.diamond != null ? String(opts.diamond) : '';
        var txn = opts.txn != null ? String(opts.txn) : '';
        var transfer = opts.transfer != null ? String(opts.transfer) : '';
        var chq = opts.chequeDt != null ? String(opts.chequeDt) : '';
        var tr = document.createElement('tr');
        tr.setAttribute('data-fw-row', '1');
        tr.innerHTML =
            '<td><input type="text" class="form-control form-control-sm fw-row-type" value="' +
            escapeHtml(pt) +
            '" readonly tabindex="-1"></td>' +
            '<td><input type="text" class="form-control form-control-sm fw-row-diamond" value="' +
            escapeHtml(diamond) +
            '"></td>' +
            '<td><input type="text" class="form-control form-control-sm fw-row-txn" value="' +
            escapeHtml(txn) +
            '"></td>' +
            '<td><input type="text" class="form-control form-control-sm fw-row-transfer" value="' +
            escapeHtml(transfer) +
            '"></td>' +
            '<td><input type="date" class="form-control form-control-sm fw-row-cheque-dt" value="' +
            escapeHtml(chq) +
            '"></td>' +
            '<td class="text-right"><input type="text" class="form-control form-control-sm text-right fw-row-amt" value="' +
            escapeHtml(amtStr) +
            '"></td>' +
            '<td class="text-center p-1"><button type="button" class="btn btn-sm btn-link text-danger p-0 fw-row-del" title="Remove">&times;</button></td>';
        tb.appendChild(tr);
        tr.querySelector('.fw-row-amt').addEventListener('input', refreshFundWithdrawGridTotal);
        tr.querySelector('.fw-row-del').addEventListener('click', function () {
            tr.parentNode.removeChild(tr);
            if (!tb.querySelector('tr[data-fw-row]')) renderFundWithdrawGridEmpty();
            else refreshFundWithdrawGridTotal();
        });
        refreshFundWithdrawGridTotal();
    }

    function addFundWithdrawGridRowFromPaymentData(paymentData, type) {
        var fwM = document.getElementById('fundWithdrawModal');
        var displayPay = (fwM && fwM.getAttribute('data-fw-pay-display')) || 'Cash';
        var pt;
        if (type === 'metal-exchange' || type === 'scrap') {
            pt = displayPay;
        } else {
            var payLabelMap = { cash: 'Cash', bank: 'Bank', cheque: 'Cheque', upi: 'UPI', card: 'Card' };
            pt = payLabelMap[type] || displayPay;
        }
        var diamondCol = paymentData.diamond_category || '';
        if (type === 'metal-exchange') {
            var pm = [];
            if (paymentData.metal_exchange_product_name) pm.push(paymentData.metal_exchange_product_name);
            if (paymentData.purity_carat) pm.push(paymentData.purity_carat);
            diamondCol = pm.join(' / ');
        } else if (type === 'scrap') {
            var ps = [];
            if (paymentData.scrap_product_name) ps.push(paymentData.scrap_product_name);
            if (paymentData.purity_carat) ps.push(paymentData.purity_carat);
            diamondCol = ps.join(' / ');
        }
        var txn = paymentData.transaction_no || '';
        if (type === 'card' && paymentData.card_no) {
            txn = txn ? txn + ' | ' + paymentData.card_no : String(paymentData.card_no);
        }
        addFundWithdrawGridRowDetailed({
            payType: pt,
            diamond: diamondCol,
            txn: txn,
            transfer: paymentData.deposit_into || '',
            chequeDt: paymentData.cheque_date || '',
            amount: paymentData.amount
        });
    }

    window.savePayment = function (type) {
        var paymentData = {
            type: type,
            deposit_into: '',
            transaction_no: '',
            cheque_date: '',
            card_no: '',
            amount: 0,
            purity_carat: '',
            quantity: 0,
            diamond_category: '',
            q_more: ''
        };
        if (type === 'cash') {
            paymentData.deposit_into = document.getElementById('cashDepositInto')
                ? document.getElementById('cashDepositInto').value
                : '';
            paymentData.amount = parseFloat(document.getElementById('cashAmount').value) || 0;
        } else if (type === 'bank') {
            paymentData.deposit_into = document.getElementById('bankDepositInto').value;
            paymentData.transaction_no = document.getElementById('bankTransNo').value;
            paymentData.amount = parseFloat(document.getElementById('bankAmount').value) || 0;
        } else if (type === 'cheque') {
            paymentData.deposit_into = document.getElementById('chequeDepositInto').value;
            paymentData.transaction_no = document.getElementById('chequeTransNo').value;
            paymentData.cheque_date = document.getElementById('chequeDate').value;
            paymentData.amount = parseFloat(document.getElementById('chequeAmount').value) || 0;
        } else if (type === 'upi') {
            paymentData.deposit_into = document.getElementById('upiDepositInto').value;
            paymentData.transaction_no = document.getElementById('upiTransNo').value;
            paymentData.amount = parseFloat(document.getElementById('upiAmount').value) || 0;
        } else if (type === 'card') {
            paymentData.deposit_into = document.getElementById('cardDepositInto').value;
            paymentData.transaction_no = document.getElementById('cardTransNo').value;
            paymentData.card_no = document.getElementById('cardNumber').value;
            paymentData.amount = parseFloat(document.getElementById('cardAmount').value) || 0;
        } else if (type === 'metal-exchange') {
            paymentData.deposit_into = 'Metal Exchange';
            paymentData.purity_carat = document.getElementById('metalExchangePurity').value;
            paymentData.quantity = parseFloat(document.getElementById('metalExchangeQty').value) || 0;
            paymentData.amount = parseFloat(document.getElementById('metalExchangeAmount').value) || 0;
            var meMetal = document.getElementById('metalExchangeMetal');
            var meProdIn = document.getElementById('metalExchangeProductInput');
            var meProdId = document.getElementById('metalExchangeProductId');
            paymentData.metal_exchange_metal_id = meMetal && meMetal.value ? meMetal.value : '';
            paymentData.metal_exchange_product_id = meProdId && meProdId.value ? meProdId.value : '';
            paymentData.metal_exchange_product_name = meProdIn && meProdIn.value ? meProdIn.value : '';
            var meGw = document.getElementById('metalExchangeGrossWt');
            var meIc = document.getElementById('metalExchangeItemCode');
            var meRt = document.getElementById('metalExchangeRate');
            var mePw = document.getElementById('metalExchangePurityWt');
            paymentData.metal_exchange_gross_wt = meGw ? meGw.value : '0';
            paymentData.metal_exchange_item_code = meIc ? meIc.value : '';
            paymentData.metal_exchange_rate = meRt ? meRt.value : '0';
            paymentData.metal_exchange_purity_wt = mePw ? mePw.value : '0';
        } else if (type === 'scrap') {
            paymentData.deposit_into = 'Scrap';
            paymentData.purity_carat = document.getElementById('scrapPurity').value;
            paymentData.quantity = parseFloat(document.getElementById('scrapQty').value) || 0;
            paymentData.amount = parseFloat(document.getElementById('scrapAmount').value) || 0;
            paymentData.scrap_metal_id =
                document.getElementById('scrapMetal') && document.getElementById('scrapMetal').value
                    ? document.getElementById('scrapMetal').value
                    : '';
            paymentData.scrap_product_id =
                document.getElementById('scrapProductId') && document.getElementById('scrapProductId').value
                    ? document.getElementById('scrapProductId').value
                    : '';
            paymentData.scrap_product_name =
                document.getElementById('scrapProductInput') && document.getElementById('scrapProductInput').value
                    ? document.getElementById('scrapProductInput').value
                    : '';
            paymentData.scrap_gross_wt =
                document.getElementById('scrapGrossWt') && document.getElementById('scrapGrossWt').value
                    ? document.getElementById('scrapGrossWt').value
                    : '0';
            paymentData.scrap_less_wt =
                document.getElementById('scrapLessWt') && document.getElementById('scrapLessWt').value
                    ? document.getElementById('scrapLessWt').value
                    : '0';
            paymentData.scrap_stone_wt =
                document.getElementById('scrapStoneWt') && document.getElementById('scrapStoneWt').value
                    ? document.getElementById('scrapStoneWt').value
                    : '0';
            paymentData.scrap_net_wt =
                document.getElementById('scrapNetWt') && document.getElementById('scrapNetWt').value
                    ? document.getElementById('scrapNetWt').value
                    : '0';
            paymentData.scrap_purity_wt =
                document.getElementById('scrapPurityWt') && document.getElementById('scrapPurityWt').value
                    ? document.getElementById('scrapPurityWt').value
                    : '0';
            paymentData.scrap_rate =
                document.getElementById('scrapRate') && document.getElementById('scrapRate').value
                    ? document.getElementById('scrapRate').value
                    : '0';
            paymentData.scrap_item_code =
                document.getElementById('scrapItemCode') && document.getElementById('scrapItemCode').value
                    ? document.getElementById('scrapItemCode').value
                    : '';
        }
        if (paymentData.amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        var fwModalEl = document.getElementById('fundWithdrawModal');
        if (fwModalEl && fwModalEl.getAttribute('data-fw-payment-flow') === '1') {
            addFundWithdrawGridRowFromPaymentData(paymentData, type);
            fwModalEl.removeAttribute('data-fw-payment-flow');
            hideIfPaymentModalByType(type);
            clearIfPaymentModalFields(type);
            return;
        }
        ifRecPaymentDraft.push(paymentData);
        renderIfRecBreakdownFromDraft();
        syncIfRecPayIconActive(type);
        hideIfPaymentModalByType(type);
        clearIfPaymentModalFields(type);
    };

    var IF_DETAIL_ATTACH_MAX_FILES = 5;
    var IF_DETAIL_ATTACH_MAX_BYTES = 2 * 1024 * 1024;

    function getDetailFooterImages(rec) {
        if (!rec || !Array.isArray(rec.detail_footer_images)) return [];
        return rec.detail_footer_images;
    }

    function refreshDetailFooterAttachUi(rec) {
        var sumEl = document.getElementById('ifDetailInstAttachSummary');
        var clr = document.getElementById('ifDetailInstAttachClear');
        var imgs = getDetailFooterImages(rec);
        if (sumEl) {
            if (!imgs.length) {
                sumEl.textContent = '';
            } else if (imgs.length === 1) {
                sumEl.textContent = imgs[0].name || '1 image';
            } else {
                sumEl.textContent = imgs.length + ' images';
            }
        }
        if (clr) clr.classList.toggle('d-none', !imgs.length);
    }

    function persistCurrentFundRecord(rec) {
        if (!rec || rec.id == null) return;
        var list = loadFundRecords();
        var ix = list.findIndex(function (x) { return String(x.id) === String(rec.id); });
        if (ix >= 0) {
            list[ix] = rec;
            saveFundRecords(list);
        }
    }

    function updateDetailInstFooter(rec) {
        var el = document.getElementById('ifDetailInstFooter');
        var stats = document.getElementById('ifDetailInstFooterStats');
        var tbl = document.getElementById('dvInstallmentTable');
        if (!el || !stats || !tbl) return;
        var n = getPassbookPeriodCount(rec);
        var per = parseFloat(rec.inst_amt);
        if (isNaN(per)) per = 0;
        var t = recalcFundPassbookTotals(rec, n);
        var totalDue = n * per;
        var rem = Math.max(0, totalDue - t.paidAmt);
        el.style.display = 'flex';
        stats.innerHTML =
            '<span class="if-dif-item"><span class="if-dif-label">Paid Inst.</span><span class="if-dif-val">' +
            t.paid +
            '/' +
            n +
            '</span></span>' +
            '<span class="if-dif-item"><span class="if-dif-label">Paid Amount</span><span class="if-dif-val">' +
            formatMoney(t.paidAmt) +
            '</span></span>' +
            '<span class="if-dif-item"><span class="if-dif-label">Remaining Amt.</span><span class="if-dif-val">' +
            formatMoney(rem) +
            '</span></span>' +
            '<span class="if-dif-item"><span class="if-dif-label">Total Amount</span><span class="if-dif-val">' +
            formatMoney(totalDue) +
            '</span></span>' +
            '<span class="if-dif-item"><span class="if-dif-label">Gold Wt.</span><span class="if-dif-val">' +
            (isNaN(t.goldWt) ? '0' : String(t.goldWt)) +
            '</span></span>';
        refreshDetailFooterAttachUi(rec);
    }

    function setDetailViewToggleUi(isPassbook) {
        var n = document.getElementById('dvViewNormal');
        var p = document.getElementById('dvViewPassbook');
        if (!n || !p) return;
        if (isPassbook) {
            p.classList.add('btn-primary');
            p.classList.remove('btn-outline-secondary');
            n.classList.remove('btn-primary');
            n.classList.add('btn-outline-secondary');
        } else {
            n.classList.add('btn-primary');
            n.classList.remove('btn-outline-secondary');
            p.classList.remove('btn-primary');
            p.classList.add('btn-outline-secondary');
        }
        refreshDetailMoreBtnVisibility();
    }

    function renderDetailInstallmentTable(rec) {
        var head = document.getElementById('dvInstallmentTableHead');
        var tb = document.getElementById('dvInstallmentTableBody');
        var tbl = document.getElementById('dvInstallmentTable');
        if (!head || !tb || !tbl) return;
        var locked = !!(rec && rec.fund_transfer_done);
        var dis = locked ? ' disabled' : '';
        tbl.classList.toggle('if-passbook-mode', ifDetailInstMode === 'passbook');
        if (ifDetailInstMode === 'normal') {
            head.innerHTML = detailInstNormalThead();
            refreshDetailMoreBtnVisibility();
            var raw = rec.installments || [];
            var visible = [];
            for (var ji = 0; ji < raw.length; ji++) {
                if (isInstallmentRowMeaningful(raw[ji])) {
                    visible.push({ row: raw[ji], schedIx: ji });
                }
            }
            tb.innerHTML = '';
            if (!visible.length) {
                tb.innerHTML =
                    '<tr><td colspan="13" class="text-center text-muted py-4">No Rows To Show</td></tr>';
            } else {
                visible.forEach(function (item, i) {
                    var row = item.row;
                    var schedIx = item.schedIx;
                    if (row.inst_no != null && String(row.inst_no).trim() !== '') {
                        var pn = parseInt(row.inst_no, 10);
                        if (!isNaN(pn) && pn >= 1) schedIx = pn - 1;
                    }
                    var tr = document.createElement('tr');
                    var payDisp = row.pay_date ? formatDateDDMMYYYY(row.pay_date) : '';
                    var entDisp = row.entry_date ? formatDateDDMMYYYY(row.entry_date) : '';
                    var pdesc = row.payment_desc || row.inst_desc || '';
                    tr.innerHTML =
                        '<td>' +
                        (i + 1) +
                        '</td><td>' +
                        escapeHtml(formatInstallmentWeekdayCell(row, rec, schedIx)) +
                        '</td><td>' +
                        escapeHtml(pdesc) +
                        '</td><td>' +
                        escapeHtml(payDisp || row.pay_date || '') +
                        '</td><td>' +
                        escapeHtml(formatDetailMoney2(row.amount)) +
                        '</td><td>' +
                        escapeHtml(formatDetailMoney2(row.gold_rate)) +
                        '</td><td>' +
                        escapeHtml(formatDetailGoldWt3(row.gold_wt)) +
                        '</td><td>' +
                        escapeHtml(row.entry_by || '') +
                        '</td><td>' +
                        escapeHtml(formatDetailMoney2(row.tax)) +
                        '</td><td>' +
                        escapeHtml(formatDetailMoney2(row.tax_pct)) +
                        '</td><td>' +
                        escapeHtml(formatDetailMoney2(row.taxable)) +
                        '</td><td>' +
                        escapeHtml(entDisp || row.entry_date || '') +
                        '</td><td class="if-action-btns if-detail-normal-actions">' +
                        '<div class="if-detail-action-cell">' +
                        '<button type="button" class="btn btn-sm btn-icon if-btn-normal-edit"' +
                        dis +
                        ' title="Edit / Receipt" data-schedule-index="' +
                        schedIx +
                        '"><i class="feather icon-edit-2"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-icon if-btn-normal-print"' +
                        dis +
                        ' title="Print" data-schedule-index="' +
                        schedIx +
                        '"><i class="feather icon-printer"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-icon btn-icon-danger if-btn-normal-clear"' +
                        dis +
                        ' title="Clear installment" data-schedule-index="' +
                        schedIx +
                        '"><i class="feather icon-trash-2"></i></button>' +
                        '</div></td>';
                    tb.appendChild(tr);
                });
            }
            updateDetailInstFooter(rec);
            return;
        }
        head.innerHTML = detailInstPassbookThead();
        refreshDetailMoreBtnVisibility();
        var n = getPassbookPeriodCount(rec);
        tb.innerHTML = '';
        for (var i = 0; i < n; i++) {
            var sched = passbookScheduleDateYmd(rec, i);
            var row = getInstallmentSlot(rec, i, n);
            var payDisp = row.pay_date ? formatDateDDMMYYYY(row.pay_date) : '';
            var entDisp = row.entry_date ? formatDateDDMMYYYY(row.entry_date) : '';
            var hasPaid =
                (row.pay_date && String(row.pay_date).trim() !== '') ||
                (row.amount != null && String(row.amount).trim() !== '' && parseFloat(row.amount) > 0);
            var amt = '0.00';
            if (hasPaid && row.amount != null && String(row.amount).trim() !== '') {
                amt = Number(row.amount).toFixed(2);
            }
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' +
                (i + 1) +
                '</td><td>' +
                escapeHtml(formatDateDMY(sched) || '—') +
                '</td><td>' +
                escapeHtml(payDisp || '') +
                '</td><td>' +
                escapeHtml(row.pay_mode || '') +
                '</td><td>' +
                escapeHtml(amt !== undefined && amt !== null ? String(amt) : '0.00') +
                '</td><td>' +
                escapeHtml(formatDetailMoney2(row.gold_rate)) +
                '</td><td>' +
                escapeHtml(formatDetailGoldWt3(row.gold_wt)) +
                '</td><td>' +
                escapeHtml(row.entry_by || '') +
                '</td><td>' +
                escapeHtml(formatDetailMoney2(row.tax)) +
                '</td><td>' +
                escapeHtml(formatDetailMoney2(row.tax_pct)) +
                '</td><td>' +
                escapeHtml(formatDetailMoney2(row.taxable)) +
                '</td><td>' +
                escapeHtml(entDisp || '') +
                '</td><td class="if-action-btns">' +
                '<button type="button" class="btn btn-sm btn-icon if-btn-pay"' +
                dis +
                ' title="Payment" data-row-index="' +
                i +
                '"><i class="feather icon-credit-card"></i></button>' +
                '<button type="button" class="btn btn-sm btn-icon if-btn-receipt"' +
                dis +
                ' title="Receipt" data-row-index="' +
                i +
                '" data-sched-ymd="' +
                escapeHtml(sched) +
                '"><i class="feather icon-file-text"></i></button>' +
                '<button type="button" class="btn btn-sm btn-icon btn-icon-danger if-btn-row-del"' +
                dis +
                ' title="Clear" data-row-index="' +
                i +
                '"><i class="feather icon-trash-2"></i></button>' +
                '</td>';
            tb.appendChild(tr);
        }
        updateDetailInstFooter(rec);
    }

    function openInvestmentReceiptModal(rowIndex, schedYmd, rec, mode) {
        var m = mode || 'passbook';
        var n = getPassbookPeriodCount(rec);
        var ix = rowIndex;
        if (ix < 0 || ix >= n) ix = 0;
        var sched = schedYmd || passbookScheduleDateYmd(rec, ix);
        ifReceiptCtx = {
            fundId: rec.id,
            rowIndex: ix,
            periodCount: n,
            schedYmd: sched,
            mode: m
        };
        syncReceiptModalLayout(m);
        if (m === 'normal') {
            renderIfRecInstMonthCheckboxes(rec, [ix], true);
        }
        applyReceiptFormFromRow(ix, sched, rec);
        if (m === 'normal') {
            updateIfRecInstMonthSummary();
            syncIfRecInstMonthSelectAllState();
        }
        if (window.jQuery) {
            window.jQuery('#investmentReceiptModal').modal('show');
        }
    }

    function postInvestmentInstallmentsToLedger(rec, installmentPayloads, doneCb) {
        var custName = String(rec.customer_name || '').trim();
        var fundNo = String(rec.fund_no || '').trim();
        if (!custName || !fundNo || !installmentPayloads || !installmentPayloads.length) {
            if (typeof doneCb === 'function') doneCb({ ok: false, skipped: true });
            return;
        }
        var ifAdminPath = window.location.pathname.replace(/[^/]*$/, '');
        var url = ifAdminPath + 'ajax/post-investment-fund-installment-ledger.php';
        var body = {
            customer_id: parseInt(rec.customer_id, 10) || 0,
            customer_name: custName,
            fund_no: fundNo,
            fund_local_id: String(rec.id || ''),
            entry_by: (installmentPayloads[0] && installmentPayloads[0].entry_by) || '',
            installments: installmentPayloads
        };
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text) : null;
                    } catch (e) {
                        data = null;
                    }
                    if (!r.ok) {
                        throw new Error((text && text.slice(0, 400)) || ('HTTP ' + r.status));
                    }
                    if (!data || typeof data !== 'object') {
                        throw new Error((text && text.slice(0, 400)) || 'Invalid response from server');
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (typeof doneCb === 'function') doneCb(data);
            })
            .catch(function (err) {
                if (typeof doneCb === 'function') {
                    doneCb({
                        ok: false,
                        message: (err && err.message) || 'Could not post installment to transaction report / ledger.'
                    });
                }
            });
    }

    function saveInvestmentReceiptFromModal() {
        var id = ifReceiptCtx.fundId;
        if (id == null || id === '') return;
        var list = loadFundRecords();
        var rec = list.filter(function (x) { return String(x.id) === String(id); })[0];
        if (!rec) return;
        var mode = ifReceiptCtx.mode || 'passbook';
        var n = ifReceiptCtx.periodCount || getPassbookPeriodCount(rec);
        var ix;
        var schedYmd;
        var targetIndices;
        if (mode === 'normal') {
            targetIndices = getIfRecSelectedMonthIndices();
            if (!targetIndices.length) {
                alert('Please select at least one Inst. Month.');
                return;
            }
        } else {
            ix = ifReceiptCtx.rowIndex;
            schedYmd = ifReceiptCtx.schedYmd || passbookScheduleDateYmd(rec, ix);
            targetIndices = [ix];
        }
        ensureInstallmentsLength(rec, n);
        var payDate = document.getElementById('ifRecPayDate').value;
        var payMode = '';
        var activeIcon = document.querySelector('#ifRecPayMethodRow .payment-icon.active');
        if (activeIcon) payMode = activeIcon.getAttribute('data-pm') || '';
        if (!payMode && ifRecPaymentDraft.length) {
            payMode = paymentTypeDisplayLabel(ifRecPaymentDraft[ifRecPaymentDraft.length - 1].type);
        }
        var paymentBreakdownSnapshot = ifRecPaymentDraft.map(function (p) {
            return Object.assign({}, p);
        });
        var shareCount = targetIndices.length;
        var instAmtTotal = parseFloat(document.getElementById('ifRecInstAmt').value) || 0;
        var instAmtEach = shareCount > 0 ? instAmtTotal / shareCount : 0;
        var taxTotal = parseFloat(document.getElementById('ifRecTaxAmt').value) || 0;
        var taxEach = shareCount > 0 ? taxTotal / shareCount : 0;
        var gwTotal = parseFloat(document.getElementById('ifRecGoldWt').value) || 0;
        var gwEach = shareCount > 0 ? gwTotal / shareCount : 0;
        var brkScale = shareCount > 0 ? 1 / shareCount : 1;
        var entryByVal = document.getElementById('ifRecEntryBy').value;
        var goldRateVal = document.getElementById('ifRecGoldRate').value;
        var ledgerPayloads = [];
        var saveBtn = document.getElementById('ifRecBtnSave');
        var prevDis = saveBtn ? saveBtn.disabled : false;
        if (saveBtn) saveBtn.disabled = true;
        for (var t = 0; t < targetIndices.length; t++) {
            ix = targetIndices[t];
            if (ix < 0 || ix >= n) continue;
            schedYmd = passbookScheduleDateYmd(rec, ix);
            var prevSlot = rec.installments[ix] || {};
            var instNo = ix + 1;
            var txnNo = (rec.fund_no || 'IF') + '-I' + instNo;
            var payDesc = prevSlot.payment_desc || installmentOrdinalLabel(ix);
            var merged = Object.assign({}, prevSlot, {
                inst_no: String(instNo),
                inst_date: schedYmd || prevSlot.inst_date || '',
                pay_date: payDate,
                pay_mode: payMode,
                payment_breakdown: paymentBreakdownSnapshot.map(function (p) {
                    var o = Object.assign({}, p);
                    o.amount = Math.round((parseFloat(p.amount) || 0) * brkScale * 100) / 100;
                    return o;
                }),
                amount: instAmtEach.toFixed(2),
                gold_rate: goldRateVal,
                gold_wt: gwEach.toFixed(3),
                entry_by: entryByVal,
                tax: taxEach.toFixed(2),
                tax_pct: prevSlot.tax_pct != null && prevSlot.tax_pct !== '' ? prevSlot.tax_pct : '0',
                taxable: prevSlot.taxable != null && prevSlot.taxable !== '' ? prevSlot.taxable : '0',
                entry_date: payDate || new Date().toISOString().slice(0, 10),
                receipt_no: '',
                product: '',
                payment_desc: payDesc,
                transaction_no: txnNo
            });
            rec.installments[ix] = merged;
            ledgerPayloads.push({
                inst_no: instNo,
                inst_index: ix,
                amount: instAmtEach,
                tax: taxEach,
                pay_date: payDate,
                pay_mode: payMode,
                gold_wt: gwEach,
                gold_rate: parseFloat(goldRateVal) || 0,
                entry_by: entryByVal,
                payment_desc: payDesc,
                transaction_no: txnNo
            });
        }
        function finishLocalSave(ledgerData) {
            if (ledgerData && ledgerData.ok && Array.isArray(ledgerData.posted)) {
                ledgerData.posted.forEach(function (p) {
                    var pNo = parseInt(p.inst_no, 10) || 0;
                    if (pNo <= 0 || pNo > n) return;
                    var slot = rec.installments[pNo - 1] || {};
                    slot.ledger_posted = true;
                    slot.ledger_id = p.ledger_id || 0;
                    slot.transaction_no = p.transaction_no || slot.transaction_no || '';
                    rec.installments[pNo - 1] = slot;
                });
            }
            recalcFundPassbookTotals(rec, n);
            rec.saved_at = new Date().toISOString();
            var lix = list.findIndex(function (x) { return String(x.id) === String(id); });
            if (lix >= 0) list[lix] = rec;
            saveFundRecords(list);
            if (window.jQuery) window.jQuery('#investmentReceiptModal').modal('hide');
            renderDetailInstallmentTable(rec);
            renderFundList();
            if (saveBtn) saveBtn.disabled = prevDis;
            if (ledgerData && !ledgerData.ok && !ledgerData.skipped) {
                alert(
                    (ledgerData.message || 'Installment saved locally, but could not post to Transaction Report / ledger.') +
                        '\nLocal installment data was kept.'
                );
            }
        }
        postInvestmentInstallmentsToLedger(rec, ledgerPayloads, finishLocalSave);
    }

    function clearPassbookRowPayment(rowIndex) {
        var rec = getCurrentFundRecord();
        if (!rec) return;
        var n = getPassbookPeriodCount(rec);
        ensureInstallmentsLength(rec, n);
        var prev = rec.installments[rowIndex] || {};
        var txnNo = String(prev.transaction_no || ((rec.fund_no || 'IF') + '-I' + (rowIndex + 1)));
        rec.installments[rowIndex] = {};
        recalcFundPassbookTotals(rec, n);
        rec.saved_at = new Date().toISOString();
        var list = loadFundRecords();
        var lix = list.findIndex(function (x) { return String(x.id) === String(rec.id); });
        if (lix >= 0) list[lix] = rec;
        saveFundRecords(list);
        renderDetailInstallmentTable(rec);
        renderFundList();
        if (prev.ledger_posted || txnNo) {
            var ifAdminPath = window.location.pathname.replace(/[^/]*$/, '');
            fetch(ifAdminPath + 'ajax/post-investment-fund-installment-ledger.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'void',
                    transaction_no: txnNo,
                    fund_no: rec.fund_no || '',
                    customer_name: rec.customer_name || ''
                })
            }).catch(function () { /* best-effort void */ });
        }
    }

    function formatInstTypeDisplay(v) {
        if (!v) return '—';
        var m = { monthly: 'Monthly', weekly: 'Weekly', daily: 'Daily', lump: 'Lump sum' };
        var k = String(v).toLowerCase();
        return m[k] || v;
    }

    var ifReportSelectedParty = null;
    var ifReportSelectedFundId = null;
    var ifReportSearchTimer = null;

    function ifReportPartyKey(r) {
        var n = String(r.customer_name || '').trim();
        return n === '' ? '—' : n;
    }

    function getInstallmentReportPartyAggregates(records) {
        var map = {};
        records.forEach(function (r) {
            var k = ifReportPartyKey(r);
            map[k] = (map[k] || 0) + 1;
        });
        return Object.keys(map)
            .sort(function (a, b) {
                return a.localeCompare(b);
            })
            .map(function (name) {
                return { name: name, count: map[name] };
            });
    }

    function updateIfReportFilterBadge() {
        var inp = document.getElementById('ifReportSearchMain');
        var bd = document.getElementById('ifReportFilterBadge');
        if (!bd) return;
        var q = inp && inp.value.trim();
        if (q) {
            bd.textContent = '1';
            bd.classList.remove('d-none');
        } else {
            bd.classList.add('d-none');
        }
    }

    function filterInstallmentReportRecords(records) {
        var party = ifReportSelectedParty;
        var q =
            (document.getElementById('ifReportSearchMain') &&
                document.getElementById('ifReportSearchMain').value.trim().toLowerCase()) ||
            '';
        return records.filter(function (r) {
            if (party != null && party !== '') {
                if (ifReportPartyKey(r) !== party) return false;
            }
            if (!q) return true;
            var hay = [
                r.customer_name,
                r.contact_no,
                r.email,
                r.scheme_label,
                r.sales_person,
                r.fund_no,
                r.redemption_on
            ]
                .map(function (x) {
                    return String(x || '').toLowerCase();
                })
                .join(' ');
            return hay.indexOf(q) !== -1;
        });
    }

    function ifReportRedemptionCell(val) {
        if (val == null || String(val).trim() === '') return '—';
        var s = String(val).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return formatDateDDMMYYYY(s);
        return s;
    }

    function renderInstallmentReportPartyList() {
        var records = loadFundRecords();
        var agg = getInstallmentReportPartyAggregates(records);
        var filtEl = document.getElementById('ifReportPartyFilter');
        var filt = (filtEl && filtEl.value.trim().toLowerCase()) || '';
        var host = document.getElementById('ifReportPartyBody');
        var footer = document.getElementById('ifReportPartyFooter');
        if (!host) return;
        host.innerHTML = '';
        var allTr = document.createElement('tr');
        allTr.className =
            'if-report-party-row' + (ifReportSelectedParty === null ? ' if-report-party-row--active' : '');
        allTr.setAttribute('data-party', '*');
        allTr.innerHTML =
            '<td><strong>All</strong></td><td class="text-right"><strong>' +
            records.length +
            '</strong></td>';
        host.appendChild(allTr);
        var visible = 1;
        agg.forEach(function (item) {
            if (filt && item.name.toLowerCase().indexOf(filt) === -1) return;
            var tr = document.createElement('tr');
            tr.className =
                'if-report-party-row' +
                (ifReportSelectedParty === item.name ? ' if-report-party-row--active' : '');
            tr.setAttribute('data-party', item.name);
            tr.innerHTML =
                '<td>' +
                escapeHtml(item.name) +
                '</td><td class="text-right">' +
                item.count +
                '</td>';
            host.appendChild(tr);
            visible++;
        });
        if (footer) {
            footer.textContent = 'Showing ' + visible + ' entr' + (visible === 1 ? 'y' : 'ies');
        }
    }

    function hideReportDetailCard() {
        var card = document.getElementById('ifReportDetailCard');
        if (card) card.classList.add('d-none');
        ifReportSelectedFundId = null;
        var tb = document.getElementById('ifReportMainBody');
        if (tb) {
            tb.querySelectorAll('tr.if-report-row--selected').forEach(function (row) {
                row.classList.remove('if-report-row--selected');
            });
        }
    }

    function showReportDetailCard() {
        var card = document.getElementById('ifReportDetailCard');
        if (card) card.classList.remove('d-none');
    }

    function setReportDg(id, text, forceEmpty) {
        var el = document.getElementById(id);
        if (!el) return;
        var t = text != null && String(text).trim() !== '' ? String(text) : '—';
        if (forceEmpty) t = '—';
        el.textContent = t;
        el.classList.toggle('if-report-dg-empty', t === '—');
    }

    function populateReportDetailView(rec) {
        setReportDg('ifReportDvCustomerName', rec.customer_name);
        setReportDg('ifReportDvLocation', extractLocation(rec.address));
        setReportDg('ifReportDvPhone', rec.contact_no);
        setReportDg('ifReportDvSchemeName', rec.scheme_label);
        setReportDg('ifReportDvJoiningDt', rec.joining_date ? formatDateDisplay(rec.joining_date) : null);
        setReportDg('ifReportDvMaturityDt', rec.maturity_date ? formatDateDisplay(rec.maturity_date) : null);
        setReportDg('ifReportDvAmount', formatMoney(rec.inst_amt));
        setReportDg('ifReportDvInstType', formatInstTypeDisplay(rec.inst_type));
        var dur = rec.duration;
        if (!dur || String(dur).trim() === '') {
            var tot = rec.total_installments != null ? rec.total_installments : 12;
            dur = tot + ' Months';
        }
        setReportDg('ifReportDvDuration', dur);
        var red = rec.redemption_on;
        if (!red || String(red).trim() === '') {
            red = 'Amount (24k)';
        } else if (/^\d{4}-\d{2}-\d{2}$/.test(String(red).trim())) {
            red = ifReportRedemptionCell(red);
        }
        setReportDg('ifReportDvRedemption', red);
        setReportDg('ifReportDvAdvancedPayment', null, true);
        setReportDg('ifReportDvNominee', rec.nominee_name, !rec.nominee_name);
        setReportDg('ifReportDvEmail', rec.email, !rec.email);
        setReportDg('ifReportDvContactNo', rec.contact_no, !rec.contact_no);
        setReportDg('ifReportDvRelationType', rec.relation_type, !rec.relation_type);
        setReportDg('ifReportDvNationalId', rec.national_id, !rec.national_id);
    }

    function ifReportMainCellOpensDetail(td) {
        if (!td || td.parentElement.tagName !== 'TR') return false;
        var ci = td.cellIndex;
        return ci === 0 || ci === 3;
    }

    function openInstallmentEntryFromReport(rec) {
        if (!rec) return;
        selectedFundListId = rec.id;
        document.getElementById('currentFundRecordId').value = rec.id;
        document.getElementById('fundNoDisplay').textContent = rec.fund_no || '';
        populateDetailView(rec);
        setInvestmentMainView('detail');
        var a = document.getElementById('ifTabInstallmentEntry');
        if (a && window.jQuery) window.jQuery(a).tab('show');
        renderFundList();
    }

    function renderInstallmentReportMain() {
        var records = loadFundRecords().slice().sort(function (a, b) {
            return new Date(b.saved_at || 0) - new Date(a.saved_at || 0);
        });
        records = filterInstallmentReportRecords(records);
        var tb = document.getElementById('ifReportMainBody');
        var foot = document.getElementById('ifReportMainFooter');
        if (!tb) return;
        tb.innerHTML = '';
        if (!records.length) {
            tb.innerHTML =
                '<tr><td colspan="12" class="text-center text-muted py-4">No Rows To Show</td></tr>';
            if (foot) foot.textContent = '';
            hideReportDetailCard();
            return;
        }
        records.forEach(function (r) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-fund-id', r.id);
            var paid = r.paid_installments != null ? r.paid_installments : 0;
            var tot = r.total_installments != null ? r.total_installments : 0;
            tr.innerHTML =
                '<td class="if-report-link-cell">' +
                escapeHtml(r.customer_name || '—') +
                '</td><td>' +
                escapeHtml(r.contact_no || '—') +
                '</td><td>' +
                escapeHtml(r.email || '—') +
                '</td><td class="if-report-link-cell">' +
                escapeHtml(r.scheme_label || '—') +
                '</td><td>' +
                escapeHtml(r.sales_person || '—') +
                '</td><td>' +
                escapeHtml(formatInstTypeDisplay(r.inst_type)) +
                '</td><td>' +
                escapeHtml(ifReportRedemptionCell(r.redemption_on)) +
                '</td><td>' +
                escapeHtml(r.joining_date ? formatDateDDMMYYYY(r.joining_date) : '—') +
                '</td><td>' +
                escapeHtml(r.maturity_date ? formatDateDDMMYYYY(r.maturity_date) : '—') +
                '</td><td>' +
                escapeHtml(r.fund_no || '—') +
                '</td><td>' +
                escapeHtml(String(paid) + '/' + String(tot)) +
                '</td><td class="text-right">' +
                escapeHtml(formatDetailMoney2(r.inst_amt)) +
                '</td>';
            if (ifReportSelectedFundId != null && String(r.id) === String(ifReportSelectedFundId)) {
                tr.classList.add('if-report-row--selected');
            }
            tb.appendChild(tr);
        });
        if (foot) {
            var n = records.length;
            foot.textContent =
                n === 0 ? 'Showing 0 to 0 of 0 entries' : 'Showing 1 to ' + n + ' of ' + n + ' entries';
        }
        if (ifReportSelectedFundId != null) {
            var sel = records.filter(function (x) {
                return String(x.id) === String(ifReportSelectedFundId);
            })[0];
            if (!sel) {
                hideReportDetailCard();
            } else {
                var card = document.getElementById('ifReportDetailCard');
                if (card && !card.classList.contains('d-none')) {
                    populateReportDetailView(sel);
                }
            }
        }
    }

    function renderInstallmentReport() {
        updateIfReportFilterBadge();
        renderInstallmentReportPartyList();
        renderInstallmentReportMain();
    }

    function exportInstallmentReportCsv() {
        var records = filterInstallmentReportRecords(
            loadFundRecords().slice().sort(function (a, b) {
                return new Date(b.saved_at || 0) - new Date(a.saved_at || 0);
            })
        );
        var headers = [
            'Customer',
            'Mobile',
            'Email',
            'Scheme Name',
            'Sale Person',
            'Inst. Type',
            'Redemption',
            'Joining',
            'Maturity',
            'Fund No',
            'Paid',
            'Inst. Amt'
        ];
        var lines = [headers.join(',')];
        records.forEach(function (r) {
            var paid = r.paid_installments != null ? r.paid_installments : 0;
            var tot = r.total_installments != null ? r.total_installments : 0;
            var row = [
                r.customer_name,
                r.contact_no,
                r.email,
                r.scheme_label,
                r.sales_person,
                formatInstTypeDisplay(r.inst_type),
                String(r.redemption_on || ''),
                r.joining_date || '',
                r.maturity_date || '',
                r.fund_no,
                paid + '/' + tot,
                r.inst_amt != null ? r.inst_amt : ''
            ].map(function (cell) {
                var s = String(cell == null ? '' : cell).replace(/"/g, '""');
                return '"' + s + '"';
            });
            lines.push(row.join(','));
        });
        var blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'installment-report.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    }

    function formatMoney(n) {
        if (n == null || n === '') return '—';
        var x = Number(n);
        if (isNaN(x)) return '—';
        return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function extractLocation(addr) {
        if (!addr || !String(addr).trim()) return '—';
        return String(addr).split(/\n|\r/)[0].trim() || '—';
    }

    function setDg(id, text, forceEmpty) {
        var el = document.getElementById(id);
        if (!el) return;
        var t = text != null && String(text).trim() !== '' ? String(text) : '—';
        if (forceEmpty) t = '—';
        el.textContent = t;
        el.classList.toggle('if-dg-empty', t === '—');
    }

    function populateDetailView(rec) {
        setDg('dvCustomerName', rec.customer_name);
        setDg('dvLocation', extractLocation(rec.address));
        setDg('dvPhone', rec.contact_no);
        setDg('dvSchemeName', rec.scheme_label);
        setDg('dvJoiningDt', formatDateDisplay(rec.joining_date));
        setDg('dvMaturityDt', formatDateDisplay(rec.maturity_date));
        setDg('dvAmount', formatMoney(rec.inst_amt));
        setDg('dvInstType', formatInstTypeDisplay(rec.inst_type));
        var dur = rec.duration;
        if (!dur || String(dur).trim() === '') {
            var tot = rec.total_installments != null ? rec.total_installments : 12;
            dur = tot + ' Months';
        }
        setDg('dvDuration', dur);
        var red = rec.redemption_on;
        if (!red || String(red).trim() === '') {
            red = 'Advanced Payment: Amount (24k)';
        }
        setDg('dvRedemption', red);
        setDg('dvNominee', rec.nominee_name, !rec.nominee_name);
        setDg('dvEmail', rec.email, !rec.email);
        setDg('dvContactNo', rec.contact_no, !rec.contact_no);
        setDg('dvRelationType', rec.relation_type, !rec.relation_type);
        setDg('dvNationalId', rec.national_id, !rec.national_id);
        var ftBadge = document.getElementById('dvFundTransferBadge');
        var ftNoEl = document.getElementById('dvFundTransferFtNo');
        if (rec.fund_transfer_done) {
            if (ftNoEl) ftNoEl.textContent = deriveFtNoFromFundNo(rec.fund_no);
            if (ftBadge) ftBadge.classList.remove('d-none');
        } else {
            if (ftBadge) ftBadge.classList.add('d-none');
        }
        applyFundTransferLockUi(rec);
        ifDetailInstMode = 'normal';
        setDetailViewToggleUi(false);
        renderDetailInstallmentTable(rec);
    }

    function getCurrentFundRecord() {
        var id = document.getElementById('currentFundRecordId').value;
        if (!id) return null;
        return loadFundRecords().filter(function (x) { return String(x.id) === String(id); })[0] || null;
    }

    function renderFundList() {
        var host = document.getElementById('investmentListBody');
        var info = document.getElementById('ifFundListInfo');
        if (!host) return;
        var party = (document.getElementById('filterParty') && document.getElementById('filterParty').value.trim().toLowerCase()) || '';
        var sch = (document.getElementById('filterScheme') && document.getElementById('filterScheme').value.trim().toLowerCase()) || '';
        var all = loadFundRecords()
            .filter(function (r) {
                var okP = !party || String(r.customer_name || '').toLowerCase().indexOf(party) !== -1;
                var okS = !sch || String(r.scheme_label || '').toLowerCase().indexOf(sch) !== -1;
                return okP && okS;
            })
            .sort(function (a, b) {
                var ta = new Date(a.saved_at || 0).getTime();
                var tb = new Date(b.saved_at || 0).getTime();
                return tb - ta;
            });
        var sizeEl = document.getElementById('ifFundListPageSize');
        var pageSize = sizeEl ? parseInt(sizeEl.value, 10) || 10 : 10;
        var total = all.length;
        var pages = Math.max(1, Math.ceil(total / pageSize));
        if (ifFundListPage > pages) ifFundListPage = pages;
        var start = (ifFundListPage - 1) * pageSize;
        var slice = all.slice(start, start + pageSize);
        host.innerHTML = '';
        if (!slice.length) {
            host.innerHTML =
                '<tr><td colspan="2" class="text-center text-muted py-4 small">No Rows To Show</td></tr>';
        } else {
            slice.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'if-fund-row' + (String(selectedFundListId) === String(r.id) ? ' if-fund-row--active' : '');
                tr.setAttribute('data-fund-id', r.id);
                var amt = r.inst_amt != null ? Number(r.inst_amt).toFixed(0) : '0';
                var paid = r.paid_installments != null ? r.paid_installments : 0;
                var tot = r.total_installments != null ? r.total_installments : 12;
                var schemeTitle = escapeHtml(r.scheme_label || '—');
                tr.innerHTML =
                    '<td><div class="if-party-name">' +
                    escapeHtml(r.customer_name || '—') +
                    '</div><div class="if-party-no">' +
                    escapeHtml(getFundSidebarDisplayNo(r)) +
                    '</div></td><td><div class="if-scheme-title">' +
                    schemeTitle +
                    '</div><div class="if-scheme-meta">(' +
                    amt +
                    ') - Paid Inst. (' +
                    paid +
                    '/' +
                    tot +
                    ')</div></td>';
                tr.addEventListener('click', function () {
                    selectedFundListId = r.id;
                    document.getElementById('currentFundRecordId').value = r.id;
                    document.getElementById('fundNoDisplay').textContent = r.fund_no || '';
                    populateDetailView(r);
                    setInvestmentMainView('detail');
                    var a = document.getElementById('ifTabInstallmentEntry');
                    if (a && window.jQuery) window.jQuery(a).tab('show');
                    renderFundList();
                });
                host.appendChild(tr);
            });
        }
        var from = total === 0 ? 0 : start + 1;
        var to = total === 0 ? 0 : Math.min(start + slice.length, total);
        if (info) info.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' entries';
    }

    function prepareNewFund() {
        selectedFundListId = null;
        document.getElementById('currentFundRecordId').value = '';
        document.getElementById('customerName').value = '';
        document.getElementById('customerId').value = '';
        window.selectedCustomerId = null;
        var sp = document.getElementById('salesPerson');
        var spDef = sp && sp.getAttribute('data-default') ? sp.getAttribute('data-default') : '';
        if (sp) ensureSalesPersonSelectValue(sp, spDef);
        document.getElementById('address').value = '';
        document.getElementById('nomineeName').value = '';
        document.getElementById('email').value = '';
        document.getElementById('schemeName').value = '';
        document.getElementById('joiningDate').value = window.IF_DEFAULT_JOINING_DATE || '';
        document.getElementById('maturityDate').value = '';
        document.getElementById('redemptionOn').value = '';
        document.getElementById('contactNo').value = '';
        document.getElementById('relationType').value = '';
        document.getElementById('duration').value = '';
        document.getElementById('instType').value = '';
        document.getElementById('instAmt').value = '';
        document.getElementById('nationalId').value = '';
        applyInstallmentRowsData([]);
        document.getElementById('fundNoDisplay').textContent = getNextFundNo();
        setInvestmentMainView('form');
        var a = document.getElementById('ifTabInstallmentEntry');
        if (a && window.jQuery) window.jQuery(a).tab('show');
        renderFundList();
    }

    document.getElementById('btnAddInstallmentRow').addEventListener('click', function () {
        appendInstallmentRow(null);
    });

    var installmentTableBodyEl = document.getElementById('installmentTableBody');
    if (installmentTableBodyEl) {
        installmentTableBodyEl.addEventListener('input', refreshInstallmentEntrySummary);
        installmentTableBodyEl.addEventListener('change', refreshInstallmentEntrySummary);
    }
    var instAmtSummaryEl = document.getElementById('instAmt');
    if (instAmtSummaryEl) {
        instAmtSummaryEl.addEventListener('input', refreshInstallmentEntrySummary);
    }
    refreshInstallmentEntrySummary();

    var ifReportPartyBodyEl = document.getElementById('ifReportPartyBody');
    if (ifReportPartyBodyEl) {
        ifReportPartyBodyEl.addEventListener('click', function (e) {
            var tr = e.target.closest('.if-report-party-row');
            if (!tr) return;
            var p = tr.getAttribute('data-party');
            ifReportSelectedParty = p === '*' ? null : p;
            hideReportDetailCard();
            renderInstallmentReport();
        });
    }
    var ifReportPartyFilterEl = document.getElementById('ifReportPartyFilter');
    if (ifReportPartyFilterEl) {
        ifReportPartyFilterEl.addEventListener('input', function () {
            renderInstallmentReportPartyList();
        });
    }
    var ifReportSearchMainEl = document.getElementById('ifReportSearchMain');
    if (ifReportSearchMainEl) {
        ifReportSearchMainEl.addEventListener('input', function () {
            if (ifReportSearchTimer) clearTimeout(ifReportSearchTimer);
            ifReportSearchTimer = setTimeout(function () {
                renderInstallmentReport();
            }, 200);
        });
    }
    var ifReportBtnRefreshEl = document.getElementById('ifReportBtnRefresh');
    if (ifReportBtnRefreshEl) {
        ifReportBtnRefreshEl.addEventListener('click', function () {
            renderInstallmentReport();
        });
    }
    var ifReportBtnFilterEl = document.getElementById('ifReportBtnFilter');
    if (ifReportBtnFilterEl) {
        ifReportBtnFilterEl.addEventListener('click', function () {
            var s = document.getElementById('ifReportSearchMain');
            if (s) s.focus();
        });
    }
    var ifReportExportCsvEl = document.getElementById('ifReportExportCsv');
    if (ifReportExportCsvEl) {
        ifReportExportCsvEl.addEventListener('click', function (e) {
            e.preventDefault();
            exportInstallmentReportCsv();
        });
    }
    var ifReportMainBodyEl = document.getElementById('ifReportMainBody');
    if (ifReportMainBodyEl) {
        ifReportMainBodyEl.addEventListener('click', function (e) {
            var td = e.target.closest('td');
            var tr = e.target.closest('tr[data-fund-id]');
            if (!tr || !td || !ifReportMainCellOpensDetail(td)) return;
            var id = tr.getAttribute('data-fund-id');
            var list = loadFundRecords();
            var rec = list.filter(function (x) {
                return String(x.id) === String(id);
            })[0];
            if (!rec) return;
            ifReportSelectedFundId = rec.id;
            ifReportMainBodyEl.querySelectorAll('tr[data-fund-id]').forEach(function (row) {
                row.classList.toggle('if-report-row--selected', row === tr);
            });
            populateReportDetailView(rec);
            showReportDetailCard();
        });
        ifReportMainBodyEl.addEventListener('dblclick', function (e) {
            var tr = e.target.closest('tr[data-fund-id]');
            if (!tr) return;
            var id = tr.getAttribute('data-fund-id');
            var list = loadFundRecords();
            var rec = list.filter(function (x) {
                return String(x.id) === String(id);
            })[0];
            if (!rec) return;
            openInstallmentEntryFromReport(rec);
        });
    }
    var ifReportBtnOpenEntryEl = document.getElementById('ifReportBtnOpenEntry');
    if (ifReportBtnOpenEntryEl) {
        ifReportBtnOpenEntryEl.addEventListener('click', function () {
            if (ifReportSelectedFundId == null) return;
            var list = loadFundRecords();
            var rec = list.filter(function (x) {
                return String(x.id) === String(ifReportSelectedFundId);
            })[0];
            openInstallmentEntryFromReport(rec);
        });
    }
    function setInstallmentReportLayoutActive(on) {
        document.body.classList.toggle('if-installment-report-active', !!on);
    }

    if (window.jQuery) {
        window.jQuery('a[href="#tabInstallmentReport"]').on('shown.bs.tab', function () {
            setInstallmentReportLayoutActive(true);
            renderInstallmentReport();
        });
        window.jQuery('a[href="#tabInstallmentEntry"]').on('shown.bs.tab', function () {
            setInstallmentReportLayoutActive(false);
        });
        window.jQuery('a[href="#tabLayawaysReport"]').on('shown.bs.tab', function () {
            setInstallmentReportLayoutActive(false);
        });
    }

    document.getElementById('viewNormal').addEventListener('click', function() {
        this.classList.add('btn-primary');
        this.classList.remove('btn-outline-secondary');
        document.getElementById('viewPassbook').classList.remove('btn-primary');
        document.getElementById('viewPassbook').classList.add('btn-outline-secondary');
    });
    document.getElementById('viewPassbook').addEventListener('click', function() {
        this.classList.add('btn-primary');
        this.classList.remove('btn-outline-secondary');
        document.getElementById('viewNormal').classList.remove('btn-primary');
        document.getElementById('viewNormal').classList.add('btn-outline-secondary');
    });

    var custInput = document.getElementById('customerName');
    var custBox = document.getElementById('customerSuggestions');
    var custId = document.getElementById('customerId');

    function hideCust() {
        custBox.style.display = 'none';
        custBox.innerHTML = '';
    }

    custInput.addEventListener('input', function() {
        var q = this.value.trim();
        if (q.length < 2) {
            hideCust();
            custId.value = '';
            window.selectedCustomerId = null;
            document.getElementById('address').value = '';
            document.getElementById('email').value = '';
            document.getElementById('contactNo').value = '';
            document.getElementById('nationalId').value = '';
            return;
        }
        fetch('ajax/search-customers.php?q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                custBox.innerHTML = '';
                if (!data.customers || !data.customers.length) {
                    custBox.innerHTML = '<div class="px-2 py-2 text-muted small">No matches</div>';
                    custBox.style.display = 'block';
                    return;
                }
                data.customers.forEach(function(c) {
                    var div = document.createElement('div');
                    div.className = 'px-2 py-1 small';
                    div.style.cursor = 'pointer';
                    div.textContent = c.display_text || c.name;
                    div.onmouseenter = function() { div.style.background = '#f1f5f9'; };
                    div.onmouseleave = function() { div.style.background = ''; };
                    div.onclick = function() {
                        custInput.value = c.name;
                        custId.value = c.id;
                        window.selectedCustomerId = c.id;
                        document.getElementById('address').value = c.address || '';
                        document.getElementById('contactNo').value = c.mobile_no || '';
                        document.getElementById('email').value = c.mail_id || '';
                        document.getElementById('nationalId').value = c.national_id || '';
                        hideCust();
                    };
                    custBox.appendChild(div);
                });
                custBox.style.display = 'block';
            })
            .catch(function() { hideCust(); });
    });

    document.addEventListener('click', function(e) {
        if (!custBox.contains(e.target) && e.target !== custInput) hideCust();
    });

    var schemeNameEl = document.getElementById('schemeName');
    if (schemeNameEl) {
        schemeNameEl.addEventListener('change', applySelectedSchemeToInstallmentForm);
    }
    var joiningDateEl = document.getElementById('joiningDate');
    if (joiningDateEl) {
        joiningDateEl.addEventListener('change', function () {
            syncMaturityDateFromJoinAndScheme();
        });
    }

    document.getElementById('btnSaveFund').addEventListener('click', function() {
        if (!document.getElementById('customerName').value.trim()) {
            alert('Please enter Customer Name.');
            return;
        }
        if (!document.getElementById('schemeName').value) {
            alert('Please select Scheme Name.');
            return;
        }
        var rows = collectInstallmentRowsData();
        var paid = countPaidInstallments(rows);
        var total = computeTotalInstallments(rows);
        var snap = collectFormSnapshot();
        var list = loadFundRecords();
        var editId = document.getElementById('currentFundRecordId').value;
        var rec;
        if (editId) {
            var existing = list.filter(function (x) { return String(x.id) === String(editId); })[0];
            rec = Object.assign({}, snap, {
                id: editId,
                fund_no: existing && existing.fund_no ? existing.fund_no : getNextFundNo(),
                paid_installments: paid,
                total_installments: total,
                saved_at: new Date().toISOString()
            });
            if (existing && existing.fund_transfer_done) {
                rec.fund_transfer_done = true;
                rec.fund_transfer_at = existing.fund_transfer_at || rec.saved_at;
            }
            if (existing && Array.isArray(existing.detail_footer_images)) {
                rec.detail_footer_images = existing.detail_footer_images;
            }
            var ix = list.findIndex(function (x) { return String(x.id) === String(editId); });
            if (ix >= 0) list[ix] = rec;
            else list.push(rec);
        } else {
            rec = Object.assign({}, snap, {
                id: String(Date.now()),
                fund_no: getNextFundNo(),
                paid_installments: paid,
                total_installments: total,
                saved_at: new Date().toISOString(),
                detail_footer_images: []
            });
            list.push(rec);
        }
        saveFundRecords(list);
        document.getElementById('currentFundRecordId').value = rec.id;
        selectedFundListId = rec.id;
        document.getElementById('fundNoDisplay').textContent = rec.fund_no;
        renderFundList();
        populateDetailView(rec);
        setInvestmentMainView('detail');
        var tabA = document.getElementById('ifTabInstallmentEntry');
        if (tabA && window.jQuery) window.jQuery(tabA).tab('show');
        alert('Record saved.');
    });

    document.getElementById('btnNewInvestmentFund').addEventListener('click', function () {
        prepareNewFund();
    });

    document.getElementById('btnEditFund').addEventListener('click', function () {
        var rec = getCurrentFundRecord();
        if (!rec) return;
        applyFormSnapshot(rec);
        setInvestmentMainView('form');
    });

    document.getElementById('btnDetailPrint').addEventListener('click', function () {
        var rec = getCurrentFundRecord();
        if (!rec) {
            alert('Select a fund and open installment detail first.');
            return;
        }
        var items = collectDetailInstallmentPrintItems(rec);
        if (!items.length) {
            alert('No installment records to print.');
            return;
        }
        openPrintBillModal(function () {
            openInvestmentFundPrintPage(rec, items);
        });
    });

    (function bindIfPrintBillModalButtons() {
        var yesBtn = document.getElementById('ifPrintBillYes');
        var noBtn = document.getElementById('ifPrintBillNo');
        var closeBtn = document.querySelector('#ifPrintBillModal .if-print-bill-close');
        if (yesBtn && !yesBtn._ifPrintBound) {
            yesBtn._ifPrintBound = true;
            yesBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var fn = ifPrintBillResolve;
                ifPrintBillResolve = null;
                /* Run print immediately (same user gesture) so the browser does not block window.open */
                if (typeof fn === 'function') {
                    fn();
                }
                if (window.jQuery) {
                    window.jQuery('#ifPrintBillModal').modal('hide');
                }
            });
        }
        if (noBtn && !noBtn._ifPrintBound) {
            noBtn._ifPrintBound = true;
            noBtn.addEventListener('click', function () {
                ifPrintBillResolve = null;
            });
        }
        if (closeBtn && !closeBtn._ifPrintBound) {
            closeBtn._ifPrintBound = true;
            closeBtn.addEventListener('click', function () {
                ifPrintBillResolve = null;
            });
        }
    })();

    var fwSelectedPayType = 'Cash';
    var fundWithdrawModalEl = document.getElementById('fundWithdrawModal');

    function getFwTodayYmd() {
        var d = new Date();
        return d.getFullYear() + '-' + pad2Num(d.getMonth() + 1) + '-' + pad2Num(d.getDate());
    }

    function parseFwNum(s) {
        if (s == null || s === '') return 0;
        return parseFloat(String(s).replace(/,/g, '')) || 0;
    }

    function syncFundWithdrawAmounts() {
        var amtEl = document.getElementById('fwAmount');
        var pctEl = document.getElementById('fwExtraPct');
        var extraEl = document.getElementById('fwExtraAmt');
        var totEl = document.getElementById('fwTotalAmt');
        if (!amtEl || !pctEl || !extraEl || !totEl) return;
        var amt = parseFwNum(amtEl.value);
        var pct = parseFwNum(pctEl.value);
        var extraOnAmt = amt * (pct / 100);
        extraEl.value = extraOnAmt.toFixed(2);
        totEl.value = (amt + extraOnAmt).toFixed(2);
    }

    function refreshFundWithdrawGridTotal() {
        var tb = document.getElementById('fwPaymentGridBody');
        var totalEl = document.getElementById('fwPaymentGridTotal');
        if (!tb || !totalEl) return;
        var rows = tb.querySelectorAll('tr[data-fw-row]');
        var sum = 0;
        for (var i = 0; i < rows.length; i++) {
            var inp = rows[i].querySelector('.fw-row-amt');
            if (inp) sum += parseFwNum(inp.value);
        }
        totalEl.textContent = sum.toFixed(2);
    }

    function renderFundWithdrawGridEmpty() {
        var tb = document.getElementById('fwPaymentGridBody');
        if (!tb) return;
        tb.innerHTML =
            '<tr class="if-fw-empty-row"><td colspan="7" class="text-center text-muted py-4">No Rows To Show</td></tr>';
        refreshFundWithdrawGridTotal();
    }

    function addFundWithdrawGridRow(payType, amount) {
        addFundWithdrawGridRowDetailed({
            payType: payType || fwSelectedPayType || 'Cash',
            diamond: '',
            txn: '',
            transfer: '',
            chequeDt: '',
            amount: amount
        });
    }

    function openFundWithdrawModal() {
        var rec = getCurrentFundRecord();
        if (!rec) {
            alert('Select a fund from the list and open its installment detail first.');
            return;
        }
        if (rec.fund_transfer_done) {
            alert('Fund withdraw is not available after a fund transfer.');
            return;
        }
        var nameEl = document.getElementById('fwCustomerName');
        if (nameEl) nameEl.textContent = rec.customer_name || '—';
        var dEl = document.getElementById('fwWithdrawDate');
        if (dEl) dEl.value = getFwTodayYmd();
        var nPass = getPassbookPeriodCount(rec);
        var passTotals = recalcFundPassbookTotals(rec, nPass);
        var paidSumEl = document.getElementById('fwTotalPaidAmt');
        if (paidSumEl) paidSumEl.value = formatMoney(passTotals.paidAmt);
        var pctEl = document.getElementById('fwExtraPct');
        if (pctEl) pctEl.value = '0';
        var perInst = rec.inst_amt != null ? Number(rec.inst_amt) : 0;
        if (isNaN(perInst)) perInst = 0;
        var paidTotal = Number(passTotals.paidAmt);
        if (isNaN(paidTotal)) paidTotal = 0;
        var defaultWithdraw = paidTotal > 0 ? paidTotal : perInst;
        var amtInp = document.getElementById('fwAmount');
        if (amtInp) amtInp.value = defaultWithdraw.toFixed(2);
        var draft = document.getElementById('fwPaymentDraft');
        if (draft) draft.value = '';
        syncFundWithdrawAmounts();
        renderFundWithdrawGridEmpty();
        fwSelectedPayType = 'Cash';
        if (fundWithdrawModalEl) {
            fundWithdrawModalEl.setAttribute('data-fw-pay-display', 'Cash');
            var icons = fundWithdrawModalEl.querySelectorAll('.if-fw-pay-icon');
            for (var i = 0; i < icons.length; i++) {
                icons[i].classList.toggle(
                    'if-fw-pay-icon--active',
                    icons[i].getAttribute('data-fw-pay') === 'Cash'
                );
            }
        }
        if (window.jQuery) window.jQuery('#fundWithdrawModal').modal('show');
    }

    if (fundWithdrawModalEl && window.jQuery) {
        window.jQuery(fundWithdrawModalEl).on('hidden.bs.modal', function () {
            fundWithdrawModalEl.removeAttribute('data-fw-payment-flow');
        });
        var fwPaymentModalIds = [
            'cashPaymentModal',
            'bankPaymentModal',
            'chequePaymentModal',
            'upiPaymentModal',
            'cardPaymentModal',
            'metalExchangeModal',
            'scrapPaymentModal'
        ];
        for (var fwi = 0; fwi < fwPaymentModalIds.length; fwi++) {
            (function (mid) {
                var pel = document.getElementById(mid);
                if (!pel) return;
                window.jQuery(pel).on('hidden.bs.modal', function () {
                    if (fundWithdrawModalEl) fundWithdrawModalEl.removeAttribute('data-fw-payment-flow');
                });
            })(fwPaymentModalIds[fwi]);
        }
    }

    var btnFundWithdrawEl = document.getElementById('btnFundWithdraw');
    if (btnFundWithdrawEl) {
        btnFundWithdrawEl.addEventListener('click', function () {
            openFundWithdrawModal();
        });
    }

    var fwAmtEl = document.getElementById('fwAmount');
    var fwPctEl = document.getElementById('fwExtraPct');
    if (fwAmtEl) fwAmtEl.addEventListener('input', syncFundWithdrawAmounts);
    if (fwPctEl) fwPctEl.addEventListener('input', syncFundWithdrawAmounts);

    var btnFwAddLineEl = document.getElementById('btnFwAddLine');
    if (btnFwAddLineEl) {
        btnFwAddLineEl.addEventListener('click', function () {
            var draft = document.getElementById('fwPaymentDraft');
            var v = draft && draft.value.trim() !== '' ? draft.value : null;
            addFundWithdrawGridRow(fwSelectedPayType, v);
            if (draft) draft.value = '';
        });
    }

    if (fundWithdrawModalEl) {
        fundWithdrawModalEl.addEventListener('click', function (e) {
            var payBtn = e.target.closest('.if-fw-pay-icon');
            if (!payBtn || !fundWithdrawModalEl.contains(payBtn)) return;
            fwSelectedPayType = payBtn.getAttribute('data-fw-pay') || 'Cash';
            fundWithdrawModalEl.setAttribute('data-fw-pay-display', fwSelectedPayType);
            fundWithdrawModalEl.setAttribute('data-fw-payment-flow', '1');
            var icons = fundWithdrawModalEl.querySelectorAll('.if-fw-pay-icon');
            for (var j = 0; j < icons.length; j++) icons[j].classList.remove('if-fw-pay-icon--active');
            payBtn.classList.add('if-fw-pay-icon--active');
            openFundWithdrawPaymentModal(fwSelectedPayType);
        });
    }

    var btnFundWithdrawSaveEl = document.getElementById('btnFundWithdrawSave');
    if (btnFundWithdrawSaveEl) {
        btnFundWithdrawSaveEl.addEventListener('click', function () {
            var rec = getCurrentFundRecord();
            if (!rec) return;
            var rows = document.querySelectorAll('#fwPaymentGridBody tr[data-fw-row]');
            var gridTotal = parseFwNum(document.getElementById('fwPaymentGridTotal').textContent);
            var headerTotal = parseFwNum(document.getElementById('fwTotalAmt').value);
            if (rows.length && Math.abs(gridTotal - headerTotal) > 0.02) {
                if (
                    !confirm(
                        'Payment grid total (' +
                            gridTotal.toFixed(2) +
                            ') does not match Total Amt. (' +
                            headerTotal.toFixed(2) +
                            '). Save anyway?'
                    )
                ) {
                    return;
                }
            }
            alert('Fund withdraw saved locally (demo). Connect API to post to ledger when ready.');
            if (window.jQuery) window.jQuery('#fundWithdrawModal').modal('hide');
        });
    }

    var btnFwMoreEl = document.getElementById('btnFwMore');
    if (btnFwMoreEl) {
        btnFwMoreEl.addEventListener('click', function () {
            alert('Additional options can be added here.');
        });
    }
    var btnFwGridSettingsEl = document.getElementById('btnFwGridSettings');
    if (btnFwGridSettingsEl) {
        btnFwGridSettingsEl.addEventListener('click', function () {
            alert('Column settings — coming soon.');
        });
    }

    var ftModalPaidAmount = 0;

    function parseFtNum(s) {
        if (s == null || s === '') return 0;
        return parseFloat(String(s).replace(/,/g, '')) || 0;
    }

    function computeFundPaidAmountSum(rec) {
        if (!rec) return 0;
        var n = rec.total_installments != null ? rec.total_installments : 12;
        var arr = rec.installments || [];
        var paidAmt = 0;
        for (var i = 0; i < n; i++) {
            var row = arr[i] || {};
            var a = parseFloat(row.amount);
            if (!isNaN(a)) paidAmt += a;
        }
        return paidAmt;
    }

    function formatFtMoney(n) {
        var x = Number(n);
        if (isNaN(x)) x = 0;
        return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function syncFundTransferCalculations() {
        var baseEl = document.getElementById('ftBonusBaseAmount');
        var pctEl = document.getElementById('ftExtraPct');
        var extraInp = document.getElementById('ftExtraAmt');
        if (!baseEl || !pctEl || !extraInp) return;
        var base = parseFtNum(baseEl.value);
        var pct = parseFtNum(pctEl.value);
        var extraAmt = base * (pct / 100);
        extraInp.value = extraAmt.toFixed(2);
        var totalBonus = extraAmt;
        var paid = ftModalPaidAmount;
        var transferAmt = totalBonus + paid;
        var tb = formatFtMoney(totalBonus);
        var ex = formatFtMoney(extraAmt);
        var pd = formatFtMoney(paid);
        var tr = formatFtMoney(transferAmt);
        var el1 = document.getElementById('ftDispTotalBonus');
        var el2 = document.getElementById('ftDispExtraAmount');
        var el3 = document.getElementById('ftDispTransferAmt');
        var el4 = document.getElementById('ftDispTotalBonusRow2');
        var el5 = document.getElementById('ftDispPaidAmount');
        if (el1) el1.textContent = tb;
        if (el2) el2.textContent = ex;
        if (el3) el3.textContent = tr;
        if (el4) el4.textContent = tb;
        if (el5) el5.textContent = pd;
    }

    function openFundTransferModal() {
        var rec = getCurrentFundRecord();
        if (!rec) {
            alert('Select a fund from the list and open its installment detail first.');
            return;
        }
        if (rec.fund_transfer_done) {
            ftModalPaidAmount = computeFundPaidAmountSum(rec);
            var cn = document.getElementById('ftCustomerName');
            if (cn) cn.textContent = rec.customer_name || '—';
            var dEl = document.getElementById('ftTransferDate');
            if (dEl) {
                if (rec.fund_transfer_date && String(rec.fund_transfer_date).trim() !== '') {
                    dEl.value = rec.fund_transfer_date;
                } else {
                    dEl.value = getFwTodayYmd();
                }
            }
            var inst = rec.inst_amt != null ? Number(rec.inst_amt) : 0;
            var bEl = document.getElementById('ftBonusBaseAmount');
            if (bEl) bEl.value = isNaN(inst) ? '0.00' : inst.toFixed(2);
            var pEl = document.getElementById('ftExtraPct');
            if (pEl) pEl.value = rec.fund_transfer_extra_pct != null ? String(rec.fund_transfer_extra_pct) : '0';
            syncFundTransferCalculations();
            if (rec.fund_transfer_amount != null && !isNaN(Number(rec.fund_transfer_amount))) {
                var dispFix = document.getElementById('ftDispTransferAmt');
                if (dispFix) dispFix.textContent = formatFtMoney(Number(rec.fund_transfer_amount));
            }
            var fts = document.getElementById('btnFundTransferSave');
            if (fts) fts.disabled = false;
            if (window.jQuery) window.jQuery('#fundTransferModal').modal('show');
            return;
        }
        ftModalPaidAmount = computeFundPaidAmountSum(rec);
        var cn = document.getElementById('ftCustomerName');
        if (cn) cn.textContent = rec.customer_name || '—';
        var dEl = document.getElementById('ftTransferDate');
        if (dEl) dEl.value = getFwTodayYmd();
        var inst = rec.inst_amt != null ? Number(rec.inst_amt) : 0;
        var bEl = document.getElementById('ftBonusBaseAmount');
        if (bEl) bEl.value = isNaN(inst) ? '0.00' : inst.toFixed(2);
        var pEl = document.getElementById('ftExtraPct');
        if (pEl) pEl.value = '0';
        syncFundTransferCalculations();
        var fts = document.getElementById('btnFundTransferSave');
        if (fts) fts.disabled = false;
        if (window.jQuery) window.jQuery('#fundTransferModal').modal('show');
    }

    var btnFundTransferEl = document.getElementById('btnFundTransfer');
    if (btnFundTransferEl) {
        btnFundTransferEl.addEventListener('click', function () {
            openFundTransferModal();
        });
    }
    var ftBaseEl = document.getElementById('ftBonusBaseAmount');
    var ftPctEl = document.getElementById('ftExtraPct');
    if (ftBaseEl) ftBaseEl.addEventListener('input', syncFundTransferCalculations);
    if (ftPctEl) ftPctEl.addEventListener('input', syncFundTransferCalculations);

    var btnFundTransferSaveEl = document.getElementById('btnFundTransferSave');
    if (btnFundTransferSaveEl) {
        btnFundTransferSaveEl.addEventListener('click', function () {
            var rec = getCurrentFundRecord();
            if (!rec) return;
            var alreadyDone = !!rec.fund_transfer_done;
            if (!confirm(alreadyDone ? 'Post this fund transfer to the account ledger (or confirm if already posted)?' : 'Do you want to save changes?')) return;
            var transferDateEl = document.getElementById('ftTransferDate');
            var transferDate = transferDateEl && transferDateEl.value ? transferDateEl.value : '';
            var baseEl = document.getElementById('ftBonusBaseAmount');
            var pctEl = document.getElementById('ftExtraPct');
            var dispTrEl = document.getElementById('ftDispTransferAmt');
            var transferAmt = 0;
            if (dispTrEl && dispTrEl.textContent) {
                transferAmt = parseFtNum(dispTrEl.textContent.replace(/[^\d.,-]/g, ''));
            }
            if (transferAmt <= 0 && baseEl && pctEl) {
                var baseFt = parseFtNum(baseEl.value);
                var pctFt = parseFtNum(pctEl.value);
                transferAmt = baseFt * (pctFt / 100) + (typeof ftModalPaidAmount === 'number' ? ftModalPaidAmount : 0);
            }
            if (alreadyDone && rec.fund_transfer_amount != null && !isNaN(Number(rec.fund_transfer_amount)) && Number(rec.fund_transfer_amount) > 0) {
                transferAmt = Number(rec.fund_transfer_amount);
            }
            if (transferAmt <= 0) {
                alert('Transfer amount must be greater than zero.');
                return;
            }
            var fundNo = rec.fund_no || '';
            if (!fundNo) {
                alert('Fund number is missing. Save the investment record first, then complete the transfer.');
                return;
            }
            var custName = (rec.customer_name || '').trim();
            if (!custName) {
                alert('Customer name is required for the account ledger.');
                return;
            }
            var payload = {
                customer_id: parseInt(rec.customer_id, 10) || 0,
                customer_name: custName,
                fund_no: fundNo,
                ft_no: deriveFtNoFromFundNo(fundNo),
                transfer_date: transferDate,
                transfer_amount: transferAmt,
                fund_local_id: String(rec.id || '')
            };
            var btnFt = this;
            var prevDis = btnFt.disabled;
            btnFt.disabled = true;
            function finalizeFundTransfer() {
                rec.fund_transfer_done = true;
                rec.fund_transfer_at = new Date().toISOString();
                rec.saved_at = rec.fund_transfer_at;
                rec.fund_transfer_amount = transferAmt;
                rec.fund_transfer_date = transferDate;
                rec.fund_transfer_extra_pct = pctEl ? parseFtNum(pctEl.value) : 0;
                persistCurrentFundRecord(rec);
                renderFundList();
                populateDetailView(rec);
                if (window.jQuery) window.jQuery('#fundTransferModal').modal('hide');
                btnFt.disabled = prevDis;
            }
            var ifAdminPath = window.location.pathname.replace(/[^/]*$/, '');
            var ftLedgerPostUrl = ifAdminPath + 'ajax/post-investment-fund-transfer-ledger.php';
            fetch(ftLedgerPostUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        var data = null;
                        try {
                            data = text ? JSON.parse(text) : null;
                        } catch (e) {
                            data = null;
                        }
                        if (!r.ok) {
                            throw new Error((text && text.slice(0, 400)) || ('HTTP ' + r.status));
                        }
                        if (!data || typeof data !== 'object') {
                            throw new Error((text && text.slice(0, 400)) || 'Invalid response from server');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        alert((data && data.message) || 'Could not post fund transfer to account ledger.');
                        btnFt.disabled = prevDis;
                        return;
                    }
                    if (typeof data.amount === 'number' && data.amount > 0) {
                        transferAmt = data.amount;
                    }
                    if (alreadyDone) {
                        rec.fund_transfer_amount = transferAmt;
                        if (transferDate) rec.fund_transfer_date = transferDate;
                        rec.fund_transfer_extra_pct = pctEl ? parseFtNum(pctEl.value) : 0;
                        persistCurrentFundRecord(rec);
                        alert(
                            data.duplicate
                                ? 'Account ledger already includes this fund transfer.'
                                : 'Posted to account ledger. Open Account Ledger Report to verify.'
                        );
                        btnFt.disabled = prevDis;
                        if (window.jQuery) window.jQuery('#fundTransferModal').modal('hide');
                        return;
                    }
                    finalizeFundTransfer();
                })
                .catch(function (err) {
                    alert(
                        (err && err.message ? err.message : '') ||
                            'Could not post fund transfer to account ledger. Check network or PHP error log.'
                    );
                    btnFt.disabled = prevDis;
                });
        });
    }

    (function bindDetailFooterAttach() {
        var btn = document.getElementById('ifDetailInstAttachBtn');
        var inp = document.getElementById('ifDetailInstAttachInput');
        var clr = document.getElementById('ifDetailInstAttachClear');
        if (!btn || !inp) return;
        btn.addEventListener('click', function () {
            inp.click();
        });
        inp.addEventListener('change', function () {
            var files = inp.files;
            if (!files || !files.length) return;
            var rec = getCurrentFundRecord();
            if (!rec) {
                alert('Select a fund from the list, then attach images.');
                inp.value = '';
                return;
            }
            var arr = getDetailFooterImages(rec).slice();
            var room = IF_DETAIL_ATTACH_MAX_FILES - arr.length;
            if (room <= 0) {
                alert('Maximum ' + IF_DETAIL_ATTACH_MAX_FILES + ' images allowed. Use Clear to remove.');
                inp.value = '';
                return;
            }
            var toAdd = Array.prototype.slice.call(files, 0, room);
            var oversize = [];
            toAdd.forEach(function (f) {
                if (f.size > IF_DETAIL_ATTACH_MAX_BYTES) oversize.push(f.name);
            });
            if (oversize.length) {
                alert('These files exceed 2 MB and were skipped: ' + oversize.join(', '));
                toAdd = toAdd.filter(function (f) { return f.size <= IF_DETAIL_ATTACH_MAX_BYTES; });
            }
            if (!toAdd.length) {
                inp.value = '';
                return;
            }
            var pending = toAdd.length;
            toAdd.forEach(function (file) {
                var rdr = new FileReader();
                rdr.onload = function () {
                    arr.push({
                        name: file.name,
                        data_url: rdr.result,
                        added_at: new Date().toISOString()
                    });
                    pending--;
                    if (pending === 0) {
                        rec.detail_footer_images = arr;
                        persistCurrentFundRecord(rec);
                        refreshDetailFooterAttachUi(rec);
                        inp.value = '';
                    }
                };
                rdr.onerror = function () {
                    pending--;
                    if (pending === 0) {
                        rec.detail_footer_images = arr;
                        persistCurrentFundRecord(rec);
                        refreshDetailFooterAttachUi(rec);
                        inp.value = '';
                    }
                };
                rdr.readAsDataURL(file);
            });
        });
        if (clr) {
            clr.addEventListener('click', function () {
                var rec = getCurrentFundRecord();
                if (!rec) return;
                if (!getDetailFooterImages(rec).length) return;
                if (!confirm('Remove all attached images for this fund?')) return;
                rec.detail_footer_images = [];
                persistCurrentFundRecord(rec);
                refreshDetailFooterAttachUi(rec);
            });
        }
    })();

    document.getElementById('dvViewNormal').addEventListener('click', function () {
        var rec = getCurrentFundRecord();
        if (!rec) return;
        ifDetailInstMode = 'normal';
        setDetailViewToggleUi(false);
        renderDetailInstallmentTable(rec);
    });
    document.getElementById('dvViewPassbook').addEventListener('click', function () {
        var rec = getCurrentFundRecord();
        if (!rec) return;
        ifDetailInstMode = 'passbook';
        setDetailViewToggleUi(true);
        renderDetailInstallmentTable(rec);
    });

    document.getElementById('dvInstallmentTableBody').addEventListener('click', function (e) {
        var rec = getCurrentFundRecord();
        if (!rec) return;
        if (rec.fund_transfer_done) return;
        if (ifDetailInstMode === 'normal') {
            var ed = e.target.closest('.if-btn-normal-edit');
            if (ed) {
                var six = parseInt(ed.getAttribute('data-schedule-index'), 10);
                if (isNaN(six)) six = 0;
                var sched = passbookScheduleDateYmd(rec, six);
                openInvestmentReceiptModal(six, sched, rec, 'normal');
                e.preventDefault();
                return;
            }
            if (e.target.closest('.if-btn-normal-print')) {
                var pr = e.target.closest('.if-btn-normal-print');
                var six = pr ? parseInt(pr.getAttribute('data-schedule-index'), 10) : 0;
                if (isNaN(six)) six = 0;
                var row = getInstallmentSlot(rec, six, getPassbookPeriodCount(rec));
                openPrintBillModal(function () {
                    openInvestmentFundPrintPage(rec, [{ row: row, schedIx: six }]);
                });
                e.preventDefault();
                return;
            }
            var clr = e.target.closest('.if-btn-normal-clear');
            if (clr) {
                var cix = parseInt(clr.getAttribute('data-schedule-index'), 10);
                if (isNaN(cix)) cix = 0;
                if (confirm('Clear this installment line?')) {
                    clearPassbookRowPayment(cix);
                }
                e.preventDefault();
                return;
            }
            return;
        }
        if (ifDetailInstMode !== 'passbook') return;
        var payRec = e.target.closest('.if-btn-receipt, .if-btn-pay');
        if (payRec) {
            var ix = parseInt(payRec.getAttribute('data-row-index'), 10);
            var sched = payRec.getAttribute('data-sched-ymd') || passbookScheduleDateYmd(rec, ix);
            openInvestmentReceiptModal(ix, sched, rec, 'passbook');
            e.preventDefault();
            return;
        }
        var del = e.target.closest('.if-btn-row-del');
        if (del) {
            if (confirm('Clear this installment line?')) {
                clearPassbookRowPayment(parseInt(del.getAttribute('data-row-index'), 10));
            }
        }
    });

    document.getElementById('btnDetailNormalMore').addEventListener('click', function () {
        var rec = getCurrentFundRecord();
        if (!rec || rec.fund_transfer_done) return;
        var n = getPassbookPeriodCount(rec);
        var ix = findFirstUnpaidIndex(rec, n);
        var sched = passbookScheduleDateYmd(rec, ix);
        openInvestmentReceiptModal(ix, sched, rec, 'normal');
    });

    (function bindIfRecInstMonthSelectAll() {
        var selAll = document.getElementById('ifRecInstMonthSelectAll');
        if (!selAll || selAll._ifBound) return;
        selAll._ifBound = true;
        selAll.addEventListener('change', function () {
            var on = this.checked;
            document.querySelectorAll('#ifRecInstMonthChecks input[type=checkbox]').forEach(function (cb) {
                cb.checked = on;
            });
            onIfRecInstMonthSelectionChange();
        });
    })();

    initIfRecPaymentIcons();

    (function bindIfScrapProductSearchForReceipt() {
        var scrapMetal = document.getElementById('scrapMetal');
        var scrapProductInput = document.getElementById('scrapProductInput');
        var scrapProductId = document.getElementById('scrapProductId');
        var scrapProductList = document.getElementById('scrapProductList');
        if (!scrapProductInput || !scrapProductList) return;
        var scrapSearchTimeout;
        function showScrapProductList(products) {
            scrapProductList.innerHTML = '';
            scrapProductList.style.display = 'block';
            if (!products || !products.length) {
                scrapProductList.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
                return;
            }
            products.forEach(function (p) {
                var div = document.createElement('div');
                div.className = 'scrap-product-item p-2 border-bottom';
                div.style.cursor = 'pointer';
                div.style.fontSize = '0.9rem';
                div.onmouseover = function () {
                    this.style.background = '#f1f5f9';
                };
                div.onmouseout = function () {
                    this.style.background = '';
                };
                div.textContent = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                div.addEventListener('click', function () {
                    scrapProductInput.value =
                        (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                    if (scrapProductId) scrapProductId.value = (p.characteristic_id || p.id) || '';
                    var rateEl = document.getElementById('scrapRate');
                    var purityEl = document.getElementById('scrapPurity');
                    if (rateEl && p.rate != null) rateEl.value = p.rate;
                    if (purityEl && p.opening_purity != null) purityEl.value = p.opening_purity;
                    scrapProductList.style.display = 'none';
                    scrapProductList.innerHTML = '';
                });
                scrapProductList.appendChild(div);
            });
        }
        function searchScrapProducts() {
            var mid = scrapMetal ? parseInt(scrapMetal.value, 10) : 0;
            var search = scrapProductInput.value ? scrapProductInput.value.trim() : '';
            if (!mid) {
                scrapProductList.innerHTML = '<div class="p-2 text-muted small">Select metal first</div>';
                scrapProductList.style.display = 'block';
                return;
            }
            scrapProductList.innerHTML = '<div class="p-2 text-muted small">Loading...</div>';
            scrapProductList.style.display = 'block';
            var url =
                'ajax/get-products-by-metal.php?metal_id=' +
                encodeURIComponent(mid) +
                (search ? '&search=' + encodeURIComponent(search) : '');
            fetch(url)
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    showScrapProductList(data.success && data.products ? data.products : []);
                })
                .catch(function () {
                    scrapProductList.innerHTML = '<div class="p-2 text-danger small">Error loading products</div>';
                });
        }
        scrapProductInput.addEventListener('input', function () {
            clearTimeout(scrapSearchTimeout);
            if (scrapProductId) scrapProductId.value = '';
            scrapSearchTimeout = setTimeout(searchScrapProducts, 300);
        });
        scrapProductInput.addEventListener('focus', function () {
            if (scrapMetal && scrapMetal.value) searchScrapProducts();
        });
        if (scrapMetal) {
            scrapMetal.addEventListener('change', function () {
                scrapProductInput.value = '';
                if (scrapProductId) scrapProductId.value = '';
                scrapProductList.style.display = 'none';
                scrapProductList.innerHTML = '';
            });
        }
        document.addEventListener('click', function (e) {
            if (
                scrapProductList.style.display === 'block' &&
                !scrapProductList.contains(e.target) &&
                e.target !== scrapProductInput
            ) {
                scrapProductList.style.display = 'none';
            }
        });
    })();

    document.getElementById('ifRecBtnSave').addEventListener('click', function () {
        saveInvestmentReceiptFromModal();
    });

    var _ifFilterT;
    function scheduleRenderFundList() {
        clearTimeout(_ifFilterT);
        _ifFilterT = setTimeout(function () {
            ifFundListPage = 1;
            renderFundList();
        }, 200);
    }
    document.getElementById('filterParty').addEventListener('input', scheduleRenderFundList);
    document.getElementById('filterScheme').addEventListener('input', scheduleRenderFundList);
    document.getElementById('ifFundListPageSize').addEventListener('change', function () {
        ifFundListPage = 1;
        renderFundList();
    });

    document.querySelectorAll('.if-fund-pg').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var party = (document.getElementById('filterParty') && document.getElementById('filterParty').value.trim().toLowerCase()) || '';
            var sch = (document.getElementById('filterScheme') && document.getElementById('filterScheme').value.trim().toLowerCase()) || '';
            var all = loadFundRecords().filter(function (r) {
                var okP = !party || String(r.customer_name || '').toLowerCase().indexOf(party) !== -1;
                var okS = !sch || String(r.scheme_label || '').toLowerCase().indexOf(sch) !== -1;
                return okP && okS;
            });
            var sizeEl = document.getElementById('ifFundListPageSize');
            var pageSize = sizeEl ? parseInt(sizeEl.value, 10) || 10 : 10;
            var pages = Math.max(1, Math.ceil(all.length / pageSize));
            var d = btn.getAttribute('data-delta');
            if (d === 'first') ifFundListPage = 1;
            else if (d === 'prev') ifFundListPage = Math.max(1, ifFundListPage - 1);
            else if (d === 'next') ifFundListPage = Math.min(pages, ifFundListPage + 1);
            else if (d === 'last') ifFundListPage = pages;
            renderFundList();
        });
    });

    if (!document.getElementById('currentFundRecordId').value) {
        document.getElementById('fundNoDisplay').textContent = getNextFundNo();
    }
    renderFundList();
})();

(function () {
    var STORAGE_KEY = 'auragold_investment_schemes_v1';
    var csPage = 1;
    var useSchemeDb = typeof window.IF_SCHEMES_USE_DB !== 'undefined' && !!window.IF_SCHEMES_USE_DB;
    var schemesCache = Array.isArray(window.IF_SCHEMES_INITIAL) ? window.IF_SCHEMES_INITIAL.slice() : [];

    function caratLabel(id) {
        var n = parseInt(id, 10);
        if (!CS_CARAT_OPTIONS || !CS_CARAT_OPTIONS.length) return '';
        for (var i = 0; i < CS_CARAT_OPTIONS.length; i++) {
            if (CS_CARAT_OPTIONS[i].id === n) return CS_CARAT_OPTIONS[i].label || '';
        }
        return String(id);
    }

    function syncKaratRequired() {
        var redEl = document.getElementById('csRedemption');
        var karatEl = document.getElementById('csKarat');
        var star = document.getElementById('csKaratReqStar');
        if (!redEl || !karatEl) return;
        var isAmount = String(redEl.value) === 'Amount';
        karatEl.required = !isAmount;
        if (star) star.style.display = isAmount ? 'none' : '';
    }

    function loadSchemes() {
        if (useSchemeDb) {
            return schemesCache;
        }
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            var a = JSON.parse(raw);
            return Array.isArray(a) ? a : [];
        } catch (e) {
            return [];
        }
    }

    window.IF_findSchemeById = function (id) {
        if (id == null || String(id).trim() === '') return null;
        var sid = String(id);
        var list = loadSchemes();
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].id) === sid) return list[i];
        }
        return null;
    };

    function saveSchemesLocal(arr) {
        if (!useSchemeDb) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
        }
    }

    function fetchSchemesFromApi() {
        if (!useSchemeDb) {
            return Promise.resolve();
        }
        return fetch('ajax/investment-schemes.php?action=list', { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (data && data.ok && Array.isArray(data.schemes)) {
                    schemesCache = data.schemes;
                }
            });
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function newSchemeForm() {
        document.getElementById('csEditId').value = '';
        document.getElementById('csSchemeName').value = '';
        document.getElementById('csRedemption').value = '';
        document.getElementById('csKarat').value = '';
        document.getElementById('csDurationVal').value = '12';
        document.getElementById('csDurationUnit').value = 'Month';
        document.getElementById('csInstType').value = '';
        document.getElementById('csInstAmt').value = '0';
        document.getElementById('csMinAmtChk').checked = false;
        document.getElementById('csMinAmt').value = '0';
        document.getElementById('csMinAmt').disabled = true;
        document.getElementById('csActive').checked = true;
        syncKaratRequired();
    }

    function fillForm(s) {
        document.getElementById('csEditId').value = String(s.id);
        document.getElementById('csSchemeName').value = s.scheme_name || '';
        document.getElementById('csRedemption').value = s.redemption_on || '';
        document.getElementById('csKarat').value = s.carat_id != null ? String(s.carat_id) : '';
        document.getElementById('csDurationVal').value = s.duration_value != null ? s.duration_value : 12;
        document.getElementById('csDurationUnit').value = s.duration_unit || 'Month';
        document.getElementById('csInstType').value = s.installment_type || '';
        document.getElementById('csInstAmt').value = s.installment_amt != null ? s.installment_amt : 0;
        document.getElementById('csMinAmtChk').checked = !!s.minimum_amt_enabled;
        document.getElementById('csMinAmt').value = s.minimum_amt != null ? s.minimum_amt : 0;
        document.getElementById('csMinAmt').disabled = !s.minimum_amt_enabled;
        document.getElementById('csActive').checked = s.active !== false;
        syncKaratRequired();
    }

    function renderMainSchemeDropdown() {
        var sel = document.getElementById('schemeName');
        if (!sel) return;
        var v = sel.value;
        var schemes = loadSchemes().filter(function (x) {
            return x.active !== false;
        });
        sel.innerHTML = '<option value="">Select scheme</option>';
        schemes.forEach(function (s) {
            var o = document.createElement('option');
            o.value = String(s.id);
            o.textContent = s.scheme_name || ('Scheme ' + s.id);
            sel.appendChild(o);
        });
        if (v && Array.prototype.some.call(sel.options, function (opt) { return opt.value === v; })) {
            sel.value = v;
        }
    }

    function renderSchemeList() {
        var tbody = document.getElementById('csListBody');
        var info = document.getElementById('csPageInfo');
        if (!tbody) return;
        var all = loadSchemes();
        var sizeEl = document.getElementById('csPageSize');
        var pageSize = sizeEl ? parseInt(sizeEl.value, 10) || 10 : 10;
        var total = all.length;
        var pages = Math.max(1, Math.ceil(total / pageSize));
        if (csPage > pages) csPage = pages;
        var start = (csPage - 1) * pageSize;
        var slice = all.slice(start, start + pageSize);
        tbody.innerHTML = '';
        if (!slice.length) {
            tbody.innerHTML =
                '<tr class="cs-list-empty"><td colspan="9" class="text-center text-muted py-4 small">No Rows To Show</td></tr>';
        } else {
            slice.forEach(function (s) {
                var dur = (s.duration_value != null ? s.duration_value : '') + ' ' + (s.duration_unit || '');
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="small" style="color:#000;">' +
                    escapeHtml(s.scheme_name) +
                    '</td>' +
                    '<td class="small">' +
                    escapeHtml(s.redemption_on) +
                    '</td>' +
                    '<td class="small">' +
                    escapeHtml(dur.trim()) +
                    '</td>' +
                    '<td class="small">' +
                    escapeHtml(s.installment_type) +
                    '</td>' +
                    '<td class="small text-right">' +
                    escapeHtml(s.installment_amt != null ? Number(s.installment_amt).toFixed(2) : '') +
                    '</td>' +
                    '<td class="text-center">' +
                    (s.active !== false ? '&#10003;' : '') +
                    '</td>' +
                    '<td class="text-center">' +
                    (s.minimum_amt_enabled ? '&#10003;' : '') +
                    '</td>' +
                    '<td class="small">' +
                    escapeHtml(s.carat_label || caratLabel(s.carat_id)) +
                    '</td>' +
                    '<td class="text-center">' +
                    '<button type="button" class="btn btn-sm btn-link p-0 cs-row-edit" data-id="' +
                    s.id +
                    '" title="Edit"><i class="feather icon-edit-2" style="font-size:14px;"></i></button> ' +
                    '<button type="button" class="btn btn-sm btn-link p-0 text-danger cs-row-del" data-id="' +
                    s.id +
                    '" title="Delete"><i class="feather icon-trash-2" style="font-size:14px;"></i></button></td>';
                tbody.appendChild(tr);
            });
        }
        var from = total === 0 ? 0 : start + 1;
        var to = total === 0 ? 0 : Math.min(start + slice.length, total);
        if (info) info.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' entries';
    }

    var csBtnSaveBottomEl = document.getElementById('csBtnSaveBottom');
    if (csBtnSaveBottomEl) {
        csBtnSaveBottomEl.addEventListener('click', function () {
            document.getElementById('csBtnSave').click();
        });
    }
    var csBtnNewBottomEl = document.getElementById('csBtnNewBottom');
    if (csBtnNewBottomEl) {
        csBtnNewBottomEl.addEventListener('click', function () {
            document.getElementById('csBtnNew').click();
        });
    }

    document.getElementById('csBtnNew').addEventListener('click', function () {
        newSchemeForm();
    });
    document.getElementById('csMinAmtChk').addEventListener('change', function () {
        document.getElementById('csMinAmt').disabled = !this.checked;
    });
    var csRedemptionEl = document.getElementById('csRedemption');
    if (csRedemptionEl) {
        csRedemptionEl.addEventListener('change', syncKaratRequired);
    }
    syncKaratRequired();

    document.getElementById('csBtnSave').addEventListener('click', function () {
        var form = document.getElementById('createSchemeForm');
        if (form && !form.checkValidity()) {
            form.reportValidity();
            return;
        }
        var name = document.getElementById('csSchemeName').value.trim();
        if (!name) {
            alert('Scheme Name is required.');
            return;
        }
        var redemptionOn = document.getElementById('csRedemption').value;
        var caratSel = document.getElementById('csKarat').value;
        if (String(redemptionOn) === 'Amount') {
            caratSel = '';
        }
        var caratId = caratSel ? parseInt(caratSel, 10) : null;
        var editIdRaw = document.getElementById('csEditId').value.trim();
        var row = {
            scheme_name: name,
            redemption_on: redemptionOn,
            carat_id: caratId,
            carat_label: caratSel ? caratLabel(caratSel) : '',
            duration_value: parseInt(document.getElementById('csDurationVal').value, 10) || 0,
            duration_unit: document.getElementById('csDurationUnit').value,
            installment_type: document.getElementById('csInstType').value,
            installment_amt: parseFloat(document.getElementById('csInstAmt').value) || 0,
            minimum_amt_enabled: document.getElementById('csMinAmtChk').checked,
            minimum_amt: parseFloat(document.getElementById('csMinAmt').value) || 0,
            active: document.getElementById('csActive').checked,
            bonus_rows: []
        };
        if (editIdRaw && /^\d+$/.test(editIdRaw)) {
            row.id = editIdRaw;
        }

        if (useSchemeDb) {
            fetch('ajax/investment-schemes.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(row)
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        alert((data && data.message) || 'Save failed.');
                        return Promise.reject(new Error('if_cs_abort'));
                    }
                    if (data.id) {
                        document.getElementById('csEditId').value = String(data.id);
                    }
                    return fetchSchemesFromApi();
                })
                .then(function () {
                    renderSchemeList();
                    renderMainSchemeDropdown();
                    alert('Scheme saved.');
                })
                .catch(function (err) {
                    if (err && err.message === 'if_cs_abort') return;
                    alert('Save failed.');
                });
            return;
        }

        row.id = editIdRaw || String(Date.now());
        var list = loadSchemes();
        if (editIdRaw) {
            var idx = list.findIndex(function (x) { return String(x.id) === String(editIdRaw); });
            if (idx >= 0) list[idx] = row;
            else list.push(row);
        } else {
            list.push(row);
        }
        saveSchemesLocal(list);
        renderSchemeList();
        renderMainSchemeDropdown();
        document.getElementById('csEditId').value = String(row.id);
        alert('Scheme saved.');
    });

    document.getElementById('csListBody').addEventListener('click', function (e) {
        var del = e.target.closest('.cs-row-del');
        var ed = e.target.closest('.cs-row-edit');
        if (del) {
            var id = del.getAttribute('data-id');
            if (!confirm('Delete this scheme?')) return;
            if (useSchemeDb) {
                var nid = parseInt(id, 10);
                if (isNaN(nid)) {
                    return;
                }
                fetch('ajax/investment-schemes.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id: nid })
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data || !data.ok) {
                            alert((data && data.message) || 'Delete failed.');
                            return Promise.reject(new Error('if_cs_del_abort'));
                        }
                        return fetchSchemesFromApi();
                    })
                    .then(function () {
                        renderSchemeList();
                        renderMainSchemeDropdown();
                        if (document.getElementById('csEditId').value === String(id)) {
                            newSchemeForm();
                        }
                    })
                    .catch(function (err) {
                        if (err && err.message === 'if_cs_del_abort') return;
                        alert('Delete failed.');
                    });
                return;
            }
            saveSchemesLocal(loadSchemes().filter(function (x) { return String(x.id) !== String(id); }));
            renderSchemeList();
            renderMainSchemeDropdown();
            if (document.getElementById('csEditId').value === String(id)) {
                newSchemeForm();
            }
            return;
        }
        if (ed) {
            var sid = ed.getAttribute('data-id');
            var s = loadSchemes().find(function (x) { return String(x.id) === String(sid); });
            if (s) fillForm(s);
        }
    });

    function csGoPage(delta) {
        var all = loadSchemes();
        var sizeEl = document.getElementById('csPageSize');
        var pageSize = sizeEl ? parseInt(sizeEl.value, 10) || 10 : 10;
        var pages = Math.max(1, Math.ceil(all.length / pageSize));
        csPage += delta;
        if (csPage < 1) csPage = 1;
        if (csPage > pages) csPage = pages;
        renderSchemeList();
    }

    document.getElementById('csPgFirst').addEventListener('click', function () {
        csPage = 1;
        renderSchemeList();
    });
    document.getElementById('csPgPrev').addEventListener('click', function () {
        csGoPage(-1);
    });
    document.getElementById('csPgNext').addEventListener('click', function () {
        csGoPage(1);
    });
    document.getElementById('csPgLast').addEventListener('click', function () {
        var all = loadSchemes();
        var sizeEl = document.getElementById('csPageSize');
        var pageSize = sizeEl ? parseInt(sizeEl.value, 10) || 10 : 10;
        csPage = Math.max(1, Math.ceil(all.length / pageSize));
        renderSchemeList();
    });
    document.getElementById('csPageSize').addEventListener('change', function () {
        csPage = 1;
        renderSchemeList();
    });

    document.getElementById('csExportCsv').addEventListener('click', function (e) {
        e.preventDefault();
        var all = loadSchemes();
        var headers = ['Scheme Name', 'Redemption', 'Duration', 'Inst. Type', 'Inst. Amt.', 'Active', 'Min. Amt. enabled', 'Min. Amt.', 'Karat'];
        var lines = [headers.join(',')];
        all.forEach(function (s) {
            var dur = (s.duration_value != null ? s.duration_value : '') + ' ' + (s.duration_unit || '');
            lines.push(
                [
                    '"' + String(s.scheme_name || '').replace(/"/g, '""') + '"',
                    s.redemption_on || '',
                    '"' + dur.trim().replace(/"/g, '""') + '"',
                    s.installment_type || '',
                    s.installment_amt != null ? s.installment_amt : '',
                    s.active !== false ? 'Yes' : 'No',
                    s.minimum_amt_enabled ? 'Yes' : 'No',
                    s.minimum_amt != null ? s.minimum_amt : '',
                    '"' + String(s.carat_label || caratLabel(s.carat_id)).replace(/"/g, '""') + '"'
                ].join(',')
            );
        });
        var blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'investment-schemes.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    });

    $('#createSchemeModal').on('shown.bs.modal', function () {
        csPage = 1;
        syncKaratRequired();
        fetchSchemesFromApi().then(function () {
            renderSchemeList();
        });
    });

    function applyInvestmentFundUrlParams() {
        try {
            var qs = new URLSearchParams(window.location.search);
            var tab = qs.get('tab');
            if (tab === 'layaways' && window.jQuery) {
                window.jQuery('a[href="#tabLayawaysReport"]').tab('show');
            } else if (tab === 'installment_report' && window.jQuery) {
                window.jQuery('a[href="#tabInstallmentReport"]').tab('show');
            }
            var fid = qs.get('fund_id');
            var fno = qs.get('fund_no');
            if (fid || fno) {
                var list = loadFundRecords();
                var rec = list.filter(function (x) {
                    if (fid && String(x.id) === String(fid)) return true;
                    if (fno && String(x.fund_no || '').toLowerCase() === String(fno).toLowerCase()) return true;
                    return false;
                })[0];
                if (rec) {
                    selectedFundListId = rec.id;
                    var el = document.getElementById('currentFundRecordId');
                    if (el) el.value = rec.id;
                    var fd = document.getElementById('fundNoDisplay');
                    if (fd) fd.textContent = rec.fund_no || '';
                    populateDetailView(rec);
                    setInvestmentMainView('detail');
                    renderFundList();
                }
            }
        } catch (e) {}
    }
    if (window.jQuery) {
        window.jQuery(applyInvestmentFundUrlParams);
    } else {
        applyInvestmentFundUrlParams();
    }

    fetchSchemesFromApi().then(function () {
        renderMainSchemeDropdown();
    });
})();
</script>
</body>
</html>
