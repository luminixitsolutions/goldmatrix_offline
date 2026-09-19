<?php 
session_start();
require_once 'config.php';

// Get date filter - default to today
$report_date = isset($_GET['date']) ? esc($_GET['date']) : date('Y-m-d');
$date_display = date('d-m-Y', strtotime($report_date));

$AURAGOLD_REPORT_PAGE = true;
include 'header-script.php';
include 'sidebar.php';
?>

<div class="layout-container day-report-page">
    <div class="main-content">
        <div class="page-container">
            <!-- Page Header -->
            <div class="page-header-bar">
                <div style="flex: 1; min-width: 0; overflow: hidden; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <h1 class="day-report-title">Day Report</h1>
                    <input type="date" id="reportDate" value="<?php echo $report_date; ?>" class="date-input" onchange="loadDayReport()">
                    <button class="btn-icon" onclick="loadDayReport()" title="Refresh">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                </div>
                <div class="page-header-actions" style="flex-shrink: 0; display: flex; gap: 6px;">
                    <button class="btn-primary" onclick="viewOldReports()">
                        <i class="feather icon-clock"></i> View Old
                    </button>
                    <button class="btn-primary" onclick="saveDayReport()">
                        <i class="feather icon-save"></i> Save
                    </button>
                    <div class="dropdown day-report-export">
                        <button type="button" class="btn-export" onclick="event.stopPropagation(); toggleDayReportExportMenu(this);" aria-haspopup="true" aria-expanded="false">
                            <i class="feather icon-download"></i> Export
                            <i class="feather icon-chevron-down btn-export-chevron"></i>
                        </button>
                        <div class="dropdown-menu export-format-menu" role="menu">
                            <a href="#" class="export-format-item" role="menuitem" onclick="event.preventDefault(); exportToExcel(); closeDayReportExportMenu();">
                                <span class="export-format-icon export-format-icon--excel" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" focusable="false"><path fill="#217346" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path fill="#fff" d="M14 2v6h6M8 13h8v1.5H8V13zm0 3.25h8V17.5H8v-1.25zm0-6.5h3v1.5H8v-1.5z"/></svg>
                                </span>
                                <span>Excel</span>
                            </a>
                            <a href="#" class="export-format-item" role="menuitem" onclick="event.preventDefault(); exportToPDF(); closeDayReportExportMenu();">
                                <span class="export-format-icon export-format-icon--pdf" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" focusable="false"><path fill="#e53935" d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 1.5V8h4.5L13 3.5zM8 18h8v-1.5H8V18zm0-3h8v-1.5H8V15zm0-3h5V10.5H8V12z"/></svg>
                                </span>
                                <span>PDF</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="report-content-wrapper">
                <!-- Data Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="dayReportTable" class="table">
                                <thead>
                                    <tr>
                                        <th><span class="th-desc-label">Description</span></th>
                                        <th style="text-align: right;">Debit</th>
                                        <th style="text-align: right;">Credit</th>
                                        <th style="text-align: right;">Inward Wt</th>
                                        <th style="text-align: right;">Outward Wt</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px;">
                                            <div style="color: #64748b;">Loading data...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Summary Panel -->
                <div class="summary-panel">
                    <div class="summary-item">
                        <span class="summary-label">Opening Amount</span>
                        <span class="summary-value" id="openingAmount">0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Expected Amount</span>
                        <span class="summary-value" id="expectedAmount">0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Online / Cheque Payment</span>
                        <span class="summary-value" id="onlineChequePayment">0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Closing Cash</span>
                        <span class="summary-value closing-cash" id="closingCash">0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Cash Denomination</span>
                        <span class="summary-value" id="cashDenomination">0.000</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Difference</span>
                        <span class="summary-value difference" id="difference">0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>

<style>
/* Layout Container */
.layout-container {
    padding: 20px;
    width: 100%;
    box-sizing: border-box;
    background: #f4f6fb;
    min-height: calc(100vh - 60px);
}

.main-content {
    width: 100%;
    max-width: 100%;
}

.page-container {
    width: 100%;
    max-width: 100%;
    padding: 0;
    background: #f4f6fb;
}

.page-header-bar {
    background: #fff;
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.date-input {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 11px;
    height: 36px;
}

.page-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: nowrap;
}

.btn-primary {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
    font-size: 11px;
    display: flex;
    align-items: center;
    gap: 6px;
    height: 36px;
}

.btn-primary:hover {
    background: #4a2d6c;
}

.btn-icon {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 6px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 11px;
    height: 36px;
}

.btn-icon:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.btn-export {
    background: #6d4ba8;
    color: #fff;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 36px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    transition: background 0.2s, box-shadow 0.2s;
}

.btn-export:hover {
    background: #5a3d8f;
    color: #fff;
}

