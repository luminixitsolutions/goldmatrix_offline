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

$countries = getList("SELECT id, name, code, code3, status, comment FROM tbl_countries ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Country Master - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.7.0/css/colReorder.dataTables.min.css">
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
                        <h1>Country &amp; country code</h1>
                        <div class="geo-toolbar">
                            <div class="geo-toolbar-left">
                                <button type="button" class="geo-btn-primary" data-toggle="modal" data-target="#countryModal" id="btnAddCountry">+ Country</button>
                            </div>
                        </div>
                        <div class="geo-table-wrap">
                            <table class="geo-table table" id="countryTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th data-col="name">Name</th>
                                        <th data-col="code">Country code</th>
                                        <th data-col="code3">ISO3</th>
                                        <th data-col="comment">Comment</th>
                                        <th data-col="status" style="width:90px;">Status</th>
                                        <th data-col="actions" style="width:100px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="countryTableBody">
                                    <?php foreach ($countries as $row): ?>
                                            <tr data-id="<?php echo (int) $row['id']; ?>">
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['code'] ?? ''); ?></td>
                                                <td class="muted"><?php echo htmlspecialchars($row['code3'] ?? ''); ?></td>
                                                <td class="muted"><?php echo htmlspecialchars($row['comment'] ?? ''); ?></td>
                                                <td>
                                                    <?php if ((int) ($row['status'] ?? 1) === 1): ?>
                                                        <span class="geo-badge on">Active</span>
                                                    <?php else: ?>
                                                        <span class="geo-badge off">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="geo-actions">
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

    <div class="modal fade" id="countryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Country</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="countryId" value="">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="countryName" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label>Country code (ISO 2-letter)</label>
                        <input type="text" class="form-control" id="countryCode" maxlength="10" placeholder="e.g. IN, AE">
                    </div>
                    <div class="form-group">
                        <label>ISO3</label>
                        <input type="text" class="form-control" id="countryCode3" maxlength="3" placeholder="e.g. IND">
                    </div>
                    <div class="form-group">
                        <label>Comment</label>
                        <textarea class="form-control" id="countryComment" rows="2"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="countryStatus" checked>
                            <label class="custom-control-label" for="countryStatus">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="countrySaveBtn" style="background:var(--geo-navy);border-color:var(--geo-navy);">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/libs/popper/popper.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="https://cdn.datatables.net/colreorder/1.7.0/js/dataTables.colReorder.min.js"></script>
    <script>
(function () {
    var modal = jQuery('#countryModal');
    var dt;

    function rowHtml(r) {
        var st = parseInt(r.status, 10) === 1;
        var badge = st ? '<span class="geo-badge on">Active</span>' : '<span class="geo-badge off">Inactive</span>';
        var c = r.code || '';
        var c3 = r.code3 || '';
        var cm = r.comment || '';
        return '<tr data-id="' + r.id + '"><td>' + escapeHtml(r.name) + '</td><td>' + escapeHtml(c) + '</td><td class="muted">' + escapeHtml(c3) + '</td><td class="muted">' + escapeHtml(cm) + '</td><td>' + badge + '</td><td class="geo-actions"><a href="javascript:void(0)" class="geo-edit" title="Edit"><i class="feather icon-edit"></i></a><a href="javascript:void(0)" class="geo-del" title="Deactivate"><i class="feather icon-trash-2"></i></a></td></tr>';
    }
    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /** Keep new/edited row cells in the same order as headers after ColReorder */
    function geoOrderRowCellsByHeader($tr) {
        var byCol = {};
        $tr.find('td[data-col]').each(function () {
            byCol[jQuery(this).attr('data-col')] = this;
        });
        $tr.empty();
        jQuery('#countryTable thead th[data-col]').each(function () {
            var k = jQuery(this).attr('data-col');
            if (byCol[k]) {
                $tr.append(byCol[k]);
            }
        });
    }

    function initDt() {
        if (jQuery.fn.DataTable.isDataTable('#countryTable')) {
            jQuery('#countryTable').DataTable().destroy();
        }
        jQuery.fn.DataTable.ext.pager.numbers_length = 5;
        dt = jQuery('#countryTable').DataTable({
            pageLength: 10,
            pagingType: 'simple_numbers',
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            order: [[0, 'asc']],
            colReorder: { fixedColumnsRight: 1, realtime: true },
            stateSave: true,
            stateDuration: -1,
            columnDefs: [{ orderable: false, targets: -1 }],
            language: { search: 'Search:', emptyTable: 'No rows to show' }
        });
    }

    jQuery(function ($) {
        initDt();

        $('#btnAddCountry').on('click', function () {
            $('#countryId').val('');
            $('#countryName').val('');
            $('#countryCode').val('');
            $('#countryCode3').val('');
            $('#countryComment').val('');
            $('#countryStatus').prop('checked', true);
            modal.find('.modal-title').text('Add country');
        });

        $('#countryTableBody').on('click', '.geo-edit', function () {
            var tr = $(this).closest('tr');
            var id = tr.data('id');
            $('#countryId').val(id);
            $('#countryName').val(tr.find('td[data-col="name"]').text().trim());
            $('#countryCode').val(tr.find('td[data-col="code"]').text().trim());
            $('#countryCode3').val(tr.find('td[data-col="code3"]').text().trim());
            $('#countryComment').val(tr.find('td[data-col="comment"]').text().trim());
            $('#countryStatus').prop('checked', tr.find('.geo-badge.on').length > 0);
            modal.find('.modal-title').text('Edit country');
            modal.modal('show');
        });

        $('#countrySaveBtn').on('click', function () {
            var id = $('#countryId').val();
            var payload = {
                action: id ? 'update' : 'add',
                name: $('#countryName').val().trim(),
                code: $('#countryCode').val().trim(),
                code3: $('#countryCode3').val().trim(),
                comment: $('#countryComment').val().trim(),
                status: $('#countryStatus').is(':checked') ? 1 : 0
            };
            if (id) payload.id = id;
            if (!payload.name) { alert('Name is required'); return; }
            var btn = $(this).prop('disabled', true);
            $.post('ajax/country-master.php', payload, function (res) {
                btn.prop('disabled', false);
                if (!res || res.status !== 'success') {
                    alert((res && res.message) ? res.message : 'Error');
                    return;
                }
                var r = {
                    id: id ? id : res.id,
                    name: payload.name,
                    code: payload.code,
                    code3: payload.code3,
                    comment: payload.comment,
                    status: payload.status
                };
                var $newRow = $(rowHtml(r));
                geoOrderRowCellsByHeader($newRow);
                if (id) {
                    var $tr = $('#countryTableBody tr[data-id="' + id + '"]');
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

        $('#countryTableBody').on('click', '.geo-del', function () {
            var tr = $(this).closest('tr');
            var id = tr.data('id');
            if (!id || !confirm('Deactivate this country?')) return;
            $.post('ajax/country-master.php', { action: 'delete', id: id }, function (res) {
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
