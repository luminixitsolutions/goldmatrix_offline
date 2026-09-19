<?php
/**
 * Sale-invoice style payment modals for Jobwork Queue (manufacturing-process.php).
 * Requires $bank_accounts and $metals (arrays). Safe defaults if unset.
 */
if (!isset($bank_accounts) || !is_array($bank_accounts)) {
    $bank_accounts = [];
}
if (!isset($metals) || !is_array($metals)) {
    $metals = [];
}
if (!isset($credit_cards) || !is_array($credit_cards)) {
    $credit_cards = [];
    if (isset($conn) && $conn instanceof mysqli) {
        require_once __DIR__ . '/auragold_credit_card_schema.php';
        if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
            auragold_ensure_branch_id_on_settings_tables($conn);
        }
        $cc_branch_id = function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
        if ($cc_branch_id <= 0 && !empty($auragold_working_branch_id)) {
            $cc_branch_id = (int) $auragold_working_branch_id;
        }
        if (function_exists('auragold_get_credit_cards')) {
            $credit_cards = auragold_get_credit_cards($conn, $cc_branch_id);
        }
    }
}
?>
<!-- Payment Modals -->
<!-- Cash Payment Modal -->
<div class="modal fade" id="cashPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Cash Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="cashDepositInto">
                        <option value="Cash">Cash</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="cashAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('cash')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Bank Payment Modal -->
<div class="modal fade" id="bankPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Bank Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="bankDepositInto">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="bankTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="bankAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('bank')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Cheque Payment Modal -->
<div class="modal fade" id="chequePaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Cheque Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="chequeDepositInto">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="chequeTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="chequeAmount" value="0.00" step="0.01">
                </div>
                <div class="form-group">
                    <label>Cheque Dt.</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="chequeDate" value="<?php echo date('Y-m-d'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-sm" type="button" onclick="document.getElementById('chequeDate').value = '<?php echo date('Y-m-d'); ?>'" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('cheque')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- UPI Payment Modal -->
<div class="modal fade" id="upiPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">UPI Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="upiDepositInto">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="upiTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="upiAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('upi')">Save</button>
            </div>
        </div>
    </div>
</div>
<!-- Card Payment Modal -->
<div class="modal fade" id="cardPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Card Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="cardDepositInto">
                        <option value="">Select Account</option>
                        <?php foreach ($credit_cards as $card) :
                            $card_name = trim((string) ($card['name'] ?? ''));
                            if ($card_name === '') {
                                continue;
                            }
                            $is_default = !empty($card['is_default']);
                            ?>
                        <option value="<?php echo htmlspecialchars($card_name, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $is_default ? ' selected' : ''; ?>><?php echo htmlspecialchars($card_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="cardTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Card No.</label>
                    <input type="text" class="form-control" id="cardNumber" placeholder="Card Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" class="form-control" id="cardAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('card')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Metal Exchange Payment Modal -->
<div class="modal fade" id="metalExchangeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">M. Exch. Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Metal</label>
                            <select class="form-control" id="metalExchangeMetal">
                                <option value="">Select Metal</option>
                                <?php foreach($metals as $metal): ?>
                                <option value="<?php echo $metal['id']; ?>"><?php echo htmlspecialchars($metal['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2" style="position: relative;">
                            <label>Product</label>
                            <input type="text" class="form-control" id="metalExchangeProductInput" placeholder="Type product name to search..." autocomplete="off">
                            <input type="hidden" id="metalExchangeProductId" value="">
                            <div id="metalExchangeProductList" style="display: none; position: absolute; left: 0; right: 0; top: 100%; z-index: 1055; max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Gross Wt</label>
                            <input type="number" class="form-control" id="metalExchangeGrossWt" value="0" step="0.001">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Purity / Karat</label>
                            <input type="number" class="form-control" id="metalExchangePurity" value="1" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Purity Wt.</label>
                            <input type="number" class="form-control" id="metalExchangePurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Quantity</label>
                            <input type="number" class="form-control" id="metalExchangeQty" value="1" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Rate</label>
                            <input type="number" class="form-control" id="metalExchangeRate" value="0" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Amount</label>
                            <input type="number" class="form-control" id="metalExchangeAmount" value="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Item Code</label>
                            <input type="text" class="form-control" id="metalExchangeItemCode" placeholder="Item Code">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('metal-exchange')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Scrap Payment Modal -->
<div class="modal fade" id="scrapPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Scrap Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Metal Type</label>
                            <select class="form-control" id="scrapMetal">
                                <option value="">Select Metal</option>
                                <?php if (!empty($metals) && is_array($metals)) { foreach ($metals as $m) { ?>
                                <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['display_name'] ?? $m['system_name'] ?? ''); ?></option>
                                <?php } } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Gross Wt</label>
                            <input type="number" class="form-control" id="scrapGrossWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Net Wt.</label>
                            <input type="number" class="form-control" id="scrapNetWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                        <div class="form-group">
                            <label>Rate</label>
                            <input type="number" class="form-control" id="scrapRate" value="0" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group" style="position: relative;">
                            <label>Product</label>
                            <input type="text" class="form-control" id="scrapProductInput" placeholder="Type product name to search..." autocomplete="off">
                            <input type="hidden" id="scrapProductId" value="">
                            <div id="scrapProductList" style="display: none; position: absolute; left: 0; right: 0; top: 100%; z-index: 1000; max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                        </div>
                        <div class="form-group">
                            <label>Less Wt.</label>
                            <input type="number" class="form-control" id="scrapLessWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Purity / Karat</label>
                            <input type="number" class="form-control" id="scrapPurity" value="1" step="0.01" placeholder="From product when selected">
                        </div>
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" class="form-control" id="scrapAmount" value="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" class="form-control" id="scrapQty" value="1" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Stone Wt.</label>
                            <input type="number" class="form-control" id="scrapStoneWt" value="0" step="0.001" placeholder="Deduct from weight">
                        </div>
                        <div class="form-group">
                            <label>Purity Wt.</label>
                            <input type="number" class="form-control" id="scrapPurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                        <div class="form-group">
                            <label>Item Code</label>
                            <input type="text" class="form-control" id="scrapItemCode" placeholder="Item Code">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">CLEAR</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('scrap')">SAVE</button>
            </div>
        </div>
    </div>
</div>

<?php
$jwq_diamond_metal_id = 0;
if (!empty($metals) && is_array($metals)) {
    foreach ($metals as $m) {
        $dn = strtolower(trim((string)($m['display_name'] ?? '')));
        $sn = strtolower(trim((string)($m['system_name'] ?? '')));
        if (strpos($dn, 'diamond') !== false || strpos($sn, 'diamond') !== false) {
            $jwq_diamond_metal_id = (int)($m['id'] ?? 0);
            break;
        }
    }
}
/** Same metal scope as diamond-stock-history.php inward (Diamond & Stones). Used with ajax/rfid-available-stock.php metal_ids. */
$jwq_diamond_scope_metal_ids = [];
if (isset($conn) && function_exists('getList')) {
    $jwq_ds_rows = getList("SELECT id FROM tbl_metal WHERE status = 1 AND TRIM(display_name) = 'Diamond & Stones'");
    if (is_array($jwq_ds_rows)) {
        foreach ($jwq_ds_rows as $jwq_ds_row) {
            $jwq_mid = (int) ($jwq_ds_row['id'] ?? 0);
            if ($jwq_mid > 0) {
                $jwq_diamond_scope_metal_ids[] = $jwq_mid;
            }
        }
    }
}
$jwq_diamond_scope_metal_ids = array_values(array_unique($jwq_diamond_scope_metal_ids));
if (empty($jwq_diamond_scope_metal_ids) && $jwq_diamond_metal_id > 0) {
    $jwq_diamond_scope_metal_ids = [$jwq_diamond_metal_id];
}
if (empty($jwq_diamond_scope_metal_ids)) {
    $jwq_diamond_scope_metal_ids = [4];
}
?>
<!-- Diamond Selection Modal (JWQ) -->
<div class="modal fade" id="jwqDiamondModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#fff;border-bottom:1px solid #dbe3f1;">
                <h5 class="modal-title" style="font-weight:700;color:#2d2b7f;">Add Diamond</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:10px 12px 8px;">
                <div style="margin-bottom:8px;">
                    <input type="text" id="jwqDiamondSearch" class="form-control form-control-sm" placeholder="Search" style="max-width:240px;">
                </div>
                <div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-bottom:8px;position:relative;">
                    <button type="button" id="jwqDiamondTabExisting" class="btn btn-sm" style="background:#4f46e5;color:#fff;">Existing</button>
                    <button type="button" id="jwqDiamondTabNew" class="btn btn-sm" style="border:1px solid #cfd8ea;background:#fff;color:#2d2b7f;">Add New</button>
                    <div style="position:absolute;right:0;top:0;display:flex;align-items:center;gap:6px;">
                        <button type="button" id="jwqDiamondAddMore" class="btn btn-sm" style="border:1px solid #cfd8ea;background:#fff;color:#2d2b7f;display:none;">Add More</button>
                        <button type="button" id="jwqDiamondColumnsBtn" title="Columns" style="border:1px solid #cfd8ea;background:#fff;color:#334155;border-radius:4px;padding:2px 6px;font-size:11px;line-height:1.2;">
                            <i class="feather icon-settings"></i> Columns
                        </button>
                    </div>
                    <div id="jwqDiamondColumnsPanel" style="display:none;position:absolute;right:0;top:30px;z-index:9999;width:300px;background:#fff;border:1px solid #dbe3f1;border-radius:10px;box-shadow:0 12px 28px rgba(0,0,0,0.18);overflow:hidden;">
                        <div style="display:flex;align-items:center;gap:6px;padding:8px 10px;border-bottom:1px solid #dbe3f1;background:#f4f7fb;">
                            <button type="button" id="jwqDiamondColumnsMiniX" style="border:1px solid #cfd8ea;background:#fff;color:#334155;border-radius:4px;padding:0 6px;font-size:12px;line-height:20px;">X</button>
                            <button type="button" id="jwqDiamondColumnsMiniP" style="border:1px solid #cfd8ea;background:#fff;color:#334155;border-radius:4px;padding:0 6px;font-size:12px;line-height:20px;">P</button>
                            <span style="font-weight:700;color:#1e3a8a;font-size:14px;"><i class="feather icon-settings" style="color:#eab308;"></i> Columns</span>
                            <button type="button" id="jwqDiamondColumnsClose" style="margin-left:auto;border:none;background:transparent;color:#64748b;font-size:16px;line-height:1;">&times;</button>
                        </div>
                        <div style="padding:8px 10px;border-bottom:1px solid #eef2f7;">
                            <input type="text" id="jwqDiamondColumnsSearch" class="form-control form-control-sm" placeholder="Search" style="height:34px;font-size:14px;">
                        </div>
                        <div style="max-height:320px;overflow-y:auto;overflow-x:hidden;padding:6px 8px;background:#faf9f6;">
                            <div id="jwqDiamondColumnsList" class="jwq-diamond-columns-list" style="display:block;width:100%;"></div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="height:430px;max-height:430px;border:1px solid #d9e1ef;position:relative;overflow-x:auto;overflow-y:auto;">
                    <table class="table table-sm mb-0" id="jwqDiamondTable" style="font-size:11px;white-space:nowrap;">
                        <thead style="background:#f1f5fb;">
                            <tr>
                                <th data-col="row_chk" style="width:32px;"><input type="checkbox" id="jwqDiamondCheckAll"></th>
                                <th data-col="item_code">Item Code</th>
                                <th data-col="barcode_no">Barcode No.</th>
                                <th data-col="style">Style</th>
                                <th data-col="diamond_category">Diamond Category</th>
                                <th data-col="calculation_type">Calculation Type</th>
                                <th data-col="product">Product</th>
                                <th data-col="weight">Weight</th>
                                <th data-col="diamond_carat">Diamond Carat</th>
                                <th data-col="quantity">Quantity</th>
                                <th data-col="rate">Rate</th>
                                <th data-col="certificate_no">Certificate No.</th>
                                <th data-col="cut">Cut</th>
                                <th data-col="color">Color</th>
                                <th data-col="seivesize">SeiveSize</th>
                                <th data-col="size">Size</th>
                                <th data-col="shape">Shape</th>
                                <th data-col="clarity">Clarity</th>
                                <th data-col="action">action</th>
                            </tr>
                        </thead>
                        <tbody id="jwqDiamondTbody"></tbody>
                    </table>
                    <div id="jwqDiamondProductPicker" style="display:none;position:absolute;z-index:1065;min-width:260px;max-width:420px;max-height:260px;overflow:auto;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 8px 20px rgba(0,0,0,0.2);"></div>
                </div>
                <div style="display:flex;justify-content:center;gap:8px;margin-top:10px;">
                    <button type="button" id="jwqDiamondBtnMetalExchange" class="btn btn-sm" style="border:1px solid #cfd8ea;background:#fff;color:#2d2b7f;">Metal Exchange</button>
                    <button type="button" id="jwqDiamondBtnOldJewellery" class="btn btn-sm" style="border:1px solid #cfd8ea;background:#fff;color:#2d2b7f;">Old Jewellery</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Picker modal like Sale Order -->
<div class="modal fade" id="jwqDiamondProductModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:460px;">
        <div class="modal-content">
            <div class="modal-header" style="padding:8px 12px;">
                <h6 class="modal-title" style="font-weight:700;">Search and Select Product</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:10px 12px;">
                <input type="text" id="jwqDiamondProductSearchInput" class="form-control form-control-sm" placeholder="Search by name, article, or SKU...">
                <div id="jwqDiamondProductModalList" style="margin-top:10px;max-height:300px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;background:#fff;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Master Value Picker modal (Cut/Color/SeiveSize/Size/Shape/Clarity) -->
<div class="modal fade" id="jwqDiamondMasterModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:420px;">
        <div class="modal-content">
            <div class="modal-header" style="padding:8px 12px;background:#1e3a8a;border-bottom:none;">
                <h6 class="modal-title" id="jwqDiamondMasterModalTitle" style="font-weight:700;color:#fff;">Select Value</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:1;text-shadow:none;"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:10px 12px;">
                <input type="text" id="jwqDiamondMasterSearchInput" class="form-control form-control-sm" placeholder="Search...">
                <div id="jwqDiamondMasterModalList" style="margin-top:10px;max-height:280px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;background:#fff;"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__metalExchangeProductSearchInited) return;
    window.__metalExchangeProductSearchInited = true;
    var _meSync = false;
    function mePurityFactor(purity) {
        var p = parseFloat(purity) || 0;
        return (p > 0 && p <= 1) ? p : (p / 100);
    }
    /** Recompute purity wt from gross Ã— purity only. */
    function meRefreshPurityWt() {
        var grossEl = document.getElementById('metalExchangeGrossWt');
        var purEl = document.getElementById('metalExchangePurity');
        var pwEl = document.getElementById('metalExchangePurityWt');
        if (!grossEl || !pwEl) return 0;
        var gross = parseFloat(grossEl.value) || 0;
        var purityWt = gross * mePurityFactor(purEl && purEl.value);
        pwEl.value = purityWt.toFixed(3);
        return purityWt;
    }
    /** Rate (or weight/qty) changed â†’ Amount = purityWt Ã— rate Ã— qty */
    function meSyncAmountFromRate() {
        if (_meSync) return;
        _meSync = true;
        try {
            var pw = meRefreshPurityWt();
            var rateEl = document.getElementById('metalExchangeRate');
            var qtyEl = document.getElementById('metalExchangeQty');
            var amtEl = document.getElementById('metalExchangeAmount');
            if (!amtEl) return;
            var rate = parseFloat(rateEl && rateEl.value) || 0;
            var qty = parseFloat(qtyEl && qtyEl.value) || 0;
            var q = qty > 0 ? qty : 1;
            amtEl.value = (pw * rate * q).toFixed(2);
        } finally {
            _meSync = false;
        }
    }
    /** Amount changed â†’ Rate = amount / (purityWt Ã— qty) */
    function meSyncRateFromAmount() {
        if (_meSync) return;
        _meSync = true;
        try {
            var pw = meRefreshPurityWt();
            var qtyEl = document.getElementById('metalExchangeQty');
            var rateEl = document.getElementById('metalExchangeRate');
            var amtEl = document.getElementById('metalExchangeAmount');
            if (!rateEl || !amtEl) return;
            var amt = parseFloat(amtEl.value) || 0;
            var qty = parseFloat(qtyEl && qtyEl.value) || 0;
            var q = qty > 0 ? qty : 1;
            var base = pw * q;
            if (base > 0) {
                rateEl.value = (amt / base).toFixed(6);
            }
        } finally {
            _meSync = false;
        }
    }
    function updateMetalExchangeCalculations() {
        meSyncAmountFromRate();
    }
    window.updateMetalExchangeCalculations = updateMetalExchangeCalculations;
    function initMetalExchangeProductSearch() {
        var metalEl = document.getElementById('metalExchangeMetal');
        var inputEl = document.getElementById('metalExchangeProductInput');
        var idEl = document.getElementById('metalExchangeProductId');
        var listEl = document.getElementById('metalExchangeProductList');
        if (!metalEl || !inputEl || !idEl || !listEl) return;
        var tmr;
        var rateEl = document.getElementById('metalExchangeRate');
        var purEl = document.getElementById('metalExchangePurity');
        function showList(products) {
            listEl.innerHTML = '';
            listEl.style.display = 'block';
            if (!products || !products.length) {
                listEl.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
                return;
            }
            products.forEach(function (p) {
                var div = document.createElement('div');
                div.className = 'p-2 border-bottom';
                div.style.cursor = 'pointer';
                div.style.fontSize = '0.9rem';
                div.onmouseover = function () { this.style.background = '#f1f5f9'; };
                div.onmouseout = function () { this.style.background = ''; };
                div.textContent = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                div.addEventListener('click', function () {
                    inputEl.value = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                    var pcid = (p.characteristic_id || p.id) || '';
                    idEl.value = pcid;
                    idEl.setAttribute('data-catalog-product-id', (p.id && p.characteristic_id) ? String(p.id) : '');
                    if (rateEl && p.rate != null && p.rate !== '') rateEl.value = p.rate;
                    if (purEl && p.opening_purity != null && p.opening_purity !== '') purEl.value = p.opening_purity;
                    var sku = (p.sku_code || p.barcode || '');
                    var ic = document.getElementById('metalExchangeItemCode');
                    if (ic && sku) ic.value = sku;
                    listEl.style.display = 'none';
                    listEl.innerHTML = '';
                    meSyncAmountFromRate();
                });
                listEl.appendChild(div);
            });
        }
        function search() {
            var mid = parseInt(metalEl.value, 10) || 0;
            var q = (inputEl.value || '').trim();
            if (!mid) {
                listEl.innerHTML = '<div class="p-2 text-muted small">Select metal first</div>';
                listEl.style.display = 'block';
                return;
            }
            listEl.innerHTML = '<div class="p-2 text-muted small">Loading...</div>';
            listEl.style.display = 'block';
            var url = 'ajax/get-products-by-metal.php?metal_id=' + encodeURIComponent(mid) + (q ? '&search=' + encodeURIComponent(q) : '');
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    showList(data.success && data.products ? data.products : []);
                })
                .catch(function () {
                    listEl.innerHTML = '<div class="p-2 text-danger small">Error loading products</div>';
                });
        }
        inputEl.addEventListener('input', function () {
            clearTimeout(tmr);
            idEl.value = '';
            tmr = setTimeout(search, 300);
        });
        inputEl.addEventListener('focus', function () {
            if (metalEl.value) search();
        });
        metalEl.addEventListener('change', function () {
            inputEl.value = '';
            idEl.value = '';
            listEl.style.display = 'none';
            listEl.innerHTML = '';
        });
        document.addEventListener('click', function (e) {
            if (listEl.style.display === 'block' && !listEl.contains(e.target) && e.target !== inputEl) {
                listEl.style.display = 'none';
            }
        });
        ['metalExchangeGrossWt', 'metalExchangePurity', 'metalExchangeRate', 'metalExchangeQty'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', meSyncAmountFromRate);
                el.addEventListener('change', meSyncAmountFromRate);
            }
        });
        var amtIn = document.getElementById('metalExchangeAmount');
        if (amtIn) {
            amtIn.addEventListener('input', meSyncRateFromAmount);
            amtIn.addEventListener('change', meSyncRateFromAmount);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMetalExchangeProductSearch);
    } else {
        initMetalExchangeProductSearch();
    }
})();
</script>

