<?php

session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
require_once __DIR__ . '/includes/auragold_metal_rate_urls.php';

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
auragold_ensure_tbl_metal_rate_urls($conn);

$metal_rate_urls = auragold_get_metal_rate_urls($conn, (int) $settings_branch_id, false);

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Metal Rates Url - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        :root {
            --mru-navy: #11294b;
            --mru-navy-dark: #0d1f38;
        }
        .mru-page {
            padding: 20px 24px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        .mru-page h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
        .mru-hint { font-size: 13px; color: #64748b; margin: 6px 0 0; }
        .mru-top-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .mru-toolbar-right {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }
        .mru-search-wrap {
            position: relative;
            width: 280px;
            min-width: 220px;
            flex: 0 1 280px;
        }
        .mru-search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 15px;
            pointer-events: none;
            z-index: 1;
        }
        .mru-search-wrap input {
            display: block;
            width: 100%;
            height: 38px;
            padding: 8px 12px 8px 38px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #1e293b;
            font-size: 13px;
            box-sizing: border-box;
        }
        .mru-search-wrap input:focus {
            outline: none;
            border-color: var(--mru-navy);
            box-shadow: 0 0 0 3px rgba(17, 41, 75, 0.12);
        }
        .mru-btn-add {
            background: linear-gradient(135deg, var(--mru-navy) 0%, var(--mru-navy-dark) 100%);
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .mru-btn-add:hover { opacity: 0.95; color: #fff; }
        .mru-table-wrap {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            background: #fff;
        }
        .mru-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 0; }
        .mru-table th, .mru-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        .mru-table th { background: #f8fafc; font-weight: 600; color: #374151; white-space: nowrap; }
        .mru-table tbody tr:last-child td { border-bottom: none; }
        .mru-table tbody tr:hover { background: #fafbfc; }
        .mru-empty { padding: 32px 16px; text-align: center; color: #94a3b8; }
        .mru-actions a { color: var(--mru-navy); margin-right: 8px; }
        .mru-actions a.mru-del { color: #dc2626; }
        .mru-url {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            word-break: break-all;
            color: #0f172a;
        }
        .mru-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .mru-badge-active { background: #dcfce7; color: #166534; }
        .mru-badge-inactive { background: #fee2e2; color: #991b1b; }
        #metalRateUrlModal .modal-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        #metalRateUrlModal .btn-save-mru { background: var(--mru-navy); border-color: var(--mru-navy); }
        .layout-content:has(.mru-page) > .container-fluid,
        .layout-content:has(.mru-page) .set-software-wrapper,
        .layout-content:has(.mru-page) .set-software-main {
            width: 100%;
            max-width: none;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0; max-width: none; width: 100%;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-masters-tabs.php'; ?>
                    <input type="hidden" name="settings_branch_id" id="settingsBranchId" value="<?php echo (int) $settings_branch_id; ?>">
                    <div class="mru-page">
                        <div class="mru-top-bar">
                            <div>
                                <h1>Metal Rates Url</h1>
                                <p class="mru-hint">Manage rate source URLs for the dashboard. Only <strong>Active</strong> URLs appear in Rate Conversions.</p>
                            </div>
                            <div class="mru-toolbar-right">
                                <div class="mru-search-wrap">
                                    <i class="feather icon-search"></i>
                                    <input type="search" id="mruSearch" placeholder="Search URLs…" autocomplete="off" aria-label="Search URLs">
                                </div>
                                <button type="button" class="mru-btn-add" id="btnAddMetalRateUrl">+ Add URL</button>
                            </div>
                        </div>

                        <div class="mru-table-wrap">
                            <table class="mru-table" id="metalRateUrlTable">
                                <thead>
                                    <tr>
                                        <th style="width:60px;">#</th>
                                        <th>Label</th>
                                        <th>URL</th>
                                        <th style="width:110px;">Status</th>
                                        <th style="width:80px;">Order</th>
                                        <th style="width:90px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="metalRateUrlBody">
                                    <?php if ($metal_rate_urls === []): ?>
                                        <tr class="mru-empty-row">
                                            <td colspan="6" class="mru-empty">No URLs yet. Click <strong>+ Add URL</strong> to add one.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($metal_rate_urls as $idx => $item): ?>
                                            <tr data-id="<?php echo (int) $item['id']; ?>"
                                                data-url="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-label="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-status="<?php echo (int) $item['status']; ?>"
                                                data-sort-order="<?php echo (int) $item['sort_order']; ?>">
                                                <td><?php echo (int) $idx + 1; ?></td>
                                                <td><?php echo htmlspecialchars($item['label'] !== '' ? $item['label'] : '—'); ?></td>
                                                <td class="mru-url"><?php echo htmlspecialchars($item['url']); ?></td>
                                                <td>
                                                    <?php if ((int) $item['status'] === 1): ?>
                                                        <span class="mru-badge mru-badge-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="mru-badge mru-badge-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo (int) $item['sort_order']; ?></td>
                                                <td class="mru-actions">
                                                    <a href="javascript:void(0)" class="mru-edit" title="Edit"><i class="feather icon-edit"></i></a>
                                                    <a href="javascript:void(0)" class="mru-del" title="Delete"><i class="feather icon-trash-2"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="metalRateUrlModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="metalRateUrlModalTitle">Metal Rates Url</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="mruId" value="">
                    <div class="form-group">
                        <label for="mruLabel">Label</label>
                        <input type="text" class="form-control" id="mruLabel" maxlength="255" placeholder="e.g. Dubai City of Gold">
                    </div>
                    <div class="form-group">
                        <label for="mruUrl">URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="mruUrl" maxlength="500" required placeholder="https://example.com/gold-rate">
                    </div>
                    <div class="form-group">
                        <label for="mruSortOrder">Sort order</label>
                        <input type="number" class="form-control" id="mruSortOrder" value="0" step="1">
                    </div>
                    <div class="form-group mb-0">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="mruStatus" checked>
                            <label class="form-check-label" for="mruStatus">Active (show on dashboard)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="mruClearBtn">Clear</button>
                    <button type="button" class="btn btn-primary btn-save-mru" id="mruSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/libs/popper/popper.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script>
    (function () {
        var $ = jQuery;
        var modal = $('#metalRateUrlModal');
        var settingsBranchId = $('#settingsBranchId').val() || '';

        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function renumberRows() {
            $('#metalRateUrlBody tr[data-id]').each(function (i) {
                $(this).children('td').eq(0).text(i + 1);
            });
        }

        function ensureEmptyRow() {
            var $body = $('#metalRateUrlBody');
            if ($body.find('tr[data-id]').length === 0 && $body.find('.mru-empty-row').length === 0) {
                $body.html('<tr class="mru-empty-row"><td colspan="6" class="mru-empty">No URLs yet. Click <strong>+ Add URL</strong> to add one.</td></tr>');
            }
        }

        function statusBadge(active) {
            return active
                ? '<span class="mru-badge mru-badge-active">Active</span>'
                : '<span class="mru-badge mru-badge-inactive">Inactive</span>';
        }

        function buildRow(row) {
            var label = row.label || '';
            var url = row.url || '';
            var status = parseInt(row.status, 10) === 1 ? 1 : 0;
            var sortOrder = parseInt(row.sort_order, 10) || 0;
            return $(
                '<tr data-id="' + row.id + '"' +
                ' data-url="' + escapeHtml(url) + '"' +
                ' data-label="' + escapeHtml(label) + '"' +
                ' data-status="' + status + '"' +
                ' data-sort-order="' + sortOrder + '">' +
                '<td></td>' +
                '<td>' + escapeHtml(label !== '' ? label : '—') + '</td>' +
                '<td class="mru-url">' + escapeHtml(url) + '</td>' +
                '<td>' + statusBadge(status === 1) + '</td>' +
                '<td>' + sortOrder + '</td>' +
                '<td class="mru-actions">' +
                '<a href="javascript:void(0)" class="mru-edit" title="Edit"><i class="feather icon-edit"></i></a> ' +
                '<a href="javascript:void(0)" class="mru-del" title="Delete"><i class="feather icon-trash-2"></i></a>' +
                '</td></tr>'
            );
        }

        function clearForm() {
            $('#mruId').val('');
            $('#mruLabel').val('');
            $('#mruUrl').val('');
            $('#mruSortOrder').val('0');
            $('#mruStatus').prop('checked', true);
        }

        function openModal(title) {
            $('#metalRateUrlModalTitle').text(title || 'Metal Rates Url');
            modal.modal('show');
        }

        $('#btnAddMetalRateUrl').on('click', function () {
            clearForm();
            openModal('Add Metal Rates Url');
        });

        $('#mruClearBtn').on('click', function () {
            clearForm();
        });

        $(document).on('click', '.mru-edit', function () {
            var $tr = $(this).closest('tr');
            $('#mruId').val($tr.data('id') || '');
            $('#mruLabel').val($tr.attr('data-label') || '');
            $('#mruUrl').val($tr.attr('data-url') || '');
            $('#mruSortOrder').val($tr.attr('data-sort-order') || '0');
            $('#mruStatus').prop('checked', String($tr.attr('data-status')) === '1');
            openModal('Edit Metal Rates Url');
        });

        $(document).on('click', '.mru-del', function () {
            var $tr = $(this).closest('tr');
            var id = $tr.data('id');
            if (!id) return;
            if (!window.confirm('Delete this URL?')) return;
            $.post('ajax/metal-rates-url.php', {
                action: 'delete',
                id: id,
                settings_branch_id: settingsBranchId
            }).done(function (res) {
                if (!res || !res.success) {
                    alert((res && res.message) ? res.message : 'Could not delete.');
                    return;
                }
                $tr.remove();
                renumberRows();
                ensureEmptyRow();
            }).fail(function () {
                alert('Could not delete.');
            });
        });

        $('#mruSaveBtn').on('click', function () {
            var id = $.trim($('#mruId').val() || '');
            var url = $.trim($('#mruUrl').val() || '');
            if (!url) {
                alert('URL is required.');
                $('#mruUrl').focus();
                return;
            }
            var payload = {
                action: 'save',
                id: id,
                url: url,
                label: $.trim($('#mruLabel').val() || ''),
                status: $('#mruStatus').is(':checked') ? 1 : 0,
                sort_order: parseInt($('#mruSortOrder').val(), 10) || 0,
                settings_branch_id: settingsBranchId
            };
            $('#mruSaveBtn').prop('disabled', true);
            $.post('ajax/metal-rates-url.php', payload).done(function (res) {
                if (!res || !res.success || !res.row) {
                    alert((res && res.message) ? res.message : 'Could not save.');
                    return;
                }
                var $body = $('#metalRateUrlBody');
                $body.find('.mru-empty-row').remove();
                var $existing = $body.find('tr[data-id="' + res.row.id + '"]');
                var $newRow = buildRow(res.row);
                if ($existing.length) {
                    $existing.replaceWith($newRow);
                } else {
                    $body.append($newRow);
                }
                renumberRows();
                modal.modal('hide');
            }).fail(function () {
                alert('Could not save.');
            }).always(function () {
                $('#mruSaveBtn').prop('disabled', false);
            });
        });

        $('#mruSearch').on('input', function () {
            var q = $.trim($(this).val() || '').toLowerCase();
            $('#metalRateUrlBody tr[data-id]').each(function () {
                var hay = (
                    ($(this).attr('data-url') || '') + ' ' +
                    ($(this).attr('data-label') || '')
                ).toLowerCase();
                $(this).toggle(!q || hay.indexOf(q) !== -1);
            });
        });
    })();
    </script>
</body>
</html>
