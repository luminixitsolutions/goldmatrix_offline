<?php

/** Modal: choose metal + product + weight when receiving issued metal into stock. */
if (($auragold_voucher_ds_kind ?? '') !== 'material_receive') {
    return;
}

$mr_me_modal_metals = isset($metals) && is_array($metals) ? $metals : [];

?>
<div class="modal fade" id="mrMeReceiveTargetModal" tabindex="-1" role="dialog" aria-labelledby="mrMeReceiveTargetModalTitle">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#11294b;color:#fff;border:none;">
                <h5 class="modal-title" id="mrMeReceiveTargetModalTitle">Receive metal into stock</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <p class="small text-muted mb-2">
                    Choose which <strong>metal product stock</strong> should receive this weight. The issued line on Material Issue is settled; stock is added to the product you select below (can differ from issued product).
                </p>
                <div class="mr-me-receive-issued-box mb-3" id="mrMeReceiveTargetIssuedBox">
                    <div class="small text-muted mb-1">Issued on Material Issue</div>
                    <div id="mrMeReceiveTargetIssuedText" class="font-weight-bold" style="color:#11294b;font-size:0.85rem;"></div>
                    <div class="small mt-1">Balance to receive: <strong id="mrMeReceiveTargetBalanceText">0.000</strong></div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold mb-1">Metal <span class="text-danger">*</span></label>
                            <select class="form-control form-control-sm" id="mrMeReceiveTargetMetal">
                                <option value="">Select metal</option>
                                <?php foreach ($mr_me_modal_metals as $metal): ?>
                                <option value="<?php echo (int) ($metal['id'] ?? 0); ?>">
                                    <?php echo htmlspecialchars((string) ($metal['display_name'] ?? $metal['system_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group mb-2" style="position:relative;">
                            <label class="small font-weight-bold mb-1">Product (metal wise) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="mrMeReceiveTargetProductInput" placeholder="Search product name..." autocomplete="off">
                            <input type="hidden" id="mrMeReceiveTargetProductId" value="">
                            <div id="mrMeReceiveTargetProductList" class="mr-me-receive-product-list" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold mb-1">Receive Wt <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm text-right" id="mrMeReceiveTargetWt" step="0.001" min="0" placeholder="0.000">
                        </div>
                    </div>
                </div>
                <p class="small text-muted mb-0" id="mrMeReceiveTargetQueueHint" style="display:none;"></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-warning" id="mrMeReceiveTargetConfirmBtn" style="color:#11294b;font-weight:600;">Add to receive</button>
            </div>
        </div>
    </div>
</div>
<style>
/* Above layout overlays and payment/product modals — nested modals trap clicks under backdrop */
#mrMeReceiveTargetModal.modal {
    z-index: 10850 !important;
}
body.modal-open:has(#mrMeReceiveTargetModal.show) .modal-backdrop {
    z-index: 10840 !important;
}
#mrMeReceiveTargetModal .modal-dialog,
#mrMeReceiveTargetModal .modal-content,
#mrMeReceiveTargetModal input,
#mrMeReceiveTargetModal select,
#mrMeReceiveTargetModal button {
    pointer-events: auto !important;
}
</style>
