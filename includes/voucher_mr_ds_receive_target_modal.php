<?php

/** Modal: choose diamond/gemstone product + weight when receiving issued lines into stock. */
if (($auragold_voucher_ds_kind ?? '') !== 'material_receive') {
    return;
}

?>
<div class="modal fade" id="mrDsReceiveTargetModal" tabindex="-1" role="dialog" aria-labelledby="mrDsReceiveTargetModalTitle">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#11294b;color:#fff;border:none;">
                <h5 class="modal-title" id="mrDsReceiveTargetModalTitle">Receive into stock</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <input type="hidden" id="mrDsReceiveTargetGem" value="">
                <p class="small text-muted mb-2" id="mrDsReceiveTargetHint">
                    Choose which <strong>product stock</strong> should receive this weight. The issued line on Material Issue is settled; stock is added to the product you select below.
                </p>
                <div class="mr-me-receive-issued-box mb-3" id="mrDsReceiveTargetIssuedBox">
                    <div class="small text-muted mb-1">Issued on Material Issue</div>
                    <div id="mrDsReceiveTargetIssuedText" class="font-weight-bold" style="color:#11294b;font-size:0.85rem;"></div>
                    <div class="small mt-1">Balance to receive: <strong id="mrDsReceiveTargetBalanceText">0.000</strong></div>
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group mb-2" style="position:relative;">
                            <label class="small font-weight-bold mb-1" id="mrDsReceiveTargetProductLabel">Product <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="mrDsReceiveTargetProductInput" placeholder="Search product name..." autocomplete="off">
                            <input type="hidden" id="mrDsReceiveTargetProductId" value="">
                            <div id="mrDsReceiveTargetProductList" class="mr-me-receive-product-list" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold mb-1">Receive Wt <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm text-right" id="mrDsReceiveTargetWt" step="0.001" min="0" placeholder="0.000">
                        </div>
                    </div>
                </div>
                <p class="small text-muted mb-0" id="mrDsReceiveTargetQueueHint" style="display:none;"></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="mrDsReceiveTargetConfirmBtn">Add to receive</button>
            </div>
        </div>
    </div>
</div>
<style>
#mrDsReceiveTargetModal.modal {
    z-index: 10850 !important;
}
body.modal-open:has(#mrDsReceiveTargetModal.show) .modal-backdrop {
    z-index: 10840 !important;
}
#mrDsReceiveTargetModal .modal-dialog,
#mrDsReceiveTargetModal .modal-content,
#mrDsReceiveTargetModal input,
#mrDsReceiveTargetModal select,
#mrDsReceiveTargetModal button {
    pointer-events: auto !important;
}
</style>
