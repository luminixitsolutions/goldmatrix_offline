<?php
require_once __DIR__ . '/includes/auragold_ui_font_settings.php';
require_once __DIR__ . '/includes/auragold_assets.php';
require_once __DIR__ . '/includes/auragold_page_asset_flags.php';

if (!isset($AURAGOLD_USE_DATATABLES)) {
    $AURAGOLD_USE_DATATABLES = auragold_page_wants_datatables();
}
if (!isset($AURAGOLD_USE_DATATABLE_BUTTONS)) {
    $AURAGOLD_USE_DATATABLE_BUTTONS = $AURAGOLD_USE_DATATABLES;
}
if (!isset($AURAGOLD_USE_FINANCIAL_EXPORT_JS)) {
    $AURAGOLD_USE_FINANCIAL_EXPORT_JS = auragold_page_wants_financial_export_js();
}

$__auragold_use_datatables = !empty($AURAGOLD_USE_DATATABLES);
$__auragold_use_dt_buttons = !empty($AURAGOLD_USE_DATATABLE_BUTTONS);
$__auragold_use_fin_export = !empty($AURAGOLD_USE_FINANCIAL_EXPORT_JS);
?>
<link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(auragold_asset_url('assets/img/logo.png'), ENT_QUOTES, 'UTF-8'); ?>">
<!-- Early hide Google Translate top strip (full rules load with footer widget CSS) -->
<style id="auragold-gt-banner-kill-early">
iframe.goog-te-banner-frame,.goog-te-banner-frame,.VIpgJd-ZVi9od-ORHb-OEVmcd{display:none!important;visibility:hidden!important;height:0!important;opacity:0!important}
html,body{top:0!important;margin-top:0!important}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://code.jquery.com" crossorigin>
<link rel="preconnect" href="https://cdn.datatables.net" crossorigin>
<link rel="dns-prefetch" href="//cdn.jsdelivr.net">
<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">

    <!-- Google Fonts (Set Software → Font Setting, or defaults) -->
    <?php auragold_ui_font_print_google_fonts_link(); ?>

    <!-- Icon fonts (core set; extra icon packs load only when needed) -->
    <?php auragold_echo_stylesheet('assets/fonts/fontawesome.css'); ?>
    <?php auragold_echo_stylesheet('assets/fonts/feather.css'); ?>
    <?php auragold_echo_stylesheet('assets/fonts/ionicons.css'); ?>

    <!-- Core stylesheets -->
    <?php auragold_echo_stylesheet('assets/css/bootstrap-material.css'); ?>
    <?php auragold_echo_stylesheet('assets/css/shreerang-material.css'); ?>
    <?php auragold_echo_stylesheet('assets/css/uikit.css'); ?>

    <!-- Libs -->
    <?php auragold_echo_stylesheet('assets/libs/perfect-scrollbar/perfect-scrollbar.css'); ?>
    <?php auragold_echo_stylesheet('assets/css/newcss.css'); ?>
    <?php auragold_ui_font_print_overrides_style(); ?>
    <?php auragold_echo_stylesheet('assets/libs/bootstrap-sweetalert/bootstrap-sweetalert.css'); ?>
    <?php auragold_echo_stylesheet('style.css'); ?>
    <?php auragold_echo_stylesheet('assets/css/advance-filter-global.css'); ?>
    <?php if (auragold_page_wants_product_list_css()): ?>
    <?php auragold_echo_stylesheet('assets/css/product-list-invoice-layout.css'); ?>
    <?php auragold_echo_stylesheet('assets/css/column-drag-icons.css'); ?>
    <?php endif; ?>
    <?php if ($__auragold_use_fin_export || !empty($AURAGOLD_FS_PAGE)): ?>
    <?php auragold_echo_stylesheet('assets/css/fs-financial-toolbar.css'); ?>
    <?php endif; ?>
    <?php auragold_echo_stylesheet('assets/css/auragold-mobile-chrome.css'); ?>
    <?php if (!empty($AURAGOLD_REPORT_PAGE)): ?>
    <?php auragold_echo_stylesheet('assets/css/report-pages-mobile.css'); ?>
    <?php endif; ?>
    <?php if ($__auragold_use_datatables): ?>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <?php if ($__auragold_use_dt_buttons): ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- jQuery (single load for all pages) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
    <?php if ($__auragold_use_fin_export): ?>
    <?php auragold_echo_script('assets/js/auragold-financial-export.js', true); ?>
    <?php endif; ?>
    
    <?php if ($__auragold_use_datatables): ?>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js" defer></script>
    <?php if ($__auragold_use_dt_buttons): ?>
    <!-- DataTables Buttons Extension -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js" defer></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js" defer></script>
    <?php endif; ?>
    <?php endif; ?>

