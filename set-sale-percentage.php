<?php

session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
require_once __DIR__ . '/includes/auragold_sale_percent_settings.php';
require_once __DIR__ . '/includes/auragold_extra_fields_schema.php';

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
auragold_ensure_tbl_sale_percent_settings($conn);

$metal_suffix = function_exists('auragold_master_list_sql_suffix')
    ? auragold_master_list_sql_suffix($conn, 'tbl_metal')
    : '';
$metals_db = getList('SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ' . $metal_suffix . ' ORDER BY id ASC');
if (!is_array($metals_db) || $metals_db === []) {
    $fallback = function_exists('auragold_extra_field_metals') ? auragold_extra_field_metals() : ['Gold', 'Silver', 'Platinum', 'Diamond & Stones', 'Imitation Or Watches', 'Other Or Services'];
    $metals_db = [];
    foreach ($fallback as $idx => $name) {
        $metals_db[] = ['id' => $idx + 1, 'display_name' => $name, 'system_name' => ''];
    }
}

$tax_modes = auragold_sale_percent_tax_modes();
$making_types = auragold_sale_percent_making_types();
$apply_making_modes = auragold_sale_percent_apply_making_modes();
$all_rules = auragold_get_sale_percent_settings($conn, (int) $settings_branch_id, null, false);
usort($all_rules, static function ($a, $b) {
    $so = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
    return $so !== 0 ? $so : ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
});

