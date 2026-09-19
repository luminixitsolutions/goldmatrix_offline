<?php
/**
 * Shared UI: expects $auragold_mac = ['dir' => 'metal_to_amount'|'amount_to_metal', 'title' => string]
 */
if (empty($auragold_mac) || empty($auragold_mac['dir']) || empty($auragold_mac['title'])) {
    exit;
}
$mac_dir = $auragold_mac['dir'];
$mac_title = (string) $auragold_mac['title'];
$is_mta = ($mac_dir === 'metal_to_amount');
$mac_effective_bid = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($mac_effective_bid <= 0 && !empty($_SESSION['branch_id'])) {
    $mac_effective_bid = (int) $_SESSION['branch_id'];
}
require_once __DIR__ . '/includes/ensure_metal_amount_conversion.php';
if (!empty($conn) && $conn instanceof mysqli) {
    auragold_ensure_metal_amount_conversion_table($conn);
}
$pb_show_use_amount_row = false;
$pb_show_use_previous_checkbox = false;
$mac_preload_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>
<div class="layout-content mac-layout">
<div class="container-fluid mac-page-outer metal-conversion-page px-2 py-0">
    <div class="mac-topbar d-flex flex-wrap align-items-center">
        <span class="mac-title-text mb-0"><?php echo htmlspecialchars($mac_title, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>

    <div class="row mac-invoice-content-row">
        <div class="col-lg-8 col-12 my-2 my-lg-0 pl-lg-2 pr-lg-1">
            <div class="mac-form-card mac-card-customer-metal">
                <div class="row align-items-end">
                    <div class="col-12 col-md-6 mb-3 mb-md-0 pr-md-2">
                        <label class="mac-fld-label">Customer <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="text" class="form-control mac-input" id="macCustomerSearch" placeholder="Search by name, mobile" autocomplete="off" required>
                            <div id="macCustomerSuggest" class="mac-suggest d-none"></div>
                            <input type="hidden" id="macCustomerId" value="0">
                        </div>
                    </div>
                    <div class="col-12 col-md-6 pl-md-2">
                        <span class="mac-fld-label d-block">Metal</span>
                        <div class="d-flex flex-wrap align-items-center mac-metal-radios" id="macMetalRadios">
                            <span class="small text-muted" id="macMetalRadiosLoading">Loading…</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mac-form-card mac-convert-line mt-2">
                <div class="d-flex flex-wrap align-items-end mac-convert-row">
                    <?php if ($is_mta) : ?>
                    <div class="mac-cel">
                        <label class="mac-fld-label">Metal (wt / ct)</label>
                        <input type="text" class="form-control form-control-sm mac-input mac-num" id="macMetalW" inputmode="decimal" placeholder="0.000" autocomplete="off">
                    </div>
                    <div class="mac-cel mac-cel-sm">
                        <label class="mac-fld-label">Dr/Cr</label>
                        <select class="form-control form-control-sm mac-input" id="macMetalWCrDr" disabled>
                            <option>Dr</option>
                        </select>
                    </div>
                    <div class="mac-cel mac-cel-rate">
                        <label class="mac-fld-label">Rate <span class="text-muted font-weight-normal" id="macRateSub" style="font-size:0.65rem;text-transform:none;"></span></label>
                        <input type="text" class="form-control form-control-sm mac-input mac-num" id="macRate" inputmode="decimal" placeholder="0.00" autocomplete="off" title="Auto-filled from Dashboard rates (last updated); you can change.">
                    </div>
                    <div class="mac-cel mac-cel-calc">
                        <button type="button" class="btn btn-sm mac-btn-primary" id="macBtnCalc">CALC</button>
                    </div>
                    <div class="mac-cel mac-cel-out">
                        <label class="mac-fld-label">Amt generated</label>
                        <input type="text" class="form-control form-control-sm mac-wash mac-num" id="macAmtOut" readonly placeholder="0.00" tabindex="-1">
                    </div>
                    <div class="mac-cel mac-cel-sm">
                        <label class="mac-fld-label">A/C</label>
                        <select class="form-control form-control-sm mac-input" id="macAmtOutCrDr" disabled>
                            <option>Dr</option>
                        </select>
                    </div>
                    <div class="mac-cel mac-cel-item d-none" id="macAgainstItemWrap">
                        <label class="mac-fld-label">Against Item</label>
                        <select class="form-control form-control-sm mac-input" id="macAgainstItem" title="Convert metal against this item">
                            <option value="">— Select item —</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="mac-cel">
                        <label class="mac-fld-label">Selected metal bal</label>
                        <input type="text" class="form-control form-control-sm mac-wash" id="macHeadMetalBal" readonly placeholder="0.000" tabindex="-1">
                    </div>
                    <div class="mac-cel mac-cel-sm">
                        <label class="mac-fld-label">A/C</label>
                        <select class="form-control form-control-sm mac-input" id="macHeadMetalDrCr" disabled>
                            <option>Cr</option>
                        </select>
                    </div>
                    <div class="mac-cel mac-cel-rate">
                        <label class="mac-fld-label">Rate <span class="text-muted font-weight-normal" id="macRateSub" style="font-size:0.65rem;text-transform:none;"></span></label>
                        <input type="text" class="form-control form-control-sm mac-input mac-num" id="macRate" inputmode="decimal" placeholder="0.00" autocomplete="off" title="Auto-filled from Dashboard rates (last updated); you can change.">
                    </div>
                    <div class="mac-cel mac-cel-calc">
                        <button type="button" class="btn btn-sm mac-btn-primary" id="macBtnCalc">CALC</button>
                    </div>
                    <div class="mac-cel mac-cel-mid">
                        <label class="mac-fld-label">Amt to convert</label>
                        <input type="text" class="form-control form-control-sm mac-input mac-num" id="macAmtIn" inputmode="decimal" placeholder="0" autocomplete="off">
                    </div>
                    <div class="mac-cel mac-cel-sm">
                        <label class="mac-fld-label">A/C</label>
                        <select class="form-control form-control-sm mac-input" id="macAmtInCrDr" disabled>
                            <option>Dr</option>
                        </select>
                    </div>
                    <div class="mac-cel mac-cel-out-m">
                        <label class="mac-fld-label">Metal generated</label>
                        <input type="text" class="form-control form-control-sm mac-wash" id="macWtOut" readonly placeholder="0.000" tabindex="-1">
                    </div>
                    <div class="mac-cel mac-cel-sm">
                        <label class="mac-fld-label">A/C</label>
                        <select class="form-control form-control-sm mac-input" id="macWtOutCrDr" disabled>
                            <option>Dr</option>
                        </select>
                    </div>
                    <div class="mac-cel mac-cel-item d-none" id="macAgainstItemWrap">
                        <label class="mac-fld-label">Against Item</label>
                        <select class="form-control form-control-sm mac-input" id="macAgainstItem" title="Convert metal against this item">
                            <option value="">— Select item —</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mac-cel flex-grow-1" style="min-width: 140px;">
                        <label class="mac-fld-label">Comment</label>
                        <input type="text" class="form-control form-control-sm mac-input" id="macComment" placeholder="" autocomplete="off">
                    </div>
                    <div class="mac-cel mac-cel-save">
                        <button type="button" class="btn btn-sm mac-btn-save" id="macSave">SAVE</button>
                    </div>
                </div>
            </div>
            <p class="small text-muted my-2 mb-0" id="macHelper"></p>

            <div class="mac-hist mac-form-card">
                <h6 class="mac-hist-title">History</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" id="macHistTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="d-none d-md-table-cell">No</th>
                                <th>Metal</th>
                                <th class="text-right">Wt/ct</th>
                                <th class="text-right d-none d-md-table-cell">Rate</th>
                                <th class="text-right">Amount</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                        <tr class="text-muted text-center no-row"><td colspan="7" class="py-3">No records</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-12 pl-lg-1 pr-lg-2 my-2 my-lg-0">
            <div class="summary-panel mac-sb-sticky">
                <?php include __DIR__ . '/includes/previous-balance-panel.php'; ?>
            </div>
        </div>
    </div>
</div>
</div>
<div id="macPrintModal" class="print-invoice-modal mac-print-modal" style="display: none;">
    <div class="print-invoice-modal-content">
        <button type="button" class="print-invoice-modal-close" onclick="macClosePrintModal()" aria-label="Close">&times;</button>
        <div class="print-invoice-modal-icon">
            <div class="receipt-icon-wrapper">
                <div class="receipt-paper">
                    <div class="receipt-lines">
                        <div class="receipt-line"></div>
                        <div class="receipt-line"></div>
                        <div class="receipt-line"></div>
                    </div>
                    <div class="receipt-dollar">$</div>
                    <div class="receipt-checkmark">✓</div>
                </div>
            </div>
        </div>
        <h3 class="print-invoice-modal-title">Print bill</h3>
        <p class="print-invoice-modal-message" id="macPrintModalMsg">Do you want to print?</p>
        <div class="print-invoice-modal-buttons">
            <button type="button" class="print-invoice-btn-yes" onclick="macConfirmPrint()">Print</button>
            <button type="button" class="print-invoice-btn-no" onclick="macClosePrintModal()">No</button>
        </div>
    </div>
</div>
<style>
/* Same as sale-invoice.php print confirmation modal */
.print-invoice-modal.mac-print-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}
.print-invoice-modal.mac-print-modal .print-invoice-modal-content {
    background: #fff;
    border-radius: 16px;
    padding: 40px 30px 30px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    position: relative;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}
