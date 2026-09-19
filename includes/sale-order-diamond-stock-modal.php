<?php
/** Modal: allocate Diamond & Stones serial stock to current sale order (sale-order.php). */
?>
<style>
#saleOrderDiamondStockModal .so-dim-table-wrap {
    overflow: auto;
    max-height: 52vh;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
#saleOrderDiamondStockModal table.so-dim-table {
    font-size: 0.72rem;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    width: 100%;
}
#saleOrderDiamondStockModal table.so-dim-table thead th {
    background: #11294b !important;
    color: #fff !important;
    border: 1px solid rgba(255, 255, 255, 0.35) !important;
    font-weight: 600;
    font-size: 0.72rem;
    padding: 0.35rem 0.45rem;
    white-space: nowrap;
    vertical-align: middle;
}
#saleOrderDiamondStockModal table.so-dim-table thead th.so-dim-dragging {
    opacity: 0.85;
    outline: 2px dashed #c4b5fd;
}
#saleOrderDiamondStockModal .so-dim-th .so-dim-drag-hint {
    display: inline-block;
    margin-right: 4px;
    color: rgba(255, 255, 255, 0.65);
    cursor: grab;
    font-size: 10px;
    user-select: none;
}
#saleOrderDiamondStockModal .so-dim-th-label {
    user-select: none;
}
#saleOrderDiamondStockModal .so-dim-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    cursor: col-resize;
    user-select: none;
    z-index: 4;
}
#saleOrderDiamondStockModal table.so-dim-table tbody td {
    border-color: #e2e8f0;
    padding: 0.25rem 0.35rem;
    vertical-align: middle;
}
#saleOrderDiamondStockModal table.so-dim-table tbody tr:nth-child(even) {
    background: #f8fafc;
}
#saleOrderDiamondStockModal #saleOrderDiamondStockSearch::placeholder {
    color: #94a3b8;
}
</style>
<div class="modal fade" id="saleOrderDiamondStockModal" tabindex="-1" role="dialog" aria-labelledby="saleOrderDiamondStockModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 96vw;">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title" id="saleOrderDiamondStockModalTitle" style="font-weight:700;">Add Diamond</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding-top: 0.75rem;">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 0.75rem;">
                    <div class="d-flex align-items-center flex-grow-1" style="min-width: 200px;">
                        <label class="mb-0 mr-2 small text-muted" for="saleOrderDiamondStockSearch">Search</label>
                        <input type="text" class="form-control form-control-sm" id="saleOrderDiamondStockSearch" placeholder="Search Barcode / product…" autocomplete="off">
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Diamond source">
                        <button type="button" class="btn so-diamond-tab active" id="saleOrderDiamondTabExisting" style="background:#5b21b6;color:#fff;border-color:#5b21b6;">Existing</button>
                        <button type="button" class="btn btn-outline-secondary so-diamond-tab" id="saleOrderDiamondTabLoose">Add Loose</button>
                    </div>
                </div>
                <div id="saleOrderDiamondPanelExisting">
                    <div class="so-dim-table-wrap">
                        <table class="table table-sm mb-0 so-dim-table" id="saleOrderDiamondStockTable">
                            <thead id="saleOrderDiamondStockThead"></thead>
                            <tbody id="saleOrderDiamondStockTableBody">
                                <tr><td colspan="20" class="text-center text-muted py-3">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="saleOrderDiamondPanelLoose" style="display:none;" class="border rounded p-4 text-center text-muted">
                    Add loose diamonds through <strong>Stock Journal</strong> or purchase; they appear under Existing once barcoded.
                </div>
                <p class="small text-muted mt-2 mb-0" id="saleOrderDiamondStockHint"></p>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.5rem;">
                <span class="small text-muted" id="saleOrderDiamondStockOrderLabel"></span>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mr-2" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm text-white" id="saleOrderDiamondAllocateBtn" style="background:#5b21b6;border:none;">Allocate to order</button>
                </div>
            </div>
        </div>
    </div>
</div>