$product_metal_map = [];
$product_ids = [];
foreach ($all_rules as $rule) {
    if (($rule['scope_type'] ?? '') === 'product' && (int) ($rule['product_id'] ?? 0) > 0) {
        $product_ids[(int) $rule['product_id']] = true;
    }
}
if ($product_ids !== []) {
    $pid_sql = implode(',', array_map('intval', array_keys($product_ids)));
    $prows = getList(
        'SELECT p.id AS product_id, MIN(m.display_name) AS metal_type, MIN(m.id) AS metal_id
         FROM tbl_products p
         INNER JOIN tbl_product_characteristics pc ON p.id = pc.product_id AND pc.status = 1
         INNER JOIN tbl_metal m ON pc.metal_id = m.id
         WHERE p.id IN (' . $pid_sql . ')
         GROUP BY p.id'
    );
    if (is_array($prows)) {
        foreach ($prows as $prow) {
            $pid = (int) ($prow['product_id'] ?? 0);
            if ($pid > 0) {
                $product_metal_map[$pid] = [
                    'metal_type' => trim((string) ($prow['metal_type'] ?? '')),
                    'metal_id' => (int) ($prow['metal_id'] ?? 0),
                ];
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Set Sale Percentage - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        :root { --ssp-navy: #11294b; --ssp-navy-dark: #0d1f38; }
        .ssp-page { padding: 20px 24px; width: 100%; max-width: none; margin: 0; box-sizing: border-box; }
        .ssp-page h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
        .ssp-hint { font-size: 13px; color: #64748b; margin: 6px 0 0; max-width: 760px; }
        .ssp-top-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
        .ssp-toolbar-right { display: flex; flex-wrap: nowrap; align-items: center; gap: 10px; margin-left: auto; }
        .ssp-search-wrap { position: relative; width: 280px; min-width: 220px; }
        .ssp-search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #64748b; pointer-events: none; }
        .ssp-search-wrap input {
            width: 100%; height: 38px; padding: 8px 12px 8px 38px; border: 1px solid #cbd5e1; border-radius: 8px;
            font-size: 13px; box-sizing: border-box;
        }
        .ssp-btn-add {
            background: linear-gradient(135deg, var(--ssp-navy), var(--ssp-navy-dark)); color: #fff; border: none;
            padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;
        }
        .ssp-table-wrap { border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .ssp-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 0; }
        .ssp-table th, .ssp-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        .ssp-table th { background: #f8fafc; font-weight: 600; color: #374151; white-space: nowrap; }
        .ssp-table tbody tr:last-child td { border-bottom: none; }
        .ssp-empty { padding: 32px 16px; text-align: center; color: #94a3b8; }
        .ssp-badge-on { background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .ssp-badge-off { background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .ssp-badge-scope { background: #e0e7ff; color: #3730a3; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .ssp-actions a { color: var(--ssp-navy); margin-right: 8px; }
        .ssp-actions a.ssp-del { color: #dc2626; }
        .ssp-muted { color: #94a3b8; font-style: italic; }
        #sspModal .modal-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        #sspModal .btn-save-ssp { background: var(--ssp-navy); border-color: var(--ssp-navy); }
        .ssp-product-search-results {
            position: absolute; left: 0; right: 0; top: 100%; z-index: 20; max-height: 220px; overflow-y: auto;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); display: none;
        }
        .ssp-product-search-results.show { display: block; }
        .ssp-product-search-item { padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        .ssp-product-search-item:hover { background: #f8fafc; }
        .ssp-product-search-wrap { position: relative; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;max-width:none;width:100%;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-masters-tabs.php'; ?>
                    <input type="hidden" name="settings_branch_id" id="settingsBranchId" value="<?php echo (int) $settings_branch_id; ?>">
                    <div class="ssp-page">
                        <div class="ssp-top-bar">
                            <div>
                                <h1>Set Sale Percentage</h1>
                                <p class="ssp-hint">Configure default sale markup by product. Select a metal first, then choose a product from that metal. Product rules override metal-wide rules. Choose whether the percentage applies on amount <strong>without tax</strong> or <strong>with tax</strong>.</p>
                            </div>
                            <div class="ssp-toolbar-right">
                                <div class="ssp-search-wrap">
                                    <i class="feather icon-search"></i>
                                    <input type="search" id="sspSearch" placeholder="Search rules…" autocomplete="off">
                                </div>
                                <button type="button" class="ssp-btn-add" id="btnAddSspRule">+ Add Rule</button>
                            </div>
                        </div>

                        <div class="ssp-table-wrap">
                            <table class="ssp-table" id="sspRulesTable">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th style="width:90px;">Type</th>
                                        <th>Metal</th>
                                        <th>Product</th>
                                        <th style="width:120px;">Sale %</th>
                                        <th>Amount basis</th>
                                        <th>Making Type</th>
                                        <th style="width:110px;">Making Rate</th>
                                        <th>Apply making on</th>
                                        <th style="width:90px;">Status</th>
                                        <th style="width:70px;">Order</th>
                                        <th style="width:90px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="sspRulesBody">
                                    <?php if ($all_rules === []): ?>
                                        <tr class="ssp-empty-row"><td colspan="12" class="ssp-empty">No rules yet. Click <strong>+ Add Rule</strong>.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($all_rules as $idx => $item): ?>
                                            <?php
                                            $scope = (string) ($item['scope_type'] ?? 'metal');
                                            $is_product = $scope === 'product';
                                            $pid = (int) ($item['product_id'] ?? 0);
                                            $metal_type = trim((string) ($item['metal_type'] ?? ''));
                                            $metal_id = 0;
                                            if ($is_product && $pid > 0 && isset($product_metal_map[$pid])) {
                                                if ($metal_type === '') {
                                                    $metal_type = (string) ($product_metal_map[$pid]['metal_type'] ?? '');
                                                }
                                                $metal_id = (int) ($product_metal_map[$pid]['metal_id'] ?? 0);
                                            }
                                            ?>
                                            <tr data-id="<?php echo (int) $item['id']; ?>"
                                                data-scope="<?php echo htmlspecialchars($scope, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-metal="<?php echo htmlspecialchars($metal_type, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-metal-id="<?php echo (int) $metal_id; ?>"
                                                data-product-id="<?php echo $pid; ?>"
                                                data-product-name="<?php echo htmlspecialchars((string) ($item['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-sale-percent="<?php echo htmlspecialchars((string) $item['sale_percent'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-tax-mode="<?php echo htmlspecialchars($item['tax_mode'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-making-type="<?php echo htmlspecialchars((string) ($item['making_type'] ?? 'Fix'), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-making-rate="<?php echo htmlspecialchars((string) ($item['making_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-apply-making-on="<?php echo htmlspecialchars((string) ($item['apply_making_on'] ?? 'sales'), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-status="<?php echo (int) $item['status']; ?>"
                                                data-sort-order="<?php echo (int) $item['sort_order']; ?>">
                                                <td><?php echo (int) $idx + 1; ?></td>
                                                <td><span class="ssp-badge-scope"><?php echo $is_product ? 'Product' : 'Metal'; ?></span></td>
                                                <td><?php echo $metal_type !== '' ? htmlspecialchars($metal_type) : '<span class="ssp-muted">—</span>'; ?></td>
                                                <td>
                                                    <?php if ($is_product): ?>
                                                        <?php echo htmlspecialchars($item['product_name'] !== '' ? $item['product_name'] : ('Product #' . $pid)); ?>
                                                    <?php else: ?>
                                                        <span class="ssp-muted">All products</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format((float) $item['sale_percent'], 2); ?>%</td>
                                                <td><?php echo htmlspecialchars($item['tax_mode_label']); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($item['making_type'] ?? 'Fix')); ?></td>
                                                <td><?php echo number_format((float) ($item['making_rate'] ?? 0), 2); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($item['apply_making_on_label'] ?? 'Sales')); ?></td>
                                                <td><?php echo (int) $item['status'] === 1 ? '<span class="ssp-badge-on">Active</span>' : '<span class="ssp-badge-off">Inactive</span>'; ?></td>
                                                <td><?php echo (int) $item['sort_order']; ?></td>
                                                <td class="ssp-actions">
                                                    <a href="javascript:void(0)" class="ssp-edit" title="Edit"><i class="feather icon-edit"></i></a>
                                                    <a href="javascript:void(0)" class="ssp-del" title="Delete"><i class="feather icon-trash-2"></i></a>
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

    <div class="modal fade" id="sspModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sspModalTitle">Sale Percentage Rule</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="sspId" value="">
                    <input type="hidden" id="sspScope" value="product">
                    <div class="form-group">
                        <label for="sspMetal">Metal <span class="text-danger">*</span></label>
                        <select class="form-control" id="sspMetal">
                            <option value="">Select metal</option>
                            <?php foreach ($metals_db as $metal): ?>
                                <option value="<?php echo htmlspecialchars($metal['display_name'], ENT_QUOTES, 'UTF-8'); ?>" data-metal-id="<?php echo (int) $metal['id']; ?>">
                                    <?php echo htmlspecialchars($metal['display_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group ssp-field-product" style="display:none;">
                        <label for="sspProductSearch">Product <span class="text-danger">*</span></label>
                        <div class="ssp-product-search-wrap">
                            <input type="hidden" id="sspProductId" value="">
                            <input type="text" class="form-control" id="sspProductSearch" placeholder="Search and select product in chosen metal…" autocomplete="off" disabled required>
                            <div class="ssp-product-search-results" id="sspProductResults"></div>
                        </div>
                        <small class="form-text text-muted">Search by product name, then click a result to select it (required).</small>
                    </div>
                    <div class="form-group">
                        <label for="sspPercent">Sale percentage <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="sspPercent" min="0" step="0.01" value="0">
                            <div class="input-group-append"><span class="input-group-text">%</span></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="sspTaxMode">Calculate on</label>
                        <select class="form-control" id="sspTaxMode">
                            <?php foreach ($tax_modes as $mode_key => $mode_label): ?>
                                <option value="<?php echo htmlspecialchars($mode_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mode_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Without tax uses Net/Purchase amount. With tax uses Net amount + tax before applying the sale %.</small>
                    </div>
                    <div class="form-group">
                        <label for="sspMakingType">Making Type</label>
                        <select class="form-control" id="sspMakingType">
                            <?php foreach ($making_types as $mt): ?>
                                <option value="<?php echo htmlspecialchars($mt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sspMakingRate">Making Rate</label>
                        <input type="number" class="form-control" id="sspMakingRate" min="0" step="0.01" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="sspApplyMakingOn">Apply making on</label>
                        <select class="form-control" id="sspApplyMakingOn">
                            <?php foreach ($apply_making_modes as $am_key => $am_label): ?>
                                <option value="<?php echo htmlspecialchars($am_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($am_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Choose whether making charges apply on Sales or Purchase.</small>
                    </div>
                    <div class="form-group">
                        <label for="sspSortOrder">Sort order</label>
                        <input type="number" class="form-control" id="sspSortOrder" value="0" step="1">
                    </div>
                    <div class="form-group mb-0">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="sspStatus" checked>
                            <label class="form-check-label" for="sspStatus">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="sspClearBtn">Clear</button>
                    <button type="button" class="btn btn-primary btn-save-ssp" id="sspSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/libs/popper/popper.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script>
    (function () {
        var $ = jQuery;
        var modal = $('#sspModal');
        var taxModeLabels = <?php echo json_encode($tax_modes, JSON_UNESCAPED_UNICODE); ?>;
        var applyMakingLabels = <?php echo json_encode($apply_making_modes, JSON_UNESCAPED_UNICODE); ?>;
        var settingsBranchId = $('#settingsBranchId').val() || '';
        var productSearchTimer = null;

        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function statusBadge(active) {
            return active
                ? '<span class="ssp-badge-on">Active</span>'
                : '<span class="ssp-badge-off">Inactive</span>';
        }

        function taxLabel(mode) {
            return taxModeLabels[mode] || mode || '';
        }

        function applyMakingLabel(mode) {
            return applyMakingLabels[mode] || mode || 'Sales';
        }

        function getSelectedMetalId() {
            var $opt = $('#sspMetal option:selected');
            return parseInt($opt.attr('data-metal-id') || '0', 10) || 0;
        }

        function updateProductSectionVisibility() {
            var metal = String($('#sspMetal').val() || '').trim();
            var show = metal !== '';
            $('.ssp-field-product').toggle(show);
            $('#sspProductSearch').prop('disabled', !show);
            if (!show) {
                $('#sspProductId').val('');
                $('#sspProductSearch').val('');
                $('#sspProductResults').removeClass('show').empty();
            }
        }

        function getSelectedProductId() {
            return parseInt($('#sspProductId').val() || '0', 10) || 0;
        }

        function renumberRows($body) {
            $body.find('tr[data-id]').each(function (i) {
                $(this).children('td').eq(0).text(i + 1);
            });
        }

        function clearForm() {
            $('#sspId').val('');
            $('#sspScope').val('product');
            $('#sspMetal').val('');
            $('#sspProductId').val('');
            $('#sspProductSearch').val('');
            $('#sspProductResults').removeClass('show').empty();
            $('#sspPercent').val('0');
            $('#sspTaxMode').val('without_tax');
            $('#sspMakingType').val('Fix');
            $('#sspMakingRate').val('0.00');
            $('#sspApplyMakingOn').val('sales');
            $('#sspSortOrder').val('0');
            $('#sspStatus').prop('checked', true);
            updateProductSectionVisibility();
        }

        function openModal(title) {
            updateProductSectionVisibility();
            $('#sspModalTitle').text(title || 'Sale Percentage Rule');
            modal.modal('show');
        }

        function buildRuleRowHtml(row) {
            var scope = row.scope_type || 'metal';
            var isProduct = scope === 'product';
            var metalType = row.metal_type || '';
            var productId = parseInt(row.product_id || '0', 10) || 0;
            var productName = row.product_name || '';
            var metalId = parseInt(row.metal_id || '0', 10) || 0;
            var productCell = isProduct
                ? escapeHtml(productName || ('Product #' + productId))
                : '<span class="ssp-muted">All products</span>';
            var metalCell = metalType !== '' ? escapeHtml(metalType) : '<span class="ssp-muted">—</span>';

            return '<tr data-id="' + row.id + '"'
                + ' data-scope="' + scope + '"'
                + ' data-metal="' + escapeHtml(metalType) + '"'
                + ' data-metal-id="' + metalId + '"'
                + ' data-product-id="' + productId + '"'
                + ' data-product-name="' + escapeHtml(productName) + '"'
                + ' data-sale-percent="' + row.sale_percent + '"'
                + ' data-tax-mode="' + row.tax_mode + '"'
                + ' data-making-type="' + escapeHtml(row.making_type || 'Fix') + '"'
                + ' data-making-rate="' + (row.making_rate != null ? row.making_rate : 0) + '"'
                + ' data-apply-making-on="' + escapeHtml(row.apply_making_on || 'sales') + '"'
                + ' data-status="' + row.status + '"'
                + ' data-sort-order="' + row.sort_order + '">'
                + '<td></td>'
                + '<td><span class="ssp-badge-scope">' + (isProduct ? 'Product' : 'Metal') + '</span></td>'
                + '<td>' + metalCell + '</td>'
                + '<td>' + productCell + '</td>'
                + '<td>' + parseFloat(row.sale_percent).toFixed(2) + '%</td>'
                + '<td>' + escapeHtml(taxLabel(row.tax_mode)) + '</td>'
                + '<td>' + escapeHtml(row.making_type || 'Fix') + '</td>'
                + '<td>' + parseFloat(row.making_rate || 0).toFixed(2) + '</td>'
                + '<td>' + escapeHtml(applyMakingLabel(row.apply_making_on || 'sales')) + '</td>'
                + '<td>' + statusBadge(row.status === 1) + '</td>'
                + '<td>' + row.sort_order + '</td>'
                + '<td class="ssp-actions"><a href="javascript:void(0)" class="ssp-edit"><i class="feather icon-edit"></i></a> '
                + '<a href="javascript:void(0)" class="ssp-del"><i class="feather icon-trash-2"></i></a></td></tr>';
        }

        $('#btnAddSspRule').on('click', function () {
            clearForm();
            openModal('Add Sale Percentage Rule');
        });

        $('#sspClearBtn').on('click', function () {
            clearForm();
        });

        $('#sspMetal').on('change', function () {
            $('#sspProductId').val('');
            $('#sspProductSearch').val('');
            $('#sspProductResults').removeClass('show').empty();
            updateProductSectionVisibility();
        });

        $(document).on('click', '.ssp-edit', function () {
            var $tr = $(this).closest('tr');
            var scope = $tr.attr('data-scope') || 'product';
            $('#sspId').val($tr.attr('data-id') || '');
            $('#sspScope').val(scope === 'metal' ? 'product' : scope);
            $('#sspPercent').val($tr.attr('data-sale-percent') || '0');
            $('#sspTaxMode').val($tr.attr('data-tax-mode') || 'without_tax');
            $('#sspMakingType').val($tr.attr('data-making-type') || 'Fix');
            $('#sspMakingRate').val(parseFloat($tr.attr('data-making-rate') || '0').toFixed(2));
            $('#sspApplyMakingOn').val($tr.attr('data-apply-making-on') || 'sales');
            $('#sspSortOrder').val($tr.attr('data-sort-order') || '0');
            $('#sspStatus').prop('checked', String($tr.attr('data-status')) === '1');
            $('#sspMetal').val($tr.attr('data-metal') || '');
            if (scope === 'product') {
                $('#sspProductId').val($tr.attr('data-product-id') || '');
                $('#sspProductSearch').val($tr.attr('data-product-name') || '');
            } else {
                $('#sspProductId').val('');
                $('#sspProductSearch').val('');
            }
            updateProductSectionVisibility();
            openModal('Edit Sale Percentage Rule');
        });

        $(document).on('click', '.ssp-del', function () {
            var $tr = $(this).closest('tr');
            var id = $tr.attr('data-id');
            if (!id || !confirm('Delete this rule?')) return;
            $.post('ajax/sale-percentage-settings.php', {
                action: 'delete',
                id: id,
                settings_branch_id: settingsBranchId
            }).done(function (res) {
                if (res && res.success) {
                    var $body = $tr.closest('tbody');
                    $tr.remove();
                    renumberRows($body);
                    if ($body.find('tr[data-id]').length === 0) {
                        $body.html('<tr class="ssp-empty-row"><td colspan="12" class="ssp-empty">No rules yet. Click <strong>+ Add Rule</strong>.</td></tr>');
                    }
                } else {
                    alert((res && res.message) ? res.message : 'Delete failed');
                }
            }).fail(function () { alert('Delete failed'); });
        });

        $('#sspSaveBtn').on('click', function () {
            var metal = String($('#sspMetal').val() || '').trim();
            if (!metal) {
                alert('Please select a metal first.');
                return;
            }
            var productId = getSelectedProductId();
            if (productId <= 0) {
                alert('Please search and select a product from the list.');
                $('#sspProductSearch').focus();
                return;
            }
            var payload = {
                action: 'save',
                id: $('#sspId').val(),
                scope_type: 'product',
                metal_type: metal,
                product_id: $('#sspProductId').val(),
                product_name: $('#sspProductSearch').val(),
                sale_percent: $('#sspPercent').val(),
                tax_mode: $('#sspTaxMode').val(),
                making_type: $('#sspMakingType').val(),
                making_rate: $('#sspMakingRate').val(),
                apply_making_on: $('#sspApplyMakingOn').val(),
                sort_order: $('#sspSortOrder').val(),
                status: $('#sspStatus').is(':checked') ? 1 : 0,
                settings_branch_id: settingsBranchId
            };
            $.post('ajax/sale-percentage-settings.php', payload).done(function (res) {
                if (!res || !res.success || !res.row) {
                    alert((res && res.message) ? res.message : 'Save failed');
                    return;
                }
                var row = res.row;
                row.metal_type = metal;
                row.metal_id = getSelectedMetalId();
                var $body = $('#sspRulesBody');
                $body.find('.ssp-empty-row').remove();
                var $existing = $body.find('tr[data-id="' + row.id + '"]');
                var html = buildRuleRowHtml(row);
                if ($existing.length) {
                    $existing.replaceWith(html);
                } else {
                    $body.append(html);
                }
                renumberRows($body);
                modal.modal('hide');
            }).fail(function () { alert('Save failed'); });
        });

        $('#sspSearch').on('input', function () {
            var term = String(this.value || '').toLowerCase().trim();
            $('#sspRulesBody tr[data-id]').each(function () {
                var text = $(this).text().toLowerCase();
                $(this).toggle(term === '' || text.indexOf(term) !== -1);
            });
        });

        $('#sspProductSearch').on('input', function () {
            var q = String(this.value || '').trim();
            var metalId = getSelectedMetalId();
            $('#sspProductId').val('');
            var $res = $('#sspProductResults');
            if (productSearchTimer) clearTimeout(productSearchTimer);
            if (!metalId) {
                $res.removeClass('show').empty();
                return;
            }
            if (q.length < 1) {
                $res.removeClass('show').empty();
                return;
            }
            productSearchTimer = setTimeout(function () {
                $.getJSON('ajax/search-products.php', { search: q, limit: 20, metal_id: metalId }).done(function (data) {
                    $res.empty();
                    var list = (data && data.products) ? data.products : (Array.isArray(data) ? data : []);
                    if (!list.length) {
                        $res.html('<div class="ssp-product-search-item text-muted">No products found for this metal</div>');
                    } else {
                        list.forEach(function (p) {
                            var label = (p.name || '') + (p.metal_name ? ' — ' + p.metal_name : '');
                            $('<div class="ssp-product-search-item"></div>')
                                .text(label)
                                .attr('data-id', p.id)
                                .attr('data-name', p.name || '')
                                .appendTo($res);
                        });
                    }
                    $res.addClass('show');
                });
            }, 250);
        });

        $(document).on('click', '.ssp-product-search-item', function () {
            var id = $(this).attr('data-id');
            if (!id) return;
            $('#sspProductId').val(id);
            $('#sspProductSearch').val($(this).attr('data-name') || $(this).text());
            $('#sspProductResults').removeClass('show').empty();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.ssp-product-search-wrap').length) {
                $('#sspProductResults').removeClass('show');
            }
        });
    })();
    </script>
</body>
</html>
