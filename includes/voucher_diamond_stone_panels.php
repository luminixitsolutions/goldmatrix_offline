<?php

/** Requires $auragold_voucher_ds_kind (non-empty) — diamond/stone allocation panels under payments. */
if (empty($auragold_voucher_ds_kind) || !is_string($auragold_voucher_ds_kind)) {
    return;
}

$auragold_jwo_ds_usage_wrap = in_array(($auragold_voucher_ds_kind ?? ''), ['jobwork_order', 'jobwork_invoice', 'material_issue', 'material_receive'], true);
$auragold_ds_usage_subtitle = 'Table shows which diamonds and stones are allocated on this document (stock deducts on Save when pending).';
if (($auragold_voucher_ds_kind ?? '') === 'jobwork_invoice') {
    $auragold_ds_usage_subtitle = 'Diamonds/stones issued for this job are returned to stock when you save the jobwork invoice (job complete).';
} elseif (($auragold_voucher_ds_kind ?? '') === 'jobwork_order') {
    $auragold_ds_usage_subtitle = 'Table shows which diamonds and stones are used on this job work order (allocated stock).';
} elseif (($auragold_voucher_ds_kind ?? '') === 'material_issue') {
    $auragold_ds_usage_subtitle = 'Table shows diamonds and stones issued on this material issue (allocated stock).';
} elseif (($auragold_voucher_ds_kind ?? '') === 'material_receive') {
    $auragold_ds_usage_subtitle = '';
}
$auragold_mr_issued_receive_ui = (($auragold_voucher_ds_kind ?? '') === 'material_receive');
$diamond_stone_card_hidden = $auragold_jwo_ds_usage_wrap ? '' : ' hidden';
$mr_issued_card_class = $auragold_mr_issued_receive_ui ? ' mr-issued-receive-card mr-issued-unified-card' : '';

