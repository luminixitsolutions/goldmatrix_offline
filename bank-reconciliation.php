<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
require_once __DIR__ . '/includes/auragold_bank_reconciliation.php';

auragold_require_login_or_exit();

$br_branch_id = auragold_bank_reconciliation_effective_branch_id();
$br_banks = auragold_bank_reconciliation_accounts($conn, $br_branch_id);

$br_from = isset($_GET['from_date']) ? trim((string) $_GET['from_date']) : '';
$br_to = isset($_GET['to_date']) ? trim((string) $_GET['to_date']) : '';
if ($br_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $br_from)) {
    $br_from = '';
}
if ($br_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $br_to)) {
    $br_to = '';
}

$br_active_bank = isset($_GET['bank_id']) ? (int) $_GET['bank_id'] : 0;
if ($br_active_bank <= 0 && !empty($br_banks)) {
    $br_active_bank = (int) ($br_banks[0]['id'] ?? 0);
}
?><!DOCTYPE html>
<html lang="en" class="default-style bank-recon-page">
<head>
    <title>Bank Reconciliation - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php'; ?>
<style>
html, body.bank-recon-page { height: auto; min-height: 100%; overflow-x: hidden; background: #f1f5f9; }
.bank-recon-page .layout-content {
    height: calc(100vh - 60px);
    min-height: calc(100vh - 60px);
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}
.br-shell {
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 10px 14px 32px;
    box-sizing: border-box;
}
.br-topbar {
    background: linear-gradient(90deg, #11294b 0%, #1a3a5c 100%);
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 10px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    box-shadow: 0 2px 10px rgba(17,41,75,.2);
}
.br-topbar h1 { margin: 0; font-size: 1.05rem; font-weight: 700; }
.br-topbar p { margin: 2px 0 0; font-size: .78rem; opacity: .85; }
.br-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
}
.br-filters label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: rgba(255,255,255,.85); margin: 0 0 3px; display: block; }
.br-filters input {
    height: 34px;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,.25);
    background: rgba(255,255,255,.95);
    padding: 0 10px;
    font-size: 13px;
    min-width: 140px;
}
.br-btn {
    height: 34px;
    border-radius: 6px;
    border: none;
    padding: 0 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.br-btn-apply { background: #c5a864; color: #11294b; }
.br-btn-clear { background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.3); }
.br-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(17,41,75,.04);
    overflow: visible;
    width: 100%;
}
.br-tabs {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 0 8px;
    -webkit-overflow-scrolling: touch;
}
.br-tab {
    border: none;
    background: transparent;
    padding: 10px 16px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    cursor: pointer;
}
.br-tab.active { color: #11294b; border-bottom-color: #c5a864; background: #fff; }
.br-tab:hover { color: #11294b; background: #fff9ed; }
.br-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #fff 0%, #fafbfd 100%);
}
.br-stat label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.br-stat strong { font-size: 1rem; color: #11294b; }
.br-stat strong.pos { color: #047857; }
.br-stat strong.neg { color: #b91c1c; }
.br-table-wrap { overflow-x: auto; overflow-y: visible; padding-bottom: 8px; }
.br-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.br-table thead th {
    background: #11294b;
    color: #fff;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .03em;
    padding: 8px 10px;
    border-bottom: 2px solid #c5a864;
    white-space: nowrap;
}
.br-table tbody td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
.br-table tbody tr:hover { background: #f8fafc; }
.br-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.br-table .opening-row td { background: #fff9ed; font-weight: 600; color: #11294b; }
.br-table .closing-row td { background: #f0fdf4; font-weight: 700; color: #11294b; }
.br-empty { padding: 40px 20px; text-align: center; color: #64748b; }
.br-loading { padding: 30px; text-align: center; color: #64748b; }
.br-excel-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}
.br-excel-bar .br-btn-outline {
    height: 32px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #11294b;
    padding: 0 12px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}
.br-excel-bar .br-btn-outline:hover { background: #fff9ed; border-color: #c5a864; }
.br-excel-bar .br-btn-primary {
    height: 32px;
    border-radius: 6px;
    border: none;
    background: #11294b;
    color: #fff;
    padding: 0 12px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}
.br-excel-bar .br-btn-primary:hover { background: #1a3a5c; }
.br-excel-hint { font-size: 11px; color: #64748b; margin-left: auto; }
.br-view-tabs {
    display: flex;
    gap: 0;
    padding: 0 14px;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}
.br-view-tab {
    border: none;
    background: transparent;
    padding: 8px 14px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: #64748b;
    border-bottom: 2px solid transparent;
    cursor: pointer;
}
.br-view-tab.active { color: #11294b; border-bottom-color: #c5a864; }
.br-match-panel { display: none; }
.br-match-panel.visible { display: block; }
.br-match-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
}
.br-match-summary .br-stat strong.ok { color: #047857; }
.br-match-summary .br-stat strong.warn { color: #b45309; }
.br-match-summary .br-stat strong.bad { color: #b91c1c; }
.br-match-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 14px 0;
}
.br-match-tab {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
}
.br-match-tab.active { background: #11294b; color: #fff; border-color: #11294b; }
.br-match-table-wrap { padding: 10px 14px 14px; }
.br-alert {
    margin: 10px 14px 0;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
}
.br-software-panel.hidden { display: none; }
.br-btn-add-missing {
    border: none;
    background: #11294b;
    color: #fff;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.br-btn-add-missing:hover { background: #1a3a5c; }
.br-btn-add-missing:disabled {
    opacity: .45;
    cursor: not-allowed;
}
.br-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1050;
    background: rgba(15, 23, 42, .45);
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.br-modal-backdrop.open { display: flex; }
.br-modal {
    width: 100%;
    max-width: 480px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, .25);
    overflow: hidden;
}
.br-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: #11294b;
    color: #fff;
}
.br-modal-head h3 { margin: 0; font-size: 15px; font-weight: 700; }
.br-modal-close {
    border: none;
    background: transparent;
    color: #fff;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    opacity: .85;
}
.br-modal-body { padding: 16px; }
.br-modal-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.br-modal-grid .full { grid-column: 1 / -1; }
.br-modal-grid label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 4px;
}
.br-modal-grid input,
.br-modal-grid select {
    width: 100%;
    height: 36px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 0 10px;
    font-size: 13px;
    box-sizing: border-box;
}
.br-modal-hint {
    margin: 10px 0 0;
    font-size: 12px;
    color: #64748b;
    line-height: 1.4;
}
.br-modal-foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px 16px;
    border-top: 1px solid #e2e8f0;
}
.br-modal-foot .br-btn-outline {
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.br-modal-foot .br-btn-primary {
    border: none;
    background: #11294b;
    color: #fff;
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.br-modal-foot .br-btn-primary:disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 991px) {
    .br-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 576px) {
    .br-summary { grid-template-columns: 1fr; }
    .br-modal-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body class="bank-recon-page">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
<div class="br-shell">

    <div class="br-topbar">
        <div>
            <h1><i class="feather icon-refresh-cw"></i> Bank Reconciliation</h1>
            <p>Compare GoldMatrix bank ledger with uploaded bank statement Excel and find missing transactions.</p>
        </div>
        <div class="br-filters">
            <div>
                <label for="brFromDate">From Date</label>
                <input type="date" id="brFromDate" value="<?php echo htmlspecialchars($br_from, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div>
                <label for="brToDate">To Date</label>
                <input type="date" id="brToDate" value="<?php echo htmlspecialchars($br_to, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <button type="button" class="br-btn br-btn-apply" id="brApplyFilters">Apply</button>
            <button type="button" class="br-btn br-btn-clear" id="brClearFilters">Clear</button>
        </div>
    </div>

    <div class="br-card">
        <?php if (empty($br_banks)): ?>
            <div class="br-empty">
                <p>No bank accounts found for this branch.</p>
                <p class="small mb-0">Create bank ledgers under Ledger Opening with group <strong>Bank Account</strong>.</p>
            </div>
        <?php else: ?>
            <div class="br-tabs" id="brBankTabs" role="tablist">
                <?php foreach ($br_banks as $bank): ?>
                    <?php
                    $bid = (int) ($bank['id'] ?? 0);
                    $is_active = ($bid === $br_active_bank);
                    ?>
                    <button type="button"
                            class="br-tab<?php echo $is_active ? ' active' : ''; ?>"
                            role="tab"
                            data-bank-id="<?php echo $bid; ?>"
                            data-bank-name="<?php echo htmlspecialchars((string) ($bank['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) ($bank['name'] ?? 'Bank'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="brTabPanel">
                <div class="br-excel-bar">
                    <button type="button" class="br-btn-outline" id="brDownloadSample"><i class="feather icon-download"></i> Sample Excel</button>
                    <button type="button" class="br-btn-outline" id="brExportSoftware"><i class="feather icon-file-text"></i> Export Software Statement</button>
                    <button type="button" class="br-btn-primary" id="brImportBank"><i class="feather icon-upload"></i> Import Bank Statement</button>
                    <input type="file" id="brImportFile" accept=".xlsx,.xls" style="display:none">
                    <span class="br-excel-hint">Upload bank Excel (Date, Reference, Withdrawal, Deposit) to match with software ledger.</span>
                </div>

                <div class="br-view-tabs">
                    <button type="button" class="br-view-tab active" data-view="software">Software Ledger</button>
                    <button type="button" class="br-view-tab" data-view="match" id="brViewMatchTab">Reconciliation Match</button>
                </div>

                <div id="brSoftwarePanel" class="br-software-panel">
                    <div class="br-summary" id="brSummary">
                        <div class="br-stat"><label>Opening Balance</label><strong id="brOpening">â€”</strong></div>
                        <div class="br-stat"><label>Total Debit</label><strong id="brDebit" class="pos">â€”</strong></div>
                        <div class="br-stat"><label>Total Credit</label><strong id="brCredit" class="neg">â€”</strong></div>
                        <div class="br-stat"><label>Closing Balance</label><strong id="brClosing">â€”</strong></div>
                    </div>
                    <div class="br-table-wrap">
                        <table class="br-table" id="brTxnTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Voucher No</th>
                                    <th>Type</th>
                                    <th>Against</th>
                                    <th>Description</th>
                                    <th class="num">Debit</th>
                                    <th class="num">Credit</th>
                                    <th class="num">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="brTxnBody">
                                <tr><td colspan="8" class="br-loading">Loading transactionsâ€¦</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="brMatchPanel" class="br-match-panel">
                    <div id="brMatchAlert" class="br-alert" style="display:none"></div>
                    <div class="br-match-summary">
                        <div class="br-stat"><label>Bank Rows</label><strong id="brMatchBankTotal">0</strong></div>
                        <div class="br-stat"><label>Matched</label><strong id="brMatchMatched" class="ok">0</strong></div>
                        <div class="br-stat"><label>Missing in Software</label><strong id="brMatchMissingSw" class="bad">0</strong></div>
                        <div class="br-stat"><label>Missing in Bank Stmt</label><strong id="brMatchMissingBk" class="warn">0</strong></div>
                    </div>
                    <div class="br-match-tabs">
                        <button type="button" class="br-match-tab active" data-match="matched">Matched</button>
                        <button type="button" class="br-match-tab" data-match="missing_software">Missing in Software</button>
                        <button type="button" class="br-match-tab" data-match="missing_bank">Missing in GoldMatrix</button>
                    </div>
                    <div class="br-match-table-wrap">
                        <div class="br-table-wrap">
                            <table class="br-table" id="brMatchTable">
                                <thead id="brMatchHead"></thead>
                                <tbody id="brMatchBody">
                                    <tr><td colspan="6" class="br-empty">Import a bank statement Excel to see match results.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>
</div>

<div class="br-modal-backdrop" id="brAddMissingModal" aria-hidden="true">
    <div class="br-modal" role="dialog" aria-modal="true" aria-labelledby="brAddMissingTitle">
        <div class="br-modal-head">
            <h3 id="brAddMissingTitle">Add Missing to Software Ledger</h3>
            <button type="button" class="br-modal-close" id="brAddMissingClose" aria-label="Close">&times;</button>
        </div>
        <div class="br-modal-body">
            <div class="br-modal-grid">
                <div>
                    <label for="brAddDate">Date</label>
                    <input type="date" id="brAddDate">
                </div>
                <div>
                    <label for="brAddAmount">Amount</label>
                    <input type="text" id="brAddAmount" readonly>
                </div>
                <div>
                    <label for="brAddSide">Bank Effect</label>
                    <input type="text" id="brAddSide" readonly>
                </div>
                <div>
                    <label for="brAddRef">Reference</label>
                    <input type="text" id="brAddRef">
                </div>
                <div class="full">
                    <label for="brAddDesc">Description</label>
                    <input type="text" id="brAddDesc">
                </div>
                <div class="full">
                    <label for="brAddAgainst">Opposite Ledger *</label>
                    <input type="text" id="brAddAgainst" list="brLedgerList" placeholder="Search ledger (e.g. Bank Charges, Cash)" autocomplete="off">
                    <datalist id="brLedgerList"></datalist>
                </div>
            </div>
            <p class="br-modal-hint">Creates a journal voucher: bank deposit â†’ Debit bank / Credit opposite ledger; withdrawal â†’ Credit bank / Debit opposite ledger.</p>
        </div>
        <div class="br-modal-foot">
            <button type="button" class="br-btn-outline" id="brAddMissingCancel">Cancel</button>
            <button type="button" class="br-btn-primary" id="brAddMissingSave">Add to Ledger</button>
        </div>
    </div>
</div>


<?php if (!empty($br_banks)): ?>
<script>
(function () {
    var branchId = <?php echo (int) $br_branch_id; ?>;
    var activeBankId = <?php echo (int) $br_active_bank; ?>;
    var reqToken = 0;
    var lastMatchData = null;
    var activeMatchView = 'matched';
    var pendingMissIdx = -1;
    var ledgerListLoaded = false;

    function fmtAmt(n) {
        var v = parseFloat(n);
        if (!isFinite(v)) v = 0;
        return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fmtBal(n) {
        if (Math.abs(parseFloat(n) || 0) < 0.005) return '0.00';
        return fmtAmt(n);
    }
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }
    function getFilters() {
        return {
            from: (document.getElementById('brFromDate') || {}).value || '',
            to: (document.getElementById('brToDate') || {}).value || ''
        };
    }
    function getActiveBankName() {
        var tab = document.querySelector('#brBankTabs .br-tab.active');
        return tab ? String(tab.getAttribute('data-bank-name') || tab.textContent || '').trim() : '';
    }
    function setPageView(view) {
        document.querySelectorAll('.br-view-tab').forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-view') === view);
        });
        var sw = document.getElementById('brSoftwarePanel');
        var mp = document.getElementById('brMatchPanel');
        if (sw) sw.classList.toggle('hidden', view !== 'software');
        if (mp) mp.classList.toggle('visible', view === 'match');
    }
    function setSummary(d) {
        var set = function (id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        set('brOpening', fmtBal(d.opening));
        set('brDebit', fmtAmt(d.total_debit));
        set('brCredit', fmtAmt(d.total_credit));
        set('brClosing', fmtBal(d.closing));
    }
    function renderRows(d) {
        var tbody = document.getElementById('brTxnBody');
        if (!tbody) return;
        var rows = (d && d.rows) ? d.rows : [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="br-empty">No transactions in this period.</td></tr>';
            return;
        }
        var html = '<tr class="opening-row"><td colspan="5">Opening Balance</td><td class="num">—</td><td class="num">—</td><td class="num">' + fmtBal(d.opening) + '</td></tr>';
        rows.forEach(function (r) {
            var dt = r.date ? String(r.date).replace('T', ' ').slice(0, 16) : '';
            var against = r.against_ledger || r.against_invoice_no || '';
            html += '<tr>'
                + '<td><small>' + esc(dt) + '</small></td>'
                + '<td>' + esc(r.voucher_no) + '</td>'
                + '<td>' + esc(r.voucher_type) + '</td>'
                + '<td><small>' + esc(against) + '</small></td>'
                + '<td><small>' + esc(r.description) + '</small></td>'
                + '<td class="num">' + (parseFloat(r.debit) > 0 ? fmtAmt(r.debit) : '—') + '</td>'
                + '<td class="num">' + (parseFloat(r.credit) > 0 ? fmtAmt(r.credit) : '—') + '</td>'
                + '<td class="num">' + fmtBal(r.balance) + '</td>'
                + '</tr>';
        });
        html += '<tr class="closing-row"><td colspan="5">Closing Balance</td><td class="num">' + fmtAmt(d.total_debit) + '</td><td class="num">' + fmtAmt(d.total_credit) + '</td><td class="num">' + fmtBal(d.closing) + '</td></tr>';
        tbody.innerHTML = html;
    }
    function updateMatchCounts() {
        if (!lastMatchData) return;
        var c = lastMatchData.counts || {};
        var set = function (id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = String(val || 0);
        };
        set('brMatchBankTotal', c.bank_total);
        set('brMatchMatched', c.matched);
        set('brMatchMissingSw', c.missing_in_software);
        set('brMatchMissingBk', c.missing_in_bank);
    }
    function renderMatchTable() {
        var head = document.getElementById('brMatchHead');
        var body = document.getElementById('brMatchBody');
        if (!head || !body) return;
        if (!lastMatchData) {
            head.innerHTML = '';
            body.innerHTML = '<tr><td colspan="8" class="br-empty">Import a bank statement Excel to see match results.</td></tr>';
            return;
        }
        var html = '';
        if (activeMatchView === 'matched') {
            head.innerHTML = '<tr><th>Date</th><th>Bank Ref</th><th>Bank Description</th><th>Bank Amt</th><th>Software Voucher</th><th class="num">Software Dr</th><th class="num">Software Cr</th></tr>';
            var rows = lastMatchData.matched || [];
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="7" class="br-empty">No matched transactions.</td></tr>';
                return;
            }
            rows.forEach(function (m) {
                var b = m.bank || {};
                var s = m.software || {};
                var bAmt = (parseFloat(b.deposit) > 0) ? ('Dep ' + fmtAmt(b.deposit)) : ((parseFloat(b.withdrawal) > 0) ? ('Wd ' + fmtAmt(b.withdrawal)) : '—');
                html += '<tr>'
                    + '<td>' + esc(b.date) + '</td>'
                    + '<td>' + esc(b.reference) + '</td>'
                    + '<td><small>' + esc(b.description) + '</small></td>'
                    + '<td class="num">' + esc(bAmt) + '</td>'
                    + '<td>' + esc(s.voucher_no) + '</td>'
                    + '<td class="num">' + (parseFloat(s.debit) > 0 ? fmtAmt(s.debit) : '—') + '</td>'
                    + '<td class="num">' + (parseFloat(s.credit) > 0 ? fmtAmt(s.credit) : '—') + '</td>'
                    + '</tr>';
            });
            body.innerHTML = html;
            return;
        }
        if (activeMatchView === 'missing_software') {
            head.innerHTML = '<tr><th>Date</th><th>Reference</th><th>Description</th><th class="num">Withdrawal</th><th class="num">Deposit</th><th>Action</th></tr>';
            var miss = lastMatchData.missing_in_software || [];
            if (!miss.length) {
                body.innerHTML = '<tr><td colspan="6" class="br-empty">No missing entries — all bank rows matched in software.</td></tr>';
                return;
            }
            miss.forEach(function (r, idx) {
                var dep = parseFloat(r.deposit) || 0;
                var wd = parseFloat(r.withdrawal) || 0;
                var canAdd = (dep > 0.009 || wd > 0.009) && !!r.date;
                var btn = canAdd
                    ? '<button type="button" class="br-btn-add-missing" data-miss-idx="' + idx + '">Add to Ledger</button>'
                    : '<button type="button" class="br-btn-add-missing" disabled title="Date and amount required">Add to Ledger</button>';
                html += '<tr>'
                    + '<td>' + esc(r.date) + '</td>'
                    + '<td>' + esc(r.reference) + '</td>'
                    + '<td><small>' + esc(r.description) + '</small></td>'
                    + '<td class="num">' + (wd > 0 ? fmtAmt(wd) : '—') + '</td>'
                    + '<td class="num">' + (dep > 0 ? fmtAmt(dep) : '—') + '</td>'
                    + '<td>' + btn + '</td>'
                    + '</tr>';
            });
            body.innerHTML = html;
            return;
        }
        head.innerHTML = '<tr><th>Date</th><th>Voucher No</th><th>Type</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th></tr>';
        var missBk = lastMatchData.missing_in_bank || [];
        if (!missBk.length) {
            body.innerHTML = '<tr><td colspan="6" class="br-empty">No missing entries — all software rows found in bank statement.</td></tr>';
            return;
        }
        missBk.forEach(function (r) {
            html += '<tr>'
                + '<td>' + esc(String(r.date || '').slice(0, 10)) + '</td>'
                + '<td>' + esc(r.voucher_no) + '</td>'
                + '<td>' + esc(r.voucher_type) + '</td>'
                + '<td><small>' + esc(r.description) + '</small></td>'
                + '<td class="num">' + (parseFloat(r.debit) > 0 ? fmtAmt(r.debit) : '—') + '</td>'
                + '<td class="num">' + (parseFloat(r.credit) > 0 ? fmtAmt(r.credit) : '—') + '</td>'
                + '</tr>';
        });
        body.innerHTML = html;
    }
    function showMatchResults(data) {
        lastMatchData = data;
        updateMatchCounts();
        var alertEl = document.getElementById('brMatchAlert');
        if (alertEl) {
            var warns = data.parse_warnings || [];
            if (warns.length) {
                alertEl.style.display = 'block';
                alertEl.textContent = warns.join(' ');
            } else {
                alertEl.style.display = 'none';
                alertEl.textContent = '';
            }
        }
        activeMatchView = 'matched';
        document.querySelectorAll('.br-match-tab').forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-match') === 'matched');
        });
        renderMatchTable();
        setPageView('match');
    }
    function closeAddMissingModal() {
        var modal = document.getElementById('brAddMissingModal');
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
        pendingMissIdx = -1;
    }
    function openAddMissingModal(idx) {
        if (!lastMatchData || !lastMatchData.missing_in_software) return;
        var row = lastMatchData.missing_in_software[idx];
        if (!row) return;
        pendingMissIdx = idx;
        var dep = parseFloat(row.deposit) || 0;
        var wd = parseFloat(row.withdrawal) || 0;
        var amt = dep > 0.009 ? dep : wd;
        document.getElementById('brAddDate').value = row.date || '';
        document.getElementById('brAddAmount').value = fmtAmt(amt);
        document.getElementById('brAddSide').value = dep > 0.009 ? 'Deposit (Debit bank)' : 'Withdrawal (Credit bank)';
        document.getElementById('brAddRef').value = row.reference || '';
        document.getElementById('brAddDesc').value = row.description || '';
        document.getElementById('brAddAgainst').value = '';
        loadLedgerOptions();
        var modal = document.getElementById('brAddMissingModal');
        if (modal) {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }
        setTimeout(function () {
            var ag = document.getElementById('brAddAgainst');
            if (ag) ag.focus();
        }, 40);
    }
    function loadLedgerOptions() {
        if (ledgerListLoaded) return;
        fetch('ajax/search-ledger-accounts.php?all=1', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var dl = document.getElementById('brLedgerList');
                if (!dl) return;
                dl.innerHTML = '';
                var rows = (d && d.ledgers) ? d.ledgers : (Array.isArray(d) ? d : []);
                rows.forEach(function (item) {
                    var name = typeof item === 'string' ? item : (item.name || item.display_text || '');
                    if (!name) return;
                    var opt = document.createElement('option');
                    opt.value = name;
                    dl.appendChild(opt);
                });
                ledgerListLoaded = true;
            })
            .catch(function () { /* ignore */ });
    }
    function saveMissingToLedger() {
        if (pendingMissIdx < 0 || !lastMatchData) return;
        var row = lastMatchData.missing_in_software[pendingMissIdx];
        if (!row) return;
        var against = String((document.getElementById('brAddAgainst') || {}).value || '').trim();
        if (!against) {
            alert('Please select the opposite ledger.');
            return;
        }
        var bankName = getActiveBankName();
        if (bankName && against.toLowerCase() === bankName.toLowerCase()) {
            alert('Opposite ledger must be different from the bank account.');
            return;
        }
        var dateVal = String((document.getElementById('brAddDate') || {}).value || '').trim();
        if (!dateVal) {
            alert('Date is required.');
            return;
        }
        var dep = parseFloat(row.deposit) || 0;
        var wd = parseFloat(row.withdrawal) || 0;
        var saveBtn = document.getElementById('brAddMissingSave');
        if (saveBtn) saveBtn.disabled = true;

        var fd = new FormData();
        fd.append('bank_id', String(activeBankId));
        if (branchId > 0) fd.append('branch_id', String(branchId));
        fd.append('date', dateVal);
        fd.append('reference', String((document.getElementById('brAddRef') || {}).value || '').trim());
        fd.append('description', String((document.getElementById('brAddDesc') || {}).value || '').trim());
        fd.append('against_ledger', against);
        fd.append('deposit', String(dep > 0.009 ? dep : 0));
        fd.append('withdrawal', String(wd > 0.009 ? wd : 0));

        fetch('ajax/add-bank-reconciliation-missing.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (saveBtn) saveBtn.disabled = false;
                if (!d || d.status !== 'success') {
                    alert((d && d.message) ? d.message : 'Could not add entry.');
                    return;
                }
                lastMatchData.missing_in_software.splice(pendingMissIdx, 1);
                if (!lastMatchData.counts) lastMatchData.counts = {};
                lastMatchData.counts.missing_in_software = lastMatchData.missing_in_software.length;
                updateMatchCounts();
                closeAddMissingModal();
                renderMatchTable();
                loadBank(activeBankId);
                alert('Added to software ledger' + (d.voucher_no ? (' as ' + d.voucher_no) : '') + '.');
            })
            .catch(function () {
                if (saveBtn) saveBtn.disabled = false;
                alert('Request failed. Please try again.');
            });
    }
    function loadBank(bankId) {
        if (!bankId) return;
        activeBankId = bankId;
        lastMatchData = null;
        var f = getFilters();
        var myReq = ++reqToken;
        var tbody = document.getElementById('brTxnBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="br-loading">Loading transactions…</td></tr>';

        var qs = 'ajax/bank-reconciliation-data.php?bank_id=' + encodeURIComponent(String(bankId));
        if (branchId > 0) qs += '&branch_id=' + encodeURIComponent(String(branchId));
        if (f.from) qs += '&from_date=' + encodeURIComponent(f.from);
        if (f.to) qs += '&to_date=' + encodeURIComponent(f.to);

        fetch(qs, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (myReq !== reqToken) return;
                if (!d || d.status !== 'success') {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="br-empty">Could not load data.</td></tr>';
                    return;
                }
                setSummary(d);
                renderRows(d);
            })
            .catch(function () {
                if (myReq !== reqToken) return;
                if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="br-empty">Request failed.</td></tr>';
            });
    }
    function exportSoftwareStatement() {
        if (!activeBankId) return;
        var f = getFilters();
        var qs = 'ajax/export-bank-statement-software-excel.php?bank_id=' + encodeURIComponent(String(activeBankId));
        if (branchId > 0) qs += '&branch_id=' + encodeURIComponent(String(branchId));
        if (f.from) qs += '&from_date=' + encodeURIComponent(f.from);
        if (f.to) qs += '&to_date=' + encodeURIComponent(f.to);
        window.location.href = qs;
    }
    function importBankStatement(file) {
        if (!file || !activeBankId) return;
        var f = getFilters();
        var fd = new FormData();
        fd.append('bank_id', String(activeBankId));
        fd.append('excel_file', file);
        if (branchId > 0) fd.append('branch_id', String(branchId));
        if (f.from) fd.append('from_date', f.from);
        if (f.to) fd.append('to_date', f.to);

        var body = document.getElementById('brMatchBody');
        if (body) body.innerHTML = '<tr><td colspan="8" class="br-loading">Matching bank statement…</td></tr>';
        setPageView('match');

        fetch('ajax/import-bank-statement-excel.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || d.status !== 'success') {
                    alert((d && d.message) ? d.message : 'Import failed.');
                    renderMatchTable();
                    return;
                }
                showMatchResults(d);
            })
            .catch(function () {
                alert('Upload failed. Please try again.');
                renderMatchTable();
            });
    }

    document.querySelectorAll('#brBankTabs .br-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#brBankTabs .br-tab').forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
            loadBank(parseInt(btn.getAttribute('data-bank-id') || '0', 10));
        });
    });
    document.querySelectorAll('.br-view-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setPageView(btn.getAttribute('data-view') || 'software');
        });
    });
    document.querySelectorAll('.br-match-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeMatchView = btn.getAttribute('data-match') || 'matched';
            document.querySelectorAll('.br-match-tab').forEach(function (t) {
                t.classList.toggle('active', t === btn);
            });
            renderMatchTable();
        });
    });

    var sampleBtn = document.getElementById('brDownloadSample');
    if (sampleBtn) sampleBtn.addEventListener('click', function () {
        window.location.href = 'ajax/download-bank-statement-excel-sample.php';
    });
    var exportBtn = document.getElementById('brExportSoftware');
    if (exportBtn) exportBtn.addEventListener('click', exportSoftwareStatement);
    var importBtn = document.getElementById('brImportBank');
    var importFile = document.getElementById('brImportFile');
    if (importBtn && importFile) {
        importBtn.addEventListener('click', function () { importFile.click(); });
        importFile.addEventListener('change', function () {
            if (importFile.files && importFile.files[0]) importBankStatement(importFile.files[0]);
            importFile.value = '';
        });
    }
    var applyBtn = document.getElementById('brApplyFilters');
    if (applyBtn) applyBtn.addEventListener('click', function () { loadBank(activeBankId); });
    var clearBtn = document.getElementById('brClearFilters');
    if (clearBtn) clearBtn.addEventListener('click', function () {
        var fd = document.getElementById('brFromDate');
        var td = document.getElementById('brToDate');
        if (fd) fd.value = '';
        if (td) td.value = '';
        loadBank(activeBankId);
    });

    var matchBody = document.getElementById('brMatchBody');
    if (matchBody) {
        matchBody.addEventListener('click', function (e) {
            var btn = e.target.closest('.br-btn-add-missing');
            if (!btn || btn.disabled) return;
            var idx = parseInt(btn.getAttribute('data-miss-idx') || '-1', 10);
            if (idx >= 0) openAddMissingModal(idx);
        });
    }
    var addClose = document.getElementById('brAddMissingClose');
    var addCancel = document.getElementById('brAddMissingCancel');
    var addSave = document.getElementById('brAddMissingSave');
    var addModal = document.getElementById('brAddMissingModal');
    if (addClose) addClose.addEventListener('click', closeAddMissingModal);
    if (addCancel) addCancel.addEventListener('click', closeAddMissingModal);
    if (addSave) addSave.addEventListener('click', saveMissingToLedger);
    if (addModal) addModal.addEventListener('click', function (e) {
        if (e.target === addModal) closeAddMissingModal();
    });

    loadBank(activeBankId);
})();
</script>
<?php endif; ?>


<?php include 'footer-script.php'; ?>
</body>
</html>

