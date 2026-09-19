<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/location-helpers.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

auragold_ensure_location_tables($conn);

$states = getList("SELECT s.id, s.country_id, s.name, s.status, s.comment, c.name AS country_name
    FROM tbl_states s
    INNER JOIN tbl_countries c ON c.id = s.country_id
    ORDER BY c.name ASC, s.name ASC");
$countries = getList("SELECT id, name FROM tbl_countries ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>State Master - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.7.0/css/colReorder.dataTables.min.css">
    <link rel="stylesheet" href="assets/libs/select2/select2.css">
    <style>
        .select2-container { z-index: 10060; }
        #stateModal .select2-container--default .select2-selection--single {
            min-height: 38px; border: 1px solid #ced4da; border-radius: 0.25rem;
        }
        #stateModal .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px; padding-left: 12px;
        }
        #stateModal .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
    </style>
    <style>
        :root { --geo-navy: #11294b; --geo-navy-dark: #0d1f38; }
        .geo-master-page { padding: 20px 24px; width: 100%; max-width: none; box-sizing: border-box; }
        .geo-master-page h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 16px; }
        .geo-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 16px; justify-content: space-between; }
        .geo-toolbar-left { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .geo-btn-primary {
            background: linear-gradient(135deg, var(--geo-navy) 0%, var(--geo-navy-dark) 100%);
            color: #fff; border: none; padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .geo-btn-primary:hover { opacity: 0.95; }
        .geo-table-wrap { border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .geo-table-wrap .dataTables_wrapper { padding: 12px 14px 8px; }
        .geo-table { border-collapse: collapse; font-size: 13px; }
        .geo-table-wrap .geo-table:not(.DTCR_clonedTable) { width: 100% !important; }
        .geo-table th, .geo-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        .geo-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .geo-table .muted { color: #94a3b8; }
        .geo-badge { font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 600; }
        .geo-badge.on { background: #d1fae5; color: #065f46; }
        .geo-badge.off { background: #fee2e2; color: #991b1b; }
        .geo-actions a { margin-right: 8px; color: var(--geo-navy); }
        .geo-actions a.geo-del { color: #dc2626; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content geo-region-layout">
        <div class="container-fluid w-100 geo-region-container" style="padding-top: 0; padding-bottom: 0; max-width: none;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <div class="geo-master-page">
                        <h1>State</h1>
                        <div class="geo-toolbar">
                            <div class="geo-toolbar-left">
                                <button type="button" class="geo-btn-primary" data-toggle="modal" data-target="#stateModal" id="btnAddState">+ State</button>
                            </div>
                        </div>
                        <div class="geo-table-wrap">
                            <table class="geo-table table" id="stateTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th data-col="name">Name</th>
                                        <th data-col="country">Country</th>
                                        <th data-col="comment">Comment</th>
                                        <th data-col="status" style="width:90px;">Status</th>
                                        <th data-col="actions" style="width:100px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="stateTableBody">
                                    <?php foreach ($states as $row): ?>
                                            <tr data-id="<?php echo (int) $row['id']; ?>" data-country-id="<?php echo (int) $row['country_id']; ?>">
                                                <td data-col="name"><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td data-col="country"><?php echo htmlspecialchars($row['country_name']); ?></td>
                                                <td class="muted" data-col="comment"><?php echo htmlspecialchars($row['comment'] ?? ''); ?></td>
                                                <td data-col="status">
                                                    <?php if ((int) ($row['status'] ?? 1) === 1): ?>
                                                        <span class="geo-badge on">Active</span>
                                                    <?php else: ?>
                                                        <span class="geo-badge off">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="geo-actions" data-col="actions">
                                                    <a href="javascript:void(0)" class="geo-edit" title="Edit"><i class="feather icon-edit"></i></a>
                                                    <a href="javascript:void(0)" class="geo-del" title="Deactivate"><i class="feather icon-trash-2"></i></a>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="stateModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">State</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="stateId" value="">
                    <div class="form-group">
                        <label>Country <span class="text-danger">*</span></label>
                        <select class="form-control" id="stateCountryId" required>
                            <option value="">— Select —</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="stateName" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label>Comment</label>
                        <textarea class="form-control" id="stateComment" rows="2"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="stateStatus" checked>
                            <label class="custom-control-label" for="stateStatus">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="stateSaveBtn" style="background:var(--geo-navy);border-color:var(--geo-navy);">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/libs/popper/popper.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="https://cdn.datatables.net/colreorder/1.7.0/js/dataTables.colReorder.min.js"></script>
    <script src="assets/libs/select2/select2.js"></script>
    <script>
(function () {
    var modal = jQuery('#stateModal');
    var dt;

    function initStateCountrySelect2() {
        if (typeof jQuery.fn.select2 !== 'function') {
            return;
        }
        var $s = jQuery('#stateCountryId');
        if ($s.length && $s.hasClass('select2-hidden-accessible')) {
            $s.select2('destroy');
        }
        $s.select2({
            placeholder: '— Select —',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 0,
            dropdownParent: modal
        });
    }

    function rowHtml(r) {
        var st = parseInt(r.status, 10) === 1;
        var badge = st ? '<span class="geo-badge on">Active</span>' : '<span class="geo-badge off">Inactive</span>';
        var cm = r.comment || '';
        var cn = r.country_name || '';
        return '<tr data-id="' + r.id + '" data-country-id="' + r.country_id + '"><td data-col="name">' + escapeHtml(r.name) + '</td><td data-col="country">' + escapeHtml(cn) + '</td><td class="muted" data-col="comment">' + escapeHtml(cm) + '</td><td data-col="status">' + badge + '</td><td class="geo-actions" data-col="actions"><a href="javascript:void(0)" class="geo-edit" title="Edit"><i class="feather icon-edit"></i></a><a href="javascript:void(0)" class="geo-del" title="Deactivate"><i class="feather icon-trash-2"></i></a></td></tr>';
    }
    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function geoOrderRowCellsByHeader($tr) {
        var byCol = {};
        $tr.find('td[data-col]').each(function () {
            byCol[jQuery(this).attr('data-col')] = this;
        });
        $tr.empty();
        jQuery('#stateTable thead th[data-col]').each(function () {
            var k = jQuery(this).attr('data-col');
            if (byCol[k]) {
                $tr.append(byCol[k]);
            }
        });
    }

    function countryNameById(cid) {
        var o = jQuery('#stateCountryId option[value="' + cid + '"]');
        return o.length ? o.text() : '';
    }

    function initDt() {
        if (jQuery.fn.DataTable.isDataTable('#stateTable')) {
            jQuery('#stateTable').DataTable().destroy();
        }
        jQuery.fn.DataTable.ext.pager.numbers_length = 5;
        dt = jQuery('#stateTable').DataTable({
            pageLength: 10,
            pagingType: 'simple_numbers',
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            order: [[1, 'asc'], [0, 'asc']],
            colReorder: { fixedColumnsRight: 1, realtime: true },
            stateSave: true,
            stateDuration: -1,
            columnDefs: [{ orderable: false, targets: -1 }],
            language: { search: 'Search:', emptyTable: 'No rows to show' }
        });
    }

    jQuery(function ($) {
        initDt();
        modal.on('shown.bs.modal', function () {
            initStateCountrySelect2();
        });

        $('#btnAddState').on('click', function () {
            $('#stateId').val('');
            $('#stateCountryId').val('').trigger('change');
            $('#stateName').val('');
            $('#stateComment').val('');
            $('#stateStatus').prop('checked', true);
            modal.find('.modal-title').text('Add state');
        });

        $('#stateTableBody').on('click', '.geo-edit', function () {
            var tr = $(this).closest('tr');
            $('#stateId').val(tr.data('id'));
            $('#stateCountryId').val(String(tr.data('country-id'))).trigger('change');
            $('#stateName').val(tr.find('td[data-col="name"]').text().trim());
            $('#stateComment').val(tr.find('td[data-col="comment"]').text().trim());
            $('#stateStatus').prop('checked', tr.find('.geo-badge.on').length > 0);
            modal.find('.modal-title').text('Edit state');
            modal.modal('show');
        });

        $('#stateSaveBtn').on('click', function () {
            var id = $('#stateId').val();
            var payload = {
                action: id ? 'update' : 'add',
                country_id: $('#stateCountryId').val(),
                name: $('#stateName').val().trim(),
                comment: $('#stateComment').val().trim(),
                status: $('#stateStatus').is(':checked') ? 1 : 0
            };
            if (id) payload.id = id;
            if (!payload.country_id || !payload.name) { alert('Country and name are required'); return; }
            var btn = $(this).prop('disabled', true);
            $.post('ajax/state-master.php', payload, function (res) {
                btn.prop('disabled', false);
                if (!res || res.status !== 'success') {
                    alert((res && res.message) ? res.message : 'Error');
                    return;
                }
                var cid = parseInt(payload.country_id, 10);
                var r = {
                    id: id ? id : res.id,
                    country_id: cid,
                    name: payload.name,
                    comment: payload.comment,
                    status: payload.status,
                    country_name: countryNameById(cid)
                };
                var $newRow = $(rowHtml(r));
                geoOrderRowCellsByHeader($newRow);
                if (id) {
                    var $tr = $('#stateTableBody tr[data-id="' + id + '"]');
                    dt.row($tr).remove();
                    dt.row.add($newRow[0]).draw(false);
                } else {
                    dt.row.add($newRow[0]).draw(false);
                }
                modal.modal('hide');
            }, 'json').fail(function (xhr) {
                btn.prop('disabled', false);
                var msg = 'Server error';
                try {
                    var j = JSON.parse(xhr.responseText);
                    if (j.message) msg = j.message;
                } catch (e) {}
                alert(msg);
            });
        });

        $('#stateTableBody').on('click', '.geo-del', function () {
            var tr = $(this).closest('tr');
            var id = tr.data('id');
            if (!id || !confirm('Deactivate this state?')) return;
            $.post('ajax/state-master.php', { action: 'delete', id: id }, function (res) {
                if (!res || res.status !== 'success') {
                    alert((res && res.message) ? res.message : 'Error');
                    return;
                }
                dt.row(tr).remove().draw(false);
            }, 'json').fail(function () { alert('Server error'); });
        });
    });
})();
    </script>
</body>
</html>