<script>
(function () {
    if (window.__jwqDiamondModalInit) return;
    window.__jwqDiamondModalInit = true;
    jwqBindDiamondIssueInputGuards();
    var diamondMetalId = <?php echo (int)$jwq_diamond_metal_id; ?>;
    var jwqDiamondScopeMetalIds = <?php echo json_encode($jwq_diamond_scope_metal_ids); ?>;
    var isAddNewMode = false;
    var currentProductCell = null;
    var currentMasterCell = null;
    var currentMasterField = '';
    var pickerRowsCache = null;
    var pickerLoading = false;
    /** Incremented on each Existing-stock fetch so stale responses cannot clear the grid (fixes first-open race). */
    var jwqDiamondStockFetchGen = 0;
    var diamondCols = ['row_chk', 'item_code', 'barcode_no', 'style', 'diamond_category', 'calculation_type', 'product', 'weight', 'diamond_carat', 'quantity', 'rate', 'certificate_no', 'cut', 'color', 'seivesize', 'size', 'shape', 'clarity', 'action'];
    var diamondHiddenCols = [];

    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    /** Same tbody as jobwork sync (wrap vs modal); avoids duplicate #jwqMaterialBody targeting the wrong table. */
    function jwqResolveMaterialBodyEl() {
        if (typeof jwqGetJobworkMaterialBody === 'function') {
            var el = jwqGetJobworkMaterialBody();
            if (el) {
                return el;
            }
        }
        return document.getElementById('jwqMaterialBody');
    }
    function num(v, d) {
        var n = parseFloat(v);
        if (isNaN(n)) return (0).toFixed(d || 3);
        return n.toFixed(d || 3);
    }
    function getJwoId() {
        var hid = document.getElementById('jwqCurrentJwoId');
        var id = hid ? parseInt(hid.value || '0', 10) : 0;
        if (id > 0) return id;
        return parseInt(window.__jwqCurrentJwoId || '0', 10) || 0;
    }
    function jwqDiamondCellText(tr, col) {
        var td = tr.querySelector('[data-col="' + col + '"]');
        if (!td) return '';
        var input = td.querySelector('input, select, textarea');
        return input ? String(input.value || '').trim() : String(td.textContent || '').trim();
    }
    function jwqDiamondCellNum(tr, col) {
        var s = jwqDiamondCellText(tr, col);
        return parseFloat(String(s).replace(/,/g, '')) || 0;
    }
    function jwqSanitizeDecimalInputValue(raw) {
        if (raw == null) {
            return '';
        }
        var s = String(raw).replace(/,/g, '');
        if (s === '') {
            return '';
        }
        var out = '';
        var dot = false;
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i);
            if (c >= '0' && c <= '9') {
                out += c;
            } else if (c === '.' && !dot) {
                dot = true;
                out += c;
            }
        }
        return out;
    }
    function jwqParseDecimalInput(val) {
        var n = parseFloat(String(jwqSanitizeDecimalInputValue(val)));
        return isFinite(n) ? n : NaN;
    }
    function jwqDiamondRowBalanceWt(tr) {
        var b = parseFloat(String(tr.getAttribute('data-balance-wt') || '').replace(/,/g, ''));
        if (isFinite(b) && b >= 0) {
            return b;
        }
        return jwqDiamondCellNum(tr, 'weight');
    }
    function jwqDiamondRowBalanceQty(tr) {
        var b = parseFloat(String(tr.getAttribute('data-balance-qty') || '').replace(/,/g, ''));
        if (isFinite(b) && b >= 0) {
            return b;
        }
        var q = jwqDiamondCellNum(tr, 'quantity');
        return q > 0 ? q : 0;
    }
    function jwqSyncDiamondRowIssueFromInputs(tr, formatInputs, sourceField) {
        if (!tr) {
            return;
        }
        formatInputs = formatInputs !== false;
        sourceField = sourceField || 'weight';
        var balWt = jwqDiamondRowBalanceWt(tr);
        var balQty = jwqDiamondRowBalanceQty(tr);
        var wtInp = tr.querySelector('.jwq-dia-issue-wt');
        var caratInp = tr.querySelector('.jwq-dia-issue-carat');
        var qtyInp = tr.querySelector('.jwq-dia-issue-qty');
        var wt = wtInp ? jwqParseDecimalInput(wtInp.value) : NaN;
        var carat = caratInp ? jwqParseDecimalInput(caratInp.value) : NaN;
        var qty = qtyInp ? jwqParseDecimalInput(qtyInp.value) : NaN;
        if (sourceField === 'carat') {
            wt = isFinite(carat) && carat >= 0 ? carat / 5 : 0;
        }
        if (!isFinite(wt) || wt < 0) {
            wt = 0;
        }
        if (!isFinite(qty) || qty < 0) {
            qty = 0;
        }
        if (wt > balWt + 0.0001) {
            wt = balWt;
        }
        if (qty > balQty + 0.0001) {
            qty = balQty;
        }
        carat = wt * 5;
        if (wtInp) {
            if (formatInputs || sourceField === 'carat') {
                wtInp.value = wt > 0.0000001 ? num(wt, 3) : '';
            }
        }
        if (caratInp) {
            if (formatInputs || sourceField === 'weight') {
                caratInp.value = carat > 0.0000001 ? num(carat, 3) : '';
            }
        }
        if (qtyInp) {
            if (formatInputs || sourceField !== 'quantity') {
                qtyInp.value = qty > 0.0000001 ? num(qty, 3) : '';
            }
        }
        tr.setAttribute('data-weight', String(wt));
        tr.setAttribute('data-carat', String(carat));
        tr.setAttribute('data-qty', String(qty));
        if (tr._rowData) {
            tr._rowData.gross_weight = wt;
            tr._rowData.carat = carat;
            tr._rowData.quantity = qty > 0 ? qty : 0;
        }
    }
    function jwqBindDiamondIssueInputGuards() {
        var tb = document.getElementById('jwqDiamondTbody');
        if (!tb || tb._jwqIssueInputBound) {
            return;
        }
        tb._jwqIssueInputBound = true;
        tb.addEventListener('input', function (e) {
            var inp = e.target.closest('.jwq-dia-issue-wt, .jwq-dia-issue-carat, .jwq-dia-issue-qty');
            if (!inp || !tb.contains(inp)) {
                return;
            }
            var tr = inp.closest('tr');
            if (!tr) {
                return;
            }
            var clean = jwqSanitizeDecimalInputValue(inp.value);
            if (clean !== inp.value) {
                inp.value = clean;
            }
            var sourceField = inp.classList.contains('jwq-dia-issue-carat')
                ? 'carat'
                : (inp.classList.contains('jwq-dia-issue-qty') ? 'quantity' : 'weight');
            jwqSyncDiamondRowIssueFromInputs(tr, false, sourceField);
        });
        tb.addEventListener('change', function (e) {
            var inp = e.target.closest('.jwq-dia-issue-wt, .jwq-dia-issue-carat, .jwq-dia-issue-qty');
            if (!inp || !tb.contains(inp)) {
                return;
            }
            var tr = inp.closest('tr');
            if (tr) {
                var sourceField = inp.classList.contains('jwq-dia-issue-carat')
                    ? 'carat'
                    : (inp.classList.contains('jwq-dia-issue-qty') ? 'quantity' : 'weight');
                jwqSyncDiamondRowIssueFromInputs(tr, true, sourceField);
            }
        });
        tb.addEventListener('keydown', function (e) {
            var inp = e.target.closest('.jwq-dia-issue-wt, .jwq-dia-issue-carat, .jwq-dia-issue-qty');
            if (!inp || !tb.contains(inp)) {
                return;
            }
            var k = e.key;
            if (!k || k.length !== 1) {
                return;
            }
            if (e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }
            if (k >= '0' && k <= '9') {
                return;
            }
            if (k === '.' && String(inp.value).indexOf('.') < 0) {
                return;
            }
            e.preventDefault();
        });
    }
    /** All checked diamond rows: issue wt/qty from editable inputs (clamped to stock balance on row). */
    function readAllSelectedDiamondRows() {
        var out = [];
        document.querySelectorAll('#jwqDiamondTbody input.jwq-dia-row:checked').forEach(function (cb) {
            var tr = cb.closest('tr');
            if (!tr) {
                return;
            }
            jwqSyncDiamondRowIssueFromInputs(tr);
            var stockId = parseInt(tr.dataset.stockId || tr.dataset.id || '0', 10) || 0;
            var barcode = String(tr.dataset.barcode || '').trim();
            var productName = String(tr.dataset.productName || '').trim();
            var balWt = jwqDiamondRowBalanceWt(tr);
            var balQty = jwqDiamondRowBalanceQty(tr);
            var weight = parseFloat(tr.getAttribute('data-weight') || '0') || 0;
            var qty = parseFloat(tr.getAttribute('data-qty') || '0') || 0;
            if (!stockId || !barcode) {
                return;
            }
            if (weight <= 0.0000001) {
                alert('Enter issue weight for barcode ' + barcode + ' (balance ' + num(balWt, 3) + ' g).');
                return;
            }
            if (qty <= 0.0000001) {
                qty = balQty > 0 ? balQty : 1;
            }
            if (weight > balWt + 0.0001) {
                weight = balWt;
            }
            if (qty > balQty + 0.0001) {
                qty = balQty;
            }
            var rd = tr._rowData;
            out.push({
                stock_id: stockId,
                id: stockId,
                product_id: rd ? (parseInt(rd.product_id || '0', 10) || 0) : 0,
                design_no: jwqDiamondCellText(tr, 'item_code'),
                barcode: barcode,
                style: jwqDiamondCellText(tr, 'style'),
                product_name: productName || jwqDiamondCellText(tr, 'product'),
                gross_weight: weight,
                carat: jwqDiamondCellText(tr, 'diamond_carat') || '0',
                quantity: qty,
                rate: jwqDiamondCellNum(tr, 'rate'),
                certificate_no: jwqDiamondCellText(tr, 'certificate_no'),
                cut: jwqDiamondCellText(tr, 'cut'),
                color: jwqDiamondCellText(tr, 'color'),
                seivesize: jwqDiamondCellText(tr, 'seivesize'),
                size: jwqDiamondCellText(tr, 'size'),
                shape: jwqDiamondCellText(tr, 'shape'),
                clarity: jwqDiamondCellText(tr, 'clarity'),
                calculation: jwqDiamondCellText(tr, 'calculation_type') || 'Fix'
            });
        });
        return out;
    }
    /** Sync header checkbox with row checkboxes (multi-select). */
    function jwqSyncDiamondCheckAllHeader() {
        var master = document.getElementById('jwqDiamondCheckAll');
        if (!master) return;
        var boxes = document.querySelectorAll('#jwqDiamondTbody input.jwq-dia-row');
        var n = boxes.length;
        var c = 0;
        Array.prototype.forEach.call(boxes, function (x) {
            if (x.checked) c += 1;
        });
        master.checked = n > 0 && c === n;
        master.indeterminate = c > 0 && c < n;
    }
    /** Jobwork Queue bottom table: Diamond Category … (see jwq-queue-modal.php). */
    function jwqRefreshJobworkMaterialTotalWt() {
        var body = jwqResolveMaterialBodyEl();
        if (!body) {
            return;
        }
        var wrap = body.closest('#jwqMatTableWrap') || body.closest('.jwq-mat-table-wrap') || body.closest('#jwqModalOverlay') || body.closest('.modal-dialog');
        var tot = wrap ? wrap.querySelector('#jwqMatTotalWt') : null;
        if (!tot) {
            tot = document.getElementById('jwqMatTotalWt');
        }
        if (!tot) {
            return;
        }
        var sum = 0;
        var isReduce = typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode();
        var isAdd = typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode();
        if (isReduce || isAdd) {
            var wantDir = isReduce ? 'reduce' : 'add';
            body.querySelectorAll('tr.jwq-material-metal-row').forEach(function (tr) {
                var dir = tr.getAttribute('data-me-adjust') || wantDir;
                if (dir !== wantDir) {
                    return;
                }
                var td = tr.querySelector('.jwq-mat-wt');
                sum += parseFloat(String(td ? td.textContent : '').replace(/,/g, '')) || 0;
            });
            tot.textContent = sum.toFixed(3);
            if (isReduce && typeof window.jwqSyncOrderLineTotalWtFromMetalReduceTable === 'function') {
                window.jwqSyncOrderLineTotalWtFromMetalReduceTable();
            }
            if (isAdd && typeof window.jwqSyncOrderLineTotalWtFromMetalAddTable === 'function') {
                window.jwqSyncOrderLineTotalWtFromMetalAddTable();
            }
            return;
        }
        body.querySelectorAll('tr.jwq-material-diamond-row').forEach(function (tr) {
            var td = tr.querySelector('.jwq-mat-wt');
            sum += parseFloat(String(td ? td.textContent : '').replace(/,/g, '')) || 0;
        });
        tot.textContent = sum.toFixed(2);
        if (typeof window.jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
            window.jwqSyncOrderLineDiamondWtFromMaterialTable();
        }
    }
    function jwqAppendDiamondRowsToJobworkMaterialTable(rows) {
        var matBody = jwqResolveMaterialBodyEl();
        if (!matBody || !rows || !rows.length) {
            return;
        }
        if (typeof window.jwqIsWeightAdjustMode === 'function' && window.jwqIsWeightAdjustMode()) {
            return;
        }
        var itemIdNum = 0;
        if (typeof window.__jwqDiamondMaterialItemId !== 'undefined' && window.__jwqDiamondMaterialItemId > 0) {
            itemIdNum = parseInt(String(window.__jwqDiamondMaterialItemId), 10) || 0;
        }
        if (itemIdNum < 1) {
            var lineTr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
            itemIdNum = lineTr ? (parseInt(lineTr.getAttribute('data-item-id'), 10) || 0) : 0;
        }
        if (itemIdNum < 1 && typeof jwqCollectQueueLinePayload === 'function') {
            var qLines = jwqCollectQueueLinePayload();
            if (Array.isArray(qLines) && qLines.length > 0) {
                itemIdNum = parseInt(qLines[0].item_id || '0', 10) || 0;
            }
        }
        var itemId = String(itemIdNum > 0 ? itemIdNum : 0);
        var existingStock = {};
        matBody.querySelectorAll('tr[data-stock-id]').forEach(function (x) {
            var sid = x.getAttribute('data-stock-id');
            if (sid) {
                existingStock[sid] = true;
            }
        });
        var empty = matBody.querySelector('.jwq-mat-empty');
        if (empty) {
            empty.remove();
        }
        rows.forEach(function (r) {
            var sidNum = parseInt(r.stock_id || r.id || r.stockId || '0', 10) || 0;
            var bcDisp = String(r.barcode || '').trim();
            if (!sidNum || !bcDisp) {
                return;
            }
            var existTr = matBody.querySelector('tr.jwq-material-diamond-row[data-stock-id="' + String(sidNum) + '"]');
            if (existTr) {
                var wtRawUp = parseFloat(r.gross_weight) || 0;
                var qtyRawUp = parseFloat(r.quantity) || 0;
                if (qtyRawUp <= 0) {
                    qtyRawUp = 1;
                }
                existTr.setAttribute('data-weight', String(wtRawUp));
                existTr.setAttribute('data-qty', String(qtyRawUp));
                var wtTd = existTr.querySelector('.jwq-mat-wt');
                var qtyTd = existTr.querySelector('.jwq-mat-qty');
                if (wtTd) {
                    wtTd.textContent = num(wtRawUp, 3);
                }
                if (qtyTd) {
                    qtyTd.textContent = num(qtyRawUp, 3);
                }
                return;
            }
            existingStock[String(sidNum)] = true;
            var fdEl = document.getElementById('jwqFromDept');
            var fuEl = document.getElementById('jwqFromUser');
            var isAddWeightBranch = typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode();
            var addDeptId = 0;
            var addUserId = 0;
            if (!isAddWeightBranch) {
                addDeptId = fdEl ? (parseInt(fdEl.value || '0', 10) || 0) : 0;
                addUserId = fuEl ? (parseInt(fuEl.value || '0', 10) || 0) : 0;
            }
            var cat = 'Diamond';
            var prodBase = String(r.product_name || '—').trim();
            var prod = esc(bcDisp) + ' — ' + esc(prodBase);
            var wtDisp = num(r.gross_weight, 3);
            var metal = 'Diamond';
            var qtyDisp = num(r.quantity, 3);
            var wtRaw = parseFloat(r.gross_weight) || 0;
            var qtyRaw = parseFloat(r.quantity) || 0;
            if (qtyRaw <= 0) {
                qtyRaw = 1;
            }
            var caratStr = String(r.carat != null && r.carat !== '' ? r.carat : '0');
            var tr = document.createElement('tr');
            tr.className = 'jwq-material-diamond-row';
            tr.setAttribute('data-jwq-mat-from-diamond', '1');
            tr.setAttribute('data-jobwork-item-id', itemId);
            tr.setAttribute('data-stock-id', String(sidNum));
            tr.setAttribute('data-id', String(sidNum));
            tr.setAttribute('data-diamond-stock-id', String(sidNum));
            if (r.product_id) {
                tr.setAttribute('data-product-id', String(r.product_id));
            }
            tr.setAttribute('data-barcode', bcDisp);
            var pnRaw = String(r.product_name || '').trim();
            if (pnRaw) {
                tr.setAttribute('data-product-name', pnRaw);
            }
            tr.setAttribute('data-weight', String(wtRaw));
            tr.setAttribute('data-qty', String(qtyRaw));
            tr.setAttribute('data-added-by-dept-id', String(addDeptId));
            tr.setAttribute('data-added-by-user-id', String(addUserId));
            if (r.from_used_diamond_modal) {
                tr.setAttribute('data-jwq-from-used-modal', '1');
            }
            if (!isAddWeightBranch && fdEl && fdEl.options && fdEl.selectedIndex >= 0) {
                var dnm = String(fdEl.options[fdEl.selectedIndex].text || '').trim();
                if (dnm) {
                    tr.setAttribute('data-added-by-dept-name', dnm);
                }
            }
            if (!isAddWeightBranch && fuEl && fuEl.options && fuEl.selectedIndex >= 0) {
                var unm = String(fuEl.options[fuEl.selectedIndex].text || '').trim();
                if (unm) {
                    tr.setAttribute('data-added-by-user-name', unm);
                }
            }
            var delTd =
                '<td class="jwq-mat-actions text-center">'
                + '<button type="button" class="btn btn-link btn-sm text-danger p-0 jwq-mat-diamond-remove" title="Remove from list" aria-label="Remove diamond from list">'
                + '<i class="feather icon-trash-2"></i></button></td>';
            tr.innerHTML = '<td>' + cat + '</td><td>' + prod + '</td><td class="jwq-mat-wt">' + wtDisp + '</td><td>' + metal + '</td><td class="jwq-mat-qty">' + qtyDisp + '</td><td>' + esc(caratStr) + '</td><td></td>' + delTd;
            matBody.appendChild(tr);
        });
        jwqRefreshJobworkMaterialTotalWt();
        var dmEl = document.getElementById('jwqDiamondModal');
        if (dmEl && dmEl.classList.contains('show')) {
            jwqSyncExistingDiamondModalChecksFromMaterial();
        }
    }
    function jwqGetMaterialGridDiamondStockIdsAndBarcodes() {
        var sids = {};
        var barcodes = {};
        var mb = document.getElementById('jwqMaterialBody');
        if (!mb) {
            return { sids: sids, barcodes: barcodes };
        }
        mb.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
            var sid = parseInt(tr.getAttribute('data-stock-id') || '0', 10) || 0;
            if (sid > 0) {
                sids[sid] = true;
            }
            var bc = String(tr.getAttribute('data-barcode') || '').trim().toUpperCase();
            if (bc) {
                barcodes[bc] = true;
            }
        });
        return { sids: sids, barcodes: barcodes };
    }
    function jwqSyncExistingDiamondModalChecksFromMaterial() {
        var tb = document.getElementById('jwqDiamondTbody');
        if (!tb) {
            return;
        }
        var mgp = jwqGetMaterialGridDiamondStockIdsAndBarcodes();
        tb.querySelectorAll('tr.jwq-diamond-stock-modal-row').forEach(function (tr) {
            var sid = parseInt(tr.getAttribute('data-stock-id') || tr.getAttribute('data-id') || '0', 10) || 0;
            var bc = String(tr.getAttribute('data-barcode') || '').trim().toUpperCase();
            var cb = tr.querySelector('input.jwq-dia-row');
            if (!cb) {
                return;
            }
            var onMat = (sid > 0 && mgp.sids[sid]) || (!!bc && !!mgp.barcodes[bc]);
            cb.checked = !!onMat;
        });
        jwqSyncDiamondCheckAllHeader();
    }
    function updateHeaderActions() {
        var addMore = document.getElementById('jwqDiamondAddMore');
        if (addMore) addMore.style.display = isAddNewMode ? '' : 'none';
    }
    function buildRowHtml(r, i, editable) {
        var editAttr = editable ? ' contenteditable="true"' : '';
        var cls = editable ? ' class="jwq-dia-product-cell"' : '';
        var masterCls = editable ? ' class="jwq-dia-master-cell"' : '';
        var calcVal = String(r.calculation || r.calculation_type || 'Fix');
        var calcCell = editable
            ? '<select class="form-control form-control-sm jwq-dia-calc-select" style="min-width:120px;height:24px;padding:1px 6px;font-size:11px;">'
                + '<option' + (calcVal === 'Carat X Rate' ? ' selected' : '') + '>Carat X Rate</option>'
                + '<option' + (calcVal === 'Fix' ? ' selected' : '') + '>Fix</option>'
                + '<option' + (calcVal === 'Quantity X Rate' ? ' selected' : '') + '>Quantity X Rate</option>'
            + '</select>'
            : esc(calcVal);
        var actionCell = editable
            ? '<button type="button" class="btn btn-sm jwq-dia-remove-row" style="border:1px solid #fecaca;background:#fff;color:#b91c1c;padding:1px 7px;font-size:11px;">Del</button>'
            : '—';
        var sidForTr = parseInt(r.stock_id || r.id || 0, 10) || 0;
        var bcForTr = String(r.barcode || '').trim();
        var onMaterialGrid = false;
        if (!editable) {
            var mgp0 = jwqGetMaterialGridDiamondStockIdsAndBarcodes();
            if (sidForTr > 0 && mgp0.sids[sidForTr]) {
                onMaterialGrid = true;
            }
            if (bcForTr !== '' && mgp0.barcodes[bcForTr.toUpperCase()]) {
                onMaterialGrid = true;
            }
        }
        var balWt = parseFloat(r.gross_weight) || 0;
        var balQty = parseFloat(r.quantity) || 0;
        if (balQty <= 0) {
            balQty = 1;
        }
        var issueWt = balWt;
        var issueQty = balQty;
        var wtAttr = String(issueWt);
        var qtyAttr = String(issueQty);
        var prodNameAttr = esc(String(r.product_name || ''));
        var wtCell;
        var caratCell;
        var qtyCell;
        if (editable) {
            wtCell = '<td data-col="weight"' + editAttr + '>' + esc(num(r.gross_weight, 3)) + '</td>';
            caratCell = '<td data-col="diamond_carat"' + editAttr + '>' + esc(r.carat || '0') + '</td>';
            qtyCell = '<td data-col="quantity"' + editAttr + '>' + esc(num(r.quantity, 3)) + '</td>';
        } else {
            var wtTitle = 'Stock balance ' + num(balWt, 3) + ' g — enter issue weight';
            var qtyTitle = 'Stock balance ' + num(balQty, 3) + ' — enter issue qty';
            wtCell = '<td data-col="weight"><input type="text" class="form-control form-control-sm jwq-dia-issue-wt" inputmode="decimal" autocomplete="off" value="' + esc(num(issueWt, 3)) + '" title="' + esc(wtTitle) + '" style="min-width:76px;"></td>';
            caratCell = '<td data-col="diamond_carat"><input type="text" class="form-control form-control-sm jwq-dia-issue-carat" inputmode="decimal" autocomplete="off" value="' + esc(num(issueWt * 5, 3)) + '" title="1 gram = 5 carat" style="min-width:76px;"></td>';
            qtyCell = '<td data-col="quantity"><input type="text" class="form-control form-control-sm jwq-dia-issue-qty" inputmode="decimal" autocomplete="off" value="' + esc(num(issueQty, 3)) + '" title="' + esc(qtyTitle) + '" style="min-width:64px;"></td>';
        }
        var trOpen = '<tr class="jwq-diamond-stock-modal-row"'
            + (sidForTr > 0 ? ' data-stock-id="' + String(sidForTr) + '" data-id="' + String(sidForTr) + '"' : '')
            + (bcForTr !== '' ? ' data-barcode="' + esc(bcForTr) + '"' : '')
            + ' data-balance-wt="' + esc(String(balWt)) + '" data-balance-qty="' + esc(String(balQty)) + '"'
            + ' data-weight="' + esc(wtAttr) + '" data-qty="' + esc(qtyAttr) + '"'
            + (prodNameAttr !== '' ? ' data-product-name="' + prodNameAttr + '"' : '')
            + '>';
        return trOpen
            + '<td data-col="row_chk"><input class="jwq-dia-row" type="checkbox"' + (onMaterialGrid ? ' checked' : '') + '></td>'
            + '<td data-col="item_code"' + editAttr + '>' + esc(r.design_no || '') + '</td>'
            + '<td data-col="barcode_no"' + editAttr + '>' + esc(r.barcode || '') + '</td>'
            + '<td data-col="style"' + editAttr + '>' + esc(r.style || '') + '</td>'
            + '<td data-col="diamond_category">Diamond</td>'
            + '<td data-col="calculation_type">' + calcCell + '</td>'
            + '<td data-col="product"' + editAttr + cls + '>' + esc(r.product_name || '') + '</td>'
            + wtCell
            + caratCell
            + qtyCell
            + '<td data-col="rate"' + editAttr + '>' + esc(num(r.rate, 2)) + '</td>'
            + '<td data-col="certificate_no"' + editAttr + '>' + esc(r.certificate_no || '') + '</td>'
            + '<td data-col="cut" data-master-field="cut"' + masterCls + '>' + esc(r.cut || '') + '</td>'
            + '<td data-col="color" data-master-field="color"' + masterCls + '>' + esc(r.color || '') + '</td>'
            + '<td data-col="seivesize" data-master-field="seivesize"' + masterCls + '>' + esc(r.seivesize || '') + '</td>'
            + '<td data-col="size" data-master-field="size"' + masterCls + '>' + esc(r.size || '') + '</td>'
            + '<td data-col="shape" data-master-field="shape"' + masterCls + '>' + esc(r.shape || '') + '</td>'
            + '<td data-col="clarity" data-master-field="clarity"' + masterCls + '>' + esc(r.clarity || '') + '</td>'
            + '<td data-col="action">' + actionCell + '</td>'
            + '</tr>';
    }
    function renderRows(rows, isAddNew) {
        var tb = document.getElementById('jwqDiamondTbody');
        if (!tb) return;
        if (!rows.length) {
            tb.innerHTML = '<tr><td colspan="19" class="text-center text-muted" style="padding:28px 8px;">No Rows To Show</td></tr>';
            applyDiamondColumnVisibility();
            jwqSyncDiamondCheckAllHeader();
            return;
        }
        tb.innerHTML = rows.map(function (r, i) { return buildRowHtml(r, i, !!isAddNew); }).join('');
        Array.prototype.forEach.call(tb.querySelectorAll('tr'), function (tr, idx) {
            tr._rowData = rows[idx];
        });
        applyDiamondColumnVisibility();
        if (!isAddNew) {
            jwqSyncExistingDiamondModalChecksFromMaterial();
            Array.prototype.forEach.call(tb.querySelectorAll('tr.jwq-diamond-stock-modal-row'), function (tr) {
                jwqSyncDiamondRowIssueFromInputs(tr);
            });
        } else {
            jwqSyncDiamondCheckAllHeader();
        }
    }
    function hideProductPicker() {
        var picker = document.getElementById('jwqDiamondProductPicker');
        if (picker) {
            picker.style.display = 'none';
            picker.innerHTML = '';
        }
        currentProductCell = null;
    }
    function renderProductModalList(rows) {
        var list = document.getElementById('jwqDiamondProductModalList');
        if (!list) return;
        if (!rows || !rows.length) {
            list.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
            return;
        }
        list.innerHTML = rows.map(function (p) {
            var n = String(p.name || '').trim();
            var code = String(p.sku_code || p.barcode || '').trim();
            var wt = (p.metal_weight != null && p.metal_weight !== '') ? p.metal_weight : (p.opening_weight || 0);
            var purity = (p.opening_purity != null && p.opening_purity !== '') ? p.opening_purity : '';
            return '<div class="jwq-dia-modal-pick-row"'
                + ' data-name="' + esc(n) + '"'
                + ' data-code="' + esc(code) + '"'
                + ' data-barcode="' + esc(p.barcode || '') + '"'
                + ' data-rate="' + esc(p.rate || '0') + '"'
                + ' data-weight="' + esc(wt) + '"'
                + ' data-qty="' + esc((p.metal_qty != null && p.metal_qty !== '') ? p.metal_qty : (p.opening_qty || 1)) + '"'
                + ' data-carat="' + esc(p.carat || 0) + '"'
                + ' style="padding:9px 10px;border-bottom:1px solid #eef2f7;cursor:pointer;">'
                + '<div style="font-weight:600;color:#111827;font-size:12px;">' + esc(n) + (code ? ' - ' + esc(code) : '') + '</div>'
                + '<div style="font-size:11px;color:#6b7280;">Weight: ' + esc(num(wt, 3)) + (purity !== '' ? ' | Purity: ' + esc(purity) : '') + '</div>'
                + '</div>';
        }).join('');
    }
    function saveHiddenCols() {
        try { localStorage.setItem('jwq_diamond_hidden_cols', JSON.stringify(diamondHiddenCols)); } catch (e) {}
    }
    function loadHiddenCols() {
        try {
            var raw = localStorage.getItem('jwq_diamond_hidden_cols');
            var arr = raw ? JSON.parse(raw) : [];
            diamondHiddenCols = Array.isArray(arr) ? arr : [];
        } catch (e) {
            diamondHiddenCols = [];
        }
    }
    function applyDiamondColumnVisibility() {
        var table = document.getElementById('jwqDiamondTable');
        if (!table) return;
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (diamondHiddenCols.indexOf(col) >= 0) el.style.display = 'none';
            else el.style.display = '';
        });
    }
    function renderColumnsPanel() {
        var list = document.getElementById('jwqDiamondColumnsList');
        if (!list) return;
        var colLabelMap = {
            row_chk: 'Select',
            item_code: 'Item Code',
            barcode_no: 'Barcode No.',
            style: 'Style',
            diamond_category: 'Diamond Category',
            calculation_type: 'Calculation Type',
            product: 'Product',
            weight: 'Weight',
            diamond_carat: 'Diamond Carat',
            quantity: 'Quantity',
            rate: 'Rate',
            certificate_no: 'Certificate No.',
            cut: 'Cut',
            color: 'Color',
            seivesize: 'SeiveSize',
            size: 'Size',
            shape: 'Shape',
            clarity: 'Clarity',
            action: 'Action'
        };
        list.innerHTML = diamondCols.map(function (c) {
            var checked = diamondHiddenCols.indexOf(c) < 0 ? 'checked' : '';
            return '<label data-col-label="' + esc((colLabelMap[c] || c).toLowerCase()) + '" class="jwq-diamond-col-row" style="display:block;width:100%;box-sizing:border-box;padding:6px 4px;margin:0;border-bottom:1px solid #eef2f7;cursor:pointer;">'
                + '<span style="display:flex;flex-direction:row;align-items:center;gap:10px;width:100%;flex-wrap:nowrap;">'
                + '<input type="checkbox" class="jwq-dia-col-cb" data-col="' + esc(c) + '" ' + checked + ' style="flex-shrink:0;width:16px;height:16px;margin:0;">'
                + '<span style="font-size:13px;line-height:1.35;color:#1e3a8a;font-weight:600;flex:1;min-width:0;">' + esc(colLabelMap[c] || c) + '</span>'
                + '</span></label>';
        }).join('');
    }
    function fetchDiamondProducts(searchText) {
        if (pickerLoading) return Promise.resolve([]);
        if (!diamondMetalId || diamondMetalId < 1) return Promise.resolve([]);
        pickerLoading = true;
        var q = String(searchText || '').trim();
        var url = 'ajax/get-products-by-metal.php?metal_id=' + encodeURIComponent(String(diamondMetalId)) + '&diamond_category=' + encodeURIComponent('Diamonds') + (q ? '&search=' + encodeURIComponent(q) : '');
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var rows = (d && d.success && Array.isArray(d.products)) ? d.products : [];
                pickerRowsCache = rows;
                return rows;
            })
            .catch(function () { return []; })
            .finally(function () { pickerLoading = false; });
    }
    function renderProductPicker(rows, anchorCell) {
        var picker = document.getElementById('jwqDiamondProductPicker');
        var wrap = document.getElementById('jwqDiamondTable') ? document.getElementById('jwqDiamondTable').parentElement : null;
        if (!picker || !wrap || !anchorCell) return;
        if (!rows.length) {
            picker.innerHTML = '<div class="p-2 text-muted small">No diamond products found</div>';
        } else {
            picker.innerHTML = rows.map(function (p) {
                var n = String(p.name || '').trim();
                var code = String(p.sku_code || p.barcode || '').trim();
                return '<div class="jwq-dia-pick-row"'
                    + ' data-name="' + esc(n) + '"'
                    + ' data-code="' + esc(code) + '"'
                    + ' data-barcode="' + esc(p.barcode || '') + '"'
                    + ' data-rate="' + esc(p.rate || '0') + '"'
                    + ' data-weight="' + esc((p.metal_weight != null && p.metal_weight !== '') ? p.metal_weight : (p.opening_weight || 0)) + '"'
                    + ' data-qty="' + esc((p.metal_qty != null && p.metal_qty !== '') ? p.metal_qty : (p.opening_qty || 1)) + '"'
                    + ' data-carat="' + esc(p.carat || 0) + '"'
                    + ' style="padding:7px 10px;border-bottom:1px solid #eef2f7;cursor:pointer;font-size:12px;">'
                    + '<div style="font-weight:600;color:#0f172a;">' + esc(n) + '</div>'
                    + '<div style="font-size:11px;color:#64748b;">' + esc(code) + '</div>'
                    + '</div>';
            }).join('');
        }
        var cRect = anchorCell.getBoundingClientRect();
        var wRect = wrap.getBoundingClientRect();
        picker.style.left = Math.max(0, cRect.left - wRect.left) + 'px';
        picker.style.top = Math.max(0, cRect.bottom - wRect.top + 2) + 'px';
        picker.style.display = 'block';
        currentProductCell = anchorCell;
    }
    function applyPickedProductToRow(pickEl) {
        if (!pickEl || !currentProductCell) return;
        var tr = currentProductCell.closest('tr');
        if (!tr) return;
        var setCol = function (col, val) {
            var td = tr.querySelector('td[data-col="' + col + '"]');
            if (td) td.textContent = val;
        };
        var pName = pickEl.getAttribute('data-name') || '';
        setCol('product', pName);
        setCol('item_code', pickEl.getAttribute('data-code') || '');
        setCol('barcode_no', pickEl.getAttribute('data-barcode') || '');
        setCol('weight', num(pickEl.getAttribute('data-weight') || 0, 3));
        setCol('quantity', num(pickEl.getAttribute('data-qty') || 1, 3));
        setCol('rate', num(pickEl.getAttribute('data-rate') || 0, 2));
        setCol('diamond_carat', pickEl.getAttribute('data-carat') || '0');
        if (!tr._rowData) tr._rowData = {};
        tr._rowData.product_name = pName;
        tr._rowData.design_no = pickEl.getAttribute('data-code') || '';
        tr._rowData.barcode = pickEl.getAttribute('data-barcode') || '';
        tr._rowData.gross_weight = parseFloat(pickEl.getAttribute('data-weight') || 0) || 0;
        tr._rowData.quantity = parseFloat(pickEl.getAttribute('data-qty') || 1) || 1;
        tr._rowData.rate = parseFloat(pickEl.getAttribute('data-rate') || 0) || 0;
        tr._rowData.carat = pickEl.getAttribute('data-carat') || 0;
        hideProductPicker();
    }
    function openProductModalForCell(prodCell) {
        currentProductCell = prodCell;
        if (window.jQuery && window.jQuery.fn.modal) {
            var inp = document.getElementById('jwqDiamondProductSearchInput');
            if (inp) inp.value = '';
            fetchDiamondProducts('').then(function (rows) {
                renderProductModalList(rows);
                window.jQuery('#jwqDiamondProductModal').modal('show');
                if (inp) setTimeout(function () { inp.focus(); }, 150);
            });
        }
    }
    function renderMasterModalList(options) {
        var list = document.getElementById('jwqDiamondMasterModalList');
        if (!list) return;
        if (!options || !options.length) {
            list.innerHTML = '<div class="p-2 text-muted small">No values found</div>';
            return;
        }
        list.innerHTML = options.map(function (v) {
            return '<div class="jwq-dia-master-pick-row" data-value="' + esc(v) + '" style="padding:9px 10px;border-bottom:1px solid #eef2f7;cursor:pointer;">'
                + '<div style="font-weight:600;color:#111827;font-size:12px;">' + esc(v) + '</div>'
                + '</div>';
        }).join('');
    }
    function jwqResolveAjaxUrl(path) {
        try {
            if (!path || path.indexOf('http') === 0) return path;
            return new URL(path, window.location.href).href;
        } catch (e) {
            return path;
        }
    }
    function fetchMasterOptions(field, searchText) {
        var q = String(searchText || '').trim();
        var url = jwqResolveAjaxUrl('ajax/mp-diamond-master-options.php?field=' + encodeURIComponent(field || '') + (q ? '&search=' + encodeURIComponent(q) : ''));
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (t) {
                    try {
                        return JSON.parse(t);
                    } catch (e) {
                        if (window.console && console.warn) {
                            console.warn('mp-diamond-master-options: not JSON', t.slice(0, 200));
                        }
                        throw e;
                    }
                });
            })
            .then(function (d) { return (d && d.success && Array.isArray(d.options)) ? d.options : []; })
            .catch(function () { return []; });
    }
    function openMasterModalForCell(cell, field) {
        currentMasterCell = cell;
        currentMasterField = field || '';
        var title = document.getElementById('jwqDiamondMasterModalTitle');
        if (title) title.textContent = 'Select ' + (field ? (field.charAt(0).toUpperCase() + field.slice(1)) : 'Value');
        var inp = document.getElementById('jwqDiamondMasterSearchInput');
        if (inp) inp.value = '';
        fetchMasterOptions(currentMasterField, '').then(function (rows) {
            renderMasterModalList(rows);
            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery('#jwqDiamondMasterModal').modal('show');
                if (inp) setTimeout(function () { inp.focus(); }, 150);
            }
        });
    }
    function applyMasterValueToCell(value) {
        if (!currentMasterCell) return;
        var tr = currentMasterCell.closest('tr');
        if (!tr) return;
        currentMasterCell.textContent = value || '';
        if (!tr._rowData) tr._rowData = {};
        if (currentMasterField) tr._rowData[currentMasterField] = value || '';
    }
    function addMoreRow() {
        var tb = document.getElementById('jwqDiamondTbody');
        if (!tb) return;
        if (tb.querySelector('td[colspan]')) tb.innerHTML = '';
        var blank = { design_no: '', barcode: '', style: '', diamond_category: 'Diamond', calculation: '', product_name: '', gross_weight: 0, carat: 0, quantity: 1, rate: 0 };
        var idx = tb.querySelectorAll('tr').length;
        tb.insertAdjacentHTML('beforeend', buildRowHtml(blank, idx, true));
        var tr = tb.querySelectorAll('tr')[idx];
        if (tr) tr._rowData = blank;
        applyDiamondColumnVisibility();
        jwqSyncDiamondCheckAllHeader();
    }
    function rfidRowToDiamondRow(r) {
        /* Balance weight = SUM(tbl_stock.current_weight) via API final_wt; gross_wt is opening/SJ gross and must not override balance. */
        var gw = parseFloat(r.final_wt);
        if (isNaN(gw) || gw < 0) {
            gw = 0;
        }
        if (gw <= 0.0000001) {
            gw = parseFloat(r.gross_wt) || 0;
        }
        var ct = String(r.carat != null ? r.carat : '').trim();
        if (!ct || ct === '0' || ct === '0.000') {
            ct = gw > 0 ? (gw * 5).toFixed(3) : '0';
        }
        var design = String(r.rfid_code || '').trim();
        if (!design) {
            design = String(r.article || '').trim();
        }
        var sid = parseInt(r.stock_id || r.id || '0', 10) || 0;
        return {
            stock_id: sid,
            id: sid,
            product_id: parseInt(r.product_code || '0', 10) || 0,
            design_no: design,
            barcode: String(r.barcode || ''),
            style: '',
            product_name: String(r.product_name || ''),
            gross_weight: gw,
            carat: ct,
            quantity: parseFloat(r.qty) || 1,
            rate: 0,
            certificate_no: '',
            cut: '',
            color: '',
            seivesize: '',
            size: '',
            shape: '',
            clarity: ''
        };
    }
    function fetchRowsAndRender(isAddNew) {
        if (!isAddNew) {
            var mids = (typeof jwqDiamondScopeMetalIds !== 'undefined' && jwqDiamondScopeMetalIds && jwqDiamondScopeMetalIds.length)
                ? jwqDiamondScopeMetalIds.join(',')
                : (diamondMetalId > 0 ? String(diamondMetalId) : '');
            if (!mids) {
                renderRows([], false);
                return;
            }
            var gen = ++jwqDiamondStockFetchGen;
            var jwoIdForStock = getJwoId();
            var stockUrl = jwqResolveAjaxUrl('ajax/rfid-available-stock.php?branch_id=0&metal_ids=' + encodeURIComponent(mids) + '&jobwork_order_id=' + encodeURIComponent(String(jwoIdForStock > 0 ? jwoIdForStock : 0)) + '&_=' + String(Date.now()));
            fetch(stockUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (gen !== jwqDiamondStockFetchGen) {
                        return;
                    }
                    var raw = (d && d.success && Array.isArray(d.rows)) ? d.rows : [];
                    var rows = raw.map(rfidRowToDiamondRow);
                    renderRows(rows, false);
                })
                .catch(function () {
                    if (gen !== jwqDiamondStockFetchGen) {
                        return;
                    }
                    renderRows([], false);
                });
            return;
        }
        var jwoId = getJwoId();
        if (jwoId < 1) {
            renderRows([], isAddNew);
            return;
        }
        fetch('ajax/mp-jobwork-order-items.php?id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var rows = (d && d.ok && Array.isArray(d.items)) ? d.items : [];
                rows = rows.filter(function (it) {
                    var p = String(it.product_name || '').toLowerCase();
                    return p.indexOf('diamond') >= 0 || p.indexOf('dia') >= 0;
                });
                if (!rows.length && isAddNew) {
                    rows = [{ design_no: '', barcode: '', style: '', diamond_category: 'Diamond', calculation: '', product_name: '', gross_weight: 0, carat: 0, quantity: 1, rate: 0 }];
                }
                renderRows(rows, isAddNew);
            })
            .catch(function () { renderRows([], isAddNew); });
    }
    function filterRows() {
        var qEl = document.getElementById('jwqDiamondSearch');
        var q = qEl ? String(qEl.value || '').toLowerCase().trim() : '';
        Array.prototype.forEach.call(document.querySelectorAll('#jwqDiamondTbody tr'), function (tr) {
            if (!q) {
                tr.style.display = '';
                return;
            }
            tr.style.display = String(tr.textContent || '').toLowerCase().indexOf(q) >= 0 ? '' : 'none';
        });
    }
    function switchTab(isAddNew) {
        isAddNewMode = !!isAddNew;
        hideProductPicker();
        updateHeaderActions();
        var ex = document.getElementById('jwqDiamondTabExisting');
        var nw = document.getElementById('jwqDiamondTabNew');
        if (ex && nw) {
            ex.style.background = isAddNew ? '#fff' : '#4f46e5';
            ex.style.color = isAddNew ? '#2d2b7f' : '#fff';
            ex.style.border = isAddNew ? '1px solid #cfd8ea' : 'none';
            nw.style.background = isAddNew ? '#4f46e5' : '#fff';
            nw.style.color = isAddNew ? '#fff' : '#2d2b7f';
            nw.style.border = isAddNew ? 'none' : '1px solid #cfd8ea';
        }
        fetchRowsAndRender(isAddNew);
    }

    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'jwqDiamondTabExisting') switchTab(false);
        if (e.target && e.target.id === 'jwqDiamondTabNew') switchTab(true);
        if (e.target && e.target.id === 'jwqDiamondAddMore') {
            e.preventDefault();
            addMoreRow();
            return;
        }
        var delBtn = e.target && e.target.closest ? e.target.closest('.jwq-dia-remove-row') : null;
        if (delBtn) {
            e.preventDefault();
            var trDel = delBtn.closest('tr');
            if (trDel) trDel.remove();
            jwqSyncDiamondCheckAllHeader();
            return;
        }
        var matDel = e.target && e.target.closest ? e.target.closest('.jwq-mat-diamond-remove') : null;
        if (matDel) {
            e.preventDefault();
            var mtr = matDel.closest('tr.jwq-material-diamond-row');
            var mb = jwqResolveMaterialBodyEl();
            if (!mtr || !mb || !mb.contains(mtr)) {
                return;
            }
            mtr.remove();
            if (!mb.querySelector('.jwq-material-diamond-row') && !mb.querySelector('.jwq-material-metal-row')) {
                mb.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
            }
            jwqRefreshJobworkMaterialTotalWt();
            var dmOpen = document.getElementById('jwqDiamondModal');
            if (dmOpen && dmOpen.classList.contains('show')) {
                jwqSyncExistingDiamondModalChecksFromMaterial();
            }
            return;
        }
        var matMetalDel = e.target && e.target.closest ? e.target.closest('.jwq-mat-metal-remove') : null;
        if (matMetalDel) {
            e.preventDefault();
            var mtrMe = matMetalDel.closest('tr.jwq-material-metal-row');
            var mbMe = jwqResolveMaterialBodyEl();
            if (!mtrMe || !mbMe || !mbMe.contains(mtrMe)) {
                return;
            }
            mtrMe.remove();
            if (!mbMe.querySelector('.jwq-material-diamond-row') && !mbMe.querySelector('.jwq-material-metal-row')) {
                mbMe.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
            }
            jwqRefreshJobworkMaterialTotalWt();
            return;
        }
        var meBtn = e.target && e.target.closest ? e.target.closest('#jwqDiamondBtnMetalExchange') : null;
        if (meBtn && meBtn.id === 'jwqDiamondBtnMetalExchange') {
            e.preventDefault();
            var drows = readAllSelectedDiamondRows();
            if (!drows.length) {
                alert('Select one or more diamond rows and enter issue weight (not more than balance).');
                return;
            }
            jwqAppendDiamondRowsToJobworkMaterialTable(drows);
            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery('#jwqDiamondModal').modal('hide');
            }
        }
        if (e.target && e.target.id === 'jwqDiamondBtnOldJewellery') {
            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery('#jwqDiamondModal').modal('hide');
                window.jQuery('#scrapPaymentModal').modal('show');
            }
        }
        var pickRow = e.target && e.target.closest ? e.target.closest('.jwq-dia-pick-row') : null;
        if (pickRow) {
            applyPickedProductToRow(pickRow);
            return;
        }
        var modalPickRow = e.target && e.target.closest ? e.target.closest('.jwq-dia-modal-pick-row') : null;
        if (modalPickRow) {
            applyPickedProductToRow(modalPickRow);
            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery('#jwqDiamondProductModal').modal('hide');
            }
            return;
        }
        var masterPickRow = e.target && e.target.closest ? e.target.closest('.jwq-dia-master-pick-row') : null;
        if (masterPickRow) {
            applyMasterValueToCell(masterPickRow.getAttribute('data-value') || '');
            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery('#jwqDiamondMasterModal').modal('hide');
            }
            return;
        }
        var prodCell = e.target && e.target.closest ? e.target.closest('#jwqDiamondTbody td.jwq-dia-product-cell') : null;
        if (prodCell && isAddNewMode) {
            e.preventDefault();
            openProductModalForCell(prodCell);
            return;
        }
        var masterCell = e.target && e.target.closest ? e.target.closest('#jwqDiamondTbody td.jwq-dia-master-cell') : null;
        if (masterCell && isAddNewMode) {
            e.preventDefault();
            openMasterModalForCell(masterCell, masterCell.getAttribute('data-master-field') || '');
            return;
        }
        var picker = document.getElementById('jwqDiamondProductPicker');
        if (picker && picker.style.display === 'block' && !picker.contains(e.target)) {
            hideProductPicker();
        }
        if (e.target && e.target.id === 'jwqDiamondColumnsBtn') {
            e.preventDefault();
            var panel = document.getElementById('jwqDiamondColumnsPanel');
            if (panel) {
                // Always rebuild list on click so options never appear empty.
                loadHiddenCols();
                renderColumnsPanel();
                applyDiamondColumnVisibility();
                panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
                if (panel.style.display === 'block') {
                    var cs = document.getElementById('jwqDiamondColumnsSearch');
                    if (cs) {
                        cs.value = '';
                        cs.focus();
                    }
                    document.querySelectorAll('#jwqDiamondColumnsList label[data-col-label]').forEach(function (lb) {
                        lb.style.display = '';
                    });
                }
            }
            return;
        }
        if (e.target && (e.target.id === 'jwqDiamondColumnsClose' || e.target.id === 'jwqDiamondColumnsMiniX')) {
            var closePanel = document.getElementById('jwqDiamondColumnsPanel');
            if (closePanel) closePanel.style.display = 'none';
            return;
        }
        var colCb = e.target && e.target.classList && e.target.classList.contains('jwq-dia-col-cb') ? e.target : null;
        if (colCb) {
            var col = colCb.getAttribute('data-col');
            if (!col) return;
            var idx = diamondHiddenCols.indexOf(col);
            if (colCb.checked) {
                if (idx >= 0) diamondHiddenCols.splice(idx, 1);
            } else {
                if (idx < 0) diamondHiddenCols.push(col);
            }
            saveHiddenCols();
            applyDiamondColumnVisibility();
            return;
        }
        var usedTbody = e.target && e.target.closest ? e.target.closest('#jwqDiamondUsedModalBody') : null;
        if (usedTbody && e.target.closest('.jwq-remove-used-diamond')) {
            if (typeof window.jwqRemoveUsedDiamondFromUi === 'function') {
                window.jwqRemoveUsedDiamondFromUi(e.target.closest('.jwq-remove-used-diamond'));
            }
            return;
        }
        if (usedTbody && e.target.closest('tr')) {
            var tru = e.target.closest('tr');
            var dum = document.getElementById('jwqDiamondUsedModal');
            if (!dum || !tru || !dum.contains(tru)) {
                return;
            }
            if (tru.getAttribute('data-jwq-editing') === '1') {
                return;
            }
            if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
                return;
            }
            var sidNum = parseInt(tru.getAttribute('data-jwq-used-stock-id') || '0', 10) || 0;
            if (sidNum < 1) {
                return;
            }
            e.preventDefault();
            var bc = String(tru.getAttribute('data-jwq-used-barcode') || '').trim();
            var wNum = parseFloat(tru.getAttribute('data-jwq-used-weight') || '0') || 0;
            var qNum = parseFloat(tru.getAttribute('data-jwq-used-qty') || '0') || 0;
            var pn = String(tru.getAttribute('data-jwq-used-product') || '').trim();
            if (!bc) {
                return;
            }
            if (qNum <= 0) {
                qNum = 1;
            }
            var appendFn = window.jwqAppendDiamondRowsToJobworkMaterialTable;
            if (typeof appendFn !== 'function') {
                return;
            }
            appendFn([{
                stock_id: sidNum,
                id: sidNum,
                barcode: bc,
                product_name: pn || bc,
                gross_weight: wNum,
                quantity: qNum,
                carat: '0',
                from_used_diamond_modal: true
            }]);
            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery('#jwqDiamondUsedModal').modal('hide');
            }
            return;
        }
        var panelNode = document.getElementById('jwqDiamondColumnsPanel');
        var panelBtn = document.getElementById('jwqDiamondColumnsBtn');
        if (panelNode && panelNode.style.display === 'block' && !panelNode.contains(e.target) && panelBtn && !panelBtn.contains(e.target)) {
            panelNode.style.display = 'none';
        }
    });

    var s = document.getElementById('jwqDiamondSearch');
    if (s) {
        s.addEventListener('input', function () {
            if (isAddNewMode && currentProductCell) {
                fetchDiamondProducts(s.value || '').then(function (rows) {
                    var pm = document.getElementById('jwqDiamondProductModal');
                    if (pm && pm.classList.contains('show')) {
                        renderProductModalList(rows);
                    } else {
                        renderProductPicker(rows, currentProductCell);
                    }
                });
            } else {
                filterRows();
            }
        });
    }
    var prodSearchInp = document.getElementById('jwqDiamondProductSearchInput');
    if (prodSearchInp) {
        var searchTmr = null;
        prodSearchInp.addEventListener('input', function () {
            clearTimeout(searchTmr);
            searchTmr = setTimeout(function () {
                fetchDiamondProducts(prodSearchInp.value || '').then(renderProductModalList);
            }, 220);
        });
    }
    var masterSearchInp = document.getElementById('jwqDiamondMasterSearchInput');
    if (masterSearchInp) {
        var masterSearchTmr = null;
        masterSearchInp.addEventListener('input', function () {
            clearTimeout(masterSearchTmr);
            masterSearchTmr = setTimeout(function () {
                fetchMasterOptions(currentMasterField, masterSearchInp.value || '').then(renderMasterModalList);
            }, 220);
        });
    }
    var all = document.getElementById('jwqDiamondCheckAll');
    if (all) {
        all.addEventListener('change', function () {
            var v = !!all.checked;
            Array.prototype.forEach.call(document.querySelectorAll('#jwqDiamondTbody .jwq-dia-row'), function (x) { x.checked = v; });
            all.indeterminate = false;
        });
    }
    var jwqDiamondTableEl = document.getElementById('jwqDiamondTable');
    if (jwqDiamondTableEl) {
        jwqDiamondTableEl.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('jwq-dia-row')) {
                jwqSyncDiamondCheckAllHeader();
            }
        });
    }
    var colSearch = document.getElementById('jwqDiamondColumnsSearch');
    if (colSearch) {
        colSearch.addEventListener('input', function () {
            var q = String(colSearch.value || '').toLowerCase().trim();
            document.querySelectorAll('#jwqDiamondColumnsList label[data-col-label]').forEach(function (lb) {
                var t = lb.getAttribute('data-col-label') || '';
                lb.style.display = (!q || t.indexOf(q) >= 0) ? '' : 'none';
            });
        });
    }
    /** Add New: Diamond Carat ↔ Weight — same rule as product-modal (1 ct = 0.2 g ⇒ weight g = carat / 5). */
    var jwqDiamondTbCaratWt = document.getElementById('jwqDiamondTbody');
    if (jwqDiamondTbCaratWt) {
        function syncJwqDiamondRowDataCaratWeight(tr) {
            if (!tr) return;
            if (!tr._rowData) tr._rowData = {};
            var wEl = tr.querySelector('td[data-col="weight"]');
            var cEl = tr.querySelector('td[data-col="diamond_carat"]');
            if (wEl) tr._rowData.gross_weight = parseFloat(String(wEl.textContent || '').replace(/,/g, '')) || 0;
            if (cEl) tr._rowData.carat = String(cEl.textContent || '').trim();
        }
        function jwqCaratToDiamondWeightG(carat) {
            var c = parseFloat(String(carat != null ? carat : '').replace(/,/g, '')) || 0;
            return c / 5;
        }
        function jwqDiamondWeightGToCarat(g) {
            var w = parseFloat(String(g != null ? g : '').replace(/,/g, '')) || 0;
            return w * 5;
        }
        jwqDiamondTbCaratWt.addEventListener('input', function (e) {
            if (!isAddNewMode) return;
            var td = e.target && e.target.closest ? e.target.closest('td[data-col]') : null;
            if (!td) return;
            var col = td.getAttribute('data-col');
            if (col !== 'diamond_carat' && col !== 'weight') return;
            var tr = td.closest('tr');
            if (!tr) return;
            if (col === 'diamond_carat') {
                var carat = parseFloat(String(td.textContent || '').replace(/,/g, '')) || 0;
                var wtTd = tr.querySelector('td[data-col="weight"]');
                if (wtTd) wtTd.textContent = num(jwqCaratToDiamondWeightG(carat), 3);
            } else {
                var wt = parseFloat(String(td.textContent || '').replace(/,/g, '')) || 0;
                var caratTd = tr.querySelector('td[data-col="diamond_carat"]');
                if (caratTd) caratTd.textContent = num(jwqDiamondWeightGToCarat(wt), 3);
            }
            syncJwqDiamondRowDataCaratWeight(tr);
        });
    }
    /**
     * This file is included before footer-script.php on manufacturing-process.php / jobwork-queue.php.
     * At parse time window.jQuery is undefined, so we must bind Bootstrap modal events after jQuery loads.
     */
    (function jwqBindDiamondModalWhenJqueryReady() {
        var jwqDiamondBootstrapBound = false;
        var jwqDiamondBindAttempts = 0;
        var jwqDiamondBindMax = 200;
        function jwqTryBindDiamondBootstrap() {
            if (jwqDiamondBootstrapBound) {
                return;
            }
            jwqDiamondBindAttempts += 1;
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.modal) {
                if (jwqDiamondBindAttempts < jwqDiamondBindMax) {
                    setTimeout(jwqTryBindDiamondBootstrap, 30);
                }
                return;
            }
            var $dm = window.jQuery('#jwqDiamondModal');
            if (!$dm.length) {
                if (jwqDiamondBindAttempts < jwqDiamondBindMax) {
                    setTimeout(jwqTryBindDiamondBootstrap, 30);
                }
                return;
            }
            jwqDiamondBootstrapBound = true;
            /* Load after open animation completes (stable layout). */
            $dm.off('shown.bs.modal.jwqDiamondStock').on('shown.bs.modal.jwqDiamondStock', function () {
                loadHiddenCols();
                renderColumnsPanel();
                applyDiamondColumnVisibility();
                var q = document.getElementById('jwqDiamondSearch');
                if (q) q.value = '';
                switchTab(false);
            });
            $dm.off('hide.bs.modal.jwqDiamondStock').on('hide.bs.modal.jwqDiamondStock', function () {
                hideProductPicker();
                var panel = document.getElementById('jwqDiamondColumnsPanel');
                if (panel) panel.style.display = 'none';
            });
            var $dpm = window.jQuery('#jwqDiamondProductModal');
            if ($dpm.length) {
                $dpm.off('hidden.bs.modal.jwqDiamondAux').on('hidden.bs.modal.jwqDiamondAux', function () {
                    var list = document.getElementById('jwqDiamondProductModalList');
                    if (list) list.innerHTML = '';
                });
            }
            var $dmm = window.jQuery('#jwqDiamondMasterModal');
            if ($dmm.length) {
                $dmm.off('hidden.bs.modal.jwqDiamondAux').on('hidden.bs.modal.jwqDiamondAux', function () {
                    var list = document.getElementById('jwqDiamondMasterModalList');
                    if (list) list.innerHTML = '';
                    currentMasterCell = null;
                    currentMasterField = '';
                });
            }
        }
        jwqTryBindDiamondBootstrap();
        window.addEventListener('load', jwqTryBindDiamondBootstrap);
    })();
    window.jwqAppendDiamondRowsToJobworkMaterialTable = jwqAppendDiamondRowsToJobworkMaterialTable;
    window.jwqRefreshJobworkMaterialTotalWt = jwqRefreshJobworkMaterialTotalWt;
})();
</script>