<script defer>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery === 'undefined') {
        return;
    }
    var $ = jQuery;

    /* ========= Initialize Transaction Table ONLY IF EXISTS ========= */
    if ($('#transactionTable').length && !$('#transactionTable').hasClass('no-datatable')) {
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#transactionTable')) {
            $('#transactionTable').DataTable({
                ordering: false,
                paging: false,
                searching: false,
                info: false
            });
        }
    }

    /* ========= Fix Hidden Tab Issue ========= */
    $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
        if (!$.fn.dataTable) {
            return;
        }
        $.fn.dataTable
            .tables({ visible: true, api: true })
            .columns.adjust()
            .draw();
    });
});
</script>

<style>
/* DataTables Custom Styling */
.dataTables_wrapper {
    width: 100%;
}
.dataTables_wrapper .datatable-header,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dt-buttons,
.dataTables_wrapper .dataTables_length {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}
.datatable-header {
    display: flex !important;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    flex-wrap: wrap;
    gap: 10px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 0;
}
.datatable-search {
    order: 1;
    flex: 1;
}
.datatable-buttons {
    order: 2;
    display: flex !important;
    gap: 8px;
}
.datatable-length {
    order: 3;
}
.datatable-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    flex-wrap: wrap;
    background: #fff;
    border-top: 1px solid #e2e8f0;
}
.dataTables_wrapper .dataTables_filter {
    display: flex !important;
    align-items: center;
}
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    margin-left: 8px;
    min-width: 250px;
    font-size: 14px;
}
.dataTables_wrapper .dataTables_length {
    display: flex !important;
    align-items: center;
}
.dataTables_wrapper .dataTables_length select {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 6px 10px;
    margin: 0 5px;
}
.dt-buttons {
    display: flex !important;
    gap: 8px;
}
.dt-buttons .btn {
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 4px;
    display: inline-flex !important;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    text-decoration: none;
}
.dt-buttons .btn-success,
.dt-buttons .buttons-excel {
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    color: #fff !important;
}
.dt-buttons .btn-success:hover,
.dt-buttons .buttons-excel:hover {
    background-color: #218838 !important;
    border-color: #1e7e34 !important;
}
.dt-buttons .btn-secondary,
.dt-buttons .buttons-print {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    color: #fff !important;
}
.dt-buttons .btn-secondary:hover,
.dt-buttons .buttons-print:hover {
    background-color: #5a6268 !important;
    border-color: #545b62 !important;
}
.dataTables_info {
    padding: 8px 0;
    color: #666;
    font-size: 13px;
}
.dataTables_paginate {
    display: flex;
    align-items: center;
    gap: 4px;
}
.dataTables_paginate .paginate_button {
    padding: 6px 12px;
    margin: 0 2px;
    border-radius: 4px;
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
}
.dataTables_paginate .paginate_button.current {
    background: #11294b !important;
    color: #fff !important;
    border-color: #11294b !important;
}
.dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) {
    background: #f8f9fa;
    border-color: #ddd;
}
.dataTables_paginate .paginate_button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.table-container {
    overflow-x: auto;
    overflow-y: visible;
}
.table-container .dataTables_wrapper {
    overflow: visible;
}
ul#ledgerTabs.nav-tabs {
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
}
ul#ledgerTabs.nav-tabs .nav-link {
    color: #0f172a !important;
    background: transparent !important;
    border: none !important;
    border-bottom: 3px solid transparent !important;
    border-radius: 0 !important;
    font-weight: 600;
    font-size: 0.72rem;
    text-transform: uppercase;
    padding: 0.55rem 0.75rem;
}
ul#ledgerTabs.nav-tabs .nav-link:hover {
    color: #11294b !important;
}
ul#ledgerTabs.nav-tabs .nav-link.active {
    color: #11294b !important;
    border-bottom-color: #c5a864 !important;
    background: transparent !important;
}
</style>
<?php
require_once __DIR__ . '/includes/brand_page_loader.php';
if (auragold_brand_page_loader_should_show()) {
    echo auragold_brand_page_loader_css();
    echo auragold_brand_page_loader_js();
}
?>
