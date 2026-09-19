<?php 
session_start();
require_once 'config.php';

// Bank/Cash account options (can be moved to a table later)
$bank_cash_options = [
    ['id' => 'Bank', 'name' => 'Bank'],
    ['id' => 'BOI', 'name' => 'BOI'],
    ['id' => 'Cash', 'name' => 'Cash'],
];

// Get next voucher number
$last_voucher = null;
$next_voucher_no = 'CV-1';
$tbl_exists = false;
$check_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_contra_vouchers'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    $tbl_exists = true;
    $last_voucher = getRecord("SELECT voucher_no FROM tbl_contra_vouchers ORDER BY id DESC LIMIT 1");
    if ($last_voucher && !empty($last_voucher['voucher_no'])) {
        $last_num = (int)preg_replace('/[^0-9]/', '', $last_voucher['voucher_no']);
        $next_voucher_no = 'CV-' . ($last_num + 1);
    }
}

// Load voucher for editing if ID provided
$edit_voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_voucher = null;
$edit_items = [];

if ($edit_voucher_id > 0 && $tbl_exists) {
    $edit_voucher = getRecord("SELECT * FROM tbl_contra_vouchers WHERE id = $edit_voucher_id");
    if ($edit_voucher) {
        $edit_items = getList("SELECT * FROM tbl_contra_voucher_items WHERE voucher_id = $edit_voucher_id ORDER BY id ASC");
        $next_voucher_no = $edit_voucher['voucher_no'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Contra Voucher - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
</head>
<style>
body { margin: 0; padding: 0; overflow-x: hidden; min-height: 100vh; background: #f4f6fb; font-family: Roboto, sans-serif; font-size: 11px; }
.layout-wrapper { min-height: 100vh; overflow: visible; }
.layout-container { margin-left: 0 !important; width: 100% !important; padding: 8px 12px; }
.layout-content { min-height: calc(100vh - 120px); overflow-y: auto; padding: 0 0 56px; margin: 0; display: flex; flex-direction: column; gap: 0; }
.layout-content > .contra-form-bar { flex-shrink: 0; }
.layout-content > .voucher-list-card { flex-shrink: 0; display: flex; flex-direction: column; }
.layout-content > .saved-voucher-list-card { flex-shrink: 0; margin-top: 8px; margin-bottom: 16px; display: flex; flex-direction: column; }
#layout-sidenav { display: none !important; }

/* Compact single-row form like reference */
.contra-form-bar { background: #fff; padding: 8px 12px; border-radius: 4px; border: 1px solid #e2e8f0; margin-bottom: 2px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; }
.contra-form-bar .form-group { margin: 0; display: flex; align-items: center; gap: 6px; }
.contra-form-bar label { margin: 0; font-size: 11px; font-weight: 600; color: #000; white-space: nowrap; }
.contra-form-bar .form-control { height: 28px; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 8px; min-width: 0; width: 100%; max-width: 120px; }
.contra-form-bar .form-control#comment { min-width: 470px; max-width: 320px; flex: 1; }
.contra-form-bar .form-control#voucherDate { max-width: 110px; }
.contra-form-bar .form-control#voucherNo { max-width: 80px; background: #f8fafc; }
.contra-form-bar .radio-group { display: flex; gap: 8px; align-items: center; }
.contra-form-bar .radio-group label { margin: 0; font-size: 11px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.btn-save { background: #11294b; color: #fff; border: none; padding: 5px 16px; height: 28px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.btn-save:hover { background: #4a2f70; color: #fff; }
.btn-add-row { background: #11294b; color: #fff; border: none; padding: 4px 10px; height: 26px; border-radius: 4px; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.btn-add-row:hover { background: #1e3a5f; color: #fff; }

.voucher-list-card { background: #fff; border-radius: 4px; border: 1px solid #e2e8f0; overflow: hidden; margin-top: 0; margin-bottom: 0; }
.voucher-list-header { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; min-height: 32px; }
.voucher-list-header h5 { margin: 0; font-size: 12px; font-weight: 600; color: #11294b; }
.voucher-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.voucher-table th { background: #f8fafc; padding: 4px 6px; text-align: left; font-weight: 600; color: #000; border-bottom: 1px solid #e2e8f0; white-space: nowrap; font-size: 10px; }
.voucher-table td { padding: 3px 6px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
.voucher-table tbody tr:hover { background: #f8fafc; }
.voucher-table .text-right { text-align: right; }
.voucher-table input[type="text"], .voucher-table input[type="number"], .voucher-table input[type="date"], .voucher-table select { width: 100%; padding: 3px 5px; border: 1px solid #e2e8f0; border-radius: 3px; font-size: 10px; height: 22px; min-width: 0; }
/* Action column: icons in single line */
.voucher-table td.action-cell { padding: 2px 4px; white-space: nowrap; width: 1%; }
.voucher-table .action-btns { display: inline-flex; align-items: center; gap: 2px; flex-wrap: nowrap; }
.voucher-table .btn-icon { background: none; border: none; padding: 1px 3px; cursor: pointer; color: #64748b; font-size: 12px; line-height: 1; display: inline-flex; align-items: center; justify-content: center; }
.voucher-table .btn-icon:hover { color: #11294b; }
.voucher-table .btn-icon.delete:hover { color: #dc2626; }
.tfoot-total { background: #f8fafc; font-weight: 600; padding: 4px 8px; font-size: 11px; text-align: right; border-top: 1px solid #e2e8f0; }
.col-settings-btn { background: none; border: none; padding: 2px 6px; cursor: pointer; color: #64748b; font-size: 12px; }
.col-settings-btn:hover { color: #11294b; }
.voucher-no-label { font-size: 11px; color: #64748b; white-space: nowrap; }

/* Saved voucher list (like payment-voucher Payment List) */
.saved-voucher-list-card { background: #fff; border-radius: 4px; border: 1px solid #e2e8f0; overflow: hidden; }
.saved-voucher-list-header { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.saved-voucher-list-header h5 { margin: 0; font-size: 12px; font-weight: 600; color: #11294b; }
.saved-voucher-list-scroll { overflow-x: auto; }
.saved-voucher-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 0; }
.saved-voucher-table thead th { background: #11294b; color: #fff; padding: 8px 10px; font-weight: 600; white-space: nowrap; border: 1px solid #0f2444; font-size: 11px; }
.saved-voucher-table tbody td { padding: 6px 10px; border: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
.saved-voucher-table tbody tr[data-voucher-id] { cursor: pointer; }
.saved-voucher-table tbody tr[data-voucher-id]:hover { background: #f1f5f9; }
.saved-voucher-table .saved-voucher-actions { white-space: nowrap; width: 70px; }
.saved-voucher-table .saved-voucher-actions .btn { padding: 2px 6px; line-height: 1; }
.saved-voucher-table .text-right { text-align: right; }
.saved-voucher-table .svl-col-drag-h { display: inline-flex; align-items: center; margin-right: 4px; cursor: grab; opacity: 0.75; }
.saved-voucher-table .svl-col-drag-h:active { cursor: grabbing; }
.saved-voucher-list-footer { padding: 10px 12px 12px; border-top: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; background: #fafbfc; }
.saved-voucher-pagination { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 11px; color: #64748b; }
.saved-voucher-pagination .btn { font-size: 11px; padding: 3px 10px; height: 26px; line-height: 1.2; }
.saved-voucher-pagination .svl-page-size { height: 26px; font-size: 11px; padding: 2px 6px; border: 1px solid #e2e8f0; border-radius: 4px; }
.voucher-list-export-wrap { position: relative; display: inline-block; }
.voucher-list-export-menu { display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; min-width: 160px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 1000; padding: 4px 0; }
.voucher-list-export-menu.show { display: block; }
.voucher-list-export-menu button { display: block; width: 100%; text-align: left; border: none; background: none; padding: 8px 12px; font-size: 0.75rem; color: #334155; cursor: pointer; }
.voucher-list-export-menu button:hover { background: #f1f5f9; }
</style>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div class="layout-container">
            <?php include 'sidebar.php';?>
            <div class="layout-content">
                <!-- Compact single-row form: Bank/Cash | Dr/Cr | Comment | Voucher No | Date | Save -->
                <div class="contra-form-bar">
                    <div class="form-group">
                        <label>Bank/Cash a/c *</label>
                        <select class="form-control" id="bankCashAc" required style="width: 160px;">
                            <option value="">Select</option>
                            <?php foreach ($bank_cash_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['id']) ?>"><?= htmlspecialchars($opt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="radio-group">
                            <label><input type="radio" name="transType" value="deposit" id="transDeposit"> Deposit/Dr</label>
                            <label><input type="radio" name="transType" value="withdrawal" id="transWithdrawal" checked> Withdrawal/Cr</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Comment</label>
                        <input type="text" class="form-control" id="comment" placeholder="Comment">
                    </div>
                    <div class="form-group" style="margin-left: auto;">
                        <span class="voucher-no-label">Contra Voucher No:</span>
                        <input type="text" class="form-control" id="voucherNo" value="<?= htmlspecialchars($next_voucher_no) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" class="form-control" id="voucherDate" value="<?= date('Y-m-d') ?>">
                    </div>
                    <button type="button" class="btn btn-save" id="btnSaveContra"><i class="feather icon-save"></i> Save</button>
                </div>

                <!-- Voucher list - compact -->
                <div class="voucher-list-card">
                    <div class="voucher-list-header">
                        <h5>Voucher List</h5>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <button type="button" class="btn-add-row" id="btnAddRow"><i class="feather icon-plus"></i> Add Row</button>
                            <button type="button" class="col-settings-btn" id="btnColSettings" title="Columns"><i class="feather icon-settings"></i></button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="voucher-table" id="voucherTable">
                            <thead>
                                <tr>
                                    <th data-column="bank_cash_ac">Bank/Cash a/c</th>
                                    <th data-column="ref_no">Ref. No.</th>
                                    <th data-column="ref_date">Ref. Date</th>
                                    <th data-column="transaction_type">Transaction Type</th>
                                    <th data-column="amount" class="text-right">Amount</th>
                                    <th data-column="comment">Comment</th>
                                    <th class="no-sort action-cell" style="width: 52px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="voucherTableBody">
                                <?php 
                                if (!empty($edit_items)) {
                                    foreach ($edit_items as $item) {
                                        $ref_date = !empty($item['ref_date']) ? date('Y-m-d', strtotime($item['ref_date'])) : '';
                                        $is_deposit = ($item['transaction_type'] ?? '') === 'deposit';
                                        echo '<tr data-id="'.(int)$item['id'].'">';
                                        echo '<td data-column="bank_cash_ac"><select class="form-control row-bank-ac"><option value="Bank"'.($item['bank_cash_ac']=='Bank'?' selected':'').'>Bank</option><option value="BOI"'.($item['bank_cash_ac']=='BOI'?' selected':'').'>BOI</option><option value="Cash"'.($item['bank_cash_ac']=='Cash'?' selected':'').'>Cash</option></select></td>';
                                        echo '<td data-column="ref_no"><input type="text" class="form-control row-ref-no" value="'.htmlspecialchars($item['ref_no']??'').'" placeholder="Ref. No."></td>';
                                        echo '<td data-column="ref_date"><input type="date" class="form-control row-ref-date" value="'.$ref_date.'"></td>';
                                        echo '<td data-column="transaction_type"><label class="mb-0"><input type="radio" name="row_type_'.$item['id'].'" value="deposit" '.($is_deposit?'checked':'').'> Dr</label> <label class="mb-0"><input type="radio" name="row_type_'.$item['id'].'" value="withdrawal" '.(!$is_deposit?'checked':'').'> Cr</label></td>';
                                        echo '<td data-column="amount" class="text-right"><input type="number" class="form-control row-amount text-right" value="'.htmlspecialchars($item['amount']??'0').'" step="0.01" min="0" placeholder="0.00"></td>';
                                        echo '<td data-column="comment"><input type="text" class="form-control row-comment" value="'.htmlspecialchars($item['comment']??'').'" placeholder="Comment"></td>';
                                        echo '<td class="action-cell"><span class="action-btns"><button type="button" class="btn-icon edit-row" title="Edit"><i class="feather icon-edit-2"></i></button><button type="button" class="btn-icon delete delete-row" title="Delete"><i class="feather icon-trash-2"></i></button></span></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    // One empty row by default
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr><td colspan="7" class="tfoot-total">Total: <span id="totalAmount">0.00</span></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Saved Contra Vouchers -->
                <div class="saved-voucher-list-card" id="contraSavedListCard">
                    <div class="saved-voucher-list-header">
                        <h5>Contra Voucher List</h5>
                        <div class="voucher-list-export-wrap">
                            <button type="button" class="btn btn-sm svl-export-btn" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.5rem; font-size: 0.7rem;">Export <i class="feather icon-chevron-down" style="font-size: 0.65rem;"></i></button>
                            <div class="voucher-list-export-menu">
                                <button type="button" class="js-svl-export-excel">Export in Excel</button>
                                <button type="button" class="js-svl-export-pdf">Export in PDF</button>
                            </div>
                        </div>
                    </div>
                    <div class="saved-voucher-list-scroll">
                        <table class="table table-bordered table-sm saved-voucher-table">
                            <thead>
                                <tr>
                                    <th data-column="sr-no"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Sr.No.</th>
                                    <th data-column="voucher_date"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Date</th>
                                    <th data-column="voucher_no"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Voucher No.</th>
                                    <th data-column="comment"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Comment</th>
                                    <th data-column="total_amount" class="text-right"><span class="svl-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Total Amount</th>
                                    <th data-column="actions" style="width: 70px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="contraSavedListBody">
                                <tr class="no-rows"><td colspan="6" class="text-center text-muted py-3">No Rows To Show</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="saved-voucher-list-footer saved-voucher-pagination">
                        <span class="svl-page-info">Loading…</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="margin: 0; font-size: 11px; color: #64748b;">Rows:</label>
                            <select class="svl-page-size">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-secondary svl-page-prev">Previous</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary svl-page-next">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Column settings modal -->
<div class="modal fade" id="colSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Columns</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="colSearch" class="form-control mb-2" placeholder="Search columns">
                <div id="colList"></div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php';?>
<script src="assets/libs/sortablejs/sortable.js"></script>
<script src="assets/js/voucher-saved-list.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/voucher-saved-list.js'); ?>"></script>

<script>
(function() {
    var voucherId = <?= (int)$edit_voucher_id ?>;
    var nextVoucherNo = <?= json_encode($next_voucher_no) ?>;
    var bankOptions = <?= json_encode($bank_cash_options) ?>;

    function addRow(opts) {
        opts = opts || {};
        var tbody = document.getElementById('voucherTableBody');
        var rowId = 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
        var isDr = !!opts.deposit;
        var preferAc = opts.bank_cash_ac || '';
        var tr = document.createElement('tr');
        tr.setAttribute('data-row-id', rowId);
        var optsHtml = bankOptions.map(function (o) {
            var sel = preferAc && String(o.id) === String(preferAc) ? ' selected' : '';
            return '<option value="' + o.id + '"' + sel + '>' + o.name + '</option>';
        }).join('');
        tr.innerHTML =
            '<td data-column="bank_cash_ac"><select class="form-control row-bank-ac">' + optsHtml + '</select></td>' +
            '<td data-column="ref_no"><input type="text" class="form-control row-ref-no" placeholder="Ref. No."></td>' +
            '<td data-column="ref_date"><input type="date" class="form-control row-ref-date" value="<?= date('Y-m-d') ?>"></td>' +
            '<td data-column="transaction_type"><label class="mb-0"><input type="radio" name="rtype_' + rowId + '" value="deposit"' + (isDr ? ' checked' : '') + '> Dr</label> <label class="mb-0"><input type="radio" name="rtype_' + rowId + '" value="withdrawal"' + (!isDr ? ' checked' : '') + '> Cr</label></td>' +
            '<td data-column="amount" class="text-right"><input type="number" class="form-control row-amount text-right" value="0" step="0.01" min="0" placeholder="0.00"></td>' +
            '<td data-column="comment"><input type="text" class="form-control row-comment" placeholder="Comment"></td>' +
            '<td class="action-cell"><span class="action-btns"><button type="button" class="btn-icon edit-row" title="Edit"><i class="feather icon-edit-2"></i></button><button type="button" class="btn-icon delete delete-row" title="Delete"><i class="feather icon-trash-2"></i></button></span></td>';
        tbody.appendChild(tr);
        bindRowEvents(tr);
        updateTotal();
    }

    function bindRowEvents(tr) {
        var delBtn = tr.querySelector('.delete-row');
        if (delBtn) delBtn.addEventListener('click', function() { tr.remove(); updateTotal(); });
        var amtInput = tr.querySelector('.row-amount');
        if (amtInput) amtInput.addEventListener('input', updateTotal);
    }

    function updateTotal() {
        var total = 0;
        document.querySelectorAll('#voucherTableBody .row-amount').forEach(function(inp) {
            total += parseFloat(inp.value) || 0;
        });
        document.getElementById('totalAmount').textContent = total.toFixed(2);
    }

    function getItems() {
        var items = [];
        document.querySelectorAll('#voucherTableBody tr').forEach(function(tr) {
            var bankAc = tr.querySelector('.row-bank-ac');
            var refNo = tr.querySelector('.row-ref-no');
            var refDate = tr.querySelector('.row-ref-date');
            var typeDep = tr.querySelector('input[value="deposit"]');
            var typeWith = tr.querySelector('input[value="withdrawal"]');
            var amount = tr.querySelector('.row-amount');
            var comment = tr.querySelector('.row-comment');
            items.push({
                bank_cash_ac: bankAc ? bankAc.value : '',
                ref_no: refNo ? refNo.value : '',
                ref_date: refDate ? refDate.value : '',
                transaction_type: (typeDep && typeDep.checked) ? 'deposit' : 'withdrawal',
                amount: amount ? (parseFloat(amount.value) || 0) : 0,
                comment: comment ? comment.value : ''
            });
        });
        return items;
    }

    document.getElementById('btnAddRow').addEventListener('click', addRow);
    document.querySelectorAll('#voucherTableBody tr').forEach(function(tr) { bindRowEvents(tr); });
    document.getElementById('btnSaveContra').addEventListener('click', function() {
        var bankAc = (document.getElementById('bankCashAc').value || '').trim();
        var comment = document.getElementById('comment').value;
        var voucherDate = document.getElementById('voucherDate').value;
        var transTypeEl = document.querySelector('input[name="transType"]:checked');
        var headerType = transTypeEl ? transTypeEl.value : 'withdrawal';
        var items = getItems().filter(function (it) {
            return it.bank_cash_ac && (parseFloat(it.amount) || 0) > 0;
        });
        if (items.length === 0) {
            alert('Add at least one row with Bank/Cash a/c and amount.');
            return;
        }

        // Top bar Bank/Cash + Dr/Cr is the other side of the contra (often not duplicated in the grid).
        // If the grid is missing that side, auto-add the header account with matching amount.
        var totalDr = 0;
        var totalCr = 0;
        items.forEach(function (it) {
            var amt = parseFloat(it.amount) || 0;
            if (it.transaction_type === 'deposit') totalDr += amt;
            else totalCr += amt;
        });
        var headerAlreadyInItems = items.some(function (it) {
            return String(it.bank_cash_ac).toLowerCase() === String(bankAc).toLowerCase()
                && it.transaction_type === headerType;
        });
        if ((totalDr < 0.00001 || totalCr < 0.00001) && bankAc) {
            var missingIsDeposit = headerType === 'deposit';
            var oppositeTotal = missingIsDeposit ? totalCr : totalDr;
            // If header is the missing side, use opposite total; if header matches the only side, flip it
            if ((missingIsDeposit && totalDr < 0.00001) || (!missingIsDeposit && totalCr < 0.00001)) {
                if (!headerAlreadyInItems && oppositeTotal > 0) {
                    items.push({
                        bank_cash_ac: bankAc,
                        ref_no: '',
                        ref_date: voucherDate || '',
                        transaction_type: headerType,
                        amount: oppositeTotal,
                        comment: comment || ''
                    });
                }
            }
        } else if (bankAc && !headerAlreadyInItems && Math.abs(totalDr - totalCr) > 0.02) {
            // Grid unbalanced and header account not listed — use header to fill the short side
            var shortIsDr = totalDr < totalCr;
            var shortAmt = Math.abs(totalCr - totalDr);
            var useHeaderType = shortIsDr ? 'deposit' : 'withdrawal';
            // Prefer header type when it matches the short side
            if ((headerType === 'deposit') === shortIsDr) {
                useHeaderType = headerType;
            }
            items.push({
                bank_cash_ac: bankAc,
                ref_no: '',
                ref_date: voucherDate || '',
                transaction_type: useHeaderType,
                amount: shortAmt,
                comment: comment || ''
            });
        }

        totalDr = 0;
        totalCr = 0;
        items.forEach(function (it) {
            var amt = parseFloat(it.amount) || 0;
            if (it.transaction_type === 'deposit') totalDr += amt;
            else totalCr += amt;
        });
        if (totalDr < 0.00001 || totalCr < 0.00001) {
            alert(
                'Contra needs both accounts.\n' +
                    '1) Select the other Bank/Cash a/c and Dr/Cr in the top bar, OR\n' +
                    '2) Add a second row with the opposite Dr/Cr.\n\n' +
                    'Example: Cash Dr 100 + BOI Cr 100'
            );
            return;
        }
        if (Math.abs(totalDr - totalCr) > 0.02) {
            alert(
                'Contra voucher is not balanced.\nDeposit/Dr: ' +
                    totalDr.toFixed(2) +
                    '\nWithdrawal/Cr: ' +
                    totalCr.toFixed(2) +
                    '\nBoth sides must be equal.'
            );
            return;
        }

        var formData = new FormData();
        formData.append('voucher_id', voucherId);
        formData.append('voucher_no', document.getElementById('voucherNo').value || nextVoucherNo);
        formData.append('voucher_date', voucherDate);
        formData.append('comment', comment);
        formData.append('bank_cash_ac', bankAc);
        formData.append('transaction_type', headerType);
        formData.append('items', JSON.stringify(items));

        fetch('ajax/save-contra-voucher.php', { method: 'POST', body: formData })
            .then(function(r) {
                return r.text().then(function(text) {
                    try {
                        return { ok: r.ok, data: text ? JSON.parse(text) : {} };
                    } catch (e) {
                        return { ok: false, data: { status: 'error', message: text || 'Invalid response from server.' } };
                    }
                });
            })
            .then(function(result) {
                var data = result.data;
                if (result.ok && data.status === 'success') {
                    alert(data.message || 'Saved successfully.');
                    // Reload blank form ready for a new contra voucher
                    window.location.href = 'contra-voucher.php';
                } else {
                    alert(data.message || 'Error saving.');
                }
            })
            .catch(function(err) { alert('Network error. ' + (err && err.message ? err.message : '')); });
    });

    updateTotal();

    // Column settings
    var colDefs = [
        { key: 'bank_cash_ac', label: 'Bank/Cash a/c' },
        { key: 'ref_no', label: 'Ref. No.' },
        { key: 'ref_date', label: 'Ref. Date' },
        { key: 'transaction_type', label: 'Transaction Type' },
        { key: 'amount', label: 'Amount' },
        { key: 'comment', label: 'Comment' }
    ];
    function getColPrefs() {
        try { return JSON.parse(localStorage.getItem('contra_voucher_columns') || '{}'); } catch(e) { return {}; }
    }
    function saveColPrefs(p) { localStorage.setItem('contra_voucher_columns', JSON.stringify(p)); }
    function applyColVisibility() {
        var p = getColPrefs();
        colDefs.forEach(function(c) {
            var vis = p[c.key] !== false;
            document.querySelectorAll('[data-column="'+c.key+'"]').forEach(function(el) { el.style.display = vis ? '' : 'none'; });
        });
    }
    document.getElementById('btnColSettings').addEventListener('click', function() {
        var html = '';
        var p = getColPrefs();
        colDefs.forEach(function(c) {
            var checked = p[c.key] !== false ? 'checked' : '';
            html += '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="col_'+c.key+'" '+checked+'><label class="custom-control-label" for="col_'+c.key+'">'+c.label+'</label></div>';
        });
        document.getElementById('colList').innerHTML = html;
        document.getElementById('colList').querySelectorAll('input').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var prefs = getColPrefs();
                prefs[cb.id.replace('col_','')] = cb.checked;
                saveColPrefs(prefs);
                applyColVisibility();
            });
        });
        $('#colSettingsModal').modal('show');
    });
    applyColVisibility();

    // New voucher: seed both sides (Cash Dr + Bank Cr) so Account Ledger gets proper double-entry
    if (document.querySelectorAll('#voucherTableBody tr').length === 0) {
        addRow({ deposit: true, bank_cash_ac: 'Cash' });
        addRow({ deposit: false, bank_cash_ac: 'Bank' });
    }

    if (typeof window.initVoucherSavedList === 'function') {
        window.initVoucherSavedList({
            root: '#contraSavedListCard',
            tableSelector: '#contraSavedListCard .saved-voucher-table',
            bodySelector: '#contraSavedListBody',
            paginationSelector: '.saved-voucher-pagination',
            getUrl: 'ajax/get-contra-vouchers.php',
            deleteUrl: 'ajax/delete-contra-voucher.php',
            editPage: 'contra-voucher.php',
            deleteConfirm: 'Are you sure you want to delete this contra voucher? This cannot be undone.',
            pageSize: 10,
            columnOrderKey: 'contraSavedListColumnOrder',
            exportExcelUrl: 'ajax/export-simple-voucher-list-excel.php?kind=contra',
            exportPdfUrl: 'ajax/export-simple-voucher-list-pdf.php?kind=contra',
            columns: [
                { key: 'voucher_date', field: 'voucher_date', label: 'Date', format: 'date' },
                { key: 'voucher_no', field: 'voucher_no', label: 'Voucher No.' },
                { key: 'comment', field: 'comment', label: 'Comment' },
                { key: 'total_amount', field: 'total_amount', label: 'Total Amount', format: 'amount', align: 'right' }
            ]
        });
    }
})();
</script>
</body>
</html>
