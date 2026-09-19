<?php
/**
 * Renders Assign Inventory or UnAssign Inventory UI.
 * Requires $ai_page_mode set; load bootstrap first.
 */
require_once __DIR__ . '/auragold_assign_unassign_inventory_bootstrap.php';
$ai_mode_js = $ai_is_assign ? 'assign' : 'unassign';
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($ai_page_title, ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <link href="https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css" rel="stylesheet">
<?php include dirname(__DIR__) . '/header-script.php'; ?>
<style>
    :root {
        --aiu-navy: #11294b;
        --aiu-navy-mid: #1a3c63;
        --aiu-gold: #c9a227;
        --aiu-gold-soft: #f5edd6;
        --aiu-border: #d8dce3;
    }
    .aiu-page { padding: 12px 14px 24px; background: #e8eaef; min-height: calc(100vh - 100px); }
    .aiu-title { color: var(--aiu-navy); font-size: 1.25rem; font-weight: 700; margin: 0 0 4px; }
    .aiu-lead { color: #64748b; font-size: 0.85rem; margin: 0 0 14px; }
    .aiu-card {
        background: #fff; border: 1px solid var(--aiu-border); border-radius: 8px;
        box-shadow: 0 1px 2px rgba(17,41,75,.06);
    }
    .aiu-toolbar {
        display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
        padding: 14px 16px; border-bottom: 1px solid #eef1f5;
    }
    .aiu-field { display: flex; flex-direction: column; gap: 4px; min-width: 160px; }
    .aiu-field label { font-size: 0.72rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: .03em; }
    .aiu-field .form-control { height: 34px; font-size: 0.85rem; }
    .aiu-branch-ro {
        height: 34px; display: flex; align-items: center; padding: 0 10px;
        background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px;
        font-size: 0.85rem; color: #334155; min-width: 160px;
    }
    .aiu-actions { display: flex; gap: 8px; margin-left: auto; flex-wrap: wrap; }
    .aiu-btn {
        border: none; border-radius: 6px; padding: 8px 16px; font-size: 0.8rem;
        font-weight: 600; cursor: pointer; white-space: nowrap;
    }
    .aiu-btn-primary { background: var(--aiu-navy); color: #fff; }
    .aiu-btn-primary:hover { background: var(--aiu-navy-mid); }
    .aiu-btn-danger { background: #b91c1c; color: #fff; }
    .aiu-btn-danger:hover { background: #991b1b; }
    .aiu-btn-outline { background: #fff; color: var(--aiu-navy); border: 1px solid #cbd5e1; }
    .aiu-btn-outline:hover { background: #f8fafc; }
    .aiu-grid-wrap { padding: 0; }
    #aiuGrid { min-height: 420px; }
    .aiu-foot {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 16px; border-top: 1px solid #eef1f5; font-size: 0.8rem; color: #475569;
    }
    .aiu-hint {
        margin: 0 0 12px; padding: 10px 12px; border-radius: 6px;
        background: linear-gradient(180deg, #fff 0%, var(--aiu-gold-soft) 100%);
        border: 1px solid #e8d9a8; font-size: 0.8rem; color: #334155;
    }
    .aiu-hint a { color: var(--aiu-navy); font-weight: 600; }
    .tabulator .tabulator-header { background: var(--aiu-navy) !important; }
    .tabulator .tabulator-col, .tabulator .tabulator-col-content { background: var(--aiu-navy) !important; color: #fff !important; border-color: #1a3c63 !important; }
    .tabulator .tabulator-col .tabulator-col-title { color: #fff !important; }
</style>
</head>
<body>
<div class="page-loader"><div class="bg-primary"></div></div>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark" aria-hidden="true"></div>
        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar" aria-hidden="true"></nav>
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
<?php include dirname(__DIR__) . '/sidebar.php'; ?>

<div class="aiu-page">
    <h1 class="aiu-title"><?php echo htmlspecialchars($ai_page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="aiu-lead"><?php echo htmlspecialchars($ai_page_lead, ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="aiu-hint">
        For drag-and-drop bulk assign between two grids, use
        <a href="assign-inventory-to-sales-team.php">Assign Inventory To Sales Team</a>.
        View item lines in
        <a href="assign-inventory-items.php">Assign Inventory Items</a>.
    </p>

    <div class="aiu-card">
        <div class="aiu-toolbar">
            <div class="aiu-field">
                <label>Branch<?php echo $ai_branch_locked ? ' (login)' : ''; ?></label>
                <?php if ($ai_branch_locked): ?>
                    <input type="hidden" id="aiuBranch" value="<?php echo (int) $ai_default_branch_id; ?>">
                    <div class="aiu-branch-ro"><?php echo htmlspecialchars($ai_branch_display_name, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                    <select id="aiuBranch" class="form-control form-control-sm">
                        <option value="">— Select branch —</option>
                        <?php foreach ($ai_branches as $br): ?>
                            <option value="<?php echo (int) ($br['id'] ?? 0); ?>"<?php echo ((int) ($br['id'] ?? 0) === $ai_default_branch_id) ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($br['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="aiu-field">
                <label for="aiuSalesPerson">Sale Person</label>
                <select id="aiuSalesPerson" class="form-control form-control-sm" style="min-width:200px;">
                    <option value="">— Select —</option>
                    <?php foreach ($ai_sales_persons as $sp): ?>
                        <option value="<?php echo htmlspecialchars($sp, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sp, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="aiu-field" style="min-width:200px;flex:1;">
                <label for="aiuSearch">Search</label>
                <input type="search" id="aiuSearch" class="form-control form-control-sm" placeholder="Barcode / product…" autocomplete="off">
            </div>
            <div class="aiu-actions">
                <button type="button" class="aiu-btn aiu-btn-outline" id="aiuBtnReload">Refresh</button>
                <?php if ($ai_is_assign): ?>
                    <button type="button" class="aiu-btn aiu-btn-primary" id="aiuBtnAction">Assign Selected</button>
                <?php else: ?>
                    <button type="button" class="aiu-btn aiu-btn-danger" id="aiuBtnAction">UnAssign Selected</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="aiu-grid-wrap">
            <div id="aiuGrid"></div>
        </div>
        <div class="aiu-foot">
            <span id="aiuStatus">Select a sale person to load data.</span>
            <span id="aiuCount">0 rows</span>
        </div>
    </div>
</div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js"></script>
<script>
(function () {
    var MODE = <?php echo json_encode($ai_mode_js); ?>;
    var LOCKED_BRANCH = <?php echo $ai_branch_locked ? (int) $ai_default_branch_id : 0; ?>;
    var table = null;

    function branchId() {
        if (LOCKED_BRANCH > 0) return LOCKED_BRANCH;
        var el = document.getElementById('aiuBranch');
        var v = el ? parseInt(String(el.value || '').trim(), 10) : 0;
        return isNaN(v) ? 0 : v;
    }

    function salesPerson() {
        var el = document.getElementById('aiuSalesPerson');
        return el ? String(el.value || '').trim() : '';
    }

    function setStatus(msg) {
        var el = document.getElementById('aiuStatus');
        if (el) el.textContent = msg || '';
    }

    function setCount(n) {
        var el = document.getElementById('aiuCount');
        if (el) el.textContent = (n || 0) + ' rows';
    }

    function ajaxUrl(name, params) {
        try {
            var u = new URL('ajax/' + name, window.location.href);
            if (params) {
                Object.keys(params).forEach(function (k) {
                    if (params[k] !== '' && params[k] != null) u.searchParams.set(k, params[k]);
                });
            }
            return u.href;
        } catch (e) {
            return 'ajax/' + name;
        }
    }

    function mapAvailableRow(r) {
        return {
            barcode_no: String(r.barcode_no || r.barcode || ''),
            product_name: String(r.description || r.product_name || r.article || ''),
            qty: r.qty != null ? r.qty : (r.quantity != null ? r.quantity : ''),
            final_wt: r.final_wt != null ? r.final_wt : (r.swt != null ? r.swt : ''),
            amount: r.amount != null ? r.amount : '',
            invoice_no: String(r.invoice_no || ''),
            sales_person: '',
            rfid_code: String(r.item_code || r.rfid_code || ''),
            active: 'Yes'
        };
    }

    function mapAssignedRow(r) {
        return {
            barcode_no: String(r.barcode_no || ''),
            product_name: String(r.product_name || r.description || ''),
            qty: r.quantity != null ? r.quantity : (r.qty != null ? r.qty : ''),
            final_wt: r.final_wt != null ? r.final_wt : '',
            amount: r.amount != null ? r.amount : '',
            invoice_no: String(r.invoice_no || ''),
            sales_person: String(r.sales_person || salesPerson()),
            rfid_code: String(r.rfid_code || r.item_code || ''),
            active: String(r.active || 'Yes'),
            _raw: r
        };
    }

    function loadRows() {
        var bid = branchId();
        var sp = salesPerson();
        if (bid <= 0) {
            setStatus('Select a branch.');
            if (table) table.setData([]);
            setCount(0);
            return;
        }
        if (MODE === 'assign' && sp === '') {
            setStatus('Select a sale person, then available stock will load.');
            if (table) table.setData([]);
            setCount(0);
            return;
        }
        if (MODE === 'unassign' && sp === '') {
            setStatus('Select a sale person to see their assigned inventory.');
            if (table) table.setData([]);
            setCount(0);
            return;
        }

        setStatus('Loading…');
        var url;
        if (MODE === 'assign') {
            // Available (unassigned) stock for this branch
            url = ajaxUrl('assign-inventory-items-data.php', {
                filter_type: 'unassign',
                branch_id: bid,
                sales_person: sp
            });
        } else {
            url = ajaxUrl('assign-inventory-sales-team-load.php', {
                sales_person: sp,
                branch_id: bid
            });
        }

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var rows = [];
                if (j && j.success && Array.isArray(j.rows)) {
                    rows = j.rows.map(MODE === 'assign' ? mapAvailableRow : mapAssignedRow);
                }
                // Filter out empty barcodes
                rows = rows.filter(function (x) { return x.barcode_no; });
                if (table) table.setData(rows);
                setCount(rows.length);
                if (MODE === 'assign') {
                    setStatus(rows.length ? ('Available stock for ' + sp + ' — select rows and Assign.') : 'No available stock in this branch.');
                } else {
                    setStatus(rows.length ? ('Assigned to ' + sp + ' — select rows and UnAssign.') : (sp + ' has no assigned inventory.'));
                }
            })
            .catch(function () {
                setStatus('Failed to load data.');
                if (table) table.setData([]);
                setCount(0);
            });
    }

    function reloadSalesPersons(done) {
        var bid = branchId();
        var sel = document.getElementById('aiuSalesPerson');
        var prev = sel ? String(sel.value || '').trim() : '';
        if (bid <= 0) {
            if (sel) {
                sel.innerHTML = '<option value="">— Select —</option>';
            }
            if (typeof done === 'function') done();
            return;
        }
        fetch(ajaxUrl('assign-inventory-sales-persons-by-branch.php', { branch_id: bid }), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var names = (j && j.success && Array.isArray(j.names)) ? j.names : [];
                if (!sel) { if (typeof done === 'function') done(); return; }
                sel.innerHTML = '';
                var o0 = document.createElement('option');
                o0.value = '';
                o0.textContent = '— Select —';
                sel.appendChild(o0);
                names.forEach(function (nm) {
                    var o = document.createElement('option');
                    o.value = nm;
                    o.textContent = nm;
                    sel.appendChild(o);
                });
                if (prev && names.indexOf(prev) >= 0) sel.value = prev;
                if (typeof done === 'function') done();
            })
            .catch(function () {
                if (typeof done === 'function') done();
            });
    }

    function selectedRows() {
        if (!table) return [];
        return table.getSelectedData() || [];
    }

    function doAction() {
        var bid = branchId();
        var sp = salesPerson();
        var rows = selectedRows();
        if (bid <= 0) {
            alert('Select a branch.');
            return;
        }
        if (sp === '') {
            alert('Select a sale person.');
            return;
        }
        if (!rows.length) {
            alert('Select at least one row.');
            return;
        }

        var payloadRows = rows.map(function (r) {
            if (r._raw && typeof r._raw === 'object') {
                var copy = Object.assign({}, r._raw);
                copy.barcode_no = r.barcode_no;
                return copy;
            }
            return {
                barcode_no: r.barcode_no,
                product_name: r.product_name,
                quantity: r.qty,
                final_wt: r.final_wt,
                amount: r.amount,
                invoice_no: r.invoice_no,
                rfid_code: r.rfid_code,
                active: r.active || 'Yes'
            };
        });

        var label = MODE === 'assign' ? 'Assign' : 'UnAssign';
        if (!confirm(label + ' ' + payloadRows.length + ' selected item(s) for "' + sp + '"?')) {
            return;
        }

        setStatus(label + 'ing…');
        fetch(ajaxUrl('assign-unassign-inventory-save.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: MODE,
                sales_person: sp,
                branch_id: bid,
                rows: payloadRows
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.success) {
                    alert((j && j.message) ? j.message : (label + ' failed.'));
                    setStatus((j && j.message) ? j.message : (label + ' failed.'));
                    return;
                }
                alert(j.message || (label + ' done.'));
                loadRows();
            })
            .catch(function () {
                alert(label + ' failed.');
                setStatus(label + ' failed.');
            });
    }

    function initGrid() {
        table = new Tabulator('#aiuGrid', {
            layout: 'fitColumns',
            height: '480px',
            placeholder: 'No data',
            selectableRows: true,
            selectableRowsRangeMode: 'click',
            columns: [
                { formatter: 'rowSelection', titleFormatter: 'rowSelection', hozAlign: 'center', headerSort: false, width: 44 },
                { title: 'Barcode', field: 'barcode_no', width: 140 },
                { title: 'Product', field: 'product_name', minWidth: 180 },
                { title: 'Qty', field: 'qty', width: 80, hozAlign: 'right' },
                { title: 'Final Wt', field: 'final_wt', width: 100, hozAlign: 'right' },
                { title: 'Amount', field: 'amount', width: 100, hozAlign: 'right' },
                { title: 'Invoice', field: 'invoice_no', width: 120 },
                { title: 'Sale Person', field: 'sales_person', width: 140, visible: MODE === 'unassign' }
            ]
        });
    }

    document.getElementById('aiuBtnReload').addEventListener('click', function () {
        loadRows();
    });
    document.getElementById('aiuBtnAction').addEventListener('click', doAction);
    document.getElementById('aiuSalesPerson').addEventListener('change', loadRows);
    var brEl = document.getElementById('aiuBranch');
    if (brEl && brEl.tagName === 'SELECT') {
        brEl.addEventListener('change', function () {
            reloadSalesPersons(loadRows);
        });
    }
    document.getElementById('aiuSearch').addEventListener('input', function () {
        var q = String(this.value || '').trim().toLowerCase();
        if (!table) return;
        if (!q) {
            table.clearFilter(true);
            return;
        }
        table.setFilter(function (data) {
            var hay = [data.barcode_no, data.product_name, data.invoice_no, data.sales_person].join(' ').toLowerCase();
            return hay.indexOf(q) !== -1;
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initGrid();
            loadRows();
        });
    } else {
        initGrid();
        loadRows();
    }
})();
</script>
<?php include dirname(__DIR__) . '/footer-script.php'; ?>
</body>
</html>
