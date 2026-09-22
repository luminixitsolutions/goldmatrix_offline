<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
require_once __DIR__ . '/includes/auragold_party_select2.php';

auragold_require_login_or_exit();
?><!DOCTYPE html>
<html lang="en" class="default-style dce-page">
<head>
    <title>Delete Customer Entries - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php'; ?>
<?php auragold_echo_party_select2_styles(); ?>
<style>
html, body.dce-page { background: #f1f5f9; min-height: 100%; }
.dce-page .layout-content {
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}
.dce-shell {
    max-width: 1180px;
    margin: 0 auto;
    padding: 16px 20px 40px;
}
.dce-hero {
    background: linear-gradient(90deg, #11294b 0%, #1a3a5c 100%);
    color: #fff;
    border-radius: 10px;
    padding: 20px 24px;
    margin-bottom: 16px;
    box-shadow: 0 2px 10px rgba(17,41,75,.2);
}
.dce-hero h1 { margin: 0 0 6px; font-size: 1.2rem; font-weight: 700; }
.dce-hero p { margin: 0; font-size: .85rem; opacity: .9; }
.dce-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px 24px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(15,23,42,.04);
}
.dce-card h2 {
    margin: 0 0 12px;
    font-size: .95rem;
    font-weight: 700;
    color: #11294b;
}
.dce-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.dce-field { flex: 1; min-width: 260px; }
.dce-field label {
    display: block;
    font-size: .78rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
    text-transform: uppercase;
}
.dce-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    border-radius: 6px;
    font-size: .85rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
}
.dce-btn-primary { background: #11294b; color: #fff; }
.dce-btn-primary:hover { background: #1a3a5c; }
.dce-btn-danger { background: #dc2626; color: #fff; }
.dce-btn-danger:hover { background: #b91c1c; }
.dce-btn-danger:disabled,
.dce-btn-primary:disabled { opacity: .55; cursor: not-allowed; }
.dce-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.dce-summary {
    font-size: .84rem;
    color: #64748b;
}
.dce-summary strong { color: #11294b; }
.dce-table-wrap {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.dce-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .84rem;
}
.dce-table th,
.dce-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}
.dce-table th {
    background: #f8fafc;
    color: #334155;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}
.dce-table tr:last-child td { border-bottom: none; }
.dce-table tr.row-blocked { background: #fff7ed; }
.dce-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
}
.dce-empty {
    padding: 28px 16px;
    text-align: center;
    color: #64748b;
    font-size: .88rem;
}
.dce-alert {
    padding: 10px 12px;
    border-radius: 6px;
    font-size: .84rem;
    margin-bottom: 12px;
}
.dce-alert-warn {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
}
.dce-block-reason {
    display: block;
    font-size: .75rem;
    color: #c2410c;
    margin-top: 2px;
}
.dce-progress {
    display: none;
    margin-top: 10px;
    font-size: .84rem;
    color: #334155;
}
.dce-progress.active { display: block; }
.dce-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.45);
    z-index: 1300;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.dce-modal-overlay.active { display: flex; }
.dce-modal {
    background: #fff;
    border-radius: 10px;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 10px 40px rgba(15,23,42,.25);
    overflow: hidden;
}
.dce-modal-head {
    padding: 16px 20px;
    background: #11294b;
    color: #fff;
    font-weight: 700;
}
.dce-modal-body { padding: 18px 20px; color: #334155; font-size: .9rem; }
.dce-modal-foot {
    padding: 12px 20px 18px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.dce-btn-outline {
    background: #fff;
    color: #11294b;
    border: 1px solid #cbd5e1;
}
</style>
</head>
<body class="dce-page">
<?php include 'sidebar.php'; ?>
<div class="layout-content">
<div class="dce-shell">
    <div class="dce-hero">
        <h1>Delete Customer Entries</h1>
        <p>Select a ledger account (same list as Account Ledger), review linked invoices, vouchers, orders, and fixings, then delete selected records in bulk.</p>
    </div>

    <div class="dce-card">
        <h2>Select Ledger Account</h2>
        <div class="dce-filter-row">
            <div class="dce-field">
                <label for="customerId">Ledger Account</label>
                <?php auragold_party_select2_field([
                    'party_id' => 'customerId',
                    'party_name' => 'customerName',
                    'placeholder' => 'Search ledger account...',
                    'required' => false,
                    'show_add_btn' => false,
                    'show_billing_state' => false,
                ]); ?>
            </div>
            <button type="button" class="dce-btn dce-btn-primary" id="dceLoadBtn">
                <i class="feather icon-search"></i> Load Records
            </button>
        </div>
    </div>

    <div class="dce-card" id="dceResultsCard" style="display:none;">
        <div class="dce-alert dce-alert-warn">
            Deleting is permanent for supported voucher types. Blocked rows must be cleared first (e.g. delete sale fixing before purchase invoice, job work order before sale order).
        </div>
        <div class="dce-toolbar">
            <div class="dce-summary" id="dceSummary"></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" class="dce-btn dce-btn-outline" id="dceSelectAllBtn">Select All Deletable</button>
                <button type="button" class="dce-btn dce-btn-outline" id="dceClearBtn">Clear Selection</button>
                <button type="button" class="dce-btn dce-btn-danger" id="dceDeleteBtn" disabled>
                    <i class="feather icon-trash-2"></i> Delete Selected
                </button>
            </div>
        </div>
        <div class="dce-progress" id="dceProgress"></div>
        <div class="dce-table-wrap">
            <table class="dce-table">
                <thead>
                    <tr>
                        <th style="width:42px;"><input type="checkbox" id="dceCheckAll" title="Select all deletable"></th>
                        <th>Type</th>
                        <th>Voucher No</th>
                        <th>Date</th>
                        <th style="text-align:right;">Amount</th>
                        <th style="text-align:right;">Balance</th>
                    </tr>
                </thead>
                <tbody id="dceTableBody"></tbody>
            </table>
        </div>
        <div class="dce-empty" id="dceEmpty" style="display:none;">No records found for this customer.</div>
    </div>
</div>
</div>

<div class="dce-modal-overlay" id="dceConfirmModal">
    <div class="dce-modal" role="dialog" aria-modal="true">
        <div class="dce-modal-head">Confirm Delete</div>
        <div class="dce-modal-body" id="dceConfirmText"></div>
        <div class="dce-modal-foot">
            <button type="button" class="dce-btn dce-btn-outline" id="dceConfirmCancel">Cancel</button>
            <button type="button" class="dce-btn dce-btn-danger" id="dceConfirmOk">Delete</button>
        </div>
    </div>
</div>

<?php auragold_echo_party_select2_scripts([
    'show_add_btn' => false,
    'show_billing_state' => false,
    'placeholder' => 'Search ledger account...',
    'searchUrl' => 'ajax/search-ledger-accounts.php',
    'noResultsText' => 'No ledger account found',
]); ?>
<script>
(function () {
    var entries = [];
    var currentCustomer = { id: 0, name: '' };
    var loadBtn = document.getElementById('dceLoadBtn');
    var resultsCard = document.getElementById('dceResultsCard');
    var tableBody = document.getElementById('dceTableBody');
    var emptyEl = document.getElementById('dceEmpty');
    var summaryEl = document.getElementById('dceSummary');
    var deleteBtn = document.getElementById('dceDeleteBtn');
    var checkAll = document.getElementById('dceCheckAll');
    var selectAllBtn = document.getElementById('dceSelectAllBtn');
    var clearBtn = document.getElementById('dceClearBtn');
    var progressEl = document.getElementById('dceProgress');
    var confirmModal = document.getElementById('dceConfirmModal');
    var confirmText = document.getElementById('dceConfirmText');
    var confirmCancel = document.getElementById('dceConfirmCancel');
    var confirmOk = document.getElementById('dceConfirmOk');

    function fmtAmount(n) {
        var v = parseFloat(n);
        if (isNaN(v)) v = 0;
        return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(d) {
        if (!d) return '—';
        var parts = String(d).substring(0, 10).split('-');
        if (parts.length !== 3) return d;
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function getCustomerId() {
        var el = document.getElementById('customerId');
        return el ? parseInt(el.value, 10) || 0 : 0;
    }

    function getCustomerName() {
        var el = document.getElementById('customerName');
        return el ? String(el.value || '').trim() : '';
    }

    function selectedCount() {
        return tableBody.querySelectorAll('input.dce-row-check:checked').length;
    }

    function updateDeleteBtn() {
        deleteBtn.disabled = selectedCount() === 0;
        var deletable = tableBody.querySelectorAll('input.dce-row-check:not(:disabled)').length;
        var checkedDeletable = tableBody.querySelectorAll('input.dce-row-check:not(:disabled):checked').length;
        checkAll.checked = deletable > 0 && checkedDeletable === deletable;
        checkAll.indeterminate = checkedDeletable > 0 && checkedDeletable < deletable;
    }

    function renderTable() {
        tableBody.innerHTML = '';
        if (!entries.length) {
            emptyEl.style.display = 'block';
            summaryEl.innerHTML = 'Customer: <strong>' + escapeHtml(currentCustomer.name) + '</strong> — 0 records';
            updateDeleteBtn();
            return;
        }
        emptyEl.style.display = 'none';
        var deletableCount = 0;
        entries.forEach(function (e, idx) {
            if (e.deletable) deletableCount++;
            var tr = document.createElement('tr');
            if (!e.deletable) tr.className = 'row-blocked';
            var tdCheck = document.createElement('td');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'dce-row-check';
            cb.dataset.index = String(idx);
            cb.disabled = !e.deletable;
            cb.addEventListener('change', updateDeleteBtn);
            tdCheck.appendChild(cb);
            tr.appendChild(tdCheck);

            var tdType = document.createElement('td');
            tdType.innerHTML = '<span class="dce-type-badge">' + escapeHtml(e.type_label || e.type) + '</span>';
            if (!e.deletable && e.block_reason) {
                tdType.innerHTML += '<span class="dce-block-reason">' + escapeHtml(e.block_reason) + '</span>';
            }
            tr.appendChild(tdType);

            var tdVoucher = document.createElement('td');
            tdVoucher.textContent = e.voucher_no || '—';
            tr.appendChild(tdVoucher);

            var tdDate = document.createElement('td');
            tdDate.textContent = fmtDate(e.date);
            tr.appendChild(tdDate);

            var tdAmt = document.createElement('td');
            tdAmt.style.textAlign = 'right';
            tdAmt.textContent = fmtAmount(e.amount);
            tr.appendChild(tdAmt);

            var tdBal = document.createElement('td');
            tdBal.style.textAlign = 'right';
            tdBal.textContent = fmtAmount(e.balance);
            tr.appendChild(tdBal);

            tableBody.appendChild(tr);
        });
        summaryEl.innerHTML = 'Customer: <strong>' + escapeHtml(currentCustomer.name) + '</strong> — '
            + entries.length + ' record(s), <strong>' + deletableCount + '</strong> deletable';
        updateDeleteBtn();
    }

    function escapeHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function loadEntries() {
        var cid = getCustomerId();
        var cname = getCustomerName();
        if (cid <= 0 && !cname) {
            alert('Please select a ledger account.');
            return;
        }
        loadBtn.disabled = true;
        loadBtn.textContent = 'Loading...';
        var url = 'ajax/get-customer-deletable-entries.php?customer_id=' + encodeURIComponent(cid)
            + '&customer_name=' + encodeURIComponent(cname);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status !== 'success') {
                    alert(res.message || 'Could not load records.');
                    return;
                }
                entries = res.entries || [];
                currentCustomer = { id: res.customer_id || cid, name: res.customer_name || cname };
                resultsCard.style.display = 'block';
                renderTable();
            })
            .catch(function () {
                alert('Network error while loading records.');
            })
            .finally(function () {
                loadBtn.disabled = false;
                loadBtn.innerHTML = '<i class="feather icon-search"></i> Load Records';
            });
    }

    function setAllDeletable(checked) {
        tableBody.querySelectorAll('input.dce-row-check:not(:disabled)').forEach(function (cb) {
            cb.checked = checked;
        });
        updateDeleteBtn();
    }

    function getSelectedEntries() {
        var selected = [];
        tableBody.querySelectorAll('input.dce-row-check:checked').forEach(function (cb) {
            var idx = parseInt(cb.dataset.index, 10);
            if (!isNaN(idx) && entries[idx]) selected.push(entries[idx]);
        });
        return selected;
    }

    function deleteOne(entry) {
        return new Promise(function (resolve) {
            var fd = new FormData();
            if (entry.type === 'advance_payment') {
                fd.append('id', String(entry.id));
            } else {
                fd.append('type', entry.type);
                fd.append('id', String(entry.id));
            }
            var endpoint = entry.delete_endpoint || 'ajax/delete-transaction.php';
            fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    resolve({
                        ok: res.status === 'success',
                        message: res.message || (res.status === 'success' ? 'Deleted' : 'Delete failed'),
                        entry: entry
                    });
                })
                .catch(function () {
                    resolve({ ok: false, message: 'Network error', entry: entry });
                });
        });
    }

    function sortForDelete(list) {
        var priority = {
            sale_fixing_direct: 1,
            purchase_fixing_direct: 1,
            old_jewelry_scrap_invoice: 2,
            jobwork_order: 3,
            payment_voucher: 4,
            receipt_voucher: 4,
            advance_payment: 4,
            journal_voucher: 4,
            contra_voucher: 4,
            sale_return: 5,
            purchase_return: 5,
            sale_quotation: 5,
            purchase_quotation: 5,
            sale_order: 6,
            sale_invoice: 7,
            purchase_invoice: 8
        };
        return list.slice().sort(function (a, b) {
            var pa = priority[a.type] || 9;
            var pb = priority[b.type] || 9;
            return pa - pb;
        });
    }

    function runBulkDelete(selected) {
        deleteBtn.disabled = true;
        selectAllBtn.disabled = true;
        clearBtn.disabled = true;
        checkAll.disabled = true;
        progressEl.classList.add('active');
        var ordered = sortForDelete(selected);
        var done = 0;
        var failed = [];

        function next() {
            if (done >= ordered.length) {
                progressEl.textContent = failed.length
                    ? ('Finished. ' + (ordered.length - failed.length) + ' deleted, ' + failed.length + ' failed.')
                    : ('Successfully deleted ' + ordered.length + ' record(s).');
                selectAllBtn.disabled = false;
                clearBtn.disabled = false;
                checkAll.disabled = false;
                if (failed.length) {
                    alert('Some records could not be deleted:\n\n' + failed.map(function (f) {
                        return (f.entry.voucher_no || f.entry.type) + ': ' + f.message;
                    }).join('\n'));
                }
                loadEntries();
                return;
            }
            var item = ordered[done];
            progressEl.textContent = 'Deleting ' + (done + 1) + ' of ' + ordered.length + ': '
                + (item.type_label || item.type) + ' ' + (item.voucher_no || item.id);
            deleteOne(item).then(function (result) {
                if (!result.ok) failed.push(result);
                done++;
                next();
            });
        }
        next();
    }

    function showConfirm(selected) {
        confirmText.innerHTML = 'Delete <strong>' + selected.length + '</strong> selected record(s) for '
            + '<strong>' + escapeHtml(currentCustomer.name) + '</strong>?<br><br>This action cannot be undone.';
        confirmModal.classList.add('active');
        confirmOk.onclick = function () {
            confirmModal.classList.remove('active');
            runBulkDelete(selected);
        };
    }

    loadBtn.addEventListener('click', loadEntries);
    selectAllBtn.addEventListener('click', function () { setAllDeletable(true); });
    clearBtn.addEventListener('click', function () { setAllDeletable(false); });
    checkAll.addEventListener('change', function () { setAllDeletable(checkAll.checked); });
    deleteBtn.addEventListener('click', function () {
        var selected = getSelectedEntries();
        if (!selected.length) return;
        showConfirm(selected);
    });
    confirmCancel.addEventListener('click', function () { confirmModal.classList.remove('active'); });
    confirmModal.addEventListener('click', function (e) {
        if (e.target === confirmModal) confirmModal.classList.remove('active');
    });
})();
</script>
</body>
</html>
