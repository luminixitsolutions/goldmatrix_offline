<?php
/**
 * Jewellery Catalogue — grid + list views, advance filter, create actions.
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/jewelry_catalog_stock_include.php';
if (!function_exists('auragold_nav_show_php_href')) {
    require_once __DIR__ . '/includes/auragold_sidebar_nav_permissions.php';
}

$jcat_no_image = 'no_image.jpg';
$jcat_featured_display_img = 'assets/img/jcat-featured-display.png';
$jcat_promo_banner_img = 'assets/img/jcat-promo-banner.png';
$jcat_share_page = 'jewelry-catalogue-share.php';
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $jcat_sd = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
    if ($jcat_sd !== '' && $jcat_sd !== '/' && $jcat_sd !== '.') {
        $jcat_no_image = rtrim($jcat_sd, '/') . '/no_image.jpg';
        $jcat_featured_display_img = rtrim($jcat_sd, '/') . '/assets/img/jcat-featured-display.png';
        $jcat_promo_banner_img = rtrim($jcat_sd, '/') . '/assets/img/jcat-promo-banner.png';
        $jcat_share_page = rtrim($jcat_sd, '/') . '/jewelry-catalogue-share.php';
    }
}
$jcat_title = function_exists('auragold_t')
    ? auragold_t('inv.jewellery_catalogue')
    : 'Jewellery Catalogue';
$jcat_display_title = 'Product Catalogue';

$jcat_masters = auragold_jewelry_catalog_filter_masters($conn);
$jcat_branches = $jcat_masters['branches'] ?? [];
$jcat_metals = $jcat_masters['metals'] ?? [];
$jcat_products = $jcat_masters['products'] ?? [];
$jcat_categories = $jcat_masters['categories'] ?? [];
$jcat_articles = $jcat_masters['articles'] ?? [];
$jcat_carats = $jcat_masters['carats'] ?? [];

$jcat_can_sale_order = !function_exists('auragold_nav_show_php_href') || auragold_nav_show_php_href('sale-order.php');
$jcat_can_sale_quot = !function_exists('auragold_nav_show_php_href') || auragold_nav_show_php_href('sale-quotations.php');
?>
<!DOCTYPE html>
<html lang="en" class="default-style jcat-page-root">
<head>
    <title><?php echo htmlspecialchars($jcat_title, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/header-script.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <link rel="stylesheet" href="assets/css/advance-filter-global.css">
    <link rel="stylesheet" href="assets/css/jewelry-catalogue.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/jewelry-catalogue.css'); ?>">
    <style>
        /* Enable page scroll (global newcss locks body/html overflow) */
        html.jcat-page-root,
        html.jcat-page-root body.jcat-page {
            overflow-x: hidden !important;
            overflow-y: auto !important;
            height: auto !important;
            min-height: 100vh;
        }
        body.jcat-page .layout-wrapper {
            height: auto !important;
            min-height: 100vh;
            overflow: visible !important;
        }
        body.jcat-page .layout-content {
            height: auto !important;
            min-height: calc(100vh - 128px);
            overflow: visible !important;
            overflow-y: visible !important;
            padding-bottom: 16px !important;
            -webkit-overflow-scrolling: touch;
        }
        body.jcat-page .layout-content .container-fluid.jcat-wrap {
            margin-left: 0;
            max-width: 100%;
            padding-left: 20px;
            padding-right: 20px;
        }
    </style>
