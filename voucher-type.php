<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/branch_working_context.php';
if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

$eff_branch = function_exists('auragold_effective_branch_id') ? auragold_effective_branch_id() : 0;
$vt_settings_branch = 0;
$branch_select_options = [];
$vt_branch_display_name = '';

if ($eff_branch > 0) {
    $vt_settings_branch = $eff_branch;
    if (function_exists('getRecordMaster')) {
        $bx = getRecordMaster('SELECT name FROM tbl_branches WHERE id = ' . (int) $eff_branch . ' LIMIT 1');
        if ($bx) {
            $vt_branch_display_name = trim((string) ($bx['name'] ?? ''));
        }
    }
    if ($vt_branch_display_name === '') {
        $vt_branch_display_name = 'Branch #' . (int) $eff_branch;
    }
} else {
    $req = isset($_GET['vt_branch']) ? (int) $_GET['vt_branch'] : 0;
    if ($req > 0 && function_exists('getRecordMaster')) {
        $br = getRecordMaster('SELECT id, main_branch_id, name, status FROM tbl_branches WHERE id = ' . $req . ' LIMIT 1');
        if ($br && (int) ($br['status'] ?? 1) === 1 && auragold_can_user_open_branch_row($br)) {
            $vt_settings_branch = $req;
        }
    }
    $main_bid = function_exists('auragold_settings_main_branch_id') ? auragold_settings_main_branch_id() : 0;
    if ($vt_settings_branch <= 0) {
        $vt_settings_branch = $main_bid > 0 ? $main_bid : 0;
    }
    if ($vt_settings_branch <= 0 && function_exists('getListMaster')) {
        $first = getListMaster('SELECT id FROM tbl_branches ORDER BY id ASC LIMIT 1');
        if ($first && !empty($first[0]['id'])) {
            $vt_settings_branch = (int) $first[0]['id'];
        }
    }
    if (function_exists('getListMaster')) {
        $allBranches = getListMaster('SELECT id, name, main_branch_id, status FROM tbl_branches ORDER BY main_branch_id ASC, id ASC');
        if (is_array($allBranches)) {
            foreach ($allBranches as $b) {
                if ((int) ($b['status'] ?? 1) !== 1) {
                    continue;
                }
                if (!auragold_can_user_open_branch_row($b)) {
                    continue;
                }
                $branch_select_options[] = $b;
            }
        }
    }
}

/** Master list (1–65) for "Types of voucher" dropdown */
$voucher_type_names = [
    'Advance Payment', 'Appraisal', 'Assign Inventory', 'Bill Of Material', 'Broken Entry',
    'Catalogue Quotation', 'Consignment In', 'Consignment Out', 'Contra Voucher', 'Credit Note',
    'Customer Advance', 'Daily Salary Voucher', 'Debit Note', 'Delivery Note', 'Expense Invoice',
    'Fund Transfer', 'Fund Withdraw', 'Income Invoice', 'Investment Fund', 'Jewelry Catalogue',
    'Jobwork Invoice', 'Jobwork Order', 'Jobwork Queue', 'JobworkQueue Master', 'Journal Voucher',
    'Loan', 'Loan Release', 'Material In', 'Material Issue', 'Material Out', 'Material Receipt',
    'Material Receive', 'Monthly Salary Voucher', 'Old Jewelry - Scrap Invoice', 'Opening Balance',
    'Opening Stock', 'Payment Voucher', 'PDC Clearance', 'PDC Payable', 'PDC Receivable',
    'Physical Stock', 'POS', 'Purchase Fixing', 'Purchase Fixing Direct Invoice', 'Purchase Invoice',
    'Purchase Order', 'Purchase Quotation', 'Purchase Return', 'Receipt Voucher', 'Rejection In',
    'Rejection Out', 'Repair Invoice', 'Repair Order', 'Sale Fixing', 'Sale Fixing Direct Invoice',
    'Sales Invoice', 'Sales Order', 'Sales Quotation', 'Sales Return', 'Service Voucher',
    'Stock Journal', 'Stock Transfer In', 'Stock Transfer Out', 'Task / Event', 'UnAssign Inventory',
];

$sql = "SELECT id, name, type_of_voucher, status FROM tbl_voucher_types ORDER BY id ASC";
$vouchers = getList($sql);
$metal_suffix = (function_exists('auragold_master_list_sql_for_branch_id') && $vt_settings_branch > 0)
    ? auragold_master_list_sql_for_branch_id($conn, 'tbl_metal', $vt_settings_branch)
    : '';
$metals = getList("SELECT id, display_name FROM tbl_metal WHERE status=1 $metal_suffix ORDER BY id");
$tax_suffix = (function_exists('auragold_master_list_sql_for_branch_id') && $vt_settings_branch > 0)
    ? auragold_master_list_sql_for_branch_id($conn, 'tbl_taxes', $vt_settings_branch)
    : '';