.btn-export .btn-export-chevron {
    margin-left: 2px;
    opacity: 0.9;
}

.day-report-export .dropdown-menu.export-format-menu {
    display: none;
    min-width: 200px;
    padding: 6px 0;
    margin-top: 6px;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

.day-report-export .dropdown-menu.export-format-menu.show {
    display: block;
}

.export-format-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    color: #1e293b;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.15s;
}

.export-format-item:hover {
    background: #e0f2fe;
    color: #0f172a;
}

.export-format-icon {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.export-format-icon svg {
    display: block;
}

.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-top: 5px;
    min-width: 150px;
    z-index: 100;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 10px 15px;
    color: #1e293b;
    text-decoration: none;
    transition: background 0.2s;
}

.dropdown-item:hover {
    background: #f8fafc;
    color: #1e293b;
}

.report-content-wrapper {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 10px;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
    flex: 1;
}

.card-body {
    padding: 15px;
}

.table-responsive {
    overflow-x: auto;
    overflow-y: auto;
    width: 100%;
    max-height: calc(100vh - 350px);
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.day-report-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #11294b;
}

.table th {
    background: #f8fafc;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    font-size: 12px;
    white-space: nowrap;
}

.day-report-section-header {
    background: #f1f5f9;
    font-weight: 600;
    color: #11294b;
    cursor: pointer;
    user-select: none;
}

.day-report-section-header:hover {
    background: #e8edf5;
}

.day-report-section-header .toggle-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    margin-right: 8px;
    border-radius: 4px;
    background: #fff;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    line-height: 1;
    vertical-align: middle;
    color: #4a2d6c;
}

.day-report-section-child td:first-child {
    padding-left: 42px;
}

.day-report-section-child.is-hidden {
    display: none;
}

.day-report-empty-section {
    color: #94a3b8;
    font-style: italic;
}

.table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 12px;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.table tbody tr.total-row {
    font-weight: 600;
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
}

.table tbody tr.closing-row {
    color: #ef4444;
    font-weight: 600;
}

.table tbody tr.closing-row td {
    color: #ef4444;
}

.summary-panel {
    background: #f3f4f6;
    border-radius: 8px;
    padding: 20px;
    min-width: 280px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}

.summary-value {
    font-size: 12px;
    color: #11294b;
    font-weight: 600;
    text-align: right;
}

.summary-value.closing-cash {
    color: #ef4444;
}

.summary-value.difference {
    color: #ef4444;
    font-weight: 700;
}
</style>

<script>
let reportData = null;

// Load data on page load
$(document).ready(function() {
    loadDayReport();
});

function loadDayReport() {
    const date = document.getElementById('reportDate').value;
    if (!date) {
        alert('Please select a date');
        return;
    }
    
    $('#tableBody').html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #64748b;">Loading data...</div></td></tr>');
    
    fetch('ajax/get-day-report.php?date=' + date)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                reportData = data;
                if (Array.isArray(data.sections) && data.sections.length) {
                    renderSections(data.sections);
                } else {
                    renderTable(data.transactions || []);
                }
                updateSummary(data.summary);
            } else {
                $('#tableBody').html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #ef4444;">Error: ' + (data.message || 'Failed to load data') + '</div></td></tr>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            $('#tableBody').html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #ef4444;">Error loading data</div></td></tr>');
        });
}

