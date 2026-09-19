<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_extra_fields_schema.php';
require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();

$column_catalog = function_exists('getInvoicePrintSaleInvoiceColumnCatalog') ? getInvoicePrintSaleInvoiceColumnCatalog() : [];
$branch_for_extra_fields = function_exists('auragold_resolve_extra_fields_branch_id')
    ? auragold_resolve_extra_fields_branch_id($settings_branch_id)
    : (int) $settings_branch_id;
if (function_exists('auragold_merge_invoice_print_extra_field_columns')) {
    auragold_merge_invoice_print_extra_field_columns($column_catalog, $conn, $branch_for_extra_fields);
}
$all_columns = [];
foreach ($column_catalog as $_col_key => $_col_meta) {
    $all_columns[$_col_key] = is_array($_col_meta) ? (string) ($_col_meta['label'] ?? $_col_key) : (string) $_col_meta;
}
if ($all_columns === []) {
    $all_columns = function_exists('getInvoicePrintSaleInvoiceColumnLabels')
        ? getInvoicePrintSaleInvoiceColumnLabels()
        : [
            'sr_no' => 'Sr No',
            'item_name' => 'Item Name',
            'design_no' => 'Design No',
            'huid' => 'HUID',
            'category' => 'Category',
            'gross_weight' => 'Gross Weight',
            'less_weight' => 'Less Weight',
            'net_weight' => 'Net Weight',
            'purity_karat' => 'Purity / Karat',
            'rate' => 'Rate',
            'making_charge' => 'Making Charge',
            'diamond_amount' => 'Diamond Amount',
            'stone_amount' => 'Stone Amount',
            'discount' => 'Discount',
            'amount' => 'Amount',
            'short_code' => 'Short Code',
        ];
}
$column_group_labels = function_exists('getInvoicePrintSaleInvoiceColumnGroupLabels') ? getInvoicePrintSaleInvoiceColumnGroupLabels() : [];

$setting_types = getInvoicePrintSettingTypes();
$current_setting_type = isset($_GET['type']) ? trim((string)$_GET['type']) : 'default';
if (!in_array($current_setting_type, $setting_types, true)) {
    $current_setting_type = 'default';
}
$setting_type_labels = [
    'default' => 'Default Setting',
    'sale_invoice' => 'Sale Invoice Print Setting',
    'purchase_invoice' => 'Purchase Invoice Print Setting',
    'sale_order' => 'Sale Order',
    'purchase_order' => 'Purchase Order',
    'purchase_quotation' => 'Purchase Quotation',
    'sale_quotation' => 'Sale Quotation',
    'sale_return' => 'Sale Return',
    'purchase_return' => 'Purchase Return',
    'sale_fixing_direct' => 'Sale Fixing Direct',
    'payment_voucher' => 'Payment Voucher',
    'receipt_voucher' => 'Receipt Voucher',
    'advance_payment' => 'Advance Payment',
    'metal_to_amount' => 'Metal to Amount',
    'amount_to_metal' => 'Amount to Metal',
];
$settings = getInvoicePrintSettingsByType($current_setting_type);
$current_layout_type = normalizeInvoicePrintLayoutType($settings['layout_type'] ?? 'A4');
$current_page_orientation = normalizeInvoicePrintPageOrientation($settings['page_orientation'] ?? 'portrait');
$design_templates = function_exists('getInvoicePrintDesignTemplates') ? getInvoicePrintDesignTemplates() : [];
$current_design = $settings['design_template'] ?? 'template_1';
$structure_templates = function_exists('getInvoicePrintStructureTemplates') ? getInvoicePrintStructureTemplates() : [];
$current_invoice_template = $settings['invoice_template'] ?? 'template_classic';
$visible_keys = $settings['sale_invoice_columns'];
if (!is_array($visible_keys)) {
    $visible_keys = array_keys($all_columns);
}
foreach ($visible_keys as $_vk) {
    if (!isset($all_columns[$_vk])) {
        $all_columns[$_vk] = ucwords(str_replace('_', ' ', (string) $_vk));
    }
}
$available_keys = array_values(array_diff(array_keys($all_columns), $visible_keys));

$column_header_labels = $settings['column_header_labels'] ?? [];
if (!is_array($column_header_labels)) {
    $column_header_labels = [];
}
$summary_label_overrides = $settings['summary_label_overrides'] ?? [];
if (!is_array($summary_label_overrides)) {
    $summary_label_overrides = [];
}
$summary_row_order = isset($settings['summary_row_order']) && is_array($settings['summary_row_order'])
    ? $settings['summary_row_order']
    : (function_exists('getInvoicePrintSummaryRowOrder') ? getInvoicePrintSummaryRowOrder($settings) : ['total', 'advance_amount', 'total_before_vat', 'vat_5_label', 'total_including_vat', 'less_scrap', 'balance_amount']);
$t6_column_labels = $settings['t6_column_labels'] ?? [];
if (is_string($t6_column_labels)) {
    $t6_column_labels = @json_decode($t6_column_labels, true) ?: [];
}
if (!is_array($t6_column_labels)) {
    $t6_column_labels = [];
}
$t6_col_meta = [
    'sno' => 'SNo', 'tag_no' => 'Tag No', 'item' => 'Item', 'hsn' => 'HSN', 'gross_wt' => 'Gross Wt',
    'net_wt' => 'Net Wt', 'dia_wt' => 'Dia Wt', 'cst_wt' => 'Cst Wt', 'amt' => 'Amt', 'tot_amt' => 'Tot Amt',
];
$summary_row_meta = [
    'total' => 'TOTAL (summary line with weights)',
    'advance_amount' => 'Advance Amount',
    'total_before_vat' => 'Total Before VAT',
    'vat_5_label' => 'VAT 5%',
    'total_including_vat' => 'Total Including VAT',
    'less_scrap' => 'Less: Scrap Purchased',
    'balance_amount' => 'Balance Amount (highlighted)',
];
$default_doc_title_hint = function_exists('getInvoicePrintDefaultDocumentTitle') ? getInvoicePrintDefaultDocumentTitle($current_setting_type) : 'INVOICE';
$email_message_subject = trim((string) ($settings['email_message_subject'] ?? ''));
$email_message_body = (string) ($settings['email_message_body'] ?? '');
$current_doc_label = $setting_type_labels[$current_setting_type] ?? $current_setting_type;

