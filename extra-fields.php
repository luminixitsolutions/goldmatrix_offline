<?php

session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
require_once __DIR__ . '/includes/auragold_extra_fields_schema.php';

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
auragold_ensure_tbl_extra_fields($conn);

$metals = auragold_extra_field_metals();
$current_metal = isset($_GET['metal']) ? auragold_extra_field_normalize_metal((string) $_GET['metal']) : 'Gold';
$extra_fields = auragold_get_extra_fields($conn, $settings_branch_id, $current_metal);

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Extra Fields - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        :root {
            --ef-navy: #11294b;
            --ef-navy-dark: #0d1f38;
            --ef-border: rgba(17, 41, 75, 0.35);
        }
        .extra-fields-page {
            padding: 20px 24px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        .extra-fields-page h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0 0 16px; }
        .ef-top-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .ef-metal-tabs { display: flex; flex-wrap: wrap; gap: 8px; }
        .ef-metal-tab {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid var(--ef-border);
            background: rgba(17, 41, 75, 0.08);
            color: var(--ef-navy-dark);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }
        .ef-metal-tab:hover { background: rgba(17, 41, 75, 0.16); color: var(--ef-navy-dark); text-decoration: none; }
        .ef-metal-tab.active {
            background: linear-gradient(135deg, var(--ef-navy) 0%, var(--ef-navy-dark) 100%);
            color: #fff;
            border-color: var(--ef-navy);
        }
        .ef-btn-add {
            background: linear-gradient(135deg, var(--ef-navy) 0%, var(--ef-navy-dark) 100%);
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .ef-btn-add:hover { opacity: 0.95; }
        .ef-toolbar-right {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }
        .ef-search-wrap {
            position: relative;
            width: 280px;
            min-width: 220px;
            flex: 0 1 280px;
        }
        .ef-search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 15px;
            pointer-events: none;
            z-index: 1;
        }
        .ef-search-wrap input {
            display: block;
            width: 100%;
            height: 38px;
            padding: 8px 12px 8px 38px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.4;
            box-sizing: border-box;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        .ef-search-wrap input::placeholder { color: #94a3b8; opacity: 1; }
        .ef-search-wrap input:focus {
            outline: none;
            border-color: var(--ef-navy);
            box-shadow: 0 0 0 3px rgba(17, 41, 75, 0.12);
        }
        .ef-no-results-row td { color: #94a3b8; font-style: italic; }
        .ef-table-wrap {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            background: #fff;
        }
        .ef-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 0; }
        .ef-table th, .ef-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        .ef-table th { background: #f8fafc; font-weight: 600; color: #374151; }
        .ef-table tbody tr:last-child td { border-bottom: none; }
        .ef-badge { font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 600; display: inline-block; }
        .ef-badge.on { background: #d1fae5; color: #065f46; }
        .ef-badge.off { background: #fee2e2; color: #991b1b; }
        .ef-value-link { color: var(--ef-navy); font-weight: 600; cursor: pointer; text-decoration: none; }
        .ef-value-link:hover { text-decoration: underline; }
        .ef-actions a { margin-right: 8px; color: var(--ef-navy); }
        .ef-actions a.ef-del { color: #dc2626; }
        .ef-empty { padding: 32px 16px; text-align: center; color: #94a3b8; }
        .ef-options-wrap { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc; }
        .ef-options-list { max-height: 160px; overflow-y: auto; margin-bottom: 10px; }
        .ef-option-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 6px 8px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .ef-option-item button {
            border: none;
            background: transparent;
            color: #dc2626;
            cursor: pointer;
            padding: 0 4px;
            line-height: 1;
        }
        .ef-option-add-row { display: flex; gap: 8px; }
        .ef-option-add-row input { flex: 1; }
        #extraFieldModal .modal-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        #extraFieldModal .btn-save-ef { background: var(--ef-navy); border-color: var(--ef-navy); }
        .layout-content:has(.extra-fields-page) > .container-fluid,
        .layout-content:has(.extra-fields-page) .set-software-wrapper,
        .layout-content:has(.extra-fields-page) .set-software-main {
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
                    <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                    <div class="extra-fields-page">
                        <h1>Extra Fields</h1>

                        <div class="ef-top-bar">
                            <div class="ef-metal-tabs" role="tablist" aria-label="Metal type">
                                <?php foreach ($metals as $metal): ?>
                                    <a href="extra-fields.php?metal=<?php echo urlencode($metal); ?>"
                                       class="ef-metal-tab<?php echo $metal === $current_metal ? ' active' : ''; ?>"
                                       data-metal="<?php echo htmlspecialchars($metal, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($metal); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="ef-toolbar-right">
                                <div class="ef-search-wrap">
                                    <i class="feather icon-search"></i>
                                    <input type="search" id="efSearch" placeholder="Search extra fields…" autocomplete="off" aria-label="Search extra fields">
                                </div>
                                <button type="button" class="ef-btn-add" id="btnAddExtraField">+ Add</button>
                            </div>
                        </div>

                        <div class="ef-table-wrap">
                            <table class="ef-table" id="extraFieldsTable">
                                <thead>
                                    <tr>
                                        <th>Display Name</th>
                                        <th style="width:120px;">Field Type</th>
                                        <th style="width:120px;">Field Value</th>
                                        <th style="width:90px;">Status</th>
                                        <th style="width:90px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="extraFieldsBody">
                                    <?php if ($extra_fields === []): ?>
                                        <tr class="ef-empty-row">
                                            <td colspan="5" class="ef-empty">No extra fields for <?php echo htmlspecialchars($current_metal); ?>. Click <strong>+ Add</strong> to create one.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($extra_fields as $field): ?>
                                            <?php
                                            $opt_count = count($field['dropdown_options'] ?? []);
                                            $type_label = $field['field_type'] === 'dropdown' ? 'Dropdown' : 'Text';
                                            ?>
                                            <tr data-id="<?php echo (int) $field['id']; ?>"
                                                data-metal="<?php echo htmlspecialchars($field['metal_type'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-display="<?php echo htmlspecialchars($field['display_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-type="<?php echo htmlspecialchars($field['field_type'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-status="<?php echo (int) $field['status']; ?>"
                                                data-options="<?php echo htmlspecialchars(json_encode($field['dropdown_options'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                                                <td><?php echo htmlspecialchars($field['display_name']); ?></td>
                                                <td><?php echo htmlspecialchars($type_label); ?></td>
                                                <td>
                                                    <?php if ($field['field_type'] === 'dropdown'): ?>
                                                        <a href="javascript:void(0)" class="ef-value-link ef-edit" title="Edit options">
                                                            <?php echo (int) $opt_count; ?> value<?php echo $opt_count === 1 ? '' : 's'; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ((int) $field['status'] === 1): ?>
                                                        <span class="ef-badge on">Active</span>
                                                    <?php else: ?>
                                                        <span class="ef-badge off">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="ef-actions">
                                                    <a href="javascript:void(0)" class="ef-edit" title="Edit"><i class="feather icon-edit"></i></a>
                                                    <a href="javascript:void(0)" class="ef-del" title="Delete"><i class="feather icon-trash-2"></i></a>
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

    <div class="modal fade" id="extraFieldModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="extraFieldModalTitle">Add Extra Field</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="efId" value="">
                    <div class="form-group">
                        <label for="efMetal">Metal Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="efMetal" required>
                            <?php foreach ($metals as $metal): ?>
                                <option value="<?php echo htmlspecialchars($metal, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $metal === $current_metal ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($metal); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="efDisplayName">Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="efDisplayName" maxlength="255" required placeholder="e.g. Hallmark, Design Code">
                    </div>
                    <div class="form-group">
                        <label for="efFieldType">Field Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="efFieldType" required>
                            <option value="text">Text</option>
                            <option value="dropdown">Dropdown</option>
                        </select>
                    </div>
                    <div class="form-group d-none" id="efDropdownWrap">
                        <label>Dropdown Options <span class="text-danger">*</span></label>
                        <div class="ef-options-wrap">
                            <div class="ef-options-list" id="efOptionsList"></div>
                            <div class="ef-option-add-row">
                                <input type="text" class="form-control form-control-sm" id="efOptionInput" placeholder="Type option and press Add">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="efOptionAddBtn">Add</button>
                            </div>
                        </div>
                        <small class="text-muted">Add one or more values. They will appear in the dropdown on product forms.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label for="efStatus">Status</label>
                        <select class="form-control" id="efStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-save-ef" id="efSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/libs/popper/popper.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script>
    (function () {
        var $ = jQuery;
        var modal = $('#extraFieldModal');
        var currentMetal = <?php echo json_encode($current_metal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var settingsBranchId = $('#settingsBranchId').val() || '';
        var options = [];

        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function toggleDropdownSection() {
            var isDropdown = $('#efFieldType').val() === 'dropdown';
            $('#efDropdownWrap').toggleClass('d-none', !isDropdown);
        }

        function renderOptionsList() {
            var $list = $('#efOptionsList');
            $list.empty();
            if (!options.length) {
                $list.append('<div class="text-muted small">No options added yet.</div>');
                return;
            }
            options.forEach(function (val, idx) {
                var $item = $('<div class="ef-option-item"></div>');
                $item.append('<span>' + escapeHtml(val) + '</span>');
                $item.append('<button type="button" title="Remove" data-idx="' + idx + '">&times;</button>');
                $list.append($item);
            });
        }

        function resetForm() {
            $('#efId').val('');
            $('#efMetal').val(currentMetal);
            $('#efDisplayName').val('');
            $('#efFieldType').val('text');
            $('#efStatus').val('1');
            $('#efOptionInput').val('');
            options = [];
            renderOptionsList();
            toggleDropdownSection();
            $('#extraFieldModalTitle').text('Add Extra Field');
        }

        function openEditFromRow($tr) {
            var id = $tr.data('id');
            var metal = $tr.attr('data-metal') || currentMetal;
            var display = $tr.attr('data-display') || '';
            var type = $tr.attr('data-type') || 'text';
            var status = String($tr.data('status') || '1');
            var optsRaw = $tr.attr('data-options') || '[]';
            try { options = JSON.parse(optsRaw); } catch (e) { options = []; }
            if (!Array.isArray(options)) options = [];

            $('#efId').val(id);
            $('#efMetal').val(metal);
            $('#efDisplayName').val(display);
            $('#efFieldType').val(type);
            $('#efStatus').val(status);
            renderOptionsList();
            toggleDropdownSection();
            $('#extraFieldModalTitle').text('Edit Extra Field');
            modal.modal('show');
        }

        function addOptionFromInput() {
            var val = $.trim($('#efOptionInput').val());
            if (!val) return;
            if (options.indexOf(val) === -1) {
                options.push(val);
            }
            $('#efOptionInput').val('').focus();
            renderOptionsList();
        }

        function buildStatusCell(status) {
            return status === 1 || status === '1'
                ? '<span class="ef-badge on">Active</span>'
                : '<span class="ef-badge off">Inactive</span>';
        }

        function buildValueCell(type, opts) {
            if (type !== 'dropdown') {
                return '<span class="text-muted">—</span>';
            }
            var count = opts.length;
            return '<a href="javascript:void(0)" class="ef-value-link ef-edit" title="Edit options">'
                + count + ' value' + (count === 1 ? '' : 's') + '</a>';
        }

        function upsertTableRow(field) {
            if (!field || !field.id) return;
            var $body = $('#extraFieldsBody');
            $body.find('.ef-empty-row').remove();
            $body.find('.ef-no-results-row').remove();

            var opts = field.dropdown_options || [];
            var type = field.field_type || 'text';
            var typeLabel = type === 'dropdown' ? 'Dropdown' : 'Text';
            var status = parseInt(field.status, 10) || 0;
            var optsJson = JSON.stringify(opts);

            var $existing = $body.find('tr[data-id="' + field.id + '"]');
            var rowHtml = ''
                + '<td>' + escapeHtml(field.display_name) + '</td>'
                + '<td>' + escapeHtml(typeLabel) + '</td>'
                + '<td>' + buildValueCell(type, opts) + '</td>'
                + '<td>' + buildStatusCell(status) + '</td>'
                + '<td class="ef-actions">'
                + '<a href="javascript:void(0)" class="ef-edit" title="Edit"><i class="feather icon-edit"></i></a>'
                + '<a href="javascript:void(0)" class="ef-del" title="Delete"><i class="feather icon-trash-2"></i></a>'
                + '</td>';

            if ($existing.length) {
                $existing.attr('data-metal', field.metal_type);
                $existing.attr('data-display', field.display_name);
                $existing.attr('data-type', type);
                $existing.attr('data-status', status);
                $existing.attr('data-options', optsJson);
                $existing.html(rowHtml);
            } else if (field.metal_type === currentMetal) {
                var $tr = $('<tr></tr>');
                $tr.attr('data-id', field.id);
                $tr.attr('data-metal', field.metal_type);
                $tr.attr('data-display', field.display_name);
                $tr.attr('data-type', type);
                $tr.attr('data-status', status);
                $tr.attr('data-options', optsJson);
                $tr.html(rowHtml);
                $body.append($tr);
            }

            if (field.metal_type !== currentMetal) {
                window.location.href = 'extra-fields.php?metal=' + encodeURIComponent(field.metal_type);
            }
            filterTable();
        }

        function filterTable() {
            var q = $.trim($('#efSearch').val()).toLowerCase();
            var visible = 0;
            var $body = $('#extraFieldsBody');
            $body.find('.ef-no-results-row').remove();

            $body.find('tr').each(function () {
                var $tr = $(this);
                if ($tr.hasClass('ef-empty-row')) {
                    return;
                }
                var text = $tr.text().toLowerCase();
                var show = q === '' || text.indexOf(q) !== -1;
                $tr.toggle(show);
                if (show) {
                    visible++;
                }
            });

            if (q !== '' && visible === 0 && !$body.find('.ef-empty-row').length) {
                $body.append(
                    '<tr class="ef-no-results-row"><td colspan="5" class="ef-empty">No extra fields match your search.</td></tr>'
                );
            }
        }

        $('#btnAddExtraField').on('click', function () {
            resetForm();
            modal.modal('show');
        });

        $('#efFieldType').on('change', toggleDropdownSection);

        $('#efOptionAddBtn').on('click', addOptionFromInput);
        $('#efOptionInput').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addOptionFromInput();
            }
        });

        $('#efOptionsList').on('click', 'button[data-idx]', function () {
            var idx = parseInt($(this).attr('data-idx'), 10);
            if (!isNaN(idx)) {
                options.splice(idx, 1);
                renderOptionsList();
            }
        });

        $('#extraFieldsBody').on('click', '.ef-edit', function () {
            openEditFromRow($(this).closest('tr'));
        });

        $('#extraFieldsBody').on('click', '.ef-del', function () {
            var $tr = $(this).closest('tr');
            var id = $tr.data('id');
            if (!id || !confirm('Delete this extra field?')) return;

            $.post('ajax/delete-extra-field.php', {
                id: id,
                settings_branch_id: settingsBranchId
            }).done(function (res) {
                if (res && res.success) {
                    $tr.remove();
                    $('#extraFieldsBody .ef-no-results-row').remove();
                    if (!$('#extraFieldsBody tr:not(.ef-no-results-row)').length) {
                        $('#extraFieldsBody').html(
                            '<tr class="ef-empty-row"><td colspan="5" class="ef-empty">No extra fields for '
                            + escapeHtml(currentMetal) + '. Click <strong>+ Add</strong> to create one.</td></tr>'
                        );
                    } else {
                        filterTable();
                    }
                } else {
                    alert((res && res.message) ? res.message : 'Could not delete.');
                }
            }).fail(function () {
                alert('Could not delete. Please try again.');
            });
        });

        $('#efSaveBtn').on('click', function () {
            var displayName = $.trim($('#efDisplayName').val());
            var fieldType = $('#efFieldType').val();
            if (!displayName) {
                alert('Display name is required.');
                return;
            }
            if (fieldType === 'dropdown' && !options.length) {
                alert('Add at least one dropdown option.');
                return;
            }

            var $btn = $(this).prop('disabled', true);
            $.post('ajax/save-extra-field.php', {
                id: $('#efId').val(),
                metal_type: $('#efMetal').val(),
                display_name: displayName,
                field_type: fieldType,
                dropdown_options: JSON.stringify(options),
                status: $('#efStatus').val(),
                settings_branch_id: settingsBranchId
            }).done(function (res) {
                if (res && res.success && res.field) {
                    modal.modal('hide');
                    upsertTableRow(res.field);
                } else {
                    alert((res && res.message) ? res.message : 'Could not save.');
                }
            }).fail(function () {
                alert('Could not save. Please try again.');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        modal.on('hidden.bs.modal', function () {
            if (!$('#efId').val()) {
                resetForm();
            }
        });

        $('#efSearch').on('input', filterTable);
    })();
    </script>
</body>
</html>
