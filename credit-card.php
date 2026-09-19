<?php

session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
require_once __DIR__ . '/includes/auragold_credit_card_schema.php';

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
auragold_ensure_tbl_credit_card($conn);

$credit_cards = auragold_get_credit_cards($conn, $settings_branch_id);

/** Account ledger list for A/C Group & Comm. A/C dropdowns */
$cc_ledger_options = [];
$cc_ledger_seen = [];
$cc_static_ledgers = [
    'Cash', 'Bank', 'CUSTOMER LEDGER', 'Sundry Debtors', 'Sundry Creditors',
    'Sales', 'Purchase', 'Capital', 'Expenses',
];
foreach ($cc_static_ledgers as $static_name) {
    $cc_ledger_options[] = [
        'id' => $static_name,
        'text' => $static_name,
        'name' => $static_name,
        'mobile_no' => '',
    ];
    $cc_ledger_seen[$static_name] = true;
}
$ledger_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
if ($ledger_tbl && mysqli_num_rows($ledger_tbl) > 0) {
    mysqli_free_result($ledger_tbl);
    $ledger_rows = getList(
        "SELECT DISTINCT customer_name AS name FROM tbl_customer_ledger
         WHERE status = 1 AND TRIM(IFNULL(customer_name,'')) != ''
         ORDER BY customer_name ASC"
    );
    if (is_array($ledger_rows)) {
        foreach ($ledger_rows as $lr) {
            $name = trim((string) ($lr['name'] ?? ''));
            if ($name === '' || isset($cc_ledger_seen[$name])) {
                continue;
            }
            $mobile_no = '';
            $cust = getRecord("SELECT mobile_no FROM tbl_customers WHERE name = '" . esc($name) . "' AND status = 1 LIMIT 1");
            if ($cust && !empty($cust['mobile_no'])) {
                $mobile_no = (string) $cust['mobile_no'];
            }
            $cc_ledger_options[] = [
                'id' => $name,
                'text' => $name . ($mobile_no !== '' ? ' - ' . $mobile_no : ''),
                'name' => $name,
                'mobile_no' => $mobile_no,
            ];
            $cc_ledger_seen[$name] = true;
        }
    }
} elseif ($ledger_tbl) {
    mysqli_free_result($ledger_tbl);
}

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Credit Card - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <link rel="stylesheet" href="assets/libs/select2/select2.css">
    <style>
        :root {
            --cc-navy: #11294b;
            --cc-navy-dark: #0d1f38;
            --cc-border: rgba(17, 41, 75, 0.35);
        }
        .credit-card-page {
            padding: 20px 24px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        .credit-card-page h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
        .cc-top-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .cc-search-wrap {
            position: relative;
            width: 280px;
            min-width: 220px;
            flex: 0 1 280px;
        }
        .cc-search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 15px;
            pointer-events: none;
            z-index: 1;
        }
        .cc-search-wrap input {
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
        .cc-search-wrap input::placeholder { color: #94a3b8; opacity: 1; }
        .cc-search-wrap input:focus {
            outline: none;
            border-color: var(--cc-navy);
            box-shadow: 0 0 0 3px rgba(17, 41, 75, 0.12);
        }
        .cc-toolbar-right {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }
        .cc-btn-add {
            background: linear-gradient(135deg, var(--cc-navy) 0%, var(--cc-navy-dark) 100%);
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .cc-btn-add:hover { opacity: 0.95; }
        .cc-table-wrap {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            background: #fff;
        }
        .cc-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 0; }
        .cc-table th, .cc-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        .cc-table th { background: #f8fafc; font-weight: 600; color: #374151; white-space: nowrap; }
        .cc-table tbody tr:last-child td { border-bottom: none; }
        .cc-table tbody tr:hover { background: #fafbfc; }
        .cc-empty { padding: 32px 16px; text-align: center; color: #94a3b8; }
        .cc-actions a { color: var(--cc-navy); margin-right: 8px; }
        .cc-actions a.cc-del { color: #dc2626; }
        .cc-check { width: 18px; height: 18px; pointer-events: none; }
        .cc-pct { font-variant-numeric: tabular-nums; }
        #creditCardModal .modal-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        #creditCardModal .modal-content,
        #creditCardModal .modal-body { overflow: visible; }
        #creditCardModal .btn-save-cc { background: var(--cc-navy); border-color: var(--cc-navy); }
        #creditCardModal .select2-container { width: 100% !important; z-index: 10060; }
        .select2-container--open .select2-dropdown { z-index: 10061 !important; }
        .cc-ledger-option { display: flex; justify-content: space-between; gap: 12px; padding: 2px 0; }
        .cc-ledger-option .cc-ledger-name { font-weight: 600; color: #0f172a; }
        .cc-ledger-option .cc-ledger-mobile { color: #64748b; font-size: 12px; white-space: nowrap; }
        .select2-results__option .cc-ledger-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 4px 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 4px;
        }
        .layout-content:has(.credit-card-page) > .container-fluid,
        .layout-content:has(.credit-card-page) .set-software-wrapper,
        .layout-content:has(.credit-card-page) .set-software-main {
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
                    <div class="credit-card-page">
                        <div class="cc-top-bar">
                            <h1>Credit Card</h1>
                            <div class="cc-toolbar-right">
                                <div class="cc-search-wrap">
                                    <i class="feather icon-search"></i>
                                    <input type="search" id="ccSearch" placeholder="Search credit cards…" autocomplete="off" aria-label="Search credit cards">
                                </div>
                                <button type="button" class="cc-btn-add" id="btnAddCreditCard">+ Credit Card</button>
                            </div>
                        </div>

                        <div class="cc-table-wrap">
                            <table class="cc-table" id="creditCardTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Account Group</th>
                                        <th>Commission Group</th>
                                        <th style="width:110px;">Commission %</th>
                                        <th style="width:80px;">Status</th>
                                        <th style="width:80px;">Default</th>
                                        <th style="width:70px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="creditCardBody">
                                    <?php if ($credit_cards === []): ?>
                                        <tr class="cc-empty-row">
                                            <td colspan="7" class="cc-empty">No credit cards yet. Click <strong>+ Credit Card</strong> to add one.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($credit_cards as $card): ?>
                                            <tr data-id="<?php echo (int) $card['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($card['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-account-group="<?php echo htmlspecialchars($card['account_group'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-commission-account="<?php echo htmlspecialchars($card['commission_account'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-commission-percent="<?php echo htmlspecialchars((string) $card['commission_percent'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-status="<?php echo (int) $card['status']; ?>"
                                                data-is-default="<?php echo (int) $card['is_default']; ?>">
                                                <td><?php echo htmlspecialchars($card['name']); ?></td>
                                                <td><?php echo htmlspecialchars($card['account_group']); ?></td>
                                                <td><?php echo htmlspecialchars($card['commission_account']); ?></td>
                                                <td class="cc-pct"><?php echo htmlspecialchars(rtrim(rtrim(number_format((float) $card['commission_percent'], 4, '.', ''), '0'), '.')); ?></td>
                                                <td><input type="checkbox" class="cc-check"<?php echo (int) $card['status'] === 1 ? ' checked' : ''; ?> disabled></td>
                                                <td><input type="checkbox" class="cc-check"<?php echo (int) $card['is_default'] === 1 ? ' checked' : ''; ?> disabled></td>
                                                <td class="cc-actions">
                                                    <a href="javascript:void(0)" class="cc-edit" title="Edit"><i class="feather icon-edit"></i></a>
                                                    <a href="javascript:void(0)" class="cc-del" title="Delete"><i class="feather icon-trash-2"></i></a>
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

    <div class="modal fade" id="creditCardModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="creditCardModalTitle">Credit Card</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ccId" value="">
                    <div class="form-group">
                        <label for="ccName">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ccName" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="ccAccountGroup">A/C Group <span class="text-danger">*</span></label>
                        <select class="form-control cc-ledger-select" id="ccAccountGroup" required></select>
                    </div>
                    <div class="form-group">
                        <label for="ccCommissionAccount">Comm. A/C <span class="text-danger">*</span></label>
                        <select class="form-control cc-ledger-select" id="ccCommissionAccount" required></select>
                    </div>
                    <div class="form-group">
                        <label for="ccCommissionPercent">Comm. % <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="ccCommissionPercent" min="0" step="0.0001" value="0" required>
                    </div>
                    <div class="form-group mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="ccStatus" checked>
                            <label class="form-check-label" for="ccStatus">Active</label>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="ccDefault">
                            <label class="form-check-label" for="ccDefault">Default</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="ccClearBtn">Clear</button>
                    <button type="button" class="btn btn-primary btn-save-cc" id="ccSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/libs/popper/popper.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/libs/select2/select2.js"></script>
    <script>
    (function () {
        var $ = jQuery;
        var modal = $('#creditCardModal');
        var settingsBranchId = $('#settingsBranchId').val() || '';
        var ledgerData = <?php echo json_encode($cc_ledger_options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;

        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function formatPct(val) {
            var n = parseFloat(val);
            if (isNaN(n)) return '0';
            var s = n.toFixed(4).replace(/\.?0+$/, '');
            return s === '' ? '0' : s;
        }

        function ledgerSelect2Config() {
            return {
                width: '100%',
                placeholder: 'Select account ledger…',
                allowClear: true,
                minimumResultsForSearch: 0,
                dropdownParent: modal,
                data: ledgerData,
                matcher: function (params, data) {
                    if ($.trim(params.term) === '') {
                        return data;
                    }
                    var term = params.term.toLowerCase();
                    var name = (data.name || data.text || data.id || '').toLowerCase();
                    var mobile = (data.mobile_no || '').toLowerCase();
                    if (name.indexOf(term) !== -1 || mobile.indexOf(term) !== -1) {
                        return data;
                    }
                    return null;
                },
                templateResult: function (item) {
                    if (item.loading) return item.text;
                    if (!item.id) return item.text;
                    var $wrap = $('<div class="cc-ledger-option"></div>');
                    $wrap.append('<span class="cc-ledger-name">' + escapeHtml(item.name || item.text) + '</span>');
                    $wrap.append('<span class="cc-ledger-mobile">' + escapeHtml(item.mobile_no || '—') + '</span>');
                    return $wrap;
                },
                templateSelection: function (item) {
                    return item.name || item.text || item.id || '';
                },
                language: {
                    searching: function () { return 'Searching…'; },
                    noResults: function () { return 'No ledger found'; }
                }
            };
        }

        function initLedgerSelects() {
            if (!$.fn.select2) return;
            $('.cc-ledger-select').each(function () {
                var $sel = $(this);
                if ($sel.hasClass('select2-hidden-accessible')) {
                    $sel.select2('destroy');
                }
                $sel.select2(ledgerSelect2Config());
            });
        }

        function setLedgerSelectValue($sel, value) {
            if (!value) {
                $sel.val(null).trigger('change');
                return;
            }
            if ($sel.find('option[value="' + value.replace(/"/g, '\\"') + '"]').length === 0) {
                var opt = new Option(value, value, true, true);
                $sel.append(opt);
            }
            $sel.val(value).trigger('change');
        }

        function resetForm() {
            $('#ccId').val('');
            $('#ccName').val('');
            $('#ccCommissionPercent').val('0');
            $('#ccStatus').prop('checked', true);
            $('#ccDefault').prop('checked', false);
            setLedgerSelectValue($('#ccAccountGroup'), '');
            setLedgerSelectValue($('#ccCommissionAccount'), '');
            $('#creditCardModalTitle').text('Credit Card');
        }

        function openEditFromRow($tr) {
            $('#ccId').val($tr.data('id') || '');
            $('#ccName').val($tr.attr('data-name') || '');
            $('#ccCommissionPercent').val($tr.attr('data-commission-percent') || '0');
            $('#ccStatus').prop('checked', String($tr.data('status')) === '1');
            $('#ccDefault').prop('checked', String($tr.data('is-default')) === '1');
            setLedgerSelectValue($('#ccAccountGroup'), $tr.attr('data-account-group') || '');
            setLedgerSelectValue($('#ccCommissionAccount'), $tr.attr('data-commission-account') || '');
            $('#creditCardModalTitle').text('Edit Credit Card');
            modal.modal('show');
        }

        function buildRowHtml(card) {
            var statusChecked = parseInt(card.status, 10) === 1 ? ' checked' : '';
            var defaultChecked = parseInt(card.is_default, 10) === 1 ? ' checked' : '';
            return '<tr data-id="' + card.id + '"'
                + ' data-name="' + escapeHtml(card.name) + '"'
                + ' data-account-group="' + escapeHtml(card.account_group) + '"'
                + ' data-commission-account="' + escapeHtml(card.commission_account) + '"'
                + ' data-commission-percent="' + escapeHtml(String(card.commission_percent)) + '"'
                + ' data-status="' + card.status + '"'
                + ' data-is-default="' + card.is_default + '">'
                + '<td>' + escapeHtml(card.name) + '</td>'
                + '<td>' + escapeHtml(card.account_group) + '</td>'
                + '<td>' + escapeHtml(card.commission_account) + '</td>'
                + '<td class="cc-pct">' + escapeHtml(formatPct(card.commission_percent)) + '</td>'
                + '<td><input type="checkbox" class="cc-check"' + statusChecked + ' disabled></td>'
                + '<td><input type="checkbox" class="cc-check"' + defaultChecked + ' disabled></td>'
                + '<td class="cc-actions">'
                + '<a href="javascript:void(0)" class="cc-edit" title="Edit"><i class="feather icon-edit"></i></a> '
                + '<a href="javascript:void(0)" class="cc-del" title="Delete"><i class="feather icon-trash-2"></i></a>'
                + '</td></tr>';
        }

        function upsertRow(card) {
            var $body = $('#creditCardBody');
            $body.find('.cc-empty-row').remove();
            var $existing = $body.find('tr[data-id="' + card.id + '"]');
            var html = buildRowHtml(card);
            if ($existing.length) {
                $existing.replaceWith(html);
            } else {
                $body.prepend(html);
            }
            if (parseInt(card.is_default, 10) === 1) {
                $body.find('tr').each(function () {
                    var $tr = $(this);
                    if (String($tr.data('id')) !== String(card.id)) {
                        $tr.attr('data-is-default', '0');
                        $tr.find('td:nth-child(6) input').prop('checked', false);
                    }
                });
            }
            filterTable();
        }

        function filterTable() {
            var q = $.trim($('#ccSearch').val()).toLowerCase();
            $('#creditCardBody tr').each(function () {
                if ($(this).hasClass('cc-empty-row')) return;
                var text = $(this).text().toLowerCase();
                $(this).toggle(q === '' || text.indexOf(q) !== -1);
            });
        }

        $('#btnAddCreditCard').on('click', function () {
            resetForm();
            modal.modal('show');
        });

        $('#ccClearBtn').on('click', function () {
            resetForm();
        });

        modal.on('shown.bs.modal', function () {
            initLedgerSelects();
            $('#ccName').trigger('focus');
        });

        $('#ccSaveBtn').on('click', function () {
            var name = $.trim($('#ccName').val());
            var accountGroup = $.trim($('#ccAccountGroup').val() || '');
            var commissionAccount = $.trim($('#ccCommissionAccount').val() || '');
            var commissionPercent = $.trim($('#ccCommissionPercent').val());

            if (!name) { alert('Name is required.'); return; }
            if (!accountGroup) { alert('A/C Group is required.'); return; }
            if (!commissionAccount) { alert('Comm. A/C is required.'); return; }
            if (commissionPercent === '' || isNaN(parseFloat(commissionPercent))) {
                alert('Comm. % is required.');
                return;
            }

            var $btn = $('#ccSaveBtn').prop('disabled', true);
            $.post('ajax/credit-card.php', {
                action: 'save',
                id: $('#ccId').val(),
                name: name,
                account_group: accountGroup,
                commission_account: commissionAccount,
                commission_percent: commissionPercent,
                status: $('#ccStatus').is(':checked') ? 1 : 0,
                is_default: $('#ccDefault').is(':checked') ? 1 : 0,
                settings_branch_id: settingsBranchId
            }).done(function (res) {
                if (res && res.success && res.card) {
                    upsertRow(res.card);
                    modal.modal('hide');
                } else {
                    alert((res && res.message) ? res.message : 'Could not save credit card.');
                }
            }).fail(function () {
                alert('Could not save credit card. Please try again.');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $('#creditCardBody').on('click', '.cc-edit', function (e) {
            e.preventDefault();
            openEditFromRow($(this).closest('tr'));
        });

        $('#creditCardBody').on('click', '.cc-del', function (e) {
            e.preventDefault();
            var $tr = $(this).closest('tr');
            var id = parseInt($tr.data('id'), 10) || 0;
            if (id <= 0) return;
            if (!confirm('Delete this credit card?')) return;
            $.post('ajax/credit-card.php', {
                action: 'delete',
                id: id,
                settings_branch_id: settingsBranchId
            }).done(function (res) {
                if (res && res.success) {
                    $tr.remove();
                    if (!$('#creditCardBody tr').length) {
                        $('#creditCardBody').html('<tr class="cc-empty-row"><td colspan="7" class="cc-empty">No credit cards yet. Click <strong>+ Credit Card</strong> to add one.</td></tr>');
                    }
                } else {
                    alert((res && res.message) ? res.message : 'Could not delete credit card.');
                }
            }).fail(function () {
                alert('Could not delete credit card. Please try again.');
            });
        });

        $('#ccSearch').on('input', filterTable);
    })();
    </script>
</body>
</html>
