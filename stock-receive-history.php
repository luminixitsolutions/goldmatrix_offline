<?php
/**
 * Stock receive: pending rows in tbl_stock_transfer_pending (in transit), and received
 * lines after ajax/stock-transfer-receive.php posts into tbl_stock.
 */
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/stock_transfer_pending_schema.php';
require_once __DIR__ . '/includes/stock_receive_history_fetch.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $stXferConn = auragold_stock_transfer_central_mysqli();
} catch (Throwable $e) {
    die('Stock transfer / receive database: ' . htmlspecialchars($e->getMessage()));
}

$srhData = auragold_stock_receive_history_fetch($stXferConn);
$pendingRows = $srhData['pending'];
$receivedRows = $srhData['received'];
$sqlError = $srhData['error'];

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Stock Receive History — <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include __DIR__ . '/header-script.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.7.0/css/colReorder.dataTables.min.css">
<style>
    .sth-wrap { padding: 1rem 1.25rem; }
    .sth-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 14px;
    }
    .sth-toolbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; position: relative; z-index: 20; }
    .sth-toolbar-right .dropdown-menu { z-index: 2000; min-width: 11rem; }
    .sth-table-wrap { overflow: auto; background: #fff; border: 1px solid #e8e6f2; border-radius: 10px; position: relative; }
    .sth-wrap .table thead th {
        background: #1a2d4a !important;
        color: #fff !important;
        border-color: rgba(255,255,255,0.12) !important;
        white-space: nowrap;
        font-size: 13px;
        vertical-align: middle;
    }
    .sth-wrap .table tbody td { font-size: 13px; vertical-align: middle; }
    .sth-wrap .dataTables_filter input {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 6px 10px;
    }
    .sth-invoice { font-weight: 600; color: #0d9488; }
    .srh-col-product { min-width: 160px; max-width: 280px; }
    .srh-section-title { font-weight: 650; color: #1d2c4f; margin: 1rem 0 0.5rem; font-size: 1rem; }
    .srh-pending-badge { font-size: 11px; font-weight: 600; }
    #srhConfirmSelected:disabled { opacity: 0.55; cursor: not-allowed; }
    .srh-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin: 1rem 0 0.5rem;
    }
    .srh-section-head .srh-section-title { margin: 0; }
    .srh-col-settings-wrap { position: relative; }
    .srh-col-settings-btn {
        color: #64748b !important;
        padding: 4px 8px !important;
        line-height: 1;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #fff;
    }
    .srh-col-settings-btn:hover {
        color: #0d9488 !important;
        background: #f0fdfa !important;
    }
    .srh-columns-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1050;
        min-width: 280px;
        max-width: 340px;
        display: none;
        margin-top: 6px;
    }
    .srh-columns-dropdown.show { display: block; }
    .srh-columns-dropdown-header {
        padding: 10px 14px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 0.85rem;
        color: #1d2c4f;
    }
    .srh-columns-dropdown-search {
        padding: 8px 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    .srh-columns-dropdown-search input {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    .srh-col-order-wrap {
        padding: 8px 12px 10px;
        border-bottom: 1px solid #e2e8f0;
        max-height: 180px;
        overflow-y: auto;
    }
    .srh-col-order-title {
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 6px;
    }
    .srh-col-order-list { display: flex; flex-direction: column; gap: 4px; }
    .srh-col-order-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
        cursor: grab;
        font-size: 0.78rem;
        color: #334155;
        user-select: none;
    }
    .srh-col-order-item.srh-dragging { opacity: 0.5; }
    .srh-col-order-item.srh-drag-over { border-color: #0d9488; background: #ecfdf5; }
    .srh-columns-dropdown-list {
        max-height: 240px;
        overflow-y: auto;
        padding: 6px 0;
    }
    .srh-columns-dropdown-item {
        padding: 6px 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    .srh-columns-dropdown-item:hover { background: #f8fafc; }
    .srh-columns-dropdown-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    .srh-columns-dropdown-item label {
        margin: 0;
        cursor: pointer;
        font-size: 0.8rem;
        color: #334155;
        flex: 1;
    }
    /* Allow dragging column headers when ColReorder is active */
    table.dataTable thead th { cursor: move; }
    table.dataTable thead th:first-child { cursor: default; }
</style>
</head>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark" aria-hidden="true"></div>
        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar" aria-hidden="true"></nav>
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="sth-wrap">
    <div class="sth-toolbar">
        <div>
            <h5 class="mb-0" style="font-weight:650;color:#1d2c4f;">Stock Receive</h5>
        </div>
        <div class="sth-toolbar-right">
            <button type="button" class="btn btn-sm btn-success" id="srhConfirmSelected" disabled title="Post selected rows into stock at the destination branch">
                <i class="feather icon-download"></i> Receive selected into stock
            </button>
            <a href="stock-transfer.php" class="btn btn-sm btn-primary"><i class="feather icon-shuffle"></i> New transfer</a>
            <a href="stock-transfer-history.php" class="btn btn-sm btn-light border"><i class="feather icon-list"></i> Transfer history</a>
            <button type="button" class="btn btn-sm btn-light border" id="srhBtnRefresh" title="Reload" onclick="location.reload();"><i class="feather icon-refresh-cw"></i></button>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">Export</button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="ajax/export-stock-receive-history-excel.php" id="srhExportExcel" target="_blank" rel="noopener"><i class="feather icon-file-text text-success mr-2"></i>Excel</a>
                    <a class="dropdown-item" href="ajax/export-stock-receive-history-pdf.php" id="srhExportPdf" target="_blank" rel="noopener"><i class="feather icon-file text-danger mr-2"></i>PDF</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($sqlError)): ?>
        <div class="alert alert-danger">Could not load: <?php echo htmlspecialchars($sqlError); ?></div>
    <?php endif; ?>

    <div class="srh-section-head">
        <h6 class="srh-section-title">In transit (staging)</h6>
        <div class="srh-col-settings-wrap">
            <button type="button" class="btn btn-sm srh-col-settings-btn" id="srhPendColBtn" title="Show / hide / reorder columns" aria-haspopup="true" aria-expanded="false">
                <i class="feather icon-settings" style="font-size:18px;"></i>
            </button>
            <div class="srh-columns-dropdown" id="srhPendColDropdown" aria-hidden="true">
                <div class="srh-columns-dropdown-header">Columns</div>
                <div class="srh-columns-dropdown-search">
                    <input type="text" id="srhPendColSearch" placeholder="Search columns…" autocomplete="off">
                </div>
                <div class="srh-col-order-wrap">
                    <div class="srh-col-order-title">Column order (drag)</div>
                    <div class="srh-col-order-list" id="srhPendColOrderList"></div>
                </div>
                <div class="srh-columns-dropdown-list" id="srhPendColList"></div>
            </div>
        </div>
    </div>
    <div class="sth-table-wrap p-2 mb-4">
        <table class="table table-sm table-bordered table-hover mb-0" id="srhPendingTable" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:40px;" class="text-center" data-orderable="false" data-col="cb" title="Select lines to receive">
                        <input type="checkbox" id="srhSelectAll" aria-label="Select all pending">
                    </th>
                    <th data-col="date">Date</th>
                    <th data-col="staging_no">Staging No</th>
                    <th class="srh-col-product" data-col="product_name">Product Name</th>
                    <th data-col="receive_at">Receive at</th>
                    <th data-col="from_branch">From Branch</th>
                    <th data-col="barcode">Barcode</th>
                    <th class="text-right" data-col="net_wt">Net Wt</th>
                    <th class="text-right" data-col="gross_wt">Gross Wt</th>
                    <th class="text-right" data-col="qty">Qty</th>
                    <th class="text-right" data-col="diamond">Diamond</th>
                    <th class="text-right" data-col="stone_wt">Stone Wt</th>
                    <th class="text-right" data-col="value">Value</th>
                    <th class="text-right" data-col="metal_cost">Metal Cost</th>
                    <th class="text-right" data-col="stone_cost">Stone Cost</th>
                    <th class="text-right" data-col="making">Making</th>
                    <th data-col="against">Against</th>
                    <th data-col="status">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingRows as $row): ?>
                    <?php
                    $pendId = (int) ($row['pending_id'] ?? 0);
                    $inv = 'STP-' . str_pad((string) $pendId, 6, '0', STR_PAD_LEFT);
                    $d = !empty($row['transfer_date']) ? $row['transfer_date'] : ($row['created_at'] ?? '');
                    $dShow = $d ? date('d/m/Y', strtotime($d)) : '';
                    ?>
                    <tr data-pending-id="<?php echo $pendId; ?>">
                        <td class="text-center srh-cb-wrap" data-col="cb">
                            <?php if ($pendId > 0): ?>
                                <input type="checkbox" class="srh-row-cb" value="<?php echo $pendId; ?>" aria-label="Select staging <?php echo htmlspecialchars($inv); ?>">
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-col="date"><?php echo htmlspecialchars($dShow); ?></td>
                        <td data-col="staging_no"><span class="sth-invoice"><?php echo htmlspecialchars($inv); ?></span></td>
                        <td class="srh-col-product" data-col="product_name"><?php echo htmlspecialchars($row['product_name'] ?? ''); ?></td>
                        <td data-col="receive_at"><?php echo htmlspecialchars($row['to_branch_name'] ?? '—'); ?></td>
                        <td data-col="from_branch"><?php echo htmlspecialchars($row['from_branch_name'] ?? '—'); ?></td>
                        <td data-col="barcode"><?php echo htmlspecialchars($row['barcode'] ?? ''); ?></td>
                        <td class="text-right" data-col="net_wt"><?php echo number_format((float) ($row['net_wt'] ?? 0), 3); ?></td>
                        <td class="text-right" data-col="gross_wt"><?php echo number_format((float) ($row['gross_wt'] ?? 0), 3); ?></td>
                        <td class="text-right" data-col="qty"><?php echo number_format((float) ($row['qty'] ?? 0), 0); ?></td>
                        <td class="text-right" data-col="diamond"><?php echo number_format((float) ($row['diamond_wt'] ?? 0), 3); ?></td>
                        <td class="text-right" data-col="stone_wt"><?php echo number_format((float) ($row['stone_wt'] ?? 0), 3); ?></td>
                        <td class="text-right" data-col="value"><?php echo number_format((float) ($row['purchase_value'] ?? 0), 2); ?></td>
                        <td class="text-right" data-col="metal_cost"><?php echo number_format((float) ($row['metal_cost'] ?? 0), 2); ?></td>
                        <td class="text-right" data-col="stone_cost"><?php echo number_format((float) ($row['stone_cost'] ?? 0), 2); ?></td>
                        <td class="text-right" data-col="making"><?php echo number_format((float) ($row['making_cost'] ?? 0), 2); ?></td>
                        <td data-col="against"><?php echo htmlspecialchars($row['against_ref'] ?? ''); ?></td>
                        <td data-col="status"><span class="badge badge-warning srh-pending-badge">In transit</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="srh-section-head">
        <h6 class="srh-section-title">Received into stock</h6>
        <div class="srh-col-settings-wrap">
            <button type="button" class="btn btn-sm srh-col-settings-btn" id="srhRecvColBtn" title="Show / hide / reorder columns" aria-haspopup="true" aria-expanded="false">
                <i class="feather icon-settings" style="font-size:18px;"></i>
            </button>
            <div class="srh-columns-dropdown" id="srhRecvColDropdown" aria-hidden="true">
                <div class="srh-columns-dropdown-header">Columns</div>
                <div class="srh-columns-dropdown-search">
                    <input type="text" id="srhRecvColSearch" placeholder="Search columns…" autocomplete="off">
                </div>
                <div class="srh-col-order-wrap">
                    <div class="srh-col-order-title">Column order (drag)</div>
                    <div class="srh-col-order-list" id="srhRecvColOrderList"></div>
                </div>
                <div class="srh-columns-dropdown-list" id="srhRecvColList"></div>
            </div>
        </div>
    </div>
    <div class="sth-table-wrap p-2">
        <table class="table table-sm table-bordered table-hover mb-0" id="srhReceivedTable" style="width:100%;">
            <thead>
                <tr>
                    <th data-col="date">Date</th>
                    <th data-col="receipt_no">Receipt No</th>
                    <th class="srh-col-product" data-col="product_name">Product Name</th>
                    <th data-col="received_at">Received at</th>
                    <th data-col="from_branch">From Branch</th>
                    <th data-col="barcode">Barcode</th>
                    <th class="text-right" data-col="net_wt">Net Wt</th>
                    <th class="text-right" data-col="gross_wt">Gross Wt</th>
                    <th class="text-right" data-col="qty">Qty</th>
                    <th class="text-right" data-col="diamond">Diamond</th>
                    <th class="text-right" data-col="stone_wt">Stone Wt</th>
                    <th class="text-right" data-col="value">Value</th>
                    <th class="text-right" data-col="metal_cost">Metal Cost</th>
                    <th class="text-right" data-col="stone_cost">Stone Cost</th>
                    <th class="text-right" data-col="making">Making</th>
                    <th data-col="against">Against</th>
                    <th data-col="receipt_status">Receipt status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receivedRows as $row): ?>
                    <?php
                    $recvId = (int) ($row['receive_stock_id'] ?? 0);
                    $inv = 'SR-' . str_pad((string) ($row['receive_stock_id'] ?? 0), 6, '0', STR_PAD_LEFT);
                    $d = !empty($row['transaction_date']) ? $row['transaction_date'] : ($row['created_at'] ?? '');
                    $dShow = $d ? date('d/m/Y', strtotime($d)) : '';
                    $src = (string) ($row['receive_source'] ?? '');
                    ?>
                    <tr data-receive-id="<?php echo $recvId; ?>">
                        <td data-col="date"><?php echo htmlspecialchars($dShow); ?></td>
                        <td data-col="receipt_no"><span class="sth-invoice"><?php echo htmlspecialchars($inv); ?></span></td>
                        <td class="srh-col-product" data-col="product_name"><?php echo htmlspecialchars($row['product_name'] ?? ''); ?></td>
                        <td data-col="received_at"><?php echo htmlspecialchars($row['to_branch_name'] ?? '—'); ?></td>
                        <td data-col="from_branch"><?php echo htmlspecialchars($row['from_branch_name'] ?? '—'); ?></td>
                        <td data-col="barcode"><?php echo htmlspecialchars($row['barcode'] ?? ''); ?></td>
                        <td class="text-right" data-col="net_wt"><?php echo number_format((float) ($row['net_wt'] ?? 0), 3); ?></td>
                        <td class="text-right" data-col="gross_wt"><?php echo number_format((float) ($row['gross_wt'] ?? 0), 3); ?></td>
                        <td class="text-right" data-col="qty"><?php echo number_format((float) ($row['qty'] ?? 0), 0); ?></td>
                        <td class="text-right" data-col="diamond"><?php echo number_format((float) ($row['diamond_wt'] ?? 0), 3); ?></td>
                        <td class="text-right" data-col="stone_wt"><?php echo number_format((float) ($row['stone_wt'] ?? 0), 3); ?></td>
                        <td class="text-right" data-col="value"><?php echo number_format((float) ($row['purchase_value'] ?? 0), 2); ?></td>
                        <td class="text-right" data-col="metal_cost"><?php echo number_format((float) ($row['metal_cost'] ?? 0), 2); ?></td>
                        <td class="text-right" data-col="stone_cost"><?php echo number_format((float) ($row['stone_cost'] ?? 0), 2); ?></td>
                        <td class="text-right" data-col="making"><?php echo number_format((float) ($row['making_cost'] ?? 0), 2); ?></td>
                        <td data-col="against"><?php echo htmlspecialchars($row['against_ref'] ?? ''); ?></td>
                        <td class="text-nowrap" data-col="receipt_status">
                            <?php if ($src === 'legacy'): ?>
                                <span class="badge badge-secondary" title="Recorded before staging table">Legacy</span>
                            <?php elseif ($src === 'cross_db'): ?>
                                <span class="badge badge-info" title="Transferred from another branch database">Cross-DB</span>
                            <?php else: ?>
                                <span class="badge badge-success">In stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-muted small mt-2 mb-0">Pending list: up to 5,000 rows. Use the gear icon to show/hide or drag-reorder columns. Drag table headers to reorder when ColReorder is active.</p>
</div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script src="https://cdn.datatables.net/colreorder/1.7.0/js/dataTables.colReorder.min.js"></script>
<script>
(function () {
    if (typeof jQuery === 'undefined') return;

    jQuery(function ($) {
        var selectedIds = {};

        var pendColDefs = [
            { key: 'cb', label: 'Select', fixed: true },
            { key: 'date', label: 'Date' },
            { key: 'staging_no', label: 'Staging No' },
            { key: 'product_name', label: 'Product Name' },
            { key: 'receive_at', label: 'Receive at' },
            { key: 'from_branch', label: 'From Branch' },
            { key: 'barcode', label: 'Barcode' },
            { key: 'net_wt', label: 'Net Wt' },
            { key: 'gross_wt', label: 'Gross Wt' },
            { key: 'qty', label: 'Qty' },
            { key: 'diamond', label: 'Diamond' },
            { key: 'stone_wt', label: 'Stone Wt' },
            { key: 'value', label: 'Value' },
            { key: 'metal_cost', label: 'Metal Cost' },
            { key: 'stone_cost', label: 'Stone Cost' },
            { key: 'making', label: 'Making' },
            { key: 'against', label: 'Against' },
            { key: 'status', label: 'Status' }
        ];
        var recvColDefs = [
            { key: 'date', label: 'Date' },
            { key: 'receipt_no', label: 'Receipt No' },
            { key: 'product_name', label: 'Product Name' },
            { key: 'received_at', label: 'Received at' },
            { key: 'from_branch', label: 'From Branch' },
            { key: 'barcode', label: 'Barcode' },
            { key: 'net_wt', label: 'Net Wt' },
            { key: 'gross_wt', label: 'Gross Wt' },
            { key: 'qty', label: 'Qty' },
            { key: 'diamond', label: 'Diamond' },
            { key: 'stone_wt', label: 'Stone Wt' },
            { key: 'value', label: 'Value' },
            { key: 'metal_cost', label: 'Metal Cost' },
            { key: 'stone_cost', label: 'Stone Cost' },
            { key: 'making', label: 'Making' },
            { key: 'against', label: 'Against' },
            { key: 'receipt_status', label: 'Receipt status' }
        ];

        function lsGet(key, fallback) {
            try {
                var raw = localStorage.getItem(key);
                if (!raw) return fallback;
                var parsed = JSON.parse(raw);
                return parsed != null ? parsed : fallback;
            } catch (e) { return fallback; }
        }
        function lsSet(key, val) {
            try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
        }

        function countSelected() {
            var n = 0;
            Object.keys(selectedIds).forEach(function (k) {
                if (selectedIds[k]) n++;
            });
            return n;
        }

        function updateConfirmButtonState() {
            var n = countSelected();
            var $btn = $('#srhConfirmSelected');
            $btn.prop('disabled', n === 0);
            if (n > 0) {
                $btn.attr('title', 'Receive ' + n + ' selected row(s) into stock');
            } else {
                $btn.attr('title', 'Select staging rows first');
            }
        }

        function syncSelectAllCheckbox() {
            var $boxes = $('#srhPendingTable tbody .srh-row-cb');
            var total = $boxes.length;
            var checked = 0;
            $boxes.each(function () {
                if (this.checked) checked++;
            });
            // Also count from selectedIds for off-page rows
            var allSelected = countSelected();
            var $all = $('#srhSelectAll');
            if (!total && allSelected === 0) {
                $all.prop('checked', false).prop('indeterminate', false);
                return;
            }
            // Current page sync
            if (total > 0) {
                $all.prop('checked', checked === total && total > 0);
                $all.prop('indeterminate', checked > 0 && checked < total);
            }
        }

        function setRowSelected(id, on) {
            id = String(id);
            if (!id || id === '0') return;
            if (on) selectedIds[id] = true;
            else delete selectedIds[id];
        }

        var hasColReorder = !!( $.fn.dataTable && $.fn.dataTable.ColReorder );
        var dtOptsCommon = {
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            dom: '<"row align-items-center mb-2"<"col-sm-6"l><"col-sm-6 text-right"f>>rtip',
            stateSave: false
        };
        if (hasColReorder) {
            dtOptsCommon.colReorder = { realtime: false };
        }

        var dtPend = $('#srhPendingTable').DataTable($.extend({}, dtOptsCommon, {
            order: [[1, 'desc']],
            columnDefs: [{ orderable: false, targets: 0 }],
            language: {
                emptyTable: 'No staging rows — save a transfer first.',
                zeroRecords: 'No Rows To Show'
            }
        }));

        var dtRecv = $('#srhReceivedTable').DataTable($.extend({}, dtOptsCommon, {
            order: [[0, 'desc']],
            language: {
                emptyTable: 'No received lines yet.',
                zeroRecords: 'No Rows To Show'
            }
        }));

        // Restore checkbox state after DataTables draw
        function restoreCheckboxState() {
            $('#srhPendingTable tbody .srh-row-cb').each(function () {
                var id = String(this.value || '');
                this.checked = !!selectedIds[id];
            });
            syncSelectAllCheckbox();
            updateConfirmButtonState();
        }

        $('#srhPendingTable thead').on('click', '#srhSelectAll', function (e) {
            e.stopPropagation();
        });

        $(document).on('change', '#srhSelectAll', function () {
            var checked = !!this.checked;
            // Select all matching filter (all pages)
            dtPend.rows({ search: 'applied' }).every(function () {
                var $cb = $(this.node()).find('.srh-row-cb');
                if (!$cb.length) return;
                $cb.prop('checked', checked);
                setRowSelected($cb.val(), checked);
            });
            $(this).prop('indeterminate', false);
            updateConfirmButtonState();
        });

        $(document).on('change', '#srhPendingTable .srh-row-cb', function () {
            setRowSelected(this.value, this.checked);
            syncSelectAllCheckbox();
            updateConfirmButtonState();
        });

        $('#srhPendingTable').on('draw.dt', function () {
            restoreCheckboxState();
        });

        $('#srhConfirmSelected').on('click', function () {
            var ids = [];
            Object.keys(selectedIds).forEach(function (k) {
                if (selectedIds[k]) {
                    var v = parseInt(k, 10);
                    if (v > 0) ids.push(v);
                }
            });
            if (!ids.length) {
                alert('Select at least one staging row.');
                return;
            }
            if (!confirm('Receive ' + ids.length + ' selected item(s) into stock?')) return;
            var btn = $(this);
            btn.prop('disabled', true);
            fetch('ajax/stock-transfer-receive.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pending_ids: ids }),
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        alert(data.message || 'Received.');
                        location.reload();
                    } else {
                        alert((data && data.message) ? data.message : 'Receive failed.');
                        btn.prop('disabled', false);
                        updateConfirmButtonState();
                    }
                })
                .catch(function () {
                    alert('Network error.');
                    btn.prop('disabled', false);
                    updateConfirmButtonState();
                });
        });

        // --- Column settings (show/hide + drag order) ---
        function colIndexByKey($table, key) {
            var idx = -1;
            $table.find('thead th').each(function (i) {
                if ($(this).attr('data-col') === key) idx = i;
            });
            return idx;
        }

        function initColPanel(which, dt, defs, lsVisKey, lsOrderKey, btnId, dropId, listId, orderListId, searchId) {
            var visState = lsGet(lsVisKey, {});
            var orderState = lsGet(lsOrderKey, defs.map(function (d) { return d.key; }));
            // Ensure all keys present in order
            var seen = {};
            var order = [];
            orderState.forEach(function (k) {
                if (!seen[k] && defs.some(function (d) { return d.key === k; })) {
                    seen[k] = true;
                    order.push(k);
                }
            });
            defs.forEach(function (d) {
                if (!seen[d.key]) {
                    seen[d.key] = true;
                    order.push(d.key);
                }
            });

            function applyVisibility() {
                defs.forEach(function (d) {
                    if (d.fixed) return;
                    var idx = colIndexByKey($(dt.table().node()), d.key);
                    if (idx < 0) return;
                    var show = visState[d.key] !== false;
                    dt.column(idx).visible(show, false);
                });
                dt.columns.adjust().draw(false);
            }

            function applyOrder() {
                if (!dt.colReorder) return;
                var map = {};
                $(dt.table().node()).find('thead th').each(function (i) {
                    map[$(this).attr('data-col')] = i;
                });
                var indexes = [];
                order.forEach(function (k) {
                    if (map[k] != null) indexes.push(map[k]);
                });
                // Append any missing
                Object.keys(map).forEach(function (k) {
                    if (indexes.indexOf(map[k]) < 0) indexes.push(map[k]);
                });
                try {
                    dt.colReorder.order(indexes, true);
                } catch (e) {}
            }

            function renderList() {
                var $list = $('#' + listId).empty();
                defs.forEach(function (d) {
                    var checked = d.fixed || visState[d.key] !== false;
                    var id = 'srh_col_' + which + '_' + d.key;
                    var $row = $('<div class="srh-columns-dropdown-item"></div>');
                    var $inp = $('<input type="checkbox">').attr({ id: id, 'data-col-key': d.key }).prop('checked', checked).prop('disabled', !!d.fixed);
                    var $lab = $('<label></label>').attr('for', id).text(d.label);
                    $row.append($inp).append($lab);
                    $list.append($row);
                });
            }

            function renderOrderList() {
                var $ol = $('#' + orderListId).empty();
                order.forEach(function (k) {
                    var def = defs.find(function (d) { return d.key === k; });
                    if (!def || def.fixed) return;
                    var $item = $('<div class="srh-col-order-item" draggable="true"></div>')
                        .attr('data-col-key', k)
                        .html('<i class="feather icon-menu" aria-hidden="true"></i><span></span>');
                    $item.find('span').text(def.label);
                    $ol.append($item);
                });
            }

            // Apply saved visibility on load
            applyVisibility();

            $('#' + btnId).on('click', function (e) {
                e.stopPropagation();
                var $d = $('#' + dropId);
                var open = !$d.hasClass('show');
                $('.srh-columns-dropdown').removeClass('show').attr('aria-hidden', 'true');
                $('.srh-col-settings-btn').attr('aria-expanded', 'false');
                if (open) {
                    renderList();
                    renderOrderList();
                    $d.addClass('show').attr('aria-hidden', 'false');
                    $(this).attr('aria-expanded', 'true');
                }
            });

            $('#' + listId).on('change', 'input[data-col-key]', function () {
                var key = $(this).attr('data-col-key');
                visState[key] = !!this.checked;
                lsSet(lsVisKey, visState);
                applyVisibility();
            });

            $('#' + searchId).on('input', function () {
                var term = (this.value || '').toLowerCase();
                $('#' + listId + ' .srh-columns-dropdown-item').each(function () {
                    var t = $(this).find('label').text().toLowerCase();
                    $(this).toggle(t.indexOf(term) >= 0);
                });
            });

            // Drag reorder in settings panel
            var dragKey = null;
            $('#' + orderListId).on('dragstart', '.srh-col-order-item', function (e) {
                dragKey = $(this).attr('data-col-key');
                $(this).addClass('srh-dragging');
                if (e.originalEvent && e.originalEvent.dataTransfer) {
                    e.originalEvent.dataTransfer.effectAllowed = 'move';
                    e.originalEvent.dataTransfer.setData('text/plain', dragKey);
                }
            });
            $('#' + orderListId).on('dragend', '.srh-col-order-item', function () {
                $(this).removeClass('srh-dragging');
                $('#' + orderListId + ' .srh-col-order-item').removeClass('srh-drag-over');
                dragKey = null;
            });
            $('#' + orderListId).on('dragover', '.srh-col-order-item', function (e) {
                e.preventDefault();
                $(this).addClass('srh-drag-over');
            });
            $('#' + orderListId).on('dragleave', '.srh-col-order-item', function () {
                $(this).removeClass('srh-drag-over');
            });
            $('#' + orderListId).on('drop', '.srh-col-order-item', function (e) {
                e.preventDefault();
                $(this).removeClass('srh-drag-over');
                var targetKey = $(this).attr('data-col-key');
                var fromKey = dragKey || (e.originalEvent && e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.getData('text/plain'));
                if (!fromKey || !targetKey || fromKey === targetKey) return;
                var fromIdx = order.indexOf(fromKey);
                var toIdx = order.indexOf(targetKey);
                if (fromIdx < 0 || toIdx < 0) return;
                order.splice(fromIdx, 1);
                order.splice(toIdx, 0, fromKey);
                // Keep fixed cb first for pending
                if (which === 'pend' && order.indexOf('cb') !== 0) {
                    order = order.filter(function (k) { return k !== 'cb'; });
                    order.unshift('cb');
                }
                lsSet(lsOrderKey, order);
                renderOrderList();
                applyOrder();
            });

            // Persist header drag (ColReorder) into localStorage
            $(dt.table().node()).on('column-reorder.dt', function (e, settings, details) {
                try {
                    var newOrder = [];
                    $(dt.table().node()).find('thead th').each(function () {
                        var k = $(this).attr('data-col');
                        if (k) newOrder.push(k);
                    });
                    if (newOrder.length) {
                        order = newOrder;
                        lsSet(lsOrderKey, order);
                    }
                } catch (err) {}
            });

            // Apply saved order once after init
            setTimeout(function () { applyOrder(); }, 50);
        }

        initColPanel(
            'pend', dtPend, pendColDefs,
            'auragold_srh_pend_col_vis', 'auragold_srh_pend_col_order',
            'srhPendColBtn', 'srhPendColDropdown', 'srhPendColList', 'srhPendColOrderList', 'srhPendColSearch'
        );
        initColPanel(
            'recv', dtRecv, recvColDefs,
            'auragold_srh_recv_col_vis', 'auragold_srh_recv_col_order',
            'srhRecvColBtn', 'srhRecvColDropdown', 'srhRecvColList', 'srhRecvColOrderList', 'srhRecvColSearch'
        );

        $(document).on('click', function (e) {
            if ($(e.target).closest('.srh-col-settings-wrap').length) return;
            $('.srh-columns-dropdown').removeClass('show').attr('aria-hidden', 'true');
            $('.srh-col-settings-btn').attr('aria-expanded', 'false');
        });

        restoreCheckboxState();
        updateConfirmButtonState();
    });
})();
</script>
</body>
</html>