function renderSections(sections) {
    const tbody = $('#tableBody');
    tbody.empty();

    if (!sections || sections.length === 0) {
        tbody.html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #64748b;">No transactions found</div></td></tr>');
        return;
    }

    let totalDebit = 0;
    let totalCredit = 0;
    let totalInwardWt = 0;
    let totalOutwardWt = 0;

    const excludeFromGrand = { bank: true, sale_quotation: true, purchase_quotation: true };

    sections.forEach(function(sec) {
        const key = sec.key || '';
        const label = sec.label || key;
        const t = sec.totals || {};
        const d = parseFloat(t.debit) || 0;
        const c = parseFloat(t.credit) || 0;
        const iw = parseFloat(t.inward_wt) || 0;
        const ow = parseFloat(t.outward_wt) || 0;

        if (!excludeFromGrand[key]) {
            totalDebit += d;
            totalCredit += c;
            totalInwardWt += iw;
            totalOutwardWt += ow;
        }

        const hdr = $('<tr class="day-report-section-header">').attr('data-section-key', key);
        const toggle = $('<span class="toggle-icon">+</span>');
        const title = $('<span>').text(label);
        hdr.append($('<td>').append(toggle).append(title));
        hdr.append($('<td>').text(formatNumber(d)).css('text-align', 'right'));
        hdr.append($('<td>').text(formatNumber(c)).css('text-align', 'right'));
        hdr.append($('<td>').text(formatWeight(iw)).css('text-align', 'right'));
        hdr.append($('<td>').text(formatWeight(ow)).css('text-align', 'right'));
        tbody.append(hdr);

        const rows = sec.rows || [];
        if (rows.length === 0) {
            const empty = $('<tr class="day-report-section-child is-hidden">').attr('data-parent-section', key);
            empty.append($('<td colspan="5" class="day-report-empty-section">').text('No records for this date.'));
            tbody.append(empty);
        } else {
            rows.forEach(function(r) {
                const tr = $('<tr class="day-report-section-child is-hidden">').attr('data-parent-section', key);
                tr.append($('<td>').text(r.description || ''));
                tr.append($('<td>').text(formatNumber(r.debit)).css('text-align', 'right'));
                tr.append($('<td>').text(formatNumber(r.credit)).css('text-align', 'right'));
                tr.append($('<td>').text(formatWeight(r.inward_wt)).css('text-align', 'right'));
                tr.append($('<td>').text(formatWeight(r.outward_wt)).css('text-align', 'right'));
                tbody.append(tr);
            });
        }
    });

    tbody.find('.day-report-section-header').on('click', function() {
        const key = $(this).attr('data-section-key');
        const rows = tbody.find('.day-report-section-child[data-parent-section="' + key + '"]');
        const icon = $(this).find('.toggle-icon');
        const hidden = rows.first().hasClass('is-hidden');
        if (hidden) {
            rows.removeClass('is-hidden');
            icon.text('−');
        } else {
            rows.addClass('is-hidden');
            icon.text('+');
        }
    });

    const totalRow = $('<tr class="total-row">');
    totalRow.append($('<td>').text('TOTAL'));
    totalRow.append($('<td>').text(formatNumber(totalDebit)).css('text-align', 'right'));
    totalRow.append($('<td>').text(formatNumber(totalCredit)).css('text-align', 'right'));
    totalRow.append($('<td>').text(formatWeight(totalInwardWt)).css('text-align', 'right'));
    totalRow.append($('<td>').text(formatWeight(totalOutwardWt)).css('text-align', 'right'));
    tbody.append(totalRow);

    const closingCash = (reportData && reportData.summary) ? parseFloat(reportData.summary.closing_cash) || 0 : 0;
    const closingRow = $('<tr class="closing-row">');
    closingRow.append($('<td>').text('CLOSING CASH BALANCE'));
    closingRow.append($('<td>').text('').css('text-align', 'right'));
    closingRow.append($('<td>').text(formatNumber(closingCash)).css('text-align', 'right'));
    closingRow.append($('<td>').text(formatWeight(0)).css('text-align', 'right'));
    closingRow.append($('<td>').text(formatWeight(0)).css('text-align', 'right'));
    tbody.append(closingRow);
}

function renderTable(transactions) {
    const tbody = $('#tableBody');
    tbody.empty();
    
    if (!transactions || transactions.length === 0) {
        tbody.html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #64748b;">No transactions found</div></td></tr>');
        return;
    }
    
    let totalDebit = 0;
    let totalCredit = 0;
    let totalInwardWt = 0;
    let totalOutwardWt = 0;
    
    // Render transaction rows
    transactions.forEach(trans => {
        const tr = $('<tr>');
        tr.append($('<td>').text(trans.description));
        tr.append($('<td>').text(formatNumber(trans.debit)).css('text-align', 'right'));
        tr.append($('<td>').text(formatNumber(trans.credit)).css('text-align', 'right'));
        tr.append($('<td>').text(formatWeight(trans.inward_wt)).css('text-align', 'right'));
        tr.append($('<td>').text(formatWeight(trans.outward_wt)).css('text-align', 'right'));
        tbody.append(tr);
        
        totalDebit += parseFloat(trans.debit) || 0;
        totalCredit += parseFloat(trans.credit) || 0;
        totalInwardWt += parseFloat(trans.inward_wt) || 0;
        totalOutwardWt += parseFloat(trans.outward_wt) || 0;
    });
    
    // Add TOTAL row
    const totalRow = $('<tr class="total-row">');
    totalRow.append($('<td>').text('TOTAL'));
    totalRow.append($('<td>').text(formatNumber(totalDebit)).css('text-align', 'right'));
    totalRow.append($('<td>').text(formatNumber(totalCredit)).css('text-align', 'right'));
    totalRow.append($('<td>').text(formatWeight(totalInwardWt)).css('text-align', 'right'));
    totalRow.append($('<td>').text(formatWeight(totalOutwardWt)).css('text-align', 'right'));
    tbody.append(totalRow);
    
    // Add CLOSING CASH BALANCE row
    const closingCash = (reportData && reportData.summary) ? parseFloat(reportData.summary.closing_cash) || 0 : 0;
    const closingRow = $('<tr class="closing-row">');
    closingRow.append($('<td>').text('CLOSING CASH BALANCE'));
    closingRow.append($('<td>').text('').css('text-align', 'right'));
    closingRow.append($('<td>').text(formatNumber(closingCash)).css('text-align', 'right'));
    closingRow.append($('<td>').text(formatWeight(0)).css('text-align', 'right'));
    closingRow.append($('<td>').text(formatWeight(0)).css('text-align', 'right'));
    tbody.append(closingRow);
}

