<?php
/** Modal: allocate gemstone / stone serial stock to current sale order (sale-order.php). */
?>
<style>
#saleOrderStoneStockModal .so-stone-table-wrap {
    overflow: auto;
    max-height: 52vh;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
#saleOrderStoneStockModal table.so-stone-table {
    font-size: 0.72rem;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    width: 100%;
}
#saleOrderStoneStockModal table.so-stone-table thead th {
    background: #11294b !important;
    color: #fff !important;
    border: 1px solid rgba(255, 255, 255, 0.35) !important;
    font-weight: 600;
    font-size: 0.72rem;
    padding: 0.35rem 0.45rem;
    white-space: nowrap;
    vertical-align: middle;
}
#saleOrderStoneStockModal table.so-stone-table thead th.so-stone-dragging {
    opacity: 0.85;
    outline: 2px dashed #c4b5fd;
}
#saleOrderStoneStockModal .so-stone-th .so-stone-drag-hint {
    display: inline-block;
    margin-right: 4px;
    color: rgba(255, 255, 255, 0.65);
    cursor: grab;
    font-size: 10px;
    user-select: none;
}
#saleOrderStoneStockModal .so-stone-th-label {
    user-select: none;
}
#saleOrderStoneStockModal .so-stone-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    cursor: col-resize;
    user-select: none;
    z-index: 4;
}
#saleOrderStoneStockModal table.so-stone-table tbody td {
    border-color: #e2e8f0;
    padding: 0.25rem 0.35rem;
    vertical-align: middle;
}
#saleOrderStoneStockModal table.so-stone-table tbody tr:nth-child(even) {
    background: #f8fafc;
}
#saleOrderStoneStockModal #saleOrderStoneStockSearch::placeholder {
    color: #94a3b8;
}
</style>
<div class="modal fade" id="saleOrderStoneStockModal" tabindex="-1" role="dialog" aria-labelledby="saleOrderStoneStockModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 96vw;">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title" id="saleOrderStoneStockModalTitle" style="font-weight:700;">Add Stone</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding-top: 0.75rem;">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 0.75rem;">
                    <div class="d-flex align-items-center flex-grow-1" style="min-width: 200px;">
                        <label class="mb-0 mr-2 small text-muted" for="saleOrderStoneStockSearch">Search</label>
                        <input type="text" class="form-control form-control-sm" id="saleOrderStoneStockSearch" placeholder="Search Barcode / product…" autocomplete="off">
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Stone source">
                        <button type="button" class="btn so-stone-tab active" id="saleOrderStoneTabExisting" style="background:#5b21b6;color:#fff;border-color:#5b21b6;">Existing</button>
                        <button type="button" class="btn btn-outline-secondary so-stone-tab" id="saleOrderStoneTabLoose">Add Loose</button>
                    </div>
                </div>
                <div id="saleOrderStonePanelExisting">
                    <div class="so-stone-table-wrap">
                        <table class="table table-sm mb-0 so-stone-table" id="saleOrderStoneStockTable">
                            <thead id="saleOrderStoneStockThead"></thead>
                            <tbody id="saleOrderStoneStockTableBody">
                                <tr><td colspan="20" class="text-center text-muted py-3">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="saleOrderStonePanelLoose" style="display:none;" class="border rounded p-4 text-center text-muted">
                    Add loose stones through <strong>Stock Journal</strong> or purchase; they appear under Existing once barcoded.
                </div>
                <p class="small text-muted mt-2 mb-0" id="saleOrderStoneStockHint"></p>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.5rem;">
                <span class="small text-muted" id="saleOrderStoneStockOrderLabel"></span>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mr-2" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm text-white" id="saleOrderStoneAllocateBtn" style="background:#5b21b6;border:none;">Allocate to order</button>
                </div>
            </div>
        </div>
    </div>
</div>
