<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();

$voucher_types = getList("SELECT id, name FROM tbl_voucher_types WHERE status = 1 ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Bill Series - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <link rel="stylesheet" href="assets/libs/select2/select2.css">
    <style>
        :root {
            --bill-series-primary: #c5a864;
            --bill-series-primary-dark: #a68a4a;
            --bill-series-border: rgba(197, 168, 100, 0.4);
        }
        .bill-series-page { padding: 24px; display: flex; gap: 24px; flex-wrap: wrap; min-height: 0; }
        .bill-series-page h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 24px; width: 100%; }
        .bill-series-form-card { flex: 0 0 380px; max-width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .bill-series-form-card .card-body { padding: 24px; }
        .bill-series-list-card { flex: 1; min-width: 320px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .bill-series-list-card .card-body { padding: 24px; overflow: auto; }
        .form-group-bs { margin-bottom: 16px; }
        .form-group-bs label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .form-group-bs label .required { color: #dc2626; }
        .form-group-bs input, .form-group-bs select { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        .form-group-bs input:disabled, .form-group-bs select:disabled { background: #f3f4f6; cursor: not-allowed; }
        .bill-series-form-card .select2-container { width: 100% !important; }
        .bill-series-form-card .select2-container--default .select2-selection--single {
            min-height: 38px;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
        }
        .bill-series-form-card .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.45;
            padding-left: 0;
        }
        .bill-series-form-card .select2-container--default .select2-selection--single .select2-selection__arrow { height: 34px; }
        .bill-series-form-card .select2-container--default.select2-container--disabled .select2-selection--single {
            background: #f3f4f6;
            cursor: not-allowed;
        }
        .bill-series-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
        .btn-save-bs { background: linear-gradient(135deg, var(--bill-series-primary) 0%, var(--bill-series-primary-dark) 100%); color: #fff; border: none; padding: 10px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-save-bs:hover:not(:disabled) { opacity: 0.95; }
        .btn-save-bs:disabled { opacity: 0.6; cursor: not-allowed; }
        .bs-alert-locked { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; display: none; }
        .bs-alert-locked.show { display: block; }
        .bs-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .bs-table th, .bs-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .bs-table th { background: #f9fafb; font-weight: 600; color: #374151; }
        .bs-table tbody tr { cursor: pointer; transition: background 0.15s; }
        .bs-table tbody tr:hover { background: #f3f4f6; }
        .bs-table tbody tr.selected { background: rgba(197, 168, 100, 0.15); }
        .bs-table .lock-icon { color: #6b7280; font-size: 1rem; cursor: help; }
        .bs-table .lock-icon[title] { cursor: help; }
        .bs-msg { font-size: 13px; margin-left: 8px; }
        .bs-msg.success { color: #059669; }
        .bs-msg.error { color: #dc2626; }
        .bs-top-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .bs-no-rows { color: #6b7280; font-size: 14px; padding: 24px; text-align: center; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                    <div class="bill-series-page">
                        <h1>Bill Series</h1>

                        <div class="bill-series-form-card card">
                            <div class="card-body">
                                <div class="bs-alert-locked" id="bsAlertLocked" role="alert">
                                    &#9888; This series is locked because transactions already exist.
                                </div>
                                <form id="billSeriesForm">
                                    <input type="hidden" id="bsId" name="id" value="">
                                    <div class="form-group-bs">
                                        <label>Voucher Type <span class="required">*</span></label>
                                        <select id="bsVoucherType" name="voucher_type_id" required>
                                            <option value="">-- Select --</option>
                                            <?php foreach ($voucher_types as $vt): ?>
                                                <option value="<?php echo (int)$vt['id']; ?>"><?php echo htmlspecialchars($vt['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group-bs">
                                        <label>Prefix <span class="required">*</span></label>
                                        <input type="text" id="bsPrefix" name="prefix" value="" placeholder="e.g. INV" required>
                                    </div>
                                    <div class="form-group-bs">
                                        <label>Suffix</label>
                                        <input type="text" id="bsSuffix" name="suffix" value="" placeholder="">
                                    </div>
                                    <div class="form-group-bs">
                                        <label>Bill series count from</label>
                                        <input type="number" id="bsStartCount" name="start_count" value="0" min="0" step="1" placeholder="0">
                                    </div>
                                    <div class="bill-series-actions">
                                        <button type="button" class="btn-save-bs" id="bsSaveBtn">Save</button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="bsDeleteBtn" style="display: none;">Delete</button>
                                        <span class="bs-msg" id="bsSaveMsg" aria-live="polite"></span>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="bill-series-list-card card">
                            <div class="card-body">
                                <div class="bs-top-bar">
                                    <strong>Series List</strong>
                                </div>
                                <div style="overflow-x: auto;">
                                    <table class="bs-table" id="bsTable">
                                        <thead>
                                            <tr>
                                                <th>Voucher Type</th>
                                                <th>Branch</th>
                                                <th>Prefix</th>
                                                <th>Suffix</th>
                                                <th>Bill series count from</th>
                                                <th style="width: 44px;" title="Locked after bill generation"><span aria-label="Lock status">&#128274;</span></th>
                                            </tr>
                                        </thead>
                                        <tbody id="bsTableBody">
                                            <tr><td colspan="6" class="bs-no-rows">No Rows To Show</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/libs/select2/select2.js"></script>
    <script>
(function() {
    var seriesList = [];
    var selectedId = null;
    var isLocked = false;

    var el = {
        form: document.getElementById('billSeriesForm'),
        id: document.getElementById('bsId'),
        voucherType: document.getElementById('bsVoucherType'),
        prefix: document.getElementById('bsPrefix'),
        suffix: document.getElementById('bsSuffix'),
        startCount: document.getElementById('bsStartCount'),
        saveBtn: document.getElementById('bsSaveBtn'),
        deleteBtn: document.getElementById('bsDeleteBtn'),
        saveMsg: document.getElementById('bsSaveMsg'),
        alertLocked: document.getElementById('bsAlertLocked'),
        tableBody: document.getElementById('bsTableBody')
    };

    function voucherSelect2Ready() {
        return typeof jQuery !== 'undefined' && jQuery.fn.select2
            && jQuery(el.voucherType).hasClass('select2-hidden-accessible');
    }

    function syncVoucherTypeToSelect2() {
        if (!voucherSelect2Ready()) return;
        jQuery(el.voucherType).val(el.voucherType.value || null).trigger('change');
    }

    function initBillSeriesVoucherSelect() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $s = jQuery('#bsVoucherType');
        if ($s.hasClass('select2-hidden-accessible')) return;
        $s.select2({
            placeholder: '-- Select --',
            allowClear: true,
            width: '100%',
            dropdownParent: jQuery('.bill-series-page')
        });
    }

    function setFormLock(locked) {
        isLocked = !!locked;
        el.voucherType.disabled = locked;
        if (voucherSelect2Ready()) {
            jQuery(el.voucherType).prop('disabled', locked).trigger('change.select2');
        }
        el.prefix.disabled = locked;
        el.suffix.disabled = locked;
        el.startCount.disabled = locked;
        el.saveBtn.disabled = locked;
        el.deleteBtn.disabled = locked;
        el.deleteBtn.style.display = locked ? 'none' : (el.id.value ? 'inline-block' : 'none');
        if (locked) {
            el.alertLocked.classList.add('show');
        } else {
            el.alertLocked.classList.remove('show');
        }
    }

    function clearMessage() {
        el.saveMsg.textContent = '';
        el.saveMsg.className = 'bs-msg';
    }

    function showMessage(text, isError) {
        el.saveMsg.textContent = text;
        el.saveMsg.className = 'bs-msg ' + (isError ? 'error' : 'success');
    }

    function loadSeriesList() {
        var listUrl = 'ajax/get_bill_series.php';
        var sb = document.getElementById('settingsBranchId');
        if (sb && sb.value) {
            listUrl += '?branch_id=' + encodeURIComponent(sb.value);
        }
        fetch(listUrl, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.status && res.data) {
                    seriesList = res.data;
                    renderTable();
                    if (selectedId) {
                        var row = seriesList.find(function(s) { return String(s.id) === String(selectedId); });
                        if (row) setFormFromRow(row);
                        else selectedId = null;
                    }
                }
            })
            .catch(function() {
                el.tableBody.innerHTML = '<tr><td colspan="6" class="bs-no-rows">Failed to load series.</td></tr>';
            });
    }

    function renderTable() {
        if (seriesList.length === 0) {
            el.tableBody.innerHTML = '<tr><td colspan="6" class="bs-no-rows">No Rows To Show</td></tr>';
            return;
        }
        el.tableBody.innerHTML = seriesList.map(function(s) {
            var lockHtml = s.is_locked
                ? '<span class="lock-icon" title="Locked after bill generation">&#128274;</span>'
                : '<span class="lock-icon"></span>';
            return '<tr data-id="' + s.id + '" data-locked="' + (s.is_locked ? '1' : '0') + '">' +
                '<td>' + escapeHtml(s.voucher_type_name || '-') + '</td>' +
                '<td>' + escapeHtml(s.branch_name || (s.branch_id != null ? String(s.branch_id) : '-') ) + '</td>' +
                '<td>' + escapeHtml(s.prefix || '') + '</td>' +
                '<td>' + escapeHtml(s.suffix || '') + '</td>' +
                '<td>' + (s.start_count != null ? Number(s.start_count) : '0') + '</td>' +
                '<td>' + lockHtml + '</td></tr>';
        }).join('');

        el.tableBody.querySelectorAll('tr').forEach(function(tr) {
            tr.addEventListener('click', function() {
                var id = tr.getAttribute('data-id');
                var locked = tr.getAttribute('data-locked') === '1';
                el.tableBody.querySelectorAll('tr').forEach(function(r) { r.classList.remove('selected'); });
                tr.classList.add('selected');
                selectedId = id;
                var row = seriesList.find(function(s) { return String(s.id) === String(id); });
                if (row) setFormFromRow(row);
                setFormLock(locked);
            });
        });
    }

    function escapeHtml(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function setFormFromRow(row) {
        el.id.value = row.id || '';
        el.voucherType.value = row.voucher_type_id || '';
        syncVoucherTypeToSelect2();
        el.prefix.value = row.prefix || '';
        el.suffix.value = row.suffix || '';
        el.startCount.value = row.start_count != null ? row.start_count : 0;
        setFormLock(!!row.is_locked);
        el.deleteBtn.style.display = row.id && !row.is_locked ? 'inline-block' : 'none';
        el.deleteBtn.disabled = !!row.is_locked;
    }

    function resetForm() {
        el.id.value = '';
        el.voucherType.value = '';
        if (voucherSelect2Ready()) {
            jQuery(el.voucherType).val(null).trigger('change');
        }
        el.prefix.value = '';
        el.suffix.value = '';
        el.startCount.value = '0';
        selectedId = null;
        isLocked = false;
        setFormLock(false);
        el.deleteBtn.style.display = 'none';
        el.tableBody.querySelectorAll('tr').forEach(function(r) { r.classList.remove('selected'); });
    }

    function doDelete() {
        if (isLocked) return;
        var id = el.id.value.trim();
        if (!id) return;
        if (!confirm('Delete this bill series?')) return;
        var formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        var sb = document.getElementById('settingsBranchId');
        if (sb) formData.append('settings_branch_id', sb.value);
        fetch('ajax/update_bill_series.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status) {
                    showMessage(data.message || 'Deleted.');
                    resetForm();
                    loadSeriesList();
                } else {
                    showMessage(data.message || 'Delete failed.', true);
                }
            })
            .catch(function(err) {
                showMessage('Error: ' + (err.message || 'Request failed'), true);
            });
    }

    function save() {
        if (isLocked) {
            showMessage('This series is locked. Cannot update.', true);
            return;
        }
        var id = el.id.value.trim();
        var action = id ? 'update' : 'add';
        var formData = new FormData(el.form);
        formData.append('action', action);
        if (id) formData.append('id', id);
        var sb = document.getElementById('settingsBranchId');
        if (sb) formData.append('settings_branch_id', sb.value);

        el.saveBtn.disabled = true;
        clearMessage();

        fetch('ajax/update_bill_series.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                el.saveBtn.disabled = isLocked;
                if (data.status) {
                    showMessage(data.message || 'Saved.');
                    if (action === 'add') {
                        // Reload so the list is fresh and the form is empty for the next bill series
                        setTimeout(function() {
                            window.location.reload();
                        }, 350);
                    } else {
                        loadSeriesList();
                    }
                } else {
                    showMessage(data.message || 'Save failed.', true);
                }
            })
            .catch(function(err) {
                el.saveBtn.disabled = isLocked;
                showMessage('Error: ' + (err.message || 'Request failed'), true);
            });
    }

    el.saveBtn.addEventListener('click', save);
    el.deleteBtn.addEventListener('click', doDelete);
    el.form.addEventListener('submit', function(e) { e.preventDefault(); save(); });

    initBillSeriesVoucherSelect();
    loadSeriesList();
})();
    </script>
</body>
</html>