.print-invoice-modal.mac-print-modal .print-invoice-modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    color: #9CA3AF;
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}
.print-invoice-modal.mac-print-modal .print-invoice-modal-close:hover {
    background: #F3F4F6;
    color: #6B7280;
}
.print-invoice-modal.mac-print-modal .print-invoice-modal-icon {
    margin-bottom: 25px;
    display: flex;
    justify-content: center;
}
.print-invoice-modal.mac-print-modal .receipt-icon-wrapper {
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.print-invoice-modal.mac-print-modal .receipt-paper {
    width: 70px;
    height: 90px;
    background: linear-gradient(135deg, #E8EAF6 0%, #C5CAE9 100%);
    border-radius: 8px;
    position: relative;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 12px 8px;
}
.print-invoice-modal.mac-print-modal .receipt-lines {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;
}
.print-invoice-modal.mac-print-modal .receipt-line {
    height: 2px;
    background: #9CA3AF;
    border-radius: 1px;
}
.print-invoice-modal.mac-print-modal .receipt-line:nth-child(1) { width: 90%; }
.print-invoice-modal.mac-print-modal .receipt-line:nth-child(2) { width: 75%; }
.print-invoice-modal.mac-print-modal .receipt-line:nth-child(3) { width: 60%; }
.print-invoice-modal.mac-print-modal .receipt-dollar {
    position: absolute;
    left: 12px;
    top: 25px;
    width: 20px;
    height: 20px;
    background: #F59E0B;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 12px;
}
.print-invoice-modal.mac-print-modal .receipt-checkmark {
    position: absolute;
    right: 10px;
    bottom: 15px;
    width: 24px;
    height: 24px;
    background: #10B981;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 12px;
}
.print-invoice-modal.mac-print-modal .print-invoice-modal-title {
    font-size: 28px;
    font-weight: 700;
    color: #1E40AF;
    margin: 0 0 12px 0;
}
.print-invoice-modal.mac-print-modal .print-invoice-modal-message {
    font-size: 16px;
    color: #64748B;
    margin: 0 0 30px 0;
}
.print-invoice-modal.mac-print-modal .print-invoice-modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.print-invoice-modal.mac-print-modal .print-invoice-btn-yes,
.print-invoice-modal.mac-print-modal .print-invoice-btn-no {
    padding: 12px 32px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 100px;
}
.print-invoice-modal.mac-print-modal .print-invoice-btn-yes {
    background: #11294b;
    color: #fff;
}
.print-invoice-modal.mac-print-modal .print-invoice-btn-yes:hover {
    background: #4a2d6c;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(90, 59, 140, 0.3);
}
.print-invoice-modal.mac-print-modal .print-invoice-btn-no {
    background: #FCE7F3;
    color: #EC4899;
}
.print-invoice-modal.mac-print-modal .print-invoice-btn-no:hover {
    background: #FBCFE8;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(236, 72, 153, 0.2);
}
.mac-layout { min-height: calc(100vh - 60px); background: linear-gradient(180deg, #f1f5f9 0%, #e8edf5 100%); }
.mac-page-outer { max-width: 100%; }
.mac-topbar {
    background: linear-gradient(90deg, #11294b 0%, #1a3a5c 100%);
    color: #fff;
    padding: 10px 16px;
    border-radius: 0 0 10px 10px;
    box-shadow: 0 2px 8px rgba(17,41,75,0.25);
}
.mac-title-text { font-weight: 600; font-size: 1rem; letter-spacing: 0.03em; }
.mac-invoice-content-row { margin: 0; }
.mac-card-customer-metal .mac-metal-radios { gap: 0.25rem 0.75rem; }

.mac-form-card {
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem 1.1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.07);
    transition: box-shadow 0.2s;
}
.mac-form-card:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.1); }
.mac-sb-sticky { position: sticky; top: 8px; }
/* Match sale-invoice summary panel: previous balance is only child */
.mac-convert-line .mac-fld-label { margin-bottom: 4px; }
.mac-fld-label {
    font-weight: 700;
    font-size: 10.5px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #11294b;
    display: block;
    margin-bottom: 4px;
}
.mac-input, .mac-convert-line .form-control {
    font-size: 0.8rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #0f172a;
    padding: 0.35rem 0.5rem;
}
.mac-input:focus { border-color: #c5a864; box-shadow: 0 0 0 0.1rem rgba(17, 41, 75, 0.12); }
.mac-metal-radios .mac-radio { margin: 0 1rem 0 0; font-size: 0.85rem; color: #334155; cursor: pointer; }
.mac-metal-radios .mac-radio input { margin-right: 4px; vertical-align: -2px; }
.mac-convert-row { gap: 0.4rem; margin: 0; }
.mac-cel { min-width: 0; margin-bottom: 0.4rem; }
@media (min-width: 992px) {
  .mac-cel-sm { width: 72px; }
  .mac-cel-rate { width: 96px; }
  .mac-cel-calc { width: 58px; padding-top: 1.35rem; }
  .mac-cel-out { width: 108px; }
  .mac-cel-mid { width: 100px; }
  .mac-cel-out-m { min-width: 100px; }
  .mac-cel-item { min-width: 170px; flex: 1 1 180px; max-width: 240px; }
  .mac-cel-save { padding-top: 1.35rem; }
}
@media (max-width: 991.98px) {
  .mac-cel { flex: 1 1 45%; }
  .mac-cel-calc, .mac-cel-save { flex: 0 0 auto; }
}
.mac-wash { background: #e8f4fc !important; color: #0b3d66; font-weight: 600; border-color: #b8d4ea !important; }
.mac-btn-primary, .mac-btn-save {
    background: #11294b !important;
    border: none !important;
    color: #fff !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    border-radius: 6px;
    min-height: 32px;
    padding-left: 12px;
    padding-right: 12px;
    box-shadow: 0 2px 4px rgba(17, 41, 75, 0.2);
}
.mac-btn-primary:hover, .mac-btn-save:hover { background: #0d1f3a !important; color: #fff !important; }
.mac-hist { margin-top: 0.75rem; }
.mac-hist-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #11294b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 0.5rem 0;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid #e2e8f0;
}
#macHistTable { font-size: 0.8rem; }
#macHistTable thead th {
    background: #11294b;
    color: #fff;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border: none;
    font-weight: 600;
    padding: 0.4rem 0.5rem;
    white-space: nowrap;
}
#macHistTable tbody td { border-color: #e2e8f0; vertical-align: middle; }
#macHistTable tbody tr:hover { background: #f8fafc; }
/* Same card treatment as sale-invoice summary column */
.metal-conversion-page .summary-panel,
.mac-sb-sticky.summary-panel {
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    padding: 1rem;
    border-radius: 10px;
    font-size: 0.8rem;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: box-shadow 0.2s;
}
.metal-conversion-page .summary-panel:hover,
.mac-sb-sticky.summary-panel:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
.mac-sb-sticky .summary-section { margin-bottom: 0.5rem; padding-bottom: 0.5rem; }
.mac-suggest { position: absolute; z-index: 2000; left: 0; right: 0; top: 100%; max-height: 220px; overflow: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.mac-suggest button { display: block; width: 100%; text-align: left; font-size: 12px; padding: 7px 10px; border: 0; background: #fff; cursor: pointer; }
.mac-suggest button:hover { background: #eff6ff; }
</style>
<script>
(function() {
  var MacDir = <?php echo json_encode($mac_dir); ?>;
  var isMta = <?php echo $is_mta ? 'true' : 'false'; ?>;
  var effBid = <?php echo (int) $mac_effective_bid; ?>;
  var macPreloadId = <?php echo (int) $mac_preload_id; ?>;
  var macLastSavedId = 0;
  /** SweetAlert (bootstrap-sweetalert) for errors, fallback to alert. */
  function macSwalError(msg, title) {
    if (typeof swal === 'function') {
      swal({
        title: title || 'Error',
        text: msg,
        type: 'error',
        confirmButtonText: 'OK',
        closeOnClickOutside: true
      });
    } else {
      window.alert((title ? (title + '\n\n') : '') + (msg || ''));
    }
  }
  var EPS_M = 0.0001;
  var EPS_A = 0.01;
  /** Signed balance: negative = customer has credit (metal/amount in their favour). */
  function macAvailableFromSigned(signed) {
    var b = parseFloat(signed) || 0;
    if (b < 0) return -b;
    return b;
  }
  function macBalanceOkForMta(metalKey, wNeed) {
    if (!(wNeed > 0) || isNaN(wNeed)) return true;
    if (metalKey === 'platinum') {
      return true;
    }
    var bal;
    if (metalKey === 'gold') bal = bJson.gold;
    else if (metalKey === 'silver') bal = bJson.silver;
    else if (metalKey === 'diamond') bal = bJson.diamond;
    else bal = bJson.gold;
    return macAvailableFromSigned(bal) >= wNeed - EPS_M;
  }
  function macBalanceOkForAtm(amtNeed) {
    if (!(amtNeed > 0) || isNaN(amtNeed)) return true;
    return macAvailableFromSigned(bJson.amount) >= amtNeed - EPS_A;
  }
  var METAL_ORDER = [
    { key: 'gold', label: 'Gold' },
    { key: 'silver', label: 'Silver' },
    { key: 'platinum', label: 'Platinum' },
    { key: 'diamond', label: 'Diamond' }
  ];
  window.macClosePrintModal = function() {
    var el = document.getElementById('macPrintModal');
    if (el) el.style.display = 'none';
  };
  window.macConfirmPrint = function() {
    if (macLastSavedId) {
      window.open('metal-amount-conversion-print.php?id=' + encodeURIComponent(String(macLastSavedId)), '_blank');
    }
    window.macClosePrintModal();
  };

  function parseNum(v) {
    if (v === null || v === undefined) return NaN;
    v = String(v).replace(/,/g, '').trim();
    if (v === '') return NaN;
    return parseFloat(v);
  }
  function fmt2(n) {
    if (!isFinite(n)) return '0.00';
    return n.toFixed(2);
  }
  function fmt3(n) {
    if (!isFinite(n)) return '0.000';
    return n.toFixed(3);
  }
  var csTimer = null;
  var sbox = document.getElementById('macCustomerSearch');
  var sdiv = document.getElementById('macCustomerSuggest');
  var macMetalsList = [];
  var macMetalIdByLedger = {};
  var macItemsReq = 0;
  var bJson = { amount:0, gold:0, silver:0, diamond:0, gemstone:0, platinum:0 };

  /** 401 from auragold_require_login_or_exit → go to login */
  function macFetchJson(url, init) {
    return fetch(url, init || { credentials: 'same-origin' }).then(function(r) {
      if (r.status === 401) {
        return r.text().then(function(t) {
          var loc = 'index.php';
          try {
            var d = JSON.parse(t);
            if (d && d.redirect) loc = d.redirect;
          } catch (e) { /* use index.php */ }
          window.location.href = loc;
          return Promise.reject(new Error('auth'));
        });
      }
      return r.json();
    });
  }

  function setPbText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }
  function applyPreviousBalanceToPanel() {
    setPbText('previousBalanceAmount', fmt2(bJson.amount));
    setPbText('previousBalanceGold', fmt3(bJson.gold));
    setPbText('previousBalanceSilver', fmt3(bJson.silver));
    setPbText('previousBalanceDiamond', fmt3(bJson.diamond));
    setPbText('previousBalanceGemstone', fmt3(bJson.gemstone));
    var map = { previousBalanceAmount: 'data-original-balance', previousBalanceGold: 'data-original-gold', previousBalanceSilver: 'data-original-silver', previousBalanceDiamond: 'data-original-diamond', previousBalanceGemstone: 'data-original-gemstone' };
    Object.keys(map).forEach(function(ek) {
      var n = document.getElementById(ek);
      if (n) n.setAttribute(map[ek], n.textContent);
    });
  }
  function getMetalRadio() {
    return document.querySelector('input[name=macMetal]:checked');
  }
  /** Ledger family for save API: gold|silver|diamond|platinum */
  function getMetal() {
    var m = getMetalRadio();
    if (!m) return 'gold';
    var lk = m.getAttribute('data-ledger-key');
    return (lk && lk.length) ? lk : 'gold';
  }
  function headMetalForUi() {
    var k = getMetal();
    if (k === 'gold') return bJson.gold;
    if (k === 'silver') return bJson.silver;
    if (k === 'diamond') return bJson.diamond;
    if (k === 'platinum') return bJson.platinum;
    return 0;
  }
  function onMetalChange() {
    refreshHeadMetal();
    updateHelper();
    loadAgainstItems();
    var el = getMetalRadio();
    var sub = document.getElementById('macRateSub');
    var rateEl = document.getElementById('macRate');
    if (!el || !rateEl) {
      if (sub) sub.textContent = '';
      return;
    }
    var lk = el.value;
    var m = null;
    for (var i = 0; i < macMetalsList.length; i++) {
      if (macMetalsList[i].ledger_key === lk) { m = macMetalsList[i]; break; }
    }
    if (m) {
      if (m.rate != null && m.rate > 0) {
        rateEl.value = String(m.rate);
      } else {
        rateEl.value = '';
      }
      if (sub) {
        var t = m.carat_label ? String(m.carat_label) : '';
        if (m.rate_updated_at) {
          t += t ? ' · ' : '';
          t += String(m.rate_updated_at).replace('T', ' ').slice(0, 16);
        }
        if (!t && m.dashboard_key) {
          t = (m.rate == null || m.rate <= 0) ? 'No dashboard rate' : '';
        }
        sub.textContent = t;
      }
    } else {
      if (sub) sub.textContent = '';
    }
  }
  function resolveMetalIdForLedger(ledgerKey) {
    if (macMetalIdByLedger[ledgerKey]) {
      return macMetalIdByLedger[ledgerKey];
    }
    return 0;
  }
  var METAL_ITEM_KEYS = ['gold', 'silver', 'platinum', 'diamond'];
  function loadAgainstItems(selectedValue) {
    var wrap = document.getElementById('macAgainstItemWrap');
    var sel = document.getElementById('macAgainstItem');
    if (!wrap || !sel) return;
    var lk = getMetal();
    if (METAL_ITEM_KEYS.indexOf(lk) === -1) {
      wrap.classList.add('d-none');
      sel.innerHTML = '<option value="">— Select item —</option>';
      sel.value = '';
      return;
    }
    var mid = resolveMetalIdForLedger(lk);
    if (!mid) {
      wrap.classList.remove('d-none');
      sel.innerHTML = '<option value="">— No ' + lk + ' master —</option>';
      return;
    }
    wrap.classList.remove('d-none');
    sel.innerHTML = '<option value="">Loading…</option>';
    sel.disabled = true;
    var reqId = ++macItemsReq;
    macFetchJson('ajax/get-products-by-metal.php?metal_id=' + encodeURIComponent(String(mid)), { credentials: 'same-origin' })
      .then(function(d) {
        if (reqId !== macItemsReq) return;
        var opts = '<option value="">— Select item —</option>';
        var list = (d && d.success && d.products) ? d.products : [];
        list.forEach(function(p) {
          var pid = parseInt(p.id, 10) || 0;
          var pcid = parseInt(p.characteristic_id, 10) || 0;
          if (pid <= 0) return;
          var label = String(p.name || 'Item');
          if (p.article) label += ' · ' + p.article;
          if (p.sku_code) label += ' [' + p.sku_code + ']';
          var val = pid + '|' + pcid;
          opts += '<option value="' + val + '">' + label.replace(/</g, '&lt;').replace(/"/g, '&quot;') + '</option>';
        });
        sel.innerHTML = opts;
        sel.disabled = false;
        if (selectedValue) {
          sel.value = selectedValue;
        }
      })
      .catch(function() {
        if (reqId !== macItemsReq) return;
        sel.innerHTML = '<option value="">— Could not load items —</option>';
        sel.disabled = false;
      });
  }
  function loadMetalMasters() {
    var wrap = document.getElementById('macMetalRadios');
    if (!wrap) return;
    var u = 'ajax/metal-conversion-masters.php' + (effBid > 0 ? ('?branch_id=' + encodeURIComponent(effBid)) : '');
    macFetchJson(u, { credentials: 'same-origin' })
      .then(function(d) {
        if (!d || d.status !== 'success') {
          wrap.innerHTML = '<span class="text-danger small">Could not load rates</span>';
          return;
        }
        var lb = d.latest_by_key || {};
        macMetalIdByLedger = {};
        (d.metals || []).forEach(function(row) {
          var rid = parseInt(row.id, 10) || 0;
          if (rid <= 0) return;
          var keys = [];
          if (row.ledger_key) keys.push(String(row.ledger_key));
          if (row.dashboard_key) keys.push(String(row.dashboard_key));
          keys.forEach(function(lk) {
            if (lk && !macMetalIdByLedger[lk]) {
              macMetalIdByLedger[lk] = rid;
            }
          });
        });
        macMetalsList = METAL_ORDER.map(function(def) {
          var rr = lb[def.key];
          if (!rr || typeof rr !== 'object') {
            return { id: 0, label: def.label, ledger_key: def.key, dashboard_key: def.key, rate: 0, carat_label: '', rate_updated_at: null };
          }
          return {
            id: 0,
            label: def.label,
            ledger_key: def.key,
            dashboard_key: def.key,
            rate: (rr.rate != null && !isNaN(parseFloat(rr.rate))) ? parseFloat(rr.rate) : 0,
            carat_label: (rr.carat_label != null && String(rr.carat_label) !== '') ? String(rr.carat_label) : '',
            rate_updated_at: rr.updated_at != null ? rr.updated_at : null
          };
        });
        wrap.innerHTML = '';
        var firstInp = null;
        macMetalsList.forEach(function(row) {
          var label = document.createElement('label');
          label.className = 'mac-radio';
          var inp = document.createElement('input');
          inp.type = 'radio';
          inp.name = 'macMetal';
          inp.value = String(row.ledger_key);
          inp.setAttribute('data-ledger-key', String(row.ledger_key));
          inp.addEventListener('change', onMetalChange);
          label.appendChild(inp);
          label.appendChild(document.createTextNode(' '));
          var s = document.createElement('span');
          s.textContent = row.label;
          label.appendChild(s);
          wrap.appendChild(label);
          if (!firstInp) firstInp = inp;
        });
        if (firstInp) {
          firstInp.checked = true;
          onMetalChange();
        } else {
          loadAgainstItems();
        }
        if (macPreloadId > 0) {
          tryPreloadEdit();
        }
      })
      .catch(function() {
        var w = document.getElementById('macMetalRadios');
        if (w) w.innerHTML = '<span class="text-danger small">Request failed (rates)</span>';
      });
  }
  function refreshHeadMetal() {
    var h = document.getElementById('macHeadMetalBal');
    if (h) h.value = fmt3(headMetalForUi());
  }

  function loadBalance() {
    var id = document.getElementById('macCustomerId').value;
    if (id < 1) {
      bJson = { amount:0, gold:0, silver:0, diamond:0, gemstone:0, platinum:0 };
      setPbText('previousBalanceAmount', '—');
      setPbText('previousBalanceGold', '—');
      setPbText('previousBalanceSilver', '—');
      setPbText('previousBalanceDiamond', '—');
      setPbText('previousBalanceGemstone', '—');
      refreshHeadMetal();
      return;
    }
    var u = 'ajax/get-customer-balance.php?customer_id=' + encodeURIComponent(id) + (effBid>0?('&branch_id=' + encodeURIComponent(effBid)):'');
    macFetchJson(u, { credentials: 'same-origin' })
      .then(function(j) {
        if (j && j.status === 'success' && j.balance) {
          var b = j.balance;
          bJson.amount = parseFloat(b.amount) || 0;
          bJson.gold = parseFloat(b.gold) || 0;
          bJson.silver = parseFloat(b.silver) || 0;
          bJson.diamond = parseFloat(b.diamond) || 0;
          bJson.gemstone = parseFloat(b.gemstone) || 0;
          bJson.platinum = parseFloat(b.platinum) || 0;
        } else {
          bJson = { amount:0, gold:0, silver:0, diamond:0, gemstone:0, platinum:0 };
        }
        applyPreviousBalanceToPanel();
        refreshHeadMetal();
        loadHistory();
      })
      .catch(function() {
        bJson = { amount:0, gold:0, silver:0, diamond:0, gemstone:0, platinum:0 };
        applyPreviousBalanceToPanel();
      });
  }

  function loadHistory() {
    var id = document.getElementById('macCustomerId').value;
    if (id < 1) {
      var tb = document.querySelector('#macHistTable tbody');
      if (tb) tb.innerHTML = '<tr class="text-muted text-center no-row"><td colspan="7" class="py-3">No records</td></tr>';
      return;
    }
    var u = 'ajax/metal-amount-conversion-list.php?customer_id=' + encodeURIComponent(id) + '&direction=' + encodeURIComponent(MacDir);
    macFetchJson(u, { credentials: 'same-origin' })
      .then(function(d) {
        var tb = document.querySelector('#macHistTable tbody');
        if (!tb) return;
        var rows = (d && d.rows) ? d.rows : [];
        if (!rows.length) {
          tb.innerHTML = '<tr class="text-muted text-center no-row"><td colspan="7" class="py-3">No records</td></tr>';
          return;
        }
        var h = rows.map(function(r) {
          var t = (r.trans_date && r.trans_date.replace) ? r.trans_date.replace('T', ' ').replace('.000Z','') : (r.trans_date || '');
          return '<tr><td><small>' + t + '</small></td><td class="d-none d-md-table-cell">' + (r.trans_no||'') + '</td><td>' + (r.metal_type||'') + '</td><td class="text-right">' + fmt3(parseNum(r.metal_weight)) + '</td><td class="text-right d-none d-md-table-cell">' + fmt2(parseNum(r.rate)) + '</td><td class="text-right">' + fmt2(parseNum(r.amount)) + '</td><td><small>' + (function() {
            var c = r.comment ? String(r.comment).replace(/</g,'&lt;') : '';
            if (r.against_item_label) {
              var ai = String(r.against_item_label).replace(/</g,'&lt;');
              c = c ? (c + ' · ' + ai) : ai;
            }
            return c;
          })() + '</small></td></tr>';
        }).join('');
        tb.innerHTML = h;
      });
  }
  setPbText('previousBalanceAmount', '—');
  setPbText('previousBalanceGold', '—');
  setPbText('previousBalanceSilver', '—');
  setPbText('previousBalanceDiamond', '—');
  setPbText('previousBalanceGemstone', '—');

  sbox && sbox.addEventListener('input', function() {
    if (sdiv) sdiv.classList.add('d-none');
    if (sbox.value.trim().length < 2) return;
    clearTimeout(csTimer);
    csTimer = setTimeout(function() {
      var q = encodeURIComponent(sbox.value.trim());
      macFetchJson('ajax/search-customers.php?q=' + q, { credentials: 'same-origin' })
        .then(function(j) {
          var list = (j && j.customers) ? j.customers : [];
          if (!sdiv) return;
          sdiv.innerHTML = '';
          (list || []).forEach(function(c) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = c.display_text || c.name;
            b.addEventListener('click', function() {
              sbox.value = c.name;
              document.getElementById('macCustomerId').value = c.id;
              sdiv.classList.add('d-none');
              loadBalance();
            });
            sdiv.appendChild(b);
          });
          if (list && list.length) sdiv.classList.remove('d-none');
        });
    }, 200);
  });
  document.addEventListener('click', function(e) {
    if (sdiv && sbox && !sdiv.contains(e.target) && e.target !== sbox) sdiv.classList.add('d-none');
  });
  var doc = document;
  doc.getElementById('macBtnCalc').addEventListener('click', doCalc);
  function doCalc() {
    var rate = parseNum(document.getElementById('macRate').value);
    if (!(rate>0)) { if (isMta) doc.getElementById('macAmtOut').value='0.00'; else doc.getElementById('macWtOut').value='0.000'; return; }
    if (isMta) {
      var w = parseNum(document.getElementById('macMetalW').value);
      if (isNaN(w)) { document.getElementById('macAmtOut').value='0.00'; return; }
      document.getElementById('macAmtOut').value = fmt2(w * rate);
    } else {
      var a = parseNum(document.getElementById('macAmtIn').value);
      if (isNaN(a)) { document.getElementById('macWtOut').value='0.000'; return; }
      document.getElementById('macWtOut').value = fmt3(a / rate);
    }
  }
  function tryPreloadEdit() {
    if (!macPreloadId) return;
    macFetchJson('ajax/metal-amount-conversion-get.php?id=' + encodeURIComponent(String(macPreloadId)), { credentials: 'same-origin' })
      .then(function(d) {
        if (!d || d.status !== 'success' || !d.row) return;
        var row = d.row;
        if (row.direction !== MacDir) {
          macSwalError('This voucher is for a different document type. Open the correct page from the menu or report.', 'Cannot load');
          return;
        }
        var cid = parseInt(row.customer_id, 10) || 0;
        if (cid < 1) return;
        document.getElementById('macCustomerId').value = String(cid);
        if (sbox) sbox.value = row.customer_name || '';
        var mt = String(row.metal_type || 'gold').toLowerCase();
        var radios = document.querySelectorAll('input[name=macMetal]');
        for (var i = 0; i < radios.length; i++) {
          if (String(radios[i].value).toLowerCase() === mt) {
            radios[i].checked = true;
            break;
          }
        }
        onMetalChange();
        var rv = parseNum(row.rate);
        if (isFinite(rv) && rv > 0) {
          var rateField = document.getElementById('macRate');
          if (rateField) rateField.value = String(rv);
        }
        if (isMta) {
          var wv = parseNum(row.metal_weight);
          if (isFinite(wv) && wv > 0) {
            var mwf = document.getElementById('macMetalW');
            if (mwf) mwf.value = fmt3(wv);
          }
        } else {
          var av = parseNum(row.amount);
          if (isFinite(av) && av > 0) {
            var aif = document.getElementById('macAmtIn');
            if (aif) aif.value = fmt2(av);
          }
        }
        doCalc();
        var cmf = document.getElementById('macComment');
        if (cmf && row.comment) cmf.value = String(row.comment);
        var itemVal = '';
        if (row.product_id) {
          itemVal = String(row.product_id) + '|' + String(row.product_characteristic_id || 0);
        }
        loadAgainstItems(itemVal);
        loadBalance();
      })
      .catch(function() { /* optional: silent */ });
  }
  function updateHelper() {
    var el = document.getElementById('macHelper');
    if (!el) return;
    el.textContent = '';
  }
  doc.getElementById('macSave').addEventListener('click', function() {
    var cid = parseInt(document.getElementById('macCustomerId').value, 10);
    if (cid<1) { macSwalError('Select a customer to continue.', 'Required'); return; }
    if (!getMetalRadio()) { macSwalError('Select a metal (load master list first).', 'Required'); return; }
    var rate = parseNum(document.getElementById('macRate').value);
    if (!(rate>0)) { macSwalError('Enter a valid rate (greater than zero).', 'Required'); return; }
    doCalc();
    var mKey = getMetal();
    if (isMta) {
      var w0 = parseNum(document.getElementById('macMetalW').value);
      if (!(w0>0)) { macSwalError('Enter metal weight to convert (greater than zero).', 'Required'); return; }
      if (!macBalanceOkForMta(mKey, w0)) {
        macSwalError('This customer does not have enough metal balance for the selected type. Reduce the weight or add metal to the account.', 'Insufficient balance');
        return;
      }
    } else {
      var a0 = parseNum(document.getElementById('macAmtIn').value);
      if (!(a0>0)) { macSwalError('Enter the amount to convert (greater than zero).', 'Required'); return; }
      if (!macBalanceOkForAtm(a0)) {
        macSwalError('This customer does not have enough amount balance to buy metal. Reduce the amount or receive payment on the account.', 'Insufficient balance');
        return;
      }
    }
    var fd = new FormData();
    fd.append('direction', MacDir);
    fd.append('customer_id', String(cid));
    var nm = sbox ? sbox.value : '';
    fd.append('customer_name', nm);
    fd.append('metal_type', mKey);
    var cm = (document.getElementById('macComment') && document.getElementById('macComment').value) || '';
    fd.append('comment', cm);
    fd.append('rate', String(rate));
    if (isMta) {
      fd.append('metal_weight', String(parseNum(document.getElementById('macMetalW').value)));
    } else {
      fd.append('amount', String(parseNum(document.getElementById('macAmtIn').value)));
    }
    var itemSel = document.getElementById('macAgainstItem');
    if (itemSel && itemSel.value) {
      var parts = String(itemSel.value).split('|');
      fd.append('product_id', String(parseInt(parts[0], 10) || 0));
      fd.append('product_characteristic_id', String(parseInt(parts[1], 10) || 0));
    }
    macFetchJson('ajax/save-metal-amount-conversion.php', { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(j) {
        if (j && j.status === 'success') {
          macLastSavedId = (j && j.id) ? parseInt(j.id, 10) : 0;
          var pm = document.getElementById('macPrintModalMsg');
          if (pm) {
            pm.textContent = (j && j.trans_no ? String(j.trans_no) : 'Saved') + '. Do you want to print?';
          }
          var pmodal = document.getElementById('macPrintModal');
          if (pmodal) pmodal.style.display = 'flex';
          loadBalance();
        } else {
          var errMsg = (j && j.message) ? String(j.message) : 'Error saving. Please try again.';
          if (j && (j.code === 'insufficient_metal' || j.code === 'insufficient_amount')) {
            macSwalError(errMsg, 'Insufficient balance');
          } else {
            macSwalError(errMsg, 'Cannot save');
          }
        }
      })
      .catch(function() { macSwalError('The request could not be completed. Check your connection and try again.', 'Request failed'); });
  });
  updateHelper();
  loadMetalMasters();
  loadHistory();
})();
</script>