?>
<?php if ($auragold_jwo_ds_usage_wrap): ?>
                                    <div class="card mb-2 border auragold-jwo-diamond-stone-usage-card<?php echo $mr_issued_card_class; ?>">
                                        <div class="card-header py-2"<?php echo $auragold_mr_issued_receive_ui ? '' : ' style="background: linear-gradient(90deg, #f8fafc, #eef2ff); border-bottom: 1px solid #e2e8f0;"'; ?>>
                                            <strong<?php echo $auragold_mr_issued_receive_ui ? '' : ' style="color: #11294b;"'; ?>><?php echo $auragold_mr_issued_receive_ui ? 'Receive from Material Issue' : 'Diamond &amp; gemstone usage'; ?></strong>
                                            <?php if (!$auragold_mr_issued_receive_ui && ($auragold_ds_usage_subtitle ?? '') !== ''): ?>
                                            <div class="small text-muted mb-0"><?php echo htmlspecialchars($auragold_ds_usage_subtitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body<?php echo $auragold_mr_issued_receive_ui ? ' py-2 px-2' : ' pt-3 pb-2'; ?>">
<?php if ($auragold_mr_issued_receive_ui): ?>
<?php require __DIR__ . '/voucher_material_receive_issued_workflow.php'; ?>
                                        <ul class="nav mr-issued-tabs" role="tablist" id="mrIssuedReceiveTabs">
                                            <li class="nav-item">
                                                <button type="button" class="nav-link mr-issued-tab-btn active" id="mrIssuedTabBtnDiamond" data-mr-tab="Diamond" role="tab" aria-selected="true" aria-controls="mrIssuedTabDiamond">
                                                    Diamonds <span class="mr-issued-tab-count" id="mrIssuedTabCountDiamond">0</span>
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button type="button" class="nav-link mr-issued-tab-btn" id="mrIssuedTabBtnStone" data-mr-tab="Stone" role="tab" aria-selected="false" aria-controls="mrIssuedTabStone">
                                                    Gemstones <span class="mr-issued-tab-count" id="mrIssuedTabCountStone">0</span>
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button type="button" class="nav-link mr-issued-tab-btn" id="mrIssuedTabBtnMetal" data-mr-tab="Metal" role="tab" aria-selected="false" aria-controls="mrIssuedTabMetal">
                                                    Metal <span class="mr-issued-tab-count" id="mrIssuedTabCountMetal">0</span>
                                                </button>
                                            </li>
                                        </ul>
                                        <div class="mr-issued-tab-panels">
<?php endif; ?>
<?php endif; ?>
                                    <div id="saleOrderDiamondLinesCard" class="<?php
                                        echo $auragold_mr_issued_receive_ui
                                            ? 'mr-issued-tab-pane active sale-order-diamond-lines-card'
                                            : ($auragold_jwo_ds_usage_wrap ? 'mb-3' : 'mt-3') . ' sale-order-diamond-lines-card';
                                    ?>"<?php echo $diamond_stone_card_hidden; ?><?php echo $auragold_mr_issued_receive_ui ? ' role="tabpanel" aria-labelledby="mrIssuedTabBtnDiamond"' : ''; ?>>
                                        <?php if (!$auragold_mr_issued_receive_ui): ?>
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <label class="mb-0 font-weight-bold" style="font-size: 0.85rem; color: #11294b;">Diamonds used</label>
                                        </div>
                                        <?php else: ?>
                                        <div class="mr-issued-tab-toolbar">
                                            <div class="mr-issued-toolbar">
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="mrReceiveDiamondSelectAll">Select all</button>
                                                <button type="button" class="btn btn-primary btn-sm" id="mrReceiveDiamondQueueBtn">Add selected to receive</button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="mr-issued-table-wrap">
                                            <table class="table table-sm table-bordered mb-0 sale-order-diamond-lines-table<?php echo $auragold_mr_issued_receive_ui ? ' mr-issued-table mr-issued-table--diamond' : ''; ?>"<?php echo $auragold_mr_issued_receive_ui ? '' : ' style="font-size: 0.8rem;"'; ?>>
                                                <thead<?php echo $auragold_mr_issued_receive_ui ? '' : ' style="background: #11294b; color: #fff;"'; ?>>
                                                    <?php if (!empty($auragold_mr_issued_receive_ui)): ?>
                                                    <tr class="mr-issued-group-row">
                                                        <th rowspan="2" class="text-center" style="width:36px;"><input type="checkbox" id="mrReceiveDiamondHdrChk" title="Select all"></th>
                                                        <th colspan="3">Item</th>
                                                        <th colspan="3" class="mr-col-group-issue text-center">From issue</th>
                                                        <th colspan="1" class="mr-col-group-receive text-center">Receive</th>
                                                        <th rowspan="2" style="width:100px;">Status</th>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr<?php echo $auragold_mr_issued_receive_ui ? ' class="mr-issued-col-row"' : ''; ?>>
                                                        <?php if (empty($auragold_mr_issued_receive_ui)): ?>
                                                        <th>Barcode</th>
                                                        <?php else: ?>
                                                        <th>Barcode</th>
                                                        <?php endif; ?>
                                                        <th>Product</th>
                                                        <th>Category</th>
                                                        <?php if (!empty($auragold_mr_issued_receive_ui)): ?>
                                                        <th class="text-right">Issued</th>
                                                        <th class="text-right">Rcvd</th>
                                                        <th class="text-right">Bal</th>
                                                        <th class="text-right" style="min-width:72px;">Wt</th>
                                                        <?php else: ?>
                                                        <th class="text-right">Qty</th>
                                                        <th class="text-right">Weight</th>
                                                        <th style="width: 120px;">Status</th>
                                                        <th class="text-center" style="width: 48px;" title="Remove line">Del</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody id="saleOrderDiamondLinesTbody"></tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div id="saleOrderStoneLinesCard" class="<?php
                                        echo $auragold_mr_issued_receive_ui
                                            ? 'mr-issued-tab-pane sale-order-stone-lines-card'
                                            : 'mt-3 sale-order-stone-lines-card';
                                    ?>"<?php echo $diamond_stone_card_hidden; ?><?php echo $auragold_mr_issued_receive_ui ? ' role="tabpanel" aria-labelledby="mrIssuedTabBtnStone"' : ''; ?>>
                                        <?php if (!$auragold_mr_issued_receive_ui): ?>
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                            <label class="mb-0 font-weight-bold" style="font-size: 0.85rem; color: #0f766e;">Gemstones / stones used</label>
                                        </div>
                                        <?php else: ?>
                                        <div class="mr-issued-tab-toolbar">
                                            <div class="mr-issued-toolbar">
                                                <button type="button" class="btn btn-outline-success btn-sm" id="mrReceiveStoneSelectAll">Select all</button>
                                                <button type="button" class="btn btn-success btn-sm" id="mrReceiveStoneQueueBtn">Add selected to receive</button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="mr-issued-table-wrap">
                                            <table class="table table-sm table-bordered mb-0 sale-order-stone-lines-table<?php echo $auragold_mr_issued_receive_ui ? ' mr-issued-table mr-issued-table--stone' : ''; ?>"<?php echo $auragold_mr_issued_receive_ui ? '' : ' style="font-size: 0.8rem;"'; ?>>
                                                <thead<?php echo $auragold_mr_issued_receive_ui ? '' : ' style="background: #0f766e; color: #fff;"'; ?>>
                                                    <?php if (!empty($auragold_mr_issued_receive_ui)): ?>
                                                    <tr class="mr-issued-group-row">
                                                        <th rowspan="2" class="text-center" style="width:36px;"><input type="checkbox" id="mrReceiveStoneHdrChk" title="Select all"></th>
                                                        <th colspan="3">Item</th>
                                                        <th colspan="3" class="mr-col-group-issue text-center">From issue</th>
                                                        <th colspan="1" class="mr-col-group-receive text-center">Receive</th>
                                                        <th rowspan="2" style="width:100px;">Status</th>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr<?php echo $auragold_mr_issued_receive_ui ? ' class="mr-issued-col-row"' : ''; ?>>
                                                        <th>Barcode</th>
                                                        <th>Product</th>
                                                        <th>Category</th>
                                                        <?php if (!empty($auragold_mr_issued_receive_ui)): ?>
                                                        <th class="text-right">Issued</th>
                                                        <th class="text-right">Rcvd</th>
                                                        <th class="text-right">Bal</th>
                                                        <th class="text-right" style="min-width:72px;">Wt</th>
                                                        <?php else: ?>
                                                        <th class="text-right">Qty</th>
                                                        <th class="text-right">Weight</th>
                                                        <th style="width: 120px;">Status</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody id="saleOrderStoneLinesTbody"></tbody>
                                            </table>
                                        </div>
                                    </div>
<?php if ($auragold_mr_issued_receive_ui): ?>
<?php require __DIR__ . '/voucher_metal_exchange_receive_panel.php'; ?>
                                        </div><!-- .mr-issued-tab-panels -->
<?php endif; ?>
<?php if ($auragold_jwo_ds_usage_wrap): ?>
                                        </div>
                                    </div>
<?php endif; ?>
<?php if (!$auragold_mr_issued_receive_ui): ?>
<?php require __DIR__ . '/voucher_metal_exchange_receive_panel.php'; ?>
<?php endif; ?>
