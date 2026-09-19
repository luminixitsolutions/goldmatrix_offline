<?php
/**
 * Advance Filter modal for Payment / Receipt voucher list.
 * Expects: optional $voucher_types (array of ['name'=>...])
 */
if (!isset($voucher_types) || !is_array($voucher_types)) {
    $voucher_types = [];
}
?>
<div id="voucherListAdvanceFilterModal" class="filter-modal" style="display: none;" aria-hidden="true">
    <div class="filter-modal-content" style="max-width: 520px;">
        <div class="filter-modal-header" style="background: #11294b; color: #fff; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0;">
            <h5 style="margin: 0; font-size: 0.95rem; font-weight: 600;">Advance Filter</h5>
            <button type="button" id="voucherListAdvanceFilterClose" style="background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        <div class="filter-modal-body" style="padding: 16px;">
            <div class="form-group mb-2">
                <label style="font-size: 0.75rem; font-weight: 600; color: #334155;">Date Range</label>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="date" class="form-control form-control-sm" id="vlfDateFrom" style="font-size: 0.8rem;">
                    <span style="font-size: 0.75rem; color: #64748b;">to</span>
                    <input type="date" class="form-control form-control-sm" id="vlfDateTo" style="font-size: 0.8rem;">
                </div>
            </div>
            <div class="form-group mb-2">
                <label style="font-size: 0.75rem; font-weight: 600; color: #334155;">Invoice No.</label>
                <input type="text" class="form-control form-control-sm" id="vlfVoucherNo" placeholder="Invoice / Voucher No." style="font-size: 0.8rem;">
            </div>
            <div class="form-group mb-2">
                <label style="font-size: 0.75rem; font-weight: 600; color: #334155;">Branch</label>
                <select class="form-control form-control-sm" id="vlfBranchType" style="font-size: 0.8rem;">
                    <option value="">All Branches</option>
                    <option value="main">Main Branch</option>
                    <option value="sub">Sub Branch</option>
                </select>
            </div>
            <div class="form-group mb-2">
                <label style="font-size: 0.75rem; font-weight: 600; color: #334155;">Ledger Name</label>
                <input type="text" class="form-control form-control-sm" id="vlfLedgerName" placeholder="Ledger / Customer name" style="font-size: 0.8rem;">
            </div>
            <div class="form-group mb-2">
                <label style="font-size: 0.75rem; font-weight: 600; color: #334155;">Against Voucher Type</label>
                <select class="form-control form-control-sm" id="vlfAgainstVoucherType" style="font-size: 0.8rem;">
                    <option value="">Select Type Of Against Voucher</option>
                    <?php foreach ($voucher_types as $vt): ?>
                    <option value="<?php echo htmlspecialchars($vt['name'] ?? ''); ?>"><?php echo htmlspecialchars($vt['name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                    <option value="Sale Invoice">Sale Invoice</option>
                    <option value="Purchase Invoice">Purchase Invoice</option>
                    <option value="Receipt">Receipt</option>
                    <option value="Payment">Payment</option>
                </select>
            </div>
            <div class="form-group mb-2">
                <label style="font-size: 0.75rem; font-weight: 600; color: #334155;">Against Invoice No</label>
                <input type="text" class="form-control form-control-sm" id="vlfAgainstInvoiceNo" placeholder="Against invoice no." style="font-size: 0.8rem;">
            </div>
            <div class="form-group mb-3">
                <label style="font-size: 0.75rem; font-weight: 600; color: #334155;">Account No.</label>
                <input type="text" class="form-control form-control-sm" id="vlfAccountNo" placeholder="Ref / Account No." style="font-size: 0.8rem;">
            </div>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button type="button" class="btn btn-sm btn-outline-primary" id="vlfApplyFilterBtn" style="min-width: 110px; border-color: #7c3aed; color: #7c3aed;">Apply Filter</button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="vlfClearFilterBtn" style="min-width: 110px; border-color: #ec4899; color: #ec4899;">Clear Filter</button>
            </div>
        </div>
    </div>
</div>

<style>
.voucher-list-export-wrap { position: relative; display: inline-block; }
.voucher-list-export-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 4px;
    min-width: 160px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    z-index: 1000;
    padding: 4px 0;
}
.voucher-list-export-menu.show { display: block; }
.voucher-list-export-menu button {
    display: block;
    width: 100%;
    text-align: left;
    border: none;
    background: none;
    padding: 8px 12px;
    font-size: 0.75rem;
    color: #334155;
    cursor: pointer;
}
.voucher-list-export-menu button:hover { background: #f1f5f9; }
.payment-list-table-wrap {
    max-height: 320px;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
}
.payment-list-table-wrap .table { margin-bottom: 0; }
.payment-list-table-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 2;
}
.payment-list-pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e2e8f0;
    font-size: 0.72rem;
    color: #64748b;
}
.payment-list-pagination .btn:disabled,
.payment-list-pagination .btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.payment-list-pagination .btn:not(:disabled):not(.disabled) {
    cursor: pointer;
    pointer-events: auto;
}
.payment-list-pagination .payment-list-page-size {
    height: 26px;
    font-size: 0.72rem;
    padding: 2px 6px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
}
</style>