<script>
(function () {
    function meEsc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function meNum(v, d) {
        var n = parseFloat(v);
        if (isNaN(n)) {
            return (0).toFixed(d || 3);
        }
        return n.toFixed(d || 3);
    }

    window.jwqClearMetalExchangeModalForm = function () {
        var ids = ['metalExchangeMetal', 'metalExchangeProductInput', 'metalExchangeProductId', 'metalExchangeGrossWt', 'metalExchangePurityWt', 'metalExchangePurity', 'metalExchangeQty', 'metalExchangeRate', 'metalExchangeAmount', 'metalExchangeItemCode'];
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) {
                return;
            }
            if (id === 'metalExchangeMetal') {
                el.value = '';
            } else if (id === 'metalExchangePurity') {
                el.value = '1';
            } else if (id === 'metalExchangeQty') {
                el.value = '1';
            } else if (id === 'metalExchangeAmount') {
                el.value = '0.00';
            } else {
                el.value = (id === 'metalExchangeGrossWt' || id === 'metalExchangePurityWt' || id === 'metalExchangeRate') ? '0' : '';
            }
        });
        var listEl = document.getElementById('metalExchangeProductList');
        if (listEl) {
            listEl.style.display = 'none';
            listEl.innerHTML = '';
        }
    };

    window.jwqSumMaterialMetalWtByAdjust = function (direction) {
        var body = typeof jwqGetJobworkMaterialBody === 'function' ? jwqGetJobworkMaterialBody() : document.getElementById('jwqMaterialBody');
        if (!body) {
            return 0;
        }
        var sum = 0;
        body.querySelectorAll('tr.jwq-material-metal-row').forEach(function (tr) {
            if ((tr.getAttribute('data-me-adjust') || '') !== direction) {
                return;
            }
            sum += parseFloat(tr.getAttribute('data-weight') || '0') || 0;
        });
        return sum;
    };

    window.jwqSumMaterialMetalReduceWt = function () {
        return window.jwqSumMaterialMetalWtByAdjust('reduce');
    };

    window.jwqSumMaterialMetalAddWt = function () {
        return window.jwqSumMaterialMetalWtByAdjust('add');
    };

    window.jwqInitReduceWeightOrigTotals = function () {
        if (typeof window.jwqSnapshotReduceWeightOrigTotals === 'function') {
            window.jwqSnapshotReduceWeightOrigTotals(false);
        }
    };

    window.jwqResolveReduceWeightOrigTotal = function (tr) {
        if (!tr) {
            return NaN;
        }
        var v = parseFloat(tr.getAttribute('data-jwq-orig-total-before-reduce') || '');
        if (isFinite(v)) {
            return v;
        }
        v = parseFloat(tr.getAttribute('data-orig-total-wt') || '');
        return isFinite(v) ? v : NaN;
    };

    window.jwqResolveReduceWeightOrigMetal = function (tr) {
        if (!tr) {
            return NaN;
        }
        var v = parseFloat(tr.getAttribute('data-jwq-orig-metal-before-reduce') || '');
        if (isFinite(v)) {
            return v;
        }
        v = parseFloat(tr.getAttribute('data-jwq-orig-metal-wt') || '');
        return isFinite(v) ? v : NaN;
    };

    window.jwqResolveReduceWeightOrigDiamond = function (tr) {
        if (!tr) {
            return NaN;
        }
        var v = parseFloat(tr.getAttribute('data-jwq-orig-diamond-before-reduce') || '');
        if (isFinite(v)) {
            return v;
        }
        v = parseFloat(tr.getAttribute('data-jwq-orig-diamond-wt') || '');
        return isFinite(v) ? v : NaN;
    };

    window.jwqSnapshotReduceWeightOrigTotals = function (force) {
        if (typeof window.jwqIsReduceWeightMode !== 'function' || !window.jwqIsReduceWeightMode()) {
            return;
        }
        document.querySelectorAll('#jwqOrderLinesBody tr[data-item-id]').forEach(function (tr) {
            if (!force && tr.getAttribute('data-jwq-reduce-orig-snapshotted') === '1') {
                return;
            }
            var origM = parseFloat(tr.getAttribute('data-jwq-orig-metal-wt') || '');
            var origT = parseFloat(tr.getAttribute('data-orig-total-wt') || '');
            var origD = parseFloat(tr.getAttribute('data-jwq-orig-diamond-wt') || '');
            var extraUi = typeof window.jwqIsReduceWeightExtraMetalUi === 'function' && window.jwqIsReduceWeightExtraMetalUi();
            if ((!isFinite(origM) || !isFinite(origT)) && typeof jwqLineFieldNum === 'function') {
                if (!isFinite(origM) && !extraUi) {
                    origM = jwqLineFieldNum(tr, 'metal_wt');
                }
                if (!isFinite(origT) && !extraUi) {
                    origT = jwqLineFieldNum(tr, 'total_wt');
                }
            }
            if ((!isFinite(origD) || origD < 0) && typeof jwqLineFieldNum === 'function' && !extraUi) {
                origD = jwqLineFieldNum(tr, 'diamond_wt');
            }
            if (!isFinite(origD) || origD < 0) {
                if (isFinite(origT) && isFinite(origM) && origT > origM + 0.0000001) {
                    origD = origT - origM;
                } else {
                    origD = 0;
                }
            }
            if (isFinite(origT)) {
                tr.setAttribute('data-jwq-orig-total-before-reduce', String(origT));
            }
            if (isFinite(origM)) {
                tr.setAttribute('data-jwq-orig-metal-before-reduce', String(origM));
            }
            if (isFinite(origD)) {
                tr.setAttribute('data-jwq-orig-diamond-before-reduce', String(origD));
            }
            tr.setAttribute('data-jwq-reduce-orig-snapshotted', '1');
        });
    };

    window.jwqInitReduceWeightExtraMetalUi = function () {
        if (typeof window.jwqIsReduceWeightExtraMetalUi !== 'function' || !window.jwqIsReduceWeightExtraMetalUi()) {
            return;
        }
        if (typeof jwqLineFieldEl !== 'function') {
            return;
        }
        var fmt = typeof jwqNum3 === 'function' ? jwqNum3 : meNum;
        document.querySelectorAll('#jwqOrderLinesBody tr[data-item-id]').forEach(function (tr) {
            tr.removeAttribute('data-jwq-freeze-weights');
            tr.removeAttribute('data-jwq-total-manual');
            tr.removeAttribute('data-jwq-manual-reduce-grams');
            var mInp = jwqLineFieldEl(tr, 'metal_wt');
            var tInp = jwqLineFieldEl(tr, 'total_wt');
            var dInp = jwqLineFieldEl(tr, 'diamond_wt');
            if (mInp) {
                mInp.value = fmt(0);
            }
            if (tInp) {
                tInp.value = fmt(0);
            }
            if (dInp) {
                dInp.value = fmt(0);
            }
        });
    };

    window.jwqReadManualReduceDeltaGrams = function (tr, origM, sumMe, curMOptional) {
        if (!tr) {
            return 0;
        }
        if (!isFinite(sumMe)) {
            sumMe = typeof window.jwqSumMaterialMetalReduceWt === 'function' ? window.jwqSumMaterialMetalReduceWt() : 0;
        }
        var curM = curMOptional;
        if (!isFinite(curM) && typeof jwqLineFieldNum === 'function') {
            curM = jwqLineFieldNum(tr, 'metal_wt');
        }
        if (typeof window.jwqIsReduceWeightExtraMetalUi === 'function' && window.jwqIsReduceWeightExtraMetalUi()) {
            var storedReduce = parseFloat(tr.getAttribute('data-jwq-manual-reduce-grams') || '');
            if (isFinite(storedReduce) && storedReduce > 0.0001) {
                return storedReduce;
            }
            if (isFinite(curM) && curM > 0.0001) {
                return curM;
            }
            return 0;
        }
        if (!isFinite(origM)) {
            origM = parseFloat(tr.getAttribute('data-jwq-orig-metal-before-reduce') || '');
        }
        if (!isFinite(origM)) {
            origM = parseFloat(tr.getAttribute('data-jwq-orig-metal-wt') || '');
        }
        if (!isFinite(origM)) {
            origM = 0;
        }
        if (!isFinite(curM)) {
            var stored = parseFloat(tr.getAttribute('data-jwq-manual-reduce-grams') || '');
            return isFinite(stored) && stored > 0 ? stored : 0;
        }
        if (curM < origM - sumMe - 0.0001) {
            return origM - sumMe - curM;
        }
        if (sumMe < 0.0001 && curM < origM - 0.0001) {
            return origM - curM;
        }
        return 0;
    };

    window.jwqApplyReduceWeightManualMetalPreview = function (tr) {
        if (!tr || typeof window.jwqIsReduceWeightMode !== 'function' || !window.jwqIsReduceWeightMode()) {
            return;
        }
        if (typeof jwqLineFieldEl !== 'function' || typeof jwqLineFieldNum !== 'function') {
            return;
        }
        if (tr.getAttribute('data-jwq-reduce-orig-snapshotted') !== '1') {
            window.jwqSnapshotReduceWeightOrigTotals(false);
        }
        var origT = typeof window.jwqResolveReduceWeightOrigTotal === 'function'
            ? window.jwqResolveReduceWeightOrigTotal(tr)
            : NaN;
        var origM = typeof window.jwqResolveReduceWeightOrigMetal === 'function'
            ? window.jwqResolveReduceWeightOrigMetal(tr)
            : NaN;
        if (!isFinite(origT)) {
            origT = isFinite(origM) ? origM : 0;
        }
        if (!isFinite(origM)) {
            origM = origT;
        }
        var sumMe = typeof window.jwqSumMaterialMetalReduceWt === 'function' ? window.jwqSumMaterialMetalReduceWt() : 0;
        var curM = jwqLineFieldNum(tr, 'metal_wt');
        var extraUi = typeof window.jwqIsReduceWeightExtraMetalUi === 'function' && window.jwqIsReduceWeightExtraMetalUi();
        var manualDelta = typeof window.jwqReadManualReduceDeltaGrams === 'function'
            ? window.jwqReadManualReduceDeltaGrams(tr, origM, sumMe, curM)
            : 0;
        tr.setAttribute('data-jwq-manual-reduce-grams', String(manualDelta));
        var fmt = typeof jwqNum3 === 'function' ? jwqNum3 : meNum;
        var reduceTotal = sumMe + manualDelta;
        var newT = Math.max(0, origT - reduceTotal);
        var newM = Math.max(0, (isFinite(origM) ? origM : 0) - reduceTotal);
        var tInp = jwqLineFieldEl(tr, 'total_wt');
        var mInp = jwqLineFieldEl(tr, 'metal_wt');
        var dInp = jwqLineFieldEl(tr, 'diamond_wt');
        if (extraUi) {
            if (tInp) {
                tInp.value = fmt(reduceTotal > 0.0001 ? newT : 0);
            }
            if (dInp) {
                dInp.value = fmt(0);
            }
            if (reduceTotal > 0.0001) {
                tr.setAttribute('data-jwq-total-manual', '1');
            } else {
                tr.removeAttribute('data-jwq-total-manual');
            }
            return;
        }
        if (tInp) {
            tInp.value = fmt(newT);
        }
        if (mInp && manualDelta > 0.0001) {
            mInp.value = fmt(newM);
        }
        if (manualDelta > 0.0001 || sumMe > 0.0001) {
            tr.setAttribute('data-jwq-total-manual', '1');
        }
    };

    window.jwqComputeManualReduceWeightGrams = function () {
        if (typeof window.jwqIsReduceWeightMode !== 'function' || !window.jwqIsReduceWeightMode()) {
            return 0;
        }
        var tr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
        if (!tr) {
            return 0;
        }
        window.jwqInitReduceWeightOrigTotals();
        var stored = parseFloat(tr.getAttribute('data-jwq-manual-reduce-grams') || '');
        var extraUi = typeof window.jwqIsReduceWeightExtraMetalUi === 'function' && window.jwqIsReduceWeightExtraMetalUi();
        if (extraUi && isFinite(stored) && stored > 0.0001) {
            return stored;
        }
        var origM = typeof window.jwqResolveReduceWeightOrigMetal === 'function'
            ? window.jwqResolveReduceWeightOrigMetal(tr)
            : NaN;
        var sumMe = typeof window.jwqSumMaterialMetalReduceWt === 'function' ? window.jwqSumMaterialMetalReduceWt() : 0;
        var curM = typeof jwqLineFieldNum === 'function' ? jwqLineFieldNum(tr, 'metal_wt') : NaN;
        var computed = typeof window.jwqReadManualReduceDeltaGrams === 'function'
            ? window.jwqReadManualReduceDeltaGrams(tr, origM, sumMe, curM)
            : 0;
        if (computed > 0.0001) {
            return computed;
        }
        if (isFinite(stored) && stored > 0.0001) {
            return stored;
        }
        return 0;
    };

    window.jwqFinalizeReduceWeightLineTotalsBeforeSave = function () {
        if (typeof window.jwqIsReduceWeightMode !== 'function' || !window.jwqIsReduceWeightMode()) {
            return;
        }
        var tr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
        if (!tr || typeof jwqLineFieldEl !== 'function' || typeof jwqLineFieldNum !== 'function') {
            return;
        }
        window.jwqInitReduceWeightOrigTotals();
        var origT = typeof window.jwqResolveReduceWeightOrigTotal === 'function'
            ? window.jwqResolveReduceWeightOrigTotal(tr)
            : NaN;
        var origM = typeof window.jwqResolveReduceWeightOrigMetal === 'function'
            ? window.jwqResolveReduceWeightOrigMetal(tr)
            : NaN;
        if (!isFinite(origT) || !isFinite(origM)) {
            return;
        }
        var sumMe = typeof window.jwqSumMaterialMetalReduceWt === 'function' ? window.jwqSumMaterialMetalReduceWt() : 0;
        var curM = jwqLineFieldNum(tr, 'metal_wt');
        var manual = typeof window.jwqReadManualReduceDeltaGrams === 'function'
            ? window.jwqReadManualReduceDeltaGrams(tr, origM, sumMe, curM)
            : 0;
        tr.setAttribute('data-jwq-manual-reduce-grams', String(manual));
        var reduceTotal = sumMe + manual;
        if (reduceTotal <= 0.0000001 && manual <= 0.0000001) {
            return;
        }
        var fmt = typeof jwqNum3 === 'function' ? jwqNum3 : meNum;
        var newT = Math.max(0, (isFinite(origT) ? origT : 0) - reduceTotal);
        var newM = Math.max(0, (isFinite(origM) ? origM : 0) - reduceTotal);
        var origD = typeof window.jwqResolveReduceWeightOrigDiamond === 'function'
            ? window.jwqResolveReduceWeightOrigDiamond(tr)
            : 0;
        if (!isFinite(origD) || origD < 0) {
            origD = 0;
        }
        var tInp = jwqLineFieldEl(tr, 'total_wt');
        var mInp = jwqLineFieldEl(tr, 'metal_wt');
        var dInp = jwqLineFieldEl(tr, 'diamond_wt');
        if (tInp) {
            tInp.value = fmt(newT);
        }
        if (mInp) {
            mInp.value = fmt(newM);
        }
        if (dInp) {
            dInp.value = fmt(origD);
        }
    };

    window.jwqSyncOrderLineTotalWtFromMetalReduceTable = function () {
        if (typeof window.jwqIsReduceWeightMode !== 'function' || !window.jwqIsReduceWeightMode()) {
            return;
        }
        if (typeof jwqLineFieldNum !== 'function' || typeof jwqLineFieldEl !== 'function') {
            return;
        }
        window.jwqInitReduceWeightOrigTotals();
        var sumReduce = window.jwqSumMaterialMetalReduceWt();
        var tbody = document.getElementById('jwqOrderLinesBody');
        if (!tbody) {
            return;
        }
        var first = true;
        tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
            if (!first) {
                return;
            }
            first = false;
            if (typeof window.jwqApplyReduceWeightManualMetalPreview === 'function') {
                window.jwqApplyReduceWeightManualMetalPreview(tr);
                return;
            }
            var origT = typeof window.jwqResolveReduceWeightOrigTotal === 'function'
                ? window.jwqResolveReduceWeightOrigTotal(tr)
                : NaN;
            if (!isFinite(origT)) {
                return;
            }
            var origM = typeof window.jwqResolveReduceWeightOrigMetal === 'function'
                ? window.jwqResolveReduceWeightOrigMetal(tr)
                : NaN;
            var curM = jwqLineFieldNum(tr, 'metal_wt');
            var manualDelta = typeof window.jwqReadManualReduceDeltaGrams === 'function'
                ? window.jwqReadManualReduceDeltaGrams(tr, origM, sumReduce, curM)
                : 0;
            tr.setAttribute('data-jwq-manual-reduce-grams', String(manualDelta));
            var reduceTotal = sumReduce + manualDelta;
            var newT = Math.max(0, origT - reduceTotal);
            var newM = Math.max(0, (isFinite(origM) ? origM : 0) - reduceTotal);
            var tInp = jwqLineFieldEl(tr, 'total_wt');
            var mInp = jwqLineFieldEl(tr, 'metal_wt');
            var fmt = typeof jwqNum3 === 'function' ? jwqNum3 : meNum;
            var extraUi = typeof window.jwqIsReduceWeightExtraMetalUi === 'function' && window.jwqIsReduceWeightExtraMetalUi();
            if (tInp) {
                tInp.value = fmt(extraUi && reduceTotal <= 0.0001 ? 0 : newT);
            }
            if (mInp && !extraUi && (!isFinite(curM) || Math.abs(curM - newM) > 0.0001)) {
                mInp.value = fmt(newM);
            }
            if (extraUi) {
                var dInp = jwqLineFieldEl(tr, 'diamond_wt');
                if (dInp) {
                    dInp.value = fmt(0);
                }
            }
            if (typeof jwqReconcileLineLossFromWeights === 'function') {
                jwqReconcileLineLossFromWeights(tr);
            }
        });
        if (typeof jwqRefreshAutoLossAllRows === 'function') {
            jwqRefreshAutoLossAllRows();
        }
    };

    window.jwqResolveAddWeightOrigMetal = function (tr) {
        if (!tr) {
            return NaN;
        }
        var v = parseFloat(tr.getAttribute('data-jwq-orig-metal-before-add') || '');
        if (isFinite(v)) {
            return v;
        }
        v = parseFloat(tr.getAttribute('data-jwq-orig-metal-wt') || '');
        return isFinite(v) ? v : NaN;
    };

    window.jwqResolveAddWeightOrigTotal = function (tr) {
        if (!tr) {
            return NaN;
        }
        var v = parseFloat(tr.getAttribute('data-jwq-orig-total-before-add') || '');
        if (isFinite(v)) {
            return v;
        }
        v = parseFloat(tr.getAttribute('data-orig-total-wt') || '');
        return isFinite(v) ? v : NaN;
    };

    window.jwqResolveAddWeightOrigDiamond = function (tr) {
        if (!tr) {
            return NaN;
        }
        var v = parseFloat(tr.getAttribute('data-jwq-orig-diamond-before-add') || '');
        if (isFinite(v)) {
            return v;
        }
        v = parseFloat(tr.getAttribute('data-jwq-orig-diamond-wt') || '');
        return isFinite(v) ? v : NaN;
    };

    /** Capture opening line weights once when modal opens — never from an edited Metal Wt field. */
    window.jwqSnapshotAddWeightOrigTotals = function (force) {
        if (typeof window.jwqIsAddWeightMode !== 'function' || !window.jwqIsAddWeightMode()) {
            return;
        }
        document.querySelectorAll('#jwqOrderLinesBody tr[data-item-id]').forEach(function (tr) {
            if (!force && tr.getAttribute('data-jwq-add-orig-snapshotted') === '1') {
                return;
            }
            var origM = parseFloat(tr.getAttribute('data-jwq-orig-metal-wt') || '');
            var origT = parseFloat(tr.getAttribute('data-orig-total-wt') || '');
            var origD = parseFloat(tr.getAttribute('data-jwq-orig-diamond-wt') || '');
            if ((!isFinite(origM) || !isFinite(origT)) && typeof jwqLineFieldNum === 'function') {
                var extraUi = typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi();
                if (!isFinite(origM) && !extraUi) {
                    origM = jwqLineFieldNum(tr, 'metal_wt');
                }
                if (!isFinite(origT) && !extraUi) {
                    origT = jwqLineFieldNum(tr, 'total_wt');
                }
            }
            if ((!isFinite(origD) || origD < 0) && typeof jwqLineFieldNum === 'function') {
                var extraUiD = typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi();
                if (!extraUiD) {
                    origD = jwqLineFieldNum(tr, 'diamond_wt');
                }
            }
            if (!isFinite(origD) || origD < 0) {
                if (isFinite(origT) && isFinite(origM) && origT > origM + 0.0000001) {
                    origD = origT - origM;
                } else {
                    origD = 0;
                }
            }
            if (isFinite(origT)) {
                tr.setAttribute('data-jwq-orig-total-before-add', String(origT));
            }
            if (isFinite(origM)) {
                tr.setAttribute('data-jwq-orig-metal-before-add', String(origM));
            }
            if (isFinite(origD)) {
                tr.setAttribute('data-jwq-orig-diamond-before-add', String(origD));
            }
            tr.setAttribute('data-jwq-add-orig-snapshotted', '1');
        });
    };

    window.jwqInitAddWeightOrigTotals = function () {
        window.jwqSnapshotAddWeightOrigTotals(false);
    };

    window.jwqInitAddWeightExtraMetalUi = function () {
        if (typeof window.jwqIsAddWeightExtraMetalUi !== 'function' || !window.jwqIsAddWeightExtraMetalUi()) {
            return;
        }
        if (typeof jwqLineFieldEl !== 'function') {
            return;
        }
        var fmt = typeof jwqNum3 === 'function' ? jwqNum3 : meNum;
        document.querySelectorAll('#jwqOrderLinesBody tr[data-item-id]').forEach(function (tr) {
            tr.removeAttribute('data-jwq-freeze-weights');
            tr.removeAttribute('data-jwq-total-manual');
            tr.removeAttribute('data-jwq-manual-add-grams');
            var mInp = jwqLineFieldEl(tr, 'metal_wt');
            var tInp = jwqLineFieldEl(tr, 'total_wt');
            var dInp = jwqLineFieldEl(tr, 'diamond_wt');
            if (mInp) {
                mInp.value = fmt(0);
            }
            if (tInp) {
                tInp.value = fmt(0);
            }
            if (dInp) {
                dInp.value = fmt(0);
            }
        });
    };

    window.jwqSyncOrderLineTotalWtFromMetalAddTable = function () {
        if (typeof window.jwqIsAddWeightMode !== 'function' || !window.jwqIsAddWeightMode()) {
            return;
        }
        if (typeof jwqLineFieldNum !== 'function' || typeof jwqLineFieldEl !== 'function') {
            return;
        }
        window.jwqInitAddWeightOrigTotals();
        var sumAdd = window.jwqSumMaterialMetalAddWt();
        var tbody = document.getElementById('jwqOrderLinesBody');
        if (!tbody) {
            return;
        }
        var first = true;
        tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
            if (!first) {
                return;
            }
            first = false;
            var origT = typeof window.jwqResolveAddWeightOrigTotal === 'function'
                ? window.jwqResolveAddWeightOrigTotal(tr)
                : NaN;
            if (!isFinite(origT)) {
                return;
            }
            var origM = typeof window.jwqResolveAddWeightOrigMetal === 'function'
                ? window.jwqResolveAddWeightOrigMetal(tr)
                : NaN;
            var curM = jwqLineFieldNum(tr, 'metal_wt');
            var manualDelta = typeof window.jwqReadManualAddDeltaGrams === 'function'
                ? window.jwqReadManualAddDeltaGrams(tr, origM, sumAdd, curM)
                : 0;
            tr.setAttribute('data-jwq-manual-add-grams', String(manualDelta));
            var addTotal = sumAdd + manualDelta;
            var newT = origT + addTotal;
            var newM = (isFinite(origM) ? origM : 0) + addTotal;
            var tInp = jwqLineFieldEl(tr, 'total_wt');
            var mInp = jwqLineFieldEl(tr, 'metal_wt');
            var fmt = typeof jwqNum3 === 'function' ? jwqNum3 : meNum;
            var extraUi = typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi();
            if (tInp) {
                tInp.value = fmt(extraUi && addTotal <= 0.0001 ? 0 : newT);
            }
            if (mInp && !extraUi && (!isFinite(curM) || Math.abs(curM - newM) > 0.0001)) {
                mInp.value = fmt(newM);
            }
            if (extraUi) {
                var dInp = jwqLineFieldEl(tr, 'diamond_wt');
                if (dInp) {
                    dInp.value = fmt(0);
                }
            }
            if (typeof jwqReconcileLineLossFromWeights === 'function') {
                jwqReconcileLineLossFromWeights(tr);
            }
        });
        if (typeof jwqRefreshAutoLossAllRows === 'function') {
            jwqRefreshAutoLossAllRows();
        }
    };

    /** Manual add grams: extra-only UI uses Metal Wt as delta; legacy UI uses metal above opening weight. */
    window.jwqReadManualAddDeltaGrams = function (tr, origM, sumMe, curMOptional) {
        if (!tr) {
            return 0;
        }
        if (!isFinite(sumMe)) {
            sumMe = typeof window.jwqSumMaterialMetalAddWt === 'function' ? window.jwqSumMaterialMetalAddWt() : 0;
        }
        var curM = curMOptional;
        if (!isFinite(curM) && typeof jwqLineFieldNum === 'function') {
            curM = jwqLineFieldNum(tr, 'metal_wt');
        }
        if (typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi()) {
            var storedExtra = parseFloat(tr.getAttribute('data-jwq-manual-add-grams') || '');
            if (isFinite(storedExtra) && storedExtra > 0.0001) {
                return storedExtra;
            }
            if (!isFinite(origM)) {
                origM = parseFloat(tr.getAttribute('data-jwq-orig-metal-before-add') || '');
            }
            if (!isFinite(origM)) {
                origM = parseFloat(tr.getAttribute('data-jwq-orig-metal-wt') || '');
            }
            if (!isFinite(origM)) {
                origM = 0;
            }
            if (isFinite(curM) && curM > 0.0001) {
                if (curM > origM + sumMe + 0.0001) {
                    return curM - origM - sumMe;
                }
                return curM;
            }
            return 0;
        }
        if (!isFinite(origM)) {
            origM = parseFloat(tr.getAttribute('data-jwq-orig-metal-before-add') || '');
        }
        if (!isFinite(origM)) {
            origM = parseFloat(tr.getAttribute('data-jwq-orig-metal-wt') || '');
        }
        if (!isFinite(origM)) {
            origM = 0;
        }
        if (!isFinite(curM)) {
            var stored = parseFloat(tr.getAttribute('data-jwq-manual-add-grams') || '');
            return isFinite(stored) && stored > 0 ? stored : 0;
        }
        var expected = origM + sumMe;
        if (curM > expected + 0.0001) {
            return curM - expected;
        }
        if (sumMe < 0.0001 && curM > origM + 0.0001) {
            return curM - origM;
        }
        return 0;
    };

    window.jwqApplyAddWeightManualMetalPreview = function (tr) {
        if (!tr || typeof window.jwqIsAddWeightMode !== 'function' || !window.jwqIsAddWeightMode()) {
            return;
        }
        if (typeof jwqLineFieldEl !== 'function' || typeof jwqLineFieldNum !== 'function') {
            return;
        }
        if (tr.getAttribute('data-jwq-add-orig-snapshotted') !== '1') {
            window.jwqSnapshotAddWeightOrigTotals(false);
        }
        var origT = typeof window.jwqResolveAddWeightOrigTotal === 'function'
            ? window.jwqResolveAddWeightOrigTotal(tr)
            : NaN;
        var origM = typeof window.jwqResolveAddWeightOrigMetal === 'function'
            ? window.jwqResolveAddWeightOrigMetal(tr)
            : NaN;
        if (!isFinite(origT)) {
            origT = isFinite(origM) ? origM : 0;
        }
        if (!isFinite(origM)) {
            origM = origT;
        }
        var sumMe = typeof window.jwqSumMaterialMetalAddWt === 'function' ? window.jwqSumMaterialMetalAddWt() : 0;
        var curM = jwqLineFieldNum(tr, 'metal_wt');
        var curT = jwqLineFieldNum(tr, 'total_wt');
        if (!isFinite(curM) && !isFinite(curT)) {
            return;
        }
        var extraUi = typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi();
        var manualDelta = typeof window.jwqReadManualAddDeltaGrams === 'function'
            ? window.jwqReadManualAddDeltaGrams(tr, origM, sumMe, curM)
            : 0;
        if (!extraUi && isFinite(curT) && curT > origT + sumMe + 0.0001) {
            var deltaFromTotal = curT - origT - sumMe;
            if (deltaFromTotal > manualDelta + 0.0001) {
                manualDelta = deltaFromTotal;
            }
        }
        tr.setAttribute('data-jwq-manual-add-grams', String(manualDelta));
        var fmt = typeof jwqNum3 === 'function' ? jwqNum3 : meNum;
        var addTotal = sumMe + manualDelta;
        var newT = origT + addTotal;
        var newM = origM + addTotal;
        var tInp = jwqLineFieldEl(tr, 'total_wt');
        var mInp = jwqLineFieldEl(tr, 'metal_wt');
        var dInp = jwqLineFieldEl(tr, 'diamond_wt');
        if (extraUi) {
            if (tInp) {
                tInp.value = fmt(addTotal > 0.0001 ? newT : 0);
            }
            if (dInp) {
                dInp.value = fmt(0);
            }
            if (addTotal > 0.0001) {
                tr.setAttribute('data-jwq-total-manual', '1');
            } else {
                tr.removeAttribute('data-jwq-total-manual');
            }
            return;
        }
        if (tInp) {
            tInp.value = fmt(newT);
        }
        if (mInp && manualDelta > 0.0001) {
            mInp.value = fmt(newM);
        }
        if (manualDelta > 0.0001 || sumMe > 0.0001) {
            tr.setAttribute('data-jwq-total-manual', '1');
        }
    };

    window.jwqRefreshAddWeightLinePreviews = function () {
        if (typeof window.jwqIsAddWeightMode !== 'function' || !window.jwqIsAddWeightMode()) {
            return;
        }
        if (typeof window.jwqInitAddWeightOrigTotals === 'function') {
            window.jwqInitAddWeightOrigTotals();
        }
        document.querySelectorAll('#jwqOrderLinesBody tr[data-item-id]').forEach(function (tr) {
            if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                window.jwqApplyAddWeightManualMetalPreview(tr);
            }
        });
        if (typeof window.jwqSyncOrderLineTotalWtFromMetalAddTable === 'function') {
            window.jwqSyncOrderLineTotalWtFromMetalAddTable();
        }
    };

    window.jwqComputeManualAddWeightGrams = function () {
        if (typeof window.jwqIsAddWeightMode !== 'function' || !window.jwqIsAddWeightMode()) {
            return 0;
        }
        var tr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
        if (!tr) {
            return 0;
        }
        window.jwqInitAddWeightOrigTotals();
        var stored = parseFloat(tr.getAttribute('data-jwq-manual-add-grams') || '');
        var extraUi = typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi();
        if (extraUi && isFinite(stored) && stored > 0.0001) {
            return stored;
        }
        var origM = typeof window.jwqResolveAddWeightOrigMetal === 'function'
            ? window.jwqResolveAddWeightOrigMetal(tr)
            : NaN;
        var sumMe = typeof window.jwqSumMaterialMetalAddWt === 'function' ? window.jwqSumMaterialMetalAddWt() : 0;
        var curM = typeof jwqLineFieldNum === 'function' ? jwqLineFieldNum(tr, 'metal_wt') : NaN;
        var computed = typeof window.jwqReadManualAddDeltaGrams === 'function'
            ? window.jwqReadManualAddDeltaGrams(tr, origM, sumMe, curM)
            : 0;
        if (computed > 0.0001) {
            return computed;
        }
        if (isFinite(stored) && stored > 0.0001) {
            return stored;
        }
        return 0;
    };

    window.jwqFinalizeAddWeightLineTotalsBeforeSave = function () {
        if (typeof window.jwqIsAddWeightMode !== 'function' || !window.jwqIsAddWeightMode()) {
            return;
        }
        var tr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
        if (!tr || typeof jwqLineFieldEl !== 'function' || typeof jwqLineFieldNum !== 'function') {
            return;
        }
        window.jwqInitAddWeightOrigTotals();
        var origT = typeof window.jwqResolveAddWeightOrigTotal === 'function'
            ? window.jwqResolveAddWeightOrigTotal(tr)
            : NaN;
        var origM = typeof window.jwqResolveAddWeightOrigMetal === 'function'
            ? window.jwqResolveAddWeightOrigMetal(tr)
            : NaN;
        if (!isFinite(origT) || !isFinite(origM)) {
            return;
        }
        var sumMe = typeof window.jwqSumMaterialMetalAddWt === 'function' ? window.jwqSumMaterialMetalAddWt() : 0;
        var curM = jwqLineFieldNum(tr, 'metal_wt');
        var manual = typeof window.jwqReadManualAddDeltaGrams === 'function'
            ? window.jwqReadManualAddDeltaGrams(tr, origM, sumMe, curM)
            : 0;
        tr.setAttribute('data-jwq-manual-add-grams', String(manual));
        var addTotal = sumMe + manual;
        if (addTotal <= 0.0000001 && manual <= 0.0000001) {
            return;
        }
        var fmt = typeof jwqNum3 === 'function' ? jwqNum3 : meNum;
        var newT = (isFinite(origT) ? origT : 0) + addTotal;
        var newM = (isFinite(origM) ? origM : 0) + addTotal;
        var origD = typeof window.jwqResolveAddWeightOrigDiamond === 'function'
            ? window.jwqResolveAddWeightOrigDiamond(tr)
            : 0;
        if (!isFinite(origD) || origD < 0) {
            origD = 0;
        }
        var tInp = jwqLineFieldEl(tr, 'total_wt');
        var mInp = jwqLineFieldEl(tr, 'metal_wt');
        var dInp = jwqLineFieldEl(tr, 'diamond_wt');
        if (tInp) {
            tInp.value = fmt(newT);
        }
        if (mInp) {
            mInp.value = fmt(newM);
        }
        if (dInp) {
            dInp.value = fmt(origD);
        }
        if (typeof jwqReconcileLineLossFromWeights === 'function') {
            jwqReconcileLineLossFromWeights(tr);
        }
    };

    window.jwqCollectMaterialMetalReduceForSave = function () {
        return window.jwqCollectMaterialMetalExchangeForSave('reduce');
    };

    window.jwqCollectMaterialMetalAddForSave = function () {
        return window.jwqCollectMaterialMetalExchangeForSave('add');
    };

    window.jwqCollectMaterialMetalExchangeForSave = function (direction) {
        var out = [];
        var body = typeof jwqGetJobworkMaterialBody === 'function' ? jwqGetJobworkMaterialBody() : document.getElementById('jwqMaterialBody');
        if (!body) {
            return out;
        }
        body.querySelectorAll('tr.jwq-material-metal-row').forEach(function (row) {
            if ((row.getAttribute('data-me-adjust') || '') !== direction) {
                return;
            }
            var raw = row.getAttribute('data-me-payload');
            if (!raw) {
                return;
            }
            try {
                var p = JSON.parse(raw);
                if (p && p.metal_exchange_metal_id && p.metal_exchange_product_id) {
                    out.push(p);
                }
            } catch (e1) {}
        });
        return out;
    };

    window.jwqAppendMetalExchangeRowToMaterialTable = function (mePayload, metalLabel, adjustType) {
        var matBody = typeof jwqGetJobworkMaterialBody === 'function' ? jwqGetJobworkMaterialBody() : document.getElementById('jwqMaterialBody');
        if (!matBody || !mePayload) {
            return;
        }
        var gross = parseFloat(String(mePayload.metal_exchange_gross_wt || '').replace(/,/g, '')) || 0;
        if (gross <= 0) {
            return;
        }
        var empty = matBody.querySelector('.jwq-mat-empty');
        if (empty) {
            empty.remove();
        }
        var rowId = 'jwq-me-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
        var adj = (adjustType === 'add') ? 'add' : 'reduce';
        var prod = String(mePayload.metal_exchange_product_name || '—').trim() || '—';
        var metal = String(metalLabel || 'Metal').trim() || 'Metal';
        var qty = parseFloat(mePayload.quantity) || 1;
        var pur = String(mePayload.purity_carat != null ? mePayload.purity_carat : '1');
        var pwt = meNum(mePayload.metal_exchange_purity_wt, 3);
        var tr = document.createElement('tr');
        tr.className = 'jwq-material-metal-row';
        tr.setAttribute('data-metal-row-id', rowId);
        tr.setAttribute('data-me-adjust', adj);
        tr.setAttribute('data-weight', String(gross));
        tr.setAttribute('data-me-payload', JSON.stringify(mePayload));
        var delTd =
            '<td class="jwq-mat-actions text-center">'
            + '<button type="button" class="btn btn-link btn-sm text-danger p-0 jwq-mat-metal-remove" title="Remove" aria-label="Remove metal row">'
            + '<i class="feather icon-trash-2"></i></button></td>';
        tr.innerHTML =
            '<td>Metal</td>'
            + '<td>' + meEsc(prod) + '</td>'
            + '<td class="jwq-mat-wt">' + meNum(gross, 3) + '</td>'
            + '<td>' + meEsc(metal) + '</td>'
            + '<td class="jwq-mat-qty">' + meNum(qty, 3) + '</td>'
            + '<td>' + meEsc(pur) + '</td>'
            + '<td>' + pwt + '</td>'
            + delTd;
        matBody.appendChild(tr);
        if (typeof window.jwqRefreshJobworkMaterialTotalWt === 'function') {
            window.jwqRefreshJobworkMaterialTotalWt();
        } else if (typeof jwqRefreshJobworkMaterialTotalWt === 'function') {
            jwqRefreshJobworkMaterialTotalWt();
        }
    };

    function jwqCollectMetalExchangePayload() {
        var meMetal = document.getElementById('metalExchangeMetal');
        var meProdIn = document.getElementById('metalExchangeProductInput');
        var meProdId = document.getElementById('metalExchangeProductId');
        var meGw = document.getElementById('metalExchangeGrossWt');
        var mePw = document.getElementById('metalExchangePurityWt');
        var mePur = document.getElementById('metalExchangePurity');
        var meQty = document.getElementById('metalExchangeQty');
        var meRt = document.getElementById('metalExchangeRate');
        var meAmt = document.getElementById('metalExchangeAmount');
        var meIc = document.getElementById('metalExchangeItemCode');
        return {
            metal_exchange_metal_id: meMetal && meMetal.value ? meMetal.value : '',
            metal_exchange_product_id: meProdId && meProdId.value ? meProdId.value : '',
            metal_exchange_product_name: meProdIn && meProdIn.value ? meProdIn.value : '',
            metal_exchange_gross_wt: meGw ? meGw.value : '0',
            metal_exchange_purity_wt: mePw ? mePw.value : '0',
            purity_carat: mePur ? mePur.value : '1',
            quantity: meQty ? (parseFloat(meQty.value) || 0) : 1,
            metal_exchange_rate: meRt ? meRt.value : '0',
            amount: meAmt ? (parseFloat(meAmt.value) || 0) : 0,
            metal_exchange_item_code: meIc ? meIc.value : ''
        };
    }

    window.jwqSaveMetalExchangePayment = function (type) {
        if (type !== 'metal-exchange') {
            return false;
        }
        var isAdd = typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode();
        var isReduce = typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode();
        if (!isAdd && !isReduce) {
            return false;
        }
        var hidId = document.getElementById('jwqCurrentJwoId');
        var id = hidId ? parseInt(hidId.value || '0', 10) : 0;
        if (id < 1) {
            alert('No job work order loaded.');
            return true;
        }
        var fromDeptEl = document.getElementById('jwqFromDept');
        var fromUserEl = document.getElementById('jwqFromUser');
        var toDeptEl = document.getElementById('jwqToDept');
        var toUserEl = document.getElementById('jwqToUser');
        var rEl = document.getElementById('jwqWeightAdjustRemark');
        var fromDeptId = fromDeptEl ? parseInt(fromDeptEl.value || '0', 10) : 0;
        var fromUserId = fromUserEl ? parseInt(fromUserEl.value || '0', 10) : 0;
        var toDeptId = toDeptEl ? parseInt(toDeptEl.value || '0', 10) : 0;
        var toUserId = toUserEl ? parseInt(toUserEl.value || '0', 10) : 0;
        var mePayload = jwqCollectMetalExchangePayload();
        var gross = parseFloat(String(mePayload.metal_exchange_gross_wt || '').replace(/,/g, ''));
        if (isAdd && toDeptId < 1) {
            alert('Please select To Dept.');
            if (toDeptEl) {
                toDeptEl.focus();
            }
            return true;
        }
        if (!mePayload.metal_exchange_metal_id || !mePayload.metal_exchange_product_id) {
            alert('Select metal and product.');
            return true;
        }
        if (!isFinite(gross) || gross <= 0) {
            alert('Enter gross weight greater than zero.');
            return true;
        }
        if (isReduce && fromDeptId < 1) {
            alert('Please select From Dept.');
            if (fromDeptEl) {
                fromDeptEl.focus();
            }
            return true;
        }
        var meMetalEl = document.getElementById('metalExchangeMetal');
        var metalLabel = 'Metal';
        if (meMetalEl && meMetalEl.options && meMetalEl.selectedIndex >= 0) {
            metalLabel = String(meMetalEl.options[meMetalEl.selectedIndex].text || 'Metal').trim() || 'Metal';
        }
        if (isAdd || isReduce) {
            window.jwqAppendMetalExchangeRowToMaterialTable(mePayload, metalLabel, isAdd ? 'add' : 'reduce');
            window.jwqClearMetalExchangeModalForm();
            if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
                window.jQuery('#metalExchangeModal').modal('hide');
            }
            return true;
        }
        return false;
    };

    function jwqOnAddWeightMetalInput(e) {
        var inp = e && e.target;
        if (!inp || !inp.getAttribute || inp.getAttribute('data-field') !== 'metal_wt') {
            return;
        }
        if (typeof window.jwqIsAddWeightMode !== 'function' || !window.jwqIsAddWeightMode()) {
            return;
        }
        var tr = inp.closest('#jwqOrderLinesBody tr[data-item-id]');
        if (!tr) {
            return;
        }
        if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
            window.jwqApplyAddWeightManualMetalPreview(tr);
        }
    }
    document.addEventListener('input', jwqOnAddWeightMetalInput);
    document.addEventListener('change', jwqOnAddWeightMetalInput);
})();
</script>