$taxes = getList("SELECT id, name, applicable_for FROM tbl_taxes WHERE status=1 $tax_suffix ORDER BY id");
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Voucher Type Configuration - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid vt-page">
            <div class="vt-toolbar">
                <h4 class="vt-title">Voucher Type Configuration</h4>
                <div class="vt-toolbar-actions d-flex align-items-center flex-wrap">
                    <?php if ($eff_branch <= 0 && count($branch_select_options) > 0) { ?>
                    <div class="vt-branch-wrap d-flex align-items-center mr-2 mb-1 mb-md-0">
                        <label for="vtBranchSelect" class="mb-0 mr-2" style="font-size:0.8rem;font-weight:600;color:#334155;white-space:nowrap;">Branch</label>
                        <select id="vtBranchSelect" class="form-control form-control-sm" style="min-width:168px;" title="Settings apply to this branch">
                            <?php foreach ($branch_select_options as $b) {
                                $bid = (int) ($b['id'] ?? 0);
                                $sel = ($bid === (int) $vt_settings_branch) ? ' selected' : '';
                            ?>
                            <option value="<?php echo $bid; ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($b['name'] ?? ''); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } elseif ($eff_branch > 0 && $vt_branch_display_name !== '') { ?>
                    <span class="small mr-2 mb-1 mb-md-0" style="color:#475569;">Branch: <strong><?php echo htmlspecialchars($vt_branch_display_name); ?></strong></span>
                    <?php } ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary vt-icon-btn" onclick="refreshVoucherList()" title="Refresh">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                    <button type="button" class="btn btn-vt-update btn-sm" onclick="saveVoucherType()">Update</button>
                </div>
            </div>

            <form id="voucherTypeForm">
                <input type="hidden" id="voucherTypeId">
                <input type="hidden" id="vtSettingsBranchId" value="<?php echo (int) $vt_settings_branch; ?>">

                <div class="vt-grid">
                    <!-- 1: Voucher list -->
                    <aside class="vt-panel vt-panel-list">
                        <p class="vt-sec-title">Voucher List</p>
                        <div class="vt-list-search">
                            <input type="text" id="searchVoucherType" class="form-control form-control-sm" placeholder="Search" onkeyup="filterVoucherList()" autocomplete="off">
                        </div>
                        <div class="vt-list-scroll">
                            <table class="table table-sm table-bordered mb-0 vt-table">
                                <thead>
                                    <tr>
                                        <th style="width:56px;">Sr No</th>
                                        <th>Voucher Type</th>
                                    </tr>
                                </thead>
                                <tbody id="voucherListTableBody">
                                    <?php
                                    $sr = 1;
                                    if (count($vouchers) > 0) {
                                        foreach ($vouchers as $row) {
                                            $dim = ($row['status'] == 0) ? 'opacity:0.55;' : '';
                                    ?>
                                    <tr class="voucher-row" data-id="<?php echo (int) $row['id']; ?>" onclick="loadVoucherType(<?php echo (int) $row['id']; ?>)" style="<?php echo $dim; ?>">
                                        <td><?php echo $sr++; ?></td>
                                        <td><a href="javascript:void(0)" class="vt-link"><?php echo htmlspecialchars($row['name']); ?></a></td>
                                    </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="2" class="text-center text-muted">No voucher types found</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="vt-list-pager" id="voucherListPager" role="navigation" aria-label="Voucher list pages">
                            <button type="button" class="btn btn-sm btn-outline-secondary vt-pager-btn" id="voucherListPrev" title="Previous page">‹</button>
                            <span class="vt-pager-info" id="voucherListPageInfo">Page 1 of 1</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary vt-pager-btn" id="voucherListNext" title="Next page">›</button>
                        </div>
                    </aside>

                    <!-- 2: General information -->
                    <section class="vt-panel vt-panel-general">
                        <p class="vt-sec-title">General Information</p>
                        <div class="vt-form-stack">
                            <div class="vt-field">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" id="voucherName" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="vt-field">
                                <label>Method of Voucher Numbering <span class="text-danger">*</span></label>
                                <input type="text" id="voucherNumbering" class="form-control form-control-sm" required>
                            </div>
                            <div class="vt-field">
                                <label>Types of voucher</label>
                                <select id="typeOfVoucher" class="form-control form-control-sm">
                                    <?php foreach ($voucher_type_names as $vn) { ?>
                                    <option value="<?php echo htmlspecialchars($vn); ?>"><?php echo htmlspecialchars($vn); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="vt-field">
                                <label>Calculate Amount By Formula</label>
                                <select id="calculateAmountBy" class="form-control form-control-sm">
                                    <option value="Rate X Gross Wt">Rate X Gross Wt</option>
                                    <option value="Rate X Purity Wt">Rate X Purity Wt</option>
                                    <option value="Rate X Net Wt">Rate X Net Wt</option>
                                    <option value="Rate X Final Wt">Rate X Final Wt</option>
                                </select>
                            </div>
                            <div class="vt-field">
                                <label>calculate wastage weight by</label>
                                <select id="calculateWastageBy" class="form-control form-control-sm">
                                    <option value="Net Wt">Net Wt</option>
                                    <option value="Purity Wt">Purity Wt</option>
                                    <option value="Gross Wt">Gross Wt</option>
                                </select>
                            </div>
                            <div class="vt-field">
                                <label>Calculate Loss Weight By</label>
                                <select id="calculateLossBy" class="form-control form-control-sm">
                                    <option value="Net Wt">Net Wt</option>
                                    <option value="Purity Wt">Purity Wt</option>
                                    <option value="Gross Wt">Gross Wt</option>
                                </select>
                            </div>
                            <div class="vt-field">
                                <label>Fixing Type</label>
                                <select id="fixingType" name="fixing_type" class="form-control form-control-sm vt-select-fixing">
                                    <option value="Standard">Standard</option>
                                    <option value="Hedging">Hedging</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <!-- 3: General settings flags -->
                    <section class="vt-panel vt-panel-flags">
                        <p class="vt-sec-title vt-sec-title-muted">General Settings</p>
                        <div class="vt-flag-grid">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="doNotApplyOnStock"><label class="form-check-label" for="doNotApplyOnStock">Do Not Apply On Stock</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="metalUnFix"><label class="form-check-label" for="metalUnFix">Metal Unfix</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="doNotAllow0Amount"><label class="form-check-label" for="doNotAllow0Amount">Do Not Allow 0 Amount Product</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="salesPersonsMandatory"><label class="form-check-label" for="salesPersonsMandatory">Sales Person Mandatory</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="internalUnFix"><label class="form-check-label" for="internalUnFix">Internal Unfix</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="paymentMandatory"><label class="form-check-label" for="paymentMandatory">Payment Mandatory</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="enableItemFastFields"><label class="form-check-label" for="enableItemFastFields">Enable Item Fast Fields</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="calculateMarkupOnSale"><label class="form-check-label" for="calculateMarkupOnSale">Calculate Markup on Sale Amount</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="createAutoJournalVoucher"><label class="form-check-label" for="createAutoJournalVoucher">Create Auto Journal Voucher With Credit Card Policy</label></div>
                        </div>
                    </section>

                    <!-- 4: Metal allocation -->
                    <section class="vt-panel vt-panel-metal">
                        <p class="vt-sec-title">Metal Allocation</p>
                        <div class="vt-sub-scroll">
                            <table class="table table-sm table-bordered mb-0 vt-table">
                                <thead>
                                    <tr>
                                        <th style="width:44px;"></th>
                                        <th>Metals</th>
                                        <th style="width:100px;">Discount (%)</th>
                                    </tr>
                                </thead>
                                <tbody id="metalAllocationTableBody">
                                    <?php
                                    if (count($metals) > 0) {
                                        foreach ($metals as $metal) {
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="metal-checkbox" data-metal-id="<?php echo (int) $metal['id']; ?>">
                                        </td>
                                        <td><?php echo htmlspecialchars($metal['display_name']); ?></td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm metal-discount" data-metal-id="<?php echo (int) $metal['id']; ?>" value="0" step="0.01" min="0" max="100">
                                        </td>
                                    </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="3" class="text-center text-muted">No metals found</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- 5: Field visibility -->
                    <section class="vt-panel vt-panel-fv">
                        <p class="vt-sec-title">Field Visibility</p>
                        <div class="vt-fv-grid">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldReferenceNo"><label class="form-check-label" for="fieldReferenceNo">Reference No.</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowFixingType"><label class="form-check-label" for="fieldShowFixingType">Fixing Type</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowMetalUnfix"><label class="form-check-label" for="fieldShowMetalUnfix">show metal unfix</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowPaymentTerm"><label class="form-check-label" for="fieldShowPaymentTerm">Show Payment Term</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldAgainstOf"><label class="form-check-label" for="fieldAgainstOf">Against Of</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldCurrency"><label class="form-check-label" for="fieldCurrency">currency</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowUnfix"><label class="form-check-label" for="fieldShowUnfix">show unfix</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowShippingMethod"><label class="form-check-label" for="fieldShowShippingMethod">show shipping Method</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldDueDate"><label class="form-check-label" for="fieldDueDate">Due Date</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowBarcodeNo"><label class="form-check-label" for="fieldShowBarcodeNo">show barcode no</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowOunceRate"><label class="form-check-label" for="fieldShowOunceRate">show ounce rate</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowLeadSource"><label class="form-check-label" for="fieldShowLeadSource">show lead source</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldSalesPerson"><label class="form-check-label" for="fieldSalesPerson">Sales Person</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowDesignNo"><label class="form-check-label" for="fieldShowDesignNo">show design no</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowProductCode"><label class="form-check-label" for="fieldShowProductCode">show product code</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldLayaways"><label class="form-check-label" for="fieldLayaways">Layaways</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowDmdOrNamUnfix"><label class="form-check-label" for="fieldShowDmdOrNamUnfix">show DMD or nam unfix</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="fieldShowUpdateTaxDropdown"><label class="form-check-label" for="fieldShowUpdateTaxDropdown">show update tax dropdown</label></div>
                        </div>
                    </section>

                    <!-- 6: Tax allocation -->
                    <section class="vt-panel vt-panel-tax">
                        <p class="vt-sec-title">Tax Allocation</p>
                        <div class="vt-sub-scroll">
                            <table class="table table-sm table-bordered mb-0 vt-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"></th>
                                        <th style="width:48px;">Sr No</th>
                                        <th>Tax Name</th>
                                        <th>Applicable For</th>
                                    </tr>
                                </thead>
                                <tbody id="taxAllocationTableBody">
                                    <?php
                                    $tx = 1;
                                    if (count($taxes) > 0) {
                                        foreach ($taxes as $tax) {
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="tax-checkbox" data-tax-id="<?php echo (int) $tax['id']; ?>">
                                        </td>
                                        <td class="text-center"><?php echo $tx++; ?></td>
                                        <td><?php echo htmlspecialchars($tax['name']); ?></td>
                                        <td><?php echo htmlspecialchars($tax['applicable_for'] ?? ''); ?></td>
                                    </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="4" class="text-center text-muted">No taxes found</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- 7: Payment button visibility -->
                    <section class="vt-panel vt-panel-pay">
                        <p class="vt-sec-title">Payment Button Visibility</p>
                        <div class="vt-pay-grid">
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payCash" data-k="cash" checked><label class="form-check-label" for="payCash">cash</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payMetalExchange" data-k="metal_exchange" checked><label class="form-check-label" for="payMetalExchange">metal exchange</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payBank" data-k="bank" checked><label class="form-check-label" for="payBank">bank</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payScrap" data-k="scrap" checked><label class="form-check-label" for="payScrap">scrap</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payCheque" data-k="cheque" checked><label class="form-check-label" for="payCheque">cheque</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payAddDiamond" data-k="add_diamond" checked><label class="form-check-label" for="payAddDiamond">add diamond</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payUpi" data-k="upi" checked><label class="form-check-label" for="payUpi">UPI</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payCard" data-k="card" checked><label class="form-check-label" for="payCard">Card payment</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payAddStone" data-k="add_stone" checked><label class="form-check-label" for="payAddStone">add stone</label></div>
                            <div class="form-check"><input class="form-check-input pay-btn-cb" type="checkbox" id="payAddOldJewellery" data-k="add_old_jewellery" checked><label class="form-check-label" for="payAddOldJewellery">Add old jewellery</label></div>
                        </div>
                    </section>
                </div>
            </form>
        </div>
    </div>

    <div class="layout-overlay layout-sidenav-toggle"></div>
    <?php include 'footer-script.php'; ?>

    <style>
    .vt-page { padding: 16px 20px 24px; max-width: 100%; }
    .vt-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
    .vt-title { margin: 0; color: #11294b; font-weight: 700; font-size: 1.15rem; }
    .vt-toolbar-actions { display: flex; align-items: center; gap: 8px; }
    .vt-icon-btn { border-radius: 50%; width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .btn-vt-update {
        background: linear-gradient(135deg, #c5a864 0%, #a68a4a 100%);
        color: #fff; border: none; font-weight: 600; padding: 8px 20px; border-radius: 6px;
        box-shadow: 0 2px 6px rgba(197, 168, 100, 0.35);
    }
    .btn-vt-update:hover { color: #fff; opacity: 0.95; }

    .vt-grid {
        display: grid;
        grid-template-columns: 260px 1fr 1fr;
        grid-template-rows: auto auto auto;
        gap: 14px;
        align-items: start;
    }
    .vt-panel-list { grid-column: 1; grid-row: 1 / -1; align-self: start; }
    .vt-panel-general { grid-column: 2; grid-row: 1; }
    .vt-panel-flags { grid-column: 3; grid-row: 1; }
    .vt-panel-metal { grid-column: 2; grid-row: 2; }
    .vt-panel-fv { grid-column: 3; grid-row: 2; }
    .vt-panel-tax { grid-column: 2; grid-row: 3; }
    .vt-panel-pay { grid-column: 3; grid-row: 3; }

    .vt-panel {
        background: #fff;
        border: 1px solid #e6e6f0;
        border-radius: 12px;
        padding: 14px 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        display: flex;
        flex-direction: column;
        min-height: 0;
        height: auto;
    }
    .vt-panel-general, .vt-panel-flags, .vt-panel-metal, .vt-panel-fv, .vt-panel-tax, .vt-panel-pay {
        align-self: start;
        width: 100%;
    }
    .vt-sec-title {
        font-weight: 700;
        font-size: 0.88rem;
        color: #11294b;
        margin: 0 0 10px 0;
        padding: 8px 10px;
        background: linear-gradient(180deg, #e8e4f3 0%, #ddd8f0 100%);
        border-radius: 8px;
        border: 1px solid #d4cef0;
    }
    .vt-sec-title-muted { background: linear-gradient(180deg, #eef0f8 0%, #e4e7f2 100%); border-color: #d8dce8; }

    .vt-form-stack { display: flex; flex-direction: column; gap: 8px; }
    .vt-field { display: grid; grid-template-columns: minmax(160px, 42%) 1fr; gap: 8px 12px; align-items: center; }
    .vt-field label { margin: 0; font-size: 0.8rem; font-weight: 600; color: #334155; }
    .vt-select-fixing { border-radius: 6px; }
    .vt-select-fixing:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 0.15rem rgba(59, 130, 246, 0.22);
        outline: none;
    }

    .vt-flag-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 12px;
        font-size: 0.8rem;
    }
    .vt-fv-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 10px;
        font-size: 0.78rem;
        max-height: 280px;
        overflow-y: auto;
        padding-right: 4px;
    }
    .vt-pay-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 12px;
        font-size: 0.8rem;
    }

    .vt-list-search { margin-bottom: 8px; }
    .vt-list-scroll {
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        flex: 0 0 auto;
    }
    .vt-list-pager {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    .vt-pager-info { font-size: 0.78rem; color: #475569; white-space: nowrap; }
    .vt-pager-btn { min-width: 34px; padding: 4px 10px; }
    .vt-sub-scroll { min-height: 0; max-height: 240px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
    .vt-panel-metal .vt-sub-scroll, .vt-panel-tax .vt-sub-scroll { max-height: 220px; }

    .vt-table { margin: 0; font-size: 0.82rem; }
    .vt-table thead th {
        background: #11294b !important;
        color: #ffffff !important;
        font-weight: 600;
        padding: 6px 8px;
        border-color: rgba(255, 255, 255, 0.2) !important;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .vt-table td { padding: 6px 8px; vertical-align: middle; border-color: #eef0f4; }

    .vt-link { color: #11294b; text-decoration: none; }
    .voucher-row { cursor: pointer; }
    .voucher-row:hover { background: #f8fafc; }
    .voucher-row.active { background: #e0e7ff !important; font-weight: 600; }

    .form-check { margin-bottom: 0; padding-left: 1.35rem; }
    .form-check-label { font-weight: 400; color: #1e293b; }

    @media (max-width: 1400px) {
        .vt-grid { grid-template-columns: 1fr 1fr; grid-template-rows: auto; }
        .vt-panel-list { grid-column: 1 / -1; grid-row: auto; align-self: start; }
        .vt-panel-general { grid-column: 1; grid-row: auto; }
        .vt-panel-flags { grid-column: 2; grid-row: auto; }
        .vt-panel-metal { grid-column: 1; grid-row: auto; }
        .vt-panel-fv { grid-column: 2; grid-row: auto; }
        .vt-panel-tax { grid-column: 1; grid-row: auto; }
        .vt-panel-pay { grid-column: 2; grid-row: auto; }
    }
    @media (max-width: 900px) {
        .vt-grid { grid-template-columns: 1fr; }
        .vt-field { grid-template-columns: 1fr; }
        .vt-flag-grid, .vt-fv-grid, .vt-pay-grid { grid-template-columns: 1fr; }
    }
    </style>

    <script>
    let currentVoucherId = null;
    let voucherListPage = 1;
    const voucherListPageSize = 10;

    function getVoucherRows() {
        return Array.prototype.slice.call(document.querySelectorAll('#voucherListTableBody tr.voucher-row'));
    }

    function applyVoucherListPagination() {
        const q = (document.getElementById('searchVoucherType').value || '').toLowerCase().trim();
        const rows = getVoucherRows();
        const filtered = rows.filter(function(r) {
            const t = r.cells[1] ? r.cells[1].textContent.toLowerCase() : '';
            return !q || t.indexOf(q) !== -1;
        });
        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / voucherListPageSize));
        if (voucherListPage > totalPages) voucherListPage = totalPages;
        if (voucherListPage < 1) voucherListPage = 1;

        const start = (voucherListPage - 1) * voucherListPageSize;
        const end = start + voucherListPageSize;

        rows.forEach(function(r) {
            var fi = filtered.indexOf(r);
            if (fi === -1) {
                r.style.display = 'none';
            } else {
                r.style.display = (fi >= start && fi < end) ? '' : 'none';
            }
        });

        var info = document.getElementById('voucherListPageInfo');
        var prevBtn = document.getElementById('voucherListPrev');
        var nextBtn = document.getElementById('voucherListNext');
        if (info) {
            if (total === 0) {
                info.textContent = 'No items';
            } else {
                var from = total === 0 ? 0 : (start + 1);
                var to = Math.min(end, total);
                info.textContent = 'Page ' + voucherListPage + ' of ' + totalPages + ' (' + from + '–' + to + ' of ' + total + ')';
            }
        }
        if (prevBtn) prevBtn.disabled = voucherListPage <= 1 || total === 0;
        if (nextBtn) nextBtn.disabled = voucherListPage >= totalPages || total === 0;
    }

    function filterVoucherList() {
        voucherListPage = 1;
        applyVoucherListPagination();
    }

    function collectPaymentButtons() {
        const o = {};
        document.querySelectorAll('.pay-btn-cb').forEach(cb => {
            const k = cb.getAttribute('data-k');
            if (k) o[k] = cb.checked ? 1 : 0;
        });
        return o;
    }

    function applyPaymentButtons(pb) {
        if (!pb || typeof pb !== 'object') return;
        document.querySelectorAll('.pay-btn-cb').forEach(cb => {
            const k = cb.getAttribute('data-k');
            if (k && pb[k] !== undefined) cb.checked = pb[k] == 1;
        });
    }

    function loadVoucherType(id) {
        currentVoucherId = id;
        document.querySelectorAll('.voucher-row').forEach(row => row.classList.remove('active'));
        const selectedRow = document.querySelector('.voucher-row[data-id="' + id + '"]');
        if (selectedRow) selectedRow.classList.add('active');

        var vtBrEl = document.getElementById('vtSettingsBranchId');
        var vtBr = vtBrEl ? vtBrEl.value : '0';
        $.ajax({
            url: 'ajax/voucher-type.php',
            type: 'POST',
            data: { action: 'get', id: id, branch_id: vtBr },
            dataType: 'json',
            success: function(response) {
                if (response.status !== 'success' || !response.data) {
                    alert(response.message || 'Voucher type not found');
                    return;
                }
                const data = response.data;

                document.getElementById('voucherTypeId').value = data.id;
                document.getElementById('voucherName').value = data.name || '';
                document.getElementById('voucherNumbering').value = data.method_of_numbering || '';
                const tov = data.type_of_voucher || data.name || '';
                const sel = document.getElementById('typeOfVoucher');
                let found = false;
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === tov) { sel.selectedIndex = i; found = true; break; }
                }
                if (!found && tov) {
                    const opt = document.createElement('option');
                    opt.value = tov; opt.textContent = tov;
                    sel.appendChild(opt);
                    sel.value = tov;
                }

                document.getElementById('calculateAmountBy').value = data.calculate_amount_by || 'Rate X Gross Wt';
                document.getElementById('calculateWastageBy').value = data.calculate_wastage_by || 'Net Wt';
                document.getElementById('calculateLossBy').value = data.calculate_loss_by || 'Net Wt';
                (function() {
                    var raw = (data.fixing_type != null && data.fixing_type !== '') ? String(data.fixing_type).trim() : 'Standard';
                    var norm = (raw === 'Hedging' || raw.toLowerCase() === 'hedging') ? 'Hedging' : 'Standard';
                    document.getElementById('fixingType').value = norm;
                })();

                document.getElementById('doNotApplyOnStock').checked = data.do_not_apply_on_stock == 1;
                document.getElementById('salesPersonsMandatory').checked = data.sales_persons_mandatory == 1;
                document.getElementById('createAutoJournalVoucher').checked = data.create_auto_journal_voucher == 1;
                document.getElementById('metalUnFix').checked = data.metal_unfix == 1;
                document.getElementById('internalUnFix').checked = data.internal_unfix == 1;
                document.getElementById('doNotAllow0Amount').checked = data.do_not_allow_0_amount == 1;
                document.getElementById('paymentMandatory').checked = data.payment_mandatory == 1;
                document.getElementById('calculateMarkupOnSale').checked = data.calculate_markup_on_sale == 1;
                var eff = document.getElementById('enableItemFastFields');
                if (eff) eff.checked = (data.enable_item_fast_fields == 1);

                document.querySelectorAll('.metal-checkbox').forEach(cb => { cb.checked = false; });
                document.querySelectorAll('.metal-discount').forEach(inp => { inp.value = 0; });
                if (response.metal_allocations && response.metal_allocations.length) {
                    response.metal_allocations.forEach(ma => {
                        const c = document.querySelector('.metal-checkbox[data-metal-id="' + ma.metal_id + '"]');
                        const d = document.querySelector('.metal-discount[data-metal-id="' + ma.metal_id + '"]');
                        if (c) c.checked = true;
                        if (d) d.value = ma.discount || 0;
                    });
                }

                document.querySelectorAll('.tax-checkbox').forEach(cb => { cb.checked = false; });
                if (response.tax_allocations && response.tax_allocations.length) {
                    response.tax_allocations.forEach(ta => {
                        const c = document.querySelector('.tax-checkbox[data-tax-id="' + ta.tax_id + '"]');
                        if (c) c.checked = true;
                    });
                }

                const fv = response.field_visibility;
                if (fv) {
                    document.getElementById('fieldReferenceNo').checked = fv.reference_no == 1;
                    document.getElementById('fieldSalesPerson').checked = fv.sales_person == 1;
                    document.getElementById('fieldCurrency').checked = fv.currency == 1;
                    document.getElementById('fieldAgainstOf').checked = fv.against_of == 1;
                    document.getElementById('fieldLayaways').checked = fv.layaways == 1;
                    document.getElementById('fieldDueDate').checked = fv.due_date == 1;
                    var sb = (fv.show_billing_type !== undefined) ? fv.show_billing_type : fv.fixing_type;
                    document.getElementById('fieldShowFixingType').checked = sb == 1;
                    document.getElementById('fieldShowMetalUnfix').checked = fv.show_metal_unfix == 1;
                    document.getElementById('fieldShowPaymentTerm').checked = fv.show_payment_term == 1;
                    document.getElementById('fieldShowUnfix').checked = fv.show_unfix == 1;
                    document.getElementById('fieldShowShippingMethod').checked = fv.show_shipping_method == 1;
                    document.getElementById('fieldShowBarcodeNo').checked = fv.show_barcode_no == 1;
                    document.getElementById('fieldShowOunceRate').checked = fv.show_ounce_rate == 1;
                    document.getElementById('fieldShowLeadSource').checked = fv.show_lead_source == 1;
                    document.getElementById('fieldShowDesignNo').checked = fv.show_design_no == 1;
                    document.getElementById('fieldShowProductCode').checked = fv.show_product_code == 1;
                    document.getElementById('fieldShowDmdOrNamUnfix').checked = fv.show_dmd_or_nam_unfix == 1;
                    document.getElementById('fieldShowUpdateTaxDropdown').checked = fv.show_update_tax_dropdown == 1;
                }

                applyPaymentButtons(response.payment_buttons);
            },
            error: function(xhr, status, err) {
                var msg = 'Error loading voucher type.';
                if (xhr && xhr.status) {
                    msg += ' (HTTP ' + xhr.status + ')';
                }
                if (xhr && xhr.responseText && xhr.responseText.indexOf('{') !== 0) {
                    msg += ' If this continues, check the server PHP error log.';
                }
                console.warn('voucher-type get', status, err, xhr && xhr.responseText);
                alert(msg);
            }
        });
    }

    function saveVoucherType() {
        const form = document.getElementById('voucherTypeForm');
        if (!currentVoucherId) {
            alert('Select a voucher type from the list.');
            return;
        }
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = {
            action: 'update',
            id: currentVoucherId,
            name: document.getElementById('voucherName').value,
            method_of_numbering: document.getElementById('voucherNumbering').value,
            type_of_voucher: document.getElementById('typeOfVoucher').value,
            calculate_amount_by: document.getElementById('calculateAmountBy').value,
            calculate_wastage_by: document.getElementById('calculateWastageBy').value,
            fixing_type: document.getElementById('fixingType').value,
            calculate_loss_by: document.getElementById('calculateLossBy').value,
            do_not_apply_on_stock: document.getElementById('doNotApplyOnStock').checked ? 1 : 0,
            sales_persons_mandatory: document.getElementById('salesPersonsMandatory').checked ? 1 : 0,
            create_auto_journal_voucher: document.getElementById('createAutoJournalVoucher').checked ? 1 : 0,
            metal_unfix: document.getElementById('metalUnFix').checked ? 1 : 0,
            internal_unfix: document.getElementById('internalUnFix').checked ? 1 : 0,
            do_not_allow_0_amount: document.getElementById('doNotAllow0Amount').checked ? 1 : 0,
            payment_mandatory: document.getElementById('paymentMandatory').checked ? 1 : 0,
            calculate_markup_on_sale: document.getElementById('calculateMarkupOnSale').checked ? 1 : 0,
            enable_item_fast_fields: document.getElementById('enableItemFastFields').checked ? 1 : 0,
            metal_allocations: [],
            tax_allocations: [],
            field_visibility: {
                reference_no: document.getElementById('fieldReferenceNo').checked ? 1 : 0,
                sales_person: document.getElementById('fieldSalesPerson').checked ? 1 : 0,
                currency: document.getElementById('fieldCurrency').checked ? 1 : 0,
                against_of: document.getElementById('fieldAgainstOf').checked ? 1 : 0,
                layaways: document.getElementById('fieldLayaways').checked ? 1 : 0,
                due_date: document.getElementById('fieldDueDate').checked ? 1 : 0,
                fixing_type: 0,
                show_billing_type: document.getElementById('fieldShowFixingType').checked ? 1 : 0,
                show_metal_unfix: document.getElementById('fieldShowMetalUnfix').checked ? 1 : 0,
                show_payment_term: document.getElementById('fieldShowPaymentTerm').checked ? 1 : 0,
                show_unfix: document.getElementById('fieldShowUnfix').checked ? 1 : 0,
                show_shipping_method: document.getElementById('fieldShowShippingMethod').checked ? 1 : 0,
                show_barcode_no: document.getElementById('fieldShowBarcodeNo').checked ? 1 : 0,
                show_ounce_rate: document.getElementById('fieldShowOunceRate').checked ? 1 : 0,
                show_lead_source: document.getElementById('fieldShowLeadSource').checked ? 1 : 0,
                show_design_no: document.getElementById('fieldShowDesignNo').checked ? 1 : 0,
                show_product_code: document.getElementById('fieldShowProductCode').checked ? 1 : 0,
                show_dmd_or_nam_unfix: document.getElementById('fieldShowDmdOrNamUnfix').checked ? 1 : 0,
                show_update_tax_dropdown: document.getElementById('fieldShowUpdateTaxDropdown').checked ? 1 : 0
            },
            payment_buttons: collectPaymentButtons()
        };

        document.querySelectorAll('.metal-checkbox:checked').forEach(checkbox => {
            const metalId = checkbox.getAttribute('data-metal-id');
            const discountInput = document.querySelector('.metal-discount[data-metal-id="' + metalId + '"]');
            formData.metal_allocations.push({
                metal_id: metalId,
                discount: discountInput ? discountInput.value : 0
            });
        });

        document.querySelectorAll('.tax-checkbox:checked').forEach(checkbox => {
            formData.tax_allocations.push({ tax_id: checkbox.getAttribute('data-tax-id') });
        });

        var vtBrSave = document.getElementById('vtSettingsBranchId');
        var vtBrVal = vtBrSave ? vtBrSave.value : '0';
        $.ajax({
            url: 'ajax/voucher-type.php',
            type: 'POST',
            data: {
                action: formData.action,
                id: formData.id,
                branch_id: vtBrVal,
                name: formData.name,
                method_of_numbering: formData.method_of_numbering,
                type_of_voucher: formData.type_of_voucher,
                calculate_amount_by: formData.calculate_amount_by,
                calculate_wastage_by: formData.calculate_wastage_by,
                fixing_type: formData.fixing_type,
                calculate_loss_by: formData.calculate_loss_by,
                do_not_apply_on_stock: formData.do_not_apply_on_stock,
                sales_persons_mandatory: formData.sales_persons_mandatory,
                create_auto_journal_voucher: formData.create_auto_journal_voucher,
                metal_unfix: formData.metal_unfix,
                internal_unfix: formData.internal_unfix,
                do_not_allow_0_amount: formData.do_not_allow_0_amount,
                payment_mandatory: formData.payment_mandatory,
                calculate_markup_on_sale: formData.calculate_markup_on_sale,
                enable_item_fast_fields: formData.enable_item_fast_fields,
                metal_allocations: JSON.stringify(formData.metal_allocations),
                tax_allocations: JSON.stringify(formData.tax_allocations),
                field_visibility: JSON.stringify(formData.field_visibility),
                payment_buttons: JSON.stringify(formData.payment_buttons)
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Voucher type saved successfully.');
                    loadVoucherType(currentVoucherId);
                } else {
                    alert('Error: ' + (response.message || 'Failed to save'));
                }
            },
            error: function(xhr) {
                alert('Error saving voucher type. ' + (xhr.responseText || '').substring(0, 200));
            }
        });
    }

    function refreshVoucherList() { location.reload(); }

    $(document).ready(function() {
        var vtSel = document.getElementById('vtBranchSelect');
        if (vtSel) {
            vtSel.addEventListener('change', function() {
                var v = this.value || '0';
                window.location.href = 'voucher-type.php?vt_branch=' + encodeURIComponent(v);
            });
        }
        applyVoucherListPagination();
        var prevBtn = document.getElementById('voucherListPrev');
        var nextBtn = document.getElementById('voucherListNext');
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (voucherListPage > 1) {
                    voucherListPage--;
                    applyVoucherListPagination();
                }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                var rows = getVoucherRows();
                var q = (document.getElementById('searchVoucherType').value || '').toLowerCase().trim();
                var filtered = rows.filter(function(r) {
                    var t = r.cells[1] ? r.cells[1].textContent.toLowerCase() : '';
                    return !q || t.indexOf(q) !== -1;
                });
                var totalPages = Math.max(1, Math.ceil(filtered.length / voucherListPageSize) || 1);
                if (voucherListPage < totalPages) {
                    voucherListPage++;
                    applyVoucherListPagination();
                }
            });
        }
        var firstVisible = null;
        getVoucherRows().forEach(function(r) {
            if (firstVisible) return;
            if (r.style.display !== 'none') firstVisible = r;
        });
        if (firstVisible) {
            var firstId = firstVisible.getAttribute('data-id');
            if (firstId) loadVoucherType(parseInt(firstId, 10));
        }
    });
    </script>
</body>
</html>