$preview_invoice_id = 0;
$last_inv = getRecord("SELECT id FROM tbl_sale_invoices ORDER BY id DESC LIMIT 1");
if ($last_inv) {
    $preview_invoice_id = (int) $last_inv['id'];
}
$preview_uses_sample_invoice = ($preview_invoice_id <= 0);
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Invoice Print Setting - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php';?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <style>
        .ips-invoice-print-layout { align-items: stretch; min-height: calc(100vh - 100px); }
        .ips-invoice-print-layout .set-software-main { overflow: auto; flex: 1; min-width: 0; }
        .ips-page { padding: 10px 12px; max-width: 100%; margin: 0 auto; }
        .ips-title { font-size: 1.15rem; font-weight: 700; color: #1a365d; margin-bottom: 6px; }
        .ips-page-desc { font-size: 0.75rem; color: #718096; margin-bottom: 10px; }
        .ips-cards-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .ips-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; }
        .ips-card.ips-card-full { grid-column: 1 / -1; }
        .ips-card-header { background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%); color: #fff; padding: 8px 12px; font-weight: 600; font-size: 0.8rem; }
        .ips-card-body { padding: 10px 12px; }
        .ips-columns-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .ips-column-panel { min-width: 0; }
        .ips-column-list { min-height: 140px; max-height: 280px; overflow-y: auto; border: 2px dashed #cbd5e0; border-radius: 6px; padding: 6px; background: #f8fafc; }
        .ips-column-item .ips-col-group { display: block; font-size: 0.65rem; color: #718096; margin-top: 1px; }
        .ips-column-search { margin-bottom: 8px; width: 100%; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.8rem; }
        .ips-column-search:focus { outline: none; border-color: #2c5282; box-shadow: 0 0 0 2px rgba(44, 82, 130, 0.15); }
        .ips-column-list.visible-list { border-color: #2c5282; background: #edf2f7; }
        .ips-column-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 8px; margin-bottom: 4px; cursor: grab; font-size: 0.75rem; color: #2d3748; }
        .ips-column-item:last-child { margin-bottom: 0; }
        .ips-column-item:hover { border-color: #2c5282; background: #ebf8ff; }
        .ips-column-item.sortable-ghost { opacity: 0.5; }
        .ips-column-item.sortable-drag { cursor: grabbing; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .ips-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; color: #718096; margin-bottom: 4px; font-weight: 600; }
        .ips-toggles { display: grid; grid-template-columns: 1fr; gap: 4px; }
        .ips-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 4px 8px; background: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0; }
        .ips-toggle-row label { margin: 0; font-size: 0.75rem; color: #2d3748; }
        .ips-switch { position: relative; width: 36px; height: 18px; background: #cbd5e0; border-radius: 9px; cursor: pointer; transition: background 0.2s; flex-shrink: 0; }
        .ips-switch.on { background: #2c5282; }
        .ips-switch::after { content: ''; position: absolute; width: 14px; height: 14px; border-radius: 50%; background: #fff; top: 2px; left: 2px; transition: transform 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.2); }
        .ips-switch.on::after { transform: translateX(18px); }
        .ips-switch input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
        .ips-toggle-row { cursor: pointer; }
        .ips-layout-options { display: flex; gap: 8px; flex-wrap: wrap; }
        .ips-layout-options.ips-layout-options--paper .ips-layout-option { flex: 1; min-width: 72px; max-width: 120px; }
        .ips-layout-option { flex: 1; min-width: 80px; padding: 6px 8px; border: 2px solid #e2e8f0; border-radius: 6px; cursor: pointer; text-align: center; background: #fff; transition: all 0.2s; font-size: 0.8rem; }
        .ips-layout-option:hover { border-color: #cbd5e0; }
        .ips-layout-option.selected { border-color: #2c5282; background: #ebf8ff; color: #1a365d; }
        .ips-layout-option input { display: none; }
        .ips-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .ips-btn { padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 0.8rem; border: none; cursor: pointer; transition: all 0.2s; }
        .ips-btn-save { background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%); color: #fff; }
        .ips-btn-save:hover { box-shadow: 0 2px 8px rgba(26,54,93,0.35); }
        .ips-btn-preview { background: linear-gradient(135deg, #d4af37 0%, #c9a227 100%); color: #1a365d; }
        .ips-btn-preview:hover { box-shadow: 0 2px 8px rgba(212,175,55,0.35); }
        .ips-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .ips-toast { position: fixed; bottom: 16px; right: 16px; padding: 10px 16px; border-radius: 8px; background: #1a365d; color: #fff; font-weight: 500; font-size: 0.85rem; box-shadow: 0 2px 12px rgba(0,0,0,0.2); z-index: 9999; display: none; }
        .ips-toast.show { display: block; animation: ips-fadeIn 0.3s ease; }
        @keyframes ips-fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .ips-form-grid { display: grid; grid-template-columns: 1fr; gap: 6px; }
        .ips-field { margin-bottom: 6px; }
        .ips-field:last-child { margin-bottom: 0; }
        .ips-field label { display: block; font-size: 0.7rem; font-weight: 600; color: #2d3748; margin-bottom: 2px; }
        .ips-field input[type=text], .ips-field input[type=email], .ips-field textarea { width: 100%; padding: 4px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 0.75rem; }
        .ips-field textarea { min-height: 44px; resize: vertical; }
        .ips-field .ips-hint { font-size: 0.65rem; color: #718096; margin-top: 2px; }
        .ips-logo-upload { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .ips-logo-preview { width: 48px; height: 48px; border: 1px dashed #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; flex-shrink: 0; }
        .ips-logo-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .ips-logo-preview.empty { color: #94a3b8; font-size: 0.6rem; text-align: center; padding: 4px; }
        .ips-upload-btn { padding: 4px 10px; background: #2c5282; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 0.7rem; }
        .ips-upload-btn:hover { background: #1a365d; }
        .ips-upload-btn input { display: none; }
        .ips-template-card.selected { border-color: #2c5282 !important; box-shadow: 0 2px 8px rgba(44,82,130,0.25); }
        .ips-template-card:hover { border-color: #cbd5e0; }
        .ips-template-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)) !important; gap: 8px !important; margin-bottom: 8px !important; }
        .ips-template-preview { height: 32px !important; }
        .ips-mb-0 { margin-bottom: 0 !important; }
        .ips-small { font-size: 0.7rem; }
        .ips-card-body.scroll-card { max-height: 42vh; overflow-y: auto; }
        .ips-lang-option:has(input:checked) { border-color: #2c5282 !important; background: #ebf8ff !important; }
        .ips-col-label-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; }
        .ips-summary-order-list { min-height: 120px; max-height: 220px; }
        .ips-summary-label-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        @media (max-width: 992px) { .ips-cards-row { grid-template-columns: 1fr; } .ips-card-body.scroll-card { max-height: none; } .ips-summary-label-grid { grid-template-columns: 1fr; } }
        .ips-email-editor-wrap { border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; }
        .ips-email-editor-wrap .ql-toolbar { border: none; border-bottom: 1px solid #e2e8f0; border-radius: 6px 6px 0 0; background: #f8fafc; }
        .ips-email-editor-wrap .ql-container { border: none; min-height: 180px; font-size: 0.85rem; }
        .ips-email-editor-wrap .ql-editor { min-height: 180px; }
        .ips-placeholder-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .ips-placeholder-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            background: #ebf8ff;
            color: #2c5282;
            font-size: 0.68rem;
            font-family: ui-monospace, monospace;
            cursor: pointer;
            border: 1px solid #bee3f8;
        }
        .ips-placeholder-tag:hover { background: #bee3f8; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
        <div class="set-software-wrapper ips-invoice-print-layout">
            <?php include 'set-software-sidebar.php'; ?>
            <div class="set-software-main">
                <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                <div class="ips-page">
                                <h1 class="ips-title">Invoice Print Setting</h1>
                                <!-- <p class="ips-page-desc text-muted">Configure print layout per document type. Default used when no specific settings.</p> -->

                                <!-- Row 1: Setting For | Design template | Invoice template -->
                                <div class="ips-cards-row">
                                    <div class="ips-card">
                                        <div class="ips-card-header">Setting For</div>
                                        <div class="ips-card-body">
                                            <div class="ips-field">
                                                <label>Document type</label>
                                                <select id="settingForSelect" class="form-control" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; font-size: 0.75rem; width: 100%;">
                                                    <?php foreach ($setting_types as $st): ?>
                                                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $current_setting_type === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($setting_type_labels[$st] ?? $st); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="ips-hint">Save to store under selected type.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!empty($design_templates)): ?>
                                    <div class="ips-card">
                                        <div class="ips-card-header">Design template</div>
                                        <div class="ips-card-body">
                                            <div class="ips-template-grid">
                                                <?php foreach ($design_templates as $tmpl): ?>
                                                <label class="ips-template-card <?php echo ($current_design === $tmpl['id']) ? 'selected' : ''; ?>" style="cursor: pointer; border: 2px solid #e2e8f0; border-radius: 6px; overflow: hidden; background: #fff; transition: all 0.2s; margin: 0;">
                                                    <input type="radio" name="design_template" value="<?php echo htmlspecialchars($tmpl['id']); ?>" <?php echo $current_design === $tmpl['id'] ? 'checked' : ''; ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
                                                    <div class="ips-template-preview" style="background: <?php echo $tmpl['header_bg']; ?>;"></div>
                                                    <div style="height: 6px; background: <?php echo $tmpl['badge_bg']; ?>;"></div>
                                                    <div style="padding: 4px 6px;">
                                                        <div style="font-weight: 600; font-size: 0.7rem; color: #1a365d;"><?php echo htmlspecialchars($tmpl['name']); ?></div>
                                                    </div>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="ips-btn ips-btn-preview ips-small" id="ipsBtnPreviewDesign" title="<?php echo $preview_uses_sample_invoice ? 'Sample invoice (create a sale invoice to preview real data)' : 'Open print preview with selected design'; ?>">Preview design</button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($structure_templates)): ?>
                                    <div class="ips-card">
                                        <div class="ips-card-header">Invoice layout</div>
                                        <div class="ips-card-body">
                                            <div class="ips-field ips-mb-0">
                                                <label class="ips-label">Template</label>
                                                <select name="invoice_template" id="ipsInvoiceTemplate" class="form-control" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; font-size: 0.75rem; width: 100%;">
                                                    <?php foreach ($structure_templates as $tid => $tname): ?>
                                                    <option value="<?php echo htmlspecialchars($tid); ?>" <?php echo $current_invoice_template === $tid ? 'selected' : ''; ?>><?php echo htmlspecialchars($tname); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="button" class="ips-btn ips-btn-preview ips-small mt-2" id="ipsBtnPreviewTemplate" title="<?php echo $preview_uses_sample_invoice ? 'Sample invoice (create a sale invoice to preview real data)' : ''; ?>">Preview template</button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Row 2: Column configuration full width -->
                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Column configuration — drag between lists to show/hide; order = print order</div>
                                    <div class="ips-card-body">
                                        <input type="search" id="ipsColumnSearch" class="ips-column-search" placeholder="Search available columns (e.g. short code, metal, wastage)…" autocomplete="off">
                                        <div class="ips-columns-row">
                                            <div class="ips-column-panel">
                                                <div class="ips-label">Available (hidden)</div>
                                                <div id="availableColumnsList" class="ips-column-list">
                                                    <?php foreach ($available_keys as $key):
                                                        $grp = $column_catalog[$key]['group'] ?? '';
                                                        $grp_label = $grp !== '' ? ($column_group_labels[$grp] ?? ucfirst($grp)) : '';
                                                    ?>
                                                    <div class="ips-column-item" data-key="<?php echo htmlspecialchars($key); ?>" data-group="<?php echo htmlspecialchars($grp_label); ?>">
                                                        <?php echo htmlspecialchars($all_columns[$key]); ?>
                                                        <?php if ($grp_label !== ''): ?><span class="ips-col-group"><?php echo htmlspecialchars($grp_label); ?></span><?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="ips-column-panel">
                                                <div class="ips-label">Visible (on invoice)</div>
                                                <div id="visibleColumnsList" class="ips-column-list visible-list">
                                                    <?php foreach ($visible_keys as $key):
                                                        if (!isset($all_columns[$key])) continue;
                                                        $grp = $column_catalog[$key]['group'] ?? '';
                                                        $grp_label = $grp !== '' ? ($column_group_labels[$grp] ?? ucfirst($grp)) : '';
                                                    ?>
                                                    <div class="ips-column-item" data-key="<?php echo htmlspecialchars($key); ?>" data-group="<?php echo htmlspecialchars($grp_label); ?>">
                                                        <?php echo htmlspecialchars($all_columns[$key]); ?>
                                                        <?php if ($grp_label !== ''): ?><span class="ips-col-group"><?php echo htmlspecialchars($grp_label); ?></span><?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <!-- Custom column titles + summary totals (per document type) -->
                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Custom column titles (optional)</div>
                                    <div class="ips-card-body">
                                        <p class="ips-hint">Leave blank to use the default label for the selected language. Saved per document type above.</p>
                                        <div class="ips-col-label-grid">
                                            <?php foreach ($all_columns as $ck => $cl): ?>
                                            <div class="ips-field">
                                                <label><?php echo htmlspecialchars($cl); ?></label>
                                                <input type="text" class="ips-col-label-input" data-col-key="<?php echo htmlspecialchars($ck); ?>" placeholder="Custom title" value="<?php echo htmlspecialchars($column_header_labels[$ck] ?? ''); ?>">
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                </div>
                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Totals section — row order &amp; labels</div>
                                    <div class="ips-card-body">
                                        <p class="ips-hint">Drag to change print order. Custom labels override the default text for this document type.</p>
                                        <div class="ips-columns-row">
                                            <div class="ips-column-panel" style="grid-column: 1 / -1;">
                                                <div class="ips-label">Summary rows order (top = first on print)</div>
                                                <div id="summaryOrderList" class="ips-column-list visible-list ips-summary-order-list">
                                                    <?php foreach ($summary_row_order as $sk): if (!isset($summary_row_meta[$sk])) continue; ?>
                                                    <div class="ips-column-item" data-summary-key="<?php echo htmlspecialchars($sk); ?>"><?php echo htmlspecialchars($summary_row_meta[$sk]); ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ips-label" style="margin-top: 10px;">Custom labels (optional)</div>
                                        <div class="ips-summary-label-grid">
                                            <?php foreach ($summary_row_meta as $sk => $sl): ?>
                                            <div class="ips-field">
                                                <label><?php echo htmlspecialchars($sl); ?></label>
                                                <input type="text" class="ips-summary-label-input" data-summary-key="<?php echo htmlspecialchars($sk); ?>" placeholder="Default from language / type" value="<?php echo htmlspecialchars($summary_label_overrides[$sk] ?? ''); ?>">
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <!-- Row 3: Header | Footer | Advertise banner -->
                                <div class="ips-cards-row">
                                <div class="ips-card">
                                    <div class="ips-card-header">Header options</div>
                                    <div class="ips-card-body scroll-card">
                                        <div class="ips-toggles">
                                            <div class="ips-toggle-row">
                                                <label>Show top header block (logo / company / TRN)</label>
                                                <div class="ips-switch <?php echo (($settings['header_section_enabled'] ?? '1') === '1') ? 'on' : ''; ?>" data-toggle="header_section_enabled" onclick="toggleSwitch(this)"><input type="checkbox" name="header_section_enabled" <?php echo (($settings['header_section_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show Company Logo</label>
                                                <div class="ips-switch <?php echo $settings['header_company_logo'] === '1' ? 'on' : ''; ?>" data-toggle="header_company_logo" onclick="toggleSwitch(this)"><input type="checkbox" name="header_company_logo" <?php echo $settings['header_company_logo'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show Company Name</label>
                                                <div class="ips-switch <?php echo $settings['header_company_name'] === '1' ? 'on' : ''; ?>" data-toggle="header_company_name" onclick="toggleSwitch(this)"><input type="checkbox" name="header_company_name" <?php echo $settings['header_company_name'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show GST / TRN Number</label>
                                                <div class="ips-switch <?php echo $settings['header_gst_number'] === '1' ? 'on' : ''; ?>" data-toggle="header_gst_number" onclick="toggleSwitch(this)"><input type="checkbox" name="header_gst_number" <?php echo $settings['header_gst_number'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show Phone</label>
                                                <div class="ips-switch <?php echo $settings['header_phone'] === '1' ? 'on' : ''; ?>" data-toggle="header_phone" onclick="toggleSwitch(this)"><input type="checkbox" name="header_phone" <?php echo $settings['header_phone'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show Invoice Title</label>
                                                <div class="ips-switch <?php echo $settings['header_invoice_title'] === '1' ? 'on' : ''; ?>" data-toggle="header_invoice_title" onclick="toggleSwitch(this)"><input type="checkbox" name="header_invoice_title" <?php echo $settings['header_invoice_title'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                        </div>
                                        <div class="ips-label">Header content</div>
                                        <div class="ips-form-grid">
                                            <div class="ips-field">
                                                <label>Logo</label>
                                                <div class="ips-logo-upload">
                                                    <div class="ips-logo-preview <?php echo empty($settings['company_logo_path']) ? 'empty' : ''; ?>" id="ipsLogoPreview">
                                                        <?php if (!empty($settings['company_logo_path']) && file_exists(dirname(__FILE__) . '/' . $settings['company_logo_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($settings['company_logo_path']); ?>?t=<?php echo time(); ?>" alt="Logo">
                                                        <?php else: ?>
                                                        —
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <label class="ips-upload-btn"><input type="file" id="ipsLogoFile" accept="image/jpeg,image/png,image/gif,image/webp"> File</label>
                                                        <input type="hidden" name="company_logo_path" id="ipsLogoPath" value="<?php echo htmlspecialchars($settings['company_logo_path'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="ips-field"><label>Company</label><input type="text" name="company_name" id="ipsCompanyName" placeholder="Name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>"></div>
                                            <div class="ips-field"><label>Address</label><input type="text" name="company_address" id="ipsCompanyAddress" placeholder="Address" value="<?php echo htmlspecialchars($settings['company_address'] ?? ''); ?>"></div>
                                            <div class="ips-field"><label>GST/TRN</label><input type="text" name="company_gst" id="ipsCompanyGst" placeholder="GST" value="<?php echo htmlspecialchars($settings['company_gst'] ?? ''); ?>"></div>
                                            <div class="ips-field"><label>PAN</label><input type="text" name="company_pan" id="ipsCompanyPan" placeholder="Company PAN (Template 6 header)" value="<?php echo htmlspecialchars($settings['company_pan'] ?? ''); ?>" maxlength="32"></div>
                                            <div class="ips-field"><label>Phone</label><input type="text" name="company_phone" id="ipsCompanyPhone" placeholder="Phone" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>"></div>
                                            <div class="ips-field"><label>Email</label><input type="email" name="company_email" id="ipsCompanyEmail" placeholder="Email" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>"></div>
                                            <div class="ips-field"><label>Invoice title</label><input type="text" name="invoice_title" id="ipsInvoiceTitle" placeholder="<?php echo htmlspecialchars($default_doc_title_hint); ?>" value="<?php echo htmlspecialchars($settings['invoice_title'] ?? ''); ?>"><p class="ips-hint">If empty, print uses the default title for this document type (e.g. <strong><?php echo htmlspecialchars($default_doc_title_hint); ?></strong>).</p></div>
                                            <div class="ips-field"><label>Top padding (print)</label><input type="number" name="print_padding_top_mm" id="ipsPaddingTop" min="0" max="80" step="1" placeholder="0" value="<?php echo htmlspecialchars($settings['print_padding_top_mm'] ?? '0'); ?>"><p class="ips-hint">Extra space at the top of the page when printing (millimetres). 0 = none.</p></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="ips-card">
                                    <div class="ips-card-header">Footer options</div>
                                    <div class="ips-card-body scroll-card">
                                        <div class="ips-toggles">
                                            <div class="ips-toggle-row">
                                                <label>Show Terms & Conditions</label>
                                                <div class="ips-switch <?php echo $settings['footer_terms_conditions'] === '1' ? 'on' : ''; ?>" data-toggle="footer_terms_conditions" onclick="toggleSwitch(this)"><input type="checkbox" name="footer_terms_conditions" <?php echo $settings['footer_terms_conditions'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show Authorized Signature</label>
                                                <div class="ips-switch <?php echo $settings['footer_authorized_signature'] === '1' ? 'on' : ''; ?>" data-toggle="footer_authorized_signature" onclick="toggleSwitch(this)"><input type="checkbox" name="footer_authorized_signature" <?php echo $settings['footer_authorized_signature'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show Thank You</label>
                                                <div class="ips-switch <?php echo $settings['footer_thank_you_message'] === '1' ? 'on' : ''; ?>" data-toggle="footer_thank_you_message" onclick="toggleSwitch(this)"><input type="checkbox" name="footer_thank_you_message" <?php echo $settings['footer_thank_you_message'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                        </div>
                                        <div class="ips-label">Footer content</div>
                                        <div class="ips-form-grid">
                                            <div class="ips-field"><label>T&C</label><textarea name="terms_conditions" id="ipsTermsConditions" placeholder="Terms..."><?php echo htmlspecialchars($settings['terms_conditions'] ?? ''); ?></textarea></div>
                                            <div class="ips-field"><label>Signature</label><input type="text" name="authorized_signature" id="ipsAuthorizedSignature" placeholder="Signatory" value="<?php echo htmlspecialchars($settings['authorized_signature'] ?? ''); ?>"></div>
                                            <div class="ips-field"><label>Thank you msg</label><textarea name="thank_you_message" id="ipsThankYouMessage" placeholder="Thank you..."><?php echo htmlspecialchars($settings['thank_you_message'] ?? ''); ?></textarea></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="ips-card">
                                    <div class="ips-card-header">Advertise banner</div>
                                    <div class="ips-card-body scroll-card">
                                        <div class="ips-toggles">
                                            <div class="ips-toggle-row">
                                                <label>Show banner in print</label>
                                                <div class="ips-switch <?php echo ($settings['footer_show_banner'] ?? '0') === '1' ? 'on' : ''; ?>" data-toggle="footer_show_banner" onclick="toggleSwitch(this)"><input type="checkbox" name="footer_show_banner" <?php echo ($settings['footer_show_banner'] ?? '0') === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                        </div>
                                        <div class="ips-label">Banner image</div>
                                        <div class="ips-logo-upload">
                                            <div class="ips-logo-preview <?php echo empty($settings['advertise_banner_path']) ? 'empty' : ''; ?>" id="ipsBannerPreview" style="min-height: 50px; max-height: 70px;">
                                                <?php if (!empty($settings['advertise_banner_path']) && file_exists(dirname(__FILE__) . '/' . $settings['advertise_banner_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($settings['advertise_banner_path']); ?>?t=<?php echo time(); ?>" alt="Banner" style="max-height: 70px;">
                                                <?php else: ?>
                                                —
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <label class="ips-upload-btn"><input type="file" id="ipsBannerFile" accept="image/jpeg,image/png,image/gif,image/webp"> Banner</label>
                                                <input type="hidden" name="advertise_banner_path" id="ipsBannerPath" value="<?php echo htmlspecialchars($settings['advertise_banner_path'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <!-- Email message template (per document type) -->
                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Email message template — <?php echo htmlspecialchars($current_doc_label); ?></div>
                                    <div class="ips-card-body">
                                        <p class="ips-hint">Compose the default email subject and body for this document type. Use placeholders below; they are replaced when sending mail. Saved with the document type selected in <strong>Setting For</strong>.</p>
                                        <div class="ips-field">
                                            <label for="ipsEmailSubject">Email subject</label>
                                            <input type="text" id="ipsEmailSubject" name="email_message_subject" placeholder="e.g. {document_title} {invoice_no} — {company_name}" value="<?php echo htmlspecialchars($email_message_subject); ?>">
                                        </div>
                                        <div class="ips-field" style="margin-top: 10px;">
                                            <label>Email message body</label>
                                            <div class="ips-email-editor-wrap">
                                                <div id="ipsEmailToolbar">
                                                    <span class="ql-formats">
                                                        <select class="ql-header">
                                                            <option selected></option>
                                                            <option value="1"></option>
                                                            <option value="2"></option>
                                                        </select>
                                                    </span>
                                                    <span class="ql-formats">
                                                        <button class="ql-bold"></button>
                                                        <button class="ql-italic"></button>
                                                        <button class="ql-underline"></button>
                                                    </span>
                                                    <span class="ql-formats">
                                                        <button class="ql-list" value="ordered"></button>
                                                        <button class="ql-list" value="bullet"></button>
                                                    </span>
                                                    <span class="ql-formats">
                                                        <button class="ql-link"></button>
                                                    </span>
                                                    <span class="ql-formats">
                                                        <button class="ql-clean"></button>
                                                    </span>
                                                </div>
                                                <div id="ipsEmailEditor"></div>
                                            </div>
                                            <textarea id="ipsEmailBodyHidden" name="email_message_body" style="display:none;"><?php echo htmlspecialchars($email_message_body); ?></textarea>
                                        </div>
                                        <div class="ips-label" style="margin-top: 10px;">Insert placeholder (click to add at cursor)</div>
                                        <div class="ips-placeholder-tags" id="ipsEmailPlaceholders">
                                            <?php
                                            $email_placeholders = [
                                                '{customer_name}' => 'Customer / party name',
                                                '{invoice_no}' => 'Document number',
                                                '{invoice_date}' => 'Document date',
                                                '{grand_total}' => 'Grand total amount',
                                                '{company_name}' => 'Company name from settings',
                                                '{document_title}' => 'Document title on print',
                                                '{document_type}' => 'Document type label',
                                            ];
                                            foreach ($email_placeholders as $token => $hint):
                                            ?>
                                            <span class="ips-placeholder-tag" data-token="<?php echo htmlspecialchars($token); ?>" title="<?php echo htmlspecialchars($hint); ?>"><?php echo htmlspecialchars($token); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <!-- Row 4: Paper & orientation | Languages | Actions -->
                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Paper size &amp; orientation</div>
                                    <div class="ips-card-body">
                                        <div class="ips-label">Paper size</div>
                                        <div class="ips-layout-options ips-layout-options--paper" id="ipsLayoutOptions">
                                            <label class="ips-layout-option <?php echo $current_layout_type === 'A4' ? 'selected' : ''; ?>">
                                                <input type="radio" name="layout_type" value="A4" <?php echo $current_layout_type === 'A4' ? 'checked' : ''; ?>>
                                                <strong>A4</strong><br><small class="text-muted">210×297mm</small>
                                            </label>
                                            <label class="ips-layout-option <?php echo $current_layout_type === 'A5' ? 'selected' : ''; ?>">
                                                <input type="radio" name="layout_type" value="A5" <?php echo $current_layout_type === 'A5' ? 'checked' : ''; ?>>
                                                <strong>A5</strong><br><small class="text-muted">148×210mm</small>
                                            </label>
                                            <label class="ips-layout-option <?php echo $current_layout_type === 'Thermal 80mm' ? 'selected' : ''; ?>">
                                                <input type="radio" name="layout_type" value="Thermal 80mm" <?php echo $current_layout_type === 'Thermal 80mm' ? 'checked' : ''; ?>>
                                                <strong>Thermal</strong><br><small class="text-muted">80mm roll</small>
                                            </label>
                                            <label class="ips-layout-option <?php echo $current_layout_type === 'Letter' ? 'selected' : ''; ?>">
                                                <input type="radio" name="layout_type" value="Letter" <?php echo $current_layout_type === 'Letter' ? 'checked' : ''; ?>>
                                                <strong>Letter</strong><br><small class="text-muted">US 8.5×11"</small>
                                            </label>
                                        </div>
                                        <div class="ips-label" style="margin-top: 10px;">Page orientation</div>
                                        <div class="ips-layout-options" id="ipsOrientationOptions">
                                            <label class="ips-layout-option <?php echo $current_page_orientation === 'portrait' ? 'selected' : ''; ?>">
                                                <input type="radio" name="page_orientation" value="portrait" <?php echo $current_page_orientation === 'portrait' ? 'checked' : ''; ?>>
                                                <strong>Portrait</strong>
                                            </label>
                                            <label class="ips-layout-option <?php echo $current_page_orientation === 'landscape' ? 'selected' : ''; ?>">
                                                <input type="radio" name="page_orientation" value="landscape" <?php echo $current_page_orientation === 'landscape' ? 'checked' : ''; ?>>
                                                <strong>Landscape</strong>
                                            </label>
                                        </div>
                                        <p class="ips-hint" style="margin-top: 8px; margin-bottom: 0;">Orientation applies to A4, A5, and Letter. Thermal stays narrow roll width.</p>
                                    </div>
                                </div>
                                </div>

                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Template 6 (Formal B&amp;W) — used when Invoice layout = Template 6</div>
                                    <div class="ips-card-body scroll-card">
                                        <p class="ips-hint" style="margin-bottom: 10px;">These options apply only to the Naveen-style print. Other layouts ignore them.</p>
                                        <div class="ips-toggles" style="margin-bottom: 10px;">
                                            <div class="ips-toggle-row">
                                                <label>Show vertical grid lines in item table (all cells)</label>
                                                <div class="ips-switch <?php echo (($settings['t6_show_item_vertical_lines'] ?? '0') === '1') ? 'on' : ''; ?>" data-toggle="t6_show_item_vertical_lines" onclick="toggleSwitch(this)"><input type="checkbox" name="t6_show_item_vertical_lines" <?php echo (($settings['t6_show_item_vertical_lines'] ?? '0') === '1') ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show currency symbol on money amounts (from currency master)</label>
                                                <div class="ips-switch <?php echo (($settings['t6_show_currency_on_amounts'] ?? '0') === '1') ? 'on' : ''; ?>" data-toggle="t6_show_currency_on_amounts" onclick="toggleSwitch(this)"><input type="checkbox" name="t6_show_currency_on_amounts" <?php echo (($settings['t6_show_currency_on_amounts'] ?? '0') === '1') ? 'checked' : ''; ?>></div>
                                            </div>
                                        </div>
                                        <div class="ips-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            <div class="ips-field"><label>Rates banner text</label><textarea name="t6_rates_banner_format" id="ipsT6RatesBanner" rows="2" placeholder="Leave empty for default. Use {silver_rate} and {gold_22k}"><?php echo htmlspecialchars($settings['t6_rates_banner_format'] ?? ''); ?></textarea><p class="ips-hint">Example: SILVER RATE {silver_rate} | GOLD 22K {gold_22k}</p></div>
                                            <div class="ips-field"><label>Minimum item rows (blank padding)</label><input type="number" name="t6_min_item_rows" id="ipsT6MinRows" min="1" max="40" value="<?php echo htmlspecialchars($settings['t6_min_item_rows'] ?? '12'); ?>"></div>
                                        </div>
                                        <div class="ips-label" style="margin-top: 8px;">Item table column titles</div>
                                        <div class="ips-col-label-grid">
                                            <?php foreach ($t6_col_meta as $t6k => $t6lab): ?>
                                            <div class="ips-field">
                                                <label><?php echo htmlspecialchars($t6lab); ?></label>
                                                <input type="text" class="ips-t6-col-input" data-t6-col="<?php echo htmlspecialchars($t6k); ?>" placeholder="Default" value="<?php echo htmlspecialchars($t6_column_labels[$t6k] ?? ''); ?>" maxlength="40">
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="ips-label" style="margin-top: 8px;">Summary &amp; balance labels</div>
                                        <div class="ips-summary-label-grid">
                                            <div class="ips-field"><label>Total Gold</label><input type="text" id="ipsT6Gold" value="<?php echo htmlspecialchars($settings['t6_label_gold_total'] ?? 'Total Gold:'); ?>"></div>
                                            <div class="ips-field"><label>Total Silver</label><input type="text" id="ipsT6Silver" value="<?php echo htmlspecialchars($settings['t6_label_silver_total'] ?? 'Total Silver:'); ?>"></div>
                                            <div class="ips-field"><label>Before GST</label><input type="text" id="ipsT6BeforeGst" value="<?php echo htmlspecialchars($settings['t6_label_total_before_gst'] ?? 'Total Value before GST'); ?>"></div>
                                            <div class="ips-field"><label>CGST line (use {pct})</label><input type="text" id="ipsT6Cgst" value="<?php echo htmlspecialchars($settings['t6_label_cgst'] ?? 'CGST @ {pct} %'); ?>"></div>
                                            <div class="ips-field"><label>SGST line (use {pct})</label><input type="text" id="ipsT6Sgst" value="<?php echo htmlspecialchars($settings['t6_label_sgst'] ?? 'SGST @ {pct} %'); ?>"></div>
                                            <div class="ips-field"><label>Total with GST</label><input type="text" id="ipsT6WithGst" value="<?php echo htmlspecialchars($settings['t6_label_total_with_gst'] ?? 'Total Value with GST'); ?>"></div>
                                            <div class="ips-field"><label>Bank transfer</label><input type="text" id="ipsT6Bank" value="<?php echo htmlspecialchars($settings['t6_label_bank_transfer'] ?? 'BANK TRANSFER'); ?>"></div>
                                            <div class="ips-field"><label>Cash</label><input type="text" id="ipsT6CashLab" value="<?php echo htmlspecialchars($settings['t6_label_cash'] ?? 'Cash'); ?>"></div>
                                            <div class="ips-field"><label>Last balance line</label><input type="text" id="ipsT6LastBal" value="<?php echo htmlspecialchars($settings['t6_label_last_balance'] ?? 'Last Amount Balance'); ?>"></div>
                                            <div class="ips-field"><label>Current balance line</label><input type="text" id="ipsT6CurrBal" value="<?php echo htmlspecialchars($settings['t6_label_current_balance'] ?? 'Current Amount Balance'); ?>"></div>
                                            <div class="ips-field"><label>Balance suffix (e.g. Dr)</label><input type="text" id="ipsT6BalSuffix" value="<?php echo htmlspecialchars($settings['t6_balance_suffix'] ?? ' Dr'); ?>"></div>
                                        </div>
                                        <div class="ips-field" style="margin-top: 10px;">
                                            <label>Custom print CSS (optional)</label>
                                            <textarea name="custom_print_css" id="ipsCustomPrintCss" rows="5" placeholder=".invoice.inv-naveen .bill-container { ... }"><?php echo htmlspecialchars($settings['custom_print_css'] ?? ''); ?></textarea>
                                            <p class="ips-hint">Scoped to this document type. Invalid CSS may break the print layout.</p>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Template 7 (GEM Shop Export) — used when Invoice layout = Template 7</div>
                                    <div class="ips-card-body scroll-card">
                                        <p class="ips-hint" style="margin-bottom: 10px;">Export/manufacturer invoice with BILL TO / SHIP TO, HSN table, SGST/CGST totals, and bank footer. Leave bank fields empty to use branch profile bank details.</p>
                                        <div class="ips-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            <div class="ips-field"><label>Company tagline (under logo)</label><input type="text" name="t7_company_tagline" id="ipsT7Tagline" placeholder="e.g. MANUFACTURERS &amp; EXPORTER" value="<?php echo htmlspecialchars($settings['t7_company_tagline'] ?? ''); ?>" maxlength="120"></div>
                                            <div class="ips-field"><label>Minimum item rows (blank padding)</label><input type="number" name="t7_min_item_rows" id="ipsT7MinRows" min="1" max="40" value="<?php echo htmlspecialchars($settings['t7_min_item_rows'] ?? '15'); ?>"></div>
                                            <div class="ips-field"><label>Bank name</label><input type="text" name="t7_bank_name" id="ipsT7BankName" placeholder="BANK OF BARODA" value="<?php echo htmlspecialchars($settings['t7_bank_name'] ?? ''); ?>" maxlength="150"></div>
                                            <div class="ips-field"><label>Account name</label><input type="text" name="t7_bank_account_name" id="ipsT7AcctName" placeholder="Account holder name" value="<?php echo htmlspecialchars($settings['t7_bank_account_name'] ?? ''); ?>" maxlength="150"></div>
                                            <div class="ips-field"><label>Account number</label><input type="text" name="t7_bank_account_no" id="ipsT7AcctNo" placeholder="01140500000082" value="<?php echo htmlspecialchars($settings['t7_bank_account_no'] ?? ''); ?>" maxlength="64"></div>
                                            <div class="ips-field"><label>IFSC</label><input type="text" name="t7_bank_ifsc" id="ipsT7Ifsc" placeholder="BARB0POWERH" value="<?php echo htmlspecialchars($settings['t7_bank_ifsc'] ?? ''); ?>" maxlength="20"></div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Template 8 (ARJUN Retail Tax Invoice) — used when Invoice layout = Template 8</div>
                                    <div class="ips-card-body scroll-card">
                                        <p class="ips-hint" style="margin-bottom: 10px;">Indian retail tax invoice with Particulars/HSN/Purity table, bank details, CGST/SGST split, amount summary, and payment lines. Leave bank fields empty to use branch profile.</p>
                                        <div class="ips-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            <div class="ips-field"><label>Minimum item rows (blank padding)</label><input type="number" name="t8_min_item_rows" id="ipsT8MinRows" min="1" max="40" value="<?php echo htmlspecialchars($settings['t8_min_item_rows'] ?? '2'); ?>"></div>
                                            <div class="ips-field"><label>Bank name</label><input type="text" name="t8_bank_name" id="ipsT8BankName" placeholder="Bandhan Bank" value="<?php echo htmlspecialchars($settings['t8_bank_name'] ?? ''); ?>" maxlength="150"></div>
                                            <div class="ips-field"><label>Account number</label><input type="text" name="t8_bank_account_no" id="ipsT8AcctNo" placeholder="20100007956766" value="<?php echo htmlspecialchars($settings['t8_bank_account_no'] ?? ''); ?>" maxlength="64"></div>
                                            <div class="ips-field"><label>IFSC</label><input type="text" name="t8_bank_ifsc" id="ipsT8Ifsc" placeholder="BDBL0001980" value="<?php echo htmlspecialchars($settings['t8_bank_ifsc'] ?? ''); ?>" maxlength="20"></div>
                                            <div class="ips-field"><label>Branch</label><input type="text" name="t8_bank_branch" id="ipsT8Branch" placeholder="Bhawanipatna" value="<?php echo htmlspecialchars($settings['t8_bank_branch'] ?? ''); ?>" maxlength="120"></div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Template 10 (Monaco Jewellery Sales Invoice) — used when Invoice layout = Template 10</div>
                                    <div class="ips-card-body scroll-card">
                                        <p class="ips-hint" style="margin-bottom: 10px;">A4 landscape bilingual gold invoice (Monaco design). Set paper size to <strong>A4</strong> and orientation to <strong>Landscape</strong>. Company name, address, phone &amp; email use the Company section above. English terms use Footer Terms; Arabic terms below.</p>
                                        <div class="ips-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            <div class="ips-field"><label>Arabic address</label><input type="text" name="t10_address_ar" id="ipsT10AddressAr" placeholder="لاجونا مول، الدوحة، قطر" value="<?php echo htmlspecialchars($settings['t10_address_ar'] ?? ''); ?>" maxlength="200" dir="rtl"></div>
                                            <div class="ips-field"><label>CR No.</label><input type="text" name="t10_cr_no" id="ipsT10CrNo" placeholder="178708" value="<?php echo htmlspecialchars($settings['t10_cr_no'] ?? ''); ?>" maxlength="64"></div>
                                            <div class="ips-field"><label>Website</label><input type="text" name="t10_website" id="ipsT10Website" placeholder="www.monacojewelry.org" value="<?php echo htmlspecialchars($settings['t10_website'] ?? ''); ?>" maxlength="120"></div>
                                            <div class="ips-field"><label>Instagram</label><input type="text" name="t10_instagram" id="ipsT10Instagram" placeholder="monacojewelry.qa" value="<?php echo htmlspecialchars($settings['t10_instagram'] ?? ''); ?>" maxlength="120"></div>
                                            <div class="ips-field"><label>Brand sub-title (script)</label><input type="text" name="t10_brand_sub" id="ipsT10BrandSub" placeholder="jewelry" value="<?php echo htmlspecialchars($settings['t10_brand_sub'] ?? 'jewelry'); ?>" maxlength="60"></div>
                                            <div class="ips-field"><label>Amount currency label</label><input type="text" name="t10_amount_currency_label" id="ipsT10AmtCurr" placeholder="QAR" value="<?php echo htmlspecialchars($settings['t10_amount_currency_label'] ?? 'QAR'); ?>" maxlength="20"></div>
                                            <div class="ips-field"><label>Gold rate karat</label>
                                                <select name="t10_gold_rate_karat" id="ipsT10GoldKarat">
                                                    <?php foreach (['24K', '22K', '21K', '18K'] as $gk): ?>
                                                    <option value="<?php echo $gk; ?>" <?php echo ($settings['t10_gold_rate_karat'] ?? '24K') === $gk ? 'selected' : ''; ?>><?php echo $gk; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="ips-field"><label>Minimum item rows (blank padding)</label><input type="number" name="t10_min_item_rows" id="ipsT10MinRows" min="1" max="40" value="<?php echo htmlspecialchars($settings['t10_min_item_rows'] ?? '5'); ?>"></div>
                                            <div class="ips-field" style="grid-column: 1 / -1;"><label>Arabic terms / conditions</label><textarea name="t10_terms_arabic" id="ipsT10TermsAr" rows="4" placeholder="الشروط والأحكام..." dir="rtl" style="width:100%;"><?php echo htmlspecialchars($settings['t10_terms_arabic'] ?? ''); ?></textarea></div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Template 11 (Heron UAE Tax Invoice) — used when Invoice layout = Template 11</div>
                                    <div class="ips-card-body scroll-card">
                                        <p class="ips-hint" style="margin-bottom: 10px;">A4 portrait UAE tax invoice (Heron design). Set paper size to <strong>A4</strong> and orientation to <strong>Portrait</strong>. Company name, TRN, phone &amp; address use the Company section above.</p>
                                        <div class="ips-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            <div class="ips-field"><label>Brand sub-title</label><input type="text" name="t11_brand_sub" id="ipsT11BrandSub" placeholder="JEWELLERY" value="<?php echo htmlspecialchars($settings['t11_brand_sub'] ?? 'JEWELLERY'); ?>" maxlength="60"></div>
                                            <div class="ips-field"><label>Amount currency label</label><input type="text" name="t11_amount_currency_label" id="ipsT11AmtCurr" placeholder="DHS" value="<?php echo htmlspecialchars($settings['t11_amount_currency_label'] ?? 'DHS'); ?>" maxlength="20"></div>
                                            <div class="ips-field"><label>Gold rate karat</label>
                                                <select name="t11_gold_rate_karat" id="ipsT11GoldKarat">
                                                    <?php foreach (['24K', '22K', '21K', '18K'] as $gk): ?>
                                                    <option value="<?php echo $gk; ?>" <?php echo ($settings['t11_gold_rate_karat'] ?? '21K') === $gk ? 'selected' : ''; ?>><?php echo $gk; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="ips-field"><label>Show Planet notice box</label>
                                                <select name="t11_show_planet_box" id="ipsT11ShowPlanet">
                                                    <option value="1" <?php echo ($settings['t11_show_planet_box'] ?? '1') === '1' ? 'selected' : ''; ?>>Yes</option>
                                                    <option value="0" <?php echo ($settings['t11_show_planet_box'] ?? '1') === '0' ? 'selected' : ''; ?>>No</option>
                                                </select>
                                            </div>
                                            <div class="ips-field" style="grid-column: 1 / -1;"><label>Planet notice text</label><textarea name="t11_planet_notice" id="ipsT11PlanetNotice" rows="4" placeholder="Planet VAT refund notice..." style="width:100%;"><?php echo htmlspecialchars($settings['t11_planet_notice'] ?? ''); ?></textarea></div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="ips-cards-row">
                                <div class="ips-card ips-card-full">
                                    <div class="ips-card-header">Template 9 (Royal Design A5 Tax Invoice) — used when Invoice layout = Template 9</div>
                                    <div class="ips-card-body scroll-card">
                                        <p class="ips-hint" style="margin-bottom: 10px;">UAE A5 bilingual red tax invoice (Qty / Description / Grams / C.T. / Amount). Set paper size to <strong>A5</strong> in Layout. Company name, TRN, phone &amp; address use the Company section above. English terms use Footer Terms; Arabic terms below.</p>
                                        <div class="ips-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                            <div class="ips-field"><label>Arabic company name</label><input type="text" name="t9_arabic_company_name" id="ipsT9ArabicName" placeholder="التصميم الملكي للمجوهرات ذ.م.م" value="<?php echo htmlspecialchars($settings['t9_arabic_company_name'] ?? ''); ?>" maxlength="200" dir="rtl"></div>
                                            <div class="ips-field"><label>QR image path</label><input type="text" name="t9_qr_image_path" id="ipsT9QrPath" placeholder="uploads/qr.png" value="<?php echo htmlspecialchars($settings['t9_qr_image_path'] ?? ''); ?>" maxlength="255"></div>
                                            <div class="ips-field"><label>Amount currency label</label><input type="text" name="t9_amount_currency_label" id="ipsT9AmtCurr" placeholder="Dhs." value="<?php echo htmlspecialchars($settings['t9_amount_currency_label'] ?? 'Dhs.'); ?>" maxlength="20"></div>
                                            <div class="ips-field" style="grid-column: 1 / -1;"><label>Arabic terms / note</label><textarea name="t9_terms_arabic" id="ipsT9TermsAr" rows="3" placeholder="ملاحظة: ..." dir="rtl" style="width:100%;"><?php echo htmlspecialchars($settings['t9_terms_arabic'] ?? ''); ?></textarea></div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="ips-cards-row">
                                <div class="ips-card">
                                    <div class="ips-card-header">Languages</div>
                                    <div class="ips-card-body">
                                        <div class="ips-label">Secondary (optional)</div>
                                        <div class="ips-lang-options" style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <label class="ips-lang-option" style="display: flex; align-items: center; gap: 4px; padding: 4px 8px; border: 2px solid #e2e8f0; border-radius: 4px; cursor: pointer; background: #fff; font-size: 0.75rem;">
                                                <input type="radio" name="invoice_secondary_language" value="" <?php echo empty($settings['invoice_secondary_language']) ? 'checked' : ''; ?>>
                                                <span>None</span>
                                            </label>
                                            <label class="ips-lang-option" style="display: flex; align-items: center; gap: 4px; padding: 4px 8px; border: 2px solid #e2e8f0; border-radius: 4px; cursor: pointer; background: #fff; font-size: 0.75rem;">
                                                <input type="radio" name="invoice_secondary_language" value="hi" <?php echo ($settings['invoice_secondary_language'] ?? '') === 'hi' ? 'checked' : ''; ?>>
                                                <span>Hindi</span>
                                            </label>
                                            <label class="ips-lang-option" style="display: flex; align-items: center; gap: 4px; padding: 4px 8px; border: 2px solid #e2e8f0; border-radius: 4px; cursor: pointer; background: #fff; font-size: 0.75rem;">
                                                <input type="radio" name="invoice_secondary_language" value="mr" <?php echo ($settings['invoice_secondary_language'] ?? '') === 'mr' ? 'checked' : ''; ?>>
                                                <span>Marathi</span>
                                            </label>
                                            <label class="ips-lang-option" style="display: flex; align-items: center; gap: 4px; padding: 4px 8px; border: 2px solid #e2e8f0; border-radius: 4px; cursor: pointer; background: #fff; font-size: 0.75rem;">
                                                <input type="radio" name="invoice_secondary_language" value="ar" <?php echo ($settings['invoice_secondary_language'] ?? '') === 'ar' ? 'checked' : ''; ?>>
                                                <span>Arabic</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="ips-card" style="display: flex; flex-direction: column; justify-content: center;">
                                    <div class="ips-card-body">
                                        <div class="ips-actions" style="margin-top: 0;">
                                            <button type="button" class="ips-btn ips-btn-save" id="ipsBtnSave">Save settings</button>
                                            <button type="button" class="ips-btn ips-btn-preview" id="ipsBtnPreview" title="<?php echo $preview_uses_sample_invoice ? 'Sample invoice (create a sale invoice to preview real data)' : ''; ?>">Preview invoice</button>
                                        </div>
                                    </div>
                                </div>
                                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="ipsToast" class="ips-toast"></div>

<script>
(function() {
    var previewId = <?php echo (int) $preview_invoice_id; ?>;
    var previewUsesSample = <?php echo $preview_uses_sample_invoice ? 'true' : 'false'; ?>;

    function saleInvoicePrintPreviewUrl(extraQuery) {
        extraQuery = extraQuery || '';
        var bid = document.getElementById('settingsBranchId');
        var branchQ = (bid && bid.value) ? ('branch_id=' + encodeURIComponent(bid.value)) : '';
        var parts = [];
        if (branchQ) parts.push(branchQ);
        if (extraQuery) parts.push(extraQuery);
        var q = parts.length ? ('&' + parts.join('&')) : '';
        if (previewUsesSample) {
            return 'sale-invoice-print.php?preview_sample=1' + q;
        }
        return 'sale-invoice-print.php?id=' + previewId + q;
    }

    function toggleSwitch(el) {
        var wrap = el.classList.contains('ips-switch') ? el : el.closest('.ips-switch');
        if (!wrap) return;
        wrap.classList.toggle('on');
        var cb = wrap.querySelector('input[type=checkbox]');
        if (cb) cb.checked = wrap.classList.contains('on');
    }
    document.querySelectorAll('.ips-toggle-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            var sw = row.querySelector('.ips-switch');
            if (sw && !sw.contains(e.target)) {
                toggleSwitch(sw);
            }
        });
    });

    function getVisibleColumnKeys() {
        var keys = [];
        document.querySelectorAll('#visibleColumnsList .ips-column-item').forEach(function(item) {
            var k = item.getAttribute('data-key');
            if (k) keys.push(k);
        });
        return keys;
    }

    function buildFormData() {
        var fd = new FormData();
        var settingForEl = document.getElementById('settingForSelect');
        fd.append('setting_type', settingForEl ? settingForEl.value : 'default');
        var designRadio = document.querySelector('input[name=design_template]:checked');
        fd.append('design_template', designRadio ? designRadio.value : 'template_1');
        var invoiceTemplateSelect = document.getElementById('ipsInvoiceTemplate');
        fd.append('invoice_template', invoiceTemplateSelect ? invoiceTemplateSelect.value : 'template_classic');
        fd.append('sale_invoice_columns', JSON.stringify(getVisibleColumnKeys()));
        fd.append('header_section_enabled', document.querySelector('input[name=header_section_enabled]') && document.querySelector('input[name=header_section_enabled]').checked ? '1' : '0');
        fd.append('header_company_logo', document.querySelector('input[name=header_company_logo]') && document.querySelector('input[name=header_company_logo]').checked ? '1' : '0');
        fd.append('header_company_name', document.querySelector('input[name=header_company_name]') && document.querySelector('input[name=header_company_name]').checked ? '1' : '0');
        fd.append('header_gst_number', document.querySelector('input[name=header_gst_number]') && document.querySelector('input[name=header_gst_number]').checked ? '1' : '0');
        fd.append('header_phone', document.querySelector('input[name=header_phone]') && document.querySelector('input[name=header_phone]').checked ? '1' : '0');
        fd.append('header_invoice_title', document.querySelector('input[name=header_invoice_title]') && document.querySelector('input[name=header_invoice_title]').checked ? '1' : '0');
        fd.append('footer_terms_conditions', document.querySelector('input[name=footer_terms_conditions]') && document.querySelector('input[name=footer_terms_conditions]').checked ? '1' : '0');
        fd.append('footer_authorized_signature', document.querySelector('input[name=footer_authorized_signature]') && document.querySelector('input[name=footer_authorized_signature]').checked ? '1' : '0');
        fd.append('footer_thank_you_message', document.querySelector('input[name=footer_thank_you_message]') && document.querySelector('input[name=footer_thank_you_message]').checked ? '1' : '0');
        fd.append('layout_type', document.querySelector('input[name=layout_type]:checked') ? document.querySelector('input[name=layout_type]:checked').value : 'A4');
        fd.append('page_orientation', document.querySelector('input[name=page_orientation]:checked') ? document.querySelector('input[name=page_orientation]:checked').value : 'portrait');
        fd.append('company_logo_path', document.getElementById('ipsLogoPath') ? document.getElementById('ipsLogoPath').value : '');
        fd.append('company_name', document.getElementById('ipsCompanyName') ? document.getElementById('ipsCompanyName').value : '');
        fd.append('company_address', document.getElementById('ipsCompanyAddress') ? document.getElementById('ipsCompanyAddress').value : '');
        fd.append('company_gst', document.getElementById('ipsCompanyGst') ? document.getElementById('ipsCompanyGst').value : '');
        fd.append('company_pan', document.getElementById('ipsCompanyPan') ? document.getElementById('ipsCompanyPan').value : '');
        fd.append('company_phone', document.getElementById('ipsCompanyPhone') ? document.getElementById('ipsCompanyPhone').value : '');
        fd.append('company_email', document.getElementById('ipsCompanyEmail') ? document.getElementById('ipsCompanyEmail').value : '');
        fd.append('invoice_title', document.getElementById('ipsInvoiceTitle') ? document.getElementById('ipsInvoiceTitle').value : '');
        fd.append('print_padding_top_mm', document.getElementById('ipsPaddingTop') ? document.getElementById('ipsPaddingTop').value : '0');
        var colLabels = {};
        document.querySelectorAll('.ips-col-label-input').forEach(function(inp) {
            var k = inp.getAttribute('data-col-key');
            if (k && inp.value.trim() !== '') colLabels[k] = inp.value.trim();
        });
        fd.append('column_header_labels', JSON.stringify(colLabels));
        var sumLabels = {};
        document.querySelectorAll('.ips-summary-label-input').forEach(function(inp) {
            var k = inp.getAttribute('data-summary-key');
            if (k && inp.value.trim() !== '') sumLabels[k] = inp.value.trim();
        });
        fd.append('summary_label_overrides', JSON.stringify(sumLabels));
        var sumOrder = [];
        document.querySelectorAll('#summaryOrderList .ips-column-item').forEach(function(el) {
            var k = el.getAttribute('data-summary-key');
            if (k) sumOrder.push(k);
        });
        fd.append('summary_row_order', JSON.stringify(sumOrder));
        fd.append('terms_conditions', document.getElementById('ipsTermsConditions') ? document.getElementById('ipsTermsConditions').value : '');
        fd.append('authorized_signature', document.getElementById('ipsAuthorizedSignature') ? document.getElementById('ipsAuthorizedSignature').value : '');
        fd.append('thank_you_message', document.getElementById('ipsThankYouMessage') ? document.getElementById('ipsThankYouMessage').value : '');
        var secLang = document.querySelector('input[name=invoice_secondary_language]:checked');
        fd.append('invoice_secondary_language', secLang ? secLang.value : '');
        fd.append('advertise_banner_path', document.getElementById('ipsBannerPath') ? document.getElementById('ipsBannerPath').value : '');
        fd.append('footer_show_banner', document.querySelector('input[name=footer_show_banner]') && document.querySelector('input[name=footer_show_banner]').checked ? '1' : '0');
        fd.append('t6_show_item_vertical_lines', document.querySelector('input[name=t6_show_item_vertical_lines]') && document.querySelector('input[name=t6_show_item_vertical_lines]').checked ? '1' : '0');
        fd.append('t6_show_currency_on_amounts', document.querySelector('input[name=t6_show_currency_on_amounts]') && document.querySelector('input[name=t6_show_currency_on_amounts]').checked ? '1' : '0');
        fd.append('t6_rates_banner_format', document.getElementById('ipsT6RatesBanner') ? document.getElementById('ipsT6RatesBanner').value : '');
        fd.append('t6_min_item_rows', document.getElementById('ipsT6MinRows') ? document.getElementById('ipsT6MinRows').value : '12');
        fd.append('t6_label_gold_total', document.getElementById('ipsT6Gold') ? document.getElementById('ipsT6Gold').value : '');
        fd.append('t6_label_silver_total', document.getElementById('ipsT6Silver') ? document.getElementById('ipsT6Silver').value : '');
        fd.append('t6_label_total_before_gst', document.getElementById('ipsT6BeforeGst') ? document.getElementById('ipsT6BeforeGst').value : '');
        fd.append('t6_label_cgst', document.getElementById('ipsT6Cgst') ? document.getElementById('ipsT6Cgst').value : '');
        fd.append('t6_label_sgst', document.getElementById('ipsT6Sgst') ? document.getElementById('ipsT6Sgst').value : '');
        fd.append('t6_label_total_with_gst', document.getElementById('ipsT6WithGst') ? document.getElementById('ipsT6WithGst').value : '');
        fd.append('t6_label_bank_transfer', document.getElementById('ipsT6Bank') ? document.getElementById('ipsT6Bank').value : '');
        fd.append('t6_label_cash', document.getElementById('ipsT6CashLab') ? document.getElementById('ipsT6CashLab').value : '');
        fd.append('t6_label_last_balance', document.getElementById('ipsT6LastBal') ? document.getElementById('ipsT6LastBal').value : '');
        fd.append('t6_label_current_balance', document.getElementById('ipsT6CurrBal') ? document.getElementById('ipsT6CurrBal').value : '');
        fd.append('t6_balance_suffix', document.getElementById('ipsT6BalSuffix') ? document.getElementById('ipsT6BalSuffix').value : '');
        var t6cols = {};
        document.querySelectorAll('.ips-t6-col-input').forEach(function(inp) {
            var k = inp.getAttribute('data-t6-col');
            if (k && inp.value.trim() !== '') t6cols[k] = inp.value.trim();
        });
        fd.append('t6_column_labels', JSON.stringify(t6cols));
        fd.append('t7_company_tagline', document.getElementById('ipsT7Tagline') ? document.getElementById('ipsT7Tagline').value : '');
        fd.append('t7_min_item_rows', document.getElementById('ipsT7MinRows') ? document.getElementById('ipsT7MinRows').value : '15');
        fd.append('t7_bank_name', document.getElementById('ipsT7BankName') ? document.getElementById('ipsT7BankName').value : '');
        fd.append('t7_bank_account_name', document.getElementById('ipsT7AcctName') ? document.getElementById('ipsT7AcctName').value : '');
        fd.append('t7_bank_account_no', document.getElementById('ipsT7AcctNo') ? document.getElementById('ipsT7AcctNo').value : '');
        fd.append('t7_bank_ifsc', document.getElementById('ipsT7Ifsc') ? document.getElementById('ipsT7Ifsc').value : '');
        fd.append('t8_min_item_rows', document.getElementById('ipsT8MinRows') ? document.getElementById('ipsT8MinRows').value : '2');
        fd.append('t8_bank_name', document.getElementById('ipsT8BankName') ? document.getElementById('ipsT8BankName').value : '');
        fd.append('t8_bank_account_no', document.getElementById('ipsT8AcctNo') ? document.getElementById('ipsT8AcctNo').value : '');
        fd.append('t8_bank_ifsc', document.getElementById('ipsT8Ifsc') ? document.getElementById('ipsT8Ifsc').value : '');
        fd.append('t8_bank_branch', document.getElementById('ipsT8Branch') ? document.getElementById('ipsT8Branch').value : '');
        fd.append('t9_arabic_company_name', document.getElementById('ipsT9ArabicName') ? document.getElementById('ipsT9ArabicName').value : '');
        fd.append('t9_qr_image_path', document.getElementById('ipsT9QrPath') ? document.getElementById('ipsT9QrPath').value : '');
        fd.append('t9_amount_currency_label', document.getElementById('ipsT9AmtCurr') ? document.getElementById('ipsT9AmtCurr').value : 'Dhs.');
        fd.append('t9_terms_arabic', document.getElementById('ipsT9TermsAr') ? document.getElementById('ipsT9TermsAr').value : '');
        fd.append('t10_address_ar', document.getElementById('ipsT10AddressAr') ? document.getElementById('ipsT10AddressAr').value : '');
        fd.append('t10_cr_no', document.getElementById('ipsT10CrNo') ? document.getElementById('ipsT10CrNo').value : '');
        fd.append('t10_website', document.getElementById('ipsT10Website') ? document.getElementById('ipsT10Website').value : '');
        fd.append('t10_instagram', document.getElementById('ipsT10Instagram') ? document.getElementById('ipsT10Instagram').value : '');
        fd.append('t10_brand_sub', document.getElementById('ipsT10BrandSub') ? document.getElementById('ipsT10BrandSub').value : 'jewelry');
        fd.append('t10_amount_currency_label', document.getElementById('ipsT10AmtCurr') ? document.getElementById('ipsT10AmtCurr').value : 'QAR');
        fd.append('t10_gold_rate_karat', document.getElementById('ipsT10GoldKarat') ? document.getElementById('ipsT10GoldKarat').value : '24K');
        fd.append('t10_min_item_rows', document.getElementById('ipsT10MinRows') ? document.getElementById('ipsT10MinRows').value : '5');
        fd.append('t10_terms_arabic', document.getElementById('ipsT10TermsAr') ? document.getElementById('ipsT10TermsAr').value : '');
        fd.append('t11_brand_sub', document.getElementById('ipsT11BrandSub') ? document.getElementById('ipsT11BrandSub').value : 'JEWELLERY');
        fd.append('t11_amount_currency_label', document.getElementById('ipsT11AmtCurr') ? document.getElementById('ipsT11AmtCurr').value : 'DHS');
        fd.append('t11_gold_rate_karat', document.getElementById('ipsT11GoldKarat') ? document.getElementById('ipsT11GoldKarat').value : '21K');
        fd.append('t11_show_planet_box', document.getElementById('ipsT11ShowPlanet') ? document.getElementById('ipsT11ShowPlanet').value : '1');
        fd.append('t11_planet_notice', document.getElementById('ipsT11PlanetNotice') ? document.getElementById('ipsT11PlanetNotice').value : '');
        fd.append('custom_print_css', document.getElementById('ipsCustomPrintCss') ? document.getElementById('ipsCustomPrintCss').value : '');
        var emailSubjEl = document.getElementById('ipsEmailSubject');
        fd.append('email_message_subject', emailSubjEl ? emailSubjEl.value : '');
        var emailBodyHtml = '';
        if (ipsEmailQuill) {
            emailBodyHtml = ipsEmailQuill.root.innerHTML;
        } else {
            var emailBodyHidden = document.getElementById('ipsEmailBodyHidden');
            emailBodyHtml = emailBodyHidden ? emailBodyHidden.value : '';
        }
        fd.append('email_message_body', emailBodyHtml);
        var logoFile = document.getElementById('ipsLogoFile');
        if (logoFile && logoFile.files && logoFile.files[0]) fd.append('company_logo', logoFile.files[0]);
        var bannerFile = document.getElementById('ipsBannerFile');
        if (bannerFile && bannerFile.files && bannerFile.files[0]) fd.append('advertise_banner', bannerFile.files[0]);
        var sb = document.getElementById('settingsBranchId');
        if (sb) fd.append('settings_branch_id', sb.value);
        return fd;
    }

    function showToast(msg) {
        var t = document.getElementById('ipsToast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(function() { t.classList.remove('show'); }, 3000);
    }

    var ipsEmailQuill = null;
    var ipsEmailEditorEl = document.getElementById('ipsEmailEditor');
    if (ipsEmailEditorEl && typeof Quill !== 'undefined') {
        ipsEmailQuill = new Quill('#ipsEmailEditor', {
            theme: 'snow',
            modules: { toolbar: '#ipsEmailToolbar' }
        });
        var ipsEmailBodyHidden = document.getElementById('ipsEmailBodyHidden');
        if (ipsEmailBodyHidden && ipsEmailBodyHidden.value.trim() !== '') {
            ipsEmailQuill.root.innerHTML = ipsEmailBodyHidden.value;
        }
    }
    document.querySelectorAll('#ipsEmailPlaceholders .ips-placeholder-tag').forEach(function(tag) {
        tag.addEventListener('click', function() {
            var token = this.getAttribute('data-token');
            if (!token) return;
            var subjEl = document.getElementById('ipsEmailSubject');
            if (subjEl && document.activeElement === subjEl) {
                var start = subjEl.selectionStart != null ? subjEl.selectionStart : subjEl.value.length;
                var end = subjEl.selectionEnd != null ? subjEl.selectionEnd : start;
                subjEl.value = subjEl.value.slice(0, start) + token + subjEl.value.slice(end);
                subjEl.focus();
                subjEl.selectionStart = subjEl.selectionEnd = start + token.length;
                return;
            }
            if (ipsEmailQuill) {
                var range = ipsEmailQuill.getSelection(true);
                ipsEmailQuill.insertText(range.index, token);
                ipsEmailQuill.setSelection(range.index + token.length);
            } else if (subjEl) {
                subjEl.value += token;
            }
        });
    });

    var settingForSelect = document.getElementById('settingForSelect');
    if (settingForSelect) {
        settingForSelect.addEventListener('change', function() {
            var bid = document.getElementById('settingsBranchId');
            var q = 'type=' + encodeURIComponent(this.value);
            if (bid && bid.value) q += '&branch_id=' + encodeURIComponent(bid.value);
            window.location = 'invoice-print-settings.php?' + q;
        });
    }

    document.querySelectorAll('.ips-template-card').forEach(function(card) {
        card.addEventListener('click', function() {
            document.querySelectorAll('.ips-template-card').forEach(function(c) { c.classList.remove('selected'); });
            card.classList.add('selected');
        });
    });

    var previewDesignBtn = document.getElementById('ipsBtnPreviewDesign');
    if (previewDesignBtn) {
        previewDesignBtn.addEventListener('click', function() {
            var designRadio = document.querySelector('input[name=design_template]:checked');
            var template = designRadio ? designRadio.value : 'template_1';
            window.open(saleInvoicePrintPreviewUrl('design_preview=' + encodeURIComponent(template)), '_blank', 'width=1000,height=800');
        });
    }
    var previewTemplateBtn = document.getElementById('ipsBtnPreviewTemplate');
    if (previewTemplateBtn) {
        previewTemplateBtn.addEventListener('click', function() {
            var sel = document.getElementById('ipsInvoiceTemplate');
            var layoutTemplate = sel ? sel.value : 'template_classic';
            window.open(saleInvoicePrintPreviewUrl('template_preview=' + encodeURIComponent(layoutTemplate)), '_blank', 'width=1000,height=800');
        });
    }

    // Paper / orientation: update highlight within each group only
    var layoutOptions = document.getElementById('ipsLayoutOptions');
    if (layoutOptions) {
        layoutOptions.addEventListener('click', function(e) {
            var option = e.target.closest('.ips-layout-option');
            if (option && layoutOptions.contains(option)) {
                layoutOptions.querySelectorAll('.ips-layout-option').forEach(function(lab) { lab.classList.remove('selected'); });
                option.classList.add('selected');
            }
        });
    }
    var orientationOptions = document.getElementById('ipsOrientationOptions');
    if (orientationOptions) {
        orientationOptions.addEventListener('click', function(e) {
            var option = e.target.closest('.ips-layout-option');
            if (option && orientationOptions.contains(option)) {
                orientationOptions.querySelectorAll('.ips-layout-option').forEach(function(lab) { lab.classList.remove('selected'); });
                option.classList.add('selected');
            }
        });
    }

    document.getElementById('ipsLogoFile').addEventListener('change', function() {
        var preview = document.getElementById('ipsLogoPreview');
        if (!this.files || !this.files[0]) return;
        var fr = new FileReader();
        fr.onload = function() {
            preview.classList.remove('empty');
            preview.innerHTML = '<img src="' + fr.result + '" alt="Logo">';
        };
        fr.readAsDataURL(this.files[0]);
    });

    document.getElementById('ipsBannerFile').addEventListener('change', function() {
        var preview = document.getElementById('ipsBannerPreview');
        if (!this.files || !this.files[0]) return;
        var fr = new FileReader();
        fr.onload = function() {
            preview.classList.remove('empty');
            preview.innerHTML = '<img src="' + fr.result + '" alt="Banner" style="max-height: 120px;">';
        };
        fr.readAsDataURL(this.files[0]);
    });

    document.getElementById('ipsBtnSave').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        var fd = buildFormData();
        fetch('ajax/save-invoice-print-settings.php', {
            method: 'POST',
            body: fd
        }).then(function(r) { return r.json(); }).then(function(data) {
            btn.disabled = false;
            showToast(data.message || (data.success ? 'Settings saved.' : 'Save failed.'));
            if (data.success && data.company_logo_path) {
                var pathEl = document.getElementById('ipsLogoPath');
                if (pathEl) pathEl.value = data.company_logo_path;
            }
            if (data.success && data.advertise_banner_path !== undefined) {
                var bannerPathEl = document.getElementById('ipsBannerPath');
                if (bannerPathEl) bannerPathEl.value = data.advertise_banner_path || '';
            }
            if (data.success) {
                setTimeout(function() { window.location.reload(); }, 600);
            }
        }).catch(function() {
            btn.disabled = false;
            showToast('Network error.');
        });
    });

    document.getElementById('ipsBtnPreview').addEventListener('click', function() {
        window.open(saleInvoicePrintPreviewUrl(''), '_blank', 'width=1000,height=800');
    });

    function removeColumnFromList(container, key) {
        if (!container || !key) return;
        container.querySelectorAll('.ips-column-item').forEach(function(n) {
            if (n.getAttribute('data-key') === key) n.remove();
        });
    }

    function dedupeVisible() {
        var vis = document.getElementById('visibleColumnsList');
        if (!vis) return;
        var seen = {};
        vis.querySelectorAll('.ips-column-item').forEach(function(el) {
            var k = el.getAttribute('data-key');
            if (!k) return;
            if (seen[k]) el.remove();
            else seen[k] = true;
        });
    }

    var availableList = document.getElementById('availableColumnsList');
    var visibleList = document.getElementById('visibleColumnsList');

    function filterInvoicePrintColumns() {
        var searchEl = document.getElementById('ipsColumnSearch');
        var q = searchEl ? searchEl.value.trim().toLowerCase() : '';
        var list = document.getElementById('availableColumnsList');
        if (!list) return;
        list.querySelectorAll('.ips-column-item').forEach(function(el) {
            var label = (el.textContent || '').toLowerCase();
            var group = (el.getAttribute('data-group') || '').toLowerCase();
            var key = (el.getAttribute('data-key') || '').toLowerCase();
            var match = !q || label.indexOf(q) !== -1 || group.indexOf(q) !== -1 || key.indexOf(q) !== -1;
            el.style.display = match ? '' : 'none';
        });
    }
    var columnSearchEl = document.getElementById('ipsColumnSearch');
    if (columnSearchEl) {
        columnSearchEl.addEventListener('input', filterInvoicePrintColumns);
    }

    if (typeof Sortable !== 'undefined') {
        if (availableList) {
            new Sortable(availableList, {
                group: 'columns',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onAdd: function(evt) {
                    var key = evt.item.getAttribute('data-key');
                    if (key) removeColumnFromList(visibleList, key);
                    dedupeVisible();
                },
                onEnd: function() { dedupeVisible(); }
            });
        }
        if (visibleList) {
            new Sortable(visibleList, {
                group: 'columns',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onAdd: function(evt) {
                    var key = evt.item.getAttribute('data-key');
                    if (key) removeColumnFromList(availableList, key);
                    dedupeVisible();
                },
                onEnd: function() { dedupeVisible(); }
            });
        }
        var summaryOrderList = document.getElementById('summaryOrderList');
        if (summaryOrderList && typeof Sortable !== 'undefined') {
            new Sortable(summaryOrderList, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag'
            });
        }
    }
})();
</script>
</body>
</html>