</head>
<body class="jcat-page">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="layout-content">
    <div class="jcat-wrap container-fluid flex-grow-1">
        <header class="jcat-hero">
            <div class="jcat-hero-row">
                <div class="jcat-hero-brand">
                    <h1 class="jcat-page-title"><?php echo htmlspecialchars($jcat_display_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="jcat-page-subtitle">Manage your jewellery collection</p>
                </div>
                <div class="jcat-hero-search">
                    <div class="jcat-search">
                        <label for="jcatSearch" class="sr-only">Search</label>
                        <i class="feather icon-search jcat-search-icon-left" aria-hidden="true"></i>
                        <input type="search" id="jcatSearch" placeholder="Search jewellery by name, category, code…" autocomplete="off">
                    </div>
                </div>
                <div class="jcat-hero-actions jcat-toolbar-right">
                        <div class="dropdown jcat-import-dropdown">
                            <button type="button" class="jcat-btn-outline btn btn-sm dropdown-toggle" id="jcatImportBtn"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Import">
                                <i class="feather icon-upload"></i> Import
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item js-jcat-excel-import-trigger" href="#"><i class="feather icon-upload mr-2"></i>Import Excel</a>
                                <a class="dropdown-item js-jcat-excel-sample-download" href="ajax/download-jewelry-catalogue-excel-sample.php"><i class="feather icon-download mr-2"></i>Download Sample</a>
                            </div>
                        </div>
                        <input type="file" id="jcatExcelImportFile" accept=".xlsx,.xls" style="display:none;" tabindex="-1">
                        <button type="button" class="jcat-icon-btn" id="jcatBtnFilter" title="Advance Filter" aria-label="Advance Filter">
                            <i class="feather icon-filter"></i>
                            <span class="jcat-filter-badge" id="jcatFilterBadge">0</span>
                        </button>
                        <button type="button" class="jcat-btn-outline btn btn-sm" id="jcatSync">
                            <i class="feather icon-refresh-cw"></i> Sync Jewellery Catalogue
                        </button>
                        <div class="dropdown jcat-create-dropdown" id="jcatCreateDropdown">
                            <button type="button" class="jcat-btn-navy btn btn-sm dropdown-toggle" id="jcatCreateBtn"
                                aria-haspopup="true" aria-expanded="false">Create</button>
                            <div class="dropdown-menu dropdown-menu-right" id="jcatCreateMenu" aria-labelledby="jcatCreateBtn">
                                <a class="dropdown-item<?php echo $jcat_can_sale_order ? '' : ' disabled'; ?>" href="#"
                                    id="jcatNewSaleOrder" role="button"
                                    <?php echo $jcat_can_sale_order ? '' : ' aria-disabled="true" tabindex="-1"'; ?>>
                                    <i class="feather icon-file-text"></i> New Sale Order
                                </a>
                                <a class="dropdown-item<?php echo $jcat_can_sale_quot ? '' : ' disabled'; ?>" href="#"
                                    id="jcatNewSaleQuotation" role="button"
                                    <?php echo $jcat_can_sale_quot ? '' : ' aria-disabled="true" tabindex="-1"'; ?>>
                                    <i class="feather icon-file"></i> Create Sale Quotation
                                </a>
                                <a class="dropdown-item<?php echo $jcat_can_sale_quot ? '' : ' disabled'; ?>"
                                    href="<?php echo $jcat_can_sale_quot ? 'sale-quotations.php' : '#'; ?>">
                                    <i class="feather icon-layers"></i> Catalogue Quotation
                                </a>
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item text-muted" id="jcatSendWhatsApp" disabled>
                                    <i class="fab fa-whatsapp"></i> Send to WhatsApp Web
                                </button>
                                <button type="button" class="dropdown-item text-muted" id="jcatSendMail" disabled>
                                    <i class="feather icon-mail"></i> Send to Mail
                                </button>
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item text-muted" id="jcatDeleteSelected" disabled>
                                    <i class="feather icon-trash-2"></i> Delete Catalogue
                                </button>
                                <button type="button" class="dropdown-item" id="jcatUpdateRecords">
                                    <i class="feather icon-refresh-cw"></i> Update Records
                                </button>
                            </div>
                        </div>
                        <a href="jewelry-catalogue-create.php?return=jewelry-catalogue.php" class="jcat-btn-gold" id="jcatBtnAdd">
                            <i class="feather icon-plus"></i> Add
                        </a>
                        <button type="button" class="jcat-icon-btn active" id="jcatViewGrid" title="Grid view" aria-pressed="true"><i class="feather icon-grid"></i></button>
                        <button type="button" class="jcat-icon-btn" id="jcatViewList" title="List view" aria-pressed="false"><i class="feather icon-list"></i></button>
                </div>
            </div>
        </header>

        <div class="jcat-body">
            <div class="jcat-sidebar-backdrop" id="jcatSidebarBackdrop" aria-hidden="true"></div>
            <aside class="jcat-sidebar" id="jcatSidebar" aria-label="Catalogue filters">
                <div class="jcat-sidebar-head">
                    <h2>Filters</h2>
                    <div class="jcat-sidebar-head-actions">
                        <button type="button" class="jcat-sidebar-advance" id="jcatOpenAdvanceFilter">Advanced</button>
                        <button type="button" class="jcat-sidebar-reset" id="jcatSidebarReset">Reset All</button>
                        <button type="button" class="jcat-sidebar-close" id="jcatSidebarClose" aria-label="Close filters">&times;</button>
                    </div>
                </div>
                <div class="jcat-filter-section">
                    <span class="jcat-filter-label">Categories</span>
                    <ul class="jcat-cat-list" id="jcatSidebarCategories"></ul>
                </div>
                <div class="jcat-filter-section">
                    <span class="jcat-filter-label">Price Range</span>
                    <div class="jcat-price-range">
                        <input type="range" class="jcat-price-slider" id="jcatPriceMax" min="0" max="100000" step="100" value="100000">
                        <div class="jcat-price-inputs">
                            <input type="number" id="jcatPriceMin" min="0" placeholder="Min" value="0">
                            <span>–</span>
                            <input type="number" id="jcatPriceMaxVal" min="0" placeholder="Max" value="100000">
                        </div>
                    </div>
                </div>
                <div class="jcat-filter-section">
                    <label class="jcat-filter-label" for="jcatSidebarMetal">Metal Type</label>
                    <select id="jcatSidebarMetal">
                        <option value="0">All Metals</option>
                        <?php foreach ($jcat_metals as $m) {
                            $mid = (int) ($m['id'] ?? 0);
                            echo '<option value="' . $mid . '">' . htmlspecialchars((string) ($m['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="jcat-filter-section">
                    <label class="jcat-filter-label" for="jcatSortBy">Sort By</label>
                    <select id="jcatSortBy">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="weight_asc">Weight: Low to High</option>
                        <option value="weight_desc">Weight: High to Low</option>
                    </select>
                </div>
                <button type="button" class="jcat-sidebar-apply" id="jcatSidebarApply">Apply Filters</button>
            </aside>

            <main class="jcat-main">
                <div class="jcat-tabs-shell">
                    <div class="jcat-metal-tabs" id="jcatMetalTabs" role="tablist">
                        <button type="button" class="jcat-tab active" data-metal-id="0"><i class="feather icon-grid"></i> All</button>
                    </div>
                </div>

                <div id="jcatCatalogueView" class="jcat-catalogue-view" aria-live="polite">
                    <div class="jcat-loading" id="jcatLoading">
                        <div class="spinner-border text-secondary" role="status"></div>
                        <div>Loading catalogue…</div>
                    </div>
                </div>

                <div id="jcatGrid" class="jcat-grid d-none" aria-hidden="true"></div>

                <div id="jcatListWrap" class="jcat-list-wrap d-none" aria-live="polite">
                    <table class="jcat-table" id="jcatTable">
                        <thead>
                            <tr id="jcatHeaderRow">
                                <th class="jcat-col-lock" data-col="_cb" style="width:40px;">
                                    <span class="jcat-th-inner"><input type="checkbox" id="jcatCheckAll" title="Select all"></span>
                                </th>
                                <th data-col="imageUrls" style="width:72px;">
                                    <span class="jcat-th-inner"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>imageUrls</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                                <th data-col="active" style="width:80px;">
                                    <span class="jcat-th-inner"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>active</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                                <th data-col="jewelryCatalogue" style="width:120px;">
                                    <span class="jcat-th-inner"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>jewelryCatalogue</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                                <th data-col="productName" style="width:140px;">
                                    <span class="jcat-th-inner"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>Product Name</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                                <th data-col="designNo" style="width:110px;">
                                    <span class="jcat-th-inner"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>Design No</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                                <th data-col="variants" style="width:90px;">
                                    <span class="jcat-th-inner"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>Variants</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                                <th data-col="billOfMaterial" style="width:120px;">
                                    <span class="jcat-th-inner"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>Bill Of Material</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                                <th data-col="weight" class="text-right" style="width:88px;">
                                    <span class="jcat-th-inner" style="justify-content:flex-end;"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>Weight</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                                <th data-col="amount" class="text-right" style="width:88px;">
                                    <span class="jcat-th-inner" style="justify-content:flex-end;"><span class="jcat-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span>Amount</span>
                                    <span class="jcat-resize-handle" title="Resize column"></span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="jcatTableBody"></tbody>
                        <tfoot>
                            <tr class="jcat-foot-row" id="jcatFooterRow">
                                <td data-col="_cb"></td>
                                <td data-col="imageUrls"></td>
                                <td data-col="active"></td>
                                <td data-col="jewelryCatalogue"></td>
                                <td data-col="productName"></td>
                                <td data-col="designNo"></td>
                                <td data-col="variants"></td>
                                <td data-col="billOfMaterial" class="text-right">Total</td>
                                <td data-col="weight" class="text-right" id="jcatFootWt">0.000</td>
                                <td data-col="amount" class="text-right" id="jcatFootAmt">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="jcat-footer">
                    <span id="jcatSummary">Showing 0 entries</span>
                    <label>
                        Show
                        <select id="jcatPerPage" class="custom-select custom-select-sm d-inline-block w-auto ml-1">
                            <option value="25" selected>25 Items</option>
                            <option value="50">50 Items</option>
                            <option value="100">100 Items</option>
                            <option value="500">All Items</option>
                        </select>
                    </label>
                </div>
            </main>
        </div>

        <div class="jcat-stats-bar" id="jcatStatsBar">
            <div class="jcat-stat">
                <div class="jcat-stat-icon"><i class="feather icon-shopping-bag"></i></div>
                <div>
                    <span class="jcat-stat-label">Total Products</span>
                    <span class="jcat-stat-value" id="jcatStatTotal">0</span>
                </div>
            </div>
            <div class="jcat-stat">
                <div class="jcat-stat-icon"><i class="feather icon-layers"></i></div>
                <div>
                    <span class="jcat-stat-label">Total Weight</span>
                    <span class="jcat-stat-value" id="jcatStatWeight">0.000 g</span>
                </div>
            </div>
            <div class="jcat-stat">
                <div class="jcat-stat-icon"><i class="feather icon-tag"></i></div>
                <div>
                    <span class="jcat-stat-label">Price Range</span>
                    <span class="jcat-stat-value" id="jcatStatPrice">₹ 0 – ₹ 0</span>
                </div>
            </div>
            <div class="jcat-stat">
                <div class="jcat-stat-icon"><i class="feather icon-calendar"></i></div>
                <div>
                    <span class="jcat-stat-label">Last Updated</span>
                    <span class="jcat-stat-value" id="jcatStatUpdated">—</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="jcatFilterOverlay" class="filter-modal-overlay" aria-hidden="true">
    <div class="filter-modal" role="dialog" aria-labelledby="jcatFilterTitle">
        <div class="filter-modal-head">
            <span id="jcatFilterTitle">Advance Filter</span>
            <button type="button" class="filter-modal-close" id="jcatFilterClose" aria-label="Close">&times;</button>
        </div>
        <div class="filter-modal-body">
            <form id="jcatFilterForm" class="jcat-filter-grid" autocomplete="off" onsubmit="return false;">
                <div class="filter-field">
                    <label for="jcatFBranch">Branch</label>
                    <select id="jcatFBranch" name="branch_id" class="form-control form-control-sm">
                        <option value="">All branches</option>
                        <?php foreach ($jcat_branches as $br) {
                            $bid = (int) ($br['id'] ?? 0);
                            echo '<option value="' . $bid . '">' . htmlspecialchars((string) ($br['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="jcatFMetal">Metal</label>
                    <select id="jcatFMetal" name="metal_id" class="form-control form-control-sm">
                        <option value="">Select Metal</option>
                        <?php foreach ($jcat_metals as $m) {
                            $mid = (int) ($m['id'] ?? 0);
                            echo '<option value="' . $mid . '">' . htmlspecialchars((string) ($m['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="jcatFProduct">Product</label>
                    <select id="jcatFProduct" name="product_id" class="form-control form-control-sm">
                        <option value="">Select Product Name</option>
                        <?php foreach ($jcat_products as $pr) {
                            $pid = (int) ($pr['id'] ?? 0);
                            echo '<option value="' . $pid . '">' . htmlspecialchars((string) ($pr['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="jcatFArticle">Article</label>
                    <select id="jcatFArticle" name="article" class="form-control form-control-sm">
                        <option value="">Select Article</option>
                        <?php foreach ($jcat_articles as $ar) {
                            $art = trim((string) ($ar['article'] ?? ''));
                            if ($art === '') continue;
                            echo '<option value="' . htmlspecialchars($art, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($art, ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="jcatFCategory">Category</label>
                    <select id="jcatFCategory" name="category_id" class="form-control form-control-sm">
                        <option value="">Select Category</option>
                        <?php foreach ($jcat_categories as $cat) {
                            $cid = (int) ($cat['id'] ?? 0);
                            echo '<option value="' . $cid . '">' . htmlspecialchars((string) ($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="jcatFKarat">Gold Karat</label>
                    <select id="jcatFKarat" class="form-control form-control-sm">
                        <option value="">Select Gold Karat</option>
                        <?php foreach ($jcat_carats as $cr) {
                            $cn = trim((string) ($cr['name'] ?? ''));
                            if ($cn === '') continue;
                            echo '<option value="' . htmlspecialchars($cn, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($cn, ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="jcatFLocation">Location</label>
                    <input type="text" id="jcatFLocation" name="location" class="form-control form-control-sm" placeholder="">
                </div>
                <div class="filter-field">
                    <label for="jcatFDesign">Design No.</label>
                    <input type="text" id="jcatFDesign" name="design_no" class="form-control form-control-sm">
                </div>
                <div class="filter-field">
                    <label for="jcatFBarcode">Barcode No.</label>
                    <input type="text" id="jcatFBarcode" name="barcode" class="form-control form-control-sm">
                </div>
                <div class="filter-field">
                    <label for="jcatFRfid">RFID Code</label>
                    <input type="text" id="jcatFRfid" name="rfid_code" class="form-control form-control-sm">
                </div>
                <div class="filter-field">
                    <label for="jcatFGross">Gross Wt</label>
                    <input type="text" id="jcatFGross" name="gross_wt" class="form-control form-control-sm">
                </div>
                <div class="filter-field">
                    <label for="jcatFComment">Comment</label>
                    <input type="text" id="jcatFComment" name="comment" class="form-control form-control-sm">
                </div>
            </form>
        </div>
        <div class="filter-modal-actions" style="padding:0 14px 14px;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" class="btn btn-sm btn-primary" id="jcatFilterApply">Apply Filter</button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="jcatFilterClear">Clear Filter</button>
        </div>
    </div>
</div>

<div id="jcatDetailsOverlay" class="filter-modal-overlay jcat-details-overlay" aria-hidden="true">
    <div class="jcat-details-modal" role="dialog" aria-labelledby="jcatDetailsTitle" aria-modal="true">
        <div class="jcat-details-head">
            <div>
                <p class="jcat-details-kicker">Product Details</p>
                <h2 id="jcatDetailsTitle" class="jcat-details-title">Catalogue Item</h2>
            </div>
            <button type="button" class="jcat-details-close" id="jcatDetailsClose" aria-label="Close">&times;</button>
        </div>
        <div class="jcat-details-body" id="jcatDetailsBody">
            <div class="jcat-details-loading">Loading product details…</div>
        </div>
        <div class="jcat-details-foot" id="jcatDetailsFoot"></div>
    </div>
</div>

<div id="jcatExcelImportLoader" class="jcat-excel-import-loader" aria-hidden="true">
    <div class="jcat-excel-import-loader__panel">
        <div class="jcat-excel-import-loader__spinner" aria-hidden="true"></div>
        <p class="jcat-excel-import-loader__text">Importing catalogue… Please wait.</p>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    var NO_IMG = <?php echo json_encode($jcat_no_image, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
    var JCAT_FEATURED_DISPLAY_IMG = <?php echo json_encode($jcat_featured_display_img, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
    var JCAT_PROMO_BANNER_IMG = <?php echo json_encode($jcat_promo_banner_img, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
    var JCAT_SHARE_PAGE = <?php echo json_encode($jcat_share_page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
    var JCAT_SITE_URL = <?php echo json_encode(rtrim((string) ($SiteUrl ?? ''), '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
    var API = 'ajax/list-jewelry-catalog-stock.php';
    var allItems = [];
    var allMetals = [];
    var metalId = 0;
    var sidebarMetalIds = {};
    var priceMin = 0;
    var priceMax = 100000;
    var sortBy = 'newest';
    var viewMode = localStorage.getItem('jcat_view') || 'grid';
    var page = 1;
    var perPage = 25;
    var searchTimer = null;
    var selectedBarcodes = {};
    var lastUpdated = new Date();

    var $catalogueView = document.getElementById('jcatCatalogueView');
    var $grid = document.getElementById('jcatGrid');
    var $listWrap = document.getElementById('jcatListWrap');
    var $tableBody = document.getElementById('jcatTableBody');
    var $loading = document.getElementById('jcatLoading');
    var $tabs = document.getElementById('jcatMetalTabs');
    var $search = document.getElementById('jcatSearch');
    var $summary = document.getElementById('jcatSummary');
    var $perPage = document.getElementById('jcatPerPage');
    var $filterOverlay = document.getElementById('jcatFilterOverlay');
    var $filterForm = document.getElementById('jcatFilterForm');
    var $filterBadge = document.getElementById('jcatFilterBadge');
    var $btnGrid = document.getElementById('jcatViewGrid');
    var $btnList = document.getElementById('jcatViewList');
    var $sidebarCategories = document.getElementById('jcatSidebarCategories');

    var METAL_ICONS = {
        'gold': 'icon-star',
        'silver': 'icon-circle',
        'platinum': 'icon-award',
        'diamond': 'icon-aperture',
        'imitation': 'icon-watch',
        'watch': 'icon-watch',
        'loose': 'icon-target',
        'other': 'icon-box',
        'service': 'icon-tool'
    };

    function metalIcon(name) {
        var n = String(name || '').toLowerCase();
        var keys = Object.keys(METAL_ICONS);
        for (var i = 0; i < keys.length; i++) {
            if (n.indexOf(keys[i]) !== -1) return METAL_ICONS[keys[i]];
        }
        return 'icon-disc';
    }

    function parseAmount(it) {
        var raw = it.amount != null ? it.amount : (it.amount_label || '0');
        if (typeof raw === 'number') return raw;
        return parseFloat(String(raw).replace(/[^\d.-]/g, '')) || 0;
    }

    function parseWeight(it) {
        if (it.current_weight != null) return parseFloat(it.current_weight) || 0;
        return parseFloat(String(it.weight_label || '0').replace(/[^\d.-]/g, '')) || 0;
    }

    function cardTitle(it) {
        return it.product_name ? it.product_name + ' - ' + it.metal_name : (it.title || '');
    }

    function formatCurrency(n) {
        return '₹ ' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function jcatFeatherRefresh(scope) {
        if (window.feather && typeof window.feather.replace === 'function') {
            try {
                window.feather.replace(scope ? { scope: scope } : undefined);
            } catch (e) { /* ignore */ }
        }
    }

    function sortCatalogueItemsFirst(items) {
        return (items || []).slice().sort(function (a, b) {
            var aOnly = a.is_catalogue_only ? 1 : 0;
            var bOnly = b.is_catalogue_only ? 1 : 0;
            if (aOnly !== bOnly) return bOnly - aOnly;
            var aId = parseInt(a.catalogue_id, 10) || 0;
            var bId = parseInt(b.catalogue_id, 10) || 0;
            if (aId !== bId) return bId - aId;
            return (parseInt(b.stock_id, 10) || 0) - (parseInt(a.stock_id, 10) || 0);
        });
    }

    function highlightCatalogueId() {
        try {
            return parseInt(new URLSearchParams(window.location.search).get('catalogue_id'), 10) || 0;
        } catch (e) {
            return 0;
        }
    }

    function esc(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    window.jcatImgError = function (img) {
        if (!img) return;
        var wrap = img.parentElement;
        if (!wrap) return;
        img.remove();
        if (!wrap.querySelector('.jcat-no-image-ph')) {
            wrap.insertAdjacentHTML('beforeend', jcatNoImagePlaceholder());
        }
        wrap.classList.add('jcat-show-no-label');
    };

    function jcatFeaturedImgHtml(thumb) {
        var hasThumb = thumb && String(thumb).trim();
        var src = hasThumb ? String(thumb).trim() : (JCAT_FEATURED_DISPLAY_IMG || NO_IMG);
        var cls = 'jcat-featured-bg-img' + (hasThumb ? '' : ' jcat-featured-fallback-img');
        return '<img class="' + cls + '" src="' + esc(src) + '" alt="" loading="lazy" onerror="window.jcatFeaturedImgError(this)">';
    }

    window.jcatFeaturedImgError = function (img) {
        if (!img) return;
        var wrap = img.closest('.jcat-featured-bg');
        if (!wrap) wrap = img.closest('.jcat-featured-img');
        if (img.dataset.jcatFallback === '1') {
            img.remove();
            if (wrap && !wrap.querySelector('.jcat-no-image-ph')) {
                wrap.insertAdjacentHTML('beforeend', jcatNoImagePlaceholder());
            }
            return;
        }
        img.dataset.jcatFallback = '1';
        if (JCAT_FEATURED_DISPLAY_IMG && img.src.indexOf('jcat-featured-display') === -1) {
            img.src = JCAT_FEATURED_DISPLAY_IMG;
            img.classList.add('jcat-featured-fallback-img');
            return;
        }
        img.remove();
        if (wrap && !wrap.querySelector('.jcat-no-image-ph')) {
            wrap.insertAdjacentHTML('beforeend', jcatNoImagePlaceholder());
        }
    };

    function jcatNoImagePlaceholder() {
        return '<div class="jcat-no-image-ph" aria-hidden="true">'
            + '<span class="jcat-no-image-icon"><i class="feather icon-aperture"></i></span>'
            + '<span>Image unavailable</span></div>';
    }

    function isUsableThumb(thumb) {
        if (!thumb || !String(thumb).trim()) return false;
        var lower = String(thumb).trim().toLowerCase();
        if (lower.indexOf('no_image') !== -1) return false;
        if (lower.indexOf('no-image') !== -1) return false;
        if (lower.indexOf('placeholder') !== -1) return false;
        var noBase = NO_IMG.split('/').pop().toLowerCase();
        if (noBase && lower.indexOf(noBase) !== -1) return false;
        return true;
    }

    function hasRealProductImage(it) {
        return isUsableThumb(it.thumb_url);
    }

    function splitShowcaseItems(items) {
        var list = items.slice();
        var featuredIdx = -1;
        var i;
        for (i = 0; i < list.length; i++) {
            if (hasRealProductImage(list[i])) {
                featuredIdx = i;
                break;
            }
        }
        var featured = featuredIdx >= 0 ? list.splice(featuredIdx, 1)[0] : list.shift();
        var med1 = list.shift();
        var med2 = list.shift();
        return { featured: featured, med1: med1, med2: med2, rest: list };
    }

    function jcatImgHtml(thumb, listClass, opts) {
        opts = opts || {};
        if (!isUsableThumb(thumb)) {
            return jcatNoImagePlaceholder();
        }
        var src = String(thumb).trim();
        var cls = listClass || 'jcat-thumb';
        return '<img class="' + cls + '" src="' + esc(src) + '" alt="" loading="lazy" onerror="window.jcatImgError(this)">';
    }

    function itemLabels(it) {
        var priceLabel = it.amount_label || formatCurrency(parseAmount(it));
        if (priceLabel.indexOf('₹') === -1) priceLabel = formatCurrency(parseAmount(it));
        var wtLabel = it.weight_label || (parseWeight(it).toFixed(3) + ' g');
        return { price: priceLabel, weight: wtLabel };
    }

    function cardAttrs(it) {
        var bc = it.barcode || '';
        var editHref = jcatCatalogueEditHref(it);
        var cardCls = editHref ? ' jcat-card-editable' : '';
        var editAttr = editHref ? ' data-edit-href="' + esc(editHref) + '"' : '';
        var checked = selectedBarcodes[bc] ? ' checked' : '';
        return {
            bc: bc,
            cardCls: cardCls,
            editAttr: editAttr,
            checked: checked,
            cid: parseInt(it.catalogue_id, 10) || 0
        };
    }

    function trimStr(s) {
        return s == null ? '' : String(s).trim();
    }

    function renderFeaturedCard(it) {
        var a = cardAttrs(it);
        var labels = itemLabels(it);
        var thumb = hasRealProductImage(it) ? String(it.thumb_url).trim() : '';
        var editHref = jcatCatalogueEditHref(it);
        var desc = trimStr(it.comment || it.article || '');
        var descHtml = desc ? '<p class="jcat-featured-desc">' + esc(desc) + '</p>' : '';
        return '<article class="jcat-featured jcat-card' + a.cardCls + '" data-barcode="' + esc(a.bc) + '" data-catalogue-id="' + a.cid + '"' + a.editAttr + '>'
            + '<div class="jcat-featured-bg">' + jcatFeaturedImgHtml(thumb) + '</div>'
            + '<div class="jcat-featured-overlay" aria-hidden="true"></div>'
            + '<div class="jcat-featured-content">'
            + '<div class="jcat-featured-top">'
            + '<span class="jcat-featured-kicker"><i class="feather icon-award"></i> Featured Collection</span>'
            + '<label class="jcat-card-select jcat-featured-check"><input type="checkbox" class="jcat-row-check" data-barcode="' + esc(a.bc) + '"' + a.checked + '></label>'
            + '</div>'
            + '<span class="jcat-featured-curated">Curated Jewellery</span>'
            + '<span class="jcat-featured-id">' + stockBadge(it) + '</span>'
            + '<h2 class="jcat-featured-title">' + esc(cardTitle(it)) + '</h2>'
            + '<div class="jcat-featured-pricing">'
            + '<span class="jcat-featured-price">' + esc(labels.price) + '</span>'
            + '<span class="jcat-featured-weight">' + esc(labels.weight) + '</span>'
            + '</div>'
            + descHtml
            + (editHref ? '<a href="' + esc(editHref) + '" class="jcat-featured-btn">View Details <i class="feather icon-arrow-right"></i></a>' : '')
            + '</div></article>';
    }

    function renderMediumCard(it) {
        var a = cardAttrs(it);
        var labels = itemLabels(it);
        var thumb = isUsableThumb(it.thumb_url) ? String(it.thumb_url).trim() : '';
        var metalHtml = it.metal_name ? '<p class="jcat-card-metal">' + esc(it.metal_name) + '</p>' : '';
        return '<article class="jcat-card jcat-card--medium' + a.cardCls + '" data-barcode="' + esc(a.bc) + '" data-catalogue-id="' + a.cid + '"' + a.editAttr + '>'
            + '<div class="jcat-card-medium-inner">'
            + '<div class="jcat-card-img">' + jcatImgHtml(thumb, 'jcat-thumb') + '</div>'
            + '<div class="jcat-card-medium-content">'
            + '<div class="jcat-card-top">'
            + '<span class="jcat-badge">' + stockBadge(it) + '</span>'
            + '<label class="jcat-card-select"><input type="checkbox" class="jcat-row-check" data-barcode="' + esc(a.bc) + '"' + a.checked + '></label>'
            + '</div>'
            + '<h2 class="jcat-card-title">' + esc(cardTitle(it)) + '</h2>'
            + metalHtml
            + '<p class="jcat-card-price">' + esc(labels.price) + '</p>'
            + '<p class="jcat-card-weight">' + esc(labels.weight) + '</p>'
            + '</div></div></article>';
    }

    function renderWideCard(it) {
        var a = cardAttrs(it);
        var labels = itemLabels(it);
        var thumb = isUsableThumb(it.thumb_url) ? String(it.thumb_url).trim() : '';
        return '<article class="jcat-card jcat-card--wide' + a.cardCls + '" data-barcode="' + esc(a.bc) + '" data-catalogue-id="' + a.cid + '"' + a.editAttr + '>'
            + '<div class="jcat-card-wide-inner">'
            + '<div class="jcat-card-img">' + jcatImgHtml(thumb, 'jcat-thumb') + '</div>'
            + '<div class="jcat-card-body">'
            + '<div class="jcat-card-head">'
            + '<span class="jcat-badge">' + stockBadge(it) + '</span>'
            + '<label class="jcat-card-select"><input type="checkbox" class="jcat-row-check" data-barcode="' + esc(a.bc) + '"' + a.checked + '></label>'
            + '</div>'
            + '<h2 class="jcat-card-title">' + esc(cardTitle(it)) + '</h2>'
            + (it.metal_name ? '<p class="jcat-card-metal">' + esc(it.metal_name) + '</p>' : '')
            + '<p class="jcat-card-price">' + esc(labels.price) + '</p>'
            + '<p class="jcat-card-weight">' + esc(labels.weight) + '</p>'
            + '</div></div></article>';
    }

    function renderCompactCard(it) {
        var a = cardAttrs(it);
        var labels = itemLabels(it);
        var thumb = isUsableThumb(it.thumb_url) ? String(it.thumb_url).trim() : '';
        var metalHtml = it.metal_name ? '<p class="jcat-card-metal">' + esc(it.metal_name) + '</p>' : '';
        return '<article class="jcat-card jcat-card--compact jcat-more-card' + a.cardCls + '" data-barcode="' + esc(a.bc) + '" data-catalogue-id="' + a.cid + '"' + a.editAttr + '>'
            + '<div class="jcat-card-compact-inner">'
            + '<div class="jcat-card-top">'
            + '<span class="jcat-badge">' + stockBadge(it) + '</span>'
            + '<label class="jcat-card-select"><input type="checkbox" class="jcat-row-check" data-barcode="' + esc(a.bc) + '"' + a.checked + '></label>'
            + '</div>'
            + '<div class="jcat-card-img">' + jcatImgHtml(thumb, 'jcat-thumb') + '</div>'
            + '<div class="jcat-card-body">'
            + '<h2 class="jcat-card-title">' + esc(cardTitle(it)) + '</h2>'
            + metalHtml
            + '<p class="jcat-card-price">' + esc(labels.price) + '</p>'
            + '<p class="jcat-card-weight">' + esc(labels.weight) + '</p>'
            + '</div></div></article>';
    }

    function stockBadge(item) {
        return esc(item.design_no || item.barcode || '');
    }

    function getFiltered() {
        var q = ($search && $search.value) ? $search.value.trim().toLowerCase() : '';
        var karatF = document.getElementById('jcatFKarat');
        var karatVal = karatF && karatF.value ? karatF.value.trim().toLowerCase() : '';
        var sidebarKeys = Object.keys(sidebarMetalIds).filter(function (k) { return sidebarMetalIds[k]; });
        var totalSidebarMetals = allMetals.length;
        var useSidebarMetalFilter = totalSidebarMetals > 0
            && sidebarKeys.length > 0
            && sidebarKeys.length < totalSidebarMetals;
        return allItems.filter(function (it) {
            var mid = parseInt(it.metal_id, 10) || 0;
            if (metalId > 0 && mid !== metalId) return false;
            if (useSidebarMetalFilter && sidebarKeys.indexOf(String(mid)) === -1) return false;
            var amt = parseAmount(it);
            if (amt < priceMin || amt > priceMax) return false;
            if (karatVal && String(it.variants || it.carat || '').toLowerCase().indexOf(karatVal) === -1) return false;
            if (!q) return true;
            var hay = [it.barcode, it.product_name, it.article, it.metal_name, it.category, it.design_no].join(' ').toLowerCase();
            return hay.indexOf(q) !== -1;
        });
    }

    function sortItems(items) {
        var list = items.slice();
        list.sort(function (a, b) {
            switch (sortBy) {
                case 'oldest':
                    return (parseInt(a.catalogue_id, 10) || 0) - (parseInt(b.catalogue_id, 10) || 0);
                case 'price_asc':
                    return parseAmount(a) - parseAmount(b);
                case 'price_desc':
                    return parseAmount(b) - parseAmount(a);
                case 'weight_asc':
                    return parseWeight(a) - parseWeight(b);
                case 'weight_desc':
                    return parseWeight(b) - parseWeight(a);
                default:
                    return (parseInt(b.catalogue_id, 10) || 0) - (parseInt(a.catalogue_id, 10) || 0);
            }
        });
        return list;
    }

    function updateStatsBar(filtered) {
        var totalEl = document.getElementById('jcatStatTotal');
        var wtEl = document.getElementById('jcatStatWeight');
        var priceEl = document.getElementById('jcatStatPrice');
        var updEl = document.getElementById('jcatStatUpdated');
        if (!totalEl) return;
        var totWt = 0;
        var minP = Infinity;
        var maxP = 0;
        filtered.forEach(function (it) {
            totWt += parseWeight(it);
            var a = parseAmount(it);
            if (a < minP) minP = a;
            if (a > maxP) maxP = a;
        });
        if (minP === Infinity) minP = 0;
        totalEl.textContent = String(filtered.length);
        wtEl.textContent = totWt.toFixed(3) + ' g';
        priceEl.textContent = formatCurrency(minP) + ' – ' + formatCurrency(maxP);
        if (updEl) {
            updEl.textContent = lastUpdated.toLocaleString('en-IN', {
                day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', hour12: true
            });
        }
    }

    function buildSidebarCategories(metals) {
        if (!$sidebarCategories) return;
        var counts = {};
        allItems.forEach(function (it) {
            var mid = String(parseInt(it.metal_id, 10) || 0);
            counts[mid] = (counts[mid] || 0) + 1;
        });
        var total = allItems.length;
        var html = '<li><label><input type="checkbox" class="jcat-cat-all" checked> All Categories</label><span class="jcat-cat-count">' + total + '</span></li>';
        (metals || []).forEach(function (m) {
            var id = parseInt(m.id, 10) || 0;
            if (id <= 0) return;
            var cnt = counts[String(id)] || 0;
            var checked = sidebarMetalIds[String(id)] !== false ? ' checked' : '';
            html += '<li><label><input type="checkbox" class="jcat-cat-metal" data-metal-id="' + id + '"' + checked + '> '
                + esc(m.name || '') + '</label><span class="jcat-cat-count">' + cnt + '</span></li>';
        });
        $sidebarCategories.innerHTML = html;
        $sidebarCategories.querySelectorAll('.jcat-cat-metal').forEach(function (cb) {
            var mid = cb.getAttribute('data-metal-id');
            sidebarMetalIds[mid] = cb.checked;
            cb.addEventListener('change', function () {
                sidebarMetalIds[mid] = cb.checked;
            });
        });
        var allCb = $sidebarCategories.querySelector('.jcat-cat-all');
        if (allCb) {
            allCb.addEventListener('change', function () {
                var on = allCb.checked;
                $sidebarCategories.querySelectorAll('.jcat-cat-metal').forEach(function (cb) {
                    cb.checked = on;
                    sidebarMetalIds[cb.getAttribute('data-metal-id')] = on;
                });
            });
        }
    }

    function syncPriceRangeFromData() {
        var max = 0;
        allItems.forEach(function (it) {
            var a = parseAmount(it);
            if (a > max) max = a;
        });
        if (max < 1000) max = 100000;
        else max = Math.ceil(max / 1000) * 1000;
        priceMax = max;
        var slider = document.getElementById('jcatPriceMax');
        var maxInput = document.getElementById('jcatPriceMaxVal');
        if (slider) { slider.max = max; slider.value = max; }
        if (maxInput) maxInput.value = max;
    }

    function jcatCatalogueEditHref(it) {
        var cid = parseInt(it.catalogue_id, 10) || 0;
        if (cid > 0) {
            return 'jewelry-catalogue-create.php?id=' + cid + '&return=jewelry-catalogue.php';
        }
        return '';
    }

    function jcatWhatsAppSharePageUrl(it) {
        var cid = parseInt(it.catalogue_id, 10) || 0;
        if (cid <= 0) return '';
        var site = String(JCAT_SITE_URL || '').replace(/\/+$/, '');
        if (site && !/localhost|127\.0\.0\.1/i.test(site)) {
            return site + '/jewelry-catalogue-share.php?id=' + cid;
        }
        return '';
    }

    function jcatWhatsAppPublicImageUrl(it) {
        if (!isUsableThumb(it.thumb_url)) return '';
        var url = String(it.thumb_url).trim();
        if (!/^https?:\/\//i.test(url)) return '';
        if (/localhost|127\.0\.0\.1/i.test(url)) return '';
        return url;
    }

    function jcatWhatsAppShareMessage(it) {
        var labels = itemLabels(it);
        var designNo = trimStr(it.design_no || it.barcode || '');
        var product = trimStr(it.product_name || cardTitle(it));
        var metal = trimStr(it.metal_name || '');
        var imgUrl = jcatWhatsAppPublicImageUrl(it);
        var shareUrl = jcatWhatsAppSharePageUrl(it);
        var lines = [
            '*GoldMatrix — Jewellery Catalogue*',
            '',
            '*Design No:* ' + (designNo || '—'),
            '*Product:* ' + (product || '—'),
            '*Metal:* ' + (metal || '—'),
            '*Weight:* ' + parseWeight(it).toFixed(3) + ' g',
            '*Amount:* ' + labels.price
        ];
        if (it.barcode && String(it.barcode).trim() !== designNo) {
            lines.push('*Barcode:* ' + String(it.barcode).trim());
        }
        if (it.variants) {
            lines.push('*SKU / Variant:* ' + String(it.variants).trim());
        }
        if (it.bill_of_material) {
            lines.push('*Bill of Material:* ' + String(it.bill_of_material).trim());
        }
        lines.push('', '— GoldMatrix');
        var body = lines.join('\n');
        if (imgUrl) {
            return imgUrl + '\n\n' + body;
        }
        if (shareUrl) {
            return shareUrl + '\n\n' + body;
        }
        return body;
    }

    function jcatOpenWhatsAppWeb(it) {
        var url = 'https://web.whatsapp.com/send?text=' + encodeURIComponent(jcatWhatsAppShareMessage(it));
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function jcatShareWhatsApp(it, e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        var msg = jcatWhatsAppShareMessage(it);
        var imgUrl = jcatWhatsAppPublicImageUrl(it);
        if (imgUrl && navigator.share && typeof navigator.canShare === 'function') {
            fetch(imgUrl, { mode: 'cors' })
                .then(function (resp) {
                    if (!resp.ok) throw new Error('image fetch failed');
                    return resp.blob();
                })
                .then(function (blob) {
                    var ext = (blob.type && blob.type.indexOf('png') !== -1) ? 'png' : 'jpg';
                    var file = new File([blob], (trimStr(it.design_no || it.barcode || 'design')) + '.' + ext, {
                        type: blob.type || 'image/jpeg'
                    });
                    var shareData = { text: msg, files: [file] };
                    if (!navigator.canShare(shareData)) throw new Error('cannot share files');
                    return navigator.share(shareData);
                })
                .catch(function () {
                    jcatOpenWhatsAppWeb(it);
                });
            return;
        }
        jcatOpenWhatsAppWeb(it);
    }

    function jcatWhatsAppWebHref(it) {
        return 'https://web.whatsapp.com/send?text=' + encodeURIComponent(jcatWhatsAppShareMessage(it));
    }

    function jcatCatalogItemShareBlock(it, index) {
        var labels = itemLabels(it);
        var designNo = trimStr(it.design_no || it.barcode || '');
        var product = trimStr(it.product_name || cardTitle(it));
        var metal = trimStr(it.metal_name || '');
        var shareUrl = jcatWhatsAppSharePageUrl(it);
        var imgUrl = jcatWhatsAppPublicImageUrl(it);
        var lines = [
            (index > 1 ? '\n' : '') + '*' + index + '. ' + (designNo || '—') + '*',
            'Product: ' + (product || '—'),
            'Metal: ' + (metal || '—'),
            'Weight: ' + parseWeight(it).toFixed(3) + ' g',
            'Amount: ' + labels.price
        ];
        if (it.barcode && String(it.barcode).trim() !== designNo) {
            lines.push('Barcode: ' + String(it.barcode).trim());
        }
        if (shareUrl) {
            lines.push('View: ' + shareUrl);
        } else if (imgUrl) {
            lines.push('Image: ' + imgUrl);
        }
        return lines.join('\n');
    }

    function jcatBulkShareMessage(items) {
        var count = items.length;
        var header = '*GoldMatrix — Jewellery Catalogue*\n\nSelected items (' + count + '):\n';
        var blocks = items.map(function (it, i) {
            return jcatCatalogItemShareBlock(it, i + 1);
        });
        return header + blocks.join('\n') + '\n\n— GoldMatrix';
    }

    function jcatTruncateShareText(text, maxLen, suffix) {
        if (!text || text.length <= maxLen) return text;
        return text.substring(0, maxLen) + (suffix || '\n\n... (message truncated)');
    }

    function requireSelectedCatalogItems() {
        var picked = getSelectedCatalogItems();
        if (!picked.length) {
            alert('Please select at least one catalogue item using the checkboxes.');
            return null;
        }
        return picked;
    }

    function jcatSendSelectedToWhatsAppWeb() {
        var picked = requireSelectedCatalogItems();
        if (!picked) return;
        var msg = jcatBulkShareMessage(picked);
        if (msg.length > 6000) {
            msg = jcatTruncateShareText(msg, 5800, '\n\n... (message truncated — select fewer items for full details)');
        }
        var url = 'https://web.whatsapp.com/send?text=' + encodeURIComponent(msg);
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function jcatSendSelectedToMail() {
        var picked = requireSelectedCatalogItems();
        if (!picked) return;
        var subject = 'GoldMatrix — Jewellery Catalogue (' + picked.length + ' item' + (picked.length === 1 ? '' : 's') + ')';
        var body = jcatBulkShareMessage(picked).replace(/\*/g, '');
        if (body.length > 1800) {
            body = jcatTruncateShareText(body, 1750, '\n\n... (message truncated — select fewer items for full details)');
        }
        window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    }

    function renderListRowCard(it) {
        var a = cardAttrs(it);
        var labels = itemLabels(it);
        var thumb = isUsableThumb(it.thumb_url) ? String(it.thumb_url).trim() : '';
        var metalHtml = it.metal_name ? '<p class="jcat-card-metal">' + esc(it.metal_name) + '</p>' : '';
        var detailsBtn = a.cid > 0
            ? '<button type="button" class="jcat-card-details-btn" data-catalogue-id="' + a.cid + '" data-barcode="' + esc(a.bc) + '">View Details</button>'
            : '';
        var whatsappBtn = '<button type="button" class="jcat-card-whatsapp-btn" data-catalogue-id="' + a.cid + '" data-barcode="' + esc(a.bc) + '" title="Share on WhatsApp" aria-label="Share design on WhatsApp"><i class="fab fa-whatsapp"></i></button>';
        var actionsHtml = '<div class="jcat-card-row-actions">' + whatsappBtn + detailsBtn + '</div>';
        return '<article class="jcat-card jcat-card--row' + a.cardCls + '" data-barcode="' + esc(a.bc) + '" data-catalogue-id="' + a.cid + '"' + a.editAttr + '>'
            + '<div class="jcat-card-row-inner">'
            + '<div class="jcat-card-img">' + jcatImgHtml(thumb, 'jcat-thumb') + '</div>'
            + '<div class="jcat-card-row-body">'
            + '<label class="jcat-card-select jcat-card-row-check"><input type="checkbox" class="jcat-row-check" data-barcode="' + esc(a.bc) + '"' + a.checked + '></label>'
            + '<span class="jcat-badge">' + stockBadge(it) + '</span>'
            + '<h2 class="jcat-card-title">' + esc(cardTitle(it)) + '</h2>'
            + metalHtml
            + '<p class="jcat-card-price">' + esc(labels.price) + '</p>'
            + '<div class="jcat-card-row-footer">'
            + '<p class="jcat-card-weight"><i class="feather icon-layers jcat-wt-icon"></i><span>' + esc(parseWeight(it).toFixed(3)) + '</span></p>'
            + actionsHtml
            + '</div>'
            + '</div></div></article>';
    }

    function renderStaticPromoBanner() {
        return '<aside class="jcat-promo-banner" aria-hidden="true">'
            + '<img class="jcat-promo-banner-img" src="' + esc(JCAT_PROMO_BANNER_IMG) + '" alt="Premium Jewellery Catalogue" loading="lazy">'
            + '</aside>';
    }

    function renderShowcaseLayout(slice, filtered, start) {
        if (!$catalogueView) return;
        $catalogueView.classList.remove('d-none');
        if ($grid) $grid.classList.add('d-none');
        if ($listWrap) $listWrap.classList.add('d-none');
        updateStatsBar(filtered);

        if (!slice.length) {
            $catalogueView.innerHTML = '<div class="jcat-empty">No catalogue items match your filters.</div>';
            updateSummary(filtered.length, start, 0);
            return;
        }

        var html = '<section class="jcat-catalogue-section">'
            + '<div class="jcat-lower-layout">'
            + renderStaticPromoBanner()
            + '<div class="jcat-lower-main">'
            + '<div class="jcat-section-head">'
            + '<h2><i class="feather icon-layers"></i> Catalogue Items</h2>'
            + '<span class="jcat-view-all">' + slice.length + ' shown</span>'
            + '</div>'
            + '<div class="jcat-list-cards">';

        slice.forEach(function (it) {
            html += renderListRowCard(it);
        });

        html += '</div></div></div></section>';

        $catalogueView.innerHTML = html;
        bindRowChecks($catalogueView);
        bindCatalogueEditClicks($catalogueView);
        jcatFeatherRefresh($catalogueView);
        updateSummary(filtered.length, start, slice.length);
    }

    function getSlice(filtered) {
        var total = filtered.length;
        var pages = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;
        var start = (page - 1) * perPage;
        return { slice: filtered.slice(start, start + perPage), total: total, start: start };
    }

    function updateSummary(total, start, sliceLen) {
        if (!$summary) return;
        var from = total ? start + 1 : 0;
        var to = Math.min(start + sliceLen, total);
        $summary.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' entries';
    }

    var $detailsOverlay = document.getElementById('jcatDetailsOverlay');
    var $detailsBody = document.getElementById('jcatDetailsBody');
    var $detailsFoot = document.getElementById('jcatDetailsFoot');
    var $detailsTitle = document.getElementById('jcatDetailsTitle');

    function findCatalogItem(catalogueId, barcode) {
        for (var i = 0; i < allItems.length; i++) {
            var it = allItems[i];
            if (catalogueId > 0 && (parseInt(it.catalogue_id, 10) || 0) === catalogueId) return it;
            if (barcode && it.barcode === barcode) return it;
        }
        return null;
    }

    function jcatDetailField(label, value) {
        if (value == null || String(value).trim() === '') return '';
        return '<div class="jcat-detail-field">'
            + '<span class="jcat-detail-label">' + esc(label) + '</span>'
            + '<span class="jcat-detail-value">' + esc(String(value).trim()) + '</span>'
            + '</div>';
    }

    function jcatFmtWeightRow(n) {
        var v = parseFloat(n);
        if (isNaN(v)) return '0.000';
        return v.toFixed(3);
    }

    function renderJcatDetailsBomTable(rows) {
        if (!rows || !rows.length) return '';
        var html = '<div class="jcat-details-section">'
            + '<h3 class="jcat-details-section-title"><i class="feather icon-list"></i> Bill of Material</h3>'
            + '<div class="jcat-details-bom-wrap"><table class="jcat-details-bom-table">'
            + '<thead><tr>'
            + '<th>Product</th><th>Design No</th><th class="text-right">Qty</th>'
            + '<th class="text-right">Gross Wt</th><th class="text-right">Net Wt</th><th class="text-right">Making</th>'
            + '</tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr>'
                + '<td>' + esc(row.product_name || '—') + '</td>'
                + '<td>' + esc(row.design_no || '—') + '</td>'
                + '<td class="text-right">' + esc(String(row.quantity != null ? row.quantity : '—')) + '</td>'
                + '<td class="text-right">' + esc(jcatFmtWeightRow(row.gross_wt)) + '</td>'
                + '<td class="text-right">' + esc(jcatFmtWeightRow(row.net_wt)) + '</td>'
                + '<td class="text-right">' + esc(row.making_amount != null ? Number(row.making_amount).toFixed(2) : '0.00') + '</td>'
                + '</tr>';
        });
        html += '</tbody></table></div></div>';
        return html;
    }

    function renderJcatDetailsContent(item, data) {
        var d = (data && data.details) ? data.details : {};
        var bomRows = (data && data.modal_rows) ? data.modal_rows : [];
        var designNo = d.design_no || (item && item.design_no) || (data && data.design_no) || '';
        var productName = d.product_name || (item && item.product_name) || '';
        var title = d.title || productName || (data && data.title) || 'Catalogue Item';
        var metalName = d.metal_name || (item && item.metal_name) || '';
        var categoryName = d.category_name || (item && item.category) || '';
        var barcode = d.barcode || (item && item.barcode) || '';
        var sku = d.sku || (item && item.variants) || '';
        var weightLabel = d.weight_label || (item && item.weight_label) || jcatFmtWeightRow(d.weight || (item && parseWeight(item)));
        var amountLabel = d.amount_label || (item && item.amount_label) || formatCurrency(parseAmount(item || {}));
        var bomLabel = d.bom_count != null
            ? String(d.bom_count)
            : ((item && item.bill_of_material) ? item.bill_of_material : (bomRows.length ? String(bomRows.length) : '0'));

        var imgs = Array.isArray(d.image_urls) ? d.image_urls.slice() : [];
        if (!imgs.length && item && isUsableThumb(item.thumb_url)) {
            imgs.push(String(item.thumb_url).trim());
        }

        var galleryHtml = '<div class="jcat-details-gallery">';
        if (imgs.length) {
            imgs.forEach(function (url) {
                galleryHtml += '<div class="jcat-details-gallery-item">'
                    + '<img src="' + esc(url) + '" alt="" loading="lazy" onerror="window.jcatImgError(this)">'
                    + '</div>';
            });
        } else {
            galleryHtml += '<div class="jcat-details-gallery-empty">' + jcatNoImagePlaceholder() + '</div>';
        }
        galleryHtml += '</div>';

        var fields = '';
        fields += jcatDetailField('Design No', designNo);
        fields += jcatDetailField('Product Name', productName);
        if (d.title && d.title !== productName) fields += jcatDetailField('Title', d.title);
        fields += jcatDetailField('Metal', metalName);
        fields += jcatDetailField('Category', categoryName);
        fields += jcatDetailField('Barcode', barcode);
        fields += jcatDetailField('SKU / Variant', sku);
        fields += jcatDetailField('Weight', weightLabel);
        fields += jcatDetailField('Amount', amountLabel.indexOf('₹') === -1 ? formatCurrency(parseFloat(String(amountLabel).replace(/[^\d.-]/g, '')) || 0) : amountLabel);
        fields += jcatDetailField('Status', d.active || (item && item.active) || 'Active');
        fields += jcatDetailField('Jewellery Catalogue', d.jewelry_catalogue || (item && item.jewelry_catalogue) || 'Yes');
        fields += jcatDetailField('Bill of Material', bomLabel + (Number(bomLabel) === 1 ? ' item' : ' items'));

        var descHtml = '';
        var shortDesc = d.short_desc || '';
        var fullDesc = d.full_desc || '';
        if (shortDesc) {
            descHtml += '<div class="jcat-details-section"><h3 class="jcat-details-section-title">Short Description</h3><p class="jcat-details-text">' + esc(shortDesc) + '</p></div>';
        }
        if (fullDesc) {
            descHtml += '<div class="jcat-details-section"><h3 class="jcat-details-section-title">Full Description</h3><p class="jcat-details-text jcat-details-text--full">' + esc(fullDesc) + '</p></div>';
        }

        return '<div class="jcat-details-layout">'
            + '<div class="jcat-details-media">' + galleryHtml + '</div>'
            + '<div class="jcat-details-grid">' + fields + '</div>'
            + '</div>'
            + descHtml
            + renderJcatDetailsBomTable(bomRows);
    }

    function renderJcatDetailsFooter(item, data) {
        var d = (data && data.details) ? data.details : {};
        var cid = parseInt(d.catalogue_id, 10) || (item ? (parseInt(item.catalogue_id, 10) || 0) : 0);
        var editHref = cid > 0 ? ('jewelry-catalogue-create.php?id=' + cid + '&return=jewelry-catalogue.php') : '';
        var html = '<button type="button" class="btn btn-sm btn-outline-secondary" id="jcatDetailsCloseBtn">Close</button>';
        if (editHref) {
            html += '<a href="' + esc(editHref) + '" class="btn btn-sm btn-primary jcat-details-edit-btn">Edit Product</a>';
        }
        return html;
    }

    function closeJcatDetailsModal() {
        if (!$detailsOverlay) return;
        $detailsOverlay.classList.remove('show');
        $detailsOverlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('jcat-details-open');
    }

    function openJcatDetailsModal(catalogueId, barcode) {
        if (!$detailsOverlay || !$detailsBody) return;
        catalogueId = parseInt(catalogueId, 10) || 0;
        var item = findCatalogItem(catalogueId, barcode);
        if (!catalogueId && item) catalogueId = parseInt(item.catalogue_id, 10) || 0;
        if (!catalogueId) {
            alert('Product details are not available for this item.');
            return;
        }

        $detailsOverlay.classList.add('show');
        $detailsOverlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('jcat-details-open');
        if ($detailsTitle) {
            $detailsTitle.textContent = item ? cardTitle(item) : 'Catalogue Item';
        }
        $detailsBody.innerHTML = '<div class="jcat-details-loading"><span class="jcat-details-spinner"></span> Loading product details…</div>';
        if ($detailsFoot) $detailsFoot.innerHTML = '';

        fetch('ajax/get-jewelry-catalogue-for-modal.php?catalogue_id=' + encodeURIComponent(String(catalogueId)), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error((data && data.message) || 'Could not load product details.');
                }
                if ($detailsTitle) {
                    var dn = (data.details && data.details.design_no) || data.design_no || '';
                    var pn = (data.details && data.details.product_name) || (data.title || '');
                    $detailsTitle.textContent = pn || (dn ? ('Design ' + dn) : 'Catalogue Item');
                }
                $detailsBody.innerHTML = renderJcatDetailsContent(item, data);
                if ($detailsFoot) $detailsFoot.innerHTML = renderJcatDetailsFooter(item, data);
                jcatFeatherRefresh($detailsOverlay);
            })
            .catch(function (err) {
                $detailsBody.innerHTML = '<div class="jcat-details-error">' + esc(err && err.message ? err.message : 'Could not load product details.') + '</div>';
                if ($detailsFoot) {
                    $detailsFoot.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary" id="jcatDetailsCloseBtn">Close</button>';
                }
            });
    }

    function bindJcatDetailsModal() {
        if ($catalogueView && !$catalogueView._jcatDetailsBound) {
            $catalogueView._jcatDetailsBound = true;
            $catalogueView.addEventListener('click', function (e) {
                var waBtn = e.target.closest('.jcat-card-whatsapp-btn');
                if (waBtn) {
                    var waCid = parseInt(waBtn.getAttribute('data-catalogue-id'), 10) || 0;
                    var waBc = waBtn.getAttribute('data-barcode') || '';
                    var waItem = findCatalogItem(waCid, waBc);
                    if (waItem) jcatShareWhatsApp(waItem, e);
                    return;
                }
                var btn = e.target.closest('.jcat-card-details-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                openJcatDetailsModal(btn.getAttribute('data-catalogue-id'), btn.getAttribute('data-barcode'));
            });
        }
        if ($detailsOverlay && !$detailsOverlay._jcatDetailsBound) {
            $detailsOverlay._jcatDetailsBound = true;
            $detailsOverlay.addEventListener('click', function (e) {
                if (e.target === $detailsOverlay) closeJcatDetailsModal();
            });
        }
        document.addEventListener('click', function (e) {
            if (e.target.id === 'jcatDetailsClose' || e.target.id === 'jcatDetailsCloseBtn') {
                closeJcatDetailsModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && $detailsOverlay && $detailsOverlay.classList.contains('show')) {
                closeJcatDetailsModal();
            }
        });
    }

    function bindCatalogueEditClicks(root) {
        if (!root) return;
        root.querySelectorAll('.jcat-card-editable, .jcat-row-editable').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target.closest('input, a, button, label')) return;
                var href = el.getAttribute('data-edit-href');
                if (href) window.location.href = href;
            });
        });
    }

    function renderGrid(slice, filtered, start) {
        renderShowcaseLayout(slice, filtered, start);
    }

    function renderList(slice, filtered, start) {
        if (!$tableBody || !$listWrap) return;
        $listWrap.classList.remove('d-none');
        if ($catalogueView) $catalogueView.classList.add('d-none');
        if ($grid) $grid.classList.add('d-none');
        updateStatsBar(filtered);
        var totWt = 0;
        var totAmt = 0;
        filtered.forEach(function (it) {
            totWt += parseFloat(it.current_weight) || 0;
            totAmt += parseFloat(it.amount) || 0;
        });
        document.getElementById('jcatFootWt').textContent = totWt.toFixed(3);
        document.getElementById('jcatFootAmt').textContent = totAmt.toFixed(2);

        if (!slice.length) {
            $tableBody.innerHTML = '<tr><td colspan="10" class="text-center py-4">No catalogue items match your filters.</td></tr>';
            updateSummary(filtered.length, start, 0);
            return;
        }
        var html = '';
        slice.forEach(function (it) {
            var bc = it.barcode || '';
            var checked = selectedBarcodes[bc] ? ' checked' : '';
            var thumb = isUsableThumb(it.thumb_url) ? String(it.thumb_url).trim() : '';
            var activeCls = it.active === 'Active' ? 'jcat-active-pill' : '';
            var editHref = jcatCatalogueEditHref(it);
            var rowCls = editHref ? ' jcat-row-editable' : '';
            var editAttr = editHref ? ' data-edit-href="' + esc(editHref) + '"' : '';
            html += '<tr class="' + rowCls.trim() + '" data-barcode="' + esc(bc) + '" data-catalogue-id="' + (parseInt(it.catalogue_id, 10) || 0) + '"' + editAttr + '>'
                + '<td data-col="_cb"><input type="checkbox" class="jcat-row-check" data-barcode="' + esc(bc) + '"' + checked + '></td>'
                + '<td data-col="imageUrls">' + jcatImgHtml(thumb, 'jcat-list-thumb', { forceImg: true }) + '</td>'
                + '<td data-col="active"><span class="' + activeCls + '">' + esc(it.active || '') + '</span></td>'
                + '<td data-col="jewelryCatalogue">' + esc(it.jewelry_catalogue || 'Yes') + '</td>'
                + '<td data-col="productName">' + esc(it.product_name || '') + '</td>'
                + '<td data-col="designNo" class="jcat-design-no">' + esc(it.design_no || '') + '</td>'
                + '<td data-col="variants">' + esc(it.variants || '') + '</td>'
                + '<td data-col="billOfMaterial">' + esc(it.bill_of_material || '') + '</td>'
                + '<td data-col="weight" class="text-right">' + esc(it.weight_label || '') + '</td>'
                + '<td data-col="amount" class="text-right">' + esc(it.amount_label || '0.00') + '</td>'
                + '</tr>';
        });
        $tableBody.innerHTML = html;
        jcatSyncColumnLayout();
        bindRowChecks($listWrap);
        bindCatalogueEditClicks($listWrap);
        updateSummary(filtered.length, start, slice.length);
    }

    var JCAT_SO_STORAGE_KEY = 'auragold_jcat_sale_order_items';
    var JCAT_SQ_STORAGE_KEY = 'auragold_jcat_sale_quotation_items';

    function getSelectedCatalogItems() {
        var out = [];
        Object.keys(selectedBarcodes).forEach(function (bc) {
            for (var i = 0; i < allItems.length; i++) {
                if (allItems[i].barcode === bc) {
                    out.push(allItems[i]);
                    break;
                }
            }
        });
        return out;
    }

    function buildCatalogSelectionPayload(picked) {
        return picked.map(function (it) {
            return {
                barcode: it.barcode || '',
                metal_id: it.metal_id || 0,
                current_qty: parseFloat(it.current_qty) || 0,
                current_weight: parseFloat(it.current_weight) || 0,
                product_name: it.product_name || '',
                design_no: it.design_no || '',
                catalogue_id: parseInt(it.catalogue_id, 10) || 0,
                is_catalogue_only: !!it.is_catalogue_only
            };
        });
    }

    function saveCatalogSelectionAndNavigate(storageKey, url, canAccess) {
        if (!canAccess) return;
        var picked = getSelectedCatalogItems();
        if (!picked.length) {
            alert('Please select at least one catalogue item using the checkboxes.');
            return;
        }
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(buildCatalogSelectionPayload(picked)));
        } catch (e) {
            alert('Could not save selection. Try selecting fewer items.');
            return;
        }
        window.location.href = url;
    }

    function goToSaleOrderWithSelection() {
        saveCatalogSelectionAndNavigate(
            JCAT_SO_STORAGE_KEY,
            'sale-order.php?from_jewelry_catalog=1',
            <?php echo $jcat_can_sale_order ? 'true' : 'false'; ?>
        );
    }

    function goToSaleQuotationWithSelection() {
        saveCatalogSelectionAndNavigate(
            JCAT_SQ_STORAGE_KEY,
            'sale-quotations.php?from_jewelry_catalog=1',
            <?php echo $jcat_can_sale_quot ? 'true' : 'false'; ?>
        );
    }

    function syncDeleteCatalogueBtn() {
        var n = Object.keys(selectedBarcodes).length;
        var delBtn = document.getElementById('jcatDeleteSelected');
        if (delBtn) {
            delBtn.disabled = n === 0;
            delBtn.classList.toggle('text-muted', n === 0);
            delBtn.classList.toggle('text-danger', n > 0);
        }
        ['jcatSendWhatsApp', 'jcatSendMail'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (!btn) return;
            btn.disabled = n === 0;
            btn.classList.toggle('text-muted', n === 0);
        });
    }

    function bindRowChecks(root) {
        if (!root) return;
        root.querySelectorAll('.jcat-row-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var bc = cb.getAttribute('data-barcode') || '';
                if (cb.checked) selectedBarcodes[bc] = true;
                else delete selectedBarcodes[bc];
                syncDeleteCatalogueBtn();
            });
        });
    }

    function initCreateDropdown() {
        var dd = document.getElementById('jcatCreateDropdown');
        var btn = document.getElementById('jcatCreateBtn');
        var menu = document.getElementById('jcatCreateMenu');
        if (!dd || !btn || !menu) return;

        function closeMenu() {
            dd.classList.remove('show');
            menu.classList.remove('show');
            btn.setAttribute('aria-expanded', 'false');
        }
        function openMenu() {
            dd.classList.add('show');
            menu.classList.add('show');
            btn.setAttribute('aria-expanded', 'true');
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (menu.classList.contains('show')) {
                closeMenu();
            } else {
                openMenu();
            }
        });
        document.addEventListener('click', function (e) {
            if (!dd.contains(e.target)) {
                closeMenu();
            }
        });
        menu.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    function renderView() {
        var filtered = sortItems(getFiltered());
        var p = getSlice(filtered);
        if (viewMode === 'list') renderList(p.slice, filtered, p.start);
        else renderGrid(p.slice, filtered, p.start);
    }

    function setView(mode) {
        viewMode = mode === 'list' ? 'list' : 'grid';
        localStorage.setItem('jcat_view', viewMode);
        if ($btnGrid) {
            $btnGrid.classList.toggle('active', viewMode === 'grid');
            $btnGrid.setAttribute('aria-pressed', viewMode === 'grid' ? 'true' : 'false');
        }
        if ($btnList) {
            $btnList.classList.toggle('active', viewMode === 'list');
            $btnList.setAttribute('aria-pressed', viewMode === 'list' ? 'true' : 'false');
        }
        renderView();
    }

    function buildMetalTabs(metals) {
        if (!$tabs) return;
        allMetals = metals || [];
        var html = '<button type="button" class="jcat-tab' + (metalId === 0 ? ' active' : '') + '" data-metal-id="0">'
            + '<i class="feather icon-grid"></i> All</button>';
        allMetals.forEach(function (m) {
            var id = parseInt(m.id, 10) || 0;
            if (id <= 0) return;
            var icon = metalIcon(m.name);
            html += '<button type="button" class="jcat-tab' + (metalId === id ? ' active' : '') + '" data-metal-id="' + id + '">'
                + '<i class="feather ' + icon + '"></i> ' + esc(m.name || '') + '</button>';
        });
        $tabs.innerHTML = html;
        $tabs.querySelectorAll('button[data-metal-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                metalId = parseInt(btn.getAttribute('data-metal-id'), 10) || 0;
                page = 1;
                $tabs.querySelectorAll('.jcat-tab').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                var sidebarMetal = document.getElementById('jcatSidebarMetal');
                if (sidebarMetal) sidebarMetal.value = String(metalId);
                renderView();
            });
        });
        buildSidebarCategories(allMetals);
        jcatFeatherRefresh($tabs);
    }

    function countActiveFilters() {
        if (!$filterForm) return 0;
        var n = 0;
        $filterForm.querySelectorAll('input, select').forEach(function (el) {
            if (el.id === 'jcatFKarat') {
                if (el.value && el.value.trim()) n++;
                return;
            }
            if (el.name && el.value && String(el.value).trim() !== '') n++;
        });
        return n;
    }

    function updateFilterBadge() {
        var n = countActiveFilters();
        if ($filterBadge) {
            $filterBadge.textContent = String(n);
            $filterBadge.classList.toggle('show', n > 0);
        }
    }

    function buildApiUrl() {
        var url = API + '?limit=5000';
        if (!$filterForm) return url;
        var fd = new FormData($filterForm);
        fd.forEach(function (val, key) {
            if (val && String(val).trim() !== '') {
                url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(String(val).trim());
            }
        });
        var sq = $search && $search.value ? $search.value.trim() : '';
        if (sq) url += '&q=' + encodeURIComponent(sq);
        if (metalId > 0) url += '&metal_id=' + metalId;
        return url;
    }

    function loadCatalog() {
        if ($loading && $catalogueView) {
            $catalogueView.classList.remove('d-none');
            $catalogueView.innerHTML = '';
            $catalogueView.appendChild($loading);
            $loading.style.display = '';
        }
        fetch(buildApiUrl(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if ($loading) $loading.style.display = 'none';
                if (!data || !data.success) {
                    var msg = esc((data && data.message) || 'Could not load catalogue.');
                    if ($catalogueView) $catalogueView.innerHTML = '<div class="jcat-empty">' + msg + '</div>';
                    return;
                }
                allItems = sortCatalogueItemsFirst(data.items || []);
                lastUpdated = new Date();
                syncPriceRangeFromData();
                buildMetalTabs(data.metals || []);
                page = 1;
                var hi = highlightCatalogueId();
                if (hi > 0) {
                    metalId = 0;
                    if ($tabs) {
                        $tabs.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
                        var allBtn = $tabs.querySelector('button[data-metal-id="0"]');
                        if (allBtn) allBtn.classList.add('active');
                    }
                }
                renderView();
                if (hi > 0) {
                    var target = document.querySelector('[data-catalogue-id="' + hi + '"]');
                    if (target && typeof target.scrollIntoView === 'function') {
                        target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        target.classList.add('jcat-card-highlight');
                        setTimeout(function () { target.classList.remove('jcat-card-highlight'); }, 2500);
                    }
                }
            })
            .catch(function () {
                if ($loading) $loading.style.display = 'none';
                if ($catalogueView) $catalogueView.innerHTML = '<div class="jcat-empty">Network error while loading catalogue.</div>';
            });
    }

    function openFilter() {
        if ($filterOverlay) {
            $filterOverlay.classList.add('show');
            $filterOverlay.setAttribute('aria-hidden', 'false');
        }
    }
    function closeFilter() {
        if ($filterOverlay) {
            $filterOverlay.classList.remove('show');
            $filterOverlay.setAttribute('aria-hidden', 'true');
        }
    }

    document.getElementById('jcatBtnFilter').addEventListener('click', openSidebarDrawer);
    var jcatOpenAdvanceFilter = document.getElementById('jcatOpenAdvanceFilter');
    if (jcatOpenAdvanceFilter) {
        jcatOpenAdvanceFilter.addEventListener('click', function () {
            closeSidebarDrawer();
            openFilter();
        });
    }
    var jcatSidebarClose = document.getElementById('jcatSidebarClose');
    if (jcatSidebarClose) {
        jcatSidebarClose.addEventListener('click', closeSidebarDrawer);
    }
    document.getElementById('jcatFilterClose').addEventListener('click', closeFilter);
    $filterOverlay.addEventListener('click', function (e) {
        if (e.target === $filterOverlay) closeFilter();
    });
    document.getElementById('jcatFilterApply').addEventListener('click', function () {
        var mf = document.getElementById('jcatFMetal');
        if (mf && mf.value) metalId = parseInt(mf.value, 10) || 0;
        updateFilterBadge();
        page = 1;
        closeFilter();
        loadCatalog();
    });
    document.getElementById('jcatFilterClear').addEventListener('click', function () {
        if ($filterForm) $filterForm.reset();
        metalId = 0;
        document.getElementById('jcatFKarat').value = '';
        updateFilterBadge();
        page = 1;
        closeFilter();
        loadCatalog();
    });

    document.getElementById('jcatUpdateRecords').addEventListener('click', loadCatalog);
    document.getElementById('jcatSync').addEventListener('click', function () { loadCatalog(); });
    window.jcatReloadCatalog = loadCatalog;

    var jcatSidebarApply = document.getElementById('jcatSidebarApply');
    if (jcatSidebarApply) {
        jcatSidebarApply.addEventListener('click', function () {
            var minEl = document.getElementById('jcatPriceMin');
            var maxEl = document.getElementById('jcatPriceMaxVal');
            var sortEl = document.getElementById('jcatSortBy');
            var metalEl = document.getElementById('jcatSidebarMetal');
            priceMin = minEl ? (parseFloat(minEl.value) || 0) : 0;
            priceMax = maxEl ? (parseFloat(maxEl.value) || 100000) : 100000;
            sortBy = sortEl ? sortEl.value : 'newest';
            if (metalEl) {
                metalId = parseInt(metalEl.value, 10) || 0;
                if ($tabs) {
                    $tabs.querySelectorAll('.jcat-tab').forEach(function (b) {
                        b.classList.toggle('active', parseInt(b.getAttribute('data-metal-id'), 10) === metalId);
                    });
                }
            }
            page = 1;
            renderView();
            closeSidebarDrawer();
        });
    }
    var jcatSidebarReset = document.getElementById('jcatSidebarReset');
    if (jcatSidebarReset) {
        jcatSidebarReset.addEventListener('click', function () {
            sidebarMetalIds = {};
            priceMin = 0;
            sortBy = 'newest';
            metalId = 0;
            var minEl = document.getElementById('jcatPriceMin');
            var maxEl = document.getElementById('jcatPriceMaxVal');
            var slider = document.getElementById('jcatPriceMax');
            var sortEl = document.getElementById('jcatSortBy');
            var metalEl = document.getElementById('jcatSidebarMetal');
            if (minEl) minEl.value = '0';
            syncPriceRangeFromData();
            if (sortEl) sortEl.value = 'newest';
            if (metalEl) metalEl.value = '0';
            if ($sidebarCategories) {
                $sidebarCategories.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                    cb.checked = true;
                    if (cb.classList.contains('jcat-cat-metal')) {
                        sidebarMetalIds[cb.getAttribute('data-metal-id')] = true;
                    }
                });
            }
            if ($tabs) {
                $tabs.querySelectorAll('.jcat-tab').forEach(function (b) {
                    b.classList.toggle('active', parseInt(b.getAttribute('data-metal-id'), 10) === 0);
                });
            }
            page = 1;
            renderView();
        });
    }
    var jcatPriceSlider = document.getElementById('jcatPriceMax');
    var jcatPriceMaxVal = document.getElementById('jcatPriceMaxVal');
    if (jcatPriceSlider && jcatPriceMaxVal) {
        jcatPriceSlider.addEventListener('input', function () {
            jcatPriceMaxVal.value = jcatPriceSlider.value;
        });
        jcatPriceMaxVal.addEventListener('change', function () {
            jcatPriceSlider.value = jcatPriceMaxVal.value;
        });
    }
    var jcatSidebarBackdrop = document.getElementById('jcatSidebarBackdrop');
    var jcatSidebarEl = document.getElementById('jcatSidebar');
    function closeSidebarDrawer() {
        if (jcatSidebarEl) jcatSidebarEl.classList.remove('is-open');
        if (jcatSidebarBackdrop) jcatSidebarBackdrop.classList.remove('is-visible');
        document.body.classList.remove('jcat-sidebar-open');
    }
    function openSidebarDrawer() {
        if (jcatSidebarEl) jcatSidebarEl.classList.add('is-open');
        if (jcatSidebarBackdrop) jcatSidebarBackdrop.classList.add('is-visible');
        document.body.classList.add('jcat-sidebar-open');
    }
    if (jcatSidebarBackdrop) {
        jcatSidebarBackdrop.addEventListener('click', closeSidebarDrawer);
    }
    document.getElementById('jcatDeleteSelected').addEventListener('click', function () {
        var n = Object.keys(selectedBarcodes).length;
        if (!n) { alert('Select items using the checkboxes first.'); return; }
        alert('Delete Catalogue: ' + n + ' item(s) selected. This action is not linked to stock delete yet.');
    });

    if ($search) {
        $search.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                page = 1;
                loadCatalog();
            }, 350);
        });
    }
    if ($perPage) {
        $perPage.addEventListener('change', function () {
            perPage = parseInt($perPage.value, 10) || 25;
            page = 1;
            renderView();
        });
    }
    document.getElementById('jcatCheckAll').addEventListener('change', function (e) {
        var on = e.target.checked;
        var filtered = getFiltered();
        var p = getSlice(filtered);
        p.slice.forEach(function (it) {
            if (on) selectedBarcodes[it.barcode] = true;
            else delete selectedBarcodes[it.barcode];
        });
        renderView();
        syncDeleteCatalogueBtn();
    });

    $btnGrid.addEventListener('click', function () { setView('grid'); });
    $btnList.addEventListener('click', function () { setView('list'); });

    var JCAT_DEFAULT_ORDER = ['_cb', 'imageUrls', 'active', 'jewelryCatalogue', 'productName', 'designNo', 'variants', 'billOfMaterial', 'weight', 'amount'];
    var JCAT_STORAGE_ORDER = 'jcat_list_col_order';
    var JCAT_STORAGE_WIDTHS = 'jcat_list_col_widths';
    var $jcatTable = document.getElementById('jcatTable');
    var $jcatHeaderRow = document.getElementById('jcatHeaderRow');
    var $jcatFooterRow = document.getElementById('jcatFooterRow');
    var jcatResizing = null;

    function jcatLoadJson(key, fallback) {
        try {
            var r = localStorage.getItem(key);
            return r ? JSON.parse(r) : fallback;
        } catch (e) { return fallback; }
    }
    function jcatSaveJson(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    }
    function jcatApplyColumnOrder(order) {
        if (!Array.isArray(order) || !order.length || !$jcatHeaderRow || !$tableBody) return;
        var ths = Array.prototype.slice.call($jcatHeaderRow.querySelectorAll('th[data-col]'));
        var map = {};
        ths.forEach(function (th) { map[th.getAttribute('data-col')] = th; });
        order.forEach(function (key) {
            var th = map[key];
            if (th) $jcatHeaderRow.appendChild(th);
        });
        $tableBody.querySelectorAll('tr').forEach(function (tr) {
            if (tr.querySelector('td[colspan]')) return;
            var tds = {};
            tr.querySelectorAll('td[data-col]').forEach(function (td) {
                tds[td.getAttribute('data-col')] = td;
            });
            order.forEach(function (key) {
                var td = tds[key];
                if (td) tr.appendChild(td);
            });
        });
        if ($jcatFooterRow) {
            var ftds = {};
            $jcatFooterRow.querySelectorAll('td[data-col]').forEach(function (td) {
                ftds[td.getAttribute('data-col')] = td;
            });
            order.forEach(function (key) {
                var td = ftds[key];
                if (td) $jcatFooterRow.appendChild(td);
            });
        }
    }
    function jcatApplyWidths(widths) {
        if (!widths || typeof widths !== 'object' || !$jcatHeaderRow) return;
        $jcatHeaderRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (widths[k] != null && widths[k] > 20) {
                th.style.width = widths[k] + 'px';
                var col = th.cellIndex;
                if ($tableBody) {
                    $tableBody.querySelectorAll('tr').forEach(function (tr) {
                        var c = tr.children[col];
                        if (c) c.style.width = widths[k] + 'px';
                    });
                }
                if ($jcatFooterRow) {
                    var fc = $jcatFooterRow.children[col];
                    if (fc) fc.style.width = widths[k] + 'px';
                }
            }
        });
    }
    function jcatSyncColumnLayout() {
        if (!$jcatHeaderRow) return;
        var order = jcatLoadJson(JCAT_STORAGE_ORDER, JCAT_DEFAULT_ORDER);
        if (order && order.length) jcatApplyColumnOrder(order);
        jcatApplyWidths(jcatLoadJson(JCAT_STORAGE_WIDTHS, {}));
    }
    function initJcatTableColumns() {
        if (!$jcatTable || !$jcatHeaderRow) return;
        jcatSyncColumnLayout();
        if (typeof Sortable !== 'undefined') {
            Sortable.create($jcatHeaderRow, {
                animation: 150,
                handle: '.jcat-th-inner',
                draggable: 'th:not(.jcat-col-lock)',
                filter: '.jcat-resize-handle',
                preventOnFilter: false,
                onEnd: function () {
                    var keys = Array.prototype.map.call($jcatHeaderRow.querySelectorAll('th[data-col]'), function (th) {
                        return th.getAttribute('data-col');
                    });
                    jcatSaveJson(JCAT_STORAGE_ORDER, keys);
                    jcatApplyColumnOrder(keys);
                }
            });
        }
        $jcatHeaderRow.querySelectorAll('.jcat-resize-handle').forEach(function (handle) {
            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                var th = handle.closest('th');
                if (!th) return;
                jcatResizing = { th: th, startX: e.pageX, startW: th.offsetWidth };
            });
        });
        document.addEventListener('mousemove', function (e) {
            if (!jcatResizing || !$tableBody) return;
            var dx = e.pageX - jcatResizing.startX;
            var nw = Math.max(48, jcatResizing.startW + dx);
            jcatResizing.th.style.width = nw + 'px';
            var col = jcatResizing.th.cellIndex;
            $tableBody.querySelectorAll('tr').forEach(function (tr) {
                var c = tr.children[col];
                if (c) c.style.width = nw + 'px';
            });
            if ($jcatFooterRow) {
                var fc = $jcatFooterRow.children[col];
                if (fc) fc.style.width = nw + 'px';
            }
        });
        document.addEventListener('mouseup', function () {
            if (!jcatResizing) return;
            var widths = jcatLoadJson(JCAT_STORAGE_WIDTHS, {});
            widths[jcatResizing.th.getAttribute('data-col')] = jcatResizing.th.offsetWidth;
            jcatSaveJson(JCAT_STORAGE_WIDTHS, widths);
            jcatResizing = null;
        });
    }

    initJcatTableColumns();
    initCreateDropdown();
    syncDeleteCatalogueBtn();
    function jcatCloseCreateMenu() {
        var menu = document.getElementById('jcatCreateMenu');
        var dd = document.getElementById('jcatCreateDropdown');
        if (menu) menu.classList.remove('show');
        if (dd) dd.classList.remove('show');
    }

    var jcatNewSo = document.getElementById('jcatNewSaleOrder');
    if (jcatNewSo) {
        jcatNewSo.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            jcatCloseCreateMenu();
            goToSaleOrderWithSelection();
        });
    }
    var jcatNewSq = document.getElementById('jcatNewSaleQuotation');
    if (jcatNewSq) {
        jcatNewSq.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            jcatCloseCreateMenu();
            goToSaleQuotationWithSelection();
        });
    }
    var jcatSendWa = document.getElementById('jcatSendWhatsApp');
    if (jcatSendWa) {
        jcatSendWa.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            jcatCloseCreateMenu();
            jcatSendSelectedToWhatsAppWeb();
        });
    }
    var jcatSendMailBtn = document.getElementById('jcatSendMail');
    if (jcatSendMailBtn) {
        jcatSendMailBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            jcatCloseCreateMenu();
            jcatSendSelectedToMail();
        });
    }
    setView(viewMode);
    bindJcatDetailsModal();
    loadCatalog();
})();
</script>
<script src="assets/js/jewelry-catalogue-excel-import.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/jewelry-catalogue-excel-import.js'); ?>"></script>
</body>
</html>
