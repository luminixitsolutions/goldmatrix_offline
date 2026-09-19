<?php
/**
 * Stock transfer history: outward lines from tbl_stock that belong to inter-branch transfers,
 * paired with the matching purchase line at the destination (same barcode/product/date window).
 */
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/stock_transfer_history_fetch.php';

$result = auragold_stock_transfer_history_fetch($conn);
$rows = $result['rows'];
$sqlError = $result['error'];

$sth_columns = [
    'date'         => 'Date',
    'invoice_no'   => 'Invoice No',
    'product_name' => 'Product Name',
    'transfer_to'  => 'Transfer To',
    'from_branch'  => 'From Branch',
    'barcode'      => 'Barcode',
    'net_wt'       => 'Net Wt',
    'gross_wt'     => 'Gross Wt',
    'qty'          => 'Qty',
    'diamond'      => 'Diamond',
    'stone_wt'     => 'Stone Wt',
    'purchase'     => 'Purchase',
    'metal_cost'   => 'Metal Cost',
    'stone_cost'   => 'Stone Cost',
    'making'       => 'Making',
    'against'      => 'Against',
    'status'       => 'Status',
];
$sth_num_cols = ['net_wt', 'gross_wt', 'qty', 'diamond', 'stone_wt', 'purchase', 'metal_cost', 'stone_cost', 'making'];

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Stock Transfer History — <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include __DIR__ . '/header-script.php'; ?>
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
    .sth-table-wrap { overflow: auto; background: #fff; border: 1px solid #e8e6f2; border-radius: 10px; }
    .sth-wrap .table thead th {
        background: #1a2d4a !important;
        color: #fff !important;
        border-color: rgba(255,255,255,0.12) !important;
        white-space: nowrap;
        font-size: 13px;
        vertical-align: middle;
    }
    table#sthTable.acr-col-table thead th {
        position: relative !important;
        top: auto !important;
        z-index: 2;
        padding-right: 14px;
        min-width: 72px;
    }
    table#sthTable.acr-col-table thead th .acr-th-inner,
    table#sthTable.acr-col-table thead th .acr-th-drag {
        color: #c9a962 !important;
        pointer-events: auto;
        position: relative;
        z-index: 2;
        cursor: grab;
        touch-action: none;
    }
    table#sthTable.acr-col-table thead th .acr-th-resize {
        z-index: 8;
        background: linear-gradient(90deg, transparent, rgba(232, 197, 71, 0.42)) !important;
    }
    table#sthTable.acr-col-table thead th .acr-th-resize:hover {
        background: rgba(232, 197, 71, 0.72) !important;
    }
    .sth-wrap .table tbody td { font-size: 13px; vertical-align: middle; }
    .sth-col-dropdown {
        min-width: 240px;
        max-height: 360px;
        overflow-y: auto;
        padding: 8px 0;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(17, 41, 75, 0.12);
    }
    .sth-col-dropdown .dropdown-header {
        font-weight: 700;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #11294b;
        padding: 8px 16px 4px;
    }
    .sth-col-check-label {
        display: flex;
        align-items: center;
        padding: 7px 16px;
        font-size: 0.875rem;
        color: #334155;
        cursor: pointer;
        margin-bottom: 0;
    }
    .sth-col-check-label:hover { background: #f8fafc; }
    .sth-col-cb { margin-right: 10px; accent-color: #11294b; }
    #sthDetailModal .modal-body { max-height: 60vh; overflow: auto; }
    #sthDetailModal #sthDetailSummary { margin-bottom: 12px; line-height: 1.5; }
    #sthDetailModal .table thead th,
    #sthDetailModal table thead th {
        background: #1a2d4a !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        font-weight: 600;
        font-size: 13px;
        white-space: nowrap;
        vertical-align: middle;
        padding: 10px 12px;
    }
    .sth-invoice { font-weight: 600; color: #1e40af; cursor: pointer; text-decoration: underline; }
    .sth-invoice:hover { color: #1d4ed8; }
    .sth-wrap .dataTables_filter input {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 6px 10px;
    }
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
    <nav class="small text-muted mb-2" aria-label="breadcrumb">
        <a href="dashboard.php">Home</a> / <a href="stock-transfer.php">Stock Transfer</a> / <span>History</span>
    </nav>
    <div class="sth-toolbar">
        <div>
            <h5 class="mb-0" style="font-weight:650;color:#1d2c4f;">Stock Transfer History</h5>
        </div>
        <div class="sth-toolbar-right">
            <a href="stock-transfer.php" class="btn btn-sm btn-primary"><i class="feather icon-shuffle"></i> New transfer</a>
            <a href="stock-receive-history.php" class="btn btn-sm btn-light border" title="Stock added at receiving branch"><i class="feather icon-download"></i> Receive history</a>
            <button type="button" class="btn btn-sm btn-light border" id="sthBtnRefresh" title="Reload" onclick="location.reload();"><i class="feather icon-refresh-cw"></i></button>
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-light border" id="sthColSettingsBtn" title="Show / hide columns" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="feather icon-settings"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right sth-col-dropdown" onclick="event.stopPropagation();">
                    <div class="dropdown-header">Show columns</div>
                    <?php foreach ($sth_columns as $key => $label): ?>
                    <label class="sth-col-check-label">
                        <input type="checkbox" class="sth-col-cb" data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" checked>
                        <?php echo htmlspecialchars($label); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">Export</button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" id="sthExportExcel"><i class="feather icon-file-text text-success mr-2"></i>Excel</a>
                    <a class="dropdown-item" href="#" id="sthExportPdf"><i class="feather icon-file text-danger mr-2"></i>PDF</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($sqlError)): ?>
        <div class="alert alert-danger">Could not load history: <?php echo htmlspecialchars($sqlError); ?></div>
    <?php endif; ?>

    <div class="sth-table-wrap p-2">
        <table class="table table-sm table-bordered table-hover mb-0 acr-col-table" id="sthTable" style="width:100%;">
            <thead>
                <tr>
                    <?php foreach ($sth_columns as $key => $label): ?>
                    <th data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($key, $sth_num_cols, true) ? ' class="text-right"' : ''; ?>><?php echo htmlspecialchars($label); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $inv = trim((string) ($row['invoice_no'] ?? ''));
                    if ($inv === '') {
                        $inv = 'ST-' . str_pad((string) ($row['outward_id'] ?? 0), 6, '0', STR_PAD_LEFT);
                    }
                    $docId = (int) ($row['transfer_doc_id'] ?? 0);
                    $owId = (int) ($row['outward_id'] ?? 0);
                    $d = !empty($row['transaction_date']) ? $row['transaction_date'] : ($row['created_at'] ?? '');
                    $dShow = $d ? date('d/m/Y', strtotime($d)) : '';
                    $lineCount = (int) ($row['line_count'] ?? 1);
                    ?>
                    <tr>
                        <td data-col="date"><?php echo htmlspecialchars($dShow); ?></td>
                        <td data-col="invoice_no">
                            <a href="#" class="sth-invoice" data-doc-id="<?php echo $docId; ?>" data-outward-id="<?php echo $owId; ?>" data-invoice="<?php echo htmlspecialchars($inv, ENT_QUOTES, 'UTF-8'); ?>" title="View transfer items">
                                <?php echo htmlspecialchars($inv); ?>
                            </a>
                            <?php if ($lineCount > 1): ?>
                                <span class="badge badge-light border ml-1"><?php echo $lineCount; ?> items</span>
                            <?php endif; ?>
                        </td>
                        <td data-col="product_name"><?php echo htmlspecialchars($row['product_name'] ?? ''); ?></td>
                        <td data-col="transfer_to"><?php echo htmlspecialchars($row['to_branch_name'] ?? '—'); ?></td>
                        <td data-col="from_branch"><?php echo htmlspecialchars($row['from_branch_name'] ?? '—'); ?></td>
                        <td data-col="barcode"><?php echo htmlspecialchars($row['barcode'] ?? ''); ?></td>
                        <td data-col="net_wt" class="text-right"><?php echo number_format((float) ($row['net_wt'] ?? 0), 3); ?></td>
                        <td data-col="gross_wt" class="text-right"><?php echo number_format((float) ($row['gross_wt'] ?? 0), 3); ?></td>
                        <td data-col="qty" class="text-right"><?php echo number_format((float) ($row['qty'] ?? 0), 0); ?></td>
                        <td data-col="diamond" class="text-right"><?php echo number_format((float) ($row['diamond_wt'] ?? 0), 3); ?></td>
                        <td data-col="stone_wt" class="text-right"><?php echo number_format((float) ($row['stone_wt'] ?? 0), 3); ?></td>
                        <td data-col="purchase" class="text-right"><?php echo number_format((float) ($row['purchase_value'] ?? 0), 2); ?></td>
                        <td data-col="metal_cost" class="text-right"><?php echo number_format((float) ($row['metal_cost'] ?? 0), 2); ?></td>
                        <td data-col="stone_cost" class="text-right"><?php echo number_format((float) ($row['stone_cost'] ?? 0), 2); ?></td>
                        <td data-col="making" class="text-right"><?php echo number_format((float) ($row['making_cost'] ?? 0), 2); ?></td>
                        <td data-col="against"><?php echo htmlspecialchars($row['against_ref'] ?? ''); ?></td>
                        <td data-col="status"><?php
                            $pst = strtolower(trim((string) ($row['transfer_pending_status'] ?? '')));
                            $rxId = (int) ($row['transfer_received_stock_id'] ?? 0);
                            $destId = (int) ($row['dest_stock_id'] ?? 0);
                            $inTransit = ($pst === 'pending' && $rxId <= 0 && $destId <= 0);
                            if ($inTransit) {
                                echo '<span class="badge badge-warning">In transit</span>';
                            } else {
                                echo '<span class="badge badge-success">Received</span>';
                            }
                        ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-muted small mt-2 mb-0">One row per transfer invoice (totals for all items). Click Invoice No to view line details.</p>
</div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoice line items modal (body-level so Bootstrap overlay works) -->
<div class="modal fade" id="sthDetailModal" tabindex="-1" role="dialog" aria-labelledby="sthDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sthDetailModalLabel">Transfer items — <span id="sthDetailInv">—</span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="sthDetailSummary"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="sthDetailTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Barcode</th>
                                <th class="text-right">Gross Wt</th>
                                <th class="text-right">Net Wt</th>
                                <th class="text-right">Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="sthDetailBody">
                            <tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    if (typeof jQuery === 'undefined') return;
    jQuery(function ($) {
        // Bind invoice click first so a DataTables error never blocks the modal.
        function sthEsc(s) {
            return $('<div>').text(s == null ? '' : String(s)).html();
        }

        function sthShowModal() {
            var $m = $('#sthDetailModal');
            if (typeof $.fn.modal === 'function') {
                $m.modal('show');
                return;
            }
            var el = $m.get(0);
            if (!el) return;
            el.style.display = 'block';
            el.classList.add('show');
            el.setAttribute('aria-hidden', 'false');
            if (!document.getElementById('sthDetailBackdrop')) {
                var bd = document.createElement('div');
                bd.id = 'sthDetailBackdrop';
                bd.className = 'modal-backdrop fade show';
                document.body.appendChild(bd);
            }
            document.body.classList.add('modal-open');
        }

        function sthHideModal() {
            var $m = $('#sthDetailModal');
            if (typeof $.fn.modal === 'function') {
                $m.modal('hide');
                return;
            }
            var el = $m.get(0);
            if (el) {
                el.style.display = 'none';
                el.classList.remove('show');
                el.setAttribute('aria-hidden', 'true');
            }
            var bd = document.getElementById('sthDetailBackdrop');
            if (bd) bd.parentNode.removeChild(bd);
            document.body.classList.remove('modal-open');
        }

        $(document).on('click', 'a.sth-invoice', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $a = $(this);
            var docId = parseInt($a.attr('data-doc-id') || $a.data('doc-id'), 10) || 0;
            var owId = parseInt($a.attr('data-outward-id') || $a.data('outward-id'), 10) || 0;
            var inv = String($a.attr('data-invoice') || $a.data('invoice') || $a.text() || '').trim();
            $('#sthDetailInv').text(inv || '—');
            $('#sthDetailSummary').text('Loading…');
            $('#sthDetailBody').html('<tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>');
            sthShowModal();
            var url = 'ajax/stock-transfer-history-detail.php?invoice_no=' + encodeURIComponent(inv);
            if (docId > 0) url += '&doc_id=' + docId;
            if (owId > 0) url += '&outward_id=' + owId;
            $.getJSON(url).done(function (data) {
                if (!data || !data.success) {
                    $('#sthDetailBody').html('<tr><td colspan="6" class="text-danger text-center">' + sthEsc((data && data.message) || 'Failed to load') + '</td></tr>');
                    $('#sthDetailSummary').text('');
                    return;
                }
                $('#sthDetailInv').text(data.invoice_no || inv);
                $('#sthDetailSummary').text(
                    (data.count || 0) + ' item(s) — Total Wt: ' + (parseFloat(data.total_wt) || 0).toFixed(3)
                    + ' — Total Qty: ' + (parseFloat(data.total_qty) || 0).toFixed(0)
                );
                var items = data.items || [];
                if (!items.length) {
                    $('#sthDetailBody').html('<tr><td colspan="6" class="text-center text-muted">No line items</td></tr>');
                    return;
                }
                var html = '';
                items.forEach(function (it) {
                    html += '<tr>'
                        + '<td>' + sthEsc(it.product_name) + '</td>'
                        + '<td>' + sthEsc(it.barcode || '—') + '</td>'
                        + '<td class="text-right">' + (parseFloat(it.gross_wt) || 0).toFixed(3) + '</td>'
                        + '<td class="text-right">' + (parseFloat(it.net_wt) || 0).toFixed(3) + '</td>'
                        + '<td class="text-right">' + (parseFloat(it.qty) || 0).toFixed(0) + '</td>'
                        + '<td>' + sthEsc(it.status || '') + '</td>'
                        + '</tr>';
                });
                $('#sthDetailBody').html(html);
            }).fail(function (xhr) {
                var msg = 'Network error';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                $('#sthDetailBody').html('<tr><td colspan="6" class="text-danger text-center">' + sthEsc(msg) + '</td></tr>');
                $('#sthDetailSummary').text('');
            });
        });

        $(document).on('click', '#sthDetailModal [data-dismiss="modal"]', function () {
            sthHideModal();
        });

        $('#sthExportExcel').on('click', function (e) {
            e.preventDefault();
            window.location.href = 'ajax/export-stock-transfer-history-excel.php';
        });
        $('#sthExportPdf').on('click', function (e) {
            e.preventDefault();
            window.location.href = 'ajax/export-stock-transfer-history-pdf.php';
        });
    });
})();
</script>
<script src="assets/js/Sortable.min.js"></script>
<script src="assets/js/auragold-col-reorder.js"></script>
<script>
(function () {
    var table = document.getElementById('sthTable');
    if (!table) return;

    var LS_KEY = 'auragold_sth_colvis_v1';
    var COL_ORDER_KEY = 'auragold_colorder_sth_v1';

    function applyCol(key, show) {
        var disp = show ? '' : 'none';
        table.querySelectorAll('[data-col="' + key + '"]').forEach(function (el) {
            el.style.display = disp;
        });
    }

    function visibleCount() {
        var n = 0;
        document.querySelectorAll('.sth-col-cb').forEach(function (cb) {
            if (cb.checked) n++;
        });
        return n;
    }

    function persistColVis() {
        var o = {};
        document.querySelectorAll('.sth-col-cb').forEach(function (cb) {
            o[cb.getAttribute('data-col')] = cb.checked;
        });
        try { localStorage.setItem(LS_KEY, JSON.stringify(o)); } catch (e) {}
    }

    function loadColVis() {
        var raw = localStorage.getItem(LS_KEY);
        if (raw) {
            try {
                var o = JSON.parse(raw) || {};
                document.querySelectorAll('.sth-col-cb').forEach(function (cb) {
                    var k = cb.getAttribute('data-col');
                    var on = o[k] !== false;
                    cb.checked = on;
                    applyCol(k, on);
                });
            } catch (e) {}
        }
        if (visibleCount() === 0) {
            document.querySelectorAll('.sth-col-cb').forEach(function (cb) {
                cb.checked = true;
                applyCol(cb.getAttribute('data-col'), true);
            });
        }
    }

    function refreshColReorder() {
        if (window.AuragoldColReorder && typeof AuragoldColReorder.refresh === 'function') {
            AuragoldColReorder.refresh(table);
        }
        if (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable('#sthTable')) {
            try { jQuery('#sthTable').DataTable().columns.adjust(); } catch (e) {}
        }
    }

    function initColReorder() {
        if (typeof Sortable === 'undefined' || !window.AuragoldColReorder) return false;
        try {
            AuragoldColReorder.init('#sthTable', {
                storageKey: COL_ORDER_KEY,
                widthsStorageKey: COL_ORDER_KEY + '_widths',
                fixedFirst: false,
                minWidth: 72
            });
            return true;
        } catch (err) {
            console.warn('Stock transfer history: column reorder init failed', err);
            return false;
        }
    }

    function initDataTable() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) return;
        if (jQuery.fn.DataTable.isDataTable('#sthTable')) return;
        try {
            jQuery('#sthTable').DataTable({
                ordering: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                dom: '<"row align-items-center mb-2"<"col-sm-6"l><"col-sm-6 text-right"f>>rtip',
                language: {
                    emptyTable: 'No Rows To Show',
                    zeroRecords: 'No Rows To Show'
                }
            });
        } catch (err) {
            console.warn('Stock transfer history DataTable init skipped', err);
        }
    }

    document.querySelectorAll('.sth-col-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (!this.checked && visibleCount() === 0) {
                this.checked = true;
                return;
            }
            applyCol(this.getAttribute('data-col'), this.checked);
            persistColVis();
            refreshColReorder();
        });
    });

    document.querySelectorAll('.sth-col-check-label').forEach(function (lbl) {
        lbl.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    loadColVis();

    function bootTableTools() {
        if (!initColReorder()) return false;
        initDataTable();
        return true;
    }

    if (!bootTableTools()) {
        var tries = 0;
        var timer = setInterval(function () {
            tries++;
            if (bootTableTools() || tries >= 30) clearInterval(timer);
        }, 100);
    }
})();
</script>
</body>
</html>
