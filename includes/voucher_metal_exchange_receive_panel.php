<?php

/** Metal exchange issued on Material Issue — receive back on Material Receive. */
if (($auragold_voucher_ds_kind ?? '') !== 'material_receive') {
    return;
}

$auragold_mr_metal_in_tabs = !empty($auragold_mr_issued_receive_ui);

?>
<?php if (!$auragold_mr_metal_in_tabs): ?>
<div class="card mb-3 border auragold-mr-metal-exchange-card mr-issued-receive-card" style="border-color: #c9a227 !important;">
    <div class="card-header py-2">
        <strong>Metal exchange — receive gold / silver</strong>
    </div>
    <div class="card-body pt-2 pb-2">
<?php else: ?>
<div id="mrIssuedTabMetal" class="mr-issued-tab-pane" role="tabpanel" aria-labelledby="mrIssuedTabBtnMetal">
<?php endif; ?>
        <div class="mr-issued-tab-toolbar">
            <div class="mr-issued-toolbar">
                <button type="button" class="btn btn-outline-warning btn-sm" id="mrReceiveMeSelectAll">Select all</button>
                <button type="button" class="btn btn-warning btn-sm" id="mrReceiveMeQueueBtn" style="color:#11294b;">Add selected to receive</button>
            </div>
        </div>
        <div class="mr-issued-table-wrap">
            <table class="table table-sm table-bordered mb-0 mr-issued-table mr-issued-table--metal">
                <thead>
                    <tr class="mr-issued-group-row">
                        <th rowspan="2" class="text-center" style="width:36px;"><input type="checkbox" id="mrReceiveMeHdrChk" title="Select all"></th>
                        <th colspan="4">Item details</th>
                        <th colspan="3" class="mr-col-group-issue text-center">From issue</th>
                        <th colspan="1" class="mr-col-group-receive text-center">Receive</th>
                        <th rowspan="2" style="width:100px;">Status</th>
                    </tr>
                    <tr class="mr-issued-col-row">
                        <th>Source</th>
                        <th>Metal</th>
                        <th>Product</th>
                        <th>Barcode</th>
                        <th class="text-right">Issued</th>
                        <th class="text-right">Rcvd</th>
                        <th class="text-right">Bal</th>
                        <th class="text-right" style="min-width:72px;">Wt</th>
                    </tr>
                </thead>
                <tbody id="mrIssuedMetalExchangeTbody"></tbody>
            </table>
        </div>
        <div class="mr-issued-empty mr-issued-empty--compact" id="mrIssuedMetalExchangeEmpty" style="display:none;" role="status">
            <p class="mr-issued-empty-title mb-0">No metal exchange lines — issue on Material Issue or use Metal Exchange icon above.</p>
        </div>
<?php if (!$auragold_mr_metal_in_tabs): ?>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
