<?php
session_start();
require_once 'config.php';

$all_columns = [
    'sr_no'         => 'Sr No',
    'item_name'     => 'Item Name',
    'design_no'     => 'Design No',
    'huid'          => 'HUID',
    'category'      => 'Category',
    'gross_weight'  => 'Gross Weight',
    'less_weight'   => 'Less Weight',
    'net_weight'    => 'Net Weight',
    'purity_karat'  => 'Purity / Karat',
    'rate'          => 'Rate',
    'making_charge' => 'Making Charge',
    'diamond_amount'=> 'Diamond Amount',
    'stone_amount'  => 'Stone Amount',
    'discount'      => 'Discount',
    'amount'        => 'Amount',
];

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
    'purchase_quotation' => 'Purchase Quotation',
    'sale_quotation' => 'Sale Quotation',
    'sale_return' => 'Sale Return',
    'purchase_return' => 'Purchase Return',
    'sale_fixing_direct' => 'Sale Fixing Direct',
];
$settings = getInvoicePrintSettingsByType($current_setting_type);
$design_templates = function_exists('getInvoicePrintDesignTemplates') ? getInvoicePrintDesignTemplates() : [];
$current_design = $settings['design_template'] ?? 'template_1';
$structure_templates = function_exists('getInvoicePrintStructureTemplates') ? getInvoicePrintStructureTemplates() : [];
$current_invoice_template = $settings['invoice_template'] ?? 'template_classic';
$visible_keys = $settings['sale_invoice_columns'];
if (!is_array($visible_keys)) $visible_keys = array_keys($all_columns);
$available_keys = array_diff(array_keys($all_columns), $visible_keys);

