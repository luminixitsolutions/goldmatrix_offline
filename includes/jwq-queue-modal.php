<?php
/**
 * Jobwork Queue modal (shared overlay) — used by Add/Update, Transfer, and Add Weight on manufacturing-process.php.
 * Requires: $jwq_order_line_columns (array of key/label).
 */
if (!isset($jwq_order_line_columns) || !is_array($jwq_order_line_columns)) {
    $jwq_order_line_columns = [];
}
?>
<style>
.jwq-weight-adjust-strip {
    margin: 12px 0 0;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.jwq-weight-adjust-title {
    font-weight: 700;
    font-size: 14px;
    color: #0f172a;
    margin-bottom: 10px;
}
.jwq-weight-adjust-inner {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px 14px;
}
.jwq-weight-adjust-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
}
.jwq-weight-adjust-field-grow {
    flex: 1 1 200px;
    min-width: 160px;
}
.jwq-weight-adjust-input {
    width: 100%;
    max-width: 200px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 14px;
    background: #fff;
}
.jwq-weight-adjust-field-grow .jwq-weight-adjust-input {
    max-width: none;
}
.jwq-weight-adjust-save {
    border: 1px solid #11294b;
    background: #11294b;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 18px;
    border-radius: 6px;
    cursor: pointer;
    align-self: flex-end;
}
.jwq-weight-adjust-save:hover {
    opacity: 0.94;
}
.jwq-weight-adjust-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
<div class="jwq-modal-overlay" id="jwqModalOverlay" aria-hidden="true">
    <div class="jwq-modal" role="dialog" aria-modal="true" aria-labelledby="jwqModalTitle">
        <div class="jwq-modal-head">
            <div class="jwq-modal-title-wrap" id="jwqModalTitle">Jobwork Queue No. : <strong id="jwqModalQueueNo">JWQ-</strong></div>
            <div class="jwq-modal-head-actions">
                <button type="button" class="jwq-btn-text" id="jwqBtnCatalogue">Create Catalogue</button>
                <button type="button" class="jwq-btn-save" id="jwqBtnSave" title="Save"><i class="feather icon-check"></i> Save</button>
                <button type="button" class="jwq-modal-close" id="jwqModalClose" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="jwq-modal-body">
            <input type="hidden" id="jwqCurrentJwoId" value="">
            <input type="hidden" id="jwqJobworkQueueNo" value="">
            <div class="jwq-transfer-row">
                <div class="jwq-from-block">
                    <div class="jwq-field">
                        <label for="jwqFromDept">From Dept.*</label>
                        <select id="jwqFromDept"></select>
                    </div>
                    <div class="jwq-field">
                        <label for="jwqFromUser">From User</label>
                        <div class="jwq-user-with-icons">
                            <select id="jwqFromUser"></select>
                            <button type="button" class="jwq-icon-btn jwq-inward-folder-btn" data-which="from" title="Inward stock details" aria-label="Inward stock details"><i class="feather icon-folder" style="font-size:15px;"></i></button>
                        </div>
                    </div>
                </div>
                <div class="jwq-arrows" aria-hidden="true">
                    <i class="feather icon-arrow-right"></i>
                    <i class="feather icon-arrow-left"></i>
                </div>
                <div class="jwq-to-block">
                    <div class="jwq-field">
                        <label for="jwqToDept">To Dept.*</label>
                        <select id="jwqToDept" required></select>
                    </div>
                    <div class="jwq-field">
                        <label for="jwqToUser">To User</label>
                        <div class="jwq-user-with-icons">
                            <select id="jwqToUser"></select>
                            <button type="button" class="jwq-icon-btn jwq-inward-folder-btn" data-which="to" title="Inward stock details" aria-label="Inward stock details"><i class="feather icon-folder" style="font-size:15px;"></i></button>
                        </div>
                    </div>
                </div>
                <div class="jwq-datetime-block">
                    <div class="jwq-field">
                        <label for="jwqDate">Date</label>
                        <input type="date" id="jwqDate">
                    </div>
                    <div class="jwq-field">
                        <label for="jwqTime">Time</label>
                        <input type="time" id="jwqTime" step="1">
                    </div>
                    <div class="jwq-time-spent">
                        <i class="feather icon-clock"></i>
                        <span>Total Time Spent</span>
                        <strong id="jwqTotalTimeDisplay">00:00:00</strong>
                    </div>
                </div>
            </div>

            <div class="jwq-tag-row">
                <input type="text" id="jwqTagNoInput" placeholder="Tag No" autocomplete="off">
                <button type="button" class="jwq-pill-btn" id="jwqBtnBom">BOM</button>
                <button type="button" class="jwq-pill-btn" id="jwqBtnOrder">Order</button>
            </div>

            <div class="jwq-weight-adjust-strip" id="jwqWeightAdjustStrip" style="display:none;" aria-hidden="true">
                <div class="jwq-weight-adjust-title" id="jwqWeightAdjustTitle">Add Weight</div>
                <input type="hidden" id="jwqWeightAdjustMode" value="add">
                <div class="jwq-weight-adjust-inner">
                    <div class="jwq-weight-adjust-field">
                        <label for="jwqWeightAdjustGrams">Weight (g) <span class="text-danger">*</span></label>
                        <input type="number" id="jwqWeightAdjustGrams" class="jwq-weight-adjust-input" min="0.001" max="999999" step="0.001" placeholder="0.000">
                    </div>
                    <div class="jwq-weight-adjust-field jwq-weight-adjust-field-grow">
                        <label for="jwqWeightAdjustRemark">Remark</label>
                        <input type="text" id="jwqWeightAdjustRemark" class="jwq-weight-adjust-input" placeholder="Optional note" autocomplete="off">
                    </div>
                    <button type="button" class="jwq-weight-adjust-save" id="jwqWeightAdjustSaveBtn">Save</button>
                </div>
            </div>

            <div class="jwq-lines-toolbar">
                <button type="button" class="head-setting-btn jwq-settings-toggle" title="Columns">
                    <i class="feather icon-settings mini-gear"></i>
                </button>
                <div class="columns-panel jwq-columns-popover" id="jwqColumnsPanel">
                    <div class="columns-panel-header">
                        <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                        <button type="button" class="columns-panel-close" data-close-panel="jwqColumnsPanel">&times;</button>
                    </div>
                    <div class="columns-search">
                        <input type="text" id="jwqColumnsSearch" placeholder="Search" autocomplete="off">
                    </div>
                    <div class="columns-list jwq-columns-list--picker" id="jwqColumnsList">
                        <?php foreach ($jwq_order_line_columns as $col):
                            $lk = strtolower($col['label']);
                        ?>
                        <label class="jwq-column-picker-label" data-label="<?php echo htmlspecialchars($lk); ?>">
                            <input type="checkbox" class="jwq-line-column-checkbox" data-col="<?php echo htmlspecialchars($col['key']); ?>" checked>
                            <span><?php echo htmlspecialchars($col['label']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="jwq-table-wrap">
                <div class="jwq-order-lines-total-bar" id="jwqOrderLinesTotalBar" style="display:none;" aria-live="polite">
                    Selected Total Wt: <strong id="jwqOrderLinesTotalWt">0.000</strong>
                </div>
                <table class="jwq-table" id="jwqOrderLinesTable">
                    <thead>
                        <tr>
                            <?php foreach ($jwq_order_line_columns as $col): ?>
                                <th data-col="<?php echo htmlspecialchars($col['key']); ?>">
                                    <?php if ($col['key'] === 'diamond_wt'): ?>
                                        <span class="jwq-th-diamond-wt"><?php echo htmlspecialchars($col['label']); ?>
                                            <button type="button" class="jwq-diamond-used-info-btn" title="Diamonds used on this line" aria-label="Diamonds used"><i class="feather icon-info"></i></button>
                                        </span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($col['label']); ?>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="jwqOrderLinesBody">
                    </tbody>
                </table>
            </div>

            <div class="jwq-bottom-split">
                <div class="jwq-bottom-left">
                    <div class="jwq-payment-row">
                        <input type="text" id="jwqPaymentScan" placeholder="Payment" autocomplete="off">
                    </div>
                    <div class="jwq-material-head">
                        <div class="jwq-payment-icons-wrap">
                            <div class="payment-icons jwq-payment-icons" id="jwqPaymentIcons">
                                <div class="payment-icon payment-exchange" title="Metal Exchange">
                                    <img src="icons/metal.jpeg" alt="Metal Exchange" style="width: 45px; height: 45px;">
                                </div>
                                <div class="payment-icon payment-jewelry" title="Scrap Payment">
                                    <img src="icons/scrap.jpeg" alt="Scrap Payment" style="width: 45px; height: 45px;">
                                </div>
                                <div class="payment-icon payment-diamond" title="Diamond">
                                    <img src="icons/diamond.jpeg" alt="Diamond" style="width: 45px; height: 45px;">
                                </div>
                                <div class="payment-icon payment-stone" title="Stone">
                                    <img src="icons/stone.jpeg" alt="Stone" style="width: 45px; height: 45px;">
                                </div>
                                <div class="payment-icon payment-other" title="Other">
                                    <img src="icons/old.jpeg" alt="Other" style="width: 45px; height: 45px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="jwq-mat-table-wrap">
                        <table class="jwq-mat-table">
                            <thead>
                                <tr>
                                    <th>Diamond Category</th>
                                    <th>Product*</th>
                                    <th>Weight</th>
                                    <th>Metal*</th>
                                    <th>Quantity</th>
                                    <th>Purity / Carat</th>
                                    <th>Purity Wt</th>
                                    <th class="jwq-mat-action-col" style="width:44px;" aria-label="Remove"></th>
                                </tr>
                            </thead>
                            <tbody id="jwqMaterialBody">
                                <tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" style="font-weight:700;">Total</td>
                                    <td id="jwqMatTotalWt">0.00</td>
                                    <td colspan="5"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="jwq-comment-row">
                        <input type="text" id="jwqCommentInput" placeholder="Enter Comment" autocomplete="off">
                        <button type="button" id="jwqCommentAdd" aria-label="Add comment"><i class="feather icon-plus"></i></button>
                    </div>
                </div>
                <div class="jwq-bottom-right">
                    <div class="jwq-images-box" id="jwqImagesBox" title="Upload images (opens gallery modal)">
                        <i class="feather icon-upload"></i>
                        <span>Images</span>
                        <small style="font-size:11px;font-weight:500;opacity:0.85;">Click to add images</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="jwqDiamondUsedModal" tabindex="-1" role="dialog" aria-labelledby="jwqDiamondUsedModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#11294b;color:#fff;border:0;">
                <h5 class="modal-title" id="jwqDiamondUsedModalTitle">Diamonds used</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 jwq-diamond-used-table">
                        <thead>
                            <tr>
                                <th>Barcode</th>
                                <th>Product</th>
                                <th class="text-right">Weight</th>
                                <th class="text-right">Qty</th>
                                <th>Added dept</th>
                                <th>Added by</th>
                                <th>Issued</th>
                                <th class="text-center" style="width:56px;" aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody id="jwqDiamondUsedModalBody">
                            <tr><td colspan="8" class="text-center text-muted p-3">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