function updateSummary(summary) {
    if (!summary) return;
    
    $('#openingAmount').text(formatNumber(summary.opening_amount || 0));
    $('#expectedAmount').text(formatNumber(summary.expected_amount || 0));
    $('#onlineChequePayment').text(formatNumber(summary.online_cheque_payment || 0));
    $('#closingCash').text(formatNumber(summary.closing_cash || 0));
    $('#cashDenomination').text(formatWeight(summary.cash_denomination || 0));
    $('#difference').text(formatNumber(summary.difference || 0));
}

function formatNumber(num) {
    return parseFloat(num || 0).toFixed(2);
}

function formatWeight(wt) {
    return parseFloat(wt || 0).toFixed(3);
}

function viewOldReports() {
    alert('View Old Reports functionality will be implemented soon');
}

function saveDayReport() {
    if (!reportData) {
        alert('No data to save');
        return;
    }
    
    const date = document.getElementById('reportDate').value;
    
    fetch('ajax/save-day-report.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            date: date,
            data: reportData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Day report saved successfully');
        } else {
            alert('Error saving report: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving report');
    });
}

function toggleDayReportExportMenu(btn) {
    var wrap = btn.closest('.day-report-export');
    var menu = wrap ? wrap.querySelector('.export-format-menu') : null;
    if (!menu) {
        return;
    }
    var wasOpen = menu.classList.contains('show');
    document.querySelectorAll('.day-report-export .export-format-menu').forEach(function(m) { m.classList.remove('show'); });
    document.querySelectorAll('.day-report-export .btn-export').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
    if (!wasOpen) {
        menu.classList.add('show');
        btn.setAttribute('aria-expanded', 'true');
    }
}

function closeDayReportExportMenu() {
    document.querySelectorAll('.day-report-export .export-format-menu').forEach(function(m) { m.classList.remove('show'); });
    document.querySelectorAll('.day-report-export .btn-export').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
}

function exportToExcel() {
    const date = document.getElementById('reportDate').value;
    if (!date) {
        alert('Please select a date');
        return;
    }
    window.location.href = 'ajax/export-day-report-excel.php?date=' + encodeURIComponent(date);
    closeDayReportExportMenu();
}

function exportToPDF() {
    var table = document.getElementById('dayReportTable');
    var summary = document.querySelector('.summary-panel');
    if (!table) {
        alert('No data to export');
        return;
    }
    var date = document.getElementById('reportDate').value;
    var styles = 'body{font-family:Segoe UI,Roboto,sans-serif;padding:24px;color:#1e293b;}h1{font-size:1.25rem;margin:0 0 4px;}p.meta{color:#64748b;margin:0 0 20px;}table{border-collapse:collapse;width:100%;font-size:12px;}th,td{border:1px solid #cbd5e1;padding:8px 10px;}th{background:#11294b;color:#fff;text-align:left;}td:nth-child(n+2){text-align:right;}.summary{margin-top:28px;max-width:360px;border:1px solid #e2e8f0;border-radius:8px;padding:16px;background:#f8fafc;}.summary-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:12px;}.summary-row:last-child{border-bottom:none;}';
    var h = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Day Report ' + String(date).replace(/</g, '') + '</title><style>' + styles + '</style></head><body>';
    h += '<h1>Day Report</h1><p class="meta">Date: ' + String(date).replace(/</g, '') + '</p>';
    h += table.outerHTML;
    if (summary) {
        h += '<div class="summary"><div style="font-weight:600;margin-bottom:8px;">Summary</div>' + summary.innerHTML + '</div>';
    }
    h += '</body></html>';
    var w = window.open('', '_blank');
    if (!w) {
        alert('Please allow pop-ups to export PDF (print dialog).');
        return;
    }
    w.document.write(h);
    w.document.close();
    w.focus();
    setTimeout(function() {
        w.print();
    }, 300);
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.day-report-export')) {
        closeDayReportExportMenu();
        return;
    }
    const dropdowns = document.querySelectorAll('.dropdown:not(.day-report-export)');
    dropdowns.forEach(dropdown => {
        if (!dropdown.contains(event.target)) {
            dropdown.querySelector('.dropdown-menu').classList.remove('show');
        }
    });
});
</script>