$preview_invoice_id = 0;
$last_inv = getRecord("SELECT id FROM tbl_sale_invoices ORDER BY id DESC LIMIT 1");
if ($last_inv) $preview_invoice_id = (int)$last_inv['id'];
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Invoice Print Settings - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php';?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .ips-page { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .ips-title { font-size: 1.5rem; font-weight: 700; color: #1a365d; margin-bottom: 24px; }
        .ips-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; margin-bottom: 24px; overflow: hidden; }
        .ips-card-header { background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%); color: #fff; padding: 14px 20px; font-weight: 600; font-size: 0.95rem; }
        .ips-card-body { padding: 20px; }
        .ips-columns-row { display: flex; gap: 24px; flex-wrap: wrap; }
        .ips-column-panel { flex: 1; min-width: 260px; }
        .ips-column-list { min-height: 320px; border: 2px dashed #cbd5e0; border-radius: 10px; padding: 12px; background: #f8fafc; }
        .ips-column-list.visible-list { border-color: #2c5282; background: #edf2f7; }
        .ips-column-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; cursor: grab; font-size: 0.9rem; color: #2d3748; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .ips-column-item:last-child { margin-bottom: 0; }
        .ips-column-item:hover { border-color: #2c5282; background: #ebf8ff; }
        .ips-column-item.sortable-ghost { opacity: 0.5; }
        .ips-column-item.sortable-drag { cursor: grabbing; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .ips-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #718096; margin-bottom: 8px; font-weight: 600; }
        .ips-toggles { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
        .ips-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
        .ips-toggle-row label { margin: 0; font-size: 0.9rem; color: #2d3748; }
        .ips-switch { position: relative; width: 44px; height: 24px; background: #cbd5e0; border-radius: 12px; cursor: pointer; transition: background 0.2s; }
        .ips-switch.on { background: #2c5282; }
        .ips-switch::after { content: ''; position: absolute; width: 20px; height: 20px; border-radius: 50%; background: #fff; top: 2px; left: 2px; transition: transform 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .ips-switch.on::after { transform: translateX(20px); }
        .ips-switch input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
        .ips-toggle-row { cursor: pointer; }
        .ips-layout-options { display: flex; gap: 16px; flex-wrap: wrap; }
        .ips-layout-option { flex: 1; min-width: 140px; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; text-align: center; background: #fff; transition: all 0.2s; }
        .ips-layout-option:hover { border-color: #cbd5e0; }
        .ips-layout-option.selected { border-color: #2c5282; background: #ebf8ff; color: #1a365d; }
        .ips-layout-option input { display: none; }
        .ips-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
        .ips-btn { padding: 12px 24px; border-radius: 10px; font-weight: 600; font-size: 0.95rem; border: none; cursor: pointer; transition: all 0.2s; }
        .ips-btn-save { background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%); color: #fff; }
        .ips-btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(26,54,93,0.35); }
        .ips-btn-preview { background: linear-gradient(135deg, #d4af37 0%, #c9a227 100%); color: #1a365d; }
        .ips-btn-preview:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(212,175,55,0.35); }
        .ips-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .ips-toast { position: fixed; bottom: 24px; right: 24px; padding: 14px 20px; border-radius: 10px; background: #1a365d; color: #fff; font-weight: 500; box-shadow: 0 4px 16px rgba(0,0,0,0.2); z-index: 9999; display: none; }
        .ips-toast.show { display: block; animation: ips-fadeIn 0.3s ease; }
        @keyframes ips-fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .ips-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .ips-field { margin-bottom: 16px; }
        .ips-field label { display: block; font-size: 0.85rem; font-weight: 600; color: #2d3748; margin-bottom: 6px; }
        .ips-field input[type=text], .ips-field input[type=email], .ips-field textarea { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .ips-field textarea { min-height: 80px; resize: vertical; }
        .ips-field .ips-hint { font-size: 0.75rem; color: #718096; margin-top: 4px; }
        .ips-logo-upload { display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
        .ips-logo-preview { width: 100px; height: 100px; border: 2px dashed #e2e8f0; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; }
        .ips-logo-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .ips-logo-preview.empty { color: #94a3b8; font-size: 0.75rem; text-align: center; padding: 8px; }
        .ips-upload-btn { padding: 8px 16px; background: #2c5282; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 0.85rem; }
        .ips-upload-btn:hover { background: #1a365d; }
        .ips-upload-btn input { display: none; }
        .ips-template-card.selected { border-color: #2c5282 !important; box-shadow: 0 4px 12px rgba(44,82,130,0.25); }
        .ips-template-card:hover { border-color: #cbd5e0; }
    </style>
</head>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
            <div class="app-brand demo">
                <span class="app-brand-logo demo"><img src="assets/img/logo.png" alt="Brand" class="img-fluid"></span>
                <a href="index.php" class="app-brand-text demo sidenav-text font-weight-normal ml-2">AuraGold</a>
                <a href="javascript:" class="layout-sidenav-toggle sidenav-link text-large ml-auto"><i class="ion ion-md-menu align-middle"></i></a>
            </div>
            <div class="sidenav-divider mt-0"></div>
            <ul class="sidenav-inner py-1">
                <li class="sidenav-item"><a href="dashboard.php" class="sidenav-link"><i class="sidenav-icon feather icon-home"></i><div>Dashboard</div></a></li>
                <li class="sidenav-item active"><a href="invoice-print-settings.php" class="sidenav-link"><i class="sidenav-icon feather icon-printer"></i><div>Invoice Print Settings</div></a></li>
            </ul>
        </div>
        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                <a href="index.php" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                    <span class="app-brand-logo demo"><img src="assets/img/logo-dark.png" alt="Brand" class="img-fluid"></span>
                    <span class="app-brand-text demo font-weight-normal ml-2">AuraGold</span>
                </a>
                <div class="layout-sidenav-toggle navbar-nav d-lg-none align-items-lg-center mr-auto">
                    <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:"><i class="ion ion-md-menu text-large align-middle"></i></a>
                </div>
                <div class="navbar-collapse collapse" id="layout-navbar-collapse">
                    <div class="navbar-nav align-items-lg-center ml-auto">
                        <div class="demo-navbar-user nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                                <span class="d-inline-flex flex-lg-row-reverse align-items-center align-middle">
                                    <img src="assets/img/avatars/1.png" alt class="d-block ui-w-30 rounded-circle">
                                    <span class="px-1 mr-lg-2 ml-2 ml-lg-0">ADMIN</span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <?php include 'sidebar.php';?>
                    <div class="row" style="margin-left: 0; margin-right: 0;">
                        <div class="col-12">
                            <div class="ips-page">
                                <h1 class="ips-title">Invoice Print Settings</h1>
                                <p class="text-muted mb-4">Configure print layout per document type. If a document has no specific settings, Default Setting is used.</p>

                                <!-- Setting For dropdown -->
                                <div class="ips-card mb-4">
                                    <div class="ips-card-header">Setting For</div>
                                    <div class="ips-card-body">
                                        <div class="ips-field" style="max-width: 360px;">
                                            <label>Document type</label>
                                            <select id="settingForSelect" class="form-control" style="padding: 10px 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                                <?php foreach ($setting_types as $st): ?>
                                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $current_setting_type === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($setting_type_labels[$st] ?? $st); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="ips-hint mt-2">Changing this loads saved settings for the selected document. Save to store under this type.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Design Template: 5 different designs to choose from -->
                                <?php if (!empty($design_templates)): ?>
                                <div class="ips-card mb-4">
                                    <div class="ips-card-header">Choose invoice design template</div>
                                    <div class="ips-card-body">
                                        <p class="text-muted small mb-3">Pick one of the 5 designs below. Each has different colors and pattern. Use <strong>Preview</strong> to see it on a real invoice, then <strong>Save</strong> to apply for this document type.</p>
                                        <div class="ips-template-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                            <?php foreach ($design_templates as $tmpl): ?>
                                            <label class="ips-template-card <?php echo ($current_design === $tmpl['id']) ? 'selected' : ''; ?>" style="cursor: pointer; border: 2px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; transition: all 0.2s; margin: 0;">
                                                <input type="radio" name="design_template" value="<?php echo htmlspecialchars($tmpl['id']); ?>" <?php echo $current_design === $tmpl['id'] ? 'checked' : ''; ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
                                                <div class="ips-template-preview" style="height: 48px; background: <?php echo $tmpl['header_bg']; ?>;"></div>
                                                <div style="height: 10px; background: <?php echo $tmpl['badge_bg']; ?>;"></div>
                                                <div style="height: 14px; background: <?php echo isset($tmpl['table_bg']) ? $tmpl['table_bg'] : $tmpl['accent']; ?>; margin: 4px 6px 0; border-radius: 2px;"></div>
                                                <div style="padding: 10px 12px;">
                                                    <div style="font-weight: 700; font-size: 0.95rem; color: #1a365d;"><?php echo htmlspecialchars($tmpl['name']); ?></div>
                                                    <?php if (!empty($tmpl['desc'])): ?>
                                                    <div style="font-size: 0.75rem; color: #718096; margin-top: 2px;"><?php echo htmlspecialchars($tmpl['desc']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                                            <button type="button" class="ips-btn ips-btn-preview" id="ipsBtnPreviewDesign" <?php echo $preview_invoice_id <= 0 ? 'disabled' : ''; ?> title="<?php echo $preview_invoice_id <= 0 ? 'No sale invoice to preview' : 'Open print preview with selected design'; ?>">Preview this design</button>
                                            <span class="text-muted small">Opens full invoice with selected template so you can compare and choose the best one. Save to apply.</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Invoice Template (Layout): different structure/layout -->
                                <?php if (!empty($structure_templates)): ?>
                                <div class="ips-card mb-4">
                                    <div class="ips-card-header">Invoice template (layout)</div>
                                    <div class="ips-card-body">
                                        <p class="text-muted small mb-3">Choose which <strong>layout structure</strong> to use for this document type. Each template has a different structure (header, table, totals, footer). Use <strong>Preview Template</strong> to see the layout on a real invoice.</p>
                                        <div class="ips-field mb-3">
                                            <label class="ips-label">Invoice template</label>
                                            <select name="invoice_template" id="ipsInvoiceTemplate" class="form-control" style="max-width: 400px; padding: 10px 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                                <?php foreach ($structure_templates as $tid => $tname): ?>
                                                <option value="<?php echo htmlspecialchars($tid); ?>" <?php echo $current_invoice_template === $tid ? 'selected' : ''; ?>><?php echo htmlspecialchars($tname); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                                            <button type="button" class="ips-btn ips-btn-preview" id="ipsBtnPreviewTemplate" <?php echo $preview_invoice_id <= 0 ? 'disabled' : ''; ?> title="<?php echo $preview_invoice_id <= 0 ? 'No sale invoice to preview' : 'Open print with selected layout template'; ?>">Preview template</button>
                                            <span class="text-muted small">Opens invoice print with selected layout. Save to apply for this document type.</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Column configuration -->
                                <div class="ips-card">
                                    <div class="ips-card-header">Column configuration</div>
                                    <div class="ips-card-body">
                                        <div class="ips-columns-row">
                                            <div class="ips-column-panel">
                                                <div class="ips-label">Available columns (hidden on invoice)</div>
                                                <div id="availableColumnsList" class="ips-column-list">
                                                    <?php foreach ($available_keys as $key): ?>
                                                    <div class="ips-column-item" data-key="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($all_columns[$key]); ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="ips-column-panel">
                                                <div class="ips-label">Visible columns (shown on invoice) — drag to reorder; order here = order on printed invoice</div>
                                                <div id="visibleColumnsList" class="ips-column-list visible-list">
                                                    <?php foreach ($visible_keys as $key): if (!isset($all_columns[$key])) continue; ?>
                                                    <div class="ips-column-item" data-key="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($all_columns[$key]); ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Header -->
                                <div class="ips-card">
                                    <div class="ips-card-header">Header options</div>
                                    <div class="ips-card-body">
                                        <div class="ips-toggles" style="margin-bottom: 20px;">
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
                                        <div class="ips-label" style="margin-bottom: 10px;">Header content (leave blank to use system default)</div>
                                        <div class="ips-form-grid">
                                            <div class="ips-field">
                                                <label>Company Logo (upload)</label>
                                                <div class="ips-logo-upload">
                                                    <div class="ips-logo-preview <?php echo empty($settings['company_logo_path']) ? 'empty' : ''; ?>" id="ipsLogoPreview">
                                                        <?php if (!empty($settings['company_logo_path']) && file_exists(dirname(__FILE__) . '/' . $settings['company_logo_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($settings['company_logo_path']); ?>?t=<?php echo time(); ?>" alt="Logo">
                                                        <?php else: ?>
                                                        No logo
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <label class="ips-upload-btn"><input type="file" id="ipsLogoFile" accept="image/jpeg,image/png,image/gif,image/webp"> Choose file</label>
                                                        <input type="hidden" name="company_logo_path" id="ipsLogoPath" value="<?php echo htmlspecialchars($settings['company_logo_path'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="ips-field">
                                                <label>Company Name</label>
                                                <input type="text" name="company_name" id="ipsCompanyName" placeholder="e.g. Aura Gold" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>">
                                            </div>
                                            <div class="ips-field">
                                                <label>Address</label>
                                                <input type="text" name="company_address" id="ipsCompanyAddress" placeholder="e.g. Dubai, UAE" value="<?php echo htmlspecialchars($settings['company_address'] ?? ''); ?>">
                                            </div>
                                            <div class="ips-field">
                                                <label>GST / TRN Number</label>
                                                <input type="text" name="company_gst" id="ipsCompanyGst" placeholder="e.g. 100436638900003" value="<?php echo htmlspecialchars($settings['company_gst'] ?? ''); ?>">
                                            </div>
                                            <div class="ips-field">
                                                <label>Phone</label>
                                                <input type="text" name="company_phone" id="ipsCompanyPhone" placeholder="e.g. +971 4 123 4567" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                                            </div>
                                            <div class="ips-field">
                                                <label>Email</label>
                                                <input type="email" name="company_email" id="ipsCompanyEmail" placeholder="e.g. info@company.com" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
                                            </div>
                                            <div class="ips-field">
                                                <label>Invoice Title (e.g. TAX INVOICE)</label>
                                                <input type="text" name="invoice_title" id="ipsInvoiceTitle" placeholder="e.g. TAX INVOICE" value="<?php echo htmlspecialchars($settings['invoice_title'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="ips-card">
                                    <div class="ips-card-header">Footer options</div>
                                    <div class="ips-card-body">
                                        <div class="ips-toggles" style="margin-bottom: 20px;">
                                            <div class="ips-toggle-row">
                                                <label>Show Terms & Conditions</label>
                                                <div class="ips-switch <?php echo $settings['footer_terms_conditions'] === '1' ? 'on' : ''; ?>" data-toggle="footer_terms_conditions" onclick="toggleSwitch(this)"><input type="checkbox" name="footer_terms_conditions" <?php echo $settings['footer_terms_conditions'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show Authorized Signature</label>
                                                <div class="ips-switch <?php echo $settings['footer_authorized_signature'] === '1' ? 'on' : ''; ?>" data-toggle="footer_authorized_signature" onclick="toggleSwitch(this)"><input type="checkbox" name="footer_authorized_signature" <?php echo $settings['footer_authorized_signature'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                            <div class="ips-toggle-row">
                                                <label>Show Thank You Message</label>
                                                <div class="ips-switch <?php echo $settings['footer_thank_you_message'] === '1' ? 'on' : ''; ?>" data-toggle="footer_thank_you_message" onclick="toggleSwitch(this)"><input type="checkbox" name="footer_thank_you_message" <?php echo $settings['footer_thank_you_message'] === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                        </div>
                                        <div class="ips-label" style="margin-bottom: 10px;">Footer content (leave blank to use default)</div>
                                        <div class="ips-form-grid">
                                            <div class="ips-field" style="grid-column: 1 / -1;">
                                                <label>Terms & Conditions (text)</label>
                                                <textarea name="terms_conditions" id="ipsTermsConditions" placeholder="Optional terms text..."><?php echo htmlspecialchars($settings['terms_conditions'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="ips-field">
                                                <label>Authorized Signature (name or title)</label>
                                                <input type="text" name="authorized_signature" id="ipsAuthorizedSignature" placeholder="e.g. Authorized Signatory" value="<?php echo htmlspecialchars($settings['authorized_signature'] ?? ''); ?>">
                                            </div>
                                            <div class="ips-field" style="grid-column: 1 / -1;">
                                                <label>Thank You Message</label>
                                                <textarea name="thank_you_message" id="ipsThankYouMessage" placeholder="e.g. Thank you for your business."><?php echo htmlspecialchars($settings['thank_you_message'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Advertise banner (footer bottom) -->
                                <div class="ips-card">
                                    <div class="ips-card-header">Advertise banner (footer bottom)</div>
                                    <div class="ips-card-body">
                                        <div class="ips-toggles" style="margin-bottom: 16px;">
                                            <div class="ips-toggle-row">
                                                <label>Show banner in print</label>
                                                <div class="ips-switch <?php echo ($settings['footer_show_banner'] ?? '0') === '1' ? 'on' : ''; ?>" data-toggle="footer_show_banner" onclick="toggleSwitch(this)"><input type="checkbox" name="footer_show_banner" <?php echo ($settings['footer_show_banner'] ?? '0') === '1' ? 'checked' : ''; ?>></div>
                                            </div>
                                        </div>
                                        <div class="ips-label" style="margin-bottom: 8px;">Upload advertise banner (image shown at bottom of invoice when "Show banner in print" is on)</div>
                                        <div class="ips-logo-upload">
                                            <div class="ips-logo-preview <?php echo empty($settings['advertise_banner_path']) ? 'empty' : ''; ?>" id="ipsBannerPreview" style="min-height: 80px; max-height: 120px;">
                                                <?php if (!empty($settings['advertise_banner_path']) && file_exists(dirname(__FILE__) . '/' . $settings['advertise_banner_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($settings['advertise_banner_path']); ?>?t=<?php echo time(); ?>" alt="Banner" style="max-height: 120px;">
                                                <?php else: ?>
                                                No banner
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <label class="ips-upload-btn"><input type="file" id="ipsBannerFile" accept="image/jpeg,image/png,image/gif,image/webp"> Choose banner</label>
                                                <input type="hidden" name="advertise_banner_path" id="ipsBannerPath" value="<?php echo htmlspecialchars($settings['advertise_banner_path'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Layout type -->
                                <div class="ips-card">
                                    <div class="ips-card-header">Invoice layout type</div>
                                    <div class="ips-card-body">
                                        <div class="ips-layout-options" id="ipsLayoutOptions">
                                            <label class="ips-layout-option <?php echo ($settings['layout_type'] ?? 'A4') === 'A4' ? 'selected' : ''; ?>">
                                                <input type="radio" name="layout_type" value="A4" <?php echo ($settings['layout_type'] ?? 'A4') === 'A4' ? 'checked' : ''; ?>>
                                                <strong>A4</strong><br><small class="text-muted">Standard page</small>
                                            </label>
                                            <label class="ips-layout-option <?php echo ($settings['layout_type'] ?? '') === 'Thermal 80mm' ? 'selected' : ''; ?>">
                                                <input type="radio" name="layout_type" value="Thermal 80mm" <?php echo ($settings['layout_type'] ?? '') === 'Thermal 80mm' ? 'checked' : ''; ?>>
                                                <strong>Thermal 80mm</strong><br><small class="text-muted">Receipt width</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sale Invoice languages -->
                                <div class="ips-card">
                                    <div class="ips-card-header">Sale Invoice languages</div>
                                    <div class="ips-card-body">
                                        <p class="text-muted small mb-3">English is always available. You can enable one additional language for the print language selector.</p>
                                        <div class="ips-lang-row" style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px; padding: 12px 14px; background: #edf2f7; border-radius: 8px;">
                                            <span style="font-weight: 600;">English</span>
                                            <span class="badge" style="background: #2c5282; color: #fff; font-size: 0.7rem;">Always on</span>
                                        </div>
                                        <div class="ips-label" style="margin-bottom: 8px;">Additional language (choose one or none)</div>
                                        <div class="ips-lang-options" style="display: flex; flex-wrap: wrap; gap: 12px;">
                                            <label class="ips-lang-option" style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: #fff;">
                                                <input type="radio" name="invoice_secondary_language" value="" <?php echo empty($settings['invoice_secondary_language']) ? 'checked' : ''; ?>>
                                                <span>None (English only)</span>
                                            </label>
                                            <label class="ips-lang-option" style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: #fff;">
                                                <input type="radio" name="invoice_secondary_language" value="hi" <?php echo ($settings['invoice_secondary_language'] ?? '') === 'hi' ? 'checked' : ''; ?>>
                                                <span>Hindi</span>
                                            </label>
                                            <label class="ips-lang-option" style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: #fff;">
                                                <input type="radio" name="invoice_secondary_language" value="mr" <?php echo ($settings['invoice_secondary_language'] ?? '') === 'mr' ? 'checked' : ''; ?>>
                                                <span>Marathi</span>
                                            </label>
                                            <label class="ips-lang-option" style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; background: #fff;">
                                                <input type="radio" name="invoice_secondary_language" value="ar" <?php echo ($settings['invoice_secondary_language'] ?? '') === 'ar' ? 'checked' : ''; ?>>
                                                <span>Arabic</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="ips-actions">
                                    <button type="button" class="ips-btn ips-btn-save" id="ipsBtnSave">Save settings</button>
                                    <button type="button" class="ips-btn ips-btn-preview" id="ipsBtnPreview" <?php echo $preview_invoice_id <= 0 ? 'disabled title="No sale invoice found to preview"' : ''; ?>>Preview invoice</button>
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
    var previewId = <?php echo (int)$preview_invoice_id; ?>;

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
        fd.append('header_company_logo', document.querySelector('input[name=header_company_logo]') && document.querySelector('input[name=header_company_logo]').checked ? '1' : '0');
        fd.append('header_company_name', document.querySelector('input[name=header_company_name]') && document.querySelector('input[name=header_company_name]').checked ? '1' : '0');
        fd.append('header_gst_number', document.querySelector('input[name=header_gst_number]') && document.querySelector('input[name=header_gst_number]').checked ? '1' : '0');
        fd.append('header_phone', document.querySelector('input[name=header_phone]') && document.querySelector('input[name=header_phone]').checked ? '1' : '0');
        fd.append('header_invoice_title', document.querySelector('input[name=header_invoice_title]') && document.querySelector('input[name=header_invoice_title]').checked ? '1' : '0');
        fd.append('footer_terms_conditions', document.querySelector('input[name=footer_terms_conditions]') && document.querySelector('input[name=footer_terms_conditions]').checked ? '1' : '0');
        fd.append('footer_authorized_signature', document.querySelector('input[name=footer_authorized_signature]') && document.querySelector('input[name=footer_authorized_signature]').checked ? '1' : '0');
        fd.append('footer_thank_you_message', document.querySelector('input[name=footer_thank_you_message]') && document.querySelector('input[name=footer_thank_you_message]').checked ? '1' : '0');
        fd.append('layout_type', document.querySelector('input[name=layout_type]:checked') ? document.querySelector('input[name=layout_type]:checked').value : 'A4');
        fd.append('company_logo_path', document.getElementById('ipsLogoPath') ? document.getElementById('ipsLogoPath').value : '');
        fd.append('company_name', document.getElementById('ipsCompanyName') ? document.getElementById('ipsCompanyName').value : '');
        fd.append('company_address', document.getElementById('ipsCompanyAddress') ? document.getElementById('ipsCompanyAddress').value : '');
        fd.append('company_gst', document.getElementById('ipsCompanyGst') ? document.getElementById('ipsCompanyGst').value : '');
        fd.append('company_phone', document.getElementById('ipsCompanyPhone') ? document.getElementById('ipsCompanyPhone').value : '');
        fd.append('company_email', document.getElementById('ipsCompanyEmail') ? document.getElementById('ipsCompanyEmail').value : '');
        fd.append('invoice_title', document.getElementById('ipsInvoiceTitle') ? document.getElementById('ipsInvoiceTitle').value : '');
        fd.append('terms_conditions', document.getElementById('ipsTermsConditions') ? document.getElementById('ipsTermsConditions').value : '');
        fd.append('authorized_signature', document.getElementById('ipsAuthorizedSignature') ? document.getElementById('ipsAuthorizedSignature').value : '');
        fd.append('thank_you_message', document.getElementById('ipsThankYouMessage') ? document.getElementById('ipsThankYouMessage').value : '');
        var secLang = document.querySelector('input[name=invoice_secondary_language]:checked');
        fd.append('invoice_secondary_language', secLang ? secLang.value : '');
        fd.append('advertise_banner_path', document.getElementById('ipsBannerPath') ? document.getElementById('ipsBannerPath').value : '');
        fd.append('footer_show_banner', document.querySelector('input[name=footer_show_banner]') && document.querySelector('input[name=footer_show_banner]').checked ? '1' : '0');
        var logoFile = document.getElementById('ipsLogoFile');
        if (logoFile && logoFile.files && logoFile.files[0]) fd.append('company_logo', logoFile.files[0]);
        var bannerFile = document.getElementById('ipsBannerFile');
        if (bannerFile && bannerFile.files && bannerFile.files[0]) fd.append('advertise_banner', bannerFile.files[0]);
        return fd;
    }

    function showToast(msg) {
        var t = document.getElementById('ipsToast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(function() { t.classList.remove('show'); }, 3000);
    }

    var settingForSelect = document.getElementById('settingForSelect');
    if (settingForSelect) {
        settingForSelect.addEventListener('change', function() {
            window.location = 'invoice-print-settings.php?type=' + encodeURIComponent(this.value);
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
            if (previewId <= 0) return;
            var designRadio = document.querySelector('input[name=design_template]:checked');
            var template = designRadio ? designRadio.value : 'template_1';
            window.open('sale-invoice-print.php?id=' + previewId + '&design_preview=' + encodeURIComponent(template), '_blank', 'width=1000,height=800');
        });
    }
    var previewTemplateBtn = document.getElementById('ipsBtnPreviewTemplate');
    if (previewTemplateBtn) {
        previewTemplateBtn.addEventListener('click', function() {
            if (previewId <= 0) return;
            var sel = document.getElementById('ipsInvoiceTemplate');
            var layoutTemplate = sel ? sel.value : 'template_classic';
            window.open('sale-invoice-print.php?id=' + previewId + '&template_preview=' + encodeURIComponent(layoutTemplate), '_blank', 'width=1000,height=800');
        });
    }

    // Layout type: update highlight on click (so Thermal 80mm shows selected when clicked)
    var layoutOptions = document.getElementById('ipsLayoutOptions');
    if (layoutOptions) {
        layoutOptions.addEventListener('click', function(e) {
            var option = e.target.closest('.ips-layout-option');
            if (option) {
                document.querySelectorAll('.ips-layout-option').forEach(function(lab) { lab.classList.remove('selected'); });
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
        }).catch(function() {
            btn.disabled = false;
            showToast('Network error.');
        });
    });

    document.getElementById('ipsBtnPreview').addEventListener('click', function() {
        if (previewId <= 0) return;
        window.open('sale-invoice-print.php?id=' + previewId, '_blank', 'width=1000,height=800');
    });

    // Sortable: connect Available and Visible lists so items can move between them
    var availableList = document.getElementById('availableColumnsList');
    var visibleList = document.getElementById('visibleColumnsList');
    if (typeof Sortable !== 'undefined') {
        new Sortable(availableList, {
            group: 'columns',
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag'
        });
        new Sortable(visibleList, {
            group: 'columns',
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag'
        });
    }
})();
</script>
</body>
</html>
